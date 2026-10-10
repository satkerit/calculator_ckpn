<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BucketDefinition extends Model
{
    protected $fillable = [
        'kode_bucket',
        'deskripsi_hari',
        'hari_min',
        'hari_max',
        'urutan',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
