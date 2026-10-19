<?php

namespace App\Http\Controllers\Calculation;

use App\Services\Bucket\BucketAssignmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Modul 7: Detail PD & EAD.
 *
 * Read-only view: per-loan EAD (current assessment), distribusi bucket,
 * PD per grup segmentasi.
 */
class DetailPdEadController extends BaseCalculationController
{
    public function index(Request $request): View
    {
        $period = $this->resolvePeriod($request);

        $eadBucket    = $this->eadPerBucket($period->id);
        $pdResults   = $this->pdResults($period->id);
        $classificationCount = $this->classificationCount($period->id);
        $loans       = $this->loanDetail($period->id);

        return view('calculation.modules.detail_pd_ead', compact(
            'period',
            'eadBucket',
            'pdResults',
            'classificationCount',
            'loans',
        ));
    }

    private function eadPerBucket(int $periodId): array
    {
        $rows = DB::table('stage_ead_buckets')
            ->where('assessment_period_id', $periodId)
            ->selectRaw('bucket_no, SUM(ead) as total_ead, SUM(member_count) as total_members')
            ->groupBy('bucket_no')
            ->orderBy('bucket_no')
            ->get();

        if ($rows->isEmpty()) {
            // Auto-populate jika stage_ead_buckets belum terisi
            $period = DB::table('assessment_periods')->where('id', $periodId)->first();
            if ($period) {
                // Parsing konsisten dengan ClassificationController: format('Ym').
                $periodeYm = Carbon::parse($period->assessment_date)->format('Ym');
                
                // Ambil data dari stage_classifications join snapshot
                $joined = DB::table('stage_classifications as sc')
                    ->join('snapshot as s', function ($j) use ($periodeYm) {
                        $j->on('sc.loan_number', '=', 's.loan_number')
                          ->where('s.periode_snapshot', '=', $periodeYm);
                    })
                    ->where('sc.assessment_period_id', $periodId)
                    ->select('s.bucket_no', 'sc.ead_amount', 'sc.office_code', 'sc.akad_code', 'sc.usage_code')
                    ->get();

                $grouped = [];
                foreach ($joined as $item) {
                    $bNo = (int) ($item->bucket_no ?? 1);
                    if (!isset($grouped[$bNo])) {
                        $grouped[$bNo] = ['ead' => 0.0, 'count' => 0];
                    }
                    $grouped[$bNo]['ead'] += (float) $item->ead_amount;
                    $grouped[$bNo]['count']++;
                }

                $batch = [];
                foreach ($grouped as $bNo => $val) {
                    $batch[] = [
                        'assessment_period_id' => $periodId,
                        'group_key'            => 'ALL_SEGMENTS',
                        'bucket_no'            => $bNo,
                        'hari_tunggakan'       => BucketAssignmentService::getBucketLabel($bNo),
                        'office_code'          => null,
                        'akad_code'            => null,
                        'usage_code'           => null,
                        'ead'                  => $val['ead'],
                        'member_count'         => $val['count'],
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ];
                }

                if (!empty($batch)) {
                    DB::table('stage_ead_buckets')->insert($batch);
                }

                $rows = DB::table('stage_ead_buckets')
                    ->where('assessment_period_id', $periodId)
                    ->selectRaw('bucket_no, SUM(ead) as total_ead, SUM(member_count) as total_members')
                    ->groupBy('bucket_no')
                    ->orderBy('bucket_no')
                    ->get();
            }
        }

        $labels = [];
        foreach ($rows as $r) {
            $labels[(int) $r->bucket_no] = BucketAssignmentService::getBucketLabel((int) $r->bucket_no);
        }

        return [
            'rows'   => $rows,
            'labels' => $labels,
        ];
    }

    private function pdResults(int $periodId): \Illuminate\Support\Collection
    {
        return DB::table('stage_pd_results')
            ->where('assessment_period_id', $periodId)
            ->orderBy('group_key')
            ->orderBy('bucket_no')
            ->get();
    }

    private function classificationCount(int $periodId): array
    {
        $rows = DB::table('stage_classifications')
            ->where('assessment_period_id', $periodId)
            ->selectRaw('classification, COUNT(*) as cnt, SUM(ead_amount) as total_ead')
            ->groupBy('classification')
            ->get();

        $data = [
            'INDIVIDUAL' => ['count' => 0, 'ead' => 0.0],
            'COLLECTIVE' => ['count' => 0, 'ead' => 0.0],
        ];
        foreach ($rows as $r) {
            $data[$r->classification] = [
                'count' => (int) $r->cnt,
                'ead'   => (float) $r->total_ead,
            ];
        }
        return $data;
    }

    private function loanDetail(int $periodId): \Illuminate\Support\Collection
    {
        // group_key PD dibangun dari dimensi segmentasi aktif (OFFICE/AKAD/USAGE) —
        // join harus memakai ekspresi yang sama, bukan selalu 3 dimensi.
        // Config tersimpan sebagai string "OFFICE|AKAD" → parse ke array.
        $config = $this->configFor($periodId);
        $rawDims = $config['segment_dimensions'];
        $dims = is_array($rawDims)
            ? $rawDims
            : array_values(array_filter(array_map('trim', explode('|', (string) $rawDims))));
        $colMap = ['OFFICE' => 'sc.office_code', 'AKAD' => 'sc.akad_code', 'USAGE' => 'sc.usage_code'];
        $parts = [];
        foreach (['OFFICE', 'AKAD', 'USAGE'] as $dim) {
            if (in_array($dim, $dims, true)) {
                $parts[] = "IFNULL({$colMap[$dim]}, 'NA')";
            }
        }
        $groupKeyExpr = $parts === [] ? "'NA'" : "CONCAT_WS('|', ".implode(', ', $parts).")";

        $pdMethod = $config['pd_method'];
        $periodeYm = Carbon::parse(
            DB::table('assessment_periods')->where('id', $periodId)->value('assessment_date')
        )->format('Ym');

        // Join snapshot untuk bucket debitur agar PD per segmen × bucket terpetakan
        // tepat (bukan menggandakan baris bila satu segmen punya beberapa bucket).
        return DB::table('stage_classifications as sc')
            ->leftJoin('snapshot as s', function ($j) use ($periodeYm) {
                $j->on('sc.loan_number', '=', 's.loan_number')
                    ->where('s.periode_snapshot', '=', $periodeYm)
                    ->where('s.active_flag', '=', true);
            })
            ->leftJoin('stage_pd_results as pd', function ($j) use ($periodId, $groupKeyExpr, $pdMethod) {
                $j->on(DB::raw($groupKeyExpr), '=', 'pd.group_key')
                    ->on(DB::raw('COALESCE(s.bucket_no, 1)'), '=', 'pd.bucket_no')
                    ->where('pd.assessment_period_id', '=', $periodId)
                    ->where('pd.method', '=', $pdMethod);
            })
            ->where('sc.assessment_period_id', $periodId)
            ->orderBy('sc.classification')
            ->orderBy('sc.ead_amount', 'desc')
            ->select(
                'sc.loan_number',
                'sc.classification',
                'sc.office_code',
                'sc.akad_code',
                'sc.usage_code',
                'sc.ead_amount',
                'sc.reason',
                DB::raw('COALESCE(s.bucket_no, 1) as bucket_no'),
                'pd.pd_calculated',
                'pd.method as pd_method',
            )
            ->limit(500)
            ->get();
    }
}