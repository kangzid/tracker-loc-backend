# Quick Setup WebSocket Real-Time Tracking

## ✅ Apa yang Sudah Diimplementasi

### 1. **Cascade Delete Vehicle Locations**

- Ketika vehicle dihapus → semua location history otomatis terhapus
- Tidak menumpuk di database
- File: `app/Http/Controllers/Api/VehicleController.php` (destroy method)

### 2. **WebSocket Real-Time Tracking**

- Location updates langsung di-broadcast ke connected clients
- No polling needed - lebih efisien bandwidth dan server
- Support Employee dan Vehicle tracking
- File implementasi:
    - `app/Events/LocationUpdated.php` - Event untuk broadcast
    - `app/Broadcasting/LocationChannel.php` - Authorization logic
    - `routes/channels.php` - Channel registration
    - `app/Http/Controllers/Api/LocationController.php` - Auto-broadcast di store()
    - `app/Http/Controllers/Api/VehicleController.php` - Auto-broadcast di updateLocation()

---

## 🚀 Setup (3 Steps)

### Step 1: Install WebSocket Package

```bash
composer require beyondco/laravel-websockets
php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider" --assets
php artisan migrate
```

### Step 2: Setup .env

```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=1
PUSHER_APP_KEY=websocket-key-1234567890
PUSHER_APP_SECRET=websocket-secret-1234567890
LARAVEL_WEBSOCKETS_PORT=6001
```

### Step 3: Start WebSocket Server

```bash
php artisan websockets:serve
# Server running pada port 6001
```

---

## 📱 Client Implementation

### Svelte (Web Frontend)

```javascript
npm install laravel-echo pusher-js

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const echo = new Echo({
  broadcaster: 'pusher',
  key: 'websocket-key-1234567890',
  wsHost: 'localhost',
  wsPort: 6001,
  forceTLS: false,
  auth: {
    headers: {
      Authorization: `Bearer ${token}`
    }
  }
});

// Subscribe to employee location
echo.private(`location.employee.${employeeId}`)
  .listen('LocationUpdated', (data) => {
    // Update map with: data.latitude, data.longitude, data.speed
  });

// Subscribe to vehicle location
echo.private(`location.vehicle.${vehicleId}`)
  .listen('LocationUpdated', (data) => {
    // Update vehicle marker on map
  });
```

### Flutter (Mobile App)

```dart
import 'package:laravel_echo/laravel_echo.dart';

final echo = Echo(
  broadcaster: 'pusher',
  client: PusherClient('websocket-key-1234567890', ...),
);

echo.private('location.employee.$employeeId')
  .listen('LocationUpdated', (event) {
    final lat = event.data['latitude'];
    final lng = event.data['longitude'];
    // Update map
  });
```

---

## 📊 How It Works

```
Employee/Vehicle App
    ↓
POST /api/locations (dengan location data)
    ↓
LocationUpdated::dispatch() → WebSocket
    ↓
Private Channel: location.employee.5 atau location.vehicle.3
    ↓
All Connected Clients (Admin/Svelte/Flutter) → Real-time map update
```

---

## 🔒 Security

- **Private Channels**: Hanya authorized users bisa subscribe
- **Admin**: Bisa tracking employee dan vehicle milik tenant mereka
- **Employee**: Hanya tracking diri sendiri dan vehicle di tenant mereka
- Authorization check di `app/Broadcasting/LocationChannel.php`

---

## 📈 Performance

✅ **No Polling**: WebSocket = real-time tanpa delay
✅ **Lower Bandwidth**: Event-driven, bukan request-response
✅ **Scalable**: Dapat handle multiple concurrent connections
✅ **Efficient**: Hanya update yang berubah di-broadcast

---

## 🗑️ Database Cleanup

Saat **DELETE vehicle**:

```php
// Cascade delete semua location history
Location::where('trackable_type', Vehicle::class)
    ->where('trackable_id', $vehicleId)
    ->delete();
```

History tetap tersimpan selama vehicle aktif, otomatis dihapus saat vehicle dihapus.

---

## 📚 Dokumentasi Lengkap

Lihat: [WEBSOCKET_REALTIME_TRACKING.md](WEBSOCKET_REALTIME_TRACKING.md)

Berisi:

- Detailed architecture
- Client implementation code
- Production deployment
- Troubleshooting
- Testing examples

---

## ✨ Summary

✅ Implemented: Cascade delete vehicle locations
✅ Implemented: WebSocket real-time tracking (broadcast)
✅ Ready: Svelte frontend integration
✅ Ready: Flutter mobile integration
✅ Efficient: No polling, event-driven
✅ Secure: Private channel authorization
✅ Scalable: Production-ready setup

**Next Step**: Frontend (Svelte/Flutter) subscribe ke channels dan update map secara real-time!
