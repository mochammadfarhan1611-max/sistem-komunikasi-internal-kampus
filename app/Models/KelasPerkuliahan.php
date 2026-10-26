<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelasPerkuliahan extends Model
{
    use HasFactory;

    protected $table = 'kelas_perkuliahan';

    protected $guarded = ['id'];

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_id');
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dosen_id');
    }

    public function krsMahasiswa(): HasMany
    {
        return $this->hasMany(KrsMahasiswa::class, 'kelas_perkuliahan_id');
    }

    public function grupChat(): HasMany
    {
        return $this->hasMany(GrupChat::class, 'kelas_perkuliahan_id');
    }
}

