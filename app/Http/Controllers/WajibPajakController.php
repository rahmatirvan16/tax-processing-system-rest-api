<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWajibPajakRequest;
use App\Http\Requests\UpdateWajibPajakRequest;
use App\Models\User;
use App\Models\WajibPajak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class WajibPajakController extends Controller
{
    #[OA\Get(
        path: '/wajib-pajak',
        summary: 'Daftar wajib pajak (pencarian & filter)',
        tags: ['Wajib Pajak'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Cari berdasarkan nama, NIK, NPWP, atau NIB', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'jenis', in: 'query', description: 'Filter jenis: INDIVIDU / BADAN', schema: new OA\Schema(type: 'string', enum: ['INDIVIDU', 'BADAN'])),
            new OA\Parameter(name: 'status_aktif', in: 'query', description: 'Filter status aktif: true/false', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar wajib pajak'),
            new OA\Response(response: 401, description: 'Tidak terautentikasi', content: new OA\JsonContent(ref: '#/components/schemas/Error401')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = WajibPajak::query();

        // WAJIB_PAJAK hanya boleh melihat datanya sendiri.
        if ($user->isWajibPajak()) {
            $query->where('id', $user->wajib_pajak_id);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('npwp', 'like', "%{$search}%")
                    ->orWhere('nib', 'like', "%{$search}%");
            });
        }

        if ($jenis = $request->query('jenis')) {
            $query->where('jenis', $jenis);
        }

        if ($request->filled('status_aktif')) {
            $query->where('status_aktif', $request->boolean('status_aktif'));
        }

        $data = $query->latest()->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[OA\Get(
        path: '/wajib-pajak/me',
        summary: 'Data wajib pajak milik sendiri (tanpa perlu ID)',
        tags: ['Wajib Pajak'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Data wajib pajak yang sedang login'),
            new OA\Response(response: 403, description: 'Bukan akun wajib pajak', content: new OA\JsonContent(ref: '#/components/schemas/Error403')),
            new OA\Response(response: 404, description: 'Data wajib pajak tidak ditemukan', content: new OA\JsonContent(ref: '#/components/schemas/Error404')),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isWajibPajak()) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint ini hanya untuk role WAJIB_PAJAK.',
            ], 403);
        }

        $wajibPajak = WajibPajak::findOrFail($user->wajib_pajak_id);

        return response()->json([
            'success' => true,
            'data' => $wajibPajak->loadCount('kewajibanPajak'),
        ]);
    }

    #[OA\Get(
        path: '/wajib-pajak/{id}',
        summary: 'Detail wajib pajak',
        tags: ['Wajib Pajak'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail wajib pajak'),
            new OA\Response(response: 403, description: 'Tidak berhak', content: new OA\JsonContent(ref: '#/components/schemas/Error403')),
            new OA\Response(response: 404, description: 'Tidak ditemukan', content: new OA\JsonContent(ref: '#/components/schemas/Error404')),
        ]
    )]
    public function show(Request $request, WajibPajak $wajibPajak): JsonResponse
    {
        $this->pastikanBolehAkses($request, $wajibPajak);

        return response()->json([
            'success' => true,
            'data' => $wajibPajak->loadCount('kewajibanPajak'),
        ]);
    }

    #[OA\Post(
        path: '/wajib-pajak',
        summary: 'Tambah wajib pajak baru',
        tags: ['Wajib Pajak'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['jenis', 'nama', 'npwp', 'username', 'password'],
                properties: [
                    new OA\Property(property: 'jenis', type: 'string', enum: ['INDIVIDU', 'BADAN'], example: 'INDIVIDU', description: 'INDIVIDU atau BADAN'),
                    new OA\Property(property: 'nama', type: 'string', example: 'Budi Santoso'),
                    new OA\Property(property: 'nik', type: 'string', example: '1171010101900001', description: 'Wajib & hanya untuk INDIVIDU (16 digit)'),
                    new OA\Property(property: 'npwp', type: 'string', example: '091234567890123', description: 'Wajib untuk semua jenis (15-16 digit)'),
                    new OA\Property(property: 'nib', type: 'string', example: '1234567890123', description: 'Wajib & hanya untuk BADAN (9-30 digit)'),
                    new OA\Property(property: 'email', type: 'string', example: 'budi@example.com'),
                    new OA\Property(property: 'telepon', type: 'string', example: '081234567890'),
                    new OA\Property(property: 'alamat', type: 'string', example: 'Jl. Merdeka No. 1'),
                    new OA\Property(property: 'status_aktif', type: 'boolean', example: true),
                    new OA\Property(property: 'username', type: 'string', example: 'budi', description: 'Username untuk login wajib pajak'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Rahasia@2026', description: 'Password minimal 8 karakter'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi gagal', content: new OA\JsonContent(ref: '#/components/schemas/Error422')),
        ]
    )]
    public function store(StoreWajibPajakRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['status_aktif'] = $validated['status_aktif'] ?? true;

        $wajibPajak = WajibPajak::create(
            collect($validated)->except(['username', 'password'])->all()
        );

        $user = User::create([
            'name'           => $wajibPajak->nama,
            'username'       => $validated['username'],
            'password'       => $validated['password'],
            'role'           => 'WAJIB_PAJAK',
            'wajib_pajak_id' => $wajibPajak->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Wajib pajak berhasil ditambahkan.',
            'data'    => $wajibPajak,
            'akun'    => [
                'username' => $user->username,
                'role'     => $user->role,
            ],
        ], 201);
    }

    #[OA\Put(
        path: '/wajib-pajak/{id}',
        summary: 'Perbarui data wajib pajak',
        tags: ['Wajib Pajak'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'jenis', type: 'string', enum: ['INDIVIDU', 'BADAN'], example: 'INDIVIDU'),
                    new OA\Property(property: 'nama', type: 'string', example: 'Budi Santoso'),
                    new OA\Property(property: 'nik', type: 'string', example: '1171010101900001'),
                    new OA\Property(property: 'npwp', type: 'string', example: '091234567890123'),
                    new OA\Property(property: 'nib', type: 'string', example: '1234567890123'),
                    new OA\Property(property: 'email', type: 'string', example: 'budi@example.com'),
                    new OA\Property(property: 'telepon', type: 'string', example: '081234567890'),
                    new OA\Property(property: 'alamat', type: 'string', example: 'Jl. Merdeka No. 1'),
                    new OA\Property(property: 'status_aktif', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Berhasil diperbarui'),
            new OA\Response(response: 422, description: 'Validasi gagal', content: new OA\JsonContent(ref: '#/components/schemas/Error422')),
        ]
    )]
    public function update(UpdateWajibPajakRequest $request, WajibPajak $wajibPajak): JsonResponse
    {
        $wajibPajak->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data wajib pajak berhasil diperbarui.',
            'data' => $wajibPajak,
        ]);
    }

    #[OA\Delete(
        path: '/wajib-pajak/{id}',
        summary: 'Hapus wajib pajak',
        tags: ['Wajib Pajak'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Berhasil dihapus'),
            new OA\Response(response: 403, description: 'Tidak berhak', content: new OA\JsonContent(ref: '#/components/schemas/Error403')),
        ]
    )]
    public function destroy(WajibPajak $wajibPajak): JsonResponse
    {
        $wajibPajak->delete();

        return response()->json([
            'success' => true,
            'message' => 'Wajib pajak berhasil dihapus.',
        ]);
    }

    /**
     * Pastikan WAJIB_PAJAK hanya mengakses datanya sendiri.
     */
    private function pastikanBolehAkses(Request $request, WajibPajak $wajibPajak): void
    {
        $user = $request->user();

        if ($user->isWajibPajak() && $user->wajib_pajak_id !== $wajibPajak->id) {
            abort(403, 'Anda hanya dapat mengakses data milik sendiri.');
        }
    }
}
