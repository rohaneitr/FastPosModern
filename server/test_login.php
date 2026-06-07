<?php
$req = \Illuminate\Http\Request::create('/api/v1/login', 'POST', [
    'username' => 'superadmin@fastpos.com',
    'password' => 'Secret@12',
    'remember_me' => false
]);
$res = app()->handle($req);
echo "STATUS: " . $res->getStatusCode() . "\n";
echo "BODY: " . $res->getContent() . "\n";
