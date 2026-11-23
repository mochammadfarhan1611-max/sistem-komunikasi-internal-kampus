<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\GroupController;
use App\Http\Middleware\EnsureSsoAuthenticated;
use App\Models\Group;
use App\Models\GroupMember;
use App\Services\UnujaPortalService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

// Rute Autentikasi
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rute Dashboard & Chat Real-Time (Personal & Grup KRS)
Route::middleware(EnsureSsoAuthenticated::class)->group(function (): void {
    Route::get('/dashboard', [ChatController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/contacts-summary', [ChatController::class, 'contactsSummary'])->name('chat.contacts');
    Route::post('/dashboard/sync-groups', [GroupController::class, 'syncKrs'])->name('groups.sync');
    Route::get('/api/search', [\App\Http\Controllers\ChatController::class, 'search'])->name('api.search');

    Route::get('/dashboard/{receiver_id}', [ChatController::class, 'show'])->name('chat.show');
    Route::get('/dashboard/{receiver_id}/messages', [ChatController::class, 'fetchMessages'])->name('chat.messages');
    Route::post('/dashboard/{receiver_id}', [ChatController::class, 'sendMessage'])->name('chat.send');
});

// Rute Development (Hanya aktif saat APP_ENV=local; otomatis 404 di production/testing)
if (app()->isLocal()) {
    // Rute Pengujian Koneksi SSO
    Route::get('/test-sso', function (UnujaPortalService $portalService) {
        return response()->json([
            'status'   => 'Terhubung ke SSO UNUJA & Redis',
            'data_url' => $portalService->getDataUrl(),
        ]);
    });

    // RUTE DEV LOGIN (Simulasi Login / Impersonation & Just-In-Time Provisioning)
    Route::get('/dev/login/{identifier}/{role}', function ($identifier, $role, App\Services\UnujaPortalService $portalService) {
        $identifier = trim((string) $identifier);
        $role = strtolower(trim((string) $role));
        $isLayanan = in_array($role, ['layanan', 'staff_unit', 'unit'], true) || str_starts_with($identifier, 'unit_');

        if ($isLayanan) {
            $existingProfile = $portalService->findUserByIdentifier($identifier);
            $serviceName = $existingProfile['nama_lengkap']
                ?? $portalService->getLocalUserName($identifier)
                ?? 'Unit Layanan Kampus ('.strtoupper(str_replace('unit_', '', $identifier)).')';
            $unitLabel = $existingProfile['unit'] ?? $serviceName;

            $portalService->saveLocalUserProfile($identifier, $serviceName, 'Staff_Unit', $unitLabel, '');

            $profile = [
                'identifier'   => $identifier,
                'username'     => $identifier,
                'role'         => 'Staff_Unit',
                'name'         => $serviceName,
                'nama_lengkap' => $serviceName,
                'prodi'        => null,
                'fakultas'     => null,
                'unit'         => $unitLabel,
                'status_akun'  => 'Aktif',
            ];

            session([
                'sso_user'   => $profile,
                'krs_synced' => true,
            ]);

            return redirect('/dashboard');
        }

        // 1. Penarikan jadwal KRS secara real-time saat login (sekaligus mengekstrak nama asli dari KRS API)
        $krsData = $portalService->getKrsData($identifier, $role);

        // 2. Tentukan Nama Profil dengan Anti-Overwrite Guard (Prioritas: DB Lokal -> API -> KRS Payload)
        $realName = $portalService->getLocalUserName($identifier)
            ?? $portalService->getRealProfileName($identifier, $role);

        if (\App\Services\UnujaPortalService::isPlaceholderName($realName, $identifier) && ! empty($krsData[0]['mahasiswa_name'])) {
            $realName = (string) $krsData[0]['mahasiswa_name'];
        }

        $roleLabel = ($role === 'dosen') ? 'Dosen' : 'Mahasiswa';

        // Simpan permanen ke tabel users & group_members lokal (dengan Anti-Overwrite Guard & Validasi NIM/NIDN)
        if (! \App\Services\UnujaPortalService::isPlaceholderName($realName, $identifier)) {
            $portalService->saveLocalUserProfile($identifier, $realName, $roleLabel, 'Fakultas Teknik', 'Teknik Informatika');
        }
        $finalName = $portalService->getLocalUserName($identifier) ?? $realName;

        // 3. Daftarkan Payload Profil
        $profile = [
            'identifier'   => $identifier,
            'username'     => $identifier,
            'role'         => $roleLabel,
            'name'         => $finalName,
            'nama_lengkap' => $finalName,
            'prodi'        => 'Teknik Informatika',
            'fakultas'     => 'Fakultas Teknik',
            'unit'         => 'Fakultas Teknik',
            'status_akun'  => 'Aktif',
        ];

        // 4. Sinkronisasi Grup Otomatis (Just-In-Time Provisioning)
        $hasUserNameCol = \Illuminate\Support\Facades\Schema::hasColumn('group_members', 'user_name');

        foreach ($krsData as $item) {
            $existingGroup = \App\Models\Group::where('name', $item['group_name'])
                ->where('kode_tahun_akademik', $item['tahun_akademik'])
                ->first();

            $dosenId = ! empty($item['dosen_identifier'])
                ? (string) $item['dosen_identifier']
                : (($existingGroup && ! empty($existingGroup->creator_identifier) && $existingGroup->creator_identifier !== '0711028801')
                    ? (string) $existingGroup->creator_identifier
                    : '0701098506');

            $dosenName = $portalService->getLocalUserName($dosenId)
                ?? $portalService->getRealProfileName($dosenId, 'dosen');
            $portalService->saveLocalUserProfile($dosenId, $dosenName, 'Dosen');

            $group = \App\Models\Group::firstOrCreate(
                ['name' => $item['group_name'], 'kode_tahun_akademik' => $item['tahun_akademik']],
                ['creator_identifier' => $dosenId]
            );

            // Pastikan dosen pengampu asli terdaftar sebagai admin grup
            $adminAttrs = ['role' => 'admin'];
            if ($hasUserNameCol && ! \App\Services\UnujaPortalService::isPlaceholderName($dosenName, $dosenId)) {
                $adminAttrs['user_name'] = $dosenName;
            }
            \App\Models\GroupMember::updateOrCreate(
                ['group_id' => $group->id, 'user_identifier' => $dosenId],
                $adminAttrs
            );

            // Masukkan pengguna yang login ke dalam grup hanya jika memiliki nama asli yang valid
            if (! \App\Services\UnujaPortalService::isPlaceholderName($finalName, $identifier)) {
                $memberAttrs = ['role' => ($role === 'dosen') ? 'admin' : 'member'];
                if ($hasUserNameCol) {
                    $memberAttrs['user_name'] = $finalName;
                }

                \App\Models\GroupMember::updateOrCreate(
                    ['group_id' => $group->id, 'user_identifier' => $identifier],
                    $memberAttrs
                );
            }
        }

        // 5. Mendaftarkan Sesi & Masuk ke Aplikasi
        session([
            'sso_user'   => $profile,
            'krs_synced' => true,
        ]);

        return redirect('/dashboard');
    })->name('dev.login');
}
