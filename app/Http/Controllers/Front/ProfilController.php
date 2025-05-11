<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\ProfilDesa;
use App\Models\StrukturPemerintahan;

class ProfilController extends Controller
{
    public function index()
    {
        $profilDesa = ProfilDesa::first();
        $strukturPemerintahan = StrukturPemerintahan::with(['profilDesa', 'aparatDesa'])
            ->first();

        return view('front.profil', compact('profilDesa', 'strukturPemerintahan'));
    }
}