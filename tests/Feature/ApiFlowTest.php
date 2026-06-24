<?php

namespace Tests\Feature;

use App\Models\KewajibanPajak;
use App\Models\User;
use App\Models\WajibPajak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiFlowTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'password' => Hash::make('Pretest@2025'),
            'role' => 'ADMIN',
        ]);

        return $this->postJson('/auth/login', [
            'username' => 'admin',
            'password' => 'Pretest@2025',
        ])->json('token');
    }

    public function test_login_berhasil_mengembalikan_token(): void
    {
        $this->adminToken();

        $this->postJson('/auth/login', ['username' => 'admin', 'password' => 'Pretest@2025'])
            ->assertOk()
            ->assertJsonStructure(['success', 'token', 'expires_in', 'user']);
    }

    public function test_login_gagal_kredensial_salah(): void
    {
        $this->adminToken();

        $this->postJson('/auth/login', ['username' => 'admin', 'password' => 'salah'])
            ->assertStatus(401);
    }

    public function test_endpoint_dilindungi_tanpa_token_ditolak(): void
    {
        $this->getJson('/wajib-pajak')->assertStatus(401);
    }

    public function test_buat_wajib_pajak_individu_validasi_nik(): void
    {
        $token = $this->adminToken();

        // NIK tidak valid (bukan 16 digit)
        $this->withToken($token)->postJson('/wajib-pajak', [
            'jenis' => 'INDIVIDU',
            'nama' => 'Andi',
            'nik' => '123',
        ])->assertStatus(422)->assertJsonValidationErrors('nik');

        // NIK valid
        $this->withToken($token)->postJson('/wajib-pajak', [
            'jenis'    => 'INDIVIDU',
            'nama'     => 'Andi',
            'nik'      => '1171010101900099',
            'npwp'     => '091234567890001',
            'username' => 'andi',
            'password' => 'Rahasia@2026',
        ])->assertStatus(201);
    }

    public function test_pembayaran_menghitung_denda_otomatis(): void
    {
        $token = $this->adminToken();

        $wp = WajibPajak::create([
            'jenis' => 'BADAN',
            'nama' => 'PT Test',
            'npwp' => '091234567890999',
            'status_aktif' => true,
        ]);

        $kewajiban = KewajibanPajak::create([
            'wajib_pajak_id' => $wp->id,
            'jenis_pajak' => 'PPh',
            'pokok_pajak' => 5_000_000,
            'jatuh_tempo' => '2026-03-31',
        ]);

        $resp = $this->withToken($token)->postJson('/pembayaran', [
            'kewajiban_pajak_id' => $kewajiban->id,
            'nominal' => 4_000_000,
            'tanggal_bayar' => '2026-04-10',
        ])->assertStatus(201);

        $resp->assertJsonPath('data.denda.denda_telat', '100000.00');
        $resp->assertJsonPath('data.denda.denda_kurang', '10000.00');
        $resp->assertJsonPath('data.denda.total_denda', '110000.00');
        $resp->assertJsonPath('data.pembayaran.status', 'KURANG_BAYAR');
    }

    public function test_pembayaran_nominal_nol_ditolak(): void
    {
        $token = $this->adminToken();

        $wp = WajibPajak::create(['jenis' => 'BADAN', 'nama' => 'PT X', 'npwp' => '091234567890888', 'status_aktif' => true]);
        $kewajiban = KewajibanPajak::create([
            'wajib_pajak_id' => $wp->id, 'jenis_pajak' => 'PPN', 'pokok_pajak' => 1_000_000, 'jatuh_tempo' => '2026-03-31',
        ]);

        $this->withToken($token)->postJson('/pembayaran', [
            'kewajiban_pajak_id' => $kewajiban->id,
            'nominal' => 0,
            'tanggal_bayar' => '2026-03-10',
        ])->assertStatus(422)->assertJsonValidationErrors('nominal');
    }

    public function test_pembayaran_tanggal_masa_depan_ditolak(): void
    {
        $token = $this->adminToken();

        $wp = WajibPajak::create(['jenis' => 'BADAN', 'nama' => 'PT Y', 'npwp' => '091234567890777', 'status_aktif' => true]);
        $kewajiban = KewajibanPajak::create([
            'wajib_pajak_id' => $wp->id, 'jenis_pajak' => 'PPN', 'pokok_pajak' => 1_000_000, 'jatuh_tempo' => '2026-03-31',
        ]);

        $this->withToken($token)->postJson('/pembayaran', [
            'kewajiban_pajak_id' => $kewajiban->id,
            'nominal' => 500_000,
            'tanggal_bayar' => now()->addDays(5)->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('tanggal_bayar');
    }

    public function test_pembayaran_wajib_pajak_nonaktif_ditolak(): void
    {
        $token = $this->adminToken();

        $wp = WajibPajak::create(['jenis' => 'BADAN', 'nama' => 'PT Mati', 'npwp' => '091234567890666', 'status_aktif' => false]);
        $kewajiban = KewajibanPajak::create([
            'wajib_pajak_id' => $wp->id, 'jenis_pajak' => 'PPN', 'pokok_pajak' => 1_000_000, 'jatuh_tempo' => '2026-03-31',
        ]);

        $this->withToken($token)->postJson('/pembayaran', [
            'kewajiban_pajak_id' => $kewajiban->id,
            'nominal' => 500_000,
            'tanggal_bayar' => '2026-03-10',
        ])->assertStatus(409);
    }

    public function test_wajib_pajak_hanya_lihat_data_sendiri(): void
    {
        $this->adminToken();

        $wpA = WajibPajak::create(['jenis' => 'INDIVIDU', 'nama' => 'A', 'nik' => '1111111111111111', 'status_aktif' => true]);
        $wpB = WajibPajak::create(['jenis' => 'INDIVIDU', 'nama' => 'B', 'nik' => '2222222222222222', 'status_aktif' => true]);

        $userA = User::create([
            'name' => 'A', 'username' => 'usera', 'password' => Hash::make('rahasia'),
            'role' => 'WAJIB_PAJAK', 'wajib_pajak_id' => $wpA->id,
        ]);
        $tokenA = $this->postJson('/auth/login', ['username' => 'usera', 'password' => 'rahasia'])->json('token');

        // Hanya melihat dirinya sendiri di list
        $this->withToken($tokenA)->getJson('/wajib-pajak')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $wpA->id)
            ->assertJsonCount(1, 'data.data');

        // Tidak boleh melihat data WP lain
        $this->withToken($tokenA)->getJson("/wajib-pajak/{$wpB->id}")->assertStatus(403);
    }

    public function test_petugas_tidak_boleh_hapus_wajib_pajak(): void
    {
        $this->adminToken();

        $wp = WajibPajak::create(['jenis' => 'INDIVIDU', 'nama' => 'C', 'nik' => '3333333333333333', 'status_aktif' => true]);

        User::create([
            'name' => 'Petugas', 'username' => 'petugas', 'password' => Hash::make('rahasia'), 'role' => 'PETUGAS',
        ]);
        $token = $this->postJson('/auth/login', ['username' => 'petugas', 'password' => 'rahasia'])->json('token');

        // DELETE hanya untuk ADMIN
        $this->withToken($token)->deleteJson("/wajib-pajak/{$wp->id}")->assertStatus(403);
    }
}
