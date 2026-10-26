# Laporan Subfase 04a — Fondasi Schema Profil Pegawai dan Hubungan Akun

Tanggal WIB: 2026-10-26. Branch: `main`.
Baseline rencana: `41f6e68`.
Keputusan pemilik: **DEC-04** (APPROVED pada 2026-10-26 WIB, commit `7cd6dc8`).
Status implementasi: **IMPLEMENTED & FULLY VERIFIED (AGEN)**.
Status penerimaan manual pemilik: **PENDING (Panduan review fondasi `PHASE-04A-MANUAL-REVIEW.md`)**.
Status fase: Fase 01, 02, dan 03 (03a–03d) tetap **DITERIMA LENGKAP & DITUTUP**; Subfase 04a adalah fondasi schema dan pemodelan data pegawai; Subfase 04b (CRUD/UI) **NOT STARTED**.

> **Pernyataan Batas Scope:** Laporan ini membuktikan implementasi teknis dan verifikasi otomatis Subfase 04a pada lapisan schema database, model relasi, privasi audit, dan layanan backfill. Bukti agen terpisah dari penerimaan manual pemilik. Subfase 04a tidak mencakup CRUD lengkap, form input, atau antarmuka pengguna (UI) yang berada pada Subfase 04b. Penerimaan bukan izin deployment ke produksi dan bukan penyelesaian seluruh risiko keamanan terbuka.

---

## 1. Ringkasan Implementasi

Sesuai Keputusan Pemilik DEC-04, Subfase 04a meletakkan fondasi teknis modul Guru dan Pegawai:
1. **Profil Pegawai Independen & Akun Opsional (`user_id` Nullable)**:
   - Menambahkan kolom `user_id` bertipe `unsignedBigInteger` nullable pada tabel `employees` dengan foreign key ke `users(id)` beraturan `nullOnDelete()`.
   - Penegakan integritas satu akun maksimal terhubung ke satu profil pegawai via unique constraint (`unique('user_id')`), dengan multiple `NULL` tetap sah secara standar SQL.
   - Profil pegawai dapat dibuat dan disimpan tanpa akun login pengguna (`user_id = null`).
2. **Kompatibilitas Relasi Legacy (`user_nik` Nullable)**:
   - Kolom legacy `user_nik` dipertahankan dan dilonggarkan nullability-nya menjadi `NULL` secara additive tanpa merusak data lama atau foreign key ke `users(nik)`.
   - Relasi model `user()` dialihkan ke `user_id` sebagai relasi primer. Relasi legacy `legacyUser()` disediakan untuk kompatibilitas.
   - Method resolusi aman `resolveUser()` memprioritaskan `user_id`, menolak fallback bila akun sudah dilepas/diresolusi, dan hanya mengizinkan fallback NIK pada baris legacy yang memang belum pernah dimigrasikan.
3. **Pelonggaran Mata Pelajaran (`subject_id` Nullable)**:
   - Kolom `subject_id` diubah menjadi `NULL` pada schema database.
   - Pegawai non-guru bertipe `Tenaga Kependidikan` (TU, satpam, pustakawan, laboran) sah disimpan dengan `subject_id = null` tanpa dipaksa memilih mata pelajaran dummy.
4. **Fondasi Kontrol Konkurensi Atomik (`version`)**:
   - Menambahkan kolom `version` bertipe `unsignedInteger` dengan default `1` pada tabel `employees`. Model menginisialisasi atribut default version = 1.
5. **Privasi Audit Trail Personalia**:
   - Menetapkan allowlist eksplisit `$auditableAttributes` pada model `Employee`.
   - Atribut pribadi sensitif (nama ibu kandung, pasangan, nomor KK, NPWP, dan nilai/IPK ijazah) secara ketat dikecualikan dari payload audit `activities`.
6. **Koreksi Typo Relasi & Otorisasi Terpusat**:
   - Memperbaiki method relasi `religion()` dan mempertahankan alias kompatibilitas `relogion()`.
   - Mengimplementasikan `EmployeePolicy` dengan penegakan kemampuan bernama `employees.view` dan `employees.manage`, serta melarang permanen `forceDelete`.
   - Menyelaraskan middleware rute `/employee` ke `can:employees.view` dan `can:employees.manage`.
7. **Layanan Backfill Idempotent (`EmployeeBackfillService`)**:
   - Menyelesaikan pemadanan akun legacy dari `user_nik` ke `user_id` secara satu-ke-satu, idempotent, dan transaksional tanpa mencetak data pribadi (PII) ke log/laporan.

---

## 2. Berkas yang Berubah / Dibuat

| Berkas | Status | Perubahan |
|---|---|---|
| `docs/DECISIONS.md` | Modifikasi | Mencatat Keputusan Pemilik **DEC-04** (profil independen, akun opsional, user_id relasi utama, subject_id nullable, versioning, audit privacy allowlist). |
| `database/migrations/2029_07_24_000001_update_employees_schema_for_phase_04a.php` | Baru | Migration additive: `user_id` nullable unique nullOnDelete, `user_id_resolved_at`, `account_unlinked_at`, `version` default 1, `user_nik` nullable, `subject_id` nullable. |
| `app/Models/Employee.php` | Modifikasi | Menambahkan `user_id`, `version`, allowlist `$auditableAttributes`, relasi utama `user()`, relasi `legacyUser()`, method `resolveUser()`, koreksi `religion()` dan alias `relogion()`. |
| `app/Policies/EmployeePolicy.php` | Modifikasi | Mengimplementasikan method policy menegakkan `employees.view` dan `employees.manage`. |
| `app/Services/EmployeeBackfillService.php` | Baru | Layanan backfill pemadanan akun legacy berbasis NIK unik 16-digit tanpa kebocoran PII. |
| `database/factories/EmployeeFactory.php` | Modifikasi | Menambahkan default `user_id = null` dan `version = 1`. |
| `routes/api.php` | Modifikasi | Menyelaraskan middleware rute `/employee` ke `can:employees.view` dan `can:employees.manage`. |
| `tests/Feature/Employee/EmployeeSchemaAndAccountRelationTest.php` | Baru | Suite pengujian otomatis SQLite in-memory: 15 skenario (schema, FK, multiple NULL, unique, version default, resolveUser, audit privacy, backfill, policy). |
| `tests/Feature/Employee/MariaDbEmployeeIntegrationTest.php` | Baru | Pengujian integrasi MariaDB pada database disposable `school_04a_integration`: 5 skenario (DDL DML aktual, unique constraint, soft delete reuse rejection, hard delete nullOnDelete, backfill). |
| `docs/DATABASE.md` | Modifikasi | Dokumentasi pembaruan schema dan migration Subfase 04a. |
| `docs/API-CONTRACTS.md` | Modifikasi | Dokumentasi kontrak model dan izin route Subfase 04a. |
| `reports/feature-update/PHASE-04A-PLAN.md` | Modifikasi | Penegasan status approved DEC-04 dan koreksi batas scope fondasi 04a vs CRUD 04b. |
| `reports/feature-update/PHASE-04A-EMPLOYEE-SCHEMA.md` | Baru | Laporan teknis implementasi dan verifikasi Subfase 04a. |
| `reports/feature-update/PHASE-04A-MANUAL-REVIEW.md` | Baru | Panduan checklist review fondasi 04a untuk pemilik. |

---

## 3. Hasil Pengujian Otomatis (Hasil Aktual)

### 3.1 Backend SQLite Automated Tests
- Eksekusi: `C:\xampp\php82-operational\php.exe artisan test tests/Feature/Employee/EmployeeSchemaAndAccountRelationTest.php`
- Hasil Aktual: **15 passed (0 failed)** dengan total **89 assertions** di atas PHP 8.2.35 ZTS x64:
  1. `can create employee profile without user_id and without user_nik (profile independent)` — PASS
  2. `non-teaching employee (Tenaga Kependidikan) can be created with null subject_id` — PASS
  3. `version column defaults to 1 on creation` — PASS
  4. `multiple employees can have null user_id simultaneously without violating unique constraint` — PASS
  5. `one user account cannot be linked to more than one active employee` — PASS
  6. `unconditional unique user_id constraint also blocks linking same user to another employee even if first is soft-deleted` — PASS
  7. `primary user_id relationship is never overridden by different legacy user_nik string` — PASS
  8. `resolveUser falls back to unique user_nik only for unmigrated legacy rows` — PASS
  9. `deliberate account detachment or hard delete does not trigger legacy NIK fallback` — PASS
  10. `soft delete of user account preserves historical relationship on employee without unlinking` — PASS
  11. `model religion method works and legacy relogion alias remains compatible` — PASS
  12. `employee backfill resolves matching 16-digit NIK uniquely and is idempotent` — PASS
  13. `employee backfill preserves existing user_id and leaves invalid/unmatched/ambiguous NIK unresolved` — PASS
  14. `employee audit log strictly enforces allowlist and excludes sensitive PII` — PASS
  15. `employee policy authorizes employees.view for viewing and employees.manage for mutations` — PASS

### 3.2 MariaDB Integration Tests
- Target database: `school_04a_integration` (database disposable khusus pada MariaDB port 3306; user `school_compat_app` dengan guard koneksi eksak).
- Eksekusi: `C:\xampp\php82-operational\php.exe artisan test tests/Feature/Employee/MariaDbEmployeeIntegrationTest.php`
- Hasil Aktual: **5 passed (0 failed)** dengan total **25 assertions**:
  1. DDL MariaDB aktual: kolom `user_id` (NULL: YES), `version` (Default: 1), `user_nik` (NULL: YES), `subject_id` (NULL: YES), index unique `user_id` terverifikasi.
  2. Multiple NULLs pada kolom `user_id` diizinkan tanpa error duplikasi.
  3. Unique constraint MariaDB memblokir duplikasi `user_id` pada dua employee aktif (SQLSTATE 23000).
  4. Unique constraint MariaDB memblokir penggunaan ulang `user_id` meskipun employee pertama berstatus soft-deleted (`deleted_at IS NOT NULL`).
  5. Hard delete pengguna di tabel `users` memicu `ON DELETE SET NULL` pada MariaDB, mengosongkan `user_id` employee secara otomatis.

### 3.3 Regresi Test Relevan
- `tests/Feature/Access/FactoryIntegrityTest.php` -> **3 passed (26 assertions)** — PASS.
- `tests/Feature/Student/StudentRombelAssignmentTest.php` -> **16 passed (78 assertions)** — PASS.
- `tests/Feature/Student/MariaDbRombelConcurrencyTest.php` -> **7 passed (43 assertions)** — PASS.
- Pint: `./vendor/bin/pint --test <7-php-files>` -> **PASS** (100% clean).

---

## 4. Batas Keamanan & Lingkungan yang Dipertahankan

1. **Isolasi Database & Host**:
   - Pengujian MariaDB dijalankan secara terisolasi pada database disposable baru `school_04a_integration`.
   - Database operasional `backdata_administrations`, database UAT `school_compat_uat`, dan seluruh resource App Gudang tidak disentuh.
   - Hak akses user `school_compat_app` dibatasi strictly pada database integrasi tanpa grant global.
2. **Server UAT Aktif**:
   - Listener UAT backend `18021` (PID `15960`) dan frontend `14021` (PID `17964`) tetap berjalan aktif di loopback tanpa restart.
3. **Risiko Keamanan Terbuka (Tetap OPEN)**:
   - Dependency advisories: 48 Composer advisories & 20 npm vulnerabilities tersisa.
   - Lifecycle runtime/framework: Laravel 10 berstatus EOL sejak Feb 2025; PHP 8.2 mendekati akhir masa dukungan keamanan (31 Desember 2026).
   - Paparan historis host dan tindak lanjut rotasi rahasia produksi tetap merupakan pekerjaan terpisah.

---

## 5. Langkah Selanjutnya

1. Pemilik meninjau checklist panduan fondasi di `reports/feature-update/PHASE-04A-MANUAL-REVIEW.md`.
2. Setelah peninjauan fondasi disetujui, pemilik dapat memberikan otorisasi untuk pelaksanaan **Subfase 04b (CRUD Lengkap, Form Input, dan Antarmuka Pengguna Pegawai & Guru)**.
