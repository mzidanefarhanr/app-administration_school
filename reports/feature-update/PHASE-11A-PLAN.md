# Fase 11 (Subfase 11a) — Rencana Ganti Widget Demo Dashboard Operasional

- **Tanggal:** 2026-11-03 (WIB)
- **Cabang:** `main`
- **Baseline audit:** `2a36800` (pencatatan penerimaan pemilik Subfase 10c `#1–#18` dan penutupan lengkap Fase 10 di atas paket implementasi `96db43b`)
- **Status:** **PLAN — OWNER APPROVED (D1–D10, 2026-11-03 WIB; pernyataan `"Saya setuju D1-D10"`) di bawah DEC-42 APPROVED**
- **Keputusan:** **DEC-42 (APPROVED, 2026-11-03 WIB)** — Arsitektur Subfase 11a: Penggantian Total Seluruh Widget Demo Dashboard Template dengan Widget Operasional Nyata, Metrik Terpadu Berbasis Kemampuan (Ability-Scoped Overview), Antrean Tugas Tertunda (Actionable Pending Tasks), Grafik Distribusi Akademik, serta Tampilan Adaptif Peran
- **Cakupan langkah ini:** **Pencatatan persetujuan pemilik atas rencana Subfase 11a**. Implementasi teknis langkah S1–S8 diotorisasi dan dikerjakan mulai langkah berikutnya.

---

## 1. Posisi dan Prasyarat

| Aspek | Status fakta |
|---|---|
| Fase 00–10 | **COMPLETE & ACCEPTED** (seluruh modul fondasi, master data, siklus akademik, persuratan, perizinan, arsip dokumen, dan administrasi pembelajaran selesai dan diterima pemilik). |
| Fase 10 (10a, 10b, 10c) | **SELESAI LENGKAP & DITUTUP** (`c8be397` [10a], `df9f925` [10b], `96db43b` [10c], diterima pemilik pada `2a36800`). Server UAT `.uat-phase10` port `18028`/`14028` bebas listener per `DEC-27` (`PORTS_18028_14028_FREE`). |
| Fase 11 di roadmap | [`roadmap/phases/PHASE-11-ANALYTICS.md`](../../roadmap/phases/PHASE-11-ANALYTICS.md): **11a** Ganti widget demo (**NEXT**), **11b** Laporan dan rekonsiliasi, **11c** Tugas dan pengingat internal. |
| Kebutuhan fungsional | [`docs/REQUIREMENTS.md`](../../docs/REQUIREMENTS.md) **FR-13**: *"Statistik nyata dengan definisi dan tanggal acuan; surat/izin/jurnal/data belum lengkap; drilldown sesuai izin."* |
| Kriteria penerimaan 11a | Dari `PHASE-11-ANALYTICS.md` §11a: *"Tidak ada Orders/Revenue/data sample; angka nyata dengan scope; widget tanpa izin tidak query/ungkap data."* |
| Batasan kerja | Tanpa AI forecasting/rekomendasi, tanpa integrasi email/WhatsApp otomatis, tanpa mutasi data langsung dari dashboard (dashboard informatif & navigatif), zero schema change. |

---

## 2. Temuan Audit Fakta Kode (`2a36800`)

Audit langsung terhadap file dashboard frontend (`vue-app/src/views/Dashboard.vue` dan `vue-app/src/components/dashboard/`) serta backend Laravel API menunjukkan kondisi aktual berikut:

1. **Dashboard Masih Didominasi Komponen Demo Template Sakai PrimeVue:**
   - [`vue-app/src/views/Dashboard.vue`](../../vue-app/src/views/Dashboard.vue) saat ini mengimpor 8 komponen:
     - `ArchiveExpiryWidget.vue`: **Nyata/Operasional** (telah diintegrasikan pada Fase 09b untuk dokumen arsip kedaluwarsa).
     - `ListUsersOnlineWidget.vue`: **Nyata/Operasional** (menampilkan daftar pengguna aktif dengan guard `users.view`).
     - `StatsWidget.vue`: **Demo Palsu** — menampilkan teks hardcoded: *"Orders: 152"*, *"Revenue: $2.100"*, *"Customers: 28441"*, *"Comments: 152 Unread"*.
     - `RecentSalesWidget.vue`: **Demo Palsu** — mengimpor `ProductService.js` dan menampilkan daftar produk toko dalam mata uang USD (*"Bamboo Watch", "Black Watch"*, dsb).
     - `BestSellingWidget.vue`: **Demo Palsu** — menampilkan daftar produk mock terlaris dengan persentase fiktif.
     - `ChartDataWidget_1.vue`: **Demo Palsu** — menampilkan grafik mock sales bulanan.
     - `RevenueStreamWidget.vue`: **Demo Palsu** — menampilkan grafik batang mock pendapatan.
     - `NotificationsWidget.vue`: **Demo Palsu** — menampilkan notifikasi mock belanja.
   - **Kebutuhan 11a:** Menggantikan seluruh widget demo palsu di atas menjadi widget operasional nyata yang mencerminkan ekosistem administrasi sekolah yang sesungguhnya.

2. **Ketersediaan Sumber Data Riil dari Modul 01–10:**
   - Modul Kesiswaan & Akademik (01, 03, 05, 06): Data siswa aktif, rombel aktif, tahun ajaran & semester aktif, serta rekap isu kelengkapan data siswa (`/api/data-quality/students/summary`).
   - Modul Personalia / PTK (04): Data guru aktif (`Pendidik`), tenaga kependidikan aktif (`Tenaga Kependidikan`), serta rekap kelengkapan data pegawai (`/api/data-quality/employees/summary`).
   - Modul Persuratan (07): Surat masuk yang belum didisposisi / belum ditindaklanjuti, surat keluar yang berstatus `submitted` (menunggu persetujuan kepala sekolah / waka).
   - Modul Perizinan (08): Pengajuan izin berstatus `submitted` yang menunggu persetujuan pejabat berwenang (`leaves.approve`).
   - Modul Arsip Dokumen (09): Dokumen kedaluwarsa atau mendekati kedaluwarsa (sudah berjalan via `ArchiveExpiryWidget.vue`).
   - Modul Pembelajaran (10): Rekapitulasi sesi belum mengisi jurnal bagi operator/pimpinan sekolah, serta jadwal mengajar hari ini dan sesi belum mengisi *within grace* bagi guru pengampu.
   - Modul Backup (02b): Status backup sistem terakhir, tanggal backup, dan ukuran arsip (khusus administrator).

3. **Prinsip Keamanan & Pembatasan Akses (Access Control):**
   - Sesuai kriteria penerimaan: *"widget tanpa izin tidak query/ungkap data"*.
   - Setiap metrik harus difilter secara ketat di sisi server berdasarkan ability pengguna (`students.view`, `employees.view`, `letters.view`, `leaves.view`, `teaching.view`, `archives.view`, `backup.manage`).
   - Di sisi frontend, widget tidak boleh memanggil endpoint jika pengguna tidak memiliki ability terkait, dan widget yang tidak relevan bagi peran tertentu disembunyikan secara bersih (`v-if`) tanpa merusak tata letak grid.

---

## 3. Usulan Keputusan Pemilik (DEC-42: D1–D10)

Berikut 10 butir keputusan arsitektur yang diusulkan untuk Subfase 11a:

### D1 — Cakupan Subfase 11a
Kerjakan **Subfase 11a: Ganti widget demo** saja. Seluruh widget demo template dari Sakai PrimeVue pada `Dashboard.vue` digantikan secara total dengan widget operasional nyata berbasis data riil lintas modul (01–10).
- **Non-goals**: AI forecasting/analisis prediktif, pengiriman notifikasi otomatis email/WhatsApp, mutasi data langsung dari dashboard (dashboard bersifat informatif dan navigatif ke modul terkait), laporan tren multi-tahun (disiapkan pada 11b), atau rekonsiliasi Dapodik (11b).

### D2 — Tanpa Migrasi Database Baru (Zero Schema Change)
Subfase 11a bersifat murni analitik dan agregasi pembacaan (*read-only*). Seluruh data bersumber dari tabel yang telah stabil pada modul 01–10:
- `students`, `school_rombels`, `student_rombels`, `school_years`, `semesters`
- `employees`, `employee_types`, `subjects`
- `incoming_letters`, `letter_dispositions`, `outgoing_letters`
- `leave_requests`, `leave_decisions`
- `archive_documents`, `archive_file_versions`
- `teaching_schedules`, `teaching_schedule_exceptions`, `teaching_journals`
- File backup manifest pada disk privat
Database operasional MariaDB (port 3306) dan Apache (port 80/443) tidak disentuh.

### D3 — Endpoint Agregasi Terpadu Berbasis Kemampuan (`GET /api/dashboard/overview`)
Menyediakan satu endpoint terpusat yang efisien untuk mengambil ringkasan metrik operasional dashboard.
- Endpoint dilindungi middleware `auth:sanctum`.
- Server memeriksa ability pengguna sebelum menjalankan kueri agregasi per domain.
- Node domain dalam payload response hanya disertakan jika pengguna memiliki ability yang sesuai:
  - `academic`: memerlukan `students.view` $\rightarrow$ memuat `active_school_year`, `active_semester`, `total_active_students`, `total_active_rombels`.
  - `employees`: memerlukan `employees.view` $\rightarrow$ memuat `total_teachers`, `total_staff`.
  - `data_quality`: memerlukan `students.view` atau `employees.view` $\rightarrow$ memuat `student_issues_count`, `employee_issues_count`.
  - `letters`: memerlukan `letters.view` $\rightarrow$ memuat `incoming_unprocessed_count`, `outgoing_pending_approval_count`.
  - `leaves`: memerlukan `leaves.view` $\rightarrow$ memuat `pending_requests_count`, `active_today_count`.
  - `teaching`: memerlukan `teaching.view` $\rightarrow$ bagi pimpinan/operator: memuat `scheduled_teachers_count`, `missing_journals_count`, `compliance_rate_percent`; bagi guru biasa: memuat `my_today_sessions_count`, `my_missing_within_grace`, `my_missing_overdue`, `my_compliance_rate_percent`.
  - `archives`: memerlukan `archives.view` $\rightarrow$ memuat `expiring_soon_count`, `expired_count`.
  - `system`: memerlukan `backup.manage` atau `users.manage` $\rightarrow$ memuat `last_backup_at`, `backup_status`, `storage_size`.
- Jika ability tidak dimiliki, node domain tersebut bernilai `null` / tidak dikirim, sehingga klien tidak menerima data di luar hak aksesnya.

### D4 — Endpoint Antrean Tugas Tertunda (`GET /api/dashboard/pending-tasks`)
Menyediakan endpoint terpadu untuk antrean tugas yang memerlukan perhatian segera (*actionable queue*), dibatasi maksimal 15 item teratas berperingkat urgensi:
- Surat keluar menunggu approval / penandatanganan (`submitted`) $\rightarrow$ untuk pimpinan / `letters.approve`.
- Surat masuk belum didisposisi $\rightarrow$ untuk pengelola surat / `letters.manage`.
- Pengajuan izin menunggu keputusan (`submitted`) $\rightarrow$ untuk pimpinan / `leaves.approve`.
- Sesi mengajar belum diisi jurnal $\rightarrow$ bagi guru: sesi miliknya yang masih dalam grace period ($\le 7$ hari); bagi operator: sesi overdue missing ($> 7$ hari).
- Dokumen arsip kedaluwarsa $\rightarrow$ untuk pengelola arsip / `archives.view`.
Setiap item memuat `category`, `title`, `description`, `date`, `urgency` (`high`, `warn`, `info`), dan `action_url` (tautan langsung ke halaman modul terkait).

### D5 — Endpoint Grafik Distribusi Operasional (`GET /api/dashboard/charts`)
Mengembalikan data terstruktur untuk komponen grafik:
- `students_by_level`: distribusi jumlah siswa aktif per tingkat kelas (X, XI, XII / 10, 11, 12) dan jenis kelamin (L/P) (memerlukan `students.view`).
- `teaching_monthly_trend`: tren persentase kepatuhan jurnal mengajar bulanan selama semester aktif berjalan (memerlukan `teaching.view`).

### D6 — Arsitektur Penggantian Komponen Frontend pada `Dashboard.vue`
Struktur komponen pada `Dashboard.vue` ditata ulang sebagai berikut:
1. **`ArchiveExpiryWidget.vue`** (tetap dipertahankan di baris paling atas).
2. **`OperationalStatsWidget.vue`** (menggantikan `StatsWidget.vue`): 4 kartu metrik utama adaptif peran dengan ikon dan warna elegan.
3. **`PendingTasksWidget.vue`** (menggantikan `RecentSalesWidget.vue`): daftar item tindakan mendesak dengan badge urgensi dan tombol navigasi langsung.
4. **`DataQualityOverviewWidget.vue`** (menggantikan `BestSellingWidget.vue`): progres kelengkapan data siswa dan pegawai dengan tombol pintas ke modul Data Quality.
5. **`StudentDistributionChartWidget.vue`** (menggantikan `ChartDataWidget_1.vue`): grafik batang distribusi siswa per tingkat dan gender.
6. **`TeachingComplianceChartWidget.vue`** (menggantikan `RevenueStreamWidget.vue`): grafik garis tren kepatuhan jurnal per bulan.
7. **`SystemOverviewWidget.vue`** (menggantikan `NotificationsWidget.vue`): status kalender akademik, jam server WIB, dan status backup sistem.
8. **`ListUsersOnlineWidget.vue`** (tetap dipertahankan di kolom kanan).

### D7 — Tampilan Adaptif Peran (Role-Adaptive Dashboard)
Tampilan dashboard menyesuaikan profil peran pengguna secara elegan:
- **Admin Sistem / Operator Sekolah**: Tampilan komprehensif seluruh operasional sekolah (Akademik, PTK, Persuratan, Perizinan, Arsip, Kualitas Data, Jurnal Mengajar, Status Backup, Users Online).
- **Kepala Sekolah / Wakil Kesiswaan**: Tampilan eksekutif berfokus pada persetujuan tertunda (Surat Keluar & Izin *submitted*), ringkasan kesiswaan, rekapitulasi kepatuhan guru, dan status arsip.
- **Guru Pengampu**: Tampilan personal berfokus pada kegiatan belajar-mengajar (Jadwal Mengajar Hari Ini, Sesi Belum Mengisi Jurnal *Within Grace*, Status Izin Mandiri, Rombel/Matpel yang Diampu).
- **Staf Administrasi**: Tampilan berfokus pada persuratan, izin pribadi, dan profil diri.
Widget yang tidak memiliki izin disembunyikan (`v-if`) tanpa meninggalkan ruang kosong (*clean responsive grid*).

### D8 — Ketahanan Performa & Kueri Terindeks
- Kueri agregasi di backend memanfaatkan indeks komposit yang telah ada pada database (mis. `status`, `school_year_id`, `semester_id`, `created_at`).
- Tidak memuat baris data masif ke memori (*no full model hydration*), melainkan menggunakan agregasi SQL terindeks (`COUNT`, `GROUP BY`).
- Waktu respons API `GET /api/dashboard/overview` dijaga $< 100\text{ ms}$.

### D9 — Eliminasi Penuh Aset Demo & Dependensi Toko
- Menghapus referensi terhadap `ProductService.js` pada halaman dashboard utama.
- Memastikan tidak ada sisa teks mata uang dollar ($), order toko, nama produk fiktif, atau data sample yang tersisa di dashboard.

### D10 — UAT Terisolasi & Verifikasi Tanpa Sentuh Server Operasional
- Menggunakan lingkungan UAT terisolasi `.uat-phase10` (yang sudah memiliki data lengkap kesiswaan, pegawai, surat, izin, arsip, dan jurnal pembelajaran) dengan launcher UAT yang diperluas atau skrip verifikasi terdedikasi.
- Menguji probe HTTP untuk seluruh peran (`uat.admin`, `uat.operator`, `uat.kepsek`, `uat.waka`, `uat.guru`, `uat.staff`).
- Memverifikasi build Vite, linting Pint, dan ESLint.
- Server UAT pada port 18028 dan 14028 dihentikan segera setelah pengujian selesai per `DEC-27` (`PORTS_18028_14028_FREE`). MariaDB dan Apache operasional tidak disentuh.

---

## 4. Desain Arsitektur & Spesifikasi Detail

### 4.1 Spesifikasi Kontrak API

#### 1. `GET /api/dashboard/overview`
- **Izin**: `auth:sanctum` (seluruh pengguna terotentikasi).
- **Perilaku**: Mengembalikan metrik ringkasan yang difilter di sisi server berdasarkan ability pengguna.
- **Contoh Payload Response (Admin / Operator)**:
```json
{
  "data": {
    "academic": {
      "active_school_year": "2026/2027",
      "active_semester": "Semester I",
      "is_locked": false,
      "total_active_students": 103,
      "total_active_rombels": 6
    },
    "employees": {
      "total_teachers": 101,
      "total_staff": 101
    },
    "data_quality": {
      "student_issues_count": 5,
      "employee_issues_count": 2
    },
    "letters": {
      "incoming_unprocessed_count": 3,
      "outgoing_pending_approval_count": 2
    },
    "leaves": {
      "pending_requests_count": 4,
      "active_today_count": 1
    },
    "teaching": {
      "view_type": "school_wide",
      "scheduled_teachers_count": 10,
      "expected_sessions_30d": 540,
      "recorded_journals_30d": 64,
      "missing_journals_30d": 476,
      "compliance_rate_percent": 11.9
    },
    "archives": {
      "expiring_soon_count": 2,
      "expired_count": 1
    },
    "system": {
      "last_backup_at": "2026-11-03 04:00:00",
      "backup_status": "success",
      "environment": "production"
    }
  },
  "meta": {
    "server_time_wib": "2026-11-03 12:50:00",
    "user_role": "admin-sistem"
  }
}
```

- **Contoh Payload Response (Guru Pengampu)**:
```json
{
  "data": {
    "academic": {
      "active_school_year": "2026/2027",
      "active_semester": "Semester I",
      "is_locked": false,
      "total_active_students": null,
      "total_active_rombels": null
    },
    "employees": null,
    "data_quality": null,
    "letters": null,
    "leaves": {
      "my_pending_requests_count": 0,
      "my_active_leave": null
    },
    "teaching": {
      "view_type": "teacher_personal",
      "teacher_name": "UAT-P10 Teacher 01",
      "today_sessions_count": 2,
      "expected_sessions_30d": 36,
      "recorded_journals_30d": 4,
      "missing_within_grace": 8,
      "missing_overdue": 24,
      "compliance_rate_percent": 11.1
    },
    "archives": null,
    "system": null
  },
  "meta": {
    "server_time_wib": "2026-11-03 12:50:00",
    "user_role": "guru"
  }
}
```

#### 2. `GET /api/dashboard/pending-tasks`
- **Izin**: `auth:sanctum`.
- **Perilaku**: Mengembalikan daftar item antrean tugas yang memerlukan aksi segera dari pengguna yang sedang login (maksimal 15 item).
- **Contoh Item Response**:
```json
{
  "data": [
    {
      "id": "outgoing-letter-14",
      "domain": "letters",
      "category": "Surat Keluar Menunggu Approval",
      "title": "Surat Keterangan Siswa Aktif No. 420/012/SMK/2026",
      "description": "Diajukan oleh Operator Sekolah untuk Siswa Ahmad Rifai",
      "date": "2026-11-02",
      "urgency": "high",
      "action_url": "/public/letters/outgoing"
    },
    {
      "id": "leave-request-8",
      "domain": "leaves",
      "category": "Permohonan Izin Menunggu Keputusan",
      "title": "Izin Sakit - Siti Aminah (Kelas X-A)",
      "description": "Rentang 2026-11-03 s.d. 2026-11-04 (2 hari)",
      "date": "2026-11-03",
      "urgency": "high",
      "action_url": "/public/leaves"
    },
    {
      "id": "teaching-session-102",
      "domain": "teaching",
      "category": "Jurnal Mengajar Belum Diisi",
      "title": "Kelas X-B · Matematika (Jam Ke-3 s.d. 4)",
      "description": "Sesi tanggal 2026-11-02 (tersisa 6 hari batas grace)",
      "date": "2026-11-02",
      "urgency": "warn",
      "action_url": "/public/teaching/journals"
    }
  ],
  "meta": {
    "total_tasks": 3
  }
}
```

#### 3. `GET /api/dashboard/charts`
- **Izin**: `auth:sanctum`.
- **Perilaku**: Mengembalikan kumpulan data visual untuk grafik distribusi kesiswaan dan tren kepatuhan jurnal mengajar (hanya jika pengguna memiliki ability terkait).

---

### 4.2 Matriks Hak Akses Widget Dashboard

| Widget | Kemampuan Diperlukan | Perilaku Jika Tidak Memiliki Izin |
|---|---|---|
| **ArchiveExpiryWidget** | `archives.view` | Disembunyikan total (`v-if="store.can('archives.view')"`) |
| **OperationalStatsWidget** | Bebas (konten adaptif) | Menampilkan metrik sesuai kemampuan pengguna |
| **PendingTasksWidget** | Bebas (konten adaptif) | Hanya memuat item antrean domain yang diizinkan; jika 0 task tampil empty state ramah |
| **DataQualityWidget** | `students.view` atau `employees.view` | Disembunyikan bagi guru biasa dan staf tanpa izin audit data |
| **StudentDistributionChartWidget** | `students.view` | Disembunyikan bagi pengguna yang tidak memiliki izin melihat data kesiswaan |
| **TeachingComplianceChartWidget** | `teaching.view` | Menampilkan tren kepatuhan sekolah bagi pimpinan/operator, atau tren personal bagi guru |
| **SystemOverviewWidget** | Bebas (konten adaptif) | Kalender akademik tampil untuk semua; rincian backup hanya tampil bagi `backup.manage` |
| **ListUsersOnlineWidget** | `users.view` | Sudah memiliki guard internal: tidak memanggil `/api/users` jika tanpa izin |

---

## 5. Rencana Langkah Implementasi (S1–S8)

Setelah keputusan D1–D10 disetujui pemilik, implementasi akan dikerjakan dalam urutan disiplin berikut:

- **S1 — Backend Service (`DashboardService.php`)**:
  Membangun service terdedikasi untuk mengagregasi data dari seluruh domain modul yang ada dengan kueri agregasi SQL efisien dan penyaringan berbasis ability.
- **S2 — Controller & Form Request (`DashboardController.php`)**:
  Membuat controller untuk menangani endpoint `GET /api/dashboard/overview`, `GET /api/dashboard/pending-tasks`, dan `GET /api/dashboard/charts`.
- **S3 — Registrasi API Routes (`routes/api.php`)**:
  Mendaftarkan prefix `/api/dashboard/*` di dalam middleware group `auth:sanctum`.
- **S4 — Penggantian Komponen Frontend Dashboard**:
  - Membuat `OperationalStatsWidget.vue` menggantikan `StatsWidget.vue`.
  - Membuat `PendingTasksWidget.vue` menggantikan `RecentSalesWidget.vue`.
  - Membuat `DataQualityOverviewWidget.vue` menggantikan `BestSellingWidget.vue`.
  - Membuat `StudentDistributionChartWidget.vue` menggantikan `ChartDataWidget_1.vue`.
  - Membuat `TeachingComplianceChartWidget.vue` menggantikan `RevenueStreamWidget.vue`.
  - Membuat `SystemOverviewWidget.vue` menggantikan `NotificationsWidget.vue`.
  - Memperbarui `Dashboard.vue` dengan tata letak grid responsif yang bersih dan role-adaptive.
- **S5 — Feature Test Suite (`DashboardApiTest.php`)**:
  Menyusun tes otomatis Pest mencakup seluruh skenario RBAC, ketiadaan kebocoran data antar-peran, integritas agregasi angka, dan penanganan empty state.
- **S6 — Pembersihan Aset Demo, Pint, ESLint, & Build Produksi**:
  Menghapus pemanggilan mock `ProductService.js`, menjalankan formatting Pint pada file PHP baru, menjalankan ESLint pada komponen Vue, dan mengeksekusi `npm run build` untuk memverifikasi kompatibilitas build Vite.
- **S7 — Verifikasi UAT Terisolasi & Penghentian Server per DEC-27**:
  Menjalankan probe HTTP nyata pada port 18028/14028 untuk seluruh variasi peran, memverifikasi tidak ada data demo yang tampil di UI, lalu menghentikan kedua server UAT per `DEC-27`.
- **S8 — Laporan & Dokumentasi Lengkap**:
  Menyusun laporan implementasi `reports/feature-update/PHASE-11A-REPORT.md`, panduan review manual `reports/feature-update/PHASE-11A-MANUAL-REVIEW.md`, serta memperbarui dokumen pelacakan.

---

## 6. Matriks Pengujian & Verifikasi (T1–T14)

| Kode | Area Pengujian | Skenario yang Diverifikasi | Ekspektasi Hasil |
|---|---|---|---|
| **T1** | Autentikasi | Request `GET /api/dashboard/overview` tanpa bearer token | Status `401 Unauthorized` |
| **T2** | Akun Nonaktif | Request dari akun berstatus `status_active = false` | Status `403 Forbidden` |
| **T3** | Admin Overview | Request dari akun peran `admin-sistem` | Status `200 OK`, seluruh node domain (`academic`, `employees`, `letters`, `leaves`, `teaching`, `system`) terisi angka nyata |
| **T4** | Operator Overview | Request dari akun peran `operator-sekolah` | Status `200 OK`, memuat data kesiswaan, kepegawaian, persuratan, perizinan, dan kepatuhan jurnal sekolah |
| **T5** | Kepsek Overview | Request dari akun peran `kepala-sekolah` | Status `200 OK`, memuat data ringkasan sekolah, tugas approval surat dan izin |
| **T6** | Guru Scoped View | Request dari akun peran `guru` (tanpa `students.view` / `employees.view` / `letters.manage`) | Status `200 OK`, node `employees` bernilai `null`, node `teaching` berorientasi personal (`my_today_sessions`, `my_missing_within_grace`) |
| **T7** | Staf Denied View | Request dari akun peran `staff` (TU non-pengajar) | Status `200 OK`, node `teaching` bernilai `null`, tidak mengungkapkan data mengajar guru |
| **T8** | Pending Tasks Queue | Request `GET /api/dashboard/pending-tasks` oleh admin/pimpinan | Status `200 OK`, mengembalikan antrean surat keluar `submitted` dan izin `submitted` |
| **T9** | Pending Tasks Guru | Request `GET /api/dashboard/pending-tasks` oleh guru | Status `200 OK`, hanya memuat sesi mengajar miliknya yang belum diisi jurnal $\le 7$ hari WIB |
| **T10** | Chart Distribution | Request `GET /api/dashboard/charts` | Status `200 OK`, distribusi siswa per tingkat dan gender sesuai data riil |
| **T11** | No Demo Artifacts | Pemeriksaan kode sumber dashboard frontend | Tidak ada kata "Orders", "Revenue", "Customers", simbol "$", atau impor `ProductService.js` |
| **T12** | Empty State Graceful | Dashboard dibuka saat tidak ada data tugas tertunda | Widget menampilkan pesan informatif ramah (*"All operational tasks are up to date"*) tanpa error console |
| **T13** | Frontend Vite Build | `npm run build` pada folder `vue-app/` | Build selesai tanpa error bundling, chunk aset terdaftar bersih |
| **T14** | Server UAT DEC-27 | Verifikasi listener port setelah pengujian | Port 18028 dan 14028 dipastikan bebas (`PORTS_18028_14028_FREE`) |

---

## 7. Batasan & Komitmen

- **Tanpa AI Decision / Forecasting**: Subfase 11a tidak menambahkan machine learning atau prediksi data fiktif.
- **Tanpa Integrasi Eksternal**: Tidak ada pengiriman pesan otomatis ke WhatsApp, SMS, atau email.
- **Tanpa Mutasi Data dari Dashboard**: Dashboard murni lapisan pemantauan; seluruh tindakan modifikasi data dilakukan melalui form terdedikasi pada modul masing-masing via navigasi tautan.
- **Zero Schema Change**: Tidak ada migration atau pengubahan struktur tabel database MariaDB maupun SQLite.
- **Kepatuhan DEC-27**: Port 18028 dan 14028 wajib dihentikan segera setelah pengujian selesai.
- **Perlindungan Lingkungan Operasional**: MariaDB (port 3306) dan Apache (port 80/443) tidak disentuh sama sekali.

---

## 8. Persetujuan Pemilik yang Diharapkan

Sebelum agen memulai penulisan kode pada Subfase 11a (langkah S1–S8), mohon pemilik meninjau usulan keputusan **DEC-42 (D1–D10)** di atas dan memberikan konfirmasi persetujuan (misalnya: *"Saya setuju D1-D10"* atau catatan penyesuaian).
