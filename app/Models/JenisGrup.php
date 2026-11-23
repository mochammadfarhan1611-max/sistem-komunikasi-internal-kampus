<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}

