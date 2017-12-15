<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hris_salary_adjustment_batches')) {
            Schema::create('hris_salary_adjustment_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('name', 150);
                $table->integer('month');
                $table->integer('year');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'month', 'year']);
            });
        }

        if (!Schema::hasTable('hris_salary_adjustment_items')) {
            Schema::create('hris_salary_adjustment_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('batch_id')->constrained('hris_salary_adjustment_batches')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->enum('type', ['addition', 'deduction'])->default('addition');
                $table->string('name', 100);
                $table->decimal('amount', 15, 2);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_salary_adjustment_items');
        Schema::dropIfExists('hris_salary_adjustment_batches');
    }
};
