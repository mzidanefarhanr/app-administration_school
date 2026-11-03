# Laporan Subfase 10a — Penugasan dan Jadwal Pembelajaran

Tanggal: implementasi dan verifikasi agen 2026-11-03 WIB; penerimaan manual pemilik 2026-11-03 WIB. Branch: `main`. Baseline induk: `b515cb1` (pencatatan DEC-39 APPROVED di atas paket rencana `a8264ad` dan baseline 09b `f185ca6`); paket implementasi & review: `c8be397`. Status teknis: **IMPLEMENTED — agent-verified (2026-11-03)**. Status review pemilik: **APPROVED (#1–#18, 2026-11-03 WIB; pernyataan `"Pengujian nomor 1-18 sudah berhasil dan saya approved."`)**.
Keputusan pemilik: **DEC-39 APPROVED** (D1–D11, 2026-11-03 WIB; pernyataan **"Saya setuju D1-D11."**); **DEC-10 CLOSED** melalui D3, D5, dan D8.
Otorisasi: implementasi **Subfase 10a** sesuai `reports/feature-update/PHASE-10-PLAN.md` (S0–S8) telah diterima pemilik. Fase 09 (09a + 09b) tetap **OWNER APPROVED (#1–#18, 2026-11-03 WIB)**. Subfase **10b** dan **10c** menunggu persetujuan rencana terpisah.

## 1. Outcome dan cakupan

Subfase 10a membangun fondasi penugasan mengajar, jadwal pelajaran mingguan dengan deteksi bentrok tiga dimensi, dan pengecualian kalender sesuai keputusan `DEC-39` (`D1–D11`) dan penutupan `DEC-10`:

- **Skema tiga tabel additive (`D2–D5`)**:
  - `teaching_assignments`: menghubungkan `(school_year_id, semester_id, school_rombel_id, subject_id)` secara unik ke seorang guru (`employee_id`), dilengkapi `hours_per_week` (`1..24`), `status` (`active`|`inactive`), `notes` opsional, `version` untuk optimistic locking (`expected_version` / `409 Conflict`), dan `created_by`/`updated_by`.
  - `teaching_schedules`: menghubungkan satu `teaching_assignment_id` ke satu atau lebih slot jadwal mingguan (`day_of_week` `1..6` = Senin..Sabtu, `period_start..period_end` `1..12`, `start_time..end_time` `HH:MM`, `room` opsional beserta `room_normalized` huruf besar untuk deteksi bentrok ruang yang case-insensitive, `status` `active`|`inactive`, `notes` opsional, dan `version`).
  - `teaching_schedule_exceptions`: mencatat pengecualian kalender bertanggal (`exception_type` = `holiday` | `cancelled` | `rescheduled`, `exception_date`, `school_rombel_id` nullable untuk libur satu sekolah vs spesifik kelas, `teaching_schedule_id` nullable untuk `cancelled`/`rescheduled`, kolom target `rescheduled_date`, `rescheduled_period_start..end`, `rescheduled_start_time..end_time`, `rescheduled_room`/`rescheduled_room_normalized`, `substitute_employee_id` opsional, `reason` wajib, dan `version`).
- **Kelayakan guru dan konsistensi tahun ajaran (`D2`)**:
  - `Employee::scopeEligibleTeacher()` memastikan hanya pegawai aktif (`deleted_at IS NULL`, `exit_date IS NULL`) dengan `employee_type` kanonik `Pendidik` yang dapat ditugaskan sebagai guru pengampu maupun guru pengganti (`substitute_employee_id`). Staf `Tenaga Kependidikan`, guru yang sudah memiliki `exit_date`, atau profil terarsip ditolak `422`.
  - `school_rombel_id` wajib milik `school_year_id` yang sama (`422` bila lintas tahun ajaran).
- **Deteksi bentrok tiga dimensi (`D4`)**:
  - `TeachingScheduleService::assertNoScheduleCollisions()` memeriksa seluruh slot jadwal `active` pada `(school_year_id, semester_id, day_of_week)` yang sama dengan penugasan `active` yang beririsan secara waktu (`new.start_time < existing.end_time AND new.end_time > existing.start_time`) **atau** jam pelajaran (`new.period_start <= existing.period_end AND new.period_end >= existing.period_start`).
  - Menolak dengan `422` dan pesan deskriptif (menyebutkan nama guru, kelas, mata pelajaran, hari, jam ke-, dan rentang pukul) apabila terjadi:
    1. **Bentrok guru (`teaching_assignment_id`)**: guru yang sama mengajar di dua kelas berbeda pada waktu beririsan.
    2. **Bentrok kelas (`start_time`)**: kelas yang sama memiliki dua mata pelajaran pada waktu beririsan.
    3. **Bentrok ruangan (`room`)**: ruangan non-kosong yang sama (`room_normalized` case-insensitive, mis. `lab-komp-1` vs `LAB-KOMP-1`) dipakai dua kelas pada waktu beririsan.
  - Deteksi bentrok juga dijalankan ulang saat mengaktifkan kembali slot jadwal (`inactive -> active`), mengaktifkan kembali penugasan (`inactive -> active`), atau mengganti guru pada penugasan yang sudah memiliki slot jadwal aktif.
- **Validasi pengecualian kalender (`D5`)**:
  - `exception_date` (dan `rescheduled_date`) wajib berada di dalam rentang `school_years.start_date..end_date`.
  - `holiday`: `teaching_schedule_id` wajib kosong; `school_rombel_id = null` berlaku untuk seluruh sekolah, atau diisi kelas milik tahun ajaran tersebut; duplikat pada tanggal dan cakupan yang sama ditolak `422`.
  - `cancelled` & `rescheduled`: wajib merujuk `teaching_schedule_id` milik tahun ajaran + semester yang sama, dan `exception_date` wajib jatuh pada hari (`day_of_week`) yang sesuai dengan slot jadwal tersebut (`422` bila hari tidak cocok).
  - `rescheduled`: wajib menyertakan `rescheduled_date` (Senin..Sabtu, dalam rentang tahun ajaran, tidak jatuh pada hari `holiday` sekolah maupun kelas tersebut), `rescheduled_period_start..end`, dan `rescheduled_start_time..end_time`; `assertNoRescheduleCollisions()` memverifikasi tidak ada bentrok guru efektif (`substitute_employee_id ?? assignment.employee_id`), kelas, atau ruangan terhadap jadwal reguler aktif (yang tidak sedang `cancelled`/`rescheduled` pada tanggal tujuan) maupun terhadap reschedule lain pada `rescheduled_date`.
- **Perlindungan tahun ajaran terkunci, optimistic locking, dan delete guard (`D6`, `D9`)**:
  - Seluruh operasi tulis (`POST`/`PUT`/`PATCH`/`DELETE`) mengunci baris `SchoolYear` dengan `lockForUpdate()` di dalam transaksi DB dan menolak mutasi dengan `422` bila `school_years.locked_at IS NOT NULL`.
  - Update penugasan, jadwal, dan exception mewajibkan `expected_version` (`409 Conflict` beserta snapshot `latest` bila basi).
  - Guard penghapusan (`422`) diterapkan secara berjenjang pada `SchoolYearController::destroy`, `SchoolRombelController::destroy` (dan perubahan tahun ajaran pada `SchoolRombelController::update`), `TeachingAssignmentController::destroy` (bila masih memiliki `teaching_schedules`), serta `TeachingScheduleController::destroy` (bila masih memiliki `teaching_schedule_exceptions`).
- **Sinkronisasi hak akses (`D7`) & privasi audit (`D10`)**:
  - `AbilityCatalog` dan migration idempoten `2029_10_01_000004_sync_teaching_abilities_to_default_roles.php` memberikan `teaching.manage` ke `operator-sekolah` (melengkapi `admin-sistem`) dan `teaching.view` ke `guru` (melengkapi `kepala-sekolah` dan `wakil-kesiswaan`).
  - `TeachingAssignment`, `TeachingSchedule`, dan `TeachingScheduleException` mendefinisikan `$auditableAttributes` eksplisit pada `Loggable` yang mengecualikan field teks bebas `notes` dan `reason` dari tabel `activities`.
- **Antarmuka pengguna Vue (`/public/teaching/schedules`)**:
  - Komponen `TeachingSchedulesView.vue` menyediakan pemilih konteks tahun ajaran + semester (otomatis memilih periode aktif), banner peringatan read-only saat melihat tahun ajaran terkunci (`2024/2025`), dan 3 tab terintegrasi (**Weekly Timetable** dengan toggle tampilan **Matrix (Mon–Sat)** vs **Paginated List**, **Teaching Assignments**, serta **Schedule Exceptions**), dialog CRUD dengan preset jam pelajaran `1..12` otomatis, banner detail bentrok `422`, dan tombol **Reload Latest** saat terjadi `409 Conflict`.

Tidak dikerjakan pada 10a (non-goals `D1`/`D11`): tabel sesi/jurnal/absensi (`10b`), perhitungan rekap guru belum mengisi jurnal (`10c`), perubahan dependency/lockfile, migrasi database operasional/MariaDB, atau perubahan service/`.env`.

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File | Perubahan | Schema/izin |
|---|---|---|
| `config/teaching.php` | Konfigurasi `days_of_week` (1..6), `period_presets` (1..12 beserta `start_time`/`end_time`), `max_period=12`, `max_hours_per_week=24`, `exception_types`, dan `journal_grace_days=7` | Tanpa `.env` baru |
| `app/Support/AbilityCatalog.php` | Menambahkan `teaching.manage` pada `operator-sekolah` dan `teaching.view` pada `guru` | RBAC katalog |
| `database/migrations/2029_10_01_000001_create_teaching_assignments_table.php` | Tabel `teaching_assignments` + unique `(school_year_id, semester_id, school_rombel_id, subject_id)` + FK `restrictOnDelete` | Additive |
| `database/migrations/2029_10_01_000002_create_teaching_schedules_table.php` | Tabel `teaching_schedules` + index deteksi bentrok waktu dan `room_normalized` | Additive |
| `database/migrations/2029_10_01_000003_create_teaching_schedule_exceptions_table.php` | Tabel `teaching_schedule_exceptions` (`holiday`/`cancelled`/`rescheduled`) + index tanggal/jadwal | Additive |
| `database/migrations/2029_10_01_000004_sync_teaching_abilities_to_default_roles.php` | Sinkronisasi idempoten `teaching.view` dan `teaching.manage` ke role bawaan | Additive/idempoten |
| `app/Models/TeachingAssignment.php` | Model + relasi + allowlist `$auditableAttributes` tanpa `notes` | `Loggable` allowlist |
| `app/Models/TeachingSchedule.php` | Model + helper `normalizeRoom()` + relasi + allowlist `$auditableAttributes` tanpa `notes` | `Loggable` allowlist |
| `app/Models/TeachingScheduleException.php` | Model + konstanta tipe + relasi + allowlist `$auditableAttributes` tanpa `reason` | `Loggable` allowlist |
| `app/Models/Employee.php` | `scopeEligibleTeacher()` (`Pendidik` aktif, `exit_date IS NULL`) + relasi `teachingAssignments()` | Additive |
| `app/Http/Controllers/SchoolYearController.php` | Guard `destroy` menolak hapus tahun ajaran yang direferensikan `TeachingAssignment` / `TeachingScheduleException` (`422`) | Delete guard |
| `app/Http/Controllers/SchoolRombelController.php` | Guard `update` (pindah tahun ajaran) dan `destroy` menolak bila kelas direferensikan `TeachingAssignment` / `TeachingScheduleException` (`422`) | Delete guard |
| `app/Services/TeachingScheduleService.php` | Layanan transaksional CRUD penugasan, jadwal, dan exception; `lockForUpdate` tahun ajaran; penolakan tahun `locked`; `expected_version`/`409`; deteksi bentrok 3 dimensi (jadwal reguler & reschedule); serialisasi API & matriks | Service domain |
| `app/Http/Requests/TeachingAssignmentStoreRequest.php` & `TeachingAssignmentUpdateRequest.php` | Validasi input penugasan mengajar & `expected_version` | `422` |
| `app/Http/Requests/TeachingScheduleStoreRequest.php` & `TeachingScheduleUpdateRequest.php` | Validasi input slot jadwal mingguan (`day_of_week`, `period_start..end`, `start_time..end_time`, `room`) & `expected_version` | `422` |
| `app/Http/Requests/TeachingScheduleExceptionStoreRequest.php` & `TeachingScheduleExceptionUpdateRequest.php` | Validasi input pengecualian kalender (`holiday`/`cancelled`/`rescheduled`) & `expected_version` | `422` |
| `app/Http/Controllers/TeachingOptionController.php` | `GET /api/teaching/options` (`teaching.view`) | 1 route |
| `app/Http/Controllers/TeachingAssignmentController.php` | `index` (`teaching.view`), `store`/`update`/`destroy` (`teaching.manage`) | 4 route |
| `app/Http/Controllers/TeachingScheduleController.php` | `index` + `matrix=1` (`teaching.view`), `store`/`update`/`destroy` (`teaching.manage`) | 4 route |
| `app/Http/Controllers/TeachingScheduleExceptionController.php` | `index` (`teaching.view`), `store`/`update`/`destroy` (`teaching.manage`) | 4 route |
| `routes/api.php` | Pendaftaran grup `/api/teaching/*` (total **13 route**) | `can:teaching.view` / `can:teaching.manage` |
| `tests/Feature/Teaching/TeachingScheduleApiTest.php` | 6 tes komprehensif / 116 assertions mencakup seluruh matriks `T1–T14` | 6 tes baru |
| `scripts/phase10-uat.php` | Launcher terisolasi `.uat-phase10` (`init`, `prepare`, `prepare-teaching`, `status`) pada API `18028` / SPA `14028` | Hanya `.uat-phase10` |
| `vue-app/src/views/teaching/TeachingSchedulesView.vue` | Halaman 3 tab (Weekly Timetable Matrix/List, Teaching Assignments, Schedule Exceptions) + dialog CRUD + preset jam + `409` Reload Latest | Guard `teaching.view` |
| `vue-app/src/router/index.js` & `vue-app/src/layout/AppMenu.vue` | Route `/public/teaching/schedules` (alias `/teaching/schedules`) dan item menu sidebar **Teaching → Assignments & Timetable** | `requiresAbility: 'teaching.view'` |

Kontrak **13 endpoint baru `/api/teaching/*`** (detail lengkap di `docs/API-CONTRACTS.md`):

| # | Method | Endpoint | Izin | Catatan |
|---|---|---|---|---|
| 1 | GET | `/api/teaching/options` | `teaching.view` | Periode aktif, daftar tahun ajaran (beserta `is_locked`), semester, kelas, mapel, guru `Pendidik` aktif, hari `1..6`, preset jam `1..12`, tipe exception, dan `can {view, manage}` |
| 2 | GET | `/api/teaching/assignments` | `teaching.view` | Daftar berpaginasi penugasan mengajar + filter tahun/semester/kelas/mapel/guru/status/search |
| 3 | POST | `/api/teaching/assignments` | `teaching.manage` | Buat penugasan baru; tolak tahun locked, kelas beda tahun, non-`Pendidik`/keluar, dan duplikat (`422`) |
| 4 | PUT/PATCH | `/api/teaching/assignments/{teachingAssignment}` | `teaching.manage` | Update guru/jam/status/catatan + `expected_version` (`409` bila basi); cek ulang bentrok slot aktif bila guru berubah atau diaktifkan kembali (`422`) |
| 5 | DELETE | `/api/teaching/assignments/{teachingAssignment}` | `teaching.manage` | Hapus penugasan bila belum memiliki jadwal (`422` bila sudah memiliki slot jadwal) |
| 6 | GET | `/api/teaching/schedules` | `teaching.view` | Daftar berpaginasi jadwal mingguan + mode `matrix=1` dikelompokkan per `day_of_week` (`1..6`) |
| 7 | POST | `/api/teaching/schedules` | `teaching.manage` | Buat slot jadwal mingguan + deteksi bentrok tiga dimensi (**guru**, **kelas**, **ruangan**) (`422`) |
| 8 | PUT/PATCH | `/api/teaching/schedules/{teachingSchedule}` | `teaching.manage` | Update slot jadwal + `expected_version` (`409`) + cek ulang bentrok tiga dimensi (`422`) |
| 9 | DELETE | `/api/teaching/schedules/{teachingSchedule}` | `teaching.manage` | Hapus slot jadwal bila belum memiliki exception kalender (`422` bila sudah memiliki exception) |
| 10 | GET | `/api/teaching/exceptions` | `teaching.view` | Daftar berpaginasi exception kalender (`holiday`, `cancelled`, `rescheduled`) |
| 11 | POST | `/api/teaching/exceptions` | `teaching.manage` | Buat exception kalender + validasi rentang tanggal tahun ajaran, kecocokan `day_of_week`, blokir reschedule ke `holiday`, dan deteksi bentrok reschedule (`422`) |
| 12 | PUT/PATCH | `/api/teaching/exceptions/{teachingScheduleException}` | `teaching.manage` | Update exception kalender + `expected_version` (`409`) + validasi penuh |
| 13 | DELETE | `/api/teaching/exceptions/{teachingScheduleException}` | `teaching.manage` | Hapus exception kalender pada tahun ajaran unlocked |

## 3. Bukti verifikasi

- **Tes fitur Teaching (`T1–T14`)**:
  - Perintah: `php artisan test tests/Feature/Teaching/TeachingScheduleApiTest.php`
  - Hasil: **PASS — 6 passed (116 assertions) in 6.57s**.
  - Cakupan `TeachingScheduleApiTest`:
    1. `T1`: Pendaftaran 13 route `/api/teaching/*` dan matriks RBAC (`guest` → `401`, akun nonaktif → `403`, staf tanpa `teaching.view` → `403`, `guru`/`kepala-sekolah`/`wakil-kesiswaan` → `200` pada GET dengan `can.manage=false` dan `403` pada POST/PUT/DELETE, `operator-sekolah`/`admin-sistem` → akses penuh `200`/`201`).
    2. `T2`: Operator membuat dan memperbarui `TeachingAssignment` (`version` naik `1 -> 2`), dan allowlist audit `activities` tidak menyimpan teks bebas `notes`.
    3. `T3`: Penolakan `422` untuk kelas yang berbeda `school_year_id`, pegawai `Tenaga Kependidikan`, guru `Pendidik` yang sudah keluar (`exit_date` terisi), dan duplikat `(school_year_id, semester_id, school_rombel_id, subject_id)`.
    4. `T4–T8`: Pembuatan slot jadwal mingguan, pengelompokan `matrix=1` per hari (`1..6`), deteksi bentrok **guru** (`422`), deteksi bentrok **kelas** (`422`), deteksi bentrok **ruangan** case-insensitive (`Lab-Komp-1` vs `LAB-KOMP-1` → `422`, sedangkan ruangan kosong `null` tidak memicu bentrok palsu), serta penolakan bentrok saat mengaktifkan kembali slot `inactive -> active` atau mengganti guru pada penugasan yang memiliki jadwal aktif (`422`).
    5. `T9–T10`: Pembuatan dan validasi `TeachingScheduleException` (`holiday`, `cancelled`, `rescheduled`): penolakan tanggal di luar rentang tahun ajaran (`422`), penolakan `cancelled`/`rescheduled` bila tanggal tidak jatuh pada `day_of_week` slot jadwal (`422`), pemblokiran reschedule ke tanggal `holiday` (`422`), deteksi bentrok reschedule pada tanggal/jam tujuan (`422`), serta allowlist audit `activities` tanpa `reason`.
    6. `T11–T14`: Penolakan mutasi pada tahun ajaran terkunci (`locked_at IS NOT NULL` → `422`) sambil tetap mengizinkan pembacaan `GET`, optimistic locking `expected_version` basi (`409` dengan `latest`), delete guard berjenjang pada `SchoolYear`, `SchoolRombel`, `TeachingAssignment`, dan `TeachingSchedule` (`422`), serta paginasi dan pemfilteran pada dataset **≥100 baris** (`105` jadwal).
- **Regresi modul berdekatan (`Academic`, `Access`, `Archives`)**:
  - Perintah: `php artisan test tests/Feature/Academic tests/Feature/Access tests/Feature/Archives`
  - Hasil: **PASS — 147 passed (2,002 assertions) in 199.60s**.
- **Daftar route, kode gaya, lint, dan build frontend**:
  - `php artisan route:list --path=teaching` → **Showing [13] routes**.
  - `./vendor/bin/pint` pada seluruh 26 file PHP baru/diubah → **PASS (26 files)**.
  - `npx eslint src/views/teaching/TeachingSchedulesView.vue src/router/index.js src/layout/AppMenu.vue` → **0 errors**.
  - `npm run build` (di `vue-app`) → **PASS in 35.86s** (`dist/assets/TeachingSchedulesView-BCURfAIy.js 53.76 kB`).
- **Lingkungan UAT terisolasi `.uat-phase10`** (SQLite `.uat-phase10/database.sqlite`, storage `.uat-phase10/storage`, port API `18028` / SPA `14028`, cookie `school_phase10_session`):
  - `php scripts/phase10-uat.php init`, `prepare`, dan `prepare-teaching` (dijalankan dua kali berturut-turut untuk membuktikan idempotensi):
    - Tahun ajaran aktif: `2026/2027` (`2026-07-01` s.d. `2027-06-30`, semester aktif `Semester I`).
    - Tahun ajaran terkunci: `2024/2025` (`2024-07-01` s.d. `2025-06-30`, `locked_at = 2025-07-15 10:00:00`) beserta 1 kelas, 1 penugasan, dan 1 slot jadwal historis.
    - Pegawai: **18 pegawai** (**15 guru `Pendidik` aktif**, 2 staf `Tenaga Kependidikan` aktif, 1 guru `Pendidik` keluar `exit_date = 2025-12-31`; `UAT-P10 Teacher 01` ditautkan ke akun `uat.guru`).
    - Penugasan mengajar: **101 baris** (`100` di `2026/2027` lintas 10 kelas × 10 mata pelajaran + `1` di `2024/2025`). Run kedua menghasilkan `0 created, 100 reused`.
    - Slot jadwal mingguan: **121 baris** (`120` di `2026/2027` lintas Senin..Sabtu termasuk ruang `LAB-FISIKA`, `LAB-KIMIA`, `LAB-BIOLOGI`, `LAB-KOMP-1`, `R-101`..`R-110` + `1` di `2024/2025`). Run kedua menghasilkan `0 created, 120 reused`.
    - Pengecualian kalender: **6 baris** (`2` `holiday` sekolah/kelas, `2` `cancelled`, `2` `rescheduled`). Run kedua menghasilkan `0 created, 6 reused`.
  - Probe HTTP nyata terhadap server UAT `.uat-phase10` (`127.0.0.1:18028`) sebelum dihentikan memverifikasi:
    - Guest `GET /api/teaching/options` → `401`.
    - `uat.staff` (tanpa `teaching.view`) `GET /api/teaching/options` → `403`.
    - `uat.guru` (`teaching.view` saja) `GET /api/teaching/options` → `200` (`can.manage = false`), `POST /api/teaching/assignments` → `403`.
    - `uat.operator` (`teaching.manage`) `GET /api/teaching/options` → `200` (`can.manage = true`, `15` eligible teachers, `10` subjects), `GET /api/teaching/schedules?school_year_id=1&semester_id=1&matrix=1&per_page=100` → `200` (`total = 120`), `GET /api/teaching/assignments` → `200` (`total = 100`), `GET /api/teaching/exceptions` → `200` (`total = 6`).
    - Bentrok jadwal `POST /api/teaching/schedules` pada Senin Jam 1–2 (`07:00–08:30`) → `422` (`Teacher collision: UAT-P10 Teacher 01 is already scheduled for Matematika in class UAT-P10 X-MIPA-1 on Monday, Period 1–2 (07:00–08:30).`).
    - Konflik versi basi `PUT /api/teaching/assignments/2` (`expected_version = 999`) → `409` (`has_latest = true`).
    - Guard hapus `DELETE /api/teaching/assignments/2` (memiliki 2 slot jadwal) → `422`.
    - Penolakan tahun ajaran terkunci `POST /api/teaching/exceptions` (`school_year_id = 2`) → `422`.

## 4. NOT RUN

- **Otomasi klik browser interaktif / screenshot visual (1440×900 & 390×844) dan navigasi keyboard pada sesi ini**: **NOT RUN** secara otomatis di browser; seluruh kontrak endpoint, deteksi bentrok, optimistic locking `409`, guard tahun terkunci, ESLint (`0 errors`), dan kompilasi produksi Vite (`npm run build` PASS) telah diverifikasi lewat tes fitur dan probe HTTP nyata terhadap `.uat-phase10`, sedangkan pengujian visual/interaksi UI diserahkan melalui checklist manual pemilik `#1–#18` di `PHASE-10A-MANUAL-REVIEW.md`.
- **Suite penuh PHPUnit tanpa filter lintas seluruh fase**: **NOT RUN** karena `Tests\Feature\Backup\MariaDbIntegrationTest` menyentuh database di luar batas akses sesi (`school_access_uat`); verifikasi backend dijalankan terfokus pada `tests/Feature/Teaching/TeachingScheduleApiTest.php` (6 tes / 116 assertions) serta regresi `Academic`, `Access`, dan `Archives` (147 tes / 2.002 assertions) di SQLite in-memory.
- **Race condition MariaDB/InnoDB multi-proses nyata dan dua tab browser nyata untuk `409`**: **NOT RUN** (runtime UAT menggunakan SQLite; konflik `409` diuji melalui request `expected_version` basi).
- **Subfase 10b (Sesi, Jurnal Mengajar, dan Absensi Mapel) & Subfase 10c (Laporan Belum Mengisi Jurnal)**: **NOT RUN / TIDAK DIOTORISASI** pada paket 10a ini.

## 5. Status UAT dan langkah berikutnya

- Server UAT `.uat-phase10` (`127.0.0.1:18028`) **telah dihentikan pada 2026-11-03 WIB** sesuai kebijakan `DEC-27` segera setelah verifikasi HTTP agen selesai: proses PHP API (PID `3332`) telah dihentikan, port `18028` dan `14028` terverifikasi bebas listener, layanan Apache/MariaDB operasional tidak disentuh, dan seluruh data `.uat-phase10` (`101` penugasan, `121` jadwal, `6` pengecualian kalender, `18` pegawai, `7` akun) dipertahankan utuh.
- Perintah untuk menyalakan kembali server UAT `.uat-phase10` saat pemilik ingin menjalankan checklist manual `#1–#18`, serta perintah untuk menghentikannya kembali, tercantum lengkap di `reports/feature-update/PHASE-10A-MANUAL-REVIEW.md`.
- **Penerimaan manual pemilik (`#1–#18` APPROVED, 2026-11-03 WIB)**: pemilik menyatakan **"Pengujian nomor 1-18 sudah berhasil dan saya approved."**; agen tidak mengulang skenario dan item NOT RUN tetap NOT RUN. Port `18028` dan `14028` diverifikasi ulang bebas listener (`PORTS_18028_14028_FREE`), `.uat-phase10` (`101` penugasan, `121` jadwal, `6` exception) dipertahankan sesuai `DEC-27`, serta Apache dan MariaDB tidak disentuh. Langkah berikutnya adalah menyusun rencana rinci **Subfase 10b (Sesi dan Jurnal Mengajar)**.
