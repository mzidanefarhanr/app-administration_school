# Fase 12 — Performa, observability dan konsistensi

Status teknis: IMPLEMENTED & DIVERIFIKASI AGEN (12a, 12b, 12c IMPLEMENTED). Status review: AWAITING OWNER REVIEW (12c #1–#18; DEC-47 IMPLEMENTED).
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

Status teknis: **IMPLEMENTED & DIVERIFIKASI AGEN (2026-11-03)**; **DEC-46 APPROVED (D1–D10)**.
- Migrasi antrean & status: `2029_12_02_000001_create_queue_jobs_and_tracking_tables.php` (`jobs`, `job_batches`, `job_execution_statuses`).
- Middleware `EnsureCorrelationId`: otomatis menghasilkan/melestarikan header `X-Correlation-ID` dan menyematkannya ke Monolog context.
- Monolog PII Redaction (`PiiRedactionProcessor`, `PiiRedactionTap`): penyembunyian otomatis password, token, NIK (16 digit), telepon, dan kunci rahasia pada seluruh log file.
- Standarisasi Global Exception Handler (`Handler.php`): format payload terpadu (`success: false`, `correlation_id`, `message`, `errors`) pada seluruh respons error HTTP API.
- `JobTrackingService` & `JobStatusController`: endpoint status progres `GET /api/jobs/status/{jobId}`, otorisasi pemilik, dan *idempotency replay guard* pencegah duplikasi.
- Frontend Axios Interceptor: penyematan Correlation ID ke objek error & tampilan toast notifikasi ramah pengguna dengan nomor referensi kesalahan teknis.
- Test suite: `QueueAndErrorResilienceTest.php` 7 tes PASS (50 assertions).
- Probe UAT: `scripts/phase10-uat.php probe-resilience` 5/5 PROBES PASSED.
- Laporan: `reports/feature-update/PHASE-12B-REPORT.md`; Panduan review manual: `reports/feature-update/PHASE-12B-MANUAL-REVIEW.md` (#1–#18).

### 12c — Konsistensi antarmenu
Scope: Audit halaman aktif, form/detail/dialog/filter/export, bahasa Indonesia, keyboard/mobile/light/dark; hapus menu demo operasional setelah audit usage.

Kriteria penerimaan: Tidak ada link/tombol dummy; tema dan state konsisten; build/lint relevan dan browser/manual diberi hasil nyata.

Status teknis: **IMPLEMENTED & DIVERIFIKASI AGEN (2026-11-03)**; **DEC-47 APPROVED (D1–D10)**.
- Pembersihan seluruh menu dan rute demo template Sakai: `UI Components`, `Pages`, `Hierarchy`, `Get Started`, `/uikit/*`, `/pages/crud`, `/landing`, `/documentation`. Modul kompilasi berkurang dari 827 ke 666 modul.
- Standardisasi 10 grup navigasi sidebar ke bahasa Indonesia baku (`Beranda`, `Data Master`, `Akademik`, `Kesiswaan & Kepegawaian`, `Tata Persuratan`, `Perizinan Pegawai`, `Arsip Dokumen`, `Administrasi Pembelajaran`, `Akun Saya`, `Pengaturan Akses`).
- Sinkronisasi breadcrumb di seluruh view rute aktif.
- Formatter visual terpusat: `vue-app/src/utils/formatters.js` (formatDate, formatTime, formatDateTime, formatNumber, formatPercentage, emptyPlaceholder).
- Aksesibilitas keyboard focus rings (`:focus-visible`) dan responsivitas mobile viewport 390px (dialog 95vw, touch target $\ge 40$ px).
- Harmonisasi kontras tema mode gelap (`.app-dark`).
- Probe UAT: `scripts/phase10-uat.php probe-ui-consistency` 5/5 PROBES PASSED.
- Linter & Build: `npm run lint` 0 errors, `npm run build` PASS (built in 33.63s).
- Laporan: `reports/feature-update/PHASE-12C-REPORT.md`; Panduan review manual: `reports/feature-update/PHASE-12C-MANUAL-REVIEW.md` (#1–#18).

## Batas pekerjaan
Bukan upgrade framework mayor atau cleanup massal. Performa diukur, bukan diklaim dari adanya index saja.

## Verifikasi minimum
Large synthetic dataset, query/payload measurement, retry/failure, logs, UI states, cross-role cache.

## Dokumen yang diperbarui
ARCHITECTURE; DESIGN_SYSTEM; TESTING; OPERATIONS; API-CONTRACTS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Syarat keluar subfase
- [x] Scope 12a selesai dan diverifikasi agen (S1–S8).
- [x] Scope 12b selesai dan diverifikasi agen (S1–S8).
- [x] Scope 12c selesai dan diverifikasi agen (S1–S8).
- [x] Kontrak request/response/schema/permission yang berubah didokumentasikan (tabel queue, job_execution_statuses, X-Correlation-ID, PII redaction, standard error contract, UI Indonesian nav).
- [x] Uji relevan benar-benar dijalankan (probe-ui-consistency 5/5 PASS, ESLint 0 errors, Pint PASS, Vite build PASS).
- [x] Checklist manual tersedia (`PHASE-12C-MANUAL-REVIEW.md` #1–#18).
- [x] SHA/working tree, known issues dan langkah berikutnya dicatat.
