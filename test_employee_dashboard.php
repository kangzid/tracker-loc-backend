<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;

$user = User::where('email', 'agus01@majusejahtera.com')->first();
$req = Request::create('/api/employee/dashboard', 'GET');
$req->setUserResolver(fn() => $user);

$routes = app('router')->getRoutes();
$route = $routes->match($req);
$action = $route->getAction();

echo "Route Action for /api/employee/dashboard: " . json_encode($action['uses'] ?? $action) . "\n";

$res = $route->run();
echo "Dashboard Data:\n" . json_encode($res->getData()) . "\n";
