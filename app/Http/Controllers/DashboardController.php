<?php

namespace App\Http\Controllers;

use App\Models\AssessmentPeriod;
use App\Models\Snapshot;
use App\Models\User;
use App\Support\CalculationResults;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Basic counts
        $assessmentCount = AssessmentPeriod::count();
        $snapshotCount = Snapshot::distinct('loan_number')->count('loan_number');
        $userCount = User::count();
        
        // Assessment status breakdown
        $assessmentByStatus = AssessmentPeriod::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');
        
        // Total EAD (from cache or latest calculation)
        $latestAssessment = AssessmentPeriod::whereIn('status', ['REVIEW', 'APPROVED', 'LOCKED'])
            ->latest('assessment_date')
            ->first();
        
        $totalEad = 0;
        $totalCkpn = 0;
        if ($latestAssessment) {
            $cachedResults = CalculationResults::forPeriod($latestAssessment->id);
            $totalEad = $cachedResults['ead']['total_ead'];
            $individualCkpn = $cachedResults['ckpn_individual']['total_individual_ckpn'];
            $collectiveCkpn = $cachedResults['ckpn_collective']['total_collective_ckpn'];
            $totalCkpn = $individualCkpn + $collectiveCkpn;
        }
        
        // Recent data
        $recentAssessments = AssessmentPeriod::latest()->limit(5)->get();
        $recentSnapshots = Snapshot::with('office:office_code,office_name')
            ->orderByDesc('periode_snapshot')
            ->limit(8)
            ->get();
        
        // Assessment period distribution (last 12 months)
        $assessmentTrend = AssessmentPeriod::select(
            DB::raw('DATE_FORMAT(assessment_date, "%Y-%m") as period'),
            DB::raw('count(*) as total'),
            DB::raw('SUM(CASE WHEN status IN ("REVIEW", "APPROVED", "LOCKED") THEN 1 ELSE 0 END) as completed')
        )
            ->where('assessment_date', '>=', now()->subMonths(12))
            ->groupBy('period')
            ->orderBy('period')
            ->get();
        
        // Snapshot by usage type (if available)
        $classificationStats = [
            'with_usage_type' => Snapshot::whereNotNull('usage_type')->distinct('loan_number')->count('loan_number'),
            'total' => $snapshotCount,
        ];

        return view('dashboard.index', compact(
            'assessmentCount',
            'snapshotCount',
            'userCount',
            'totalEad',
            'totalCkpn',
            'assessmentByStatus',
            'latestAssessment',
            'recentAssessments',
            'recentSnapshots',
            'assessmentTrend',
            'classificationStats'
        ));
    }
}
