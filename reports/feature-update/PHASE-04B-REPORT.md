# Laporan Subfase 04b — CRUD Pegawai, Relasi Akun, Ekspor, dan Menu Aktif

Tanggal WIB: 2026-10-29. Branch: `main`.
Baseline implementasi 04a: `ecb2ea3` (fix `c7379a5`). Baseline dokumentasi keputusan: `fff5249`.
Keputusan pemilik: **DEC-24** (APPROVED 2026-10-29 WIB; D1–D8).
Status implementasi: **IMPLEMENTED with scoped verification (2026-10-29)**; English presentation, teacher/non-teaching separation and synthetic UAT data delivered.
Status penerimaan manual pemilik: **APPROVED** — checklist #1–#18 approved by the owner's statement on 2026-10-30 WIB (see `PHASE-04B-MANUAL-REVIEW.md`). The agent did not rerun the checklist.
Laporan rencana: `reports/feature-update/PHASE-04B-PLAN.md`.

> **Historical initial 04b evidence (2026-10-29):** Sections 1–7 record the initial implementation checkpoint: SQLite in-memory automated tests, no UAT migration, browser UAT NOT RUN, and MariaDB integration NOT RUN. The correction addendum below supersedes those runtime statements for the new isolated SQLite UAT only. It does not turn earlier NOT RUN evidence into PASS or imply owner acceptance, production deployment, or MariaDB verification.

## 0. Correction addendum — 2026-10-29 (English/UAT checkpoint)

### Scope and baseline

- Current baseline: `main` at `e281419` (corrections `dc5e031`, documentation/acceptance `e281419`), pushed to `origin/main`. Initial implementation `e1de4f4` and documentation `b93f954` remain historical evidence, not the tested SHA of these corrections.
- Latest owner instructions: full-English application presentation; **Teachers = Guru only**, canonical `Pendidik`; **Employees = non-teaching staff**, canonical `Tenaga Kependidikan`; at least 100 synthetic records for each empty new UAT feature. DEC-24 authorized implementation; the separate manual acceptance is recorded below.
- **Owner acceptance (2026-10-30 WIB):** “Pengujian nomor 1-18 sudah berhasil dan saya approved.” All #1–#18 statuses are APPROVED; the statement is the sole acceptance evidence and the agent did not rerun those scenarios. Acceptance covers the 04b implementation on the prepared isolated SQLite UAT, not production/legacy migration, deployment, merge, force-push, dependency changes or closure of open security risks.
- This checkpoint records implemented corrections, isolated UAT preparation and actual scoped test/browser/HTTP evidence below. Corrections are committed as `dc5e031`; documentation and owner acceptance are in `e281419`, pushed to `origin/main` under the owner's post-approval authorization. No deployment, merge or migration was performed. Owner artefacts `console.txt` and `vue-app/test-results/` remain untracked and untouched.
- Correction paths include new `laravel-api/resources/reference-labels.json`, `app/Support/ReferenceLabels.php`, `scripts/phase04b-uat.php`, `tests/Feature/Employee/EmployeeUatSeederTest.php`, `tests/Unit/{ReferenceLabelsTest.php,Phase04bUatScriptTest.php}`, `vue-app/src/utils/referenceLabels.mjs` and `vue-app/tests/reference-labels.test.mjs`; modified Laravel controllers/requests and synthetic seeders, Vue employee/Student/Profile/master/reference views, layout/navigation/cache helpers, `.gitignore` and affected contracts/reports. Laravel paths after the first item are relative to `laravel-api`.

### Dedicated UAT and safety boundary

- SPA: `http://127.0.0.1:14022`; API: `http://127.0.0.1:18022`. This is a **new SQLite UAT**, not the older 14021/18021 environment.
- Runtime: `D:/xampp-htdocs/app-administration_school/.uat-phase04b/`; exact database: `.uat-phase04b/database.sqlite`. Private `.env`, storage and session files are separate from the main API environment/storage; cookie: `school_phase04b_session`. Runtime and local `UAT-ACCOUNTS.local.md` are ignored; no password literal belongs in reports or Git.
- Launcher: `laravel-api/scripts/phase04b-uat.php` supports `init`, `prepare`, `status`. `init` refuses to overwrite an existing runtime. `prepare` uses **normal additive migrations**, not `migrate:fresh`, reset or restore; existing edited/archived fixtures are preserved on repeat preparation. `status` reports the guard and current counts.
- Fail-closed guard requires the exact SQLite database path and dedicated effective configuration; rejects database URLs, cached application configuration/routes, and conflicting process environment overrides. Never substitute root `artisan --env=uat` commands: the old profile targets an excluded database.
- No MariaDB target, legacy/main environment, existing owner service, dependency, grant, or operational data was changed/accessed in this correction scope. MariaDB is available, but excluded targets were not accessed. **04b MariaDB DDL/unique/InnoDB row-lock evidence remains NOT RUN**; SQLite browser checks cannot establish it.

### Fixtures and English presentation

- Baseline: **100 `Synthetic Teacher ...` profiles**, all with subjects, and **100 `Synthetic Staff ...` profiles**, all without subjects, spanning **15 non-teaching jobs**. Browser-created records add one of each: current totals **101 teachers, 101 staff, 103 students, 100 synthetic schools**. Schools use the string NPSN `04` namespace. Do not mistake current totals for the original 100-per-type seed baseline.
- Preparation/seeding is idempotent and retains edited/archived rows. Only the initial **five synthetic UAT accounts** are created when the account table is empty; employee/school fixtures do not create additional login accounts or overwrite existing credentials.
- Classification uses **only `employee_type_id`**, not job titles. Canonical employment/job references, including head-of-school positions, are retained; no job-title restriction is introduced. Teachers use fixed `Pendidik` type with required Subject; non-teaching Employees use fixed `Tenaga Kependidikan` type with prohibited Subject and no Subject UI.
- A shared **17-domain / 243-label JSON catalog** supplies English reference presentation. Pure frontend `display_name` enrichment preserves canonical `name` values and IDs; proper names and user-entered data are not translated. Navigation, Profile, validation, print labels and CSV reference values are English. This is presentation, not a database/reference-name migration.
- Password fields are removed **before** frontend cache enrichment. A route-view key prevents stale rows/dialog state from carrying between Teachers and Employees.
- Current employee CSV has exactly 10 headers: `Name, Employee Type, Employee Status, Employment, Subject, NUPTK, Gender, Employment Start Date (TMT), Exit Date, Archived At`. The initial header `TMT` is historical; the current label is `Employment Start Date (TMT)`.

### Contract/privacy corrections

- Earlier “zero PII” / “no account data” wording was inaccurate: list and export contain **name, NUPTK and gender**. Lists also expose `account: {linked, user_id}` linkage metadata. They omit sensitive NIK/contact/family/education data; sensitive fields and expanded account identity remain restricted to **manage detail**. Export permission controls the download, not copying authorized operational data.
- Unresolved legacy list names remain **`null` / blank**, not the literal `name unresolved`; backfill reports aggregate counts.
- `GET /api/employees/{id}` now resolves archived rows with `withTrashed()`. Archived detail requires **both `employees.view` and `employees.manage`**; a view-only actor receives **403**. Manager archived detail opening was checked in the browser; this read does not restore/mutate the record.

### Actual correction evidence (separate, overlapping runs)

| Check | Actual result | Scope/timing boundary |
|---|---|---|
| Earlier main explicit SQLite run | **98 tests / 1777 assertions PASS** | Employee + SeederIsolation + ReferenceLabels; before the two latest fixes, not a final-state/full-suite claim |
| Subsequent `EmployeeCrudTest` | **17 tests / 117 assertions PASS** | Focused archived-detail regression run |
| Subsequent `EmployeeUatSeederTest` | **5 tests / 219 assertions PASS** | Focused seeder regression run |
| UAT launcher tests | **3 tests / 372 assertions PASS** | Dedicated runtime/isolation preparation checks |
| Backend English-translation run | **119 tests / 1223 assertions PASS** | Separate explicit SQLite files including access, student export and rombel messages; not the full backend suite |
| Latest explicit SQLite run | **96 tests / 1738 assertions PASS, 34.77 s** | Seven files listed below; current employee CRUD/schema/account/export/seeder plus reference labels and launcher; no MariaDB files or full-suite claim |
| Final frontend Node tests | **42 tests PASS, 0 failed** | Includes credential-cache regression; not owner/browser checklist acceptance |
| Final production build | **PASS, 18.77 s** | `npm run build` after final implementation updates; earlier 18.28 s PASS remains historical; baseline Browserslist/chunk-size warnings remain, dependencies unchanged |
| Targeted ESLint | **0 errors** | Checked changed frontend scopes, including final layout/role labels/dialogs/Student/Profile/cache/test updates; not repository-wide warning-free evidence |
| Pint | **PASS** | Checked changed PHP scopes, including current employee controller/seeder/CRUD/seeder tests and launcher plus translation-agent scope; not repository-wide evidence |
| Repeat UAT preparation/status | **PASS** | Normal migration preparation repeated without count changes: 101 teachers, 101 staff, 103 students, 100 schools; no credential reset |
| Git whitespace/ignore checks | **PASS** | `git diff --check`; private runtime/environment/database/password/local account note ignored; no staging/commit/push |

Latest backend command, from `laravel-api`, with guarded SQLite `:memory:` test configuration and isolated test storage:

```bash
"C:/xampp/php82-operational/php.exe" artisan test \
  tests/Feature/Employee/EmployeeCrudTest.php \
  tests/Feature/Employee/EmployeeProfileFieldsTest.php \
  tests/Feature/Employee/EmployeeAccountAndExportTest.php \
  tests/Feature/Employee/EmployeeSchemaAndAccountRelationTest.php \
  tests/Feature/Employee/EmployeeUatSeederTest.php \
  tests/Unit/ReferenceLabelsTest.php \
  tests/Unit/Phase04bUatScriptTest.php
```

Final frontend commands, from `vue-app`: `node --test tests/*.test.mjs` and `npm run build`. Style checks used non-mutating `eslint --quiet` and `pint --test` against targeted files, not the baseline auto-fixing lint script.

Do **not** aggregate overlapping runs or add these totals to the initial 56/293-test evidence. The latest run does not rerun every earlier translation/SeederIsolation file or prove all repository behavior. Initial automated counts and browser NOT RUN remain dated history in Sections 3–5.

### Browser evidence — agent, not owner acceptance

Method: **browser DOM interactions with authenticated API readback**, on the new SQLite UAT as `uat.operator`:

- Teachers: 100 baseline profiles, all with Subject. Staff: 100 baseline profiles, all without Subject, across 15 jobs. Teachers → Employees → Teachers showed correct fixed types/titles after the route-view key fix, without stale opposite-type rows/dialogs.
- Actual Guru create: **201, version 1**; edit persisted **version 2**. An authenticated concurrent API save produced **version 3**; stale UI save returned **409**, preserved typed input, and **Reload Latest** loaded the saved values. This was not a two-browser-tab test.
- Blank archive reason produced English validation; archive succeeded at **version 4**, with `exit_date = null` unchanged. Manager opened archived detail. Restore succeeded at **version 5**.
- Actual non-teaching UI create: **201, version 1**, fixed staff type and no Subject UI.
- Actual teacher account link to existing synthetic `uat.waka`: **version 6, linked true**; UI unlink: **version 7, linked false**. Role/ability readback remained unchanged; no new account or password update.
- Authenticated browser fetch of CSV with each type plus `Synthetic` search: **200, 100 baseline rows per type**, 10 English headers/reference values, no cross-type rows. This is authenticated response readback, not a claim that the Download button/file save was tested.
- Mobile staff page/form at **390×844**: document width 390 with no horizontal page overflow; table horizontal content remained contained (scroll width 1077). Dialog width approximately 374.39, within viewport; content scrolled to the Create button and Cancel worked, Subject stayed hidden. **DOM dimensions/scroll interaction only**; screenshot capture attempts failed, no screenshot or pixel-based visual evidence.
- Students showed **103** active records with English labels and an opened Student Detail. Education Schools showed **100** synthetic rows with English level/public/private labels while names/NPSNs stayed unchanged. Profile and role panel labels were English; blank Save produced English password-field validation without an account mutation. Profile confirmation and print-preview were not verified.
- Latest checked browser console contained no errors/warnings; observed current reference/list/detail/export requests succeeded. Earlier intentional 409/422 validation responses are separate expected evidence.

### Separate HTTP permission evidence

A local ignored Node script used synthetic cookie/CSRF sessions against only `127.0.0.1:18022`, with the new SPA origin. Login/list/detail/logout and endpoint permissions passed for **`uat.waka`, `uat.kepsek`, `uat.operator`**, repeated after link/unlink:

- Vice Principal: operational detail allowed, sensitive detail omitted; export/candidates/management/archived detail forbidden (403).
- Principal: operational detail/export allowed; sensitive detail omitted; candidates/management/archived detail forbidden (403).
- Operator: sensitive detail/export/candidates/archived detail allowed. Role abilities stayed unchanged after linking/unlinking.

This is **HTTP evidence, not other-role browser UI logins**. Inactive-account login was not rerun in this correction checkpoint.

**Agent checks still NOT RUN:** other-role browser UI automation, two-tab stale/link-conflict scenarios, print-preview/Profile confirmation, screenshot-based visual checks, Linux/macOS and 04b MariaDB DDL/InnoDB tests. These remain agent gaps even though the owner separately approved #1–#18 from their own review on 2026-10-30 WIB. No production migration/deployment was performed or is authorized.

---

## 1. Ringkasan Implementasi (initial 04b checkpoint, 2026-10-29)

1. **Profil pegawai memiliki identitas sendiri (D1/D2)**
   - Migration additive `2029_07_26_000001` menambahkan `employees.name` (`string(100)`, nullable di schema), `employees.nik` (`string(16)`, nullable, unique **unconditional** termasuk baris terarsip), dan `employees.archive_reason` (`string(255)`, nullable).
   - Index additive pada `name`, `employee_type_id`, `employee_status_id`.
   - Backfill nama saat migration hanya dari `users.name` melalui `user_id`; baris yang tidak dapat diresolusi dibiarkan `NULL`.
   - Command `php artisan app:employee-name-backfill [--dry-run]` (idempotent, atomik) melaporkan hitungan agregat tanpa nama/NIK. Sumber: `user_id`, lalu fallback NIK unik hanya untuk baris legacy yang masih eligible.
   - `down()` migration menolak rollback bila ada nama yang tidak dapat direkonstruksi dari akun, NIK, atau alasan arsip.
2. **Daftar server-side dan privasi payload (D6)**
   - `GET /api/employees` memakai `EmployeeDirectory`: paginasi `10/20/50/100` (default 20), pencarian `name`/NUPTK, filter tipe/status/kepegawaian/mapel, sort allowlist `id|name|tmt_employee` dengan tie-break `id`.
   - Payload daftar memuat nama/NUPTK/gender dan `account: {linked, user_id}`; **bukan tanpa PII/data akun** (wording corrected 2026-10-29). NIK, NPWP, KK, ibu kandung, pasangan, kontak/alamat dan nilai ijazah tidak disertakan.
   - `only_archived=1` hanya untuk `employees.manage`.
   - Detail (`GET /api/employees/{id}`) menambahkan field sensitif + `education` **hanya** untuk pemegang `employees.manage`; pemegang `employees.view` menerima data operasional. Current archived-detail gating is documented in the correction addendum.
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

## 3. Hasil Pengujian Otomatis (historical initial 04b, actual 2026-10-29)

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
- Daftar: paginasi/default, `per_page` tidak sah `422`, filter, sort, payload operasional tanpa field sensitif (bukan klaim nol PII; corrected 2026-10-29).
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
| D6 | Privasi PII | Daftar/ekspor memuat nama/NUPTK/gender; daftar memuat metadata taut akun; field sensitif hanya detail `employees.manage` (wording corrected 2026-10-29) |
| D7 | Arsip beralasan + restore | `archive_reason` wajib; `exit_date` tidak diubah; restore berversi |
| D8 | Ekspor masuk 04b | `GET /api/employees/export`, izin ganda, 10 kolom, batas 10.000 → `422` |

---

## 5. NOT RUN dan Keterbatasan (historical initial 04b, 2026-10-29; see correction addendum)

1. **MariaDB disposable 04b — NOT RUN.** MariaDB tidak berjalan; service tidak dinyalakan sesuai batas sesi. Verifikasi DDL/unique/row-lock pada driver MySQL belum dijalankan. Kelas `MariaDbEmployeeIntegrationTest` (04a) ikut ter-skip.
2. **Browser UAT — NOT RUN.** Migration belum diterapkan ke database UAT dan belum ada fixture pegawai sintetis di UAT; keduanya memerlukan otorisasi terpisah. Golden path UI belum diuji di browser.
3. **Linux/macOS — NOT RUN.** Hanya Windows + PHP 8.2.35 ZTS.
4. **Migration UAT/produksi — TIDAK DITERAPKAN.** Hanya dijalankan otomatis pada SQLite in-memory oleh `RefreshDatabase` di suite tes.

Checklist pengganti untuk pemilik: `reports/feature-update/PHASE-04B-MANUAL-REVIEW.md`.

---

## 6. Risiko dan Catatan Terbuka

| Risiko | Status / mitigasi |
|---|---|
| Baris legacy dengan nama tak teresolusi tetap `NULL` | List returns `null` / blank, not the literal "name unresolved" (corrected 2026-10-29); command reports aggregates; operator completes the profile |
| Alias `/employee` deprecated | Dipertahankan dan diuji; penghapusan menunggu konfirmasi consumer |
| UI browser coverage | Initial NOT RUN remains historical; current operator CRUD/link/unlink/CSV/mobile DOM and separate three-role HTTP checks recorded above. Agent-side other-role browser UI automation, two-tab stale/link-conflict and print-preview/screenshot visual checks remain NOT RUN; **owner approved #1–#18 on 2026-10-30 WIB by statement** |
| MariaDB row-lock 04b belum diuji | NOT RUN; pola sama dengan `StudentController` yang telah diuji pada 03d, tetapi bukan bukti untuk 04b |
| Batas baris ekspor | Default 10.000; dapat diubah via `EMPLOYEES_EXPORT_MAX_ROWS`; perilaku 422 diuji |
| Advisory dependency & lifecycle Laravel 10/PHP 8.2 | **OPEN**, terpisah dari 04b; tidak ada perubahan dependency/lockfile |

---

## 7. Kesimpulan Teknis (historical initial 04b, 2026-10-29; current status in addendum)

Backend dan frontend Subfase 04b selesai dan lulus seluruh pemeriksaan otomatis yang tersedia pada sesi ini (suite Employee 56/331, suite penuh 293/1448, unit frontend 7/7, build, Pint, ESLint tanpa error). Tidak ada migration yang diterapkan ke UAT/produksi dan tidak ada merge/deploy. Status berikutnya: review manual pemilik melalui `PHASE-04B-MANUAL-REVIEW.md`.
