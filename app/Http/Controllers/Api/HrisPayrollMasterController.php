<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HrisAllowanceType;
use App\Models\HrisEmployeeAllowance;
use App\Models\HrisEmployeeAllowanceItem;
use App\Models\HrisEmployeeBpjs;
use App\Models\HrisSalaryAdjustmentBatch;
use App\Models\HrisSalaryAdjustment;

class HrisPayrollMasterController extends Controller
{
    // ==========================================
    // 1. Jenis Tunjangan
    // ==========================================
    public function getAllowanceTypes(Request $request)
    {
        $types = HrisAllowanceType::where('tenant_id', $request->user()->id)->get();
        return response()->json($types);
    }

    public function storeAllowanceType(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'type' => 'required|in:fixed,variable',
        ]);

        $type = HrisAllowanceType::create([
            'tenant_id' => $request->user()->id,
            'code' => $request->code,
            'name' => $request->name,
            'type' => $request->type,
        ]);

        return response()->json($type, 201);
    }

    public function deleteAllowanceType($id, Request $request)
    {
        HrisAllowanceType::where('id', $id)
            ->where('tenant_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Jenis tunjangan berhasil dihapus']);
    }

    // ==========================================
    // 2. Data Tunjangan Karyawan (Matrix)
    // ==========================================
    public function getEmployeeAllowances(Request $request)
    {
        $query = HrisEmployeeAllowance::where('tenant_id', $request->user()->id)
            ->with(['employee.user', 'items.allowanceType']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee.user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }
        if ($request->filled('position')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('position', $request->position);
            });
        }
        if ($request->filled('department')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department', $request->department);
            });
        }
        if ($request->filled('effective_date')) {
            $query->whereDate('effective_date', $request->effective_date);
        }

        $data = $query->latest()->get();
        return response()->json($data);
    }

    public function storeEmployeeAllowance(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'effective_date' => 'required|date',
            'items' => 'required|array', // [{ allowance_type_id: 1, amount: 300000 }]
        ]);

        // Code: T[YY][5 digits sequence] e.g. T2600001
        $year = date('y', strtotime($request->effective_date));
        $count = HrisEmployeeAllowance::where('tenant_id', $request->user()->id)->count() + 1;
        $code = 'T' . $year . str_pad($count, 5, '0', STR_PAD_LEFT);

        $allowance = HrisEmployeeAllowance::create([
            'tenant_id' => $request->user()->id,
            'employee_id' => $request->employee_id,
            'code' => $code,
            'effective_date' => $request->effective_date,
        ]);

        foreach ($request->items as $item) {
            if (isset($item['allowance_type_id']) && isset($item['amount']) && $item['amount'] > 0) {
                HrisEmployeeAllowanceItem::create([
                    'employee_allowance_id' => $allowance->id,
                    'allowance_type_id' => $item['allowance_type_id'],
                    'amount' => $item['amount'],
                ]);
            }
        }

        return response()->json($allowance->load(['employee.user', 'items.allowanceType']), 201);
    }

    public function deleteEmployeeAllowance($id, Request $request)
    {
        HrisEmployeeAllowance::where('id', $id)
            ->where('tenant_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Data tunjangan berhasil dihapus']);
    }

    // ==========================================
    // 3. BPJS Kesehatan & BPJS Ketenagakerjaan
    // ==========================================
    public function getBpjs(Request $request)
    {
        $type = $request->query('type', 'kesehatan'); // kesehatan | ketenagakerjaan

        $query = HrisEmployeeBpjs::where('tenant_id', $request->user()->id)
            ->where('bpjs_type', $type)
            ->with(['employee.user']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee.user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }
        if ($request->filled('position')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('position', $request->position);
            });
        }
        if ($request->filled('department')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department', $request->department);
            });
        }
        if ($request->filled('effective_date')) {
            $query->whereDate('effective_date', $request->effective_date);
        }

        $data = $query->latest()->get();
        return response()->json($data);
    }

    public function storeBpjs(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'bpjs_type' => 'required|in:kesehatan,ketenagakerjaan',
            'bpjs_number' => 'nullable|string|max:50',
            'amount' => 'required|numeric|min:0',
            'effective_date' => 'required|date',
        ]);

        // Code: BPK[YY][5 digits] for Kesehatan, K[YY][5 digits] for Ketenagakerjaan
        $year = date('y', strtotime($request->effective_date));
        $count = HrisEmployeeBpjs::where('tenant_id', $request->user()->id)
            ->where('bpjs_type', $request->bpjs_type)
            ->count() + 1;
            
        $prefix = $request->bpjs_type === 'kesehatan' ? 'BPK' : 'K';
        $code = $prefix . $year . str_pad($count, 5, '0', STR_PAD_LEFT);

        $bpjs = HrisEmployeeBpjs::create([
            'tenant_id' => $request->user()->id,
            'employee_id' => $request->employee_id,
            'code' => $code,
            'bpjs_type' => $request->bpjs_type,
            'bpjs_number' => $request->bpjs_number,
            'amount' => $request->amount,
            'effective_date' => $request->effective_date,
        ]);

        return response()->json($bpjs->load('employee.user'), 201);
    }

    public function deleteBpjs($id, Request $request)
    {
        HrisEmployeeBpjs::where('id', $id)
            ->where('tenant_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Data BPJS berhasil dihapus']);
    }

    // ==========================================
    // 4. Penyesuaian Gaji (Batches & Items)
    // ==========================================
    public function getAdjustmentBatches(Request $request)
    {
        $year = $request->query('year', date('Y'));
        $batches = HrisSalaryAdjustmentBatch::where('tenant_id', $request->user()->id)
            ->where('year', $year)
            ->with(['items.employee.user'])
            ->latest()
            ->get();

        return response()->json($batches);
    }

    public function storeAdjustmentBatch(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $code = 'PYG' . str_pad($request->month, 2, '0', STR_PAD_LEFT) . $request->year;

        $batch = HrisSalaryAdjustmentBatch::firstOrCreate([
            'tenant_id' => $request->user()->id,
            'month' => $request->month,
            'year' => $request->year,
        ], [
            'code' => $code,
        ]);

        return response()->json($batch->load('items.employee.user'), 201);
    }

    public function storeAdjustmentItem(Request $request, $batchId)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:addition,deduction',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $batch = HrisSalaryAdjustmentBatch::where('id', $batchId)
            ->where('tenant_id', $request->user()->id)
            ->firstOrFail();

        $item = HrisSalaryAdjustment::create([
            'batch_id' => $batch->id,
            'employee_id' => $request->employee_id,
            'type' => $request->type,
            'amount' => $request->amount,
            'notes' => $request->notes,
        ]);

        return response()->json($item->load('employee.user'), 201);
    }

    public function deleteAdjustmentItem($id, Request $request)
    {
        $item = HrisSalaryAdjustment::whereHas('batch', function ($q) use ($request) {
            $q->where('tenant_id', $request->user()->id);
        })->where('id', $id)->firstOrFail();

        $item->delete();
        return response()->json(['message' => 'Item penyesuaian berhasil dihapus']);
    }

    public function deleteAdjustmentBatch($batchId, Request $request)
    {
        HrisSalaryAdjustmentBatch::where('id', $batchId)
            ->where('tenant_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Batch penyesuaian berhasil dihapus']);
    }
}
