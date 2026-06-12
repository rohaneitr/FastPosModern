<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Modules\IAM\Models\User;
use App\Modules\Tenant\Models\Business;
use App\Modules\Inventory\Models\Product;
use App\Modules\Sales\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class ChaosClusterSimulation extends Command
{
    protected $signature = 'simulation:chaos-cluster';
    protected $description = 'Simulate Multi-Tenant Cluster & Offline Synchronization Chaos';

    public function handle()
    {
        $this->info("Starting Chaos Cluster Simulation...");
        Gate::define('pos.access', function () { return true; });
        try {
            // PHASE 1: Multi-Tenant Tier Seeding
            $this->info("Provisioning Tenant A (Retail Basic)...");
            $userA = User::create(['first_name' => 'User', 'last_name' => 'A', 'email' => 'a' . time() . '@test.com', 'password' => bcrypt('password')]);
            $tenantA = Business::create(['name' => 'Tenant A Retail', 'owner_id' => $userA->id, 'active_modules' => ['core_pos'], 'max_users' => 2]);
            $userA->update(['business_id' => $tenantA->id]);
            $locA = DB::table('locations')->insertGetId(['business_id' => $tenantA->id, 'name' => 'Loc A', 'created_at' => now(), 'updated_at' => now()]);

            $this->info("Provisioning Tenant B (Pharmacy Pro)...");
            $userB = User::create(['first_name' => 'User', 'last_name' => 'B', 'email' => 'b' . time() . '@test.com', 'password' => bcrypt('password')]);
            $tenantB = Business::create(['name' => 'Tenant B Pharmacy', 'owner_id' => $userB->id, 'active_modules' => ['core_pos', 'pharmacy']]);
            $userB->update(['business_id' => $tenantB->id]);
            $locB = DB::table('locations')->insertGetId(['business_id' => $tenantB->id, 'name' => 'Loc B', 'created_at' => now(), 'updated_at' => now()]);

            $this->info("Provisioning Tenant C (Manufacturing Enterprise)...");
            $userC = User::create(['first_name' => 'User', 'last_name' => 'C', 'email' => 'c' . time() . '@test.com', 'password' => bcrypt('password')]);
            $tenantC = Business::create(['name' => 'Tenant C Factory', 'owner_id' => $userC->id, 'active_modules' => ['core_pos', 'manufacturing']]);
            $userC->update(['business_id' => $tenantC->id]);

            $unitId = DB::table('units')->insertGetId(['business_id' => $tenantB->id, 'name' => 'Box', 'short_name' => 'Bx', 'allow_decimal' => false, 'created_at' => now(), 'updated_at' => now()]);

            // Setup Product for Pharmacy
            $productB = Product::create([
                'business_id' => $tenantB->id,
                'name' => 'Paracetamol',
                'sku' => 'MED-01',
                'barcode_symbology' => 'C128',
                'unit_id' => $unitId,
                'manage_stock' => true,
                'enable_sr_no' => false,
                'enable_imei' => false,
                'is_medicine' => true,
                'created_by' => $userB->id,
                'purchase_price' => 5.00,
                'selling_price' => 10.00
            ]);

            $variationId = DB::table('variations')->insertGetId([
                'product_id' => $productB->id,
                'name' => 'Default',
                'default_purchase_price' => 5.00,
                'sell_price_inc_tax' => 10.00,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Insert initial stock batch
            DB::table('inventory_layers')->insert([
                'product_id' => $productB->id,
                'original_qty' => 10,
                'remaining_qty' => 10,
                'unit_cost' => 5.00,
                'business_id' => $tenantB->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::table('product_stocks')->insert([
                'product_id' => $productB->id,
                'location_id' => $locB,
                'qty_available' => 10,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $this->info("Tenant Seeding Complete.");

            // PHASE 2: Chaos Matrix
            $this->info("Simulating Offline Network Drop for Tenant B...");

            // Create 5 offline payloads
            $offlineTransactions = [];
            for($i = 0; $i < 5; $i++) {
                $offlineTransactions[] = [
                    'uuid' => "offline-tx-{$i}",
                    'payload' => [
                        'location_id' => $locB,
                        'payment_method' => 'cash',
                        'amount_paid' => 10,
                        'tax_rate' => 0,
                        'discount_type' => 'fixed',
                        'discount_amount' => 0,
                        'send_sms' => false,
                        'items' => [
                            [
                                'variation_id' => $variationId,
                                'product_id' => $productB->id,
                                'quantity' => 1,
                                'price' => 10,
                                'fractional_ratio' => 1
                            ]
                        ]
                    ]
                ];
            }

            // The Chaos Collision: Reduce DB stock while 'offline'
            $this->info("Injecting Chaos Collision: Reducing stock for Paracetamol in backend...");
            DB::table('product_stocks')->where('product_id', $productB->id)->update(['qty_available' => 3]);
            DB::table('inventory_layers')->where('product_id', $productB->id)->update(['remaining_qty' => 3]);

            $this->info("Simulating Network Restoration...");
            Auth::login($userB);
            
            $request = new Request();
            $request->replace(['transactions' => $offlineTransactions]);
            $request->setUserResolver(function() use ($userB) { return $userB; });

            $controller = new TransactionController();
            $response = $controller->syncOfflineTransactions($request);
            $data = json_decode($response->getContent(), true);

            $this->info("Sync Result: " . count($data['successes']) . " succeeded, " . count($data['failures']) . " failed.");
            
            foreach ($data['failures'] as $failure) {
                $this->error("Conflict caught successfully: UUID {$failure['uuid']} - {$failure['error']}");
            }

            if (count($data['successes']) === 3 && count($data['failures']) === 2) {
                $this->info("CHAOS SIMULATION PASSED: Engine correctly identified stock deficiency and prevented database corruption.");
            } else {
                $this->error("CHAOS SIMULATION FAILED: Expected 3 successes and 2 failures based on stock reduction.");
            }

        } catch (\Exception $e) {
            $this->error("Simulation Error: " . $e->getMessage());
        }
    }
}
