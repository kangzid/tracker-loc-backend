<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hris_attendance_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('hris_attendance_settings', 'standard_working_days')) {
                $table->json('standard_working_days')->nullable()->after('is_shift_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hris_attendance_settings', function (Blueprint $table) {
            if (Schema::hasColumn('hris_attendance_settings', 'standard_working_days')) {
                $table->dropColumn('standard_working_days');
            }
        });
    }
};
