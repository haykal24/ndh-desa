<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StrukturPemerintahan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'struktur_pemerintahan';

    protected $fillable = [
        'profil_desa_id',
        'created_by',
        'sambutan_kepala_desa',
        'foto_kepala_desa',
        'nama_kepala_desa',
        'periode_jabatan',
        'program_kerja',
        'prioritas_program',
        'bagan_struktur'
    ];

    public function profilDesa(): BelongsTo
    {
        return $this->belongsTo(ProfilDesa::class, 'profil_desa_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function aparatDesa(): HasMany
    {
        return $this->hasMany(AparatDesa::class, 'struktur_pemerintahan_id')
                    ->orderBy('urutan', 'asc');
    }

    /**
     * Mendapatkan visi desa dari relasi profil desa
     */
    public function getVisiAttribute()
    {
        return $this->profilDesa->visi ?? null;
    }
    
    /**
     * Mendapatkan misi desa dari relasi profil desa
     */
    public function getMisiAttribute()
    {
        return $this->profilDesa->misi ?? null;
    }

    public function aparatDesaMany()
    {
        return $this->belongsToMany(AparatDesa::class, 'aparat_desa', 'struktur_pemerintahan_id', 'id');
    }
} 