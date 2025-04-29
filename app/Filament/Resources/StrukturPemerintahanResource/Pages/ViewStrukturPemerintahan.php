<?php

namespace App\Filament\Resources\StrukturPemerintahanResource\Pages;

use App\Filament\Resources\StrukturPemerintahanResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStrukturPemerintahan extends ViewRecord
{
    protected static string $resource = StrukturPemerintahanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Ubah Struktur')
                ->icon('heroicon-o-pencil-square'),
            Actions\DeleteAction::make()
                ->label('Hapus Struktur')
                ->icon('heroicon-o-trash'),
            Actions\ForceDeleteAction::make()
                ->icon('heroicon-o-trash'),
            Actions\RestoreAction::make()
                ->icon('heroicon-o-arrow-path'),
        ];
    }
} 