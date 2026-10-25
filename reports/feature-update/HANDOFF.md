# Handoff pengembangan aplikasi sekolah

Checkpoint penutupan sesi: 2026-10-22 (WIB). Branch `main`.
Baseline backend terakhir: `e77f335` ("feat(phase-03b): implement student CRUD with optimistic locking, administrative archive, and audit privacy").
Baseline penerimaan manual 03b: `cbd25bd` ("feat(phase-03b): add student CRUD UI, archive/restore flows, and 03b concurrency tests").
Baseline implementasi 03c: `0a15325`; keputusan 03c (DEC-22): `4fa20c6` ("docs(phase-03c): record owner decisions for list, placement, recap, and export").
Baseline pemeriksaan akhir 03c: `472ce9b`; baseline implementasi terakhir: `e15f028` ("fix(phase-03c): clear eslint errors and always send export placement").
Kebijakan commit/push: commit dan push per fase/koreksi ke branch kerja aktif telah **diotorisasi**; merge, force-push, rewrite history, dan deploy manual **tetap tidak diotorisasi**.

Catatan perubahan pemilik: `console.txt` dan `vue-app/test-results/` adalah artefak lokal dan **tidak** di-commit.

---

## 0. Checkpoint Penutupan Sesi (2026-10-22)

- **HEAD/remote**: `e15f028`, branch `main`, sinkron `origin/main`. Working tree bersih selain artefak lokal `console.txt` dan `vue-app/test-results/` (tidak di-commit).
- **Status**: Fase 01, 02, dan 03 **DITERIMA LENGKAP & DITUTUP**; Subfase 03a–03c seluruhnya **DITERIMA PEMILIK** (03c checklist manual #1–#23 APPROVED pada 2026-10-26 WIB). Subfase kandidat 03d tetap **BELUM DIOTORISASI**.
- **Bukti terakhir 03c (`e15f028`)**: ESLint `Student.vue` **0 errors / 217 warnings** (LF blob); frontend tests **18 passed**; build **PASS**; ekspor UI selalu mengirim `placement` termasuk `placement=all`; browser smoke All/Placed PASS; UAT sintetis terisolasi PASS (16 skenario). Penerimaan manual pemilik: **APPROVED #1–#23**.
- **Alasan jeda**: pemilik memeriksa aplikasi lain terkait laporan kerentanan PHP dengan identifier **CVE-2026-109618** dan **CVE-2026-109619**. Identifier ini **informasi dari pemilik yang BELUM diverifikasi** pada sesi ini; jangan menyimpulkan versi terdampak, tingkat keparahan, atau keterpaparan aplikasi sekolah.
- **Belum diotorisasi**: audit keamanan, upgrade PHP/dependency, dan perubahan konfigurasi aplikasi sekolah. Tunggu instruksi terpisah.
- **Melanjutkan sesi**: (1) baca `AGENTS.md` dan dokumen workflow; (2) periksa HEAD aktual serta perubahan sejak `e15f028` (branch, remote, working tree); (3) lanjut hanya sesuai instruksi pemilik berikutnya; jangan menutup Fase 03 atau memulai fase berikutnya tanpa perintah.
- **Selama jeda**: jangan menjalankan server, test/build ulang, migrasi, seeder, backup/restore, deploy, atau aktivasi scheduler tanpa instruksi.

---

## 0a. Checkpoint Lingkungan Keamanan (2026-10-22, sesi terpisah)

- **Scope**: preflight read-only + penyiapan runtime/checkout uji terisolasi. **Bukan** kelanjutan fitur dan **bukan** penutupan Fase 03.
- **HEAD kode**: `224529f`; branch `main`; working tree hanya berisi dokumen sesi ini + artefak lokal `console.txt`/`vue-app/test-results/` (tidak di-commit).
- **Temuan kritis**: repository sekolah di `C:/xampp/htdocs` dapat diakses lewat parent DocumentRoot; `.env`, `.git/HEAD`, dan `storage/logs/laravel.log` mengembalikan HTTP 200. Apache listen `0.0.0.0:80/443`; rule firewall Apache aktif pada profil Public. **Belum diperbaiki**; perubahan path-specific/vhost perlu instruksi pemilik.
- **Runtime uji**: PHP 8.3.36 NTS x64 dan Node 24.22.1 portable di `%TEMP%\opencode\school-sec-env` (di luar web root), checkout clone @ `224529f` tanpa `.env`, storage/cache/session/log terpisah, port 18021/14021 kosong. PHP/Node checksum resmi cocok. Scheduler/worker OFF.
- **Hasil**: Composer install dari lock PASS (125 paket, `--no-scripts --no-plugins`); `check-platform-reqs` PASS; frontend `node --test` 18 passed; build PASS; ESLint 0 errors/2574 warnings (CRLF). Audit: Composer 48 advisory/14 paket; npm 20 vulnerability. Backend test/DB/migrasi/seeder/smoke/browser **NOT RUN**.
- **Laporan**: `reports/feature-update/ENVIRONMENT-SCHOOL-COMPAT.md`.
- **Langkah berikutnya**: review exposure Apache, keputusan DB sintetis, lalu otorisasi DB/migrasi/smoke. 03c #1-#23 tetap PENDING.

---

## 0b. Checkpoint Containment Apache (2026-10-22, sesi terpisah)

- **Scope**: containment direktori sekolah saja. Tidak ada DB/test aplikasi/remediasi dependency/fitur.
- **Baseline laporan**: `2e2e557`; branch `main`.
- **Perubahan**: file scoped `conf/extra/httpd-school-containment.conf` (Directory fisik sekolah, akses ditolak, listing off, `AllowOverride None`) + satu `Include` di akhir `httpd.conf`. Backup privat di `%TEMP%\opencode\apache-containment-backup-20261022-231712` (di luar web root; tidak di-commit).
- **Hash**: `httpd.conf` `D4E2DB1A...` -> `A24BE18C...`; file containment `4C3D07A6...`; App Gudang/SSL/XAMPP dan seluruh `conf/extra` lain tidak berubah.
- **Penerapan**: `httpd -t` **Syntax OK**; `httpd -k restart` gagal karena tidak ada service Apache2.4; Apache console dihentikan/dimulai ulang pada PID yang terbukti (parent 3364 -> 9360), downtime ~3-5 detik.
- **Verifikasi**: HTTP/HTTPS loopback root sekolah, `.env`, `.git/HEAD`, log, `public/`, junction `public/storage`, dan `backdata-administration` -> **403**. `/` -> 302 dan `/appgudang/` -> 403 (config App Gudang tetap). LAN/internet **NOT TESTED**.
- **Risiko**: eksposur 200 sebelum containment tidak membuktikan tidak ada akses; rotasi kredensial dan audit log historis belum dilakukan. APP_KEY tidak diubah.
- **Laporan**: `reports/feature-update/APACHE-SCHOOL-CONTAINMENT.md`.
- **Langkah berikutnya**: review laporan; uji LAN/firewall dan audit log/rotasi sebagai pekerjaan terpisah; 03c tetap PENDING.

---

## 0c. Checkpoint Penghentian Server UAT 8001 (2026-10-23, sesi terpisah)

- **Scope**: hanya menghentikan server UAT sekolah `127.0.0.1:8001` dan mencatat hasil; tidak ada DB/test/cutover/fitur.
- **Identitas terverifikasi**: listener 8001 -> PID `20444` (`C:\xampp\php\php.exe -S 127.0.0.1:8001 ...\laravel-api\vendor\...\server.php`); parent `cmd.exe` `17428`; parent Artisan `13828` (`php artisan serve --host=127.0.0.1 --port=8001 --env=uat`); cwd Artisan `...\laravel-api`, cwd child `...\laravel-api\public`; `.env.uat` ada (isi tidak dibaca).
- **Metode/waktu**: tidak ada terminal pemilik; parent Artisan dihentikan lebih dulu, lalu cmd/php child. Waktu **2026-10-23 15:07:31 +07:00**.
- **Hasil**: port 8001 **0 listener**; PID 13828/17428/20444 hilang; tidak ada respawn setelah 10+ detik. Data/session/log/tidak dihapus; tidak ada restart server.
- **Insiden**: setelah stop, seluruh proses `php.exe` hilang; listener **8000** (PID 2384 sebelum stop) juga hilang meski bukan target dan tidak dihentikan langsung. Penyebab pasti belum terbukti (kemungkinan shared console/job cleanup). Server 8000 **tidak** dijalankan ulang.
- **Laporan**: `reports/feature-update/UAT-SERVER-STOP-8001.md`.
- **Status**: UAT 8001 dihentikan; kelanjutan runtime menunggu cutover PHP global; pemulihan 8000 menunggu keputusan pemilik. 03c #1-#23 PENDING; Fase 03 PARTIAL.

---

## 0d. Checkpoint UAT Kompatibilitas PHP 8.2 Global Terisolasi (2026-10-26, sesi terpisah)

- **Scope**: Persiapan dan pengujian kompatibilitas UAT sintetis khusus sekolah di luar DocumentRoot/Alias Apache. Bukan penerimaan manual 03c; bukan persetujuan penyelesaian seluruh risiko keamanan.
- **Koreksi dokumentasi**: Laravel 11 dikoreksi berstatus EOL (dukungan keamanan berakhir Maret 2026, bukan masih 5 bulan); target modernisasi utama adalah Laravel 12. Batasan klaim advisory dicatat: ketiadaan pemanggilan langsung bukan jaminan mutlak seluruh jalur transitif/framework unreached; loopback adalah pembatas jaringan, bukan mitigasi universal parser/build tools. Risiko advisory, eksposur historis, dan rotasi kredensial tetap OPEN.
- **Checkout uji & runtime**: Checkout uji `%TEMP%\...\school-sec-env\staging\checkout` disinkronkan ke baseline implementasi `e15f028` (commit `27eca92`). PHP global `C:\xampp\php82-operational\php.exe` (PHP 8.2.35); Node portable `v24.22.1` (npm 11.19.1). Main checkout `.env.uat`, storage, log tidak ditimpa.
- **Pre-flight guard fail-closed**: `laravel-api/scripts/verify-isolation.php` memvalidasi target eksak `school_compat_uat`, menolak `DB_URL`/process env overrides, tolak config cache, cookie `school_compat_session`, mailer log.
- **Database sintetis**: DB `school_compat_uat` dibuat di MariaDB port 3306; dedicated user `school_compat_app` (akses ke DB lain ditolak); password privat di `secret_db_pass.txt`; 42 migrasi normal sukses; `UatSeeder` selesai (password privat di `secret_uat_password.txt`). Database `backdata_administrations`, UAT lama, dan App Gudang tidak disentuh.
- **Hasil pengujian otomatis**:
  - Backend SQLite-only suite: **221 passed (1038 assertions)** in 178.05s across 27 files; 2 file integrasi MariaDB (`MariaDbIntegrationTest.php` dan `MariaDbStudentIntegrationTest.php`) strictly excluded.
  - Frontend unit tests: **18 passed** in 961ms (Node 24.22.1).
  - Frontend build: **PASS** (Vite 6.3.5, 789 modules in 1m 14s).
  - Frontend lint terarah tanpa `--fix`: **0 errors, 2574 warnings** (CRLF).
- **Server UAT aktif untuk review pemilik**:
  - API: PID 3812 / child 15960 (`C:\xampp\php82-operational\php.exe -S 127.0.0.1:18021`)
  - SPA: PID 17964 (`Node portable 24.22.1 ... vite --mode uat --host 127.0.0.1 --port 14021 --strictPort`)
  - Kredensial lokal: Pemilik dapat membaca password dari `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\secret_uat_password.txt` atau mereset via Tinker UAT (tidak dicetak ke laporan).
- **Hasil smoke test (16 skenario PASS)**: CSRF 204, login operator 204, check-auth abilities, student list & recap (school_year_id=1), filter placed/unplaced, search formula, detail, edit version 1->2, stale edit 409 conflict, archive version 2->3, archived list view, restore version 3->4, export CSV, logout 204, kepsek role checks (view 200, create 403, archived 403, export 200), waka role checks (view 200, export 403). Seluruhnya PASS. UI penempatan rombel tidak diklaim tersedia.
- **Status kelanjutan**: Server UAT 18021/14021 dibiarkan aktif untuk peninjauan checklist manual 03c #1–#23 oleh pemilik. Fase 03 tetap PARTIAL. Laporan: `reports/feature-update/PHP82-COMPATIBILITY-AUDIT.md`.

---

---

## 1. Status Fase
- **Fase 00 — Audit baseline**: PARTIAL (source & environment audit selesai; sebagian browser NOT RUN).
- **Fase 01 — Hak akses, autentikasi dan lingkungan aman**: **DITERIMA LENGKAP & DITUTUP** (01a, 01b, 01c).
- **Fase 02 — Integritas Operasional (Audit & Backup/Restore)**: **DITERIMA LENGKAP & DITUTUP** (02a, 02b, 02c).
- **Fase 03 — Pendataan Siswa Lengkap dan Aman**: **DITERIMA LENGKAP & DITUTUP** (03a, 03b, 03c seluruhnya DITERIMA PEMILIK):
  - **Subfase 03a** — Identitas dan membership migration: **DITERIMA PEMILIK** (checklist #1–#11 APPROVED 2026-10-21 WIB, baseline `37f7d79`).
  - **Subfase 03b** — CRUD dan Form Siswa: **DITERIMA PEMILIK** (checklist #1–#13 APPROVED 2026-10-21 WIB, baseline `cbd25bd`).
  - **Subfase 03c** — Daftar, Rekap & Ekspor Siswa (server-side): **DITERIMA PEMILIK** (checklist #1–#23 APPROVED 2026-10-26 WIB mengacu baseline fitur `e15f028` dan lingkungan `933e71c`; laporan `PHASE-03C-STUDENT-DIRECTORY.md`).
  - **Subfase 03d (tambahan)** — Aksi penempatan/pemindahan rombel via UI: **IMPLEMENTED & FULLY VERIFIED (AGEN) / MANUAL ACCEPTANCE PENDING**.
    - Keputusan **DEC-23** disetujui pemilik 2026-10-26 WIB; baseline implementasi `01c953e`.
    - Verifikasi agen: `php artisan test` **235 passed (1099 assertions)** (28 files SQLite in-memory); MariaDB concurrency test **2 passed (6 assertions)** pada DB disposable `school_03d_integration`; frontend `node --test` **18 passed**; `npm run build` **PASS**; Pint **PASS**; targeted lint **0 errors**; browser UAT smoke (16 skenario) **PASS**.
    - Laporan: `PHASE-03D-STUDENT-PLACEMENT.md`; checklist manual `PHASE-03D-MANUAL-REVIEW.md` (#1–#15) **menunggu review pemilik**.
- **Keputusan Terbuka yang Masih OPEN (belum menghambat)**:
  1. Makna `status_user_id`.
  2. Deployment CORS/Sanctum produksi.
  3. Strategi pencabutan token dibanding sesi.
- **Batasan Cakupan**:
  - Single-school (DEC-01); keunikan NIS level tabel `students`.
  - Task scheduler OS belum diaktifkan.
  - Tidak ada izin deployment produksi.
  - UI 03b tidak membuat keanggotaan rombel; siswa baru kini **tampil** pada daftar All/Unplaced (03c) meski belum ditempatkan ke rombel.
  - Pagination server dan rekap chart dikerjakan pada **03c** (selesai secara teknis, menunggu penerimaan manual).

---

## 2. Pemeriksaan yang Dijalankan (Subfase 03b Sesi Ini)
1. **Backend**:
   - `php artisan test tests/Feature/Student` → **55 passed (228 assertions)**.
     - MariaDB integration 7, StudentAgeAndIdentity 5, StudentCrud 24, StudentMigrationAndBackfill 8, StudentRombelConstraint 6, StudentRombelResolution 5.
   - `php artisan test` → **209 passed (946 assertions)**.
   - `./vendor/bin/pint --test tests/Feature/Student/MariaDbStudentIntegrationTest.php` → **PASS**.
2. **Frontend**:
   - `node --test` (dari `vue-app`) → **15 passed (0 failed)**.
   - `npm run build` → **PASS** (788 modules).
   - `npx eslint src/views/pages/public/Student.vue src/main.js` → **22 errors, 2609 warnings**; 22 error seluruhnya pada baris lama (baseline `no-unused-vars` / `vue/no-side-effects-in-computed-properties`), **0 error baru** dari 03b.
3. **Konkurensi 03b (MariaDB disposable `school_restore_drill_uat`)**:
   - Dua update dengan expected version sama: tepat satu 200, satu 409 (`current_version` benar), versi naik sekali, audit hanya milik pemenang.
   - Arsip vs aktivasi rombel: aktivasi untuk siswa terarsip ditolak trigger; jumlah keanggotaan aktif akhir 0.
4. **Browser UAT agen (Playwright MCP)**:
   - `uat.operator`: create (422 inline NIK + create valid) → detail → edit (422 inline NISN seed + update sukses) → konflik 409 dua tab → Reload Latest → simpan sukses → arsip dengan alasan → daftar arsip → restore.
   - `uat.kepsek`: New/Archived/Edit/Archive tidak tampil; View Detail tampil.
5. **Keamanan/Isolasi**: `backdata_administrations` tidak disentuh. Server operasional port 8000 dibiarkan utuh.

---

## 3. Berkas yang Berubah (lihat commit)
- Commit `cbd25bd` (implementasi UI 03b):
  - `vue-app/src/views/pages/public/Student.vue`: UI 03b lengkap.
  - `vue-app/src/main.js`: registrasi directive `v-tooltip`.
  - `laravel-api/tests/Feature/Student/MariaDbStudentIntegrationTest.php`: 2 integration test konkurensi 03b.
  - `reports/feature-update/PHASE-03B-STUDENT-CRUD.md` (baru), `PHASE-03B-MANUAL-REVIEW.md`, `INDEX.md`, `HANDOFF.md`, `roadmap/ROADMAP.md`, `CHANGELOG.md`, `docs/DATABASE.md`, `docs/API-CONTRACTS.md`.
- Commit pencatatan penerimaan 03b (lihat CHANGELOG): `PHASE-03B-STUDENT-CRUD.md`, `PHASE-03B-MANUAL-REVIEW.md`, `INDEX.md`, `HANDOFF.md`, `roadmap/ROADMAP.md`, `CHANGELOG.md`.
- Commit rencana 03c (lihat CHANGELOG): `PHASE-03C-PLAN.md` (baru), `INDEX.md`, `HANDOFF.md`, `roadmap/ROADMAP.md`, `CHANGELOG.md`.

---

## 4. Status Tugas Subfase 03c
1. **Keputusan DEC-22**: SELESAI dicatat & di-push (`4fa20c6`).
2. **Backend daftar/rekap + validasi + anti double-count + atribut minimum**: SELESAI (`StudentDirectory`, `StudentController::index`); `only_archived` kini butuh `students.manage`.
3. **Backend ekspor terfilter (unplaced, batas 422, leading zero, audit tidak berubah)**: SELESAI (`StudentExportController`).
4. **Frontend lazy table + filter + rekap + stale guard + state + pemulihan halaman kosong**: SELESAI (`Student.vue`).
5. **Verifikasi**: backend **229 passed (1076 assertions)**; Student+Export **88 passed (394 assertions)**; frontend **18 passed**; build **PASS**; Pint **PASS**; browser UAT **PASS**. Pemeriksaan akhir (backend tidak berubah): ESLint `Student.vue` **0 errors/217 warnings** (LF; dari 20/218), smoke ekspor All/Placed PASS.
6. **Lint terarah**: 20 error `no-unused-vars` diperbaiki (hapus import/ref/fungsi mati; tanpa `eslint-disable`/auto-format). Warning 217 kosmetik/pre-existing — bukan PASS bersih.
7. **Ekspor placement**: `buildStudentExportParams()` selalu mengirim `placement` (fallback `all`), dikunci 3 test unit; request All terbukti `placement=all`.
8. **Penerimaan manual pemilik**: **PENDING** (checklist #1–#23). Fase 03 tetap PARTIAL; 03a–03b tetap DITERIMA.
9. **Catatan klasifikasi bukti**: bukti agen (test/build/browser) terpisah dari penerimaan manual. ESLint repo **bukan PASS bersih** (0 errors/217 warnings tersisa, kosmetik/pre-existing). Tidak ada deployment; penerimaan bukan izin deployment/akses data operasional.

---

## 5. Server & Lingkungan Aktif
Server UAT lokal yang diketahui berjalan (jangan dihentikan sembarangan):
- **API UAT**: port `8001` (`php artisan serve --host=127.0.0.1 --port=8001 --env=uat`; listener `php -S` aktif).
- **SPA UAT**: port `4001` (`vite --mode uat --host 127.0.0.1 --port 4001`, PID 16624).
- **Database UAT**: MySQL `school_access_uat` (disposable).
- **Database Target Drill**: MySQL `school_restore_drill_uat` (disposable).
- Server operasional port `8000` dibiarkan utuh.
- **PENTING**: dilarang mengakses data operasional atau membersihkan database UAT `school_access_uat`.

Data sintetis yang berubah selama browser UAT:
- 03b: `UAT Browser Invalid Nik` (NIK `9876543210123456`, NIS `UAT-BROWSER-001`); `Synthetic Student One` → `Synthetic Student One (B)` diarsipkan lalu di-restore (rombel lama tetap non-aktif).
- 03c: siswa `UAT 03C No Rombel` (NIK `9000000000000101`); seed `UAT03C Bulk 01–25` (NIK `90000000000002xx`; 10 placed, 15 unplaced); beberapa bulk diarsipkan lalu satu di-restore; `=FORMULA TEST` NISN diubah ke `0000000999` via edit UAT.
- 03c: kata sandi akun sintetis `uat.operator`/`uat.kepsek` direset pada DB UAT disposable (kata sandi tidak ditulis ke dokumen) karena kata sandi sesi sebelumnya tidak tersedia.

---

## 6. Langkah Berikutnya
1. Subfase 03c telah **DITERIMA PEMILIK** (checklist #1–#23 APPROVED pada 2026-10-26 WIB); **Fase 03 DITUTUP LENGKAP**.
2. Subfase tambahan **03d (Penempatan dan Perpindahan Rombel via UI)** telah selesai diimplementasikan dan diverifikasi agen:
   - Status: **IMPLEMENTED & FULLY VERIFIED (AGEN) / MANUAL ACCEPTANCE PENDING**.
   - Pemilik meninjau checklist manual di `reports/feature-update/PHASE-03D-MANUAL-REVIEW.md` (#1–#15) pada server UAT aktif `http://127.0.0.1:14021`.
3. Setelah penerimaan manual 03d diselesaikan, pemilik dapat menentukan pemilihan fase berikutnya sesuai `NEXT-STEPS-PLAN.md` (Fase 04 Guru dan Pegawai).
4. Server UAT terisolasi pada `127.0.0.1:18021` (API) dan `127.0.0.1:14021` (SPA) tetap berjalan di loopback untuk keperluan peninjauan lokal pemilik.
5. Menunggu instruksi dan review pemilik sebelum memulai pekerjaan implementasi berikutnya.
