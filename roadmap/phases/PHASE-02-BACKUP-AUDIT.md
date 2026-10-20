# Fase 02 — Audit terpercaya dan backup yang dapat dipulihkan

Status teknis: IMPLEMENTED & FULLY VERIFIED (02a, 02b, 02c selesai; 154 backend tests PASS).
Status review: DITERIMA LENGKAP & DITUTUP (02a APPROVED 2026-10-19, 02b APPROVED 2026-10-20, 02c APPROVED 2026-10-20 WIB mengacu baseline 94d29f4).
Dependensi: 01; DEC-02, DEC-09, DEC-17, DEC-18, dan DEC-19. Laporan subfase: PHASE-02A-AUDIT.md, PHASE-02B-BACKUP-RESTORE.md, PHASE-02C-MONITORING-SCHEDULE.md.

## Tujuan dan scope
Audit terpercaya dan backup yang dapat dipulihkan. Subfase 02a, 02b, dan 02c telah diimplementasikan dan DITERIMA LENGKAP OLEH PEMILIK; Fase 02 resmi DITUTUP.

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
Status: **DITERIMA PEMILIK** (Review Pemilik: APPROVED pada 20 Oktober 2026 WIB, checklist #1–#10 di PHASE-02B-MANUAL-REVIEW.md mengacu baseline e8eaea4).
Laporan: `reports/feature-update/PHASE-02B-BACKUP-RESTORE.md`.
Scope selesai: Backup database (mysqldump / sqlite) + lampiran privat ke storage privat terenkripsi ZIP AES-256 via secret prompt; manifest SHA-256 dan companion sha256; atomic publishing; download berizin backup.manage dan anti-traversal; restore drill multi-faktor ke target disposable (school_restore_drill_uat) dengan fail-safe keras anti-operasional, penolakan target berisi data, anti-Zip-Slip, dan audit SQL dump. Uji integrasi nyata MariaDB lokal PASS (132 test backend PASS). Batas cakupan: dibuktikan dengan data sintetis, konsistensi lampiran operasional N/A, deteksi deviasi bukan snapshot atomik DB-berkas.

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
Status: **DITERIMA PEMILIK** (Review Pemilik: APPROVED pada 20 Oktober 2026 WIB, checklist #1–#10 di `reports/feature-update/PHASE-02C-MANUAL-REVIEW.md` mengacu baseline `94d29f4`; butir #10 diterima atas bukti automated `BackupMonitoringTest` waktu virtual).
Laporan: `reports/feature-update/PHASE-02C-MONITORING-SCHEDULE.md`.
Scope selesai: Backup non-interaktif (`--non-interactive`) dengan rahasia lokal tanpa prompt; scheduler Laravel opt-in pada 02:00 dan 02:30 Asia/Jakarta dengan default OFF; mutex lock atomik bersama (`BackupMutex`) pada file lock `.backup.lock` untuk mencegah overlap; algoritma retensi bertingkat 7 harian + 4 mingguan dengan preservasi cadangan terakhir sukses dan mode `--dry-run`; monitoring status pasif terstruktur di `monitoring.json`, freshness alert dinamis (> 28 jam), pembedaan tegas `backup_created` vs `restore_verified`, dan endpoint API `GET /api/backups/status` berizin `backup.manage`. Runbook operasi Windows Task Scheduler dan Linux Cron. Batas cakupan: monitoring pasif tanpa notifikasi eksternal, single-server mutex, lampiran operasional N/A. (154 test backend PASS).

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
- [x] Scope selesai atau partial/blocker dijelaskan.
- [x] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [x] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [x] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [x] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [x] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
