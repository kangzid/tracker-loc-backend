# AntiGravity IDE Prompt - Svelte Admin Notification System

**Purpose:** Implementasi admin notification polling component di Svelte untuk LocaTrack backend.

**Reference Documentation:** 
- Backend API: `NOTIFICATION_SYSTEM.md`
- Implementation Guide: `SVELTE_NOTIFICATION_IMPLEMENTATION.md`
- Postman Collection: `docs/LocaTrack-Backend-API.postman_collection.json` (Section: Notifications → Admin endpoints)

---

## CONTEXT

### Backend API Endpoints Ready
```
GET    /api/admin/notifications?page=1
POST   /api/admin/notifications/{notificationId}/read
DELETE /api/admin/notifications/{notificationId}
```

### Database Schema (Backend Already Implemented)
```
notifications table:
- id, created_by (FK), image_url, recipient_type, admin_ids, deleted_at

admin_notification_status table:
- admin_id, notification_id, read_at, deleted_by_admin_at
```

### Key Feature
Admin receives broadcast notifications dari superadmin via polling (setiap 30 detik). Soft delete hanya menyembunyikan dari view admin, bukan hard delete.

---

## REQUIREMENTS

### 1. Notification Store (src/lib/stores/notificationStore.ts)
Create reactive Svelte store dengan:
- **notifications**: Notification[] - array of notifications
- **isLoading**: boolean - loading state
- **error**: string | null - error message
- **currentPage**: number - current pagination page
- **totalPages**: number - total pages
- **unreadCount**: number - auto-calculate unread notifications
- **lastPolled**: Date | null - last poll timestamp

Computed properties:
- unreadCount: derived store yang hitung count dari notifications dengan read_at null
- filteredNotifications: derived store yang exclude deleted_by_admin_at null notifications

Functions:
- setNotifications(data): Update notifications array
- setLoading(value): Set loading state
- setError(error): Set error message
- reset(): Clear all data
- addNotification(notif): Push new notification
- updateNotification(id, data): Update specific notification
- removeNotification(id): Remove notification from array

### 2. Notification Service (src/lib/services/notificationService.ts)
Create service class dengan:

```typescript
class NotificationService {
  private pollingInterval: NodeJS.Timeout | null = null;
  private baseUrl = '/api/admin/notifications';
  private token = localStorage.getItem('token');
  
  async getNotifications(page: number = 1): Promise<{
    data: Notification[],
    pagination: { current_page, total, per_page, last_page }
  }>
  
  async markAsRead(notificationId: number): Promise<{ read_at: string }>
  
  async deleteNotification(notificationId: number): Promise<{ deleted_by_admin_at: string }>
  
  startPolling(intervalMs: number = 30000): void
  // Start polling setiap intervalMs milliseconds
  
  stopPolling(): void
  // Stop polling interval
  
  private poll(): Promise<void>
  // Internal method untuk fetch & update store setiap interval
}
```

Error handling:
- Network errors: console.error + set store error
- 401 Unauthorized: redirect ke /login
- 403 Forbidden: set error "Access denied"
- 500+ errors: retry 3x dengan exponential backoff

### 3. Main Component - AdminNotificationCenter (src/lib/components/AdminNotificationCenter.svelte)
```svelte
<script>
  // State & stores
  import { notifications, isLoading, error, unreadCount, currentPage, totalPages } from '$lib/stores/notificationStore';
  import { notificationService } from '$lib/services/notificationService';
  import { onMount, onDestroy } from 'svelte';
  
  // Components
  import NotificationCard from './NotificationCard.svelte';
  import LoadingSkeletons from './LoadingSkeletons.svelte';
  import ErrorMessage from './ErrorMessage.svelte';
  import PaginationControls from './PaginationControls.svelte';
  
  onMount(async => {
    // Fetch initial notifications
    await notificationService.getNotifications(1);
    // Start polling setiap 30 detik
    notificationService.startPolling(30000);
    
    return () => {
      notificationService.stopPolling();
    };
  });
  
  function handlePageChange(page) {
    notificationService.getNotifications(page);
  }
</script>

<div class="notification-center">
  <div class="header">
    <h2>Broadcast Notifications</h2>
    <span class="unread-count">{$unreadCount} unread</span>
  </div>
  
  {#if $isLoading}
    <LoadingSkeletons count={3} />
  {:else if $error}
    <ErrorMessage message={$error} onRetry={() => notificationService.getNotifications(1)} />
  {:else if $notifications.length === 0}
    <EmptyState message="No notifications" />
  {:else}
    <div class="notifications-grid">
      {#each $notifications as notification (notification.id)}
        <NotificationCard {notification} />
      {/each}
    </div>
    
    {#if $totalPages > 1}
      <PaginationControls
        currentPage={$currentPage}
        totalPages={$totalPages}
        onPageChange={handlePageChange}
      />
    {/if}
  {/if}
</div>

<style>
  .notification-center { padding: 1.5rem; }
  .header { display: flex; justify-content: space-between; margin-bottom: 1.5rem; }
  .unread-count { 
    background: var(--notification-badge-bg, #ef4444);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.875rem;
  }
  .notifications-grid { display: grid; gap: 1rem; }
</style>
```

### 4. Notification Card Component (src/lib/components/NotificationCard.svelte)
```svelte
<script>
  export let notification;
  
  import { notificationService } from '$lib/services/notificationService';
  
  let showModal = false;
  let isDeleting = false;
  let isMarkingRead = false;
  
  async function handleMarkAsRead() {
    isMarkingRead = true;
    try {
      await notificationService.markAsRead(notification.id);
    } catch (err) {
      console.error('Error marking as read:', err);
    } finally {
      isMarkingRead = false;
    }
  }
  
  async function handleDelete() {
    if (!confirm('Are you sure?')) return;
    isDeleting = true;
    try {
      await notificationService.deleteNotification(notification.id);
    } catch (err) {
      console.error('Error deleting:', err);
    } finally {
      isDeleting = false;
    }
  }
  
  const typeColors = {
    offer: '#f59e0b',
    news: '#3b82f6',
    info: '#10b981'
  };
</script>

<div class="notification-card" class:unread={!notification.read_at}>
  {#if notification.image_url}
    <img src={notification.image_url} alt={notification.title} class="card-image" />
  {/if}
  
  <div class="card-content">
    <div class="header-row">
      <h3>{notification.title}</h3>
      <span class="badge" style="--badge-color: {typeColors[notification.type]}">
        {notification.type}
      </span>
    </div>
    
    <p class="message">{notification.message}</p>
    
    <div class="meta">
      <small>From: {notification.created_by.name}</small>
      <small>{new Date(notification.created_at).toLocaleString()}</small>
    </div>
    
    <div class="actions">
      {#if !notification.read_at}
        <button
          on:click={handleMarkAsRead}
          disabled={isMarkingRead}
          class="btn btn-primary"
        >
          {isMarkingRead ? 'Marking...' : 'Mark as Read'}
        </button>
      {/if}
      
      <button
        on:click={handleDelete}
        disabled={isDeleting}
        class="btn btn-danger"
      >
        {isDeleting ? 'Removing...' : 'Remove'}
      </button>
      
      <button on:click={() => showModal = true} class="btn btn-secondary">
        View Details
      </button>
    </div>
  </div>
  
  {#if showModal}
    <NotificationModal bind:showModal {notification} />
  {/if}
</div>

<style>
  .notification-card {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1rem;
    background: white;
    transition: all 0.2s;
  }
  .notification-card.unread {
    background: #f0f9ff;
    border-left: 4px solid #3b82f6;
  }
  .card-image {
    width: 100%;
    max-height: 300px;
    object-fit: cover;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
  }
  .header-row {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 0.5rem;
  }
  .badge {
    padding: 0.25rem 0.75rem;
    background: var(--badge-color);
    color: white;
    border-radius: 999px;
    font-size: 0.75rem;
    white-space: nowrap;
  }
  .message { margin: 0.5rem 0 1rem; }
  .meta { display: flex; gap: 1rem; margin-bottom: 1rem; }
  .actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
  .btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.375rem;
    cursor: pointer;
    font-size: 0.875rem;
  }
  .btn-primary { background: #3b82f6; color: white; }
  .btn-danger { background: #ef4444; color: white; }
  .btn-secondary { background: #6b7280; color: white; }
  .btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
```

### 5. Notification Modal (src/lib/components/NotificationModal.svelte)
```svelte
<script>
  export let notification;
  export let showModal = false;
  
  import { notificationService } from '$lib/services/notificationService';
  
  let isDeleting = false;
  let isMarkingRead = false;
  
  async function handleMarkAsRead() {
    isMarkingRead = true;
    try {
      await notificationService.markAsRead(notification.id);
      showModal = false;
    } catch (err) {
      console.error('Error:', err);
    } finally {
      isMarkingRead = false;
    }
  }
  
  async function handleDelete() {
    if (!confirm('Remove this notification?')) return;
    isDeleting = true;
    try {
      await notificationService.deleteNotification(notification.id);
      showModal = false;
    } catch (err) {
      console.error('Error:', err);
    } finally {
      isDeleting = false;
    }
  }
</script>

{#if showModal}
  <div class="modal-overlay" on:click={() => showModal = false}>
    <div class="modal-content" on:click|stopPropagation>
      <button class="close-btn" on:click={() => showModal = false}>✕</button>
      
      {#if notification.image_url}
        <img src={notification.image_url} alt={notification.title} class="modal-image" />
      {/if}
      
      <h2>{notification.title}</h2>
      <p class="type-badge">{notification.type}</p>
      <p class="message">{notification.message}</p>
      
      <div class="metadata">
        <p><strong>From:</strong> {notification.created_by.name}</p>
        <p><strong>Date:</strong> {new Date(notification.created_at).toLocaleString()}</p>
        <p><strong>Status:</strong> {notification.read_at ? '✓ Read' : '◯ Unread'}</p>
      </div>
      
      <div class="modal-actions">
        {#if !notification.read_at}
          <button
            on:click={handleMarkAsRead}
            disabled={isMarkingRead}
            class="btn btn-primary"
          >
            {isMarkingRead ? 'Marking...' : 'Mark as Read'}
          </button>
        {/if}
        
        <button
          on:click={handleDelete}
          disabled={isDeleting}
          class="btn btn-danger"
        >
          {isDeleting ? 'Removing...' : 'Remove'}
        </button>
      </div>
    </div>
  </div>
{/if}

<style>
  .modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
  }
  .modal-content {
    background: white;
    border-radius: 0.75rem;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 2rem;
    position: relative;
  }
  .close-btn {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
  }
  .modal-image {
    width: 100%;
    max-height: 400px;
    object-fit: cover;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
  }
  .message { margin: 1rem 0; }
  .metadata { background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; }
  .modal-actions { display: flex; gap: 1rem; margin-top: 1.5rem; }
  .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.375rem; cursor: pointer; }
  .btn-primary { background: #3b82f6; color: white; }
  .btn-danger { background: #ef4444; color: white; }
  .btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
```

### 6. Notification Bell Header Component (src/lib/components/NotificationBell.svelte)
```svelte
<script>
  import { unreadCount } from '$lib/stores/notificationStore';
  
  let showDropdown = false;
</script>

<div class="notification-bell">
  <button
    class="bell-btn"
    on:click={() => showDropdown = !showDropdown}
    aria-label="Notifications"
  >
    <span class="bell-icon">🔔</span>
    {#if $unreadCount > 0}
      <span class="badge">{$unreadCount > 99 ? '99+' : $unreadCount}</span>
    {/if}
  </button>
  
  {#if showDropdown}
    <div class="dropdown" on:click:outside={() => showDropdown = false}>
      <a href="/admin/notifications">View All Notifications →</a>
    </div>
  {/if}
</div>

<style>
  .notification-bell { position: relative; }
  .bell-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    position: relative;
  }
  .badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ef4444;
    color: white;
    border-radius: 999px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: bold;
  }
  .dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1rem;
    min-width: 200px;
    box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
  }
</style>
```

### 7. Loading Skeletons Component (src/lib/components/LoadingSkeletons.svelte)
```svelte
<script>
  export let count = 3;
</script>

<div class="skeleton-list">
  {#each Array(count) as _, i (i)}
    <div class="skeleton-card">
      <div class="skeleton-image"></div>
      <div class="skeleton-text"></div>
      <div class="skeleton-text short"></div>
    </div>
  {/each}
</div>

<style>
  .skeleton-list { display: grid; gap: 1rem; }
  .skeleton-card { padding: 1rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; }
  .skeleton-image {
    width: 100%;
    height: 200px;
    background: linear-gradient(90deg, #f3f4f6, #e5e7eb, #f3f4f6);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
  }
  .skeleton-text {
    height: 16px;
    background: linear-gradient(90deg, #f3f4f6, #e5e7eb, #f3f4f6);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 0.25rem;
    margin-bottom: 0.5rem;
  }
  .skeleton-text.short { width: 60%; }
  @keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
  }
</style>
```

### 8. Error Message Component (src/lib/components/ErrorMessage.svelte)
```svelte
<script>
  export let message = 'An error occurred';
  export let onRetry = () => {};
</script>

<div class="error-message">
  <p>⚠️ {message}</p>
  <button on:click={onRetry} class="btn-retry">Retry</button>
</div>

<style>
  .error-message {
    background: #fee2e2;
    border: 1px solid #fecaca;
    border-radius: 0.5rem;
    padding: 1rem;
    text-align: center;
  }
  .btn-retry {
    margin-top: 1rem;
    padding: 0.5rem 1rem;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 0.375rem;
    cursor: pointer;
  }
</style>
```

### 9. Integration in App Layout (src/routes/+layout.svelte)
```svelte
<script>
  import { onMount } from 'svelte';
  import { notificationService } from '$lib/services/notificationService';
  import NotificationBell from '$lib/components/NotificationBell.svelte';
  
  onMount(() => {
    // Start polling notifications setiap 30 detik
    notificationService.startPolling(30000);
    
    // Cleanup on unmount
    return () => notificationService.stopPolling();
  });
</script>

<header>
  <nav>
    <h1>LocaTrack Admin</h1>
    <div class="header-actions">
      <NotificationBell />
      <!-- Other header items -->
    </div>
  </nav>
</header>

<main>
  <slot />
</main>
```

### 10. Notifications Page (src/routes/admin/notifications/+page.svelte)
```svelte
<script>
  import AdminNotificationCenter from '$lib/components/AdminNotificationCenter.svelte';
</script>

<svelte:head>
  <title>Notifications - LocaTrack Admin</title>
</svelte:head>

<div class="notifications-page">
  <AdminNotificationCenter />
</div>

<style>
  .notifications-page { padding: 2rem; }
</style>
```

---

## IMPLEMENTATION DETAILS

### Types Definition (types/notification.ts)
```typescript
export interface Notification {
  id: number;
  title: string;
  message: string;
  image_url: string | null;
  type: 'offer' | 'news' | 'info';
  read_at: string | null;
  deleted_by_admin_at: string | null;
  created_at: string;
  created_by: {
    id: number;
    name: string;
  };
}

export interface NotificationResponse {
  data: Notification[];
  pagination: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}
```

### API Configuration
```typescript
// src/lib/config/api.ts
export const API_BASE_URL = process.env.PUBLIC_API_URL || 'http://localhost:8000/api';
export const NOTIFICATION_POLLING_INTERVAL = 30000; // 30 seconds
export const MAX_RETRIES = 3;
export const RETRY_DELAY = 1000; // 1 second, exponential backoff
```

---

## TESTING CHECKLIST

- [ ] Component renders without errors
- [ ] Polling starts on component mount
- [ ] Notifications update every 30 seconds
- [ ] Mark as read updates read_at
- [ ] Delete action hides notification
- [ ] Modal opens and shows full image
- [ ] Unread count badge updates correctly
- [ ] Error messages display on API failure
- [ ] Loading skeletons show during fetch
- [ ] Pagination works (prev/next buttons)
- [ ] Mobile responsive design
- [ ] Keyboard navigation (Escape closes modal)
- [ ] Accessibility (ARIA labels)
- [ ] Dark mode support

---

## EXPECTED OUTCOME

After implementation, admin dashboard will show:
1. ✅ Bell icon in header with unread count badge
2. ✅ /admin/notifications page dengan full notification list
3. ✅ Each card shows image, title, message, type badge, timestamp
4. ✅ Polling every 30 seconds automatically updates list
5. ✅ Mark as read & delete buttons on each card
6. ✅ Modal untuk full view dengan HD image
7. ✅ Error handling & loading states
8. ✅ Pagination support (20 per page)

---

## TIMELINE

- **Phase 1:** Setup stores & service (30 min)
- **Phase 2:** Create card & modal components (45 min)
- **Phase 3:** Create main center & bell components (30 min)
- **Phase 4:** Integration & testing (30 min)

**Total: 2-3 hours**

---

## QUESTIONS TO CLARIFY

1. Styling framework? (Tailwind CSS, custom CSS, daisyUI, etc)
2. Dark mode support needed?
3. Notification sounds needed?
4. Browser push notifications needed?
5. Any existing UI components library?

---

**Status:** Ready for Implementation  
**API Backend:** ✅ Complete (NOTIFICATION_SYSTEM.md)  
**Database:** ✅ Migrated  
**Postman Tests:** ✅ Available  
**Next Step:** Start Svelte development
