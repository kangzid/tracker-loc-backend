<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $employees = Employee::with('user')->get();
        return response()->json($employees);
    }

    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'password' => 'required|min:8',
                'role' => 'required|in:admin,employee',
                'employee_id' => 'required_if:role,employee|unique:employees',
                'phone' => 'nullable|string',
                'address' => 'nullable|string',
                'department' => 'nullable|string',
                'position' => 'nullable|string',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
            ]);

            $employee = null;
            if ($request->role === 'employee') {
                $employee = Employee::create([
                    'user_id' => $user->id,
                    'employee_id' => $request->employee_id,
                    'phone' => $request->phone,
                    'address' => $request->address,
                    'department' => $request->department,
                    'position' => $request->position,
                ]);
            }

            return response()->json([
                'user' => $user,
                'employee' => $employee
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $employee = Employee::with('user')->find($id);
        
        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        return response()->json($employee);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $employee = Employee::find($id);
        
        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $employee->user_id,
            'employee_id' => 'sometimes|unique:employees,employee_id,' . $id,
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'department' => 'nullable|string',
            'position' => 'nullable|string',
        ]);

        // Update user data if provided
        if ($request->has('name') || $request->has('email')) {
            $employee->user->update(array_filter([
                'name' => $request->name,
                'email' => $request->email,
            ]));
        }

        // Update employee data
        $employee->update(array_filter([
            'employee_id' => $request->employee_id,
            'phone' => $request->phone,
            'address' => $request->address,
            'department' => $request->department,
            'position' => $request->position,
        ]));

        return response()->json($employee->load('user'));
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $employee = Employee::find($id);
        
        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $user = $employee->user;
        $employee->delete();
        $user->delete();

        return response()->json(['message' => 'Employee deleted successfully']);
    }
}