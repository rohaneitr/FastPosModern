<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Tenant\Models\Business;
use Illuminate\Support\Facades\Log;

class CheckExpiredSubscriptions extends Command
{
    protected $signature = 'saas:check-subscriptions';
    protected $description = 'Automatically suspend businesses with expired subscriptions';

    public function handle()
    {
        $this->info("Checking for expired subscriptions...");

        $expiredBusinesses = Business::where('status', '!=', 'suspended')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expiredBusinesses as $business) {
            $business->update([
                'status' => 'suspended',
                'is_active' => false,
                'subscription_status' => 'Expired'
            ]);

            Log::channel('single')->info("Suspended business {$business->id} ({$business->name}) due to expired subscription.");
            $this->info("Suspended business {$business->id}");
            $count++;
        }

        $this->info("Suspended {$count} expired businesses.");
    }
}
