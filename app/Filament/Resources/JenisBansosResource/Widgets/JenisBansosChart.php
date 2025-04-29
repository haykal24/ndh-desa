<?php

namespace App\Filament\Resources\JenisBansosResource\Widgets;

use App\Models\JenisBansos;
use App\Models\Bansos;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class JenisBansosChart extends ChartWidget
{
    protected static ?string $heading = 'Grafik Bantuan Sosial';
    protected static ?string $pollingInterval = '60s';
    protected int|string|array $columnSpan = 'full';
    protected static ?string $maxHeight = '300px';

    // Filter properties
    public ?string $filter = 'kategori';
    protected ?string $chartType = 'doughnut';
    public ?string $periode = 'semua';

    // Tambahan property untuk tanggal
    public ?string $dariTanggal = null;
    public ?string $sampaiTanggal = null;

    // Determine chart type
    protected function getType(): string
    {
        // Untuk grafik tahunan sebaiknya gunakan line chart
        if ($this->filter === 'tahunan') {
            return 'line';
        }

        return $this->chartType;
    }

    // Filter dropdown
    protected function getFilters(): ?array
    {
        return [
            'kategori' => 'Berdasarkan Kategori',
            'bentuk' => 'Berdasarkan Bentuk Bantuan',
            'periode' => 'Berdasarkan Periode Bantuan',
            'instansi' => 'Berdasarkan Instansi Pemberi',
            'tahunan' => 'Trend Tahunan',
        ];
    }

    // Event listener for dashboard filter
    #[On('filter-changed')]
    public function onDashboardFilterChanged(string $dari_tanggal = null, string $sampai_tanggal = null, string $periode = 'bulan_ini'): void
    {
        // Simpan periode
        if (isset($periode)) {
            $this->setPeriodeFilter($periode);
        }

        // Simpan tanggal kustom jika ada
        if ($periode === 'kustom' && $dari_tanggal && $sampai_tanggal) {
            $this->dariTanggal = $dari_tanggal;
            $this->sampaiTanggal = $sampai_tanggal;
        }

        // Refresh chart dengan cara Livewire standard - tidak pakai markAsNeedsRefresh
        $this->dispatch('$refresh');
    }

    // Event listener for stats widget filter
    #[On('bansos-filter-changed')]
    public function onBansosFilterChanged($periode, $dari_tanggal = null, $sampai_tanggal = null): void
    {
        $this->periode = $periode;
        $this->dariTanggal = $dari_tanggal;
        $this->sampaiTanggal = $sampai_tanggal;

        // Refresh chart dengan cara Livewire standard - tidak pakai markAsNeedsRefresh
        $this->dispatch('$refresh');
    }

    // Perbaiki metode setPeriodeFilter untuk menangani tanggal juga
    public function setPeriodeFilter(string $periode): void
    {
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

    // Handle chart type switch
    public function switchChartType(string $type): void
    {
        $this->chartType = $type;
    }

    // Chart header actions
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('switchChartType')
                ->label('Tipe Chart')
                ->icon('heroicon-m-chart-pie')
                ->button()
                ->color('gray')
                ->modalHeading('Pilih Tipe Chart')
                ->modalSubmitActionLabel('Pilih')
                ->form([
                    \Filament\Forms\Components\Grid::make(3)
                        ->schema([
                            \Filament\Forms\Components\Radio::make('type')
                                ->label('Tipe Chart')
                                ->options([
                                    'doughnut' => 'Doughnut',
                                    'pie' => 'Pie',
                                    'bar' => 'Bar',
                                    'polarArea' => 'Polar Area',
                                ])
                                ->default($this->chartType)
                                ->required(),
                        ]),
                ])
                ->action(function (array $data): void {
                    $this->switchChartType($data['type']);
                }),
        ];
    }

    // Data for chart
    protected function getData(): array
    {
        // Query base untuk filter periode
        $query = JenisBansos::query();

        if ($this->periode === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($this->periode === 'this_week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->periode === 'this_month') {
            $query->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
        } elseif ($this->periode === 'this_year') {
            $query->whereYear('created_at', now()->year);
        } elseif ($this->periode === 'last_month') {
            $query->whereMonth('created_at', now()->subMonth()->month)
                  ->whereYear('created_at', now()->subMonth()->year);
        } elseif ($this->periode === 'last_year') {
            $query->whereYear('created_at', now()->subYear()->year);
        }

        // Generate chart data based on selected filter
        if ($this->filter === 'kategori') {
            return $this->getKategoriData($query);
        } elseif ($this->filter === 'bentuk') {
            return $this->getBentukBantuanData($query);
        } elseif ($this->filter === 'periode') {
            return $this->getPeriodeBantuanData($query);
        } elseif ($this->filter === 'instansi') {
            return $this->getInstansiData($query);
        } elseif ($this->filter === 'tahunan') {
            return $this->getTahunanData();
        }

        // Default to kategori
        return $this->getKategoriData($query);
    }

    // Data berdasarkan kategori
    protected function getKategoriData($query)
    {
        $data = $query->select('kategori', DB::raw('count(*) as total'))
            ->groupBy('kategori')
            ->orderBy('total', 'desc')
            ->get();

        if ($data->isEmpty()) {
            return $this->getEmptyData();
        }

        $colors = $this->getChartColors();

        return [
            'labels' => $data->pluck('kategori')->toArray(),
            'datasets' => [
                [
                    'label' => 'Jumlah Jenis Bantuan',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $data->count()),
                    'borderColor' => array_slice($colors, 0, $data->count()),
                    'borderWidth' => 1,
                    'hoverOffset' => 15,
                ],
            ],
        ];
    }

    // Data berdasarkan bentuk bantuan
    protected function getBentukBantuanData($query)
    {
        $data = $query->select('bentuk_bantuan', DB::raw('count(*) as total'))
            ->groupBy('bentuk_bantuan')
            ->orderBy('total', 'desc')
            ->get();

        if ($data->isEmpty()) {
            return $this->getEmptyData();
        }

        $colors = $this->getChartColors();
        $bentukOptions = JenisBansos::getBentukBantuanOptions();

        return [
            'labels' => $data->pluck('bentuk_bantuan')
                ->map(fn($item) => $bentukOptions[$item] ?? $item)
                ->toArray(),
            'datasets' => [
                [
                    'label' => 'Jumlah Jenis Bantuan',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $data->count()),
                    'borderColor' => array_slice($colors, 0, $data->count()),
                    'borderWidth' => 1,
                    'hoverOffset' => 15,
                ],
            ],
        ];
    }

    // Data berdasarkan periode bantuan
    protected function getPeriodeBantuanData($query)
    {
        $data = $query->select('periode', DB::raw('count(*) as total'))
            ->groupBy('periode')
            ->orderBy('total', 'desc')
            ->get();

        if ($data->isEmpty()) {
            return $this->getEmptyData();
        }

        $colors = $this->getChartColors();

        return [
            'labels' => $data->pluck('periode')->toArray(),
            'datasets' => [
                [
                    'label' => 'Jumlah Jenis Bantuan',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $data->count()),
                    'borderColor' => array_slice($colors, 0, $data->count()),
                    'borderWidth' => 1,
                    'hoverOffset' => 15,
                ],
            ],
        ];
    }

    // Data berdasarkan instansi pemberi
    protected function getInstansiData($query)
    {
        $data = $query->select('instansi_pemberi', DB::raw('count(*) as total'))
            ->groupBy('instansi_pemberi')
            ->orderBy('total', 'desc')
            ->limit(10) // Batasi jumlah instansi yang ditampilkan
            ->get();

        if ($data->isEmpty()) {
            return $this->getEmptyData();
        }

        $colors = $this->getChartColors();

        return [
            'labels' => $data->pluck('instansi_pemberi')->toArray(),
            'datasets' => [
                [
                    'label' => 'Jumlah Jenis Bantuan',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $data->count()),
                    'borderColor' => array_slice($colors, 0, $data->count()),
                    'borderWidth' => 1,
                    'hoverOffset' => 15,
                ],
            ],
        ];
    }

    // Data trend tahunan
    protected function getTahunanData()
    {
        // Ambil 5 tahun terakhir
        $currentYear = Carbon::now()->year;
        $years = range($currentYear - 4, $currentYear);

        // Siapkan data bantuan per tahun
        $bantuanPerTahun = DB::table('jenis_bansos')
            ->selectRaw('YEAR(created_at) as year, COUNT(*) as total')
            ->whereIn(DB::raw('YEAR(created_at)'), $years)
            ->groupBy(DB::raw('YEAR(created_at)'))
            ->orderBy('year')
            ->get()
            ->keyBy('year');

        // Siapkan data penerima per tahun
        $penerimaPerTahun = DB::table('bansos')
            ->selectRaw('YEAR(created_at) as year, COUNT(*) as total')
            ->whereIn(DB::raw('YEAR(created_at)'), $years)
            ->groupBy(DB::raw('YEAR(created_at)'))
            ->orderBy('year')
            ->get()
            ->keyBy('year');

        // Siapkan datasets
        $bantuanData = [];
        $penerimaData = [];

        foreach ($years as $year) {
            $bantuanData[] = $bantuanPerTahun[$year]->total ?? 0;
            $penerimaData[] = $penerimaPerTahun[$year]->total ?? 0;
        }

        return [
            'labels' => array_map('strval', $years),
            'datasets' => [
                [
                    'label' => 'Jenis Bantuan',
                    'data' => $bantuanData,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.3,
                    'fill' => true,
                ],
                [
                    'label' => 'Penerima Bantuan',
                    'data' => $penerimaData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.3,
                    'fill' => true,
                ],
            ],
        ];
    }

    // Empty chart data
    protected function getEmptyData()
    {
        return [
            'labels' => ['Tidak Ada Data'],
            'datasets' => [
                [
                    'label' => 'Tidak Ada Data',
                    'data' => [100],
                    'backgroundColor' => ['#d1d5db'],
                    'borderColor' => ['#d1d5db'],
                ],
            ],
        ];
    }

    // Warna untuk chart
    protected function getChartColors(): array
    {
        return [
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
    }

    // Chart title
    protected function getChartTitle(): string
    {
        $periodeLabel = match($this->periode) {
            'today' => 'Hari Ini',
            'this_week' => 'Minggu Ini',
            'this_month' => 'Bulan Ini',
            'this_year' => 'Tahun Ini',
            'last_month' => 'Bulan Lalu',
            'last_year' => 'Tahun Lalu',
            default => 'Semua Waktu'
        };

        $filterLabel = match($this->filter) {
            'kategori' => 'Kategori Bantuan',
            'bentuk' => 'Bentuk Bantuan',
            'periode' => 'Periode Bantuan',
            'instansi' => 'Instansi Pemberi',
            'tahunan' => 'Trend Bantuan 5 Tahun Terakhir',
            default => 'Kategori Bantuan'
        };

        return "Distribusi {$filterLabel} ({$periodeLabel})";
    }

    // Chart options
    protected function getOptions(): array
    {
        // Opsi dasar
        $baseOptions = [
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                    'labels' => [
                        'font' => [
                            'size' => 11,
                        ],
                        'padding' => 15,
                        'usePointStyle' => true,
                    ],
                ],
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                    'backgroundColor' => 'rgba(17, 24, 39, 0.95)',
                    'padding' => 10,
                ],
                'title' => [
                    'display' => true,
                    'text' => $this->getChartTitle(),
                    'font' => [
                        'size' => 14,
                        'weight' => 'bold',
                    ],
                    'padding' => [
                        'top' => 10,
                        'bottom' => 20
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
            'animation' => [
                'duration' => 1000,
            ],
            'layout' => [
                'padding' => [
                    'left' => 5,
                    'right' => 5,
                    'top' => 10,
                    'bottom' => 10
                ],
            ],
        ];

        // Opsi untuk chart doughnut/pie
        if (in_array($this->chartType, ['doughnut', 'pie'])) {
            return array_merge($baseOptions, [
                'cutout' => $this->chartType === 'doughnut' ? '65%' : '0',
                'radius' => '90%',
            ]);
        }

        // Opsi untuk chart bar
        if ($this->chartType === 'bar') {
            return array_merge($baseOptions, [
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                    'x' => [
                        'grid' => [
                            'display' => false,
                        ],
                    ],
                ],
            ]);
        }

        // Opsi untuk chart line (tren tahunan)
        if ($this->filter === 'tahunan' || $this->chartType === 'line') {
            return array_merge($baseOptions, [
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                    'x' => [
                        'grid' => [
                            'display' => true,
                        ],
                    ],
                ],
                'elements' => [
                    'line' => [
                        'tension' => 0.3,
                    ],
                    'point' => [
                        'radius' => 4,
                        'hoverRadius' => 6,
                    ],
                ],
            ]);
        }

        // Opsi default
        return $baseOptions;
    }
}