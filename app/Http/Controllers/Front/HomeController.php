<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ProfilDesa;
use App\Models\Berita;
use App\Models\Umkm;
use App\Models\StrukturPemerintahan;

class HomeController extends Controller
{
    public function index()
    {
        $profilDesa = ProfilDesa::first();
        $strukturPemerintahan = StrukturPemerintahan::with('profilDesa')->first();
        $beritaTerbaru = Berita::latest()->take(12)->get();

        // Ubah query UMKM sesuai dengan struktur tabel yang ada
        $umkmUnggulan = Umkm::where('is_verified', true)
                            ->latest()
                            ->take(12)
                            ->get();

        // Tambahkan data statistik untuk counters
        $statistik = [
            'penduduk' => \App\Models\Penduduk::count(),
            'umkm' => \App\Models\Umkm::where('is_verified', true)->count(),
            'berita' => \App\Models\Berita::count(),
            'layanan' => \App\Models\LayananDesa::count()
        ];

        return view('front.home', compact(
            'profilDesa',
            'strukturPemerintahan',
            'beritaTerbaru',
            'umkmUnggulan',
            'statistik'
        ));
    }
}