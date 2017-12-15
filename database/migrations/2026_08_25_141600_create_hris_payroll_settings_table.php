<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hris_payroll_settings')) {
            Schema::create('hris_payroll_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('working_days_divider_type')->default('fixed_25'); // fixed_25, fixed_22, fixed_21, fixed_20, calendar_days, custom
                $table->integer('custom_working_days')->default(25);
                $table->decimal('overtime_rate_multiplier', 5, 2)->default(1.5);
                $table->decimal('late_deduction_rate', 15, 2)->default(0);
                $table->boolean('auto_generate_payslip')->default(true);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_payroll_settings');
    }
};
