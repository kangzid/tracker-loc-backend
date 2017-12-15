<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisClaim;
use App\Models\HrisClaimType;
use App\Services\EncryptedStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisClaimController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->role === 'superadmin' ? ($user->tenant_id ?? 1) : ($user->admin_id ?? $user->id);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $month = $request->query('month');
        $year = $request->query('year', date('Y'));

        $query = HrisClaim::where('tenant_id', $tenantId);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($month && $month !== 'all') {
            $query->where(function($q) use ($month) {
                $q->whereMonth('claim_date', $month)->orWhereMonth('created_at', $month);
            });
        }
        if ($year && $year !== 'all') {
            $query->where(function($q) use ($year) {
                $q->whereYear('claim_date', $year)->orWhereYear('created_at', $year);
            });
        }

        // Calculations
        $totalApprovedAmount = (float) (clone $query)->whereIn('status', ['approved', 'paid'])->sum('amount');
        $approvedCount = (int) (clone $query)->where('status', 'approved')->count();

        $totalPaidAmount = (float) (clone $query)->where('status', 'paid')->sum('amount');
        $paidCount = (int) (clone $query)->where('status', 'paid')->count();

        $totalPendingAmount = (float) (clone $query)->where('status', 'pending')->sum('amount');
        $pendingCount = (int) (clone $query)->where('status', 'pending')->count();

        $totalRejectedAmount = (float) (clone $query)->where('status', 'rejected')->sum('amount');
        $rejectedCount = (int) (clone $query)->where('status', 'rejected')->count();

        return response()->json([
            // Svelte Frontend fields
            'total_approved_amount' => $totalApprovedAmount,
            'total_paid_amount' => $totalPaidAmount,
            'total_pending_amount' => $totalPendingAmount,
            'total_rejected_amount' => $totalRejectedAmount,
            'approved_count' => $approvedCount,
            'paid_count' => $paidCount,
            'pending_count' => $pendingCount,
            'rejected_count' => $rejectedCount,

            // Flat aliases for mobile & API
            'total_approved' => $totalApprovedAmount,
            'total_paid' => $totalPaidAmount,
            'total_pending' => $totalPendingAmount,
            'total_rejected' => $totalRejectedAmount,
            'status' => 'success',
            'data' => [
                'total_approved' => $totalApprovedAmount,
                'total_approved_amount' => $totalApprovedAmount,
                'approved_count' => $approvedCount,
                'total_paid' => $totalPaidAmount,
                'total_paid_amount' => $totalPaidAmount,
                'paid_count' => $paidCount,
                'total_pending' => $totalPendingAmount,
                'total_pending_amount' => $totalPendingAmount,
                'pending_count' => $pendingCount,
                'total_rejected' => $totalRejectedAmount,
                'total_rejected_amount' => $totalRejectedAmount,
                'rejected_count' => $rejectedCount,
            ]
        ]);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $query = HrisClaim::where('tenant_id', $tenantId)
            ->with(['employee.user', 'claimType', 'approver'])
            ->orderBy('id', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('claim_type_id') && $request->claim_type_id) {
            $query->where('claim_type_id', $request->claim_type_id);
        }

        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('month') && $request->month && $request->month !== 'all') {
            $month = $request->month;
            $query->where(function($q) use ($month) {
                $q->whereMonth('claim_date', $month)->orWhereMonth('created_at', $month);
            });
        }

        if ($request->has('year') && $request->year && $request->year !== 'all') {
            $year = $request->year;
            $query->where(function($q) use ($year) {
                $q->whereYear('claim_date', $year)->orWhereYear('created_at', $year);
            });
        }

        if ($request->has('search') && $request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('code', 'like', "%{$s}%")
                    ->orWhere('title', 'like', "%{$s}%")
                    ->orWhereHas('employee.user', function ($uq) use ($s) {
                        $uq->where('name', 'like', "%{$s}%");
                    });
            });
        }

        return response()->json($query->paginate($request->get('per_page', 15)));
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'claim_type_id' => 'required|exists:hris_claim_types,id',
            'claim_date' => 'required|date',
            'amount' => 'required|numeric|min:1',
            'title' => 'nullable|string|max:150',
            'description' => 'nullable|string',
            'receipt_file' => 'nullable|file|max:10240',
            'receipt_base64' => 'nullable|string',
            'receipt_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if receipt is required by claim type
        $type = HrisClaimType::where('tenant_id', $tenantId)->find($request->claim_type_id);
        if ($type) {
            if ($type->requires_receipt) {
                $hasReceipt = $request->hasFile('receipt_file') || $request->filled('receipt_base64');
                if (!$hasReceipt) {
                    return response()->json([
                        'message' => 'Jenis klaim ' . $type->name . ' mewajibkan lampiran foto bukti nota atau struk pembayaran.'
                    ], 422);
                }
            }

            // Check Plafon Rules
            if ($type->max_amount_per_claim > 0 && $request->amount > $type->max_amount_per_claim) {
                return response()->json([
                    'message' => 'Nominal melebihi batas maksimum per klaim (Maks Rp ' . number_format($type->max_amount_per_claim, 0, ',', '.') . ')'
                ], 422);
            }

            if ($type->max_amount_per_month > 0) {
                $claimMonth = date('m', strtotime($request->claim_date));
                $claimYear = date('Y', strtotime($request->claim_date));

                $monthlyUsed = HrisClaim::where('tenant_id', $tenantId)
                    ->where('employee_id', $request->employee_id)
                    ->where('claim_type_id', $request->claim_type_id)
                    ->whereIn('status', ['approved', 'paid', 'pending'])
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

        // Generate Code: CLM[YY][MM][0001]
        $prefix = 'CLM' . date('ym', strtotime($request->claim_date));
        $last = HrisClaim::where('tenant_id', $tenantId)
            ->where('code', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNum = 1;
        if ($last && preg_match('/' . $prefix . '([0-9]+)/', $last->code, $matches)) {
            $nextNum = (int)$matches[1] + 1;
        }
        $code = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        // Handle receipt storage with AES-256
        $receiptPath = null;
        $receiptName = $request->receipt_name;
        if ($request->hasFile('receipt_file')) {
            $stored = EncryptedStorageService::storeEncrypted($request->file('receipt_file'), $tenantId, 'claims', 'claim_' . $code);
            $receiptPath = $stored['path'];
            $receiptName = $stored['name'];
        } elseif ($request->filled('receipt_base64')) {
            $stored = EncryptedStorageService::storeEncrypted($request->receipt_base64, $tenantId, 'claims', 'claim_' . $code, $receiptName);
            $receiptPath = $stored['path'];
            $receiptName = $stored['name'];
        }

        $claim = HrisClaim::create([
            'tenant_id' => $tenantId,
            'employee_id' => $request->employee_id,
            'claim_type_id' => $request->claim_type_id,
            'code' => $code,
            'title' => $request->title ?? ($request->description ? substr($request->description, 0, 50) : 'Klaim Biaya'),
            'claim_date' => $request->claim_date,
            'amount' => $request->amount,
            'description' => $request->description,
            'receipt_path' => $receiptPath,
            'receipt_name' => $receiptName,
            'status' => 'pending',
        ]);

        return response()->json($claim->load(['employee.user', 'claimType', 'approver']), 201);
    }

    public function previewReceipt(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $claim = HrisClaim::where('tenant_id', $tenantId)->findOrFail($id);
        if (!$claim->receipt_path) {
            return response()->json(['message' => 'Berkas kwitansi tidak ditemukan.'], 404);
        }
        return EncryptedStorageService::streamResponse($claim->receipt_path, $claim->receipt_name, false);
    }

    public function downloadReceipt(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $claim = HrisClaim::where('tenant_id', $tenantId)->findOrFail($id);
        if (!$claim->receipt_path) {
            return response()->json(['message' => 'Berkas kwitansi tidak ditemukan.'], 404);
        }
        return EncryptedStorageService::streamResponse($claim->receipt_path, $claim->receipt_name, true);
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

        if ($claim->receipt_path) {
            try {
                EncryptedStorageService::deleteFile($claim->receipt_path);
            } catch (\Exception $e) {
                \Log::warning('Failed deleting claim receipt file: ' . $e->getMessage());
            }
        }

        $claim->delete();

        return response()->json(['message' => 'Data klaim dan berkas bukti berhasil dihapus.']);
    }

    // MASTER JENIS & ATURAN PLAFON
    public function getClaimTypes(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $types = HrisClaimType::where('tenant_id', $tenantId)->withCount('claims')->orderBy('id', 'asc')->get();
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
