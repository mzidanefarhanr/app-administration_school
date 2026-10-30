# Phase 04b — Owner Manual Review

- Date: 2026-10-29 WIB; correction addendum checkpoint; owner acceptance recorded 2026-10-30 WIB.
- Branch: `main`; correction baseline: `d10f7b8f61e4cbc95e676bb263eb6575b7658390` plus uncommitted English/UAT corrections.
- Original implementation: `e1de4f4`; original documentation: `b93f954`.
- Owner acceptance: **APPROVED** — checklist #1–#18 approved by the owner's statement on 2026-10-30 WIB (see Owner acceptance below). This record is the owner's statement; the agent did not rerun the checklist.
- Technical evidence: `PHASE-04B-REPORT.md`, correction addendum.
- Decisions: DEC-24 D1–D8; latest owner instructions require full-English application presentation and at least 100 synthetic records for each empty new UAT feature. Teachers are Guru only (canonical `Pendidik`); Employees are non-teachers (canonical `Tenaga Kependidikan`).

## Correction addendum — 2026-10-29: agent checks are not owner review

Browser DOM interactions plus authenticated API readback verified the operator path on the new SQLite UAT: teacher create 201/version 1, edit version 2, concurrent API save version 3 then stale UI 409 preserving input and Reload Latest, blank archive-reason English validation, archive version 4 with exit date still null, manager archived detail, restore version 5, account link version 6 and unlink version 7, and non-teaching UI create 201/version 1 with fixed type/no Subject UI. Route switching showed the correct types without stale dialogs. Authenticated CSV reads returned 100 baseline rows per type with English labels. At 390×844, the staff page/form fit the viewport and long form content scrolled to the Create button; this is DOM measurement, not screenshot evidence. Student/reference pages and Profile labels were checked in English; the blank Profile save returned English password-field validation without changing the account.

Separate HTTP session checks passed for `uat.waka`, `uat.kepsek` and `uat.operator`, including sensitive-detail, export, management and archived-detail permissions; these were not other-role browser UI logins. Latest explicit SQLite run: **96 tests / 1738 assertions PASS**; frontend: **42 tests PASS**; final production build: **PASS, 18.77 s**. See the report for commands, overlapping run boundaries and targeted style checks.

**Owner acceptance recorded 2026-10-30 WIB:** the owner stated that scenarios #1–#18 passed and approved them; all statuses below are **APPROVED**. Agent-only gaps remain documented and are not retroactively claimed as PASS: other-role browser UI automation, two-tab link-conflict, print-preview/screenshot visual checks and 04b MariaDB DDL/InnoDB were **NOT RUN by the agent**. Initial browser NOT RUN evidence remains dated history, not the current SQLite status.

## Safe review environment

Use the **new isolated SQLite UAT**, not the legacy root `.env.uat` or any MariaDB database:

- Frontend: `http://127.0.0.1:14022`
- API: `http://127.0.0.1:18022`
- Database: `.uat-phase04b/database.sqlite`, outside the API public directory and ignored by Git.
- Session: `school_phase04b_session`.
- Accounts and private password location: local ignored `UAT-ACCOUNTS.local.md`; runtime `.uat-phase04b/account-password.txt`. Do not copy secrets into reports. Only the initial five UAT accounts exist; fixtures do not create extra accounts.
- Fixtures: baseline 100 teachers with subjects and 100 non-teaching employees without subjects, spanning 15 jobs. Names begin `Synthetic Teacher` or `Synthetic Staff`. Current totals after one browser-created profile of each type: **101 teachers / 101 staff / 103 students / 100 synthetic schools** (NPSN `04` namespace).
- PHP: `C:\xampp\php82-operational\php.exe`.

Run from `laravel-api` using Git Bash:

```bash
"C:/xampp/php82-operational/php.exe" scripts/phase04b-uat.php status
```

The guard must report the **exact dedicated SQLite UAT** and at least 100 profiles per type; it currently reports 101 each, not exactly 100. The launcher loads root `.uat-phase04b/.env` and private storage, not the main/old UAT environment. It rejects database URLs, cached configuration/routes and conflicting process overrides. Never substitute any root `artisan --env=uat` command: that old profile targets an excluded database.

Launcher lifecycle: `init` initializes a missing runtime only (refuses overwrite); `prepare` runs normal additive migrations and idempotent synthetic preparation; `status` is the read-only guard/count check. The runtime is already prepared: do not reinitialize/reset it. Repeat preparation preserves edited/archived records and does not reset credentials or create additional accounts. No `migrate:fresh`, legacy UAT migration or operational/MariaDB target is allowed.

If the new servers are not running, start **only** these isolated instances:

```bash
# laravel-api
"C:/xampp/php82-operational/php.exe" -S 127.0.0.1:18022 -t public scripts/phase04b-uat.php
# vue-app
VITE_API_BASE_URL=http://127.0.0.1:18022 npm run dev -- --host 127.0.0.1 --port 14022 --strictPort
```

Do not restart existing owner servers, alter services/grants, reset databases, migrate old review databases, or use production data. SQLite browser evidence does **not** verify MariaDB DDL or InnoDB concurrency.

## Review checklist

Owner statuses below were recorded as **APPROVED** from the owner's statement on 2026-10-30 WIB; agent test results alone never change them. Use synthetic `UAT Browser ...` records for mutations; leave baseline fixtures intact.

| # | Scenario | Steps and expected result | Owner status |
|---|---|---|---|
<!-- Owner statuses updated to APPROVED 2026-10-30 WIB per owner statement; see Owner acceptance. -->
| 1 | Routes and English navigation | Login `uat.operator`; switch Teachers → Employees → Teachers. Correct titles and routes, no stale cross-type rows; English labels throughout. | APPROVED |
| 2 | Teacher directory | Only teachers (`Pendidik` internally); English status/job/subject filters, paging, search and Name/start-date sorting work server-side. | APPROVED |
| 3 | Non-teaching directory | Only non-teaching employees (`Tenaga Kependidikan` internally); administrative, library, laboratory, IT, medical, security, transport and support jobs appear; no Subject control/column. | APPROVED |
| 4 | Inline validation | New Teacher → Create Teacher with blank Name/start date; enter NIK `123`. English errors appear without losing input. | APPROVED |
| 5 | Subject rules | Teacher without Subject is rejected with 422. Non-teaching form hides Subject; an authenticated API request that explicitly supplies Subject for a non-teaching profile is rejected with 422. Do not attempt this impossible choice through the hidden UI control. | APPROVED |
| 6 | Valid create/detail | Create `UAT Browser Teacher` with required reference fields, subject and start date; optional NIK may be blank. 201, success notification and list refresh; open detail separately and verify version 1. | APPROVED |
| 7 | Versioned edit | Edit the synthetic target, save a changed name; reopen detail. Name persists and version increases by one; another fixture remains unchanged. | APPROVED |
| 8 | Stale edit | Open the same record in two tabs. Save B, then save stale A. A shows English conflict; Reload Latest loads B's values; input is preserved until reload. | APPROVED |
| 9 | Archive reason | Archive target: blank reason is rejected; `UAT browser archive test` succeeds. Target leaves active list; exit date remains unchanged. | APPROVED |
| 10 | Archive/restore | Manager opens Archived and restores target after confirmation. Record returns to active directory with increased version and cleared archive reason. | APPROVED |
| 11 | Restricted detail | Login `uat.waka`: list/detail omit NIK, NPWP, family/contact/address/education data. Operational name/NUPTK/gender and account linkage metadata are not a claim of zero PII. | APPROVED |
| 12 | Manager detail | Login operator; permitted sensitive details and education section are visible. | APPROVED |
| 13 | Permission controls | `uat.waka`: no create/edit/archive/link/restore/export controls; forced mutations/export return 403. `uat.kepsek`: view and export are allowed, but management remains forbidden. | APPROVED |
| 14 | Filtered English CSV | Operator filters one directory and exports; English reference values, explicit 10 columns, no sensitive identity/contact data, no other employee type. Formula cells remain neutralized. | APPROVED |
| 15 | Link account | Link a synthetic test profile to an active unlinked synthetic account. Account and role survive unchanged; linked/inactive accounts are not eligible candidates. | APPROVED |
| 16 | Unlink account | Unlink the same target; profile remains, account roles remain, linkage clears and legacy fallback stays disabled. | APPROVED |
| 17 | Link conflict | Open link dialog in two tabs, save B then stale A; A reports conflict rather than overwriting B. | APPROVED |
| 18 | Responsive/regression | At 390px width, use both directories and long forms; confirm dialogs remain usable. Check student/profile confirmations, reference labels and print-preview labels are English; proper names and entered data are unchanged. | APPROVED |

## Owner acceptance

- [x] Checklist #1–#18 approved by owner.
- Owner review date: 2026-10-30 WIB.
- Owner statement (verbatim): “Pengujian nomor 1-18 sudah berhasil dan saya approved.”
- Provenance: the owner's statement is the sole evidence for this acceptance; the agent did not rerun the #1–#18 scenarios to record it. Agent-executed evidence and its NOT RUN gaps stay as recorded in `PHASE-04B-REPORT.md`.
- Scope: acceptance of Subfase 04b implementation on the prepared isolated SQLite UAT. It does not authorize production/legacy-UAT migration, deployment, merge, force-push, dependency upgrades, database resets, MariaDB target access, or closure of open security risks.
