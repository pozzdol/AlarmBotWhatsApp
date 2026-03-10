<?php

namespace App\Providers;

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
        // Memaksa penggunaan HTTPS
        \Illuminate\Support\Facades\URL::forceScheme('https');

        // Memaksa penggunaan domain dari file .env (mengabaikan nama panggilan Docker)
        \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
    }
}
