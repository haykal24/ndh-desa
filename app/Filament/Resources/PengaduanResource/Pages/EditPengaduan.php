<?php

namespace App\Filament\Resources\PengaduanResource\Pages;

use App\Filament\Resources\PengaduanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPengaduan extends EditRecord
{
    protected static string $resource = PengaduanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Jika status berubah, catat petugas yang menangani
        if ($this->record->status !== $data['status']) {
            $data['ditangani_oleh'] = auth()->id();
        }

        // Jika tanggapan diisi atau diubah, catat waktu tanggapan
        if (empty($this->record->tanggapan) && filled($data['tanggapan'])) {
            $data['tanggal_tanggapan'] = now();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}