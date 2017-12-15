<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hris_employee_leave_balances')) {
            Schema::create('hris_employee_leave_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->string('category', 50)->default('leave');
                $table->foreignId('leave_type_id')->nullable()->constrained('hris_leave_types')->nullOnDelete();
                $table->integer('year');
                $table->integer('quota')->default(12);
                $table->integer('used')->default(0);
                $table->integer('remaining')->default(12);
                $table->timestamps();

                $table->index(['tenant_id', 'employee_id', 'year']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_employee_leave_balances');
    }
};
