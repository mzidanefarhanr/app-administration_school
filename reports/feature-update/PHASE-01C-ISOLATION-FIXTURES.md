# Laporan Fase 01c — Isolasi Pengujian, Fixture Sintetis, dan Penanganan BLK-03

- Tanggal WIB: 2026-10-17
- Mode: **implementasi + verifikasi** (backend Laravel + frontend Vue SPA + lingkungan UAT lokal terisolasi)
- Scope instruksi pemilik: Implementasi Fase 01c berdasarkan rencana `PHASE-01C-PLAN.md` dan keputusan pemilik `DEC-16` (Q-01c-1 s/d Q-01c-3).
- Branch / baseline SHA: `main` @ `9de8f6d` / `bc53877`.
- Lingkungan: Windows, PHP 8.1.25, Composer 2.8.2, Node 24.18.0, npm 11.4.2, MySQL 3306. UAT: SPA `127.0.0.1:4001`, API `127.0.0.1:8001`, DB `school_access_uat`. DB unit test backend = SQLite in-memory `:memory:`.
- Status teknis: **IMPLEMENTED & FULLY VERIFIED**.
- Status review: **PENDING** (menunggu review/persetujuan manual pemilik atas checklist UAT Fase 01c).

---

## 1. Outcome dan Cakupan Implementasi

### A. Isolasi Pengujian dan Storage Fail-Safe
1. **Guard Database Tetap Ditegakkan**:
   - `guardAgainstUnsafeTestDatabase()` di `tests/CreatesApplication.php` memastikan seluruh pengujian otomatis hanya berjalan pada SQLite in-memory (`:memory:`) dalam `APP_ENV=testing`. Upaya mengarahkan test ke MySQL operasional digagalkan seketika.
2. **Fail-Safe Disk Storage Pengujian**:
   - Ditambahkan disk `testing` pada `config/filesystems.php` yang mengarah ke `storage/framework/testing/disks/testing`.
   - `phpunit.xml` menyetel `<env name="FILESYSTEM_DISK" value="testing" force="true"/>`.
   - Ditambahkan `guardAgainstUnsafeStorage()` pada `CreatesApplication.php` yang membatalkan pengujian bila disk storage pengujian mengarah ke storage publik atau operasional di luar `framework/testing`.
   - Diuji otomatis pada `tests/Feature/Access/StorageIsolationTest.php` (3 passed).
3. **Penyempurnaan Factory Model**:
   - `StudentFactory.php`: Menghasilkan record siswa sintetis lengkap dengan foreign key relasi valid (`gender_id`, `religion_id`, `education_level_id`, `family_status_id`) dan NIK 16 digit string dengan nol awal dipertahankan.
   - `EmployeeFactory.php`: Menghasilkan record pegawai sintetis dengan relasi valid ke `users.nik` (`user_nik`) dan foreign key kepegawaian valid (`employee_status_id`, `employee_type_id`, `employment_id`, `subject_id`, `religion_id`, `district_id`, `marital_status_id`, `gender_id`).
   - `UserFactory.php`: NIK diformat 16 digit string (`0###############`), password default selaras dengan akun pengujian.
   - Diuji otomatis pada `tests/Feature/Access/FactoryIntegrityTest.php` (3 passed).

### B. Pembersihan Source Aktif dari Identitas Pribadi (Penutupan BLK-03)
1. **Pembersihan Akun Riil dari `DatabaseSeeder.php`**:
   - Seluruh akun pengguna riil yang sebelumnya terdapat pada `DatabaseSeeder.php` telah dibersihkan sepenuhnya.
   - `DatabaseSeeder.php` kini hanya memanggil `ReferenceDataSeeder::class`, menghasilkan **0 akun pengguna** secara default.
2. **Pembersihan Data Siswa Riil dari Seeder**:
   - Berkas `StudentSeeder.php` (sebelumnya memuat 62.784 baris data riil siswa) dibersihkan dan dialihkan menjadi wrapper pemanggil `SyntheticDataSeeder::class`.
   - Berkas `StudentRombelSeeder.php` (sebelumnya memuat 11.416 baris penautan NIK riil) dibersihkan dari NIK riil.
   - Berkas `SchoolProfileSeeder.php` dibersihkan dari nomor kontak pribadi dan hanya mengaitkan kepala sekolah bila akun pengguna memang tersedia.
3. **Pemisahan Seeder Referensi vs Dummy**:
   - `ReferenceDataSeeder.php`: Memuat data referensi murni (katalog peran/kemampuan, status akun, tipe akun, wilayah, jenjang, agama, rombel, dll.) yang bebas dari data pribadi. Seeder ini sepenuhnya **idempotent**: aman dijalankan berulang tanpa menduplikasi data atau mereset izin yang telah disesuaikan admin.
   - `SyntheticDataSeeder.php`: Khusus untuk lingkungan pengembangan/UAT terisolasi, menghasilkan tepat 15 siswa sintetis dengan rombel dan NIK fiktif. Dilengkapi metode `guardEnvironment()` yang menolak eksekusi pada lingkungan produksi atau database operasional.
   - `UatSeeder.php`: Dilengkapi `guardEnvironment()` dan menghasilkan password acak minimal 12 karakter bila `UAT_SEED_PASSWORD` tidak disediakan.
4. **Catatan Batasan Riwayat Git**:
   - Sesuai aturan repositori, pembersihan ini dilakukan pada source aktif (HEAD). Tidak dilakukan rewrite history Git remote (`git filter-repo` / force-push dilarang). Data pada commit historis masa lalu tidak direkonstruksi atau dimutasi.

### C. Bootstrap Administrator Pertama (`app:bootstrap-admin`)
- Sesuai keputusan `DEC-16` (Q-01c-1), dibuat perintah Artisan interaktif:
  `php artisan app:bootstrap-admin`
- **Fitur Keamanan**:
  - Memeriksa keberadaan pengguna aktif yang memiliki kemampuan pengelolaan akses (`canManageAccess()`). Jika sudah ada, perintah langsung dibatalkan (exit code 1).
  - Kata sandi wajib diketik tersembunyi via secret prompt (`$this->secret()`) dengan konfirmasi; kata sandi tidak pernah diterima melalui argumen CLI untuk mencegah pencatatan pada riwayat bash atau process listing.
  - Menampilkan ringkasan lingkungan dan koneksi database target sebelum eksekusi, serta meminta konfirmasi eksplisit operator.
  - Memvalidasi NIK tepat 16 digit string, email valid, username unik, dan password memenuhi `Password::defaults()` (min 12 karakter).
  - Membuat akun aktif dan menautkan peran `admin-sistem` secara atomik di dalam transaksi database.
  - Diuji otomatis pada `tests/Feature/Access/BootstrapAdminTest.php` (5 passed).

### D. Standar Password 12 Karakter dan Validasi Akun
- Sesuai keputusan `DEC-16` (Q-01c-3), standar password aplikasi ditetapkan minimal 12 karakter tanpa kewajiban komposisi huruf besar/angka (`Password::defaults()` diatur pada `AppServiceProvider.php`).
- Diterapkan secara konsisten di seluruh alur terkait:
  - Backend: `UserController.php` (pembuatan dan update `password_new`), `BootstrapAdminCommand.php`, `NewPasswordController.php`.
  - Frontend: `Accounts.vue` (validasi dialog akun min 12 karakter), `Profile.vue` (validasi form ubah password min 12 karakter), `ResetPassword.vue` (validasi form reset password min 12 karakter).
  - Akun lama tidak dipaksa mengubah kata sandi massal.
- **Validasi Akun (`UserController.php` & `Accounts.vue`)**:
  - Aturan keunikan pada kolom `name` dihapus (nama boleh sama / homonim diizinkan sesuai aturan domain sekolah).
  - NIK divalidasi tepat 16 digit angka string (`digits:16`). Nilai disimpan string dan mempertahankan nol awal.
  - Foreign key `type_user_id` dan `status_user_id` divalidasi dengan `exists:...,id`.
  - Format respon validasi 422 distandarisasi ke `{ message: "The given data was invalid.", errors: { field: [msg] } }`.
  - Diuji otomatis pada `tests/Feature/Access/UserValidationTest.php` (5 passed).

---

## 2. File yang Disentuh pada Fase 01c

| File | Perubahan |
|---|---|
| `docs/DECISIONS.md` | Dicatat DEC-16 APPROVED (Q-01c-1 s/d Q-01c-3). |
| `docs/DATABASE.md` | Catatan fail-safe storage test, NIK 16 digit, dan penghapusan keunikan nama. |
| `docs/API-CONTRACTS.md` | Kontrak validasi akun, bootstrap admin CLI, dan kebijakan password 12 karakter. |
| `laravel-api/config/filesystems.php` | Penambahan disk `testing` terisolasi di bawah `framework/testing`. |
| `laravel-api/phpunit.xml` | Penambahan `FILESYSTEM_DISK=testing force="true"`. |
| `laravel-api/tests/CreatesApplication.php` | Penambahan fail-safe `guardAgainstUnsafeStorage()`. |
| `laravel-api/app/Providers/AppServiceProvider.php` | Konfigurasi `Password::defaults(fn () => Password::min(12))`. |
| `laravel-api/app/Http/Controllers/UserController.php` | Hapus keunikan `name`, validasi NIK `digits:16`, password `Password::defaults()`, format error 422 standar. |
| `laravel-api/app/Console/Commands/BootstrapAdminCommand.php` (baru) | Perintah CLI interaktif `app:bootstrap-admin` dengan secret prompt dan fail-safe access manager. |
| `laravel-api/database/factories/StudentFactory.php` | Definisi factory siswa sintetis dengan relasi FK valid dan NIK string 16 digit. |
| `laravel-api/database/factories/EmployeeFactory.php` | Definisi factory pegawai sintetis dengan relasi valid ke `users.nik` dan FK status/jabatan. |
| `laravel-api/database/factories/UserFactory.php` | Penyelarasan format NIK string 16 digit. |
| `laravel-api/database/seeders/ReferenceDataSeeder.php` (baru) | Seeder referensi murni bebas data pribadi, idempotent, tidak mereset izin admin. |
| `laravel-api/database/seeders/SyntheticDataSeeder.php` (baru) | Seeder 15 siswa sintetis untuk dev/UAT dengan guard lingkungan aman. |
| `laravel-api/database/seeders/DatabaseSeeder.php` | Bersihkan akun riil; hanya memanggil `ReferenceDataSeeder`. |
| `laravel-api/database/seeders/StudentSeeder.php` | Bersihkan 62k baris data riil; wrapper sintetis untuk non-produksi. |
| `laravel-api/database/seeders/StudentRombelSeeder.php` | Bersihkan 11k baris NIK riil; wrapper bersih tanpa PII. |
| `laravel-api/database/seeders/SchoolRombelSeeder.php` | Rombel default `class_teach_id = null` (tanpa ketergantungan ID user), idempotent `firstOrCreate`. |
| `laravel-api/database/seeders/SchoolProfileSeeder.php` | Bersihkan nomor kontak pribadi, relasi principal dinamis aman. |
| `laravel-api/database/seeders/AccessControlSeeder.php` | Pertahankan izin peran yang telah dikonfigurasi admin saat seeder di-rerun. |
| `laravel-api/database/seeders/SchoolYearSeeder.php` | Idempotent `firstOrCreate`. |
| `laravel-api/database/seeders/SemesterSeeder.php` | Idempotent `firstOrCreate`. |
| `laravel-api/database/seeders/StatusUserSeeder.php` | Idempotent `firstOrCreate`. |
| `laravel-api/database/seeders/TypeUserSeeder.php` | Idempotent `firstOrCreate`. |
| `laravel-api/database/seeders/UatSeeder.php` | Guard lingkungan aman, password sintetis min 12 karakter. |
| `laravel-api/tests/Feature/Access/StorageIsolationTest.php` (baru) | 3 tes fail-safe storage dan operasi isolated disk. |
| `laravel-api/tests/Feature/Access/FactoryIntegrityTest.php` (baru) | 3 tes integritas StudentFactory & EmployeeFactory. |
| `laravel-api/tests/Feature/Access/BootstrapAdminTest.php` (baru) | 5 tes bootstrap admin (keberadaan manager, validasi NIK/password, atomic create, cancel). |
| `laravel-api/tests/Feature/Access/UserValidationTest.php` (baru) | 5 tes validasi akun (homonim diterima, NIK 16 digit, password 12 char, FK exists). |
| `laravel-api/tests/Feature/Access/SeederIsolationTest.php` (baru) | 6 tes isolasi seeder (DatabaseSeeder 0 akun, ReferenceDataSeeder rerun aman, SyntheticDataSeeder guard, 15 siswa dummy). |
| `vue-app/src/utils/useResourceManager.js` | Dukungan `requiredFields` terpisah dari `uniqueFields`. |
| `vue-app/src/views/pages/master/Accounts.vue` | Hapus keunikan `name` di UI, validasi NIK 16 digit, pesan password min 12 karakter. |
| `vue-app/src/views/pages/auth/Profile.vue` | Validasi form ubah password min 12 karakter. |
| `vue-app/src/views/pages/auth/ResetPassword.vue` | Validasi form reset password min 12 karakter. |
| `vue-app/tests/phase-01b-api-auth.test.mjs` | Test 9 validasi klien password min 12 karakter. |
| `reports/feature-update/PHASE-01C-ISOLATION-FIXTURES.md` (baru) | Laporan implementasi Fase 01c lengkap. |
| `reports/feature-update/PHASE-01C-MANUAL-REVIEW.md` (baru) | Panduan review manual UAT 01c (8 checklist). |
| `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `CHANGELOG.md` | Sinkronisasi status. |

---

## 3. Bukti Verifikasi Otomatis, Lint, dan Build

### A. Backend Test Suite (PHPUnit / Pest di DB SQLite In-Memory Terisolasi)
- Perintah: `php artisan test`
- Hasil: **82 passed (346 assertions)**.
- Rincian suite:
  - `BootstrapAdminTest`: 5 passed
  - `StorageIsolationTest`: 3 passed
  - `FactoryIntegrityTest`: 3 passed
  - `UserValidationTest`: 5 passed
  - `SeederIsolationTest`: 6 passed
  - `TypeUserAccessTest`: 5 passed
  - `AuthorizationTest`: 23 passed
  - `StudentExportTest`: 13 passed
  - `DatabaseIsolationTest`: 2 passed
  - `AuthenticationTest`: 6 passed
  - `PasswordResetTest`: 6 passed
  - `EmailVerificationTest`: 2 passed
  - `RegistrationTest`: 1 passed
  - `ExampleTest`: 2 passed
- Style check: `./vendor/bin/pint --test ...` (26 files) → **PASS** (100% clean).

### B. Frontend Automated Test Runner (Node.js Test Runner)
- Perintah: `node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`
- Hasil: **15 passed (0 failed)**.
- Mencakup verifikasi validasi password min 12 karakter, penanganan token 422, anti-race sesi, route guard child, dan logout.

### C. Build & Lint Frontend
- Lint: `npx eslint src/views/pages/master/Accounts.vue src/views/pages/auth/Profile.vue src/views/pages/auth/ResetPassword.vue src/utils/useResourceManager.js --quiet` → **0 errors**.
- Build: `npm run build` → **PASS** (`✓ built in 47s`, 0 errors).

### D. Verifikasi Browser UAT Nyata (Live `127.0.0.1:4001`)
1. **Dialog Akun (`/master/accounts`)**:
   - Membuka form "New Account".
   - Memasukkan password kurang dari 12 karakter → pesan kuning muncul: *"Password must be at least 12 characters."*.
   - Memasukkan NIK kurang dari 16 digit → pesan kuning muncul: *"NIK must be exactly 16 digits."*.
   - Kolom Name tidak lagi menampilkan pesan penolakan homonim.
2. **Form Ubah Password Profil (`/profile/user`)**:
   - Label ditampilkan: *"New Password (min 12 chars)"*.
   - Memasukkan password baru kurang dari 12 karakter ditolak validasi klien.
3. **Form Reset Password (`/password-reset/:token`)**:
   - Label ditampilkan: *"New Password (min 12 chars)"*.
   - Memasukkan password kurang dari 12 karakter memicu modal dialog: *"Validation Error: Password must be at least 12 characters long."*.

---

## 4. Status Blocker dan Keputusan

- **BLK-03**: **CLOSED (pada source aktif)**. Seluruh data akun riil pada `DatabaseSeeder.php` dan ribuan baris data siswa riil pada `StudentSeeder.php` serta `StudentRombelSeeder.php` telah dibersihkan dari source aktif dan digantikan oleh seeder referensi murni serta fixture sintetis terukur. Sesuai kebijakan keamanan, riwayat commit Git historis masa lalu tidak diubah.
- **DEC-16**: **APPROVED** (2026-10-17 WIB).
- **Fase 01a**: **DITERIMA pemilik** (2026-10-15 WIB).
- **Fase 01b**: **DITERIMA pemilik** (2026-10-17 WIB).
- **Fase 01c**: **IMPLEMENTED & FULLY VERIFIED**; menunggu review manual pemilik.
- **Fase 01 Secara Keseluruhan**: Belum ditutup penuh sampai review manual Fase 01c disetujui pemilik.
- **Keputusan Terbuka yang Belum Menghambat**: Makna `status_user_id` (tetap OPEN, tidak disamakan dengan `status_active`), deployment CORS/Sanctum produksi, dan strategi pencabutan token vs sesi.
