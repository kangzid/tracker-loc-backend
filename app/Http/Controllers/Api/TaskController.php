<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->isAdmin()) {
            $tasks = Task::with(['employee.user', 'assignedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        } else {
            $employee = $user->employee;
            if (!$employee) {
                return response()->json(['message' => 'Employee profile not found'], 404);
            }
            
            $tasks = Task::with(['assignedBy'])
                ->where('assigned_to', $employee->id)
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

        $task = Task::create([
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

    public function myTasks(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $status = $request->query('status');
        $priority = $request->query('priority');

        $query = Task::with(['assignedBy'])
            ->where('assigned_to', $employee->id);

        if ($status) {
            $query->where('status', $status);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        $tasks = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($tasks);
    }

    public function acceptTask(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $employee = $request->user()->employee;

        if (!$employee || $task->assigned_to !== $employee->id) {
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

        if (!$employee || $task->assigned_to !== $employee->id) {
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
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $task = Task::findOrFail($id);
        $employee = $request->user()->employee;

        if (!$employee || $task->assigned_to !== $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($task->status === 'completed') {
            return response()->json(['message' => 'Task already completed'], 400);
        }

        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completion_notes' => $request->completion_notes,
        ]);

        return response()->json($task);
    }
}