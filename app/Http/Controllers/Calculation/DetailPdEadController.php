<?php

namespace App\Http\Controllers\Calculation;

use App\Services\Bucket\BucketAssignmentService;
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
        $dims = $this->configFor($periodId)['segment_dimensions'];
        $colMap = ['OFFICE' => 'sc.office_code', 'AKAD' => 'sc.akad_code', 'USAGE' => 'sc.usage_code'];
        $parts = [];
        foreach (['OFFICE', 'AKAD', 'USAGE'] as $dim) {
            if (in_array($dim, $dims, true)) {
                $parts[] = "IFNULL({$colMap[$dim]}, 'NA')";
            }
        }
        $groupKeyExpr = $parts === [] ? "'NA'" : "CONCAT_WS('|', ".implode(', ', $parts).")";

        $pdMethod = $this->configFor($periodId)['pd_method'];

        return DB::table('stage_classifications as sc')
            ->leftJoin('stage_pd_results as pd', function ($j) use ($periodId, $groupKeyExpr, $pdMethod) {
                $j->on(DB::raw($groupKeyExpr), '=', 'pd.group_key')
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
                'pd.pd_calculated',
                'pd.method as pd_method',
            )
            ->limit(500)
            ->get();
    }
}