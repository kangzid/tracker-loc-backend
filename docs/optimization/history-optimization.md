# Optimasi Sistem Riwayat Pelacakan (History Optimization)

Dokumen ini menjelaskan strategi efisiensi yang diterapkan pada penyimpanan riwayat pelacakan (history) karyawan dan kendaraan di sistem LocaTrack. Tujuan dari optimasi ini adalah menjaga kestabilan database sekaligus memastikan riwayat pergerakan terekam dengan akurat untuk periode 30 hari terakhir.

## Tiga Pilar Optimasi

Sistem ini menggunakan tiga pilar utama untuk mengelola efisiensi data lokasi.

### 1. Distance-Based Filtering (Penyaringan Berdasarkan Jarak)
Aplikasi mobile secara otomatis mengirimkan pembaruan koordinat GPS setiap 60 detik (interval *heartbeat*). Jika tidak ada penyaringan, karyawan yang diam/tidak berpindah posisi (misalnya duduk di meja kantor) selama 8 jam akan menghasilkan 480 baris data kembar yang membebani database.

**Implementasi:**
- Di `LocationController@store`, sistem akan menghitung jarak (`calculateDistance` menggunakan formula Haversine) antara titik yang dikirimkan dengan titik terakhir yang direkam untuk karyawan/kendaraan tersebut.
- Jika jarak perpindahan **kurang dari 50 meter**, maka sistem **tidak akan membuat baris database baru**.
- Sistem hanya akan **memperbarui waktu (`recorded_at`)** pada rekaman terakhir tersebut.
- Hal ini mengurangi penggunaan database secara drastis hingga 80% pada kondisi di mana target sedang stasioner.

### 2. Auto-Pruning (Pembersihan Otomatis Data > 30 Hari)
Untuk menjaga agar data riwayat tidak bertumbuh tak terbatas, Laravel dijadwalkan secara otomatis untuk membersihkan data yang berumur lebih dari 30 hari.

**Implementasi:**
- Tabel `locations` menggunakan *trait* `MassPruning`.
- Pada model `Location.php`, terdapat metode `prunable` yang mendefinisikan bahwa semua baris data dengan `recorded_at` yang usianya lebih dari 30 hari akan dihapus.
- `Schedule::command('model:prune')->daily()` berjalan setiap hari (dijadwalkan di `routes/console.php`) secara asinkron untuk menjaga tabel tetap ramping.

### 3. Downsampling / Query Optimization (Sisi Frontend)
Meskipun penyaringan jarak sudah mengurangi entri secara signifikan, pencarian data historis untuk rentang tanggal yang panjang (misalnya 1 bulan) masih berpotensi mereturn ribuan titik yang dapat membuat browser memori membengkak (saat menggambar peta Leaflet di Svelte).

**Praktik Terbaik / Implementasi:**
- Data riwayat hanya mereturn kolom-kolom yang diperlukan secara spesifik via klausa `select()`.
- Secara otomatis membatasi jumlah data yang dikembalikan ke frontend menjadi maksimal **20 titik** melalui fungsi downsampling untuk performa peta yang ringan.

## Dampak pada Kode (Perbandingan Sebelum vs Sesudah)

**Sebelumnya:**
- Pada `LocationController`, lokasi karyawan disimpan dengan metode `updateOrCreate` mencocokkan `trackable_type` dan `trackable_id`. Ini menyebabkan sistem secara keliru menimpa data yang sudah ada, sehingga riwayat pergerakan tidak pernah tersimpan. Karyawan hanya memiliki **1** lokasi di database pada satu waktu.

**Sekarang:**
- Sistem memeriksa jarak terlebih dahulu.
- Apabila berbeda atau bergerak, metode `Location::create()` digunakan agar riwayat pergerakan (jalur / path) benar-benar terbentuk dan dapat ditampilkan di peta riwayat.
- Untuk mengimbangi pertumbuhan data karena pemakaian `create()`, mekanisme Auto-Pruning diberlakukan.
