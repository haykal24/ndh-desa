<?php

namespace App\Filament\Resources\PengaduanResource\Widgets;

use App\Models\Pengaduan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Carbon\Carbon;

class PengaduanChart extends ChartWidget
{
    protected static ?string $heading = 'Grafik Pengaduan Warga';
    protected static ?string $pollingInterval = '60s';
    protected int|string|array $columnSpan = 'full';
    protected static ?string $maxHeight = '300px';

    // Filter properties
    public ?string $filter = 'status';
    protected ?string $chartType = 'doughnut';
    public ?string $periode = 'semua';
    public ?string $dariTanggal = null;
    public ?string $sampaiTanggal = null;

    // Determine chart type
    protected function getType(): string
    {
        return match($this->filter) {
            'bulanan' => 'line',
            default => $this->chartType,
        };
    }

    // Filter dropdown
    protected function getFilters(): ?array
    {
        return [
            'status' => 'Berdasarkan Status',
            'kategori' => 'Berdasarkan Kategori',
            'prioritas' => 'Berdasarkan Prioritas',
            'bulanan' => 'Trend Bulanan',
        ];
    }

    // Event listener for dashboard filter
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
            // Konversi periode dashboard ke format widget
            $this->setPeriodeFilter($periode);
        }

        // Refresh chart
        $this->dispatch('$refresh');
    }

    // Convert dashboard period filter
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

    // Switch chart type
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

    // Get chart title
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
            'status' => 'Status',
            'kategori' => 'Kategori',
            'prioritas' => 'Prioritas',
            'bulanan' => 'Trend Bulanan',
            default => 'Status'
        };

        return "Pengaduan Berdasarkan $filterLabel - $periodeLabel";
    }

    // Data for chart
    protected function getData(): array
    {
        // Base query
        $query = DB::table('pengaduan')->whereNull('deleted_at');

        // Apply period filter
        if ($this->periode !== 'semua') {
            if ($this->dariTanggal && $this->sampaiTanggal) {
                $query->whereBetween('created_at', [
                    Carbon::parse($this->dariTanggal)->startOfDay(),
                    Carbon::parse($this->sampaiTanggal)->endOfDay(),
                ]);
            } elseif ($this->periode === 'today') {
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
        }

        // Generate chart data based on selected filter
        if ($this->filter === 'status') {
            return $this->getStatusData($query);
        } elseif ($this->filter === 'kategori') {
            return $this->getKategoriData($query);
        } elseif ($this->filter === 'prioritas') {
            return $this->getPrioritasData($query);
        } elseif ($this->filter === 'bulanan') {
            return $this->getBulananData();
        }

        // Default to status
        return $this->getStatusData($query);
    }

    // Chart data by status
    protected function getStatusData($query)
    {
        $data = $query->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        if ($data->isEmpty()) {
            return $this->getEmptyData();
        }

        $colors = [
            'Belum Ditangani' => '#f59e0b', // amber
            'Sedang Diproses' => '#3b82f6', // blue
            'Selesai' => '#10b981', // emerald
            'Ditolak' => '#ef4444', // red
        ];

        $backgroundColors = [];
        $borderColors = [];

        foreach ($data as $item) {
            $backgroundColors[] = $colors[$item->status] ?? '#6b7280'; // gray default
            $borderColors[] = $colors[$item->status] ?? '#6b7280';
        }

        return [
            'labels' => $data->pluck('status')->toArray(),
            'datasets' => [
                [
                    'label' => 'Jumlah Pengaduan',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 1,
                    'hoverOffset' => 15,
                ],
            ],
        ];
    }

    // Chart data by category
    protected function getKategoriData($query)
    {
        $data = $query->select('kategori', DB::raw('count(*) as total'))
            ->groupBy('kategori')
            ->orderByDesc('total')
            ->get();

        if ($data->isEmpty()) {
            return $this->getEmptyData();
        }

        $colors = $this->getChartColors();

        return [
            'labels' => $data->pluck('kategori')->toArray(),
            'datasets' => [
                [
                    'label' => 'Jumlah Pengaduan',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $data->count()),
                    'borderColor' => array_slice($colors, 0, $data->count()),
                    'borderWidth' => 1,
                    'hoverOffset' => 15,
                ],
            ],
        ];
    }

    // Chart data by priority
    protected function getPrioritasData($query)
    {
        $data = $query->select('prioritas', DB::raw('count(*) as total'))
            ->groupBy('prioritas')
            ->orderByRaw("CASE
                WHEN prioritas = 'Tinggi' THEN 1
                WHEN prioritas = 'Sedang' THEN 2
                WHEN prioritas = 'Rendah' THEN 3
                ELSE 4 END")
            ->get();

        if ($data->isEmpty()) {
            return $this->getEmptyData();
        }

        $prioritasColors = [
            'Tinggi' => '#ef4444', // red
            'Sedang' => '#f59e0b', // amber
            'Rendah' => '#22c55e', // green
        ];

        $backgroundColors = [];
        $borderColors = [];

        foreach ($data as $item) {
            $backgroundColors[] = $prioritasColors[$item->prioritas] ?? '#6b7280';
            $borderColors[] = $prioritasColors[$item->prioritas] ?? '#6b7280';
        }

        return [
            'labels' => $data->pluck('prioritas')->toArray(),
            'datasets' => [
                [
                    'label' => 'Jumlah Pengaduan',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 1,
                    'hoverOffset' => 15,
                ],
            ],
        ];
    }

    // Monthly trend data
    protected function getBulananData()
    {
        // Determine date range based on periode
        if ($this->periode === 'this_year') {
            $startDate = now()->startOfYear();
            $endDate = now()->endOfYear();
        } elseif ($this->periode === 'last_year') {
            $startDate = now()->subYear()->startOfYear();
            $endDate = now()->subYear()->endOfYear();
        } else {
            // Default to last 6 months
            $startDate = now()->subMonths(5)->startOfMonth();
            $endDate = now()->endOfMonth();
        }

        // Generate array of months
        $dates = [];
        $current = clone $startDate;

        while ($current <= $endDate) {
            $dates[] = $current->format('Y-m');
            $current->addMonth();
        }

        // Get data for each status by month
        $statuses = ['Belum Ditangani', 'Sedang Diproses', 'Selesai', 'Ditolak'];
        $datasets = [];

        $colors = [
            'Belum Ditangani' => '#f59e0b',
            'Sedang Diproses' => '#3b82f6',
            'Selesai' => '#10b981',
            'Ditolak' => '#ef4444',
        ];

        foreach ($statuses as $status) {
            $statusData = [];

            foreach ($dates as $date) {
                $year = substr($date, 0, 4);
                $month = substr($date, 5, 2);

                $count = DB::table('pengaduan')
                    ->whereNull('deleted_at')
                    ->where('status', $status)
                    ->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->count();

                $statusData[] = $count;
            }

            // Only add dataset if there's data
            if (array_sum($statusData) > 0) {
                $datasets[] = [
                    'label' => $status,
                    'data' => $statusData,
                    'borderColor' => $colors[$status],
                    'backgroundColor' => $colors[$status] . '33', // Add transparency
                    'tension' => 0.3,
                    'fill' => false,
                ];
            }
        }

        // If no data, add dummy dataset
        if (empty($datasets)) {
            $datasets[] = [
                'label' => 'Tidak Ada Data',
                'data' => array_fill(0, count($dates), 0),
                'borderColor' => '#9ca3af',
                'backgroundColor' => '#9ca3af33',
                'tension' => 0.3,
                'fill' => false,
            ];
        }

        // Format month names for display
        $formattedDates = [];
        foreach ($dates as $date) {
            $formattedDates[] = Carbon::createFromFormat('Y-m', $date)->format('M Y');
        }

        return [
            'labels' => $formattedDates,
            'datasets' => $datasets,
        ];
    }

    // Empty data placeholder
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

    // Chart colors
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

    // Chart options
    protected function getOptions(): array
    {
        if ($this->filter === 'bulanan') {
            return [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'ticks' => [
                            'precision' => 0,
                        ],
                    ],
                ],
                'plugins' => [
                    'legend' => [
                        'position' => 'top',
                    ],
                    'tooltip' => [
                        'mode' => 'index',
                        'intersect' => false,
                    ],
                ],
            ];
        }

        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'animation' => [
                'animateScale' => true,
                'animateRotate' => true,
            ],
        ];
    }
}