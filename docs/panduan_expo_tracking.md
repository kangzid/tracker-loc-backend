# PANDUAN EXPO INFORMATIKA: SISTEM MONITORING GPS REAL-TIME

Panduan ini dibuat untuk membantu kamu (**Zidan**) saat presentasi di expo nanti. Bahasa yang dipakai di sini santai tapi tetap sopan, seperti kalau kamu lagi ngobrol langsung sama dosen.

---

## Bagian 1: Tips Sebelum Mulai (Anti Gugup)

Dosen penguji di expo informatika itu bukan nyari yang paling lancar ngomong, tapi yang paling ngerti sistemnya. Jadi tenang aja, yang penting kamu bisa jelaskan kenapa kamu buat ini, bagaimana cara kerjanya, dan kenapa kamu ambil keputusan teknis tertentu.

1. **Mulai dari masalah, baru solusi**: Ceritain dulu kenapa aplikasi ini perlu dibuat, baru tunjukin sistemnya jalan.
2. **Bicara pelan-pelan**: Kalau gugup, tarik napas dulu. Lebih baik pelan tapi jelas daripada cepat tapi penguji bingung.
3. **Arahkan perhatian penguji ke demo**: Sambil demo, beri tahu mereka lagi lihat apa. Misalnya: *"Ini layar adminnya, dan di sini bisa dilihat posisi karyawan bergerak langsung di peta."*

---

## Bagian 2: Naskah Presentasi (3 - 5 Menit)

*Pakai ini saat dosen atau pengunjung datang ke booth kamu.*

> **[Pembuka]**
> "Selamat pagi/siang Pak/Bu. Perkenalkan saya Zidan Alfian. Di expo ini saya membawa proyek yang judulnya: *Sistem Informasi Monitoring Karyawan Lapangan dan Kendaraan Operasional Berbasis GPS Real-Time*."
>
> **[Masalah]**
> "Latar belakangnya dari masalah yang cukup umum di perusahaan yang punya karyawan lapangan atau armada kendaraan. Selama ini pemantauan masih dilakukan secara manual, entah lewat WhatsApp atau laporan harian. Masalahnya, informasi itu datangnya terlambat, dan data lokasi juga gampang dimanipulasi. Jadi manajemen susah ambil keputusan cepat kalau ada masalah di lapangan."
>
> **[Solusi dan Demo]**
> "Untuk menyelesaikan masalah itu, saya bikin sistem yang terdiri dari tiga bagian:
> 1. **Aplikasi mobile (Capacitor)** — dipakai karyawan untuk absen dan aktifkan pelacakan lokasi.
> 2. **Dashboard web (Svelte + Laravel)** — dipakai admin untuk lihat posisi semua karyawan dan kendaraan di peta secara langsung.
> 3. **Simulasi GPS (Python/Wokwi)** — untuk demo pelacakan kendaraan operasional.
>
> Kalau boleh, saya tunjukkan demonya... *[jalankan simulasi/buka dashboard]*... ini contohnya, koordinat dikirim dari simulator terus langsung muncul di peta."
>
> **[Teknis Singkat]**
> "Untuk pengembangannya saya pakai metode Waterfall karena kebutuhannya sudah jelas dari awal. Backend-nya Laravel dengan RESTful API, database MySQL dengan relasi polimorfik supaya satu tabel lokasi bisa menampung data dari dua entitas berbeda — karyawan dan kendaraan — tanpa harus bikin tabel terpisah."
>
> **[Penutup]**
> "Sistem ini juga sudah saya rancang ke arah SaaS, jadi bisa dipakai oleh banyak perusahaan sekaligus dengan data yang terpisah satu sama lain. Mungkin itu dulu dari saya, kalau ada pertanyaan saya siap."

---

## Bagian 3: Alur Sistem

Kalau dosen minta penjelasan alur datanya, pakai diagram ini:

```mermaid
graph TD
    A[Karyawan Mobile App / Capacitor] -->|POST Koordinat| B(API Laravel)
    C[Simulasi GPS / Python / Wokwi] -->|POST Koordinat| B
    B -->|Cek Token| D{Token Valid?}
    D -- Ya -->|Simpan Log| E[(Tabel locations - MySQL)]
    D -- Tidak -->|Tolak 401| F[Unauthorized]
    E -->|Update Posisi Terakhir| G[(Tabel employees & vehicles)]
    H[Dashboard Web / Svelte] -->|GET Data Terkini| B
    B -->|Response JSON| H
    H -->|Tampilkan Marker| I[Peta OpenStreetMap]
```

### Teknologi yang Dipakai:
- **Backend: Laravel (PHP)** — Dipilih karena struktur routing-nya rapi, sistem middleware untuk keamanan sudah tersedia, dan ORM Eloquent mempermudah pengelolaan relasi database yang cukup kompleks di proyek ini.
- **Frontend Web: Svelte (JavaScript)** — Karena pendekatannya berbasis compiler, tidak ada Virtual DOM, jadi file akhirnya ringan dan peta bisa dirender dengan cepat.
- **Frontend Mobile: Capacitor (HTML/CSS/JS)** — Memungkinkan satu kode yang sama dipakai untuk aplikasi mobile yang bisa mengakses fitur native perangkat seperti GPS.
- **Metode Waterfall** — Dipilih karena kebutuhan sistemnya sudah terdefinisi dari awal, sehingga perancangan database bisa dilakukan secara matang sebelum mulai koding.

---

## Bagian 4: Penjelasan Database

Ini bagian yang paling sering ditanyakan dosen di expo informatika. Pahami baik-baik.

### Kenapa ada perbedaan antara database di laporan dan yang sekarang?

Jelaskan ke dosen seperti ini:

> "Pak/Bu, di laporan Tahap 1 saya rancang sistemnya untuk satu perusahaan saja (*single-tenant*). Tapi saat pengerjaan berlanjut, saya kembangkan ke arah SaaS (*Software as a Service*) supaya platformnya bisa dipakai banyak perusahaan sekaligus dengan data yang terisolasi. Karena itu ada beberapa tabel yang bertambah dan beberapa kolom yang disesuaikan."

Berikut perbandingan tabelnya:

| Tabel di Laporan | Tabel Sekarang | Keterangan Perubahan |
| :--- | :--- | :--- |
| `users` | `users` | Ditambah role `superadmin` untuk mengelola semua tenant, dan kolom `admin_id` untuk relasi kepemilikan antar user. |
| `employees` | `employees` | Ditambah kolom `admin_id` sebagai foreign key. Tujuannya supaya data karyawan Perusahaan A tidak bisa diakses oleh Admin Perusahaan B. |
| `vehicles` | `vehicles` | Sama seperti `employees`, ditambah `admin_id`, plus kolom `tracking_token` untuk autentikasi perangkat GPS eksternal dan `is_active` untuk status kendaraan. |
| `locations` | `locations` | Menggunakan relasi polimorfik (`trackable_id` dan `trackable_type`) supaya satu tabel ini bisa menyimpan riwayat lokasi dari karyawan maupun kendaraan. |
| *(belum ada)* | `attendances` | Tabel baru untuk absensi karyawan yang menyimpan koordinat lokasi saat check-in dan check-out, bukan hanya waktu. |
| *(belum ada)* | `tasks` | Tabel baru untuk penugasan lapangan. Admin bisa buat tugas lengkap dengan koordinat lokasi tujuan, dan karyawan bisa update statusnya. |
| *(belum ada)* | `geofences` | Tabel baru untuk definisikan batas wilayah kerja. Kalau karyawan atau kendaraan keluar dari radius yang ditentukan, sistem bisa kirim notifikasi. |
| *(belum ada)* | `plans` & `subscriptions` | Tabel baru untuk model berlangganan. Dipakai untuk batasi jumlah karyawan dan kendaraan sesuai paket yang dibeli perusahaan. |
| *(belum ada)* | `transactions` & `vouchers` | Tabel baru untuk riwayat pembayaran langganan dan kode diskon. |
| *(belum ada)* | `notifications` & `admin_notification_status` | Tabel baru untuk sistem notifikasi, misalnya pemberitahuan saat aset keluar geofence atau langganan hampir habis. |

---

### Pertanyaan Teknis Database yang Sering Ditanyakan

**A. "Kenapa pakai `DECIMAL(10,8)` untuk latitude, bukan `FLOAT` atau `DOUBLE`?"**

> "Jadi begini Pak/Bu, kalau pakai `FLOAT`, database itu cuma menjamin akurasi sekitar 7 digit total. Karena koordinat GPS itu butuh presisi tinggi, `FLOAT` bisa membulatkan angkanya secara otomatis — misalnya `-7.12345678` bisa jadi `-7.123457`. Selisih kecil itu kalau di peta bisa jadi perbedaan posisi beberapa meter. Kalau pakai `DOUBLE` presisinya lebih baik tapi rentan kesalahan pembulatan biner di beberapa arsitektur database.
>
> Makanya saya pakai `DECIMAL`. Dengan `DECIMAL`, nilainya disimpan eksak seperti yang dikirimkan tanpa pembulatan. Dengan 8 digit di belakang koma, tingkat presisinya bisa sampai sekitar 1,11 milimeter, dan itu penting banget untuk fitur geofencing supaya deteksi batas areanya akurat.
>
> Untuk ukurannya: latitude rentangnya -90 sampai +90, jadi digit sebelum koma maksimal 2, totalnya jadi `DECIMAL(10,8)`. Longitude -180 sampai +180, digit sebelum koma maksimal 3, jadi `DECIMAL(11,8)`."

**B. "Kenapa lokasi karyawan dan kendaraan digabung di satu tabel dengan relasi polimorfik? Kenapa tidak dipisah saja?"**

> "Sebetulnya kalau dipisah jadi dua tabel, `employee_locations` dan `vehicle_locations`, strukturnya akan persis sama — kolom latitude, longitude, speed, recorded_at, semuanya identik. Jadi tidak ada manfaatnya dipisah, malah bikin skema database lebih rumit dari yang perlu. Dengan relasi polimorfik, satu tabel `locations` bisa melayani keduanya lewat kolom `trackable_type` dan `trackable_id`. Selain itu, kalau nanti ada aset lain yang mau dilacak — misalnya drone atau kurir eksternal — tidak perlu bikin tabel baru lagi, cukup sambungkan ke relasi yang sudah ada."

**C. "Kenapa `VARCHAR(50)` untuk kolom nama, kenapa tidak VARCHAR yang lebih panjang saja sekalian?"**

> "Ada dua alasannya Pak/Bu. Pertama, MySQL mengalokasikan memori sementara saat melakukan pengurutan atau pencarian berdasarkan kolom itu. Kalau panjangnya terlalu besar tanpa alasan, memori yang dialokasikan juga besar meskipun isinya pendek. Kedua, membatasi panjang input itu juga salah satu cara sederhana mengurangi risiko eksploitasi seperti injeksi data yang memanfaatkan panjang karakter. Untuk nama dan ID karyawan, 50 karakter sudah lebih dari cukup secara praktis."

**D. "Kenapa ada composite index di tabel `locations`?"**

> "Karena saat kita tarik riwayat lokasi, query-nya selalu filter berdasarkan tiga kolom sekaligus: tipe objek (`trackable_type`), ID objeknya (`trackable_id`), dan rentang waktunya (`recorded_at`). Kalau tidak ada index, MySQL harus scan seluruh tabel dulu. Dengan composite index di ketiga kolom itu, MySQL bisa langsung loncat ke data yang relevan. Ini penting karena tabel `locations` bisa tumbuh sangat besar — setiap 10 detik bisa masuk satu baris data per kendaraan."

**E. "Bagaimana cara memastikan data antar perusahaan tidak bocor satu sama lain?"**

> "Saya implementasikan isolasi data di level database. Hampir semua tabel operasional punya kolom `admin_id` yang menyimpan ID dari admin/perusahaan yang memiliki data itu. Di sisi backend Laravel, setiap kali ada request dari admin, query yang berjalan otomatis ditambahkan filter `WHERE admin_id = id_admin_yang_login`. Jadi secara teknis, admin dari perusahaan A tidak akan pernah bisa lihat data perusahaan B meskipun mereka pakai platform yang sama."

---

## Bagian 5: Tanya Jawab Kritis & Desain Sistem (Q&A)

### "Bagaimana konsep MVC (Model-View-Controller) diterapkan di proyek ini? Kan tidak ada file HTML Blade di Laravel-mu?"

> "Iya Pak/Bu, benar. Proyek ini menggunakan arsitektur **Decoupled (Terpisah)** atau **API-First Architecture**. Jadi Laravel di sini berperan murni sebagai **RESTful API backend**, bukan untuk merender tampilan visual secara langsung.
>
> Penerapan MVC-nya dibagi seperti ini:
> 1. **Model**: Ada di folder `app/Models/` (seperti `Location.php`, `Employee.php`, `Geofence.php`). Model Eloquent ini bertugas mendefinisikan struktur database, relasi antar-tabel, serta logika bisnis internal (misalnya fungsi untuk menghitung jarak koordinat di model `Geofence`).
> 2. **Controller**: Ada di `app/Http/Controllers/Api/` (seperti `GpsTrackingController.php` dan `LocationController.php`). Controller bertugas menerima masukan data dari klien, melakukan validasi input, memanggil Model untuk manipulasi database, dan mengirimkan respon balik.
> 3. **View**: Karena Laravel bertindak sebagai API, maka *View* tidak di-render oleh Laravel. Peran *View* didelegasikan sepenuhnya ke **Dashboard Web (Svelte)** dan **Aplikasi Mobile (Capacitor)** pada sisi klien. Controller di Laravel hanya mengirimkan data dalam format **JSON**, yang kemudian ditangkap oleh Svelte/Capacitor untuk dirender menjadi peta interaktif, grafik statistik, dan tabel."

---

### "Kenapa tidak pakai studi kasus perusahaan nyata? Validasinya di mana?"

> "Iya Pak/Bu, memang saya tidak terpaku pada satu perusahaan tertentu. Alasannya justru karena saya ingin bikin sistemnya lebih general — kalau saya desain khusus untuk satu perusahaan, hasilnya akan sangat spesifik dan tidak bisa langsung dipakai di tempat lain. Dengan model SaaS ini, tantangan teknisnya justru lebih besar, karena saya harus selesaikan masalah isolasi data antar-tenant, manajemen kuota berlangganan, dan autentikasi perangkat yang berbeda-beda.
>
> Untuk validasinya, saya lakukan melalui simulasi terkontrol — field testing pakai aplikasi Capacitor di koordinat nyata, dan emulasi kendaraan pakai script Python serta Wokwi. Hasilnya bisa direproduksi dan diukur, tidak sekadar kualitatif."

---

### "Bagaimana dengan data penelitiannya? Kamu dapat dari mana?"

> "Pak/Bu, karena proyek ini berfokus pada pengembangan sistem (*Software Engineering*), data penelitian yang digunakan dibagi menjadi tiga:
> 1. **Data fungsional hasil pengujian**: Berupa catatan respon API (seperti response code HTTP) dan kecepatan respon database.
> 2. **Data simulasi rute perjalanan**: Saya buat script simulasi pengiriman data GPS terstruktur menggunakan script Python dan simulator Wokwi. Data koordinat yang dikirimkan diambil dari titik-titik koordinat nyata di area Yogyakarta (seperti rute Tugu ke Malioboro).
> 3. **Data pengujian geofencing**: Berupa log deteksi keluar-masuk radius batas wilayah virtual untuk memvalidasi algoritma Haversine yang digunakan.
>
> Jadi validasi datanya dilakukan secara kuantitatif melalui metode pengujian fungsional (*functional testing*) dan simulasi kasus terkontrol, bukan survei responden kualitatif."

---

### "Tracking real-time buat apa? Kalau klien tidak komplain, berarti pekerjaan selesai kan?"

> "Sebenarnya begini Pak/Bu — kalau kita menunggu komplain dari klien dulu baru bertindak, itu artinya kita baru merespons setelah masalah sudah terjadi. Kerusakan reputasinya sudah ada.
>
> Yang saya coba tawarkan di sini adalah pendekatan sebaliknya — admin bisa tahu lebih awal kalau ada sesuatu yang tidak beres. Misalnya karyawan terlambat tiba di lokasi, admin bisa langsung hubungi klien untuk kasih info, atau alihkan karyawan lain yang posisinya lebih dekat. Ini juga soal keselamatan — kalau karyawan kerja di lokasi terpencil dan tiba-tiba tidak bergerak dalam waktu lama, itu sinyal yang perlu direspons cepat, bukan ditunggu sampai ada yang lapor.
>
> Selain itu, data perjalanan dan durasi di lokasi itu bisa jadi dasar evaluasi kinerja yang objektif, bukan sekadar laporan 'sudah selesai' yang tidak bisa diverifikasi."

---

### "Di pasaran sudah ada Lacak.id, GPSWOX, dan sebagainya. Bedanya apa?"

> "Platform yang sudah ada itu umumnya fokus ke satu hal — ada yang khusus fleet management untuk kendaraan, ada yang khusus absensi karyawan. Saya menggabungkan keduanya dalam satu platform yang saling terhubung. Karyawan bisa dilacak lewat aplikasi mobile, kendaraan lewat simulasi GPS, dan semua datanya bisa dilihat di satu dashboard.
>
> Yang membedakan juga adalah konteksnya — saya tidak cuma catat koordinat mentah. Koordinat itu terhubung langsung ke data tugas dan geofence, jadi sistem tahu koordinat ini dikirim untuk tugas apa dan apakah karyawan ada di lokasi yang benar.
>
> Dan dari sisi arsitektur, platform ini dibangun multi-tenant dari awal, lengkap dengan manajemen paket, pembayaran, dan kuota, bukan sekadar aplikasi internal satu perusahaan."

---

### "Boros baterai dan data dong kalau tracking terus?"

> "Betul Pak/Bu, itu memang jadi pertimbangan. Makanya ada dua mode — mode otomatis yang kirim koordinat secara berkala di background, dan mode manual di mana karyawan kirim lokasi sendiri saat perlu. Di mode otomatis, saya pakai library yang memanfaatkan akselerometer perangkat. Kalau HP terdeteksi diam, pengiriman koordinat berhenti dulu, tidak terus-menerus kirim kalau posisinya tidak berubah."

---

### "URL API-nya di-hardcode di aplikasi?"

> "Tidak Pak/Bu, saya pakai konsep remote config. Jadi URL backend tidak ditanam langsung di dalam kode aplikasi. Aplikasinya ambil konfigurasi itu dari server saat pertama kali dibuka. Manfaatnya, kalau misalnya server-nya pindah domain atau berganti hosting, saya tidak perlu rebuild aplikasinya, cukup update nilai di remote config dan semua klien otomatis pakai endpoint yang baru."

---

### "Kenapa pakai OpenStreetMap, bukan Google Maps?"

> "Karena Google Maps mengenakan biaya per request render peta. Untuk sistem yang update posisi setiap beberapa detik, biaya itu bisa cukup signifikan dalam jangka panjang. OpenStreetMap gratis dan tidak ada batasan kuota ketat, jadi lebih cocok untuk tahap pengembangan sekarang."

---

## Checklist Sebelum Expo
- [ ] Cek server backend sudah jalan (localhost atau hosting).
- [ ] Siapkan script Python (`test_gps_jogja.py`) atau buka Wokwi untuk demo kendaraan.
- [ ] Login ke akun demo admin di web, pastikan ada data karyawan dan kendaraan yang sudah terisi.
- [ ] Simpan dokumen ini di HP atau cetak sebagai contekan.

**Semangat Zidan, persiapannya sudah matang. Tinggal santai dan jelaskan apa yang sudah kamu buat.**
