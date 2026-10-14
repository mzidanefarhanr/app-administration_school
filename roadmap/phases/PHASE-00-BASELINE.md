# Fase 00 — Audit baseline dan kesiapan lingkungan

Status teknis: NOT STARTED. Status review: PENDING. Semua tes/browser/manual: NOT RUN.
Dependensi: Tidak ada. Baseline rencana: b02a87f; audit ulang HEAD saat pelaksanaan.

## Tujuan dan scope
Audit baseline dan kesiapan lingkungan. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 00a — Inventory source
Scope: Petakan route → controller/request/policy → model/migration → consumer Vue; klasifikasi scaffold/demo/implemented; audit working tree dan SHA. Tinjau seluruh API resource termasuk firstlogin, register, aktivitas, reset password serta profil. Jangan hanya meninjau nama menu.

Kriteria penerimaan: SOURCE inventory bertaut ke file aktual; delta dari laporan awal dicatat; tidak ada mutasi aplikasi/data.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-00-BASELINE.md.
Kerjakan subfase 00a saja: Inventory source sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 00b — Kesiapan uji dan rencana
Scope: Catat PHP/Node/MySQL aktual jika tersedia; dependency/lockfile; DB/storage test dan konfigurasi cache. Build hanya bila dependency tersedia atau instalasi development diotorisasi. Identifikasi keputusan subfase 01a.

Kriteria penerimaan: Perintah dan output aktual; test/browser belum tersedia diberi NOT RUN/BLOCKED; rencana 01a konkret tanpa implementasi.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-00-BASELINE.md.
Kerjakan subfase 00b saja: Kesiapan uji dan rencana sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Kode aplikasi, schema, data, dependency lock tidak diubah. Hanya laporan/dokumentasi diperbarui.

## Verifikasi minimum
Inventaris guest/auth dan payload luas; audit seed tanpa menyalin identitas; tidak menjalankan test DB sebelum isolasi.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
PROJECT_CONTEXT; DATABASE; API-CONTRACTS; laporan baseline; DECISIONS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Versi runtime, jalur test dan data lokal belum terbukti. Laporan source awal tidak menutup audit lingkungan OpenCode.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
