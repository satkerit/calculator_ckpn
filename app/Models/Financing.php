<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Financing extends Model
{
    protected $table = 'financing';

    protected $fillable = [
        'loan_number',
        'cif_number',
        'name',
        'tglwo',
        'usage_code',
        'active_flag',
        'classification',
    ];

    protected $casts = [
        'tglwo' => 'date',
        'active_flag' => 'boolean',
    ];

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class, 'loan_number', 'loan_number');
    }

    public function collaterals(): HasMany
    {
        return $this->hasMany(Collateral::class, 'loan_number', 'loan_number');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_code', 'office_code');
    }
}
