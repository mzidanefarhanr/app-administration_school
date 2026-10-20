# Laporan Subfase 02b — Backup Manual Terenkripsi dan Restore Drill Terisolasi

- Tanggal WIB: 2026-10-19 (Implementasi), 2026-10-20 (Penerimaan Manual)
- Branch kerja: `main`
- Status teknis: **IMPLEMENTED & FULLY VERIFIED (132 backend tests PASS, MariaDB round-trip PASS, Pint PASS)**
- Status review pemilik: **APPROVED (DITERIMA PEMILIK pada 20 Oktober 2026 WIB mengacu baseline e8eaea4; seluruh checklist #1–#10 APPROVED)**
- Prasyarat: Subfase 02a telah **DITERIMA LENGKAP OLEH PEMILIK**; keputusan DEC-18 APPROVED
- Batasan penerimaan & cakupan:
  - **Data Sintetis**: Pencadangan dan pemulihan dibuktikan sepenuhnya menggunakan data UAT sintetis (`school_access_uat` dan fixture privat sintetis).
  - **Konsistensi Lampiran**: Modul lampiran operasional (Surat, Kepegawaian, Dokumen Siswa) belum dibangun; konsistensi lintas database–lampiran operasional berstatus **N/A (belum dibuktikan)**.
  - **Bukan Snapshot Atomik**: Deteksi deviasi berkas (pre/post-scan) bukan snapshot atomik database–berkas. Penerapan pada lampiran operasional produksi memerlukan penghentian mutasi sementara (*write quiescence*) atau mekanisme snapshot filesystem.
  - **Bukan Izin Rilis**: Penerimaan manual ini bukan izin deployment atau pelaksanaan backup pada database operasional produksi.
- Database & storage:
  - Database pengujian otomatis: **SQLite in-memory `:memory:` terisolasi** dan disk storage `testing`.
  - Database integrasi nyata lokal: MariaDB 10.4.32 port 3306 sumber `school_access_uat` (sintetis) dan target disposable `school_restore_drill_uat`.
  - Database operasional: `backdata_administrations` (TERLINDUNGI & TIDAK PERNAH DISENTUH).

---

## 1. Ringkasan Eksekutif

Subfase 02b mengimplementasikan pencadangan (backup) mandiri terenkripsi dan uji pemulihan (restore drill) terisolasi sesuai keputusan pemilik **DEC-17** dan **DEC-18**:

1. **Pencadangan Mandiri & Terenkripsi**:
   - Perintah `php artisan --env=uat app:backup-run` mengekspor database skema + data dan mengarsipkan direktori lampiran privat `storage/app/attachments`.
   - Menggunakan standar enkripsi **ZIP AES-256** native PHP via `ZipArchive::EM_AES_256`.
   - Kredensial database diisolasi dari process list OS menggunakan berkas konfigurasi sementara `--defaults-extra-file` dengan jaminan pembersihan seketika di blok `finally`.
   - **Tanpa Flag Passphrase CLI**: Passphrase rahasia tidak diterima via argumen/opsi CLI, melainkan bersumber dari konfigurasi rahasia lokal `BACKUP_ENCRYPTION_KEY` atau secret prompt interaktif CLI (min 12 karakter); tidak pernah masuk ke log, exception, manifest, subprocess arguments, atau repositori Git.
   - Menampilkan identitas koneksi efektif (host, port, database, direktori lampiran) tanpa kredensial, serta memverifikasi database sumber bukan database operasional sebelum dump.
2. **Konsistensi Database & Deteksi Deviasi Lampiran**:
   - **Prasyarat Engine Database**: Dump MySQL memverifikasi seluruh tabel sumber menggunakan engine `InnoDB`. Jika ditemukan tabel non-transaksional, backup digagalkan dengan jelas.
   - **Deteksi Deviasi Lampiran (Attachment Drift Detection)**: Berkas lampiran dipindai sebelum dump database dimulai (pre-scan) dan dipindai ulang sesudah dump selesai (post-scan). Jika terdeteksi adanya penambahan, modifikasi, atau penghapusan berkas lampiran selama pengambilan backup, publikasi arsip langsung dibatalkan demi mencegah arsip yang tidak konsisten.
   - **Batasan Konsistensi**: Deteksi deviasi ini bukan merupakan snapshot atomik lintas database dan filesystem. Karena modul lampiran riil belum dibangun pada sistem, konsistensi lintas database–lampiran operasional dinyatakan berstatus **N/A (belum dibuktikan)**. Penerapan pada data operasional di masa mendatang mewajibkan mekanisme penghentian mutasi sementara (application write quiescence) atau snapshot level filesystem.
3. **Manifest Integritas & Companion Checksum**:
   - `manifest.json` di dalam arsip memuat metadata aplikasi, timestamp UTC & WIB, versi DBMS, ringkasan tabel dan jumlah baris data, status migrasi terakhir, checksum SHA-256 berkas dump SQL, metadata berkas lampiran, dan catatan batasan konsistensi.
   - Checksum SHA-256 arsip final disimpan di berkas pendamping di luar arsip: `backup-<env>-<timestamp>.zip.sha256`.
4. **Atomic Publishing & Fail-Safe Cleanup**:
   - Seluruh tahapan backup dibangun di dalam folder temporer unik `storage/app/backups/.tmp-<uuid>/`.
   - Jika proses gagal pada tahap mana pun, direktori temporer dan file konfigurasi sementara dibersihkan seketika tanpa meninggalkan berkas plaintext atau arsip parsial/korup.
   - Publikasi arsip ke `storage/app/backups/backup-<env>-<timestamp>.zip` hanya dilakukan setelah seluruh proses kompresi, enkripsi, dan verifikasi selesai 100%. Berkas temporer (`.tmp*`, `.sha256`) tidak pernah muncul dalam daftar unduhan.
5. **Unduhan Berizin `backup.manage`**:
   - Endpoint `GET /api/backups` dan `GET /api/backups/{filename}/download` dilindungi autentikasi dan kemampuan bernama `backup.manage`.
   - Kemampuan `backup.manage` terdaftar di `AbilityCatalog.php` dan dipasangkan secara default ke peran **System Administrator** (`admin-sistem`) via migration `2029_07_22_000001_add_backup_manage_ability.php` tanpa mengubah kemampuan peran lain.
   - Perlindungan Directory Traversal ketat: nama file divalidasi dengan regex strict `/^backup-[a-zA-Z0-9._-]+\.zip$/`; karakter `..`, `/`, `\`, `%00` serta symlink yang mengarah ke luar direktori backup ditolak tegas. Berkas disajikan via stream privat tanpa public URL.
6. **Uji Pemulihan Terisolasi (Restore Drill)**:
   - Perintah `php artisan --env=uat app:restore-drill {archive}` memulihkan dan memvalidasi integritas arsip ke target database dan storage disposable.
   - **Fail-safe keras**: Menolak keras database operasional `backdata_administrations`, database aktif sumber, dan database di luar allowlist disposable (`school_restore_drill`, `school_restore_drill_uat`).
   - **Penolakan Target Berisi Data**: Target database yang sudah berisi tabel ditolak secara tegas demi mencegah penimpaan data eksisting. Database drill tidak dikosongkan secara otomatis.
   - **Anti-Zip-Slip Guard**: Memeriksa nama setiap entri dalam ZIP dan menolak berkas dengan path traversal.
   - **Audit SQL Dump**: Memindai isi dump dan menggugurkan proses bila terdapat perintah berbahaya pengalihan koneksi (`USE ...` atau `CREATE DATABASE ...`).
   - **Isolasi Lampiran**: Berkas lampiran diekstraksi ke direktori uji disposable `storage/app/restore-drill-attachments/drill-<uuid>`, bukan ke direktori lampiran aktif.
   - **Pembuktian Multi-Faktor**: Pemulihan diverifikasi meliputi skema tabel, jumlah baris data cocok dengan manifest, foreign key integrity valid, akun administrator terverifikasi aktif (tanpa mencetak password/hash), dan checksum berkas lampiran cocok 100%.

---

## 2. Inventarisasi Komponen & Jalur Operasi

| Komponen | Tipe | Lokasi File | Fungsi & Proteksi |
|---|---|---|---|
| `config/backup.php` | Konfigurasi | `laravel-api/config/backup.php` | Path storage privat, allowlist target disposable, forbidden databases, path binary DB |
| `BackupService` | Service | `laravel-api/app/Services/BackupService.php` | Dump MySQL & SQLite, ZipArchive AES-256, manifest, companion sha256, attachment drift detection, atomic publishing |
| `RestoreDrillService` | Service | `laravel-api/app/Services/RestoreDrillService.php` | Validasi fail-safe target, penolakan target berisi data, anti-Zip-Slip, audit SQL dump, multi-factor verification |
| `BackupRunCommand` | CLI Command | `laravel-api/app/Console/Commands/BackupRunCommand.php` | `php artisan app:backup-run` dengan secret prompt / env passphrase tanpa argumen CLI |
| `RestoreDrillCommand` | CLI Command | `laravel-api/app/Console/Commands/RestoreDrillCommand.php` | `php artisan app:restore-drill` dengan fail-safe koneksi target tanpa opsi passphrase |
| `BackupController` | Controller API | `laravel-api/app/Http/Controllers/BackupController.php` | List dan download arsip privat berizin `backup.manage` |
| `AbilityCatalog` | Support | `laravel-api/app/Support/AbilityCatalog.php` | Pendaftaran kemampuan bernama `backup.manage` |
| Migration | Migration | `laravel-api/database/migrations/2029_07_22_000001_add_backup_manage_ability.php` | Add `backup.manage` ability dan tautkan ke `admin-sistem` |
| `routes/api.php` | Routing | `laravel-api/routes/api.php` | Rute terproteksi `/api/backups` dan `/api/backups/{filename}/download` |

---

## 3. Hasil Pengujian Otomatis

### A. Pengujian Backend (`php artisan test`)
- Lingkungan uji: SQLite in-memory `:memory:` terisolasi, storage disk `testing`, dan MariaDB lokal (`school_access_uat` → `school_restore_drill_uat`).
- Hasil suite keseluruhan: **132 passed (602 assertions)**.
- Rincian pengujian Fase 02b (22 tes lulus):
  - `tests/Feature/Backup/BackupRunTest.php` (11 tes):
    1. `guest cannot list or download backups` (401) — PASS
    2. `user without backup.manage cannot list or download backups` (403) — PASS
    3. `admin-sistem with backup.manage can list backups and download valid archive` (200) — PASS
    4. `download rejects path traversal and invalid filenames` (400/404) — PASS
    5. `partial and temporary backup files are excluded from list and download` — PASS
    6. `download rejects symlink resolving outside backup directory` — PASS
    7. `backup run command creates encrypted zip via interactive secret prompt` — PASS
    8. `backup run command aborts when passphrase confirmation does not match` — PASS
    9. `backup run command rejects passphrase under 12 characters` — PASS
    10. `backup run cleans up temporary directories and plaintext dumps on failure` — PASS
    11. `backup run detects attachment drift during backup and aborts publication` — PASS
  - `tests/Feature/Backup/RestoreDrillTest.php` (10 tes):
    12. `restore drill successfully restores and verifies database and attachments on disposable target` — PASS
    13. `restore drill command executes via interactive secret prompt without CLI passphrase flag` — PASS
    14. `restore drill strictly refuses to target operational database backdata_administrations` — PASS
    15. `restore drill strictly refuses target database identical to active source database` — PASS
    16. `restore drill refuses target database not on disposable allowlist` — PASS
    17. `restore drill strictly rejects target database containing existing data and preserves original data` — PASS
    18. `restore drill rejects archive with invalid passphrase` — PASS
    19. `restore drill rejects modified archive failing companion checksum` — PASS
    20. `restore drill rejects zip containing path traversal (Zip Slip guard)` — PASS
    21. `restore drill rejects SQL dump containing database switching commands (USE/CREATE DATABASE)` — PASS
  - `tests/Feature/Backup/MariaDbIntegrationTest.php` (1 tes):
    22. `real local MariaDB backup and restore drill round-trip between school_access_uat and school_restore_drill_uat` (12 assertions) — PASS

### B. Pemeriksaan Style Pint (`./vendor/bin/pint --test`)
- File diperiksa: 13 file PHP baru dan termodifikasi pada Subfase 02b.
- Hasil: **13 files PASS** (100% PSR-12 / Laravel clean).

---

## 4. Keterbatasan & Batas Cakupan

1. **Cakupan Lampiran Saat Ini**:
   - Pada saat implementasi Fase 02b, modul yang menghasilkan lampiran riil (Surat pada Fase 07, Dokumen Pegawai pada Fase 04, Arsip Siswa pada Fase 09) belum diimplementasikan.
   - Mekanisme pengarsipan lampiran telah dibuktikan secara nyata menggunakan berkas sintetis representatif (`sample-doc.txt` dan `student-contract-uat.pdf`) pada direktori privat `storage/app/attachments`.
2. **Konsistensi Database dan Lampiran**:
   - Dump MariaDB menggunakan `--single-transaction` yang diverifikasi khusus untuk tabel ber-engine `InnoDB`.
   - Deteksi deviasi berkas lampiran memindai perbedaan berkas sebelum dan sesudah dump. Ini bukan snapshot atomik instan lintas storage.
   - Konsistensi lintas database–lampiran operasional dicatat berstatus **N/A (belum dibuktikan)** hingga modul lampiran operasional selesai dibangun dan prasyarat penghentian mutasi sementara (quiescence) diterapkan.
3. **Scheduler & Rotasi Retensi**:
   - Kebijakan retensi 7 harian + 4 mingguan (DEC-09, DEC-17) telah disetujui, namun penjadwalan otomatis dan rotasi pembersihan arsip lama berada dalam cakupan **Subfase 02c**. Subfase 02b berfokus murni pada eksekusi manual dan restore drill.
