<?php

namespace App\Services\PdNetflow;

use App\Services\Bucket\BucketAssignmentService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PdNetflowCalculator
{
    private const ROLLING_AVG_MONTHS = 6; // jendela rata-rata utk proyeksi Tabel 2
    private const DEFAULT_PROJECTION_MONTHS = 12;

    /**
     * Calculate 3 tabel PD Netflow dari snapshot data
     * 
     * @param int $assessmentPeriodId
     * @param string $groupKey format dinamis berbasis segment_dimensions (misal: 010|MURABAHAH|NA atau 010|MURABAHAH|MODAL_KERJA)
     */
    public function calculate(int $assessmentPeriodId, string $groupKey): void
    {
        // Seluruh hitung + tulis dibungkus SATU transaksi: bila gagal di tengah,
        // tidak menyisakan stage parsial (bucket_snapshots / transitions / compound_flows).
        DB::transaction(function () use ($assessmentPeriodId, $groupKey) {
            $this->doCalculate($assessmentPeriodId, $groupKey);
        });
    }

    /**
     * Implementasi kalkulasi aktual (dipanggil dalam transaksi oleh calculate()).
     */
    private function doCalculate(int $assessmentPeriodId, string $groupKey): void
    {
        $parts = explode('|', $groupKey);
        
        // Dynamic assignment based on length or standard 3-part fallback
        $officeCode = $parts[0] ?? 'NA';
        $akadCode   = $parts[1] ?? 'NA';
        $usageCode  = $parts[2] ?? 'NA';

        $assessmentPeriod = DB::table('assessment_periods')->where('id', $assessmentPeriodId)->first();
        $assessmentDate = $assessmentPeriod ? Carbon::parse($assessmentPeriod->assessment_date) : Carbon::now();

        // Get historical window dari config
        $historicalMonths = $this->getHistoricalWindowFromConfig($assessmentPeriodId);

        // Hapus hasil lama group ini agar periode di luar window tidak tertinggal (stale)
        $this->deleteGroupResults($assessmentPeriodId, $groupKey);

        // 0. Re-assign bucket_no snapshot window agar konsisten dgn skema bucket aktif
        $this->updateMissingBuckets($officeCode, $akadCode, $usageCode, $historicalMonths, $assessmentDate);

        // 1. Load snapshot historis untuk group ini (streaming — tidak memuat seluruh baris)
        // Tabel 1 window: (N-H-1) .. (N-1) — butuh P-1 dan P per transisi
        $agg = $this->aggregateSnapshots(
            $this->loadHistoricalSnapshots($officeCode, $akadCode, $usageCode, $historicalMonths, $assessmentDate)
        );

        if ($agg['empty']) {
            return;
        }

        // 2. Hitung Tabel 1: Min. Bucket (outstanding per bucket per periode)
        $this->calculateBucketSnapshots($assessmentPeriodId, $groupKey, $agg['bucket_outstanding'], $agg['counts']);

        // 3. Hitung Tabel 2: Perpindahan Bucket (historis dari Tabel 1 + proyeksi rolling avg 6 bln)
        $this->calculateTransitions($assessmentPeriodId, $groupKey, $agg['bucket_outstanding'], $agg['periods'], $historicalMonths, $assessmentDate);

        // 4. Hitung Tabel 3: Compound Flow to Loss (PD compound dari Tabel 2, + kolom PD Rate)
        $this->calculateCompoundFlows($assessmentPeriodId, $groupKey);
    }

    /**
     * Get historical window (bulan) dari stage_configs
     */
    private function getHistoricalWindowFromConfig(int $assessmentPeriodId): int
    {
        $config = DB::table('stage_configs')
            ->where('assessment_period_id', $assessmentPeriodId)
            ->value('historical_months');

        return $config ? (int)$config : 36; // default 36 bulan
    }

    /**
     * Jumlah bulan proyeksi ke depan utk Tabel 2/3 (default 12 bulan).
     * Kolom `projection_months` opsional; fallback ke default bila belum ada.
     */
    private function getProjectionMonthsFromConfig(int $assessmentPeriodId): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('stage_configs', 'projection_months')) {
            return self::DEFAULT_PROJECTION_MONTHS;
        }

        $config = DB::table('stage_configs')
            ->where('assessment_period_id', $assessmentPeriodId)
            ->value('projection_months');

        return $config ? (int) $config : self::DEFAULT_PROJECTION_MONTHS;
    }

    /**
     * Window Tabel 1: (N-H-1) .. (N-1) bulan.
     * N = bulan assessment, H = historical_months. Menghasilkan H+1 titik historis
     * sehingga terbentuk H pasangan transisi ((N-H)->(N-H+1) ... (N-1)->N historis).
     */
    private function tableOneStartYm(Carbon $assessmentDate, int $historicalMonths): string
    {
        return $assessmentDate->copy()->subMonths($historicalMonths + 1)->format('Ym');
    }

    private function tableOneEndYm(Carbon $assessmentDate): string
    {
        return $assessmentDate->copy()->subMonthNoOverflow()->format('Ym');
    }

    /**
     * Hapus hasil kalkulasi lama untuk satu group (3 tabel output).
     */
    private function deleteGroupResults(int $assessmentPeriodId, string $groupKey): void
    {
        DB::table('pd_netflow_bucket_snapshots')
            ->where('assessment_period_id', $assessmentPeriodId)
            ->where('group_key', $groupKey)
            ->delete();
        DB::table('pd_netflow_transitions')
            ->where('assessment_period_id', $assessmentPeriodId)
            ->where('group_key', $groupKey)
            ->delete();
        DB::table('pd_netflow_compound_flows')
            ->where('assessment_period_id', $assessmentPeriodId)
            ->where('group_key', $groupKey)
            ->delete();
    }

    /**
     * Normalize nilai periode_snapshot (bisa 'YYYYMM' atau 'YYYY-MM-DD') → 'YYYYMM'.
     */
    private function normalizePeriode(mixed $value): string
    {
        return substr((string) preg_replace('/\D/', '', (string) $value), 0, 6);
    }

    /**
     * Resolve daftar nilai mentah periode_snapshot dalam rentang [startYm, endYm] (perbandingan ternormalisasi).
     */
    private function resolvePeriodsInRange(string $startYm, string $endYm): array
    {
        return DB::table('snapshot')
            ->where('active_flag', true)
            ->distinct()
            ->pluck('periode_snapshot')
            ->filter(function ($p) use ($startYm, $endYm) {
                $ym = $this->normalizePeriode($p);

                return strlen($ym) === 6 && $ym >= $startYm && $ym <= $endYm;
            })
            ->values()
            ->all();
    }

    /**
     * Re-assign bucket_no utk snapshot dalam window Tabel 1: (N-H-1) .. (N-1).
     * Selalu re-assign SEMUA baris (bukan hanya yang NULL) agar skema bucket
     * terjamin konsisten dgn skema aktif di BucketAssignmentService.
     */
    private function updateMissingBuckets(string $officeCode, string $akadCode, string $usageCode, int $historicalMonths, Carbon $assessmentDate): int
    {
        $windowPeriods = $this->resolvePeriodsInRange(
            $this->tableOneStartYm($assessmentDate, $historicalMonths),
            $this->tableOneEndYm($assessmentDate),
        );
        if (empty($windowPeriods)) {
            return 0;
        }

        $query = DB::table('snapshot')
            ->where('active_flag', true)
            ->whereIn('periode_snapshot', $windowPeriods);

        if ($officeCode !== 'NA') $query->where('office_code', $officeCode);
        if ($akadCode !== 'NA') $query->where('financing_contract_code', $akadCode);
        if ($usageCode !== 'NA') $query->where('usage_type', $usageCode);

        $periods = $query->distinct()->pluck('periode_snapshot');

        if ($periods->isEmpty()) {
            return 0;
        }

        $bucketService = app(BucketAssignmentService::class);
        $totalUpdated = 0;

        foreach ($periods as $periodeYm) {
            $updated = $bucketService->assignBucketsForPeriodFast($periodeYm);
            $totalUpdated += $updated;
        }

        return $totalUpdated;
    }

    /**
     * Load historical snapshots (Tabel 1) filtered by group segmentasi.
     * Window: (N-H-1) .. (N-1)
     */
    private function loadHistoricalSnapshots(string $officeCode, string $akadCode, string $usageCode, int $months, Carbon $assessmentDate)
    {
        $windowPeriods = $this->resolvePeriodsInRange(
            $this->tableOneStartYm($assessmentDate, $months),
            $this->tableOneEndYm($assessmentDate),
        );
        if (empty($windowPeriods)) {
            return null;
        }

        $query = DB::table('snapshot')
            ->where('active_flag', true)
            ->whereNotNull('bucket_no')
            ->whereIn('periode_snapshot', $windowPeriods);

        if ($officeCode !== 'NA') $query->where('office_code', $officeCode);
        if ($akadCode !== 'NA') $query->where('financing_contract_code', $akadCode);
        if ($usageCode !== 'NA') $query->where('usage_type', $usageCode);

        return $query->orderBy('periode_snapshot')
            ->orderBy('loan_number');
    }

    /**
     * Stream snapshot historis (cursor) → agregasi memori ringkas.
     * Semantik identik dgn versi ->get() lama (group by periode_bucket, sum outstanding,
     * count rekening, daftar periode unik terurut) tanpa memuat seluruh baris ke memori.
     *
     * @return array{bucket_outstanding: array<string, float>, counts: array<string, int>, periods: list<string>, empty: bool}
     */
    private function aggregateSnapshots($query): array
    {
        if ($query === null) {
            return ['bucket_outstanding' => [], 'counts' => [], 'periods' => [], 'empty' => true];
        }

        $bucketOutstanding = []; // "periode_bucket" => sum outstanding
        $counts = [];            // "periode_bucket" => count rekening
        $periods = [];           // daftar periode_snapshot unik

        foreach ($query->cursor() as $s) {
            $key = $s->periode_snapshot . '_' . $s->bucket_no;
            $bucketOutstanding[$key] = ($bucketOutstanding[$key] ?? 0) + (float) $s->outstanding_balance;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            if (! in_array($s->periode_snapshot, $periods, true)) {
                $periods[] = $s->periode_snapshot;
            }
        }

        // Samakan dgn sort() koleksi lama (nilai string YYYYMM sama panjang).
        sort($periods);

        return [
            'bucket_outstanding' => $bucketOutstanding,
            'counts'             => $counts,
            'periods'            => $periods,
            'empty'              => $counts === [],
        ];
    }

    /**
     * Tabel 1: Calculate outstanding per bucket per periode
     */
    private function calculateBucketSnapshots(int $periodId, string $groupKey, array $bucketOutstanding, array $counts): void
    {
        $batch = [];
        $now = now();
        foreach ($bucketOutstanding as $key => $outstanding) {
            [$periode, $bucketNo] = explode('_', $key);

            // Format YYYYMM to YYYY-MM-DD for date column (normalize format campuran)
            $date = Carbon::createFromFormat('Ym', $this->normalizePeriode($periode))->endOfMonth()->toDateString();

            $batch[] = [
                'assessment_period_id' => $periodId,
                'group_key' => $groupKey,
                'bucket_no' => (int) $bucketNo,
                'periode_snapshot' => $date,
                'outstanding_balance' => $outstanding,
                'account_count' => $counts[$key] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($batch)) {
            DB::table('pd_netflow_bucket_snapshots')->upsert(
                $batch,
                ['assessment_period_id', 'group_key', 'bucket_no', 'periode_snapshot'], // Unique key
                ['outstanding_balance', 'account_count', 'updated_at']
            );
        }
    }

    /**
     * Tabel 2: Calculate transition rate (outstanding ratio)
     *
     * Periode historis (N-H)..(N-1): dihitung dari outstanding Tabel 1
     * Formula: transition_rate(i→i+1, P) = Outstanding_bucket_i+1(P) / Outstanding_bucket_i(P-1)
     *
     * Periode proyeksi N..(N+K-1): rata-rata bergerak dari ROLLING_AVG_MONTHS
     * bulan terakhir Tabel 2 itu sendiri (bukan dari snapshot).
     */
    private function calculateTransitions(int $periodId, string $groupKey, array $bucketOutstanding, array $periods, int $historicalMonths, Carbon $assessmentDate): void
    {
        // Window historis Tabel 2: (N-H) .. (N-1)
        $histStartYm = $assessmentDate->copy()->subMonths($historicalMonths)->format('Ym');
        $histEndYm = $this->tableOneEndYm($assessmentDate);

        $rates = []; // [fromBucket][ym] = rate, urutan insert kronologis utk rolling average
        $batch = [];
        $now = now();

        foreach ($periods as $i => $periode) {
            if ($i === 0) continue; // Skip titik pertama Tabel 1 (hanya dipakai sebagai prev)

            $prevPeriode = $periods[$i - 1];
            $ym = $this->normalizePeriode($periode);

            if ($ym < $histStartYm || $ym > $histEndYm) continue;

            $date = Carbon::createFromFormat('Ym', $ym)->endOfMonth()->toDateString();

            // Skema bucket: 1..13 lalu 14 (loss). Transisi 1→2, 2→3, ... 13→14.
            for ($fromBucket = 1; $fromBucket <= 13; $fromBucket++) {
                $toBucket = $fromBucket + 1;
                $rate = $this->computeTransitionRate($bucketOutstanding, $prevPeriode, $periode, $fromBucket, $toBucket);
                $rates[$fromBucket][$ym] = $rate;

                $batch[] = [
                    'assessment_period_id' => $periodId,
                    'group_key' => $groupKey,
                    'from_bucket' => $fromBucket,
                    'to_bucket' => $toBucket,
                    'periode' => $date,
                    'transition_rate' => round($rate, 6),
                    'flow_count' => 0, // ponytail: loan-level tracking for audit
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Proyeksi: N..(N+K-1) — rata-rata 6 bulan ke belakang dari Tabel 2 itu sendiri
        $projectionMonths = $this->getProjectionMonthsFromConfig($periodId);
        $projectionStart = $assessmentDate->copy()->startOfMonth();

        for ($k = 0; $k < $projectionMonths; $k++) {
            $projDate = $projectionStart->copy()->addMonths($k);
            $ym = $projDate->format('Ym');
            $date = $projDate->endOfMonth()->toDateString();

            for ($fromBucket = 1; $fromBucket <= 13; $fromBucket++) {
                $lastRates = array_slice(array_values($rates[$fromBucket] ?? []), -self::ROLLING_AVG_MONTHS);
                // Guard division by zero: bila belum ada rate historis, proyeksi = 0.
                $rate = count($lastRates) > 0 ? array_sum($lastRates) / count($lastRates) : 0.0;
                $rates[$fromBucket][$ym] = $rate;

                $batch[] = [
                    'assessment_period_id' => $periodId,
                    'group_key' => $groupKey,
                    'from_bucket' => $fromBucket,
                    'to_bucket' => $fromBucket + 1,
                    'periode' => $date,
                    'transition_rate' => round($rate, 6),
                    'flow_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($batch)) {
            DB::table('pd_netflow_transitions')->upsert(
                $batch,
                ['assessment_period_id', 'group_key', 'from_bucket', 'to_bucket', 'periode'], // Unique columns
                ['transition_rate', 'flow_count', 'updated_at'] // Columns to update on duplicate
            );
        }
    }

    /**
     * Rate satu transisi: Outstanding_to_bucket(P) / Outstanding_from_bucket(P-1)
     */
    private function computeTransitionRate($bucketOutstanding, string $prevPeriode, string $periode, int $fromBucket, int $toBucket): float
    {
        $outstandingFrom = $bucketOutstanding["{$prevPeriode}_{$fromBucket}"] ?? 0;
        $outstandingTo = $bucketOutstanding["{$periode}_{$toBucket}"] ?? 0;

        $rate = $outstandingFrom > 0 ? $outstandingTo / $outstandingFrom : 0.0;
        // Cap rate at 99.999999 to prevent Decimal(8,6) Out of Range SQL Error
        return min(99.999999, $rate);
    }

    /**
     * Tabel 3: Calculate compound PD (chain probability ke loss bucket 14)
     */
    private function calculateCompoundFlows(int $periodId, string $groupKey): void
    {
        // Load transition rates
        $transitions = DB::table('pd_netflow_transitions')
            ->where('assessment_period_id', $periodId)
            ->where('group_key', $groupKey)
            ->get()
            ->groupBy('periode');

        $batch = [];
        $now = now();

        foreach ($transitions as $periode => $trans) {
            // Build transition matrix untuk periode ini
            $matrix = [];
            foreach ($trans as $t) {
                $matrix[$t->from_bucket][$t->to_bucket] = (float) $t->transition_rate;
            }

            // Calculate compound PD dari setiap source bucket ke bucket 14 (loss)
            for ($sourceBucket = 1; $sourceBucket <= 13; $sourceBucket++) {
                $compoundPd = $this->calculateChainProbability($matrix, $sourceBucket, 14);

                $batch[] = [
                    'assessment_period_id' => $periodId,
                    'group_key' => $groupKey,
                    'source_bucket' => $sourceBucket,
                    'periode' => $periode,
                    'compound_pd' => round($compoundPd, 6),
                    'flow_path' => json_encode([$sourceBucket, 14]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($batch)) {
            DB::table('pd_netflow_compound_flows')->upsert(
                $batch,
                ['assessment_period_id', 'group_key', 'source_bucket', 'periode'], // Unique key
                ['compound_pd', 'flow_path', 'updated_at']
            );
        }
    }

    /**
     * Calculate compound PD dari source bucket ke loss bucket (14).
     * Formula: CF(source) = P(source→source+1) × P(source+1→source+2) × ... × P(13→14)
     * Skema bucket: 1..13 lalu 14 (loss) — transisi selalu +1 hingga loss.
     */
    private function calculateChainProbability(array $matrix, int $source, int $target): float
    {
        if ($source >= $target) {
            return 0.0;
        }

        // Rantai mengikuti urutan bucket nyata: 1 → 2 → ... → 13 → 14
        $compoundPd = 1.0;
        $from = $source;
        while ($from < $target) {
            $to = $from + 1;
            $rate = $matrix[$from][$to] ?? 0.0;
            $compoundPd *= $rate;
            $from = $to;
        }

        return min(1.0, max(0.0, $compoundPd));
    }
}
