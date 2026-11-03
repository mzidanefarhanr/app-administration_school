# Laporan Subfase 11a — Ganti Widget Demo Dashboard Operasional

Tanggal: 2026-11-03 WIB. Branch: `main`. Baseline induk: `d7cf62d` (pencatatan `DEC-42 APPROVED` di atas penutupan Fase 10 `2a36800`). Status teknis: **IMPLEMENTED — agent-verified (2026-11-03)**. Status review pemilik: **AWAITING OWNER REVIEW (#1–#18, 2026-11-03 WIB)**.
Keputusan pemilik: **DEC-42 APPROVED** (D1–D10, 2026-11-03 WIB; pernyataan **"Saya setuju D1-D10"**).
Otorisasi: implementasi **Subfase 11a** diselesaikan penuh sesuai `reports/feature-update/PHASE-11A-PLAN.md` (S1–S8) menggantikan seluruh widget demo e-commerce bawaan template Sakai Vue dengan metrik operasional nyata sekolah.

---

## 1. Outcome dan Cakupan Teknis

Subfase 11a menuntaskan modernisasi antarmuka utama (Dashboard) sekolah dari template statis/mock menjadi pusat kendali operasional terintegrasi berbasis peran (Role-Based Access Control) dan data riil:

- **Zero Database Migrations (`D1`)**:
  - Subfase 11a murni bersifat analitikal dan pelaporan *read-only*.
  - Tidak ada penambahan tabel, kolom, perubahan tipe data, maupun migrasi skema MariaDB baru.
- **Scoping Berbasis Peran & Kemampuan (`D2`)**:
  - **Administrator Sistem (`admin-sistem`)**: menerima seluruh metrik sekolah, pemantauan backup database, tugas tertunda lintas-domain, audit kualitas data, serta grafik distribusi dan tren kepatuhan.
  - **Operator Sekolah (`operator-sekolah`)**: menerima metrik operasional sekolah, tugas koreksi operasional (surat belum didisposisi, jurnal mengajar overdue), audit kualitas data, dan grafik sekolah.
  - **Kepala Sekolah (`kepala-sekolah`) & Wakil Kesiswaan (`wakil-kesiswaan`)**: menerima ringkasan pimpinan sekolah (*school-wide*), tugas persetujuan surat keluar dan permohonan izin, serta grafik kepatuhan mengajar sekolah.
  - **Guru Pengampu (`guru`)**: server secara otomatis membatasi tampilan ke mode personal (*teacher_personal*). Menampilkan sesi mengajar hari ini, kepatuhan jurnal 30 hari guru sendiri, dan sesi *missing grace window* yang perlu diisi. Informasi staf, arsip sistem, dan surat sekolah umum disembunyikan (*null*).
  - **Staf / Pengguna Terbatas**: domain data yang tidak memiliki hak akses (`can`) disembunyikan secara aman (*null*), tanpa *error* 500.
- **Widget KPI Operasional Nyata (`OperationalStatsWidget.vue`) (`D3`)**:
  - Menggantikan demo `StatsWidget.vue` (orders, revenue, customers, comments).
  - 4 kartu metrik adaptif responsif:
    1. **Siswa Aktif**: jumlah siswa aktif dan rombel berjalan dengan tautan ke `/public/students/directory`.
    2. **Pendidik & Tendik**: total PTK aktif (terpilah Guru dan Tendik) dengan tautan ke `/public/employees`.
    3. **Surat & Izin Tertunda**: akumulasi surat masuk belum disposisi, surat keluar menunggu review/approval, dan izin menunggu keputusan dengan tautan ke modul terkait.
    4. **Kepatuhan Mengajar / Sesi Hari Ini**: persentase kepatuhan jurnal sekolah (untuk pimpinan/operator) atau jadwal sesi hari ini beserta sisa jurnal grace (untuk guru).
- **Daftar Tugas Tertunda Operasional (`PendingTasksWidget.vue`) (`D4`)**:
  - Menggantikan demo e-commerce `RecentSalesWidget.vue` / `BestSellingWidget.vue`.
  - Mengumpulkan seluruh urusan yang memerlukan aksi nyata pengguna secara terurut berdasarkan urgensi (*high* $\to$ *warn* $\to$ *info*) dan tanggal terbaru:
    - Surat keluar menunggu approval (*urgent*).
    - Surat masuk belum didisposisi.
    - Permohonan izin siswa/staf menunggu keputusan (*urgent*).
    - Jurnal mengajar belum diisi (dalam masa grace 7 hari untuk guru, atau overdue untuk operator).
    - Dokumen arsip kedaluwarsa.
  - Tombol aksi langsung (*direct call-to-action*) mengarahkan pengguna ke halaman penyelesaian.
  - State kosong yang ramah (*"Semua Beres!"*) jika tidak ada tugas tertunda.
- **Audit Kualitas & Kelengkapan Data Master (`DataQualityOverviewWidget.vue`) (`D5`)**:
  - Menampilkan ringkasan kelengkapan profil data siswa dan PTK dari engine audit Fase 06.
  - Dilengkapi *progress bar* visual persentase kelengkapan, indikator profil belum lengkap, dan tombol tinjau kendala langsung ke `/public/data-quality`.
- **Grafik Operasional Ganda (`D6`)**:
  - **Distribusi Siswa per Rombel (`StudentDistributionChartWidget.vue`)**: menggantikan `ChartDataWidget_1.vue` (demo linear). Menampilkan grafik batang komparasi siswa laki-laki dan perempuan per kelas.
  - **Tren Kepatuhan Mengajar (`TeachingComplianceChartWidget.vue`)**: menggantikan `RevenueStreamWidget.vue` (demo revenue quarterly). Menampilkan tren tingkat pengisian jurnal (%) dan volume jurnal terisi selama 4 bulan terakhir.
  - Keduanya beradaptasi penuh terhadap tema *Light* maupun *Dark* secara dinamis.
- **Informasi Operasional & Pintasan Cepat (`SystemOverviewWidget.vue`) (`D7`)**:
  - Menggantikan demo `NotificationsWidget.vue`.
  - Menampilkan tahun ajaran dan semester berjalan (dengan status aktif/terkunci), waktu server saat ini (WIB), indikator kesehatan cadangan database (untuk admin/backup manager), serta tombol pintasan cepat ke modul-modul utama sekolah.
- **Preservasi Widget Resmi yang Sudah Berfungsi (`D8`)**:
  - `ArchiveExpiryWidget.vue` (pengingat dokumen arsip kedaluwarsa dari Fase 07) dan `ListUsersOnlineWidget.vue` (daftar pengguna daring) tetap dipertahankan dan ditata berdampingan secara harmonis.
- **Kualitas Kode, Keamanan, & Uji Regresi (`D9` & `D10`)**:
  - Seluruh kode backend mengikuti standar PSR-12 via Laravel Pint.
  - Seluruh komponen Vue terverifikasi bersih tanpa *error* atau *warning* pada ESLint.
  - Bundle frontend produksi Vite berhasil di-*build* (`dist/assets/Dashboard-BP4b2Y80.js`, 41.00 kB).
  - 10/10 feature tests di `DashboardApiTest.php` lulus sempurna.
  - 5/5 probe UAT otomatis di `scripts/phase10-uat.php probe-dashboard` lulus.
  - Seluruh 654 pengujian sistem (full suite) lulus tanpa regresi.

---

## 2. File dan Kontrak Komponen

### File Backend Baru & Termodifikasi

| File | Tipe Perubahan | Deskripsi & Peran |
|---|---|---|
| `laravel-api/app/Services/DashboardService.php` | File Baru | Service backend untuk agregasi metrik `overview`, filter `pending-tasks`, dan pengumpulan data `charts` |
| `laravel-api/app/Http/Controllers/DashboardController.php` | File Baru | Controller penanganan request API dashboard |
| `laravel-api/routes/api.php` | Modifikasi | Pendaftaran 3 endpoint `/api/dashboard/*` di bawah middleware `auth:sanctum` |
| `laravel-api/tests/Feature/Dashboard/DashboardApiTest.php` | File Baru | Suite uji fitur komprehensif mencakup skenario T1–T10 (10 tes / 52 assertions) |
| `laravel-api/scripts/phase10-uat.php` | Modifikasi | Penambahan perintah `probe-dashboard` untuk verifikasi otomatis 5 skenario probe dashboard pada runtime UAT |

### File Frontend Baru & Termodifikasi

| File | Tipe Perubahan | Deskripsi & Peran |
|---|---|---|
| `vue-app/src/components/dashboard/OperationalStatsWidget.vue` | File Baru | 4 kartu KPI operasional responsif adaptif sesuai peran |
| `vue-app/src/components/dashboard/PendingTasksWidget.vue` | File Baru | Daftar tugas tindakan mendesak dengan badge urgensi dan tautan aksi langsung |
| `vue-app/src/components/dashboard/DataQualityOverviewWidget.vue` | File Baru | Progress bar kelengkapan data siswa & PTK serta tautan audit |
| `vue-app/src/components/dashboard/StudentDistributionChartWidget.vue` | File Baru | Bar chart distribusi siswa per rombel dan gender |
| `vue-app/src/components/dashboard/TeachingComplianceChartWidget.vue` | File Baru | Combo line/bar chart tren kepatuhan jurnal mengajar 4 bulan |
| `vue-app/src/components/dashboard/SystemOverviewWidget.vue` | File Baru | Info periode aktif, waktu WIB, status backup, dan tombol pintasan cepat |
| `vue-app/src/views/Dashboard.vue` | Modifikasi | Penggantian seluruh widget demo mock dengan widget operasional terintegrasi |

### Kontrak Endpoint API Dashboard (`/api/dashboard/*`)

| Method | Endpoint | Middleware | Hak Akses | Deskripsi Respons Sukses (`200 OK`) |
|---|---|---|---|---|
| `GET` | `/api/dashboard/overview` | `auth:sanctum` | Pengguna aktif | Data ringkasan: `academic`, `employees`, `data_quality`, `letters`, `leaves`, `teaching`, `archives`, `system`, dan `meta` (disesuaikan dengan ability akun) |
| `GET` | `/api/dashboard/pending-tasks` | `auth:sanctum` | Pengguna aktif | Array tugas terurut: `id`, `domain`, `category`, `title`, `description`, `date`, `urgency` (`high`/`warn`/`info`), `action_url` (maks. 15 tugas) |
| `GET` | `/api/dashboard/charts` | `auth:sanctum` | Pengguna aktif | Kumpulan data grafik: `students_by_level` (labels, datasets L/P) dan `teaching_compliance_trend` (labels 4 bulan, compliance %, count) |

---

## 3. Bukti Verifikasi Otomatis

### 3.1. Uji Fitur Matriks T1–T10 (`DashboardApiTest.php`)

```text
PASS  Tests\Feature\Dashboard\DashboardApiTest
✓ T1: unauthenticated requests are rejected with 401                                    (1.52s)
✓ T2: deactivated accounts are rejected with 403                                        (0.50s)
✓ T3: admin-sistem receives comprehensive overview with actual counts                   (0.65s)
✓ T4: operator-sekolah receives school-wide operational overview                        (0.61s)
✓ T5: kepala-sekolah receives leadership overview                                       (0.53s)
✓ T6: guru receives teacher personal overview and unpermitted sections are omitted      (0.60s)
✓ T7: plain staff without domain abilities has unpermitted domains as null              (0.60s)
✓ T8: pending-tasks returns submitted letters and leaves for principal                  (0.57s)
✓ T9: pending-tasks for teacher includes missing journals within grace window           (0.51s)
✓ T10: charts returns distribution and trend structure for authorized user              (0.79s)

Tests:    10 passed (52 assertions)
Duration: 8.15s
```

### 3.2. Pemeriksaan Standar Kode (Laravel Pint & ESLint)

- **Laravel Pint**:
  ```text
  PASS: 3 files inspected, 0 style issues.
  ```
- **ESLint Frontend**:
  ```text
  Checked src/views/Dashboard.vue and all 6 components in src/components/dashboard/*.vue.
  Result: 0 errors, 0 warnings.
  ```

### 3.3. Kompilasi Produksi Frontend (Vite Build)

```text
✓ 822 modules transformed.
dist/assets/Dashboard-BP4b2Y80.js   41.00 kB │ gzip: 10.08 kB
✓ built in 36.20s
```

### 3.4. Verifikasi Probe Runtime UAT (`probe-dashboard`)

Dijalankan pada runtime `.uat-phase10`:

```text
=== UAT Phase 11a Operational Dashboard Probe ===

[PROBE 1] Admin Dashboard Overview:
  - Active School Year: 2026/2027
  - Total Active Students: 3
  - Total Active Rombels: 11
  - Teaching View Type: school_wide
  - System Info: Present
  -> PASS: Admin overview contains complete school-wide metrics.

[PROBE 2] Kepala Sekolah Dashboard Overview:
  - Role Slug: kepala-sekolah
  - Teaching View Type: school_wide
  - Can View Students: true
  -> PASS: Kepala Sekolah overview is school-wide.

[PROBE 3] Guru Dashboard Overview:
  - Teaching View Type: teacher_personal
  - Teacher Name: UAT-P10 Teacher 01
  - Employees Data: NULL (Hidden)
  - Letters Data: NULL (Hidden)
  - System Data: NULL (Hidden)
  -> PASS: Guru personal view correctly scoped without RBAC leaks.

[PROBE 4] Operational Pending Tasks:
  - Admin Tasks Count: 15
  - Guru Tasks Count: 14
  -> PASS: Pending tasks returned as structured arrays.

[PROBE 5] Charts API:
  - Admin Student Chart Labels: 11 rombels
  - Admin Teaching Trend Labels: 4 months
  - Guru Student Chart: NULL (Unpermitted)
  - Guru Teaching Trend: Present
  -> PASS: Operational charts conform to authorization matrix.

=== ALL 5 UAT PHASE 11A DASHBOARD PROBES PASSED SUCCESSFULLY ===
```

### 3.5. Uji Regresi Lengkap Seluruh Sistem (Full Test Suite)

```text
Tests:    13 skipped, 654 passed (7767 assertions)
Duration: 611.05s
```

---

## 4. Kepatuhan Isolasi & Kebijakan Port UAT (`DEC-27`)

- **Port 18028 & Port 14028**: Terverifikasi bebas dan tidak ada proses latar belakang yang tertinggal (`PORTS_18028_14028_FREE`).
- **Database Operasional MariaDB (Port 3306)**: Utuh tanpa modifikasi atau migrasi.
- **Apache Produksi (Port 80/443)**: Utuh tanpa sentuhan.
- **Environment Produksi (`.env`)**: Tidak dimodifikasi.
