<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Denda extends Model
{
    protected $table = 'denda';

    protected $fillable = [
        'pembayaran_id',
        'denda_telat',
        'denda_kurang',
        'total_denda',
        'is_telat',
        'is_kurang_bayar',
        'keterangan',
    ];

    protected $casts = [
        'denda_telat' => 'decimal:2',
        'denda_kurang' => 'decimal:2',
        'total_denda' => 'decimal:2',
        'is_telat' => 'boolean',
        'is_kurang_bayar' => 'boolean',
    ];

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class, 'pembayaran_id');
    }
}
