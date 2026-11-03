# Runbook & SOP Operasional Staf IT Sekolah
## Standard Operating Procedures (SOP) & Technical Runbook — Versi 1.0

Dokumen ini merupakan panduan teknis operasional definitif bagi staf IT, *system administrator*, dan teknisi pengelola Sistem Administrasi Sekolah dalam memelihara, mengoperasikan, dan menjaga kelangsungan layanan aplikasi di lingkungan produksi.

---

## 1. Arsitektur & Topologi Sistem

Aplikasi dibangun dengan arsitektur decoupled modern:
- **Backend API:** Laravel 11 (PHP 8.2+) melayani 334 endpoint REST API terstruktur dalam 10 domain modul.
- **Frontend SPA:** Vue 3, Vite, PrimeVue (Sakai template) melayani 27 halaman rute aktif berbahasa Indonesia baku.
- **Basis Data:** MariaDB 10.4+ / MySQL 8.0+ dengan mesin penyimpanan InnoDB dan penguncian transaksi ACID.
- **Penyimpanan Berkas Privat:** Terletak di luar web root publik (`storage/app/attachments/`) mencakup berkas PDF surat dinas ber-Kop (`attachments/letters`), lampiran perizinan (`attachments/leaves`), dan arsip dokumen versi *immutable* (`attachments/archives`). Tidak ada tautan langsung peramban (*no direct public URL*); unduhan wajib melalui endpoint terotorisasi dengan pengecekan wewenang dan header keamanan (`Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`).
- **Observabilitas & Keamanan Data:** Sensor otomatis Monolog PII Redaction (`[REDACTED_NIK]`, `[REDACTED]`), propagasi header pelacak kesalahan `X-Correlation-ID` pada setiap permintaan, dan otorisasi berbasis peran (RBAC 5 peran kanonikal).

---

## 2. Panduan Inisialisasi Server Baru & Instalasi Perdana

### 2.1. Prasyarat Perangkat Lunak Server
1. **Sistem Operasi:** Linux Server (Ubuntu 22.04 LTS / Debian 12 / AlmaLinux 9) ATAU Windows Server 2022+ (dengan PHP & MySQL terpasang).
2. **PHP:** Versi 8.2 atau 8.3 dengan ekstensi wajib:
   - `pdo_mysql`, `zip` (wajib mendukung enkripsi AES-256), `fileinfo`, `gd`, `bcmath`, `mbstring`, `openssl`, `tokenizer`, `xml`, `curl`.
3. **DBMS:** MariaDB 10.4+ / MySQL 8.0+ dengan port default 3306.
4. **Web Server:** Nginx 1.20+ atau Apache 2.4+ (dengan modul `mod_rewrite`, `mod_headers`, `mod_ssl`).
5. **Node.js:** Versi 20 LTS (digunakan untuk kompilasi build aset antarmuka Vue).
6. **Composer:** Versi 2.x.

### 2.2. Konfigurasi Web Server (Apache / Nginx)
Pastikan web server hanya mengarahkan *DocumentRoot* ke direktori publik:
- **Backend:** Mengarah ke direktori `laravel-api/public/`
- **Frontend SPA:** Disajikan melalui Nginx/Apache atau direktori root frontend dengan penanganan *HTML5 History Mode Fallback*:
  ```nginx
  # Contoh konfigurasi fallback Nginx untuk Frontend SPA:
  location / {
      try_files $uri $uri/ /index.html;
  }
  ```
- **Blokir Akses Berkas Sensitif:** Pastikan web server menolak keras akses ke berkas tersembunyi:
  ```nginx
  location ~ /\.(env|git|htaccess) {
      deny all;
      return 404;
  }
  ```

### 2.3. Penyiapan Izin Direktori (Directory Permissions)
Di lingkungan Linux Server:
```bash
cd /var/www/app-administration_school/laravel-api

# Berikan kepemilikan ke pengguna web server (misal www-data):
sudo chown -R www-data:www-data storage bootstrap/cache

# Atur izin baca-tulis:
sudo chmod -R 775 storage bootstrap/cache
```

### 2.4. Penyiapan Konfigurasi Lingkungan (`.env`)
```bash
cp .env.production.example .env
php artisan key:generate
```
Edit berkas `.env` dan sesuaikan kredensial basis data (`DB_DATABASE=backdata_administrations`, `DB_USERNAME`, `DB_PASSWORD`), `BACKUP_ENCRYPTION_KEY` (minimal 12 karakter acak), dan domain `APP_URL`.

### 2.5. Eksekusi Migrasi & Inisialisasi Administrator Perdana
1. Jalankan migrasi basis data lengkap:
   ```bash
   php artisan migrate --force
   ```
2. Jalankan seeder data master sistem (peran, status, referensi):
   ```bash
   php artisan db:seed --class=Database\\Seeders\\SystemRoleSeeder --force
   ```
3. Bangkitkan akun Administrator Sistem Perdana via perintah CLI aman:
   ```bash
   php artisan app:bootstrap-admin
   ```
   *Catatan Keamanan:* Perintah ini akan meminta Nama, Username, Email, NIK, dan Kata Sandi secara interaktif via prompt rahasia. Kata sandi tidak pernah disimpan di histori terminal atau log.

---

## 3. Tata Kelola Operasional Harian & Mingguan

### 3.1. Prosedur Harian (Setiap Pagi 07:00 WIB)
1. **Pengecekan Pusat Tugas & Antrean Operator:**
   Buka menu *Pusat Tugas* (`/public/tasks`) sebagai staf operator atau administrator. Pastikan tidak ada pengingat kritis yang terlewat (surat belum bernomor, izin pegawai pending approval, kelengkapan jurnal mengajar).
2. **Pengecekan Log Anomali:**
   Periksa catatan log harian pada `storage/logs/laravel-<YYYY-MM-DD>.log`.
   ```bash
   # Filter log yang mengandung galat:
   grep "ERROR" storage/logs/laravel-$(date +%Y-%m-%d).log
   ```
   Jika ditemukan galat, gunakan `correlation_id` pada entri log untuk melacak alur permintaan pengguna.
3. **Pengecekan Kesehatan Pencadangan Harian:**
   Buka dasbor sistem atau periksa via CLI:
   ```bash
   php artisan tinker --execute="echo json_encode(app(App\Services\BackupMonitoringService::class)->getHealthStatus(), JSON_PRETTY_PRINT).PHP_EOL;"
   ```
   Pastikan `status` bernilai `healthy` dan `last_backup_at` tercatat pada pukul 02:00 WIB dini hari tadi.

### 3.2. Prosedur Mingguan (Setiap Hari Jumat Sore)
1. **Review Rekap Jurnal Mengajar Guru:**
   Buka menu *Rekapitulasi Jurnal* dan cetak laporan ketidakhadiran jurnal guru yang belum terisi untuk dilaporkan kepada Kepala Sekolah.
2. **Audit Jejak Aktivitas (Audit Trail):**
   Tinjau log aktivitas pada `/public/management/audit` untuk memantau perubahan data penting (perubahan akun pengguna, perubahan hak akses peran, disposisi surat).
3. **Pengunduhan Cadangan Cold Storage (Offline):**
   Unduh arsip cadangan mingguan terbaru dari server ke media penyimpanan terpisah (harddisk eksternal ruang IT atau cloud storage terisolasi).

---

## 4. Prosedur Pencadangan, Pemulihan, dan Uji Coba (Backup & Disaster Recovery)

### 4.1. Konfigurasi Task Scheduler Otomatis
Sistem mengandalkan Laravel Scheduler yang harus dipanggil setiap menit:
- **Di Linux Server (Cron):**
  Tambahkan baris berikut ke crontab pengguna web server (`crontab -e -u www-data`):
  ```cron
  * * * * * cd /var/www/app-administration_school/laravel-api && php artisan schedule:run >> /dev/null 2>&1
  ```
- **Di Windows Server (Task Scheduler):**
  Buka Task Scheduler (`taskschd.msc`), buat Basic Task bernama `SchoolAdministrationScheduler`:
  - Trigger: *Daily*, repeat task every *1 minute*, duration *indefinitely*.
  - Action: *Start a program*, Program: `C:\xampp\php\php.exe`, Arguments: `artisan schedule:run`, Start in: `D:\xampp-htdocs\app-administration_school\laravel-api`.

Jadwal otomatis yang aktif ketika `BACKUP_SCHEDULE_ENABLED=true`:
1. **02:00 WIB:** `php artisan app:backup-run` (Menghasilkan arsip ZIP AES-256 dan hash `.sha256`).
2. **02:30 WIB:** `php artisan app:backup-prune` (Rotasi retensi: mempertahankan 7 cadangan harian + 4 mingguan).

### 4.2. Pencadangan Manual Sewaktu-waktu (*On-Demand Backup*)
Jika staf IT hendak melakukan pemeliharaan mendadak di siang hari:
```bash
php artisan app:backup-run
```
Masukkan passphrase enkripsi (minimal 12 karakter) saat diminta.

### 4.3. Prosedur Uji Pemulihan Terjadwal (*Periodic Restore Drill*)
Setiap 1 bulan sekali, staf IT wajib memvalidasi bahwa berkas cadangan benar-benar dapat dipulihkan (*recoverable*) ke database target kosong yang diizinkan (*allowlist disposable*):
```bash
# Uji coba restore ke database sekali pakai 'school_restore_drill':
php artisan app:restore-drill backup-production-2026-11-03-020000.zip --target-connection=school_restore_drill
```
*Aturan Keamanan DEC-18:*
- Perintah ini **MUTLAK MENOLAK** database operasional `backdata_administrations`.
- Perintah ini **MENOLAK** database target jika di dalamnya sudah terdapat data (mencegah penimpaan tidak sengaja).
- Laporan hasil uji pemulihan disimpan di `storage/app/backups/drill-reports/drill-report-*.json`.

---

## 5. Tata Kelola Pergantian Tahun Ajaran (*Academic Year Rollover SOP*)

Pergantian tahun ajaran (misal dari 2025/2026 ke 2026/2027) merupakan siklus penting di sekolah. Ikuti urutan langkah berikut:

### Langkah 1: Kunci Tahun Ajaran Lama (*Locking*)
1. Masuk sebagai `admin-sistem` atau `operator-sekolah`.
2. Buka menu **Data Master $\rightarrow$ Tahun Ajaran**.
3. Pilih tahun ajaran yang akan berakhir, ubah status menjadi **Terkunci (*Locked*)**.
   *Dampak:* Pembelajaran, jadwal, dan jurnal pada tahun tersebut menjadi *read-only* untuk keperluan arsip historis, mencegah perubahan nilai/jurnal di masa lalu.

### Langkah 2: Buat Tahun Ajaran & Semester Baru
1. Tambahkan Tahun Ajaran baru (contoh: `2026/2027`, tanggal mulai `2026-07-15`, tanggal selesai `2027-06-30`).
2. Tambahkan Semester I (Ganjil) dan Semester II (Genap) untuk tahun ajaran baru tersebut.
3. Tetapkan Tahun Ajaran Baru dan Semester I sebagai **Aktif**.

### Langkah 3: Kenaikan Kelas Siswa (*Grade Promotions*)
1. Buka menu **Kesiswaan $\rightarrow$ Daftar Siswa**.
2. Siswa kelas akhir (misal Kelas XII SMA / Kelas IX SMP) yang telah lulus dialihkan statusnya menjadi *Alumni*.
3. Siswa tingkat bawah dipromosikan ke tingkat berikutnya.

### Langkah 4: Pembentukan Rombongan Belajar (Rombel) Baru
1. Buka menu **Akademik $\rightarrow$ Rombel**.
2. Buat rombel baru untuk tahun ajaran 2026/2027 (contoh: `X-A`, `X-B`, `XI-MIPA-1`, dst.).
3. Masukkan siswa ke dalam rombel baru tersebut (*penempatan kelas*).

### Langkah 5: Penugasan Mengajar & Jadwal Pelajaran Baru
1. Buka menu **Administrasi Pembelajaran $\rightarrow$ Penugasan Guru**.
2. Tentukan penugasan guru per mata pelajaran dan rombel untuk Semester I tahun ajaran baru.
3. Susun jadwal pelajaran mingguan bebas bentrok (`422 Conflict Detection`).

---

## 6. Pemecahan Masalah & Mitigasi Kendala (*Troubleshooting & FAQ*)

### 6.1. Pekerja Antrean (*Queue Worker*) Berhenti atau Pekerjaan Gagal
- **Gejala:** Tugas asinkron seperti ekspor data atau pencadangan latar belakang tidak kunjung selesai.
- **Pemeriksaan:**
  Periksa status di tabel `job_execution_statuses` atau jalankan:
  ```bash
  php artisan queue:failed
  ```
- **Solusi:**
  1. Restart queue worker: `php artisan queue:restart`.
  2. Jika menggunakan Supervisor di Linux: `sudo supervisorctl restart all`.
  3. Coba jalankan kembali pekerjaan yang gagal: `php artisan queue:retry all`.

### 6.2. Muncul Error `419 Page Expired` / CSRF Token Mismatch
- **Penyebab:** Masa aktif sesi pengguna telah kedaluwarsa (`SESSION_LIFETIME=120` menit) atau cookie sesi terblokir browser.
- **Solusi:**
  1. Pengguna cukup me-refresh halaman peramban dan melakukan login ulang.
  2. Pastikan domain frontend dan backend serasi serta konfigurasi `SANCTUM_STATEFUL_DOMAINS` dan `SESSION_DOMAIN` di `.env` telah sesuai.

### 6.3. File Lock Cadangan Macet (`.backup.lock`)
- **Gejala:** Perintah backup mengeluarkan pesan: *“Another backup or restore process is currently running. Skipping backup run.”* padahal tidak ada proses backup yang aktif.
- **Penyebab:** Server mati mendadak saat backup sedang berjalan.
- **Solusi:**
  Sistem menggunakan `flock` berbasis kernel OS yang otomatis terbuka saat proses mati. Namun jika file sisa tetap memblokir:
  1. Pastikan tidak ada proses PHP `app:backup-run` di `top` / Task Manager.
  2. Hapus berkas lock manual: `rm -f storage/app/backups/.backup.lock`.

### 6.4. Kapasitas Disk Penuh (*Disk Space Exhaustion*)
- **Pencegahan:** Sistem secara default mempertahankan rotasi log selama 14 hari (`LOG_DAILY_DAYS=14`) dan rotasi backup 7 harian + 4 mingguan.
- **Tindakan Darurat:**
  1. Bersihkan direktori temporer: `rm -rf storage/app/backups/.tmp-*`.
  2. Bersihkan log usang yang berumur lebih dari 30 hari.
  3. Periksa ukuran direktori berkas lampiran: `du -sh storage/app/attachments/*`.
