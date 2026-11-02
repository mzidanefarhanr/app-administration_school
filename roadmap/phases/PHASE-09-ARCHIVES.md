# Fase 09 — Arsip dokumen terpusat

Status teknis: 09a **PLAN — AWAITING OWNER APPROVAL (D1–D10)**; 09b **NOT STARTED**. Status review: PENDING. Semua tes/browser/manual: NOT RUN.
Dependensi: File privat Fase07; DEC-13. Baseline rencana 09a: `aeca361` (2026-11-02); audit ulang HEAD saat pelaksanaan.

## Status paket rencana 09a (2026-11-02)

- Rencana rinci ada di [`reports/feature-update/PHASE-09-PLAN.md`](../../reports/feature-update/PHASE-09-PLAN.md), berstatus **PLAN — AWAITING OWNER APPROVAL (D1–D10)** dengan usulan **DEC-37 PROPOSED**.
- Paket rencana hanya dokumentasi: **tidak ada kode, migration, route, model, service, request, controller, komponen, tes, fixture, atau UAT** yang dibuat/diubah. Semua verifikasi implementasi **NOT RUN**.
- Usulan 09a: dua tabel additive `archive_documents` + `archive_file_versions`; kategori config; link subjek `student`/`employee`/`letter`/`leave` atau null (whitelist, both-or-none, immutable); nomor opsional/non-unik; masa berlaku; versi file immutable + sha256 internal; PDF/JPG/JPEG/PNG 10 MB pada disk privat `local`; `archives.view`/`archives.manage` + ability domain untuk dokumen bertaut; 9 route; halaman `/public/archives`; UAT `.uat-phase09` 18027/14027.
- **09b tetap memerlukan rencana dan persetujuan terpisah**; **DEC-13 OPEN** menjadi gate retensi/purge. 09a mencakup download berizin agar versi dapat diverifikasi end-to-end; preview inline, audit akses download, expiry task, arsip/restore, dan retensi tetap di 09b.
- Implementasi **belum diotorisasi** sampai pemilik menyetujui D1–D10 dan pernyataan dicatat sebagai **DEC-37 APPROVED**.

## Tujuan dan scope
Arsip dokumen terpusat. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 09a — Metadata dan versi
Scope: Kategori, objek siswa/pegawai/surat/izin, nomor/tanggal/masa berlaku, file version/checksum; pencarian lintas domain hanya objek berizin.

Kriteria penerimaan: Upload gagal tidak meninggalkan orphan; versi lama tetap dapat ditelusuri; link type/id arbitrary ditolak.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-09-ARCHIVES.md.
Kerjakan subfase 09a saja: Metadata dan versi sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 09b — Retensi, akses dan pengingat
Scope: Download/preview aman, audit akses sesuai kebutuhan, expiry task di dashboard, arsip/restore, retensi/pemusnahan menurut keputusan.

Kriteria penerimaan: File private tidak terbuka via direct public URL; izin lintas objek benar; expired tampil sebagai tugas; tidak auto-purge data sebelum kebijakan.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-09-ARCHIVES.md.
Kerjakan subfase 09b saja: Retensi, akses dan pengingat sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Tidak memindahkan semua lampiran existing secara destruktif atau membuat OCR/fulltext sebelum dibutuhkan.

## Verifikasi minimum
MIME/extension/size, path traversal, version/history, own/other, failure compensation dan retention boundary.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
DATABASE; SECURITY-DATA; OPERATIONS; API-CONTRACTS; DECISIONS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Metadata dan file bytes harus konsisten; link berizin tidak cukup jika storage publik masih dapat diakses langsung.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
