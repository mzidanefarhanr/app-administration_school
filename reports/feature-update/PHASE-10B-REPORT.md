# Laporan Subfase 10b — Sesi dan Jurnal Pembelajaran

Tanggal: implementasi dan verifikasi agen 2026-11-03 WIB; penerimaan manual pemilik 2026-11-03 WIB. Branch: `main`. Baseline induk: `1c2c48c` (pencatatan DEC-40 APPROVED di atas paket rencana `8ce345b` dan baseline 10a `a304df8`); paket review: `df9f925`. Status teknis: **IMPLEMENTED — agent-verified (2026-11-03)**. Status review pemilik: **APPROVED (#1–#18, 2026-11-03 WIB; pernyataan `"Pengujian nomor 1-18 sudah berhasil dan saya approved."`)**.
Keputusan pemilik: **DEC-40 APPROVED** (D1–D9, 2026-11-03 WIB; pernyataan **"Saya setuju D1-D9"**).
Otorisasi: implementasi **Subfase 10b** sesuai `reports/feature-update/PHASE-10B-PLAN.md` (S0–S7) telah diterima pemilik. Subfase **10a** tetap **OWNER APPROVED (#1–#18, 2026-11-03 WIB; `a304df8`)**, dan Fase **09 (09a + 09b)** tetap **OWNER APPROVED (#1–#18, 2026-11-03 WIB; `f185ca6`)**. Subfase **10c** (laporan belum mengisi jurnal) menunggu persetujuan rencana terpisah setelah 10b diterima pemilik.

## 1. Outcome dan cakupan

Subfase 10b membangun evaluasi sesi pembelajaran efektif harian/rentang dan pencatatan jurnal mengajar (`teaching_journals`) di atas fondasi jadwal 10a sesuai keputusan `DEC-40` (`D1–D9`) dan `DEC-10`:

- **Skema tabel additive `teaching_journals` & Idempotent Retry (`D3`)**:
  - `teaching_journals` (`2029_10_02_000001_create_teaching_journals_table.php`) memiliki `UNIQUE (teaching_schedule_id, session_date)` untuk menjamin paling banyak **satu jurnal aktif per sesi**.
  - Menyimpan *snapshot* konteks sesi saat jurnal dicatat (`school_year_id`, `semester_id`, `teaching_assignment_id`, `teaching_schedule_id`, `teaching_schedule_exception_id`, `school_rombel_id`, `subject_id`, `session_date`, `source_type` = `regular`|`rescheduled`, `period_start..period_end`, `scheduled_start_time..scheduled_end_time`, `actual_start_time..actual_end_time`, `room`, `assigned_employee_id`, `teacher_employee_id`) ditambah atribut jurnal (`topic`, `method`, `summary`, `notes`), jejak koreksi operator (`correction_reason`, `corrected_by`, `corrected_at`), `version` untuk optimistic locking (`expected_version` / `409 Conflict`), dan `created_by`/`updated_by`.
  - **Idempotent retry ("retry tidak dobel")**: pada `POST /api/teaching/journals`, bila baris untuk `(teaching_schedule_id, session_date)` sudah ada dan payload fungsional identik (`topic`, `method`, `summary`, `notes`, `actual_start_time`, `actual_end_time`, `teacher_employee_id`), service mengembalikan **`200 OK`** beserta `meta.idempotent_replay = true` **tanpa membuat baris kedua dan tanpa menaikkan `version`**. Bila sudah ada namun isinya berbeda, service mengembalikan **`409 Conflict`** beserta objek `latest`.
  - Tidak ada hard delete (`DELETE` tidak disediakan); perbaikan dilakukan melalui `PUT/PATCH /api/teaching/journals/{teachingJournal}` dengan `expected_version`.
- **Evaluasi deterministik sesi pembelajaran efektif (`D2`)**:
  - `TeachingSessionJournalService::listSessions()` mengevaluasi daftar sesi pada satu tanggal `date` (`YYYY-MM-DD`, Senin..Sabtu) atau rentang `date_from..date_to` (maks. 14 hari) di dalam rentang `school_years.start_date..end_date`:
    1. **Sesi reguler (`source_type = 'regular'`)**: seluruh `teaching_schedules` (`status = 'active'` dan `teaching_assignment.status = 'active'`) dengan `day_of_week == ISO dayOfWeek(D)` dievaluasi terhadap `teaching_schedule_exceptions` menjadi:
       - `holiday` (jika ada exception `holiday` satu sekolah `school_rombel_id IS NULL` atau khusus `school_rombel_id` kelas tersebut pada tanggal `D`),
       - `cancelled` (jika ada exception `cancelled` pada jadwal & tanggal `D`),
       - `rescheduled_out` (jika ada exception `rescheduled` pada jadwal & tanggal asli `D`),
       - atau `effective` (wajib/dapat diisi jurnal).
    2. **Sesi pengganti (`source_type = 'rescheduled'`)**: seluruh `teaching_schedule_exceptions` bertipe `rescheduled` dengan `rescheduled_date == D` (selama tanggal `D` bukan `holiday` untuk kelas tersebut) tampil sebagai sesi `effective` dengan jam ke-`rescheduled_period_start..end`, pukul `rescheduled_start_time..end_time`, ruangan `rescheduled_room ?? schedule.room`, dan guru efektif `substitute_employee_id ?? assignment.employee_id`.
  - Sesi berstatus `holiday`, `cancelled`, atau `rescheduled_out` menolak pencatatan jurnal (`422`) dan dikelompokkan ke `excluded_slots` pada ringkasan harian (`summary: { total_slots, effective_sessions, recorded_journals, missing_journals, excluded_slots }`).
- **Otorisasi `Own Assignment` (Guru) vs `teaching.manage` (Operator/Admin) (`D4`)**:
  - Tanpa menambah ability baru di `AbilityCatalog`:
    - **Guru (`teaching.view` tanpa `teaching.manage`, tertaut ke profil `Employee` `Pendidik` aktif via `employees.user_id`)**: hanya diizinkan mengisi (`POST`) atau memperbarui (`PUT/PATCH`) jurnal pada sesi di mana ia menjadi **guru efektif** (`assignment.employee_id` pada sesi `regular`, atau `exception.substitute_employee_id ?? assignment.employee_id` pada sesi `rescheduled`). Percobaan mengisi/mengubah jurnal milik guru atau kelas lain ditolak tegas dengan **`403 Forbidden`**. Apabila slot `rescheduled` menunjuk `substitute_employee_id`, guru pengganti berhak mengisi jurnal sesi pengganti tersebut (`201`), sedangkan guru utama yang sudah digantikan ditolak (`403`).
    - **Pengelola (`teaching.manage` — `operator-sekolah`, `admin-sistem`, `wakil-kesiswaan`)**: dapat mengisi dan mengoreksi jurnal lintas guru pada tahun ajaran yang belum `locked`, serta wajib menyertakan `correction_reason` (`10–255` karakter) apabila mengisi jurnal lewat batas 7 hari, mengedit jurnal milik guru lain, atau mengganti `teacher_employee_id` (`corrected_by` & `corrected_at` dicatat otomatis).
    - **Pemantau read-only (`kepala-sekolah` atau pengguna `teaching.view` tanpa tautan `Employee` `Pendidik` aktif)**: dapat membaca seluruh sesi dan jurnal (`GET`), tetapi ditolak `403` pada `POST`/`PUT`/`PATCH`.
- **Penegakan jendela waktu `journal_grace_days = 7` WIB & tahun ajaran terkunci (`D5`)**:
  - Tanggal masa depan (`session_date > today WIB`) ditolak `422` bagi semua peran.
  - Guru tanpa `teaching.manage` hanya boleh mengisi/mengubah jurnal pada rentang `today_wib - 7 days <= session_date <= today_wib`; sesi yang lebih lama dari 7 hari ditolak `422` bagi guru, namun tetap dapat diisi/dikoreksi oleh `teaching.manage` dengan `correction_reason`.
  - Tahun ajaran terkunci (`school_years.locked_at IS NOT NULL`) menolak seluruh operasi tulis (`POST`/`PUT`/`PATCH` → `422`) dengan `SchoolYear::lockForUpdate()`, sementara `GET` tetap `200`.
- **Guard konsistensi dua arah dengan jadwal & exception 10a (`D6`)**:
  - `TeachingScheduleService` menolak penghapusan (`422`) `TeachingAssignment`, `TeachingSchedule`, atau `TeachingScheduleException` yang sudah memiliki `teaching_journals`, menolak perubahan struktural jadwal yang sudah berjurnal (`422`), serta menolak pembuatan/pengubahan exception `holiday`, `cancelled`, atau `rescheduled` yang akan menggugurkan atau memindahkan sesi yang sudah memiliki `teaching_journals` (`422`).
- **Privasi audit (`D8`) & antarmuka pengguna Vue (`/public/teaching/journals` — `D7`)**:
  - `TeachingJournal` memakai `Loggable` dengan `$auditableAttributes` eksplisit yang mengecualikan teks bebas `topic`, `summary`, `notes`, dan `correction_reason` dari tabel `activities`.
  - Komponen `TeachingJournalsView.vue` menyediakan 2 tab terintegrasi (**Daily Sessions Worklist** dengan navigasi tanggal cepat `Prev Day`/`Today`/`Next Day`, toggle `My Sessions` vs `All Sessions`, 4 kartu ringkasan, dan badge status sesi; serta **Recorded Journals History** berpaginasi server-side), dialog Fill/Edit/View Journal, serta tombol **Reload Latest** saat terjadi `409 Conflict`.

Tidak dikerjakan pada 10b (non-goals `D1`): laporan agregasi guru belum mengisi jurnal lintas periode (`10c`), absensi per siswa, LMS tugas/kuis/rapor, unggah berkas materi, perubahan dependency/lockfile, migrasi database operasional/MariaDB, atau perubahan service/`.env`.

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File | Perubahan | Schema/izin |
|---|---|---|
| `config/teaching.php` | Menambahkan `journal_methods` (8 metode pembelajaran) dan `journal_source_types` (`regular`, `rescheduled`) | Tanpa `.env` baru |
| `database/migrations/2029_10_02_000001_create_teaching_journals_table.php` | Tabel `teaching_journals` + `UNIQUE (teaching_schedule_id, session_date)` + FK `restrictOnDelete` | Additive |
| `app/Models/TeachingJournal.php` | Model + konstanta sumber + relasi + allowlist `$auditableAttributes` tanpa `topic`/`summary`/`notes`/`correction_reason` | `Loggable` allowlist |
| `app/Models/TeachingAssignment.php`, `TeachingSchedule.php`, `TeachingScheduleException.php` | Relasi `journals(): HasMany` | Additive |
| `app/Services/TeachingScheduleService.php` | Guard `D6` menolak hapus/ubah struktural penugasan/jadwal/exception berjurnal dan menolak exception yang menggugurkan sesi berjurnal (`422`) | Consistency guard |
| `app/Services/TeachingSessionJournalService.php` | Evaluasi deterministik sesi harian/rentang (`listSessions`), riwayat/detail jurnal (`listJournals`/`showJournal`), pembuatan idempoten (`recordJournal`: `201`/`200` replay/`409`), dan pembaruan berversi (`updateJournal`: `200`/`409`), otorisasi `own assignment` vs `teaching.manage`, `journal_grace_days = 7` WIB, dan guard tahun `locked` | Service domain |
| `app/Http/Requests/TeachingJournalStoreRequest.php` & `TeachingJournalUpdateRequest.php` | Validasi input jurnal (`topic`, `method`, `actual_start_time..end_time`, `summary`, `notes`, `correction_reason`, `expected_version`) & gate izin tulis awal | `403` / `422` |
| `app/Http/Controllers/TeachingOptionController.php` | Memperluas `GET /api/teaching/options` dengan `can.create_journals`, `can.update_journals`, `self_employee`, `journal_methods`, `journal_source_types`, `today_wib`, `grace_deadline_date` | Additive |
| `app/Http/Controllers/TeachingSessionController.php` | `GET /api/teaching/sessions` (`teaching.view`) | 1 route baru |
| `app/Http/Controllers/TeachingJournalController.php` | `index`, `show`, `store`, `update` (`PUT`/`PATCH`) di bawah `/api/teaching/journals*` (`teaching.view` + otorisasi tulis per sesi) | 4 route baru |
| `routes/api.php` | Pendaftaran 5 route baru di bawah `/api/teaching/*` (total **18 route**) | `can:teaching.view` |
| `tests/Feature/Teaching/TeachingJournalApiTest.php` & `TeachingScheduleApiTest.php` | 12 tes baru / 148 assertions mencakup matriks `T1–T12` + penyesuaian hitungan 18 route pada `TeachingScheduleApiTest` (total **18 tes / 264 assertions**) | 12 tes baru |
| `scripts/phase10-uat.php` | Perintah baru `prepare-journals` (aditif & idempoten: akun `uat.guru2` + `66` jurnal sintetis) dan pembaruan `status` | Hanya `.uat-phase10` |
| `vue-app/src/views/teaching/TeachingJournalsView.vue` | Halaman 2 tab (Daily Sessions Worklist & Recorded Journals History) + dialog Fill/Edit/View Journal + `409` Reload Latest | Guard `teaching.view` |
| `vue-app/src/router/index.js` & `vue-app/src/layout/AppMenu.vue` | Route `/public/teaching/journals` (alias `/teaching/journals`) dan item menu sidebar **Teaching → Sessions & Journals** | `requiresAbility: 'teaching.view'` |

Kontrak **5 endpoint baru `/api/teaching/*`** (melengkapi 13 endpoint 10a → total **18 route**, detail lengkap di `docs/API-CONTRACTS.md`):

| # | Method | Endpoint | Izin | Catatan |
|---|---|---|---|---|
| 14 | GET | `/api/teaching/sessions` | `teaching.view` | Evaluasi deterministik sesi pada `date` (atau `date_from..date_to` ≤14 hari) beserta `summary` dan metadata izin per sesi (`can_record`, `can_update`, `block_reason`) |
| 15 | GET | `/api/teaching/journals` | `teaching.view` | Daftar berpaginasi jurnal tercatat + filter tahun/semester/kelas/mapel/guru/tanggal/metode/sumber/scope (`mine`\|`all`)/search |
| 16 | GET | `/api/teaching/journals/{teachingJournal}` | `teaching.view` | Detail satu jurnal beserta `can_update`, `is_within_grace_window`, `grace_deadline_date`, dan `block_reason` |
| 17 | POST | `/api/teaching/journals` | `teaching.manage` atau (`teaching.view` + `own assignment`) | Buat jurnal sesi efektif (`201`), idempotent replay (`200` + `meta.idempotent_replay=true`) bila payload identik, atau `409` + `latest` bila berbeda |
| 18 | PUT/PATCH | `/api/teaching/journals/{teachingJournal}` | `teaching.manage` atau (`teaching.view` + `own assignment`) | Perbarui jurnal + `expected_version` (`409` bila basi); wajib `correction_reason` bagi koreksi operator |

## 3. Bukti verifikasi

- **Tes fitur Teaching (`TeachingJournalApiTest` + `TeachingScheduleApiTest`)**:
  - Perintah: `php artisan test tests/Feature/Teaching/TeachingJournalApiTest.php tests/Feature/Teaching/TeachingScheduleApiTest.php`
  - Hasil: **PASS — 18 passed (264 assertions) in 17.53s** (`TeachingJournalApiTest`: **12 passed / 148 assertions**; `TeachingScheduleApiTest`: **6 passed / 116 assertions**).
  - Cakupan `TeachingJournalApiTest` (`T1–T12`):
    1. `T1`: Pendaftaran 18 route `/api/teaching/*` dan matriks akses (`guest` → `401`, akun nonaktif → `403`, staf tanpa `teaching.view` → `403`, `kepala-sekolah` → `200` pada GET dengan `can.create_journals=false` dan `403` pada POST/PUT).
    2. `T2`: Evaluasi deterministik `GET /api/teaching/sessions` untuk hari kerja reguler (`effective`), hari `holiday` (sekolah & kelas), slot `cancelled`, slot `rescheduled_out` pada tanggal asli, dan slot `rescheduled` pada tanggal pengganti (`rescheduled_date`).
    3. `T3`: Guru tertaut (`own assignment`) berhasil mencatat jurnal pada sesinya sendiri (`201`) dan memperbaruinya (`200`, `version = 2`), serta allowlist audit `activities` tidak menyimpan teks bebas `topic`, `summary`, `notes`, atau `correction_reason`.
    4. `T4`: Idempotent retry pada `POST /api/teaching/journals` dengan payload fungsional identik mengembalikan `200 OK` (`meta.idempotent_replay = true`) tanpa menggandakan baris dan tanpa menaikkan `version`, sedangkan pengiriman ulang dengan isi berbeda mengembalikan `409 Conflict` beserta `latest`.
    5. `T5`: Guru ditolak `403` saat mencoba mengisi jurnal pada sesi milik guru/kelas lain atau mengedit jurnal milik guru lain.
    6. `T6`: Guru pengganti (`substitute_employee_id`) pada exception `rescheduled` diizinkan mengisi jurnal sesi pengganti tersebut (`201`), sedangkan guru utama yang sudah digantikan ditolak `403`.
    7. `T7`: Penolakan `422` saat mencoba mencatat jurnal untuk tanggal masa depan (`session_date > today WIB`), hari `holiday`, slot `cancelled`, tanggal asli slot `rescheduled_out`, atau tanggal yang tidak cocok dengan `day_of_week` jadwal reguler.
    8. `T8`: Penegakan `journal_grace_days = 7` WIB: guru ditolak `422` untuk `session_date = today - 8 days`, sedangkan operator (`teaching.manage`) berhasil mencatat jurnal lewat batas waktu dengan menyertakan `correction_reason` (`corrected_by` & `corrected_at` terisi).
    9. `T9`: Operator (`teaching.manage`) wajib menyertakan `correction_reason` (`10–255` karakter) saat mengoreksi jurnal milik guru lain atau melewati grace window (`422` bila kosong).
    10. `T10`: Penolakan mutasi (`422`) pada tahun ajaran terkunci (`locked_at !== null`) bagi guru maupun operator, sedangkan pembacaan `GET` tetap `200`.
    11. `T11`: Optimistic locking `expected_version` basi pada `PUT /api/teaching/journals/{id}` mengembalikan `409 Conflict` beserta snapshot `latest`.
    12. `T12`: Guard konsistensi 10a (`D6`): slot jadwal yang sudah memiliki jurnal menolak `DELETE /api/teaching/schedules/{id}` (`422`), penugasan berjurnal menolak `DELETE /api/teaching/assignments/{id}` (`422`), dan pembuatan exception `cancelled`/`holiday`/`rescheduled` yang menggugurkan sesi berjurnal ditolak `422`.
- **Regresi modul berdekatan (`Academic`, `Access`, `Archives`)**:
  - Perintah: `php artisan test tests/Feature/Academic tests/Feature/Access tests/Feature/Archives`
  - Hasil: **PASS — 147 passed (2,002 assertions) in 195.54s**.
- **Daftar route, kode gaya, lint, dan build frontend**:
  - `php artisan route:list --path=teaching` → **Showing [18] routes**.
  - `./vendor/bin/pint --test` pada seluruh 17 file PHP baru/diubah → **PASS (17 files)**.
  - `npx eslint src/views/teaching/TeachingJournalsView.vue` → **0 errors, 0 warnings**.
  - `npm run build` (di `vue-app`) → **PASS in 1m 3s** (`dist/assets/TeachingJournalsView-hRYNDph5.js 40.07 kB`).
- **Lingkungan UAT terisolasi `.uat-phase10`** (SQLite `.uat-phase10/database.sqlite`, storage `.uat-phase10/storage`, port API `18028` / SPA `14028`, cookie `school_phase10_session`):
  - `php scripts/phase10-uat.php prepare-journals` dijalankan dua kali berturut-turut untuk membuktikan idempotensi:
    - Mempertahankan seluruh data 10a (`101` penugasan, `121` jadwal, `6` exception, `18` pegawai, `15` guru `Pendidik` aktif).
    - Menambahkan akun ke-8 **`uat.guru2`** (role `guru`, ditautkan ke `UAT-P10 Teacher 02`).
    - Membuat **66 baris `teaching_journals`** (`64` sesi reguler sepanjang Oktober 2026 lintas guru termasuk koreksi operator, `1` jurnal sesi `rescheduled` pada Sabtu `2026-09-12` oleh guru pengganti `UAT-P10 Teacher 11`, serta `1` jurnal historis pada tahun ajaran terkunci `2024/2025`), sembari membiarkan sesi awal November 2026 tetap belum terisi untuk pengujian pengisian langsung di UI.
    - Run kedua `prepare-journals` menghasilkan `Teaching journals: 66 total (0 created, 66 reused)`.
  - Probe HTTP nyata terhadap server UAT `.uat-phase10` (`127.0.0.1:18028`) sebelum dihentikan memverifikasi:
    - Guest `GET /api/teaching/sessions` → `401`.
    - `uat.staff` (tanpa `teaching.view`) `GET /api/teaching/sessions` → `403`.
    - `uat.kepsek` (read-only) `GET /api/teaching/journals` → `200` (`total = 66`), `POST /api/teaching/journals` → `403`.
    - `uat.guru` (`UAT-P10 Teacher 01`) `GET /api/teaching/options` → `200` (`self_employee.id = 1`, `today_wib = 2026-11-03`).
    - `uat.guru` mencatat jurnal baru pada sesinya (`2026-11-02`) → `201 Created`, mengirim ulang payload identik → `200 OK` (`meta.idempotent_replay = true`), mengirim POST dengan topik berbeda → `409 Conflict`, mengedit via `PUT` (`expected_version = 1`) → `200 OK` (`version = 2`), mengirim `PUT` versi basi (`expected_version = 1`) → `409 Conflict`.
    - `uat.guru` mencoba mengisi jurnal pada sesi milik `UAT-P10 Teacher 02` → `403 Forbidden`; `uat.guru2` mencoba mengedit jurnal milik `uat.guru` → `403 Forbidden`.
    - Evaluasi hari libur nasional `GET /api/teaching/sessions?school_year_id=1&semester_id=1&date=2026-08-17` → `200 OK` (`effective_sessions = 0`, `excluded_slots = 20`).

## 4. NOT RUN

- **Otomasi klik browser interaktif / screenshot visual (1440×900 & 390×844) dan navigasi keyboard pada sesi ini**: **NOT RUN** secara otomatis di browser; seluruh kontrak endpoint, evaluasi sesi efektif, idempotent retry, otorisasi `own assignment` vs `teaching.manage`, batas waktu 7 hari WIB, optimistic locking `409`, guard tahun terkunci, ESLint (`0 errors`), dan kompilasi produksi Vite (`npm run build` PASS) telah diverifikasi lewat tes fitur dan probe HTTP nyata terhadap `.uat-phase10`, sedangkan pengujian visual/interaksi UI diserahkan melalui checklist manual pemilik `#1–#18` di `PHASE-10B-MANUAL-REVIEW.md`.
- **Suite penuh PHPUnit tanpa filter lintas seluruh fase**: **NOT RUN** karena `Tests\Feature\Backup\MariaDbIntegrationTest` menyentuh database di luar batas akses sesi (`school_access_uat`); verifikasi backend dijalankan terfokus pada `TeachingJournalApiTest` + `TeachingScheduleApiTest` (18 tes / 264 assertions) serta regresi `Academic`, `Access`, dan `Archives` (147 tes / 2.002 assertions) di SQLite in-memory.
- **Race condition MariaDB/InnoDB multi-proses nyata dan dua tab browser nyata untuk `409`**: **NOT RUN** (runtime UAT menggunakan SQLite; konflik `409` diuji melalui request `expected_version` basi dan POST konflik).
- **Subfase 10c (Laporan Guru/Sesi Belum Mengisi Jurnal)**: **NOT RUN / TIDAK DIOTORISASI** pada paket 10b ini.

## 5. Status UAT dan langkah berikutnya

- Server UAT `.uat-phase10` (`127.0.0.1:18028`) **telah dihentikan pada 2026-11-03 WIB** sesuai kebijakan `DEC-27` segera setelah verifikasi HTTP agen selesai: port `18028` dan `14028` terverifikasi bebas listener (`UAT_PORTS_18028_14028_STOPPED_CLEANLY`), layanan Apache/MariaDB operasional tidak disentuh, dan seluruh data `.uat-phase10` (`8` akun, `101` penugasan, `121` jadwal, `6` pengecualian kalender, `66` jurnal mengajar) dipertahankan utuh.
- Perintah untuk menyalakan kembali server UAT `.uat-phase10` saat pemilik ingin menjalankan checklist manual `#1–#18`, serta perintah untuk menghentikannya kembali, tercantum lengkap di `reports/feature-update/PHASE-10B-MANUAL-REVIEW.md`.
- **Penerimaan manual pemilik (`#1–#18` APPROVED, 2026-11-03 WIB)**: pemilik menyatakan **"Pengujian nomor 1-18 sudah berhasil dan saya approved."**; agen tidak mengulang skenario dan item NOT RUN tetap NOT RUN. Port `18028` dan `14028` diverifikasi ulang bebas listener (`PORTS_18028_14028_FREE`), `.uat-phase10` (`8` akun, `101` penugasan, `121` jadwal, `6` exception, `66` jurnal mengajar) dipertahankan sesuai `DEC-27`, serta Apache dan MariaDB tidak disentuh. Langkah berikutnya adalah menyusun rencana rinci **Subfase 10c (Laporan Belum Mengisi Jurnal)**.
