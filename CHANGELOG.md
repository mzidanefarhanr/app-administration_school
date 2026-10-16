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

## [2026-10-16] Fase 01b — Test & Docs: pelengkapan review bukti pemulihan error autentikasi dan audit akses master
- Type: Test, Docs, Security
- Scope / fase: pelengkapan review bukti Fase 01b (skenario pemulihan error auth, audit akses Operator ke `/master/typeusers`, regresi 4 peran browser, dan perapian panduan UAT).
- Files touched:
  - `laravel-api/tests/Feature/Access/TypeUserAccessTest.php` (baru)
  - `vue-app/src/stores/authStore.js`
  - `vue-app/tests/phase-01b-api-auth.test.mjs`
  - `vue-app/tests/auth-race-condition.test.mjs`
  - `reports/feature-update/PHASE-01B-API-AUTH.md`
  - `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`
  - `reports/feature-update/HANDOFF.md`
  - `reports/feature-update/INDEX.md`
  - `roadmap/ROADMAP.md`
  - `CHANGELOG.md`
- What changed:
  1. Pemetaan 6 skenario pemulihan & error auth diverifikasi lengkap:
     - Retry 419 berulang berhenti tanpa loop dan notifikasi security mismatch.
     - Refresh CSRF gagal ditangani tanpa auto-retry dan tanpa loop.
     - Beberapa request 419 bersamaan dikoordinasikan ke tepat 1 request refresh CSRF.
     - Timeout / network failure tidak menghapus sesi lokal.
     - Logout server gagal melaporkan `serverSuccess: false` dan membersihkan state lokal.
     - 401 bersamaan dideduplikasi dan sequence request monotonik global (`requestSequence`) mencegah respon usang menimpa sesi baru saat switch account.
  2. Audit akses Operator ke `/master/typeusers`:
     - Menjelaskan izin `master.manage` dan operasi CRUD backend.
     - Membuktikan secara struktural dan tes regresi bahwa pengubahan `type_users` tidak memengaruhi `roles` / `abilities`, tidak memicu pemetaan ulang berbasis nama, tipe terikat ditolak saat hapus (422), dan tidak membypass perlindungan admin terakhir.
  3. Regresi browser 4 peran diverifikasi penuh di UAT live (`uat.admin`, `uat.operator`, `uat.kepsek`, `uat.waka`): form login → refresh → navigasi izin → ekspor izin → logout bersih.
  4. Panduan manual `PHASE-01B-MANUAL-REVIEW.md` dirapikan: langkah sesi kedaluwarsa, kegagalan jaringan, offline logout, sanitasi URL log email reset, dan penjelasan modal dialog Session Expired.
- Why: memenuhi prasyarat pelengkapan bukti review Fase 01b sebelum peninjauan manual mandiri oleh pemilik.
- Validation:
  - Backend: 60 passed (173 assertions) di SQLite in-memory (`php artisan test`).
  - Pint: PASS (100% clean).
  - Frontend unit tests: 13 passed (`node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`).
  - Build: PASS (`npm run build`).
  - Lint: 0 errors, 1.086 Prettier line-ending warnings dilaporkan terpisah (`npx eslint ...`).
  - Playwright browser UAT: walkthrough 4 peran pada database sintetis `school_access_uat` PASS.
- Notes / risks: BLK-03 tetap OPEN (dialokasikan ke 01c); review manual pemilik PENDING (16 checklist).
- Evidence: `reports/feature-update/PHASE-01B-API-AUTH.md`, `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`.
- Type: Fix, Feature, Security, Test
- Scope / fase: implementasi Fase 01b berdasarkan audit 6a3d71f dan keputusan DEC-15 (Q1–Q3).
- Files touched: `laravel-api/.env.uat.example`, `laravel-api/tests/Feature/Auth/PasswordResetTest.php`, `docs/API-CONTRACTS.md`, `vue-app/src/apiLogin/index.js`, `vue-app/src/main.js`, `vue-app/src/stores/authStore.js`, `vue-app/src/router/index.js`, `vue-app/src/views/pages/auth/Login_2.vue`, `vue-app/src/views/pages/auth/Logout.vue`, `vue-app/src/views/pages/auth/Profile.vue`, `vue-app/src/layout/AppUsers.vue`, `vue-app/src/views/pages/auth/ForgotPassword.vue` (baru), `vue-app/src/views/pages/auth/ResetPassword.vue` (baru), `vue-app/tests/phase-01b-api-auth.test.mjs` (baru), `reports/feature-update/{PHASE-01B-API-AUTH.md,PHASE-01B-MANUAL-REVIEW.md,INDEX.md,HANDOFF.md}`, `roadmap/ROADMAP.md`, `CHANGELOG.md`.
- What changed:
  1. Interceptor dipindahkan ke instance `apiLogin` yang benar (timeout 15.000 ms, withCredentials, withXSRFToken).
  2. Pemulihan CSRF 419 terkoordinasi (single retry setelah penyegaran cookie, koordinasi request bersamaan, payload utuh, tanpa rekursi).
  3. Penanganan sesi aktif kedaluwarsa (401 non-auth) mengalihkan ke `/auth/login` dengan modal banner persisten; request bersamaan dideduplikasi.
  4. Penolakan izin fitur (403) mempertahankan sesi; akun nonaktif mengalihkan ke login.
  5. Router guard dievaluasi pada setiap navigasi langsung maupun antar-child (`router.beforeEach`), memetakan kemampuan per halaman secara eksplisit.
  6. Logout disatukan ke `authStore.logOut()` tunggal (`POST /logout` 204 tanpa membaca body), status server dilaporkan akurat.
  7. Form Forgot Password dan Reset Password end-to-end dibangun (anti-enumerasi, validasi token, broker Laravel, redirect login); UAT memakai `MAIL_MAILER=log`.
  8. Penyelarasan bahasa UI English pada kontrol tersentuh (`Profile.vue`, `Logout.vue`).
- Why: menutup gap konfigurasi API, mencegah kegagalan sesi diam-diam, menutup celah navigasi child, dan melengkapi alur reset password end-to-end sesuai keputusan pemilik.
- Validation:
  - Backend: 55 passed (149 assertions) di SQLite in-memory (`php artisan test`).
  - Pint: PASS (100% clean).
  - Frontend: 10 passed (`node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`).
  - Build: PASS (`npm run build`).
  - Lint: 0 errors (`npx eslint ... --quiet`).
  - Playwright UAT: form login, child guard, CSRF 419 recovery nyata, session expired redirect, forgot/reset password end-to-end via email log, logout 204.
- Notes / risks: BLK-03 tetap OPEN (Fase 01c); review pemilik PENDING.
- Evidence: `reports/feature-update/PHASE-01B-API-AUTH.md`, `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`.

## [2026-10-15] Fase 01b — Docs: audit konfigurasi API dan alur error autentikasi
- Type: Docs
- Scope / fase: audit kondisi aktual dan penyusunan rencana implementasi Fase 01b (implementasi BELUM dijalankan).
- Files touched: `reports/feature-update/{PHASE-01B-API-AUTH-AUDIT.md (baru),INDEX.md,HANDOFF.md}`, `roadmap/ROADMAP.md`, `CHANGELOG.md`.
- What changed:
  1. Audit kondisi aktual pasca-01a: instance axios tunggal `apiLogin` (baseURL dari env, withCredentials/withXSRFToken aktif, tanpa URL hardcode tersisa); ditemukan bahwa response interceptor terdaftar pada global `axios`, bukan instance `apiLogin` → tidak berjalan.
  2. Audit router: `beforeEnter` parent `/` tidak dievaluasi saat navigasi antar-child; gating `/master` (`can('users.view')`) tidak dicek ulang.
  3. Audit error auth: 401/403/419/422/429/5xx/timeout belum ditangani seragam; `Logout.vue` memiliki risiko bug (akses `response.data.id` pada 204, memanggil logout dua kali, helper tak terdefinisi).
  4. Audit kontrak auth: strategi aktif = cookie session Sanctum saja (tanpa bearer); konfigurasi CORS/Sanctum UAT memadai untuk pengujian lokal; tidak perlu mengubah strategi token/sesi.
  5. Audit reset password: backend lengkap (routes+controller+broker+notification), frontend gap (link placeholder tanpa route/view/pemanggilan API). Disusun opsi minimum (Q1–Q4) untuk pemilik.
  6. Rencana implementasi C-01..C-06 dan rencana verifikasi menyeluruh disusun; keputusan penghalang diidentifikasi (Q1: apakah reset password masuk 01b).
  7. Klarifikasi CHANGELOG: entri pemilik ("Pengembangan fitur iteratif via sesi Claude") tidak ditemukan pada working tree maupun history yang diperiksa; penyebab kehilangan belum diketahui dan tidak direkonstruksi tanpa sumber.
  8. Status: 01a tetap DITERIMA pemilik; 01b berstatus audit/rencana (belum implementasi); BLK-03 tetap OPEN dan dialokasikan ke 01c.
- Why: memenuhi prasyarat perencanaan subfase 01b sebelum pelaksanaan kode, sesuai aturan roadmap.
- Validation: source review statis (tidak ada kode/config/data yang diubah; tidak menjalankan test/build/browser pada langkah ini).
- Notes / risks: Implementasi **belum** dimulai; keputusan Q1 pemilik menentukan cakupan reset password; BLK-03 tetap OPEN; tidak ada email reset nyata.
- Evidence: reports/feature-update/PHASE-01B-API-AUTH-AUDIT.md.

## [2026-10-15] Fase 01a — Docs: penerimaan manual pemilik (akses & ekspor) dan penutupan review 01a
- Type: Docs
- Scope / fase: pencatatan penerimaan manual pemilik atas pengujian tambahan akses & ekspor Fase 01a dan penutupan review 01a.
- Files touched: reports/feature-update/{PHASE-01A-ACCESS.md,PHASE-01A-MANUAL-REVIEW.md,INDEX.md,HANDOFF.md}, roadmap/ROADMAP.md, CHANGELOG.md.
- What changed:
  1. Dicatat persetujuan manual pemilik (15 Oktober 2026 WIB, mengacu implementasi `bea4ccd`) atas 8 butir pengujian tambahan: matriks Roles & Abilities English; admin menambah & menyimpan `students.export` untuk Waka; izin tambahan berlaku di sesi Waka berjalan (ekspor berhasil); pencabutan izin berlaku pada request berikutnya (ekspor ditolak 403); ekspor Operator/Principal sesuai filter tahun ajaran dengan nol awal identitas utuh; ekspor melebihi batas ditolak 422 English tanpa CSV terpotong; interaksi topbar tanpa error/warning; UI scope 01a English.
  2. Butir pengujian tambahan dipetakan ke checklist panduan **berdasarkan isi pemeriksaan**, bukan menyamakan nomor (paparan di `PHASE-01A-MANUAL-REVIEW.md` §6 dan `PHASE-01A-ACCESS.md` §22b).
  3. Ditambahkan `PHASE-01A-ACCESS.md` §22; §21c disesuaikan agar tidak lagi menyebut checklist sebagai tertunda (ditutup pada §22).
  4. Seluruh nomor checklist panduan #1–#14 kini APPROVED; Fase 01a ditandai **DITERIMA** dalam scope checklist. INDEX, HANDOFF, dan ROADMAP disinkronkan.
- Why: menutup review Fase 01a sesuai bukti persetujuan manual pemilik, tanpa memperluas ke subfase lain/deployment.
- Validation: dokumentasi saja (tidak ada perubahan kode). Tidak mengulang tes/build sesuai instruksi pemilik.
- Notes / risks: Penerimaan 01a **bukan** penerimaan seluruh Fase 01 (01b/01c NOT STARTED) dan bukan izin deployment/modul belum tersedia. **BLK-03** dan keputusan autentikasi lain tetap **OPEN**.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md §22; reports/feature-update/PHASE-01A-MANUAL-REVIEW.md §4/§6.

## [2026-10-15] Fase 01a — Docs: persetujuan manual pemilik atas retest login
- Type: Docs
- Scope / fase: pencatatan persetujuan manual pemilik atas retest login Fase 01a nomor 1–7.
- Files touched: reports/feature-update/{PHASE-01A-ACCESS.md,PHASE-01A-MANUAL-REVIEW.md,INDEX.md,HANDOFF.md}, roadmap/ROADMAP.md, CHANGELOG.md.
- What changed:
  1. Dicatat persetujuan manual pemilik (15 Oktober 2026 WIB, mengacu implementasi `5f4308d`) untuk retest login: Operator login langsung ke Dashboard tanpa refresh/error console; refresh mempertahankan sesi; profil Operator menampilkan identitas & peran; logout kembali ke halaman login; Admin login dengan menu & widget pengguna sesuai; akun nonaktif ditolak dengan pesan English; password salah ditolak dengan pesan English.
  2. Ditambahkan bagian §21 pada `PHASE-01A-ACCESS.md` yang membedakan **bukti manual pemilik** dari **verifikasi otomatis agen**, menegaskan batas persetujuan (hanya alur login/sesi), dan mendaftar checklist yang masih tertunda.
  3. Panduan review manual `PHASE-01A-MANUAL-REVIEW.md` diberi kolom status (APPROVED/PENDING) per nomor.
  4. INDEX, HANDOFF, dan ROADMAP diperbarui ke status penerimaan parsial (login APPROVED; izin/ekspor PENDING).
- Why: mencatat bukti persetujuan manual pemilik secara akurat tanpa memperluasnya ke seluruh Fase 01a.
- Validation: dokumentasi saja (tidak ada perubahan kode). Tidak mengulang tes/build sesuai instruksi pemilik.
- Notes / risks: Persetujuan **terbatas** pada alur login; kontrol izin, ekspor, batas ekspor, dan modul lain **tetap PENDING**. **BLK-03** dan keputusan autentikasi lain tetap **OPEN**.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md §21; reports/feature-update/PHASE-01A-MANUAL-REVIEW.md §4/§6.

## [2026-10-15] Fase 01a — Fix: inisialisasi identitas pasca-login (Login_2.vue & authStore)
- Type: Fix, Security, Test
- Scope / fase: koreksi inisialisasi identitas pasca-login pada Fase 01a tanpa reload/polling/penambahan izin.
- Files touched:
  - `vue-app/src/stores/authStore.js`
  - `vue-app/src/views/pages/auth/Login_2.vue`
  - `vue-app/tests/auth-race-condition.test.mjs`
  - `laravel-api/app/Http/Requests/Auth/LoginRequest.php`
  - `laravel-api/app/Http/Middleware/EnsureUserIsActive.php`
  - `laravel-api/tests/Feature/Auth/AuthenticationTest.php`
  - `.gitignore`
  - `reports/feature-update/{PHASE-01A-ACCESS.md,PHASE-01A-MANUAL-REVIEW.md,INDEX.md,HANDOFF.md}`
  - `roadmap/ROADMAP.md`
  - `CHANGELOG.md`
- What changed:
  1. Investigasi & pembuktian akar masalah: `Login_2.vue` mengakses properti usang `authStore().authUser` yang telah dihapus pada refactor sebelumnya, menyebabkan pelemparan exception "User data not found." dan modal "Failed to validate user session." padahal backend telah sukses mengautentikasi (sehingga refresh manual berhasil masuk).
  2. `authStore.js`: menambahkan getter kompatibilitas `authUser` dan `AllUserAssign` yang konsisten merujuk ke `identity`.
  3. `authStore.js`: menambahkan sequence tracker `_requestId` pada `fetchIdentity()` untuk mendiskualifikasi respons in-flight guest check yang datang terlambat agar tidak menimpa sesi login aktif (anti-race condition deterministik). `fetchIdentity()`, `getUser()`, dan `checkLogin()` mengembalikan data `identity`.
  4. `Login_2.vue`: mengintegrasikan pencegahan submit ganda (`isSubmitting` guard + disabled/loading button), explicit boolean dialog flags, dan `await useAuthStore.fetchIdentity()` sebelum navigasi.
  5. Pesan error English per jenis kegagalan: koneksi API server, kredensial salah (422), akun nonaktif (422), dan sesi tidak valid. Pesan nonaktif di `LoginRequest.php` dan `EnsureUserIsActive.php` diselaraskan ke English.
- Why: memastikan pengguna dapat langsung login ke beranda tanpa perlu refresh manual, bebas race condition, dan tanpa memanggil endpoint terproteksi `/api/users`.
- Validation:
  - `php artisan test` backend: 51 passed (131 assertions) di SQLite in-memory terisolasi (2 tes baru).
  - Pint style check: PASS pada seluruh file PHP yang diubah.
  - `node --test vue-app/tests/auth-race-condition.test.mjs`: 3 passed (race condition & getter consistency).
  - `npm run build`: PASS (`✓ built in 42s`).
  - `npx eslint`: 0 errors pada berkas yang disentuh.
  - Playwright browser UAT sintetis: Form login Operator, Admin, Principal, Wakil Kesiswaan langsung berhasil ke Dashboard tanpa refresh; refresh menjaga sesi; logout dan switch account bersih; password salah menampilkan error 422 English; akun nonaktif menampilkan error 422 English. Request `/api/users` pada non-admin = 0.
- Notes / risks: BLK-03 tetap OPEN (`DatabaseSeeder` dilarang); penerimaan manual pemilik PENDING.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md §20; `vue-app/tests/auth-race-condition.test.mjs`.

## [2026-10-15] Fase 01a — Fix: pemisahan identitas pengguna dari administrasi akun
- Type: Fix, Security, Refactor
- Scope / fase: perbaikan defect UAT akun operator pada Fase 01a.
- Files touched: vue-app/src/stores/authStore.js, vue-app/src/components/dashboard/ListUsersOnlineWidget.vue, vue-app/src/layout/AppUsers.vue, vue-app/src/layout/AppMenu.vue, vue-app/src/router/index.js, vue-app/src/apiLogin/index.js, vue-app/src/main.js, vue-app/src/utils/useResourceManager.js, vue-app/src/views/pages/auth/Profile.vue, vue-app/src/views/pages/auth/Login_2.vue, vue-app/package.json, reports/feature-update/{PHASE-01A-ACCESS.md,PHASE-01A-MANUAL-REVIEW.md,INDEX.md,HANDOFF.md}, roadmap/ROADMAP.md.
- What changed:
  1. Identitas sesi dialihkan ke kontrak ringan `GET /api/check-auth` (`authStore.fetchIdentity()`); menghapus pemanggilan `GET /api/users` untuk mencari profil sendiri.
  2. `ListUsersOnlineWidget.vue` diproteksi `v-if="canViewUsers"`, tidak memanggil `/api/users` jika operator tidak memiliki `users.view`; error ditangani tanpa unhandled promise.
  3. `AppUsers.vue` membaca nama dan peran sesi langsung dari store identitas; logout berbasis sesi backend `POST /logout` tanpa ketergantungan ID dari daftar pengguna.
  4. State sesi dibersihkan saat logout (`$reset()`) untuk mencegah kebocoran state antar-akun.
  5. `AppMenu.vue` beralih ke gating kemampuan bernama (`requires`), menghapus pemeriksaan numerik inline `type_user_id`.
  6. `main.js` meregistrasikan directive PrimeVue `StyleClass` (`v-styleclass` tanpa warning).
  7. `apiLogin/index.js` menghapus fallback IP lama; error konfigurasi English jika `VITE_API_BASE_URL` hilang/tidak valid; membedakan network error dari session expired.
  8. `Login_2.vue` membedakan network error dari invalid credentials (422).
  9. `package.json` menambahkan script `"dev:uat"`.
- Why: memperbaiki defect console error 403, TypeError null render, dan failure logout pada operator saat UAT, tanpa mengorbankan keamanan backend.
- Validation: automated test 49 passed (119 assertions); frontend build PASS; lint changed files 0 error; UAT browser walkthrough pada 4 peran (Operator, Admin, Principal, Waka) PASS dengan 0 console error dan 0 `/api/users` calls pada non-admin; topbar buttons PASS tanpa directive warning.
- Notes / risks: BLK-03 tetap OPEN; penerimaan manual pemilik PENDING.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md §19; reports/feature-update/evidence/{uat-operator-dashboard.png, uat-operator-profile-dropdown.png}.

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
