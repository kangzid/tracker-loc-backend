# FIX: Data Karyawan Bocor Antar Tenant (Multi-Tenant Isolation)

## 🔴 MASALAH YANG DITEMUKAN

### Gejala:
1. ✗ Admin dari tenant A bisa melihat data employee dari tenant B
2. ✗ Dashboard menampilkan total dari SEMUA tenant (251 employees, 244 vehicles)
3. ✗ Edit employee dari tenant A bisa dilihat oleh tenant B
4. ✗ Vehicle list sudah benar (20 data dengan pagination), tapi dashboard salah karena count() tanpa filter tenant

### Root Cause:
Sistem sudah memiliki struktur multi-tenant (SaaS) dengan tabel `subscriptions`, TAPI tidak ada kolom `admin_id` di tabel:
- `employees`
- `vehicles`
- `tasks`
- `geofences`
- `notifications`

Akibatnya, semua query tidak ter-filter berdasarkan tenant/admin yang login.

## ✅ SOLUSI

### 1. Tambahkan kolom `admin_id` ke semua tabel tenant
Migration sudah dibuat: `2026_04_05_000000_add_admin_id_to_tenant_tables.php`

### 2. Update semua Model untuk include `admin_id`
- ✅ Employee.php
- ✅ Vehicle.php
- ✅ Task.php
- ✅ Geofence.php
- ✅ Notification.php

### 3. Update semua Controller untuk filter berdasarkan `admin_id`
- ✅ EmployeeController.php
- ✅ VehicleController.php
- ✅ TaskController.php
- ✅ GeofenceController.php
- ✅ AttendanceController.php (untuk geofence check)
- ✅ Dashboard stats di routes/api.php

### 4. Tambahkan helper method di User model
- ✅ `getAdminId()` - untuk mendapatkan admin_id dari user yang login

## 📋 LANGKAH INSTALASI

### Step 1: Backup Database
```bash
# Backup database sebelum migration
mysqldump -u root -p locatrack > backup_before_fix.sql
```

### Step 2: Run Migration
```bash
php artisan migrate
```

Migration akan menambahkan kolom `admin_id` ke tabel:
- employees
- vehicles
- tasks
- geofences
- notifications

### Step 3: Assign Admin ID ke Data Existing

**PENTING**: Anda perlu menentukan strategi assignment berdasarkan kondisi data Anda.

#### Opsi A: Jika Anda memiliki 1 admin utama (recommended untuk testing)
```bash
php artisan db:seed --class=AssignAdminIdSeeder
```

Seeder ini akan assign semua data existing ke admin pertama.

#### Opsi B: Manual Assignment (jika Anda tahu mapping tenant-nya)
```sql
-- Contoh: Assign employee ke admin tertentu
UPDATE employees SET admin_id = 1 WHERE id IN (1,2,3,4,5);
UPDATE employees SET admin_id = 2 WHERE id IN (6,7,8,9,10);

-- Assign vehicles
UPDATE vehicles SET admin_id = 1 WHERE id IN (1,2,3);
UPDATE vehicles SET admin_id = 2 WHERE id IN (4,5,6);

-- Assign tasks berdasarkan assigned_by
UPDATE tasks SET admin_id = assigned_by WHERE admin_id IS NULL;

-- Assign geofences
UPDATE geofences SET admin_id = 1 WHERE type = 'office';

-- Assign notifications berdasarkan employee
UPDATE notifications n 
INNER JOIN employees e ON n.employee_id = e.id 
SET n.admin_id = e.admin_id 
WHERE n.admin_id IS NULL;
```

### Step 4: Verifikasi Data
```sql
-- Check employees tanpa admin_id
SELECT COUNT(*) FROM employees WHERE admin_id IS NULL;

-- Check vehicles tanpa admin_id
SELECT COUNT(*) FROM vehicles WHERE admin_id IS NULL;

-- Check tasks tanpa admin_id
SELECT COUNT(*) FROM tasks WHERE admin_id IS NULL;

-- Check geofences tanpa admin_id
SELECT COUNT(*) FROM geofences WHERE admin_id IS NULL;

-- Check notifications tanpa admin_id
SELECT COUNT(*) FROM notifications WHERE admin_id IS NULL;
```

Semua query di atas harus return 0.

### Step 5: Update Migration untuk NOT NULL (Optional - Setelah data sudah di-assign)
Setelah semua data sudah memiliki admin_id, Anda bisa update migration untuk set NOT NULL:

```php
// Buat migration baru
php artisan make:migration set_admin_id_not_nullable_on_tenant_tables
```

```php
public function up(): void
{
    Schema::table('employees', function (Blueprint $table) {
        $table->foreignId('admin_id')->nullable(false)->change();
    });
    
    Schema::table('vehicles', function (Blueprint $table) {
        $table->foreignId('admin_id')->nullable(false)->change();
    });
    
    // ... dst untuk tabel lainnya
}
```

## 🧪 TESTING

### Test 1: Login sebagai Admin A
```bash
# Login
POST /api/login
{
  "email": "admin1@example.com",
  "password": "password"
}

# Check dashboard - harus hanya tampil data tenant A
GET /api/dashboard/stats
# Expected: total_employees = jumlah employee tenant A saja

# Check employees - harus hanya tampil employee tenant A
GET /api/employees
# Expected: hanya employee dengan admin_id = admin A
```

### Test 2: Login sebagai Admin B
```bash
# Login
POST /api/login
{
  "email": "admin2@example.com",
  "password": "password"
}

# Check dashboard - harus hanya tampil data tenant B
GET /api/dashboard/stats
# Expected: total_employees = jumlah employee tenant B saja

# Check employees - harus hanya tampil employee tenant B
GET /api/employees
# Expected: hanya employee dengan admin_id = admin B
```

### Test 3: Create New Employee
```bash
# Login sebagai Admin A
POST /api/employees
{
  "name": "New Employee",
  "email": "new@example.com",
  "password": "password123",
  "role": "employee",
  "employee_id": "EMP001"
}

# Verify admin_id di database
SELECT admin_id FROM employees WHERE employee_id = 'EMP001';
# Expected: admin_id = ID admin A
```

### Test 4: Cross-Tenant Access (Should Fail)
```bash
# Login sebagai Admin A
# Try to access employee dari tenant B
GET /api/employees/{id_employee_tenant_B}
# Expected: 404 Not Found
```

## 📊 HASIL YANG DIHARAPKAN

### Before Fix:
```json
// Admin Jaya Trans login
GET /api/dashboard/stats
{
  "total_employees": 251,      // ❌ SALAH - ini total dari semua tenant
  "total_vehicles": 244,        // ❌ SALAH - ini total dari semua tenant
  "today_attendances": 250,     // ❌ SALAH - ini total dari semua tenant
  "pending_tasks": 0
}
```

### After Fix:
```json
// Admin Jaya Trans login
GET /api/dashboard/stats
{
  "total_employees": 25,        // ✅ BENAR - hanya employee Jaya Trans
  "total_vehicles": 20,         // ✅ BENAR - hanya vehicle Jaya Trans
  "today_attendances": 20,      // ✅ BENAR - hanya attendance Jaya Trans
  "pending_tasks": 0
}
```

## 🔒 SECURITY IMPROVEMENTS

1. ✅ **Tenant Isolation**: Setiap admin hanya bisa akses data tenant mereka sendiri
2. ✅ **Data Privacy**: Employee dari tenant A tidak bisa dilihat oleh tenant B
3. ✅ **Geofence Isolation**: Setiap tenant punya geofence sendiri
4. ✅ **Task Isolation**: Task hanya bisa di-assign ke employee di tenant yang sama
5. ✅ **Dashboard Accuracy**: Dashboard menampilkan data yang akurat per tenant

## 🚨 CATATAN PENTING

1. **Backup Database**: WAJIB backup database sebelum run migration
2. **Data Assignment**: Pastikan semua data existing sudah di-assign ke admin yang benar
3. **Testing**: Test thoroughly sebelum deploy ke production
4. **Rollback Plan**: Siapkan rollback plan jika ada masalah

## 🔄 ROLLBACK (Jika Ada Masalah)

```bash
# Restore database dari backup
mysql -u root -p locatrack < backup_before_fix.sql

# Atau rollback migration
php artisan migrate:rollback
```

## 📝 CHECKLIST

- [ ] Backup database
- [ ] Run migration
- [ ] Assign admin_id ke data existing
- [ ] Verifikasi tidak ada data dengan admin_id NULL
- [ ] Test login sebagai admin A
- [ ] Test login sebagai admin B
- [ ] Verify dashboard stats
- [ ] Verify employee list
- [ ] Verify vehicle list
- [ ] Test create new employee
- [ ] Test cross-tenant access (should fail)
- [ ] Deploy ke production

## 🎯 KESIMPULAN

Bug ini terjadi karena sistem sudah dirancang untuk multi-tenant (SaaS) tapi implementasi tenant isolation belum lengkap. Dengan menambahkan kolom `admin_id` dan filter di semua query, sekarang setiap tenant benar-benar terisolasi dan tidak bisa melihat data tenant lain.
