# Fase 10 (Subfase 10b) — Rencana Sesi dan Jurnal Pembelajaran

- **Tanggal:** 2026-11-03 (WIB)
- **Cabang:** `main`
- **Baseline audit:** `a304df8` (pencatatan penerimaan pemilik Subfase 10a `#1–#18` di atas paket implementasi `c8be397`); paket rencana: `8ce345b`
- **Status:** **PLAN APPROVED — DEC-40 APPROVED (2026-11-03 WIB, berdasarkan pernyataan pemilik `"Saya setuju D1-D9"`); implementasi Subfase 10b diotorisasi**
- **Keputusan:** **DEC-40 (APPROVED, 2026-11-03 WIB)** — Arsitektur Subfase 10b Sesi Pembelajaran Efektif, Jurnal Mengajar (`teaching_journals`), Otorisasi `Own Assignment` vs `teaching.manage`, Idempotent Retry, dan Jendela Waktu Pengisian (`DEC-10`)
- **Cakupan langkah ini:** Persetujuan rencana D1–D9 dicatat terlebih dahulu; implementasi S1–S7 dieksekusi setelah commit persetujuan ini.

---

## 1. Posisi dan Prasyarat

| Aspek | Status fakta |
|---|---|
| Fase 00–09b | **COMPLETE & ACCEPTED** (terakhir Subfase 09b diterima pemilik `#1–#18` pada `f185ca6`). |
| Subfase 10a | **COMPLETE & ACCEPTED** (`c8be397`, diterima pemilik `#1–#18` pada `a304df8` 2026-11-03 WIB; server UAT `.uat-phase10` `18028`/`14028` telah dihentikan sesuai `DEC-27` dan data `101` penugasan, `121` jadwal, `6` exception dipertahankan). |
| Fase 10 di roadmap | [`roadmap/phases/PHASE-10-TEACHING.md`](../../roadmap/phases/PHASE-10-TEACHING.md): **10a** Penugasan dan jadwal (**APPROVED**), **10b** Sesi dan jurnal (**NEXT**), **10c** Laporan belum mengisi jurnal (**PENDING**). |
| Kebutuhan fungsional | [`docs/REQUIREMENTS.md`](../../docs/REQUIREMENTS.md) **FR-12**: *"Penugasan guru-matpel-rombel-periode, jadwal dengan deteksi bentrok, jurnal per sesi, laporan belum diisi."* |
| Gerbang keputusan | **`DEC-10`** telah **CLOSED** melalui **`DEC-39`** (`D3`, `D5`, `D8`): slot jam ke-`1..12` + `HH:MM` + ruang opsional; exception `holiday` (sekolah/per rombel), `cancelled`, dan `rescheduled`; sesi efektif = jadwal reguler aktif dikurangi `holiday`/`cancelled` ditambah `rescheduled`; batas waktu pengisian jurnal oleh guru pengampu **`journal_grace_days = 7` hari kalender WIB** (`D <= today WIB` s.d. `D + 7`), lewat batas hanya oleh `teaching.manage` selama tahun ajaran belum `locked`. |

---

## 2. Temuan Audit Fakta Kode (`a304df8`)

Audit langsung terhadap kode sumber pada `laravel-api/` dan `vue-app/` menunjukkan kondisi aktual berikut:

1. **Fondasi 10a Sudah Aktif dan Siap Menjadi Sumber Sesi 10b:**
   - Tabel `teaching_assignments`, `teaching_schedules`, dan `teaching_schedule_exceptions` sudah terpasang beserta model [`TeachingAssignment.php`](../../laravel-api/app/Models/TeachingAssignment.php), [`TeachingSchedule.php`](../../laravel-api/app/Models/TeachingSchedule.php), dan [`TeachingScheduleException.php`](../../laravel-api/app/Models/TeachingScheduleException.php).
   - [`config/teaching.php`](../../laravel-api/config/teaching.php) sudah menyimpan `days_of_week` (`1..6`), `period_presets` (`1..12`), `exception_types` (`holiday`, `cancelled`, `rescheduled`), dan **`journal_grace_days => 7`**.
   - **Gap:** Belum ada tabel, model, service, controller, route, ataupun halaman Vue untuk **sesi harian/mingguan yang dievaluasi terhadap exception kalender** dan **pencatatan jurnal pembelajaran (`teaching_journals`)**.

2. **Tautan Akun Pengguna (`users`) ke Profil Guru (`employees`):**
   - Sejak Fase 04a (`DEC-04`) dan dipakai pada Fase 08a (`LeaveRequestService`), relasi kanonik antara akun login dan profil pegawai adalah kolom `employees.user_id` (unik untuk profil aktif non-trashed).
   - Pada `.uat-phase10`, akun `uat.guru` sudah ditautkan ke profil `UAT-P10 Teacher 01` (`employee_type = 'Pendidik'`).
   - Agar pengujian manual dan otomatis pada 10b dapat membuktikan kriteria *"Guru kelas lain ditolak (`403`)"* secara berdampingan di UAT, `.uat-phase10` perlu memiliki akun guru kedua (`uat.guru2` → `UAT-P10 Teacher 02`).

3. **Pemetaan Ability RBAC (`teaching.view` dan `teaching.manage`):**
   - Setelah migration `2029_10_01_000004_sync_teaching_abilities_to_default_roles.php`:
     - `admin-sistem`, `operator-sekolah`, dan `wakil-kesiswaan` memiliki `teaching.view` + `teaching.manage`.
     - `kepala-sekolah` dan `guru` memiliki `teaching.view`.
   - Sesuai spesifikasi `PHASE-10-TEACHING.md` §10b (*"satu jurnal aktif per sesi; own assignment; koreksi admin berizin"*), tidak diperlukan ability baru:
     - Aktor dengan **`teaching.manage`** dapat mencatat/mengoreksi jurnal lintas penugasan (termasuk melewati grace window 7 hari) selama tahun ajaran belum `locked`.
     - Aktor dengan **`teaching.view`** yang tertaut ke profil `Employee` (`Pendidik` aktif) dapat mencatat/memperbarui jurnal **hanya pada sesi penugasannya sendiri (`own assignment`)** — termasuk sesi di mana ia ditunjuk sebagai `substitute_employee_id` pada exception `rescheduled` — dalam jendela waktu `session_date` s.d. `session_date + 7 hari` WIB.
     - Aktor `teaching.view` yang mencoba mengisi/mengubah jurnal milik guru lain, atau yang tidak tertaut ke profil `Employee` (mis. `kepala-sekolah`), ditolak dengan `403 Forbidden`.

4. **Interaksi dengan Pengecualian Kalender & Penghapusan Jadwal (10a):**
   - Saat ini `TeachingScheduleController::destroy()` memeriksa keterkaitan dengan `teaching_schedule_exceptions`, namun di 10b wajib juga menolak penghapusan (`422`) apabila slot jadwal sudah memiliki `teaching_journals`.
   - Demikian pula pada `TeachingScheduleException`: apabila sebuah sesi pada tanggal `D` sudah memiliki `teaching_journals`, maka pembuatan exception `holiday`, `cancelled`, atau `rescheduled` yang membatalkan/memindahkan sesi berjurnal tersebut harus ditolak `422` (atau mengharuskan pembatalan/penyesuaian terlebih dahulu) agar tidak menimbulkan jurnal yatim (*orphan journal*) pada hari libur/batal.

---

## 3. Usulan Keputusan Pemilik (`D1–D9` / `DEC-40 PROPOSED`)

Setiap butir dilengkapi rekomendasi tegas agar pemilik cukup menjawab **`"Saya setuju D1-D9."`** atau menunjuk butir `D#` yang ingin disesuaikan.

### D1 — Cakupan Subfase: Kerjakan 10b Saja (Sesi & Jurnal Mengajar); 10c Menyusul Setelah 10b Diterima
- **Rekomendasi:**
  1. Fokuskan paket ini sepenuhnya pada **Subfase 10b — Sesi dan jurnal pembelajaran**.
  2. **Subfase 10c** (Laporan guru/sesi belum mengisi jurnal lintas rentang tanggal, agregasi kepatuhan per guru/kelas, dan drilldown sesi kosong) dikerjakan pada subfase berikutnya setelah 10b diterima pemilik, menggunakan fondasi evaluator sesi efektif dari 10b.
  3. **Di luar cakupan (Non-goals 10b):** absensi kehadiran per siswa di kelas, LMS tugas/kuis/ujian/nilai rapor, unggah berkas materi ajar, dan notifikasi eksternal (WA/email).

### D2 — Evaluasi Sesi Efektif (*Computed Effective Sessions*) + Snapshot Konteks pada `teaching_journals` (`DEC-10`)
- **Rekomendasi:**
  1. Daftar sesi pembelajaran untuk suatu tanggal kalender `D` (`YYYY-MM-DD`, Senin..Sabtu, di dalam rentang `school_years.start_date..end_date`) dievaluasi secara deterministik tanpa perlu cron/seeder baris sesi kosong:
     - **Sesi Reguler (`source_type = 'regular'`):** Semua `teaching_schedules` (`status = 'active'` dan `teaching_assignment.status = 'active'`) dengan `day_of_week == ISO dayOfWeek(D)`:
       - Berstatus **`holiday`** bila terdapat `teaching_schedule_exceptions` bertipe `holiday` pada `exception_date == D` dengan `school_rombel_id IS NULL` (libur sekolah) atau `school_rombel_id == assignment.school_rombel_id` (libur kelas).
       - Berstatus **`cancelled`** bila terdapat exception bertipe `cancelled` pada `(teaching_schedule_id, exception_date == D)`.
       - Berstatus **`rescheduled_out`** bila terdapat exception bertipe `rescheduled` pada `(teaching_schedule_id, exception_date == D)` (dipindahkan ke `rescheduled_date`).
       - Selain tiga kondisi di atas, berstatus **`effective`** (wajib/dapat diisi jurnal pada tanggal `D`).
     - **Sesi Pengganti (`source_type = 'rescheduled'`):** Semua `teaching_schedule_exceptions` bertipe `rescheduled` dengan `rescheduled_date == D` (di mana jadwal & penugasan induknya `active` dan tanggal `D` bukan `holiday`):
       - Tampil sebagai sesi **`effective`** pada tanggal `D` dengan jam ke-`rescheduled_period_start..end`, pukul `rescheduled_start_time..end_time`, ruangan `rescheduled_room ?? schedule.room`, dan guru efektif `substitute_employee_id ?? assignment.employee_id`.
  2. Sesi berstatus `holiday`, `cancelled`, atau `rescheduled_out` **tidak dapat diisi jurnal** (`422 Unprocessable Entity` bila dipaksa lewat API) dan tidak dihitung sebagai sesi yang wajib diisi jurnal.
  3. Saat jurnal disimpan pada sesi `effective`, baris `teaching_journals` menyimpan *snapshot* konteks sesi (`school_year_id`, `semester_id`, `teaching_assignment_id`, `teaching_schedule_id`, `teaching_schedule_exception_id`, `school_rombel_id`, `subject_id`, `session_date`, `source_type`, `period_start`, `period_end`, `scheduled_start_time`, `scheduled_end_time`, `actual_start_time`, `actual_end_time`, `room`, `assigned_employee_id`, `teacher_employee_id`) sehingga jejak historis tetap utuh walaupun jadwal mingguan kemudian diubah ke `inactive`.

### D3 — Skema Tabel `teaching_journals`, Satu Jurnal per Sesi, & Idempotent Retry ("Retry Tidak Dobel")
- **Rekomendasi:**
  1. Buat tabel baru **`teaching_journals`** (`2029_10_02_000001_create_teaching_journals_table.php`) dengan **unique constraint `UNIQUE (teaching_schedule_id, session_date)`**:
     - `id` (PK)
     - `school_year_id`, `semester_id`, `teaching_assignment_id`, `teaching_schedule_id`, `teaching_schedule_exception_id` (nullable), `school_rombel_id`, `subject_id` (seluruhnya FK `restrictOnDelete`)
     - `session_date` (`DATE`, indexed)
     - `source_type` (`VARCHAR(20)`: `'regular'` | `'rescheduled'`)
     - `period_start` (`UNSIGNED TINYINT`), `period_end` (`UNSIGNED TINYINT`)
     - `scheduled_start_time` (`CHAR(5)`), `scheduled_end_time` (`CHAR(5)`)
     - `actual_start_time` (`CHAR(5)`), `actual_end_time` (`CHAR(5)`) — wajib format `HH:MM` 24 jam dengan `actual_start_time < actual_end_time` (default di UI mengikuti `scheduled_start_time..end_time`)
     - `room` (`VARCHAR(60)` nullable)
     - `assigned_employee_id` (FK `employees` — guru pengampu penugasan)
     - `teacher_employee_id` (FK `employees` — guru aktual yang mengajar sesi tersebut)
     - `topic` (`VARCHAR(200)` NOT NULL — pokok bahasan/materi, minimal 3 karakter)
     - `method` (`VARCHAR(60)` NOT NULL — metode pembelajaran dari daftar `config/teaching.php`: `Lecture`, `Discussion`, `Practicum`, `Demonstration`, `Project`, `Assignment`, `Assessment`, `Other`)
     - `summary` (`TEXT` nullable, maks. 2.000 karakter — ringkasan aktivitas pembelajaran)
     - `notes` (`TEXT` nullable, maks. 1.000 karakter — catatan kelas/tindak lanjut)
     - `correction_reason` (`VARCHAR(255)` nullable — alasan koreksi/input susulan oleh admin/operator)
     - `corrected_by` (nullable FK `users`), `corrected_at` (`TIMESTAMP` nullable)
     - `version` (`UNSIGNED INTEGER` default `1`)
     - `created_by`, `updated_by` (nullable FK `users`)
     - `timestamps()`
  2. **Penanganan Idempotent Retry (`POST /api/teaching/journals`):**
     - Di dalam transaksi DB yang mengunci `SchoolYear::lockForUpdate()` dan `TeachingSchedule::lockForUpdate()`, service memeriksa apakah jurnal untuk `(teaching_schedule_id, session_date)` sudah ada:
       - **Belum ada:** buat baris baru (`version = 1`), kembalikan `201 Created`.
       - **Sudah ada & payload fungsional identik** (`topic`, `method`, `summary`, `notes`, `actual_start_time`, `actual_end_time`, `teacher_employee_id` sama persis): anggap sebagai *idempotent retry* (mis. double-click atau network retry), kembalikan **`200 OK`** beserta `meta.idempotent_replay = true` **tanpa membuat baris kedua dan tanpa menaikkan `version`**.
       - **Sudah ada & payload berbeda:** kembalikan **`409 Conflict`** beserta objek `latest` agar klien beralih ke mode edit (`PUT/PATCH` dengan `expected_version`).
  3. **Kebijakan Tanpa Hard Delete:**
     - Jurnal yang sudah tercatat tidak dihapus (`DELETE` tidak disediakan); perbaikan dilakukan melalui `PUT/PATCH /api/teaching/journals/{teachingJournal}` dengan `expected_version`.

### D4 — Otorisasi `Own Assignment` (Guru) vs `teaching.manage` (Operator/Admin)
- **Rekomendasi:**
  1. **Guru (`teaching.view` tanpa `teaching.manage`, tertaut ke `Employee` via `employees.user_id == auth()->id()`):**
     - Hanya boleh mengisi (`POST`) dan memperbarui (`PUT/PATCH`) jurnal pada sesi di mana `self_employee.id` adalah **guru efektif sesi tersebut**:
       - Untuk sesi `regular`: `assignment.employee_id == self_employee.id`.
       - Untuk sesi `rescheduled`: `(exception.substitute_employee_id ?? assignment.employee_id) == self_employee.id`.
     - Percobaan oleh guru untuk mengisi atau mengubah jurnal milik **guru/kelas lain** ditolak tegas dengan **`403 Forbidden`** (*"You are only allowed to record or update teaching journals for your own assigned sessions."*).
     - Guru tidak dapat mengubah `teacher_employee_id` ke pegawai lain (otomatis dikunci ke `self_employee.id`).
  2. **Pengelola Jadwal/Pembelajaran (`teaching.manage` — `operator-sekolah`, `admin-sistem`, `wakil-kesiswaan`):**
     - Dapat mengisi (`POST`) dan mengoreksi (`PUT/PATCH`) jurnal untuk sesi mana pun pada tahun ajaran yang belum `locked`.
     - Dapat memilih `teacher_employee_id` dari daftar guru `Pendidik` aktif (`Employee::scopeEligibleTeacher()`) apabila sesi diisi oleh guru pengganti mendadak.
     - Wajib mengisi `correction_reason` (`10–255` karakter) apabila:
       - Mengisi jurnal untuk sesi yang sudah melewati batas waktu 7 hari (`session_date < today_wib - 7 days`), **atau**
       - Mengedit jurnal yang sebelumnya dibuat oleh guru lain, **atau**
       - Mengganti `teacher_employee_id` ke guru yang berbeda dari guru efektif jadwal.
       Saat kondisi tersebut terpenuhi, server mencatat `corrected_by = auth()->id()` dan `corrected_at = now()`.
  3. **Peran Pemantau Read-Only (`kepala-sekolah` atau pengguna `teaching.view` tanpa tautan `Employee`):**
     - Dapat melihat seluruh daftar sesi dan detail jurnal (`GET`), tetapi setiap percobaan `POST`/`PUT`/`PATCH` ditolak dengan **`403 Forbidden`**.

### D5 — Penegakan Jendela Waktu (`DEC-10` / `journal_grace_days = 7` WIB) & Tahun Ajaran Terkunci (`locked_at`)
- **Rekomendasi:**
  1. **Larangan Tanggal Masa Depan (`session_date > today WIB`):**
     - Baik guru maupun operator/admin **tidak boleh** mencatat jurnal untuk tanggal sesi di masa depan (`session_date > Carbon::now('Asia/Jakarta')->toDateString()`) → ditolak **`422 Unprocessable Entity`**.
  2. **Jendela Waktu Guru (`D <= today WIB <= D + 7`):**
     - Bagi pengguna tanpa `teaching.manage` (guru pengampu/pengganti), pembuatan dan pengubahan jurnal hanya diizinkan selama `session_date >= today_wib - 7 days` (`config('teaching.journal_grace_days', 7)`).
     - Jika `session_date < today_wib - 7 days`, percobaan `POST`/`PUT`/`PATCH` oleh guru ditolak dengan **`422 Unprocessable Entity`** (*"The 7-day submission window for this session has expired. Please contact the school operator for a late entry or correction."*).
  3. **Tahun Ajaran Terkunci (`school_years.locked_at !== null`):**
     - Seluruh operasi tulis (`POST`, `PUT`/`PATCH`) pada tahun ajaran yang sudah `locked` ditolak dengan **`422 Unprocessable Entity`** bagi **semua peran** (termasuk `teaching.manage`), sedangkan pembacaan (`GET`) tetap diizinkan secara *read-only*.

### D6 — Guard Konsistensi Terhadap Jadwal & Exception Kalender (10a)
- **Rekomendasi:**
  1. `TeachingScheduleService::deleteSchedule()` menolak penghapusan (`422`) apabila slot jadwal sudah memiliki baris di `teaching_journals` (operator diarahkan mengubah `status` jadwal menjadi `inactive`).
  2. `TeachingScheduleService::createException()` dan `updateException()` menolak pembuatan/pengubahan exception `holiday`, `cancelled`, atau `rescheduled` (`422`) apabila exception tersebut akan membatalkan atau memindahkan sesi `(teaching_schedule_id, date)` yang **sudah memiliki `teaching_journals`**.
  3. `TeachingScheduleService::deleteException()` menolak penghapusan exception `rescheduled` (`422`) apabila sesi pengganti pada `rescheduled_date` sudah memiliki `teaching_journals` yang merujuk `teaching_schedule_exception_id` tersebut.

### D7 — Kontrak API (5 Route Baru → Total 18 Route `/api/teaching/*`) & Halaman UI `/public/teaching/journals`
- **Rekomendasi:**
  1. Perluas `GET /api/teaching/options` secara aditif dengan:
     - `self_employee`: `{ id, name, nuptk, subject_id, subject_name } | null` (profil `Pendidik` aktif yang tertaut ke `auth()->id()`).
     - `journal_methods`: daftar metode pembelajaran dari `config/teaching.php`.
     - `journal_grace_days`: `7`.
     - `today_wib`: tanggal `YYYY-MM-DD` saat ini di zona waktu `Asia/Jakarta`.
  2. Tambahkan **5 route baru** di bawah `/api/teaching/*` (`auth:sanctum`, `active`, `can:teaching.view`):
     - `GET /api/teaching/sessions` — evaluasi daftar sesi pada tanggal `date` (default `today_wib`, atau rentang `date_from..date_to` maks. 14 hari) untuk `(school_year_id, semester_id)`, lengkap dengan filter `school_rombel_id`, `subject_id`, `employee_id`, `scope` (`mine`|`all`), dan `journal_status` (`all`|`filled`|`missing`|`excluded`), ringkasan angka (`summary: { total_slots, effective_sessions, recorded_journals, missing_journals, excluded_slots }`), status kelayakan pengisian per sesi (`can_record`, `can_update`, `is_within_grace_window`, `grace_deadline_date`, `block_reason`), serta data `journal` bila sudah terisi.
     - `GET /api/teaching/journals` — daftar berpaginasi (`10/20/50/100`, default `20`) jurnal pembelajaran yang sudah tercatat, mendukung filter `school_year_id`, `semester_id`, `school_rombel_id`, `subject_id`, `employee_id`, `date_from`, `date_to`, `method`, `source_type`, `scope` (`mine`|`all`), dan `search` (`topic`, kelas, mapel, nama guru).
     - `GET /api/teaching/journals/{teachingJournal}` — detail satu jurnal pembelajaran beserta konteks sesi dan izin `can.update`.
     - `POST /api/teaching/journals` — simpan jurnal baru untuk `(teaching_schedule_id, session_date)` (atau idempotent replay `200` bila identik, `409` bila konflik).
     - `PUT/PATCH /api/teaching/journals/{teachingJournal}` — perbarui jurnal dengan `expected_version` (`409` + `latest` bila basi).
  3. Tambahkan halaman Vue baru **`vue-app/src/views/teaching/TeachingJournalsView.vue`** pada route `/public/teaching/journals` (alias `/teaching/journals`, `requiresAbility: 'teaching.view'`) dan item menu sidebar **Teaching → Sessions & Journals**:
     - **Tab 1 — Daily Sessions Worklist:** pemilih Tahun Ajaran, Semester, Tanggal (`Date` + tombol cepat `Prev Day` / `Today` / `Next Day`), toggle `My Sessions` vs `All Sessions`, filter Kelas/Mapel/Guru/Status, 4 kartu ringkasan harian, tabel sesi harian lengkap dengan badge status (`Recorded`, `Pending`, `Grace Expired`, `Holiday`, `Cancelled`, `Moved`, `Rescheduled In`), dan tombol langsung **Fill Journal** / **Edit Journal** / **View Journal**.
     - **Tab 2 — Recorded Journals History:** tabel riwayat jurnal berpaginasi server-side dengan filter rentang tanggal, Kelas, Mapel, Guru, Metode, dan pencarian `topic`, dilengkapi dialog detail/edit serta penanganan konflik `409` (**Reload Latest**).

### D8 — Privasi Audit (`Loggable` Allowlist Tanpa Teks Bebas)
- **Rekomendasi:**
  - Model `TeachingJournal` memakai trait `Loggable` dengan `$auditableAttributes` eksplisit yang mencatat atribut struktural (`school_year_id`, `semester_id`, `teaching_assignment_id`, `teaching_schedule_id`, `teaching_schedule_exception_id`, `school_rombel_id`, `subject_id`, `session_date`, `source_type`, `period_start`, `period_end`, `scheduled_start_time`, `scheduled_end_time`, `actual_start_time`, `actual_end_time`, `room`, `assigned_employee_id`, `teacher_employee_id`, `method`, `corrected_by`, `corrected_at`, `version`) dan **mengecualikan** teks bebas `topic`, `summary`, `notes`, serta `correction_reason` dari tabel `activities`.

### D9 — Perluasan UAT Terisolasi `.uat-phase10` (`prepare-journals`) & Kebijakan `DEC-27`
- **Rekomendasi:**
  1. Perluas `laravel-api/scripts/phase10-uat.php` secara **additif dan idempoten** dengan perintah `prepare-journals` (tanpa mereset data 10a di `.uat-phase10`):
     - Tambahkan akun **`uat.guru2`** (role `guru`, tertaut ke `UAT-P10 Teacher 02`) untuk menguji penolakan `403` lintas guru secara langsung.
     - Seed **≥60 baris `teaching_journals`** sintetis pada tahun ajaran aktif `2026/2027 Semester I` (mencakup jurnal milik `uat.guru` / `UAT-P10 Teacher 01`, jurnal milik `uat.guru2` / `UAT-P10 Teacher 02` dan guru lainnya, jurnal pada sesi `rescheduled` Sabtu `2026-09-12` dengan guru pengganti `UAT-P10 Teacher 11`, jurnal dengan koreksi operator, serta menyisakan sesi efektif dalam 7 hari terakhir yang belum diisi jurnal untuk uji coba langsung di UI) ditambah **1 baris `teaching_journals` historis** pada tahun ajaran terkunci `2024/2025`.
  2. Sesuai `DEC-27`, server UAT pada port `18028`/`14028` dihentikan kembali setelah verifikasi agen selesai dan data `.uat-phase10` dipertahankan untuk review manual pemilik.

---

## 4. Rencana Langkah Implementasi (`S0–S8`, Setelah `D1–D9` Disetujui)

1. **`S0` — Pencatatan Persetujuan `DEC-40 APPROVED`:**
   - Catat persetujuan pemilik atas `D1–D9` di `PHASE-10B-PLAN.md`, `DECISIONS.md`, `REQUIREMENTS.md`, `PHASE-10-TEACHING.md`, `ROADMAP.md`, `INDEX.md`, `HANDOFF.md`, `PROJECT_CONTEXT.md`, dan `CHANGELOG.md` pada commit dokumentasi terpisah sebelum menulis kode.
2. **`S1` — Config, Migration & Model `TeachingJournal`:**
   - Perbarui `laravel-api/config/teaching.php` (`journal_methods`).
   - Buat migration `2029_10_02_000001_create_teaching_journals_table.php`.
   - Buat model `laravel-api/app/Models/TeachingJournal.php` dengan `$auditableAttributes` tanpa `topic`/`summary`/`notes`/`correction_reason`, dan tambahkan relasi `journals()` pada `TeachingAssignment`, `TeachingSchedule`, dan `TeachingScheduleException`.
3. **`S2` — Guard Integritas pada `TeachingScheduleService` (10a) & Layanan Baru `TeachingSessionJournalService` (10b):**
   - Tambahkan guard penghapusan/pembatalan sesi berjurnal pada `TeachingScheduleService`.
   - Buat `laravel-api/app/Services/TeachingSessionJournalService.php` untuk evaluasi sesi efektif harian/rentang tanggal (`regular` vs `holiday`/`cancelled`/`rescheduled_out`/`rescheduled`), pemeriksaan otorisasi `own assignment` vs `teaching.manage`, validasi jendela waktu `journal_grace_days = 7` WIB & larangan tanggal masa depan, penanganan idempotent retry (`200` vs `201` vs `409`), dan pembaruan berversi (`expected_version`/`409`).
4. **`S3` — FormRequest, Controller & 5 Route Baru `/api/teaching/*`:**
   - Buat `TeachingJournalStoreRequest.php` dan `TeachingJournalUpdateRequest.php`.
   - Perbarui `TeachingOptionController.php` (`self_employee`, `journal_methods`, `journal_grace_days`, `today_wib`).
   - Buat `TeachingSessionController.php` (`GET /api/teaching/sessions`) dan `TeachingJournalController.php` (`index`, `show`, `store`, `update`).
   - Daftarkan 5 route baru di `laravel-api/routes/api.php` (total 18 route `/api/teaching/*`).
5. **`S4` — Halaman Frontend Vue `/public/teaching/journals`:**
   - Buat `vue-app/src/views/teaching/TeachingJournalsView.vue` (2 tab: **Daily Sessions Worklist** & **Recorded Journals History**).
   - Daftarkan route `/public/teaching/journals` (alias `/teaching/journals`) di `vue-app/src/router/index.js` dan item menu **Sessions & Journals** di bawah grup **Teaching** pada `vue-app/src/layout/AppMenu.vue`.
6. **`S5` — Feature Tests (`TeachingJournalApiTest`) & Regresi:**
   - Buat `laravel-api/tests/Feature/Teaching/TeachingJournalApiTest.php` yang menguji matriks `T1–T15`:
     - `T1`: Pendaftaran 18 route `/api/teaching/*` & matriks akses (`guest` 401, `inactive` 403, `staff` 403, `kepala-sekolah` read-only 200/403).
     - `T2`: Evaluasi `GET /api/teaching/sessions` untuk hari kerja biasa, hari `holiday` (sekolah & kelas), slot `cancelled`, slot `rescheduled_out` pada tanggal asli, dan slot `rescheduled` pada tanggal tujuan (`rescheduled_date`).
     - `T3`: Guru tertaut (`own assignment`) berhasil mengisi jurnal pada sesinya sendiri (`201`) dan mengeditnya (`200`, `version = 2`), serta allowlist audit `activities` tidak memuat `topic`/`summary`/`notes`/`correction_reason`.
     - `T4`: Idempotent retry pada `POST /api/teaching/journals` dengan payload identik mengembalikan `200 OK` (`idempotent_replay = true`) tanpa menggandakan baris dan tanpa menaikkan `version`, sedangkan payload berbeda mengembalikan `409 Conflict` + `latest`.
     - `T5`: Guru ditolak `403` saat mencoba mengisi atau mengedit jurnal milik guru/kelas lain.
     - `T6`: Guru pengganti (`substitute_employee_id`) pada exception `rescheduled` diizinkan mengisi jurnal sesi pengganti tersebut (`201`), sedangkan guru utama yang sudah digantikan tidak lagi menjadi guru efektif sesi pengganti tersebut.
     - `T7`: Penolakan `422` saat mengisi jurnal untuk tanggal masa depan (`session_date > today WIB`), hari `holiday`, slot `cancelled`, tanggal asli slot `rescheduled_out`, atau tanggal yang tidak cocok dengan `day_of_week` jadwal reguler.
     - `T8`: Penegakan `journal_grace_days = 7` WIB: guru ditolak `422` untuk `session_date = today - 8 days`, sedangkan operator (`teaching.manage`) berhasil mencatat/mengoreksi jurnal tersebut dengan `correction_reason` (`corrected_by` & `corrected_at` terisi).
     - `T9`: Operator (`teaching.manage`) wajib menyertakan `correction_reason` saat mengoreksi jurnal milik guru lain atau melewati grace window (`422` bila kosong).
     - `T10`: Penolakan mutasi (`422`) pada tahun ajaran terkunci (`locked_at !== null`) bagi guru maupun operator, sedangkan `GET` tetap `200`.
     - `T11`: Optimistic locking `expected_version` basi pada `PUT /api/teaching/journals/{id}` mengembalikan `409 Conflict` beserta `latest`.
     - `T12`: Guard konsistensi 10a: slot jadwal yang sudah memiliki jurnal menolak `DELETE /api/teaching/schedules/{id}` (`422`) dan menolak pembuatan exception `cancelled`/`holiday`/`rescheduled` yang menggugurkan sesi berjurnal tersebut (`422`).
   - Jalankan regresi `TeachingScheduleApiTest`, `Academic`, `Access`, dan `Archives`, ditambah Pint, ESLint, dan `npm run build`.
7. **`S6` — Perluasan UAT `.uat-phase10` (`prepare-journals`), Probe HTTP & Penghentian Server (`DEC-27`):**
   - Perluas `laravel-api/scripts/phase10-uat.php` dengan `prepare-journals`, jalankan dua kali untuk membuktikan idempotensi, jalankan probe HTTP live pada `127.0.0.1:18028`, lalu hentikan server UAT sesuai `DEC-27`.
8. **`S7` — Dokumentasi Kontrak & Laporan Review Pemilik:**
   - Perbarui `DATABASE.md`, `ACCESS-CONTROL.md`, `API-CONTRACTS.md`, `TESTING.md`, `DECISIONS.md`, `REQUIREMENTS.md`, `PHASE-10-TEACHING.md`, `ROADMAP.md`, `INDEX.md`, `HANDOFF.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`, serta buat `PHASE-10B-REPORT.md` dan `PHASE-10B-MANUAL-REVIEW.md`.

---

## 5. Cara Pemilik Memberikan Keputusan

Silakan tinjau usulan **D1–D9** di atas:
- Bila seluruh usulan sudah sesuai, cukup balas: **`"Saya setuju D1-D9."`**
- Bila ada butir yang ingin diubah, sebutkan nomor `D#` beserta preferensi Anda.
