<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Geofence;
use App\Models\HrisAttendanceSetting;
use App\Models\HrisShift;
use App\Models\HrisShiftAssignment;
use App\Services\GeofenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AttendanceController extends Controller
{
    protected GeofenceService $geofenceService;

    public function __construct(GeofenceService $geofenceService)
    {
        $this->geofenceService = $geofenceService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $adminId = $user->id;
            $attendances = Attendance::with([
                'employee' => function($query) {
                    $query->select('id', 'user_id', 'admin_id', 'employee_id', 'department', 'position');
                }, 
                'employee.user' => function($query) {
                    $query->select('id', 'name', 'email');
                },
                'shift:id,name,code,color,work_start_time,work_end_time'
            ])
            ->whereHas('employee', function ($q) use ($adminId) {
                $q->where('admin_id', $adminId);
            })
            ->orderBy('date', 'desc')
            ->paginate(20);
        } else {
            $employee = $user->employee;
            if (!$employee) {
                return response()->json(['message' => 'Employee profile not found'], 404);
            }

            $attendances = Attendance::forEmployee($employee->id)
                ->with('shift:id,name,code,color,work_start_time,work_end_time')
                ->orderBy('date', 'desc')
                ->paginate(20);
        }

        return response()->json($attendances);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'type' => 'required|in:check_in,check_out',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $adminId = $employee->admin_id;

        // 1. Geofence verification
        $isInOffice = $this->geofenceService->isInsideGeofence(
            $request->latitude, 
            $request->longitude, 
            $adminId
        );

        if (!$isInOffice) {
            return response()->json([
                'error' => 'OUTSIDE_GEOFENCE',
                'message' => 'Anda berada di luar area kantor. Silakan mendekat ke area kantor untuk melakukan absensi.',
                'title' => 'Lokasi Di Luar Area Kantor'
            ], 422);
        }

        // 2. Resolve Active Schedule (Shift or Regular)
        $schedule = $this->getEffectiveSchedule($employee);
        $now = Carbon::now();
        $currentTimeStr = $now->format('H:i');

        $today = Carbon::today();
        $attendance = Attendance::firstOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            [
                'admin_id' => $adminId,
                'status' => 'present',
                'shift_id' => $schedule['shift_id'],
            ]
        );

        if ($request->type === 'check_in') {
            if ($attendance->check_in) {
                return response()->json([
                    'error' => 'ALREADY_CHECKED_IN',
                    'message' => 'Anda sudah melakukan absensi masuk hari ini pada pukul ' . Carbon::parse($attendance->check_in)->format('H:i') . ' WIB.',
                ], 400);
            }

            // Check if too early
            if ($schedule['check_in_start'] && $currentTimeStr < $schedule['check_in_start']) {
                return response()->json([
                    'error' => 'TOO_EARLY',
                    'message' => "Belum waktu absensi masuk. Absen dibuka mulai pukul {$schedule['check_in_start']} WIB.",
                    'title' => 'Absensi Belum Dibuka',
                    'schedule' => $schedule,
                ], 422);
            }

            // Check if past late cut-off limit and lock is enabled
            if ($schedule['lock_after_late_cutoff'] && $schedule['check_in_end'] && $currentTimeStr > $schedule['check_in_end']) {
                return response()->json([
                    'error' => 'LOCKED_LATE_CUTOFF',
                    'message' => "Batas waktu absensi masuk telah berakhir (pukul {$schedule['check_in_end']} WIB). Silakan hubungi HRD untuk konfirmasi kehadiran Anda.",
                    'title' => 'Waktu Absensi Berakhir',
                    'schedule' => $schedule,
                ], 422);
            }

            // Determine status (Late or Present)
            $threshold = $schedule['late_tolerance_time'] ?: $schedule['work_start_time'];
            $status = 'present';
            if ($threshold && $currentTimeStr > $threshold) {
                $status = 'late';
            }

            $attendance->update([
                'check_in' => now(),
                'check_in_lat' => $request->latitude,
                'check_in_lng' => $request->longitude,
                'status' => $status,
                'shift_id' => $schedule['shift_id'] ?: $attendance->shift_id,
            ]);
            
            Cache::forget("attendance:today:employee:{$employee->id}:" . Carbon::today()->format('Y-m-d'));
        } else {
            // Check Out
            if (!$attendance->check_in) {
                return response()->json([
                    'error' => 'MUST_CHECK_IN_FIRST',
                    'message' => 'Anda harus melakukan absensi masuk terlebih dahulu.',
                ], 400);
            }
            if ($attendance->check_out) {
                return response()->json([
                    'error' => 'ALREADY_CHECKED_OUT',
                    'message' => 'Anda sudah melakukan absensi keluar hari ini pada pukul ' . Carbon::parse($attendance->check_out)->format('H:i') . ' WIB.',
                ], 400);
            }

            // Verify if checkout before work end time is prohibited
            if ($schedule['min_checkout_at_work_end'] && $schedule['work_end_time'] && $currentTimeStr < $schedule['work_end_time']) {
                return response()->json([
                    'error' => 'EARLY_CHECKOUT',
                    'message' => "Belum waktu jam pulang kerja. Absen keluar dapat dilakukan mulai pukul {$schedule['work_end_time']} WIB.",
                    'title' => 'Belum Jam Pulang',
                    'schedule' => $schedule,
                ], 422);
            }

            $attendance->update([
                'check_out' => now(),
                'check_out_lat' => $request->latitude,
                'check_out_lng' => $request->longitude,
            ]);
            
            Cache::forget("attendance:today:employee:{$employee->id}:" . Carbon::today()->format('Y-m-d'));
        }

        return response()->json([
            'message' => $request->type === 'check_in' ? 'Absensi masuk berhasil dicatat' : 'Absensi keluar berhasil dicatat',
            'attendance' => $attendance->load('shift'),
            'schedule' => $schedule,
        ]);
    }

    public function show($id)
    {
        $user = auth()->user();
        $attendance = Attendance::with(['employee.user', 'shift'])->findOrFail($id);

        if ($user->isAdmin()) {
            if ($attendance->employee->admin_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } else {
            if (!$user->employee || $attendance->employee_id !== $user->employee->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json($attendance);
    }

    /**
     * Get Today Attendance & Schedule Window for Mobile App
     */
    public function todayAttendance(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $todayStr = Carbon::today()->format('Y-m-d');
        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('date', $todayStr)
            ->with('shift')
            ->first();

        $schedule = $this->getEffectiveSchedule($employee);
        $now = Carbon::now();
        $currentTimeStr = $now->format('H:i');

        // Calculate Window Status & Permissions
        $canCheckIn = false;
        $canCheckOut = false;
        $windowStatus = 'ontime';
        $windowMessage = 'Waktu absensi dibuka.';

        if (!$attendance || !$attendance->check_in) {
            // Not checked in yet
            if (!empty($schedule['is_day_off'])) {
                $canCheckIn = true; // allow voluntary check-in / overtime
                $windowStatus = 'day_off';
                $windowMessage = "Hari ini adalah hari libur kerja Anda (Day Off).";
            } elseif ($schedule['check_in_start'] && $currentTimeStr < $schedule['check_in_start']) {
                $canCheckIn = false;
                $windowStatus = 'too_early';
                $windowMessage = "Absen masuk dibuka pukul {$schedule['check_in_start']} WIB.";
            } elseif ($schedule['lock_after_late_cutoff'] && $schedule['check_in_end'] && $currentTimeStr > $schedule['check_in_end']) {
                $canCheckIn = false;
                $windowStatus = 'locked_late';
                $windowMessage = "Batas waktu absensi masuk berakhir pukul {$schedule['check_in_end']} WIB. Hubungi HRD.";
            } else {
                $canCheckIn = true;
                $threshold = $schedule['late_tolerance_time'] ?: $schedule['work_start_time'];
                if ($threshold && $currentTimeStr > $threshold) {
                    $windowStatus = 'late';
                    $windowMessage = "Anda terlambat. Batas toleransi adalah {$threshold} WIB.";
                } else {
                    $windowStatus = 'ontime';
                    $windowMessage = "Waktu absensi masuk normal.";
                }
            }
        } elseif (!$attendance->check_out) {
            // Already checked in, waiting for checkout
            if ($schedule['min_checkout_at_work_end'] && $schedule['work_end_time'] && $currentTimeStr < $schedule['work_end_time']) {
                $canCheckOut = false;
                $windowStatus = 'checked_in_waiting_checkout';
                $windowMessage = "Absen keluar dibuka mulai jam pulang pukul {$schedule['work_end_time']} WIB.";
            } else {
                $canCheckOut = true;
                $windowStatus = 'ready_checkout';
                $windowMessage = "Waktu absensi keluar telah dibuka.";
            }
        } else {
            // Completed
            $windowStatus = 'completed';
            $windowMessage = "Absensi hari ini telah lengkap.";
        }

        // Compute Weekly Shift Roster for this Employee
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weeklyRoster = [];
        $dayNamesShort = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
        $dayNamesFull = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startOfWeek->copy()->addDays($i);
            $dateStr = $currentDate->format('Y-m-d');
            $daySchedule = $this->getEffectiveSchedule($employee, $dateStr);

            $weeklyRoster[] = [
                'day_index' => $i,
                'day_name' => $dayNamesShort[$i],
                'day_full' => $dayNamesFull[$i],
                'date' => $dateStr,
                'date_day' => $currentDate->format('d'),
                'is_today' => $dateStr === $todayStr,
                'is_past' => $dateStr < $todayStr,
                'is_shift' => $daySchedule['is_shift'],
                'is_day_off' => $daySchedule['is_day_off'] ?? false,
                'shift_id' => $daySchedule['shift_id'],
                'shift_name' => $daySchedule['shift_name'],
                'shift_code' => $daySchedule['shift_code'],
                'color' => $daySchedule['color'],
                'work_start_time' => $daySchedule['work_start_time'],
                'work_end_time' => $daySchedule['work_end_time'],
            ];
        }

        $monthNamesId = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $currentMonthLabel = $monthNamesId[$now->month - 1] . ' ' . $now->year;

        return response()->json([
            'attendance' => $attendance,
            'schedule' => $schedule,
            'weekly_roster' => $weeklyRoster,
            'current_month_label' => $currentMonthLabel,
            'can_check_in' => $canCheckIn,
            'can_check_out' => $canCheckOut,
            'window_status' => $windowStatus,
            'window_message' => $windowMessage,
            'server_time' => $now->toIso8601String(),
        ]);
    }

    public function monthlyAttendance(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $attendances = Attendance::forEmployee($employee->id)
            ->with('shift:id,name,code,color')
            ->thisMonth()
            ->orderBy('date', 'desc')
            ->get();

        return response()->json($attendances);
    }

    // Admin only methods
    public function getEmployeeAttendances(Request $request, $employeeId)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $adminId = $request->user()->id;
        $employee = Employee::where('id', $employeeId)
            ->where('admin_id', $adminId)
            ->first();

        if (!$employee) {
            return response()->json(['message' => 'Employee not found or does not belong to your tenant'], 404);
        }

        $validator = Validator::make($request->all(), [
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2020',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $month = (int) ($request->month ?? Carbon::now()->month);
        $year = (int) ($request->year ?? Carbon::now()->year);

        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = Carbon::create($year, $month, $daysInMonth)->endOfDay();

        $attendances = Attendance::with(['employee.user', 'shift'])
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate->format('Y-m-d 00:00:00'), $endDate->format('Y-m-d 23:59:59')])
            ->orderBy('date', 'asc')
            ->get();

        $result = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $carbonDate = Carbon::create($year, $month, $day);
            $dateStr = $carbonDate->format('Y-m-d');
            
            $att = $attendances->first(function ($item) use ($dateStr) {
                $itemDate = is_string($item->date) ? substr($item->date, 0, 10) : (is_object($item->date) ? $item->date->format('Y-m-d') : '');
                return $itemDate === $dateStr;
            });

            $result[] = [
                'date' => $dateStr,
                'day_name' => $carbonDate->format('l'), // e.g. "Sunday", "Monday"
                'attendance' => $att,
            ];
        }

        return response()->json([
            'employee' => $employee->load('user'),
            'month' => $month,
            'year' => $year,
            'days_in_month' => $daysInMonth,
            'attendances' => $result,
            'days' => $result,
        ]);
    }

    /**
     * Helper to verify if an attendance date is within the tenant's modification limit window
     */
    private function checkDateModificationLimit($adminId, $targetDate, $actionName = 'memodifikasi')
    {
        $setting = HrisAttendanceSetting::where('tenant_id', $adminId)->first();
        $limitDays = $setting ? (int) $setting->edit_delete_limit_days : 0;

        if ($limitDays > 0) {
            $parsedTarget = Carbon::parse($targetDate)->startOfDay();
            $cutoffDate = Carbon::now()->startOfDay()->subDays($limitDays);

            if ($parsedTarget->lt($cutoffDate)) {
                return "Batas waktu telah terlewati. Anda hanya dapat {$actionName} absensi dalam rentang {$limitDays} hari terakhir.";
            }
        }

        return null;
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $attendance = Attendance::findOrFail($id);

        if ($attendance->employee->admin_id != $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $limitError = $this->checkDateModificationLimit($request->user()->id, $attendance->date, 'mengedit');
        if ($limitError) {
            return response()->json(['message' => $limitError], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:present,absent,late,early_leave,sakit,cuti,izin,dinas',
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:255',
            'shift_id' => 'nullable|integer|exists:hris_shifts,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = [];
        if ($request->status) $updateData['status'] = $request->status;
        if ($request->has('notes')) $updateData['notes'] = $request->notes;
        if ($request->has('shift_id')) $updateData['shift_id'] = $request->shift_id;

        if ($request->check_in) {
            $updateData['check_in'] = Carbon::parse(Carbon::parse($attendance->date)->format('Y-m-d') . ' ' . $request->check_in);
        }
        if ($request->check_out) {
            $updateData['check_out'] = Carbon::parse(Carbon::parse($attendance->date)->format('Y-m-d') . ' ' . $request->check_out);
        }

        $attendance->update($updateData);

        return response()->json($attendance->load(['employee.user', 'shift']));
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $attendance = Attendance::findOrFail($id);
        if ($attendance->employee->admin_id != $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $limitError = $this->checkDateModificationLimit($request->user()->id, $attendance->date, 'menghapus');
        if ($limitError) {
            return response()->json(['message' => $limitError], 403);
        }

        $attendance->delete();

        return response()->json(['message' => 'Absensi berhasil dihapus']);
    }

    public function storeAdmin(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'date' => 'required|date_format:Y-m-d',
            'status' => 'required|in:present,absent,late,early_leave,sakit,cuti,izin,dinas',
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:255',
            'shift_id' => 'nullable|integer|exists:hris_shifts,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adminId = $request->user()->id;
        $employee = Employee::where('id', $request->employee_id)
            ->where('admin_id', $adminId)
            ->first();

        if (!$employee) {
            return response()->json(['message' => 'Employee not found or does not belong to your tenant'], 404);
        }

        $limitError = $this->checkDateModificationLimit($adminId, $request->date, 'menambah');
        if ($limitError) {
            return response()->json(['message' => $limitError], 403);
        }

        $existingAttendance = Attendance::where('employee_id', $request->employee_id)
            ->where('date', $request->date)
            ->first();

        if ($existingAttendance) {
            return response()->json([
                'message' => 'Data absensi untuk tanggal ini sudah ada',
                'date' => $request->date,
                'existing_id' => $existingAttendance->id,
            ], 409);
        }

        $attendanceData = [
            'admin_id' => $adminId,
            'employee_id' => $request->employee_id,
            'date' => $request->date,
            'status' => $request->status,
            'notes' => $request->notes,
            'shift_id' => $request->shift_id,
        ];

        if ($request->check_in) {
            $attendanceData['check_in'] = Carbon::parse($request->date . ' ' . $request->check_in);
        }
        if ($request->check_out) {
            $attendanceData['check_out'] = Carbon::parse($request->date . ' ' . $request->check_out);
        }

        $attendance = Attendance::create($attendanceData);

        return response()->json([
            'message' => 'Data absensi berhasil dibuat',
            'data' => $attendance->load(['employee.user', 'shift'])
        ], 201);
    }

    public function checkLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $isInOffice = $this->geofenceService->isInsideGeofence(
            $request->latitude, 
            $request->longitude, 
            $employee->admin_id
        );

        return response()->json([
            'is_in_office' => $isInOffice,
            'message' => $isInOffice ? 'Anda berada di dalam area kantor' : 'Anda berada di luar area kantor'
        ]);
    }

    public function getSettings(Request $request)
    {
        $adminId = $request->user()->isAdmin() ? $request->user()->id : ($request->user()->employee ? $request->user()->employee->admin_id : null);
        if (!$adminId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $setting = HrisAttendanceSetting::firstOrCreate(
            ['tenant_id' => $adminId],
            [
                'is_shift_enabled' => false,
                'check_in_start' => '06:00',
                'work_start_time' => '08:00',
                'late_tolerance_time' => '08:15',
                'check_in_end' => '09:00',
                'lock_after_late_cutoff' => true,
                'late_cutoff_policy' => 'empty',
                'work_end_time' => '17:00',
                'min_checkout_at_work_end' => true,
                'require_geofence_checkout' => true,
                'edit_delete_limit_days' => 0,
                'allow_admin_bypass' => true,
            ]
        );

        return response()->json($setting);
    }

    public function saveSettings(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'is_shift_enabled' => 'nullable|boolean',
            'check_in_start' => 'nullable|string',
            'work_start_time' => 'nullable|string',
            'late_tolerance_time' => 'nullable|string',
            'check_in_end' => 'nullable|string',
            'lock_after_late_cutoff' => 'nullable|boolean',
            'late_cutoff_policy' => 'nullable|string|in:empty,absent',
            'work_end_time' => 'nullable|string',
            'min_checkout_at_work_end' => 'nullable|boolean',
            'require_geofence_checkout' => 'nullable|boolean',
            'edit_delete_limit_days' => 'nullable|integer|min:0|max:3650',
            'allow_admin_bypass' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $setting = HrisAttendanceSetting::updateOrCreate(
            ['tenant_id' => $request->user()->id],
            [
                'is_shift_enabled' => $request->is_shift_enabled ?? false,
                'check_in_start' => $request->check_in_start ? substr($request->check_in_start, 0, 5) : '06:00',
                'work_start_time' => $request->work_start_time ? substr($request->work_start_time, 0, 5) : '08:00',
                'late_tolerance_time' => $request->late_tolerance_time ? substr($request->late_tolerance_time, 0, 5) : '08:15',
                'check_in_end' => $request->check_in_end ? substr($request->check_in_end, 0, 5) : '09:00',
                'lock_after_late_cutoff' => $request->lock_after_late_cutoff ?? true,
                'late_cutoff_policy' => $request->late_cutoff_policy ?? 'empty',
                'work_end_time' => $request->work_end_time ? substr($request->work_end_time, 0, 5) : '17:00',
                'min_checkout_at_work_end' => $request->min_checkout_at_work_end ?? true,
                'require_geofence_checkout' => $request->require_geofence_checkout ?? true,
                'edit_delete_limit_days' => $request->edit_delete_limit_days ?? 0,
                'allow_admin_bypass' => $request->allow_admin_bypass ?? true,
            ]
        );

        return response()->json([
            'message' => 'Pengaturan kehadiran berhasil disimpan',
            'setting' => $setting,
        ]);
    }

    public function cleanupOldAttendances(Request $request)
    {
        $request->validate([
            'before_date' => 'required|date_format:Y-m-d',
        ]);

        $beforeDate = $request->input('before_date');
        $beforeDateCarbon = Carbon::createFromFormat('Y-m-d', $beforeDate)->startOfDay();

        $deletedCount = Attendance::whereHas('employee', function ($query) use ($request) {
            $query->where('admin_id', $request->user()->id);
        })
            ->where('date', '<', $beforeDateCarbon)
            ->delete();

        return response()->json([
            'message' => 'Data absensi lama berhasil dibersihkan',
            'deleted_count' => $deletedCount,
            'before_date' => $beforeDate
        ]);
    }

    /**
     * Helper to resolve the effective schedule for an employee for today
     */
    private function getEffectiveSchedule(Employee $employee, $dateStr = null)
    {
        $date = $dateStr ?: Carbon::today()->format('Y-m-d');
        $adminId = $employee->admin_id;

        $setting = HrisAttendanceSetting::firstOrCreate(
            ['tenant_id' => $adminId],
            [
                'is_shift_enabled' => false,
                'check_in_start' => '06:00',
                'work_start_time' => '08:00',
                'late_tolerance_time' => '08:15',
                'check_in_end' => '09:00',
                'lock_after_late_cutoff' => true,
                'late_cutoff_policy' => 'empty',
                'work_end_time' => '17:00',
                'min_checkout_at_work_end' => true,
                'require_geofence_checkout' => true,
            ]
        );

        // If Shift is enabled, check employee assignment
        if ($setting->is_shift_enabled) {
            $assignment = HrisShiftAssignment::where('employee_id', $employee->id)
                ->where('date', $date)
                ->with('shift')
                ->first();

            if ($assignment && $assignment->shift) {
                $shift = $assignment->shift;
                return [
                    'is_shift' => true,
                    'is_day_off' => false,
                    'shift_id' => $shift->id,
                    'shift_name' => $shift->name,
                    'shift_code' => $shift->code,
                    'color' => $shift->color ?: '#3b82f6',
                    'check_in_start' => $shift->check_in_start,
                    'work_start_time' => $shift->work_start_time,
                    'late_tolerance_time' => $shift->late_tolerance_time,
                    'check_in_end' => $shift->check_in_end,
                    'work_end_time' => $shift->work_end_time,
                    'is_night_shift' => $shift->is_night_shift,
                    'lock_after_late_cutoff' => $setting->lock_after_late_cutoff,
                    'min_checkout_at_work_end' => $setting->min_checkout_at_work_end,
                    'require_geofence_checkout' => $setting->require_geofence_checkout,
                ];
            }

            // When shift is enabled but NO shift is assigned for this date -> It is DAY OFF (Libur Kerja)
            return [
                'is_shift' => true,
                'is_day_off' => true,
                'shift_id' => null,
                'shift_name' => 'Libur Kerja',
                'shift_code' => 'OFF',
                'color' => '#94a3b8',
                'check_in_start' => null,
                'work_start_time' => null,
                'late_tolerance_time' => null,
                'check_in_end' => null,
                'work_end_time' => null,
                'is_night_shift' => false,
                'lock_after_late_cutoff' => false,
                'min_checkout_at_work_end' => false,
                'require_geofence_checkout' => $setting->require_geofence_checkout ?? true,
            ];
        }

        // Regular (Non-shift) Schedule
        return [
            'is_shift' => false,
            'is_day_off' => false,
            'shift_id' => null,
            'shift_name' => 'Reguler (Non-Shift)',
            'shift_code' => 'REG',
            'color' => '#3b82f6',
            'check_in_start' => $setting->check_in_start ?: '06:00',
            'work_start_time' => $setting->work_start_time ?: '08:00',
            'late_tolerance_time' => $setting->late_tolerance_time ?: '08:15',
            'check_in_end' => $setting->check_in_end ?: '09:00',
            'work_end_time' => $setting->work_end_time ?: '17:00',
            'is_night_shift' => false,
            'lock_after_late_cutoff' => $setting->lock_after_late_cutoff ?? true,
            'min_checkout_at_work_end' => $setting->min_checkout_at_work_end ?? true,
            'require_geofence_checkout' => $setting->require_geofence_checkout ?? true,
        ];
    }
}
