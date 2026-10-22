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
