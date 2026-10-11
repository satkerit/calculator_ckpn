<?php

namespace App\Services\PdNetflow;

use App\Models\AssessmentPeriod;
use App\Models\PdNetflowBucketSnapshot;
use App\Models\PdNetflowTransition;
use App\Models\PdNetflowCompoundFlow;
use Illuminate\Support\Collection;

class PdNetflowService
{
    /**
     * Load 3 tabel per group: bucket snapshots, transitions, compound flows
     */
    public function loadGroupDetail(int $assessmentPeriodId, string $groupKey): array
    {
        $buckets = $this->loadBucketSnapshots($assessmentPeriodId, $groupKey);
        $transitions = $this->loadTransitions($assessmentPeriodId, $groupKey);
        $compounds = $this->loadCompoundFlows($assessmentPeriodId, $groupKey);

        return [
            'buckets' => $buckets,
            'transitions' => $transitions,
            'compounds' => $compounds,
            'periods' => $this->extractPeriods($buckets),
            'transition_periods' => $this->extractTransitionPeriods($transitions),
        ];
    }

    /**
     * Tabel 1: Min. Bucket – outstanding per bucket per periode
     * Format: bucket_no | periode1 | periode2 | ...
     */
    private function loadBucketSnapshots(int $assessmentPeriodId, string $groupKey): array
    {
        $rows = PdNetflowBucketSnapshot::query()
            ->where('assessment_period_id', $assessmentPeriodId)
            ->where('group_key', $groupKey)
            ->orderBy('periode_snapshot')
            ->orderBy('bucket_no')
            ->get()
            ->groupBy('bucket_no');

        $result = [];
        foreach ($rows as $bucketNo => $bucketRows) {
            $result[$bucketNo] = [
                'bucket_no' => $bucketNo,
                'hari_tunggakan' => $this->getBucketLabel($bucketNo),
                'periods' => $bucketRows->pluck('outstanding_balance', 'periode_snapshot')->toArray(),
            ];
        }

        ksort($result);
        return $result;
    }

    /**
     * Tabel 2: Perpindahan Bucket – transition rate
     * Format: from_bucket→to_bucket | periode1 | periode2 | ...
     */
    private function loadTransitions(int $assessmentPeriodId, string $groupKey): array
    {
        $rows = PdNetflowTransition::query()
            ->where('assessment_period_id', $assessmentPeriodId)
            ->where('group_key', $groupKey)
            ->orderBy('periode')
            ->orderBy('from_bucket')
            ->orderBy('to_bucket')
            ->get()
            ->groupBy(fn ($r) => "{$r->from_bucket}_{$r->to_bucket}");

        $result = [];
        foreach ($rows as $key => $transRows) {
            [$from, $to] = explode('_', $key);
            $result[$key] = [
                'from_bucket' => (int) $from,
                'to_bucket' => (int) $to,
                'label' => "{$from} to {$to}",
                'periods' => $transRows->pluck('transition_rate', 'periode')->toArray(),
                'flow_counts' => $transRows->pluck('flow_count', 'periode')->toArray(),
            ];
        }

        return $result;
    }

    /**
     * Tabel 3: Compound Flow to Loss – PD compound
     * Format: source_bucket | periode1 | periode2 | ...
     */
    public function loadCompoundFlows(int $assessmentPeriodId, string $groupKey): array
    {
        $rows = PdNetflowCompoundFlow::query()
            ->where('assessment_period_id', $assessmentPeriodId)
            ->where('group_key', $groupKey)
            ->orderBy('periode')
            ->orderBy('source_bucket')
            ->get()
            ->groupBy('source_bucket');

        $result = [];
        foreach ($rows as $sourceBucket => $compoundRows) {
            $periods = $compoundRows->pluck('compound_pd', 'periode')->toArray();

            $result[$sourceBucket] = [
                'source_bucket' => $sourceBucket,
                'label' => $this->getBucketLabel($sourceBucket),
                'periods' => $periods,
                // Kolom terakhir: PD Rate per bucket = MIN(100%, AVERAGE PD compound semua periode).
                'pd_rate' => !empty($periods) ? min(1.0, array_sum($periods) / count($periods)) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * Extract unique periode dari bucket snapshots (column headers)
     */
    private function extractPeriods(array $buckets): array
    {
        $periods = [];
        foreach ($buckets as $bucket) {
            $periods = array_unique(array_merge($periods, array_keys($bucket['periods'] ?? [])));
        }
        sort($periods);
        return $periods;
    }

    /**
     * Extract unique periode dari transitions (column headers Tabel 2 & 3).
     * Tabel 2/3 berperiode (N-H)..(N+K-1) — berbeda dari periode Tabel 1.
     */
    private function extractTransitionPeriods(array $transitions): array
    {
        $periods = [];
        foreach ($transitions as $transition) {
            $periods = array_unique(array_merge($periods, array_keys($transition['periods'] ?? [])));
        }
        sort($periods);
        return $periods;
    }

    /**
     * Map bucket_no ke label hari tunggakan.
     * Sumber tunggal label: BucketAssignmentService (skema 1=Lancar/0 hari, 2-13 per
     * interval 30 hari, 14=loss) agar tampilan T1 konsisten dgn pengisian bucket.
     */
    private function getBucketLabel(int $bucketNo): string
    {
        return \App\Services\Bucket\BucketAssignmentService::getBucketLabel($bucketNo);
    }

    /**
     * Format nilai untuk display
     */
    public function formatValue(mixed $value, string $type = 'number'): string
    {
        if ($value === null) {
            return '-';
        }

        return match ($type) {
            'rate' => number_format((float) $value, 4, ',', '.'),
            'currency' => number_format((float) $value, 0, ',', '.'),
            'percent' => number_format((float) $value * 100, 2, ',', '.') . '%',
            default => number_format((float) $value, 2, ',', '.'),
        };
    }
}
