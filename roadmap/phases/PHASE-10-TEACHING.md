# Fase 10 — Administrasi pembelajaran

Status teknis: **10a PLAN — AWAITING OWNER APPROVAL (D1–D11; DEC-39 PROPOSED; DEC-10 proposed resolution)**; **10b–10c NOT STARTED**. Status review: **10a PLAN AWAITING OWNER APPROVAL (D1–D11)**; **10b–10c PENDING**. Semua tes/browser/manual: **NOT RUN** (tahap rencana).
Dependensi: 04,06; DEC-10 (usulan penyelesaian di `DEC-39` D3/D5/D8). Baseline rencana 10a: `f185ca6` (penerimaan pemilik 09b `#1–#18` di atas implementasi `51002e6`); rencana lengkap di `reports/feature-update/PHASE-10-PLAN.md`.

## Tujuan dan scope
Administrasi pembelajaran. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 10a — Penugasan dan jadwal
- **Status rencana (2026-11-03 WIB):** **PLAN — AWAITING OWNER APPROVAL (D1–D11; DEC-39 PROPOSED)** di `reports/feature-update/PHASE-10-PLAN.md` (baseline `f185ca6`). Menunggu keputusan pemilik atas `D1–D11` (termasuk penyelesaian `DEC-10`) sebelum implementasi kode/migration/tes dimulai.
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
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
