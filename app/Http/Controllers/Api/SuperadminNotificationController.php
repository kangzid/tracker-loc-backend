<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\AdminNotificationStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SuperadminNotificationController extends Controller
{
    /**
     * Create broadcast notification with optional image
     */
    public function broadcast(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:offer,news,info',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'recipient_type' => 'required|in:all,specific',
            'admin_ids' => 'required_if:recipient_type,specific|array',
            'admin_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle image upload
        $imageUrl = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $path = "notifications/{$request->user()->id}/" . now()->format('Y-m-d');
            $filename = time() . '.' . $image->getClientOriginalExtension();

            Storage::disk('public')->putFileAs($path, $image, $filename);
            $imageUrl = "notifications/{$request->user()->id}/" . now()->format('Y-m-d') . "/{$filename}";
        }

        // Determine recipient admin IDs
        $recipientAdminIds = $request->recipient_type === 'all'
            ? \App\Models\User::where('role', 'admin')->pluck('id')->toArray()
            : $request->admin_ids;

        // Create notification
        $notification = Notification::create([
            'created_by' => $request->user()->id,
            'title' => $request->title,
            'message' => $request->message,
            'image_url' => $imageUrl,
            'type' => $request->type,
            'recipient_type' => 'broadcast',
            'admin_ids' => $recipientAdminIds,
        ]);

        // Create admin notification status for each recipient
        foreach ($recipientAdminIds as $adminId) {
            AdminNotificationStatus::create([
                'admin_id' => $adminId,
                'notification_id' => $notification->id,
            ]);
        }

        // Add full image URL for response
        $notificationData = $notification->load('adminStatus');
        if ($notificationData->image_url) {
            // Parse image_url to get adminId, date, filename
            $parts = explode('/', $notificationData->image_url);
            $adminId = $parts[1];
            $date = $parts[2];
            $filename = $parts[3];
            $notificationData->image_full_url = url("/api/images/notifications/{$adminId}/{$date}/{$filename}");
        }

        return response()->json([
            'message' => 'Notification sent to ' . count($recipientAdminIds) . ' admins',
            'data' => $notificationData,
            'sent_count' => count($recipientAdminIds),
        ], 201);
    }

    /**
     * Get all broadcast notifications created by superadmin
     */
    public function index(Request $request)
    {
        // Get all broadcast notifications (Always include trashed for history transparency)
        $notifications = Notification::withTrashed()
            ->where(function($query) {
                $query->where('recipient_type', 'broadcast')
                      ->orWhereNotNull('admin_ids');
            })
            ->with(['adminStatus'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Add full image URLs
        $notifications->getCollection()->transform(function ($notification) {
            if ($notification->image_url) {
                $parts = explode('/', $notification->image_url);
                $adminId = $parts[1];
                $date = $parts[2];
                $filename = $parts[3];
                $notification->image_full_url = url("/api/images/notifications/{$adminId}/{$date}/{$filename}");
            }
            return $notification;
        });

        return response()->json($notifications);
    }

    /**
     * Get single notification
     */
    public function show($id)
    {
        $notification = Notification::withTrashed()->findOrFail($id);

        $notification->load(['adminStatus']);

        // Add full image URL
        if ($notification->image_url) {
            $parts = explode('/', $notification->image_url);
            $adminId = $parts[1];
            $date = $parts[2];
            $filename = $parts[3];
            $notification->image_full_url = url("/api/images/notifications/{$adminId}/{$date}/{$filename}");
        }

        return response()->json($notification);
    }

    /**
     * Delete notification (hard delete) - also delete image
     */
    public function destroy($id)
    {
        $notification = Notification::withTrashed()->findOrFail($id);

        // Delete image if exists
        if ($notification->image_url) {
            Storage::disk('public')->delete($notification->image_url);
        }

        // Delete admin notification status
        AdminNotificationStatus::where('notification_id', $notification->id)->delete();

        // Hard delete notification
        $notification->forceDelete();

        return response()->json([
            'message' => 'Notification deleted successfully',
            'deleted_count' => count($notification->admin_ids ?? []),
        ]);
    }
}
