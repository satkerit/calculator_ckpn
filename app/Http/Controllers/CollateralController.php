<?php

namespace App\Http\Controllers;

use App\Models\Collateral;
use App\Models\CollateralSetup;
use App\Http\Requests\StoreCollateralRequest;
use App\Http\Requests\UpdateCollateralRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class CollateralController extends Controller
{
    public function index(Request $request): View
    {
        $query = Collateral::with(['collateralSetup']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                  ->orWhere('collateral_code', 'like', "%{$search}%")
                  ->orWhere('collateral_type', 'like', "%{$search}%");
            });
        }

        $collaterals = $query->orderBy('loan_number')->orderBy('collateral_order')->paginate(15);
        return view('collateral.index', compact('collaterals'));
    }

    public function create(): View
    {
        $setups = CollateralSetup::where('active', true)->orderBy('collateral_ket')->get();
        return view('collateral.create', compact('setups'));
    }

    public function store(StoreCollateralRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Collateral::create([
            'loan_number' => $validated['loan_number'],
            'collateral_code' => $validated['collateral_code'] ?? null,
            'collateral_order' => (int) $validated['collateral_order'],
            'collateral_type' => $validated['collateral_type'],
            'liquidity_value' => $validated['liquidity_value'] ?? 0,
            'estimated_value' => $validated['estimated_value'] ?? 0,
            'valuation_date' => $validated['valuation_date'] ?? null,
            'active_flag' => $request->boolean('active_flag', true),
        ]);

        return redirect()->route('collateral.index')->with('success', 'Collateral record created successfully');
    }

    public function show(Collateral $collateral): View
    {
        $collateral->load(['collateralSetup']);
        return view('collateral.show', compact('collateral'));
    }

    public function edit(Collateral $collateral): View
    {
        $setups = CollateralSetup::where('active', true)->orderBy('collateral_ket')->get();
        return view('collateral.edit', compact('collateral', 'setups'));
    }

    public function update(UpdateCollateralRequest $request, Collateral $collateral): RedirectResponse
    {
        $validated = $request->validated();

        $collateral->update([
            'collateral_code' => $validated['collateral_code'] ?? null,
            'collateral_order' => (int) $validated['collateral_order'],
            'collateral_type' => $validated['collateral_type'],
            'liquidity_value' => $validated['liquidity_value'] ?? 0,
            'estimated_value' => $validated['estimated_value'] ?? 0,
            'valuation_date' => $validated['valuation_date'] ?? null,
            'active_flag' => $request->boolean('active_flag', $collateral->active_flag),
        ]);

        return redirect()->route('collateral.index')->with('success', 'Collateral record updated successfully');
    }

    public function destroy(Collateral $collateral): RedirectResponse
    {
        $collateral->delete();
        return redirect()->route('collateral.index')->with('success', 'Collateral record deleted successfully');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|exists:collateral,id']);
        
        $count = Collateral::whereIn('id', $validated['ids'])->delete();
        
        return redirect()->route('collateral.index')->with('success', "{$count} collateral records deleted successfully");
    }
}
