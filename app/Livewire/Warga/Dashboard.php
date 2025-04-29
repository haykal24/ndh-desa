<?php

namespace App\Livewire\Warga;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    public $penduduk;
    public $bansos;
    public $pengaduan;
    public $umkm;
    public $verifikasiPending;

    public function mount()
    {
        $user = Auth::user();
        $this->penduduk = $user->penduduk;

        $this->verifikasiPending = \App\Models\VerifikasiPenduduk::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($this->penduduk) {
            $this->bansos = $this->penduduk->bansos ?? collect();
            $this->pengaduan = $this->penduduk->pengaduan ?? collect();
            $this->umkm = $this->penduduk->umkm ?? collect();
        }
    }

    public function render()
    {
        return view('livewire.warga.dashboard', [
            'penduduk' => $this->penduduk,
            'pengaduan' => $this->pengaduan,
            'bansos' => $this->bansos,
            'umkm' => $this->umkm,
            'layanan' => $this->layanan ?? collect(),
            'user' => auth()->user(),
        ])
            ->layout('layouts.app');
    }
}