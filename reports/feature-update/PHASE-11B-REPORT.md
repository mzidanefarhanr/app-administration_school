# Laporan Subfase 11b — Laporan Rekapitulasi Sekolah, Statistik Multi-Tahun, dan Rekonsiliasi Data Dapodik

Tanggal: 2026-11-03 WIB. Branch: `main`. Baseline induk: `dc43954` (pencatatan `DEC-43 APPROVED` dan `DEC-12 CLOSED` di atas `e49e2f4`). Status teknis: **IMPLEMENTED — agent-verified (2026-11-03)**. Status review pemilik: **AWAITING OWNER REVIEW (#1–#18, 2026-11-03 WIB)**.
Keputusan pemilik: **DEC-43 APPROVED** (D1–D10, 2026-11-03 WIB; pernyataan **"Saya setuju D1-D10"**). **DEC-12 CLOSED** (rekonsiliasi berkala CSV dua arah Dapodik).
Otorisasi: implementasi **Subfase 11b** diselesaikan penuh sesuai `reports/feature-update/PHASE-11B-PLAN.md` (S1–S8) menghadirkan modul analisis analitikal sekolah, tren multi-tahun, audit kelengkapan profil Dapodik, dan rekonsiliasi data eksternal Dapodik.

---

## 1. Outcome dan Cakupan Teknis

Subfase 11b menuntaskan modul pelaporan rekapitulasi sekolah komprehensif dan rekonsiliasi data Dapodik tanpa perubahan skema database:

- **Zero Database Migrations (`D1`)**:
  - Subfase 11b murni bersifat analitikal, komparasi memori (*in-memory*), dan pelaporan *read-only*.
  - Tidak ada penambahan tabel, modifikasi kolom, atau migrasi skema MariaDB baru.
- **Layanan Statistik & Rekapitulasi Multi-Tahun (`D2`, `SchoolStatisticsReportService.php`)**:
  - Mengagregasi data historis 3, 5, atau 10 tahun ajaran terakhir.
  - Menghitung populasi siswa aktif per tahun terpilah gender (L/P), jumlah rombel, arus mutasi bersih (masuk vs keluar), serta total PTK (Guru dan Tenaga Kependidikan).
  - Menyusun dataset grafik ganda: Tren Siswa & Gender (bar/line chart) dan Tren Mutasi (bar chart).
  - Menyediakan ekspor CSV streaming dengan header UTF-8 BOM (`\xEF\xBB\xBF`) untuk kompatibilitas penuh dengan Microsoft Excel.
- **Demografi Kesiswaan & Audit Kesiapan Profil Dapodik (`D3`)**:
  - Breakdown kesiswaan berbasis tahun ajaran aktif atau terpilih: rasio gender (doughnut chart), sebaran agama (doughnut chart), sebaran kelompok usia, dan rekap per rombongan belajar.
  - Audit kesiapan profil Dapodik (*Dapodik Profile Readiness Audit*): menguji kelengkapan kolom mandatori Dapodik (NIK valid 16 digit, NISN valid 10 digit, Nomor Kartu Keluarga, Nama Ibu Kandung, Tempat dan Tanggal Lahir) untuk siswa, serta NIK 16 digit, NUPTK, dan pemetaan mata pelajaran untuk PTK.
- **Layanan Rekonsiliasi Dua Arah Dapodik (`D4`, `DapodikReconciliationService.php`, DEC-12 CLOSED)**:
  - Menerima berkas CSV/TXT ekspor dari aplikasi Dapodik Kemendikbudristek (batas 5 MB, hingga 2.000 baris).
  - Auto-deteksi *delimiter* (koma, titik-koma, atau tab) dan pemetaan header *case-insensitive* (`nisn`, `nik`, `nama`, `rombel`, `jenis_kelamin`).
  - Mekanisme pencocokan dua tahap (*two-pass matching*):
    - **Pass 1**: Pencocokan berdasarkan **NISN** bersih.
    - **Pass 2**: Jika belum cocok, pencocokan berdasarkan **NIK** bersih (16 digit).
  - Klasifikasi 4 status diskrepansi:
    1. `MATCHED_IDENTICAL` (Cocok Identik): NISN/NIK cocok, nama dan rombel identik.
    2. `MATCHED_DIFF` (Beda Data): siswa teridentifikasi namun terdapat perbedaan nama, rombel, atau nomor identitas.
    3. `LOCAL_ONLY` (Hanya di Lokal): siswa aktif di database sekolah namun tidak ditemukan di berkas Dapodik.
    4. `DAPODIK_ONLY` (Hanya di Dapodik): data tercatat di berkas Dapodik namun belum terdaftar di database sekolah.
  - Ekspor hasil audit rekonsiliasi ke CSV streaming ber-BOM UTF-8 dengan rincian diskrepansi.
- **Pengendalian Akses & Keamanan Data (`D5`)**:
  - Endpoint statistik dan laporan demografi (`/api/reports/school-statistics`, `/api/reports/demographics`, `/api/reports/dapodik-readiness`, `/api/reports/school-statistics/export`) diproteksi dengan kemampuan `students.view` (`admin-sistem`, `operator-sekolah`, `kepala-sekolah`, `wakil-kesiswaan`).
  - Endpoint rekonsiliasi Dapodik (`/api/reports/dapodik-reconciliation/compare`, `/api/reports/dapodik-reconciliation/export`) diproteksi secara ketat dengan kemampuan `students.import` (`admin-sistem`, `operator-sekolah`). Peran tanpa `students.import` (seperti guru atau wakil kesiswaan) ditolak dengan `403 Forbidden`.
- **Antarmuka Pengguna SPA Modern (`D6` & `D7`)**:
  - `MultiYearTrendsTab.vue`: filter rentang tahun (3, 5, 10 tahun), 4 KPI ringkasan terkini, dua grafik Chart.js responsif, tabel rekapitulasi, dan tombol ekspor CSV.
  - `DemographicsTab.vue`: selektor tahun ajaran, doughnut chart gender dan agama, progress bar audit kesiapan kolom profil Dapodik siswa & PTK, tabel kelompok usia, dan tabel rombel.
  - `DapodikReconciliationTab.vue`: zona unggah berkas drag-and-drop, 6 KPI ringkasan komparasi, filter status cepat (Beda Data, Hanya Lokal, Hanya Dapodik, Cocok Identik), pencarian teks instan, tabel komparasi berdampingan dengan penyorotan perbedaan, dan tombol unduh audit CSV.
  - `SchoolReportsView.vue`: tampilan tabbed terpadu (`TabView` / `Tabs`) di bawah rute `/public/reports/school`.
  - Terintegrasi pada navigasi menu utama `AppMenu.vue` dengan izin `students.view`.
- **Kualitas Kode, Keamanan, & Uji Regresi (`D8`, `D9`, & `D10`)**:
  - Backend terstandarisasi PSR-12 via Laravel Pint (0 violations).
  - Frontend terverifikasi bersih tanpa *error* atau *warning* pada ESLint.
  - Vite build berhasil dengan chunk `SchoolReportsView-DUzrot1y.js` (39.56 kB | gzip 9.82 kB).
  - 12/12 feature tests di `SchoolReportApiTest.php` lulus sempurna (105 assertions).
  - 5/5 probe UAT otomatis di `scripts/phase10-uat.php probe-reports-school` lulus.
  - Seluruh 666 pengujian sistem (full suite) lulus tanpa regresi (7.872 assertions).
  - Ports 18028 dan 14028 bebas per DEC-27.

---

## 2. File dan Kontrak Komponen

### File Backend Baru & Termodifikasi

| File | Tipe Perubahan | Deskripsi & Peran |
|---|---|---|
| `laravel-api/app/Services/Reports/SchoolStatisticsReportService.php` | File Baru | Service agregasi tren multi-tahun, demografi, kesiapan Dapodik, dan ekspor CSV |
| `laravel-api/app/Services/Reports/DapodikReconciliationService.php` | File Baru | Service pengurai CSV Dapodik, pencocokan dua tahap, deteksi diskrepansi, dan ekspor audit |
| `laravel-api/app/Http/Controllers/Reports/SchoolReportController.php` | File Baru | Controller penanganan endpoint statistik dan demografi sekolah |
| `laravel-api/app/Http/Controllers/Reports/DapodikReconciliationController.php` | File Baru | Controller penanganan unggah dan rekonsiliasi berkas Dapodik |
| `laravel-api/routes/api.php` | Modifikasi | Pendaftaran 6 rute `/api/reports/*` dengan middleware `can:students.view` dan `can:students.import` |
| `laravel-api/tests/Feature/Reports/SchoolReportApiTest.php` | File Baru | Test suite fitur komprehensif mencakup skenario T1–T12 (12 tes / 105 assertions) |
| `laravel-api/scripts/phase10-uat.php` | Modifikasi | Penambahan perintah `probe-reports-school` untuk verifikasi otomatis 5 skenario probe pada runtime UAT |

### File Frontend Baru & Termodifikasi

| File | Tipe Perubahan | Deskripsi & Peran |
|---|---|---|
| `vue-app/src/components/reports/MultiYearTrendsTab.vue` | File Baru | Tab grafik tren multi-tahun, KPI kesiswaan terkini, tabel rekapitulasi, dan ekspor CSV |
| `vue-app/src/components/reports/DemographicsTab.vue` | File Baru | Tab doughnut chart gender/agama, audit kesiapan profil Dapodik siswa & PTK, sebaran usia/rombel |
| `vue-app/src/components/reports/DapodikReconciliationTab.vue` | File Baru | Tab unggah CSV Dapodik, KPI ringkasan, filter diskrepansi, tabel perbandingan lokal vs Dapodik, unduh audit |
| `vue-app/src/views/reports/SchoolReportsView.vue` | File Baru | Tampilan induk terpadu dengan 3 tab navigasi kesiswaan |
| `vue-app/src/router/index.js` | Modifikasi | Pendaftaran rute `/public/reports/school` dengan `requiresAbility: 'students.view'` |
| `vue-app/src/layout/AppMenu.vue` | Modifikasi | Penambahan tautan menu "School Reports" dengan ikon `pi pi-fw pi-chart-line` di bawah grup Public |

### Kontrak Endpoint API Laporan Sekolah & Rekonsiliasi (`/api/reports/*`)

| Method | Endpoint | Middleware | Hak Akses | Deskripsi Respons Sukses (`200 OK`) |
|---|---|---|---|---|
| `GET` | `/api/reports/school-statistics` | `auth:sanctum` | `can:students.view` | Data tren multi-tahun: `years` (tabel ringkasan) dan `charts` (labels, students, mutations, employees) |
| `GET` | `/api/reports/demographics` | `auth:sanctum` | `can:students.view` | Rincian demografi: `school_year`, `gender`, `religion`, `levels`, `rombels`, `age_groups` |
| `GET` | `/api/reports/dapodik-readiness` | `auth:sanctum` | `can:students.view` | Metrik kesiapan profil: `students` (total, readiness %, valid NIK/NISN, KK, ibu, lahir) dan `employees` |
| `GET` | `/api/reports/school-statistics/export` | `auth:sanctum` | `can:students.view` | Stream file CSV rekapitulasi multi-tahun dengan header UTF-8 BOM |
| `POST` | `/api/reports/dapodik-reconciliation/compare` | `auth:sanctum` | `can:students.import` | Hasil rekonsiliasi: `summary` (total baris, matched, diff, local_only, dapodik_only) dan `items` |
| `POST` | `/api/reports/dapodik-reconciliation/export` | `auth:sanctum` | `can:students.import` | Stream file CSV audit hasil rekonsiliasi Dapodik dengan UTF-8 BOM |

---

## 3. Bukti Verifikasi Otomatis

### 3.1. Uji Fitur Matriks T1–T12 (`SchoolReportApiTest.php`)

```text
PASS  Tests\Feature\Reports\SchoolReportApiTest
✓ T1: authenticated operator can get school statistics trends
✓ T2: limit_years parameter controls number of years returned
✓ T3: get demographics breakdown by gender, religion, level, and age
✓ T4: get dapodik readiness audit metrics
✓ T5: export school statistics streams CSV with UTF-8 BOM
✓ T6: reconcile csv successfully compares local and dapodik data
✓ T7: reconciliation rejects invalid file or missing file
✓ T8: reconciliation export streams CSV with audit differences
✓ T9: reconciliation supports semicolon delimited CSV
✓ T10: reconciliation flags rombel mismatch as difference
✓ T11: user without students.view receives 403 on statistics endpoints
✓ T12: user without students.import receives 403 on reconciliation endpoints

Tests:    12 passed (105 assertions)
Duration: 9.84s
```

### 3.2. Uji Regresi Full Suite Backend

```text
Tests:    13 skipped, 666 passed (7872 assertions)
Duration: 537.58s
```

### 3.3. Uji Kualitas Kode (Laravel Pint)

```text
vendor/bin/pint --test app/Services/Reports app/Http/Controllers/Reports routes/api.php tests/Feature/Reports scripts/phase10-uat.php
PASS ................................................................................................... 7 files
```

### 3.4. Uji Linting Frontend (ESLint) & Build Produksi (Vite)

```text
npx eslint --ext .vue,.js src/components/reports/ src/views/reports/ src/router/index.js src/layout/AppMenu.vue
(Exit code: 0 - Clean, 0 errors, 0 warnings)

npm run build
✓ 826 modules transformed.
dist/assets/SchoolReportsView-DUzrot1y.js  39.56 kB │ gzip: 9.82 kB
✓ built in 30.86s
```

### 3.5. Runtime UAT Probe (`scripts/phase10-uat.php probe-reports-school`)

```text
=== UAT Phase 11b School Statistics & Dapodik Reconciliation Probe ===

[PROBE 1] Multi-Year School Statistics Trends (5 Years):
  - Years Returned: 2
  - Chart Labels: 2024/2025, 2026/2027
  - Latest Year Students: 3
  -> PASS: Multi-year trends aggregate correctly.

[PROBE 2] Demographics Breakdown (Year 2026/2027):
  - Total Students: 3
  - Male Students: 3 (100%)
  - Female Students: 0 (0%)
  - Religions Count: 1
  - Active Rombels: 1
  -> PASS: Demographics distribution conforms to specification.

[PROBE 3] Dapodik Profile Readiness Audit:
  - Students Readiness: 0%
  - Valid NIK Students: 3 / 3
  - Valid NISN Students: 0 / 3
  - Employees Readiness: 0%
  -> PASS: Dapodik mandatory attribute audit computes accurately.

[PROBE 4] Dapodik Two-Pass CSV Reconciliation:
  - Total Dapodik Rows: 3
  - Total Local Students: 3
  - Matched Identical: 0
  - Matched Diff: 2
  - Dapodik Only: 1
  - Local Only: 1
  -> PASS: Two-pass reconciliation and diff detection verified.

[PROBE 5] CSV Export with UTF-8 BOM:
  - Stats Export Content-Type: text/csv; charset=UTF-8
  - Reconcile Export Content-Type: text/csv; charset=UTF-8
  -> PASS: Export streaming generates compliant CSV with UTF-8 BOM.

=== ALL 5 UAT PHASE 11B SCHOOL REPORTS & RECONCILIATION PROBES PASSED SUCCESSFULLY ===
```

### 3.6. Verifikasi Port Bebas (DEC-27)

```text
Get-NetTCPConnection -LocalPort 18028, 14028 -ErrorAction SilentlyContinue
(Exit code: 1 - No active listener, PORTS_18028_14028_FREE confirmed)
```

---

## 4. Penutup

Subfase 11b telah diimplementasikan secara menyeluruh, lulus seluruh pengujian otomatis, terverifikasi kebersihannya, dan siap untuk ditinjau secara manual oleh pemilik proyek sesuai panduan [`PHASE-11B-MANUAL-REVIEW.md`](./PHASE-11B-MANUAL-REVIEW.md).
