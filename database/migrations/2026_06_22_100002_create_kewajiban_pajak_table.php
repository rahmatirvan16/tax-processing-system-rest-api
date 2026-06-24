<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kewajiban_pajak', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wajib_pajak_id')->constrained('wajib_pajak')->cascadeOnDelete();
            // Jenis pajak, mis: PBB, PPh, PPN, dll
            $table->string('jenis_pajak');
            $table->string('masa_pajak')->nullable(); // periode kewajiban, mis: 2026-01
            // Pokok pajak yang menjadi kewajiban
            $table->decimal('pokok_pajak', 18, 2);
            $table->date('jatuh_tempo');
            // Status kewajiban dihitung dari akumulasi pembayaran
            $table->enum('status', ['BELUM_LUNAS', 'LUNAS', 'LEBIH_BAYAR'])->default('BELUM_LUNAS');
            $table->timestamps();

            $table->index('jenis_pajak');
            $table->index('jatuh_tempo');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kewajiban_pajak');
    }
};
