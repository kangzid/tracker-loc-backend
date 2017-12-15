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
                    'assignedBy:id,name,email',
                    'vehicle'
                ])
                ->select('id', 'admin_id', 'title', 'description', 'assigned_to', 'assigned_by', 'status', 'priority', 'due_date', 'created_at', 'updated_at', 'task_type', 'vehicle_id', 'origin_lat', 'origin_lng', 'origin_address', 'destination_lat', 'destination_lng', 'destination_address', 'estimated_distance', 'estimated_duration')
                ->forAdmin($adminId)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        } else {
            $employee = $user->employee;
            if (!$employee) {
                return response()->json(['message' => 'Employee profile not found'], 404);
            }
            
            // OPTIMIZATION: Use query scope
            $tasks = Task::with(['assignedBy:id,name,email', 'vehicle'])
                ->select('id', 'admin_id', 'title', 'description', 'assigned_to', 'assigned_by', 'status', 'priority', 'due_date', 'created_at', 'updated_at', 'task_type', 'vehicle_id', 'origin_lat', 'origin_lng', 'origin_address', 'destination_lat', 'destination_lng', 'destination_address', 'estimated_distance', 'estimated_duration')
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
            'task_type' => 'nullable|in:general,dispatch',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'origin_lat' => 'nullable|numeric',
            'origin_lng' => 'nullable|numeric',
            'origin_address' => 'nullable|string',
            'destination_lat' => 'nullable|numeric',
            'destination_lng' => 'nullable|numeric',
            'destination_address' => 'nullable|string',
            'estimated_distance' => 'nullable|numeric',
            'estimated_duration' => 'nullable|integer',
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

        // Ensure 1-to-1 active task per employee and vehicle
        $activeEmployeeTask = Task::where('assigned_to', $request->assigned_to)
            ->where('status', 'in_progress')
            ->first();
        if ($activeEmployeeTask) {
            return response()->json(['message' => 'Karyawan ini sedang menjalankan tugas aktif (In Progress).', 'errors' => ['assigned_to' => ['Karyawan sedang sibuk']]], 422);
        }

        if ($request->vehicle_id) {
            $activeVehicleTask = Task::where('vehicle_id', $request->vehicle_id)
                ->where('status', 'in_progress')
                ->first();
            if ($activeVehicleTask) {
                return response()->json(['message' => 'Kendaraan ini sedang digunakan dalam tugas aktif (In Progress).', 'errors' => ['vehicle_id' => ['Kendaraan sedang digunakan']]], 422);
            }
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
            'task_type' => $request->task_type ?? 'general',
            'vehicle_id' => $request->vehicle_id,
            'origin_lat' => $request->origin_lat,
            'origin_lng' => $request->origin_lng,
            'origin_address' => $request->origin_address,
            'destination_lat' => $request->destination_lat,
            'destination_lng' => $request->destination_lng,
            'destination_address' => $request->destination_address,
            'estimated_distance' => $request->estimated_distance,
            'estimated_duration' => $request->estimated_duration,
        ]);

        // Create notification for employee
        $this->createNotification($request->assigned_to, $task);

        return response()->json($task->load(['employee.user', 'assignedBy']), 201);
    }

    public function show(Request $request, $id)
    {
        $task = Task::with(['employee.user', 'assignedBy', 'vehicle'])->findOrFail($id);
        $user = $request->user();

        // Tenant isolation: Admin can only view tasks from their organization, Employee can only view their own tasks
        if ($user->isAdmin()) {
            if ($task->admin_id != $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } else {
            if (!$user->employee || $task->assigned_to != $user->employee->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json($task);
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $user = $request->user();

        // Tenant isolation: Admin can only update tasks in their tenant, employee can only update their own tasks
        if ($user->isAdmin()) {
            if ($task->admin_id != $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } else {
            if (!$user->employee || $task->assigned_to != $user->employee->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:pending,accepted,in_progress,completed,cancelled',
            'priority' => 'sometimes|required|in:low,medium,high,urgent',
            'due_date' => 'nullable|date|after:now',
            'completion_notes' => 'nullable|string',
            'task_type' => 'nullable|in:general,dispatch',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'origin_lat' => 'nullable|numeric',
            'origin_lng' => 'nullable|numeric',
            'origin_address' => 'nullable|string',
            'destination_lat' => 'nullable|numeric',
            'destination_lng' => 'nullable|numeric',
            'destination_address' => 'nullable|string',
            'estimated_distance' => 'nullable|numeric',
            'estimated_duration' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = $request->only(['title', 'description', 'status', 'priority', 'due_date', 'completion_notes', 'task_type', 'vehicle_id', 'origin_lat', 'origin_lng', 'origin_address', 'destination_lat', 'destination_lng', 'destination_address', 'estimated_distance', 'estimated_duration']);

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
        
        // Tenant isolation: Ensure admin only deletes tasks belonging to their tenant
        if ($task->admin_id != $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

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
        $query = Task::with(['assignedBy:id,name,email', 'vehicle'])
            ->select('id', 'admin_id', 'title', 'description', 'assigned_to', 'assigned_by', 'status', 'priority', 'due_date', 'created_at', 'updated_at', 'task_type', 'vehicle_id', 'origin_lat', 'origin_lng', 'origin_address', 'destination_lat', 'destination_lng', 'destination_address', 'estimated_distance', 'estimated_duration')
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


