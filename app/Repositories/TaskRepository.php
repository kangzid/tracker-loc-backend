<?php

namespace App\Repositories;

use App\Models\Task;

class TaskRepository extends BaseRepository
{
    public function __construct(Task $model)
    {
        parent::__construct($model);
    }

    public function getByAdminId(int $adminId, int $perPage = 20)
    {
        return $this->model
            ->with([
                'employee.user:id,name,email', 
                'assignedBy:id,name,email'
            ])
            ->select('id', 'admin_id', 'title', 'description', 'assigned_to', 'assigned_by', 'status', 'priority', 'due_date', 'created_at', 'updated_at')
            ->forAdmin($adminId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getByEmployeeId(int $employeeId, ?string $status = null, ?string $priority = null, int $perPage = 20)
    {
        $query = $this->model
            ->with(['assignedBy:id,name,email'])
            ->select('id', 'admin_id', 'title', 'description', 'assigned_to', 'assigned_by', 'status', 'priority', 'due_date', 'created_at', 'updated_at')
            ->forEmployee($employeeId);

        if ($status) {
            $query->where('status', $status);
        }

        if ($priority) {
            $query->byPriority($priority);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function findWithRelations(int $id): ?Task
    {
        return $this->model
            ->with(['employee.user', 'assignedBy'])
            ->find($id);
    }
}
