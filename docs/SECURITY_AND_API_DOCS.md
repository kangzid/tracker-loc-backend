# Dokumentasi Keamanan & API - LocaTrack Backend

Dokumen ini menjelaskan langkah-langkah *hardening* keamanan yang telah diterapkan pada sistem dan panduan penggunaan dokumentasi API otomatis menggunakan Scramble.

---

## 1. Laporan Hardening Keamanan (Post-Audit)

Setelah dilakukan audit keamanan (*pentesting*), berikut adalah perubahan krusial yang telah diimplementasikan untuk mengamankan sistem:

### A. Proteksi Mass Assignment (Model Level)
*   **Masalah**: Pengguna jahat bisa mengirimkan parameter seperti `role=superadmin` atau `admin_id=1` melalui request API untuk menaikkan level akun atau mencuri data tenant lain.
*   **Solusi**: 
    *   Field sensitif seperti `role`, `admin_id`, dan `tracking_token` telah dihapus dari properti `$fillable` di model `User` dan `Vehicle`.
    *   Pengisian field tersebut kini dilakukan secara **eksplisit** di Controller (menggunakan `$user->role = ...` atau `$model->forceFill(...)`), sehingga input dari user tidak akan bisa memanipulasi field ini.

### B. Rate Limiting (Brute Force Protection)
*   **Masalah**: Bot bisa mencoba menebak password atau melakukan spam request ke endpoint provisioning/tracking.
*   **Solusi**: Middleware `rate.limit` telah diterapkan pada rute:
    *   `/login`: Maksimal 5 percobaan per menit.
    *   `/register`, `/provision`, dan `/gps/track`: Dibatasi untuk mencegah spam.

### C. Proteksi Path Traversal
*   **Masalah**: Endpoint pengambilan gambar bisa disalahgunakan untuk membaca file rahasia sistem (misal: `.env`) dengan memanipulasi parameter `$filename`.
*   **Solusi**: Menambahkan fungsi `basename()` di `ImageController` untuk memastikan hanya nama file di dalam folder tujuan yang bisa diakses.

### D. Security Headers & CSP
*   **Masalah**: Kerentanan terhadap serangan XSS dan Clickjacking, serta pemblokiran script dokumentasi karena kebijakan yang terlalu ketat.
*   **Solusi**: 
    *   Mengonfigurasi `EnsureSecurityHeaders` middleware.
    *   Menerapkan **Content Security Policy (CSP)** yang aman namun tetap mendukung asset eksternal yang diperlukan (unpkg & cdnjs).
    *   Menghapus middleware keamanan yang redundan untuk menghindari konflik.

### E. Proteksi Dokumentasi API
*   **Masalah**: Informasi struktur API (endpoint, parameter) bersifat rahasia dan tidak boleh dilihat publik.
*   **Solusi**: 
    *   Menambahkan **Basic Auth** (Popup login browser) pada rute `/docs/api`.
    *   Membatasi akses hanya untuk akun dengan role `superadmin` melalui Laravel Gate.

---

## 2. Panduan API Dokumentasi (Scramble)

Sistem ini menggunakan **Scramble** untuk menghasilkan dokumentasi API secara otomatis.

### Bagaimana Scramble Bekerja?
Scramble tidak memerlukan Anda menulis file YAML/JSON secara manual. Ia menggunakan **Static Analysis** (analisis kode tanpa menjalankannya):
1.  Ia melihat file `routes/api.php` untuk menemukan semua endpoint.
2.  Ia membaca **Type Hints** (misal: `string $email`) dan **Request Validation** (misal: `Rule::required()`) untuk menentukan parameter apa saja yang dibutuhkan.
3.  Ia membaca **Return Types** di Controller untuk mengetahui format response (JSON).

### Mengapa Dokumentasinya Begitu Lengkap?
Karena kita mengikuti standar Laravel yang baik:
*   Menggunakan **FormRequest** untuk validasi.
*   Menggunakan **Eloquent Resource** untuk format response.
*   Menulis **Docblocks** (komentar di atas fungsi) yang dibaca oleh Scramble sebagai deskripsi.

### Cara Update Dokumentasi (Jika Ada Fitur Baru)
Agar fitur baru Anda muncul dengan rapi di dokumentasi, ikuti tips ini:

1.  **Tambahkan Rute**: Pastikan rute baru Anda terdaftar di `routes/api.php`.
2.  **Gunakan Type Hints**: 
    ```php
    public function update(Request $request, string $id) // Scramble tahu $id adalah string
    ```
3.  **Gunakan Docblocks untuk Deskripsi**:
    ```php
    /**
     * Mengambil riwayat perjalanan kendaraan.
     * @queryParam date string Tanggal history (YYYY-MM-DD).
     */
    public function history(Request $request) { ... }
    ```
4.  **Validasi Jelas**: Definisikan validasi di `rules()` agar Scramble tahu mana field yang `required`, `integer`, atau `email`.
5.  **Refresh**: Dokumentasi akan terupdate otomatis saat Anda me-refresh halaman `/docs/api`. Tidak perlu menjalankan perintah artisan apapun.

---

## 3. Tool Pemulihan (Artisan Command)
Jika Anda kehilangan akses Superadmin, telah disediakan command khusus:
```bash
php artisan superadmin:reset {email} {password}
```
Command ini akan mereset password dan memastikan role user tersebut tetap `superadmin`.
