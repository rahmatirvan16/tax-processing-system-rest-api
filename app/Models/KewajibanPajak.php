<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class KewajibanPajak extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('kewajiban_pajak');
    }
    protected $table = 'kewajiban_pajak';

    protected $fillable = [
        'wajib_pajak_id',
        'jenis_pajak',
        'masa_pajak',
        'pokok_pajak',
        'jatuh_tempo',
        'status',
    ];

    protected $casts = [
        'pokok_pajak' => 'decimal:2',
        'jatuh_tempo' => 'date',
    ];

    public function wajibPajak(): BelongsTo
    {
        return $this->belongsTo(WajibPajak::class, 'wajib_pajak_id');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'kewajiban_pajak_id');
    }

    /**
     * Total nominal yang sudah dibayarkan untuk kewajiban ini.
     */
    public function totalDibayar(): float
    {
        return (float) $this->pembayaran()->sum('nominal');
    }
}
