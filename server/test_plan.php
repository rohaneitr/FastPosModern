<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$req = Illuminate\Http\Request::create('/api/v1/superadmin/plans', 'POST', ['name'=>'T', 'price'=>10, 'plan_architecture'=>'online_web']);
try {
    $res = app(App\Domain\Tenant\Controllers\SubscriptionController::class)->storePlan($req);
    echo $res->getContent();
} catch (\Exception $e) {
    echo "CRASH: " . $e->getMessage();
}
