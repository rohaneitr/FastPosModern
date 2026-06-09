<?php

namespace App\Modules\Restaurant\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

class RestaurantServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Hook KOT dispatch listener into Event Bus
        Event::listen(
            \App\Modules\Shared\Events\KotTicketEmitted::class,
            [\App\Modules\Restaurant\Listeners\DispatchKotToKitchen::class, 'handle']
        );

        // Load module migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        $this->app->singleton(
            \App\Modules\Restaurant\Services\TableSessionManager::class
        );
    }
}
