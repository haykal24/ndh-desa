<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Penduduk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'penduduk';

    protected $fillable = [
        'nik',
        'kk',
        'nama',
        'alamat',
        'rt_rw',
        'desa_kelurahan',
        'kecamatan',
        'kabupaten',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'status_perkawinan',
        'pekerjaan',
        'pendidikan',
        'id_desa',
        'kepala_keluarga',
        'kepala_keluarga_id',
        'user_id',
        'no_hp',
        'email',
        'golongan_darah',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'kepala_keluarga' => 'boolean',
        'jenis_kelamin' => 'string',
    ];

    // Helper method untuk jenis kelamin
    public function getJenisKelaminLabelAttribute()
    {
        return match($this->jenis_kelamin) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => 'Tidak Diketahui'
        };
    }

    // Relasi-relasi tetap sama seperti sebelumnya
    public function desa(): BelongsTo
    {
        return $this->belongsTo(ProfilDesa::class, 'id_desa');
    }

    public function kepalaKeluarga()
    {
        return $this->belongsTo(Penduduk::class, 'kepala_keluarga_id');
    }

    public function anggotaKeluarga()
    {
        return $this->hasMany(Penduduk::class, 'kepala_keluarga_id');
    }

    /**
     * Get the user associated with the penduduk.
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function bansos(): HasMany
    {
        return $this->hasMany(Bansos::class);
    }

    public function pengaduan(): HasMany
    {
        return $this->hasMany(Pengaduan::class);
    }

    public function umkm(): HasMany
    {
        return $this->hasMany(Umkm::class);
    }

    public function kartuKeluarga()
    {
        return $this->belongsTo(KartuKeluarga::class, 'kk', 'nomor_kk');
    }

    public function isKepalaKeluarga(): bool
    {
        return $this->kepala_keluarga;
    }
}