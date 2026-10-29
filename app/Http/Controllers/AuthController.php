<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Tampilkan halaman login portal.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('login');
    }

    /**
     * Proses autentikasi username & password ke tabel users.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Harap isi identitas resmi (NIM / NIDN / Kode Unit / Username).',
            'password.required' => 'Harap isi kata sandi akun.',
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if ($user && $user->status_akun !== 'Aktif') {
            $this->catatLoginLog($user->id, $credentials['username'], 'Gagal', $request);

            return back()->withErrors([
                'username' => 'Akun Anda berstatus nonaktif. Silakan hubungi Helpdesk IT.',
            ])->onlyInput('username');
        }

        if (Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();

            $this->catatLoginLog(Auth::id(), $credentials['username'], 'Berhasil', $request);

            return redirect()->intended(route('dashboard'));
        }

        $this->catatLoginLog($user?->id, $credentials['username'], 'Gagal', $request);

        return back()->withErrors([
            'username' => 'Identitas atau kata sandi yang Anda masukkan salah.',
        ])->onlyInput('username');
    }

    /**
     * Keluar dari sesi pengguna aktif.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Simpan riwayat percobaan autentikasi ke tabel login_logs.
     */
    private function catatLoginLog(?int $userId, string $username, string $status, Request $request): void
    {
        LoginLog::create([
            'user_id' => $userId,
            'username_dicoba' => Str::limit($username, 50, ''),
            'status_login' => $status,
            'info_perangkat_session' => $request->userAgent() ? Str::limit($request->userAgent(), 250, '') : null,
            'ip_address' => $request->ip(),
        ]);
    }
}

