<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Employee;
use App\Models\HrisPayroll;
use App\Models\HrisPayslip;
use Illuminate\Http\Request;

$users = User::where('name', 'like', '%Agus%')->orWhere('role', 'employee')->get();
foreach ($users as $u) {
    $emp = Employee::where('user_id', $u->id)->first();
    echo "User: ID {$u->id}, Name: {$u->name}, Email: {$u->email}, Role: {$u->role}, Emp ID: " . ($emp ? $emp->id : 'NONE') . ", Emp Admin ID: " . ($emp ? $emp->admin_id : 'NONE') . "\n";
}

$payrolls = HrisPayroll::all();
echo "\nTotal Payrolls in DB: " . $payrolls->count() . "\n";
foreach ($payrolls as $p) {
    echo "- ID: {$p->id}, Tenant: {$p->tenant_id}, Code: {$p->code}, Batch: {$p->batch_name}, Status: {$p->status}, Payslips: " . $p->payslips()->count() . "\n";
}
