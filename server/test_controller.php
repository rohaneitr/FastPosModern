<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Inject into AuthController directly
$request = Illuminate\Http\Request::create('/api/v1/login', 'POST', ['username' => 'admin@fastpos.com', 'password' => 'password']);

$user = \App\Modules\IAM\Models\User::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

echo "Found user: " . ($user ? $user->email : 'null') . "\n";
if ($user) {
    echo "Hash check: " . (\Illuminate\Support\Facades\Hash::check($request->password, $user->password) ? 'YES' : 'NO') . "\n";
}
