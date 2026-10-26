# Database: baseline dan usulan

## Status
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
| employees | user_nik → users.nik; employee status/type/employment/subject, religion/district/marital/gender; biodata dan pendidikan | Banyak FK required; subject tunggal; tidak memiliki version baseline |
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
- `Employee.php`: Kolom `user_id`, `user_id_resolved_at`, `account_unlinked_at`, dan `version` ditambahkan via migration `2029_07_24_000001` (additive, DEC-04). Relasi utama `user()` kini berbasis `user_id` (nullOnDelete) dengan fallback aman legacy ke `user_nik`. Relasi typo `relogion` diperbaiki menjadi `religion()` dengan alias kompatibilitas `relogion()`. Allowlist audit ketat diterapkan untuk membatasi PII.

## Usulan migration & status implementasi
- **Subfase 03a (SELESAI)**: Penambahan `student_id` pada `student_rombels` (FK restrictOnDelete, cascadeOnUpdate); pelonggaran nullability `student_nik`; backfill idempotent berbasis NIK tunggal; dan penegakan trigger database 1 rombel aktif per siswa per tahun ajaran.
- **Subfase 03b (SELESAI & DITUTUP)**: Migration `2029_07_23_000002` menambahkan `students.archive_reason` serta `student_rombels.archived_at` + `student_rombels.archive_reason`, dan memperbarui trigger agar mengabaikan baris `archived_at IS NOT NULL` serta menolak keanggotaan aktif untuk siswa terarsip (`deleted_at IS NOT NULL`). Arsip administratif tidak mengubah `student_status_id` maupun `latest_student_exit_date`.
- **Subfase 03d (SELESAI & DITUTUP)**: Endpoint `assign-rombel` dan `transfer-rombel` ber-row-lock InnoDB dan versioning atomik (DEC-23).
- **Subfase 04a (IMPLEMENTED & FULLY VERIFIED AGEN / MANUAL REVIEW PENDING)**: Migration `2029_07_24_000001` menambahkan `employees.user_id` (nullable bigint unsigned FK ke `users.id` nullOnDelete, unique), `employees.user_id_resolved_at`, `employees.account_unlinked_at`, `employees.version` (default 1). Pelonggaran nullability `user_nik` dan `subject_id` secara additive (DEC-04). Backfill idempotent `EmployeeBackfillService` berbasis NIK unik tanpa leak PII.
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
