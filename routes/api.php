<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AssessmentController;

Route::prefix('v1')->middleware(['auth', 'throttle:60,1'])->group(function () {
    // Assessment endpoints
    Route::post('/assessments', [AssessmentController::class, 'store'])
        ->middleware('permission:assessment.create');
    Route::get('/assessments/{periodId}', [AssessmentController::class, 'show'])
        ->middleware('permission:assessment.view');
});
