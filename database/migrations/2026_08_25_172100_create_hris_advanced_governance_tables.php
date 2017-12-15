<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Asset Categories
        if (!Schema::hasTable('hris_asset_categories')) {
            Schema::create('hris_asset_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('name', 100);
                $table->string('code', 50)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Training Programs
        if (!Schema::hasTable('hris_trainings')) {
            Schema::create('hris_trainings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title', 150);
                $table->string('provider', 150)->nullable();
                $table->string('location', 150)->nullable();
                $table->date('training_date');
                $table->decimal('duration_hours', 5, 2)->default(1);
                $table->decimal('cost_per_person', 15, 2)->default(0);
                $table->string('status', 30)->default('scheduled');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 3. Training Participants
        if (!Schema::hasTable('hris_training_participants')) {
            Schema::create('hris_training_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_id')->constrained('hris_trainings')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->string('attendance_status', 30)->default('registered');
                $table->decimal('score', 5, 2)->nullable();
                $table->string('certificate_file', 255)->nullable();
                $table->text('feedback')->nullable();
                $table->timestamps();
            });
        }

        // 4. Employee Contracts
        if (!Schema::hasTable('hris_contracts')) {
            Schema::create('hris_contracts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('contract_number', 100)->nullable();
                $table->string('document_number', 100)->nullable();
                $table->string('contract_type', 50)->default('PKWT');
                $table->date('contract_date')->nullable();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->string('department', 100)->nullable();
                $table->string('position', 100)->nullable();
                $table->decimal('basic_salary', 15, 2)->default(0);
                $table->json('allowances_json')->nullable();
                $table->string('bank_name', 100)->nullable();
                $table->string('bank_account_number', 50)->nullable();
                $table->string('bank_account_holder', 100)->nullable();
                $table->string('document_pdf_path', 255)->nullable();
                $table->string('document_pdf_name', 255)->nullable();
                $table->string('status', 30)->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 5. Employee Documents Vault
        if (!Schema::hasTable('hris_documents')) {
            Schema::create('hris_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->string('title', 150);
                $table->string('category', 50)->default('general');
                $table->string('document_name', 255)->nullable();
                $table->string('document_path', 255)->nullable();
                $table->longText('file_base64')->nullable();
                $table->string('file_name', 255)->nullable();
                $table->string('file_type', 50)->nullable();
                $table->integer('file_size_kb')->default(0);
                $table->string('physical_location', 255)->nullable();
                $table->boolean('is_original_stored')->default(false);
                $table->boolean('is_verified')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 6. Violations & SP Letters
        if (!Schema::hasTable('hris_violations')) {
            Schema::create('hris_violations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('sp_type', 20)->default('SP1');
                $table->string('violation_type', 100)->nullable();
                $table->date('incident_date');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->date('valid_until')->nullable();
                $table->text('description');
                $table->text('punishment')->nullable();
                $table->string('evidence_path', 255)->nullable();
                $table->string('evidence_name', 255)->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamps();
            });
        }

        // 7. Employee Resignations
        if (!Schema::hasTable('hris_resignations')) {
            Schema::create('hris_resignations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('resignation_number', 100)->nullable();
                $table->string('category', 50)->default('voluntary');
                $table->date('resignation_date');
                $table->date('last_working_date')->nullable();
                $table->text('reason');
                $table->string('document_path', 255)->nullable();
                $table->string('document_name', 255)->nullable();
                $table->string('status', 30)->default('pending');
                $table->text('handover_notes')->nullable();
                $table->timestamps();
            });
        }

        // 8. Mutations & Promotions
        if (!Schema::hasTable('hris_mutations')) {
            Schema::create('hris_mutations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('mutation_number', 100)->nullable();
                $table->string('mutation_type', 50)->default('promotion');
                $table->date('effective_date');
                $table->string('old_department', 100)->nullable();
                $table->string('new_department', 100)->nullable();
                $table->string('old_position', 100)->nullable();
                $table->string('new_position', 100)->nullable();
                $table->string('old_employment_status', 50)->nullable();
                $table->string('new_employment_status', 50)->nullable();
                $table->text('reason')->nullable();
                $table->string('document_sk_path', 255)->nullable();
                $table->string('document_sk_name', 255)->nullable();
                $table->string('status', 30)->default('approved');
                $table->timestamps();
            });
        }

        // 9. Compliance & Legal Items
        if (!Schema::hasTable('hris_compliance_items')) {
            Schema::create('hris_compliance_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('title', 150);
                $table->string('item_type', 50)->default('license');
                $table->string('entity_type', 50)->default('company');
                $table->foreignId('entity_id')->nullable();
                $table->string('document_number', 100)->nullable();
                $table->date('expiry_date');
                $table->integer('reminder_days')->default(30);
                $table->string('status', 30)->default('active');
                $table->string('document_path', 255)->nullable();
                $table->string('document_name', 255)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 10. Performance Reviews
        if (!Schema::hasTable('hris_performance_reviews')) {
            Schema::create('hris_performance_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('period', 50);
                $table->decimal('attendance_score', 5, 2)->default(0);
                $table->decimal('task_completion_score', 5, 2)->default(0);
                $table->decimal('discipline_score', 5, 2)->default(0);
                $table->decimal('teamwork_score', 5, 2)->default(0);
                $table->decimal('final_score', 5, 2)->default(0);
                $table->string('grade', 10)->default('B');
                $table->string('status', 30)->default('draft');
                $table->text('feedback')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_performance_reviews');
        Schema::dropIfExists('hris_compliance_items');
        Schema::dropIfExists('hris_mutations');
        Schema::dropIfExists('hris_resignations');
        Schema::dropIfExists('hris_violations');
        Schema::dropIfExists('hris_documents');
        Schema::dropIfExists('hris_contracts');
        Schema::dropIfExists('hris_training_participants');
        Schema::dropIfExists('hris_trainings');
        Schema::dropIfExists('hris_asset_categories');
    }
};
