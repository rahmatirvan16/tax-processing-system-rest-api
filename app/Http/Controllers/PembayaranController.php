<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePembayaranRequest;
use App\Models\Denda;
use App\Models\KewajibanPajak;
use App\Models\Pembayaran;
use App\Services\DendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class PembayaranController extends Controller
{
    public function __construct(private readonly DendaService $dendaService)
    {
    }

    #[OA\Post(
        path: '/pembayaran',
        summary: 'Catat transaksi pembayaran pajak (denda dihitung otomatis)',
        tags: ['Pembayaran'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['kewajiban_pajak_id', 'nominal', 'tanggal_bayar'],
                properties: [
                    new OA\Property(property: 'kewajiban_pajak_id', type: 'integer', example: 1),
                    new OA\Property(property: 'nominal', type: 'number', example: 800000),
                    new OA\Property(property: 'tanggal_bayar', type: 'string', format: 'date', example: '2026-04-10'),
                    new OA\Property(property: 'keterangan', type: 'string', example: 'Pembayaran sebagian'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pembayaran tercatat beserta denda otomatis'),
            new OA\Response(response: 409, description: 'Wajib pajak tidak aktif', content: new OA\JsonContent(ref: '#/components/schemas/Error403')),
            new OA\Response(response: 422, description: 'Validasi gagal', content: new OA\JsonContent(ref: '#/components/schemas/Error422')),
        ]
    )]
    public function store(StorePembayaranRequest $request): JsonResponse
    {
        $data = $request->validated();

        $kewajiban = KewajibanPajak::with('wajibPajak')->findOrFail($data['kewajiban_pajak_id']);

        // Validasi: wajib pajak harus aktif.
        if (! $kewajiban->wajibPajak || ! $kewajiban->wajibPajak->status_aktif) {
            return response()->json([
                'success' => false,
                'message' => 'Wajib pajak tidak aktif. Pembayaran tidak dapat diproses.',
            ], 409);
        }

        $hasil = DB::transaction(function () use ($data, $kewajiban, $request) {
            // Total pembayaran sebelum transaksi ini (untuk hitung kekurangan kumulatif).
            $totalSebelumnya = (float) $kewajiban->pembayaran()->sum('nominal');

            $perhitungan = $this->dendaService->hitung(
                $kewajiban,
                (float) $data['nominal'],
                $data['tanggal_bayar'],
                $totalSebelumnya
            );

            $pembayaran = Pembayaran::create([
                'kewajiban_pajak_id' => $kewajiban->id,
                'nominal' => $data['nominal'],
                'tanggal_bayar' => $data['tanggal_bayar'],
                'status' => $perhitungan['status_pembayaran'],
                'keterangan' => $data['keterangan'] ?? null,
                'dicatat_oleh' => $request->user()->id,
            ]);

            // Denda dihitung & disimpan otomatis saat transaksi dicatat.
            $denda = Denda::create([
                'pembayaran_id' => $pembayaran->id,
                'denda_telat' => $perhitungan['denda_telat'],
                'denda_kurang' => $perhitungan['denda_kurang'],
                'total_denda' => $perhitungan['total_denda'],
                'is_telat' => $perhitungan['is_telat'],
                'is_kurang_bayar' => $perhitungan['is_kurang_bayar'],
                'keterangan' => $perhitungan['keterangan'],
            ]);

            // Perbarui status agregat kewajiban.
            $kewajiban->update([
                'status' => $this->dendaService->statusKewajiban(
                    (float) $kewajiban->pokok_pajak,
                    $perhitungan['total_dibayar']
                ),
            ]);

            return [
                'pembayaran' => $pembayaran,
                'denda' => $denda,
                'perhitungan' => $perhitungan,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dicatat.',
            'data' => [
                'pembayaran'     => $hasil['pembayaran'],
                'denda'          => $hasil['denda'],
                'pokok_pajak'    => (float) $kewajiban->pokok_pajak,
                'total_dibayar'  => $hasil['perhitungan']['total_dibayar'],
                'sisa_kewajiban' => $hasil['perhitungan']['kekurangan'],
            ],
        ], 201);
    }
}
