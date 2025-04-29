<?php

namespace App\Filament\Resources\AparatDesaResource\Pages;

use App\Filament\Resources\AparatDesaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAparatDesa extends EditRecord
{
    protected static string $resource = AparatDesaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Hapus Aparat')
                ->icon('heroicon-o-trash'),
            Actions\ForceDeleteAction::make()
                ->icon('heroicon-o-trash'),
            Actions\RestoreAction::make()
                ->icon('heroicon-o-arrow-path'),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}