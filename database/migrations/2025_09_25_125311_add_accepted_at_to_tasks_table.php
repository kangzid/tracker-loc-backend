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
        Schema::table('tasks', function (Blueprint $table) {
            $table->datetime('accepted_at')->nullable()->after('started_at');
        });
        
        // Update enum to include 'accepted' status
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending', 'accepted', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('accepted_at');
        });
        
        // Revert enum back to original
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending'");
    }
};
