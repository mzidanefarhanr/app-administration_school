# Fase 12 — Performa, observability dan konsistensi

Status teknis: IN PROGRESS (12a IMPLEMENTED & DIVERIFIKASI AGEN; 12b PLANNED). Status review: AWAITING OWNER REVIEW (12a #1–#18; 12b PLAN PROPOSED).
Dependensi: Modul aktif; audit 00. Baseline rencana: `9a7eeb6`; audit ulang HEAD saat pelaksanaan.

## Tujuan dan scope
Performa, observability dan konsistensi. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis.

## Subfase

### 12a — Pagination dan query
Scope: Lengkapi pagination/filter server seluruh resource aktif, index terukur, eager loading tepat, agregasi chart, limit export/import. Catat dataset/metode sebelum target response.

Kriteria penerimaan: Payload terbatas, query count/time diukur, seluruh hasil export benar, tidak ada cross-role cache leak.

Status teknis: **IMPLEMENTED & DIVERIFIKASI AGEN (2026-11-03)**; **DEC-45 APPROVED (D1–D10)**.
- Migrasi aditif indeks komposit: `2029_12_01_000001_add_performance_composite_indexes.php`.
- Batas keras server-side pagination `per_page <= 100` pada seluruh 11 endpoint daftar aktif (nilai > 100 ditolak validasi 422).
- Eager loading selektif bebas N+1 query: Students, Employees, Teaching Schedules/Assignments, Letters, Leaves, Archives, Operational Tasks.
- Invarian penskalaan kueri $O(1)$: dibuktikan via test otomatis (jumlah kueri konstan pada 5 vs 25 item).
- Memory-safe streaming CSV export: StreamedResponse generator ber-BOM UTF-8 dan formula sanitization.
- Frontend debounce 300 ms pada seluruh input pencarian tabel Vue.
- Test suite: `QueryPerformanceTest.php` 6 tes PASS (62 assertions).
- Probe UAT: `scripts/phase10-uat.php probe-performance` 5/5 PROBES PASSED.
- Laporan: `reports/feature-update/PHASE-12A-REPORT.md`; Panduan review manual: `reports/feature-update/PHASE-12A-MANUAL-REVIEW.md` (#1–#18).

### 12b — Job dan error
Scope: Worker/scheduler jika dipakai, retry/idempotency, progress/status, exception handling; hilangkan console suppression global dan gunakan observability tanpa data pribadi.

Kriteria penerimaan: Gagal network/server/job terlihat, error tercatat dengan correlation ID aman; log tidak menyimpan raw biodata/password.

Status teknis: **PLANNED — AWAITING OWNER APPROVAL (DEC-46 PROPOSED; D1–D10)**.
- Usulan keputusan: DEC-46 (D1–D10) di `reports/feature-update/PHASE-12B-PLAN.md`.
- Belum dieksekusi sebelum instruksi tertulis persetujuan D1–D10 dari pemilik.

### 12c — Konsistensi antarmenu
Scope: Audit halaman aktif, form/detail/dialog/filter/export, bahasa Indonesia, keyboard/mobile/light/dark; hapus menu demo operasional setelah audit usage.

Kriteria penerimaan: Tidak ada link/tombol dummy; tema dan state konsisten; build/lint relevan dan browser/manual diberi hasil nyata.

Status teknis: **NOT STARTED**.

## Batas pekerjaan
Bukan upgrade framework mayor atau cleanup massal. Performa diukur, bukan diklaim dari adanya index saja.

## Verifikasi minimum
Large synthetic dataset, query/payload measurement, retry/failure, logs, UI states, cross-role cache.

## Dokumen yang diperbarui
ARCHITECTURE; DESIGN_SYSTEM; TESTING; OPERATIONS; API-CONTRACTS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Syarat keluar subfase
- [x] Scope 12a selesai dan diverifikasi agen (S1–S8).
- [x] Kontrak request/response/schema/permission yang berubah didokumentasikan (hard-limit 100, indeks aditif).
- [x] Uji relevan benar-benar dijalankan (QueryPerformanceTest 6/6 PASS, probe-performance 5/5 PASS, Pint PASS, Vite build PASS).
- [x] Checklist manual tersedia (`PHASE-12A-MANUAL-REVIEW.md` #1–#18).
- [x] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [x] Scope berikutnya (Subfase 12b) diusulkan via DEC-46 (D1–D10) di `PHASE-12B-PLAN.md`.
