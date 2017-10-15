<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SupportMessage;

$messages = SupportMessage::all();
echo "Total Messages: " . $messages->count() . "\n";
foreach ($messages as $msg) {
    echo "ID: {$msg->id}, From: {$msg->sender_id}, To: {$msg->receiver_id}, Msg: {$msg->message}\n";
}
