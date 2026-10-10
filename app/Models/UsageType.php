<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageType extends Model
{
    protected $fillable = [
        'kode_jenis_penggunaan',
        'nama',
        'deskripsi',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
