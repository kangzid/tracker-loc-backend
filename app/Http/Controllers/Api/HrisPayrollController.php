<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HrisPayroll;
use App\Models\HrisPayslip;
use App\Models\HrisOvertime;
use App\Models\HrisClaim;
use App\Models\HrisLoan;
use App\Models\HrisLoanPayment;
use App\Models\Employee;
use App\Models\HrisEmployeeSalary;
use App\Models\HrisEmployeeAllowance;
use App\Models\HrisEmployeeBpjs;
use App\Models\HrisSalaryAdjustmentBatch;
use Illuminate\Support\Facades\DB;

class HrisPayrollController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->query('year', date('Y'));
        
        $monthly = HrisPayroll::where('tenant_id', $request->user()->id)
            ->where('payroll_type', 'monthly')
            ->where(function ($q) use ($year) {
                $q->where('year', $year)->orWhereYear('period_start', $year);
            })
            ->withCount('payslips')
            ->latest()
            ->get();

        $daily = HrisPayroll::where('tenant_id', $request->user()->id)
            ->where('payroll_type', 'daily')
            ->where(function ($q) use ($year) {
                $q->whereYear('period_start', $year)->orWhereYear('slip_date', $year);
            })
            ->withCount('payslips')
            ->latest()
            ->get();

        return response()->json([
            'monthly' => $monthly,
            'daily' => $daily,
        ]);
    }

    public function generateMonthly(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $tenantId = $request->user()->id;
        $month = $request->month;
        $year = $request->year;

        // Code: GJ[M][YYYY] e.g. GJ82026
        $code = 'GJ' . $month . $year;
        $batchName = 'Gaji Bulan ' . date('F', mktime(0, 0, 0, $month, 10)) . ' ' . $year;

        return DB::transaction(function () use ($request, $tenantId, $month, $year, $code, $batchName) {
            $payroll = HrisPayroll::create([
                'tenant_id' => $tenantId,
                'payroll_type' => 'monthly',
                'code' => $code,
                'batch_name' => $batchName,
                'month' => $month,
                'year' => $year,
                'period_start' => $request->period_start,
                'period_end' => $request->period_end,
                'status' => 'draft',
                'processed_by' => $tenantId,
            ]);

            $employees = Employee::where('admin_id', $tenantId)->with('user')->get();
            $totalAmount = 0;

            // Adjustment batch for this month & year
            $adjBatch = HrisSalaryAdjustmentBatch::where('tenant_id', $tenantId)
                ->where('month', $month)
                ->where('year', $year)
                ->with('items')
                ->first();

            foreach ($employees as $emp) {
                // 1. Basic Salary
                $salaryRecord = HrisEmployeeSalary::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('wage_type', 'Bulanan')
                    ->latest('effective_date')
                    ->first();
                $basicSalary = $salaryRecord ? (float)$salaryRecord->amount : 0;

                // 2. Allowances
                $allowanceRecord = HrisEmployeeAllowance::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->with('items')
                    ->latest('effective_date')
                    ->first();
                $allowances = $allowanceRecord ? (float)$allowanceRecord->items->sum('amount') : 0;


                // 3b. Lembur (Overtime Pay)
                $overtimePay = (float) HrisOvertime::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('status', 'approved')
                    ->whereBetween('date', [$request->period_start, $request->period_end])
                    ->sum('total_pay');


                // 3c. Reimbursement dikelola terpisah via modul Reimbursement (Pencairan Kas/Transfer Langsung)
                $reimbursements = 0;


                // 3d. Pinjaman / Kasbon (Loan Deductions)
                $activeLoan = HrisLoan::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('status', 'active')
                    ->where('remaining_amount', '>', 0)
                    ->first();
                $loanDeductions = 0;
                if ($activeLoan) {
                    $loanDeductions = min((float)$activeLoan->remaining_amount, (float)$activeLoan->monthly_deduction);
                }

                // 3. BPJS Kesehatan
                $bpjsKes = HrisEmployeeBpjs::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('bpjs_type', 'kesehatan')
                    ->latest('effective_date')
                    ->first();
                $bpjsKesehatanAmount = $bpjsKes ? (float)$bpjsKes->amount : 0;

                // 4. BPJS Ketenagakerjaan
                $bpjsTk = HrisEmployeeBpjs::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('bpjs_type', 'ketenagakerjaan')
                    ->latest('effective_date')
                    ->first();
                $bpjsTkAmount = $bpjsTk ? (float)$bpjsTk->amount : 0;

                // 5. Adjustments
                $adjAdd = 0;
                $adjDed = 0;
                if ($adjBatch) {
                    $empAdjs = $adjBatch->items->where('employee_id', $emp->id);
                    $adjAdd = (float)$empAdjs->where('type', 'addition')->sum('amount');
                    $adjDed = (float)$empAdjs->where('type', 'deduction')->sum('amount');
                }

                // Net salary calculation
                $netSalary = max(0, ($basicSalary + $allowances + $overtimePay + $adjAdd) - ($bpjsKesehatanAmount + $bpjsTkAmount + $loanDeductions + $adjDed));
                if ($netSalary < 0) $netSalary = 0;

                HrisPayslip::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $emp->id,
                    'basic_salary' => $basicSalary,
                    'allowances' => $allowances,
                    'overtime_pay' => $overtimePay,
                    'loan_deductions' => $loanDeductions,
                    'reimbursements' => $reimbursements,
                    'bpjs_kesehatan' => $bpjsKesehatanAmount,
                    'bpjs_ketenagakerjaan' => $bpjsTkAmount,
                    'adjustments_addition' => $adjAdd,
                    'adjustments_deduction' => $adjDed,
                    'net_salary' => $netSalary,
                    'status' => 'draft',
                ]);

                
                $totalAmount += $netSalary;
            }

            $payroll->update(['total_amount' => $totalAmount]);

            return response()->json([
                'message' => 'Slip gaji bulanan berhasil digenerate!',
                'payroll' => $payroll->load('payslips.employee.user'),
            ], 201);
        });
    }

    public function generateDaily(Request $request)
    {
        $request->validate([
            'slip_date' => 'required|date',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $tenantId = $request->user()->id;
        $slipDateFormatted = date('Ymd', strtotime($request->slip_date));
        $code = 'SGH' . $slipDateFormatted;
        $batchName = 'Slip Gaji Harian ' . date('d/m/Y', strtotime($request->slip_date));

        return DB::transaction(function () use ($request, $tenantId, $code, $batchName) {
            $payroll = HrisPayroll::create([
                'tenant_id' => $tenantId,
                'payroll_type' => 'daily',
                'code' => $code,
                'batch_name' => $batchName,
                'slip_date' => $request->slip_date,
                'period_start' => $request->period_start,
                'period_end' => $request->period_end,
                'status' => 'draft',
                'processed_by' => $tenantId,
            ]);

            // Pull daily wage employees
            $dailySalaries = HrisEmployeeSalary::where('tenant_id', $tenantId)
                ->where('wage_type', 'Harian')
                ->with('employee.user')
                ->get();

            $totalAmount = 0;

            foreach ($dailySalaries as $sal) {
                $basicSalary = (float)$sal->amount;
                $overtimePay = (float) HrisOvertime::where('tenant_id', $tenantId)
                    ->where('employee_id', $sal->employee_id)
                    ->where('status', 'approved')
                    ->whereBetween('date', [$request->period_start, $request->period_end])
                    ->sum('total_pay');

                $netSalary = max(0, $basicSalary + $overtimePay);

                HrisPayslip::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $sal->employee_id,
                    'basic_salary' => $basicSalary,
                    'overtime_pay' => $overtimePay,
                    'net_salary' => $netSalary,
                    'status' => 'draft',
                ]);

                $totalAmount += $netSalary;
            }

            $payroll->update(['total_amount' => $totalAmount]);

            return response()->json([
                'message' => 'Slip gaji harian berhasil digenerate!',
                'payroll' => $payroll->load('payslips.employee.user'),
            ], 201);
        });
    }

    public function slips($id, Request $request)
    {
        $payroll = HrisPayroll::where('id', $id)
            ->where('tenant_id', $request->user()->id)
            ->with(['payslips.employee.user', 'processor'])
            ->firstOrFail();

        return response()->json($payroll);
    }

    public function publish($id, Request $request)
    {
        $payroll = HrisPayroll::where('id', $id)
            ->where('tenant_id', $request->user()->id)
            ->firstOrFail();

        $payroll->update(['status' => 'published']);

        // Process loan deductions upon publish
        foreach ($payroll->payslips as $slip) {
            if ($slip->loan_deductions > 0) {
                $loan = HrisLoan::where('tenant_id', $payroll->tenant_id)
                    ->where('employee_id', $slip->employee_id)
                    ->where('status', 'active')
                    ->first();
                if ($loan) {
                    HrisLoanPayment::create([
                        'loan_id' => $loan->id,
                        'amount' => $slip->loan_deductions,
                        'payment_date' => now()->toDateString(),
                        'payment_method' => 'payroll',
                        'payroll_id' => $payroll->id,
                        'notes' => 'Potong gaji payroll ' . $payroll->code,
                    ]);
                    $newRemaining = max(0, (float)$loan->remaining_amount - (float)$slip->loan_deductions);
                    $newPaid = (float)$loan->paid_amount + (float)$slip->loan_deductions;
                    $loan->update([
                        'remaining_amount' => $newRemaining,
                        'paid_amount' => $newPaid,
                        'status' => ($newRemaining <= 0) ? 'completed' : 'active',
                    ]);
                }
            }
        }

        $payroll->payslips()->update(['status' => 'published']);

        return response()->json(['message' => 'Slip gaji berhasil dipublish ke karyawan!']);
    }

    public function destroy($id, Request $request)
    {
        HrisPayroll::where('id', $id)
            ->where('tenant_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Batch slip gaji berhasil dihapus']);
    }
}
