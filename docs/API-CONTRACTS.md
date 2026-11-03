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

## Perubahan Subfase 07a (DEC-32) — surat masuk dan lampiran dasar

Sembilan endpoint baru di `/api/letters/incoming`; **tanpa ability baru**. Baca (options/list/detail/download) memakai `letters.view`; tulis (create/update/void/upload/remove) memakai `letters.manage`; guest `401`, tanpa ability `403`. Surat void bersifat read-only (`409`), tidak ada endpoint delete surat atau unvoid.

| Method | Endpoint | Izin | Ringkas |
|---|---|---|---|
| GET | `/api/letters/incoming/options` | `letters.view` | `categories` (7), `statuses` (`active`/`void`), `per_page_options` (10/20/50/100), `default_per_page=20`, `max_file_kb=10240`, `max_files_per_letter=5`, `allowed_extensions` |
| GET | `/api/letters/incoming?search=&category=&status=&received_from=&received_to=&per_page=&page=` | `letters.view` | Daftar 20/halaman default; urut `received_date` desc lalu `id` desc; `status` default `active`, menerima `void`/`all`; `search` maks. 100 pada nomor sumber/pengirim/perihal (wildcard di-escape) |
| GET | `/api/letters/incoming/{letter}` | `letters.view` | Detail + `attachments` (id, nama asli, MIME, ukuran, waktu) + `events` terbaru (maks. 100; aktor, metadata minimal); tanpa path/checksum |
| POST | `/api/letters/incoming` | `letters.manage` | `received_date`/`letter_date` (`Y-m-d`; terima ≤ hari ini, surat ≤ terima), `source_number` opsional ≤100 (tidak unik), `sender` ≤150, `subject` ≤200, `category` ∈ config, `notes` ≤2000 opsional; `422` validasi |
| PUT/PATCH | `/api/letters/incoming/{letter}` | `letters.manage` | Field create + `expected_version` wajib; sukses menaikkan versi dan menulis event Updated; versi basi `409` + `current_versions`; surat void `409` |
| POST | `/api/letters/incoming/{letter}/void` | `letters.manage` | `reason` 10–255 + `expected_version`; status `void`, `voided_at`/`voided_by`, versi+1, event Voided; void ulang `409` |
| POST | `/api/letters/incoming/{letter}/attachments` | `letters.manage` | Multipart `file`; PDF/JPG/JPEG/PNG; maks. 10 MB; maks. 5 lampiran aktif per surat; MIME dideteksi server; `422` tipe/ukuran/limit; surat void `409` |
| GET | `/api/letters/incoming/{letter}/attachments/{attachment}/download` | `letters.view` | Streamed privat; `Content-Disposition: attachment`, `nosniff`, CSP sandbox, `Cache-Control: no-store, private`; lampiran milik surat lain `404` |
| DELETE | `/api/letters/incoming/{letter}/attachments/{attachment}` | `letters.manage` | Soft delete baris + event Attachment removed; file fisik dipertahankan; surat void `409`; `404` bila bukan milik surat |

- **Envelope dan status**: seluruh respons JSON memakai `StatusResource` (`success`, `message`, `data`). Create/update/void/upload/remove mengembalikan **`200`** dengan envelope (mengikuti pola controller aplikasi), bukan `201`.
- **Bentuk item surat**: `id`, `reference` (`#` + id 6 digit), `direction`, `received_date`, `letter_date`, `source_number`, `sender`, `subject`, `category`, `notes`, `status`, `version`, `attachments_count`, `voided_at`/`void_reason`/`voided_by`, `created_by`, timestamps. Daftar memuat `items`/`meta`/`filters`.
- **Konflik**: versi basi atau surat void → `409` dengan `message`, `errors` per field, dan `current_versions` (`letter_version`, `status`). Tidak ada mutasi saat konflik.
- **Lampiran privat**: disimpan di disk `local` (config `letters.disk`) pada `attachments/letters/{letter_id}/{uuid}.{ext}`; nama asli hanya metadata; `checksum_sha256` tidak diekspos di payload; file tidak dapat diakses lewat URL publik. Unduhan lintas surat `404`; route delete surat tidak terdaftar (`405`).
- **Audit/history**: `activities` memakai allowlist `Letter` (direction/tanggal/nomor sumber/kategori/status/versi/void/aktor) dan `LetterAttachment` (letter_id/MIME/ukuran/deleted_at) tanpa notes/sender/subject/alasan void/nama asli/path/checksum; `letter_events` immutable dan tidak memakai `Loggable`.
- **UI**: `/public/letters/incoming` (name `incomingLetters`, breadcrumb `Incoming Letters`) dengan filter, tabel, dialog detail/create/edit/void, upload/remove/download lampiran, history, dan panel konflik **Reload Latest**. Grup menu **Letters** menggantikan item dummy **Letters Form**; route memakai `letters.view`, tombol tulis `letters.manage`.
- **Batas**: 07a tidak menyediakan disposisi, penomoran resmi, approval/sign, template/PDF, ekspor, atau pembersihan file retensi (DEC-13). 07b/07c memerlukan rencana dan keputusan terpisah; 07c memerlukan keputusan dependency PDF.

Frontend: `IncomingLetters.vue`, route `/public/letters/incoming`, dan grup menu **Letters**; dummy **Letters Form** dihapus.

## Perubahan Subfase 07b (DEC-33) — surat keluar, disposisi, dan nomor resmi

**19 route baru** (enam disposisi + 13 surat keluar), sehingga total route `/api/letters*` menjadi **28** (sembilan 07a + 19 07b). Tidak ada ability/role migration baru; `letters.approve` dan `letters.sign` mulai ditegakkan. Guest `401`; tanpa ability `403`; objek lintas surat/tipe `404`; konflik state/versi `409`; validasi `422`. Semua mutasi mengembalikan `200` + `StatusResource`.

### Disposisi surat masuk

| Method | Endpoint | Izin | Ringkas |
|---|---|---|---|
| GET | `/api/letters/dispositions/options` | `letters.view` | Status disposisi (`pending`/`in_progress`/`completed`/`cancelled`), `per_page_options`, batas catatan |
| GET | `/api/letters/dispositions/recipients?search=&per_page=` | `letters.manage` + `employees.view` | Pegawai aktif (id, nama, tipe); tanpa NIK/kontak |
| GET | `/api/letters/dispositions?status=&overdue=&recipient_employee_id=&search=&per_page=&page=` | `letters.view` | Worklist semua disposisi; urut `due_date` asc lalu `id`; `overdue=1` = due date < hari ini dan status terbuka; wildcard `%`/`_` di-escape |
| POST | `/api/letters/incoming/{letter}/dispositions` | `letters.manage` | `recipients[]` 1–100 unik, `due_date` (`Y-m-d`), `note` ≤2000 opsional; satu baris + event per penerima; duplikasi terbuka/penerima tidak aktif/surat void `422`/`409` |
| PUT/PATCH | `/api/letters/incoming/{letter}/dispositions/{disposition}` | `letters.manage` | `status`, `due_date`, `note`, `follow_up_note` (wajib 10–2000 saat completed), `cancel_reason` (wajib 10–255 saat cancelled), `expected_version`; transisi `pending→in_progress→completed`, `pending|in_progress→cancelled`; tidak ada reopen |
| POST | `/api/letters/incoming/{letter}/dispositions/{disposition}/cancel` | `letters.manage` | `reason` 10–255 + `expected_version`; status cancelled; baris tidak dihapus |

- Disposisi hanya untuk `direction=incoming`; `{disposition}` harus milik `{letter}`.
- `GET /api/letters/incoming/{letter}` memuat `dispositions`; list incoming memuat `dispositions_open_count` dan `dispositions_overdue_count`.
- Void surat ditolak `409` (`errors.open_dispositions` + jumlah) selama ada disposisi `pending`/`in_progress`; setelah completed/cancelled void dapat dilakukan.
- `overdue` dihitung saat baca (Asia/Jakarta), tidak disimpan.

### Surat keluar

| Method | Endpoint | Izin | Ringkas |
|---|---|---|---|
| GET | `/api/letters/outgoing/options` | `letters.view` | Kategori, lima status workflow, paginasi, batas lampiran, `default_sender`, `numbering{format,example,scope,period}` (contoh, bukan nomor berikutnya) |
| GET | `/api/letters/outgoing?search=&workflow_status=&letter_from=&letter_to=&per_page=&page=` | `letters.view` | Default semua status; cari `official_number`/`recipient`/`sender`/`subject`; filter tanggal surat; urut `letter_date` desc lalu `id` desc |
| GET | `/api/letters/outgoing/{letter}` | `letters.view` | Detail + lampiran + 100 event + `approvals`; `dispositions` kosong |
| POST | `/api/letters/outgoing` | `letters.manage` | Draft; `received_date`/`letter_date` (terima ≤ hari ini, surat ≤ terima), `sender` ≤150, `recipient` wajib ≤150, `subject` ≤200, `category` ∈ config, `notes` ≤2000; tanpa nomor |
| PUT/PATCH | `/api/letters/outgoing/{letter}` | `letters.manage` | Hanya draft + `expected_version`; selain draft `409` |
| POST | `/api/letters/outgoing/{letter}/submit` | `letters.manage` | `draft→review` + `expected_version`; setelah submit edit/lampiran terkunci |
| POST | `/api/letters/outgoing/{letter}/approve` | `letters.approve` | `review→approved` + `expected_version`; notes opsional ≤500; aktor ≠ `created_by` (`403`); ledger `approved` |
| POST | `/api/letters/outgoing/{letter}/reject` | `letters.approve` | `review→draft` + `expected_version`; alasan 10–255; aktor ≠ `created_by` (`403`); ledger `rejected` |
| POST | `/api/letters/outgoing/{letter}/issue` | `letters.sign` | `approved→issued` + `expected_version`; aktor ≠ `created_by` (`403`); nomor atomik; replay/issue kedua `409` + nomor yang ada |
| POST | `/api/letters/outgoing/{letter}/cancel` | `letters.view` + dinamis | Draft/review butuh `letters.manage`; approved/issued butuh `letters.approve`; `reason` 10–255 + `expected_version`; nomor (bila ada) dipertahankan; cancelled read-only |
| POST | `/api/letters/outgoing/{letter}/attachments` | `letters.manage` | Multipart `file`; hanya draft; aturan 07a (PDF/JPG/PNG, 10 MB, 5 aktif) |
| GET | `/api/letters/outgoing/{letter}/attachments/{attachment}/download` | `letters.view` | Streamed privat + header keamanan 07a; lintas surat `404` |
| DELETE | `/api/letters/outgoing/{letter}/attachments/{attachment}` | `letters.manage` | Soft delete + event; hanya draft; file fisik dipertahankan |

- **Workflow**: `draft → review → approved → issued`; `reject` kembali ke `draft`; cancel dari semua status aktif; `cancelled` final dan `status=void`. Tidak ada un-cancel, edit nomor, issue massal, atau delete surat.
- **Nomor resmi**: diberikan saat issue dari counter `(scope=outgoing, period=YYYY)` dengan `lockForUpdate`; format config `{seq:03d}/{school_code}/{year}` (contoh `001/20512345/2026`), `school_code` dari `LETTERS_NUMBER_SCHOOL_CODE` atau NPSN sekolah aktif; unique `official_number`; nomor tidak dipakai ulang setelah cancel dan counter tidak mundur.
- **Konflik**: `409` memuat `current_versions` surat (`letter_version`, `status`, `workflow_status`, `official_number`) atau disposisi (`disposition_version`, `status`), tanpa mutasi.
- **Bentuk item outgoing**: field incoming + `recipient`, `workflow_status`, `lifecycle_status`, `official_number`, `approved_at`/`approved_by`, `issued_at`/`issued_by`, `cancel_reason` (alias baca `void_reason`), `attachments_count`.
- **Audit/history**: allowlist `Letter` menambah `official_number`/workflow/approved/issued tanpa notes/sender/subject/recipient/alasan; `letter_approvals` dan `letter_events` immutable; event 07b: `submitted`, `approved`, `rejected`, `issued`, `cancelled`, `disposition_created`, `disposition_updated`, `disposition_completed`, `disposition_cancelled`.
- **UI**: `/public/letters/outgoing` (breadcrumb **Outgoing Letters**) dan `/public/letters/dispositions` (**Letter Dispositions**), keduanya `letters.view`; tombol approve/reject `letters.approve`, issue `letters.sign`, tulis `letters.manage`. Detail incoming mendapat panel disposisi + dialog Assign multi-penerima.
- **Batas**: tidak ada PDF/template/snapshot (07c/DEC-11), notifikasi email/WhatsApp, disposisi di luar pegawai, atau retensi file (DEC-13).

Frontend: `OutgoingLetters.vue`, `LetterDispositions.vue`, panel disposisi di `IncomingLetters.vue`, route router, dan menu **Letters → Incoming/Outgoing/Dispositions**.

## Perubahan Subfase 07c (DEC-34) — kop, tanda tangan, stempel, template PDF, dan snapshot

**9 route baru** (4 settings + 3 subject picker + 2 dokumen), sehingga total route `/api/letters*` menjadi **37** (9 incoming + 6 disposisi + 13 outgoing 07b + 9 07c). Tidak ada ability/role migration baru. Guest `401`; tanpa ability `403`; objek lintas tipe/surat `404`; validasi/readiness `422`; konflik versi `409`.

| Method | Endpoint | Izin | Perilaku |
|---|---|---|---|
| GET | `/api/letters/settings` | `letters.view` | Tahun ajaran aktif, profil sekolah, settings tersanitasi (tanpa path/hash), readiness `{active_year, profile, header, signatory, signature, template, ready, missing}`, suggestions, permissions |
| POST | `/api/letters/settings/header` | `letters.manage` | Simpan kop + logo (multipart) + `expected_version`; logo PNG/JPG ≤2 MB/≤2000×2000; `422`/`409` tanpa mutasi |
| POST | `/api/letters/settings/signatory` | `letters.sign` | Simpan penandatangan + signature/stamp (multipart) + `expected_version`; signature wajib; aset di-re-encode PNG; `422`/`409` |
| GET | `/api/letters/settings/assets/{asset}` | `letters.view` untuk `logo`; `letters.sign` untuk `signature`/`stamp` | Stream aset privat; whitelist asset; tanpa URL publik |
| GET | `/api/letters/outgoing/subjects/students?search=&per_page=` | `letters.manage` + `students.view` | Siswa dengan keanggotaan aktif tahun aktif; id/nama/NIS/NISN/kelas; tanpa NIK/alamat |
| GET | `/api/letters/outgoing/subjects/student-mutations?search=&per_page=` | `letters.manage` + `students.view` | Mutasi `out` aktif; id/siswa/tanggal/sekolah tujuan; tanpa alasan panjang |
| GET | `/api/letters/outgoing/subjects/employees?search=&per_page=` | `letters.manage` + `employees.view` | Pegawai aktif; id/nama/NUPTK/tipe; tanpa NIK/alamat/kontak |
| GET | `/api/letters/outgoing/{letter}/document` | `letters.view` | PDF tersimpan untuk issued/cancelled; `404` untuk draft/review/approved/general/legacy/file hilang; tanpa regenerasi |
| GET | `/api/letters/outgoing/{letter}/document/preview` | `letters.view` | PDF preview watermarked untuk draft/review/approved typed; `404` untuk issued/cancelled/general/legacy; `422` jika settings/subjek tidak valid; tanpa nomor/counter/penyimpanan |

- Route `subjects/*` didaftarkan sebelum `/outgoing/{letter}` dan memakai `whereNumber('letter')`.
- **Typed fields**: create/update menerima `letter_type` (`student_active`/`student_mutation`/`employee_duty` atau null untuk general), `document_subject_id`, dan `document_fields`; `student_active` wajib `purpose` 5–500; `employee_duty` wajib `duty_description` 10–2000, `start_date`, `end_date`, `location` 5–200; `student_mutation` tidak memakai field tambahan. Tipe/subjek immutable setelah create; fields hanya editable saat draft; general letter 07b tidak berubah.
- **Issue typed**: validasi readiness settings + eligibility subjek, snapshot minimal PII, nomor atomik, render PDF via `dompdf` (A4 portrait, DejaVu Sans, aset data URI, remote resource off), tulis file privat, simpan `letter_documents` + event, semuanya dalam transaksi. Gagal → rollback, surat tetap approved, nomor tidak dikonsumsi, file dibersihkan best-effort. Tidak ada reissue/regenerate/backfill.
- **Dokumen**: `letter_documents` unique per surat, immutable; snapshot berisi template/school/signatory/letter/subject/fields/issued_at/by tanpa NIK/alamat/kontak; API hanya mengekspos `available`, `template_code`, `template_version`, `page_count`, `generated_at`, `size_bytes`; tanpa path/hash/snapshot.
- **Header PDF**: `Content-Type: application/pdf`, `X-Document-Filename`, `Access-Control-Expose-Headers: X-Document-Filename`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, `Content-Security-Policy: sandbox`, `Content-Length`. **Tanpa `Content-Disposition`** karena browser memblokir `fetch` lintas origin berkredensial untuk respons yang memuat header tersebut; UI memakai `X-Document-Filename` + atribut `download`. Preview memakai nama `preview-letter-{id}.pdf`; unduhan memakai nomor resmi tersanitasi (mis. `007-20512345-2026.pdf`).
- **Bentuk item outgoing** menambahkan `letter_type`, `letter_type_label`, `has_document`, dan `document` (ringkasan untuk detail). Filter list: `letter_type` (whitelist) dan `has_document` (`0/1`); options menambahkan `letter_types`, `settings_ready`, `settings_missing`, dan capability `document`.
- **Audit/history**: allowlist `Letter` menambah `letter_type` tetapi tidak `document_fields`/`document_subject_id`; `LetterDocument` tidak memakai `Loggable` dan menolak update/delete; event `issued` typed menambahkan `template_code`/`template_version`/`page_count` tanpa nama siswa/NISN/alasan/purpose/path; `LetterSettings` allowlist tanpa alamat/kontak/nama/identifier/path.
- **UI**: `/public/letters/settings` (**Letter Settings**, `letters.view`) dan perluasan `/public/letters/outgoing` (tipe form, subject picker, document card, filter Type/Has document, preview/download). Menu **Letters → Incoming/Outgoing/Dispositions/Letter Settings**.
- **Batas**: tidak ada template CRUD/editor, DOCX/PDF-A, e-sign tersertifikasi/QR/OCR, email/WhatsApp, notifikasi, bulk generation, arsip/versioning Fase 09, retensi DEC-13, rekap DEC-12, surat izin Fase 08, atau backfill PDF surat 07b/legacy.

Frontend 07c: `LetterSettings.vue`, `OutgoingLetters.vue` (typed form/document card), `router/index.js`, `AppMenu.vue`.

## Perubahan Subfase 08a (DEC-35) — pengajuan izin siswa dan pegawai

**13 route baru** `/api/leaves*`. Tidak ada route keputusan/rekap 08b. Guest `401`; akun nonaktif `403`; tanpa ability `403`; objek tidak terlihat `404`; validasi/kelayakan/overlap `422`; konflik versi/status `409`.

| Method | Endpoint | Izin | Perilaku |
|---|---|---|---|
| GET | `/api/leaves/options` | `viewAny` (`leaves.view`/`leaves.request`/`leaves.manage`) | Jenis siswa/pegawai + batas hari, status, batas alasan, pagination (10/20/50/100, default 20), aturan lampiran (3×5 MB), tahun ajaran aktif, `self_employee`, dan `can {view, request, manage, approve}` |
| GET | `/api/leaves` | `leaves.view` | Daftar seluruh pengajuan; filter `search`, `status`, `subject_type`, `type`, `date_from`, `date_to`, `school_year_id`, `rombel_id`, `employee_type_id`, `sort`, `direction`, pagination |
| GET | `/api/leaves/mine` | `leaves.request` | Pengajuan dengan subjek pegawai yang tertaut tepat ke aktor (`employees.user_id`); filter/pagination sama |
| GET | `/api/leaves/subjects/students?search=&per_page=` | `leaves.manage` + `students.view` | Siswa dengan keanggotaan aktif tahun aktif; id/nama/NIS/NISN/kelas/tahun; tanpa NIK/alamat |
| GET | `/api/leaves/subjects/employees?search=&per_page=` | `leaves.manage` + `employees.view` | Pegawai aktif; id/nama/NUPTK/tipe; tanpa NIK/alamat/kontak |
| GET | `/api/leaves/{leave}` | policy view (`view`/`manage`, pembuat, subjek pegawai sendiri) | Detail + lampiran + riwayat keputusan; tanpa path/hash internal |
| POST | `/api/leaves` | policy create | `subject_type`, `student_id` atau `employee_id`, `type`, `start_date`, `end_date`, `reason`; siswa hanya `leaves.manage`; pegawai self hanya `leaves.request` + tautan tepat |
| PUT/PATCH | `/api/leaves/{leave}` | pembuat atau `leaves.manage` | Hanya draft; `expected_version`; field subjek/tipe immutable setelah create |
| POST | `/api/leaves/{leave}/submit` | pembuat atau `leaves.manage` | Validasi ulang kelayakan subjek, tanggal, tahun ajaran, dan overlap; `409`/`422` |
| POST | `/api/leaves/{leave}/cancel` | pembuat atau `leaves.manage` | Draft: alasan opsional; submitted: alasan wajib 10–255; menulis `leave_decisions` `cancelled` |
| POST | `/api/leaves/{leave}/attachments` | pembuat atau `leaves.manage` | Hanya draft/submitted; maksimal 3; PDF/JPG/JPEG/PNG ≤5 MB; validasi bersama |
| GET | `/api/leaves/{leave}/attachments/{attachment}/download` | policy view | Stream privat; `X-Document-Filename`, `nosniff`, CSP sandbox, `Cache-Control: private, no-store`; tanpa `Content-Disposition` |
| DELETE | `/api/leaves/{leave}/attachments/{attachment}` | pembuat atau `leaves.manage` | Soft delete baris; file fisik dipertahankan sampai DEC-13 |

- **Bentuk item ringkas**: `id`, `subject_type`, subjek (`student` id/nama/NIS/NISN/kelas/tahun atau `employee` id/nama/NUPTK/tipe), `type` + label, `start_date`/`end_date`/`days`, `status` + label, `version`, `requested_by`, `submitted_at`, `decided_at`, `cancelled_at`, `attachments_count`, dan `can {view, edit, submit, cancel, upload_attachment}`.
- **Bentuk detail** menambahkan `reason`, `submitted_by`, `decided_by`, `cancelled_by`, daftar `attachments` (nama asli, MIME, ukuran, waktu), dan `decisions` (action/from/to/actor/reason/created_at). `leave_decisions` tidak dapat diubah/dihapus; `permissions.approve/reject` selalu false pada 08a.
- **Aturan tanggal/overlap**: inklusif WIB; `start_date <= end_date`; batas jenis 30 hari (maternity/paternity 90) dan global 90 hari; siswa harus di dalam tahun ajaran aktif dan memiliki keanggotaan aktif; pegawai boleh tanggal lampau; overlap terhadap subjek sama berstatus `submitted`/`approved` → `422` dengan `errors.overlap` berisi ID/rentang konflik tanpa data pribadi; draft dan status final lain tidak memblokir.
- **Validasi request**: `type` harus milik `subject_type`; alasan 5–1000 karakter; alasan batal 10–255; `expected_version` wajib untuk update/submit/cancel; `per_page` hanya 10/20/50/100.
- **Audit/privasi**: allowlist `LeaveRequest` tanpa `reason`; `LeaveDecision` tanpa `reason`; `LeaveAttachment` hanya leave_request_id/mime/ukuran/deleted_at; nama subjek, NIS/NISN/NUPTK, isi lampiran, path, checksum, dan alasan tidak masuk audit. `leave_requests` menolak delete dan `leave_decisions` menolak update/delete pada level model.
- **UI**: `/public/leaves` (menu **Leaves**) dengan tab **Requests** (`leaves.view`) dan **My Leaves** (`leaves.request`); tombol New Request/New Self Request/edit/submit/cancel/upload mengikuti ability + kepemilikan. Label peran `leaves.request` tersedia di Roles.
- **Batas**: tidak ada approve/reject/rekap, pembatalan approved, delegasi per-permintaan, approval berjenjang, izin setengah hari/per jam/berulang/lintas tahun, akun siswa/orang tua, absensi otomatis, saldo cuti, notifikasi email/WhatsApp, PDF surat izin, impor/ekspor CSV izin, retensi DEC-13, atau perubahan dependency/service/`.env`.

Frontend 08a: `Leaves.vue`, `router/index.js`, `AppMenu.vue`, `Roles.vue` (label ability).

## Perubahan Subfase 08b (DEC-36) — keputusan izin dan rekap

**3 route baru** (approve, reject, recap), sehingga total route `/api/leaves*` menjadi **16**. Tidak ada tabel/kolom/migration/ability/peran/dependency baru. Guest `401`; akun nonaktif `403`; tanpa ability `403`; objek tidak terlihat `404`; validasi `422`; konflik versi/status `409`.

| Method | Endpoint | Izin | Perilaku |
|---|---|---|---|
| POST | `/api/leaves/{leave}/approve` | middleware `can:leaves.approve` + policy approve (`leaves.approve` + `leaves.view`, bukan pembuat/subjek) | `submitted → approved`; `expected_version` wajib; `reason` opsional 10–500; cek ulang overlap; menulis `leave_decisions` `approved` + `decided_at/by`; `200`/`403`/`404`/`409`/`422` |
| POST | `/api/leaves/{leave}/reject` | sama | `submitted → rejected`; `expected_version` wajib; `reason` wajib 10–500; menulis `leave_decisions` `rejected` + `decided_at/by`; `200`/`403`/`404`/`409`/`422` |
| GET | `/api/leaves/recap` | `can:leaves.view` | Rekap agregat periode; `from`/`to` wajib `Y-m-d`; `200`/`403`/`422` |

- Route `GET /recap` didaftarkan sebelum `GET /{leave}` dan `{leave}` memakai `whereNumber`; approve/reject berada bersama mutasi lain dan tidak bertabrakan dengan detail.
- **Approve/reject**: hanya izin `submitted`; status lain `409` dengan ringkasan `latest` yang aman. Versi basi/replay `409`. Approver wajib memegang `leaves.approve` **dan** `leaves.view`, bukan `requested_by`, dan bukan subjek pegawai yang tertaut ke akunnya (`employees.user_id`); admin/operator/waka/guru tanpa ability tetap `403`. Alasan approve kosong dinormalisasi menjadi null; reject tanpa alasan `422`.
- **Cancel approved** memakai route 08a `POST /api/leaves/{leave}/cancel`: policy bercabang; `draft`/`submitted` tetap pembuat atau `leaves.manage`; `approved` hanya approver (bukan pembuat/subjek) dengan alasan wajib 10–255; `decided_at/by` dipertahankan dan riwayat mencatat `approved` lalu `cancelled`; `rejected`/`cancelled` final (`409`); pembuat/`leaves.manage` tanpa ability approve tidak dapat membatalkan izin approved.
- **Approve tidak memvalidasi ulang** config jenis, kelayakan master, atau tahun ajaran; hanya mengecek ulang overlap terhadap subjek yang sama berstatus `submitted`/`approved` (D3). Semua transisi memakai transaksi + `lockForUpdate` + `expected_version`.
- **`permissions` objek** (detail/summary) memuat `view`, `edit`, `submit`, `cancel`, `approve`, `reject`, `upload`, `delete_attachment`. `approve`/`reject` bernilai true hanya untuk `submitted` + policy `decides()`; `cancel` bernilai true untuk draft/submitted yang dapat diubah atau approved + `decides()`. Nilai ini server-driven, bukan pengganti enforcement.
- **Options additive**: `decision_reason` (min/max) hanya untuk pemegang `leaves.approve`; `recap` (max 366 hari, school years, rombels, employee types) hanya untuk `leaves.view`; `can.approve` diselaraskan dengan policy.
- **Rekap**: query berdasarkan irisan tanggal izin (`start_date <= to AND end_date >= from`), inklusif WIB; `days` dipotong ke irisan periode. Default semua status. Filter opsional `status`, `type`, `subject_type`, `school_year_id`, `rombel_id`, `employee_type_id`; `school_year_id`/`rombel_id` memaksa subjek siswa, `employee_type_id` memaksa subjek pegawai, kelas + tipe pegawai tidak boleh digabung, dan `type` harus milik `subject_type` (`422`). Rentang maksimum **366 hari**; `to < from` `422`. Kelas memakai `withTrashed()` (kelas terhapus tetap bernama); siswa tanpa keanggotaan tampil `Unplaced` (`rombel_id: null`).
- **Bentuk respons rekap**: `period {from,to,days}`, `filters {status,type,subject_type,school_year_id,rombel_id,employee_type_id}`, `totals {requests,days,students,employees}`, `by_status [{status,label,requests,days}]`, `by_type [{subject_type,type,label,requests,days}]`, `by_class [{rombel_id,class,requests,days}]`, `by_employee [{employee_id,name,nuptk,employee_type,requests,days}]`. Tanpa alasan, lampiran, path/hash, NIK/NISN, atau teks bebas; tanpa pagination/ekspor.
- **Audit/privasi**: `LeaveRequest` allowlist tetap tanpa `reason`; `LeaveDecision` append-only dan alasan tidak masuk audit; rekap tidak menambah penulisan data (GET tidak mengubah domain). Retensi lampiran tetap DEC-13.
- **UI**: tombol **Approve**/**Reject** pada baris/tabel dan detail hanya bila `data.can.approve`/`can.reject`; dialog alasan (approve opsional, reject wajib 10–500); **Cancel Approved** pada izin approved dengan alasan 10–255; tab ketiga **Recap** (`leaves.view`) dengan filter, kartu Total requests/days/Students/Employees, tabel By status/type/class/employee, empty/loading/error; konflik `409` memakai dialog **Reload Latest**; English, 390 px, tabel scroll horizontal, tanpa tombol dummy.
- **Batas**: tidak ada route ke-17, delegasi per-permintaan, approval berjenjang, notifikasi, ekspor/PDF, atau perubahan schema. Guru (`leaves.request` saja) hanya melihat My Leaves dan mendapat `403` untuk daftar/rekap.

Frontend 08b: `Leaves.vue` (aksi keputusan, dialog, tab Recap), tanpa route/menu baru.

## Perubahan Subfase 09a (DEC-37) — metadata dan versi arsip dokumen

**9 route baru** `/api/archives*`. Tidak ada route delete/restore/preview. Guest `401`; akun nonaktif `403`; tanpa ability `403`; filter subjek tanpa ability domain `403`; objek/unduhan tersembunyi `404`; validasi/tautan subjek/file `422`; konflik versi `409`.

| Method | Endpoint | Izin | Perilaku |
|---|---|---|---|
| GET | `/api/archives/options` | `archives.view` | Kategori, tipe subjek yang diizinkan aktor, status validitas, pagination, batas judul/nomor/deskripsi/catatan, aturan file, `expiring_soon_days`, `search_max_length`, `can {view, manage}` |
| GET | `/api/archives/subjects/{type}` | `archives.view` + ability domain | Picker `q`/`limit` (1–100, default 20); `type` dibatasi config; tanpa NIK/alamat |
| GET | `/api/archives` | `archives.view` | Daftar dokumen umum + domain yang diizinkan; filter `q`, `category`, `subject_type`, `subject_id`, `validity`, `date_from`, `date_to`, `per_page` (10/20/50/100), `page` |
| POST | `/api/archives` | `archives.manage` | Multipart metadata + `file` wajib; subjek both-or-none; `200` (pola `StatusResource`) |
| GET | `/api/archives/{archive}` | policy view (`archives.view` + domain) | Detail + `latest_version`; tersembunyi `404` |
| PUT/PATCH | `/api/archives/{archive}` | policy update (`archives.manage` + domain) | Metadata saja + `expected_version`; subjek/file `prohibited`; `409` saat versi basi |
| GET | `/api/archives/{archive}/versions` | policy view | Riwayat versi terbaru dahulu + `meta`; `per_page`/`page` |
| POST | `/api/archives/{archive}/versions` | policy `addVersion` (`archives.manage` + domain) | File + catatan opsional ≤500 + `expected_version`; nomor versi baru |
| GET | `/api/archives/{archive}/versions/{version}/download` | policy download (= view) | Stream file milik dokumen; `404` bila dokumen/versi/file tidak ada |

- **Route order**: `/options` dan `/subjects/{type}` didaftarkan sebelum `/{archive}`; `{archive}`/`{version}` memakai `whereNumber`; `type` memakai `whereIn` kunci `config('archives.subject_types')`.
- **Bentuk item ringkas**: `id`, `category`, `category_label`, `title`, `document_number`, `document_date`, `valid_from`, `valid_until`, `validity_status`, `subject` (ringkasan aman per domain; `archived` untuk siswa/pegawai soft-deleted), `current_version`, `versions_count`, `version`, `updated_at`, `can {view, update, add_version, download}`. `meta {current_page, per_page, total, last_page}`; urutan `document_date desc, id desc`.
- **Bentuk detail** menambahkan `description`, `created_at`, `created_by {id,name}`, `updated_by {id,name}`, `latest_version`. Item versi: `id`, `version_number`, `original_name`, `mime_type`, `size_bytes`, `note`, `uploaded_by {id,name}`, `created_at`. **Tidak ada path/checksum** pada respons.
- **Create**: `category` whitelist; `title` 3–200; `document_number` opsional ≤100; `document_date` wajib `Y-m-d`; `valid_from`/`valid_until` opsional berpasangan dengan `valid_until >= valid_from`; `description` ≤1000; `subject_type` whitelist + `subject_id` both-or-none; `subject_id` harus ada untuk tipe tersebut (termasuk baris soft-deleted siswa/pegawai); `file` wajib PDF/JPG/JPEG/PNG ≤10 MB. Dokumen + versi 1 + file dibuat dalam transaksi; kegagalan membersihkan file.
- **Update**: field metadata `sometimes`; `expected_version` wajib; `subject_type`/`subject_id`/`file` `prohibited`; perubahan menaikkan `version` hanya bila ada perubahan; `409` mengembalikan `{message, errors, latest {id, version, current_version, updated_at}}` tanpa data sensitif.
- **Tambah versi**: `expected_version` wajib; nomor baru = `current_version + 1`; file ditulis sebelum baris versi dan dibuang bila penyimpanan/DB gagal; `current_version`/`version`/`updated_by` diperbarui dalam transaksi + `lockForUpdate`.
- **Validitas**: `none` bila `valid_until` null; `upcoming` bila `valid_from` > hari ini (WIB) dan menang atas status lain; `expired` bila `valid_until` < hari ini; `expiring_soon` bila ≤30 hari; selain itu `valid`. Filter `validity` mencerminkan presedensi dan mengecualikan `valid_from` masa depan untuk `expired`/`expiring_soon`/`valid`.
- **Download header**: `Content-Type` sesuai MIME tersimpan, `Content-Length`, `X-Document-Filename` (nama asli tersanitasi + ekstensi), `Access-Control-Expose-Headers: X-Document-Filename`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: sandbox; default-src 'none'`, `Cache-Control: private, no-store`; **tanpa `Content-Disposition`**. File hilang `404`.
- **Audit/privasi**: allowlist `ArchiveDocument` hanya `category`, `document_date`, `valid_from`, `valid_until`, `subject_type`, `subject_id`, `current_version`, `version`; judul/nomor/deskripsi/nama file/path/checksum tidak masuk audit. `ArchiveFileVersion` menolak update/delete dan tidak memakai `Loggable`.
- **UI**: `/public/archives` (menu **Archives**, guard `archives.view`) dengan daftar/filter, detail, versi, New/Edit/Add Version hanya bila `can.manage`, download, dan konflik `409` + **Reload Latest**; English, tanpa tombol dummy.
- **Batas**: tidak ada delete/restore/soft delete, preview inline, audit akses download, expiry task/pengingat, retensi/purge (**09b/DEC-13**), OCR/fulltext, atau perubahan dependency/service/`.env`.

Frontend 09a: `Archives.vue`, `router/index.js`, `AppMenu.vue`.

## Perubahan Subfase 09b (DEC-38) — retensi read-only, akses aman, audit akses, arsip/restore, dan pengingat masa berlaku

**6 route baru** di `/api/archives*`, sehingga total route arsip menjadi **15** (9 route 09a + 6 route 09b). Satu ability sensitif baru `archives.purge` didefinisikan di katalog/migrasi tetapi **tidak ditugaskan ke role mana pun** (`0` baris di `ability_role`); tidak ada role baru. Seluruh perubahan payload pada route 09a bersifat **additive** sehingga consumer 09a tetap kompatibel.

| Method | Endpoint | Izin | Perilaku |
|---|---|---|---|
| GET | `/api/archives/tasks/expiring` | `archives.view` | Tugas masa berlaku terhitung (WIB, ambang 30 hari): hanya dokumen `active` (`archived_at IS NULL`) berstatus `expired` atau `expiring_soon` yang diizinkan domainnya bagi aktor (`upcoming`/`valid`/`none` dikecualikan); menerima `limit` (1..50, default 10); mengembalikan `summary {expired_count, expiring_soon_count, total_attention_count, threshold_days, today}` dan `items[]` (urut `expired` dahulu lalu `valid_until asc, id asc`, dengan `days_remaining` bertanda) |
| GET | `/api/archives/retention/report` | `archives.manage` | Laporan retensi **100% read-only** per kategori config (`student_record`: permanen/`null`; `employee_record`/`letter`/`other`: 5 tahun; `leave`: 2 tahun; `school_document`: 10 tahun) + ringkasan `access_logs` (retensi 5 tahun), `totals`, `purge_enabled: false`, dan `can_purge: false`; tidak menghapus baris atau file apa pun |
| GET | `/api/archives/{archive}/versions/{version}/preview` | policy `preview` (= `download`: `archives.view` + ability domain subjek) | Stream inline dari disk privat `local`; MIME dari kolom `mime_type` tersimpan; `X-Document-Filename: preview-archive-{archive}-v{version_number}.{ext}`, `Access-Control-Expose-Headers: X-Document-Filename`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, `Content-Security-Policy: sandbox`, `Content-Length`; **tanpa `Content-Disposition`**, tanpa URL publik/signed URL, tanpa watermark; memverifikasi keberadaan file (`404`) + SHA-256 (`500`) **sebelum** menulis `archive_access_logs` (`action=preview`) secara fail-closed (`500` bila log gagal ditulis) |
| GET | `/api/archives/{archive}/access-logs` | `audit.view` + policy `view` (`archives.view` + domain) | Riwayat akses append-only terbaru dahulu (`created_at desc, id desc`); filter `action=download\|preview`, `per_page` (10/20/50/100, default 20), `page`; item memuat `{id, archive_document_id, archive_file_version_id, version_number, action, actor {id, name}, ip_address, user_agent, created_at}` tanpa judul/nomor/nama file/path/checksum; dokumen tersembunyi `404`, tanpa `audit.view` `403` |
| POST | `/api/archives/{archive}/archive` | policy `archive` (`archives.manage` + domain) | `reason` wajib 10–255 + `expected_version` wajib; mengarsipkan dokumen aktif (`archived_at`, `archive_reason`, `archived_by`, `version + 1`) di dalam transaksi + `lockForUpdate()`; dokumen sudah archived `422`; versi basi `409` dengan `latest {id, status, archived_at, version, current_version, updated_at}` |
| POST | `/api/archives/{archive}/restore` | policy `restore` (`archives.manage` + domain) | `reason` wajib 10–255 + `expected_version` wajib; memulihkan dokumen archived menjadi aktif (`archived_at=null`, `archive_reason=null`, `archived_by=null`, `version + 1`) di dalam transaksi + `lockForUpdate()`; dokumen sudah aktif `422`; versi basi `409` |

- **Urutan route**: `/tasks/expiring` dan `/retention/report` didaftarkan sebelum `/{archive}`; `{archive}` dan `{version}` memakai `whereNumber`.
- **Perluasan additive route 09a**:
  - `GET /api/archives/options`: menambah `statuses` (`active`, `archived`, `all`), `archive_reason {min_length: 10, max_length: 255}`, `retention {purge_enabled: false, access_log_retention_years: 5, categories}`, dan `can {manage, view_access_log, view_retention, purge: false}`.
  - `GET /api/archives`: menerima filter `status=active|archived|all` (default `active`).
  - Item ringkas & detail `ArchiveDocument`: menambah `status` (`active`/`archived`), `is_archived`, `archived_at`, dan memperluas `can {view, update, add_version, download, preview, archive, restore, view_access_log}`; detail menambahkan `archive_reason` dan `archived_by {id, name}`.
  - `PUT/PATCH /api/archives/{archive}` dan `POST /api/archives/{archive}/versions`: menolak dokumen berstatus `archived` dengan `422` (`errors.status`).
  - `GET /api/archives/{archive}/versions/{version}/download`: memverifikasi keberadaan file (`404`) + SHA-256 (`500`) terlebih dahulu, lalu menulis baris `archive_access_logs` (`action=download`) secara fail-closed (`500` bila log gagal ditulis, tanpa mengirimkan byte file). Pencatatan log akses pada `GET .../download` dan `GET .../preview` adalah pencatatan audit append-only yang tidak mengubah metadata/versi dokumen dan tidak menulis ke tabel `activities`.
- **Audit**: `ArchiveDocument` menambahkan `archived_at` ke allowlist `activities` (tanpa `archive_reason`); `ArchiveAccessLog` tidak memakai `Loggable` dan menolak update/delete; `ArchiveDocumentPolicy::delete` dan `forceDelete` tetap `false`.
- **CLI**: `php artisan app:archives-retention --dry-run` mencetak tabel retensi read-only; pemanggilan tanpa `--dry-run` ditolak (`INVALID`); scheduler tidak diubah.
- **UI**: `ArchiveExpiryWidget.vue` pada baris teratas `Dashboard.vue` (hanya untuk pemegang `archives.view`, menaut ke `/public/archives?validity=expired|expiring_soon`), serta perluasan `Archives.vue` (filter Status, dialog Preview PDF/gambar, panel Access History untuk `audit.view`, dialog Archive/Restore + `409` Reload Latest, dan dialog Retention Report untuk `archives.manage`).

Frontend 09b: `ArchiveExpiryWidget.vue`, `Dashboard.vue`, `Archives.vue`.

## Perubahan Subfase 10a (DEC-39 / DEC-10) — penugasan mengajar, jadwal mingguan, dan pengecualian kalender

**13 route baru** di `/api/teaching/*`. Mengaktifkan ability `teaching.view` dan `teaching.manage` (`admin-sistem` & `operator-sekolah` memperoleh `teaching.view` + `teaching.manage`; `kepala-sekolah`, `wakil-kesiswaan`, & `guru` memperoleh `teaching.view`). Guest `401`; akun nonaktif `403`; tanpa ability `403`; objek tidak ada `404`; validasi/bentrok/tahun terkunci/delete guard `422`; konflik versi basi (`expected_version`) `409` dengan `latest` snapshot.

| Method | Endpoint | Izin | Perilaku |
|---|---|---|---|
| GET | `/api/teaching/options` | `teaching.view` | Mengembalikan `active_period {school_year_id, semester_id, school_year, semester}`, `school_years[]` (beserta `is_locked`, `locked_at`, `start_date`, `end_date`), `semesters[]`, `school_rombels[]` (filter opsional `school_year_id`), `subjects[]`, `teachers[]` (hanya profil `Employee` aktif `Pendidik` dengan `exit_date IS NULL`), `days_of_week[]` (1..6), `period_presets[]` (1..12 dengan `start_time`/`end_time`), `exception_types[]` (`holiday`, `cancelled`, `rescheduled`), `max_period` (12), `max_hours_per_week` (24), dan `can {view, manage}` |
| GET | `/api/teaching/assignments` | `teaching.view` | Daftar berpaginasi `teaching_assignments` (filter `school_year_id`, `semester_id`, `school_rombel_id`, `subject_id`, `employee_id`, `status`, `search`, `per_page` 1..100 default 25, `page`); menyertakan `schedules_count`, `active_schedules_count`, dan ringkasan relasi (`school_year`, `semester`, `school_rombel`, `subject`, `teacher`) |
| POST | `/api/teaching/assignments` | `teaching.manage` | Membuat penugasan mengajar (`school_year_id`, `semester_id`, `school_rombel_id`, `subject_id`, `employee_id`, `hours_per_week` 1..24, `status` `active`\|`inactive`, `notes` opsional ≤500); menolak tahun ajaran terkunci (`422`), kelas beda tahun ajaran (`422`), pegawai non-`Pendidik`/keluar/diarsipkan (`422`), dan duplikat `(school_year_id, semester_id, school_rombel_id, subject_id)` (`422`); mengembalikan `201` |
| PUT/PATCH | `/api/teaching/assignments/{teachingAssignment}` | `teaching.manage` | Memperbarui `employee_id`, `hours_per_week`, `status`, dan/atau `notes` dengan `expected_version` wajib (`409` + `latest` bila basi); bila guru diubah atau status diaktifkan kembali (`inactive -> active`) ketika penugasan sudah memiliki slot jadwal aktif, seluruh slot jadwal aktif divalidasi ulang terhadap bentrok guru/kelas/ruangan (`422` bila bentrok) |
| DELETE | `/api/teaching/assignments/{teachingAssignment}` | `teaching.manage` | Menghapus penugasan mengajar bila tahun ajaran unlocked dan belum memiliki `teaching_schedules` (`422` bila masih memiliki slot jadwal) |
| GET | `/api/teaching/schedules` | `teaching.view` | Daftar berpaginasi `teaching_schedules` (filter `school_year_id`, `semester_id`, `school_rombel_id`, `employee_id`, `subject_id`, `day_of_week` 1..6, `room`, `status`, `search`, `per_page` 1..100 default 50, `page`); bila `matrix=1` dikirim, menyertakan `matrix` dikelompokkan per `day_of_week` (`1..6`) berurutan `period_start asc, start_time asc, id asc` |
| POST | `/api/teaching/schedules` | `teaching.manage` | Membuat slot jadwal mingguan (`teaching_assignment_id`, `day_of_week` 1..6, `period_start` 1..12, `period_end >= period_start`, `start_time` `HH:MM`, `end_time > start_time`, `room` opsional ≤80, `status` `active`\|`inactive`, `notes` opsional ≤255); menegakkan deteksi bentrok tiga dimensi (**guru**, **kelas**, **ruangan** `room_normalized`) pada tahun+semester+hari yang sama (`422` dengan pesan deskriptif); mengembalikan `201` |
| PUT/PATCH | `/api/teaching/schedules/{teachingSchedule}` | `teaching.manage` | Memperbarui slot jadwal mingguan dengan `expected_version` wajib (`409` + `latest` bila basi) dan mengecek ulang bentrok tiga dimensi (mengecualikan ID dirinya sendiri) termasuk saat mengaktifkan kembali slot `inactive -> active` (`422`) |
| DELETE | `/api/teaching/schedules/{teachingSchedule}` | `teaching.manage` | Menghapus slot jadwal bila tahun ajaran unlocked dan belum memiliki `teaching_schedule_exceptions` (`422` bila sudah memiliki riwayat exception) |
| GET | `/api/teaching/exceptions` | `teaching.view` | Daftar berpaginasi `teaching_schedule_exceptions` (filter `school_year_id`, `semester_id`, `exception_type`, `school_rombel_id`, `teaching_schedule_id`, `date_from`, `date_to`, `search`, `per_page` 1..100 default 25, `page`) |
| POST | `/api/teaching/exceptions` | `teaching.manage` | Mencatat pengecualian kalender (`holiday`, `cancelled`, atau `rescheduled`) dengan `reason` wajib ≤500; memvalidasi tanggal dalam rentang `school_years.start_date..end_date`, kesesuaian hari (`day_of_week`) untuk `cancelled`/`rescheduled`, blokir reschedule ke tanggal `holiday`, serta deteksi bentrok guru efektif/kelas/ruangan pada tanggal tujuan (`rescheduled_date`) terhadap jadwal reguler maupun reschedule lain (`422`); mengembalikan `201` |
| PUT/PATCH | `/api/teaching/exceptions/{teachingScheduleException}` | `teaching.manage` | Memperbarui pengecualian kalender dengan `expected_version` wajib (`409` + `latest` bila basi) dan validasi penuh |
| DELETE | `/api/teaching/exceptions/{teachingScheduleException}` | `teaching.manage` | Menghapus pengecualian kalender pada tahun ajaran unlocked |

- **Deteksi bentrok tiga dimensi (`D4`)**: dua slot berstatus `active` pada `(school_year_id, semester_id, day_of_week)` yang sama dianggap beririsan bila `(new.start_time < existing.end_time AND new.end_time > existing.start_time)` **atau** `(new.period_start <= existing.period_end AND new.period_end >= existing.period_start)`. Bentrok ditolak `422` apabila memiliki `employee_id` yang sama (`errors.teaching_assignment_id`), `school_rombel_id` yang sama (`errors.start_time`), atau `room_normalized` non-null yang sama (`errors.room`).
- **Audit/privasi (`D10`)**: `TeachingAssignment`, `TeachingSchedule`, dan `TeachingScheduleException` menggunakan allowlist `$auditableAttributes` pada `Loggable` yang mengecualikan `notes` dan `reason` dari tabel `activities`.
- **UI**: `/public/teaching/schedules` (alias `/teaching/schedules`, menu **Teaching → Assignments & Timetable**, guard `teaching.view`) dengan 3 tab (**Weekly Timetable** Matrix/List toggle, **Teaching Assignments**, **Schedule Exceptions**), preset jam pelajaran otomatis, banner error bentrok `422`, banner read-only tahun terkunci, dan dialog `409` **Reload Latest**.

Frontend 10a: `TeachingSchedulesView.vue`, `router/index.js`, `AppMenu.vue`.

## Perubahan Subfase 10b (DEC-40 / DEC-10) — sesi harian & jurnal pembelajaran

**5 route baru** di `/api/teaching/*` (total **18 route `/api/teaching/*`**), ditambah perluasan additive pada `GET /api/teaching/options`. Tanpa ability baru: menggunakan `teaching.view`, `teaching.manage`, dan tautan `employees.user_id` (`Pendidik` aktif).

| Method | Endpoint | Izin | Perilaku |
|---|---|---|---|
| GET | `/api/teaching/sessions` | `teaching.view` | Evaluasi deterministik sesi pembelajaran untuk satu tanggal (`date`, default `today_wib`) atau rentang tanggal (`date_from..date_to`, maks. 14 hari) pada `(school_year_id, semester_id)` dengan filter `school_rombel_id`, `subject_id`, `employee_id`, `status` (`all\|effective\|recorded\|missing\|excluded`), `scope` (`mine\|all`). Mengembalikan `data[]` (status `effective`, `holiday`, `cancelled`, `rescheduled_out`; `source_type` `regular\|rescheduled`; guru efektif termasuk `substitute_employee_id`; flag `can_record`, `can_update`, `is_within_grace_window`, `block_reason`, dan ringkasan `journal` bila sudah diisi), `summary {total_slots, effective_sessions, recorded_journals, missing_journals, excluded_slots}`, serta `meta` |
| GET | `/api/teaching/journals` | `teaching.view` | Daftar berpaginasi `teaching_journals` (`per_page` `10/20/50/100` default `20`, `page`) dengan filter `school_year_id`, `semester_id`, `school_rombel_id`, `subject_id`, `employee_id`, `date_from`, `date_to`, `method`, `source_type` (`regular\|rescheduled`), `scope` (`mine\|all`), dan `search` |
| GET | `/api/teaching/journals/{teachingJournal}` | `teaching.view` | Detail satu jurnal pembelajaran beserta snapshot jadwal/exception/guru dan metadata audit (`version`, `correction_reason`, `corrected_by_name`, `corrected_at`) |
| POST | `/api/teaching/journals` | `teaching.manage` atau (`teaching.view` + profil `Pendidik` aktif tertaut) | Mencatat jurnal pada sesi efektif `(teaching_schedule_id, session_date)`: wajib `topic` (3–200), `method` (whitelist `config/teaching.php`), opsional `summary` (≤2000), `notes` (≤1000), `actual_start_time`/`actual_end_time` (`HH:MM`, default mengikuti waktu sesi efektif). Menolak tanggal masa depan (`session_date > today_wib` → `422`), sesi non-efektif (`holiday`/`cancelled`/`rescheduled_out` → `422`), tahun ajaran terkunci (`422`), guru lain (`403`), dan pengisian mandiri lewat grace window 7 hari (`422`, kecuali `teaching.manage` dengan `correction_reason` 10–255 karakter). **Idempotent retry (`D3`):** bila baris `(teaching_schedule_id, session_date)` sudah ada dengan payload fungsional identik, mengembalikan **`200 OK`** (`idempotent_replay: true`) tanpa duplikasi baris; bila payload berbeda, mengembalikan **`409 Conflict`** + `latest`; bila baru, mengembalikan **`201 Created`** (`version = 1`) |
| PUT/PATCH | `/api/teaching/journals/{teachingJournal}` | `teaching.manage` atau (`teaching.view` + pemilik sesi `Pendidik` dalam grace window 7 hari) | Memperbarui jurnal dengan `expected_version` wajib (`409` + `latest` bila basi); menegakkan kepemilikan `own assignment` + grace window 7 hari bagi guru (`403`/`422`), serta mewajibkan `correction_reason` (10–255 karakter) bagi `teaching.manage` saat mengedit lewat 7 hari, mengedit jurnal guru lain, atau mengubah `teacher_employee_id` |

- **Perluasan additive `GET /api/teaching/options`**: menambahkan `can.create_journals`, `can.update_journals`, `self_employee {id, name, nuptk, primary_subject_id, primary_subject_name}`, `journal_methods[]` (8 metode), `journal_source_types[]` (`regular`, `rescheduled`), `today_wib`, dan `grace_deadline_date`.
- **Guard konsistensi dua arah (`D6`)**: `TeachingScheduleService` menolak penghapusan penugasan/jadwal yang sudah memiliki `teaching_journals` (`422`), menolak perubahan `teaching_assignment_id`/`day_of_week` pada jadwal berjurnal (`422`), dan menolak pembuatan/perubahan/penghapusan exception kalender (`holiday`, `cancelled`, `rescheduled`) yang membatalkan sesi yang sudah memiliki jurnal (`422`).
- **Audit/privasi (`D8`)**: `TeachingJournal` memakai allowlist `$auditableAttributes` pada `Loggable` yang mengecualikan `topic`, `summary`, `notes`, dan `correction_reason`.
- **UI**: `/public/teaching/journals` (alias `/teaching/journals`, menu **Teaching → Sessions & Journals**, guard `teaching.view`) dengan 2 tab (**Daily Sessions Worklist** dan **Recorded Journals History**) serta dialog Fill/Edit/View Journal + `409` **Reload Latest**.

Frontend 10b: `TeachingJournalsView.vue`, `router/index.js`, `AppMenu.vue`.

## Aktual

Resource di `/api`, dilindungi auth:sanctum: users, status-users, type-users, firstlogin; provinces/regencies/districts/villages; school-years/semesters/education-levels; genders/religions/blood-types/professions/family-status; education-schools/student-statuses/student-entries/student-majors/phase-statuses; students/school-levels/school-rombels/student-rombels; employees (alias deprecated: employee)/employee-statuses/employee-types/employments/marital-statuses; school-profile/subjects/activities.

`/api/user` mengambil actor. `/api/check-auth` mengembalikan authenticated + user atau null. Route auth web mencakup login/logout/register/forgot-password/reset-password dan email verification. Sejak 05c tersedia `/api/data-quality/students/*` dan `/api/data-quality/employees/*` (enam route). Sejak 06a tersedia `/api/academic/period`, `/api/school-years/{id}/lock`, `/api/school-years/{id}/unlock`, dan `/api/employees/teacher-options`. Sejak 06b tersedia enam route `/api/academic/progression/*` (options, roster, preview, commit, batches, batches/{id}). Sejak 06c tersedia empat route `/api/academic/mutations/*` (options, schools, recap, index), empat route `/api/students/{id}/mutations*` (timeline, in, out, cancel), dan dua route `/api/students/{id}/locked-corrections` (index, store). Sejak 07a tersedia sembilan route `/api/letters/incoming*` (options, list, detail, create, update, void, upload, download, remove lampiran). Sejak 07b tersedia tiga route global `/api/letters/dispositions*` (options, recipients, list), tiga route disposisi di bawah surat masuk (create, update, cancel), dan 13 route `/api/letters/outgoing*` (options, list, detail, create, update, submit, approve, reject, issue, cancel, upload/download/remove lampiran). Sejak 07c tersedia empat route `/api/letters/settings*` (show, header, signatory, asset), tiga route `/api/letters/outgoing/subjects/*` (students, student-mutations, employees), dan dua route dokumen `/api/letters/outgoing/{id}/document*` (download, preview) — total **37 route surat**. Sejak 08a tersedia 13 route `/api/leaves*` (options, index, mine, dua subject picker, detail, create, update, submit, cancel, upload/download/remove lampiran); sejak 08b tersedia tiga route tambahan (`approve`, `reject`, `recap`) — total **16 route izin**. Sejak 09a tersedia sembilan route `/api/archives*` (options, picker subjek, list, create, detail, update, versions, add version, download); sejak 09b tersedia enam route tambahan (`tasks/expiring`, `retention/report`, `versions/{version}/preview`, `access-logs`, `archive`, `restore`) — total **15 route arsip**. Sejak 10a tersedia 13 route `/api/teaching/*` (`options`, `assignments` index/store/update/destroy, `schedules` index/store/update/destroy, dan `exceptions` index/store/update/destroy); sejak 10b tersedia 5 route tambahan (`sessions` index dan `journals` index/show/store/update) — total **18 route `/api/teaching/*`**. Daftar route bukan bukti semua handler lengkap.
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
