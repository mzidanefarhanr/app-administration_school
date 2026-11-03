# Laporan Subfase 09b — Retensi, Akses Aman, dan Pengingat Arsip

Tanggal: implementasi dan verifikasi agen 2026-11-03 WIB. Branch: `main`. Baseline induk: `b405fe7` (pencatatan DEC-38 APPROVED di atas paket rencana `e89a682` dan baseline 09a `cad8f75`); SHA paket review dilaporkan setelah commit/push (tanpa klaim SHA masa depan). Status teknis: **IMPLEMENTED — agent-verified (2026-11-03)**. Status review pemilik: **PENDING (#1–#18)** (`reports/feature-update/PHASE-09B-MANUAL-REVIEW.md`).
Keputusan pemilik: **DEC-38 APPROVED** (D1–D11, 2026-11-03 WIB; pernyataan **"Saya setuju D1-D11"**); **D6 = Opsi A** (laporan retensi read-only + `app:archives-retention --dry-run`, ability `archives.purge` didefinisikan tanpa penugasan role, **tanpa eksekusi purge/pemusnahan data**); **DEC-13 PARTIALLY CLOSED untuk lingkup modul arsip dokumen** (sisa DEC-13 tetap OPEN untuk Fase 13); **DEC-35/DEC-36/DEC-37 tidak berubah**.
Otorisasi: implementasi **09b** sesuai `reports/feature-update/PHASE-09B-PLAN.md` (S0–S8). 09a tetap **OWNER APPROVED (#1–#18, 2026-11-03 WIB)**; 08a/08b tetap **OWNER APPROVED**.

## 1. Outcome dan cakupan

Subfase 09b melengkapi modul arsip dokumen terpusat sesuai keputusan D1–D11:

- **Preview inline aman (D2).** `GET /api/archives/{archive}/versions/{version}/preview` mengalirkan file privat dari disk `local` (`attachments/archives/{document_id}/{uuid}.{ext}`) menggunakan aturan otorisasi yang sama dengan `download` (`archives.view` + ability domain subjek bila bertaut). Header mengikuti pola terbukti 07c: `Content-Type` dari kolom `mime_type` tersimpan (hasil deteksi isi saat unggah), `Content-Length`, `X-Document-Filename`, `Access-Control-Expose-Headers: X-Document-Filename`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, `Content-Security-Policy: sandbox`, **tanpa `Content-Disposition`**, tanpa URL publik/signed URL, tanpa file sementara di disk publik, dan tanpa watermark/dependency baru.
- **Verifikasi integritas SHA-256 saat baca (D2.3 / D3.3).** Setiap pembacaan file (`download` maupun `preview`) memverifikasi terlebih dahulu keberadaan file fisik (`404` bila hilang) dan kecocokan hash SHA-256 terhadap kolom internal `checksum_sha256` (`500` dengan pesan umum bila tidak cocok, tanpa membocorkan path atau hash). Pengecekan keterbacaan dan integritas dijalankan **sebelum** pencatatan audit akses sehingga percobaan pada file hilang (`404`) atau korup (`500`) tidak menulis baris log akses palsu dan tidak mengirimkan byte file.
- **Audit akses append-only `archive_access_logs` (D3).** Tabel baru `archive_access_logs` merekam setiap `download` dan `preview` yang lolos otorisasi + verifikasi integritas file, tanpa mengubah enum tetap `activities.information`. Kolom yang disimpan hanya `archive_document_id`, `archive_file_version_id`, `version_number`, `user_id`, `action` (`download`/`preview`), `ip_address`, `user_agent` (dipotong ≤255), dan `created_at` (tanpa judul, nomor dokumen, nama file, path, atau checksum). Model `ArchiveAccessLog` menolak `updating`/`deleting` dan tidak memakai `Loggable`. Penulisan bersifat **fail-closed**: bila insert log gagal, `ArchiveAccessLogException` menghasilkan `500` dan byte file tidak dikirim. Pembacaan `GET /api/archives/{archive}/access-logs` memerlukan `audit.view` + `archives.view` + ability domain subjek (dokumen tersembunyi → `404`; tanpa `audit.view` → `403`).
- **Tugas masa berlaku di dashboard (D4).** `GET /api/archives/tasks/expiring` menghitung secara on-read (WIB `Asia/Jakarta`, ambang default 30 hari dari `config('archives.expiring_soon_days')`) dokumen **aktif** (`archived_at IS NULL`) yang berstatus `expired` atau `expiring_soon` sesuai visibilitas domain aktor; dokumen `upcoming` (`valid_from` di masa depan), `valid`, `none`, dan `archived` dikecualikan. Respons memuat `summary {expired_count, expiring_soon_count, total_attention_count, threshold_days, today}` dan `items[]` (urut `expired` dahulu lalu `valid_until asc, id asc`, disertai `days_remaining` bertanda). Komponen baru `ArchiveExpiryWidget.vue` dirender pada baris teratas `Dashboard.vue` hanya bila aktor memiliki `archives.view`, dengan tautan langsung ke `/public/archives?validity=expired` atau `expiring_soon` yang langsung menerapkan filter query di `Archives.vue`. Tidak ada tabel tugas baru, job latar, atau notifikasi email/WhatsApp/push.
- **Arsip administratif & pemulihan dokumen (D5).** Migration additive menambahkan `archived_at`, `archive_reason`, dan `archived_by` pada `archive_documents` (tanpa soft delete / `deleted_at`). `POST /api/archives/{archive}/archive` dan `POST /api/archives/{archive}/restore` memerlukan `archives.manage` + ability domain subjek, `reason` wajib 10–255 karakter, dan `expected_version` wajib (`409` dengan payload `latest` bila versi basi). Transisi dijalankan di dalam transaksi + `lockForUpdate()`, menaikkan `version`, dan hanya mencatat `archived_at` pada allowlist audit `activities` (`archive_reason` tidak masuk `activities`). Dokumen berstatus `archived` bersifat read-only terhadap edit metadata dan tambah versi (`422`), tetapi tetap dapat dilihat, diunduh, dan dipratinjau; versi file tetap immutable; `ArchiveDocumentPolicy::delete()` dan `forceDelete()` tetap `false`. Filter daftar `GET /api/archives` mendukung `status=active|archived|all` (default `active`).
- **Laporan retensi read-only tanpa eksekusi purge (D6 Opsi A & DEC-13 parsial).** Periode retensi per kategori disimpan di `config/archives.php` (`student_record`: `null`/permanen; `employee_record`: 5 tahun; `letter`: 5 tahun; `leave`: 2 tahun; `school_document`: 10 tahun; `other`: 5 tahun; `access_log_retention_years`: 5 tahun; `purge_enabled`: `false`). `GET /api/archives/retention/report` (`archives.manage`) dan CLI `php artisan app:archives-retention --dry-run` menghasilkan ringkasan kandidat retensi per kategori dan log akses secara **100% read-only** tanpa mengubah/menghapus baris atau file. Ability sensitif baru `archives.purge` didefinisikan di `AbilityCatalog` dan migration idempoten tetapi **tidak ditugaskan ke role mana pun** (`0` baris di `ability_role`). Scheduler `Console/Kernel.php` tidak diubah.

Tidak dikerjakan pada 09b (non-goals D1/D6/D11): eksekusi pemusnahan/auto-purge data atau file; penugasan `archives.purge` ke role; pengaktifan scheduler; perubahan enum `activities`; OCR/fulltext; watermark/regenerasi PDF arsip; perubahan dependency/lockfile; migrasi database operasional/MariaDB; perubahan service/`.env`; 09c atau Fase 10+.

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File | Perubahan | Schema/izin |
|---|---|---|
| `config/archives.php` | Tambah `statuses` (`active`/`archived`/`all`), `archive_reason_min_length=10`, `archive_reason_max_length=255`, `access_log` (`actions`, `per_page_options`, `default_per_page=20`, `user_agent_max_length=255`), `tasks` (`limit_default=10`, `limit_max=50`), dan `retention` (`purge_enabled=false`, `access_log_retention_years=5`, `categories`) | Tanpa `.env` baru |
| `database/migrations/2029_09_02_000001_add_archive_lifecycle_to_archive_documents_table.php` | Kolom additive `archived_at`, `archive_reason`, `archived_by` + index `(archived_at, document_date)` dan `(archived_at, valid_until)` | Additive |
| `database/migrations/2029_09_02_000002_create_archive_access_logs_table.php` | Tabel append-only `archive_access_logs` + index `(archive_document_id, created_at)`, `(user_id, created_at)`, `(action, created_at)` | Additive, append-only |
| `database/migrations/2029_09_02_000003_add_archive_purge_ability.php` | Ability `archives.purge` (`is_sensitive=true`) tanpa penugasan role | Additive/idempoten |
| `app/Support/AbilityCatalog.php` | Definisi `archives.purge` (`Manage archive retention purge`, sensitif, tanpa role default) | `0` role assignment |
| `app/Models/ArchiveDocument.php` | Cast `archived_at`, relasi `archiver()`, helper `isArchived()`/`status()`, scope `active()`/`archived()`, allowlist audit menambah `archived_at` (tanpa `archive_reason`) | `delete`/`forceDelete` tetap ditolak |
| `app/Models/ArchiveAccessLog.php` | Model append-only tanpa `updated_at`; relasi dokumen/versi/user; larangan `updating`/`deleting` | Immutable |
| `app/Policies/ArchiveDocumentPolicy.php` | Tambah `preview` (= `download`), `archive`/`unarchive`/`restore` (`archives.manage` + domain), `accessLogs` (`audit.view` + `view`), `retentionReport` (`archives.manage`); `delete`/`forceDelete` tetap `false` | Centralized Policy |
| `app/Services/Archives/ArchiveFileService.php` | `assertReadable()` (cek keberadaan `404` + SHA-256 `500`), `previewResponse()` (`CSP: sandbox`), `downloadResponse()` (`CSP: sandbox; default-src 'none'`) | Disk privat + SHA-256 |
| `app/Services/Archives/ArchiveAccessService.php` | `record()` fail-closed + `listForDocument()` berpaginasi | Append-only |
| `app/Services/Archives/ArchiveDocumentService.php` | Integrasi `assertReadable()` sebelum `access->record()` pada `download`/`preview`; `archive()`/`restore()` bertransaksi + `lockForUpdate` + `expected_version`/`409`; guard `422` saat dokumen `archived` diedit/ditambah versi; filter `status`; serialisasi additive `status`, `archived_*`, `can` | Transaksi + `lockForUpdate` |
| `app/Services/Archives/ArchiveTaskService.php` | `expiring()` untuk dokumen aktif `expired`/`expiring_soon` sesuai visibilitas domain aktor dan batas WIB 30 hari | Read-only |
| `app/Services/Archives/ArchiveRetentionService.php` | `report()` read-only per kategori + log akses berdasarkan tanggal dokumen/log dan config retensi | Read-only |
| `app/Http/Requests/ArchiveDocumentArchiveRequest.php` | Validasi `reason` (10–255) dan `expected_version` (integer ≥1) untuk pengarsipan | `422` |
| `app/Http/Requests/ArchiveDocumentRestoreRequest.php` | Validasi `reason` (10–255) dan `expected_version` (integer ≥1) untuk pemulihan | `422` |
| `app/Http/Controllers/ArchiveController.php` | Tambah 6 action (`preview`, `accessLogs`, `archive`, `restore`, `expiringTasks`, `retentionReport`) + validasi filter log akses | 15 route total |
| `app/Console/Commands/ArchivesRetentionCommand.php` | Command `app:archives-retention --dry-run` (menolak eksekusi tanpa `--dry-run` dengan exit code `INVALID`) | Tanpa scheduler |
| `routes/api.php` | Tambah `/tasks/expiring` dan `/retention/report` sebelum `/{archive}`, serta `/preview`, `/access-logs`, `/archive`, `/restore` | 15 route `/api/archives*` |
| `tests/Feature/Archives/ArchiveAccessRetentionTest.php` | 15 tes Pest / 296 assertions untuk seluruh matriks 09b (T1–T20) | 15 tes baru |
| `tests/Feature/Archives/ArchiveDocumentTest.php` | Penyesuaian regresi jumlah ability arsip (`archives.view`, `archives.manage`, `archives.purge`) | 14 tes 09a |
| `scripts/phase09-uat.php` | Subcommand `prepare-09b` (idempoten, additive `#122`–`#136` + 23 log akses) dan `retention-dry-run` | Hanya `.uat-phase09` |
| `vue-app/src/components/dashboard/ArchiveExpiryWidget.vue` | Widget ringkasan & daftar dokumen `expired`/`expiring_soon` + navigasi ke `/public/archives?validity=...` | Guard `archives.view` |
| `vue-app/src/views/Dashboard.vue` | Render `<ArchiveExpiryWidget />` pada baris teratas grid dashboard | Additive |
| `vue-app/src/views/pages/public/Archives.vue` | Filter Status (`active`/`archived`/`all`), sinkronisasi query `validity`, dialog Preview inline (PDF/gambar), panel Access History (`audit.view`), dialog Archive/Restore beralasan + `409` Reload Latest, panel Retention Summary read-only (`can.manage`) | Tombol mengikuti `can` server |

Kontrak 6 endpoint baru 09b (melengkapi 9 endpoint 09a menjadi **15 route `/api/archives*`**; detail lengkap di `docs/API-CONTRACTS.md`):

| # | Method | Endpoint | Izin | Catatan |
|---|---|---|---|---|
| 10 | GET | `/api/archives/tasks/expiring` | `archives.view` | Ringkasan + daftar dokumen aktif `expired`/`expiring_soon` sesuai visibilitas domain aktor; `limit` 1..50 (default 10) |
| 11 | GET | `/api/archives/retention/report` | `archives.manage` | Laporan retensi read-only per kategori + log akses; `purge_enabled=false`; tanpa mutasi |
| 12 | GET | `/api/archives/{archive}/versions/{version}/preview` | policy `preview` (= `download`) | Stream inline privat (`CSP: sandbox`, `X-Document-Filename`, tanpa `Content-Disposition`), cek SHA-256 (`500`), tulis `archive_access_logs` (`action=preview`) fail-closed |
| 13 | GET | `/api/archives/{archive}/access-logs` | `audit.view` + policy `view` | Riwayat akses append-only terbaru dahulu; filter `action=download\|preview`, `per_page` (10/20/50/100); tanpa `audit.view` `403`, dokumen tersembunyi `404` |
| 14 | POST | `/api/archives/{archive}/archive` | policy `archive` (`archives.manage` + domain) | `reason` 10–255 + `expected_version`; aktif → archived; sudah archived `422`; versi basi `409` |
| 15 | POST | `/api/archives/{archive}/restore` | policy `restore` (`archives.manage` + domain) | `reason` 10–255 + `expected_version`; archived → aktif (`archived_*` dikosongkan); sudah aktif `422`; versi basi `409` |

Perubahan additive pada endpoint 09a:
- `GET /api/archives/options`: menambah `statuses` (`active`, `archived`, `all`), `archive_reason {min_length: 10, max_length: 255}`, `retention {purge_enabled: false, access_log_retention_years: 5, categories}`, dan `can {manage, view_access_log, view_retention, purge: false}`.
- `GET /api/archives`: menerima filter `status=active|archived|all` (default `active`).
- `GET /api/archives` & `GET /api/archives/{archive}`: setiap item memuat `status` (`active`/`archived`), `is_archived`, `archived_at`, dan `can {view, update, add_version, download, preview, archive, restore, view_access_log}`; detail menambahkan `archive_reason` dan `archived_by {id, name}`.
- `GET /api/archives/{archive}/versions/{version}/download`: kini memverifikasi SHA-256 sebelum streaming (`500` bila mismatch) dan mencatat baris `archive_access_logs` (`action=download`) secara fail-closed.

## 3. Bukti verifikasi

- **Tes fitur Archives (09a + 09b)**:
  - Perintah: `C:\xampp\php82-operational\php.exe artisan test tests/Feature/Archives`
  - Hasil: **PASS — 29 passed (670 assertions) in 26.36s** (`ArchiveDocumentTest`: 14 passed / 374 assertions; `ArchiveAccessRetentionTest`: 15 passed / 296 assertions).
  - Cakupan `ArchiveAccessRetentionTest` (T1–T20):
    1. Preview inline mengembalikan `200`, byte identik, header `X-Document-Filename`, `Access-Control-Expose-Headers`, `nosniff`, `Cache-Control: private, no-store`, `Content-Security-Policy: sandbox`, tanpa `Content-Disposition`, dan mencatat log `preview`.
    2. Visibilitas preview lintas peran/domain (`uat.archivist`/viewer tanpa domain mendapat `404` pada dokumen bertaut dan `0` log tertulis; versi milik dokumen lain `404`; guru `403`).
    3. Download mencatat log `download` tanpa mengubah `version`/`updated_at` dokumen dan tanpa menulis ke tabel `activities`.
    4. Immutability `ArchiveAccessLog` (update/delete melempar `LogicException`) dan perilaku **fail-closed** (`500`, byte tidak dikirim saat penulisan log gagal).
    5. Endpoint `GET /api/archives/{archive}/access-logs` menegakkan `audit.view` + visibilitas domain, mendukung filter `action`, dan tidak membocorkan judul/nomor/nama file/path/checksum.
    6. Deteksi kerusakan/kehilangan file fisik: file hilang → `404`, checksum SHA-256 tidak cocok → `500` tanpa byte file dan **tanpa menulis baris `archive_access_logs`**.
    7. Siklus hidup `archive` dan `restore`: wajib alasan 10–255 + `expected_version`, menaikkan `version`, memblokir edit metadata & tambah versi saat `archived` (`422`), tetap mengizinkan detail/download/preview saat `archived`, serta hanya mencatat `archived_at` di `activities` (tanpa `archive_reason`).
    8. Konflik versi basi `409` pada `archive`/`restore` beserta payload `latest`, serta penolakan transisi duplikat (`422`).
    9. Filter `status=active|archived|all` (default `active`) dan perlindungan izin domain pada `archive`/`restore`.
    10. `GET /api/archives/tasks/expiring` dengan batas tanggal WIB (kemarin/hari ini/+1/+30/+31 hari), presedensi `upcoming`, pengecualian dokumen `archived`, dan penyaringan domain per aktor.
    11. `GET /api/archives/retention/report` dan `php artisan app:archives-retention --dry-run`: perhitungan kategori permanen vs berbatas tahun, kandidat log akses >5 tahun, penolakan eksekusi tanpa `--dry-run`, dan jaminan read-only (jumlah dokumen/versi/log/file tetap utuh).
    12. `archives.purge` terdaftar sebagai ability sensitif tetapi memiliki `0` penugasan di `ability_role`; tidak ada route `DELETE /api/archives/{archive}`.
- **Regresi modul berdekatan (Letters + Leaves)**:
  - Perintah: `C:\xampp\php82-operational\php.exe artisan test tests/Feature/Letters tests/Feature/Leaves`
  - Hasil: **PASS — 116 passed (2,217 assertions) in 136.66s** (`Letters`: 86 tes; `Leaves`: 30 tes).
- **Daftar route, kode gaya, lint, dan build frontend**:
  - `C:\xampp\php82-operational\php.exe artisan route:list --path=archives` → **Showing [15] routes**.
  - `C:\xampp\php82-operational\php.exe vendor/bin/pint --test` pada seluruh 21 file PHP yang dibuat/diubah → **PASS (21 files)**.
  - `npx eslint src/views/Dashboard.vue src/components/dashboard/ArchiveExpiryWidget.vue src/views/pages/public/Archives.vue` → **0 errors** (`Dashboard.vue` dan `ArchiveExpiryWidget.vue` 0 warning; `Archives.vue` hanya warning Prettier CRLF lama).
  - `npm run build` (di `vue-app`) → **PASS in 29.53s**.
- **Lingkungan UAT terisolasi `.uat-phase09`** (SQLite `.uat-phase09/database.sqlite`, storage `.uat-phase09/storage`, port API `18027` / SPA `14027`):
  - `C:\xampp\php82-operational\php.exe scripts/phase09-uat.php prepare-09b` dijalankan dua kali untuk membuktikan idempotensi:
    - Baseline 09a utuh: **120 dokumen** `P09/ARCHIVE/*` + **1 dokumen UI `#121`** dipertahankan tanpa reset.
    - Fixture additive 09b: **15 dokumen** `P09B/ARCHIVE/001` s.d. `015` (`#122`–`#136`), sehingga total menjadi **136 dokumen** (`134 active`, `2 archived` yaitu `#129` `P09B/ARCHIVE/008` dan `#130` `P09B/ARCHIVE/009`) dan **177 baris versi** (**176 file fisik** di disk karena `#131` `P09B/ARCHIVE/010` sengaja tanpa file fisik untuk probe `404` dan `#132` `P09B/ARCHIVE/011` sengaja berisi byte tidak cocok SHA-256 untuk probe `500`).
    - Distribusi validitas seluruh 136 dokumen: `upcoming: 25`, `valid: 31`, `expiring_soon: 27`, `expired: 26`, `none: 27`.
    - Log akses: **23 baris** (`uat.operator` dan `uat.admin`), **0 baris** pada `#131`/`#132`. Run kedua menghasilkan `0 created, 15 reused`, `0` transisi arsip baru, dan `0` log akses baru.
    - Penugasan role untuk `archives.purge`: **`0`**.
  - `C:\xampp\php82-operational\php.exe scripts/phase09-uat.php retention-dry-run` → **PASS**:
    - `Student Record`: Permanent, 41 docs (41 active, 0 archived, 0 eligible), 7.75 KB, oldest `2026-09-23`
    - `Employee Record`: 5 year(s), cutoff `2021-11-03`, 20 docs, 0 eligible, 3.77 KB, oldest `2026-09-04`
    - `Letter`: 5 year(s), cutoff `2021-11-03`, 20 docs, 0 eligible, 3.81 KB, oldest `2026-10-13`
    - `Leave`: 2 year(s), cutoff `2024-11-03`, 21 docs, **1 eligible** (`#136` `P09B/ARCHIVE/015`, `document_date=2023-11-03`), 3.95 KB
    - `School Document`: 10 year(s), cutoff `2016-11-03`, 24 docs (22 active, 2 archived), **1 eligible** (`#135` `P09B/ARCHIVE/014`, `document_date=2015-11-03`), 4.89 KB
    - `Other`: 5 year(s), cutoff `2021-11-03`, 10 docs, 0 eligible, 1.44 KB, oldest `2026-09-04`
    - Total: `136 documents (2 archived), 177 versions, 25.61 KB stored, 2 past retention`; `Access logs: 23 rows, 0 past the 5-year retention (cutoff 2021-11-03)`; `archives.purge is not assigned to any role; no destructive action is available.`
  - Verifikasi HTTP terhadap server UAT `.uat-phase09` sebelum dihentikan mencakup: preview PDF/PNG (`200`, `CSP: sandbox`, `X-Document-Filename`, tanpa `Content-Disposition`), pencatatan log akses dan pembacaan oleh `uat.admin` (`200`) vs penolakan `uat.operator`/`uat.kepsek` (`403` karena tanpa `audit.view`) dan `uat.archivist` pada dokumen bertaut (`404`), probe file hilang `#131` (`404`, `0` log baru), probe SHA-256 mismatch `#132` (`500`, `0` log baru),siklus archive → blocked edit/version (`422`) → restore → `409` versi basi, endpoint `/api/archives/tasks/expiring` per peran (`uat.operator` vs `uat.archivist`), serta `/api/archives/retention/report` (`200` untuk `uat.operator`, `403` untuk `uat.kepsek`).

## 4. NOT RUN

- **Otomasi browser interaktif / screenshot visual (1440×900 & 390×844) dan navigasi keyboard pada sesi ini**: **NOT RUN** secara otomatis di browser; seluruh kontrak endpoint, aliran data komponen, build Vite, dan ESLint telah diverifikasi lewat tes fitur, probe HTTP nyata terhadap `.uat-phase09`, dan kompilasi produksi, sedangkan pengujian visual/interaksi UI diserahkan melalui checklist manual pemilik `#1–#18` di `PHASE-09B-MANUAL-REVIEW.md`.
- **Suite penuh PHPUnit lintas seluruh fase**: **NOT RUN** karena `Tests\Feature\Backup\MariaDbIntegrationTest` menyentuh database di luar batas akses sesi (`school_access_uat`); verifikasi backend dijalankan terfokus pada `tests/Feature/Archives` (29 tes) serta regresi `tests/Feature/Letters` dan `tests/Feature/Leaves` (116 tes) di SQLite in-memory.
- **Race condition MariaDB/InnoDB multi-proses nyata dan dua tab browser nyata untuk `409`**: **NOT RUN** (runtime UAT menggunakan SQLite; konflik `409` diuji melalui request `expected_version` basi).
- **Restore drill yang memuat tabel `archive_access_logs` dan kolom lifecycle 09b**: **NOT RUN**.
- **Eksekusi pemusnahan/purge arsip**: **NOT RUN / TIDAK DIOTORISASI** sesuai D6 Opsi A dan DEC-38.

## 5. Status UAT dan langkah berikutnya

- Server UAT `.uat-phase09` **telah dihentikan pada 2026-11-03 WIB** sesuai kebijakan DEC-27 setelah verifikasi agen selesai: proses API `127.0.0.1:18027` (PHP PID `2736`) dan SPA `127.0.0.1:14027` (Vite node PID `18008`) diverifikasi command line-nya lalu dihentikan, port `18027` dan `14027` terverifikasi bebas, Apache (PID `1304` pada port `80`/`443`) dan MariaDB (PID `18244` pada port `3306`) tidak disentuh, dan seluruh data `.uat-phase09` (136 dokumen, 177 versi, 176 file fisik, 23 log akses) dipertahankan.
- Perintah untuk menyalakan kembali server UAT saat pemilik ingin menjalankan checklist manual `#1–#18`, serta perintah untuk menghentikannya kembali, tercantum lengkap di `reports/feature-update/PHASE-09B-MANUAL-REVIEW.md`.
- **Review manual pemilik: PENDING (#1–#18)**. Commit dan push paket review ini **bukan** penerimaan pemilik; penerimaan baru dicatat pada commit dokumentasi terpisah setelah pemilik menyampaikan hasil review.
