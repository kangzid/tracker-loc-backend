<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Geofence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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