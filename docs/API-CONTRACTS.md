# Kontrak API

## Correction addendum — 2026-10-29 (04b English presentation/privacy)

Baseline `main` at `e281419` (corrections `dc5e031`, documentation `e281419`); **04b owner acceptance: APPROVED 2026-10-30 WIB** (checklist #1–#18 per owner statement). Current presentation is full English. A shared 17-domain/243-label JSON catalog supplies English labels; pure frontend `display_name` enrichment preserves canonical API `name` values/IDs, user-entered data and proper names. Canonical `Pendidik` = Guru-only Teachers and `Tenaga Kependidikan` = non-teaching Employees; classification uses only `employee_type_id`, not employment/job titles. Canonical jobs, including head-of-school positions, are retained. Subject remains required for Teachers and prohibited for non-teaching Employees; the staff UI omits Subject.

Employee list/export **are not zero-PII payloads**: operational name, NUPTK and gender remain; list `account: {linked, user_id}` is account linkage metadata. Sensitive NIK/contact/family/education data and expanded account identity are restricted to manage-detail responses. Unresolved legacy list names return `null` / blank, never a literal `name unresolved`. Archived employee GET detail resolves with `withTrashed()` and requires **employees.view + employees.manage** (403 for view-only); reading detail does not restore/mutate data. CSV reference values and validation messages are English; current 10-column employee CSV replaces `TMT` with **`Employment Start Date (TMT)`**.

Passwords are stripped before frontend cache enrichment; route-view key prevents stale Teachers/Employees rows/dialog state. New SQLite UAT only: SPA `http://127.0.0.1:14022`, API `http://127.0.0.1:18022`, root `.uat-phase04b/database.sqlite`, dedicated environment/storage/cookie `school_phase04b_session`; guarded launcher `scripts/phase04b-uat.php` (`init`/`prepare`/`status`), normal migrations only. Never root `artisan --env=uat`; main/legacy environments and MariaDB targets are excluded. See `PHASE-04B-REPORT.md` for latest explicit SQLite 96/1738 PASS and operator-browser CRUD/link/unlink, English CSV and staff mobile DOM evidence; three-role HTTP permission checks PASS is not other-role browser UI coverage. Agent-side other-role browser UI automation, two-tab stale/link-conflict, print-preview/screenshot visual checks and 04b MariaDB DDL/InnoDB remain NOT RUN; owner acceptance does not convert them to PASS. Acceptance does not authorize deployment or legacy/production migration.

## Perubahan Fase 01a
Endpoint baru (grup `auth:sanctum` + `active`, dilindungi `can:<ability>`):
- `GET /api/roles`, `GET /api/roles/{role}` — `roles.view`.
- `POST /api/roles`, `PUT|PATCH /api/roles/{role}`, `DELETE /api/roles/{role}` — `roles.manage`. Body boleh memuat `abilities: [name,...]` untuk menyinkronkan pemetaan.
- `GET /api/abilities`, `GET /api/abilities/{ability}` — `roles.view`.
- `POST /api/users/{user}/roles` (body `role_id`) — `access.assign`. `DELETE /api/users/{user}/roles/{role}` — `access.assign`.
- `GET /api/students/export?school_year_id={id}` — **CSV siswa**. Wajib `students.view` **dan** `students.export`. Response `text/csv; charset=UTF-8` sebagai unduhan lampiran. Lihat detail di bawah.
Perubahan kontrak:
- `GET /api/check-auth` kini mengembalikan objek user minimal (`id,name,username,type_user_id,status_user_id,status_active,abilities[],roles[]`) — tidak lagi objek user penuh.
- `PUT|PATCH /api/firstlogin/{id}` hanya boleh untuk akun actor (`id` harus sama dengan user terautentikasi) dan hanya mengubah `first_login_at`. `status_active` tidak lagi diterima di sini.
- `PUT|PATCH /api/users/{id}`: update profil sendiri mengabaikan `type_user_id`/`status_user_id`/`status_active` (anti-eskalasi); mengubah akun orang lain butuh `users.manage` dan dilindungi aturan admin-pengelola-terakhir.
- Endpoint master/siswa/pegawai kini 403 bila kemampuan tidak dimiliki (sebelumnya hanya butuh auth).
- `POST /register` dihapus (404/405).
- Validasi email akun berubah dari `email:rfc,dns` menjadi `email:rfc` (lingkungan LAN/offline tanpa DNS).

## Perubahan Fase 01b
Kontrak autentikasi, error handling, dan pemulihan sesi:
- **Instance interceptor terpusat**: interceptor dipasang pada instance `apiLogin` (timeout 15.000 ms, `withCredentials: true`, `withXSRFToken: true`).
- **Penanganan CSRF 419**: pada respons 419 penolakan CSRF, interceptor menyegarkan `GET /sanctum/csrf-cookie` sekali (dikoordinasikan bila ada request bersamaan), memperbarui header `X-XSRF-TOKEN`, dan mengulangi request asli tepat satu kali. Jika retry tetap 419, tampilkan pesan pemulihan tanpa loop. Request ke `/sanctum/csrf-cookie` sendiri dikecualikan dari retry.
- **Sesi aktif kedaluwarsa (401)**: request non-auth yang ditolak 401 membersihkan state lokal, menyimpan flash banner di `sessionStorage`, menampilkan notifikasi "Your session has expired. Please sign in again.", dan mengarahkan ke `/auth/login`. Request 401 bersamaan dideduplikasi (satu penanganan, satu navigasi). Pemeriksaan guest (`skipAuthRedirect`) dikecualikan dari pengalihan.
- **Penolakan izin vs nonaktif (403)**: 403 izin fitur mempertahankan sesi lokal pengguna. 403 akun nonaktif (dari middleware `EnsureUserIsActive`) mengarahkan pengguna ke login dengan pesan English.
- **Router guard per halaman & child-to-child**: `router.beforeEach` mengevaluasi setiap navigasi langsung maupun antar-child. Kemampuan bernama dipetakan eksplisit: `/master/accounts` (`users.view`), `/master/typeusers` (`master.manage`), `/master/roles` (`roles.view`), `/public/students` (`students.view`), master referensi (`master.view`).
- **Logout**: disatukan ke `authStore.logOut()` tunggal (`POST /logout` 204 No Content tanpa membaca body); mengembalikan `{ success: true, serverSuccess }`.
- **Reset password end-to-end**:
  - `POST /forgot-password` (body `{ email }`): form frontend menampilkan konfirmasi anti-enumerasi seragam ("If your email address is registered, you will receive a password reset link shortly.").
  - `POST /reset-password` (body `{ token, email, password, password_confirmation }`): mereset password via broker Laravel, memperbarui hash, menginvalidasi token lama (token kedaluwarsa/bekas pakai ditolak 422).
  - Rute frontend: `/auth/forgot-password`, `/password-reset/:token`, `/auth/reset-password/:token`.
  - UAT lokal: `MAIL_MAILER=log` pada `.env.uat` (tanpa pengiriman email nyata).
- **Penyelarasan UI**: kontrol ubah password di `Profile.vue` dan `Logout.vue` diselaraskan ke English.

## Perubahan Fase 01c
Kontrak validasi akun, bootstrap administrator, dan kebijakan password:
- **Validasi Akun (`UserController`)**:
  - `name`: aturan keunikan dihapus (`required, string, min:3, max:100`). Dua akun dengan nama yang sama persis (homonim) diterima.
  - `nik`: divalidasi tepat 16 digit string angka (`required, string, digits:16`). Nilai disimpan string dan mempertahankan nol awal.
  - `password`: minimal 12 karakter tanpa komposisi huruf besar/angka wajib (`Password::defaults()`).
  - `type_user_id` & `status_user_id`: divalidasi keberadaannya di tabel referensi masing-masing (`exists:type_users,id` dan `exists:status_users,id`).
  - Respon kegagalan validasi distandarisasi ke `{ message: "The given data was invalid.", errors: { field: [msg] } }` (HTTP 422).
- **Standar Password Global**:
  - `Password::defaults()` disetel ke `Password::min(12)` pada `AppServiceProvider.php`.
  - Diterapkan konsisten pada pembuatan akun (`UserController`), bootstrap admin (`BootstrapAdminCommand`), form ubah password (`Profile.vue`), dan form reset password (`ResetPassword.vue`).
  - Akun lama tidak dipaksa mengganti password.
- **Bootstrap Administrator Pertama (`app:bootstrap-admin`)**:
  - Perintah CLI: `php artisan app:bootstrap-admin`.
  - Input non-sensitif (name, username, email, NIK) via argumen/prompt; kata sandi diketik tersembunyi via secret prompt (`$this->secret()`) dengan konfirmasi.
  - Ditolak (exit code 1) bila sudah ada pengguna aktif yang mampu mengelola akses (`canManageAccess()`).
  - Pembuatan akun dan penugasan peran `admin-sistem` dilakukan secara atomik dalam transaksi database.
- **Isolasi Storage Pengujian**:
  - Testing disk terisolasi pada `storage/framework/testing/disks/testing` (`FILESYSTEM_DISK=testing` pada `phpunit.xml`).
  - Fail-safe `guardAgainstUnsafeStorage` membatalkan test bila storage default mengarah ke storage publik atau operasional.

### Detail ekspor siswa (Fase 01a)
- Method/path: `GET /api/students/export`.
- Kemampuan: `students.view` (wajib) + `students.export` (wajib). Akun nonaktif ditolak (`active`). Guest 401.
- Query: `school_year_id` (wajib, integer, exists), opsional `status`, `entry`, `rombel`, `search`.
- Lingkup: sama dengan daftar siswa (berdasar tahun ajaran + filter). **Mencakup seluruh hasil filter, bukan hanya halaman aktif** (endpoint tidak berpaginasi) — dengan syarat jumlah baris tidak melebihi batas.
- Batas baris: maksimum **10.000 baris** (`config('students.export_max_rows')`, dapat diturunkan lewat env `STUDENTS_EXPORT_MAX_ROWS`). Bila hasil filter melebihi batas, ekspor **DITOLAK `422`** dengan pesan English yang menyebut jumlah baris, batas, dan permintaan mempersempit filter — **bukan memotong hasil secara diam-diam**. Ekspor hanya berhasil bila hasil filter ≤ batas.
- Kolom eksplisit: Name, Gender, Birthplace, Birthdate, NIK, NIS, NISN, School Rombel, Student Entry, Student Status. Semua nilai teks; leading zero dipertahankan; koma/kutip/newline RFC 4180; formula injection dinetralkan prefix apostrof.
- Frontend: `Student.vue` mengunduh via endpoint ini (bukan `dt.exportCSV()` klien); tombol tampil hanya bila `students.export` dimiliki.
- Catatan penting: kemampuan ekspor mengendalikan **berkas unduhan**, bukan mencegah penyalinan data yang sudah boleh dilihat pengguna.

### Kontrak Audit Trail (`/api/activities`) — Fase 02a (DEC-17)
- `GET /api/activities`:
  - Izin: `audit.view` (wajib). Guest `401`, user tanpa kemampuan `403`.
  - Format: JSON paginasi (default 50 per halaman) dengan relasi `user:id,name,username` (termasuk soft-deleted user via `withTrashed()`).
- `GET /api/activities/{id}`:
  - Izin: `audit.view` (wajib). Mengembalikan detail rekaman audit tunggal.
- `POST /api/activities`:
  - Status: **DITUTUP/NONAKTIF** (DEC-17).
  - Respons: `405 Method Not Allowed` (`{"message": "Audit logs are immutable and cannot be created by clients."}`).
  - Seluruh log audit dihasilkan otomatis oleh observer/trait di server (`Loggable.php`).
- `PUT/PATCH /api/activities/{id}`:
  - Status: **DITUTUP/NONAKTIF** (DEC-17).
  - Respons: `405 Method Not Allowed` (`{"message": "Audit logs are immutable. Rollback has been disabled."}`).
  - Audit trail bersifat *immutable append-only*; perbaikan data dilakukan via mutasi baru yang tercatat secara resmi.
- `DELETE /api/activities/{id}`:
  - Status: **DITUTUP/NONAKTIF** (DEC-17).
  - Respons: `405 Method Not Allowed` (`{"message": "Audit logs are immutable and cannot be deleted."}`).

### Kontrak Backup & Restore (`/api/backups`) — Fase 02b & 02c (DEC-18, DEC-19)
- `GET /api/backups`:
  - Izin: `backup.manage` (wajib). Guest `401`, user tanpa kemampuan `403`.
  - Format: JSON daftar arsip backup selesai (`filename`, `size_bytes`, `created_at`, `sha256`).
- `GET /api/backups/status`:
  - Izin: `backup.manage` (wajib). Guest `401`, user tanpa kemampuan `403`.
  - Format: JSON status kesehatan dan metrik monitoring (`status`: `HEALTHY`/`OVERDUE`/`DEGRADED`/`NEVER_BACKED_UP`/`ARCHIVE_MISSING`, `hours_since_last_success`, `is_overdue`, `backup_created`, `restore_verified`, `last_attempt`, `last_success`, `last_verified_drill`). Dihitung dinamis saat runtime, bebas dari rahasia/password.
- `GET /api/backups/{filename}/download`:
  - Izin: `backup.manage` (wajib). Guest `401`, user tanpa kemampuan `403`.
  - Parameter `{filename}`: divalidasi strict regex `/^backup-[a-zA-Z0-9._-]+\.zip$/`. Karakter `..`, `/`, `\`, `%00` ditolak dengan `400 Bad Request`.
  - Respons: Stream unduhan berkas biner ZIP (`Content-Type: application/zip`, `Cache-Control: no-store, private`). Berkas disajikan dari storage privat `storage/app/backups`, tanpa URL publik.

## Perubahan Subfase 03a
Kontrak keanggotaan rombel dan identitas siswa (DEC-20):
- **Relasi Keanggotaan Rombel (`StudentRombel`)**:
  - Kolom `student_id` (FK ke `students.id`) menjadi relasi utama `student`.
  - Kolom legacy `student_nik` dibuat nullable dan tetap dipertahankan untuk backward compatibility.
  - Sinkronisasi dua arah otomatis pada level model saat saving.
  - Penegakan integritas: maksimal 1 keanggotaan rombel aktif per siswa per tahun ajaran (`school_year_id`) ditegakkan pada level database trigger MariaDB (`SQLSTATE 45000`) dan SQLite (`RAISE(ABORT)`).
- **Perhitungan Umur Dinamis (`Student`)**:
  - Kolom `age` dan accessor `calculated_age` dihitung secara dinamis dari tanggal lahir (`birthdate`) menggunakan timezone `Asia/Jakarta`. Nilai tanggal kosong, tidak valid, atau di masa depan mengembalikan `null`.
- **Ekspor Siswa (`GET /api/students/export`)**:
  - Eager load dan resolusi atribut siswa kini mendukung relasi `student` (utama) maupun `studentNik` (fallback).

## Perubahan Subfase 03b
Kontrak CRUD siswa, optimistic locking, dan arsip administratif (DEC-21):

| Method | Endpoint | Izin | Request | Respons |
|---|---|---|---|---|
| POST | `/api/students` | `students.manage` | Field siswa (whitelist; `id`/`age`/timestamp diabaikan) | `201` objek siswa (`version=1`) |
| GET | `/api/students/{id}` | `students.view` | — | `200` detail siswa + relasi + membership aktif (termasuk menyertakan record terarsip) |
| PUT | `/api/students/{id}` | `students.manage` | Field siswa + `version` wajib | `200`; `409` bila version usang; `422` bila siswa terarsip (restore dulu) |
| DELETE | `/api/students/{id}` | `students.manage` | `version` + `archive_reason` wajib | `200` arsip; `409` versi usang; `422` validasi |
| POST | `/api/students/{id}/restore` | `students.manage` | `version` wajib | `200` pulih; `409` versi usang; `422` bentrok identitas/NIK/NIS/NISN |

- **Optimistic locking**: `version` divalidasi atomik (row lock + guard) pada update/arsip/restore; versi usang → `409` dengan `current_version`. Pesan error dalam Bahasa English.
- **Arsip administratif**: soft delete `students.deleted_at` + `archive_reason`; seluruh keanggotaan rombel aktif ditandai `archived_at`/`archive_reason` **tanpa** mengubah `student_status_id` (Mutasi Keluar) atau `latest_student_exit_date`. Riwayat mutasi tetap utuh.
- **Restore**: tidak mengaktifkan kembali rombel lama (`archived_at` dipertahankan); menolak bila NIK/NIS/NISN sudah dipakai siswa aktif lain (`422`).
- **Keunikan**: index unik `students` menyeluruh (termasuk record terarsip); NIK/NIS/NISN terarsip tidak dapat dipakai ulang tanpa pemulihan resmi.
- **List arsip**: `GET /api/students?only_archived=1` kini **membutuhkan `students.manage`** (03c; sebelumnya `students.view` saja membocorkan NIK/alasan arsip ke akun hanya-baca). `with_archived` dihapus pada 03c.
- **UI**: `Student.vue` (English) — create/edit/detail/archive/daftar arsip/restore, 422 inline, 409 + Reload Latest, loading/double-submit guard, tombol sesuai `students.view`/`students.manage`.

## Perubahan Subfase 03c
Daftar, rekap, dan ekspor siswa server-side (DEC-22). Definisi bersama `App\Support\StudentDirectory`.

### `GET /api/students` (izin `students.view`)
| Parameter | Aturan |
|---|---|
| `school_year_id` | wajib, integer, ada di `school_years` |
| `search` | opsional, maks 100; cocok parsial nama/NIS/NISN (bukan NIK) |
| `placement` | `all` (default) / `placed` / `unplaced` |
| `rombel` | opsional, ID rombel **pada tahun terpilih**; ditolak `422` bila dikombinasikan dengan `placement=unplaced` |
| `sort` | allowlist `id`, `name` (default), `nis`, `nisn`; `direction` `asc` (default)/`desc`; pembeda stabil `students.id` |
| `per_page` | `10`/`20`/`50`/`100` (default `20`; selain itu `422`) |
| `page` | integer ≥ 1 |

- **Definisi**: siswa terarsip (`students.deleted_at`) tidak pernah muncul. *Placed* = keanggotaan **aktif** pada tahun terpilih (`student_rombels.deleted_at`/`archived_at` null, status Aktif/null, `latest_student_exit_date` null). *Unplaced* = tidak punya keanggotaan aktif tahun tersebut (termasuk yang hanya punya riwayat nonaktif/tahun lain atau belum pernah punya rombel). `all` = seluruh siswa tidak terarsip.
- **Identitas**: `student_id` primer; baris legacy `student_id IS NULL` cocok hanya via `student_nik` = `students.nik` (resolusi aman 03a). Dihitung per siswa unik (EXISTS), tanpa duplikasi join.
- **Respons** `200`: `data[]` hanya `id`, `version`, `name`, `nis`, `nisn`, `gender` (label), `rombel` (`{id,name}` aktif tahun terpilih atau `null`), `placement` (`placed`/`unplaced`); `meta` (`current_page`, `per_page`, `total`, `last_page`, `from`, `to`); `recap`.
- **`recap`** dihitung dari **seluruh hasil filter** (bukan halaman): `total_matches` (sesuai `placement`), `placed_count`, `unplaced_count` (dari pencarian + rombel, sebelum placement), `by_gender[]` (`label`,`total`), `by_rombel[]` (`id`,`name`,`total`; siswa unik; kosong untuk `unplaced`).
- **Tanpa** NIK, tanggal lahir, alamat, telepon, medis, penghasilan, atau alasan arsip pada daftar/rekap; detail melalui `GET /api/students/{id}`.
- `GET /api/students?only_archived=1` mempertahankan perilaku 03b, kini wajib `students.manage`.

### `GET /api/students/export` (izin `students.view` + `students.export`)
- Parameter: `school_year_id` (wajib), `search`, `rombel`, `placement` (`all`/`placed`/`unplaced`; **default `placed`** agar perilaku ekspor per tahun ajaran tetap). Filter memakai definisi bersama daftar; mencakup seluruh hasil, bukan halaman aktif.
- `unplaced`/`all`: siswa tanpa keanggotaan aktif ikut dengan kolom rombel/entry/status **kosong** (tidak mengarang rombel).
- Batas baris `students.export_max_rows` → `422` (tanpa pemotongan diam-diam). Kolom, leading zero, CSV escape, dan netralisasi formula tidak berubah; tidak ada kolom sensitif baru.
- Parameter lama `status` dan `entry` tidak lagi dipakai pada ekspor (status/entry kini atribut keanggotaan, bukan filter siswa).

### Frontend
`Student.vue`: DataTable lazy (pagination/sort/pencarian debounce 300 ms/placement/rombel server), rekap server, guard respons usang (`requestId`), loading/empty/error + Retry, halaman terakhir yang kosong mundur satu halaman, Export mengikuti filter aktif, filter rombel dinonaktifkan pada `Unplaced`. Aksi penempatan rombel di luar scope 03c.

## Perubahan Subfase 04a
- **Izin Route `/employee`**: Tidak ada perubahan route pada 04a. Route singular `/employee` sudah memakai `can:employees.view` (index, show) dan `can:employees.manage` (store, update, destroy) pada baseline `41f6e68`. Endpoint CRUD lengkap, normalisasi route ke `/employees`, dan transisi consumer berada pada Subfase 04b.
- **Model `Employee`**: Relasi utama `user()` via `user_id` (bigint unsigned nullable, unique, nullOnDelete). Relasi legacy `legacyUser()` via `user_nik`. Resolusi aman `resolveUser()` yang memprioritaskan `user_id`, menolak fallback bila akun sudah dilepas/diresolusi, dan hanya mengizinkan fallback NIK pada baris legacy bertanda `legacy_profile_at` (profil pasca-04a tidak pernah memakai fallback). Relasi `religion()` diperbaiki dengan alias kompatibilitas `relogion()`.
- **Privasi Audit Personalia**: Allowlist `$auditableAttributes` diterapkan ketat pada model `Employee`; mengecualikan NUPTK, nama ibu kandung, pasangan, nomor KK, NPWP, kontak/alamat, dan nilai/IPK ijazah dari pencatatan log `activities`.
- **Layanan Pemadanan**: `EmployeeBackfillService` menyelesaikan relasi `user_id` untuk baris legacy secara idempotent, atomik (all-or-nothing), mengecualikan akun soft-deleted, dan tanpa mencetak NIK, nama, atau data pribadi. Command `php artisan app:employee-account-backfill [--dry-run]` melaporkan hitungan agregat.

## Perubahan Subfase 04b (DEC-24)
- **Route**: `/api/employees` ditambahkan dan **menggantikan** `/employee` sebagai route utama; `/employee` dipertahankan sebagai **alias deprecated** sampai consumer lain dimigrasikan dan diuji. Index/show memakai `employees.view`; store/update/destroy memakai `employees.manage`.
- **`GET /api/employees`** (`employees.view`): paginasi `per_page` ∈ `10,20,50,100` (default 20), `page`, `search` (nama/NUPTK), filter `employee_type_id`, `employee_status_id`, `employment_id`, `subject_id`; sort `id|name|tmt_employee` (default `name asc`) dengan tie-break `id`. `only_archived=1` memerlukan `employees.manage`. `meta`: `current_page`, `per_page`, `total`, `last_page`, `from`, `to`.
  - Payload daftar: `id`, `version`, `name`, `nuptk`, `employee_type`, `employee_status`, `employment`, `subject`, `gender`, `tmt_employee`, `exit_date`, `archived_at`, `account.linked`/`account.user_id`. Memuat nama/NUPTK/gender dan metadata akun; **bukan tanpa PII/data akun**. Tidak memuat NIK, NPWP, KK, ibu kandung, pasangan, kontak/alamat atau nilai ijazah.
- **`GET /api/employees/{id}`** (`employees.view`): data operasional; field sensitif (`nik`, `npwp`, `no_kk`, `place_of_birth`, `date_of_birth`, `address`, `rt`, `rw`, `zip_code`, `wa_number`, `email`, `religion`, `district`, `marital_status`, `appointment_certificate`, `certificate_of_teaching_hours`, `biological_mothers_name`, `partners_name`, `check_dapodik`, `government_certificate`, `education[]`, serta `account.name`/`username`/`email`) **hanya** untuk `employees.manage`. Current correction: resolves archived records with `withTrashed()`; archived detail requires **view + manage**, view-only → **403**.
- **`POST /api/employees`** (`employees.manage`): `201`, `version=1`. `name` wajib (maks 100); `nik` opsional 16 digit unik; `subject_id` wajib untuk Pendidik dan prohibited untuk Tenaga Kependidikan; field `user_id`, `user_nik`, `version` dari klien diabaikan.
- **`PUT/PATCH /api/employees/{id}`** (`employees.manage`): `version` wajib; sukses `200` dengan versi +1; versi usang `409` + `current_version`; baris terarsip `422`.
- **`DELETE /api/employees/{id}`** (`employees.manage`): body `version` + `archive_reason` wajib. Arsip administratif (soft delete) menyimpan alasan, menaikkan versi, dan **tidak** mengubah `exit_date`. Tanpa alasan `422`; versi usang `409`.
- **`POST /api/employees/{id}/restore`** (`employees.manage`): body `version`; `200` dan `archive_reason` dikosongkan; versi usang `409`; bentrok NIK `422`.
- **`GET /api/employees/account-candidates`** (`employees.manage`): maks. 20 akun `users` aktif, tidak terhapus, belum tertaut profil lain; opsional `search`; payload `id`, `name`, `username`, `email`.
- **`POST /api/employees/{id}/link-account`** (`employees.manage`): body `user_id` + `version`; hanya akun kandidat; mengisi `user_id` dan `user_id_resolved_at`; tidak mengubah role/abilities. Akun nonaktif/tertaut/re-link `422`; versi usang `409`.
- **`POST /api/employees/{id}/unlink-account`** (`employees.manage`): body `version`; mengosongkan `user_id` dan mengisi `account_unlinked_at`; fallback NIK legacy tidak diaktifkan kembali; unlink ulang `422`.
- **`GET /api/employees/export`** (`employees.view` + `employees.export`): mengikuti filter yang sama dengan daftar (tanpa paginasi/arsip). CSV UTF-8 BOM, `X-Content-Type-Options: nosniff`, default 10 kolom operasional: `Name`, `Employee Type`, `Employee Status`, `Employment`, `Subject`, `NUPTK`, `Gender`, `Employment Start Date (TMT)`, `Exit Date`, `Archived At`; reference values in English, canonical stored names/IDs unchanged. Sejak 05b menerima `columns` (lihat bagian 05b). Hasil melebihi `employees.export_max_rows` (default 10.000) → `422` (tidak dipotong). Sel dinetralkan terhadap formula (`= + - @`, tab, CR).
- **Privasi & audit**: `EmployeeResource` memisahkan payload daftar dan sensitif; allowlist audit `Loggable` tidak memuat `name`, `nik`, maupun `archive_reason`.

## Perubahan Subfase 05a (DEC-25) — impor siswa CSV

Preview tanpa mutasi, commit **all-or-nothing** dalam satu transaksi. Header English ketat (17 kolom, urutan bebas, delimiter `,` atau `;`, BOM UTF-8 diabaikan). Batch milik pembuat saja; pengguna berizin lain menerima **404**, bukan 403, agar keberadaan batch tidak bocor.

| Method | Endpoint | Izin | Ringkas |
|---|---|---|---|
| GET | `/api/imports/students/template` | `students.view` | Unduh `students-import-template.csv` (header saja), `text/csv; charset=UTF-8` |
| POST | `/api/imports/students` | `students.import` | Multipart `file` + `mode` (`insert`/`update`) → `201` ringkasan batch + preview/error terbatas |
| GET | `/api/imports/{batch}` | `students.import` | Ringkasan batch current user (`token` sebagai route key) |
| POST | `/api/imports/{batch}/commit` | `students.import` | Commit batch `ready`; `200` ringkasan `committed` |
| GET | `/api/imports/{batch}/errors` | `students.import` | Unduh CSV error privat; `404` bila batch tanpa laporan error |

- **Batas berkas**: `mimes:csv,txt`, maksimum `imports.students.max_size_kb` (default 5.120 KB = 5 MB) dan `max_rows` (default 2.000 baris). Melebihi batas **ditolak `422`**, bukan dipotong. `mode` selain `insert`/`update` → `422`; pesan English.
- **Kolom template v1**: `Name`, `NIK`, `NIS`, `NISN`, `Gender`, `Birthplace`, `Birthdate`, `Religion`, `Family Status`, `Education Level`, `Profession`, `Blood Type`, `Address`, `RT`, `RW`, `Zip Code`, `Phone`. Referensi (Gender/Religion/Family Status/Education Level/Profession/Blood Type) dicocokkan berdasarkan nama English; nilai tak dikenal → error baris.
- **Validasi**: memakai `App\Support\StudentRules` yang sama dengan form create/update. `NIK` 16 digit, `NISN` 10 digit, `Birthdate` `YYYY-MM-DD` sebelum hari ini. Duplikat NIS/NIK/NISN **di dalam berkas** (mode insert) atau duplikat match key (mode update) ditandai pada kedua baris.
- **Mode `update`**: pencocokan baris **NIS lebih dulu, lalu NIK**; **nama tidak pernah** dipakai sebagai kunci. Kolom yang dikosongkan tidak menimpa nilai tersimpan; `Name` opsional saat update. Baris tanpa match → error.
- **Commit**: batch `invalid`/kedaluwarsa (TTL `imports.students.batch_ttl_minutes`, default 120 menit)/hash sumber berubah/baris berubah sejak preview → **`409`** dengan `data.errors`; tidak ada baris yang ditulis. Sukses mengembalikan `status=committed` dan `committed_rows`.
- **Ringkasan batch**: `token`, `domain`, `mode`, `status` (`invalid`/`ready`/`committed`), `file_name`, `total_rows`, `valid_rows`, `invalid_rows`, `committed_rows`, `has_errors`, `created_at`, `committed_at`, `expires_at`; respons upload menambah `preview` (maks. `imports.students.preview_rows`, default 50) dan `errors` (maks. `imports.students.error_rows`, default 200).
- **Berkas & privasi**: sumber dan laporan error disimpan di disk privat `imports.disk` (default `local`) pada `imports/{token}/source.csv` dan `imports/{token}/errors.csv`; tidak ada URL publik. CSV error menetralkan formula (`= + - @`, tab, CR) dan memakai retensi manual 30 hari.
- **Authority**: `students.import` ditambahkan oleh migration `2029_07_27_000002` hanya ke peran `admin-sistem` dan `operator-sekolah`; tidak mengubah kemampuan lain. Unduh template tidak memerlukan `students.import`.

Frontend: `Student.vue` menampilkan tombol Import hanya bila `students.import`; dialog `StudentImportDialog.vue` menjalankan unggah → preview (siap/invalid) → commit, menampilkan `409` dari `data.data.errors`, dan memuat ulang daftar setelah sukses.

## Perubahan Subfase 05b (DEC-26) — impor pegawai dan pilihan kolom ekspor

Mesin impor 05a diekstrak menjadi `App\Services\Imports\ImportService` (abstrak) + `StudentImportService`/`EmployeeImportService`; perilaku dan endpoint siswa tidak berubah. Batch tetap milik pembuat dan domainnya; pengguna berizin lain atau domain berbeda menerima **404**.

### Impor pegawai

| Method | Endpoint | Izin | Ringkas |
|---|---|---|---|
| GET | `/api/imports/employees/template` | `employees.view` | Unduh `employee-import-template.csv` (header saja), `text/csv; charset=UTF-8` |
| POST | `/api/imports/employees` | `employees.import` | Multipart `file` + `mode` (`insert`/`update`) → `201` ringkasan batch + preview/error terbatas |
| GET | `/api/imports/employees/{batch}` | `employees.import` | Ringkasan batch current user (`token` sebagai route key) |
| POST | `/api/imports/employees/{batch}/commit` | `employees.import` | Commit batch `ready`; `200` ringkasan `committed` |
| GET | `/api/imports/employees/{batch}/errors` | `employees.import` | Unduh CSV error privat; `404` bila batch tanpa laporan error |

- **Kolom template v1 (22, English, urutan bebas)**: `Name`, `NIK`, `NUPTK`, `Employee Type`, `Employee Status`, `Employment`, `Subject`, `Gender`, `Religion`, `Marital Status`, `Regency`, `District`, `TMT`, `Exit Date`, `Place of Birth`, `Date of Birth`, `Address`, `RT`, `RW`, `Zip Code`, `Phone`, `Email`. Kolom akun tidak termasuk.
- **Referensi**: Employee Type/Status, Employment, Gender, Religion, Marital Status dicocokkan berdasarkan nama English; nilai tak dikenal → error baris. `Subject` dicari pada mata pelajaran aktif; nama tidak ada/ambigu → error baris (bukan pilihan acak). `District` dicari di dalam `Regency` yang diberikan; pasangan tidak cocok/ambigu → error baris.
- **Mode `insert`**: `NIK` opsional; bila diisi harus 16 digit string dan unik termasuk record arsip. **Mode `update`**: `NIK` wajib sebagai kunci pencocokan; baris tanpa match atau match record arsip → error baris; NIK tidak dapat diubah.
- **Update parsial**: sel kosong berarti nilai tersimpan dipertahankan; validasi memakai gabungan nilai lama + sel terisi (aturan `App\Support\EmployeeRules`, sama dengan form). `Name` opsional saat update. Baris tanpa perubahan → error "No values to update on this row.". Jika `Employee Type` diisi `Tenaga Kependidikan`, `Subject` dikosongkan; `Pendidik` wajib Subject, non-teaching prohibited.
- **Batas**: `mimes:csv,txt`, `imports.employees.max_size_kb` (5.120 KB) dan `max_rows` (2.000). Melebihi batas atau header tidak sesuai → `422`, bukan dipotong. `mode` selain `insert`/`update` → `422`.
- **Commit**: batch `invalid`/kedaluwarsa (TTL `imports.employees.batch_ttl_minutes`, 120 menit)/hash berubah/validasi ulang gagal → `409` tanpa mutasi. Sukses menulis semua baris dalam satu transaksi + `lockForUpdate`, menaikkan `version`, dan mencatat audit domain.
- **Preview**: `row`, `action` (`insert`/`update`), `name`, `nik`, `nuptk`, `employee_type`.
- **Laporan error**: CSV privat `imports/{token}/errors.csv` dengan header `Row,Field,Message,NIK,NUPTK`; formula dinetralkan; retensi manual 30 hari.
- **Authority**: `employees.import` ditambahkan migration `2029_07_28_000001` hanya ke `admin-sistem` dan `operator-sekolah`; template cukup `employees.view`.

### Pilihan kolom ekspor pegawai

- **`GET /api/employees/export/options`** (`employees.view` + `employees.export`): `data.default` berisi 10 key operasional; `data.columns` berisi `{key, header, sensitive}`; kolom sensitif hanya disertakan bila aktor memegang `employees.manage`; `data.sensitive_allowed` boolean.
- **`GET /api/employees/export?columns=...`**: `columns` berupa daftar key dipisah koma (maks. 500 karakter). Tanpa `columns`/kosong → default 10 kolom. Urutan keluaran selalu urutan registry, bukan urutan permintaan. Key tak dikenal → `422`. Kolom sensitif tanpa `employees.manage` → `403` (tidak ada fallback diam-diam).
- **Registry**: 10 key default (`name`, `employee_type`, `employee_status`, `employment`, `subject`, `nuptk`, `gender`, `tmt_employee`, `exit_date`, `archived_at`) + 15 key sensitif (`nik`, `npwp`, `no_kk`, `place_of_birth`, `date_of_birth`, `address`, `rt`, `rw`, `zip_code`, `wa_number`, `email`, `religion`, `regency`, `district`, `marital_status`). Nama file `employees_YYYYmmdd_His.csv`; BOM UTF-8, `nosniff`, netralisasi formula, batas 10.000 baris → `422`.

Frontend: `EmployeeDirectory.vue` (dipakai Teachers dan Employees) menampilkan tombol Export CSV (dialog kolom) dan Import CSV hanya bila `employees.import` dan tidak dalam mode arsip; `EmployeeImportDialog.vue`, `EmployeeExportDialog.vue`, `employeeImportParams.mjs`, dan `buildEmployeeExportParams` mengirim filter + kolom terpilih. Rekap sekolah tetap menunggu DEC-12 (PROPOSED), di luar 05a/05b/05c.

## Perubahan Subfase 05c (DEC-28) — pemeriksaan kelengkapan dan review

Enam endpoint baru, tanpa ability baru. Baca memakai `students.view`/`employees.view`; tulis review memakai `students.manage`/`employees.manage` (view-only `403`). Guest `401`. Tidak ada endpoint koreksi massal, merge, atau edit inline.

| Method | Endpoint | Izin | Ringkas |
|---|---|---|---|
| GET | `/api/data-quality/students/summary?school_year_id=` | `students.view` | Ringkasan denominator **placed non-archived**, complete/incomplete, field, blocked NIK, duplikat, counter review |
| GET | `/api/data-quality/students/issues` | `students.view` | Daftar issue berpaginasi; filter `school_year_id` (wajib), `type`, `field`, `status`, `search`, `page`, `per_page` |
| PUT | `/api/data-quality/students/reviews/{issueKey}` | `students.manage` | Upsert review siswa; payload memuat `school_year_id`, identitas issue, versi record, dan `version` review |
| GET | `/api/data-quality/employees/summary` | `employees.view` | Ringkasan denominator **active non-archived** (`exit_date` null/belum lewat), separated/archived excluded |
| GET | `/api/data-quality/employees/issues` | `employees.view` | Daftar issue pegawai; filter sama tanpa `school_year_id` |
| PUT | `/api/data-quality/employees/reviews/{issueKey}` | `employees.manage` | Upsert review pegawai; `school_year_id` **ditolak** (`422`) |

- **Denominator dan tipe issue**: siswa placed dinilai field wajib NIK, NIS, NISN, Gender, Birthplace, Birthdate, Religion; siswa unplaced dengan NIK missing/invalid menjadi `blocked` **di luar persentase**; unplaced lain dan archived tidak dievaluasi. Pegawai active wajib NIK, Employee Type, Employee Status, Employment, Gender, Religion, Marital Status, District, TMT; `Pendidik` wajib NUPTK + Subject; `Tenaga Kependidikan` dengan Subject = `invalid`; separated/archived excluded. Tipe issue: `missing`, `invalid`, `duplicate`, `blocked`.
- **Bentuk issue**: `issue_key` (SHA-256 tanpa nilai pribadi), `type`, `field`, `field_label`, `message` (tidak menyalin nilai field), `subject` (id, version, nama, atribut operasional), `related` (record pasangan untuk duplikat), `review` (`status`, `reason_code`, `version`, `reviewed_at`). Duplikat selalu pasangan; tidak pernah nama saja dan tidak ada auto-merge.
- **Bentuk summary**: `profile_version`, `as_of`, `school_year`, `denominator_basis`, `population`, `records` (`complete`, `incomplete`, `complete_percent`), `fields` (denominator/missing/invalid/complete/completeness), `duplicates` (`candidate_pairs`, `affected_subjects`), `reviews` (`open`, `acknowledged`, `ignored`, `unresolved`). Populasi siswa: `active_students`, `placed`, `unplaced`, `not_evaluated`, `archived_excluded`; pegawai: `active`, `separated`, `archived_excluded`.
- **Filter/status**: `type` ∈ missing/invalid/duplicate/blocked; `field` mengikuti profil domain; `status` default `open`, menerima `acknowledged`/`ignored`/`all`; `per_page` hanya 10/20/50/100 (lainnya `422`); `search` maks. 100 karakter. `rows`/`meta` mengikuti pola paginasi daftar lain.
- **Review payload**: `status` (`open`/`acknowledged`/`ignored`), `reason_code` (wajib saat `ignored`; allowlist `pending_document`, `source_unavailable`, `verified_distinct`, `not_applicable`; `verified_distinct` hanya untuk duplicate), `issue_type`, `field`, `subject_id`, `subject_version`, `related_subject_id`, `related_version`, `version` (versi review yang diharapkan; 0 bila belum ada), serta `school_year_id` (wajib siswa, prohibited pegawai).
- **Konflik/error**: versi review usang → `409` + `current_version`; versi record berubah, issue key tidak cocok, atau issue tidak lagi aktif → `409`; subject/related tidak ada atau terarsip → `404`; validasi → `422`. Issue key mengikat domain, tipe, field, tahun ajaran (siswa), record, versi record, dan related sehingga review lama tidak menyembunyikan data baru.
- **Privasi**: daftar/summary tidak memuat NIK, alamat, telepon, atau data medis; pesan issue tidak menyalin nilai field; audit review hanya menyimpan metadata temuan (tanpa nama/NIK/NUPTK/alamat).
- **UI**: menu/route `/public/data-quality` memakai `requiresAbility: ['students.view','employees.view']` (OR); halaman menampilkan tab Students/Employees, ringkasan, filter, duplikat tanpa merge, dialog review, dan deep link `?openStudent=`/`?openEmployee=` ke halaman lama. Tombol review hanya untuk pemegang manage.

Frontend: `DataQuality.vue`, `DataQualityReviewDialog.vue`, `dataQualityParams.mjs`, `routeAbility.mjs`, dan dukungan `canAny` pada store; deep link additive di `Student.vue` dan `EmployeeDirectory.vue`. Rekap sekolah tetap menunggu DEC-12 (PROPOSED).

## Perubahan Subfase 06a (DEC-29) — periode aktif, lock tahun ajaran, dan kelas

Empat endpoint baru dan satu endpoint opsi wali kelas; tanpa ability baru. Baca periode memakai `students.view`; tulis periode/lock memakai `master.manage`; kelas tetap `students.view`/`students.manage`; opsi guru `employees.view`.

| Method | Endpoint | Izin | Ringkas |
|---|---|---|---|
| GET | `/api/academic/period` | `students.view` | `data.active` (`school_year`, `semester`, atau null), `data.school_years` (tanggal/active/locked/reason), `data.semesters` |
| PUT | `/api/academic/period` | `master.manage` | `active_school_year_id`, `active_semester_id`; menolak tahun locked/soft-deleted dan semester tidak valid (`422`); transaksi + row lock |
| POST | `/api/school-years/{id}/lock` | `master.manage` | `reason` wajib (maks. 255); tahun aktif `422`; lock ulang idempotent tanpa menimpa alasan |
| POST | `/api/school-years/{id}/unlock` | `master.manage` | `reason` wajib (maks. 255); unlock idempotent; lock/unlock diaudit |
| GET | `/api/employees/teacher-options?search=` | `employees.view` | Hanya profil `Pendidik` aktif (`exit_date` null/≥ hari ini), payload `id`,`name`, limit 50, `search` maks. 100 |

- **School Years**: `GET /api/school-years` kini memuat `start_date`, `end_date`, `is_active`, `is_locked`, `locked_at`, `locked_by`, `lock_reason`, `school_rombels_count`. Create/update menerima tanggal nullable `Y-m-d` dan menolak urutan terbalik (`422`). Delete (tetap membutuhkan `delete=true`) ditolak `422` bila tahun aktif, locked, atau masih direferensikan kelas **termasuk kelas soft-deleted**; tahun not-found `404`.
- **School Rombels**: `GET /api/school-rombels?school_year_id=` mengembalikan `version`, `school_year` (termasuk `is_locked`), `school_level`, `student_major`, `class_teach_employee`, `class_teach` legacy, `memberships_count`, `active_members_count`. Create/update memakai `name` (wajib, maks. 100), `school_year_id`/`school_level_id` wajib non-deleted, `student_major_id`/`class_teach_employee_id` opsional; `expected_version` wajib untuk update/delete. Nama unik per tahun secara case-insensitive (`422`); tahun locked (`422`); wali non-`Pendidik` aktif (`422`); tahun/tingkat/jurusan tidak dapat diubah bila sudah ada riwayat keanggotaan. Update/delete versi basi → `409` dengan `current_version` dan `data` terbaru; delete ditolak bila ada riwayat `student_rombels` termasuk soft-deleted.
- **Assign/transfer 03d**: `POST /api/students/{id}/assign-rombel` dan `/transfer-rombel` mengunci baris tahun lebih dulu di transaksi; tahun locked → `422` "The selected academic year is locked. Unlock it before placing/moving students."; tahun atau kelas soft-deleted ditolak validasi. Aturan single-active, versi siswa/membership, dan audit 03d tidak berubah.
- **UI**: `/public/schoolyears` (periode aktif + lock + tanggal) dan `/public/schoolrombels` (CRUD kelas + konflik versi) berada di grup menu **Academic**; route Classes memakai `students.view`, School Years `master.view`. Students memakai periode aktif dari server dan menonaktifkan assign/transfer saat tahun locked.

## Perubahan Subfase 06b (DEC-30) — naik kelas, tinggal kelas, dan lulus

Enam endpoint baru di `/api/academic/progression`; tanpa ability baru. Baca opsi/roster/riwayat memakai `students.view`; preview dan commit memakai `students.manage`.

| Method | Endpoint | Izin | Ringkas |
|---|---|---|---|
| GET | `/api/academic/progression/options?source_school_year_id=&target_school_year_id=` | `students.view` | Tahun sumber/tujuan (tanggal, lock, active), kelas per tahun, `max_batch_size=500` |
| GET | `/api/academic/progression/roster?source_school_year_id=&target_school_year_id=&rombel_ids[]=&search=` | `students.view` | Siswa non-archived dengan tepat satu keanggotaan aktif di tahun sumber; item memuat `student_version`, `membership_id`/`membership_version`, kelas sumber, `eligible`, `ineligible_reason`; maksimum 500 baris + `truncated`/`ambiguous_legacy_count` |
| POST | `/api/academic/progression/preview` | `students.manage` | Validasi tanpa mutasi; `200` + `token`/`expires_at`/`batch_id`/`summary`/`items`, atau `422` item konflik tanpa batch |
| POST | `/api/academic/progression/commit` | `students.manage` | `token` 64 hex; transaksi atomik; `409` itemized tanpa mutasi bila versi/data berubah; `200` replay (`replayed: true`) bila sudah committed; token kedaluwarsa `422`; token akun lain `403` |
| GET | `/api/academic/progression/batches?source_school_year_id=&target_school_year_id=` | `students.view` | Riwayat batch committed untuk semua pembaca; batch preview hanya milik pembuatnya; tanpa token/decisions mentah |
| GET | `/api/academic/progression/batches/{id}` | `students.view` | Detail hasil committed; preview milik akun lain `403` |

- **Payload preview**: `source_school_year_id`, `target_school_year_id` (berbeda, non-deleted), `graduation_date` (`Y-m-d`, wajib bila ada graduate), `decisions` 1–500 dengan `student_id`/`membership_id` unik, `expected_student_version`, `expected_membership_version`, `decision` (`promote`/`retain`/`graduate`/`skip`), `target_rombel_id` wajib untuk promote. Server tidak menerima nama/NIK sebagai kunci.
- **Commit dan lock**: urutan lock batch → tahun → siswa → keanggotaan → kelas tujuan; target harus unlocked bila ada promote, sumber harus unlocked bila ada graduate; retain/skip tidak memerlukan lock. Promote membuat keanggotaan baru di tahun tujuan (status `Aktif`, tanggal masuk `start_date` tujuan, versi 1); graduate mengubah keanggotaan sumber menjadi status `Lulus` (id 6) dengan exit/graduation date dan versi+1; retain/skip tidak memutasi. `students.version` naik hanya untuk siswa yang berubah.
- **Token**: acak 256-bit, hanya SHA-256 yang disimpan, berlaku 30 menit, terikat pada `created_by`; batch tidak dihapus otomatis dan tidak memiliki endpoint delete. Tahun ajaran yang direferensikan batch tidak dapat dihapus (`422`).
- **UI**: `/public/progression` di grup menu **Academic** memakai `students.view`; preview/commit hanya untuk pemegang `students.manage`; halaman Students memakai `counts[6]` untuk kartu Graduated.

Frontend: `StudentProgression.vue` (wizard Years → Students & Decisions → Preview → Result, keputusan persisten lintas filter, dialog `409` + **Reload & Preview Again**, riwayat/detail), route/menu Academic, dan perbaikan kartu Graduated di `Student.vue`.

## Perubahan Subfase 06c (DEC-31) — mutasi, pembatalan, rekap, dan koreksi periode locked

Sepuluh endpoint baru; **satu ability baru** `students.correct-locked` (sensitif, default Admin Sistem) yang wajib bersama `students.manage` hanya untuk koreksi. Baca memakai `students.view`; mutasi/pembatalan memakai `students.manage`. Tidak ada token pada 06c: setiap operasi tunggal divalidasi ulang dan diterapkan dalam satu transaksi.

| Method | Endpoint | Izin | Ringkas |
|---|---|---|---|
| GET | `/api/academic/mutations/options?school_year_id=` | `students.view` | Tahun (tanggal, `is_locked`, `is_active`), kelas per tahun, referensi `entry_mutation_in_id=2`/`status_mutation_out_id=2`/`status_graduated_id=6`, `max_search_results=20`; tahun tidak tersedia `422` |
| GET | `/api/academic/mutations/schools?search=` | `students.view` | Cari sekolah non-deleted berdasarkan NPSN/nama; `items` (`npsn`, `name`, tingkat) maksimum 20 |
| GET | `/api/academic/mutations/recap?school_year_id=` | `students.view` | `year`, `period`, `mutation_in`, `mutation_out`, `graduated`, `retained`, catatan rekap semester tidak tersedia; tahun tanpa tanggal `422` |
| GET | `/api/academic/mutations?school_year_id=&type=&status=&search=&page=` | `students.view` | Riwayat log 20/halaman; filter `in`/`out`, `active`/`cancelled`, pencarian siswa/kelas/NPSN; item memuat versi siswa/membership/mutasi |
| GET | `/api/students/{id}/mutations` | `students.view` | Timeline siswa: ringkasan siswa, daftar mutasi kronologis, daftar koreksi locked, versi terkini |
| POST | `/api/students/{id}/mutations/in` | `students.manage` | `school_rombel_id`, `effective_date` (`Y-m-d`), `school_npsn`, `reason` 10–255, `expected_student_version`; membuat membership baru (`Aktif`, entry `Mutasi Masuk`, NPSN asal); siswa archived/aktif membership/tahun locked/di luar rentang `422`/`409` |
| POST | `/api/students/{id}/mutations/out` | `students.manage` | `student_rombel_id`, `effective_date`, `school_npsn`, `reason`, `expected_student_version`, `expected_membership_version`; menutup satu membership (`Mutasi Keluar`, exit date/tujuan, versi+1), biodata dan tahun lain tidak berubah |
| POST | `/api/students/{id}/mutations/{mutation}/cancel` | `students.manage` | `reason`, `expected_student_version`, `expected_membership_version`, `expected_mutation_version`; pembatalan kompensatif: keluar memulihkan membership, masuk mengarsipkan membership (tidak menghapus); status log `cancelled` |
| GET | `/api/students/{id}/locked-corrections` | `students.view` | Riwayat koreksi immutable siswa (aksi, before/after, aktor, alasan, approval) |
| POST | `/api/students/{id}/locked-corrections` | `students.manage` + `students.correct-locked` | `action` (`mutation_in`/`mutation_out`/`mutation_cancel`/`membership_exit`/`membership_reopen`), `school_year_id`, target (`mutation_id`/`student_rombel_id`/`effective_date`/`school_npsn`/`target_status_id`), `reason` 10–255, `approval_reference` opsional, `expected_student_version`/`expected_membership_version`/`expected_mutation_version`; hanya tahun locked; tahun unlocked `422`; tanpa ability `403` |

- **Konflik versi**: seluruh mutasi/koreksi memakai `expected_*_version`; ketidaksesuaian menghasilkan `409` dengan `errors` itemized dan `current_versions`, tanpa mutasi. Tidak ada endpoint update/delete untuk log mutasi maupun koreksi.
- **Audit**: `StudentMutation` memakai allowlist tanpa `reason`, NIK, nama, atau alamat; `StudentRombel` menambah `mutation_education_school_id`/`latest_student_exit_school_id`; kegagalan audit me-rollback transaksi. Koreksi locked menyimpan snapshot `before`/`after` (id/status/tanggal/versi) di tabel immutable.
- **Rekap**: `mutation_in`/`mutation_out` menghitung log `active` bertanggal dalam rentang tahun; `cancelled` dikecualikan; `graduated` menghitung siswa unik status `Lulus` dengan tanggal lulus dalam rentang; `retained` diambil dari keputusan `retain` pada batch progresi committed terbaru per siswa. Rekap semester ditunda karena `semesters` belum memiliki tanggal.
- **UI**: section **Students → Mutations** dengan pemilih tahun sendiri (default periode aktif), kartu rekap, tabel log, pencarian siswa/sekolah, dialog In/Out/Cancel/Correct, dan dialog timeline. Tombol tulis/Correct mengikuti ability dan status lock; server tetap menegakkan `403`/`422`/`409`.

Frontend: `StudentMutations.vue` (section + dialog) dan `StudentMutationTimelineDialog.vue` di `Student.vue`.

## Aktual

Resource di `/api`, dilindungi auth:sanctum: users, status-users, type-users, firstlogin; provinces/regencies/districts/villages; school-years/semesters/education-levels; genders/religions/blood-types/professions/family-status; education-schools/student-statuses/student-entries/student-majors/phase-statuses; students/school-levels/school-rombels/student-rombels; employees (alias deprecated: employee)/employee-statuses/employee-types/employments/marital-statuses; school-profile/subjects/activities.
`/api/user` mengambil actor. `/api/check-auth` mengembalikan authenticated + user atau null. Route auth web mencakup login/logout/register/forgot-password/reset-password dan email verification. Sejak 05c tersedia `/api/data-quality/students/*` dan `/api/data-quality/employees/*` (enam route). Sejak 06a tersedia `/api/academic/period`, `/api/school-years/{id}/lock`, `/api/school-years/{id}/unlock`, dan `/api/employees/teacher-options`. Sejak 06b tersedia enam route `/api/academic/progression/*` (options, roster, preview, commit, batches, batches/{id}). Sejak 06c tersedia empat route `/api/academic/mutations/*` (options, schools, recap, index), empat route `/api/students/{id}/mutations*` (timeline, in, out, cancel), dan dua route `/api/students/{id}/locked-corrections` (index, store). Daftar route bukan bukti semua handler lengkap.
`GET /api/student-rombels/{id}` baseline memakai ID tahun ajaran untuk mengembalikan daftar keanggotaan, bukan ID membership. Pertahankan consumer atau migrasikan bersama; jangan mengubah makna `{id}` diam-diam.
Frontend reusable memakai POST dengan `_method` untuk update/delete. Audit method/payload sebenarnya sebelum memigrasi menjadi PUT/DELETE.

## Standar target
| Keadaan | HTTP target | Bentuk |
|---|---|---|
| List/detail sukses | 200 | data + meta/filter bila list |
| Create sukses | 201 | objek baru |
| Delete sukses | 200 dengan pesan atau 204 | pilih konsisten dan sesuaikan Vue |
| Belum login | 401 | pesan umum tanpa detail pribadi |
| Tidak berizin | 403 | pesan umum |
| Objek tidak ada/tidak boleh diungkap | 404 | keputusan policy konsisten |
| Validasi gagal | 422 | message + errors per field |
| Versi/idempotency konflik | 409 | message, latest yang aman bila diizinkan |
| Job diterima | 202 | job_id + status endpoint |

Ini target, bukan format response baseline. Gunakan Resource khusus domain untuk mengurangi payload; jangan mengubah seluruh endpoint sekaligus. Catat perubahan dan migrasi consumer.
Query list target: search, page, per_page, school_year_id, status, rombel_id sesuai domain; sort/order whitelist; limit maksimum. Export menerima filter yang sama dan mengekspor seluruh hasil cocok, bukan hanya halaman aktif, tetap dengan izin dan batas ukuran.
Kontrak date memakai ISO `YYYY-MM-DD`, timestamp dengan timezone/UTC yang jelas; presentasi WIB di UI. Nomor identitas tetap string. Missing/null/empty didefinisikan per field.

## Register perubahan per fase
Untuk setiap endpoint baru/berubah catat method/path, kemampuan, scope record, request, response, validasi, error dan contoh sintetis di laporan fase. Nomor surat/keputusan/perubahan status dibuat server. Idempotency key tidak dapat dipakai ulang dengan payload berbeda.
Reset password frontend route baseline belum ditemukan pada router; audit alur lengkap, jangan menyatakan reset berfungsi hanya karena backend route ada.
