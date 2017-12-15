<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Task;
use Illuminate\Http\Request;

$admin = User::where('email', 'admin@majusejahtera.com')->first();
$taskCtrl = app(App\Http\Controllers\Api\TaskController::class);

$req = Request::create('/api/tasks/9', 'GET');
$req->setUserResolver(fn() => $admin);
$res = $taskCtrl->show($req, 9);

echo "Task #9 API Response:\n";
echo $res->getContent() . "\n";
