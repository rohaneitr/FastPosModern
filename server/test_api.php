<?php
$u = \App\Domain\IAM\Models\User::find(1);
$token = $u->createToken('test')->plainTextToken;

$req = \Illuminate\Http\Request::create('/api/v1/superadmin/overview-stats', 'GET');
$req->headers->set('Accept', 'application/json');
$req->headers->set('Authorization', 'Bearer ' . $token);

$res = app()->handle($req);
echo "STATUS: " . $res->getStatusCode() . "\n";
echo "BODY: " . $res->getContent() . "\n";
