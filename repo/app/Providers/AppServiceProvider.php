<?php

namespace App\Providers;

use App\Models\Doctor;
use App\Models\Trip;
use App\Observers\DoctorObserver;
use App\Observers\TripObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Prevent lazy loading in development
        Model::preventLazyLoading(! app()->isProduction());

        // Prevent mass assignment exceptions with clear errors
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        // Register model observers for SearchTerm population (questions.md 5.1)
        Trip::observe(TripObserver::class);
        Doctor::observe(DoctorObserver::class);
    }
}
