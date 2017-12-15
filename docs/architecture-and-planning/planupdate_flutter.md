# Roadmap Update Mobile App Flutter (SaaS Tahap 1)

Berdasarkan `architecture_guide_mobile.md`, berikut adalah langkah integrasi untuk sisi Mobile App (Staff Tracker) agar sinkron dengan fitur baru (SaaS dan Task Location) yang telah dibuat pada Backend.

---

## 1. Integrasi Ganti Password (Settings)
Fitur Profile untuk `Employee` dan `Admin` di aplikasi Mobile sekarang perlu dihubungkan dengan kapabilitas penggantian password tanpa OTP.

**Langkah Implementasi:**
- **Lokasi Fokus:** `lib/features/employee/settings/` (dan `lib/features/admin/settings/` bila ada).
- **Perubahan UI:** Di halaman profil/setting, kembangkan tombol "Ganti Password". Munculkan `CustomConfirmationDialog` dari `core/widgets/` untuk mengisi 3 kolom: *Password Lama*, *Password Baru*, *Konfirmasi Password*.
- **Endpoint Target:** `PUT /api/change-password`
- **Logic:** Gunakan package `http` dan ambil Base URL dari `ApiConfig.endpoints`. Kirim header wajib `'Authorization': 'Bearer $token'` yang diambil lewat `AuthStorage`.

---

## 2. Peningkatan Fitur Penyelesaian Tugas (Complete Task)
Backend kini mendukung rekam jejak koordinat aktual (`latitude` dan `longitude`) di mana pekerja menyelesaikan `Task` mereka.

**Langkah Implementasi:**
- **Lokasi Fokus:** Buat direktori (jika belum ada) di `lib/features/employee/tasks/`
- **Perubahan UI / Aksi:** 
  - Pada halaman *Task Detail* (`screens/task_detail_page.dart`), terdapat tombol aksi: "Selesaikan Tugas" ("Complete Task").
  - Ubah tombol ini menjadi fungsi *asynchronous*. Sebelum memanggil API Complete, instruksikan aplikasi untuk me-_request_ akses lokasi dan mengambil koordinat terkini `latitude/longitude` si device (menggunakan plugin `geolocator` atau metode yang setara di project Flutter Anda).
- **Endpoint Target:** `PUT /api/tasks/{id}/complete`
- **Payload Request:** 
  Sertakan properti koordinat JSON selain catatan. Contoh:
  ```json
  {
      "completion_notes": "Barang sudah sampai tujuan",
      "latitude": -6.21462,
      "longitude": 106.84513
  }
  ```

---

## 3. Penyesuaian Endpoint Daftar Tasks (My Tasks)
Karena backend kini mendukung sistem filter berdasarkan Status dan Priority, sesuaikan *data fetching* pada halaman tugas (Tasks).

**Langkah Implementasi:**
- **Lokasi Fokus:** `lib/features/employee/tasks/screens/tasks_page.dart`
- **Perubahan Logika UI:** 
  - Ganti endpoint *list* murni Anda ke `GET /api/my-tasks` dengan menambahkan *query parameter* untuk fitur filter.
  - Tambahkan komponen `Dropdown` atau `Chip` kecil di atas halaman yang isinya `Urgent`, `High`, `Medium`, atau `Low`. Lalu reload ListView menggunakan URL  `GET /api/my-tasks?priority={value}`.
- Gunakan `if (!mounted) return;` sebelum melakukan `setState()` pada *loading indicator* saat update filter berdasarkan *Best Practice* di arsitektur guide Anda.

---

## 4. Penanganan Quota HTTP 403 (Mode Admin)
Apabila Admin membuka versi App dari *Aplikasi Mobile* dan mencoba membuat karyawan / kendaraan lewat aplikasi, mereka juga bisa tersandung batas kuota.

**Langkah Implementasi:**
- **Lokasi Fokus:** `lib/features/admin/employee/` dan `lib/features/admin/vehicle/`
- **Perubahan State:** Saat _HTTP request_ POST mendapatkan status `403`, periksa body response JSON: jika berisi `EMPLOYEE_QUOTA_EXCEEDED` atau `VEHICLE_QUOTA_EXCEEDED`.
- **Eksekusi Komponen:** Munculkan `CustomConfirmationDialog` berjenis 'Error/Alert' dari `core/widgets/custom_confirmation_dialog.dart` yang mengatakan bahwa "Kuota telah habis, harap akses dashboard Superadmin atau upgrade paket".
