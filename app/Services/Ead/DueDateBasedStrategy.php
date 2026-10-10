<?php

namespace App\Services\Ead;

/**
 * EAD berbasis jatuh tempo: principal_arrears jika sudah expired, 0 jika belum.
 * Dipakai oleh Musyarakah & IMBT (logika identik).
 */
abstract class DueDateBasedStrategy implements EadStrategyInterface
{
    protected array $config = [];

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function calculate(array $snapshot, \DateTime $assessmentDate): float
    {
        $expiredDate = $snapshot['expired_date'] ?? null;

        // Tanpa jatuh tempo → EAD 0 (config requires_due_date juga menuntut ini)
        if (!$expiredDate) {
            return 0.0;
        }

        $expiredDateObj = $expiredDate instanceof \DateTime ? $expiredDate : new \DateTime($expiredDate);

        // Sudah jatuh tempo → EAD = principal_arrears
        return $expiredDateObj <= $assessmentDate
            ? (float) ($snapshot['principal_arrears'] ?? 0)
            : 0.0;
    }
}
