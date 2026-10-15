# Laporan Fase 01b — Audit konfigurasi API dan alur error autentikasi

- Tanggal WIB: 2026-10-15
- Mode: **audit + rencana** (implementasi BELUM dijalankan pada langkah ini)
- Scope instruksi pemilik: audit kondisi aktual setelah koreksi 01a, audit kontrak autentikasi, audit reset password, susun rencana implementasi 01b, dan sinkronkan dokumentasi. Batas: tidak mengubah kode/config runtime/schema/data, tidak menjalankan seeder, tidak merge/force-push/deploy, tidak mulai implementasi 01b.
- Branch / baseline SHA: `main` @ `b93cbee109d9b6a5682d69c4dda1d97c6ed2ad45` (HEAD = `origin/main`, tersinkronisasi penuh). Fase 01a **DITERIMA pemilik** (2026-10-15 WIB).
- Working tree sebelum: `?? console.txt` (log bukti pemilik, dipertahankan). Tidak ada tracked diff.
- Working tree sesudah: file laporan baru + pembaruan INDEX/HANDOFF/ROADMAP/CHANGELOG (lihat §6).
- Lingkungan: Windows, PHP 8.1.25, Composer 2.8.2, Node 24.18.0, npm 11.4.2. UAT: SPA `127.0.0.1:4001`, API `127.0.0.1:8001`, DB `school_access_uat`. Tidak ada server/test/build yang dijalankan pada audit ini (source review saja).
- Status teknis: **AUDIT SELESAI** (inventaris + rencana). Status review: **PENDING**.

> Catatan bukti: seluruh temuan di bawah berasal dari **source review statis** (baca file) pada HEAD `b93cbee`. Tidak ada perintah uji/build/browser yang dijalankan pada langkah audit ini. Bila ada baris perintah (mis. `grep`), itu adalah pencarian source, bukan pengujian runtime.

## 1. Audit kondisi aktual setelah koreksi 01a

### 1a. Instance axios, baseURL, interceptor, credentials, CSRF
| Aspek | Temuan aktual | Lokasi |
|---|---|---|
| Instance axios | **Satu** instance aplikasi: `apiLogin = axios.create({...})`. Tidak ada instance axios lain di `src`. | `vue-app/src/apiLogin/index.js:32` |
| baseURL | `baseURL: import.meta.env.VITE_API_BASE_URL`. **Tidak ada fallback**; jika kosong/tidak valid → `notify` error + `throw` (aplikasi gagal boot dengan pesan English). | `vue-app/src/apiLogin/index.js:8,20-30,34` |
| Credentials | `withCredentials: true` (cookie session dikirim lintas origin). | `vue-app/src/apiLogin/index.js:35` |
| CSRF | `withXSRFToken: true` pada instance; juga `X-Requested-With: XMLHttpRequest` dan `Accept: application/json` sebagai default header. Bootstrapping CSRF via `GET /sanctum/csrf-cookie` di `authStore.getToken()`. | `vue-app/src/apiLogin/index.js:36,60-61`; `vue-app/src/stores/authStore.js:45-47` |
| Header default | `X-Requested-With` + `Accept: application/json` diset pada instance `apiLogin`. | `vue-app/src/apiLogin/index.js:60-61` |
| **Interceptor** | Terdaftar pada **`axios.interceptors.response.use(...)`** — yaitu pada **instance global `axios`**, **bukan** pada instance `apiLogin`. Karena seluruh pemanggilan aplikasi memakai `apiLogin`, dan interceptor instance terpisah di axios v1, notifikasi 401/network di interceptor itu **tidak akan pernah berjalan** untuk request aplikasi. | `vue-app/src/apiLogin/index.js:39` (terhadap `apiLogin` di :32) |
| Pemakai `apiLogin` | `authStore.js`, `useResourceManager.js`, `AppTopbar.vue`, `Student.vue`, `SchoolProfile.vue`, `Roles.vue`, `Accounts.vue`, `ListUsersOnlineWidget.vue`, `Profile.vue`, `Logout.vue`, `Login_2.vue`. | (grep `apiLogin`) |
| Pemakai axios global | **Tidak ada.** Grep menunjukkan tidak ada `axios.get/post/put/patch/delete(` di luar `apiLogin`. | grep `src` |

**Bukti interceptor tidak aktif**: `vue-app/src/apiLogin/index.js` baris 39 memakai `axios.interceptors...`, sedangkan instance yang dipakai seluruh kode adalah `apiLogin` (baris 32). Tidak ada `.interceptors` yang dipasang pada `apiLogin`. Ini adalah inti temuan "interceptor pada instance yang benar" yang disebut roadmap 01b.

### 1b. URL API hardcode yang masih tersisa
- **URL API**: tidak ditemukan IP/port hardcode pada kode aplikasi. Satu-satunya nilai `VITE_API_BASE_URL` berada di `vue-app/.env.uat` (di-ignore) dan pesan contoh pada `apiLogin/index.js` (`http://127.0.0.1:8001` sebagai contoh teks). Tidak ada fallback IP lama (`26.117.240.38`) — sudah dihapus pada 01a.
- **Sisa hardcode non-API (bukan scope 01b, catat saja)**: URL aset/CDN PrimeVue di halaman contoh/demo (`Crud.vue`, uikit `*Doc.vue`, `RecentSalesWidget.vue`, `ListUsersOnlineWidget.vue` memakai gambar `primefaces.org`), avatar Supabase di `Profile.vue:224`, dan link dokumentasi. Ini bukan endpoint API dan berada di demo/template.

### 1c. Guard router (termasuk navigasi antar-child)
- `authNav` dipasang sebagai `beforeEnter` pada **parent** `/` (children: dashboard, public/*, master/*, uikit/*, pages/*, documentation) dan pada rute `/auth/login`, `/auth/logout`. Rute `/landing`, `/auth/access`, `/auth/error`, `/pages/notfound`, catch-all `:pathMatch(.*)` **tanpa** guard. | `vue-app/src/router/index.js:34,229,235`
- **Temuan**: pada vue-router, `beforeEnter` parent **hanya dipicu saat memasuki route record parent**, bukan pada setiap transisi antar-child di bawah parent yang sama. Saat pengguna yang sudah berada di dalam layout (`/`) berpindah child (mis. `/public/schoolyears` → `/master/roles`), `authNav` parent **tidak dijalankan ulang**, sehingga gating `/master` (`can('users.view')`) **tidak dievaluasi ulang** untuk transisi child. Ini temuan langsung yang disebut roadmap 01b ("Audit beforeEnter parent Vue yang tidak selalu berjalan saat berpindah child").
- Guard mengandalkan `useAuthStore.fetchIdentity()` (sebelum tiap `authNav`), lalu `hasLogin` + `getToken()` (CSRF) + `can('users.view')` untuk area `/master`. Bercampurnya panggilan `fetchIdentity` dan `getToken` di guard membuat setiap navigasi berpotensi memicu request jaringan.
- Redirect non-auth: 401 tidak menangani redirect; guard hanya memeriksa `hasLogin`, tidak membedakan **403 izin** vs **sesi berakhir (401)**.

### 1d. Penanganan status error (401/403/419/422/429/5xx/timeout/tanpa-response)
| Status | Penanganan aktual | Lokasi |
|---|---|---|
| 401 | Hanya di `fetchIdentity()`: memperlakukan 401 sebagai sesi tidak valid (`clearIdentity`, `hasLogin=false`). Tidak ada redirect otomatis ke login; tidak ada pesan global. | `authStore.js:67-69` |
| 403 | Interceptor global berniat `notify` "Session expired" hanya untuk 401, dan tidak logout pada 403 — **tetapi interceptor ini tidak aktif** (§1a). 403 tidak ditangani seragam; hanya beberapa pemanggil (mis. `Student.vue` export) menangani lokal. | `apiLogin/index.js:44-49` |
| 419 (CSRF) | **Tidak ada penanganan khusus.** 419 dari `VerifyCsrfToken` akan muncul sebagai error axios generik; tidak ada refresh `csrf-cookie` otomatis atau pesan "session expired". | (tidak ada) |
| 422 | Ditangani per-komponen (mis. `Login_2.vue` menampilkan `error.response.data.message`; export menangani 422 blob). Tidak terpusat. | `Login_2.vue`, `Student.vue:281` |
| 429 | **Tidak ada penanganan.** Rate limit login ada di backend (`LoginRequest`), tapi frontend tidak menampilkan pesan khusus. | (tidak ada) |
| 5xx | **Tidak ada penanganan pusat.** Pesan generik dari `Login_2.vue` `else` branch saja. | `Login_2.vue` |
| Timeout | **Tidak ada** konfigurasi `timeout` pada instance; tidak ada penanganan `ECONNABORTED`. | `apiLogin/index.js:32-37` |
| Error tanpa response | `Login_2.vue` membedakan `!error.response` → pesan koneksi; interceptor global (tidak aktif) berniat `notify` koneksi. | `Login_2.vue`, `apiLogin/index.js:50-55` |

### 1e. Konsistensi login, refresh, logout, akun nonaktif, sesi kedaluwarsa
- **Login**: `Login_2.vue` `submitLogin` → `POST /login` (204) → `await fetchIdentity()` → cek `status_active`/`status_user_id` → `POST /api/firstlogin/{id}` → sukses. Ini sudah diperbaiki dan diterima pada 01a. | `Login_2.vue`
- **Refresh**: `authNav` memanggil `fetchIdentity()` pada mount; sesi cookie + `check-auth` memulihkan identitas. Sudah diverifikasi pemilik.
- **Logout**: dua jalur terpisah & tidak konsisten:
  - `authStore.logOut()`: `POST /logout` → `$reset()`. Dipakai `AppUsers.vue`.
  - `Logout.vue`: `POST /logout` → memeriksa `response.data.id != null` (204 **tidak punya body**, jadi `response.data` = `''` → `.id` `undefined` → jatuh ke `else`), memanggil `logOut()` lagi (menembak `/logout` dua kali), memakai helper `visibleStaticBackdrop*` yang **tidak didefinisikan** di file ini (berpotensi `ReferenceError`), lalu `router.push('/login')`. Ini adalah kandidat bug konsistensi logout. | `Logout.vue:9-46`, `authStore.js:141-150`
- **Akun nonaktif**: backend menolak login (422) dan `active` middleware mengakhiri sesi (403 JSON) — sudah 01a. Frontend menangani 422 login; 403 sesi-aktif tidak ditangani terpusat.
- **Sesi kedaluwarsa**: tidak ada mekanisme global; `fetchIdentity` 401 menandai belum login, guard kemudian mengarahkan ke login pada navigasi berikutnya — namun **tidak** saat berada di child yang sudah terbuka tanpa navigasi.

### 1f. Bagian yang sudah diperbaiki pada 01a (jangan diimplementasikan ulang)
- `apiLogin/index.js`: baseURL dari env, tanpa fallback IP lama, pesan error konfigurasi English, pemisahan 401 vs 403 di interceptor (niatnya). | 01a
- `Login_2.vue`: identitas pasca-login, pencegahan submit ganda, pesan English per jenis kegagalan. | 01a (§20 laporan 01a)
- `authStore.js`: kontrak ringan `check-auth`, getter kompatibilitas, anti-race `_requestId`, `logOut()` berbasis sesi. | 01a
- `AppMenu.vue`/`router`: gating kemampuan bernama (bukan `type_user_id`). | 01a
- Yang **belum** diselesaikan dan menjadi scope 01b: interceptor pada instance yang benar, guard child, 419/429/5xx/timeout, konsistensi logout, alur session-expired.

## 2. Audit kontrak autentikasi

### 2a. Cookie session vs bearer token
- **Yang benar-benar dipakai**: **Sanctum SPA cookie/session**. Bukti:
  - `apiLogin` memakai `withCredentials: true` + `withXSRFToken: true` (cookie), tanpa header `Authorization: Bearer`. | `apiLogin/index.js:35-36`
  - Middleware group `api` menyertakan `Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful`, yang mengaktifkan auth berbasis session untuk origin stateful. | `app/Http/Kernel.php:42`
  - Login/logout/CSRF berada pada **web routes** (`routes/web.php` → `require auth.php`): `POST /login`, `POST /logout`, `GET /sanctum/csrf-cookie` (dari Sanctum), `POST /forgot-password`, `POST /reset-password`. | `routes/auth.php`, `config/sanctum.php`
  - `check-auth`/`api/*` memakai `$request->user('sanctum')` dan `auth:sanctum`. | `routes/api.php:59,87`
- **Bearer token**: `User` memakai `HasApiTokens` dan Sanctum mendukung bearer, tetapi **tidak ada kode frontend** yang mengirim/menyimpan token (tidak ada `Authorization`, tidak ada penyimpanan token). `authStore.persist` menyimpan `identity`/state ke `sessionStorage`, **bukan** token.
- **Kesimpulan**: strategi aktif = **cookie session saja**. Bearer hanya tersedia sebagai kemampuan framework yang tidak dipakai. **Jangan mengubah strategi token/sesi** pada 01b; kebutuhan bearer belum terbukti.

### 2b. Konfigurasi CORS, Sanctum, cookie domain, secure, SameSite, CSRF (UAT)
| Item | Nilai aktual (UAT) | Sumber |
|---|---|---|
| CORS `paths` / methods / headers | `['*']` / `['*']` / `['*']` | `config/cors.php` |
| CORS `allowed_origins` | `[env('FRONTEND_URL','http://localhost:3000')]` = `http://127.0.0.1:4001` (UAT) | `config/cors.php:22`, `.env.uat` |
| CORS `supports_credentials` | `true` (wajib untuk cookie lintas origin) | `config/cors.php` |
| Sanctum `stateful` | default + `SANCTUM_STATEFUL_DOMAINS=127.0.0.1:4001,localhost:4001` (UAT) | `config/sanctum.php`, `.env.uat` |
| Sanctum `guard` | `['web']` (session) | `config/sanctum.php` |
| Session driver / lifetime | `file` / `120` menit | `.env.uat`, `config/session.php` |
| `SESSION_DOMAIN` | `127.0.0.1` | `.env.uat` |
| `SESSION_SECURE_COOKIE` | **tidak diset** pada UAT → cookie **tidak** secure (benar untuk HTTP lokal) | `config/session.php:secure`, `.env.uat` |
| `SESSION_SAME_SITE` | `lax` (hardcode di `config/session.php`) | `config/session.php` |
| `http_only` | `true` | `config/session.php` |
| CSRF | `VerifyCsrfToken` aktif pada group `web` (tanpa `$except`); SPA memperoleh cookie via `/sanctum/csrf-cookie` + `withXSRFToken` | `app/Http/Middleware/VerifyCsrfToken.php`, `Kernel.php:37` |
| `FRONTEND_URL` | `http://127.0.0.1:4001` (UAT) — dipakai untuk CORS, Sanctum stateful, dan URL reset password | `.env.uat`, `config/app.php:60` |

**Catatan deployment (keputusan tersendiri, bukan blocker 01b lokal)**: `SESSION_SECURE_COOKIE=true`, `SameSite` final, domain produksi, dan origin CORS spesifik perlu ditetapkan saat deployment. Untuk pengujian lokal (HTTP `127.0.0.1`) konfigurasi UAT di atas sudah konsisten dan **tidak perlu mengubah** apa pun demi 01b.

### 2c. Penolakan izin vs sesi berakhir (jangan disamakan)
- **403 = izin fitur ditolak** (mis. tanpa `users.view` → `/api/users` 403; `active` middleware akun nonaktif → 403 dengan `message`). **Jangan** perlakukan 403 sebagai sesi berakhir/logout.
- **401 = tidak terautentikasi/sesi berakhir**.
- Interceptor 01a sudah berniat memisahkan ini (`if 401 ... else if !response`), tetapi **belum aktif** (instance salah). Rencana 01b harus mempertahankan pemisahan 401 vs 403 vs jaringan.

## 3. Audit reset password

### 3a. Yang tersedia (backend)
| Bagian | Status | Lokasi |
|---|---|---|
| Route `POST /forgot-password` (`password.email`, middleware `guest`) | **Ada** | `routes/auth.php:19-21` |
| Route `POST /reset-password` (`password.store`, middleware `guest`) | **Ada** | `routes/auth.php:23-25` |
| `PasswordResetLinkController@store` | **Ada**: validasi `email`, `Password::sendResetLink` | `app/Http/Controllers/Auth/PasswordResetLinkController.php` |
| `NewPasswordController@store` | **Ada**: validasi `token/email/password confirmed`, `Password::reset`, update password + `remember_token` | `app/Http/Controllers/Auth/NewPasswordController.php` |
| Broker/token | **Ada** (default Laravel): tabel `password_reset_tokens`, `expire=60`, `throttle=60` | `config/auth.php` |
| Notifikasi email | **Ada** (default `ResetPassword` notification via `Notifiable` + `CanResetPassword`) | `app/Models/User.php:17`, framework default |
| Tujuan link | `ResetPassword::createUrlUsing` → `config('app.frontend_url') . "/password-reset/{token}?email=..."` | `app/Providers/AuthServiceProvider.php` |
| Kirim email nyata | **Tidak dijalankan** pada audit ini (dilarang). `MAIL_MAILER` default `smtp`; UAT `.env.uat` **tidak** mengeset `MAIL_*`. | `config/mail.php`, `.env.uat` |

### 3b. Yang tersedia (frontend)
- **Form Forgot Password**: hanya **placeholder link teks** di `Login_2.vue:179` (`<a>Forgot Password?</a>`), **tanpa handler**, tanpa route tujuan. `Login.vue` (template lama) juga hanya teks.
- **Route `/password-reset/:token`**: **tidak ada** di `vue-app/src/router/index.js`. Tidak ada view form reset password.
- **Tidak ada** pemanggilan `POST /forgot-password` maupun `POST /reset-password` dari SPA. Grep `password-reset|forgot-password|reset-password|resetPassword` di `src` = **kosong**.
- **Ada** form ubah password **dalam sesi** di `Profile.vue` (butuh login; `POST /api/users/{id}` dengan `password_old`/`password_new`), dengan pesan masih **Indonesia** ("Password tidak tervalidasi...", "Password Baru Berhasil Dibuat!").

### 3c. Klasifikasi (scaffold vs berjalan)
- **Berjalan (backend)**: endpoint `forgot-password`/`reset-password` + broker + notifikasi = kode lengkap tapi **belum diuji end-to-end** (email tidak dikirim, tidak ada klien).
- **Scaffold / gap (frontend)**: hampir seluruh alur reset password **belum ada**. Link "Forgot Password?" adalah placeholder tanpa aksi/route.
- **Tidak diklaim PASS**: tanpa pengujian end-to-end dan tanpa form frontend, alur reset password = **NOT RUN / gap**.

### 3d. Opsi minimum untuk diputuskan pemilik (bila 01b mencakup reset password)
1. **Prioritas alur**: apakah reset password end-to-end masuk 01b, atau ditunda ke subfase/backlog tersendiri? (Ini keputusan penghalang utama.)
2. **Frontend form reset**: buat route `/password-reset/:token` + form (email token + password baru/konfirmasi) di SPA (English), plus form "Forgot Password" yang benar-benar memanggil `POST /forgot-password`.
3. **Pengiriman email lokal**: pilih driver uji — `log`/`array` untuk UAT (tanpa email nyata) vs SMTP. Untuk UAT **tanpa** mengirim email nyata: `MAIL_MAILER=log` pada `.env.uat` (lokal, di-ignore) memungkinkan uji token dari `storage/logs`, tanpa menyentuh akun/SMTP operasional.
4. **Kebijakan keamanan**: pesan seragam anti-enumerasi (always "if the email exists..."), rate limit, masa berlaku token (60 menit default), dan apakah akun nonaktif boleh reset password.

## 4. Rencana implementasi 01b (perubahan yang diusulkan)

> Semua item di bawah **belum diimplementasikan**. Format: masalah+bukti → sebelum/sesudah → file → kriteria+tes → keputusan penghalang.

### C-01 — Pindahkan interceptor ke instance `apiLogin` (inti 01b)
- **Masalah/bukti**: `axios.interceptors.response.use` dipasang pada global `axios`, bukan `apiLogin`; seluruh request memakai `apiLogin` → interceptor **mati**. | `apiLogin/index.js:39`, grep pemakai.
- **Sebelum**: interceptor tidak pernah berjalan; tidak ada notifikasi 401/koneksi terpusat.
- **Sesudah**: interceptor dipasang pada instance `apiLogin` (atau `axios.create` mengembalikan instance yang di-`use`), sehingga respons/error aplikasi melewatinya.
- **File**: `vue-app/src/apiLogin/index.js`.
- **Kriteria + tes**: setelah perubahan, request 401/403/network memicu perilaku terpusat yang diinginkan; tes unit (mock axios instance) memverifikasi `apiLogin.interceptors.response.handlers.length > 0`; tidak ada regresi pada pemanggil (build + smoke).
- **Keputusan penghalang**: tidak ada (murni koreksi).

### C-02 — Pisahkan 401 vs 403 vs 419 vs 5xx vs jaringan secara terpusat
- **Masalah/bukti**: 403 (izin) dan 401 (sesi) tidak dibedakan seragam; 419/429/5xx/timeout tanpa penanganan. | §1d.
- **Sebelum**: penanganan tersebar per-komponen; 419 tidak dikenali; 5xx/429 generik.
- **Sesudah**: interceptor pada `apiLogin` menangani: 401 → tandai sesi berakhir + arahkan login (tanpa memaksa reload loop); 403 → **tidak** logout, pesan izin; 419 → segarkan `csrf-cookie` sekali lalu ulangi/beri pesan; 422 → diteruskan ke pemanggil; 429 → pesan "too many attempts"; 5xx/timeout/tanpa-response → pesan koneksi English. Semua pesan **English**.
- **File**: `vue-app/src/apiLogin/index.js` (+ mungkin `authStore.js` untuk `clearIdentity`), opsi `timeout` pada instance.
- **Kriteria + tes**: tes unit pemetaan status→aksi (mock response); UAT: 401 setelah sesi dihapus, 403 tanpa izin, 419 dengan menghapus cookie XSRF, koneksi mati.
- **Keputusan penghalang**: **tidak** perlu keputusan bisnis; perilaku default diusulkan (lihat §7 untuk konfirmasi singkat).

### C-03 — Perbaiki konsistensi logout
- **Masalah/bukti**: `Logout.vue` memakai `response.data.id` (204 tanpa body), memanggil logout dua kali, memakai helper tak terdefinisi. | `Logout.vue:9-46`.
- **Sebelum**: dua pemanggilan `/logout` + risiko `ReferenceError` + pesan Indonesia.
- **Sesudah**: satu jalur `authStore.logOut()` (single `POST /logout` + `$reset()`), tanpa akses `response.data.id`, UI English, redirect tunggal ke `/auth/login`. `Logout.vue` disederhanakan atau diarahkan ke perilaku `authStore.logOut`.
- **File**: `vue-app/src/views/pages/auth/Logout.vue` (+ konsumsi `AppUsers.vue` tetap).
- **Kriteria + tes**: satu `POST /logout` per aksi (bukti network); kembali ke login; state bersih.
- **Keputusan penghalang**: tidak ada.

### C-04 — Guard router: cakup transisi antar-child + bedakan izin vs sesi
- **Masalah/bukti**: `beforeEnter` parent tidak dievaluasi pada transisi child; tidak membedakan 403 vs 401. | §1c.
- **Sebelum**: pengguna tanpa `users.view` dapat membuka `/master/*` via navigasi child; tidak ada gating ulang.
- **Sesudah**: pindahkan/duplikasi gating ke `router.beforeEach` global (memeriksa setiap navigasi) atau tambahkan `beforeEnter` pada setiap child `/master`; bedakan informatif antara "izin ditolak" (403) dan "sesi berakhir" (401) untuk redirect yang tepat. Pertahankan UI English.
- **File**: `vue-app/src/router/index.js`.
- **Kriteria + tes**: navigasi langsung (URL) **dan** antar-child (menu) ke `/master/*` untuk 4 peran; sesi berakhir pada request nyata diarahkan ke login; regresi 4 peran tidak bercampur.
- **Keputusan penghalang**: tidak ada; ini perbaikan kebenaran (bukan keputusan bisnis).

### C-05 — Reset password frontend (opsional; butuh keputusan pemilik)
- **Masalah/bukti**: link placeholder tanpa route/form; backend siap. | §3.
- **Sebelum**: alur reset password tidak dapat dipakai dari SPA.
- **Sesudah**: route + form (English) untuk meminta link dan menyetel password baru; memakai endpoint yang ada; tanpa mengirim email nyata pada UAT (opsi `MAIL_MAILER=log`).
- **File**: `vue-app/src/router/index.js`, view form baru, `Login_2.vue` (link), `laravel-api/.env.uat` (lokal) untuk driver mail uji; **tidak** mengubah `config/*` production.
- **Kriteria + tes**: tes backend existing (`PasswordResetTest`) hijau; uji UAT terisolasi dengan driver `log` (tanpa email nyata); tidak mengubah akun operasional.
- **Keputusan penghalang**: **ya** — apakah reset password masuk scope 01b (lihat §7 Q1). Bila tidak, C-05 ditunda dan tidak menghambat C-01..C-04.

### C-06 (evaluasi) — Penyelarasan UI English pada halaman tersentuh
- **Masalah/bukti**: `Profile.vue` (ubah password) masih Indonesia; `Logout.vue` Indonesia. | §1e, §3b.
- **Sebelum/sesudah**: teks Indonesia → English pada kontrol yang disentuh; sesuai DEC-14 (UI English untuk pekerjaan ini).
- **File**: `Profile.vue`, `Logout.vue` (hanya pada area yang memang diubah 01b).
- **Keputusan penghalang**: tidak ada.

## 5. Rencana verifikasi (menyeluruh)
1. **Navigasi langsung**: buka URL `/master/roles` langsung sebagai Operator (tanpa `users.view`) → harus ditolak; sebagai Admin → boleh.
2. **Navigasi antar-child**: dari `/public/schoolyears` klik ke `/master/*` di dalam layout → gating tetap berlaku (tujuan C-04).
3. **Sesi berakhir pada request nyata**: hapus cookie sesi lalu picu request → 401 memicu alur session-expired sekali (bukan loop), arahkan ke login.
4. **Penolakan izin**: peran tanpa `students.export` → 403, **tidak** logout, pesan izin.
5. **Kegagalan jaringan**: matikan API → pesan koneksi English, tanpa crash.
6. **CSRF 419**: hapus cookie XSRF → 419 ditangani (segarkan cookie sekali / pesan).
7. **Rate limit 429**: picu login berulang → pesan English.
8. **Regresi 4 peran** (Operator/Admin/Principal/Waka): login, dashboard, menu, ekspor per izin, logout — **0 console error** pada non-admin, **0** `/api/users` untuk non-admin.
9. **Backend**: `php artisan test` (suite existing) pada DB SQLite `:memory:` terisolasi; tambah tes bila ada perubahan perilaku backend.
10. **Build/lint**: `npm run build`, `npx eslint` read-only pada file berubah.

## 6. Sinkronisasi dokumen
- Laporan baru ini (`PHASE-01B-API-AUTH-AUDIT.md`); INDEX/HANDOFF/ROADMAP/CHANGELOG diperbarui.
- 01a tetap **APPROVED (diterima pemilik)**; 01b berstatus **AUDIT/RENCANA** (belum implementasi).
- **BLK-03** tetap OPEN, dialokasikan ke **01c**.
- Commit/push dokumentasi: lihat pesan commit `docs(phase-01b): audit API configuration and authentication error flows` (SHA dicatat setelah commit).

## 7. Keputusan yang membutuhkan jawaban pemilik (minimum)
- **Q1 (penghalang C-05)**: Apakah **alur reset password end-to-end** termasuk scope 01b, atau ditunda? Bila ditunda, C-01..C-04 tetap dapat dikerjakan tanpa blocker.
- **Q2 (pilihan, non-penghalang)**: Untuk penanganan **session-expired** terpusat, apakah cukup **redirect ke `/auth/login`** dengan pesan singkat saat 401 (tanpa reload paksa), atau perlu modal "sesi berakhir" yang eksplisit?
- **Q3 (pilihan, non-penghalang)**: Untuk 419 (CSRF), apakah cukup **segarkan `csrf-cookie` sekali lalu coba ulang**, atau tampilkan pesan "sesi kedaluwarsa, silakan muat ulang"?

Keputusan OPEN lain (makna `status_user_id`, bootstrap pengguna pertama, deployment CORS/Sanctum, strategi token vs sesi) **tidak** menghambat C-01..C-04:
- `status_user_id` tidak dipakai pada alur API/error/auth SPA (check-auth sudah mengirim `status_active`); tidak menghambat 01b.
- Bootstrap pengguna pertama berkaitan dengan register (sudah ditutup, DEC-14) — tidak memengaruhi 01b.
- Deployment CORS/Sanctum berkaitan dengan **produksi**; untuk UAT lokal tidak menghambat. Dicatat sebagai keputusan deployment tersendiri.

## 8. Yang tidak dijalankan (NOT RUN) pada audit ini
- Tidak ada `php artisan test`, `npm run build`, `eslint`, seeder, atau browser.
- Tidak ada perubahan kode/config/schema/data.
- Tidak ada email reset password nyata dan tidak ada perubahan password akun.

## 9. Langkah berikutnya
Implementasi 01b (C-01..C-04 wajib; C-05/C-06 menunggu Q1) setelah rencana ini disetujui pemilik. BLK-03 tetap dialokasikan ke 01c. Laporan ini **bukan** izin lanjut otomatis.
