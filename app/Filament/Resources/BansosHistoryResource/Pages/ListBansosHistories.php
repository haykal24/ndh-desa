<?php

namespace App\Filament\Resources\BansosHistoryResource\Pages;

use App\Filament\Resources\BansosHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListBansosHistories extends ListRecords
{
    protected static string $resource = BansosHistoryResource::class;

    // Tidak ada tombol create
    protected function getHeaderActions(): array
    {
        return [];
    }
}