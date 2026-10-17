# Laporan Subfase 02a — Audit Server dan Penonaktifan Rollback Klien

- Tanggal WIB: 2026-10-17
- Branch kerja: `main`
- Status teknis: **IMPLEMENTED & FULLY VERIFIED**
- Status review pemilik: **PENDING** (menunggu pemeriksaan manual checklist 02a)
- Prasyarat: Fase 01 telah DITERIMA PENUH dan DITUTUP; kebijakan DEC-17 APPROVED
- Database & storage: **SQLite in-memory `:memory:` terisolasi**, disk storage `testing` terisolasi, API UAT port 8001 / SPA UAT port 4001
- Batasan klaim: **Perlindungan integritas audit dan penonaktifan modifikasi/rollback ditegakkan pada lapisan API aplikasi dan ORM Eloquent, bukan merupakan klaim kekebalan terhadap administrator sistem operasi atau administrator database langsung (root DB/OS admin).**

---

## 1. Ringkasan Eksekutif

Subfase 02a mengimplementasikan audit trail terpercaya berbasis server (*immutable append-only audit trail*) dan menutup seluruh celah manipulasi log dari sisi klien sesuai keputusan pemilik **DEC-17**:

1. **Penutupan Mutasi & Rollback Klien**:
   - Endpoint `POST /api/activities` (pembuatan log oleh klien), `PUT/PATCH /api/activities/{id}` (modifikasi & rollback), dan `DELETE /api/activities/{id}` (penghapusan log) dinonaktifkan secara tegas dengan respons standar **HTTP 405 Method Not Allowed**.
   - Model `Activity` mengunci catatan audit pada level Eloquent via callback `booted()`: percobaan `update()` atau `delete()` langsung melempar `\RuntimeException`.
2. **Perlindungan Akses Baca Audit**:
   - Pembacaan log audit (`GET /api/activities` dan `GET /api/activities/{id}`) dilindungi oleh kemampuan bernama terpusat **`audit.view`** (dapat dikonfigurasi admin sistem via `/master/roles`).
   - Guest ditolak `401 Unauthorized`; pengguna tanpa kemampuan (mis. Operator Sekolah default) ditolak `403 Forbidden`.
   - Log yang dikembalikan memuat relasi pengguna (`id`, `name`, `username`) dan berpaginasi.
3. **Pencatatan Otomatis & Penegakan Server-Side**:
   - Actor (`user_id`), target (`table_name`, `record_id`), waktu (`created_at`), dan aksi (`information`) ditentukan 100% oleh server berdasarkan sesi aktif (`Auth::id()`). Klien tidak dapat memalsukan actor.
   - Trait `Loggable.php` dilengkapi **allowlist atribut** per model (`$auditableAttributes` pada `User`, `Role`, `Ability`, `Student`, dan master data).
   - Seluruh rahasia autentikasi (`password`, `password_new`, `remember_token`, hash bcrypt, access token, cookies) tersaring 100% dan **tidak pernah masuk** ke kolom JSON `before`, `after`, `new`, atau `delete`.
   - Data pribadi non-operasional pada siswa (riwayat medis, data orang tua/gaji) tidak terekam dalam log audit (hanya atribut operasional: name, nis, nisn, nik, gender, religion, education level, version).
4. **Audit Mutasi Pivot**:
   - Mutasi penetapan peran (`role_user`) pada `AccessAssignmentController` mencatat audit `Created` (assign) dan `Deleted` (revoke).
   - Mutasi pemetaan kemampuan peran (`ability_role`) pada `RoleController` mencatat audit `Created` dan `Updated` memuat array kemampuan sebelum dan sesudah.
5. **Konsistensi Transaksi & Proteksi Rekursi**:
   - Reentrancy guard (`$isLoggingAudit`) mencegah loop logging rekursif atau pencatatan ganda.
   - Mutasi domain dan pencatatan audit berjalan dalam transaksi atomik: jika transaksi database gagal atau dibatalkan, rekaman audit terkait ikut dibatalkan (rollback).
   - Riwayat audit historis tetap terjaga saat akun pengguna di-soft delete via relasi `Activity::user()->withTrashed()`.
6. **Pembersihan Frontend**:
   - Kode sisa/komentar pemanggilan `POST /api/activities` pada `vue-app/src/utils/useResourceManager.js` dibersihkan. Antarmuka tidak memuat tombol manipulasi atau rollback audit.

---

## 2. Inventarisasi Jalur Mutasi Aplikasi

| Jalur Mutasi | Tipe Operasi | Mekanisme Audit | Status 02a |
|---|---|---|---|
| `UserController` (`store`, `update`, `destroy`) | Model `User` CRUD | Trait `Loggable` (allowlist: name, username, email, nik, status, version) | **TERLINDUNGI & TERSARING** (password & hash absen) |
| `RoleController` (`store`, `update`, `destroy`) | Model `Role` CRUD | Trait `Loggable` (allowlist: name, slug, description, is_protected, version) | **TERLINDUNGI & TERBUKTI** |
| `RoleController` (`sync` abilities) | Pivot `ability_role` | Pencatatan server eksplisit dalam transaksi atomik | **TERLINDUNGI & TERBUKTI** |
| `AccessAssignmentController` (`assignRole`, `revokeRole`) | Pivot `role_user` | Pencatatan server eksplisit dalam transaksi atomik | **TERLINDUNGI & TERBUKTI** |
| `FirstLoginController` (`store`) | Model `User` update password/status | Trait `Loggable` | **TERLINDUNGI** (rahasia tersaring) |
| `NewPasswordController` (`store`) | Model `User` reset password | Trait `Loggable` | **TERLINDUNGI** (rahasia tersaring) |
| Master Data Controllers (TypeUser, SchoolYear, Subject, dll.) | Model Master CRUD | Trait `Loggable` | **TERLINDUNGI** |
| `StudentController` (scaffold/factory) | Model `Student` | Trait `Loggable` (allowlist operasional saja) | **TERLINDUNGI** (privasi terjaga) |
| `BootstrapAdminCommand` | CLI Console | Non-authenticated / system context (`Auth::check() = false`) | **AMAN** (tidak crash) |
| `ActivityController` (`store`, `update`, `destroy`) | Mutasi Audit oleh Klien | Dinonaktifkan (HTTP 405) + Model level guard | **DITUTUP & IMMUTABLE** |

---

## 3. Hasil Pengujian Otomatis

### A. Pengujian Backend (`php artisan test`)
- Lingkungan: SQLite in-memory `:memory:` terisolasi (`DatabaseIsolationTest` & `StorageIsolationTest` PASS).
- Hasil: **99 passed (433 assertions)**, bertambah 17 tes baru khusus integritas audit pada `tests/Feature/Audit/ServerAuditTest.php`:
  1. `guest cannot access activities endpoint` (401) — PASS
  2. `user without audit.view is denied viewing activities` (403) — PASS
  3. `user with audit.view can list activities with user relation` (200, paginate, user relation) — PASS
  4. `user with audit.view can view single activity detail` (200) — PASS
  5. `client cannot create audit log via POST /api/activities (rejected 405)` — PASS
  6. `client cannot modify audit log or trigger rollback via PUT/PATCH /api/activities/{id} (rejected 405)` — PASS
  7. `client cannot delete audit log via DELETE /api/activities/{id} (rejected 405)` — PASS
  8. `audit actor cannot be forged: server strictly derives actor from Auth::id()` — PASS
  9. `user creation audits allowlisted attributes and strips password, hashes, and tokens` — PASS
  10. `user update audits changed allowlisted fields and strips secrets` — PASS
  11. `student model audit captures operational attributes without dumping private data` — PASS
  12. `role assignment and revocation generate role_user pivot audit` — PASS
  13. `role ability sync generates ability_role pivot audit` — PASS
  14. `transaction failure rolls back both domain mutation and audit record` (atomicity) — PASS
  15. `reentrancy guard prevents recursive audit creation` — PASS
  16. `activity model throws RuntimeException when update or delete is attempted directly` — PASS
  17. `activity user relation resolves soft-deleted user historically via withTrashed` — PASS

### B. Pemeriksaan Style Pint Backend (`./vendor/bin/pint --test`)
- File diperiksa: 11 file PHP yang disentuh pada Fase 02a.
- Hasil: **11 files PASS** (100% PSR-12 / Laravel clean).

### C. Pengujian Frontend (`vue-app`)
- Node test suite: `node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs` → **15 passed, 0 failed**.
- Build: `npm run build` → **PASS** (`✓ built in 34s`, 0 errors).
- Lint: `npx eslint src/utils/useResourceManager.js` → **0 errors** (peringatan CRLF Prettier bawaan lingkungan).

---

## 4. Berkas yang Berubah pada Fase 02a

- Backend:
  - `laravel-api/app/Traits/Loggable.php`: implementasi reentrancy guard, allowlist atribut model, filter denylist rahasia ketat, sanitasi server-side.
  - `laravel-api/app/Models/Activity.php`: proteksi immutability model level (`updating` dan `deleting` throw RuntimeException), relasi `user()` dengan `withTrashed()`.
  - `laravel-api/app/Models/User.php`: definisi `$auditableAttributes` allowlist operasional (tanpa password/token).
  - `laravel-api/app/Models/Role.php`: integrasi trait `Loggable`, definisi `$auditableAttributes`.
  - `laravel-api/app/Models/Ability.php`: integrasi trait `Loggable`, definisi `$auditableAttributes`.
  - `laravel-api/app/Models/Student.php`: integrasi trait `Loggable`, definisi `$auditableAttributes` terbatas operasional.
  - `laravel-api/app/Http/Controllers/ActivityController.php`: pembacaan terproteksi `audit.view`, penonaktifan store/update/destroy via HTTP 405.
  - `laravel-api/app/Http/Controllers/RoleController.php`: pencatatan audit pivot `ability_role` pada sync kemampuan peran.
  - `laravel-api/app/Http/Controllers/AccessAssignmentController.php`: perbaikan penyimpanan array cast pada audit pivot `role_user`.
  - `laravel-api/routes/api.php`: penataan rute eksplisit `/activities` (GET dengan `can:audit.view`; POST/PUT/PATCH/DELETE ditolak 405).
  - `laravel-api/tests/Feature/Audit/ServerAuditTest.php` (baru): 17 skenario automated test pembuktian otorisasi, penolakan pemalsuan, sanitasi rahasia, audit pivot, konsistensi transaksi, dan immutability.
- Frontend:
  - `vue-app/src/utils/useResourceManager.js`: penghapusan dead code / commented-out call ke `POST /api/activities`.
- Dokumentasi & Kontrak:
  - `docs/API-CONTRACTS.md`: pembaruan kontrak endpoint `/api/activities` (GET berizin, POST/PUT/PATCH/DELETE ditutup 405).
  - `docs/SECURITY-DATA.md`: pencatatan mekanisme allowlist audit, penutupan mutasi klien, dan batasan klaim perlindungan API.
  - `docs/DATABASE.md`: pembaruan catatan tabel `activities` (immutable append-only & soft-delete preservation).
  - `reports/feature-update/PHASE-02A-AUDIT.md` (baru): laporan teknis Subfase 02a.
  - `reports/feature-update/PHASE-02A-MANUAL-REVIEW.md` (baru): checklist manual UAT 02a berstatus PENDING.
  - `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`, `CHANGELOG.md`: sinkronisasi checkpoint dan status.
