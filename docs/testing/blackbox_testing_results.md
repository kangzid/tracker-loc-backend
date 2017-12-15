# Hasil Pengujian Blackbox Testing (Tahap 1 - Prototipe)

Dokumen ini berisi tabel lengkap pengujian fungsionalitas sistem menggunakan metode *Blackbox Testing* untuk laporan Bab 4. Pengujian difokuskan sepenuhnya pada fitur-fitur **Tahap 1 (Prototipe)**, yaitu:
1. **Autentikasi** (Login Web & Mobile - akun dibuat oleh admin, tanpa registrasi mandiri).
2. **CRUD Data Master** (Kelola data Karyawan & Kendaraan melalui Web Admin).
3. **Pelacakan Lokasi** (Kirim koordinat GPS dari perangkat Mobile/GPS Tracker ke API).
4. **Pemantauan Peta** (Live Tracking & Filter Riwayat Rute di Web Admin).

---

## A. Pengujian Autentikasi (Web & Mobile)

| No | Fitur | Skenario Pengujian | Test Case (Data Input) | Hasil yang Diharapkan | Hasil Pengujian |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | Login Web Admin (Normal) | Melakukan login ke web admin menggunakan akun admin yang terdaftar aktif | **Email:** `admin@tracker.com`<br>**Password:** `password123` | Sistem berhasil memvalidasi kredensial, menghasilkan token akses (Sanctum), dan mengarahkan ke dashboard utama. | Sesuai |
| 2 | Login Web Admin (Gagal - Password Salah) | Melakukan login ke web admin menggunakan password yang salah | **Email:** `admin@tracker.com`<br>**Password:** `salahpassword` | Sistem menolak akses masuk, menampilkan pesan kesalahan `"Invalid credentials"` (HTTP 401). | Sesuai |
| 3 | Login Web Admin (Gagal - Format Email) | Melakukan login ke web admin dengan format email tidak sesuai standar | **Email:** `admin-tracker`<br>**Password:** `password123` | Sistem memicu error validasi dan menampilkan pesan kesalahan bahwa email wajib berformat valid (HTTP 422). | Sesuai |
| 4 | Login Mobile Karyawan (Normal) | Melakukan login ke aplikasi mobile menggunakan akun karyawan yang terdaftar aktif | **Email:** `karyawan1@tracker.com`<br>**Password:** `password123` | Sistem berhasil memvalidasi kredensial, mengembalikan data profil karyawan beserta token akses, dan mengizinkan masuk ke aplikasi. | Sesuai |
| 5 | Login Mobile Karyawan (Gagal - Akun Tidak Aktif) | Melakukan login dengan akun karyawan yang statusnya dinonaktifkan (`is_active = false`) | **Email:** `karyawan_nonaktif@tracker.com`<br>**Password:** `password123` | Sistem menolak akses masuk dan menampilkan pesan kesalahan `"Account is inactive"` (HTTP 403). | Sesuai |

---

## B. Pengujian Kelola Data Master (Web Admin)

| No | Fitur | Skenario Pengujian | Test Case (Data Input) | Hasil yang Diharapkan | Hasil Pengujian |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | Tambah Data Karyawan (Normal) | Menambahkan data karyawan baru melalui Web Admin dengan data lengkap | **Nama:** `Budi Santoso`<br>**Email:** `budi@tracker.com`<br>**Password:** `password123`<br>**Role:** `employee`<br>**Employee ID:** `EMP001`<br>**Phone:** `08123456789`<br>**Department:** `Logistik`<br>**Position:** `Driver` | Sistem berhasil menyimpan data user dan karyawan baru ke database, mengembalikan response sukses, dan memperbarui daftar karyawan. | Sesuai |
| 2 | Tambah Data Karyawan (Gagal - Email Duplikat) | Menambahkan karyawan baru dengan email yang sudah digunakan oleh akun lain | **Nama:** `Budi Baru`<br>**Email:** `budi@tracker.com`<br>**Password:** `password123`<br>**Role:** `employee`<br>**Employee ID:** `EMP002` | Sistem menolak penyimpanan dan menampilkan pesan error validasi bahwa email sudah terdaftar (`"The email has already been taken"`, HTTP 422). | Sesuai |
| 3 | Lihat Detail Karyawan (Normal) | Mengakses data spesifik salah satu karyawan yang terdaftar | **ID Karyawan:** `1` (Budi Santoso) | Sistem berhasil mengambil dan menampilkan data profil lengkap karyawan beserta relasi akunnya (HTTP 200). | Sesuai |
| 4 | Edit Data Karyawan (Normal) | Mengubah informasi profil karyawan | **ID Karyawan:** `1`<br>**Data Edit:** `phone => 08999999999`, `department => Distribusi` | Sistem berhasil memperbarui informasi karyawan di database dan mengembalikan data terbaru. | Sesuai |
| 5 | Hapus Data Karyawan (Normal) | Menghapus akun karyawan dari sistem | **ID Karyawan:** `1` | Sistem menghapus data karyawan beserta data akun user-nya secara permanen dari database. | Sesuai |
| 6 | Hapus Data Karyawan (Gagal - ID Tidak Ada) | Mencoba menghapus karyawan dengan ID yang tidak terdaftar | **ID Karyawan:** `999` | Sistem menampilkan pesan kesalahan `"Employee not found"` (HTTP 404). | Sesuai |
| 7 | Tambah Data Kendaraan (Normal) | Mendaftarkan kendaraan operasional baru ke sistem | **Plat Nomor:** `B 1234 CD`<br>**Tipe:** `Truck Box`<br>**Brand:** `Isuzu`<br>**Model:** `Elf NLR`<br>**Tahun:** `2021` | Sistem berhasil menyimpan data kendaraan baru, secara otomatis men-generate `tracking_token`, dan menampilkan response sukses (HTTP 201). | Sesuai |
| 8 | Tambah Data Kendaraan (Gagal - Nomor Plat Duplikat) | Mendaftarkan kendaraan dengan plat nomor yang sudah terdaftar di sistem | **Plat Nomor:** `B 1234 CD`<br>**Tipe:** `Pick Up`<br>**Brand:** `Suzuki`<br>**Model:** `Carry`<br>**Tahun:** `2022` | Sistem menolak pendaftaran dan memicu pesan kesalahan validasi bahwa nomor kendaraan harus unik (`"The vehicle number has already been taken"`, HTTP 422). | Sesuai |
| 9 | Edit Data Kendaraan (Normal) | Memperbarui detail data kendaraan | **ID Kendaraan:** `1`<br>**Data Edit:** `vehicle_type => Truck CDE`, `model => Elf Box` | Sistem berhasil memperbarui data kendaraan dan menampilkan data terbaru di database. | Sesuai |
| 10 | Hapus Data Kendaraan (Normal) | Menghapus data kendaraan dari sistem | **ID Kendaraan:** `1` | Sistem menghapus data kendaraan beserta seluruh riwayat lokasinya (cascade delete) dan mengembalikan pesan sukses. | Sesuai |

---

## C. Pengujian Pelacakan Lokasi (Kirim Koordinat GPS)

| No | Fitur | Skenario Pengujian | Test Case (Data Input) | Hasil yang Diharapkan | Hasil Pengujian |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | Kirim Lokasi Karyawan Mobile (Normal) | Mengirimkan koordinat lokasi terkini dari aplikasi mobile karyawan | **Headers:** `Bearer {token_karyawan}`<br>**Body:** `latitude: -7.7828`, `longitude: 110.3671`, `trackable_type: employee`, `trackable_id: 1`, `speed: 25.5`, `accuracy: 10.0` | Sistem berhasil memvalidasi data koordinat, mengupdate lokasi terkini karyawan, menyimpan riwayat, dan memicu event WebSocket (`LocationUpdated`) untuk tracking real-time. | Sesuai |
| 2 | Kirim Lokasi Karyawan Mobile (Gagal - Manipulasi ID) | Mengirimkan lokasi dengan ID karyawan lain yang bukan milik pengirim token | **Headers:** `Bearer {token_karyawan_1}`<br>**Body:** `latitude: -7.7828`, `longitude: 110.3671`, `trackable_type: employee`, `trackable_id: 2` | Sistem menolak update lokasi dan merespons dengan pesan `"Unauthorized Location Update"` (HTTP 403). | Sesuai |
| 3 | Kirim Lokasi GPS Kendaraan (Normal) | Mengirim koordinat lokasi GPS dari perangkat tracking kendaraan menggunakan API publik | **Headers:** `X-Tracking-Token: {tracking_token_valid}`<br>**Body:** `latitude: -7.7956`, `longitude: 110.3695`, `speed: 40.0`, `accuracy: 5.0` | Sistem memvalidasi token pelacakan kendaraan, memperbarui lokasi terkini kendaraan, menyimpan ke tabel histori, dan membroadcast update real-time ke web admin. | Sesuai |
| 4 | Kirim Lokasi GPS Kendaraan (Gagal - Token Salah) | Mengirim koordinat lokasi kendaraan dengan token yang salah/palsu | **Headers:** `X-Tracking-Token: token_salah_123`<br>**Body:** `latitude: -7.7956`, `longitude: 110.3695` | Sistem menolak pengiriman data dan mengembalikan response `"Invalid tracking token"` (HTTP 401). | Sesuai |
| 5 | Kirim Lokasi GPS Kendaraan (Gagal - Koordinat Salah) | Mengirim lokasi kendaraan dengan nilai latitude melebihi batas geografis | **Headers:** `X-Tracking-Token: {tracking_token_valid}`<br>**Body:** `latitude: 120.0`, `longitude: 110.3695` | Sistem menolak input lokasi dan mengembalikan pesan validasi error (`"latitude must be between -90 and 90"`, HTTP 422). | Sesuai |

---

## D. Pengujian Pemantauan Peta (Live Tracking & Filter Riwayat)

| No | Fitur | Skenario Pengujian | Test Case (Data Input) | Hasil yang Diharapkan | Hasil Pengujian |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | Live Tracking Dashboard (Normal) | Mengakses dashboard peta pemantauan langsung seluruh karyawan dan kendaraan yang aktif | Melakukan request `GET /api/locations/live` dengan login admin valid | Sistem sukses mengambil koordinat terkini seluruh karyawan dan kendaraan aktif di bawah admin tersebut untuk digambarkan langsung pada peta. | Sesuai |
| 2 | Live Tracking Dashboard (Gagal - Tanpa Login) | Mencoba memanggil endpoint live tracking tanpa menyertakan token autentikasi | Request `GET /api/locations/live` tanpa header Authorization | Sistem menolak permintaan dan mengembalikan error `"Unauthenticated"` (HTTP 401). | Sesuai |
| 3 | Filter Riwayat Rute Karyawan (Normal) | Memfilter rute perjalanan karyawan tertentu berdasarkan tanggal | **ID Karyawan:** `1`<br>**Query Params:** `date: 2026-05-18` | Sistem memuat dan menampilkan runtutan titik koordinat perjalanan yang dilalui karyawan tersebut pada tanggal yang dipilih. | Sesuai |
| 4 | Filter Riwayat Rute Kendaraan (Normal) | Memfilter rute perjalanan kendaraan tertentu berdasarkan rentang tanggal | **ID Kendaraan:** `2`<br>**Query Params:** `start_date: 2026-05-15`, `end_date: 2026-05-18` | Sistem sukses memuat seluruh titik histori lokasi kendaraan dalam rentang waktu tersebut untuk digambar sebagai jalur rute di peta. | Sesuai |
| 5 | Filter Riwayat Rute (Gagal - Format Tanggal) | Melakukan filter dengan format tanggal yang salah | **ID Karyawan:** `1`<br>**Query Params:** `date: bukan-tanggal` | Sistem menolak filter rute, memicu kesalahan validasi data, dan meminta input tanggal dalam format yang benar (HTTP 422). | Sesuai |
