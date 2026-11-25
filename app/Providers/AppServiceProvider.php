<?php

namespace App\Providers;

use App\Models\AssessmentPeriod;
use App\Observers\AssessmentPeriodObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        AssessmentPeriod::observe(AssessmentPeriodObserver::class);
    }
}
