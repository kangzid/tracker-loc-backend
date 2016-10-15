# Superadmin Broadcast Notification System

## Overview

This document describes the superadmin broadcast notification system that allows superadmins to send notifications (offers, news, announcements) to admin tenants with optional image attachments.

## Architecture

### Database Schema

#### `notifications` table (columns added)
- `created_by` - Foreign key to `users` table (superadmin who created the notification)
- `image_url` - Path to uploaded image (optional)
- `recipient_type` - Enum: `task_completion`, `broadcast`
- `admin_ids` - JSON array of admin IDs for targeted broadcasts
- `deleted_at` - Soft delete timestamp

#### `admin_notification_status` table (new)
Tracks per-admin notification read/delete status to enable soft delete behavior:
```sql
- admin_id (FK to users)
- notification_id (FK to notifications)
- read_at (timestamp, nullable)
- deleted_by_admin_at (timestamp, nullable)
- unique constraint: (admin_id, notification_id)
```

### Models

#### AdminNotificationStatus
```php
class AdminNotificationStatus extends Model {
    // Tracks read_at and deleted_by_admin_at for each admin
    public function admin() { return $this->belongsTo(User::class); }
    public function notification() { return $this->belongsTo(Notification::class); }
}
```

#### Notification (updated)
```php
class Notification extends Model {
    use SoftDeletes;
    
    public function employee() { return $this->belongsTo(Employee::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function adminStatus() { return $this->hasMany(AdminNotificationStatus::class); }
}
```

## API Endpoints

### Superadmin Endpoints (requires `superadmin` middleware)

#### 1. Broadcast Notification with Image
**POST** `/api/superadmin/notifications/broadcast`

Request (multipart/form-data):
```json
{
    "type": "offer|news|info",
    "title": "string (required)",
    "message": "string (required)",
    "image": "file (optional, max 5MB, jpeg|png|jpg|webp)",
    "recipient_type": "all|specific",
    "admin_ids": "[1, 2, 3]" // Required if recipient_type=specific
}
```

Response (201 Created):
```json
{
    "id": 1,
    "type": "offer",
    "title": "Special Discount",
    "message": "Get 20% off",
    "image_url": "/storage/notifications/1/2026-04-09/abc123.jpg",
    "recipient_type": "specific",
    "admin_ids": [1, 2, 3],
    "sent_count": 3,
    "created_at": "2026-04-09T10:30:00Z"
}
```

#### 2. List Notifications (created by this superadmin)
**GET** `/api/superadmin/notifications?page=1`

Response (200 OK):
```json
{
    "data": [
        {
            "id": 1,
            "type": "offer",
            "title": "Special Discount",
            "message": "Get 20% off",
            "image_url": "/storage/notifications/1/2026-04-09/abc123.jpg",
            "recipient_type": "specific",
            "admin_ids": [1, 2, 3],
            "created_at": "2026-04-09T10:30:00Z"
        }
    ],
    "pagination": { "current_page": 1, "total": 10, "per_page": 20 }
}
```

#### 3. View Single Notification
**GET** `/api/superadmin/notifications/{id}`

Response (200 OK):
```json
{
    "id": 1,
    "type": "offer",
    "title": "Special Discount",
    "message": "Get 20% off",
    "image_url": "/storage/notifications/1/2026-04-09/abc123.jpg",
    "recipient_type": "all",
    "admin_ids": null,
    "created_by": { "id": 1, "name": "Superadmin", "email": "super@admin.com" },
    "created_at": "2026-04-09T10:30:00Z"
}
```

#### 4. Delete Notification (Hard Delete)
**DELETE** `/api/superadmin/notifications/{id}`

Response (204 No Content)

**Side Effects:**
- Deletes the notification from database (hard delete)
- Deletes image file from storage
- Cascades delete all `admin_notification_status` records
- Notification completely removed for all admins

---

### Admin Endpoints (requires `auth:sanctum`)

#### 1. Get Broadcast Notifications (Polling)
**GET** `/api/admin/notifications?page=1`

Use Case: Call this endpoint every 30 seconds from Svelte frontend for near-real-time updates

Response (200 OK):
```json
{
    "data": [
        {
            "id": 1,
            "title": "Special Discount",
            "message": "Get 20% off",
            "image_url": "/storage/notifications/1/2026-04-09/abc123.jpg",
            "type": "offer",
            "read_at": null,
            "deleted_by_admin_at": null,
            "created_at": "2026-04-09T10:30:00Z",
            "created_by": { "id": 1, "name": "Superadmin" }
        }
    ],
    "pagination": { "current_page": 1, "total": 5, "per_page": 20 }
}
```

**Query Features:**
- Excludes notifications with `deleted_by_admin_at` (soft deleted by this admin)
- Ordered by `created_at DESC` (newest first)
- Paginated 20 per page
- Shows `read_at` status

#### 2. Mark Notification as Read
**POST** `/api/admin/notifications/{notificationId}/read`

Response (200 OK):
```json
{
    "message": "Notification marked as read",
    "notification_id": 1,
    "read_at": "2026-04-09T10:35:00Z"
}
```

#### 3. Delete Notification (Soft Delete)
**DELETE** `/api/admin/notifications/{notificationId}`

Response (200 OK):
```json
{
    "message": "Notification deleted",
    "notification_id": 1,
    "deleted_by_admin_at": "2026-04-09T10:40:00Z"
}
```

**Side Effects:**
- Only marks `deleted_by_admin_at` for this admin
- Notification remains in database (soft delete)
- Superadmin still sees this notification
- Other admins still see this notification
- Excluded from future `getAdminNotifications()` queries for this admin

---

## Delete Behavior Comparison

### Superadmin Delete (Hard Delete)
```
DELETE /superadmin/notifications/1
↓
- Notification record completely removed from DB
- Image file deleted from storage
- All admin_notification_status records deleted
- Notification gone for ALL admins
```

### Admin Delete (Soft Delete)
```
DELETE /admin/notifications/1
↓
- admin_notification_status.deleted_by_admin_at = NOW()
- Notification record still exists in DB
- Image file still exists
- Notification hidden ONLY for this admin
- Other admins still see it
- Superadmin still sees it
```

## Image Storage

### Directory Structure
```
storage/app/public/notifications/
├── {admin_id}/
│   └── {Y-m-d}/
│       └── {timestamp}_{random}.jpg
```

Example: `storage/app/public/notifications/5/2026-04-09/1712660400_a1b2c3d4.jpg`

### Image Requirements
- **Max Size:** 5 MB
- **Formats:** JPEG, PNG, JPG, WebP
- **Quality:** 85% JPEG compression for HD quality
- **Automatic Cleanup:** Deleted when superadmin removes notification

## Frontend Integration (Svelte)

### Polling Implementation
```javascript
// In admin dashboard component
setInterval(async () => {
    const response = await fetch('/api/admin/notifications?page=1', {
        headers: { 'Authorization': `Bearer ${token}` }
    });
    const data = await response.json();
    
    // Update notifications UI with data.data
    // data.data contains undeleted notifications
}, 30000); // Poll every 30 seconds
```

### Mark as Read
```javascript
await fetch('/api/admin/notifications/{id}/read', {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` }
});
```

### Soft Delete
```javascript
await fetch('/api/admin/notifications/{id}', {
    method: 'DELETE',
    headers: { 'Authorization': `Bearer ${token}` }
});
```

## Postman Collection

All endpoints have been added to `docs/testing_postman_collection.json`:

**Admin Endpoints (Polling):**
- `Get Admin Broadcast Notifications (Polling)` - GET /admin/notifications
- `Mark Admin Notification as Read` - POST /admin/notifications/{id}/read
- `Delete Admin Notification (Soft Delete)` - DELETE /admin/notifications/{id}

**Superadmin Endpoints:**
- `🔔 SUPERADMIN - Broadcast Notification with Image` - POST /superadmin/notifications/broadcast
- `🔔 SUPERADMIN - List Broadcast Notifications` - GET /superadmin/notifications
- `🔔 SUPERADMIN - View Broadcast Notification` - GET /superadmin/notifications/{id}
- `🔔 SUPERADMIN - Delete Broadcast Notification (Hard Delete + Image)` - DELETE /superadmin/notifications/{id}

## Testing Checklist

- [ ] Superadmin can broadcast notification to all admins
- [ ] Superadmin can broadcast notification to specific admins only
- [ ] Image upload works (test with 5MB limit)
- [ ] Image deleted when superadmin deletes notification
- [ ] Admin receives notification via polling endpoint
- [ ] Admin can mark notification as read
- [ ] Admin soft delete hides notification for that admin only
- [ ] Superadmin still sees admin's soft-deleted notification
- [ ] Other admins still see admin's soft-deleted notification
- [ ] Superadmin hard delete removes notification for all admins
- [ ] Notifications paginated 20 per page
- [ ] Notifications ordered by created_at DESC

## Security Considerations

1. **Tenant Isolation:** Admin can only see/interact with their own notifications via AdminNotificationStatus
2. **Authorization:** Superadmin endpoints require `superadmin` middleware
3. **File Upload:** Image validation (size, mime type, storage location)
4. **Soft Delete:** Prevents accidental data loss while providing per-admin privacy
5. **Hard Delete:** Only superadmin can permanently remove notifications

## Performance Notes

- **Polling Frequency:** 30 seconds recommended (adjustable in frontend)
- **Pagination:** 20 per page for /admin/notifications (configurable)
- **Eager Loading:** Image_url, title, message, created_by preloaded to reduce queries
- **Index:** Unique constraint on (admin_id, notification_id) for fast lookups

## Future Enhancements

1. WebSocket integration for real-time notifications instead of polling
2. Notification templates for common message types
3. Scheduling notifications for future dates
4. Notification read receipts (percentage of admins who read)
5. Notification analytics and engagement tracking
