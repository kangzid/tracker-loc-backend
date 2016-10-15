<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ALTER enum untuk menambahkan nilai 'superadmin'
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'admin', 'employee') DEFAULT 'employee'");
    }

    public function down(): void
    {
        // Kembalikan ke nilai semula (pastikan tidak ada data superadmin sebelum rollback)
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'employee') DEFAULT 'employee'");
    }
};
