<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BatasWilayahPotensiResource\Pages;
use App\Models\BatasWilayahPotensi;
use App\Models\ProfilDesa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Card;

class BatasWilayahPotensiResource extends Resource
{
    protected static ?string $model = BatasWilayahPotensi::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationParentItem = 'Profil Desa';

    protected static ?string $navigationGroup = 'Desa';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Batas & Potensi Wilayah';
    }

    public static function getPluralLabel(): string
    {
        return 'Batas & Potensi Wilayah';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('profil_desa_id')
                    ->label('Desa')
                    ->options(ProfilDesa::pluck('nama_desa', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => Auth::id()),

                Forms\Components\Tabs::make('Tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Wilayah & Batas')
                            ->icon('heroicon-o-map')
                            ->schema([
                                Card::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('luas_wilayah')
                                            ->label('Luas Wilayah (m²)')
                                            ->numeric()
                                            ->suffix('m²')
                                            ->helperText('Masukkan luas wilayah dalam meter persegi (m²)')
                                            ->columnSpanFull(),
                                    ]),

                                Card::make()
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('batas_utara')
                                            ->label('Batas Utara')
                                            ->prefixIcon('heroicon-o-arrow-up')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('batas_selatan')
                                            ->label('Batas Selatan')
                                            ->prefixIcon('heroicon-o-arrow-down')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('batas_timur')
                                            ->label('Batas Timur')
                                            ->prefixIcon('heroicon-o-arrow-right')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('batas_barat')
                                            ->label('Batas Barat')
                                            ->prefixIcon('heroicon-o-arrow-left')
                                            ->maxLength(255),
                                    ]),

                                Forms\Components\Textarea::make('keterangan_batas')
                                    ->label('Keterangan Tambahan')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Potensi Desa')
                            ->icon('heroicon-o-light-bulb')
                            ->schema([
                                Repeater::make('potensi_desa')
                                    ->label('Potensi Desa')
                                    ->schema([
                                        Forms\Components\TextInput::make('nama')
                                            ->label('Nama Potensi')
                                            ->required(),
                                        Forms\Components\Select::make('kategori')
                                            ->label('Kategori')
                                            ->options([
                                                'sda' => 'Sumber Daya Alam',
                                                'pertanian' => 'Pertanian',
                                                'peternakan' => 'Peternakan',
                                                'pariwisata' => 'Pariwisata',
                                                'industri' => 'Industri/UMKM',
                                                'budaya' => 'Budaya/Kesenian',
                                                'lingkungan' => 'Lingkungan',
                                                'pendidikan' => 'Pendidikan',
                                                'kesehatan' => 'Kesehatan',
                                                'lainnya' => 'Lainnya',
                                            ])
                                            ->searchable()
                                            ->required(),
                                        Forms\Components\TextInput::make('lokasi')
                                            ->label('Lokasi'),
                                        Forms\Components\TextInput::make('deskripsi')
                                            ->label('Deskripsi')
                                            ->columnSpanFull(),
                                    ])
                                    ->itemLabel(fn (array $state): ?string => ($state['nama'] ?? 'Potensi Baru') . ' - ' . ($state['kategori'] ?? 'Kategori Belum Dipilih'))
                                    ->defaultItems(0)
                                    ->reorderable()
                                    ->collapsible()
                                    ->collapsed(false)
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('keterangan_potensi')
                                    ->label('Keterangan Tambahan')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('profilDesa.nama_desa')
                    ->label('Desa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('luas_wilayah')
                    ->label('Luas Wilayah')
                    ->formatStateUsing(fn ($record) => $record ? $record->getLuasWilayahFormatted() : '-'),
                Tables\Columns\TextColumn::make('batas_utara')
                    ->label('Batas Utara')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('potensi_desa_count')
                    ->label('Jumlah Potensi')
                    ->state(function ($record) {
                        if (!$record->potensi_desa || !is_array($record->potensi_desa)) {
                            return 0;
                        }
                        return count($record->potensi_desa);
                    })
                    ->suffix(' item'),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Dibuat Oleh'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('kategori_potensi')
                    ->label('Kategori Potensi')
                    ->options([
                        'sda' => 'Sumber Daya Alam',
                        'pertanian' => 'Pertanian',
                        'peternakan' => 'Peternakan',
                        'pariwisata' => 'Pariwisata',
                        'industri' => 'Industri/UMKM',
                        'budaya' => 'Budaya/Kesenian',
                        'lingkungan' => 'Lingkungan',
                        'pendidikan' => 'Pendidikan',
                        'kesehatan' => 'Kesehatan',
                        'lainnya' => 'Lainnya',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function (Builder $query, $kategori) {
                            return $query->whereJsonContains('potensi_desa', [['kategori' => $kategori]]);
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make()
                    ->visible(fn ($record) => $record->trashed()),
                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn ($record) => $record->trashed()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
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
            'index' => Pages\ListBatasWilayahPotensis::route('/'),
            'create' => Pages\CreateBatasWilayahPotensi::route('/create'),
            'view' => Pages\ViewBatasWilayahPotensi::route('/{record}'),
            'edit' => Pages\EditBatasWilayahPotensi::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}