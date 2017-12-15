<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisContract;
use App\Models\Employee;
use App\Models\HrisEmployeeSalary;
use App\Models\HrisEmployeeAllowance;
use App\Models\HrisEmployeeAllowanceItem;
use App\Services\EncryptedStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class HrisContractController extends Controller
{

    private function syncContractToPayroll($tenantId, $employeeId, $basicSal, $allowancesList, $startDate, $contractNumber)
    {
        // 1. Sync Gaji Pokok (HrisEmployeeSalary)
        if ((float)$basicSal > 0) {
            try {
                $salaryCode = 'SAL-' . date('Ym') . '-' . str_pad($employeeId, 4, '0', STR_PAD_LEFT);
                HrisEmployeeSalary::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'employee_id' => $employeeId
                    ],
                    [
                        'code' => $salaryCode,
                        'wage_type' => 'Bulanan',
                        'amount' => (float)$basicSal,
                        'effective_date' => $startDate ?? date('Y-m-d'),
                    ]
                );
            } catch (\Exception $e) {
                \Log::warning('HrisEmployeeSalary sync warning: ' . $e->getMessage());
            }
        }

        // 2. Sync Tunjangan (HrisEmployeeAllowance & Items)
        if (!empty($allowancesList)) {
            if (is_string($allowancesList)) {
                $allowancesList = json_decode($allowancesList, true) ?: [];
            }
            if (is_array($allowancesList) && count($allowancesList) > 0) {
                try {
                    $allowanceCode = 'ALW-' . date('Ym') . '-' . str_pad($employeeId, 4, '0', STR_PAD_LEFT);
                    $empAllowance = HrisEmployeeAllowance::updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'employee_id' => $employeeId
                        ],
                        [
                            'code' => $allowanceCode,
                            'effective_date' => $startDate ?? date('Y-m-d'),
                        ]
                    );

                    $empAllowance->items()->delete();
                    foreach ($allowancesList as $item) {
                        $typeId = $item['allowance_type_id'] ?? $item['id'] ?? null;
                        $amt = (float)($item['amount'] ?? 0);
                        if ($typeId && $amt > 0) {
                            HrisEmployeeAllowanceItem::create([
                                'employee_allowance_id' => $empAllowance->id,
                                'allowance_type_id' => $typeId,
                                'amount' => $amt,
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('HrisEmployeeAllowance sync warning: ' . $e->getMessage());
                }
            }
        }
    }

    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->role === 'superadmin' ? ($user->tenant_id ?? 1) : ($user->admin_id ?? $user->id);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $today = date('Y-m-d');
        $thirtyDaysAhead = date('Y-m-d', strtotime('+30 days'));

        $totalContracts = HrisContract::where('tenant_id', $tenantId)->count();
        $activeContracts = HrisContract::where('tenant_id', $tenantId)
            ->where(function($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->where('status', '!=', 'terminated')
            ->count();

        $expiringSoon = HrisContract::where('tenant_id', $tenantId)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today, $thirtyDaysAhead])
            ->where('status', '!=', 'terminated')
            ->count();

        $expired = HrisContract::where('tenant_id', $tenantId)
            ->whereNotNull('end_date')
            ->where('end_date', '<', $today)
            ->count();

        $pkwtCount = HrisContract::where('tenant_id', $tenantId)
            ->where('contract_type', 'like', '%PKWT%')
            ->where('contract_type', 'not like', '%PKWTT%')
            ->count();

        $pkwttCount = HrisContract::where('tenant_id', $tenantId)
            ->where('contract_type', 'like', '%PKWTT%')
            ->count();



        return response()->json([
            'status' => 'success',
            'data' => [
                'total_contracts' => $totalContracts,
                'active_contracts' => $activeContracts,
                'expiring_soon' => $expiringSoon,
                'expired_contracts' => $expired,
                'pkwt_contracts' => $pkwtCount,
                'pkwt_count' => $pkwtCount,
                'pkwtt_contracts' => $pkwttCount,
                'pkwtt_count' => $pkwttCount,
            ]
        ]);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $query = HrisContract::with(['employee.user', 'creator'])
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'desc');

        if ($request->has('search') && $request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('contract_number', 'like', "%{$s}%")
                    ->orWhere('document_number', 'like', "%{$s}%")
                    ->orWhere('department', 'like', "%{$s}%")
                    ->orWhere('position', 'like', "%{$s}%")
                    ->orWhereHas('employee.user', function ($uq) use ($s) {
                        $uq->where('name', 'like', "%{$s}%");
                    });
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $today = date('Y-m-d');
            if ($request->status === 'active') {
                $query->where(function ($q) use ($today) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
                })->where('status', '!=', 'terminated');
            } elseif ($request->status === 'expired') {
                $query->where(function ($q) use ($today) {
                    $q->where('status', 'expired')
                        ->orWhere(function ($sub) use ($today) {
                            $sub->whereNotNull('end_date')->where('end_date', '<', $today);
                        });
                });
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->has('contract_type') && $request->contract_type !== 'all') {
            $query->where('contract_type', $request->contract_type);
        }

        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('department') && $request->department !== 'all') {
            $query->where('department', $request->department);
        }

        return response()->json($query->paginate($request->get('per_page', 15)));
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'contract_type' => 'required|string|max:50',
            'contract_date' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'basic_salary' => 'nullable|numeric|min:0',
            'base_salary' => 'nullable|numeric|min:0',
            'document_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_holder' => 'nullable|string|max:100',
            'allowances' => 'nullable|array',
            'allowances_json' => 'nullable|array',
            'notes' => 'nullable|string',
            'document_pdf_file' => 'nullable|file|max:10240',
            'document_pdf_base64' => 'nullable|string',
            'document_pdf_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Auto-generate Unique Contract Number: CTR/YYYYMM/XXXX
        $yearMonth = date('Ym', strtotime($request->contract_date));
        $prefix = "CTR/{$yearMonth}/";

        $maxSeq = 0;
        $latestContracts = HrisContract::where('contract_number', 'like', "{$prefix}%")->get();
        foreach ($latestContracts as $c) {
            $parts = explode('/', $c->contract_number);
            if (isset($parts[2]) && is_numeric($parts[2])) {
                $seq = (int)$parts[2];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $nextSeq = $maxSeq + 1;
        $contractNumber = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        while (HrisContract::where('contract_number', $contractNumber)->exists()) {
            $nextSeq++;
            $contractNumber = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        }

        // Handle allowances formatting
        $allowancesList = $request->allowances ?? $request->allowances_json ?? [];
        $basicSal = $request->basic_salary ?? $request->base_salary ?? 0;

        // Determine Status based on end_date
        $today = date('Y-m-d');
        $status = 'active';
        if ($request->end_date && $request->end_date < $today) {
            $status = 'expired';
        }

        // Handle Encrypted PDF File Storage
        $filePath = null;
        $fileName = $request->document_pdf_name;
        if ($request->hasFile('document_pdf_file')) {
            $stored = EncryptedStorageService::storeEncrypted($request->file('document_pdf_file'), $tenantId, 'contracts', 'contract_' . $request->employee_id);
            $filePath = $stored['path'];
            $fileName = $stored['name'];
        } elseif ($request->filled('document_pdf_base64')) {
            $stored = EncryptedStorageService::storeEncrypted($request->document_pdf_base64, $tenantId, 'contracts', 'contract_' . $request->employee_id, $fileName ?: 'kontrak.pdf');
            $filePath = $stored['path'];
            $fileName = $stored['name'];
        }

        $contract = HrisContract::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'created_by' => $request->user()->id,
            'contract_number' => $contractNumber,
            'document_number' => $request->document_number,
            'contract_type' => $request->contract_type,
            'contract_date' => $request->contract_date,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'department' => $request->department,
            'position' => $request->position,
            'basic_salary' => $basicSal,
            'allowances_json' => $allowancesList,
            'bank_name' => $request->bank_name,
            'bank_account_number' => $request->bank_account_number,
            'bank_account_holder' => $request->bank_account_holder,
            'document_pdf_path' => $filePath,
            'document_pdf_name' => $fileName,
            'status' => $status,
            'notes' => $request->notes,
        ]);

        // Auto Sync to Employee Profile
        $emp = Employee::find($request->employee_id);
        if ($emp) {
            if ($request->department) $emp->department = $request->department;
            if ($request->position) $emp->position = $request->position;
            
            if ($status === 'expired') {
                $emp->is_active = false;
                $emp->exit_date = $request->end_date;
            } else {
                $emp->is_active = true;
                $emp->exit_date = null;
            }
            $emp->save();
        }

        // Auto Sync to Payroll (Gaji Pokok & Tunjangan)
        $this->syncContractToPayroll($tenantId, $request->employee_id, $basicSal, $allowancesList, $request->start_date, $contractNumber);

        return response()->json([
            'status' => 'success',
            'message' => 'Kontrak kerja berhasil diterbitkan.',
            'data' => $contract->load('employee.user')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $contract = HrisContract::with(['employee.user', 'creator'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        return response()->json($contract);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $contract = HrisContract::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'contract_date' => 'sometimes|date',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date',
            'contract_type' => 'sometimes|string',
            'department' => 'nullable|string',
            'position' => 'nullable|string',
            'basic_salary' => 'nullable|numeric',
            'base_salary' => 'nullable|numeric',
            'document_number' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'bank_account_holder' => 'nullable|string',
            'allowances_json' => 'nullable|array',
            'allowances' => 'nullable|array',
            'status' => 'sometimes|in:active,expired,terminated,renewed',
            'notes' => 'nullable|string',
            'document_pdf_file' => 'nullable|file|max:10240',
            'document_pdf_base64' => 'nullable|string',
            'document_pdf_name' => 'nullable|string',
        ]);

        if ($request->hasFile('document_pdf_file')) {
            $stored = EncryptedStorageService::storeEncrypted($request->file('document_pdf_file'), $tenantId, 'contracts', 'contract_' . $contract->employee_id);
            $contract->document_pdf_path = $stored['path'];
            $contract->document_pdf_name = $stored['name'];
        } elseif ($request->filled('document_pdf_base64')) {
            $stored = EncryptedStorageService::storeEncrypted($request->document_pdf_base64, $tenantId, 'contracts', 'contract_' . $contract->employee_id, $request->document_pdf_name ?: 'kontrak.pdf');
            $contract->document_pdf_path = $stored['path'];
            $contract->document_pdf_name = $stored['name'];
        }

        $today = date('Y-m-d');
        $endDate = $request->has('end_date') ? ($request->end_date ?: null) : $contract->end_date;
        $status = $contract->status;
        if ($endDate && $endDate < $today) {
            $status = 'expired';
        } elseif (!$endDate || $endDate >= $today) {
            $status = 'active';
        }

        $basicSal = $request->basic_salary ?? $request->base_salary ?? ($request->has('basic_salary') ? ($request->basic_salary ?: 0) : $contract->basic_salary);
        $allowancesList = $request->allowances_json ?? $request->allowances ?? $contract->allowances_json;

        $contract->update([
            'contract_date' => $request->contract_date ?: $contract->contract_date,
            'start_date' => $request->start_date ?: $contract->start_date,
            'end_date' => $endDate,
            'contract_type' => $request->contract_type ?: $contract->contract_type,
            'department' => $request->department ?: $contract->department,
            'position' => $request->position ?: $contract->position,
            'basic_salary' => $basicSal,
            'allowances_json' => $allowancesList,
            'bank_name' => $request->has('bank_name') ? $request->bank_name : $contract->bank_name,
            'bank_account_number' => $request->has('bank_account_number') ? $request->bank_account_number : $contract->bank_account_number,
            'bank_account_holder' => $request->has('bank_account_holder') ? $request->bank_account_holder : $contract->bank_account_holder,
            'document_number' => $request->has('document_number') ? $request->document_number : $contract->document_number,
            'status' => $status,
            'notes' => $request->has('notes') ? $request->notes : $contract->notes,
        ]);

        // Auto sync employee status
        $emp = Employee::find($contract->employee_id);
        if ($emp) {
            if ($contract->department) $emp->department = $contract->department;
            if ($contract->position) $emp->position = $contract->position;
            
            if ($status === 'expired') {
                $emp->is_active = false;
                $emp->exit_date = $contract->end_date;
            } else {
                $emp->is_active = true;
                $emp->exit_date = null;
            }
            $emp->save();
        }

        // Auto sync salary
        if ($basicSal > 0) {
            try {
                HrisEmployeeSalary::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'employee_id' => $contract->employee_id
                    ],
                    [
                        'salary_type' => 'monthly',
                        'amount' => $basicSal,
                        'bank_name' => $contract->bank_name,
                        'bank_account_number' => $contract->bank_account_number,
                        'bank_account_holder' => $contract->bank_account_holder,
                        'effective_date' => $contract->start_date,
                        'notes' => 'Tersinkron otomatis dari Perubahan Kontrak: ' . $contract->contract_number,
                    ]
                );
            } catch (\Exception $e) {
                \Log::warning('HrisEmployeeSalary sync notice: ' . $e->getMessage());
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data kontrak berhasil diperbarui.',
            'data' => $contract->load('employee.user')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $contract = HrisContract::where('tenant_id', $tenantId)->findOrFail($id);
        $contract->delete();

        return response()->json(['status' => 'success', 'message' => 'Kontrak kerja berhasil dihapus.']);
    }

    public function previewPdf(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $contract = HrisContract::where('tenant_id', $tenantId)->findOrFail($id);

        if (!$contract->document_pdf_path) {
            return response()->json(['message' => 'Berkas PDF kontrak tidak ditemukan.'], 404);
        }

        return EncryptedStorageService::streamResponse($contract->document_pdf_path, $contract->document_pdf_name ?: 'Kontrak.pdf', false);
    }

    public function downloadPdf(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $contract = HrisContract::where('tenant_id', $tenantId)->findOrFail($id);

        if (!$contract->document_pdf_path) {
            return response()->json(['message' => 'Berkas PDF kontrak tidak ditemukan.'], 404);
        }

        return EncryptedStorageService::streamResponse($contract->document_pdf_path, $contract->document_pdf_name ?: 'Kontrak.pdf', true);
    }
}
