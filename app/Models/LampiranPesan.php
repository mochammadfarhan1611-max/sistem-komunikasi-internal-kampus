<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LampiranPesan extends Model
{
    use HasFactory;

    protected $table = 'lampiran_pesan';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'ukuran_kb' => 'integer',
            'waktu_upload' => 'datetime',
        ];
    }

    public function pesan(): BelongsTo
    {
        return $this->belongsTo(Pesan::class, 'pesan_id');
    }
}

