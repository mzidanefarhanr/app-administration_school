# Operasi, backup dan rilis

## Keputusan deployment
Hosting/local LAN belum dikonfirmasi. Pertahankan biaya rendah; jangan membeli layanan atau memasang integrasi tanpa scope. Untuk LAN, frontend dan API harus memiliki URL konsisten; jangan memakai IP developer hardcode. Produksi publik memerlukan HTTPS, CORS/cookie/Sanctum tepat, dan storage privat.
Versi PHP/Node/database dicatat dari lingkungan aktual. Constraint package tidak membuktikan versi runtime. Pilih upgrade framework/dependensi setelah audit kompatibilitas dan dukungan saat implementasi.

## Backup minimum sebelum data operasional
Backup mencakup database, lampiran, manifest schema/app version, checksum dan waktu. Konfigurasi/APP_KEY disimpan secara aman terpisah bila dibutuhkan pemulihan; jangan menaruh rahasia di manifest publik. Backup disk yang sama saja tidak melindungi kegagalan disk; pilih salinan terpisah sesuai deployment.
Kredensial dump jangan masuk argumen/log yang terlihat; gunakan mekanisme credential aman. Tidak ada download publik. Tampilkan status running/completed/failed, ukuran dan hasil pengecekan; “file dibuat” bukan bukti recoverability.
Retensi, jadwal, enkripsi, lokasi salinan, RPO (kehilangan data maksimal) dan RTO (waktu pemulihan) belum diputuskan. Jangan menetapkan angka seolah disetujui.

## Backup & Restore Operasional (Fase 02b/02c — DEC-17, DEC-18, DEC-19)
- **Lokasi Penyimpanan**: Direktori privat `storage/app/backups` di luar web root publik.
- **Standar Enkripsi**: ZIP AES-256 via native PHP `ZipArchive::EM_AES_256`.
- **Manajemen Passphrase**: Kunci enkripsi (`BACKUP_ENCRYPTION_KEY` min 12 karakter) disimpan di vault/password manager terpisah oleh administrator; tidak pernah dimasukkan ke dalam repositori Git, process argument OS, manifest, atau log.
- **Perintah Pencadangan Manual & Non-Interaktif**:
  ```bash
  # Mode interaktif (prompt secret):
  php artisan --env=uat app:backup-run
  # Mode otomatis / non-interaktif (membaca BACKUP_ENCRYPTION_KEY lokal):
  php artisan --env=uat app:backup-run --non-interactive
  ```
  Menghasilkan arsip `backup-<env>-<timestamp>.zip` dan berkas pendamping `backup-...zip.sha256`. Kredensial dump MySQL menggunakan `--defaults-extra-file` temporer dengan pembersihan seketika.
- **Perintah Rotasi Retensi (7 Harian + 4 Mingguan)**:
  ```bash
  # Pratinjau evaluasi tanpa menghapus:
  php artisan --env=uat app:backup-prune --dry-run
  # Eksekusi pembersihan nyata:
  php artisan --env=uat app:backup-prune
  ```
  Melindungi arsip sukses terakhir secara mutlak. Menghapus arsip `.zip` dan pendamping `.sha256` secara berpasangan. Melewati rotasi jika backup hari ini gagal atau backup sedang berjalan.
- **Perintah Restore Drill**:
  ```bash
  php artisan --env=uat app:restore-drill <path-to-archive.zip> --target-connection=restore_drill
  ```
  Target wajib masuk allowlist disposable (`school_restore_drill`, `school_restore_drill_uat`) dan dalam keadaan bersih/kosong. Menolak keras database operasional `backdata_administrations`, database sumber aktif, dan target yang sudah berisi data. Passphrase diminta interaktif via secret prompt.
- **Pemantauan Kesehatan Pasif & Freshness Detection**:
  Status tercatat di `storage/app/backups/monitoring.json` dan dapat dibaca melalui endpoint `GET /api/backups/status` (`backup.manage`). Menandai status `OVERDUE` secara dinamis jika lebih dari 28 jam sejak keberhasilan terakhir.
- **Prosedur Salinan Terpisah (Cold Storage)**:
  Operator/Admin mengunduh arsip backup terenkripsi secara berkala via endpoint berizin `GET /api/backups/{filename}/download` (`backup.manage`) atau menyalin berkas dari server ke harddisk eksternal offline. Kunci pemulihan disimpan di brankas terpisah dari media fisik arsip.
- **Prosedur Pemulihan Mesin Pengganti**:
  1. Siapkan server/lingkungan baru (PHP 8.2+, ekstensi zip dengan AES-256, DBMS MariaDB/MySQL).
  2. Salin kode aplikasi dari repositori Git dan pasang dependensi (`composer install`).
  3. Konfigurasikan `.env` baru dengan `APP_KEY` dan kredensial database target.
  4. Ambil arsip backup dari cold storage dan masukkan passphrase pemulihan via `app:restore-drill` atau impor mandiri.
  5. Verifikasi integritas multi-faktor (tabel, row count, foreign key, login admin, dan lampiran).
- **Runbook OS Task Scheduler & Cron (Panduan Referensi Produksi)**:
  - *Windows*: Buka Task Scheduler (`taskschd.msc`), buat task harian menjalankan `php.exe artisan schedule:run --env=production` di direktori `laravel-api`. Mesin harus hidup pada pukul 02:00 WIB. Jika mesin mati, status `OVERDUE` akan muncul hingga backup berikutnya dieksekusi.
  - *Linux*: `* * * * * cd /path/to/laravel-api && php artisan schedule:run >> /dev/null 2>&1`.
  - *Lock Stale Recovery*: Jika proses server terhenti mendadak (mati lampu) saat backup berjalan, file lock `storage/app/backups/.backup.lock` dapat dibuka kembali secara otomatis setelah proses OS mati karena lock berbasis handle file OS (`flock`).

## Lampiran surat privat (Fase 07a — DEC-32)
- Lampiran surat masuk disimpan di disk `local` pada `storage/app/attachments/letters/{letter_id}/{uuid}.{ext}` (config `letters.disk`, `letters.attachments_path`), di luar web root; tidak ada URL publik. Unduhan hanya lewat endpoint berizin `GET /api/letters/incoming/{letter}/attachments/{attachment}/download` dengan `Content-Disposition: attachment`, `nosniff`, CSP sandbox, dan `Cache-Control: no-store, private`.
- Batas operasional default: PDF/JPG/JPEG/PNG, 10 MB/file, 5 lampiran aktif per surat (dapat diubah via `LETTERS_MAX_FILE_KB`/`LETTERS_MAX_FILES_PER_LETTER`; tidak ada perubahan `.env` pada 07a). MIME dideteksi server; nama file disimpan acak, `sha256` dicatat.
- Hapus lampiran = **soft delete baris**; file fisik tetap disimpan sampai keputusan retensi/pemusnahan **DEC-13**. Tidak ada pembersihan otomatis atau antivirus pada 07a; pemantauan kapasitas disk tetap tanggung jawab operasional.
- Backup sudah memindai `config('backup.attachments_path')` (`storage/app/attachments`), sehingga lampiran surat ikut dalam pemindaian filesystem. Manifest `BackupService` kini menyebut lampiran surat operasional dan menandai **restore drill dengan lampiran surat NOT RUN**. Restore drill belum dijalankan pada 07a; jangan mengklaim recoverability lampiran sebelum drill dilakukan pada target disposable.
- Surat void tetap menyimpan lampiran dan tetap dapat diunduh oleh `letters.view`; tidak ada hard delete surat atau file pada 07a.

## Surat keluar, nomor resmi, approval/sign, dan disposisi (Fase 07b — DEC-33)
- **Nomor resmi** dibuat server saat transisi `approved→issued`; format default `{seq:03d}/{school_code}/{year}` (contoh `001/20512345/2026`). `school_code` dibaca dari `LETTERS_NUMBER_SCHOOL_CODE` (config `letters.numbering.school_code`) atau NPSN sekolah aktif; bila kosong, issue ditolak `422` sampai konfigurasi diisi. **Tidak ada perubahan `.env` operasional pada 07b**; nilai pada UAT terisolasi bersifat sintetis.
- Counter disimpan di `letter_number_counters` per `(scope=outgoing, period=YYYY)`, dikunci `lockForUpdate` dalam transaksi issue; `official_number` juga unique di database. Nomor tidak dipakai ulang setelah cancel, counter tidak mundur, dan issue ulang/replay ditolak `409` tanpa nomor baru.
- **Pemisahan tugas**: approve/reject membutuhkan `letters.approve`, issue/sign membutuhkan `letters.sign`; pembuat tidak dapat menyetujui/menandatangani suratnya sendiri (`403`). Admin teknis tidak otomatis berwenang; pemberian ability dilakukan lewat pengaturan peran, bukan perubahan kode.
- **Lifecycle**: `draft→review→approved→issued`; reject kembali ke `draft`; `cancelled` final dengan `status=void`. Tidak ada un-cancel, edit nomor, issue massal, atau delete surat. Nomor yang sudah terbit tetap tercatat pada surat cancelled.
- **Disposisi**: hanya surat masuk dan pegawai aktif; penerima tanpa akun tetap dapat didisposisi dan status dicatat operator. Disposisi terbuka (`pending`/`in_progress`) memblokir void surat (`409`); overdue dihitung saat baca. Tidak ada notifikasi email/WhatsApp pada 07b.
- **Lampiran** outgoing memakai mekanisme privat 07a dan hanya dapat diubah pada status draft; file tetap disimpan setelah soft delete sampai DEC-13. Tidak ada PDF/template/snapshot (07c/DEC-11).
- **UAT**: server 14025/18025 tetap berjalan sampai penerimaan pemilik; setelah diterima dihentikan sesuai DEC-27 (data `.uat-phase07` dipertahankan, perintah restart ada di `PHASE-07B-MANUAL-REVIEW.md`). Backup/restore drill dengan tabel dan nomor 07b belum dijalankan; jangan mengklaim recoverability sebelum drill pada target disposable.

## Restore drill
1. Pilih backup fixture sintetis dan target terisolasi; verifikasi target bukan DB/storage aktif.
2. Periksa manifest/checksum dan kompatibilitas schema; dokumentasikan app commit yang diperlukan.
3. Restore database serta lampiran; tangani APP_KEY dan konfigurasi rahasia secara aman.
4. Verifikasi jumlah/relasi sampel, izin, login uji, download lampiran, PDF dan audit.
5. Catat durasi, hasil, missing file dan keterbatasan. Hapus target uji sesuai scope.
Restore ke instalasi aktif memerlukan instruksi pemilik untuk target dan window, bukan dilakukan untuk membuktikan tombol restore.

## Runbook rilis (rencana)
Catat kandidat SHA, test/UAT, known issues dan backup tervalidasi; siapkan build Vue dengan environment tujuan; document root backend hanya `laravel-api/public`. Jangan mengunggah `.env`, tests, dump dan source rahasia sebagai public assets. Jalankan migration baru yang telah direview, konfigurasi cache sesuai env, worker/scheduler bila dibutuhkan. Smoke test login, role, list, mutasi uji berizin, download dan status job.
Setelah rilis, pantau error tanpa data pribadi. Rollback kode dan rollback schema tidak selalu kompatibel; tentukan strategi sebelum rilis. Jangan drop kolom lama sebelum masa kompatibilitas selesai.

## Jadwal dan job
Scheduler baseline kosong; queue sync. Jangan menyatakan backup otomatis aktif sampai scheduler/worker benar-benar terpasang dan job diuji. Pemantauan harus membedakan job stuck, retry, gagal dan sukses. Catat perubahan kredensial, akses backup dan insiden sesuai kejadian nyata.
