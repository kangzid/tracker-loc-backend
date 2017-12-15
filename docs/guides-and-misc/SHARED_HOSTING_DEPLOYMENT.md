# Panduan Deployment Laravel ke Shared Hosting (Tanpa Akses SSH)

Dokumen ini berisi panduan langkah demi langkah untuk melakukan *deployment* aplikasi backend Tracker Loc ke layanan *shared hosting* murni melalui **File Manager cPanel** dan **Upload ZIP**.

---

## 1. Persiapan File ZIP (Di Komputer Lokal)

1. Buka terminal di folder backend (`tracker-loc-backend`).
2. Jalankan perintah ini agar ukuran file tidak bengkak:
   ```bash
   composer install --optimize-autoloader --no-dev
   ```
3. Hapus *cache* konfigurasi:
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```
4. **Buat file `.zip`**, pastikan Anda memasukkan semua file dan folder proyek **KECUALI**:
   - `node_modules/` 
   - `.git/` 
   - `tests/`
   - File `.env` (Kita akan bikin manual di hosting)
   - Folder `storage/logs/` (Kosongkan isinya saja)
   - Folder `storage/framework/cache/data/` (Kosongkan isinya saja)

---

## 2. Upload ke Folder `locatrack` di Hosting Anda

Karena Anda sudah membuat folder `locatrack`, kita akan mengunggah file ke sana.

1. Buka cPanel dan masuk ke **File Manager**.
2. Masuk ke direktori `/home/zalfyanmy/locatrack`.
3. **Upload** file `.zip` yang sudah Anda persiapkan.
4. **Extract** file `.zip` tersebut di dalam folder `/home/zalfyanmy/locatrack`.
5. *(Penting)* Jika Anda menggunakan domain atau subdomain tertentu, pastikan *Document Root* dari domain/subdomain tersebut diatur menunjuk ke folder `/home/zalfyanmy/locatrack/public` (bukan hanya `locatrack`).

---

## 3. Import Database SQL

1. Buka **MySQL Databases** di cPanel dan buat Database Baru (misal: `zalfyanmy_locatrack`) beserta User & Password-nya.
2. Tambahkan User ke Database tersebut dengan centang hak akses *All Privileges*.
3. Buka **phpMyAdmin** dari cPanel.
4. Pilih database `zalfyanmy_locatrack`.
5. Klik tab **Import**, pilih file `.sql` Anda, dan klik **Go**.

---

## 4. Konfigurasi File `.env` Lengkap

Di dalam File Manager (`/home/zalfyanmy/locatrack`), buat file baru dengan nama `.env` lalu *copy-paste* seluruh teks di bawah ini ke dalamnya. 

*Catatan: Konfigurasi di bawah ini sudah saya gabungkan dari `.env` hosting lama Anda, dengan `.env` lokal Anda yang terbaru yang memuat pengaturan **Midtrans** (untuk Gateway Pembayaran) dan **FCM**.*

```env
APP_NAME=Laravel
APP_ENV=production
APP_KEY=base64:lt8D/bWDMAQFJiKImhG4Q9zf+7GOcs4FHVaFtS5sxXk=
APP_DEBUG=false
APP_URL=https://locatrack.zalfyan.my.id

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zalfyanmy_locatrack
DB_USERNAME=zalfyanmy_locatrack-admin
DB_PASSWORD=@Qwerty098

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

# Koneksi Web Sockets pakai Pusher (Reverb ditiadakan untuk Shared Hosting)
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=2137988
PUSHER_APP_KEY=737575397aad80d7c83b
PUSHER_APP_SECRET=1f4caad4350f5f3b24ae
PUSHER_APP_CLUSTER=ap1
PUSHER_HOST=api-ap1.pusher.com
PUSHER_PORT=443
PUSHER_SCHEME=https

FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"

# Midtrans Payment Gateway
MIDTRANS_MERCHANT_ID=
MIDTRANS_CLIENT_KEY=
MIDTRANS_SERVER_KEY=
# Ubah ke true jika sudah produksi live
MIDTRANS_IS_PRODUCTION=false

# Konfigurasi Tambahan Baru untuk Firebase Cloud Messaging
FCM_SERVER_KEY=masukkan_key_fcm_anda_di_sini
```

---

## 5. Membuat Storage Link (Tanpa Terminal SSH)

Karena aplikasi Laravel Anda butuh folder *storage* untuk menyimpan file gambar/upload, lakukan cara ini:

1. Buka file `routes/web.php` menggunakan editor di File Manager cPanel.
2. Tambahkan *script* ini di bagian paling bawah:
   ```php
   Route::get('/symlink', function () {
       $targetFolder = storage_path('app/public');
       $linkFolder = $_SERVER['DOCUMENT_ROOT'] . '/storage';
       symlink($targetFolder, $linkFolder);
       return 'Symlink process completed';
   });
   ```
3. Buka browser Anda dan kunjungi `https://locatrack.zalfyan.my.id/symlink`.
4. Jika halaman berkata `"Symlink process completed"`, folder storage berhasil dikaitkan.
5. **Segera hapus kembali *script* tadi dari `web.php`** demi keamanan.

---

## 6. Pengecekan Akhir
- Coba akses URL website Anda untuk memastikan data API keluar dengan benar.
- Cek tabel-tabel di database (via phpMyAdmin) apakah terhubung.
- Cek URL gambar atau file storage jika ada.

---

## 7. Troubleshooting (Penyelesaian Masalah)

### Error Browser: `DNS_PROBE_FINISHED_NXDOMAIN`
Jika setelah semua proses Anda mencoba membuka website dan mendapati pesan *error* "Situs ini tidak dapat dijangkau" atau `NXDOMAIN`, hal ini berarti DNS dari domain/subdomain Anda belum terbaca atau *nyangkut* di cPanel.

**Cara Mengatasinya (Reset DNS Zone):**
1. Kembali ke halaman utama **cPanel**.
2. Cari dan buka menu **Zone Editor**.
3. Pada baris nama domain utama Anda (`zalfyan.my.id`), klik tombol **Action** (atau **Manage**).
4. Klik opsi **Reset DNS Zone** dan konfirmasi tindakan tersebut.
5. Tunggu sekitar 1-5 menit.
6. Coba buka kembali website Anda (disarankan menggunakan mode *Incognito* / *Private Window* atau *clear cache* terlebih dahulu).
