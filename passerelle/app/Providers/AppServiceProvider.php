<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // En production uniquement : toute URL générée (liens d'invitation, redirections)
        // est forcée en HTTPS. La démo locale en HTTP n'est pas affectée.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
