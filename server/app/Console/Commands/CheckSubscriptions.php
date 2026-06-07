<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saas:check-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan for expired subscriptions and automatically downgrade tenants.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting SaaS Subscription Expiry Check...');
        Log::info('Starting SaaS Subscription Expiry Check...');

        // Find active subscriptions that have expired
        $expiredSubscriptions = DB::table('subscriptions')
            ->where('status', 'active')
            ->where('current_period_end', '<', Carbon::now())
            ->get();

        $count = 0;

        foreach ($expiredSubscriptions as $sub) {
            DB::beginTransaction();
            try {
                // Update subscription status
                DB::table('subscriptions')
                    ->where('id', $sub->id)
                    ->update(['status' => 'past_due', 'updated_at' => Carbon::now()]);

                // Downgrade the business
                DB::table('businesses')
                    ->where('id', $sub->business_id)
                    ->update(['subscription_status' => 'past_due', 'updated_at' => Carbon::now()]);

                // The Phase 15 Heartbeat will natively block POS devices because the business status is now 'past_due'.
                // Optionally log an alert for the Business Admin
                Log::info("Tenant downgraded: Business ID {$sub->business_id}");

                // TODO: Dispatch Email/System Alert to Business Admin
                // Notification::send($admin, new SubscriptionExpired());

                DB::commit();
                $count++;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to downgrade Business ID {$sub->business_id}: " . $e->getMessage());
            }
        }

        $this->info("Completed. {$count} subscriptions downgraded.");
        Log::info("Completed. {$count} subscriptions downgraded.");
    }
}
