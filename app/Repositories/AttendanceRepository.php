<?php

namespace App\Repositories;

use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceRepository extends BaseRepository
{
    public function __construct(Attendance $model)
    {
        parent::__construct($model);
    }

    public function findByEmployeeAndDate(int $employeeId, $date): ?Attendance
    {
        return $this->model
            ->where('employee_id', $employeeId)
            ->whereDate('date', $date)
            ->first();
    }

    public function getByEmployeeId(int $employeeId, int $perPage = 20)
    {
        return $this->model
            ->where('employee_id', $employeeId)
            ->orderBy('date', 'desc')
            ->paginate($perPage);
    }

    public function getByAdminId(int $adminId, int $perPage = 20)
    {
        return $this->model
            ->with(['employee' => function($query) {
                $query->select('id', 'user_id', 'admin_id', 'employee_id', 'department', 'position');
            }, 'employee.user' => function($query) {
                $query->select('id', 'name', 'email');
            }])
            ->whereHas('employee', function ($q) use ($adminId) {
                $q->where('admin_id', $adminId);
            })
            ->orderBy('date', 'desc')
            ->paginate($perPage);
    }

    public function getTodayByEmployeeId(int $employeeId): ?Attendance
    {
        return $this->model
            ->forEmployee($employeeId)
            ->today()
            ->first();
    }

    public function getMonthlyByEmployeeId(int $employeeId)
    {
        return $this->model
            ->forEmployee($employeeId)
            ->thisMonth()
            ->orderBy('date', 'desc')
            ->get();
    }

    public function getEmployeeAttendancesByMonth(int $employeeId, int $month, int $year)
    {
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $startDate = Carbon::create($year, $month, 1);
        $endDate = Carbon::create($year, $month, $daysInMonth);

        return $this->model
            ->with('employee.user')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('date', 'asc')
            ->get();
    }

    public function deleteOldAttendances(int $adminId, $beforeDate): int
    {
        return $this->model
            ->whereHas('employee', function ($query) use ($adminId) {
                $query->where('admin_id', $adminId);
            })
            ->where('date', '<', $beforeDate)
            ->delete();
    }
}
