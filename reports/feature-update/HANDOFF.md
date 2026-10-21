# Handoff pengembangan aplikasi sekolah

Checkpoint terbaru: 2026-10-21 (WIB). Branch `main`.
Baseline backend terakhir: `e77f335` ("feat(phase-03b): implement student CRUD with optimistic locking, administrative archive, and audit privacy").
Baseline penerimaan manual 03b: `cbd25bd` ("feat(phase-03b): add student CRUD UI, archive/restore flows, and 03b concurrency tests").
Kebijakan commit/push: commit dan push per fase/koreksi ke branch kerja aktif telah **diotorisasi**; merge, force-push, rewrite history, dan deploy manual **tetap tidak diotorisasi**.

Catatan perubahan pemilik: `console.txt` dan `vue-app/test-results/` adalah artefak lokal dan **tidak** di-commit.

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
  - **Subfase 03c** — Daftar dan Rekap Siswa: NOT STARTED. Rencana teknis disusun (`reports/feature-update/PHASE-03C-PLAN.md`) dan menunggu review pemilik; implementasi belum diotorisasi.
- **Keputusan Terbuka yang Masih OPEN (belum menghambat)**:
  1. Makna `status_user_id`.
  2. Deployment CORS/Sanctum produksi.
  3. Strategi pencabutan token dibanding sesi.
- **Batasan Cakupan**:
  - Single-school (DEC-01); keunikan NIS level tabel `students`.
  - Task scheduler OS belum diaktifkan.
  - Tidak ada izin deployment produksi.
  - UI 03b tidak membuat keanggotaan rombel; siswa baru belum tampil di daftar per tahun ajaran sampai ditempatkan di rombel.
  - Pagination server dan rekap chart tetap scope **03c**.

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

## 4. Status Tugas Subfase 03b
1. **Backend CRUD + optimistic locking + arsip administratif**: SELESAI (commit `e77f335`).
2. **UI create/edit/detail/archive/restore + 422/409/version/loading/izin/English**: IMPLEMENTED & DIVERIFIKASI agen.
3. **Bukti konkurensi 03b**: SELESAI (2 integration test MariaDB disposable PASS).
4. **Browser UAT**: PASS oleh agen pada server UAT lokal.
5. **Penerimaan manual pemilik**: **APPROVED** (checklist #1–#13, 2026-10-21 WIB, baseline `cbd25bd`). Status Subfase 03b: **DITERIMA PEMILIK**.
6. **Catatan klasifikasi bukti**: penerimaan manual pemilik terpisah dari bukti otomatis/browser agen. ESLint terakhir 22 errors / 2609 warnings (**bukan PASS**). Penerimaan bukan izin deployment/akses data operasional.

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
- Dibuat `UAT Browser Invalid Nik` (NIK `9876543210123456`, NIS `UAT-BROWSER-001`).
- `Synthetic Student One` diubah menjadi `Synthetic Student One (B)`, diarsipkan (`UAT browser archive test`), lalu di-restore; rombel lamanya tetap non-aktif sesuai DEC-21.

---

## 6. Langkah Berikutnya
1. Subfase 03b **DITERIMA PEMILIK** (penerimaan manual #1–#13, baseline `cbd25bd`). Penerimaan 03a tetap dipertahankan.
2. Rencana teknis **Subfase 03c** disusun (`reports/feature-update/PHASE-03C-PLAN.md`) dan menunggu review pemilik. Fase 03 tetap **PARTIAL** sampai 03c selesai.
3. **Jangan memulai implementasi 03c** sebelum rencana disetujui pemilik.
4. Keputusan pemilik yang dibutuhkan tercantum pada bagian keputusan di rencana 03c.
