<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisComplianceItem;
use App\Models\HrisComplianceDocType;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Services\EncryptedStorageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisComplianceController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->role === 'superadmin' ? ($user->tenant_id ?? 1) : ($user->admin_id ?? $user->id);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $today = Carbon::today();

        $items = HrisComplianceItem::where('tenant_id', $tenantId)->get();

        $totalItems = $items->count();
        $safeCount = 0;
        $warningCount = 0;
        $expiredCount = 0;
        $employeeCount = 0;
        $vehicleCount = 0;

        foreach ($items as $item) {
            $status = $item->calculateCurrentStatus();
            if ($status === 'safe') $safeCount++;
            elseif ($status === 'warning') $warningCount++;
            else $expiredCount++;

            if ($item->target_type === 'employee') $employeeCount++;
            elseif ($item->target_type === 'vehicle') $vehicleCount++;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_items' => $totalItems,
                'safe_count' => $safeCount,
                'warning_count' => $warningCount,
                'expired_count' => $expiredCount,
                'employee_count' => $employeeCount,
                'vehicle_count' => $vehicleCount,
            ]
        ]);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $query = HrisComplianceItem::where('tenant_id', $tenantId)
            ->with(['employee.user', 'vehicle'])
            ->orderBy('expiry_date', 'asc');

        if ($request->has('target_type') && $request->target_type && $request->target_type !== 'all') {
            $query->where('target_type', $request->target_type);
        }

        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('doc_name', 'like', "%{$s}%")
                    ->orWhere('doc_number', 'like', "%{$s}%")
                    ->orWhereHas('employee.user', function ($uq) use ($s) {
                        $uq->where('name', 'like', "%{$s}%");
                    })
                    ->orWhereHas('vehicle', function ($vq) use ($s) {
                        $vq->where('license_plate', 'like', "%{$s}%")
                           ->orWhere('name', 'like', "%{$s}%");
                    });
            });
        }

        $items = $query->get()->map(function ($item) {
            $calculatedStatus = $item->calculateCurrentStatus();
            if ($item->status !== $calculatedStatus) {
                $item->status = $calculatedStatus;
                $item->save();
            }
            return $item;
        });

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'target_type' => 'required|in:employee,vehicle',
            'target_id' => 'required|integer',
            'doc_name' => 'required|string|max:150',
            'doc_number' => 'nullable|string|max:100',
            'expiry_date' => 'required|date',
            'reminder_days_before' => 'nullable|integer',
            'notes' => 'nullable|string',
            'document_file' => 'nullable|file|max:10240',
            'document_base64' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $docPath = null;
        $targetPrefix = $request->target_type . '_' . $request->target_id;

        if ($request->hasFile('document_file')) {
            $stored = EncryptedStorageService::storeEncrypted(
                $request->file('document_file'),
                $tenantId,
                'compliance',
                'comp_' . $targetPrefix
            );
            $docPath = $stored['path'];
        } elseif ($request->filled('document_base64')) {
            $stored = EncryptedStorageService::storeEncrypted(
                $request->document_base64,
                $tenantId,
                'compliance',
                'comp_' . $targetPrefix,
                $request->doc_name . '.pdf'
            );
            $docPath = $stored['path'];
        }

        // Calculate dynamic status
        $today = Carbon::today();
        $expiry = Carbon::parse($request->expiry_date);
        $diffDays = $today->diffInDays($expiry, false);
        $reminderDays = (int)($request->reminder_days_before ?? 30);

        $status = 'safe';
        if ($diffDays < 0) {
            $status = 'expired';
        } elseif ($diffDays <= $reminderDays) {
            $status = 'warning';
        }

        $item = HrisComplianceItem::create([
            'tenant_id' => $tenantId,
            'target_type' => $request->target_type,
            'target_id' => $request->target_id,
            'doc_name' => $request->doc_name,
            'doc_number' => $request->doc_number,
            'expiry_date' => $request->expiry_date,
            'status' => $status,
            'reminder_days_before' => $reminderDays,
            'document_path' => $docPath,
            'notes' => $request->notes,
        ]);

        return response()->json($item->load(['employee.user', 'vehicle']), 201);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $item = HrisComplianceItem::where('tenant_id', $tenantId)
            ->with(['employee.user', 'vehicle'])
            ->findOrFail($id);

        return response()->json($item);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $item = HrisComplianceItem::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'target_type' => 'nullable|in:employee,vehicle',
            'target_id' => 'nullable|integer',
            'doc_name' => 'required|string|max:150',
            'doc_number' => 'nullable|string|max:100',
            'expiry_date' => 'required|date',
            'reminder_days_before' => 'nullable|integer',
            'notes' => 'nullable|string',
            'document_file' => 'nullable|file|max:10240',
            'document_base64' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $targetType = $request->target_type ?? $item->target_type;
        $targetId = $request->target_id ?? $item->target_id;
        $targetPrefix = $targetType . '_' . $targetId;

        if ($request->hasFile('document_file')) {
            $stored = EncryptedStorageService::storeEncrypted(
                $request->file('document_file'),
                $tenantId,
                'compliance',
                'comp_' . $targetPrefix
            );
            $item->document_path = $stored['path'];
        } elseif ($request->filled('document_base64')) {
            $stored = EncryptedStorageService::storeEncrypted(
                $request->document_base64,
                $tenantId,
                'compliance',
                'comp_' . $targetPrefix,
                $request->doc_name . '.pdf'
            );
            $item->document_path = $stored['path'];
        }

        if ($request->has('target_type')) $item->target_type = $request->target_type;
        if ($request->has('target_id')) $item->target_id = $request->target_id;
        $item->doc_name = $request->doc_name;
        $item->doc_number = $request->doc_number;
        $item->expiry_date = $request->expiry_date;
        $item->reminder_days_before = (int)($request->reminder_days_before ?? $item->reminder_days_before ?? 30);
        $item->notes = $request->notes;
        $item->status = $item->calculateCurrentStatus();
        $item->save();

        return response()->json($item->load(['employee.user', 'vehicle']));
    }

    public function renew(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $item = HrisComplianceItem::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'new_expiry_date' => 'required|date',
            'new_doc_number' => 'nullable|string|max:100',
            'document_file' => 'nullable|file|max:10240',
            'document_base64' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $targetPrefix = $item->target_type . '_' . $item->target_id;

        if ($request->hasFile('document_file')) {
            $stored = EncryptedStorageService::storeEncrypted(
                $request->file('document_file'),
                $tenantId,
                'compliance',
                'comp_renew_' . $targetPrefix
            );
            $item->document_path = $stored['path'];
        } elseif ($request->filled('document_base64')) {
            $stored = EncryptedStorageService::storeEncrypted(
                $request->document_base64,
                $tenantId,
                'compliance',
                'comp_renew_' . $targetPrefix,
                $item->doc_name . '_renewed.pdf'
            );
            $item->document_path = $stored['path'];
        }

        $item->expiry_date = $request->new_expiry_date;
        if ($request->filled('new_doc_number')) {
            $item->doc_number = $request->new_doc_number;
        }
        $item->renewed_at = Carbon::today();
        if ($request->filled('notes')) {
            $item->notes = ($item->notes ? $item->notes . "\n" : "") . $request->notes;
        }
        $item->status = $item->calculateCurrentStatus();
        $item->save();

        return response()->json($item->load(['employee.user', 'vehicle']));
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $item = HrisComplianceItem::where('tenant_id', $tenantId)->findOrFail($id);
        
        if (!empty($item->document_path)) {
            EncryptedStorageService::deleteFile($item->document_path);
        }

        $item->delete();
        return response()->json(['message' => 'Dokumen legalitas berhasil dihapus.']);
    }

    public function previewDoc(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $item = HrisComplianceItem::where('tenant_id', $tenantId)->findOrFail($id);
        if (!$item->document_path) {
            return response()->json(['message' => 'Berkas legalitas tidak ditemukan.'], 404);
        }
        return EncryptedStorageService::streamResponse($item->document_path, $item->doc_name, false);
    }

    public function downloadDoc(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $item = HrisComplianceItem::where('tenant_id', $tenantId)->findOrFail($id);
        if (!$item->document_path) {
            return response()->json(['message' => 'Berkas legalitas tidak ditemukan.'], 404);
        }
        return EncryptedStorageService::streamResponse($item->document_path, $item->doc_name, true);
    }
}
