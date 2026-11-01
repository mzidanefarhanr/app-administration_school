# Fase 07 — Persuratan dan generator dokumen

Status teknis: **07a IMPLEMENTED dan diverifikasi agen 2026-11-01**; 07b/07c NOT STARTED. Status review: **07a APPROVED 2026-11-01 WIB (#1–#16)** berdasarkan "Pengujian nomor 1-16 sudah berhasil dan saya approved."; server UAT dihentikan setelah penerimaan (DEC-27).
Status rencana: **PLAN 07a APPROVED — DEC-32 APPROVED 2026-11-01 WIB** ("Saya setuju D1-D10"); implementasi 07a selesai. Rencana di `reports/feature-update/PHASE-07-PLAN.md` (baseline `1101812`); laporan `PHASE-07A-REPORT.md`. DEC-06 dan DEC-11 tetap OPEN dan hanya mengikat 07b/07c.
Dependensi: 01,02; siswa/guru siap untuk auto-fill; DEC-06,11. Baseline implementasi 07a: `f46f736`.

## Tujuan dan scope
Persuratan dan generator dokumen. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 07a — Surat masuk dan lampiran dasar
Status: **IMPLEMENTED + agent-verified; OWNER APPROVED 2026-11-01 WIB (#1–#16).**

Scope: Agenda surat masuk dengan tanggal terima/surat, nomor sumber, pengirim/perihal/kategori, lampiran privat, search/filter. Layanan file minimal dibuat di sini dan diperluas Fase09.

Kriteria penerimaan: Surat nyata tersimpan, nomor pengirim tidak diperlakukan sebagai nomor unik internal, lampiran hanya dapat diakses berizin.

Bukti implementasi: tiga tabel additive `letters`/`letter_attachments`/`letter_events`; sembilan endpoint `/api/letters/incoming` dengan `letters.view`/`letters.manage`; lampiran PDF/JPG/PNG privat 10 MB/5 per surat; update/void berversi + `409`; `letter_events` immutable; audit allowlist tanpa teks sensitif; UI Incoming Letters English 390 px; UAT `.uat-phase07` (126 surat/64 baris lampiran/221 event). 20 tes/294 assertions; suite 487 passed/13 skipped/4.786 assertions. Detail: `reports/feature-update/PHASE-07A-REPORT.md`.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-07-LETTERS.md.
Kerjakan subfase 07a saja: Surat masuk dan lampiran dasar sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 07b — Disposisi, surat keluar dan nomor
Scope: Disposisi penerima/tenggat/status dan tindak lanjut; draft→review→approved→issued/cancelled sesuai keputusan; nomor unik atomik dan replay safe; approver/signatory terpisah dari admin teknis.

Kriteria penerimaan: Pengguna tanpa mandat tidak approve/issue; dua penerbitan paralel tidak duplikat; pembatalan/audit utuh; status tidak meloncat.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-07-LETTERS.md.
Kerjakan subfase 07b saja: Disposisi, surat keluar dan nomor sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 07c — Template PDF dan snapshot
Scope: Mulai jenis surat yang dipilih pemilik: siswa aktif/mutasi/tugas/izin; kop, tanda tangan, data auto-fill; template version, snapshot penandatangan dan biodata saat terbit; download berizin.

Kriteria penerimaan: PDF panjang/halaman kedua dirender dan diperiksa; perubahan master tidak mengubah surat issued; surat draft tidak dianggap final; font/tanda tangan konsisten.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-07-LETTERS.md.
Kerjakan subfase 07c saja: Template PDF dan snapshot sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Tanda tangan gambar bukan otomatis tanda tangan digital tersertifikasi. OCR, e-sign provider dan pengiriman email/WhatsApp otomatis di luar scope.

## Verifikasi minimum
Counter concurrency, transition/replay, approval scope, lampiran berbahaya, PDF data panjang, filter/agenda dan history.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
DATABASE; API-CONTRACTS; REQUIREMENTS; ACCESS-CONTROL; DECISIONS; OPERATIONS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Format nomor dan identitas penandatangan tidak ditebak; surat final jangan dibuat ulang dari biodata yang berubah.

## Syarat keluar subfase
- [x] Scope 07a selesai (07a); 07b/07c belum dijalankan.
- [x] Kontrak request/response/schema/permission yang berubah didokumentasikan di DATABASE/API-CONTRACTS/ACCESS-CONTROL/OPERATIONS.
- [x] Uji relevan benar-benar dijalankan (20 tes surat/294 assertions; suite 487/4.786; build 26,18 s; ESLint 0); NOT RUN dicatat.
- [x] Checklist manual tersedia (`PHASE-07A-MANUAL-REVIEW.md`); persetujuan pemilik **APPROVED 2026-11-01 WIB (#1–#16)** dan dicatat terpisah.
- [x] SHA/working tree, known issues dan langkah berikutnya dicatat di INDEX/HANDOFF/ROADMAP dan `PHASE-07A-REPORT.md`.
- [x] Server UAT dihentikan setelah penerimaan (DEC-27); data `.uat-phase07` dipertahankan.
- [x] 07b/07c belum dijalankan tanpa instruksi/rencana terpisah.
