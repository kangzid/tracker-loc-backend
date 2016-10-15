# Svelte Implementation - Quick Start Guide

## Files to Create

```
src/lib/
├── stores/
│   └── notificationStore.ts           ← Create this first
├── services/
│   └── notificationService.ts         ← Then this
├── types/
│   └── notification.ts                ← And this
└── components/
    ├── NotificationBell.svelte
    ├── AdminNotificationCenter.svelte
    ├── NotificationCard.svelte
    ├── NotificationModal.svelte
    ├── NotificationList.svelte
    ├── LoadingSkeletons.svelte
    └── ErrorMessage.svelte

src/routes/admin/notifications/
└── +page.svelte                       ← Page component

src/
└── +layout.svelte                     ← Update (add polling start)
```

## Step-by-Step Implementation Order

### Step 1: Types (5 minutes)
Create `src/lib/types/notification.ts` with Notification and NotificationResponse interfaces.

### Step 2: Store (10 minutes)
Create `src/lib/stores/notificationStore.ts`:
- writable stores for notifications, isLoading, error, etc
- derived stores for unreadCount
- update functions

### Step 3: Service (15 minutes)
Create `src/lib/services/notificationService.ts`:
- getNotifications()
- markAsRead()
- deleteNotification()
- startPolling()
- stopPolling()

### Step 4: Helper Components (30 minutes)
Create simple utility components:
- LoadingSkeletons.svelte
- ErrorMessage.svelte

### Step 5: Card Components (30 minutes)
Create main display components:
- NotificationCard.svelte
- NotificationModal.svelte
- NotificationBell.svelte

### Step 6: Main Container (15 minutes)
Create AdminNotificationCenter.svelte with polling logic

### Step 7: Page & Layout (10 minutes)
Create notification page and update layout

### Step 8: Testing (20 minutes)
Test all functionality with backend API

---

## Copy-Paste Code Snippets

### Minimal notificationStore.ts
```typescript
import { writable, derived } from 'svelte/store';

export interface Notification {
  id: number;
  title: string;
  message: string;
  image_url: string | null;
  type: 'offer' | 'news' | 'info';
  read_at: string | null;
  deleted_by_admin_at: string | null;
  created_at: string;
  created_by: { id: number; name: string };
}

// Writable stores
export const notifications = writable<Notification[]>([]);
export const isLoading = writable(false);
export const error = writable<string | null>(null);
export const currentPage = writable(1);
export const totalPages = writable(1);

// Derived stores
export const unreadCount = derived(notifications, ($notifications) =>
  $notifications.filter((n) => !n.read_at && !n.deleted_by_admin_at).length
);
```

### Minimal notificationService.ts
```typescript
import { notifications, isLoading, error, currentPage, totalPages } from '$lib/stores/notificationStore';

class NotificationService {
  private pollingInterval: NodeJS.Timeout | null = null;

  async getNotifications(page: number = 1) {
    isLoading.set(true);
    try {
      const response = await fetch(`/api/admin/notifications?page=${page}`, {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
      });
      const data = await response.json();
      notifications.set(data.data);
      currentPage.set(data.pagination.current_page);
      totalPages.set(data.pagination.last_page);
      error.set(null);
    } catch (err) {
      error.set(err.message);
    } finally {
      isLoading.set(false);
    }
  }

  async markAsRead(notificationId: number) {
    await fetch(`/api/admin/notifications/${notificationId}/read`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
    });
    // Refetch notifications
    await this.getNotifications(1);
  }

  async deleteNotification(notificationId: number) {
    await fetch(`/api/admin/notifications/${notificationId}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
    });
    // Refetch notifications
    await this.getNotifications(1);
  }

  startPolling(intervalMs: number = 30000) {
    this.getNotifications(1);
    this.pollingInterval = setInterval(() => {
      this.getNotifications(1);
    }, intervalMs);
  }

  stopPolling() {
    if (this.pollingInterval) {
      clearInterval(this.pollingInterval);
      this.pollingInterval = null;
    }
  }
}

export const notificationService = new NotificationService();
```

### Minimal NotificationCard.svelte
```svelte
<script>
  export let notification;
  import { notificationService } from '$lib/services/notificationService';
  
  let showModal = false;
</script>

<div class="card" class:unread={!notification.read_at}>
  {#if notification.image_url}
    <img src={notification.image_url} alt={notification.title} />
  {/if}
  <h3>{notification.title}</h3>
  <p>{notification.message}</p>
  <small>{notification.type} • {new Date(notification.created_at).toLocaleDateString()}</small>
  
  <div class="actions">
    {#if !notification.read_at}
      <button on:click={() => notificationService.markAsRead(notification.id)}>
        Mark as Read
      </button>
    {/if}
    <button on:click={() => notificationService.deleteNotification(notification.id)}>
      Delete
    </button>
    <button on:click={() => showModal = true}>View</button>
  </div>
  
  {#if showModal}
    <div class="modal" on:click={() => showModal = false}>
      <div class="modal-content" on:click|stopPropagation>
        <button on:click={() => showModal = false}>Close</button>
        {#if notification.image_url}
          <img src={notification.image_url} alt={notification.title} />
        {/if}
        <h2>{notification.title}</h2>
        <p>{notification.message}</p>
      </div>
    </div>
  {/if}
</div>

<style>
  .card { border: 1px solid #ddd; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
  .card.unread { background: #f0f9ff; border-left: 4px solid #3b82f6; }
  img { width: 100%; border-radius: 8px; margin-bottom: 1rem; }
  .actions { display: flex; gap: 0.5rem; margin-top: 1rem; }
  button { padding: 0.5rem 1rem; cursor: pointer; }
  .modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; }
  .modal-content { background: white; padding: 2rem; border-radius: 8px; max-width: 600px; }
</style>
```

### Minimal AdminNotificationCenter.svelte
```svelte
<script>
  import { onMount } from 'svelte';
  import { notifications, isLoading, error, unreadCount, totalPages, currentPage } from '$lib/stores/notificationStore';
  import { notificationService } from '$lib/services/notificationService';
  import NotificationCard from './NotificationCard.svelte';
  import LoadingSkeletons from './LoadingSkeletons.svelte';
  
  onMount(async () => {
    notificationService.startPolling(30000);
    return () => notificationService.stopPolling();
  });
</script>

<div class="container">
  <h2>Notifications ({$unreadCount} unread)</h2>
  
  {#if $isLoading}
    <LoadingSkeletons count={3} />
  {:else if $error}
    <p style="color: red;">Error: {$error}</p>
  {:else if $notifications.length === 0}
    <p>No notifications</p>
  {:else}
    {#each $notifications as notif (notif.id)}
      <NotificationCard notification={notif} />
    {/each}
  {/if}
</div>

<style>
  .container { padding: 2rem; }
</style>
```

### Update +layout.svelte
```svelte
<script>
  import { onMount } from 'svelte';
  import { notificationService } from '$lib/services/notificationService';
  import NotificationBell from '$lib/components/NotificationBell.svelte';
  
  onMount(() => {
    notificationService.startPolling(30000);
    return () => notificationService.stopPolling();
  });
</script>

<header>
  <NotificationBell />
</header>

<main>
  <slot />
</main>
```

### Create /admin/notifications page
```svelte
<script>
  import AdminNotificationCenter from '$lib/components/AdminNotificationCenter.svelte';
</script>

<AdminNotificationCenter />
```

---

## Testing API Manually

### Before Svelte Implementation

Test backend API directly dengan curl atau Postman:

```bash
# Get notifications
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8000/api/admin/notifications?page=1

# Mark as read
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8000/api/admin/notifications/1/read

# Delete
curl -X DELETE -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:8000/api/admin/notifications/1
```

Semua endpoints sudah ada di Postman collection: `docs/LocaTrack-Backend-API.postman_collection.json`

---

## Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| CORS error | Check backend CORS config (config/cors.php) |
| 401 Unauthorized | Verify token in localStorage, check login |
| Notifications empty | Check database has admin_notification_status records |
| Image not showing | Check storage symlink exists: `php artisan storage:link` |
| Polling stops | Check browser console for errors |
| High memory | Check for setInterval cleanup in onDestroy |

---

## Files Reference

| File | Purpose |
|------|---------|
| NOTIFICATION_SYSTEM.md | Backend API documentation |
| SVELTE_NOTIFICATION_IMPLEMENTATION.md | Detailed implementation guide |
| SVELTE_ANTIGRAVITY_PROMPT.md | Comprehensive AntiGravity prompt |
| SVELTE_QUICKSTART.md | This file |

---

## Key Points to Remember

1. ✅ Backend API is ready at `/api/admin/notifications`
2. ✅ Polling every 30 seconds from frontend
3. ✅ Soft delete only hides from current admin's view
4. ✅ Bearer token in Authorization header
5. ✅ Images stored at `/storage/notifications/{admin_id}/...`
6. ✅ Unread count derived from `read_at === null`

---

## Next Steps After Implementation

1. Test in browser with real API
2. Add notifications to mobile app (Flutter)
3. Consider WebSocket upgrade for real-time
4. Add notification sounds & browser push (optional)
5. Analytics & engagement tracking (future)

---

**Ready to start?** Pick the AntiGravity prompt file and paste it into your IDE! 🚀

Atau bisa pakai file ini untuk manual implementation sambil follow SVELTE_NOTIFICATION_IMPLEMENTATION.md untuk detail lebih lengkap.

**Estimated Time:** 2-4 hours development + testing
