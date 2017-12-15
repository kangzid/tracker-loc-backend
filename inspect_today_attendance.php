<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Http\Controllers\Api\AttendanceController;
use Illuminate\Http\Request;

$user = User::where('email', 'agus01@majusejahtera.com')->first();
$req = Request::create('/api/attendances/today', 'GET');
$req->setUserResolver(fn() => $user);

$ctrl = app(AttendanceController::class);
$res = $ctrl->today($req);

echo "Today Attendance API response:\n";
echo json_encode($res->getData()) . "\n";
