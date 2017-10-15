<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Login as superadmin (ID usually 2 or something)
$superadmin = \App\Models\User::where('role', 'superadmin')->first();

$request = Illuminate\Http\Request::create('/api/superadmin/transactions', 'GET');
if ($superadmin) {
    $request->setUserResolver(function () use ($superadmin) {
        return $superadmin;
    });
}
$response = $kernel->handle($request);
echo "Status: " . $response->status() . "\n";
echo "Content: " . $response->content() . "\n";
