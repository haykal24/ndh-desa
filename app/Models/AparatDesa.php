<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AparatDesa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'aparat_desa';

    protected $fillable = [
        'struktur_pemerintahan_id',
        'nama',
        'jabatan',
        'foto',
        'pendidikan',
        'tanggal_lahir',
        'alamat',
        'kontak',
        'periode_jabatan',
        'urutan'
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    public function strukturPemerintahan(): BelongsTo
    {
        return $this->belongsTo(StrukturPemerintahan::class, 'struktur_pemerintahan_id');
    }

    protected static function booted()
    {
        // Memastikan urutan diisi jika tidak ada
        static::creating(function ($aparat) {
            if (!$aparat->urutan) {
                // Tetapkan urutan default berdasarkan jabatan
                $aparat->urutan = static::getDefaultUrutan($aparat->jabatan);
            }
        });

        // Add this to ensure struktur_pemerintahan_id is set
        static::saving(function ($aparat) {
            if (!$aparat->struktur_pemerintahan_id && $aparat->struktur_pemerintahan) {
                $aparat->struktur_pemerintahan_id = $aparat->struktur_pemerintahan->id;
            }
        });
    }

    /**
     * Mendapatkan urutan default berdasarkan jabatan
     */
    private static function getDefaultUrutan(string $jabatan): int
    {
        return match(strtolower(trim($jabatan))) {
            'kepala desa' => 1,
            'sekretaris desa' => 2,
            'bendahara desa', 'kepala urusan keuangan' => 3,
            'kepala urusan umum' => 4,
            'kepala seksi pemerintahan', 'kepala urusan pemerintahan' => 5,
            'kepala seksi kesejahteraan' => 6,
            'kepala seksi pelayanan' => 7,
            default => preg_match('/kepala dusun/i', $jabatan) ? 10 : 15,
        };
    }
} 