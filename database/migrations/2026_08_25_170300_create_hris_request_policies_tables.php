<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hris_leave_types')) {
            Schema::create('hris_leave_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('name', 100);
                $table->string('code', 50)->nullable();
                $table->integer('quota')->default(12);
                $table->boolean('is_paid')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('tenant_id');
            });
        }

        if (!Schema::hasTable('hris_request_policies')) {
            Schema::create('hris_request_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('policy_type', 50); // absence, sick, leave, duty
                $table->boolean('is_paid')->default(true);
                $table->boolean('requires_approval')->default(true);
                $table->boolean('requires_document')->default(false);
                $table->integer('max_days_per_request')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'policy_type']);
            });
        }

        if (!Schema::hasTable('hris_requests')) {
            Schema::create('hris_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->string('code', 50)->nullable();
                $table->string('request_type', 50);
                $table->foreignId('leave_type_id')->nullable()->constrained('hris_leave_types')->nullOnDelete();
                $table->date('start_date');
                $table->date('end_date');
                $table->integer('days_count')->default(1);
                $table->text('reason')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('attachment_name')->nullable();
                $table->string('status', 20)->default('pending');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('approved_at')->nullable();
                $table->text('approver_note')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'request_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_requests');
        Schema::dropIfExists('hris_request_policies');
        Schema::dropIfExists('hris_leave_types');
    }
};
