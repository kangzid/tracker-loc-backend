<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->isAdmin()) {
            $adminId = $user->id;
            // OPTIMIZATION: Eager load with selected columns only
            $tasks = Task::with([
                    'employee.user:id,name,email', 
                    'assignedBy:id,name,email'
                ])
                ->select('id', 'admin_id', 'title', 'description', 'assigned_to', 'assigned_by', 'status', 'priority', 'due_date', 'created_at', 'updated_at')
                ->forAdmin($adminId)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        } else {
            $employee = $user->employee;
            if (!$employee) {
                return response()->json(['message' => 'Employee profile not found'], 404);
            }
            
            // OPTIMIZATION: Use query scope
            $tasks = Task::with(['assignedBy:id,name,email'])
                ->select('id', 'admin_id', 'title', 'description', 'assigned_to', 'assigned_by', 'status', 'priority', 'due_date', 'created_at', 'updated_at')
                ->forEmployee($employee->id)
                ->where(function ($q) {
                    $q->where('completion_notes', 'NOT LIKE', '%[HIDDEN_BY_EMPLOYEE]%')
                      ->orWhereNull('completion_notes');
                })
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        }

        return response()->json($tasks);
    }

    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'required|exists:employees,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'address' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'due_date' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify employee belongs to this admin
        $adminId = $request->user()->id;
        $employee = Employee::where('admin_id', $adminId)
            ->where('id', $request->assigned_to)
            ->first();
        
        if (!$employee) {
            return response()->json(['message' => 'Employee not found or does not belong to your organization'], 404);
        }

        $task = Task::create([
            'admin_id' => $adminId,
            'title' => $request->title,
            'description' => $request->description,
            'assigned_to' => $request->assigned_to,
            'assigned_by' => $request->user()->id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'priority' => $request->priority,
            'due_date' => $request->due_date,
        ]);

        // Create notification for employee
        $this->createNotification($request->assigned_to, $task);

        return response()->json($task->load(['employee.user', 'assignedBy']), 201);
    }

    public function show($id)
    {
        $task = Task::with(['employee.user', 'assignedBy'])->findOrFail($id);
        return response()->json($task);
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $user = $request->user();

        // Admin can update any task, employee can only update their own tasks
        if (!$user->isAdmin() && (!$user->employee || $task->assigned_to !== $user->employee->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:pending,in_progress,completed,cancelled',
            'priority' => 'sometimes|required|in:low,medium,high,urgent',
            'due_date' => 'nullable|date|after:now',
            'completion_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = $request->only(['title', 'description', 'status', 'priority', 'due_date', 'completion_notes']);

        // Handle status changes
        if ($request->has('status')) {
            if ($request->status === 'in_progress' && !$task->started_at) {
                $updateData['started_at'] = now();
            } elseif ($request->status === 'completed' && !$task->completed_at) {
                $updateData['completed_at'] = now();
            }
        }

        $task->update($updateData);

        return response()->json($task->load(['employee.user', 'assignedBy']));
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $task = Task::findOrFail($id);
        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }

    public function hideByEmployee(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $employee = $request->user()->employee;

        if (!$employee || $task->assigned_to != $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only allow hiding completed or cancelled tasks
        if (!in_array($task->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Hanya tugas yang sudah selesai atau dibatalkan yang bisa disembunyikan'], 400);
        }

        $marker = ' [HIDDEN_BY_EMPLOYEE]';
        
        // Prevent duplicate appending
        if (strpos($task->completion_notes, $marker) === false) {
            $task->update([
                'completion_notes' => ($task->completion_notes ?? '') . $marker
            ]);
        }

        return response()->json(['message' => 'Tugas berhasil disembunyikan']);
    }

    public function myTasks(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $status = $request->query('status');
        $priority = $request->query('priority');

        // OPTIMIZATION: Use query scopes
        $query = Task::with(['assignedBy:id,name,email'])
            ->select('id', 'admin_id', 'title', 'description', 'assigned_to', 'assigned_by', 'status', 'priority', 'due_date', 'created_at', 'updated_at')
            ->forEmployee($employee->id)
            ->where(function ($q) {
                $q->where('completion_notes', 'NOT LIKE', '%[HIDDEN_BY_EMPLOYEE]%')
                  ->orWhereNull('completion_notes');
            });

        if ($status) {
            $query->where('status', $status);
        }

        if ($priority) {
            $query->byPriority($priority);
        }

        $tasks = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($tasks);
    }

    public function acceptTask(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $employee = $request->user()->employee;

        if (!$employee || $task->assigned_to != $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($task->status !== 'pending') {
            return response()->json(['message' => 'Task cannot be accepted'], 400);
        }

        $task->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return response()->json($task);
    }

    public function startTask(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $employee = $request->user()->employee;

        if (!$employee || $task->assigned_to != $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!in_array($task->status, ['pending', 'accepted'])) {
            return response()->json(['message' => 'Task cannot be started'], 400);
        }

        $task->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return response()->json($task);
    }

    public function completeTask(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'completion_notes' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $task = Task::findOrFail($id);
        $employee = $request->user()->employee;

        if (!$employee || $task->assigned_to != $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($task->status === 'completed') {
            return response()->json(['message' => 'Task already completed'], 400);
        }

        $updateData = [
            'status' => 'completed',
            'completed_at' => now(),
            'completion_notes' => $request->completion_notes,
        ];

        if ($request->has('latitude') && $request->has('longitude')) {
            $updateData['latitude'] = $request->latitude;
            $updateData['longitude'] = $request->longitude;
        }

        $task->update($updateData);

        return response()->json($task);
    }

    private function createNotification($employeeId, $task)
    {
        $employee = Employee::find($employeeId);
        \App\Models\Notification::create([
            'admin_id' => $employee->admin_id,
            'employee_id' => $employeeId,
            'title' => 'Tugas Baru Hari Ini',
            'message' => $task->title,
            'type' => 'new_task',
            'data' => json_encode([
                'task_id' => $task->id,
                'priority' => $task->priority,
            ]),
            'is_read' => false,
        ]);
    }
}