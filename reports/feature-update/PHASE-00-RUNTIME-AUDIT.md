# Laporan Fase 00 — Audit baseline dan kesiapan lingkungan

- Tanggal WIB: 2026-10-15
- Mode: source review / runtime inventory (read-only)
- Scope instruksi pemilik: Fase 00 saja — audit source, route API, consumer Vue, schema migration, akses, seed/factory dan kesiapan lingkungan; cocokkan dengan HEAD aktual. Read-only aplikasi/data; boleh menulis laporan. Jangan jalankan test DB sebelum isolasi, jangan seed/migrate:fresh/restore/akses API hardcode. Berhenti setelah laporan.
- Branch / baseline SHA: `main` @ `b699feccd65357a10f7430c165400906e6394d49` (HEAD). Baseline paket: `b02a87f0d57adffec297ed465a8afcd8e8d7ec59`.
- Working tree sebelum: file tracked termodifikasi `CHANGELOG.md` (edisi pemilik, +7 baris; bukan hasil sesi ini). Tidak ada perubahan lain; `.env` di-ignore.
- Working tree sesudah: laporan ini + pembaruan `CHANGELOG.md`, `ROADMAP.md`, `INDEX.md`, `HANDOFF.md`. Tidak ada perubahan kode aplikasi/schema/data.
- Lingkungan: Windows win32, PowerShell 5.1; PHP 8.1.25, Composer 2.8.2, Node v24.18.0, npm 11.4.2, git 2.47.0; MySQL port 3306 (127.0.0.1) terbuka. Browser (Playwright MCP) tersedia tetapi tidak dipakai untuk mutasi.
- Status teknis: `PARTIAL` (audit source + inventaris lingkungan selesai; runtime/build/test DB dan browser NOT RUN).
- Status review: `PENDING`.

## 1. Outcome dan cakupan

Audit ulang source terhadap HEAD aktual `b699fec` (bukan `b02a87f`). Delta `b02a87f..b699fec` hanya menambahkan paket dokumentasi (36 file Markdown, +1854 baris) — **tidak ada perubahan kode aplikasi**. Karena itu temuan source `PHASE-00-SOURCE-BASELINE.md` masih relevan untuk kode, tetapi **SHA/klaim baseline berubah** dan harus diperbarui ke `b699fec`.

Yang selesai: peta route→controller/request/policy→model/migration→consumer Vue; audit akses; audit schema/migration; audit seed/factory; inventaris runtime dependency (read-only). Yang **tidak** dilakukan: `composer install`, `php artisan test`, `migrate`, `seed`, `npm run build`, lint, dan setiap akses API/browser ke data. Subfase 00a/00b dijalankan bersamaan atas instruksi pemilik "Fase 00 saja".

## 2. Inventory source (00a)

### 2.1 Working tree dan HEAD
- HEAD `b699fec`, branch `main`. Commit setelah baseline: `05eb83f` dan `b699fec` (keduanya "chore: update new file for chore") — isinya = paket docs.
- Delta `b02a87f..HEAD` = 36 file docs saja (`git diff --stat`). Kode PHP/Vue identik baseline.

### 2.2 Route API
- `laravel-api/routes/api.php`: seluruh resource bisnis berada dalam grup `Route::middleware(['auth:sanctum'])` (baris 71–110). 33 `apiResource` (users, status-users, type-users, firstlogin, provinces…villages, school-years, education-levels, semesters, genders, religions, blood-types, professions, family-status, education-schools, student-statuses, student-entries, student-majors, phase-statuses, students, school-levels, school-rombels, student-rombels, employee, employee-statuses, employee-types, employments, marital-statuses, school-profile, subjects, activities).
- **Route publik tanpa auth**: `GET /api/check-auth` (baris 54–66). Mengembalikan `authenticated` + **objek user penuh** (`$request->user('sanctum')`) bila sesi ada, 200 walau tidak login.
- `laravel-api/routes/auth.php`: `register`, `login`, `forgot-password`, `reset-password` (middleware `guest`); `verify-email`, `email/verification-notification`, `logout` (auth). **Tidak ada** cek akun aktif/nonaktif pada login.
- `routes/console.php`: hanya `inspire`. **Tidak ada scheduler/backup** (B-06 tetap berlaku).

### 2.3 Controller → Request/Policy
- 34 controller non-auth. Pola: controller master data (TypeUser, StatusUser, Gender, dst.) **implemented** untuk index/store/show/update/destroy dengan validasi inline + soft-delete restore + cek referensi before delete (contoh `TypeUserController`).
- **Scaffold (belum diimplementasikan)**: `StudentController` (store = transaksi kosong; update/destroy/show kosong), `StudentRombelController` (store/update/destroy kosong), `EmployeeController` (seluruh write kosong). `create`/`edit` kosong di banyak controller (normal untuk API).
- `ActivityController`: `store` menerima `before/after/new/delete` mentah dari request; `update` melakukan rollback. Terkonfirmasi temuan B-05: memakai `DB::table($modelName)` dengan `$modelName` = nama **kelas model** (`App\Models\X`) bukan nama tabel → bug.
- `FirstLoginController::update(Request $request, string $id)` menerima `id` user dari URL dan `status_active`/`first_login_at` dari request **tanpa pembatasan kepemilikan** (B-02).
- `UserController`: validasi jenis kelamin unik dengan `whereNull('deleted_at')`; update memakai optimistic `version` check (409) **non-atomik** (`if` lalu `update` tanpa `where version`); cabang admin dicek `$editor['type_user_id'] == 1` **inline** (B-14). `register` bawaan Breeze **tidak** menyertakan kolom `required` users (username/nik/type_user_id/status_user_id) (B-17).
- `app/Http/Requests/StoreStudentRequest.php` & `UpdateStudentRequest.php`: `authorize(): false` dan `rules(): []` (B-03).

### 2.4 Policy
- 32 policy di `app/Policies/**` — **semua scaffold kosong** (method `viewAny/view/create/...` body `//`).
- `AuthServiceProvider::$policies` **kosong** (mapping dikomentari). Tidak ada `authorize(`, `Gate::`, atau `can(` di controller mana pun (hanya `FormRequest::authorize`). → **Authorization per operasi/objek tidak ditegakkan backend** (B-01).

### 2.5 Consumer Vue
- `vue-app/src/apiLogin/index.js`: `baseURL: 'http://26.117.240.38:4001'` **hardcode HTTP** (B-09). Interceptor `401` dipasang di `axios` global, bukan instance `apiLogin`, dan membaca `error.response.status` (dapat `undefined`).
- `vue-app/src/stores/authStore.js`: `checkLogin()` memanggil `/api/check-auth`; `getUser()` memanggil `/api/users` (payload penuh). Persist ke `sessionStorage` (bukan otorisasi).
- `vue-app/src/router/index.js`: `authNav` pada `beforeEnter` parent `/`; memblokir `type_user_id == 2` dari `/master` **di frontend saja** (B-16). Route `landing/notfound/access/error` tanpa guard. Tidak ada route `/master/employees` dan tidak ada route `/profile/password`, `/forgot-password` (B-17).
- `vue-app/src/layout/AppMenu.vue`: `Teachers`, `Employees`, `Letters Form` → `to: '#'` (B-11).
- `vue-app/src/views/Dashboard.vue`: memuat widget demo Sakai (`BetaSellingWidget`, `RevenueStreamWidget`, `RecentSalesWidget`, `ChartDataWidget_1`, dll.) (B-10).
- `vue-app/src/main.js`: pada `import.meta.env.PROD` menimpa `console.error/warn/log` menjadi no-op (B-15).
- View pages: 9 `public/*` (SchoolProfile, EducationLevels, EducationSchools, PhaseStatus, Profession, SchoolYears, SchoolRombels, StudentMajors, Student), 2 `master/*` (Accounts, TypeUsers), 6 `auth/*`. `useResourceManager.js` (composable CRUD) memakai `type_editor = saveUser.value.type_user_id` (pola tipe inline).

### 2.6 Klasifikasi
| Kategori | Contoh |
|---|---|
| Implemented | Master data CRUD (TypeUser, StatusUser, Gender, Religion, …, SchoolProfile, Subjects), auth Breeze, `check-auth` |
| Scaffold | Student/StudentRombel/Employee write; seluruh Policy; sebagian FormRequest |
| Demo | Dashboard widget/revenue/orders Sakai |
| Placeholder | Menu `#` (Teachers/Employees/Letters Form) |
| Belum ada | Backup/restore, scheduler, pagination server, letters/leaves/archives/teaching |

## 3. Schema dan migration

- 35 migration; **semua additive**, urutan berbasis nama file (cap tahun 2029). Tidak ada migration Fase-01+.
- `users` (`2026_10_12`): `first_login_at`/`status_active` **boolean NOT NULL tanpa default**; `email/username/nik` unique; FK `type_user_id`→`type_users`, `status_user_id`→`status_users`; soft delete.
- `students` (`2029_07_06`): `nik/nis/nisn` **unique nullable**; banyak FK nullable; kolom keluarga; `check_ppdbbersama/kjp/bpms/pip`. Kolom statistik/rombel lama dikomentari.
- `student_rombels` (`2029_07_10`): `student_nik` **string FK ke `students.nik`** (NIK nullable → relasi rapuh); `school_rombel` FK ke `school_rombels.id`; **tidak ada constraint unique komposit**; soft delete. Terkonfirmasi B-13.
- `school_rombels` (`2027`): FK `school_level_id`, `school_year_id`, `student_major_id`, `class_teach_id` (users.id), `name`, `version`.
- `employees` (`2026_10_15`): FK required banyak; `subject` tunggal; tanpa `version` baseline.
- `activities` (`2026_10_15`): `user_id` FK `onDelete('cascade')`; JSON before/after/new/delete; `information` enum; tanpa idempotency/rollback linkage.
- **Model–schema mismatch**: model `Student` fillable tidak memuat `check_bpms` (ada di migration); `StudentRombel` migration punya `latest_information/check_dapodik/informations_dapodik` yang belum tentu ada di fillable; `Employee` relasi kemungkinan salah eja (`relogion`). Perlu verifikasi consumer saat menyentuh model (belum diubah di Fase 00).
- `docs/DATABASE.md` memperlakukan baseline `b02a87f`; **perlu** dicatat bahwa HEAD kini `b699fec` dengan migration identik (tidak berubah), jadi analisis schema dokumen masih valid.

## 4. Akses

- Enam temuan akses inti tetap terbuka di HEAD (lihat B-01/B-02/B-14/B-16/B-17 di laporan baseline; semuanya terverifikasi ulang di source HEAD):
  - Tidak ada enforcement operasi/record di backend (policy kosong, tidak dipanggil).
  - `FirstLoginController::update` rentan IDOR (`id` dari URL, status dari request).
  - `check-auth` mengembalikan objek user ke pemanggil tanpa sesi (200).
  - Login tidak memeriksa `status_active`/`status_user_id` (akun nonaktif tetap dapat login).
  - Cek tipe `type_user_id == 1/2` tersebar inline (backend `UserController`, frontend router, `useResourceManager`).
  - `register` publik tetap terbuka tanpa kebijakan bootstrap.
- Peta role/status: seeder Admin(1)/User(2); status Aktif/Tidak Aktif; `first_login_at`/`status_active` boolean (makna bisnis belum dikonfirmasi — lihat DEC-03).

## 5. Seed dan factory

- `DatabaseSeeder` memanggil `StatusUser/TypeUser` lalu **membuat 6 User** dengan **nama, email, username, NIK asli** (mis. NIK 16 digit, email personal) dan mengharuskan data lain (SchoolYear…Employee). Terkonfirmasi B-08: **seed memuat identitas menyerupai data pribadi** dan tidak layak dipakai sebagai fixture sintetis.
- 32 factory tersedia, `UserFactory` default `type_user_id=2`, `status_user_id=1`, password `password`, boolean false.
- Tidak ada factory fixture terisolasi untuk test. Seeder **tidak dijalankan** di sesi ini.

## 6. Kesiapan lingkungan (00b)

| Item | Hasil (read-only) |
|---|---|
| PHP | 8.1.25 (ZTS) — memenuhi `composer.json` `"php": "^8.1"` |
| Laravel | v10.48.29 (composer.lock) |
| Composer | 2.8.2; `vendor/` **ada**; `composer.lock` ada |
| Node/npm | v24.18.0 / 11.4.2; `node_modules/` **ada**; `package-lock.json` ada |
| MySQL | port 3306 terbuka; driver `.env` = `mysql`, DB `backdata_administrations` (nama development/aktif) |
| `.env` | ada dan **git-ignored**; `FRONTEND_URL` ganda (localhost:3000 lalu 26.117.240.38:4000); `SANCTUM_STATEFUL_DOMAINS` = host LAN |
| Browser | Playwright MCP tersedia; **tidak** dipakai (tidak ada tindakan browser dalam scope) |
| Test | `tests/`: ExampleTest + Breeze Auth (Authentication/EmailVerification/PasswordReset/Registration) — sesuai TESTING.md |
| `phpunit.xml` | `DB_CONNECTION`/`DB_DATABASE` sqlite **dikomentari** (B-07) → test akan memakai `mysql` `.env` = DB aktif. **Belum terisolasi.** |

**Isolasi belum terbukti.** Karena `phpunit.xml` mengomentari override sqlite dan `.env` mengarah ke `mysql` DB `backdata_administrations`, menjalankan `php artisan test` saat ini berisiko menulis ke DB development/aktif. Semua test DB **NOT RUN** (sesuai instruksi). Belum ada `.env.testing` atau guard DB uji. Ini blocker utama subfase 01.

## 7. Evidence

| ID | Metode / command | Objek | Expected | Actual | Status |
|---|---|---|---|---|---|
| E-01 | `git rev-parse HEAD` / `git status` | repo | SHA + working tree | `b699fec`, `M CHANGELOG.md` | SELESAI |
| E-02 | `git diff --stat b02a87f..HEAD` | delta baseline | perubahan kode? | 36 file docs saja | SELESAI |
| E-03 | Baca `routes/api.php`,`auth.php` | route | peta auth | 33 resource auth:sanctum + `check-auth` publik | SOURCE REVIEW |
| E-04 | Baca policy + `AuthServiceProvider` | akses | enforcement | policy kosong, mapping kosong, tanpa authorize | SOURCE REVIEW |
| E-05 | Baca `FirstLogin/Activity/UserController` | akses/audit | temuan baseline | B-02/B-05/B-14 terkonfirmasi | SOURCE REVIEW |
| E-06 | Baca migration users/students/student_rombels/activities | schema | mismatch | B-13 terkonfirmasi | SOURCE REVIEW |
| E-07 | Baca `DatabaseSeeder` | seed | identitas? | B-08 terkonfirmasi | SOURCE REVIEW |
| E-08 | `php -v`, `composer -v`, `node -v`, `npm -v`, `Test-NetConnection 3306` | env | runtime | tersedia semua; MySQL terbuka | SELESAI |
| E-09 | Baca `phpunit.xml` + `.env` (redacted) | isolasi test | terisolasi? | mysql aktif → **tidak terisolasi** | BLOCKED |
| E-10 | `php artisan test` | suite | jalankan | **NOT RUN** (DB belum terisolasi) | NOT RUN |
| E-11 | `npm run build` | frontend | build | **NOT RUN** (di luar kebutuhan 00) | NOT RUN |
| E-12 | Browser/manual | UI | periksa | **NOT RUN** | NOT RUN |
| E-13 | Seed/migrate/restore | data | — | **NOT RUN** (dilarang) | NOT RUN |

Semua bukti SOURCE REVIEW/SELESAI, bukan PASS fungsional. Tidak ada test/build/browser dijalankan.

## 8. UAT manual

Browser tersedia namun tidak ada skenario UI yang perlu dijalankan pada Fase 00 (read-only, tanpa server hidup). Before the owner runs any manual checklist: **jangan** memakai data operasional. Checklist minimum setelah isolasi DB (untuk 01):
1. Login akun nonaktif (`status_active=0`) — expected: ditolak (belum dijamin saat ini).
2. `GET /api/check-auth` tanpa sesi — expected: tidak membocorkan objek user.
3. Akses `/master/accounts` sebagai `type_user_id=2` — expected: API menolak 403, bukan hanya UI.
4. `PUT /api/firstlogin/{id_user_lain}` dengan user biasa — expected: ditolak.
Semua tetap NOT RUN/PENDING sampai hasil pemilik dicatat.

## 9. Risiko, blocker dan keputusan

Blocker:
- **BLK-01 Isolasi DB test belum ada** — `phpunit.xml` sqlite dikomentari, `.env` mysql `backdata_administrations`. Semua test DB diblokir sampai `.env.testing`/guard dibuat.
- **BLK-02 Enforcement akses backend tidak ada** — policy scaffold + tidak dipanggil; FR-01 belum tercapai.
- **BLK-03 Identitas pribadi pada seeder** — mencegah test/browser dengan data yang ada.
- **BLK-04 Keputusan DEC-03 (role) OPEN** — menghambat 01a.

Risiko (bukan regresi — temuan baseline): audit password bocor via `getOriginal()` (B-04); rollback `ActivityController` bug (B-05); API hardcode (B-09); payload user penuh via `check-auth`/`/api/users` (B-12); error PROD disenyapkan (B-15); `version` update non-atomik (B-14).

Keputusan dibutuhkan untuk 01a:
- **DEC-03**: peran (Admin/Operator/Kepala sekolah/Guru-wali), kemampuan bernama, dan siapa boleh sign/approve/export. Wajib sebelum enforcement.
- Konfirmasi makna `status_active` vs `status_user_id` dan perilaku akun nonaktif (cabut sesi/token).
- Kebijakan endpoint `register` publik: tetap, ditutup, atau bootstrap.
- Kebijakan `check-auth`: minimalkan payload (hindari objek user penuh).

## 10. Sinkronisasi dokumen

Diperbarui: laporan ini; `CHANGELOG.md` (entri Fase 00); `roadmap/ROADMAP.md` (status 00 → IN PROGRESS/PARTIAL, baseline SHA); `reports/feature-update/INDEX.md` (tautan laporan); `reports/feature-update/HANDOFF.md` (checkpoint). Tidak ada perubahan kode/schema/data.
Commit/push/merge/deploy: **NOT DONE** (tidak diotorisasi). Working tree tetap reviewable.

## 11. Langkah berikutnya

- **Scope berikutnya (usulan, belum dijalankan): 01a — role dan kemampuan + penegakan akses backend**, dengan prasyarat: isolasi DB test (BLK-01) dan DEC-03.
- Sebelum 01a: perbarui `PROJECT_CONTEXT.md`/`docs/DATABASE.md` baseline SHA ke `b699fec` (atau catat bahwa kode = `b02a87f`) dan putuskan DEC-03.
- Jangan lanjut subfase lain tanpa instruksi. Laporan ini belum memberi izin implementasi.
