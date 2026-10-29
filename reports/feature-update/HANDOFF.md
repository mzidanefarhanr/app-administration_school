# Handoff Pengembangan Aplikasi Sekolah

Checkpoint penutupan sesi: 2026-10-26 (WIB). Branch `main`.
Baseline implementasi akhir 03d: `7bed397c30f5a38e6a34002157c6ac33c0317c89` ("feat(phase-03d): implement student rombel assignment and transfer UI").
Baseline keputusan DEC-23: `01c953e` ("docs(decisions): record owner decisions for student rombel assignment and transfer (DEC-23)").
Baseline implementasi 03c: `e15f028`.
Status fase:
- **Fase 01, 02, dan 03 (03a, 03b, 03c, serta subfase tambahan 03d)**: **DITERIMA LENGKAP & DITUTUP** oleh pemilik (checklist manual #1–#15 APPROVED 2026-10-26 WIB).
- **Subfase 03d (scope tambahan)**: **DITERIMA LENGKAP & DITUTUP OLEH PEMILIK**.
- Kebijakan commit/push: commit dan push per fase/koreksi ke branch kerja aktif telah **diotorisasi**; merge, force-push, rewrite history, dan deploy manual **tetap tidak diotorisasi**.
- Catatan perubahan pemilik: `console.txt` dan `vue-app/test-results/` adalah artefak lokal dan **tidak** di-commit.
- **Peringatan Batas:** Penutupan sesi ini murni dokumentasi handoff; bukan security clearance, bukan izin deploy/upgrade, dan bukan otorisasi akses ke database operasional.

---

## 0a. Checkpoint Re-verifikasi 04a (2026-10-29)

- **Konteks**: Sesi 2026-10-26 sudah mengimplementasikan dan mem-push 04a (`ecb2ea3`). Sesi 2026-10-29 memverifikasi ulang hasil tersebut secara independen dan memperbaiki gap terhadap instruksi pemilik.
- **Gap yang diperbaiki**:
  1. Penanda `legacy_profile_at` (migration `2029_07_25_000001`, additive) membatasi fallback NIK hanya pada baris pra-04a; profil baru dan akun yang dilepas/hard-deleted tidak bisa ter-relink.
  2. `EmployeeBackfillService` atomik, mengecualikan akun soft-deleted, dan hanya memproses baris legacy.
  3. `down()` migration 04a fail-closed sebelum DDL (NULL `user_nik`/`subject_id` atau `user_id` terhubung) dan rebuild SQLite dibungkus transaksi.
  4. NUPTK dikeluarkan dari allowlist audit.
  5. Command `app:employee-account-backfill --dry-run` dengan keluaran agregat tanpa PII.
  6. Test MariaDB memakai user khusus DB `school_04a` (bukan `school_compat_app` yang memiliki grant pada DB review lain).
- **Bukti aktual 2026-10-29**: SQLite `EmployeeSchemaAndAccountRelationTest` **21 passed / 111 assertions**; MariaDB `MariaDbEmployeeIntegrationTest` **6 passed / 31 assertions** pada `school_04a_integration` yang dibuat ulang; `FactoryIntegrityTest` 3/26 dan `StudentRombelAssignmentTest` 16/78 PASS; Pint --test PASS.
- **NOT RUN**: `MariaDbRombelConcurrencyTest` (target `school_restore_drill_uat` di luar allowlist sesi).
- **Status 04a**: **DITERIMA PEMILIK** — checklist manual #1–#13 APPROVED 2026-10-29 WIB berdasarkan pernyataan pemilik ("Pengujian nomor 1-13 sudah berhasil dan saya approved"); agen tidak menjalankan ulang pengujian untuk mencatat status. 04b **diotorisasi 2026-10-29 WIB (DEC-24)** dan implementasinya berjalan. Migration baru belum diterapkan ke UAT.

---

## 0. Checkpoint Penutupan Sesi (2026-10-26)

- **HEAD / Remote**: sinkron `origin/main`. Working tree bersih selain artefak lokal `console.txt` dan `vue-app/test-results/`.
- **Status Subfase 03d**: **DITERIMA LENGKAP & DITUTUP OLEH PEMILIK**. Seluruh checklist manual `PHASE-03D-MANUAL-REVIEW.md` (#1–#15) disetujui penuh (APPROVED) pada 2026-10-26 WIB.
- **Server UAT Aktif**: Backend loopback port `18021` dan SPA loopback port `14021` strict port dibiarkan berjalan untuk kebutuhan review selanjutnya.

---

## 1. Bukti Aktual Subfase 03d (Hasil Agen)

1. **Backend Automated Tests (SQLite In-Memory Murni)**:
   - `php artisan test <28-test-files>` -> **237 passed (0 failed)** across 28 test files, total **1117 assertions** di atas PHP 8.2.35.
   - Pengecualian eksplisit: `MariaDbIntegrationTest.php`, `MariaDbStudentIntegrationTest.php`, dan `MariaDbRombelConcurrencyTest.php` diexclude dari suite SQLite agar tidak membuka koneksi MariaDB.
   - Cakupan khusus `StudentRombelAssignmentTest.php`: **16 passed (78 assertions)** mencakup validasi input 422, otorisasi 401/403, penolakan siswa terarsip, conflict 409, transaksi atomik, dan **rollback kegagalan audit** (assign dan transfer atomik).
2. **MariaDB Concurrency Integration Tests**:
   - Dijalankan pada database disposable khusus `school_03d_integration` pada MariaDB port 3306 (user `school_compat_app` dengan guard koneksi eksak).
   - Eksekusi worker nyata melalui pipeline controller aktual (`concurrency_worker.php`) dengan verifikasi tumpang tindih lock InnoDB (`SHOW FULL PROCESSLIST` / `information_schema.innodb_trx` / `innodb_lock_waits`).
   - `MariaDbRombelConcurrencyTest.php` -> **7 passed (43 assertions)** dalam 10.55s:
     - Dua Assign konkuren serial via row lock (1 commit 200, 1 ditolak 409 conflict).
     - Penolakan siswa terarsip pre-existing (non-race) via trigger MariaDB.
     - Dua Transfer konkuren simultan pada siswa/membership yang sama (1 commit 200, 1 ditolak 409 conflict).
     - Race Archive vs Assign (Assign menang lock: Assign 200, Archive 409).
     - Race Archive vs Assign (Archive menang lock: Archive 200, Assign 422).
     - Race Archive vs Transfer (Transfer menang lock: Transfer 200, Archive 409).
     - Race Archive vs Transfer (Archive menang lock: Archive 200, Transfer 422).
3. **Frontend Tests, Build & Lint**:
   - Unit tests: `node --test` (Node portable v24.22.1) -> **18 passed (0 failed)** dalam 1090ms.
   - Production build: `npm run build` -> **PASS** (Vite 6.3.5, 789 modules transformed dalam 1m 9s).
   - Pint: **PASS** (100% clean pada seluruh berkas PHP yang dibuat/dimodifikasi).
   - Targeted lint: `npx eslint src/views/pages/public/Student.vue src/main.js` -> **0 errors, 2750 warnings** (seluruh warning adalah CRLF `prettier/prettier Delete ␍`, **bukan PASS bersih**).
4. **Smoke Test UAT Agen**:
   - Metode: **HTTP/API fetch script via Node** (bukan browser visual / Playwright MCP).
   - Hasil: **16 skenario PASS** mencakup CSRF bootstrap, login operator 204, check-auth abilities, student list & recap (school_year_id=1), filter placed/unplaced, search formula, detail, edit version 1->2, stale edit 409 conflict, archive version 2->3, archived list view, restore version 3->4, export CSV, logout 204, kepsek view-only checks (view 200, create 403, archived 403, export 200), dan waka role checks (view 200, export 403).
5. **Penerimaan Manual Pemilik**:
   - Checklist `reports/feature-update/PHASE-03D-MANUAL-REVIEW.md` (#1–#15) tetap berstatus **PENDING** menunggu review manual pemilik.

---

## 2. Catatan Rekonsiliasi Bukti yang Telah Diselesaikan

Gap pengujian yang diidentifikasi pada checkpoint sebelumnya telah dibuktikan secara deterministik:
1. **Rollback Kegagalan Audit**:
   - Terbukti pada `StudentRombelAssignmentTest.php`: kegagalan audit pada Assign dan Transfer membatalkan seluruh perubahan domain (membership baru batal tersimpan, membership sumber batal ditutup, versi siswa dan membership tetap utuh, tidak ada audit parsial).
2. **Matriks Konkurensi MariaDB Lengkap (7 Skenario PASS)**:
   - **Dua Assign konkuren**: TERBUKTI (1 sukses 200, 1 konflik 409).
   - **Dua Transfer konkuren**: TERBUKTI (1 sukses 200, 1 konflik 409).
   - **Race Archive vs Assign (kedua urutan lock)**: TERBUKTI (Assign duluan -> Archive ditolak 409; Archive duluan -> Assign ditolak 422).
   - **Race Archive vs Transfer (kedua urutan lock)**: TERBUKTI (Transfer duluan -> Archive ditolak 409; Archive duluan -> Transfer ditolak 422).
   - **Pemisahan Tes Non-Race vs Race Aktual**: Pengujian penolakan trigger terhadap data terarsip pre-existing secara tegas dipisahkan dari pengujian race condition simultan.

---

## 3. Status Lingkungan, Server UAT, dan Pemulihan Kredensial

- **PHP Global Operasional**: `C:\xampp\php82-operational\php.exe` (PHP 8.2.35 ZTS x64, loaded ini: `C:\xampp\php82-operational\php.ini`).
- **Node Portable Patched**: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\runtime\node-v24.22.1\node-v24.22.1-win-x64\node.exe` (v24.22.1, npm 11.19.1).
- **Proses Server UAT yang Terverifikasi Berjalan**:
  - **API Backend**: Port `18021` (PID listener `15960`, parent PID `3812`/`21044`, executable `C:\xampp\php82-operational\php.exe`).
    - *Working Directory / Serving Checkout*: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\staging\checkout\laravel-api` (`--env=uat`).
    - *Database Target*: `school_compat_uat` pada MariaDB port 3306.
  - **SPA Frontend**: Port `14021` (PID listener `17964`, executable Node portable `v24.22.1`, strict port).
    - *Serving Checkout*: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\staging\checkout\vue-app` (`--mode uat`).
  - **Server Host Bersama**: Apache port `80`/`443` (PID `16684`), MariaDB port `3306` (PID `1840`).
- **Diagnosis Eksekusi Seeder Pemilik & Transparansi Akses Database**:
  - Pemilik menjalankan `UatSeeder` pada checkout utama `C:\xampp\htdocs\app-administration_school\laravel-api`.
  - Berkas `.env.uat` pada checkout utama merujuk ke database `school_access_uat` (basis data UAT Fase 01a terdahulu). Akibatnya, `updated_at` akun `uat.%` pada `school_access_uat` terupdate ke `2026-10-26 07:08:49`.
  - *Transparansi Pemeriksaan Luar Target*: Saat diagnosis, pemeriksaan read-only (`SELECT`) dijalankan terhadap `school_access_uat` dan `backdata_administrations` untuk mengidentifikasi divergensi target seeder pemilik. Akses diagnostik ini dicatat secara transparan berada di luar target DB yang diotorisasi (`school_compat_uat`). Akses tersebut murni baca tanpa ada mutasi data.
  - *Catatan Database Operasional*: Query `SELECT COUNT(*)` pada `backdata_administrations` mengembalikan 0 baris akun `uat.%`. Namun, ketiadaan baris akun tersebut dicatat tidak membuktikan keselamatan atau ketiadaan perubahan historis DB secara menyeluruh.
  - Karena server UAT aktif di port 18021 dilayani oleh checkout terisolasi yang membaca database `school_compat_uat`, password akun di `school_compat_uat` belum tersinkronisasi.
- **Tindakan Pemulihan Akses Login**:
  - Menghasilkan kata sandi acak baru yang aman secara lokal dan memperbarui berkas privat `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\secret_uat_password.txt`.
  - Mutasi pemulihan dibatasi secara eksklusif hanya pada database terotorisasi `school_compat_uat` via PDO terproteksi pre-bootstrap guard untuk mereset password `uat.operator` dan `uat.kepsek`.
  - Atribut akun lainnya, peran (`operator-sekolah`, `kepala-sekolah`), status aktif (`1`), dan data siswa tetap utuh.
- **Hasil Verifikasi Otomatis Login UAT (HTTP/API)**:
  - `uat.operator`: CSRF 204, Login 204, Check-auth 200 (role: `operator-sekolah`, ability `students.manage` PRESENT, `backup.manage` ABSENT), Logout 204, sesi dibersihkan (authenticated = false) -> **PASS**.
  - `uat.kepsek`: CSRF 204, Login 204, Check-auth 200 (role: `kepala-sekolah`, ability `students.view` PRESENT, `students.manage` ABSENT), Logout 204, sesi dibersihkan -> **PASS**.
  - Review manual di browser tetap berstatus **PENDING** untuk diuji oleh pemilik.
- **Lokasi Kredensial Privat Lokal (Tanpa Membaca/Menyalin Isi ke Laporan/Git)**:
  - Password database UAT & integration: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\secret_db_pass.txt`
  - Password akun sintetis UAT: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\secret_uat_password.txt`
  - *Perintah membaca password bagi pemilik:* `Get-Content "C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\secret_uat_password.txt"`

---

## 4. Batas Keamanan yang Dipertahankan

1. **Containment Apache Proyek Sekolah**:
   - `Include "conf/extra/httpd-school-containment.conf"` aktif di `httpd.conf:573`.
   - Seluruh akses HTTP/HTTPS loopback ke direktori sekolah, `.env`, `.git/HEAD`, log, dan `public/` tetap mengembalikan **403 Forbidden**.
2. **Risiko yang Tetap OPEN**:
   - Advisory dependency lockfile: 48 Composer advisories & 20 npm vulnerabilities tersisa.
   - Lifecycle framework/runtime: Laravel 10 berstatus EOL sejak Feb 2025; PHP 8.2 mendekati akhir masa dukungan keamanan (31 Des 2026), bukan sudah EOL pada tanggal pencatatan ini.
   - Paparan historis host: File `.env` dan log berpotensi terekspos sebelum containment Apache aktif pada sesi terdahulu. Tindak lanjut rotasi rahasia produksi tetap merupakan pekerjaan terpisah.
3. **Isolasi Database & Host**:
   - Mutasi data dibatasi ketat pada database terotorisasi (`school_compat_uat` untuk kredensial UAT; `school_03d_integration` dan `school_04a_integration` untuk tes integrasi). Pemeriksaan diagnostik read-only terhadap `school_access_uat` dan `backdata_administrations` dicatat transparan di Bagian 3 sebagai akses di luar otorisasi tanpa mutasi data. Resource/database App Gudang tidak disentuh.
   - Tidak ada modifikasi pada konfigurasi Apache, PHP global, service Windows, atau firewall host.

---

## 5. Ringkasan Status Roadmap & Langkah Sesi Berikutnya

| Subfase | Deskripsi | Status Teknis | Review Pemilik |
|---|---|---|---|
| **00** | Audit baseline & lingkungan | PARTIAL | PENDING |
| **01 (01a–01c)** | Hak akses, auth, isolasi fixture | IMPLEMENTED & FULLY VERIFIED | **DITERIMA LENGKAP & DITUTUP** |
| **02 (02a–02c)** | Audit terpercaya & backup/restore | IMPLEMENTED & FULLY VERIFIED | **DITERIMA LENGKAP & DITUTUP** |
| **03 (03a–03c)** | Pendataan siswa lengkap & aman | IMPLEMENTED & FULLY VERIFIED | **DITERIMA LENGKAP & DITUTUP** |
| **03d (tambahan)** | Penempatan & perpindahan rombel UI | IMPLEMENTED & FULLY VERIFIED | **DITERIMA LENGKAP & DITUTUP** (Checklist #1–#15 APPROVED) |
| **04a** | Schema profil pegawai dan hubungan akun | **IMPLEMENTED & FULLY VERIFIED (AGEN)** | **DITERIMA PEMILIK** (checklist #1–#13 APPROVED 2026-10-29 WIB per pernyataan pemilik) |
| **04b** | CRUD pegawai dan menu aktif | IMPLEMENTASI BERJALAN (sesi 2026-10-29) | PENDING |

**Langkah untuk Sesi Baru**:
1. ~~Pemilik meninjau checklist review fondasi Subfase 04a~~ — **SELESAI 2026-10-29**: checklist `PHASE-04A-MANUAL-REVIEW.md` #1–#13 APPROVED oleh pemilik.
2. ~~Subfase 04b menunggu otorisasi terpisah~~ — **DIOTORISASI 2026-10-29 WIB**: pemilik menyetujui D1–D8 (DEC-24) dan menginstruksikan eksekusi; implementasi berjalan dalam batas `PHASE-04B-PLAN.md`; hasil dan sisa pekerjaan dicatat di `PHASE-04B-REPORT.md`.
3. Terapkan migration additive `2029_07_25_000001` dan `2029_07_26_000001` hanya pada lingkungan yang diotorisasi (UAT belum diotorisasi).
4. Pertahankan server UAT loopback 18021/14021 dan database `school_compat_uat`.
5. Seluruh risiko keamanan terbuka (advisories, lifecycle Laravel/PHP, rotasi kunci produksi) tetap OPEN.
