<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ProfilDesa;
use App\Models\Umkm;
use Illuminate\Http\Request;

class UmkmController extends Controller
{
    public function index(Request $request)
    {
        $profilDesa = ProfilDesa::first();

        $query = Umkm::where('is_verified', true)->latest();

        // Filter by kategori if provided
        if ($request->has('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        $umkm = $query->paginate(12);

        // Simpan query string untuk pagination
        $umkm->appends($request->query());

        return view('front.umkm', compact('umkm', 'profilDesa'));
    }

    public function show($id)
    {
        $profilDesa = ProfilDesa::first();
        $umkm = Umkm::with(['penduduk', 'desa'])->where('is_verified', true)->findOrFail($id);

        // Get related UMKM (same kategori, exclude current)
        $umkmLainnya = Umkm::where('id', '!=', $umkm->id)
                         ->where('is_verified', true)
                         ->where('kategori', $umkm->kategori)
                         ->latest()
                         ->limit(4)
                         ->get();

        // If not enough related UMKM, get some random ones
        if ($umkmLainnya->count() < 4) {
            $moreUmkm = Umkm::where('id', '!=', $umkm->id)
                         ->where('is_verified', true)
                         ->where('kategori', '!=', $umkm->kategori)
                         ->inRandomOrder()
                         ->limit(4 - $umkmLainnya->count())
                         ->get();

            $umkmLainnya = $umkmLainnya->concat($moreUmkm);
        }

        return view('front.umkm-detail', compact('umkm', 'umkmLainnya', 'profilDesa'));
    }
}