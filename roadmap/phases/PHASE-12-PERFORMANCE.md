# Fase 12 — Performa, observability dan konsistensi

Status teknis: NOT STARTED. Status review: PENDING. Semua tes/browser/manual: NOT RUN.
Dependensi: Modul aktif; audit 00. Baseline rencana: b02a87f; audit ulang HEAD saat pelaksanaan.

## Tujuan dan scope
Performa, observability dan konsistensi. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 12a — Pagination dan query
Scope: Lengkapi pagination/filter server seluruh resource aktif, index terukur, eager loading tepat, agregasi chart, limit export/import. Catat dataset/metode sebelum target response.

Kriteria penerimaan: Payload terbatas, query count/time diukur, seluruh hasil export benar, tidak ada cross-role cache leak.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-12-PERFORMANCE.md.
Kerjakan subfase 12a saja: Pagination dan query sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 12b — Job dan error
Scope: Worker/scheduler jika dipakai, retry/idempotency, progress/status, exception handling; hilangkan console suppression global dan gunakan observability tanpa data pribadi.

Kriteria penerimaan: Gagal network/server/job terlihat, error tercatat dengan correlation ID aman; log tidak menyimpan raw biodata/password.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-12-PERFORMANCE.md.
Kerjakan subfase 12b saja: Job dan error sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 12c — Konsistensi antarmenu
Scope: Audit halaman aktif, form/detail/dialog/filter/export, bahasa Indonesia, keyboard/mobile/light/dark; hapus menu demo operasional setelah audit usage.

Kriteria penerimaan: Tidak ada link/tombol dummy; tema dan state konsisten; build/lint relevan dan browser/manual diberi hasil nyata.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-12-PERFORMANCE.md.
Kerjakan subfase 12c saja: Konsistensi antarmenu sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Bukan upgrade framework mayor atau cleanup massal. Performa diukur, bukan diklaim dari adanya index saja.

## Verifikasi minimum
Large synthetic dataset, query/payload measurement, retry/failure, logs, UI states, cross-role cache.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
ARCHITECTURE; DESIGN_SYSTEM; TESTING; OPERATIONS; API-CONTRACTS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Target latency/dataset belum diputuskan. Dependency baru wajib alasan dan pemeriksaan kompatibilitas versi aktual.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
