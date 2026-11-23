<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\GroupMember;
use App\Services\UnujaPortalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncKrsSemester extends Command
{
    protected $signature = 'sync:krs {identifier?} {--role=mahasiswa} {--semester=20261} {--perwalian}';

    protected $description = 'Tarik dan sinkronkan seluruh populasi kelas KRS, Grup Perwalian (Dosen Wali), Dosen Pengampu Asli, serta bersihkan NIM tidak valid dari server SSO UNUJA';

    public function handle(UnujaPortalService $portalService): int
    {
        $identifier = $this->argument('identifier');
        $role = strtolower((string) $this->option('role'));
        $semester = (string) $this->option('semester') ?: '20261';

        if ($this->option('perwalian')) {
            $this->info("=== MEMULAI SINKRONISASI GRUP PERWALIAN / BIMBINGAN AKADEMIK (SEMESTER {$semester}) ===");
            $summary = $portalService->syncPerwalianGroupsFromApi($semester, function (string $msg): void {
                $this->line('  -> '.$msg);
            });

            $this->table(
                ['Metrik', 'Nilai'],
                [
                    ['Total Dosen Wali (PA)', $summary['advisors_count'] ?? 0],
                    ['Total Grup Perwalian Terbentuk', $summary['groups_synced'] ?? 0],
                    ['Total Mahasiswa Bimbingan Terdaftar', $summary['students_assigned'] ?? 0],
                ]
            );

            return self::SUCCESS;
        }

        if (empty($identifier)) {
            $this->info("=== MEMULAI SINKRONISASI PENUH POPULASI KELAS & DOSEN ASLI (SEMESTER {$semester}) ===");

            $summary = $portalService->syncFullCampusKrsSemester($semester, function (string $msg): void {
                $this->line('  -> '.$msg);
            });

            $this->newLine();
            $this->info('=== RINGKASAN SINKRONISASI DATABASE LOKAL ===');
            $this->table(
                ['Metrik', 'Nilai'],
                [
                    ['Data Sampah / NIM Typo Dihapus (Users)', $summary['cleanup']['deleted_users'] ?? 0],
                    ['Data Sampah / NIM Typo Dihapus (Group Members)', $summary['cleanup']['deleted_members'] ?? 0],
                    ['Grup Kelas Diperbarui dengan Dosen Pengampu Asli', $summary['dosen_sync']['updated_groups'] ?? 0],
                    ['Total Mahasiswa Terverifikasi Disinkronkan', $summary['students_synced'] ?? 0],
                    ['Total Keanggotaan Kelas (Group Members) Diproses', $summary['memberships_synced'] ?? 0],
                    ['Total Grup Aktif di Database', $summary['total_groups'] ?? 0],
                ]
            );

            $mlGroup = Group::with('members')
                ->where('name', 'MK Machine Learning - Kelas C')
                ->where('kode_tahun_akademik', $semester)
                ->first();

            if ($mlGroup) {
                $this->info("Total Anggota Grup '{$mlGroup->name}' (Semester {$semester}): ".$mlGroup->members->count().' orang.');
            }

            return self::SUCCESS;
        }

        $this->info("Menghubungi API Kampus untuk {$role}: {$identifier}...");

        // Pastikan daftar dosen pengampu asli sudah disinkronkan terlebih dahulu
        $dosenSync = $portalService->syncAllDosenAndCourseLecturers($semester);
        $courseLecturerMap = $dosenSync['course_lecturer_map'] ?? [];

        $krsData = $portalService->getKrsData($identifier, $role);

        if (empty($krsData)) {
            $this->error("Gagal! Data kosong atau API menolak untuk identifier {$identifier}.");

            return self::FAILURE;
        }

        $realName = $portalService->getLocalUserName($identifier)
            ?? (! empty($krsData[0]['mahasiswa_name']) ? (string) $krsData[0]['mahasiswa_name'] : $portalService->getRealProfileName($identifier, $role));

        $portalService->saveLocalUserProfile(
            $identifier,
            $realName,
            $role === 'dosen' ? 'Dosen' : 'Mahasiswa'
        );

        $total = count($krsData);
        $this->info("Berhasil mendapatkan {$total} mata kuliah untuk {$realName} ({$identifier}). Menyinkronkan ke database lokal...");

        $hasUserNameCol = Schema::hasColumn('group_members', 'user_name');
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($krsData as $item) {
            $groupKey = $item['group_name'].'|'.$item['tahun_akademik'];
            $lecturerInfo = $courseLecturerMap[$groupKey] ?? null;

            $existingGroup = Group::where('name', $item['group_name'])
                ->where('kode_tahun_akademik', $item['tahun_akademik'])
                ->first();

            $dosenId = $lecturerInfo['identifier']
                ?? (! empty($item['dosen_identifier']) ? (string) $item['dosen_identifier'] : null)
                ?? ($existingGroup && $existingGroup->creator_identifier !== '0711028801' ? $existingGroup->creator_identifier : '0701098506');

            $dosenName = $lecturerInfo['name']
                ?? $portalService->getLocalUserName($dosenId)
                ?? $portalService->getRealProfileName($dosenId, 'dosen');

            $portalService->saveLocalUserProfile($dosenId, $dosenName, 'Dosen');

            $group = Group::updateOrCreate(
                ['name' => $item['group_name'], 'kode_tahun_akademik' => $item['tahun_akademik']],
                ['creator_identifier' => $dosenId]
            );

            $adminAttrs = ['role' => 'admin'];
            if ($hasUserNameCol && ! UnujaPortalService::isPlaceholderName($dosenName, $dosenId)) {
                $adminAttrs['user_name'] = $dosenName;
            }
            GroupMember::updateOrCreate(
                ['group_id' => $group->id, 'user_identifier' => $dosenId],
                $adminAttrs
            );

            $memberAttrs = ['role' => ($role === 'dosen') ? 'admin' : 'member'];
            if ($hasUserNameCol && ! UnujaPortalService::isPlaceholderName($realName, $identifier)) {
                $memberAttrs['user_name'] = $realName;
            }

            GroupMember::updateOrCreate(
                ['group_id' => $group->id, 'user_identifier' => $identifier],
                $memberAttrs
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Sinkronisasi KRS untuk {$realName} ({$identifier}) berhasil diselesaikan!");

        return self::SUCCESS;
    }
}
