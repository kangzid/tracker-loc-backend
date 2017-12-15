<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== All tables containing contract ===\n";
$tables = DB::select('SHOW TABLES');
foreach ($tables as $t) {
    $tArr = (array)$t;
    $tName = reset($tArr);
    if (str_contains($tName, 'contract')) {
        echo "Table: $tName\n";
        print_r(DB::table($tName)->get()->toArray());
    }
}
