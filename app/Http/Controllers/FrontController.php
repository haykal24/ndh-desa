<?php

namespace App\Http\Controllers;

use App\Models\ProfilDesa;
use App\Models\Berita;
use App\Models\Umkm;
use App\Models\StrukturPemerintahan;
use App\Models\KeuanganDesa;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Bansos;
use App\Models\Inventaris;
use App\Models\LayananDesa;

class FrontController extends Controller
{
    public function home()
    {
        $profilDesa = ProfilDesa::first();
        $strukturPemerintahan = StrukturPemerintahan::with('profilDesa')->first();
        $beritaTerbaru = Berita::latest()->take(12)->get();

        // Ubah query UMKM sesuai dengan struktur tabel yang ada
        $umkmUnggulan = Umkm::where('is_verified', true)
                            ->latest()
                            ->take(12)
                            ->get();

        // Tambahkan data statistik untuk counters
        $statistik = [
            'penduduk' => \App\Models\Penduduk::count(),
            'umkm' => \App\Models\Umkm::where('is_verified', true)->count(),
            'berita' => \App\Models\Berita::count(),
            'layanan' => \App\Models\LayananDesa::count()
        ];

        return view('front.home', compact(
            'profilDesa',
            'strukturPemerintahan',
            'beritaTerbaru',
            'umkmUnggulan',
            'statistik'
        ));
    }

    public function profil()
    {
        $profilDesa = ProfilDesa::first();
        $strukturPemerintahan = StrukturPemerintahan::with(['profilDesa', 'aparatDesa'])
            ->first();

        return view('front.profil', compact('profilDesa', 'strukturPemerintahan'));
    }

    public function berita(Request $request)
    {
        $profilDesa = ProfilDesa::first();

        $query = Berita::with(['creator', 'desa'])
                      ->latest();

        // Filter by kategori if provided
        if ($request->has('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        // Ubah jumlah item per halaman menjadi 6 (dari 9)
        $berita = $query->paginate(6);

        // Simpan query string (kategori filter) saat pindah halaman pagination
        $berita->appends($request->query());

        $kategoriColors = [
            'Umum' => 'blue',
            'Pengumuman' => 'yellow',
            'Kegiatan' => 'green',
            'Infrastruktur' => 'red',
            'Kesehatan' => 'sky',
            'Pendidikan' => 'purple',
        ];

        return view('front.berita', compact('berita', 'profilDesa', 'kategoriColors'));
    }

    public function beritaShow($id)
    {
        $profilDesa = ProfilDesa::first();
        $berita = Berita::with(['creator', 'desa'])->findOrFail($id);

        // Get related posts (same category, exclude current)
        $beritaTerkait = Berita::where('id', '!=', $berita->id)
                             ->where('kategori', $berita->kategori)
                             ->latest()
                             ->limit(3)
                             ->get();

        // If not enough related posts by category, get latest posts
        if ($beritaTerkait->count() < 3) {
            $moreBerita = Berita::where('id', '!=', $berita->id)
                             ->where('kategori', '!=', $berita->kategori)
                             ->latest()
                             ->limit(3 - $beritaTerkait->count())
                             ->get();

            $beritaTerkait = $beritaTerkait->concat($moreBerita);
        }

        $kategoriColors = [
            'Umum' => 'blue',
            'Pengumuman' => 'yellow',
            'Kegiatan' => 'green',
            'Infrastruktur' => 'red',
            'Kesehatan' => 'sky',
            'Pendidikan' => 'purple',
        ];

        return view('front.berita-detail', compact('berita', 'beritaTerkait', 'profilDesa', 'kategoriColors'));
    }

    public function umkm(Request $request)
    {
        $profilDesa = ProfilDesa::first();

        $query = Umkm::where('is_verified', true)->latest();

        // Filter by kategori if provided
        if ($request->has('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        $umkm = $query->paginate(12);

        // Simpan query string untuk pagination
        $umkm->appends($request->query());

        return view('front.umkm', compact('umkm', 'profilDesa'));
    }

    public function umkmShow($id)
    {
        $profilDesa = ProfilDesa::first();
        $umkm = Umkm::with(['penduduk', 'desa'])->where('is_verified', true)->findOrFail($id);

        // Get related UMKM (same kategori, exclude current)
        $umkmLainnya = Umkm::where('id', '!=', $umkm->id)
                         ->where('is_verified', true)
                         ->where('kategori', $umkm->kategori)
                         ->latest()
                         ->limit(4)
                         ->get();

        // If not enough related UMKM, get some random ones
        if ($umkmLainnya->count() < 4) {
            $moreUmkm = Umkm::where('id', '!=', $umkm->id)
                         ->where('is_verified', true)
                         ->where('kategori', '!=', $umkm->kategori)
                         ->inRandomOrder()
                         ->limit(4 - $umkmLainnya->count())
                         ->get();

            $umkmLainnya = $umkmLainnya->concat($moreUmkm);
        }

        return view('front.umkm-detail', compact('umkm', 'umkmLainnya', 'profilDesa'));
    }

    public function layanan(Request $request)
    {
        $profilDesa = ProfilDesa::first();

        $query = LayananDesa::with(['desa', 'creator'])->latest();

        // Filter by kategori if provided
        if ($request->has('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        $layanans = $query->paginate(9);
        $layanans->appends($request->query());

        // Keep the same kategori colors
        $kategoriColors = [
            'Surat' => 'blue',
            'Kesehatan' => 'green',
            'Pendidikan' => 'yellow',
            'Sosial' => 'purple',
            'Infrastruktur' => 'red',
        ];

        return view('front.layanan', compact('profilDesa', 'layanans', 'kategoriColors'));
    }

    public function layananShow($id)
    {
        $profilDesa = ProfilDesa::first();
        $layanan = LayananDesa::with(['desa', 'creator'])->findOrFail($id);

        // Get related layanan (same kategori, exclude current)
        $layananLainnya = LayananDesa::with(['desa'])
                          ->where('id', '!=', $layanan->id)
                          ->where('kategori', $layanan->kategori)
                          ->latest()
                          ->limit(3)
                          ->get();

        // If not enough related layanan, get some random ones
        if ($layananLainnya->count() < 3) {
            $moreLanayan = LayananDesa::with(['desa'])
                          ->where('id', '!=', $layanan->id)
                          ->where('kategori', '!=', $layanan->kategori)
                          ->inRandomOrder()
                          ->limit(3 - $layananLainnya->count())
                          ->get();

            $layananLainnya = $layananLainnya->concat($moreLanayan);
        }

        return view('front.layanan-detail', compact('layanan', 'layananLainnya', 'profilDesa'));
    }

    public function statistik()
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

    /**
     * Get financial data for statistics page with caching
     */
    public function keuanganData(Request $request)
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
     * Get social assistance (bansos) data with optimized caching
     */
    public function bansosData(Request $request)
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

    /**
     * Get inventory data for statistics page with optimized caching
     */
    public function inventarisData(Request $request)
    {
        try {
            if (!$request->ajax()) {
                return redirect()->route('statistik');
            }

            // Use a single cache key for all inventaris data
            $cacheKey = "inventaris_all_data";

            // Store this cache key for easier invalidation
            $cacheKeys = Cache::get('inventaris_cache_keys', []);
            if (!in_array($cacheKey, $cacheKeys)) {
                $cacheKeys[] = $cacheKey;
                Cache::put('inventaris_cache_keys', $cacheKeys, 24 * 60 * 60); // 24 hours
            }

            // Cache for 24 hours since we're showing all data
            $cacheDuration = 24 * 60;

            // Get data from cache or generate if not cached
            return Cache::remember($cacheKey, $cacheDuration, function() {
                // Query without any date filtering
                $query = Inventaris::query();

                // Optimized statistics retrieval - use aggregates in single query
                $stats = [
                    'total_inventaris' => $query->count(), // Total jenis barang (records)
                    'total_unit' => $query->sum('jumlah'), // Total unit barang (quantity)
                    'total_nilai' => $query->sum('nominal_harga') // Total nilai inventaris
                ];

                // Optimized condition stats - count both records and total units per condition
                $kondisiStats = DB::table('inventaris')
                    ->selectRaw('
                        kondisi,
                        COUNT(*) as jumlah_jenis,
                        SUM(jumlah) as jumlah_unit
                    ')
                    ->groupBy('kondisi')
                    ->get()
                    ->keyBy('kondisi');

                // Optimized status stats - count both records and total units per status
                $statusStats = DB::table('inventaris')
                    ->selectRaw('
                        status,
                        COUNT(*) as jumlah_jenis,
                        SUM(jumlah) as jumlah_unit
                    ')
                    ->groupBy('status')
                    ->get()
                    ->keyBy('status');

                // Format kondisi and status data for response
                $kondisiData = [
                    'baik' => (int)($kondisiStats->get('Baik')->jumlah_unit ?? 0),
                    'rusak_ringan' => (int)($kondisiStats->get('Rusak Ringan')->jumlah_unit ?? 0),
                    'rusak_berat' => (int)($kondisiStats->get('Rusak Berat')->jumlah_unit ?? 0)
                ];

                $statusData = [
                    'tersedia' => (int)($statusStats->get('Tersedia')->jumlah_unit ?? 0),
                    'dipinjam' => (int)($statusStats->get('Dipinjam')->jumlah_unit ?? 0),
                    'dalam_perbaikan' => (int)($statusStats->get('Dalam Perbaikan')->jumlah_unit ?? 0)
                ];

                // Efficiently retrieve latest items
                $latestItems = Inventaris::select('kode_barang', 'nama_barang', 'kategori', 'jumlah', 'nominal_harga', 'tanggal_perolehan', 'kondisi', 'status')
                    ->latest('tanggal_perolehan')
                    ->limit(5)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'kode_barang' => $item->kode_barang,
                            'nama_barang' => $item->nama_barang,
                            'kategori' => $item->kategori,
                            'jumlah' => $item->jumlah . ' unit',
                            'nominal_harga' => 'Rp ' . number_format($item->nominal_harga, 0, ',', '.'),
                            'tanggal_perolehan' => Carbon::parse($item->tanggal_perolehan)->format('d/m/Y'),
                            'kondisi' => $item->kondisi,
                            'status' => $item->status
                        ];
                    });

                return response()->json([
                    'success' => true,
                    'stats' => [
                        'total_inventaris' => $stats['total_inventaris'],
                        'total_unit' => $stats['total_unit'],
                        'total_nilai' => 'Rp ' . number_format($stats['total_nilai'], 0, ',', '.'),
                        'total_nilai_raw' => $stats['total_nilai'],
                        'kondisi' => $kondisiData,
                        'status' => $statusData,
                        // Additional data to clarify unit/item counts
                        'kondisi_jenis' => [
                            'baik' => (int)($kondisiStats->get('Baik')->jumlah_jenis ?? 0),
                            'rusak_ringan' => (int)($kondisiStats->get('Rusak Ringan')->jumlah_jenis ?? 0),
                            'rusak_berat' => (int)($kondisiStats->get('Rusak Berat')->jumlah_jenis ?? 0)
                        ],
                        'status_jenis' => [
                            'tersedia' => (int)($statusStats->get('Tersedia')->jumlah_jenis ?? 0),
                            'dipinjam' => (int)($statusStats->get('Dipinjam')->jumlah_jenis ?? 0),
                            'dalam_perbaikan' => (int)($statusStats->get('Dalam Perbaikan')->jumlah_jenis ?? 0)
                        ]
                    ],
                    'latest_items' => $latestItems,
                    'periode' => 'semua_waktu',
                    'periode_label' => 'Semua Data',
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

    /**
     * Clear all statistical data caches
     */
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