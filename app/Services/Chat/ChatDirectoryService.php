<?php

namespace App\Services\Chat;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use App\Models\Message;
use App\Models\TahunAkademik;
use App\Models\User;
use App\Services\UnujaPortalService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatDirectoryService
{
    public function __construct(
        private UnujaPortalService $portalService,
    ) {}

    /**
     * Sinkronisasi pengguna dari Session SSO ke Auth in-memory dengan Anti-Overwrite Guard dari Database Lokal.
     */
    public function currentSsoUser(): User
    {
        $profile = session('sso_user');
        abort_if(! $profile, 401);

        $identifier = trim((string) ($profile['identifier'] ?? $profile['username'] ?? ''));
        $sessionName = trim((string) ($profile['nama_lengkap'] ?? $profile['name'] ?? ''));
        $role = (string) ($profile['role'] ?? 'Mahasiswa');

        if ($identifier !== '') {
            $localRealName = $this->portalService->getLocalUserName($identifier);

            if ($localRealName !== null && ! UnujaPortalService::isPlaceholderName($localRealName, $identifier)) {
                if (UnujaPortalService::isPlaceholderName($sessionName, $identifier)) {
                    $profile['name'] = $localRealName;
                    $profile['nama_lengkap'] = $localRealName;
                    session(['sso_user' => $profile]);
                } else {
                    $this->portalService->saveLocalUserProfile($identifier, $sessionName, $role);
                }
            } elseif (! UnujaPortalService::isPlaceholderName($sessionName, $identifier)) {
                $this->portalService->saveLocalUserProfile($identifier, $sessionName, $role);
            } else {
                $apiRealName = $this->portalService->getRealProfileName($identifier, strtolower($role));
                if (! UnujaPortalService::isPlaceholderName($apiRealName, $identifier)) {
                    $profile['name'] = $apiRealName;
                    $profile['nama_lengkap'] = $apiRealName;
                    session(['sso_user' => $profile]);
                }
            }
        }

        $userObj = $this->portalService->toUserObject($profile);
        Auth::setUser($userObj);

        return $userObj;
    }

    /**
     * Pastikan direktori dan Grup Kelas KRS pengguna sudah tersinkronisasi secara otomatis (Zero-Click).
     */
    public function ensureUserKrsSynced(User $currentUser): void
    {
        $userId = (string) $currentUser->id;
        $role = strtolower($currentUser->roleName());

        $needsSync = ! session('krs_synced');

        if (! $needsSync && in_array($role, ['mahasiswa', 'dosen'], true) && Schema::hasTable('groups')) {
            $groupCount = Group::whereHas('members', function (Builder $query) use ($userId): void {
                $query->where('user_identifier', $userId);
            })->count();

            if ($groupCount < 3) {
                $needsSync = true;
            }
        }

        if ($needsSync) {
            $this->portalService->syncUserKrsGroups($currentUser);
        }
    }

    /**
     * Periksa apakah identifier target merupakan ID grup (memiliki prefix "group_").
     */
    public function isGroupIdentifier(string $receiverId): bool
    {
        return str_starts_with($receiverId, 'group_');
    }

    /**
     * Ekstrak ID numerik tabel groups dari string "group_{id}".
     */
    public function extractGroupId(string $receiverId): int
    {
        return (int) Str::after($receiverId, 'group_');
    }

    /**
     * Pastikan grup ada dan pengguna saat ini terdaftar sebagai anggota grup tersebut.
     */
    public function findAuthorizedGroup(int $groupId, string $userId): Group
    {
        $group = Group::with('members')->withCount('members')->find($groupId);
        abort_if(! $group, 404, 'Grup Kelas tidak ditemukan.');

        $isMember = $group->members()->where('user_identifier', $userId)->exists();
        abort_if(! $isMember, 403, 'Anda bukan anggota grup kelas ini.');

        return $group;
    }

    /**
     * Bangun objek User in-memory untuk merepresentasikan Grup Kelas agar kompatibel dengan seluruh komponen Blade.
     */
    public function buildGroupReceiverObject(Group $group): User
    {
        $group->loadMissing('members');
        $membersCount = (int) ($group->members_count ?? $group->members->count());
        $groupType = strtolower(trim((string) ($group->type ?? 'kelas'))) ?: 'kelas';
        $isPerwalian = $groupType === 'perwalian';

        $semesterLabel = $isPerwalian
            ? 'Perwalian Akademik'
            : ($group->kode_tahun_akademik
                ? Group::formatKodeSemester((string) $group->kode_tahun_akademik)
                : 'Grup Sistem');
        $groupKey = 'group_'.$group->id;

        $receiver = $this->portalService->toUserObject([
            'identifier'   => $groupKey,
            'username'     => $semesterLabel,
            'name'         => $group->name,
            'nama_lengkap' => $group->name,
            'role'         => 'Group',
            'prodi'        => 'Teknik Informatika',
            'fakultas'     => $membersCount.' Anggota',
            'unit'         => $isPerwalian ? 'Grup Perwalian & Bimbingan KRS' : 'Grup Kelas Perkuliahan',
            'status_akun'  => 'Aktif',
        ]);

        $receiver->forceFill([
            'id'                  => $groupKey,
            'identifier'          => $groupKey,
            'username'            => $semesterLabel,
            'nama_lengkap'        => $group->name,
            'status_akun'         => 'Aktif',
            'is_group'            => true,
            'group_id'            => $group->id,
            'group_type'          => $groupType,
            'angkatan'            => $group->angkatan ?? null,
            'members_count'       => $membersCount,
            'kode_tahun_akademik' => $group->kode_tahun_akademik,
        ]);

        $receiver->setRelation('members', $group->members);
        $receiver->setAttribute('members', $group->members);

        return $receiver;
    }

    /**
     * Resolusi target grup aktif beserta daftar anggotanya yang sudah ter-resolusi & terurut (Admin/Dosen di atas, lalu A-Z).
     *
     * @return array{group: Group, groupMembers: SupportCollection<int, GroupMember>, receiver: User, directory: array<string, array<string, mixed>>}
     */
    public function resolveGroupChatTarget(int $groupId, User $currentUser): array
    {
        $userId = (string) $currentUser->id;
        $group = $this->findAuthorizedGroup($groupId, $userId);

        // Sinkronisasi otomatis nama asli seluruh anggota grup dari DB Lokal / API Kampus UNUJA
        $this->portalService->syncClassmatesFromApi($group);
        $directory = $this->portalService->getDirectoryContacts();
        $group->load('members');

        $groupMembers = $group->members
            ->each(function (GroupMember $member) use ($directory, $currentUser): void {
                $identifier = (string) $member->user_identifier;
                $profile = $this->resolveSenderProfile($identifier, $directory, $currentUser);
                $resolvedName = (string) ($profile['nama_lengkap'] ?? $profile['name'] ?? $member->user_name ?? $identifier);
                $member->setAttribute('user_name', $resolvedName);
            })
            ->sortBy(function (GroupMember $member): string {
                $isAdmin = strtolower((string) $member->role) === 'admin' ? '0' : '1';

                return $isAdmin.'_'.strtolower((string) ($member->user_name ?? $member->user_identifier));
            })
            ->values();

        $receiver = $this->buildGroupReceiverObject($group);
        $receiver->setRelation('members', $groupMembers);
        $receiver->setAttribute('members', $groupMembers);

        return [
            'group'        => $group,
            'groupMembers' => $groupMembers,
            'receiver'     => $receiver,
            'directory'    => $directory,
        ];
    }

    /**
     * Resolusi target chat pribadi (DM) menjadi objek User in-memory.
     */
    public function resolveDirectReceiver(string $receiverId): User
    {
        $receiverProfile = $this->portalService->findUserByIdentifier($receiverId);
        abort_if(! $receiverProfile, 404, 'Kontak tidak ditemukan di Portal SSO.');

        $receiver = $this->portalService->toUserObject($receiverProfile);
        $receiver->setAttribute('is_group', false);

        return $receiver;
    }

    /**
     * Resolusi profil pengirim dengan prioritas Database Lokal -> Direktori -> Sesi Aktif (Anti-Overwrite Guard).
     *
     * @param  array<string, array<string, mixed>>  $directory
     * @return array<string, mixed>
     */
    public function resolveSenderProfile(string $senderIdentifier, array $directory, ?User $currentUser = null): array
    {
        $senderIdentifier = trim($senderIdentifier);
        $localRealName = $this->portalService->getLocalUserName($senderIdentifier);

        if (isset($directory[$senderIdentifier])) {
            $profile = $directory[$senderIdentifier];
            if ($localRealName !== null && ! UnujaPortalService::isPlaceholderName($localRealName, $senderIdentifier)) {
                $profile['name'] = $localRealName;
                $profile['nama_lengkap'] = $localRealName;
            }

            return $profile;
        }

        if ($currentUser && (string) $currentUser->id === $senderIdentifier) {
            $bestName = $localRealName ?? (string) $currentUser->nama_lengkap;

            return [
                'identifier'   => $senderIdentifier,
                'username'     => $senderIdentifier,
                'name'         => $bestName,
                'nama_lengkap' => $bestName,
                'role'         => $currentUser->roleName(),
                'status_akun'  => 'Aktif',
            ];
        }

        $isDosen = UnujaPortalService::isDosenIdentifier($senderIdentifier);
        $resolvedName = $localRealName
            ?? $this->portalService->getRealProfileName($senderIdentifier, $isDosen ? 'dosen' : 'mahasiswa');

        return [
            'identifier'   => $senderIdentifier,
            'username'     => $senderIdentifier,
            'name'         => $resolvedName,
            'nama_lengkap' => $resolvedName,
            'role'         => $isDosen ? 'Dosen' : 'Mahasiswa',
            'status_akun'  => 'Aktif',
        ];
    }

    /**
     * Bangun ringkasan percakapan per kontak (Personal & Grup) berdasarkan identifier string.
     */
    public function getContactsSummary(string $userId): SupportCollection
    {
        $summaries = collect();

        // 1. Ringkasan Pesan Personal
        $lastMessageIds = Message::query()
            ->selectRaw('CASE WHEN sender_identifier = ? THEN receiver_identifier ELSE sender_identifier END AS contact_id', [$userId])
            ->selectRaw('MAX(id) AS last_message_id')
            ->where(function (Builder $query) use ($userId): void {
                $query->where('sender_identifier', $userId)->orWhere('receiver_identifier', $userId);
            })
            ->whereNotNull('receiver_identifier')
            ->groupBy('contact_id')
            ->pluck('last_message_id', 'contact_id');

        if ($lastMessageIds->isNotEmpty()) {
            $lastMessages = Message::whereIn('id', $lastMessageIds->values())->get()->keyBy('id');

            $unreadCounts = Message::query()
                ->selectRaw('sender_identifier, COUNT(*) AS total')
                ->where('receiver_identifier', $userId)
                ->where('is_read', false)
                ->groupBy('sender_identifier')
                ->pluck('total', 'sender_identifier');

            foreach ($lastMessageIds as $contactId => $lastMessageId) {
                $message = $lastMessages->get((int) $lastMessageId);
                if (! $message) {
                    continue;
                }

                $contactKey = (string) $contactId;
                $summaries->put($contactKey, [
                    'contact_id'   => $contactKey,
                    'unread_count' => (int) ($unreadCounts[$contactKey] ?? 0),
                    'last_message' => $this->formatPesanTerakhir($message, $userId),
                ]);
            }
        }

        // 2. Ringkasan Pesan Grup KRS & Perwalian yang diikuti pengguna (hitung unread_count nyata dari last_read_message_id)
        if (Schema::hasTable('groups') && Schema::hasTable('group_messages')) {
            $memberRecords = GroupMember::query()
                ->where('user_identifier', $userId)
                ->get(Schema::hasColumn('group_members', 'last_read_message_id') ? ['group_id', 'last_read_message_id'] : ['group_id'])
                ->keyBy('group_id');

            $userGroupIds = $memberRecords->keys();

            if ($userGroupIds->isNotEmpty()) {
                $lastGroupMessageIds = GroupMessage::query()
                    ->selectRaw('group_id, MAX(id) AS last_message_id')
                    ->whereIn('group_id', $userGroupIds)
                    ->where('body', 'not like', 'Selamat datang di %')
                    ->groupBy('group_id')
                    ->pluck('last_message_id', 'group_id');

                if ($lastGroupMessageIds->isNotEmpty()) {
                    $lastGroupMessages = GroupMessage::whereIn('id', $lastGroupMessageIds->values())->get()->keyBy('id');
                    $directory = $this->portalService->getDirectoryContacts();
                    $hasLastReadCol = Schema::hasColumn('group_members', 'last_read_message_id');

                    foreach ($lastGroupMessageIds as $groupId => $lastMsgId) {
                        $groupMsg = $lastGroupMessages->get((int) $lastMsgId);
                        if (! $groupMsg) {
                            continue;
                        }

                        $unreadGroupCount = 0;
                        if ($hasLastReadCol) {
                            $memberRecord = $memberRecords->get($groupId);
                            $lastReadId = $memberRecord?->last_read_message_id;
                            if ($lastReadId !== null) {
                                $unreadGroupCount = GroupMessage::query()
                                    ->where('group_id', $groupId)
                                    ->where('id', '>', (int) $lastReadId)
                                    ->where('sender_identifier', '!=', $userId)
                                    ->where('body', 'not like', 'Selamat datang di %')
                                    ->count();
                            }
                        }

                        $groupKey = 'group_'.$groupId;
                        $summaries->put($groupKey, [
                            'contact_id'   => $groupKey,
                            'unread_count' => $unreadGroupCount,
                            'last_message' => $this->formatPesanTerakhirGrup($groupMsg, $userId, $directory),
                        ]);
                    }
                }
            }
        }

        return $summaries->sortByDesc(function (array $ringkasan): string {
            return ($ringkasan['last_message']['timestamp'] ?? '').'_'.str_pad((string) ($ringkasan['last_message']['id'] ?? 0), 10, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Pencarian AJAX & Lazy-Load Tab Kategori (Dosen / Mahasiswa) di Sidebar dengan pengurutan Top Relevance Match.
     *
     * @return array<string, mixed>
     */
    public function searchContacts(
        User $currentUser,
        string $keyword,
        string $roleFilter,
        int $offset,
        int $limit
    ): array {
        $userId = (string) $currentUser->id;
        $ringkasanKontak = $this->getContactsSummary($userId);

        // Jika keyword kosong:
        // - Untuk tab "Dosen" atau "Mahasiswa", gunakan metode terpadu getCategoryContactsPaginated() (identik 100% dengan server-side render).
        // - Untuk tab "all", "Group", atau "Staff_Unit", kembalikan daftar percakapan aktif & grup bawaan sidebar.
        if ($keyword === '') {
            if (in_array($roleFilter, ['Dosen', 'Mahasiswa'], true)) {
                $page = $this->getCategoryContactsPaginated($userId, $roleFilter, $ringkasanKontak, $offset, $limit);
                $formatted = $page['items']
                    ->map(fn (User $contact): array => $this->formatKontakSidebarItem($contact))
                    ->values();

                return [
                    'keyword'  => '',
                    'role'     => $roleFilter,
                    'offset'   => $offset,
                    'limit'    => $limit,
                    'total'    => $page['total'],
                    'has_more' => $page['has_more'],
                    'groups'   => [],
                    'members'  => [],
                    'results'  => $formatted,
                ];
            }

            $allItems = $this->getBaseContacts($currentUser, null, 'all', $ringkasanKontak)
                ->map(fn (User $contact): array => $this->formatKontakSidebarItem($contact))
                ->values();

            $filteredForCount = ($roleFilter !== '' && strtolower($roleFilter) !== 'all')
                ? $allItems->filter(fn (array $item): bool => strcasecmp((string) ($item['role'] ?? ''), $roleFilter) === 0)->values()
                : $allItems;

            return [
                'keyword'  => '',
                'role'     => $roleFilter,
                'offset'   => 0,
                'limit'    => $allItems->count(),
                'total'    => $filteredForCount->count(),
                'has_more' => false,
                'groups'   => [],
                'members'  => [],
                'results'  => $allItems,
            ];
        }

        // 1. Cari di tabel groups HANYA jika NAMA MATA KULIAH atau KODE KELAS / SEMESTER mengandung keyword (dan user adalah anggotanya)
        $matchedGroups = Group::withCount('members')
            ->whereHas('members', function (Builder $memberQuery) use ($userId): void {
                $memberQuery->where('user_identifier', $userId);
            })
            ->where(function (Builder $query) use ($keyword): void {
                $query->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('kode_tahun_akademik', 'like', '%'.$keyword.'%');
            })
            ->get();

        // 2. Cari di tabel users lokal berdasarkan name, nama_lengkap, atau identifier (NIM/NIDN)
        $matchedUsers = collect();
        if (Schema::hasTable('users')) {
            $matchedUsers = User::query()
                ->where(function (Builder $query) use ($keyword): void {
                    $query->where('nama_lengkap', 'like', '%'.$keyword.'%')
                        ->orWhere('username', 'like', '%'.$keyword.'%')
                        ->orWhere('nim', 'like', '%'.$keyword.'%')
                        ->orWhere('nidn_nip', 'like', '%'.$keyword.'%');
                    if (Schema::hasColumn('users', 'name')) {
                        $query->orWhere('name', 'like', '%'.$keyword.'%');
                    }
                    if (Schema::hasColumn('users', 'identifier')) {
                        $query->orWhere('identifier', 'like', '%'.$keyword.'%');
                    }
                })
                ->get();
        }

        // 3. Cari di tabel group_members berdasarkan user_identifier atau user_name
        $matchedMembers = GroupMember::query()
            ->where(function (Builder $query) use ($keyword): void {
                $query->where('user_identifier', 'like', '%'.$keyword.'%');
                if (Schema::hasColumn('group_members', 'user_name')) {
                    $query->orWhere('user_name', 'like', '%'.$keyword.'%');
                }
            })
            ->get();

        // 4. Jika belum ditemukan di DB lokal (atau hasil user masih sedikit), cari ke API Kampus UNUJA lalu simpan ke DB lokal
        if ($matchedUsers->count() < 3 && strlen($keyword) >= 3) {
            $this->portalService->searchUsersFromApi($keyword);

            if (Schema::hasTable('users')) {
                $matchedUsers = User::query()
                    ->where(function (Builder $query) use ($keyword): void {
                        $query->where('nama_lengkap', 'like', '%'.$keyword.'%')
                            ->orWhere('username', 'like', '%'.$keyword.'%')
                            ->orWhere('nim', 'like', '%'.$keyword.'%')
                            ->orWhere('nidn_nip', 'like', '%'.$keyword.'%');
                        if (Schema::hasColumn('users', 'name')) {
                            $query->orWhere('name', 'like', '%'.$keyword.'%');
                        }
                        if (Schema::hasColumn('users', 'identifier')) {
                            $query->orWhere('identifier', 'like', '%'.$keyword.'%');
                        }
                    })
                    ->get();
            }
        }

        $directory = $this->portalService->getDirectoryContacts();
        $candidates = [];
        $addedIds = [];

        // A. Kumpulkan kandidat dari tabel users lokal
        foreach ($matchedUsers as $dbUser) {
            $idStr = trim((string) ($dbUser->identifier ?: $dbUser->username));
            if ($idStr === '' || $idStr === $userId || isset($addedIds[$idStr])) {
                continue;
            }

            $realName = trim((string) ($dbUser->name ?: $dbUser->nama_lengkap));
            if (UnujaPortalService::isPlaceholderName($realName, $idStr)) {
                $realName = $this->portalService->getRealProfileName($idStr, strtolower($dbUser->roleName()));
            }

            $profile = $directory[$idStr] ?? [
                'identifier'   => $idStr,
                'username'     => $idStr,
                'name'         => $realName,
                'nama_lengkap' => $realName,
                'role'         => $dbUser->roleName(),
                'prodi'        => 'Teknik Informatika',
                'fakultas'     => 'Fakultas Teknik',
                'unit'         => (string) ($dbUser->unit ?: 'Fakultas Teknik'),
                'status_akun'  => 'Aktif',
            ];
            $profile['name'] = $realName;
            $profile['nama_lengkap'] = $realName;

            $userObj = $this->portalService->toUserObject($profile);
            $ringkasan = $ringkasanKontak->get($idStr);

            $userObj->setAttribute('is_group', false);
            $userObj->setAttribute('unread_count', $ringkasan['unread_count'] ?? 0);
            $userObj->setAttribute('last_message', $ringkasan['last_message'] ?? null);

            $item = $this->formatKontakSidebarItem($userObj);
            $score = $this->hitungSkorRelevansiPencarian($item, $keyword);
            if ($score > 0) {
                $item['_relevance_score'] = $score;
                $candidates[] = $item;
                $addedIds[$idStr] = true;
            }
        }

        // B. Kumpulkan kandidat kontak personal dari tabel group_members
        foreach ($matchedMembers as $member) {
            $memberIdentifier = trim((string) $member->user_identifier);
            if ($memberIdentifier === '' || $memberIdentifier === $userId || isset($addedIds[$memberIdentifier])) {
                continue;
            }

            $roleType = strtolower((string) $member->role) === 'admin' ? 'dosen' : 'mahasiswa';
            $realName = $this->portalService->getLocalUserName($memberIdentifier)
                ?? $this->portalService->getRealProfileName($memberIdentifier, $roleType);

            $profile = $directory[$memberIdentifier] ?? [
                'identifier'   => $memberIdentifier,
                'username'     => $memberIdentifier,
                'name'         => $realName,
                'nama_lengkap' => $realName,
                'role'         => $roleType === 'dosen' ? 'Dosen' : 'Mahasiswa',
                'prodi'        => 'Teknik Informatika',
                'fakultas'     => 'Fakultas Teknik',
                'status_akun'  => 'Aktif',
            ];
            $profile['name'] = $realName;
            $profile['nama_lengkap'] = $realName;

            $userObj = $this->portalService->toUserObject($profile);
            $ringkasan = $ringkasanKontak->get($memberIdentifier);

            $userObj->setAttribute('is_group', false);
            $userObj->setAttribute('unread_count', $ringkasan['unread_count'] ?? 0);
            $userObj->setAttribute('last_message', $ringkasan['last_message'] ?? null);

            $item = $this->formatKontakSidebarItem($userObj);
            $score = $this->hitungSkorRelevansiPencarian($item, $keyword);
            if ($score > 0) {
                $item['_relevance_score'] = $score;
                $candidates[] = $item;
                $addedIds[$memberIdentifier] = true;
            }
        }

        // C. Kumpulkan kandidat tambahan dari direktori kontak sivitas (Mahasiswa / Dosen / Layanan)
        foreach ($directory as $identifier => $profile) {
            $idStr = trim((string) $identifier);
            if ($idStr === '' || $idStr === $userId || isset($addedIds[$idStr])) {
                continue;
            }

            $userObj = $this->portalService->toUserObject($profile);
            $ringkasan = $ringkasanKontak->get($idStr);

            $userObj->setAttribute('is_group', false);
            $userObj->setAttribute('unread_count', $ringkasan['unread_count'] ?? 0);
            $userObj->setAttribute('last_message', $ringkasan['last_message'] ?? null);

            $item = $this->formatKontakSidebarItem($userObj);
            $score = $this->hitungSkorRelevansiPencarian($item, $keyword);
            if ($score > 0) {
                $item['_relevance_score'] = $score;
                $candidates[] = $item;
                $addedIds[$idStr] = true;
            }
        }

        // D. Kumpulkan kandidat Grup KRS yang nama mata kuliah atau kode kelasnya cocok dengan keyword
        foreach ($matchedGroups as $group) {
            $groupKey = 'group_'.$group->id;
            if (isset($addedIds[$groupKey])) {
                continue;
            }

            $groupObj = $this->buildGroupReceiverObject($group);
            $ringkasan = $ringkasanKontak->get($groupKey);

            $groupObj->setAttribute('unread_count', $ringkasan['unread_count'] ?? 0);
            $groupObj->setAttribute('last_message', $ringkasan['last_message'] ?? null);

            $item = $this->formatKontakSidebarItem($groupObj);
            $score = $this->hitungSkorRelevansiPencarian($item, $keyword);
            if ($score > 0) {
                $item['_relevance_score'] = $score;
                $candidates[] = $item;
                $addedIds[$groupKey] = true;
            }
        }

        // E. Urutkan hasil berdasarkan tingkat relevansi tertinggi (Top Results), lalu aktivitas terbaru, lalu abjad nama
        usort($candidates, function (array $a, array $b): int {
            $scoreA = (int) ($a['_relevance_score'] ?? 0);
            $scoreB = (int) ($b['_relevance_score'] ?? 0);

            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }

            $isGroupA = (bool) ($a['is_group'] ?? false);
            $isGroupB = (bool) ($b['is_group'] ?? false);
            if ($isGroupA !== $isGroupB) {
                return $isGroupA ? 1 : -1;
            }

            $tsA = (string) ($a['last_message']['timestamp'] ?? '');
            $tsB = (string) ($b['last_message']['timestamp'] ?? '');
            if ($tsA !== $tsB) {
                return strcmp($tsB, $tsA);
            }

            return strcasecmp((string) ($a['nama_lengkap'] ?? ''), (string) ($b['nama_lengkap'] ?? ''));
        });

        $results = array_map(function (array $item): array {
            unset($item['_relevance_score']);

            return $item;
        }, $candidates);

        if ($roleFilter !== '' && strtolower($roleFilter) !== 'all') {
            $results = array_values(array_filter($results, function (array $item) use ($roleFilter): bool {
                return strcasecmp((string) ($item['role'] ?? ''), $roleFilter) === 0;
            }));
        }

        $totalMatched = count($results);
        $pagedResults = array_slice(array_values($results), $offset, $limit);

        return [
            'keyword'  => $keyword,
            'role'     => $roleFilter,
            'offset'   => $offset,
            'limit'    => $limit,
            'total'    => $totalMatched,
            'has_more' => ($offset + count($pagedResults)) < $totalMatched,
            'groups'   => $matchedGroups,
            'members'  => $matchedMembers,
            'results'  => $pagedResults,
        ];
    }

    /**
     * Sumber kebenaran tunggal untuk pagination & pengurutan kategori Dosen / Mahasiswa (Server-Side & AJAX 100% identik):
     * 1) Kontak dalam kategori tersebut yang punya riwayat chat DM selalu berada di paling atas (urut waktu pesan terbaru DESC).
     * 2) Diikuti kontak lainnya urut abjad A-Z.
     *
     * @return array{items: Collection, total: int, has_more: bool, offset: int, limit: int}
     */
    public function getCategoryContactsPaginated(
        string $userId,
        string $roleFilter,
        SupportCollection $ringkasanKontak,
        int $offset = 0,
        int $limit = 40
    ): array {
        if (! Schema::hasTable('users') || ! in_array($roleFilter, ['Dosen', 'Mahasiswa'], true)) {
            return [
                'items'    => new Collection(),
                'total'    => 0,
                'has_more' => false,
                'offset'   => $offset,
                'limit'    => $limit,
            ];
        }

        $targetRoleLower = strtolower($roleFilter);
        $dbUsers = User::query()
            ->where(function (Builder $q) use ($targetRoleLower): void {
                if (Schema::hasColumn('users', 'role_name')) {
                    $q->where('role_name', $targetRoleLower);
                }
                if ($targetRoleLower === 'mahasiswa') {
                    $q->orWhereNotNull('nim');
                } elseif ($targetRoleLower === 'dosen') {
                    $q->orWhereNotNull('nidn_nip');
                }
            })
            ->get();

        $allItems = [];
        $seen = [];

        foreach ($dbUsers as $dbUser) {
            $idStr = trim((string) ($dbUser->identifier ?: $dbUser->username));
            if ($idStr === '' || $idStr === $userId || isset($seen[$idStr])) {
                continue;
            }
            if (strtolower($dbUser->roleName()) !== $targetRoleLower) {
                continue;
            }

            $realName = trim((string) ($dbUser->name ?: $dbUser->nama_lengkap));
            if (UnujaPortalService::isPlaceholderName($realName, $idStr)) {
                continue;
            }

            $userObj = $this->portalService->toUserObject([
                'identifier'   => $idStr,
                'username'     => $idStr,
                'name'         => $realName,
                'nama_lengkap' => $realName,
                'role'         => $roleFilter,
                'prodi'        => 'Teknik Informatika',
                'fakultas'     => 'Fakultas Teknik',
                'unit'         => (string) ($dbUser->unit ?: 'Fakultas Teknik'),
                'status_akun'  => 'Aktif',
            ]);

            $ringkasan = $ringkasanKontak->get($idStr);
            $userObj->setAttribute('is_group', false);
            $userObj->setAttribute('unread_count', $ringkasan['unread_count'] ?? 0);
            $userObj->setAttribute('last_message', $ringkasan['last_message'] ?? null);

            $allItems[] = $userObj;
            $seen[$idStr] = true;
        }

        usort($allItems, function (User $a, User $b): int {
            $tsA = (string) ($a->last_message['timestamp'] ?? '');
            $tsB = (string) ($b->last_message['timestamp'] ?? '');
            $hasMsgA = $tsA !== '';
            $hasMsgB = $tsB !== '';

            if ($hasMsgA !== $hasMsgB) {
                return $hasMsgA ? -1 : 1;
            }

            if ($hasMsgA && $hasMsgB) {
                if ($tsA !== $tsB) {
                    return strcmp($tsB, $tsA);
                }

                $idA = (int) ($a->last_message['id'] ?? 0);
                $idB = (int) ($b->last_message['id'] ?? 0);
                if ($idA !== $idB) {
                    return $idB <=> $idA;
                }
            }

            return strcasecmp((string) $a->nama_lengkap, (string) $b->nama_lengkap);
        });

        $total = count($allItems);
        $sliced = array_slice($allItems, $offset, $limit);

        return [
            'items'    => new Collection($sliced),
            'total'    => $total,
            'has_more' => ($offset + count($sliced)) < $total,
            'offset'   => $offset,
            'limit'    => $limit,
        ];
    }

    /**
     * Bangun seluruh paket data sidebar: koleksi kontak terurut, jumlah asli per kategori, dan status pagination.
     *
     * @return array{kontak: Collection, categoryCounts: array<string, int>, hasMoreContacts: bool, loadedCategoryCount: int}
     */
    public function buildSidebarData(User $currentUser, ?User $activeReceiver = null, string $requestedCategory = 'all'): array
    {
        $userId = (string) $currentUser->id;
        $ringkasanKontak = $this->getContactsSummary($userId);

        $baseContacts = $this->getBaseContacts($currentUser, $activeReceiver, 'all', $ringkasanKontak);
        $allBaseCount = $baseContacts->count();
        $groupCount = $baseContacts->filter(fn (User $c): bool => (bool) ($c->is_group ?? false))->count();
        $layananCount = $baseContacts->filter(fn (User $c): bool => ! ($c->is_group ?? false) && $c->roleName() === 'Staff_Unit')->count();

        $dosenPage = $this->getCategoryContactsPaginated($userId, 'Dosen', $ringkasanKontak, 0, $requestedCategory === 'Dosen' ? 40 : 1);
        $mhsPage = $this->getCategoryContactsPaginated($userId, 'Mahasiswa', $ringkasanKontak, 0, $requestedCategory === 'Mahasiswa' ? 40 : 1);

        $categoryCounts = [
            'all'        => $allBaseCount,
            'Dosen'      => $dosenPage['total'],
            'Mahasiswa'  => $mhsPage['total'],
            'Staff_Unit' => $layananCount,
            'Group'      => $groupCount,
        ];

        $hasMoreContacts = false;
        $loadedCategoryCount = 0;

        if ($requestedCategory === 'Dosen' || $requestedCategory === 'Mahasiswa') {
            $activePage = $requestedCategory === 'Dosen' ? $dosenPage : $mhsPage;
            $hasMoreContacts = $activePage['has_more'];
            $loadedCategoryCount = $activePage['items']->count();

            $merged = [];
            $addedIds = [];

            foreach ($activePage['items'] as $roleItem) {
                $idStr = (string) $roleItem->id;
                $merged[] = $roleItem;
                $addedIds[$idStr] = true;
            }

            foreach ($baseContacts as $baseItem) {
                $idStr = (string) $baseItem->id;
                if (isset($addedIds[$idStr])) {
                    continue;
                }
                $itemRole = ($baseItem->is_group ?? false) ? 'Group' : $baseItem->roleName();
                if ($itemRole === $requestedCategory && ($activeReceiver === null || (string) $activeReceiver->id !== $idStr)) {
                    continue;
                }
                $merged[] = $baseItem;
                $addedIds[$idStr] = true;
            }

            return [
                'kontak'              => new Collection($merged),
                'categoryCounts'      => $categoryCounts,
                'hasMoreContacts'     => $hasMoreContacts,
                'loadedCategoryCount' => $loadedCategoryCount,
            ];
        }

        return [
            'kontak'              => $baseContacts,
            'categoryCounts'      => $categoryCounts,
            'hasMoreContacts'     => false,
            'loadedCategoryCount' => $allBaseCount,
        ];
    }

    /**
     * Ambil daftar kontak ringkas untuk muatan awal sidebar:
     * (a) Seluruh Grup (Kelas & Perwalian) yang diikuti pengguna
     * (b) Chat Pribadi (DM) yang sudah memiliki riwayat pesan
     * (c) 4 Unit Layanan Kampus (Staff_Unit) untuk tab "Layanan"
     * (d) Kontak/Grup yang sedang aktif dibuka ($activeReceiver)
     */
    public function getBaseContacts(
        User $currentUser,
        ?User $activeReceiver = null,
        string $requestedCategory = 'all',
        ?SupportCollection $prefetchedRingkasan = null
    ): Collection {
        $userId = (string) $currentUser->id;

        $ringkasanKontak = $prefetchedRingkasan ?? $this->getContactsSummary($userId);
        $items = [];
        $addedIds = [];

        // 1. Kumpulkan identifier kontak personal yang sudah memiliki riwayat pesan DM + 4 Unit Layanan Kampus
        $dmContactIds = $ringkasanKontak->keys()
            ->map(fn ($k): string => (string) $k)
            ->filter(fn (string $k): bool => $k !== '' && $k !== $userId && ! str_starts_with($k, 'group_'))
            ->values()
            ->all();

        $serviceUnitIds = ['unit_baak', 'unit_bau', 'unit_ft', 'unit_pdsi'];
        $initialPersonalIds = array_values(array_unique(array_merge($dmContactIds, $serviceUnitIds)));

        foreach ($initialPersonalIds as $idStr) {
            if ($idStr === '' || $idStr === $userId || isset($addedIds[$idStr])) {
                continue;
            }

            $profile = $this->portalService->findUserByIdentifier($idStr);
            if (! $profile) {
                continue;
            }

            $userObj = $this->portalService->toUserObject($profile);
            $ringkasan = $ringkasanKontak->get($idStr);

            $userObj->setAttribute('is_group', false);
            $userObj->setAttribute('unread_count', $ringkasan['unread_count'] ?? 0);
            $userObj->setAttribute('last_message', $ringkasan['last_message'] ?? null);

            $items[] = $userObj;
            $addedIds[$idStr] = true;
        }

        // 2. Tarik & Masukkan seluruh Grup (Kelas & Perwalian) yang diikuti pengguna ini
        if (Schema::hasTable('groups')) {
            $userGroups = Group::withCount('members')
                ->whereHas('members', function (Builder $query) use ($userId): void {
                    $query->where('user_identifier', $userId);
                })
                ->get();

            foreach ($userGroups as $group) {
                $groupKey = 'group_'.$group->id;
                if (isset($addedIds[$groupKey])) {
                    continue;
                }

                $ringkasan = $ringkasanKontak->get($groupKey);

                $groupObj = $this->buildGroupReceiverObject($group);
                $groupObj->setAttribute('unread_count', $ringkasan['unread_count'] ?? 0);
                $groupObj->setAttribute('last_message', $ringkasan['last_message'] ?? null);

                $items[] = $groupObj;
                $addedIds[$groupKey] = true;
            }
        }

        // 3. Pastikan kontak/grup yang sedang dibuka saat ini selalu ada di dalam daftar sidebar
        if ($activeReceiver !== null) {
            $activeId = (string) $activeReceiver->id;
            if ($activeId !== '' && $activeId !== $userId && ! isset($addedIds[$activeId])) {
                $ringkasan = $ringkasanKontak->get($activeId);
                $activeReceiver->setAttribute('unread_count', 0);
                $activeReceiver->setAttribute('last_message', $ringkasan['last_message'] ?? null);
                $items[] = $activeReceiver;
                $addedIds[$activeId] = true;
            }
        }

        // 4. Urutkan percakapan aktif (timestamp terbaru DESC), diikuti kontak & grup lainnya urut nama bersih A-Z
        usort($items, function ($a, $b): int {
            $tsA = (string) ($a->last_message['timestamp'] ?? '');
            $tsB = (string) ($b->last_message['timestamp'] ?? '');
            $hasMsgA = $tsA !== '';
            $hasMsgB = $tsB !== '';

            if ($hasMsgA !== $hasMsgB) {
                return $hasMsgA ? -1 : 1;
            }

            if ($hasMsgA && $hasMsgB) {
                if ($tsA !== $tsB) {
                    return strcmp($tsB, $tsA);
                }

                $idA = (int) ($a->last_message['id'] ?? 0);
                $idB = (int) ($b->last_message['id'] ?? 0);

                if ($idA !== $idB) {
                    return $idB <=> $idA;
                }
            }

            $cleanNameA = trim((string) preg_replace('/^mk\s+/i', '', (string) $a->nama_lengkap));
            $cleanNameB = trim((string) preg_replace('/^mk\s+/i', '', (string) $b->nama_lengkap));

            return strcasecmp($cleanNameA, $cleanNameB);
        });

        return new Collection($items);
    }

    /**
     * Ambil tahun akademik aktif untuk ditampilkan pada header/sidebar dashboard.
     */
    public function getActiveAcademicYear(): mixed
    {
        try {
            if (Schema::hasTable('tahun_akademiks')) {
                $tahun = TahunAkademik::where('is_aktif', true)->first();
                if ($tahun) {
                    return $tahun;
                }
            }
        } catch (\Throwable $e) {
            // Abaikan jika tabel tidak ada
        }

        return (object) [
            'tahun'         => '2026/2027',
            'nama_tahun'    => '2026/2027',
            'nama_semester' => 'Semester Ganjil 2026/2027',
            'semester'      => 'Ganjil',
            'kode_semester' => '20261',
            'is_aktif'      => true,
        ];
    }

    /**
     * Hitung skor relevansi item kontak/grup terhadap kata kunci pencarian.
     * Mengembalikan 0 jika tidak relevan.
     *
     * @param  array<string, mixed>  $item
     */
    private function hitungSkorRelevansiPencarian(array $item, string $keyword): int
    {
        $kw = strtolower(trim($keyword));
        if ($kw === '') {
            return 0;
        }

        $isGroup = (bool) ($item['is_group'] ?? false);
        $name = strtolower(trim((string) ($item['nama_lengkap'] ?? '')));
        $id = strtolower(trim((string) ($item['id'] ?? '')));
        $identity = strtolower(trim((string) ($item['identity_number'] ?? '')));
        $role = strtolower(trim((string) ($item['role'] ?? '')));
        $subtitle = strtolower(trim((string) ($item['subtitle'] ?? '')));

        if ($isGroup) {
            $cleanCourseName = trim((string) preg_replace('/^mk\s+/i', '', $name));

            if (! str_contains($name, $kw) && ! str_contains($cleanCourseName, $kw) && ! str_contains($identity, $kw)) {
                return 0;
            }

            if ($cleanCourseName === $kw || $name === $kw) {
                return 950;
            }

            if (str_starts_with($cleanCourseName, $kw) || str_starts_with($name, $kw)) {
                return 780;
            }

            $courseWords = preg_split('/[\s,.\-\/()]+/', $cleanCourseName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($courseWords as $idx => $word) {
                if ($word === $kw) {
                    return 680 + max(0, 10 - $idx);
                }
            }

            foreach ($courseWords as $idx => $word) {
                if (str_starts_with($word, $kw)) {
                    return 580 + max(0, 10 - $idx);
                }
            }

            return 280;
        }

        $matchesName = str_contains($name, $kw);
        $matchesId = str_contains($id, $kw) || str_contains($identity, $kw);
        $matchesServiceUnit = $role === 'staff_unit' && str_contains($subtitle, $kw);

        if (! $matchesName && ! $matchesId && ! $matchesServiceUnit) {
            return 0;
        }

        if ($id === $kw || $identity === $kw || $name === $kw) {
            return 1000;
        }

        if (str_starts_with($name, $kw) || str_starts_with($id, $kw) || str_starts_with($identity, $kw)) {
            return 850;
        }

        $words = preg_split('/[\s,.\-\/()]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $idx => $word) {
            if ($word === $kw) {
                return 750 + max(0, 10 - $idx);
            }
        }

        if (str_contains($kw, ' ') && $matchesName) {
            return 720;
        }

        foreach ($words as $idx => $word) {
            if (str_starts_with($word, $kw)) {
                return 650 + max(0, 10 - $idx);
            }
        }

        if ($matchesId) {
            return 500;
        }

        if ($matchesName) {
            return 350;
        }

        return 300;
    }

    /**
     * Format objek kontak/grup menjadi struktur JSON yang siap dirender ulang oleh DOM Sidebar.
     *
     * @return array<string, mixed>
     */
    private function formatKontakSidebarItem(User $contact): array
    {
        $isGroup = (bool) ($contact->is_group ?? false);
        $groupType = $isGroup ? strtolower((string) ($contact->group_type ?? $contact->type ?? 'kelas')) : null;
        $isPerwalian = $isGroup && $groupType === 'perwalian';

        $role = $isGroup ? 'Group' : $contact->roleName();
        $roleLabel = $isGroup ? ($isPerwalian ? 'Perwalian PA' : 'Grup Kelas') : $contact->roleLabel();
        $initials = $isGroup ? ($isPerwalian ? 'PA' : 'GR') : $contact->initials();
        $identityNumber = $isGroup
            ? ($isPerwalian
                ? 'Perwalian Akademik'
                : (! empty($contact->kode_tahun_akademik) ? Group::formatKodeSemester((string) $contact->kode_tahun_akademik) : 'Grup Sistem'))
            : $contact->identityNumber();
        $subtitle = $isGroup
            ? (isset($contact->members_count) ? $contact->members_count.' Anggota' : 'Komunikasi Internal')
            : $contact->contactSubtitle();

        return [
            'id'              => (string) $contact->id,
            'url'             => route('chat.show', $contact->id),
            'nama_lengkap'    => (string) $contact->nama_lengkap,
            'is_group'        => $isGroup,
            'group_type'      => $groupType,
            'role'            => $role,
            'role_label'      => $roleLabel,
            'initials'        => $initials,
            'identity_number' => $identityNumber,
            'subtitle'        => $subtitle,
            'status_akun'     => (string) ($contact->status_akun ?? 'Aktif'),
            'unread_count'    => (int) ($contact->unread_count ?? 0),
            'last_message'    => $contact->last_message,
        ];
    }

    /**
     * Format pesan terakhir personal untuk cuplikan di sidebar.
     *
     * @return array{id: int, body: string, time: string, timestamp: string, is_sender_me: bool, is_read: bool}
     */
    private function formatPesanTerakhir(Message $msg, string $userId): array
    {
        $tanggal = $msg->created_at->timezone('Asia/Jakarta');

        return [
            'id'           => $msg->id,
            'body'         => Str::limit(trim((string) preg_replace('/\s+/u', ' ', $msg->body)), 120),
            'time'         => $this->formatWaktuSidebar($tanggal),
            'timestamp'    => $tanggal->toIso8601String(),
            'is_sender_me' => (string) $msg->sender_identifier === $userId,
            'is_read'      => (bool) $msg->is_read,
        ];
    }

    /**
     * Format pesan terakhir grup untuk cuplikan di sidebar (menyertakan prefix "Anda: " atau nama pengirim).
     *
     * @param  array<string, array<string, mixed>>  $directory
     * @return array{id: int, body: string, time: string, timestamp: string, is_sender_me: bool, is_read: bool}
     */
    private function formatPesanTerakhirGrup(GroupMessage $msg, string $userId, array $directory): array
    {
        $tanggal = $msg->created_at->timezone('Asia/Jakarta');
        $isSenderMe = (string) $msg->sender_identifier === $userId;

        if ($isSenderMe) {
            $prefix = 'Anda: ';
        } else {
            $senderProfile = $directory[$msg->sender_identifier] ?? null;
            $senderName = $senderProfile['nama_lengkap'] ?? $senderProfile['name'] ?? $msg->sender_identifier;
            $prefix = $senderName.': ';
        }

        $cleanBody = trim((string) preg_replace('/\s+/u', ' ', $msg->body));

        return [
            'id'           => $msg->id,
            'body'         => Str::limit($prefix.$cleanBody, 120),
            'time'         => $this->formatWaktuSidebar($tanggal),
            'timestamp'    => $tanggal->toIso8601String(),
            'is_sender_me' => false,
            'is_read'      => false,
        ];
    }

    private function formatWaktuSidebar(CarbonInterface $tanggal): string
    {
        if ($tanggal->isToday()) {
            return $tanggal->format('H:i');
        }

        if ($tanggal->isYesterday()) {
            return 'Kemarin';
        }

        if ($tanggal->greaterThanOrEqualTo(now($tanggal->getTimezone())->subDays(6)->startOfDay())) {
            return $tanggal->locale('id')->translatedFormat('l');
        }

        return $tanggal->format('d/m/y');
    }
}
