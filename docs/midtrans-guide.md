# Panduan Integrasi Midtrans (Lokal & Production)

Dokumen ini menjelaskan cara menjalankan dan menguji sistem pembayaran SaaS **Natra HRIS** menggunakan Midtrans, baik di lingkungan pengembangan (Lokal) maupun saat sudah online (Production).

---

## 1. Lingkungan Pengembangan (Lokal)

Karena server Midtrans tidak bisa mengirim notifikasi ke `localhost`, kita menggunakan **ngrok** sebagai terowongan (tunneling).

### Langkah-langkah:
1.  **Jalankan Backend**: Pastikan Laravel berjalan di port 8000.
    ```bash
    php artisan serve
    ```
2.  **Jalankan ngrok**:
    ```bash
    npx ngrok http 8000
    ```
3.  **Dapatkan URL ngrok**: Salin URL `Forwarding` yang muncul (contoh: `https://fb1c-2404-xxx.ngrok-free.app`).
4.  **Konfigurasi Midtrans Dashboard**:
    *   Buka [Midtrans Dashboard (Sandbox)](https://dashboard.sandbox.midtrans.com/).
    *   Menu: **Settings > Configuration**.
    *   **Payment Notification URL**: Isi dengan `{URL_NGROK}/api/payment/webhook`.
        *   Contoh: `https://fb1c-2404-xxx.ngrok-free.app/api/payment/webhook`
    *   **Finish Redirect URL**: `{URL_FRONTEND}/admin/subscription` (Opsional).
5.  **Verifikasi Signature**: Sistem sudah dilengkapi verifikasi `signature_key` di `PaymentController`. Ini menjamin hanya notifikasi asli dari Midtrans yang diproses.

---

## 2. Lingkungan Produksi (Hosting/cPanel)

Saat backend sudah di-hosting, Anda tidak perlu lagi menggunakan ngrok.

### Langkah-langkah:
1.  **Update `.env`**: Pastikan `MIDTRANS_IS_PRODUCTION` diatur ke `true` dan isi `SERVER_KEY` serta `CLIENT_KEY` produksi.
2.  **Konfigurasi Dashboard Midtrans (Production)**:
    *   Ganti URL Notifikasi di Dashboard Midtrans (Production) menggunakan domain asli Anda.
    *   Contoh: `https://api.locatrack.id/api/payment/webhook`
3.  **SSL/HTTPS**: Pastikan hosting Anda sudah menggunakan HTTPS, karena Midtrans mewajibkan URL webhook menggunakan protokol aman (HTTPS).

---

## 3. Alur Kerja Webhook (Otomatisasi)

Sistem ini bekerja secara otomatis menggunakan **Webhook Notification**:
1.  **User Membayar**: Melalui Snap UI di Frontend.
2.  **Midtrans Mengirim Notifikasi**: Midtrans mengirim data ke endpoint `/api/payment/webhook`.
3.  **Backend Memvalidasi**:
    *   Mengecek `signature_key` (Keamanan).
    *   Mencari data transaksi di tabel `transactions`.
    *   Mencari data langganan di tabel `subscriptions`.
4.  **Aktivasi Otomatis**: Jika status = `settlement` (berhasil):
    *   `status` subscription berubah menjadi `active`.
    *   `plan_id` diperbarui sesuai paket yang dibeli.
    *   Kuota Karyawan & Kendaraan diperbarui otomatis.
    *   **AI Credits** diisi ulang sesuai jatah paket.

---

## 4. Troubleshooting (Jika Notifikasi Tidak Masuk)

Jika paket tidak otomatis berubah setelah bayar di lokal:
1.  **Cek Log ngrok**: Lihat apakah ada request POST ke `/api/payment/webhook` dengan status `200 OK`.
2.  **Cek Log Laravel**: Lihat `storage/logs/laravel.log` jika ada error saat pemrosesan data.
3.  **Manual Sync**: Gunakan fitur **Sync Status** di Dashboard Superadmin jika webhook gagal masuk (misal: internet mati saat proses bayar).

---

*Dokumentasi ini dibuat untuk proyek Utama Informatika - LocaTrack 2026.*
