<?php

namespace App\Http\Controllers\Calculation;

use App\Services\PdNetflow\PdNetflowService;
use App\Services\PdNetflow\PdNetflowCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class PdNetflowDetailController
{
    public function __construct(
        private PdNetflowService $service,
        private PdNetflowCalculator $calculator,
    ) {}

    public function show(Request $request, int $assessmentPeriod): View
    {
        $groupKey = $request->query('group_key');

        // Load config segmentation dimensions
        $dimsConfig = DB::table('stage_configs')
            ->where('assessment_period_id', $assessmentPeriod)
            ->value('segmentation_dims') ?? 'OFFICE|AKAD|USAGE';

        $dims = array_map('trim', explode('|', $dimsConfig));

        // Load available groups based on selected segmentation dimensions
        $query = DB::table('stage_classifications')
            ->where('assessment_period_id', $assessmentPeriod)
            ->where('classification', 'COLLECTIVE');

        $selectParts = [];
        $selectParts[] = in_array('OFFICE', $dims) ? "COALESCE(office_code, 'NA')" : "'NA'";
        $selectParts[] = in_array('AKAD', $dims) ? "COALESCE(akad_code, 'NA')" : "'NA'";
        $selectParts[] = in_array('USAGE', $dims) ? "COALESCE(usage_code, 'NA')" : "'NA'";

        $availableGroups = $query->selectRaw("CONCAT(" . implode(", '|', ", $selectParts) . ") as group_key")
            ->distinct()
            ->pluck('group_key')
            ->sort()
            ->values()
            ->all();

        $allData = [];
        // Load data for ALL available groups regardless of query string
        foreach ($availableGroups as $gk) {
            // Check & Calculate if not exist
            $hasData = DB::table('pd_netflow_bucket_snapshots')
                ->where('assessment_period_id', $assessmentPeriod)
                ->where('group_key', $gk)
                ->exists();

            if (!$hasData) {
                $this->calculator->calculate($assessmentPeriod, $gk);
            }
            
            $allData[$gk] = $this->service->loadGroupDetail($assessmentPeriod, $gk);
        }

        return view('calculation.pd_netflow_detail', [
            'groupKey' => $groupKey,
            'availableGroups' => $availableGroups,
            'allData' => $allData,
            'service' => $this->service,
            'assessmentPeriodId' => $assessmentPeriod,
        ]);
    }
}
