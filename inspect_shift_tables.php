<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Shift-related tables in DB ===\n";
$tables = DB::select('SHOW TABLES');
foreach ($tables as $t) {
    $tbl = array_values((array)$t)[0];
    if (str_contains(strtolower($tbl), 'shift') || str_contains(strtolower($tbl), 'schedule') || str_contains(strtolower($tbl), 'roster') || str_contains(strtolower($tbl), 'attendance')) {
        echo "- {$tbl}\n";
    }
}
