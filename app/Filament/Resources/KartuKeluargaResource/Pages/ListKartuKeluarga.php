<?php

namespace App\Filament\Resources\KartuKeluargaResource\Pages;

use App\Filament\Resources\KartuKeluargaResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Filament\Forms;
use App\Models\ProfilDesa;
use App\Models\Penduduk;
use Illuminate\Support\Collection;
use App\Http\Controllers\KartuKeluargaExportController;
use Illuminate\Http\Request;

class ListKartuKeluarga extends ListRecords
{
    protected static string $resource = KartuKeluargaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Aksi untuk ekspor semua KK
            Actions\Action::make('exportAll')
                ->label('Ekspor Semua')
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('id_desa')
                        ->label('Desa')
                        ->options(ProfilDesa::pluck('nama_desa', 'id'))
                        ->placeholder('Semua Desa'),

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
                    return redirect()->route('kartu-keluarga.export.all', [
                        'id_desa' => $data['id_desa'] ?? null,
                        'format' => $data['format'] ?? 'pdf',
                    ]);
                }),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            // Bulk action untuk ekspor KK yang dipilih
            Tables\Actions\BulkAction::make('exportSelected')
                ->label('Ekspor Terpilih')
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->form([
                    Forms\Components\Select::make('format')
                        ->label('Format Ekspor')
                        ->options([
                            'pdf' => 'PDF',
                            'excel' => 'Excel',
                        ])
                        ->default('excel')
                        ->required(),
                ])
                ->action(function (Collection $records, array $data) {
                    // Kumpulkan Nomor KK dari kepala keluarga yang dipilih
                    $nomorKK = $records->pluck('kk')->toArray();

                    return redirect()->route('kartu-keluarga.export.selected', [
                        'kk' => implode(',', $nomorKK),
                        'format' => $data['format'] ?? 'excel',
                    ]);
                })
                ->deselectRecordsAfterCompletion(),
        ];
    }
}