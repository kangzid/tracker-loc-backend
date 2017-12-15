<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Task;
use App\Models\Notification;
use Carbon\Carbon;

class EmployeeDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isEmployee()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $employee = $user->employee;
        
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        // Attendance Today
        $attendanceToday = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', Carbon::today())
            ->first();

        // Active Tasks List for Employee Focus Task (SWR/Offline/Focus)
        $activeTasksList = Task::where('assigned_to', $employee->id)
            ->whereIn('status', ['pending', 'accepted', 'in_progress'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Tasks Summary counts
        $tasksSummary = [
            'pending' => Task::where('assigned_to', $employee->id)->where('status', 'pending')->count(),
            'accepted' => Task::where('assigned_to', $employee->id)->where('status', 'accepted')->count(),
            'in_progress' => Task::where('assigned_to', $employee->id)->where('status', 'in_progress')->count(),
            'completed' => Task::where('assigned_to', $employee->id)->where('status', 'completed')->count(),
        ];

        // Unread Notifications
        $unreadNotifications = Notification::where('employee_id', $employee->id)
            ->where('is_read', false)
            ->count();

        // Monthly Attendance
        $monthlyAttendance = Attendance::where('employee_id', $employee->id)
            ->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            // Standard/New response format
            'employee' => $employee->load('user'),
            'attendance_today' => $attendanceToday,
            'tasks_summary' => [
                'pending' => $tasksSummary['pending'],
                'accepted' => $tasksSummary['accepted'],
                'in_progress' => $tasksSummary['in_progress'],
                'completed' => $tasksSummary['completed'],
                'total' => array_sum($tasksSummary),
            ],
            'unread_notifications' => $unreadNotifications,
            'monthly_attendance' => $monthlyAttendance,

            // Legacy/Mobile App expected format compatibility
            'today_attendance' => $attendanceToday,
            'pending_tasks' => $tasksSummary['pending'],
            'accepted_tasks' => $tasksSummary['accepted'],
            'in_progress_tasks' => $tasksSummary['in_progress'],
            'completed_tasks_this_month' => $tasksSummary['completed'],
            'active_tasks_list' => $activeTasksList,
        ]);
    }
}
