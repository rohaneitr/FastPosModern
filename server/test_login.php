<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Modules\IAM\Models\User::where('email', 'admin@fastpos.com')->first();
if ($user) {
    echo "User found: " . $user->email . "\n";
    echo "Password check: " . (Hash::check('password', $user->password) ? 'YES' : 'NO') . "\n";
} else {
    echo "User not found\n";
}
