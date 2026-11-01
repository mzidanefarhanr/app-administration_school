# Fase 07 — Persuratan dan generator dokumen

Status teknis: **07a IMPLEMENTED dan diverifikasi agen 2026-11-01**; **07b IMPLEMENTED dan diverifikasi agen 2026-11-01**; **07c PLAN — AWAITING OWNER APPROVAL (D1–D11), belum diimplementasikan**. Status review: **07a APPROVED 2026-11-01 WIB (#1–#16)** dan **07b APPROVED 2026-11-02 WIB (#1–#16)**, keduanya berdasarkan "Pengujian nomor 1-16 sudah berhasil dan saya approved."; server UAT 07a dan 07b dihentikan setelah penerimaan (DEC-27) dengan data `.uat-phase07` dipertahankan.
Status rencana: **PLAN 07a APPROVED — DEC-32 APPROVED 2026-11-01 WIB** ("Saya setuju D1-D10"); implementasi 07a selesai. Rencana 07a di `reports/feature-update/PHASE-07-PLAN.md` (baseline `1101812`); rencana rinci 07b di `reports/feature-update/PHASE-07B-PLAN.md` (baseline `aec356c`) berstatus **PLAN APPROVED — DEC-33 APPROVED 2026-11-01 WIB** ("Saya setuju D1-D11"); rencana rinci 07c di `reports/feature-update/PHASE-07C-PLAN.md` (baseline `ff581c6`) berstatus **PLAN — AWAITING OWNER APPROVAL (D1–D11)** dan diajukan sebagai **DEC-34 PROPOSED**; laporan `PHASE-07A-REPORT.md` dan `PHASE-07B-REPORT.md`. **DEC-06 CLOSED** melalui D4–D5/DEC-33; DEC-11 dan DEC-13 tetap OPEN sampai keputusan 07c; 07c belum diotorisasi.
Dependensi: 01,02; siswa/guru siap untuk auto-fill; DEC-11; dependency PDF (D2). Baseline implementasi 07a: `f46f736`; baseline rencana/implementasi 07b: `aec356c`/`d326369`; paket rencana 07b: `c4bd338`; paket implementasi/review 07b: `d736b37`/`7f89264`; baseline rencana 07c: `ff581c6`.

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
Status: **IMPLEMENTED + agent-verified 2026-11-01; OWNER APPROVED 2026-11-02 WIB (#1–#16)**. **DEC-33 APPROVED 2026-11-01 WIB** ("Saya setuju D1-D11"); rencana rinci `reports/feature-update/PHASE-07B-PLAN.md` (baseline `aec356c`; paket rencana `c4bd338`); laporan `PHASE-07B-REPORT.md`; checklist `PHASE-07B-MANUAL-REVIEW.md`. **DEC-06 CLOSED** melalui D4–D5 (format nomor `{seq:03d}/{school_code}/{year}`, counter global tahunan, nomor saat issue, approver/signatory terpisah). DEC-11/DEC-13 tetap OPEN. Setelah penerimaan, server UAT 14025/18025 dihentikan 2026-11-02 (DEC-27; PID 21876/3132 diverifikasi, port bebas, `.uat-phase07` dipertahankan).

Scope: Disposisi penerima/tenggat/status dan tindak lanjut; draft→review→approved→issued/cancelled sesuai keputusan; nomor unik atomik dan replay safe; approver/signatory terpisah dari admin teknis.

Desain disetujui: kolom additive `recipient`/`official_number`/`workflow_status`/`approved_*`/`issued_*` pada `letters` tanpa mengubah kolom lama; tabel `letter_dispositions` (penerima pegawai, tenggat, status, tindak lanjut, versi, pembatalan), `letter_approvals` immutable, `letter_number_counters` unik per `(scope, period)`; nomor diberikan saat issue; `letters.approve` untuk approve/reject dan `letters.sign` untuk issue/sign, drafter tidak dapat menyetujui/menandatangani sendiri; disposisi hanya surat masuk dan disposisi terbuka memblokir void (`409`); enam endpoint disposisi + 13 endpoint outgoing; audit allowlist; fixture UAT additive `.uat-phase07`.

Kriteria penerimaan: Pengguna tanpa mandat tidak approve/issue; dua penerbitan paralel tidak duplikat; pembatalan/audit utuh; status tidak meloncat.

Bukti implementasi: empat migration additive (`letters` fields, `letter_dispositions`, `letter_approvals`, `letter_number_counters`); service outgoing/penomoran/disposisi; 28 route surat total; halaman Outgoing Letters + Dispositions + panel Incoming; **35 tes 07b/531 assertions**, suite **522 passed/13 skipped/5.320 assertions**, Pint 31 file, build 41,94 s, ESLint 0; UAT `.uat-phase07` 46 outgoing/32 disposisi dengan browser/API operator, kepsek, waka. Detail dan NOT RUN: `PHASE-07B-REPORT.md`.

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
Status: **PLAN — AWAITING OWNER APPROVAL (D1–D11)**. Rencana rinci `reports/feature-update/PHASE-07C-PLAN.md` (baseline `ff581c6`) diajukan sebagai **DEC-34 PROPOSED**; **belum ada kode, migration, dependency, tes, atau UAT**. **DEC-11 tetap OPEN** sampai D1–D11 disetujui; **DEC-13 tetap OPEN**. Usulan: tiga jenis surat (Student Active Certificate, Student Mutation Certificate, Employee Duty Letter), `letter_settings` per profil sekolah aktif (kop/logo/penandatangan/signature/stamp privat), template Blade berversi, `dompdf/dompdf:^2.0` (D2), PDF + snapshot immutable saat `approved → issued`, preview watermarked, download berizin untuk issued/cancelled, 9 route baru (37 total), UAT `.uat-phase07` + `prepare-documents`.
Scope: Mulai jenis surat yang dipilih pemilik: siswa aktif/mutasi/tugas/izin; kop, tanda tangan, data auto-fill; template version, snapshot penandatangan dan biodata saat terbit; download berizin.

Kriteria penerimaan: PDF panjang/halaman kedua dirender dan diperiksa; perubahan master tidak mengubah surat issued; surat draft tidak dianggap final; font/tanda tangan konsisten.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF, PHASE-07-LETTERS.md dan PHASE-07C-PLAN.md.
Kerjakan subfase 07c saja: kop, tanda tangan, stempel, template PDF, dan snapshot
sesuai scope, kriteria, dan D1–D11 yang disetujui (DEC-34).
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Jangan menambah dependency
selain yang disetujui D2. Scope ini belum memberi izin
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
- [x] Scope 07a selesai (07a) dan **OWNER APPROVED #1–#16 (2026-11-01 WIB)**; 07c belum dijalankan.
- [x] Scope 07b selesai (07b) dan **OWNER APPROVED #1–#16 (2026-11-02 WIB)**; rencana/implementasi terpisah (`PHASE-07B-PLAN.md`, `PHASE-07B-REPORT.md`).
- [x] Kontrak request/response/schema/permission yang berubah didokumentasikan di DATABASE/API-CONTRACTS/ACCESS-CONTROL/OPERATIONS.
- [x] Uji relevan benar-benar dijalankan (20 tes 07a/294 assertions; 35 tes 07b/531 assertions; suite 522/5.320; build 41,94 s; ESLint 0); NOT RUN dicatat.
- [x] Checklist manual tersedia (`PHASE-07A-MANUAL-REVIEW.md`, `PHASE-07B-MANUAL-REVIEW.md`); persetujuan pemilik dicatat terpisah (07a 2026-11-01; 07b 2026-11-02).
- [x] SHA/working tree, known issues dan langkah berikutnya dicatat di INDEX/HANDOFF/ROADMAP, `PHASE-07A-REPORT.md`, dan `PHASE-07B-REPORT.md`.
- [x] Server UAT 07a dan 07b dihentikan setelah penerimaan (DEC-27); data `.uat-phase07` dipertahankan.
- [x] 07c belum dijalankan tanpa instruksi/rencana terpisah; rencana 07c `PHASE-07C-PLAN.md` berstatus **PLAN — AWAITING OWNER APPROVAL (D1–D11)** dan belum diotorisasi.
