<?php

namespace App\Services\Ead;

/**
 * Multijasa EAD = outstanding_balance.
 */
class MultijasaStrategy implements EadStrategyInterface
{
    public function calculate(array $snapshot, \DateTime $assessmentDate): float
    {
        return (float) ($snapshot['outstanding_balance'] ?? 0);
    }
}
