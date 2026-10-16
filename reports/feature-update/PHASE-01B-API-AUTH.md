# Laporan Fase 01b — Konfigurasi API, alur error autentikasi, dan reset password

- Tanggal WIB: 2026-10-16
- Mode: **pelengkapan bukti implementasi & audit akses** (backend Laravel + frontend Vue SPA + browser UAT lokal)
- Scope sesi: Pelengkapan 6 butir pemetaan bukti recovery/error auth, audit akses Operator ke `/master/typeusers`, regresi browser 4 peran, lint/build baru, dan perapian panduan manual UAT.
- Branch / baseline SHA: `main` @ `10bf3ed`.
- Lingkungan: Windows, PHP 8.1.25, Composer 2.8.2, Node 24.18.0, npm 11.4.2, MySQL 3306. UAT: SPA `127.0.0.1:4001`, API `127.0.0.1:8001`, DB `school_access_uat`. DB unit test backend = SQLite in-memory `:memory:`.
- Status teknis: **IMPLEMENTED & FULLY VERIFIED**.
- Status penerimaan manual: **PENDING** (menunggu review/persetujuan manual pemilik atas checklist UAT).

---

## 1. Outcome dan Cakupan Sesi Ini

1. **Pemetaan Bukti Recovery & Error Auth (6 Skenario)**:
   - Request hasil retry kembali 419: berhenti seketika tanpa infinite loop.
   - Refresh `csrf-cookie` gagal: terdeteksi rapi tanpa loop dan tanpa me-retry request gagal.
   - Beberapa request 419 bersamaan: dikoordinasikan ke tepat 1 request refresh `csrf-cookie`.
   - Timeout / network failure: tanpa auto-retry dan tanpa penghapusan sesi lokal.
   - Logout server gagal: state lokal dibersihkan dengan notifikasi akurat bahwa server belum mengonfirmasi pengakhiran sesi.
   - 401 bersamaan & pergantian akun: deduplikasi penanganan sesi kedaluwarsa serta nomor urut request monotonik global mencegah respon usang menimpa sesi baru.

2. **Audit Akses Operator ke `/master/typeusers`**:
   - Kemampuan: `master.manage` (tulis) dan `master.view` (baca).
   - Operasi backend tersedia: `GET /api/type-users`, `GET /api/type-users/{id}`, `POST /api/type-users`, `PUT/PATCH /api/type-users/{id}`, `DELETE /api/type-users/{id}`.
   - Struktur independen: `type_users` adalah tabel referensi profil legacy, sepenuhnya terpisah dari tabel `roles`, `abilities`, `role_user`, dan `ability_role`.
   - Verifikasi regresi membuktikan: pengubahan data `type_users` TIDAK mengubah role/kemampuan pengguna, TIDAK memicu pemetaan ulang berbasis nama, tipe yang masih berelasi ke pengguna DITOLAK saat dihapus (422), dan TIDAK dapat membypass perlindungan admin terakhir.

3. **Regresi Browser Empat Peran**:
   - Berhasil diverifikasi live pada 4 peran (`uat.admin`, `uat.operator`, `uat.kepsek`, `uat.waka`) melalui browser nyata: login form → refresh → navigasi izin → ekspor izin → logout bersih.

---

## 2. File yang Disentuh

| File | Perubahan |
|---|---|
| `laravel-api/tests/Feature/Access/TypeUserAccessTest.php` (baru) | 5 tes regresi untuk izin `master.manage`, integritas relasi 422, isolasi kemampuan, dan perlindungan admin terakhir. |
| `vue-app/src/stores/authStore.js` | Monotonic request sequence global (`requestSequence`) untuk mencegah tabrakan ID setelah `$reset()` pada pergantian akun. |
| `vue-app/tests/phase-01b-api-auth.test.mjs` | Penambahan Test 2b (retry 419 berulang berhenti tanpa loop) dan Test 2c (refresh CSRF gagal ditangani bersih), serta asersi sesi aman pada Test 1. |
| `vue-app/tests/auth-race-condition.test.mjs` | Penambahan tes anti-race pada pergantian akun (respon lambat akun lama tidak menimpa akun baru). |
| `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md` | Panduan UAT dirapikan: langkah sesi berakhir, kegagalan jaringan, logout offline, sanitasi log email reset, dan penjelasan modal Session Expired. |
| `reports/feature-update/PHASE-01B-API-AUTH.md` | Laporan bukti lengkap Fase 01b. |
| `reports/feature-update/HANDOFF.md` | Pembaruan checkpoint sesi dan status checklist. |
| `reports/feature-update/INDEX.md` | Sinkronisasi indeks dokumen. |
| `roadmap/ROADMAP.md` | Penegasan status 01b (menunggu review pemilik) dan BLK-03 tetap OPEN. |
| `CHANGELOG.md` | Pencatatan entri riwayat pengujian dan pengamanan autentikasi 01b. |

---

## 3. Pemetaan Bukti Skenario Recovery & Error Autentikasi

| No | Skenario | Nama Tes / Bukti | Perilaku yang Dibuktikan | Batas / Catatan |
|---|---|---|---|---|
| 1 | **Request hasil retry kembali 419** | `vue-app/tests/phase-01b-api-auth.test.mjs` → `419 retry returning 419 again stops immediately without loop and notifies mismatch` | Saat request yang sudah di-retry (`_retry: true`) kembali menerima 419 dari server, interceptor tidak memanggil refresh token lagi. Interceptor langsung memicu notifikasi `Security token mismatch` dan me-reject promise. | Berlaku pada instance `apiLogin`. Menghentikan infinite retry loop bila server terus menolak token. |
| 2 | **Refresh csrf-cookie gagal** | `vue-app/tests/phase-01b-api-auth.test.mjs` → `419 recovery stops and notifies when csrf-cookie refresh fails` | Jika request `GET /sanctum/csrf-cookie` gagal (network error / server drop), interceptor menangkap kegagalan, memicu notifikasi `Security validation expired`, tidak me-retry request asli, dan me-reject promise. | Mencegah aplikasi mencoba mengulang request dengan token usang saat backend tidak merespons. |
| 3 | **Beberapa request 419 bersamaan** | `vue-app/tests/phase-01b-api-auth.test.mjs` → `419 CSRF recovery refreshes token once, coordinates concurrent requests, and retries once` | Tiga request paralel yang serentak menerima 419 dikoordinasikan via `csrfRefreshPromise` tunggal. `GET /sanctum/csrf-cookie` hanya dipanggil tepat satu kali, kemudian seluruh request diulang dengan payload utuh. | Bukti runtime UAT: request 585 (419) → request 586 (`csrf-cookie` 204) → request 587 (200 OK). |
| 4 | **Timeout / network failure tanpa auto-retry & tanpa hapus sesi** | `vue-app/tests/phase-01b-api-auth.test.mjs` → `interceptor classifies network error and timeout without auto-retry and preserves session` | Error `ECONNABORTED` / connection drop menampilkan toast English tanpa me-retry otomatis. State identitas pengguna di store tetap aktif dan tidak dihapus semata-mata karena gangguan jaringan. | Berbeda dari 401 (sesi kedaluwarsa). Penanganan network drop membiarkan pengguna mencoba kembali tanpa kehilangan sesi kerja. |
| 5 | **Logout server gagal tanpa klaim sukses backend** | `vue-app/tests/phase-01b-api-auth.test.mjs` → `logout handles 204 without reading body, cleans state, and reports server status` | Jika `POST /logout` gagal (mis. status 500 atau network down), store membersihkan state lokal demi keamanan pengguna namun mengembalikan `serverSuccess: false`. UI menampilkan bahwa sesi lokal dibersihkan tetapi server gagal dihubungi. | Bukti UAT: `AppUsers.vue` menampilkan dialog "Signed out locally. Could not reach server to terminate session." jika server mati. |
| 6 | **401 bersamaan & respons sesi lama setelah pergantian akun** | `vue-app/tests/phase-01b-api-auth.test.mjs` → `401 on active session deduplicates notifications...` & `vue-app/tests/auth-race-condition.test.mjs` → `stale response from prior account does not overwrite newly logged in account on account switch` | 1) Beberapa 401 serentak hanya memicu 1 kali notifikasi dan 1 kali pengalihan. 2) Ketika pengguna beralih akun (Akun A logout → Akun B login), respon in-flight tertunda milik Akun A dibuang dan tidak menimpa sesi Akun B berkat monotonic `requestSequence`. | Request guest `/check-auth` dan `/login` dikecualikan dari notifikasi kedaluwarsa untuk mencegah loop. |

---

## 4. Audit Akses Operator ke `/master/typeusers`

### A. Kemampuan yang Mengizinkan dan Operasi Backend
- **Kemampuan Penegak**:
  - Halaman SPA `/master/typeusers`: dilindungi guard `meta: { requiresAbility: 'master.manage' }` pada `router/index.js` dan menu navigasi `AppMenu.vue`.
  - Backend `laravel-api/routes/api.php`:
    - `GET /api/type-users` & `GET /api/type-users/{id}`: dilindungi `can:master.view`.
    - `POST /api/type-users`, `PUT/PATCH /api/type-users/{id}`, `DELETE /api/type-users/{id}`: dilindungi `can:master.manage`.
- **Operasi yang Tersedia untuk Operator Sekolah**:
  - Sesuai `DEC-03` dan `AbilityCatalog::initialRoles()`, peran `operator-sekolah` memiliki kemampuan `master.view` dan `master.manage`. Oleh karena itu, Operator dapat melihat daftar, menambah tipe baru, mengubah nama tipe, dan menghapus tipe pengguna yang tidak terikat.

### B. Analisis Risiko Keamanan & Eskalasi Hak Akses
1. **Pemisahan Peran dan Kemampuan dari `type_users`**:
   - Peran dan kemampuan ditegakkan melalui tabel baru `roles`, `abilities`, `ability_role`, dan `role_user` (Fase 01a).
   - Hak akses pengguna dievaluasi dari relasi `$user->roles()->with('abilities')` via `User::abilityNames()`. Kolom `type_user_id` sama sekali **tidak dibaca** saat mengevaluasi otorisasi gate atau middleware.
2. **Pemetaan Ulang Berdasarkan Nama**:
   - Pemetaan nama legacy (`type_users.name = 'Admin'` → `admin-sistem`) hanya dijalankan satu kali saat migrasi data awal `2029_07_20_000003_seed_and_map_roles.php`.
   - Tidak ada kode runtime pada controller, model, middleware, atau provider yang melakukan pemetaan ulang peran berdasarkan nama `type_users`. Mengubah nama tipe pengguna di tabel `type_users` tidak memberi hak akses baru ke akun mana pun.
3. **Perlindungan Terhadap Akun dan Admin Terakhir**:
   - Operator Sekolah **tidak memiliki** kemampuan `users.manage` maupun `access.assign`.
   - Endpoint pembaruan akun pengguna (`PUT /api/users/{id}`) mengabaikan input `type_user_id` bila dipanggil oleh actor tanpa `users.manage` (`UserController.php`).
   - Tipe pengguna yang masih dirujuk oleh akun pengguna (misal tipe `Admin` atau `User`) **tidak dapat dihapus**: `TypeUserController::destroy` menghitung `$typeUser->users_count` dan menolak penghapusan dengan status **422** (`Cannot delete! This record is still being referenced by X users.`).
   - Perlindungan admin pengelola akses terakhir (`UserController::isLastAccessManager`) berbasis pada kemampuan `roles.manage`/`abilities.manage`/`access.assign`, bukan pada `type_users`.
- **Kesimpulan Audit**: Tidak ditemukan celah eskalasi hak akses atau bypass proteksi admin melalui modul `type_users`. Matriks izin tetap dipertahankan sesuai keputusan pemilik tanpa perubahan yang tidak berdasar.

---

## 5. Hasil Regresi Browser Empat Peran (Server UAT Live)

Pengujian dilakukan menggunakan browser otomatis Playwright pada server UAT nyata (`127.0.0.1:4001` SPA dan `127.0.0.1:8001` API) dengan database sintetis `school_access_uat`.

| Peran & Akun Uji | Login Form | Refresh Halaman | Navigasi Izin | Ekspor Siswa | Logout Bersih | Console / Error Log |
|---|---|---|---|---|---|---|
| **System Administrator** (`uat.admin`) | Berhasil masuk ke `/` | State sesi & kemampuan lengkap utuh | Masuk `/master/roles` (OK), masuk `/master/accounts` (OK), masuk `/master/typeusers` (OK) | Tombol Export muncul di `/public/students`; file CSV terunduh | Berhasil logout via `/auth/logout`, sesi backend 204 | 0 uncaught errors, 0 page errors |
| **School Operator** (`uat.operator`) | Berhasil masuk ke `/` | State sesi & kemampuan operasional utuh | Masuk `/master/typeusers` (OK); masuk `/master/roles` dialihkan ke `/auth/access` (Access Denied) | Tombol Export muncul di `/public/students`; file CSV terunduh | Berhasil logout via menu topbar profil, sesi backend 204 | 0 uncaught errors, 1 warning (expected route guard denial) |
| **Principal** (`uat.kepsek`) | Berhasil masuk ke `/` | State sesi & kemampuan persetujuan utuh | Masuk `/public/students` (OK); masuk `/master/roles` & `/master/typeusers` dialihkan ke `/auth/access` | Tombol Export muncul di `/public/students`; file CSV terunduh | Berhasil logout via `/auth/logout`, sesi backend 204 | 0 uncaught errors, 2 warnings (expected route guard denials) |
| **Vice Principal** (`uat.waka`) | Berhasil masuk ke `/` | State sesi & kemampuan utuh | Masuk `/public/students` (OK); masuk `/master/roles` & `/master/typeusers` dialihkan ke `/auth/access` | Tombol Export **disembunyikan**; pemanggilan API langsung menghasilkan **403 Forbidden** | Berhasil logout via `/auth/logout`, sesi backend 204 | 0 uncaught errors, 2 warnings (expected route guard denials) |

### Verifikasi Sesi Berakhir Nyata & Pengecualian Check-Auth:
1. Sesi backend diakhiri secara terpisah saat pengguna berada di Dashboard.
2. Pengguna memicu navigasi ke `/public/students`. Request API merespons **401 Unauthorized**.
3. Interceptor `apiLogin` mendeteksi 401 pada protected endpoint → mengarahkan browser ke `/auth/login`.
4. Di `/auth/login`, modal dialog PrimeVue berbingkai "Session Expired" dengan isi "Your session has expired. Please sign in again." langsung ditampilkan.
5. Request background guest `/api/check-auth` merespons 200 `{ authenticated: false }` dengan `skipAuthRedirect: true`, sehingga form login tetap tenang tanpa terjadi reload loop.

---

## 6. Bukti Verifikasi Otomatis, Lint, dan Build

### A. Backend Test Suite (DB SQLite In-Memory Terisolasi)
- Perintah: `php artisan test`
- Hasil: **60 passed (173 assertions)**.
- Penambahan 5 tes baru pada `Tests\Feature\Access\TypeUserAccessTest`.
- Style check: `./vendor/bin/pint --test tests/Feature/Access/TypeUserAccessTest.php` → **PASS** (100% clean).

### B. Frontend Automated Test Runner (Node.js Test Runner)
- Perintah: `node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`
- Hasil: **13 passed (0 failed)**.
- Mencakup pemulihan 419 single & double failure, 401 deduplication, anti-race pergantian akun, route guard, dan 403 separation.

### C. Lint Non-Mutasi & Build Frontend
- Lint: `npx eslint src/apiLogin/index.js src/main.js src/stores/authStore.js src/router/index.js src/views/pages/auth/Login_2.vue src/views/pages/auth/Logout.vue src/views/pages/auth/Profile.vue src/layout/AppUsers.vue src/views/pages/auth/ForgotPassword.vue src/views/pages/auth/ResetPassword.vue`
  - **Errors**: **0**.
  - **Warnings**: 1.086 (seluruhnya merupakan peringatan format Prettier baris akhir CRLF `Delete ␍`; tidak dijalankan `--fix` massal untuk menjaga kebersihan diff).
- Build: `npm run build` → **PASS** (`✓ built in 1m`, 0 errors).

---

## 7. Status Keputusan & Blocker

- **DEC-15**: **APPROVED** (2026-10-16 WIB).
- **Fase 01a**: **DITERIMA pemilik** (2026-10-15 WIB).
- **Fase 01b**: **SELESAI DIIMPLEMENTASIKAN & DIVERIFIKASI PENUH**; checklist manual di `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md` berstatus **PENDING** menunggu review mandiri pemilik.
- **BLK-03**: **TETAP OPEN** (`DatabaseSeeder` berisi identitas nyata; dialokasikan ke Fase 01c). Tidak ada pengujian yang menyentuh atau menjalankan `DatabaseSeeder`.
