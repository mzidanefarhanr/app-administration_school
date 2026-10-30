# Roadmap update fitur aplikasi sekolah

Versi dokumen: 1.0, 14 Oktober 2026 (WIB); audit HEAD 15 Oktober 2026.
Baseline kode: `b02a87f0d57adffec297ed465a8afcd8e8d7ec59`; HEAD audit `b699feccd65357a10f7430c165400906e6394d49` (delta = paket docs saja, kode tidak berubah).
Historical drafting status (2026-10-14/15): DRAFT; Fase 00 PARTIAL. The original “all other phases not started” statement is obsolete; current statuses are in the table and correction addendum below.

## Correction addendum — 2026-10-30 (Subfase 05a)

DEC-25 (APPROVED 2026-10-30 WIB) authorizes Phase 05 with: all-or-nothing commit; student match by NIS then NIK (employee by NIK in 05b); UTF-8 CSV, 5 MB / 2,000 rows; full English; `students.import` for Admin Sistem and Operator Sekolah only; private error reports with manual 30-day retention; separate `.uat-phase05`; 05b = employee import + export column options, school recap deferred pending a format sample (DEC-12 PROPOSED).

**05a is IMPLEMENTED and agent-verified, owner review PENDING.** Isolated `.uat-phase05` SQLite (SPA `127.0.0.1:14023`, API `127.0.0.1:18023`, cookie `school_phase05_session`, launcher `scripts/phase05-uat.php`): impor tests 18/97, regression 170/1097 (13 skipped), Pint 16 files, frontend 3 node tests, build 26.02 s; browser DOM valid/mixed/update/template flows with 0 console errors. Owner manual checklist 05a remains PENDING; agent evidence is not owner acceptance. Implementation `58828a6` and documentation `a3e19d4` are pushed to `origin/main` so the package can be reviewed on GitHub. Per owner instruction, review packages are pushed before manual checks; commit/push is not approval.

## Correction addendum — 2026-10-29

Baseline `main` at `e281419` (corrections `dc5e031`, documentation `e281419`), pushed to `origin/main`. **04a and 04b are owner-accepted**: 04b checklist #1–#18 APPROVED per the owner's statement on 2026-10-30 WIB, recorded separately from agent evidence. Full-English application presentation, Teachers = Guru only (`Pendidik`), Employees = non-teachers (`Tenaga Kependidikan`), classification only by `employee_type_id`, canonical jobs retained, and >=100 synthetic rows per empty new UAT feature are current requirements.

New isolated UAT: SPA `http://127.0.0.1:14022`, API `http://127.0.0.1:18022`, root `.uat-phase04b/database.sqlite`, private environment/storage and `school_phase04b_session`; guarded launcher `laravel-api/scripts/phase04b-uat.php` (`init`/`prepare`/`status`), normal migrations only. Never root `artisan --env=uat` or legacy/main/MariaDB targets. Baseline 100 synthetic teachers + 100 staff (15 jobs), current 101 each after browser creates, 103 students, 100 synthetic schools (NPSN `04` namespace); edited/archived rows preserved, only initial five UAT accounts, runtime/credentials ignored.

See `PHASE-04B-REPORT.md` for latest explicit SQLite **96 tests/1738 assertions PASS**, frontend **42 tests PASS**, final **build PASS, 18.77 s**, targeted style scopes, and earlier overlapping runs (not aggregated). Operator browser DOM/API-readback checks include CRUD/archive/restore/link/unlink, route separation, English CSV and staff mobile fit/scroll; separate three-role HTTP permissions PASS is not other-role browser UI coverage. Agent-side other-role browser UI, two-tab stale/link-conflict, print-preview/screenshot visual checks and 04b MariaDB DDL/InnoDB remain NOT RUN; owner acceptance does not convert these to PASS. Initial evidence below remains dated history. The owner's 2026-10-30 WIB statement approves 04b but does not authorize deployment, legacy/production migration, merge or dependency changes; corrections are committed (`dc5e031`, `e281419`) and pushed without service/dependency changes.

## Urutan dan prioritas
Fondasi keamanan + backup dahulu; penyelesaian pendataan; pengolahan massal dan tahun ajaran; surat/izin/arsip; pembelajaran; analisis; optimasi dan QA. Backup didahulukan pada Fase02 dan diuji ulang setelah seluruh modul lampiran berkembang. Data operasional tidak digunakan sebelum fondasi dan restore drill siap.

| Fase | Scope | Dependensi | Status teknis | Review |
|---|---|---|---|---|
| 00 | [Audit baseline dan kesiapan lingkungan](phases/PHASE-00-BASELINE.md) | Tidak ada | PARTIAL (source+env audit; test/build/browser NOT RUN) | PENDING |
| 01 | [Hak akses, autentikasi dan lingkungan aman](phases/PHASE-01-ACCESS.md) | 00; DEC-03 untuk 01a | **IMPLEMENTED & VERIFIED** (01a, 01b, 01c selesai; 82 test backend PASS; 15 unit test frontend PASS; UAT browser PASS) | **DITERIMA LENGKAP & DITUTUP** (01a APPROVED 2026-10-15; 01b APPROVED 2026-10-16/17; 01c APPROVED 2026-10-17 WIB) |
| 02 | [Audit terpercaya dan backup yang dapat dipulihkan](phases/PHASE-02-BACKUP-AUDIT.md) | 01; DEC-02 dan DEC-09 untuk backup | **IMPLEMENTED & VERIFIED** (02a, 02b, 02c selesai; 154 test backend PASS; round-trip MariaDB PASS) | **DITERIMA LENGKAP & DITUTUP** (02a APPROVED 2026-10-19; 02b APPROVED 2026-10-20; 02c APPROVED 2026-10-20 WIB) |
| 03 | [Pendataan siswa lengkap dan aman](phases/PHASE-03-STUDENTS.md) | 01; 02 sebelum data operasional; DEC-05, DEC-20 | **IMPLEMENTED & VERIFIED** (03a, 03b, 03c selesai; 229 test backend PASS; 18 unit test frontend PASS; UAT browser & smoke PASS) | **DITERIMA LENGKAP & DITUTUP** (03a APPROVED 2026-10-21; 03b APPROVED 2026-10-21; 03c APPROVED 2026-10-26 WIB) |
| 04 | [Guru dan pegawai](phases/PHASE-04-EMPLOYEES.md) | 01; 02; DEC-04; DEC-24 | **04a and 04b IMPLEMENTED and owner-accepted** (latest SQLite 96/1738, frontend 42/build PASS; scoped operator browser + three-role HTTP; agent-side other-role browser UI, two-tab/print/screenshot and 04b MariaDB NOT RUN) | **04a APPROVED 2026-10-29; 04b #1–#18 APPROVED 2026-10-30 WIB per owner statement** |
| 05 | [Impor, ekspor dan kualitas data](phases/PHASE-05-IMPORT-EXPORT.md) | 03,04; DEC-08, DEC-25 | **IN PROGRESS — 05a IMPLEMENTED (agent-verified 2026-10-30)**; 05b (impor pegawai + pilihan kolom ekspor) dan 05c NOT STARTED | **PENDING** (checklist manual 05a belum dijalankan pemilik) |
| 06 | [Siklus tahun ajaran dan rombel](phases/PHASE-06-ACADEMIC-YEAR.md) | 03,04; DEC-05 | NOT STARTED | PENDING |
| 07 | [Persuratan dan generator dokumen](phases/PHASE-07-LETTERS.md) | 01,02; siswa/guru siap untuk auto-fill; DEC-06,11 | NOT STARTED | PENDING |
| 08 | [Perizinan siswa dan guru](phases/PHASE-08-LEAVES.md) | 01,03,04; file privat Fase07a atau service ekuivalen; DEC-07 | NOT STARTED | PENDING |
| 09 | [Arsip dokumen terpusat](phases/PHASE-09-ARCHIVES.md) | File privat Fase07; DEC-13 | NOT STARTED | PENDING |
| 10 | [Administrasi pembelajaran](phases/PHASE-10-TEACHING.md) | 04,06; DEC-10 | NOT STARTED | PENDING |
| 11 | [Dashboard operator dan laporan](phases/PHASE-11-ANALYTICS.md) | Modul sumber 03–10; boleh bertahap setelah sumber siap | NOT STARTED | PENDING |
| 12 | [Performa, observability dan konsistensi](phases/PHASE-12-PERFORMANCE.md) | Modul aktif; audit 00 | NOT STARTED | PENDING |
| 13 | [QA akhir, rilis dan handoff operasional](phases/PHASE-13-QA-RELEASE.md) | Fase yang dipilih selesai; keputusan kritis ditutup | NOT STARTED | PENDING |

Setiap fase memiliki subfase, prompt, kriteria dan bukti. Fase11 dashboard dapat bertahap berdasarkan modul sumber yang sudah siap; tidak harus menunggu semua modul untuk mengganti widget demo, tetapi jangan menampilkan angka palsu untuk modul belum tersedia.

## Milestone yang bisa dirilis terpisah
| Milestone | Isi | Prasyarat |
|---|---|---|
| M1 Pendataan | Fase01–06 | Hak akses, backup/restore, CRUD, impor, riwayat periode dan UAT scope |
| M2 Administrasi | M1 + Fase07–09 | Surat, izin, file privat dan UAT scope |
| M3 Pembelajaran | M2 + Fase10–11 | Jadwal, jurnal, missing report dan dashboard nyata |
| M4 Operasional | Scope terpilih + Fase12–13 | Ukuran data/performansi, restore drill kandidat rilis dan runbook |

Ini opsi scope, bukan izin deploy. Fase13 QA dapat diterapkan pada tiap milestone terpilih; fase lain tetap NOT STARTED/deferred bila belum dipilih.

## Aturan status
Teknis: NOT STARTED → IN PROGRESS → PARTIAL/BLOCKED/IMPLEMENTED → VERIFIED untuk cakupan bukti jelas. Review: PENDING → APPROVED/CHANGES REQUESTED. Evidence: SOURCE REVIEW/NOT RUN/PASS/FAIL/BLOCKED/N/A per skenario.
VERIFIED teknis tidak sama dengan APPROVED pemilik; APPROVED manual tidak mengubah skenario belum diuji menjadi PASS. Jangan satu status global menutupi role/backup/browser NOT RUN.

## Kendali scope
Satu subfase = scope reviewable. Branch mengikuti kondisi repo dan instruksi pemilik; contoh nama `feat/school-03b-student-crud`. Commit kecil per concern jika diotorisasi; prefix docs/fix/feat/test/chore sesuai perubahan. Tidak merge main otomatis. Tidak migrasi framework, multi-school, portal publik, pembayaran, AI/OCR atau API Dapodik otomatis tanpa scope tambahan.

## Penggunaan pertama
Baca [prompt OpenCode](../docs/OPENCODE-WORKFLOW.md), jalankan 00a–00b audit saja sesuai instruksi awal, lalu review keputusan untuk 01a. Update baseline source jika HEAD berubah. Tidak mengeksekusi fase lain hanya karena ada dalam roadmap.

## Status penyusunan paket
- [x] Source repo sekolah dibaca.
- [x] Pola dokumentasi App Gudang ditinjau dan diadaptasi.
- [x] Paket Markdown disiapkan; link/struktur diperiksa pada penyusunan.
- [x] Paket dipasang dalam repo pemilik (commit 05eb83f, b699fec).
- [x] Fase00 runtime inventory oleh OpenCode (PARTIAL; laporan PHASE-00-RUNTIME-AUDIT.md).
- [x] Review pemilik atas laporan Fase 00 dan keputusan DEC-03 untuk 01a (APPROVED 2026-10-15).
- [x] Fase 01a — authorization backend: peran/kemampuan + enforcement (IMPLEMENTED; test PASS; verifikasi UAT browser/HTTP PASS pada data sintetis; laporan PHASE-01A-ACCESS.md).
- [x] Keputusan DEC-14 (register/nonaktif/ekspor/legacy/Bahasa UI) APPROVED 2026-10-14 WIB; penyelesaian ekspor siswa (endpoint CSV terlindungi), UI English, koreksi pemetaan legacy.
- [x] Penerimaan manual pemilik Fase 01a (checklist #1–#14 APPROVED 2026-10-15 WIB; laporan PHASE-01A-ACCESS.md §21–§22).
- [x] Fase 01b — implementasi konfigurasi API, alur error autentikasi, dan reset password (DEC-15 APPROVED; laporan PHASE-01B-API-AUTH.md; 60 test backend PASS, 14 unit test frontend PASS; penerimaan manual pemilik: seluruh checklist #1–#16 APPROVED: #1–#7 dan #9–#16 pada 2026-10-16 WIB, #8 retest berhasil pada 2026-10-17 WIB).
- [x] Subfase 01c — isolasi test lanjutan, pemisahan fixture sintetis, validator fixture, dan penanganan BLK-03 (DEC-16 APPROVED; laporan PHASE-01C-ISOLATION-FIXTURES.md; 82 test backend PASS, 15 unit test frontend PASS).
- [x] Penerimaan manual pemilik Fase 01c (checklist #1–#8 di PHASE-01C-MANUAL-REVIEW.md APPROVED 2026-10-17 WIB mengacu baseline 844883e).
- [x] **Fase 01 — Hak akses, autentikasi dan lingkungan aman**: DITUTUP LENGKAP sesuai cakupan roadmap (01a, 01b, 01c seluruhnya DITERIMA PEMILIK).
- [x] Rencana Fase 02 — Audit terpercaya dan backup yang dapat dipulihkan (DEC-17 disetujui pemilik 2026-10-17 WIB).
- [x] Subfase 02a — Audit server dan rollback terbatas: DITERIMA PEMILIK (checklist #1–#9 APPROVED 2026-10-19 WIB mengacu baseline cf94cae; laporan PHASE-02A-AUDIT.md).
- [x] Subfase 02b — Backup manual dan restore drill: DITERIMA PEMILIK (checklist #1–#10 APPROVED 2026-10-20 WIB mengacu baseline e8eaea4; laporan PHASE-02B-BACKUP-RESTORE.md).
- [x] Subfase 02c — Jadwal dan monitoring backup: DITERIMA PEMILIK (checklist #1–#10 APPROVED 2026-10-20 WIB mengacu baseline 94d29f4; laporan PHASE-02C-MONITORING-SCHEDULE.md).
- [x] **Fase 02 — Audit terpercaya dan backup yang dapat dipulihkan**: DITUTUP LENGKAP sesuai cakupan roadmap (02a, 02b, 02c seluruhnya DITERIMA PEMILIK).
- [x] Keputusan DEC-20 (kebijakan identitas siswa, keanggotaan rombel, audit) APPROVED 2026-10-20 WIB.
- [x] Subfase 03a — Identitas dan membership migration: DITERIMA PEMILIK (checklist #1–#11 APPROVED 2026-10-21 WIB mengacu baseline 37f7d79; laporan PHASE-03A-STUDENT-IDENTITY.md).
- [x] Subfase 03b — CRUD dan form siswa: **DITERIMA PEMILIK** (checklist #1–#13 APPROVED 2026-10-21 WIB mengacu baseline `cbd25bd`; backend `e77f335` + UI `cbd25bd`; 55 test Student/228 assertions, 209 test penuh/946 assertions, 15 unit frontend, build PASS, browser UAT agen PASS; laporan PHASE-03B-STUDENT-CRUD.md).
- [x] Subfase 03c — Daftar, rekap & ekspor siswa (server-side): **DITERIMA PEMILIK** (checklist #1–#23 APPROVED 2026-10-26 WIB mengacu baseline e15f028 & lingkungan 933e71c; 229 test backend PASS, 18 unit frontend PASS, build PASS, Pint PASS, UAT browser & smoke PASS; laporan PHASE-03C-STUDENT-DIRECTORY.md).
- [x] **Fase 03 — Pendataan siswa lengkap dan aman**: DITUTUP LENGKAP sesuai cakupan roadmap yang diotorisasi (03a, 03b, 03c seluruhnya DITERIMA PEMILIK).
- [x] Subfase 03d — Aksi penempatan & perpindahan rombel via UI: **DITERIMA PEMILIK** (DEC-23; checklist #1–#15 di `PHASE-03D-MANUAL-REVIEW.md` APPROVED 2026-10-26 WIB mengacu baseline 7bed397 dan f6be20f; 237 test backend PASS, 7 test MariaDB concurrency PASS, 18 test frontend PASS, build PASS, Pint PASS, UAT smoke PASS; laporan `PHASE-03D-STUDENT-PLACEMENT.md`).
- [x] Subfase 04a — Schema profil dan hubungan akun: **DITERIMA PEMILIK** (DEC-04; checklist #1–#13 di `PHASE-04A-MANUAL-REVIEW.md` APPROVED 2026-10-29 WIB berdasarkan pernyataan pemilik; 21 test SQLite PASS / 111 assertions, 6 test MariaDB PASS / 31 assertions; `MariaDbRombelConcurrencyTest` NOT RUN; laporan `PHASE-04A-EMPLOYEE-SCHEMA.md`). Additive migrations now applied to new isolated SQLite UAT only; old UAT/production unchanged.
- [x] Subfase 04b — CRUD pegawai dan menu aktif: **DITERIMA PEMILIK** (DEC-24 D1–D8; checklist #1–#18 di `PHASE-04B-MANUAL-REVIEW.md` APPROVED 2026-10-30 WIB berdasarkan pernyataan pemilik; laporan `PHASE-04B-REPORT.md`). **Historical initial evidence, 2026-10-29:** 56 test Employee/331 assertions, 293 test penuh/1448 assertions, 7 unit frontend, build PASS, Pint PASS; MariaDB & browser UAT NOT RUN; migration not yet applied to UAT at that initial checkpoint. **Current correction:** new isolated SQLite UAT prepared with normal additive migrations; latest explicit SQLite 96/1738, frontend 42 tests/build 18.77 s PASS, scoped operator browser CRUD/link/unlink/CSV/mobile staff and separate three-role HTTP results recorded in addendum. Agent-side other-role browser UI, two-tab stale/link-conflict, print-preview/screenshot visual checks and 04b MariaDB NOT RUN. **Owner #1–#18 APPROVED 2026-10-30 WIB per owner statement.**
- [ ] Lingkungan kompatibilitas keamanan (sesi 2026-10-22 s.d. 2026-10-26): preflight dan runtime uji terisolasi disiapkan; **bukan** subfase roadmap. Containment Apache scoped aktif (loopback 403). Server UAT 8001 dihentikan 2026-10-23 (laporan `UAT-SERVER-STOP-8001.md`). Persiapan dan pengujian kompatibilitas UAT sintetis selesai 2026-10-26 (laporan `PHP82-COMPATIBILITY-AUDIT.md`): fail-closed guard `verify-isolation.php` aktif; DB `school_compat_uat` disiapkan; 221 tes SQLite PASS (MariaDb excluded); 18 tes frontend PASS; build PASS; server UAT 18021/14021 aktif untuk review pemilik; 16 smoke PASS; manual checklist 03c #1–#23 APPROVED 2026-10-26 WIB; Fase 03 DITUTUP LENGKAP.
