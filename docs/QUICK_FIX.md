# 🚨 QUICK FIX: Data Karyawan Bocor Antar Tenant

## Masalah
- Admin bisa lihat data employee dari tenant lain
- Dashboard menampilkan total dari SEMUA tenant (251 employees, 244 vehicles)
- Data tidak terisolasi per tenant

## Solusi Cepat (5 Menit)

### 1. Backup Database
```bash
mysqldump -u root -psidakaton locatrack > backup_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Run Migration
```bash
php artisan migrate
```

### 3. Assign Admin ID (Dry Run dulu)
```bash
php artisan fix:tenant-isolation --dry-run
```

### 4. Jika OK, Run Tanpa Dry Run
```bash
php artisan fix:tenant-isolation
```

### 5. Test
```bash
# Login sebagai admin dan cek dashboard
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@locatrack.com","password":"password123"}'

# Ambil token dari response, lalu:
curl -X GET http://localhost:8000/api/dashboard/stats \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Hasil Yang Diharapkan

### Before:
```json
{
  "total_employees": 251,  // ❌ Semua tenant
  "total_vehicles": 244    // ❌ Semua tenant
}
```

### After:
```json
{
  "total_employees": 25,   // ✅ Hanya tenant ini
  "total_vehicles": 20     // ✅ Hanya tenant ini
}
```

## Rollback (Jika Ada Masalah)
```bash
mysql -u root -psidakaton locatrack < backup_YYYYMMDD_HHMMSS.sql
```

## Detail Lengkap
Lihat file: `FIX_TENANT_ISOLATION.md`
