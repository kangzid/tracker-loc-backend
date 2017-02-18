<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Geofence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->isAdmin()) {
            // Admin can see all attendances
            $attendances = Attendance::with('employee.user')
                ->orderBy('date', 'desc')
                ->paginate(20);
        } else {
            // Employee can only see their own attendances
            $employee = $user->employee;
            if (!$employee) {
                return response()->json(['message' => 'Employee profile not found'], 404);
            }

            $attendances = Attendance::where('employee_id', $employee->id)
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

        $today = Carbon::today();
        $attendance = Attendance::firstOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            ['status' => 'present']
        );

        // Check geofencing
        $isInOffice = $this->checkGeofencing($request->latitude, $request->longitude);

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
        }

        return response()->json($attendance);
    }

    public function show($id)
    {
        $attendance = Attendance::with('employee.user')->findOrFail($id);
        return response()->json($attendance);
    }

    public function todayAttendance(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('date', Carbon::today())
            ->first();

        return response()->json($attendance);
    }

    public function monthlyAttendance(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
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
            $attendance = $attendances->firstWhere('date', $date);
            
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
        
        // Only allow edit within 7 days
        if (Carbon::parse($attendance->date)->diffInDays(Carbon::now()) > 7) {
            return response()->json(['message' => 'Can only edit attendance within 7 days'], 403);
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
            $updateData['check_in'] = Carbon::parse($attendance->date . ' ' . $request->check_in);
        }

        if ($request->check_out) {
            $updateData['check_out'] = Carbon::parse($attendance->date . ' ' . $request->check_out);
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
        
        // Only allow delete within 7 days
        if (Carbon::parse($attendance->date)->diffInDays(Carbon::now()) > 7) {
            return response()->json(['message' => 'Can only delete attendance within 7 days'], 403);
        }

        $attendance->delete();

        return response()->json(['message' => 'Attendance deleted successfully']);
    }

    public function cleanupOldAttendances()
    {
        // Delete attendances older than 3 months
        $threeMonthsAgo = Carbon::now()->subMonths(3);
        
        $deletedCount = Attendance::where('date', '<', $threeMonthsAgo->format('Y-m-d'))->delete();
        
        return response()->json([
            'message' => 'Old attendances cleaned up successfully',
            'deleted_count' => $deletedCount
        ]);
    }

    private function checkGeofencing($latitude, $longitude)
    {
        $geofences = Geofence::where('is_active', true)
            ->where('type', 'office')
            ->get();

        foreach ($geofences as $geofence) {
            if ($geofence->isInsideGeofence($latitude, $longitude)) {
                return true;
            }
        }

        return false;
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