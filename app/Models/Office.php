<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    protected $fillable = [
        'office_code',
        'office_name',
        'office_type',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class, 'office_code', 'office_code');
    }
}
