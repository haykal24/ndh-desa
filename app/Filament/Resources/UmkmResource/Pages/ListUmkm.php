<?php

namespace App\Filament\Resources\UmkmResource\Pages;

use App\Filament\Resources\UmkmResource;
use App\Filament\Resources\UmkmResource\Widgets\UmkmStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Carbon\Carbon;

class ListUmkm extends ListRecords
{
    protected static string $resource = UmkmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->icon('heroicon-o-plus')
            ->label('Tambah UMKM'),

            // Tambahkan action ekspor semua
            Actions\Action::make('exportAll')
                ->label('Ekspor Semua')
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('kategori')
                        ->label('Kategori')
                        ->options([
                            '' => 'Semua Kategori',
                            'Kuliner' => 'Kuliner',
                            'Kerajinan' => 'Kerajinan',
                            'Fashion' => 'Fashion',
                            'Pertanian' => 'Pertanian',
                            'Jasa' => 'Jasa',
                            'Lainnya' => 'Lainnya',
                        ])
                        ->default(''),

                    Forms\Components\Select::make('is_verified')
                        ->label('Status Verifikasi')
                        ->options([
                            '' => 'Semua Status',
                            '1' => 'Terverifikasi',
                            '0' => 'Belum Terverifikasi',
                        ])
                        ->default(''),

                    Forms\Components\Select::make('periode')
                        ->label('Periode Data')
                        ->options([
                            'semua' => 'Semua Waktu',
                            'hari_ini' => 'Hari Ini',
                            'minggu_ini' => 'Minggu Ini',
                            'bulan_ini' => 'Bulan Ini',
                            'tahun_ini' => 'Tahun Ini',
                            'bulan_lalu' => 'Bulan Lalu',
                            'tahun_lalu' => 'Tahun Lalu',
                            'kustom' => 'Kustom (Pilih Tanggal)',
                        ])
                        ->default('semua')
                        ->live()
                        ->afterStateUpdated(function($state, callable $set) {
                            if ($state !== 'kustom') {
                                $set('dari_tanggal', null);
                                $set('sampai_tanggal', null);
                            }
                        }),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\DatePicker::make('dari_tanggal')
                                ->label('Dari Tanggal')
                                ->visible(fn ($get) => $get('periode') === 'kustom'),

                            Forms\Components\DatePicker::make('sampai_tanggal')
                                ->label('Sampai Tanggal')
                                ->visible(fn ($get) => $get('periode') === 'kustom'),
                        ]),

                    Forms\Components\Radio::make('format')
                        ->label('Format Ekspor')
                        ->options([
                            'pdf' => 'PDF',
                            'excel' => 'Excel',
                        ])
                        ->default('pdf')
                        ->required()
                        ->inline(),
                ])
                ->action(function (array $data) {
                    // Proses periode ke tanggal
                    $dariTanggal = null;
                    $sampaiTanggal = null;

                    // Konversi periode ke tanggal
                    if ($data['periode'] === 'hari_ini') {
                        $dariTanggal = Carbon::today()->format('Y-m-d');
                        $sampaiTanggal = Carbon::today()->format('Y-m-d');
                    }
                    elseif ($data['periode'] === 'minggu_ini') {
                        $dariTanggal = Carbon::today()->startOfWeek()->format('Y-m-d');
                        $sampaiTanggal = Carbon::today()->endOfWeek()->format('Y-m-d');
                    }
                    elseif ($data['periode'] === 'bulan_ini') {
                        $dariTanggal = Carbon::today()->startOfMonth()->format('Y-m-d');
                        $sampaiTanggal = Carbon::today()->endOfMonth()->format('Y-m-d');
                    }
                    elseif ($data['periode'] === 'tahun_ini') {
                        $dariTanggal = Carbon::today()->startOfYear()->format('Y-m-d');
                        $sampaiTanggal = Carbon::today()->endOfYear()->format('Y-m-d');
                    }
                    elseif ($data['periode'] === 'bulan_lalu') {
                        $dariTanggal = Carbon::today()->subMonth()->startOfMonth()->format('Y-m-d');
                        $sampaiTanggal = Carbon::today()->subMonth()->endOfMonth()->format('Y-m-d');
                    }
                    elseif ($data['periode'] === 'tahun_lalu') {
                        $dariTanggal = Carbon::today()->subYear()->startOfYear()->format('Y-m-d');
                        $sampaiTanggal = Carbon::today()->subYear()->endOfYear()->format('Y-m-d');
                    }
                    elseif ($data['periode'] === 'kustom') {
                        $dariTanggal = isset($data['dari_tanggal']) ? $data['dari_tanggal']->format('Y-m-d') : null;
                        $sampaiTanggal = isset($data['sampai_tanggal']) ? $data['sampai_tanggal']->format('Y-m-d') : null;
                    }

                    // Buat array parameter
                    $params = [
                        'format' => $data['format'] ?? 'pdf',
                    ];

                    // Tambahkan parameter kategori jika dipilih
                    if (!empty($data['kategori'])) {
                        $params['kategori'] = $data['kategori'];
                    }

                    // Tambahkan parameter status verifikasi jika dipilih
                    if ($data['is_verified'] !== '') {
                        $params['is_verified'] = $data['is_verified'];
                    }

                    // Tambahkan parameter yang tidak null
                    if ($dariTanggal) {
                        $params['dari_tanggal'] = $dariTanggal;
                    }

                    if ($sampaiTanggal) {
                        $params['sampai_tanggal'] = $sampaiTanggal;
                    }

                    return redirect()->route('umkm.export.all', $params);
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UmkmStats::class,
        ];
    }
}