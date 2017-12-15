<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

if (!Schema::hasColumn('employees', 'photo_base64')) {
    Schema::table('employees', function (Blueprint $table) {
        $table->longText('photo_base64')->nullable()->after('position');
    });
    echo "Added photo_base64 to employees table!" . PHP_EOL;
} else {
    echo "photo_base64 already exists in employees table!" . PHP_EOL;
}

if (!Schema::hasColumn('users', 'photo_base64')) {
    Schema::table('users', function (Blueprint $table) {
        $table->longText('photo_base64')->nullable()->after('name');
    });
    echo "Added photo_base64 to users table!" . PHP_EOL;
} else {
    echo "photo_base64 already exists in users table!" . PHP_EOL;
}
