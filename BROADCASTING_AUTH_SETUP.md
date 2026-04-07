# Broadcasting Auth Endpoint Fix - Complete Setup

## ✅ Fixes Applied to Backend

### 1. **Created BroadcastServiceProvider** (`app/Providers/BroadcastServiceProvider.php`)
- Registers broadcasting routes with correct middleware
- Loads channel definitions from `routes/channels.php`
- Middleware: `['api', 'auth:sanctum']` for protected channels

### 2. **Registered Provider** (`bootstrap/providers.php`)
- Added `App\Providers\BroadcastServiceProvider::class`
- Ensures routes are loaded during application bootstrap

### 3. **Removed Conflicting Configuration** (`bootstrap/app.php`)
- Removed `->withBroadcasting()` call that was conflicting
- Service provider handles all broadcasting setup

### 4. **Verified Channels Configuration** (`routes/channels.php`)
- Location tracking channels properly configured
- Format: `location.{trackableType}.{trackableId}`
- Uses LocationChannel for authorization

## ✅ Broadcasting Route Registered

```
GET|POST|HEAD   /broadcasting/auth   Illuminate\Broadcasting\BroadcastController
```

Middleware: `api`, `auth:sanctum`

## How It Works

1. **Svelte connects to Pusher with channel list**
   ```javascript
   channel = pusher.subscribe('location.employee.1')
   ```

2. **Pusher.js requests authorization token from backend**
   ```
   POST /api/broadcasting/auth
   Headers: {
     Authorization: Bearer <token>
     Content-Type: application/json
   }
   Body: {
     channel_name: "location.employee.1"
   }
   ```

3. **Backend authenticates user via Sanctum token and checks LocationChannel authorization**
   - User must have valid Bearer token from login
   - LocationChannel::join() verifies user can access this channel
   - Returns auth signature if authorized

4. **Pusher.js receives authorization and subscribes to channel**
   ```javascript
   channel.bind('location.updated', (data) => {
     // Update map with new location
   })
   ```

5. **When GPS sends location, event broadcasts to Pusher**
   ```
   POST /api/gps/track
   → LocationUpdated::dispatch()
   → Broadcasts to Pusher
   → Pusher sends to subscribed clients
   → Svelte receives and updates map
   ```

## Testing from Svelte

### Prerequisites
- User must be logged in with valid Sanctum token
- Token must be passed in Authorization header
- User must have permission to access channel (checked in LocationChannel)

### Expected Flow
1. ✅ Svelte connects to Pusher successfully
2. ✅ Svelte authenticates to broadcasting/auth endpoint
3. ✅ LocationChannel verifies user permissions
4. ✅ Svelte subscribes to location channels
5. ✅ Svelte listens for 'location.updated' events
6. ✅ When GPS sends data, Pusher broadcasts to map
7. ✅ Map updates in real-time without refresh

## Troubleshooting 405 Error

If still getting HTTP 405 (Method Not Allowed):

1. **Check BroadcastServiceProvider is registered**
   ```bash
   php artisan route:list | Select-String -Pattern "broadcast"
   ```
   Should show `/broadcasting/auth` route

2. **Clear all caches**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:cache
   ```

3. **Verify Sanctum token is valid**
   - Token must come from `/api/login` endpoint
   - Token must be passed as Bearer in Authorization header
   - User must have valid auth record in database

4. **Check LocationChannel logic**
   - Verify user role (admin/employee)
   - Verify user has access to the requested channel
   - Check admin_id/employee_id matches

## Next Steps for Svelte

1. **Ensure correct Pusher configuration in .env.local**
   ```
   VITE_PUSHER_APP_KEY=737575397aad80d7c83b
   VITE_PUSHER_CLUSTER=ap1
   ```

2. **Pass Pusher authorization endpoint**
   ```javascript
   // In websocket.service.ts
   const pusher = new Pusher(appKey, {
     cluster: cluster,
     channelAuthorization: {
       endpoint: '/api/broadcasting/auth',
       transport: 'ajax',
       headers: {
         'Authorization': `Bearer ${token}` // From localStorage
       }
     }
   })
   ```

3. **Verify token is available when connecting**
   - Get token from localStorage after login
   - Pass token in Authorization header for broadcasting auth

## Files Modified

- `app/Providers/BroadcastServiceProvider.php` - Created
- `bootstrap/providers.php` - Added BroadcastServiceProvider
- `bootstrap/app.php` - Removed conflicting withBroadcasting
- `config/broadcasting.php` - Already has Pusher config
- `routes/channels.php` - Already configured

## Status

✅ Backend broadcasting routes configured correctly
✅ SSL certificate verification disabled for local dev
✅ Broadcasting/auth endpoint ready for authentication
⏳ Waiting for Svelte team to add proper Authorization header in channel auth request
