<?php

namespace App\Filament\Resources\BatasWilayahPotensiResource\Pages;

use App\Filament\Resources\BatasWilayahPotensiResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBatasWilayahPotensi extends ViewRecord
{
    protected static string $resource = BatasWilayahPotensiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
            ->icon('heroicon-o-pencil-square')
            ->label('Ubah Data'),
            Actions\DeleteAction::make()
            ->icon('heroicon-o-trash')
            ->label('Hapus Data'),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}