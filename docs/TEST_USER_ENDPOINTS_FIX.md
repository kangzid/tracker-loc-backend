# Test User Endpoints - Tenant Isolation

## Test 1: Login Admin Bahari
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@bahari.com",
    "password": "password123"
  }'
```

## Test 2: GET /users (List All Users)
```bash
curl -X GET http://localhost:8000/api/users \
  -H "Authorization: Bearer YOUR_TOKEN_BAHARI"
```

**Expected Result:**
```json
[
  {
    "id": 5,
    "name": "Admin PT Bahari Tegal",
    "email": "admin@bahari.com",
    "role": "admin"
  },
  {
    "id": 6,
    "name": "Nayda Porter",
    "email": "cufavytuju@example.com",
    "role": "employee",
    "employee": {
      "admin_id": 5  // ✅ Milik Bahari
    }
  }
]
```

❌ **TIDAK BOLEH** tampil:
- Admin Maju Sejahtera (id: 1)
- Superadmin (id: 3)
- Employee dari tenant lain (id: 2, 4)

## Test 3: GET /users/admins (List Admins)
```bash
curl -X GET http://localhost:8000/api/users/admins \
  -H "Authorization: Bearer YOUR_TOKEN_BAHARI"
```

**Expected Result:**
```json
[
  {
    "id": 5,
    "name": "Admin PT Bahari Tegal",
    "email": "admin@bahari.com",
    "role": "admin"
  }
]
```

✅ Hanya tampil dirinya sendiri
❌ **TIDAK BOLEH** tampil admin lain atau superadmin

## Test 4: GET /users/{id} - Try Access Other Admin
```bash
# Coba akses Admin Maju Sejahtera (id: 1)
curl -X GET http://localhost:8000/api/users/1 \
  -H "Authorization: Bearer YOUR_TOKEN_BAHARI"
```

**Expected Result:**
```json
{
  "message": "User not found"
}
```

## Test 5: GET /users/{id} - Try Access Superadmin
```bash
# Coba akses Superadmin (id: 3)
curl -X GET http://localhost:8000/api/users/3 \
  -H "Authorization: Bearer YOUR_TOKEN_BAHARI"
```

**Expected Result:**
```json
{
  "message": "User not found"
}
```

## Test 6: GET /users/{id} - Access Own Employee
```bash
# Akses employee sendiri (id: 6)
curl -X GET http://localhost:8000/api/users/6 \
  -H "Authorization: Bearer YOUR_TOKEN_BAHARI"
```

**Expected Result:**
```json
{
  "id": 6,
  "name": "Nayda Porter",
  "role": "employee",
  "employee": {
    "admin_id": 5  // ✅ Milik Bahari
  }
}
```

## Test 7: PUT /users/{id} - Try Update Other Admin
```bash
# Coba update Admin Maju Sejahtera (id: 1)
curl -X PUT http://localhost:8000/api/users/1 \
  -H "Authorization: Bearer YOUR_TOKEN_BAHARI" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Hacked Name",
    "email": "admin@majusejahtera.com",
    "role": "admin"
  }'
```

**Expected Result:**
```json
{
  "message": "User not found or unauthorized"
}
```

## Test 8: DELETE /users/{id} - Try Delete Other Admin
```bash
# Coba delete Admin Maju Sejahtera (id: 1)
curl -X DELETE http://localhost:8000/api/users/1 \
  -H "Authorization: Bearer YOUR_TOKEN_BAHARI"
```

**Expected Result:**
```json
{
  "message": "User not found or unauthorized"
}
```

## Test 9: Login Admin Maju Sejahtera
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@majusejahtera.com",
    "password": "password123"
  }'
```

## Test 10: GET /users (Admin Maju)
```bash
curl -X GET http://localhost:8000/api/users \
  -H "Authorization: Bearer YOUR_TOKEN_MAJU"
```

**Expected Result:**
```json
[
  {
    "id": 1,
    "name": "Admin PT. Maju Sejahtera",
    "role": "admin"
  },
  {
    "id": 2,
    "name": "John D. Updated",
    "role": "employee",
    "employee": {
      "admin_id": 1  // ✅ Milik Maju
    }
  },
  {
    "id": 4,
    "name": "Karyawan Test Kuota 2",
    "role": "employee",
    "employee": {
      "admin_id": 1  // ✅ Milik Maju
    }
  }
]
```

❌ **TIDAK BOLEH** tampil:
- Admin Bahari (id: 5)
- Superadmin (id: 3)
- Employee Bahari (id: 6)

---

## Summary Fix

### Yang Diperbaiki di UserController:

1. **index()** - List users
   - Admin hanya lihat: dirinya sendiri + employee miliknya
   - Tidak bisa lihat admin lain atau superadmin

2. **admins()** - List admins
   - Admin hanya lihat: dirinya sendiri
   - Tidak bisa lihat admin lain atau superadmin

3. **show()** - Detail user
   - Admin hanya bisa akses: dirinya sendiri + employee miliknya
   - Akses ke user lain return 404

4. **update()** - Update user
   - Admin hanya bisa update: dirinya sendiri + employee miliknya
   - Update user lain return 404

5. **destroy()** - Delete user
   - Admin hanya bisa delete: employee miliknya
   - Tidak bisa delete diri sendiri atau user lain

### Hasil:
✅ Admin Bahari tidak bisa lihat Admin Maju
✅ Admin Bahari tidak bisa lihat Superadmin
✅ Admin Bahari tidak bisa lihat/edit/delete employee tenant lain
✅ Setiap admin hanya bisa manage user di tenant mereka sendiri
