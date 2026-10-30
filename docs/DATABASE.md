# Database: baseline dan usulan

## Correction addendum — 2026-10-29 (dedicated SQLite 04b UAT)

Current baseline `main` at `d10f7b8` plus uncommitted corrections. Historical installation/old-UAT claims below do not describe the new runtime: 04a/04b additive migrations are now applied through **normal migrations** to the new root `.uat-phase04b/database.sqlite` only. No `migrate:fresh`, reset/restore, MariaDB, legacy/main environment, existing service or dependency change. 04a and **04b are owner-accepted**: 04b checklist #1–#18 APPROVED per the owner's 2026-10-30 WIB statement, which does not authorize legacy/production migration or deployment.

Launcher `laravel-api/scripts/phase04b-uat.php` supports `init` (missing runtime only, refuses overwrite), `prepare` (normal migrations and idempotent fixtures), `status` (guard/counts). It loads dedicated `.uat-phase04b/.env` and private storage with cookie `school_phase04b_session`; exact SQLite guard rejects database URLs, caches and conflicting process overrides. Never root `artisan --env=uat`: old target is excluded. SPA/API are `127.0.0.1:14022` / `127.0.0.1:18022`; private runtime and `UAT-ACCOUNTS.local.md` remain ignored, no password literal in Git/docs.

Baseline fixtures: 100 `Synthetic Teacher ...` profiles with subjects, 100 `Synthetic Staff ...` without subjects across 15 non-teaching jobs. Current totals after one browser-created profile each: **101 teachers / 101 staff / 103 students / 100 synthetic schools**. School NPSNs are strings in the `04` namespace; no real-school import. Preparation preserves edited/archived rows and initializes only the first five UAT accounts when accounts are empty; employee fixtures create no additional accounts or password resets.

Teachers = Guru only (canonical `Pendidik`); Employees = non-teaching (canonical `Tenaga Kependidikan`). Classification is solely `employee_type_id`, never job-title restrictions; canonical jobs including head-of-school positions remain unchanged. English 17-domain/243-label JSON presentation and frontend `display_name` enrichment do **not** rename database references or IDs/user/proper data. Unresolved legacy employee list names remain null/blank. Employee list/export contain name/NUPTK/gender, list exposes linkage metadata, and sensitive data remains manage-detail only; do not describe these payloads as zero PII.

Latest explicit seven-file SQLite run **96 tests/1738 assertions PASS** and scoped operator browser/separate three-role HTTP checks are recorded in `PHASE-04B-REPORT.md`; earlier overlapping runs are not aggregated and historical browser NOT RUN is preserved. Repeat normal UAT preparation retained counts/edits/archives, without credential reset. **04b MariaDB DDL/unique/InnoDB concurrency NOT RUN**: MariaDB available, but excluded targets not accessed. SQLite results are not driver-concurrency or production-migration evidence.

## Status (historical baseline; current changes above and below)
Source review migration/model, bukan inspeksi database hidup. Baseline b02a87f; HEAD audit b699fec (kode identik). MySQL pada contoh environment; schema aktual instalasi pemilik harus diperiksa Fase 00.
**Isolasi uji (Fase 01a/01c):** tes otomatis berjalan pada SQLite in-memory via `laravel-api/.env.testing` + `phpunit.xml` (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `force="true"`) dan disk storage pengujian terisolasi (`FILESYSTEM_DISK=testing` mengarah ke `storage/framework/testing/disks/testing`) dengan fail-safe di `tests/CreatesApplication.php` (`guardAgainstUnsafeTestDatabase` dan `guardAgainstUnsafeStorage`) yang membatalkan suite bila koneksi bukan sqlite in-memory atau disk storage mengarah ke storage operasional/publik. Database operasional `backdata_administrations` dan storage publik TIDAK dipakai untuk tes. Koneksi port 3306 terbuka bukan bukti koneksi Laravel/DB.
Pada Fase 01c, validasi keunikan `name` pada pembuatan akun dihapus dari application layer (nama boleh sama/homonim), NIK divalidasi tepat 16 digit string, dan password minimal 12 karakter (DEC-16).
Nama tahun pada migration mencapai 2029; Laravel menentukan urutan dari nama file, bukan tanggal bisnis. Migration baru Fase 01a memakai prefix `2029_07_20_*` agar berurutan setelah migration 2029 yang ada.

## Tabel domain aktual
| Tabel | Kolom/relasi penting | Catatan |
|---|---|---|
| users | name, email, username, password, nik, type_user_id, status_user_id, version, first_login_at, status_active | email/username/nik unique; dua field terakhir boolean; soft delete |
| type_users / status_users | name | Seeder Admin/User dan Aktif/Tidak Aktif |
| students | nik, nis, nisn, birthdate, age; gender/religion/education/blood/profession/village FK; keluarga; check_kjp/pip/ppdbbersama/bpms; archive_reason (Fase 03b) | Identitas unique nullable (termasuk record terarsip); umur dihitung dinamis dari birthdate (Asia/Jakarta, DEC-20); soft delete; `archive_reason` = alasan arsip administratif (Fase 03b) |
| school_rombels | school_level_id, school_year_id, student_major_id, class_teach_id, name, version | Wali kelas terkait users.id |
| student_rombels | student_id → students.id (Fase 03a); student_nik (legacy nullable); school_rombel → school_rombels.id; status/entry/exit/graduation; archived_at + archive_reason (Fase 03b) | Relasi primer student_id; trigger DB menegakkan max 1 rombel aktif per siswa/tahun (DEC-20) dan menolak keanggotaan aktif untuk siswa terarsip (DEC-21); `archived_at` menonaktifkan keanggotaan secara administratif tanpa mengubah status/mutasi keluar; soft delete |
| employees | name/nik/archive_reason (04b); user_id → users.id nullable unique nullOnDelete (04a), user_nik legacy; version; employee status/type/employment/subject, religion/district/marital/gender; biodata dan pendidikan | Independent profile; name required by API but nullable schema, nik string nullable unique including archived rows; legacy fallback gated; subject single/nullable schema, required Pendidik and prohibited Tenaga Kependidikan in API |
| school_profiles | pendidikan sekolah, kepala sekolah, tahun ajaran, nomor/kontak sekolah | Detail sumber migration dan SchoolProfile model |
| subjects | name, description | Referensi mata pelajaran, bukan jurnal |
| school_years / semesters / school_levels / student_majors / phase_statuses | referensi akademik | Jangan menganggap seluruh referensi memiliki UI CRUD lengkap |
| student_statuses / student_entries | referensi status siswa dan jenis masuk | Status tampilan siswa memakai ID numerik; petakan label sebelum ubah |
| employee_statuses / employee_types / employments / marital_statuses | referensi kepegawaian | Hubungan ke employee |
| provinces / regencies / districts / villages | hierarki wilayah | Relasi bertingkat untuk alamat |
| education_schools / education_levels | npsn dan referensi pendidikan | Sekolah dirujuk memakai npsn |
| genders / religions / blood_types / professions / family_statuses | referensi biodata | Identitas/domain berbeda dari role |
| activities | user_id, table_name, record_id, before/after/new/delete JSON, information enum, timestamps | Append-only immutable via Eloquent booted guard; relasi User::withTrashed() menjaga riwayat historis saat akun pengguna di-soft delete; client create/modify/delete dinonaktifkan (DEC-17) |
| password_reset_tokens / personal_access_tokens / failed_jobs | tabel framework | Jangan masukkan token ke evidence |
| roles (Fase 01a) | name, slug, description, is_protected, version, soft delete | Peran baru terpisah dari type_users; `is_protected` melindungi Admin Sistem |
| abilities (Fase 01a) | name unik (mis. students.export), group, description, is_sensitive | Katalog kemampuan bernama; di-seed dari AbilityCatalog |
| ability_role (Fase 01a) | role_id, ability_id, unique komposit | Pemetaan peran→kemampuan yang dapat diatur admin |
| role_user (Fase 01a) | user_id, role_id, assigned_by, unique komposit, FK cascade | Penetapan peran; auto-map legacy hanya `Admin`→`admin-sistem` (DEC-14); `User` tidak otomatis; penetapan manual admin tidak dihapus |

## Kesenjangan model–schema & Status Penyelarasan
- `Student.php`: Atribut `check_bpms` telah ditambahkan ke `$fillable`. Umur dihitung dinamis via accessor `age` / `calculated_age` dari `birthdate` (Asia/Jakarta, DEC-20).
- `StudentRombel.php`: Kolom `student_id` telah ditambahkan via migration `2029_07_23_000001` (additive). Model mengaktifkan trait `Loggable` dengan allowlist audit minimalkan PII. Atribut `latest_student_graduation_date`, `latest_information`, `check_dapodik`, dan `informations_dapodik` diselaraskan ke `$fillable`.
- `Employee.php`: Kolom `user_id`, `user_id_resolved_at`, `account_unlinked_at`, dan `version` ditambahkan via migration `2029_07_24_000001` (additive, DEC-04); kolom `legacy_profile_at` ditambahkan via `2029_07_25_000001`; kolom `name`, `nik`, dan `archive_reason` ditambahkan via `2029_07_26_000001` (additive, DEC-24). Relasi utama `user()` berbasis `user_id` (nullOnDelete). Fallback NIK hanya berlaku untuk baris legacy bertanda `legacy_profile_at`; profil pasca-04a tidak pernah memakai fallback. Relasi typo `relogion` diperbaiki menjadi `religion()` dengan alias kompatibilitas `relogion()`. Allowlist audit ketat (`employee_status_id`, `employee_type_id`, `employment_id`, `subject_id`, `gender_id`, `religion_id`, `tmt_employee`, `exit_date`, `version`, `user_id`) membatasi PII; nama, NIK/NPWP/KK, NUPTK, ibu kandung, pasangan, kontak, alamat, nilai/IPK ijazah, dan `archive_reason` dikecualikan.

## Usulan migration & status implementasi
- **Subfase 03a (SELESAI)**: Penambahan `student_id` pada `student_rombels` (FK restrictOnDelete, cascadeOnUpdate); pelonggaran nullability `student_nik`; backfill idempotent berbasis NIK tunggal; dan penegakan trigger database 1 rombel aktif per siswa per tahun ajaran.
- **Subfase 03b (SELESAI & DITUTUP)**: Migration `2029_07_23_000002` menambahkan `students.archive_reason` serta `student_rombels.archived_at` + `student_rombels.archive_reason`, dan memperbarui trigger agar mengabaikan baris `archived_at IS NOT NULL` serta menolak keanggotaan aktif untuk siswa terarsip (`deleted_at IS NOT NULL`). Arsip administratif tidak mengubah `student_status_id` maupun `latest_student_exit_date`.
- **Subfase 03d (SELESAI & DITUTUP)**: Endpoint `assign-rombel` dan `transfer-rombel` ber-row-lock InnoDB dan versioning atomik (DEC-23).
- **Subfase 04a (DITERIMA PEMILIK 2026-10-29 WIB; additive migrations applied to new isolated SQLite UAT only, not old UAT/production)**: Migration `2029_07_24_000001` menambahkan `employees.user_id` (nullable bigint unsigned FK ke `users.id` nullOnDelete, unique), `employees.user_id_resolved_at`, `employees.account_unlinked_at`, `employees.version` (default 1). Pelonggaran nullability `user_nik` dan `subject_id` secara additive (DEC-04). `down()` menolak rollback sebelum DDL bila ada baris `user_nik`/`subject_id` NULL atau `user_id` terhubung; rebuild SQLite dibungkus transaksi. Migration `2029_07_25_000001` menambahkan `employees.legacy_profile_at` dan menandai baris pra-04a; hanya baris itu yang memakai fallback NIK. Backfill idempotent `EmployeeBackfillService` + command `app:employee-account-backfill` (dry-run, keluaran agregat tanpa PII), mengecualikan akun soft-deleted dan atomik.
- **Subfase 04b (IMPLEMENTED 2026-10-29; migration `2029_07_26_000001` applied to new isolated SQLite UAT only, not old UAT/production)**: Menambahkan `employees.name` varchar(100) NULL, `employees.nik` varchar(16) NULL dengan unique **unconditional** (termasuk baris terarsip), dan `employees.archive_reason` varchar(255) NULL; index `name`, `employee_type_id`, `employee_status_id`. Backfill nama saat migration hanya dari `users.name` via `user_id`; command `app:employee-name-backfill [--dry-run]` melengkapi baris legacy (fallback NIK unik hanya untuk baris eligible, bebas PII di keluaran). `down()` fail-closed bila ada nama tak dapat direkonstruksi, NIK, atau alasan arsip. NIK disimpan sebagai string; arsip administratif mengisi `archive_reason` dan tidak mengubah `exit_date`.
- Identitas bernama tetap string. Null/blank dinormalisasi. Unique NIS lingkup sekolah (DEC-20).
- Constraint keanggotaan rombel: telah ditegakkan via database trigger MariaDB dan SQLite pada Fase 03a.
- Jangan rewrite migration lama. Semua backfill punya count awal/akhir, daftar exception sintetis, strategi retry, dan rollback yang tidak menghapus data valid.

## Tabel target baru (usulan logis)
| Domain | Entitas calon | Invariant |
|---|---|---|
| Surat | letters, letter_dispositions, letter_templates, letter_number_counters, letter_approvals | Direction/status jelas; nomor terbit unik; snapshot dan versi; keputusan tercatat |
| Izin | leave_requests, leave_decisions | Subjek siswa/pegawai valid; tanggal sah; satu keputusan final aktif |
| Arsip | document_files, document_versions, document_links | private storage; pemilik/objek/versi; izin berdasarkan objek terkait |
| Pembelajaran | teaching_assignments, timetable_entries, teaching_sessions, teaching_journals | period valid; tidak bentrok; jurnal unik per sesi; cancelled/rescheduled terdefinisi |
| Impor | import_batches, import_rows atau ringkasan error terstruktur | job token, mapping/template version, actor, hasil dan retry |
| Backup | backup_runs, backup_manifests atau manifest file privat | target, checksum, DB/schema/version, status dan hasil restore |

Nama dan struktur final ditetapkan saat fase aktif, bukan SQL siap eksekusi. Polymorphic link membutuhkan validasi type whitelist + cakupan objek; jangan percaya type/id arbitrer dari request.

## Checklist perubahan schema
Periksa data nyata tanpa mempublikasikannya; ukur orphan/duplikat; tentukan downtime/backfill; backup yang telah diuji; migration additive; test MySQL FK/unique/concurrency; update model/request/resource/frontend; catat command migration dan rollback. Tidak menjanjikan `down()` aman jika perubahan memiliki risiko kehilangan data.
