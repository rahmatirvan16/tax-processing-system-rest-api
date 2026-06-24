<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Tax Processing System (TPS) REST API',
    description: "REST API pengelolaan kewajiban pajak wajib pajak: data wajib pajak, transaksi pembayaran, penghitungan denda otomatis, dan laporan per periode.\n\n**Akun Bawaan untuk Pengujian**\n\n| Username | Password | Role |\n|---|---|---|\n| `admin` | `Pretest@2025` | ADMIN — Akses penuh |\n| `petugas` | `Petugas@2025` | PETUGAS — Input data & laporan |\n| `budi` | `Wajib@2025` | WAJIB\\_PAJAK — Hanya data sendiri |",
    contact: new OA\Contact(name: 'TPS Backend')
)]
#[OA\Server(url: 'http://localhost:8000', description: 'Local development server')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Masukkan token JWT yang diperoleh dari POST /auth/login.'
)]
#[OA\Tag(name: 'Auth', description: 'Autentikasi & token JWT')]
#[OA\Tag(name: 'User', description: 'Manajemen user sistem (khusus ADMIN)')]
#[OA\Tag(name: 'Wajib Pajak', description: 'Pengelolaan data wajib pajak')]
#[OA\Tag(name: 'Kewajiban Pajak', description: 'Daftar kewajiban pajak per wajib pajak')]
#[OA\Tag(name: 'Pembayaran', description: 'Pencatatan transaksi pembayaran pajak')]
#[OA\Tag(name: 'Denda', description: 'Penghitungan denda otomatis')]
#[OA\Tag(name: 'Laporan', description: 'Laporan pembayaran pajak per periode')]
#[OA\Tag(name: 'Audit Log', description: 'Riwayat aktivitas sistem (khusus ADMIN)')]

// ── Reusable error response schemas ──────────────────────────────────────────
#[OA\Schema(
    schema: 'Error401',
    description: 'Tidak terautentikasi / kredensial salah',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Token tidak valid atau tidak disertakan.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Error403',
    description: 'Tidak memiliki hak akses',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Anda tidak memiliki hak akses untuk melakukan tindakan ini.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Error404',
    description: 'Data tidak ditemukan',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Resource tidak ditemukan.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Error422',
    description: 'Validasi gagal',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Data yang dikirim tidak valid.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            example: ['username' => ['Username sudah digunakan.'], 'email' => ['Email sudah digunakan.']],
        ),
    ],
    type: 'object'
)]
abstract class Controller
{
    //
}
