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
| students | nik, nis, nisn, birthdate, age; gender/religion/education/blood/profession/village FK; keluarga; check_kjp/pip/ppdbbersama/bpms | Identitas unique nullable; age tersimpan; soft delete |
| school_rombels | school_level_id, school_year_id, student_major_id, class_teach_id, name, version | Wali kelas terkait users.id |
| student_rombels | student_nik → students.nik; school_rombel → school_rombels.id; status/entry/mutation/exit/graduation/check_dapodik | Tidak ada constraint unique komposit pada baseline; soft delete |
| employees | user_nik → users.nik; employee status/type/employment/subject, religion/district/marital/gender; biodata dan pendidikan | Banyak FK required; subject tunggal; tidak memiliki version baseline |
| school_profiles | pendidikan sekolah, kepala sekolah, tahun ajaran, nomor/kontak sekolah | Detail sumber migration dan SchoolProfile model |
| subjects | name, description | Referensi mata pelajaran, bukan jurnal |
| school_years / semesters / school_levels / student_majors / phase_statuses | referensi akademik | Jangan menganggap seluruh referensi memiliki UI CRUD lengkap |
| student_statuses / student_entries | referensi status siswa dan jenis masuk | Status tampilan siswa memakai ID numerik; petakan label sebelum ubah |
| employee_statuses / employee_types / employments / marital_statuses | referensi kepegawaian | Hubungan ke employee |
| provinces / regencies / districts / villages | hierarki wilayah | Relasi bertingkat untuk alamat |
| education_schools / education_levels | npsn dan referensi pendidikan | Sekolah dirujuk memakai npsn |
| genders / religions / blood_types / professions / family_statuses | referensi biodata | Identitas/domain berbeda dari role |
| activities | user_id, table_name, record_id, before/after/new/delete JSON, information enum, timestamps | FK user cascade untuk hard delete; belum memiliki idempotency/rollback linkage |
| password_reset_tokens / personal_access_tokens / failed_jobs | tabel framework | Jangan masukkan token ke evidence |
| roles (Fase 01a) | name, slug, description, is_protected, version, soft delete | Peran baru terpisah dari type_users; `is_protected` melindungi Admin Sistem |
| abilities (Fase 01a) | name unik (mis. students.export), group, description, is_sensitive | Katalog kemampuan bernama; di-seed dari AbilityCatalog |
| ability_role (Fase 01a) | role_id, ability_id, unique komposit | Pemetaan peran→kemampuan yang dapat diatur admin |
| role_user (Fase 01a) | user_id, role_id, assigned_by, unique komposit, FK cascade | Penetapan peran; auto-map legacy hanya `Admin`→`admin-sistem` (DEC-14); `User` tidak otomatis; penetapan manual admin tidak dihapus |

## Kesenjangan model–schema
Migration siswa punya check_bpms; model fillable yang diperiksa belum memuatnya. StudentRombel migration memiliki graduation/latest_information/check_dapodik/informations_dapodik yang tidak semuanya ada di fillable baseline. Employee relation `relogion` tampak salah eja. Verifikasi consumer sebelum mengganti nama. Jangan menganggap semua model memiliki version atau Loggable; Student/StudentRombel belum menggunakannya.

## Usulan migration inti (belum ada)
- Tambah student_id pada student_rombels, backfill berdasar NIK yang cocok, audit orphan/duplikat, constraint, pindahkan consumer, kemudian deprecate FK lama di perubahan terpisah. NIK siswa nullable, sehingga FK NIK sebagai satu-satunya identitas menyulitkan data belum lengkap.
- Evaluasi user_id/akun opsional pada employees. Profil pegawai bukan akun login; migrasi hubungan membutuhkan keputusan DEC-04.
- Identitas bernama tetap string. Null/blank dinormalisasi. Unique NIS/NISN sesuai lingkup sekolah dan kebijakan retensi; soft delete tidak otomatis membebaskan unique DB.
- Constraint keanggotaan rombel mengikuti kebijakan satu rombel per periode; jangan hanya menambah unique student_id-school_rombel jika larangan meliputi dua rombel berbeda pada tahun sama.
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
