# Fase 04 — Guru dan pegawai

Status teknis: SUBPHASE 04a IMPLEMENTED & FULLY VERIFIED (AGEN); **DITERIMA PEMILIK (checklist #1–#13 APPROVED 2026-10-29 WIB)**. Subfase 04b: **IMPLEMENTED dengan bukti agen terbatas**; **DITERIMA PEMILIK (checklist #1–#18 APPROVED 2026-10-30 WIB per pernyataan pemilik)**.
Dependensi: 01; 02; DEC-04 (APPROVED 2026-10-26 WIB); DEC-24 (APPROVED 2026-10-29 WIB).

## Correction addendum — 2026-10-29

- Baseline `main` at `d10f7b8` plus uncommitted implementation/documentation corrections; no owner acceptance for 04b, no correction commit/push.
- Current owner requirements: full-English presentation; Teachers = Guru only, canonical `Pendidik`; Employees = non-teaching staff, canonical `Tenaga Kependidikan`; >=100 synthetic rows for each empty new UAT feature. Only `employee_type_id` determines classification; canonical jobs, including head-of-school positions, remain unchanged without job-title restrictions. Subject required for Teachers, prohibited/hidden for Employees.
- Shared 17-domain/243-label JSON and frontend-only `display_name` enrichment preserve canonical names/IDs and proper/user-entered data; English CSV/validation/print/Profile/navigation. Passwords stripped before cache enrichment; route-view key prevents stale rows/dialogs when switching directories.
- New isolated SQLite SPA/API `127.0.0.1:14022` / `127.0.0.1:18022`; root `.uat-phase04b` private environment/storage/database, cookie `school_phase04b_session`. Use only guarded `scripts/phase04b-uat.php` (`init`/`prepare`/`status`), normal migrations; never root `artisan --env=uat`. Main/legacy environments, MariaDB targets, services and dependencies are excluded. Additive 04a/04b migrations are now applied **only to this new UAT**, not old UAT/production.
- Fixtures: baseline 100 teachers with subjects + 100 staff without subjects across 15 jobs; current 101 each after browser creates, 103 students, 100 synthetic schools (NPSN `04` namespace). Idempotent preparation preserves edits/archives and only initializes five UAT accounts; no extra employee accounts. Runtime and `UAT-ACCOUNTS.local.md` remain ignored/private.
- Privacy correction: list/export include name/NUPTK/gender; list includes account linkage metadata, not zero PII/no account data. Sensitive fields are manage-detail only. Archived GET detail uses `withTrashed()` and view + manage (403 view-only); unresolved list names remain null/blank; CSV header `Employment Start Date (TMT)`.
- Current evidence is scoped in `PHASE-04B-REPORT.md`: latest explicit SQLite **96 tests/1738 assertions PASS**, final **42 frontend tests PASS**, final **build PASS, 18.77 s**, targeted ESLint 0 errors and Pint PASS. Earlier 98/1777, CRUD 17/117, seeder 5/219, launcher 3/372 and separate translation 119/1223 remain separately recorded, never aggregated or claimed as a final full suite.
- Operator browser DOM/API readback verified teacher create/edit/stale 409/input preservation/Reload Latest/archive/detail/restore/link/unlink, staff create without Subject, correct route types, English CSV and mobile staff DOM fit/scroll. Separate three-role HTTP permission checks PASS, not other-role browser UI. User reported checklist #1–#18 passed and approved on 2026-10-30 WIB; this is the owner's statement, not an agent rerun. Agent-side other-role browser UI automation, two-tab stale/link-conflict and print-preview/screenshot visual checks remain **NOT RUN**. 04b MariaDB DDL/InnoDB **NOT RUN** (available, excluded targets not accessed). Initial evidence below remains dated history.

## Tujuan dan scope
Guru dan pegawai. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis.

## Subfase

### 04a — Schema profil dan hubungan akun
Status: **DITERIMA PEMILIK (checklist #1–#13 APPROVED 2026-10-29 WIB; sumber bukti pernyataan pemilik)** (DEC-04 APPROVED 2026-10-26 WIB; checklist di `reports/feature-update/PHASE-04A-MANUAL-REVIEW.md`). Additive migrations applied to the new isolated SQLite UAT only; old UAT/production unchanged.
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
Status: **IMPLEMENTED dan DITERIMA PEMILIK — checklist #1–#18 APPROVED 2026-10-30 WIB per pernyataan pemilik** (DEC-24 D1–D8 APPROVED 2026-10-29 WIB; report `reports/feature-update/PHASE-04B-REPORT.md`; `PHASE-04B-MANUAL-REVIEW.md`). Migration `2029_07_26_000001` applied to new dedicated SQLite UAT only; old UAT/production unchanged. **Initial evidence (2026-10-29, historical):** 56 test Employee/331 assertions, 293 test penuh/1448 assertions, 7 unit frontend, build PASS, Pint PASS; MariaDB & browser UAT NOT RUN. Current separate correction runs, including latest SQLite 96/1738, frontend 42/build PASS and scoped operator browser/three-role HTTP results, are in the addendum. Agent-side other-role browser UI automation, two-tab stale/link-conflict, print-preview/screenshot visual checks and 04b MariaDB remain NOT RUN; owner acceptance does not convert them to PASS.
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
