# LocaTrack Backend API

Sistem tracking lokasi karyawan dan kendaraan operasional berbasis Laravel API.

## Fitur Utama

1. **Absensi berbasis lokasi (Geofencing)**
   - Check-in/out dengan validasi lokasi
   - Deteksi otomatis area kantor

2. **Tracking kendaraan operasional**
   - Real-time location tracking
   - History perjalanan kendaraan

3. **Live monitoring (karyawan & kendaraan)**
   - Dashboard real-time
   - Status aktif karyawan dan kendaraan

4. **Geofencing otomatis**
   - Area kantor dan area kerja
   - Notifikasi masuk/keluar area

5. **Task assignment (tugas lapangan)**
   - Assign tugas ke karyawan
   - Tracking progress tugas

6. **Sharing lokasi sementara**
   - Share lokasi dengan token
   - Expired time untuk keamanan

7. **Analitik & laporan otomatis**
   - Dashboard statistik
   - Laporan kehadiran dan produktivitas

## User Roles

- **Admin/Manajer HR**: Kelola absensi & laporan
- **Manajer Armada**: Lihat posisi kendaraan & jarak tempuh  
- **Karyawan Lapangan**: Absen, terima tugas, panic button
- **Manajemen**: Ringkasan kinerja dari dashboard

## Tech Stack

- Laravel 11
- MySQL Database
- Laravel Sanctum (API Authentication)
- RESTful API

## Installation

1. Clone repository
```bash
git clone <repository-url>
cd LocaTrack-backend
```

2. Install dependencies
```bash
composer install
```

3. Setup environment
```bash
cp .env.example .env
```

4. Configure database di `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=locatrack
DB_USERNAME=root
DB_PASSWORD=
```

5. Generate application key
```bash
php artisan key:generate
```

6. Run migrations dan seeder
```bash
php artisan migrate --seed
```

7. Start development server
```bash
php artisan serve
```

API akan tersedia di: `http://localhost:8000/api`

## Database Schema

### Users
- id, name, email, password, role (admin/employee), is_active

### Employees  
- id, user_id, employee_id, phone, address, department, position, latitude, longitude

### Vehicles
- id, vehicle_number, vehicle_type, brand, model, year, latitude, longitude, is_active

### Attendances
- id, employee_id, date, check_in, check_out, check_in_lat/lng, check_out_lat/lng, status

### Tasks
- id, title, description, assigned_to, assigned_by, latitude, longitude, status, priority, due_date

### Locations (Polymorphic)
- id, trackable_type, trackable_id, latitude, longitude, speed, accuracy, recorded_at

### Geofences
- id, name, center_lat, center_lng, radius, type, is_active

## API Documentation

Lihat file `docs/API_DOCUMENTATION.md` untuk detail lengkap endpoint API.
Untuk testing API, lihat `docs/API_TEST.md`.

## Default Users

### Admin
- Email: admin@locatrack.com
- Password: password123

### Karyawan
- Email: john@locatrack.com / jane@locatrack.com
- Password: password123

## Contributing

1. Fork repository
2. Create feature branch
3. Commit changes
4. Push to branch
5. Create Pull Request
