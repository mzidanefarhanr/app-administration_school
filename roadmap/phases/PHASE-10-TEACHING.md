# Fase 10 — Administrasi pembelajaran

Status teknis: **10a IMPLEMENTED — agent-verified (`c8be397`) & OWNER APPROVED #1–#18 (`a304df8`, 2026-11-03 WIB)**; **10b IMPLEMENTED — agent-verified (`df9f925`) & OWNER APPROVED #1–#18 (2026-11-03 WIB)**; **10c IMPLEMENTED — agent-verified (2026-11-03 WIB); AWAITING OWNER REVIEW (#1–#18)**. Status review: **10a OWNER APPROVED (#1–#18, 2026-11-03 WIB; `"Pengujian nomor 1-18 sudah berhasil dan saya approved."`)**; **10b OWNER APPROVED (#1–#18, 2026-11-03 WIB; `"Pengujian nomor 1-18 sudah berhasil dan saya approved."`)**; **10c IMPLEMENTED — agent-verified (2026-11-03 WIB; DEC-41 IMPLEMENTED; AWAITING OWNER REVIEW #1–#18)**. Bukti 10a: `TeachingScheduleApiTest` **6 tes / 116 assertions PASS (6,57 s)**, regresi Academic+Access+Archives **147 tes / 2.002 assertions PASS (199,60 s)**, Pint **26 file PASS**, **13 route `/api/teaching/*`**, ESLint **0 error**, `npm run build` **PASS (35,86 s)**, UAT `.uat-phase10` (`18028`/`14028`) **101 penugasan / 121 jadwal / 6 exception**. Bukti 10b: `TeachingJournalApiTest` + `TeachingScheduleApiTest` **18 tes / 264 assertions PASS (17,53 s)** (`TeachingJournalApiTest` 12 tes / 148 assertions + `TeachingScheduleApiTest` 6 tes / 116 assertions), regresi Academic+Access+Archives **147 tes / 2.002 assertions PASS (195,54 s)**, Pint **17 file PASS**, **18 route `/api/teaching/*`**, ESLint **0 error**, `npm run build` **PASS (1m 3s)**, UAT `.uat-phase10` (`prepare-journals` idempoten: **8 akun termasuk `uat.guru2` / 66 jurnal mengajar**, probe HTTP lintas peran/own-assignment `403`/idempotent replay `200`/`409`/holiday exclusion PASS, server dihentikan sesuai DEC-27); otomasi klik browser visual/screenshot & race MariaDB multi-proses **NOT RUN**. Bukti 10c: `TeachingReportApiTest` + `TeachingJournalApiTest` + `TeachingScheduleApiTest` **32 tes / 336 assertions PASS (28.88s)** (`TeachingReportApiTest` 14 tes / 72 assertions; regresi Academic+Access+Archives 147 tes / 2.002 assertions PASS, total 179 tes / 2.338 assertions PASS), Pint **7 file PASS**, **20 route `/api/teaching/*`**, ESLint **0 error**, `npm run build` **PASS (31.50s)**, UAT `.uat-phase10` `probe-reports` 5 probe HTTP PASS (admin 540 sesi/11,9% kepatuhan, guru `scope=mine` 36 sesi/11,1%, 403 lintas guru, kepsek sekolah penuh, streaming CSV BOM; server dihentikan sesuai DEC-27); otomasi klik browser visual & race MariaDB multi-proses **NOT RUN**.
Dependensi: 04,06; DEC-10 (**CLOSED** melalui `DEC-39` D3/D5/D8). Baseline rencana 10a: `f185ca6`; paket rencana 10a: `a8264ad`; persetujuan 10a: `b515cb1`; paket implementasi & review 10a: `c8be397`; penerimaan 10a: `a304df8`; paket rencana 10b: `8ce345b`; persetujuan 10b: `1c2c48c`; paket implementasi & review 10b: `df9f925`; penerimaan 10b: `35728bb`; paket rencana 10c: `reports/feature-update/PHASE-10C-PLAN.md` (`1ab03ef`; `DEC-41 APPROVED D1–D9`).

## Tujuan dan scope
Administrasi pembelajaran. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 10a — Penugasan dan jadwal
- **Status implementasi (2026-11-03 WIB):** **IMPLEMENTED — agent-verified (`c8be397`); OWNER APPROVED (#1–#18, 2026-11-03 WIB; `"Pengujian nomor 1-18 sudah berhasil dan saya approved."`, `a304df8`)** (`reports/feature-update/PHASE-10A-REPORT.md`, `reports/feature-update/PHASE-10A-MANUAL-REVIEW.md`). Disahkan melalui **DEC-39 APPROVED (D1–D11; `"Saya setuju D1-D11."`; DEC-10 CLOSED)** di atas baseline `b515cb1`.
Scope: Relasi banyak guru-matpel-rombel-periode; timetable/hari/jam/ruang bila diperlukan; bentrok guru/rombel/ruang; libur/cancel/reschedule.

Kriteria penerimaan: Guru dapat beberapa penugasan; bentrok nyata ditolak; exception jadwal tidak menghasilkan tugas palsu.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-10-TEACHING.md.
Kerjakan subfase 10a saja: Penugasan dan jadwal sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 10b — Sesi dan jurnal
- **Status implementasi (2026-11-03 WIB):** **IMPLEMENTED — agent-verified (`df9f925`); OWNER APPROVED (#1–#18, 2026-11-03 WIB; `"Pengujian nomor 1-18 sudah berhasil dan saya approved."`)** (`reports/feature-update/PHASE-10B-REPORT.md`, `reports/feature-update/PHASE-10B-MANUAL-REVIEW.md`). Disahkan melalui **DEC-40 APPROVED (D1–D9; `"Saya setuju D1-D9"`)** di atas baseline `1c2c48c` (paket rencana `8ce345b`, baseline 10a `a304df8`).
Scope: Definisikan sesi yang dijadwalkan/berlangsung, jurnal materi/catatan/waktu dan guru; satu jurnal aktif per sesi; own assignment; koreksi admin berizin.

Kriteria penerimaan: Guru kelas lain ditolak; jurnal tersimpan; retry tidak dobel; periode locked dihormati.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-10-TEACHING.md.
Kerjakan subfase 10b saja: Sesi dan jurnal sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 10c — Laporan belum mengisi jurnal
- **Status rencana (2026-11-03 WIB):** **PLAN — OWNER APPROVED (D1–D9, 2026-11-03 WIB; `"Saya setuju D1-D9"`, DEC-41 APPROVED)** di atas baseline `35728bb` (paket rencana `1ab03ef`). Implementasi langkah S1–S8 diotorisasi.
Scope: Bandingkan expected sessions dengan jurnal aktual pada periode/tanggal dan exception; daftar guru/sesi, drilldown dan filter.

Kriteria penerimaan: Guru tanpa sesi tidak dianggap belum mengisi; cancelled/libur dikecualikan; count cocok fixture; akses laporan terbatas.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-10-TEACHING.md.
Kerjakan subfase 10c saja: Laporan belum mengisi jurnal sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Tidak membangun LMS tugas/kuis/rapor lengkap. Absensi dan materi online merupakan perluasan terpisah.

## Verifikasi minimum
Schedule collision/concurrency, assigned/unassigned teacher, session duplicate, locked year, cancellation/reschedule dan missing-journal denominator.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
DATABASE; REQUIREMENTS; ACCESS-CONTROL; API-CONTRACTS; DECISIONS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
“Belum isi jurnal” tidak bisa dihitung hanya dari daftar guru; perlu sumber expected session yang jelas.

## Syarat keluar subfase
- [x] Scope selesai atau partial/blocker dijelaskan (10a dan 10b selesai dan diterima pemilik; 10c selesai diimplementasikan dan diverifikasi agen; menunggu review manual pemilik #1–#18).
- [x] Kontrak request/response/schema/permission yang berubah didokumentasikan (`DATABASE.md`, `ACCESS-CONTROL.md`, `API-CONTRACTS.md`, `TESTING.md`).
- [x] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan (`PHASE-10A-REPORT.md`, `PHASE-10B-REPORT.md`, `PHASE-10C-REPORT.md`).
- [x] Checklist manual tersedia bila dibutuhkan (`PHASE-10A-MANUAL-REVIEW.md` #1–#18 APPROVED; `PHASE-10B-MANUAL-REVIEW.md` #1–#18 APPROVED; `PHASE-10C-MANUAL-REVIEW.md` #1–#18 SIAP REVIEW); persetujuan dicatat hanya setelah jawaban pemilik.
- [x] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [x] Scope berikutnya diusulkan (Subfase 10c siap review pemilik; setelah 10c disetujui, Fase 10 selesai lengkap dan dapat diusulkan fase berikutnya: Fase 11 Dashboard Operator & Laporan).
