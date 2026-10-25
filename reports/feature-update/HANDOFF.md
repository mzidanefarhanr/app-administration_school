# Handoff Pengembangan Aplikasi Sekolah

Checkpoint penutupan sesi: 2026-10-26 (WIB). Branch `main`.
Baseline implementasi akhir 03d: `7bed397c30f5a38e6a34002157c6ac33c0317c89` ("feat(phase-03d): implement student rombel assignment and transfer UI").
Baseline keputusan DEC-23: `01c953e` ("docs(decisions): record owner decisions for student rombel assignment and transfer (DEC-23)").
Baseline implementasi 03c: `e15f028`.
Status fase:
- **Fase 01, 02, dan 03 (03a, 03b, 03c)**: **DITERIMA LENGKAP & DITUTUP** oleh pemilik.
- **Subfase 03d (scope tambahan)**: **IMPLEMENTED & FULLY VERIFIED (AGEN) / MANUAL ACCEPTANCE PENDING**.
- Kebijakan commit/push: commit dan push per fase/koreksi ke branch kerja aktif telah **diotorisasi**; merge, force-push, rewrite history, dan deploy manual **tetap tidak diotorisasi**.
- Catatan perubahan pemilik: `console.txt` dan `vue-app/test-results/` adalah artefak lokal dan **tidak** di-commit.
- **Peringatan Batas:** Penutupan sesi ini murni dokumentasi handoff; bukan approval manual pemilik dan bukan security clearance.

---

## 0. Checkpoint Penutupan Sesi (2026-10-26)

- **HEAD / Remote**: `7bed397c30f5a38e6a34002157c6ac33c0317c89`, branch `main`, sinkron `origin/main`. Working tree bersih selain artefak lokal `console.txt` dan `vue-app/test-results/`.
- **Status Subfase 03d**: Implementasi selesai, pengujian otomatis agen lulus; checklist manual peninjauan pemilik `PHASE-03D-MANUAL-REVIEW.md` (#1–#15) berstatus **PENDING**.
- **Server UAT Aktif**: Backend loopback port `18021` dan SPA loopback port `14021` strict port dibiarkan berjalan untuk peninjauan pemilik.

---

## 1. Bukti Aktual Subfase 03d (Hasil Agen)

1. **Backend Automated Tests (SQLite In-Memory Murni)**:
   - `php artisan test <28-test-files>` -> **235 passed (0 failed)** across 28 test files, total **1099 assertions** dalam 235.78s di atas PHP 8.2.35.
   - Pengecualian eksplisit: `MariaDbIntegrationTest.php`, `MariaDbStudentIntegrationTest.php`, dan `MariaDbRombelConcurrencyTest.php` diexclude dari suite SQLite agar tidak membuka koneksi MariaDB.
   - Cakupan khusus `StudentRombelAssignmentTest.php`: **14 passed (60 assertions)** mencakup validasi input 422, otorisasi 401/403, penolakan siswa terarsip, conflict 409, dan transaksi atomik.
2. **MariaDB Concurrency Integration Tests**:
   - Dijalankan pada database disposable khusus `school_03d_integration` pada MariaDB port 3306 (user `school_compat_app`).
   - `MariaDbRombelConcurrencyTest.php` -> **2 passed (6 assertions)** dalam 2.11s:
     - Serialisasi dua Assign konkuren dengan `expected_student_version` sama via row lock: tepat satu 200/commit, satu 409 Conflict.
     - Penolakan penempatan siswa terarsip via trigger `trg_student_rombels_single_active_insert` (SQLSTATE 45000).
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

## 2. Catatan Review Source & Bukti untuk Sesi Berikutnya

Sesi berikutnya disarankan melakukan review mendalam terhadap source dan rekonsiliasi bukti sebelum menutup 03d:
1. **Penyelarasan Istilah Tahun Ajaran**:
   - Periksa konsistensi antara istilah keputusan *"tahun ajaran terpilih"* dengan implementasi yang terkadang menggunakan label *"tahun ajaran aktif"*. Pastikan tidak ada pembatasan bisnis tambahan yang mengunci tahun masa lalu jika tidak diinstruksikan pemilik.
2. **Verifikasi Jalur Mutasi & Rollback**:
   - Audit kembali verifikasi versi siswa (`students.version`) vs versi membership (`student_rombels.version`).
   - Verifikasi preservasi riwayat keanggotaan lama (`latest_student_exit_date = $todayWib`, `student_status_id` tetap Aktif/1, bukan Mutasi Keluar).
   - Verifikasi atomisitas transaksi: kegagalan insert membership baru atau audit log harus me-rollback penutupan keanggotaan lama secara utuh.
3. **Rekonsiliasi Matriks Konkurensi (Bukti Aktual vs Target Rencana)**:
   - **Dua Assign konkuren**: TERBUKTI pada `MariaDbRombelConcurrencyTest` (tepat 1 sukses, 1 konflik 409).
   - **Dua Transfer konkuren**: **NOT RUN / PENDING** pada database MariaDB.
   - **Race Archive vs Assign**: Uji saat ini baru membuktikan penolakan siswa yang *sudah* terarsip (`deleted_at IS NOT NULL`), **bukan bukti race condition simultan** saat transaksi Archive dan Assign dieksekusi bersamaan di dua thread konkuren. Status: **NOT RUN / PENDING**.
   - **Race Archive vs Transfer**: **NOT RUN / PENDING** pada database MariaDB.
   - *Catatan Penting:* Skenario yang belum dibuktikan secara deterministik di atas **TIDAK BOLEH diklaim PASS** hanya berdasarkan dua tes yang dilaporkan.

---

## 3. Status Lingkungan & Server UAT (Pemeriksaan Read-Only)

- **PHP Global Operasional**: `C:\xampp\php82-operational\php.exe` (PHP 8.2.35 ZTS x64, loaded ini: `C:\xampp\php82-operational\php.ini`).
- **Node Portable Patched**: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\runtime\node-v24.22.1\node-v24.22.1-win-x64\node.exe` (v24.22.1, npm 11.19.1).
- **Proses Server UAT yang Terverifikasi Berjalan**:
  - **API Backend**: Port `18021` (PID listener `15960`, parent PID `3812`/`21044`, executable `C:\xampp\php82-operational\php.exe`).
  - **SPA Frontend**: Port `14021` (PID listener `17964`, executable Node portable `v24.22.1`, strict port).
  - **Server Host Bersama**: Apache port `80`/`443` (PID `16684`), MariaDB port `3306` (PID `1840`).
- **Peringatan Kelangsungan Proses**:
  - Proses server UAT di atas dapat berhenti secara otomatis ketika sesi terminal atau proses parent ditutup.
  - **Handoff ini tidak menjamin server akan tetap hidup saat sesi baru dimulai.** Sesi berikutnya wajib memeriksa ulang listener port 18021 dan 14021 sebelum pengujian.
- **Lokasi Kredensial Privat Lokal (Tanpa Membaca/Menyalin Isi)**:
  - Password database UAT & integration: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\secret_db_pass.txt`
  - Password akun sintetis UAT: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\secret_uat_password.txt`
  - *Catatan:* Dilarang mencetak atau menyalin isi kredensial tersebut ke dalam dokumen git atau log publik.

---

## 4. Batas Keamanan yang Dipertahankan

1. **Containment Apache Proyek Sekolah**:
   - `Include "conf/extra/httpd-school-containment.conf"` aktif di `httpd.conf:573`.
   - Seluruh akses HTTP/HTTPS loopback ke direktori sekolah, `.env`, `.git/HEAD`, log, dan `public/` tetap mengembalikan **403 Forbidden**.
2. **Risiko yang Tetap OPEN**:
   - Advisory dependency lockfile: 48 Composer advisories & 20 npm vulnerabilities tersisa.
   - Lifecycle framework/runtime: Laravel 10 berstatus EOL sejak Feb 2025; PHP 8.2 security support berakhir 31 Des 2026.
   - Paparan historis host: File `.env` dan log berpotensi terekspos sebelum containment Apache aktif pada sesi terdahulu. Tindak lanjut rotasi rahasia produksi tetap merupakan pekerjaan terpisah.
3. **Isolasi Database & Host**:
   - Database operasional `backdata_administrations`, database UAT lama, dan seluruh resource/database App Gudang tidak disentuh.
   - Tidak ada modifikasi pada konfigurasi Apache, PHP global, service Windows, atau firewall host.

---

## 5. Ringkasan Status Roadmap & Langkah Sesi Berikutnya

| Subfase | Deskripsi | Status Teknis | Review Pemilik |
|---|---|---|---|
| **00** | Audit baseline & lingkungan | PARTIAL | PENDING |
| **01 (01a–01c)** | Hak akses, auth, isolasi fixture | IMPLEMENTED & FULLY VERIFIED | **DITERIMA LENGKAP & DITUTUP** |
| **02 (02a–02c)** | Audit terpercaya & backup/restore | IMPLEMENTED & FULLY VERIFIED | **DITERIMA LENGKAP & DITUTUP** |
| **03 (03a–03c)** | Pendataan siswa lengkap & aman | IMPLEMENTED & FULLY VERIFIED | **DITERIMA LENGKAP & DITUTUP** |
| **03d (tambahan)** | Penempatan & perpindahan rombel UI | **IMPLEMENTED & FULLY VERIFIED (AGEN)** | **MANUAL ACCEPTANCE PENDING** (#1–#15) |
| **04 (04a–04b)** | Guru dan pegawai | NOT STARTED | PENDING (Menunggu review 03d & DEC-04) |

**Langkah untuk Sesi Baru**:
1. Verifikasi status proses/listener port 18021 dan 14021. Jika terhenti pasca-tutup terminal, jalankan kembali dengan executable absolut PHP 8.2 global dan Node portable 24.22.1 tanpa mengubah konfigurasi.
2. Tunggu konfirmasi peninjauan manual pemilik terhadap checklist `PHASE-03D-MANUAL-REVIEW.md` (#1–#15).
3. Setelah persetujuan manual 03d diberikan:
   - Catat approval manual pada commit dokumentasi tersendiri.
   - Diskusikan arah pekerjaan berikutnya sesuai `reports/feature-update/NEXT-STEPS-PLAN.md` (Fase 04a Guru dan Pegawai & keputusan DEC-04).
