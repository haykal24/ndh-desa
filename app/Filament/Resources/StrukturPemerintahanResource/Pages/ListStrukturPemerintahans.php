<?php

namespace App\Filament\Resources\StrukturPemerintahanResource\Pages;

use App\Filament\Resources\StrukturPemerintahanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStrukturPemerintahans extends ListRecords
{
    protected static string $resource = StrukturPemerintahanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Struktur')
                ->icon('heroicon-o-plus'),
        ];
    }
} 