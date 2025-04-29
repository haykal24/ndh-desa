<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AparatDesaResource\Pages;
use App\Models\AparatDesa;
use App\Models\StrukturPemerintahan;
use App\Models\ProfilDesa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Card;

class AparatDesaResource extends Resource
{
    protected static ?string $model = AparatDesa::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationParentItem = 'Profil Desa';

    protected static ?string $navigationGroup = 'Desa';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return 'Aparat Desa';
    }

    public static function getPluralLabel(): string
    {
        return 'Aparat Desa';
    }

    public static function form(Form $form): Form
    {
        // Coba dapatkan struktur pemerintahan default (desa pertama)
        $defaultStruktur = StrukturPemerintahan::with('profilDesa')
            ->orderBy('id', 'asc')
            ->first()?->id;
            
        return $form
            ->schema([
                Forms\Components\Select::make('struktur_pemerintahan_id')
                    ->label('Desa')
                    ->options(function () {
                        return StrukturPemerintahan::with('profilDesa')
                            ->get()
                            ->mapWithKeys(function ($item) {
                                return [$item->id => ($item->profilDesa->nama_desa ?? 'Desa tidak diketahui') . ' - ' . 
                                       ($item->nama_kepala_desa ? 'Kades: ' . $item->nama_kepala_desa : 'Tanpa kepala desa')];
                            });
                    })
                    ->searchable()
                    ->required()
                    ->preload()
                    ->default($defaultStruktur)
                    ->placeholder('Pilih desa tempat aparat bertugas'),

                Card::make()
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('nama')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Masukkan nama lengkap aparat desa'),
                            
                        Forms\Components\TextInput::make('jabatan')
                            ->label('Jabatan')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Sekretaris Desa, Kepala Dusun, dll'),
                            
                        Forms\Components\FileUpload::make('foto')
                            ->label('Foto')
                            ->image()
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('300')
                            ->imageResizeTargetHeight('300')
                            ->directory('uploads/desa/aparat')
                            ->helperText('Upload foto berwarna dengan latar belakang polos. Format persegi (1:1).')
                            ->columnSpan(2),
                            
                        Forms\Components\TextInput::make('pendidikan')
                            ->label('Pendidikan Terakhir')
                            ->maxLength(255)
                            ->placeholder('Contoh: S1, SMA, D3, dll'),
                            
                        Forms\Components\DatePicker::make('tanggal_lahir')
                            ->label('Tanggal Lahir')
                            ->placeholder('DD/MM/YYYY')
                            ->displayFormat('d/m/Y')
                            ->maxDate(now()->subYears(17)),
                            
                        Forms\Components\TextInput::make('alamat')
                            ->label('Alamat')
                            ->maxLength(255)
                            ->placeholder('Alamat lengkap tempat tinggal aparat desa')
                            ->columnSpan(2),
                            
                        Forms\Components\TextInput::make('kontak')
                            ->label('Kontak/Telepon')
                            ->tel()
                            ->maxLength(100)
                            ->placeholder('Nomor HP/Telepon yang dapat dihubungi'),
                            
                        Forms\Components\TextInput::make('periode_jabatan')
                            ->label('Periode Jabatan')
                            ->maxLength(100)
                            ->placeholder('Contoh: 2020-2025'),
                            
                        Forms\Components\TextInput::make('urutan')
                            ->label('Urutan Tampilan')
                            ->numeric()
                            ->default(function () {
                                // Coba tentukan urutan default berdasarkan jumlah aparat yang sudah ada
                                $lastOrder = AparatDesa::max('urutan');
                                return $lastOrder ? $lastOrder + 1 : 1;
                            })
                            ->placeholder('Angka urutan')
                            ->helperText('Urutan tampilan (angka kecil tampil lebih dulu. Sekretaris Desa = 2, Kaur = 3-7, Kadus = 10+)'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('strukturPemerintahan.profilDesa.nama_desa')
                    ->label('Desa')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\ImageColumn::make('foto')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),
                    
                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('jabatan')
                    ->label('Jabatan')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('pendidikan')
                    ->label('Pendidikan')
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('periode_jabatan')
                    ->label('Periode')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('urutan')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('struktur_pemerintahan_id')
                    ->label('Desa')
                    ->options(function () {
                        return StrukturPemerintahan::with('profilDesa')
                            ->get()
                            ->mapWithKeys(function ($item) {
                                return [$item->id => $item->profilDesa->nama_desa ?? 'Desa tidak diketahui'];
                            });
                    })
                    ->searchable(),
                    
                Tables\Filters\SelectFilter::make('jabatan')
                    ->label('Jabatan')
                    ->options(function () {
                        return AparatDesa::distinct()
                            ->pluck('jabatan', 'jabatan')
                            ->toArray();
                    })
                    ->searchable(),
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
            ])
            ->defaultSort('urutan', 'asc');
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
            'index' => Pages\ListAparatDesas::route('/'),
            'create' => Pages\CreateAparatDesa::route('/create'),
            'view' => Pages\ViewAparatDesa::route('/{record}'),
            'edit' => Pages\EditAparatDesa::route('/{record}/edit'),
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