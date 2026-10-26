<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitKampus extends Model
{
    use HasFactory;

    protected $table = 'unit_kampus';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function percakapan(): HasMany
    {
        return $this->hasMany(Percakapan::class, 'unit_kampus_id');
    }
}

