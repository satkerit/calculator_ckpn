<?php

namespace App\Services\Ead;

/**
 * Murabahah EAD = outstanding_balance.
 */
class MurabahahStrategy implements EadStrategyInterface
{
    public function calculate(array $snapshot, \DateTime $assessmentDate): float
    {
        return (float) ($snapshot['outstanding_balance'] ?? 0);
    }
}
