<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class SetupLocalTestingEnv extends Command
{
    protected $signature = 'fpm:local-test-init';
    protected $description = 'Wipe database, seed local staging UAT environment, and clear caches.';

    public function handle()
    {
        if (!app()->environment('local', 'testing')) {
            $this->error('CRITICAL: This command can only be run in local/testing environments to prevent production data loss.');
            return 1;
        }

        $this->info('Initializing FastPOS Local UAT Environment...');

        // 1. Wipe and Migrate
        $this->info('Step 1: Wiping and migrating database schemas...');
        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

        // 2. Run the UAT Seeder
        $this->info('Step 2: Injecting Enterprise Seeders (Tech & Pharma)...');
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\LocalStagingSeeder', '--force' => true]);

        // 3. Clear Caches
        $this->info('Step 3: Flushing Application Cache & Redis State...');
        Artisan::call('cache:clear');
        try {
            Cache::flush();
        } catch (\Exception $e) {
            $this->warn('Redis cache clear failed (is Redis running?): ' . $e->getMessage());
        }

        // 4. Output Routing Instructions & Credentials
        $this->outputMatrix();

        return 0;
    }

    private function outputMatrix()
    {
        $this->line("\n<bg=green;fg=white;options=bold> UAT ENVIRONMENT SUCCESSFULLY PROVISIONED </>");
        
        $this->line("\n<fg=yellow>=== OS HOSTS ROUTING REQUIRED ===</>");
        $this->line("To access tenant subdomains, you MUST map them to 127.0.0.1 on your local machine.");
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->line("1. Open Notepad as Administrator.");
            $this->line("2. Open: C:\\Windows\\System32\\drivers\\etc\\hosts");
        } else {
            $this->line("1. Open Terminal.");
            $this->line("2. Run: sudo nano /etc/hosts");
        }
        $this->line("3. Add the following line at the bottom:");
        $this->line("<fg=cyan>127.0.0.1  tech.localhost pharma.localhost</>");
        
        $this->line("\n<fg=yellow>=== SUPER ADMIN CONTROL ROOM ===</>");
        $this->line("URL:      <fg=cyan>http://localhost:3000</> (or Next.js Client Root)");
        $this->line("Email:    <fg=cyan>admin@fastpos.com</>");
        $this->line("Password: <fg=cyan>password</>");

        $this->line("\n<fg=yellow>=== TENANT 1: TECH RETAIL (Hardware/Serial Core) ===</>");
        $this->line("URL:      <fg=cyan>http://tech.localhost:3000</>");
        $this->line("Email:    <fg=cyan>admin@tech.com</>");
        $this->line("Password: <fg=cyan>password</>");

        $this->line("\n<fg=yellow>=== TENANT 2: PHARMACY (FEFO Batch/Expiry Core) ===</>");
        $this->line("URL:      <fg=cyan>http://pharma.localhost:3000</>");
        $this->line("Email:    <fg=cyan>admin@pharma.com</>");
        $this->line("Password: <fg=cyan>password</>");

        $this->line("\n<fg=magenta>Mock Webhook Trigger (Postman):</>");
        $this->line("POST http://localhost:8002/api/v1/local-test/mock-webhook");
        $this->line("Body (JSON): {\"business_id\": 1, \"amount\": 100.00, \"months_added\": 1}");
    }
}

