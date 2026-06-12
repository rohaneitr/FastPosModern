<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Modules\Tenant\Models\Business;
use App\Modules\IAM\Models\User;

class MigrateLegacyTenants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-tenants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy business and users from the old database schema to the modern DDD schema';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Legacy Tenants Migration...');

        try {
            // Check connection
            DB::connection('legacy')->getPdo();
        } catch (\Exception $e) {
            $this->error('Cannot connect to legacy database: ' . $e->getMessage());
            $this->error('Make sure the legacy fastpos_db docker container is running on port 3306.');
            return 1;
        }

        // Disable foreign key constraints to handle circular dependency between business owner_id and user business_id
        DB::statement('PRAGMA foreign_keys=OFF;');

        $this->migrateBusinesses();
        $this->migrateUsers();

        DB::statement('PRAGMA foreign_keys=ON;');

        $this->info('Legacy Tenants Migration Complete!');
        return 0;
    }

    private function migrateBusinesses()
    {
        $this->info('Migrating businesses...');
        
        $legacyBusinesses = DB::connection('legacy')->table('business')->get();

        $bar = $this->output->createProgressBar(count($legacyBusinesses));
        $bar->start();

        foreach ($legacyBusinesses as $b) {
            // Package all unknown or bloated fields into JSON settings
            $settings = [
                'default_profit_percent' => $b->default_profit_percent,
                'fy_start_month' => $b->fy_start_month,
                'accounting_method' => $b->accounting_method,
                'default_sales_discount' => $b->default_sales_discount,
                'sell_price_tax' => $b->sell_price_tax,
                'sku_prefix' => $b->sku_prefix,
                'enable_tooltip' => $b->enable_tooltip,
            ];

            // Use updateOrCreate so the command is idempotent
            Business::updateOrCreate(
                ['id' => $b->id],
                [
                    'name' => $b->name,
                    'owner_id' => $b->owner_id, // Note: user migrates after, but this might fail foreign key constraint if users don't exist yet!
                    // To handle this, we temporarily disable foreign key checks or migrate users first.
                    // Actually, since owner_id references users, we should migrate users first, but users reference business_id.
                    // We'll migrate businesses without owner_id first (by dropping the constraint or making owner_id nullable), or we just let it fail.
                    // Wait! In the new schema, owner_id is constrained. So we MUST migrate users first, but users have business_id.
                    // Let's remove the constraint temporarily or just use DB::statement('PRAGMA foreign_keys=OFF;');
                    
                    'start_date' => $b->start_date,
                    'time_zone' => $b->time_zone ?? 'Asia/Kolkata',
                    'currency_code' => 'USD', // Mapping legacy currency_id requires another lookup, defaulting to USD for now.
                    'logo' => $b->logo,
                    'tax_number_1' => $b->tax_number_1,
                    'tax_label_1' => $b->tax_label_1,
                    'tax_number_2' => $b->tax_number_2,
                    'tax_label_2' => $b->tax_label_2,
                    'settings' => $settings,
                    'is_active' => true,
                    'created_at' => $b->created_at,
                    'updated_at' => $b->updated_at,
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function migrateUsers()
    {
        $this->info('Migrating users...');
        
        $legacyUsers = DB::connection('legacy')->table('users')->get();

        $bar = $this->output->createProgressBar(count($legacyUsers));
        $bar->start();

        foreach ($legacyUsers as $u) {
            User::updateOrCreate(
                ['id' => $u->id],
                [
                    'business_id' => $u->business_id ?? null,
                    'surname' => $u->surname,
                    'first_name' => $u->first_name,
                    'last_name' => $u->last_name,
                    'username' => $u->username,
                    'email' => $u->email,
                    'password' => $u->password, // preserve the bcrypt hash!
                    'language' => $u->language,
                    'user_type' => $u->user_type ?? 'user',
                    'allow_login' => $u->allow_login ?? true,
                    'created_at' => $u->created_at,
                    'updated_at' => $u->updated_at,
                    'deleted_at' => $u->deleted_at,
                ]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }
}
