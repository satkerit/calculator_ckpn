<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityRating extends Model
{
    protected $fillable = [
        'kode_kualitas',
        'nama_kualitas',
        'urutan',
        'status',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
