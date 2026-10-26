<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahunAkademik extends Model
{
    use HasFactory;

    protected $table = 'tahun_akademik';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function kelasPerkuliahan(): HasMany
    {
        return $this->hasMany(KelasPerkuliahan::class, 'tahun_akademik_id');
    }

    public function hubunganAkademik(): HasMany
    {
        return $this->hasMany(HubunganAkademik::class, 'tahun_akademik_id');
    }

    public function krsMahasiswa(): HasMany
    {
        return $this->hasMany(KrsMahasiswa::class, 'tahun_akademik_id');
    }

    public function grupChat(): HasMany
    {
        return $this->hasMany(GrupChat::class, 'tahun_akademik_id');
    }

    public function permintaanChat(): HasMany
    {
        return $this->hasMany(PermintaanChat::class, 'tahun_akademik_id');
    }
}

