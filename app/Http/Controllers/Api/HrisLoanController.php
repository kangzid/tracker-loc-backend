<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisLoan;
use App\Models\HrisLoanPayment;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisLoanController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->isAdmin() ? $user->id : ($user->admin_id ?? $user->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisLoan::where('tenant_id', $tenantId)
            ->with(['employee.user', 'payments']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('employee_id', 'like', "%{$search}%")
                         ->orWhereHas('user', function ($uq) use ($search) {
                             $uq->where('name', 'like', "%{$search}%");
                         });
                  });
            });
        }

        $items = $query->orderBy('id', 'desc')->get();
        return response()->json($items);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisLoan::where('tenant_id', $tenantId);

        $totalActivePrincipal = (float)(clone $query)->where('status', 'active')->sum('amount');
        $totalRemaining = (float)(clone $query)->where('status', 'active')->sum('remaining_amount');
        $totalPaid = (float)(clone $query)->sum('paid_amount');
        $activeLoansCount = (int)(clone $query)->where('status', 'active')->count();
        $completedLoansCount = (int)(clone $query)->where('status', 'completed')->count();

        return response()->json([
            'total_active_principal' => $totalActivePrincipal,
            'total_remaining' => $totalRemaining,
            'total_paid' => $totalPaid,
            'active_loans_count' => $activeLoansCount,
            'completed_loans_count' => $completedLoansCount,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'amount' => 'required|numeric|min:10000',
            'tenor_months' => 'required|integer|min:1|max:60',
            'disbursed_at' => 'required|date',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $amount = (float)$request->amount;
        $tenor = (int)$request->tenor_months;
        $monthlyDeduction = round($amount / $tenor, 2);

        // Generate Code: LOAN[YY][MM][0001]
        $prefix = 'LOAN' . date('ym', strtotime($request->disbursed_at));
        $last = HrisLoan::where('tenant_id', $tenantId)->where('code', 'like', "{$prefix}%")->orderBy('id', 'desc')->first();
        $nextNum = 1;
        if ($last && preg_match('/' . $prefix . '(d+)/', $last->code, $m)) {
            $nextNum = (int)$m[1] + 1;
        }
        $code = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        $loan = HrisLoan::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'code' => $code,
            'amount' => $amount,
            'tenor_months' => $tenor,
            'monthly_deduction' => $monthlyDeduction,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'status' => 'active',
            'reason' => $request->reason,
            'disbursed_at' => $request->disbursed_at,
        ]);

        return response()->json($loan->load('employee.user'), 201);
    }

    public function manualPayment(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $loan = HrisLoan::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|max:50',
            'notes' => 'nullable|string|max:255',
        ]);

        $payAmount = min((float)$request->amount, (float)$loan->remaining_amount);

        $payment = HrisLoanPayment::create([
            'loan_id' => $loan->id,
            'amount' => $payAmount,
            'payment_date' => $request->payment_date,
            'payment_method' => $request->payment_method,
            'notes' => $request->notes ?? 'Pelunasan/cicilan manual kas',
        ]);

        $newRemaining = max(0, (float)$loan->remaining_amount - $payAmount);
        $newPaid = (float)$loan->paid_amount + $payAmount;
        $newStatus = ($newRemaining <= 0) ? 'completed' : 'active';

        $loan->update([
            'paid_amount' => $newPaid,
            'remaining_amount' => $newRemaining,
            'status' => $newStatus,
        ]);

        return response()->json([
            'message' => 'Pembayaran cicilan berhasil dicatat.',
            'loan' => $loan->load(['employee.user', 'payments'])
        ]);
    }

    public function history(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $loan = HrisLoan::where('tenant_id', $tenantId)->with(['employee.user', 'payments'])->findOrFail($id);

        return response()->json($loan);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $loan = HrisLoan::where('tenant_id', $tenantId)->findOrFail($id);
        $loan->delete();

        return response()->json(['message' => 'Data pinjaman berhasil dihapus.']);
    }
}
