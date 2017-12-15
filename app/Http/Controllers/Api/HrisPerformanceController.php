<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisPerformanceReview;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisPerformanceController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        return $user->isAdmin() ? $user->id : ($user->admin_id ?? $user->id);
    }

    private function calculateGradeAndScore($att, $task, $disc, $team)
    {
        // Weighted average: Attendance (25%), Task Completion (35%), Discipline (20%), Teamwork (20%)
        $score = round(($att * 0.25) + ($task * 0.35) + ($disc * 0.20) + ($team * 0.20), 2);
        
        if ($score >= 4.50) $grade = 'A';
        elseif ($score >= 3.50) $grade = 'B';
        elseif ($score >= 2.50) $grade = 'C';
        elseif ($score >= 1.50) $grade = 'D';
        else $grade = 'E';

        return [$score, $grade];
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $query = HrisPerformanceReview::where('tenant_id', $tenantId)
            ->with(['employee.user', 'reviewer']);

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        if ($request->filled('target_type') && in_array($request->target_type, ['employee', 'department'])) {
            $query->where('target_type', $request->target_type);
        }

        if ($request->filled('department')) {
            $dept = $request->department;
            $query->where(function ($q) use ($dept) {
                $q->where('department', $dept)
                  ->orWhereHas('employee', function ($eq) use ($dept) {
                      $eq->where('department', $dept);
                  });
            });
        }

        if ($request->filled('grade')) {
            $query->where('grade', $request->grade);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('department', 'like', "%{$search}%")
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
        $query = HrisPerformanceReview::where('tenant_id', $tenantId);

        $totalReviews = (clone $query)->count();
        $avgScore = round((float)(clone $query)->where('score', '>', 0)->avg('score'), 2);
        $gradeA = (clone $query)->where('grade', 'A')->count();
        $gradeB = (clone $query)->where('grade', 'B')->count();
        $gradeC = (clone $query)->where('grade', 'C')->count();
        $gradeD = (clone $query)->where('grade', 'D')->count();
        $finalizedCount = (clone $query)->where('status', 'finalized')->count();
        $draftCount = (clone $query)->where('status', 'draft')->count();
        $departmentReviewsCount = (clone $query)->where('target_type', 'department')->count();

        return response()->json([
            'total_reviews' => $totalReviews,
            'avg_score' => $avgScore > 0 ? $avgScore : 0,
            'grade_a_count' => $gradeA,
            'grade_b_count' => $gradeB,
            'grade_c_count' => $gradeC,
            'grade_d_count' => $gradeD,
            'finalized_count' => $finalizedCount,
            'draft_count' => $draftCount,
            'department_reviews_count' => $departmentReviewsCount,
        ]);
    }

    public function departmentStats(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $department = $request->query('department');
        $period = $request->query('period');

        if (!$department) {
            return response()->json(['error' => 'Department is required'], 400);
        }

        // Find all employee reviews in this department for this period
        $query = HrisPerformanceReview::where('tenant_id', $tenantId)
            ->where('target_type', 'employee')
            ->whereHas('employee', function ($q) use ($department) {
                $q->where('department', $department);
            });

        if ($period) {
            $query->where('period', $period);
        }

        $reviews = $query->get();
        $count = $reviews->count();

        if ($count === 0) {
            return response()->json([
                'count' => 0,
                'attendance_score' => 4.0,
                'task_completion_score' => 4.0,
                'discipline_score' => 4.0,
                'teamwork_score' => 4.0,
                'score' => 4.0,
                'grade' => 'B',
                'message' => 'Belum ada review karyawan pada periode ini. Nilai default disediakan.'
            ]);
        }

        $att = round($reviews->avg('attendance_score'), 2);
        $task = round($reviews->avg('task_completion_score'), 2);
        $disc = round($reviews->avg('discipline_score'), 2);
        $team = round($reviews->avg('teamwork_score'), 2);

        list($score, $grade) = $this->calculateGradeAndScore($att, $task, $disc, $team);

        return response()->json([
            'count' => $count,
            'attendance_score' => $att,
            'task_completion_score' => $task,
            'discipline_score' => $disc,
            'teamwork_score' => $team,
            'score' => $score,
            'grade' => $grade,
            'message' => "Otomatis dihitung dari {$count} evaluasi karyawan di departemen {$department}."
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'target_type' => 'required|in:employee,department',
            'employee_id' => 'required_if:target_type,employee|nullable|exists:employees,id',
            'department' => 'required_if:target_type,department|nullable|string|max:100',
            'period' => 'required|string|max:50',
            'attendance_score' => 'required|numeric|min:1|max:5',
            'task_completion_score' => 'required|numeric|min:1|max:5',
            'discipline_score' => 'required|numeric|min:1|max:5',
            'teamwork_score' => 'required|numeric|min:1|max:5',
            'remarks' => 'nullable|string',
            'status' => 'in:draft,finalized',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        list($score, $grade) = $this->calculateGradeAndScore(
            (float)$request->attendance_score,
            (float)$request->task_completion_score,
            (float)$request->discipline_score,
            (float)$request->teamwork_score
        );

        $targetType = $request->target_type;
        $deptName = $request->department;
        if ($targetType === 'employee' && $request->filled('employee_id')) {
            $emp = Employee::find($request->employee_id);
            $deptName = $emp ? $emp->department : null;
        }

        $review = HrisPerformanceReview::create([
            'tenant_id' => $tenantId,
            'target_type' => $targetType,
            'employee_id' => $targetType === 'employee' ? $request->employee_id : null,
            'department' => $deptName,
            'period' => $request->period,
            'score' => $score,
            'grade' => $grade,
            'attendance_score' => $request->attendance_score,
            'task_completion_score' => $request->task_completion_score,
            'discipline_score' => $request->discipline_score,
            'teamwork_score' => $request->teamwork_score,
            'remarks' => $request->remarks,
            'reviewer_id' => $request->user()->id,
            'status' => $request->status ?? 'draft',
        ]);

        return response()->json($review->load(['employee.user', 'reviewer']), 201);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $review = HrisPerformanceReview::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'period' => 'required|string|max:50',
            'attendance_score' => 'required|numeric|min:1|max:5',
            'task_completion_score' => 'required|numeric|min:1|max:5',
            'discipline_score' => 'required|numeric|min:1|max:5',
            'teamwork_score' => 'required|numeric|min:1|max:5',
            'remarks' => 'nullable|string',
            'department' => 'nullable|string|max:100',
            'status' => 'in:draft,finalized',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        list($score, $grade) = $this->calculateGradeAndScore(
            (float)$request->attendance_score,
            (float)$request->task_completion_score,
            (float)$request->discipline_score,
            (float)$request->teamwork_score
        );

        $review->update([
            'period' => $request->period,
            'department' => $request->department ?? $review->department,
            'score' => $score,
            'grade' => $grade,
            'attendance_score' => $request->attendance_score,
            'task_completion_score' => $request->task_completion_score,
            'discipline_score' => $request->discipline_score,
            'teamwork_score' => $request->teamwork_score,
            'remarks' => $request->remarks,
            'status' => $request->status ?? $review->status,
        ]);

        return response()->json($review->load(['employee.user', 'reviewer']));
    }

    public function finalizeReview(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $review = HrisPerformanceReview::where('tenant_id', $tenantId)->findOrFail($id);

        $review->status = 'finalized';
        $review->save();

        return response()->json([
            'message' => 'Review kinerja berhasil difinalisasi.',
            'data' => $review->load(['employee.user', 'reviewer'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $review = HrisPerformanceReview::where('tenant_id', $tenantId)->findOrFail($id);
        $review->delete();

        return response()->json(['message' => 'Data review kinerja berhasil dihapus.']);
    }
}
