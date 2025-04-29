<?php

namespace App\Filament\Resources\JenisBansosResource\Widgets;

use App\Models\JenisBansos;
use App\Models\Bansos;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class JenisBansosStats extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';

    // Perbaiki property filter
    public ?string $periode = 'semua';
    public ?string $kategoriFilter = null;
    public ?string $dariTanggal = null;
    public ?string $sampaiTanggal = null;

    // Ganti listener lama dengan listener yang sama seperti widget keuangan
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

        // Broadcast event ke chart widget
        $this->dispatch('bansos-filter-changed',
            periode: $this->periode,
            dari_tanggal: $this->dariTanggal,
            sampai_tanggal: $this->sampaiTanggal
        );

        // Refresh widget
        $this->dispatch('$refresh');
    }

    // Perbaiki metode setPeriodeFilter untuk menangani tanggal juga
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

    // Filter untuk kategori bantuan
    public function filterByKategori(?string $kategori): void
    {
        $this->kategoriFilter = $kategori;
    }

    protected function getStats(): array
    {
        // Base query untuk jenis bantuan
        $jenisBansosQuery = JenisBansos::query();
        $bansosQuery = Bansos::query();

        // Terapkan filter periode
        if ($this->periode === 'today') {
            $jenisBansosQuery->whereDate('created_at', today());
            $bansosQuery->whereDate('created_at', today());
        } elseif ($this->periode === 'this_week') {
            $jenisBansosQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            $bansosQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->periode === 'this_month') {
            $jenisBansosQuery->whereMonth('created_at', now()->month)
                             ->whereYear('created_at', now()->year);
            $bansosQuery->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year);
        } elseif ($this->periode === 'this_year') {
            $jenisBansosQuery->whereYear('created_at', now()->year);
            $bansosQuery->whereYear('created_at', now()->year);
        }

        // Terapkan filter kategori jika ada
        if ($this->kategoriFilter) {
            $jenisBansosQuery->where('kategori', $this->kategoriFilter);
            $bansosQuery->whereHas('jenisBansos', function($query) {
                $query->where('kategori', $this->kategoriFilter);
            });
        }

        // Hitung total jenis bantuan
        $totalJenisBansos = $jenisBansosQuery->count();
        $totalJenisBansosAktif = (clone $jenisBansosQuery)->where('is_active', true)->count();
        $persentaseAktif = $totalJenisBansos > 0 ? round(($totalJenisBansosAktif / $totalJenisBansos) * 100) : 0;

        // Total penerima bantuan
        $totalPenerima = $bansosQuery->count();
        $totalDiterima = (clone $bansosQuery)->where('status', 'Sudah Diterima')->count();
        $persentaseDiterima = $totalPenerima > 0 ? round(($totalDiterima / $totalPenerima) * 100) : 0;

        // Kategori bantuan terbanyak
        $kategoriTerbanyak = DB::table('jenis_bansos')
            ->select('kategori', DB::raw('count(*) as total'))
            ->when($this->periode === 'today', fn($q) => $q->whereDate('created_at', today()))
            ->when($this->periode === 'this_week', fn($q) => $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]))
            ->when($this->periode === 'this_month', fn($q) => $q->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year))
            ->when($this->periode === 'this_year', fn($q) => $q->whereYear('created_at', now()->year))
            ->groupBy('kategori')
            ->orderByDesc('total')
            ->first();

        $namaKategoriTerbanyak = $kategoriTerbanyak ? $kategoriTerbanyak->kategori : 'Tidak ada data';
        $jumlahKategoriTerbanyak = $kategoriTerbanyak ? $kategoriTerbanyak->total : 0;

        // Tambahkan periode label untuk deskripsi
        $periodeLabel = match($this->periode) {
            'today' => 'hari ini',
            'this_week' => 'minggu ini',
            'this_month' => 'bulan ini',
            'this_year' => 'tahun ini',
            default => 'semua waktu'
        };

        return [
            Stat::make('Total Jenis Bantuan', number_format($totalJenisBansos, 0, ',', '.'))
                ->description($totalJenisBansosAktif . ' jenis aktif (' . $persentaseAktif . '%)' . ($this->periode !== 'semua' ? ' ' . $periodeLabel : ''))
                ->descriptionIcon('heroicon-m-gift')
                ->color('primary')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'wire:click' => 'filterByKategori(null)',
                ]),

            Stat::make('Total Pengajuan Bantuan', number_format($totalPenerima, 0, ',', '.'))
                ->description(number_format($totalDiterima, 0, ',', '.') . ' sudah diterima (' . $persentaseDiterima . '%)')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([3, 5, 7, 10, 6, 15, 12]),

            Stat::make('Kategori Terbanyak', $namaKategoriTerbanyak)
                ->description($jumlahKategoriTerbanyak . ' jenis bantuan' . ($this->periode !== 'semua' ? ' ' . $periodeLabel : ''))
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('warning')
                ->chart([5, 10, 15, 7, 12, 8, 16])
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'wire:click' => ($namaKategoriTerbanyak !== 'Tidak ada data') ?
                        "filterByKategori('$namaKategoriTerbanyak')" : '',
                ]),
        ];
    }
}