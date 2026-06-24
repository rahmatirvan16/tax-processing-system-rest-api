<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DendaController extends Controller
{
    #[OA\Get(
        path: '/denda/{id}',
        summary: 'Hitung & tampilkan denda otomatis untuk sebuah pembayaran',
        description: 'Parameter {id} adalah ID transaksi pembayaran. Mengembalikan rincian denda telat bayar (2%) dan denda kurang bayar (1%) yang dihitung otomatis saat pembayaran dicatat.',
        tags: ['Denda'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID pembayaran', schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Rincian denda'),
            new OA\Response(response: 403, description: 'Tidak berhak', content: new OA\JsonContent(ref: '#/components/schemas/Error403')),
            new OA\Response(response: 404, description: 'Tidak ditemukan', content: new OA\JsonContent(ref: '#/components/schemas/Error404')),
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $pembayaran = Pembayaran::with(['denda', 'kewajibanPajak.wajibPajak'])->findOrFail($id);

        // WAJIB_PAJAK hanya boleh melihat denda miliknya sendiri.
        $user = $request->user();
        if ($user->isWajibPajak() && $pembayaran->kewajibanPajak->wajib_pajak_id !== $user->wajib_pajak_id) {
            abort(403, 'Anda hanya dapat mengakses data milik sendiri.');
        }

        $denda = $pembayaran->denda;

        $pokok        = (float) $pembayaran->kewajibanPajak->pokok_pajak;
        $totalDibayar = (float) $pembayaran->kewajibanPajak->pembayaran()
            ->where('id', '!=', $pembayaran->id)
            ->sum('nominal');
        $sisaPokok    = max(0.0, $pokok - ($totalDibayar + (float) $pembayaran->nominal));

        return response()->json([
            'success' => true,
            'data' => [
                'pembayaran_id'      => $pembayaran->id,
                'kewajiban_pajak_id' => $pembayaran->kewajiban_pajak_id,
                'jenis_pajak'        => $pembayaran->kewajibanPajak->jenis_pajak,
                'pokok_pajak'        => $pokok,
                'total_dibayar'      => round($totalDibayar, 2),
                'sisa_pokok'         => round($sisaPokok, 2),
                'jatuh_tempo'        => $pembayaran->kewajibanPajak->jatuh_tempo->toDateString(),
                'tanggal_bayar'      => $pembayaran->tanggal_bayar->toDateString(),
                'nominal_dibayar'    => (float) $pembayaran->nominal,
                'denda_telat'        => (float) ($denda->denda_telat ?? 0),
                'denda_kurang'       => (float) ($denda->denda_kurang ?? 0),
                'total_denda'        => (float) ($denda->total_denda ?? 0),
                'is_telat'           => (bool) ($denda->is_telat ?? false),
                'is_kurang_bayar'    => (bool) ($denda->is_kurang_bayar ?? false),
                'keterangan'         => $denda->keterangan ?? 'Tidak ada denda.',
            ],
        ]);
    }
}
