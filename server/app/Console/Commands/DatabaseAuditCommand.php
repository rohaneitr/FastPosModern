<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseAuditCommand extends Command
{
    protected $signature = 'db:audit';
    protected $description = 'Perform forensic database integrity checks: Orphans, Inventory Mismatches, and FK consistency.';

    public function handle()
    {
        $this->info("Starting Phase 13: Database Forensics & Integrity Sweep...");
        
        $report = [
            'orphans' => [],
            'inventory_mismatches' => [],
            'fk_violations' => []
        ];

        // 1. Orphan Record Sweep (transaction_items vs products)
        $this->info("1. Sweeping for Orphan Records...");
        $orphansCount = 0;
        
        DB::table('transaction_items')->orderBy('id')->chunkById(1000, function ($items) use (&$orphansCount, &$report) {
            foreach ($items as $item) {
                // Using DB facade ignores Eloquent soft deletes. 
                // We only flag true orphans (where the product is physically missing from the table).
                $productExists = DB::table('products')->where('id', $item->product_id)->exists();
                if (!$productExists) {
                    $orphansCount++;
                    $report['orphans'][] = $item->id;
                }
            }
        });
            
        if ($orphansCount > 0) {
            $this->error("Found {$orphansCount} orphan transaction_items!");
        } else {
            $this->info("No orphans found.");
        }

        // 2. Inventory Integrity Sweep
        $this->info("2. Sweeping for Inventory Discrepancies...");
        $mismatches = 0;

        DB::table('products')->orderBy('id')->chunkById(1000, function ($products) use (&$mismatches, &$report) {
            foreach ($products as $product) {
                $locations = DB::table('stock_ledgers')
                    ->where('product_id', $product->id)
                    ->select('location_id')
                    ->distinct()
                    ->pluck('location_id');

                foreach ($locations as $locId) {
                    $ledgerNet = DB::table('stock_ledgers')
                        ->where('product_id', $product->id)
                        ->where('location_id', $locId)
                        ->sum(DB::raw("CASE WHEN type = 'in' THEN quantity ELSE -quantity END"));

                    $stock = DB::table('product_location_stocks')
                        ->where('product_id', $product->id)
                        ->where('location_id', $locId)
                        ->first();

                    $currentQty = $stock ? $stock->quantity : 0;
                    
                    if (abs((float)$ledgerNet - (float)$currentQty) > 0.0001) {
                        $mismatches++;
                        $report['inventory_mismatches'][] = [
                            'product_id' => $product->id,
                            'location_id' => $locId,
                            'ledger_net' => $ledgerNet,
                            'current_qty' => $currentQty
                        ];
                    }
                }
            }
        });
        
        if ($mismatches > 0) {
            $this->error("Found {$mismatches} inventory mismatches!");
        } else {
            $this->info("Inventory perfectly balanced.");
        }

        // 3. Foreign Key Integrity (Tenant/Business ID match)
        $this->info("3. Sweeping for Tenant FK Integrity...");
        // Validate that transaction_items' business_id matches transactions' business_id
        // Wait, transaction_items might not have business_id, they belong to transactions.
        // Let's check products business_id vs categories business_id
        $fkMismatches = DB::table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereColumn('products.business_id', '!=', 'categories.business_id')
            ->count();
            
        if ($fkMismatches > 0) {
            $this->error("Found {$fkMismatches} products linked to categories in different businesses!");
            $report['fk_violations']['products_categories'] = $fkMismatches;
        } else {
            $this->info("Product-Category Tenant FKs valid.");
        }

        // Write report
        $reportPath = storage_path('logs/db_audit_report.json');
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        $this->info("Audit complete. Report generated at: {$reportPath}");
        
        // Hypothetical email to sysadmin
        Log::info("Database Health Report generated.", $report);
        
        return Command::SUCCESS;
    }
}
