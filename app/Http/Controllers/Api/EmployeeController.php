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

        $adminId = $request->user()->id;
        $employees = Employee::with('user')
            ->where('admin_id', $adminId)
            ->get();
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
                'admin_id' => $request->user()->id,
            ]);

            $employee = null;
            if ($request->role === 'employee') {
                $employee = Employee::create([
                    'admin_id' => $request->user()->id,
                    'user_id' => $user->id,
                    'employee_id' => $request->employee_id,
                    'phone' => $request->phone,
                    'address' => $request->address,
                    'department' => $request->department,
                    'position' => $request->position,
                    'photo_base64' => $request->photo_base64,
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

        $adminId = $request->user()->id;
        $employee = Employee::with('user')
            ->where('admin_id', $adminId)
            ->find($id);
        
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

        $adminId = $request->user()->id;
        $employee = Employee::where('admin_id', $adminId)->find($id);
        
        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $employee->user_id,
            'password' => 'sometimes|string|min:8',
            'employee_id' => 'sometimes|unique:employees,employee_id,' . $id,
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'department' => 'nullable|string',
            'position' => 'nullable|string',
        ]);

        // Update user data if provided
        if ($request->has('name') || $request->has('email') || $request->has('password')) {
            $userData = array_filter([
                'name' => $request->name,
                'email' => $request->email,
            ]);
            
            // Allow admin to force update password if provided
            if ($request->has('password')) {
                $userData['password'] = Hash::make($request->password);
            }

            $employee->user->update($userData);
        }

        // Update employee data
        $employee->update(array_filter([
            'employee_id' => $request->employee_id,
            'phone' => $request->phone,
            'address' => $request->address,
            'department' => $request->department,
            'position' => $request->position,
            'photo_base64' => $request->photo_base64,
        ]));

        return response()->json($employee->load('user'));
    }

    
            public function getComprehensiveProfile(Request $request, $id)
    {
        $adminId = $request->user()->isAdmin() ? $request->user()->id : ($request->user()->admin_id ?? $request->user()->id);
        
        $employee = Employee::with('user')
            ->where('admin_id', $adminId)
            ->where(function($q) use ($id) {
                $q->where('id', $id)
                  ->orWhere('employee_id', $id)
                  ->orWhere('user_id', $id);
            })
            ->first();

        if (!$employee) {
            $employee = Employee::with('user')
                ->where(function($q) use ($id) {
                    $q->where('id', $id)
                      ->orWhere('employee_id', $id)
                      ->orWhere('user_id', $id);
                })
                ->first();
        }

        if (!$employee) {
            return response()->json(['message' => 'Data karyawan tidak ditemukan.'], 404);
        }

        $tenantId = $employee->admin_id;

        // 1. Documents
        $documents = \App\Models\HrisDocument::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->get();

        // 2. Performance Reviews
        $performanceReviews = \App\Models\HrisPerformanceReview::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->orderBy('created_at', 'desc')->get();

        // 3. Assigned Assets
        $assets = \App\Models\HrisAsset::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->get();

        // 4. Loans / Kasbon
        $loans = \App\Models\HrisLoan::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->orderBy('created_at', 'desc')->get();

        // 5. Overtime Requests
        $overtimes = \App\Models\HrisOvertime::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->orderBy('date', 'desc')->take(10)->get();

        // 6. Reimbursement Claims
        $claims = \App\Models\HrisClaim::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->orderBy('claim_date', 'desc')->take(10)->get();

        // 7. Violations & SP
        $violations = \App\Models\HrisViolation::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->orderBy('violation_date', 'desc')->get();

        // 8. Training & Certifications
        $trainings = \App\Models\HrisTrainingParticipant::with('training')->where('employee_id', $employee->id)->get();

        // 9. Compliance Items
        $complianceItems = \App\Models\HrisComplianceItem::where('tenant_id', $tenantId)->where('target_type', 'employee')->where('target_id', $employee->id)->get();

        // 10. Pengajuan Cuti / Izin
        $requests = \App\Models\HrisRequest::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->orderBy('created_at', 'desc')->take(10)->get();

        
        // 11. Contracts & Active Contract Data
        $contracts = \App\Models\HrisContract::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->orderBy('created_at', 'desc')->get();
        $activeContract = $contracts->firstWhere('status', 'active') ?: $contracts->first();

        // 12. Salary, Allowances & Bank Data
        $salary = \App\Models\HrisEmployeeSalary::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->first();
        if (!$salary && $activeContract) {
            $salary = [
                'amount' => $activeContract->basic_salary,
                'bank_name' => $activeContract->bank_name,
                'bank_account_number' => $activeContract->bank_account_number,
                'bank_account_holder' => $activeContract->bank_account_holder ?: ($employee->user ? $employee->user->name : null),
                'status' => 'active'
            ];
        }

        $allowances = \App\Models\HrisEmployeeAllowance::with('items.allowanceType')->where('tenant_id', $tenantId)->where('employee_id', $employee->id)->first();
        $contractAllowances = $activeContract ? ($activeContract->allowances_json ?: []) : [];

        $bpjs = \App\Models\HrisEmployeeBpjs::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->first();
        $mutations = \App\Models\HrisMutation::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->orderBy('effective_date', 'desc')->get();

        return response()->json([
            'employee' => $employee,
            'documents' => $documents,
            'performance_reviews' => $performanceReviews,
            'assets' => $assets,
            'loans' => $loans,
            'overtimes' => $overtimes,
            'claims' => $claims,
            'violations' => $violations,
            'trainings' => $trainings,
            'compliance_items' => $complianceItems,
            'requests' => $requests,
            'salary' => $salary,
            'allowances' => $allowances,
            'contract_allowances' => $contractAllowances,
            'bpjs' => $bpjs,
            'contracts' => $contracts,
            'mutations' => $mutations,
        ]);

    }

public function destroy(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $adminId = $request->user()->id;
        $employee = Employee::where('admin_id', $adminId)->find($id);
        
        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $user = $employee->user;
        $employee->delete();
        $user->delete();

        return response()->json(['message' => 'Employee deleted successfully']);
    }
}