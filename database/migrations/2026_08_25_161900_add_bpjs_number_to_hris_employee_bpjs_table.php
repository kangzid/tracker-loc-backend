<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hris_employee_bpjs', function (Blueprint $table) {
            if (!Schema::hasColumn('hris_employee_bpjs', 'bpjs_number')) {
                $table->string('bpjs_number')->nullable()->after('bpjs_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hris_employee_bpjs', function (Blueprint $table) {
            if (Schema::hasColumn('hris_employee_bpjs', 'bpjs_number')) {
                $table->dropColumn('bpjs_number');
            }
        });
    }
};
