# Laravel Reverb WebSocket Setup for Svelte

## Status ✅
- **Backend**: Laravel Reverb WebSocket server installed and running on `ws://127.0.0.1:8080`
- **Broadcasting Driver**: Changed from `log` to `reverb`
- **Events**: `LocationUpdated` event configured for broadcasting
- **Ready**: Svelte frontend can now connect

---

## What Changed in Backend

### 1. Installed Laravel Reverb
```bash
composer require laravel/reverb
php artisan reverb:install
```

### 2. Updated .env Configuration
```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=my-app
REVERB_APP_KEY=my-key
REVERB_APP_SECRET=my-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

### 3. Running the WebSocket Server
```bash
php artisan reverb:start
# Server now listening on ws://127.0.0.1:8080
```

---

## Update Svelte Frontend Configuration

### 1. Update .env in Svelte Project

Change from Pusher to Reverb:

```env
# OLD (Pusher)
# VITE_PUSHER_APP_KEY=xxx
# VITE_PUSHER_HOST=localhost
# VITE_PUSHER_PORT=6001
# VITE_PUSHER_SCHEME=ws

# NEW (Reverb - Use these instead)
VITE_REVERB_APP_KEY=my-key
VITE_REVERB_HOST=127.0.0.1
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

### 2. Update websocket.service.ts (or your Echo config file)

**OLD CODE (with Pusher):**
```typescript
export const echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    wsHost: import.meta.env.VITE_PUSHER_HOST || window.location.hostname,
    wsPort: import.meta.env.VITE_PUSHER_PORT || 6001,
    wssPort: import.meta.env.VITE_PUSHER_PORT || 6001,
    forceTLS: import.meta.env.VITE_PUSHER_SCHEME === 'https',
    encrypted: true,
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('auth_token')}`
        }
    },
    enabledTransports: ['ws', 'wss'],
});
```

**NEW CODE (with Reverb):**
```typescript
export const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    encrypted: false, // Set to false for http, true for https
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('auth_token')}`
        }
    },
    enabledTransports: ['ws'],
});
```

### 3. Install Reverb Client (if needed)

If you haven't already installed the JavaScript client:

```bash
npm install laravel-echo
# No need for pusher-js anymore, Reverb uses native WebSocket
```

### 4. Update tsconfig or imports in websocket.service.ts

Make sure you're using the correct Echo library:

```typescript
import Echo from 'laravel-echo'; // This is the same, supports both Pusher and Reverb
```

---

## Connection Details

### Backend WebSocket Server
```
URL: ws://127.0.0.1:8080
Protocol: WebSocket (ws://)
Environment: Development
Port: 8080 (can be changed in config/reverb.php)
```

### Channel Format (Same as before)
```
Private Channels:
  - location.employee.{id}      → For employee location tracking
  - location.vehicle.{id}       → For vehicle location tracking
```

### Event Broadcasting (Same as before)
```
Event: LocationUpdated
Payload: {
  latitude: number,
  longitude: number,
  speed: number,
  accuracy: number,
  recorded_at: string (ISO datetime),
  entity_name: string,
  trackable_type: 'employee' | 'vehicle',
  trackable_id: number
}
```

---

## Testing Connection

### 1. Start Backend WebSocket Server
```bash
cd backend
php artisan reverb:start
```

### 2. Start Svelte Dev Server
```bash
cd svelte-app
npm run dev
```

### 3. Open Browser Console
Check for connection status:
```javascript
// In browser console:
echo.connector.socket.on('connected', () => {
    console.log('✅ WebSocket Connected!');
});

echo.connector.socket.on('disconnected', () => {
    console.log('❌ WebSocket Disconnected');
});
```

### 4. Test with Postman
1. Login and get auth token
2. POST to `/api/locations` with employee/vehicle location data
3. Watch browser console for `LocationUpdated` events
4. Verify map markers update in real-time

---

## Production Deployment

When deploying to production:

### 1. Use HTTPS/WSS
```env
REVERB_SCHEME=https
VITE_REVERB_SCHEME=https
```

### 2. Use Production Domain
```env
REVERB_HOST=your-domain.com
VITE_REVERB_HOST=your-domain.com
VITE_REVERB_PORT=443
```

### 3. Update CORS
In `config/cors.php`, ensure Reverb domain is allowed:
```php
'allowed_origins' => [
    'https://your-domain.com',
    'https://your-svelte-domain.com',
],
```

### 4. Run with Supervisor/systemd
Create a supervisor config to keep Reverb running:
```ini
[program:reverb]
process_name=%(program_name)s
command=php artisan reverb:start
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/reverb.log
```

---

## Troubleshooting

### Issue: "WebSocket connection failed"
**Solution**:
1. Check backend server is running: `php artisan reverb:start`
2. Verify port 8080 is open/not blocked
3. Check `.env` has correct `REVERB_HOST` and `REVERB_PORT`
4. Check Svelte `.env` matches backend config

### Issue: "Channel authorization failed"
**Solution**:
1. Verify Bearer token is valid (check auth middleware)
2. Check LocationChannel.php authorization logic
3. Ensure user role is admin or employee
4. Verify tenant_id matches

### Issue: "Events not received in browser"
**Solution**:
1. Verify `BROADCAST_CONNECTION=reverb` in backend `.env`
2. Check backend is dispatching LocationUpdated event
3. Verify subscription happens after connection is ready
4. Check browser console for JavaScript errors

### Issue: "High CPU or memory usage"
**Solution**:
1. Reverb is single-threaded; use multiple processes in production
2. Configure proper limits in `config/reverb.php`
3. Monitor WebSocket connections with `php artisan reverb:list` (in future versions)

---

## Quick Reference

| Item | Value |
|------|-------|
| **WebSocket Server** | Laravel Reverb |
| **Backend Port** | 8080 |
| **Frontend Protocol** | ws:// (http) or wss:// (https) |
| **Broadcaster** | reverb |
| **Auth Method** | Bearer Token in headers |
| **Channel Privacy** | Private (authenticated) |
| **Event Name** | LocationUpdated |
| **Max Connections** | Depends on server resources |

---

## Next Steps

1. ✅ Backend: WebSocket server running
2. **TODO**: Update Svelte `.env` with Reverb config
3. **TODO**: Update websocket.service.ts in Svelte
4. **TODO**: Test connection with Postman
5. **TODO**: Deploy to production with Supervisor

---

## Questions?

If you encounter issues:
1. Check `php artisan reverb:start` logs
2. Check browser console for JavaScript errors
3. Verify auth token is valid
4. Ensure firewall allows port 8080
5. Check Laravel logs in `storage/logs/`

---

**Ready to update Svelte? Follow the steps above in your Svelte app's `.env` and websocket config file.**
