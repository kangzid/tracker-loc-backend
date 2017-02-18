<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\GeofenceController;
use App\Http\Controllers\Api\UserController;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/shared-location/{token}', [LocationController::class, 'getSharedLocation']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // Attendance routes
    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::post('/attendances', [AttendanceController::class, 'store']);
    Route::get('/attendances/today', [AttendanceController::class, 'todayAttendance']);
    Route::get('/attendances/monthly', [AttendanceController::class, 'monthlyAttendance']);
    Route::get('/attendances/{id}', [AttendanceController::class, 'show']);
    
    // Admin attendance management
    Route::get('/admin/attendances/employee/{employeeId}', [AttendanceController::class, 'getEmployeeAttendances']);
    Route::put('/admin/attendances/{id}', [AttendanceController::class, 'update']);
    Route::delete('/admin/attendances/{id}', [AttendanceController::class, 'destroy']);
    Route::post('/admin/attendances/cleanup', [AttendanceController::class, 'cleanupOldAttendances']);

    // Location tracking routes
    Route::post('/locations', [LocationController::class, 'store']);
    Route::get('/locations/live', [LocationController::class, 'liveTracking']);
    Route::get('/locations/employee/{employeeId}/history', [LocationController::class, 'employeeHistory']);
    Route::get('/locations/vehicle/{vehicleId}/history', [LocationController::class, 'vehicleHistory']);
    Route::post('/locations/share', [LocationController::class, 'shareLocation']);

    // Task routes
    Route::apiResource('tasks', TaskController::class);
    Route::get('/my-tasks', [TaskController::class, 'myTasks']);
    Route::post('/tasks/{id}/accept', [TaskController::class, 'acceptTask']);
    Route::post('/tasks/{id}/start', [TaskController::class, 'startTask']);
    Route::post('/tasks/{id}/complete', [TaskController::class, 'completeTask']);

    // Geofence routes (Admin only)
    Route::apiResource('geofences', GeofenceController::class);

    // User routes (Admin only)
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/admins', [UserController::class, 'admins']);
    Route::get('/users/{id}', [UserController::class, 'show']);

    // Employee routes (Admin only)
    Route::apiResource('employees', EmployeeController::class);

    // Vehicle routes (Admin only)
    Route::apiResource('vehicles', VehicleController::class);
    Route::get('/vehicles-active', [VehicleController::class, 'activeVehicles']);
    Route::get('/vehicles-inactive', [VehicleController::class, 'inactiveVehicles']);
    Route::post('/vehicles/{id}/location', [VehicleController::class, 'updateLocation']);

    // Dashboard/Analytics routes
    Route::get('/dashboard/stats', function (Request $request) {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $stats = [
            'total_employees' => \App\Models\Employee::count(),
            'active_employees' => \App\Models\User::where('role', 'employee')->where('is_active', true)->count(),
            'total_vehicles' => \App\Models\Vehicle::count(),
            'active_vehicles' => \App\Models\Vehicle::where('is_active', true)->count(),
            'today_attendances' => \App\Models\Attendance::whereDate('date', today())->count(),
            'pending_tasks' => \App\Models\Task::where('status', 'pending')->count(),
            'in_progress_tasks' => \App\Models\Task::where('status', 'in_progress')->count(),
        ];

        return response()->json($stats);
    });

    // Employee dashboard
    Route::get('/employee/dashboard', function (Request $request) {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $stats = [
            'today_attendance' => \App\Models\Attendance::where('employee_id', $employee->id)
                ->whereDate('date', today())->first(),
            'pending_tasks' => \App\Models\Task::where('assigned_to', $employee->id)
                ->where('status', 'pending')->count(),
            'in_progress_tasks' => \App\Models\Task::where('assigned_to', $employee->id)
                ->where('status', 'in_progress')->count(),
            'completed_tasks_this_month' => \App\Models\Task::where('assigned_to', $employee->id)
                ->where('status', 'completed')
                ->whereMonth('completed_at', now()->month)
                ->count(),
        ];

        return response()->json($stats);
    });
});

// Fallback route
Route::fallback(function () {
    return response()->json(['message' => 'API endpoint not found'], 404);
});