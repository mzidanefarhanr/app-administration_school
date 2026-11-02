# Fase 08 — Perizinan siswa dan guru

Status teknis: **08a IMPLEMENTED — agent-verified 2026-11-02**; **08b PLAN — AWAITING OWNER APPROVAL (D1–D8; proposed DEC-36)**. Status review: **08a APPROVED (#1–#16, 2026-11-02 WIB)**; 08b PENDING. Tes otomatis/browser 08a: lihat `PHASE-08A-REPORT.md`; review manual owner: **APPROVED #1–#16**; UAT `.uat-phase08` dihentikan setelah penerimaan.
Dependensi: 01,03,04; file privat Fase07a atau service ekuivalen; DEC-07 (CLOSED via DEC-35). Baseline rencana: b02a87f; audit ulang HEAD saat pelaksanaan.

Rencana rinci: **`reports/feature-update/PHASE-08-PLAN.md`** (baseline audit `2db6475`, 2026-11-02 WIB) — **PLAN APPROVED — DEC-35 APPROVED (2026-11-02 WIB)** berdasarkan **"Saya setuju D1-D12"**. **DEC-07 CLOSED**; **DEC-13 tetap OPEN**. Implementasi **08a telah diselesaikan** (lihat `PHASE-08A-REPORT.md`). Rencana 08b: **`reports/feature-update/PHASE-08B-PLAN.md`** (baseline `618b8f8`) — **PLAN — AWAITING OWNER APPROVAL (D1–D8); proposed DEC-36 PROPOSED**; belum ada kode/tes/UAT 08b.

## Tujuan dan scope
Perizinan siswa dan guru. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Implementasi 08a dicatat di `reports/feature-update/PHASE-08A-REPORT.md`; 08b belum dimulai.

## Subfase

### 08a — Pengajuan izin
Status: **IMPLEMENTED — agent-verified 2026-11-02; OWNER APPROVED (#1–#16, 2026-11-02 WIB)** berdasarkan **"Pengujian nomor 1-16 sudah berhasil dan saya approved."**; paket review `91b3eeb`; agen tidak mengulang skenario. UAT `.uat-phase08` 14026/18026 dihentikan setelah penerimaan (DEC-27; PID/command line diverifikasi, port bebas, data dipertahankan). Evidence: 22 tes/209 assertions, regresi Letters 86/1.744, Pint 23 file, 13 route, build 25,44 s, ESLint 0 error, browser operator/guru/waka/kepsek.

Scope: Jenis, subjek, rentang tanggal, alasan/lampiran, actor pemohon/operator; validasi overlap sesuai kebijakan; draft/diajukan dan pembatalan.

Kriteria penerimaan: Rentang sah; pengajuan atas orang lain memerlukan izin; guru hanya permohonan sendiri; lampiran privat.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-08-LEAVES.md.
Kerjakan subfase 08a saja: Pengajuan izin sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 08b — Keputusan dan rekap
Status: **PLAN — AWAITING OWNER APPROVAL (D1–D8; proposed DEC-36 PROPOSED)** di `reports/feature-update/PHASE-08B-PLAN.md` (baseline `618b8f8`); **belum ada kode/tes/UAT**; implementasi menunggu persetujuan pemilik atas D1–D8.

Scope: Approval/rejection alasan, actor/waktu, delegasi bila disetujui; transisi atomik dan retry aman; rekap periode/kelas/pegawai.

Kriteria penerimaan: Keputusan paralel tidak bertentangan; unauthorized direct API ditolak; rekap sesuai filter; perubahan keputusan ter-audit.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-08-LEAVES.md.
Kerjakan subfase 08b saja: Keputusan dan rekap sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Tidak otomatis membuat absensi, mengurangi saldo cuti, atau mengirim pesan eksternal.

## Verifikasi minimum
Date boundary WIB, leave own/other, approval scope, overlap, replay, cancel-after-decision policy, attachment permission.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
REQUIREMENTS; ACCESS-CONTROL; DATABASE; API-CONTRACTS; DECISIONS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Aturan izin siswa dan guru berbeda; gunakan keputusan pemilik untuk approver/overlap, jangan samakan tanpa alasan.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
