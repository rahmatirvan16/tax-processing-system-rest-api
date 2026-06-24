<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class LaporanController extends Controller
{
    #[OA\Get(
        path: '/laporan',
        summary: 'Laporan pembayaran pajak per periode',
        description: 'Menampilkan total kewajiban, total dibayar, total denda, dan sisa kewajiban dengan filter rentang tanggal, jenis pajak, dan status pembayaran.',
        tags: ['Laporan'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'wajib_pajak_id', in: 'query', description: 'Filter berdasarkan ID wajib pajak', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tanggal_mulai', in: 'query', description: 'Filter tanggal bayar mulai (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'tanggal_akhir', in: 'query', description: 'Filter tanggal bayar akhir (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'jenis_pajak', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['LUNAS', 'KURANG_BAYAR', 'LEBIH_BAYAR'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Laporan pembayaran'),
            new OA\Response(response: 401, description: 'Tidak terautentikasi', content: new OA\JsonContent(ref: '#/components/schemas/Error401')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'wajib_pajak_id' => ['nullable', 'integer', 'exists:wajib_pajak,id'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'jenis_pajak' => ['nullable', 'string'],
            'status' => ['nullable', 'in:LUNAS,KURANG_BAYAR,LEBIH_BAYAR'],
        ]);

        $query = Pembayaran::query()
            ->with(['denda', 'kewajibanPajak.wajibPajak'])
            ->when($request->query('wajib_pajak_id'), function ($q, $v) {
                $q->whereHas('kewajibanPajak', fn ($k) => $k->where('wajib_pajak_id', $v));
            })
            ->when($request->query('tanggal_mulai'), fn ($q, $v) => $q->whereDate('tanggal_bayar', '>=', $v))
            ->when($request->query('tanggal_akhir'), fn ($q, $v) => $q->whereDate('tanggal_bayar', '<=', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('jenis_pajak'), function ($q, $v) {
                $q->whereHas('kewajibanPajak', fn ($k) => $k->where('jenis_pajak', $v));
            });

        $pembayaran = $query->get();

        $totalDibayar = (float) $pembayaran->sum('nominal');
        $totalDenda = (float) $pembayaran->sum(fn ($p) => (float) ($p->denda->total_denda ?? 0));

        // Total kewajiban dihitung dari kewajiban unik yang muncul pada laporan.
        $kewajibanUnik = $pembayaran->pluck('kewajibanPajak')->filter()->unique('id');
        $totalKewajiban = (float) $kewajibanUnik->sum(fn ($k) => (float) $k->pokok_pajak);

        // Sisa kewajiban = total pokok - total seluruh pembayaran kewajiban tsb (bukan hanya yang difilter).
        $sisaKewajiban = (float) $kewajibanUnik->sum(function ($k) {
            $dibayar = (float) $k->pembayaran()->sum('nominal');
            return max(0, (float) $k->pokok_pajak - $dibayar);
        });

        return response()->json([
            'success' => true,
            'filter' => $request->only(['wajib_pajak_id', 'tanggal_mulai', 'tanggal_akhir', 'jenis_pajak', 'status']),
            'ringkasan' => [
                'total_kewajiban'  => round($totalKewajiban, 2),
                'total_dibayar'    => round($totalDibayar, 2),
                'sisa_kewajiban'   => round($sisaKewajiban, 2),
                'total_denda'      => round($totalDenda, 2),
                'total_keseluruhan' => round($sisaKewajiban + $totalDenda, 2),
                'jumlah_transaksi' => $pembayaran->count(),
            ],
            'detail' => $pembayaran->map(function ($p) {
                return [
                    'pembayaran_id' => $p->id,
                    'wajib_pajak' => $p->kewajibanPajak->wajibPajak->nama ?? null,
                    'jenis_pajak' => $p->kewajibanPajak->jenis_pajak ?? null,
                    'pokok_pajak' => (float) ($p->kewajibanPajak->pokok_pajak ?? 0),
                    'tanggal_bayar' => $p->tanggal_bayar->toDateString(),
                    'nominal' => (float) $p->nominal,
                    'status' => $p->status,
                    'total_denda' => (float) ($p->denda->total_denda ?? 0),
                ];
            }),
        ]);
    }
}
