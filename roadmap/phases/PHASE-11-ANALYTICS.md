# Fase 11 — Dashboard operator dan laporan

Status teknis: **11a SELESAI LENGKAP & DITUTUP (2026-11-03 WIB)**; **11b SELESAI LENGKAP & DITUTUP (2026-11-03 WIB)**; **11c IN PROGRESS (DEC-44 APPROVED)**. Status review: **11a OWNER APPROVED (#1–#18)**; **11b OWNER APPROVED (#1–#18)**; **11c PLAN APPROVED (D1–D10, 2026-11-03 WIB)**.
Dependensi: Modul sumber 03–10; boleh bertahap setelah sumber siap. Baseline rencana 11c: `3cf80cf`; paket rencana: `38e5856`.

## Tujuan dan scope
Dashboard operator dan laporan. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis.

## Subfase

### 11a — Ganti widget demo
- **Status implementasi (2026-11-03 WIB):** **SELESAI LENGKAP & DITUTUP — DEC-42 APPROVED & CLOSED**. Review manual pemilik: **APPROVED (#1–#18)** (`reports/feature-update/PHASE-11A-MANUAL-REVIEW.md`). Laporan implementasi: `reports/feature-update/PHASE-11A-REPORT.md`. Paket commit: `2b7e8a5`.
Scope: Statistik siswa/guru/rombel, data kurang lengkap, surat overdue/pending, izin pending, jurnal missing, backup terakhir; tiap widget mengikuti ability.
Kriteria penerimaan: Tidak ada Orders/Revenue/data sample; angka nyata dengan scope; widget tanpa izin tidak query/ungkap data. (Terpenuhi penuh).

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-11-ANALYTICS.md.
Kerjakan subfase 11a saja: Ganti widget demo sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 11b — Laporan dan rekonsiliasi
- **Status implementasi (2026-11-03 WIB):** **SELESAI LENGKAP & DITUTUP — DEC-43 APPROVED & CLOSED; DEC-12 CLOSED**. Review manual pemilik: **APPROVED (#1–#18)** (`reports/feature-update/PHASE-11B-MANUAL-REVIEW.md`). Laporan implementasi: `reports/feature-update/PHASE-11B-REPORT.md`. Paket commit: `78a2c28`.
Scope: Tren per tahun, mutasi/bantuan/kelengkapan; definisi aktif/pending/overdue; filter drilldown dan ekspor. Dapodik rekonsiliasi file jika dipilih, bukan sync otomatis (DEC-12 CLOSED).

Kriteria penerimaan: Chart/list/export konsisten; hari batas dan periode jelas; perbedaan data terlihat tanpa auto-overwrite.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-11-ANALYTICS.md.
Kerjakan subfase 11b saja: Laporan dan rekonsiliasi sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 11c — Tugas dan pengingat internal
- **Status implementasi (2026-11-03 WIB):** **IN PROGRESS — DEC-44 APPROVED (D1–D10; `"Saya setuju D1-D10"`)** (`reports/feature-update/PHASE-11C-PLAN.md`). Implementasi langkah S1–S8 diotorisasi.
Scope: Daftar prioritas operator dari deadline/jurnal/dokumen kedaluwarsa; status selesai/cancel; dedup dan link ke objek; tabel additive `operator_reminders`.

Kriteria penerimaan: Tugas tidak duplikat saat refresh; hanya objek berizin; empty/error state jelas; tidak mengirim pesan eksternal.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-11-ANALYTICS.md.
Kerjakan subfase 11c saja: Tugas dan pengingat internal sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Tidak memakai AI untuk keputusan data atau menambah email/WhatsApp otomatis. Modul sumber yang belum ada diberi unavailable, bukan angka 0 menyesatkan.

## Verifikasi minimum
Count/denominator fixture, timezone due-date, denied widget, cached cross-role leakage, chart theme dan mobile.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
REQUIREMENTS; API-CONTRACTS; DESIGN_SYSTEM; ACCESS-CONTROL; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Cache harus keyed dengan scope yang benar dan invalidation; agregasi bukan mengambil seluruh biodata ke browser.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
