<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Http\Requests\StoreOfficeRequest;
use App\Http\Requests\UpdateOfficeRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class OfficeController extends Controller
{
    public function index(): View
    {
        $offices = Office::paginate(15);
        return view('office.index', compact('offices'));
    }

    public function create(): View
    {
        return view('office.create');
    }

    public function store(StoreOfficeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Office::create($validated + ['active' => $request->boolean('active', true)]);

        return redirect()->route('office.index')->with('success', 'Office created successfully');
    }

    public function show(Office $office): View
    {
        return view('office.show', compact('office'));
    }

    public function edit(Office $office): View
    {
        return view('office.edit', compact('office'));
    }

    public function update(UpdateOfficeRequest $request, Office $office): RedirectResponse
    {
        $validated = $request->validated();

        $office->update($validated + ['active' => $request->boolean('active', $office->active)]);

        return redirect()->route('office.show', $office)->with('success', 'Office updated');
    }

    public function destroy(Office $office): RedirectResponse
    {
        $office->delete();
        return redirect()->route('office.index')->with('success', 'Office deleted');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|exists:offices,id']);
        
        $count = Office::whereIn('id', $validated['ids'])->delete();
        
        return redirect()->route('office.index')->with('success', "{$count} offices deleted successfully");
    }
}
