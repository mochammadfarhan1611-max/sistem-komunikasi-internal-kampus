<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Percakapan extends Model
{
    use HasFactory;

    protected $table = 'percakapan';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'waktu_pesan_terakhir' => 'datetime',
            'waktu_percakapan' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function userSatu(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_satu_id');
    }

    public function userDua(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_dua_id');
    }

    public function grup(): BelongsTo
    {
        return $this->belongsTo(GrupChat::class, 'grup_id');
    }

    public function grupChat(): BelongsTo
    {
        return $this->belongsTo(GrupChat::class, 'grup_id');
    }

    public function unitKampus(): BelongsTo
    {
        return $this->belongsTo(UnitKampus::class, 'unit_kampus_id');
    }

    public function pesan(): HasMany
    {
        return $this->hasMany(Pesan::class, 'percakapan_id');
    }

    public function permintaanChat(): HasMany
    {
        return $this->hasMany(PermintaanChat::class, 'percakapan_id');
    }
}

