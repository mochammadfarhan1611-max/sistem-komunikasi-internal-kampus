<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HariLiburKampus extends Model
{
    use HasFactory;

    protected $table = 'hari_libur_kampus';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal_libur' => 'date',
        ];
    }
}

