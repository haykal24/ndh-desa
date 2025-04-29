<?php

namespace App\Filament\Resources\PengajuanWargaResource\Pages;

use App\Filament\Resources\PengajuanWargaResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;

class ListPengajuanWarga extends ListRecords
{
    protected static string $resource = PengajuanWargaResource::class;

    public function getTitle(): string
    {
        return 'Pengajuan Bantuan dari Warga';
    }

    protected function getHeaderDescription(): ?string
    {
        return 'Halaman ini hanya menampilkan pengajuan dengan status "Diajukan". Pengajuan yang sudah diverifikasi atau diproses lebih lanjut akan muncul di menu Data Bantuan Sosial.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refreshNavigation')
                ->label('Refresh Menu')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    // Force refresh navigation cache
                    cache()->forget('filament.navigation');

                    $this->redirect($this->getResource()::getUrl());
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Jika ingin menambahkan widget stats
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }
}