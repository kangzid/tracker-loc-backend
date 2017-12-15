<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hris_overtime_settings')) {
            Schema::create('hris_overtime_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->decimal('default_rate_per_hour', 15, 2)->default(25000);
                $table->string('calculation_type', 50)->default('flat');
                $table->integer('min_duration_minutes')->default(30);
                $table->boolean('require_approval')->default(true);
                $table->timestamps();

                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_overtime_settings');
    }
};
