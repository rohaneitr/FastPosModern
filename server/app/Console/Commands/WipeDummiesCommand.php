<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Domain\IAM\Models\User;
use App\Domain\Tenant\Models\Business;

class WipeDummiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fastpos:wipe-dummies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wipe all dummy tenants and users except the Super Admin';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Dummy Wipeout...');

        $superAdminEmail = env('SUPER_ADMIN_EMAIL');

        if (!$superAdminEmail) {
            $this->error('SUPER_ADMIN_EMAIL is not defined in .env');
            return 1;
        }

        $superAdmin = User::where('email', $superAdminEmail)->first();

        if (!$superAdmin) {
            $this->error('Super Admin not found in the database. Please run seeders first.');
            return 1;
        }

        // Disable foreign key checks to prevent deletion errors
        DB::statement('PRAGMA foreign_keys=OFF;');

        // Delete all businesses
        DB::table('businesses')->delete();

        // Delete all users except super admin
        DB::table('users')->where('id', '!=', $superAdmin->id)->delete();

        // Delete related data to provide a completely clean slate
        DB::table('locations')->delete();
        DB::table('units')->delete();
        DB::table('products')->delete();
        DB::table('variations')->delete();
        DB::table('subscriptions')->delete();

        DB::statement('PRAGMA foreign_keys=ON;');

        $this->info('Dummy data wiped successfully. Only Super Admin remains.');
        return 0;
    }
}
