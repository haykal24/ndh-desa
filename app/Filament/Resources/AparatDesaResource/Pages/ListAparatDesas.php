<?php

namespace App\Filament\Resources\AparatDesaResource\Pages;

use App\Filament\Resources\AparatDesaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAparatDesas extends ListRecords
{
    protected static string $resource = AparatDesaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Aparat Desa Baru')
                ->icon('heroicon-o-plus'),
        ];
    }
} 