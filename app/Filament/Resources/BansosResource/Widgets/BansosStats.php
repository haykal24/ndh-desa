<?php

namespace App\Filament\Resources\BansosResource\Widgets;

use App\Models\Bansos;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Carbon\Carbon;

class BansosStats extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';

    // Properti untuk filter
    public ?string $periode = 'semua';
    public ?string $dariTanggal = null;
    public ?string $sampaiTanggal = null;

    // Ganti nama metode dan tambahkan parameter sesuai standar
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

        // Refresh widget
        $this->dispatch('$refresh');
    }

    // Perbaiki setPeriodeFilter untuk menangani tanggal
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
        // Query dasar
        $query = Bansos::query();

        // Terapkan filter periode
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

        // Hitung total per status
        $totalPerStatus = $query->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Total pengajuan bantuan
        $totalPengajuan = array_sum($totalPerStatus);

        // Total bantuan sudah diterima
        $totalDiterima = $totalPerStatus['Sudah Diterima'] ?? 0;

        // Total bantuan ditolak
        $totalDitolak = $totalPerStatus['Ditolak'] ?? 0;

        // Hitung berdasarkan prioritas
        $prioritas = (clone $query)->select('prioritas', DB::raw('count(*) as total'))
            ->groupBy('prioritas')
            ->pluck('total', 'prioritas')
            ->toArray();

        // Hitung berdasarkan sumber pengajuan
        $sumberPengajuan = (clone $query)->select('sumber_pengajuan', DB::raw('count(*) as total'))
            ->groupBy('sumber_pengajuan')
            ->pluck('total', 'sumber_pengajuan')
            ->toArray();

        // Hitung bantuan yang ditandai urgent
        $totalUrgent = (clone $query)->where('is_urgent', true)->count();

        // Persentase persetujuan (disetujui & diterima / total pengajuan)
        $persentasePersetujuan = $totalPengajuan > 0
            ? round((($totalPerStatus['Disetujui'] ?? 0) + $totalDiterima) / $totalPengajuan * 100)
            : 0;

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
            Stat::make('Total Pengajuan', number_format($totalPengajuan, 0, ',', '.'))
                ->description('Seluruh pengajuan bantuan' . ($this->periode !== 'semua' ? ' ' . $periodeLabel : ''))
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('Menunggu Proses', number_format(($totalPerStatus['Diajukan'] ?? 0) + ($totalPerStatus['Diverifikasi'] ?? 0) + ($totalPerStatus['Dalam Verifikasi'] ?? 0), 0, ',', '.'))
                ->description('Pengajuan sedang diproses')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->chart([3, 5, 7, 10, 6, 15, 12]),

            Stat::make('Sudah Diterima', number_format($totalDiterima, 0, ',', '.'))
                ->description('Bantuan telah disalurkan')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([5, 10, 15, 7, 12, 8, 16]),

            Stat::make('Ditolak', number_format($totalDitolak, 0, ',', '.'))
                ->description('Pengajuan ditolak')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->chart([2, 4, 3, 7, 5, 6, 4]),

            Stat::make('Prioritas Tinggi', number_format($prioritas['Tinggi'] ?? 0, 0, ',', '.'))
                ->description('Termasuk ' . number_format($totalUrgent, 0, ',', '.') . ' kasus urgent')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->chart([10, 15, 8, 12, 9, 14, 7]),

            Stat::make('Persentase Persetujuan', $persentasePersetujuan . '%')
                ->description('Tingkat persetujuan bantuan')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info')
                ->chart([6, 8, 7, 9, 10, 11, 12]),
        ];
    }
}