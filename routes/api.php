<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DendaController;
use App\Http\Controllers\KewajibanPajakController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WajibPajakController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (tanpa prefix /api, sesuai spesifikasi soal)
|--------------------------------------------------------------------------
| Role:
|  - ADMIN        : akses penuh
|  - PETUGAS      : input data & laporan
|  - WAJIB_PAJAK  : hanya melihat data miliknya sendiri
*/

// Publik
Route::post('/auth/login', [AuthController::class, 'login']);

// Semua endpoint di bawah wajib menyertakan header Authorization: Bearer <token>
Route::middleware('auth:api')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Manajemen User & Audit Log (hanya ADMIN)
    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        Route::get('/audit-log', [AuditLogController::class, 'index']);
    });

    // Wajib Pajak
    Route::get('/wajib-pajak', [WajibPajakController::class, 'index']);
    Route::get('/wajib-pajak/me', [WajibPajakController::class, 'me']);
    Route::get('/wajib-pajak/{wajibPajak}', [WajibPajakController::class, 'show']);
    Route::post('/wajib-pajak', [WajibPajakController::class, 'store'])->middleware('role:ADMIN,PETUGAS');
    Route::put('/wajib-pajak/{wajibPajak}', [WajibPajakController::class, 'update'])->middleware('role:ADMIN,PETUGAS');
    Route::delete('/wajib-pajak/{wajibPajak}', [WajibPajakController::class, 'destroy'])->middleware('role:ADMIN');

    // Kewajiban Pajak
    Route::get('/kewajiban-pajak', [KewajibanPajakController::class, 'index']);
    Route::get('/kewajiban-pajak/{kewajibanPajak}', [KewajibanPajakController::class, 'show']);
    Route::post('/kewajiban-pajak', [KewajibanPajakController::class, 'store'])->middleware('role:ADMIN,PETUGAS');

    // Pembayaran (denda dihitung otomatis)
    Route::post('/pembayaran', [PembayaranController::class, 'store'])->middleware('role:ADMIN,PETUGAS');

    // Denda
    Route::get('/denda/{id}', [DendaController::class, 'show']);

    // Laporan
    Route::get('/laporan', [LaporanController::class, 'index'])->middleware('role:ADMIN,PETUGAS');
});
