# Fase 04 — Guru dan pegawai

Status teknis: SUBPHASE 04a IMPLEMENTED & FULLY VERIFIED (AGEN); **DITERIMA PEMILIK (checklist #1–#13 APPROVED 2026-10-29 WIB)**. Subfase 04b: **IMPLEMENTED & DIVERIFIKASI OTOMATIS (AGEN, 2026-10-29)**; penerimaan manual **PENDING**.
Dependensi: 01; 02; DEC-04 (APPROVED 2026-10-26 WIB); DEC-24 (APPROVED 2026-10-29 WIB).

## Tujuan dan scope
Guru dan pegawai. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis.

## Subfase

### 04a — Schema profil dan hubungan akun
Status: **DITERIMA PEMILIK (checklist #1–#13 APPROVED 2026-10-29 WIB; sumber bukti pernyataan pemilik)** (DEC-04 APPROVED 2026-10-26 WIB; checklist di `reports/feature-update/PHASE-04A-MANUAL-REVIEW.md`). Migration additive belum diterapkan ke UAT.
Laporan: `reports/feature-update/PHASE-04A-EMPLOYEE-SCHEMA.md`.
Scope selesai: Migration additive `2029_07_24_000001` (`user_id` nullable unique nullOnDelete, `version` default 1, `user_nik` & `subject_id` nullable); model `Employee` (`user_id` relasi primer, `user_nik` fallback aman legacy, koreksi typo `religion()` + alias `relogion()`, allowlist audit privacy minimalkan PII); `EmployeePolicy` penegakan `employees.view` dan `employees.manage`; `EmployeeBackfillService` idempotent berbasis NIK unik tanpa leak PII. (15 SQLite test PASS, 5 MariaDB integration test PASS, Pint 100% PASS).

Kriteria penerimaan: Profil pegawai tanpa mata pelajaran tetap sah sesuai kebijakan; akun/login terpisah dari kepegawaian; backfill tidak kehilangan histori.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-04-EMPLOYEES.md.
Kerjakan subfase 04a saja: Schema profil dan hubungan akun sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 04b — CRUD dan menu aktif
Status: **IMPLEMENTED & DIVERIFIKASI OTOMATIS (AGEN, 2026-10-29)** (DEC-24 D1–D8 APPROVED 2026-10-29 WIB; laporan `reports/feature-update/PHASE-04B-REPORT.md`; checklist `PHASE-04B-MANUAL-REVIEW.md` **PENDING**). Migration `2029_07_26_000001` belum diterapkan ke UAT. Bukti: 56 test Employee/331 assertions, 293 test penuh/1448 assertions, 7 unit frontend, build PASS, Pint PASS; MariaDB & browser UAT NOT RUN.
Scope selesai: Migration additive `employees.name`/`nik` (unique)/`archive_reason` + index dan backfill nama; `EmployeeDirectory`/`EmployeeResource`; CRUD `/api/employees` berversi (`409` + `current_version`), arsip beralasan + restore, kandidat/link/unlink akun tanpa perubahan role, ekspor CSV terfilter berizin ganda (batas 10.000 → `422`), gating PII; route `/public/teachers` + `/public/employees` dengan satu komponen direktori dan menu aktif.

Kriteria penerimaan: Aksi tersimpan, jenis pegawai terfilter benar, profil sendiri terbatas, download/export sesuai izin, nama sama dan data opsional valid.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-04-EMPLOYEES.md.
Kerjakan subfase 04b saja: CRUD dan menu aktif sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Multi-penugasan mengajar detail dikerjakan Fase10; tidak menambahkan payroll/absensi pegawai.

## Verifikasi minimum
Role/own/other, invalid FK, status keluar, soft-delete relasi, audit dan data pribadi; tabel dan form panjang mobile.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
DATABASE; REQUIREMENTS; ACCESS-CONTROL; API-CONTRACTS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Employee baseline subject tunggal dan relasi akun by NIK; perubahan harus menjaga data existing.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
