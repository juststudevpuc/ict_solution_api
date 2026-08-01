<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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
        // 🔥 FIX: Only force HTTPS if we are on the live production server
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
