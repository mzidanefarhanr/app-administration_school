# Rencana Migrasi & Prosedur Rollback Darurat
## Release Migration & Emergency Rollback Plan — Versi 1.0

Dokumen ini merupakan panduan standar operasional (*Standard Operating Procedure* / SOP) resmi untuk pelaksanaan migrasi sistem, pembaruan versi rilis produksi, serta tata cara penanganan mitigasi darurat pemulihan (*disaster recovery rollback*) bagi administrator dan staf IT sekolah.

---

## 1. Prinsip Dasar & Batasan Keamanan

1. **Prinsip Nol Kehilangan Data (*Zero Data Loss*):**
   Setiap proses migrasi produksi wajib diawali dengan pembuatan *snapshot* cadangan lengkap (basis data SQL dan seluruh media berkas lampiran privat) yang telah diverifikasi integritas hash SHA-256-nya sebelum satu baris kode atau skema diubah.
2. **Prinsip Jendela Pemeliharaan Terjadwal (*Maintenance Window*):**
   Migrasi produksi wajib dieksekusi di luar jam aktif operasional sekolah (disarankan pada hari libur atau malam hari antara pukul 20:00 s.d. 04:00 WIB).
3. **Prinsip Pemulihan Cepat (*Mean Time to Recovery* $\le$ 15 Menit):**
   Jika terjadi anomali kritis atau kegagalan migrasi yang tidak dapat diselesaikan dalam waktu 15 menit, tim IT wajib segera mengeksekusi Prosedur Rollback Darurat (Bagian 4).

---

## 2. Prasyarat & Checklist Pra-Migrasi (*Pre-Migration Readiness*)

Sebelum memulai proses migrasi, administrator wajib memastikan seluruh kriteria berikut terpenuhi:

- [ ] **Kapasitas Penyimpanan Server:** Sisa ruang disk kosong (*free disk space*) minimal **10 GB** untuk menampung arsip cadangan dan ekstraksi temporer.
- [ ] **Kesehatan Cadangan Terakhir:** Periksa status cadangan melalui `GET /api/backups/status` atau isi `storage/app/backups/monitoring.json`. Status harus bernilai `healthy`.
- [ ] **Kunci Enkripsi Cadangan:** Pastikan variabel `BACKUP_ENCRYPTION_KEY` telah diset (minimal 12 karakter) dan dicatat pada pengelola kata sandi aman.
- [ ] **Akses Hak Istimewa:** Akun pengguna OS memiliki hak akses eksekusi `sudo` / *Administrator*, akses penuh ke direktori `storage/` dan `bootstrap/cache/`, serta izin akses administratif DBMS MariaDB/MySQL.
- [ ] **Kompilasi Aset Frontend:** Pastikan bundel aset statis antarmuka Vue SPA telah dibangun (`npm run build`) dan terverifikasi bersih di direktori `vue-app/dist/` (bebas error linting).

---

## 3. Urutan Langkah Migrasi Produksi (*Golden Path*)

Ikuti 10 langkah terstruktur berikut secara berurutan:

### Langkah 1: Aktifkan Mode Pemeliharaan (*Maintenance Mode*)
Cegah transaksi data masuk dari pengguna saat proses pembaruan berjalan:
```bash
cd /var/www/app-administration_school/laravel-api

# Aktifkan mode maintenance dengan secret bypass untuk pengujian staf IT
php artisan down --secret="sekolah-migration-bypass-2026" --render="errors::503"
```
*Catatan:* Pengguna umum akan menerima halaman 503 Service Unavailable ramah pengguna, sementara administrator dapat mengakses sistem via URL `https://administrasi.sekolah.sch.id/sekolah-migration-bypass-2026`.

### Langkah 2: Buat Snapshot Cadangan Lengkap Pra-Migrasi
Eksekusi pencadangan komprehensif basis data dan berkas lampiran privat:
```bash
php artisan app:backup-run --non-interactive
```
*Verifikasi:*
- Pastikan berkas cadangan tercipta di `storage/app/backups/backup-production-*.zip`.
- Pastikan berkas pendamping `backup-production-*.zip.sha256` tercipta dan valid.
- Salin berkas ZIP dan SHA256 tersebut ke direktori cadangan aman di luar server (misal: harddisk eksternal atau storage cloud cadangan).

### Langkah 3: Perbarui Kode Sumber Aplikasi
Tarik commit rilis stabil yang telah disetujui:
```bash
git fetch --all --tags
git checkout tags/v1.0.0
# ATAU jika menggunakan branch main stabil:
# git pull origin main
```

### Langkah 4: Perbarui Dependensi PHP Backend
Pasang dependensi rilis produksi secara optimal:
```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

### Langkah 5: Eksekusi Migrasi Basis Data
Jalankan migrasi skema tabel baru dengan pengaman produksi:
```bash
php artisan migrate --force
```
*Verifikasi:* Output migrasi harus menunjukkan kode keluar `0` (sukses) tanpa pesan error SQL atau *foreign key constraint violation*.

### Langkah 6: Perbarui Aset Statis Frontend
Salin atau sinkronkan berkas hasil build antarmuka `vue-app/dist/` ke direktori publik web server yang melayani aplikasi SPA.

### Langkah 7: Bersihkan dan Bangun Ulang Cache Framework
Optimalkan performa waktu muat aplikasi di lingkungan produksi:
```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Bangun cache teroptimasi:
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Langkah 8: Eksekusi Pre-Release Smoke Test Suite ($\le 10$ Detik)
Lakukan pengujian kesehatan cepat melalui CLI:
```bash
# Uji konektivitas database & tabel vital:
php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB_OK'.PHP_EOL; echo 'Users: '.App\Models\User::count().PHP_EOL;"

# Uji status integritas monitoring backup:
php artisan tinker --execute="echo json_encode(app(App\Services\BackupMonitoringService::class)->getHealthStatus()).PHP_EOL;"
```

### Langkah 9: Restart Background Queue Worker & Task Scheduler
Pastikan pekerja antrean latar belakang memuat kode dan konfigurasi terbaru:
```bash
php artisan queue:restart
```
Jika menggunakan Supervisor / systemd di Linux:
```bash
sudo systemctl restart school-queue-worker
```

### Langkah 10: Nonaktifkan Mode Pemeliharaan (*Go-Live*)
Buka kembali akses sistem untuk seluruh pengguna sekolah:
```bash
php artisan up
```

Lakukan pemantauan aktif pada catatan log (`storage/logs/laravel-*.log`) selama 60 menit pasca-migrasi untuk memastikan tidak ada anomali atau lonjakan galat HTTP 500.

---

## 4. Prosedur Rollback Darurat (*Emergency Disaster Recovery*)

Prosedur ini wajib dieksekusi jika terjadi salah satu kondisi berikut:
1. Perintah `migrate --force` mengalami kegagalan fatal di tengah jalan yang mengakibatkan inkonsistensi skema.
2. Terjadi lonjakan galat fatal (HTTP 500 terus-menerus) pada halaman-halaman vital sekolah (kesiswaan, presensi, persuratan).
3. Kerusakan relasi data yang tidak dapat diperbaiki dalam durasi jendela pemeliharaan.

### Urutan Eksekusi Rollback Darurat:

#### Langkah R1: Pertahankan Mode Pemeliharaan
Pastikan aplikasi tetap tertutup bagi publik:
```bash
php artisan down --render="errors::503"
```

#### Langkah R2: Hentikan Pekerja Antrean Latar Belakang
Cegah pekerja antrean mengeksekusi pekerjaan dengan skema lama/rusak:
```bash
sudo systemctl stop school-queue-worker
# ATAU di Windows matikan task queue worker terkait
```

#### Langkah R3: Pulihkan Basis Data dari Snapshot Pra-Migrasi
Eksekusi pemulihan data dari dump SQL yang dibuat pada Langkah 2.
1. Ekstrak `database.sql` dari berkas cadangan pra-migrasi:
   ```bash
   unzip -p storage/app/backups/backup-production-<TIMESTAMP>.zip database.sql > /tmp/restore_database.sql
   ```
2. Verifikasi keutuhan SQL (pastikan bukan berkas kosong).
3. Impor kembali ke basis data produksi:
   ```bash
   mysql -u school_admin -p backdata_administrations < /tmp/restore_database.sql
   rm -f /tmp/restore_database.sql
   ```

#### Langkah R4: Kembalikan Berkas Lampiran Privat (Jika Terjadi Mutasi)
Jika terjadi perubahan atau penghapusan berkas lampiran selama migrasi, pulihkan direktori `storage/app/attachments/` dari berkas cadangan pra-migrasi:
```bash
unzip -o -q storage/app/backups/backup-production-<TIMESTAMP>.zip "attachments/*" -d /tmp/restore_attachments
cp -r /tmp/restore_attachments/attachments/* storage/app/attachments/
rm -rf /tmp/restore_attachments
```

#### Langkah R5: Kembalikan Kode Sumber ke Versi Stabil Sebelumnya
```bash
git checkout <COMMIT_STABIL_SEBELUMNYA>
composer install --no-dev --optimize-autoloader --no-interaction
```

#### Langkah R6: Bersihkan dan Bangun Ulang Cache
```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Langkah R7: Verifikasi Integritas Pasca-Rollback
Pastikan akun pengguna dan data operasional telah kembali normal:
```bash
php artisan tinker --execute="echo 'Users: '.App\Models\User::count().', Students: '.App\Models\Student::count().PHP_EOL;"
```

#### Langkah R8: Aktifkan Kembali Queue Worker & Buka Aplikasi
```bash
sudo systemctl start school-queue-worker
php artisan up
```

#### Langkah R9: Dokumentasi & Post-Mortem Incident Report
Catat penyebab kegagalan migrasi, nomor referensi `X-Correlation-ID` pada log, dan waktu pemulihan untuk dianalisis oleh tim pengembang sebelum penjadwalan ulang migrasi berikutnya.
