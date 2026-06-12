<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$business = \App\Domain\Tenant\Models\Business::first();
$business->update(['subscription_status' => 'active', 'subscription_ends_at' => now()->addDays(30), 'subscription_expires_at' => now()->addDays(30), 'status' => 'active']);
$user = \App\Domain\IAM\Models\User::where('business_id', $business->id)->first();
$token = $user->createToken('e2e')->plainTextToken;
$location = \Illuminate\Support\Facades\DB::table('locations')->where('business_id', $business->id)->first();
if (!$location) {
    $locId = \Illuminate\Support\Facades\DB::table('locations')->insertGetId([
        'business_id' => $business->id,
        'name' => 'Main Location',
        'is_active' => 1,
    ]);
    $location = \Illuminate\Support\Facades\DB::table('locations')->where('id', $locId)->first();
}

$product1 = \Illuminate\Support\Facades\DB::table('products')->insertGetId([
    'business_id' => $business->id,
    'name' => 'E2E Item A',
    'sku' => 'E2E-A-' . time(),
    'purchase_price' => 50,
    'selling_price' => 100,
    'created_at' => now(),
    'updated_at' => now(),
]);

$product2 = \Illuminate\Support\Facades\DB::table('products')->insertGetId([
    'business_id' => $business->id,
    'name' => 'E2E Item B',
    'sku' => 'E2E-B-' . time(),
    'purchase_price' => 25,
    'selling_price' => 50,
    'created_at' => now(),
    'updated_at' => now(),
]);

\Illuminate\Support\Facades\DB::table('product_stocks')->insert([
    ['product_id' => $product1, 'location_id' => $location->id, 'qty_available' => '100.0000', 'created_at' => now(), 'updated_at' => now()],
    ['product_id' => $product2, 'location_id' => $location->id, 'qty_available' => '50.0000', 'created_at' => now(), 'updated_at' => now()]
]);

echo 'JSON_START' . json_encode([
    'token' => $token,
    'location_id' => $location->id,
    'product1' => $product1,
    'product2' => $product2
]) . 'JSON_END';
