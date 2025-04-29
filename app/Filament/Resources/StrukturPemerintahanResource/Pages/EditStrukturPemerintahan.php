<?php

namespace App\Filament\Resources\StrukturPemerintahanResource\Pages;

use App\Filament\Resources\StrukturPemerintahanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditStrukturPemerintahan extends EditRecord
{
    protected static string $resource = StrukturPemerintahanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Hapus Struktur')
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

    // Override save method to handle the aparat_desa relationship properly
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        // Begin a database transaction
        DB::beginTransaction();
        
        try {
            // Get current form state
            $data = $this->form->getState();
            
            // Extract aparat_desa array before saving
            $aparatDesaIds = $data['aparat_desa'] ?? [];
            unset($data['aparat_desa']);
            
            // Update the main record without touching relationships
            $this->record->update($data);
            
            // If we have aparat IDs, sync them manually without detaching first
            if (!empty($aparatDesaIds)) {
                // Update only records not in the selection (avoid NULL values)
                $this->record->aparatDesa()
                    ->whereNotIn('id', $aparatDesaIds)
                    ->update(['struktur_pemerintahan_id' => DB::raw('(SELECT id FROM struktur_pemerintahan LIMIT 1)')]);
                
                // Then set the correct struktur_pemerintahan_id for selected records
                DB::table('aparat_desa')
                    ->whereIn('id', $aparatDesaIds)
                    ->update(['struktur_pemerintahan_id' => $this->record->id]);
            }
            
            DB::commit();
            
            if ($shouldSendSavedNotification) {
                $this->getSavedNotification()?->send();
            }
            
            if ($shouldRedirect && ($redirectUrl = $this->getRedirectUrl())) {
                $this->redirect($redirectUrl);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
} 