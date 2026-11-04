# Sekolario — Sistem Administrasi Sekolah

[![Status Rilis](https://img.shields.io/badge/Release-v1.1.0_Sekolario_i18n-success.svg)](docs/RELEASE_NOTES_v1.0.md)
[![Status Roadmap](https://img.shields.io/badge/Roadmap-100%25_Completed-brightgreen.svg)](roadmap/ROADMAP.md)
[![Bilingual](https://img.shields.io/badge/i18n-ID_%7C_EN_(2.728_keys)-blue.svg)](docs/I18N.md)
[![Design](https://img.shields.io/badge/Design-Sekolario_Emerald-059669.svg)](DESIGN.md)
[![Backend](https://img.shields.io/badge/Backend-Laravel_11_(PHP_8.2+)-red.svg)](laravel-api/)
[![Frontend](https://img.shields.io/badge/Frontend-Vue_3_+_Vite_6-42b883.svg)](vue-app/)
[![UI Kit](https://img.shields.io/badge/UI-PrimeVue_4_(Aura_Preset)-blue.svg)](https://primevue.org/)
[![Database](https://img.shields.io/badge/Database-MariaDB_10.4+_/_MySQL_8.0+-orange.svg)](docs/DATABASE.md)
[![Security Status](https://img.shields.io/badge/Security-RCE_Protected_&_Audited-blueviolet.svg)](docs/SECURITY-DATA.md)

**Sekolario** adalah Sistem Informasi Manajemen Administrasi Sekolah terintegrasi berbasis web dengan dukungan dwibahasa (Bahasa Indonesia & English) dan identitas visual modern. Dirancang untuk tata kelola kesiswaan, kepegawaian (PTK), tata persuratan dinas, disposisi pimpinan, perizinan & cuti, kearsipan digital, jurnal pembelajaran guru, validasi kualitas data, serta pencadangan data mandiri.

---

## 📌 Status Proyek (Rilis Resmi v1.1.0)

* **Status Kematangan:** **Production-Ready / Stable Release**
* **Versi Rilis:** `v1.1.0` (Tag Git: `v1.1.0`)
* **Pembaruan Terkini (v1.1.0):**
  * **Internasionalisasi Penuh (i18n):** Dukungan dwibahasa (Bahasa Indonesia dan English) pada seluruh 27 halaman antarmuka, mencakup 2.728 kunci terjemahan simetris 100%, kalender dan paginator multibahasa, serta pemilih bahasa (`FloatingConfigurator`) yang reaktif instan tanpa reload halaman di halaman autentikasi (Login, Lupa Sandi, Reset Sandi) maupun di dalam dasbor.
  * **Identitas Jenama Sekolario:** Penerapan identitas visual resmi *Sekolario* dengan palet warna primer emerald (`#059669`) dan surface slate, tipografi modern lokal **Plus Jakarta Sans** (format woff2 mandiri tanpa dependensi jaringan ke Google Fonts), logo vektor responsif terhadap mode terang/gelap, serta variasi tagline dwibahasa otomatis (*"Sistem Administrasi Sekolah"* / *"School Management System"*).
  * **Konsolidasi Arsitektur:** Pengesahan keputusan arsitektur `DEC-51` (kontras tombol primer Opsi A), `DEC-52` (pemilih bahasa di halaman auth), dan `DEC-53` (`DESIGN.md` sebagai *Single Source of Truth*).
* **Status Roadmap:** **Fase 00 s.d. Fase 13 Selesai 100% & Ditutup Resmi**.
* **Hasil Pengujian Validasi:** Matriks 8 kombinasi (Desktop & Mobile 390px $\times$ Terang/Gelap $\times$ ID/EN) pada halaman kritis dinyatakan **LULUS (100% PASS)**.

---

## 🏛️ Arsitektur dan Teknologi

| Komponen | Spesifikasi & Teknologi | Keterangan |
| :--- | :--- | :--- |
| **Backend API** | **Laravel 11**, PHP `^8.2`, Laravel Sanctum | Arsitektur RESTful API (334 endpoint terdaftar), autentikasi session cookie stateful & Bearer token, CORS terproteksi. |
| **Frontend SPA** | **Vue 3** (Composition API), **Vite 6.4.5**, Pinia, Vue Router 4 | 27 rute antarmuka interaktif, Single Page Application (SPA), responsif multi-viewport (Desktop, Tablet, Ponsel 390px). |
| **Internasionalisasi** | **Vue I18n** (`^11.4.18`), PrimeVue Locales | 2.728 kunci terjemahan simetris (ID & EN), format tanggal & angka dinamis, paginator & kalender multibahasa. |
| **UI Components** | **PrimeVue 4** (Sekolario Preset / Aura-based), Tailwind CSS | Komponen data grid server-side, form builder, modal dialog seragam, chart statistik terharmonisasi, dan breadcrumb dinamis. |
| **Tipografi & Aset** | **Plus Jakarta Sans** (woff2 lokal), Aset SVG Sekolario | Bebas dari pemanggilan font eksternal (0 kebocoran ke Google Fonts), logo dan tagline adaptif tema & bahasa. |
| **Database** | **MariaDB 10.4+** / **MySQL 8.0+** | Mesin penyimpanan InnoDB dengan integritas referensial penuh (ACID), audit trail *append-only*, migrasi dan seeder. |
| **Reporting & Export** | **Dompdf** (PDF Generator), Streaming CSV, JSON | Ekspor surat dinas ber-Kop resmi dinas sekolah, rekapitulasi data siswa/pegawai, dan cadangan terenkripsi AES-256. |
| **Direktori Proyek** | Backend: `laravel-api/` · Frontend: `vue-app/` · Dokumentasi: `docs/` | Struktur modular monorepo terpisah antara backend API dan frontend client. |

---

## 📦 Fitur dan Modul Utama

1. **Dwibahasa & Jenama Sekolario (v1.1.0):**
   * Pilihan bahasa antarmuka Bahasa Indonesia (ID) dan English (EN) yang dapat diubah seketika melalui pemilih bahasa tanpa *page reload*.
   * Preferensi bahasa dan tema (terang/gelap) tersimpan persisten pada peramban (`localStorage`) dan bertahan setelah masuk (*login*).
   * Desain visual elegan berstandar profesional sekolah dengan tipografi lokal Plus Jakarta Sans.
2. **Master Data & Konfigurasi Sekolah:**
   * Pengaturan profil sekolah, identitas NPSN/NSS, jenjang pendidikan, kurikulum, dan semester.
   * *Academic Year Locking:* Penguncian tahun ajaran lampau untuk menjaga integritas data historis sekolah.
3. **Autentikasi, Akun & RBAC:**
   * Otorisasi berbasis peran (*Role-Based Access Control*) dengan 5 peran kanonikal: `admin-sistem`, `operator-sekolah`, `kepala-sekolah`, `wakil-kesiswaan`, dan `guru`.
   * CLI inisialisasi administrator darurat: `php artisan app:bootstrap-admin`.
4. **Kesiswaan & Rombongan Belajar (Rombel):**
   * Buku Induk Siswa Digital lengkap (biodata, NISN, NIK, alamat, riwayat keluarga).
   * Penempatan siswa ke dalam rombel per tahun ajaran aktif, tata kelola kenaikan kelas (*grade promotion*), kelulusan, dan mutasi.
5. **Kepegawaian (PTK - Pendidik & Tenaga Kependidikan):**
   * Direktori pegawai, pencatatan NIP/NUPTK, status kepegawaian (PNS, PPPK, Honorer), jabatan, dan riwayat tugas tambahan.
6. **Persuratan Dinas & Disposisi Digital:**
   * Tata kelola Surat Masuk dan Surat Keluar ber-Kop resmi dinas sekolah.
   * Penomoran surat otomatis yang atomik (`lockForUpdate`) dan anti-duplikasi.
   * Alur persetujuan berjenjang (*Draft $\rightarrow$ Review $\rightarrow$ Approved $\rightarrow$ Issued*) dengan prinsip *no self-approval*.
   * Lembar disposisi pimpinan digital dengan batas waktu tindak lanjut staf/guru.
   * Generator dokumen PDF surat resmi siap cetak menggunakan Dompdf.
7. **Perizinan & Cuti Pegawai/Siswa:**
   * Pengajuan izin mandiri oleh guru/pegawai dan pencatatan izin siswa oleh wali kelas/operator.
   * Alur persetujuan Kepala Sekolah dengan pencatatan audit trail *append-only*.
8. **Kearsipan Dokumen Digital:**
   * Penyimpanan arsip surat keputusan, akreditasi, dan sertifikat sekolah.
   * Skema versi berkas *immutable* (tidak dapat ditimpa) disertai verifikasi integritas hash SHA-256.
9. **Administrasi Pembelajaran:**
   * Pengelolaan mata pelajaran, alokasi jam, dan jadwal mengajar mingguan bebas bentrok.
   * Pengisian jurnal harian pembelajaran guru serta laporan rekapitulasi keterlaksanaan kurikulum.
10. **Kualitas Data & Audit Trail:**
    * Papan skor kelengkapan data siswa/pegawai, deteksi anomali/duplikasi data otomatis.
    * Log aktivitas sistem dan rekaman jejak keamanan yang tidak dapat dimanipulasi (*append-only*).
11. **Pencadangan & Pemulihan Mandiri (Backup & Restore Drill):**
    * Pencadangan database dan lampiran berkas terkompresi ZIP terenkripsi AES-256.
    * Fitur *automated restore drill* untuk membuktikan validitas file backup pada database simulasi terisolasi.

---

## ⚡ Panduan Menjalankan Aplikasi di Lingkungan Lokal (Windows)

### Cara Paling Cepat: Gunakan Skrip Peluncur Otomatis (`.bat`)

Untuk kemudahan operasional di lingkungan Windows (XAMPP):

1. **Nyalakan Service MySQL di XAMPP:**
   * Buka **XAMPP Control Panel**.
   * Klik tombol **Start** pada modul **MySQL** (pastikan berjalan di port `3306`).
2. **Jalankan Aplikasi:**
   * Cukup klik dua kali file **`jalankan-sekolario.bat`** (atau `start-local.bat`) di direktori utama repositori:
     ```
     D:\xampp-htdocs\app-administration_school\jalankan-sekolario.bat
     ```
   * Skrip ini akan secara otomatis:
     * Menyiapkan file konfigurasi lingkungan `.env` backend dan frontend bila belum ada.
     * Memeriksa kesiapan service database MySQL port 3306.
     * Menjalankan server Backend Laravel API di latar belakang pada `http://127.0.0.1:8000`.
     * Menjalankan server Frontend Vue SPA di latar belakang pada `http://localhost:4000`.
     * Membuka peramban (*browser*) Anda secara otomatis ke halaman login Sekolario di **`http://localhost:4000`**.

---

### Cara Manual: Langkah demi Langkah

Jika Anda ingin menjalankan atau menyesuaikan konfigurasi secara manual melalui terminal:

#### 1. Konfigurasi Database Lokal (`laravel-api/.env`)
Pastikan file `laravel-api/.env` telah terkonfigurasi untuk database lokal MySQL Anda:
```env
APP_NAME=Sekolario
APP_ENV=local
APP_KEY=base64:JP97bgkYiRGF2Wct6o7U+QxBbA6uMNmJ3iA7adHeU60=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=backdata_administrations
DB_USERNAME=root
DB_PASSWORD=

FRONTEND_URL=http://localhost:4000
SANCTUM_STATEFUL_DOMAINS=localhost:4000,127.0.0.1:4000,localhost,127.0.0.1
SESSION_DOMAIN=
```
> **Catatan:** Sesuaikan `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` dengan setelan MariaDB/MySQL XAMPP Anda.

#### 2. Migrasi Basis Data & Pembuatan Admin Pertama Kali
Buka PowerShell atau Command Prompt pada folder `laravel-api`:
```bash
cd D:\xampp-htdocs\app-administration_school\laravel-api

# Jalankan migrasi tabel
php artisan migrate

# Buat akun administrator utama (Super Admin)
php artisan app:bootstrap-admin --username=admin --email=admin@sekolah.sch.id --password="PasswordKuat123!"
```
*(Atau Anda dapat mengklik dua kali file `setup-database-local.bat` untuk menjalankan migrasi dan pembuatan akun admin secara interaktif).*

#### 3. Menjalankan Server Backend Laravel
```bash
cd D:\xampp-htdocs\app-administration_school\laravel-api
php artisan serve --host=127.0.0.1 --port=8000
```
Backend API akan aktif dan melayani permintaan di `http://127.0.0.1:8000`.

#### 4. Menyiapkan Konfigurasi Frontend (`vue-app/.env`)
Pastikan file `vue-app/.env` berisi alamat backend API lokal Anda:
```env
VITE_API_BASE_URL=http://127.0.0.1:8000
```

#### 5. Menjalankan Server Frontend Vue SPA
Buka jendela terminal baru:
```bash
cd D:\xampp-htdocs\app-administration_school\vue-app
npm run dev
```
Akses antarmuka aplikasi melalui peramban web di: **`http://localhost:4000`**.

---

## 🔒 Postur Keamanan & Hasil Audit

* **Perlindungan Terhadap Remote Code Execution (RCE):**
  * **PHP Backend:** Bebas dari fungsi eksekusi berbahaya (`eval`, `exec`, `system`, `unserialize`). Eksekusi proses sistem (mysqldump/mysql) menggunakan tokenized array arguments di luar shell interpreter. Validasi unggahan berkas menerapkan pembatasan *single extension*, pengecekan magic-byte MIME binary, dan penyimpanan privat di luar docroot dengan penamaan acak UUID.
  * **Node.js Frontend:** Di lingkungan produksi, Node.js tidak berjalan sebagai backend server; aplikasi dikompilasi menjadi aset statis murni yang dilayani secara pasif oleh Apache/Nginx.
* **Audit Dependensi:**
  * Backend: `composer audit` mencatat **0 kerentanan RCE atau Command Injection**.
  * Frontend: `npm audit fix` diterapkan dengan Vite versi `6.4.5` dan vue-i18n `^11.4.18` (0 kerentanan High dan Critical).

---

## 📚 Dokumentasi Teknis Terkait

Dokumentasi arsitektur, panduan desain, dan catatan rilis tersedia di direktori `docs/` dan `roadmap/`:

| Dokumen | Deskripsi |
| :--- | :--- |
| [Catatan Desain Sekolario (DESIGN.md)](DESIGN.md) | **Single Source of Truth** identitas jenama Sekolario, font, palet warna emerald/slate, logo, dan preset UI. |
| [Panduan Dwibahasa (docs/I18N.md)](docs/I18N.md) | Panduan teknis arsitektur internasionalisasi, penambahan kunci terjemahan, dan format locale. |
| [Keputusan Arsitektur (docs/DECISIONS.md)](docs/DECISIONS.md) | Register lengkap keputusan teknis proyek (`DEC-01` hingga `DEC-53`). |
| [Rencana & Laporan Integrasi](reports/parallel/integration/REPORT.md) | Catatan eksekusi integrasi paralel Fase 0 hingga Fase 8 beserta bukti hasil validasi. |
| [Catatan Rilis v1.0.0 (docs/RELEASE_NOTES_v1.0.md)](docs/RELEASE_NOTES_v1.0.md) | Release Notes resmi, cakupan fitur modul inti, dan metadata rilis. |
| [Panduan Deployment Produksi (docs/DEPLOYMENT_GUIDE.md)](docs/DEPLOYMENT_GUIDE.md) | Petunjuk instalasi server Apache/Nginx, virtual host, SSL, dan manajemen task schedule. |
| [SOP Runbook Operasional (docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md)](docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md) | Prosedur standar operasional staf IT sekolah, manajemen akun, backup, dan insiden. |
| [Arsitektur Sistem (docs/ARCHITECTURE.md)](docs/ARCHITECTURE.md) | Desain teknis arsitektur perangkat lunak, pola service layer, dan aliran data. |
| [Desain Basis Data (docs/DATABASE.md)](docs/DATABASE.md) | Kamus data, relasi antarentitas, dan indeks performa. |
| [Kebijakan Keamanan & Data (docs/SECURITY-DATA.md)](docs/SECURITY-DATA.md) | Standar privasi data siswa, proteksi upload file, dan audit keamanan. |

---

## 📄 Lisensi dan Privasi Data

* Hak cipta kode dan lisensi aplikasi mengikuti ketentuan pengelolaan sekolah.
* **Privasi Data:** Dilarang mengunggah file `.env`, kredensial server, database dump produksi, maupun lampiran berkas asli yang memuat data pribadi (*Personally Identifiable Information* / PII) ke dalam repositori publik. Seluruh data seeder dalam repositori ini menggunakan data sintetis fiktif.
