<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\AdminNotificationStatus;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Employee - Get employee notifications (task completion)
     */
    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $notifications = Notification::where('employee_id', $employee->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($notifications);
    }

    /**
     * Employee - Get unread notifications
     */
    public function unread(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $notifications = Notification::where('employee_id', $employee->id)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    /**
     * Employee - Mark notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $notification = Notification::where('employee_id', $employee->id)
            ->findOrFail($id);

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * Employee - Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        Notification::where('employee_id', $employee->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    /**
     * Admin - Get admin notifications (broadcast + task completion)
     * For polling support (called every 30 seconds from Svelte)
     */
    public function getAdminNotifications(Request $request)
    {
        $adminId = $request->user()->id;

        // Get all notifications for this admin (not soft deleted)
        $notifications = AdminNotificationStatus::where('admin_id', $adminId)
            ->whereNull('deleted_by_admin_at')
            ->with([
                'notification' => function ($query) {
                    $query->withTrashed()->select('id', 'created_by', 'title', 'message', 'image_url', 'type', 'recipient_type', 'created_at');
                }
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        // Add full image URLs
        $notifications->getCollection()->transform(function ($status) {
            if ($status->notification && $status->notification->image_url) {
                $parts = explode('/', $status->notification->image_url);
                $adminId = $parts[1];
                $date = $parts[2];
                $filename = $parts[3];
                $status->notification->image_full_url = url("/api/images/notifications/{$adminId}/{$date}/{$filename}");
            }
            return $status;
        });

        return response()->json($notifications);
    }

    /**
     * Admin - Mark notification as read
     */
    public function markAdminNotificationAsRead(Request $request, $notificationId)
    {
        $adminId = $request->user()->id;

        $status = AdminNotificationStatus::where('admin_id', $adminId)
            ->where('notification_id', $notificationId)
            ->firstOrFail();

        $status->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * Admin - Delete notification (soft delete for admin only)
     */
    public function deleteAdminNotification(Request $request, $notificationId)
    {
        $adminId = $request->user()->id;

        $status = AdminNotificationStatus::where('admin_id', $adminId)
            ->where('notification_id', $notificationId)
            ->firstOrFail();

        $status->update(['deleted_by_admin_at' => now()]);

        return response()->json(['message' => 'Notification removed']);
    }
}