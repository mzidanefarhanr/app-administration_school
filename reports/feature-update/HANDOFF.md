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
- **Fase 02 — Integritas Operasional**: **IN PROGRESS / PARTIAL (02a DITERIMA, 02b IMPLEMENTED & PENDING REVIEW, 02c NOT STARTED)**:
  - **Subfase 02a — Audit Server dan Rollback Terbatas**: **DITERIMA PEMILIK** (seluruh checklist manual #1–#9 APPROVED 2026-10-19 WIB mengacu baseline `cf94cae`).
  - **Subfase 02b — Backup Manual dan Restore Drill**: **IMPLEMENTED & FULLY VERIFIED** (Pemeriksaan manual pemilik: **PENDING** mengacu `reports/feature-update/PHASE-02B-MANUAL-REVIEW.md`).
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

## 2. Pemeriksaan yang Dijalankan & Hasil (Subfase 02b Terakhir)
1. **Automated Backend**:
   - Perintah: `php artisan test` pada database SQLite in-memory `:memory:` terisolasi dan database integrasi MariaDB lokal.
   - Hasil: **125 passed (573 assertions)**, bertambah 15 tes baru khusus backup dan restore drill pada `tests/Feature/Backup/{BackupRunTest.php, RestoreDrillTest.php, MariaDbIntegrationTest.php}`:
     - Otorisasi endpoint `GET /api/backups` dan download `GET /api/backups/{filename}/download` (401 guest, 403 operator, 200 admin-sistem).
     - Validasi anti-path-traversal pada nama file download.
     - Perintah `app:backup-run` membuat arsip ZIP AES-256, manifest, companion sha256, dan membersihkan folder temporer.
     - Penolakan passphrase pendek (< 12 karakter).
     - Perintah `app:restore-drill` memulihkan dan memvalidasi multi-faktor ke target disposable (tabel, row count, foreign key, akun admin, dan lampiran).
     - Proteksi fail-safe keras: menolak `backdata_administrations`, menolak target yang sama dengan sumber aktif, menolak database di luar allowlist disposable.
     - Penolakan arsip rusak, passphrase salah, Zip Slip, dan SQL dump berbahaya (`USE`, `CREATE DATABASE`).
     - Uji integrasi nyata MariaDB lokal: round-trip backup dari `school_access_uat` dengan `mysqldump` dan restore drill ke `school_restore_drill_uat` dengan `mysql.exe` terbukti **PASS** (12 assertions).
   - Style check: `./vendor/bin/pint --test` (13 files) → **PASS** (100% clean).
2. **Automated Frontend**:
   - Perintah: `node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`.
   - Hasil: **15 passed (0 failed)**.
   - Build: `npm run build` → **PASS** (0 errors).
3. **Lingkungan & Keamanan**:
   - Database operasional `backdata_administrations` sama sekali tidak disentuh.
   - Database disposable drill `school_restore_drill_uat` terverifikasi bersih dan terpisah dari database operasional.
   - Kredensial database tidak pernah bocor ke process list baris perintah atau log (menggunakan berkas konfigurasi sementara `--defaults-extra-file`).
   - Tidak ada passphrase atau berkas arsip backup yang ter-commit ke Git.

---

## 3. Berkas yang Berubah pada Fase 02b
- Backend:
  - `laravel-api/config/backup.php` (baru): konfigurasi path backup, lampiran, passphrase, allowlist target disposable, dan forbidden databases.
  - `laravel-api/config/database.php`: penambahan koneksi `restore_drill` (database `school_restore_drill_uat`).
  - `laravel-api/app/Support/AbilityCatalog.php`: pendaftaran kemampuan bernama `backup.manage` dan default peran `admin-sistem`.
  - `laravel-api/database/migrations/2029_07_22_000001_add_backup_manage_ability.php` (baru): migration pendaftaran `backup.manage` ke tabel abilities dan penautan ke peran `admin-sistem`.
  - `laravel-api/app/Services/BackupService.php` (baru): service backup database (mysqldump / sqlite), ZipArchive AES-256, manifest, companion sha256, dan atomic publishing.
  - `laravel-api/app/Services/RestoreDrillService.php` (baru): service restore drill, fail-safe target koneksi, anti-Zip-Slip, audit dump SQL, isolasi lampiran, dan pembuktian multi-faktor.
  - `laravel-api/app/Console/Commands/BackupRunCommand.php` (baru): command `app:backup-run`.
  - `laravel-api/app/Console/Commands/RestoreDrillCommand.php` (baru): command `app:restore-drill`.
  - `laravel-api/app/Http/Controllers/BackupController.php` (baru): endpoint list dan download arsip privat berizin `backup.manage`.
  - `laravel-api/routes/api.php`: rute `GET /api/backups` dan `GET /api/backups/{filename}/download`.
  - `laravel-api/tests/Feature/Backup/BackupRunTest.php` (baru): 6 tes otorisasi, path traversal, dan command backup.
  - `laravel-api/tests/Feature/Backup/RestoreDrillTest.php` (baru): 8 tes restore drill, fail-safe operasional, Zip Slip, SQL audit, dan integritas.
  - `laravel-api/tests/Feature/Backup/MariaDbIntegrationTest.php` (baru): tes integrasi round-trip nyata MariaDB lokal.
- Dokumentasi & Kontrak:
  - `docs/DECISIONS.md`: pencatatan keputusan operasional DEC-18.
  - `docs/API-CONTRACTS.md`: kontrak API backup `/api/backups`.
  - `docs/OPERATIONS.md`: runbook operasi backup, restore drill, pengelolaan passphrase, dan cold storage.
  - `docs/SECURITY-DATA.md`: proteksi keamanan backup, isolasi kredensial, dan fail-safe restore.
  - `reports/feature-update/PHASE-02B-BACKUP-RESTORE.md` (baru): laporan implementasi Subfase 02b lengkap.
  - `reports/feature-update/PHASE-02B-MANUAL-REVIEW.md` (baru): panduan manual UAT 02b (10 butir checklist, status PENDING).
  - `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`, `CHANGELOG.md`: sinkronisasi checkpoint dan status.

---

## 4. Status Tugas Subfase 02b
1. **Pencadangan Mandiri & Terenkripsi**: **SELESAI**. `app:backup-run` memproduksi arsip ZIP AES-256, database dump via mysqldump aman, berkas lampiran privat, dan manifest JSON.
2. **Atomic Publishing & Companion Checksum**: **SELESAI**. Pemrosesan di direktori temporer `.tmp-*` dengan pembersihan seketika; companion checksum `.sha256` tersimpan di luar arsip.
3. **Endpoint Unduhan Berizin**: **SELESAI**. `GET /api/backups` dan download stream privat terlindungi `backup.manage` dan anti-path-traversal.
4. **Restore Drill Terisolasi**: **SELESAI**. `app:restore-drill` memulihkan dan memvalidasi multi-faktor ke target disposable `school_restore_drill_uat`; fail-safe keras menolak database operasional `backdata_administrations`.
5. **Pemeriksaan Manual Pemilik**: **PENDING**. Tersedia 10 butir checklist di `PHASE-02B-MANUAL-REVIEW.md` menunggu pemeriksaan manual pemilik.

---

## 5. Server & Lingkungan Aktif
Server UAT lokal yang diketahui masih berjalan (jangan dihentikan sembarangan):
- **API UAT**: port `8001` (PID 12720, `php artisan serve --host=127.0.0.1 --port=8001 --env=uat`)
- **SPA UAT**: port `4001` (PID 16624, `vite --mode uat --host 127.0.0.1 --port 4001`)
- **Database UAT**: MySQL `school_access_uat` (disposable, terpisah dari `backdata_administrations`)
- **Database Target Drill**: MySQL `school_restore_drill_uat` (disposable, target restore drill)
- Server operasional pada port `8000` dibiarkan utuh.
- **PENTING**: Dilarang mengakses data operasional atau membersihkan database UAT `school_access_uat`.

---

## 6. Pekerjaan Pertama Sesi Baru (Subfase 02b Menunggu Review Pemilik)
1. Menunggu pemeriksaan manual pemilik atas Subfase 02b (checklist #1–#10 di `PHASE-02B-MANUAL-REVIEW.md`).
2. Setelah disetujui pemilik, catat persetujuan manual dalam commit dokumentasi tersendiri.
3. Menunggu otorisasi pemilik untuk memulai Subfase 02c (Jadwal dan Monitoring Backup). Jangan mendahului implementasi 02c tanpa instruksi eksplisit pemilik.
