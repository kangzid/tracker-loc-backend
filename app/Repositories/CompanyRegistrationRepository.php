<?php

namespace App\Repositories;

use App\Models\CompanyRegistration;

class CompanyRegistrationRepository extends BaseRepository
{
    public function __construct(CompanyRegistration $model)
    {
        parent::__construct($model);
    }

    public function getPending(int $perPage = 20)
    {
        return $this->model
            ->pending()
            ->recent()
            ->paginate($perPage);
    }

    public function getApproved(int $perPage = 20)
    {
        return $this->model
            ->with(['createdAdmin', 'approvedBy'])
            ->approved()
            ->recent()
            ->paginate($perPage);
    }

    public function getRejected(int $perPage = 20)
    {
        return $this->model
            ->with(['approvedBy'])
            ->rejected()
            ->recent()
            ->paginate($perPage);
    }

    public function findByEmail(string $email): ?CompanyRegistration
    {
        return $this->model->where('contact_email', $email)->first();
    }

    public function approve(int $id, int $approvedBy, int $createdAdminId): bool
    {
        return $this->model
            ->where('id', $id)
            ->update([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'created_admin_id' => $createdAdminId,
            ]);
    }

    public function reject(int $id, int $rejectedBy, string $reason): bool
    {
        return $this->model
            ->where('id', $id)
            ->update([
                'status' => 'rejected',
                'approved_by' => $rejectedBy,
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ]);
    }
}
