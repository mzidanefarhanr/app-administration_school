# Fase 03 — Pendataan siswa lengkap dan aman

Status teknis: **IMPLEMENTED & FULLY VERIFIED** (03a, 03b, 03c selesai; 229 test backend PASS, 18 unit test frontend PASS, build PASS, Pint PASS, browser UAT agen & smoke PASS).
Status review: **DITERIMA LENGKAP & DITUTUP** (03a APPROVED 2026-10-21; 03b APPROVED 2026-10-21; 03c APPROVED 2026-10-26 WIB).
Dependensi: 01; 02; DEC-05; DEC-20 (APPROVED 2026-10-20 WIB); DEC-21 (APPROVED 2026-10-21 WIB); DEC-22 (APPROVED 2026-10-21 WIB).
Laporan subfase: `PHASE-03A-STUDENT-IDENTITY.md`, `PHASE-03B-STUDENT-CRUD.md`, `PHASE-03C-STUDENT-DIRECTORY.md`.

## Tujuan dan scope
Pendataan siswa lengkap dan aman. Seluruh subfase yang diotorisasi (03a, 03b, 03c) telah selesai dan DITERIMA LENGKAP OLEH PEMILIK. Fase 03 resmi DITUTUP. Subfase kandidat 03d (aksi penempatan rombel via UI) tetap BELUM DIOTORISASI.

## Subfase

### 03a — Identitas dan membership migration
Status: **DITERIMA PEMILIK** (Review Pemilik: APPROVED pada 21 Oktober 2026 WIB, checklist #1–#11 di `reports/feature-update/PHASE-03A-MANUAL-REVIEW.md` mengacu baseline `37f7d79`; bukti konkurensi diakui via automated MariaDB integration test).
Laporan: `reports/feature-update/PHASE-03A-STUDENT-IDENTITY.md`.
Scope selesai: Kolom `student_id` additive pada `student_rombels` (FK restrictOnDelete, cascadeOnUpdate); `student_nik` nullable; backfill aman kecocokan NIK tunggal tanpa membocorkan PII (`StudentBackfillService` dan `app:student-backfill`); penegakan aturan maksimal 1 rombel aktif per siswa per tahun ajaran via database trigger MariaDB (`SQLSTATE 45000`) dengan InnoDB row lock dan SQLite (`RAISE(ABORT)`) serta model event; umur dihitung dinamis dari `birthdate` berbasis `Asia/Jakarta`; trait `Loggable` aktif di `StudentRombel` dengan allowlist audit yang mengecualikan data medis dan penghasilan orang tua; adaptasi jalur ekspor dan pembacaan dengan `resolveStudent()`. (29 backend test PASS).

Kriteria penerimaan: Siswa tanpa NIK tetap dapat dicatat dan diberi membership; NIK diperbaiki tidak memutus relasi; backfill count dan exception terbukti; unique constraint sesuai kebijakan.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-03-STUDENTS.md.
Kerjakan subfase 03a saja: Identitas dan membership migration sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 03b — CRUD dan form siswa
Status: **DITERIMA PEMILIK** (Review Pemilik: APPROVED pada 21 Oktober 2026 WIB, checklist #1–#13 di `reports/feature-update/PHASE-03B-MANUAL-REVIEW.md` mengacu baseline `cbd25bd`; backend `e77f335` + UI `cbd25bd`).
Laporan: `reports/feature-update/PHASE-03B-STUDENT-CRUD.md`.
Scope selesai: Implementasi store/show/update/arsip/restore dengan FormRequest sah, validator server, optimistic concurrency locking 409 atomik (`version`), soft delete & penonaktifan rombel atomik, restore dengan pemeriksaan konflik identitas aktif, audit privacy (strip data medis/finansial/alasan arsip), dan UI English lengkap di `Student.vue`. (55 Student test PASS; 209 backend full PASS; 15 unit frontend PASS; build PASS; browser UAT agen PASS).

### 03c — Daftar dan rekap siswa
Status: **DITERIMA PEMILIK** (Review Pemilik: APPROVED pada 2026-10-26 WIB, checklist #1–#23 di `reports/feature-update/PHASE-03C-MANUAL-REVIEW.md`; baseline fitur `e15f028`, baseline lingkungan `933e71c`).
Laporan: `reports/feature-update/PHASE-03C-STUDENT-DIRECTORY.md`.
Scope selesai: Pagination/search/sort server-side via `StudentDirectory` dan `StudentController::index`; filter All/Placed/Unplaced + rombel + school_year_id; rekap server anti double-count; ekspor CSV terfilter terproteksi yang selalu menyertakan `placement` (`StudentExportController` + `studentExportParams.mjs`); penutupan kebocoran `only_archived` (wajib `students.manage`); DataTable lazy, debounce search, stale response guard (`listRequestId`), error retry, pemulihan halaman terakhir kosong di `Student.vue`. (229 backend test PASS; 18 unit frontend PASS; build PASS; Pint PASS; UAT browser & smoke PASS).

### 03d (Tambahan) — Aksi penempatan dan perpindahan rombel via UI
Status: **DITERIMA PEMILIK** (Review Pemilik: APPROVED pada 2026-10-26 WIB, checklist #1–#15 di `reports/feature-update/PHASE-03D-MANUAL-REVIEW.md`; baseline implementasi `7bed397`, penguatan bukti uji `f6be20f`).
Laporan: `reports/feature-update/PHASE-03D-STUDENT-PLACEMENT.md`.
Scope selesai: Endpoint `assign-rombel` & `transfer-rombel`, kontrol konkurensi atomik row lock InnoDB (`version`), validasi tahun terpilih & tanggal server Asia/Jakarta, preservasi keanggotaan keluar (status tetap 1), UI English `Student.vue` (dialog `placementDialog`, tombol aksi terproteksi `can:students.manage`, dialog konflik 409). (237 SQLite test PASS; 7 MariaDB integration/concurrency test PASS; 18 frontend test PASS; build PASS; Pint PASS; UAT smoke PASS).

## Batas pekerjaan
Belum membangun kenaikan kelas massal, portal siswa/orang tua, atau sinkronisasi pemerintah.

## Verifikasi minimum
CRUD lengkap, null identity, duplikat termasuk soft delete, version race, FK scope, pagination, chart dan audit dengan fixture sintetis.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
DATABASE; API-CONTRACTS; REQUIREMENTS; ACCESS-CONTROL; desain form; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Peralihan FK NIK additive; jangan drop kolom lama sebelum seluruh consumer dipindahkan. Tentukan restore vs duplicate sebelum implementasi.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
