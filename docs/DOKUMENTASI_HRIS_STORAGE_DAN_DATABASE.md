# 📖 DOKUMENTASI ARSITEKTUR PENYIMPANAN BERKAS TERENKRIPSI AES-256 & AUDIT DATABASE HRIS

**Aplikasi:** Locatrack HRIS & ERP Tracker  
**Versi Sistem:** 2.0.0 (Unified Enterprise HRIS)  
**Tanggal Rilis Dokumentasi:** 20 Agustus 2026  
**Lokasi Berkas:** `tracker-loc-backend/docs/DOKUMENTASI_HRIS_STORAGE_DAN_DATABASE.md`  

---

## 📑 DAFTAR ISI
1. [Ringkasan Eksekutif & Hasil Audit Database](#1-ringkasan-eksekutif--hasil-audit-database)
2. [Arsitektur Keamanan: Private Encrypted Storage (AES-256-CBC)](#2-arsitektur-keamanan-private-encrypted-storage-aes-256-cbc)
3. [Layanan Backend: EncryptedStorageService](#3-layanan-backend-encryptedstorageservice)
4. [Struktur Direktori & Standarisasi Multi-Tenant Storage](#4-struktur-direktori--standarisasi-multi-tenant-storage)
5. [Daftar Endpoint Streaming Terotentikasi](#5-daftar-endpoint-streaming-terotentikasi)
6. [Integrasi Frontend SvelteKit (secureMedia.ts)](#6-integrasi-frontend-sveltekit-securemediats)
7. [Daftar Modul & Kolom Database Terkait](#7-daftar-modul--kolom-database-terkait)
8. [Log Perbaikan Masalah & Bug Teknis](#8-log-perbaikan-masalah--bug-teknis)
9. [Panduan Pemeliharaan & Pengembangan Lanjutan](#9-panduan-pemeliharaan--pengembangan-lanjutan)

---

## 1. Ringkasan Eksekutif & Hasil Audit Database

Sebelumnya, sejumlah tabel database menyimpan berkas dokumen, sertifikat, kwitansi, dan foto profil dalam bentuk **string Base64 panjang (LongText)** langsung di dalam kolom database MySQL.

### ⚠️ Masalah pada Metode Base64 di Database:
- **Ukuran Database Membengkak:** Ukuran database membengkak hingga ~8.8 MB hanya untuk beberapa baris data (overhead ukuran bertambah ~33% dibanding biner asli).
- **Performa Kueri Lambat:** Kueri `SELECT *` memuat megabyte teks base64 ke memori RAM server pada setiap pagination.
- **Risiko Keamanan:** Berkas sensitif (KTP, SIM, SKCK, Kontrak Kerja) berada dalam bentuk teks tak terenkripsi di dump database.

### 🛡️ Solusi yang Diterapkan:
1. **Audit Total 62 Tabel Database:** Mengidentifikasi 12 tabel yang menyimpan berkas base64.
2. **Migrasi Data Eksisting:** Seluruh string base64 dikonversi menjadi berkas biner fisik, dienkripsi dengan cipher **AES-256-CBC**, lalu disimpan ke private disk.
3. **Pembersihan Database (*Drop Columns*):** Menghapus seluruh 12 kolom `*_base64` dan menggantikannya dengan kolom `*_path` (VARCHAR 255) yang hanya menyimpan path referensi berkas.
4. **Optimasi InnoDB:** Menjalankan `OPTIMIZE TABLE` pada 12 tabel untuk mereklamasi ruang penyimpanan.

---

## 2. Arsitektur Keamanan: Private Encrypted Storage (AES-256-CBC)

Sistem menggunakan standar keamanan perbankan/enterprise untuk penyimpanan dokumen:

```
[User / Admin Browser] 
       │ (1. Upload File / Multipart FormData)
       ▼
[Laravel Controller & API Gateway]
       │ (2. Validasi & Kirim ke EncryptedStorageService)
       ▼
[EncryptedStorageService] ── (3. AES-256-CBC Encryption via Laravel Crypt)
       │
       ├─► [Database MySQL] : Hanya menyimpan path string ("tenants/2/contracts/xxx.enc")
       │
       └─► [Private Disk Storage] : Menyimpan payload biner terenkripsi (.enc)
```

### Keunggulan:
- **Zero Public Access:** Berkas TIDAK disimpan di `storage/app/public` dan TIDAK memiliki symlink publik. Pihak luar tidak dapat mengakses URL berkas secara langsung.
- **Enkripsi Kuat:** Berkas fisik di server memiliki ekstensi `.enc`. Jika server storage diretas atau file dicuri, berkas tetap tidak dapat dibuka tanpa kunci rahasia aplikasi (`APP_KEY`).
- **Isolasi Multi-Tenant:** Setiap perusahaan/tenant memiliki folder terisolasi (`tenants/{tenant_id}/`).

---

## 3. Layanan Backend: `EncryptedStorageService`

File: `app/Services/EncryptedStorageService.php`

Layanan terpusat ini menyediakan tiga metode inti:

### 1. `storeEncrypted($file, $tenantId, $folder, $prefix, $customFilename)`
Menerima `UploadedFile` (multipart) atau string biner/base64, mengenkripsinya dengan `Crypt::encrypt()`, dan menulis berkas `.enc` ke disk `'private'`.

### 2. `getDecrypted($path)`
Membaca berkas fisik `.enc`, mendekripsinya dengan `Crypt::decrypt()`, mendeteksi format/MIME type asli (PDF, PNG, JPG, DOCX, dll.), dan mengembalikan konten biner asli.

### 3. `streamResponse($path, $downloadName, $isDownload)`
Menghasilkan `Illuminate\Http\Response` biner terotentikasi lengkap dengan header:
- `Content-Type`: MIME type berkas (misal `application/pdf`, `image/png`).
- `Content-Disposition`: `inline` (untuk pratinjau browser) atau `attachment` (untuk pengunduhan).
- `Cache-Control`: `private, no-cache, no-store, must-revalidate` (mencegah cache tidak sah).

---

## 4. Struktur Direktori & Standarisasi Multi-Tenant Storage

Seluruh berkas privat tersimpan pada direktori tunggal terstandarisasi:

```
storage/
└── app/
    └── private/
        └── tenants/
            └── {tenant_id}/
                ├── avatars/     (Foto profil karyawan & user)
                ├── claims/      (Struk & kwitansi reimbursement)
                ├── contracts/   (Dokumen PDF kontrak kerja PKWT/PKWTT)
                ├── documents/   (Brankas dokumen digital: Ijazah, KTP, KK, SKCK, dll.)
                ├── news/        (Banner berita & pengumuman internal)
                ├── requests/    (Surat keterangan dokter & lampiran cuti/izin)
                ├── training/    (Sertifikat kompetensi & pelatihan)
                └── violations/  (Surat Peringatan & berkas bukti pelanggaran)
```

> **Catatan Konfigurasi:**  
> Konfigurasi disk `'private'` terdaftar pada `config/filesystems.php`:
> ```php
> 'private' => [
>     'driver' => 'local',
>     'root' => storage_path('app/private'),
>     'throw' => false,
>     'report' => false,
> ],
> ```

---

## 5. Daftar Endpoint Streaming Terotentikasi

Seluruh endpoint di bawah ini memerlukan otentikasi **Bearer Token (Laravel Sanctum)** dan secara otomatis memeriksa hak akses tenant:

| Fitur | Method | Endpoint | Fungsi |
|---|---|---|---|
| **Foto Karyawan** | `GET` | `/api/employees/{id}/photo` | Stream foto profil karyawan |
| **Avatar User** | `GET` | `/api/users/{id}/photo` | Stream foto avatar akun pengguna |
| **PDF Kontrak** | `GET` | `/api/hris/contracts/{id}/preview` | Pratinjau PDF kontrak kerja |
| **PDF Kontrak** | `GET` | `/api/hris/contracts/{id}/download` | Unduh PDF kontrak kerja |
| **Brankas Dokumen** | `GET` | `/api/hris/documents/{id}/preview` | Pratinjau dokumen digital / ijazah |
| **Brankas Dokumen** | `GET` | `/api/hris/documents/{id}/download` | Unduh dokumen digital asli |
| **Kwitansi Klaim** | `GET` | `/api/hris/claims/{id}/preview-receipt` | Pratinjau nota klaim biaya |
| **Kwitansi Klaim** | `GET` | `/api/hris/claims/{id}/download-receipt` | Unduh nota klaim biaya |
| **Dokumen Legalitas** | `GET` | `/api/hris/compliance/{id}/preview` | Pratinjau SIM / sertifikasi legal |
| **Dokumen Legalitas** | `GET` | `/api/hris/compliance/{id}/download` | Unduh dokumen legalitas |
| **Bukti Pelanggaran** | `GET` | `/api/hris/violations/{id}/preview-evidence` | Pratinjau bukti Surat Peringatan (SP) |
| **Bukti Pelanggaran** | `GET` | `/api/hris/violations/{id}/download-evidence` | Unduh bukti Surat Peringatan (SP) |
| **Sertifikat Training** | `GET` | `/api/hris/training/participants/{id}/preview-cert` | Pratinjau sertifikat pelatihan |
| **Sertifikat Training** | `GET` | `/api/hris/training/participants/{id}/download-cert` | Unduh sertifikat pelatihan |
| **Lampiran Izin/Cuti** | `GET` | `/api/hris/requests/{id}/preview-attachment` | Pratinjau surat dokter / bukti izin |
| **Lampiran Izin/Cuti** | `GET` | `/api/hris/requests/{id}/download-attachment` | Unduh lampiran surat dokter |
| **Banner Berita** | `GET` | `/api/hris/news/{id}/banner` | Stream gambar banner berita |

---

## 6. Integrasi Frontend SvelteKit (`secureMedia.ts`)

File: `frontend-locatrack/src/lib/utils/secureMedia.ts`

Untuk mengakses endpoint yang membutuhkan Bearer token di browser, frontend menggunakan dua utility utama:

### 1. `openSecurePreview(endpoint, token, title)`
- Mengambil stream binary via `apiService.getBlob(endpoint, token)`.
- Mengonversi blob menjadi Object URL (`URL.createObjectURL(blob)`).
- Membuka pratinjau instan di tab baru atau popup browser.
- Otomatis melepaskan memori URL setelah ditutup (`URL.revokeObjectURL`).

### 2. `downloadSecureFile(endpoint, token, filename)`
- Mengambil stream binary via `apiService.getBlob(endpoint, token)`.
- Membuat elemen trigger `<a download="...">` secara transparan untuk memulai pengunduhan dengan nama berkas asli.

---

## 7. Daftar Modul & Kolom Database Terkait

Tabel database yang telah diperbarui dan bersih dari kolom base64:

| No | Tabel Database | Kolom Path Baru | Kolom Nama/Meta | Keterangan Berkas |
|---|---|---|---|---|
| 1 | `employees` | `photo_path` | - | Foto profil karyawan |
| 2 | `users` | `photo_path` | - | Foto profil akun login |
| 3 | `hris_contracts` | `document_pdf_path` | `document_pdf_name` | PDF kontrak kerja resmi |
| 4 | `hris_documents` | `document_path` | `document_name`, `file_type`, `file_size_kb` | Berkas brankas ijazah/KTP |
| 5 | `hris_claims` | `receipt_path` | `receipt_name` | Kwitansi reimbursement |
| 6 | `hris_compliance_items` | `document_path` | `doc_name`, `doc_number` | Dokumen SIM & legalitas |
| 7 | `hris_mutations` | `document_sk_path` | `document_sk_name` | SK Mutasi / Promosi |
| 8 | `hris_news` | `banner_path` | - | Gambar cover berita |
| 9 | `hris_requests` | `attachment_path` | `attachment_name` | Lampiran surat sakit |
| 10 | `hris_resignations` | `document_path` | `document_name` | Surat pengunduran diri |
| 11 | `hris_training_participants` | `certificate_path` | `certificate_name` | Sertifikat kelulusan training |
| 12 | `hris_violations` | `evidence_path` | `evidence_name` | Berkas bukti SP / pelanggaran |

---

## 8. Log Perbaikan Masalah & Bug Teknis

Berikut adalah riwayat perbaikan kendala yang telah diselesaikan:

1. **Perbaikan Model Gaji `HrisSalary`:**  
   Nama model diperbarui menjadi `App\Models\HrisEmployeeSalary` sehingga pembuatan kontrak baru berhasil menyinkronkan data gaji dan rekening ke master payroll.
2. **Pencegahan Error Duplikasi Nomor Kontrak (`Duplicate entry for key contract_number_unique`):**  
   Generator nomor kontrak (`CTR/YYYYMM/XXXX`) kini menghitung urutan sequence tertinggi pada bulan berjalan dan dilengkapi loop guard `exists()` untuk mencegah tabrakan sequence.
3. **Perbaikan Error Fatal PHP `Number()` pada Kontrak:**  
   Mengganti sintaks `Number($request->basic_salary)` yang keliru menjadi standard type-casting `(float)$request->basic_salary > 0`.
4. **Perbaikan Error SQL `Unknown column document_pdf_base64` saat Edit Kontrak:**  
   Menghapus seluruh assignment ke kolom base64 yang telah di-drop dari database dan beralih penuh ke `document_pdf_path` terenkripsi.
5. **Perbaikan Relasi `creator` pada Kontrak:**  
   Menambahkan method relasi `public function creator() { return $this->belongsTo(User::class, 'created_by'); }` pada model `HrisContract`.
6. **Peningkatan Fitur Brankas Dokumen (`/admin/hris/documents`):**  
   Menambahkan tombol aksi langsung **"Lihat Full"** dan **"Unduh Berkas"** pada kartu/tabel dokumen serta in-modal live decrypted viewer.
7. **Pembersihan Direktori Ganda (`storage/app/private/private`):**  
   Mengonsolidasikan seluruh berkas ke folder tunggal `storage/app/private/tenants/{tenantId}/` dan membersihkan path ganda di database.

---

8. **Penambahan Kolom `created_by` pada Tabel `hris_contracts`:**  
   Menambahkan kolom `created_by` (unsignedBigInteger nullable) pada tabel `hris_contracts` dan menyelaraskan metrik ringkasan kartu kontrak (`pkwt_contracts` & `pkwtt_contracts`) sehingga sinkron 100% antara backend dan frontend.

## 9. Panduan Pemeliharaan & Pengembangan Lanjutan

### Menambah Modul Baru dengan Berkas Terenkripsi:
Saat menambahkan fitur baru yang memiliki upload berkas di masa mendatang:
1. **Model & Migrasi:** Gunakan kolom `string('file_path')->nullable()` dan `string('file_name')->nullable()`. Hindari tipe data `longText` untuk file biner/base64.
2. **Controller Simpan Berkas:**
   ```php
   use App\Services\EncryptedStorageService;

   if ($request->hasFile('berkas')) {
       $stored = EncryptedStorageService::storeEncrypted($request->file('berkas'), $tenantId, 'nama_modul', 'prefix_file');
       $model->file_path = $stored['path'];
       $model->file_name = $stored['name'];
   }
   ```
3. **Controller Stream Berkas:**
   ```php
   public function preview($id) {
       $item = Model::findOrFail($id);
       return EncryptedStorageService::streamResponse($item->file_path, $item->file_name, false);
   }

   public function download($id) {
       $item = Model::findOrFail($id);
       return EncryptedStorageService::streamResponse($item->file_path, $item->file_name, true);
   }
   ```
4. **Frontend SvelteKit:**
   ```svelte
   <button onclick={() => openSecurePreview('/api/endpoint/preview', token, 'Judul')}>Lihat</button>
   <button onclick={() => downloadSecureFile('/api/endpoint/download', token, 'nama_file.pdf')}>Unduh</button>
   ```

---
*Dokumentasi ini dibuat secara otomatis dan telah divalidasi dengan pengujian end-to-end pada backend dan frontend Locatrack HRIS.*

---

## 10. Arsitektur Master Data Global: Departemen & Jabatan / Posisi

Untuk memastikan konsistensi data organisasi di seluruh modul aplikasi (Karyawan, Kontrak Kerja, Mutasi & Promosi, Evaluasi Kinerja, Penggajian), sistem kini dilengkapi modul Master Data terpusat:

### A. Tabel Database & Relasi:
1. **`hris_departments`**:
   - Kolom: `id`, `tenant_id`, `name`, `code`, `description`, `manager_id` (relasi ke `employees`), `is_active`, `timestamps`.
   - Relasi: `hasMany(HrisPosition)`, `belongsTo(Employee, 'manager_id')`.
2. **`hris_positions`**:
   - Kolom: `id`, `tenant_id`, `department_id`, `name`, `code`, `level` (`Staff`, `Officer`, `Lead`, `Supervisor`, `Manager`, `Director / VP`), `description`, `is_active`, `timestamps`.
   - Relasi: `belongsTo(HrisDepartment)`.

### B. REST API Endpoints:
- `GET /api/departments` & `GET /api/hris/departments` (List Departemen lengkap dengan jumlah jabatan & info manager)
- `POST /api/departments` & `POST /api/hris/departments` (Tambah Departemen)
- `PUT /api/departments/{id}` & `PUT /api/hris/departments/{id}` (Edit Departemen & otomatis sinkronisasi nama di profil karyawan dan kontrak)
- `DELETE /api/departments/{id}` & `DELETE /api/hris/departments/{id}` (Hapus Departemen)
- `GET /api/positions` & `GET /api/positions?department_id={id}` (List Jabatan / Posisi dengan filter departemen)
- `POST /api/positions` & `POST /api/hris/positions` (Tambah Jabatan)
- `PUT /api/positions/{id}` & `PUT /api/hris/positions/{id}` (Edit Jabatan & sinkronisasi nama posisi)
- `DELETE /api/positions/{id}` & `DELETE /api/hris/positions/{id}` (Hapus Jabatan)

### C. Pusat Pengaturan UI (/admin/hris/settings):
Tersedia 6 tab pengelolaan master data:
1. 🏢 **Departemen & Divisi**
2. 💼 **Jabatan & Posisi (dengan Level Hierarki)**
3. 📄 **Jenis Kontrak Kerja (PKWT, PKWTT, Magang, dll.)**
4. 💵 **Komponen Tunjangan Kontrak & Payroll**
5. 🏦 **Rekening Bank Penggajian**
6. 🗄️ **Kategori Dokumen Digital**

---

## 11. Arsitektur Master Data: Tipe Kendaraan & Armada (`vehicle_types`)

Untuk mendukung fleksibilitas manajemen armada logistik dan pelacakan GPS, sistem kini dilengkapi pengelolaan Master Tipe Kendaraan:

### A. Tabel Database:
- **`vehicle_types`**:
  - Kolom: `id`, `tenant_id`, `name`, `code`, `category` (`Mobil`, `Truk`, `Van`, `Pick-up`, `Motor`, `Lainnya`), `description`, `is_active`, `timestamps`.

### B. REST API Endpoints:
- `GET /api/vehicle-types` & `GET /api/hris/vehicle-types` (List tipe armada per tenant)
- `POST /api/vehicle-types` (Tambah tipe kendaraan)
- `PUT /api/vehicle-types/{id}` (Edit tipe kendaraan & otomatis update kendaraan terkait)
- `DELETE /api/vehicle-types/{id}` (Hapus tipe kendaraan)

### C. Fitur UX Cerdas (Dual-Access Management):
1. **Pada Halaman Utama Karyawan (`/admin/employees`):**
   - Form Tambah / Edit Karyawan menggunakan **Dropdown Dinamis** Departemen & Posisi (terfilter otomatis).
   - Tombol **"Pengaturan Departemen & Jabatan"** dengan modal terpadu langsung di halaman karyawan.
2. **Pada Halaman Utama Kendaraan (`/admin/vehicles`):**
   - Form Tambah / Edit Kendaraan menggunakan **Dropdown Dinamis** Tipe Kendaraan.
   - Tombol **"Pengaturan Tipe Kendaraan"** dengan modal terpadu langsung di halaman armada.
3. **Pada Pusat Pengaturan HRIS & Armada (`/admin/hris/settings`):**
   - Tab 7: 🚚 **Tipe Kendaraan** terintegrasi penuh dalam satu dashboard master konfigurasi global.

---

## 12. Manajemen Profil & Avatar Terenkripsi AES-256 (Admin & Karyawan)

### Arsitektur Unggah Foto Profil:
1. **Frontend (`employee-form.svelte` & `/admin/settings/+page.svelte`):**
   - File gambar (JPG/PNG/WEBP maks 5MB) dikonversi menjadi Data URL Base64 di browser pengguna.
   - Dikirim ke endpoint backend via payload JSON atau multipart form data.
2. **Backend (`AuthController::updateProfile` & `EmployeeController::store/update`):**
   - Base64 diekstrak dan dienkripsi menggunakan `EncryptedStorageService::storeEncrypted($file, $tenantId, 'avatars', 'avatar_' . $id)`.
   - File fisik disimpan terenkripsi di: `storage/app/private/tenants/{tenant_id}/avatars/{filename}.enc`.
   - Jalur relatif (`photo_path`) dicatat pada tabel `users` dan `employees`.
3. **Endpoint Streaming Terproteksi:**
   - `GET /api/users/{id}/photo` : Mengalirkan avatar terdekripsi untuk pengguna/admin.
   - `GET /api/employees/{id}/photo` : Mengalirkan foto profil terdekripsi untuk karyawan.

---

## 13. Panduan Pengguna (User Guide) & Kebijakan Privasi (UU PDP Compliance)

1. **User Guide (/admin/settings - Tab User Guide):**
   - Panduan terstruktur 5 pilar utama: Pelacakan Armada GPS Real-time, Manajemen SDM & Struktur Organisasi, Brankas Dokumen & Kontrak Kerja, Presensi Geofencing & Lembur, serta Penggajian (Payroll Bulanan).
2. **Privacy Policy (/admin/settings - Tab Privacy Policy):**
   - Disusun sesuai ketentuan **Undang-Undang Republik Indonesia No. 27 Tahun 2022 tentang Perlindungan Data Pribadi (UU PDP)** dan standar keamanan informasi **ISO/IEC 27001**.
   - Menjamin isolasi data antar-tenant, transparansi pembatasan pelacakan GPS (hanya dalam jam dinas/tugas aktif), serta hak subjek data karyawan.
