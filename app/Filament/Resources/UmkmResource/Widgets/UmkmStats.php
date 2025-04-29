<?php

namespace App\Filament\Resources\UmkmResource\Widgets;

use App\Models\Umkm;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class UmkmStats extends BaseWidget
{
    protected function getStats(): array
    {
        // Total UMKM
        $totalUmkm = Umkm::count();

        // UMKM terverifikasi
        $totalVerified = Umkm::where('is_verified', true)->count();

        // Persentase terverifikasi
        $persentaseVerifikasi = $totalUmkm > 0
            ? round(($totalVerified / $totalUmkm) * 100)
            : 0;

        // UMKM per kategori
        $umkmPerKategori = DB::table('umkm')
            ->whereNull('deleted_at')
            ->whereNotNull('kategori')
            ->select('kategori', DB::raw('count(*) as total'))
            ->groupBy('kategori')
            ->orderByDesc('total')
            ->limit(1)
            ->first();

        $kategoriTerbanyak = $umkmPerKategori ? $umkmPerKategori->kategori : 'Tidak ada';
        $jumlahKategoriTerbanyak = $umkmPerKategori ? $umkmPerKategori->total : 0;

        // UMKM baru bulan ini
        $umkmBaruBulanIni = Umkm::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return [
            Stat::make('Total UMKM', number_format($totalUmkm, 0, ',', '.'))
                ->description('Semua UMKM terdaftar')
                ->descriptionIcon('heroicon-o-building-storefront')
                ->color('primary'),

            Stat::make('UMKM Terverifikasi', number_format($totalVerified, 0, ',', '.'))
                ->description($persentaseVerifikasi . '% dari total UMKM')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color('success'),

            Stat::make('UMKM Belum Terverifikasi', number_format($totalUmkm - $totalVerified, 0, ',', '.'))
                ->description('Menunggu verifikasi')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Kategori Terbanyak', $kategoriTerbanyak)
                ->description($jumlahKategoriTerbanyak . ' UMKM')
                ->descriptionIcon('heroicon-o-tag')
                ->color('info'),

            Stat::make('UMKM Baru', number_format($umkmBaruBulanIni, 0, ',', '.'))
                ->description('Terdaftar bulan ' . now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-o-calendar')
                ->color('gray'),
        ];
    }
}