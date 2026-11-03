# Rencana Kerja Fitur — Subfase 13a (QA Integrasi dan UAT)

## 1. Ringkasan Eksekutif & Status Rencana

- **Subfase:** 13a — QA Integrasi dan UAT (*Cross-Module Integration QA, Full Role-Based Permission Matrix, E2E Workflows, Zero PII Leak Audit, Multi-Viewport UI Verification, Isolated UAT Verification*).
- **Milestone Induk:** Fase 13 — QA Akhir, Rilis dan Handoff Operasional.
- **Usulan Keputusan Arsitektur:** **DEC-48 PROPOSED (D1–D10)**.
- **Baseline Kode:** `main` pada commit [`0af991d`](https://github.com/mzidanefarhanr/app-administration_school/commit/0af991d) setelah penyelesaian penuh dan persetujuan pemilik untuk Fase 12 (Subfase 12c).
- **Status Rencana:** **PLAN — APPROVED (DEC-48: D1–D10)** pada 2026-11-03 WIB via instruksi pemilik: *"Saya setuju D1-D10"*.
- **Status Implementasi:** **IMPLEMENTED & DIVERIFIKASI AGEN (2026-11-03)**.
- **Status Review Pemilik:** **APPROVED (#1–#18, 2026-11-03 WIB)** via instruksi pemilik: *"Pengujian nomor 1-18 sudah berhasil dan saya approved."* (**SUBFASE 13A SELESAI LENGKAP & DITUTUP**).

---

## 2. Konteks & Latar Belakang Kebutuhan

Aplikasi administrasi sekolah telah melewati siklus pengembangan intensif dari Fase 00 hingga penutupan lengkap Fase 12. Sistem kini memiliki fondasi yang sangat matang:
- **334 Endpoint HTTP API** di backend Laravel yang mencakup 10 domain modul fungsional: Autentikasi & Pengguna, Data Master, Kesiswaan, Kepegawaian, Tata Persuratan, Perizinan Pegawai, Repositori Arsip Dokumen, Administrasi Pembelajaran (Jadwal & Jurnal), Kualitas Data & Rekapitulasi Sekolah, serta Pusat Tugas & Pengingat Operasional.
- **27 Halaman Antarmuka Terpadu** pada frontend Vue 3 SPA (Sakai PrimeVue) yang telah distandarisasi ke dalam bahasa Indonesia baku, bersih dari menu demo bawaan template, mendukung kontras tema terang/gelap, dan responsif terhadap layar ponsel (viewport 390px).
- **Ketahanan Backend & Observabilitas:** Indeks komposit performa, paginasi aman $O(1)$, antrean background jobs yang tangguh dengan retry ber-backoff eksponensial, pelacakan Correlation ID (`X-Correlation-ID`), dan penyensoran otomatis data pribadi (PII) pada berkas log aplikasi.

Sebelum melangkah ke tahap kesiapan rilis (*Release Readiness* — Subfase 13b) dan penerbitan rilis kandidat (*Deployment* — Subfase 13c), sistem memerlukan pengujian integrasi akhir menyeluruh (**Subfase 13a — QA Integrasi dan UAT**):
1. **Inventarisasi & Katalog Aktif:** Mengaudit dan mendokumentasikan seluruh 334 endpoint backend dan 27 rute antarmuka frontend secara definitif untuk memastikan tidak ada rute yatim (*orphaned routes*), dead links, atau ketidaksesuaian kontrak hak akses.
2. **Pengujian Matriks Peran Menyeluruh (Full Role Matrix):** Memverifikasi perilaku otorisasi secara presisi untuk 5 profil peran sekolah (`admin-sistem`, `operator-sekolah`, `kepala-sekolah`, `waka-kurikulum`, dan `guru`). Menjamin bahwa hak akses diberikan secara tepat (`200 OK`) dan pembatasan wewenang ditegakkan secara disiplin (`403 Forbidden` / pengalihan ke `/auth/access`).
3. **Pengujian Alur Integrasi Lintas Domain (End-to-End Workflows):** Memvalidasi 4 skenario bisnis kritis yang melibatkan kerja sama beberapa modul sekaligus:
   - *Alur Kesiswaan ke Arsip:* Siswa baru -> Penempatan rombel -> Penerbitan Surat Keterangan Aktif resmi ber-Kop & TTD -> Pengarsipan otomatis file PDF ke modul Arsip Dokumen.
   - *Alur Pembelajaran ke Pelaporan:* Profil Guru -> Penugasan mapel & rombel -> Pembuatan jadwal bebas bentrok -> Pengisian jurnal harian dalam batas toleransi 7 hari WIB -> Pemantauan kepatuhan pengisian jurnal oleh pimpinan.
   - *Alur Perizinan ke Pusat Tugas:* Pengajuan cuti oleh guru -> Notifikasi otomatis pada Pusat Tugas & Pengingat pimpinan/operator -> Persetujuan resmi (approval tanpa self-approval) -> Update status izin dan audit riwayat append-only.
   - *Alur Cadangan & Integritas Sistem:* Pemeriksaan kesehatan cadangan basis data dan status sistem.
4. **Audit Keamanan Data Pribadi (Zero PII Leak):** Memastikan tidak ada NIK 16 digit, kata sandi, token otentikasi Sanctum, atau nomor telepon sensitif yang bocor ke log Monolog atau terbuka untuk peran tanpa wewenang.
5. **Verifikasi Tampilan Multi-Resolusi & Aksesibilitas:** Memverifikasi keselarasan antarmuka pada layar Desktop (1440px), Tablet (768px), dan Layar Ponsel Mobile (390px) serta tema Light dan Dark.
6. **Automated Test Suite & Probe UAT:** Menyediakan rangkaian pengujian otomatis `EndToEndIntegrationTest.php` dan handler probe UAT `probe-integration` pada launcher UAT `.uat-phase10`.
7. **Pencatatan Known Issues & Batasan Rilis:** Mengidentifikasi dan mendokumentasikan secara transparan seluruh batasan sistem, item tertunda, dan ruang lingkup rilis aktual.

---

## 3. Usulan Keputusan Pemilik (DEC-48: D1–D10)

Berikut adalah 10 butir usulan keputusan arsitektur dan teknis untuk Subfase 13a:

### D1 — Cakupan Subfase 13a & Batasan Non-Goals
Kerjakan **Subfase 13a: QA Integrasi dan UAT** saja.
- **Goals:**
  1. Audit dan inventarisasi katalog lengkap seluruh 334 endpoint backend dan 27 halaman rute frontend aktif.
  2. Verifikasi matriks hak akses 5 profil peran sekolah (`admin-sistem`, `operator-sekolah`, `kepala-sekolah`, `waka-kurikulum`, `guru`).
  3. Verifikasi 4 skenario alur integrasi end-to-end lintas modul.
  4. Audit komprehensif zero PII leak pada log aplikasi dan respon API.
  5. Pengujian tampilan multi-viewport (Desktop 1440px, Tablet 768px, Mobile 390px) dan tema Light/Dark.
  6. Penyusunan suite tes integrasi otomatis `EndToEndIntegrationTest.php`.
  7. Penyediaan probe UAT terisolasi `probe-integration` pada `laravel-api/scripts/phase10-uat.php`.
  8. Pendokumentasian daftar *known issues*, *open items*, dan ruang lingkup rilis aktual.
- **Non-Goals:**
  - Tidak menambahkan fitur bisnis baru di luar modul yang sudah ada.
  - Tidak melakukan perubahan skema basis data baru (*zero schema change*).
  - Tidak melakukan rilis/deployment aktual ke lingkungan produksi operasional (rilis disiapkan pada 13b dan dieksekusi pada 13c jika diotorisasi).
  - Tidak mengubah port operasional: Apache (port 80/443) dan MariaDB operasional (port 3306) tetap tidak disentuh.
  - Tetap mematuhi aturan DEC-27: port 18028 dan 14028 dipastikan bebas listener setelah pengujian.

### D2 — Inventarisasi & Katalog Lengkap Endpoint serta Halaman Aktif
- Menyusun katalog resmi seluruh **334 endpoint HTTP API** di `laravel-api/routes/api.php` yang dikelompokkan ke dalam 10 domain modul:
  1. Autentikasi & Akun (`/api/auth/*`, `/api/users/*`, `/api/roles/*`, `/api/abilities/*`)
  2. Data Master Referensi (`/api/education-levels/*`, `/api/education-schools/*`, `/api/school-years/*`, dll.)
  3. Kesiswaan & Mutasi (`/api/students/*`, `/api/school-rombels/*`, `/api/academic/progression/*`, `/api/academic/mutations/*`)
  4. Kepegawaian & PTK (`/api/employees/*`, `/api/employee-statuses/*`, `/api/employments/*`)
  5. Tata Persuratan & Disposisi (`/api/letters/*`, `/api/letter-dispositions/*`, `/api/letter-settings/*`)
  6. Perizinan Siswa & Pegawai (`/api/leaves/*`)
  7. Repositori Arsip Dokumen (`/api/archives/*`)
  8. Administrasi Pembelajaran (`/api/teaching/assignments/*`, `/api/teaching/schedules/*`, `/api/teaching/journals/*`, `/api/teaching/reports/*`)
  9. Laporan & Kualitas Data (`/api/data-quality/*`, `/api/reports/school/*`, `/api/academic/period/*`)
  10. Dasbor, Pusat Tugas & Ketahanan Sistem (`/api/dashboard/*`, `/api/tasks/*`, `/api/backups/*`, `/api/jobs/status/*`)
- Mendokumentasikan seluruh **27 rute antarmuka aktif** di `vue-app/src/router/index.js` beserta breadcrumb dan kemampuan akses (`requiresAbility`) yang disyaratkan.

### D3 — Matriks Hak Akses Penuh (Full Role-Based Access Matrix)
- Melakukan verifikasi otorisasi formal terhadap 5 profil peran baku:
  1. **Admin Sistem (`admin-sistem`):** Wewenang penuh pengelolaan teknis, akun pengguna, peran, hak akses, pemulihan data, konfigurasi surat, dan pemantauan sistem.
  2. **Operator Sekolah (`operator-sekolah`):** Wewenang operasional penuh untuk administrasi data siswa, mutasi, kelulusan, rombel, direktori pegawai, surat masuk/keluar, disposisi, pengarsipan dokumen, penugasan mengajar, jadwal, laporan sekolah, dan pusat tugas operasional.
  3. **Kepala Sekolah (`kepala-sekolah`):** Wewenang eksekutif: persetujuan surat keluar (`letters.approve` / `letters.sign`), persetujuan izin pegawai (`leaves.approve`), instruksi disposisi surat, monitoring laporan sekolah, monitoring kepatuhan jurnal, dan dasbor pimpinan.
  4. **Waka Kurikulum (`waka-kurikulum`):** Wewenang akademik: penugasan guru & rombel, penyusunan jadwal pelajaran mingguan, penanganan exception jadwal (libur/reschedule), monitoring jurnal mengajar, dan peninjauan laporan kepatuhan guru.
  5. **Guru (`guru`):** Wewenang self-service terbatas: melihat jadwal mengajar pribadi, mengisi jurnal mengajar harian dalam *grace period* 7 hari kalender WIB, pengajuan izin/cuti mandiri (`leaves.request`), melihat direktori profil PTK, dan pengelolaan akun sendiri.
- Memverifikasi penolakan akses yang tegas (`403 Forbidden` pada API dan pengalihan ke `/auth/access` pada Vue Router) saat seorang aktor mencoba mengakses wewenang di luar perannya.

### D4 — Skenario Pengujian Alur Integrasi End-to-End (E2E Integration Workflows)
- Menjalankan 4 skenario pengujian integrasi alur kerja lintas domain:
  - **Alur E2E-1 (Kesiswaan -> Rombel -> Surat -> Arsip):**
    Pendaftaran siswa baru -> Penempatan ke rombongan belajar aktif -> Pembuatan draf Surat Keterangan Siswa Aktif -> Persetujuan dan penerbitan nomor surat resmi ber-Kop dan tanda tangan digital -> Pengarsipan otomatis file PDF surat ke modul Repositori Arsip -> Verifikasi integritas checksum SHA-256 dan unduh berkas arsip.
  - **Alur E2E-2 (PTK -> Penugasan -> Jadwal -> Jurnal Pembelajaran -> Rekap Kepatuhan):**
    Pendaftaran profil guru aktif -> Penugasan mata pelajaran ke rombel tahun ajaran aktif -> Pembuatan jadwal mingguan dengan verifikasi bebas bentrok waktu (guru, rombel, ruang) -> Evaluasi sesi harian efektif -> Pengisian jurnal mengajar harian oleh guru pengampu (idempoten dan batas 7 hari WIB) -> Verifikasi live ketercatatan sesi pada Laporan Kepatuhan Jurnal Pimpinan.
  - **Alur E2E-3 (Pengajuan Izin -> Notifikasi Tugas -> Persetujuan Pimpinan -> Selesai):**
    Pengajuan cuti oleh guru mandiri -> Notifikasi tugas sistem otomatis muncul di Pusat Tugas & Pengingat Operator/Kepala Sekolah -> Verifikasi pencegahan *self-approval* (guru tidak dapat menyetujui izinnya sendiri) -> Persetujuan resmi oleh Kepala Sekolah -> Status izin berubah menjadi Disetujui -> Tugas sistem otomatis diselesaikan (*dismissed*).
  - **Alur E2E-4 (Integritas Sistem Cadangan & Pemantauan):**
    Pemeriksaan status cadangan data -> Verifikasi integritas metadata backup dan proteksi berkas cadangan dari akses tanpa izin.

### D5 — Verifikasi Zero PII Leak pada Seluruh Respon & Catatan Log
- Memverifikasi secara otomatis bahwa mekanisme `PiiRedactionProcessor` dan `EnsureCorrelationId` (hasil Subfase 12b) menyensor secara tuntas data pribadi pada berkas log:
  - NIK (16 digit) disensor menjadi `[REDACTED]`.
  - Password, plain token Sanctum, dan kunci rahasia disensor menjadi `[REDACTED]`.
  - Nomor telepon pribadi pada log disensor menjadi `[REDACTED]`.
- Memverifikasi bahwa endpoint publik atau daftar ringkasan (misalnya daftar direktori guru untuk peran umum) tidak membocorkan NIK, nomor kartu keluarga, atau alamat lengkap kepada pengguna yang tidak memiliki hak `employees.manage` atau `students.manage`.

### D6 — Verifikasi Tampilan Multi-Resolusi & Responsivitas Mobile (390px)
- Melakukan verifikasi tata letak, keterbacaan, dan navigasi pada 3 profil resolusi perangkat:
  1. **Desktop Lebar (1440px / 1920px):** Tata letak kartu KPI dasbor rapi, tabel data memiliki ruang kolom yang memadai, sidebar navigasi tetap kokoh.
  2. **Tablet (768px):** Sidebar adaptif (dapat ditutup-buka), grid dasbor bertransformasi dari 4 kolom menjadi 2 kolom secara proporsional.
  3. **Layar Ponsel Mobile (390px — standar iPhone 12/13/14):** Tabel data memiliki scrolling horizontal yang mulus tanpa merusak topbar atau header, dialog modal formulir mengisi lebar layar secara proporsional ($\ge 90vw$), dan tombol sentuh memenuhi batas ergonomi jari tangan ($\ge 40 \times 40$ px).
- Memverifikasi keterbacaan teks dan kontras warna pada mode terang (*Light Mode*) dan mode gelap (*Dark Mode* / `.app-dark`).

### D7 — Automated End-to-End Integration Test Suite (`EndToEndIntegrationTest.php`)
- Menyusun test suite otomatis baru di:
  `laravel-api/tests/Feature/Integration/EndToEndIntegrationTest.php`
- Suite pengujian ini menguji secara komprehensif:
  - Eksekusi alur E2E-1 (Kesiswaan -> Surat -> Arsip).
  - Eksekusi alur E2E-2 (Guru -> Jadwal -> Jurnal -> Rekap Kepatuhan).
  - Eksekusi alur E2E-3 (Izin Pegawai -> Pusat Tugas -> Approval Kepala Sekolah).
  - Matriks pengujian hak akses lintas 5 peran pada endpoint-endpoint kritis.
  - Verifikasi ketiadaan kebocoran data pribadi (PII) pada respons API.

### D8 — UAT Probe Terisolasi (`probe-integration`) di `phase10-uat.php`
- Menambahkan handler probe baru pada launcher UAT:
  `php scripts/phase10-uat.php probe-integration`
- Handler ini menjalankan 5 probe verifikasi otomatis di lingkungan runtime terisolasi `.uat-phase10`:
  1. *Probe 1 (Runtime & Role Seeding):* Verifikasi database SQLite UAT dan kesiapan 5 akun pengguna uji (Admin, Operator, Kepsek, Waka, Guru).
  2. *Probe 2 (Role Matrix Permissions):* Verifikasi uji coba hak akses dan penolakan 403 untuk setiap peran.
  3. *Probe 3 (E2E Workflow Student-Letter-Archive):* Verifikasi rantai data siswa hingga terbit surat dan tersimpan di arsip.
  4. *Probe 4 (E2E Workflow Teacher-Schedule-Journal-Task):* Verifikasi rantai jadwal mengajar, pengisian jurnal, dan agregasi tugas operasional.
  5. *Probe 5 (Log Sanitization & Correlation):* Verifikasi injeksi header `X-Correlation-ID` dan ketiadaan raw NIK/password pada berkas `storage/logs/laravel.log`.

### D9 — Penyusunan Daftar Known Issues, Open Items, dan Lingkup Rilis Aktual
- Mendokumentasikan secara rinci dan jujur (*zero false claims*):
  - Status akhir seluruh keputusan arsitektur (DEC-01 s.d. DEC-48).
  - Penutupan sisa gerbang keputusan DEC-13 (retensi data pribadi pada audit trail dan soft-delete).
  - Pemetaan batasan rilis kandidat (*Release Scope* v1.0) versus fitur masa depan pasca-rilis (misal integrasi Dapodik via API web service resmi Kemendikdasmen).
  - Catatan rilis awal (*pre-release notes*) untuk panduan staf operasional sekolah.

### D10 — Protokol Review Manual Pemilik (#1–#18) & Kepatuhan Port DEC-27
- Menyusun checklist tinjauan manual 18 butir (#1–#18) pada `reports/feature-update/PHASE-13A-MANUAL-REVIEW.md` yang mencakup:
  - Verifikasi alur integrasi langsung dari antarmuka pengguna SPA.
  - Verifikasi perbedaan tampilan antarmuka saat login sebagai Admin, Operator, Kepala Sekolah, Waka Kurikulum, dan Guru.
  - Verifikasi responsivitas pada layar ponsel dan tema Light/Dark.
- Kepatuhan mutlak DEC-27: port 18028 dan 14028 hanya digunakan saat verifikasi aktif dan wajib dipastikan bebas listener setelahnya (`PORTS_18028_14028_FREE`).

---

## 4. Tahapan Rencana Kerja Implementasi (S1–S8)

| Tahap | Deskripsi Pekerjaan | Artefak / File Target |
|:---:|---|---|
| **S1** | **Inventarisasi Katalog Endpoint & Halaman Aktif** | Audit komprehensif seluruh 334 endpoint di `laravel-api/routes/api.php` dan 27 rute antarmuka di `vue-app/src/router/index.js`. |
| **S2** | **Audit Matriks Hak Akses Penuh (Full Role Matrix)** | Verifikasi hak akses dan penolakan 403 untuk 5 peran (`admin-sistem`, `operator-sekolah`, `kepala-sekolah`, `waka-kurikulum`, `guru`). |
| **S3** | **Verifikasi Alur Integrasi End-to-End (E2E-1 s.d. E2E-4)** | Pengujian alur kesiswaan->surat->arsip, guru->jadwal->jurnal->rekap, izin->tugas->approval, dan cadangan data. |
| **S4** | **Audit Keamanan Data Sensitif (Zero PII Leak)** | Verifikasi ketiadaan kebocoran NIK, kata sandi, dan token pada berkas log aplikasi dan respons HTTP. |
| **S5** | **Verifikasi UI Multi-Viewport & Tema Light/Dark** | Verifikasi tampilan Desktop (1440px), Tablet (768px), dan Layar Ponsel Mobile (390px) serta tema Light/Dark. |
| **S6** | **Penyusunan Automated Integration Test Suite** | Implementasi test suite baru `laravel-api/tests/Feature/Integration/EndToEndIntegrationTest.php`. |
| **S7** | **Penyediaan Probe UAT Terisolasi `probe-integration`** | Penambahan probe `probe-integration` di `laravel-api/scripts/phase10-uat.php` dan verifikasi 5/5 PASS. |
| **S8** | **Dokumentasi Known Issues, Checklist Manual & Laporan** | Penyusunan `PHASE-13A-REPORT.md`, `PHASE-13A-MANUAL-REVIEW.md` (#1–#18), pembaruan tracking docs, commit, dan push. |

---

## 5. Pertanyaan Keputusan Pemilik

Mohon konfirmasi pemilik dengan menyatakan:
> **"Saya setuju D1-D10"**

atau berikan arahan spesifik jika ada butir yang ingin disesuaikan sebelum eksekusi dimulai.
