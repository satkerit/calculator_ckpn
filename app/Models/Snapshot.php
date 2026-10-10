<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Snapshot extends Model
{
    use HasFactory;

    protected $table = 'snapshot';

    protected $fillable = [
        'loan_number',
        'product_code',
        'office_code',
        'usage_type',
        'tglwo',
        'effective_date',
        'expired_date',
        'outstanding_balance',
        'principal_arrears',
        'days_past_due',
        'quality_rating',
        'financing_contract_code',
        'active_flag',
        'write_off_flag',
        'ppka_value',
        'periode_snapshot',
        'bucket_no',
    ];

    protected $casts = [
        'active_flag' => 'boolean',
        'write_off_flag' => 'boolean',
        'days_past_due' => 'integer',
        'bucket_no' => 'integer',
        'outstanding_balance' => 'decimal:2',
        'principal_arrears' => 'decimal:2',
        'ppka_value' => 'decimal:2',
        'effective_date' => 'date',
        'expired_date' => 'date',
    ];

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_code', 'office_code');
    }
}
