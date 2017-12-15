<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrisShift;
use App\Models\HrisShiftAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class HrisShiftController extends Controller
{
    /**
     * Get all master shifts for current tenant
     */
    public function index(Request $request)
    {
        $adminId = $request->user()->isAdmin() ? $request->user()->id : ($request->user()->employee ? $request->user()->employee->admin_id : null);
        if (!$adminId) {
            return response()->json(['message' => 'Unauthorized or tenant not found'], 403);
        }

        $shifts = HrisShift::where('tenant_id', $adminId)
            ->withCount('assignments')
            ->orderBy('work_start_time', 'asc')
            ->get();

        return response()->json([
            'shifts' => $shifts,
            'total' => $shifts->count(),
        ]);
    }

    /**
     * Create a new master shift
     */
    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'check_in_start' => 'required|string',
            'work_start_time' => 'required|string',
            'late_tolerance_time' => 'required|string',
            'check_in_end' => 'required|string',
            'work_end_time' => 'required|string',
            'is_night_shift' => 'nullable|boolean',
            'color' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $shift = HrisShift::create([
            'tenant_id' => $request->user()->id,
            'name' => $request->name,
            'code' => $request->code ?: 'SHF-' . strtoupper(substr(uniqid(), -4)),
            'check_in_start' => substr($request->check_in_start, 0, 5),
            'work_start_time' => substr($request->work_start_time, 0, 5),
            'late_tolerance_time' => substr($request->late_tolerance_time, 0, 5),
            'check_in_end' => substr($request->check_in_end, 0, 5),
            'work_end_time' => substr($request->work_end_time, 0, 5),
            'is_night_shift' => $request->is_night_shift ?? false,
            'color' => $request->color ?: '#3b82f6',
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'Master shift berhasil dibuat',
            'shift' => $shift,
        ], 201);
    }

    /**
     * Update existing master shift
     */
    public function update(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $shift = HrisShift::where('id', $id)
            ->where('tenant_id', $request->user()->id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'check_in_start' => 'required|string',
            'work_start_time' => 'required|string',
            'late_tolerance_time' => 'required|string',
            'check_in_end' => 'required|string',
            'work_end_time' => 'required|string',
            'is_night_shift' => 'nullable|boolean',
            'color' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $shift->update([
            'name' => $request->name,
            'code' => $request->code ?: $shift->code,
            'check_in_start' => substr($request->check_in_start, 0, 5),
            'work_start_time' => substr($request->work_start_time, 0, 5),
            'late_tolerance_time' => substr($request->late_tolerance_time, 0, 5),
            'check_in_end' => substr($request->check_in_end, 0, 5),
            'work_end_time' => substr($request->work_end_time, 0, 5),
            'is_night_shift' => $request->is_night_shift ?? $shift->is_night_shift,
            'color' => $request->color ?: $shift->color,
            'is_active' => $request->is_active ?? $shift->is_active,
        ]);

        return response()->json([
            'message' => 'Master shift berhasil diperbarui',
            'shift' => $shift,
        ]);
    }

    /**
     * Delete master shift
     */
    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $shift = HrisShift::where('id', $id)
            ->where('tenant_id', $request->user()->id)
            ->firstOrFail();

        if ($shift->attendances()->exists()) {
            $shift->update(['is_active' => false]);
            return response()->json(['message' => 'Shift dinonaktifkan karena telah memiliki riwayat absensi']);
        }

        $shift->delete();
        return response()->json(['message' => 'Master shift berhasil dihapus']);
    }

    /**
     * Get shift assignments / roster
     */
    public function getAssignments(Request $request)
    {
        $adminId = $request->user()->isAdmin() ? $request->user()->id : ($request->user()->employee ? $request->user()->employee->admin_id : null);
        if (!$adminId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $month = $request->month ?: Carbon::now()->month;
        $year = $request->year ?: Carbon::now()->year;

        $startDate = $request->start_date ?: Carbon::createFromDate($year, $month, 1)->startOfMonth()->format('Y-m-d');
        $endDate = $request->end_date ?: Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

        $query = HrisShiftAssignment::where('tenant_id', $adminId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['employee.user', 'shift']);

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        $assignments = $query->orderBy('date', 'asc')->get();

        $employees = Employee::where('admin_id', $adminId)
            ->with('user:id,name,email')
            ->get(['id', 'user_id', 'employee_id', 'department', 'position']);

        $shifts = HrisShift::where('tenant_id', $adminId)->where('is_active', true)->get();

        return response()->json([
            'assignments' => $assignments,
            'employees' => $employees,
            'shifts' => $shifts,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Bulk assign shift to employees
     */
    public function assignShifts(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
            'shift_id' => 'required|integer|exists:hris_shifts,id',
            'dates' => 'nullable|array',
            'dates.*' => 'date_format:Y-m-d',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adminId = $request->user()->id;

        $datesList = [];
        if (!empty($request->dates)) {
            $datesList = $request->dates;
        } elseif ($request->start_date && $request->end_date) {
            $period = CarbonPeriod::create($request->start_date, $request->end_date);
            foreach ($period as $date) {
                $datesList[] = $date->format('Y-m-d');
            }
        } else {
            return response()->json(['message' => 'Silakan tentukan tanggal atau rentang tanggal jadwal'], 422);
        }

        $assignedCount = 0;
        DB::transaction(function () use ($adminId, $request, $datesList, &$assignedCount) {
            foreach ($request->employee_ids as $empId) {
                $emp = Employee::where('id', $empId)->where('admin_id', $adminId)->first();
                if (!$emp) continue;

                foreach ($datesList as $dateStr) {
                    HrisShiftAssignment::updateOrCreate(
                        [
                            'tenant_id' => $adminId,
                            'employee_id' => $empId,
                            'date' => $dateStr,
                        ],
                        [
                            'shift_id' => $request->shift_id,
                            'notes' => $request->notes,
                        ]
                    );
                    $assignedCount++;
                }
            }
        });

        return response()->json([
            'message' => "Berhasil menetapkan jadwal shift untuk {$assignedCount} jadwal penugasan.",
            'assigned_count' => $assignedCount,
        ]);
    }

    /**
     * Delete a shift assignment
     */
    public function deleteAssignment(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $assignment = HrisShiftAssignment::where('id', $id)
            ->where('tenant_id', $request->user()->id)
            ->firstOrFail();

        $assignment->delete();

        return response()->json(['message' => 'Jadwal shift berhasil dihapus']);
    }

    /**
     * SMART AUTO-GENERATE SHIFT ROSTER ENGINE
     * Handles weekly rolling shift rotation, balanced daily split, and custom day-offs
     */
    public function autoGenerate(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
            'shift_ids' => 'required|array|min:1',
            'shift_ids.*' => 'integer|exists:hris_shifts,id',
            'pattern' => 'required|in:weekly_rotation,balanced_daily,fixed_shift',
            'off_days' => 'nullable|array', // [0] for Sunday, [0, 6] for Sat & Sun
            'off_days.*' => 'integer|between:0,6',
            'rotating_off' => 'nullable|boolean',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adminId = $request->user()->id;
        $employeeIds = $request->employee_ids;
        $shiftIds = $request->shift_ids;
        $pattern = $request->pattern;
        $offDays = $request->off_days ?? [0]; // default Sunday off (0 = Sunday in Carbon)
        $rotatingOff = $request->rotating_off ?? false;
        $notes = $request->notes ?: 'Auto-Generated Roster';

        $startDate = Carbon::createFromFormat('Y-m-d', $request->start_date)->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $request->end_date)->endOfDay();
        $period = CarbonPeriod::create($startDate, $endDate);

        $shiftCount = count($shiftIds);
        $empCount = count($employeeIds);
        $generatedCount = 0;

        DB::transaction(function () use (
            $adminId, $employeeIds, $shiftIds, $pattern, $offDays, $rotatingOff, $notes,
            $startDate, $period, $shiftCount, $empCount, &$generatedCount
        ) {
            foreach ($period as $currentDate) {
                $dateStr = $currentDate->format('Y-m-d');
                $dayOfWeek = $currentDate->dayOfWeek; // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
                $weekNumber = (int) $startDate->diffInWeeks($currentDate);

                foreach ($employeeIds as $index => $empId) {
                    // Check if employee has a day off on this date
                    $isOffDay = false;

                    if ($rotatingOff) {
                        // In rotating off mode, stagger day off based on employee index
                        $assignedOffDay = ($index % 7);
                        if ($dayOfWeek === $assignedOffDay) {
                            $isOffDay = true;
                        }
                    } else {
                        // Standard off days (e.g. Sunday or Sat+Sun)
                        if (in_array($dayOfWeek, $offDays)) {
                            $isOffDay = true;
                        }
                    }

                    if ($isOffDay) {
                        // Remove assignment if day off so it reflects default off / day-off
                        HrisShiftAssignment::where('tenant_id', $adminId)
                            ->where('employee_id', $empId)
                            ->where('date', $dateStr)
                            ->delete();
                        continue;
                    }

                    // Determine assigned shift based on rotation pattern
                    $shiftId = null;

                    if ($pattern === 'weekly_rotation') {
                        // Weekly rolling shift: shift index advances each week for each employee
                        $shiftIndex = ($index + $weekNumber) % $shiftCount;
                        $shiftId = $shiftIds[$shiftIndex];
                    } elseif ($pattern === 'balanced_daily') {
                        // Balanced daily: evenly split team across shifts
                        $shiftIndex = $index % $shiftCount;
                        $shiftId = $shiftIds[$shiftIndex];
                    } else {
                        // Fixed shift: assign first selected shift
                        $shiftId = $shiftIds[0];
                    }

                    HrisShiftAssignment::updateOrCreate(
                        [
                            'tenant_id' => $adminId,
                            'employee_id' => $empId,
                            'date' => $dateStr,
                        ],
                        [
                            'shift_id' => $shiftId,
                            'notes' => $notes,
                        ]
                    );
                    $generatedCount++;
                }
            }
        });

        return response()->json([
            'message' => "Auto-generate roster berhasil! Diterapkan {$generatedCount} jadwal penugasan shift.",
            'generated_count' => $generatedCount,
            'pattern' => $pattern,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);
    }

    /**
     * SWAP SCHEDULE BETWEEN TWO EMPLOYEES
     */
    public function swapShifts(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'employee_id_1' => 'required|integer|exists:employees,id',
            'employee_id_2' => 'required|integer|exists:employees,id|different:employee_id_1',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $adminId = $request->user()->id;
        $emp1Id = $request->employee_id_1;
        $emp2Id = $request->employee_id_2;

        $period = CarbonPeriod::create($request->start_date, $request->end_date);
        $swappedCount = 0;

        DB::transaction(function () use ($adminId, $emp1Id, $emp2Id, $period, &$swappedCount) {
            foreach ($period as $currentDate) {
                $dateStr = $currentDate->format('Y-m-d');

                $assign1 = HrisShiftAssignment::where('tenant_id', $adminId)
                    ->where('employee_id', $emp1Id)
                    ->where('date', $dateStr)
                    ->first();

                $assign2 = HrisShiftAssignment::where('tenant_id', $adminId)
                    ->where('employee_id', $emp2Id)
                    ->where('date', $dateStr)
                    ->first();

                $shift1Id = $assign1 ? $assign1->shift_id : null;
                $shift2Id = $assign2 ? $assign2->shift_id : null;

                // Swap for Employee 1
                if ($shift2Id) {
                    HrisShiftAssignment::updateOrCreate(
                        ['tenant_id' => $adminId, 'employee_id' => $emp1Id, 'date' => $dateStr],
                        ['shift_id' => $shift2Id, 'notes' => 'Tukar shift dengan karyawan # ' . $emp2Id]
                    );
                } else {
                    if ($assign1) $assign1->delete();
                }

                // Swap for Employee 2
                if ($shift1Id) {
                    HrisShiftAssignment::updateOrCreate(
                        ['tenant_id' => $adminId, 'employee_id' => $emp2Id, 'date' => $dateStr],
                        ['shift_id' => $shift1Id, 'notes' => 'Tukar shift dengan karyawan # ' . $emp1Id]
                    );
                } else {
                    if ($assign2) $assign2->delete();
                }

                $swappedCount++;
            }
        });

        return response()->json([
            'message' => "Berhasil menukar jadwal shift untuk {$swappedCount} hari penugasan.",
            'swapped_count' => $swappedCount,
        ]);
    }
}
