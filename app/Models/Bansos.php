<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bansos extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bansos';

    protected $fillable = [
        'id_desa',
        'penduduk_id',
        'jenis_bansos_id',
        'status',
        'prioritas',
        'sumber_pengajuan',
        'tanggal_pengajuan',
        'tanggal_penerimaan',
        'tenggat_pengambilan',
        'lokasi_pengambilan',
        'alasan_pengajuan',
        'keterangan',
        'dokumen_pendukung',
        'bukti_penerimaan',
        'foto_rumah',
        'diubah_oleh',
        'notifikasi_terkirim',
        'is_urgent',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'datetime',
        'tanggal_penerimaan' => 'datetime',
        'tenggat_pengambilan' => 'datetime',
        'notifikasi_terkirim' => 'boolean',
        'is_urgent' => 'boolean',
    ];

    // Status bantuan
    public static function getStatusOptions(): array
    {
        return [
            'Diajukan' => 'Diajukan',
            'Dalam Verifikasi' => 'Dalam Verifikasi',
            'Diverifikasi' => 'Diverifikasi',
            'Disetujui' => 'Disetujui',
            'Ditolak' => 'Ditolak',
            'Sudah Diterima' => 'Sudah Diterima',
            'Dibatalkan' => 'Dibatalkan',
        ];
    }

    // Warna status
    public static function getStatusColors(): array
    {
        return [
            'Diajukan' => 'gray',
            'Dalam Verifikasi' => 'warning',
            'Diverifikasi' => 'info',
            'Disetujui' => 'success',
            'Ditolak' => 'danger',
            'Sudah Diterima' => 'primary',
            'Dibatalkan' => 'warning',
        ];
    }

    // Prioritas bantuan
    public static function getPrioritasOptions(): array
    {
        return [
            'Tinggi' => 'Tinggi',
            'Sedang' => 'Sedang',
            'Rendah' => 'Rendah',
        ];
    }

    // Warna prioritas
    public static function getPrioritasColors(): array
    {
        return [
            'Tinggi' => 'danger',
            'Sedang' => 'warning',
            'Rendah' => 'success',
        ];
    }

    // Sumber pengajuan
    public static function getSumberPengajuanOptions(): array
    {
        return [
            'admin' => 'Admin/Petugas Desa',
            'warga' => 'Pengajuan Warga',
        ];
    }

    // Scope untuk pengajuan dari warga
    public function scopeFromWarga($query)
    {
        return $query->where('sumber_pengajuan', 'warga');
    }

    // Scope untuk pengajuan dari admin
    public function scopeFromAdmin($query)
    {
        return $query->where('sumber_pengajuan', 'admin');
    }

    // Scope untuk yang belum diverifikasi
    public function scopeBelumDiverifikasi($query)
    {
        return $query->whereIn('status', ['Diajukan', 'Dalam Verifikasi']);
    }

    // Relasi
    public function penduduk(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class);
    }

    public function jenisBansos(): BelongsTo
    {
        return $this->belongsTo(JenisBansos::class, 'jenis_bansos_id');
    }

    public function desa(): BelongsTo
    {
        return $this->belongsTo(ProfilDesa::class, 'id_desa');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }

    public function riwayatStatus(): HasMany
    {
        return $this->hasMany(BansosHistory::class, 'bansos_id')->orderBy('waktu_perubahan', 'desc');
    }

    // Metode ini hanya mencatat history perubahan status
    public function addStatusHistory(string $statusBaru, ?string $keterangan = null): void
    {
        BansosHistory::create([
            'bansos_id' => $this->id,
            'status_lama' => $this->status, // Gunakan status saat ini
            'status_baru' => $statusBaru,
            'keterangan' => $keterangan,
            'diubah_oleh' => auth()->id(),
            'waktu_perubahan' => now(),
        ]);
    }

    // Metode untuk membersihkan soft-deleted records yang sudah lama
    public static function cleanupOldSoftDeletedRecords()
    {
        $threshold = now()->subMonths(3); // Sesuaikan dengan kebutuhan

        return static::onlyTrashed()
            ->where('deleted_at', '<', $threshold)
            ->forceDelete();
    }
}