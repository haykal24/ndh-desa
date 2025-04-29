<?php

namespace App\Filament\Resources\BansosResource\Pages;

use App\Filament\Resources\BansosResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Penduduk;
use Illuminate\Support\Arr;
use App\Models\JenisBansos;

class CreateBansos extends CreateRecord
{
    protected static string $resource = BansosResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    // Override metode ini untuk mengisi form secara otomatis
    protected function fillForm(): void
    {
        // Panggil metode parent terlebih dahulu
        parent::fillForm();

        // Ambil parameter dari URL
        $pendudukId = request()->get('penduduk_id');
        $desaId = request()->get('id_desa');

        // Jika parameter ada, isi form dengan nilai-nilai tersebut
        if ($pendudukId && $desaId) {
            // Cari jenis bantuan yang tersedia (ambil yang pertama sebagai default)
            $defaultJenisBansosId = JenisBansos::where('is_active', true)
                ->orderBy('nama_bansos')
                ->value('id');

            $this->form->fill([
                'id_desa' => $desaId,
                'penduduk_id' => $pendudukId,
                // Tambahkan jenis_bansos_id dengan nilai default
                'jenis_bansos_id' => $defaultJenisBansosId,
                'tanggal_pengajuan' => now(),
                'status' => 'Diajukan',
                'prioritas' => 'Sedang',
                'sumber_pengajuan' => 'admin',
            ]);
        }
    }

    // Mutasi data sebelum simpan jika diperlukan
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['approved_by'] = auth()->id();
        $data['verified_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        // Catat status awal bantuan
        $this->record->addStatusHistory(
            $this->record->status,
            'Status awal saat pembuatan bantuan'
        );
    }
}