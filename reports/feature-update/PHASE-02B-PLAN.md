# Rencana Kerja Teknis Subfase 02b — Backup Manual dan Restore Drill

- Tanggal WIB: 2026-10-19
- Mode: **Perencanaan teknis murni (read-only)**. Tidak ada implementasi kode, eksekusi backup aktif, manipulasi data, atau restore drill yang dijalankan pada langkah ini.
- Status subfase: **PLANNING / READY FOR OWNER REVIEW**.
- Prasyarat: Subfase 02a telah **DITERIMA PENUH OLEH PEMILIK** (checklist #1–#9 APPROVED 2026-10-19 WIB mengacu baseline `cf94cae`).
- Dokumen acuan: `AGENTS.md`, `ROADMAP.md`, `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`, `docs/OPERATIONS.md`, `docs/SECURITY-DATA.md`, register keputusan `DEC-02`, `DEC-09`, `DEC-17`.

---

## 1. Tujuan dan Ruang Lingkup Subfase 02b

Subfase 02b bertujuan mewujudkan mekanisme pencadangan (backup) dan pemulihan (restore drill) yang mandiri, terenkripsi, terisolasi, dan terverifikasi secara nyata sebelum sistem mulai memproses data operasional sekolah:

1. **Pencadangan Mandiri & Terenkripsi**:
   - Menghasilkan satu berkas arsip terpadu (ZIP) yang memuat dump database skema + data, berkas lampiran privat, dan berkas `manifest.json`.
   - Mengenkripsi berkas arsip dengan standar industri **ZIP AES-256** menggunakan ekstensi native PHP `ZipArchive`.
   - Menyimpan arsip di direktori privat server (`storage/app/backups`) di luar public web root.
2. **Integritas Manifest & Anti-Kebocoran**:
   - `manifest.json` memuat ringkasan metadata, jumlah baris tabel, versi migrasi, dan checksum **SHA-256** untuk setiap komponen.
   - Kredensial database tidak pernah bocor ke process listing OS (menggunakan `--defaults-extra-file` sementara).
   - Kunci enkripsi arsip disimpan terpisah dari repositori dan arsip itu sendiri.
3. **Atomic Publishing & Fail-Safe Cleanup**:
   - Proses pembuatan backup berjalan di direktori temporer terisolasi. Jika terjadi kegagalan (disk penuh, proses terputus, dump gagal), direktori temporer dibersihkan 100% tanpa meninggalkan arsip parsial/korup.
4. **Unduhan Berizin `backup.manage`**:
   - Endpoint API terproteksi `GET /api/backups/{filename}/download` dengan penegakan kemampuan bernama `backup.manage`.
   - Sanitasi path ketat untuk mencegah kerentanan Directory Traversal.
5. **Uji Pemulihan Terisolasi (Restore Drill)**:
   - Perintah Artisan `app:restore-drill` untuk memvalidasi dan memulihkan arsip ke target database dan storage disposable (`school_restore_drill`).
   - **Fail-safe mutlak**: Menolak keras target database operasional (`backdata_administrations`) atau target yang sama dengan sumber data aktif.
   - **Pembuktian multi-faktor**: Pemulihan diverifikasi tidak hanya lewat checksum, melainkan meliputi integritas skema tabel, jumlah baris data, foreign key constraint check, dan keberadaan berkas lampiran.

---

## 2. Inventarisasi Prasyarat Lingkungan Aktual

Berdasarkan pemeriksaan langsung pada server lokal tanpa menyentuh data operasional:
- **Sistem Operasi**: Windows 64-bit (Win32), XAMPP stack.
- **PHP**: Versi 8.2.12 (CLI).
- **Modul PHP Relevan**:
  - `zip`: Ekstensi native PHP ZipArchive aktif; konstanta `ZipArchive::EM_AES_256` terbukti didukung.
  - `openssl`: Aktif (alternatif enkripsi/dekripsi stream).
  - `pdo_mysql` & `pdo_sqlite`: Aktif.
  - `hash`: Aktif (`hash_file('sha256', ...)`).
- **Utilitas Database CLI**:
  - `C:\xampp\mysql\bin\mysqldump.exe`: Ver 10.19 Distrib 10.4.32-MariaDB.
  - `C:\xampp\mysql\bin\mysql.exe`: Ver 15.1 Distrib 10.4.32-MariaDB.
- **Direktori Penyimpanan (Disks)**:
  - Private storage: `storage/app` (disk `local`).
  - Target backup: `storage/app/backups` (di luar public web root).
  - Target lampiran privat: `storage/app/attachments`.
  - Testing disk: `storage/framework/testing/disks/testing`.
- **Database UAT**: `school_access_uat` (port 3306, disposable).
- **Database Drill Target**: `school_restore_drill` (disposable, disiapkan khusus untuk verifikasi restore).
- **Database Operasional**: `backdata_administrations` (TERLINDUNGI, TIDAK BOLEH DISENTUH).

---

## 3. Rincian Desain Teknis Komponen Subfase 02b

### A. Perintah Backup (`php artisan app:backup-run`)
1. **Alur Kerja Eksekusi**:
   - Inisialisasi ID proses backup acak dan folder temporer: `storage/app/backups/.tmp-<uuid>/`.
   - Verifikasi ruang disk tersedia sebelum memulai dump.
   - **Tahap 1: Dump Database**:
     - Untuk MySQL/MariaDB: Membuat file konfigurasi otentikasi sementara yang memuat kredensial koneksi, menjalankan `mysqldump` dengan `--defaults-extra-file`, `--single-transaction`, `--quick`, `--skip-comments`, `--routines`, `--triggers`. File konfigurasi sementara langsung dihapus segera setelah dump selesai.
     - Untuk SQLite (lingkungan automated test): Menyalin database terisolasi atau mengekspor DDL + INSERT statements secara konsisten dalam transaksi read-only.
     - Menghitung checksum SHA-256 dari berkas `database.sql`.
   - **Tahap 2: Pengarsipan Lampiran Privat**:
     - Memindai berkas pada `storage/app/attachments`.
     - Menghitung checksum SHA-256 per berkas lampiran.
   - **Tahap 3: Pembuatan `manifest.json`**:
     - Struktur JSON memuat format version, timestamp (UTC & WIB), nama environment, DBMS version, daftar tabel & count baris, hash dump SQL, dan metadata berkas lampiran.
   - **Tahap 4: Kompresi & Enkripsi ZIP AES-256**:
     - Mengemas `manifest.json`, `database.sql`, dan folder `attachments/` ke dalam arsip ZIP.
     - Mengaktifkan enkripsi AES-256 per entri berkas menggunakan password dari konfigurasi `BACKUP_ENCRYPTION_KEY` (atau input secret).
   - **Tahap 5: Atomic Publishing**:
     - Menghitung checksum SHA-256 berkas ZIP final.
     - Memindahkan (atomic rename) berkas ZIP dari folder temporer ke berkas final: `storage/app/backups/backup-<env>-<Ymd_His>.zip`.
     - Membersihkan folder temporer.
     - Mencatat aktivitas backup ke log sistem.

2. **Penanganan Kegagalan**:
   - Jika terjadi exception atau kegagalan pada tahap 1–4, seluruh isi folder temporer `.tmp-<uuid>` dihapus seketika via `try ... finally`.
   - Tidak ada berkas parsial yang tertinggal pada direktori `backups`.

### B. Kemampuan Bernama & Download API (`backup.manage`)
1. **Kemampuan Bernama**:
   - Tambahkan kemampuan bernama `backup.manage` pada `AbilityCatalog.php`.
   - Hak default awal (DEC-03): Hanya diberikan kepada peran **Admin Sistem** (`admin-sistem`).
2. **Endpoint Controller (`BackupController`)**:
   - `GET /api/backups`: Melihat daftar arsip backup (nama berkas, ukuran, waktu pembuatan, checksum). Dilindungi `can:backup.manage`.
   - `GET /api/backups/{filename}/download`: Mengunduh berkas backup. Dilindungi `can:backup.manage`.
3. **Perlindungan Keamanan Unduhan**:
   - **Sanitasi Path Traversal**: `basename($filename)` divalidasi dengan regex strict `/^backup-[a-zA-Z0-9_-]+\.zip$/`. Karakter `..`, `/`, `\`, `%00` ditolak dengan HTTP 400/404.
   - **Penyajian Aman**: Disajikan melalui `response()->download(...)` dengan stream privat, bukan direct web link.

### C. Perintah Uji Pemulihan (`php artisan app:restore-drill`)
1. **Validasi & Fail-Safe Pengaman Target**:
   - Argumen: Path berkas arsip backup, opsi `--connection=drill`, opsi `--password=...`.
   - **Fail-Safe Database Operasional**:
     ```php
     $targetDb = config("database.connections.{$connection}.database");
     $operationalDb = env('DB_DATABASE'); // backdata_administrations

     if (in_array(strtolower($targetDb), [strtolower($operationalDb), 'backdata_administrations'], true)) {
         throw new \RuntimeException("CRITICAL: Restore drill to operational database is strictly prohibited!");
     }
     if (strtolower($targetDb) === strtolower(config('database.connections.mysql.database'))) {
         throw new \RuntimeException("CRITICAL: Restore drill target cannot be the active source database!");
     }
     ```
   - Target database wajib berstatus disposable (misal `school_restore_drill` atau SQLite in-memory untuk automated testing).
   - Konfirmasi interaktif eksplisit sebelum mengeksekusi penimpaan data pada database target.

2. **Validasi Arsip & Ekstraksi Aman**:
   - Membuka arsip ZIP dengan password yang diberikan.
   - **Anti-Zip-Slip Guard**: Memeriksa nama setiap entri dalam ZIP. Jika entri mengandung `..`, karakter path absolut, atau mengarah ke luar folder ekstraksi temporer, proses drill langsung digagalkan seketika.
   - Membaca `manifest.json` dan memvalidasi integritas checksum SHA-256 terhadap berkas `database.sql` dan lampiran yang diekstraksi. Jika checksum tidak cocok (arsip korup/termodifikasi), drill dibatalkan dengan pesan error detail.

3. **Verifikasi Pemulihan Multi-Faktor**:
   - Menjalankan impor `database.sql` ke database target drill.
   - **Asersi Pembuktian**:
     - Asersi 1: Seluruh tabel yang terdaftar pada `manifest.json` ada pada database target.
     - Asersi 2: Jumlah baris data per tabel cocok dengan metadata manifest.
     - Asersi 3: Foreign key constraint check valid (tidak ada relasi orphan/rusak).
     - Asersi 4: Pengguna administrator dapat ditemukan dan hash password-nya terverifikasi.
     - Asersi 5: Berkas lampiran diekstraksi ke direktori uji disposable dan checksum SHA-256 cocok 100%.
   - Menampilkan ringkasan status drill: **PASS / FAIL** beserta metrik pemulihan.
   - Membersihkan seluruh berkas ekstraksi temporer pasca drill.

---

## 4. Strategi Pengujian Terisolasi (Automated & UAT)

1. **Uji Otomatis Unit/Feature (`php artisan test`)**:
   - `laravel-api/tests/Feature/Backup/BackupRunTest.php`:
     - Uji pembuatan backup menghasilkan berkas ZIP terenkripsi, berkas dump SQL, dan `manifest.json` yang valid.
     - Uji pengecualian kredensial database dari process listing / manifest.
     - Uji penolakan download unauthenticated (401) dan tanpa kemampuan `backup.manage` (403).
     - Uji penolakan path traversal pada nama file download (`../../`).
     - Uji pembersihan folder temporer saat proses backup mengalami kegagalan.
   - `laravel-api/tests/Feature/Backup/RestoreDrillTest.php`:
     - Uji restore drill berhasil memulihkan database dan file lampiran ke database uji terisolasi.
     - Uji fail-safe: Penolakan target `backdata_administrations` dan penolakan target aktif.
     - Uji penolakan arsip korup (checksum mismatch).
     - Uji penolakan password enkripsi yang salah.
     - Uji penolakan arsip berbahaya dengan path traversal (Zip Slip).
2. **Pemeriksaan Kualitas Kode**:
   - `./vendor/bin/pint --test` pada seluruh controller, command, dan service baru.
   - Tidak ada modifikasi kode pada frontend yang tidak perlu (kecuali halaman monitoring/backup bila masuk scope di masa depan).

---

## 5. Prosedur Operasional Salinan Terpisah & Pengelolaan Kunci (DEC-02 & DEC-17)

1. **Penyimpanan Kunci Enkripsi Terpisah**:
   - Nilai `BACKUP_ENCRYPTION_KEY` tidak boleh disimpan di server berdampingan dengan arsip backup.
   - Kunci enkripsi didokumentasikan di password manager / brankas kunci terpisah oleh Kepala Sekolah / Administrator Sistem.
2. **Prosedur Salinan Terpisah (Off-site / Cold Storage)**:
   - Operator Sekolah / Admin mengunduh berkas backup terenkripsi secara berkala via endpoint berizin atau menyalin via media eksternal (harddisk eksternal offline).
   - Dengan enkripsi AES-256, salinan arsip aman disimpan pada media sekunder tanpa risiko kebocoran isi data siswa atau kredensial.

---

## 6. Keputusan yang Diperlukan dari Pemilik Sebelum Implementasi 02b

Sebelum pengkodean Subfase 02b dimulai, pemilik diharapkan memberikan konfirmasi atas 2 poin praktis:

1. **Nama Database Disposable untuk Restore Drill Lokal**:
   - *Usulan*: `school_restore_drill_uat` pada MySQL lokal (port 3306), berdampingan dengan `school_access_uat`. Database ini bersifat disposable dan dapat di-drop/recreate kapan saja oleh script restore drill tanpa memengaruhi database lain.
2. **Peran Pengakses Kemampuan `backup.manage`**:
   - *Usulan*: Kemampuan `backup.manage` (menjalankan backup, melihat daftar arsip, dan mengunduh berkas) secara default **hanya** diberikan kepada peran **System Administrator** (`admin-sistem`). Peran Kepala Sekolah dan Operator Sekolah tidak memiliki akses unduh backup secara default, namun Admin Sistem dapat memberikannya via UI `/master/roles` jika diinginkan.
