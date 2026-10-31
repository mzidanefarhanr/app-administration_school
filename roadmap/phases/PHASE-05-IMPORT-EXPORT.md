# Fase 05 — Impor, ekspor dan kualitas data

Status teknis: **05a IMPLEMENTED (agent-verified 2026-10-30)**; **05b IMPLEMENTED (`ad74309`, agent-verified 2026-10-31)**; **05c IMPLEMENTED (`c2fddfa`, agent-verified 2026-10-31, DEC-28)**. Status review: **05a APPROVED 2026-10-30 WIB (#1–#12)**; **05b APPROVED 2026-10-31 WIB (#1–#14)**; **05c APPROVED 2026-10-31 WIB (#1–#14)**. Server UAT 04b dan 05 dihentikan setelah penerimaan sesuai DEC-27 (port 14022/18022/14023/18023 bebas); data `.uat-*` dipertahankan.
Dependensi: 03,04; DEC-08, DEC-25, DEC-26, DEC-27; DEC-28 (APPROVED dan diimplementasikan untuk 05c). Baseline rencana: b02a87f; baseline paket 05a: HEAD `b093db7` sebelum commit; implementasi 05a `58828a6`; baseline rencana 05b `4f1fec0`; implementasi 05b `ad74309`; baseline rencana 05c `8b091cf`; implementasi 05c `c2fddfa`.
Rencana rinci: `reports/feature-update/PHASE-05-PLAN.md`, `PHASE-05B-PLAN.md`, dan `PHASE-05C-PLAN.md`. Keputusan D1–D8 **APPROVED 2026-10-30 WIB sebagai DEC-25**; keputusan 05b D1–D7 **APPROVED 2026-10-30 WIB sebagai DEC-26**; keputusan 05c D1–D7 **APPROVED 2026-10-31 WIB sebagai DEC-28**; DEC-12 (format rekap sekolah) masih PROPOSED. Laporan: `reports/feature-update/PHASE-05A-REPORT.md`, `PHASE-05B-REPORT.md`, `PHASE-05C-REPORT.md`; checklist manual: `PHASE-05A-MANUAL-REVIEW.md`, `PHASE-05B-MANUAL-REVIEW.md`, `PHASE-05C-MANUAL-REVIEW.md`.

## Status implementasi 05a (2026-10-30)

- Berkas CSV UTF-8 (maks 5 MB / 2.000 baris), header English ketat 17 kolom, delimiter `,`/`;`, BOM diabaikan; preview tanpa mutasi; commit **all-or-nothing** (satu transaksi + `lockForUpdate`); mode `insert`/`update` dengan match NIS lalu NIK (bukan nama).
- Laporan error CSV privat (disk `local`, `imports/{token}/errors.csv`), formula dinetralkan; retensi 30 hari manual (D6).
- Endpoint: template (`students.view`), upload/show/commit/errors (`students.import`, hanya pembuat batch; 404 untuk pengguna berizin lain). Ability `students.import` untuk `admin-sistem`/`operator-sekolah`.
- Migration additive `2029_07_27_000001` (`import_batches`) dan `2029_07_27_000002` (ability); `App\Support\StudentRules` menyatukan aturan form + impor.
- UAT terisolasi `.uat-phase05` (SQLite; launcher `scripts/phase05-uat.php`). Bukti: 18 tes impor/97 assertion; regresi 170/1097 (13 skipped); Pint 16 file; 3 unit test Node; build 26,02 s; browser DOM valid/campuran/update/template, 0 console error.
- **Saat 05a selesai, belum dikerjakan**: impor pegawai, pilihan kolom ekspor, XLSX, job antrean, scheduler retensi. Rekap sekolah tetap menunggu DEC-12 (bukan bagian 05a–05c). MariaDB/concurrency/two-tab/screenshot NOT RUN.

## Status implementasi 05b (2026-10-31)

- Mesin impor 05a diekstrak ke `App\Services\Imports\ImportService`; kontrak siswa tidak berubah (regresi 18/97 PASS). Impor pegawai: template English 22 kolom, match update **NIK**, NIK wajib update/opsional insert, update parsial, validasi gabungan via `EmployeeRules`, Subject Teacher/Staff, Regency+District, error privat `Row,Field,Message,NIK,NUPTK`, commit all-or-nothing.
- Ability `employees.import` (migration additive `2029_07_28_000001`) hanya untuk `admin-sistem`/`operator-sekolah`; template cukup `employees.view`.
- Ekspor pegawai: registry 10 kolom operasional default + 15 kolom PII; PII hanya `employees.manage` (tanpa izin `403`); `GET /api/employees/export/options`; kolom tak dikenal `422`; urutan registry; batas 10.000 baris.
- UI: `EmployeeImportDialog.vue` + `EmployeeExportDialog.vue` pada Teachers/Employees; Import hanya untuk `employees.import` dan bukan mode arsip.
- Bukti: 28/189 + 15/60 tes (**43/249**); suite **402 passed / 13 skipped / 3.587 assertions**; Pint 15 file; 15 unit Node; ESLint file baru 0 error; build 28,46 s; migration diterapkan ke `.uat-phase05` dengan data tetap 409 siswa/200 pegawai.
- **Belum/NOT RUN**: browser/visual UAT 05b (otomasi browser diblokir kebijakan sesi), MariaDB/InnoDB, two-tab concurrency, retensi otomatis, rekap sekolah (menunggu DEC-12), XLSX. Review pemilik **APPROVED 2026-10-31 WIB (#1–#14)** berdasarkan pernyataan pemilik, bukan pengujian ulang agen; item NOT RUN tetap NOT RUN. Server UAT 04b/05 dihentikan 2026-10-31 (DEC-27); perintah restart ada di `PHASE-05B-MANUAL-REVIEW.md`/HANDOFF.

## Tujuan dan scope
Impor, ekspor dan kualitas data. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis.

## Subfase

### 05a — Impor siswa (SELESAI — IMPLEMENTED 2026-10-30; owner APPROVED 2026-10-30 WIB #1–#12)
Scope: Template/version, mapping kolom, preview tanpa mutasi, validasi baris/FK/identity, mode insert/update eksplisit, hasil dan error download privat; batch token idempotent. Implementasi tidak menambah dependency baru (parser CSV memakai fungsi PHP standar); tidak ada Excel library.

Kriteria penerimaan: Leading zero utuh; preview tidak mengubah DB; kasus campuran error mengikuti kebijakan; retry tidak menduplikasi; update tidak menimpa record luar scope.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-05-IMPORT-EXPORT.md.
Kerjakan subfase 05a saja: Impor siswa sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 05b — Impor pegawai dan pilihan kolom ekspor
Scope (DEC-25): Impor pegawai memakai mesin dan rule domain sendiri (match **NIK**); pilihan kolom ekspor dengan batas kolom sensitif/izin; escape formula CSV berbahaya; error dilaporkan per baris. **Rekap sekolah ditunda** menunggu contoh format (DEC-12 PROPOSED), bukan bagian 05b sampai format disepakati. Dapodik hanya pencocokan file bila dipilih.

**Rencana rinci:** `reports/feature-update/PHASE-05B-PLAN.md`. Status **IMPLEMENTED (`ad74309`, agent-verified 2026-10-31); owner APPROVED 2026-10-31 WIB (#1–#14); server UAT dihentikan setelah penerimaan (DEC-27)**. Implementasi mencakup: ekstraksi mesin impor 05a yang menjaga perilaku siswa, ability `employees.import`, template 22 kolom English, match update via NIK, aturan Subject Teacher/Non-teaching, identifikasi Regency+District, laporan error `Row,Field,Message,NIK,NUPTK`, registry 10 kolom operasional + 15 kolom sensitif (khusus `employees.manage`), endpoint `/api/employees/export/options`, UI impor/kolom ekspor, dan UAT pada `.uat-phase05`. Bukti dan NOT RUN ada di `PHASE-05B-REPORT.md`.

Kriteria penerimaan: Filter list/export konsisten, sensitive columns dibatasi, spreadsheet formula tidak aktif dari input tak tepercaya, error dilaporkan per baris.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-05-IMPORT-EXPORT.md.
Kerjakan subfase 05b saja: Ekspor dan impor pegawai sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 05c — Pemeriksaan kelengkapan

Status: **IMPLEMENTED — agent-verified 2026-10-31; owner APPROVED 2026-10-31 WIB (#1–#14)** berdasarkan pernyataan pemilik **"Pengujian nomor 1-14 sudah berhasil dan saya approved."**; implementasi `c2fddfa`. Rencana rinci: `reports/feature-update/PHASE-05C-PLAN.md` (baseline `8b091cf`). Tujuh keputusan pemilik D1–D7 **APPROVED 2026-10-31 WIB sebagai DEC-28** ("Saya setuju D1-D7"). Implementasi mencakup: kelengkapan siswa (denominator placed, unplaced NIK = blocked di luar persentase) dan pegawai (denominator active, aturan Pendidik/Tenaga Kependidikan), kandidat duplikat tanpa auto-merge, tabel review berversi `data_quality_reviews` per tahun ajaran, enam endpoint `/api/data-quality/*` tanpa ability baru, halaman Data Quality + deep link ke form lama, dan 120 fixture sintetis `UAT-DQ` pada `.uat-phase05` (guarded/idempotent). Bukti: 15 tes data quality/239 assertions, suite 417 passed/13 skipped/3.826 assertions, Pint 21 file, 17 tes Node, build 38,18 s, browser DOM UAT. Server 14023/18023 dihentikan setelah penerimaan (DEC-27; PID 10336/19196 diverifikasi, port bebas, Apache/MariaDB tidak disentuh, data `.uat-phase05` dipertahankan). **NOT RUN:** dua tab/409 browser, MariaDB/InnoDB data quality, uji beban, screenshot visual; item ini tetap NOT RUN. Rekap sekolah tetap menunggu DEC-12 dan bukan bagian 05c. Laporan: `reports/feature-update/PHASE-05C-REPORT.md`; checklist: `PHASE-05C-MANUAL-REVIEW.md`.

Scope: Aturan kelengkapan kontekstual, daftar duplikat/invalid/missing, link ke record berizin, status review operator; jangan auto-merge orang berdasar nama.

Kriteria penerimaan: Angka kelengkapan dengan denominator jelas; siswa NIK belum tersedia dibedakan incomplete vs blocked sesuai aturan; correction ter-audit.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-05-IMPORT-EXPORT.md.
Kerjakan subfase 05c saja: Pemeriksaan kelengkapan sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Tidak menggunakan API Dapodik tak diketahui atau menebak format resmi. Format F8355/KJP/PPDB membutuhkan contoh sekolah, bukan label menu saja.

## Verifikasi minimum
File salah format/ukuran, identitas numeric/leading zero, batch campuran, idempotency, izin, seluruh halaman export, CSV injection.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
REQUIREMENTS; API-CONTRACTS; DATABASE; TESTING; DECISIONS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Bulk dapat partial jika kebijakan memilih per-row; laporan tidak boleh menyebut seluruh batch sukses ketika sebagian gagal.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
