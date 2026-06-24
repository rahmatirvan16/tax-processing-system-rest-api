<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Pembayaran extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('pembayaran');
    }
    protected $table = 'pembayaran';

    protected $fillable = [
        'kewajiban_pajak_id',
        'nominal',
        'tanggal_bayar',
        'status',
        'keterangan',
        'dicatat_oleh',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'tanggal_bayar' => 'date',
    ];

    public function kewajibanPajak(): BelongsTo
    {
        return $this->belongsTo(KewajibanPajak::class, 'kewajiban_pajak_id');
    }

    public function denda(): HasOne
    {
        return $this->hasOne(Denda::class, 'pembayaran_id');
    }

    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
