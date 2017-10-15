<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ubah tipe data ENUM menjadi VARCHAR agar bisa menerima nilai dinamis (starter, pro, custom, inactive, dll)
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan VARCHAR(255) DEFAULT 'trial'");
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status VARCHAR(255) DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('trial', 'monthly', 'yearly') DEFAULT 'trial'");
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('active', 'expired', 'cancelled') DEFAULT 'active'");
    }
};
