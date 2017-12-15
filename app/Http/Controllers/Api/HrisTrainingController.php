<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisTraining;
use App\Models\HrisTrainingParticipant;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisTrainingController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->isAdmin() ? $user->id : ($user->admin_id ?? $user->id);
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisTraining::where('tenant_id', $tenantId)
            ->with(['participants.employee.user']);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('trainer_name', 'like', "%{$search}%")
                  ->orWhere('location_or_link', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('training_date', 'desc')->get();
        return response()->json($items);
    }

    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisTraining::where('tenant_id', $tenantId);

        $totalTrainings = (clone $query)->count();
        $completedTrainings = (clone $query)->where('status', 'completed')->count();
        $plannedTrainings = (clone $query)->where('status', 'planned')->count();
        $ongoingTrainings = (clone $query)->where('status', 'ongoing')->count();

        $totalParticipants = HrisTrainingParticipant::whereHas('training', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->count();

        $passedParticipants = HrisTrainingParticipant::whereHas('training', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->where('passed', true)->count();

        return response()->json([
            'total_trainings' => $totalTrainings,
            'completed_count' => $completedTrainings,
            'planned_count' => $plannedTrainings,
            'ongoing_count' => $ongoingTrainings,
            'total_participants' => $totalParticipants,
            'passed_participants' => $passedParticipants,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'trainer_name' => 'required|string|max:100',
            'training_date' => 'required|date',
            'location_or_link' => 'nullable|string|max:255',
            'duration_hours' => 'required|integer|min:1|max:48',
            'status' => 'in:planned,ongoing,completed,cancelled',
            'description' => 'nullable|string',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $training = HrisTraining::create([
            'tenant_id' => $tenantId,
            'title' => $request->title,
            'category' => $request->category,
            'trainer_name' => $request->trainer_name,
            'training_date' => $request->training_date,
            'location_or_link' => $request->location_or_link,
            'duration_hours' => $request->duration_hours ?? 4,
            'status' => $request->status ?? 'planned',
            'description' => $request->description,
        ]);

        if ($request->has('participant_ids') && is_array($request->participant_ids)) {
            foreach ($request->participant_ids as $empId) {
                HrisTrainingParticipant::create([
                    'training_id' => $training->id,
                    'employee_id' => $empId,
                    'attendance_status' => 'registered',
                    'passed' => false,
                ]);
            }
        }

        return response()->json($training->load(['participants.employee.user']), 201);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $training = HrisTraining::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:150',
            'category' => 'required|string|max:50',
            'trainer_name' => 'required|string|max:100',
            'training_date' => 'required|date',
            'location_or_link' => 'nullable|string|max:255',
            'duration_hours' => 'required|integer|min:1|max:48',
            'status' => 'in:planned,ongoing,completed,cancelled',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $training->update($request->all());
        return response()->json($training->load(['participants.employee.user']));
    }

    public function addParticipants(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $training = HrisTraining::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->employee_ids as $empId) {
            HrisTrainingParticipant::firstOrCreate([
                'training_id' => $training->id,
                'employee_id' => $empId,
            ], [
                'attendance_status' => 'registered',
                'passed' => false,
            ]);
        }

        return response()->json($training->load(['participants.employee.user']));
    }

    public function updateParticipant(Request $request, $trainingId, $participantId)
    {
        $tenantId = $this->getTenantId($request);
        $training = HrisTraining::where('tenant_id', $tenantId)->findOrFail($trainingId);
        $participant = HrisTrainingParticipant::where('training_id', $training->id)->findOrFail($participantId);

        $validator = Validator::make($request->all(), [
            'attendance_status' => 'required|in:registered,attended,absent',
            'score' => 'nullable|integer|min:0|max:100',
            'passed' => 'boolean',
            'certificate_number' => 'nullable|string|max:100',
            'certificate_base64' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $passed = $request->boolean('passed', false);
        $certNumber = $request->certificate_number;
        if ($passed && empty($certNumber)) {
            $certNumber = sprintf("CERT/%s/%04d", date('Y', strtotime($training->training_date)), $participant->id);
        }

        $participant->update([
            'attendance_status' => $request->attendance_status,
            'score' => $request->score,
            'passed' => $passed,
            'certificate_number' => $certNumber,
            'certificate_base64' => $request->certificate_base64 ?? $participant->certificate_base64,
            'notes' => $request->notes,
        ]);

        return response()->json($participant->load('employee.user'));
    }

    public function removeParticipant(Request $request, $trainingId, $participantId)
    {
        $tenantId = $this->getTenantId($request);
        $training = HrisTraining::where('tenant_id', $tenantId)->findOrFail($trainingId);
        $participant = HrisTrainingParticipant::where('training_id', $training->id)->findOrFail($participantId);
        $participant->delete();

        return response()->json(['message' => 'Peserta berhasil dihapus dari pelatihan.']);
    }

    public function complete(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $training = HrisTraining::where('tenant_id', $tenantId)->findOrFail($id);

        $training->status = 'completed';
        $training->save();

        return response()->json([
            'message' => 'Pelatihan telah diselesaikan.',
            'data' => $training->load(['participants.employee.user'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $training = HrisTraining::where('tenant_id', $tenantId)->findOrFail($id);
        $training->delete();

        return response()->json(['message' => 'Data pelatihan berhasil dihapus.']);
    }
}
