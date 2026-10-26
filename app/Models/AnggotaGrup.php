<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnggotaGrup extends Model
{
    use HasFactory;

    protected $table = 'anggota_grup';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'terakhir_baca_pada' => 'datetime',
            'waktu_bergabung' => 'datetime',
        ];
    }

    public function grup(): BelongsTo
    {
        return $this->belongsTo(GrupChat::class, 'grup_id');
    }

    public function grupChat(): BelongsTo
    {
        return $this->belongsTo(GrupChat::class, 'grup_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

