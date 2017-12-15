<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// 1. Training Categories
if (!Schema::hasTable('hris_training_categories')) {
    Schema::create('hris_training_categories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->string('name');
        $table->string('code')->nullable();
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();

        $table->index(['tenant_id', 'is_active']);
    });
    echo "Created hris_training_categories table!" . PHP_EOL;
}

// 2. KPI Periods
if (!Schema::hasTable('hris_kpi_periods')) {
    Schema::create('hris_kpi_periods', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->string('name'); // e.g. Semester 2 2026, Q3 2026
        $table->string('period_type')->default('quarterly'); // monthly, quarterly, semester, annual
        $table->date('start_date');
        $table->date('end_date');
        $table->string('status')->default('open'); // open, closed, draft
        $table->text('description')->nullable();
        $table->timestamps();

        $table->index(['tenant_id', 'status']);
    });
    echo "Created hris_kpi_periods table!" . PHP_EOL;
}

// 3. Compliance Document Types
if (!Schema::hasTable('hris_compliance_doc_types')) {
    Schema::create('hris_compliance_doc_types', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->string('target_type'); // 'employee' or 'vehicle'
        $table->string('name'); // e.g. SIM B1, STNK, Uji KIR
        $table->string('code')->nullable();
        $table->integer('default_reminder_days')->default(30);
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();

        $table->index(['tenant_id', 'target_type', 'is_active']);
    });
    echo "Created hris_compliance_doc_types table!" . PHP_EOL;
}

// 4. Asset Categories
if (!Schema::hasTable('hris_asset_categories')) {
    Schema::create('hris_asset_categories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->string('name');
        $table->string('code')->nullable();
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();

        $table->index(['tenant_id', 'is_active']);
    });
    echo "Created hris_asset_categories table!" . PHP_EOL;
}
