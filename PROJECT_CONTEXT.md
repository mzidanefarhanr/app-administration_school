# Konteks proyek

## Current addendum — 2026-10-30 (Subfase 05a)

Student CSV import is **implemented and agent-verified, owner review PENDING**, on `main` above `b093db7`; implementation `58828a6` and documentation `a3e19d4` are pushed to `origin/main` for GitHub review (per owner instruction, review packages are pushed before manual checks; push is not approval). DEC-25 (APPROVED 2026-10-30 WIB): all-or-nothing commit; NIS then NIK matching (employee import matches NIK in 05b); UTF-8 CSV ≤5 MB/2,000 rows; full English; `students.import` for Admin Sistem and Operator Sekolah only; private error reports with manual 30-day retention; separate `.uat-phase05`; 05b = employee import + export column options, school recap deferred (DEC-12 PROPOSED).

Contract: `GET /api/imports/students/template` (`students.view`); `POST /api/imports/students`, batch show/commit/errors (`students.import`, creator-only, other authorized users 404). Batches live in `import_batches`; source/error CSV stay in private `imports/{token}` storage. Header is strict English 17 columns; `App\Support\StudentRules` is shared with the student form. Migrations `2029_07_27_000001`/`000002` are additive.

Isolated UAT `.uat-phase05` (SQLite; SPA `127.0.0.1:14023`, API `127.0.0.1:18023`, cookie `school_phase05_session`; launcher `scripts/phase05-uat.php`) holds 300 synthetic students + 200 employees (100/100). Agent evidence: import 18/97, regression 170/1097 (13 skipped), Pint 16, 3 Node tests, build 26.02 s, browser DOM flows with 0 console errors. MariaDB/concurrency/two-tab/screenshot and automatic retention are NOT RUN. Owner checklist 05a remains PENDING; agent evidence is not acceptance.

## Current correction addendum — 2026-10-29

Baseline `main` at `e281419` (corrections `dc5e031`, documentation `e281419`), pushed to `origin/main`. The October 14/15 scaffold/runtime claims below are **historical, not current**: student CRUD/directory/placement and employee CRUD/Teachers/Employees routes are implemented. 04a and **04b are owner-accepted**: 04b checklist #1–#18 APPROVED per the owner's 2026-10-30 WIB statement, recorded separately from agent evidence.

Full-English presentation is required: Teachers = Guru only (`Pendidik`), Employees = non-teachers (`Tenaga Kependidikan`), classification only by `employee_type_id`, canonical jobs retained. Shared 17-domain/243-label JSON and frontend `display_name` preserve canonical references/IDs and proper/user data; passwords stripped before cache enrichment; route-view key prevents stale rows/dialogs. List/export contain name/NUPTK/gender and list account linkage metadata (not zero PII); sensitive data is manage-detail only. Archived GET detail requires view + manage (403 view-only); unresolved list names are null/blank; CSV header `Employment Start Date (TMT)`.

New SQLite UAT: SPA/API `127.0.0.1:14022` / `127.0.0.1:18022`, root `.uat-phase04b/database.sqlite`, private environment/storage, `school_phase04b_session`. Use guarded `laravel-api/scripts/phase04b-uat.php` (`init`/`prepare`/`status`), normal migrations only; never root `artisan --env=uat` or old/main/MariaDB targets. Baseline 100 synthetic teachers + 100 staff (15 jobs), current 101 each, 103 students, 100 synthetic schools (NPSN `04` namespace); idempotent fixtures preserve edits/archives, only initial five accounts; runtime/`UAT-ACCOUNTS.local.md` ignored/private. Latest explicit SQLite 96 tests/1738 assertions and frontend 42 tests PASS; final build PASS (18.77 s), targeted style PASS. Operator browser checks include create/edit/conflict/archive/restore/link/unlink, route types, English CSV and staff mobile DOM fit/scroll; separate three-role HTTP permissions PASS, not other-role browser UI. Other-role browser UI, two-tab stale/link-conflict, print-preview/screenshot visual checks and 04b MariaDB DDL/InnoDB NOT RUN. See report/HANDOFF for overlapping run/method boundaries. Owner accepted checklist #1–#18 by statement on 2026-10-30 WIB; acceptance does not approve deployment, legacy/production migration, merge or service/dependency changes; the corrections are committed (`dc5e031`) with documentation (`e281419`) pushed to `origin/main`.

## Baseline dan maksud (historical October 14/15 source review)
Tanggal source review: 14 Oktober 2026 (WIB); audit Fase 00/01a: 15 Oktober 2026 (WIB). Repo: `mzidanefarhanr/app-administration_school`.
Baseline kode: `main` @ `b02a87f0d57adffec297ed465a8afcd8e8d7ec59`. HEAD saat audit: `b699feccd65357a10f7430c165400906e6394d49` (delta = paket docs, kode tidak berubah). Fase 01a menambah perubahan working tree belum commit (lihat laporan 01a).
Tujuan pemilik: mempermudah operator mengelola siswa, guru, pembelajaran, surat, izin, pencatatan, backup dan analisis. Runtime aplikasi (server hidup) NOT RUN; tes otomatis DB SQLite in-memory dijalankan di Fase 01a.

## Arsitektur aktual
| Area | Lokasi / perilaku source |
|---|---|
| API | `laravel-api/routes/api.php`, mayoritas resource dalam `auth:sanctum` |
| Autentikasi | `routes/auth.php`, `LoginRequest`, session controller; Sanctum SPA cookie/session |
| Model | `laravel-api/app/Models` dan relasi Eloquent; banyak soft delete |
| Audit | `app/Traits/Loggable.php`, `ActivityController`, tabel activities |
| Halaman Vue | `vue-app/src/views/pages/public`, `master`, `auth` |
| Routing Vue | `src/router/index.js`; pemeriksaan frontend tipe User terhadap `/master` |
| State | `src/stores/authStore.js`; persisted state sessionStorage, bukan sumber otorisasi |
| CRUD reusable | `src/utils/useResourceManager.js` |
| Tema | `src/main.js`: PrimeVue Aura, selector `.app-dark` |
| URL API | `src/apiLogin/index.js`: HTTP hardcode, perlu environment |
| Akses (Fase 01a) | `app/Support/AbilityCatalog.php`, `app/Providers/AccessServiceProvider.php`, tabel `roles`/`abilities`/`ability_role`/`role_user`; middleware `active`; Gate bernama `can:` |
| UI peran (Fase 01a) | `vue-app/src/views/pages/master/Roles.vue` (route `/master/roles`), penetapan peran di `master/Accounts.vue`; label English |
| Ekspor siswa (Fase 01a) | `GET /api/students/export` (`StudentExportController`); unduhan CSV di `public/Student.vue` |

Folder frontend `public` adalah nama pengelompokan source, bukan bukti data boleh diakses guest. Jangan membuka biodata siswa ke publik karena nama direktori tersebut.

## Domain dan keadaan
Siswa memiliki biodata dan keluarga. Keanggotaan rombel terpisah dari siswa; rombel terkait tahun ajaran, jenjang, jurusan dan wali kelas. Pegawai baseline terkait akun lewat NIK; mata pelajaran pegawai baseline satu FK, belum mendukung banyak penugasan.
Role seeder: Admin kemudian User. Status akun: Aktif kemudian Tidak Aktif. ID 1/2 mengikuti urutan seeder pada database baru, bukan jaminan ID setiap instalasi. Jangan menyalin role gudang 1–6.
Fase 01a menambahkan peran baru terpisah dari `type_users`: Admin Sistem, Operator Sekolah, Kepala Sekolah, Wakil Kepala Sekolah Bidang Kesiswaan (DEC-03). Pemetaan legacy memakai nama `type_users` (Admin→Admin Sistem, User→Operator Sekolah), bukan angka ID. Kemampuan bernama mis. `roles.manage`, `students.export`, `letters.approve`, `leaves.approve`, `letters.sign`.
`status_active` dan `first_login_at` adalah boolean baseline; Fase 01a menegakkan `status_active` (akun nonaktif ditolak login dan sesi aktifnya diakhiri). `status_user_id` (Aktif/Tidak Aktif) belum dijadikan sumber penegakan — keputusan terbuka. DEC-14 (2026-10-14 WIB) menyetujui register publik ditutup, akun nonaktif ditolak, ekspor siswa via `students.export`, dan pemetaan legacy `Admin`→Admin Sistem (`User` **tidak** otomatis Operator Sekolah).

## Source yang belum lengkap
StudentController: index berisi query; store hanya transaksi kosong; update/destroy kosong; FormRequest siswa authorize false. EmployeeController dan operasi tulis StudentRombel belum lengkap. Teachers/Employees/Letters Form pada menu masih `#`. Subject dan master lainnya bukan bukti jurnal pembelajaran tersedia.
Dashboard memuat widget penjualan contoh; halaman siswa sendiri memiliki rekap/chart/filter tahun ajaran dan ekspor CSV. Penanda Dapodik bukan integrasi sinkronisasi.

## Risiko awal
Hak akses API, rollback audit, log atribut sensitif, payload siswa berlebihan, query seluruh data, lingkungan test tidak terisolasi, seed identitas menyerupai data pribadi, endpoint HTTP hardcode. Daftar dengan sumber ada di laporan baseline.

## Akses (status setelah Fase 01a)
Penegakan backend terpusat via Gate bernama (tanpa bypass admin). Endpoint master/siswa/pegawai/roles/abilities dilindungi `can:`. FirstLogin terikat actor; atribut administratif akun dilindungi; `check-auth` diminimalkan; register publik ditutup. Default deny untuk kemampuan tak terdaftar. Kemampuan modul yang belum ada (surat/izin/pembelajaran) baru sebatas katalog/kemampuan, belum ada enforcement pada fitur yang belum dibangun.

## Sumber kebenaran dokumentasi
REQUIREMENTS menyimpan target; DATABASE memisahkan schema aktual/usulan; ROADMAP menyimpan scope/status; DECISIONS menyimpan keputusan; laporan menyimpan evidence. HANDOFF berisi checkpoint sesi terbaru. Semua harus diaudit ulang jika HEAD berubah.
