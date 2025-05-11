<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ProfilDesa;
use App\Models\Berita;
use Illuminate\Http\Request;

class BeritaController extends Controller
{
    public function index(Request $request)
    {
        $profilDesa = ProfilDesa::first();

        $query = Berita::with(['creator', 'desa'])
                      ->latest();

        // Filter by kategori if provided
        if ($request->has('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        // Ubah jumlah item per halaman menjadi 6 (dari 9)
        $berita = $query->paginate(6);

        // Simpan query string (kategori filter) saat pindah halaman pagination
        $berita->appends($request->query());

        $kategoriColors = [
            'Umum' => 'blue',
            'Pengumuman' => 'yellow',
            'Kegiatan' => 'green',
            'Infrastruktur' => 'red',
            'Kesehatan' => 'sky',
            'Pendidikan' => 'purple',
        ];

        return view('front.berita', compact('berita', 'profilDesa', 'kategoriColors'));
    }

    public function show($id)
    {
        $profilDesa = ProfilDesa::first();
        $berita = Berita::with(['creator', 'desa'])->findOrFail($id);

        // Get related posts (same category, exclude current)
        $beritaTerkait = Berita::where('id', '!=', $berita->id)
                             ->where('kategori', $berita->kategori)
                             ->latest()
                             ->limit(3)
                             ->get();

        // If not enough related posts by category, get latest posts
        if ($beritaTerkait->count() < 3) {
            $moreBerita = Berita::where('id', '!=', $berita->id)
                             ->where('kategori', '!=', $berita->kategori)
                             ->latest()
                             ->limit(3 - $beritaTerkait->count())
                             ->get();

            $beritaTerkait = $beritaTerkait->concat($moreBerita);
        }

        $kategoriColors = [
            'Umum' => 'blue',
            'Pengumuman' => 'yellow',
            'Kegiatan' => 'green',
            'Infrastruktur' => 'red',
            'Kesehatan' => 'sky',
            'Pendidikan' => 'purple',
        ];

        return view('front.berita-detail', compact('berita', 'beritaTerkait', 'profilDesa', 'kategoriColors'));
    }
}