# Handoff Pengembangan Aplikasi Sekolah

## Current checkpoint — 2026-10-31 (Subfase 06a owner-accepted)

- **Status:** 06a (periode akademik aktif, lock tahun ajaran, CRUD kelas, wali kelas dari profil pegawai, penegakan lock pada penempatan) is **IMPLEMENTED, agent-verified, and OWNER APPROVED 2026-10-31 WIB (#1–#12)** on `main` above `25875c9`; implementation package `d8d9874`.
- **Acceptance:** owner statement 2026-10-31 WIB **"Pengujian nomor 1-12 sudah berhasil dan saya approved."** covers checklist #1–#12 in `PHASE-06A-MANUAL-REVIEW.md`. That statement is the sole acceptance evidence; the agent did not rerun scenarios, and agent NOT RUN items remain NOT RUN. Acceptance does not authorize merge, deploy, operational/MariaDB migration, dependency/service changes, or 06b/06c.
- **Authorization:** DEC-29 APPROVED 2026-10-31 WIB — owner statement **"Saya setuju D1-D8 dilakukan."** — authorized 06a only.
- **Implemented:** singleton `academic_settings` with no guessed initial period; `school_years.start_date`/`end_date` + per-year lock (`locked_at`, `locked_by`, `lock_reason`) with mandatory reason and audit; active year cannot be locked/deleted and locked year cannot be active/deleted; class CRUD with case-insensitive name uniqueness per year, required `expected_version` + `409` with `current_version`/fresh data, delete blocked by any membership history including soft-deleted, structural fields immutable once history exists; homeroom teacher from active `Pendidik` employee profiles (`class_teach_employee_id`) while retaining legacy `class_teach_id`; assign/transfer 03d lock the year row first and reject locked/soft-deleted years and classes; School Years/Classes/Students UI + **Academic** menu; route Classes aligned to `students.view`.
- **Agent evidence (actual, 2026-10-31):** academic tests **20 passed/145 assertions**; 03d placement regression **27 passed/104 assertions**; full suite **437 passed / 13 skipped / 3.971 assertions** (skips are MariaDB integration without password); Pint **22 files PASS**; `npm run build` **PASS 806 modules/21.55 s**; `npx eslint --quiet` **exit 0** (non-quiet warnings are environmental Prettier/CRLF). UAT `.uat-phase06`: 133 students (130 `UAT-P06`), 12 classes, 10 active teachers + 1 separated, 4 staff, active period 2026/2027 · Ganjil; browser DOM + API checks #1–#12 passed.
- **UAT:** isolated `.uat-phase06` SQLite (SPA 14024 / API 18024, cookie `school_phase06_session`, launcher `scripts/phase06-uat.php`). Servers were **stopped on 2026-10-31** after acceptance per DEC-27 (PIDs 19240/2388 verified, ports free, Apache/MariaDB untouched); `.uat-phase06` data/storage is preserved and restart commands are in the manual review.
- **NOT RUN (agent):** MariaDB/InnoDB migration/backfill and lock concurrency, multi-process race between lock and assign/transfer, real two-tab `409`, screenshot/visual regression, semester CRUD, unique DB index for class names (deferred by D6), 06b/06c. These remain NOT RUN regardless of owner acceptance.
- **Git:** branch `main`; implementation package `d8d9874` (pushed); acceptance documentation commit recorded separately after the owner statement (its SHA appears in the next checkpoint). Repository has no `.github/workflows`, so push triggers no deployment. Selective staging excludes `.uat-phase06/`, `console.txt`, `vue-app/test-results/`, runtime DB/logs/credentials, and `.env`.
- **Next:** prepare and submit the separate **06b** plan (`PHASE-06B-PLAN.md`) for owner decision; do not start 06b/06c implementation, merge, or deploy before approval.

---

## Previous checkpoint — 2026-10-31 (DEC-29 APPROVED; implementasi 06a dimulai)

- **Approval:** pemilik menyatakan pada 2026-10-31 WIB, **"Saya setuju D1-D8 dilakukan."**, menyetujui delapan keputusan rencana Fase 06 di `PHASE-06-PLAN.md` sebagai **DEC-29 APPROVED**. Otorisasi mencakup implementasi **06a** saja; tidak mencakup merge/deploy, migration database operasional, perubahan dependency/service, atau pelaksanaan 06b/06c. Rekap sekolah tetap menunggu format (DEC-12 PROPOSED).
- **Status:** `PHASE-06-PLAN.md` berstatus **PLAN APPROVED**; implementasi 06a dimulai. Rencana hanya dokumentasi; belum ada kode/migration/tes/UAT saat persetujuan dicatat.
- **Baseline:** `main` di `ffa71e3` (05c owner-approved dan sudah di-push). Paket rencana dikomit dan di-push terpisah; SHA-nya dicatat pada checkpoint berikutnya, bukan diklaim di sini.
- **Isi rencana:** audit aktual (school_years tanpa periode aktif/lock/tanggal; semesters global tanpa relasi tahun dan tanpa CRUD; SchoolRombelController/Requests masih scaffold; `class_teach_id` tidak fillable dan menunjuk `users`; mismatch route/menu Classes `master.view` vs API `students.*`; 03d belum memeriksa tahun locked/soft-deleted; belum ada promosi/kelulusan/mutasi), urutan yang diusulkan **06a → 06b → 06c**, desain 06a, garis besar 06b/06c dengan pertanyaan terbuka, matriks tes, risiko, dan rencana UAT terpisah.
- **Keputusan yang menunggu pemilik (D1–D8):** D1 tabel singleton `academic_settings` untuk periode aktif (tanpa menebak tahun saat migration); D2 `start_date`/`end_date` nullable pada `school_years`; D3 lock per tahun ajaran dengan alasan + audit, tahun aktif tidak boleh lock; D4 wali kelas dari profil `employees` (`class_teach_employee_id`, hanya `Pendidik` aktif) dengan `class_teach_id` dipertahankan; D5 tanpa ability baru (Classes `students.*`, periode/lock `master.manage`) dan menu Academic; D6 nama unik per tahun + `expected_version` + delete ditolak bila ada riwayat; D7 field wajib `name`/`school_year_id`/`school_level_id`, jurusan dan wali opsional; D8 UAT baru `.uat-phase06` (SQLite, API 18024, SPA 14024, cookie `school_phase06_session`, fixture ≥120 siswa) dihentikan setelah penerimaan (DEC-27).
- **Batas:** rencana ini tidak mengotorisasi implementasi 06a sebelum D1–D8 disetujui dan dicatat sebagai DEC baru; tidak ada merge/deploy/force-push/rewrite; tidak menyentuh database operasional, MariaDB, `.env`, service, dependency, `.uat-phase05`, atau `.uat-phase04b`. Rekap sekolah tetap menunggu format (DEC-12 PROPOSED).
- **Git:** selective staging (hanya dokumen); `git diff --cached --check` sebelum commit; repo tanpa `.github/workflows` sehingga push tidak memicu deployment. Artefak pemilik `console.txt` dan `vue-app/test-results/` tetap tidak di-commit.
- **Next:** implementasi **06a saja** sesuai DEC-29 (migration additive, periode aktif, lock, CRUD rombel, wali kelas `employees`, penegakan lock pada assign/transfer, UI, tes, UAT `.uat-phase06`), lalu berhenti untuk review pemilik. Jangan memulai 06b/06c tanpa rencana dan keputusan terpisah.

---

## Previous checkpoint — 2026-10-31 (05c owner approved; UAT servers stopped)

- **Acceptance:** the owner stated on 2026-10-31 WIB, **"Pengujian nomor 1-14 sudah berhasil dan saya approved."**, approving checklist #1–#14 in `PHASE-05C-MANUAL-REVIEW.md`. That statement is the sole acceptance evidence; the agent did not rerun the scenarios. Acceptance covers 05c on the isolated `.uat-phase05` SQLite UAT only; it does not authorize merge, deployment, legacy/MariaDB/production migration, dependency/service changes, school recap, or Phase 06.
- **Status:** 05c (student/employee completeness inspection and keyed reviews) is **IMPLEMENTED and agent-verified** on `main` at implementation commit `c2fddfa` (baseline `037b1c6`), documentation `82e9755`; owner manual review is **APPROVED** (recorded in this separate documentation commit). Agent NOT RUN items remain NOT RUN.
- **Authorization:** DEC-28 APPROVED 2026-10-31 WIB ("Saya setuju D1-D7") for D1–D7 in `PHASE-05C-PLAN.md`. Scope remains 05c only; no merge, deployment, operational/MariaDB migration, dependency/service changes, school recap (DEC-12 PROPOSED) or Phase 06.
- **Implemented:** completeness profiles/inspectors with explicit denominators (placed non-archived students; active employees), unplaced student NIK as `blocked` outside the percentage, required-field findings, duplicate candidates without auto-merge, `data_quality_reviews` versioned per school year, six `data-quality` endpoints gated by existing `students.view`/`students.manage` and `employees.view`/`employees.manage`, Data Quality SPA page with review dialog and deep links to existing audited forms, and idempotent `UAT-DQ` fixture preparation (60 students + 60 employees; second run created 0).
- **Agent evidence (actual):** DataQuality 15 tests/239 assertions PASS; full suite 417 passed, 13 skipped, 3,826 assertions (260.36 s); Pint 21 files; Node 17 tests PASS; ESLint 0 errors; build PASS 38.18 s; 6 routes registered; migration `2029_07_29_000001` applied only to isolated `.uat-phase05`. Browser UAT: Student 021 acknowledge/ignore/reopen v3, Teacher 021 acknowledge/reopen, duplicate Teacher 034 ignore/reopen v3, deep links Student 430 / Teacher 242 / Staff 228, 390px no page overflow, 0 console messages.
- **UAT:** isolated `.uat-phase05` SQLite, SPA `127.0.0.1:14023`, API `127.0.0.1:18023`, cookie `school_phase05_session`. After acceptance on 2026-10-31 the server trees (Vite PID 10336 and PHP PID 19196) were verified by command line and stopped with `taskkill /T /F` per DEC-27; ports 14023/18023 are free, Apache 80/443 and MariaDB 3306 were untouched, and `.uat-phase05` data/storage is preserved. Restart commands are in `PHASE-05C-MANUAL-REVIEW.md`. Current numbers before shutdown: students denominator 153, complete 30/incomplete 123 (19.6%), blocked 10, duplicates 8 pairs/16, reviews 144 Open; employees denominator 250, separated 10, incomplete 220 (12.0%), duplicates 5/10, reviews 325 Open.
- **NOT RUN (agent):** MariaDB/InnoDB behavior for the new table, two-tab concurrency, full multi-role browser coverage beyond the operator/`uat.kepsek` checks, performance/load scan, and XLSX; these stay NOT RUN regardless of owner acceptance.
- **Git:** branch `main`; implementation `c2fddfa`, documentation `82e9755`; owner acceptance is recorded in this separate documentation commit (SHA reported at the next checkpoint). No `.github/workflows`, so push triggers no deployment. Selective staging excludes `.uat-phase05/`, `.uat-phase04b/`, runtime DB/logs/credentials, `console.txt`, and `vue-app/test-results/`.
- **Next:** await explicit owner instruction for the school recap (DEC-12 PROPOSED, format sample still required) or Phase 06. Do not merge/deploy; UAT servers for accepted phases stay stopped (DEC-27).

---

## Previous checkpoint — 2026-10-31 (DEC-28 APPROVED; implementasi 05c dimulai)

- **Approval:** the owner stated on 2026-10-31 WIB, **"Saya setuju D1-D7."**, approving the 05c plan decisions in `PHASE-05C-PLAN.md` as **DEC-28 APPROVED**. This authorizes 05c implementation only; it does not authorize merge, deployment, operational/MariaDB migration, dependency/service changes, or Phase 06.
- **Status:** 05c implementation was started on `main` above `ded2257`; the plan remains at `reports/feature-update/PHASE-05C-PLAN.md`. No report or acceptance was claimed until implementation verification and owner review.
- **Plan content:** student and employee completeness with explicit denominators (placed students / active employees); student unplaced NIK as `blocked` outside the percentage; required-field profiles; duplicate candidates (never name-only, no auto-merge); persisted versioned review table scoped by school year, writes gated by `students.manage`/`employees.manage`; six `data-quality` endpoints without new abilities; Data Quality page with deep links to existing audited forms; 120 synthetic `UAT-DQ` fixtures in `.uat-phase05`; full backend/frontend/UAT test plan.
- **Boundaries preserved:** no school recap (DEC-12 PROPOSED), no auto-merge/bulk correction/inline edit, no new ability, no operational/MariaDB/04b DB access, no `.env`/service/dependency changes.
- **Git verification:** DEC-28 approval documentation is committed and pushed separately; actual SHA is reported at the next checkpoint. No `.github/workflows`, so push triggers no deployment. Selective staging excludes `.uat-phase05/`, `.uat-phase04b/`, sample CSVs, runtime DB/logs/credentials, `console.txt`, and `vue-app/test-results/`; `git diff --cached --check` is run before commit.
- **Next (historical):** implement 05c per the approved plan (S2–S7), verify backend/frontend/UAT, prepare report and manual checklist, then commit/push the review package. See the current checkpoint above.

---

## Previous checkpoint — 2026-10-31 (Rencana Subfase 05c disiapkan; menunggu D1–D7)

- **Acceptance:** the owner stated on 2026-10-31 WIB, "Pengujian nomor 1-14 sudah berhasil dan saya approved." Checklist #1–#14 in `PHASE-05B-MANUAL-REVIEW.md` are **APPROVED**. That statement is the sole acceptance evidence; the agent did not rerun the scenarios. Acceptance covers 05b on the isolated `.uat-phase05` SQLite UAT only; it does not authorize merge, deployment, legacy/MariaDB/production migration, dependency/service changes, or 05c. Agent NOT RUN items (browser/visual UAT, MariaDB/InnoDB, two-tab, automatic retention, XLSX) remain NOT RUN.
- **Documentation package:** this acceptance/policy documentation is committed and pushed separately as **`c19c7f0`** (above implementation `ad74309` and implementation documentation `c5afdc4`); `main...origin/main` synced after `git push`. This checkpoint update follows in a separate commit and records that SHA; no future SHA is claimed.
- **UAT lifecycle policy (DEC-27, APPROVED 2026-10-31 WIB):** after a phase's manual checklist is passed/accepted, its isolated per-phase UAT servers are stopped so they do not keep running unused. Stop only verified phase server trees (SPA Vite + PHP built-in); never touch shared Apache/MariaDB/PHP/service configs, dependencies, or other applications. Preserve `.uat-*` data/storage and private credentials; document restart commands. A phase still awaiting review keeps its servers running until the owner's decision.
- **Stopped 2026-10-31:** 04b Vite `127.0.0.1:14022` PID 2336 (+esbuild 11284) and PHP `127.0.0.1:18022` PID 12416; 05 Vite `127.0.0.1:14023` PID 13456 (+esbuild 996) and PHP `127.0.0.1:18023` PID 17188. Command lines were verified before `taskkill /T /F`; only these trees were targeted. Ports 14022/14023/18022/18023 are now free; 14021/18021 were already free. Apache (80/443, PID 1304) and MariaDB (3306, PID 18244) remained listening and untouched. `.uat-phase04b/` and `.uat-phase05/` data/storage were not modified.
- **Restart commands (only when reopening a phase for review):** 04b API from `laravel-api`: `C:\xampp\php82-operational\php.exe -S 127.0.0.1:18022 -t "D:/xampp-htdocs/app-administration_school/laravel-api/public" "D:/xampp-htdocs/app-administration_school/laravel-api/scripts/phase04b-uat.php"`; 04b SPA from `vue-app`: `npm run dev -- --host 127.0.0.1 --port 14022 --strictPort`. 05 API from `laravel-api`: `C:\xampp\php82-operational\php.exe -S 127.0.0.1:18023 -t public scripts/phase05-uat.php`; 05 SPA from `vue-app`: `npm run dev -- --host 127.0.0.1 --port 14023 --strictPort`. Cookies: `school_phase04b_session` / `school_phase05_session`.
- **Git verification:** `main` at `5633726` before this package, synced with `origin/main`; repository has no `.github/workflows`, so pushes trigger no deployment. Selective staging excludes `.uat-phase05/`, `.uat-phase04b/`, sample CSVs, runtime DB/logs/credentials, `console.txt`, and `vue-app/test-results/`; `git diff --cached --check` is run before commit.
- **Next:** do not start 05c; school recap awaits a format sample (DEC-12 PROPOSED) and explicit owner instruction. Merge/deployment remain unauthorized.

---

## Previous checkpoint — 2026-10-31 (Subfase 05b impor pegawai & pilihan kolom ekspor — AGENT VERIFIED, OWNER REVIEW PENDING)

- **Authorization:** DEC-26 APPROVED 2026-10-30 WIB — owner statement "Saya setuju D1-D7" on `PHASE-05B-PLAN.md`. This authorizes 05b implementation only; it does not authorize merge/deploy, dependency/service changes, operational DB migration, or 05c.
- **Baseline:** `main` at `4f1fec0` (05b plan documentation). Implementation commit **`ad74309`** ("feat(phase-05b): implement employee CSV import and export column options"); documentation commit **`c5afdc4`**. Both are pushed to `origin/main` (`4f1fec0..c5afdc4`, `main...origin/main` synced after `git fetch`). This checkpoint commit follows and is pushed separately; no future SHA is claimed.
- **Scope delivered (05b):** shared import engine extraction (`App\Services\Imports\ImportService`) preserving the 05a student contract; employee CSV import with 22 English columns, NIK-keyed partial updates (NIK required on update, optional on insert, immutable, unique incl. archived), merged old+new validation via `App\Support\EmployeeRules`, Teacher/Staff Subject rules (Staff conversion clears Subject), Regency+District pair resolution with ambiguity errors, private `Row,Field,Message,NIK,NUPTK` error CSV, all-or-nothing transaction + `lockForUpdate`; ability `employees.import` for `admin-sistem`/`operator-sekolah` (migration `2029_07_28_000001`); employee export column registry (10 default operational + 15 PII gated by `employees.manage`, 403 otherwise), `GET /api/employees/export/options`; English Import/Export dialogs in Teachers/Employees.
- **Agent evidence (2026-10-30/31):** employee import **28 tests/189 assertions PASS**; export columns **15 tests/60 assertions PASS** (combined **43/249**, 18.65 s); full suite **402 passed / 13 skipped / 3,587 assertions** (skips are MariaDB integration without password, not failures); 05a import regression **18/97 PASS**; Pint **15 files PASS**; frontend helper tests **15 PASS**; ESLint new files **0 errors**; build **PASS 28.46 s** (pre-existing chunk warning). Isolated `.uat-phase05`: `prepare` applied migration `2029_07_28_000001`; counts unchanged at 409 students / 200 employees; `employees.import` mapped only to `admin-sistem` + `operator-sekolah`; 3 synthetic employee sample CSVs validated at 22 columns.
- **NOT RUN:** browser/visual UAT 05b — browser automation was blocked by session policy; no UI upload/preview/commit/export flow was executed by the agent. Also NOT RUN: 05b MariaDB/InnoDB, two-tab concurrency, automatic 30-day retention (manual), school recap (05c), XLSX. These are not converted to PASS by any later owner acceptance.
- **UAT review (historical):** `.uat-phase05` (SPA `127.0.0.1:14023`, API `127.0.0.1:18023`, cookie `school_phase05_session`) was running during owner review; it was stopped on 2026-10-31 after acceptance. Owner checklist is `PHASE-05B-MANUAL-REVIEW.md` (#1–#14, now APPROVED). Run `employees-import-valid.csv` before `employees-import-update.csv`; `employees-import-mixed.csv` must be rejected without mutation.
- **Git verification:** implementation `ad74309` and documentation `c5afdc4` are on `main` and pushed to `origin/main` under the standing phase commit+push authorization. Push is review delivery, not owner acceptance. Repository has no `.github/workflows`, so pushes trigger no deployment. Selective staging; remaining untracked items are owner artefacts `console.txt` and `vue-app/test-results/` plus ignored `.uat-phase05/` runtime/credentials. `git diff --cached --check` passed before both commits.
- **Working tree warning:** never commit `.uat-phase05/`, sample CSVs, runtime DB/logs, credentials, `console.txt`, or `vue-app/test-results/`.
- **Next (historical):** owner manual review of #1–#14 was completed on 2026-10-31 WIB (APPROVED); see the current checkpoint above. Do not start 05c or school recap until the owner provides a format sample (DEC-12 PROPOSED) and explicit instruction. Merge/deployment remain unauthorized.

---

## Previous checkpoint — 2026-10-30 (Subfase 05a impor siswa — OWNER APPROVED)

- **Acceptance:** the owner stated on 2026-10-30 WIB, "Pengujian nomor 1-12 sudah berhasil dan saya approved." Checklist #1–#12 are **APPROVED** in `PHASE-05A-MANUAL-REVIEW.md`. That statement is the sole acceptance evidence; the agent did not rerun the scenarios. Acceptance covers 05a on the isolated `.uat-phase05` SQLite UAT only; it does not authorize merge, deployment, legacy/MariaDB/production migration, dependency/service changes, or 05b/05c.

- **Baseline:** `main` at `b093db7` (documentation of 04b acceptance). Implementasi 05a dikomit sebagai **`58828a6`**, dokumentasi sebagai **`a3e19d4`**; keduanya di-push ke `origin/main` (`b093db7..a3e19d4`, `main...origin/main` sinkron) agar pemilik dapat meninjau di GitHub sebelum cek manual. Commit/push bukan penerimaan.
- **Authorization:** DEC-25 APPROVED 2026-10-30 WIB (D1–D8). Owner instruction 2026-10-30: implementation packages that need manual review are committed and pushed immediately so they can be reviewed on GitHub. Owner manual approval is still required and is recorded in a separate documentation commit.
- **Scope delivered (05a):** student CSV import — UTF-8 CSV ≤5 MB/2,000 rows; strict English 17-column header; delimiter `,`/`;`; preview without mutation; all-or-nothing commit in one transaction with `lockForUpdate`; `insert`/`update` mode matching NIS then NIK (never name); private error CSV with formula neutralization and manual 30-day retention; `students.import` for `admin-sistem`/`operator-sekolah`; template download needs only `students.view`; batch owner scoping returns 404 to other authorized users. Migrations `2029_07_27_000001` (`import_batches`) and `2029_07_27_000002` (ability) are additive; `App\Support\StudentRules` is the single rules source for form + import.
- **Frontend:** `StudentImportDialog.vue` wizard (upload → preview → commit/errors), `studentImportParams.mjs`, 3 Node unit tests; `Student.vue` gains +18 lines (Import button gated by `students.import`, list refresh on success).
- **Isolated UAT:** `.uat-phase05` SQLite; SPA `127.0.0.1:14023`, API `127.0.0.1:18023`, cookie `school_phase05_session`; launcher `laravel-api/scripts/phase05-uat.php` (`init`/`prepare`/`status`) with process-override/cache guards. Synthetic data: 300 Phase05 students + 200 employees (100 teachers/100 staff). Samples in `.uat-phase05/samples/` are ignored and re-keyed (valid NIS 42001–42003, mixed 43001–43003, update targets 40001), not re-run after re-key.
- **Agent evidence (2026-10-30):** import tests **18/97 PASS**; regression **170 passed / 1.097 assertions / 13 skipped** (skips are MariaDB integration without password, not failures); Pint **16 files PASS**; frontend **3 Node tests PASS**; build **PASS 26,02 s**; launcher `status` shows 406 students, 200 employees (100/100), ability present; browser DOM valid import 406→409, mixed 1 valid/2 invalid rejected with total unchanged at 409, update via NIS, template download, **0 console errors**. Pointer clicks were unavailable (`NATIVE_BROWSER_VIEWPORT_UNAVAILABLE`); evidence is DOM/HTTP, not screenshots.
- **NOT RUN:** 05a MariaDB integration/concurrency, two-tab scenarios, screenshot/visual checks, automatic 30-day cleanup (manual by D6), XLSX, employee import, column options, school recap. These remain NOT RUN after owner acceptance and are not converted to PASS.
- **Git verification:** implementation `58828a6`, documentation `a3e19d4`, checkpoint `ad7bb9c` pushed to `origin/main`; `main...origin/main` synced after `git fetch`. Acceptance is recorded in a separate documentation commit after this checkpoint. No `.github/workflows`, so pushes trigger no deployment. `git diff --cached --check` passed before each commit; remaining untracked items are only owner artefacts `console.txt` and `vue-app/test-results/`, plus ignored runtime/credentials (`.uat-phase05/`).
- **Working tree warning:** do not commit `.uat-phase05/`, sample CSVs, runtime databases/logs, credentials, `console.txt`, or `vue-app/test-results/`. Per owner instruction, review packages are pushed before manual checks with selective staging; push is not approval.
- **Next (historical):** 05b plan was prepared in `PHASE-05B-PLAN.md` and was later approved as DEC-26 on 2026-10-30 WIB; 05b implementation is recorded in the latest checkpoint above. School recap awaits a format sample (DEC-12 PROPOSED). Merge/deployment remain unauthorized.

---

## Current correction addendum — 2026-10-29 (English/isolated 04b UAT)

- **Baseline:** `main` at `e281419` (correction implementation `dc5e031`, documentation/acceptance `e281419`), pushed to `origin/main` under the owner's standing authorization after their 2026-10-30 WIB approval. 04a and **04b are owner-accepted**: the owner stated on 2026-10-30 WIB, “Pengujian nomor 1-18 sudah berhasil dan saya approved”, approving checklist #1–#18. That statement is the sole acceptance evidence; the agent did not rerun the checklist, and agent NOT RUN gaps remain recorded. `console.txt` and `vue-app/test-results/` are owner artefacts left untracked/untouched.
- **Owner requirements:** full-English application presentation; Teachers = Guru only (canonical `Pendidik`), Employees = non-teaching staff (canonical `Tenaga Kependidikan`); at least 100 synthetic records per empty new UAT feature. Classification uses only `employee_type_id`, never a job-title restriction; canonical jobs including head-of-school positions remain intact.
- **Current review target:** new SPA `http://127.0.0.1:14022`, API `http://127.0.0.1:18022`; root `.uat-phase04b/database.sqlite`, dedicated private environment/storage and cookie `school_phase04b_session`. Use only `laravel-api/scripts/phase04b-uat.php` (`init` / `prepare` / `status`). The fail-closed guard rejects database URLs, caches and conflicting process overrides; `prepare` uses normal migrations only. Never run root `artisan --env=uat`: legacy/main environment and MariaDB targets are excluded. Do not alter existing services, dependencies or old UAT.
- **Fixtures:** baseline 100 Synthetic Teachers with subjects + 100 Synthetic Staff without subjects across 15 jobs; one browser-created record of each gives current **101 teachers / 101 staff / 103 students / 100 synthetic schools** (NPSN `04` namespace). Idempotent preparation preserves edits and archives; only the initial five UAT accounts, no additional employee logins. Runtime and local `UAT-ACCOUNTS.local.md` stay private/ignored; never copy password literals into documentation.
- **Contract corrections:** shared 17-domain/243-label English JSON catalog and frontend-only `display_name` enrichment preserve canonical names/IDs and user/proper names; English navigation/Profile/validation/CSV/print labels. Passwords are stripped before cache enrichment; route-view key avoids stale cross-route dialogs/data. Employee list/export have name/NUPTK/gender (not zero PII), and lists expose `account: {linked, user_id}`. Sensitive/expanded account fields remain manage-detail only. Unresolved list names are null/blank. Archived GET detail uses `withTrashed()` and requires view + manage (403 for view-only). Current CSV header is `Employment Start Date (TMT)`.
- **Actual automated evidence, not additive totals:** latest explicit seven-file SQLite run **96 tests/1738 assertions PASS, 34.77 s**, including current employee CRUD/schema/account/export/seeder and reference-label/launcher tests. Earlier 98/1777, subsequent 17/117, 5/219, launcher 3/372 and separate translation 119/1223 runs remain separately recorded in the report; do not aggregate overlapping runs or call this a full backend suite. Final frontend **42 Node tests PASS**; final production build **PASS, 18.77 s** (earlier 18.28 s build is historical). Targeted ESLint 0 errors and Pint PASS apply only to checked scopes; baseline build chunk/Browserslist warnings remain.
- **Browser actually checked:** operator DOM interactions + authenticated API readback: Guru create 201/v1, edit v2, concurrent API save v3 → stale UI 409/input preservation/Reload Latest, English blank-reason validation, archive v4 (exit null), manager archived detail, restore v5, account link v6 and unlink v7; staff UI create 201/v1 with fixed type/no Subject. Route switches showed correct types; authenticated CSV reads returned 100 baseline rows per type with English labels. Staff page/form at 390×844 fit the viewport and long content scrolled to Create; DOM measurements only, no screenshot evidence. Student/reference/Profile English labels checked; blank Profile save produced English validation without account mutation.
- **HTTP roles:** separate synthetic sessions for `uat.waka`, `uat.kepsek`, `uat.operator` passed detail/PII, export, management and archived-detail checks, repeated after link/unlink with abilities unchanged. This is HTTP evidence, not other-role browser UI coverage or owner acceptance.
- **Still NOT RUN:** other-role browser UI, two-tab stale/link-conflict scenarios, print-preview and screenshot-based visual checks; 04b MariaDB DDL/unique/InnoDB concurrency (available, excluded targets not accessed). Initial browser NOT RUN and earlier test evidence below remain historical. No production migration/deployment evidence or authorization.
- **Next:** 04b owner acceptance is recorded and committed. Continue only on explicit owner instruction; merge, deployment and migration still require separate authorization. Do not migrate legacy UAT/MariaDB or expand phase scope. New isolated servers are left running; existing owner servers were not restarted/stopped.
- **Git verification:** command-scoped `-c safe.directory` permits read-only checks without persistent Git configuration changes. Correction implementation `dc5e031` and documentation/acceptance `e281419` are on `main`; push `d10f7b8..e281419` succeeded and `main...origin/main` is synced after `git fetch`. Repository has no `.github/workflows`, so the push triggers no deployment. `git diff --cached --check` and `git diff --check` PASS; line-ending warnings are not whitespace errors. Working tree is clean except owner artefacts `console.txt` and `vue-app/test-results/`; ignored runtime/credentials `(.uat-phase04b/, UAT-ACCOUNTS.local.md)` are excluded.

---

## Historical handoff — 2026-10-26 and initial 04b checkpoint

Checkpoint penutupan sesi: 2026-10-26 (WIB). Branch `main`.
Baseline implementasi akhir 03d: `7bed397c30f5a38e6a34002157c6ac33c0317c89` ("feat(phase-03d): implement student rombel assignment and transfer UI").
Baseline keputusan DEC-23: `01c953e` ("docs(decisions): record owner decisions for student rombel assignment and transfer (DEC-23)").
Baseline implementasi 03c: `e15f028`.
Status fase:
- **Fase 01, 02, dan 03 (03a, 03b, 03c, serta subfase tambahan 03d)**: **DITERIMA LENGKAP & DITUTUP** oleh pemilik (checklist manual #1–#15 APPROVED 2026-10-26 WIB).
- **Subfase 03d (scope tambahan)**: **DITERIMA LENGKAP & DITUTUP OLEH PEMILIK**.
- Kebijakan commit/push: commit dan push per fase/koreksi ke branch kerja aktif telah **diotorisasi**; merge, force-push, rewrite history, dan deploy manual **tetap tidak diotorisasi**.
- Catatan perubahan pemilik: `console.txt` dan `vue-app/test-results/` adalah artefak lokal dan **tidak** di-commit.
- **Peringatan Batas:** Penutupan sesi ini murni dokumentasi handoff; bukan security clearance, bukan izin deploy/upgrade, dan bukan otorisasi akses ke database operasional.

---

## 0a. Checkpoint Re-verifikasi 04a (2026-10-29)

- **Konteks**: Sesi 2026-10-26 sudah mengimplementasikan dan mem-push 04a (`ecb2ea3`). Sesi 2026-10-29 memverifikasi ulang hasil tersebut secara independen dan memperbaiki gap terhadap instruksi pemilik.
- **Gap yang diperbaiki**:
  1. Penanda `legacy_profile_at` (migration `2029_07_25_000001`, additive) membatasi fallback NIK hanya pada baris pra-04a; profil baru dan akun yang dilepas/hard-deleted tidak bisa ter-relink.
  2. `EmployeeBackfillService` atomik, mengecualikan akun soft-deleted, dan hanya memproses baris legacy.
  3. `down()` migration 04a fail-closed sebelum DDL (NULL `user_nik`/`subject_id` atau `user_id` terhubung) dan rebuild SQLite dibungkus transaksi.
  4. NUPTK dikeluarkan dari allowlist audit.
  5. Command `app:employee-account-backfill --dry-run` dengan keluaran agregat tanpa PII.
  6. Test MariaDB memakai user khusus DB `school_04a` (bukan `school_compat_app` yang memiliki grant pada DB review lain).
- **Bukti aktual 2026-10-29**: SQLite `EmployeeSchemaAndAccountRelationTest` **21 passed / 111 assertions**; MariaDB `MariaDbEmployeeIntegrationTest` **6 passed / 31 assertions** pada `school_04a_integration` yang dibuat ulang; `FactoryIntegrityTest` 3/26 dan `StudentRombelAssignmentTest` 16/78 PASS; Pint --test PASS.
- **NOT RUN**: `MariaDbRombelConcurrencyTest` (target `school_restore_drill_uat` di luar allowlist sesi).
- **Status 04a**: **DITERIMA PEMILIK** — checklist manual #1–#13 APPROVED 2026-10-29 WIB berdasarkan pernyataan pemilik ("Pengujian nomor 1-13 sudah berhasil dan saya approved"); agen tidak menjalankan ulang pengujian untuk mencatat status. 04b **diotorisasi 2026-10-29 WIB (DEC-24)** dan implementasinya berjalan. Migration baru belum diterapkan ke UAT.

## 0b. Historical initial implementation checkpoint 04b (2026-10-29; superseded by correction addendum)

- **Konteks**: Pemilik menyetujui D1–D8 (DEC-24) dan menginstruksikan eksekusi. Implementasi backend + frontend diselesaikan pada sesi ini di atas baseline `fff5249`.
- **Hasil aktual**: `php artisan test tests/Feature/Employee` → **56 passed, 6 skipped (331 assertions)**; suite penuh → **293 passed, 21 skipped (1448 assertions)**; `node --test tests/employee-directory-params.test.mjs` → **7 passed**; `npm run build` → **PASS**; `pint --test` 17 berkas → **PASS**; `eslint --quiet` 8 berkas → **0 error**.
- **NOT RUN**: MariaDB disposable 04b (MariaDB berhenti; service tidak diubah) dan browser UAT (migration `2029_07_26_000001` belum diotorisasi untuk UAT; data pegawai UAT kosong).
- **Status 04b**: **IMPLEMENTED & DIVERIFIKASI OTOMATIS**; penerimaan manual **PENDING** melalui `PHASE-04B-MANUAL-REVIEW.md` #1–#18. Migration belum diterapkan ke UAT/produksi; tidak ada merge/deploy. *(Historis 2026-10-29; pemilik kemudian menyetujui #1–#18 pada 2026-10-30 WIB — lihat bagian 0a dan `PHASE-04B-MANUAL-REVIEW.md`.)*
- **Commit**: implementasi `e1de4f4`; dokumentasi `b93f954`; `main` sinkron dengan `origin/main`. Artefak lokal `console.txt` dan `vue-app/test-results/` tidak di-commit.


---

## 0. Checkpoint Penutupan Sesi (2026-10-26)

- **HEAD / Remote**: sinkron `origin/main`. Working tree bersih selain artefak lokal `console.txt` dan `vue-app/test-results/`.
- **Status Subfase 03d**: **DITERIMA LENGKAP & DITUTUP OLEH PEMILIK**. Seluruh checklist manual `PHASE-03D-MANUAL-REVIEW.md` (#1–#15) disetujui penuh (APPROVED) pada 2026-10-26 WIB.
- **Server UAT Aktif**: Backend loopback port `18021` dan SPA loopback port `14021` strict port dibiarkan berjalan untuk kebutuhan review selanjutnya.

---

## 1. Bukti Aktual Subfase 03d (Hasil Agen)

1. **Backend Automated Tests (SQLite In-Memory Murni)**:
   - `php artisan test <28-test-files>` -> **237 passed (0 failed)** across 28 test files, total **1117 assertions** di atas PHP 8.2.35.
   - Pengecualian eksplisit: `MariaDbIntegrationTest.php`, `MariaDbStudentIntegrationTest.php`, dan `MariaDbRombelConcurrencyTest.php` diexclude dari suite SQLite agar tidak membuka koneksi MariaDB.
   - Cakupan khusus `StudentRombelAssignmentTest.php`: **16 passed (78 assertions)** mencakup validasi input 422, otorisasi 401/403, penolakan siswa terarsip, conflict 409, transaksi atomik, dan **rollback kegagalan audit** (assign dan transfer atomik).
2. **MariaDB Concurrency Integration Tests**:
   - Dijalankan pada database disposable khusus `school_03d_integration` pada MariaDB port 3306 (user `school_compat_app` dengan guard koneksi eksak).
   - Eksekusi worker nyata melalui pipeline controller aktual (`concurrency_worker.php`) dengan verifikasi tumpang tindih lock InnoDB (`SHOW FULL PROCESSLIST` / `information_schema.innodb_trx` / `innodb_lock_waits`).
   - `MariaDbRombelConcurrencyTest.php` -> **7 passed (43 assertions)** dalam 10.55s:
     - Dua Assign konkuren serial via row lock (1 commit 200, 1 ditolak 409 conflict).
     - Penolakan siswa terarsip pre-existing (non-race) via trigger MariaDB.
     - Dua Transfer konkuren simultan pada siswa/membership yang sama (1 commit 200, 1 ditolak 409 conflict).
     - Race Archive vs Assign (Assign menang lock: Assign 200, Archive 409).
     - Race Archive vs Assign (Archive menang lock: Archive 200, Assign 422).
     - Race Archive vs Transfer (Transfer menang lock: Transfer 200, Archive 409).
     - Race Archive vs Transfer (Archive menang lock: Archive 200, Transfer 422).
3. **Frontend Tests, Build & Lint**:
   - Unit tests: `node --test` (Node portable v24.22.1) -> **18 passed (0 failed)** dalam 1090ms.
   - Production build: `npm run build` -> **PASS** (Vite 6.3.5, 789 modules transformed dalam 1m 9s).
   - Pint: **PASS** (100% clean pada seluruh berkas PHP yang dibuat/dimodifikasi).
   - Targeted lint: `npx eslint src/views/pages/public/Student.vue src/main.js` -> **0 errors, 2750 warnings** (seluruh warning adalah CRLF `prettier/prettier Delete ␍`, **bukan PASS bersih**).
4. **Smoke Test UAT Agen**:
   - Metode: **HTTP/API fetch script via Node** (bukan browser visual / Playwright MCP).
   - Hasil: **16 skenario PASS** mencakup CSRF bootstrap, login operator 204, check-auth abilities, student list & recap (school_year_id=1), filter placed/unplaced, search formula, detail, edit version 1->2, stale edit 409 conflict, archive version 2->3, archived list view, restore version 3->4, export CSV, logout 204, kepsek view-only checks (view 200, create 403, archived 403, export 200), dan waka role checks (view 200, export 403).
5. **Penerimaan Manual Pemilik**:
   - Checklist `reports/feature-update/PHASE-03D-MANUAL-REVIEW.md` (#1–#15) tetap berstatus **PENDING** menunggu review manual pemilik.

---

## 2. Catatan Rekonsiliasi Bukti yang Telah Diselesaikan

Gap pengujian yang diidentifikasi pada checkpoint sebelumnya telah dibuktikan secara deterministik:
1. **Rollback Kegagalan Audit**:
   - Terbukti pada `StudentRombelAssignmentTest.php`: kegagalan audit pada Assign dan Transfer membatalkan seluruh perubahan domain (membership baru batal tersimpan, membership sumber batal ditutup, versi siswa dan membership tetap utuh, tidak ada audit parsial).
2. **Matriks Konkurensi MariaDB Lengkap (7 Skenario PASS)**:
   - **Dua Assign konkuren**: TERBUKTI (1 sukses 200, 1 konflik 409).
   - **Dua Transfer konkuren**: TERBUKTI (1 sukses 200, 1 konflik 409).
   - **Race Archive vs Assign (kedua urutan lock)**: TERBUKTI (Assign duluan -> Archive ditolak 409; Archive duluan -> Assign ditolak 422).
   - **Race Archive vs Transfer (kedua urutan lock)**: TERBUKTI (Transfer duluan -> Archive ditolak 409; Archive duluan -> Transfer ditolak 422).
   - **Pemisahan Tes Non-Race vs Race Aktual**: Pengujian penolakan trigger terhadap data terarsip pre-existing secara tegas dipisahkan dari pengujian race condition simultan.

---

## 3. Status Lingkungan, Server UAT, dan Pemulihan Kredensial

- **PHP Global Operasional**: `C:\xampp\php82-operational\php.exe` (PHP 8.2.35 ZTS x64, loaded ini: `C:\xampp\php82-operational\php.ini`).
- **Node Portable Patched**: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\runtime\node-v24.22.1\node-v24.22.1-win-x64\node.exe` (v24.22.1, npm 11.19.1).
- **Proses Server UAT yang Terverifikasi Berjalan**:
  - **API Backend**: Port `18021` (PID listener `15960`, parent PID `3812`/`21044`, executable `C:\xampp\php82-operational\php.exe`).
    - *Working Directory / Serving Checkout*: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\staging\checkout\laravel-api` (`--env=uat`).
    - *Database Target*: `school_compat_uat` pada MariaDB port 3306.
  - **SPA Frontend**: Port `14021` (PID listener `17964`, executable Node portable `v24.22.1`, strict port).
    - *Serving Checkout*: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\staging\checkout\vue-app` (`--mode uat`).
  - **Server Host Bersama**: Apache port `80`/`443` (PID `16684`), MariaDB port `3306` (PID `1840`).
- **Diagnosis Eksekusi Seeder Pemilik & Transparansi Akses Database**:
  - Pemilik menjalankan `UatSeeder` pada checkout utama `C:\xampp\htdocs\app-administration_school\laravel-api`.
  - Berkas `.env.uat` pada checkout utama merujuk ke database `school_access_uat` (basis data UAT Fase 01a terdahulu). Akibatnya, `updated_at` akun `uat.%` pada `school_access_uat` terupdate ke `2026-10-26 07:08:49`.
  - *Transparansi Pemeriksaan Luar Target*: Saat diagnosis, pemeriksaan read-only (`SELECT`) dijalankan terhadap `school_access_uat` dan `backdata_administrations` untuk mengidentifikasi divergensi target seeder pemilik. Akses diagnostik ini dicatat secara transparan berada di luar target DB yang diotorisasi (`school_compat_uat`). Akses tersebut murni baca tanpa ada mutasi data.
  - *Catatan Database Operasional*: Query `SELECT COUNT(*)` pada `backdata_administrations` mengembalikan 0 baris akun `uat.%`. Namun, ketiadaan baris akun tersebut dicatat tidak membuktikan keselamatan atau ketiadaan perubahan historis DB secara menyeluruh.
  - Karena server UAT aktif di port 18021 dilayani oleh checkout terisolasi yang membaca database `school_compat_uat`, password akun di `school_compat_uat` belum tersinkronisasi.
- **Tindakan Pemulihan Akses Login**:
  - Menghasilkan kata sandi acak baru yang aman secara lokal dan memperbarui berkas privat `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\secret_uat_password.txt`.
  - Mutasi pemulihan dibatasi secara eksklusif hanya pada database terotorisasi `school_compat_uat` via PDO terproteksi pre-bootstrap guard untuk mereset password `uat.operator` dan `uat.kepsek`.
  - Atribut akun lainnya, peran (`operator-sekolah`, `kepala-sekolah`), status aktif (`1`), dan data siswa tetap utuh.
- **Hasil Verifikasi Otomatis Login UAT (HTTP/API)**:
  - `uat.operator`: CSRF 204, Login 204, Check-auth 200 (role: `operator-sekolah`, ability `students.manage` PRESENT, `backup.manage` ABSENT), Logout 204, sesi dibersihkan (authenticated = false) -> **PASS**.
  - `uat.kepsek`: CSRF 204, Login 204, Check-auth 200 (role: `kepala-sekolah`, ability `students.view` PRESENT, `students.manage` ABSENT), Logout 204, sesi dibersihkan -> **PASS**.
  - Review manual di browser tetap berstatus **PENDING** untuk diuji oleh pemilik.
- **Lokasi Kredensial Privat Lokal (Tanpa Membaca/Menyalin Isi ke Laporan/Git)**:
  - Password database UAT & integration: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\secret_db_pass.txt`
  - Password akun sintetis UAT: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\secret_uat_password.txt`
  - *Perintah membaca password bagi pemilik:* `Get-Content "C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\secret_uat_password.txt"`

---

## 4. Batas Keamanan yang Dipertahankan

1. **Containment Apache Proyek Sekolah**:
   - `Include "conf/extra/httpd-school-containment.conf"` aktif di `httpd.conf:573`.
   - Seluruh akses HTTP/HTTPS loopback ke direktori sekolah, `.env`, `.git/HEAD`, log, dan `public/` tetap mengembalikan **403 Forbidden**.
2. **Risiko yang Tetap OPEN**:
   - Advisory dependency lockfile: 48 Composer advisories & 20 npm vulnerabilities tersisa.
   - Lifecycle framework/runtime: Laravel 10 berstatus EOL sejak Feb 2025; PHP 8.2 mendekati akhir masa dukungan keamanan (31 Des 2026), bukan sudah EOL pada tanggal pencatatan ini.
   - Paparan historis host: File `.env` dan log berpotensi terekspos sebelum containment Apache aktif pada sesi terdahulu. Tindak lanjut rotasi rahasia produksi tetap merupakan pekerjaan terpisah.
3. **Isolasi Database & Host**:
   - Mutasi data dibatasi ketat pada database terotorisasi (`school_compat_uat` untuk kredensial UAT; `school_03d_integration` dan `school_04a_integration` untuk tes integrasi). Pemeriksaan diagnostik read-only terhadap `school_access_uat` dan `backdata_administrations` dicatat transparan di Bagian 3 sebagai akses di luar otorisasi tanpa mutasi data. Resource/database App Gudang tidak disentuh.
   - Tidak ada modifikasi pada konfigurasi Apache, PHP global, service Windows, atau firewall host.

---

## 5. Ringkasan Status Roadmap & Langkah Sesi Berikutnya

| Subfase | Deskripsi | Status Teknis | Review Pemilik |
|---|---|---|---|
| **00** | Audit baseline & lingkungan | PARTIAL | PENDING |
| **01 (01a–01c)** | Hak akses, auth, isolasi fixture | IMPLEMENTED & FULLY VERIFIED | **DITERIMA LENGKAP & DITUTUP** |
| **02 (02a–02c)** | Audit terpercaya & backup/restore | IMPLEMENTED & FULLY VERIFIED | **DITERIMA LENGKAP & DITUTUP** |
| **03 (03a–03c)** | Pendataan siswa lengkap & aman | IMPLEMENTED & FULLY VERIFIED | **DITERIMA LENGKAP & DITUTUP** |
| **03d (tambahan)** | Penempatan & perpindahan rombel UI | IMPLEMENTED & FULLY VERIFIED | **DITERIMA LENGKAP & DITUTUP** (Checklist #1–#15 APPROVED) |
| **04a** | Schema profil pegawai dan hubungan akun | **IMPLEMENTED & FULLY VERIFIED (AGEN)** | **DITERIMA PEMILIK** (checklist #1–#13 APPROVED 2026-10-29 WIB per pernyataan pemilik) |
| **04b** | CRUD pegawai dan menu aktif | **IMPLEMENTED**, latest SQLite 96/1738 + frontend 42/build PASS; scoped operator browser/three-role HTTP. Agent-side other-role browser UI, two-tab/print/screenshot and 04b MariaDB NOT RUN | **DITERIMA PEMILIK** (checklist #1–#18 APPROVED 2026-10-30 WIB per pernyataan pemilik) |

**Langkah untuk Sesi Baru**:
1. ~~Pemilik meninjau checklist review fondasi Subfase 04a~~ — **SELESAI 2026-10-29**: checklist `PHASE-04A-MANUAL-REVIEW.md` #1–#13 APPROVED oleh pemilik.
2. ~~Subfase 04b menunggu otorisasi terpisah~~ — **SELESAI 2026-10-29 WIB**: pemilik menyetujui D1–D8 (DEC-24), implementasi selesai dan diverifikasi otomatis (`PHASE-04B-REPORT.md`).
3. Current 2026-10-29 correction: use only new isolated SQLite UAT 14022/18022 and guarded `scripts/phase04b-uat.php status`; migration/preparation is already applied there. Root `artisan --env=uat`, old UAT/MariaDB targets and owner services remain excluded.
4. Final explicit SQLite 96/1738, frontend 42 tests/build 18.77 s PASS and scoped style/browser/HTTP evidence are recorded in the report; never aggregate overlapping runs. Other-role browser UI, two-tab stale/link-conflict, print-preview/screenshot visual checks and 04b MariaDB DDL/InnoDB remain NOT RUN.
5. `PHASE-04B-MANUAL-REVIEW.md` #1–#18 is APPROVED per the owner's 2026-10-30 WIB statement, recorded separately from agent evidence. Corrections are committed as `dc5e031` with documentation/acceptance in `e281419`, pushed to `origin/main`; no merge/deploy/migration was performed. Preserve owner artefacts.
6. Seluruh risiko keamanan terbuka (advisories, lifecycle Laravel/PHP, rotasi kunci produksi) tetap OPEN.
