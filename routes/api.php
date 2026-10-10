<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AssessmentController;

Route::prefix('v1')->group(function () {
    // Assessment endpoints
    Route::post('/assessments', [AssessmentController::class, 'store']);
    Route::get('/assessments/{periodId}', [AssessmentController::class, 'show']);
});
