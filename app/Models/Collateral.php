<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collateral extends Model
{
    use HasFactory;
    protected $table = 'collateral';
    protected $fillable = [
        'loan_number',
        'collateral_code',
        'collateral_order',
        'collateral_type',
        'liquidity_value',
        'estimated_value',
        'valuation_date',
        'active_flag',
    ];

    protected $casts = [
        'active_flag' => 'boolean',
        'liquidity_value' => 'decimal:2',
        'estimated_value' => 'decimal:2',
        'valuation_date' => 'date',
    ];

    public function collateralSetup(): BelongsTo
    {
        return $this->belongsTo(CollateralSetup::class, 'collateral_type', 'collateral_type');
    }
}
