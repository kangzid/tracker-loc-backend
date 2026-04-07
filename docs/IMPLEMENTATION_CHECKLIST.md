# Implementation Checklist - WebSocket & Cascade Delete

## ✅ Backend Implementation (Completed)

### 1. Cascade Delete Vehicle Locations
- [x] Update `VehicleController@destroy()` method
- [x] Query delete semua `Location` dengan `trackable_type=Vehicle` dan `trackable_id=$vehicleId`
- [x] Delete vehicle record setelah locations dihapus
- **File**: `app/Http/Controllers/Api/VehicleController.php`

### 2. WebSocket Event Broadcasting
- [x] Create `LocationUpdated` event class
  - [x] Implement `ShouldBroadcast` interface
  - [x] Private channel: `location.{trackableType}.{trackableId}`
  - [x] Broadcast payload dengan latitude, longitude, speed, accuracy, etc
  - **File**: `app/Events/LocationUpdated.php`

### 3. Channel Authorization
- [x] Create `LocationChannel` broadcasting class
  - [x] Admin: bisa subscribe ke employee dan vehicle milik tenant mereka
  - [x] Employee: hanya ke diri sendiri dan vehicle di tenant mereka
  - [x] Prevent unauthorized access
  - **File**: `app/Broadcasting/LocationChannel.php`

### 4. Routes & Broadcasting Setup
- [x] Register channel di `routes/channels.php`
  - [x] Channel pattern: `location.{trackableType}.{trackableId}`
  - [x] Authorization via `LocationChannel@join()`
  - **File**: `routes/channels.php`

### 5. Auto-Broadcast Location Updates
- [x] Update `LocationController@store()` method
  - [x] Fire `LocationUpdated` event saat location baru diterima
  - [x] Broadcast ke private channel
  - **File**: `app/Http/Controllers/Api/LocationController.php`

- [x] Update `VehicleController@updateLocation()` method
  - [x] Fire `LocationUpdated` event saat admin update vehicle location
  - [x] Broadcast ke private channel
  - **File**: `app/Http/Controllers/Api/VehicleController.php`

### 6. Documentation
- [x] Create `docs/WEBSOCKET_REALTIME_TRACKING.md` - Dokumentasi lengkap
- [x] Create `docs/WEBSOCKET_QUICK_SETUP.md` - Quick reference & setup guide
- [x] Create `docs/IMPLEMENTATION_CHECKLIST.md` - File ini

---

## 📝 How to Test Backend

### 1. Test Cascade Delete
```bash
# Login as admin
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Delete vehicle (should cascade delete locations)
curl -X DELETE http://localhost:8000/api/vehicles/{vehicleId} \
  -H "Authorization: Bearer {token}"

# Verify locations are deleted
SELECT * FROM locations WHERE trackable_type='App\Models\Vehicle' AND trackable_id={vehicleId};
# Should return empty result
```

### 2. Test WebSocket Broadcasting
```bash
# Start WebSocket server
php artisan websockets:serve

# In another terminal, submit location
curl -X POST http://localhost:8000/api/locations \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "latitude": -6.2088,
    "longitude": 106.8456,
    "trackable_type": "employee",
    "trackable_id": 5,
    "speed": 45,
    "accuracy": 10
  }'

# Check WebSocket logs untuk event dispatch
```

---

## 🎨 Frontend Implementation (Next Steps)

### Svelte (Web App)
- [ ] Install `laravel-echo` dan `pusher-js`
- [ ] Setup Echo client dengan WebSocket config
- [ ] Subscribe ke `location.employee.*` channels
- [ ] Subscribe ke `location.vehicle.*` channels
- [ ] Listen to `LocationUpdated` event
- [ ] Update map markers real-time

### Flutter (Mobile App)
- [ ] Add `laravel_echo` package ke `pubspec.yaml`
- [ ] Setup Flutter Echo client
- [ ] Subscribe ke location channels
- [ ] Update Google Maps markers real-time
- [ ] Handle reconnection & network errors

---

## 🔧 Production Setup (Prerequisites)

### Server Requirements
- [ ] WebSocket port 6001 open (firewall)
- [ ] PHP 8.0+ dengan ext-pcntl (untuk websockets:serve)
- [ ] Redis (optional, untuk scale WebSocket server)

### Option 1: Self-Hosted WebSocket (Recommended)
- [ ] Install `beyondco/laravel-websockets`
- [ ] Setup `config/broadcasting.php` untuk pusher driver
- [ ] Configure `.env` dengan WebSocket credentials
- [ ] Setup supervisor untuk daemon process
  - File: `/etc/supervisor/conf.d/laravel-websockets.conf`
  - Command: `php artisan websockets:serve`
  - Autostart: true

### Option 2: Pusher Cloud Service
- [ ] Sign up di pusher.com
- [ ] Get credentials (APP_ID, APP_KEY, APP_SECRET)
- [ ] Update `.env` dengan Pusher credentials
- [ ] No WebSocket server setup needed (cloud-managed)

---

## 📊 Database

### No Migration Needed
- Location table sudah ada
- Tidak ada schema changes

### Data Flow
```
POST /api/locations
    ↓
Location::updateOrCreate() - Update latest location
    ↓
LocationUpdated::dispatch() - Fire event
    ↓
WebSocket channel broadcast - Send to subscribed clients
    ↓
Client receives event & updates map
```

---

## 🚀 Deployment Checklist

### Pre-Deployment
- [ ] Test cascade delete in staging
- [ ] Test WebSocket broadcasting in staging
- [ ] Load test WebSocket with multiple concurrent connections
- [ ] Setup monitoring/logging untuk WebSocket server

### Deployment
- [ ] `php artisan migrate`
- [ ] `composer install --no-dev`
- [ ] Update `.env` production settings
- [ ] Setup supervisor untuk WebSocket daemon
- [ ] Configure nginx/reverse proxy untuk WebSocket (if needed)
- [ ] Test end-to-end dengan Svelte/Flutter client

### Post-Deployment
- [ ] Monitor WebSocket server logs
- [ ] Monitor database for growth (Location table)
- [ ] Setup automated cleanup untuk old locations (optional, if needed)
- [ ] Monitor client-side connection health

---

## 📋 Files Summary

### New Files Created
```
app/
  Events/
    └── LocationUpdated.php (155 lines)
  Broadcasting/
    └── LocationChannel.php (63 lines)

docs/
  ├── WEBSOCKET_REALTIME_TRACKING.md (370+ lines)
  ├── WEBSOCKET_QUICK_SETUP.md (160+ lines)
  └── IMPLEMENTATION_CHECKLIST.md (this file)

routes/
  └── channels.php (updated)
```

### Files Modified
```
app/Http/Controllers/Api/
  ├── LocationController.php (added LocationUpdated import & broadcast)
  └── VehicleController.php (added cascade delete + LocationUpdated broadcast)
```

---

## ✨ Key Features

✅ **Cascade Delete**: Vehicle dihapus → Locations otomatis dihapus
✅ **Real-Time Broadcasting**: Location update langsung di-broadcast
✅ **Private Channels**: Hanya authorized users bisa subscribe
✅ **Multi-Client Support**: Svelte web + Flutter mobile
✅ **Efficient**: WebSocket event-driven, no polling
✅ **Secure**: Authorization check di channel join
✅ **Scalable**: Ready untuk production dengan supervisor/Pusher

---

## 🔗 Related Documentation

- [ROLE_FEATURES_FLOW.md](ROLE_FEATURES_FLOW.md) - Role & fitur per user type
- [API_DOCUMENTATION.md](API_DOCUMENTATION.md) - Full API reference
- [WEBSOCKET_REALTIME_TRACKING.md](WEBSOCKET_REALTIME_TRACKING.md) - Detailed WebSocket guide
- [WEBSOCKET_QUICK_SETUP.md](WEBSOCKET_QUICK_SETUP.md) - Quick reference

---

## ❓ FAQ

**Q: Apakah perlu ubah location history tracking?**
A: Tidak, history tetap direkam di Location table. Hanya auto-broadcast saat location update.

**Q: Kalau WebSocket server down, apa terjadi?**
A: Location tetap tersimpan (REST API jalan normal), tapi real-time broadcast tidak aktif. Client bisa polling `/api/locations/live` sebagai fallback.

**Q: Berapa sering client harus submit location?**
A: Rekomendasi setiap 5-10 detik. Bisa dikonfigurasi di mobile app.

**Q: Apakah billing/cost untuk WebSocket?**
A: Jika menggunakan self-hosted (websockets:serve), hanya server cost. Jika Pusher cloud, ada plan berbayar.

---

## 📞 Support

Untuk pertanyaan teknis lebih lanjut, refer ke:
- `docs/WEBSOCKET_REALTIME_TRACKING.md` - Troubleshooting section
- Laravel Broadcasting docs: https://laravel.com/docs/broadcasting
- Laravel WebSockets docs: https://beyondco.de/docs/laravel-websockets/

