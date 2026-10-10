<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdNetflowCompoundFlow extends Model
{
    protected $fillable = [
        'assessment_period_id',
        'group_key',
        'source_bucket',
        'periode',
        'compound_pd',
        'flow_path',
    ];

    protected $casts = [
        'periode' => 'date',
        'compound_pd' => 'decimal:6',
        'source_bucket' => 'integer',
        'flow_path' => 'array',
    ];

    public function assessmentPeriod(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class);
    }
}
