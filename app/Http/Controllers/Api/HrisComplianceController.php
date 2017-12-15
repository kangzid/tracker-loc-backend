<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisComplianceItem;
use App\Models\Employee;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HrisComplianceController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->isAdmin() ? $user->id : ($user->admin_id ?? $user->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisComplianceItem::where('tenant_id', $tenantId);

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->target_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('doc_name', 'like', "%{$search}%")
                  ->orWhere('doc_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('expiry_date', 'asc')->get();

        // Update dynamic status & load polymorphic entity
        foreach ($items as $item) {
            $calculatedStatus = $item->calculateCurrentStatus();
            if ($item->status !== $calculatedStatus) {
                $item->status = $calculatedStatus;
                $item->save();
            }

            if ($item->target_type === 'employee') {
                $item->load('employee.user');
            } elseif ($item->target_type === 'vehicle') {
                $item->load('vehicle');
            }
        }

        return response()->json($items);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $items = HrisComplianceItem::where('tenant_id', $tenantId)->get();

        $total = $items->count();
        $safe = 0;
        $warning = 0;
        $expired = 0;
        $employeeDocs = 0;
        $vehicleDocs = 0;

        foreach ($items as $item) {
            $st = $item->calculateCurrentStatus();
            if ($st === 'expired') $expired++;
            elseif ($st === 'warning') $warning++;
            else $safe++;

            if ($item->target_type === 'employee') $employeeDocs++;
            else $vehicleDocs++;
        }

        return response()->json([
            'total_items' => $total,
            'safe_count' => $safe,
            'warning_count' => $warning,
            'expired_count' => $expired,
            'employee_docs_count' => $employeeDocs,
            'vehicle_docs_count' => $vehicleDocs,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'target_type' => 'required|in:employee,vehicle',
            'target_id' => 'required|integer',
            'doc_name' => 'required|string|max:100',
            'doc_number' => 'nullable|string|max:100',
            'expiry_date' => 'required|date',
            'reminder_days_before' => 'nullable|integer|min:1|max:365',
            'document_base64' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify target existence
        if ($request->target_type === 'employee') {
            Employee::where('id', $request->target_id)->firstOrFail();
        } else {
            Vehicle::where('id', $request->target_id)->firstOrFail();
        }

        $item = new HrisComplianceItem([
            'tenant_id' => $tenantId,
            'target_type' => $request->target_type,
            'target_id' => $request->target_id,
            'doc_name' => $request->doc_name,
            'doc_number' => $request->doc_number,
            'expiry_date' => $request->expiry_date,
            'reminder_days_before' => $request->reminder_days_before ?? 30,
            'document_base64' => $request->document_base64,
            'notes' => $request->notes,
        ]);

        $item->status = $item->calculateCurrentStatus();
        $item->save();

        if ($item->target_type === 'employee') {
            $item->load('employee.user');
        } else {
            $item->load('vehicle');
        }

        return response()->json($item, 201);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $item = HrisComplianceItem::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'doc_name' => 'required|string|max:100',
            'doc_number' => 'nullable|string|max:100',
            'expiry_date' => 'required|date',
            'reminder_days_before' => 'nullable|integer|min:1|max:365',
            'document_base64' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $item->update($request->only([
            'doc_name', 'doc_number', 'expiry_date', 'reminder_days_before', 'notes'
        ]));

        if ($request->filled('document_base64')) {
            $item->document_base64 = $request->document_base64;
        }

        $item->status = $item->calculateCurrentStatus();
        $item->save();

        if ($item->target_type === 'employee') {
            $item->load('employee.user');
        } else {
            $item->load('vehicle');
        }

        return response()->json($item);
    }

    public function renew(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $item = HrisComplianceItem::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'new_expiry_date' => 'required|date|after:today',
            'new_doc_number' => 'nullable|string|max:100',
            'document_base64' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $item->expiry_date = $request->new_expiry_date;
        if ($request->filled('new_doc_number')) {
            $item->doc_number = $request->new_doc_number;
        }
        if ($request->filled('document_base64')) {
            $item->document_base64 = $request->document_base64;
        }
        if ($request->filled('notes')) {
            $item->notes = $request->notes;
        }

        $item->renewed_at = now()->toDateString();
        $item->status = $item->calculateCurrentStatus();
        $item->save();

        if ($item->target_type === 'employee') {
            $item->load('employee.user');
        } else {
            $item->load('vehicle');
        }

        return response()->json([
            'message' => 'Dokumen legalitas kepatuhan berhasil diperpanjang.',
            'data' => $item
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $item = HrisComplianceItem::where('tenant_id', $tenantId)->findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Item kepatuhan berhasil dihapus.']);
    }
}
