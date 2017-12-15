<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. employees
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!Schema::hasColumn('employees', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (!Schema::hasColumn('employees', 'exit_date')) {
                    $table->date('exit_date')->nullable();
                }
            });
        }

        // 2. hris_asset_categories
        if (Schema::hasTable('hris_asset_categories')) {
            Schema::table('hris_asset_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('hris_asset_categories', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
            });
        }

        // 3. hris_news
        if (Schema::hasTable('hris_news')) {
            Schema::table('hris_news', function (Blueprint $table) {
                if (!Schema::hasColumn('hris_news', 'banner_path')) {
                    $table->string('banner_path')->nullable();
                }
                if (!Schema::hasColumn('hris_news', 'views')) {
                    $table->integer('views')->default(0);
                }
                if (!Schema::hasColumn('hris_news', 'priority')) {
                    $table->string('priority', 20)->default('normal');
                }
                if (!Schema::hasColumn('hris_news', 'target_audience')) {
                    $table->string('target_audience', 50)->default('all');
                }
            });
        }

        // 4. hris_contracts
        if (Schema::hasTable('hris_contracts')) {
            Schema::table('hris_contracts', function (Blueprint $table) {
                if (!Schema::hasColumn('hris_contracts', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('hris_contracts', 'contract_date')) {
                    $table->date('contract_date')->nullable();
                }
                if (!Schema::hasColumn('hris_contracts', 'basic_salary')) {
                    $table->decimal('basic_salary', 15, 2)->default(0);
                }
                if (!Schema::hasColumn('hris_contracts', 'allowances_json')) {
                    $table->json('allowances_json')->nullable();
                }
                if (!Schema::hasColumn('hris_contracts', 'document_number')) {
                    $table->string('document_number', 100)->nullable();
                }
                if (!Schema::hasColumn('hris_contracts', 'department')) {
                    $table->string('department', 100)->nullable();
                }
                if (!Schema::hasColumn('hris_contracts', 'position')) {
                    $table->string('position', 100)->nullable();
                }
                if (!Schema::hasColumn('hris_contracts', 'bank_name')) {
                    $table->string('bank_name', 100)->nullable();
                }
                if (!Schema::hasColumn('hris_contracts', 'bank_account_number')) {
                    $table->string('bank_account_number', 50)->nullable();
                }
                if (!Schema::hasColumn('hris_contracts', 'bank_account_holder')) {
                    $table->string('bank_account_holder', 100)->nullable();
                }
                if (!Schema::hasColumn('hris_contracts', 'document_pdf_path')) {
                    $table->string('document_pdf_path')->nullable();
                }
                if (!Schema::hasColumn('hris_contracts', 'document_pdf_name')) {
                    $table->string('document_pdf_name')->nullable();
                }
                if (!Schema::hasColumn('hris_contracts', 'notes')) {
                    $table->text('notes')->nullable();
                }
            });
        }

        // 5. hris_documents
        if (Schema::hasTable('hris_documents')) {
            Schema::table('hris_documents', function (Blueprint $table) {
                if (!Schema::hasColumn('hris_documents', 'document_name')) {
                    $table->string('document_name', 255)->nullable();
                }
                if (!Schema::hasColumn('hris_documents', 'document_path')) {
                    $table->string('document_path')->nullable();
                }
                if (!Schema::hasColumn('hris_documents', 'file_name')) {
                    $table->string('file_name', 255)->nullable();
                }
                if (!Schema::hasColumn('hris_documents', 'file_type')) {
                    $table->string('file_type', 50)->nullable();
                }
                if (!Schema::hasColumn('hris_documents', 'file_size_kb')) {
                    $table->integer('file_size_kb')->default(0);
                }
                if (!Schema::hasColumn('hris_documents', 'physical_location')) {
                    $table->string('physical_location', 255)->nullable();
                }
                if (!Schema::hasColumn('hris_documents', 'is_original_stored')) {
                    $table->boolean('is_original_stored')->default(false);
                }
                if (!Schema::hasColumn('hris_documents', 'is_verified')) {
                    $table->boolean('is_verified')->default(false);
                }
            });
        }

        // 6. hris_violations
        if (Schema::hasTable('hris_violations')) {
            Schema::table('hris_violations', function (Blueprint $table) {
                if (!Schema::hasColumn('hris_violations', 'evidence_path')) {
                    $table->string('evidence_path')->nullable();
                }
                if (!Schema::hasColumn('hris_violations', 'evidence_name')) {
                    $table->string('evidence_name')->nullable();
                }
            });
        }

        // 7. hris_resignations
        if (Schema::hasTable('hris_resignations')) {
            Schema::table('hris_resignations', function (Blueprint $table) {
                if (!Schema::hasColumn('hris_resignations', 'last_working_date')) {
                    $table->date('last_working_date')->nullable();
                }
            });
        }

        // 8. hris_compliance_items
        if (Schema::hasTable('hris_compliance_items')) {
            Schema::table('hris_compliance_items', function (Blueprint $table) {
                if (!Schema::hasColumn('hris_compliance_items', 'document_path')) {
                    $table->string('document_path')->nullable();
                }
                if (!Schema::hasColumn('hris_compliance_items', 'document_name')) {
                    $table->string('document_name')->nullable();
                }
            });
        }

        // 9. hris_assets
        if (Schema::hasTable('hris_assets')) {
            Schema::table('hris_assets', function (Blueprint $table) {
                if (!Schema::hasColumn('hris_assets', 'return_date')) {
                    $table->date('return_date')->nullable();
                }
            });
        }

        // 10. hris_claims
        if (Schema::hasTable('hris_claims')) {
            Schema::table('hris_claims', function (Blueprint $table) {
                if (!Schema::hasColumn('hris_claims', 'title')) {
                    $table->string('title', 150)->nullable();
                }
                if (!Schema::hasColumn('hris_claims', 'code')) {
                    $table->string('code', 50)->nullable();
                }
                if (!Schema::hasColumn('hris_claims', 'receipt_path')) {
                    $table->string('receipt_path')->nullable();
                }
                if (!Schema::hasColumn('hris_claims', 'receipt_name')) {
                    $table->string('receipt_name', 255)->nullable();
                }
            });
        }

        // 11. hris_loan_payments
        if (Schema::hasTable('hris_loan_payments')) {
            Schema::table('hris_loan_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('hris_loan_payments', 'payment_method')) {
                    $table->string('payment_method', 50)->default('payroll_deduction');
                }
                if (!Schema::hasColumn('hris_loan_payments', 'notes')) {
                    $table->text('notes')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
    }
};
