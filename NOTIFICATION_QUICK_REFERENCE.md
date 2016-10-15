# Superadmin Notification System - Quick Reference Guide

## System Architecture Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    SUPERADMIN PORTAL                             │
│  (Web/Dashboard - Creates Broadcast Notifications)              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ POST /superadmin/notifications/broadcast
                              │ (with image file)
                              ▼
                    ┌──────────────────┐
                    │  Laravel Backend │
                    │  (API Server)    │
                    └──────────────────┘
                              │
                    ┌─────────┴─────────┐
                    │                   │
                    ▼                   ▼
            ┌────────────────┐  ┌───────────────┐
            │ Notifications  │  │ Storage Layer │
            │ Table (Hard)   │  │ (Image Files) │
            └────────────────┘  └───────────────┘
                    │
                    ▼
         ┌──────────────────────────┐
         │ AdminNotificationStatus  │
         │ (Per-Admin Tracking)     │
         │ - read_at               │
         │ - deleted_by_admin_at   │
         └──────────────────────────┘
                    │
            ┌───────┴────────┐
            │                │
            ▼                ▼
   ┌─────────────────┐  ┌──────────────────┐
   │  Admin #1       │  │  Admin #2        │
   │  Dashboard      │  │  Dashboard       │
   │  (Svelte App)   │  │  (Svelte App)    │
   │  Polls every    │  │  Polls every     │
   │  30 seconds     │  │  30 seconds      │
   └─────────────────┘  └──────────────────┘
```

## Database Relationships

```
Users (Superadmin)
    │
    ├─────────────────────────────────┐
    │                                 │
    ▼                                 ▼
Notifications               AdminNotificationStatus
(created_by ────FK────)    (admin_id ──FK──) Users (Admin)
    │                       (notification_id ──FK──) Notifications
    │                       (read_at, deleted_by_admin_at)
    └─────────────FK────────────┘
    
Unique Constraint: (admin_id, notification_id)
```

## Request/Response Examples

### 1. Superadmin Broadcasts Notification with Image

```
REQUEST:
POST /api/superadmin/notifications/broadcast
Content-Type: multipart/form-data
Authorization: Bearer {superadmin_token}

Form Data:
├── type: "offer"
├── title: "Spring Sale - 30% Off"
├── message: "Save up to 30% on all annual plans this month"
├── image: [binary file data - spring_sale.jpg]
├── recipient_type: "specific"
└── admin_ids: [1, 3, 5]


RESPONSE: 201 Created
{
  "id": 42,
  "type": "offer",
  "title": "Spring Sale - 30% Off",
  "message": "Save up to 30% on all annual plans this month",
  "image_url": "/storage/notifications/1/2026-04-09/1712674200_k9m2n1.jpg",
  "recipient_type": "specific",
  "admin_ids": [1, 3, 5],
  "sent_count": 3,
  "created_at": "2026-04-09T14:30:00Z"
}
```

### 2. Admin #1 Polls for Notifications (Every 30 seconds)

```
REQUEST:
GET /api/admin/notifications?page=1
Authorization: Bearer {admin1_token}


RESPONSE: 200 OK
{
  "data": [
    {
      "id": 42,
      "title": "Spring Sale - 30% Off",
      "message": "Save up to 30% on all annual plans this month",
      "image_url": "/storage/notifications/1/2026-04-09/1712674200_k9m2n1.jpg",
      "type": "offer",
      "read_at": null,                    ← Not yet read
      "deleted_by_admin_at": null,        ← Not deleted
      "created_at": "2026-04-09T14:30:00Z",
      "created_by": {
        "id": 1,
        "name": "Platform Admin"
      }
    }
  ],
  "pagination": {
    "current_page": 1,
    "total": 1,
    "per_page": 20
  }
}
```

### 3. Admin #1 Marks as Read

```
REQUEST:
POST /api/admin/notifications/42/read
Authorization: Bearer {admin1_token}

RESPONSE: 200 OK
{
  "message": "Notification marked as read",
  "notification_id": 42,
  "read_at": "2026-04-09T14:35:22Z"
}
```

### 4. Admin #1 Soft Deletes (Only for themselves)

```
REQUEST:
DELETE /api/admin/notifications/42
Authorization: Bearer {admin1_token}

RESPONSE: 200 OK
{
  "message": "Notification deleted",
  "notification_id": 42,
  "deleted_by_admin_at": "2026-04-09T14:36:45Z"
}

Effect:
├── admin_notification_status.deleted_by_admin_at = 2026-04-09 14:36:45
├── Admin #1: Notification HIDDEN ❌
├── Admin #3: Notification VISIBLE ✅
├── Admin #5: Notification VISIBLE ✅
├── Superadmin: Notification VISIBLE ✅
├── Image file: STILL EXISTS
└── DB record: STILL EXISTS
```

### 5. Superadmin Hard Deletes (Everyone affected)

```
REQUEST:
DELETE /api/superadmin/notifications/42
Authorization: Bearer {superadmin_token}

RESPONSE: 204 No Content

Effect:
├── notifications table: RECORD DELETED ❌
├── Image file: DELETED ❌
├── admin_notification_status: ALL 3 RECORDS DELETED ❌
├── Admin #1: Notification GONE ❌
├── Admin #3: Notification GONE ❌
├── Admin #5: Notification GONE ❌
└── Superadmin: Notification GONE ❌
```

## Timeline Example

```
09:00 AM - Superadmin creates broadcast
          ├─ POST /superadmin/notifications/broadcast
          └─ Sent to Admin #1, #3, #5

09:00:30 - Admin #1 polls (every 30 sec)
          ├─ GET /admin/notifications?page=1
          ├─ Receives notification #42
          └─ read_at: null, deleted_by_admin_at: null

09:01:00 - Admin #1 opens notification
          ├─ Sees image: spring_sale.jpg
          └─ Clicks "Mark as Read"

09:01:05 - Admin #1 marks as read
          ├─ POST /admin/notifications/42/read
          ├─ Updates: read_at = 09:01:05
          └─ Notification shown as "read" in UI

09:02:00 - Admin #1 decides to hide it
          ├─ DELETE /admin/notifications/42
          ├─ Updates: deleted_by_admin_at = 09:02:00
          └─ Notification removed from Admin #1's list

09:02:30 - Admin #3 polls
          ├─ Still sees notification #42
          ├─ read_at: null (they haven't read it)
          └─ deleted_by_admin_at: null (still visible)

09:05:00 - Superadmin wants to remove the offer
          ├─ DELETE /superadmin/notifications/42
          ├─ Hard deletes notification
          ├─ Deletes image file
          ├─ Deletes all admin_notification_status records
          └─ Notification GONE for everyone

09:05:30 - Admin #3 polls again
          ├─ Notification #42 NO LONGER in response
          └─ Admin #3 cannot see it anymore
```

## Storage Structure Example

```
storage/
└── app/
    └── public/
        └── notifications/
            ├── 1/                           (Admin ID)
            │   └── 2026-04-09/              (Date folder)
            │       ├── 1712674200_a1b2c3.jpg
            │       ├── 1712674500_d4e5f6.jpg
            │       └── 1712674800_g7h8i9.jpg
            ├── 3/
            │   └── 2026-04-09/
            │       └── 1712674850_j0k1l2.jpg
            └── 5/
                └── 2026-04-09/
                    └── 1712674900_m3n4o5.jpg
```

## Table Structure

### notifications table (excerpt)
```sql
id            │ created_by  │ type       │ recipient_type │ admin_ids │ image_url         │ deleted_at
──────────────┼─────────────┼────────────┼────────────────┼───────────┼───────────────────┼──────────
42            │ 1 (superadmin)│ broadcast│ specific       │ [1,3,5]   │ /storage/notif... │ null
```

### admin_notification_status table
```sql
id │ admin_id │ notification_id │ read_at           │ deleted_by_admin_at
───┼──────────┼─────────────────┼───────────────────┼─────────────────────
 1 │ 1        │ 42              │ 2026-04-09 14:35  │ 2026-04-09 14:36:45
 2 │ 3        │ 42              │ null              │ null
 3 │ 5        │ 42              │ null              │ null
```

## Security Rules

✅ **Can be broadcast to:**
- All admins across all tenants
- Specific admin list (by admin_id)

✅ **Admin can only:**
- See their own notifications
- See notifications created by superadmin
- Mark their own notifications as read
- Soft delete (hide) from their view

❌ **Admin cannot:**
- See other admin's delete status
- Hard delete any notification
- Modify notification content

✅ **Superadmin can:**
- Create notifications
- View all notifications
- Hard delete (removes for everyone + image)
- See recipient status

## Integration with Svelte Frontend

### Simple Polling Component (Pseudocode)
```javascript
<script>
  let notifications = [];
  let isLoading = false;

  async function fetchNotifications() {
    isLoading = true;
    try {
      const res = await fetch('/api/admin/notifications?page=1', {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const data = await res.json();
      notifications = data.data;
    } catch (e) {
      console.error('Error fetching notifications:', e);
    } finally {
      isLoading = false;
    }
  }

  async function markAsRead(notificationId) {
    await fetch(`/api/admin/notifications/${notificationId}/read`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` }
    });
    fetchNotifications(); // Refresh
  }

  async function deleteNotification(notificationId) {
    await fetch(`/api/admin/notifications/${notificationId}`, {
      method: 'DELETE',
      headers: { 'Authorization': `Bearer ${token}` }
    });
    fetchNotifications(); // Refresh
  }

  // Poll every 30 seconds
  setInterval(fetchNotifications, 30000);

  // Initial fetch
  fetchNotifications();
</script>

{#each notifications as notif (notif.id)}
  <div class="notification">
    {#if notif.image_url}
      <img src={notif.image_url} alt={notif.title} />
    {/if}
    <h3>{notif.title}</h3>
    <p>{notif.message}</p>
    <p class="meta">
      From: {notif.created_by.name} • 
      {new Date(notif.created_at).toLocaleString()}
    </p>
    {#if !notif.read_at}
      <button on:click={() => markAsRead(notif.id)}>Mark as Read</button>
    {/if}
    <button on:click={() => deleteNotification(notif.id)}>Remove</button>
  </div>
{/each}
```

## Testing Checklist

### Superadmin Tests
- [ ] POST broadcast with image to all admins
- [ ] POST broadcast with image to specific admins [1, 3]
- [ ] GET list of created notifications
- [ ] GET single notification details
- [ ] DELETE notification (verify image deleted, all admins affected)
- [ ] Test image size limit (5MB)
- [ ] Test invalid mime types (should reject)

### Admin Tests
- [ ] GET notifications via polling
- [ ] Verify excluded soft-deleted notifications
- [ ] POST mark as read (read_at updated)
- [ ] DELETE soft delete (deleted_by_admin_at updated)
- [ ] Pagination (page=1, page=2)
- [ ] Other admin's soft delete doesn't affect my view

### Integration Tests
- [ ] Superadmin creates → Admins receive via polling
- [ ] Image displays correctly in admin UI
- [ ] Polling refresh shows new notifications
- [ ] Multiple admins soft delete same notification independently

## Performance Expectations

- **Broadcast Creation:** <500ms (including image upload)
- **Polling Response:** <100ms (paginated, 20 per page)
- **Mark as Read:** <50ms
- **Soft Delete:** <50ms
- **Hard Delete:** <200ms (includes image deletion)

## Error Handling

```
Validation Errors (422)
├── Invalid type: must be offer|news|info
├── Image too large: max 5MB
├── Invalid mime type: must be jpeg|png|jpg|webp
├── Missing required fields: title, message
└── admin_ids invalid: must be array if recipient_type=specific

Authorization Errors (403)
├── Not superadmin (broadcast endpoints)
├── Not authenticated (missing Bearer token)
└── Trying to hard-delete (admin)

Not Found Errors (404)
└── Notification ID doesn't exist

Business Logic Errors (400)
├── recipient_type=specific but admin_ids empty
├── Trying to delete already-deleted notification (soft)
└── Image deletion failed during hard delete
```

---

**Version:** 1.0  
**Last Updated:** April 9, 2026  
**Status:** ✅ Implementation Complete and Ready for Testing
