<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\GeofenceController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SuperadminNotificationController;
use App\Http\Controllers\Api\ProvisionController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\Api\GpsTrackingController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\CompanyRegistrationController;
use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\SupportChatController;

// ============================================================
// Public routes
// ============================================================
// Public Avatar & Photo Streaming (Accessible for web <img>, new tabs, and mobile applications)
Route::get('/employees/{id}/photo', [\App\Http\Controllers\Api\EmployeeController::class, 'photo']);
Route::get('/users/{id}/photo', [\App\Http\Controllers\Api\UserController::class, 'photo']);

Route::post('/login', [AuthController::class, 'login'])->middleware('rate.limit:login');
Route::post('/register', [AuthController::class, 'register'])->middleware('rate.limit:api');
Route::get('/shared-location/{token}', [LocationController::class, 'getSharedLocation']);
Route::post('/company/register', [CompanyRegistrationController::class, 'register'])->middleware('rate.limit:api');
Route::get('/images/notifications/{adminId}/{date}/{filename}', [ImageController::class, 'serveNotificationImage']);
Route::post('/provision', [ProvisionController::class, 'provision'])->middleware('rate.limit:api');
Route::prefix('gps')->group(function () {
    Route::post('/track', [GpsTrackingController::class, 'track'])->middleware('rate.limit:location');
    Route::get('/ping', [GpsTrackingController::class, 'ping']);
    Route::get('/status', [GpsTrackingController::class, 'status']);
});
Route::get('/plans', [PaymentController::class, 'getPlans']);
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

// ============================================================
// Protected routes
// ============================================================
Route::middleware('auth:sanctum')->group(function () {
    // Vehicle Types Master (Global)
    Route::apiResource('vehicle-types', App\Http\Controllers\Api\VehicleTypeController::class);
    Route::apiResource('hris/vehicle-types', App\Http\Controllers\Api\VehicleTypeController::class);

    // HRIS Master Departments & Positions (Global)
    Route::apiResource('departments', App\Http\Controllers\Api\HrisDepartmentController::class);
    Route::apiResource('positions', App\Http\Controllers\Api\HrisPositionController::class);
    Route::apiResource('hris/departments', App\Http\Controllers\Api\HrisDepartmentController::class);
    Route::apiResource('hris/positions', App\Http\Controllers\Api\HrisPositionController::class);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);

    Route::post('/payment/create-transaction', [PaymentController::class, 'createTransaction']);
    Route::post('/payment/validate-voucher', [PaymentController::class, 'validateVoucher']);
    Route::get('/payment/history', [PaymentController::class, 'history']);
    Route::post('/payment/sync-status', [PaymentController::class, 'syncStatus']);

    Route::get('/subscription/status', [ProvisionController::class, 'status']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications', [NotificationController::class, 'destroyAll']);

    Route::get('/admin/notifications', [NotificationController::class, 'getAdminNotifications']);
    Route::post('/admin/notifications/{notificationId}/read', [NotificationController::class, 'markAdminNotificationAsRead']);
    Route::delete('/admin/notifications/{notificationId}', [NotificationController::class, 'deleteAdminNotification']);
    Route::post('/admin/notifications/{id}/view', [NotificationController::class, 'incrementView']);

    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::post('/attendances', [AttendanceController::class, 'store']);
    Route::post('/attendances/check-location', [AttendanceController::class, 'checkLocation']);
    Route::get('/attendances/today', [AttendanceController::class, 'todayAttendance']);
    Route::get('/attendances/monthly', [AttendanceController::class, 'monthlyAttendance']);
    Route::get('/attendances/{id}', [AttendanceController::class, 'show']);
    Route::get('/admin/attendances/employee/{employeeId}', [AttendanceController::class, 'getEmployeeAttendances']);
    Route::post('/admin/attendances', [AttendanceController::class, 'storeAdmin']);
    Route::put('/admin/attendances/{id}', [AttendanceController::class, 'update']);
    Route::delete('/admin/attendances/{id}', [AttendanceController::class, 'destroy']);
    Route::get('/admin/attendances/settings', [AttendanceController::class, 'getSettings']);
    Route::post('/admin/attendances/settings', [AttendanceController::class, 'saveSettings']);
    Route::post('/admin/attendances/cleanup', [AttendanceController::class, 'cleanupOldAttendances']);

    Route::post('/locations', [LocationController::class, 'store']);
    Route::get('/locations/live', [LocationController::class, 'liveTracking']);
    Route::get('/locations/employee/{employeeId}/history', [LocationController::class, 'employeeHistory']);
    Route::get('/locations/vehicle/{vehicleId}/history', [LocationController::class, 'vehicleHistory']);
    Route::post('/locations/share', [LocationController::class, 'shareLocation']);

    Route::apiResource('tasks', TaskController::class);
    Route::get('/my-tasks', [TaskController::class, 'myTasks']);
    Route::post('/tasks/{id}/accept', [TaskController::class, 'acceptTask']);
    Route::post('/tasks/{id}/start', [TaskController::class, 'startTask']);
    Route::post('/tasks/{id}/complete', [TaskController::class, 'completeTask']);
    Route::post('/tasks/{id}/hide', [TaskController::class, 'hideByEmployee']);

    Route::apiResource('geofences', GeofenceController::class);

    Route::post('/employees', [EmployeeController::class, 'store'])->middleware('subscription.quota:employee');
    Route::get('/employees/{id}/profile', [EmployeeController::class, 'getComprehensiveProfile']);
    Route::apiResource('employees', EmployeeController::class)->except('store');

    Route::post('/vehicles', [VehicleController::class, 'store'])->middleware('subscription.quota:vehicle');
    Route::apiResource('vehicles', VehicleController::class)->except('store');
    Route::get('/vehicles-active', [VehicleController::class, 'activeVehicles']);
    Route::get('/vehicles-inactive', [VehicleController::class, 'inactiveVehicles']);
    Route::post('/vehicles/{id}/location', [VehicleController::class, 'updateLocation']);
    Route::post('/vehicles/{id}/regenerate-token', [VehicleController::class, 'regenerateToken']);
    Route::get('/vehicles/{id}/token', [VehicleController::class, 'getToken']);

    Route::get('/dashboard/stats', function (Request $request) {
        if (!$request->user()->isAdmin()) return response()->json(['message' => 'Unauthorized'], 403);
        $adminId = $request->user()->id;
        return response()->json([
            'total_employees' => \App\Models\Employee::where('admin_id', $adminId)->count(),
            'active_employees' => \App\Models\Employee::where('admin_id', $adminId)->whereHas('user', fn($q) => $q->where('is_active', true))->count(),
            'total_vehicles' => \App\Models\Vehicle::where('admin_id', $adminId)->count(),
            'active_vehicles' => \App\Models\Vehicle::where('admin_id', $adminId)->where('is_active', true)->count(),
            'today_attendances' => \App\Models\Attendance::whereDate('date', today())->whereHas('employee', fn($q) => $q->where('admin_id', $adminId))->count(),
            'pending_tasks' => \App\Models\Task::where('admin_id', $adminId)->where('status', 'pending')->count(),
            'in_progress_tasks' => \App\Models\Task::where('admin_id', $adminId)->where('status', 'in_progress')->count(),
        ]);
    });

    Route::get('/ai/query', [AIController::class, 'query']);
    Route::post('/ai/usage', [AIController::class, 'recordUsage']);

    Route::get('/admins', [UserController::class, 'admins']);
    Route::apiResource('users', UserController::class);

    Route::get('/employee/dashboard', [\App\Http\Controllers\Api\EmployeeDashboardController::class, 'index']);

    // HRIS Payroll & Master Data Routes
    
        // Stream & Encrypted Media Routes
        
        
        
        Route::get('/hris/claims/{id}/preview-receipt', [\App\Http\Controllers\Api\HrisClaimController::class, 'previewReceipt']);
        Route::get('/hris/claims/{id}/download-receipt', [\App\Http\Controllers\Api\HrisClaimController::class, 'downloadReceipt']);
        
        Route::get('/hris/compliance/{id}/preview', [\App\Http\Controllers\Api\HrisComplianceController::class, 'previewDoc']);
        Route::get('/hris/compliance/{id}/download', [\App\Http\Controllers\Api\HrisComplianceController::class, 'downloadDoc']);
        
        Route::get('/hris/violations/{id}/preview-evidence', [\App\Http\Controllers\Api\HrisViolationController::class, 'previewEvidence']);
        Route::get('/hris/violations/{id}/download-evidence', [\App\Http\Controllers\Api\HrisViolationController::class, 'downloadEvidence']);
        
        Route::get('/hris/training/participants/{id}/preview-cert', [\App\Http\Controllers\Api\HrisTrainingController::class, 'previewCert']);
        Route::get('/hris/training/participants/{id}/download-cert', [\App\Http\Controllers\Api\HrisTrainingController::class, 'downloadCert']);
        
        Route::get('/hris/requests/{id}/preview-attachment', [\App\Http\Controllers\Api\HrisRequestController::class, 'previewAttachment']);
        Route::get('/hris/requests/{id}/download-attachment', [\App\Http\Controllers\Api\HrisRequestController::class, 'downloadAttachment']);
        
        Route::get('/hris/news/{id}/banner', [\App\Http\Controllers\Api\HrisNewsController::class, 'previewBanner']);

        Route::prefix('hris')->group(function () {
        // HRIS Shift & Attendance Settings Routes
        Route::get('/attendance-settings', [\App\Http\Controllers\Api\AttendanceController::class, 'getSettings']);
        Route::post('/attendance-settings', [\App\Http\Controllers\Api\AttendanceController::class, 'saveSettings']);

        Route::get('/shifts', [\App\Http\Controllers\Api\HrisShiftController::class, 'index']);
        Route::post('/shifts', [\App\Http\Controllers\Api\HrisShiftController::class, 'store']);
        Route::put('/shifts/{id}', [\App\Http\Controllers\Api\HrisShiftController::class, 'update']);
        Route::delete('/shifts/{id}', [\App\Http\Controllers\Api\HrisShiftController::class, 'destroy']);

        Route::get('/shift-assignments', [\App\Http\Controllers\Api\HrisShiftController::class, 'getAssignments']);
        Route::post('/shift-assignments', [\App\Http\Controllers\Api\HrisShiftController::class, 'assignShifts']);
        Route::post('/shift-assignments/auto-generate', [\App\Http\Controllers\Api\HrisShiftController::class, 'autoGenerate']);
        Route::post('/shift-assignments/swap', [\App\Http\Controllers\Api\HrisShiftController::class, 'swapShifts']);
        Route::delete('/shift-assignments/{id}', [\App\Http\Controllers\Api\HrisShiftController::class, 'deleteAssignment']);

        // 1. Slip Gaji (Bulanan & Harian)
        Route::get('/payroll/settings', [\App\Http\Controllers\Api\HrisPayrollSettingController::class, 'getSettings']);
        Route::post('/payroll/settings', [\App\Http\Controllers\Api\HrisPayrollSettingController::class, 'saveSettings']);
        Route::get('/hris/payroll/settings', [\App\Http\Controllers\Api\HrisPayrollSettingController::class, 'getSettings']);
        Route::post('/hris/payroll/settings', [\App\Http\Controllers\Api\HrisPayrollSettingController::class, 'saveSettings']);
        Route::get('/payrolls', [App\Http\Controllers\Api\HrisPayrollController::class, 'index']);
        Route::post('/payrolls/generate-monthly', [App\Http\Controllers\Api\HrisPayrollController::class, 'generateMonthly']);
        Route::post('/hris/payrolls/generate-monthly', [App\Http\Controllers\Api\HrisPayrollController::class, 'generateMonthly']);
        Route::post('/payrolls/generate-daily', [App\Http\Controllers\Api\HrisPayrollController::class, 'generateDaily']);
        Route::get('/payrolls/{id}/slips', [App\Http\Controllers\Api\HrisPayrollController::class, 'slips']);
        Route::post('/payrolls/{id}/publish', [App\Http\Controllers\Api\HrisPayrollController::class, 'publish']);
        Route::delete('/payrolls/{id}', [App\Http\Controllers\Api\HrisPayrollController::class, 'destroy']);

        // 2. Gaji Pokok
        Route::get('/salaries', [App\Http\Controllers\Api\HrisSalaryController::class, 'index']);
        Route::post('/salaries', [App\Http\Controllers\Api\HrisSalaryController::class, 'store']);
        Route::delete('/salaries/{id}', [App\Http\Controllers\Api\HrisSalaryController::class, 'destroy']);

        // 3. Jenis Tunjangan
        Route::get('/allowance-types', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'getAllowanceTypes']);
        Route::post('/allowance-types', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'storeAllowanceType']);
        Route::delete('/allowance-types/{id}', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'deleteAllowanceType']);

        // 4. Tunjangan (Matrix per Karyawan)
        Route::get('/allowances', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'getEmployeeAllowances']);
        Route::post('/allowances', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'storeEmployeeAllowance']);
        Route::delete('/allowances/{id}', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'deleteEmployeeAllowance']);

        // 5. BPJS (Kesehatan & Ketenagakerjaan)
        Route::get('/bpjs', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'getBpjs']);
        Route::post('/bpjs', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'storeBpjs']);
        Route::delete('/bpjs/{id}', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'deleteBpjs']);

        // 6. Penyesuaian Gaji (Batches & Items)
        Route::get('/adjustments/batches', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'getAdjustmentBatches']);
        Route::post('/adjustments/batches', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'storeAdjustmentBatch']);
        Route::delete('/adjustments/batches/{batchId}', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'deleteAdjustmentBatch']);
        Route::post('/adjustments/batches/{batchId}/items', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'storeAdjustmentItem']);
        Route::delete('/adjustments/items/{id}', [App\Http\Controllers\Api\HrisPayrollMasterController::class, 'deleteAdjustmentItem']);

        // 7. Pengajuan (HRIS Requests)
        Route::get('/requests/summary', [\App\Http\Controllers\Api\HrisRequestController::class, 'summary']);
        Route::get('/requests', [\App\Http\Controllers\Api\HrisRequestController::class, 'index']);
        // Route::get('/requests', [\App\Http\Controllers\Api\HrisRequestController::class, 'index']);
        Route::post('/requests', [\App\Http\Controllers\Api\HrisRequestController::class, 'store']);
        Route::post('/requests/{id}/approve', [\App\Http\Controllers\Api\HrisRequestController::class, 'approve']);
        Route::post('/requests/{id}/reject', [\App\Http\Controllers\Api\HrisRequestController::class, 'reject']);
        Route::delete('/requests/{id}', [\App\Http\Controllers\Api\HrisRequestController::class, 'destroy']);

        // 8. Pengaturan Cuti (Leave Types & Balances)
                Route::get('/hris/request-policies', [\App\Http\Controllers\Api\HrisLeaveSettingController::class, 'getPolicies']);
        Route::post('/hris/request-policies', [\App\Http\Controllers\Api\HrisLeaveSettingController::class, 'savePolicy']);
        Route::get('/hris/leave-types', [\App\Http\Controllers\Api\HrisLeaveSettingController::class, 'getLeaveTypes']);
        Route::post('/hris/leave-types', [\App\Http\Controllers\Api\HrisLeaveSettingController::class, 'storeLeaveType']);
        Route::delete('/hris/leave-types/{id}', [\App\Http\Controllers\Api\HrisLeaveSettingController::class, 'deleteLeaveType']);
        Route::get('/hris/leave-balances', [\App\Http\Controllers\Api\HrisLeaveSettingController::class, 'getLeaveBalances']);
        Route::put('/hris/leave-balances/{id}', [\App\Http\Controllers\Api\HrisLeaveSettingController::class, 'updateLeaveBalance']);
        Route::get('/leave-types', [\App\Http\Controllers\Api\HrisLeaveSettingController::class, 'getLeaveTypes']);
        Route::post('/leave-types', [\App\Http\Controllers\Api\HrisLeaveSettingController::class, 'storeLeaveType']);
        Route::delete('/leave-types/{id}', [\App\Http\Controllers\Api\HrisLeaveSettingController::class, 'deleteLeaveType']);
        Route::get('/leave-balances', [\App\Http\Controllers\Api\HrisLeaveSettingController::class, 'getLeaveBalances']);
        Route::put('/leave-balances/{id}', [\App\Http\Controllers\Api\HrisLeaveSettingController::class, 'updateLeaveBalance']);

        // 9. Lembur (Overtime)
        Route::get('/overtimes/summary', [\App\Http\Controllers\Api\HrisOvertimeController::class, 'summary']);
        Route::get('/overtimes/settings', [\App\Http\Controllers\Api\HrisOvertimeController::class, 'getSettings']);
        Route::post('/overtimes/settings', [\App\Http\Controllers\Api\HrisOvertimeController::class, 'saveSettings']);
        Route::get('/overtimes', [\App\Http\Controllers\Api\HrisOvertimeController::class, 'index']);
        Route::post('/overtimes', [\App\Http\Controllers\Api\HrisOvertimeController::class, 'store']);
        Route::post('/overtimes/{id}/approve', [\App\Http\Controllers\Api\HrisOvertimeController::class, 'approve']);
        Route::post('/overtimes/{id}/reject', [\App\Http\Controllers\Api\HrisOvertimeController::class, 'reject']);
        Route::delete('/overtimes/{id}', [\App\Http\Controllers\Api\HrisOvertimeController::class, 'destroy']);

        // 10. Reimbursement (Claims)
        Route::get('/claims/summary', [\App\Http\Controllers\Api\HrisClaimController::class, 'summary']);
        Route::get('/claims/types', [\App\Http\Controllers\Api\HrisClaimController::class, 'getClaimTypes']);
        Route::post('/claims/types', [\App\Http\Controllers\Api\HrisClaimController::class, 'storeClaimType']);
        Route::delete('/claims/types/{id}', [\App\Http\Controllers\Api\HrisClaimController::class, 'deleteClaimType']);
        Route::get('/claims', [\App\Http\Controllers\Api\HrisClaimController::class, 'index']);
        Route::post('/claims', [\App\Http\Controllers\Api\HrisClaimController::class, 'store']);
        Route::post('/claims/{id}/approve', [\App\Http\Controllers\Api\HrisClaimController::class, 'approve']);
        Route::post('/claims/{id}/reject', [\App\Http\Controllers\Api\HrisClaimController::class, 'reject']);
        Route::post('/claims/{id}/mark-paid', [\App\Http\Controllers\Api\HrisClaimController::class, 'markPaid']);
        Route::delete('/claims/{id}', [\App\Http\Controllers\Api\HrisClaimController::class, 'destroy']);

        // 11. Inventaris Aset (Assets)
        Route::get('/assets/summary', [\App\Http\Controllers\Api\HrisAssetController::class, 'summary']);
        Route::get('/assets', [\App\Http\Controllers\Api\HrisAssetController::class, 'index']);
        Route::post('/assets', [\App\Http\Controllers\Api\HrisAssetController::class, 'store']);
        Route::put('/assets/{id}', [\App\Http\Controllers\Api\HrisAssetController::class, 'update']);
        Route::post('/assets/{id}/assign', [\App\Http\Controllers\Api\HrisAssetController::class, 'assign']);
        Route::post('/assets/{id}/return', [\App\Http\Controllers\Api\HrisAssetController::class, 'returnAsset']);
        Route::post('/assets/{id}/report-damage', [\App\Http\Controllers\Api\HrisAssetController::class, 'reportDamage']);
        Route::post('/assets/{id}/maintenance-status', [\App\Http\Controllers\Api\HrisAssetController::class, 'updateMaintenanceStatus']);
        Route::delete('/assets/{id}', [\App\Http\Controllers\Api\HrisAssetController::class, 'destroy']);

        // 12. Pinjaman & Kasbon (Loans)
        Route::get('/loans/summary', [\App\Http\Controllers\Api\HrisLoanController::class, 'summary']);
        Route::get('/loans', [\App\Http\Controllers\Api\HrisLoanController::class, 'index']);
        Route::post('/loans', [\App\Http\Controllers\Api\HrisLoanController::class, 'store']);
        Route::post('/loans/{id}/manual-payment', [\App\Http\Controllers\Api\HrisLoanController::class, 'manualPayment']);
        Route::get('/loans/{id}/history', [\App\Http\Controllers\Api\HrisLoanController::class, 'history']);
        Route::delete('/loans/{id}', [\App\Http\Controllers\Api\HrisLoanController::class, 'destroy']);

        // 13. Pengumuman & Berita Perusahaan (News & Announcements)
        Route::get('/news/summary', [\App\Http\Controllers\Api\HrisNewsController::class, 'summary']);
        Route::get('/news', [\App\Http\Controllers\Api\HrisNewsController::class, 'index']);
        Route::post('/news', [\App\Http\Controllers\Api\HrisNewsController::class, 'store']);
        Route::put('/news/{id}', [\App\Http\Controllers\Api\HrisNewsController::class, 'update']);
        Route::post('/news/{id}/toggle-publish', [\App\Http\Controllers\Api\HrisNewsController::class, 'togglePublish']);
        Route::post('/news/{id}/view', [\App\Http\Controllers\Api\HrisNewsController::class, 'incrementView']);
        Route::get('/news/{id}', [\App\Http\Controllers\Api\HrisNewsController::class, 'show']);
        Route::delete('/news/{id}', [\App\Http\Controllers\Api\HrisNewsController::class, 'destroy']);

        // 14. KPI & Evaluasi Kinerja (Performance Appraisals)
        Route::get('/performance/summary', [\App\Http\Controllers\Api\HrisPerformanceController::class, 'summary']);
        Route::get('/performance/department-stats', [\App\Http\Controllers\Api\HrisPerformanceController::class, 'departmentStats']);
        Route::get('/performance', [\App\Http\Controllers\Api\HrisPerformanceController::class, 'index']);
        Route::post('/performance', [\App\Http\Controllers\Api\HrisPerformanceController::class, 'store']);
        Route::put('/performance/{id}', [\App\Http\Controllers\Api\HrisPerformanceController::class, 'update']);
        Route::post('/performance/{id}/finalize', [\App\Http\Controllers\Api\HrisPerformanceController::class, 'finalizeReview']);
        Route::delete('/performance/{id}', [\App\Http\Controllers\Api\HrisPerformanceController::class, 'destroy']);

                    // 15. Digital Documents (Brankas Berkas Karyawan)
          Route::get('/documents/summary', [\App\Http\Controllers\Api\HrisDocumentController::class, 'summary']);
          Route::get('/documents', [\App\Http\Controllers\Api\HrisDocumentController::class, 'index']);
          Route::post('/documents', [\App\Http\Controllers\Api\HrisDocumentController::class, 'store']);
          Route::get('/documents/{id}/preview', [\App\Http\Controllers\Api\HrisDocumentController::class, 'preview']);
          Route::get('/documents/{id}/download', [\App\Http\Controllers\Api\HrisDocumentController::class, 'download']);
          Route::put('/documents/{id}', [\App\Http\Controllers\Api\HrisDocumentController::class, 'update']);
          Route::post('/documents/{id}/verify', [\App\Http\Controllers\Api\HrisDocumentController::class, 'verify']);
          Route::delete('/documents/{id}', [\App\Http\Controllers\Api\HrisDocumentController::class, 'destroy']);

          // 16. Pelanggaran & Surat Peringatan (Violations & Warning Letters)
          Route::get('/violations/summary', [\App\Http\Controllers\Api\HrisViolationController::class, 'summary']);
          Route::get('/violations/types', [\App\Http\Controllers\Api\HrisViolationController::class, 'getViolationTypes']);
          Route::post('/violations/types', [\App\Http\Controllers\Api\HrisViolationController::class, 'storeViolationType']);
          Route::put('/violations/types/{id}', [\App\Http\Controllers\Api\HrisViolationController::class, 'storeViolationType']);
          Route::delete('/violations/types/{id}', [\App\Http\Controllers\Api\HrisViolationController::class, 'deleteViolationType']);
          Route::get('/violations', [\App\Http\Controllers\Api\HrisViolationController::class, 'index']);
          Route::post('/violations', [\App\Http\Controllers\Api\HrisViolationController::class, 'store']);
          Route::put('/violations/{id}', [\App\Http\Controllers\Api\HrisViolationController::class, 'update']);
          Route::post('/violations/{id}/revoke', [\App\Http\Controllers\Api\HrisViolationController::class, 'revoke']);
          Route::delete('/violations/{id}', [\App\Http\Controllers\Api\HrisViolationController::class, 'destroy']);

          
        // HRIS Compliance & Alerts (Legalitas SIM, STNK, KIR, Sertifikasi)
        Route::get('/hris/compliance/summary', [\App\Http\Controllers\Api\HrisComplianceController::class, 'summary']);
        Route::get('/hris/compliance', [\App\Http\Controllers\Api\HrisComplianceController::class, 'index']);
        Route::post('/hris/compliance', [\App\Http\Controllers\Api\HrisComplianceController::class, 'store']);
        Route::get('/hris/compliance/{id}', [\App\Http\Controllers\Api\HrisComplianceController::class, 'show']);
        Route::put('/hris/compliance/{id}', [\App\Http\Controllers\Api\HrisComplianceController::class, 'update']);
        Route::post('/hris/compliance/{id}/renew', [\App\Http\Controllers\Api\HrisComplianceController::class, 'renew']);
        Route::delete('/hris/compliance/{id}', [\App\Http\Controllers\Api\HrisComplianceController::class, 'destroy']);
        Route::get('/hris/compliance/{id}/preview', [\App\Http\Controllers\Api\HrisComplianceController::class, 'previewDoc']);
        Route::get('/hris/compliance/{id}/download', [\App\Http\Controllers\Api\HrisComplianceController::class, 'downloadDoc']);

        // HRIS Compliance Doc Types
        Route::get('/hris/compliance-doc-types', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'getComplianceDocTypes']);
        Route::post('/hris/compliance-doc-types', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'storeComplianceDocType']);
        Route::put('/hris/compliance-doc-types/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'updateComplianceDocType']);
        Route::delete('/hris/compliance-doc-types/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'deleteComplianceDocType']);

        // HRIS Violations & Sanctions (Kedisiplinan, Sanksi & Surat Peringatan)
        Route::get('/hris/violations/summary', [\App\Http\Controllers\Api\HrisViolationController::class, 'summary']);
        Route::get('/hris/violations/types', [\App\Http\Controllers\Api\HrisViolationController::class, 'getViolationTypes']);
        Route::post('/hris/violations/types', [\App\Http\Controllers\Api\HrisViolationController::class, 'storeViolationType']);
        Route::put('/hris/violations/types/{id}', [\App\Http\Controllers\Api\HrisViolationController::class, 'storeViolationType']);
        Route::delete('/hris/violations/types/{id}', [\App\Http\Controllers\Api\HrisViolationController::class, 'deleteViolationType']);
        Route::get('/hris/violations', [\App\Http\Controllers\Api\HrisViolationController::class, 'index']);
        Route::post('/hris/violations', [\App\Http\Controllers\Api\HrisViolationController::class, 'store']);
        Route::get('/hris/violations/{id}', [\App\Http\Controllers\Api\HrisViolationController::class, 'show']);
        Route::put('/hris/violations/{id}', [\App\Http\Controllers\Api\HrisViolationController::class, 'update']);
        Route::post('/hris/violations/{id}/revoke', [\App\Http\Controllers\Api\HrisViolationController::class, 'revoke']);
        Route::delete('/hris/violations/{id}', [\App\Http\Controllers\Api\HrisViolationController::class, 'destroy']);
        Route::get('/hris/violations/{id}/preview', [\App\Http\Controllers\Api\HrisViolationController::class, 'previewEvidence']);
        Route::get('/hris/violations/{id}/download', [\App\Http\Controllers\Api\HrisViolationController::class, 'downloadEvidence']);

        // 17. Compliance & Alerts (Legalitas SIM, STNK, KIR, K3)
          
        
        // Master Settings: Training Categories, KPI Periods, Compliance Doc Types, Asset Categories
        Route::get('/training-categories', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'getTrainingCategories']);
        Route::post('/training-categories', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'storeTrainingCategory']);
        Route::put('/training-categories/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'updateTrainingCategory']);
        Route::delete('/training-categories/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'deleteTrainingCategory']);

        Route::get('/kpi-periods', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'getKpiPeriods']);
        Route::post('/kpi-periods', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'storeKpiPeriod']);
        Route::put('/kpi-periods/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'updateKpiPeriod']);
        Route::delete('/kpi-periods/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'deleteKpiPeriod']);

        Route::get('/compliance-doc-types', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'getComplianceDocTypes']);
        Route::post('/compliance-doc-types', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'storeComplianceDocType']);
        Route::put('/compliance-doc-types/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'updateComplianceDocType']);
        Route::delete('/compliance-doc-types/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'deleteComplianceDocType']);

        Route::get('/asset-categories', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'getAssetCategories']);
        Route::post('/asset-categories', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'storeAssetCategory']);
        Route::put('/asset-categories/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'updateAssetCategory']);
        Route::delete('/asset-categories/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'deleteAssetCategory']);

        
        Route::get('/document-categories', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'getDocumentCategories']);
        Route::post('/document-categories', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'storeDocumentCategory']);
        Route::put('/document-categories/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'updateDocumentCategory']);
        Route::delete('/document-categories/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'deleteDocumentCategory']);

        
        // Master Banks
        Route::get('/banks', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'getBanks']);
        Route::post('/banks', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'storeBank']);
        Route::put('/banks/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'updateBank']);
        Route::delete('/banks/{id}', [\App\Http\Controllers\Api\HrisMasterSettingController::class, 'deleteBank']);

        // Mutasi & Promosi
        Route::get('/mutations/summary', [\App\Http\Controllers\Api\HrisMutationController::class, 'summary']);
        Route::apiResource('/mutations', \App\Http\Controllers\Api\HrisMutationController::class);

        
        // Resign Karyawan
        Route::get('/resignations/summary', [\App\Http\Controllers\Api\HrisResignationController::class, 'summary']);
        Route::apiResource('/resignations', \App\Http\Controllers\Api\HrisResignationController::class);

        // Kontrak Kerja
        Route::apiResource('/contract-types', \App\Http\Controllers\Api\HrisContractTypeController::class);
        
        // HRIS Contracts with and without /hris/ prefix
        Route::get('/hris/contracts/summary', [\App\Http\Controllers\Api\HrisContractController::class, 'summary']);
        Route::get('/hris/contracts/{id}/download', [\App\Http\Controllers\Api\HrisContractController::class, 'downloadPdf']);
        Route::get('/hris/contracts', [\App\Http\Controllers\Api\HrisContractController::class, 'index']);
        Route::post('/hris/contracts', [\App\Http\Controllers\Api\HrisContractController::class, 'store']);
        Route::get('/hris/contracts/{id}', [\App\Http\Controllers\Api\HrisContractController::class, 'show']);
        Route::put('/hris/contracts/{id}', [\App\Http\Controllers\Api\HrisContractController::class, 'update']);
        Route::delete('/hris/contracts/{id}', [\App\Http\Controllers\Api\HrisContractController::class, 'destroy']);

        // HRIS Allowances with /hris/ prefix
        Route::get('/hris/allowance-types', [\App\Http\Controllers\Api\HrisPayrollMasterController::class, 'getAllowanceTypes']);
        Route::post('/hris/allowance-types', [\App\Http\Controllers\Api\HrisPayrollMasterController::class, 'storeAllowanceType']);
        Route::delete('/hris/allowance-types/{id}', [\App\Http\Controllers\Api\HrisPayrollMasterController::class, 'deleteAllowanceType']);
        Route::get('/hris/allowances', [\App\Http\Controllers\Api\HrisPayrollMasterController::class, 'getEmployeeAllowances']);
        Route::post('/hris/allowances', [\App\Http\Controllers\Api\HrisPayrollMasterController::class, 'storeEmployeeAllowance']);
        Route::delete('/hris/allowances/{id}', [\App\Http\Controllers\Api\HrisPayrollMasterController::class, 'deleteEmployeeAllowance']);

        // HRIS Payroll Reports (Encrypted Batch Report & On-the-fly Individual Payslip)
        Route::get('/hris/payrolls/{id}/batch-report', [\App\Http\Controllers\Api\HrisPayrollController::class, 'downloadBatchReport']);
        Route::get('/hris/payrolls/payslips/{id}/download-pdf', [\App\Http\Controllers\Api\HrisPayrollController::class, 'downloadIndividualPayslipPdf']);

        Route::get('/contracts/summary', [\App\Http\Controllers\Api\HrisContractController::class, 'summary']);
        Route::get('/contracts/{id}/download', [\App\Http\Controllers\Api\HrisContractController::class, 'downloadPdf']);
        Route::get('/contracts', [\App\Http\Controllers\Api\HrisContractController::class, 'index']);
        Route::post('/contracts', [\App\Http\Controllers\Api\HrisContractController::class, 'store']);
        Route::get('/contracts/{id}', [\App\Http\Controllers\Api\HrisContractController::class, 'show']);
        Route::put('/contracts/{id}', [\App\Http\Controllers\Api\HrisContractController::class, 'update']);
        Route::delete('/contracts/{id}', [\App\Http\Controllers\Api\HrisContractController::class, 'destroy']);

        Route::get('/compliance/summary', [\App\Http\Controllers\Api\HrisComplianceController::class, 'summary']);
          Route::get('/compliance', [\App\Http\Controllers\Api\HrisComplianceController::class, 'index']);
          Route::post('/compliance', [\App\Http\Controllers\Api\HrisComplianceController::class, 'store']);
          Route::put('/compliance/{id}', [\App\Http\Controllers\Api\HrisComplianceController::class, 'update']);
          Route::post('/compliance/{id}/renew', [\App\Http\Controllers\Api\HrisComplianceController::class, 'renew']);
          Route::delete('/compliance/{id}', [\App\Http\Controllers\Api\HrisComplianceController::class, 'destroy']);
          Route::get('/compliance/{id}/preview', [\App\Http\Controllers\Api\HrisComplianceController::class, 'previewDoc']);
          Route::get('/compliance/{id}/download', [\App\Http\Controllers\Api\HrisComplianceController::class, 'downloadDoc']);

          // 18. Training & Skills (Pelatihan & Sertifikasi)
          Route::get('/training/summary', [\App\Http\Controllers\Api\HrisTrainingController::class, 'summary']);
          Route::get('/training', [\App\Http\Controllers\Api\HrisTrainingController::class, 'index']);
          Route::post('/training', [\App\Http\Controllers\Api\HrisTrainingController::class, 'store']);
          Route::put('/training/{id}', [\App\Http\Controllers\Api\HrisTrainingController::class, 'update']);
          Route::post('/training/{id}/participants', [\App\Http\Controllers\Api\HrisTrainingController::class, 'addParticipants']);
          Route::put('/training/{id}/participants/{participantId}', [\App\Http\Controllers\Api\HrisTrainingController::class, 'updateParticipant']);
          Route::delete('/training/{id}/participants/{participantId}', [\App\Http\Controllers\Api\HrisTrainingController::class, 'removeParticipant']);
          Route::post('/training/{id}/complete', [\App\Http\Controllers\Api\HrisTrainingController::class, 'complete']);
          Route::delete('/training/{id}', [\App\Http\Controllers\Api\HrisTrainingController::class, 'destroy']);


    });

    // Support Chat Routes
    Route::get('/support/messages', [SupportChatController::class, 'index']);
    Route::get('/support/messages/{userId}', [SupportChatController::class, 'show']);
    Route::post('/support/messages', [SupportChatController::class, 'store']);
    Route::delete('/support/messages/{otherUserId}', [SupportChatController::class, 'clear']);
});

// ============================================================
// Superadmin routes
// ============================================================
Route::middleware(['auth:sanctum', 'superadmin'])->prefix('superadmin')->group(function () {
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard']);
    Route::get('/ai/query', [App\Http\Controllers\Api\SuperadminAIController::class, 'query']);

    Route::prefix('registrations')->group(function () {
        Route::get('/pending', [CompanyRegistrationController::class, 'pending']);
        Route::get('/approved', [CompanyRegistrationController::class, 'approved']);
        Route::get('/rejected', [CompanyRegistrationController::class, 'rejected']);
        Route::get('/statistics', [CompanyRegistrationController::class, 'statistics']);
        Route::get('/{id}', [CompanyRegistrationController::class, 'show']);
        Route::post('/{id}/approve', [CompanyRegistrationController::class, 'approve']);
        Route::post('/{id}/reject', [CompanyRegistrationController::class, 'reject']);
        Route::delete('/{id}', [CompanyRegistrationController::class, 'destroy']);
    });

    Route::get('/admins', [SuperAdminController::class, 'listAdmins']);
    Route::post('/admins', [SuperAdminController::class, 'createAdmin']);
    Route::get('/admins/{id}', [SuperAdminController::class, 'showAdmin']);
    Route::put('/admins/{id}/toggle', [SuperAdminController::class, 'toggleAdmin']);
    Route::put('/admins/{id}/reset-password', [SuperAdminController::class, 'resetAdminPassword']);
    Route::delete('/admins/{id}', [SuperAdminController::class, 'deleteAdmin']);

    Route::get('/subscriptions', [SuperAdminController::class, 'listSubscriptions']);
    Route::put('/subscriptions/{id}', [SuperAdminController::class, 'updateSubscription']);
    Route::post('/subscriptions/{id}/extend', [SuperAdminController::class, 'extendSubscription']);
    Route::delete('/subscriptions/{id}', [SuperAdminController::class, 'cancelSubscription']);

    Route::post('/plans/reorder', [PlanController::class, 'reorder']);
    Route::apiResource('plans', PlanController::class);

    Route::get('/transactions', [PaymentController::class, 'getTransactions']);
    Route::post('/transactions/{orderId}/sync', [PaymentController::class, 'syncTransaction']);

    Route::apiResource('vouchers', VoucherController::class);

    Route::post('/notifications/broadcast', [SuperadminNotificationController::class, 'broadcast']);
    Route::get('/notifications', [SuperadminNotificationController::class, 'index']);
    Route::get('/notifications/{id}', [SuperadminNotificationController::class, 'show']);
    Route::delete('/notifications/{id}', [SuperadminNotificationController::class, 'destroy']);
});

Route::fallback(fn() => response()->json(['message' => 'API endpoint not found'], 404));





// Direct Token & Auth Download for HRIS Payrolls (Admin & Employee)
Route::get('/hris/payrolls/{id}/batch-report', [\App\Http\Controllers\Api\HrisPayrollController::class, 'downloadBatchReport']);
Route::get('/hris/payrolls/payslips/{id}/download-pdf', [\App\Http\Controllers\Api\HrisPayrollController::class, 'downloadIndividualPayslipPdf']);
