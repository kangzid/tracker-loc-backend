# Roadmap Update Frontend SvelteKit (SaaS Tahap 1)

Berdasarkan `project-structure.txt` dari repository Frontend Anda, berikut adalah langkah terperinci untuk mengintegrasikan fitur SaaS tahap pertama ke dalam arsitektur SvelteKit Anda hari esok. Panduan ini eksklusif untuk Svelte Admin Panel (Front-end Web), karena integrasi Employee App (Flutter) akan dilakukan minggu depan.

---

## 1. Halaman Public: Self-Service Provisioning (Pendaftaran Perusahaan)
Klien baru akan mendaftar akun trial mandiri melalui SvelteKit sebelum dialihkan ke halaman login.

**Langkah Implementasi:**
- **Service:** Tambahkan fungsi registrasi di `src/lib/services/auth.service.ts` (mengarah ke `POST /api/provision`).
- **Route Baru:** Buat folder `src/routes/register/` (atau `provision/`).
- **File:** Buat `+page.svelte` dan `+page.server.ts` berisi form (Company Name, Email, Contact Phone).
- **Proses Output:** Sesudah sukses 201, tangkap teks raw dari `credentials.password` di response JSON. Tampilkan melalui modal/dialog yang mengingatkan user bahwa *password* plaintext tersebut hanya muncul satu kali dan menyuruh mereka segera menyimpannya.

---

## 2. Area Baru: 👑 Superadmin Panel
Karena struktur SvelteKit Anda saat ini hanya memiliki `admin/` dan `employee/`, Anda perlu menambahkan lingkup baru khusus untuk role *Superadmin*.

**Langkah Implementasi:**
- **Layout & Routing:** Buat branch route `src/routes/superadmin/` lengkap dengan `+layout.svelte` dan `+layout.server.ts`. Pastikan logic di server me-redirect user jika rolenya bukan `superadmin`.
- **Pages Baru:**
  1. `src/routes/superadmin/dashboard/` -> Menampilkan summary statistik (`GET /superadmin/dashboard`).
  2. `src/routes/superadmin/admins/` -> Tabel manajemen Admin tenant (`GET` & `POST /superadmin/admins`).
     - *Fitur Spesial:* Di tabel ini, buat aksi dropdown (Action Menu) berupa: Toggle Status (`PUT /toggle`), Reset Password (`PUT /reset-password`), dan Delete (`DELETE`).
  3. `src/routes/superadmin/subscriptions/` -> Tabel manajemen paket dan kuota langganan (`GET`, `PUT`, `POST /extend`).
- **Service:** Buat file service baru di `src/lib/services/superadmin.service.ts` untuk melayani semua fetch HTTP wilayah superadmin.
- **Type:** Tambahkan interface di `src/lib/types/` (misal `subscription.ts` dan `superadmin.ts`) mengacu ke format response kita.

---

## 3. Area Admin Panel (Penyesuaian Middleware Quota)
Sistem backend kini memberlakukan *limit* pada fungsi pembuatan karyawan dan kendaraan sesuai kuota SaaS.

**Langkah Implementasi:**
- **Status Langganan UI:** Di komponen `src/lib/components/ui/` (mungkin `app-sidebar` atau header `src/routes/admin/+layout.svelte`), tambahkan fetch ke `GET /api/subscription/status` untuk menampilkan tulisan kecil berupa sisa kuota Employee/Vehicle dan indikator kedaluwarsa.
- **Error Handling Employee Form (`src/lib/components/features/employees/employee-form.svelte`):**
  - Tangkap exception error berstatus `403`. 
  - Jika `error.response.data.error_code === 'EMPLOYEE_QUOTA_EXCEEDED'`, gunakan `notification.store.ts` untuk memberikan Toast/Alert merah berisi "Gagal: Kuota Karyawan Penuh. Silahkan Upgrade Paket!".
- **Error Handling Vehicle Form (`src/lib/components/features/vehicles/vehicle-form.svelte`):**
  - Implementasikan logic penanganan error HTTP `403` yang sama seperti di atas, menggunakan pesan `VEHICLE_QUOTA_EXCEEDED`.

---

## 4. Admin Panel: Update Password Karyawan
Anda kini bisa mengganti password karyawan tanpa OTP lewat form update.

**Langkah Implementasi:**
- Buka `src/lib/components/features/employees/employee-form.svelte`.
- Tambahkan satu form input opsional: *Ubah Password Karyawan*.
- Pada `src/lib/services/employee.service.ts`, di metode `update()`, sematkan properti `"password": "..."` jika field tadi diisi pada form submit yang dikirim ke `PUT /api/employees/{id}`.

---

## 5. Fitur Profile: Change Password (Semua Role)
Semua pengguna Svelte yang memiliki login di web harus memiliki cara mengubah password.

**Langkah Implementasi:**
- Masuk ke `src/routes/admin/settings/+page.svelte` (ini nampaknya halaman setting untuk Admin biasa).
- Tambahkan form "Change Password".
- Hit up fungsi baru di `src/lib/services/auth.service.ts` menuju endpoint `PUT /api/change-password` dengan mengirim parameter `current_password`, `password`, dan `password_confirmation`.
