<?php

namespace App\Listeners;

use App\Events\UserSsoAuthenticated;
use App\Services\UnujaPortalService;
use Illuminate\Support\Facades\Log;

class SyncUserKrsGroups
{
    public function __construct(
        public UnujaPortalService $portalService,
    ) {}

    /**
     * Jalankan sinkronisasi direktori & grup kelas KRS secara diam-diam saat pengguna berhasil login SSO.
     */
    public function handle(UserSsoAuthenticated $event): void
    {
        try {
            $user = $this->portalService->toUserObject($event->userProfile);
            $this->portalService->syncUserKrsGroups($user);
        } catch (\Throwable $e) {
            Log::error('Gagal sinkronisasi otomatis Grup KRS saat login: '.$e->getMessage());
        }
    }
}
