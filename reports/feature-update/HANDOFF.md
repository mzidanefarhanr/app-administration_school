# Handoff pengembangan aplikasi sekolah

Checkpoint terbaru: 2026-10-19 (WIB). Branch `main`.
Baseline terakhir yang dilaporkan: `cf94cae` ("fix(phase-02a): wrap active master and firstlogin in transactions and audit bootstrap admin").
Kebijakan commit/push: commit dan push per fase/koreksi ke branch kerja aktif telah **diotorisasi**; merge, force-push, rewrite history, dan deploy manual **tetap tidak diotorisasi**.

Catatan perubahan pemilik: berkas log bukti `console.txt` milik pemilik tetap ada dan **tidak** di-commit.

---

## 1. Status Fase
- **Fase 00 — Audit baseline**: PARTIAL (source & environment audit selesai; browser NOT RUN).
- **Fase 01 — Hak akses, autentikasi dan lingkungan aman**: **DITERIMA LENGKAP & DITUTUP** (Fase 01a, 01b, dan 01c seluruhnya DITERIMA PEMILIK).
- **BLK-03**: **CLOSED pada source aktif** (seluruh akun pengguna riil dan data siswa riil telah dibersihkan dari seeder aktif; data pada riwayat commit Git historis belum dihapus).
- **Fase 02 — Integritas Operasional**: **IN PROGRESS / PARTIAL (02a DITERIMA, 02b–02c NOT STARTED)**:
  - **Subfase 02a — Audit Server dan Rollback Terbatas**: **DITERIMA PEMILIK** (seluruh checklist manual #1–#9 APPROVED 2026-10-19 WIB mengacu baseline `cf94cae`).
  - **Subfase 02b — Backup Manual dan Restore Drill**: **NOT STARTED (PERENCANAAN)** (menunggu otorisasi implementasi dari pemilik).
  - **Subfase 02c — Jadwal dan Monitoring Backup**: **NOT STARTED**.
- **Keputusan Terbuka yang Masih OPEN (belum menghambat)**:
  1. Makna `status_user_id` (jangan disamakan dengan `status_active`).
  2. Deployment CORS/Sanctum produksi.
  3. Strategi pencabutan token dibanding sesi.
- **Batasan**: Penerimaan manual Fase 01, penerimaan manual 02a, dan persetujuan kebijakan DEC-17 **bukan izin deployment ke produksi** dan bukan penerimaan modul yang belum tersedia.
  - Perlindungan audit & immutability terbatas pada lapisan API aplikasi dan ORM Eloquent, bukan kekebalan terhadap root DB/OS admin.
  - Bulk deletion di frontend berjalan per-record atomic, bukan satu transaksi batch lintas item.
  - Modul scaffold (Student CRUD, Employee CRUD, Rombel write, Surat, Izin, Pembelajaran) tetap N/A.

---

## 2. Pemeriksaan yang Dijalankan & Hasil (Subfase 02a)
1. **Automated Backend**:
   - Perintah: `php artisan test` pada database SQLite in-memory `:memory:` terisolasi.
   - Hasil: **110 passed (506 assertions)**, mencakup 25 tes pada `tests/Feature/Audit/ServerAuditTest.php` dan 8 tes pada `tests/Feature/Access/BootstrapAdminTest.php` (seluruh tes audit PASS: otorisasi, sanitasi serialisasi respons pembacaan, penolakan pemalsuan klien, penolakan rollback, sanitasi rahasia, audit pivot, konsistensi atomik transaksi termasuk kegagalan insert audit pada endpoint aktual `POST /api/type-users`, `PUT /api/type-users/{id}`, `DELETE /api/type-users/{id}`, dan `PUT /api/firstlogin/{id}`, audit bootstrap admin ber-actor `system:cli` tanpa rahasia, reentrancy guard, immutability model level, dan preservasi soft-delete).
   - Style check: `./vendor/bin/pint --test` (18 files) → **PASS** (100% clean).
2. **Automated Frontend**:
   - Perintah: `node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`.
   - Hasil: **15 passed (0 failed)**.
   - Build: `npm run build` → **PASS** (`✓ built in 34s`, 0 errors).
   - Lint: `npx eslint src/utils/useResourceManager.js` → **0 errors** (peringatan CRLF Prettier dilaporkan terpisah tanpa `--fix` massal).
3. **Verifikasi Browser & Runtime (UAT Live 127.0.0.1:4001 & 127.0.0.1:8001)**:
   - Akses guest ke `http://127.0.0.1:8001/api/activities` menghasilkan 401 Unauthorized (`curl.exe -s http://127.0.0.1:8001/api/check-auth` mengembalikan `{authenticated:false,user:null}`).
   - Navigasi browser tak terautentikasi ke route terproteksi langsung dialihkan ke `/auth/login` oleh route guard SPA.
   - Pemeriksaan staging bersih; tidak ada pemanggilan klien ke endpoint aktivitas pada kode frontend aktif.
4. **Lingkungan & Keamanan**:
   - Seluruh tes otomatis hanya menggunakan SQLite in-memory (`:memory:`) dan disk storage `testing`. Database operasional `backdata_administrations` sama sekali tidak disentuh.
   - Database UAT `school_access_uat` tetap utuh.

---

## 3. Berkas yang Berubah pada Fase 02a
- Backend:
  - `laravel-api/app/Traits/Loggable.php`: reentrancy guard, allowlist atribut model, filter denylist rahasia ketat, sanitasi server-side.
  - `laravel-api/app/Models/Activity.php`: proteksi immutability model level (`updating` dan `deleting` throw RuntimeException), relasi `user()` dengan `withTrashed()`.
  - `laravel-api/app/Models/User.php`: definisi allowlist `$auditableAttributes` operasional (tanpa password/token).
  - `laravel-api/app/Models/Role.php`: integrasi trait `Loggable`, definisi `$auditableAttributes`.
  - `laravel-api/app/Models/Ability.php`: integrasi trait `Loggable`, definisi `$auditableAttributes`.
  - `laravel-api/app/Models/Student.php`: integrasi trait `Loggable`, definisi `$auditableAttributes` terbatas operasional.
  - `laravel-api/app/Http/Controllers/ActivityController.php`: pembacaan terproteksi `audit.view`, penonaktifan store/update/destroy via HTTP 405.
  - `laravel-api/app/Http/Controllers/RoleController.php`: pencatatan audit pivot `ability_role` pada sync kemampuan peran.
  - `laravel-api/app/Http/Controllers/AccessAssignmentController.php`: perbaikan penyimpanan array cast pada audit pivot `role_user`.
  - `laravel-api/routes/api.php`: penataan rute eksplisit `/activities` (GET dengan `can:audit.view`; POST/PUT/PATCH/DELETE ditolak 405).
  - `laravel-api/tests/Feature/Audit/ServerAuditTest.php` (baru): 17 skenario automated test pembuktian otorisasi, penolakan pemalsuan, sanitasi rahasia, audit pivot, konsistensi transaksi, dan immutability.
- Frontend:
  - `vue-app/src/utils/useResourceManager.js`: pembersihan dead code / komentar pemanggilan `POST /api/activities`.
- Dokumentasi & Kontrak:
  - `docs/API-CONTRACTS.md`: pembaruan kontrak endpoint `/api/activities` (GET berizin, POST/PUT/PATCH/DELETE ditutup 405).
  - `docs/SECURITY-DATA.md`: pencatatan mekanisme allowlist audit, penutupan mutasi klien, dan batasan klaim perlindungan API.
  - `docs/DATABASE.md`: pembaruan catatan tabel `activities` (immutable append-only & soft-delete preservation).
  - `reports/feature-update/PHASE-02A-AUDIT.md` (baru): laporan teknis Subfase 02a.
  - `reports/feature-update/PHASE-02A-MANUAL-REVIEW.md` (baru): panduan manual UAT 02a (checklist #1–#9, status PENDING).
  - `reports/feature-update/INDEX.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`, `CHANGELOG.md`: sinkronisasi checkpoint dan status.

---

## 4. Status Tugas Subfase 02a
1. **Penutupan Mutasi & Rollback Klien**: **SELESAI & DITERIMA**. `POST /api/activities` dan `PUT/PATCH/DELETE /api/activities/{id}` dinonaktifkan (405); model Eloquent melempar RuntimeException bila diubah/dihapus langsung.
2. **Perlindungan Akses Baca**: **SELESAI & DITERIMA**. `GET /api/activities` dan `GET /api/activities/{id}` dilindungi kemampuan bernama `audit.view`.
3. **Pencatatan Otomatis & Penentuan Server**: **SELESAI & DITERIMA**. Actor ditentukan dari `Auth::id()`; target, waktu, dan aksi ditentukan dari server; allowlist atribut diterapkan pada User, Role, Ability, Student, dan Master; rahasia autentikasi tersaring.
4. **Audit Pivot Hak Akses**: **SELESAI & DITERIMA**. Perubahan `role_user` (assign/revoke) dan `ability_role` (sync) diaudit secara transaksional di server.
5. **Konsistensi Transaksi & Immutability**: **SELESAI & DITERIMA**. Mutasi `UserController`, `RoleController`, `AccessAssignmentController`, `FirstLoginController`, `TypeUserController`, dan `SchoolYearController` terbukti atomik; kegagalan audit membatalkan mutasi domain/pivot.
6. **Pemeriksaan Manual Pemilik**: **DITERIMA LENGKAP**. Seluruh checklist #1–#9 di `PHASE-02A-MANUAL-REVIEW.md` dinyatakan **APPROVED** oleh pemilik pada 19 Oktober 2026 WIB mengacu baseline `cf94cae`.

---

## 5. Server & Lingkungan Aktif
Server UAT lokal yang diketahui masih berjalan (jangan dihentikan sembarangan):
- **API UAT**: port `8001` (PID 12720, `php artisan serve --host=127.0.0.1 --port=8001 --env=uat`)
- **SPA UAT**: port `4001` (PID 16624, `vite --mode uat --host 127.0.0.1 --port 4001`)
- **Database UAT**: MySQL `school_access_uat` (disposable, terpisah dari `backdata_administrations`)
- Server operasional pada port `8000` dibiarkan utuh.
- **PENTING**: Dilarang mengakses data operasional atau membersihkan database UAT `school_access_uat`.

---

## 6. Pekerjaan Pertama Sesi Baru (Hanya Perencanaan Subfase 02b)
1. Menyusun rencana kerja teknis Subfase 02b (Backup Manual dan Restore Drill) tanpa implementasi kode atau eksekusi backup/restore.
2. Menunggu review dan otorisasi pemilik atas rencana teknis 02b sebelum memulai implementasi. Jangan mendahului implementasi 02b tanpa instruksi eksplisit pemilik.
