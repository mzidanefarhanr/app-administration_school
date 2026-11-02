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

## Kop, penandatangan, aset, dan dokumen PDF (Fase 07c — DEC-34)
- **Settings per sekolah/tahun**: `letter_settings` 1:1 dengan `school_profiles`; resolusi memakai `academic_settings.active_school_year_id` → `school_profiles` → settings. Tanpa tahun aktif/profil/settings, readiness `false` dan submit/issue typed ditolak `422`; general letter 07b tidak memerlukan settings.
- **Aset privat**: logo/signature/stamp disimpan di `storage/app/attachments/letter-settings/{school_profile_id}/` pada disk `local`; re-encode PNG, maksimum 2 MB dan 2000×2000; hanya PNG/JPG yang diterima. File lama dihapus setelah update sukses; jika transaksi gagal, file baru dibuang best-effort. Tidak ada URL publik; akses lewat endpoint berizin (`logo` = `letters.view`, `signature`/`stamp` = `letters.sign`).
- **Hak tulis**: kop/logo `letters.manage`; penandatangan/signature/stamp `letters.sign`; update memakai `expected_version` dan `409` tanpa mutasi. Signature wajib untuk readiness; logo/stempel opsional. Readiness juga memerlukan tahun/profil/kop (nama sekolah + alamat) dan penandatangan (nama + jabatan).
- **Template**: Blade kode di `resources/views/letters/pdf/` dengan registry `config/letters.php` (`code`, `version`, `view`, label). Tidak ada CRUD/editor template atau HTML pengguna. Versi template dinaikkan manual; dokumen menyimpan `template_code`/`template_version` saat issue.
- **PDF dan snapshot**: dibuat saat `approved→issued` di dalam transaksi setelah nomor atomik; file ditulis ke `storage/app/attachments/letter-documents/{letter_id}/{uuid}.pdf`; snapshot minimal PII disimpan di `letter_documents` (immutable). Kegagalan validasi/settings/subjek/render/storage/insert → rollback, surat tetap approved, nomor tidak dikonsumsi, file dibersihkan best-effort. Tidak ada reissue/regenerate; koreksi dilakukan dengan cancel + surat baru.
- **Preview/download**: draft/review/approved typed → preview watermarked `PREVIEW — NOT ISSUED` tanpa nomor/counter/penyimpanan; issued/cancelled → unduh PDF tersimpan; general/legacy 07b → `404` tanpa backfill; file hilang → `404` tanpa regenerasi. Nama file preview `preview-letter-{id}.pdf`; unduhan memakai nomor resmi tersanitasi.
- **Header PDF**: `Content-Type: application/pdf`, `X-Document-Filename`, `Access-Control-Expose-Headers`, `nosniff`, `Cache-Control: private, no-store`, CSP sandbox. **Tidak memakai `Content-Disposition`** karena Chrome memblokir `fetch` lintas origin berkredensial untuk respons yang memuat header tersebut; UI memakai `X-Document-Filename`. Unduhan tetap melalui endpoint berizin; tidak ada URL storage publik.
- **Backup**: path settings/dokumen berada di bawah `storage/app/attachments`, sehingga tercakup `config/backup.php`/`BackupService` tanpa perubahan kode backup. Restore drill dengan aset/PDF 07c dan manifest yang memuatnya **belum dijalankan**.
- **Orphan file**: jika proses crash setelah file ditulis tetapi sebelum commit, file dapat menjadi orphan; tidak ada auto-prune pada 07c. Risiko dicatat; pembersihan manual/retensi menunggu DEC-13.
- **Dependency**: hanya `dompdf/dompdf v2.0.8` + empat transitive (`masterminds/html5`, `phenx/php-font-lib`, `phenx/php-svg-lib`, `sabberworm/php-css-parser`); tidak ada package lain berubah/dihapus. Tidak ada binary sistem, layanan eksternal, atau perubahan `.env` utama.
- **UAT**: `.uat-phase07` (API 18025/SPA 14025) berjalan sampai penerimaan pemilik; setelah itu dihentikan sesuai DEC-27 dengan verifikasi PID/command line, data dipertahankan, Apache/MariaDB tidak disentuh. Perintah restart ada di `PHASE-07C-MANUAL-REVIEW.md`.
- **Retensi**: file/snapshot dipertahankan setelah cancel/void; kebijakan retensi/pemusnahan tetap **DEC-13 OPEN**.

## Lampiran izin privat dan UAT (Fase 08a — DEC-35)
- **Penyimpanan lampiran izin**: disk `local` pada `storage/app/attachments/leaves/{leave_request_id}/{uuid}.{ext}` (config `leaves.attachment.disk`/`path`), di luar web root; tidak ada URL publik. Unduhan hanya lewat `GET /api/leaves/{leave}/attachments/{attachment}/download` dengan izin policy view, `X-Document-Filename`, `nosniff`, CSP sandbox, dan `Cache-Control: private, no-store`; **tanpa `Content-Disposition`** mengikuti pola PDF 07c.
- **Batas**: PDF/JPG/JPEG/PNG, maksimum 5 MB per file dan 3 lampiran per pengajuan (config `config/leaves.php`, tanpa perubahan `.env` pada 08a). Validasi memakai `PrivateUploadValidator` bersama dengan surat (nama satu ekstensi, allowlist, MIME server, kecocokan ekstensi–MIME, `getimagesize`). Nama file UUID; nama asli disanitasi; SHA-256 dicatat.
- **Hapus lampiran** = soft delete baris; file fisik dipertahankan sampai keputusan retensi/pemusnahan **DEC-13**. Tidak ada auto-prune/antivirus pada 08a; pemantauan kapasitas disk tetap tanggung jawab operasional.
- **Backup**: path berada di bawah `storage/app/attachments`, sehingga pemindaian `config/backup.php`/`BackupService` mencakup lampiran izin tanpa perubahan kode backup. Restore drill dengan tabel/lampiran izin **belum dijalankan**; jangan mengklaim recoverability sebelum drill pada target disposable.
- **UAT terisolasi**: `.uat-phase08` (SQLite/storage terpisah; launcher `scripts/phase08-uat.php`; API `127.0.0.1:18026`, SPA `127.0.0.1:14026`, cookie `school_phase08_session`). Launcher menolak target non-SQLite/URL dan tidak membuka koneksi MariaDB. Fixture `prepare-leaves` idempoten; jangan me-reset `.uat-phase08`.
- **Siklus server**: server UAT dipertahankan sampai penerimaan pemilik, lalu dihentikan sesuai DEC-27 setelah verifikasi PID/command line, port 18026/14026 dipastikan bebas, dan `.uat-phase08` dipertahankan; Apache/MariaDB/service tidak disentuh. Perintah restart ada di `PHASE-08A-MANUAL-REVIEW.md`.
- **Batas 08a**: tidak ada approve/reject/rekap (08b), notifikasi email/WhatsApp, PDF surat izin, absensi otomatis, saldo cuti, impor/ekspor CSV izin, atau perubahan dependency/service/`.env`. Database operasional/MariaDB dan merge/deploy tidak disentuh.

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
