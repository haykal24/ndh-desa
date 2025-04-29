<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LayananDesa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'layanan_desa';

    protected $fillable = [
        'id_desa',
        'created_by',
        'kategori',
        'nama_layanan',
        'deskripsi',
        'biaya',
        'lokasi_layanan',
        'jadwal_pelayanan',
        'kontak_layanan',
        'persyaratan',
        'prosedur'
    ];

    protected $casts = [
        'persyaratan' => 'array',
        'prosedur' => 'array',
        'biaya' => 'integer',
    ];

    protected function setBiayaAttribute($value)
    {
        $this->attributes['biaya'] = preg_replace('/\D/', '', $value);
    }

    public function desa(): BelongsTo
    {
        return $this->belongsTo(ProfilDesa::class, 'id_desa');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helper untuk menampilkan biaya dalam format yang sesuai
    public function getBiayaFormatted()
    {
        if ($this->biaya == 0) {
            return 'Gratis';
        }
        return 'Rp ' . number_format($this->biaya, 0, ',', '.');
    }
}