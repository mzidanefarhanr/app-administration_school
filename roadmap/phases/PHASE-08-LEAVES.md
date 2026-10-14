# Fase 08 — Perizinan siswa dan guru

Status teknis: NOT STARTED. Status review: PENDING. Semua tes/browser/manual: NOT RUN.
Dependensi: 01,03,04; file privat Fase07a atau service ekuivalen; DEC-07. Baseline rencana: b02a87f; audit ulang HEAD saat pelaksanaan.

## Tujuan dan scope
Perizinan siswa dan guru. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 08a — Pengajuan izin
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
