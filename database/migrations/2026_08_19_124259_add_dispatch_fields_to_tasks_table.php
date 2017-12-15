<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('task_type')->default('general')->after('id');
            $table->unsignedBigInteger('vehicle_id')->nullable()->after('assigned_to');
            $table->decimal('origin_lat', 10, 8)->nullable();
            $table->decimal('origin_lng', 11, 8)->nullable();
            $table->text('origin_address')->nullable();
            $table->decimal('destination_lat', 10, 8)->nullable();
            $table->decimal('destination_lng', 11, 8)->nullable();
            $table->text('destination_address')->nullable();
            $table->decimal('estimated_distance', 8, 2)->nullable();
            $table->integer('estimated_duration')->nullable();
            
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropColumn([
                'task_type',
                'vehicle_id',
                'origin_lat',
                'origin_lng',
                'origin_address',
                'destination_lat',
                'destination_lng',
                'destination_address',
                'estimated_distance',
                'estimated_duration',
            ]);
        });
    }
};
