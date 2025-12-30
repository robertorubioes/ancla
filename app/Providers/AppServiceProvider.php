<?php

namespace App\Providers;

use App\Models\SigningProcess;
use App\Observers\SigningProcessObserver;
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
        // Register model observers
        SigningProcess::observe(SigningProcessObserver::class);
    }
}
