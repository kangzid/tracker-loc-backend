<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisClaim;
use App\Models\HrisClaimType;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HrisClaimController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->isAdmin() ? $user->id : ($user->admin_id ?? $user->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisClaim::where('tenant_id', $tenantId)
            ->with(['employee.user', 'claimType', 'approver']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('claim_type_id')) {
            $query->where('claim_type_id', $request->claim_type_id);
        }

        if ($request->filled('month')) {
            $query->whereMonth('claim_date', $request->month);
        }

        if ($request->filled('year')) {
            $query->whereYear('claim_date', $request->year);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('claim_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('claim_date', '<=', $request->end_date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('employee_id', 'like', "%{$search}%")
                         ->orWhereHas('user', function ($uq) use ($search) {
                             $uq->where('name', 'like', "%{$search}%");
                         });
                  });
            });
        }

        if ($request->filled('position')) {
            $query->whereHas('employee', fn($q) => $q->where('position', $request->position));
        }

        if ($request->filled('department')) {
            $query->whereHas('employee', fn($q) => $q->where('department', $request->department));
        }

        $items = $query->orderBy('claim_date', 'desc')->orderBy('id', 'desc')->get();
        return response()->json($items);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $month = $request->month ?? date('m');
        $year = $request->year ?? date('Y');

        $query = HrisClaim::where('tenant_id', $tenantId)
            ->whereMonth('claim_date', $month)
            ->whereYear('claim_date', $year);

        $totalApprovedAmount = (float) (clone $query)->whereIn('status', ['approved', 'paid'])->sum('amount');
        $totalPaidAmount = (float) (clone $query)->where('status', 'paid')->sum('amount');
        $pendingCount = (int) (clone $query)->where('status', 'pending')->count();
        $approvedCount = (int) (clone $query)->where('status', 'approved')->count();
        $paidCount = (int) (clone $query)->where('status', 'paid')->count();
        $rejectedCount = (int) (clone $query)->where('status', 'rejected')->count();

        return response()->json([
            'month' => (int)$month,
            'year' => (int)$year,
            'total_approved_amount' => $totalApprovedAmount,
            'total_paid_amount' => $totalPaidAmount,
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'paid_count' => $paidCount,
            'rejected_count' => $rejectedCount,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'claim_type_id' => 'nullable|exists:hris_claim_types,id',
            'claim_date' => 'required|date',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:1000',
            'title' => 'nullable|string|max:255',
            'receipt_base64' => 'nullable|string',
            'receipt_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate plafon if claim_type is set
        if ($request->filled('claim_type_id')) {
            $type = HrisClaimType::where('tenant_id', $tenantId)->find($request->claim_type_id);
            if ($type) {
                if ($type->requires_receipt && empty($request->receipt_base64)) {
                    return response()->json(['message' => 'Jenis klaim ini mewajibkan lampiran bukti struk/nota.'], 422);
                }
                if ($type->max_amount_per_claim > 0 && $request->amount > $type->max_amount_per_claim) {
                    return response()->json([
                        'message' => 'Nominal klaim melebihi batas plafon per klaim (Maksimal Rp ' . number_format($type->max_amount_per_claim, 0, ',', '.') . ')'
                    ], 422);
                }
                if ($type->max_amount_per_month > 0) {
                    $claimMonth = Carbon::parse($request->claim_date)->month;
                    $claimYear = Carbon::parse($request->claim_date)->year;
                    $monthlyUsed = HrisClaim::where('tenant_id', $tenantId)
                        ->where('employee_id', $request->employee_id)
                        ->where('claim_type_id', $type->id)
                        ->whereIn('status', ['approved', 'paid'])
                        ->whereMonth('claim_date', $claimMonth)
                        ->whereYear('claim_date', $claimYear)
                        ->sum('amount');

                    if (($monthlyUsed + $request->amount) > $type->max_amount_per_month) {
                        return response()->json([
                            'message' => 'Total pengajuan melebihi sisa plafon bulanan karyawan (Plafon Rp ' . number_format($type->max_amount_per_month, 0, ',', '.') . ', Terpakai: Rp ' . number_format($monthlyUsed, 0, ',', '.') . ')'
                        ], 422);
                    }
                }
            }
        }

        // Generate Code: CLM[YY][MM][0001]
        $prefix = 'CLM' . date('ym', strtotime($request->claim_date));
        $last = HrisClaim::where('tenant_id', $tenantId)
            ->where('code', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNum = 1;
        if ($last && preg_match('/' . $prefix . '(d+)/', $last->code, $matches)) {
            $nextNum = (int)$matches[1] + 1;
        }
        $code = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        $claim = HrisClaim::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'claim_type_id' => $request->claim_type_id,
            'code' => $code,
            'title' => $request->title ?? ($request->description ? substr($request->description, 0, 50) : 'Klaim Biaya'),
            'claim_date' => $request->claim_date,
            'amount' => $request->amount,
            'description' => $request->description,
            'receipt_base64' => $request->receipt_base64,
            'receipt_name' => $request->receipt_name,
            'status' => 'pending',
        ]);

        return response()->json($claim->load(['employee.user', 'claimType', 'approver']), 201);
    }

    public function approve(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $claim = HrisClaim::where('tenant_id', $tenantId)->findOrFail($id);

        $claim->status = 'approved';
        $claim->approved_by = $request->user()->id;
        $claim->approved_at = now();
        $claim->approver_note = $request->note ?? 'Disetujui oleh admin';
        $claim->save();

        return response()->json([
            'message' => 'Pengajuan klaim berhasil disetujui.',
            'data' => $claim->load(['employee.user', 'claimType', 'approver'])
        ]);
    }

    public function reject(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $claim = HrisClaim::where('tenant_id', $tenantId)->findOrFail($id);

        $claim->status = 'rejected';
        $claim->approved_by = $request->user()->id;
        $claim->approved_at = now();
        $claim->approver_note = $request->note ?? 'Ditolak';
        $claim->save();

        return response()->json([
            'message' => 'Pengajuan klaim ditolak.',
            'data' => $claim->load(['employee.user', 'claimType', 'approver'])
        ]);
    }

    public function markPaid(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $claim = HrisClaim::where('tenant_id', $tenantId)->findOrFail($id);

        $claim->status = 'paid';
        $claim->paid_at = now();
        $claim->paid_via = $request->paid_via ?? 'cash';
        $claim->approver_note = $request->note ?? 'Dibayar langsung via ' . ($request->paid_via ?? 'kas/transfer');
        if (!$claim->approved_by) {
            $claim->approved_by = $request->user()->id;
            $claim->approved_at = now();
        }
        $claim->save();

        return response()->json([
            'message' => 'Klaim berhasil ditandai telah dibayar.',
            'data' => $claim->load(['employee.user', 'claimType', 'approver'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $claim = HrisClaim::where('tenant_id', $tenantId)->findOrFail($id);
        $claim->delete();

        return response()->json(['message' => 'Data klaim berhasil dihapus.']);
    }

    // -------------------------------------------------------------
    // MASTER JENIS & ATURAN PLAFON REIMBURSEMENT
    // -------------------------------------------------------------
    public function getClaimTypes(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        // Seed default claim types if none exist
        $count = HrisClaimType::where('tenant_id', $tenantId)->count();
        if ($count === 0) {
            HrisClaimType::create([
                'tenant_id' => $tenantId,
                'code' => 'JR001',
                'name' => 'Transportasi',
                'description' => 'Transportasi yang bersifat keperluan perusahaan',
                'max_amount_per_claim' => 50000,
                'max_amount_per_month' => 0,
                'requires_receipt' => true,
                'is_active' => true,
            ]);
            HrisClaimType::create([
                'tenant_id' => $tenantId,
                'code' => 'SPPD',
                'name' => 'Penginapan',
                'description' => 'Penginapan & Akomodasi Perjalanan Dinas',
                'max_amount_per_claim' => 0,
                'max_amount_per_month' => 0,
                'requires_receipt' => true,
                'is_active' => true,
            ]);
            HrisClaimType::create([
                'tenant_id' => $tenantId,
                'code' => 'BBM',
                'name' => 'BBM / Fuel',
                'description' => 'Bahan Bakar Kendaraan Operasional & Dinas',
                'max_amount_per_claim' => 150000,
                'max_amount_per_month' => 1000000,
                'requires_receipt' => true,
                'is_active' => true,
            ]);
            HrisClaimType::create([
                'tenant_id' => $tenantId,
                'code' => 'MEDIS',
                'name' => 'Medis / Kesehatan',
                'description' => 'Klaim rawat jalan / obat-obatan',
                'max_amount_per_claim' => 500000,
                'max_amount_per_month' => 1500000,
                'requires_receipt' => true,
                'is_active' => true,
            ]);
        }

        $types = HrisClaimType::where('tenant_id', $tenantId)
            ->withCount('claims')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($types);
    }

    public function storeClaimType(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'max_amount_per_claim' => 'nullable|numeric|min:0',
            'max_amount_per_month' => 'nullable|numeric|min:0',
            'requires_receipt' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $claimType = HrisClaimType::updateOrCreate(
            ['tenant_id' => $tenantId, 'id' => $request->id],
            [
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'max_amount_per_claim' => $request->max_amount_per_claim ?? 0,
                'max_amount_per_month' => $request->max_amount_per_month ?? 0,
                'requires_receipt' => $request->requires_receipt ?? true,
                'is_active' => $request->is_active ?? true,
            ]
        );

        return response()->json($claimType, 201);
    }

    public function deleteClaimType(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $type = HrisClaimType::where('tenant_id', $tenantId)->findOrFail($id);
        $type->delete();

        return response()->json(['message' => 'Jenis klaim berhasil dihapus.']);
    }
}
