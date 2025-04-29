<?php

namespace App\Filament\Resources\PengajuanWargaResource\Pages;

use App\Filament\Resources\PengajuanWargaResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use App\Models\Bansos;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\Storage;

class ViewPengajuanWarga extends ViewRecord
{
    protected static string $resource = PengajuanWargaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reject')
                ->label('Tolak Pengajuan')
                ->color('danger')
                ->icon('heroicon-o-x-mark')
                ->visible(fn () => in_array($this->record->status, ['Diajukan', 'Dalam Verifikasi']))
                ->form([
                    \Filament\Forms\Components\Textarea::make('alasan_penolakan')
                        ->label('Alasan Penolakan')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->addStatusHistory('Ditolak', $data['alasan_penolakan']);

                    // Update status pada record
                    $this->record->status = 'Ditolak';
                    $this->record->keterangan = $data['alasan_penolakan'];
                    $this->record->diubah_oleh = auth()->id();
                    $this->record->save();

                    $this->redirect(PengajuanWargaResource::getUrl('view', ['record' => $this->record]));
                }),

            Actions\EditAction::make()
                ->label('Edit Pengajuan'),

            Actions\Action::make('updateStatus')
                ->label('Update Status')
                ->icon('heroicon-o-arrow-path')
                ->form([
                    \Filament\Forms\Components\Select::make('status')
                        ->label('Status Baru')
                        ->options(function () {
                            $record = $this->record;
                            $status = Bansos::getStatusOptions();

                            // Filter status berdasarkan status sekarang
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

                    \Filament\Forms\Components\Textarea::make('keterangan')
                        ->label('Keterangan')
                        ->required(),

                    \Filament\Forms\Components\DateTimePicker::make('tanggal_penerimaan')
                        ->label('Tanggal Penerimaan')
                        ->visible(fn (\Filament\Forms\Get $get) => $get('status') === 'Sudah Diterima'),
                ])
                ->action(function (array $data) {
                    $record = $this->record;

                    // Catat perubahan ke history
                    $record->addStatusHistory($data['status'], $data['keterangan']);

                    // Update status pada record
                    $record->status = $data['status'];
                    $record->keterangan = $data['keterangan'];
                    $record->diubah_oleh = auth()->id();

                    // Set timestamp penerimaan jika ada
                    if ($data['status'] === 'Sudah Diterima') {
                        $record->tanggal_penerimaan = $data['tanggal_penerimaan'] ?? now();
                    }

                    $record->save();

                    $this->redirect(PengajuanWargaResource::getUrl('view', ['record' => $record->id]));
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Informasi Penerima Bantuan dengan layout responsif
                Section::make('Informasi Penerima Bantuan')
                    ->icon('heroicon-o-user')
                    ->schema([
                        // Baris 1: Nama dan data utama penerima
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                            'lg' => 6,
                        ])
                        ->schema([
                            Components\TextEntry::make('penduduk.nama')
                                ->label('Nama Penerima')
                                ->weight(FontWeight::Bold)
                                ->icon('heroicon-o-user')
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                    'lg' => 2,
                                ]),

                            Components\TextEntry::make('penduduk.nik')
                                ->label('NIK')
                                ->copyable()
                                ->icon('heroicon-o-identification')
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                    'lg' => 2,
                                ]),

                            Components\IconEntry::make('is_urgent')
                                ->label('Bantuan Mendesak')
                                ->boolean()
                                ->trueIcon('heroicon-o-exclamation-triangle')
                                ->falseIcon('heroicon-o-x-mark')
                                ->trueColor('danger')
                                ->falseColor('gray')
                                ->columnSpan(2),
                        ]),

                        // Baris 2: Informasi tambahan penduduk
                        Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('sumber_pengajuan')
                                    ->label('Sumber Pengajuan')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'admin' => 'Admin/Petugas Desa',
                                        'warga' => 'Pengajuan Warga',
                                        default => $state,
                                    })
                                    ->icon('heroicon-o-document-text'),

                                Components\TextEntry::make('penduduk.tanggal_lahir')
                                    ->label('Tanggal Lahir')
                                    ->date('d M Y')
                                    ->icon('heroicon-o-calendar'),

                                Components\TextEntry::make('desa.nama_desa')
                                    ->label('Desa')
                                    ->icon('heroicon-o-map-pin'),
                            ]),

                        // Baris 3: Alamat
                        Components\TextEntry::make('penduduk.alamat')
                            ->label('Alamat')
                            ->icon('heroicon-o-home')
                            ->columnSpanFull(),

                        // Baris 4: Informasi Kontak
                        Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('penduduk.no_hp')
                                    ->label('Nomor HP')
                                    ->icon('heroicon-o-phone')
                                    ->copyable()
                                    ->copyMessageDuration(2000)
                                    ->url(fn ($state) => $state ? "tel:{$state}" : null),

                                Components\TextEntry::make('penduduk.email')
                                    ->label('Email')
                                    ->icon('heroicon-o-envelope')
                                    ->copyable()
                                    ->copyMessageDuration(2000)
                                    ->url(fn ($state) => $state ? "mailto:{$state}" : null),
                            ])
                            ->visible(fn ($record) =>
                                !empty($record->penduduk?->no_hp) ||
                                !empty($record->penduduk?->email)
                            ),
                    ]),

                // Detail Bantuan yang diterima
                Section::make('Detail Bantuan Sosial')
                    ->icon('heroicon-o-gift')
                    ->schema([
                        // Baris 1: Nama Program, Status, Kategori
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                            'lg' => 6,
                        ])
                        ->schema([
                            Components\TextEntry::make('jenisBansos.nama_bansos')
                                ->label('Nama Program Bantuan')
                                ->weight(FontWeight::Bold)
                                ->size(Components\TextEntry\TextEntrySize::Large)
                                ->icon('heroicon-o-gift')
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 2,
                                    'lg' => 3,
                                ]),

                            Components\TextEntry::make('jenisBansos.is_active')
                                ->label('Status Program')
                                ->formatStateUsing(fn ($state) => $state ? 'Aktif' : 'Tidak Aktif')
                                ->badge()
                                ->color(fn ($state) => $state ? 'success' : 'danger')
                                ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                                ->columnSpan(1),

                            Components\TextEntry::make('jenisBansos.kategori')
                                ->label('Kategori')
                                ->badge()
                                ->icon('heroicon-o-tag')
                                ->color(fn (string $state): string => match ($state) {
                                    'Sembako' => 'primary',
                                    'Tunai' => 'success',
                                    'Kesehatan' => 'info',
                                    'Pendidikan' => 'warning',
                                    'Perumahan' => 'danger',
                                    'Pangan' => 'primary',
                                    'Pertanian' => 'success',
                                    'UMKM' => 'info',
                                    'Lainnya' => 'gray',
                                    default => 'gray',
                                })
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                    'lg' => 2,
                                ]),
                        ]),

                        // Baris 2: Instansi, Bentuk, Periode, Nilai Bantuan
                        Grid::make(4)
                            ->schema([
                                Components\TextEntry::make('jenisBansos.instansi_pemberi')
                                    ->label('Instansi Pemberi')
                                    ->icon('heroicon-o-building-office'),

                                Components\TextEntry::make('jenisBansos.bentuk_bantuan')
                                    ->label('Bentuk Bantuan')
                                    ->formatStateUsing(fn (?string $state): string => ucfirst($state))
                                    ->badge()
                                    ->icon('heroicon-o-cube')
                                    ->color('primary'),

                                Components\TextEntry::make('jenisBansos.periode')
                                    ->label('Periode Bantuan')
                                    ->icon('heroicon-o-calendar'),

                                Components\TextEntry::make('jenisBansos.nilai_bantuan')
                                    ->label('Nilai Bantuan')
                                    ->state(fn ($record) => $record->jenisBansos?->getNilaiBantuanFormatted() ?? '-')
                                    ->icon(fn ($record) => $record->jenisBansos?->bentuk_bantuan === 'uang' ?
                                        'heroicon-o-banknotes' : 'heroicon-o-scale'),
                            ]),

                        // Baris 3: Alasan pengajuan
                        Components\TextEntry::make('alasan_pengajuan')
                            ->label('Alasan Pengajuan')
                            ->markdown()
                            ->columnSpanFull(),
                    ]),

                // Status Bantuan dengan layout lebih efisien
                Section::make('Status Bantuan')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        // Status, Prioritas, dan Tanggal dalam satu baris
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                        ->schema([
                            Components\TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->size('lg')
                                ->weight(FontWeight::Bold)
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

                            Components\TextEntry::make('prioritas')
                                ->label('Prioritas')
                                ->badge()
                                ->icon('heroicon-o-flag')
                                ->color(fn (string $state): string => match ($state) {
                                    'Tinggi' => 'danger',
                                    'Sedang' => 'warning',
                                    'Rendah' => 'success',
                                    default => 'gray',
                                }),

                            Components\TextEntry::make('tanggal_pengajuan')
                                ->label('Tanggal Pengajuan')
                                ->date('d M Y')
                                ->icon('heroicon-o-calendar'),
                        ]),

                        // Baris tanggal-tanggal penting dan lokasi pengambilan dalam satu baris
                        Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('tanggal_penerimaan')
                                    ->label('Tanggal Penerimaan')
                                    ->date('d M Y')
                                    ->icon('heroicon-o-calendar-days')
                                    ->visible(fn ($record) => $record->tanggal_penerimaan !== null),

                                Components\TextEntry::make('tenggat_pengambilan')
                                    ->label('Batas Pengambilan')
                                    ->date('d M Y')
                                    ->icon('heroicon-o-clock')
                                    ->visible(fn ($record) => $record->tenggat_pengambilan !== null),

                                Components\TextEntry::make('lokasi_pengambilan')
                                    ->label('Lokasi Pengambilan')
                                    ->icon('heroicon-o-map-pin')
                                    ->visible(fn ($record) => !empty($record->lokasi_pengambilan)),
                            ])
                            ->visible(fn ($record) =>
                                $record->tanggal_penerimaan !== null ||
                                $record->tenggat_pengambilan !== null ||
                                !empty($record->lokasi_pengambilan)
                            ),

                        // Keterangan
                        Components\TextEntry::make('keterangan')
                            ->label('Keterangan')
                            ->markdown()
                            ->icon('heroicon-o-document-text')
                            ->visible(fn ($record) => $record->keterangan)
                            ->extraAttributes(['class' => 'prose max-w-none'])
                            ->columnSpanFull(),
                    ]),

                // Dokumen Pendukung
                Section::make('Dokumen Pendukung')
                    ->icon('heroicon-o-document')
                    ->schema([
                        Grid::make([
                            'default' => 1,     // 1 kolom pada layar kecil
                            'md' => 2,          // 2 kolom mulai dari ukuran medium
                        ])
                        ->schema([
                            // Kolom 1: Foto rumah (di sebelah kiri)
                            ImageEntry::make('foto_rumah')
                                ->label('Foto Rumah')
                                ->visible(fn ($record) => $record->foto_rumah !== null)
                                ->extraAttributes(['class' => 'w-full h-auto max-h-80 object-contain'])
                                ->columnSpan(1),

                            // Kolom 2: Dokumen pendukung dan bukti penerimaan (di sebelah kanan)
                            Group::make([
                                Components\TextEntry::make('dokumen_pendukung')
                                    ->label('Dokumen Pendukung')
                                    ->visible(fn ($record) => $record->dokumen_pendukung !== null)
                                    ->formatStateUsing(function ($state) {
                                        // Periksa tipe data dan konversi jika perlu
                                        if (!$state) return '-';

                                        // Jika state adalah string, mungkin ini path tunggal, bukan array
                                        if (is_string($state)) {
                                            $documents = [$state]; // Konversi ke array dengan 1 item
                                        } elseif (is_array($state)) {
                                            $documents = $state;
                                        } else {
                                            return 'Format dokumen tidak didukung';
                                        }

                                        $html = '<div class="space-y-2">';
                                        foreach ($documents as $dokumen) {
                                            $url = Storage::url($dokumen);
                                            $filename = basename($dokumen);
                                            $extension = pathinfo($filename, PATHINFO_EXTENSION);

                                            // Pilih ikon berdasarkan jenis file
                                            $icon = match(strtolower($extension)) {
                                                'pdf' => '<svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path><path d="M3 8a2 2 0 012-2h2a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"></path></svg>',
                                                'jpg', 'jpeg', 'png', 'gif' => '<svg class="h-5 w-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path></svg>',
                                                default => '<svg class="h-5 w-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path></svg>',
                                            };

                                            $html .= '<div class="flex items-center p-2 border rounded hover:bg-gray-50">';
                                            $html .= $icon;
                                            $html .= '<a href="' . $url . '" target="_blank" class="ml-2 text-primary-600 hover:text-primary-800 flex-grow">' . $filename . '</a>';
                                            $html .= '<a href="' . $url . '" download class="text-gray-500 hover:text-gray-700 ml-2">';
                                            $html .= '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                            $html .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />';
                                            $html .= '</svg>';
                                            $html .= '</a>';
                                            $html .= '</div>';
                                        }
                                        $html .= '</div>';

                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->extraAttributes(['class' => 'mb-4']),

                                Components\TextEntry::make('bukti_penerimaan')
                                    ->label('Bukti Penerimaan')
                                    ->visible(fn ($record) => $record->bukti_penerimaan !== null)
                                    ->formatStateUsing(function ($state) {
                                        // Periksa tipe data dan konversi jika perlu
                                        if (!$state) return '-';

                                        // Jika state adalah string, mungkin ini path tunggal, bukan array
                                        if (is_string($state)) {
                                            $documents = [$state]; // Konversi ke array dengan 1 item
                                        } elseif (is_array($state)) {
                                            $documents = $state;
                                        } else {
                                            return 'Format dokumen tidak didukung';
                                        }

                                        $html = '<div class="space-y-2">';
                                        foreach ($documents as $dokumen) {
                                            $url = Storage::url($dokumen);
                                            $filename = basename($dokumen);
                                            $extension = pathinfo($filename, PATHINFO_EXTENSION);

                                            // Pilih ikon berdasarkan jenis file
                                            $icon = match(strtolower($extension)) {
                                                'pdf' => '<svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path><path d="M3 8a2 2 0 012-2h2a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"></path></svg>',
                                                'jpg', 'jpeg', 'png', 'gif' => '<svg class="h-5 w-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path></svg>',
                                                default => '<svg class="h-5 w-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path></svg>',
                                            };

                                            $html .= '<div class="flex items-center p-2 border rounded hover:bg-gray-50">';
                                            $html .= $icon;
                                            $html .= '<a href="' . $url . '" target="_blank" class="ml-2 text-primary-600 hover:text-primary-800 flex-grow">' . $filename . '</a>';
                                            $html .= '<a href="' . $url . '" download class="text-gray-500 hover:text-gray-700 ml-2">';
                                            $html .= '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                            $html .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />';
                                            $html .= '</svg>';
                                            $html .= '</a>';
                                            $html .= '</div>';
                                        }
                                        $html .= '</div>';

                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->extraAttributes(['class' => 'prose max-w-none']),
                            ])
                            ->columnSpan(1)
                            ->visible(fn ($record) =>
                                $record->dokumen_pendukung !== null ||
                                $record->bukti_penerimaan !== null
                            ),
                        ]),
                    ])
                    ->collapsible()
                    ->collapsed(false)
                    ->visible(fn ($record) =>
                        $record->foto_rumah !== null ||
                        $record->dokumen_pendukung !== null ||
                        $record->bukti_penerimaan !== null
                    )
                    ->columnSpanFull(),

                // Riwayat Bantuan yang konsisten dengan BansosResource
                Section::make('Riwayat Bantuan yang Diterima')
                    ->icon('heroicon-o-clock')
                    ->columnSpanFull()
                    ->schema([
                        Components\TextEntry::make('riwayat_bantuan')
                            ->label(false)
                            ->state(function ($record) {
                                return $record;
                            })
                            ->formatStateUsing(function ($state) {
                                $pendudukId = $state->penduduk_id;
                                if (!$pendudukId) return 'Data penerima tidak tersedia';

                                $bantuanSebelumnya = \App\Models\Bansos::where('penduduk_id', $pendudukId)
                                    ->with('jenisBansos')
                                    ->orderByDesc('tanggal_pengajuan')
                                    ->get();

                                if ($bantuanSebelumnya->isEmpty()) {
                                    return 'Belum ada bantuan yang diterima';
                                }

                                // Gunakan kelas CSS yang persis sama dengan di BansosResource
                                $html = "<div class='space-y-3 p-1'>";
                                foreach ($bantuanSebelumnya as $item) {
                                    if (!$item->jenisBansos) continue;

                                    // Buat badge status sesuai warna - persis sama dengan BansosResource
                                    $statusClass = match($item->status) {
                                        'Diajukan' => 'bg-gray-100 text-gray-700',
                                        'Dalam Verifikasi' => 'bg-amber-100 text-amber-700',
                                        'Diverifikasi' => 'bg-blue-100 text-blue-700',
                                        'Disetujui' => 'bg-green-100 text-green-700',
                                        'Ditolak' => 'bg-red-100 text-red-700',
                                        'Sudah Diterima' => 'bg-indigo-100 text-indigo-700',
                                        'Dibatalkan' => 'bg-gray-100 text-gray-700',
                                        default => 'bg-gray-100 text-gray-700'
                                    };

                                    $url = route('filament.admin.resources.bansos.view', ['record' => $item->id]);

                                    // Gunakan class yang persis sama dengan BansosResource
                                    $html .= "<div class='flex items-center justify-between border rounded gap-4 p-2 full-width'>";
                                    $html .= "<div>";
                                    // Perbedaan kecil: kita gunakan <a> untuk link ke detail bantuan
                                    $html .= "<a href='{$url}' class='font-medium text-primary-600 hover:text-primary-800'>" . $item->jenisBansos->nama_bansos . "</a>";
                                    $html .= "<div class='text-xs text-gray-500'>" . $item->tanggal_pengajuan->format('d M Y') . "</div>";
                                    $html .= "</div>";
                                    $html .= "<span class='px-2 py-1 text-xs rounded-full {$statusClass}'>{$item->status}</span>";
                                    $html .= "</div>";
                                }
                                $html .= "</div>";

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull()
                    ])
                    ->collapsible(true)
                    ->columnSpanFull(),

                // Informasi Tambahan - metadata
                Section::make('Informasi Tambahan')
                    ->icon('heroicon-o-information-circle')
                    ->collapsed(true)
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('created_at')
                                    ->label('Dibuat Pada')
                                    ->dateTime('d M Y, H:i')
                                    ->icon('heroicon-o-clock'),

                                Components\TextEntry::make('updated_at')
                                    ->label('Diperbarui Pada')
                                    ->dateTime('d M Y, H:i')
                                    ->icon('heroicon-o-arrow-path'),

                                Components\TextEntry::make('editor.name')
                                    ->label('Terakhir Diubah Oleh')
                                    ->icon('heroicon-o-user-circle')
                                    ->visible(fn ($record) => $record->diubah_oleh !== null),
                            ]),
                    ]),
            ])
            ->columns(1);
    }
}