<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UmkmResource\Pages;
use App\Models\Umkm;
use App\Models\ProfilDesa;
use App\Models\Penduduk;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class UmkmResource extends Resource
{
    protected static ?string $model = Umkm::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Layanan Warga';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'nama_usaha';

    public static function getNavigationLabel(): string
    {
        return 'UMKM Warga';
    }

    public static function getPluralLabel(): string
    {
        return 'UMKM Warga';
    }
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi UMKM')
                    ->schema([
                        Forms\Components\Select::make('id_desa')
                            ->label('Desa')
                            ->options(ProfilDesa::pluck('nama_desa', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('penduduk_id')
                            ->label('Pemilik UMKM')
                            ->options(function () {
                                return Penduduk::query()
                                    ->orderBy('nama')
                                    ->pluck('nama', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('nama_usaha')
                            ->label('Nama Usaha')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('produk')
                            ->label('Produk/Layanan')
                            ->required()
                            ->helperText('Jenis produk atau layanan yang ditawarkan')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('kontak_whatsapp')
                            ->label('Kontak WhatsApp')
                            ->required()
                            ->tel()
                            ->prefixIcon('heroicon-o-phone')
                            ->helperText('Format: 628xxxxxxxxxx (tanpa tanda +)')
                            ->maxLength(15),
                    ]),

                Forms\Components\Section::make('Informasi Tambahan')
                    ->schema([
                        Forms\Components\TextInput::make('lokasi')
                            ->label('Lokasi Usaha')
                            ->helperText('Alamat lengkap tempat usaha')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('deskripsi')
                            ->label('Deskripsi Usaha')
                            ->placeholder('Deskripsi singkat tentang usaha ini')
                            ->rows(3),

                        Forms\Components\Select::make('kategori')
                            ->label('Kategori Usaha')
                            ->options([
                                'Kuliner' => 'Kuliner',
                                'Kerajinan' => 'Kerajinan',
                                'Fashion' => 'Fashion',
                                'Pertanian' => 'Pertanian',
                                'Jasa' => 'Jasa',
                                'Lainnya' => 'Lainnya',
                            ]),

                        Forms\Components\Toggle::make('is_verified')
                            ->label('Terverifikasi')
                            ->helperText('UMKM ini telah diverifikasi oleh admin desa')
                            ->default(false)
                            ,

                        Forms\Components\FileUpload::make('foto_usaha')
                            ->label('Foto Usaha')
                            ->image()
                            ->directory('umkm')
                            ->maxSize(2048)
                            ->helperText('Maks. 2MB. Format: JPG, PNG'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_usaha')
                    ->label('Nama Usaha')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('penduduk.nama')
                    ->label('Pemilik')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('produk')
                    ->label('Produk/Layanan')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('kategori')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'Kuliner' => 'success',
                        'Kerajinan' => 'info',
                        'Fashion' => 'warning',
                        'Pertanian' => 'primary',
                        'Jasa' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Terverifikasi')
                    ->boolean()
                    ->alignment('center')
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->alignment('center')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('kontak_whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-m-phone')
                    ->copyable()
                    ->formatStateUsing(fn (string $state): string =>
                        substr($state, 0, 3) . '-' . substr($state, 3, 4) . '-' . substr($state, 7)
                    ),

                Tables\Columns\TextColumn::make('desa.nama_desa')
                    ->label('Desa')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('id_desa')
                    ->label('Desa')
                    ->options(ProfilDesa::pluck('nama_desa', 'id')),

                Tables\Filters\SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->options([
                        'Kuliner' => 'Kuliner',
                        'Kerajinan' => 'Kerajinan',
                        'Fashion' => 'Fashion',
                        'Pertanian' => 'Pertanian',
                        'Jasa' => 'Jasa',
                        'Lainnya' => 'Lainnya',
                    ]),

                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Status Verifikasi')
                    ->trueLabel('Terverifikasi')
                    ->falseLabel('Belum Terverifikasi')
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_verified', true),
                        false: fn (Builder $query) => $query->where('is_verified', false),
                        blank: fn (Builder $query) => $query,
                    ),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('verify')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Umkm $record) => !$record->is_verified)
                    ->action(fn (Umkm $record) => $record->update(['is_verified' => true])),

                Tables\Actions\Action::make('whatsapp')
                    ->label('WA')
                    ->icon('heroicon-o-phone')
                    ->color('success')
                    ->url(fn (Umkm $record) => "https://wa.me/{$record->kontak_whatsapp}", true),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make(),

                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('verifyBulk')
                        ->label('Verifikasi Semua')
                        ->icon('heroicon-o-check-badge')
                        ->action(fn ($records) => $records->each->update(['is_verified' => true]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\RestoreBulkAction::make(),

                    Tables\Actions\BulkAction::make('exportSelected')
                        ->label('Ekspor Terpilih')
                        ->icon('heroicon-o-document-arrow-up')
                        ->color('success')
                        ->form([
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
                        ->action(function (Collection $records, array $data) {
                            $dariTanggal = null;
                            $sampaiTanggal = null;

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

                            $params = [
                                'ids' => $records->pluck('id')->join(','),
                                'format' => $data['format'] ?? 'pdf',
                            ];

                            if ($dariTanggal) {
                                $params['dari_tanggal'] = $dariTanggal;
                            }

                            if ($sampaiTanggal) {
                                $params['sampai_tanggal'] = $sampaiTanggal;
                            }

                            return redirect()->route('umkm.export.selected', $params);
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi UMKM')
                    ->schema([
                        Infolists\Components\TextEntry::make('nama_usaha')
                            ->label('Nama Usaha')
                            ->weight(FontWeight::Bold)
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('penduduk.nama')
                                    ->label('Pemilik')
                                    ->icon('heroicon-o-user'),

                                Infolists\Components\TextEntry::make('kontak_whatsapp')
                                    ->label('Kontak WhatsApp')
                                    ->icon('heroicon-o-phone')
                                    ->copyable()
                                    ->formatStateUsing(fn (string $state): string =>
                                        substr($state, 0, 3) . '-' . substr($state, 3, 4) . '-' . substr($state, 7)
                                    )
                                    ->url(fn (Umkm $record) => "https://wa.me/{$record->kontak_whatsapp}", true),

                                Infolists\Components\IconEntry::make('is_verified')
                                    ->label('Terverifikasi')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-badge')
                                    ->falseIcon('heroicon-o-x-mark')
                                    ->trueColor('success')
                                    ->falseColor('gray'),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('kategori')
                                    ->label('Kategori')
                                    ->badge()
                                    ->icon('heroicon-o-tag')
                                    ->color(fn (string $state): string => match($state) {
                                        'Kuliner' => 'success',
                                        'Kerajinan' => 'info',
                                        'Fashion' => 'warning',
                                        'Pertanian' => 'primary',
                                        'Jasa' => 'danger',
                                        default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('produk')
                                    ->label('Produk/Layanan')
                                    ->icon('heroicon-o-shopping-bag'),

                                Infolists\Components\TextEntry::make('desa.nama_desa')
                                    ->label('Desa')
                                    ->icon('heroicon-o-home-modern'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Informasi Tambahan')
                    ->schema([
                        Infolists\Components\TextEntry::make('lokasi')
                            ->label('Lokasi Usaha')
                            ->icon('heroicon-o-map-pin'),

                        Infolists\Components\TextEntry::make('deskripsi')
                            ->label('Deskripsi Usaha')
                            ->markdown(),

                        Infolists\Components\ImageEntry::make('foto_usaha')
                            ->label('Foto Usaha')
                            ->visible(fn (Umkm $record) => filled($record->foto_usaha)),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Terdaftar Pada')
                                    ->icon('heroicon-o-calendar')
                                    ->dateTime('d M Y, H:i'),

                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Terakhir Diperbarui')
                                    ->icon('heroicon-o-clock')
                                    ->dateTime('d M Y, H:i'),
                            ]),
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
            'index' => Pages\ListUmkm::route('/'),
            'create' => Pages\CreateUmkm::route('/create'),
            'view' => Pages\ViewUmkm::route('/{record}'),
            'edit' => Pages\EditUmkm::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getWidgets(): array
    {
        return [
            UmkmResource\Widgets\UmkmStats::class,
        ];
    }
}