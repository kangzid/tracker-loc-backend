<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\HrisEmployeeLeaveBalance;

echo "All leave balances in DB:\n";
foreach (HrisEmployeeLeaveBalance::with(['employee.user', 'leaveType'])->get() as $b) {
    echo "- ID: {$b->id}, Emp: {$b->employee?->user?->name} ({$b->employee?->employee_id}), Type: {$b->leaveType?->name} ({$b->leaveType?->code}), Quota: {$b->quota}, Used: {$b->used}, Remaining: {$b->remaining}\n";
}
