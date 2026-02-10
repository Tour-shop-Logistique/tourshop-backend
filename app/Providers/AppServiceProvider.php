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

    public function boot(\Illuminate\Routing\UrlGenerator $url): void
    {
        if (config('app.env') === 'production') {
            $url->forceScheme('https');
        }
    }
}
