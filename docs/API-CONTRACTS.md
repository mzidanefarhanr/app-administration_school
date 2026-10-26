# Kontrak API

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
- **Penyelarasan Izin Route `/employee`**: Route singular baseline `/employee` diselaraskan izinnya ke kemampuan bernama `can:employees.view` (index, show) dan `can:employees.manage` (store, update, destroy). Endpoint CRUD lengkap, normalisasi route ke `/employees`, dan transisi consumer berada pada Subfase 04b.
- **Model `Employee`**: Relasi utama `user()` via `user_id` (bigint unsigned nullable, unique, nullOnDelete). Relasi legacy `legacyUser()` via `user_nik`. Resolusi aman `resolveUser()` yang memprioritaskan `user_id`, menolak fallback bila akun sudah dilepas/diresolusi, dan hanya mengizinkan fallback NIK pada baris legacy yang belum pernah dimigrasikan. Relasi `religion()` diperbaiki dengan alias kompatibilitas `relogion()`.
- **Privasi Audit Personalia**: Allowlist `$auditableAttributes` diterapkan ketat pada model `Employee`; mengecualikan nama ibu kandung, pasangan, nomor KK, NPWP, dan nilai/IPK ijazah dari pencatatan log `activities`.
- **Layanan Pemadanan**: `EmployeeBackfillService` menyelesaikan relasi `user_id` secara idempotent dan transaksional tanpa mencetak NIK, nama, atau data pribadi ke log/laporan.

## Aktual
Resource di `/api`, dilindungi auth:sanctum: users, status-users, type-users, firstlogin; provinces/regencies/districts/villages; school-years/semesters/education-levels; genders/religions/blood-types/professions/family-status; education-schools/student-statuses/student-entries/student-majors/phase-statuses; students/school-levels/school-rombels/student-rombels; employee/employee-statuses/employee-types/employments/marital-statuses; school-profile/subjects/activities.
`/api/user` mengambil actor. `/api/check-auth` mengembalikan authenticated + user atau null. Route auth web mencakup login/logout/register/forgot-password/reset-password dan email verification. Daftar route bukan bukti semua handler lengkap.
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
