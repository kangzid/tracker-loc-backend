# WebSocket Real-Time Tracking Implementation

## Deskripsi
Sistem real-time tracking menggunakan **Laravel WebSockets** untuk efficient live location updates. Ketika employee atau vehicle mengirim location update, data langsung di-broadcast ke semua clients yang subscribed ke channel tersebut, tanpa perlu polling/refetch.

---

## Architecture Overview

```
GPS Device / Mobile App
    ↓
POST /api/locations  (LocationUpdated Event)
    ↓
Location Model Updated
    ↓
WebSocket Event Broadcast (Private Channel)
    ↓
Connected Clients (Admin/Svelte/Flutter)
    ↓
Real-time Map Update
```

---

## Setup WebSocket Server

### 1. Install Laravel WebSocket Package
```bash
composer require beyondco/laravel-websockets
php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider" --assets
php artisan migrate
```

### 2. Konfigurasi `.env`
```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=1
PUSHER_APP_KEY=websocket-key-1234567890
PUSHER_APP_SECRET=websocket-secret-1234567890
PUSHER_APP_CLUSTER=mt1
PUSHER_HOST=localhost
PUSHER_PORT=6001
PUSHER_SCHEME=http

# WebSocket server config
LARAVEL_WEBSOCKETS_PORT=6001
```

### 3. Start WebSocket Server
```bash
# Development
php artisan websockets:serve

# Production (dengan supervisor atau systemd)
```

### 4. Konfigurasi Channels (`config/broadcasting.php`)
```php
'channels' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'host' => env('PUSHER_HOST', 'localhost'),
            'port' => env('PUSHER_PORT', 6001),
            'scheme' => env('PUSHER_SCHEME', 'http')
        ],
    ],
],
```

---

## Channel: Location Tracking

### Private Channel: `location.{trackable_type}.{trackable_id}`

**Format:**
- `location.employee.5` → Track employee ID 5
- `location.vehicle.3` → Track vehicle ID 3

**Authorization (app/Broadcasting/LocationChannel.php):**
- **Admin**: Bisa subscribe ke location employee dan vehicle milik tenant mereka
- **Employee**: Hanya bisa subscribe ke location diri sendiri dan vehicle di tenant mereka
- **Guest/Invalid**: Tidak boleh subscribe

---

## Event: Location Updated

### Broadcast Event: `LocationUpdated`

**File:** `app/Events/LocationUpdated.php`

**Trigger:**
- Ketika employee/vehicle submit location via `POST /api/locations`
- Ketika admin update vehicle location via `POST /api/vehicles/{id}/location`

**Payload:**
```json
{
  "trackable_type": "employee|vehicle",
  "trackable_id": 5,
  "latitude": -6.2088,
  "longitude": 106.8456,
  "speed": 45.5,
  "accuracy": 10,
  "recorded_at": "2026-04-07T10:30:00Z",
  "entity_name": "Budi Santoso|B-1234-ABC"
}
```

**Event Name (Client Side):** `location.updated`

---

## Client Implementation

### Svelte/JavaScript (Web Frontend)

```javascript
// 1. Install dependency
npm install laravel-echo pusher-js

// 2. Setup Echo
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'pusher',
  key: 'websocket-key-1234567890',
  wsHost: 'localhost',
  wsPort: 6001,
  wssPort: 6001,
  forceTLS: false,
  encrypted: false,
  disableStats: true,
  enabledTransports: ['ws', 'wss'],
  auth: {
    headers: {
      Authorization: `Bearer ${token}`
    }
  }
});

// 3. Subscribe to location channel (Employee diri sendiri)
echo
  .private(`location.employee.${employeeId}`)
  .listen('LocationUpdated', (data) => {
    console.log('Location updated:', data);
    // Update map dengan data:
    // { latitude, longitude, speed, accuracy, recorded_at, entity_name }
    updateMapMarker(data.trackable_id, data.latitude, data.longitude);
  });

// 4. Subscribe ke vehicle location (Admin atau Employee di tenant)
echo
  .private(`location.vehicle.${vehicleId}`)
  .listen('LocationUpdated', (data) => {
    console.log('Vehicle location updated:', data);
    updateVehicleMarker(data.trackable_id, data.latitude, data.longitude);
  });

// 5. Unsubscribe saat unmount component
echo.leaveChannel(`location.employee.${employeeId}`);
echo.leaveChannel(`location.vehicle.${vehicleId}`);
```

### Flutter (Mobile App)

```dart
// 1. Add dependency to pubspec.yaml
dependencies:
  laravel_echo: ^latest
  pusher_channels_flutter: ^latest

// 2. Setup Flutter Echo
import 'package:laravel_echo/laravel_echo.dart';

final echo = Echo(
  broadcaster: 'pusher',
  options: {
    'auth': {
      'headers': {
        'Authorization': 'Bearer $token'
      }
    }
  },
  client: PusherClient(
    'websocket-key-1234567890',
    PusherOptions(
      host: 'localhost',
      port: 6001,
      encrypted: false,
    ),
  ),
);

// 3. Subscribe to location channel (Employee sendiri)
echo
  .private('location.employee.$employeeId')
  .listen('LocationUpdated', (event) {
    print('Location updated: ${event.data}');
    // Update map dengan data
    final lat = event.data['latitude'];
    final lng = event.data['longitude'];
    updateMapMarker(lat, lng);
  });

// 4. Subscribe ke vehicle location
echo
  .private('location.vehicle.$vehicleId')
  .listen('LocationUpdated', (event) {
    print('Vehicle location: ${event.data}');
    updateVehicleMarker(event.data['latitude'], event.data['longitude']);
  });

// 5. Cleanup
echo.leave('location.employee.$employeeId');
echo.leave('location.vehicle.$vehicleId');
```

---

## API Endpoints (Update & Broadcast)

### Employee Submit Location (dengan Auto-Broadcast)
```
POST /api/locations
Content-Type: application/json
Authorization: Bearer {token}

{
  "latitude": -6.2088,
  "longitude": 106.8456,
  "trackable_type": "employee",
  "trackable_id": 5,
  "speed": 45,
  "accuracy": 10
}

Response: Location record + WebSocket broadcast ke location.employee.5
```

### Admin Update Vehicle Location (dengan Auto-Broadcast)
```
POST /api/vehicles/{id}/location
Content-Type: application/json
Authorization: Bearer {token}

{
  "latitude": -6.2088,
  "longitude": 106.8456,
  "speed": 50,
  "accuracy": 5
}

Response: Vehicle record + Location record + WebSocket broadcast ke location.vehicle.{id}
```

---

## Security & Authorization

### Channel Authorization
- **Private Channel**: `location.{trackable_type}.{trackable_id}`
- Hanya authorized users yang bisa subscribe
- Authorization check di `app/Broadcasting/LocationChannel.php`

### Database-Backed Authorization
```php
// Admin subscribe ke employee location
auth()->user()->isAdmin() && 
Employee::where('admin_id', auth()->user()->id)->where('id', $employeeId)->exists()

// Employee subscribe ke diri sendiri
auth()->user()->employee->id == $employeeId

// Semua bisa subscribe ke vehicle location yang ada di tenant mereka
Vehicle::where('admin_id', $adminId)->exists()
```

---

## Database: Location Table

**Tidak ada perubahan**, tetap menggunakan Location table yang sudah ada:
```
locations
├── id (PK)
├── trackable_type (Employee|Vehicle - polymorphic)
├── trackable_id (employee_id atau vehicle_id)
├── latitude (decimal:8)
├── longitude (decimal:8)
├── speed (nullable, decimal:2)
├── accuracy (nullable, decimal:2)
├── recorded_at (datetime)
├── created_at (auto)
├── updated_at (auto)
```

**History tetap tersimpan** untuk analytics/audit. **Saat vehicle dihapus**, location history otomatis dihapus via cascade delete.

---

## Performance Optimization

### 1. **Only Keep Latest Location in Live Tracking**
```php
// LocationController@store menggunakan updateOrCreate
// Hanya 1 record location per trackable untuk live tracking
Location::updateOrCreate(
  ['trackable_type' => '...', 'trackable_id' => '...'],
  [...location data...]
);
```

### 2. **Batch Location Updates**
Untuk high-frequency GPS updates (e.g., setiap 1 detik), bisa batch:
```javascript
// Client side: Buffer updates, kirim setiap 5 detik
let locationBuffer = [];
setInterval(() => {
  if (locationBuffer.length > 0) {
    POST /api/locations/batch
    locationBuffer = [];
  }
}, 5000);
```

### 3. **WebSocket vs REST API**
- **WebSocket**: Real-time updates ke multiple clients (dashboard, map)
- **REST API**: Untuk query history, detailed analytics
- **Hybrid**: Kirim location via REST, broadcast via WebSocket

---

## Monitoring & Debugging

### Check WebSocket Server Status
```bash
# Terminal 1: Start WebSocket server
php artisan websockets:serve

# Terminal 2: Check connections
curl http://localhost:6001/apps/1/channels
```

### Client-side Debugging (Browser Console)
```javascript
// Check connection
window.Echo.connector.pusher.connection.state
// Output: 'connected', 'connecting', 'disconnected'

// Check subscribed channels
Object.keys(window.Echo.connector.pusher.channels)
// Output: ['location.employee.5', 'location.vehicle.3']

// Listen to connection events
window.Echo.connector.pusher.connection.bind('state_change', state => {
  console.log('WebSocket state:', state);
});
```

### Server-side Debugging
```bash
# Add logging di LocationUpdated event
// app/Events/LocationUpdated.php
Log::info('Location broadcast', ['channel' => "location.{$this->trackableType}.{$this->trackableId}"]);
```

---

## Production Deployment

### Option 1: Pusher (Cloud Service)
```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=xxx
PUSHER_APP_KEY=xxx
PUSHER_APP_SECRET=xxx
PUSHER_APP_CLUSTER=mt1
```

### Option 2: Self-Hosted WebSocket (Recommended untuk project ini)
```bash
# Install supervisor
sudo apt-get install supervisor

# Create config: /etc/supervisor/conf.d/laravel-websockets.conf
[program:laravel-websockets]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/backend/artisan websockets:serve
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/logs/websockets.log

# Start service
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-websockets:*
```

---

## Testing WebSocket

### Unit Test: Broadcasting Event
```php
// tests/Feature/LocationTrackingTest.php
public function test_location_update_broadcasts_to_channel()
{
    $user = User::factory()->employee()->create();
    
    Event::fake();
    
    $this->actingAs($user)->postJson('/api/locations', [
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'trackable_type' => 'employee',
        'trackable_id' => $user->employee->id,
    ]);
    
    Event::assertDispatched(LocationUpdated::class);
}
```

### Integration Test: Subscribe & Receive
```php
// Gunakan Pusher test mode atau WebSocket mock
// Validate bahwa event diterima di client
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| WebSocket connection refused | Ensure `php artisan websockets:serve` is running on port 6001 |
| Event not broadcasting | Check `BROADCAST_DRIVER=pusher` di .env |
| Authorization failed | Verify user token dan location channel authorization logic |
| Slow updates | Reduce GPS update frequency (setiap 5-10 detik vs setiap 1 detik) |
| High server load | Batch location updates atau scale WebSocket server |

---

## Summary

✅ **Real-time tracking menggunakan WebSocket** untuk efficient live location updates
✅ **Private channel authorization** untuk security
✅ **Cascade delete locations** saat vehicle dihapus
✅ **Support both Web (Svelte) dan Mobile (Flutter)** clients
✅ **History tetap tersimpan** untuk analytics
✅ **Production-ready** dengan Pusher atau self-hosted option

**No polling = Lower bandwidth & Server load = Better UX**

