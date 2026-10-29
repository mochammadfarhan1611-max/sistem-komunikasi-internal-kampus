<?php

namespace App\Http\Controllers;

use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChatController extends Controller
{
    /**
     * Tampilkan halaman dashboard komunikasi beserta daftar kontak sivitas akademika.
     */
    public function index(): View
    {
        $currentUser = Auth::user();
        $currentUser->loadMissing(['role', 'fakultas', 'prodi', 'unitKampus']);

        $kontak = User::with(['role', 'prodi', 'fakultas', 'unitKampus'])
            ->where('id', '!=', $currentUser->id)
            ->orderBy('nama_lengkap')
            ->get();

        $tahunAktif = TahunAkademik::where('is_aktif', true)->first();

        return view('dashboard', compact('kontak', 'tahunAktif'));
    }
}

