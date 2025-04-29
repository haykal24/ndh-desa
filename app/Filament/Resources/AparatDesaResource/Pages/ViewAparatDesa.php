<?php

namespace App\Filament\Resources\AparatDesaResource\Pages;

use App\Filament\Resources\AparatDesaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAparatDesa extends ViewRecord
{
    protected static string $resource = AparatDesaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Ubah Aparat')
                ->icon('heroicon-o-pencil-square'),
            Actions\DeleteAction::make()
                ->label('Hapus Aparat')
                ->icon('heroicon-o-trash'),
            Actions\ForceDeleteAction::make()
                ->icon('heroicon-o-trash'),
            Actions\RestoreAction::make()
                ->icon('heroicon-o-arrow-path'),
        ];
    }
} 