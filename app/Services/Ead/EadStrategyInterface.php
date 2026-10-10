<?php

namespace App\Services\Ead;

interface EadStrategyInterface
{
    public function calculate(array $snapshot, \DateTime $assessmentDate): float;
}
