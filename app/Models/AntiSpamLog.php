<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AntiSpamLog extends Model
{
    use HasFactory;

    protected $table = 'anti_spam_logs';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'jumlah_pesan_singkat' => 'integer',
            'status_cooldown' => 'boolean',
            'cooldown_sampai' => 'datetime',
            'waktu_kejadian' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

