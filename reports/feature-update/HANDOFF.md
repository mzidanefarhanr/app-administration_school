# Handoff pengembangan aplikasi sekolah

Checkpoint terbaru: 2026-10-16 (WIB). Branch `main`.
Implementasi 01b terakhir: `60e951b` ("fix(phase-01b): unify session errors, route guards, and password reset").
Kebijakan commit/push: commit dan push per fase/koreksi ke branch kerja aktif telah **diotorisasi**; merge, force-push, rewrite history, dan deploy manual **tetap tidak diotorisasi**.

Catatan perubahan pemilik: entri `CHANGELOG.md` yang disebut laporan lama ("Pengembangan fitur iteratif via sesi Claude") **tidak ditemukan** pada working tree maupun history yang diperiksa (lihat §8); penyebab kehilangan belum diketahui dan tidak direkonstruksi. Berkas log bukti `console.txt` tetap ada dan **tidak** di-commit. Entri baru milik pemilik pada `CHANGELOG.md` dipertahankan tanpa menyentuh baris tersebut.

---

## 1. Status Fase
- **Fase 00 — Audit baseline**: PARTIAL (source & environment audit selesai; browser NOT RUN).
- **Fase 01a — Authorization backend**: **IMPLEMENTED + DITERIMA pemilik** (checklist #1–#14 APPROVED 2026-10-15 WIB).
- **Fase 01b — Konfigurasi API dan error auth**: **IMPLEMENTED & VERIFIED** (DEC-15 APPROVED 2026-10-16 WIB).
  - Implementasi kode commit `60e951b`.
  - Keputusan Q1–Q3 disetujui dan dicatat pada `DEC-15`.
  - **Penerimaan manual 01b**: **PENDING** (belum disetujui manual oleh pemilik; instruksi commit/push bukan penerimaan implementasi).
  - Laporan implementasi: `reports/feature-update/PHASE-01B-API-AUTH.md`. Panduan manual: `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`.
- **Fase 01c dan fase selanjutnya**: NOT STARTED. BLK-03 dialokasikan ke 01c.

---

## 2. Pemeriksaan yang Dijalankan & Hasil (Fase 01b)
1. **Automated Backend**:
   - Perintah: `php artisan test` pada database SQLite in-memory `:memory:` terisolasi.
   - Hasil: **55 passed (149 assertions)**, termasuk 6 tes password reset (requested, valid, invalid token 422, expired token 422, reused token 422, mismatch & short password 422).
   - Style check: `./vendor/bin/pint --test` → **PASS** (100% clean).
2. **Automated Frontend**:
   - Perintah: `node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`.
   - Hasil: **10 passed (0 failed)**. Menguji interceptor timeout/network, 419 single coordinated recovery, 401 session expiry deduplication, 403 permission vs inactive separation, router guard child ability evaluation, dan logout 204.
   - Build: `npm run build` → **PASS** (`✓ built in 18s`, 0 errors, 0 warnings).
   - Lint: `npx eslint ... --quiet` pada seluruh berkas yang disentuh → **0 errors**.
3. **Verifikasi Browser/HTTP Nyata (UAT 127.0.0.1:4001 & 127.0.0.1:8001)**:
   - CSRF 419 nyata: cookie dirusak → server merespons 419 → interceptor mengambil `csrf-cookie` (204) → request diulang otomatis (200 OK).
   - Sesi aktif 401 nyata: sesi backend dihapus → request API → otomatis redirect ke `/auth/login` dengan modal dialog "Session Expired: Your session has expired. Please sign in again."
   - Child navigation guard: Operator di `/` klik `/master/roles` atau `/master/accounts` → dialihkan ke `/auth/access`; Operator ke `/master/typeusers` → diizinkan; Admin ke `/master/roles` → diizinkan.
   - Forgot & reset password end-to-end: input email di form → ambil link dari `laravel.log` (driver `log`, tanpa email nyata) → buka form reset → simpan password baru → password lama ditolak 422 → password baru berhasil login ke Dashboard → token bekas pakai ditolak 422. Password operator dikembalikan ke `password`.
   - Single logout 204: sesi backend berakhir bersih (`serverSuccess: true`).
4. **Lingkungan & Keamanan**:
   - Seluruh tes hanya menggunakan database sintetis (`school_access_uat` dan SQLite `:memory:`) dan akun uji. Tidak ada data operasional yang disentuh.
   - Bahasa antarmuka dalam scope implementasi tetap **English**.

---

## 3. Berkas yang Berubah pada Fase 01b (`60e951b`)
- Backend:
  - `laravel-api/.env.uat.example`: ditambahkan `MAIL_MAILER=log`.
  - `laravel-api/tests/Feature/Auth/PasswordResetTest.php`: 4 tes baru untuk invalid/expired/reused token dan validasi.
- Frontend:
  - `vue-app/src/apiLogin/index.js`: interceptor aktif pada instance, 419 single retry, 401 deduplikasi + flash banner, timeout 15s, pemisahan 403.
  - `vue-app/src/main.js`: pendaftaran auth error handlers tanpa circular imports.
  - `vue-app/src/stores/authStore.js`: anti-race sesi lama, `skipAuthRedirect`, `logOut()` 204 dengan `serverSuccess`.
  - `vue-app/src/router/index.js`: `router.beforeEach` global (child & direct navigation), pemetaan ability per halaman, rute forgot/reset password.
  - `vue-app/src/views/pages/auth/Login_2.vue`: link forgot password aktif, pengecekan flash message sesi kedaluwarsa.
  - `vue-app/src/views/pages/auth/Logout.vue`: pemanggil single store logout, UI English.
  - `vue-app/src/views/pages/auth/Profile.vue`: kontrol ubah password English.
  - `vue-app/src/layout/AppUsers.vue`: pelaporan status server logout yang akurat.
  - `vue-app/src/views/pages/auth/ForgotPassword.vue` (baru): form permintaan link reset dengan anti-enumerasi.
  - `vue-app/src/views/pages/auth/ResetPassword.vue` (baru): form reset password dengan validasi min 8 char & konfirmasi.
  - `vue-app/tests/phase-01b-api-auth.test.mjs` (baru): unit test deterministik untuk interceptor, 419, 401, 403, guard, logout.
- Dokumentasi & Kontrak:
  - `docs/DECISIONS.md`: DEC-15 APPROVED (Q1–Q3).
  - `docs/API-CONTRACTS.md`: kontrak error, 419 retry, router guard, reset password.
  - `reports/feature-update/PHASE-01B-API-AUTH.md`: laporan implementasi 01b lengkap.
  - `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`: panduan manual UAT 01b (16 checklist).
  - `reports/feature-update/INDEX.md`, `roadmap/ROADMAP.md`, `CHANGELOG.md`: sinkronisasi status.

---

## 4. Status Tugas Pelengkapan Bukti (Rencana Sesi Baru)
Berdasarkan diff dan bukti aktual pada repository:
1. **Bukti recovery / error auth**: **SEBAGIAN SELESAI**.
   - Sudah diverifikasi via automated test (10 passed) dan Playwright runtime (live 419 recovery, 401 redirect dengan persistent banner, network error notification).
   - Belum dilakukan: perekaman tangkapan layar/artefak visual formal jika pemilik menghendaki lampiran gambar di dokumen.
2. **Audit akses `/master/typeusers`**: **SEBAGIAN SELESAI**.
   - Guard `master.manage` pada router sudah terpasang dan diuji.
   - Belum dikonfirmasi: keputusan bisnis pemilik apakah Operator Sekolah memang dimaksudkan dapat mengelola tipe pengguna (`master.manage` mencakup type_users di baseline seeder), atau perlu kemampuan terpisah di masa mendatang.
3. **Regresi empat peran**: **SEBAGIAN SELESAI**.
   - Operator dan Admin telah diuji langsung pada alur 01b (login, child guard, reset password).
   - Belum dilakukan: walkthrough manual menyeluruh untuk Principal (`uat.kepsek`) dan Waka (`uat.waka`) khusus untuk fitur-fitur baru 01b (mis. link reset password untuk masing-masing peran).
4. **Lint tanpa `--quiet`**: **SEBAGIAN SELESAI**.
   - `npx eslint ... --quiet` menghasilkan **0 errors** pada seluruh berkas yang disentuh.
   - Peringatan Prettier (CRLF/LF line endings & trailing whitespace) dibiarkan tanpa `--fix` otomatis sesuai aturan AGENTS.md agar tidak melakukan mutasi format massal di luar scope.
5. **Panduan manual UAT**: **SELESAI**.
   - Berkas `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md` sudah memuat 16 butir checklist terstruktur siap uji.

---

## 5. Keputusan & Blocker Terbuka
- **DEC-03** (peran & kemampuan): **APPROVED** (2026-10-15).
- **DEC-14** (register publik ditutup, status_active ditolak, ekspor via students.export, legacy mapping per nama, UI English): **APPROVED** (2026-10-14 WIB).
- **DEC-15** (alur error auth, CSRF 419 single retry, reset password scope 01b via mail log): **APPROVED** (2026-10-16 WIB).
- **BLK-03**: **OPEN** (`DatabaseSeeder` masih memuat identitas asli; dilarang dijalankan untuk pengujian; dialokasikan ke 01c).
- **Keputusan autentikasi lain**: makna `status_user_id` (jangan disamakan dengan `status_active`), bootstrap pengguna pertama, deployment CORS/Sanctum produksi, strategi pencabutan token vs sesi: **OPEN** (tidak menghambat 01b).
- **UI Language**: Seluruh antarmuka dalam scope tetap **English**.

---

## 6. Server & Lingkungan Aktif
Server UAT lokal yang diketahui masih berjalan (jangan dihentikan sembarangan):
- **API UAT**: port `8001` (PID 12720, `php artisan serve --host=127.0.0.1 --port=8001 --env=uat`)
- **SPA UAT**: port `4001` (PID 21372, `vite --mode uat --host 127.0.0.1 --port 4001`)
- **Database UAT**: MySQL `school_access_uat` (disposable, terpisah dari `backdata_administrations`)
- Server operasional pada port `8000` dibiarkan utuh.

**Cara melanjutkan di sesi baru**:
- Jika kedua proses masih hidup, langsung akses `http://127.0.0.1:4001`.
- Jika server perlu dimulai ulang:
  ```powershell
  # Terminal 1 — API
  php artisan serve --host=127.0.0.1 --port=8001 --env=uat
  # Terminal 2 — SPA
  npm run dev:uat
  ```

---

## 7. Pekerjaan Pertama Sesi Baru
1. Menunggu review manual pemilik atas checklist Fase 01b di `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`.
2. Melengkapi tugas pelengkapan bukti yang berstatus sebagian selesai jika diminta pemilik (perekaman artefak bukti visual, walkthrough regresi Waka/Kepsek pada reset password, konfirmasi akses `/master/typeusers`).
3. Setelah Fase 01b memperoleh persetujuan manual, lanjutkan perencanaan ke Fase 01c (isolasi test lanjutan, validator fixture, penanganan BLK-03).

---

## 8. Klarifikasi Entri CHANGELOG Pemilik
Berdasarkan pemeriksaan `git status`, `git diff`, `git log -S/-G`, dan pemindaian blob history pada HEAD:
- Working tree bersih (hanya `?? console.txt`).
- Frasa entri pemilik `"Pengembangan fitur iteratif via sesi Claude"` **tidak ditemukan** pada `CHANGELOG.md` di working tree **maupun** pada blob `CHANGELOG.md` mana pun di history yang diperiksa. Frasa hanya pernah hidup di berkas `HANDOFF.md` terdahulu.
- Jejak historis: pesan commit `5f18c06` dan `ad937e0` menyebut "owner CHANGELOG.md entry preserved unstaged".
- **Kesimpulan**: entri tersebut pernah berupa perubahan working tree tidak ter-commit lalu **tidak ada lagi**; penyebab persis kehilangan **belum diketahui**. Isi tidak direkonstruksi tanpa sumber. Berkas log `console.txt` milik pemilik tetap dipertahankan.
