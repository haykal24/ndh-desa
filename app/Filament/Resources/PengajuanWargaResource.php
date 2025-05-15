<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PengajuanWargaResource\Pages;
use App\Models\Bansos;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Models\ProfilDesa;
use Illuminate\Database\Eloquent\Collection;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\JenisBansos;

class PengajuanWargaResource extends Resource
{
    protected static ?string $model = Bansos::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Bantuan Sosial';

    protected static ?int $navigationSort = 3; // Posisikan setelah Bansos dan Jenis Bansos

    public static function getNavigationLabel(): string
    {
        return 'Pengajuan Warga';
    }

    public static function getPluralLabel(): string
    {
        return 'Pengajuan Warga';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('sumber_pengajuan', 'warga')
            ->where('status', 'Diajukan')
            ->whereNull('deleted_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('sumber_pengajuan', 'warga')
            ->where('status', 'Diajukan')
            ->count() > 0 ? 'warning' : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pengajuan')
                    ->description('Detail pengajuan bantuan sosial')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('jenis_bansos_id')
                                    ->label('Jenis Bantuan')
                                    ->relationship('jenisBansos', 'nama_bansos')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\Select::make('prioritas')
                                    ->label('Prioritas')
                                    ->options(Bansos::getPrioritasOptions())
                                    ->required(),
                            ]),

                        Forms\Components\Toggle::make('is_urgent')
                            ->label('Bantuan Mendesak')
                            ->helperText('Tandai jika bantuan ini perlu ditangani segera')
                            ->onColor('danger')
                            ->offColor('gray'),

                        Forms\Components\Textarea::make('alasan_pengajuan')
                            ->label('Alasan Pengajuan')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan Tambahan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Status & Tanggal')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options(Bansos::getStatusOptions())
                                    ->required(),

                                Forms\Components\DateTimePicker::make('tanggal_pengajuan')
                                    ->label('Tanggal Pengajuan')
                                    ->required(),

                                Forms\Components\DateTimePicker::make('tanggal_penerimaan')
                                    ->label('Tanggal Penerimaan')
                                    ->visible(fn (Forms\Get $get) => $get('status') === 'Sudah Diterima'),

                                Forms\Components\DateTimePicker::make('tenggat_pengambilan')
                                    ->label('Tenggat Pengambilan')
                                    ->visible(fn (Forms\Get $get) => $get('status') === 'Disetujui'),
                            ]),

                        Forms\Components\TextInput::make('lokasi_pengambilan')
                            ->label('Lokasi Pengambilan')
                            ->helperText('Lokasi dimana bantuan dapat diambil')
                            ->visible(fn (Forms\Get $get) => in_array($get('status'), ['Disetujui', 'Diverifikasi']))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Dokumen')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\FileUpload::make('foto_rumah')
                                    ->label('Foto Rumah')
                                    ->image()
                                    ->maxSize(2048)
                                    ->directory('bansos/foto-rumah'),

                                Forms\Components\FileUpload::make('dokumen_pendukung')
                                    ->label('Dokumen Pendukung')
                                    ->multiple()
                                    ->maxFiles(3)
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->maxSize(2048)
                                    ->directory('bansos/dokumen'),

                                Forms\Components\FileUpload::make('bukti_penerimaan')
                                    ->label('Bukti Penerimaan')
                                    ->image()
                                    ->maxSize(2048)
                                    ->directory('bansos/bukti-penerimaan')
                                    ->visible(fn (Forms\Get $get) => $get('status') === 'Sudah Diterima'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('penduduk.nama')
                    ->label('Nama Pemohon')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('desa.nama_desa')
                    ->label('Desa')
                    ->sortable(),

                Tables\Columns\TextColumn::make('jenisBansos.nama_bansos')
                    ->label('Jenis Bantuan')
                    ->description(fn (Bansos $record): ?string => $record->jenisBansos?->instansi_pemberi)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('jenisBansos.bentuk_bantuan')
                    ->label('Bentuk Bantuan')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('prioritas')
                    ->badge()
                    ->alignment('center')
                    ->color(fn (string $state): string => match ($state) {
                        'Tinggi' => 'danger',
                        'Sedang' => 'warning',
                        'Rendah' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->alignment('center')
                    ->color(fn (string $state): string => match ($state) {
                        'Diajukan' => 'gray',
                        'Dalam Verifikasi' => 'warning',
                        'Diverifikasi' => 'info',
                        'Disetujui' => 'success',
                        'Ditolak' => 'danger',
                        'Sudah Diterima' => 'primary',
                        'Dibatalkan' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_urgent')
                    ->label('Mendesak')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('danger'),

                Tables\Columns\TextColumn::make('tanggal_pengajuan')
                    ->date('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Pengajuan')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Bansos::getStatusOptions()),

                Tables\Filters\SelectFilter::make('jenis_bansos_id')
                    ->label('Jenis Bantuan')
                    ->options(JenisBansos::where('is_active', true)->pluck('nama_bansos', 'id'))
                    ->searchable(),


                Tables\Filters\Filter::make('is_urgent')
                    ->label('Bantuan Mendesak')
                    ->query(fn (Builder $query): Builder => $query->where('is_urgent', true)),

                Tables\Filters\Filter::make('warga_dengan_banyak_pengajuan')
                    ->label('Warga dengan Banyak Pengajuan')
                    ->form([
                        Forms\Components\Select::make('minimum_pengajuan')
                            ->label('Minimum Pengajuan')
                            ->options([
                                2 => '2 pengajuan',
                                3 => '3 pengajuan',
                                4 => '4 pengajuan',
                                5 => '5+ pengajuan',
                            ])
                            ->default(2),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['minimum_pengajuan'])) {
                            return $query;
                        }

                        // Subquery untuk mendapatkan penduduk_id yang memiliki banyak bantuan
                        $pendudukIds = Bansos::select('penduduk_id')
                            ->groupBy('penduduk_id')
                            ->havingRaw('COUNT(*) >= ?', [$data['minimum_pengajuan']])
                            ->pluck('penduduk_id');

                        return $query->whereIn('penduduk_id', $pendudukIds);
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Lihat Detail'),

                Tables\Actions\Action::make('updateStatus')
                    ->label('Update Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Status Baru')
                            ->options(function (Bansos $record) {
                                // Filter status berdasarkan status sekarang
                                $status = Bansos::getStatusOptions();

                                // Hilangkan status yang tidak logis untuk transisi
                                switch ($record->status) {
                                    case 'Diajukan':
                                        unset($status['Sudah Diterima']);
                                        break;
                                    case 'Dalam Verifikasi':
                                        unset($status['Diajukan'], $status['Sudah Diterima']);
                                        break;
                                    case 'Diverifikasi':
                                        unset($status['Diajukan'], $status['Dalam Verifikasi']);
                                        break;
                                    case 'Disetujui':
                                        return ['Sudah Diterima' => 'Sudah Diterima', 'Dibatalkan' => 'Dibatalkan'];
                                }

                                return $status;
                            })
                            ->required(),

                        Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->required(),

                        Forms\Components\DateTimePicker::make('tanggal_penerimaan')
                            ->label('Tanggal Penerimaan')
                            ->visible(fn (Forms\Get $get) => $get('status') === 'Sudah Diterima'),
                    ])
                    ->action(function (Bansos $record, array $data) {
                        // Catat perubahan ke history
                        $record->addStatusHistory($data['status'], $data['keterangan']);

                        // Simpan status lama untuk perbandingan
                        $oldStatus = $record->status;

                        // Update status pada record
                        $record->status = $data['status'];
                        $record->keterangan = $data['keterangan'];
                        $record->diubah_oleh = auth()->id();

                        // Set timestamp penerimaan jika ada
                        if ($data['status'] === 'Sudah Diterima') {
                            $record->tanggal_penerimaan = $data['tanggal_penerimaan'] ?? now();
                        }

                        $record->save();

                        // Tambahkan notifikasi jika status diubah dari Diajukan
                        if ($oldStatus === 'Diajukan' && $data['status'] !== 'Diajukan') {
                            Notification::make()
                                ->title('Pengajuan berhasil diproses')
                                ->body('Pengajuan telah diubah statusnya dan sekarang akan muncul di Data Bantuan Sosial')
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Bansos $record) => in_array($record->status, ['Diajukan', 'Dalam Verifikasi']))
                    ->form([
                        Forms\Components\Textarea::make('alasan_penolakan')
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->action(function (Bansos $record, array $data) {
                        // Catat perubahan ke history
                        $record->addStatusHistory('Ditolak', $data['alasan_penolakan']);

                        // Update status pada record
                        $record->status = 'Ditolak';
                        $record->keterangan = 'Ditolak: ' . $data['alasan_penolakan'];
                        $record->diubah_oleh = auth()->id();
                        $record->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('updatePrioritas')
                        ->label('Update Prioritas')
                        ->icon('heroicon-o-arrow-trending-up')
                        ->form([
                            Forms\Components\Select::make('prioritas')
                                ->options(Bansos::getPrioritasOptions())
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $record) {
                                $record->update(['prioritas' => $data['prioritas']]);
                            }
                        }),

                    Tables\Actions\BulkAction::make('setInVerification')
                        ->label('Tandai Dalam Verifikasi')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                if ($record->status === 'Diajukan') {
                                    // Catat perubahan ke history
                                    $record->addStatusHistory('Dalam Verifikasi', 'Sedang dalam proses verifikasi');

                                    // Update status pada record
                                    $record->status = 'Dalam Verifikasi';
                                    $record->diubah_oleh = auth()->id();
                                    $record->save();
                                }
                            }
                        }),
                ]),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                // Tampilkan semua pengajuan dari warga, tidak hanya yang berstatus "Diajukan"
                return $query->where('sumber_pengajuan', 'warga')
                    ->whereNull('deleted_at');
            })
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Bisa menambahkan RelationManager untuk riwayat status jika diperlukan
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPengajuanWarga::route('/'),
            'view' => Pages\ViewPengajuanWarga::route('/{record}'),
            'edit' => Pages\EditPengajuanWarga::route('/{record}/edit'),
        ];
    }
}