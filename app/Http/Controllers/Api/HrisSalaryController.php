<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HrisEmployeeSalary;

class HrisSalaryController extends Controller
{
    public function index(Request $request)
    {
        $query = HrisEmployeeSalary::where('tenant_id', $request->user()->id)
            ->with(['employee.user']);

        // Search by employee name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee.user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Filter by position
        if ($request->filled('position')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('position', $request->position);
            });
        }

        // Filter by department
        if ($request->filled('department')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department', $request->department);
            });
        }

        // Filter by effective_date
        if ($request->filled('effective_date')) {
            $query->whereDate('effective_date', $request->effective_date);
        }

        $salaries = $query->latest()->get();

        return response()->json($salaries);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'wage_type' => 'required|in:Bulanan,Harian',
            'amount' => 'required|numeric|min:0',
            'effective_date' => 'required|date',
        ]);

        // Auto-generate code: G[YY][5 digits sequence] e.g. G2600001
        $year = date('y', strtotime($request->effective_date));
        $count = HrisEmployeeSalary::where('tenant_id', $request->user()->id)->count() + 1;
        $code = 'G' . $year . str_pad($count, 5, '0', STR_PAD_LEFT);

        $salary = HrisEmployeeSalary::create([
            'tenant_id' => $request->user()->id,
            'employee_id' => $request->employee_id,
            'code' => $code,
            'wage_type' => $request->wage_type,
            'amount' => $request->amount,
            'effective_date' => $request->effective_date,
        ]);

        return response()->json($salary->load('employee.user'), 201);
    }

    public function destroy($id, Request $request)
    {
        HrisEmployeeSalary::where('id', $id)
            ->where('tenant_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Gaji pokok berhasil dihapus']);
    }
}
