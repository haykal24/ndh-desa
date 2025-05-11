<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ProfilDesa;
use App\Models\LayananDesa;
use Illuminate\Http\Request;

class LayananController extends Controller
{
    public function index(Request $request)
    {
        $profilDesa = ProfilDesa::first();

        $query = LayananDesa::with(['desa', 'creator'])->latest();

        // Filter by kategori if provided
        if ($request->has('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        $layanans = $query->paginate(9);
        $layanans->appends($request->query());

        // Keep the same kategori colors
        $kategoriColors = [
            'Surat' => 'blue',
            'Kesehatan' => 'green',
            'Pendidikan' => 'yellow',
            'Sosial' => 'purple',
            'Infrastruktur' => 'red',
        ];

        return view('front.layanan', compact('profilDesa', 'layanans', 'kategoriColors'));
    }

    public function show($id)
    {
        $profilDesa = ProfilDesa::first();
        $layanan = LayananDesa::with(['desa', 'creator'])->findOrFail($id);

        // Get related layanan (same kategori, exclude current)
        $layananLainnya = LayananDesa::with(['desa'])
                          ->where('id', '!=', $layanan->id)
                          ->where('kategori', $layanan->kategori)
                          ->latest()
                          ->limit(3)
                          ->get();

        // If not enough related layanan, get some random ones
        if ($layananLainnya->count() < 3) {
            $moreLanayan = LayananDesa::with(['desa'])
                          ->where('id', '!=', $layanan->id)
                          ->where('kategori', '!=', $layanan->kategori)
                          ->inRandomOrder()
                          ->limit(3 - $layananLainnya->count())
                          ->get();

            $layananLainnya = $layananLainnya->concat($moreLanayan);
        }

        return view('front.layanan-detail', compact('layanan', 'layananLainnya', 'profilDesa'));
    }
}