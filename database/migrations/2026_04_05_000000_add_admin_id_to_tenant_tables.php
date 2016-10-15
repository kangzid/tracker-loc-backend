<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add admin_id to employees table
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('admin_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
            $table->index('admin_id');
        });

        // Add admin_id to vehicles table
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('admin_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
            $table->index('admin_id');
        });

        // Add admin_id to tasks table
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('admin_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
            $table->index('admin_id');
        });

        // Add admin_id to geofences table
        Schema::table('geofences', function (Blueprint $table) {
            $table->foreignId('admin_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
            $table->index('admin_id');
        });

        // Add admin_id to notifications table
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('admin_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropColumn('admin_id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropColumn('admin_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropColumn('admin_id');
        });

        Schema::table('geofences', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropColumn('admin_id');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropColumn('admin_id');
        });
    }
};
