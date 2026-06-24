<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WajibPajak extends Model
{
    protected $table = 'wajib_pajak';

    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('wajib_pajak');
    }

    protected $fillable = [
        'jenis',
        'nama',
        'nik',
        'npwp',
        'nib',
        'email',
        'telepon',
        'alamat',
        'status_aktif',
    ];

    protected $casts = [
        'status_aktif' => 'boolean',
    ];

    public function kewajibanPajak(): HasMany
    {
        return $this->hasMany(KewajibanPajak::class, 'wajib_pajak_id');
    }
}
