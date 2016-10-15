# 🏗️ Architecture Guide - Staff Tracker

Dokumen ini menjelaskan struktur proyek, standar kode, dan panduan pengembangan untuk aplikasi **Staff Tracker**. Tujuannya adalah memastikan konsistensi dan kemudahan *maintenance* bagi tim pengembang.

---

## 📂 Struktur Proyek

Aplikasi ini menggunakan pendekatan **Feature-First Architecture**. Kode dipecah berdasarkan "Fitur" fungsional, bukan hanya berdasarkan jenis file (seperti memisahkan semua controller dan view secara global).

```
lib/
├── core/                  # Logika & Widget yang digunakan di seluruh aplikasi
│   ├── config/            # Konfigurasi global (API, Firebase, Theme)
│   ├── routes/            # Definisi Navigasi & Named Routes
│   ├── theme/             # Styling & Warna aplikasi
│   ├── utils/             # Fungsi helper (Format tanggal, Auth storage)
│   └── widgets/           # Widget reusable (CustomAppBar, Dialogs)
│
├── features/              # Modul fungsional utama
│   ├── admin/             # Fitur khusus Admin
│   │   ├── dashboard/     # Halaman Home Admin
│   │   ├── employee/      # Manajemen Karyawan (CRUD)
│   │   ├── tracking/      # Live Map Tracking
│   │   └── vehicle/       # Manajemen Kendaraan (CRUD)
│   │
│   ├── employee/          # Fitur khusus Karyawan
│   │   ├── attendance/    # Absensi & Riwayat
│   │   ├── dashboard/     # Halaman Home Karyawan
│   │   ├── settings/      # Profil & Info Akun
│   │   └── tracking/      # GPS Mode (Pengirim Lokasi)
│   │
│   ├── auth/              # Login & Authentication Logic
│   └── splash/            # Splash Screen
│
└── main.dart              # Entry point aplikasi
```

---

## 🧱 Core Components (Inti Aplikasi)

Komponen-komponen ini adalah fondasi aplikasi. Jangan menduplikasi logika ini di dalam fitur.

### 1. `ApiConfig` (`core/config/api_config.dart`)
Mengatur URL backend. Secara default menggunakan `Firebase Remote Config` untuk mendapatkan *Base URL*, dengan *fallback* jika gagal.
*   **Best Practice**: Selalu gunakan `ApiConfig.endpoints` daripada menulis string URL mentah di halaman.

### 2. `AuthStorage` (`core/utils/auth_storage.dart`)
Mengelola penyimpanan lokal token dan data sesi pengguna menggunakan `SharedPreferences`.
*   Kegunaan: `saveLoginData`, `getLoginData`, `clearLoginData`.

### 3. `CustomAppBar` (`core/widgets/custom_app_bar.dart`)
AppBar standar aplikasi yang konsisten (putih, teks tebal, support icon actions).
*   **Wajib** digunakan di setiap halaman baru agar tampilan seragam.

### 4. `CustomConfirmationDialog` (`core/widgets/custom_confirmation_dialog.dart`)
Dialog standar untuk konfirmasi (Ya/Tidak) atau Alert (Info).
*   Gunakan untuk Logout, Delete Confirmation, atau Error Message.

---

## 🚀 Panduan Menambah Fitur Baru

Jika Anda ingin membuat fitur baru (misal: "Laporan Gaji"), ikuti langkah ini:

### 1. Buat Folder Feature
Buat folder di `lib/features/laporan/`.
Di dalamnya, buat struktur:
*   `screens/` : Halaman UI utama (misal: `salary_page.dart`)
*   `widgets/` : Widget kecil khusus halaman tersebut (misal: `salary_card.dart`)

### 2. Implementasi UI & Logika
*   Gunakan `StatefulWidget` jika butuh `setState` (misal loading data).
*   Gunakan `http` package untuk memanggil API.
*   Tangani status: `_loading`, `_error`, dan data sukses.

### 3. Daftarkan Route
Buka `lib/core/routes/app_routes.dart`.
*   Tambahkan konstanta nama route: `static const String salary = '/salary';`.
*   Tambahkan ke map `routes`: `salary: (context) => const SalaryPage()`.

### 4. Tambahkan Entry Point
Tambahkan tombol atau menu icon di `HomeAdminPage` atau `HomeEmployeePage` untuk menuju fitur baru tersebut.

---

## 📡 Integrasi Eksternal

### A. Backend API (Laravel)
Komunikasi menggunakan REST API.
*   Header Wajib: `'Authorization': 'Bearer $token'`
*   Selalu cek `token` null sebelum request. Jika null, arahkan ke Login.

### B. Firebase Realtime Database
Digunakan untuk **Live Tracking**.
*   **Admin**: `listen` ke node `vehicles/` untuk menerima update lokasi real-time.
*   **Employee**: `set` atau `update` ke node `vehicles/[plat_nomor]` untuk mengirim lokasi.

### C. Maps (OpenStreetMap)
Menggunakan `flutter_map` dan `latlong2`.
*   Gratis, tidak butuh API Key Google Maps untuk tampilan peta dasar.

---

## ⚠️ Aturan Penting (Do's & Don'ts)

✅ **DO**:
*   Selalu gunakan `if (!mounted) return;` setelah `await` sebelum memanggil `setState` atau `Navigator`. Ini mencegah crash.
*   Gunakan `HugeIcons` untuk ikon agar terlihat modern dan *premium*.
*   Pecah widget yang panjang (> 200 baris) ke file terpisah di folder `widgets/`.

❌ **DON'T**:
*   Jangan menulis *Hardcoded String* (URL, API Key) di dalam UI. Masukkan ke `ApiConfig` atau `const`.
*   Jangan campur logika Admin dan Employee dalam satu file. Pisahkan foldernya.

---

## 👷‍♂️ Workflow Git (Tim)

1.  **Pull** dulu sebelum ngoding: `git pull origin dev`
2.  Buat **Branch** baru untuk fitur: `git checkout -b fitur-baru`
3.  Commit dengan pesan jelas: `git commit -m "Menambahkan halaman Laporan"`
4.  Push & Pull Request.

---

*Dokumen ini dibuat untuk membantu tim pengembang Staff Tracker menjaga kualitas kode.*
*Updated: v1.1.0*
