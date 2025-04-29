<?php

namespace App\Filament\Resources\PengajuanWargaResource\Pages;

use App\Filament\Resources\PengajuanWargaResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditPengajuanWarga extends EditRecord
{
    protected static string $resource = PengajuanWargaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
