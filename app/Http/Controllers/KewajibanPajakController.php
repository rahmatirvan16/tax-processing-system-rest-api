<?php

namespace App\Http\Controllers;

use App\Models\KewajibanPajak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class KewajibanPajakController extends Controller
{
    #[OA\Get(
        path: '/kewajiban-pajak',
        summary: 'Daftar kewajiban pajak per wajib pajak',
        tags: ['Kewajiban Pajak'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'wajib_pajak_id', in: 'query', description: 'Filter berdasarkan wajib pajak', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'jenis_pajak', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['BELUM_LUNAS', 'LUNAS', 'LEBIH_BAYAR'])),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar kewajiban pajak'),
            new OA\Response(response: 401, description: 'Tidak terautentikasi', content: new OA\JsonContent(ref: '#/components/schemas/Error401')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = KewajibanPajak::query()->with('wajibPajak')->withSum('pembayaran', 'nominal');

        // WAJIB_PAJAK hanya melihat kewajiban miliknya sendiri.
        if ($user->isWajibPajak()) {
            $query->where('wajib_pajak_id', $user->wajib_pajak_id);
        } elseif ($wpId = $request->query('wajib_pajak_id')) {
            $query->where('wajib_pajak_id', $wpId);
        }

        if ($jenis = $request->query('jenis_pajak')) {
            $query->where('jenis_pajak', $jenis);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $data = $query->latest()->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[OA\Post(
        path: '/kewajiban-pajak',
        summary: 'Tambah kewajiban pajak (ADMIN/PETUGAS)',
        tags: ['Kewajiban Pajak'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['wajib_pajak_id', 'jenis_pajak', 'pokok_pajak', 'jatuh_tempo'],
                properties: [
                    new OA\Property(property: 'wajib_pajak_id', type: 'integer', example: 1),
                    new OA\Property(property: 'jenis_pajak', type: 'string', example: 'PBB'),
                    new OA\Property(property: 'masa_pajak', type: 'string', example: '2026-01'),
                    new OA\Property(property: 'pokok_pajak', type: 'number', example: 1000000),
                    new OA\Property(property: 'jatuh_tempo', type: 'string', format: 'date', example: '2026-03-31'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi gagal', content: new OA\JsonContent(ref: '#/components/schemas/Error422')),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wajib_pajak_id' => ['required', 'exists:wajib_pajak,id'],
            'jenis_pajak' => ['required', 'string', 'max:100'],
            'masa_pajak' => ['nullable', 'string', 'max:20'],
            'pokok_pajak' => ['required', 'numeric', 'gt:0'],
            'jatuh_tempo' => ['required', 'date'],
        ]);

        $kewajiban = KewajibanPajak::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Kewajiban pajak berhasil ditambahkan.',
            'data' => $kewajiban,
        ], 201);
    }

    #[OA\Get(
        path: '/kewajiban-pajak/{id}',
        summary: 'Detail kewajiban pajak beserta pembayaran',
        tags: ['Kewajiban Pajak'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail kewajiban'),
            new OA\Response(response: 403, description: 'Tidak berhak', content: new OA\JsonContent(ref: '#/components/schemas/Error403')),
            new OA\Response(response: 404, description: 'Tidak ditemukan', content: new OA\JsonContent(ref: '#/components/schemas/Error404')),
        ]
    )]
    public function show(Request $request, KewajibanPajak $kewajibanPajak): JsonResponse
    {
        $user = $request->user();

        if ($user->isWajibPajak() && $kewajibanPajak->wajib_pajak_id !== $user->wajib_pajak_id) {
            abort(403, 'Anda hanya dapat mengakses data milik sendiri.');
        }

        $kewajibanPajak->load(['wajibPajak', 'pembayaran.denda']);
        $totalDibayar = $kewajibanPajak->totalDibayar();

        return response()->json([
            'success' => true,
            'data' => $kewajibanPajak,
            'ringkasan' => [
                'total_dibayar' => $totalDibayar,
                'sisa_kewajiban' => max(0, (float) $kewajibanPajak->pokok_pajak - $totalDibayar),
            ],
        ]);
    }
}
