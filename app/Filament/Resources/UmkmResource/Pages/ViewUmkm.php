<?php

namespace App\Filament\Resources\UmkmResource\Pages;

use App\Filament\Resources\UmkmResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUmkm extends ViewRecord
{
    protected static string $resource = UmkmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
            ->icon('heroicon-o-pencil-square')
            ->label('Ubah Data'),
        ];
    }
}