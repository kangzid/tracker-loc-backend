<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Alter status column to add 'accepted' to the enum
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending', 'accepted', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset tasks in 'accepted' status to 'pending' before dropping the 'accepted' enum value
        DB::table('tasks')->where('status', 'accepted')->update(['status' => 'pending']);
        
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending'");
    }
};
