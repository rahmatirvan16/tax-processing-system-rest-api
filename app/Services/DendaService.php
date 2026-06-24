<?php

namespace App\Services;

use App\Models\KewajibanPajak;
use Carbon\Carbon;

/**
 * Service penghitungan denda pajak otomatis.
 *
 * Aturan denda (sesuai spesifikasi):
 *  - Denda Telat Bayar  = 2% x pokok pajak, bila pembayaran melewati jatuh tempo.
 *  - Denda Kurang Bayar = 1% x selisih kekurangan, bila total dibayar < pokok kewajiban.
 *  - Denda Gabungan     = kedua denda dijumlahkan bila terlambat sekaligus kurang bayar.
 *
 * Catatan desain:
 *  - "selisih kekurangan" dihitung dari akumulasi seluruh pembayaran terhadap kewajiban
 *    (termasuk pembayaran yang sedang dicatat), sehingga audit antar transaksi konsisten.
 */
class DendaService
{
    public const TARIF_TELAT = 0.02;   // 2%
    public const TARIF_KURANG = 0.01;  // 1%

    /**
     * Hitung komponen denda untuk sebuah transaksi pembayaran.
     *
     * @param  KewajibanPajak  $kewajiban  Kewajiban yang dibayar.
     * @param  float  $nominal             Nominal pembayaran transaksi ini.
     * @param  Carbon|string  $tanggalBayar Tanggal pembayaran.
     * @param  float  $totalDibayarSebelumnya Total pembayaran terdahulu (selain transaksi ini).
     * @return array{denda_telat: float, denda_kurang: float, total_denda: float, is_telat: bool, is_kurang_bayar: bool, status_pembayaran: string, kekurangan: float, total_dibayar: float, keterangan: string}
     */
    public function hitung(
        KewajibanPajak $kewajiban,
        float $nominal,
        Carbon|string $tanggalBayar,
        float $totalDibayarSebelumnya = 0.0
    ): array {
        $pokok = (float) $kewajiban->pokok_pajak;
        $jatuhTempo = Carbon::parse($kewajiban->jatuh_tempo)->startOfDay();
        $tanggal = Carbon::parse($tanggalBayar)->startOfDay();

        $totalDibayar = $totalDibayarSebelumnya + $nominal;
        $kekurangan = max(0.0, $pokok - $totalDibayar);

        $isTelat = $tanggal->gt($jatuhTempo);
        $isKurang = $kekurangan > 0;

        // Denda telat: 2% dari pokok pajak bila pembayaran melewati jatuh tempo.
        $dendaTelat = $isTelat ? round($pokok * self::TARIF_TELAT, 2) : 0.0;

        // Denda kurang bayar: 1% dari sisa kekurangan.
        $dendaKurang = $isKurang ? round($kekurangan * self::TARIF_KURANG, 2) : 0.0;

        $totalDenda = round($dendaTelat + $dendaKurang, 2);

        $status = $this->tentukanStatus($pokok, $totalDibayar);

        return [
            'denda_telat'       => $dendaTelat,
            'denda_kurang'      => $dendaKurang,
            'total_denda'       => $totalDenda,
            'is_telat'          => $isTelat,
            'is_kurang_bayar'   => $isKurang,
            'status_pembayaran' => $status,
            'kekurangan'        => round($kekurangan, 2),
            'total_dibayar'     => round($totalDibayar, 2),
            'keterangan'        => $this->keterangan($isTelat, $isKurang),
        ];
    }

    /**
     * Tentukan status pembayaran terhadap kewajiban.
     */
    public function tentukanStatus(float $pokok, float $totalDibayar): string
    {
        if ($totalDibayar < $pokok) {
            return 'KURANG_BAYAR';
        }

        if ($totalDibayar > $pokok) {
            return 'LEBIH_BAYAR';
        }

        return 'LUNAS';
    }

    /**
     * Status kewajiban (agregat) untuk disimpan di tabel kewajiban_pajak.
     */
    public function statusKewajiban(float $pokok, float $totalDibayar): string
    {
        if ($totalDibayar < $pokok) {
            return 'BELUM_LUNAS';
        }

        if ($totalDibayar > $pokok) {
            return 'LEBIH_BAYAR';
        }

        return 'LUNAS';
    }

    private function keterangan(bool $isTelat, bool $isKurang): string
    {
        return match (true) {
            $isTelat && $isKurang => 'Denda gabungan: terlambat sekaligus kurang bayar.',
            $isTelat              => 'Denda telat bayar: pembayaran melewati jatuh tempo.',
            $isKurang             => 'Denda kurang bayar: jumlah dibayar kurang dari kewajiban.',
            default               => 'Tidak ada denda.',
        };
    }
}
