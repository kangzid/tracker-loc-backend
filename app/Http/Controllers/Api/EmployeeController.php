<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\EncryptedStorageService;
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

                        // Handle photo upload or removal with storage deletion
        if ($request->has('photo_base64')) {
            $oldPath = $employee->photo_path ?: ($employee->user ? $employee->user->photo_path : null);
            if (empty($request->photo_base64)) {
                if ($oldPath) {
                    EncryptedStorageService::deleteFile($oldPath);
                }
                $employee->photo_path = null;
                if ($employee->user) {
                    $employee->user->photo_path = null;
                    $employee->user->save();
                }
            } elseif (str_starts_with($request->photo_base64, 'data:image')) {
                if ($oldPath) {
                    EncryptedStorageService::deleteFile($oldPath);
                }
                $stored = EncryptedStorageService::storeEncrypted(
                    $request->photo_base64,
                    $adminId,
                    'avatars',
                    'avatar_' . ($employee->employee_id ?: $employee->id)
                );
                $employee->photo_path = $stored['path'];
                if ($employee->user) {
                    $employee->user->photo_path = $stored['path'];
                    $employee->user->save();
                }
            }
        }

        // Update user data if provided
        if ($request->has('name') || $request->has('email') || $request->has('password')) {
            $userData = array_filter([
                'name' => $request->name,
                'email' => $request->email,
            ]);
            
            if ($request->has('password') && !empty($request->password)) {
                $userData['password'] = Hash::make($request->password);
            }

            $employee->user->update($userData);
        }

        // Update employee data
        $updateFields = [
            'phone' => $request->phone,
            'address' => $request->address,
            'department' => $request->department,
            'position' => $request->position,
            'photo_path' => $employee->photo_path,
        ];
        if ($request->has('employee_id') && !empty($request->employee_id)) {
            $updateFields['employee_id'] = $request->employee_id;
        }

        $employee->update($updateFields);

        return response()->json($employee->load('user'));
    }

    
                public function getComprehensiveProfile(Request $request, $id)
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();
        $adminId = $isAdmin ? $user->id : ($user->admin_id ?? 2);
        
        $employee = null;
        if ($id === 'me') {
            $employee = $user->employee ? $user->employee->load('user') : null;
        } elseif ($user->role === 'employee' && $user->employee && ($user->employee->id == $id || $user->employee->employee_id == $id)) {
            $employee = $user->employee->load('user');
        }

        // Primary Lookup: Exact Primary Key (id) if numeric, or exact Code (employee_id) if string
        if (!$employee) {
            if (is_numeric($id)) {
                $employee = Employee::with('user')
                    ->where('admin_id', $adminId)
                    ->where('id', (int)$id)
                    ->first();
            } else {
                $employee = Employee::with('user')
                    ->where('admin_id', $adminId)
                    ->where('employee_id', $id)
                    ->first();
            }
        }

        // Fallback without adminId scoping if not found
        if (!$employee) {
            if (is_numeric($id)) {
                $employee = Employee::with('user')->where('id', (int)$id)->first();
            } else {
                $employee = Employee::with('user')->where('employee_id', $id)->first();
            }
        }

        // Last fallback for employee user_id if still not found
        if (!$employee && is_numeric($id)) {
            $employee = Employee::with('user')->where('user_id', (int)$id)->first();
        }

        if (!$employee) {
            return response()->json(['message' => 'Data karyawan tidak ditemukan.'], 404);
        }

        $tenantId = $employee->admin_id ?? $adminId;

        // Photo Base64 resolution
        $photoPath = $employee->photo_path ?: ($employee->user ? $employee->user->photo_path : null);
        if ($photoPath) {
            $employee->photo_base64 = EncryptedStorageService::getBase64($photoPath);
            if ($employee->user) {
                $employee->user->photo_base64 = $employee->photo_base64;
            }
        }

        // 1. Documents (Only for Admin / HRD, excluded for employee privacy)
        $documents = $isAdmin 
            ? \App\Models\HrisDocument::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->get()
            : [];

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

        // 9. Compliance Items (SIM, SKCK, Sertifikasi)
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

        $totalAllowancesAmount = 0;
        if (!empty($contractAllowances)) {
            foreach ($contractAllowances as $ca) {
                $totalAllowancesAmount += (float) ($ca['amount'] ?? 0);
            }
        } elseif ($allowances) {
            $totalAllowancesAmount = (float) ($allowances->total_amount ?? 0);
        }

        $bpjs = \App\Models\HrisEmployeeBpjs::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->first();
        $mutations = \App\Models\HrisMutation::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->orderBy('effective_date', 'desc')->get();
        $resignation = \App\Models\HrisResignation::where('tenant_id', $tenantId)->where('employee_id', $employee->id)->latest()->first();

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
            'total_allowances_amount' => $totalAllowancesAmount,
            'bpjs' => $bpjs,
            'contracts' => $contracts,
            'mutations' => $mutations,
            'resignation' => $resignation,
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
        if ($employee->photo_path) {
            EncryptedStorageService::deleteFile($employee->photo_path);
        }
        if ($user && $user->photo_path && $user->photo_path !== $employee->photo_path) {
            EncryptedStorageService::deleteFile($user->photo_path);
        }
        $employee->delete();
        if ($user) $user->delete();

        return response()->json(['message' => 'Employee deleted successfully']);
    }

            public function photo(Request $request, $id)
    {
        $employee = Employee::with('user')->find($id);

        if (!$employee) {
            return response()->json(['message' => 'Karyawan tidak ditemukan.'], 404);
        }

        $photoPath = $employee->photo_path ?: ($employee->user ? $employee->user->photo_path : null);

        if (!$photoPath) {
            return response()->json(['message' => 'Foto profil tidak ditemukan.'], 404);
        }

        try {
            return EncryptedStorageService::streamResponse($photoPath, 'photo_' . $employee->employee_id, false);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Berkas foto tidak ditemukan di penyimpanan server.'], 404);
        }
    }

}
