# 🚀 Perencanaan Pengembangan Landing Page (Astro + Envato Template)

Dokumen ini berisi catatan rencana kerja pengembangan landing page LocaTrack untuk bulan depan, serta panduan teknis mengenai optimasi performa dan keamanan kode agar tidak mudah dibaca/ditiru oleh orang lain.

---

## 🎯 Rangkuman Proyek
* **Target Mulai**: Bulan Depan.
* **Status Aset**: Template HTML/Tailwind dari Envato seharga $12 (sudah dibeli).
* **Teknologi Utama**: **Astro** (`astro.build`) + **Tailwind CSS**.
* **Model Hosting**: Hosting statis di **Vercel** / **Netlify** menggunakan domain utama (contoh: `locatrack.com`), sedangkan dashboard Svelte tetap di subdomain (`app.locatrack.com`).

---

## 🛠️ Sistem Routing & URL Bersih (Clean URLs)
Dengan menggunakan Astro, URL website Anda akan otomatis bersih tanpa ekstensi `.html`:
1. **File-based Routing**: Astro menggunakan sistem folder-routing seperti SvelteKit.
   * `src/pages/index.astro` ➡️ `locatrack.com/`
   * `src/pages/pricing.astro` ➡️ `locatrack.com/pricing`
   * `src/pages/about.astro` ➡️ `locatrack.com/about`
2. **Vercel Integration**: Saat dideploy ke Vercel, Vercel secara otomatis menghapus akhiran ekstensi `.html` sehingga URL terlihat sangat profesional dan SEO-friendly.

---

## 🔐 2 Poin Keamanan Agar Kode Sumber Tidak Mudah Dibaca

Karena website dijalankan di browser, file HTML, CSS, dan JS harus diunduh oleh client agar bisa ditampilkan. Kita tidak bisa menyembunyikan file tersebut 100%, tetapi kita bisa **mengacak dan memampatkannya** agar sangat sulit dipahami atau diplagiat secara mentah-mentah menggunakan dua metode berikut:

### 1. Minifikasi & Bundling (Minification & Bundling)
Metode ini adalah standar wajib untuk semua website modern. Ketika Anda menjalankan perintah build (`npm run build`), Astro/Vite akan memproses kode Anda secara otomatis:
* **Kompresi Baris**: Seluruh spasi, enter, indentasi, dan baris baru di dalam kode HTML, CSS, dan JS akan dihapus total. Kode Anda akan berubah menjadi satu baris raksasa tak berujung.
* **Penghapusan Komentar**: Semua komentar penjelasan di dalam kode akan dibuang.
* **Penyederhanaan Variabel**: Nama variabel atau fungsi yang panjang di JavaScript akan diubah menjadi satu atau dua huruf acak (misalnya, `function checkUserSession()` diubah menjadi `function a()`).
* **Manfaat**:
  * Ukuran file menjadi sangat kecil sehingga loading website instan.
  * Ketika ada orang mengklik kanan dan memilih **"View Source"**, mereka hanya akan melihat tumpukan kode rapat satu baris yang sangat memusingkan dan tidak ramah manusia.

### 2. Obfuskasi JavaScript (JS Obfuscation)
Jika Anda memiliki logika interaktif, kalkulator harga, atau script khusus di landing page yang tidak ingin dicontek alurnya, Anda bisa menerapkan teknik **Obfuskasi**.
* **Cara Kerja**: Sebelum dideploy, kode JavaScript Anda diproses menggunakan alat/plugin seperti `javascript-obfuscator`. Alat ini akan mengacak sintaks kode Anda menjadi representasi string heksadesimal yang rumit.
* **Contoh Transformasi**:
  * Kode Asli:
    ```javascript
    const isPro = true;
    if (isPro) { console.log("Welcome Pro User"); }
    ```
  * Setelah Obfuskasi:
    ```javascript
    var _0x1a2b=["\x57\x65\x6c\x63\x6f\x6d\x65\x20\x50\x72\x6f\x20\x55\x73\x65\x72"];const isPro=!![];if(isPro){console['\x6c\x6f\x67'](_0x1a2b[0])}
    ```
* **Manfaat**:
  * Kode tetap berjalan 100% normal di browser.
  * Jika seseorang mencoba melakukan *reverse engineering* (menganalisis kode Anda), mereka akan menyerah karena nama variabel, teks, dan logika percabangan di dalamnya sudah teracak total dan tidak memiliki pola yang mudah dipahami.

---

## 📅 Langkah Awal Memulai (Bulan Depan)

Saat Anda siap memulai pengerjaan bulan depan, berikut adalah langkah pertamanya:

1. **Inisialisasi Project Astro**:
   ```bash
   npm create astro@latest
   ```
   *(Pilih opsi dengan integrasi Tailwind CSS agar sinkron dengan template)*.
2. **Pindahkan Aset Envato**:
   * Pindahkan file CSS/JS template ke folder `src/` Astro.
   * Letakkan gambar, logo, dan ikon di folder `public/`.
3. **Pecah HTML menjadi Komponen**:
   * Buat layout utama (misal: `Layout.astro` yang berisi Header & Footer).
   * Masukkan konten utama ke `src/pages/index.astro`.
4. **Deploy ke Vercel**:
   * Hubungkan repositori Git baru Anda ke akun Vercel untuk deployment otomatis setiap kali ada update.
