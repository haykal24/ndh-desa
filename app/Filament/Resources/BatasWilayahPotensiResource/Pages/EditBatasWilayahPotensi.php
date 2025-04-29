<?php

namespace App\Filament\Resources\BatasWilayahPotensiResource\Pages;

use App\Filament\Resources\BatasWilayahPotensiResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBatasWilayahPotensi extends EditRecord
{
    protected static string $resource = BatasWilayahPotensiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}