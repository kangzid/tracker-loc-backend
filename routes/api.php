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
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
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
