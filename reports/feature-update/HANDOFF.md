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
- **Status**: Fase 01–02 ditutup; 03a–03b DITERIMA PEMILIK; 03c **IMPLEMENTED & VERIFIED (AGEN)**, penerimaan manual **#1–#23 PENDING**. Fase 03 **tidak** ditutup.
- **Bukti terakhir 03c (`e15f028`)**: ESLint `Student.vue` **0 errors / 217 warnings** (LF blob); frontend tests **18 passed**; build **PASS**; ekspor UI selalu mengirim `placement` termasuk `placement=all`; browser smoke All/Placed PASS. Suite backend tidak diulang karena backend tidak berubah.
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

## 0d. Checkpoint Audit Kompatibilitas PHP 8.2 Global (2026-10-26, sesi terpisah)

- **Scope**: Audit awal read-only kompatibilitas PHP 8.2 global pasca-cutover dan perancangan UAT/runtime sekolah. Tidak ada eksekusi server, DB, tes, migrasi, atau fitur.
- **Runtime terverifikasi**: PHP CLI global `C:\xampp\php82-operational\php.exe` (PHP 8.2.35 ZTS x64); Apache SAPI memuat `php8apache2_4.dll` dari direktori yang sama (error.log mencatat `PHP/8.2.35 configured`); Composer 2.8.2 via `composer.bat` menggunakan PHP 8.2.35. Runtime rollback `C:\xampp\php` (PHP 8.1.25) tidak aktif di PATH. Node global `v24.18.0` (npm 11.4.2); portable `%TEMP%\...\school-sec-env\runtime` memuat Node `v24.22.1` (npm 11.19.1).
- **Hasil audit backend**: Platform requirement `composer.lock` 125 paket **100% success** di atas PHP 8.2.35. Ekstensi non-manifest `pdo_mysql`, `pdo_sqlite`, `sqlite3`, `zip` (AES-256), `proc_open` seluruhnya tersedia. Script `composer install` memanggil `package:discover` sehingga wajib `--no-scripts --no-plugins` saat instalasi awal.
- **Hasil audit frontend**: SPA statis murni (Vite 6.3.5 + Vue 3.4.34), tanpa SSR/server Node. Script `dev` mengikat `0.0.0.0` (risiko eksposur LAN); usulan UAT wajib loopback `127.0.0.1` dengan `--strictPort`. Script `lint` mengandung `--fix` (peringatan read-only).
- **Status advisory**: PHP 8.2.35 memuat patch CVE-2026-109618/CVE-2026-109619. PHP 8.2 security support berakhir 31 Des 2026. Node Brotli CVE-2026-111497 risiko rendah untuk local dev SPA. Laravel 10 berstatus EOL sejak Feb 2025. Lockfile mencatat 48 advisory Composer & 20 vulnerability npm (pre-existing, bukan blocker).
- **Status containment**: Apache containment sekolah aktif (`httpd-school-containment.conf`), seluruh akses loopback sekolah 403 Forbidden. Listener 8000 dan 8001 tetap FREE; Apache 80/443 & MariaDB 3306 aktif.
- **Rencana Tiga Jalur**:
  - Jalur A (Minimum Dev/UAT): Gunakan PHP 8.2 global, tidak perlu runtime portable permanen, port terisolasi usulan 18021/14021.
  - Jalur B (Modernisasi Jangka Panjang): Upgrade terencana Laravel 10 -> 11, koordinasi keseragaman lintas aplikasi.
  - Jalur C (Kelanjutan Fitur): Baseline 03c `e15f028` tetap; checklist #1–#23 PENDING review pemilik; Fase 03 tetap PARTIAL; 03d belum diotorisasi.
- **Rancangan UAT (BELUM DIJALANKAN)**: Usulan DB disposable `school_compat_uat`, fail-safe pre-bootstrap guard, port API `127.0.0.1:18021` dan SPA `127.0.0.1:14021`, cookie `school_compat_session`, profil browser terpisah, SQLite-first test.
- **Laporan**: `reports/feature-update/PHP82-COMPATIBILITY-AUDIT.md`.
- **Langkah berikutnya**: Menunggu review pemilik atas laporan audit & rencana kompatibilitas sebelum otorisasi pembuatan DB sintetis / pembukaan server.

---

## 1. Status Fase
- **Fase 00 — Audit baseline**: PARTIAL (source & environment audit selesai; sebagian browser NOT RUN).
- **Fase 01 — Hak akses, autentikasi dan lingkungan aman**: **DITERIMA LENGKAP & DITUTUP** (01a, 01b, 01c).
- **Fase 02 — Integritas Operasional (Audit & Backup/Restore)**: **DITERIMA LENGKAP & DITUTUP** (02a, 02b, 02c).
- **Fase 03 — Pendataan Siswa Lengkap dan Aman**: **IN PROGRESS / PARTIAL**:
  - **Subfase 03a** — Identitas dan membership migration: **DITERIMA PEMILIK** (checklist #1–#11 APPROVED 2026-10-21 WIB, baseline `37f7d79`).
  - **Subfase 03b** — CRUD dan Form Siswa: **DITERIMA PEMILIK**.
    - Backend: `e77f335`; UI/deliverable: `cbd25bd`.
    - Verifikasi agen: suite Student **55 passed (228 assertions)**; regresi backend **209 passed (946 assertions)**; frontend unit **15 passed**; build **PASS**; browser UAT agen PASS.
    - **Penerimaan manual pemilik: APPROVED** (checklist #1–#13, 2026-10-21 WIB, baseline `cbd25bd`).
  - **Subfase 03c** — Daftar, Rekap & Ekspor Siswa (server-side): **IMPLEMENTED & VERIFIED (AGEN) / MANUAL ACCEPTANCE PENDING**.
    - Keputusan **DEC-22** disetujui pemilik 2026-10-21 WIB (commit `4fa20c6`); baseline implementasi `0a15325`.
    - Verifikasi agen: `php artisan test` **229 passed (1076 assertions)**; `tests/Feature/Student` + `StudentExportTest` **88 passed (394 assertions)**; frontend `node --test` **15 passed**; `npm run build` **PASS**; Pint **PASS**; browser UAT **PASS**.
    - Laporan: `PHASE-03C-STUDENT-DIRECTORY.md`; checklist `PHASE-03C-MANUAL-REVIEW.md` (#1–#23) **menunggu review pemilik**.
  - **Subfase 03d (kandidat)** — Aksi penempatan/penyesuaian keanggotaan rombel via UI: **BELUM DIOTORISASI** (di luar scope 03c).
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
1. Review pemilik checklist 03c (`PHASE-03C-MANUAL-REVIEW.md` #1–#23). Status 03c: IMPLEMENTED & VERIFIED / MANUAL ACCEPTANCE PENDING.
2. Setelah persetujuan: catat penerimaan pada commit dokumentasi tersendiri dan tutup Fase 03 bila seluruh subfase selesai.
3. Aksi penempatan rombel via UI belum dibangun (kandidat 03d; belum diotorisasi).
4. 03a–03b tetap DITERIMA PEMILIK; jangan mengubah semantik arsip/restore DEC-21.
