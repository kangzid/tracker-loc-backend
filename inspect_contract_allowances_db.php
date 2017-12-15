<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Employee;
use App\Models\HrisContract;
use App\Models\HrisEmployeeSalary;
use App\Models\HrisEmployeeAllowance;
use App\Models\HrisEmployeeAllowanceItem;
use App\Models\HrisAllowanceType;

echo "=== 1. Check Contracts in DB ===\n";
$contracts = HrisContract::all();
echo "Total contracts in DB: " . $contracts->count() . "\n";
foreach ($contracts as $c) {
    echo "- ID: {$c->id}, Tenant: {$c->tenant_id}, Emp: {$c->employee_id}, Number: {$c->contract_number}, Type: {$c->contract_type}, Status: {$c->status}, Start: {$c->start_date}, End: {$c->end_date}, Basic Salary: {$c->basic_salary}, Allowances: " . json_encode($c->allowances) . "\n";
}

echo "\n=== 2. Check Employee Salaries in DB ===\n";
$salaries = HrisEmployeeSalary::all();
echo "Total salaries in DB: " . $salaries->count() . "\n";
foreach ($salaries as $s) {
    echo "- ID: {$s->id}, Tenant: {$s->tenant_id}, Emp: {$s->employee_id}, Amount: {$s->amount}, Effective: {$s->effective_date}\n";
}

echo "\n=== 3. Check Employee Allowances in DB ===\n";
$allowances = HrisEmployeeAllowance::with('items.allowanceType')->get();
echo "Total employee allowances in DB: " . $allowances->count() . "\n";
foreach ($allowances as $a) {
    echo "- ID: {$a->id}, Tenant: {$a->tenant_id}, Emp: {$a->employee_id}, Code: {$a->code}, Effective: {$a->effective_date}\n";
    foreach ($a->items as $item) {
        echo "   * Item Type: {$item->allowanceType?->name} ({$item->allowance_type_id}), Amount: {$item->amount}\n";
    }
}

echo "\n=== 4. Check Allowance Types in DB ===\n";
$types = HrisAllowanceType::all();
echo "Total allowance types in DB: " . $types->count() . "\n";
foreach ($types as $t) {
    echo "- ID: {$t->id}, Tenant: {$t->tenant_id}, Code: {$t->code}, Name: {$t->name}\n";
}
