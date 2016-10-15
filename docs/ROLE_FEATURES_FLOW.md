# LocaTrack - Role Features & Access Flow

Dokumentasi ini mendeskripsikan fitur, akses, dan workflow untuk setiap role dalam sistem LocaTrack: **Superadmin**, **Admin Tenant**, **Employee**, dan **Vehicle**.

---

## 📊 Daftar Isi

1. [Superadmin](#1-superadmin)
1.5. [Provision & Subscription Management](#15-provision--subscription-management)
2. [Admin Tenant](#2-admin-tenant)
3. [Employee](#3-employee)
4. [Vehicle](#4-vehicle)
5. [Tabel Perbandingan Akses](#5-tabel-perbandingan-akses)

---

## 1. SUPERADMIN

### Deskripsi
**Superadmin** adalah pemilik/pengelola platform LocaTrack yang memiliki akses tertinggi ke seluruh sistem. Mereka bertanggung jawab mengelola semua tenant (Admin), subscription, dan monitoring kesehatan platform.

### 🎯 Role Definition
- **Role Code**: `superadmin`
- **Type**: Platform Owner/System Administrator
- **Akses Level**: Tertinggi (Full Access)

### ✨ Fitur-Fitur Superadmin

#### A. Dashboard & Monitoring Platform
- **Akses**: `GET /api/superadmin/dashboard`
- **Fitur**:
  - 📊 Ringkasan statistik keseluruhan platform
  - 👥 Total jumlah Admin (Tenant)
  - ✅ Jumlah Admin yang aktif
  - 👨‍💼 Total Employee di semua tenant
  - 🚗 Total Vehicle di semua tenant
  - 📋 Status Subscription (active, expired, trial)
  - 📈 Monitoring real-time health platform

#### B. Manajemen Akun Admin (Tenant)
Superadmin dapat mengelola akun-akun Admin/Tenant dengan kontrol penuh:

**1. Lihat Daftar Admin**
- Endpoint: `GET /api/superadmin/admins?per_page=15&search=`
- Query Parameters:
  - `per_page`: Jumlah data per halaman (default: 15)
  - `search`: Cari berdasarkan nama atau email
- Response:
  - Data lengkap admin (id, name, email, created_at, is_active)
  - Status subscription terkini (plan, status, expired_at)
  - Jumlah hari remaining subscription (days_remaining)
  - Jumlah employee & vehicle yang dikelola

**2. Lihat Detail Admin**
- Endpoint: `GET /api/superadmin/admins/:id`
- Response:
  - Profile lengkap admin (id, name, email, phone, created_at, is_active)
  - Subscription details lengkap (plan, status, tanggal expire, kuota)
  - Jumlah employee yang dikelola
  - Jumlah vehicle yang dikelola
  - Sisa hari trial/langganan

**3. Buat Akun Admin Baru**
- Endpoint: `POST /api/superadmin/admins`
- Input:
  ```json
  {
    "company_name": "PT. Contoh Klien",
    "email": "admin@contohklien.com",
    "contact_phone": "08111222333",
    "plan": "monthly",
    "max_employees": 10,
    "max_vehicles": 5,
    "duration_days": 30
  }
  ```
- Response:
  - User baru dengan role 'admin'
  - Subscription record dengan plan & kuota sesuai request
  - Password plaintext (hanya muncul sekali)
- Catatan: Berbeda dari `/provision` yang publik, ini manual oleh superadmin dengan kontrol penuh plan/kuota/durasi

**4. Aktifkan/Nonaktifkan Admin (Suspend)**
- Endpoint: `PUT /api/superadmin/admins/:id/toggle`
- Efek:
  - Toggle status `is_active` admin
  - Aktif → Nonaktif (suspend): Admin tidak bisa login
  - Nonaktif → Aktif: Admin bisa login kembali
  - Data tetap tersimpan, tidak dihapus
  - Employee/Vehicle milik admin masih ada tapi tidak bisa diakses

**5. Reset Password Admin**
- Endpoint: `PUT /api/superadmin/admins/:id/reset-password`
- Input:
  ```json
  {
    "password": "newadminpass123"
  }
  ```
- Superadmin memaksa ganti password admin tanpa perlu password lama
- Tidak perlu OTP verification

**6. Hapus Akun Admin**
- Endpoint: `DELETE /api/superadmin/admins/:id`
- ⚠️ Operasi final, tidak dapat dibatalkan!
- Cascade delete:
  - Hapus user admin
  - Hapus semua employee milik admin
  - Hapus semua vehicle milik admin
  - Hapus semua subscription milik admin

#### C. Manajemen Subscription
Superadmin mengontrol semua subscription untuk mengatur monetisasi dan akses tenant:

**1. Lihat Daftar Subscription**
- Endpoint: `GET /api/superadmin/subscriptions?status=active&per_page=15`
- Query Parameters:
  - `status`: Filter by status (active | expired | cancelled) - kosongkan untuk semua
  - `per_page`: Jumlah data per halaman
- Response:
  - Admin yang memiliki subscription
  - Plan saat ini (trial, monthly, yearly, custom)
  - Tanggal mulai dan expire
  - Kuota employee & vehicle (max_employees, max_vehicles)
  - Status pembayaran

**2. Update Subscription**
- Endpoint: `PUT /api/superadmin/subscriptions/:id`
- Input (semua field opsional, kirim hanya yang ingin diubah):
  ```json
  {
    "plan": "yearly",
    "max_employees": 25,
    "max_vehicles": 10,
    "expired_at": "2027-04-04 00:00:00",
    "status": "active"
  }
  ```
- Update yang bisa dilakukan:
  - Plan (trial → monthly → yearly → custom)
  - Status (active → expired → canceled)
  - Tanggal expire (expired_at)
  - Kuota employee & vehicle (max_employees, max_vehicles)

**3. Perpanjang Subscription (Extend)**
- Endpoint: `POST /api/superadmin/subscriptions/:id/extend`
- Input:
  ```json
  {
    "days": 30
  }
  ```
- Efek:
  - Tambah durasi subscription sebanyak N hari
  - Update tanggal expired_at
  - Status tetap active
  - Kuota tidak berubah (gunakan update jika ingin naikkan kuota)

**4. Cancel Subscription**
- Endpoint: `DELETE /api/superadmin/subscriptions/:id`
- Efek:
  - Ubah status menjadi 'canceled'
  - Admin dan employee masih ada tapi fitur premium tidak aktif
  - Tidak bisa di-extend lagi (perlu create subscription baru)

### 📍 Akses Data Superadmin
- ✅ Melihat semua data di sistem tanpa batasan tenant
- ✅ Tidak memiliki employee pribadi
- ✅ Tidak memiliki vehicle pribadi
- ✅ Audit trail semua aktivitas admin
- ✅ Query cross-tenant

### 🔐 Proteksi Akses
- Middleware: `auth:sanctum` + `superadmin`
- Semua endpoint superadmin dilindungi
- Hanya user dengan role='superadmin' yang bisa akses
- Token-based authentication (Laravel Sanctum)

### 📲 Workflow Khas Superadmin
```
1. Login ke dashboard superadmin
   ↓
2. Monitoring statistik platform
   ↓
3. Review list admin & subscription mereka
   ↓
4. Jika ada tenant baru:
   - Buat akun admin baru
   - Set subscription plan & durasi
   - Send welcome credentials
   ↓
5. Manage renewal/upgrade subscription
   ↓
6. Handle admin yang inaktif (nonaktifkan atau hapus)
   ↓
7. Review & audit trail semua aktivitas
```

---

## 1.5 PROVISION & SUBSCRIPTION MANAGEMENT

Sebelum masuk ke penjelasan Admin Tenant secara detail, penting diketahui ada 2 endpoint khusus untuk SaaS provisioning dan subscription status:

### Provision (Self-Service Trial Account)
**Daftar Trial Account Baru - Public Endpoint**

- **Endpoint**: `POST /api/provision`
- **Auth**: Tidak perlu token (public)
- **Input**:
  ```json
  {
    "company_name": "PT. Baru Maju",
    "email": "admin@barumaju.com",
    "contact_name": "Budi Santoso",
    "contact_phone": "08123456789"
  }
  ```
- **Response**:
  - User baru dengan role 'admin' (created)
  - Subscription record dengan plan='trial' (default: 7 hari, 1 employee, 1 vehicle)
  - Temporary password untuk login pertama
  - Email confirmation dikirim ke alamat yang daftar
- **Kegunaan**: Calon pelanggan bisa self-register tanpa perlu intervensi superadmin
- **Catatan**: Data trial akan otomatis expired setelah durasi berakhir

### Subscription Status (Admin View Own Subscription)
**Cek Status & Kuota Subscription - Untuk Admin**

- **Endpoint**: `GET /api/subscription/status`
- **Auth**: Bearer Token (Admin login)
- **Response**:
  ```json
  {
    "plan": "trial",
    "status": "active",
    "expired_at": "2026-04-14T00:00:00Z",
    "days_remaining": 7,
    "max_employees": 1,
    "used_employees": 0,
    "max_vehicles": 1,
    "used_vehicles": 0,
    "features": ["basic_tracking", "task_management"]
  }
  ```
- **Kegunaan**: 
  - Admin bisa lihat kapan subscription mereka expire
  - Admin lihat sisa kuota employee & vehicle
  - Admin tahu fitur apa saja yang aktif di plan mereka
  - Admin lihat sisa hari subscription
- **Catatan**: Jika `days_remaining < 0`, subscription sudah expired, tidak bisa tambah employee/vehicle baru

---

### Deskripsi
**Admin Tenant** adalah pemilik/pengelola organisasi (perusahaan) yang berlangganan LocaTrack. Mereka mengelola employee, vehicle, task, geofence, dan semua fitur operasional dalam tenant mereka sendiri.

### 🎯 Role Definition
- **Role Code**: `admin`
- **Type**: Organization Owner / Manager
- **Akses Level**: Tinggi (Tenant Isolation)
- **Constraint**: Hanya bisa akses data employee/vehicle/task milik mereka sendiri

### ✨ Fitur-Fitur Admin Tenant

#### A. Dashboard & Analytics
**Akses**: `GET /api/dashboard/stats`

Dashboard menampilkan overview operasional tenant:
- 👥 Total employee aktif vs nonaktif
- 🚗 Total vehicle aktif vs nonaktif
- 📋 Attendance hari ini
- ✅ Task: pending, in_progress, completed

#### B. Manajemen Employee
Admin dapat mengelola semua employee (karyawan) di organisasi mereka:

**1. Buat Employee Baru**
- Endpoint: `POST /api/employees`
- Middleware: `subscription.quota:employee` → Cek kuota subscription
- Input:
  ```json
  {
    "user": {
      "name": "Budi Santoso",
      "email": "budi@company.com",
      "password": "password123",
      "is_active": true
    },
    "employee_id": "EMP001",
    "phone": "081234567890",
    "address": "Jl. Merdeka No.1",
    "department": "Operasional",
    "position": "Driver"
  }
  ```
- Proses:
  - Validasi kuota employee (tidak boleh melebihi subscription quota)
  - Create user dengan role='employee'
  - Create employee record dengan admin_id = user_id admin saat ini
  - Isolasi tenant: employee hanya bisa diakses admin yang create
  - Send welcome email ke employee

**2. Lihat Daftar Employee**
- Endpoint: `GET /api/employees`
- Fitur:
  - Pagination
  - Search (nama, employee_id, email)
  - Filter status aktif/nonaktif
  - Sort berbagai field
- Data yang ditampilkan:
  - Info lengkap employee
  - Department, position
  - Status aktif/nonaktif
  - Last location update
  - Latest attendance status

**3. Lihat Detail Employee**
- Endpoint: `GET /api/employees/{id}`
- Info:
  - Profil lengkap
  - Contact details
  - Department/position
  - Attendance history (today, this month)
  - Current location
  - Assigned tasks

**4. Update Employee**
- Endpoint: `PUT /api/employees/{id}`
- Field yang bisa di-update:
  - name, email, phone
  - address, department, position
  - is_active status
  - Password (jika diperlukan admin reset)

**5. Hapus Employee**
- Endpoint: `DELETE /api/employees/{id}`
- Cascade delete:
  - Hapus user terkait
  - Hapus attendance records
  - Hapus locations
  - Soft-delete assigned tasks atau reassign ke employee lain

#### C. Manajemen Vehicle
Admin dapat mengelola kendaraan yang dimiliki/disewa organisasi:

**1. Buat Vehicle Baru**
- Endpoint: `POST /api/vehicles`
- Middleware: `subscription.quota:vehicle` → Cek kuota
- Input:
  ```json
  {
    "vehicle_number": "B-1234-ABC",
    "vehicle_type": "Truck",
    "brand": "Hino",
    "model": "500 Series",
    "year": 2023,
    "is_active": true
  }
  ```
- Proses:
  - Validasi kuota vehicle
  - Create vehicle dengan admin_id = user_id admin
  - Inisialisasi location default
  - Siap untuk live tracking

**2. Lihat Daftar Vehicle**
- Endpoint: `GET /api/vehicles` + filtering
- Filter:
  - Status aktif/nonaktif
  - Vehicle type
  - Search berdasarkan nomor kendaraan
- Data:
  - Info lengkap vehicle
  - Status aktivitas (active/inactive)
  - Current location
  - Last update timestamp
  - Vehicle condition info

**3. Lihat Vehicle Aktif & Nonaktif**
- Endpoint: `GET /api/vehicles-active` dan `GET /api/vehicles-inactive`
- Untuk quick view status fleet

**4. Update Vehicle**
- Endpoint: `PUT /api/vehicles/{id}`
- Update fields: brand, model, year, status aktif/nonaktif

**5. Update Location Vehicle**
- Endpoint: `POST /api/vehicles/{id}/location`
- Dari IoT device atau manual input
- Input:
  ```json
  {
    "latitude": -6.2088,
    "longitude": 106.8456,
    "accuracy": 5,
    "speed": 45
  }
  ```
- Simpan ke Location table (polymorphic)

**6. Hapus Vehicle**
- Endpoint: `DELETE /api/vehicles/{id}`
- Soft delete atau hard delete
- Cascade: hapus semua location history

#### D. Manajemen Task
Admin membuat dan mengelola task untuk employee:

**1. Buat Task**
- Endpoint: `POST /api/tasks`
- Input:
  ```json
  {
    "title": "Pengiriman ke Jakarta Pusat",
    "description": "Deliver package to customer",
    "assigned_to": 5,
    "assigned_by": 1,
    "priority": "high",
    "status": "pending",
    "due_date": "2026-04-08 17:00:00"
  }
  ```
- Proses:
  - Create task dengan admin_id auto-filled
  - Link ke employee (assigned_to)
  - Send notification ke employee
  - Status awal: pending

**2. Lihat Daftar Task**
- Endpoint: `GET /api/tasks`
- Filter:
  - Status (pending, in_progress, completed)
  - Priority
  - Assigned to specific employee
  - Date range
- Sorting: due_date, priority

**3. Update Task**
- Endpoint: `PUT /api/tasks/{id}`
- Update: title, description, priority, due_date
- Assign ke employee berbeda jika diperlukan
- Status tidak bisa di-update langsung dari sini (employee yang update)

**4. Hapus Task**
- Endpoint: `DELETE /api/tasks/{id}`
- Cascade: hapus dari notification

#### E. Manajemen Geofence
Admin membuat geofence (virtual boundary) untuk validasi attendance:

**1. Buat Geofence**
- Endpoint: `POST /api/geofences`
- Input:
  ```json
  {
    "name": "Kantor Pusat",
    "latitude": -6.2088,
    "longitude": 106.8456,
    "radius_meters": 100,
    "description": "Main office location"
  }
  ```
- Kegunaan:
  - Validasi attendance (harus dalam radius)
  - Trigger notification jika employee keluar area
  - Manajemen zone operasional

**2. Lihat Daftar Geofence**
- Endpoint: `GET /api/geofences`
- Lihat semua geofence yang didefinisikan

**3. Update Geofence**
- Endpoint: `PUT /api/geofences/{id}`
- Update: nama, lokasi, radius

**4. Hapus Geofence**
- Endpoint: `DELETE /api/geofences/{id}`

#### F. Manajemen User (Sub-Admins)
Admin dapat membuat user lain (admin, employee) dalam tenant mereka:

**1. Lihat Daftar User**
- Endpoint: `GET /api/users`
- Lihat semua user di tenant (admin, employee)

**2. Lihat Admin Saja**
- Endpoint: `GET /api/users/admins`
- List secondary admins/managers dalam tenant (jika ada)

**3. Update User**
- Endpoint: `PUT /api/users/{id}`
- Update: name, email, is_active

**4. Hapus User**
- Endpoint: `DELETE /api/users/{id}`

#### G. Attendance Management
Admin dapat view dan manage attendance records:

**1. Lihat Semua Attendance**
- Endpoint: `GET /api/attendances`
- Pagination & filtering

**2. Lihat Attendance Employee Spesifik**
- Endpoint: `GET /api/admin/attendances/employee/{employeeId}`
- History attendance untuk satu employee

**3. Update Attendance**
- Endpoint: `PUT /api/admin/attendances/{id}`
- Admin bisa koreksi attendance (manual update)
- Contoh: employee lupa absen, dikoreksi admin

**4. Hapus Attendance**
- Endpoint: `DELETE /api/admin/attendances/{id}`
- Soft delete untuk audit trail

#### H. Location & Live Tracking
Admin dapat monitoring lokasi employee dan vehicle secara real-time:

**1. Live Tracking**
- Endpoint: `GET /api/locations/live`
- Get latest location semua employee/vehicle
- For map visualization

**2. History Lokasi Employee**
- Endpoint: `GET /api/locations/employee/{employeeId}/history`
- Lihat track history employee dalam date range tertentu

**3. History Lokasi Vehicle**
- Endpoint: `GET /api/locations/vehicle/{vehicleId}/history`
- Lihat perjalanan kendaraan

**4. Share Location**
- Endpoint: `POST /api/locations/share`
- Buat token share untuk third-party view location
- Public link tanpa perlu login

#### I. Notification Management
Admin menerima notifikasi tentang aktivitas tenant:

**1. Lihat Notifikasi**
- Endpoint: `GET /api/notifications`
- Pagination, filter read/unread

**2. Unread Notifications**
- Endpoint: `GET /api/notifications/unread`

**3. Mark as Read**
- Endpoint: `POST /api/notifications/{id}/read`

**4. Mark All as Read**
- Endpoint: `POST /api/notifications/read-all`

#### J. Subscription Management
Admin melihat status subscription mereka:

**1. Lihat Status Subscription**
- Endpoint: `GET /api/subscription/status`
- Info:
  - Plan saat ini (trial, starter, professional, enterprise)
  - Tanggal expire
  - Hari remaining
  - Kuota employee & vehicle (used/total)
  - Fitur yang aktif
  - Opsi upgrade/extend (link ke superadmin)

### 📍 Akses Data Admin Tenant
- ✅ Akses 100% ke data sendiri (employee, vehicle, task, geofence)
- ❌ TIDAK bisa akses data admin lain
- ❌ TIDAK bisa akses employee admin lain
- ✅ Akses employee's location, attendance, task mereka sendiri
- ✅ Akses vehicle mereka sendiri

### 🔐 Proteksi Akses (Tenant Isolation)
- **Middleware**: `auth:sanctum` + automatic tenant isolation
- **Mechanism**:
  - `admin_id` di setiap record employee, vehicle, task, geofence
  - Query otomatis di-filter: `where('admin_id', auth()->user()->id)`
  - Admin tidak bisa akses data admin lain
  - Jika try akses data admin lain → 403 Forbidden

### 📲 Workflow Khas Admin Tenant

```
1. Login ke dashboard tenant
   ↓
2. View dashboard stats (employee, vehicle, attendance)
   ↓
3. Setup initial data:
   - Tambah geofence lokasi kantor/warehouse
   - Buat employee dan generate akun
   - Daftar vehicle fleet
   ↓
4. Operational management:
   - Create task untuk employee
   - Monitor attendance (check-in/out)
   - Live tracking employee & vehicle
   ↓
5. Daily operations:
   - Review attendance records
   - Check task progress
   - Monitor location in real-time
   - Handle attendance corrections
   ↓
6. Admin tasks:
   - Manage user account
   - Update employee/vehicle info
   - View subscription status
   - Plan upgrade jika perlu
```

---

## 3. EMPLOYEE

### Deskripsi
**Employee** adalah karyawan yang di-manage oleh Admin Tenant. Mereka adalah end-user yang melakukan attendance check-in, location tracking, dan mengerjakan task.

### 🎯 Role Definition
- **Role Code**: `employee`
- **Type**: Karyawan / Field Worker
- **Akses Level**: Terbatas (Own Data Only)
- **Constraint**: Hanya bisa akses data diri sendiri

### ✨ Fitur-Fitur Employee

#### A. Dashboard & Profile
**Akses**: `GET /api/employee/dashboard` dan `GET /api/profile`

Dashboard menampilkan overview hari ini:
- 📍 Status attendance hari ini (check-in/out)
- 📋 Task pending & in-progress
- ✅ Completed tasks bulan ini
- 👤 Profile information
- 🔔 Personal notifications

#### B. Authentication & Profile
**1. Login**
- Endpoint: `POST /api/login`
- Credentials: email & password
- Response: API token (Sanctum)
- Berlaku untuk semua role (superadmin, admin, employee, vehicle)

**2. Lihat Profile**
- Endpoint: `GET /api/profile`
- Melihat data diri sendiri:
  - Name, email, phone
  - Employee ID, department, position
  - Address
  - Create/update timestamp

**3. Ubah Password**
- Endpoint: `PUT /api/change-password`
- Input: old_password, new_password
- Validasi old_password harus benar

**4. Logout**
- Endpoint: `POST /api/logout`
- Revoke current token
- Session berakhir

#### C. Attendance Management
**1. Check-in/out (Store Attendance)**
- Endpoint: `POST /api/attendances`
- Biasanya dari mobile app di pagi hari (check-in) dan sore (check-out)
- Input:
  ```json
  {
    "type": "checkin",
    "latitude": -6.2088,
    "longitude": 106.8456,
    "accuracy": 5,
    "timestamp": "2026-04-07 08:15:00"
  }
  ```
- Proses:
  - Validasi employee exists
  - Check jika ada geofence → validasi in-geofence
  - If tidak in geofence → warning/reject (configurable)
  - Create attendance record
  - Send notification ke admin
  - Return confirmation

**2. Check Location (Validasi Geofence)**
- Endpoint: `POST /api/attendances/check-location`
- Untuk check apakah current location dalam geofence sebelum check-in
- Input:
  ```json
  {
    "latitude": -6.2088,
    "longitude": 106.8456
  }
  ```
- Output:
  ```json
  {
    "in_geofence": true,
    "geofence_name": "Kantor Pusat",
    "distance_from_center": 45
  }
  ```

**3. Lihat Attendance Hari Ini**
- Endpoint: `GET /api/attendances/today`
- Return attendance record hari ini (jika ada)

**4. Lihat Attendance Bulanan**
- Endpoint: `GET /api/attendances/monthly`
- Return semua attendance bulan ini
- Untuk lihat attendance pattern

**5. Lihat Detail Attendance**
- Endpoint: `GET /api/attendances/{id}`
- View full detail attendance record

#### D. Task Management
**1. Lihat My Tasks**
- Endpoint: `GET /api/my-tasks`
- List task yang assigned ke employee ini
- Filter: status (pending, in_progress, completed)
- Sorting: due_date, priority

**2. Accept Task**
- Endpoint: `POST /api/tasks/{id}/accept`
- Mengkonfirmasi task, status masih pending tapi employee sudah accept
- Menunjukkan bahwa employee sudah aware tentang task ini

**3. Start Task**
- Endpoint: `POST /api/tasks/{id}/start`
- Change status dari pending → in_progress
- Record start_time
- Mulai mengerjakan task

**4. Complete Task**
- Endpoint: `POST /api/tasks/:id/complete`
- Input (opsional):
  ```json
  {
    "completion_notes": "Task completed successfully.",
    "latitude": -6.21462,
    "longitude": 106.84513
  }
  ```
- Fitur:
  - Change status dari in_progress → completed
  - Record completed_at timestamp
  - Optional: Tambahkan completion_notes (catatan penyelesaian)
  - Optional: Capture location saat task selesai (untuk verifikasi lokasi pekerjaan)

**5. Lihat Detail Task**
- Endpoint: `GET /api/tasks/{id}`
- View full task details:
  - Title, description
  - Priority, due_date
  - Status, assigned_by
  - Time tracking (started_at, completed_at)

#### E. Location Tracking
**1. Submit Current Location**
- Endpoint: `POST /api/locations`
- Employee/mobile app submit location berkala (e.g., setiap 5 menit)
- Input:
  ```json
  {
    "latitude": -6.2088,
    "longitude": 106.8456,
    "accuracy": 5,
    "speed": 45,
    "timestamp": "2026-04-07 10:30:00"
  }
  ```
- Proses:
  - Create location record
  - Link ke employee (polymorphic)
  - Update employee.last_location_update
  - Optional: trigger geofence-based alerts

**2. Lihat History Lokasi Sendiri**
- Endpoint: `GET /api/locations/employee/{employeeId}/history`
- Dengan employee_id = auth user's employee_id
- View perjalanan sendiri dalam date range
- Untuk self-tracking / verifikasi

#### F. Notification
Employee menerima notification dari sistem (task assignment, alerts, dll):

**1. Lihat Notifikasi**
- Endpoint: `GET /api/notifications`
- List semua notification yang diterima

**2. Unread Notifications**
- Endpoint: `GET /api/notifications/unread`
- Untuk badge count

**3. Mark as Read**
- Endpoint: `POST /api/notifications/{id}/read`

**4. Mark All as Read**
- Endpoint: `POST /api/notifications/read-all`

#### G. Register (First Time)
**Untuk employee baru** yang belum punya akun:
- Endpoint: `POST /api/register`
- Input:
  ```json
  {
    "name": "Budi Santoso",
    "email": "budi@company.com",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "employee",
    "employee_id": "EMP001",
    "phone": "081234567890",
    "department": "Operasional",
    "position": "Driver"
  }
  ```
- Proses:
  - Validasi employee_id exists dalam employee table
  - Create user account
  - Link ke employee record
  - Employee ready to login

### 📍 Akses Data Employee
- ✅ Akses 100% ke data diri sendiri
- ✅ Lihat task assigned ke diri sendiri
- ✅ Lihat attendance diri sendiri
- ✅ Lihat location history diri sendiri
- ❌ TIDAK bisa lihat employee lain
- ❌ TIDAK bisa modify data employee lain
- ❌ TIDAK bisa akses admin/superadmin features

### 🔐 Proteksi Akses
- **Middleware**: `auth:sanctum`
- **Mechanism**:
  - Employee hanya bisa akses resource dengan employee_id = auth user's employee_id
  - Query filter: `where('employee_id', auth()->user()->employee->id)`
  - Jika coba akses data orang lain → 403 Forbidden
  - API validate pada setiap request

### 📲 Workflow Khas Employee

```
1. Login (first time atau setiap hari)
   ↓
2. View dashboard
   - Check attendance status hari ini
   - See pending tasks
   ↓
3. Morning routine:
   - Cek location valid (in geofence)
   - Check-in di kantor/location
   - Accept tasks untuk hari ini
   ↓
4. During work:
   - Submit location berkala (GPS tracking)
   - Start task yang diberikan
   - Update task progress
   ↓
5. During field work:
   - Tracking location otomatis
   - Monitor geofence alerts
   - Check task details
   - Complete task saat selesai
   ↓
6. End of day:
   - Check-out di end location
   - Verify attendance hari ini
   - Review completed tasks
   - See next day tasks
   ↓
7. Notifikasi:
   - Receive task assignment notification
   - Receive alert jika keluar geofence
   - Receive pending task reminder
```

---

## 4. VEHICLE

### Deskripsi
**Vehicle** adalah kendaraan yang didaftarkan dalam sistem untuk fleet tracking. Vehicle bisa submit location data tapi TIDAK memiliki user account atau login authentication.

### 🎯 Role Definition
- **Role Code**: Tidak memiliki role (bukan user)
- **Type**: Asset/Equipment for Tracking
- **Akses Level**: Limited (Location Only)
- **Constraint**: Tidak bisa login, hanya bisa submit location data via API
- **Authentication**: Via API key atau direct admin request

### ✨ Fitur-Fitur Vehicle

#### A. Data Vehicle
Vehicle memiliki record dalam database dengan fields:
```
- id: unique identifier
- admin_id: siapa pemilik (tenant)
- vehicle_number: nomor kendaraan (B-1234-ABC)
- vehicle_type: jenis (truck, car, motorcycle, etc)
- brand: merek (Hino, Toyota, dll)
- model: model kendaraan
- year: tahun produksi
- latitude/longitude: current position
- last_location_update: timestamp update terakhir
- is_active: status aktif/nonaktif
```

#### B. Location Tracking (IoT Device)
**1. Update Location (dari IoT/GPS Device)**
- Endpoint: `POST /api/vehicles/{id}/location`
- Input:
  ```json
  {
    "latitude": -6.2088,
    "longitude": 106.8456,
    "accuracy": 5,
    "speed": 65,
    "heading": 180,
    "timestamp": "2026-04-07 10:30:00"
  }
  ```
- Proses:
  - Create location record di Location table
  - Link ke vehicle (polymorphic: `trackable_type = Vehicle`, `trackable_id = id`)
  - Update vehicle.latitude, longitude, last_location_update
  - Trigger geofence-based alerts (jika diperlukan)

**2. History Lokasi Vehicle**
- Endpoint: `GET /api/locations/vehicle/{vehicleId}/history`
- Akses: Admin tenant yang pemilik vehicle atau Superadmin
- Lihat perjalanan vehicle dalam date range tertentu
- Useful untuk:
  - Verify perjalanan driver
  - Audit trail
  - Analyze operational efficiency

#### C. Live Tracking
**1. Live Position Vehicle**
- Endpoint: `GET /api/locations/live`
- Return latest location semua vehicle aktif milik admin
- Untuk map visualization real-time

### 📍 Data Vehicle
Vehicle record berisi:
- 📝 Identitas kendaraan (vehicle_number, brand, model, year)
- 📍 Current location (latitude, longitude)
- ✅ Status (active/inactive)
- 🕐 Last location timestamp
- 📊 Location history (polymorphic relationship)

### 🔐 Proteksi Akses Vehicle Location
- **Location Submit**: 
  - Bisa dari IoT device dengan API key
  - Bisa dari admin manual update
  - Bisa dari mobile app driver
- **Location View**: 
  - Admin tenant pemilik bisa lihat
  - Superadmin bisa lihat
  - Employee pengemudi bisa lihat vehicle history mereka (jika role=employee + assigned vehicle)
- **Tenant Isolation**: 
  - Vehicle hanya bisa diakses admin_id pemilik
  - Query filter: `where('admin_id', auth()->user()->id)`

### 📲 Workflow Khas Vehicle

```
1. Vehicle Registration (oleh Admin Tenant):
   - Input vehicle data (nomor, type, brand, model)
   - Create vehicle record dengan admin_id = admin
   - Assign ke driver/employee (optional)
   - Activate vehicle
   ↓
2. Location Tracking (otomatis):
   - IoT/GPS device di kendaraan
   - Kirim location setiap interval (e.g., setiap 5 menit)
   - Location data dicatat di database
   - Last update timestamp terupdate
   ↓
3. Fleet Monitoring (oleh Admin):
   - Admin lihat vehicle di map (live tracking)
   - Monitor perjalanan vehicle
   - Check location history
   - Verify driver behavior
   ↓
4. Analytics:
   - Review perjalanan vehicle
   - Analyze route efficiency
   - Check fuel consumption vs distance
   - Audit trail untuk compliance
   ↓
5. Maintenance/Status Change:
   - Update vehicle status jika maintenance
   - Nonaktifkan vehicle jika out of service
   - Soft delete untuk record keeping
```

---

## 5. TABEL PERBANDINGAN AKSES

### Tabel Ringkas Akses Fitur per Role

| Fitur | Superadmin | Admin Tenant | Employee | Vehicle |
|-------|-----------|------------|----------|---------|
| **Dashboard** | Platform-wide | Tenant-wide | Personal | N/A |
| **Manage Admins** | ✅ Full | ❌ No | ❌ No | ❌ No |
| **Manage Subscription** | ✅ Full | 🔍 View only | ❌ No | ❌ No |
| **Create Employee** | ❌ No | ✅ Full | ❌ No | ❌ No |
| **Edit Employee** | ❌ No | ✅ Full | 🔍 Self only | ❌ No |
| **Create Vehicle** | ❌ No | ✅ Full | ❌ No | ❌ No |
| **Edit Vehicle** | ❌ No | ✅ Full | ❌ No | ❌ No |
| **Create Task** | ❌ No | ✅ Full | ❌ No | ❌ No |
| **Edit Task** | ❌ No | ✅ Full | 🔍 Self (accept/start/complete) | ❌ No |
| **Create Geofence** | ❌ No | ✅ Full | ❌ No | ❌ No |
| **Check-in/Attendance** | ❌ No | 🔍 View/Edit all | ✅ Self | ❌ No |
| **Submit Location** | ❌ No | 🔍 View all | ✅ Self | ✅ Yes (IoT) |
| **View Location History** | 🔍 All | 🔍 Tenant | ✅ Self | ❌ No |
| **Live Tracking** | ✅ All | ✅ Tenant | 🔍 Self | ❌ No |
| **Notification** | ❌ No | ✅ Full | ✅ Own | ❌ No |
| **User Management** | ❌ No | ✅ Limited | ❌ No | ❌ No |

**Keterangan:**
- ✅ Full: Akses penuh ke fitur
- 🔍 View only: Hanya bisa lihat, tidak bisa edit
- ❌ No: Tidak ada akses
- 🔍 Self only: Hanya bisa akses data diri sendiri
- N/A: Tidak applicable

### Tabel Tenant Isolation

| Scenario | Allowed | Reason |
|----------|---------|--------|
| Admin1 lihat employee Admin2 | ❌ | Tenant isolation |
| Admin1 lihat geofence Admin2 | ❌ | Tenant isolation |
| Employee lihat task dari admin lain | ❌ | Tidak dalam tenant |
| Superadmin lihat semua employee | ✅ | No tenant boundary |
| Employee lihat task assigned-nya | ✅ | Own data |
| Vehicle kirim location update | ✅ | Public endpoint |
| Admin lihat vehicle location | ✅ | Owner |

---

## 📋 Summary Table: Role Hierarchy

```
SUPERADMIN (Top Level)
├─ Full access ke semua data platform
├─ Manage: Admin accounts, subscriptions
├─ Monitor: Platform health & statistics
└─ Tidak memiliki employee/vehicle pribadi

ADMIN TENANT (Middle Level)
├─ Full access ke data tenant mereka
├─ Manage: Employee, Vehicle, Task, Geofence, Users
├─ Monitor: Tenant dashboard & attendance
├─ Batas: Hanya tenant mereka sendiri
└─ Tenant isolation enforced

EMPLOYEE (Operational Level)
├─ Terbatas akses ke data diri sendiri
├─ Can: Check-in/out, view task, submit location
├─ Cannot: Manage other employees, create task
├─ Batas: Own data only
└─ Role isolation enforced

VEHICLE (Asset Level)
├─ Tidak memiliki user account
├─ Can: Submit location (via IoT)
├─ Cannot: Access dashboard, view data
├─ Batas: Location submission only
└─ Admin pemilik yang manage
```

---

## 🔐 Security Summary

### Authentication Methods
- **Superadmin & Admin & Employee**: Token-based (Laravel Sanctum)
- **Vehicle**: API key atau direct admin submission

### Authorization Mechanism
1. **Role-based**: `auth()->user()->role` must match
2. **Tenant Isolation**: `admin_id` filtering for Admin & Employee data
3. **Ownership**: Employee only access own data
4. **Middleware Protection**: `auth:sanctum`, `superadmin`, custom middlewares

### Data Isolation Strategy
```
- Superadmin: No isolation (access all)
- Admin: Filtered by admin_id (tenant_id)
- Employee: Filtered by employee_id (own data)
- Vehicle: Filtered by admin_id (owner)
```

---

## 🎯 Use Cases per Role

### Superadmin Use Case
```
Hari 1: Review platform stats → 10 active admins, 500 total employees
Hari 2: 3 new tenant sign-ups → Create 3 admin accounts + trial subscriptions
Hari 3: Tenant A requests upgrade → Extend subscription + increase quota
Hari 4: Tenant B non-payment → Suspend subscription
Hari 5: Tenant C wants more features → Create custom plan
```

### Admin Tenant Use Case
```
Hari 1: Onboard 5 karyawan baru → Register employees + create accounts
Hari 2: Setup operasional → Add geofence kantor, daftar 3 vehicles
Hari 3: Assign tasks → Create daily delivery tasks untuk 5 drivers
Hari 4: Monitor attendance → Check attendance report, approve koreksi
Hari 5: Review location & tasks → Verify perjalanan driver + task completion
```

### Employee Use Case
```
Pagi: Cek location kantor → Check-in di geofence → Accept daily tasks
Pagi-Siang: Kerja → Start task → Submit location berkala → Update progress
Siang-Sore: Perjalanan → Monitor location di map → Complete task
Sore-Malam: Pulang → Check-out di lokasi akhir → Review daily stats
```

### Vehicle Use Case
```
Kontinyu: GPS device kirim location setiap 5 menit
Admin: Monitor vehicle di map (live tracking)
Admin: Review perjalanan vehicle (history)
Admin: Verify route efficiency & fuel consumption
```

---

## 📞 Contact & Support
Untuk pertanyaan atau klarifikasi lebih lanjut tentang role dan akses, silakan hubungi tim development.

**Last Updated**: April 7, 2026
