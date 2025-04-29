<?php

namespace App\Filament\Resources\KartuKeluargaResource\Pages;

use App\Filament\Resources\KartuKeluargaResource;
use App\Models\Penduduk;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Actions;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\HtmlString;
use Filament\Forms;

class ViewKartuKeluarga extends ViewRecord
{
    protected static string $resource = KartuKeluargaResource::class;

    public ?string $nomor_kk = null;
    public ?Penduduk $kepalaKeluarga = null;

    // Override untuk menggunakan nomor KK sebagai record
    public function mount(string|int $record): void
    {
        $this->nomor_kk = (string) $record;
        $this->kepalaKeluarga = Penduduk::where('kk', $this->nomor_kk)
            ->where('kepala_keluarga', true)
            ->first();

        if (!$this->kepalaKeluarga) {
            $this->redirect(static::getResource()::getUrl('index'));
            return;
        }

        // Set record sebagai kepala keluarga
        $this->record = $this->kepalaKeluarga;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Informasi Kartu Keluarga')
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        Components\TextEntry::make('kk')
                            ->label('Nomor KK')
                            ->weight(FontWeight::Bold)
                            ->size(Components\TextEntry\TextEntrySize::Large)
                            ->copyable()
                            ->icon('heroicon-o-document-text'),

                        Components\TextEntry::make('Jumlah Anggota')
                            ->state(function () {
                                return Penduduk::where('kk', $this->nomor_kk)
                                    ->where('kepala_keluarga', false)
                                    ->count() . ' orang';
                            })
                            ->icon('heroicon-o-users'),

                        Components\TextEntry::make('nama')
                            ->label('Kepala Keluarga')
                            ->weight(FontWeight::Bold)
                            ->icon('heroicon-o-user-circle'),

                        Components\TextEntry::make('nik')
                            ->label('NIK Kepala Keluarga')
                            ->copyable()
                            ->icon('heroicon-o-identification'),
                    ]),

                Components\Section::make('Alamat')
                    ->icon('heroicon-o-home')
                    ->schema([
                        Components\Grid::make(4)
                            ->schema([
                                Components\TextEntry::make('rt_rw')
                                    ->label('RT/RW')
                                    ->icon('heroicon-o-home-modern'),

                                Components\TextEntry::make('desa_kelurahan')
                                    ->label('Desa/Kelurahan')
                                    ->icon('heroicon-o-building-office-2'),

                                Components\TextEntry::make('kecamatan')
                                    ->icon('heroicon-o-building-office'),

                                Components\TextEntry::make('kabupaten')
                                    ->icon('heroicon-o-building-library'),
                            ]),

                        Components\TextEntry::make('alamat')
                            ->columnSpanFull()
                            ->icon('heroicon-o-map'),
                    ]),

                Components\Section::make('Anggota Keluarga')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Components\ViewEntry::make('anggota_keluarga')
                            ->label(false)
                            ->view('filament.resources.kartu-keluarga-resource.anggota-table')
                            ->state(function () {
                                return [
                                    'anggota' => Penduduk::where('kk', $this->nomor_kk)
                                        ->where('kepala_keluarga', false)
                                        ->orderBy('tanggal_lahir')
                                        ->get(),
                                    'nomor_kk' => $this->nomor_kk
                                ];
                            }),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('tambah_anggota')
                ->label('Tambah Anggota')
                ->icon('heroicon-o-user-plus')
                ->url(fn () => route('filament.admin.resources.penduduks.create', [
                    'kk' => $this->nomor_kk,
                    'kepala_keluarga_id' => $this->kepalaKeluarga->id
                ])),

            Actions\Action::make('export')
                ->label('Ekspor')
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('format')
                        ->label('Format Ekspor')
                        ->options([
                            'pdf' => 'PDF',
                            'excel' => 'Excel',
                        ])
                        ->default('pdf')
                        ->required(),
                ])
                ->action(function (array $data) {
                    return redirect()->route('kartu-keluarga.export', [
                        'kk' => $this->nomor_kk,
                        'format' => $data['format'] ?? 'pdf'
                    ]);
                }),
        ];
    }
}