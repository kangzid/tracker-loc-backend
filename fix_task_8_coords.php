<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Task;

$task = Task::find(8);
if ($task) {
    $task->update([
        'origin_lat' => -7.782800,
        'origin_lng' => 110.367000,
        'destination_lat' => -7.795600,
        'destination_lng' => 110.369500,
    ]);
    echo "Updated task #8 with coordinates successfully!\n";
    print_r(Task::find(8)->toArray());
} else {
    echo "Task #8 not found.\n";
}
