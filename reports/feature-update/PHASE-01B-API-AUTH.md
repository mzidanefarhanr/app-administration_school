# Laporan Fase 01b — Konfigurasi API, alur error autentikasi, dan reset password

- Tanggal WIB: 2026-10-16
- Mode: **implementasi + verifikasi** (backend Laravel + frontend Vue SPA + lingkungan UAT lokal terisolasi)
- Scope instruksi pemilik: implementasi C-01 sampai C-06 berdasarkan audit `6a3d71f` dan keputusan pemilik Q1–Q3 (DEC-15, 2026-10-16 WIB).
- Branch / baseline SHA: `main` @ `5eaf444` (commit keputusan).
- Working tree sebelum: `modified` 10 file (kode), `untracked` 3 file baru + `console.txt`.
- Lingkungan: Windows, PHP 8.1.25, Composer 2.8.2, Node 24.18.0, npm 11.4.2, MySQL 3306. UAT: SPA `127.0.0.1:4001`, API `127.0.0.1:8001`, DB `school_access_uat`. DB pengujian unit backend = SQLite in-memory `:memory:`.
- Status teknis: **IMPLEMENTED & VERIFIED**.
- Status review: **PENDING** (menunggu review/persetujuan manual pemilik atas implementasi).

---

## 1. Outcome dan cakupan implementasi

### A. Interceptor terpusat pada instance `apiLogin` (C-01 & C-02)
- Interceptor respons dipindahkan dari global `axios` ke instance `apiLogin` (instance yang dipakai seluruh pemanggilan API aplikasi).
- Ditetapkan timeout 15.000 ms (15 detik) dengan penanganan `ECONNABORTED` / timeout error tanpa auto-retry.
- Error tanpa response (koneksi putus / server offline) menampilkan pesan English tanpa auto-retry dan tanpa crash.
- Error 429 Too Many Requests menampilkan pesan English terpusat.
- Error 5xx Server Error menampilkan pesan English seragam tanpa membocorkan trace atau informasi sensitif backend.
- Status 422 diteruskan langsung ke pemanggil form domain-specific.

### B. Pemulihan CSRF 419 terkoordinasi (Q3 / DEC-15)
- Pada respons 419 yang berasal dari penolakan CSRF, interceptor menyegarkan `GET /sanctum/csrf-cookie` tepat sekali.
- Permintaan refresh dikoordinasikan via `csrfRefreshPromise` tunggal: bila ada beberapa request yang gagal 419 bersamaan, hanya ada satu pemanggilan ke `/sanctum/csrf-cookie`.
- Setelah refresh berhasil, header `X-XSRF-TOKEN` diperbarui dengan cookie segar dari browser dan request asli diulang (`_retry = true`) dengan payload dan method utuh.
- Request `/sanctum/csrf-cookie` sendiri dikecualikan dari interceptor retry untuk mencegah rekursi tak terbatas.
- Bila retry tetap gagal, interceptor berhenti dan menampilkan pesan pemulihan yang jelas tanpa reload loop.
- **Diverifikasi pada server UAT hidup**: `POST /api/firstlogin/1` dengan cookie CSRF rusak sengaja diuji → backend mengembalikan 419 → interceptor mengambil cookie 204 → mengulang request 200 OK.

### C. Penanganan sesi aktif kedaluwarsa (401) & penolakan izin (403) (Q2 / DEC-15)
- **401 pada request selain auth**: saat sesi aktif kedaluwarsa di backend, state lokal dibersihkan, pesan "Your session has expired. Please sign in again." disimpan di `sessionStorage`, notifikasi ditampilkan, dan pengguna dialihkan ke `/auth/login`. Dialog sesi kedaluwarsa otomatis ditampilkan di halaman login.
- **Deduplikasi 401**: beberapa request 401 bersamaan dideduplikasi via flag `isSessionExpiredHandled` (hanya satu notifikasi dan satu navigasi).
- **Pengecualian pemeriksaan guest**: endpoint `check-auth`, `/login`, `/sanctum/csrf-cookie`, dan request dengan `skipAuthRedirect: true` tidak memicu pengalihan sesi kedaluwarsa.
- **403 Izin Fitur**: penolakan izin fitur (mis. kurang kemampuan `roles.manage` atau `students.export`) **TIDAK** mengeluarkan pengguna atau membersihkan sesi. Error diteruskan ke pemanggil.
- **403 Akun Nonaktif**: jika 403 berasal dari akun nonaktif (middleware `EnsureUserIsActive`), pengguna dialihkan ke login dengan pesan English.

### D. Konsistensi logout & router guard (C-03 & C-04)
- **Logout**: disatukan ke `authStore.logOut()` tunggal (`POST /logout`). Respons 204 No Content diproses tanpa membaca body. Status keberhasilan server dilaporkan (`serverSuccess`), dan jika server offline pengguna diinfokan bahwa sesi backend belum tentu berakhir sementara state lokal dibersihkan. `Logout.vue` disederhanakan menjadi pemanggil store tunggal dengan UI English.
- **Router Guard Global (`router.beforeEach`)**:
  - Dijalankan pada **setiap** navigasi, termasuk navigasi langsung (URL) dan navigasi antar-child route.
  - Memetakan kemampuan bernama per halaman secara eksplisit:
    - `/master/accounts`: `users.view`
    - `/master/typeusers`: `master.manage`
    - `/master/roles`: `roles.view`
    - `/public/students`: `students.view`
    - `/public/educationlevels`, `/public/educationschools`, `/public/phasestatuses`, `/public/professions`, `/public/schoolrombels`, `/public/schoolyears`, `/public/studentmajors`: `master.view`
  - Pengguna terautentikasi dilarang membuka halaman guest-only (`/auth/login`, `/auth/forgot-password`, `/password-reset/:token`) dan dialihkan ke `/`.
  - Pengguna belum login dialihkan ke `/auth/login` bila mencoba membuka halaman terproteksi.
  - Pengguna tanpa kemampuan spesifik dialihkan ke `/auth/access`.

### E. Form Forgot Password & Reset Password (C-05 / Q1 / DEC-15)
- Dibuat rute dan komponen baru:
  - `/auth/forgot-password` (`ForgotPassword.vue`): form meminta email terdaftar, memanggil `POST /forgot-password`. Menampilkan konfirmasi anti-enumerasi seragam ("If your email address is registered, you will receive a password reset link shortly.") sehingga tidak membocorkan keberadaan email di database.
  - `/password-reset/:token` dan `/auth/reset-password/:token` (`ResetPassword.vue`): form menyetel password baru (email terisi dari query, password baru min 8 karakter + konfirmasi), memanggil `POST /reset-password`. Berhasil mereset password, menginvalidasi token lama, dan mengarahkan ke `/auth/login`.
- Tautan "Forgot Password?" di `Login_2.vue` dihubungkan ke `/auth/forgot-password`.
- Lingkungan UAT disetel `MAIL_MAILER=log` pada `laravel-api/.env.uat` dan `.env.uat.example`: email reset dicetak ke `storage/logs/laravel.log` tanpa mengirim email nyata ke internet.
- **Diverifikasi end-to-end**: minta link via form → link diambil dari `laravel.log` → form reset dibuka → password baru disimpan → login dengan password lama ditolak → login dengan password baru berhasil masuk Dashboard.

### F. Penyelarasan UI English (C-06)
- Kontrol ubah password di `Profile.vue` dan layar `Logout.vue` diselaraskan ke English.

---

## 2. File yang disentuh

| File | Perubahan |
|---|---|
| `docs/DECISIONS.md` | Dicatat DEC-15 (APPROVED 2026-10-16 WIB). |
| `docs/API-CONTRACTS.md` | Dicatat kontrak error, CSRF retry, router guard, dan reset password 01b. |
| `laravel-api/.env.uat.example` | Ditambahkan `MAIL_MAILER=log`. |
| `laravel-api/.env.uat` (untracked) | Ditambahkan `MAIL_MAILER=log`. |
| `laravel-api/tests/Feature/Auth/PasswordResetTest.php` | Ditambahkan 4 tes baru: token invalid, expired, reused, dan validasi password/konfirmasi. |
| `vue-app/src/apiLogin/index.js` | Interceptor dipindah ke `apiLogin`, retry 419 single coordinated, deduplikasi 401, timeout 15s, pembedaan 403, registrasi handler terpisah. |
| `vue-app/src/main.js` | Mendaftarkan handler sesi kedaluwarsa & nonaktif ke `apiLogin` tanpa circular dependency. |
| `vue-app/src/stores/authStore.js` | `loginTimestamp` untuk anti-race sesi lama, `skipAuthRedirect` pada `fetchIdentity`, `logOut()` 204 bersih dengan pelaporan `serverSuccess`. |
| `vue-app/src/router/index.js` | `router.beforeEach` global (child & direct navigation), pemetaan `requiresAbility` per halaman, rute forgot & reset password. |
| `vue-app/src/views/pages/auth/Login_2.vue` | Tautan Forgot Password aktif, pengecekan flash message sesi kedaluwarsa pada mount. |
| `vue-app/src/views/pages/auth/Logout.vue` | Disatukan ke single store `logOut()`, UI English, redirect bersih. |
| `vue-app/src/views/pages/auth/Profile.vue` | Kontrol form ubah password diselaraskan ke English. |
| `vue-app/src/layout/AppUsers.vue` | Pelaporan status server logout yang akurat. |
| `vue-app/src/views/pages/auth/ForgotPassword.vue` (baru) | Form permintaan link reset password English dengan proteksi anti-enumerasi. |
| `vue-app/src/views/pages/auth/ResetPassword.vue` (baru) | Form input password baru dan konfirmasi dengan validasi token dan redirect ke login. |
| `vue-app/tests/phase-01b-api-auth.test.mjs` (baru) | 7 unit test deterministik untuk interceptor, 419 CSRF retry, 401 deduplication, 403 separation, router guard child, dan logout contract. |

---

## 3. Bukti Verifikasi Otomatis & Runtime

### A. Backend Test Suite (PHPUnit / Pest di DB SQLite In-Memory)
- Perintah: `php artisan test`
- Hasil: **55 passed (149 assertions)**.
- Cakupan:
  - 6 tes pada `PasswordResetTest` (link requested, valid reset, invalid token rejected, expired token rejected, reused token rejected, confirmation mismatch & short password rejected).
  - 6 tes autentikasi & logout.
  - 23 tes akses peran/kemampuan & enforcement.
  - 13 tes ekspor siswa (filter, leading zero, delimiter, injection, limit 422).
  - 2 tes isolasi database SQLite `:memory:`.
  - 2 tes email verification & 1 registration closed.
- Style check: `./vendor/bin/pint --test` → **PASS** (100% clean).

### B. Frontend Automated Unit & Integration Tests (Node Test Runner)
- Perintah: `node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`
- Hasil: **10 passed (0 failed)**.
- Pengujian deterministik mencakup:
  1. Klasifikasi network error dan timeout tanpa auto-retry.
  2. CSRF 419 single recovery: menyegarkan token sekali, mengoordinasikan 3 request bersamaan ke 1 call refresh, dan mengulang dengan payload utuh.
  3. Mencegah rekursi bila 419 terjadi pada endpoint `/sanctum/csrf-cookie` itu sendiri.
  4. Deduplikasi 401: 3 request bersamaan hanya memicu 1 kali penanganan sesi; request guest/login dikecualikan.
  5. 403 izin fitur mempertahankan sesi lokal; 403 nonaktif memicu logout.
  6. Evaluasi route guard per halaman dan pada transisi antar-child (Operator dilarang ke roles/accounts tapi diizinkan ke typeusers; Admin diizinkan ke roles; guest dialihkan ke login).
  7. Logout 204 diproses tanpa membaca body, state lokal dibersihkan, status server dilaporkan benar.
  8. Getter identitas `user`, `authUser`, `AllUserAssign` konsisten.
  9. Respons guest lambat tidak menimpa sesi login aktif.
  10. Respons error usang tidak menimpa sesi login aktif.

### C. Build & Lint Frontend
- Perintah: `npm run build` → **PASS** (`✓ built in 18.72s`, 0 errors, 0 warnings).
- Perintah: `npx eslint ... --quiet` pada seluruh berkas yang disentuh → **0 errors**.

### D. Verifikasi Playwright Browser pada Server UAT Nyata (DB `school_access_uat`)
1. **CSRF 419 Nyata**:
   - Cookie `XSRF-TOKEN` sengaja dirusak dengan token invalid.
   - Request `POST /api/firstlogin/1` dikirim → server mengembalikan **HTTP 419**.
   - Interceptor mendeteksi 419 → memanggil `GET /sanctum/csrf-cookie` (204) → header `X-XSRF-TOKEN` diperbarui → request diulang → berhasil **HTTP 200**!
   - Dibuktikan dari network log browser: request 585 (419) → request 586 (`csrf-cookie` 204) → request 587 (`firstlogin` 200).
2. **Sesi Kedaluwarsa Nyata (401)**:
   - Sesi di backend dihapus paksa dari storage sesi.
   - Request API dipicu → server mengembalikan 401.
   - Pengguna otomatis dialihkan ke `/auth/login`, modal dialog "Session Expired: Your session has expired. Please sign in again." muncul tanpa reload loop.
3. **Child Navigation Guard**:
   - Operator login berada di `/` → berpindah ke `/master/roles` → otomatis diblokir ke `/auth/access` (karena tidak punya `roles.view`).
   - Operator berpindah ke `/master/typeusers` → diizinkan (karena punya `master.manage`).
   - Operator berpindah ke `/master/accounts` → otomatis diblokir ke `/auth/access` (karena tidak punya `users.view`).
   - Admin berpindah ke `/master/roles` → diizinkan (punya `roles.view`).
4. **Forgot Password & Reset Password End-to-End**:
   - Buka `/auth/forgot-password` → input `uat.operator@uat.local` → dialog konfirmasi anti-enumerasi tampil.
   - Email tercatat di `laravel-api/storage/logs/laravel.log` dengan URL bertoken.
   - Buka URL reset password di browser → form `/password-reset/:token` termuat dengan email terisi.
   - Input password baru `newpassword123` → tersimpan dan diarahkan ke `/auth/login`.
   - Login dengan password lama (`password`) → ditolak 422 ("These credentials do not match our records.").
   - Login dengan password baru (`newpassword123`) → berhasil masuk Dashboard!
   - Password uji kemudian dikembalikan ke baseline `password` via database UAT untuk konsistensi retest pemilik.

---

## 4. Status Keputusan & Blocker
- **DEC-15**: **APPROVED** (2026-10-16 WIB).
- **Fase 01a**: **DITERIMA pemilik** (checklist #1–#14 APPROVED 2026-10-15 WIB).
- **Fase 01b**: **IMPLEMENTED & VERIFIED secara otomatis dan browser UAT**; menunggu review pemilik.
- **BLK-03**: **TETAP OPEN** (dialokasikan ke Fase 01c).
- Keputusan autentikasi lain (makna `status_user_id`, bootstrap pengguna pertama, deployment produksi) **tetap OPEN** karena tidak menghambat scope 01b.
- **Klarifikasi CHANGELOG**: entri pemilik ("Pengembangan fitur iteratif via sesi Claude") tidak ditemukan di working tree maupun riwayat Git yang diperiksa; penyebab kehilangan belum diketahui dan tidak direkonstruksi tanpa sumber.

---

## 5. Panduan UAT Manual Pemilik
Langkah-langkah pengujian manual pemilik dirinci lengkap dalam `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`.
