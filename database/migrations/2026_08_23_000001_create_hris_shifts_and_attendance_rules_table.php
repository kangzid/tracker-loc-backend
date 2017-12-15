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
        // 1. Master Shifts Table
        if (!Schema::hasTable('hris_shifts')) {
            Schema::create('hris_shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->string('name'); // e.g. Shift Pagi, Shift Siang, Shift Malam
                $table->string('code', 50)->nullable(); // e.g. SHF-01
                $table->string('check_in_start', 10)->default('06:00'); // Jam mulai buka check-in
                $table->string('work_start_time', 10)->default('07:00'); // Jam masuk resmi
                $table->string('late_tolerance_time', 10)->default('07:15'); // Toleransi keterlambatan
                $table->string('check_in_end', 10)->default('08:00'); // Batas akhir cut-off absen masuk
                $table->string('work_end_time', 10)->default('15:00'); // Jam pulang kerja
                $table->boolean('is_night_shift')->default(false);
                $table->string('color', 20)->default('#3b82f6');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('tenant_id');
            });
        }

        // 2. Shift Assignments (Roster) Table
        if (!Schema::hasTable('hris_shift_assignments')) {
            Schema::create('hris_shift_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->foreignId('shift_id')->constrained('hris_shifts')->onDelete('cascade');
                $table->date('date');
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'date'], 'idx_emp_date_shift_unique');
                $table->index(['tenant_id', 'date']);
            });
        }

        // 3. Ensure hris_attendance_settings table exists with complete rule columns
        if (!Schema::hasTable('hris_attendance_settings')) {
            Schema::create('hris_attendance_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->boolean('is_shift_enabled')->default(false);
                $table->string('check_in_start', 10)->default('06:00');
                $table->string('work_start_time', 10)->default('08:00');
                $table->string('late_tolerance_time', 10)->default('08:15');
                $table->string('check_in_end', 10)->default('09:00');
                $table->boolean('lock_after_late_cutoff')->default(true);
                $table->string('late_cutoff_policy', 20)->default('empty');
                $table->string('work_end_time', 10)->default('17:00');
                $table->boolean('min_checkout_at_work_end')->default(true);
                $table->boolean('require_geofence_checkout')->default(true);
                $table->timestamps();

                $table->index('tenant_id');
            });
        } else {
            Schema::table('hris_attendance_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('hris_attendance_settings', 'is_shift_enabled')) {
                    $table->boolean('is_shift_enabled')->default(false)->after('tenant_id');
                }
                if (!Schema::hasColumn('hris_attendance_settings', 'check_in_start')) {
                    $table->string('check_in_start', 10)->default('06:00')->after('is_shift_enabled');
                }
                if (!Schema::hasColumn('hris_attendance_settings', 'work_start_time')) {
                    $table->string('work_start_time', 10)->default('08:00')->after('check_in_start');
                }
                if (!Schema::hasColumn('hris_attendance_settings', 'late_tolerance_time')) {
                    $table->string('late_tolerance_time', 10)->default('08:15')->after('work_start_time');
                }
                if (!Schema::hasColumn('hris_attendance_settings', 'check_in_end')) {
                    $table->string('check_in_end', 10)->default('09:00')->after('late_tolerance_time');
                }
                if (!Schema::hasColumn('hris_attendance_settings', 'lock_after_late_cutoff')) {
                    $table->boolean('lock_after_late_cutoff')->default(true)->after('check_in_end');
                }
                if (!Schema::hasColumn('hris_attendance_settings', 'late_cutoff_policy')) {
                    $table->string('late_cutoff_policy', 20)->default('empty')->after('lock_after_late_cutoff');
                }
                if (!Schema::hasColumn('hris_attendance_settings', 'work_end_time')) {
                    $table->string('work_end_time', 10)->default('17:00')->after('late_cutoff_policy');
                }
                if (!Schema::hasColumn('hris_attendance_settings', 'min_checkout_at_work_end')) {
                    $table->boolean('min_checkout_at_work_end')->default(true)->after('work_end_time');
                }
                if (!Schema::hasColumn('hris_attendance_settings', 'require_geofence_checkout')) {
                    $table->boolean('require_geofence_checkout')->default(true)->after('min_checkout_at_work_end');
                }
            });
        }

        // 4. Update attendances table with shift_id
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (!Schema::hasColumn('attendances', 'shift_id')) {
                    $table->foreignId('shift_id')->nullable()->constrained('hris_shifts')->nullOnDelete()->after('employee_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendances') && Schema::hasColumn('attendances', 'shift_id')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropConstrainedForeignId('shift_id');
            });
        }

        Schema::dropIfExists('hris_shift_assignments');
        Schema::dropIfExists('hris_shifts');
        Schema::dropIfExists('hris_attendance_settings');
    }
};
