<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BeritaResource\Pages;
use App\Models\Berita;
use App\Models\ProfilDesa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use App\Models\User;
use Illuminate\Support\Collection;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Split;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;

class BeritaResource extends Resource
{
    protected static ?string $model = Berita::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Desa';

    protected static ?string $recordTitleAttribute = 'judul';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Berita';

    protected static ?string $pluralModelLabel = 'Berita';

    public static function getNavigationLabel(): string
    {
        return 'Berita';
    }

    // Definisi kategori berita
    public static function getKategoriBerita(): array
    {
        return [
            'Umum' => 'Umum',
            'Pengumuman' => 'Pengumuman',
            'Kegiatan' => 'Kegiatan',
            'Infrastruktur' => 'Infrastruktur',
            'Kesehatan' => 'Kesehatan',
            'Pendidikan' => 'Pendidikan',
        ];
    }

    // Warna kategori
    public static function getKategoriColors(): array
    {
        return [
            'Umum' => 'primary',
            'Pengumuman' => 'warning',
            'Kegiatan' => 'success',
            'Infrastruktur' => 'danger',
            'Kesehatan' => 'info',
            'Pendidikan' => 'secondary',
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Berita')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('id_desa')
                                    ->label('Desa')
                                    ->options(ProfilDesa::pluck('nama_desa', 'id'))
                                    ->required(),
                                Forms\Components\Select::make('kategori')
                                    ->options(self::getKategoriBerita())
                                    ->required(),
                            ]),
                        Forms\Components\TextInput::make('judul')
                            ->required()
                            ->maxLength(200),
                        Forms\Components\FileUpload::make('gambar')
                            ->label('Gambar/Thumbnail')
                            ->image()
                            ->imageCropAspectRatio('16:9')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('675')
                            ->directory('uploads/berita')
                            ->required(),
                        Forms\Components\RichEditor::make('isi')
                            ->required()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('uploads/berita/content')
                            ->columnSpanFull(),
                        // Created by akan diisi otomatis
                        Forms\Components\Hidden::make('created_by')
                            ->default(fn () => auth()->id()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('gambar')
                    ->label('Thumbnail')
                    ->square(),
                Tables\Columns\TextColumn::make('judul')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('kategori')
                    ->badge()
                    ->colors(self::getKategoriColors()),
                Tables\Columns\TextColumn::make('desa.nama_desa')
                    ->label('Desa')
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Penulis')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                // Pertahankan hanya filter penting

                // Filter kategori
                SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->options(self::getKategoriBerita())
                    ->placeholder('Semua Kategori')
                    ->multiple(),

                // Sederhanakan filter tanggal
                Filter::make('created_at')
                    ->form([
                        Forms\Components\Select::make('preset_periode')
                            ->label('Periode')
                            ->options([
                                'today' => 'Hari Ini',
                                'yesterday' => 'Kemarin',
                                'last7days' => '7 Hari Terakhir',
                                'last30days' => '30 Hari Terakhir',
                                'thismonth' => 'Bulan Ini',
                                'lastmonth' => 'Bulan Lalu',
                                'thisyear' => 'Tahun Ini',
                                'custom' => 'Kustom (Pilih Tanggal)',
                            ])
                            ->placeholder('Pilih periode...')
                            ->live()
                            ->afterStateUpdated(function (callable $get, callable $set, ?string $state) {
                                if (!$state || $state === 'custom') {
                                    return;
                                }

                                if ($state === 'today') {
                                    $set('created_from', today());
                                    $set('created_until', today());
                                } elseif ($state === 'yesterday') {
                                    $set('created_from', today()->subDay());
                                    $set('created_until', today()->subDay());
                                } elseif ($state === 'last7days') {
                                    $set('created_from', today()->subDays(6));
                                    $set('created_until', today());
                                } elseif ($state === 'last30days') {
                                    $set('created_from', today()->subDays(29));
                                    $set('created_until', today());
                                } elseif ($state === 'thismonth') {
                                    $set('created_from', today()->startOfMonth());
                                    $set('created_until', today()->endOfMonth());
                                } elseif ($state === 'lastmonth') {
                                    $set('created_from', today()->subMonth()->startOfMonth());
                                    $set('created_until', today()->subMonth()->endOfMonth());
                                } elseif ($state === 'thisyear') {
                                    $set('created_from', today()->startOfYear());
                                    $set('created_until', today()->endOfYear());
                                }
                            }),
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Dari Tanggal')
                            ->visible(fn ($get) => $get('preset_periode') === 'custom'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Sampai Tanggal')
                            ->visible(fn ($get) => $get('preset_periode') === 'custom'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // Jangan terapkan filter jika tidak ada data yang diisi
                        if (empty($data['created_from']) && empty($data['created_until']) && empty($data['preset_periode'])) {
                            return $query;
                        }

                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        // Tampilkan preset sebagai indikator
                        if (isset($data['preset_periode']) && $data['preset_periode'] !== 'custom' && $data['preset_periode'] !== '') {
                            $periodNames = [
                                'today' => 'Hari Ini',
                                'yesterday' => 'Kemarin',
                                'last7days' => '7 Hari Terakhir',
                                'last30days' => '30 Hari Terakhir',
                                'thismonth' => 'Bulan Ini',
                                'lastmonth' => 'Bulan Lalu',
                                'thisyear' => 'Tahun Ini',
                            ];

                            if (isset($periodNames[$data['preset_periode']])) {
                                $indicators['periode'] = 'Periode: ' . $periodNames[$data['preset_periode']];
                                return $indicators;
                            }
                        }

                        // Tampilkan rentang tanggal kustom
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Dari: ' . Carbon::parse($data['created_from'])->format('d/m/Y');
                        }

                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Sampai: ' . Carbon::parse($data['created_until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),

                // Filter sampah tetap ada untuk keperluan administrasi
                Tables\Filters\TrashedFilter::make()
                    ->label('Item Terhapus'),
            ])
            ->filtersFormColumns(2) // Kurangi jumlah kolom filter menjadi 2
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

                // Tambahkan action untuk restore dan force delete individual
                Tables\Actions\RestoreAction::make()
                    ->label('Pulihkan')
                    ->tooltip('Mengembalikan item yang telah dihapus'),

                Tables\Actions\ForceDeleteAction::make()
                    ->label('Hapus Permanen')
                    ->tooltip('Menghapus item secara permanen')
                    ->color('danger'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    // Ini sudah ada tapi kita perjelas labelnya
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->label('Hapus Permanen Massal'),

                    Tables\Actions\RestoreBulkAction::make()
                        ->label('Pulihkan Massal')
                        ->icon('heroicon-o-arrow-path'),

                    // Pertahankan Bulk Action untuk update kategori
                    Tables\Actions\BulkAction::make('updateCategory')
                        ->label('Update Kategori')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Forms\Components\Select::make('kategori')
                                ->label('Kategori')
                                ->options(self::getKategoriBerita())
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                $record->update([
                                    'kategori' => $data['kategori'],
                                ]);
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListBerita::route('/'),
            'create' => Pages\CreateBerita::route('/create'),
            'view' => Pages\ViewBerita::route('/{record}'),
            'edit' => Pages\EditBerita::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Bagian Utama - Judul dan Gambar
                \Filament\Infolists\Components\Section::make()
                    ->schema([
                        \Filament\Infolists\Components\ImageEntry::make('gambar')
                            ->hiddenLabel()
                            ->extraAttributes(['class' => 'rounded-lg']),

                        \Filament\Infolists\Components\TextEntry::make('judul')
                            ->hiddenLabel()
                            ->weight(\Filament\Support\Enums\FontWeight::Bold)
                            ->size(\Filament\Infolists\Components\TextEntry\TextEntrySize::Large),

                        \Filament\Infolists\Components\TextEntry::make('kategori')
                            ->badge()
                            ->icon('heroicon-o-tag')
                            ->color(fn (string $state): string => self::getKategoriColors()[$state] ?? 'gray'),
                    ])
                    ->columns(1),

                // Metadata Berita
                \Filament\Infolists\Components\Section::make('Informasi Berita')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('creator.name')
                            ->label('Penulis')
                            ->icon('heroicon-o-user'),

                        \Filament\Infolists\Components\TextEntry::make('desa.nama_desa')
                            ->label('Desa')
                            ->icon('heroicon-o-home'),

                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('Tanggal Publikasi')
                            ->dateTime('d M Y H:i')
                            ->icon('heroicon-o-calendar'),

                        \Filament\Infolists\Components\TextEntry::make('updated_at')
                            ->label('Terakhir Diperbarui')
                            ->dateTime('d M Y H:i')
                            ->icon('heroicon-o-clock'),
                    ])
                    ->columns(2)
                    ->collapsed(false)
                    ->collapsible(),

                // Konten Berita
                \Filament\Infolists\Components\Section::make('Konten')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('isi')
                            ->hiddenLabel()
                            ->html(),
                    ]),
            ]);
    }
}