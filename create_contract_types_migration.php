<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// Create hris_contract_types table
if (!Schema::hasTable('hris_contract_types')) {
    Schema::create('hris_contract_types', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->string('code')->nullable();
        $table->string('name');
        $table->boolean('is_permanent')->default(false);
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();

        $table->index(['tenant_id', 'is_active']);
    });
    echo "Created hris_contract_types table successfully!" . PHP_EOL;
}

// Add allowances_json to hris_contracts if missing
if (!Schema::hasColumn('hris_contracts', 'allowances_json')) {
    Schema::table('hris_contracts', function (Blueprint $table) {
        $table->longText('allowances_json')->nullable()->after('allowance_communication');
    });
    echo "Added allowances_json to hris_contracts!" . PHP_EOL;
}
