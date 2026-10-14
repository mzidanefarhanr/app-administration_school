# Fase 02 — Audit terpercaya dan backup yang dapat dipulihkan

Status teknis: NOT STARTED. Status review: PENDING. Semua tes/browser/manual: NOT RUN.
Dependensi: 01; DEC-02 dan DEC-09 untuk backup. Baseline rencana: b02a87f; audit ulang HEAD saat pelaksanaan.

## Tujuan dan scope
Audit terpercaya dan backup yang dapat dipulihkan. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 02a — Audit server dan rollback terbatas
Scope: Audit whitelist per model; tidak log password/hash/token; hilangkan kemampuan memalsukan log; coverage siswa/membership disiapkan saat CRUD ada. Perbaiki target rollback DB::table($modelName), whitelist field/domain, version check, transaksi, linkage dan replay. Jika rollback belum aman, endpoint diblokir sesuai izin dan dicatat deferred, bukan tombol sukses palsu.

Kriteria penerimaan: Create/update/delete actor dan before/after benar, secrets absent; log tidak dapat dibuat pengguna bebas; rollback stale/ulang/di luar domain ditolak; perubahan terbaru dan record decoy utuh.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-02-BACKUP-AUDIT.md.
Kerjakan subfase 02a saja: Audit server dan rollback terbatas sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 02b — Backup manual dan restore drill
Scope: Implementasi DB+lampiran+manifest/checksum serta private storage; akses eksplisit; credential dump aman; status nyata; desain retensi dan salinan terpisah sesuai deployment. Uji restore ke target disposable.

Kriteria penerimaan: Backup sintetis lengkap; checksum/manifest diverifikasi; restore DB+file berjalan pada target terisolasi; disk penuh/missing file/target aktif ditolak atau gagal terlihat.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-02-BACKUP-AUDIT.md.
Kerjakan subfase 02b saja: Backup manual dan restore drill sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 02c — Jadwal dan monitoring backup
Scope: Aktifkan scheduler/job sesuai lingkungan yang disepakati; retry/idempotency; retention tidak menghapus backup masih digunakan. Runbook operasi dan hasil drill.

Kriteria penerimaan: Scheduler/worker yang dipakai terbukti memicu job; retry tidak menggandakan efek; backup gagal terlihat; jadwal/retensi disetujui. Jika environment belum tersedia, catat BLOCKED tanpa klaim otomatis aktif.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-02-BACKUP-AUDIT.md.
Kerjakan subfase 02c saja: Jadwal dan monitoring backup sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Backup operasional didahulukan sebelum data sekolah aktif; restore aktif dan rewrite history remote bukan bagian uji fase.

## Verifikasi minimum
Transaksi gagal setelah write, audit rahasia, replay rollback, akses backup/download, manifest rusak, restore terisolasi dan salinan backup.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
OPERATIONS; SECURITY-DATA; DATABASE; API-CONTRACTS; laporan restore drill; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Rollback log bukan pengganti backup. Tabel audit baseline cascade ketika user hard-delete; kebijakan actor historis perlu dijaga.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
