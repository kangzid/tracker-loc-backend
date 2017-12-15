<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// 1. Table hris_resignations
if (!Schema::hasTable('hris_resignations')) {
    Schema::create('hris_resignations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->unsignedBigInteger('employee_id');
        $table->string('resignation_number')->unique();
        $table->string('category'); // 'Resign Sukarela', 'Habis Masa Kontrak', 'Pemutusan Hubungan Kerja (PHK)', 'Pensiun', 'Indisipliner', 'Lainnya'
        $table->date('resignation_date');
        $table->date('last_working_date')->nullable();
        $table->text('reason')->nullable();
        $table->string('document_path')->nullable();
        $table->string('document_name')->nullable();
        $table->longText('document_base64')->nullable();
        $table->string('status')->default('approved'); // approved, pending, cancelled
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();

        $table->index(['tenant_id', 'employee_id']);
        $table->index(['tenant_id', 'category']);
    });
    echo "Created hris_resignations table!" . PHP_EOL;
}

// 2. Add exit_date and status column to employees if not exists
if (Schema::hasTable('employees')) {
    Schema::table('employees', function (Blueprint $table) {
        if (!Schema::hasColumn('employees', 'exit_date')) {
            $table->date('exit_date')->nullable()->after('join_date');
        }
        if (!Schema::hasColumn('employees', 'is_active')) {
            $table->boolean('is_active')->default(true)->after('exit_date');
        }
    });
    echo "Updated employees table with exit_date & is_active columns!" . PHP_EOL;
}
