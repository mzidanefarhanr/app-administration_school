# Fase 10 — Administrasi pembelajaran

Status teknis: **10a IMPLEMENTED — agent-verified (`c8be397`) & OWNER APPROVED #1–#18 (`a304df8`, 2026-11-03 WIB)**; **10b PLAN — AWAITING OWNER APPROVAL (`D1–D9` di `reports/feature-update/PHASE-10B-PLAN.md`, `DEC-40 PROPOSED`)**; **10c NOT STARTED**. Status review: **10a OWNER APPROVED (#1–#18, 2026-11-03 WIB; `"Pengujian nomor 1-18 sudah berhasil dan saya approved."`)**; **10b PLAN PENDING OWNER APPROVAL (`D1–D9`)**; **10c PENDING**. Bukti 10a: `TeachingScheduleApiTest` **6 tes / 116 assertions PASS (6,57 s)**, regresi Academic+Access+Archives **147 tes / 2.002 assertions PASS (199,60 s)**, Pint **26 file PASS**, **13 route `/api/teaching/*`**, ESLint **0 error**, `npm run build` **PASS (35,86 s)**, UAT `.uat-phase10` (`18028`/`14028`) **101 penugasan / 121 jadwal / 6 exception** (idempoten, probe HTTP lintas peran/bentrok/`409`/locked year PASS, server dihentikan sesuai DEC-27); otomasi klik browser visual/screenshot & race MariaDB multi-proses **NOT RUN**.
Dependensi: 04,06; DEC-10 (**CLOSED** melalui `DEC-39` D3/D5/D8). Baseline rencana 10a: `f185ca6`; paket rencana 10a: `a8264ad`; persetujuan 10a: `b515cb1`; paket implementasi & review 10a: `c8be397`; penerimaan 10a: `a304df8`; rencana 10b di `reports/feature-update/PHASE-10B-PLAN.md` (`DEC-40 PROPOSED`).

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
- **Status rencana (2026-11-03 WIB):** **PLAN — AWAITING OWNER APPROVAL (`D1–D9` di `reports/feature-update/PHASE-10B-PLAN.md`; `DEC-40 PROPOSED`; baseline `a304df8`)**. Belum ada kode aplikasi, migration, model, service, controller, route, komponen Vue, fixture UAT 10b, atau tes 10b yang dijalankan (`NOT RUN`).
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
- [x] Scope selesai atau partial/blocker dijelaskan (10a selesai dan diverifikasi agen; 10b/10c belum dimulai).
- [x] Kontrak request/response/schema/permission yang berubah didokumentasikan (`DATABASE.md`, `ACCESS-CONTROL.md`, `API-CONTRACTS.md`, `TESTING.md`).
- [x] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan (`PHASE-10A-REPORT.md`).
- [x] Checklist manual tersedia bila dibutuhkan (`PHASE-10A-MANUAL-REVIEW.md` #1–#18); persetujuan dicatat hanya setelah jawaban pemilik.
- [x] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [x] Scope berikutnya diusulkan (10b), belum dijalankan tanpa instruksi.
