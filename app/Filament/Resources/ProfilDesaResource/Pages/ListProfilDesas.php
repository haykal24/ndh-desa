<?php

namespace App\Filament\Resources\ProfilDesaResource\Pages;

use App\Filament\Resources\ProfilDesaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ListProfilDesas extends ListRecords
{
    protected static string $resource = ProfilDesaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label('Tambah Profil Desa')

        ];
    }
}