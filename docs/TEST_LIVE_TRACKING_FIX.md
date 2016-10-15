# Test Live Tracking - Tenant Isolation

## Test 1: Login Admin Bahari
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin.bahari@example.com",
    "password": "password123"
  }'
```

## Test 2: Cek Live Tracking Admin Bahari
```bash
curl -X GET http://localhost:8000/api/locations/live \
  -H "Authorization: Bearer YOUR_TOKEN_BAHARI"
```

**Expected Result:**
```json
{
  "employees": [
    {
      "id": 3,
      "admin_id": 2,  // ✅ ID Admin Bahari
      "user": {
        "name": "Karyawan Bahari 1"
      }
    }
  ],
  "vehicles": [
    {
      "id": 2,
      "admin_id": 2,  // ✅ ID Admin Bahari
      "vehicle_number": "B 1234 XYZ"
    }
  ]
}
```

❌ **TIDAK BOLEH** tampil employee/vehicle dari Admin Maju Sejahtera!

## Test 3: Login Admin Maju Sejahtera
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin.maju@example.com",
    "password": "password123"
  }'
```

## Test 4: Cek Live Tracking Admin Maju Sejahtera
```bash
curl -X GET http://localhost:8000/api/locations/live \
  -H "Authorization: Bearer YOUR_TOKEN_MAJU"
```

**Expected Result:**
```json
{
  "employees": [
    {
      "id": 1,
      "admin_id": 1,  // ✅ ID Admin Maju
      "user": {
        "name": "Karyawan Maju 1"
      }
    },
    {
      "id": 2,
      "admin_id": 1,  // ✅ ID Admin Maju
      "user": {
        "name": "Karyawan Maju 2"
      }
    }
  ],
  "vehicles": [
    {
      "id": 1,
      "admin_id": 1,  // ✅ ID Admin Maju
      "vehicle_number": "B 5678 ABC"
    }
  ]
}
```

❌ **TIDAK BOLEH** tampil employee/vehicle dari Admin Bahari!

## Test 5: Cross-Tenant Access (Should Fail)

### Admin Bahari coba akses employee history dari Maju Sejahtera
```bash
curl -X GET "http://localhost:8000/api/locations/employee/1/history" \
  -H "Authorization: Bearer YOUR_TOKEN_BAHARI"
```

**Expected Result:**
```json
{
  "message": "Employee not found or unauthorized"
}
```

### Admin Bahari coba akses vehicle history dari Maju Sejahtera
```bash
curl -X GET "http://localhost:8000/api/locations/vehicle/1/history" \
  -H "Authorization: Bearer YOUR_TOKEN_BAHARI"
```

**Expected Result:**
```json
{
  "message": "Vehicle not found or unauthorized"
}
```

## Test 6: Login sebagai Employee

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "karyawan.bahari1@example.com",
    "password": "password123"
  }'
```

### Cek Live Tracking sebagai Employee
```bash
curl -X GET http://localhost:8000/api/locations/live \
  -H "Authorization: Bearer YOUR_TOKEN_EMPLOYEE"
```

**Expected Result:**
```json
{
  "employees": [
    {
      "id": 3,  // ✅ Hanya dirinya sendiri
      "user": {
        "name": "Karyawan Bahari 1"
      }
    }
  ],
  "vehicles": [
    {
      "id": 2,  // ✅ Vehicle di tenant yang sama (Bahari)
      "vehicle_number": "B 1234 XYZ"
    }
  ]
}
```

---

## Summary Fix

### Yang Diperbaiki di LocationController:

1. **liveTracking()** - Tambah filter `admin_id`
   - Admin: hanya lihat employee & vehicle miliknya
   - Employee: hanya lihat dirinya sendiri & vehicle di tenant yang sama

2. **employeeHistory()** - Tambah verifikasi ownership
   - Admin: hanya bisa akses history employee miliknya
   - Employee: hanya bisa akses history sendiri

3. **vehicleHistory()** - Tambah verifikasi ownership
   - Admin: hanya bisa akses history vehicle miliknya
   - Employee: hanya bisa akses history vehicle di tenant yang sama

### Hasil:
✅ Admin Bahari hanya lihat data Bahari
✅ Admin Maju hanya lihat data Maju
✅ Employee hanya lihat data di tenant mereka
✅ Cross-tenant access ditolak (404/403)
