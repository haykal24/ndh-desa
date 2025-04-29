<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifikasiPenduduk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'verifikasi_penduduk';

    protected $fillable = [
        'user_id',
        'penduduk_id',
        'id_desa',
        'nik',
        'kk',
        'kepala_keluarga_id',
        'nama',
        'alamat',
        'rt_rw',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'status_perkawinan',
        'kepala_keluarga',
        'pekerjaan',
        'pendidikan',
        'status',
        'catatan',
        'no_hp',
        'email',
        'golongan_darah'
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'kepala_keluarga' => 'boolean',
        'jenis_kelamin' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function desa(): BelongsTo
    {
        return $this->belongsTo(ProfilDesa::class, 'id_desa');
    }

    public function penduduk(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class);
    }

    public function kepalaKeluarga(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class, 'kepala_keluarga_id');
    }

    public function getJenisKelaminLabelAttribute()
    {
        return match($this->jenis_kelamin) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => 'Tidak Diketahui'
        };
    }
}