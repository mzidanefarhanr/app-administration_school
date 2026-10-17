# Handoff pengembangan aplikasi sekolah

Checkpoint terbaru: 2026-10-17 (WIB). Branch `main`.
Implementasi 01b terakhir: `60e951b` ("fix(phase-01b): unify session errors, route guards, and password reset").
Kebijakan commit/push: commit dan push per fase/koreksi ke branch kerja aktif telah **diotorisasi**; merge, force-push, rewrite history, dan deploy manual **tetap tidak diotorisasi**.

Catatan perubahan pemilik: entri `CHANGELOG.md` yang disebut laporan lama ("Pengembangan fitur iteratif via sesi Claude") **tidak ditemukan** pada working tree maupun history yang diperiksa (lihat §8); penyebab kehilangan belum diketahui dan tidak direkonstruksi. Berkas log bukti `console.txt` tetap ada dan **tidak** di-commit. Entri baru milik pemilik pada `CHANGELOG.md` dipertahankan tanpa menyentuh baris tersebut.

---

## 1. Status Fase
- **Fase 00 — Audit baseline**: PARTIAL (source & environment audit selesai; browser NOT RUN).
- **Fase 01a — Authorization backend**: **IMPLEMENTED + DITERIMA pemilik** (checklist #1–#14 APPROVED 2026-10-15 WIB).
- **Fase 01b — Konfigurasi API dan error auth**: **IMPLEMENTED + DITERIMA pemilik** (seluruh checklist manual #1–#16 APPROVED: nomor 1–7 dan 9–16 pada 16 Oktober 2026 WIB; nomor 8 retest berhasil pada 17 Oktober 2026 WIB).
  - Implementasi kode commit `60e951b`.
  - Keputusan Q1–Q3 disetujui dan dicatat pada `DEC-15`.
  - Laporan implementasi: `reports/feature-update/PHASE-01B-API-AUTH.md`. Panduan manual: `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`.
- **Fase 01c dan fase selanjutnya**: NOT STARTED (rencana Fase 01c disusun di `reports/feature-update/PHASE-01C-PLAN.md`, menunggu review pemilik; BLK-03 dialokasikan ke 01c).

---

## 2. Pemeriksaan yang Dijalankan & Hasil (Fase 01b Pelengkapan Bukti)
1. **Automated Backend**:
   - Perintah: `php artisan test` pada database SQLite in-memory `:memory:` terisolasi.
   - Hasil: **60 passed (173 assertions)**, bertambah 5 tes baru pada `TypeUserAccessTest` (CRUD via `master.manage`, integritas relasi 422, isolasi kemampuan, dan proteksi admin terakhir).
   - Style check: `./vendor/bin/pint --test` → **PASS** (100% clean).
2. **Automated Frontend**:
   - Perintah: `node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`.
   - Hasil: **13 passed (0 failed)**. Menguji interceptor timeout/network error tanpa auto-retry dan tanpa hapus sesi, CSRF 419 single retry terkoordinasi, penanganan kegagalan refresh CSRF, retry 419 berulang berhenti tanpa loop, deduplikasi 401, pemisahan 403, router child guard, monotonic request sequence anti-race saat pergantian akun, dan single logout 204.
   - Build: `npm run build` → **PASS** (`✓ built in 1m`, 0 errors).
   - Lint: `npx eslint ...` tanpa `--quiet` pada seluruh berkas yang disentuh → **0 errors**, 1.086 warnings format Prettier CRLF (tidak di-fix massal agar diff tetap bersih).
3. **Verifikasi Browser Nyata (UAT Live 127.0.0.1:4001 & 127.0.0.1:8001)**:
   - Walkthrough regresi penuh pada 4 peran (`uat.admin`, `uat.operator`, `uat.kepsek`, `uat.waka`):
     - Login melalui form: seluruh 4 peran berhasil masuk Dashboard.
     - Refresh halaman: state sesi dan kemampuan masing-masing peran tetap utuh.
     - Navigasi sesuai izin: Admin (`roles.view`, `users.view`) diizinkan; Operator (`master.manage`) diizinkan ke `typeusers` dan diblokir dari `roles`; Kepsek & Waka diblokir dari `roles` & `typeusers` ke `/auth/access` (Access Denied).
     - Ekspor sesuai izin: Admin, Operator, dan Kepsek memiliki tombol Export dan berhasil mengunduh CSV; Waka tidak memiliki tombol Export dan pemanggilan API langsung menghasilkan **403 Forbidden**.
     - Logout bersih: sesi berakhir via `POST /logout` (204), state lokal dibersihkan, dialihkan kembali ke `/auth/login`.
   - Sesi berakhir (401 nyata): sesi backend dibatalkan → request API merespons 401 → otomatis redirect ke `/auth/login` dengan modal dialog PrimeVue berbingkai "Session Expired: Your session has expired. Please sign in again." dan form login siap tanpa loop.
   - Pengecualian guest: request `check-auth` untuk guest merespons 200 `{ authenticated: false }` dengan `skipAuthRedirect: true`, tidak memicu modal sesi kedaluwarsa berulang atau loop.
4. **Lingkungan & Keamanan**:
   - Seluruh tes hanya menggunakan database sintetis (`school_access_uat` dan SQLite `:memory:`) dan akun uji. Tidak ada data operasional yang disentuh.
   - Bahasa antarmuka dalam scope implementasi tetap **English**.

---

## 3. Berkas yang Berubah pada Pelengkapan Bukti Fase 01b
- Backend:
  - `laravel-api/tests/Feature/Access/TypeUserAccessTest.php` (baru): 5 tes regresi untuk verifikasi izin Operator terhadap `type-users`, integritas relasi 422, dan isolasi role/kemampuan.
- Frontend:
  - `vue-app/src/stores/authStore.js`: nomor urut request monotonik global (`requestSequence`) untuk mencegah tabrakan sequence ID pasca `$reset()` pada pergantian akun.
  - `vue-app/tests/phase-01b-api-auth.test.mjs`: Test 2b (retry 419 gagal berhenti tanpa loop), Test 2c (refresh CSRF gagal ditangani bersih), dan asersi sesi aman pada Test 1.
  - `vue-app/tests/auth-race-condition.test.mjs`: tes anti-race pada pergantian akun.
- Dokumentasi & Laporan:
  - `reports/feature-update/PHASE-01B-API-AUTH.md`: laporan bukti lengkap Fase 01b diperbarui (tabel 6 skenario recovery, audit akses typeusers, tabel regresi 4 peran, hasil lint/test/build).
  - `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`: panduan manual UAT dirapikan (prosedur sesi berakhir, kegagalan jaringan, offline logout, sanitasi log email reset, dan penjelasan modal Session Expired).
  - `reports/feature-update/INDEX.md`, `roadmap/ROADMAP.md`, `CHANGELOG.md`: sinkronisasi checkpoint dan pencatatan riwayat perubahan.

---

## 4. Status Tugas Pelengkapan Bukti Sesi Ini
1. **Bukti recovery / error auth**: **SELESAI PENUH**. 6 skenario dipetakan dengan nama tes, perilaku yang dibuktikan, dan batasnya pada `PHASE-01B-API-AUTH.md` §3.
2. **Audit akses `/master/typeusers`**: **SELESAI PENUH**. Terbukti independen dari tabel peran/kemampuan; tidak ada jalur eskalasi hak akses atau bypass proteksi admin terakhir; 5 tes regresi lulus.
3. **Regresi empat peran**: **SELESAI PENUH**. Diuji live via browser otomatis Playwright pada server UAT nyata untuk 4 peran; 0 uncaught errors; pembedaan mock vs live tercatat.
4. **Lint tanpa `--quiet`**: **SELESAI PENUH**. Dilaporkan terpisah: 0 errors, 1.086 Prettier line-ending formatting warnings (CRLF). Build `npm run build` PASS (`✓ built in 1m`).
5. **Panduan manual UAT & Penerimaan Lengkap**: **SELESAI PENUH & APPROVED**.
   - Seluruh checklist nomor 1–16 telah disetujui manual oleh pemilik: nomor 1–7 dan 9–16 APPROVED pada 16 Oktober 2026 WIB; nomor 8 retest mandiri berhasil dan APPROVED pada 17 Oktober 2026 WIB (terbukti token dikonsumsi dan submit replay ditolak 422).
   - Status penerimaan manual Fase 01b: **DITERIMA PEMILIK**.

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
1. Menunggu review dan konfirmasi pemilik atas rencana kerja Fase 01c di `reports/feature-update/PHASE-01C-PLAN.md` (termasuk konfirmasi keputusan Q-01c-1 sampai Q-01c-3).
2. Setelah rencana dan keputusan disetujui pemilik, laksanakan implementasi subfase 01c (pemisahan seeder, perbaikan BLK-03, validator akun, dan fail-safe storage test).

---

## 8. Klarifikasi Entri CHANGELOG Pemilik
Berdasarkan pemeriksaan `git status`, `git diff`, `git log -S/-G`, dan pemindaian blob history pada HEAD:
- Working tree bersih (hanya `?? console.txt`).
- Frasa entri pemilik `"Pengembangan fitur iteratif via sesi Claude"` **tidak ditemukan** pada `CHANGELOG.md` di working tree **maupun** pada blob `CHANGELOG.md` mana pun di history yang diperiksa. Frasa hanya pernah hidup di berkas `HANDOFF.md` terdahulu.
- Jejak historis: pesan commit `5f18c06` dan `ad937e0` menyebut "owner CHANGELOG.md entry preserved unstaged".
- **Kesimpulan**: entri tersebut pernah berupa perubahan working tree tidak ter-commit lalu **tidak ada lagi**; penyebab persis kehilangan **belum diketahui**. Isi tidak direkonstruksi tanpa sumber. Berkas log `console.txt` milik pemilik tetap dipertahankan.
