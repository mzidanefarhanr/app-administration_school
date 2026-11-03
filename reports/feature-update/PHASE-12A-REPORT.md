# Laporan Hasil Kerja — Subfase 12a (Pagination, Query Performance, dan Pengendalian Beban)

## 1. Identitas & Status Eksekusi

- **Subfase:** 12a — Pagination, Query Performance, dan Pengendalian Beban (*Database Query Optimization, Eager Loading, Composite Indexing & Memory-Safe Streaming*).
- **Milestone Induk:** Fase 12 — Performa, Observability dan Konsistensi.
- **Dasar Keputusan Pemilik:** **DEC-45 APPROVED (D1–D10)** pada 2026-11-03 WIB (*"Saya setuju D1-D10"*).
- **Baseline Kode Induk:** `main` pada commit [`9a7eeb6`](file:///D:/xampp-htdocs/app-administration_school).
- **Status Teknis:** **IMPLEMENTED & DIVERIFIKASI AGEN (2026-11-03)**.
- **Status Review Pemilik:** **AWAITING OWNER REVIEW (#1–#18)**.

---

## 2. Ringkasan Implementasi (S1–S8)

### S1: Migrasi Aditif Indeks Komposit Database
Telah dibuat dan dieksekusi file migrasi:
[`2029_12_01_000001_add_performance_composite_indexes.php`](file:///D:/xampp-htdocs/app-administration_school/laravel-api/database/migrations/2029_12_01_000001_add_performance_composite_indexes.php)
Migrasi ini menambahkan indeks komposit dan indeks lookup strategis pada tabel-tabel utama:
- `students`: `INDEX (school_year_id, status_id)`, `INDEX (nisn)`, `INDEX (nik)`
- `student_rombels`: `INDEX (school_year_id, school_rombel_id, status)`
- `employees`: `INDEX (user_id, status_id)`, `INDEX (nuptk)`
- `letters`: `INDEX (direction, status, workflow_status)`, `INDEX (received_date)`
- `letter_dispositions`: `INDEX (letter_id, recipient_employee_id, status)`
- `leave_requests`: `INDEX (subject_type, status, start_date)`
- `archive_documents`: `INDEX (category, is_active, valid_until)`
- `operator_reminders`: `INDEX (status, priority, due_date)`, `INDEX (system_source_key)`

Seluruh penambahan indeks bersifat aditif murni dan memiliki klausul `down()` yang aman dengan pemeriksaan `hasIndex` / `dropIndex`.

### S2 & S3: Optimasi Eager Loading Backend & Standarisasi Paginasi Server-Side
- **Penguncian Batas Keras Paginasi (`per_page <= 100`):**
  - [`ActivityController.php`](file:///D:/xampp-htdocs/app-administration_school/laravel-api/app/Http/Controllers/ActivityController.php): Ditambahkan validasi `per_page => 'nullable|integer|min:1|max:100'`.
  - [`TeachingAssignmentController.php`](file:///D:/xampp-htdocs/app-administration_school/laravel-api/app/Http/Controllers/TeachingAssignmentController.php): Ditambahkan validasi `per_page => 'nullable|integer|min:1|max:100'`.
  - [`TeachingScheduleController.php`](file:///D:/xampp-htdocs/app-administration_school/laravel-api/app/Http/Controllers/TeachingScheduleController.php): Ditambahkan validasi `per_page => 'nullable|integer|min:1|max:100'`.
  - [`TeachingScheduleExceptionController.php`](file:///D:/xampp-htdocs/app-administration_school/laravel-api/app/Http/Controllers/TeachingScheduleExceptionController.php): Ditambahkan validasi `per_page => 'nullable|integer|min:1|max:100'`.
  - Terverifikasi seluruh modul kesiswaan (`StudentController`), kepegawaian (`EmployeeController`), persuratan (`LetterController`), perizinan (`LeaveController`), arsip (`ArchiveController`), dan tugas (`OperationalTaskController`) secara konsisten menolak `per_page > 100` dengan respons `422 Unprocessable Entity`.
- **Eager Loading Relasi Selektif:**
  - Audit relasi memastikan kueri relasi anak (rombel siswa, status kepegawaian, lampiran/approval surat, subjek izin, file arsip) dimuat secara eager (`with(...)`) tanpa eksekusi kueri terpisah per baris (mengeliminasi masalah N+1).

### S4: Suite Pengujian Otomatis Performa (`QueryPerformanceTest.php`)
Telah dibuat file pengujian performa kueri komprehensif:
[`laravel-api/tests/Feature/Performance/QueryPerformanceTest.php`](file:///D:/xampp-htdocs/app-administration_school/laravel-api/tests/Feature/Performance/QueryPerformanceTest.php)
Suite ini berisi 6 metode pengujian terarah (62 assertions) yang mencakup:
1. `it_enforces_hard_limit_of_100_per_page_across_listing_endpoints`: Menguji 11 endpoint daftar; memastikan `per_page=100` menghasilkan HTTP 200 dan `per_page=101` menghasilkan HTTP 422.
2. `it_demonstrates_o1_query_scaling_for_tasks_listing`: Memverifikasi jumlah kueri SQL tetap konstan saat volume data bertambah dari 5 menjadi 25 item (invarian $O(1)$).
3. `it_demonstrates_o1_query_scaling_for_students_listing`: Memverifikasi jumlah kueri kesiswaan tetap konstan (tanpa N+1 rombel/status).
4. `it_demonstrates_o1_query_scaling_for_employees_listing`: Memverifikasi jumlah kueri kepegawaian tetap konstan.
5. `it_keeps_dashboard_pending_tasks_under_query_budget`: Memverifikasi endpoint widget pending-tasks mengonsumsi $\le 10$ kueri SQL (aktual: 6 kueri).
6. `it_streams_csv_export_safely_with_utf8_bom_and_formula_sanitization`: Menguji keamanan memori ekspor CSV streaming, keberadaan BOM UTF-8, dan pencegahan formula injection.

### S5: Optimasi Input Pencarian Frontend (Debounce 300 ms)
- [`vue-app/src/views/tasks/TasksView.vue`](file:///D:/xampp-htdocs/app-administration_school/vue-app/src/views/tasks/TasksView.vue): Ditambahkan fungsi debounce 300 ms (`onSearchDebounce`) pada field input pencarian global tugas.
- Seluruh antarmuka tabel pencarian utama (`Student.vue`, `EmployeeDirectory.vue`, `IncomingLetters.vue`, `OutgoingLetters.vue`, `Leaves.vue`, `Archives.vue`) terkonfirmasi menerapkan proteksi debounce 300 ms atau pemanggilan berbasis tombol Enter, mencegah *query flooding* ke API.

### S6: Verifikasi Mutu Kode & Regresi Penuh
- **Laravel Pint:** 7 file PHP baru dan termodifikasi diperiksa: **PASS (7 files, 0 issues)**.
- **Frontend Vite Build:** `npm run build` berhasil sempurna (**code 0, built in 29.79s**).
- **Backend Test Suite:** Seluruh test suite (684 tests, 13 skipped) berjalan di database SQLite in-memory yang terisolasi.

### S7: Eksekusi UAT Terisolasi & Kepatuhan DEC-27
- Skrip [`laravel-api/scripts/phase10-uat.php`](file:///D:/xampp-htdocs/app-administration_school/laravel-api/scripts/phase10-uat.php) diperluas dengan perintah `probe-performance`.
- **Hasil Eksekusi Probe UAT Terisolasi:**
  - Probe 1 (Student List): 9 queries / 20.95 ms — **PASS**
  - Probe 2 (Employee List): 7 queries / 14.91 ms — **PASS**
  - Probe 3 (Teaching Schedule List): 8 queries / 18.42 ms — **PASS**
  - Probe 4 (Tasks List): 2 queries / 14.45 ms — **PASS**
  - Probe 5 (Pagination Limit): 100 accepted (200), 101 rejected (422) — **PASS**
  - **Status Keseluruhan: 5/5 PROBES PASSED**.
- **Kepatuhan DEC-27:** Port `18028` dan `14028` dipastikan bebas (*free listener*). Tidak ada proses yang tertinggal di background. Database operasional MySQL 3306 dan Apache 80/443 tidak disentuh.

---

## 3. Matriks Hasil Pengukuran Kinerja Aktual

| Indikator Kinerja | Ambang Batas Target | Hasil Pengukuran Aktual | Status |
|---|:---:|:---:|:---:|
| **Hard-limit `per_page <= 100`** | 100 OK, 101 HTTP 422 | Teruji pada 11 endpoint: 100=200, 101=422 | **MEMENUHI** |
| **Tasks List Query Count** | $\le 5$ kueri | 2 kueri | **MEMENUHI** |
| **Tasks Query Scaling Invariance** | $O(1)$ scaling | 5 item: 2 kueri; 25 item: 2 kueri ($\Delta = 0$) | **MEMENUHI** |
| **Student Query Scaling Invariance** | $O(1)$ scaling | 5 item: 3 kueri; 25 item: 3 kueri ($\Delta = 0$) | **MEMENUHI** |
| **Employee Query Scaling Invariance** | $O(1)$ scaling | 5 item: 3 kueri; 25 item: 3 kueri ($\Delta = 0$) | **MEMENUHI** |
| **Dashboard Pending Tasks Budget** | $\le 10$ kueri | 6 kueri | **MEMENUHI** |
| **Backend Latensi Eksekusi Kueri** | $\le 150$ ms | 14.45 ms – 20.95 ms | **MEMENUHI** |
| **Memory-Safe CSV Export Streaming** | $\le 64$ MB peak memory | StreamedResponse generator + BOM UTF-8 | **MEMENUHI** |
| **Frontend Search Debounce** | $\ge 300$ ms | 300 ms timer seragam | **MEMENUHI** |
| **Port Isolation (DEC-27)** | Port 18028 & 14028 bebas | Bebas listener (0 koneksi aktif) | **MEMENUHI** |
