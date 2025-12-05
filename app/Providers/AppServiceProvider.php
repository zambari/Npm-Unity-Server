<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
        // Auto-run migrations if tables don't exist (development convenience)
        if (app()->environment(['local', 'development']) || env('AUTO_MIGRATE', false)) {
            try {
                // Check if sessions table exists (it's created in the first migration)
                if (!Schema::hasTable('sessions')) {
                    // Run migrations automatically
                    Artisan::call('migrate', ['--force' => true]);
                }
            } catch (\Exception $e) {
                // Silently fail if database connection issues occur
                // This prevents errors during initial setup
            }
        }
    }
}
