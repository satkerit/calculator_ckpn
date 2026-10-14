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
     * Daftar ID bucket aktif terurut (sumber tunggal skema bucket untuk display).
     * Dinamis mengikuti konfigurasi PD Netflow Bucket Config — bukan hardcode 1..14.
     */
    public function getActiveBucketIds(): array
    {
        return \App\Models\BucketDefinition::query()
            ->where('active', true)
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->all();
    }

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
            'compound_periods' => $this->extractCompoundPeriods($compounds),
        ];
    }

    /**
     * Tabel 1: Min. Bucket – outstanding per bucket per periode
     * Format: bucket_no | periode1 | periode2 | ...
     * Memastikan bucket 1 s/d 14 selalu ada di output.
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
        foreach ($this->getActiveBucketIds() as $bucketNo) {
            $bucketRows = $rows->get($bucketNo, collect());
            $periods = [];
            foreach ($bucketRows as $row) {
                $key = $row->periode_snapshot instanceof \Carbon\Carbon
                    ? $row->periode_snapshot->format('Y-m-d')
                    : (string) $row->periode_snapshot;
                $periods[$key] = $row->outstanding_balance;
            }
            $result[$bucketNo] = [
                'bucket_no' => $bucketNo,
                'hari_tunggakan' => $this->getBucketLabel($bucketNo),
                'periods' => $periods,
            ];
        }

        return $result;
    }

    /**
     * Tabel 2: Perpindahan Bucket – transition rate
     * Format: from_bucket→to_bucket | periode1 | periode2 | ...
     * Memastikan semua transisi 1..13 selalu ada.
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

        $bucketIds = $this->getActiveBucketIds();

        $result = [];
        for ($b = 0; $b < count($bucketIds) - 1; $b++) {
            $from = $bucketIds[$b];
            $to = $bucketIds[$b + 1];
            $key = "{$from}_{$to}";
            $transRows = $rows->get($key, collect());

            $periods = [];
            $flowCounts = [];
            foreach ($transRows as $row) {
                $periodeKey = $row->periode instanceof \Carbon\Carbon
                    ? $row->periode->format('Y-m-d')
                    : (string) $row->periode;
                $periods[$periodeKey] = $row->transition_rate;
                $flowCounts[$periodeKey] = $row->flow_count;
            }

            $result[$key] = [
                'from_bucket' => $from,
                'to_bucket' => $to,
                'label' => "{$from} to {$to}",
                'periods' => $periods,
                'flow_counts' => $flowCounts,
            ];
        }

        return $result;
    }

    /**
     * Tabel 3: Compound Flow to Loss – PD compound
     * Format: source_bucket | periode1 | periode2 | ...
     * Memastikan source bucket 1..13 selalu ada.
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

        $bucketIds = $this->getActiveBucketIds();
        $result = [];
        // Bucket terakhir = loss, tidak punya compound flow
        for ($b = 0; $b < count($bucketIds) - 1; $b++) {
            $sourceBucket = $bucketIds[$b];
            $compoundRows = $rows->get($sourceBucket, collect());
            
            $periods = [];
            foreach ($compoundRows as $row) {
                $periodeKey = $row->periode instanceof \Carbon\Carbon
                    ? $row->periode->format('Y-m-d')
                    : (string) $row->periode;
                $periods[$periodeKey] = $row->compound_pd;
            }

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
     * Extract unique periode dari transitions (column headers Tabel 2).
     * Tabel 2 memuat data historis + proyeksi 12 bulan ke depan.
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
     * Extract unique periode dari compounds (column headers Tabel 3).
     * Berakhir di periode historis akhir Tabel 1 (tanpa proyeksi 12 bulan Tabel 2).
     */
    private function extractCompoundPeriods(array $compounds): array
    {
        $periods = [];
        foreach ($compounds as $compound) {
            $periods = array_unique(array_merge($periods, array_keys($compound['periods'] ?? [])));
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
