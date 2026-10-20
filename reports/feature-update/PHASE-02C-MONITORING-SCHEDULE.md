# Laporan Subfase 02c — Penjadwalan, Retensi Otomatis, dan Monitoring Backup

- Tanggal WIB: 2026-10-20
- Branch kerja: `main`
- Status teknis: **IMPLEMENTED & FULLY VERIFIED (152 backend tests PASS, Pint 100% clean)**
- Status review pemilik: **PENDING (Menunggu Pemeriksaan Manual Pemilik di `PHASE-02C-MANUAL-REVIEW.md`)**
- Prasyarat: Subfase 02a dan Subfase 02b telah **DITERIMA LENGKAP OLEH PEMILIK**; keputusan DEC-19 APPROVED
- Database & storage:
  - Database pengujian otomatis: **SQLite in-memory `:memory:` terisolasi** dan disk storage `testing`.
  - Database integrasi nyata lokal: MariaDB 10.4.32 port 3306 sumber `school_access_uat` (sintetis) dan target disposable `school_restore_drill_uat`.
  - Database operasional: `backdata_administrations` (TERLINDUNGI & TIDAK PERNAH DISENTUH).

---

## 1. Ringkasan Eksekutif

Subfase 02c mengimplementasikan otomatisasi penjadwalan non-interaktif, penegakan kebijakan rotasi retensi bertingkat (7 harian + 4 mingguan), dan sistem pemantauan pasif berbasis API dan status terstruktur sesuai keputusan pemilik **DEC-17**, **DEC-18**, dan **DEC-19**:

1. **Pencadangan Non-Interaktif & Scheduler Opt-In**:
   - Menambahkan opsi `--non-interactive` pada perintah `app:backup-run`. Passphrase dibaca dari konfigurasi rahasia lokal `config('backup.encryption_key')`. Jika tidak tersedia atau `< 12` karakter, proses gagal seketika tanpa prompt dan tanpa mencetak secret.
   - Pendaftaran tugas pada `app/Console/Kernel.php` menggunakan timezone eksplisit `Asia/Jakarta`:
     - Backup harian pukul **02:00 WIB** (`app:backup-run --non-interactive`).
     - Rotasi retensi harian pukul **02:30 WIB** (`app:backup-prune`).
   - Default penjadwalan otomatis adalah **OFF (opt-in)** melalui konfigurasi `BACKUP_SCHEDULE_ENABLED=false` demi mencegah eksekusi tak terkendali di lingkungan pengembangan/pengujian.
2. **Pencegahan Tumpang-Tindih Bersama (Shared Mutex Lock)**:
   - Dibuat service `BackupMutex` yang memanfaatkan file lock eksklusif non-blocking (`flock`) pada `storage/app/backups/.backup.lock`.
   - Mutex ini dipatuhi oleh seluruh perintah: `app:backup-run` (manual dan non-interaktif), `app:backup-prune`, dan `app:restore-drill`.
   - Jika proses lain sedang berjalan, proses berikutnya dilewati secara aman (*skipped*) dan status lewati dicatat di monitoring tanpa menghapus rekaman sukses terakhir.
   - Mutex menjamin pelepasan lock di blok `finally` bahkan saat terjadi exception.
   - *Batasan*: Mekanisme file lock berlaku untuk satu server fisik/lokal (*single-server support*).
3. **Algoritma Retensi Bertingkat (7 Harian + 4 Mingguan)**:
   - Command `app:backup-prune [--dry-run]` mengimplementasikan pemilihan arsip berbasis tanggal dan minggu kalender `Asia/Jakarta`:
     - **Kelompok Harian**: Menyimpan 1 arsip terbaru per hari untuk 7 tanggal unik terbaru yang memiliki backup sukses (termasuk hari berjalan).
     - **Kelompok Mingguan**: Menyimpan 1 arsip akhir pekan (Senin–Minggu) per minggu untuk 4 minggu kalender unik terbaru.
     - **Deduplikasi Union**: Arsip yang memenuhi kedua kriteria hanya dihitung satu entitas.
   - **Aturan Pengaman Mutlak (*Safety Rules*)**:
     - Arsip cadangan valid paling akhir (*most recent successful backup*) **MUTLAK SELALU DILINDUNGI**, bahkan jika retensi menghitungnya berumur tua atau backup baru gagal.
     - Jika jumlah total arsip valid $\le 1$, tidak ada berkas yang dihapus.
     - Jika proses backup hari ini berstatus `FAILED`, rotasi otomatis hari itu dilewati (*skipped*) untuk mencegah penghapusan cadangan lama saat cadangan baru belum berhasil.
     - Jika backup masih berjalan pada pukul 02:30, rotasi retensi otomatis dilewati via mutex lock.
   - **Pembersihan Berpasangan & Keamanan Direktori**:
     - Setiap penghapusan arsip `.zip` otomatis menghapus file checksum pendamping `.zip.sha256`.
     - Memvalidasi path absolut dengan `realpath()`: symlink atau berkas di luar `storage/app/backups` diabaikan.
     - Berkas temporer (`.tmp*`) atau berkas dengan metadata/checksum rusak tidak dianggap sebagai backup valid dan tidak dihapus diam-diam.
   - **Mode Pratinjau (`--dry-run`)**: Menampilkan tabel arsip yang dipertahankan (beserta alasan retensi) dan daftar kandidat penghapusan tanpa mutasi fisik pada disk.
4. **Monitoring Pasif & Freshness Detection Dinamis**:
   - Dibuat service `BackupMonitoringService` yang menyimpan status terstruktur pada `storage/app/backups/monitoring.json` secara atomik (menggunakan temporary write + rename).
   - Menyimpan `last_attempt` (status, timestamp, durasi, error tersanitasi) dan `last_success` (timestamp, durasi, nama file, ukuran, sha256). Upaya yang gagal atau dilewati **tidak pernah menimpa** nilai `last_success`.
   - **Pembeda Tegas**: Membedakan `backup_created: true/false` dari `restore_verified: true/false`. `restore_verified` hanya bernilai `true` bila hasil restore drill dinyatakan `PASS` dan merujuk secara spesifik pada nama arsip dan checksum yang diuji.
   - **Status Kesehatan Dinamis (*Freshness Detection*)**:
     - `NEVER_BACKED_UP`: Jika belum pernah ada backup yang sukses tercatat.
     - `OVERDUE`: Jika waktu saat status dibaca $> 28\text{ jam}$ sejak `last_success.timestamp` (dihitung dinamis saat runtime, tetap terdeteksi meski scheduler tidak berjalan).
     - `ARCHIVE_MISSING`: Jika berkas cadangan dari `last_success` tidak ditemukan secara fisik di disk.
     - `METADATA_CORRUPTED`: Jika berkas monitoring rusak atau tidak valid.
     - `DEGRADED`: Jika cadangan valid masih dalam batas 28 jam namun upaya terakhir gagal.
     - `HEALTHY`: Semua parameter normal.
   - Endpoint terproteksi `GET /api/backups/status` dilindungi oleh `backup.manage`. Rute didaftarkan sebelum `/api/backups/{filename}/download` sehingga tidak tertangkap sebagai parameter nama file.

---

## 2. Inventarisasi Komponen Baru & Jalur Operasi

| Komponen | Tipe | Lokasi File | Fungsi & Proteksi |
|---|---|---|---|
| `config/backup.php` | Konfigurasi | `laravel-api/config/backup.php` | Konfigurasi jadwal, timezone Asia/Jakarta, retensi 7+4, monitoring file, lock file |
| `BackupMutex` | Service | `laravel-api/app/Services/BackupMutex.php` | Mutex file lock non-blocking atomik, pencegahan overlap, single-server guard |
| `BackupMonitoringService` | Service | `laravel-api/app/Services/BackupMonitoringService.php` | Pengelolaan `monitoring.json`, freshness detection 28 jam, pemisahan backup vs restore |
| `BackupRetentionService` | Service | `laravel-api/app/Services/BackupRetentionService.php` | Algoritma retensi 7 harian + 4 mingguan, safety preservation, dry-run evaluation |
| `BackupPruneCommand` | CLI Command | `laravel-api/app/Console/Commands/BackupPruneCommand.php` | Perintah `php artisan app:backup-prune [--dry-run]` |
| `BackupRunCommand` | CLI Command | `laravel-api/app/Console/Commands/BackupRunCommand.php` | Opsi `--non-interactive`, integrasi BackupMutex dan BackupMonitoringService |
| `RestoreDrillCommand` | CLI Command | `laravel-api/app/Console/Commands/RestoreDrillCommand.php` | Integrasi BackupMutex dan pencatatan hasil drill ke monitoring service |
| `RestoreDrillService` | Service | `laravel-api/app/Services/RestoreDrillService.php` | Callback pencatatan hasil drill ke `BackupMonitoringService` |
| `BackupController` | Controller API | `laravel-api/app/Http/Controllers/BackupController.php` | Method `status()` mengembalikan metrik kesehatan sistem cadangan |
| `Kernel.php` | Console Kernel | `laravel-api/app/Console/Kernel.php` | Penjadwalan backup 02:00 dan prune 02:30 WIB dengan `withoutOverlapping(60)` |
| `routes/api.php` | Routing | `laravel-api/routes/api.php` | Endpoint `GET /api/backups/status` berizin `backup.manage` |

---

## 3. Hasil Pengujian Otomatis

### A. Pengujian Backend (`php artisan test`)
- Lingkungan uji: SQLite in-memory `:memory:` terisolasi, storage disk `testing`, dan MariaDB lokal.
- Total pengujian aplikasi: **152 passed (702 assertions)**.
- Rincian pengujian Subfase 02c (27 tes baru lulus):
  - `tests/Feature/Backup/BackupScheduleTest.php` (6 tes):
    1. `non-interactive backup succeeds when valid encryption key is configured without prompts` — PASS
    2. `non-interactive backup fails immediately without prompt when encryption key is missing or short` — PASS
    3. `scheduler is disabled by default (opt-in configuration)` — PASS
    4. `scheduler registers commands at 02:00 and 02:30 Asia/Jakarta when opt-in enabled` — PASS
    5. `mutex lock prevents overlapping backup runs and logs skipped attempt without clearing last_success` — PASS
    6. `mutex lock is released on failure allowing subsequent run to acquire lock` — PASS
  - `tests/Feature/Backup/BackupPruneTest.php` (7 tes):
    7. `retention preserves single archive when only one backup exists` — PASS
    8. `retention preserves latest backup overall regardless of age or count` — PASS
    9. `retention correctly retains 7 daily, 4 weekly across month boundaries and deletes older archives` — PASS
    10. `dry run displays keep and delete candidates without modifying or deleting files` — PASS
    11. `pruning deletes both zip archive and companion sha256 file as a group` — PASS
    12. `rotation is skipped if latest backup attempt failed today to prevent data loss` — PASS
    13. `prune rejects symlinks and ignores corrupted or temporary files without deleting valid archives` — PASS
  - `tests/Feature/Backup/BackupMonitoringTest.php` (7 tes):
    14. `health status returns NEVER_BACKED_UP on fresh installation` — PASS
    15. `health status dynamically detects OVERDUE when more than 28 hours pass without running scheduler` — PASS
    16. `failed backup attempt preserves last_success and updates health status to DEGRADED` — PASS
    17. `backup_created and restore_verified remain strictly distinct metrics` — PASS
    18. `guest cannot access backup status endpoint` (401) — PASS
    19. `user without backup.manage cannot access backup status endpoint` (403) — PASS
    20. `admin with backup.manage can access status endpoint without leaking secrets or credentials` (200) — PASS
  - `tests/Feature/Backup/{BackupRunTest.php, RestoreDrillTest.php, MariaDbIntegrationTest.php}`: Seluruh 22 tes Subfase 02b tetap **100% PASS** tanpa regresi.

### B. Pemeriksaan Style Pint (`./vendor/bin/pint --test`)
- File diperiksa: 13 file PHP baru dan termodifikasi pada Subfase 02c.
- Hasil: **13 files PASS** (100% PSR-12 / Laravel clean).

---

## 4. Runbook Operasi Sistem (Task Scheduler & Cron)

*Catatan: Panduan di bawah ini adalah petunjuk instalasi untuk administrator pada saat aplikasi disiapkan untuk produksi. Pada tahap pengembangan saat ini, scheduler belum diaktifkan pada sistem operasi.*

### A. Lingkungan Windows Server / Local XAMPP
1. Buka **Task Scheduler** Windows (`taskschd.msc`).
2. Buat tugas baru (*Create Task*):
   - **General**: Nama `SchoolApp_Scheduler`, pilih *Run whether user is logged on or not*.
   - **Triggers**: *Daily*, mulai pukul `00:00:00`, berulang setiap 1 hari.
   - **Actions**:
     - Program: `C:\xampp\php\php.exe`
     - Arguments: `artisan schedule:run --env=production`
     - Start in: `C:\xampp\htdocs\app-administration_school\laravel-api`
   - **Settings**: *If the running task does not end when requested, force it to stop* (centang, set 2 jam).
3. **Prasyarat Operasional Windows**:
   - Komputer server harus menyala pada pukul 02:00 WIB agar tugas berjalan.
   - Jika mesin mati pada jam jadwal, scheduler Laravel akan mendeteksi keterlambatan saat mesin hidup kembali dan status `OVERDUE` akan terlihat pada endpoint monitoring hingga backup berhasil dieksekusi.

### B. Lingkungan Linux Server (Cron)
1. Buka crontab: `crontab -e`.
2. Tambahkan baris pemanggilan scheduler setiap menit:
   ```cron
   * * * * * cd /path/to/laravel-api && php artisan schedule:run >> /dev/null 2>&1
   ```

---

## 5. Batasan & Catatan Integritas
1. **Pemantauan Pasif**: Sistem monitoring mencatat status secara lokal dan menyediakannya via API/log, namun tidak mengirimkan email/notifikasi proaktif ke luar. Administrator bertugas memeriksa status secara berkala.
2. **Dukungan Single-Server**: Mutex file lock atomik mengamankan proses yang berjalan pada host yang sama. Jika aplikasi di kemudian hari dideploy secara multi-server/cluster, mutex harus dialihkan ke Redis cache lock terpusat.
3. **Lampiran Operasional**: Konsistensi database dan berkas lampiran operasional riil tetap berstatus **N/A** (modul lampiran belum dibangun).
