# Sistem Administrasi Sekolah

[![Status Rilis](https://img.shields.io/badge/Release-v1.0.0_Golden_Master-success.svg)](docs/RELEASE_NOTES_v1.0.md)
[![Status Roadmap](https://img.shields.io/badge/Roadmap-100%25_Completed-brightgreen.svg)](roadmap/ROADMAP.md)
[![Backend](https://img.shields.io/badge/Backend-Laravel_11_(PHP_8.2+)-red.svg)](laravel-api/)
[![Frontend](https://img.shields.io/badge/Frontend-Vue_3_+_Vite_6-42b883.svg)](vue-app/)
[![UI Kit](https://img.shields.io/badge/UI-PrimeVue_4_(Sakai)-blue.svg)](https://primevue.org/)
[![Database](https://img.shields.io/badge/Database-MariaDB_10.4+_/_MySQL_8.0+-orange.svg)](docs/DATABASE.md)
[![Security Status](https://img.shields.io/badge/Security-RCE_Protected_&_Audited-blueviolet.svg)](docs/SECURITY-DATA.md)

Sistem Informasi Manajemen Administrasi Sekolah terintegrasi berbasis web untuk tata kelola kesiswaan, kepegawaian (PTK), tata persuratan dinas, disposisi pimpinan, perizinan & cuti, kearsipan digital, jurnal pembelajaran guru, validasi kualitas data, serta pencadangan data mandiri.

---

## 📌 Status Proyek (Rilis Resmi v1.0.0)

* **Status Kematangan:** **Production-Ready / Stable Release (Golden Master)**
* **Versi Rilis:** `v1.0.0` (Tag Git: `v1.0.0`)
* **Tanggal Rilis & Pengesahan:** 3 November 2026
* **Status Roadmap:** **Fase 00 s.d. Fase 13 Selesai 100% & Ditutup Resmi** oleh Pemilik Proyek.
* **Hasil Pengujian UAT:** 18 dari 18 skenario uji operasional (*End-to-End*) dinyatakan **LULUS & APPROVED**.

---

## 🏛️ Arsitektur dan Teknologi

| Komponen | Spesifikasi & Teknologi | Keterangan |
| :--- | :--- | :--- |
| **Backend API** | **Laravel 11**, PHP `^8.2`, Laravel Sanctum | Arsitektur RESTful API (334 endpoint terdaftar), otentikasi token Bearer stateful, CORS terproteksi. |
| **Frontend SPA** | **Vue 3** (Composition API), **Vite 6.4.5**, Pinia, Vue Router 4 | 27 rute halaman interaktif, tema Gelap (*Dark*) & Terang (*Light*), desain responsif mobile/tablet/desktop. |
| **UI Components** | **PrimeVue 4** (Sakai Theme), Tailwind CSS | Komponen data grid canggih, form builder, modal dialog, chart statistik, dan navigasi breadcrumb. |
| **Database** | **MariaDB 10.4+** / **MySQL 8.0+** | Mesin penyimpanan InnoDB dengan integritas relasi referensial penuh (ACID), migration dan seeder sintetis. |
| **Reporting & Export** | **Dompdf** (PDF Generator), CSV, JSON | Ekspor surat dinas ber-Kop resmi sekolah, rekap data siswa/pegawai, dan backup terenkripsi. |
| **Direktori Proyek** | Backend: `laravel-api/` · Frontend: `vue-app/` · Dokumentasi: `docs/` | Struktur modular monorepo terpisah antara backend API dan frontend client. |

---

## 📦 10 Modul Domain Utama (Fitur Siap Pakai)

Seluruh modul telah selesai 100% dan terhubung langsung ke database riil tanpa mock/placeholder:

1. **Master Data & Konfigurasi Sekolah:**
   * Pengaturan profil sekolah, identitas NPSN/NSS, jenjang pendidikan, kurikulum, dan semester.
   * *Academic Year Locking:* Penguncian tahun ajaran lampau untuk menjaga integritas data historis sekolah.
2. **Autentikasi, Akun & RBAC:**
   * Otorisasi berbasis peran (*Role-Based Access Control*) dengan 5 peran kanonikal: `admin-sistem`, `operator-sekolah`, `kepala-sekolah`, `wakil-kesiswaan`, dan `guru`.
   * CLI inisialisasi administrator darurat: `php artisan app:bootstrap-admin`.
3. **Kesiswaan & Rombongan Belajar (Rombel):**
   * Buku Induk Siswa Digital lengkap (biodata, NISN, NIK, alamat, riwayat keluarga).
   * Penempatan siswa ke dalam rombel per tahun ajaran aktif, tata kelola kenaikan kelas (*grade promotion*), kelulusan, dan mutasi.
4. **Kepegawaian (PTK - Pendidik & Tenaga Kependidikan):**
   * Direktori pegawai, pencatatan NIP/NUPTK, status kepegawaian (PNS, PPPK, Honorer), jabatan, dan riwayat tugas tambahan.
5. **Persuratan Dinas & Disposisi Digital:**
   * Tata kelola Surat Masuk dan Surat Keluar ber-Kop resmi dinas sekolah.
   * Penomoran surat otomatis yang atomik (`lockForUpdate`) dan anti-duplikasi.
   * Alur persetujuan berjenjang (*Draft $\rightarrow$ Review $\rightarrow$ Approved $\rightarrow$ Issued*) dengan prinsip *no self-approval*.
   * Lembar disposisi pimpinan digital dengan batas waktu tindak lanjut staf/guru.
   * Generator dokumen PDF surat resmi siap cetak menggunakan Dompdf.
6. **Perizinan & Cuti Pegawai/Siswa:**
   * Pengajuan izin mandiri oleh guru/pegawai dan pencatatan izin siswa oleh wali kelas/operator.
   * Alur persetujuan Kepala Sekolah dengan pencatatan audit trail *append-only*.
7. **Kearsipan Dokumen Digital:**
   * Penyimpanan arsip surat keputusan, akreditasi, dan sertifikat sekolah.
   * Skema versi berkas *immutable* (tidak dapat ditimpa) disertai verifikasi integritas hash SHA-256.
8. **Administrasi Pembelajaran:**
   * Pengelolaan mata pelajaran, alokasi jam, dan jadwal mengajar mingguan.
   * Pengisian jurnal harian pembelajaran guru serta laporan rekapitulasi keterlaksanaan kurikulum.
9. **Kualitas Data & Audit Trail:**
   * Papan skor kelengkapan data siswa/pegawai, deteksi anomali/duplikasi data otomatis.
   * Log aktivitas sistem dan rekaman jejak keamanan yang tidak dapat dimanipulasi (*append-only*).
10. **Pencadangan & Pemulihan Mandiri (Backup & Restore Drill):**
    * Pencadangan database dan lampiran berkas terkompresi ZIP terenkripsi AES-256.
    * Fitur *automated restore drill* untuk membuktikan validitas file backup pada database simulasi terisolasi tanpa menyentuh database operasional aktif.

---

## 🔒 Postur Keamanan & Hasil Audit

* **Perlindungan Terhadap Remote Code Execution (RCE):**
  * **PHP Backend:** Bebas dari fungsi eksekusi berbahaya (`eval`, `exec`, `system`, `unserialize`). Eksekusi proses sistem (mysqldump/mysql) menggunakan tokenized array arguments di luar shell interpreter. Validasi unggahan berkas menerapkan pembatasan *single extension*, pengecekan magic-byte MIME binary, dan penyimpanan privat di luar docroot dengan penamaan acak UUID.
  * **Node.js Frontend:** Di lingkungan produksi, Node.js tidak berjalan sebagai backend server; aplikasi dikompilasi menjadi aset statis murni yang dilayani secara pasif oleh Apache/Nginx.
* **Audit Dependensi:**
  * Backend: `composer audit` mencatat **0 kerentanan RCE atau Command Injection**.
  * Frontend: `npm audit fix` telah diterapkan, memutakhirkan Vite ke versi `6.4.5` dan mengeliminasi seluruh kerentanan High dan Critical (tersisa 0 High, 0 Critical).

---

## 🚀 Panduan Menjalankan Sistem

### A. Kebutuhan Sistem Minimum
* **PHP:** Versi 8.2 atau lebih tinggi (ekstensi `pdo_mysql`, `mbstring`, `openssl`, `zip`, `gd`, `fileinfo` aktif).
* **Node.js:** Versi 18.x atau 20.x LTS & npm 9+.
* **Database:** MariaDB 10.4+ atau MySQL 8.0+.
* **Web Server (Produksi):** Apache 2.4+ atau Nginx.

---

### B. Menjalankan di Lingkungan Pengembangan (Development)

#### 1. Persiapan Backend (`laravel-api`)
```bash
cd laravel-api

# Pasang dependensi PHP
composer install

# Siapkan berkas konfigurasi lingkungan
cp .env.example .env
# Pada Windows PowerShell: Copy-Item .env.example .env

# Generate encryption key
php artisan key:generate

# Konfigurasikan koneksi DB pada berkas .env, kemudian jalankan migrasi database
php artisan migrate

# (Opsional) Isi database dengan data uji sintetis
php artisan db:seed

# Jalankan server backend development
php artisan serve --host=127.0.0.1 --port=8000
```

#### 2. Persiapan Frontend (`vue-app`)
Buka jendela terminal baru:
```bash
cd vue-app

# Pasang dependensi JavaScript
npm install

# Jalankan server frontend Vite
npm run dev
```
Akses antarmuka aplikasi melalui browser di: `http://localhost:4000` (atau port yang ditampilkan oleh Vite).

---

### C. Inisialisasi Akun Administrator Pertama Kali
Jika Anda tidak menggunakan database seeder dan ingin membuat akun administrator utama secara aman, gunakan perintah artisan:
```bash
cd laravel-api
php artisan app:bootstrap-admin --username=admin --email=admin@sekolah.sch.id --password="PasswordKuat123!"
```

---

### D. Operasional Pencadangan Data (Backup & Drill)
```bash
cd laravel-api

# Menjalankan pencadangan manual (Database + Arsip Lampiran)
php artisan backup:run-all

# Menguji validitas pemulihan arsip backup (Restore Drill pada DB terisolasi)
php artisan backup:restore-drill
```

---

## 📚 Dokumentasi Teknis Lengkap

Dokumentasi arsitektur, panduan operasional, dan laporan rilis tersedia di direktori `docs/` dan `roadmap/`:

| Dokumen | Deskripsi |
| :--- | :--- |
| [Catatan Rilis v1.0.0](docs/RELEASE_NOTES_v1.0.md) | Release Notes resmi, cakupan fitur, dan metadata rilis Golden Master. |
| [Panduan Deployment Produksi](docs/DEPLOYMENT_GUIDE.md) | Petunjuk instalasi server Apache/Nginx, virtual host, SSL, dan manajemen task schedule. |
| [SOP Runbook Operasional](docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md) | Prosedur standar operasional staf IT sekolah, manajemen akun, backup, dan insiden. |
| [Rencana Migrasi & Rollback](docs/RELEASE_MIGRATION_ROLLBACK_PLAN.md) | Rencana mitigasi risiko, prosedur rollback, dan pemulihan darurat. |
| [Roadmap Proyek](roadmap/ROADMAP.md) | Rekam jejak penyelesaian Fase 00 hingga Fase 13. |
| [Arsitektur Sistem](docs/ARCHITECTURE.md) | Desain teknis arsitektur perangkat lunak, pola service layer, dan aliran data. |
| [Desain Basis Data](docs/DATABASE.md) | Kamus data, relasi antarentitas, dan indeks performa. |
| [Kebijakan Keamanan & Data](docs/SECURITY-DATA.md) | Standar privasi data siswa, proteksi upload file, dan audit keamanan. |
| [Panduan Pengujian (Testing)](docs/TESTING.md) | Instruksi eksekusi automated test suite PHPUnit dan pengujian integrasi. |

---

## 📄 Lisensi dan Privasi Data

* Hak cipta kode dan lisensi aplikasi mengikuti ketentuan pengelolaan sekolah.
* **Privasi Data:** Dilarang mengunggah file `.env`, kredensial server, database dump produksi, maupun lampiran berkas asli yang memuat data pribadi (*Personally Identifiable Information* / PII) ke dalam repositori publik. Seluruh data seeder dalam repositori ini menggunakan data sintetis fiktif.
