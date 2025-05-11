<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ProfilDesa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StatistikController extends Controller
{
    public function index()
    {
        // Use a cache for basic statistics that don't change frequently
        $cacheDuration = 60; // 60 minutes
        $profilDesa = Cache::remember('profilDesa', $cacheDuration, function() {
            return ProfilDesa::first();
        });

        // Get all population data in a single efficient query with conditionals
        $populationStats = Cache::remember('populationStats', $cacheDuration, function() {
            return DB::table('penduduk')
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN jenis_kelamin = "L" THEN 1 ELSE 0 END) as laki_laki,
                    SUM(CASE WHEN jenis_kelamin = "P" THEN 1 ELSE 0 END) as perempuan,
                    SUM(CASE WHEN kepala_keluarga = 1 THEN 1 ELSE 0 END) as kepala_keluarga
                ')
                ->first();
        });

        $totalPenduduk = $populationStats->total;
        $totalLakiLaki = $populationStats->laki_laki;
        $totalPerempuan = $populationStats->perempuan;
        $totalKepalaKeluarga = $populationStats->kepala_keluarga;

        // Calculate percentages
        $persenLakiLaki = $totalPenduduk > 0 ? round(($totalLakiLaki / $totalPenduduk) * 100) : 0;
        $persenPerempuan = $totalPenduduk > 0 ? round(($totalPerempuan / $totalPenduduk) * 100) : 0;

        // Define age groups
        $kelompokUmurLabels = [
            'Balita (0-5)',
            'Anak (6-12)',
            'Remaja (13-18)',
            'Dewasa Muda (19-30)',
            'Dewasa (31-45)',
            'Paruh Baya (46-60)',
            'Lansia (>60)'
        ];

        // Define age ranges
        $ageRanges = [
            [0, 5],   // Balita
            [6, 12],  // Anak
            [13, 18], // Remaja
            [19, 30], // Dewasa Muda
            [31, 45], // Dewasa
            [46, 60], // Paruh Baya
            [61, 200] // Lansia
        ];

        // Use a single query to get age distribution with CASE WHEN
        $kelompokUmurData = Cache::remember('kelompokUmurData', $cacheDuration, function() use ($ageRanges) {
            $cases = [];
            foreach ($ageRanges as $index => $range) {
            $min = $range[0];
            $max = $range[1];
                $cases[] = "SUM(CASE WHEN TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) BETWEEN {$min} AND {$max} THEN 1 ELSE 0 END) as age_group_{$index}";
            }

            $select = implode(', ', $cases);
            $result = DB::table('penduduk')
                ->selectRaw($select)
                ->whereNotNull('tanggal_lahir')
                ->first();

            return collect($result)->values()->toArray();
        });

        // Get education and occupation data efficiently with single queries
        $pendidikanStats = Cache::remember('pendidikanStats', $cacheDuration, function() {
            return DB::table('penduduk')
                ->select('pendidikan', DB::raw('count(*) as total'))
            ->whereNotNull('pendidikan')
            ->where('pendidikan', '<>', '')
            ->groupBy('pendidikan')
            ->orderByDesc('total')
            ->limit(6)
            ->get();
        });

        $pendidikanLabels = $pendidikanStats->pluck('pendidikan')->toArray();
        $pendidikanData = $pendidikanStats->pluck('total')->toArray();

        $pekerjaanStats = Cache::remember('pekerjaanStats', $cacheDuration, function() {
            return DB::table('penduduk')
                ->select('pekerjaan', DB::raw('count(*) as total'))
            ->whereNotNull('pekerjaan')
            ->where('pekerjaan', '<>', '')
            ->groupBy('pekerjaan')
            ->orderByDesc('total')
            ->limit(6)
            ->get();
        });

        $pekerjaanLabels = $pekerjaanStats->pluck('pekerjaan')->toArray();
        $pekerjaanData = $pekerjaanStats->pluck('total')->toArray();

        return view('front.statistik', compact(
            'profilDesa',
            'totalPenduduk',
            'totalLakiLaki',
            'totalPerempuan',
            'persenLakiLaki',
            'persenPerempuan',
            'totalKepalaKeluarga',
            'kelompokUmurLabels',
            'kelompokUmurData',
            'pendidikanLabels',
            'pendidikanData',
            'pekerjaanLabels',
            'pekerjaanData'
        ));
    }

    public function clearDataCaches()
    {
        // Only allow for admin users
        if (!auth()->user() || !auth()->user()->hasRole('admin')) {
            return redirect()->back()->with('error', 'Tidak memiliki izin untuk operasi ini');
        }

        // Clear financial data caches
        $keuanganCacheKeys = Cache::get('keuangan_cache_keys', []);
        foreach ($keuanganCacheKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('keuangan_cache_keys');

        // Clear bansos data caches
        $bansosCacheKeys = Cache::get('bansos_cache_keys', []);
        foreach ($bansosCacheKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('bansos_cache_keys');

        // Clear inventaris data caches
        $inventarisCacheKeys = Cache::get('inventaris_cache_keys', []);
        foreach ($inventarisCacheKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('inventaris_cache_keys');

        return redirect()->back()->with('success', 'Semua cache data statistik berhasil dihapus');
    }
}