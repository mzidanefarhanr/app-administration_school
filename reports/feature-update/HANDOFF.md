# Handoff pengembangan aplikasi sekolah

Checkpoint terbaru: 2026-10-15 (WIB). Branch `main`.
HEAD sebelumnya: `b93cbee` ("docs(phase-01a): record owner acceptance of access and export checks").
Kebijakan commit/push: commit dan push per fase/koreksi ke branch kerja aktif telah **diotorisasi**; merge, force-push, rewrite history, dan deploy manual **tetap tidak diotorisasi**.

Catatan perubahan pemilik: entri `CHANGELOG.md` yang disebut laporan lama ("Pengembangan fitur iteratif via sesi Claude") **tidak ditemukan** pada working tree maupun history yang diperiksa (lihat §7); penyebab kehilangan belum diketahui dan tidak direkonstruksi. Berkas log bukti `console.txt` tetap ada dan **tidak** di-commit. Entri **baru** milik pemilik pada `CHANGELOG.md` (biaya, dll. bila ada) dipertahankan tanpa menyentuh baris itu.

---

## 1. Status Fase
- **Fase 00 — Audit baseline**: PARTIAL (source & environment audit selesai; browser NOT RUN).
- **Fase 01a — Authorization backend**: **IMPLEMENTED + DITERIMA pemilik**.
  - Verifikasi otomatis (51 Pest/PHPUnit tests PASS, 3 race unit tests PASS, build PASS, lint PASS).
  - Verifikasi UAT browser sintetis: 4 peran (Operator, Admin, Principal, Waka) langsung login ke Dashboard tanpa refresh; password salah & akun nonaktif menampilkan modal 422 English; refresh menjaga sesi; logout/switch account bersih.
  - **Persetujuan manual pemilik**: **APPROVED** (15 Oktober 2026 WIB). Retest login #1–7 (mengacu `5f4308d`) dan pengujian tambahan akses/ekspor (mengacu `bea4ccd`); seluruh nomor checklist panduan #1–#14 disetujui.
  - **Penerimaan manual Fase 01a**: **DITERIMA** dalam scope checklist. **Bukan** penerimaan seluruh Fase 01 (subfase 01b/01c belum dikerjakan) maupun deployment.
- **Fase 01b — Konfigurasi API dan error auth**: **AUDIT/RENCANA SELESAI** (implementasi BELUM dijalankan). Laporan: `reports/feature-update/PHASE-01B-API-AUTH-AUDIT.md`. Status review: PENDING.
- **Fase 01c dan fase selanjutnya**: NOT STARTED. BLK-03 dialokasikan ke 01c.

---

## 2. Riwayat Koreksi Inisialisasi Identitas Pasca-Login
Defect "Failed to validate user session" dan console "User data not found" pada `Login_2.vue` berhasil diinvestigasi, direproduksi, dan diperbaiki:
1. **Akar Masalah Terbukti**:
   - `Login_2.vue` mengakses properti usang `authStore().authUser`. Pada refactor commit `5f18c06`, store dialihkan ke state `identity` dan getter `user` (dari `GET /api/check-auth`). Properti `authUser` dihapus tanpa getter kompatibilitas, sehingga bernilai `undefined`.
   - Hal tersebut memicu `throw new Error("User data not found.")` di dalam fungsi `authenticated`, memunculkan dialog "Authentication Error: Failed to validate user session."
   - Sesi Laravel di cookie backend sebenarnya sudah terbentuk (`POST /login` 204), sehingga saat browser di-refresh, router `authNav` memanggil `fetchIdentity()` dan langsung mengizinkan masuk ke Dashboard.
2. **Perbaikan Kode**:
   - `vue-app/src/stores/authStore.js`: menambahkan getter `authUser` dan `AllUserAssign` yang merujuk konsisten ke `identity`.
   - `authStore.js`: menambahkan sequence tracker `_requestId` pada `fetchIdentity()` untuk mendiskualifikasi respons in-flight guest check yang datang terlambat agar tidak menimpa sesi login aktif (anti-race condition deterministik).
   - `vue-app/src/views/pages/auth/Login_2.vue`: menambahkan proteksi submit ganda (`isSubmitting` flag + disabled/loading submit button), menggunakan `ref` reaktif konsisten (`.value`), menunggu `await useAuthStore.fetchIdentity()` siap sebelum navigasi, dan menampilkan pesan English per kategori kegagalan.
   - `laravel-api`: menyelaraskan pesan penolakan akun nonaktif di `LoginRequest.php` dan `EnsureUserIsActive.php` ke bahasa English ("Your account is inactive. Please contact the administrator.").
   - `vue-app/tests/auth-race-condition.test.mjs`: tes Node terisolasi untuk membuktikan getter konsisten dan pencegahan race condition respons guest usang.
   - `laravel-api/tests/Feature/Auth/AuthenticationTest.php`: menambahkan tes kontrak identitas `check-auth` pasca-login, pesan English akun nonaktif, dan unauthenticated check-auth.

---

## 3. Bukti & Artefak yang Tersedia
- **Berkas bukti sintetis**:
  - `reports/feature-update/evidence/uat-roles-en.png` (tangkapan layar Roles & Abilities English)
  - `reports/feature-update/evidence/uat-operator-dashboard.png` (tangkapan layar dashboard operator)
  - `reports/feature-update/evidence/uat-operator-profile-dropdown.png` (tangkapan layar dropdown profil operator)
  - `reports/feature-update/evidence/uat-students-export.csv` (unduhan CSV siswa sintetis dari endpoint terproteksi)
- **Log bukti pemilik**:
  - `console.txt` (catatan error console awal dari pengujian operator oleh pemilik)
- **Hasil verifikasi otomatis**:
  - PHPUnit / Pest test suite: **51 passed (131 assertions)** di database terisolasi SQLite in-memory.
  - Frontend unit race test: **3 passed (0 failed)** (`vue-app/tests/auth-race-condition.test.mjs`).
  - Frontend build: `npm run build` PASS (`✓ built in 42s`).
  - Pint style check: PASS pada seluruh berkas backend yang disentuh.
  - ESLint: 0 errors pada berkas store dan page login yang disentuh.
- **Batas pengujian**:
  - Seluruh verifikasi berjalan pada database/data sintetis terisolasi (`school_access_uat` dan SQLite `:memory:`).
  - Modul surat, izin, dan pembelajaran belum tersedia (hanya katalog kemampuan).
  - Tidak ada pengujian pada database operasional.

---

## 4. Keputusan & Blocker Terbuka
- **DEC-03** (peran & kemampuan fleksibel): **APPROVED** (2026-10-15).
- **DEC-14** (register publik ditutup, status_active ditolak, ekspor via students.export, legacy mapping per nama, UI English): **APPROVED** (2026-10-14 WIB).
- **BLK-03**: **OPEN** (`DatabaseSeeder` masih memuat identitas asli; dilarang dijalankan untuk pengujian).
- **Keputusan autentikasi lain**: makna `status_user_id` (jangan disamakan dengan `status_active`), reset password end-to-end, bootstrap pengguna pertama, deployment CORS/Sanctum, strategi pencabutan token vs sesi: **OPEN**.
- **UI Language**: Seluruh antarmuka dalam scope tetap **English**.

---

## 5. Server & Lingkungan Aktif
Server UAT lokal yang teridentifikasi sedang berjalan (jangan dihentikan sembarangan):
- **API UAT**: port `8001` (`php artisan serve --host=127.0.0.1 --port=8001 --env=uat`)
- **SPA UAT**: port `4001` (`vite --mode uat --host 127.0.0.1 --port 4001`)
- **Database UAT**: MySQL `school_access_uat` (disposable, terpisah dari `backdata_administrations`)
- Server operasional pada port `8000` dibiarkan utuh.

---

## 6. Checklist Retest Pemilik (Fase 01a Review)
Pemilik dapat langsung melakukan verifikasi manual dari form login pada URL `http://127.0.0.1:4001/auth/login`:
1. [x] Login `uat.operator` + sandi sintetis: form langsung berhasil divalidasi, dialog success tampil, lalu langsung masuk ke Dashboard (`/`) **tanpa refresh**. — **APPROVED** (2026-10-15 WIB).
2. [x] Dashboard operator: 0 console errors, tidak ada pemanggilan `/api/users`, widget online users tersembunyi, menu Admin tersembunyi. — **APPROVED** (2026-10-15 WIB).
3. [x] Refresh halaman Dashboard: sesi tetap aktif dan valid di Dashboard. — **APPROVED** (2026-10-15 WIB).
4. [x] Buka dropdown profil di topbar: menampilkan "UAT Operator Sekolah" dan "operator-sekolah", tombol Logout berfungsi normal. — **APPROVED** (2026-10-15 WIB).
5. [x] Login `uat.admin`: langsung masuk ke Dashboard, widget online users tampil 5 akun, menu Admin tampil lengkap. — **APPROVED** (2026-10-15 WIB).
6. [x] Login `uat.inactive`: ditolak dengan dialog "Login Failed: Your account is inactive. Please contact the administrator." — **APPROVED** (2026-10-15 WIB).
7. [x] Login password salah: ditolak dengan dialog "Login Failed: These credentials do not match our records." — **APPROVED** (2026-10-15 WIB).
8. [x] Hak ekspor siswa (penambahan/pencabutan izin runtime, unduhan CSV, 403 tanpa izin, batas 422), kontrol Roles & Abilities English, topbar, dan bahasa UI mengikuti checklist lengkap di `reports/feature-update/PHASE-01A-MANUAL-REVIEW.md` (nomor #5–#11, #13–#14). — **APPROVED** (2026-10-15 WIB).

Catatan: Seluruh nomor checklist Fase 01a (#1–#14) **APPROVED** pemilik. Persetujuan ini mencakup scope checklist Fase 01a dan **tidak** memperluas ke deployment, modul yang belum tersedia (surat/izin/pembelajaran), atau subfase 01b/01c. **BLK-03** dan keputusan autentikasi lain tetap **OPEN**.

---

## 7. Fase 01b — Ringkasan Audit & Rencana (2026-10-15)
Laporan penuh: `reports/feature-update/PHASE-01B-API-AUTH-AUDIT.md`. Status: **AUDIT/RENCANA** (implementasi BELUM). Temuan utama (source review HEAD `b93cbee`):
- **Interceptor tidak aktif**: interceptor dipasang pada global `axios`, bukan instance `apiLogin` yang dipakai seluruh kode → tidak berjalan.
- **Guard router**: `beforeEnter` parent `/` tidak dievaluasi pada transisi antar-child; gating `/master` tidak dicek ulang.
- **Error handling**: 401/403/419/422/429/5xx/timeout/tanpa-response tidak ditangani seragam; belum ada `timeout`.
- **Logout tak konsisten**: `Logout.vue` mengakses `response.data.id` pada 204, memanggil `/logout` dua kali, memakai helper tak terdefinisi.
- **Reset password**: backend lengkap (routes+broker+notification), frontend **gap** (link placeholder tanpa route/form). Butuh keputusan pemilik (Q1) apakah masuk scope 01b.
- **Kontrak auth**: aktif = **cookie session Sanctum** saja (tanpa bearer); tidak perlu mengubah strategi token/sesi.
- Rencana C-01..C-06 (§4 laporan) dan rencana verifikasi (§5 laporan) sudah disusun; keputusan pemilik Q1–Q3 di §7 laporan.

## 8. Klarifikasi Entri CHANGELOG Pemilik
Berdasarkan pemeriksaan `git status`, `git diff`, `git log -S/-G`, dan pemindaian blob history pada HEAD `b93cbee`:
- Working tree bersih (hanya `?? console.txt`).
- Frasa entri pemilik `"Pengembangan fitur iteratif via sesi Claude"` **tidak ditemukan** pada `CHANGELOG.md` di working tree **maupun** pada blob `CHANGELOG.md` mana pun di history yang diperiksa. Frasa hanya pernah hidup di `reports/feature-update/HANDOFF.md`.
- Jejak terakhir: pesan commit `5f18c06` dan `ad937e0` menyebut "owner CHANGELOG.md entry preserved unstaged".
- **Kesimpulan**: entri tersebut pernah berupa perubahan working tree tidak ter-commit lalu **tidak ada lagi**; penyebab persis kehilangan **belum diketahui**. Isi tidak direkonstruksi tanpa sumber.
