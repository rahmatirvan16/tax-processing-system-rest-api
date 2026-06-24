<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('denda', function (Blueprint $table) {
            $table->id();
            // Denda dihitung & disimpan otomatis saat pembayaran dicatat (relasi 1-1)
            $table->foreignId('pembayaran_id')->constrained('pembayaran')->cascadeOnDelete();
            // Denda telat bayar = 2% x pokok pajak (jika melewati jatuh tempo)
            $table->decimal('denda_telat', 18, 2)->default(0);
            // Denda kurang bayar = 1% x selisih kekurangan (jika dibayar < kewajiban)
            $table->decimal('denda_kurang', 18, 2)->default(0);
            // Total denda = denda_telat + denda_kurang
            $table->decimal('total_denda', 18, 2)->default(0);
            $table->boolean('is_telat')->default(false);
            $table->boolean('is_kurang_bayar')->default(false);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('denda');
    }
};
