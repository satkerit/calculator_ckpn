<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class AssessmentPeriod extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'DRAFT';
    const STATUS_VALIDATING = 'VALIDATING';
    const STATUS_CALCULATING = 'CALCULATING';
    const STATUS_REVIEW = 'REVIEW';
    const STATUS_APPROVED = 'APPROVED';
    const STATUS_LOCKED = 'LOCKED';

    protected $fillable = [
        'period_code',
        'period_name',
        'fiscal_month',
        'fiscal_year',
        'assessment_date',
        'status',
        'source_version',
        'created_by',
        'approved_by',
        'approved_at',
        'quality_score',
        'data_quality_status',
    ];

    protected $casts = [
        'assessment_date' => 'date',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'quality_score' => 'float',
    ];

    protected static array $stateTransitions = [
        self::STATUS_DRAFT => [self::STATUS_VALIDATING],
        self::STATUS_VALIDATING => [self::STATUS_CALCULATING, self::STATUS_DRAFT],
        self::STATUS_CALCULATING => [self::STATUS_REVIEW, self::STATUS_DRAFT],
        self::STATUS_REVIEW => [self::STATUS_APPROVED, self::STATUS_DRAFT],
        self::STATUS_APPROVED => [self::STATUS_LOCKED],
        self::STATUS_LOCKED => [],
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $currentStatus = $this->status ?? self::STATUS_DRAFT;
        $allowedStates = self::$stateTransitions[$currentStatus] ?? [];
        return in_array($newStatus, $allowedStates);
    }

    public function transitionTo(string $newStatus): bool
    {
        if (!$this->canTransitionTo($newStatus)) {
            Log::warning("Invalid state transition", [
                'current' => $this->status,
                'requested' => $newStatus,
                'period_id' => $this->id
            ]);
            return false;
        }

        if ($this->status === self::STATUS_LOCKED) {
            Log::error("Cannot recalculate LOCKED period", ['period_id' => $this->id]);
            return false;
        }

        $this->status = $newStatus;
        $this->save();
        
        Log::info("Status transition", [
            'period_id' => $this->id,
            'from' => $this->getOriginal('status'),
            'to' => $newStatus
        ]);
        
        return true;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function canCalculate(): bool
    {
        return !$this->isLocked();
    }
}
