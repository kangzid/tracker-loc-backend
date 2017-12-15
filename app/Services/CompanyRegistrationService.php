<?php

namespace App\Services;

use App\Models\User;
use App\Models\CompanyRegistration;
use App\Repositories\CompanyRegistrationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyRegistrationService
{
    protected CompanyRegistrationRepository $repository;

    public function __construct(CompanyRegistrationRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Register new company
     */
    public function register(array $data): CompanyRegistration
    {
        return $this->repository->create([
            'company_name' => $data['company_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'status' => 'pending',
        ]);
    }

    /**
     * Get all pending registrations
     */
    public function getPendingRegistrations(int $perPage = 20)
    {
        return $this->repository->getPending($perPage);
    }

    /**
     * Get all approved registrations
     */
    public function getApprovedRegistrations(int $perPage = 20)
    {
        return $this->repository->getApproved($perPage);
    }

    /**
     * Get all rejected registrations
     */
    public function getRejectedRegistrations(int $perPage = 20)
    {
        return $this->repository->getRejected($perPage);
    }

    /**
     * Get registration by ID
     */
    public function getById(int $id): ?CompanyRegistration
    {
        return $this->repository->find($id);
    }

    /**
     * Approve registration and create admin account
     */
    public function approve(int $id, int $approvedBy): array
    {
        $registration = $this->repository->find($id);

        if (!$registration) {
            throw new \Exception('Registration not found');
        }

        if (!$registration->isPending()) {
            throw new \Exception('Registration is not pending');
        }

        return DB::transaction(function () use ($registration, $approvedBy, $id) {
            // Generate random password
            $password = $this->generatePassword();

            // Create admin user
            $admin = User::create([
                'name' => $registration->company_name,
                'email' => $registration->contact_email,
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
                'must_change_password' => true,
            ]);

            // Create trial subscription
            $trialPlan = \App\Models\Plan::where('slug', 'trial')->first();
            
            \App\Models\Subscription::create([
                'user_id' => $admin->id,
                'plan_id' => $trialPlan ? $trialPlan->id : null,
                'max_employees' => $trialPlan ? $trialPlan->max_employees : 2,
                'max_vehicles' => $trialPlan ? $trialPlan->max_vehicles : 2,
                'ai_credits_limit' => $trialPlan ? $trialPlan->ai_credits : 20,
                'ai_credits_used' => 0,
                'company_name' => $registration->company_name,
                'contact_phone' => $registration->contact_phone,
                'started_at' => now(),
                'expired_at' => now()->addDays(14),
                'status' => 'active',
            ]);

            // Update registration status
            $this->repository->approve($id, $approvedBy, $admin->id);

            return [
                'admin' => $admin,
                'password' => $password,
                'registration' => $this->repository->find($id),
            ];
        });
    }

    /**
     * Reject registration
     */
    public function reject(int $id, int $rejectedBy, string $reason): CompanyRegistration
    {
        $registration = $this->repository->find($id);

        if (!$registration) {
            throw new \Exception('Registration not found');
        }

        if (!$registration->isPending()) {
            throw new \Exception('Registration is not pending');
        }

        $this->repository->reject($id, $rejectedBy, $reason);

        return $this->repository->find($id);
    }

    /**
     * Generate random secure password
     */
    protected function generatePassword(): string
    {
        $length = config('locatrack.registration.default_password_length', 12);

        // Generate password with mix of characters
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*';

        $password = '';
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)];
        $password .= $lowercase[rand(0, strlen($lowercase) - 1)];
        $password .= $numbers[rand(0, strlen($numbers) - 1)];
        $password .= $special[rand(0, strlen($special) - 1)];

        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[rand(0, strlen($allChars) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * Delete registration (only rejected)
     */
    public function delete(int $id): bool
    {
        $registration = $this->repository->find($id);

        if (!$registration) {
            throw new \Exception('Registration not found');
        }

        return DB::transaction(function () use ($registration, $id) {
            if ($registration->isApproved()) {
                // Cancel subscription & soft-delete tenant
                $subscription = \App\Models\Subscription::where('user_id', $registration->created_admin_id)->first();
                if ($subscription) {
                    $subscription->update([
                        'status' => 'cancelled',
                        'expired_at' => now(),
                    ]);
                }

                // Soft-delete tenant admin & related data
                $admin = \App\Models\User::where('id', $registration->created_admin_id)
                    ->where('role', 'admin')
                    ->first();
                if ($admin) {
                    $admin->update(['is_active' => false]);
                    // Optionally delete employees/vehicles: Employee::where('admin_id', $admin->id)->delete();
                }
            }

            // Delete registration (all statuses)
            return $this->repository->delete($id);
        });
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => CompanyRegistration::count(),
            'pending' => CompanyRegistration::pending()->count(),
            'approved' => CompanyRegistration::approved()->count(),
            'rejected' => CompanyRegistration::rejected()->count(),
        ];
    }
}
