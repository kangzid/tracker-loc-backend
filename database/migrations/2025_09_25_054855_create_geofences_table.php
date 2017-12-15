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
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('name', 50);
            $table->text('description')->nullable();
            $table->decimal('center_lat', 10, 8);
            $table->decimal('center_lng', 11, 8);
            $table->integer('radius'); // in meters
            $table->enum('type', ['office', 'work_area', 'restricted']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // PERFORMANCE INDEXES
            $table->index('is_active', 'idx_geofences_is_active');
            $table->index('type', 'idx_geofences_type');
            $table->index(['admin_id', 'is_active', 'type'], 'idx_geofences_admin_active_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofences');
    }
};
