# Fase 10 (Subfase 10c) — Rencana Laporan Belum Mengisi Jurnal

- **Tanggal:** 2026-11-03 (WIB)
- **Cabang:** `main`
- **Baseline audit:** `35728bb` (pencatatan penerimaan pemilik Subfase 10b `#1–#18` di atas paket implementasi `df9f925` dan persetujuan `1c2c48c`)
- **Status:** **PLAN — OWNER APPROVED (D1–D9, 2026-11-03 WIB; pernyataan `"Saya setuju D1-D9"`) di bawah DEC-41 APPROVED**
- **Keputusan:** **DEC-41 (APPROVED, 2026-11-03 WIB)** — Arsitektur Subfase 10c Laporan Kepatuhan & Sesi Belum Mengisi Jurnal, Rekap per Guru, Drilldown Sesi Kosong/Overdue, Ekspor CSV, serta Pembatasan Hak Akses
- **Cakupan langkah ini:** **Pencatatan persetujuan pemilik atas rencana Subfase 10c**. Implementasi teknis langkah S1–S8 diotorisasi dan dikerjakan mulai langkah berikutnya.

---

## 1. Posisi dan Prasyarat

| Aspek | Status fakta |
|---|---|
| Fase 00–09b | **COMPLETE & ACCEPTED** (seluruh fase fondasi, pendataan, siklus akademik, persuratan, perizinan, dan arsip dokumen selesai dan diterima pemilik). |
| Subfase 10a | **COMPLETE & ACCEPTED** (`c8be397`, diterima pemilik `#1–#18` pada `a304df8`; penugasan mengajar, jadwal mingguan, bentrok tiga dimensi, dan exception kalender). |
| Subfase 10b | **COMPLETE & ACCEPTED** (`df9f925`, diterima pemilik `#1–#18` pada `35728bb` 2026-11-03 WIB; tabel `teaching_journals`, evaluasi sesi efektif harian/rentang, `own assignment`, idempotent retry, jendela 7 hari WIB). Server UAT `.uat-phase10` `18028`/`14028` telah dihentikan sesuai `DEC-27` dan data 8 akun / 66 jurnal dipertahankan. |
| Fase 10 di roadmap | [`roadmap/phases/PHASE-10-TEACHING.md`](../../roadmap/phases/PHASE-10-TEACHING.md): **10a** Penugasan dan jadwal (**APPROVED**), **10b** Sesi dan jurnal (**APPROVED**), **10c** Laporan belum mengisi jurnal (**NEXT**). |
| Kebutuhan fungsional | [`docs/REQUIREMENTS.md`](../../docs/REQUIREMENTS.md) **FR-12**: *"Penugasan guru-matpel-rombel-periode, jadwal dengan deteksi bentrok, jurnal per sesi, laporan belum diisi."* |
| Kriteria penerimaan 10c | Dari `PHASE-10-TEACHING.md` §10c: *"Guru tanpa sesi tidak dianggap belum mengisi; cancelled/libur dikecualikan; count cocok fixture; akses laporan terbatas."* |
| Gerbang keputusan | **`DEC-10`** telah **CLOSED** melalui **`DEC-39`** (`D3`, `D5`, `D8`): batas waktu pengisian jurnal oleh guru pengampu **`journal_grace_days = 7` hari kalender WIB** (`today_wib - 7 <= session_date <= today_wib`). Setelah 7 hari, sesi tercatat sebagai *overdue missing* yang hanya dapat diisi/dikoreksi oleh pemegang `teaching.manage` dengan alasan koreksi wajib. |

---

## 2. Temuan Audit Fakta Kode (`35728bb`)

Audit langsung terhadap kode sumber pada `laravel-api/` dan `vue-app/` menunjukkan kondisi aktual berikut:

1. **Mesin Evaluasi Sesi Deterministik 10b Sudah Tersedia dan Teruji:**
   - [`TeachingSessionJournalService.php`](../../laravel-api/app/Services/TeachingSessionJournalService.php) sudah memiliki logika komputasi sesi deterministik harian/rentang (`listSessions`), relasi jurnal, deteksi exception (`holiday`, `cancelled`, `rescheduled`), dan penghitungan status `effective`, `has_journal`, serta `is_missing`.
   - Namun, metode `listSessions` saat ini dirancang untuk lembar kerja harian/mingguan guru (maksimal 14 hari) dan berorientasi pada daftar per-sesi.
   - **Kebutuhan 10c:** Subfase 10c membutuhkan laporan analitik agregat yang mampu merangkum kepatuhan per guru (`by_teacher`), ringkasan KPI sekolah (`summary`), rincian sesi belum mengisi (`drilldown`), dan ekspor CSV, dengan rentang waktu yang lebih luas (mis. 1 bulan s.d. 90 hari) tanpa membebani memori.

2. **Aturan Denominator dan Pengecualian Akurat:**
   - Kriteria penerimaan menegaskan bahwa guru yang tidak memiliki sesi mengajar terjadwal pada rentang tanggal tersebut **tidak boleh dianggap sebagai guru yang belum mengisi jurnal** (denominator `expected_sessions = 0`, tidak masuk hitungan "tidak patuh").
   - Sesi pada hari libur sekolah/rombel (`holiday`), sesi yang dibatalkan (`cancelled`), dan sesi yang dipindahkan keluar (`rescheduled_out`) wajib dikeluarkan secara otomatis dari penyebut (*denominator*).
   - Sesi pengganti (`rescheduled`) yang masuk pada tanggal tujuan wajib dimasukkan ke penyebut pada tanggal tujuan di bawah tanggung jawab guru efektif (`substitute_employee_id ?? assignment.employee_id`).

3. **Status Keterlambatan Sesi Belum Mengisi (Grace Window vs Overdue):**
   - Berdasarkan `DEC-10` dan `DEC-39 D3`, guru memiliki batas waktu 7 hari kalender WIB untuk mengisi jurnal.
   - Pada laporan 10c, sesi belum mengisi perlu dipisahkan menjadi dua kelompok yang jelas:
     - `missing_within_grace`: belum diisi, namun masih dalam batas 7 hari WIB. Guru penugasan masih dapat langsung mengisi secara mandiri.
     - `missing_overdue`: belum diisi dan sudah melewati batas 7 hari WIB. Guru tidak dapat lagi mengisi mandiri; memerlukan koreksi/pengisian oleh pengelola (`teaching.manage`) dengan `correction_reason` wajib.

4. **Pembatasan Hak Akses (RBAC) Laporan:**
   - Pengelola (`teaching.manage` — `admin-sistem`, `operator-sekolah`) dan pimpinan (`kepala-sekolah`, `wakil-kesiswaan` dengan `teaching.view`): berhak melihat rekap seluruh guru sekolah (`scope=all`), melihat rincian per guru, dan mengunduh laporan CSV.
   - Guru pengajar (`guru` dengan `teaching.view`, tanpa `teaching.manage`, tertaut ke `employees.user_id`): hanya boleh melihat rekapitulasi dan rincian sesinya sendiri (`scope=mine` dipaksakan oleh server). Guru tidak diizinkan melihat daftar kepatuhan rekan guru lainnya.
   - Staf non-pengajar tanpa `teaching.view`: ditolak dengan `403 Forbidden`.

5. **Kesiapan Ekspor CSV:**
   - Pola ekspor CSV aplikasi sekolah (seperti pada `EmployeeExportController` dan `StudentExportController`) menggunakan streaming aman (`response()->streamDownload()`), UTF-8 BOM (`\xEF\xBB\xBF`), header `nosniff`, `Content-Disposition`, dan batasan jumlah baris maksimum untuk mencegah kehabisan memori.

---

## 3. Usulan Keputusan Pemilik (`D1–D9` / `DEC-41 PROPOSED`)

Setiap butir dilengkapi rekomendasi tegas agar pemilik cukup menjawab **`"Saya setuju D1-D9."`** atau menunjuk butir `D#` yang ingin disesuaikan.

### D1 — Cakupan Subfase 10c: Laporan Belum Mengisi Jurnal Saja
- **Rekomendasi:**
  1. Fokuskan paket ini sepenuhnya pada **Subfase 10c — Laporan Belum Mengisi Jurnal**.
  2. Cakupan fungsional mencakup: ringkasan kepatuhan sekolah, tabel agregasi kepatuhan per guru, rincian (*drilldown*) sesi belum mengisi jurnal, filter fleksibel (periode, tanggal, kelas, mata pelajaran, guru, status), dan ekspor CSV.
  3. **Di luar cakupan (Non-goals 10c):** pengiriman notifikasi/pengingat otomatis (WhatsApp/Email/SMS gateway), modul absensi kehadiran siswa, penilaian/rapor, dan mutasi data operasional.

### D2 — Tanpa Migrasi Database Baru (Zero Schema Change)
- **Rekomendasi:**
  1. Subfase 10c bersifat murni analitik dan pelaporan (read-only); seluruh data dihitung dari tabel yang telah stabil pada 10a dan 10b (`teaching_assignments`, `teaching_schedules`, `teaching_schedule_exceptions`, dan `teaching_journals`).
  2. **Tidak ada migrasi database, tabel baru, kolom baru, ataupun perubahan skema DDL.**

### D3 — Mesin Evaluasi Sesi Deterministik & Aturan Denominator Akurat
- **Rekomendasi:**
  1. Gunakan service analitik khusus [`TeachingReportService`](../../laravel-api/app/Services/TeachingReportService.php) (atau metode analitik pada `TeachingSessionJournalService`) yang mengevaluasi sesi terjadwal secara deterministik pada rentang tanggal `date_from..date_to`:
     - **Penyebut (`expected_sessions`):** Hanya sesi yang berstatus `effective` pada hari aktif (Senin..Sabtu) dalam tahun ajaran & semester yang dipilih.
     - **Pengecualian otomatis:** Sesi pada hari libur sekolah/rombel (`holiday`), sesi dibatalkan (`cancelled`), dan sesi dipindahkan keluar (`rescheduled_out`) **dikeluarkan dari penyebut**.
     - **Sesi pengganti (`rescheduled`):** Dimasukkan ke penyebut pada tanggal pengganti di bawah tanggung jawab guru efektif (`substitute_employee_id ?? assignment.employee_id`).
  2. **Aturan Guru Tanpa Sesi:** Guru yang memiliki 0 sesi efektif dalam rentang waktu terpilih **tidak dianggap belum mengisi** (`expected_sessions = 0`, `recorded_journals = 0`, `missing_journals = 0`, `compliance_percentage = 100.0` atau `null/N/A`, dan dikeluarkan dari daftar peringatan "guru menunggak jurnal").
  3. **Persamaan Konsistensi Fixture:** Untuk setiap guru dan total sekolah, berlaku rumus eksak:
     $$\text{expected\_sessions} = \text{recorded\_journals} + \text{missing\_journals}$$
     $$\text{compliance\_percentage} = \begin{cases} \text{round}\left(\frac{\text{recorded\_journals}}{\text{expected\_sessions}} \times 100, 1\right) & \text{jika } \text{expected\_sessions} > 0 \\ 100.0 & \text{jika } \text{expected\_sessions} = 0 \end{cases}$$

### D4 — Metrik Kepatuhan & Segmentasi Status Sesi Belum Mengisi
- **Rekomendasi:**
  1. Sesi yang belum memiliki baris pada `teaching_journals` disegmentasikan berdasarkan jendela waktu `journal_grace_days = 7` hari kalender WIB:
     - **`missing_within_grace`:** `session_date >= today_wib - 7 days`. Status: Masih dapat diisi secara mandiri oleh guru penugasan melalui tautan langsung ke form 10b.
     - **`missing_overdue`:** `session_date < today_wib - 7 days`. Status: Telah terlewat (kadaluwarsa). Guru ditolak bila mencoba mengisi mandiri; memerlukan intervensi/koreksi oleh pemegang `teaching.manage` dengan alasan koreksi wajib.
  2. Ringkasan KPI sekolah menyajikan: Total Sesi Terjadwal Efektif, Total Jurnal Terisi, Total Belum Mengisi (dengan rincian Dalam Grace Period vs Terlewat), dan Persentase Kepatuhan Global Sekolah.

### D5 — Batas Rentang Tanggal & Validasi Parameter
- **Rekomendasi:**
  1. Parameter wajib: `school_year_id` dan `semester_id`.
  2. Parameter rentang tanggal: `date_from` dan `date_to` (format `YYYY-MM-DD`, zona waktu WIB):
     - Default bila tidak diisi: awal bulan berjalan s.d. hari ini (atau 30 hari kalender terakhir s.d. hari ini bila di awal bulan).
     - Validasi: `date_to >= date_from`, kedua tanggal berada di dalam rentang kalender tahun ajaran terpilih (`start_date..end_date`).
     - Batas maksimum rentang: dibatasi maksimum **90 hari kalender** per kueri untuk menjaga efisiensi memori dan waktu respons responsif. Kueri melebihi 90 hari ditolak dengan validasi `422 Unprocessable Entity`.

### D6 — Otorisasi & Pembatasan Akses Berbasis Peran (RBAC)
- **Rekomendasi:**
  1. Seluruh endpoint laporan pembelajaran dilindungi oleh middleware `can:teaching.view`.
  2. **Pengelola & Pimpinan Sekolah:** Pengguna dengan `teaching.manage` (`admin-sistem`, `operator-sekolah`) atau pimpinan sekolah (`kepala-sekolah`, `wakil-kesiswaan` dengan `teaching.view`):
     - Berhak mengakses laporan skala sekolah (`scope=all`).
     - Dapat melihat tabel rekapitulasi seluruh guru, memfilter per guru, rombel, mapel, dan status.
     - Berhak mengunduh ekspor CSV seluruh data laporan.
  3. **Guru Pengajar:** Pengguna dengan role `guru` (memiliki `teaching.view` tanpa `teaching.manage`, tertaut ke profil pegawai via `employees.user_id`):
     - Server memaksakan filter `scope=mine` dan `employee_id = self_employee.id`.
     - Guru hanya dapat melihat ringkasan kepatuhannya sendiri dan rincian sesi miliknya yang belum diisi. Guru tidak dapat melihat data kepatuhan atau daftar sesi guru lain.
  4. Pengguna tanpa kemampuan `teaching.view` (mis. staf perpustakaan/tata usaha umum tanpa izin): ditolak `403 Forbidden`.

### D7 — Kontrak API Laporan & Ekspor CSV
- **Rekomendasi:**
  1. Sediakan 2 endpoint baru di bawah prefix `/api/teaching/reports`:
     - **`GET /api/teaching/reports/missing-journals`**: Mengembalikan JSON berisi `summary`, daftar `by_teacher`, array `drilldown` sesi (dengan filter dan paginasi), dan `meta`.
     - **`GET /api/teaching/reports/missing-journals/export`**: Menghasilkan streaming unduhan file CSV (`Content-Type: text/csv; charset=UTF-8`, UTF-8 BOM, `nosniff`, `Content-Disposition: attachment; filename="teaching-missing-journals-YYYYMMDD_HHMMSS.csv"`).
  2. Format kolom CSV ekspor mencakup: Tanggal Sesi, Hari, Jam Ke, Waktu, Rombel/Kelas, Mata Pelajaran, Guru Pengampu, Guru Aktual/Pengganti, Status Sesi, Status Jurnal, Keterangan Batas Waktu (Masih dalam batas / Terlewat), dan Tanggal Pengisian (bila terisi).

### D8 — Desain Antarmuka Pengguna (UI) Menu & Halaman Laporan
- **Rekomendasi:**
  1. Buat tampilan Vue mandiri [`vue-app/src/views/teaching/TeachingReportsView.vue`](../../vue-app/src/views/teaching/TeachingReportsView.vue) pada rute `/public/teaching/reports` (alias `/teaching/reports`, guard `requiresAbility: 'teaching.view'`).
  2. Tambahkan sub-menu navigasi pada sidebar [`AppMenu.vue`](../../vue-app/src/layout/AppMenu.vue):
     - `Teaching -> Assignments & Timetable` (`/public/teaching/schedules`)
     - `Teaching -> Sessions & Journals` (`/public/teaching/journals`)
     - `Teaching -> Journal Reports` (`/public/teaching/reports`)
  3. Struktur tampilan:
     - **Bilah Filter:** Tahun Ajaran, Semester, Rentang Tanggal (dengan pintasan cepat: *7 Hari Terakhir*, *30 Hari Terakhir*, *Bulan Ini*), Rombel, Mata Pelajaran, Guru (hanya aktif untuk pengelola/pimpinan), dan Status (Semua, Belum Mengisi Saja, Terlewat Saja, Terisi).
     - **Kartu Ringkasan KPI:** Total Sesi Efektif, Jurnal Terisi, Belum Mengisi (Dalam Batas Waktu vs Terlewat), dan Persentase Kepatuhan.
     - **Dua Tab Konten:**
       - **Tab 1: Rekap Kepatuhan Guru (Teacher Compliance Summary):** Tabel daftar guru dengan jumlah sesi efektif, jurnal terisi, belum terisi, persentase kepatuhan dengan progress bar visual, dan tombol "Lihat Rincian Sesi".
       - **Tab 2: Rincian Sesi (Sessions Drilldown):** Daftar sesi dengan status jurnal, tanggal batas pengisian, dan tombol aksi langsung "Isi Jurnal" bagi guru/operator yang membuka dialog pengisian jurnal 10b secara mulus.
     - **Tombol Ekspor CSV:** Tombol unduh laporan CSV yang menghormati seluruh filter yang sedang aktif.

### D9 — Strategi Verifikasi & Perluasan UAT `.uat-phase10`
- **Rekomendasi:**
  1. Gunakan lingkungan UAT terisolasi yang sudah aktif di `.uat-phase10` (`127.0.0.1:18028` / `127.0.0.1:14028`, cookie `school_phase10_session`).
  2. Data sintetis 10a dan 10b yang sudah ada (8 akun, 101 penugasan, 121 jadwal, 6 exception, 66 jurnal mengajar) dipertahankan tanpa reset.
  3. Tambahkan skrip verifikasi otomatis pada `scripts/phase10-uat.php` untuk memverifikasi akurasi perhitungan laporan skala sekolah, per-guru, drilldown, otorisasi `scope=mine` pada akun `uat.guru`, dan probe HTTP streaming ekspor CSV.
  4. Server UAT dijalankan sementara hanya selama verifikasi dan **wajib dihentikan kembali setelah pengujian** sesuai `DEC-27` (port 18028/14028 bebas). Database operasional MariaDB (port 3306) dan Apache (80/443) tidak disentuh.

---

## 4. Matriks Hak Akses & Penegakan Otorisasi (RBAC)

| Peran (Role) | Kemampuan | Cakupan Data (`scope`) | Hak Akses Laporan & Ekspor |
|---|---|---|---|
| `admin-sistem` | `teaching.manage`, `teaching.view` | `all` | Melihat rekapitulasi sekolah, seluruh guru, rincian seluruh sesi, dan mengunduh CSV. |
| `operator-sekolah` | `teaching.manage`, `teaching.view` | `all` | Melihat rekapitulasi sekolah, seluruh guru, rincian seluruh sesi, dan mengunduh CSV. |
| `kepala-sekolah` | `teaching.view` | `all` | Melihat rekapitulasi sekolah, seluruh guru, rincian sesi (read-only), dan mengunduh CSV. |
| `wakil-kesiswaan` | `teaching.view` | `all` | Melihat rekapitulasi sekolah, seluruh guru, rincian sesi (read-only), dan mengunduh CSV. |
| `guru` (tertaut ke pegawai) | `teaching.view` (tanpa `manage`) | `mine` (dipaksa server) | Hanya melihat ringkasan kepatuhannya sendiri dan rincian sesi miliknya. Ekspor CSV dibatasi hanya pada sesinya sendiri. Ditolak `403` jika mencoba meminta data guru lain. |
| Staf non-pengajar / Guest | Tanpa `teaching.view` | Tidak ada | Ditolak `403 Forbidden` / `401 Unauthorized`. |

---

## 5. Kontrak Permintaan & Respons API (`/api/teaching/reports/*`)

### 5.1. `GET /api/teaching/reports/missing-journals`

- **Middleware:** `auth:sanctum`, `can:teaching.view`
- **Query Parameters:**
  - `school_year_id` (required, integer, min:1)
  - `semester_id` (required, integer, min:1)
  - `date_from` (optional, date format `YYYY-MM-DD`, default: 30 hari ke belakang)
  - `date_to` (optional, date format `YYYY-MM-DD`, default: hari ini WIB)
  - `school_rombel_id` (optional, integer, min:1)
  - `subject_id` (optional, integer, min:1)
  - `employee_id` (optional, integer, min:1)
  - `status` (optional, string: `all` | `missing` | `missing_grace` | `missing_overdue` | `recorded`)
  - `scope` (optional, string: `all` | `mine`, dipaksa `mine` untuk guru)
  - `per_page` (optional, integer: 10, 20, 50, 100; default 20)
  - `page` (optional, integer, min:1)

- **Response `200 OK`:**
```json
{
  "success": true,
  "message": "Teaching journal compliance report retrieved.",
  "data": {
    "summary": {
      "total_teachers_with_sessions": 15,
      "total_expected_sessions": 240,
      "total_recorded_journals": 180,
      "total_missing_journals": 60,
      "total_missing_within_grace": 25,
      "total_missing_overdue": 35,
      "overall_compliance_percentage": 75.0,
      "total_excluded_slots": 18
    },
    "by_teacher": [
      {
        "employee_id": 12,
        "employee_name": "Budi Santoso, S.Pd.",
        "employee_nuptk": "1234567890123456",
        "primary_subject_name": "Matematika",
        "expected_sessions": 24,
        "recorded_journals": 20,
        "missing_journals": 4,
        "missing_within_grace": 3,
        "missing_overdue": 1,
        "compliance_percentage": 83.3
      }
    ],
    "drilldown": {
      "data": [
        {
          "schedule_id": 45,
          "assignment_id": 12,
          "session_date": "2026-10-28",
          "day_of_week": 3,
          "day_label": "Rabu",
          "period_start": 1,
          "period_end": 2,
          "scheduled_start_time": "07:30",
          "scheduled_end_time": "08:50",
          "room": "R.101",
          "school_rombel_id": 3,
          "school_rombel_name": "X-A",
          "subject_id": 5,
          "subject_name": "Matematika",
          "teacher_employee_id": 12,
          "teacher_name": "Budi Santoso, S.Pd.",
          "teacher_nuptk": "1234567890123456",
          "source_type": "regular",
          "has_journal": false,
          "journal_id": null,
          "is_missing": true,
          "is_within_grace_window": true,
          "grace_deadline_date": "2026-11-04",
          "status_badge": "missing_grace",
          "can_record": true
        }
      ],
      "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 20,
        "total": 60
      }
    }
  },
  "meta": {
    "school_year_id": 1,
    "school_year_name": "2026/2027",
    "is_year_locked": false,
    "semester_id": 1,
    "semester_name": "Ganjil",
    "date_from": "2026-10-01",
    "date_to": "2026-10-31",
    "today_wib": "2026-11-03",
    "grace_days": 7,
    "grace_deadline_date": "2026-10-27",
    "scope": "all",
    "status_filter": "missing"
  }
}
```

### 5.2. `GET /api/teaching/reports/missing-journals/export`

- **Middleware:** `auth:sanctum`, `can:teaching.view`
- **Query Parameters:** sama dengan endpoint laporan di atas.
- **Response `200 OK`:** Streaming file CSV dengan header:
  - `Content-Type: text/csv; charset=UTF-8`
  - `Content-Disposition: attachment; filename="teaching-missing-journals-YYYYMMDD_HHMMSS.csv"`
  - `X-Content-Type-Options: nosniff`
  - Konten diawali UTF-8 BOM (`\xEF\xBB\xBF`)
  - Baris Header CSV:
    `"Session Date","Day","Period","Time","Class","Subject","Teacher","NUPTK","Source","Status","Journal Recorded","Overdue Status","Journal Topic"`

---

## 6. Desain Antarmuka Pengguna (UI) `/public/teaching/reports`

1. **Struktur Navigasi:**
   - URL: `/public/teaching/reports` (alias rute `/teaching/reports`)
   - Guard rute: `requiresAbility: 'teaching.view'`
   - Menu Sidebar: `Teaching` $\rightarrow$ `Journal Reports` dengan ikon diagram/laporan (`pi pi-chart-bar`).

2. **Komponen Halaman:**
   - **Header & Action Bar:** Judul *"Teaching Journal Compliance & Missing Reports"*, deskripsi singkat, tombol **Export CSV** (dengan status loading dan tooltips informatif), serta tombol refresh.
   - **Filter Panel:**
     - Tahun Ajaran & Semester (dropdown).
     - Rentang Tanggal (Datepicker ganda atau DateRangePicker) dilengkapi tombol pintas cepat: *7 Hari Terakhir*, *30 Hari Terakhir*, *Bulan Ini*.
     - Dropdown Filter Rombel, Mata Pelajaran, dan Guru (guru hanya bisa memilih dirinya sendiri atau dinonaktifkan untuk guru).
     - Filter Status Sesi: *Semua*, *Belum Mengisi Saja (Missing)*, *Dalam Batas Waktu (Grace)*, *Terlewat (Overdue)*, *Sudah Terisi (Recorded)*.
   - **Ringkasan Indikator KPI (Stat Cards):**
     1. **Total Sesi Efektif:** Total sesi yang wajib dilaksanakan (tidak termasuk libur/batal).
     2. **Jurnal Terisi:** Jumlah sesi yang sudah dibuatkan jurnal pembelajarannya.
     3. **Belum Mengisi (Dalam Batas):** Sesi belum terisi yang masih bisa diisi mandiri oleh guru.
     4. **Belum Mengisi (Terlewat):** Sesi belum terisi yang sudah lewat batas 7 hari WIB.
     5. **Tingkat Kepatuhan (%):** Progress bar kepatuhan dengan warna indikator dinamis (hijau $\ge 90\%$, kuning $70-89\%$, merah $< 70\%$).
   - **Tampilan Tab Ganda:**
     - **Tab 1: Rekap Kepatuhan per Guru:**
       - Tabel kolom: Nama Guru, NUPTK, Mata Pelajaran, Total Sesi, Terisi, Belum Mengisi, Batas Waktu Terlewat, % Kepatuhan (progress bar), Aksi ("Lihat Sesi").
       - Fitur klik baris guru otomatis menyaring tab rincian ke guru bersangkutan.
     - **Tab 2: Rincian Sesi Belum Mengisi (Drilldown):**
       - Tabel rincian per sesi: Tanggal, Hari, Jam Ke, Waktu, Kelas, Mata Pelajaran, Guru Pengampu, Status Jurnal, Sisa Waktu Pengisian.
       - Tombol aksi kontekstual:
         - Jika `can_record` bernilai `true`: Tombol *"Isi Jurnal"* yang membuka form dialog pengisian jurnal 10b.
         - Jika `missing_overdue`: Label merah *"Terlewat"* dengan keterangan bagi guru *"Hubungi operator untuk koreksi"* atau tombol koreksi bagi pemegang `teaching.manage`.

---

## 7. Matriks Pengujian & Verifikasi (T1–T14)

| Kode | Skenario Uji | Expected Result |
|---|---|---|
| **T1** | Denominator akurat: guru tanpa sesi mengajar terjadwal pada rentang tanggal | `expected_sessions = 0`, `missing_journals = 0`, tidak dimasukkan ke dalam daftar guru menunggak jurnal. |
| **T2** | Pengecualian hari libur (`holiday` sekolah maupun per-rombel) | Sesi pada tanggal libur dikeluarkan dari penyebut (`is_effective = false`, `status = holiday`), tidak dihitung missing. |
| **T3** | Pengecualian pembatalan sesi (`cancelled`) | Sesi yang dibatalkan dikeluarkan dari penyebut (`is_effective = false`, `status = cancelled`), tidak dihitung missing. |
| **T4** | Pengecualian pemindahan keluar (`rescheduled_out`) | Sesi reguler yang dipindahkan ke tanggal lain dikeluarkan dari penyebut tanggal asal. |
| **T5** | Penyertaan sesi pengganti (`rescheduled_in`) | Sesi pengganti dimasukkan ke penyebut pada tanggal pengganti di bawah guru pengganti/efektif. |
| **T6** | Pencocokan jurnal terisi | Sesi yang telah memiliki baris `teaching_journals` dihitung sebagai `recorded`, bukan `missing`. |
| **T7** | Deteksi sesi belum mengisi | Sesi efektif tanpa baris `teaching_journals` dihitung sebagai `missing`. |
| **T8** | Segmentasi jendela waktu (grace window vs overdue) | Sesi missing $\le 7$ hari WIB masuk `missing_within_grace`; sesi missing $> 7$ hari WIB masuk `missing_overdue`. |
| **T9** | Konsistensi matematika agregat | $\text{expected} = \text{recorded} + \text{missing}$ bernilai eksak pada ringkasan sekolah maupun rekap per guru. |
| **T10** | Filter data (rombel, mapel, guru, status) | Kueri menyaring data secara presisi sesuai kombinasi filter yang dikirimkan. |
| **T11** | Validasi rentang tanggal & batas 90 hari | `date_to < date_from` ditolak `422`; rentang $> 90$ hari ditolak `422`. |
| **T12** | Penegakan RBAC guru (`scope=mine`) | Pengguna peran `guru` dipaksa `scope=mine`; upaya membaca kepatuhan guru lain ditolak `403`. |
| **T13** | Streaming ekspor CSV | Endpoint ekspor mengalirkan file CSV ber-BOM UTF-8, baris header benar, data cocok dengan filter, header `nosniff`. |
| **T14** | Pembacaan tahun ajaran terkunci (`locked`) | Laporan tahun ajaran terkunci tetap dapat dibaca secara historis, tombol aksi pengisian dinonaktifkan (`422` jika dipaksa). |

---

## 8. Rencana Eksekusi Langkah Demi Langkah (S0–S8)

Setelah pemilik menyetujui keputusan **D1–D9**:

- **Langkah S0 — Persetujuan Rencana Pemilik:** Catat persetujuan pemilik sebagai **`DEC-41 APPROVED`** pada commit terpisah sebelum menulis kode.
- **Langkah S1 — Backend Service (`TeachingReportService.php`):**
  - Implementasikan komputasi agregasi laporan kepatuhan per guru dan sekolah.
  - Implementasikan evaluasi rincian sesi belum mengisi dengan segmentasi grace vs overdue.
  - Implementasikan generator streaming CSV untuk laporan kepatuhan.
- **Langkah S2 — Controller & Request (`TeachingReportController.php`):**
  - Buat `TeachingReportIndexRequest` untuk validasi filter dan batas rentang tanggal 90 hari.
  - Buat `TeachingReportController` dengan metode `missingJournals` dan `exportMissingJournals`.
- **Langkah S3 — Rute API & Kebijakan Akses (`routes/api.php`):**
  - Daftarkan route `GET /api/teaching/reports/missing-journals` dan `GET /api/teaching/reports/missing-journals/export` di bawah middleware `can:teaching.view`.
- **Langkah S4 — Frontend Vue View (`TeachingReportsView.vue`):**
  - Bangun halaman laporan dengan filter bar, kartu KPI, tab rekap guru, tab rincian sesi, tombol ekspor CSV, dan tombol aksi "Isi Jurnal".
  - Daftarkan rute `/public/teaching/reports` dan menu sidebar pada `AppMenu.vue`.
- **Langkah S5 — Pengujian Backend Otomatis (`TeachingReportApiTest.php`):**
  - Tulis tes fitur PHPUnit komprehensif mencakup skenario `T1–T14`.
- **Langkah S6 — Verifikasi Kualitas Kode (Pint, ESLint, Build):**
  - Jalankan Pint pada seluruh file PHP baru/diubah.
  - Jalankan ESLint pada komponen Vue baru.
  - Jalankan `npm run build` untuk memverifikasi kompilasi frontend tanpa error.
- **Langkah S7 — UAT Terisolasi `.uat-phase10`:**
  - Perluas `scripts/phase10-uat.php` dengan sub-perintah probe verifikasi laporan dan ekspor CSV.
  - Verifikasi respons HTTP lintas peran (`uat.admin`, `uat.operator`, `uat.kepsek`, `uat.guru`, `uat.guru2`).
  - Hentikan server UAT sesuai `DEC-27` dan pastikan port 18028/14028 bebas.
- **Langkah S8 — Laporan & Panduan Review:**
  - Susun `reports/feature-update/PHASE-10C-REPORT.md` dan `PHASE-10C-MANUAL-REVIEW.md`.

---

## 9. Lingkungan UAT Terisolasi `.uat-phase10` & Batasan Keamanan

- **Port UAT:** API `127.0.0.1:18028`, SPA `127.0.0.1:14028`, cookie `school_phase10_session`.
- **Integritas Server Operasional:**
  - **Dilarang keras menyentuh MariaDB operasional (port 3306), Apache operasional (port 80/443), atau `.env` utama.**
  - Seluruh pengujian UAT hanya berjalan di atas SQLite `.uat-phase10/database.sqlite`.
  - Server UAT wajib dimatikan segera setelah pengujian (`DEC-27`). Port `18028` dan `14028` wajib bebas pendengar saat checkpoint review.

---

## 10. Cara Memberikan Persetujuan

Bila pemilik menyetujui seluruh rancangan di atas tanpa perubahan, pemilik cukup membalas dengan:

> **"Saya setuju D1-D9"**

Bila ada butir yang ingin disesuaikan atau diubah (misalnya batas rentang tanggal, penamaan menu, atau format kolom ekspor), pemilik dapat menyebutkan nomor butir `D#` beserta penyesuaian yang diinginkan.
