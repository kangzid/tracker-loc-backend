<?php

namespace App\Http\Controllers\Api;

use App\Events\SupportMessageSent;
use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportChatController extends Controller
{
    /**
     * Get list of conversations (For Superadmin) or messages (For Admin)
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            // Get list of unique conversations for superadmin efficiently
            $conversations = SupportMessage::where('receiver_id', $user->id)
                ->orWhere('sender_id', $user->id)
                ->with(['sender:id,name,email', 'receiver:id,name,email'])
                ->latest()
                ->get()
                ->groupBy(function ($msg) use ($user) {
                    return $msg->sender_id == $user->id ? $msg->receiver_id : $msg->sender_id;
                })
                ->map(function ($group) {
                    $lastMessage = $group->first();
                    $otherUser = $lastMessage->sender_id == \Illuminate\Support\Facades\Auth::id() ? $lastMessage->receiver : $lastMessage->sender;
                    
                    // Fallback if user is deleted
                    if (!$otherUser) {
                        $otherUser = [
                            'id' => 0,
                            'name' => 'Deleted User',
                            'email' => 'deleted@locatrack.com'
                        ];
                    }

                    return [
                        'user' => $otherUser,
                        'last_message' => $lastMessage->message ?? '',
                        'unread_count' => $group->where('receiver_id', Auth::id())->where('is_read', false)->count(),
                        'updated_at' => $lastMessage->created_at ?? now(),
                    ];
                })
                ->values()
                ->sortByDesc('updated_at')
                ->values(); // Ensure it returns a clean array

            return response()->json($conversations);
        }

        // For Admin Tenant: Get messages with Superadmin
        $superAdmin = User::where('role', 'superadmin')->first();
        if (!$superAdmin) return response()->json([]);

        $messages = SupportMessage::where(function ($q) use ($user, $superAdmin) {
            $q->where('sender_id', $user->id)->where('receiver_id', $superAdmin->id);
        })->orWhere(function ($q) use ($user, $superAdmin) {
            $q->where('sender_id', $superAdmin->id)->where('receiver_id', $user->id);
        })
        ->visibleTo($user->id)
        ->orderBy('created_at', 'asc')
        ->get();

        // Mark as read for Admin
        SupportMessage::where('sender_id', $superAdmin->id)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json($messages);
    }

    /**
     * Get messages with a specific user (For Superadmin)
     */
    public function show(Request $request, $userId)
    {
        $user = Auth::user();
        
        $messages = SupportMessage::where(function ($q) use ($user, $userId) {
            $q->where('sender_id', $user->id)->where('receiver_id', $userId);
        })->orWhere(function ($q) use ($user, $userId) {
            $q->where('sender_id', $userId)->where('receiver_id', $user->id);
        })
        ->visibleTo($user->id)
        ->orderBy('created_at', 'asc')
        ->get();

        // Mark as read
        SupportMessage::where('sender_id', $userId)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json($messages);
    }

    /**
     * Send a message
     */
    public function store(Request $request)
    {
        $request->validate([
            'receiver_id' => 'nullable|exists:users,id',
            'message' => 'required|string',
        ]);

        $user = Auth::user();
        $receiverId = $request->receiver_id;

        // If admin sending, find superadmin automatically if receiver_id is null
        if ($user->isAdmin() && !$receiverId) {
            $superAdmin = User::where('role', 'superadmin')->first();
            $receiverId = $superAdmin->id;
        }

        if (!$receiverId) {
            return response()->json(['error' => 'Receiver not found'], 404);
        }

        $message = SupportMessage::create([
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'message' => $request->message,
            'is_read' => false,
        ]);

        \Illuminate\Support\Facades\Log::info('Support message created, broadcasting...', ['id' => $message->id]);
        broadcast(new SupportMessageSent($message));
        \Illuminate\Support\Facades\Log::info('Broadcast finished.');

        return response()->json($message);
    }

    /**
     * Delete chat history (for the current user only)
     */
    public function clear(Request $request, $otherUserId)
    {
        $user = Auth::user();

        // Mark messages as deleted by sender
        SupportMessage::where('sender_id', $user->id)
            ->where('receiver_id', $otherUserId)
            ->update(['sender_deleted_at' => now()]);

        // Mark messages as deleted by receiver
        SupportMessage::where('sender_id', $otherUserId)
            ->where('receiver_id', $user->id)
            ->update(['receiver_deleted_at' => now()]);

        return response()->json(['success' => true]);
    }
}
