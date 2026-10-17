# Laporan Subfase 02a — Audit Server dan Penonaktifan Rollback Klien

- Tanggal WIB: 2026-10-17
- Branch kerja: `main`
- Status teknis: **IMPLEMENTED & FULLY VERIFIED**
- Status review pemilik: **PENDING** (menunggu pemeriksaan manual checklist 02a di `PHASE-02A-MANUAL-REVIEW.md`)
- Prasyarat: Fase 01 telah DITERIMA PENUH dan DITUTUP; kebijakan DEC-17 APPROVED
- Database & storage: **SQLite in-memory `:memory:` terisolasi**, disk storage `testing` terisolasi, API UAT port 8001 / SPA UAT port 4001
- Batasan klaim: **Perlindungan integritas audit dan penonaktifan modifikasi/rollback ditegakkan pada lapisan API aplikasi dan ORM Eloquent, bukan merupakan klaim kekebalan terhadap administrator sistem operasi atau administrator database langsung (root DB/OS admin).**

---

## 1. Ringkasan Eksekutif

Subfase 02a mengimplementasikan audit trail terpercaya berbasis server (*immutable append-only audit trail*) dan menutup seluruh celah manipulasi log dari sisi klien sesuai keputusan pemilik **DEC-17**:

1. **Penutupan Mutasi & Rollback Klien**:
   - Endpoint `POST /api/activities` (pembuatan log oleh klien), `PUT/PATCH /api/activities/{id}` (modifikasi & rollback), dan `DELETE /api/activities/{id}` (penghapusan log) dinonaktifkan secara tegas dengan respons standar **HTTP 405 Method Not Allowed**.
   - Model `Activity` mengunci catatan audit pada level Eloquent via callback `booted()`: percobaan `update()` atau `delete()` langsung melempar `\RuntimeException`.
2. **Perlindungan Akses Baca Audit & Sanitasi Serialisasi**:
   - Pembacaan log audit (`GET /api/activities` dan `GET /api/activities/{id}`) dilindungi oleh kemampuan bernama terpusat **`audit.view`** (dapat dikonfigurasi admin sistem via `/master/roles`).
   - Guest ditolak `401 Unauthorized`; pengguna tanpa kemampuan ditolak `403 Forbidden`.
   - Log yang dikembalikan memuat relasi pengguna dengan pemilihan kolom terbatas (`user:id,name,username`). Terbukti respons JSON pembacaan audit tidak mengekspos kata sandi, token, maupun hash bcrypt `$2y$`.
3. **Pencatatan Otomatis & Penegakan Server-Side**:
   - Actor (`user_id`), target (`table_name`, `record_id`), waktu (`created_at`), dan aksi (`information`) ditentukan oleh server berdasarkan sesi aktif (`Auth::id()`). Klien tidak dapat memalsukan actor.
   - Trait `Loggable.php` menerapkan **allowlist atribut** eksplisit per model domain.
   - Kolom rahasia dan kredensial disaring secara ketat: kata sandi mentah, password baru, remember token, token otentikasi, dan hash kata sandi tidak pernah masuk ke payload audit.
4. **Audit Mutasi Pivot & Transaksionalitas**:
   - Mutasi penetapan peran (`role_user`) pada `AccessAssignmentController` dan sinkronisasi kemampuan peran (`ability_role`) pada `RoleController` dicatat transaksional.
   - Kegagalan transaksi domain maupun kegagalan proses INSERT audit membatalkan (rollback) seluruh perubahan domain dan pivot secara atomik.
5. **Preservasi Jejak Historis & Proteksi Rekursi**:
   - Reentrancy guard (`$isLoggingAudit`) mencegah loop logging rekursif.
   - Relasi pengguna pada `Activity` menggunakan `withTrashed()` sehingga identitas pengguna historis tetap terbaca saat akun pengguna di-soft delete.

---

## 2. Matriks Cakupan Audit dari Source Aktual

| Jalur Mutasi Aktual | Mekanisme Audit | Batas Transaksi | Bukti Tes Terarah | Status Cakupan |
|---|---|---|---|---|
| `UserController::store` (Create Akun Baru) | Trait `Loggable` (`created`) | `DB::transaction(...)` (atomik) | `user creation audits allowlisted attributes and strips password, hashes, and tokens`<br>`audit insert failure rolls back user domain mutation and preserves database state` | **TERCAKUP & TERBUKTI** |
| `UserController::store` (Restore Akun Soft-Deleted) | Trait `Loggable` (`updated`) | `DB::transaction(...)` (atomik) | `UserValidationTest`, `ServerAuditTest` | **TERCAKUP & TERBUKTI** |
| `UserController::update` (Self / Admin Update Akun) | Trait `Loggable` (`updated`) | `DB::transaction(...)` (atomik) | `user update audits changed allowlisted fields and strips secrets` | **TERCAKUP & TERBUKTI** |
| `UserController::destroy` (Soft Delete Akun) | Trait `Loggable` (`deleted`) | `DB::transaction(...)` (atomik) | `activity user relation resolves soft-deleted user historically via withTrashed` | **TERCAKUP & TERBUKTI** |
| `AccessAssignmentController::assignRole` (Pivot `role_user`) | Eksplisit `Activity::create` (`role_user`, `Created`) | `DB::transaction(...)` (atomik) | `role assignment and revocation generate role_user pivot audit`<br>`audit insert failure rolls back role_user pivot mutation and preserves database state` | **TERCAKUP & TERBUKTI** |
| `AccessAssignmentController::revokeRole` (Pivot `role_user`) | Eksplisit `Activity::create` (`role_user`, `Deleted`) | `DB::transaction(...)` (atomik) | `role assignment and revocation generate role_user pivot audit`<br>`audit insert failure rolls back role_user pivot mutation and preserves database state` | **TERCAKUP & TERBUKTI** |
| `RoleController::store` (Create Peran + Ability Sync) | Trait `Loggable` pada Role + `Activity::create` pada `ability_role` | `DB::transaction(...)` (atomik) | `role ability sync generates ability_role pivot audit` | **TERCAKUP & TERBUKTI** |
| `RoleController::update` (Update Peran + Ability Sync) | Trait `Loggable` pada Role + `Activity::create` pada `ability_role` | `DB::transaction(...)` (atomik) | `role ability sync generates ability_role pivot audit`<br>`audit insert failure rolls back ability_role pivot mutation and preserves database state` | **TERCAKUP & TERBUKTI** |
| `RoleController::destroy` (Soft Delete Peran) | Trait `Loggable` (`deleted`) | `DB::transaction(...)` (atomik) | `AuthorizationTest`, `ServerAuditTest` | **TERCAKUP & TERBUKTI** |
| `FirstLoginController::store` (Ganti Password Pertama) | Trait `Loggable` (`updated` pada `User`) | Model `User::update` | `firstlogin can update own account only` | **TERCAKUP & TERBUKTI** (rahasia tersaring) |
| `NewPasswordController::store` (Reset Password Token) | Trait `Loggable` (`updated` pada `User`) | Broker Password Transaction | `password can be reset with valid token` | **TERCAKUP & TERBUKTI** (rahasia tersaring) |
| Master Data Controllers (`TypeUserController`, `StatusUserController`, `SchoolYearController`, `EducationSchoolController`, `EducationLevelController`, `ProfessionController`, `PhaseStatusController`, `SubjectController`, `EmploymentController`, `EmployeeTypeController`, `EmployeeStatusController`, `MaritalStatusController`, `SchoolProfileController`) | Trait `Loggable` pada model master masing-masing | Model-level Eloquent observer | `audit actor cannot be forged: server strictly derives actor from Auth::id()`<br>`TypeUserAccessTest` | **TERCAKUP & TERBUKTI** |
| Bulk Deletion di Frontend (`useResourceManager.js::deleteSelectedManyDatas`) | Loop individual request `DELETE /api/{endpoint}/{id}` ke backend | Per-record atomic transaction | `useResourceManager.js` build PASS | **TERCAKUP** |
| Query Langsung `DB::table(...)` | Tidak ada query mutasi langsung di controller | N/A | Source grep review (hanya ada di historical migration) | **TIDAK ADA CELAH** |
| Command `BootstrapAdminCommand` (`php artisan app:bootstrap-admin`) | CLI Non-authenticated (`Auth::check() = false`) | `DB::transaction(...)` (atomik) | `BootstrapAdminTest` (5 tests PASS) | **TERISOLASI DARI AUDIT WEB** |
| `ActivityController` (`store`, `update`, `destroy`) | Mutasi audit oleh klien | Dinonaktifkan (HTTP 405) + Model level guard | `client cannot create audit log via POST /api/activities (rejected 405)`<br>`client cannot modify audit log or trigger rollback via PUT/PATCH /api/activities/{id} (rejected 405)`<br>`client cannot delete audit log via DELETE /api/activities/{id} (rejected 405)`<br>`activity model throws RuntimeException when update or delete is attempted directly` | **TERKUNCI & IMMUTABLE** |
| **Jalur Scaffold / Belum Diimplementasikan (Di Luar Scope 02a)** | | | | |
| `StudentController` (`store`, `update`, `destroy`) | Model `Student` memiliki `Loggable` + allowlist | Scaffold kosong (Fase 03) | `student model audit captures operational attributes without dumping private data` | **BELUM DIIMPLEMENTASIKAN** (CRUD belum ada di controller) |
| `EmployeeController` (`store`, `update`, `destroy`) | Model `Employee` memiliki `Loggable` | Scaffold kosong (Fase 04) | Scaffold review | **BELUM DIIMPLEMENTASIKAN** |
| `SchoolRombelController` / `StudentRombelController` (Tulis) | Belum dipasangi `Loggable` | Scaffold kosong (Fase 03/06) | Scaffold review | **BELUM DIIMPLEMENTASIKAN** |
| Modul Surat, Izin, Pembelajaran | Belum ada controller/rute | Belum dibuat (Fase 07, 08, 10) | N/A | **BELUM DIIMPLEMENTASIKAN** |

---

## 3. Dokumentasi Sanitasi & Allowlist Atribut per Domain

Penyaringan data audit dilakukan dua lapis via `Loggable.php`:
1. **Allowlist per Model**: Setiap model mendefinisikan array `$auditableAttributes`. Atribut di luar daftar ini secara otomatis diabaikan.
2. **Denylist Rahasia Ketat (Deny Override)**: Atribut rahasia (`password`, `password_confirmation`, `password_new`, `remember_token`, `token`, `access_token`, `refresh_token`, `two_factor_secret`, `two_factor_recovery_codes`, `secret`, `cookie`, `session_id`, `authorization`) dibuang tanpa syarat.

### Daftar Allowlist per Domain:
- **Domain `User`**: `name`, `username`, `email`, `nik`, `type_user_id`, `status_user_id`, `first_login_at`, `status_active`, `version`.
- **Domain `Role`**: `name`, `slug`, `description`, `is_protected`, `version`.
- **Domain `Ability`**: `name`, `group`, `description`, `is_sensitive`.
- **Domain `Student`**: `name`, `nis`, `nisn`, `nik`, `gender_id`, `religion_id`, `education_level_id`, `version`. (Atribut pribadi sensitif seperti penghasilan orang tua, riwayat medis, dan detail wali tidak dimasukkan ke audit).
- **Domain `EducationSchool`**: `name`, `npsn`, `education_level_id`, `status_education`, `address`, `district_id`, `version`.
- **Master Referensi (`TypeUser`, `StatusUser`, `SchoolLevel`, `Semester`)**: `name` (dan `name_other`).

### Bukti Sanitasi Respons Pembacaan Audit:
Pengujian terarah `audit read response does not expose password or hash through user relation or serialization` membuktikan:
- Respons endpoint `GET /api/activities` dan `GET /api/activities/{id}` hanya memuat relasi `user` dengan atribut terpilih: `id`, `name`, `username`.
- Kata sandi teks mentah (`AdminTopSecret123!`, `TargetTopSecret456!`) dan nilai hash bcrypt (`$2y$`) tidak ditemukan dalam respons teks mentah maupun struktur JSON audit.

---

## 4. Hasil Pengujian Otomatis

### A. Pengujian Backend (`php artisan test`)
- Lingkungan: SQLite in-memory `:memory:` terisolasi (`DatabaseIsolationTest` & `StorageIsolationTest` PASS).
- Hasil: **103 passed (465 assertions)**, bertambah 21 tes pada `tests/Feature/Audit/ServerAuditTest.php`:
  1. `guest cannot access activities endpoint` (401) — PASS
  2. `user without audit.view is denied viewing activities` (403) — PASS
  3. `user with audit.view can list activities with user relation` (200, paginate, user relation) — PASS
  4. `user with audit.view can view single activity detail` (200) — PASS
  5. `audit read response does not expose password or hash through user relation or serialization` — PASS
  6. `client cannot create audit log via POST /api/activities (rejected 405)` — PASS
  7. `client cannot modify audit log or trigger rollback via PUT/PATCH /api/activities/{id} (rejected 405)` — PASS
  8. `client cannot delete audit log via DELETE /api/activities/{id} (rejected 405)` — PASS
  9. `audit actor cannot be forged: server strictly derives actor from Auth::id()` — PASS
  10. `user creation audits allowlisted attributes and strips password, hashes, and tokens` — PASS
  11. `user update audits changed allowlisted fields and strips secrets` — PASS
  12. `student model audit captures operational attributes without dumping private data` — PASS
  13. `role assignment and revocation generate role_user pivot audit` — PASS
  14. `role ability sync generates ability_role pivot audit` — PASS
  15. `transaction failure rolls back both domain mutation and audit record` (atomicity) — PASS
  16. `audit insert failure rolls back user domain mutation and preserves database state` — PASS
  17. `audit insert failure rolls back role_user pivot mutation and preserves database state` — PASS
  18. `audit insert failure rolls back ability_role pivot mutation and preserves database state` — PASS
  19. `reentrancy guard prevents recursive audit creation` — PASS
  20. `activity model throws RuntimeException when update or delete is attempted directly` — PASS
  21. `activity user relation resolves soft-deleted user historically via withTrashed` — PASS

### B. Pemeriksaan Style Pint Backend (`./vendor/bin/pint --test`)
- File diperiksa: 16 file PHP yang disentuh pada Fase 02a.
- Hasil: **16 files PASS** (100% PSR-12 / Laravel clean).

### C. Pengujian Frontend (`vue-app`)
- Node test suite: `node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs` → **15 passed, 0 failed**.
- Build: `npm run build` → **PASS** (`✓ built in 34s`, 0 errors).
- Lint: `npx eslint src/utils/useResourceManager.js` → **0 errors** (peringatan CRLF Prettier bawaan lingkungan).

---

## 5. Berkas yang Berubah pada Fase 02a

- Backend:
  - `laravel-api/app/Traits/Loggable.php`: reentrancy guard, allowlist atribut model, filter denylist rahasia ketat, sanitasi server-side.
  - `laravel-api/app/Models/Activity.php`: hook pengujian atomisitas transaksi `$testCreatingHook`, proteksi immutability model level (`updating` dan `deleting` throw RuntimeException), relasi `user()` dengan `withTrashed()`.
  - `laravel-api/app/Models/User.php`: definisi allowlist `$auditableAttributes` operasional.
  - `laravel-api/app/Models/Role.php`: integrasi trait `Loggable`, definisi `$auditableAttributes`.
  - `laravel-api/app/Models/Ability.php`: integrasi trait `Loggable`, definisi `$auditableAttributes`.
  - `laravel-api/app/Models/Student.php`: integrasi trait `Loggable`, definisi `$auditableAttributes` terbatas operasional.
  - `laravel-api/app/Models/EducationSchool.php`: integrasi trait `Loggable`, definisi `$auditableAttributes`.
  - `laravel-api/app/Models/StatusUser.php`: integrasi trait `Loggable`, definisi `$auditableAttributes`.
  - `laravel-api/app/Models/Semester.php`: integrasi trait `Loggable`, definisi `$auditableAttributes`.
  - `laravel-api/app/Models/SchoolLevel.php`: integrasi trait `Loggable`, definisi `$auditableAttributes`.
  - `laravel-api/app/Http/Controllers/ActivityController.php`: pembacaan terproteksi `audit.view`, penonaktifan store/update/destroy via HTTP 405.
  - `laravel-api/app/Http/Controllers/RoleController.php`: audit pivot `ability_role`, atomisitas transaksi delete.
  - `laravel-api/app/Http/Controllers/AccessAssignmentController.php`: perbaikan penyimpanan array cast pada audit pivot `role_user`.
  - `laravel-api/app/Http/Controllers/UserController.php`: pembungkusan `store` dan `destroy` dalam `DB::transaction` atomik.
  - `laravel-api/routes/api.php`: penataan rute eksplisit `/activities` (GET dengan `can:audit.view`; POST/PUT/PATCH/DELETE ditolak 405).
  - `laravel-api/tests/Feature/Audit/ServerAuditTest.php`: 21 skenario automated test pembuktian otorisasi, penolakan pemalsuan, sanitasi rahasia, audit pivot, konsistensi transaksi, kegagalan insert audit, dan immutability.
- Frontend:
  - `vue-app/src/utils/useResourceManager.js`: pembersihan dead code / komentar pemanggilan `POST /api/activities`.
- Dokumentasi & Kontrak:
  - `docs/API-CONTRACTS.md`: kontrak endpoint `/api/activities` (GET berizin, POST/PUT/PATCH/DELETE ditutup 405).
  - `docs/SECURITY-DATA.md`: mekanisme allowlist audit, penutupan mutasi klien, dan batasan klaim perlindungan API.
  - `docs/DATABASE.md`: catatan tabel `activities` (immutable append-only & soft-delete preservation).
  - `reports/feature-update/PHASE-02A-AUDIT.md`: laporan teknis Subfase 02a.
  - `reports/feature-update/PHASE-02A-MANUAL-REVIEW.md`: panduan manual UAT 02a (checklist #1–#9, status PENDING).
  - `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`, `CHANGELOG.md`: sinkronisasi checkpoint dan status.
