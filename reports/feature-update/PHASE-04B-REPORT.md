# Laporan Subfase 04b — CRUD Pegawai, Relasi Akun, Ekspor, dan Menu Aktif

Tanggal WIB: 2026-10-29. Branch: `main`.
Baseline implementasi 04a: `ecb2ea3` (fix `c7379a5`). Baseline dokumentasi keputusan: `fff5249`.
Keputusan pemilik: **DEC-24** (APPROVED 2026-10-29 WIB; D1–D8).
Status implementasi: **IMPLEMENTED & DIVERIFIKASI OTOMATIS (AGEN, 2026-10-29)**.
Status penerimaan manual pemilik: **PENDING** — checklist `PHASE-04B-MANUAL-REVIEW.md` belum dijalankan pemilik.
Laporan rencana: `reports/feature-update/PHASE-04B-PLAN.md`.

> **Pernyataan Batas Scope:** Laporan ini mencatat implementasi dan verifikasi otomatis Subfase 04b pada lingkungan uji terisolasi (SQLite in-memory). Implementasi **tidak** diterapkan ke UAT/produksi: migration `2029_07_26_000001` belum dijalankan pada database mana pun selain database uji disposable. Browser UAT dan integrasi MariaDB **NOT RUN** pada sesi ini (lihat Bagian 5). Status teknis ini bukan penerimaan manual pemilik, bukan security clearance, dan bukan izin deploy.

---

## 1. Ringkasan Implementasi

1. **Profil pegawai memiliki identitas sendiri (D1/D2)**
   - Migration additive `2029_07_26_000001` menambahkan `employees.name` (`string(100)`, nullable di schema), `employees.nik` (`string(16)`, nullable, unique **unconditional** termasuk baris terarsip), dan `employees.archive_reason` (`string(255)`, nullable).
   - Index additive pada `name`, `employee_type_id`, `employee_status_id`.
   - Backfill nama saat migration hanya dari `users.name` melalui `user_id`; baris yang tidak dapat diresolusi dibiarkan `NULL`.
   - Command `php artisan app:employee-name-backfill [--dry-run]` (idempotent, atomik) melaporkan hitungan agregat tanpa nama/NIK. Sumber: `user_id`, lalu fallback NIK unik hanya untuk baris legacy yang masih eligible.
   - `down()` migration menolak rollback bila ada nama yang tidak dapat direkonstruksi dari akun, NIK, atau alasan arsip.
2. **Daftar server-side dan privasi payload (D6)**
   - `GET /api/employees` memakai `EmployeeDirectory`: paginasi `10/20/50/100` (default 20), pencarian `name`/NUPTK, filter tipe/status/kepegawaian/mapel, sort allowlist `id|name|tmt_employee` dengan tie-break `id`.
   - Payload daftar **tanpa PII**: tanpa NIK, NPWP, KK, ibu kandung, pasangan, kontak/alamat, nilai ijazah, dan tanpa data akun.
   - `only_archived=1` hanya untuk `employees.manage`.
   - Detail (`GET /api/employees/{id}`) menambahkan field sensitif + `education` **hanya** untuk pemegang `employees.manage`; pemegang `employees.view` menerima data operasional.
3. **CRUD dengan konkurensi optimistik**
   - `store` menetapkan `version=1`, mengabaikan `user_id`/`user_nik`/`version` dari klien, dan mengembalikan `201`.
   - `update` memakai row lock + `version`; versi usang → `409` dengan `current_version`; baris terarsip → `422`.
   - Validasi `EmployeeProfileRules`: `name` wajib; NIK 16 digit unik; `subject_id` wajib untuk Pendidik dan prohibited untuk Tenaga Kependidikan (D4); normalisasi string kosong → `NULL`.
   - Audit `Loggable` tetap allowlist tanpa PII; atribut baru (`name`, `nik`, `archive_reason`) tidak masuk allowlist.
4. **Arsip administratif dan restore (D7)**
   - `DELETE /api/employees/{id}` mewajibkan `version` + `archive_reason` (422 bila kosong), menyimpan alasan, menaikkan versi, dan **tidak** mengubah `exit_date`.
   - `POST /api/employees/{id}/restore` hanya untuk baris terarsip, memakai `version`, dan mengembalikan `422` bila bentrok NIK; `archive_reason` dikosongkan saat restore.
5. **Taut/lepas akun terkendali (D5)**
   - `GET /api/employees/account-candidates` mengembalikan maks. 20 akun aktif, belum terhapus, dan belum tertaut profil lain (dengan pencarian).
   - `POST .../link-account` hanya menerima akun kandidat, mengisi `user_id` + `user_id_resolved_at`, dan tidak mengubah role/abilities. Re-link atau akun nonaktif/tertaut profil lain → `422`.
   - `POST .../unlink-account` mengosongkan `user_id`, mengisi `account_unlinked_at`, dan tidak mengaktifkan kembali fallback NIK legacy.
6. **Ekspor CSV terfilter (D8)**
   - `GET /api/employees/export` menegakkan `employees.view` + `employees.export`; mengikuti filter daftar; kolom eksplisit `Name, Employee Type, Employee Status, Employment, Subject, NUPTK, Gender, TMT, Exit Date, Archived At`; BOM UTF-8, `nosniff`, netralisasi formula; hasil > `employees.export_max_rows` (default 10.000) → `422`.
7. **Frontend Teachers/Employees (D3)**
   - Route `/public/teachers` (`Pendidik`) dan `/public/employees` (`Tenaga Kependidikan`) memakai satu komponen direktori dengan tipe tetap per route; menu sidebar diarahkan ke route nyata dengan `requires: employees.view`.
   - `EmployeeDirectory.vue`: DataTable lazy, debounce 300 ms, filter status/kepegawaian/mapel, sort/paginasi server, guard respons usang (`requestId`), pemulihan halaman kosong, Export mengikuti filter.
   - Dialog modular: form create/edit (422 inline, 409 + Reload Latest, konfirmasi perubahan belum disimpan), detail, arsip (alasan wajib), dan taut/lepas akun.
   - Tombol mengikuti izin: `view`/`manage`/`export`; daftar arsip hanya `manage`.

---

## 2. Berkas yang Berubah / Dibuat

| Berkas | Status | Perubahan |
|---|---|---|
| `laravel-api/database/migrations/2029_07_26_000001_add_profile_fields_to_employees.php` | Baru | `name`, `nik` unique, `archive_reason`, index; backfill nama dari akun; `down()` fail-closed. |
| `laravel-api/app/Models/Employee.php` | Modifikasi | Fillable kolom baru; koreksi `district()` (menghapus `withTrashed()` pada model tanpa SoftDeletes). |
| `laravel-api/app/Http/Requests/StoreEmployeeRequest.php` | Modifikasi | `authorize` `employees.manage`, normalisasi blank→NULL, rules profil. |
| `laravel-api/app/Http/Requests/UpdateEmployeeRequest.php` | Modifikasi | Sama + `version` wajib dan unique NIK mengabaikan baris sendiri. |
| `laravel-api/app/Http/Requests/Concerns/EmployeeProfileRules.php` | Baru | Rules bersama create/update; aturan mapel Pendidik vs Tenaga Kependidikan (D4). |
| `laravel-api/app/Http/Controllers/EmployeeController.php` | Modifikasi | index/show/store/update/destroy/restore + kandidat/link/unlink akun; row lock, 409, 422, gating PII. |
| `laravel-api/app/Http/Controllers/EmployeeExportController.php` | Baru | Ekspor CSV terfilter, izin ganda, batas baris 422, sanitasi sel. |
| `laravel-api/app/Http/Resources/EmployeeResource.php` | Baru | Payload daftar minimal; field sensitif + `education` hanya untuk `employees.manage`. |
| `laravel-api/app/Support/EmployeeDirectory.php` | Baru | Query dasar daftar/arsip/ekspor, allowlist sort & filter. |
| `laravel-api/app/Services/EmployeeNameBackfillService.php` | Baru | Backfill nama idempotent, atomik, tanpa PII. |
| `laravel-api/app/Console/Commands/EmployeeNameBackfillCommand.php` | Baru | `app:employee-name-backfill [--dry-run]` dengan keluaran agregat. |
| `laravel-api/config/employees.php` | Baru | `export_max_rows` (default 10.000). |
| `laravel-api/database/factories/EmployeeFactory.php` | Modifikasi | Default `name`, `nik=null`, tipe `Pendidik`, status `Pegawai Tetap Yayasan`, mapel `Umum`. |
| `laravel-api/routes/api.php` | Modifikasi | Route `/employees` (index/show `employees.view`; mutasi `employees.manage`), export, kandidat, restore, link/unlink; alias `/employee` dipertahankan. |
| `laravel-api/tests/Feature/Employee/EmployeeProfileFieldsTest.php` | Baru | 8 tes schema/backfill/rollback 04b. |
| `laravel-api/tests/Feature/Employee/EmployeeCrudTest.php` | Baru | 17 tes CRUD, konkurensi, arsip/restore, audit. |
| `laravel-api/tests/Feature/Employee/EmployeeAccountAndExportTest.php` | Baru | 10 tes otorisasi akun/ekspor, kandidat, link/unlink, CSV. |
| `vue-app/src/views/pages/public/EmployeeDirectory.vue` | Baru | Direktori bersama Teachers/Employees. |
| `vue-app/src/views/pages/public/EmployeeFormDialog.vue` | Baru | Form create/edit dengan konflik & validasi inline. |
| `vue-app/src/views/pages/public/EmployeeDetailDialog.vue` | Baru | Detail + aksi sesuai izin. |
| `vue-app/src/views/pages/public/EmployeeArchiveDialog.vue` | Baru | Arsip wajib alasan, `version` dikirim. |
| `vue-app/src/views/pages/public/EmployeeAccountLinkDialog.vue` | Baru | Pencarian kandidat, link/unlink. |
| `vue-app/src/views/pages/public/employeeDirectoryParams.mjs` | Baru | Helper params daftar/ekspor, resolusi tipe, normalisasi payload, pesan validasi. |
| `vue-app/tests/employee-directory-params.test.mjs` | Baru | 7 unit test (`node --test`). |
| `vue-app/src/router/index.js` | Modifikasi | Route `/public/teachers` dan `/public/employees` dengan `requiresAbility: employees.view`. |
| `vue-app/src/layout/AppMenu.vue` | Modifikasi | Menu Teachers/Employees aktif + `requires: employees.view`. |

---

## 3. Hasil Pengujian Otomatis (Aktual 2026-10-29)

### 3.1 Backend — suite Employee
- Perintah: `C:\xampp\php82-operational\php.exe artisan test tests/Feature/Employee`
- Hasil: **56 passed, 6 skipped (331 assertions)**, durasi 20,13 s, PHP 8.2.35 ZTS.
- Rincian file: `EmployeeSchemaAndAccountRelationTest` 21, `EmployeeCrudTest` 17, `EmployeeAccountAndExportTest` 10, `EmployeeProfileFieldsTest` 8; 6 skip adalah `MariaDbEmployeeIntegrationTest` (tanpa kredensial MariaDB disposable).

### 3.2 Backend — suite penuh (regresi)
- Perintah: `C:\xampp\php82-operational\php.exe artisan test`
- Hasil: **293 passed, 21 skipped (1448 assertions)**, durasi 215,82 s.
- 21 skip berasal dari kelas integrasi MariaDB (Backup/Employee/RombelConcurrency/Student) yang memerlukan MariaDB dan kredensial; MariaDB tidak berjalan pada sesi ini dan service tidak diubah.

### 3.3 Cakupan perilaku yang diuji
- Otorisasi: guest `401`; tanpa `employees.view`/`employees.manage` `403`; `only_archived` dan kandidat/link/unlink/restore hanya `manage`; ekspor butuh `employees.export` (peran `wakil-kesiswaan` → `403`).
- Daftar: paginasi/default, `per_page` tidak sah `422`, filter, sort, payload tanpa PII.
- CRUD: `201`/`200`, versi naik, stale `409` + `current_version`, baris terarsip `422`, decoy (`user_id`, `version`) diabaikan, audit allowlist tanpa nama/NIK.
- Arsip/restore: tanpa alasan `422`; `exit_date` utuh; stale `409`; restore mengosongkan alasan; bentrok NIK `422`.
- Akun: kandidat mengecualikan akun nonaktif/terhapus/tertaut/terarsip; link/unlink stale `409`; re-link `422`; unlink mematikan fallback NIK.
- Ekspor: header & kolom eksplisit, BOM, `nosniff`, filter, tanpa NIK/NPWP, batas baris `422`, sanitasi formula.
- Schema 04b: kolom/index, nullability, unique NIK termasuk baris terarsip, backfill idempoten, dry-run tanpa PII, rollback fail-closed.

### 3.4 Frontend
- `node --test tests/employee-directory-params.test.mjs` → **7 passed, 0 failed**.
- `npm run build` → **PASS** (Vite 6, selesai 24,41 s; hanya peringatan ukuran chunk > 500 kB pada chunk utama — kondisi baseline).
- `npx eslint --quiet` pada 8 berkas yang dibuat/diubah → **exit 0, 0 error**.

### 3.5 PHP style
- `pint --test` pada 17 berkas PHP yang dibuat/diubah → **PASS**.

---

## 4. Keputusan Teknis yang Diimplementasikan (DEC-24)

| ID | Keputusan | Implementasi |
|---|---|---|
| D1 | `employees.name` additif + backfill | Migration `name` nullable + backfill via `user_id`; service/command; `name` wajib di API |
| D2 | NIK pribadi nullable 16 digit, unik | Kolom `nik` string(16) unique unconditional; validasi `digits:16`; konflik restore `422` |
| D3 | Dua route, satu komponen | `/public/teachers` (Pendidik) dan `/public/employees` (Tenaga Kependidikan) |
| D4 | Mapel per jenis pegawai | Wajib Pendidik, prohibited Tenaga Kependidikan |
| D5 | Link/unlink akun terkendali | Kandidat akun aktif; tanpa buat akun/ubah role; timestamp link/unlink |
| D6 | Privasi PII | Daftar tanpa PII; detail sensitif hanya `employees.manage`; ekspor tanpa PII |
| D7 | Arsip beralasan + restore | `archive_reason` wajib; `exit_date` tidak diubah; restore berversi |
| D8 | Ekspor masuk 04b | `GET /api/employees/export`, izin ganda, 10 kolom, batas 10.000 → `422` |

---

## 5. NOT RUN dan Keterbatasan

1. **MariaDB disposable 04b — NOT RUN.** MariaDB tidak berjalan; service tidak dinyalakan sesuai batas sesi. Verifikasi DDL/unique/row-lock pada driver MySQL belum dijalankan. Kelas `MariaDbEmployeeIntegrationTest` (04a) ikut ter-skip.
2. **Browser UAT — NOT RUN.** Migration belum diterapkan ke database UAT dan belum ada fixture pegawai sintetis di UAT; keduanya memerlukan otorisasi terpisah. Golden path UI belum diuji di browser.
3. **Linux/macOS — NOT RUN.** Hanya Windows + PHP 8.2.35 ZTS.
4. **Migration UAT/produksi — TIDAK DITERAPKAN.** Hanya dijalankan otomatis pada SQLite in-memory oleh `RefreshDatabase` di suite tes.

Checklist pengganti untuk pemilik: `reports/feature-update/PHASE-04B-MANUAL-REVIEW.md`.

---

## 6. Risiko dan Catatan Terbuka

| Risiko | Status / mitigasi |
|---|---|
| Baris legacy dengan nama tak teresolusi tetap `NULL` | Ditampilkan sebagai "name unresolved" dan dilaporkan agregat oleh command; operator melengkapi manual |
| Alias `/employee` deprecated | Dipertahankan dan diuji; penghapusan menunggu konfirmasi consumer |
| UI belum diuji browser | NOT RUN; checklist manual disediakan; jangan anggap UI tervalidasi sebelum review pemilik |
| MariaDB row-lock 04b belum diuji | NOT RUN; pola sama dengan `StudentController` yang telah diuji pada 03d, tetapi bukan bukti untuk 04b |
| Batas baris ekspor | Default 10.000; dapat diubah via `EMPLOYEES_EXPORT_MAX_ROWS`; perilaku 422 diuji |
| Advisory dependency & lifecycle Laravel 10/PHP 8.2 | **OPEN**, terpisah dari 04b; tidak ada perubahan dependency/lockfile |

---

## 7. Kesimpulan Teknis

Backend dan frontend Subfase 04b selesai dan lulus seluruh pemeriksaan otomatis yang tersedia pada sesi ini (suite Employee 56/331, suite penuh 293/1448, unit frontend 7/7, build, Pint, ESLint tanpa error). Tidak ada migration yang diterapkan ke UAT/produksi dan tidak ada merge/deploy. Status berikutnya: review manual pemilik melalui `PHASE-04B-MANUAL-REVIEW.md`.
