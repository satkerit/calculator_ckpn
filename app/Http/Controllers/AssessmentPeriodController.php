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

        // Peringatan data lama: lebih dari satu periode untuk tahun/bulan fiskal
        // yang sama membuat hasil kalkulasi ambigu (resolvePeriod memilih satu saja).
        $duplicatePeriods = AssessmentPeriod::query()
            ->selectRaw('fiscal_year, fiscal_month, COUNT(*) as total')
            ->groupBy('fiscal_year', 'fiscal_month')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        return view('assessment.index', compact('assessments', 'duplicatePeriods'));
    }

    public function create(): View
    {
        return view('assessment.create');
    }

    public function store(StoreAssessmentPeriodRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // period_code deterministik dari tahun/bulan fiskal: P{Y}{MM} (mis. P202610).
        $periodCode = strtoupper(sprintf('P%d%02d', $validated['fiscal_year'], $validated['fiscal_month']));

        // Cegah duplikat periode tahun/bulan yang sama.
        $existing = AssessmentPeriod::where('period_code', $periodCode)->first();
        if ($existing) {
            return redirect()->route('assessment.index')->with('error', 'Periode assessment sudah ada untuk tahun/bulan ini.');
        }

        AssessmentPeriod::create($validated + [
            'status' => AssessmentPeriod::STATUS_DRAFT,
            'period_code' => $periodCode,
            'created_by' => auth()->id()
        ]);

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

        // period_code deterministik: regenerasi bila tahun/bulan fiskal berubah.
        if ((int) $validated['fiscal_year'] !== (int) $assessment->fiscal_year
            || (int) $validated['fiscal_month'] !== (int) $assessment->fiscal_month) {
            $validated['period_code'] = strtoupper(sprintf('P%d%02d', $validated['fiscal_year'], $validated['fiscal_month']));
        }

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
