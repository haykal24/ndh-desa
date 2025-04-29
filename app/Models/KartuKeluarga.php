<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KartuKeluarga extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kartu_keluarga';

    protected $fillable = [
        'id_desa',
        'nomor_kk',
        'alamat',
        'rt_rw',
        'kepala_keluarga_id',
    ];

    public function desa(): BelongsTo
    {
        return $this->belongsTo(ProfilDesa::class, 'id_desa');
    }

    public function kepalaKeluarga(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class, 'kepala_keluarga_id');
    }

    public function anggotaKeluarga(): HasMany
    {
        return $this->hasMany(Penduduk::class, 'kk', 'nomor_kk');
    }
}