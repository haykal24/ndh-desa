<?php

namespace App\Filament\Resources\AparatDesaResource\Pages;

use App\Filament\Resources\AparatDesaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAparatDesa extends CreateRecord
{
    protected static string $resource = AparatDesaResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
} 