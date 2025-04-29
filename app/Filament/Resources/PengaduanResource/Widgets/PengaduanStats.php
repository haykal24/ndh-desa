<?php

namespace App\Filament\Resources\PengaduanResource\Widgets;

use App\Models\Pengaduan;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Carbon\Carbon;

class PengaduanStats extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';

    protected int | string | array $columnSpan = 'full';

    // Properti untuk filter
    public ?string $periode = 'semua';
    public ?string $dariTanggal = null;
    public ?string $sampaiTanggal = null;

    // Listener untuk dashboard filter
    #[On('filter-changed')]
    public function onDashboardFilterChanged(string $dari_tanggal = null, string $sampai_tanggal = null, string $periode = 'bulan_ini'): void
    {
        // Simpan periode
        $this->periode = $periode;

        // Simpan tanggal kustom jika ada
        if ($periode === 'kustom' && $dari_tanggal && $sampai_tanggal) {
            $this->dariTanggal = $dari_tanggal;
            $this->sampaiTanggal = $sampai_tanggal;
        } else {
            // Gunakan setPeriodeFilter untuk mengatur tanggal berdasarkan periode
            $this->setPeriodeFilter($periode);
        }

        // Batalkan cache untuk memaksa refresh data
        Cache::forget('pengaduan_stats');

        // Refresh widget
        $this->dispatch('$refresh');
    }

    // Konversi filter periode
    public function setPeriodeFilter(string $periode): void
    {
        // Mapping periode dari dashboard ke widget
        $periodeMap = [
            'semua_waktu' => 'semua',
            'hari_ini' => 'today',
            'minggu_ini' => 'this_week',
            'bulan_ini' => 'this_month',
            'tahun_ini' => 'this_year',
            'bulan_lalu' => 'last_month',
            'tahun_lalu' => 'last_year',
        ];

        $this->periode = $periodeMap[$periode] ?? 'semua';

        // Set tanggal sesuai periode
        if ($periode === 'semua_waktu') {
            $this->dariTanggal = null;
            $this->sampaiTanggal = null;
            return;
        }

        switch ($periode) {
            case 'hari_ini':
                $this->dariTanggal = now()->toDateString();
                $this->sampaiTanggal = now()->toDateString();
                break;
            case 'minggu_ini':
                $this->dariTanggal = now()->startOfWeek()->toDateString();
                $this->sampaiTanggal = now()->endOfWeek()->toDateString();
                break;
            case 'bulan_ini':
                $this->dariTanggal = now()->startOfMonth()->toDateString();
                $this->sampaiTanggal = now()->endOfMonth()->toDateString();
                break;
            case 'tahun_ini':
                $this->dariTanggal = now()->startOfYear()->toDateString();
                $this->sampaiTanggal = now()->endOfYear()->toDateString();
                break;
            case 'bulan_lalu':
                $this->dariTanggal = now()->subMonth()->startOfMonth()->toDateString();
                $this->sampaiTanggal = now()->subMonth()->endOfMonth()->toDateString();
                break;
            case 'tahun_lalu':
                $this->dariTanggal = now()->subYear()->startOfYear()->toDateString();
                $this->sampaiTanggal = now()->subYear()->endOfYear()->toDateString();
                break;
        }
    }

    protected function getStats(): array
    {
        // Gunakan cache untuk meningkatkan performa
        $cacheKey = 'pengaduan_stats_' . $this->periode;

        if ($this->dariTanggal && $this->sampaiTanggal) {
            $cacheKey .= '_' . $this->dariTanggal . '_' . $this->sampaiTanggal;
        }

        return Cache::remember($cacheKey, 60, function () {
            // Base query
            $baseQuery = DB::table('pengaduan')->whereNull('deleted_at');

            // Terapkan filter periode
            if ($this->periode !== 'semua') {
                if ($this->dariTanggal && $this->sampaiTanggal) {
                    $baseQuery->whereBetween('created_at', [
                        Carbon::parse($this->dariTanggal)->startOfDay(),
                        Carbon::parse($this->sampaiTanggal)->endOfDay(),
                    ]);
                } elseif ($this->periode === 'today') {
                    $baseQuery->whereDate('created_at', today());
                } elseif ($this->periode === 'this_week') {
                    $baseQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                } elseif ($this->periode === 'this_month') {
                    $baseQuery->whereMonth('created_at', now()->month)
                            ->whereYear('created_at', now()->year);
                } elseif ($this->periode === 'this_year') {
                    $baseQuery->whereYear('created_at', now()->year);
                } elseif ($this->periode === 'last_month') {
                    $baseQuery->whereMonth('created_at', now()->subMonth()->month)
                            ->whereYear('created_at', now()->subMonth()->year);
                } elseif ($this->periode === 'last_year') {
                    $baseQuery->whereYear('created_at', now()->subYear()->year);
                }
            }

            // Hitung total pengaduan per status
            $totalPerStatus = (clone $baseQuery)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            // Hitung total per kategori untuk mengetahui kategori terbanyak
            $totalPerKategori = (clone $baseQuery)
                ->select('kategori', DB::raw('count(*) as total'))
                ->groupBy('kategori')
                ->orderByDesc('total')
                ->limit(1)
                ->first();

            $kategoriTerbanyak = $totalPerKategori ? $totalPerKategori->kategori : 'Tidak ada';
            $jumlahKategoriTerbanyak = $totalPerKategori ? $totalPerKategori->total : 0;

            // Total semua pengaduan
            $totalPengaduan = array_sum($totalPerStatus);

            // Persentase penyelesaian (selesai / total)
            $persentasePenyelesaian = $totalPengaduan > 0
                ? round((($totalPerStatus['Selesai'] ?? 0) / $totalPengaduan) * 100)
                : 0;

            // Pengaduan prioritas tinggi belum ditangani
            $prioritasTinggi = (clone $baseQuery)
                ->where('prioritas', 'Tinggi')
                ->where('status', 'Belum Ditangani')
                ->count();

            // Pengaduan minggu ini
            $pengaduanMingguIni = (clone $baseQuery)
                ->where('created_at', '>=', now()->startOfWeek())
                ->count();

            // Rata-rata waktu penanganan (dalam jam)
            $waktuPenanganan = (clone $baseQuery)
                ->whereIn('status', ['Selesai', 'Ditolak'])
                ->whereNotNull('tanggal_tanggapan')
                ->whereNotNull('created_at')
                ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, tanggal_tanggapan)) as rata_rata'))
                ->first();

            $rataRataWaktu = $waktuPenanganan && $waktuPenanganan->rata_rata ?
                round($waktuPenanganan->rata_rata) : 0;

            // Pengaduan yang belum selesai (belum ditangani + sedang diproses)
            $belumSelesai = ($totalPerStatus['Belum Ditangani'] ?? 0) + ($totalPerStatus['Sedang Diproses'] ?? 0);

            // Periode label untuk deskripsi
            $periodeLabel = match($this->periode) {
                'today' => 'hari ini',
                'this_week' => 'minggu ini',
                'this_month' => 'bulan ini',
                'this_year' => 'tahun ini',
                'last_month' => 'bulan lalu',
                'last_year' => 'tahun lalu',
                default => 'semua waktu'
            };

            return [
                Stat::make('Total Pengaduan', number_format($totalPengaduan, 0, ',', '.'))
                    ->description('Pengaduan warga ' . ($this->periode !== 'semua' ? $periodeLabel : ''))
                    ->descriptionIcon('heroicon-o-megaphone')
                    ->color('primary')
                    ->chart([7, 8, 5, 9, 12, 10, $totalPengaduan]),

                Stat::make('Belum Ditangani', number_format($totalPerStatus['Belum Ditangani'] ?? 0, 0, ',', '.'))
                    ->description('Perlu tanggapan segera')
                    ->descriptionIcon('heroicon-o-clock')
                    ->color('warning'),

                Stat::make('Prioritas Tinggi', number_format($prioritasTinggi, 0, ',', '.'))
                    ->description('Butuh penanganan cepat')
                    ->descriptionIcon('heroicon-o-exclamation-triangle')
                    ->color('danger'),

                Stat::make('Sedang Diproses', number_format($totalPerStatus['Sedang Diproses'] ?? 0, 0, ',', '.'))
                    ->description('Dalam penanganan')
                    ->descriptionIcon('heroicon-o-arrow-path')
                    ->color('info'),

                Stat::make('Pengaduan Aktif', number_format($belumSelesai, 0, ',', '.'))
                    ->description('Belum ditangani + Sedang diproses')
                    ->descriptionIcon('heroicon-o-bell-alert')
                    ->color('gray'),

                Stat::make('Selesai Ditangani', number_format($totalPerStatus['Selesai'] ?? 0, 0, ',', '.'))
                    ->description($persentasePenyelesaian . '% dari total pengaduan')
                    ->descriptionIcon('heroicon-o-check-circle')
                    ->color('success')
                    ->chart([2, 3, 5, 4, 6, 8, $totalPerStatus['Selesai'] ?? 0]),

                Stat::make('Kategori Terbanyak', $kategoriTerbanyak)
                    ->description($jumlahKategoriTerbanyak . ' pengaduan')
                    ->descriptionIcon('heroicon-o-bars-3')
                    ->color('indigo'),

                Stat::make('Pengaduan Minggu Ini', number_format($pengaduanMingguIni, 0, ',', '.'))
                    ->description('Sejak ' . now()->startOfWeek()->format('d M Y'))
                    ->descriptionIcon('heroicon-o-calendar')
                    ->color('blue'),

                Stat::make('Rata-rata Penanganan', $rataRataWaktu . ' jam')
                    ->description('Waktu respon pengaduan')
                    ->descriptionIcon('heroicon-o-clock')
                    ->color('emerald'),

                Stat::make('Ditolak', number_format($totalPerStatus['Ditolak'] ?? 0, 0, ',', '.'))
                    ->description('Pengaduan tidak valid')
                    ->descriptionIcon('heroicon-o-x-circle')
                    ->color('rose'),
            ];
        });
    }
}