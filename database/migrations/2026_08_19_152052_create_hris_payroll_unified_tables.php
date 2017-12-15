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
        if (!Schema::hasTable('hris_contracts')) {
            Schema::create('hris_contracts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('employee_id');
                $table->string('contract_number')->unique();
                $table->string('document_number')->nullable();
                $table->string('contract_type')->default('PKWT');
                $table->date('contract_date');
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->string('department')->nullable();
                $table->string('position')->nullable();
                $table->decimal('basic_salary', 15, 2)->default(0);
                $table->decimal('allowance_extrafooding', 15, 2)->default(0);
                $table->decimal('allowance_fuel', 15, 2)->default(0);
                $table->decimal('allowance_communication', 15, 2)->default(0);
                $table->string('document_pdf_path')->nullable();
                $table->string('document_pdf_name')->nullable();
                $table->longText('document_pdf_base64')->nullable();
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'employee_id']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // 1. Shift & Attendance Settings
        if (!Schema::hasTable('attendance_settings')) {
            Schema::create('attendance_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->time('work_start_time')->default('08:00:00');
                $table->time('work_end_time')->default('17:00:00');
                $table->integer('late_tolerance_minutes')->default(15);
                $table->boolean('require_selfie')->default(true);
                $table->boolean('require_location')->default(true);
                $table->boolean('auto_approval_leaves')->default(false);
                $table->timestamps();
            });
        }

        // 2. Base Payroll Tables
        if (!Schema::hasTable('hris_payroll_periods')) {
            Schema::create('hris_payroll_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('name', 100);
                $table->date('start_date');
                $table->date('end_date');
                $table->date('payment_date');
                $table->enum('status', ['draft', 'processing', 'completed'])->default('draft');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hris_employee_salaries')) {
            Schema::create('hris_employee_salaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->string('code', 50)->nullable();
                $table->enum('wage_type', ['Bulanan', 'Harian'])->default('Bulanan');
                $table->decimal('amount', 15, 2);
                $table->date('effective_date');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hris_allowance_types')) {
            Schema::create('hris_allowance_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('code', 50);
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->boolean('is_taxable')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hris_employee_allowances')) {
            Schema::create('hris_employee_allowances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->foreignId('allowance_type_id')->nullable()->constrained('hris_allowance_types')->onDelete('cascade');
                $table->string('code', 50)->nullable();
                $table->decimal('amount', 15, 2);
                $table->date('effective_date');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hris_employee_bpjs')) {
            Schema::create('hris_employee_bpjs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->string('code', 50)->nullable();
                $table->enum('bpjs_type', ['kesehatan', 'ketenagakerjaan']);
                $table->decimal('amount', 15, 2);
                $table->date('effective_date');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hris_payrolls')) {
            Schema::create('hris_payrolls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('payroll_type', 50)->default('monthly');
                $table->string('code', 50)->nullable();
                $table->string('batch_name', 150)->nullable();
                $table->integer('month')->nullable();
                $table->integer('year')->nullable();
                $table->date('slip_date')->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('status', 30)->default('draft');
                $table->string('report_file_path', 255)->nullable();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hris_payroll_items')) {
            Schema::create('hris_payroll_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_id')->constrained('hris_payrolls')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->decimal('basic_salary', 15, 2)->default(0);
                $table->decimal('allowances', 15, 2)->default(0);
                $table->decimal('overtime_pay', 15, 2)->default(0);
                $table->decimal('bpjs_kesehatan', 15, 2)->default(0);
                $table->decimal('bpjs_ketenagakerjaan', 15, 2)->default(0);
                $table->decimal('adjustments', 15, 2)->default(0);
                $table->decimal('reimbursements', 15, 2)->default(0);
                $table->decimal('loan_deductions', 15, 2)->default(0);
                $table->decimal('net_salary', 15, 2)->default(0);
                $table->enum('status', ['draft', 'published', 'paid'])->default('draft');
                $table->timestamps();
            });
        }

        // 3. Requests / Pengajuan
        if (!Schema::hasTable('hris_leave_requests')) {
            Schema::create('hris_leave_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->enum('type', ['izin', 'sakit', 'cuti', 'dinas']);
                $table->string('leave_type', 50)->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->integer('duration_days')->default(1);
                $table->text('reason');
                $table->string('attachment_path', 255)->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->text('approver_note')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hris_leave_balances')) {
            Schema::create('hris_leave_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->integer('year');
                $table->integer('total_quota')->default(12);
                $table->integer('used_quota')->default(0);
                $table->integer('remaining_quota')->default(12);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hris_schedule_requests')) {
            Schema::create('hris_schedule_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->date('request_date');
                $table->string('current_shift', 50);
                $table->string('target_shift', 50);
                $table->text('reason');
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->text('approver_note')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        // 4. Overtime (Lembur)
        if (!Schema::hasTable('hris_overtimes')) {
            Schema::create('hris_overtimes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->date('date');
                $table->time('start_time');
                $table->time('end_time');
                $table->decimal('duration_hours', 5, 2);
                $table->text('reason');
                $table->decimal('rate_per_hour', 15, 2)->default(25000);
                $table->decimal('total_pay', 15, 2)->default(0);
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->text('approver_note')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        // 5. Claims & Reimbursement
        if (!Schema::hasTable('hris_claim_types')) {
            Schema::create('hris_claim_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('code', 50);
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->decimal('max_amount_per_claim', 15, 2)->nullable();
                $table->decimal('max_amount_per_month', 15, 2)->nullable();
                $table->boolean('require_proof')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hris_claims')) {
            Schema::create('hris_claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->foreignId('claim_type_id')->nullable()->constrained('hris_claim_types')->onDelete('set null');
                $table->string('code', 50)->unique();
                $table->date('claim_date');
                $table->decimal('amount', 15, 2);
                $table->text('description');
                $table->longText('proof_base64')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->text('approver_note')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        // 6. Inventory Assets
        if (!Schema::hasTable('hris_assets')) {
            Schema::create('hris_assets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('asset_code', 50)->unique();
                $table->string('name', 150);
                $table->string('category', 50);
                $table->string('serial_number', 100)->nullable();
                $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('set null');
                $table->enum('status', ['available', 'storage', 'assigned', 'maintenance', 'damaged', 'disposed', 'lost'])->default('available');
                $table->date('handover_date')->nullable();
                $table->date('returned_date')->nullable();
                $table->string('condition', 50)->default('Baik');
                $table->longText('photo_base64')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 7. Loans & Kasbon
        if (!Schema::hasTable('hris_loans')) {
            Schema::create('hris_loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->string('code', 50)->unique();
                $table->decimal('amount', 15, 2);
                $table->integer('tenor_months')->default(1);
                $table->decimal('monthly_deduction', 15, 2);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->decimal('remaining_amount', 15, 2);
                $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
                $table->text('reason')->nullable();
                $table->date('disbursed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hris_loan_payments')) {
            Schema::create('hris_loan_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_id')->constrained('hris_loans')->onDelete('cascade');
                $table->decimal('amount', 15, 2);
                $table->date('payment_date');
                $table->enum('source', ['payroll_deduction', 'cash_transfer'])->default('payroll_deduction');
                $table->foreignId('payroll_id')->nullable()->constrained('hris_payrolls')->onDelete('set null');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 8. News & Internal Announcements
        if (!Schema::hasTable('hris_news')) {
            Schema::create('hris_news', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('title', 200);
                $table->string('category', 50)->default('Announcement');
                $table->text('content');
                $table->longText('banner_base64')->nullable();
                $table->enum('priority', ['normal', 'urgent'])->default('normal');
                $table->enum('target_audience', ['all', 'drivers_only', 'staff_only'])->default('all');
                $table->boolean('is_published')->default(true);
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
            });
        }

        // 9. KPI & Performance Reviews (Employee & Department)
        if (!Schema::hasTable('hris_performance_reviews')) {
            Schema::create('hris_performance_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->enum('target_type', ['employee', 'department'])->default('employee');
                $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('cascade');
                $table->string('department', 100)->nullable();
                $table->string('period', 50);
                $table->decimal('score', 3, 2)->default(0);
                $table->string('grade', 5)->default('B');
                $table->decimal('attendance_score', 3, 2)->default(0);
                $table->decimal('task_completion_score', 3, 2)->default(0);
                $table->decimal('discipline_score', 3, 2)->default(0);
                $table->decimal('teamwork_score', 3, 2)->default(0);
                $table->text('remarks')->nullable();
                $table->foreignId('reviewer_id')->constrained('users');
                $table->enum('status', ['draft', 'finalized'])->default('draft');
                $table->timestamps();
            });
        }

        // 10. Digital Documents (KTP, Ijazah, Kontrak, dll + Brankas Fisik)
        if (!Schema::hasTable('hris_documents')) {
            Schema::create('hris_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->string('title', 150);
                $table->string('category', 50);
                $table->longText('file_base64')->nullable();
                $table->string('document_name', 255)->nullable();
                $table->string('document_path', 255)->nullable();
                $table->string('file_name', 255)->nullable();
                $table->string('file_type', 50)->default('image/jpeg');
                $table->integer('file_size_kb')->default(0);
                $table->string('physical_location', 255)->nullable();
                $table->boolean('is_original_stored')->default(false);
                $table->boolean('is_verified')->default(false);
                $table->foreignId('verified_by')->nullable()->constrained('users');
                $table->timestamp('verified_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }


        // 12. Compliance & Alerts (Legalitas SIM, STNK, KIR, K3, dll)
        if (!Schema::hasTable('hris_compliance_items')) {
            Schema::create('hris_compliance_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->enum('target_type', ['employee', 'vehicle']);
                $table->unsignedBigInteger('target_id'); // ID Employee atau ID Vehicle
                $table->string('doc_name', 100); // 'SIM B1 Umum', 'STNK', 'Uji KIR', 'Sertifikat K3', dll.
                $table->string('doc_number', 100)->nullable();
                $table->date('expiry_date');
                $table->enum('status', ['safe', 'warning', 'expired'])->default('safe');
                $table->integer('reminder_days_before')->default(30);
                $table->longText('document_base64')->nullable();
                $table->date('renewed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'expiry_date']);
            });
        }

        // 13. Training & Capacity Building
        if (!Schema::hasTable('hris_trainings')) {
            Schema::create('hris_trainings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('title', 150);
                $table->string('category', 50); // 'Safety & Driving', 'K3', 'Technical', 'Soft Skills'
                $table->string('trainer_name', 100);
                $table->date('training_date');
                $table->string('location_or_link', 255)->nullable();
                $table->integer('duration_hours')->default(4);
                $table->enum('status', ['planned', 'ongoing', 'completed', 'cancelled'])->default('planned');
                $table->text('description')->nullable();
                $table->timestamps();
                $table->index('tenant_id');
            });
        }

        if (!Schema::hasTable('hris_training_participants')) {
            Schema::create('hris_training_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_id')->constrained('hris_trainings')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->enum('attendance_status', ['registered', 'attended', 'absent'])->default('registered');
                $table->integer('score')->nullable();
                $table->boolean('passed')->default(false);
                $table->string('certificate_number', 100)->nullable();
                $table->longText('certificate_base64')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 11. Pelanggaran & Surat Peringatan (SP 1, SP 2, SP 3, Teguran)
        if (!Schema::hasTable('hris_violation_types')) {
            Schema::create('hris_violation_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('code', 50);
                $table->string('name', 100);
                $table->integer('default_duration_months')->default(6);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hris_violations')) {
            Schema::create('hris_violations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->date('violation_date');
                $table->date('valid_from');
                $table->date('valid_until');
                $table->string('violation_type', 50);
                $table->string('document_number', 100)->nullable();
                $table->string('contract_number', 100)->nullable();
                $table->text('description');
                $table->text('violation_points')->nullable();
                $table->text('legal_basis')->nullable();
                $table->longText('evidence_base64')->nullable();
                $table->enum('status', ['active', 'expired', 'revoked'])->default('active');
                $table->foreignId('issued_by')->constrained('users');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hris_training_participants');
        Schema::dropIfExists('hris_trainings');
        Schema::dropIfExists('hris_compliance_items');
        Schema::dropIfExists('hris_violations');
        Schema::dropIfExists('hris_violation_types');
        Schema::dropIfExists('hris_contracts');
        Schema::dropIfExists('hris_documents');
        Schema::dropIfExists('hris_performance_reviews');
        Schema::dropIfExists('hris_news');
        Schema::dropIfExists('hris_loan_payments');
        Schema::dropIfExists('hris_loans');
        Schema::dropIfExists('hris_assets');
        Schema::dropIfExists('hris_claims');
        Schema::dropIfExists('hris_claim_types');
        Schema::dropIfExists('hris_overtimes');
        Schema::dropIfExists('hris_schedule_requests');
        Schema::dropIfExists('hris_leave_balances');
        Schema::dropIfExists('hris_leave_requests');
        Schema::dropIfExists('hris_payroll_items');
        Schema::dropIfExists('hris_payrolls');
        Schema::dropIfExists('hris_employee_bpjs');
        Schema::dropIfExists('hris_employee_allowances');
        Schema::dropIfExists('hris_allowance_types');
        Schema::dropIfExists('hris_employee_salaries');
        Schema::dropIfExists('hris_payroll_periods');
        Schema::dropIfExists('attendance_settings');
    }
};
