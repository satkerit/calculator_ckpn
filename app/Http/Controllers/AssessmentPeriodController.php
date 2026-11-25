<?php

namespace App\Http\Controllers;

use App\Models\AssessmentPeriod;
use App\Models\Snapshot;
use App\Http\Requests\StoreAssessmentPeriodRequest;
use App\Http\Requests\UpdateAssessmentPeriodRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    public function store(StoreAssessmentPeriodRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $periodCode = strtoupper(sprintf('P%d%02d%s', $validated['fiscal_year'], $validated['fiscal_month'], substr(md5(microtime(true)), 0, 4)));
        
        AssessmentPeriod::updateOrCreate(
            ['period_code' => $periodCode],
            $validated + [
                'status' => AssessmentPeriod::STATUS_DRAFT,
                'period_code' => $periodCode,
                'created_by' => auth()->id()
            ]
        );

        return redirect()->route('assessment.index')->with('success', 'Assessment period created successfully');
    }

    public function show(AssessmentPeriod $assessment): View
    {
        // Count debitur unik dari snapshot; di-cache karena query distinct berat dan jarang berubah.
        $loanCount = Cache::remember('snapshot:distinct_loan_count', 300, function () {
            return Snapshot::distinct('loan_number')->count('loan_number');
        });

        return view('assessment.show', ['assessmentPeriod' => $assessment, 'financingCount' => $loanCount, 'loanCount' => $loanCount]);
    }

    public function edit(AssessmentPeriod $assessment): View
    {
        return view('assessment.edit', ['assessmentPeriod' => $assessment]);
    }

    public function update(UpdateAssessmentPeriodRequest $request, AssessmentPeriod $assessment): RedirectResponse
    {
        $validated = $request->validated();

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
