<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\HrisPayroll;
use App\Models\HrisPayslip;
use Illuminate\Http\Request;

$user = User::where('email', 'agus01@majusejahtera.com')->first();
$token = $user->createToken('test-agus')->plainTextToken;

echo "User: {$user->name}, ID: {$user->id}, Role: {$user->role}, Admin ID: {$user->admin_id}\n";
echo "Employee: " . ($user->employee ? "ID {$user->employee->id}, Admin ID {$user->employee->admin_id}" : "None") . "\n";

$payrollCtrl = app(App\Http\Controllers\Api\HrisPayrollController::class);

// 1. Test index
$req = Request::create('/api/hris/payrolls', 'GET');
$req->setUserResolver(fn() => $user);
$res = $payrollCtrl->index($req);
echo "\n1. /api/hris/payrolls response:\n";
echo json_encode($res->getData()) . "\n";

// 2. Test slips for ID 22
$reqSlips = Request::create('/api/hris/payrolls/22/slips', 'GET');
$reqSlips->setUserResolver(fn() => $user);
$resSlips = $payrollCtrl->slips(22, $reqSlips);
echo "\n2. /api/hris/payrolls/22/slips response:\n";
$data = $resSlips->getData();
echo "Code: {$data->code}, Batch: {$data->batch_name}, Status: {$data->status}, Total Slips: " . count($data->payslips) . "\n";
$agusSlip = collect($data->payslips)->firstWhere('employee_id', 7);
echo "Agus Slip Found: " . ($agusSlip ? "YES (ID {$agusSlip->id}, Basic {$agusSlip->basic_salary}, Net {$agusSlip->net_salary})" : "NO") . "\n";
