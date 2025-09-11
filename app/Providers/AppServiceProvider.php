<?php

namespace App\Providers;

use App\Services\CloudinaryService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
  // app/Providers/AppServiceProvider.php
public function register()
{
    $this->app->singleton(CloudinaryService::class, function ($app) {
        return new CloudinaryService();
    });
}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
