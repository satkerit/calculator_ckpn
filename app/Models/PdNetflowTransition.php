<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdNetflowTransition extends Model
{
    protected $fillable = [
        'assessment_period_id',
        'group_key',
        'from_bucket',
        'to_bucket',
        'periode',
        'transition_rate',
        'flow_count',
    ];

    protected $casts = [
        'periode' => 'date',
        'transition_rate' => 'decimal:6',
        'from_bucket' => 'integer',
        'to_bucket' => 'integer',
        'flow_count' => 'integer',
    ];

    public function assessmentPeriod(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class);
    }
}
