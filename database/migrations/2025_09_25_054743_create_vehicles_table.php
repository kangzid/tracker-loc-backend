<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('vehicle_number', 20)->unique();
            $table->string('vehicle_type', 50);
            $table->string('brand', 50)->nullable();
            $table->string('model', 50)->nullable();
            $table->year('year')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('last_location_update')->nullable();
            $table->string('tracking_token', 64)->nullable()->unique();
            $table->timestamp('token_generated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // PERFORMANCE INDEXES
            $table->index('admin_id', 'idx_vehicles_admin_id');
            $table->index('is_active', 'idx_vehicles_is_active');
            $table->index('vehicle_type', 'idx_vehicles_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
