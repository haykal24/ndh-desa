<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bansos;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BansosController extends Controller
{
    /**
     * Get social assistance (bansos) data with optimized caching
     */
    public function getData(Request $request)
    {
        try {
            if (!$request->ajax()) {
                return redirect()->route('statistik');
            }

            $periode = $request->input('periode', 'semua_waktu');
            $filter = $request->input('filter', 'status');
            $chartType = $request->input('chart_type', 'doughnut');
            $dariTanggal = $request->input('dariTanggal');
            $sampaiTanggal = $request->input('sampaiTanggal');

            // Create a unique cache key based on all parameters
            $cacheKey = "bansos_data:{$periode}:{$filter}:{$chartType}";
            if ($periode === 'kustom' && $dariTanggal && $sampaiTanggal) {
                $cacheKey .= ":{$dariTanggal}:{$sampaiTanggal}";
            }

            // Store this cache key in a list of bansos cache keys for easier invalidation
            $cacheKeys = Cache::get('bansos_cache_keys', []);
            if (!in_array($cacheKey, $cacheKeys)) {
                $cacheKeys[] = $cacheKey;
                Cache::put('bansos_cache_keys', $cacheKeys, 24 * 60 * 60); // 24 hours
            }

            // Determine appropriate cache duration based on period type
            $cacheDuration = 60; // 60 minutes default
            if ($periode === 'semua_waktu' || $periode === 'tahun_lalu') {
                $cacheDuration = 24 * 60; // 24 hours for historical data
            } elseif ($periode === 'tahun_ini') {
                $cacheDuration = 12 * 60; // 12 hours
            } elseif ($periode === 'bulan_ini' || $periode === 'bulan_lalu') {
                $cacheDuration = 6 * 60; // 6 hours
            }

            // Get data from cache or generate if not cached
            return Cache::remember($cacheKey, $cacheDuration, function() use ($periode, $filter, $chartType, $dariTanggal, $sampaiTanggal) {
                $query = Bansos::query()->select('id', 'penduduk_id', 'jenis_bansos_id', 'status', 'prioritas', 'is_urgent', 'sumber_pengajuan', 'created_at');

                // Apply date filter
                switch ($periode) {
                    case 'hari_ini':
                        $query->whereDate('created_at', today());
                        break;
                    case 'minggu_ini':
                        $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                        break;
                    case 'bulan_ini':
                        $query->whereMonth('created_at', now()->month)
                              ->whereYear('created_at', now()->year);
                        break;
                    case 'tahun_ini':
                        $query->whereYear('created_at', now()->year);
                        break;
                    case 'bulan_lalu':
                        $query->whereMonth('created_at', now()->subMonth()->month)
                              ->whereYear('created_at', now()->subMonth()->year);
                        break;
                    case 'tahun_lalu':
                        $query->whereYear('created_at', now()->subYear()->year);
                        break;
                    case 'kustom':
                        if ($dariTanggal && $sampaiTanggal) {
                            $query->whereBetween('created_at', [
                                Carbon::parse($dariTanggal)->startOfDay(),
                                Carbon::parse($sampaiTanggal)->endOfDay()
                            ]);
                        }
                        break;
                }

                // Execute the base query once and store results to reduce DB hits
                $results = $query->get();

                // Calculate statistics from in-memory collection (faster than multiple queries)
                $totalPengajuan = $results->count();
                $totalDiajukan = $results->where('status', 'Diajukan')->count();
                $dalamProses = $results->whereIn('status', ['Dalam Verifikasi', 'Diverifikasi'])->count();
                $sudahDiterima = $results->where('status', 'Sudah Diterima')->count();
                $ditolak = $results->where('status', 'Ditolak')->count();

                $prioritasTinggi = $results->where('prioritas', 'Tinggi')->count();
                $prioritasSedang = $results->where('prioritas', 'Sedang')->count();
                $prioritasRendah = $results->where('prioritas', 'Rendah')->count();
                $totalUrgent = $results->where('is_urgent', true)->count();

                // Generate chart data efficiently
                $chartData = $this->getBansosChartDataOptimized($results, $filter);

                // Get recent bansos applications (no need for a new query)
                $recentBansos = Bansos::with(['penduduk:id,nama,nik', 'jenisBansos:id,nama_bansos'])
                    ->select('id', 'penduduk_id', 'jenis_bansos_id', 'status', 'created_at', 'prioritas')
                    ->latest()
                    ->limit(5)
                    ->get();

                return response()->json([
                    'success' => true,
                    'stats' => [
                        'total_pengajuan' => $totalPengajuan,
                        'diajukan' => $totalDiajukan,
                        'dalam_proses' => $dalamProses,
                        'sudah_diterima' => $sudahDiterima,
                        'ditolak' => $ditolak,
                        'prioritas' => [
                            'tinggi' => $prioritasTinggi,
                            'sedang' => $prioritasSedang,
                            'rendah' => $prioritasRendah,
                        ],
                        'total_urgent' => $totalUrgent
                    ],
                    'chart' => $chartData,
                    'recent_bansos' => $recentBansos,
                    'periode' => $periode,
                    'filter' => $filter,
                    'chart_type' => $chartType,
                    'cached' => true,
                    'cachedAt' => now()->format('d/m/Y H:i:s')
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimized chart data generation for bansos
     */
    private function getBansosChartDataOptimized($results, $filter)
    {
        switch ($filter) {
            case 'status':
                $grouped = $results->groupBy('status');
                $labels = $grouped->keys()->toArray();
                $values = $grouped->map->count()->values()->toArray();

                // Colors match the admin panel
                $colors = [
                    'Diajukan' => '#f59e0b',
                    'Dalam Verifikasi' => '#60a5fa',
                    'Diverifikasi' => '#3b82f6',
                    'Disetujui' => '#10b981',
                    'Ditolak' => '#ef4444',
                    'Sudah Diterima' => '#84cc16',
                    'Dibatalkan' => '#9ca3af',
                ];

                $backgroundColors = array_map(function($label) use ($colors) {
                    return $colors[$label] ?? '#6b7280';
                }, $labels);
                break;

            case 'prioritas':
                $grouped = $results->groupBy('prioritas');
                $labels = $grouped->keys()->toArray();
                $values = $grouped->map->count()->values()->toArray();

                // Priority colors
                $prioritasColors = [
                    'Tinggi' => '#ef4444',
                    'Sedang' => '#f59e0b',
                    'Rendah' => '#22c55e',
                ];

                $backgroundColors = array_map(function($label) use ($prioritasColors) {
                    return $prioritasColors[$label] ?? '#6b7280';
                }, $labels);

                // Add urgent cases separately
                $urgentCount = $results->where('is_urgent', true)->count();
                if ($urgentCount > 0) {
                    $labels[] = 'Kasus Urgent';
                    $values[] = $urgentCount;
                    $backgroundColors[] = '#dc2626';
                }
                break;

            case 'sumber':
                $grouped = $results->groupBy('sumber_pengajuan');
                $labels = $grouped->keys()->toArray();
                $values = $grouped->map->count()->values()->toArray();

                // User-friendly labels
                $sumberLabels = [
                    'admin' => 'Admin/Petugas Desa',
                    'warga' => 'Pengajuan Warga',
                ];

                // Map technical keys to friendly labels
                $labels = array_map(function($label) use ($sumberLabels) {
                    return $sumberLabels[$label] ?? $label;
                }, $labels);

                // Source colors
                $sumberColors = [
                    'admin' => '#3b82f6',
                    'warga' => '#10b981',
                ];

                $backgroundColors = [];
                foreach ($grouped->keys() as $key) {
                    $backgroundColors[] = $sumberColors[$key] ?? '#6b7280';
                }
                break;

            case 'jenis':
            default:
                // For jenis, we need to fetch the jenis_bansos data
                $jenisIds = $results->pluck('jenis_bansos_id')->unique()->filter();

                if ($jenisIds->isEmpty()) {
                    $labels = ['Tidak Ada Data'];
                    $values = [100];
                    $backgroundColors = ['#d1d5db'];
                } else {
                    $jenisBansos = \App\Models\JenisBansos::whereIn('id', $jenisIds)->pluck('nama_bansos', 'id');

                    $grouped = $results->groupBy('jenis_bansos_id');
                    $labels = [];
                    $values = [];

                    foreach ($grouped as $id => $items) {
                        $labels[] = $jenisBansos[$id] ?? 'Tidak diketahui';
                        $values[] = $items->count();
                    }

                    $backgroundColors = $this->getChartColors(count($labels));
                }
                break;
        }

        // If no data found
        if (empty($values) || array_sum($values) === 0) {
            $labels = ['Tidak Ada Data'];
            $values = [100];
            $backgroundColors = ['#d1d5db'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $backgroundColors,
                    'borderWidth' => 1,
                ]
            ]
        ];
    }

    /**
     * Get standard chart colors
     *
     * @param int $count
     * @return array
     */
    private function getChartColors($count = 12)
    {
        $colors = [
            '#3b82f6', // blue-500
            '#ef4444', // red-500
            '#10b981', // emerald-500
            '#f59e0b', // amber-500
            '#8b5cf6', // violet-500
            '#ec4899', // pink-500
            '#6366f1', // indigo-500
            '#14b8a6', // teal-500
            '#f97316', // orange-500
            '#06b6d4', // cyan-500
            '#a855f7', // purple-500
            '#84cc16', // lime-500
        ];

        return array_slice($colors, 0, $count);
    }

    /**
     * Get empty chart data when no results are found
     *
     * @return array
     */
    private function getEmptyChartData()
    {
        return [
            'labels' => ['Tidak Ada Data'],
            'datasets' => [
                [
                    'data' => [100],
                    'backgroundColor' => ['#d1d5db'],
                    'borderColor' => ['#d1d5db'],
                    'borderWidth' => 1,
                ]
            ]
        ];
    }
}