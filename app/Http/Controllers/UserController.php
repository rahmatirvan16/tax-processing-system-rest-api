<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: '/users',
        summary: 'Daftar user (pencarian & filter role)',
        tags: ['User'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Cari berdasarkan nama, username, atau email', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'role', in: 'query', description: 'Filter role: ADMIN / PETUGAS / WAJIB_PAJAK', schema: new OA\Schema(type: 'string', enum: ['ADMIN', 'PETUGAS', 'WAJIB_PAJAK'])),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar user'),
            new OA\Response(response: 401, description: 'Tidak terautentikasi', content: new OA\JsonContent(ref: '#/components/schemas/Error401')),
            new OA\Response(response: 403, description: 'Tidak berhak', content: new OA\JsonContent(ref: '#/components/schemas/Error403')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        $data = $query->latest()->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[OA\Get(
        path: '/users/{id}',
        summary: 'Detail user',
        tags: ['User'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detail user'),
            new OA\Response(response: 404, description: 'Tidak ditemukan', content: new OA\JsonContent(ref: '#/components/schemas/Error404')),
        ]
    )]
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    #[OA\Post(
        path: '/users',
        summary: 'Tambah user baru',
        tags: ['User'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'username', 'password', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Siti Petugas'),
                    new OA\Property(property: 'username', type: 'string', example: 'siti'),
                    new OA\Property(property: 'email', type: 'string', example: 'siti@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Rahasia@2026'),
                    new OA\Property(property: 'role', type: 'string', enum: ['ADMIN', 'PETUGAS', 'WAJIB_PAJAK'], example: 'PETUGAS'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Berhasil dibuat'),
            new OA\Response(response: 422, description: 'Validasi gagal', content: new OA\JsonContent(ref: '#/components/schemas/Error422')),
        ]
    )]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::create($data);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditambahkan.',
            'data' => $user,
        ], 201);
    }

    #[OA\Put(
        path: '/users/{id}',
        summary: 'Perbarui data user',
        tags: ['User'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Siti Petugas'),
                    new OA\Property(property: 'username', type: 'string', example: 'siti'),
                    new OA\Property(property: 'email', type: 'string', example: 'siti@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Rahasia@2026'),
                    new OA\Property(property: 'role', type: 'string', enum: ['ADMIN', 'PETUGAS', 'WAJIB_PAJAK'], example: 'PETUGAS'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Berhasil diperbarui'),
            new OA\Response(response: 422, description: 'Validasi gagal', content: new OA\JsonContent(ref: '#/components/schemas/Error422')),
        ]
    )]
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Data user berhasil diperbarui.',
            'data' => $user,
        ]);
    }

    #[OA\Delete(
        path: '/users/{id}',
        summary: 'Hapus user',
        tags: ['User'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Berhasil dihapus'),
            new OA\Response(response: 403, description: 'Tidak berhak'),
            new OA\Response(response: 409, description: 'Tidak boleh menghapus akun sendiri'),
        ]
    )]
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Cegah admin menghapus akunnya sendiri.
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun yang sedang Anda gunakan.',
            ], 409);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus.',
        ]);
    }
}
