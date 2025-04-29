<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisBansos extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jenis_bansos';

    protected $fillable = [
        'nama_bansos',
        'deskripsi',
        'instansi_pemberi',
        'kategori',
        'periode',
        'bentuk_bantuan',
        'jumlah_per_penerima',
        'satuan',
        'nominal_standar',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'nominal_standar' => 'integer',
        'jumlah_per_penerima' => 'integer',
    ];

    // Kategori bantuan sosial
    public static function getKategoriOptions(): array
    {
        return [
            'Sembako' => 'Sembako',
            'Tunai' => 'Bantuan Tunai',
            'Kesehatan' => 'Kesehatan',
            'Pendidikan' => 'Pendidikan',
            'Perumahan' => 'Perumahan',
            'Pangan' => 'Pangan',
            'Pertanian' => 'Pertanian',
            'UMKM' => 'UMKM',
            'Lainnya' => 'Bantuan Lainnya',
        ];
    }

    // Bentuk bantuan
    public static function getBentukBantuanOptions(): array
    {
        return [
            'uang' => 'Uang Tunai',
            'barang' => 'Barang',
            'jasa' => 'Jasa/Layanan',
            'voucher' => 'Voucher/Kupon',
            'bantuan_modal' => 'Modal Usaha',
            'pelatihan' => 'Pelatihan/Pendampingan',
            'lainnya' => 'Lainnya',
        ];
    }

    // Satuan standar
    public static function getSatuanOptions(): array
    {
        return [
            'rupiah' => 'Rupiah',
            'kg' => 'Kilogram',
            'paket' => 'Paket',
            'unit' => 'Unit',
            'liter' => 'Liter',
            'bulan' => 'Bulan',
            'tahun' => 'Tahun',
            'sesi' => 'Sesi',
            'lembar' => 'Lembar',
            'karung' => 'Karung',
            'lainnya' => 'Lainnya',
        ];
    }

    // Periode bantuan
    public static function getPeriodeOptions(): array
    {
        return [
            'Bulanan' => 'Bulanan',
            'Triwulan' => 'Triwulan',
            'Semester' => 'Semester',
            'Tahunan' => 'Tahunan',
            'Sekali' => 'Satu Kali',
            'Insidental' => 'Insidental',
            'Berkelanjutan' => 'Berkelanjutan',
        ];
    }

    // Warna kategori
    public static function getKategoriColors(): array
    {
        return [
            'Sembako' => 'primary',
            'Tunai' => 'success',
            'Kesehatan' => 'info',
            'Pendidikan' => 'warning',
            'Perumahan' => 'danger',
            'Pangan' => 'primary',
            'Pertanian' => 'success',
            'UMKM' => 'info',
            'Lainnya' => 'gray',
        ];
    }

    // Metode untuk format Rupiah (tambahkan di model JenisBansos)
    public static function formatRupiah($nominal)
    {
        return 'Rp ' . number_format($nominal, 0, ',', '.');
    }

    // Sebelum save, bersihkan format angka
    protected function setNominalStandarAttribute($value)
    {
        if (is_string($value)) {
            $this->attributes['nominal_standar'] = (int) preg_replace('/[^\d]/', '', $value);
        } else {
            $this->attributes['nominal_standar'] = $value;
        }
    }

    // Tambahkan mutator untuk jumlah_per_penerima untuk memastikan nilai yang disimpan benar
    protected function setJumlahPerPenerimaAttribute($value)
    {
        if (is_string($value)) {
            // Simpan sebagai float untuk decimal:2
            $this->attributes['jumlah_per_penerima'] = (float) str_replace(',', '.', $value);
        } else {
            $this->attributes['jumlah_per_penerima'] = $value;
        }
    }

    public function bansos(): HasMany
    {
        return $this->hasMany(Bansos::class, 'jenis_bansos_id');
    }

    // Scope untuk bantuan yang aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Format nilai bantuan berdasarkan bentuk dan satuan
    public function getNilaiBantuanFormatted(): string
    {
        if ($this->bentuk_bantuan === 'uang') {
            return self::formatRupiah($this->nominal_standar);
        } elseif ($this->jumlah_per_penerima) {
            return (int)$this->jumlah_per_penerima . ' ' . $this->satuan;
        }
        return '-';
    }
}