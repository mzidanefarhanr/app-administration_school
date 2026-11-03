# Fase 11 — Dashboard operator dan laporan

Status teknis: **11a PLAN — AWAITING OWNER APPROVAL (D1–D10; DEC-42 PROPOSED, 2026-11-03 WIB)**. Status review: **PENDING OWNER REVIEW (D1–D10)**. Semua tes/browser/manual: **NOT RUN** (langkah perencanaan; implementasi S1–S8 belum diotorisasi).
Dependensi: Modul sumber 03–10; boleh bertahap setelah sumber siap. Baseline rencana: `2a36800` (pencatatan penerimaan pemilik Subfase 10c `#1–#18` dan penutupan Fase 10); paket rencana 11a: `reports/feature-update/PHASE-11A-PLAN.md`.

## Tujuan dan scope
Dashboard operator dan laporan. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 11a — Ganti widget demo
- **Status implementasi (2026-11-03 WIB):** **PLAN — AWAITING OWNER APPROVAL (D1–D10; DEC-42 PROPOSED)** (`reports/feature-update/PHASE-11A-PLAN.md`). Implementasi kode langkah S1–S8 belum dimulai.
Scope: Statistik siswa/guru/rombel, data kurang lengkap, surat overdue/pending, izin pending, jurnal missing, backup terakhir; tiap widget mengikuti ability.

Kriteria penerimaan: Tidak ada Orders/Revenue/data sample; angka nyata dengan scope; widget tanpa izin tidak query/ungkap data.

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
Scope: Tren per tahun, mutasi/bantuan/kelengkapan; definisi aktif/pending/overdue; filter drilldown dan ekspor. Dapodik rekonsiliasi file jika dipilih, bukan sync otomatis.

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
Scope: Daftar prioritas operator dari deadline/jurnal/dokumen kedaluwarsa; status selesai/cancel; dedup dan link ke objek.

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
