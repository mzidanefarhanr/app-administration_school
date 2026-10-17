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
- **Fase 01c — Isolasi pengujian dan fixture**: **IMPLEMENTED & FULLY VERIFIED** (DEC-16 APPROVED 2026-10-17 WIB; laporan `PHASE-01C-ISOLATION-FIXTURES.md`, panduan manual `PHASE-01C-MANUAL-REVIEW.md`; review pemilik **PENDING**).
- **BLK-03**: **CLOSED pada source aktif** (seluruh akun pengguna riil dan data siswa riil telah dibersihkan dari seeder aktif; riwayat Git masa lalu tidak diubah).
- **Fase 02 dan fase selanjutnya**: NOT STARTED.

---

## 2. Pemeriksaan yang Dijalankan & Hasil (Fase 01c)
1. **Automated Backend**:
   - Perintah: `php artisan test` pada database SQLite in-memory `:memory:` terisolasi.
   - Hasil: **82 passed (346 assertions)**, bertambah 22 tes baru (`BootstrapAdminTest` 5 passed, `StorageIsolationTest` 3 passed, `FactoryIntegrityTest` 3 passed, `UserValidationTest` 5 passed, `SeederIsolationTest` 6 passed).
   - Style check: `./vendor/bin/pint --test ...` (26 files) → **PASS** (100% clean).
2. **Automated Frontend**:
   - Perintah: `node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`.
   - Hasil: **15 passed (0 failed)**.
   - Build: `npm run build` → **PASS** (`✓ built in 47s`, 0 errors).
   - Lint: `npx eslint ... --quiet` pada seluruh berkas yang disentuh → **0 errors**.
3. **Verifikasi Browser Nyata (UAT Live 127.0.0.1:4001 & 127.0.0.1:8001)**:
   - Dialog New Account (`/master/accounts`): NIK divalidasi tepat 16 digit string, password divalidasi minimal 12 karakter, kolom Name tidak lagi memblokir nama homonim.
   - Form Ubah Password Profil (`/profile/user`): Label "New Password (min 12 chars)", input kurang dari 12 karakter ditolak validasi.
   - Form Reset Password (`/password-reset/:token`): Label "New Password (min 12 chars)", input kurang dari 12 karakter memunculkan modal dialog "Validation Error: Password must be at least 12 characters long.".
4. **Lingkungan & Keamanan**:
   - Seluruh tes hanya menggunakan database sintetis (`school_access_uat` dan SQLite `:memory:`) dan akun uji. Tidak ada data operasional yang disentuh.
   - Sumber aktif bersih dari data identitas pribadi (PII).

---

## 3. Berkas yang Berubah pada Fase 01c
- Backend:
  - `laravel-api/config/filesystems.php`: disk storage `testing` di bawah `storage/framework/testing/disks/testing`.
  - `laravel-api/phpunit.xml`: `FILESYSTEM_DISK=testing force="true"`.
  - `laravel-api/tests/CreatesApplication.php`: fail-safe `guardAgainstUnsafeStorage()`.
  - `laravel-api/app/Providers/AppServiceProvider.php`: `Password::defaults(fn () => Password::min(12))`.
  - `laravel-api/app/Http/Controllers/UserController.php`: hapus unique `name`, validasi NIK `digits:16`, password `Password::defaults()`, format error 422 standar.
  - `laravel-api/app/Console/Commands/BootstrapAdminCommand.php` (baru): CLI interaktif `app:bootstrap-admin` dengan password tersembunyi via secret prompt.
  - `laravel-api/database/factories/{StudentFactory.php, EmployeeFactory.php, UserFactory.php}`: definisi factory terisi lengkap dengan relasi FK valid dan NIK string 16 digit.
  - `laravel-api/database/seeders/ReferenceDataSeeder.php` (baru): seeder referensi murni bebas PII, idempotent, tidak mereset izin admin.
  - `laravel-api/database/seeders/SyntheticDataSeeder.php` (baru): 15 siswa dummy untuk dev/testing dengan fail-safe `guardEnvironment()`.
  - `laravel-api/database/seeders/DatabaseSeeder.php`: bersih dari akun riil; hanya memanggil `ReferenceDataSeeder`.
  - `laravel-api/database/seeders/{StudentSeeder.php, StudentRombelSeeder.php, SchoolProfileSeeder.php, SchoolRombelSeeder.php, AccessControlSeeder.php, SchoolYearSeeder.php, SemesterSeeder.php, StatusUserSeeder.php, TypeUserSeeder.php, UatSeeder.php}`: pembersihan PII dan perbaikan idempotensi.
  - `laravel-api/tests/Feature/Access/{StorageIsolationTest.php, FactoryIntegrityTest.php, BootstrapAdminTest.php, UserValidationTest.php, SeederIsolationTest.php}` (baru): 22 tes pengujian isolasi storage, factory, bootstrap, validasi, dan seeder.
- Frontend:
  - `vue-app/src/utils/useResourceManager.js`: dukungan `requiredFields` terpisah dari `uniqueFields`.
  - `vue-app/src/views/pages/master/Accounts.vue`: penghapusan keunikan `name`, validasi NIK 16 digit, pesan password min 12 karakter.
  - `vue-app/src/views/pages/auth/Profile.vue`: validasi form ubah password min 12 karakter.
  - `vue-app/src/views/pages/auth/ResetPassword.vue`: validasi form reset password min 12 karakter.
  - `vue-app/tests/phase-01b-api-auth.test.mjs`: Test 9 validasi klien password min 12 karakter.
- Dokumentasi & Kontrak:
  - `docs/DECISIONS.md`: dicatat DEC-16 APPROVED (Q-01c-1 s/d Q-01c-3).
  - `docs/DATABASE.md` & `docs/API-CONTRACTS.md`: pembaruan kontrak validasi dan isolasi storage.
  - `reports/feature-update/PHASE-01C-ISOLATION-FIXTURES.md` (baru): laporan implementasi Fase 01c lengkap.
  - `reports/feature-update/PHASE-01C-MANUAL-REVIEW.md` (baru): panduan manual UAT 01c (8 checklist).
  - `reports/feature-update/INDEX.md`, `roadmap/ROADMAP.md`, `CHANGELOG.md`: sinkronisasi status.

---

## 4. Status Tugas Fase 01c
1. **Isolasi Database & Storage**: **SELESAI PENUH**. SQLite in-memory guard aktif, storage testing disk fail-safe aktif.
2. **Pembersihan BLK-03 (PII Seeder)**: **SELESAI PENUH PADA SOURCE AKTIF**. Akun riil dan data siswa riil dibersihkan; riwayat Git masa lalu tidak diubah.
3. **Pemisahan Seeder & Bootstrap Admin**: **SELESAI PENUH**. `ReferenceDataSeeder` referensi murni; `SyntheticDataSeeder` dummy aman; `app:bootstrap-admin` CLI interaktif dengan secret prompt.
4. **Validasi Akun & Standar Password 12 Karakter**: **SELESAI PENUH**. Nama boleh sama, NIK string 16 digit, password min 12 karakter di seluruh alur.
5. **Panduan Manual UAT 01c**: **SELESAI PENUH**. 8 butir checklist siap diuji pemilik pada `reports/feature-update/PHASE-01C-MANUAL-REVIEW.md`.

---

## 5. Keputusan & Blocker Terbuka
- **DEC-03** (peran & kemampuan): **APPROVED** (2026-10-15).
- **DEC-14** (register publik ditutup, status_active ditolak, ekspor via students.export, legacy mapping per nama, UI English): **APPROVED** (2026-10-14 WIB).
- **DEC-15** (alur error auth, CSRF 419 single retry, reset password scope 01b via mail log): **APPROVED** (2026-10-16 WIB).
- **DEC-16** (isolasi fixture, bootstrap admin CLI, standar password 12 karakter): **APPROVED** (2026-10-17 WIB).
- **BLK-03**: **CLOSED pada source aktif** (data riil dibersihkan dari seeder aktif; riwayat Git historis tidak di-rewrite).
- **Keputusan autentikasi lain yang masih OPEN**: makna `status_user_id` (jangan disamakan dengan `status_active`), deployment CORS/Sanctum produksi, strategi pencabutan token vs sesi (tidak menghambat 01c).
- **UI Language**: Seluruh antarmuka dalam scope tetap **English**.

---

## 6. Server & Lingkungan Aktif
Server UAT lokal yang diketahui masih berjalan (jangan dihentikan sembarangan):
- **API UAT**: port `8001` (PID 12720, `php artisan serve --host=127.0.0.1 --port=8001 --env=uat`)
- **SPA UAT**: port `4001` (PID 16624, `vite --mode uat --host 127.0.0.1 --port 4001`)
- **Database UAT**: MySQL `school_access_uat` (disposable, terpisah dari `backdata_administrations`)
- Server operasional pada port `8000` dibiarkan utuh.

---

## 7. Pekerjaan Pertama Sesi Baru
1. Menunggu review manual pemilik atas checklist Fase 01c di `reports/feature-update/PHASE-01C-MANUAL-REVIEW.md`.
2. Setelah seluruh checklist Fase 01c disetujui pemilik, tandai Fase 01 secara keseluruhan selesai dan lanjutkan perencanaan ke Fase 02 (Integritas Operasional: audit log, soft delete, backup & restore).

---

## 8. Klarifikasi Entri CHANGELOG Pemilik
Berdasarkan pemeriksaan `git status`, `git diff`, `git log -S/-G`, dan pemindaian blob history pada HEAD:
- Working tree bersih (hanya `?? console.txt`).
- Frasa entri pemilik `"Pengembangan fitur iteratif via sesi Claude"` **tidak ditemukan** pada `CHANGELOG.md` di working tree **maupun** pada blob `CHANGELOG.md` mana pun di history yang diperiksa. Frasa hanya pernah hidup di berkas `HANDOFF.md` terdahulu.
- Jejak historis: pesan commit `5f18c06` dan `ad937e0` menyebut "owner CHANGELOG.md entry preserved unstaged".
- **Kesimpulan**: entri tersebut pernah berupa perubahan working tree tidak ter-commit lalu **tidak ada lagi**; penyebab persis kehilangan **belum diketahui**. Isi tidak direkonstruksi tanpa sumber. Berkas log `console.txt` milik pemilik tetap dipertahankan.
