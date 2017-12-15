# 🛰️ Panduan Simulator GPS & Metode Optimasi Riwayat Lokasi

Panduan ini menjelaskan metode optimasi penyimpanan koordinat lokasi yang digunakan pada backend LocaTrack serta cara menjalankan skrip simulator GPS (`test_gps_jogja.py`) untuk menguji fungsionalitas tersebut secara lokal.

---

## 🛠️ Metode Optimasi yang Digunakan di Backend

Untuk menjaga agar database tetap ringan, cepat, dan tidak membengkak karena data lokasi real-time yang masuk secara terus-menerus, backend menerapkan tiga tingkat optimasi:

### 1. Distance-Based Filtering (Penyaringan Berbasis Jarak)
Aplikasi mobile mengirimkan pembaruan koordinat GPS secara berkala. Jika pengguna diam, data duplikat akan menumpuk.
* **Cara Kerja**: Sebelum menyimpan data baru, backend menghitung jarak antara titik baru dengan titik terakhir menggunakan **Formula Haversine**.
* **Konfigurasi Threshold**: **50 Meter**.
  * **Jika gerakan < 50 meter**: Backend **tidak membuat baris database baru**. Ia hanya memperbarui waktu (`recorded_at`), kecepatan (`speed`), dan akurasi (`accuracy`) pada baris terakhir yang sudah ada.
  * **Jika gerakan $\ge$ 50 meter**: Backend akan menyimpan baris koordinat baru untuk membentuk rute pergerakan.
* **Manfaat**: Mengurangi penulisan database (I/O) hingga 80% saat pengguna stasioner (diam/dalam ruangan).

### 2. Downsampling Adaptif (Penyederhanaan Rute)
Saat memuat riwayat perjalanan di peta frontend (misalnya Leaflet di Svelte), merender ratusan hingga ribuan titik sekaligus dapat menyebabkan browser melambat (*lag*).
* **Cara Kerja**: Backend menyederhanakan jumlah rute menggunakan algoritma downsampling dinamis sebelum data dikirim ke frontend.
* **Batas Maksimal**: **20 Titik**.
* **Mekanisme Dinamis**: 
  * Jika total titik riwayat di database kurang dari 20, semua titik dikembalikan apa adanya.
  * Jika total titik lebih dari 20, sistem menghitung langkah sampling (`step = ceil(total_titik / 20)`). Sistem akan mengambil titik pertama (terbaru), titik terakhir (terawal), dan titik-titik diantaranya dengan lompatan sebesar `step`.
  * **Catatan**: Jumlah titik hasil downsampling akan bergerak dinamis antara **11 hingga 20 titik** tergantung kelipatan jumlah data asli. Ini normal dan jdirancang agar rute peta tetap mulus tapi sangat ringan dimuat.

### 3. Auto-Pruning (Pembersihan Otomatis 30 Hari)
* **Cara Kerja**: Sistem dipasangi scheduler otomatis Laravel `Schedule::command('model:prune')->daily()` (diatur pada `routes/console.php`).
* **Kebijakan**: Semua data koordinat lokasi yang usianya melebihi **30 hari (1 bulan)** akan otomatis dihapus secara permanen setiap harinya.
* **Manfaat**: Database tetap ramping dan ukuran tabel tidak membengkak tanpa batas.

---

## 🚀 Cara Menjalankan Simulator GPS (`test_gps_jogja.py`)

Skrip `test_gps_jogja.py` menyimulasikan pergerakan koordinat GPS di area Yogyakarta (berangkat dari kawasan Tugu Jogja mengarah ke selatan menuju Malioboro).

### 1. Prasyarat (Prerequisites)
Pastikan Anda memiliki Python terinstal di komputer Anda dan instal pustaka `requests`:
```bash
pip install requests
```

### 2. Langkah-Langkah Menjalankan
Buka terminal/PowerShell di direktori backend Anda, lalu jalankan perintah:
```bash
python test_gps_jogja.py
```

Setelah berjalan, simulator akan menanyakan beberapa input interaktif:

#### **A. Jika Menguji Karyawan (Employee - Mobile App)**
1. Pilih Target: Ketik `1` lalu tekan Enter.
2. Masukkan **Bearer Token** Karyawan.
   > 💡 *Bearer token bisa didapatkan dari response API Login Karyawan atau disalin dari tabel database `personal_access_tokens`.*
3. Masukkan **Employee ID** (Angka, contoh: `1`).
4. Pilih Mode Simulasi:
   * Ketik `1` (Diam/Stasioner) untuk menguji goyangan GPS kecil (< 50 meter) - *untuk melihat apakah data hanya meng-update baris yang sama*.
   * Ketik `2` (Bergerak) untuk menyimulasikan perjalanan dari Tugu Jogja ke Malioboro - *untuk mengisi titik-titik baru*.

#### **B. Jika Menguji Kendaraan (Vehicle - GPS Device)**
1. Pilih Target: Ketik `2` lalu tekan Enter.
2. Masukkan **Tracking Token** Kendaraan.
   > 💡 *Tracking token dapat dicari di tabel database `vehicles` pada kolom `tracking_token`.*
3. Pilih Mode Simulasi:
   * Ketik `1` (Diam/Stasioner).
   * Ketik `2` (Bergerak).

*Untuk menghentikan jalannya simulator, tekan tombol kombinasi **Ctrl + C** di terminal.*

---

## 🔍 Cara Verifikasi Hasil Pengujian

1. Jalankan simulator dengan **Mode 2 (Bergerak)** selama beberapa waktu (misalnya biarkan berjalan hingga mengirim 30-50 koordinat sukses).
2. Jalankan aplikasi frontend Anda dan buka halaman admin:
   `http://localhost:5173/admin/history`
3. Cari riwayat berdasarkan kategori target (**Vehicle** / **Employee**) yang Anda simulasikan dengan rentang tanggal **Today (Hari Ini)**.
4. Anda akan melihat ringkasan riwayat (*Journey Overview*):
   * **Total Data Points** yang ditampilkan di peta maksimal hanya akan berkisar di **11 hingga 20 titik** saja meskipun database Anda mencatat lebih dari itu.
   * Rute perjalanan di peta akan terhubung dengan mulus dan cepat tanpa membebani memori browser.
