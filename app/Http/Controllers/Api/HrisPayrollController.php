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
use App\Models\HrisRequest;
use App\Models\HrisRequestPolicy;
use App\Models\HrisContract;
use App\Models\HrisResignation;
use App\Services\EncryptedStorageService;
use Illuminate\Support\Facades\DB;

class HrisPayrollController extends Controller
{
        private function getTenantId(Request $request)
    {
        $user = $request->user();
        if (!$user && $request->has('token')) {
            $token = $request->query('token');
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $user = $accessToken->tokenable;
            }
        }
        if (!$user) {
            return 2; // Default tenant fallback for active session
        }
        return $user->role === 'employee' ? ($user->employee ? $user->employee->admin_id : ($user->admin_id ?? $user->id)) : ($user->admin_id ?? $user->id);
    }

    public function index(Request $request)
    {
        $year = $request->query('year', date('Y'));
        
        $monthly = HrisPayroll::where('tenant_id', $this->getTenantId($request))
            ->where('payroll_type', 'monthly')
            ->where(function ($q) use ($year) {
                $q->where('year', $year)->orWhereYear('period_start', $year);
            })
            ->withCount('payslips')
            ->latest()
            ->get();

        $daily = HrisPayroll::where('tenant_id', $this->getTenantId($request))
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

            // Only fetch active employees
            $employees = Employee::where('admin_id', $tenantId)
                ->where('is_active', 1)
                ->with('user')
                ->get();
            $totalAmount = 0;

            $adjBatch = HrisSalaryAdjustmentBatch::where('tenant_id', $tenantId)
                ->where('month', $month)
                ->where('year', $year)
                ->with('items')
                ->first();

            $absencePolicy = HrisRequestPolicy::where('tenant_id', $tenantId)->where('policy_type', 'absence')->first();
            $sickPolicy = HrisRequestPolicy::where('tenant_id', $tenantId)->where('policy_type', 'sick')->first();

            $periodStartStr = date('Y-m-d', strtotime($request->period_start));
            $periodEndStr = date('Y-m-d', strtotime($request->period_end));

            foreach ($employees as $emp) {
                // 0. Check Resignation / Non-active status
                $resignation = HrisResignation::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('status', 'approved')
                    ->first();
                if ($resignation && $resignation->effective_date && $resignation->effective_date < $periodStartStr) {
                    // Employee already resigned before payroll period
                    continue;
                }

                // 0.1 Check Contract validity for this payroll period
                $contracts = HrisContract::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('status', '!=', 'terminated')
                    ->get();

                $activeContract = null;
                if ($contracts->isNotEmpty()) {
                    // Find a contract covering this payroll period
                    $validContract = $contracts->first(function ($c) use ($periodStartStr, $periodEndStr) {
                        $cStart = date('Y-m-d', strtotime($c->start_date));
                        $cEnd = $c->end_date ? date('Y-m-d', strtotime($c->end_date)) : null;

                        $startOk = $cStart <= $periodEndStr;
                        $endOk = empty($cEnd) || $cEnd >= $periodStartStr;

                        return $startOk && $endOk && ($c->status === 'active' || (empty($c->status) && $endOk));
                    });

                    if (!$validContract) {
                        // Employee has contracts, but none are valid/active for this period (e.g. expired)
                        continue;
                    }
                    $activeContract = $validContract;
                }

                // 1. Basic Salary
                $basicSalary = 0;
                $salaryRecord = HrisEmployeeSalary::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('wage_type', 'Bulanan')
                    ->latest('effective_date')
                    ->first();

                if ($activeContract && (float)$activeContract->basic_salary > 0) {
                    $basicSalary = (float)$activeContract->basic_salary;
                } elseif ($salaryRecord) {
                    $basicSalary = (float)$salaryRecord->amount;
                }

                // 2. Allowances
                $allowances = 0;
                $allowanceRecord = HrisEmployeeAllowance::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->with('items')
                    ->latest('effective_date')
                    ->first();

                if ($activeContract && !empty($activeContract->allowances_json) && is_array($activeContract->allowances_json)) {
                    foreach ($activeContract->allowances_json as $ca) {
                        $allowances += (float)($ca['amount'] ?? 0);
                    }
                } elseif ($allowanceRecord) {
                    $allowances = (float)$allowanceRecord->items->sum('amount');
                }

                // 3. Overtime Pay
                $overtimePay = (float) HrisOvertime::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('status', 'approved')
                    ->whereBetween('date', [$request->period_start, $request->period_end])
                    ->sum('total_pay');

                // 4. Loan Deductions (Kasbon)
                $activeLoan = HrisLoan::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('status', 'active')
                    ->where('remaining_amount', '>', 0)
                    ->first();
                $loanDeductions = 0;
                if ($activeLoan) {
                    $monthlyDed = (float)($activeLoan->monthly_deduction > 0 ? $activeLoan->monthly_deduction : $activeLoan->amount / max(1, $activeLoan->tenor_months));
                    $loanDeductions = min((float)$activeLoan->remaining_amount, $monthlyDed);
                }

                // 5. BPJS Kesehatan & Ketenagakerjaan
                $bpjsKes = HrisEmployeeBpjs::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('bpjs_type', 'kesehatan')
                    ->latest('effective_date')
                    ->first();
                $bpjsKesehatanAmount = $bpjsKes ? (float)$bpjsKes->amount : 0;

                $bpjsTk = HrisEmployeeBpjs::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('bpjs_type', 'ketenagakerjaan')
                    ->latest('effective_date')
                    ->first();
                $bpjsTkAmount = $bpjsTk ? (float)$bpjsTk->amount : 0;

                // 6. Potongan Kehadiran / Unpaid Leave
                $approvedRequests = HrisRequest::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('status', 'approved')
                    ->where(function ($q) use ($request) {
                        $q->whereBetween('start_date', [$request->period_start, $request->period_end])
                          ->orWhereBetween('end_date', [$request->period_start, $request->period_end]);
                    })
                    ->with('leaveType')
                    ->get();

                $unpaidDays = 0;
                foreach ($approvedRequests as $reqItem) {
                    $days = (int)($reqItem->days_count ?? 1);
                    if ($reqItem->request_type === 'izin_absen') {
                        if ($absencePolicy && !$absencePolicy->is_paid) {
                            $unpaidDays += $days;
                        }
                    } elseif ($reqItem->request_type === 'izin_cuti') {
                        if ($reqItem->leaveType && !$reqItem->leaveType->is_paid) {
                            $unpaidDays += $days;
                        }
                    } elseif ($reqItem->request_type === 'izin_sakit') {
                        if ($sickPolicy && !$sickPolicy->is_paid) {
                            $unpaidDays += $days;
                        }
                    }
                }

                $dailyRate = $basicSalary > 0 ? ($basicSalary / 25) : 0;
                $absenceDeductions = round($unpaidDays * $dailyRate, 2);

                // 7. Adjustments
                $adjAdd = 0;
                $adjDed = 0;
                if ($adjBatch) {
                    $empAdjs = $adjBatch->items->where('employee_id', $emp->id);
                    $adjAdd = (float)$empAdjs->where('type', 'addition')->sum('amount');
                    $adjDed = (float)$empAdjs->where('type', 'deduction')->sum('amount');
                }

                // Net salary calculation
                $netSalary = max(0, ($basicSalary + $allowances + $overtimePay + $adjAdd) - ($bpjsKesehatanAmount + $bpjsTkAmount + $loanDeductions + $absenceDeductions + $adjDed));

                HrisPayslip::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $emp->id,
                    'basic_salary' => $basicSalary,
                    'allowances' => $allowances,
                    'overtime_pay' => $overtimePay,
                    'loan_deductions' => $loanDeductions,
                    'absence_deductions' => $absenceDeductions,
                    'reimbursements' => 0,
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
                'message' => 'Payroll bulanan berhasil digenerate!',
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

            $dailySalaries = HrisEmployeeSalary::where('tenant_id', $tenantId)
                ->where('wage_type', 'Harian')
                ->whereHas('employee', function($eq) {
                    $eq->where('is_active', 1);
                })
                ->with('employee.user')
                ->get();

            $totalAmount = 0;
            $periodStartStr = date('Y-m-d', strtotime($request->period_start));
            $periodEndStr = date('Y-m-d', strtotime($request->period_end));

            foreach ($dailySalaries as $sal) {
                $emp = $sal->employee;
                if (!$emp || !$emp->is_active) continue;

                // Check contract
                $contracts = HrisContract::where('tenant_id', $tenantId)
                    ->where('employee_id', $emp->id)
                    ->where('status', '!=', 'terminated')
                    ->get();

                if ($contracts->isNotEmpty()) {
                    $validContract = $contracts->first(function ($c) use ($periodStartStr, $periodEndStr) {
                        $cStart = date('Y-m-d', strtotime($c->start_date));
                        $cEnd = $c->end_date ? date('Y-m-d', strtotime($c->end_date)) : null;
                        return $cStart <= $periodEndStr && (empty($cEnd) || $cEnd >= $periodStartStr) && ($c->status === 'active' || empty($c->status));
                    });
                    if (!$validContract) continue;
                }

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
            ->where('tenant_id', $this->getTenantId($request))
            ->with(['payslips.employee.user', 'processor'])
            ->firstOrFail();

        return response()->json($payroll);
    }

    public function publish($id, Request $request)
    {
        $payroll = HrisPayroll::where('id', $id)
            ->where('tenant_id', $this->getTenantId($request))
            ->with(['payslips.employee.user', 'processor'])
            ->firstOrFail();

        $payroll->update(['status' => 'published']);

        // 1. Process active loan deductions
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

        // 2. Generate and encrypt Batch Summary Report (Dokumen 1 - Khusus Admin, terenkripsi)
        try {
            $batchHtml = $this->renderProfessionalBatchReportHtml($payroll);
            $stored = EncryptedStorageService::storeEncrypted(
                $batchHtml,
                $payroll->tenant_id,
                'payrolls',
                'batch_' . $payroll->id,
                'Laporan_Batch_Payroll_' . $payroll->code . '.html'
            );
            $payroll->update(['report_file_path' => $stored['path']]);
        } catch (\Exception $e) {
            \Log::warning('Failed generating encrypted batch payroll report: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Slip gaji berhasil dipublish dan laporan batch terenkripsi telah dibuat!',
            'report_file_path' => $payroll->report_file_path
        ]);
    }

        public function downloadBatchReport($id, Request $request)
    {
        $payroll = HrisPayroll::where('id', $id)
            ->where('tenant_id', $this->getTenantId($request))
            ->with(['payslips.employee.user', 'processor'])
            ->firstOrFail();

        $html = '';
        if (!empty($payroll->report_file_path)) {
            try {
                $decrypted = EncryptedStorageService::getDecrypted($payroll->report_file_path);
                $html = $decrypted['content'];
            } catch (\Exception $e) {
                // fallback to render
            }
        }

        if (empty($html)) {
            $html = $this->renderProfessionalBatchReportHtml($payroll);
        }

        // Ensure auto-print is included if requested
        if (!str_contains($html, 'window.print()')) {
            $html = str_replace('</body>', "<script>window.addEventListener('DOMContentLoaded', function() { setTimeout(function() { window.print(); }, 400); });</script></body>", $html);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'inline; filename="Laporan_Batch_Payroll_' . $payroll->code . '.html"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function downloadIndividualPayslipPdf($id, Request $request)
    {
        $slip = HrisPayslip::where('id', $id)
            ->with(['employee.user', 'payroll'])
            ->firstOrFail();

        // Security check
        $tenantId = $this->getTenantId($request);
        if ($slip->payroll && $slip->payroll->tenant_id != $tenantId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $html = $this->renderProfessionalPayslipHtml($slip);

        $empName = str_replace(' ', '_', $slip->employee?->user?->name ?: 'Karyawan');
        $code = $slip->payroll?->code ?: 'SLIP';
        $filename = "Slip_Gaji_{$code}_{$empName}.html";

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function destroy($id, Request $request)
    {
        $payroll = HrisPayroll::where('id', $id)
            ->where('tenant_id', $this->getTenantId($request))
            ->firstOrFail();

        // Clean up encrypted batch report file from storage
        if (!empty($payroll->report_file_path)) {
            EncryptedStorageService::deleteFile($payroll->report_file_path);
        }

        $payroll->payslips()->delete();
        $payroll->delete();

        return response()->json(['message' => 'Batch slip gaji dan dokumen terenkripsi berhasil dihapus bersih.']);
    }

    // =========================================================================
    // DOKUMEN TEMPLATES (PROFESSIONAL CORPORATE STYLING)
    // =========================================================================

    private function terbilang($angka)
    {
        $angka = abs((float)$angka);
        $baca = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        $terbilang = '';

        if ($angka < 12) {
            $terbilang = ' ' . $baca[(int)$angka];
        } elseif ($angka < 20) {
            $terbilang = $this->terbilang($angka - 10) . ' Belas';
        } elseif ($angka < 100) {
            $terbilang = $this->terbilang($angka / 10) . ' Puluh' . $this->terbilang($angka % 10);
        } elseif ($angka < 200) {
            $terbilang = ' Seratus' . $this->terbilang($angka - 100);
        } elseif ($angka < 1000) {
            $terbilang = $this->terbilang($angka / 100) . ' Ratus' . $this->terbilang($angka % 100);
        } elseif ($angka < 2000) {
            $terbilang = ' Seribu' . $this->terbilang($angka - 1000);
        } elseif ($angka < 1000000) {
            $terbilang = $this->terbilang($angka / 1000) . ' Ribu' . $this->terbilang($angka % 1000);
        } elseif ($angka < 1000000000) {
            $terbilang = $this->terbilang($angka / 1000000) . ' Juta' . $this->terbilang($angka % 1000000);
        } elseif ($angka < 1000000000000) {
            $terbilang = $this->terbilang($angka / 1000000000) . ' Miliar' . $this->terbilang(fmod($angka, 1000000000));
        }

        return trim($terbilang);
    }

        private function renderProfessionalPayslipHtml(HrisPayslip $slip): string
    {
        $emp = $slip->employee;
        $user = $emp ? $emp->user : null;
        $payroll = $slip->payroll;

        $basic = (float)$slip->basic_salary;
        $allowances = (float)$slip->allowances;
        $overtime = (float)$slip->overtime_pay;
        $adjAdd = (float)$slip->adjustments_addition;
        $totalGross = $basic + $allowances + $overtime + $adjAdd;

        $bpjsKes = (float)$slip->bpjs_kesehatan;
        $bpjsTk = (float)$slip->bpjs_ketenagakerjaan;
        $loanDed = (float)$slip->loan_deductions;
        $absenceDed = (float)$slip->absence_deductions;
        $adjDed = (float)$slip->adjustments_deduction;
        $totalDed = $bpjsKes + $bpjsTk + $loanDed + $absenceDed + $adjDed;

        $netSalary = (float)$slip->net_salary;
        $terbilangText = $this->terbilang($netSalary) . ' Rupiah';

        $periodText = $payroll ? ($payroll->batch_name ?: ('Periode ' . date('d/m/Y', strtotime($payroll->period_start)) . ' - ' . date('d/m/Y', strtotime($payroll->period_end)))) : 'Bulan Ini';
        $printDate = date('d F Y, H:i');

        $empName = htmlspecialchars($user?->name ?: 'Karyawan');
        $empId = htmlspecialchars($emp?->employee_id ?: '-');
        $empDept = htmlspecialchars($emp?->department ?: '-');
        $empPos = htmlspecialchars($emp?->position ?: '-');
        $batchCode = htmlspecialchars($payroll?->code ?: '-');

        $basicFmt = $this->formatNumber($basic);
        $allowancesFmt = $this->formatNumber($allowances);
        $overtimeFmt = $this->formatNumber($overtime);
        $adjAddFmt = $this->formatNumber($adjAdd);
        $totalGrossFmt = $this->formatNumber($totalGross);

        $bpjsKesFmt = $this->formatNumber($bpjsKes);
        $bpjsTkFmt = $this->formatNumber($bpjsTk);
        $loanDedFmt = $this->formatNumber($loanDed);
        $absenceDedFmt = $this->formatNumber($absenceDed);
        $adjDedFmt = $this->formatNumber($adjDed);
        $totalDedFmt = $this->formatNumber($totalDed);
        $netSalaryFmt = $this->formatNumber($netSalary);

        $adjAddRow = $adjAdd > 0 ? "<div class=\"item-row\"><span class=\"item-name\">Penyesuaian (+)</span><span class=\"item-amt\">Rp {$adjAddFmt}</span></div>" : "";
        $loanRow = $loanDed > 0 ? "<div class=\"item-row\"><span class=\"item-name\">Potongan Kasbon / Pinjaman</span><span class=\"item-amt\" style=\"color: #b91c1c;\">- Rp {$loanDedFmt}</span></div>" : "";
        $absenceRow = $absenceDed > 0 ? "<div class=\"item-row\"><span class=\"item-name\">Potongan Kehadiran (Unpaid)</span><span class=\"item-amt\" style=\"color: #b91c1c;\">- Rp {$absenceDedFmt}</span></div>" : "";
        $adjDedRow = $adjDed > 0 ? "<div class=\"item-row\"><span class=\"item-name\">Penyesuaian (-)</span><span class=\"item-amt\" style=\"color: #b91c1c;\">- Rp {$adjDedFmt}</span></div>" : "";

        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Gaji - {$empId} - {$empName}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif; }
        body { background: #f1f5f9; padding: 24px 12px; color: #1e293b; }
        .payslip-card { max-width: 780px; margin: 0 auto; background: #ffffff; padding: 36px 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .company-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px double #0f172a; padding-bottom: 16px; margin-bottom: 20px; }
        .company-title { font-size: 18px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; text-transform: uppercase; }
        .company-sub { font-size: 11px; color: #64748b; margin-top: 2px; }
        .confidential-badge { display: inline-block; background: #fee2e2; color: #b91c1c; font-weight: 800; font-size: 10px; padding: 3px 8px; border-radius: 4px; letter-spacing: 0.5px; }
        .doc-title { text-align: center; margin: 16px 0; font-size: 16px; font-weight: 800; letter-spacing: 1px; color: #0f172a; text-transform: uppercase; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 24px; background: #f8fafc; padding: 14px 18px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 24px; font-size: 12px; }
        .meta-row { display: flex; justify-content: space-between; }
        .meta-label { color: #64748b; font-weight: 500; }
        .meta-val { font-weight: 700; color: #0f172a; }
        .table-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .section-box { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .section-header { background: #f8fafc; padding: 10px 14px; font-size: 12px; font-weight: 800; color: #0f172a; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.5px; }
        .item-list { padding: 8px 14px; font-size: 12px; }
        .item-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed #f1f5f9; }
        .item-row:last-child { border-bottom: none; }
        .item-name { color: #334155; }
        .item-amt { font-family: 'Courier New', monospace; font-weight: 700; color: #0f172a; }
        .section-total { background: #f8fafc; padding: 8px 14px; font-size: 12px; font-weight: 800; display: flex; justify-content: space-between; border-top: 1px solid #e2e8f0; }
        .thp-container { background: #0f172a; color: #ffffff; padding: 16px 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .thp-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.9; }
        .thp-terbilang { font-size: 11px; font-style: italic; opacity: 0.8; margin-top: 3px; }
        .thp-val { font-size: 22px; font-weight: 900; font-family: 'Courier New', monospace; color: #34d399; }
        .signature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; text-align: center; font-size: 11px; margin-top: 24px; }
        .sig-space { height: 50px; }
        .sig-name { font-weight: 700; text-decoration: underline; }
        .print-btn { display: block; width: 100%; max-width: 220px; margin: 20px auto 0; padding: 12px 20px; background: #2563eb; color: #ffffff; text-align: center; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; }
        @media print {
            body { background: #ffffff; padding: 0; }
            .payslip-card { box-shadow: none; border: none; padding: 0; width: 100%; max-width: 100%; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>

<div class="payslip-card">
    <div class="company-header">
        <div>
            <div class="company-title">PT MAJU SEJAHTERA BERSAMA</div>
            <div class="company-sub">Sistem Manajemen Operasional & Human Resource Information System</div>
            <div class="company-sub">Jl. Pemuda No. 108, Jakarta Pusat &bull; support@majusejahtera.com</div>
        </div>
        <div style="text-align: right;">
            <span class="confidential-badge">CONFIDENTIAL</span>
            <div style="font-size: 10px; color: #64748b; margin-top: 4px;">Kode: <b>{$batchCode}</b></div>
        </div>
    </div>

    <div class="doc-title">SLIP GAJI KARYAWAN</div>

    <div class="meta-grid">
        <div class="meta-row"><span class="meta-label">Nama Karyawan:</span><span class="meta-val">{$empName}</span></div>
        <div class="meta-row"><span class="meta-label">ID Karyawan:</span><span class="meta-val">{$empId}</span></div>
        <div class="meta-row"><span class="meta-label">Departemen:</span><span class="meta-val">{$empDept}</span></div>
        <div class="meta-row"><span class="meta-label">Jabatan:</span><span class="meta-val">{$empPos}</span></div>
        <div class="meta-row"><span class="meta-label">Periode Gaji:</span><span class="meta-val">{$periodText}</span></div>
        <div class="meta-row"><span class="meta-label">Tanggal Cetak:</span><span class="meta-val">{$printDate}</span></div>
    </div>

    <div class="table-container">
        <!-- PENGHASILAN -->
        <div class="section-box">
            <div class="section-header" style="color: #047857;">Komponen Penghasilan (+)</div>
            <div class="item-list">
                <div class="item-row">
                    <span class="item-name">Gaji Pokok</span>
                    <span class="item-amt">Rp {$basicFmt}</span>
                </div>
                <div class="item-row">
                    <span class="item-name">Tunjangan Tetap</span>
                    <span class="item-amt">Rp {$allowancesFmt}</span>
                </div>
                <div class="item-row">
                    <span class="item-name">Upah Lembur</span>
                    <span class="item-amt">Rp {$overtimeFmt}</span>
                </div>
                {$adjAddRow}
            </div>
            <div class="section-total">
                <span>Total Penghasilan Kotor:</span>
                <span class="item-amt" style="color: #047857;">Rp {$totalGrossFmt}</span>
            </div>
        </div>

        <!-- POTONGAN -->
        <div class="section-box">
            <div class="section-header" style="color: #b91c1c;">Komponen Potongan (-)</div>
            <div class="item-list">
                {$loanRow}
                {$absenceRow}
                <div class="item-row">
                    <span class="item-name">BPJS Kesehatan</span>
                    <span class="item-amt" style="color: #b91c1c;">- Rp {$bpjsKesFmt}</span>
                </div>
                <div class="item-row">
                    <span class="item-name">BPJS Ketenagakerjaan</span>
                    <span class="item-amt" style="color: #b91c1c;">- Rp {$bpjsTkFmt}</span>
                </div>
                {$adjDedRow}
            </div>
            <div class="section-total">
                <span>Total Potongan:</span>
                <span class="item-amt" style="color: #b91c1c;">- Rp {$totalDedFmt}</span>
            </div>
        </div>
    </div>

    <div class="thp-container">
        <div>
            <div class="thp-label">Gaji Bersih Diterima (Take Home Pay)</div>
            <div class="thp-terbilang">Terbilang: {$terbilangText}</div>
        </div>
        <div class="thp-val">Rp {$netSalaryFmt}</div>
    </div>

    <div class="signature-grid">
        <div>
            <p>Penerima,</p>
            <div class="sig-space"></div>
            <p class="sig-name">{$empName}</p>
            <p style="font-size: 10px; color: #64748b;">Karyawan</p>
        </div>
        <div>
            <p>Disetujui Oleh,</p>
            <div class="sig-space"></div>
            <p class="sig-name">HRD & Finance Dept.</p>
            <p style="font-size: 10px; color: #64748b;">PT Maju Sejahtera Bersama</p>
        </div>
    </div>

    <button onclick="window.print()" class="print-btn">🖨️ Cetak / Simpan PDF</button>
</div>

    <script>
        window.addEventListener('DOMContentLoaded', function() {
            var params = new URLSearchParams(window.location.search);
            if (params.get('autoprint') === '1') {
                setTimeout(function() { window.print(); }, 500);
            }
        });
    </script>
</body>
</html>
HTML;
    }

            private function renderProfessionalBatchReportHtml(HrisPayroll $payroll): string
    {
        $slips = $payroll->payslips;
        $totalSlips = $slips->count();
        $totalAmount = (float)$payroll->total_amount;
        $totalAmountFmt = $this->formatNumber($totalAmount);
        $batchCode = htmlspecialchars($payroll->code);
        $batchName = htmlspecialchars($payroll->batch_name ?: ('Periode ' . date('d/m/Y', strtotime($payroll->period_start)) . ' s/d ' . date('d/m/Y', strtotime($payroll->period_end))));
        $status = htmlspecialchars($payroll->status);
        $printDate = date('d F Y, H:i');

        $rowsHtml = '';
        foreach ($slips as $idx => $s) {
            $num = $idx + 1;
            $empName = htmlspecialchars($s->employee?->user?->name ?: '-');
            $empId = htmlspecialchars($s->employee?->employee_id ?: '-');
            $dept = htmlspecialchars($s->employee?->department ?: '-');
            $basic = $this->formatNumber((float)$s->basic_salary);
            $allowances = $this->formatNumber((float)$s->allowances);
            $overtime = $this->formatNumber((float)$s->overtime_pay);
            $loan = $this->formatNumber((float)$s->loan_deductions);
            $absence = $this->formatNumber((float)$s->absence_deductions);
            $net = $this->formatNumber((float)$s->net_salary);

            $rowsHtml .= "
            <tr>
                <td style=\"text-align: center;\">{$num}</td>
                <td><b>{$empName}</b><br><span style=\"font-size: 10px; color: #64748b;\">{$empId} &bull; {$dept}</span></td>
                <td style=\"text-align: right; font-family: monospace;\">Rp {$basic}</td>
                <td style=\"text-align: right; font-family: monospace; color: #047857;\">+ Rp {$allowances}</td>
                <td style=\"text-align: right; font-family: monospace; color: #047857;\">+ Rp {$overtime}</td>
                <td style=\"text-align: right; font-family: monospace; color: #b91c1c;\">- Rp {$loan}</td>
                <td style=\"text-align: right; font-family: monospace; color: #b91c1c;\">- Rp {$absence}</td>
                <td style=\"text-align: right; font-family: monospace; font-weight: 800; color: #0f172a;\">Rp {$net}</td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Payroll Batch - {$batchCode}</title>
    <style>
        @page { size: landscape; margin: 10mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif; }
        body { background: #f8fafc; padding: 24px 12px; color: #0f172a; }
        .report-card { max-width: 1100px; margin: 0 auto; background: #ffffff; padding: 32px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0f172a; padding-bottom: 16px; margin-bottom: 20px; }
        .title { font-size: 18px; font-weight: 800; text-transform: uppercase; }
        .meta-box { background: #f1f5f9; padding: 12px 16px; border-radius: 8px; font-size: 12px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 20px; }
        th { background: #f1f5f9; padding: 10px 8px; border: 1px solid #cbd5e1; text-align: left; font-weight: 700; }
        td { padding: 8px; border: 1px solid #cbd5e1; vertical-align: middle; }
        .total-box { background: #0f172a; color: #ffffff; padding: 14px 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 700; }
        .top-bar { display: flex; justify-content: flex-end; margin-bottom: 16px; gap: 10px; }
        .btn-action { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; border: none; }
        .btn-action:hover { background: #1d4ed8; }
        @media print { 
            .top-bar, .print-btn { display: none !important; } 
            body { padding: 0; background: #fff; } 
            .report-card { border: none; box-shadow: none; padding: 0; max-width: 100%; } 
        }
    </style>
</head>
<body>
<div class="report-card">
    <div class="top-bar">
        <button onclick="window.print()" class="btn-action">🖨️ Simpan PDF / Cetak Laporan</button>
    </div>

    <div class="header">
        <div>
            <div class="title">PT MAJU SEJAHTERA BERSAMA</div>
            <div style="font-size: 12px; color: #64748b;">LAPORAN REKAPITULASI PAYROLL (BATCH REPORT)</div>
        </div>
        <div style="text-align: right; font-size: 11px; color: #64748b;">
            Kode Batch: <b>{$batchCode}</b><br>Tanggal: {$printDate}
        </div>
    </div>

    <div class="meta-box">
        <div>Nama Batch: <b>{$batchName}</b></div>
        <div>Total Karyawan: <b>{$totalSlips} Orang</b></div>
        <div>Status: <b style="color: #047857; text-transform: uppercase;">{$status}</b></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px; text-align: center;">NO</th>
                <th>NAMA KARYAWAN & DEPT</th>
                <th style="text-align: right;">GAJI POKOK</th>
                <th style="text-align: right;">TUNJANGAN</th>
                <th style="text-align: right;">LEMBUR</th>
                <th style="text-align: right;">KASBON</th>
                <th style="text-align: right;">UNPAID</th>
                <th style="text-align: right;">GAJI BERSIH (THP)</th>
            </tr>
        </thead>
        <tbody>
            {$rowsHtml}
        </tbody>
    </table>

    <div class="total-box">
        <span>TOTAL PENGELUARAN GAJI (BATCH TOTAL):</span>
        <span style="font-size: 18px; font-family: monospace; color: #34d399;">Rp {$totalAmountFmt}</span>
    </div>
</div>

<script>
    window.addEventListener('DOMContentLoaded', function() {
        var params = new URLSearchParams(window.location.search);
        if (params.get('autoprint') === '1') {
            setTimeout(function() { window.print(); }, 400);
        }
    });
</script>
</body>
</html>
HTML;
    }

    private function formatNumber($val): string
    {
        return number_format((float)$val, 0, ',', '.');
    }
}
