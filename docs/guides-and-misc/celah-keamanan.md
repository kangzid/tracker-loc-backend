# Security Review: tracker-loc-backend

## Context

Proyek ini adalah backend RESTful API menggunakan **Laravel 12** dengan fitur utama employee tracking, GPS monitoring, attendance, dan task management. User meminta review keamanan untuk menemukan celah atau masalah keamanan.

---

## Tech Stack

| Komponen | Detail |
|----------|--------|
| Framework | Laravel 12 (PHP 8.2+) |
| Database | MySQL (`locatrack`) |
| Auth | Laravel Sanctum (Bearer Token) |
| Roles | admin, employee, superadmin |
| Features | Multi-tenant, geofencing, Midtrans payment, real-time (Pusher/Reverb) |

---

## Security Findings Summary

### ✅ LOW RISK - Sudah Aman
- **SQL Injection**: Menggunakan Eloquent ORM dengan parameterized queries
- **XSS**: API-only (JSON responses, tidak ada Blade templates)
- **Input Validation**: Consistently menggunakan Laravel validation rules
- **Rate Limiting**: Sudah ada (Login 5/min, Location 60/min, API 120/min)
- **Password Hashing**: BCRYPT (Laravel default)

---

### ⚠️ MEDIUM RISK - Perlu Perhatian

#### 1. [MEDIUM] CORS Membolehkan Semua Origin
- **File:** `config/cors.php` line 16-25
- **Masalah:** `'allowed_origins' => ['*']` - production mengizinkan request dari domain manapun
- **Risk:** Attacker bisa melakukan CSRF atau memanggil API dari malicious site

#### 2. [MEDIUM] Debug Info Bocor di Production Response
- **File:** `app/Http/Controllers/Api/LocationController.php` line 38-42
- **Masalah:** Response JSON mengandung `debug: { user_id, employee_id, requested_id }`
- **Risk:** Internal IDs bocor ke client

#### 3. [MEDIUM] Password Plain Text di API Response
- **File:** `app/Http/Controllers/Api/SuperAdminController.php` line 217-220
- **Masalah:** Setelah create admin, password plain text dikembalikan di response
- **Risk:** Password terlihat di logs/network inspection

#### 4. [MEDIUM] Image Upload - Client Extension Trust
- **File:** `app/Http/Controllers/Api/SuperadminNotificationController.php` line 35-42
- **Masalah:** `getClientOriginalExtension()` digunakan (client-controlled), seharusnya pakai generated filename
- **Risk:** Extension spoofing jika validasi mimes bypassed

#### 5. [MEDIUM] GPS Tracking Tanpa Auth Middleware
- **File:** `routes/api.php` line 35-39
- **Masalah:** Endpoint `/gps/track`, `/gps/ping`, `/gps/status` tidak ada `auth:sanctum` middleware
- **Risk:** Jika token intercepted, attacker bisa submit fake GPS data

---

### 🔴 HIGH RISK - Harus Diperbaiki

#### 1. [HIGH] Public Image Access - ID Enumeration
- **File:** `routes/api.php` line 33
- **Route:** `GET /api/images/notifications/{adminId}/{date}/{filename}`
- **Masalah:**
  - Route publik tanpa authentication
  - `basename()` sanitization baik, tapi `adminId` dan `date` bisa enumerate semua image files
- **Risk:** Attacker bisa enumerate dan akses semua notification images

#### 2. [HIGH] IDOR Vulnerability - TaskController::show()
- **File:** `app/Http/Controllers/Api/TaskController.php` line 100-104
- **Masalah:**
  ```php
  public function show($id)
  {
      $task = Task::with([...])->findOrFail($id);
      return response()->json($task);  // Tidak ada tenant check!
  }
  ```
  Tidak ada validasi `admin_id` / `assigned_to` match dengan user yang request
- **Risk:** Authenticated user bisa lihat task user/admin lain

---

## Critical Files

| File | Priority |
|------|----------|
| `app/Http/Controllers/Api/TaskController.php` | HIGH - IDOR fix |
| `app/Http/Controllers/Api/LocationController.php` | MEDIUM - debug info |
| `app/Http/Controllers/Api/SuperAdminController.php` | MEDIUM - password exposure |
| `app/Http/Controllers/Api/ImageController.php` | HIGH - public access |
| `routes/api.php` | HIGH - auth middleware |
| `config/cors.php` | MEDIUM - origin restriction |
| `app/Http/Controllers/Api/SuperadminNotificationController.php` | MEDIUM - file upload |

---

## Verification Plan

Untuk memverifikasi fix:

1. **IDOR Test:** Login sebagai admin A, coba akses task ID milik admin B via Postman
2. **Image Access Test:** Akses `/api/images/notifications/1/2024-01-01/test.jpg` tanpa auth token
3. **Debug Info Test:** Check response JSON dari location endpoint untuk debug field
4. **CORS Test:** Check response headers, pastikan hanya domain yang diizinkan
5. **Password Exposure Test:** Create admin baru, check apakah password di response

---

## Options for User

**A) Fix semua vulnerability** - Saya akan perbaiki semua issue di atas secara bertahap

**B) Dokumentasi saja** - Simpan report ini, tidak perlu action sekarang

**C) Fix priority HIGH only** - Hanya perbaiki 2 issue HIGH risk

**D) Custom** - Pilih specific issue yang mau difix
