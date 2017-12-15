<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Geofence;
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
            // OPTIMIZATION: Use eager loading to prevent N+1 queries
            $attendances = Attendance::with(['employee' => function($query) {
                    $query->select('id', 'user_id', 'admin_id', 'employee_id', 'department', 'position');
                }, 'employee.user' => function($query) {
                    $query->select('id', 'name', 'email');
                }])
                ->whereHas('employee', function ($q) use ($adminId) {
                    $q->where('admin_id', $adminId);
                })
                ->orderBy('date', 'desc')
                ->paginate(20);
        } else {
            // Employee can only see their own attendances
            $employee = $user->employee;
            if (!$employee) {
                return response()->json(['message' => 'Employee profile not found'], 404);
            }

            // OPTIMIZATION: Use query scope
            $attendances = Attendance::forEmployee($employee->id)
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

        // OPTIMIZATION: Use cached geofence service
        $isInOffice = $this->geofenceService->isInsideGeofence(
            $request->latitude, 
            $request->longitude, 
            $employee->admin_id
        );

        // Return error if outside office area
        if (!$isInOffice) {
            return response()->json([
                'error' => 'OUTSIDE_GEOFENCE',
                'message' => 'Anda berada di luar area kantor. Silakan mendekat ke area kantor untuk melakukan absensi.',
                'title' => 'Lokasi Tidak Valid'
            ], 422);
        }

        $today = Carbon::today();
        $attendance = Attendance::firstOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            [
                'admin_id' => $employee->admin_id,
                'status' => 'present',
            ]
        );

        if ($request->type === 'check_in') {
            if ($attendance->check_in) {
                return response()->json(['message' => 'Already checked in today'], 400);
            }

            // Determine status based on time and location
            $status = $this->determineAttendanceStatus($isInOffice);

            $attendance->update([
                'check_in' => now(),
                'check_in_lat' => $request->latitude,
                'check_in_lng' => $request->longitude,
                'status' => $status
            ]);
            
            // Clear today's attendance cache
            Cache::forget("attendance:today:employee:{$employee->id}:" . Carbon::today()->format('Y-m-d'));
        } else {
            if (!$attendance->check_in) {
                return response()->json(['message' => 'Must check in first'], 400);
            }
            if ($attendance->check_out) {
                return response()->json(['message' => 'Already checked out today'], 400);
            }

            $attendance->update([
                'check_out' => now(),
                'check_out_lat' => $request->latitude,
                'check_out_lng' => $request->longitude,
            ]);
            
            // Clear today's attendance cache
            Cache::forget("attendance:today:employee:{$employee->id}:" . Carbon::today()->format('Y-m-d'));
        }

        return response()->json($attendance);
    }

    public function show($id)
    {
        $user = auth()->user();
        $attendance = Attendance::with('employee.user')->findOrFail($id);

        // Check authorization
        if ($user->isAdmin()) {
            // Admin can only view attendances of their employees
            if ($attendance->employee->admin_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } else {
            // Employee can only view their own attendance
            if (!$user->employee || $attendance->employee_id !== $user->employee->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json($attendance);
    }

    public function todayAttendance(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        // OPTIMIZATION: Cache today's attendance for 5 minutes
        $cacheKey = "attendance:today:employee:{$employee->id}:" . Carbon::today()->format('Y-m-d');
        
        $attendance = Cache::remember($cacheKey, 300, function () use ($employee) {
            return Attendance::forEmployee($employee->id)
                ->today()
                ->first();
        });

        return response()->json($attendance);
    }

    public function monthlyAttendance(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        // OPTIMIZATION: Use query scope
        $attendances = Attendance::forEmployee($employee->id)
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

        // Verify employee belongs to this admin's tenant
        $adminId = $request->user()->id;
        $employee = \App\Models\Employee::where('id', $employeeId)
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

        $month = $request->month ?? Carbon::now()->month;
        $year = $request->year ?? Carbon::now()->year;

        // Get all days in the month
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $startDate = Carbon::create($year, $month, 1);
        $endDate = Carbon::create($year, $month, $daysInMonth);

        $attendances = Attendance::with('employee.user')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('date', 'asc')
            ->get();

        // Create array with all days of the month
        $result = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day)->format('Y-m-d');
            // Find attendance by comparing formatted dates (handle timezone properly)
            $attendance = $attendances->first(function ($item) use ($date) {
                return $item->date->format('Y-m-d') === $date;
            });

            $result[] = [
                'date' => $date,
                'day_name' => Carbon::create($year, $month, $day)->format('l'),
                'attendance' => $attendance,
            ];
        }

        return response()->json([
            'month' => $month,
            'year' => $year,
            'days_in_month' => $daysInMonth,
            'attendances' => $result
        ]);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $attendance = Attendance::findOrFail($id);

        // Verify admin owns this attendance (via employee ownership)
        if ($attendance->employee->admin_id != $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Configurable limit per tenant (0 = unlimited / unrestricted)
        $setting = \App\Models\HrisAttendanceSetting::where('tenant_id', $request->user()->id)->first();
        $limitDays = $setting ? $setting->edit_delete_limit_days : 0;
        if ($limitDays > 0 && Carbon::parse($attendance->date)->diffInDays(Carbon::now()) > $limitDays) {
            return response()->json(['message' => "Can only edit attendance within {$limitDays} days"], 403);
        }

        $validator = Validator::make($request->all(), [
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:present,late,absent,sick,leave',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = [
            'status' => $request->status,
            'notes' => $request->notes,
        ];

        if ($request->check_in) {
            $updateData['check_in'] = Carbon::parse(Carbon::parse($attendance->date)->format('Y-m-d') . ' ' . $request->check_in);
        }

        if ($request->check_out) {
            $updateData['check_out'] = Carbon::parse(Carbon::parse($attendance->date)->format('Y-m-d') . ' ' . $request->check_out);
        }

        $attendance->update($updateData);

        return response()->json($attendance->load('employee.user'));
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $attendance = Attendance::findOrFail($id);

        // Verify admin owns this attendance (via employee ownership)
        if ($attendance->employee->admin_id != $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Configurable limit per tenant (0 = unlimited / unrestricted)
        $setting = \App\Models\HrisAttendanceSetting::where('tenant_id', $request->user()->id)->first();
        $limitDays = $setting ? $setting->edit_delete_limit_days : 0;
        if ($limitDays > 0 && Carbon::parse($attendance->date)->diffInDays(Carbon::now()) > $limitDays) {
            return response()->json(['message' => "Can only delete attendance within {$limitDays} days"], 403);
        }

        $attendance->delete();

        return response()->json(['message' => 'Attendance deleted successfully']);
    }

    /**
     * Admin manual attendance creation for gap-filling
     * Used when employee forgot to check-in or for manual corrections
     */
    public function storeAdmin(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'date' => 'required|date_format:Y-m-d',
            'status' => 'required|in:present,absent,late,early_leave',
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify employee belongs to this admin's tenant
        $adminId = $request->user()->id;
        $employee = \App\Models\Employee::where('id', $request->employee_id)
            ->where('admin_id', $adminId)
            ->first();

        if (!$employee) {
            return response()->json(['message' => 'Employee not found or does not belong to your tenant'], 404);
        }

        // Check if attendance already exists for this date
        $existingAttendance = Attendance::where('employee_id', $request->employee_id)
            ->where('date', $request->date)
            ->first();

        if ($existingAttendance) {
            return response()->json([
                'message' => 'Attendance record already exists for this date',
                'date' => $request->date,
                'existing_id' => $existingAttendance->id,
                'note' => 'Use update endpoint to modify existing record'
            ], 409);
        }

        $attendanceData = [
            'admin_id' => $adminId,
            'employee_id' => $request->employee_id,
            'date' => $request->date,
            'status' => $request->status,
            'notes' => $request->notes,
        ];

        // Add check-in if provided
        if ($request->check_in) {
            $attendanceData['check_in'] = Carbon::parse($request->date . ' ' . $request->check_in);
        }

        // Add check-out if provided
        if ($request->check_out) {
            $attendanceData['check_out'] = Carbon::parse($request->date . ' ' . $request->check_out);
        }

        $attendance = Attendance::create($attendanceData);

        return response()->json([
            'message' => 'Attendance record created successfully',
            'data' => $attendance->load('employee.user')
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

        // OPTIMIZATION: Use cached geofence service
        $isInOffice = $this->geofenceService->isInsideGeofence(
            $request->latitude, 
            $request->longitude, 
            $employee->admin_id
        );

        return response()->json([
            'is_in_office' => $isInOffice,
            'message' => $isInOffice ? 'Anda berada di area kantor' : 'Anda berada di luar area kantor'
        ]);
    }

    
    public function getSettings(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $setting = \App\Models\HrisAttendanceSetting::firstOrCreate(
            ['tenant_id' => $request->user()->id],
            ['edit_delete_limit_days' => 0, 'allow_admin_bypass' => true]
        );

        return response()->json($setting);
    }

    public function saveSettings(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'edit_delete_limit_days' => 'required|integer|min:0|max:3650',
            'allow_admin_bypass' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $setting = \App\Models\HrisAttendanceSetting::updateOrCreate(
            ['tenant_id' => $request->user()->id],
            [
                'edit_delete_limit_days' => $request->edit_delete_limit_days,
                'allow_admin_bypass' => $request->allow_admin_bypass ?? true,
            ]
        );

        return response()->json($setting);
    }

    public function cleanupOldAttendances(Request $request)
    {
        $request->validate([
            'before_date' => 'required|date_format:Y-m-d',
        ]);

        $beforeDate = $request->input('before_date');

        // Convert string to Carbon date for proper comparison
        $beforeDateCarbon = Carbon::createFromFormat('Y-m-d', $beforeDate)->startOfDay();

        // Delete attendances before the specified date, only for this admin's tenant
        // Using whereHas to filter by employee's admin_id (tenant isolation)
        $deletedCount = Attendance::whereHas('employee', function ($query) use ($request) {
            $query->where('admin_id', $request->user()->id);
        })
            ->where('date', '<', $beforeDateCarbon)
            ->delete();

        return response()->json([
            'message' => 'Old attendances cleaned up successfully',
            'deleted_count' => $deletedCount,
            'before_date' => $beforeDate
        ]);
    }



    private function determineAttendanceStatus($isInOffice)
    {
        $now = Carbon::now();
        $workStartTime = Carbon::today()->setTime(8, 0); // 08:00
        $lateThreshold = Carbon::today()->setTime(8, 30); // 08:30

        // If not in office geofence, mark as absent
        if (!$isInOffice) {
            return 'absent';
        }

        // If check-in after late threshold, mark as late
        if ($now->gt($lateThreshold)) {
            return 'late';
        }

        // Otherwise, mark as present
        return 'present';
    }
}
