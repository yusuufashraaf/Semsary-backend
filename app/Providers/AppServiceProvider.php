<?php

namespace App\Providers;

use App\Services\CloudinaryService;
use Carbon\CarbonInterval;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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
    Passport::tokensExpireIn(CarbonInterval::days(15));
    Passport::refreshTokensExpireIn(CarbonInterval::days(30));
    Passport::personalAccessTokensExpireIn(CarbonInterval::months(6));
    }
}
