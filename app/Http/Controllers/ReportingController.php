<?php

namespace App\Http\Controllers;

use App\Models\AssessmentPeriod;
use App\Support\CalculationResults;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportingController extends Controller
{
    public function ckpnSummary(Request $request): View
    {
        $assessments = AssessmentPeriod::whereIn('status', ['REVIEW', 'APPROVED', 'LOCKED'])
            ->latest('assessment_date')
            ->paginate(10);

        $reports = $assessments->map(function ($assessment) {
            $cached = CalculationResults::forPeriod($assessment->id);
            return [
                'assessment' => $assessment,
                'ead' => $cached['ead']['total_ead'] ?? 0,
                'individual_count' => $cached['ead']['total_count'] ?? 0,
                'individual_ckpn' => $cached['individual_ckpn']['total_ckpn_individual'] ?? 0,
                'collective_ckpn' => $cached['collective_ckpn']['total_ckpn_collective'] ?? 0,
                'total_ckpn' => ($cached['individual_ckpn']['total_ckpn_individual'] ?? 0) + ($cached['collective_ckpn']['total_ckpn_collective'] ?? 0),
                'coverage_ratio' => ($cached['ead']['total_ead'] ?? 0) > 0 ? (($cached['individual_ckpn']['total_ckpn_individual'] ?? 0 + $cached['collective_ckpn']['total_ckpn_collective'] ?? 0) / ($cached['ead']['total_ead'] ?? 1)) * 100 : 0,
            ];
        });

        return view('reporting.ckpn-summary', compact('reports', 'assessments'));
    }

    public function riskMatrix(Request $request): View
    {
        $assessmentId = $request->query('assessment_id');
        $assessment = AssessmentPeriod::findOrFail($assessmentId);
        $cached = CalculationResults::forPeriod($assessment->id);

        // Build risk matrix: PD vs LGD
        $individual = $cached['individual_ckpn']['individual_ckpn_results'] ?? [];
        $collective = $cached['collective_ckpn']['collective_ckpn_results'] ?? [];

        $matrix = [];
        foreach ($individual as $row) {
            $pd = intval(($row['pd_rate'] ?? 0) * 100 / 10) * 10;
            $lgd = intval(($row['lgd_rate'] ?? 0) * 100 / 10) * 10;
            $key = "{$pd}-{$lgd}";
            $matrix[$key] = ($matrix[$key] ?? 0) + ($row['ckpn_individual'] ?? 0);
        }

        foreach ($collective as $row) {
            $pd = intval(($row['pd'] ?? 0) * 100 / 10) * 10;
            $lgd = intval(($row['lgd'] ?? 0) * 100 / 10) * 10;
            $key = "{$pd}-{$lgd}";
            $matrix[$key] = ($matrix[$key] ?? 0) + ($row['ckpn'] ?? 0);
        }

        return view('reporting.risk-matrix', compact('assessment', 'matrix', 'cached'));
    }

    public function agingAnalysis(Request $request): View
    {
        $assessmentId = $request->query('assessment_id');
        $assessment = AssessmentPeriod::findOrFail($assessmentId);
        $cached = CalculationResults::forPeriod($assessment->id);

        // Simulate aging buckets from classification data
        $individual = $cached['individual_ckpn']['individual_ckpn_results'] ?? [];
        $collective = $cached['collective_ckpn']['collective_ckpn_results'] ?? [];

        $agingBuckets = [
            '0-30' => 0,
            '31-60' => 0,
            '61-90' => 0,
            '91-180' => 0,
            '181-360' => 0,
            '>360' => 0,
        ];

        $collateralCoverage = [
            'full' => 0,
            'partial' => 0,
            'none' => 0,
        ];

        foreach ($individual as $row) {
            $ckpn = $row['ckpn_individual'] ?? 0;
            $arrears = $row['principal_arrears'] ?? 0;

            // Simulate aging
            if ($arrears == 0) {
                $agingBuckets['0-30'] += $ckpn;
            } elseif ($arrears < 1_000_000) {
                $agingBuckets['31-60'] += $ckpn;
            } else {
                $agingBuckets['>360'] += $ckpn;
            }

            // Collateral coverage
            $recovery = $row['estimated_recovery'] ?? 0;
            $outstanding = $row['outstanding_balance'] ?? 0;
            if ($recovery >= $outstanding) {
                $collateralCoverage['full'] += $ckpn;
            } elseif ($recovery > 0) {
                $collateralCoverage['partial'] += $ckpn;
            } else {
                $collateralCoverage['none'] += $ckpn;
            }
        }

        return view('reporting.aging-analysis', compact('assessment', 'agingBuckets', 'collateralCoverage', 'cached'));
    }

    public function exportReport(Request $request, string $type)
    {
        $assessmentId = $request->query('assessment_id');
        $assessment = AssessmentPeriod::findOrFail($assessmentId);
        $cached = CalculationResults::forPeriod($assessment->id);

        $filename = "report_{$type}_{$assessment->id}_" . now()->format('YmdHis') . '.pdf';

        // ponytail: PDF generation skipped, returning CSV instead for now. Add when laravel-pdf dependency added.
        return response()->streamDownload(function () use ($cached, $type) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ["Report Type: {$type}"]);
            fputcsv($handle, []);
            fputcsv($handle, ["Generated: " . now()->format('d-m-Y H:i:s')]);
            fputcsv($handle, []);

            if ($type === 'ckpn-summary') {
                fputcsv($handle, ['Metric', 'Value']);
                fputcsv($handle, ['Total EAD', $cached['ead']['total_ead'] ?? 0]);
                fputcsv($handle, ['Individual CKPN', $cached['individual_ckpn']['total_ckpn_individual'] ?? 0]);
                fputcsv($handle, ['Collective CKPN', $cached['collective_ckpn']['total_ckpn_collective'] ?? 0]);
            }

            fclose($handle);
        }, $filename);
    }
}
