<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BansosHistoryResource\Pages;
use App\Filament\Resources\BansosHistoryResource\RelationManagers;
use App\Models\BansosHistory;
use App\Models\Bansos;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BansosHistoryResource extends Resource
{
    protected static ?string $model = BansosHistory::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Bantuan Sosial';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'status_baru';

    public static function getNavigationLabel(): string
    {
        return 'Riwayat Status Bantuan';
    }

    public static function getPluralLabel(): string
    {
        return 'Riwayat Status Bantuan';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('bansos_id')
                    ->label('Bantuan Sosial')
                    ->relationship('bansos', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Bansos $record) =>
                        "{$record->penduduk->nama} - {$record->jenisBansos->nama_bansos}")
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('status_lama')
                    ->label('Status Lama')
                    ->options(Bansos::getStatusOptions())
                    ->nullable(),

                Forms\Components\Select::make('status_baru')
                    ->label('Status Baru')
                    ->options(Bansos::getStatusOptions())
                    ->required(),

                Forms\Components\Textarea::make('keterangan')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Select::make('diubah_oleh')
                    ->label('Diubah Oleh')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\DateTimePicker::make('waktu_perubahan')
                    ->label('Waktu Perubahan')
                    ->required()
                    ->default(now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('waktu_perubahan')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('bansos.penduduk.nama')
                    ->label('Penerima')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bansos.jenisBansos.nama_bansos')
                    ->label('Jenis Bantuan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status_lama')
                    ->label('Status Lama')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status_baru')
                    ->label('Status Baru')
                    ->badge()
                    ->colors([
                        'gray' => 'Diajukan',
                        'info' => 'Diverifikasi',
                        'success' => 'Disetujui',
                        'danger' => 'Ditolak',
                        'primary' => 'Sudah Diterima',
                        'warning' => 'Dibatalkan',
                    ]),

                Tables\Columns\TextColumn::make('keterangan')
                    ->limit(50)
                    ->tooltip(function($record) {
                        return $record->keterangan;
                    }),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Diubah Oleh')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status_baru')
                    ->label('Status Baru')
                    ->options(Bansos::getStatusOptions()),

                Tables\Filters\Filter::make('waktu_perubahan')
                    ->form([
                        Forms\Components\DatePicker::make('dari_tanggal'),
                        Forms\Components\DatePicker::make('sampai_tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['dari_tanggal'],
                                fn (Builder $query, $date): Builder => $query->whereDate('waktu_perubahan', '>=', $date),
                            )
                            ->when(
                                $data['sampai_tanggal'],
                                fn (Builder $query, $date): Builder => $query->whereDate('waktu_perubahan', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Riwayat sebaiknya tidak diedit, hanya bisa dilihat
            ])
            ->bulkActions([
                // Riwayat sebaiknya tidak dihapus secara massal
            ])
            ->defaultSort('waktu_perubahan', 'desc');
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
            'index' => Pages\ListBansosHistories::route('/'),
            'view' => Pages\ViewBansosHistory::route('/{record}'),
            // Sebaiknya tidak ada halaman create dan edit
        ];
    }

    // Override agar tidak muncul tombol create di halaman index
    public static function canCreate(): bool
    {
        return false;
    }
}