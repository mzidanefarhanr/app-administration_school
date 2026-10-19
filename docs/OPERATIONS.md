# Operasi, backup dan rilis

## Keputusan deployment
Hosting/local LAN belum dikonfirmasi. Pertahankan biaya rendah; jangan membeli layanan atau memasang integrasi tanpa scope. Untuk LAN, frontend dan API harus memiliki URL konsisten; jangan memakai IP developer hardcode. Produksi publik memerlukan HTTPS, CORS/cookie/Sanctum tepat, dan storage privat.
Versi PHP/Node/database dicatat dari lingkungan aktual. Constraint package tidak membuktikan versi runtime. Pilih upgrade framework/dependensi setelah audit kompatibilitas dan dukungan saat implementasi.

## Backup minimum sebelum data operasional
Backup mencakup database, lampiran, manifest schema/app version, checksum dan waktu. Konfigurasi/APP_KEY disimpan secara aman terpisah bila dibutuhkan pemulihan; jangan menaruh rahasia di manifest publik. Backup disk yang sama saja tidak melindungi kegagalan disk; pilih salinan terpisah sesuai deployment.
Kredensial dump jangan masuk argumen/log yang terlihat; gunakan mekanisme credential aman. Tidak ada download publik. Tampilkan status running/completed/failed, ukuran dan hasil pengecekan; “file dibuat” bukan bukti recoverability.
Retensi, jadwal, enkripsi, lokasi salinan, RPO (kehilangan data maksimal) dan RTO (waktu pemulihan) belum diputuskan. Jangan menetapkan angka seolah disetujui.

## Backup & Restore Operasional (Fase 02b — DEC-17, DEC-18)
- **Lokasi Penyimpanan**: Direktori privat `storage/app/backups` di luar web root publik.
- **Standar Enkripsi**: ZIP AES-256 via native PHP `ZipArchive::EM_AES_256`.
- **Manajemen Passphrase**: Kunci enkripsi (`BACKUP_ENCRYPTION_KEY` min 12 karakter) disimpan di vault/password manager terpisah oleh administrator; tidak pernah dimasukkan ke dalam repositori Git, process argument OS, manifest, atau log.
- **Perintah Pencadangan**:
  ```bash
  php artisan app:backup-run
  ```
  Menghasilkan arsip `backup-<env>-<timestamp>.zip` dan berkas pendamping `backup-...zip.sha256`. Kredensial dump MySQL menggunakan `--defaults-extra-file` temporer dengan pembersihan seketika.
- **Perintah Restore Drill**:
  ```bash
  php artisan app:restore-drill <path-to-archive.zip> --target-connection=restore_drill --force
  ```
  Target wajib masuk allowlist disposable (`school_restore_drill`, `school_restore_drill_uat`). Menolak keras database operasional `backdata_administrations` dan database sumber aktif.
- **Prosedur Salinan Terpisah (Cold Storage)**:
  Operator/Admin mengunduh arsip backup terenkripsi secara berkala via endpoint berizin `GET /api/backups/{filename}/download` (`backup.manage`) atau menyalin berkas dari server ke harddisk eksternal offline. Kunci pemulihan disimpan di brankas terpisah dari media fisik arsip.
- **Prosedur Pemulihan Mesin Pengganti**:
  1. Siapkan server/lingkungan baru (PHP 8.2+, ekstensi zip dengan AES-256, DBMS MariaDB/MySQL).
  2. Salin kode aplikasi dari repositori Git dan pasang dependensi (`composer install`).
  3. Konfigurasikan `.env` baru dengan `APP_KEY` dan kredensial database target.
  4. Ambil arsip backup dari cold storage dan masukkan passphrase pemulihan via `app:restore-drill` atau impor mandiri.
  5. Verifikasi integritas multi-faktor (tabel, row count, foreign key, login admin, dan lampiran).

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
