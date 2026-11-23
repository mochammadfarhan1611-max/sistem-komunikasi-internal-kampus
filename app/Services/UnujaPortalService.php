<?php

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use App\Services\Unuja\UnujaApiClient;
use App\Services\Unuja\UnujaKrsSyncService;
use App\Services\Unuja\UnujaPerwalianSyncService;
use App\Services\Unuja\UnujaProfileService;

class UnujaPortalService
{
    public function __construct(
        private UnujaApiClient $apiClient,
        private UnujaProfileService $profileService,
        private UnujaKrsSyncService $krsSyncService,
        private UnujaPerwalianSyncService $perwalianSyncService,
    ) {}

    public static function isPlaceholderName(?string $name, ?string $identifier = null): bool
    {
        return UnujaProfileService::isPlaceholderName($name, $identifier);
    }

    public static function isDosenIdentifier(string $identifier): bool
    {
        return UnujaProfileService::isDosenIdentifier($identifier);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function formatDosenFullName(array $row): string
    {
        return UnujaProfileService::formatDosenFullName($row);
    }

    public function getLocalUserName(string $identifier): ?string
    {
        return $this->profileService->getLocalUserName($identifier);
    }

    public function saveLocalUserProfile(
        string $identifier,
        ?string $name,
        string $role = 'Mahasiswa',
        string $unit = 'Fakultas Teknik',
        ?string $prodi = 'Teknik Informatika'
    ): ?User {
        return $this->profileService->saveLocalUserProfile($identifier, $name, $role, $unit, $prodi);
    }

    public function getDataUrl(bool $forceRefresh = false): ?string
    {
        return $this->apiClient->getDataUrl($forceRefresh);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function fetchPortalReferenceData(array $payload): array
    {
        return $this->apiClient->fetchPortalReferenceData($payload);
    }

    public function getRealProfileName(string $identifier, string $role = 'mahasiswa'): string
    {
        return $this->profileService->getRealProfileName($identifier, $role);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findUserByIdentifier(string $identifier): ?array
    {
        return $this->profileService->findUserByIdentifier($identifier);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchUsersFromApi(string $keyword): array
    {
        return $this->profileService->searchUsersFromApi($keyword);
    }

    /**
     * @return array{deleted_members: int, deleted_users: int, deleted_groups: int}
     */
    public function cleanupInvalidUsersAndMembers(): array
    {
        return $this->krsSyncService->cleanupInvalidUsersAndMembers();
    }

    /**
     * @return array<string, array{identifier: string, name: string, id_matkul: string, kode_matkul: string, nama_matkul: string, nama_kelas: string, id_kelas: int|null}>
     */
    public function syncAllDosenAndCourseLecturers(string $semester = '20261'): array
    {
        return $this->krsSyncService->syncAllDosenAndCourseLecturers($semester);
    }

    /**
     * @return array{groups_synced: int, total_groups: int, perwalian_groups_synced: int, students_synced: int, machine_learning_c_count: int}
     */
    public function syncFullCampusKrsSemester(string $semester = '20261', ?callable $logger = null): array
    {
        return $this->krsSyncService->syncFullCampusKrsSemester($semester, $logger);
    }

    /**
     * @return array{groups_synced: int, students_assigned: int, advisors_count: int}
     */
    public function syncPerwalianGroupsFromApi(string $semester = '20261', ?callable $logger = null): array
    {
        return $this->perwalianSyncService->syncPerwalianGroupsFromApi($semester, $logger);
    }

    public function syncClassmatesFromApi(?Group $group = null): void
    {
        $this->krsSyncService->syncClassmatesFromApi($group);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getDirectoryContacts(): array
    {
        return $this->profileService->getDirectoryContacts();
    }

    /**
     * @param  array<string, array<string, mixed>>  $newContacts
     */
    public function upsertDirectoryContacts(array $newContacts): void
    {
        $this->profileService->upsertDirectoryContacts($newContacts);
    }

    public function syncDirectoryFromPortal(): void
    {
        $this->krsSyncService->syncDirectoryFromPortal();
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    public function toUserObject(array $profile): User
    {
        return $this->profileService->toUserObject($profile);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getKrsData(string $identifier, string $role): array
    {
        return $this->krsSyncService->getKrsData($identifier, $role);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getKrsFallbackData(string $identifier, string $role): array
    {
        return $this->krsSyncService->getKrsFallbackData($identifier, $role);
    }

    public function syncUserKrsGroups(User $user): int
    {
        return $this->krsSyncService->syncUserKrsGroups($user);
    }
}
