<?php

namespace App\Services\PdNetflow;

use App\Services\Bucket\BucketAssignmentService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PdNetflowCalculator
{
    private const ROLLING_AVG_MONTHS = 6;
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
        $this->calculateBucketSnapshots($assessmentPeriodId, $groupKey, $agg['bucket_outstanding'], $agg['counts'], $agg['periods']);

        $histEndYm = $this->tableOneEndYm($assessmentDate);
        $histEndDate = Carbon::createFromFormat('Ym', $histEndYm)->endOfMonth()->toDateString();

        // 3. Hitung Tabel 2: Perpindahan Bucket (historis + proyeksi 12 bulan ke depan)
        $this->calculateTransitions($assessmentPeriodId, $groupKey, $agg['bucket_outstanding'], $agg['periods'], $historicalMonths, $assessmentDate);

        // 4. Hitung Tabel 3: Compound Flow to Loss (hanya sampai akhir periode historis Tabel 1, tanpa proyeksi)
        $this->calculateCompoundFlows($assessmentPeriodId, $groupKey, $histEndDate);
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
     * Tabel 1: Calculate outstanding per bucket per periode.
     * Iterasi semua bucket dari bucket_definitions (dinamis, bukan hardcode 1..14).
     */
    private function calculateBucketSnapshots(int $periodId, string $groupKey, array $bucketOutstanding, array $counts, array $periods): void
    {
        $bucketIds = DB::table('bucket_definitions')
            ->where('active', true)
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->all();

        if (empty($bucketIds)) {
            return;
        }

        $batch = [];
        $now = now();

        foreach ($periods as $periode) {
            $date = Carbon::createFromFormat('Ym', $this->normalizePeriode($periode))->endOfMonth()->toDateString();

            foreach ($bucketIds as $bucketNo) {
                $key = "{$periode}_{$bucketNo}";
                $outstanding = $bucketOutstanding[$key] ?? 0.0;
                $count = $counts[$key] ?? 0;

                $batch[] = [
                    'assessment_period_id' => $periodId,
                    'group_key' => $groupKey,
                    'bucket_no' => $bucketNo,
                    'periode_snapshot' => $date,
                    'outstanding_balance' => (float) $outstanding,
                    'account_count' => (int) $count,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
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
     * Periode historis (N-H)..(N-1): dihitung dari outstanding Tabel 1.
     * Formula: transition_rate(i→i+1, P) = Outstanding_bucket_i+1(P) / Outstanding_bucket_i(P-1)
     *
     * Periode proyeksi N..(N+K-1): proyeksi 12 bulan ke depan menggunakan rata-rata bergerak
     * 6 bulan ke belakang dari Tabel 2 itu sendiri.
     */
    public function calculateTransitions(int $periodId, string $groupKey, array $bucketOutstanding, array $periods, int $historicalMonths, Carbon $assessmentDate): void
    {
        // Window historis Tabel 2: (N-H) .. (N-1)
        $histStartYm = $assessmentDate->copy()->subMonths($historicalMonths)->format('Ym');
        $histEndYm = $this->tableOneEndYm($assessmentDate);

        // Load bucket definitions dinamis (urutan menentukan transisi berikutnya)
        $bucketIds = DB::table('bucket_definitions')
            ->where('active', true)
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->all();

        if (empty($bucketIds)) {
            return;
        }

        $rates = []; // [fromBucket][ym] = rate, urutan insert kronologis utk rolling average
        $batch = [];
        $now = now();

        foreach ($periods as $i => $periode) {
            if ($i === 0) continue; // Skip titik pertama Tabel 1 (hanya dipakai sebagai prev)

            $prevPeriode = $periods[$i - 1];
            $ym = $this->normalizePeriode($periode);

            if ($ym < $histStartYm || $ym > $histEndYm) continue;

            $date = Carbon::createFromFormat('Ym', $ym)->endOfMonth()->toDateString();

            // Transisi dari setiap bucket ke bucket BERIKUTNYA dalam urutan definisi
            // (bukan fromBucket+1 — custom bucket bisa punya ID non-sekuensial)
            for ($b = 0; $b < count($bucketIds) - 1; $b++) {
                $fromBucket = $bucketIds[$b];
                $toBucket = $bucketIds[$b + 1];
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

        // Proyeksi 12 bulan ke depan: N..(N+K-1) — rata-rata 6 bulan ke belakang dari Tabel 2 itu sendiri
        $projectionMonths = self::DEFAULT_PROJECTION_MONTHS;
        $projectionStart = $assessmentDate->copy()->startOfMonth();

        for ($k = 0; $k < $projectionMonths; $k++) {
            $projDate = $projectionStart->copy()->addMonths($k);
            $ym = $projDate->format('Ym');
            $date = $projDate->endOfMonth()->toDateString();

            // Proyeksi utk semua pasangan transisi (dinamis, bukan hardcode 1..13)
            for ($b = 0; $b < count($bucketIds) - 1; $b++) {
                $fromBucket = $bucketIds[$b];

                $lastRates = array_slice(array_values($rates[$fromBucket] ?? []), -self::ROLLING_AVG_MONTHS);
                // Rumus Excel: =ROUND(AVERAGEIF(AG25:AL25,">0"),2)
                // Hanya rata-rata nilai yang > 0 dari 6 bulan terakhir
                $nonZeroRates = array_filter($lastRates, fn($r) => $r > 0);
                $rate = count($nonZeroRates) > 0 ? array_sum($nonZeroRates) / count($nonZeroRates) : 0.0;
                $rates[$fromBucket][$ym] = $rate;

                $batch[] = [
                    'assessment_period_id' => $periodId,
                    'group_key' => $groupKey,
                    'from_bucket' => $fromBucket,
                    'to_bucket' => $bucketIds[$b + 1],
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
     * Rumus Excel: =IF(ISBLANK(prev),1,IFERROR(to/from,0))
     * Jika prev blank/0 → rate = 1.0 (tidak 0.0)
     */
    private function computeTransitionRate($bucketOutstanding, string $prevPeriode, string $periode, int $fromBucket, int $toBucket): float
    {
        $outstandingFrom = $bucketOutstanding["{$prevPeriode}_{$fromBucket}"] ?? 0;
        $outstandingTo = $bucketOutstanding["{$periode}_{$toBucket}"] ?? 0;

        $rate = $outstandingFrom > 0 ? $outstandingTo / $outstandingFrom : 1.0;
        // Cap rate at 99.999999 to prevent Decimal(8,6) Out of Range SQL Error
        return min(99.999999, $rate);
    }

    /**
     * Tabel 3: Calculate compound PD (diagonal multiplication lintas periode)
     * Bucket i periode t: rate[i→i+1][t] × rate[i+1→i+2][t+1] × ... × rate[13→14][t+(13-i)]
     * Hanya historis menjadi titik awal; butuh data proyeksi untuk diagonal lengkap.
     */
    private function calculateCompoundFlows(int $periodId, string $groupKey, string $histEndDate): void
    {
        // Load SEMUA transition (historis + proyeksi)
        $allTransitions = DB::table('pd_netflow_transitions')
            ->where('assessment_period_id', $periodId)
            ->where('group_key', $groupKey)
            ->orderBy('periode', 'asc')
            ->get();

        if ($allTransitions->isEmpty()) {
            return;
        }

        // Normalisasi periode key: cast Carbon/string ke YYYY-MM-DD format
        $normalized = collect();
        foreach ($allTransitions->groupBy('periode') as $periode => $group) {
            $key = $periode instanceof \Carbon\Carbon
                ? $periode->format('Y-m-d')
                : (string) $periode;
            $normalized[$key] = $group;
        }

        // Susun periode terurut sebagai indexed array
        $sortedPeriodes = array_keys($normalized->toArray());
        $periodIndex = array_flip($sortedPeriodes);
        
        // Tentukan akhir periode historis (batas dimulainya proyeksi)
        $histEndIdx = $periodIndex[$histEndDate] ?? null;
        if ($histEndIdx === null) {
            return;
        }

        // Load bucket definitions dinamis
        $bucketIds = DB::table('bucket_definitions')
            ->where('active', true)
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->all();

        if (empty($bucketIds)) {
            return;
        }

        $maxBucketId = max($bucketIds);
        $batch = [];
        $now = now();

        // Outstanding per (periode, bucket) untuk mendeteksi rate 1.0 hasil default
        // "prev blank" (rumus Excel IF(ISBLANK(prev),1,...)), bukan migrasi nyata
        $outstandingMap = [];
        foreach (DB::table('pd_netflow_bucket_snapshots')
            ->where('assessment_period_id', $periodId)
            ->where('group_key', $groupKey)
            ->get() as $snap) {
            $snapKey = $snap->periode_snapshot instanceof \Carbon\Carbon
                ? $snap->periode_snapshot->format('Y-m-d')
                : (string) $snap->periode_snapshot;
            $outstandingMap[$snapKey][$snap->bucket_no] = (float) $snap->outstanding_balance;
        }

        // Iterate hanya periode historis sebagai titik awal (indeks 0 s/d histEndIdx)
        for ($startIdx = 0; $startIdx <= $histEndIdx; $startIdx++) {
            $startPeriode = $sortedPeriodes[$startIdx];

            // Untuk setiap source bucket (kecuali bucket loss/max)
            for ($b = 0; $b < count($bucketIds) - 1; $b++) {
                $sourceBucket = $bucketIds[$b];

                $compoundPd = 1.0;
                $totalSteps = count($bucketIds) - 1 - $b;

                // Diagonal: bucket i→i+1 di t, i+1→i+2 di t+1, ..., (max-1)→max di t+(max-i-1)
                for ($step = 0; $step < $totalSteps; $step++) {
                    $fromBucket = $bucketIds[$b + $step];
                    $toBucket = $bucketIds[$b + $step + 1];
                    $stepPeriodeIdx = $startIdx + $step;

                    // Jika periode tidak ada dalam data, gunakan 0.0
                    if ($stepPeriodeIdx >= count($sortedPeriodes)) {
                        $rate = 0.0;
                    } else {
                        $stepPeriode = $sortedPeriodes[$stepPeriodeIdx];
                        $transitions = $normalized[$stepPeriode] ?? [];

                        // Cari rate dari_bucket → bucket berikutnya
                        $rate = 0.0;
                        foreach ($transitions as $t) {
                            if ($t->from_bucket == $fromBucket && $t->to_bucket == $toBucket) {
                                $rate = (float) $t->transition_rate;
                                break;
                            }
                        }

                        // Rate 1.0 karena outstanding bucket asal kosong pada periode
                        // sebelumnya = tidak ada data → compound = 0 (bukan 1)
                        if ($rate == 1.0) {
                            $prevDate = \Carbon\Carbon::parse($stepPeriode)->subMonth()->endOfMonth()->toDateString();
                            $prevOutstanding = $outstandingMap[$prevDate][$fromBucket] ?? 0.0;
                            if ($prevOutstanding <= 0) {
                                $rate = 0.0;
                            }
                        }
                    }

                    $compoundPd *= $rate;
                }

                $batch[] = [
                    'assessment_period_id' => $periodId,
                    'group_key' => $groupKey,
                    'source_bucket' => $sourceBucket,
                    'periode' => $startPeriode,
                    'compound_pd' => round(min(1.0, max(0.0, $compoundPd)), 6),
                    'flow_path' => json_encode([$sourceBucket, $maxBucketId]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= 500) {
                    DB::table('pd_netflow_compound_flows')->upsert(
                        $batch,
                        ['assessment_period_id', 'group_key', 'source_bucket', 'periode'],
                        ['compound_pd', 'flow_path', 'updated_at']
                    );
                    $batch = [];
                }
            }
        }

        if (!empty($batch)) {
            DB::table('pd_netflow_compound_flows')->upsert(
                $batch,
                ['assessment_period_id', 'group_key', 'source_bucket', 'periode'],
                ['compound_pd', 'flow_path', 'updated_at']
            );
        }
    }
}
