<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisTraining;
use App\Models\HrisTrainingCategory;
use App\Models\HrisTrainingParticipant;
use App\Models\Employee;
use App\Services\EncryptedStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HrisTrainingController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->role === 'superadmin' ? ($user->tenant_id ?? 1) : ($user->admin_id ?? $user->id);
    }

    /**
     * List all trainings with participants
     */
    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $query = HrisTraining::where('tenant_id', $tenantId)
            ->with(['participants.employee.user'])
            ->orderBy('id', 'desc');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('trainer_name', 'like', "%{$s}%")
                    ->orWhere('category', 'like', "%{$s}%")
                    ->orWhere('location_or_link', 'like', "%{$s}%");
            });
        }

        $trainings = $query->get();

        return response()->json($trainings);
    }

    /**
     * Training Statistics Summary for Dashboard
     */
    public function summary(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $trainings = HrisTraining::where('tenant_id', $tenantId)->with('participants')->get();

        $totalTrainings = $trainings->count();
        $completedCount = $trainings->where('status', 'completed')->count();
        $plannedCount = $trainings->where('status', 'planned')->count();
        $ongoingCount = $trainings->where('status', 'ongoing')->count();

        $totalParticipants = 0;
        $passedParticipants = 0;

        foreach ($trainings as $t) {
            $totalParticipants += $t->participants->count();
            $passedParticipants += $t->participants->where('passed', true)->count();
        }

        return response()->json([
            'total_trainings' => $totalTrainings,
            'completed_count' => $completedCount,
            'planned_count' => $plannedCount,
            'ongoing_count' => $ongoingCount,
            'total_participants' => $totalParticipants,
            'passed_participants' => $passedParticipants,
        ]);
    }

    /**
     * Create a new training program
     */
    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:150',
            'category' => 'nullable|string|max:100',
            'trainer_name' => 'nullable|string|max:100',
            'training_date' => 'required|date',
            'location_or_link' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'duration_hours' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:planned,ongoing,completed,cancelled',
            'description' => 'nullable|string',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'integer|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $training = HrisTraining::create([
            'tenant_id' => $tenantId,
            'title' => $request->title,
            'category' => $request->category ?? 'Safety & Defensive Driving',
            'trainer_name' => $request->trainer_name ?? '-',
            'training_date' => $request->training_date,
            'location_or_link' => $request->location_or_link ?? $request->location ?? '-',
            'duration_hours' => $request->duration_hours ?? 4,
            'status' => $request->status ?? 'planned',
            'description' => $request->description,
        ]);

        if ($request->has('participant_ids') && is_array($request->participant_ids)) {
            foreach ($request->participant_ids as $empId) {
                HrisTrainingParticipant::firstOrCreate(
                    [
                        'training_id' => $training->id,
                        'employee_id' => $empId,
                    ],
                    [
                        'attendance_status' => 'registered',
                        'passed' => false,
                    ]
                );
            }
        }

        return response()->json($training->load(['participants.employee.user']), 201);
    }

    /**
     * Update training program details
     */
    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $training = HrisTraining::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:150',
            'category' => 'nullable|string|max:100',
            'trainer_name' => 'nullable|string|max:100',
            'training_date' => 'nullable|date',
            'location_or_link' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'duration_hours' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:planned,ongoing,completed,cancelled',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [];
        if ($request->has('title')) $data['title'] = $request->title;
        if ($request->has('category')) $data['category'] = $request->category;
        if ($request->has('trainer_name')) $data['trainer_name'] = $request->trainer_name;
        if ($request->has('training_date')) $data['training_date'] = $request->training_date;
        if ($request->has('location_or_link')) $data['location_or_link'] = $request->location_or_link;
        if ($request->has('location')) $data['location_or_link'] = $request->location;
        if ($request->has('duration_hours')) $data['duration_hours'] = $request->duration_hours;
        if ($request->has('status')) $data['status'] = $request->status;
        if ($request->has('description')) $data['description'] = $request->description;

        $training->update($data);

        return response()->json($training->load(['participants.employee.user']));
    }

    /**
     * Delete training
     */
    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $training = HrisTraining::where('tenant_id', $tenantId)->findOrFail($id);
        $training->delete();

        return response()->json(['message' => 'Jadwal pelatihan berhasil dihapus.']);
    }

    /**
     * Add participants to a training
     */
    public function addParticipants(Request $request, $trainingId)
    {
        $tenantId = $this->getTenantId($request);
        $training = HrisTraining::where('tenant_id', $tenantId)->findOrFail($trainingId);

        $empIds = $request->input('employee_ids', []);
        if ($request->filled('employee_id')) {
            $empIds[] = $request->input('employee_id');
        }

        foreach (array_unique($empIds) as $empId) {
            HrisTrainingParticipant::firstOrCreate(
                [
                    'training_id' => $training->id,
                    'employee_id' => $empId,
                ],
                [
                    'attendance_status' => 'registered',
                    'passed' => false,
                ]
            );
        }

        return response()->json($training->load(['participants.employee.user']));
    }

    /**
     * Update participant progress/score
     */
    public function updateParticipant(Request $request, $trainingId, $participantId)
    {
        $tenantId = $this->getTenantId($request);
        $training = HrisTraining::where('tenant_id', $tenantId)->findOrFail($trainingId);
        $participant = HrisTrainingParticipant::where('training_id', $training->id)->findOrFail($participantId);

        $validator = Validator::make($request->all(), [
            'attendance_status' => 'nullable|string|in:registered,attended,absent',
            'score' => 'nullable|numeric|min:0|max:100',
            'passed' => 'nullable|boolean',
            'certificate_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'certificate_file' => 'nullable|file|max:10240',
            'certificate_base64' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [];
        if ($request->has('attendance_status')) $data['attendance_status'] = $request->attendance_status;
        if ($request->has('score')) {
            $data['score'] = $request->score;
            if (!$request->has('passed')) {
                $data['passed'] = $request->score >= 70;
            }
        }
        if ($request->has('passed')) $data['passed'] = $request->passed;
        if ($request->has('certificate_number')) $data['certificate_number'] = $request->certificate_number;
        if ($request->has('notes')) $data['notes'] = $request->notes;

        if ($request->hasFile('certificate_file')) {
            $stored = EncryptedStorageService::storeEncrypted($request->file('certificate_file'), $tenantId, 'training', 'cert_' . $participant->employee_id);
            $data['certificate_path'] = $stored['path'];
            $data['certificate_name'] = $stored['name'];
        } elseif ($request->filled('certificate_base64')) {
            $stored = EncryptedStorageService::storeEncrypted($request->certificate_base64, $tenantId, 'training', 'cert_' . $participant->employee_id);
            $data['certificate_path'] = $stored['path'];
            $data['certificate_name'] = $stored['name'];
        }

        $participant->update($data);

        return response()->json($participant->load(['employee.user']));
    }

    /**
     * Remove a participant from training
     */
    public function removeParticipant(Request $request, $trainingId, $participantId)
    {
        $tenantId = $this->getTenantId($request);
        $training = HrisTraining::where('tenant_id', $tenantId)->findOrFail($trainingId);
        $participant = HrisTrainingParticipant::where('training_id', $training->id)->findOrFail($participantId);
        $participant->delete();

        return response()->json(['message' => 'Peserta berhasil dihapus dari pelatihan.']);
    }

    /**
     * Preview encrypted certificate
     */
    public function previewCert(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $part = HrisTrainingParticipant::whereHas('training', function($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->findOrFail($id);

        if (!$part->certificate_path) {
            return response()->json(['message' => 'Sertifikat pelatihan tidak ditemukan.'], 404);
        }
        return EncryptedStorageService::streamResponse($part->certificate_path, $part->certificate_name, false);
    }

    /**
     * Download encrypted certificate
     */
    public function downloadCert(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $part = HrisTrainingParticipant::whereHas('training', function($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->findOrFail($id);

        if (!$part->certificate_path) {
            return response()->json(['message' => 'Sertifikat pelatihan tidak ditemukan.'], 404);
        }
        return EncryptedStorageService::streamResponse($part->certificate_path, $part->certificate_name, true);
    }

    /**
     * Complete a training session
     */
    public function complete(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $training = HrisTraining::where('tenant_id', $tenantId)->findOrFail($id);
        $training->update(['status' => 'completed']);

        return response()->json($training->load(['participants.employee.user']));
    }
}
