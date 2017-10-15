<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRegistrationRequest;
use App\Http\Requests\ApproveRegistrationRequest;
use App\Http\Requests\RejectRegistrationRequest;
use App\Services\CompanyRegistrationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CompanyRegistrationController extends Controller
{
    use ApiResponse;

    protected CompanyRegistrationService $service;

    public function __construct(CompanyRegistrationService $service)
    {
        $this->service = $service;
    }

    /**
     * Register new company (Public endpoint)
     */
    public function register(CompanyRegistrationRequest $request)
    {
        try {
            $registration = $this->service->register($request->validated());

            return $this->createdResponse(
                $registration,
                'Pendaftaran berhasil! Tim kami akan menghubungi Anda melalui WhatsApp dalam 1x24 jam untuk aktivasi akun.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Pendaftaran gagal: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all pending registrations (Superadmin only)
     */
    public function pending(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return $this->forbiddenResponse('Only superadmin can access this resource');
        }

        $registrations = $this->service->getPendingRegistrations(
            $request->get('per_page', 20)
        );

        return $this->paginatedResponse($registrations, 'Pending registrations retrieved successfully');
    }

    /**
     * Get all approved registrations (Superadmin only)
     */
    public function approved(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return $this->forbiddenResponse('Only superadmin can access this resource');
        }

        $registrations = $this->service->getApprovedRegistrations(
            $request->get('per_page', 20)
        );

        return $this->paginatedResponse($registrations, 'Approved registrations retrieved successfully');
    }

    /**
     * Get all rejected registrations (Superadmin only)
     */
    public function rejected(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return $this->forbiddenResponse('Only superadmin can access this resource');
        }

        $registrations = $this->service->getRejectedRegistrations(
            $request->get('per_page', 20)
        );

        return $this->paginatedResponse($registrations, 'Rejected registrations retrieved successfully');
    }

    /**
     * Get registration by ID (Superadmin only)
     */
    public function show(Request $request, int $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return $this->forbiddenResponse('Only superadmin can access this resource');
        }

        $registration = $this->service->getById($id);

        if (!$registration) {
            return $this->notFoundResponse('Registration not found');
        }

        return $this->successResponse($registration, 'Registration retrieved successfully');
    }

    /**
     * Approve registration (Superadmin only)
     */
    public function approve(ApproveRegistrationRequest $request, int $id)
    {
        try {
            $result = $this->service->approve($id, $request->user()->id);

            return $this->successResponse([
                'registration' => $result['registration'],
                'admin' => [
                    'id' => $result['admin']->id,
                    'name' => $result['admin']->name,
                    'email' => $result['admin']->email,
                    'role' => $result['admin']->role,
                ],
                'credentials' => [
                    'email' => $result['admin']->email,
                    'password' => $result['password'],
                ],
                'whatsapp_template' => $this->generateWhatsAppTemplate(
                    $result['registration']->company_name,
                    $result['admin']->email,
                    $result['password']
                ),
            ], 'Registration approved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Approval failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Reject registration (Superadmin only)
     */
    public function reject(RejectRegistrationRequest $request, int $id)
    {
        try {
            $registration = $this->service->reject(
                $id,
                $request->user()->id,
                $request->rejection_reason
            );

            return $this->successResponse($registration, 'Registration rejected successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Rejection failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Get registration statistics (Superadmin only)
     */
    public function statistics(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return $this->forbiddenResponse('Only superadmin can access this resource');
        }

        $stats = $this->service->getStatistics();

        return $this->successResponse($stats, 'Statistics retrieved successfully');
    }

    /**
     * Delete registration (Superadmin only - only rejected)
     */
    public function destroy(Request $request, int $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return $this->forbiddenResponse('Only superadmin can access this resource');
        }

        try {
            $this->service->delete($id);
            return $this->successResponse(null, 'Registration deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Delete failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Generate WhatsApp message template
     */
    protected function generateWhatsAppTemplate(string $companyName, string $email, string $password): string
    {
        $appUrl = config('app.url');
        
        return "Halo {$companyName},\n\n" .
               "Akun LocaTrack Anda sudah aktif!\n\n" .
               "🔐 Kredensial Login:\n" .
               "Email: {$email}\n" .
               "Password: {$password}\n" .
               "Link: {$appUrl}/login\n\n" .
               "Silakan login dan ganti password Anda.\n" .
               "Butuh bantuan? Reply chat ini.";
    }
}
