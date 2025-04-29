<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Inventaris extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventaris';

    protected $fillable = [
        'id_desa',
        'created_by',
        'kode_barang',
        'nama_barang',
        'kategori',
        'jumlah',
        'kondisi',
        'tanggal_perolehan',
        'nominal_harga',
        'sumber_dana',
        'lokasi',
        'status',
        'keterangan',
        'foto',
    ];

    protected $casts = [
        'tanggal_perolehan' => 'date',
    ];

    // Mutator untuk mengubah format sebelum menyimpan ke database
    public function setNominalHargaAttribute($value)
    {
        $this->attributes['nominal_harga'] = preg_replace('/[^0-9]/', '', $value);
    }

    // Metode untuk format Rupiah
    public static function formatRupiah($nominal)
    {
        return 'Rp ' . number_format($nominal, 0, ',', '.');
    }

    // Accessor untuk mengambil nominal_harga dalam format Rupiah
    public function getNominalHargaRupiahAttribute()
    {
        return self::formatRupiah($this->nominal_harga);
    }

    // Boot method untuk otomatisasi kode barang
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($inventaris) {
            // Jika kode barang belum diisi
            if (empty($inventaris->kode_barang)) {
                // Generate kode format: INVDSA-YYYYMM-XXXX
                // DSA = kode desa (3 digit dari ID desa)
                // YYYYMM = tahun dan bulan
                // XXXX = nomor urut

                $desaId = str_pad($inventaris->id_desa, 3, '0', STR_PAD_LEFT);
                $tanggal = now()->format('Ymd');

                // Cari nomor urut terakhir pada hari ini
                $lastInventaris = self::where('kode_barang', 'like', "INV{$desaId}-{$tanggal}%")
                    ->orderBy('id', 'desc')
                    ->first();

                $nomorUrut = 1;
                if ($lastInventaris) {
                    // Ekstrak nomor urut dari kode terakhir dan tambahkan 1
                    $parts = explode('-', $lastInventaris->kode_barang);
                    if (isset($parts[2])) {
                        $nomorUrut = (int)$parts[2] + 1;
                    }
                }

                // Format nomor urut 4 digit
                $nomorUrut = str_pad($nomorUrut, 4, '0', STR_PAD_LEFT);

                // Buat kode barang final
                $inventaris->kode_barang = "INV{$desaId}-{$tanggal}-{$nomorUrut}";
            }
        });
    }

    // Kategori barang yang tersedia
    public static function getKategoriOptions(): array
    {
        return [
            'Elektronik' => 'Elektronik',
            'Furnitur' => 'Furnitur',
            'Kendaraan' => 'Kendaraan',
            'ATK' => 'Alat Tulis Kantor',
            'Komputer' => 'Komputer & Perangkat IT',
            'Peralatan' => 'Peralatan & Perkakas',
            'Lainnya' => 'Lainnya',
        ];
    }

    // Status barang yang tersedia
    public static function getStatusOptions(): array
    {
        return [
            'Tersedia' => 'Tersedia',
            'Dipinjam' => 'Dipinjam',
            'Dalam Perbaikan' => 'Dalam Perbaikan',
            'Tidak Aktif' => 'Tidak Aktif',
        ];
    }

    // Kondisi barang yang tersedia
    public static function getKondisiOptions(): array
    {
        return [
            'Baik' => 'Baik',
            'Rusak Ringan' => 'Rusak Ringan',
            'Rusak Berat' => 'Rusak Berat',
            'Hilang' => 'Hilang',
        ];
    }

    // Warna untuk tampilan kondisi
    public static function getKondisiColors(): array
    {
        return [
            'Baik' => 'success',
            'Rusak Ringan' => 'warning',
            'Rusak Berat' => 'danger',
            'Hilang' => 'gray',
        ];
    }

    // Warna untuk tampilan status
    public static function getStatusColors(): array
    {
        return [
            'Tersedia' => 'success',
            'Dipinjam' => 'warning',
            'Dalam Perbaikan' => 'danger',
            'Tidak Aktif' => 'gray',
        ];
    }

    // Sumber dana
    public static function getSumberDanaOptions(): array
    {
        return [
            'APBD' => 'APBD',
            'APBN' => 'APBN',
            'APBDes' => 'APBDes',
            'Hibah' => 'Hibah/Sumbangan',
            'Swadaya' => 'Swadaya Masyarakat',
            'CSR' => 'CSR Perusahaan',
            'Lainnya' => 'Lainnya',
        ];
    }

    // Relasi
    public function desa(): BelongsTo
    {
        return $this->belongsTo(ProfilDesa::class, 'id_desa');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}