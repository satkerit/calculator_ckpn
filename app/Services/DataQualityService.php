<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DataQualityService
{
    public function calculateQualityScore(array $data): array
    {
        $scores = [
            'completeness' => $this->calculateCompleteness($data),
            'consistency' => $this->calculateConsistency($data),
            'validity' => $this->calculateValidity($data),
            'historical_coverage' => $this->calculateHistoricalCoverage($data),
            'reconciliation' => $this->calculateReconciliation($data),
        ];

        $totalScore = array_sum($scores) / count($scores);

        return [
            'quality_score' => round($totalScore, 2),
            'breakdown' => $scores,
            'status' => $this->getQualityStatus($totalScore),
            'meets_threshold' => $totalScore >= 85,
        ];
    }

    private function calculateCompleteness(array $data): float
    {
        $validation = $data['validation'] ?? [];
        $totalRecords = $validation['total_record_count'] ?? 0;
        $errorRecords = $validation['error_record_count'] ?? 0;
        $warningRecords = $validation['warning_record_count'] ?? 0;

        if ($totalRecords === 0) {
            return 0;
        }

        $validRecords = $totalRecords - $errorRecords;
        $score = ($validRecords / $totalRecords) * 100;

        // Penalize warnings: each warning reduces score by 0.5%
        $score -= ($warningRecords * 0.5);
        return max(0, min(100, $score));
    }

    private function calculateConsistency(array $data): float
    {
        $classification = $data['classification'] ?? [];
        $ead = $data['ead'] ?? [];

        if (empty($ead['record_count'])) {
            return 0;
        }

        $classifiedCount = ($classification['individual_count'] ?? 0) + ($classification['collective_count'] ?? 0);
        $eadCount = $ead['record_count'] ?? 0;

        if ($classifiedCount === 0 || $eadCount === 0) {
            return 0;
        }

        $mismatch = abs($classifiedCount - $eadCount);
        $tolerance = max(1, intval($eadCount * 0.01)); // 1% tolerance

        if ($mismatch > $tolerance) {
            return 50; // major inconsistency
        }

        return 100 - (($mismatch / $tolerance) * 50);
    }

    private function calculateValidity(array $data): float
    {
        $validation = $data['validation'] ?? [];
        $ead = $data['ead'] ?? [];

        $validationPassed = ($validation['status'] ?? null) === 'VALID' ? 100 : 0;
        $eadValid = ($ead['reconciliation_status'] ?? null) === 'PASS' ? 100 : (
            ($ead['reconciliation_status'] ?? null) === 'WARNING' ? 75 : 0
        );

        return ($validationPassed + $eadValid) / 2;
    }

    private function calculateHistoricalCoverage(array $data): float
    {
        $pdNetFlow = $data['pd_net_flow'] ?? [];
        $pdMigration = $data['pd_migration'] ?? [];

        $netFlowMonths = $pdNetFlow['historical_months'] ?? 0;
        $migrationMonths = $pdMigration['historical_months'] ?? 0;

        $minMonths = min($netFlowMonths, $migrationMonths);
        $adequateMonths = 24; // 24 months minimum

        if ($minMonths >= $adequateMonths) {
            return 100;
        }

        if ($minMonths >= 12) {
            return 80;
        }

        if ($minMonths > 0) {
            return 50;
        }

        return 0;
    }

    private function calculateReconciliation(array $data): float
    {
        $reconciliation = $data['reconciliation'] ?? [];

        if (!isset($reconciliation['reconciliation_checks'])) {
            return 0;
        }

        $checks = $reconciliation['reconciliation_checks'];
        $passedChecks = array_filter($checks, fn($check) => $check === true);
        $totalChecks = count($checks);

        if ($totalChecks === 0) {
            return 0;
        }

        return (count($passedChecks) / $totalChecks) * 100;
    }

    private function getQualityStatus(float $score): string
    {
        if ($score >= 85) {
            return 'APPROVED';
        }

        if ($score >= 70) {
            return 'WARNING';
        }

        return 'REJECTED';
    }
}
