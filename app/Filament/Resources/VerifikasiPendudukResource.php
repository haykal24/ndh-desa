<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VerifikasiPendudukResource\Pages;
use App\Models\VerifikasiPenduduk;
use App\Models\Penduduk;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class VerifikasiPendudukResource extends Resource
{
    protected static ?string $model = VerifikasiPenduduk::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Kependudukan';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Verifikasi Data Warga';
    }

    public static function getPluralLabel(): string
    {
        return 'Verifikasi Data Warga';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() > 0 ? 'warning' : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Data Diri')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('nik')
                            ->label('NIK')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('kk')
                            ->label('Nomor KK')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('nama')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('tempat_lahir')
                            ->label('Tempat Lahir')
                            ->maxLength(100),
                        Forms\Components\DatePicker::make('tanggal_lahir')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('jenis_kelamin')
                            ->label('Jenis Kelamin')
                            ->options([
                                'L' => 'Laki-laki',
                                'P' => 'Perempuan',
                            ]),
                        Forms\Components\Select::make('golongan_darah')
                            ->label('Golongan Darah')
                            ->options([
                                'A' => 'A',
                                'B' => 'B',
                                'AB' => 'AB',
                                'O' => 'O',
                                'A+' => 'A+',
                                'A-' => 'A-',
                                'B+' => 'B+',
                                'B-' => 'B-',
                                'AB+' => 'AB+',
                                'AB-' => 'AB-',
                                'O+' => 'O+',
                                'O-' => 'O-',
                                'Belum Diketahui' => 'Belum Diketahui',
                            ]),
                    ]),

                Section::make('Alamat')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Textarea::make('alamat')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('rt_rw')
                            ->label('RT/RW')
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Informasi Kontak')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('no_hp')
                            ->label('Nomor HP')
                            ->tel()
                            ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                    ]),

                Section::make('Informasi Tambahan')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('agama')
                            ->options([
                                'Islam' => 'Islam',
                                'Kristen' => 'Kristen',
                                'Katolik' => 'Katolik',
                                'Hindu' => 'Hindu',
                                'Buddha' => 'Buddha',
                                'Konghucu' => 'Konghucu',
                                'Lainnya' => 'Lainnya',
                            ]),
                        Forms\Components\Select::make('status_perkawinan')
                            ->options([
                                'Belum Kawin' => 'Belum Kawin',
                                'Kawin' => 'Kawin',
                                'Cerai Hidup' => 'Cerai Hidup',
                                'Cerai Mati' => 'Cerai Mati',
                            ]),
                        Forms\Components\TextInput::make('pekerjaan')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('pendidikan')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Checkbox::make('kepala_keluarga')
                            ->label('Kepala Keluarga')
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Informasi Pengajuan')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('user.name')
                            ->label('Nama Pengaju')
                            ->formatStateUsing(function ($record) {
                                return $record->user?->name ?? 'Pengajuan Mandiri';
                            })
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('user.email')
                            ->label('Email Pengaju')
                            ->formatStateUsing(function ($record) {
                                return $record->user?->email ?? 'Tidak tersedia';
                            })
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Tanggal Pengajuan')
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Verifikasi')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Disetujui',
                                'rejected' => 'Ditolak',
                            ])
                            ->required(),
                        Textarea::make('catatan')
                            ->label('Catatan Admin')
                            ->placeholder('Tambahkan catatan jika perlu')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pengaju')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nik')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kk')
                    ->label('Nomor KK')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('no_hp')
                    ->label('Nomor HP')
                    ->searchable(),
                Tables\Columns\TextColumn::make('alamat')
                    ->limit(30)
                    ->tooltip(function (VerifikasiPenduduk $record): string {
                        return $record->alamat;
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Pengajuan')
                    ->date('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                        default => 'Tidak Diketahui',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'L' => 'info',
                        'P' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('golongan_darah')
                    ->label('Gol. Darah')
                    ->badge()
                    ->color(fn (string $state): string => match($state ?? '') {
                        'A', 'A+', 'A-' => 'success',
                        'B', 'B+', 'B-' => 'info',
                        'AB', 'AB+', 'AB-' => 'warning',
                        'O', 'O+', 'O-' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
                SelectFilter::make('golongan_darah')
                    ->label('Golongan Darah')
                    ->options([
                        'A' => 'A',
                        'B' => 'B',
                        'AB' => 'AB',
                        'O' => 'O',
                        'A+' => 'A+',
                        'A-' => 'A-',
                        'B+' => 'B+',
                        'B-' => 'B-',
                        'AB+' => 'AB+',
                        'AB-' => 'AB-',
                        'O+' => 'O+',
                        'O-' => 'O-',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (VerifikasiPenduduk $record) => $record->status === 'pending')
                    ->action(function (VerifikasiPenduduk $record) {
                        DB::beginTransaction();
                        try {
                            // Check if penduduk with this NIK already exists
                            $existingPenduduk = Penduduk::where('nik', $record->nik)->first();

                            if ($existingPenduduk) {
                                // Update existing penduduk record
                                $existingPenduduk->update([
                                    'id_desa' => $record->id_desa,
                                    'kk' => $record->kk,
                                    'kepala_keluarga_id' => $record->kepala_keluarga_id,
                                    'nama' => $record->nama,
                                    'alamat' => $record->alamat,
                                    'rt_rw' => $record->rt_rw,
                                    'tanggal_lahir' => $record->tanggal_lahir,
                                    'kepala_keluarga' => $record->kepala_keluarga,
                                    'pekerjaan' => $record->pekerjaan,
                                    'pendidikan' => $record->pendidikan,
                                    'jenis_kelamin' => $record->jenis_kelamin,
                                    'agama' => $record->agama,
                                    'status_perkawinan' => $record->status_perkawinan,
                                    'tempat_lahir' => $record->tempat_lahir,
                                    'no_hp' => $record->no_hp,
                                    'email' => $record->email,
                                    'golongan_darah' => $record->golongan_darah,
                                ]);
                                $penduduk = $existingPenduduk;
                            } else {
                                // Create new penduduk record
                                $penduduk = Penduduk::create([
                                    'id_desa' => $record->id_desa,
                                    'nik' => $record->nik,
                                    'kk' => $record->kk,
                                    'kepala_keluarga_id' => $record->kepala_keluarga_id,
                                    'nama' => $record->nama,
                                    'alamat' => $record->alamat,
                                    'rt_rw' => $record->rt_rw,
                                    'tanggal_lahir' => $record->tanggal_lahir,
                                    'kepala_keluarga' => $record->kepala_keluarga,
                                    'pekerjaan' => $record->pekerjaan,
                                    'pendidikan' => $record->pendidikan,
                                    'jenis_kelamin' => $record->jenis_kelamin,
                                    'agama' => $record->agama,
                                    'status_perkawinan' => $record->status_perkawinan,
                                    'tempat_lahir' => $record->tempat_lahir,
                                    'no_hp' => $record->no_hp,
                                    'email' => $record->email,
                                    'golongan_darah' => $record->golongan_darah,
                                ]);
                            }

                            // Update verifikasi dengan penduduk_id
                            $record->update([
                                'penduduk_id' => $penduduk->id,
                                'status' => 'approved',
                                'catatan' => 'Disetujui oleh admin'
                            ]);

                            // Update user dengan penduduk_id dan role
                            $user = User::find($record->user_id);
                            if ($user) {
                                $user->penduduk_id = $penduduk->id;
                                $user->save();

                                // Hapus role unverified dan tambahkan role warga
                                $user->removeRole('unverified');
                                $user->assignRole('warga');
                            }

                            DB::commit();

                            Notification::make()
                                ->title('Verifikasi berhasil disetujui')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Notification::make()
                                ->title('Gagal memproses verifikasi')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (VerifikasiPenduduk $record) => $record->status === 'pending')
                    ->form([
                        Textarea::make('catatan')
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->action(function (VerifikasiPenduduk $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'catatan' => $data['catatan']
                        ]);

                        Notification::make()
                            ->title('Verifikasi telah ditolak')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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

                            // Format ekspor di bawah periode
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
                                'ids' => $records->pluck('id')->join(','),
                                'format' => $data['format'] ?? 'pdf',
                            ];

                            // Tambahkan parameter yang tidak null
                            if ($dariTanggal) {
                                $params['dari_tanggal'] = $dariTanggal;
                            }

                            if ($sampaiTanggal) {
                                $params['sampai_tanggal'] = $sampaiTanggal;
                            }

                            return redirect()->route('verifikasi.export.selected', $params);
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function (Builder $query) {
                return $query->whereIn('status', ['pending', 'rejected']);
            });
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
            'index' => Pages\ListVerifikasiPenduduks::route('/'),
            'view' => Pages\ViewVerifikasiPenduduk::route('/{record}'),
        ];
    }
}