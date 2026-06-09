<?php

namespace App\Modules\Pharmacy\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Domain\Shared\Events\TransactionCompleted;
use App\Modules\Pharmacy\Listeners\PharmacyTransactionCompletedListener;

class PharmacyServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Bind module specific services
    }

    public function boot()
    {
        // Zero-Coupling: Listen to core events
        Event::listen(
            \App\Domain\Shared\Events\TransactionCompleted::class,
            [\App\Modules\Pharmacy\Listeners\PharmacyTransactionCompletedListener::class, 'handle']
        );

        Event::listen(
            \App\Domain\Shared\Events\TransactionProcessing::class,
            [\App\Modules\Pharmacy\Listeners\EnforceFEFOStockDeduction::class, 'handle']
        );
        
        // Load module migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }
}
