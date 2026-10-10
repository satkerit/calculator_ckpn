<?php

namespace App\Http\Controllers;

use App\Models\AssessmentPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class AssessmentPeriodController extends Controller
{
    public function index(): View
    {
        $assessments = AssessmentPeriod::paginate(15);
        return view('assessment.index', compact('assessments'));
    }

    public function create(): View
    {
        return view('assessment.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period_name' => 'required|string|max:255',
            'fiscal_month' => 'required|numeric|min:1|max:12',
            'fiscal_year' => 'required|numeric|min:2000',
            'assessment_date' => 'required|date',
        ]);

        $periodCode = strtoupper(sprintf('P%d%02d%s', $validated['fiscal_year'], $validated['fiscal_month'], substr(md5(microtime(true)), 0, 4)));
        
        AssessmentPeriod::updateOrCreate(
            ['period_code' => $periodCode],
            $validated + [
                'status' => 'DRAFT',
                'period_code' => $periodCode,
                'created_by' => auth()->id()
            ]
        );

        return redirect()->route('assessment.index')->with('success', 'Assessment period created successfully');
    }

    public function show(AssessmentPeriod $assessment): View
    {
        $loanCount = \App\Models\Snapshot::distinct('loan_number')->count('loan_number');
        return view('assessment.show', ['assessmentPeriod' => $assessment, 'financingCount' => $loanCount, 'loanCount' => $loanCount]);
    }

    public function edit(AssessmentPeriod $assessment): View
    {
        return view('assessment.edit', ['assessmentPeriod' => $assessment]);
    }

    public function update(Request $request, AssessmentPeriod $assessment): RedirectResponse
    {
        $validated = $request->validate([
            'period_name' => 'required|string|max:255',
            'fiscal_month' => 'required|numeric|min:1|max:12',
            'fiscal_year' => 'required|numeric|min:2000',
            'assessment_date' => 'required|date',
        ]);

        $assessment->update($validated);

        return redirect()->route('assessment.show', $assessment)->with('success', 'Assessment period updated');
    }

    public function destroy(AssessmentPeriod $assessment): RedirectResponse
    {
        // Hasil perhitungan stage_* terhapus otomatis via ON DELETE CASCADE.
        $assessment->delete();
        return redirect()->route('assessment.index')->with('success', 'Assessment period beserta hasil kalkulasinya berhasil dihapus.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|exists:assessment_periods,id'
        ]);

        $count = AssessmentPeriod::whereIn('id', $validated['ids'])->delete();

        return redirect()->route('assessment.index')->with('success', "{$count} assessment period beserta seluruh hasil kalkulasinya berhasil dihapus.");
    }
}
