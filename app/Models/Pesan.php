<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pesan extends Model
{
    use HasFactory;

    protected $table = 'pesan';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'dikirim_saat_libur' => 'boolean',
            'status_terkirim' => 'boolean',
            'status_dibaca' => 'boolean',
            'waktu_pengiriman' => 'datetime',
        ];
    }

    public function percakapan(): BelongsTo
    {
        return $this->belongsTo(Percakapan::class, 'percakapan_id');
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengirim_id');
    }

    public function lampiranPesan(): HasMany
    {
        return $this->hasMany(LampiranPesan::class, 'pesan_id');
    }
}

