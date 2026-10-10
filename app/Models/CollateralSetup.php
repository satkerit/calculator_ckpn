<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollateralSetup extends Model
{
    protected $table = 'collateral_setup';

    protected $fillable = [
        'collateral_type',
        'collateral_ket',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected $primaryKey = 'collateral_type';

    public $incrementing = false;

    protected $keyType = 'string';
}
