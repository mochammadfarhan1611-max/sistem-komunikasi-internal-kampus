<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanKomunikasi extends Model
{
    use HasFactory;

    protected $table = 'pengaturan_komunikasi';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'mode_jangan_ganggu' => 'boolean',
            'notifikasi_aktif' => 'boolean',
            'mute_percakapan_ids' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

