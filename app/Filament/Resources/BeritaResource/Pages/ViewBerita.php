<?php

namespace App\Filament\Resources\BeritaResource\Pages;

use App\Filament\Resources\BeritaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBerita extends ViewRecord
{
    protected static string $resource = BeritaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
            ->icon('heroicon-o-pencil-square')
            ->label('Ubah Berita'),
            Actions\DeleteAction::make()
            ->label('Hapus Berita')
                ->icon('heroicon-o-trash')
                ->color('danger'),
        ];
    }
}