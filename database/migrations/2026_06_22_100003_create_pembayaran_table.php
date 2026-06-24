<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kewajiban_pajak_id')->constrained('kewajiban_pajak')->cascadeOnDelete();
            // Nominal yang dibayarkan pada transaksi ini
            $table->decimal('nominal', 18, 2);
            $table->date('tanggal_bayar');
            // Status hasil evaluasi terhadap kewajiban: LUNAS / KURANG_BAYAR / LEBIH_BAYAR
            $table->enum('status', ['LUNAS', 'KURANG_BAYAR', 'LEBIH_BAYAR']);
            $table->text('keterangan')->nullable();
            // Audit: user (petugas/admin) yang mencatat pembayaran
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tanggal_bayar');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
