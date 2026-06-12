<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CleanupArchivedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fpm:cleanup-archived-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete soft-deleted records older than 30 days to ensure data compliance.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting automated cleanup of archived data...');
        $cutoffDate = Carbon::now()->subDays(30);

        try {
            // Delete products
            $deletedProducts = DB::table('products')->whereNotNull('deleted_at')->where('deleted_at', '<', $cutoffDate)->delete();
            $this->info("Permanently deleted $deletedProducts products.");

            // Delete purchases
            $deletedPurchases = DB::table('purchases')->whereNotNull('deleted_at')->where('deleted_at', '<', $cutoffDate)->delete();
            $this->info("Permanently deleted $deletedPurchases purchases.");

            // Delete transactions (sales)
            $deletedTransactions = DB::table('transactions')->whereNotNull('deleted_at')->where('deleted_at', '<', $cutoffDate)->delete();
            $this->info("Permanently deleted $deletedTransactions transactions.");

            // Delete businesses (tenants)
            $deletedBusinesses = DB::table('businesses')->whereNotNull('deleted_at')->where('deleted_at', '<', $cutoffDate)->delete();
            $this->info("Permanently deleted $deletedBusinesses businesses.");

            Log::info("Archived data cleanup executed successfully.", [
                'products' => $deletedProducts,
                'purchases' => $deletedPurchases,
                'transactions' => $deletedTransactions,
                'businesses' => $deletedBusinesses,
            ]);

            $this->info('Cleanup complete!');
        } catch (\Exception $e) {
            $this->error('Failed to cleanup archived data: ' . $e->getMessage());
            Log::error('Archived data cleanup failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
