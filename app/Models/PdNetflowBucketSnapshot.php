<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdNetflowBucketSnapshot extends Model
{
    protected $fillable = [
        'assessment_period_id',
        'group_key',
        'bucket_no',
        'periode_snapshot',
        'outstanding_balance',
        'account_count',
    ];

    protected $casts = [
        'periode_snapshot' => 'date',
        'outstanding_balance' => 'decimal:2',
        'bucket_no' => 'integer',
        'account_count' => 'integer',
    ];

    public function assessmentPeriod(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class);
    }
}
