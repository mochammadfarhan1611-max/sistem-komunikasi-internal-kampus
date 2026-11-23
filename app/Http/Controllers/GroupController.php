<?php

namespace App\Http\Controllers;

use App\Services\UnujaPortalService;
use Illuminate\Http\JsonResponse;

class GroupController extends Controller
{
    /**
     * Sinkronisasi grup KRS berdasarkan profil SSO pengguna.
     *
     * @param  array<string, mixed>  $profile
     */
    public function syncKrsForProfile(array $profile, UnujaPortalService $portalService): int
    {
        $user = $portalService->toUserObject($profile);

        return $portalService->syncUserKrsGroups($user);
    }

    public function syncKrs(UnujaPortalService $portalService): JsonResponse
    {
        $profile = session('sso_user');
        if (! $profile) {
            return response()->json(['error' => 'Sesi tidak valid'], 401);
        }

        $groupsCount = $this->syncKrsForProfile($profile, $portalService);

        if ($groupsCount === 0) {
            return response()->json(['message' => 'Tidak ada data KRS/Mata Kuliah aktif saat ini.'], 200);
        }

        return response()->json([
            'message'      => 'Grup KRS berhasil disinkronisasi.',
            'groups_count' => $groupsCount,
        ]);
    }
}
