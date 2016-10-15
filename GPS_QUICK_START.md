# 🚀 Quick Start - GPS Vehicle Tracking

## Setup dalam 5 Menit

### Step 1: Create Vehicle & Get Token

```bash
# Login sebagai admin
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@bahari.com",
    "password": "password123"
  }'

# Simpan token dari response
export ADMIN_TOKEN="your_admin_token_here"

# Create vehicle
curl -X POST http://localhost:8000/api/vehicles \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "vehicle_number": "B 1234 GPS",
    "vehicle_type": "Truck",
    "brand": "Hino",
    "model": "Ranger",
    "year": 2023
  }'
```

**Response:**
```json
{
  "vehicle": { ... },
  "tracking_token": "a1b2c3d4e5f6...64char",  // ⭐ SIMPAN INI!
  "message": "Vehicle created successfully..."
}
```

### Step 2: Test GPS Connection

```bash
# Ganti dengan tracking_token dari step 1
export GPS_TOKEN="a1b2c3d4e5f6...64char"

# Test ping
curl -X GET http://localhost:8000/api/gps/ping \
  -H "X-Tracking-Token: $GPS_TOKEN"
```

**Expected:**
```json
{
  "success": true,
  "message": "Connection successful",
  "data": { ... }
}
```

### Step 3: Send Location

```bash
# Kirim lokasi pertama
curl -X POST http://localhost:8000/api/gps/track \
  -H "X-Tracking-Token: $GPS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "latitude": -6.200000,
    "longitude": 106.816666,
    "speed": 60.5,
    "accuracy": 10.0
  }'
```

**Expected:**
```json
{
  "success": true,
  "message": "Location updated successfully",
  "data": { ... }
}
```

### Step 4: View Live Tracking

```bash
# Login sebagai admin dan lihat live tracking
curl -X GET http://localhost:8000/api/locations/live \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

**Expected:**
```json
{
  "vehicles": [
    {
      "id": 1,
      "vehicle_number": "B 1234 GPS",
      "latitude": "-6.20000000",
      "longitude": "106.81666600",
      "last_location_update": "2026-04-07T12:35:00.000000Z"
    }
  ]
}
```

---

## 🐍 Testing dengan Python Simulator

### Install Requirements
```bash
pip install requests
```

### Edit gps_simulator.py
```python
# Ganti token dengan tracking_token Anda
TRACKING_TOKEN = "a1b2c3d4e5f6...64char"
```

### Run Simulator
```bash
python gps_simulator.py
```

**Menu:**
```
1. Send single location      - Kirim 1x lokasi
2. Start continuous simulation - Simulasi pergerakan (kirim setiap 5 detik)
3. Get vehicle status         - Cek status vehicle
4. Exit
```

---

## 🔧 Wokwi ESP32 Setup

### 1. Buka Wokwi.com
https://wokwi.com/projects/new/esp32

### 2. Copy Code dari GPS_TRACKING_GUIDE.md
- Ganti `YOUR_WIFI_SSID`
- Ganti `YOUR_WIFI_PASSWORD`
- Ganti `YOUR_SERVER_IP` (gunakan ngrok jika local)
- Ganti `YOUR_VEHICLE_TRACKING_TOKEN`

### 3. Add GPS Module
- Add Part → GPS Module
- Connect:
  - GPS TX → ESP32 RX (GPIO 16)
  - GPS RX → ESP32 TX (GPIO 17)
  - GPS VCC → ESP32 3V3
  - GPS GND → ESP32 GND

### 4. Run Simulation
- Click "Start Simulation"
- GPS akan kirim lokasi setiap 10 detik
- Cek live tracking di dashboard

---

## 📱 Postman Collection

### Import Collection
1. Open Postman
2. Import → `docs/LocaTrack-Backend-API.postman_collection.json`
3. Add GPS Tracking folder dengan endpoints:
   - GET /api/gps/ping
   - POST /api/gps/track
   - GET /api/gps/status

### Set Environment Variables
```
tracking_token = a1b2c3d4e5f6...64char
```

---

## 🎯 Testing Checklist

- [ ] Create vehicle → Get tracking_token
- [ ] Test ping → Success
- [ ] Send location → Success
- [ ] View live tracking → Vehicle muncul dengan lokasi
- [ ] Send location lagi → Lokasi terupdate
- [ ] Regenerate token → Old token invalid
- [ ] Test dengan new token → Success

---

## 🔒 Security Tips

1. **Jangan commit token** ke git
2. **Regenerate token** jika bocor
3. **Monitor tracking** secara berkala
4. **Disable vehicle** jika tidak digunakan

---

## 🆘 Troubleshooting

### Error: "Invalid tracking token"
- Cek token sudah benar
- Cek token belum di-regenerate
- Cek vehicle masih active

### Error: "Validation failed"
- Cek format latitude (-90 to 90)
- Cek format longitude (-180 to 180)
- Cek speed >= 0

### Location tidak update
- Cek response API (success: true?)
- Cek last_location_update di database
- Refresh live tracking page

---

## 📞 Support

Dokumentasi lengkap: `GPS_TRACKING_GUIDE.md`
Wokwi example: `GPS_TRACKING_GUIDE.md` (Arduino Code section)
Python simulator: `gps_simulator.py`

**Status:** READY TO USE ✅
