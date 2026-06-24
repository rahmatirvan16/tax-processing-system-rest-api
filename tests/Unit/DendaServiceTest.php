<?php

namespace Tests\Unit;

use App\Models\KewajibanPajak;
use App\Services\DendaService;
use Tests\TestCase;

class DendaServiceTest extends TestCase
{
    private function kewajiban(float $pokok, string $jatuhTempo): KewajibanPajak
    {
        $k = new KewajibanPajak();
        $k->pokok_pajak = $pokok;
        $k->jatuh_tempo = $jatuhTempo;

        return $k;
    }

    public function test_tepat_waktu_dan_lunas_tanpa_denda(): void
    {
        $service = new DendaService();
        $hasil = $service->hitung($this->kewajiban(1_000_000, '2026-03-31'), 1_000_000, '2026-03-20');

        $this->assertSame(0.0, $hasil['denda_telat']);
        $this->assertSame(0.0, $hasil['denda_kurang']);
        $this->assertSame(0.0, $hasil['total_denda']);
        $this->assertSame('LUNAS', $hasil['status_pembayaran']);
    }

    public function test_telat_bayar_denda_2_persen_pokok(): void
    {
        $service = new DendaService();
        // Bayar penuh tapi setelah jatuh tempo -> hanya denda telat 2% x 1jt = 20.000
        $hasil = $service->hitung($this->kewajiban(1_000_000, '2026-03-31'), 1_000_000, '2026-04-05');

        $this->assertSame(20_000.0, $hasil['denda_telat']);
        $this->assertSame(0.0, $hasil['denda_kurang']);
        $this->assertSame(20_000.0, $hasil['total_denda']);
        $this->assertTrue($hasil['is_telat']);
        $this->assertFalse($hasil['is_kurang_bayar']);
    }

    public function test_kurang_bayar_denda_1_persen_selisih(): void
    {
        $service = new DendaService();
        // Bayar 800rb tepat waktu dari kewajiban 1jt -> kurang 200rb -> denda 1% x 200rb = 2.000
        $hasil = $service->hitung($this->kewajiban(1_000_000, '2026-03-31'), 800_000, '2026-03-10');

        $this->assertSame(0.0, $hasil['denda_telat']);
        $this->assertSame(2_000.0, $hasil['denda_kurang']);
        $this->assertSame(2_000.0, $hasil['total_denda']);
        $this->assertSame('KURANG_BAYAR', $hasil['status_pembayaran']);
    }

    public function test_denda_gabungan_telat_dan_kurang_bayar(): void
    {
        $service = new DendaService();
        // Pokok 5jt, bayar 4jt setelah jatuh tempo:
        // telat = 2% x 5jt = 100.000 ; kurang = 1% x 1jt = 10.000 ; total 110.000
        $hasil = $service->hitung($this->kewajiban(5_000_000, '2026-03-31'), 4_000_000, '2026-04-10');

        $this->assertSame(100_000.0, $hasil['denda_telat']);
        $this->assertSame(10_000.0, $hasil['denda_kurang']);
        $this->assertSame(110_000.0, $hasil['total_denda']);
        $this->assertTrue($hasil['is_telat']);
        $this->assertTrue($hasil['is_kurang_bayar']);
        $this->assertSame('KURANG_BAYAR', $hasil['status_pembayaran']);
    }

    public function test_lebih_bayar(): void
    {
        $service = new DendaService();
        $hasil = $service->hitung($this->kewajiban(1_000_000, '2026-03-31'), 1_200_000, '2026-03-10');

        $this->assertSame('LEBIH_BAYAR', $hasil['status_pembayaran']);
        $this->assertSame(0.0, $hasil['total_denda']);
    }

    public function test_pembayaran_kumulatif(): void
    {
        $service = new DendaService();
        // Sudah dibayar 600rb, sekarang bayar 400rb tepat waktu -> lunas, tanpa denda.
        $hasil = $service->hitung($this->kewajiban(1_000_000, '2026-03-31'), 400_000, '2026-03-10', 600_000);

        $this->assertSame('LUNAS', $hasil['status_pembayaran']);
        $this->assertSame(0.0, $hasil['total_denda']);
        $this->assertSame(1_000_000.0, $hasil['total_dibayar']);
    }
}
