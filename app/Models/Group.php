<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $guarded = [];

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class, 'group_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GroupMessage::class, 'group_id');
    }

    /**
     * Format kode periode akademik 5 digit (misal "20261" -> "Ganjil 2026/2027").
     */
    public static function formatKodeSemester(?string $kode): string
    {
        $clean = trim((string) $kode);
        if ($clean === '') {
            return 'Grup Sistem';
        }

        if (preg_match('/(?:^|semester\s*)(\d{4})([123])$/i', $clean, $matches) === 1) {
            $year = (int) $matches[1];
            $nextYear = $year + 1;
            $term = match ($matches[2]) {
                '1' => 'Ganjil',
                '2' => 'Genap',
                '3' => 'Antara',
                default => 'Semester',
            };

            return "{$term} {$year}/{$nextYear}";
        }

        return $clean;
    }
}
