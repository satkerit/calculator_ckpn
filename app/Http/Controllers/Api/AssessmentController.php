<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AssessmentController extends Controller
{
    /**
     * Get assessment period details
     */
    public function show($periodId): JsonResponse
    {
        $assessmentPeriod = AssessmentPeriod::with(['createdBy', 'approvedBy'])
            ->findOrFail($periodId);

        return response()->json([
            'success' => true,
            'data' => $assessmentPeriod,
        ]);
    }

    /**
     * Create new assessment period
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_code' => 'required|string|unique:assessment_periods',
            'assessment_date' => 'required|date',
        ]);

        $assessmentPeriod = AssessmentPeriod::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status' => 'PENDING',
        ]);

        return response()->json([
            'success' => true,
            'data' => $assessmentPeriod,
        ], 201);
    }
}
