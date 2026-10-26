<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GrupChat extends Model
{
    use HasFactory;

    protected $table = 'grup_chat';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'waktu_dibuat' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function jenisGrup(): BelongsTo
    {
        return $this->belongsTo(JenisGrup::class, 'jenis_grup_id');
    }

    public function kelasPerkuliahan(): BelongsTo
    {
        return $this->belongsTo(KelasPerkuliahan::class, 'kelas_perkuliahan_id');
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembuat_id');
    }

    public function anggotaGrup(): HasMany
    {
        return $this->hasMany(AnggotaGrup::class, 'grup_id');
    }

    public function percakapan(): HasOne
    {
        return $this->hasOne(Percakapan::class, 'grup_id');
    }
}

