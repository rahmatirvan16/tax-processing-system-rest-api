<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wajib_pajak', function (Blueprint $table) {
            $table->id();
            // INDIVIDU (perorangan, identitas NIK) atau BADAN (badan usaha, NPWP/NIB)
            $table->enum('jenis', ['INDIVIDU', 'BADAN']);
            $table->string('nama');
            // NIK 16 digit untuk individu
            $table->string('nik', 16)->nullable()->unique();
            // NPWP untuk badan usaha (15/16 digit)
            $table->string('npwp', 25)->nullable()->unique();
            // NIB sebagai alternatif identitas badan usaha
            $table->string('nib', 30)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('telepon', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();

            $table->index('nama');
            $table->index('jenis');
            $table->index('status_aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wajib_pajak');
    }
};
