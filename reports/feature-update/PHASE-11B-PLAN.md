# Rencana Kerja Fitur — Subfase 11b (Laporan Rekapitulasi Sekolah, Statistik Multi-Tahun, dan Rekonsiliasi Data)

## 1. Ringkasan Eksekutif & Status Rencana

- **Subfase:** 11b — Laporan Rekapitulasi Sekolah, Statistik Multi-Tahun, dan Rekonsiliasi Data Dapodik.
- **Milestone Induk:** Fase 11 — Dashboard Operator dan Laporan.
- **Usulan Keputusan Arsitektur:** **DEC-43 APPROVED (D1–D10, 2026-11-03 WIB)** dan penutupan gerbang **DEC-12 CLOSED (Laporan Eksternal & Rekonsiliasi Berkas)**.
- **Baseline Kode:** `main` pada commit [`bb06d98`](file:///D:/xampp-htdocs/app-administration_school) (pencatatan penerimaan pemilik Subfase 11a `#1–#18`). Paket rencana: `e49e2f4`.
- **Status Rencana:** **PLAN APPROVED (DEC-43 APPROVED; D1–D10 DISETUJUI PEMILIK 2026-11-03 WIB)** berdasarkan pernyataan pemilik: *"Saya setuju D1-D10"*.
- **Status Implementasi:** **IN PROGRESS (Langkah S1–S8 diotorisasi)**.

---

## 2. Konteks & Latar Belakang Kebutuhan

Pada Subfase 11a yang telah selesai dan disahkan pemilik, seluruh widget demo template Sakai PrimeVue pada beranda telah berhasil digantikan dengan widget operasional nyata live yang adaptif sesuai peran pengguna. Namun, untuk kebutuhan manajerial pimpinan sekolah (Kepala Sekolah, Wakil Kesiswaan) dan kebutuhan administrasi pelaporan dinas oleh Operator Sekolah, diperlukan modul laporan komprehensif yang menyajikan:

1. **Rekapitulasi Statistik Multi-Tahun (Multi-Year Academic Trends):**
   - Tren jumlah siswa per tahun ajaran (total siswa, sebaran gender Laki-laki vs Perempuan, dan per tingkat kelas X/XI/XII).
   - Tren mutasi siswa (siswa masuk dari luar sekolah vs siswa keluar/pindah/lulus) per tahun ajaran.
   - Tren komposisi Pendidik (Guru) vs Tenaga Kependidikan (Tendik) serta status kepegawaian.
   - Tren volume administrasi sekolah (surat masuk, surat keluar, dan permohonan izin).

2. **Rekapitulasi Demografi & Kesiapan Profil Dapodik (Dapodik Profile Readiness):**
   - Ringkasan demografis siswa aktif (sebaran agama, rentang usia, dan rombel).
   - Audit kelengkapan atribut wajib profil identitas siswa (NIK 16 digit valid, NISN 10 digit valid, Nomor Kartu Keluarga, Nama Ibu Kandung, Tempat/Tanggal Lahir) untuk persiapan pelaporan ke pangkalan data nasional.

3. **Rekonsiliasi Data Berkas Dapodik (DEC-12 - File-based Dapodik Reconciliation):**
   - Gerbang keputusan **DEC-12** telah berstatus *PROPOSED* sejak Fase 05b untuk menyelesaikan kebutuhan sinkronisasi eksternal sekolah.
   - Sesuai prinsip ketahanan sistem sekolah: **tidak menggunakan API/Web Service langsung ke server Dapodik Kemendikdasmen** (Dapodik tidak menyediakan API terbuka dua arah yang stabil untuk pihak ketiga, dan sering berubah versi setiap semester).
   - Rekonsiliasi dilakukan secara transparan melalui **unggah berkas ekspor CSV Dapodik lokal**: sistem membaca berkas, melakukan komparasi dua arah dengan database sekolah lokal, menampilkan tabel perbandingan *side-by-side*, mengelompokkan perbedaan data (*Matched Identical, Matched Diff, Local Only, Dapodik Only*), dan menyediakan ekspor CSV hasil audit **tanpa penimpaan (auto-overwrite) otomatis**.

---

## 3. Usulan Keputusan Pemilik (DEC-43: D1–D10 & DEC-12)

Berikut 10 butir keputusan arsitektur yang diusulkan untuk Subfase 11b:

### D1 — Cakupan Subfase 11b & Batasan Non-Goals
Kerjakan **Subfase 11b: Laporan dan Rekonsiliasi** saja. Fokus utama:
1. Rekapitulasi statistik multi-tahun (tren siswa, rombel, mutasi, pegawai) lintas tahun ajaran aktif dan terkunci.
2. Rekapitulasi demografi dan kesiapan profil identitas Dapodik.
3. Fitur rekonsiliasi berkas CSV Dapodik lokal dengan komparasi visual dua arah dan ekspor laporan audit.
- **Non-goals**:
  - Tidak ada integrasi API langsung ke Dapodik Kemendikdasmen via jaringan.
  - Tidak ada fitur penimpaan otomatis (*auto-overwrite / auto-sync*) ke database lokal dari berkas Dapodik (perubahan data lokal tetap dilakukan melalui modul master atau impor terverifikasi Fase 05a).
  - Subfase 11c (Tugas dan Pengingat Internal Lanjutan) dikerjakan terpisah setelah 11b.

### D2 — Penyelesaian Gerbang Keputusan DEC-12 (Laporan Eksternal & Rekonsiliasi Berkas)
Menutup gerbang **DEC-12** (yang berstatus PROPOSED sejak Fase 05b):
- Format laporan eksternal sekolah diselaraskan dengan struktur ringkasan demografis baku.
- Rekonsiliasi data Dapodik diimplementasikan melalui pemrosesan berkas CSV ekspor Dapodik secara aman (*in-memory / temporary file*), tanpa dependensi eksternal yang rapuh. Status DEC-12 disahkan menjadi **CLOSED**.

### D3 — Tanpa Migrasi Database Baru (Zero Schema Change)
Subfase 11b bersifat murni analitik, agregasi pembacaan (*read-only*), dan pemrosesan komparasi berkas:
- Menggunakan data relasional yang telah stabil: `students`, `school_rombels`, `student_rombels`, `school_years`, `semesters`, `student_mutations`, `employees`, `employee_types`, `letters`, `leave_requests`.
- Berkas CSV Dapodik yang diunggah untuk rekonsiliasi diproses langsung secara *streaming/in-memory* dan dihapus dari penyimpanan sementara setelah perbandingan selesai, tanpa membuat tabel baru.
- Database operasional MariaDB (port 3306) dan Apache (port 80/443) tidak disentuh.

### D4 — Mesin Rekonsiliasi Data Dapodik (Matching Engine & Klasifikasi)
- **Format Masukan**: Berkas CSV UTF-8 berukuran maksimal 5 MB (batas 2.000 baris, konsisten dengan batas impor 05a).
- **Deteksi Header Otomatis**: Mendukung header CSV ekspor standar Dapodik (mis. kolom NISN, Nama, NIK, Rombel/Tingkat, Jenis Kelamin).
- **Pencocokan Bertingkat (Two-pass Matching)**:
  1. *Pass 1 (Utama)*: Mencocokkan data berdasarkan **NISN** (10 digit numerik unik).
  2. *Pass 2 (Sekunder)*: Jika NISN kosong pada salah satu sisi, mencocokkan berdasarkan **NIK** (16 digit numerik).
- **Klasifikasi Status Rekonsiliasi**:
  1. `MATCHED_IDENTICAL`: Ditemukan di kedua sisi dengan atribut Nama, NISN, dan Rombel cocok.
  2. `MATCHED_DIFF`: Ditemukan di kedua sisi tetapi terdapat perbedaan nilai atribut (misal: penulisan nama berbeda, NISN berbeda, atau penempatan rombel berbeda).
  3. `LOCAL_ONLY`: Siswa aktif tercatat di sistem sekolah lokal, namun tidak ada di berkas Dapodik.
  4. `DAPODIK_ONLY`: Siswa terdaftar di berkas Dapodik, namun belum ada di sistem sekolah lokal.

### D5 — Kontrak API Subfase 11b
Menyediakan 6 endpoint di bawah middleware `auth:sanctum`:
1. `GET /api/reports/school-statistics`: Ringkasan agregat multi-tahun (tren siswa 3–5 tahun ajaran, tren mutasi, rasio gender, pegawai).
2. `GET /api/reports/demographics`: Sebaran demografi siswa aktif (gender, agama, tingkat kelas, rentang usia).
3. `GET /api/reports/dapodik-readiness`: Audit kesiapan atribut identitas Dapodik siswa dan PTK (persentase lengkap, daftar field kosong kritis).
4. `POST /api/reports/dapodik-reconciliation/compare`: Mengunggah berkas CSV Dapodik untuk menghasilkan analisis komparasi (*dry-run in-memory*).
5. `GET /api/reports/school-statistics/export`: Mengunduh berkas CSV rekap statistik sekolah multi-tahun.
6. `POST /api/reports/dapodik-reconciliation/export`: Mengunduh berkas CSV hasil audit rekonsiliasi Dapodik.

### D6 — Otorisasi & Pembatasan Hak Akses Berbasis Peran
- Endpoint laporan sekolah dan demografi (`/api/reports/school-statistics`, `/api/reports/demographics`, `/api/reports/dapodik-readiness`) dapat diakses oleh pemegang ability `students.view` + `employees.view` (yaitu **`admin-sistem`**, **`operator-sekolah`**, **`kepala-sekolah`**, dan **`wakil-kesiswaan`**).
- Fitur rekonsiliasi Dapodik (`/api/reports/dapodik-reconciliation/*`) khusus diperuntukkan bagi pengelola operasional data: **`admin-sistem`** dan **`operator-sekolah`**.
- Peran **`guru`** dan staf umum ditolak **`403 Forbidden`** pada seluruh endpoint laporan manajerial dan rekonsiliasi ini.

### D7 — Desain Antarmuka Pengguna (UI) `/public/reports/school`
Menyediakan halaman mandiri terpadu **Laporan & Rekonsiliasi Sekolah** di bawah menu navigasi analitik, memiliki 3 tab terorganisir:
- **Tab 1: Statistik Multi-Tahun**: Visualisasi grafik tren siswa per tahun, tren mutasi, dan rekap komposisi pegawai, dilengkapi filter rentang tahun ajaran dan tombol Ekspor CSV.
- **Tab 2: Demografi & Kesiapan Profil**: Tabel rekap siswa per tingkat, gender, agama, serta persentase kepatuhan atribut Dapodik.
- **Tab 3: Rekonsiliasi Dapodik**: Area drag-and-drop unggah file CSV Dapodik, kartu ringkasan hasil (*Matched, Diff, Local Only, Dapodik Only*), filter status hasil, tabel perbandingan visual dua kolom (*Lokal vs Dapodik*), dan tombol Ekspor Hasil Rekonsiliasi CSV.

### D8 — Ketahanan Kinerja & Pemrosesan Berkas Streaming
- Kueri SQL multi-tahun menggunakan agregasi `COUNT`, `GROUP BY`, dan indeks komposit yang sudah ada pada tabel `student_rombels`, `school_years`, dan `student_mutations`.
- Berkas CSV Dapodik diproses secara *streaming* baris demi baris melalui generator PHP untuk menjaga konsumsi memori tetap rendah (< 32 MB untuk 2.000 baris).

### D9 — Standar Ekspor CSV & Kompatibilitas Excel
- Seluruh ekspor CSV menyertakan UTF-8 BOM (`\xEF\xBB\xBF`) agar karakter khusus dan tanda baca terbaca dengan rapi di Microsoft Excel.
- Nilai numerik sensitif (NISN, NIK, NIS) diformat sebagai teks dengan tanda kutip untuk mencegah pemotongan angka nol di awal atau konversi otomatis ke notasi ilmiah oleh spreadsheet viewer.
- Header respons menyertakan `Content-Type: text/csv; charset=UTF-8`, `Cache-Control: no-cache, private`, dan `X-Content-Type-Options: nosniff`.

### D10 — UAT Additive `.uat-phase10` & Kepatuhan DEC-27
- Memanfaatkan lingkungan UAT terisolasi `.uat-phase10` yang telah stabil dengan data lintas tahun ajaran (2024/2025 dan 2026/2027).
- Menyiapkan berkas CSV sampel Dapodik sintetis untuk pengujian skenario rekonsiliasi (`sample-dapodik-sync.csv`).
- Menambahkan subperintah `probe-reports-school` pada `scripts/phase10-uat.php`.
- Sesuai aturan **DEC-27**: Port 18028 dan 14028 dipastikan dihentikan kembali setelah pengujian dan kedua port berstatus bebas.

---

## 4. Rincian Teknis & Arsitektur

### 4.1 Backend (Laravel API)
1. **Service Layer**:
   - `app/Services/Reports/SchoolStatisticsReportService.php`:
     - `getMultiYearTrends(int $limitYears = 5): array`: Mengagregasikan total siswa unik, sebaran gender (L/P), tingkat rombel, mutasi (masuk/keluar), dan rasio guru/siswa per tahun ajaran.
     - `getDemographics(int $schoolYearId): array`: Sebaran gender per rombel, sebaran agama, dan sebaran umur siswa.
     - `getDapodikReadiness(int $schoolYearId): array`: Mengaudit kelengkapan NIK, NISN, No KK, Ibu Kandung, Tempat/Tanggal Lahir menggunakan evaluator terstandarisasi.
     - `exportMultiYearTrendsCsv(int $limitYears = 5): StreamedResponse`.
   - `app/Services/Reports/DapodikReconciliationService.php`:
     - `reconcileCsv(UploadedFile $file, int $schoolYearId): array`: Membaca CSV, mencocokkan dengan data lokal `Student` & `StudentRombel`, mengembalikan ringkasan statistik dan daftar detail baris hasil perbandingan.
     - `exportReconciliationCsv(array $reconciliationResults): StreamedResponse`.

2. **Controller Layer**:
   - `app/Http/Controllers/Reports/SchoolReportController.php`:
     - `schoolStatistics(Request $request)`
     - `demographics(Request $request)`
     - `dapodikReadiness(Request $request)`
     - `exportSchoolStatistics(Request $request)`
   - `app/Http/Controllers/Reports/DapodikReconciliationController.php`:
     - `compare(Request $request)`
     - `exportReconciliation(Request $request)`

3. **Routing (`routes/api.php`)**:
   ```php
   Route::prefix('reports')->middleware(['auth:sanctum'])->group(function () {
       Route::get('/school-statistics', [SchoolReportController::class, 'schoolStatistics'])
           ->middleware('can:students.view');
       Route::get('/demographics', [SchoolReportController::class, 'demographics'])
           ->middleware('can:students.view');
       Route::get('/dapodik-readiness', [SchoolReportController::class, 'dapodikReadiness'])
           ->middleware('can:students.view');
       Route::get('/school-statistics/export', [SchoolReportController::class, 'exportSchoolStatistics'])
           ->middleware('can:students.view');

       Route::post('/dapodik-reconciliation/compare', [DapodikReconciliationController::class, 'compare'])
           ->middleware('can:students.import');
       Route::post('/dapodik-reconciliation/export', [DapodikReconciliationController::class, 'exportReconciliation'])
           ->middleware('can:students.import');
   });
   ```

### 4.2 Frontend (Vue SPA)
1. **Views & Components (`vue-app/src/views/reports/` & `components/reports/`)**:
   - `SchoolReportsView.vue`: Halaman utama dengan PrimeVue `Tabs` / `TabView` (Statistik Multi-Tahun, Demografi & Kesiapan Profil, Rekonsiliasi Dapodik).
   - `MultiYearTrendsTab.vue`: Grafik tren batang & garis (Chart.js via PrimeVue Chart), tabel rekapitulasi angka per tahun ajaran, tombol Ekspor CSV.
   - `DemographicsTab.vue`: Grafik donat sebaran gender & agama, tabel siswa per tingkat kelas, dan kartu indikator kesiapan Dapodik.
   - `DapodikReconciliationTab.vue`: Drag-and-drop file upload CSV, kartu metrik komparasi (Total Diunggah, Cocok Identik, Beda Atribut, Hanya di Lokal, Hanya di Dapodik), filter tab status, tabel perbandingan visual kolom ganda (*Data Lokal vs Data Dapodik*), dan tombol Ekspor Hasil Rekonsiliasi.
2. **Navigasi Menu (`AppMenu.vue`)**:
   - Menambahkan menu `School Reports` di bawah grup navigasi yang relevan dengan izin akses `students.view`.

---

## 5. Rencana Pengujian & Verifikasi

### 5.1 Backend Feature Test Suite (`SchoolReportApiTest.php`)
- `T1`: Pengguna dengan izin `students.view` dapat mengambil data statistik multi-tahun (`200 OK`).
- `T2`: Guru tanpa izin manajerial ditolak saat mengakses laporan manajerial sekolah (`403 Forbidden`).
- `T3`: Data demografi siswa per rombel dan agama terhitung akurat sesuai database fixture (`200 OK`).
- `T4`: Audit kesiapan Dapodik mengidentifikasi siswa dengan NIK/NISN kosong atau tidak valid secara tepat (`200 OK`).
- `T5`: Ekspor CSV statistik multi-tahun mengembalikan header `text/csv`, ber-BOM UTF-8, dan memuat kolom tahun ajaran.
- `T6`: Unggah CSV Dapodik tanpa file mengembalikan error validasi `422 Unprocessable Entity`.
- `T7`: Unggah CSV Dapodik melebihi batas ukuran 5 MB ditolak `422`.
- `T8`: Rekonsiliasi CSV Dapodik berhasil mengidentifikasi siswa `MATCHED_IDENTICAL` secara presisi.
- `T9`: Rekonsiliasi CSV Dapodik mendeteksi siswa `MATCHED_DIFF` saat terdapat perbedaan nama atau rombel.
- `T10`: Rekonsiliasi CSV Dapodik mengklasifikasikan siswa lokal yang tidak tercantum di file sebagai `LOCAL_ONLY`.
- `T11`: Rekonsiliasi CSV Dapodik mengklasifikasikan siswa di file yang tidak ada di database sebagai `DAPODIK_ONLY`.
- `T12`: Ekspor CSV hasil rekonsiliasi Dapodik menghasilkan berkas perbandingan yang valid dengan format teks aman Excel.

### 5.2 Standar Kualitas Kode & Build
- `vendor/bin/pint --test`: 0 format issues.
- `npm run lint` di `vue-app`: 0 errors, 0 warnings.
- `npm run build` di `vue-app`: Build produksi sukses tanpa kompilasi gagal.
- Suite regresi penuh backend: Memastikan 654+ tes lama tetap PASS (zero regression).

---

## 6. Rencana Checklist Review Manual Pemilik (#1–#18)

Checklist pengujian manual yang akan disediakan di `reports/feature-update/PHASE-11B-MANUAL-REVIEW.md`:

| No | Skenario Pengujian | Ekspektasi |
|:---|:---|:---|
| **#1** | **Navigasi Halaman Laporan Sekolah** | Login `uat.admin` $\to$ Buka menu `School Reports` (`/public/reports/school`). Halaman termuat mulus dengan 3 tab. |
| **#2** | **Grafik Tren Siswa Multi-Tahun** | Tab 1 menampilkan grafik tren jumlah siswa 3–5 tahun ajaran terakhir dengan pemisahan gender L/P. |
| **#3** | **Tabel Rekapitulasi Siswa per Tahun** | Tabel di bawah grafik menampilkan angka riil total siswa, rombel, dan rasio gender per tahun ajaran. |
| **#4** | **Tren Mutasi Siswa Tahunan** | Menampilkan statistik siswa masuk vs siswa keluar per tahun ajaran. |
| **#5** | **Tren Komposisi Pegawai (PTK)** | Menampilkan perbandingan jumlah guru (Pendidik) dan staf (Tenaga Kependidikan) per periode. |
| **#6** | **Ekspor CSV Statistik Multi-Tahun** | Klik tombol *"Ekspor CSV"* $\to$ Berkas CSV terunduh, ber-BOM UTF-8, terbuka rapi di Excel tanpa teks rusak. |
| **#7** | **Visualisasi Demografi Siswa (Tab 2)** | Tab 2 menampilkan grafik donat sebaran gender (L/P) dan sebaran agama siswa aktif. |
| **#8** | **Tabel Sebaran Tingkat Kelas & Usia** | Menampilkan rekapitulasi siswa per tingkat rombel (X, XI, XII) dan kelompok umur. |
| **#9** | **Indikator Kesiapan Profil Dapodik** | Kartu persentase kelengkapan data Dapodik menampilkan skor kesiapan data sekolah (NIK, NISN, No KK, Ibu). |
| **#10** | **Daftar Field Identitas Belum Lengkap** | Rincian jumlah siswa yang belum melengkapi NIK valid atau NISN valid tampil transparan dengan link audit. |
| **#11** | **Antarmuka Rekonsiliasi Dapodik (Tab 3)**| Tab 3 menampilkan area unggah berkas CSV Dapodik dengan instruksi format yang jelas. |
| **#12** | **Validasi Unggah Berkas Dapodik** | Unggah berkas bukan CSV atau berukuran >5 MB ditolak secara elegan dengan pesan informatif. |
| **#13** | **Eksekusi Analisis Rekonsiliasi CSV** | Unggah file sampel Dapodik $\to$ Sistem menganalisis dan menampilkan kartu ringkasan hasil (*Matched, Diff, Local Only, Dapodik Only*). |
| **#14** | **Tabel Komparasi Siswa Cocok & Beda Atribut** | Tabel menampilkan baris siswa yang cocok, dengan penyorotan warna (*badge*) pada kolom yang berbeda (mis. nama/rombel). |
| **#15** | **Penyaringan Kategori Rekonsiliasi** | Filter tab/dropdown (*Semua, Cocok, Beda Data, Hanya Lokal, Hanya Dapodik*) menyaring baris tabel dengan responsif. |
| **#16** | **Ekspor CSV Laporan Rekonsiliasi** | Klik *"Ekspor Hasil Rekonsiliasi"* $\to$ Berkas CSV komparasi terunduh dengan kolom *Status, Data Lokal, Data Dapodik*. |
| **#17** | **Verifikasi Hak Akses Guru (`uat.guru`)** | Login sebagai `uat.guru` $\to$ Menu `School Reports` tidak tampil atau akses URL langsung ditolak `403 Forbidden`. |
| **#18** | **Preservasi Port & Integritas Lingkungan** | Server UAT dihentikan per DEC-27, port 18028 & 14028 bebas (`PORTS_18028_14028_FREE`), Apache/MariaDB utuh. |

---

## 7. Rencana Langkah Kerja Implementasi (S1–S8)

Setelah pemilik menyetujui usulan keputusan **D1–D10**:
- **S1 (Backend Services)**: Implementasi `SchoolStatisticsReportService` dan `DapodikReconciliationService`.
- **S2 (Backend Controllers & Routes)**: Implementasi `SchoolReportController`, `DapodikReconciliationController`, dan pendaftaran 6 route di `routes/api.php`.
- **S3 (Backend Tests)**: Pembuatan feature test `SchoolReportApiTest.php` mencakup 12 skenario pengujian (`T1–T12`).
- **S4 (Frontend Components)**: Pembuatan `MultiYearTrendsTab.vue`, `DemographicsTab.vue`, `DapodikReconciliationTab.vue`, dan integrasi pada `SchoolReportsView.vue`.
- **S5 (Navigation & Routing)**: Pendaftaran rute `/public/reports/school` pada Vue router dan penambahan menu di `AppMenu.vue`.
- **S6 (Code Quality & Build)**: Eksekusi Laravel Pint, ESLint, dan Vite production build (`npm run build`).
- **S7 (UAT Probe & Testing Data)**: Penambahan sampel CSV Dapodik sintetis dan probe HTTP otomatis di `scripts/phase10-uat.php`.
- **S8 (Documentation & Handoff)**: Penyusunan laporan teknis `PHASE-11B-REPORT.md`, panduan review manual `PHASE-11B-MANUAL-REVIEW.md`, dan sinkronisasi seluruh dokumen pelacak proyek.

---

## 8. Analisis Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|:---|:---|:---|
| File CSV Dapodik memiliki variasi nama kolom header | Pencocokan data gagal mendeteksi kolom | Mengimplementasikan normalisasi header case-insensitive dan kamus sinonim nama kolom umum Dapodik (mis: `nama_siswa` / `nama` / `nama_lengkap`). |
| Konsumsi memori tinggi pada berkas CSV besar | Server timeout atau memory exhaustion | Menggunakan PHP streaming generator baris demi baris, membatasi berkas maksimal 5 MB dan 2.000 baris. |
| Akses data rekap sekolah oleh pengguna tidak berwenang | Kebocoran data agregat institusi | Menegakkan middleware izin ketat `students.view` + `employees.view` di backend; peran guru dan staf umum ditolak `403`. |
| Inkonsistensi data lintas tahun ajaran | Angka statistik multi-tahun tidak akurat | Membaca riwayat penempatan rombel `student_rombels` berelasi ke `school_years` dengan penanganan tahun aktif maupun terkunci. |

---

## 9. Permohonan Persetujuan Pemilik

Paket rencana ini **hanya dokumentasi teknis**. Implementasi kode tidak akan dijalankan sebelum ada persetujuan resmi dari pemilik atas usulan **DEC-43 (D1–D10)** dan penutupan gerbang **DEC-12**.

Bila Bapak/Ibu menyetujui rancangan di atas, mohon berkenan memberikan persetujuan dengan format:
> *"Saya setuju D1-D10"*
