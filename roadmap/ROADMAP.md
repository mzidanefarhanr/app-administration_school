# Roadmap update fitur aplikasi sekolah

Versi dokumen: 1.0, 14 Oktober 2026 (WIB); audit HEAD 15 Oktober 2026.
Baseline kode: `b02a87f0d57adffec297ed465a8afcd8e8d7ec59`; HEAD audit `b699feccd65357a10f7430c165400906e6394d49` (delta = paket docs saja, kode tidak berubah).
Status: DRAFT terstruktur. Fase 00 audit source + inventaris lingkungan selesai PARTIAL (test/build/browser NOT RUN). Seluruh implementasi fase lain belum dimulai. Tujuan utama berasal instruksi pemilik; detail target/role/kebijakan masih usulan sampai dipilih.

## Urutan dan prioritas
Fondasi keamanan + backup dahulu; penyelesaian pendataan; pengolahan massal dan tahun ajaran; surat/izin/arsip; pembelajaran; analisis; optimasi dan QA. Backup didahulukan pada Fase02 dan diuji ulang setelah seluruh modul lampiran berkembang. Data operasional tidak digunakan sebelum fondasi dan restore drill siap.

| Fase | Scope | Dependensi | Status teknis | Review |
|---|---|---|---|---|
| 00 | [Audit baseline dan kesiapan lingkungan](phases/PHASE-00-BASELINE.md) | Tidak ada | PARTIAL (source+env audit; test/build/browser NOT RUN) | PENDING |
| 01 | [Hak akses, autentikasi dan lingkungan aman](phases/PHASE-01-ACCESS.md) | 00; DEC-03 untuk 01a | **IMPLEMENTED & VERIFIED** (01a, 01b, 01c selesai; 82 test backend PASS; 15 unit test frontend PASS; UAT browser PASS) | **DITERIMA LENGKAP & DITUTUP** (01a APPROVED 2026-10-15; 01b APPROVED 2026-10-16/17; 01c APPROVED 2026-10-17 WIB) |
| 02 | [Audit terpercaya dan backup yang dapat dipulihkan](phases/PHASE-02-BACKUP-AUDIT.md) | 01; DEC-02 dan DEC-09 untuk backup | NOT STARTED | PENDING |
| 03 | [Pendataan siswa lengkap dan aman](phases/PHASE-03-STUDENTS.md) | 01; 02 sebelum data operasional; DEC-05 | NOT STARTED | PENDING |
| 04 | [Guru dan pegawai](phases/PHASE-04-EMPLOYEES.md) | 01; 02; DEC-04 | NOT STARTED | PENDING |
| 05 | [Impor, ekspor dan kualitas data](phases/PHASE-05-IMPORT-EXPORT.md) | 03,04; DEC-08 | NOT STARTED | PENDING |
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
- [/] Subfase 02c — Jadwal dan monitoring backup (IMPLEMENTED & FULLY VERIFIED; 152 test backend PASS; laporan PHASE-02C-MONITORING-SCHEDULE.md; review manual pemilik PENDING di PHASE-02C-MANUAL-REVIEW.md).
