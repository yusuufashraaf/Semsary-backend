<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    // app/Providers/BroadcastServiceProvider.php
    public function boot()
    {
        Broadcast::routes(['middleware' => ['auth:api']]);
        require base_path('routes/channels.php');
    }
}