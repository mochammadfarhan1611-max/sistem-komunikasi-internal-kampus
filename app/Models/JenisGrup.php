<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisGrup extends Model
{
    use HasFactory;

    protected $table = 'jenis_grup';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_otomatis' => 'boolean',
        ];
    }

    public function grupChat(): HasMany
    {
        return $this->hasMany(GrupChat::class, 'jenis_grup_id');
    }
}

