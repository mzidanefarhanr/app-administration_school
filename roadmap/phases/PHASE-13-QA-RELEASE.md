# Fase 13 — QA akhir, rilis dan handoff operasional

Status teknis: IN PROGRESS (Subfase 13a SELESAI LENGKAP & DITUTUP; Subfase 13b PLAN PROPOSED — DEC-49: D1–D10). Status review: Subfase 13a OWNER APPROVED (#1–#18, 2026-11-03 WIB); Subfase 13b MENUNGGU PERSETUJUAN RENCANA PEMILIK (D1–D10). Verifikasi otomatis: EndToEndIntegrationTest 6/6 PASS, probe-integration 5/5 PASS, lint & build pass.
Dependensi: Fase 00 s.d. Fase 12 SELESAI LENGKAP & DITUTUP. Baseline implementasi 13a: `6d79fdd`; baseline rencana 13b: `cace7d3`.

## Tujuan dan scope
QA akhir, rilis dan handoff operasional. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis.

## Subfase

### 13a — QA integrasi dan UAT
Scope: Inventory semua endpoint/halaman aktif (334 endpoint, 27 halaman); full role matrix (5 peran); end-to-end siswa→rombel→surat→arsip, guru→jadwal→jurnal→rekap, izin→tugas→approval dan backup; audit zero PII leak; multi-viewport (1440/768/390px) & light/dark; test suite otomatis EndToEndIntegrationTest.php (6/6 pass); probe UAT probe-integration (5/5 pass); catat open issues dan scope release nyata. Detail rencana: `reports/feature-update/PHASE-13A-PLAN.md` (DEC-48 APPROVED). Laporan: `reports/feature-update/PHASE-13A-REPORT.md`. Checklist review manual: `reports/feature-update/PHASE-13A-MANUAL-REVIEW.md` (#1–#18). Status: **OWNER APPROVED (#1–#18, 2026-11-03 WIB; DEC-48 CLOSED) — SUBFASE 13A SELESAI LENGKAP & DITUTUP**.

Kriteria penerimaan: Suite dan UAT sesuai hasil; semua NOT RUN/FAIL/BLOCKED eksplisit; approval manual tidak melampaui checklist.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-13-QA-RELEASE.md.
Kerjakan subfase 13a saja: QA integrasi dan UAT sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 13b — Release readiness
Scope: Restore drill baru pada kandidat release, backup lengkap setelah modul file berkembang, cek manifest/schema, env/HTTPS/worker/scheduler/storage, migration dan rollback plan; dokumentasi operator. Detail rencana: `reports/feature-update/PHASE-13B-PLAN.md` (DEC-49: D1–D10). Status: **PLAN PROPOSED — MENUNGGU PERSETUJUAN PEMILIK (DEC-49: D1–D10)**.

Kriteria penerimaan: Backup terbaru mencakup seluruh domain/file; smoke test dan rencana rollback tersedia; tidak ada blocker kritis dalam scope release.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-13-QA-RELEASE.md.
Kerjakan subfase 13b saja: Release readiness sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 13c — Rilis bila diotorisasi
Scope: Siapkan kandidat/reviewable deployment instructions; commit/push/merge/deploy hanya mengikuti instruksi pemilik. Setelah deploy sah, smoke test dan handoff versi, known issues, operasi backup.

Kriteria penerimaan: SHA/versi terdeploy aktual dan hasil smoke tercatat. Jika deploy tidak diotorisasi, selesai pada READY FOR REVIEW, bukan DEPLOYED.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-13-QA-RELEASE.md.
Kerjakan subfase 13c saja: Rilis bila diotorisasi sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Tidak menandai semua roadmap selesai bila pemilik memilih release parsial. Tidak restore DB aktif untuk uji.

## Verifikasi minimum
Integration, direct API role, migration on copy, full DB+file restore, light/dark/mobile/PDF, deployment smoke bila sah.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
PROJECT_CONTEXT; ROADMAP; OPERATIONS; seluruh kontrak affected; INDEX; HANDOFF; CHANGELOG; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Kesiapan produksi memerlukan bukti dan target operasi; dokumentasi rencana bukan sertifikasi atau jaminan keamanan.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
