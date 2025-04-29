<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KartuKeluargaResource\Pages;
use App\Models\Penduduk;
use App\Models\ProfilDesa;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Form;
use Filament\Forms\Components\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class KartuKeluargaResource extends Resource
{
    protected static ?string $model = Penduduk::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Kependudukan';

    protected static ?string $navigationLabel = 'Kartu Keluarga';

    protected static ?string $modelLabel = 'Kartu Keluarga';

    protected static ?string $pluralModelLabel = 'Kartu Keluarga';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                // Query untuk mendapatkan penduduk yang kepala keluarga
                Penduduk::query()
                    ->where('kepala_keluarga', true)
                    ->orderBy('kk')
            )
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Kepala Keluarga')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nik')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kk')
                    ->label('Nomor KK')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                    Tables\Columns\TextColumn::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->formatStateUsing(fn (string $state): string => $state === 'L' ? 'Laki-laki' : 'Perempuan')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'L' ? 'info' : 'danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('alamat')
                    ->limit(30)
                    ->tooltip(function (Penduduk $record): string {
                        return $record->alamat;
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('rt_rw')
                    ->label('RT/RW'),
                Tables\Columns\TextColumn::make('desa_kelurahan')
                    ->label('Desa/Kelurahan')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kecamatan')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kabupaten')
                    ->label('Kabupaten/Kota')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Jumlah anggota keluarga (tidak termasuk kepala keluarga)
                Tables\Columns\TextColumn::make('Jumlah Anggota')
                    ->getStateUsing(function (Penduduk $record) {
                        return Penduduk::where('kk', $record->kk)
                            ->where('kepala_keluarga', false)
                            ->count();
                    }),
                Tables\Columns\TextColumn::make('desa.nama_desa')
                    ->label('Desa')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('kk', 'asc')
            ->filters([

                Tables\Filters\SelectFilter::make('jenis_kelamin')
                    ->label('Jenis Kelamin Kepala Keluarga')
                    ->options([
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                    ]),

                Tables\Filters\SelectFilter::make('status_perkawinan')
                    ->label('Status Perkawinan')
                    ->options([
                        'Belum Kawin' => 'Belum Kawin',
                        'Kawin' => 'Kawin',
                        'Cerai Hidup' => 'Cerai Hidup',
                        'Cerai Mati' => 'Cerai Mati',
                    ]),


                Tables\Filters\Filter::make('memiliki_anggota')
                    ->label('Memiliki Anggota')
                    ->query(function (Builder $query): Builder {
                        $kkWithMultipleMembers = Penduduk::query()
                            ->select('kk')
                            ->whereNot('kepala_keluarga', true)
                            ->groupBy('kk')
                            ->havingRaw('COUNT(*) > 0')
                            ->pluck('kk');

                        return $query->whereIn('kk', $kkWithMultipleMembers);
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Penduduk $record) => route('filament.admin.resources.kartu-keluargas.view', ['record' => $record->kk])),

                // Tambahkan action ekspor untuk single KK
                Tables\Actions\Action::make('export')
                    ->label('Ekspor')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->form([
                        Select::make('format')
                            ->label('Format Ekspor')
                            ->options([
                                'pdf' => 'PDF',
                                'excel' => 'Excel',
                            ])
                            ->default('pdf')
                            ->required(),
                    ])
                    ->action(function (Penduduk $record, array $data) {
                        return redirect()->route('kartu-keluarga.export', [
                            'kk' => $record->kk,
                            'format' => $data['format'] ?? 'pdf'
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('exportSelected')
                        ->label('Ekspor Terpilih')
                        ->icon('heroicon-o-document-arrow-up')
                        ->color('success')
                        ->form([
                            Select::make('periode')
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

                            Grid::make(2)
                                ->schema([
                                    DatePicker::make('dari_tanggal')
                                        ->label('Dari Tanggal')
                                        ->visible(fn ($get) => $get('periode') === 'kustom'),

                                    DatePicker::make('sampai_tanggal')
                                        ->label('Sampai Tanggal')
                                        ->visible(fn ($get) => $get('periode') === 'kustom'),
                                ]),

                            // Format dipindahkan ke bawah periode
                            Radio::make('format')
                                ->label('Format Ekspor')
                                ->options([
                                    'pdf' => 'PDF',
                                    'excel' => 'Excel',
                                ])
                                ->default('pdf')
                                ->required()
                                ->inline(),
                        ])
                        ->action(function (Collection $records, array $data) {
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
                                'ids' => $records->pluck('kk')->join(','),
                                'format' => $data['format'] ?? 'pdf',
                            ];

                            // Tambahkan parameter yang tidak null
                            if ($dariTanggal) {
                                $params['dari_tanggal'] = $dariTanggal;
                            }

                            if ($sampaiTanggal) {
                                $params['sampai_tanggal'] = $sampaiTanggal;
                            }

                            return redirect()->route('kartu-keluarga.export.selected', $params);
                        }),
                    // Tambahkan bulk action lain jika diperlukan
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKartuKeluarga::route('/'),
            'view' => Pages\ViewKartuKeluarga::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('kepala_keluarga', true)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}