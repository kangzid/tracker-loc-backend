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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('employee_id', 50)->unique();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('department', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamp('last_location_update')->nullable();
            $table->timestamps();

            // PERFORMANCE INDEXES
            $table->index('admin_id', 'idx_employees_admin_id');
            $table->index('user_id', 'idx_employees_user_id');
            $table->index('employee_id', 'idx_employees_employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
