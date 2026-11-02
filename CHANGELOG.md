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

## [2026-11-02] Fase 08b — Pemilik menyetujui rencana (DEC-36 APPROVED; implementasi diotorisasi)
- Type: Docs
- Scope / fase: Persetujuan rencana 08b D1–D8; implementasi 08b diotorisasi. Tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `docs/DECISIONS.md`, `docs/REQUIREMENTS.md`, `reports/feature-update/PHASE-08B-PLAN.md`, `reports/feature-update/PHASE-08-PLAN.md`, `reports/feature-update/INDEX.md`, `HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-08-LEAVES.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`.
- What changed: mencatat pernyataan pemilik **"Saya setuju D1-D8"** (2026-11-02 WIB) sebagai **DEC-36 APPROVED**; status rencana 08b menjadi PLAN APPROVED dan implementasi dikerjakan; 08a tetap OWNER APPROVED #1–#16; DEC-35 tidak berubah.
- Why: persetujuan pemilik adalah gate implementasi subfase; keputusan dicatat terpisah dari paket implementasi.
- Validation: **NOT RUN** — tidak ada tes/build/Pint/ESLint/browser/UAT pada entri persetujuan; server UAT tidak dinyalakan.
- Notes / risks: DEC-13 tetap OPEN; merge/deploy/migrasi DB operasional/MariaDB, dependency/service/`.env`, dan fase lain tidak diotorisasi; implementasi 08b belum diverifikasi pada entri ini.
- Evidence: `reports/feature-update/PHASE-08B-PLAN.md`; paket rencana `dc86ce5`; baseline `618b8f8`.

## [2026-11-02] Fase 08b — Rencana keputusan dan rekap izin disusun (proposed DEC-36)
- Type: Docs
- Scope / fase: Perencanaan 08b (approve/reject, pembatalan izin approved, rekap). Tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `reports/feature-update/PHASE-08B-PLAN.md`, `reports/feature-update/INDEX.md`, `HANDOFF.md`, `PHASE-08-PLAN.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-08-LEAVES.md`, `PROJECT_CONTEXT.md`, `docs/DECISIONS.md`, `docs/REQUIREMENTS.md`, `CHANGELOG.md`.
- What changed: rencana 08b dengan D1–D8 berstatus **PLAN — AWAITING OWNER APPROVAL** dan usulan **DEC-36 PROPOSED**: approve/reject hanya `leaves.approve` tanpa self-decision dan hanya `submitted` (reject wajib alasan 10–500, approve opsional), pembatalan approved via `/cancel` oleh approver beralasan 10–255 tanpa menghapus riwayat, `GET /api/leaves/recap` (≤366 hari, irisan tanggal izin, agregat tanpa PII), transaksi + `lockForUpdate` + `expected_version`/`409`, tab Recap, total 16 route, tanpa tabel/kolom/ability/dependency baru; UAT `.uat-phase08` `prepare-decisions` direncanakan saat implementasi.
- Why: menyiapkan subfase berikutnya setelah 08a diterima, tanpa menebak keputusan bisnis; persetujuan pemilik diperlukan sebelum implementasi.
- Validation: **NOT RUN** — tidak ada tes/build/Pint/ESLint/route/browser/UAT pada paket rencana; server UAT tidak dinyalakan.
- Notes / risks: 08b belum diotorisasi sampai D1–D8 disetujui; DEC-35 tetap; DEC-13 OPEN; tanpa perubahan dependency/service/`.env`/DB.
- Evidence: `reports/feature-update/PHASE-08B-PLAN.md`; baseline `618b8f8`; SHA paket dilaporkan setelah push (tanpa klaim SHA masa depan).

## [2026-11-02] Fase 08a — Penerimaan pemilik #1–#16 dan penghentian UAT
- Type: Docs
- Scope / fase: Penerimaan checklist manual 08a; penghentian server UAT. Tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `docs/DECISIONS.md`, `docs/REQUIREMENTS.md`, `docs/TESTING.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-08-LEAVES.md`, `reports/feature-update/INDEX.md`, `HANDOFF.md`, `PHASE-08-PLAN.md`, `PHASE-08A-REPORT.md`, `PHASE-08A-MANUAL-REVIEW.md`.
- What changed: mencatat pernyataan pemilik **"Pengujian nomor 1-16 sudah berhasil dan saya approved."** (2026-11-02 WIB) sebagai penerimaan checklist 08a #1–#16; paket review **`91b3eeb`**; status 08a menjadi OWNER APPROVED. Server UAT 14026/18026 dihentikan sesuai DEC-27 setelah verifikasi PID/command line (API 11864, SPA 19376), port dipastikan bebas, `.uat-phase08` dipertahankan. 08b tetap belum dimulai.
- Why: penerimaan pemilik adalah gate review manual; server UAT fase yang sudah lulus dihentikan agar tidak terus berjalan (DEC-27).
- Validation: pernyataan pemilik; verifikasi proses/port sebelum dan sesudah penghentian; **NOT RUN:** agen tidak mengulang skenario #1–#16, tes, build, atau browser; item NOT RUN tetap NOT RUN.
- Notes / risks: penerimaan 08a tidak mengotorisasi 08b, merge/deploy, migrasi DB operasional/MariaDB, dependency/service/`.env`, atau retensi DEC-13. DEC-13 tetap OPEN.
- Evidence: `reports/feature-update/PHASE-08A-MANUAL-REVIEW.md`, `PHASE-08A-REPORT.md`; paket review `91b3eeb`; baseline `7a45813`.

## [2026-11-02] Fase 08a — Pengajuan izin siswa dan pegawai (DEC-35)
- Type: Feature | Schema Change
- Scope / fase: 08a — pengajuan izin siswa/pegawai, validasi kelayakan/tanggal/overlap, lampiran privat, pembatalan, audit, UI Leaves, dan UAT terisolasi. 08b (keputusan/rekap) tidak termasuk.
- Files touched: migration `2029_08_04_000001`–`000004`; model `LeaveRequest`/`LeaveDecision`/`LeaveAttachment`; `config/leaves.php`; `LeaveRequestService`/`LeaveAttachmentService`/`PrivateUploadValidator`; `LeaveRequestPolicy`; `LeaveController` + lima request; `AbilityCatalog`; `routes/api.php`; `LeaveRequestTest.php`; `scripts/phase08-uat.php`; `Leaves.vue`, router, menu, Roles; dokumen/laporan 08a.
- What changed: tabel additive `leave_requests`/`leave_decisions`/`leave_attachments`; ability `leaves.request` + peran awal `guru`; subjek siswa (hanya `leaves.manage`) atau pegawai (self via `leaves.request` + tautan `user_id` tepat); alur `draft → submitted → cancelled` dengan `expected_version`/`409` dan riwayat keputusan append-only; validasi tahun ajaran/keanggotaan siswa, batas 30/90 hari, dan overlap submitted/approved; lampiran privat 3×5 MB (PDF/JPG/JPEG/PNG) dengan validator bersama; 13 route `/api/leaves*`; halaman `/public/leaves` (Requests/My Leaves); audit tanpa alasan/nama file/path/hash; UAT `.uat-phase08` 18026/14026 dengan 120 fixture sintetis + 2 izin hasil browser.
- Why: melaksanakan D1–D12/DEC-35 untuk FR-10 bagian pengajuan; guru memperoleh self-service tanpa memberi `leaves.manage`, dan siswa dapat diajukan staf berwenang tanpa akun login siswa.
- Validation: Leaves **22 passed / 209 assertions (19,44 s)**; regresi Letters **86 passed / 1.744 assertions (88,68 s)**; Pint **23 file PASS**; `route:list --path=leaves` **13 route**; `npm run build` **PASS 25,44 s**; ESLint **0 error** (595 warning Prettier); UAT status guard PASS — 122 izin (34 draft/73 submitted/15 cancelled), 49 lampiran/49 file, 15 keputusan, 123 siswa/123 keanggotaan aktif, 20 pegawai/1 tertaut, 6 akun; browser/API operator end-to-end, guru self-service, waka/kepsek view-only, viewport sempit, 0 console error. **NOT RUN:** suite penuh (batas akses `school_access_uat`), race MariaDB/InnoDB, dua tab nyata, restore drill, visual regression, benchmark, alur 08b.
- Notes / risks: `approved`/`rejected` belum dapat dicapai dan `leaves.approve` belum ditegakkan (08b); overlap ditegakkan aplikasi, konkurensi MariaDB belum diuji; lampiran disimpan setelah soft delete sampai DEC-13; review pemilik **PENDING (#1–#16)** saat implementasi; **kemudian APPROVED 2026-11-02 WIB (#1–#16)** (entri penerimaan di atas), server UAT dihentikan. Tidak ada perubahan dependency/lockfile, service, `.env`, database operasional/MariaDB, merge, deploy, atau force-push.
- Evidence: `reports/feature-update/PHASE-08A-REPORT.md`, `reports/feature-update/PHASE-08A-MANUAL-REVIEW.md`; baseline `7a45813`; SHA paket review dicatat pada checkpoint berikutnya.

## [2026-11-02] Fase 08 — Pemilik menyetujui rencana (DEC-35 APPROVED; DEC-07 CLOSED)
- Type: Docs
- Scope / fase: Persetujuan rencana Fase 08; implementasi 08a diotorisasi. Tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `reports/feature-update/PHASE-08-PLAN.md`, `docs/DECISIONS.md`, `docs/REQUIREMENTS.md`, `reports/feature-update/INDEX.md`, `HANDOFF.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-08-LEAVES.md`.
- What changed: mencatat pernyataan pemilik **"Saya setuju D1-D12"** (2026-11-02 WIB) sebagai **DEC-35 APPROVED**; **DEC-07 CLOSED**; **DEC-13 tetap OPEN**. Rencana `PHASE-08-PLAN.md` berubah dari AWAITING OWNER APPROVAL menjadi PLAN APPROVED; implementasi 08a diotorisasi, 08b menunggu instruksi terpisah; checklist paket rencana ditandai selesai.
- Why: persetujuan D1–D12 adalah gate DEC-07 dan syarat implementasi izin; persetujuan dicatat terpisah dari paket rencana `c530429`.
- Validation: pernyataan pemilik; pemeriksaan dokumen; **NOT RUN:** implementasi 08a, migration, tes, build/lint, browser, dan UAT 08.
- Notes / risks: DEC-13 tetap OPEN; merge/deploy/force-push, migrasi DB operasional/MariaDB, dan perubahan dependency/service/`.env` tidak diotorisasi. Persetujuan rencana bukan penerimaan hasil implementasi.
- Evidence: `reports/feature-update/PHASE-08-PLAN.md`, `docs/DECISIONS.md` (DEC-35); paket rencana `c530429`; baseline `2db6475`.

## [2026-11-02] Fase 08 — Rencana perizinan siswa dan guru (DEC-35 PROPOSED)
- Type: Docs
- Scope / fase: Rencana Fase 08 (08a pengajuan; 08b keputusan dan rekap); tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `reports/feature-update/PHASE-08-PLAN.md` (baru), `roadmap/phases/PHASE-08-LEAVES.md`, `roadmap/ROADMAP.md`, `docs/DECISIONS.md`, `docs/REQUIREMENTS.md`, `reports/feature-update/INDEX.md`, `HANDOFF.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`.
- What changed: menambahkan rencana rinci Fase 08 (baseline audit `2db6475`) dengan status **PLAN — AWAITING OWNER APPROVAL (D1–D12)**, diajukan sebagai **DEC-35 PROPOSED**; menandai DEC-07 masih OPEN dan DEC-13 tetap OPEN; menyinkronkan baris roadmap 08 menjadi rencana menunggu persetujuan dengan implementasi NOT STARTED.
- Why: DEC-07 (jenis, approver, delegasi, perubahan/pembatalan keputusan izin) harus diputuskan pemilik sebelum implementasi; rencana perlu reviewable di GitHub sesuai kebijakan paket per fase.
- Validation: audit source aktual (tidak ada model/tabel/route/UI izin; ability `leaves.view/manage/approve` sudah ada; siswa tanpa akun; `employees.user_id` unik; pola file privat 07a/07c; UAT per fase); **NOT RUN:** implementasi izin, migration, tes, build/lint, browser, UAT 08.
- Notes / risks: rencana mencakup seluruh Fase 08 tetapi implementasi dimulai 08a saja; `leaves.request` + peran `guru` adalah usulan D9; tidak ada dependency baru; temuan `MariaDbIntegrationTest`/`school_access_uat`, CORS lampiran 07a/07b, dan DEC-13 tetap tercatat. Merge/deploy/force-push, migrasi DB operasional/MariaDB, dan perubahan service/`.env` tetap tidak diotorisasi.
- Evidence: `reports/feature-update/PHASE-08-PLAN.md`; baseline `2db6475`.

## [2026-11-02] Fase 07c — Penerimaan pemilik (#1–#18) dan penutupan UAT
- Type: Docs
- Scope / fase: Penerimaan manual Subfase 07c; tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `docs/DECISIONS.md`, `docs/TESTING.md`, `docs/REQUIREMENTS.md`, `reports/feature-update/PHASE-07C-REPORT.md`, `PHASE-07C-MANUAL-REVIEW.md`, `PHASE-07C-PLAN.md`, `PHASE-07-PLAN.md`, `INDEX.md`, `HANDOFF.md`, `CHANGELOG.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-07-LETTERS.md`, `PROJECT_CONTEXT.md`.
- What changed: mencatat pernyataan pemilik **"Pengujian nomor 1-18 sudah berhasil dan saya approved."** sebagai **OWNER APPROVED (#1–#18, 2026-11-02 WIB)** untuk checklist 07c; status review PENDING diganti APPROVED. Setelah penerimaan, server UAT 14025/18025 dihentikan sesuai DEC-27: API PID 8340 dan SPA PID 22456/8808 diverifikasi command line sebelum `taskkill /T /F`, port terverifikasi bebas, data `.uat-phase07` dipertahankan; Apache/MariaDB tidak disentuh.
- Why: catatan penerimaan manual pemilik harus terpisah dari commit implementasi dan paket review; DEC-27 mewajibkan server UAT fase yang telah diterima dihentikan.
- Validation: pernyataan pemilik; verifikasi PID/command line dan port; **agen tidak mengulang skenario**; item NOT RUN tetap NOT RUN. Tidak ada tes/build baru pada entri dokumentasi ini.
- Notes / risks: **DEC-13 tetap OPEN**; merge/deploy/force-push, migrasi DB operasional/MariaDB, perubahan dependency/service/`.env`, dan Fase 08 tidak diotorisasi; temuan `MariaDbIntegrationTest`/`school_access_uat` dan CORS lampiran 07a/07b tetap tercatat.
- Evidence: `reports/feature-update/PHASE-07C-REPORT.md`, `PHASE-07C-MANUAL-REVIEW.md`; implementasi `2a78b7e`, perbaikan tes `ac3b811`, paket review `a078050`; commit dokumentasi penerimaan menyusul setelah `a078050`.

## [2026-11-02] Fase 07c — Kop, tanda tangan, stempel, template PDF, dan snapshot surat (implementasi)
- Type: Feature
- Scope / fase: Subfase 07c sesuai DEC-34 (D1–D11); 07a/07b tetap berjalan; Fase 08 tidak dimulai.
- Files touched: tiga migration additive (`letter_settings`, `letter_documents`, kolom typed `letters`); model `LetterSettings`/`LetterDocument` + perluasan `Letter`; service settings/aset/template/renderer/dokumen/subjek + integrasi `OutgoingLetterService::issue()`; controller settings/subjek, request header/signatory + typed, sembilan route baru; enam template Blade; `config/letters.php`; `composer.json`/`composer.lock` (hanya dompdf + transitive); tes settings/dokumen + fixture; `scripts/phase07-uat.php`; `LetterSettings.vue`, `OutgoingLetters.vue`, router, menu; dokumentasi.
- What changed: `letter_settings` 1:1 dengan profil sekolah aktif (kop/logo/penandatangan/signature/stamp privat, readiness, versi) dan `letter_documents` immutable; tiga jenis surat (`student_active`, `student_mutation`, `employee_duty`) dengan tipe/subjek immutable; template Blade berversi dan `dompdf/dompdf v2.0.8`; PDF + snapshot minimal PII dibuat di dalam transaksi `approved → issued` setelah nomor (gagal → rollback, nomor tidak dikonsumsi); preview watermarked draft/review/approved; download issued/cancelled; surat umum/legacy `404`; 9 endpoint (total 37 route surat); halaman Letter Settings dan typed Outgoing.
- Why: menutup DEC-11 untuk lingkup 07c dan menyediakan dokumen resmi bertanda tangan/stempel yang tidak berubah setelah terbit, sesuai D1–D11.
- Validation: `LetterSettingsTest` **10/167**, `LetterDocumentTest` **21/744**, gabungan **31/911 (59,98 s)**, regresi Letters **86/1.744 (109,17 s)**; suite penuh **552 passed / 1 failed / 13 skipped / 6.235 assertions (541,94 s)** dengan kegagalan tunggal filter route 06c yang menghitung route 07c — diperbaiki dan lulus ulang **1/23 (4,32 s)**, suite penuh tidak diulang karena tes drill MariaDB melanggar batas akses; Pint **28**; **37 route**; build **32,44 s**; ESLint **0 error** (623 warning); UAT `.uat-phase07` `prepare-documents` (20 surat bertipe, 7 dokumen/12 halaman/7 PDF, nomor 007–013, settings v2; 07a/07b utuh); browser settings/preview/PDF 2 halaman/390 px/0 console error.
- Notes / risks: review pemilik saat entri ini **PENDING (#1–#18)**, kemudian **APPROVED 2026-11-02 WIB** (lihat entri terbaru); DEC-13 tetap OPEN; render sinkron tanpa queue, retensi file, dan backfill legacy belum ada; unduhan lampiran 07a/07b masih kena blokir CORS `Content-Disposition` (di luar scope); `MariaDbIntegrationTest` baseline membaca `school_access_uat` saat MariaDB root terjangkau — suite penuh tidak diulang dan tes perlu digating; item NOT RUN dicatat di laporan. Tidak ada merge/deploy, migrasi DB operasional/MariaDB, atau perubahan service/`.env`.
- Evidence: `reports/feature-update/PHASE-07C-REPORT.md`, `PHASE-07C-MANUAL-REVIEW.md`; paket implementasi `2a78b7e` di atas baseline `4713c70`; paket dokumentasi dicatat setelah commit.

## [2026-11-02] Fase 07c — Pemilik menyetujui rencana (DEC-34 APPROVED; DEC-11 CLOSED untuk 07c)
- Type: Docs
- Scope / fase: Persetujuan rencana Subfase 07c; implementasi 07c diotorisasi. Tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `docs/DECISIONS.md`, `docs/REQUIREMENTS.md`, `reports/feature-update/PHASE-07C-PLAN.md`, `roadmap/phases/PHASE-07-LETTERS.md`, `roadmap/ROADMAP.md`, `PROJECT_CONTEXT.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `CHANGELOG.md`.
- What changed: mencatat pernyataan pemilik **"Saya setuju D1-D11"** (2026-11-02 WIB) sebagai **DEC-34 APPROVED** untuk rencana `PHASE-07C-PLAN.md` (paket `42108dc`; baseline `ff581c6`); status rencana/roadmap berubah dari PLAN — AWAITING OWNER APPROVAL menjadi PLAN APPROVED; **DEC-11 CLOSED untuk lingkup 07c** (rekap tetap DEC-12); implementasi 07c diotorisasi dan dimulai.
- Why: persetujuan pemilik atas D1–D11 diperlukan sebelum implementasi 07c dan penambahan dependency `dompdf/dompdf:^2.0` (D2); DEC-11 sebelumnya menjadi gate 07c.
- Validation: pernyataan pemilik; pemeriksaan dokumen; **NOT RUN:** implementasi 07c, composer update/install, migration, tes, build/lint, browser, dan UAT 07c.
- Notes / risks: DEC-13 (retensi) tetap OPEN; merge/deploy, migrasi DB operasional/MariaDB, perubahan service/`.env`, dependency selain D2, dan Fase 08 tidak diotorisasi. Persetujuan rencana bukan penerimaan hasil implementasi.
- Evidence: `reports/feature-update/PHASE-07C-PLAN.md`, `docs/DECISIONS.md` (DEC-34); rencana `42108dc`; baseline `ff581c6`.

## [2026-11-02] Fase 07c — Rencana kop, tanda tangan, stempel, template PDF, dan snapshot
- Type: Docs
- Scope / fase: **Rencana 07c saja**; tidak ada perubahan kode, migration, dependency, tes, atau UAT.
- Files touched: `reports/feature-update/PHASE-07C-PLAN.md` (baru), `docs/DECISIONS.md` (DEC-34 PROPOSED), `docs/REQUIREMENTS.md` (catatan FR-09), `roadmap/phases/PHASE-07-LETTERS.md`, `roadmap/ROADMAP.md`, `PROJECT_CONTEXT.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `CHANGELOG.md`.
- What changed: menyusun rencana rinci 07c dengan audit source aktual dan usulan D1–D11: tiga jenis surat (`student_active`, `student_mutation`, `employee_duty`; surat izin ditunda Fase 08), `letter_settings` 1:1 per `school_profiles` (kop/logo/penandatangan/signature/stamp privat), template Blade kode berversi, dependency `dompdf/dompdf:^2.0` hanya bila D2 disetujui, PDF + snapshot minimal PII saat `approved → issued` di dalam transaksi, preview watermarked, download issued/cancelled, 9 route baru (37 total), halaman Letter Settings + typed Outgoing, matriks T1–T28, dan UAT additive `prepare-documents`.
- Why: menyiapkan keputusan pemilik untuk subfase berikutnya tanpa menebak format kop/penandatangan, isi surat, atau dependency PDF; DEC-11 masih OPEN dan 07c belum diotorisasi.
- Validation: pemeriksaan dokumen/source untuk rencana; `git diff --check`; **NOT RUN:** implementasi, migration, dependency/composer install, tes, build/lint, browser, dan UAT 07c.
- Notes / risks: rencana diajukan sebagai **DEC-34 PROPOSED**; **DEC-11/DEC-13 tetap OPEN**; merge/deploy, migrasi DB operasional/MariaDB, dependency/service/`.env`, dan Fase 08 tidak diotorisasi. Jika D1–D11 disetujui, keputusan dicatat pada commit dokumentasi terpisah sebelum implementasi.
- Evidence: `reports/feature-update/PHASE-07C-PLAN.md`; baseline `ff581c6`; SHA paket rencana dicatat setelah commit.

## [2026-11-02] Fase 07b — Pemilik menyetujui hasil implementasi (checklist #1–#16)
- Type: Docs
- Scope / fase: Penerimaan manual Subfase 07b; tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `reports/feature-update/PHASE-07B-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-07B-REPORT.md`, `docs/DECISIONS.md`, `docs/TESTING.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `PROJECT_CONTEXT.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-07-LETTERS.md`, `CHANGELOG.md`.
- What changed: mencatat pernyataan pemilik **"Pengujian nomor 1-16 sudah berhasil dan saya approved."** (2026-11-02 WIB) sebagai penerimaan checklist 07b **#1–#16 APPROVED**; status review pada laporan/checklist/DEC-33/roadmap berubah dari PENDING menjadi APPROVED. Server UAT 14025/18025 dihentikan setelah penerimaan (DEC-27).
- Why: penerimaan pemilik adalah bukti acceptance yang terpisah dari bukti otomatis dan paket review `7f89264`; DEC-27 mengharuskan server fase yang sudah lulus dihentikan agar tidak berjalan tanpa dipakai.
- Validation: pernyataan pemilik; command line API PID 3132 (`php.exe -S 127.0.0.1:18025 -t public scripts/phase07-uat.php`) dan SPA PID 21876 (Vite `--host 127.0.0.1 --port 14025 --strictPort`) diverifikasi sebelum `taskkill /T /F`; `netstat` menunjukkan port 14025/18025 bebas; Apache (PID 1304, port 80/443) dan MariaDB (PID 18244, port 3306) tetap berjalan; data `.uat-phase07` dipertahankan. **NOT RUN:** skenario manual tidak diulang agen; item NOT RUN 07b tetap NOT RUN.
- Notes / risks: penerimaan tidak mengotorisasi merge/deploy, migrasi database operasional/MariaDB, perubahan dependency/service/`.env`, atau implementasi 07c. DEC-11 (07c) dan DEC-13 (retensi) tetap OPEN; 07c memerlukan rencana dan keputusan dependency PDF terpisah.
- Evidence: `reports/feature-update/PHASE-07B-REPORT.md`, `reports/feature-update/PHASE-07B-MANUAL-REVIEW.md`; paket implementasi `d736b37`, paket review `7f89264`.

## [2026-11-02] Fase 07b — Disposisi surat masuk, surat keluar, dan nomor resmi
- Type: Feature
- Scope / fase: **07b saja** di atas `main` @ `d326369` (DEC-33 APPROVED, "Saya setuju D1-D11"). Implementasi/UAT 2026-11-01 WIB; paket dokumentasi 2026-11-02. 07c (template/PDF/kop/tanda tangan/stempel), merge/deploy, migrasi database operasional/MariaDB, dan perubahan dependency/service/`.env` tidak termasuk.
- Files touched: migration additive `2029_08_02_000004_add_outgoing_fields_to_letters_table`..`000007_create_letter_number_counters_table`; model `Letter`/`LetterEvent` (diperluas), `LetterDisposition`, `LetterApproval`, `LetterNumberCounter`; service `OutgoingLetterService`/`LetterNumberService`/`LetterDispositionService`, `LetterService` (diperluas); 10 FormRequest; `OutgoingLetterController`/`LetterDispositionController`; `routes/api.php` (19 route baru); `config/letters.php`; launcher `scripts/phase07-uat.php`; tes `OutgoingLetterTest`/`DispositionTest`, `IncomingLetterTest` (diperluas); UI `OutgoingLetters.vue`/`LetterDispositions.vue` (baru), `IncomingLetters.vue`/router/`AppMenu.vue` (diperluas); dokumentasi fase.
- What changed: kolom outgoing nullable pada `letters` (`recipient`, `official_number` unik, `workflow_status`, `approved_at/by`, `issued_at/by`) tanpa mengubah kolom lama; tabel `letter_dispositions` (penerima pegawai aktif, tenggat, status, tindak lanjut, versi, pembatalan), `letter_approvals` immutable, `letter_number_counters` unik per `(scope, period)`; alur `draft → review → approved → issued` dengan `reject` kembali ke draft dan `cancel` (`letters.manage` untuk draft/review, `letters.approve` untuk approved/issued); nomor resmi atomik `{seq:03d}/{school_code}/{year}` (counter global tahunan, reset 1 Januari Asia/Jakarta, nomor saat issue, tidak dipakai ulang); approval/reject `letters.approve`, issue/sign `letters.sign`, pembuat tidak dapat approve/reject/issue suratnya sendiri (`403`), admin/operator tidak otomatis mendapat mandat; edit/lampiran hanya saat draft; disposisi hanya surat masuk, disposisi terbuka memblokir void (`409`); 6 endpoint disposisi + 13 endpoint outgoing/opsi; halaman Outgoing Letters, Dispositions, dan panel disposisi di Incoming Letters; event/approval immutable, audit allowlist tanpa teks sensitif.
- Why: menyelesaikan Fase 07b sesuai DEC-33/D4–D5 tanpa menebak format nomor, mandat approval, atau semantik disposisi; DEC-06 tertutup dan DEC-11/DEC-13 tetap menjadi gate 07c.
- Validation: `php artisan test tests/Feature/Letters/OutgoingLetterTest.php tests/Feature/Letters/DispositionTest.php` **35 passed / 531 assertions (18,42 s)** (Outgoing 21/277; Disposition 14/254); suite penuh **522 passed / 13 skipped / 5.320 assertions (464,78 s)**; Pint **31 files PASS**; `route:list --path=letters` **28 route** (9 incoming + 6 disposisi + 13 outgoing); `npm run build` **PASS 41,94 s**; `npx eslint --quiet` **0 error**. UAT `.uat-phase07` (SQLite, 14025/18025, cookie `school_phase07_session`): 46 surat keluar (16 draft/8 review/10 approved/6 issued/6 cancelled, 6 nomor resmi, counter 2026=6), 32 disposisi (11 pending/7 in_progress/7 completed/7 cancelled, 2 overdue), 14 pegawai (12 aktif), data 07a tetap utuh; browser/API operator/kepsek/waka PASS termasuk `409` + Reload Latest, nomor `006/20512345/2026`, self-approval tidak dapat diuji manual karena akun terpisah, void `409 open_dispositions`, 390 px, 0 console error. **NOT RUN:** konkurensi counter MariaDB/InnoDB/race multi-proses nyata, dua tab browser nyata, PDF/print (07c), restore drill tabel/nomor 07b, visual regression berbasis gambar, notifikasi email/WhatsApp, benchmark volume besar. Fixture tidak memuat surat void dengan riwayat disposisi completed (tercakup tes otomatis T23).
- Notes / risks: server UAT 14025/18025 berjalan sampai penerimaan pemilik (DEC-27); data `.uat-phase07` dipertahankan. Review manual **PENDING** (`PHASE-07B-MANUAL-REVIEW.md` #1–#16); commit/push paket review bukan penerimaan manual. DEC-11 (07c) dan DEC-13 (retensi) tetap OPEN; 07c memerlukan rencana dan keputusan dependency PDF terpisah.
- Evidence: `reports/feature-update/PHASE-07B-REPORT.md`, `reports/feature-update/PHASE-07B-MANUAL-REVIEW.md`; paket implementasi `d736b37`; baseline `d326369`.

## [2026-11-01] Fase 07b — Pemilik menyetujui rencana (DEC-33 APPROVED; DEC-06 CLOSED)
- Type: Docs
- Scope / fase: Rencana Subfase 07b; implementasi 07b diotorisasi. Tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `docs/DECISIONS.md`, `reports/feature-update/PHASE-07B-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `reports/feature-update/PHASE-07-PLAN.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-07-LETTERS.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`.
- What changed: mencatat pernyataan pemilik **"Saya setuju D1-D11"** (2026-11-01 WIB) sebagai **DEC-33 APPROVED (D1–D11)**; **DEC-06 ditutup** melalui D4–D5 (format nomor `{seq:03d}/{school_code}/{year}`, counter global tahunan, nomor saat issue, approver/signatory terpisah dengan larangan self-approval); status rencana 07b menjadi PLAN APPROVED dan implementasi dimulai.
- Why: gate keputusan 07b dan DEC-06 sudah dijawab pemilik; implementasi tidak perlu menebak model data, alur status, penomoran, penerima disposisi, ability, kontrak API/UI, audit, atau lingkungan UAT.
- Validation: pemeriksaan dokumen/status. **NOT RUN:** tidak ada tes aplikasi, migration, build, lint, browser, atau UAT pada entri dokumentasi ini.
- Notes / risks: persetujuan tidak mencakup 07c, merge/deploy, migrasi DB operasional/MariaDB, atau perubahan dependency/service/`.env`; DEC-11 dan DEC-13 tetap OPEN. Server UAT 07a tetap dihentikan (DEC-27); data `.uat-phase07` dipertahankan.
- Evidence: `docs/DECISIONS.md` (DEC-33, DEC-06 CLOSED); `reports/feature-update/PHASE-07B-PLAN.md`; paket rencana `c4bd338`.

## [2026-11-01] Fase 07 — Rencana disposisi, surat keluar, dan nomor disiapkan (PLAN 07b, D1–D11 PROPOSED)
- Type: Docs
- Scope / fase: Rencana Subfase **07b** saja; tidak ada perubahan kode, migration, tes, atau UAT. Implementasi 07b belum diotorisasi.
- Files touched: `reports/feature-update/PHASE-07B-PLAN.md` (baru), `reports/feature-update/PHASE-07-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-07-LETTERS.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`.
- What changed: menambahkan rencana rinci 07b — kolom additive pada `letters`, tabel `letter_dispositions`/`letter_approvals`/`letter_number_counters`, transisi `draft → review → approved → issued/cancelled`, disposisi ke pegawai dengan tenggat/status/tindak lanjut, penomoran atomik tahunan `{seq:03d}/{school_code}/{year}` saat issue, approval/sign terpisah dari admin teknis, enam endpoint disposisi + 13 endpoint outgoing, tiga halaman UI, matriks tes T1–T26, dan UAT additive `.uat-phase07`.
- Why: menyiapkan subfase kedua Fase 07 agar pemilik dapat memutuskan format nomor, mandat approval/sign, semantik disposisi, dan pembekuan edit sebelum implementasi; DEC-06 tidak ditebak.
- Validation: pemeriksaan dokumen, kode, route, migration, config, dan tautan. **NOT RUN:** tidak ada tes/build/browser/UAT pada entri dokumentasi ini; implementasi 07b belum dimulai.
- Notes / risks: implementasi 07b menunggu persetujuan **D1–D11** dan pencatatan **DEC-33**; **DEC-06 tetap OPEN** sampai D4–D5 disetujui; DEC-11 (07c) dan DEC-13 (retensi) tetap OPEN. Tidak ada merge/deploy, migrasi DB operasional/MariaDB, atau perubahan dependency/service/`.env`.
- Evidence: `reports/feature-update/PHASE-07B-PLAN.md`; SHA commit paket dokumen dilaporkan pada checkpoint berikutnya.

## [2026-11-01] Fase 07a — Pemilik menyetujui hasil implementasi (checklist #1–#16)
- Type: Docs
- Scope / fase: Penerimaan manual Subfase 07a; tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `docs/DECISIONS.md`, `docs/REQUIREMENTS.md`, `docs/TESTING.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-07-LETTERS.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `reports/feature-update/PHASE-07-PLAN.md`, laporan dan checklist manual 07a.
- What changed: mencatat pernyataan pemilik **"Pengujian nomor 1-16 sudah berhasil dan saya approved."** (2026-11-01 WIB) sebagai penerimaan checklist 07a #1–#16; server UAT 14025/18025 dihentikan setelah verifikasi PID/command line; data `.uat-phase07` dipertahankan.
- Why: penerimaan pemilik adalah bukti acceptance yang terpisah dari bukti otomatis; DEC-27 mengharuskan server fase yang sudah lulus dihentikan agar tidak berjalan tanpa dipakai.
- Validation: pernyataan pemilik; command line API PID 19564 (tree 19968) dan rantai npm/vite PID 19228 → 19196 → 20504 → 9060 diverifikasi sebelum `taskkill /T /F`; `netstat` menunjukkan port 14025/18025 bebas; Apache (PID 1304, port 80/443) dan MariaDB (PID 18244, port 3306) tetap berjalan. NOT RUN: skenario manual tidak diulang agen; item PDF-artifact automation, restore drill, MariaDB, race, dan visual regression tetap NOT RUN.
- Notes / risks: penerimaan tidak mengotorisasi merge/deploy, migrasi database operasional/MariaDB, perubahan dependency/service/`.env`, 07b, atau 07c; DEC-06/DEC-11/DEC-13 tetap OPEN.
- Evidence: `reports/feature-update/PHASE-07A-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-07A-REPORT.md`; paket implementasi `ae2b75f`, koreksi dokumentasi `b6f0af5`.

## [2026-11-01] Fase 07a — Surat masuk dan lampiran dasar
- Type: Feature
- Scope / fase: **07a saja** di atas `main` @ `f46f736` (DEC-32 APPROVED, "Saya setuju D1-D10"). 07b/07c, merge/deploy, migrasi database operasional/MariaDB, dan perubahan dependency/service/`.env` tidak termasuk.
- Files touched: migration `letters`, `letter_attachments`, `letter_events` (`2029_08_02_000001..000003`); model `Letter`/`LetterAttachment`/`LetterEvent`; `config/letters.php`; service `LetterService`/`LetterAttachmentService`; empat FormRequest (store/update/void/attachment); `IncomingLetterController`; sembilan route `/api/letters/incoming`; `IncomingLetters.vue` + router/menu Letters; `.gitignore` (`.uat-phase07/`); catatan manifest `BackupService.php`; launcher `scripts/phase07-uat.php`; tes `IncomingLetterTest`; dokumentasi fase.
- What changed: agenda surat masuk dengan referensi internal `#` dan nomor sumber opsional/tidak unik; pengirim/perihal/kategori/tanggal terima/tanggal surat dengan aturan tanggal server Asia/Jakarta; kategori dari config; lampiran privat PDF/JPG/JPEG/PNG (10 MB/file, 5/surat, nama UUID, sha256, MIME dideteksi server, path `attachments/letters/{letter_id}/`); daftar server-side search/filter/paginasi; detail dengan lampiran + maksimum 100 event; edit/void berversi dengan `expected_version` dan `409` itemized; surat void read-only; lampiran soft delete (file disimpan sampai DEC-13); `letter_events` immutable; audit allowlist tanpa pengirim/perihal/catatan/nama file/path/checksum; ability `letters.view`/`letters.manage` tanpa ability/role baru; UI English responsif 390 px.
- Why: memulai Fase 07 dengan modul surat masuk yang dapat dicari, ditelusuri, dan aman untuk lampiran privat, tanpa menebak format nomor resmi (DEC-06) atau kop/penandatangan (DEC-11) yang menjadi scope 07b/07c.
- Validation: `php artisan test tests/Feature/Letters/IncomingLetterTest.php` **20 passed/294 assertions (15,13 s)**; suite penuh **487 passed/13 skipped/4.786 assertions (374,84 s)**; Pint **18 files PASS**; `route:list --path=letters` **9 routes** tanpa delete surat; `npm run build` **PASS 26,18 s**; `npx eslint --quiet` **0 error**; UAT `.uat-phase07` (126 surat/115 aktif/11 void, 64 baris lampiran/64 file, 221 event) dengan browser/API operator, kepsek, waka; download headers diverifikasi via curl. **NOT RUN:** artefak PDF via otomasi Chrome, restore drill dengan lampiran surat, MariaDB/InnoDB, race multi-proses/dua tab, visual regression, review manual pemilik.
- Notes / risks: server UAT 14025/18025 berjalan sampai penerimaan pemilik (DEC-27); data `.uat-phase07` dipertahankan; bug library PrimeVue 4.3.1 `Select.alignOverlay` (resize/orientasi) dicatat sebagai known issue tanpa perubahan dependency; commit/push paket review bukan penerimaan manual.
- Evidence: `reports/feature-update/PHASE-07A-REPORT.md`, `reports/feature-update/PHASE-07A-MANUAL-REVIEW.md`; SHA paket dilaporkan pada checkpoint berikutnya.

## [2026-11-01] Fase 07a — Pemilik menyetujui rencana (DEC-32 APPROVED)
- Type: Docs
- Scope / fase: Rencana Subfase **07a**; implementasi 07a diotorisasi. Tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `docs/DECISIONS.md`, `reports/feature-update/PHASE-07-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-07-LETTERS.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`.
- What changed: mencatat pernyataan pemilik **"Saya setuju D1-D10"** (2026-11-01 WIB) sebagai **DEC-32 APPROVED** untuk D1–D10; rencana 07a menjadi PLAN APPROVED dan implementasi 07a dimulai.
- Why: gate keputusan rencana 07a sudah dijawab pemilik; implementasi tidak perlu menebak model data, kategori, penyimpanan lampiran, versi/void, ability, kontrak API/UI, audit, atau lingkungan UAT.
- Validation: pemeriksaan dokumen/status. **NOT RUN:** tidak ada tes aplikasi pada entri dokumentasi ini.
- Notes / risks: persetujuan tidak mencakup merge/deploy, migrasi DB operasional/MariaDB, perubahan dependency/service/`.env`, 07b, 07c, atau penutupan DEC-06/DEC-11/DEC-13.
- Evidence: `docs/DECISIONS.md` (DEC-32); `reports/feature-update/PHASE-07-PLAN.md`.

## [2026-11-01] Fase 07 — Rencana surat masuk dan lampiran dasar disiapkan (PLAN 07a, D1–D10 PROPOSED)
- Type: Docs
- Scope / fase: Rencana Subfase **07a** saja; tidak ada perubahan kode, migration, tes, atau UAT. Implementasi 07a belum diotorisasi.
- Files touched: `reports/feature-update/PHASE-07-PLAN.md` (baru), `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-07-LETTERS.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`.
- What changed: menambahkan rencana rinci 07a — tiga tabel additive (`letters`, `letter_attachments`, `letter_events`), agenda surat masuk dengan tanggal terima/surat, nomor sumber opsional dan tidak unik, kategori dari config, lampiran privat PDF/JPG/PNG di `storage/app/attachments/letters`, update/void berversi dengan `409` itemized, history immutable, sembilan endpoint `/api/letters/incoming`, halaman Incoming Letters, matriks 20 tes, fixture UAT `prepare-letters`, dan D1–D10 berstatus PROPOSED.
- Why: menyelesaikan audit dan desain subfase pertama Fase 07 agar pemilik dapat memutuskan sebelum implementasi; lampiran ditempatkan di bawah `backup.attachments_path` agar tercakup backup 02, dan DEC-06/DEC-11 tidak ditebak karena hanya mengikat 07b/07c.
- Validation: pemeriksaan dokumen, kode, route, migration, config, dan tautan. **NOT RUN:** tidak ada tes/build/browser pada entri dokumentasi ini; implementasi 07a belum dimulai.
- Notes / risks: implementasi 07a menunggu **DEC-32** atas D1–D10; DEC-06 (format nomor/approval) dan DEC-11 (kop/penandatangan) tetap OPEN untuk 07b/07c; 07c memerlukan keputusan dependency PDF karena belum ada library di `composer.json`. Tidak ada merge/deploy, migrasi DB operasional/MariaDB, atau perubahan dependency/service/`.env`.
- Evidence: `reports/feature-update/PHASE-07-PLAN.md`; SHA commit paket dokumen dilaporkan pada checkpoint berikutnya.

## [2026-11-01] Fase 06c — Pemilik menyetujui hasil implementasi (checklist #1–#16)
- Type: Docs
- Scope / fase: Penerimaan manual Subfase 06c; tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `docs/DECISIONS.md`, `docs/REQUIREMENTS.md`, `docs/TESTING.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `reports/feature-update/PHASE-06C-PLAN.md`, laporan dan checklist manual 06c.
- What changed: mencatat pernyataan pemilik **"Pengujian nomor 1-16 sudah berhasil dan saya approved."** (2026-11-01 WIB) sebagai penerimaan checklist #1–#16; server UAT 14024/18024 dihentikan setelah verifikasi PID/command line; data `.uat-phase06` dipertahankan.
- Why: penerimaan pemilik adalah bukti acceptance yang terpisah dari bukti otomatis; DEC-27 mengharuskan server fase yang sudah lulus dihentikan agar tidak berjalan tanpa dipakai.
- Validation: pernyataan pemilik; command line PID 19280 (`node ... vite --host 127.0.0.1 --port 14024 --strictPort`) dan PID 9436 (`php.exe -S 127.0.0.1:18024 -t public scripts/phase06-uat.php`) diverifikasi sebelum penghentian; `netstat` menunjukkan port 14024/18024 bebas; Apache (PID 1304, port 80/443) dan MariaDB (PID 18244, port 3306) tetap berjalan. NOT RUN: skenario manual tidak diulang agen; item MariaDB/race/benchmark/visual regression tetap NOT RUN.
- Notes / risks: penerimaan tidak mengotorisasi merge/deploy, migrasi database operasional/MariaDB, rekap semester/sekolah, atau fase berikutnya.
- Evidence: `reports/feature-update/PHASE-06C-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-06C-REPORT.md`; commit dokumentasi penerimaan `41ec7b5` (di-push ke `origin/main`).

## [2026-11-01] Fase 06c — Mutasi, pembatalan, rekap, dan koreksi periode locked
- Type: Feature
- Scope / fase: **06c saja** di atas `main` @ `da94195` (DEC-31 APPROVED). 06d, merge/deploy, migrasi database operasional/MariaDB, rekap semester/sekolah, dan perubahan dependency/service/`.env` tidak termasuk.
- Files touched: migration `student_mutations`, `student_locked_corrections`, ability `students.correct-locked`; model `StudentMutation`/`StudentLockedCorrection`; service `StudentMutationService`/`StudentLockedCorrectionService`; empat request + dua controller; sepuluh route; guard hapus tahun; `membership_id`/`membership_version` di direktori siswa; allowlist audit `StudentRombel`; `AbilityCatalog`; fixture UAT `prepare-mutations`; tes `StudentMutationTest`; `StudentMutations.vue` + `StudentMutationTimelineDialog.vue`; dokumentasi fase.
- What changed: mutation-in membuat membership baru (`Aktif`, entry `Mutasi Masuk`, NPSN asal) dan mutation-out menutup satu membership (`Mutasi Keluar`, exit/tujuan, versi+1) dengan log immutable `student_mutations`; pembatalan kompensatif (out membuka kembali, in mengarsipkan) memakai `expected_*_version` + `409` itemized tanpa mutasi; rekap per tahun (`mutation_in`/`mutation_out`/`graduated`/`retained`, cancelled dikecualikan); ability sensitif `students.correct-locked` + tabel immutable `student_locked_corrections` untuk lima tindakan pada tahun locked; sepuluh endpoint; UI Students → Mutations dengan rekap, log, pencarian, dialog In/Out/Cancel/Correct, dan timeline; guard hapus tahun; audit NPSN tanpa alasan/PII.
- Why: menyelesaikan subfase terakhir Fase 06 — mutasi siswa dapat ditelusuri, status/tanggal konsisten, biodata tidak dihapus untuk mencatat keluar, dan koreksi periode locked ter-audit sesuai DEC-31.
- Validation: `php artisan test tests/Feature/Academic/StudentMutationTest.php` **19 passed/369 assertions (10,41 s)**; `StudentDirectoryTest` **39/501**; suite penuh **467 passed/13 skipped/4.492 assertions (279,96 s)**; Pint **21 files PASS**; `route:list` **8 mutation + 2 locked-correction**; `npm run build` **PASS 30,56 s**; `npx eslint --quiet` **0 error**; UAT browser/API `.uat-phase06` (operator in/out/cancel/re-entry, kepsek view-only `403`, admin koreksi locked 0072 + approval, rekap In 41/Out 20/Graduated 1/Retained 1, 390 px, 0 console error). **NOT RUN:** MariaDB/InnoDB + trigger MySQL, race multi-proses/dua tab, benchmark volume besar, visual regression, review manual pemilik.
- Notes / risks: server UAT 14024/18024 berjalan sampai penerimaan pemilik (DEC-27); data 06a/06b tidak di-reset; demo 0021/0081/0111/0072 dipertahankan. Review manual saat implementasi **PENDING (#1–#16)**; **kemudian APPROVED 2026-11-01 WIB (#1–#16)** (entri penerimaan di atas). Merge/deploy dan migrasi operasional tidak diotorisasi.
- Evidence: `reports/feature-update/PHASE-06C-REPORT.md`, `reports/feature-update/PHASE-06C-MANUAL-REVIEW.md`; paket implementasi+dokumentasi `9927d8e` (di-push ke `origin/main`).

## [2026-11-01] Fase 06c — Pemilik menyetujui rencana (DEC-31 APPROVED)
- Type: Docs
- Scope / fase: Rencana Subfase **06c**; implementasi 06c diotorisasi. Tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `docs/DECISIONS.md`, `reports/feature-update/PHASE-06C-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`.
- What changed: mencatat pernyataan pemilik **"Saya setuju D1-D10."** (2026-11-01 WIB) sebagai **DEC-31 APPROVED** untuk D1–D10; rencana 06c menjadi PLAN APPROVED dan implementasi 06c dimulai.
- Why: gate keputusan rencana 06c sudah dijawab; implementasi dapat berjalan tanpa menebak keputusan bisnis.
- Validation: pemeriksaan dokumen/status. **NOT RUN:** tidak ada tes aplikasi pada entri dokumentasi ini.
- Notes / risks: persetujuan tidak mencakup merge/deploy, migrasi DB operasional/MariaDB, perubahan dependency/service/`.env`, rekap semester, atau rekap sekolah (DEC-12 PROPOSED).
- Evidence: `docs/DECISIONS.md` (DEC-31); `reports/feature-update/PHASE-06C-PLAN.md`.

## [2026-11-01] Fase 06c — Rencana mutasi & koreksi disiapkan (PLAN PREPARED, D1–D10 PROPOSED)
- Type: Docs
- Scope / fase: Rencana Subfase **06c** saja; tidak ada perubahan kode, migration, tes, atau UAT. Implementasi 06c belum diotorisasi.
- Files touched: `reports/feature-update/PHASE-06C-PLAN.md` (baru), `reports/feature-update/PHASE-06-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`.
- What changed: menambahkan rencana rinci 06c — log immutable `student_mutations`, mutasi masuk/keluar bertanggal dengan NPSN sekolah asal/tujuan, pembatalan kompensatif, rekap per tahun (mutation_in/mutation_out/graduated/retained), koreksi periode locked melalui ability `students.correct-locked` + tabel `student_locked_corrections`, sepuluh endpoint, UI Students → Mutations, matriks 20 tes, fixture UAT `prepare-mutations`, dan D1–D10 berstatus PROPOSED.
- Why: menyelesaikan audit dan desain subfase terakhir Fase 06 agar pemilik dapat mengambil keputusan sebelum implementasi; memisahkan rencana dari otorisasi.
- Validation: pemeriksaan dokumen dan tautan. **NOT RUN:** tidak ada tes/build/browser 06c pada entri dokumentasi ini.
- Notes / risks: implementasi 06c menunggu **DEC-31**. Rekap semester ditunda karena `semesters` tanpa tanggal; transfer 03d bukan mutasi keluar. Rekap sekolah (DEC-12) tetap PROPOSED. Tidak ada merge/deploy, migrasi DB operasional/MariaDB, atau perubahan dependency/service/`.env`.
- Evidence: `reports/feature-update/PHASE-06C-PLAN.md`; SHA commit paket dokumen dilaporkan pada checkpoint berikutnya.

## [2026-11-01] Fase 06b — Penerimaan pemilik (#1–#14) dan penghentian UAT
- Type: Docs
- Scope / fase: **06b saja**; tidak ada perubahan kode aplikasi pada entri ini. Merge/deploy, migrasi database operasional/MariaDB, perubahan dependency/service/`.env`, dan 06c tidak termasuk.
- Files touched: `reports/feature-update/PHASE-06B-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-06B-REPORT.md`, `reports/feature-update/PHASE-06B-PLAN.md`, `reports/feature-update/PHASE-06-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `PROJECT_CONTEXT.md`, `docs/DECISIONS.md`, `docs/REQUIREMENTS.md`, `docs/TESTING.md`, `CHANGELOG.md`.
- What changed: mencatat pernyataan pemilik **"Pengujian nomor 1-14 sudah berhasil dan saya approved."** (2026-11-01 WIB) sebagai penerimaan checklist 06b #1–#14; menghentikan server UAT 14024/18024 setelah verifikasi PID/command line dan mempertahankan data `.uat-phase06`; memperbarui status DEC-30, roadmap, handoff, indeks, laporan, dan requirement.
- Why: penerimaan pemilik dicatat pada commit dokumentasi tersendiri, terpisah dari commit implementasi `c1dde67`; server fase yang sudah lulus dihentikan agar tidak berjalan tanpa dipakai (DEC-27).
- Validation: command line PID 18664 (SPA Vite 14024) dan 11604 (API PHP 18024) diverifikasi sebelum penghentian; `netstat` memastikan port 14024/18024 bebas; `.uat-phase06` (database/storage/password runtime) tetap ada; Apache 80/443 (PID 1304) dan MariaDB 3306 (PID 18244) tetap berjalan dan tidak disentuh. **NOT RUN:** agen tidak mengulang skenario #1–#14; item MariaDB/konkurensi/dua-tab/screenshot tetap NOT RUN.
- Notes / risks: penerimaan tidak mengotorisasi merge, deploy, migrasi data operasional/MariaDB, perubahan dependency/service, atau 06c. Baris audit lama #22/#24 memuat NIK sintetis sebelum perbaikan allowlist; audit append-only tidak diubah.
- Evidence: `reports/feature-update/PHASE-06B-MANUAL-REVIEW.md`; `reports/feature-update/PHASE-06B-REPORT.md`; implementasi `c1dde67`; commit dokumentasi penerimaan `1b34455` (di-push ke `origin/main`).

## [2026-10-31] Fase 06b — Naik kelas, tinggal kelas, dan lulus
- Type: Feature
- Scope / fase: **06b saja** di atas `main` @ `6dcd432` (DEC-30 APPROVED). 06c, merge/deploy, migrasi database operasional/MariaDB, dan perubahan dependency/service/`.env` tidak termasuk.
- Files touched: migration `student_progression_batches`; model `StudentProgressionBatch`; service `StudentProgressionService`; request/controller/route progresi; guard hapus tahun; allowlist audit `StudentRombel`; fixture UAT `prepare-progression`; tes `StudentProgressionTest`; `StudentProgression.vue`; route/menu Academic; kartu Graduated `Student.vue`; dokumentasi fase.
- What changed: menambahkan preview tanpa mutasi dan commit atomik ber-token untuk `promote`/`retain`/`graduate`/`skip` (token 256-bit hash, 30 menit, terikat pembuat, ≤500 keputusan, `409` itemized, replay idempoten), riwayat batch permanen tanpa token/decisions, guard hapus tahun, UI wizard empat langkah Academic → Progression, filter preview hanya pembuat, audit membership tanpa NIK, dan perbaikan kartu Graduated (`counts[6]`).
- Why: menyelesaikan proses akhir tahun ajaran yang selama ini belum ada, dengan mutasi atomik, audit, dan kontrol konkurensi sesuai DEC-30.
- Validation: `php artisan test --filter=StudentProgressionTest` **11 passed/150 assertions**; suite penuh **448 passed/13 skipped/4.121 assertions (250,68 s)**; Pint **10 files PASS**; `route:list` **6 route**; `npm run build` **PASS 26,36 s**; ESLint read-only **0 error**; UAT `.uat-phase06` (153 siswa/12 kelas/2 batch) — commit campuran, replay, `409`, view-only `403`, audit, Graduated card. **NOT RUN:** MariaDB/InnoDB + trigger MySQL, race multi-proses/dua tab nyata, lock sumber di browser, benchmark 500 keputusan, visual regression, penerimaan pemilik.
- Notes / risks: batch #2 preview dan tahun target unlocked sengaja dipertahankan di UAT; baris audit lama #22/#24 memuat NIK sintetis sebelum perbaikan allowlist (audit append-only tidak diubah). Server UAT tetap berjalan sampai penerimaan; data `.uat-phase06` dipertahankan.
- Evidence: `reports/feature-update/PHASE-06B-REPORT.md`, `reports/feature-update/PHASE-06B-MANUAL-REVIEW.md`; paket implementasi+dokumentasi `c1dde67` (di-push ke `origin/main`).

## [2026-10-31] Fase 06b — Pemilik menyetujui rencana (DEC-30 APPROVED)
- Type: Docs
- Scope / fase: Rencana Subfase **06b**; implementasi 06b diotorisasi. Tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `docs/DECISIONS.md` (DEC-30 APPROVED), `reports/feature-update/PHASE-06B-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `CHANGELOG.md`.
- What changed: mencatat pernyataan pemilik **"Saya setuju dieksekusi"** (2026-10-31 WIB) sebagai **DEC-30 APPROVED** untuk D1–D9; rencana 06b menjadi PLAN APPROVED dan implementasi 06b dimulai.
- Why: gate keputusan rencana 06b sudah dijawab; implementasi dapat berjalan tanpa menebak keputusan bisnis.
- Validation: pemeriksaan dokumen/status. **NOT RUN**: tidak ada tes aplikasi pada entri dokumentasi ini.
- Notes / risks: persetujuan tidak mencakup merge/deploy, migrasi DB operasional/MariaDB, dependency/service/`.env`, atau 06c. Rekap sekolah tetap menunggu DEC-12 PROPOSED.
- Evidence: `docs/DECISIONS.md` (DEC-30); `reports/feature-update/PHASE-06B-PLAN.md`.

## [2026-10-31] Fase 06b — Rencana naik/tinggal/lulus disiapkan (PLAN PREPARED, D1–D9 PROPOSED)
- Type: Docs
- Scope / fase: Rencana Subfase **06b** di atas `main` @ `1799cf7`; tidak ada perubahan kode, migration, route, UI, ability, dependency, atau data.
- Files touched: `reports/feature-update/PHASE-06B-PLAN.md` (baru), `reports/feature-update/HANDOFF.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/PHASE-06-PLAN.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `CHANGELOG.md`.
- What changed: menambahkan rencana rinci 06b — audit aktual (status siswa berada pada `student_rombels`, belum ada kode/endpoint/tes naik kelas, `school_levels` tanpa urutan, bug lama kartu "Graduated" `Student.vue` memakai `counts[4]` alih-alih `counts[6]`), semantik promote/retain/graduate/skip, kriteria roster, tabel `student_progression_batches` + token commit 30 menit terikat aktor, endpoint options/roster/preview/commit/batches, wizard Academic → Progression, matriks tes T1–T16, fixture `prepare-progression` pada `.uat-phase06`, risiko, non-goals, dan sembilan keputusan **D1–D9 PROPOSED**.
- Why: melanjutkan Fase 06 ke subfase berikutnya; keputusan bisnis tanggal efektif, semantik lulus, sumber roster, dan batas lock memerlukan persetujuan pemilik sebelum implementasi.
- Validation: pemeriksaan dokumen/tautan dan konsistensi status saja. **NOT RUN**: tidak ada tes backend/frontend, build, browser, migrasi, atau UAT.
- Notes / risks: implementasi 06b belum diotorisasi sampai D1–D9 disetujui dan dicatat sebagai DEC-30; **DEC-30 belum diajukan**. Tidak ada merge/deploy/force-push, perubahan dependency/service/`.env`, akses DB terlarang, atau perubahan `.uat-phase05`/`.uat-phase04b`/`.uat-phase06` (tidak ada server UAT dijalankan pada entri ini).
- Evidence: `reports/feature-update/PHASE-06B-PLAN.md`; baseline `1799cf7`.

## [2026-10-31] Fase 06a — Pemilik menyetujui checklist #1–#12 (OWNER APPROVED)
- Type: Docs
- Scope / fase: Penerimaan manual Subfase **06a**; tidak ada perubahan kode, migration, route, UI, atau data pada entri ini.
- Files touched: `reports/feature-update/PHASE-06A-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-06A-REPORT.md`, `PROJECT_CONTEXT.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `docs/DECISIONS.md`, `reports/feature-update/PHASE-06-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `CHANGELOG.md`.
- What changed: mencatat pernyataan pemilik **"Pengujian nomor 1-12 sudah berhasil dan saya approved."** (2026-10-31 WIB) sebagai penerimaan checklist #1–#12; status 06a menjadi **IMPLEMENTED & OWNER APPROVED**; server UAT 14024/18024 dihentikan setelah penerimaan sesuai DEC-27 dan data `.uat-phase06` dipertahankan.
- Why: penerimaan pemilik dicatat pada commit dokumentasi tersendiri, terpisah dari commit implementasi `d8d9874`; server fase yang sudah lulus dihentikan agar tidak berjalan tanpa dipakai.
- Validation: command line PID 19240 (SPA) dan 2388 (API) diverifikasi sebelum `taskkill /T /F`; `netstat` memastikan port 14024/18024 bebas; `.uat-phase06` (database/storage/password runtime) tetap ada; Apache 80/443 dan MariaDB 3306 tidak disentuh. **NOT RUN:** agen tidak mengulang skenario #1–#12; item MariaDB/konkurensi/dua-tab/screenshot tetap NOT RUN.
- Notes / risks: penerimaan tidak mengotorisasi merge, deploy, migrasi data legacy/MariaDB/produksi, perubahan dependency/service, atau 06b/06c. Rekap sekolah tetap menunggu DEC-12 (PROPOSED).
- Evidence: `reports/feature-update/PHASE-06A-MANUAL-REVIEW.md`; `reports/feature-update/PHASE-06A-REPORT.md`; implementasi `d8d9874`.

## [2026-10-31] Subfase 06a — Periode akademik, lock tahun ajaran, dan rombel
- Type: Feature | Schema Change | Docs
- Scope / fase: **06a saja** di atas `main` @ `25875c9` (DEC-29 APPROVED). 06b/06c, merge/deploy, migrasi database operasional/MariaDB, dan perubahan dependency/service/`.env` tidak termasuk.
- Files touched: tiga migration additive `2029_07_30_*`; `AcademicSetting`, `AcademicPeriodController`; `SchoolYearController`, `SchoolRombelController`, `StudentRombelController`, `EmployeeController`, request assign/transfer/kelas, model `SchoolYear`/`SchoolRombel`/`Employee`, policy `SchoolYear`/`SchoolRombel`, `routes/api.php`; `SchoolYears.vue`, `SchoolRombels.vue`, `Student.vue`, `AppMenu.vue`, `router/index.js`; `tests/Feature/Academic/*`; `scripts/phase06-uat.php`; dokumen kontrak, roadmap, laporan, checklist.
- What changed: periode aktif singleton `academic_settings` (tanpa menebak nilai awal); `school_years` mendapat tanggal + lock beralasan/aktor/audit; CRUD kelas berfungsi dengan nama unik per tahun case-insensitive, `expected_version` + `409`, delete ditolak bila ada riwayat (termasuk soft-deleted), dan wali kelas dari profil `Pendidik` aktif (`class_teach_employee_id`) sambil mempertahankan `class_teach_id` legacy; assign/transfer 03d menolak tahun locked/soft-deleted; UI School Years/Classes/Students + grup menu Academic; route Classes diselaraskan ke `students.view`; UAT terisolasi `.uat-phase06` dengan fixture 130 siswa/12 kelas/10 guru aktif/4 staf.
- Why: fondasi periode aktif, lock, dan kelas yang benar diperlukan sebelum 06b/06c; scaffold kelas sebelumnya tidak dapat menyimpan data atau memilih guru tanpa akun.
- Validation: `php artisan test tests/Feature/Academic` **20 passed/145 assertions**; regresi 03d **27/104**; suite penuh **437 passed/13 skipped/3.971 assertions**; Pint **22 files PASS**; `npm run build` **PASS 806 modules/21,55 s**; `npx eslint --quiet` **exit 0**; UAT `.uat-phase06` checklist #1–#12 dijalankan agen (browser DOM + API + audit). **NOT RUN:** MariaDB/InnoDB migration/backfill dan konkurensi lock, race multi-proses, dua tab nyata, screenshot/visual regression, 06b/06c, CRUD semester, unique index DB nama kelas.
- Notes / risks: `school_rombels_count` pada School Years menghitung kelas soft-deleted agar tahun tidak dapat dihapus saat masih memiliki riwayat; tahun aktif tidak dapat lock/delete dan tahun locked tidak dapat active/delete; lock idempotent; nama kelas belum memakai unique index DB (D6). Server UAT 14024/18024 tetap berjalan sampai penerimaan (DEC-27). Review manual pemilik **PENDING**; commit+push paket review bukan penerimaan.
- Evidence: `reports/feature-update/PHASE-06A-REPORT.md`; `reports/feature-update/PHASE-06A-MANUAL-REVIEW.md`; SHA paket dicatat pada checkpoint berikutnya.

## [2026-10-31] Fase 06 — Pemilik menyetujui D1–D8 (DEC-29 APPROVED)
- Type: Docs
- Scope / fase: Rencana Fase 06; implementasi **06a** diotorisasi. Tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `docs/DECISIONS.md` (DEC-29 APPROVED), `reports/feature-update/PHASE-06-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `CHANGELOG.md`.
- What changed: mencatat pernyataan pemilik **"Saya setuju D1-D8 dilakukan."** (2026-10-31 WIB) sebagai **DEC-29 APPROVED**; rencana Fase 06 berstatus PLAN APPROVED dan implementasi 06a diotorisasi.
- Why: gate keputusan rencana Fase 06 sudah dijawab; implementasi 06a dapat dimulai tanpa menebak pilihan bisnis.
- Validation: pemeriksaan dokumen/status. **NOT RUN**: tidak ada tes aplikasi pada entri dokumentasi ini.
- Notes / risks: persetujuan tidak mencakup merge/deploy, migration DB operasional, dependency/service, atau 06b/06c. Rekap sekolah tetap menunggu DEC-12 PROPOSED.
- Evidence: `docs/DECISIONS.md` (DEC-29); `reports/feature-update/PHASE-06-PLAN.md`.

## [2026-10-31] Fase 06 — Rencana siklus tahun ajaran dan rombel (PLAN PREPARED, D1–D8 PROPOSED)
- Type: Docs
- Scope / fase: Rencana Fase 06 di atas `main` @ `ffa71e3`; tidak ada perubahan kode, migration, route, UI, ability, dependency, atau data.
- Files touched: `reports/feature-update/PHASE-06-PLAN.md` (baru), `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `CHANGELOG.md`.
- What changed: menambahkan rencana rinci Fase 06 — audit aktual, urutan 06a→06b→06c, delapan keputusan pemilik D1–D8, desain 06a (periode aktif singleton, lock tahun ajaran, CRUD rombel berversi, wali kelas dari profil `employees`, penegakan lock pada assign/transfer), garis besar 06b/06c dengan pertanyaan terbuka, matriks tes, risiko, dan UAT baru `.uat-phase06`; menyelaraskan kata commit+push pada prompt subfase dengan aturan repo.
- Why: melanjutkan roadmap ke Fase 06; keputusan bisnis periode aktif, lock, wali kelas, akses, dan UAT memerlukan persetujuan pemilik sebelum implementasi.
- Validation: pemeriksaan dokumen/tautan dan konsistensi status saja. **NOT RUN**: tidak ada tes backend/frontend, build, browser, migrasi, atau UAT.
- Notes / risks: implementasi 06a belum diotorisasi sampai D1–D8 disetujui dan dicatat sebagai DEC baru; tidak ada merge/deploy/force-push, perubahan dependency/service/`.env`, akses DB terlarang, atau perubahan `.uat-phase05`/`.uat-phase04b`. Rekap sekolah tetap menunggu DEC-12 PROPOSED.
- Evidence: `reports/feature-update/PHASE-06-PLAN.md`; baseline `ffa71e3`.

## [2026-10-31] Subfase 05c — Penerimaan manual pemilik (#1–#14) dan penghentian UAT 05
- Type: Docs
- Scope / fase: Subfase 05c di atas `main` @ `c2fddfa`; dokumentasi penerimaan dan operasional; tidak ada perubahan kode aplikasi.
- Files touched: `reports/feature-update/PHASE-05C-MANUAL-REVIEW.md`, `PHASE-05C-REPORT.md`, `PHASE-05C-PLAN.md`, `PHASE-05-PLAN.md`, `INDEX.md`, `HANDOFF.md`, `CHANGELOG.md`, `docs/TESTING.md`, `docs/REQUIREMENTS.md`, `docs/DECISIONS.md` (DEC-28), `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-05-IMPORT-EXPORT.md`, `PROJECT_CONTEXT.md`.
- What changed: mencatat pernyataan pemilik **"Pengujian nomor 1-14 sudah berhasil dan saya approved."** (2026-10-31 WIB) sebagai penerimaan 05c checklist #1–#14; mencatat bukti penghentian server UAT 05 (PID 10336 Vite/19196 PHP diverifikasi command line, `taskkill /T /F`, port 14023/18023 bebas) dan perintah restart.
- Why: penerimaan manual pemilik dicatat terpisah dari implementasi; server UAT fase yang sudah diterima tidak perlu terus berjalan (DEC-27).
- Validation: `Get-CimInstance Win32_Process` memverifikasi command line kedua PID; `taskkill /T /F` menghentikan tree; `netstat` menunjukkan 14023/18023 bebas, Apache 80/443 (PID 1304) dan MariaDB 3306 (PID 18244) tetap listening. **NOT RUN**: tidak ada tes aplikasi ulang; penerimaan berdasarkan pernyataan pemilik, bukan pengujian ulang agen. Item NOT RUN 05c (dua tab/409 browser, MariaDB/InnoDB, uji beban, screenshot visual) tetap NOT RUN.
- Notes / risks: penghentian hanya proses UAT terisolasi; data `.uat-phase05` dan kredensial privat dipertahankan; tidak ada merge/deploy/force-push, perubahan dependency/service/`.env`, atau akses DB terlarang. Rekap sekolah tetap menunggu DEC-12 (PROPOSED).
- Evidence: `reports/feature-update/PHASE-05C-MANUAL-REVIEW.md`, `PHASE-05C-REPORT.md`, `HANDOFF.md`; baseline `c2fddfa`.

## [2026-10-31] Subfase 05c — Pemeriksaan kelengkapan siswa/pegawai dan review operator
- Type: Feature
- Scope / fase: Subfase 05c (DEC-28 APPROVED 2026-10-31 WIB); tidak ada perubahan pada rekap sekolah, kontrak impor 05a/05b, dependency, atau DB operasional.
- Files touched: `laravel-api/app/Services/DataQuality/*` (profil, inspector, issue key, review service), `app/Http/Controllers/{Student,Employee}DataQualityController.php`, request data quality, `app/Models/DataQualityReview.php`, `config/data_quality.php`, migration `2029_07_29_000001_create_data_quality_reviews_table.php`, `routes/api.php`, `scripts/phase05-uat.php`, `tests/Feature/DataQuality/DataQualityApiTest.php`; `vue-app/src/views/pages/public/{DataQuality.vue,DataQualityReviewDialog.vue,dataQualityParams.mjs,Student.vue,EmployeeDirectory.vue}`, `vue-app/src/router/{index.js,routeAbility.mjs}`, `vue-app/src/layout/AppMenu.vue`, `vue-app/src/stores/authStore.js`, tes Node `data-quality-params.test.mjs` + `route-ability.test.mjs`; dokumentasi terkait.
- What changed: menambahkan pemeriksaan kelengkapan siswa (denominator placed; NIK unplaced = blocked di luar persentase) dan pegawai (denominator active; aturan Pendidik/Tenaga Kependidikan), daftar `missing`/`invalid`/`duplicate`/`blocked`, kandidat duplikat tanpa auto-merge, tabel review berversi `data_quality_reviews` (open/acknowledged/ignored + alasan, versi + 409, audit tanpa PII), enam endpoint `/api/data-quality/*` tanpa ability baru, halaman Data Quality English dengan deep link ke form lama, dukungan OR ability pada guard/menu, dan fixture UAT 120 record `UAT-DQ` yang guarded/idempotent.
- Why: melengkapi Fase 05 dengan pemeriksaan kualitas data dan alur review operator tanpa mengubah data domain atau menambah izin; rekap sekolah menunggu format DEC-12.
- Validation: `php artisan test tests/Feature/DataQuality` **15 passed / 239 assertions (9,90 s)**; suite penuh **417 passed / 13 skipped / 3.826 assertions (260,36 s)**; Pint **21 file PASS**; `node --test tests/data-quality-params.test.mjs tests/route-ability.test.mjs` **17 tests, 0 fail**; `npm run build` **PASS 38,18 s**; ESLint read-only **0 error** (warning lama Prettier/CRLF); `route:list --path=data-quality` **6 route**; `prepare-data-quality` idempotent (**0 record** pada run kedua, 60+60 fixture, tally 35 siswa/25 pegawai); browser DOM UAT siswa/pegawai/review/deep link/390 px, **0 console message**. **NOT RUN:** dua tab/409 browser, MariaDB/InnoDB data quality, uji beban, screenshot/visual regression, rekap sekolah, XLSX.
- Notes / risks: status review tidak mengubah persentase; `issue_key` mengikat versi record dan tahun ajaran sehingga koreksi membuka temuan baru; server UAT 14023/18023 masih berjalan untuk review dan dihentikan setelah penerimaan (DEC-27); data `.uat-phase05` dipertahankan. Commit implementasi dan dokumentasi dicatat di HANDOFF/checkpoint berikutnya; push bukan penerimaan.
- Evidence: `reports/feature-update/PHASE-05C-REPORT.md`, `reports/feature-update/PHASE-05C-MANUAL-REVIEW.md`, `docs/DECISIONS.md` (DEC-28).

## [2026-10-31] Subfase 05c — Pemilik menyetujui D1–D7 (DEC-28 APPROVED)
- Type: Docs
- Scope / fase: Rencana Subfase 05c; tidak ada perubahan kode aplikasi pada entri ini.
- Files touched: `docs/DECISIONS.md` (DEC-28 APPROVED), `reports/feature-update/PHASE-05C-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-05-IMPORT-EXPORT.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`.
- What changed: mencatat pernyataan pemilik **"Saya setuju D1-D7."** (2026-10-31 WIB) sebagai **DEC-28 APPROVED**; rencana 05c berstatus PLAN APPROVED dan implementasi 05c diotorisasi.
- Why: gate keputusan S1 pada rencana 05c sudah dijawab; implementasi dapat dimulai tanpa menebak pilihan bisnis.
- Validation: pemeriksaan dokumen/status. **NOT RUN**: tidak ada tes aplikasi pada entri dokumentasi ini.
- Notes / risks: persetujuan tidak mencakup merge/deploy, migration DB operasional, dependency/service, atau Fase 06. Rekap sekolah tetap menunggu DEC-12 PROPOSED.
- Evidence: `docs/DECISIONS.md` (DEC-28); `reports/feature-update/PHASE-05C-PLAN.md`.

## [2026-10-31] Subfase 05c — Rencana pemeriksaan kelengkapan (PLAN PREPARED, DEC-28 PROPOSED)
- Type: Docs
- Scope / fase: Rencana Subfase 05c di atas `main` @ `8b091cf`; tidak ada perubahan kode, migration, route, UI, ability, dependency, atau data.
- Files touched: `reports/feature-update/PHASE-05C-PLAN.md` (baru), `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-05-IMPORT-EXPORT.md`, `docs/DECISIONS.md` (DEC-28 PROPOSED), `docs/REQUIREMENTS.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`.
- What changed: menambahkan rencana rinci 05c — definisi populasi/denominator siswa (placed) dan pegawai (active), incomplete vs blocked untuk NIK siswa unplaced, profil field wajib, deteksi kandidat duplikat tanpa auto-merge, tabel review berversi per tahun ajaran, enam endpoint `data-quality` tanpa ability baru, UI Data Quality + deep link ke form lama, fixture 120 record `UAT-DQ` pada `.uat-phase05`, matriks tes, risiko, dan batas scope. Mencatat tujuh keputusan D1–D7 sebagai **DEC-28 PROPOSED**.
- Why: melanjutkan Fase 05 ke subfase 05c; keputusan bisnis (profil, duplikat, review, UAT) memerlukan jawaban pemilik sebelum implementasi.
- Validation: pemeriksaan dokumen/tautan dan konsistensi status saja. **NOT RUN**: tidak ada tes backend/frontend, build, browser, atau migrasi karena paket ini hanya dokumentasi rencana.
- Notes / risks: rekap sekolah tetap di luar scope dan menunggu contoh format (DEC-12 PROPOSED); tidak ada merge/deploy/force-push, perubahan dependency/service/`.env`, atau akses DB terlarang. Implementasi belum dimulai sampai D1–D7 dijawab.
- Evidence: `reports/feature-update/PHASE-05C-PLAN.md`; baseline `8b091cf`; commit paket rencana `68f6e11` (di-push ke `origin/main`); status rencana dan usulan DEC-28 tercatat di dokumen terkait.

## [2026-10-31] Subfase 05b — Penerimaan manual pemilik (#1–#14) dan kebijakan siklus UAT per fase
- Type: Docs
- Scope / fase: Subfase 05b di atas `main` @ `5633726`; dokumentasi penerimaan dan operasional; tidak ada perubahan kode aplikasi.
- Files touched: `reports/feature-update/PHASE-05B-MANUAL-REVIEW.md`, `PHASE-05B-REPORT.md`, `INDEX.md`, `HANDOFF.md`, `CHANGELOG.md`, `docs/TESTING.md`, `docs/REQUIREMENTS.md`, `docs/DECISIONS.md` (DEC-27), `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-05-IMPORT-EXPORT.md`, `PROJECT_CONTEXT.md`.
- What changed: mencatat pernyataan pemilik **"Pengujian nomor 1-14 sudah berhasil dan saya approved."** (2026-10-31 WIB) sebagai penerimaan 05b checklist #1–#14; mencatat **DEC-27** (lingkungan UAT per fase dihentikan setelah fase lulus); mencatat bukti penghentian server UAT 04b/05 (PID/port, command line diverifikasi) dan perintah restart.
- Why: penerimaan manual pemilik dicatat terpisah dari implementasi; server UAT fase yang sudah tidak diuji tidak perlu terus berjalan.
- Validation: `taskkill /T /F` pada PID 2336/11284 (14022), 12416 (18022), 13456/996 (14023), 17188 (18023); verifikasi `Get-NetTCPConnection` → port 14022/14023/18022/18023 bebas; Apache 80/443 dan MariaDB 3306 tetap listening. **NOT RUN**: tidak ada tes aplikasi ulang; penerimaan berdasarkan pernyataan pemilik, bukan pengujian ulang agen. Item NOT RUN 05b tetap NOT RUN.
- Notes / risks: penghentian hanya proses UAT terisolasi; data `.uat-phase04b`/`.uat-phase05` dan kredensial privat dipertahankan; tidak ada merge/deploy/force-push, perubahan dependency/service/`.env`, atau akses DB terlarang.
- Evidence: `reports/feature-update/PHASE-05B-MANUAL-REVIEW.md`, `PHASE-05B-REPORT.md`, `HANDOFF.md`; baseline `5633726`; dokumentasi penerimaan `c19c7f0` (di-push ke `origin/main`).

## [2026-10-30] Subfase 05b — Impor pegawai dan pilihan kolom ekspor
- Type: Feature
- Scope / fase: Subfase 05b (Fase 05) di atas `main` @ `4f1fec0`; implementasi `ad74309`; dokumentasi `c5afdc4` (di-push ke `origin/main`).
- Files touched: `laravel-api/app/Services/Imports/ImportService.php` (baru, mesin impor abstrak), `app/Services/StudentImportService.php`, `app/Services/EmployeeImportService.php` (baru), `app/Support/EmployeeRules.php` (baru), `app/Support/EmployeeExportColumns.php` (baru), `app/Support/AbilityCatalog.php`, `app/Http/Requests/StoreEmployeeImportRequest.php` (baru), `app/Http/Requests/Concerns/EmployeeProfileRules.php`, `app/Http/Controllers/ImportController.php`, `app/Http/Controllers/EmployeeExportController.php`, `routes/api.php`, `config/imports.php`, `database/migrations/2029_07_28_000001_add_employees_import_ability.php` (baru); `vue-app/src/views/pages/public/EmployeeImportDialog.vue`, `EmployeeExportDialog.vue`, `employeeImportParams.mjs` (baru), `employeeDirectoryParams.mjs`, `EmployeeDirectory.vue`; tes `tests/Feature/Import/EmployeeImportTest.php`, `tests/Feature/Employee/EmployeeExportColumnsTest.php`, `vue-app/tests/employee-import-params.test.mjs`, `vue-app/tests/employee-directory-params.test.mjs`; dokumentasi terkait.
- What changed: mesin impor 05a diekstrak tanpa mengubah kontrak siswa; impor pegawai CSV 22 kolom English (match update **NIK**, NIK wajib update/opsional insert, update parsial, Subject Teacher/Non-teaching, Regency+District berpasangan, laporan error privat `Row,Field,Message,NIK,NUPTK`, commit all-or-nothing + `lockForUpdate`); ability `employees.import` untuk `admin-sistem`/`operator-sekolah`; ekspor pegawai menerima `columns` dari registry 10 kolom operasional + 15 kolom PII, `GET /api/employees/export/options`, PII hanya untuk `employees.manage` (tanpa izin → 403); dialog Export/Import English di direktori Teachers/Employees.
- Why: melanjutkan Fase 05 sesuai DEC-25 D8 dan keputusan pemilik D1–D7 (DEC-26, APPROVED 2026-10-30 WIB).
- Validation: `php artisan test` dua berkas baru **43 passed / 249 assertions**; regresi suite penuh **402 passed / 13 skipped / 3.587 assertions PASS**; regresi impor siswa 05a **18/97 PASS**; `pint --test` **15 file PASS**; `node --test` 3 berkas helper **15 tes PASS**; `npx eslint` file baru **0 error**; `npm run build` **PASS 28,46 s** (warning chunk lama). Sampel UAT 05b sintetis dibuat di `.uat-phase05/samples/` (ignored, tidak dikomit). **NOT RUN**: browser/visual UAT 05b (otomasi browser diblokir kebijakan sesi), MariaDB/InnoDB, two-tab concurrency, retensi otomatis, rekap sekolah, XLSX.
- Notes / risks: tidak ada perubahan schema domain/tabel pegawai; migration hanya menambah ability. PII ekspor dibatasi di server, bukan sekadar UI. Batch impor hanya milik pembuat + domain; 404 untuk aktor lain. Item NOT RUN tidak boleh dianggap PASS. Tidak ada merge/deploy/force-push, perubahan dependency/service, atau akses DB terlarang.
- Evidence: `reports/feature-update/PHASE-05B-REPORT.md`, `reports/feature-update/PHASE-05B-MANUAL-REVIEW.md`; baseline `4f1fec0`; implementasi `ad74309`, dokumentasi `c5afdc4`, keduanya di-push ke `origin/main`.

## [2026-10-30] Subfase 05b — Rencana impor pegawai dan pilihan kolom ekspor
- Type: Docs
- Scope / fase: Perencanaan Subfase 05b (Fase 05) di atas `main` @ `4e95706`; tidak ada implementasi, migration, route, UI, dependency, atau perubahan data.
- Files touched: `reports/feature-update/PHASE-05B-PLAN.md` (baru), `reports/feature-update/PHASE-05-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-05-IMPORT-EXPORT.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`.
- What changed: rencana rinci 05b berisi audit mesin impor 05a/ekspor 04b, desain impor pegawai (template 22 kolom English, match NIK, aturan Subject Teacher/Non-teaching, Regency+District, laporan error privat `Row,Field,Message,NIK,NUPTK`), registry 10 kolom operasional + 15 kolom sensitif khusus `employees.manage`, endpoint `/api/employees/export/options`, rencana UI, matriks tes/UAT, risiko, dan **7 keputusan pemilik D1–D7 (usulan DEC-26)**.
- Why: melanjutkan Fase 05 sesuai DEC-25 D8 (impor pegawai + pilihan kolom ekspor) dengan audit source aktual; keputusan bisnis tidak boleh ditebak.
- Validation: **NOT RUN** — entri dokumentasi; tidak ada tes, build, migration, atau koneksi DB yang dijalankan. Rencana belum diimplementasikan.
- Notes / risks: status **RENCANA — MENUNGGU D1–D7 (usulan DEC-26)**. Rekap sekolah (DEC-12), Dapodik, XLSX, akun pegawai, dan 05c di luar scope. Default ekspor 10 kolom dan perilaku 05a direncanakan tidak berubah; PII ekspor hanya untuk `employees.manage`. Tidak ada merge/deploy/force-push atau perubahan dependency/service.
- Evidence: `reports/feature-update/PHASE-05B-PLAN.md`; baseline `4e95706`; `main...origin/main` sinkron sebelum penyusunan.

## [2026-10-30] Subfase 05a — Penerimaan manual pemilik (checklist #1–#12 APPROVED)
- Type: Docs
- Scope / fase: Penerimaan Subfase 05a oleh pemilik pada UAT SQLite terisolasi `.uat-phase05`; tidak mengubah kode, schema, UI, konfigurasi, atau lingkungan.
- Files touched: `reports/feature-update/PHASE-05A-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-05A-REPORT.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `reports/feature-update/PHASE-05-PLAN.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-05-IMPORT-EXPORT.md`, `docs/REQUIREMENTS.md`, `docs/TESTING.md`, `PROJECT_CONTEXT.md`, `CHANGELOG.md`.
- What changed: status review 05a dari PENDING menjadi **APPROVED**; checklist #1–#12 ditandai disetujui; dicatat kutipan pernyataan pemilik, provenansi bukti, dan batas cakupan penerimaan.
- Why: Pemilik menyatakan "Pengujian nomor 1-12 sudah berhasil dan saya approved." (2026-10-30 WIB).
- Validation: **NOT RUN** — entri dokumentasi; agen tidak menjalankan ulang skenario #1–#12, tes, build, migration, atau koneksi DB untuk mencatat persetujuan ini. Sumber bukti satu-satunya adalah pernyataan pemilik.
- Notes / risks: Persetujuan hanya mencakup implementasi 05a pada UAT terisolasi. Tidak mengotorisasi merge, deploy, migration UAT/produksi/legacy/MariaDB, reset database, perubahan dependency/service, atau pekerjaan 05b/05c. Item NOT RUN agen (05a MariaDB/concurrency, two-tab, screenshot/visual, retensi otomatis) tetap NOT RUN dan tidak diubah menjadi PASS. Implementasi `58828a6`, dokumentasi `a3e19d4`, checkpoint `ad7bb9c` sudah di-push; penerimaan ini dicatat pada commit dokumentasi terpisah.
- Evidence: Pernyataan pemilik "Pengujian nomor 1-12 sudah berhasil dan saya approved." (2026-10-30 WIB); `reports/feature-update/PHASE-05A-MANUAL-REVIEW.md` bagian Owner acceptance.

## [2026-10-30] Subfase 05a — Impor siswa CSV (commit `58828a6`; review pemilik PENDING)
- Type: Feature | Schema Change | Docs
- Scope / fase: Subfase 05a (Fase 05: Impor, ekspor dan kualitas data), DEC-25 D1–D8, di atas `main` @ `b093db7`. Tidak ada merge/deploy/force-push, perubahan `.env` utama, dependency/lockfile, service, atau akses DB terlarang.
- Files touched: `laravel-api/app/Services/StudentImportService.php`, `app/Http/Controllers/ImportController.php`, `app/Http/Requests/StoreStudentImportRequest.php`, `app/Models/ImportBatch.php`, `app/Exceptions/ImportBatchException.php`, `app/Support/StudentRules.php`, `app/Support/AbilityCatalog.php`, `app/Http/Requests/{StoreStudentRequest,UpdateStudentRequest}.php`, `config/imports.php`, `routes/api.php`, `database/seeders/EmployeeSeeder.php`, `database/migrations/2029_07_27_000001_create_import_batches_table.php`, `database/migrations/2029_07_27_000002_add_students_import_ability.php`, `tests/Feature/Import/StudentImportTest.php`, `scripts/phase05-uat.php`; `vue-app/src/views/pages/public/StudentImportDialog.vue`, `studentImportParams.mjs`, `Student.vue` (+18 baris), `tests/student-import-params.test.mjs`; laporan/checklist 05a dan dokumen REQUIREMENTS/API-CONTRACTS/DATABASE/ACCESS-CONTROL/TESTING/ROADMAP/PHASE-05/INDEX/HANDOFF/PROJECT_CONTEXT ini.
- What changed: impor siswa CSV end-to-end — UTF-8 ≤5 MB/2.000 baris, header English 17 kolom, delimiter `,`/`;`, preview tanpa mutasi, commit all-or-nothing dalam satu transaksi + `lockForUpdate`, mode insert/update dengan match NIS lalu NIK (bukan nama), laporan error CSV privat (formula dinetralkan, retensi 30 hari manual), izin `students.import` hanya untuk `admin-sistem`/`operator-sekolah`, template `students.view`. UI wizard English + tombol Import mengikuti kemampuan. `StudentRules` menyatukan aturan form dan impor.
- Why: menyelesaikan bagian impor siswa pada FR-06 sesuai keputusan DEC-25 (APPROVED 2026-10-30 WIB) tanpa menambah dependency atau menyentuh data operasional.
- Validation: `php artisan test tests/Feature/Import` **18 tes / 97 assertions PASS**; regresi Import+Student+Employee **170 passed / 1.097 assertions / 13 skipped PASS** (skip = integrasi MariaDB tanpa password); `pint --test` **16 file PASS**; `node --test tests/student-import-params.test.mjs` **3 PASS**; `npm run build` **PASS 26,02 s**; launcher `scripts/phase05-uat.php status` guard PASS, Students 406, Employees 200 (100/100), ability present; browser DOM valid 406→409, campuran 1 valid/2 invalid ditolak tanpa mutasi (total 409), update via NIS, template CSV, **0 console error**. **NOT RUN**: MariaDB 05a/concurrency, two-tab, screenshot, retensi otomatis, XLSX.
- Notes / risks: implementasi dikomit sebagai `58828a6` (`feat(phase-05a)`) dan dokumentasi `a3e19d4` (`docs(phase-05a)`) di-push ke `origin/main` agar dapat direview di GitHub sebelum cek manual; `main...origin/main` sinkron. Review pemilik masih **PENDING**; commit/push bukan penerimaan. 12 skenario `PHASE-05A-MANUAL-REVIEW.md` **PENDING**. Contoh CSV di `.uat-phase05/samples/` diabaikan Git dan telah dikunci ulang ke identitas belum terpakai (valid 42001–42003, mixed 43001–43003, update target 40001). File runtime/DB/log/kredensial dan artefak pemilik (`console.txt`, `vue-app/test-results/`) tidak di-commit. Retensi 30 hari masih manual (D6).
- Evidence: `reports/feature-update/PHASE-05A-REPORT.md` (E1–E12), `PHASE-05A-MANUAL-REVIEW.md`, `PHASE-05-PLAN.md`; implementasi `58828a6` dan dokumentasi `a3e19d4` di atas `b093db7`.
- Addendum 2026-10-30: status review PENDING pada entri ini bersifat historis; penerimaan pemilik atas checklist #1–#12 dicatat pada entri penerimaan di atas. Bukti dan angka validasi entri ini tidak diubah.

## [2026-10-30] Subfase 04b — Commit dan push koreksi + penerimaan pemilik
- Type: Chore | Docs
- Scope / fase: Checkpoint Git setelah pemilik menyatakan cek manual #1–#18 berhasil dan approved; tidak mengubah kode, schema, konfigurasi, lingkungan, atau dependency.
- Files touched: 53 berkas implementasi/tes/gitignore pada commit `dc5e031`; 11 berkas dokumentasi pada commit `e281419`; entri ini.
- What changed: koreksi English/isolasi UAT dikomit sebagai `dc5e031` (`fix(phase-04b)`), lalu dokumentasi + penerimaan pemilik sebagai `e281419` (`docs(phase-04b)`), sesuai aturan repo bahwa penerimaan dicatat pada commit dokumentasi tersendiri di luar commit implementasi. Push `d10f7b8..e281419` ke `origin/main` berhasil; `main...origin/main` sinkron.
- Why: pemilik memberikan otorisasi tetap "Setiap saya selesai melakukan cek manual dan approved. Kamu bisa commit dan push." sehingga persetujuan 04b 2026-10-30 WIB langsung ditindaklanjuti dengan commit dan push.
- Validation: `git diff --cached --check` dan `git diff --check` PASS; `php -l` pada seluruh berkas PHP yang berubah/ditambah PASS; push ditolak-tidak dan tidak memakai `--force`. Tes/build **tidak dijalankan ulang** untuk checkpoint ini — angka 96/1738, 42 frontend, dan build 18.77 s tetap berasal dari laporan, tidak diagregasikan ulang.
- Notes / risks: repo tidak memiliki `.github/workflows`, jadi push tidak memicu deployment. Tidak ada merge/deploy/migration/service/dependency change. `console.txt` dan `vue-app/test-results/` tetap untracked dan tidak disentuh; `.uat-phase04b/` dan `UAT-ACCOUNTS.local.md` diabaikan. Risiko keamanan terbuka tetap OPEN.
- Evidence: `dc5e031`, `e281419` pada `main`; `git status --porcelain` bersih selain artefak pemilik; kutipan persetujuan pemilik tercatat di `PHASE-04B-MANUAL-REVIEW.md`.

## [2026-10-30] Subfase 04b — Penerimaan manual pemilik (checklist #1–#18 APPROVED)
- Type: Docs
- Scope / fase: Penerimaan Subfase 04b oleh pemilik pada UAT SQLite terisolasi; tidak mengubah kode, schema, UI, konfigurasi, atau lingkungan.
- Files touched: `reports/feature-update/PHASE-04B-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-04B-REPORT.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-04-EMPLOYEES.md`, `PROJECT_CONTEXT.md`, `docs/API-CONTRACTS.md`, `docs/DATABASE.md`, `docs/ACCESS-CONTROL.md`, `CHANGELOG.md`.
- What changed: Status penerimaan 04b diubah dari PENDING menjadi APPROVED; checklist #1–#18 ditandai disetujui; dicatat kutipan pernyataan pemilik, provenansi bukti, dan batas cakupan penerimaan.
- Why: Pemilik menyatakan seluruh pengujian manual #1–#18 berhasil dan memberikan persetujuan (2026-10-30 WIB). Catatan koreksi 2026-10-29 tetap disimpan sebagai addendum, tidak dihapus.
- Validation: **NOT RUN** — entri dokumentasi; agen tidak menjalankan ulang skenario #1–#18, tes, build, migration, atau koneksi DB untuk mencatat persetujuan ini. Sumber bukti satu-satunya adalah pernyataan pemilik, sehingga hasil pengujian sebelumnya di laporan tidak diagregasikan ulang. `git diff --check` PASS.
- Notes / risks: Persetujuan hanya mencakup implementasi 04b pada UAT terisolasi. Tidak mengotorisasi migration UAT/produksi, deploy, merge, force-push, upgrade dependency, reset database, akses target MariaDB, atau penutupan risiko keamanan terbuka. Celah bukti agen (browser UI role lain, konflik taut dua tab, cetak/pratinjau dan screenshot, DDL/InnoDB MariaDB 04b) tetap **NOT RUN** dan tidak diubah menjadi PASS. Perbaikan English/UAT masih belum ter-commit.
- Evidence: Pernyataan pemilik "Pengujian nomor 1-18 sudah berhasil dan saya approved" (2026-10-30 WIB); `reports/feature-update/PHASE-04B-MANUAL-REVIEW.md` bagian Owner acceptance.

## [2026-10-29] Phase 04b — English presentation / isolated UAT correction addendum
- Type: Feature | Fix | Security | Test | Docs.
- Scope / phase: uncommitted 04b corrections above `main` at `d10f7b8`; no commit/push. English/type-separation/synthetic-UAT prerequisites are ready for owner review; checklist #1–#18 remains **PENDING**, no owner approval.
- Files touched: `.gitignore`; Laravel reference-label catalog/helper, controllers/requests, synthetic seeders, guarded UAT launcher and tests; Vue reference-label helper/cache, layout/navigation, employee dialogs/directory, Student/Profile/master/reference views and tests; existing `CHANGELOG.md`, `PROJECT_CONTEXT.md`, `reports/feature-update/{PHASE-04B-REPORT.md,PHASE-04B-MANUAL-REVIEW.md,INDEX.md,HANDOFF.md}`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-04-EMPLOYEES.md`, `docs/{API-CONTRACTS.md,DATABASE.md,ACCESS-CONTROL.md}`. See report for implementation paths.
- What changed: implements full-English app presentation; Teachers = Guru only (`Pendidik`), Employees = non-teachers (`Tenaga Kependidikan`), classification only by `employee_type_id` with canonical jobs retained. Shared 17-domain/243-label JSON and frontend `display_name` preserve canonical names/IDs and user/proper names; English validation/CSV/print/Profile/navigation. Passwords stripped before cache enrichment and route-view key prevents stale cross-route dialogs/data. Archived GET detail uses `withTrashed()` and view + manage (403 view-only). Corrects zero-PII/no-account-data wording: list/export contain name/NUPTK/gender; lists contain account linkage metadata; sensitive fields remain manage-detail only. Unresolved list names are null/blank; current CSV header `Employment Start Date (TMT)`.
- UAT boundary: new SQLite SPA `http://127.0.0.1:14022` / API `http://127.0.0.1:18022`, root `.uat-phase04b` private environment/storage/database, `school_phase04b_session`; guarded launcher `scripts/phase04b-uat.php` (`init`/`prepare`/`status`), exact SQLite target rejects URLs/caches/process overrides, normal migrations only. Never root `artisan --env=uat`; no MariaDB/legacy/main environment, service or dependency changes. Baseline 100 Synthetic Teachers + 100 Synthetic Staff (15 jobs), current 101 each after browser creates, 103 students, 100 synthetic schools (NPSN `04` namespace). Idempotent fixtures preserve edits/archives; only initial five UAT accounts; ignored runtime and `UAT-ACCOUNTS.local.md`, no password literals.
- Why: completes the owner's English presentation, teacher/non-teaching distinction and >=100 synthetic-UAT prerequisites before manual review, without changing canonical reference identities or operational environments.
- Validation: latest explicit seven-file SQLite run **96 tests/1738 assertions PASS, 34.77 s**; separate translation run **119/1223** and earlier correction runs remain recorded in the report. **Do not aggregate overlapping runs**. Final frontend **42 Node tests PASS** including credential cache; final `npm run build` **PASS, 18.77 s** (prior 18.28 s build is historical); targeted ESLint **0 errors** and Pint **PASS** within checked scopes. Baseline build chunk/Browserslist warnings remain; `git diff --check` PASS.
- Browser/HTTP evidence: operator DOM + authenticated API readback: teacher create 201/v1, edit v2, concurrent API v3 then stale UI 409/input preserved/Reload Latest, English blank-reason validation, archive v4 with exit null unchanged, manager archived detail, restore v5, link v6/unlink v7; staff create 201/v1 with fixed type/no Subject; route type separation, English CSV 100 baseline rows/type, mobile staff page/form 390×844 DOM fit/scroll and Student/reference/Profile labels. Separate HTTP checks for operator/principal/vice-principal permissions PASS, with abilities unchanged after link/unlink; not other-role browser UI evidence.
- Notes / risks: initial 04b evidence below remains **historical**, not current SQLite status. Other-role browser UI, two-tab stale/link-conflict, print-preview and screenshot visual checks **NOT RUN**; 04b MariaDB DDL/InnoDB **NOT RUN** (available, excluded targets not accessed); old UAT/production migrations not applied. Current corrections uncommitted; owner `console.txt` and `vue-app/test-results/` remain untracked/untouched. Security/dependency risks remain OPEN.
- Evidence: `reports/feature-update/PHASE-04B-REPORT.md` correction addendum, `PHASE-04B-MANUAL-REVIEW.md` (all 18 owner statuses PENDING), `HANDOFF.md`. No future SHA or owner acceptance invented.
- Addendum 2026-10-30: status PENDING pada entri ini bersifat historis; penerimaan pemilik atas checklist #1–#18 dicatat pada entri 2026-10-30 di atas. Bukti dan angka validasi entri ini tidak diubah.

## [2026-10-29] Subfase 04b — CRUD pegawai, relasi akun, ekspor, dan menu aktif (historical initial checkpoint)
- Type: Feature
- Scope / fase: Subfase 04b (Fase 04: Guru dan Pegawai), DEC-24 D1–D8.
- Files touched: `laravel-api/database/migrations/2029_07_26_000001_add_profile_fields_to_employees.php`, `app/Http/Controllers/EmployeeController.php`, `app/Http/Controllers/EmployeeExportController.php`, `app/Http/Requests/StoreEmployeeRequest.php`, `app/Http/Requests/UpdateEmployeeRequest.php`, `app/Http/Requests/Concerns/EmployeeProfileRules.php`, `app/Http/Resources/EmployeeResource.php`, `app/Support/EmployeeDirectory.php`, `app/Services/EmployeeNameBackfillService.php`, `app/Console/Commands/EmployeeNameBackfillCommand.php`, `app/Models/Employee.php`, `config/employees.php`, `database/factories/EmployeeFactory.php`, `routes/api.php`, tiga berkas tes Employee, lima komponen Vue pegawai + helper, `vue-app/src/router/index.js`, `vue-app/src/layout/AppMenu.vue`, satu unit test frontend, serta dokumen API/access/database/laporan.
- What changed: Migration additive `name`/`nik`/`archive_reason` + index dan backfill nama; CRUD `/api/employees` dengan paginasi/filter/sort server, optimistic locking (`version`, `409` + `current_version`), arsip beralasan + restore, gating field sensitif (daftar tetap memuat nama/NUPTK/gender dan metadata taut akun; wording corrected 2026-10-29; detail sensitif hanya `employees.manage`), kandidat/link/unlink akun tanpa perubahan role, ekspor CSV terfilter berizin ganda dengan batas 10.000 → `422`; route `/public/teachers` dan `/public/employees` memakai satu komponen direktori; menu Teachers/Employees aktif.
- Why: Menyelesaikan DEC-24 (D1–D8) yang diotorisasi pemilik 2026-10-29 WIB sehingga modul Guru/Pegawai dapat dipakai operator.
- Validation: `php artisan test tests/Feature/Employee` → **56 passed, 6 skipped (331 assertions)**; `php artisan test` (suite penuh) → **293 passed, 21 skipped (1448 assertions)**; `node --test tests/employee-directory-params.test.mjs` → **7 passed**; `npm run build` → **PASS** (peringatan ukuran chunk baseline); `pint --test` 17 berkas → **PASS**; `eslint --quiet` 8 berkas → **0 error**. **NOT RUN**: MariaDB disposable 04b (MariaDB tidak berjalan) dan browser UAT (migration UAT belum diotorisasi; data pegawai UAT kosong).
- Notes / risks: Migration `2029_07_26_000001` **belum** diterapkan ke UAT/produksi; alias `/employee` masih deprecated; baris legacy tanpa nama teresolusi tetap `NULL` dan dilaporkan agregat; risiko advisory dependency/lifecycle tetap OPEN.
- Evidence: `reports/feature-update/PHASE-04B-REPORT.md`, `reports/feature-update/PHASE-04B-MANUAL-REVIEW.md` (PENDING); implementation commit `e1de4f4`; dokumentasi `b93f954`.

## [2026-10-29] Subfase 04b — Otorisasi pemilik dan pencatatan DEC-24
- Type: Docs
- Scope / fase: Otorisasi Subfase 04b (CRUD pegawai, relasi akun, ekspor, menu aktif); belum ada kode yang diubah pada entri ini.
- Files touched: `docs/DECISIONS.md`, `reports/feature-update/PHASE-04B-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-04-EMPLOYEES.md`, `CHANGELOG.md`.
- What changed: DEC-24 dicatat APPROVED (2026-10-29 WIB) berisi pemetaan D1–D8; status rencana 04b diubah menjadi diotorisasi/dieksekusi; sub-item yang ditafsirkan teknis (keunikan `nik` unconditional, tanpa re-link otomatis, daftar kolom ekspor tanpa PII) dicatat eksplisit.
- Why: Pemilik menyatakan "Saya setuju rekomendasi kamu, lakukan eksekusi." atas rencana 04b dan seluruh rekomendasi D1–D8.
- Validation: **NOT RUN** — entri dokumentasi; tidak ada tes/build/migration yang dijalankan.
- Notes / risks: Otorisasi tidak mencakup merge/deploy, migration UAT, atau perubahan dependency. `employees.export` kini menjadi komitmen implementasi 04b.
- Evidence: Pernyataan pemilik 2026-10-29 WIB; DEC-24; `PHASE-04B-PLAN.md`.

## [2026-10-29] Subfase 04a — Penerimaan manual pemilik (checklist #1–#13 APPROVED)
- Type: Docs
- Scope / fase: Penerimaan Subfase 04a oleh pemilik; tidak mengubah kode, schema, atau UI.
- Files touched: `reports/feature-update/PHASE-04A-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-04A-EMPLOYEE-SCHEMA.md`, `reports/feature-update/PHASE-04A-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `CHANGELOG.md`.
- What changed: Status penerimaan 04a diubah dari PENDING menjadi APPROVED; checklist #1–#13 ditandai disetujui; dicatat kutipan pernyataan pemilik beserta provenansi bukti dan batas cakupan penerimaan.
- Why: Pemilik menyatakan seluruh pengujian manual #1–#13 berhasil dan memberikan persetujuan (DEC-04).
- Validation: **NOT RUN** — tidak ada pengujian, Tinker, koneksi DB, atau migration yang dijalankan ulang untuk mencatat persetujuan; sumber bukti satu-satunya adalah pernyataan pemilik.
- Notes / risks: Persetujuan hanya mencakup fondasi 04a; 04b (CRUD/UI) belum diotorisasi, migration `2029_07_25_000001` belum diterapkan ke UAT, dan risiko keamanan terbuka tetap OPEN.
- Evidence: Pernyataan pemilik "Pengujian nomor 1-13 sudah berhasil dan saya approved" (2026-10-29 WIB); laporan `PHASE-04A-EMPLOYEE-SCHEMA.md` dan `PHASE-04A-MANUAL-REVIEW.md`.

## [2026-10-29] Subfase 04a — Re-verifikasi independen dan perbaikan gap fondasi profil pegawai
- Type: Fix | Security | Schema Change | Test | Docs
- Scope / fase: Subfase 04a (Fase 04: Guru dan Pegawai); tidak menyentuh CRUD/UI 04b.
- Files touched:
  - `laravel-api/database/migrations/2029_07_25_000001_add_legacy_profile_marker_to_employees.php` (baru)
  - `laravel-api/database/migrations/2029_07_24_000001_update_employees_schema_for_phase_04a.php` (down fail-closed, rebuild SQLite atomik)
  - `laravel-api/app/Console/Commands/EmployeeBackfillCommand.php` (baru)
  - `laravel-api/app/Models/Employee.php`, `laravel-api/app/Services/EmployeeBackfillService.php`
  - `laravel-api/tests/Feature/Employee/EmployeeSchemaAndAccountRelationTest.php`, `laravel-api/tests/Feature/Employee/MariaDbEmployeeIntegrationTest.php`
  - `docs/DATABASE.md`, `docs/API-CONTRACTS.md`, `reports/feature-update/PHASE-04A-*.md`, `reports/feature-update/INDEX.md`, `roadmap/ROADMAP.md`
- What changed: Menambahkan penanda `legacy_profile_at` agar fallback NIK hanya berlaku untuk baris pra-04a; backfill mengecualikan akun soft-deleted dan berjalan atomik; `resolveUser()` ber-gate legacy; allowlist audit mengeluarkan NUPTK; `down()` menolak rollback sebelum DDL bila data tidak representable dan rebuild SQLite dibungkus transaksi; command `app:employee-account-backfill --dry-run` tanpa PII; test MariaDB memakai user khusus DB `school_04a`. Koreksi dokumentasi: `routes/api.php` tidak berubah pada 04a (sudah memakai `employees.view`/`employees.manage` pada baseline).
- Why: Menutup celah relink akun melalui NIK untuk profil baru/akun terhapus, mencegah rollback parsial dan kehilangan link diam-diam, serta memenuhi allowlist audit minimum (DEC-04 #3, #5, #6).
- Validation: SQLite 21 passed / 111 assertions; MariaDB 6 passed / 31 assertions; FactoryIntegrity 3/26 dan StudentRombelAssignment 16/78 PASS; Pint --test PASS (7 berkas). NOT RUN: `MariaDbRombelConcurrencyTest` (database di luar allowlist).
- Notes / risks: Migration `2029_07_25_000001` belum diterapkan ke UAT (migrasi UAT tidak diotorisasi). Risiko dependency/lifecycle tetap OPEN. Penerimaan manual pemilik tetap PENDING.
- Evidence: `reports/feature-update/PHASE-04A-EMPLOYEE-SCHEMA.md`.

## [2026-10-26] Subfase 04a — Implementasi fondasi schema profil pegawai dan hubungan akun
- Type: Feature | Schema Change | Test | Docs
- Scope / fase: Subfase 04a (Fase 04: Guru dan Pegawai); implementasi fondasi schema, model, audit allowlist, policy, dan backfill akun.
- Files touched:
  - `laravel-api/database/migrations/2029_07_24_000001_update_employees_schema_for_phase_04a.php` (baru)
  - `laravel-api/app/Models/Employee.php`
  - `laravel-api/app/Policies/EmployeePolicy.php`
  - `laravel-api/app/Services/EmployeeBackfillService.php` (baru)
  - `laravel-api/database/factories/EmployeeFactory.php`
  - `laravel-api/routes/api.php`
  - `laravel-api/tests/Feature/Employee/EmployeeSchemaAndAccountRelationTest.php` (baru)
  - `laravel-api/tests/Feature/Employee/MariaDbEmployeeIntegrationTest.php` (baru)
  - `docs/DATABASE.md`
  - `docs/API-CONTRACTS.md`
  - `reports/feature-update/PHASE-04A-EMPLOYEE-SCHEMA.md` (baru)
  - `reports/feature-update/PHASE-04A-MANUAL-REVIEW.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `roadmap/phases/PHASE-04-EMPLOYEES.md`
  - `CHANGELOG.md`
- What changed:
  1. Migration additive: `user_id` nullable unique FK ke `users.id` (`nullOnDelete`), `user_id_resolved_at`, `account_unlinked_at`, `version` default 1; pelonggaran `user_nik` dan `subject_id` menjadi nullable secara safe.
  2. Model `Employee`: relasi utama `user()` via `user_id`, relasi legacy `legacyUser()` via `user_nik`, resolusi aman `resolveUser()`, koreksi typo `religion()` + alias `relogion()`, allowlist audit privacy `$auditableAttributes` (strip nama ibu kandung, pasangan, KK, NPWP, IPK).
  3. Layanan `EmployeeBackfillService`: pemadanan akun legacy dari `user_nik` ke `user_id` secara idempotent dan transaksional tanpa kebocoran PII.
  4. Policy & Route: `EmployeePolicy` menegakkan `employees.view` dan `employees.manage`, rute `/employee` diselaraskan izinnya.
  5. Pengujian komprehensif: 15 tes SQLite in-memory PASS (89 assertions), 5 tes integrasi MariaDB pada database disposable `school_04a_integration` PASS (25 assertions), Pint 100% PASS.
- Why: Memenuhi seluruh spesifikasi fondasi schema profil pegawai dan hubungan akun sesuai Keputusan Pemilik DEC-04.
- Validation:
  - `php artisan test tests/Feature/Employee/EmployeeSchemaAndAccountRelationTest.php` -> 15 passed (89 assertions).
  - `php artisan test tests/Feature/Employee/MariaDbEmployeeIntegrationTest.php` -> 5 passed (25 assertions).
  - Pint `./vendor/bin/pint --test` -> 7 files PASS.
  - Regresi relevan (`FactoryIntegrityTest`, `StudentRombelAssignmentTest`, `MariaDbRombelConcurrencyTest`) -> 26 passed.
- Notes / risks: Checklist manual `reports/feature-update/PHASE-04A-MANUAL-REVIEW.md` (#1–#10) berstatus PENDING untuk peninjauan fondasi pemilik. CRUD/form/UI tetap berada pada Subfase 04b.
- Evidence: `reports/feature-update/PHASE-04A-EMPLOYEE-SCHEMA.md`, `reports/feature-update/PHASE-04A-MANUAL-REVIEW.md`.

## [2026-10-26] Keputusan Pemilik DEC-04 — Hubungan pegawai dan akun login disahkan
- Type: Docs
- Scope / fase: Subfase 04a (Fase 04: Guru dan Pegawai); pengesahan keputusan pemilik DEC-04 dan penegasan batas scope fondasi 04a vs CRUD/UI 04b.
- Files touched:
  - `docs/DECISIONS.md`
  - `reports/feature-update/PHASE-04A-PLAN.md`
  - `CHANGELOG.md`
- What changed:
  1. Mencatat keputusan pemilik **DEC-04** sebagai **APPROVED** di `docs/DECISIONS.md`:
     - Profil pegawai independen; akun login `users` bersifat opsional (`user_id` nullable).
     - Menambahkan `user_id` (nullable bigint FK ke `users.id` dengan `nullOnDelete()`) sebagai relasi utama; relasi legacy `user_nik` dipertahankan dan dibuat nullable.
     - Satu akun maksimal terhubung ke satu profil pegawai (`unique('user_id')`, multiple NULL diizinkan). Akun yang terhubung tidak mengubah role/abilities.
     - `subject_id` dibuat nullable secara schema (wajib untuk Guru/Pendidik; NULL untuk Tenaga Kependidikan). Validasi input bisnis endpoint dikerjakan pada 04b.
     - Menambahkan kolom `version` unsigned integer default 1 untuk fondasi optimistic concurrency control. Kontrak update `expected_version` dan HTTP 409 Conflict diimplementasikan pada 04b.
     - Audit trail memakai allowlist minimum; mengecualikan nama ibu kandung, pasangan, nomor KK, NPWP, nilai/IPK ijazah, dan relasi akun/hash/password.
     - Kontrak baru memakai English; nama kolom existing tidak diganti massal.
  2. Mengoreksi scope Subfase 04a pada `reports/feature-update/PHASE-04A-PLAN.md`: membatasi 04a pada fondasi schema, model, allowlist audit, dan backfill ID/NIK aman. CRUD lengkap, form, antarmuka pengguna (UI), dan tes endpoint create 201/update 409 ditegaskan berada pada Subfase 04b.
- Why: Menindaklanjuti instruksi eksplisit pemilik sebelum memulai implementasi teknis.
- Validation: Verifikasi konsistensi dokumen `docs/DECISIONS.md`, `PHASE-04A-PLAN.md`, dan `CHANGELOG.md`.
- Evidence: `docs/DECISIONS.md`, `reports/feature-update/PHASE-04A-PLAN.md`.

## [2026-10-26] Subfase 04a — Perencanaan schema profil pegawai dan hubungan akun (DEC-04)
- Type: Docs
- Scope / fase: Subfase 04a (Fase 04: Guru dan Pegawai); penyusunan rencana teknis, audit source statis, dan proposal keputusan bisnis DEC-04.
- Files touched:
  - `reports/feature-update/PHASE-04A-PLAN.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `CHANGELOG.md`
- What changed:
  1. Audit statis baseline personalia:
     - Tabel `employees` mewajibkan relasi akun pengguna via string `user_nik` (harus ada di `users`).
     - Kolom `subject_id` berstatus `NOT NULL`, memaksakan pegawai non-guru (TU, pustakawan, satpam) memilih mapel.
     - Ketiadaan kolom `version` (belum siap optimistic locking 409).
     - Typo relasi di model `Employee`: `relogion()` seharusnya `religion()`.
     - Ketiadaan allowlist `auditableAttributes` di model `Employee` (rentan membocorkan nama ibu kandung, pasangan, nomor KK, NPWP ke tabel audit).
     - Route API singular `/employee` dengan permission yang salah (`master.view`/`master.manage`), serta controller/policy/request yang masih berupa scaffold kosong.
     - Menu `Teachers` dan `Employees` di `AppMenu.vue` masih berupa placeholder `#`.
  2. Menyusun dokumen perencanaan teknis `reports/feature-update/PHASE-04A-PLAN.md` yang merumuskan:
     - Scope in-scope (DEC-04, migration additive `user_id`, `subject_id` nullable, `version`, koreksi typo, audit privacy allowlist, route `/employees`, otorisasi `employees.*`).
     - Scope out-of-scope (UI Vue ditunda ke 04b, penugasan mengajar jam detail ke Fase 10, wali kelas ke Fase 06, payroll/absensi out of roadmap).
     - Usulan keputusan pemilik DEC-04 (akun opsional, integer FK `user_id`, mapel nullable untuk non-guru, integer versioning 409, allowlist audit PII, 100% English contracts).
     - Pemisahan tegas jalur fitur dari jalur modernisasi framework.
- Why: Menyiapkan fondasi personalia sekolah yang bersih, modular, dan terencana sebelum memulai implementasi kode.
- Validation: Audit statis berkas sumber dan konsistensi dokumen; tidak ada migration/seed/code changes.
- Evidence: `reports/feature-update/PHASE-04A-PLAN.md`.

## [2026-10-26] Subfase 03d — Penerimaan manual pemilik (Checklist #1–#15 APPROVED)
- Type: Docs
- Scope / fase: Subfase tambahan 03d (Penempatan dan Perpindahan Rombel via UI); pencatatan penerimaan manual pemilik.
- Files touched:
  - `reports/feature-update/PHASE-03D-MANUAL-REVIEW.md`
  - `reports/feature-update/PHASE-03D-STUDENT-PLACEMENT.md`
  - `reports/feature-update/HANDOFF.md`
  - `reports/feature-update/INDEX.md`
  - `roadmap/ROADMAP.md`
  - `roadmap/phases/PHASE-03-STUDENTS.md`
  - `CHANGELOG.md`
- What changed:
  1. Mencatat persetujuan manual pemilik atas seluruh checklist peninjauan manual `PHASE-03D-MANUAL-REVIEW.md` nomor #1–#15 (status APPROVED pada 2026-10-26 WIB mengacu baseline implementasi `7bed397` dan penguatan bukti uji `f6be20f`).
  2. Memperbarui status Subfase tambahan 03d menjadi **DITERIMA LENGKAP & DITUTUP OLEH PEMILIK**. Fase 01, 02, dan 03 (03a, 03b, 03c, 03d) resmi ditutup lengkap.
  3. Memisahkan secara tegas bukti penerimaan manual pemilik dari bukti otomatis agen (SQLite in-memory, MariaDB concurrency harness, frontend build/unit, dan HTTP/API fetch UAT smoke).
  4. Menegaskan batas keamanan: penerimaan manual ini bukan security clearance, bukan izin deployment produksi, bukan izin upgrade dependensi, dan tidak mengizinkan akses ke database operasional. Seluruh risiko keamanan terbuka (advisories Composer/npm, lifecycle Laravel 10/PHP 8.2, paparan historis host, rotasi rahasia produksi) tetap berstatus OPEN.
- Why: Menutup Subfase tambahan 03d secara administratif sesuai instruksi eksplisit pemilik sebelum melangkah ke perencanaan Fase 04a.
- Validation: Verifikasi konsistensi dokumen dan status git; tanpa eksekusi test/build/DB.
- Evidence: `reports/feature-update/PHASE-03D-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-03D-STUDENT-PLACEMENT.md`.

## [2026-10-26] Pemulihan akses login UAT sekolah untuk review manual 03d
- Type: Fix | Docs
- Scope / fase: Lingkungan UAT Subfase 03d; pemulihan akses login `uat.operator` dan `uat.kepsek` pada server UAT aktif (`school_compat_uat`).
- Files touched:
  - `reports/feature-update/HANDOFF.md`
  - `CHANGELOG.md`
- What changed:
  1. Diagnosis divergensi DB target: `UatSeeder` pada checkout utama (`C:\xampp\htdocs\app-administration_school\laravel-api`) menargetkan `school_access_uat` (karena `.env.uat` checkout utama merujuk DB tersebut), sementara server aktif UAT di port 18021/14021 dilayani oleh checkout terisolasi (`C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\staging\checkout\laravel-api`) yang merujuk ke database `school_compat_uat`.
  2. Transparansi akses database: saat diagnosis, pemeriksaan read-only (`SELECT`) dilakukan terhadap `school_access_uat` dan `backdata_administrations`, yang dicatat secara transparan berada di luar target DB yang diotorisasi (`school_compat_uat`). Akses tersebut murni baca dan dibedakan secara tegas dari mutasi data. Pemeriksaan `SELECT COUNT(*)` pada `backdata_administrations` mengembalikan 0 baris akun `uat.%`; ketiadaan akun ini dicatat tidak membuktikan keselamatan atau ketiadaan perubahan historis database secara menyeluruh.
  3. Pemulihan terbatas: mutasi data dilakukan secara eksklusif pada database terotorisasi `school_compat_uat` dengan mereset kata sandi sintetis untuk `uat.operator` dan `uat.kepsek` menggunakan generator privat aman dan memperbarui file lokal privat `secret_uat_password.txt`.
  4. Atribut pengguna, peran (`operator-sekolah`, `kepala-sekolah`), kemampuan (`students.manage`, `students.view`), dan data lain tidak dimodifikasi.
- Why: Memulihkan kemampuan pemilik untuk login ke UI UAT port 14021 guna menjalankan checklist manual review 03d #1–#15.
- Validation:
  - Verifikasi API/Sanctum terarah via Node fetch:
    - `uat.operator`: CSRF 204, Login 204, Check-auth 200 (role: `operator-sekolah`, ability `students.manage` present, `backup.manage` absent), Logout 204, sesi bersih.
    - `uat.kepsek`: CSRF 204, Login 204, Check-auth 200 (role: `kepala-sekolah`, ability `students.view` present, `students.manage` absent), Logout 204, sesi bersih.
  - Review manual browser tetap PENDING untuk dilakukan langsung oleh pemilik.
- Notes / risks: Tidak ada password, hash, atau rahasia yang dicetak ke terminal atau git. Server UAT tidak direstart.
- Evidence: `reports/feature-update/HANDOFF.md`.

## [2026-10-26] Subfase 03d — Penguatan bukti pengujian rollback audit dan konkurensi MariaDB
- Type: Test | Docs
- Scope / fase: Subfase 03d (Penguatan bukti pengujian penempatan dan perpindahan rombel); diotorisasi pemilik sesuai gap review.
- Files touched:
  - `laravel-api/tests/Feature/Student/StudentRombelAssignmentTest.php`
  - `laravel-api/tests/Feature/Student/MariaDbRombelConcurrencyTest.php`
  - `laravel-api/tests/Support/concurrency_worker.php` (baru)
  - `reports/feature-update/PHASE-03D-STUDENT-PLACEMENT.md`
  - `reports/feature-update/HANDOFF.md`
  - `reports/feature-update/INDEX.md`
  - `CHANGELOG.md`
- What changed:
  1. Menambahkan tes rollback kegagalan audit pada `StudentRombelAssignmentTest.php` untuk Assign dan Transfer via `Activity::$testCreatingHook`. Membuktikan bahwa kegagalan audit membatalkan mutasi keanggotaan dan increment versi siswa secara utuh.
  2. Mengimplementasikan test harness worker proses independen `concurrency_worker.php` yang menjalankan controller mutasi aplikasi nyata (`assignRombel`, `transferRombel`, `destroy`) pada database disposable `school_03d_integration`.
  3. Memperluas `MariaDbRombelConcurrencyTest.php` menjadi 7 skenario (43 assertions, 10.55s):
     - Dua transfer simultan konkuren (1 sukses 200, 1 konflik 409).
     - Penolakan siswa terarsip pre-existing (non-race).
     - Race Archive vs Assign (kedua urutan lock: Assign duluan [200/409] & Archive duluan [200/422]).
     - Race Archive vs Transfer (kedua urutan lock: Transfer duluan [200/409] & Archive duluan [200/422]).
     - Bukti deterministik tumpang tindih lock InnoDB via `SHOW FULL PROCESSLIST` / `innodb_lock_waits` / `innodb_trx`.
- Why: Menutup seluruh celah pembuktian konkurensi dan atomisitas rollback audit sesuai instruksi eksplisit pemilik tanpa mengubah arsitektur atau memperluas fitur.
- Validation:
  - `php artisan test tests/Feature/Student/StudentRombelAssignmentTest.php` -> 16 passed (78 assertions).
  - `php artisan test tests/Feature/Student/MariaDbRombelConcurrencyTest.php` -> 7 passed (43 assertions).
  - Regresi Student SQLite (`StudentDirectoryTest`, `StudentCrudTest`, `StudentAgeAndIdentityTest`, `StudentRombelResolutionTest`, `StudentRombelConstraintTest`, `StudentMigrationAndBackfillTest`) -> 68 passed (333 assertions).
  - `./vendor/bin/pint --test` pada file PHP yang berubah/baru -> PASS (3 files clean).
- Notes / risks: Checklist manual `reports/feature-update/PHASE-03D-MANUAL-REVIEW.md` (#1–#15) tetap PENDING menunggu peninjauan pemilik.
- Evidence: `reports/feature-update/PHASE-03D-STUDENT-PLACEMENT.md`, `reports/feature-update/HANDOFF.md`.

## [2026-10-26] Subfase 03d — Implementasi penempatan dan perpindahan rombel via UI
- Type: Feature | Docs | Test
- Scope / fase: Subfase 03d (Penempatan dan Perpindahan Rombel via UI); scope tambahan yang diotorisasi pemilik (DEC-23).
- Files touched:
  - `laravel-api/routes/api.php`
  - `laravel-api/app/Http/Controllers/StudentRombelController.php`
  - `laravel-api/app/Http/Requests/AssignStudentRombelRequest.php` (baru)
  - `laravel-api/app/Http/Requests/TransferStudentRombelRequest.php` (baru)
  - `laravel-api/app/Policies/StudentRombelPolicy.php`
  - `vue-app/src/views/pages/public/Student.vue`
  - `laravel-api/tests/Feature/Student/StudentRombelAssignmentTest.php` (baru)
  - `laravel-api/tests/Feature/Student/MariaDbRombelConcurrencyTest.php` (baru)
  - `reports/feature-update/PHASE-03D-STUDENT-PLACEMENT.md` (baru)
  - `reports/feature-update/PHASE-03D-MANUAL-REVIEW.md` (baru)
  - `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `CHANGELOG.md`
- What changed:
  1. Backend endpoints `POST /api/students/{id}/assign-rombel` dan `POST /api/students/{id}/transfer-rombel` dengan proteksi `can:students.manage`.
  2. Logika transaksi atomik ber-row-lock InnoDB (`Student::lockForUpdate`) untuk mencegah race condition. Tanggal efektif ditentukan server menggunakan tanggal hari berjalan pada timezone `Asia/Jakarta`.
  3. Preservasi riwayat keanggotaan lama pada transfer: `latest_student_exit_date = $todayWib` diisi, `student_status_id` dipertahankan (tidak diubah ke Mutasi Keluar).
  4. Optimistic concurrency control (HTTP 409 Conflict) menggunakan `expected_student_version` dan `expected_membership_version`.
  5. UI `Student.vue`: tombol aksi kondisional (Assign Rombel untuk Unplaced, Transfer Rombel untuk Placed), dialog interaktif `placementDialog`, double-submit guard, error handling 409 & 422, dan refresh instan tabel & rekap server.
  6. Suite pengujian otomatis SQLite in-memory murni (14 skenario di `StudentRombelAssignmentTest.php`) dan pengujian integrasi MariaDB pada database disposable `school_03d_integration` (2 skenario di `MariaDbRombelConcurrencyTest.php`).
- Why: Memenuhi keputusan pemilik DEC-23 untuk melengkapi alur operasional siswa tanpa rombel (Unplaced) dan perpindahan rombel di UI.
- Validation (hasil aktual):
  - Suite backend SQLite-only: **235 passed (1099 assertions)** across 28 test files in 235.78s.
  - Suite integrasi MariaDB disposable (`school_03d_integration`): **2 passed (6 assertions)** in 2.11s.
  - Frontend unit tests: **18 passed** in 1090ms.
  - Frontend production build: **PASS** (789 modules in 1m 9s).
  - Frontend lint terarah tanpa `--fix`: **0 errors, 2750 warnings** (CRLF).
  - Pint: **PASS** (100% clean pada seluruh berkas PHP yang dimodifikasi).
  - UAT smoke test (16 skenario): **PASS** pada server loopback 18021/14021.
- Notes / risks: Subfase 03d berstatus IMPLEMENTED & FULLY VERIFIED (AGEN) / MANUAL ACCEPTANCE PENDING (checklist manual #1–#15 menunggu review pemilik); Fase 01, 02, dan 03 tetap DITERIMA LENGKAP & DITUTUP; risiko advisory dependency, EOL framework, dan eksposur historis tetap OPEN.
- Evidence: `reports/feature-update/PHASE-03D-STUDENT-PLACEMENT.md`, `reports/feature-update/PHASE-03D-MANUAL-REVIEW.md`.

## [2026-10-26] Subfase 03d — Pencatatan keputusan pemilik (DEC-23) dan otorisasi implementasi
- Type: Docs
- Scope / fase: Subfase 03d (Penempatan dan Perpindahan Rombel via UI); pencatatan keputusan pemilik DEC-23 dan otorisasi implementasi.
- Files touched:
  - `docs/DECISIONS.md`
  - `reports/feature-update/PHASE-03D-PLAN.md`
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `CHANGELOG.md`
- What changed:
  1. Mencatat keputusan pemilik DEC-23 di `docs/DECISIONS.md`:
     a. Implementasikan Assign Rombel dan Transfer Rombel saja; fitur koreksi/`is_correction` ditunda (tanpa checkbox koreksi).
     b. Menggunakan tahun ajaran aktif terpilih pada halaman Students (`school_year_id`); server memvalidasi rombel tujuan.
     c. Transfer strictly antar-rombel dalam tahun ajaran yang sama; transfer lintas tahun ditunda ke Fase 06.
     d. Tanggal efektif ditentukan server hari berjalan di timezone `Asia/Jakarta`; tanggal mundur/terjadwal tidak didukung.
     e. Transfer menutup keanggotaan lama (`latest_student_exit_date = $today`) sebagai riwayat; `student_status_id` tidak diubah ke Mutasi Keluar.
     f. Otorisasi ditegakkan seragam mengikuti kemampuan `students.manage`.
     g. Kontrol konkurensi atomik via `expected_student_version` dan `expected_membership_version` (HTTP 409 Conflict).
  2. Menyelaraskan dokumen rencana `reports/feature-update/PHASE-03D-PLAN.md` dengan status DIOTORISASI PEMILIK (DEC-23).
  3. Memperbarui `INDEX.md`, `HANDOFF.md`, dan `roadmap/ROADMAP.md` dengan status otorisasi 03d.
- Why: Memenuhi instruksi pemilik Bagian 1 untuk mencatat keputusan DEC-23 secara resmi dalam commit terpisah sebelum eksekusi kode.
- Evidence: `docs/DECISIONS.md`, `reports/feature-update/PHASE-03D-PLAN.md`.

## [2026-10-26] Rencana rinci Subfase 03d (kandidat penempatan & perpindahan rombel UI)
- Type: Docs
- Scope / fase: Perencanaan rinci Subfase 03d (Penempatan dan Perpindahan Rombel via UI); dokumentasi dan audit source saja, belum diotorisasi untuk implementasi.
- Files touched:
  - `reports/feature-update/PHASE-03D-PLAN.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `CHANGELOG.md`
- What changed:
  1. Audit source aktual: menganalisis `StudentRombel.php`, event `saving`, dan database triggers MariaDB/SQLite existing yang menegakkan maksimal 1 keanggotaan rombel aktif per siswa per tahun; mencatat `StudentRombelController.php` masih berupa scaffold kosong.
  2. Merancang alur operasional: penempatan siswa Unplaced ke rombel tahun aktif, perpindahan rombel siswa Placed dengan pencatatan riwayat keluar, penolakan siswa terarsip, dan perumusan kebijakan koreksi salah input vs mutasi resmi.
  3. Merancang kontrak API: endpoint `POST /api/students/{id}/assign-rombel` dan `POST /api/students/{id}/transfer-rombel`, payload minimum, validasi 422 English, otorisasi `can:students.manage`, respons 409 Conflict, dan transaksi atomik dengan row lock InnoDB (`FOR UPDATE`).
  4. Merancang integrasi antarmuka di `Student.vue`: tombol kondisional `Assign Rombel` / `Change Rombel` pada kolom Action, dialog interaktif `placementDialog`, double-submit guard, dan refresh instan data tabel & rekap server (100% English).
  5. Menyusun matriks pengujian lengkap (SQLite validation & permission, MariaDB concurrency serialisasi penempatan simultan di database `school_compat_uat`, dan browser UAT) serta daftar 3 pertanyaan keputusan pemilik sebelum implementasi.
- Why: Menyajikan rancangan teknis komprehensif bagi kandidat Subfase 03d untuk ditinjau pemilik sebelum otorisasi implementasi.
- Evidence: `reports/feature-update/PHASE-03D-PLAN.md`.

## [2026-10-26] Rencana pekerjaan sekolah berikutnya pasca-Fase 03
- Type: Docs
- Scope / fase: Rencana fase berikutnya (analisis posisi kandidat 03d vs Fase 04 Guru dan Pegawai, pemisahan jalur modernisasi). Tanpa implementasi kode.
- Files touched:
  - `reports/feature-update/NEXT-STEPS-PLAN.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `CHANGELOG.md`
- What changed:
  1. Menganalisis posisi kandidat 03d (aksi penempatan rombel via UI) terhadap urutan roadmap: Opsi 1 (rekomendasi: menyelesaikan 03d terlebih dahulu agar alur siswa unplaced dan mutasi kelas tuntas) vs Opsi 2 (langsung membuka Fase 04a Guru dan Pegawai).
  2. Menyusun scope teknis, dependensi, kriteria penerimaan, dan pertanyaan keputusan pemilik untuk Subfase 03d dan Subfase 04a (hubungan akun-pegawai DEC-04 dan nullability `subject_id`).
  3. Memisahkan jalur fitur domain sekolah dari jalur modernisasi jangka panjang (upgrade terencana ke Laravel 12 dan targeted package update).
  4. Merancang arsitektur pengujian sintetis untuk fase berikutnya (SQLite-first + MariaDB triggers terarah + UAT strict port).
- Why: Menyajikan rekomendasi strategis terstruktur untuk review pemilik sebelum otorisasi implementasi subfase berikutnya.
- Evidence: `reports/feature-update/NEXT-STEPS-PLAN.md`.

## [2026-10-26] Subfase 03c — Penerimaan manual pemilik dan penutupan Fase 03
- Type: Docs
- Scope / fase: Subfase 03c (Daftar, Rekap, dan Ekspor Siswa) dan penutupan Fase 03 (Pendataan Siswa Lengkap dan Aman).
- Files touched:
  - `reports/feature-update/PHASE-03C-MANUAL-REVIEW.md`
  - `reports/feature-update/PHASE-03C-STUDENT-DIRECTORY.md`
  - `roadmap/phases/PHASE-03-STUDENTS.md`
  - `roadmap/ROADMAP.md`
  - `reports/feature-update/HANDOFF.md`
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/PHP82-COMPATIBILITY-AUDIT.md`
  - `CHANGELOG.md`
- What changed:
  1. Mencatat konfirmasi penerimaan manual pemilik untuk seluruh checklist `PHASE-03C-MANUAL-REVIEW.md` #1–#23 (**APPROVED**). Baseline lingkungan: `933e71cbbfb8f332e307161e1a67e33479d04c4d`; baseline fitur: `e15f028`. Tanggal pencatatan WIB: 2026-10-26 (tanggal pengujian tidak dinyatakan secara terpisah oleh pemilik).
  2. Menutup scope Fase 03 yang telah diotorisasi karena seluruh deliverable wajib (03a: identitas & membership migration, 03b: CRUD & form siswa, 03c: daftar, rekap & ekspor server-side) telah diterima lengkap oleh pemilik.
  3. Menegaskan bahwa kandidat 03d (aksi penempatan rombel via UI) tetap **BELUM DIOTORISASI** dan bukan bagian dari scope yang ditutup.
  4. Mencatat batasan penerimaan: UAT sintetis berjalan di atas PHP global 8.2.35 dan Node portable 24.22.1; 2 tes integrasi MariaDB tetap NOT RUN pada sesi kompatibilitas; advisory dependency, status EOL framework Laravel 10, dan paparan historis host sebelum containment tetap OPEN; bukan security clearance, bukan izin deployment produksi, dan bukan approval modernisasi.
- Why: Mencatat approval manual pemilik secara resmi sesuai alur kerja repositori tanpa mencampuradukkan bukti otomatis agen dengan penerimaan pemilik.
- Evidence: `reports/feature-update/PHASE-03C-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-03C-STUDENT-DIRECTORY.md`.

## [2026-10-26] Persiapan dan pengujian kompatibilitas UAT sintetis khusus sekolah (PHP 8.2 & Node 24)
- Type: Security | Test | Ops | Docs
- Scope / fase: Persiapan dan pengujian kompatibilitas UAT sintetis khusus sekolah di checkout terisolasi; bukan penerimaan manual 03c dan bukan penyelesaian seluruh risiko keamanan. Baseline laporan: `27eca92`.
- Files touched:
  - `laravel-api/scripts/verify-isolation.php` (baru)
  - `laravel-api/.env.uat.example` (baru)
  - `vue-app/.env.uat.example` (baru)
  - `reports/feature-update/PHP82-COMPATIBILITY-AUDIT.md`
  - `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `CHANGELOG.md`, `roadmap/ROADMAP.md`
- What changed:
  1. Koreksi dokumentasi: mencatat status EOL keamanan Laravel 11 (berakhir Maret 2026; target utama modernisasi adalah Laravel 12); membatasi klaim reachability advisory pada bukti actual kode (bukan jaminan mutlak seluruh jalur transitif unreached); mencatat loopback sebagai isolasi jaringan, bukan mitigasi universal parser/build tool; risiko advisory, eksposur historis, dan rotasi rahasia tetap OPEN.
  2. Lingkungan checkout uji disiapkan di luar web root Apache (`%TEMP%\...\school-sec-env\staging\checkout`) dan disinkronkan ke baseline `e15f028` (commit `27eca92`). Menggunakan PHP global `C:\xampp\php82-operational\php.exe` (PHP 8.2.35) dan Node portable patched `v24.22.1` (npm 11.19.1).
  3. Mengimplementasikan script fail-closed Pre-Flight Guard (`laravel-api/scripts/verify-isolation.php`) untuk profil `testing` (SQLite :memory:) dan `uat` (allowlist eksak `school_compat_uat`, host loopback, port 3306, user non-root `school_compat_app`, penolakan variable proses OS overrides, penolakan config cache).
  4. Membuat database baru `school_compat_uat` di MariaDB port 3306 dan dedicated user `school_compat_app` dengan hak terbatas pada database tersebut (tanpa menyentuh `backdata_administrations`, DB UAT lama, atau App Gudang; kredensial disimpan privat di temp lokal).
  5. Menjalankan 42 file migrasi normal dan `UatSeeder` sintetis (password disuplai privat via `UAT_SEED_PASSWORD`).
  6. Menjalankan suite tes backend SQLite-only: **221 passed (1038 assertions)** in 178.05s, secara eksplisit mengecualikan 2 tes integrasi MariaDB (`MariaDbIntegrationTest.php` dan `MariaDbStudentIntegrationTest.php`).
  7. Menjalankan frontend unit tests: **18 passed** in 961ms via Node 24.22.1 portable; production build: **PASS** (Vite 6.3.5, 789 modules in 1m 14s); targeted lint: **0 errors**, 2574 warnings (CRLF).
  8. Memverifikasi port 18021 dan 14021 bebas, lalu menyalakan server UAT terisolasi: API port `18021` (PID parent 3812 / child 15960) dan SPA port `14021` strict port (PID 17964).
  9. Menjalankan 16 skenario smoke test terarah: CSRF 204, login operator 204, check-auth abilities, student list & recap (school_year_id=1), filter placed/unplaced, search formula, detail, edit version 1->2, stale edit 409 conflict, archive version 2->3, archived list view, restore version 3->4, export CSV, logout 204, kepsek role checks (view 200, create 403, archived 403, export 200), waka role checks (view 200, export 403). Seluruhnya PASS. UI penempatan rombel tidak diklaim tersedia.
  10. Menyediakan panduan akses lokal bagi pemilik (cara membaca kredensial privat atau reset via Tinker) dan prosedur start/stop terverifikasi.
- Why: Membuktikan kompatibilitas penuh aplikasi sekolah pada runtime PHP 8.2 global di lingkungan UAT terisolasi, menyiapkan instans review untuk checklist manual 03c, serta memperkuat guard isolasi tanpa merusak database operasional atau aplikasi lain.
- Validation (hasil aktual):
  - Pre-flight guard: PASS untuk `testing` dan `uat`.
  - Backend automated tests: 221 passed (1038 assertions) in 178.05s.
  - Frontend unit tests: 18 passed in 961ms; build: PASS; lint: 0 errors.
  - Smoke tests UAT: 16 skenario seluruhnya PASS.
  - Port 18021 dan 14021 aktif secara strict; Apache containment tetap aktif (loopback 403); MariaDB tetap aktif.
- Notes / risks: Server UAT 18021/14021 dibiarkan aktif untuk review pemilik; checklist manual 03c #1–#23 tetap PENDING; Fase 03 tetap PARTIAL; risiko advisory dependency, EOL framework, dan eksposur historis tetap OPEN.
- Evidence: `reports/feature-update/PHP82-COMPATIBILITY-AUDIT.md`.

## [2026-10-23] Penghentian server UAT sekolah 127.0.0.1:8001
- Type: Chore | Ops
- Scope / fase: Hanya penghentian server UAT sekolah di 8001 dan pencatatan hasil; tidak ada DB, test aplikasi, dependency audit, cutover global, atau fitur.
- Files touched:
  - `reports/feature-update/UAT-SERVER-STOP-8001.md` (baru)
  - `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `CHANGELOG.md`, `roadmap/ROADMAP.md`
- What changed:
  1. Memverifikasi listener 8001 milik PID 20444, executable `C:\xampp\php\php.exe`, command `php -S 127.0.0.1:8001 ...server.php`, parent cmd 17428, parent Artisan 13828 (`artisan serve --port=8001 --env=uat`), cwd checkout sekolah, dan `.env.uat`.
  2. Menghentikan parent Artisan lebih dulu, lalu cmd/php child pada 2026-10-23 15:07:31 +07:00; tidak ada terminal pemilik yang tersedia.
  3. Memverifikasi port 8001 tanpa listener, PID terkait hilang, dan tidak ada respawn setelah 10+ detik.
  4. Mencatat insiden di luar target: setelah stop, seluruh `php.exe` hilang dan listener 8000 (PID 2384 sebelum stop) juga hilang; tidak ada restart.
- Why: mengoordinasikan cutover PHP global dengan menghentikan server UAT lama tanpa menyentuh data/session atau runtime lain.
- Validation (hasil aktual):
  - Listener 8001 sebelum: 1 (PID 20444); sesudah: 0.
  - PID 13828/17428/20444: tidak ada; tidak ada respawn.
  - Apache 80/443 tetap aktif (PID 9360/15464); MariaDB 3306 tetap aktif (PID 1840).
  - Tidak ada request endpoint Laravel/DB; `.env`, `.env.uat`, session, log, dan artefak UAT tidak diubah/dihapus.
- Notes / risks: listener 8000 hilang sebagai dampak collateral di luar target; penyebab pasti belum terbukti (kemungkinan shared console/job cleanup). Server 8000 tidak dijalankan ulang. 03c #1-#23 tetap PENDING; Fase 03 tetap PARTIAL.
- Evidence: `reports/feature-update/UAT-SERVER-STOP-8001.md`.

## [2026-10-22] Containment akses Apache untuk direktori proyek sekolah
- Type: Security | Chore
- Scope / fase: Containment host Apache; **tidak** melanjutkan DB, pengujian aplikasi, remediasi dependency, atau fitur. Baseline laporan `2e2e557`.
- Files touched:
  - `C:/xampp/apache/conf/extra/httpd-school-containment.conf` (baru, di luar repo)
  - `C:/xampp/apache/conf/httpd.conf` (satu `Include`)
  - `reports/feature-update/APACHE-SCHOOL-CONTAINMENT.md` (baru)
  - `reports/feature-update/ENVIRONMENT-SCHOOL-COMPAT.md` (addendum)
  - `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `CHANGELOG.md`, `roadmap/ROADMAP.md`
- What changed:
  1. Menambahkan aturan server-level scoped ke direktori fisik sekolah: seluruh akses ditolak, directory listing off, `AllowOverride None` agar `.htaccess` tidak melemahkan aturan.
  2. Menambahkan satu `Include` di akhir `httpd.conf`; tidak mengubah App Gudang, SSL/XAMPP, vhost, PHP global, firewall, atau resource aplikasi lain.
  3. Mencadangkan `httpd.conf` ke lokasi privat di luar web root; mencatat hash sebelum/sesudah.
  4. `httpd -t` PASS. `httpd -k restart` tidak tersedia karena Apache berjalan sebagai console application tanpa service; Apache dihentikan/dimulai ulang pada PID yang terbukti dengan downtime ~3-5 detik.
  5. Memverifikasi status 403 loopback HTTP/HTTPS untuk root sekolah, `.env`, `.git/HEAD`, log, `public/`, junction storage, dan `backdata-administration`.
- Why: menutup akses HTTP langsung ke repository sekolah dan berkas sensitif tanpa mengubah host-wide policy atau aplikasi lain.
- Validation (hasil aktual):
  - `httpd -t -f C:\xampp\apache\conf\httpd.conf` -> `Syntax OK`, exit 0; include terdaftar di `httpd.conf:573`.
  - `curl -I` loopback: seluruh target sekolah -> **403**; HTTPS 443 juga 403; `/` -> 302; `/appgudang/` -> 403.
  - Hash `httpd-appgudang-access.conf`, `httpd-ssl.conf`, `httpd-xampp.conf`, dan seluruh `conf/extra` lain tidak berubah.
  - Listener 80/443, 3306, 8000, 8001 tetap aktif; API sekolah 8000/8001 tidak dihentikan.
- Notes / risks: containment tidak membuktikan tidak pernah ada akses sebelumnya; rotasi kredensial dan audit log historis belum dilakukan; `APP_KEY` tidak diubah. LAN/internet **NOT TESTED**; loopback 403 bukan bukti LAN. Penerimaan manual 03c #1-#23 tetap PENDING; Fase 03 tetap PARTIAL.
- Evidence: `reports/feature-update/APACHE-SCHOOL-CONTAINMENT.md`.

## [2026-10-22] Preflight keamanan lingkungan dan runtime uji sekolah terisolasi
- Type: Security | Docs | Chore
- Scope / fase: Lingkungan kompatibilitas keamanan; **tidak** melanjutkan fitur atau menutup Fase 03. Checkpoint kode `224529f`.
- Files touched:
  - `reports/feature-update/ENVIRONMENT-SCHOOL-COMPAT.md` (baru)
  - `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `CHANGELOG.md`, `roadmap/ROADMAP.md`
- What changed:
  1. Preflight read-only Windows: inventaris PHP 8.1.25 CLI/web, Apache 2.4.58 + mod_php, Node 24.18.0/npm 11.4.2, proses/listener, DocumentRoot/Alias/Directory/AllowOverride/Indexes, firewall.
  2. Diverifikasi exposure repository sekolah lewat parent `C:/xampp/htdocs`: `laravel-api/.env`, `.git/HEAD`, dan `laravel-api/storage/logs/laravel.log` merespons HTTP 200. Tidak ada isi rahasia yang dibaca/dicetak; tidak ada perubahan Apache.
  3. Menyiapkan runtime terisolasi di luar web root: PHP 8.3.36 NTS x64 dan Node 24.22.1 portable, checksum resmi cocok; checkout clone `224529f` tanpa `.env`; storage/cache/session/log, cache Composer/npm, dan profil browser terpisah; port 18021/14021 kosong.
  4. Menjalankan instalasi dari lock (`composer install --no-scripts --no-plugins`, `npm ci`), `composer validate`/`check-platform-reqs`, frontend test/build, dan ESLint non-mutasi. DB, migrasi, seeder, backend test, server, browser, dan smoke **NOT RUN**.
  5. Menyajikan konfigurasi target sintetis + daftar perintah untuk review, termasuk peringatan cookie lintas port dan opsi DB terpisah.
- Why: menyiapkan lingkungan uji kompatibilitas keamanan tanpa mengubah host bersama, sekaligus mendokumentasikan risiko exposure dan dependency sebelum ada otorisasi DB/perbaikan host.
- Validation (hasil aktual):
  - PHP 8.3.36 `-v`/`--ini`/`-m` PASS; checksum PHP dan Node cocok dengan sumber resmi.
  - Composer install 125 paket PASS; `composer validate --strict` PASS; `check-platform-reqs` seluruhnya success; lock tidak berubah.
  - `node --test` 18 passed/0 failed; `npm run build` PASS (789 modules, 27.85s); ESLint `Student.vue`+`main.js` **0 errors / 2574 warnings** (CRLF), bukan PASS bersih.
  - `composer audit --locked` 48 advisory/14 paket; `npm audit --omit=dev` 20 vulnerability (1 critical/16 high/3 moderate).
- Notes / risks: exposure `.env`/`.git`/log adalah risiko host bersama yang **belum** diselesaikan; versi PHP web 8.1.25 mendahului perbaikan 8.3.36; dependency lock belum di-update. Penerimaan manual 03c #1-#23 tetap PENDING; Fase 03 tetap PARTIAL.
- Evidence: `reports/feature-update/ENVIRONMENT-SCHOOL-COMPAT.md`.

## [2026-10-22] Subfase 03c — Pemeriksaan akhir: ESLint 0 error dan guard placement ekspor
- Type: Fix
- Scope / fase: Subfase 03c (pemeriksaan akhir; baseline `472ce9b`). Tidak melanjutkan fase berikutnya.
- Files touched:
  - `vue-app/src/views/pages/public/Student.vue`
  - `vue-app/src/views/pages/public/studentExportParams.mjs` (baru)
  - `vue-app/tests/student-export-params.test.mjs` (baru)
  - `reports/feature-update/PHASE-03C-STUDENT-DIRECTORY.md`, `PHASE-03C-MANUAL-REVIEW.md`, `HANDOFF.md`, `CHANGELOG.md`
- What changed:
  1. Menghapus 20 error `no-unused-vars` secara terarah (import/ref/fungsi mati: `reactive`, `watch`, `useRoute`, `RouterLink`, `useLayout`, `useConfirm`/`confirm`, `useRouter`/`router`, `CustomerService`, `all_students`, `saveCompany`, `titleWarning`, `fieldWarning`, `titleWaiting`, `fieldWaiting`, `visibleStaticBackdropWarnings/Dangers/Waitings/Successs`, `saveValidContinue`, `validContinue`, `viewStudentProfile`). Tanpa `eslint-disable`, tanpa menonaktifkan aturan, tanpa auto-format massal.
  2. Ekstraksi `buildStudentExportParams()` yang **selalu** mengirim `placement` (fallback `all`), sehingga CSV tidak berbeda dari daftar All hanya karena parameter absen (backend default `placed`).
  3. Menambah 3 test unit parameter ekspor; browser smoke memverifikasi request ekspor `placement=all`/`placement=placed` dan baris CSV sesuai total daftar/rekap.
- Why: menyelesaikan temuan lint final 03c dan menutup risiko ekspor All tertukar dengan default `placed`.
- Validation (hasil aktual):
  - `npx eslint` (blob LF) `Student.vue` → **0 errors / 217 warnings** (baseline 20/218); helper+test 0/0.
  - `node --test` (vue-app) → **18 passed / 0 fail**.
  - `npm run build` → **PASS**.
  - Browser smoke (UAT, operator): All → `placement=all`, CSV 27 = Total 27; Placed → `placement=placed`, CSV 12 = recap 12, semua berisi rombel; Unplaced recap 15, Rombel disabled → **PASS**.
  - Backend tidak berubah → suite backend/MariaDB tidak diulang.
- Notes / risks: 217 warning ESLint tersisa bersifat kosmetik/pre-existing (bukan PASS bersih). Kredensial akun UAT sintetis berubah; kata sandi tidak dicetak (pemilik dapat menetapkan ulang lewat `UAT_SEED_PASSWORD`/Tinker `--env=uat`).
- Evidence: `reports/feature-update/PHASE-03C-STUDENT-DIRECTORY.md` §4–§5, `PHASE-03C-MANUAL-REVIEW.md` #15/#2.

## [2026-10-21] Subfase 03c — Implementasi daftar, rekap, dan ekspor siswa server-side
- Type: Feature
- Scope / fase: Subfase 03c (Daftar dan Rekap Siswa), DEC-22.
- Files touched:
  - `laravel-api/app/Support/StudentDirectory.php` (baru)
  - `laravel-api/app/Http/Controllers/StudentController.php`
  - `laravel-api/app/Http/Controllers/StudentExportController.php`
  - `laravel-api/tests/Feature/Student/StudentDirectoryTest.php` (baru)
  - `vue-app/src/views/pages/public/Student.vue`
- What changed:
  1. `GET /api/students` server-side: pagination 10/20/50/100 (default 20), pencarian nama/NIS/NISN, sort allowlist (`id|name|nis|nisn`) dengan pembeda stabil `id`, filter `placement` All/Placed/Unplaced + `rombel` + `school_year_id`, respons `data`/`meta`/`recap`. Validasi parameter English 422; `unplaced`+`rombel` ditolak 422.
  2. Definisi bersama `StudentDirectory` (dipakai daftar & ekspor): keanggotaan aktif tahun terpilih, identitas `student_id` primer + fallback legacy `student_nik` (aman 03a), anti double-count via EXISTS, satu rombel aktif per siswa.
  3. Atribut daftar minimum (`id, version, name, nis, nisn, gender, rombel, placement`); tanpa NIK/tanggal lahir/alamat/telepon/medis/penghasilan/alasan arsip. Rekap dihitung dari seluruh hasil filter (bukan halaman).
  4. Ekspor memakai filter yang sama; `placement` default `placed`; `unplaced` mengisi kolom rombel kosong; batas baris tetap 422; leading zero & netralisasi formula tetap.
  5. `only_archived=1` kini butuh `students.manage` (menutup kebocoran NIK/alasan arsip ke akun view-only); `with_archived` dihapus.
  6. `Student.vue`: DataTable lazy server-driven, filter/placement/rombel, rekap server, guard respons usang `requestId`, loading/empty/error + Retry, pemulihan halaman terakhir kosong setelah archive, Export mengikuti filter; `tabItems` dijadikan computed murni.
- Why: daftar siswa client-side lama tidak menemukan siswa tanpa rombel, tidak terpaginasi server, dan menampilkan field sensitif; DEC-22 menetapkan definisi All/Placed/Unplaced, rekap siswa unik, dan ekspor terfilter.
- Validation (hasil aktual):
  - `php artisan test` → **229 passed (1076 assertions)**; `tests/Feature/Student` + `StudentExportTest` → **88 passed (394 assertions)**.
  - `./vendor/bin/pint --test` (4 berkas PHP 03c) → **PASS**.
  - `node --test` (vue-app) → **15 passed**; `npm run build` → **PASS** (788 modules).
  - `npx eslint` (blob LF) `Student.vue` → **20 errors/218 warnings** (dari baseline 22/228); tidak ada error baru.
  - Browser UAT (Playwright, `school_access_uat`) → **PASS** (paginasi, sort, pencarian, All/Placed/Unplaced, create tanpa rombel + refresh, detail/edit, archive/restore, pemulihan halaman, rekap lintas halaman, ekspor terfilter, error/retry, empty, guard respons usang, kontrol izin).
- Notes / risks: ESLint repo bukan PASS (20 error pre-existing tersisa); `status`/`entry` ekspor tidak lagi difilter; pengujian 03c berjalan pada SQLite in-memory (MariaDB konkurensi 03b tidak dijalankan ulang); kredensial akun UAT sintetis direset pada DB UAT disposable; tidak ada deployment.
- Evidence: `reports/feature-update/PHASE-03C-STUDENT-DIRECTORY.md`, `PHASE-03C-MANUAL-REVIEW.md` (manual acceptance pending).

## [2026-10-21] Subfase 03c — Keputusan pemilik (DEC-22)
- Type: Docs
- Scope / fase: Subfase 03c; keputusan DEC-22.
- Files touched: `docs/DECISIONS.md`.
- What changed: Mencatat 7 keputusan pemilik (batas siswa tak terarsip vs keanggotaan aktif; filter All/Placed/Unplaced; paginasi 10/20/50/100 default 20; ekspor wajib tahun + ikut filter + batas 422; penempatan rombel di luar 03c; arsip hanya dialog Archived; atribut daftar minimum).
- Why: DEC-22 menghambat implementasi 03c; dicatat tersendiri sebelum implementasi.
- Validation: `git diff --cached --check` exit 0; commit `4fa20c6` di-push ke `origin/main`.
- Notes / risks: keputusan ini mempersempit scope 03c (tanpa aksi penempatan rombel).
- Evidence: `docs/DECISIONS.md` (DEC-22), commit `4fa20c6`.

## [2026-10-21] Subfase 03c — Docs: audit source dan rencana daftar/rekap siswa
- Type: Docs
- Scope / fase: Subfase 03c (Daftar dan Rekap Siswa).
- Files touched:
  - `reports/feature-update/PHASE-03C-PLAN.md` (baru)
  - `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `CHANGELOG.md`
- What changed:
  1. Audit source aktual: `StudentController::index` (belum terpaginasi), `StudentRombelController::show` (daftar per tahun, siswa tanpa rombel tidak muncul), `StudentExportController` (batas baris 422 tanpa pemotongan), dan DataTable client-side `Student.vue`.
  2. Rencana teknis 03c: server pagination/pencarian/sort/filter, definisi tegas siswa aktif/terarsip/unplaced/keanggotaan aktif-nonaktif, rekap anti double-count (siswa unik), ekspor mengikuti filter + `students.export` + batas 422, atribut minimum, kompatibilitas CRUD/archive/restore + `version` 03b, UI English + loading/empty/error + guard respons usang, matriks tes backend & browser UAT sintetis.
  3. Rencana lint terarah dengan pembanding blob commit (bukan PASS otomatis; tanpa auto-format massal / menonaktifkan aturan).
  4. Daftar keputusan pemilik yang diperlukan sebelum implementasi (definisi aktif/rekap, mode unplaced, paginasi, ekspor, penempatan rombel, visibilitas arsip, atribut minimum).
- Why: mematuhi tata kelola repo — audit source dan rencana sebelum implementasi subfase berikutnya.
- Validation: `git diff --cached --check` = exit 0. Dokumentasi saja; tidak mengulang test/build.
- Notes / risks: Implementasi 03c belum diotorisasi dan **tidak** dimulai. Fase 03 tetap PARTIAL. Tidak ada perubahan skema.
- Evidence: `reports/feature-update/PHASE-03C-PLAN.md`.

## [2026-10-21] Subfase 03b — Docs: penerimaan manual pemilik (checklist #1–#13 APPROVED)
- Type: Docs
- Scope / fase: Subfase 03b (CRUD dan Form Siswa).
- Files touched:
  - `reports/feature-update/PHASE-03B-STUDENT-CRUD.md`
  - `reports/feature-update/PHASE-03B-MANUAL-REVIEW.md`
  - `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `CHANGELOG.md`
- What changed:
  1. Pemilik menyatakan pengujian manual #1–#13 **berhasil dan APPROVED** pada 21 Oktober 2026 WIB mengacu baseline `cbd25bd`; status Subfase 03b menjadi **DITERIMA PEMILIK**.
  2. Ditambahkan perbandingan lint terarah baseline (`e77f335`) vs perubahan (`cbd25bd`) dengan konfigurasi sama: `Student.vue` 22→22 errors (172→228 warnings), `main.js` 0→0 errors (66→69 warnings) — jumlah error tidak bertambah.
  3. Klasifikasi bukti dipertahankan: penerimaan manual pemilik terpisah dari bukti otomatis/browser agen; ESLint terakhir 22 errors/2609 warnings (**bukan PASS**); agen tidak menjalankan deploy; penerimaan bukan izin deployment/akses data operasional.
  4. Penerimaan 03a dipertahankan; Fase 03 tetap **PARTIAL** karena 03c belum selesai.
- Why: mencatat penerimaan manual pemilik pada commit dokumentasi tersendiri sesuai aturan repo, tanpa menyentuh commit implementasi.
- Validation: `git diff --cached --check` = exit 0. Dokumentasi saja; tidak mengulang test/build.
- Notes / risks: Penerimaan bukan otorisasi merge/force-push/deploy/scheduler. Rencana 03c diajukan pada commit tersendiri dan menunggu review pemilik.
- Evidence: `reports/feature-update/PHASE-03B-STUDENT-CRUD.md` §6, `reports/feature-update/PHASE-03B-MANUAL-REVIEW.md` §5.

## [2026-10-21] Subfase 03b — Feature: UI CRUD siswa, optimistic locking, arsip administratif, dan bukti konkurensi
- Type: Feature
- Scope / fase: Subfase 03b (CRUD dan Form Siswa).
- Files touched:
  - `vue-app/src/views/pages/public/Student.vue`
  - `vue-app/src/main.js`
  - `laravel-api/tests/Feature/Student/MariaDbStudentIntegrationTest.php`
  - `reports/feature-update/PHASE-03B-STUDENT-CRUD.md` (baru)
  - `reports/feature-update/PHASE-03B-MANUAL-REVIEW.md`
  - `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `roadmap/ROADMAP.md`, `docs/DATABASE.md`, `docs/API-CONTRACTS.md`, `CHANGELOG.md`
- What changed:
  1. UI `Student.vue`: tombol New Student & Archived (`students.manage`), kolom Action (View/Edit/Archive), dialog Add/Edit (section English), dialog Detail, dialog Archive dengan alasan wajib, dialog Archived + Restore, error 422 inline per field, dialog 409 (Data Conflict Detected) dengan input dipertahankan + Reload Latest, `isSubmitting` anti double-submit, dan tombol View untuk `students.view`.
  2. `main.js`: registrasi directive PrimeVue `v-tooltip`.
  3. 2 integration test MariaDB disposable: update dengan expected version sama (satu menang/409/versi naik sekali/audit konsisten) dan arsip vs aktivasi rombel (siswa terarsip berakhir 0 keanggotaan aktif).
  4. Sinkronisasi dokumentasi status 03b = IN PROGRESS, penerimaan manual PENDING.
- Why: menuntaskan scope 03b yang diotorisasi (backend sudah di `e77f335`) dengan deliverable UI nyata, bukti konkurensi khusus 03b, dan verifikasi browser tanpa mengklaim 03b selesai sebelum persetujuan pemilik.
- Validation:
  - `php artisan test tests/Feature/Student` → 55 passed (228 assertions).
  - `php artisan test` → 209 passed (946 assertions).
  - `./vendor/bin/pint --test tests/Feature/Student/MariaDbStudentIntegrationTest.php` → PASS.
  - `node --test` (vue-app) → 15 passed.
  - `npm run build` → PASS.
  - `npx eslint src/views/pages/public/Student.vue src/main.js` → 22 errors (seluruhnya baseline) + 2609 warnings.
  - Browser UAT Playwright MCP: create/422/detail/edit/409/archive/daftar arsip/restore/kontrol izin → PASS (agent evidence).
- Notes / risks: Status Subfase 03b tetap **IN PROGRESS**; penerimaan manual pemilik PENDING. UI tidak membuat keanggotaan rombel; siswa baru belum tampil di daftar per tahun ajaran sampai ditempatkan. Pagination server & chart tetap scope 03c.
- Evidence: `reports/feature-update/PHASE-03B-STUDENT-CRUD.md`, `reports/feature-update/PHASE-03B-MANUAL-REVIEW.md`.

## [2026-10-21] Subfase 03b — Docs: perencanaan teknis CRUD siswa, optimistic locking, dan validasi form
- Type: Docs
- Scope / fase: Subfase 03b (CRUD dan Form Siswa).
- Files touched:
  - `reports/feature-update/PHASE-03B-PLAN.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `CHANGELOG.md`
- What changed:
  1. Penyusunan dokumen rencana teknis terperinci Subfase 03b (`reports/feature-update/PHASE-03B-PLAN.md`):
     - Spesifikasi endpoint RESTful: `POST /api/students`, `GET /api/students/{id}`, `PUT /api/students/{id}`, `DELETE /api/students/{id}`, dan `POST /api/students/{id}/restore` dilindungi kemampuan `students.manage` dan `students.view`.
     - Validasi server lengkap (`StoreStudentRequest` / `UpdateStudentRequest`): normalisasi string/null, homonim sah (nama boleh sama), NIK nullable 16 digit angka, NIS unik lingkup single-school (DEC-01 & DEC-20), NISN nullable 10 digit, serta validasi FK ke seluruh tabel referensi.
     - Kontrol konkurensi optimistik atomik via kolom `version`: deteksi konflik versi pada update HTTP 409 Conflict tanpa menimpa data server dan aman terhadap request bersamaan.
     - Transaksi atomik soft delete dan penonaktifan keanggotaan rombel aktif pada tahun ajaran berjalan tanpa menghapus riwayat mutasi kelas siswa.
     - Transaksi restore dengan pemeriksaan potensi bentrok keunikan identitas dan kepatuhan aturan bahwa rombel lama tidak otomatis diaktifkan kembali.
     - Penjelasan semantik keunikan aktual: indeks unik tabel `students` pada MariaDB/InnoDB berlaku menyeluruh (unconditional unique), menjaga nomor identitas siswa terarsip tidak dipakai sembarangan tanpa melemahkan constraint database.
     - Audit server terarah via trait `Loggable` dengan allowlist ketat atribut operasional dan eksklusi mutlak data medis (`history_illness`), nomor kontak, dan data penghasilan orang tua/wali (NFR-01, DEC-20).
     - Antarmuka pengguna (UI English): dialog form modular di `Student.vue`, penanganan inline field error HTTP 422, penanganan dialog konflik HTTP 409, loading state, dan pencegahan double-submit.
     - Matriks pengujian backend komprehensif dan UAT sintetis.
     - Identifikasi satu pertanyaan keputusan operasional pemilik terkait penetapan status rombel spesifik saat soft delete siswa.
- Why: mematuhi tata kelola repository dan instruksi pemilik untuk merencanakan Subfase 03b secara konkret sebelum implementasi.
- Notes / risks:
  - Implementasi Subfase 03b belum diotorisasi.
- Evidence: `reports/feature-update/PHASE-03B-PLAN.md`.

## [2026-10-21] Subfase 03a — Docs: penerimaan manual pemilik atas identitas siswa dan migrasi keanggotaan rombel
- Type: Docs
- Scope / fase: Subfase 03a (Identitas Siswa dan Membership Migration).
- Files touched:
  - `reports/feature-update/PHASE-03A-MANUAL-REVIEW.md`
  - `reports/feature-update/PHASE-03A-STUDENT-IDENTITY.md`
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `roadmap/phases/PHASE-03-STUDENTS.md`
  - `CHANGELOG.md`
- What changed:
  1. Pencatatan resmi penerimaan manual pemilik atas Subfase 03a: seluruh checklist UAT #1–#11 di `PHASE-03A-MANUAL-REVIEW.md` dinyatakan **APPROVED** pada 21 Oktober 2026 WIB mengacu baseline `37f7d79`.
  2. Status Subfase 03a resmi ditetapkan **DITERIMA PEMILIK**.
  3. Pembuktian serialisasi konkurensi multi-transaksi MariaDB diakui berdasarkan bukti automated integration test agen (`MariaDbStudentIntegrationTest`), terpisah dari observasi checklist manual pemilik.
  4. Status Fase 03 tetap PARTIAL / IN PROGRESS; Subfase 03b dan 03c belum diimplementasikan/diterima.
  5. Batasan cakupan tetap dipertahankan: pengujian memakai fixture sintetis dan DB UAT terisolasi, `student_nik` legacy dipertahankan, baris backfill unresolved tidak dimutasi otomatis, CRUD siswa dan optimistic locking tetap berada pada lingkup Subfase 03b, dan penerimaan ini bukan izin migrasi database operasional atau deployment produksi.
- Why: memenuhi tata kelola repository dan aturan AGENTS.md sebelum melangkah ke perencanaan Subfase 03b.
- Validation: verifikasi keselarasan silang seluruh dokumen dokumentasi.
- Notes / risks:
  - Implementasi Subfase 03b belum diotorisasi.
- Evidence: `reports/feature-update/PHASE-03A-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-03A-STUDENT-IDENTITY.md`.

## [2026-10-21] Subfase 03a — Docs: penyempurnaan panduan manual review 03a berbasis transaksi aman dan bukti otomatis
- Type: Docs
- Scope / fase: Subfase 03a (Identitas Siswa dan Membership Migration).
- Files touched:
  - `reports/feature-update/PHASE-03A-MANUAL-REVIEW.md`
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `CHANGELOG.md`
- What changed:
  1. Penyempurnaan checklist review manual `PHASE-03A-MANUAL-REVIEW.md`:
     - Menghapus ketergantungan pada `Student::first()` dan ID hardcoded; menggantikannya dengan fixture sintetis berpenanda unik (`MANUAL-UAT-`).
     - Mengubah seluruh skenario mutasi manual ke pola transaksi database (`DB::beginTransaction()` ... `DB::rollBack()`) dalam blok `try ... finally` untuk menjamin database UAT `school_access_uat` tetap bersih dan riwayat/audit existing tidak terhapus.
     - Mewajibkan preflight check koneksi database aktif (`school_access_uat`) sebelum eksekusi Tinker `--env=uat`.
     - Menyusun 11 butir checklist terstruktur (#1–#11) dengan pemetaan eksplisit.
     - Menyajikan bukti pengujian otomatis integrasi MariaDB (`MariaDbStudentIntegrationTest`) untuk konkurensi multi-transaksi, sehingga pemilik tidak dibebani uji konkurensi manual multi-terminal.
     - Memperjelas batasan: validasi NIK pada `StudentBackfillService` khusus pemadanan legacy, `app:bootstrap-admin` untuk akun admin, dan validasi form CRUD siswa tetap berada pada lingkup Subfase 03b.
- Why: mematuhi instruksi penajaman panduan review manual UAT 03a agar aman, dapat diulang tanpa polusi data, dan tidak ambigu bagi pemilik.
- Validation: seluruh cuplikan kode manual diverifikasi berjalan dan ter-rollback secara bersih pada lingkungan UAT.
- Notes / risks:
  - Status review manual pemilik tetap PENDING.
- Evidence: `reports/feature-update/PHASE-03A-MANUAL-REVIEW.md`.

## [2026-10-21] Subfase 03a — Feature & Schema: migrasi additive student_id, backfill aman, umur dinamis, dan penegakan keanggotaan rombel tunggal
- Type: Schema Change | Feature | Security
- Scope / fase: Subfase 03a (Identitas Siswa dan Membership Migration).
- Files touched:
  - `laravel-api/database/migrations/2029_07_23_000001_add_student_id_to_student_rombels_table.php` (baru)
  - `laravel-api/app/Services/StudentBackfillService.php` (baru)
  - `laravel-api/app/Console/Commands/StudentBackfillCommand.php` (baru)
  - `laravel-api/app/Models/Student.php`
  - `laravel-api/app/Models/StudentRombel.php`
  - `laravel-api/app/Http/Controllers/StudentRombelController.php`
  - `laravel-api/app/Http/Controllers/StudentExportController.php`
  - `laravel-api/database/seeders/SyntheticDataSeeder.php`
  - `laravel-api/database/seeders/UatSeeder.php`
  - `laravel-api/tests/Support/CreatesAccessFixtures.php`
  - `laravel-api/tests/Feature/Student/StudentMigrationAndBackfillTest.php` (baru)
  - `laravel-api/tests/Feature/Student/StudentAgeAndIdentityTest.php` (baru)
  - `laravel-api/tests/Feature/Student/StudentRombelConstraintTest.php` (baru)
  - `laravel-api/tests/Feature/Student/MariaDbStudentIntegrationTest.php` (baru)
  - `docs/DATABASE.md`
  - `docs/API-CONTRACTS.md`
  - `reports/feature-update/PHASE-03A-STUDENT-IDENTITY.md` (baru)
  - `reports/feature-update/PHASE-03A-MANUAL-REVIEW.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `roadmap/phases/PHASE-03-STUDENTS.md`
  - `CHANGELOG.md`
- What changed:
  1. Additive migration: menambahkan kolom `student_id` nullable pada `student_rombels` (FK restrictOnDelete, cascadeOnUpdate) dan mengubah `student_nik` menjadi nullable, sehingga siswa tanpa NIK dapat memiliki keanggotaan rombel. Kolom legacy `student_nik` tetap dipertahankan.
  2. Backfill aman & idempotent (`StudentBackfillService` dan `app:student-backfill`): hanya mengisi baris yang `student_id`-nya null; hanya memadankan bila NIK valid 16 digit dan unik cocok dengan tepat satu siswa di tabel `students`. NIK kosong/invalid/unmatched/ambigu dibiarkan unresolved tanpa mutasi destruktif. Pelaporan bebas PII.
  3. Penegakan 1 rombel aktif per siswa per tahun: ditegakkan pada level database via native BEFORE INSERT dan BEFORE UPDATE trigger pada MariaDB (SQLSTATE 45000) dan SQLite (RAISE(ABORT)) serta Eloquent saving event. Mempertahankan riwayat mutasi kelas (keanggotaan non-aktif/exit date).
  4. Perhitungan umur dinamis: accessor `age` dan `calculated_age` pada model `Student` menghitung umur runtime dari `birthdate` berbasis `Asia/Jakarta`. Tanggal kosong, tidak valid, atau masa depan mengembalikan `null`.
  5. Penyelarasan controller: `StudentExportController` dan `StudentRombelController` mengadopsi relasi ganda `student` dan `studentNik`.
  6. Audit dan fillable: `StudentRombel` mengaktifkan trait `Loggable` dengan allowlist audit; `Student` menambahkan `'check_bpms'` ke `$fillable` dan allowlist audit mengecualikan data riwayat medis dan penghasilan orang tua.
- Why: memenuhi instruksi pemilik dan keputusan DEC-20 untuk menyelesaikan cacat struktural relasi FK NIK siswa dan integritas keanggotaan rombel.
- Validation:
  - Backend: 29 test baru Subfase 03a PASS (98 assertions); 41 test regresi PASS; total suite PASS.
  - Style check: `./vendor/bin/pint --test` (15 file) → PASS (100% clean).
  - Frontend: `node --test` (15 passed), `npm run build` PASS (0 errors).
- Notes / risks:
  - Lingkup sekolah untuk NIS saat ini single-school (DEC-01), tidak ada kolom `school_id` pada `students`.
  - Review manual pemilik berstatus PENDING di `reports/feature-update/PHASE-03A-MANUAL-REVIEW.md`.
  - Implementasi CRUD siswa tetap di Subfase 03b.
- Evidence: `reports/feature-update/PHASE-03A-STUDENT-IDENTITY.md`, `reports/feature-update/PHASE-03A-MANUAL-REVIEW.md`.

## [2026-10-20] Keputusan — Docs: pencatatan keputusan pemilik atas identitas siswa, keanggotaan rombel, dan audit (DEC-20)
- Type: Docs
- Scope / fase: Fase 03 (Pendataan Siswa Lengkap dan Aman).
- Files touched:
  - `docs/DECISIONS.md`
- What changed:
  1. Pencatatan resmi keputusan pemilik DEC-20 (menuntaskan usulan DEC-05):
     - NIK opsional; bila diisi wajib string tepat 16 digit angka dan unik (mengabaikan soft delete); leading zero dipertahankan; input kosong dinormalisasi ke null.
     - NIS unik dalam lingkup sekolah.
     - Maksimal satu keanggotaan rombel aktif per siswa per tahun ajaran; riwayat mutasi kelas tetap disimpan.
     - Soft delete siswa menonaktifkan keanggotaan rombel aktif tanpa menghapus riwayat; restore memeriksa potensi konflik identitas dan tidak otomatis mengaktifkan rombel lama.
     - Audit pendataan mencatat perubahan identitas/akademik seperlunya; mengecualikan data medis dan penghasilan orang tua; meminimalkan penyalinan PII.
- Why: dasar hukum dan teknis resmi untuk memulai implementasi Subfase 03a.
- Evidence: `docs/DECISIONS.md`.

## [2026-10-20] Fase 03 — Docs: perencanaan teknis pendataan siswa lengkap dan aman
- Type: Docs
- Scope / fase: Fase 03 (Pendataan Siswa Lengkap dan Aman).
- Files touched:
  - `reports/feature-update/PHASE-03-PLAN.md` (baru)
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `CHANGELOG.md`
- What changed:
  1. Penyusunan rencana kerja teknis komprehensif Fase 03 (`reports/feature-update/PHASE-03-PLAN.md`):
     - Baseline skema dan analisis cacat FK `student_rombels.student_nik` (masalah siswa tanpa NIK dan pemutusan relasi saat perbaikan NIK).
     - Rencana Subfase 03a: migrasi additive penambahan `student_id` pada `student_rombels`, backfill otomatis, penanganan orphan, preservasi `student_nik` untuk kompatibilitas, virtual calculated age dari birthdate, dan pembaruan model fillable.
     - Rencana Subfase 03b: implementasi CRUD siswa (Store, Show, Update, Soft Delete/Arsip, Restore), validasi FormRequest server, validasi homonim sah (nama boleh sama), validasi nomor identitas string, optimistic concurrency control (version 409 conflict), otorisasi backend `students.manage` vs `students.view`, dan audit server dengan eksklusi data riwayat medis/penghasilan orang tua.
     - Rencana Subfase 03c: paginasi server `GET /api/students`, pencarian dan filter rombel/tahun, penanganan empty state tahun ajaran tanpa data, rekap chart dinamis, dan integrasi ekspor CSV.
     - Identifikasi 4 butir keputusan yang diperlukan dari pemilik terkait DEC-05 (kebijakan NIS vs NIK, aturan keanggotaan 1 rombel/periode, soft delete vs restore, dan eksklusi audit data sensitif).
     - Strategi pengujian terisolasi (SQLite `:memory:`, UAT sintetis, zero access to production DB).
- Why: mematuhi alur tata kelola repository dan AGENTS.md untuk merencanakan fase baru secara matang sebelum meminta otorisasi implementasi dari pemilik.
- Validation: verifikasi keselarasan silang dokumen teknis, roadmap, dan schema database.
- Notes / risks:
  - Implementasi Fase 03 BELUM DIOTORISASI dan belum dimulai.
  - Menunggu review rencana dan jawaban keputusan dari pemilik.
- Evidence: `reports/feature-update/PHASE-03-PLAN.md`.

## [2026-10-20] Subfase 02c & Fase 02 — Docs: penerimaan manual pemilik atas jadwal/monitoring backup dan penutupan resmi Fase 02
- Type: Docs
- Scope / fase: Subfase 02c (Jadwal dan Monitoring Backup) & Penutupan Fase 02 (Audit & Backup).
- Files touched:
  - `reports/feature-update/PHASE-02C-MANUAL-REVIEW.md`
  - `reports/feature-update/PHASE-02C-MONITORING-SCHEDULE.md`
  - `reports/feature-update/INDEX.md`
  - `reports/feature-update/HANDOFF.md`
  - `roadmap/ROADMAP.md`
  - `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`
  - `CHANGELOG.md`
- What changed:
  1. Pencatatan resmi penerimaan manual pemilik atas Subfase 02c: seluruh checklist UAT #1–#10 di `PHASE-02C-MANUAL-REVIEW.md` dinyatakan **APPROVED** pada 20 Oktober 2026 WIB mengacu baseline `94d29f4` (butir #10 diterima atas bukti automated `BackupMonitoringTest` waktu virtual).
  2. Status Subfase 02c resmi ditetapkan **DITERIMA PEMILIK**.
  3. Penutupan resmi **Fase 02 — Audit terpercaya dan backup yang dapat dipulihkan**: seluruh subfase (02a, 02b, dan 02c) telah diterima lengkap oleh pemilik sesuai cakupan roadmap.
  4. Status penutupan Fase 01 dan register keputusan yang masih OPEN (`status_user_id`, CORS/Sanctum produksi, pencabutan token vs sesi) tetap dipertahankan.
- Why: memenuhi alur verifikasi dan tata kelola dokumentasi repository sesuai AGENTS.md sebelum melangkah ke perencanaan Fase 03.
- Validation: verifikasi status dan konsistensi silang seluruh dokumen dokumentasi.
- Notes / risks:
  - Task scheduler OS belum dipasang/diaktifkan.
  - Tidak ada izin deployment ke produksi atau pencadangan database operasional.
  - Modul lampiran operasional belum tersedia (konsistensi database–lampiran tetap N/A).
- Evidence: `reports/feature-update/PHASE-02C-MANUAL-REVIEW.md`, `reports/feature-update/PHASE-02C-MONITORING-SCHEDULE.md`.

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
  - Backend: `php artisan test` → **154 passed (720 assertions)** (29 tes baru 02c PASS).
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
