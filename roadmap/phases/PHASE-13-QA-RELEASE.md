# Fase 13 — QA akhir, rilis dan handoff operasional

Status teknis: NOT STARTED. Status review: PENDING. Semua tes/browser/manual: NOT RUN.
Dependensi: Fase yang dipilih selesai; keputusan kritis ditutup. Baseline rencana: b02a87f; audit ulang HEAD saat pelaksanaan.

## Tujuan dan scope
QA akhir, rilis dan handoff operasional. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 13a — QA integrasi dan UAT
Scope: Inventory semua endpoint/halaman aktif; full role matrix; end-to-end siswa→rombel→surat→arsip, guru→jadwal→jurnal, izin dan backup. Catat open issues dan scope release nyata.

Kriteria penerimaan: Suite dan UAT sesuai hasil; semua NOT RUN/FAIL/BLOCKED eksplisit; approval manual tidak melampaui checklist.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-13-QA-RELEASE.md.
Kerjakan subfase 13a saja: QA integrasi dan UAT sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 13b — Release readiness
Scope: Restore drill baru pada kandidat release, backup lengkap setelah modul file berkembang, cek manifest/schema, env/HTTPS/worker/scheduler/storage, migration dan rollback plan; dokumentasi operator.

Kriteria penerimaan: Backup terbaru mencakup seluruh domain/file; smoke test dan rencana rollback tersedia; tidak ada blocker kritis dalam scope release.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-13-QA-RELEASE.md.
Kerjakan subfase 13b saja: Release readiness sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 13c — Rilis bila diotorisasi
Scope: Siapkan kandidat/reviewable deployment instructions; commit/push/merge/deploy hanya mengikuti instruksi pemilik. Setelah deploy sah, smoke test dan handoff versi, known issues, operasi backup.

Kriteria penerimaan: SHA/versi terdeploy aktual dan hasil smoke tercatat. Jika deploy tidak diotorisasi, selesai pada READY FOR REVIEW, bukan DEPLOYED.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-13-QA-RELEASE.md.
Kerjakan subfase 13c saja: Rilis bila diotorisasi sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Tidak menandai semua roadmap selesai bila pemilik memilih release parsial. Tidak restore DB aktif untuk uji.

## Verifikasi minimum
Integration, direct API role, migration on copy, full DB+file restore, light/dark/mobile/PDF, deployment smoke bila sah.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
PROJECT_CONTEXT; ROADMAP; OPERATIONS; seluruh kontrak affected; INDEX; HANDOFF; CHANGELOG; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Kesiapan produksi memerlukan bukti dan target operasi; dokumentasi rencana bukan sertifikasi atau jaminan keamanan.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
