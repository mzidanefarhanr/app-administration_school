# Fase 09 — Arsip dokumen terpusat

Status teknis: 09a **IMPLEMENTED — agent-verified 2026-11-02** (DEC-37 APPROVED); 09b **NOT STARTED**. Status review: **OWNER APPROVED (#1–#18, 2026-11-03 WIB)**. Semua tes/browser/manual implementasi: dicatat di `PHASE-09A-REPORT.md`; checklist di `PHASE-09A-MANUAL-REVIEW.md`.
Dependensi: File privat Fase07; DEC-13. Baseline rencana 09a: `aeca361` (2026-11-02; paket rencana `1dcf044`); baseline implementasi: `b26fb7f`; paket review: **`dfd6f85`**.

## Status implementasi 09a (2026-11-02)

- **IMPLEMENTED dan DIVERIFIKASI AGEN 2026-11-02** di atas `main` @ baseline `b26fb7f`; paket review **`dfd6f85`** (di-push ke `origin/main`). **Review manual pemilik APPROVED (#1–#18, 2026-11-03 WIB)** berdasarkan **"Pengujian nomor 1-18 sudah berhasil dan saya approved."**; agen tidak mengulang skenario dan item NOT RUN tetap NOT RUN; penerimaan dicatat pada commit dokumentasi terpisah.
- Disampaikan: dua tabel additive `archive_documents` + `archive_file_versions` (+ migrasi ability); kategori config 6 nilai; link subjek whitelist `student`/`employee`/`letter`/`leave` atau null (both-or-none, existence termasuk soft-deleted, immutable); nomor dokumen opsional/non-unik; `valid_from`/`valid_until` berpasangan + status validitas dihitung (WIB, ambang 30 hari, upcoming diprioritaskan); versi file immutable + sha256 internal, PDF/JPG/JPEG/PNG 10 MB di disk privat `local` (`attachments/archives/{id}/{uuid}.{ext}`), kompensasi + rollback; download berizin current/versi lama dengan pola header 07c/08a (tanpa `Content-Disposition`); `archives.view`/`archives.manage` (admin/operator manage, kepala/waka view, guru none) + ability domain untuk dokumen bertaut; list/search menyaring domain, filter eksplisit tanpa izin `403`, objek hidden `404`; `expected_version`/`409`; **9 route**; halaman `/public/archives`; audit allowlist tanpa title/nomor/deskripsi/nama file/path/checksum; UAT `.uat-phase09` 18027/14027.
- Bukti: Archives **14 tes/374 assertions (11,81 s)**; suite penuh **597 passed/13 skipped/7.083 assertions (438,24 s)**; Pint **18 file**; **9 route**; build **PASS**; ESLint **0 error** (613 warning Prettier lama); UAT `prepare-archives` idempoten 120 dokumen/160 versi/160 file, status akhir **121 dokumen/162 versi/162 file**, 7 akun; browser/API admin create+versi+edit+search/filter, archivist hanya dokumen umum, kepsek view-only, guru `403`.
- **NOT RUN:** viewport 390 px/keyboard, race MariaDB/InnoDB, dua tab nyata, restore drill, visual regression, benchmark, create picker domain di UI, alur waka, seluruh 09b.
- Server UAT dihentikan **sebelum** review (DEC-27) dan diverifikasi ulang setelah penerimaan: PID/command line diverifikasi, port 18027/14027 bebas, `.uat-phase09` dipertahankan; Apache/MariaDB tidak disentuh. Perintah restart ada di `PHASE-09A-MANUAL-REVIEW.md`.
- **09b** (preview inline, audit akses download, expiry task, arsip/restore, retensi/purge) memerlukan rencana dan persetujuan terpisah; **DEC-13 OPEN** adalah gate retensi/purge. Merge/deploy/force-push, migrasi DB operasional/MariaDB, dependency/lockfile, dan service/`.env` tidak diotorisasi.

## Status paket rencana 09a (2026-11-02)

- Rencana rinci ada di [`reports/feature-update/PHASE-09-PLAN.md`](../../reports/feature-update/PHASE-09-PLAN.md), dengan status **PLAN APPROVED — DEC-37 APPROVED** berdasarkan pernyataan pemilik **"Saya setuju D1-D10"** (2026-11-02 WIB).
- Persetujuan mengotorisasi **implementasi 09a saja**: metadata, kategori, link domain, versi file/checksum, pencarian lintas domain berizin, dan download berizin. Implementasi kemudian diselesaikan dan diverifikasi (lihat bagian di atas).
- **09b tetap memerlukan rencana dan persetujuan terpisah**; **DEC-13 OPEN** menjadi gate retensi/purge. Preview inline, audit akses download, expiry task, arsip/restore, dan retensi tetap di 09b.
- Merge/deploy/force-push, migrasi DB operasional/MariaDB, dependency/lockfile, dan perubahan service/`.env` tidak diotorisasi.

## Tujuan dan scope
Arsip dokumen terpusat. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis.

## Subfase

### 09a — Metadata dan versi
Scope: Kategori, objek siswa/pegawai/surat/izin, nomor/tanggal/masa berlaku, file version/checksum; pencarian lintas domain hanya objek berizin.

Kriteria penerimaan: Upload gagal tidak meninggalkan orphan; versi lama tetap dapat ditelusuri; link type/id arbitrary ditolak.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-09-ARCHIVES.md.
Kerjakan subfase 09a saja: Metadata dan versi sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 09b — Retensi, akses dan pengingat
Scope: Download/preview aman, audit akses sesuai kebutuhan, expiry task di dashboard, arsip/restore, retensi/pemusnahan menurut keputusan.

Kriteria penerimaan: File private tidak terbuka via direct public URL; izin lintas objek benar; expired tampil sebagai tugas; tidak auto-purge data sebelum kebijakan.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-09-ARCHIVES.md.
Kerjakan subfase 09b saja: Retensi, akses dan pengingat sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Tidak memindahkan semua lampiran existing secara destruktif atau membuat OCR/fulltext sebelum dibutuhkan.

## Verifikasi minimum
MIME/extension/size, path traversal, version/history, own/other, failure compensation dan retention boundary.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
DATABASE; SECURITY-DATA; OPERATIONS; API-CONTRACTS; DECISIONS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Metadata dan file bytes harus konsisten; link berizin tidak cukup jika storage publik masih dapat diakses langsung.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
