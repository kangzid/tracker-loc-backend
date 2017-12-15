<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hris_payslips')) {
            Schema::create('hris_payslips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_id')->constrained('hris_payrolls')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->decimal('basic_salary', 15, 2)->default(0);
                $table->decimal('allowances', 15, 2)->default(0);
                $table->decimal('overtime_pay', 15, 2)->default(0);
                $table->decimal('reimbursements', 15, 2)->default(0);
                $table->decimal('loan_deductions', 15, 2)->default(0);
                $table->decimal('absence_deductions', 15, 2)->default(0);
                $table->decimal('tax_deductions', 15, 2)->default(0);
                $table->decimal('bpjs_kesehatan', 15, 2)->default(0);
                $table->decimal('bpjs_ketenagakerjaan', 15, 2)->default(0);
                $table->decimal('adjustments_addition', 15, 2)->default(0);
                $table->decimal('adjustments_deduction', 15, 2)->default(0);
                $table->decimal('net_salary', 15, 2)->default(0);
                $table->string('status', 20)->default('draft');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['payroll_id', 'employee_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_payslips');
    }
};
