<?php

namespace Database\Seeders;

use App\Models\Denda;
use App\Models\KewajibanPajak;
use App\Models\Pembayaran;
use App\Models\User;
use App\Models\WajibPajak;
use App\Services\DendaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $dendaService = new DendaService();

        // ----- Users / akun -----
        // Kredensial pengujian sesuai soal: admin / Pretest@2025
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'email' => 'admin@tps.test',
                'password' => Hash::make('Pretest@2025'),
                'role' => 'ADMIN',
            ]
        );

        User::updateOrCreate(
            ['username' => 'petugas'],
            [
                'name' => 'Petugas Pajak',
                'email' => 'petugas@tps.test',
                'password' => Hash::make('Petugas@2025'),
                'role' => 'PETUGAS',
            ]
        );

        // ----- Wajib Pajak -----
        $individu = WajibPajak::updateOrCreate(
            ['nik' => '1171010101900001'],
            [
                'jenis' => 'INDIVIDU',
                'nama' => 'Budi Santoso',
                'email' => 'budi@example.com',
                'telepon' => '081234567890',
                'alamat' => 'Jl. Merdeka No. 1, Banda Aceh',
                'status_aktif' => true,
            ]
        );

        $badan = WajibPajak::updateOrCreate(
            ['npwp' => '091234567890123'],
            [
                'jenis' => 'BADAN',
                'nama' => 'PT Maju Bersama',
                'nib' => '1234567890123',
                'email' => 'info@majubersama.co.id',
                'telepon' => '0651123456',
                'alamat' => 'Jl. Sudirman No. 10, Banda Aceh',
                'status_aktif' => true,
            ]
        );

        // Akun WAJIB_PAJAK untuk user Budi Santoso.
        User::updateOrCreate(
            ['username' => 'budi'],
            [
                'name' => 'Budi Santoso',
                'email' => 'budi@example.com',
                'password' => Hash::make('Wajib@2025'),
                'role' => 'WAJIB_PAJAK',
            ]
        );

        // ----- Kewajiban Pajak + Pembayaran contoh -----
        // Kewajiban 1: dibayar lunas & tepat waktu (tanpa denda).
        $k1 = KewajibanPajak::create([
            'wajib_pajak_id' => $individu->id,
            'jenis_pajak' => 'PBB',
            'masa_pajak' => '2026-01',
            'pokok_pajak' => 1000000,
            'jatuh_tempo' => '2026-03-31',
        ]);
        $this->catatPembayaran($dendaService, $k1, 1000000, '2026-03-20');

        // Kewajiban 2: telat + kurang bayar (denda gabungan).
        $k2 = KewajibanPajak::create([
            'wajib_pajak_id' => $badan->id,
            'jenis_pajak' => 'PPh',
            'masa_pajak' => '2026-02',
            'pokok_pajak' => 5000000,
            'jatuh_tempo' => '2026-03-31',
        ]);
        // Bayar 4.000.000 pada 2026-04-10 -> telat (2% x 5jt) + kurang (1% x 1jt).
        $this->catatPembayaran($dendaService, $k2, 4000000, '2026-04-10');
    }

    /**
     * Buat pembayaran + denda otomatis (mereplikasi logika controller).
     */
    private function catatPembayaran(DendaService $service, KewajibanPajak $kewajiban, float $nominal, string $tanggal): void
    {
        $totalSebelumnya = (float) $kewajiban->pembayaran()->sum('nominal');
        $perhitungan = $service->hitung($kewajiban, $nominal, $tanggal, $totalSebelumnya);

        $pembayaran = Pembayaran::create([
            'kewajiban_pajak_id' => $kewajiban->id,
            'nominal' => $nominal,
            'tanggal_bayar' => $tanggal,
            'status' => $perhitungan['status_pembayaran'],
            'keterangan' => 'Data contoh seeder',
        ]);

        Denda::create([
            'pembayaran_id' => $pembayaran->id,
            'denda_telat' => $perhitungan['denda_telat'],
            'denda_kurang' => $perhitungan['denda_kurang'],
            'total_denda' => $perhitungan['total_denda'],
            'is_telat' => $perhitungan['is_telat'],
            'is_kurang_bayar' => $perhitungan['is_kurang_bayar'],
            'keterangan' => $perhitungan['keterangan'],
        ]);

        $kewajiban->update([
            'status' => $service->statusKewajiban((float) $kewajiban->pokok_pajak, $perhitungan['total_dibayar']),
        ]);
    }
}
