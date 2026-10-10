<?php

namespace App\Http\Controllers;

use App\Models\CollateralSetup;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class CollateralSetupController extends Controller
{
    public function index(): View
    {
        $collaterals = CollateralSetup::orderBy('collateral_type')->paginate(15);
        return view('collateral-setup.index', compact('collaterals'));
    }

    public function create(): View
    {
        return view('collateral-setup.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'collateral_type' => 'required|string|max:100|unique:collateral_setup,collateral_type',
            'collateral_ket' => 'required|string|max:255',
            'active' => 'boolean',
        ]);

        CollateralSetup::create([
            'collateral_type' => $validated['collateral_type'],
            'collateral_ket' => $validated['collateral_ket'],
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('collateral-setup.index')->with('success', 'Collateral setup created successfully');
    }

    public function show(CollateralSetup $collateralSetup): View
    {
        return view('collateral-setup.show', ['collateral' => $collateralSetup]);
    }

    public function edit(CollateralSetup $collateralSetup): View
    {
        return view('collateral-setup.edit', ['collateral' => $collateralSetup]);
    }

    public function update(Request $request, CollateralSetup $collateralSetup): RedirectResponse
    {
        $validated = $request->validate([
            'collateral_ket' => 'required|string|max:255',
            'active' => 'boolean',
        ]);

        $collateralSetup->update([
            'collateral_ket' => $validated['collateral_ket'],
            'active' => $request->boolean('active', $collateralSetup->active),
        ]);

        return redirect()->route('collateral-setup.show', $collateralSetup)->with('success', 'Collateral setup updated successfully');
    }

    public function destroy(CollateralSetup $collateralSetup): RedirectResponse
    {
        $collateralSetup->delete();
        return redirect()->route('collateral-setup.index')->with('success', 'Collateral setup deleted successfully');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        // Debug: log incoming request
        \Log::info('Bulk delete request', ['ids' => $request->input('ids'), 'all' => $request->all()]);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string|exists:collateral_setup,collateral_type'
        ]);

        $count = CollateralSetup::whereIn('collateral_type', $validated['ids'])->delete();

        return redirect()->route('collateral-setup.index')->with('success', "{$count} collateral setup berhasil dihapus.");
    }
}
