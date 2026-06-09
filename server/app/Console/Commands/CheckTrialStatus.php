<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Tenant\Models\Business;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\UpcomingTrialExpiry;
use App\Mail\TrialExpired;

class CheckTrialStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fpm:check-trial-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for trial expirations and send notifications or suspend businesses.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking trial statuses...');

        $now = Carbon::now();

        // 1. Upcoming Expiry (3 days away)
        $upcomingBusinesses = Business::whereNotNull('trial_ends_at')
            ->whereDate('trial_ends_at', '=', $now->copy()->addDays(3)->toDateString())
            ->where('status', '!=', 'suspended')
            ->with('owner')
            ->get();

        foreach ($upcomingBusinesses as $business) {
            if (!$business->isSubscriptionActive() && $business->owner) {
                Mail::to($business->owner->email)->send(new UpcomingTrialExpiry($business));
                $this->info("Sent upcoming trial expiry to {$business->name}.");
            }
        }

        // 2. Expired Trials
        $expiredBusinesses = Business::whereNotNull('trial_ends_at')
            ->whereDate('trial_ends_at', '<', $now->toDateString())
            ->where('status', '!=', 'suspended')
            ->with('owner')
            ->get();

        foreach ($expiredBusinesses as $business) {
            if (!$business->isSubscriptionActive()) {
                $business->update(['status' => 'suspended', 'is_active' => false]);
                
                if ($business->owner) {
                    Mail::to($business->owner->email)->send(new TrialExpired($business));
                }
                
                $this->info("Suspended business {$business->name} due to trial expiry.");
            }
        }

        $this->info('Trial checks completed.');
        return Command::SUCCESS;
    }
}
