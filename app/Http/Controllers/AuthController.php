<?php

namespace App\Http\Controllers;

use App\Events\UserSsoAuthenticated;
use App\Models\User;
use App\Services\UnujaPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (session()->has('sso_user')) {
            return redirect()->route('dashboard');
        }

        return view('login');
    }

    public function login(Request $request, UnujaPortalService $portalService): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Harap isi identitas resmi (NIM / NIDN / Kode Unit).',
            'password.required' => 'Harap isi kata sandi akun.',
        ]);

        $identifier = trim((string) $credentials['username']);
        $password = (string) $credentials['password'];

        $user = $this->findLocalUser($identifier);

        // Di environment lokal, izinkan provisioning otomatis dari API SSO bila user belum ada di SQLite
        if (! $user && app()->isLocal()) {
            $portalService->findUserByIdentifier($identifier);
            $user = $this->findLocalUser($identifier);
        }

        // Verifikasi password ketat dengan Hash::check tanpa bypass plaintext
        if (! $user || empty($user->password) || ! Hash::check($password, (string) $user->password)) {
            return back()->withErrors([
                'username' => 'Identitas atau kata sandi yang Anda masukkan tidak valid.',
            ])->onlyInput('username');
        }

        if (($user->status_akun ?? 'Aktif') !== 'Aktif') {
            return back()->withErrors([
                'username' => 'Akun Anda berstatus nonaktif. Silakan hubungi Helpdesk IT.',
            ])->onlyInput('username');
        }

        $user->loadMissing(['role', 'prodi', 'fakultas', 'unitKampus']);
        $roleName = $user->roleName();
        $canonicalIdentifier = trim((string) ($user->identifier ?: $user->username ?: $identifier));
        $fullName = trim((string) ($user->nama_lengkap ?: $user->name ?: $canonicalIdentifier));

        $userProfile = [
            'identifier'        => $canonicalIdentifier,
            'username'          => (string) ($user->username ?: $canonicalIdentifier),
            'nim'               => $user->nim ?? ($roleName === 'Mahasiswa' ? $canonicalIdentifier : null),
            'nidn_nip'          => $user->nidn_nip ?? ($roleName === 'Dosen' ? $canonicalIdentifier : null),
            'kode_unit'         => $user->unitKampus?->kode_unit,
            'name'              => $fullName,
            'nama_lengkap'      => $fullName,
            'role'              => $roleName,
            'prodi'             => $user->prodi?->nama_prodi,
            'fakultas'          => $user->fakultas?->nama_fakultas,
            'unit'              => $user->unitKampus?->nama_unit_kerja ?? $user->unit ?? $user->fakultas?->nama_fakultas,
            'deskripsi_layanan' => $user->unitKampus?->deskripsi_layanan,
            'tahun_angkatan'    => $user->tahun_angkatan,
            'email_kampus'      => $user->email_kampus,
            'status_akun'       => (string) ($user->status_akun ?: 'Aktif'),
        ];

        Auth::login($user);
        $request->session()->regenerate();
        session(['sso_user' => $userProfile]);

        UserSsoAuthenticated::dispatch($userProfile);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->forget('sso_user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function findLocalUser(string $identifier): ?User
    {
        if ($identifier === '' || ! Schema::hasTable('users')) {
            return null;
        }

        $hasIdentifierCol = Schema::hasColumn('users', 'identifier');

        return User::query()
            ->where(function ($query) use ($identifier, $hasIdentifierCol): void {
                $query->where('username', $identifier)
                    ->orWhere('nim', $identifier)
                    ->orWhere('nidn_nip', $identifier);

                if ($hasIdentifierCol) {
                    $query->orWhere('identifier', $identifier);
                }
            })
            ->first();
    }
}
