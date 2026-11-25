<?php

namespace App\Http\Controllers;

use App\Models\AssessmentPeriod;
use App\Models\Snapshot;
use App\Models\User;
use App\Support\CalculationResults;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Cached stats (30s TTL) — heavy count queries yang jarang berubah
        $stats = Cache::remember('dashboard:stats', 30, function () {
            $snapshotCount = Snapshot::distinct('loan_number')->count('loan_number');

            return [
                'assessmentCount' => AssessmentPeriod::count(),
                'snapshotCount' => $snapshotCount,
                'userCount' => User::count(),
                'assessmentByStatus' => AssessmentPeriod::select('status', DB::raw('count(*) as total'))
                    ->groupBy('status')
                    ->get()
                    ->keyBy('status'),
                'classificationStats' => [
                    'with_usage_type' => Snapshot::whereNotNull('usage_type')->distinct('loan_number')->count('loan_number'),
                    'total' => $snapshotCount,
                ],
            ];
        });

        $assessmentCount = $stats['assessmentCount'];
        $snapshotCount = $stats['snapshotCount'];
        $userCount = $stats['userCount'];
        $assessmentByStatus = $stats['assessmentByStatus'];

        // Data yang sering berubah tetap fresh (tidak di-cache)
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
        $recentAssessments = AssessmentPeriod::latest()->orderByDesc('id')->limit(5)->get();
        // Tabel `snapshot` tidak punya kolom `id` — tie-breaker pakai `created_at`.
        $recentSnapshots = Snapshot::with('office:office_code,office_name')
            ->orderByDesc('periode_snapshot')
            ->orderByDesc('created_at')
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
        $classificationStats = $stats['classificationStats'];

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
