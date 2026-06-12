<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
DB::enableQueryLog();
$user = \App\Modules\IAM\Models\User::where('username', 'admin@fastpos.com')->orWhere('email', 'admin@fastpos.com')->first();
print_r(DB::getQueryLog());
if ($user) {
    echo "Found user: " . $user->email . "\n";
    echo "Hash check: " . (Hash::check('password', $user->password) ? 'YES' : 'NO') . "\n";
} else {
    echo "User not found\n";
}
