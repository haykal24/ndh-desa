<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\KeuanganDesa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class KeuanganController extends Controller
{
    /**
     * Get financial data for statistics page with caching
     */
    public function getData(Request $request)
    {
        // Check if it's an ajax request
        if (!$request->ajax()) {
            return redirect()->route('statistik');
        }

        // Change default periode to 'semua_waktu'
        $periode = $request->input('periode', 'semua_waktu');
        $dariTanggal = $request->input('dari_tanggal');
        $sampaiTanggal = $request->input('sampai_tanggal');

        // Create a unique cache key based on the filter parameters
        $cacheKey = "keuangan_data:{$periode}";
        if ($periode === 'kustom' && $dariTanggal && $sampaiTanggal) {
            $cacheKey .= ":{$dariTanggal}:{$sampaiTanggal}";
        }

        // Store this cache key in a list of keuangan cache keys
        $cacheKeys = \Cache::get('keuangan_cache_keys', []);
        if (!in_array($cacheKey, $cacheKeys)) {
            $cacheKeys[] = $cacheKey;
            \Cache::put('keuangan_cache_keys', $cacheKeys, 24 * 60 * 60); // 24 hours
        }

        // Cache duration - adjust as needed
        $cacheDuration = 60; // 60 minutes default cache

        // For non-custom periods that don't change frequently, use longer cache
        if ($periode === 'semua_waktu' || $periode === 'tahun_lalu') {
            $cacheDuration = 24 * 60; // 24 hours
        } elseif ($periode === 'tahun_ini') {
            $cacheDuration = 12 * 60; // 12 hours
        } elseif ($periode === 'bulan_ini' || $periode === 'bulan_lalu') {
            $cacheDuration = 6 * 60; // 6 hours
        }

        // Get data from cache or generate if not cached
        return \Cache::remember($cacheKey, $cacheDuration, function() use ($periode, $dariTanggal, $sampaiTanggal) {
            // Set date range based on periode
            $startDate = null;
            $endDate = null;
            $periodeLabel = 'Bulan Ini';

            if ($periode === 'semua_waktu') {
                $periodeLabel = 'Semua Waktu';
                // For 'semua_waktu', we don't set date constraints
            } elseif ($periode === 'kustom' && $dariTanggal && $sampaiTanggal) {
                $startDate = Carbon::parse($dariTanggal)->startOfDay();
                $endDate = Carbon::parse($sampaiTanggal)->endOfDay();
                $periodeLabel = $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y');
            } else {
                switch ($periode) {
                    case 'hari_ini':
                        $startDate = now()->startOfDay();
                        $endDate = now()->endOfDay();
                        $periodeLabel = 'Hari Ini';
                        break;
                    case 'minggu_ini':
                        $startDate = now()->startOfWeek();
                        $endDate = now()->endOfWeek();
                        $periodeLabel = 'Minggu Ini';
                        break;
                    case 'bulan_ini':
                        $startDate = now()->startOfMonth();
                        $endDate = now()->endOfMonth();
                        $periodeLabel = 'Bulan Ini';
                        break;
                    case 'tahun_ini':
                        $startDate = now()->startOfYear();
                        $endDate = now()->endOfYear();
                        $periodeLabel = 'Tahun Ini';
                        break;
                    case 'bulan_lalu':
                        $startDate = now()->subMonth()->startOfMonth();
                        $endDate = now()->subMonth()->endOfMonth();
                        $periodeLabel = 'Bulan Lalu';
                        break;
                    case 'tahun_lalu':
                        $startDate = now()->subYear()->startOfYear();
                        $endDate = now()->subYear()->endOfYear();
                        $periodeLabel = 'Tahun Lalu';
                        break;
                }
            }

            // Get financial data
            $query = KeuanganDesa::query();

            // Apply date filter if dates are set
            if ($startDate && $endDate) {
                $query->whereBetween('tanggal', [$startDate, $endDate]);
            }

            // Calculate totals for the period
            $totalPemasukan = $query->clone()->whereIn('jenis', ['pemasukan', 'Pemasukan', 'PEMASUKAN'])->sum('jumlah');
            $totalPengeluaran = $query->clone()->whereIn('jenis', ['pengeluaran', 'Pengeluaran', 'PENGELUARAN'])->sum('jumlah');
            $saldoDesa = $totalPemasukan - $totalPengeluaran;

            // Get top transactions (largest income and expense)
            $topPemasukan = $query->clone()
                ->whereIn('jenis', ['pemasukan', 'Pemasukan', 'PEMASUKAN'])
                ->orderByDesc('jumlah')
                ->limit(1)
                ->get()
                ->map(function($item) {
                    return [
                        'jumlah' => $item->jumlah,
                        'deskripsi' => $item->deskripsi,
                        'tanggal' => $item->tanggal ? Carbon::parse($item->tanggal)->format('d/m/Y') : null
                    ];
                });

            $topPengeluaran = $query->clone()
                ->whereIn('jenis', ['pengeluaran', 'Pengeluaran', 'PENGELUARAN'])
                ->orderByDesc('jumlah')
                ->limit(1)
                ->get()
                ->map(function($item) {
                    return [
                        'jumlah' => $item->jumlah,
                        'deskripsi' => $item->deskripsi,
                        'tanggal' => $item->tanggal ? Carbon::parse($item->tanggal)->format('d/m/Y') : null
                    ];
                });

            // Get monthly averages for comparison
            $averagePemasukan = 0;
            $averagePengeluaran = 0;

            if ($periode !== 'hari_ini') {
                // Get monthly averages for the last 6 months
                $sixMonthsAgo = now()->subMonths(6)->startOfMonth();

                $monthlyData = KeuanganDesa::where('tanggal', '>=', $sixMonthsAgo)
                    ->whereIn('jenis', ['pemasukan', 'Pemasukan', 'PEMASUKAN', 'pengeluaran', 'Pengeluaran', 'PENGELUARAN'])
                    ->selectRaw('YEAR(tanggal) as year, MONTH(tanggal) as month, jenis, SUM(jumlah) as total')
                    ->groupBy('year', 'month', 'jenis')
                    ->get();

                $monthlyPemasukan = $monthlyData->filter(function ($item) {
                    return strtolower($item->jenis) === 'pemasukan';
                })->pluck('total');

                $monthlyPengeluaran = $monthlyData->filter(function ($item) {
                    return strtolower($item->jenis) === 'pengeluaran';
                })->pluck('total');

                $averagePemasukan = $monthlyPemasukan->count() > 0 ? $monthlyPemasukan->avg() : 0;
                $averagePengeluaran = $monthlyPengeluaran->count() > 0 ? $monthlyPengeluaran->avg() : 0;
            }

            // Prepare simplified data structure for the front-end
            $data = [
                'overview' => [
                    'totalPemasukan' => $totalPemasukan,
                    'totalPengeluaran' => $totalPengeluaran,
                    'saldoDesa' => $saldoDesa,
                    'periodeLabel' => $periodeLabel
                ],
                'topTransactions' => [
                    'pemasukan' => $topPemasukan,
                    'pengeluaran' => $topPengeluaran
                ],
                'averages' => [
                    'pemasukan' => round($averagePemasukan),
                    'pengeluaran' => round($averagePengeluaran)
                ],
                // Include minimal structure for existing front-end compatibility
                'financeBars' => [
                    'labels' => [],
                    'pemasukan' => [],
                    'pengeluaran' => []
                ],
                'kategoriPengeluaran' => [
                    'labels' => ['Data tidak tersedia'],
                    'data' => [100]
                ],
                'monthlyTrend' => [
                    'labels' => [],
                    'pemasukan' => [],
                    'pengeluaran' => []
                ],
                'cached' => true, // Add indicator that this is cached data
                'cachedAt' => now()->format('d/m/Y H:i:s')
            ];

            return response()->json($data);
        });
    }

    /**
     * Format periode label for display
     *
     * @param string $periode
     * @param string|null $dariTanggal
     * @param string|null $sampaiTanggal
     * @return string
     */
    private function formatPeriodeLabel($periode, $dariTanggal = null, $sampaiTanggal = null)
    {
        if ($periode === 'kustom' && $dariTanggal && $sampaiTanggal) {
            return Carbon::parse($dariTanggal)->format('d/m/Y') . ' - ' . Carbon::parse($sampaiTanggal)->format('d/m/Y');
        }

        return match($periode) {
            'hari_ini' => 'Hari Ini',
            'minggu_ini' => 'Minggu Ini',
            'bulan_ini' => 'Bulan Ini',
            'tahun_ini' => 'Tahun Ini',
            'bulan_lalu' => 'Bulan Lalu',
            'tahun_lalu' => 'Tahun Lalu',
            'semua_waktu' => 'Semua Waktu',
            default => 'Periode'
        };
    }
}