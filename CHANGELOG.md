# Riwayat perubahan proyek

Catat perubahan selesai; jangan menyalin riwayat template `vue-app/CHANGELOG.md` menjadi riwayat aplikasi. Riwayat commit sebelum paket ini belum direkonstruksi. Tambah entri terbaru di atas entri lama; koreksi lewat addendum, jangan menghapus bukti sebelumnya.

## Format entri
```markdown
## [YYYY-MM-DD] Judul perubahan
- Type: Feature | Fix | Security | Schema Change | Docs | Chore
- Scope / fase:
- Files touched:
- What changed:
- Why:
- Validation: perintah + hasil aktual; NOT RUN bila tidak dijalankan
- Notes / risks:
- Evidence: laporan fase / SHA aktual bila sudah ada
```

## Entries

## [2026-10-20] Subfase 02c — Feature & Ops: implementasi backup non-interaktif, mutex lock, retensi 7+4, dan monitoring status
- Type: Feature | Ops | Security
- Scope / fase: Subfase 02c (Jadwal dan Monitoring Backup).
- Files touched:
  - `laravel-api/config/backup.php`
  - `laravel-api/app/Services/BackupMutex.php` (baru)
  - `laravel-api/app/Services/BackupMonitoringService.php` (baru)
  - `laravel-api/app/Services/BackupRetentionService.php` (baru)
  - `laravel-api/app/Console/Commands/BackupPruneCommand.php` (baru)
  - `laravel-api/app/Console/Commands/BackupRunCommand.php`
  - `laravel-api/app/Console/Commands/RestoreDrillCommand.php`
  - `laravel-api/app/Console/Kernel.php`
  - `laravel-api/app/Http/Controllers/BackupController.php`
  - `laravel-api/routes/api.php`
  - `laravel-api/.env.example`
  - `laravel-api/tests/Feature/Backup/BackupScheduleTest.php` (baru)
  - `laravel-api/tests/Feature/Backup/BackupPruneTest.php` (baru)
  - `laravel-api/tests/Feature/Backup/BackupMonitoringTest.php` (baru)
  - `docs/DECISIONS.md`
  - `docs/API-CONTRACTS.md`
  - `docs/OPERATIONS.md`
  - `reports/feature-update/PHASE-02C-MONITORING-SCHEDULE.md` (baru)
  - `reports/feature-update/PHASE-02C-MANUAL-REVIEW.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`
  - `CHANGELOG.md`
- What changed:
  1. Backup non-interaktif: opsi `--non-interactive` pada `app:backup-run` membaca passphrase lokal; gagal seketika tanpa prompt dan tanpa secret leak jika key kosong/tidak valid.
  2. Penjadwalan opt-in: Kernel menjadwalkan backup 02:00 dan prune 02:30 Asia/Jakarta dengan default OFF (`BACKUP_SCHEDULE_ENABLED=false`).
  3. Shared Mutex Lock: `BackupMutex` mencegah tabrakan eksekusi manual, terjadwal, dan rotasi retensi; proses yang tumpang tindih dilewati (*skipped*) tanpa menimpa status sukses terakhir.
  4. Rotasi retensi bertingkat 7 harian + 4 mingguan: command `app:backup-prune` memelihara 7 tanggal harian terbaru dan 4 minggu kalender terbaru (Senin-Minggu, Asia/Jakarta); melindungi backup terakhir sukses secara mutlak; menghapus arsip `.zip` dan companion `.sha256` berpasangan; rotasi dilewati jika backup hari ini gagal; mode `--dry-run` tersedia.
  5. Monitoring pasif & freshness detection: status terstruktur di `monitoring.json`, endpoint API `GET /api/backups/status` (`backup.manage`), pembedaan tegas `backup_created` vs `restore_verified`, dan deteksi dinamis status `OVERDUE` jika > 28 jam.
- Why: memenuhi keputusan pemilik DEC-17, DEC-18, dan DEC-19 untuk mengotomatiskan dan memantau cadangan sistem sekolah secara aman.
- Validation:
  - Backend: `php artisan test` → **152 passed (702 assertions)** (27 tes baru 02c PASS).
  - Style check: `./vendor/bin/pint --test` (13 file) → **PASS** (100% clean).
  - Frontend: `node --test` → 15 passed, `npm run build` → PASS (kode frontend tidak berubah).
- Notes / risks:
  - Monitoring bersifat pasif dan memerlukan peninjauan berkala oleh administrator.
  - Penjadwalan otomatis memerlukan aktivasi opt-in dan konfigurasi Task Scheduler / cron saat deployment produksi.
  - Review manual pemilik berstatus PENDING (checklist #1–#10 di `PHASE-02C-MANUAL-REVIEW.md`).
- Evidence: `reports/feature-update/PHASE-02C-MONITORING-SCHEDULE.md`, `reports/feature-update/PHASE-02C-MANUAL-REVIEW.md`.

## [2026-10-20] Subfase 02c — Docs: perencanaan jadwal otomatis, rotasi retensi bertingkat, dan monitoring backup
- Type: Docs
- Scope / fase: Subfase 02c (Jadwal dan Monitoring Backup).
- Files touched:
  - `reports/feature-update/PHASE-02C-PLAN.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`
  - `CHANGELOG.md`
- What changed:
  1. Penyusunan rencana kerja teknis konkret Subfase 02c tanpa implementasi kode atau pengaktifan scheduler otomatis:
     - Jadwal dan timezone: `Asia/Jakarta` (WIB), usulan waktu pencadangan harian pukul 02:00 WIB dan rotasi retensi pukul 02:30 WIB di luar jam operasional sekolah.
     - Eksekusi non-interaktif: pembacaan passphrase rahasia dari konfigurasi lokal `BACKUP_ENCRYPTION_KEY`; pembatalan seketika tanpa prompt dan tanpa fallback kredensial jika tidak tersedia.
     - Pencegahan overlap: mutex lock atomik berbasis file lock (`.backup.lock`) dan `withoutOverlapping` pada scheduler Laravel.
     - Algoritma retensi 7 harian + 4 mingguan (DEC-09, DEC-17): definisi seleksi harian (1 arsip terbaru per hari selama 7 hari terakhir) dan mingguan (1 arsip akhir pekan per minggu selama 4 minggu terakhir), deduplikasi union, proteksi mutlak cadangan terakhir yang berhasil (*last successful backup preservation*), pembersihan berpasangan (.zip dan .sha256), guard anti-symlink, serta mode `--dry-run`.
     - Monitoring status & freshness alert: struktur data terstruktur `monitoring.json`, endpoint `GET /api/backups/status` (`backup.manage`), pembedaan tegas status "backup created" vs "restore verified", dan deteksi keterlambatan jika waktu melebihi 28 jam.
     - Runbook OS: panduan instalasi Windows Task Scheduler dan Linux Cron untuk referensi masa depan (belum dipasang/diaktifkan saat ini).
     - Strategi pengujian: manipulasi waktu virtual `Carbon::setTestNow(...)` pada storage testing terisolasi tanpa menyentuh backup UAT pemilik.
  2. Identifikasi 2 pertanyaan keputusan pemilik: konfirmasi jadwal 02:00 WIB dan konfirmasi penanganan status `OVERDUE`.
- Evidence: `reports/feature-update/PHASE-02C-PLAN.md`.

## [2026-10-20] Subfase 02b — Docs: penerimaan manual pemilik atas backup manual dan restore drill
- Type: Docs
- Scope / fase: Subfase 02b (Backup Manual dan Restore Drill).
- Files touched:
  - `reports/feature-update/PHASE-02B-BACKUP-RESTORE.md`
  - `reports/feature-update/PHASE-02B-MANUAL-REVIEW.md`
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`
  - `CHANGELOG.md`
- What changed:
  1. Pencatatan penerimaan manual pemilik atas seluruh checklist UAT Subfase 02b (#1–#10) berstatus **APPROVED** pada 20 Oktober 2026 WIB mengacu baseline commit `e8eaea4`.
  2. Status Subfase 02b resmi ditetapkan **DITERIMA PEMILIK**.
  3. Penegasan status fase: Fase 01 tetap DITUTUP LENGKAP; Subfase 02a dan 02b DITERIMA PEMILIK; Fase 02 secara keseluruhan tetap berjalan (PARTIAL / IN PROGRESS) karena Subfase 02c (Jadwal dan Monitoring Backup) belum diimplementasikan / belum selesai.
  4. Penegasan batasan penerimaan: pencadangan dan pemulihan dibuktikan pada data UAT sintetis (`school_access_uat` dan fixture privat sintetis); modul lampiran operasional belum tersedia sehingga konsistensi lintas database–lampiran operasional tetap berstatus N/A; deteksi deviasi berkas bukan snapshot atomik database–berkas; penerapan lampiran operasional produksi memerlukan mekanisme penghentian mutasi sementara (*write quiescence*); penerimaan ini bukan izin deployment atau backup operasional produksi.
- Evidence: `reports/feature-update/PHASE-02B-MANUAL-REVIEW.md`, baseline commit `e8eaea4`.

## [2026-10-19] Subfase 02b — Feature & Security: implementasi backup manual terenkripsi dan restore drill terisolasi
- Type: Feature | Security
- Scope / fase: Subfase 02b (Backup Manual dan Restore Drill).
- Files touched:
  - `laravel-api/config/backup.php` (baru)
  - `laravel-api/config/database.php`
  - `laravel-api/app/Support/AbilityCatalog.php`
  - `laravel-api/database/migrations/2029_07_22_000001_add_backup_manage_ability.php` (baru)
  - `laravel-api/app/Services/BackupService.php` (baru)
  - `laravel-api/app/Services/RestoreDrillService.php` (baru)
  - `laravel-api/app/Console/Commands/BackupRunCommand.php` (baru)
  - `laravel-api/app/Console/Commands/RestoreDrillCommand.php` (baru)
  - `laravel-api/app/Http/Controllers/BackupController.php` (baru)
  - `laravel-api/routes/api.php`
  - `laravel-api/tests/Feature/Backup/BackupRunTest.php` (baru)
  - `laravel-api/tests/Feature/Backup/RestoreDrillTest.php` (baru)
  - `laravel-api/tests/Feature/Backup/MariaDbIntegrationTest.php` (baru)
  - `docs/DECISIONS.md`
  - `docs/API-CONTRACTS.md`
  - `docs/OPERATIONS.md`
  - `docs/SECURITY-DATA.md`
  - `reports/feature-update/PHASE-02B-BACKUP-RESTORE.md` (baru)
  - `reports/feature-update/PHASE-02B-MANUAL-REVIEW.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`
  - `CHANGELOG.md`
- What changed:
  1. Perintah `app:backup-run`: mengekspor database (mysqldump / sqlite) dan berkas lampiran privat `storage/app/attachments` ke direktori privat `storage/app/backups`; enkripsi ZIP AES-256 via native PHP `ZipArchive::EM_AES_256`; isolasi kredensial dump via `--defaults-extra-file` temporer dengan pembersihan seketika di blok `finally`.
  2. Manifest dan companion checksum: `manifest.json` di dalam arsip memuat metadata verifikasi lengkap tanpa kredensial; checksum SHA-256 arsip final disimpan di berkas pendamping `.sha256` di luar arsip; atomic publishing via direktori temporer `.tmp-<uuid>`.
  3. API download berizin: `GET /api/backups` dan `GET /api/backups/{filename}/download` dilindungi kemampuan `backup.manage` (default: `admin-sistem` via migration baru additive tanpa mengubah peran kustom) dan validasi anti-path-traversal strict regex.
  4. Perintah `app:restore-drill`: memulihkan dan memvalidasi integritas arsip ke target database disposable `school_restore_drill_uat`; fail-safe keras menolak database operasional `backdata_administrations` dan database sumber aktif; guard anti-Zip-Slip; audit pemindaian SQL dump menolak perintah pengalihan database (`USE`, `CREATE DATABASE`); isolasi ekstraksi lampiran ke direktori terpisah; pembuktian pemulihan multi-faktor (tabel, row count, foreign keys, akun admin tanpa mencetak hash, dan checksum lampiran).
- Why: memenuhi keputusan pemilik DEC-17 dan DEC-18 untuk menjamin kesiapan pemulihan bencana dan integritas data sekolah sebelum modul operasional aktif.
- Validation:
  - Backend: `php artisan test` → **125 passed (573 assertions)** (15 tes baru Backup & Restore PASS).
  - Integration: `MariaDbIntegrationTest` (uji round-trip nyata MariaDB lokal `school_access_uat` ke `school_restore_drill_uat`) → PASS (12 assertions).
  - Backend style: `./vendor/bin/pint --test` (13 files) → PASS (100% clean).
  - Frontend: `node --test` → 15 passed, `npm run build` → PASS (kode frontend tidak berubah).
- Notes / risks:
  - Modul lampiran riil belum tersedia; dibuktikan menggunakan fixture lampiran privat sintetis.
  - Scheduler otomatis dan rotasi retensi 7+4 berada dalam cakupan Subfase 02c.
  - Pemeriksaan manual pemilik berstatus PENDING (checklist #1–#10 di `PHASE-02B-MANUAL-REVIEW.md`).
- Evidence: `reports/feature-update/PHASE-02B-BACKUP-RESTORE.md`, `reports/feature-update/PHASE-02B-MANUAL-REVIEW.md`.

## [2026-10-19] Subfase 02b — Docs: perencanaan backup manual terenkripsi dan restore drill terisolasi
- Type: Docs
- Scope / fase: Subfase 02b (Backup Manual dan Restore Drill).
- Files touched:
  - `reports/feature-update/PHASE-02B-PLAN.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`
  - `CHANGELOG.md`
- What changed:
  1. Penyusunan rencana kerja teknis konkret Subfase 02b tanpa implementasi kode atau eksekusi mutasi data:
     - Backup database skema + data dan lampiran privat ke direktori privat (`storage/app/backups`) di luar public web root.
     - Standar enkripsi arsip: ZIP AES-256 via ekstensi native PHP `ZipArchive`; rahasia enkripsi bersumber dari env/secret prompt terpisah, tidak pernah masuk argumen proses atau log.
     - Struktur `manifest.json`: format version, timestamp, identitas sumber tanpa kredensial, ringkasan skema/tabel/baris, dan checksum SHA-256 untuk database dump, file lampiran, serta arsip final.
     - Penanganan kegagalan & atomic publishing: pembuatan backup di direktori temporer `.tmp-<uuid>`; jika gagal dibersihkan seketika tanpa meninggalkan file parsial/korup; publikasi arsip hanya setelah seluruh tahapan berhasil.
     - Endpoint unduh berizin `GET /api/backups/{filename}/download` dengan kemampuan bernama `backup.manage` (default: System Administrator), dilengkapi perlindungan anti-directory traversal.
     - Command restore drill `app:restore-drill` ke target database dan storage disposable (`school_restore_drill`), dilengkapi fail-safe keras menolak database operasional (`backdata_administrations`) dan database aktif sumber, anti-Zip-Slip guard, serta pembuktian multi-faktor (skema, data row count, foreign key integrity, verifikasi akun admin, dan berkas lampiran).
     - Prosedur penyalinan berkala ke media eksternal (cold storage) dan pemisahan kunci pemulihan.
  2. Inventarisasi prasyarat aktual: MariaDB 10.4.32, mysqldump.exe, mysql.exe, PHP 8.2.12 ZipArchive dengan EM_AES_256, OpenSSL, disk privat `local` (`storage/app`).
  3. Identifikasi keputusan yang dibutuhkan dari pemilik sebelum implementasi: nama database disposable lokal (`school_restore_drill_uat`) dan konfirmasi default peran untuk `backup.manage`.
- Evidence: `reports/feature-update/PHASE-02B-PLAN.md`.

## [2026-10-19] Subfase 02a — Docs: penerimaan manual pemilik atas audit server dan penonaktifan rollback
- Type: Docs
- Scope / fase: Subfase 02a (Audit Server dan Rollback Terbatas).
- Files touched:
  - `reports/feature-update/PHASE-02A-AUDIT.md`
  - `reports/feature-update/PHASE-02A-MANUAL-REVIEW.md`
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`
  - `CHANGELOG.md`
- What changed:
  1. Pencatatan penerimaan manual pemilik atas seluruh checklist UAT Subfase 02a (#1–#9) berstatus **APPROVED** pada 19 Oktober 2026 WIB mengacu baseline commit `cf94cae`.
  2. Status Subfase 02a resmi ditetapkan **DITERIMA PEMILIK**.
  3. Penegasan status fase: Fase 01 tetap DITUTUP LENGKAP; Fase 02 secara keseluruhan masih berjalan (IN PROGRESS / PARTIAL) karena Subfase 02b (Backup Manual dan Restore Drill) dan 02c (Jadwal dan Monitoring Backup) belum diimplementasikan / belum diterima.
  4. Penegasan batas cakupan: perlindungan audit dan immutability ditegakkan pada level API aplikasi dan ORM Eloquent, bukan proteksi terhadap administrator root DB/OS; bulk deletion di frontend berjalan per-record atomic di level klien; modul yang belum diimplementasikan (Student CRUD, Employee CRUD, Rombel write, Surat, Izin, Pembelajaran) tetap berstatus N/A.
- Evidence: `reports/feature-update/PHASE-02A-MANUAL-REVIEW.md`, commit baseline `cf94cae`.

## [2026-10-17] Subfase 02a — Security & Feature: audit server terpercaya, allowlist atribut, dan penonaktifan rollback klien
- Type: Security | Feature
- Scope / fase: Subfase 02a (Audit Server dan Rollback Terbatas).
- Files touched:
  - `laravel-api/database/migrations/2029_07_21_000001_make_user_id_nullable_on_activities_table.php` (baru)
  - `laravel-api/app/Console/Commands/BootstrapAdminCommand.php`
  - `laravel-api/app/Traits/Loggable.php`
  - `laravel-api/app/Models/Activity.php`
  - `laravel-api/app/Models/User.php`
  - `laravel-api/app/Models/Role.php`
  - `laravel-api/app/Models/Ability.php`
  - `laravel-api/app/Models/Student.php`
  - `laravel-api/app/Models/EducationSchool.php`
  - `laravel-api/app/Models/StatusUser.php`
  - `laravel-api/app/Models/Semester.php`
  - `laravel-api/app/Models/SchoolLevel.php`
  - `laravel-api/app/Http/Controllers/ActivityController.php`
  - `laravel-api/app/Http/Controllers/RoleController.php`
  - `laravel-api/app/Http/Controllers/AccessAssignmentController.php`
  - `laravel-api/app/Http/Controllers/UserController.php`
  - `laravel-api/app/Http/Controllers/FirstLoginController.php`
  - `laravel-api/app/Http/Controllers/TypeUserController.php`
  - `laravel-api/app/Http/Controllers/SchoolYearController.php`
  - `laravel-api/routes/api.php`
  - `laravel-api/tests/Feature/Audit/ServerAuditTest.php` (baru)
  - `laravel-api/tests/Feature/Access/BootstrapAdminTest.php`
  - `vue-app/src/utils/useResourceManager.js`
  - `docs/API-CONTRACTS.md`
  - `docs/SECURITY-DATA.md`
  - `docs/DATABASE.md`
  - `reports/feature-update/PHASE-02A-AUDIT.md` (baru)
  - `reports/feature-update/PHASE-02A-MANUAL-REVIEW.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`
  - `CHANGELOG.md`
- What changed:
  1. Penutupan mutasi klien: `POST /api/activities` (pemalsuan log), `PUT/PATCH /api/activities/{id}` (modifikasi & rollback), dan `DELETE /api/activities/{id}` (penghapusan log) dinonaktifkan secara tegas dengan HTTP 405 Method Not Allowed.
  2. Immutability model level: model `Activity` menerapkan event callback `booted()` yang melempar `\RuntimeException` saat `updating` atau `deleting` dipanggil langsung di kode aplikasi; dilengkapi hook pengujian atomisitas `$testCreatingHook`.
  3. Pembacaan audit berizin & sanitasi serialisasi: `GET /api/activities` dan `GET /api/activities/{id}` dilindungi kemampuan bernama `audit.view`; mengembalikan JSON terpaginasi dengan relasi pengguna `user:id,name,username` tanpa mengekspos kata sandi atau hash.
  4. Penegakan server-side & allowlist atribut: actor (`user_id`), target (`table_name`, `record_id`), timestamp, dan aksi (`information`) ditentukan oleh server (`Auth::id()`). Trait `Loggable.php` menerapkan allowlist `$auditableAttributes` pada `User`, `Role`, `Ability`, `Student`, `EducationSchool`, dan master data.
  5. Penyaringan rahasia terdefinisi: kata sandi mentah, password baru, remember_token, access token, dan hash bcrypt tersaring total dari kolom JSON `before`, `after`, `new`, `delete`.
  6. Audit pivot: mutasi penetapan peran (`role_user`) dan sinkronisasi kemampuan peran (`ability_role`) dicatat ke tabel `activities` secara transaksional di server.
  7. Konsistensi transaksi mutasi model: pembungkusan mutasi `UserController` (`store`, `update`, `destroy`), `RoleController`, `AccessAssignmentController`, `FirstLoginController::update`, `TypeUserController` (`store`, `update`, `destroy`), dan `SchoolYearController` (`store`, `update`, `destroy`) dalam `DB::transaction` atomik; terbukti kegagalan proses audit membatalkan perubahan domain/pivot secara penuh tanpa meninggalkan audit sukses atau data separuh jalan.
  8. Audit bootstrap admin: migration baru `2029_07_21_000001_make_user_id_nullable_on_activities_table.php` mendukung actor `system:cli` (`user_id = null`); `BootstrapAdminCommand` mencatat audit pembuatan akun dan penetapan peran secara atomik tanpa password/hash/secret; kegagalan audit membatalkan pembuatan akun dan role assignment; bootstrap yang ditolak karena admin sudah ada tidak menghasilkan audit keberhasilan fiktif.
  9. Koreksi matriks bulk delete: diklasifikasikan sebagai per-record atomic (loop HTTP individual di `useResourceManager.js`), bukan satu batch atomic lintas entitas.
  10. Pembersihan frontend: pemanggilan `POST /api/activities` yang dikomentari di `useResourceManager.js` dibersihkan.
- Why: memenuhi keputusan pemilik DEC-17 untuk mengunci audit trail ke mode immutable append-only, mencegah pemalsuan dan manipulasi data dari sisi klien, dan melindungi kredensial serta privasi data sekolah.
- Validation:
  - Backend: `php artisan test` pada SQLite in-memory → **110 passed (506 assertions)** (25 tes `ServerAuditTest`, 8 tes `BootstrapAdminTest` PASS).
  - Backend style: `./vendor/bin/pint --test` (18 file) → **PASS** (100% clean).
  - Frontend node tests: `node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs` → **15 passed, 0 failed**.
  - Frontend build: `npm run build` → **PASS** (`✓ built in 34s`, 0 errors).
  - Frontend lint: `npx eslint src/utils/useResourceManager.js` → **0 errors**.
  - Live HTTP UAT: `http://127.0.0.1:8001/api/activities` guest ditolak 401; unauthenticated browser dialihkan ke `/auth/login`.
- Notes / risks:
  - Perlindungan audit ditegakkan pada level API aplikasi dan Eloquent, bukan kekebalan terhadap administrator sistem operasi atau administrator database langsung (root DB/OS admin).
  - Pemeriksaan manual pemilik berstatus PENDING (checklist #1–#9 di `PHASE-02A-MANUAL-REVIEW.md`).
- Evidence: `reports/feature-update/PHASE-02A-AUDIT.md`, `reports/feature-update/PHASE-02A-MANUAL-REVIEW.md`.

## [2026-10-17] Fase 02 — Docs: perencanaan audit terpercaya, rollback terbatas, dan backup/restore drill
- Type: Docs
- Scope / fase: penyusunan rencana teknis Fase 02 (audit terpercaya dan backup yang dapat dipulihkan) berdasarkan roadmap dan kondisi aktual HEAD.
- Files touched:
  - `reports/feature-update/PHASE-02-PLAN.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `CHANGELOG.md`
- What changed:
  1. Perumusan tujuan dan cakupan 3 subfase Fase 02: 02a (audit server-side anti-tampering dan rollback terbatas), 02b (backup mandiri DB + file privat dengan manifest SHA-256 dan restore drill pada target disposable), dan 02c (scheduler, rotasi retensi, dan monitoring).
  2. Audit temuan teknis aktual HEAD:
     - Celah pemalsuan log klien via `POST /api/activities`.
     - Cacat teknis implementasi rollback pada `ActivityController::update` (`DB::table($modelName)`).
     - Relasi `cascadeOnDelete()` pada `activities.user_id` yang berisiko menghapus riwayat audit.
     - Kebutuhan disk privat untuk arsip backup dan command mandiri.
  3. Perumusan kriteria penerimaan dan strategi verifikasi terisolasi tanpa menyentuh database operasional `backdata_administrations`.
  4. Identifikasi keputusan pemilik yang diperlukan: DEC-02 (lokasi penyimpanan backup), DEC-09 (retensi & enkripsi backup), dan kebijakan fitur rollback pada 02a (rekomendasi: nonaktifkan rollback dan terapkan append-only audit trail murni).
- Why: memenuhi prasyarat perencanaan formal sebelum memulai implementasi subfase 02a.
- Validation: source review statis dan analisis roadmap.
- Notes / risks: Fase 02 NOT STARTED (implementasi belum dimulai, menunggu review pemilik).
- Evidence: `reports/feature-update/PHASE-02-PLAN.md`.

## [2026-10-17] Fase 01 — Docs: penerimaan manual lengkap Fase 01c dan penutupan Fase 01
- Type: Docs
- Scope / fase: pencatatan penerimaan manual pemilik atas checklist Fase 01c (#1–#8) dan penutupan resmi Fase 01.
- Files touched:
  - `reports/feature-update/PHASE-01C-ISOLATION-FIXTURES.md`
  - `reports/feature-update/PHASE-01C-MANUAL-REVIEW.md`
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `CHANGELOG.md`
- What changed:
  1. Dicatat hasil penerimaan manual pemilik pada 17 Oktober 2026 WIB mengacu baseline `844883e`: checklist #1–#8 Fase 01c seluruhnya dinyatakan **APPROVED**.
  2. Fase 01 ditutup lengkap sesuai cakupan roadmap karena seluruh subfase 01a (authorization backend), 01b (konfigurasi API & error auth), dan 01c (isolasi fixture & bootstrap) telah diterima penuh oleh pemilik.
  3. Status penutupan BLK-03 ditegaskan: CLOSED sebatas source code aktif (akun dan data siswa riil dibersihkan dari seeder aktif; data pada riwayat commit Git historis masa lalu tidak di-rewrite/belum dihapus).
  4. Keputusan yang masih OPEN tetap dicatat: makna `status_user_id` (tidak disamakan dengan `status_active`), deployment CORS/Sanctum produksi, strategi pencabutan token dibanding sesi.
  5. Penegasan batasan: penerimaan manual ini bukan izin deployment ke produksi dan bukan penerimaan modul yang belum tersedia.
- Why: mendokumentasikan penerimaan manual penuh Fase 01c dan penutupan resmi Fase 01 sesuai instruksi pemilik.
- Validation: dokumentasi saja (pernyataan manual pemilik).
- Notes / risks: Fase 01 DITUTUP LENGKAP; Fase 02 NOT STARTED.
- Evidence: `reports/feature-update/PHASE-01C-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-01C-ISOLATION-FIXTURES.md`.

## [2026-10-17] Fase 01c — Docs: penyempurnaan panduan manual review dan konfigurasi lingkungan bootstrap disposable
- Type: Docs
- Scope / fase: penyempurnaan panduan pengujian manual UAT Fase 01c dan template konfigurasi database disposable khusus bootstrap.
- Files touched:
  - `laravel-api/.env.bootstrap.example` (baru)
  - `laravel-api/.gitignore`
  - `reports/feature-update/PHASE-01C-MANUAL-REVIEW.md`
  - `reports/feature-update/HANDOFF.md`
  - `CHANGELOG.md`
- What changed:
  1. Penyiapan template `.env.bootstrap.example` dan pendaftaran `.env.bootstrap` pada `.gitignore` untuk pengujian perintah bootstrap pada database disposable terisolasi.
  2. Penyempurnaan `PHASE-01C-MANUAL-REVIEW.md`:
     - Menegaskan aturan keselamatan: larangan terhadap database operasional `backdata_administrations`, perlindungan terhadap `school_access_uat` (dilarang migrate:fresh atau hapus admin existing).
     - Prosedur pre-flight verifikasi koneksi efektif dan nama database sebelum mutasi apa pun.
     - Panduan konkret dua skenario bootstrap: Skenario 3A (bootstrap berhasil pada DB disposable kosong) dan Skenario 3B (bootstrap ditolak saat admin aktif sudah ada pada DB yang sama).
     - Penegasan kerahasiaan kata sandi: tidak pernah dicetak atau disimpan ke dokumen/Git.
     - Pembedaan bukti otomatis agen dari penerimaan manual pemilik (seluruh checklist berstatus PENDING hingga diverifikasi mandiri oleh pemilik).
     - Penjelasan bahwa ESLint `--quiet` membuktikan 0 errors (bukan 0 warnings).
- Why: memastikan proses review manual mandiri oleh pemilik berjalan aman, terisolasi, dan tidak mengganggu data UAT maupun operasional sekolah.
- Validation: dokumentasi saja.
- Notes / risks: Status 01c tetap IMPLEMENTED & VERIFIED / MANUAL ACCEPTANCE PENDING; Fase 02 NOT STARTED.
- Evidence: `reports/feature-update/PHASE-01C-MANUAL-REVIEW.md`.

## [2026-10-17] Fase 01c — Fix: isolasi fixture, pembersihan data riil seeder, bootstrap admin CLI, dan validator akun
- Type: Fix, Security, Feature, Test, Docs
- Scope / fase: implementasi Fase 01c berdasarkan rencana `PHASE-01C-PLAN.md` dan keputusan pemilik `DEC-16` (Q-01c-1 s/d Q-01c-3).
- Files touched:
  - `laravel-api/config/filesystems.php`
  - `laravel-api/phpunit.xml`
  - `laravel-api/tests/CreatesApplication.php`
  - `laravel-api/app/Providers/AppServiceProvider.php`
  - `laravel-api/app/Http/Controllers/UserController.php`
  - `laravel-api/app/Console/Commands/BootstrapAdminCommand.php` (baru)
  - `laravel-api/database/factories/{StudentFactory.php, EmployeeFactory.php, UserFactory.php}`
  - `laravel-api/database/seeders/{ReferenceDataSeeder.php (baru), SyntheticDataSeeder.php (baru), DatabaseSeeder.php, StudentSeeder.php, StudentRombelSeeder.php, SchoolRombelSeeder.php, SchoolProfileSeeder.php, AccessControlSeeder.php, SchoolYearSeeder.php, SemesterSeeder.php, StatusUserSeeder.php, TypeUserSeeder.php, UatSeeder.php}`
  - `laravel-api/tests/Feature/Access/{StorageIsolationTest.php, FactoryIntegrityTest.php, BootstrapAdminTest.php, UserValidationTest.php, SeederIsolationTest.php}` (baru)
  - `vue-app/src/utils/useResourceManager.js`
  - `vue-app/src/views/pages/master/Accounts.vue`
  - `vue-app/src/views/pages/auth/Profile.vue`
  - `vue-app/src/views/pages/auth/ResetPassword.vue`
  - `vue-app/tests/phase-01b-api-auth.test.mjs`
  - `docs/{DATABASE.md, API-CONTRACTS.md}`
  - `reports/feature-update/{PHASE-01C-ISOLATION-FIXTURES.md (baru), PHASE-01C-MANUAL-REVIEW.md (baru), INDEX.md, HANDOFF.md}`
  - `roadmap/ROADMAP.md`
  - `CHANGELOG.md`
- What changed:
  1. Isolasi pengujian dan storage: fail-safe SQLite in-memory dipertahankan; ditambahkan disk testing terisolasi `storage/framework/testing/disks/testing` dan fail-safe `guardAgainstUnsafeStorage` yang membatalkan test bila mengarah ke storage operasional/publik.
  2. Factory terisi data sintetis valid: `StudentFactory` dan `EmployeeFactory` menghasilkan record dan relasi FK valid, NIK 16 digit string, dan nol awal dipertahankan.
  3. Pembersihan PII seeder (BLK-03 ditutup pada source aktif): akun riil dibersihkan dari `DatabaseSeeder.php`; ribuan baris data siswa dan NIK riil dibersihkan dari `StudentSeeder.php` dan `StudentRombelSeeder.php`. Riwayat commit Git historis masa lalu tidak diubah.
  4. Pemisahan seeder: `ReferenceDataSeeder` referensi murni tanpa akun/password bawaan, idempotent, dan tidak mereset izin admin; `SyntheticDataSeeder` menghasilkan 15 siswa dummy dengan fail-safe `guardEnvironment()` yang menolak production dan database operasional; `UatSeeder` memuat fail-safe dan password sintetis min 12 karakter.
  5. Perintah CLI bootstrap admin: `php artisan app:bootstrap-admin` interaktif dengan password diketik tersembunyi via secret prompt (`$this->secret()`) dan konfirmasi; menolak eksekusi bila pengelola akses aktif sudah ada; membuat akun dan menautkan peran `admin-sistem` secara atomik.
  6. Validasi akun: aturan keunikan `name` dihapus (nama boleh sama/homonim); NIK divalidasi tepat 16 digit string angka (`digits:16`); relasi FK divalidasi `exists`; format error 422 distandarisasi.
  7. Standar password 12 karakter: `Password::defaults()` disetel min 12 karakter pada `AppServiceProvider.php`; diterapkan konsisten pada pembuatan akun, bootstrap, form ubah password (`Profile.vue`), dan form reset password (`ResetPassword.vue`); akun lama tidak dipaksa mengubah kata sandi.
- Why: menutup risiko kebocoran data pribadi pada seeder, menyediakan mekanisme inisialisasi administrator aman, memperkuat isolasi test dan storage, dan menyelaraskan validasi akun sekolah sesuai roadmap dan keputusan pemilik.
- Validation:
  - Backend: 82 passed (346 assertions) di SQLite `:memory:` (`php artisan test`).
  - Pint: PASS (100% clean, 26 files).
  - Frontend test: 15 passed (0 failed) (`node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`).
  - Lint: 0 errors (`npx eslint ... --quiet`).
  - Build: PASS (`npm run build`).
  - Live Browser UAT: Dialog New Account (NIK 16 digit, password min 12 char, homonim nama diterima), form Profile ubah password min 12 char, form Reset Password min 12 char.
- Notes / risks: BLK-03 CLOSED pada source aktif; review manual pemilik Fase 01c PENDING (8 checklist); Fase 01a & 01b DITERIMA; seluruh Fase 01 menunggu penerimaan 01c.
- Evidence: `reports/feature-update/PHASE-01C-ISOLATION-FIXTURES.md`, `reports/feature-update/PHASE-01C-MANUAL-REVIEW.md`.

## [2026-10-17] Fase 01c — Docs: perencanaan isolasi pengujian, pemisahan fixture sintetis, dan penanganan BLK-03
- Type: Docs
- Scope / fase: perencanaan Fase 01c (isolasi test dan fixture sintetis) berdasarkan roadmap aktual.
- Files touched:
  - `reports/feature-update/PHASE-01C-PLAN.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `CHANGELOG.md`
- What changed:
  1. Audit proteksi DB/storage pengujian dan fixture sintetis: memetakan apa yang sudah ada (fail-safe SQLite in-memory, `DatabaseIsolationTest`, `CreatesAccessFixtures`, `UatSeeder`) dan gap yang tersisa (isolasi storage pengujian, factory scaffold kosong).
  2. Pemetaan penanganan BLK-03: pembersihan akun riil dari `DatabaseSeeder.php` dan data siswa riil dari seeder aktif pada commit aktif tanpa melakukan rewrite riwayat Git.
  3. Usulan arsitektur seeder: pemisahan seeder referensi murni (`ReferenceDataSeeder`), data dummy sintetis (`SyntheticDataSeeder`), dan mekanisme bootstrap akun administrator pertama tanpa kredensial bawaan hardcoded di Git.
  4. Pemetaan validator akun sesuai roadmap: penghapusan aturan unique pada `name` di `UserController.php` (karena nama tidak wajib unik), validasi NIK string 16 digit angka, standarisasi password dan validasi relasi foreign key.
  5. Perumusan kriteria penerimaan, metode verifikasi, dan identifikasi 3 keputusan pemilik yang diperlukan (Q-01c-1 s/d Q-01c-3).
- Why: menyusun rencana teknis menyeluruh sebelum pelaksanaan subfase 01c sesuai aturan roadmap.
- Validation: source review statis (tanpa eksekusi seeder atau perubahan kode runtime).
- Notes / risks: Implementasi 01c belum dimulai (menunggu review pemilik); BLK-03 tetap OPEN sampai 01c diimplementasikan.
- Evidence: `reports/feature-update/PHASE-01C-PLAN.md`.

## [2026-10-17] Fase 01b — Docs: penerimaan manual lengkap pemilik atas seluruh checklist 1–16
- Type: Docs
- Scope / fase: pencatatan penerimaan manual lengkap pemilik atas seluruh checklist UAT Fase 01b.
- Files touched:
  - `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`
  - `reports/feature-update/PHASE-01B-API-AUTH.md`
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `CHANGELOG.md`
- What changed:
  1. Dicatat hasil penerimaan manual pemilik:
     - Nomor 1–7 dan 9–16: APPROVED pada 16 Oktober 2026 WIB.
     - Nomor 8: retest mandiri berhasil dan APPROVED pada 17 Oktober 2026 WIB (berdasarkan pembuktian bahwa membuka form SPA bukan bukti token reusable; token dikonsumsi dan replay submit ditolak server dengan status 422).
     - Dengan demikian, checklist nomor 1–16 seluruhnya **APPROVED**.
  2. Fase 01b ditandai **DITERIMA PEMILIK** dalam scope checklist.
  3. Fase 01a tetap berstatus **DITERIMA**. Seluruh Fase 01 belum ditandai selesai karena subfase 01c belum dikerjakan.
  4. Penegasan batasan: BLK-03 dan keputusan autentikasi terbuka lain tetap **OPEN**; penerimaan ini bukan izin deployment atau penerimaan modul yang belum tersedia.
- Why: mendokumentasikan penerimaan manual penuh Fase 01b sesuai pernyataan pemilik sebagai sumber bukti resmi sebelum merencanakan subfase berikutnya.
- Validation: dokumentasi saja (pernyataan manual pemilik).
- Notes / risks: BLK-03 tetap OPEN (dialokasikan ke 01c); Fase 01c NOT STARTED.
- Evidence: `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-01B-API-AUTH.md` §8.

## [2026-10-16] Fase 01b — Test & Docs: investigasi bukti token reuse dan koreksi panduan manual UAT #8
- Type: Test, Docs, Security
- Scope / fase: investigasi laporan defect checklist #8 Fase 01b (link reset yang sudah digunakan dilaporkan masih dapat dibuka kembali).
- Files touched:
  - `laravel-api/tests/Feature/Auth/PasswordResetTest.php`
  - `vue-app/tests/phase-01b-api-auth.test.mjs`
  - `reports/feature-update/PHASE-01B-API-AUTH.md`
  - `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`
  - `reports/feature-update/HANDOFF.md`
  - `CHANGELOG.md`
- What changed:
  1. Reproduksi live UAT pada akun sintetis (`uat.waka@uat.local`):
     - Reset pertama disubmit dengan kata sandi A: berhasil 200 OK, kata sandi A aktif dan dapat login ke Dashboard.
     - Tautan bertoken yang sama dibuka kembali: form antarmuka klien SPA termuat (perilaku rute SPA klien).
     - Submit reset kedua dengan kata sandi B yang berbeda: server Laravel menolak dengan **HTTP 422 Unprocessable Content** (`Invalid Token: This password reset token is invalid.`).
     - Login dengan kata sandi B ditolak ("These credentials do not match our records."); kata sandi A tetap berlaku.
     - Kesimpulan: token dikonsumsi dan **tidak reusable**. Membuka link hanya memuat template SPA; penolakan token terjadi saat submit.
  2. Penguatan tes unit backend: `PasswordResetTest.php` menambahkan asersi verifikasi bahwa replay token yang sama dengan password kedua berbeda tidak mengubah hash password pengguna.
  3. Penambahan tes unit frontend: `phase-01b-api-auth.test.mjs` menguji pemetaan error 422 token invalid/expired/reused ke modal dialog English "Invalid Token".
  4. Koreksi panduan manual: `PHASE-01B-MANUAL-REVIEW.md` menambahkan subbab karakteristik SPA dan memperjelas langkah pengujian checklist #8 (penolakan diverifikasi saat submit).
- Why: membuktikan keabsahan konsumsi token, mencegah salah paham antara form termuat di SPA dengan token reusable, dan memberi panduan verifikasi yang tepat bagi pemilik.
- Validation:
  - Backend test: 60 passed (177 assertions) di SQLite in-memory (`php artisan test`).
  - Pint: PASS (100% clean).
  - Frontend test: 14 passed (`node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`).
  - Build: PASS (`npm run build`).
  - Lint: 0 errors (`npx eslint tests/phase-01b-api-auth.test.mjs`).
  - Playwright live UAT: reproduksi reset pertama (200) vs replay (422) + login A vs B terbukti.
- Notes / risks: Checklist #8 berstatus PENDING KONFIRMASI PEMILIK pasca penjelasan dan panduan terkoreksi; BLK-03 tetap OPEN.
- Evidence: `reports/feature-update/PHASE-01B-API-AUTH.md` §8; `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md` §2.D & §3 (#8).

## [2026-10-16] Fase 01b — Docs: pencatatan persetujuan manual terbatas pemilik (nomor 1–7 dan 9–16)
- Type: Docs
- Scope / fase: pencatatan hasil review manual pemilik atas checklist Fase 01b.
- Files touched:
  - `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`
  - `reports/feature-update/PHASE-01B-API-AUTH.md`
  - `reports/feature-update/HANDOFF.md`
  - `reports/feature-update/INDEX.md`
  - `roadmap/ROADMAP.md`
  - `CHANGELOG.md`
- What changed:
  1. Dicatat hasil manual review pemilik pada 16 Oktober 2026 WIB: nomor 1–7 dan 9–16 dinyatakan **APPROVED**.
  2. Nomor 8 dicatat **REPORTED** (masalah pada link reset password yang dilaporkan masih dapat dibuka kembali setelah digunakan; dalam investigasi apakah form hanya terbuka atau submit reset kedua benar-benar berhasil mengubah password).
  3. Status keseluruhan Fase 01b disesuaikan menjadi **PARTIAL** menunggu penyelesaian investigasi nomor 8.
- Why: mencatat persetujuan parsial pemilik secara terpisah dari commit implementasi/perbaikan sesuai aturan repo.
- Validation: dokumentasi saja.
- Notes / risks: Nomor 8 dalam proses reproduksi dan investigasi pada lingkungan UAT sintetis; BLK-03 tetap OPEN.
- Evidence: `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`.

## [2026-10-16] Fase 01b — Test & Docs: pelengkapan review bukti pemulihan error autentikasi dan audit akses master
- Type: Test, Docs, Security
- Scope / fase: pelengkapan review bukti Fase 01b (skenario pemulihan error auth, audit akses Operator ke `/master/typeusers`, regresi 4 peran browser, dan perapian panduan UAT).
- Files touched:
  - `laravel-api/tests/Feature/Access/TypeUserAccessTest.php` (baru)
  - `vue-app/src/stores/authStore.js`
  - `vue-app/tests/phase-01b-api-auth.test.mjs`
  - `vue-app/tests/auth-race-condition.test.mjs`
  - `reports/feature-update/PHASE-01B-API-AUTH.md`
  - `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`
  - `reports/feature-update/HANDOFF.md`
  - `reports/feature-update/INDEX.md`
  - `roadmap/ROADMAP.md`
  - `CHANGELOG.md`
- What changed:
  1. Pemetaan 6 skenario pemulihan & error auth diverifikasi lengkap:
     - Retry 419 berulang berhenti tanpa loop dan notifikasi security mismatch.
     - Refresh CSRF gagal ditangani tanpa auto-retry dan tanpa loop.
     - Beberapa request 419 bersamaan dikoordinasikan ke tepat 1 request refresh CSRF.
     - Timeout / network failure tidak menghapus sesi lokal.
     - Logout server gagal melaporkan `serverSuccess: false` dan membersihkan state lokal.
     - 401 bersamaan dideduplikasi dan sequence request monotonik global (`requestSequence`) mencegah respon usang menimpa sesi baru saat switch account.
  2. Audit akses Operator ke `/master/typeusers`:
     - Menjelaskan izin `master.manage` dan operasi CRUD backend.
     - Membuktikan secara struktural dan tes regresi bahwa pengubahan `type_users` tidak memengaruhi `roles` / `abilities`, tidak memicu pemetaan ulang berbasis nama, tipe terikat ditolak saat hapus (422), dan tidak membypass perlindungan admin terakhir.
  3. Regresi browser 4 peran diverifikasi penuh di UAT live (`uat.admin`, `uat.operator`, `uat.kepsek`, `uat.waka`): form login → refresh → navigasi izin → ekspor izin → logout bersih.
  4. Panduan manual `PHASE-01B-MANUAL-REVIEW.md` dirapikan: langkah sesi kedaluwarsa, kegagalan jaringan, offline logout, sanitasi URL log email reset, dan penjelasan modal dialog Session Expired.
- Why: memenuhi prasyarat pelengkapan bukti review Fase 01b sebelum peninjauan manual mandiri oleh pemilik.
- Validation:
  - Backend: 60 passed (173 assertions) di SQLite in-memory (`php artisan test`).
  - Pint: PASS (100% clean).
  - Frontend unit tests: 13 passed (`node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`).
  - Build: PASS (`npm run build`).
  - Lint: 0 errors, 1.086 Prettier line-ending warnings dilaporkan terpisah (`npx eslint ...`).
  - Playwright browser UAT: walkthrough 4 peran pada database sintetis `school_access_uat` PASS.
- Notes / risks: BLK-03 tetap OPEN (dialokasikan ke 01c); review manual pemilik PENDING (16 checklist).
- Evidence: `reports/feature-update/PHASE-01B-API-AUTH.md`, `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`.
- Type: Fix, Feature, Security, Test
- Scope / fase: implementasi Fase 01b berdasarkan audit 6a3d71f dan keputusan DEC-15 (Q1–Q3).
- Files touched: `laravel-api/.env.uat.example`, `laravel-api/tests/Feature/Auth/PasswordResetTest.php`, `docs/API-CONTRACTS.md`, `vue-app/src/apiLogin/index.js`, `vue-app/src/main.js`, `vue-app/src/stores/authStore.js`, `vue-app/src/router/index.js`, `vue-app/src/views/pages/auth/Login_2.vue`, `vue-app/src/views/pages/auth/Logout.vue`, `vue-app/src/views/pages/auth/Profile.vue`, `vue-app/src/layout/AppUsers.vue`, `vue-app/src/views/pages/auth/ForgotPassword.vue` (baru), `vue-app/src/views/pages/auth/ResetPassword.vue` (baru), `vue-app/tests/phase-01b-api-auth.test.mjs` (baru), `reports/feature-update/{PHASE-01B-API-AUTH.md,PHASE-01B-MANUAL-REVIEW.md,INDEX.md,HANDOFF.md}`, `roadmap/ROADMAP.md`, `CHANGELOG.md`.
- What changed:
  1. Interceptor dipindahkan ke instance `apiLogin` yang benar (timeout 15.000 ms, withCredentials, withXSRFToken).
  2. Pemulihan CSRF 419 terkoordinasi (single retry setelah penyegaran cookie, koordinasi request bersamaan, payload utuh, tanpa rekursi).
  3. Penanganan sesi aktif kedaluwarsa (401 non-auth) mengalihkan ke `/auth/login` dengan modal banner persisten; request bersamaan dideduplikasi.
  4. Penolakan izin fitur (403) mempertahankan sesi; akun nonaktif mengalihkan ke login.
  5. Router guard dievaluasi pada setiap navigasi langsung maupun antar-child (`router.beforeEach`), memetakan kemampuan per halaman secara eksplisit.
  6. Logout disatukan ke `authStore.logOut()` tunggal (`POST /logout` 204 tanpa membaca body), status server dilaporkan akurat.
  7. Form Forgot Password dan Reset Password end-to-end dibangun (anti-enumerasi, validasi token, broker Laravel, redirect login); UAT memakai `MAIL_MAILER=log`.
  8. Penyelarasan bahasa UI English pada kontrol tersentuh (`Profile.vue`, `Logout.vue`).
- Why: menutup gap konfigurasi API, mencegah kegagalan sesi diam-diam, menutup celah navigasi child, dan melengkapi alur reset password end-to-end sesuai keputusan pemilik.
- Validation:
  - Backend: 55 passed (149 assertions) di SQLite in-memory (`php artisan test`).
  - Pint: PASS (100% clean).
  - Frontend: 10 passed (`node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs`).
  - Build: PASS (`npm run build`).
  - Lint: 0 errors (`npx eslint ... --quiet`).
  - Playwright UAT: form login, child guard, CSRF 419 recovery nyata, session expired redirect, forgot/reset password end-to-end via email log, logout 204.
- Notes / risks: BLK-03 tetap OPEN (Fase 01c); review pemilik PENDING.
- Evidence: `reports/feature-update/PHASE-01B-API-AUTH.md`, `reports/feature-update/PHASE-01B-MANUAL-REVIEW.md`.

## [2026-10-15] Fase 01b — Docs: audit konfigurasi API dan alur error autentikasi
- Type: Docs
- Scope / fase: audit kondisi aktual dan penyusunan rencana implementasi Fase 01b (implementasi BELUM dijalankan).
- Files touched: `reports/feature-update/{PHASE-01B-API-AUTH-AUDIT.md (baru),INDEX.md,HANDOFF.md}`, `roadmap/ROADMAP.md`, `CHANGELOG.md`.
- What changed:
  1. Audit kondisi aktual pasca-01a: instance axios tunggal `apiLogin` (baseURL dari env, withCredentials/withXSRFToken aktif, tanpa URL hardcode tersisa); ditemukan bahwa response interceptor terdaftar pada global `axios`, bukan instance `apiLogin` → tidak berjalan.
  2. Audit router: `beforeEnter` parent `/` tidak dievaluasi saat navigasi antar-child; gating `/master` (`can('users.view')`) tidak dicek ulang.
  3. Audit error auth: 401/403/419/422/429/5xx/timeout belum ditangani seragam; `Logout.vue` memiliki risiko bug (akses `response.data.id` pada 204, memanggil logout dua kali, helper tak terdefinisi).
  4. Audit kontrak auth: strategi aktif = cookie session Sanctum saja (tanpa bearer); konfigurasi CORS/Sanctum UAT memadai untuk pengujian lokal; tidak perlu mengubah strategi token/sesi.
  5. Audit reset password: backend lengkap (routes+controller+broker+notification), frontend gap (link placeholder tanpa route/view/pemanggilan API). Disusun opsi minimum (Q1–Q4) untuk pemilik.
  6. Rencana implementasi C-01..C-06 dan rencana verifikasi menyeluruh disusun; keputusan penghalang diidentifikasi (Q1: apakah reset password masuk 01b).
  7. Klarifikasi CHANGELOG: entri pemilik ("Pengembangan fitur iteratif via sesi Claude") tidak ditemukan pada working tree maupun history yang diperiksa; penyebab kehilangan belum diketahui dan tidak direkonstruksi tanpa sumber.
  8. Status: 01a tetap DITERIMA pemilik; 01b berstatus audit/rencana (belum implementasi); BLK-03 tetap OPEN dan dialokasikan ke 01c.
- Why: memenuhi prasyarat perencanaan subfase 01b sebelum pelaksanaan kode, sesuai aturan roadmap.
- Validation: source review statis (tidak ada kode/config/data yang diubah; tidak menjalankan test/build/browser pada langkah ini).
- Notes / risks: Implementasi **belum** dimulai; keputusan Q1 pemilik menentukan cakupan reset password; BLK-03 tetap OPEN; tidak ada email reset nyata.
- Evidence: reports/feature-update/PHASE-01B-API-AUTH-AUDIT.md.

## [2026-10-15] Fase 01a — Docs: penerimaan manual pemilik (akses & ekspor) dan penutupan review 01a
- Type: Docs
- Scope / fase: pencatatan penerimaan manual pemilik atas pengujian tambahan akses & ekspor Fase 01a dan penutupan review 01a.
- Files touched: reports/feature-update/{PHASE-01A-ACCESS.md,PHASE-01A-MANUAL-REVIEW.md,INDEX.md,HANDOFF.md}, roadmap/ROADMAP.md, CHANGELOG.md.
- What changed:
  1. Dicatat persetujuan manual pemilik (15 Oktober 2026 WIB, mengacu implementasi `bea4ccd`) atas 8 butir pengujian tambahan: matriks Roles & Abilities English; admin menambah & menyimpan `students.export` untuk Waka; izin tambahan berlaku di sesi Waka berjalan (ekspor berhasil); pencabutan izin berlaku pada request berikutnya (ekspor ditolak 403); ekspor Operator/Principal sesuai filter tahun ajaran dengan nol awal identitas utuh; ekspor melebihi batas ditolak 422 English tanpa CSV terpotong; interaksi topbar tanpa error/warning; UI scope 01a English.
  2. Butir pengujian tambahan dipetakan ke checklist panduan **berdasarkan isi pemeriksaan**, bukan menyamakan nomor (paparan di `PHASE-01A-MANUAL-REVIEW.md` §6 dan `PHASE-01A-ACCESS.md` §22b).
  3. Ditambahkan `PHASE-01A-ACCESS.md` §22; §21c disesuaikan agar tidak lagi menyebut checklist sebagai tertunda (ditutup pada §22).
  4. Seluruh nomor checklist panduan #1–#14 kini APPROVED; Fase 01a ditandai **DITERIMA** dalam scope checklist. INDEX, HANDOFF, dan ROADMAP disinkronkan.
- Why: menutup review Fase 01a sesuai bukti persetujuan manual pemilik, tanpa memperluas ke subfase lain/deployment.
- Validation: dokumentasi saja (tidak ada perubahan kode). Tidak mengulang tes/build sesuai instruksi pemilik.
- Notes / risks: Penerimaan 01a **bukan** penerimaan seluruh Fase 01 (01b/01c NOT STARTED) dan bukan izin deployment/modul belum tersedia. **BLK-03** dan keputusan autentikasi lain tetap **OPEN**.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md §22; reports/feature-update/PHASE-01A-MANUAL-REVIEW.md §4/§6.

## [2026-10-15] Fase 01a — Docs: persetujuan manual pemilik atas retest login
- Type: Docs
- Scope / fase: pencatatan persetujuan manual pemilik atas retest login Fase 01a nomor 1–7.
- Files touched: reports/feature-update/{PHASE-01A-ACCESS.md,PHASE-01A-MANUAL-REVIEW.md,INDEX.md,HANDOFF.md}, roadmap/ROADMAP.md, CHANGELOG.md.
- What changed:
  1. Dicatat persetujuan manual pemilik (15 Oktober 2026 WIB, mengacu implementasi `5f4308d`) untuk retest login: Operator login langsung ke Dashboard tanpa refresh/error console; refresh mempertahankan sesi; profil Operator menampilkan identitas & peran; logout kembali ke halaman login; Admin login dengan menu & widget pengguna sesuai; akun nonaktif ditolak dengan pesan English; password salah ditolak dengan pesan English.
  2. Ditambahkan bagian §21 pada `PHASE-01A-ACCESS.md` yang membedakan **bukti manual pemilik** dari **verifikasi otomatis agen**, menegaskan batas persetujuan (hanya alur login/sesi), dan mendaftar checklist yang masih tertunda.
  3. Panduan review manual `PHASE-01A-MANUAL-REVIEW.md` diberi kolom status (APPROVED/PENDING) per nomor.
  4. INDEX, HANDOFF, dan ROADMAP diperbarui ke status penerimaan parsial (login APPROVED; izin/ekspor PENDING).
- Why: mencatat bukti persetujuan manual pemilik secara akurat tanpa memperluasnya ke seluruh Fase 01a.
- Validation: dokumentasi saja (tidak ada perubahan kode). Tidak mengulang tes/build sesuai instruksi pemilik.
- Notes / risks: Persetujuan **terbatas** pada alur login; kontrol izin, ekspor, batas ekspor, dan modul lain **tetap PENDING**. **BLK-03** dan keputusan autentikasi lain tetap **OPEN**.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md §21; reports/feature-update/PHASE-01A-MANUAL-REVIEW.md §4/§6.

## [2026-10-15] Fase 01a — Fix: inisialisasi identitas pasca-login (Login_2.vue & authStore)
- Type: Fix, Security, Test
- Scope / fase: koreksi inisialisasi identitas pasca-login pada Fase 01a tanpa reload/polling/penambahan izin.
- Files touched:
  - `vue-app/src/stores/authStore.js`
  - `vue-app/src/views/pages/auth/Login_2.vue`
  - `vue-app/tests/auth-race-condition.test.mjs`
  - `laravel-api/app/Http/Requests/Auth/LoginRequest.php`
  - `laravel-api/app/Http/Middleware/EnsureUserIsActive.php`
  - `laravel-api/tests/Feature/Auth/AuthenticationTest.php`
  - `.gitignore`
  - `reports/feature-update/{PHASE-01A-ACCESS.md,PHASE-01A-MANUAL-REVIEW.md,INDEX.md,HANDOFF.md}`
  - `roadmap/ROADMAP.md`
  - `CHANGELOG.md`
- What changed:
  1. Investigasi & pembuktian akar masalah: `Login_2.vue` mengakses properti usang `authStore().authUser` yang telah dihapus pada refactor sebelumnya, menyebabkan pelemparan exception "User data not found." dan modal "Failed to validate user session." padahal backend telah sukses mengautentikasi (sehingga refresh manual berhasil masuk).
  2. `authStore.js`: menambahkan getter kompatibilitas `authUser` dan `AllUserAssign` yang konsisten merujuk ke `identity`.
  3. `authStore.js`: menambahkan sequence tracker `_requestId` pada `fetchIdentity()` untuk mendiskualifikasi respons in-flight guest check yang datang terlambat agar tidak menimpa sesi login aktif (anti-race condition deterministik). `fetchIdentity()`, `getUser()`, dan `checkLogin()` mengembalikan data `identity`.
  4. `Login_2.vue`: mengintegrasikan pencegahan submit ganda (`isSubmitting` guard + disabled/loading button), explicit boolean dialog flags, dan `await useAuthStore.fetchIdentity()` sebelum navigasi.
  5. Pesan error English per jenis kegagalan: koneksi API server, kredensial salah (422), akun nonaktif (422), dan sesi tidak valid. Pesan nonaktif di `LoginRequest.php` dan `EnsureUserIsActive.php` diselaraskan ke English.
- Why: memastikan pengguna dapat langsung login ke beranda tanpa perlu refresh manual, bebas race condition, dan tanpa memanggil endpoint terproteksi `/api/users`.
- Validation:
  - `php artisan test` backend: 51 passed (131 assertions) di SQLite in-memory terisolasi (2 tes baru).
  - Pint style check: PASS pada seluruh file PHP yang diubah.
  - `node --test vue-app/tests/auth-race-condition.test.mjs`: 3 passed (race condition & getter consistency).
  - `npm run build`: PASS (`✓ built in 42s`).
  - `npx eslint`: 0 errors pada berkas yang disentuh.
  - Playwright browser UAT sintetis: Form login Operator, Admin, Principal, Wakil Kesiswaan langsung berhasil ke Dashboard tanpa refresh; refresh menjaga sesi; logout dan switch account bersih; password salah menampilkan error 422 English; akun nonaktif menampilkan error 422 English. Request `/api/users` pada non-admin = 0.
- Notes / risks: BLK-03 tetap OPEN (`DatabaseSeeder` dilarang); penerimaan manual pemilik PENDING.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md §20; `vue-app/tests/auth-race-condition.test.mjs`.

## [2026-10-15] Fase 01a — Fix: pemisahan identitas pengguna dari administrasi akun
- Type: Fix, Security, Refactor
- Scope / fase: perbaikan defect UAT akun operator pada Fase 01a.
- Files touched: vue-app/src/stores/authStore.js, vue-app/src/components/dashboard/ListUsersOnlineWidget.vue, vue-app/src/layout/AppUsers.vue, vue-app/src/layout/AppMenu.vue, vue-app/src/router/index.js, vue-app/src/apiLogin/index.js, vue-app/src/main.js, vue-app/src/utils/useResourceManager.js, vue-app/src/views/pages/auth/Profile.vue, vue-app/src/views/pages/auth/Login_2.vue, vue-app/package.json, reports/feature-update/{PHASE-01A-ACCESS.md,PHASE-01A-MANUAL-REVIEW.md,INDEX.md,HANDOFF.md}, roadmap/ROADMAP.md.
- What changed:
  1. Identitas sesi dialihkan ke kontrak ringan `GET /api/check-auth` (`authStore.fetchIdentity()`); menghapus pemanggilan `GET /api/users` untuk mencari profil sendiri.
  2. `ListUsersOnlineWidget.vue` diproteksi `v-if="canViewUsers"`, tidak memanggil `/api/users` jika operator tidak memiliki `users.view`; error ditangani tanpa unhandled promise.
  3. `AppUsers.vue` membaca nama dan peran sesi langsung dari store identitas; logout berbasis sesi backend `POST /logout` tanpa ketergantungan ID dari daftar pengguna.
  4. State sesi dibersihkan saat logout (`$reset()`) untuk mencegah kebocoran state antar-akun.
  5. `AppMenu.vue` beralih ke gating kemampuan bernama (`requires`), menghapus pemeriksaan numerik inline `type_user_id`.
  6. `main.js` meregistrasikan directive PrimeVue `StyleClass` (`v-styleclass` tanpa warning).
  7. `apiLogin/index.js` menghapus fallback IP lama; error konfigurasi English jika `VITE_API_BASE_URL` hilang/tidak valid; membedakan network error dari session expired.
  8. `Login_2.vue` membedakan network error dari invalid credentials (422).
  9. `package.json` menambahkan script `"dev:uat"`.
- Why: memperbaiki defect console error 403, TypeError null render, dan failure logout pada operator saat UAT, tanpa mengorbankan keamanan backend.
- Validation: automated test 49 passed (119 assertions); frontend build PASS; lint changed files 0 error; UAT browser walkthrough pada 4 peran (Operator, Admin, Principal, Waka) PASS dengan 0 console error dan 0 `/api/users` calls pada non-admin; topbar buttons PASS tanpa directive warning.
- Notes / risks: BLK-03 tetap OPEN; penerimaan manual pemilik PENDING.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md §19; reports/feature-update/evidence/{uat-operator-dashboard.png, uat-operator-profile-dropdown.png}.

## [2026-10-15] Fase 01a — Koreksi akhir (tanggal DEC-14, batas ekspor, daftar file)
- Type: Fix, Docs
- Scope / fase: koreksi terbatas sebelum penerimaan pemilik Fase 01a.
- Files touched: docs/{DECISIONS,ACCESS-CONTROL,API-CONTRACTS}.md; PROJECT_CONTEXT.md; roadmap/ROADMAP.md; CHANGELOG.md; reports/feature-update/{PHASE-01A-ACCESS.md,INDEX.md,HANDOFF.md}; laravel-api/{config/students.php (baru),app/Http/Controllers/StudentExportController.php,database/migrations/2029_07_20_000003_seed_and_map_roles.php (komentar),tests/Feature/Access/StudentExportTest.php}; vue-app/src/views/pages/public/Student.vue.
- What changed: (1) tanggal persetujuan DEC-14 dikoreksi menjadi **2026-10-14 WIB** secara konsisten (tidak mengubah tanggal/identifier migration); (2) batas ekspor 10.000 baris diubah dari potong senyap → **tolak 422** dengan pesan English (jumlah baris, batas, ajakan persempit filter) via `config('students.export_max_rows')` (`config/students.php`, env `STUDENTS_EXPORT_MAX_ROWS`); `Student.vue` menampilkan pesan 422 backend; dokumentasi "seluruh hasil filter" diperjelas (berhasil hanya bila ≤ batas); (3) daftar file berubah dirapikan (modified vs untracked), `StudentExportTest.php` dikoreksi sebagai untracked.
- Why: memenuhi koreksi akhir atas permintaan pemilik sebelum penerimaan Fase 01a; menghindari pemotongan data diam-diam.
- Validation: `php artisan test` → **49 passed (119 assertions)** (2 tes batas baru); `./vendor/bin/pint --test` PASS (termasuk config/students.php); `npm run build` PASS; HTTP UAT (limit=2, 3 baris) → 422 pesan English (DB `school_access_uat`, sintetis). Tidak mengulang seluruh uji browser. NOT RUN: modul surat/izin/pembelajaran, migrasi/backup DB operasional.
- Notes / risks: BLK-03 tetap OPEN. Persetujuan DEC-14 = kebijakan, bukan penerimaan manual menyeluruh. Tidak commit/push; edisi pemilik pada CHANGELOG.md dipertahankan.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md §18.

## [2026-10-15] Fase 01a — Penyelesaian: ekspor siswa, pemetaan legacy, UI English (DEC-14 disetujui 2026-10-14 WIB)
- Type: Feature, Security, Fix, Docs, Test
- Scope / fase: penyelesaian Fase 01a sesuai keputusan pemilik DEC-14 (disetujui 2026-10-14 WIB).
- Files touched: docs/DECISIONS.md (DEC-14 APPROVED); docs/{API-CONTRACTS,ACCESS-CONTROL,REQUIREMENTS,DATABASE}.md; PROJECT_CONTEXT.md; roadmap/ROADMAP.md; CHANGELOG.md; reports/feature-update/{PHASE-01A-ACCESS.md,INDEX.md,HANDOFF.md,evidence/*}; laravel-api/app/Http/Controllers/StudentExportController.php (baru); laravel-api/routes/api.php; laravel-api/app/Support/AbilityCatalog.php; laravel-api/database/migrations/2029_07_20_000003_seed_and_map_roles.php; laravel-api/database/seeders/UatSeeder.php; laravel-api/tests/Feature/Access/{StudentExportTest.php (baru),AuthorizationTest.php}; laravel-api/tests/Support/CreatesAccessFixtures.php; vue-app/src/views/pages/master/Roles.vue; vue-app/src/views/pages/master/Accounts.vue; vue-app/src/views/pages/public/Student.vue; vue-app/src/stores/authStore.js.
- What changed: (1) DEC-14 APPROVED 2026-10-14 WIB (register ditutup, akun nonaktif ditolak, ekspor via `students.export`, legacy `Admin`→Admin Sistem & `User` tidak otomatis, UI English); (2) endpoint `GET /api/students/export` (auth+active+students.view+students.export) dengan kolom eksplisit, filter sama dengan daftar, leading zero/CSV escaping/neutralisasi formula; `Student.vue` unduh via endpoint, tombol ikut izin; (3) migration legacy dikoreksi (hanya Admin; `down()` tidak menghapus penetapan manual); (4) UI English di Roles/Accounts(+Assign Roles dialog)/Student, nama peran English, `authStore` abilities+`can()`; (5) `authStore.getUser` null-safe; 3 warning formatting Roles.vue dirapikan.
- Why: menyelesaikan Fase 01a sesuai keputusan pemilik dan menutup keterbatasan ekspor.
- Validation: `php artisan test` → **49 passed (119 assertions)** (DB uji SQLite in-memory; 2 tes batas ditambahkan pada koreksi akhir); `./vendor/bin/pint --test` PASS; `npm run build` PASS; `npx eslint Roles.vue` 0 problems; UAT HTTP+Playwright PASS (ekspor 200 kolom benar & 403 untuk waka & 422 batas, unduhan CSV via endpoint, tombol per izin, UI English, audit tanpa rahasia). NOT RUN: modul surat/izin/pembelajaran, migrasi/backup DB operasional.
- Notes / risks: BLK-03 (seeder `DatabaseSeeder` identitas asli) TETAP; `status_user_id` belum ditegakkan; keputusan autentikasi lain OPEN. Tidak commit/push; edisi pemilik pada CHANGELOG.md dipertahankan.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md §11–16; reports/feature-update/evidence/{uat-roles-en.png,uat-students-export.csv}.

## [2026-10-15] Fase 01a — Verifikasi review + lingkungan UAT terisolasi
- Type: Docs, Test, Chore
- Scope / fase: Fase 01a (persiapan & verifikasi review; tanpa fase berikutnya).
- Files touched: reports/feature-update/{PHASE-01A-ACCESS.md,INDEX.md,HANDOFF.md,evidence/uat-roles.png}; docs/{DECISIONS,ACCESS-CONTROL,TESTING,API-CONTRACTS}.md; PROJECT_CONTEXT.md; roadmap/ROADMAP.md; CHANGELOG.md; laravel-api/{.env.uat.example,database/seeders/UatSeeder.php,.gitignore}; vue-app/{.env.uat(lokal),src/apiLogin/index.js,.gitignore}. Tidak ada perubahan pada seeder identitas asli atau modul fitur berikutnya.
- What changed: (1) bukti laporan dilengkapi (nama/cakupan tes, pemisahan tes katalog vs endpoint, 3 warning ESLint, pemetaan legacy per nama, register/nonaktif sebagai implementasi menunggu keputusan, status_active≠status_user_id); (2) lingkungan UAT terisolasi (MySQL `school_access_uat` + UatSeeder sintetis + `VITE_API_BASE_URL`); (3) verifikasi Playwright + HTTP (sesi aktif, penolakan 403, akun nonaktif, check-auth minimum, admin tanpa bypass, audit tanpa rahasia).
- Why: menyiapkan & memverifikasi review Fase 01a pada lingkungan terisolasi tanpa menyentuh data operasional.
- Validation: lingkungan UAT efektif diverifikasi (mysql/school_access_uat) sebelum migrasi; migrasi hanya di DB UAT. Playwright PASS (roles + matriks + tambah students.export). HTTP PASS (propagasi izin sesi aktif, 403 wakil, 422 nonaktif, 403 sesi dinonaktifkan, check-auth minimum, audit 0 leak). `php artisan test` tetap 34 passed. Guard DB tes tidak dikendurkan. NOT RUN: modul surat/izin/pembelajaran (belum ada), migrasi/backup pada DB operasional.
- Notes / risks: `students.export` belum punya endpoint backend (ekspor klien di Student.vue) — tidak diklaim terlindungi. Register/nonaktif = implementasi menunggu konfirmasi pemilik. BLK-03 (seeder identitas asli) tetap. Tidak commit/push; edisi pemilik pada CHANGELOG.md dipertahankan.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md (revisi §3–§5, §4b–§4e); reports/feature-update/evidence/uat-roles.png.

## [2026-10-15] Fase 01a — Peran/kemampuan dan penegakan akses backend
- Type: Feature, Security, Schema Change
- Scope / fase: Fase 01a (authorization backend) + isolasi DB uji (BLK-01) + DEC-03.
- Files touched: migration 2029_07_20_00000{1,2,3}; app/Support/AbilityCatalog.php; app/Providers/AccessServiceProvider.php; app/Http/Middleware/EnsureUserIsActive.php; app/Models/{Role,Ability,User}.php; app/Http/Controllers/{RoleController,AbilityController,AccessAssignmentController,UserController,FirstLoginController}.php; app/Http/Requests/Auth/LoginRequest.php; app/Traits/Loggable.php; routes/{api,auth}.php; config/app.php; Laravel Kernel.php; database/{seeders/AccessControlSeeder.php,factories/UserFactory.php,seeders/DatabaseSeeder.php}; phpunit.xml; .env.testing; tests/*; vue-app/src/views/pages/master/Roles.vue; vue-app/src/router/index.js; vue-app/src/layout/AppMenu.vue; docs/*; PROJECT_CONTEXT.md; roadmap/ROADMAP.md; reports/*; CHANGELOG.md.
- What changed: peran/kemampuan fleksibel (roles/abilities/ability_role/role_user + katalog + UI `/master/roles`); Gate bernama terpusat tanpa bypass admin; middleware `active`; endpoint dilindungi `can:`; FirstLogin anti-IDOR; self-update tidak bisa eskalasi atribut; proteksi admin pengelola akses terakhir; `check-auth` minimal; register publik ditutup; akun nonaktif ditolak; audit Loggable tanpa password/token; DB uji terisolasi SQLite `:memory:` + fail-safe.
- Why: menutup BLK-01/BLK-02 (sebagian) dan menegakkan FR-01/ACCESS-CONTROL sesuai DEC-03.
- Validation: `php artisan test` → **34 passed (79 assertions)** di DB uji SQLite in-memory; guard isolasi terverifikasi memblokir mysql operasional; `npm run build` PASS; `./vendor/bin/pint --test` 27 file PASS; `npx eslint Roles.vue` 0 error (3 warning format). NOT RUN: browser/Playwright, migrasi pada DB operasional.
- Notes / risks: BLK-03 seeder identitas asli belum diperbaiki; `status_user_id` belum ditegakkan; kemampuan modul belum dibangun hanya katalog; SQLite belum menguji concurrency/unique MySQL. Perubahan pemilik pada CHANGELOG.md dipertahankan. Tidak commit/push.
- Evidence: reports/feature-update/PHASE-01A-ACCESS.md; HEAD b699fec (working tree berubah).

## [2026-10-15] Fase 00 — Audit baseline dan kesiapan lingkungan (read-only)
- Type: Docs
- Scope / fase: Fase 00 (00a inventory source + 00b kesiapan lingkungan).
- Files touched: reports/feature-update/PHASE-00-RUNTIME-AUDIT.md (baru); CHANGELOG.md, roadmap/ROADMAP.md, reports/feature-update/INDEX.md, reports/feature-update/HANDOFF.md (pembaruan). Tidak ada kode/schema/data diubah.
- What changed: Inventaris route API, controller/request/policy, consumer Vue, migration/schema, akses, seed/factory dan runtime; audit baseline HEAD `b699fec` (delta dari `b02a87f` = paket docs saja).
- Why: Menutup syarat keluar subfase 00 dan menyiapkan keputusan 01a.
- Validation: read-only. Dijalankan: git rev-parse/status/diff --stat, baca source, php -v (8.1.25), composer -V (2.8.2), node -v (24.18.0), npm -v (11.4.2), git --version, Test-NetConnection:3306 (True). NOT RUN: php artisan test, migrate, seed, npm run build, lint, browser/manual, akses API/data hardcode.
- Notes / risks: BLK-01 isolasi DB test belum ada (phpunit.xml sqlite dikomentari; .env = mysql `backdata_administrations`) → test DB diblokir. BLK-02 enforcement akses backend tidak ada (policy scaffold). BLK-03 seeder memuat identitas pribadi. DEC-03 OPEN menghambat 01a. Temuan baseline B-01..B-17 terverifikasi ulang pada HEAD.
- Evidence: reports/feature-update/PHASE-00-RUNTIME-AUDIT.md; HEAD b699fec, working tree `M CHANGELOG.md`.

## [2026-10-14] Paket dokumentasi pengembangan OpenCode disiapkan
- Type: Docs
- Scope: aturan kerja, kebutuhan, arsitektur, schema baseline/usulan, roadmap 14 fase, prompt dan template evidence.
- Files touched: berkas Markdown dalam paket ini; belum diterapkan/commit/push ke repo oleh penyusun.
- What changed: dokumentasi disiapkan sebagai paket untuk dipasang dan ditinjau pemilik.
- Why: mendukung pengembangan fitur bertahap melalui OpenCode berdasarkan tujuan operator sekolah.
- Validation: source review pada baseline b02a87f; pemeriksaan struktur dan tautan paket. Runtime/test/build/browser NOT RUN.
- Notes / risks: target fitur dan role baru merupakan usulan; implementasi belum dimulai. Sumber App Gudang dipakai sebagai pola proses, bukan teknologi atau hasil QA.
- Evidence: reports/feature-update/PHASE-00-SOURCE-BASELINE.md.
