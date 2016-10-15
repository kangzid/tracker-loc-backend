# 🚗 GPS Vehicle Tracking System

## 📋 Overview

Sistem tracking kendaraan menggunakan **token-based authentication** untuk GPS device. Setiap kendaraan memiliki **unique tracking token** yang digunakan oleh GPS device untuk mengirim lokasi tanpa perlu login.

## 🔑 Konsep

### Alur Kerja:
```
1. Admin buat kendaraan → Sistem generate tracking token
2. Admin copy token → Install ke GPS device (Wokwi/Hardware)
3. GPS device kirim lokasi → Pakai token di header
4. Sistem validasi token → Update lokasi kendaraan
5. Admin lihat live tracking → Real-time location
```

### Keuntungan:
- ✅ GPS device tidak perlu login (cukup token)
- ✅ Token unik per vehicle (aman)
- ✅ Bisa revoke token jika device hilang/dicuri
- ✅ Public endpoint (tidak perlu auth admin)
- ✅ Tenant isolation tetap terjaga

---

## 🚀 Setup Kendaraan

### 1. Create Vehicle (Admin)

**Endpoint:** `POST /api/vehicles`

**Request:**
```bash
curl -X POST http://localhost:8000/api/vehicles \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "vehicle_number": "B 1234 XYZ",
    "vehicle_type": "Truck",
    "brand": "Hino",
    "model": "Ranger",
    "year": 2023
  }'
```

**Response:**
```json
{
  "vehicle": {
    "id": 1,
    "admin_id": 1,
    "vehicle_number": "B 1234 XYZ",
    "vehicle_type": "Truck",
    "tracking_token": "a1b2c3d4e5f6...64char",
    "token_generated_at": "2026-04-07T12:00:00.000000Z"
  },
  "tracking_token": "a1b2c3d4e5f6...64char",
  "message": "Vehicle created successfully. Save this tracking token for your GPS device."
}
```

⚠️ **PENTING:** Simpan `tracking_token` ini! Token hanya ditampilkan sekali saat create.

### 2. Get Token (Jika Lupa)

**Endpoint:** `GET /api/vehicles/{id}/token`

```bash
curl -X GET http://localhost:8000/api/vehicles/1/token \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

**Response:**
```json
{
  "vehicle_id": 1,
  "vehicle_number": "B 1234 XYZ",
  "tracking_token": "a1b2c3d4e5f6...64char",
  "generated_at": "2026-04-07T12:00:00.000000Z"
}
```

### 3. Regenerate Token (Revoke Old Token)

**Endpoint:** `POST /api/vehicles/{id}/regenerate-token`

```bash
curl -X POST http://localhost:8000/api/vehicles/1/regenerate-token \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

**Response:**
```json
{
  "message": "Tracking token regenerated successfully. Update your GPS device with new token.",
  "vehicle_id": 1,
  "vehicle_number": "B 1234 XYZ",
  "tracking_token": "new_token_64char",
  "generated_at": "2026-04-07T13:00:00.000000Z"
}
```

⚠️ Token lama akan langsung invalid!

---

## 📡 GPS Device Integration

### Public Endpoints (Tidak Perlu Login)

#### 1. Test Connection (Ping)

**Endpoint:** `GET /api/gps/ping`

**Headers:**
```
X-Tracking-Token: a1b2c3d4e5f6...64char
```

**Request:**
```bash
curl -X GET http://localhost:8000/api/gps/ping \
  -H "X-Tracking-Token: YOUR_VEHICLE_TOKEN"
```

**Response:**
```json
{
  "success": true,
  "message": "Connection successful",
  "data": {
    "vehicle_id": 1,
    "vehicle_number": "B 1234 XYZ",
    "vehicle_type": "Truck",
    "is_active": true,
    "last_update": "2026-04-07T12:30:00.000000Z"
  }
}
```

#### 2. Send Location (Track)

**Endpoint:** `POST /api/gps/track`

**Headers:**
```
X-Tracking-Token: a1b2c3d4e5f6...64char
Content-Type: application/json
```

**Body:**
```json
{
  "latitude": -6.200000,
  "longitude": 106.816666,
  "speed": 60.5,
  "accuracy": 10.0
}
```

**Request:**
```bash
curl -X POST http://localhost:8000/api/gps/track \
  -H "X-Tracking-Token: YOUR_VEHICLE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "latitude": -6.200000,
    "longitude": 106.816666,
    "speed": 60.5,
    "accuracy": 10.0
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Location updated successfully",
  "data": {
    "vehicle_id": 1,
    "vehicle_number": "B 1234 XYZ",
    "latitude": "-6.20000000",
    "longitude": "106.81666600",
    "updated_at": "2026-04-07T12:35:00.000000Z"
  }
}
```

#### 3. Get Status

**Endpoint:** `GET /api/gps/status`

**Headers:**
```
X-Tracking-Token: a1b2c3d4e5f6...64char
```

**Request:**
```bash
curl -X GET http://localhost:8000/api/gps/status \
  -H "X-Tracking-Token: YOUR_VEHICLE_TOKEN"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "vehicle": {
      "id": 1,
      "vehicle_number": "B 1234 XYZ",
      "vehicle_type": "Truck",
      "is_active": true
    },
    "location": {
      "latitude": "-6.20000000",
      "longitude": "106.81666600",
      "last_update": "2026-04-07T12:35:00.000000Z"
    },
    "latest_tracking": {
      "latitude": "-6.20000000",
      "longitude": "106.81666600",
      "speed": 60.5,
      "accuracy": 10.0,
      "recorded_at": "2026-04-07T12:35:00.000000Z"
    }
  }
}
```

---

## 🔧 Wokwi ESP32 Example

### Arduino Code (ESP32 + GPS)

```cpp
#include <WiFi.h>
#include <HTTPClient.h>
#include <TinyGPS++.h>

// WiFi credentials
const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";

// API Configuration
const char* serverUrl = "http://YOUR_SERVER_IP:8000/api/gps/track";
const char* trackingToken = "YOUR_VEHICLE_TRACKING_TOKEN";

// GPS
TinyGPSPlus gps;
HardwareSerial GPS_Serial(1);

void setup() {
  Serial.begin(115200);
  GPS_Serial.begin(9600, SERIAL_8N1, 16, 17); // RX=16, TX=17
  
  // Connect to WiFi
  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi Connected!");
  
  // Test connection
  testConnection();
}

void loop() {
  // Read GPS data
  while (GPS_Serial.available() > 0) {
    gps.encode(GPS_Serial.read());
  }
  
  // Send location every 10 seconds
  if (gps.location.isUpdated()) {
    sendLocation(gps.location.lat(), gps.location.lng(), gps.speed.kmph());
    delay(10000); // 10 seconds
  }
}

void testConnection() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin("http://YOUR_SERVER_IP:8000/api/gps/ping");
    http.addHeader("X-Tracking-Token", trackingToken);
    
    int httpCode = http.GET();
    if (httpCode > 0) {
      String payload = http.getString();
      Serial.println("Connection Test:");
      Serial.println(payload);
    }
    http.end();
  }
}

void sendLocation(double lat, double lng, double speed) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-Tracking-Token", trackingToken);
    
    // Create JSON payload
    String jsonPayload = "{";
    jsonPayload += "\"latitude\":" + String(lat, 6) + ",";
    jsonPayload += "\"longitude\":" + String(lng, 6) + ",";
    jsonPayload += "\"speed\":" + String(speed, 2) + ",";
    jsonPayload += "\"accuracy\":10.0";
    jsonPayload += "}";
    
    int httpCode = http.POST(jsonPayload);
    
    if (httpCode > 0) {
      String response = http.getString();
      Serial.println("Location sent:");
      Serial.println(response);
    } else {
      Serial.println("Error sending location");
    }
    
    http.end();
  }
}
```

### Wokwi diagram.json

```json
{
  "version": 1,
  "author": "Your Name",
  "editor": "wokwi",
  "parts": [
    { "type": "wokwi-esp32-devkit-v1", "id": "esp", "top": 0, "left": 0 },
    { "type": "wokwi-gps-module", "id": "gps", "top": 100, "left": 200 }
  ],
  "connections": [
    [ "esp:TX", "gps:RX", "green", [] ],
    [ "esp:RX", "gps:TX", "blue", [] ],
    [ "esp:GND", "gps:GND", "black", [] ],
    [ "esp:3V3", "gps:VCC", "red", [] ]
  ]
}
```

---

## 🧪 Testing

### Test 1: Create Vehicle & Get Token
```bash
# 1. Login sebagai admin
# 2. Create vehicle
# 3. Copy tracking_token dari response
```

### Test 2: Test GPS Connection
```bash
curl -X GET http://localhost:8000/api/gps/ping \
  -H "X-Tracking-Token: YOUR_TOKEN"
```

Expected: `"success": true`

### Test 3: Send Location
```bash
curl -X POST http://localhost:8000/api/gps/track \
  -H "X-Tracking-Token: YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "latitude": -6.200000,
    "longitude": 106.816666,
    "speed": 60.5,
    "accuracy": 10.0
  }'
```

Expected: `"success": true, "message": "Location updated successfully"`

### Test 4: View Live Tracking (Admin)
```bash
curl -X GET http://localhost:8000/api/locations/live \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

Expected: Vehicle dengan lokasi terbaru

---

## 🔒 Security

### Token Security:
- ✅ Token 64 karakter (256-bit random)
- ✅ Unique per vehicle
- ✅ Bisa di-revoke kapan saja
- ✅ Tenant isolation tetap terjaga

### Best Practices:
1. **Jangan share token** ke orang lain
2. **Regenerate token** jika device hilang/dicuri
3. **Monitor tracking** secara berkala
4. **Disable vehicle** jika tidak digunakan

---

## 📊 Database Schema

```sql
-- vehicles table
ALTER TABLE vehicles ADD COLUMN tracking_token VARCHAR(64) UNIQUE;
ALTER TABLE vehicles ADD COLUMN token_generated_at TIMESTAMP;
```

---

## 🎯 Summary

### Endpoints untuk Admin:
- `POST /api/vehicles` - Create vehicle (auto-generate token)
- `GET /api/vehicles/{id}/token` - Get token
- `POST /api/vehicles/{id}/regenerate-token` - Regenerate token
- `GET /api/locations/live` - View live tracking

### Endpoints untuk GPS Device (Public):
- `GET /api/gps/ping` - Test connection
- `POST /api/gps/track` - Send location
- `GET /api/gps/status` - Get status

### Token Management:
- Token auto-generated saat create vehicle
- Token bisa di-regenerate (revoke old token)
- Token disimpan di header: `X-Tracking-Token`

Sistem siap digunakan untuk tracking kendaraan dengan GPS external atau Wokwi! 🚀
