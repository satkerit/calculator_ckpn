<?php

namespace App\Http\Controllers\Calculation;

use App\Models\Collateral;
use App\Services\Ckpn\CkpnCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Modul 8: Detail CKPN (Individual + Kolektif).
 *
 * Menghitung CKPN final:
 * - Individual: nilai_tercatat - net_recovery_value (liquidity * (1 - sale_cost))
 * - Kolektif  : EAD × PD × LGD (LGD per metode konfigurasi: ER / CS / WEIGHTED)
 */
class DetailCkpnController extends BaseCalculationController
{
    public function index(Request $request): View
    {
        $period = $this->resolvePeriod($request);
        $periods = $this->periods();
        $config = $this->configFor($period->id);

        $individuals = $this->individualRows($period->id);
        $collectives = $this->collectiveRows($period->id);
        $summary = $this->summary($period->id);
        // Hasil basi: konfigurasi berubah setelah PD/LGD/CKPN terakhir dihitung.
        $stale = $this->staleResultTypes($period->id);

        return view('calculation.modules.detail_ckpn', compact(
            'period',
            'periods',
            'config',
            'individuals',
            'collectives',
            'summary',
            'stale',
        ));
    }

    public function finalize(Request $request): RedirectResponse|JsonResponse
    {
        $period = $this->resolvePeriod($request);
        $config = $this->configFor($period->id);

        // CKPN hanya boleh dihitung setelah PD dan LGD tersedia untuk periode ini.
        // PD harus ada sesuai metode aktif di konfigurasi (NET_FLOW / MIGRATION).
        $pdCount = DB::table('stage_pd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', $config['pd_method'])
            ->count();
        $lgdCount = DB::table('stage_lgd_results')->where('assessment_period_id', $period->id)->count();
        $classCount = DB::table('stage_classifications')->where('assessment_period_id', $period->id)->count();

        if ($classCount === 0) {
            return $this->backTo($request, 'calculation.module.detail_ckpn', [], 'Klasifikasi belum dijalankan. Jalankan modul Classification terlebih dahulu.');
        }
        if ($pdCount === 0) {
            return $this->backTo($request, 'calculation.module.detail_ckpn', [], "Nilai PD dengan metode {$config['pd_method']} belum tersedia. Jalankan modul PD Net Flow / Migration sesuai metode konfigurasi terlebih dahulu.");
        }

        // PD harus mencakup SETIAP kombinasi segmen × bucket kolektif, bukan hanya
        // sebagian. Kombinasi tanpa baris PD akan dihitung dengan PD=0 (CKPN
        // understated) — bukan memakai rate sesuai segmentasi + bucket-nya.
        // Cegah sebelum menyimpan hasil.
        // NET_FLOW: bucket_no (1..14). MIGRATION: bucket_no = quality_rating (1..5).
        // Keduanya wajib punya PD untuk setiap kombinasi segmen × dimensi risiko.
        $eadBucketKeys = $this->eadGroups($period->id)
            ->map(fn ($eg) => $eg->bucket_key)
            ->unique();
        $pdBucketKeys = DB::table('stage_pd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', $config['pd_method'])
            ->get()
            ->map(fn ($r) => $r->group_key.'|'.(int) ($r->bucket_no ?? 1))
            ->unique();
        $missingPd = $eadBucketKeys->diff($pdBucketKeys);

        if ($missingPd->isNotEmpty()) {
            $sample = implode(', ', $missingPd->take(5)->all());
            $more = $missingPd->count() > 5 ? ' (+'.($missingPd->count() - 5).' lainnya)' : '';

            return $this->backTo($request, 'calculation.module.detail_ckpn', [], "Rate PD metode {$config['pd_method']} belum lengkap untuk {$missingPd->count()} kombinasi segmentasi × bucket: {$sample}{$more}. Hitung ulang modul PD agar setiap segmentasi memiliki rate PD per bucket sebelum Finalize CKPN.");
        }
        if ($lgdCount === 0) {
            return $this->backTo($request, 'calculation.module.detail_ckpn', [], 'Nilai LGD belum tersedia. Jalankan modul LGD (ER dan/atau CS) terlebih dahulu.');
        }

        // Finalize DIBLOKIR bila konfigurasi berubah setelah PD/LGD dihitung
        // (mis. segmentasi ulang) — hasil lama tidak akurat dan wajib dihitung ulang.
        // (CKPN sendiri tidak memblokir: finalize adalah cara menghitung ulang CKPN.)
        $stale = $this->staleResultTypes($period->id);
        $blocking = array_values(array_intersect($stale, ['PD', 'LGD']));
        if ($blocking !== []) {
            $labels = implode(', ', array_map(fn ($t) => $this->staleResultLabel($t), $blocking));

            return $this->backTo(
                $request,
                'calculation.module.detail_ckpn',
                [],
                "Konfigurasi berubah setelah perhitungan terakhir (tidak valid: {$labels}). Hitung ulang modul tersebut terlebih dahulu sebelum Finalize."
            );
        }

        $token = $this->progressToken($request);

        // Lock per-periode: cegah dua user finalize periode sama bersamaan.
        return $this->withRunLock($period->id, function () use ($request, $period, $config, $token) {
            // Transaksi: penulisan individual + kolektif + konsolidasi atomik.
            return DB::transaction(function () use ($request, $period, $config, $token) {
                // Hitung ulang individual + collective untuk konsistensi.
                $this->pushProgress($token, 'Menghitung CKPN Individual…', 10);
                $this->computeIndividual($period->id, $config, $token);
                $this->pushProgress($token, 'Menghitung CKPN Kolektif (EAD × PD × LGD)…', 55);
                $this->computeCollective($period->id, $config, $token);
                $this->pushProgress($token, 'Konsolidasi total CKPN…', 92);

                $summary = $this->summary($period->id);

                // Tandai hasil CKPN sah dengan konfigurasi berlaku (fingerprint).
                $this->markResults($period->id, 'CKPN');

                $this->audit('CKPN', 'FINALIZE', $period->id, $summary);

                return $this->backTo($request, 'calculation.module.detail_ckpn', [
                    'finalized' => 1,
                    'individual_count' => $summary['count_individual'],
                    'collective_count' => $summary['count_collective'],
                    'total_ckpn' => $summary['total_ckpn'],
                ]);
            });
        });
    }

    private function computeIndividual(int $periodId, array $config, ?string $token = null): void
    {
        if (! Schema::hasTable('stage_individual_ckpn')) {
            return;
        }
        DB::table('stage_individual_ckpn')
            ->where('assessment_period_id', $periodId)
            ->delete();

        $individuals = DB::table('stage_classifications')
            ->where('assessment_period_id', $periodId)
            ->where('classification', 'INDIVIDUAL')
            ->get();

        if ($individuals->isEmpty()) {
            return;
        }

        $saleCostPct = (float) $config['sale_cost_percentage'];

        $collateralMap = Collateral::whereIn('loan_number', $individuals->pluck('loan_number'))
            ->groupBy('loan_number')
            ->selectRaw('loan_number, SUM(liquidity_value) as total_liquidity')
            ->pluck('total_liquidity', 'loan_number')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $now = now();
        $batch = [];
        $totalInd = max(1, $individuals->count());
        $indIdx = 0;

        foreach ($individuals as $ind) {
            $indIdx++;
            if ($token !== null && ($indIdx % 50 === 0 || $indIdx === $totalInd)) {
                $this->pushProgress($token, "Menghitung CKPN Individual rekening {$indIdx}/{$totalInd}…", 10 + ($indIdx / $totalInd) * 40, [
                    'current' => $indIdx, 'total' => $totalInd, 'label' => 'CKPN Individual',
                ]);
            }
            $loanNumber = $ind->loan_number;
            $liquidity = (float) ($collateralMap[$loanNumber] ?? 0);
            $nilai = (float) $ind->ead_amount;
            $saleCost = round($liquidity * $saleCostPct, 2);

            $ckpn = CkpnCalculator::calculateIndividual($nilai, $liquidity, $saleCostPct);

            $batch[] = [
                'assessment_period_id' => $periodId,
                'loan_number' => $loanNumber,
                'nilai_tercatat' => $nilai,
                'liquidity_value' => round($liquidity, 2),
                'sale_cost' => $saleCost,
                'estimated_recovery' => round($liquidity - $saleCost, 2),
                'ckpn_individual' => $ckpn,
                'recovery_method' => $liquidity > 0 ? 'COLLATERAL_LIQUIDITY' : 'NO_COLLATERAL',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($batch, 200) as $chunk) {
            $this->safeInsert('stage_individual_ckpn', $chunk);
        }
    }

    private function computeCollective(int $periodId, array $config, ?string $token = null): void
    {
        if (! Schema::hasTable('stage_collective_ckpn')) {
            return;
        }
        DB::table('stage_collective_ckpn')
            ->where('assessment_period_id', $periodId)
            ->delete();

        // EAD totals per grup: prefer stage_ead_buckets (post-bucket), fallback ke
        // stage_classifications (EAD dihitung saat klasifikasi).
        $eadGroups = $this->eadGroups($periodId);

        if ($eadGroups->isEmpty()) {
            return;
        }

        // PD per grup × dimensi risiko (bucket 1..14 untuk NET_FLOW, rating 1..5 untuk MIGRATION).
        $pdMap = DB::table('stage_pd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', $config['pd_method'])
            ->get()
            ->keyBy(fn ($r) => $r->group_key.'|'.(int) ($r->bucket_no ?? 1));

        // LGD per grup, mengikuti metode aktif di konfigurasi (lgd_method).
        $lgdEr = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'ER')
            ->get()
            ->keyBy('group_key');
        $lgdCs = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'CS')
            ->get()
            ->keyBy('group_key');

        // Metode LGD aktif: EXPECTED_RECOVERY | COLLATERAL_SHORTFALL | WEIGHTED.
        $lgdMethod = strtoupper((string) ($config['lgd_method'] ?? 'WEIGHTED'));
        $now = now();
        $batch = [];
        $totalGroups = max(1, $eadGroups->count());
        $groupIdx = 0;

        foreach ($eadGroups as $eg) {
            $groupIdx++;
            if ($token !== null) {
                $this->pushProgress($token, "Menghitung CKPN Kolektif grup {$groupIdx}/{$totalGroups} ({$eg->group_key})…", 55 + ($groupIdx / $totalGroups) * 35, [
                    'current' => $groupIdx, 'total' => $totalGroups, 'label' => 'CKPN Kolektif',
                ]);
            }
            $key = $eg->group_key;
            // NET_FLOW: bucket_no (1..14). MIGRATION: quality_rating (1..5) sebagai bucket_no.
            // Keduanya lookup via bucket_key ($group_key|$bucket_no).
            $bucketKey = $eg->bucket_key;

            // Tanpa fallback: PD wajib tersedia per segmen × bucket (dijaga guard
            // missingPd di finalize — kombinasi tanpa PD tidak boleh diloloskan PD=0).
            if (! isset($pdMap[$bucketKey])) {
                throw new \RuntimeException("PD untuk grup {$key} bucket {$eg->bucket_no} tidak ditemukan. Hitung ulang modul PD sesuai metode {$config['pd_method']}.");
            }
            $pd = (float) $pdMap[$bucketKey]->pd_calculated;
            $erObj = $lgdEr[$key] ?? null;
            $csObj = $lgdCs[$key] ?? null;

            $erVal = $erObj ? (float) $erObj->lgd_calculated : null;
            $csVal = $csObj ? (float) $csObj->lgd_calculated : null;

            // LGD per metode:
            // - EXPECTED_RECOVERY   : LGD dari WO 5 tahun historis (recovery tunai)
            // - COLLATERAL_SHORTFALL: LGD dari Kol 5 Aktif & WO beragunan (shortfall agunan)
            // - WEIGHTED (default)  : Pembobotan Total Loss / Total Exposure gabungan ER & CS
            $woEr   = $erObj ? (float) $erObj->total_write_off : 0.0;
            $lossEr = $erObj ? max(0.0, $woEr - (float) $erObj->total_recovery) : 0.0;
            $expCs  = $csObj ? (float) $csObj->total_exposure_wo : 0.0;
            $lossCs = $csObj ? (float) $csObj->total_shortfall : 0.0;

            $weighted = ($woEr + $expCs) > 0 ? ($lossEr + $lossCs) / ($woEr + $expCs) : null;

            $lgd = match ($lgdMethod) {
                'EXPECTED_RECOVERY' => $erVal ?? $weighted ?? 0.0,
                'COLLATERAL_SHORTFALL' => $csVal ?? $weighted ?? 0.0,
                default => $weighted ?? $erVal ?? $csVal ?? 0.0,
            };
            $lgd = min(1.0, max(0.0, $lgd));
            $totalEad = (float) $eg->total_ead;

            $ckpn = CkpnCalculator::calculateCollective($pd, $lgd, $totalEad);

            $batch[] = [
                'assessment_period_id' => $periodId,
                'group_key' => $key,
                'bucket_no' => (int) $eg->bucket_no,
                'office_code' => $eg->office_code,
                'akad_code' => $eg->akad_code,
                'usage_code' => $eg->usage_code,
                'total_ead' => round($totalEad, 2),
                'pd' => round($pd, 6),
                'lgd' => round($lgd, 6),
                'lgd_method' => $lgdMethod,
                'ckpn_kolektif' => $ckpn,
                'member_count' => (int) $eg->member_count,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($batch, 200) as $chunk) {
            $this->safeInsert('stage_collective_ckpn', $chunk);
        }
    }

    /**
     * EAD totals per grup segmentasi × dimensi risiko (bucket untuk NET_FLOW, rating untuk MIGRATION).
     * 
     * NET_FLOW: Segmentasi × Bucket (B0..B13 = urutan 1..14)
     * MIGRATION: Segmentasi × Quality Rating (1..5) — aggregate per rating
     */
    private function eadGroups(int $periodId): Collection
    {
        $config = $this->configFor($periodId);
        $pdMethod = strtoupper((string) ($config['pd_method'] ?? 'NET_FLOW'));
        $dims = $this->segmentDimensions(request(), $periodId);
        $periodeYm = $this->assessmentYm($periodId);

        // Dimensi segmentasi (OFFICE, AKAD, USAGE).
        $columns = ['OFFICE' => 'sc.office_code', 'AKAD' => 'sc.akad_code', 'USAGE' => 'sc.usage_code'];
        $select = [];
        $group = [];

        foreach ($columns as $dim => $column) {
            if (in_array($dim, $dims, true)) {
                $select[] = "COALESCE({$column}, 'NA') as ".substr($column, 3);
                $group[] = "COALESCE({$column}, 'NA')";
            } else {
                $select[] = "'NA' as ".substr($column, 3);
            }
        }

        // Dimensi risiko sesuai metode PD.
        if ($pdMethod === 'MIGRATION') {
            // Quality rating 1..5 dari snapshot.
            $select[] = 'CAST(COALESCE(s.quality_rating, 1) AS UNSIGNED) as bucket_no';
            $group[] = 'CAST(COALESCE(s.quality_rating, 1) AS UNSIGNED)';
        } else {
            // Bucket 1..14 (B0..B13) dari snapshot.
            $select[] = 'COALESCE(s.bucket_no, 1) as bucket_no';
            $group[] = 'COALESCE(s.bucket_no, 1)';
        }

        return DB::table('stage_classifications as sc')
            ->leftJoin('snapshot as s', function ($j) use ($periodeYm) {
                $j->on('sc.loan_number', '=', 's.loan_number')
                    ->where('s.periode_snapshot', '=', $periodeYm)
                    ->where('s.active_flag', '=', true);
            })
            ->where('sc.assessment_period_id', $periodId)
            ->where('sc.classification', 'COLLECTIVE')
            ->selectRaw(implode(', ', $select).', SUM(sc.ead_amount) as total_ead, COUNT(*) as member_count')
            ->groupByRaw(implode(', ', $group))
            ->get()
            ->map(function ($g) use ($dims, $pdMethod) {
                $g->bucket_no = (int) $g->bucket_no;
                $g->group_key = $this->collectiveGroupKey($dims, $g->office_code, $g->akad_code, $g->usage_code);
                // Kunci unik segmen × dimensi risiko (bucket untuk NET_FLOW, rating untuk MIGRATION).
                $g->bucket_key = $g->group_key.'|'.$g->bucket_no;

                return $g;
            });
    }

    /**
     * Periode snapshot (Ym) dari assessment_date periode — konsisten dengan
     * parsing yang dipakai ClassificationController & PdNetFlowController.
     */
    private function assessmentYm(int $periodId): string
    {
        $assessmentDate = DB::table('assessment_periods')->where('id', $periodId)->value('assessment_date');

        return $assessmentDate ? Carbon::parse($assessmentDate)->format('Ym') : '999912';
    }

    private function individualRows(int $periodId): Collection
    {
        return DB::table('stage_individual_ckpn')
            ->where('assessment_period_id', $periodId)
            ->orderBy('ckpn_individual', 'desc')
            ->get();
    }

    private function collectiveRows(int $periodId): Collection
    {
        // NET_FLOW: per bucket_no (1..14). MIGRATION: per bucket_no = quality_rating (1..5).
        // Keduanya sudah tersimpan sebagai baris terpisah di stage_collective_ckpn.
        return DB::table('stage_collective_ckpn')
            ->where('assessment_period_id', $periodId)
            ->orderBy('group_key')
            ->orderBy('bucket_no')
            ->get();
    }

    private function summary(int $periodId): array
    {
        $individual = (float) DB::table('stage_individual_ckpn')
            ->where('assessment_period_id', $periodId)
            ->sum('ckpn_individual');

        $collective = (float) DB::table('stage_collective_ckpn')
            ->where('assessment_period_id', $periodId)
            ->sum('ckpn_kolektif');

        $totalEadInd = (float) DB::table('stage_individual_ckpn')
            ->where('assessment_period_id', $periodId)
            ->sum('nilai_tercatat');

        $totalEadCol = (float) DB::table('stage_collective_ckpn')
            ->where('assessment_period_id', $periodId)
            ->sum('total_ead');

        return [
            'individual_ckpn' => round($individual, 2),
            'collective_ckpn' => round($collective, 2),
            'total_ckpn' => round($individual + $collective, 2),
            'total_ead' => round($totalEadInd + $totalEadCol, 2),
            'individual_ead' => round($totalEadInd, 2),
            'collective_ead' => round($totalEadCol, 2),
            'count_individual' => (int) DB::table('stage_individual_ckpn')
                ->where('assessment_period_id', $periodId)->count(),
            'count_collective' => (int) DB::table('stage_collective_ckpn')
                ->where('assessment_period_id', $periodId)->count(),
        ];
    }
}
