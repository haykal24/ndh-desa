<?php

namespace App\Filament\Resources\BatasWilayahPotensiResource\Pages;

use App\Filament\Resources\BatasWilayahPotensiResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBatasWilayahPotensis extends ListRecords
{
    protected static string $resource = BatasWilayahPotensiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->icon('heroicon-o-plus')
            ->label('Tambah Data'),
        ];
    }
}