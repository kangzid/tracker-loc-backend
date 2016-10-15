# Svelte Notification Component Implementation Guide

## Overview
Implementasi admin notification polling component di Svelte untuk menerima broadcast notifications dari superadmin.

## File Structure
```
src/
├── lib/
│   ├── services/
│   │   └── notificationService.ts      (API calls & state management)
│   └── components/
│       ├── AdminNotificationCenter.svelte (Main container)
│       ├── NotificationList.svelte       (List display)
│       ├── NotificationCard.svelte       (Individual card)
│       ├── NotificationModal.svelte      (Detail view with image)
│       └── NotificationBell.svelte       (Bell icon with badge)
├── routes/
│   └── admin/
│       └── notifications/
│           └── +page.svelte            (Notification page)
└── stores/
    └── notificationStore.ts            (Reactive store)
```

## API Endpoints Used

### Get Notifications (Polling)
```
GET /api/admin/notifications?page=1
Response: 200 OK
{
  data: [
    {
      id: number,
      title: string,
      message: string,
      image_url: string | null,
      type: 'offer' | 'news' | 'info',
      read_at: string | null,
      deleted_by_admin_at: string | null,
      created_at: string,
      created_by: { id: number, name: string }
    }
  ],
  pagination: { current_page, total, per_page, last_page }
}
```

### Mark as Read
```
POST /api/admin/notifications/{notificationId}/read
Response: 200 OK
{
  message: string,
  notification_id: number,
  read_at: string
}
```

### Soft Delete (Hide)
```
DELETE /api/admin/notifications/{notificationId}
Response: 200 OK
{
  message: string,
  notification_id: number,
  deleted_by_admin_at: string
}
```

## Component Structure

### 1. NotificationStore (stores/notificationStore.ts)
```typescript
// Reactive store untuk manage notification state
- notifications: Notification[] (list)
- isLoading: boolean
- error: string | null
- currentPage: number
- totalPages: number
- unreadCount: number
- lastPolled: Date | null
```

### 2. NotificationService (lib/services/notificationService.ts)
```typescript
// API service class
- getNotifications(page: number): Promise<NotificationResponse>
- markAsRead(notificationId: number): Promise<void>
- deleteNotification(notificationId: number): Promise<void>
- startPolling(interval: number): void
- stopPolling(): void
```

### 3. AdminNotificationCenter (lib/components/AdminNotificationCenter.svelte)
```svelte
<!-- Main container component -->
- Props: none (uses store)
- State management via store
- Polling interval: 30 seconds default
- Auto-cleanup on unmount
- Shows: loading, error, empty states
- Pagination support
```

### 4. NotificationBell (lib/components/NotificationBell.svelte)
```svelte
<!-- Header bell icon with badge -->
- Shows unread count badge
- Opens modal on click
- Updates in real-time from store
```

### 5. NotificationCard (lib/components/NotificationCard.svelte)
```svelte
<!-- Individual notification card -->
- Props: notification object
- Shows: image, title, message, type badge, timestamp
- Actions: mark as read, delete, view details
- Type styling (offer, news, info)
```

### 6. NotificationModal (lib/components/NotificationModal.svelte)
```svelte
<!-- Full screen modal for detail view -->
- Props: notification, isOpen
- Shows: full image (HD), title, message, metadata
- Actions: mark as read, delete, close
- Image zoom support
```

### 7. NotificationList (lib/components/NotificationList.svelte)
```svelte
<!-- List container with pagination -->
- Props: notifications, pagination
- Displays: card grid or list
- Pagination controls (prev, next)
- Empty state handling
```

## Key Features

### Polling
- Every 30 seconds (configurable)
- Automatic retry on error
- Incremental updates (no full replace)
- Graceful error handling
- Auto-pause when not focused (optional)

### State Management
- Reactive Svelte store
- Auto-unsubscribe on component destroy
- Automatic badge count update
- Preserves unread status across updates

### UI/UX
- Loading skeletons
- Error toast notifications
- Empty state messages
- Pagination support (20 per page)
- Type badges (offer/news/info)
- Read/unread visual distinction

### Performance
- Lazy image loading
- Debounced pagination
- Minimal re-renders
- Auto-cleanup timers

## Integration Points

### In App.svelte
```svelte
<script>
  import { onMount } from 'svelte';
  import NotificationBell from './lib/components/NotificationBell.svelte';
  import { notificationService } from './lib/services/notificationService';
  
  onMount(() => {
    notificationService.startPolling(30000); // 30 seconds
    return () => notificationService.stopPolling();
  });
</script>

<header>
  <NotificationBell />
  <!-- Other header items -->
</header>
```

### In Admin Dashboard
```svelte
<script>
  import AdminNotificationCenter from './lib/components/AdminNotificationCenter.svelte';
</script>

<main>
  <h1>Admin Dashboard</h1>
  <AdminNotificationCenter />
</main>
```

## Required Dependencies

```json
{
  "svelte": "^4.x",
  "sveltekit": "^2.x",
  "axios": "^1.x or fetch API (native)"
}
```

## Styling Approach

### Tailwind CSS Classes (if using Tailwind)
```css
.notification-card - Card styling
.notification-badge - Type badge (offer/news/info)
.notification-image - Image container
.notification-unread - Unread state
.notification-modal - Modal styling
.notification-bell - Bell icon & badge
```

### CSS Variables (Theme Support)
```css
--notification-color-offer: #f59e0b (amber)
--notification-color-news: #3b82f6 (blue)
--notification-color-info: #10b981 (green)
--notification-padding: 1rem
--notification-border-radius: 0.5rem
```

## Error Handling

### Types of Errors
1. **Network Error** - Show toast: "Tidak dapat terhubung ke server"
2. **401 Unauthorized** - Redirect to login
3. **403 Forbidden** - Show message: "Anda tidak memiliki akses"
4. **500 Server Error** - Show toast: "Server error, coba lagi"

### Retry Logic
- Auto-retry failed polls 3 times
- Exponential backoff (1s, 2s, 4s)
- Manual retry button on error state
- Cancel retry on manual stop

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Accessibility

- ARIA labels on bell icon
- Keyboard navigation (Escape to close modal)
- Color contrast ratios WCAG AA
- Focus management in modal
- Semantic HTML structure

## Performance Metrics

| Operation | Target |
|-----------|--------|
| Initial load | <200ms |
| Polling response | <100ms |
| Mark as read | <50ms |
| Delete action | <50ms |
| Modal open | <100ms |

## Testing Strategy

### Unit Tests
- Service functions (getNotifications, markAsRead, etc)
- Store logic (state updates)
- Utility functions

### Component Tests
- NotificationCard rendering
- Polling start/stop
- Error state display
- Mark as read action
- Delete action

### E2E Tests
- Full polling flow
- Mark multiple as read
- Delete notification
- Pagination
- Real API integration

## Dark Mode Support

```svelte
{#if $theme === 'dark'}
  <div class="dark:bg-gray-800 dark:text-white">
    <!-- Component -->
  </div>
{/if}
```

## Internationalization (i18n)

```typescript
const messages = {
  en: {
    'notifications.title': 'Notifications',
    'notifications.empty': 'No notifications',
    'notifications.loading': 'Loading...',
    'notifications.error': 'Failed to load notifications'
  },
  id: {
    'notifications.title': 'Notifikasi',
    'notifications.empty': 'Tidak ada notifikasi',
    'notifications.loading': 'Memuat...',
    'notifications.error': 'Gagal memuat notifikasi'
  }
};
```

## Configuration

### In env or config file
```typescript
export const NOTIFICATION_CONFIG = {
  POLLING_INTERVAL: 30000,    // 30 seconds
  MAX_RETRIES: 3,
  RETRY_DELAY: 1000,
  PAGE_SIZE: 20,
  ENABLE_SOUND: false,
  IMAGE_MAX_WIDTH: 600
};
```

## Future Enhancements

1. ✅ WebSocket for real-time (instead of polling)
2. ✅ Notification sounds
3. ✅ Browser notifications (push API)
4. ✅ Notification analytics
5. ✅ Mark all as read
6. ✅ Bulk delete
7. ✅ Notification filtering (by type)
8. ✅ Search notifications

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Notifications not showing | Check CORS headers, verify token in localStorage |
| Image not loading | Verify image URL is accessible, check storage symlink |
| Polling stops | Check browser console for errors, verify network tab |
| High memory usage | Check for memory leaks in setInterval, verify store cleanup |

## Code Examples

### Basic Usage
```svelte
<script>
  import AdminNotificationCenter from '$lib/components/AdminNotificationCenter.svelte';
</script>

<AdminNotificationCenter />
```

### Custom Polling Interval
```typescript
import { notificationService } from '$lib/services/notificationService';

// Start with 60 second interval
notificationService.startPolling(60000);
```

### Access Notifications Store
```svelte
<script>
  import { notifications } from '$lib/stores/notificationStore';
</script>

<div>
  Unread: {$notifications.filter(n => !n.read_at).length}
</div>
```

## Related Files
- API Documentation: [NOTIFICATION_SYSTEM.md](NOTIFICATION_SYSTEM.md)
- Backend Routes: routes/api.php
- Models: app/Models/Notification.php, app/Models/AdminNotificationStatus.php
- Controllers: app/Http/Controllers/Api/NotificationController.php

---

**Status:** Ready for Implementation  
**Last Updated:** April 9, 2026  
**Estimated Development Time:** 2-4 hours
