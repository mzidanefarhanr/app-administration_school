# Riwayat perubahan proyek

Catat perubahan selesai; jangan menyalin riwayat template `vue-app/CHANGELOG.md` menjadi riwayat aplikasi. Riwayat commit sebelum paket ini belum direkonstruksi. Tambah entri terbaru di atas entri lama; koreksi lewat addendum, jangan menghapus bukti sebelumnya.

## Format entri
```markdown
## [YYYY-MM-DD] Judul perubahan
- Type: Feature | Fix | Security | Schema Change | Docs | Chore
- Scope / fase:
- Files touched:
- What changed:
- Why:
- Validation: perintah + hasil aktual; NOT RUN bila tidak dijalankan
- Notes / risks:
- Evidence: laporan fase / SHA aktual bila sudah ada
```

## Entries

## [2026-10-15] Fase 01a — Koreksi akhir (tanggal DEC-14, batas ekspor, daftar file)
- Type: Fix, Docs
- Scope / fase: koreksi terbatas sebelum penerimaan pemilik Fase 01a.
- Files touched: docs/{DECISIONS,ACCESS-CONTROL,API-CONTRACTS}.md; PROJECT_CONTEXT.md; roadmap/ROADMAP.md; CHANGELOG.md; reports/feature-update/{PHASE-01A-ACCESS.md,INDEX.md,HANDOFF.md}; laravel-api/{config/students.php (baru),app/Http/Controllers/StudentExportController.php,database/migrations/2029_07_20_000003_seed_and_map_roles.php (komentar),tests/Feature/Access/StudentExportTest.php}; vue-app/src/views/pages/public/Student.vue.
- What changed: (1) tanggal persetujuan DEC-14 dikoreksi menjadi **2026-10-14 WIB** secara konsisten (tidak mengubah tanggal/identifier migration); (2) batas ekspor 10.000 baris diubah dari potong senyap → **tolak 422** dengan pesan English (jumlah baris, batas, ajakan persempit filter) via `config('students.export_max_rows')` (`config/students.php`, env `STUDENTS_EXPORT_MAX_ROWS`); `Student.vue` menampilkan pesan 422 backend; dokumentasi "seluruh hasil filter" diperjelas (berhasil hanya bila ≤ batas); (3) daftar file berubah dirapikan (modified vs untracked), `StudentExportTest.php` dikoreksi sebagai untracked.
- Why: memenuhi koreksi akhir atas permintaan pemilik sebelum penerimaan Fase 01a; menghindari pemotongan data diam-diam.
- Validation: `php artisan test` → **49 passed (119 assertions)** (2 tes batas baru); `./vendor/bin/pint --test` PASS (termasuk config/students.php); `npm run build` PASS; HTTP UAT (limit=2, 3 baris) → 422 pesan English (DB `school_access_uat`, sintetis). Tidak mengulang seluruh uji browser. NOT RUN: modul surat/izin/pembelajaran, migrasi/backup DB operasional.
- Notes / risks: BLK-03 tetap OPEN. Persetujuan DEC-14 = kebijakan, bukan penerimaan manual menyeluruh. Tidak commit/push; edisi pemilik pada CHANGELOG.md dipertahankan.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md §18.

## [2026-10-15] Fase 01a — Penyelesaian: ekspor siswa, pemetaan legacy, UI English (DEC-14 disetujui 2026-10-14 WIB)
- Type: Feature, Security, Fix, Docs, Test
- Scope / fase: penyelesaian Fase 01a sesuai keputusan pemilik DEC-14 (disetujui 2026-10-14 WIB).
- Files touched: docs/DECISIONS.md (DEC-14 APPROVED); docs/{API-CONTRACTS,ACCESS-CONTROL,REQUIREMENTS,DATABASE}.md; PROJECT_CONTEXT.md; roadmap/ROADMAP.md; CHANGELOG.md; reports/feature-update/{PHASE-01A-ACCESS.md,INDEX.md,HANDOFF.md,evidence/*}; laravel-api/app/Http/Controllers/StudentExportController.php (baru); laravel-api/routes/api.php; laravel-api/app/Support/AbilityCatalog.php; laravel-api/database/migrations/2029_07_20_000003_seed_and_map_roles.php; laravel-api/database/seeders/UatSeeder.php; laravel-api/tests/Feature/Access/{StudentExportTest.php (baru),AuthorizationTest.php}; laravel-api/tests/Support/CreatesAccessFixtures.php; vue-app/src/views/pages/master/Roles.vue; vue-app/src/views/pages/master/Accounts.vue; vue-app/src/views/pages/public/Student.vue; vue-app/src/stores/authStore.js.
- What changed: (1) DEC-14 APPROVED 2026-10-14 WIB (register ditutup, akun nonaktif ditolak, ekspor via `students.export`, legacy `Admin`→Admin Sistem & `User` tidak otomatis, UI English); (2) endpoint `GET /api/students/export` (auth+active+students.view+students.export) dengan kolom eksplisit, filter sama dengan daftar, leading zero/CSV escaping/neutralisasi formula; `Student.vue` unduh via endpoint, tombol ikut izin; (3) migration legacy dikoreksi (hanya Admin; `down()` tidak menghapus penetapan manual); (4) UI English di Roles/Accounts(+Assign Roles dialog)/Student, nama peran English, `authStore` abilities+`can()`; (5) `authStore.getUser` null-safe; 3 warning formatting Roles.vue dirapikan.
- Why: menyelesaikan Fase 01a sesuai keputusan pemilik dan menutup keterbatasan ekspor.
- Validation: `php artisan test` → **49 passed (119 assertions)** (DB uji SQLite in-memory; 2 tes batas ditambahkan pada koreksi akhir); `./vendor/bin/pint --test` PASS; `npm run build` PASS; `npx eslint Roles.vue` 0 problems; UAT HTTP+Playwright PASS (ekspor 200 kolom benar & 403 untuk waka & 422 batas, unduhan CSV via endpoint, tombol per izin, UI English, audit tanpa rahasia). NOT RUN: modul surat/izin/pembelajaran, migrasi/backup DB operasional.
- Notes / risks: BLK-03 (seeder `DatabaseSeeder` identitas asli) TETAP; `status_user_id` belum ditegakkan; keputusan autentikasi lain OPEN. Tidak commit/push; edisi pemilik pada CHANGELOG.md dipertahankan.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md §11–16; reports/feature-update/evidence/{uat-roles-en.png,uat-students-export.csv}.

## [2026-10-15] Fase 01a — Verifikasi review + lingkungan UAT terisolasi
- Type: Docs, Test, Chore
- Scope / fase: Fase 01a (persiapan & verifikasi review; tanpa fase berikutnya).
- Files touched: reports/feature-update/{PHASE-01A-ACCESS.md,INDEX.md,HANDOFF.md,evidence/uat-roles.png}; docs/{DECISIONS,ACCESS-CONTROL,TESTING,API-CONTRACTS}.md; PROJECT_CONTEXT.md; roadmap/ROADMAP.md; CHANGELOG.md; laravel-api/{.env.uat.example,database/seeders/UatSeeder.php,.gitignore}; vue-app/{.env.uat(lokal),src/apiLogin/index.js,.gitignore}. Tidak ada perubahan pada seeder identitas asli atau modul fitur berikutnya.
- What changed: (1) bukti laporan dilengkapi (nama/cakupan tes, pemisahan tes katalog vs endpoint, 3 warning ESLint, pemetaan legacy per nama, register/nonaktif sebagai implementasi menunggu keputusan, status_active≠status_user_id); (2) lingkungan UAT terisolasi (MySQL `school_access_uat` + UatSeeder sintetis + `VITE_API_BASE_URL`); (3) verifikasi Playwright + HTTP (sesi aktif, penolakan 403, akun nonaktif, check-auth minimum, admin tanpa bypass, audit tanpa rahasia).
- Why: menyiapkan & memverifikasi review Fase 01a pada lingkungan terisolasi tanpa menyentuh data operasional.
- Validation: lingkungan UAT efektif diverifikasi (mysql/school_access_uat) sebelum migrasi; migrasi hanya di DB UAT. Playwright PASS (roles + matriks + tambah students.export). HTTP PASS (propagasi izin sesi aktif, 403 wakil, 422 nonaktif, 403 sesi dinonaktifkan, check-auth minimum, audit 0 leak). `php artisan test` tetap 34 passed. Guard DB tes tidak dikendurkan. NOT RUN: modul surat/izin/pembelajaran (belum ada), migrasi/backup pada DB operasional.
- Notes / risks: `students.export` belum punya endpoint backend (ekspor klien di Student.vue) — tidak diklaim terlindungi. Register/nonaktif = implementasi menunggu konfirmasi pemilik. BLK-03 (seeder identitas asli) tetap. Tidak commit/push; edisi pemilik pada CHANGELOG.md dipertahankan.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md (revisi §3–§5, §4b–§4e); reports/feature-update/evidence/uat-roles.png.

## [2026-10-15] Fase 01a — Peran/kemampuan dan penegakan akses backend
- Type: Feature, Security, Schema Change
- Scope / fase: Fase 01a (authorization backend) + isolasi DB uji (BLK-01) + DEC-03.
- Files touched: migration 2029_07_20_00000{1,2,3}; app/Support/AbilityCatalog.php; app/Providers/AccessServiceProvider.php; app/Http/Middleware/EnsureUserIsActive.php; app/Models/{Role,Ability,User}.php; app/Http/Controllers/{RoleController,AbilityController,AccessAssignmentController,UserController,FirstLoginController}.php; app/Http/Requests/Auth/LoginRequest.php; app/Traits/Loggable.php; routes/{api,auth}.php; config/app.php; Laravel Kernel.php; database/{seeders/AccessControlSeeder.php,factories/UserFactory.php,seeders/DatabaseSeeder.php}; phpunit.xml; .env.testing; tests/*; vue-app/src/views/pages/master/Roles.vue; vue-app/src/router/index.js; vue-app/src/layout/AppMenu.vue; docs/*; PROJECT_CONTEXT.md; roadmap/ROADMAP.md; reports/*; CHANGELOG.md.
- What changed: peran/kemampuan fleksibel (roles/abilities/ability_role/role_user + katalog + UI `/master/roles`); Gate bernama terpusat tanpa bypass admin; middleware `active`; endpoint dilindungi `can:`; FirstLogin anti-IDOR; self-update tidak bisa eskalasi atribut; proteksi admin pengelola akses terakhir; `check-auth` minimal; register publik ditutup; akun nonaktif ditolak; audit Loggable tanpa password/token; DB uji terisolasi SQLite `:memory:` + fail-safe.
- Why: menutup BLK-01/BLK-02 (sebagian) dan menegakkan FR-01/ACCESS-CONTROL sesuai DEC-03.
- Validation: `php artisan test` → **34 passed (79 assertions)** di DB uji SQLite in-memory; guard isolasi terverifikasi memblokir mysql operasional; `npm run build` PASS; `./vendor/bin/pint --test` 27 file PASS; `npx eslint Roles.vue` 0 error (3 warning format). NOT RUN: browser/Playwright, migrasi pada DB operasional.
- Notes / risks: BLK-03 seeder identitas asli belum diperbaiki; `status_user_id` belum ditegakkan; kemampuan modul belum dibangun hanya katalog; SQLite belum menguji concurrency/unique MySQL. Perubahan pemilik pada CHANGELOG.md dipertahankan. Tidak commit/push.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md; HEAD b699fec (working tree berubah).

## [2026-10-15] Fase 00 — Audit baseline dan kesiapan lingkungan (read-only)
- Type: Docs
- Scope / fase: Fase 00 (00a inventory source + 00b kesiapan lingkungan).
- Files touched: reports/feature-update/PHASE-00-RUNTIME-AUDIT.md (baru); CHANGELOG.md, roadmap/ROADMAP.md, reports/feature-update/INDEX.md, reports/feature-update/HANDOFF.md (pembaruan). Tidak ada kode/schema/data diubah.
- What changed: Inventaris route API, controller/request/policy, consumer Vue, migration/schema, akses, seed/factory dan runtime; audit baseline HEAD `b699fec` (delta dari `b02a87f` = paket docs saja).
- Why: Menutup syarat keluar subfase 00 dan menyiapkan keputusan 01a.
- Validation: read-only. Dijalankan: git rev-parse/status/diff --stat, baca source, php -v (8.1.25), composer -V (2.8.2), node -v (24.18.0), npm -v (11.4.2), git --version, Test-NetConnection:3306 (True). NOT RUN: php artisan test, migrate, seed, npm run build, lint, browser/manual, akses API/data hardcode.
- Notes / risks: BLK-01 isolasi DB test belum ada (phpunit.xml sqlite dikomentari; .env = mysql `backdata_administrations`) → test DB diblokir. BLK-02 enforcement akses backend tidak ada (policy scaffold). BLK-03 seeder memuat identitas pribadi. DEC-03 OPEN menghambat 01a. Temuan baseline B-01..B-17 terverifikasi ulang pada HEAD.
- Evidence: reports/feature-update/PHASE-00-RUNTIME-AUDIT.md; HEAD b699fec, working tree `M CHANGELOG.md`.

## [2026-10-14] Paket dokumentasi pengembangan OpenCode disiapkan
- Type: Docs
- Scope: aturan kerja, kebutuhan, arsitektur, schema baseline/usulan, roadmap 14 fase, prompt dan template evidence.
- Files touched: berkas Markdown dalam paket ini; belum diterapkan/commit/push ke repo oleh penyusun.
- What changed: dokumentasi disiapkan sebagai paket untuk dipasang dan ditinjau pemilik.
- Why: mendukung pengembangan fitur bertahap melalui OpenCode berdasarkan tujuan operator sekolah.
- Validation: source review pada baseline b02a87f; pemeriksaan struktur dan tautan paket. Runtime/test/build/browser NOT RUN.
- Notes / risks: target fitur dan role baru merupakan usulan; implementasi belum dimulai. Sumber App Gudang dipakai sebagai pola proses, bukan teknologi atau hasil QA.
- Evidence: reports/feature-update/PHASE-00-SOURCE-BASELINE.md.
