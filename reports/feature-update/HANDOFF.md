# Handoff pengembangan aplikasi sekolah

Checkpoint terbaru: 2026-10-20 (WIB). Branch `main`.
Baseline terakhir yang dilaporkan: `e8eaea4` ("docs(phase-02b): align operations runbook with interactive prompts and empty target requirements").
Kebijakan commit/push: commit dan push per fase/koreksi ke branch kerja aktif telah **diotorisasi**; merge, force-push, rewrite history, dan deploy manual **tetap tidak diotorisasi**.

Catatan perubahan pemilik: berkas log bukti `console.txt` milik pemilik tetap ada dan **tidak** di-commit.

---

## 1. Status Fase
- **Fase 00 — Audit baseline**: PARTIAL (source & environment audit selesai; browser NOT RUN).
- **Fase 01 — Hak akses, autentikasi dan lingkungan aman**: **DITERIMA LENGKAP & DITUTUP** (Fase 01a, 01b, dan 01c seluruhnya DITERIMA PEMILIK).
- **BLK-03**: **CLOSED pada source aktif** (seluruh akun pengguna riil dan data siswa riil telah dibersihkan dari seeder aktif; data pada riwayat commit Git historis belum dihapus).
- **Fase 02 — Integritas Operasional**: **IN PROGRESS / PARTIAL (02a DITERIMA, 02b DITERIMA, 02c IMPLEMENTED & PENDING REVIEW)**:
  - **Subfase 02a — Audit Server dan Rollback Terbatas**: **DITERIMA PEMILIK** (seluruh checklist manual #1–#9 APPROVED 2026-10-19 WIB mengacu baseline `cf94cae`).
  - **Subfase 02b — Backup Manual dan Restore Drill**: **DITERIMA PEMILIK** (seluruh checklist manual #1–#10 APPROVED 2026-10-20 WIB mengacu baseline `e8eaea4`).
  - **Subfase 02c — Jadwal dan Monitoring Backup**: **IMPLEMENTED & FULLY VERIFIED** (Pemeriksaan manual pemilik: **PENDING** mengacu `reports/feature-update/PHASE-02C-MANUAL-REVIEW.md`).
- **Keputusan Terbuka yang Masih OPEN (belum menghambat)**:
  1. Makna `status_user_id` (jangan disamakan dengan `status_active`).
  2. Deployment CORS/Sanctum produksi.
  3. Strategi pencabutan token dibanding sesi.
- **Batasan Penerimaan**:
  - Penerimaan manual Fase 01, Subfase 02a, dan Subfase 02b **bukan izin deployment ke produksi** dan bukan izin pencadangan data operasional aktif.
  - Backup dan restore drill dibuktikan menggunakan data UAT sintetis (`school_access_uat` dan fixture lampiran privat sintetis).
  - Modul lampiran operasional belum tersedia; konsistensi lintas database–lampiran operasional tetap **N/A (belum dibuktikan)**.
  - Deteksi deviasi berkas (pre/post-scan) bukan snapshot atomik database–berkas. Penggunaan pada lampiran operasional produksi memerlukan mekanisme penghentian mutasi sementara (*write quiescence*).
  - Perlindungan audit & immutability terbatas pada lapisan API aplikasi dan ORM Eloquent, bukan kekebalan terhadap root DB/OS admin.
  - Pemantauan status cadangan bersifat pasif via API/JSON dan log Laravel; belum ada notifikasi eksternal.
  - Mutex lock mengamankan single-server concurrency; belum mendukung multi-server cluster.
  - Bulk deletion di frontend berjalan per-record atomic, bukan satu transaksi batch lintas item.
  - Modul scaffold (Student CRUD, Employee CRUD, Rombel write, Surat, Izin, Pembelajaran) tetap N/A.

---

## 2. Pemeriksaan yang Dijalankan & Hasil (Subfase 02c Terakhir)
1. **Automated Backend**:
   - Perintah: `php artisan test` pada database SQLite in-memory `:memory:` terisolasi dan database integrasi MariaDB lokal.
   - Hasil: **152 passed (702 assertions)**, mencakup 42 tes di suite `tests/Feature/Backup`:
     - Otorisasi endpoint `GET /api/backups`, `GET /api/backups/status`, dan download `GET /api/backups/{filename}/download` (401 guest, 403 operator, 200 admin-sistem).
     - Validasi anti-path-traversal pada nama file download.
     - Mode non-interaktif: sukses tanpa prompt bila key valid; gagal seketika tanpa prompt dan tanpa secret print bila key kosong.
     - Mutex lock atomik: menolak overlap backup manual, terjadwal, dan rotasi; status lewati tercatat tanpa menimpa `last_success`; lock dilepas saat gagal.
     - Penjadwalan opt-in: default schedule OFF; aktif pada 02:00 dan 02:30 Asia/Jakarta bila di-enable.
     - Retensi 7 harian + 4 mingguan: preservasi backup terakhir sukses, union harian/mingguan, deduplikasi tanggal, rotasi dilewati bila backup hari ini gagal.
     - Mode `--dry-run`: kandidat penghapusan dilaporkan tanpa mutasi fisik pada berkas.
     - Monitoring status & freshness detection dinamis: status `NEVER_BACKED_UP`, `OVERDUE` (> 28 jam), `DEGRADED`, dan `HEALTHY` dihitung saat runtime; pembedaan tegas `backup_created` vs `restore_verified`.
     - Uji integrasi nyata MariaDB lokal: round-trip backup dari `school_access_uat` dengan `mysqldump` dan restore drill ke `school_restore_drill_uat` dengan `mysql.exe` terbukti **PASS** (12 assertions).
   - Style check: `./vendor/bin/pint --test` (13 files) → **PASS** (100% clean).
2. **Automated Frontend**:
   - Perintah: `node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`.
   - Hasil: **15 passed (0 failed)**.
   - Build: `npm run build` → **PASS** (0 errors).
3. **Lingkungan & Keamanan**:
   - Database operasional `backdata_administrations` sama sekali tidak disentuh.
   - Kredensial database diisolasi menggunakan berkas konfigurasi sementara `--defaults-extra-file`.
   - Tidak ada passphrase atau berkas arsip backup yang ter-commit ke Git.

---

## 3. Berkas yang Berubah pada Fase 02c
- Backend:
  - `laravel-api/config/backup.php`: penambahan konfigurasi penjadwalan, timezone Asia/Jakarta, retensi 7+4, monitoring file, lock file.
  - `laravel-api/app/Services/BackupMutex.php` (baru): mutex file lock non-blocking atomik untuk mencegah overlap.
  - `laravel-api/app/Services/BackupMonitoringService.php` (baru): service monitoring pasif, freshness detection 28 jam, pencatatan atomik.
  - `laravel-api/app/Services/BackupRetentionService.php` (baru): algoritma retensi bertingkat 7 harian + 4 mingguan, safety preservation.
  - `laravel-api/app/Console/Commands/BackupPruneCommand.php` (baru): command `app:backup-prune [--dry-run]`.
  - `laravel-api/app/Console/Commands/BackupRunCommand.php`: penambahan opsi `--non-interactive`, integrasi BackupMutex dan BackupMonitoringService.
  - `laravel-api/app/Console/Commands/RestoreDrillCommand.php`: integrasi BackupMutex dan pelaporan drill ke monitoring service.
  - `laravel-api/app/Console/Kernel.php`: pendaftaran jadwal harian 02:00 dan prune 02:30 Asia/Jakarta dengan `withoutOverlapping(60)`.
  - `laravel-api/app/Http/Controllers/BackupController.php`: penambahan method `status()`.
  - `laravel-api/routes/api.php`: penambahan rute `GET /api/backups/status`.
  - `laravel-api/.env.example`: dokumentasi variabel env backup tanpa rahasia.
  - `laravel-api/tests/Feature/Backup/BackupScheduleTest.php` (baru): 6 tes non-interaktif, timezone scheduler, dan mutex lock.
  - `laravel-api/tests/Feature/Backup/BackupPruneTest.php` (baru): 7 tes evaluasi retensi, dry-run, group deletion, safety preservation.
  - `laravel-api/tests/Feature/Backup/BackupMonitoringTest.php` (baru): 7 tes health status, freshness alert, distinct metrics, dan API status.
- Dokumentasi & Kontrak:
  - `docs/DECISIONS.md`: pencatatan keputusan operasional DEC-19.
  - `docs/API-CONTRACTS.md`: pembaruan kontrak API `/api/backups/status`.
  - `docs/OPERATIONS.md`: penambahan runbook retensi, monitoring pasif, Task Scheduler, dan Cron.
  - `reports/feature-update/PHASE-02C-MONITORING-SCHEDULE.md` (baru): laporan teknis Subfase 02c.
  - `reports/feature-update/PHASE-02C-MANUAL-REVIEW.md` (baru): panduan manual UAT 02c (10 butir checklist, status PENDING).
  - `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`, `CHANGELOG.md`: sinkronisasi checkpoint dan status.

---

## 4. Status Tugas Subfase 02c
1. **Backup Non-Interaktif & Scheduler Opt-In**: **SELESAI**. `app:backup-run --non-interactive` membaca config lokal; jadwal harian 02:00 dan prune 02:30 WIB terdaftar di Kernel dengan default OFF.
2. **Pencegahan Overlap (Shared Mutex)**: **SELESAI**. `BackupMutex` mencegah tabrakan proses manual, terjadwal, dan rotasi.
3. **Penegakan Retensi Bertingkat**: **SELESAI**. `app:backup-prune` memelihara 7 harian + 4 mingguan dan melindungi backup terakhir secara mutlak; mode `--dry-run` tersedia.
4. **Monitoring Pasif & Freshness Alert**: **SELESAI**. `monitoring.json` atomik dan endpoint `GET /api/backups/status` mendeteksi status `OVERDUE` (> 28 jam) secara dinamis.
5. **Pemeriksaan Manual Pemilik**: **PENDING**. Tersedia 10 butir checklist di `PHASE-02C-MANUAL-REVIEW.md` menunggu pemeriksaan manual pemilik.

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

## 6. Pekerjaan Pertama Sesi Baru (Subfase 02c Menunggu Review Pemilik)
1. Menunggu pemeriksaan manual pemilik atas Subfase 02c (checklist #1–#10 di `PHASE-02C-MANUAL-REVIEW.md`).
2. Setelah disetujui pemilik, catat persetujuan manual dalam commit dokumentasi tersendiri.
3. Setelah 02a, 02b, dan 02c seluruhnya disetujui pemilik, Fase 02 resmi ditutup di roadmap. Menunggu arahan pemilik untuk perencanaan Fase 03 (Data Siswa).
