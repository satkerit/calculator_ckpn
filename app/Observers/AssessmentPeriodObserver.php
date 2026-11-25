<?php

namespace App\Observers;

use App\Models\AssessmentPeriod;
use Illuminate\Support\Facades\Cache;

class AssessmentPeriodObserver
{
    public function created(AssessmentPeriod $model): void
    {
        Cache::forget('dashboard:stats');
    }

    public function updated(AssessmentPeriod $model): void
    {
        Cache::forget('dashboard:stats');
    }

    public function deleted(AssessmentPeriod $model): void
    {
        Cache::forget('dashboard:stats');
    }
}
