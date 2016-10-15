# Test Script - Subscription Quota Fix

## Test 1: Login Admin Bahari
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin.bahari@example.com",
    "password": "password123"
  }'
```

Simpan token dari response.

## Test 2: Cek Status Subscription Admin Bahari
```bash
curl -X GET http://localhost:8000/api/subscription/status \
  -H "Authorization: Bearer YOUR_TOKEN_BAHARI"
```

**Expected Result:**
```json
{
  "subscription": {
    "max_employees": 2,
    "max_vehicles": 2
  },
  "usage": {
    "employees": {
      "current": 0,     // ✅ Harus 0 (belum punya employee)
      "max": 2,
      "remaining": 2    // ✅ Harus 2 (masih bisa tambah 2)
    },
    "vehicles": {
      "current": 1,     // ✅ Harus 1 (sudah punya 1 vehicle)
      "max": 2,
      "remaining": 1    // ✅ Harus 1 (masih bisa tambah 1)
    }
  }
}
```

## Test 3: Tambah Employee Baru (Admin Bahari)
```bash
curl -X POST http://localhost:8000/api/employees \
  -H "Authorization: Bearer YOUR_TOKEN_BAHARI" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Karyawan Bahari 1",
    "email": "karyawan.bahari1@example.com",
    "password": "password123",
    "role": "employee",
    "employee_id": "EMP-BAHARI-001",
    "phone": "081234567890",
    "department": "Operasional",
    "position": "Staff"
  }'
```

**Expected Result:**
```json
{
  "user": { ... },
  "employee": {
    "admin_id": 2,  // ✅ ID admin Bahari
    "employee_id": "EMP-BAHARI-001"
  }
}
```

## Test 4: Cek Dashboard Admin Bahari
```bash
curl -X GET http://localhost:8000/api/dashboard/stats \
  -H "Authorization: Bearer YOUR_TOKEN_BAHARI"
```

**Expected Result:**
```json
{
  "total_employees": 1,      // ✅ Harus 1 (baru tambah 1)
  "active_employees": 1,
  "total_vehicles": 1,       // ✅ Harus 1 (sudah ada 1)
  "active_vehicles": 1,
  "today_attendances": 0,
  "pending_tasks": 0,
  "in_progress_tasks": 0
}
```

## Test 5: Login Admin Maju Sejahtera
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin.maju@example.com",
    "password": "password123"
  }'
```

## Test 6: Cek Dashboard Admin Maju Sejahtera
```bash
curl -X GET http://localhost:8000/api/dashboard/stats \
  -H "Authorization: Bearer YOUR_TOKEN_MAJU"
```

**Expected Result:**
```json
{
  "total_employees": 2,      // ✅ Harus 2 (punya 2 employee)
  "active_employees": 2,
  "total_vehicles": 1,       // ✅ Harus 1 (punya 1 vehicle)
  "active_vehicles": 1,
  "today_attendances": 0,
  "pending_tasks": 0,
  "in_progress_tasks": 0
}
```

## Test 7: Verifikasi Isolasi - Admin Maju tidak bisa lihat employee Bahari
```bash
curl -X GET http://localhost:8000/api/employees \
  -H "Authorization: Bearer YOUR_TOKEN_MAJU"
```

**Expected Result:**
Hanya tampil 2 employee milik Maju Sejahtera, TIDAK ada employee Bahari.

---

## Summary Hasil Yang Diharapkan

### Admin Bahari (max: 2 employee, 2 vehicle)
- ✅ Current: 0 employee, 1 vehicle
- ✅ Bisa tambah employee baru
- ✅ Tidak bisa lihat data Maju Sejahtera

### Admin Maju Sejahtera (max: sesuai subscription)
- ✅ Current: 2 employee, 1 vehicle
- ✅ Tidak bisa lihat data Bahari
- ✅ Dashboard hanya tampil data sendiri
