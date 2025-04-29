<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Umkm extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'umkm';

    protected $fillable = [
        'id_desa',
        'penduduk_id',
        'nama_usaha',
        'produk',
        'kontak_whatsapp',
        'lokasi',
        'deskripsi',
        'kategori',
        'is_verified',
        'foto_usaha',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function desa(): BelongsTo
    {
        return $this->belongsTo(ProfilDesa::class, 'id_desa');
    }

    public function penduduk(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class, 'penduduk_id');
    }

    // Mendapatkan URL WhatsApp
    public function getWhatsappUrl(): string
    {
        return "https://wa.me/{$this->kontak_whatsapp}";
    }

    // Scope untuk UMKM terverifikasi
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    // Scope untuk UMKM berdasarkan kategori
    public function scopeKategori($query, $kategori)
    {
        return $query->where('kategori', $kategori);
    }
}