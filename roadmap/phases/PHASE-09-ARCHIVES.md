# Fase 09 — Arsip dokumen terpusat

Status teknis: 09a **IMPLEMENTED — agent-verified 2026-11-02** (DEC-37 APPROVED); 09b **IMPLEMENTED — agent-verified 2026-11-03** (DEC-38 APPROVED; paket review **`51002e6`**). Status review: 09a **OWNER APPROVED (#1–#18, 2026-11-03 WIB)**; 09b **OWNER APPROVED (#1–#18, 2026-11-03 WIB)** berdasarkan pernyataan **"Pengujian nomor 1-18 sudah berhasil dan saya approved."** (`reports/feature-update/PHASE-09B-MANUAL-REVIEW.md`). Semua tes/bukti implementasi: dicatat di `PHASE-09A-REPORT.md` dan `PHASE-09B-REPORT.md`; checklist di `PHASE-09A-MANUAL-REVIEW.md` dan `PHASE-09B-MANUAL-REVIEW.md`.
Dependensi: File privat Fase07; DEC-13. Baseline rencana 09a: `aeca361` (2026-11-02; paket rencana `1dcf044`); baseline implementasi 09a: `b26fb7f`; paket review 09a: **`dfd6f85`**; penerimaan 09a: **`cad8f75`**. Baseline rencana 09b: **`cad8f75`** (paket rencana **`e89a682`**; persetujuan **`b405fe7`**; paket review **`51002e6`**).

## Status implementasi 09b (2026-11-03)

- **IMPLEMENTED dan DIVERIFIKASI AGEN 2026-11-03** di atas `main` @ baseline **`b405fe7`** (paket review **`51002e6`**), dengan otorisasi **DEC-38 APPROVED** ("Saya setuju D1-D11"; **D6 = Opsi A** tanpa eksekusi purge; **DEC-13 PARTIALLY CLOSED untuk lingkup modul arsip dokumen**, sisanya tetap OPEN untuk Fase 13). **Review manual pemilik: APPROVED (#1–#18, 2026-11-03 WIB)** berdasarkan pernyataan **"Pengujian nomor 1-18 sudah berhasil dan saya approved."** di [`reports/feature-update/PHASE-09B-MANUAL-REVIEW.md`](../../reports/feature-update/PHASE-09B-MANUAL-REVIEW.md); agen tidak mengulang skenario dan item NOT RUN tetap NOT RUN; laporan lengkap di [`reports/feature-update/PHASE-09B-REPORT.md`](../../reports/feature-update/PHASE-09B-REPORT.md).
- Disampaikan: preview inline aman `GET /api/archives/{archive}/versions/{version}/preview` (`CSP: sandbox`, `X-Document-Filename`, `nosniff`, `Cache-Control: private, no-store`, tanpa `Content-Disposition`, tanpa URL publik/watermark/dependency baru); verifikasi keberadaan file (`404`) + integritas SHA-256 (`500` tanpa byte file) pada `download` dan `preview` **sebelum** menulis log akses; audit akses append-only `archive_access_logs` untuk `download`/`preview` (tanpa mengubah enum `activities`), dibaca `GET /api/archives/{archive}/access-logs` dengan `audit.view` + `archives.view` + ability domain, fail-closed `500` bila penulisan log gagal; tugas masa berlaku terhitung `GET /api/archives/tasks/expiring` (WIB, ambang 30 hari, hanya dokumen aktif `expired`/`expiring_soon` sesuai domain) + `ArchiveExpiryWidget.vue` pada baris teratas `Dashboard.vue`; arsip/restore dokumen lewat kolom additive `archived_at`/`archive_reason`/`archived_by` + `POST /api/archives/{archive}/archive` & `/restore` (`reason` 10–255, `expected_version`/`409`), dokumen archived read-only untuk edit/add version (`422`) tetapi tetap dapat dilihat/diunduh/dipratinjau, filter `status=active|archived|all` (default `active`), `delete`/`forceDelete` tetap `false`; laporan retensi read-only `GET /api/archives/retention/report` (`archives.manage`) + CLI `php artisan app:archives-retention --dry-run` tanpa menghapus baris/file; ability `archives.purge` didefinisikan (`is_sensitive=true`) tanpa penugasan role (`0` baris di `ability_role`); total **15 route `/api/archives*`**.
- Bukti: Archives **29 tes/670 assertions PASS (26,36 s)** (`ArchiveDocumentTest` 14/374 + `ArchiveAccessRetentionTest` 15/296); regresi Letters + Leaves **116 tes/2.217 assertions PASS (136,66 s)**; Pint **21 file PASS**; **15 route**; ESLint **0 error**; `npm run build` **PASS (29,53 s)**; UAT `.uat-phase09` `prepare-09b` idempoten (**136 dokumen**: 134 aktif / 2 archived `#129`–`#130`; **177 versi / 176 file fisik** dengan `#131` hilang sengaja untuk probe `404` dan `#132` korup sengaja untuk probe `500`; **23 log akses** dengan `0` log pada `#131`/`#132`; `0` role pada `archives.purge`) + `retention-dry-run` **PASS** (2 dokumen melewati retensi: `#135` School Document dan `#136` Leave; Student Record `#134` permanen = 0 eligible).
- **NOT RUN:** otomasi browser visual/screenshot (1440×900 & 390×844) dan navigasi keyboard pada sesi ini, suite penuh PHPUnit lintas seluruh fase (`MariaDbIntegrationTest`), race MariaDB/InnoDB, dua tab nyata, dan restore drill 09b.
- Server UAT `.uat-phase09` telah dihentikan sesuai DEC-27 setelah verifikasi agen (port 18027/14027 bebas, `.uat-phase09` dipertahankan, Apache/MariaDB tidak disentuh). Perintah menyalakan kembali server UAT untuk review manual tersedia di `PHASE-09B-MANUAL-REVIEW.md`.

## Status paket rencana 09b (2026-11-03)

- Rencana rinci ada di [`reports/feature-update/PHASE-09B-PLAN.md`](../../reports/feature-update/PHASE-09B-PLAN.md) (baseline `cad8f75`; paket rencana `e89a682`) dengan status **PLAN APPROVED — DEC-38 APPROVED** berdasarkan pernyataan pemilik **"Saya setuju D1-D11"** (2026-11-03 WIB). Persetujuan mengotorisasi **implementasi 09b saja**; implementasi belum diverifikasi pada saat pencatatan persetujuan.
- **D6 disetujui sebagai Opsi A**: laporan retensi read-only `GET /api/archives/retention/report` + `app:archives-retention --dry-run`; ability baru `archives.purge` didefinisikan tetapi **tidak ditugaskan ke role mana pun**; **tidak ada eksekusi purge/pemusnahan data** dan scheduler tidak diubah. Periode retensi per kategori (student_record permanen; employee_record/letter/other 5 tahun; leave 2 tahun; school_document 10 tahun; access log 5 tahun) disimpan sebagai config dan hanya dipakai laporan.
- **DEC-13 PARTIALLY CLOSED untuk lingkup modul arsip dokumen**; sisanya (arsip siswa/alumni di luar modul arsip, lampiran surat/izin hasil soft delete, audit `activities` umum) **tetap OPEN** untuk Fase 13.
- Scope yang diotorisasi: preview inline aman `GET /api/archives/{archive}/versions/{version}/preview` (pola header 07c, MIME dari kolom tersimpan, tanpa `Content-Disposition`, tanpa URL publik, tanpa watermark/dependency baru); audit akses append-only **`archive_access_logs`** untuk `download`/`preview` (enum `activities` **tidak** diubah), dibaca `audit.view` + `archives.view` + ability domain, tanpa judul/nama file/path/checksum, **fail-closed** `500` bila penulisan log gagal; expiry task terhitung `GET /api/archives/tasks/expiring` + `ArchiveExpiryWidget.vue` (tanpa tabel/job/notifikasi push); arsip/restore dokumen lewat kolom additive `archived_at`/`archive_reason`/`archived_by` + `expected_version`/`409` (alasan 10–255), archived read-only untuk tulis tetapi tetap dapat dilihat/diunduh/dipratinjau, filter `status=active|archived|all` (default `active`), `delete`/`forceDelete` tetap `false`, versi file tetap immutable; verifikasi SHA-256 saat baca (mismatch → `500`, bytes tidak dikirim); **6 route baru (total 15 route arsip)**; tanpa dependency baru; tes T1–T20; UAT `.uat-phase09` diperluas additive (`prepare-09b`, 18027/14027, tanpa reset data 09a) dan dihentikan setelah penerimaan (DEC-27).
- Eksekusi purge/pemusnahan, penugasan `archives.purge` ke role, pengaktifan scheduler, merge/deploy/force-push, migrasi DB operasional/MariaDB, perubahan dependency/lockfile, perubahan service/`.env`/PHP/Apache, dan 09c/Fase 10+ **tidak diotorisasi**.

## Status implementasi 09a (2026-11-02)

- **IMPLEMENTED dan DIVERIFIKASI AGEN 2026-11-02** di atas `main` @ baseline `b26fb7f`; paket review **`dfd6f85`** (di-push ke `origin/main`). **Review manual pemilik APPROVED (#1–#18, 2026-11-03 WIB)** berdasarkan **"Pengujian nomor 1-18 sudah berhasil dan saya approved."**; agen tidak mengulang skenario dan item NOT RUN tetap NOT RUN; penerimaan dicatat pada commit dokumentasi terpisah.
- Disampaikan: dua tabel additive `archive_documents` + `archive_file_versions` (+ migrasi ability); kategori config 6 nilai; link subjek whitelist `student`/`employee`/`letter`/`leave` atau null (both-or-none, existence termasuk soft-deleted, immutable); nomor dokumen opsional/non-unik; `valid_from`/`valid_until` berpasangan + status validitas dihitung (WIB, ambang 30 hari, upcoming diprioritaskan); versi file immutable + sha256 internal, PDF/JPG/JPEG/PNG 10 MB di disk privat `local` (`attachments/archives/{id}/{uuid}.{ext}`), kompensasi + rollback; download berizin current/versi lama dengan pola header 07c/08a (tanpa `Content-Disposition`); `archives.view`/`archives.manage` (admin/operator manage, kepala/waka view, guru none) + ability domain untuk dokumen bertaut; list/search menyaring domain, filter eksplisit tanpa izin `403`, objek hidden `404`; `expected_version`/`409`; **9 route**; halaman `/public/archives`; audit allowlist tanpa title/nomor/deskripsi/nama file/path/checksum; UAT `.uat-phase09` 18027/14027.
- Bukti: Archives **14 tes/374 assertions (11,81 s)**; suite penuh **597 passed/13 skipped/7.083 assertions (438,24 s)**; Pint **18 file**; **9 route**; build **PASS**; ESLint **0 error** (613 warning Prettier lama); UAT `prepare-archives` idempoten 120 dokumen/160 versi/160 file, status akhir **121 dokumen/162 versi/162 file**, 7 akun; browser/API admin create+versi+edit+search/filter, archivist hanya dokumen umum, kepsek view-only, guru `403`.
- **NOT RUN:** viewport 390 px/keyboard, race MariaDB/InnoDB, dua tab nyata, restore drill, visual regression, benchmark, create picker domain di UI, alur waka, seluruh 09b.
- Server UAT dihentikan **sebelum** review (DEC-27) dan diverifikasi ulang setelah penerimaan: PID/command line diverifikasi, port 18027/14027 bebas, `.uat-phase09` dipertahankan; Apache/MariaDB tidak disentuh. Perintah restart ada di `PHASE-09A-MANUAL-REVIEW.md`.
- **09b** (preview inline, audit akses download, expiry task, arsip/restore, retensi/purge) memerlukan rencana dan persetujuan terpisah — **rencananya DISETUJUI sebagai DEC-38 (2026-11-03 WIB)** dan implementasinya sedang dikerjakan (lihat bagian di atas); **DEC-13 PARTIALLY CLOSED untuk lingkup arsip**. Merge/deploy/force-push, migrasi DB operasional/MariaDB, dependency/lockfile, dan service/`.env` tidak diotorisasi.

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
