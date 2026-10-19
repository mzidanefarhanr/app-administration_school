# Fase 02 — Audit terpercaya dan backup yang dapat dipulihkan

Status teknis: 02a IMPLEMENTED & DITERIMA PEMILIK; 02b-02c NOT STARTED.
Status review: Subfase 02a APPROVED (19 Oktober 2026 WIB mengacu baseline cf94cae); Fase 02 keseluruhan IN PROGRESS.
Dependensi: 01; DEC-02, DEC-09, dan DEC-17 (APPROVED 2026-10-17). Laporan subfase: PHASE-02A-AUDIT.md.

## Tujuan dan scope
Audit terpercaya dan backup yang dapat dipulihkan. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Subfase 02a telah diimplementasikan dan DITERIMA PEMILIK; 02b dan 02c menunggu otorisasi terpisah.

## Subfase

### 02a — Audit server dan rollback terbatas
Status: **DITERIMA PEMILIK** (Review Pemilik: APPROVED pada 19 Oktober 2026 WIB, checklist #1–#9 di PHASE-02A-MANUAL-REVIEW.md).
Scope selesai: Audit allowlist per model (User, Role, Ability, Student, Master); rahasia tersaring; penutupan endpoint klien POST /api/activities dan PUT/PATCH/DELETE /api/activities/{id} (HTTP 405); rollback dinonaktifkan (DEC-17); model Activity dilindungi immutability (updating/deleting throw RuntimeException); audit pembacaan dilindungi kemampuan bernama audit.view; audit mutasi pivot role_user dan ability_role; audit bootstrap admin ber-actor system:cli; reentrancy guard dan konsistensi transaksi terbukti (110 test backend PASS). Batas cakupan: perlindungan pada level API/ORM, bulk deletion per-record atomic di klien, modul scaffold tetap N/A.

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
