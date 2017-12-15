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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->morphs('trackable'); // employee_id/vehicle_id
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('address')->nullable();
            $table->decimal('speed', 5, 2)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // PERFORMANCE INDEXES
            $table->index(['latitude', 'longitude'], 'idx_locations_coordinates');
            $table->index(['trackable_type', 'trackable_id'], 'idx_locations_trackable');
            $table->index(['trackable_type', 'trackable_id', 'recorded_at'], 'idx_locations_trackable_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
