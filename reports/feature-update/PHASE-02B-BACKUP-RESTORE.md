# Laporan Subfase 02b — Backup Manual Terenkripsi dan Restore Drill Terisolasi

- Tanggal WIB: 2026-10-19
- Branch kerja: `main`
- Status teknis: **IMPLEMENTED & FULLY VERIFIED**
- Status review pemilik: **PENDING** (menunggu pemeriksaan manual checklist 02b di `PHASE-02B-MANUAL-REVIEW.md`)
- Prasyarat: Subfase 02a telah **DITERIMA LENGKAP OLEH PEMILIK**; keputusan DEC-18 APPROVED
- Database & storage:
  - Database pengujian otomatis: **SQLite in-memory `:memory:` terisolasi** dan disk storage `testing`.
  - Database integrasi nyata lokal: MariaDB 10.4.32 port 3306 sumber `school_access_uat` (sintetis) dan target disposable `school_restore_drill_uat`.
  - Database operasional: `backdata_administrations` (TERLINDUNGI & TIDAK PERNAH DISENTUH).

---

## 1. Ringkasan Eksekutif

Subfase 02b mengimplementasikan pencadangan (backup) mandiri terenkripsi dan uji pemulihan (restore drill) terisolasi sesuai keputusan pemilik **DEC-17** dan **DEC-18**:

1. **Pencadangan Mandiri & Terenkripsi**:
   - Perintah `php artisan app:backup-run` mengekspor database skema + data dan mengarsipkan direktori lampiran privat `storage/app/attachments`.
   - Menggunakan standar enkripsi **ZIP AES-256** native PHP via `ZipArchive::EM_AES_256`.
   - Kredensial database diisolasi dari process list OS menggunakan berkas konfigurasi sementara `--defaults-extra-file` dengan jaminan pembersihan seketika di blok `finally`.
   - Passphrase rahasia bersumber dari environment `BACKUP_ENCRYPTION_KEY` atau secret prompt interaktif CLI (min 12 karakter); tidak pernah masuk ke log, manifest, atau repositori Git.
2. **Manifest Integritas & Companion Checksum**:
   - `manifest.json` di dalam arsip memuat metadata aplikasi, timestamp UTC & WIB, versi DBMS, ringkasan tabel dan jumlah baris data, status migrasi terakhir, checksum SHA-256 berkas dump SQL, dan metadata berkas lampiran.
   - Checksum SHA-256 arsip final disimpan di berkas pendamping di luar arsip: `backup-<env>-<timestamp>.zip.sha256`.
3. **Atomic Publishing & Fail-Safe Cleanup**:
   - Seluruh tahapan backup dibangun di dalam folder temporer unik `storage/app/backups/.tmp-<uuid>/`.
   - Jika proses gagal pada tahap mana pun, direktori temporer dibersihkan seketika tanpa meninggalkan berkas parsial/korup.
   - Publikasi arsip ke `storage/app/backups/backup-<env>-<timestamp>.zip` hanya dilakukan setelah seluruh proses kompresi, enkripsi, dan verifikasi selesai 100%.
4. **Unduhan Berizin `backup.manage`**:
   - Endpoint `GET /api/backups` dan `GET /api/backups/{filename}/download` dilindungi autentikasi dan kemampuan bernama `backup.manage`.
   - Kemampuan `backup.manage` terdaftar di `AbilityCatalog.php` dan dipasangkan secara default ke peran **System Administrator** (`admin-sistem`) via migration `2029_07_22_000001_add_backup_manage_ability.php` tanpa mengubah kemampuan peran lain.
   - Perlindungan Directory Traversal ketat: nama file divalidasi dengan regex strict `/^backup-[a-zA-Z0-9._-]+\.zip$/`; karakter `..`, `/`, `\`, `%00` ditolak dengan HTTP 400. Berkas disajikan via stream privat tanpa public URL.
5. **Uji Pemulihan Terisolasi (Restore Drill)**:
   - Perintah `php artisan app:restore-drill {archive}` memulihkan dan memvalidasi integritas arsip ke target database dan storage disposable.
   - **Fail-safe keras**: Menolak keras database operasional `backdata_administrations`, database aktif sumber, dan database di luar allowlist disposable (`school_restore_drill`, `school_restore_drill_uat`).
   - **Anti-Zip-Slip Guard**: Memeriksa nama setiap entri dalam ZIP dan menolak berkas dengan path traversal.
   - **Audit SQL Dump**: Memindai isi dump dan menggugurkan proses bila terdapat perintah berbahaya pengalihan koneksi (`USE ...` atau `CREATE DATABASE ...`).
   - **Isolasi Lampiran**: Berkas lampiran diekstraksi ke direktori uji disposable `storage/app/restore-drill-attachments/drill-<uuid>`, bukan ke direktori lampiran aktif.
   - **Pembuktian Multi-Faktor**: Pemulihan diverifikasi tidak hanya lewat checksum, melainkan meliputi:
     1. Seluruh tabel ada pada database target.
     2. Jumlah baris data per tabel cocok persis dengan manifest.
     3. Foreign key integrity valid.
     4. Akun administrator dapat ditemukan dan aktif (tanpa mencetak password/hash).
     5. Berkas lampiran diekstraksi dan checksum SHA-256 cocok.

---

## 2. Inventarisasi Komponen & Jalur Operasi

| Komponen | Tipe | Lokasi File | Fungsi & Proteksi |
|---|---|---|---|
| `config/backup.php` | Konfigurasi | `laravel-api/config/backup.php` | Path storage privat, allowlist target disposable, forbidden databases, path binary DB |
| `BackupService` | Service | `laravel-api/app/Services/BackupService.php` | Logika dump MySQL & SQLite, ZipArchive AES-256, manifest, companion sha256, atomic publishing |
| `RestoreDrillService` | Service | `laravel-api/app/Services/RestoreDrillService.php` | Validasi fail-safe target, anti-Zip-Slip, audit SQL dump, multi-factor verification |
| `BackupRunCommand` | CLI Command | `laravel-api/app/Console/Commands/BackupRunCommand.php` | `php artisan app:backup-run` dengan secret prompt / env passphrase |
| `RestoreDrillCommand` | CLI Command | `laravel-api/app/Console/Commands/RestoreDrillCommand.php` | `php artisan app:restore-drill` dengan fail-safe koneksi target |
| `BackupController` | Controller API | `laravel-api/app/Http/Controllers/BackupController.php` | List dan download arsip privat berizin `backup.manage` |
| `AbilityCatalog` | Support | `laravel-api/app/Support/AbilityCatalog.php` | Pendaftaran kemampuan bernama `backup.manage` |
| Migration | Migration | `laravel-api/database/migrations/2029_07_22_000001_add_backup_manage_ability.php` | Add `backup.manage` ability dan tautkan ke `admin-sistem` |
| `routes/api.php` | Routing | `laravel-api/routes/api.php` | Rute terproteksi `/api/backups` dan `/api/backups/{filename}/download` |

---

## 3. Hasil Pengujian Otomatis

### A. Pengujian Backend (`php artisan test`)
- Lingkungan uji: SQLite in-memory `:memory:` terisolasi, storage disk `testing`, dan MariaDB lokal (`school_access_uat` → `school_restore_drill_uat`).
- Hasil suite keseluruhan: **125 passed (573 assertions)**.
- Rincian pengujian Fase 02b (15 tes lulus):
  - `tests/Feature/Backup/BackupRunTest.php` (6 tes):
    1. `guest cannot list or download backups` (401) — PASS
    2. `user without backup.manage cannot list or download backups` (403) — PASS
    3. `admin-sistem with backup.manage can list backups and download valid archive` (200) — PASS
    4. `download rejects path traversal and invalid filenames` (400/404) — PASS
    5. `backup run command creates encrypted zip, manifest, and companion sha256` — PASS
    6. `backup run command rejects passphrase under 12 characters` — PASS
  - `tests/Feature/Backup/RestoreDrillTest.php` (8 tes):
    7. `restore drill successfully restores and verifies database and attachments on disposable target` — PASS
    8. `restore drill strictly refuses to target operational database backdata_administrations` — PASS
    9. `restore drill strictly refuses target database identical to active source database` — PASS
    10. `restore drill refuses target database not on disposable allowlist` — PASS
    11. `restore drill rejects archive with invalid passphrase` — PASS
    12. `restore drill rejects modified archive failing companion checksum` — PASS
    13. `restore drill rejects zip containing path traversal (Zip Slip guard)` — PASS
    14. `restore drill rejects SQL dump containing database switching commands (USE/CREATE DATABASE)` — PASS
  - `tests/Feature/Backup/MariaDbIntegrationTest.php` (1 tes):
    15. `real local MariaDB backup and restore drill round-trip between school_access_uat and school_restore_drill_uat` (12 assertions) — PASS

### B. Pemeriksaan Style Pint (`./vendor/bin/pint --test`)
- File diperiksa: 13 file PHP baru dan termodifikasi pada Subfase 02b.
- Hasil: **13 files PASS** (100% PSR-12 / Laravel clean).

---

## 4. Keterbatasan & Batas Cakupan

1. **Cakupan Lampiran Saat Ini**:
   - Pada saat implementasi Fase 02b, modul yang menghasilkan lampiran riil (Surat pada Fase 07, Dokumen Pegawai pada Fase 04, Arsip Siswa pada Fase 09) belum diimplementasikan.
   - Mekanisme pengarsipan lampiran telah dibuktikan secara nyata menggunakan berkas sintetis representatif (`sample-doc.txt` dan `student-contract-uat.pdf`) pada direktori privat `storage/app/attachments`.
2. **Konsistensi Database dan Lampiran**:
   - Dump MariaDB menggunakan `--single-transaction` yang menjamin konsistensi point-in-time untuk seluruh tabel InnoDB.
   - Berkas lampiran disalin berurutan setelah dump database. Jika terjadi mutasi file masif selama proses backup, ada kemungkinan berkas lampiran berubah. Keterbatasan ini dicatat secara jujur pada `manifest.json`.
3. **Scheduler & Rotasi Retensi**:
   - Kebijakan retensi 7 harian + 4 mingguan (DEC-09, DEC-17) telah disetujui, namun penjadwalan otomatis dan rotasi pembersihan arsip lama berada dalam cakupan **Subfase 02c**. Subfase 02b berfokus murni pada eksekusi manual dan restore drill.
