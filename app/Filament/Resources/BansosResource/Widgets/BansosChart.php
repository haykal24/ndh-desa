<?php

namespace App\Filament\Resources\BansosResource\Widgets;

use App\Models\Bansos;
use App\Models\JenisBansos;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BansosChart extends ChartWidget
{
    protected static ?string $heading = 'Grafik Bantuan Sosial';
    protected static ?string $pollingInterval = '60s';
    protected int|string|array $columnSpan = 'full';
    protected static ?string $maxHeight = '300px';

    // Filter properties
    public ?string $filter = 'status';
    protected ?string $chartType = 'doughnut';
    public ?string $periode = 'semua';

    // Tambahkan properti berikut jika belum ada
    protected $listeners = [
        'filter-changed' => 'onFilterChanged',
    ];

    // Tambahan property untuk tanggal
    public ?string $dariTanggal = null;
    public ?string $sampaiTanggal = null;

    // Determine chart type
    protected function getType(): string
    {
        return $this->chartType;
    }

    // Filter dropdown
    protected function getFilters(): ?array
    {
        return [
            'status' => 'Berdasarkan Status',
            'jenis' => 'Berdasarkan Jenis Bantuan',
            'kategori' => 'Berdasarkan Kategori Bantuan',
            'prioritas' => 'Berdasarkan Prioritas',
            'sumber' => 'Berdasarkan Sumber Pengajuan',
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
            // Gunakan setPeriodeFilter untuk mengatur tanggal berdasarkan periode
            $this->setPeriodeFilter($periode);
        }

        // Refresh chart (tanpa markAsNeedsRefresh)
        $this->dispatch('$refresh');
    }

    // Perbaiki setPeriodeFilter untuk menangani tanggal
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
        $query = Bansos::query();

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
        if ($this->filter === 'status') {
            return $this->getStatusData($query);
        } elseif ($this->filter === 'jenis') {
            return $this->getJenisBantuanData($query);
        } elseif ($this->filter === 'kategori') {
            return $this->getKategoriBantuanData($query);
        } elseif ($this->filter === 'prioritas') {
            return $this->getPrioritasData($query);
        } elseif ($this->filter === 'sumber') {
            return $this->getSumberPengajuanData($query);
        }

        // Default to status
        return $this->getStatusData($query);
    }

    // Data berdasarkan status bantuan
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
            'Diajukan' => '#f59e0b', // amber
            'Dalam Verifikasi' => '#60a5fa', // lighter blue
            'Diverifikasi' => '#3b82f6', // blue
            'Disetujui' => '#10b981', // emerald
            'Ditolak' => '#ef4444', // red
            'Sudah Diterima' => '#84cc16', // lime
            'Dibatalkan' => '#9ca3af', // gray-400
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
                    'label' => 'Jumlah Bantuan',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 1,
                    'hoverOffset' => 15,
                ],
            ],
        ];
    }

    // Data berdasarkan jenis bantuan
    protected function getJenisBantuanData($query)
    {
        $data = $query->select('jenis_bansos_id', DB::raw('count(*) as total'))
            ->groupBy('jenis_bansos_id')
            ->orderByDesc('total')
            ->limit(10) // Batasi 10 teratas
            ->with('jenisBansos:id,nama_bansos')
            ->get();

        if ($data->isEmpty()) {
            return $this->getEmptyData();
        }

        $colors = $this->getChartColors();

        $labels = $data->map(function ($item) {
            return $item->jenisBansos ? $item->jenisBansos->nama_bansos : 'Tidak ada data';
        })->toArray();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Jumlah Bantuan',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                    'borderColor' => array_slice($colors, 0, count($data)),
                    'borderWidth' => 1,
                    'hoverOffset' => 15,
                ],
            ],
        ];
    }

    // Data berdasarkan kategori bantuan
    protected function getKategoriBantuanData($query)
    {
        $data = $query->join('jenis_bansos', 'bansos.jenis_bansos_id', '=', 'jenis_bansos.id')
            ->select('jenis_bansos.kategori', DB::raw('count(*) as total'))
            ->groupBy('jenis_bansos.kategori')
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
                    'label' => 'Jumlah Bantuan',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, count($data)),
                    'borderColor' => array_slice($colors, 0, count($data)),
                    'borderWidth' => 1,
                    'hoverOffset' => 15,
                ],
            ],
        ];
    }

    // Data berdasarkan prioritas
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

        // Tambahkan data urgent
        $urgentCount = $query->where('is_urgent', true)->count();

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

        // Cek apakah ada kasus urgent
        $hasUrgent = $urgentCount > 0;

        $labels = $data->pluck('prioritas')->toArray();
        $values = $data->pluck('total')->toArray();

        // Jika ada kasus urgent, tambahkan ke dataset untuk visualisasi terpisah
        if ($hasUrgent) {
            $labels[] = 'Kasus Urgent';
            $values[] = $urgentCount;
            $backgroundColors[] = '#dc2626'; // darker red
            $borderColors[] = '#dc2626';
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Jumlah Bantuan',
                    'data' => $values,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 1,
                    'hoverOffset' => 15,
                ],
            ],
        ];
    }

    // Data berdasarkan sumber pengajuan
    protected function getSumberPengajuanData($query)
    {
        $data = $query->select('sumber_pengajuan', DB::raw('count(*) as total'))
            ->groupBy('sumber_pengajuan')
            ->get();

        if ($data->isEmpty()) {
            return $this->getEmptyData();
        }

        $sumberLabels = [
            'admin' => 'Admin/Petugas Desa',
            'warga' => 'Pengajuan Warga',
        ];

        $sumberColors = [
            'admin' => '#3b82f6', // blue
            'warga' => '#10b981', // emerald
        ];

        $labels = $data->map(function ($item) use ($sumberLabels) {
            return $sumberLabels[$item->sumber_pengajuan] ?? $item->sumber_pengajuan;
        })->toArray();

        $backgroundColors = $data->map(function ($item) use ($sumberColors) {
            return $sumberColors[$item->sumber_pengajuan] ?? '#6b7280';
        })->toArray();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Jumlah Bantuan',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $backgroundColors,
                    'borderWidth' => 1,
                    'hoverOffset' => 15,
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
            'status' => 'Status Bantuan',
            'jenis' => 'Jenis Bantuan',
            'kategori' => 'Kategori Bantuan',
            'prioritas' => 'Prioritas Bantuan',
            'sumber' => 'Sumber Pengajuan Bantuan',
            default => 'Status Bantuan'
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

        // Opsi untuk chart line
        if ($this->chartType === 'line') {
            return array_merge($baseOptions, [
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'stacked' => false,
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
                        'radius' => 3,
                        'hoverRadius' => 5,
                    ],
                ],
                'interaction' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ]);
        }

        // Opsi default
        return $baseOptions;
    }
}