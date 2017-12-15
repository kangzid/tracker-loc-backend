<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

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
    echo "Created hris_contracts table successfully!" . PHP_EOL;
} else {
    echo "hris_contracts table already exists!" . PHP_EOL;
}
