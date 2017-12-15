<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// 1. Table hris_banks
if (!Schema::hasTable('hris_banks')) {
    Schema::create('hris_banks', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->string('name');
        $table->string('code')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();

        $table->index(['tenant_id', 'is_active']);
    });
    echo "Created hris_banks table!" . PHP_EOL;
}

// 2. Add bank columns to hris_contracts
if (Schema::hasTable('hris_contracts')) {
    Schema::table('hris_contracts', function (Blueprint $table) {
        if (!Schema::hasColumn('hris_contracts', 'bank_name')) {
            $table->string('bank_name')->nullable()->after('allowance_communication');
        }
        if (!Schema::hasColumn('hris_contracts', 'bank_account_number')) {
            $table->string('bank_account_number')->nullable()->after('bank_name');
        }
        if (!Schema::hasColumn('hris_contracts', 'bank_account_holder')) {
            $table->string('bank_account_holder')->nullable()->after('bank_account_number');
        }
    });
    echo "Updated hris_contracts with bank columns!" . PHP_EOL;
}

// 3. Table hris_mutations
if (!Schema::hasTable('hris_mutations')) {
    Schema::create('hris_mutations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->unsignedBigInteger('employee_id');
        $table->string('mutation_number')->unique();
        $table->string('mutation_type'); // 'Promosi Jabatan', 'Demosi Jabatan', 'Rotasi / Mutasi Departemen', 'Penyesuaian Status'
        $table->date('effective_date');
        $table->string('old_department')->nullable();
        $table->string('new_department')->nullable();
        $table->string('old_position')->nullable();
        $table->string('new_position')->nullable();
        $table->string('old_employment_status')->nullable();
        $table->string('new_employment_status')->nullable();
        $table->text('reason')->nullable();
        $table->string('document_sk_path')->nullable();
        $table->string('document_sk_name')->nullable();
        $table->longText('document_sk_base64')->nullable();
        $table->string('status')->default('approved'); // approved, pending, cancelled
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();

        $table->index(['tenant_id', 'employee_id']);
        $table->index(['tenant_id', 'mutation_type']);
    });
    echo "Created hris_mutations table!" . PHP_EOL;
}
