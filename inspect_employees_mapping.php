<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Models\User;

echo "=== All Employees in DB ===\n";
$employees = Employee::with('user')->get();
foreach ($employees as $e) {
    echo "- Employee DB ID: {$e->id}, Code: {$e->employee_id}, User ID: {$e->user_id}, Name: {$e->user?->name}, Email: {$e->user?->email}\n";
}
