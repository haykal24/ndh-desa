<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Inventaris;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InventarisController extends Controller
{
    /**
     * Get inventory data for statistics page with optimized caching
     */
    public function getData(Request $request)
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
}