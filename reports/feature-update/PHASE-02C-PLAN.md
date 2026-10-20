# Rencana Kerja Teknis Subfase 02c — Jadwal, Retensi, dan Monitoring Backup

- Tanggal WIB: 2026-10-20
- Mode: **Perencanaan teknis murni (read-only)**. Tidak ada implementasi kode, penjadwalan aktif, pemasangan cron/task scheduler, atau manipulasi arsip/data pada langkah ini.
- Status subfase: **PLANNING / READY FOR OWNER REVIEW**.
- Prasyarat: Subfase 02a dan Subfase 02b telah **DITERIMA LENGKAP OLEH PEMILIK** (02a APPROVED 2026-10-19 WIB, 02b APPROVED 2026-10-20 WIB mengacu baseline `e8eaea4`).
- Dokumen acuan: `AGENTS.md`, `ROADMAP.md`, `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`, `docs/OPERATIONS.md`, `docs/SECURITY-DATA.md`, register keputusan `DEC-02`, `DEC-09`, `DEC-17`, `DEC-18`.

---

## 1. Tujuan dan Ruang Lingkup Subfase 02c

Subfase 02c bertujuan mengotomatiskan siklus hidup pencadangan (backup automation), menegakkan kebijakan rotasi retensi bertingkat (retention pruning), dan menyediakan pemantauan status kesehatan cadangan (observability & freshness detection):

1. **Jadwal Otomatis Non-Interaktif**:
   - Menjalankan pencadangan otomatis secara periodik pada jam beban terendah sekolah tanpa memerlukan kehadiran operator.
   - Eksekusi non-interaktif murni: passphrase rahasia dibaca dari konfigurasi rahasia lokal (`BACKUP_ENCRYPTION_KEY`); jika tidak tersedia, proses otomatis dibatalkan dengan kode keluar gagal dan dicatat pada sistem monitoring tanpa menunggu prompt.
2. **Pencegahan Tumpang-Tindih (Concurrency Lock & Overlap Prevention)**:
   - Mencegah eksekusi bersamaan antara pencadangan manual, pencadangan terjadwal, dan proses rotasi retensi menggunakan mutex lock atomik (`withoutOverlapping` / file lock).
3. **Penegakan Retensi Bertingkat (7 Harian + 4 Mingguan)**:
   - Menerapkan algoritma pemilihan arsip harian dan mingguan sesuai keputusan yang disetujui (**DEC-09** dan **DEC-17**).
   - Melindungi berkas cadangan terakhir yang berhasil (*last successful backup preservation rule*); kegagalan cadangan baru tidak boleh memicu penghapusan cadangan lama.
   - Rotasi membersihkan arsip `.zip` dan berkas pendamping `.sha256` secara berpasangan; menolak keras symlink keluar direktori.
   - Menyediakan mode pratinjau (`--dry-run`) untuk meninjau arsip yang memenuhi syarat pembersihan sebelum dihapus.
4. **Monitoring Status & Deteksi Keterlambatan (Observability & Freshness Detection)**:
   - Mencatat rekaman status terstruktur pada `storage/app/backups/monitoring.json` dan endpoint API `GET /api/backups/status` (`backup.manage`).
   - Membedakan secara tegas status "backup berhasil dibuat" (*backup created*) dan status "pemulihan terverifikasi" (*restore verified drill*).
   - Mendeteksi keterlambatan eksekusi (*freshness alert*): jika waktu saat ini melebihi 28 jam dari cadangan terakhir yang berhasil, sistem menandai peringatan bahwa scheduler mungkin terhenti.
5. **Runbook Operasi Sistem (OS Task Scheduler)**:
   - Menyediakan dokumentasi instalasi penjadwal untuk Windows Task Scheduler (lingkungan operasional XAMPP lokal) dan alternatif Linux Cron.
   - Scheduler **belum dipasang atau diaktifkan** pada fase perencanaan ini.

---

## 2. Desain Jadwal dan Timezone Eksplisit

### A. Waktu dan Timezone
- **Timezone Aplikasi & Penjadwalan**: `Asia/Jakarta` (WIB, UTC+7) eksplisit.
- **Usulan Jam Pencadangan Harian**: Pukul **02:00 WIB** setiap hari.
  - *Alasan*: Jam operasional administrasi sekolah berlangsung antara pukul 06:30 s/d 17:00 WIB. Pukul 02:00 WIB adalah jendela aktivitas pengguna terendah, meminimalkan risiko penguncian transaksi atau persaingan I/O disk.
- **Usulan Jam Rotasi Retensi Harian**: Pukul **02:30 WIB** setiap hari.
  - *Alasan*: Dijalankan tepat setelah pencadangan harian selesai dan terverifikasi berhasil, sehingga berkas baru sudah terbit sebelum rotasi mengevaluasi retensi.

### B. Mekanisme Eksekusi Non-Interaktif
Perintah pencadangan otomatis dijalankan melalui command:
```bash
php artisan app:backup-run --non-interactive
```
- Jika flag `--non-interactive` aktif:
  1. Command memeriksa ketersediaan `config('backup.encryption_key')`.
  2. Jika `BACKUP_ENCRYPTION_KEY` tidak diatur atau panjangnya kurang dari 12 karakter: proses **langsung digagalkan seketika** (exit code 1), mencatat error `"Non-interactive backup aborted: BACKUP_ENCRYPTION_KEY is not configured or shorter than 12 characters"`.
  3. Tidak ada pemanggilan `$this->secret()` yang dapat menggantung proses background (*hung process*).
  4. Tidak ada argumen rahasia yang diteruskan ke baris perintah proses sistem operasi.

---

## 3. Pencegahan Overlap (Atomic Concurrency Control)

Untuk mencegah race condition dan kerusakan arsip akibat eksekusi simultan:
1. **Mutex Lock Atomik**:
   - Proses pencadangan dan pembersihan menggunakan file lock eksklusif di direktori privat: `storage/app/backups/.backup.lock`.
   - Menggunakan fungsi native `flock($fp, LOCK_EX | LOCK_NB)`:
     ```php
     $lockFile = storage_path('app/backups/.backup.lock');
     $fp = fopen($lockFile, 'c+');
     if (!flock($fp, LOCK_EX | LOCK_NB)) {
         $this->warn('Another backup or prune process is already running. Aborting current run.');
         return Command::SUCCESS;
     }
     ```
2. **Jaminan Pelepasan Lock**:
   - Lock dilepaskan pada blok `finally` segera setelah proses selesai atau gagal.
3. **Pada Scheduler Laravel**:
   - Pendaftaran pada `app/Console/Kernel.php` dikonfigurasi dengan `->withoutOverlapping(60)` untuk mencegah penumpukan tugas jika dump memakan waktu lebih dari perkiraan.

---

## 4. Desain Algoritma Rotasi Retensi (7 Harian + 4 Mingguan)

Kebijakan retensi bertingkat (**DEC-09**, **DEC-17**) diimplementasikan melalui command mandiri:
```bash
php artisan app:backup-prune [--dry-run]
```

### A. Aturan Klasifikasi & Pemilihan Berkas
Daftar seluruh berkas cadangan diurutkan dari yang terbaru ke terlama (`created_at` descending):
1. **Kelompok Harian (Daily Retention - 7 Slot)**:
   - Memeriksa rentang 7 hari kalender terakhir (Hari ke-1 s/d Hari ke-7 dari waktu sekarang).
   - Untuk setiap hari dalam jendela 7 hari tersebut, pilih **1 berkas cadangan paling baru** pada hari itu untuk dipertahankan.
2. **Kelompok Mingguan (Weekly Retention - 4 Slot)**:
   - Memeriksa rentang 4 minggu kalender terakhir (Minggu ke-1 s/d Minggu ke-4).
   - Untuk setiap minggu, pilih **1 berkas cadangan terakhir pada akhir pekan** (hari Minggu atau hari aktif terakhir dalam minggu tersebut) untuk dipertahankan.
3. **Deduplikasi (Union of Kept Archives)**:
   - Himpunan berkas yang dipertahankan adalah gabungan (*union*) dari Kelompok Harian dan Kelompok Mingguan:
     $$\text{Preserved} = \text{DailySet} \cup \text{WeeklySet}$$
   - Berkas yang memenuhi kriteria harian sekaligus mingguan tidak akan dihitung ganda dan aman dipertahankan.
4. **Kandidat Penghapusan**:
   - Berkas cadangan yang **tidak termasuk** dalam $\text{Preserved}$ diklasifikasikan sebagai kandidat penghapusan (*prune candidates*).

### B. Aturan Pengaman Mutlak (Safety Guards)
1. **Preservasi Cadangan Terakhir (*Last Successful Backup Protection*)**:
   - Berkas cadangan valid paling baru secara keseluruhan **MUTLAK DILARANG DIHAPUS**, tanpa memandang usia atau kalkulasi retensi.
   - Jika proses backup baru pada hari ini gagal, cadangan hari sebelumnya tidak akan terhapus karena kegagalan tersebut.
2. **Batas Minimum Arsip**:
   - Jika jumlah total arsip valid yang ada $\le 1$, proses rotasi langsung selesai tanpa menghapus apa pun.
3. **Pembersihan Berpasangan Atomik**:
   - Setiap penghapusan berkas `backup-*.zip` wajib menghapus berkas pendamping `backup-*.zip.sha256` yang bersangkutan.
4. **Guard Anti-Symlink & Batasan Direktori**:
   - Pemindaian berkas menggunakan `File::files($backupDir)`.
   - Nama berkas wajib mematuhi regex `/^backup-[a-zA-Z0-9._-]+\.zip$/`.
   - Path absolut diverifikasi menggunakan `realpath()`; jika path berada di luar direktori `storage/app/backups`, berkas dilewati (*skip*) dan dicatat sebagai anomali.
   - Berkas sementara (`.tmp*`), berkas manifest terpisah, atau berkas pengujian tidak akan disentuh oleh rotasi.
5. **Mode Dry-Run (`--dry-run`)**:
   - Menampilkan tabel berkas yang dipertahankan (beserta alasan: Harian/Mingguan/Terbaru) dan tabel berkas yang akan dihapus, tanpa melakukan penghapusan fisik berkas.

---

## 5. Desain Monitoring, Status Kesehatan, dan Freshness Detection

### A. Struktur File Status Monitoring (`storage/app/backups/monitoring.json`)
Setiap kali proses backup atau restore drill dieksekusi, status dicatat secara atomik:
```json
{
  "updated_at": "2026-10-20T02:05:12+07:00",
  "timezone": "Asia/Jakarta",
  "backup_status": {
    "last_run_at": "2026-10-20T02:00:01+07:00",
    "last_finished_at": "2026-10-20T02:05:12+07:00",
    "last_result": "SUCCESS",
    "last_duration_seconds": 311.24,
    "last_archive_file": "backup-production-20261020_020001.zip",
    "last_archive_size_bytes": 15420310,
    "last_archive_sha256": "abcdef123456...",
    "error_message": null
  },
  "restore_drill_status": {
    "last_drill_at": "2026-10-19T14:30:00+07:00",
    "last_drill_result": "PASS",
    "last_drill_duration_seconds": 45.12,
    "last_target_database": "school_restore_drill_uat",
    "tables_verified_count": 35
  },
  "health": {
    "freshness": "HEALTHY",
    "hours_since_last_backup": 2.1,
    "max_allowed_age_hours": 28,
    "is_backup_overdue": false,
    "backup_created": true,
    "restore_verified": true
  }
}
```

### B. Pembedaan Status Cadangan vs Status Pemulihan
- **`backup_created: true/false`**: Menandakan apakah berkas arsip fisik berhasil diproduksi dan lolos verifikasi internal.
- **`restore_verified: true/false`**: Menandakan apakah pemulihan bencana (*disaster recovery*) telah dibuktikan secara nyata ke target database disposable.
- *Prinsip*: Keberhasilan pembuatan berkas arsip **bukan** bukti bahwa arsip dapat dipulihkan. Keduanya dipisahkan dengan jelas pada metrik monitoring.

### C. Deteksi Cadangan Terlambat (*Freshness Detection*)
- Jendela toleransi maksimal: **28 jam** sejak cadangan terakhir yang berhasil.
  - *Alasan*: Jadwal harian berjalan setiap 24 jam. Jeda 4 jam tambahan diberikan untuk mengantisipasi toleransi waktu eksekusi antrian tanpa memicu alarm palsu (*false positive*).
- Jika waktu berjalan saat ini $> 28\text{ jam}$ sejak `last_finished_at` dari cadangan sukses terakhir:
  - Nilai `health.freshness` berubah menjadi **`OVERDUE`**.
  - Nilai `health.is_backup_overdue` bernilai **`true`**.
  - Kondisi ini menandakan bahwa sistem otomasi (Windows Task Scheduler / cron) tidak berjalan, server mati, atau proses terjadwal terus-menerus gagal sebelum sempat menulis log.

### D. Endpoint API Monitoring (`GET /api/backups/status`)
- Dilindungi oleh middleware autentikasi dan kemampuan bernama **`backup.manage`**.
- Mengembalikan data status monitoring di atas dalam format JSON terstruktur.
- Tidak membocorkan password, passphrase, string koneksi, atau kredensial database.

---

## 6. Runbook Pemasangan Penjadwal Sistem Operasi (Untuk Referensi Masa Depan)

*PENTING: Langkah di bawah ini adalah panduan operasional. TIDAK BOLEH dipasang atau diaktifkan pada sesi perencanaan saat ini.*

### A. Lingkungan Windows Server / XAMPP (Lingkungan Lokal Saat Ini)
1. Buka **Task Scheduler** pada Windows (`taskschd.msc`).
2. Klik **Create Task**:
   - General: Beri nama `SchoolApp_Daily_Backup`. Pilih *Run whether user is logged on or not*.
   - Triggers: *Daily*, mulai pukul `02:00:00`, recur every `1 days`.
   - Actions:
     - Program/script: `C:\xampp\php\php.exe`
     - Add arguments: `artisan schedule:run --env=production`
     - Start in: `C:\xampp\htdocs\app-administration_school\laravel-api`
   - Settings: Centang *Stop the task if it runs longer than: 2 hours*.

### B. Lingkungan Linux Server (Hosting / Cloud di Masa Depan)
1. Buka konfigurasi crontab:
   ```bash
   crontab -e
   ```
2. Tambahkan entri pemanggilan scheduler Laravel setiap menit:
   ```bash
   * * * * * cd /var/www/app-administration_school/laravel-api && php artisan schedule:run >> /dev/null 2>&1
   ```

---

## 7. Strategi Pengujian Terisolasi Subfase 02c

1. **Uji Otomatis Unit & Feature (`php artisan test`)**:
   - `tests/Feature/Backup/BackupScheduleTest.php`:
     - Uji eksekusi non-interaktif berhasil saat passphrase tersedia di konfigurasi, dan gagal seketika tanpa prompt jika passphrase kosong.
     - Uji mutex lock: proses kedua yang berjalan saat lock aktif dibatalkan secara aman (*aborted/skipped*).
     - Uji pendaftaran command pada `Schedule` Laravel dengan frekuensi harian pukul 02:00 WIB dan tanpa overlap.
   - `tests/Feature/Backup/BackupPruneTest.php`:
     - Uji algoritma retensi dengan memanipulasi timestamp berkas sintetis (menggunakan fixture 15 arsip backup harian dan mingguan).
     - Uji bahwa 7 harian dan 4 mingguan dipertahankan secara akurat.
     - Uji bahwa berkas backup terakhir yang berhasil **tidak pernah dihapus**.
     - Uji bahwa berkas pendamping `.sha256` ikut terhapus saat arsip `.zip` dihapus.
     - Uji mode `--dry-run`: kandidat penghapusan dilaporkan namun tidak ada berkas yang terhapus di disk.
     - Uji keamanan: berkas temporer atau symlink di luar direktori tidak disentuh.
   - `tests/Feature/Backup/BackupMonitoringTest.php`:
     - Uji rekaman status `monitoring.json` terisi akurat setelah backup/drill.
     - Uji deteksi `OVERDUE` saat waktu virtual dimajukan $> 28\text{ jam}$ (`Carbon::setTestNow(...)`).
     - Uji endpoint `GET /api/backups/status`: 401 guest, 403 operator, 200 admin-sistem tanpa membocorkan rahasia.
2. **Pemberian Proteksi Fixture UAT**:
   - Seluruh pengujian otomasi berjalan pada disk `testing` terisolasi (`storage/framework/testing/disks/testing/backups`), sehingga berkas backup UAT milik pemilik pada `storage/app/backups` tidak akan tersentuh atau terhapus.

---

## 8. Pertanyaan Keputusan yang Diperlukan dari Pemilik

Sebelum pengkodean Subfase 02c dimulai, pemilik diharapkan mengonfirmasi 2 pertanyaan operasional praktis:

1. **Waktu Pelaksanaan Backup Terjadwal**:
   - *Usulan*: Pukul **02:00 WIB** setiap hari untuk backup, dilanjutkan pukul **02:30 WIB** untuk rotasi retensi.
   - *Pertanyaan*: Apakah pemilik menyetujui waktu pelaksanaan pukul 02:00 WIB, atau ada preferensi jam lain?
2. **Tindakan Saat Status Backup `OVERDUE`**:
   - *Usulan*: Status `OVERDUE` dicatat pada `monitoring.json`, ditampilkan pada endpoint API `GET /api/backups/status` untuk Administrator Sistem, dan dicatat sebagai `Log::warning(...)` pada log harian Laravel.
   - *Pertanyaan*: Apakah pencatatan status dan warning log ini sudah mencukupi, atau ada instruksi integrasi notifikasi lain (mis. webhook/email, jika di luar scope saat ini dicatat sebagai perbaikan masa depan)?
