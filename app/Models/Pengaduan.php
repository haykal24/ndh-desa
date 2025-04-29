<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Pengaduan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pengaduan';

    protected $fillable = [
        'id_desa',
        'penduduk_id',
        'judul',
        'kategori',
        'prioritas',
        'deskripsi',
        'foto',
        'status',
        'is_public',
        'tanggapan',
        'ditangani_oleh',
        'tanggal_tanggapan',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'tanggal_tanggapan' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Daftar kategori pengaduan yang tersedia
     *
     * @return array<string, string>
     */
    public static function getKategoriOptions(): array
    {
        return [
            'Keamanan' => 'Keamanan',
            'Infrastruktur' => 'Infrastruktur',
            'Sosial' => 'Sosial',
            'Lingkungan' => 'Lingkungan',
            'Pelayanan Publik' => 'Pelayanan Publik',
            'Kesehatan' => 'Kesehatan',
            'Lainnya' => 'Lainnya',
        ];
    }

    /**
     * Daftar status pengaduan yang tersedia
     *
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            'Belum Ditangani' => 'Belum Ditangani',
            'Sedang Diproses' => 'Sedang Diproses',
            'Selesai' => 'Selesai',
            'Ditolak' => 'Ditolak',
        ];
    }

    /**
     * Daftar prioritas pengaduan yang tersedia
     *
     * @return array<string, string>
     */
    public static function getPrioritasOptions(): array
    {
        return [
            'Rendah' => 'Rendah',
            'Sedang' => 'Sedang',
            'Tinggi' => 'Tinggi',
        ];
    }

    /**
     * Daftar warna untuk status pengaduan
     *
     * @return array<string, string>
     */
    public static function getStatusColors(): array
    {
        return [
            'Belum Ditangani' => 'warning',
            'Sedang Diproses' => 'info',
            'Selesai' => 'success',
            'Ditolak' => 'danger',
        ];
    }

    /**
     * Daftar warna untuk prioritas pengaduan
     *
     * @return array<string, string>
     */
    public static function getPrioritasColors(): array
    {
        return [
            'Rendah' => 'info',
            'Sedang' => 'warning',
            'Tinggi' => 'danger',
        ];
    }

    /**
     * Daftar warna untuk kategori pengaduan
     *
     * @return array<string, string>
     */
    public static function getKategoriColors(): array
    {
        return [
            'Keamanan' => 'danger',
            'Infrastruktur' => 'warning',
            'Sosial' => 'success',
            'Lingkungan' => 'primary',
            'Pelayanan Publik' => 'info',
            'Kesehatan' => 'success',
            'Lainnya' => 'gray',
        ];
    }

    /**
     * Ikon untuk setiap kategori
     *
     * @return array<string, string>
     */
    public static function getKategoriIcons(): array
    {
        return [
            'Keamanan' => 'heroicon-o-shield-exclamation',
            'Infrastruktur' => 'heroicon-o-wrench-screwdriver',
            'Sosial' => 'heroicon-o-user-group',
            'Lingkungan' => 'heroicon-o-sparkles',
            'Pelayanan Publik' => 'heroicon-o-clipboard-document-list',
            'Kesehatan' => 'heroicon-o-heart',
            'Lainnya' => 'heroicon-o-question-mark-circle',
        ];
    }

    /**
     * Mendapatkan ikon untuk kategori tertentu
     *
     * @return string
     */
    public function getKategoriIcon(): string
    {
        return self::getKategoriIcons()[$this->kategori] ?? 'heroicon-o-question-mark-circle';
    }

    /**
     * Scope query untuk pengaduan yang belum ditangani
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeBelumDitangani(Builder $query): Builder
    {
        return $query->where('status', 'Belum Ditangani');
    }

    /**
     * Scope query untuk pengaduan yang sedang diproses
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeSedangDiproses(Builder $query): Builder
    {
        return $query->where('status', 'Sedang Diproses');
    }

    /**
     * Scope query untuk pengaduan yang sudah selesai
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeSelesai(Builder $query): Builder
    {
        return $query->where('status', 'Selesai');
    }

    /**
     * Scope query untuk pengaduan yang ditolak
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeDitolak(Builder $query): Builder
    {
        return $query->where('status', 'Ditolak');
    }

    /**
     * Scope query untuk pengaduan dengan prioritas tinggi
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePrioritasTinggi(Builder $query): Builder
    {
        return $query->where('prioritas', 'Tinggi');
    }

    /**
     * Scope query untuk pengaduan yang dibuat dalam 7 hari terakhir
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeMingguIni(Builder $query): Builder
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays(7));
    }

    /**
     * Scope query untuk pengaduan publik
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePublik(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Relasi ke desa
     *
     * @return BelongsTo
     */
    public function desa(): BelongsTo
    {
        return $this->belongsTo(ProfilDesa::class, 'id_desa');
    }

    /**
     * Relasi ke penduduk
     *
     * @return BelongsTo
     */
    public function penduduk(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class);
    }

    /**
     * Relasi ke petugas yang menangani
     *
     * @return BelongsTo
     */
    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditangani_oleh');
    }

    /**
     * Relasi ke user (alias untuk penduduk)
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class, 'penduduk_id');
    }

    /**
     * Cek apakah pengaduan sudah ditanggapi
     *
     * @return bool
     */
    public function sudahDitanggapi(): bool
    {
        return !is_null($this->tanggapan) && !is_null($this->tanggal_tanggapan);
    }

    /**
     * Cek apakah pengaduan belum selesai (belum ditangani atau sedang diproses)
     *
     * @return bool
     */
    public function belumSelesai(): bool
    {
        return in_array($this->status, ['Belum Ditangani', 'Sedang Diproses']);
    }

    /**
     * Cek apakah pengaduan masih dapat diedit oleh warga
     *
     * @return bool
     */
    public function dapatDiedit(): bool
    {
        return $this->status === 'Belum Ditangani';
    }

    /**
     * Menghitung waktu penanganan (dari dibuat sampai selesai/ditolak)
     *
     * @return int|null Waktu penanganan dalam jam, atau null jika belum selesai
     */
    public function waktuPenanganan(): ?int
    {
        if (in_array($this->status, ['Selesai', 'Ditolak']) && $this->tanggal_tanggapan) {
            return $this->created_at->diffInHours($this->tanggal_tanggapan);
        }

        return null;
    }

    /**
     * Ringkasan deskripsi (terpotong)
     *
     * @param int $length
     * @return string
     */
    public function getRingkasanDeskripsi(int $length = 100): string
    {
        if (strlen($this->deskripsi) <= $length) {
            return $this->deskripsi;
        }

        return substr($this->deskripsi, 0, $length) . '...';
    }
}