# Phase 05a — Owner Manual Review (Students CSV Import)

- Date prepared: 2026-10-30 WIB. Branch: `main`; HEAD before 05a package: `b093db7`. Package 05a is **uncommitted** while this checklist is PENDING.
- Technical report: `PHASE-05A-REPORT.md`. Decision: **DEC-25** (D1–D8, APPROVED 2026-10-30 WIB).
- Owner status: **PENDING** — all scenarios below are **NOT RUN** by the owner. Agent DOM/HTTP verification does **not** count as owner acceptance.
- Scope: student CSV import only (05a). Employee import, export column options and school recap are not part of this checklist.

## Safe review environment

Use only the **new isolated Phase 05 SQLite UAT**:

- Frontend: `http://127.0.0.1:14023`
- API: `http://127.0.0.1:18023`
- Database: `.uat-phase05/database.sqlite` (outside the API public directory, ignored by Git)
- Session cookie: `school_phase05_session`
- Runtime `.uat-phase05/` is private and ignored. The UAT password is in `.uat-phase05/account-password.txt`; do **not** copy it into reports, chat or Git.
- Fixtures (synthetic): 409 active students (103 placed + 306 unplaced), 200 employees (100 Teachers / 100 Staff), 100 synthetic schools. Never use real student data in this environment.
- Sample CSVs: `.uat-phase05/samples/students-import-valid.csv`, `students-import-mixed.csv`, `students-import-update.csv`.
- PHP: `C:\xampp\php82-operational\php.exe`.

Read-only guard (from `laravel-api`):

```bash
"C:/xampp/php82-operational/php.exe" scripts/phase05-uat.php status
```

Expected: `[ISOLATION GUARD PASS]`, `students.import` present for `admin-sistem, operator-sekolah`, and the counts above. The launcher rejects process overrides, database URLs and cached config/routes. Never substitute root `artisan --env=uat`: excluded targets must not be touched.

If the isolated servers are not running, start **only** these:

```bash
# laravel-api
"C:/xampp/php82-operational/php.exe" -S 127.0.0.1:18023 -t public scripts/phase05-uat.php
# vue-app
VITE_API_BASE_URL=http://127.0.0.1:18023 npm run dev -- --host 127.0.0.1 --port 14023 --strictPort
```

Do not restart/stop the existing 04b UAT or any owner service, alter `.env`/services/grants, or connect MariaDB for this review.

## Sample files and one-use caveat

- `students-import-valid.csv`: 3 students, NIS `42001–42003`, NIK/NISN `056…`; entirely valid.
- `students-import-mixed.csv`: 3 rows; row 2 has NIK `12345` (not 16 digits), row 3 has `Unknown Value` gender; row 1 is valid. **Nothing may be committed** while errors exist.
- `students-import-update.csv`: one row matching **NIS `40001`** (created by the valid sample) and changing gender to Female plus address/phone.
- Run the valid sample **once**; re-uploading it later is expected to fail on duplicate NIS/NIK/NISN (that is scenario 6, not a defect). To test again, edit NIS/NIK/NISN values to unused ones.
- Account roles: `uat.operator` has `students.import`; `uat.kepsek` has `students.view` only.

## Review checklist

| # | Scenario | Steps and expected result | Owner status |
|---|---|---|---|
| 1 | Isolation and fixtures | Run the `status` command. Expected: isolation guard PASS, `students.import` on `admin-sistem`/`operator-sekolah`, 409 students and 100+100 employees. | PENDING |
| 2 | Permission visibility | Sign in as `uat.operator` → Students; **Import** button is visible next to Export. Sign in as `uat.kepsek` (new session/incognito): Import button is absent. | PENDING |
| 3 | Template download | Students → Import → **Download Template**. Expected: CSV named `student-import-template.csv` with 17 English headers (`Name,NIK,NIS,NISN,Gender,...,Phone`). | PENDING |
| 4 | Valid preview without mutation | Upload `students-import-valid.csv` → **Upload and Preview**. Expected: Status “Ready to commit”, Total 3 / Valid 3 / Invalid 0, rows NIS 42001–42003 marked **Insert**, and the page total behind the dialog stays 409. | PENDING |
| 5 | Commit valid batch | Click **Commit Import**. Expected: green committed state, toast “3 student(s) imported.”, dialog preview retained, and the Students total becomes **412** after the list refreshes. | PENDING |
| 6 | Duplicate identity blocked | Upload the same `students-import-valid.csv` again. Expected: Status “Needs fixes”, all rows invalid with NIK/NIS/NISN “already registered”, no Commit button, total remains 412. | PENDING |
| 7 | Mixed file all-or-nothing | Upload `students-import-mixed.csv`. Expected: 1 valid / 2 invalid, Status “Needs fixes”, no Commit button, error list shows “NIK must be exactly 16 digits” (row 3) and “Unknown Gender value” (row 4). Total unchanged. | PENDING |
| 8 | Error report download | In the mixed result click **Download Error Report**. Expected: CSV with headers `Row,Field,Message,NIS,NIK` and exactly those two errors. The file must come from the private endpoint, not a public URL. | PENDING |
| 9 | Update mode by NIS | After step 5, choose Mode **Update existing students** → upload `students-import-update.csv` → Preview. Expected: one row, Action **Update**, NIS 40001 (Name shows “-”). Commit: toast “1 student(s) imported.”, Phase05-equivalent row (Synthetic Import One) becomes **Female**, and total stays 412 (no new row). | PENDING |
| 10 | Header validation | Upload a CSV with an unknown or missing column (e.g. rename `NIS` to `Nomor Induk`). Expected: upload rejected with an English message naming the unknown/missing header; no batch committed. | PENDING |
| 11 | File type and size limits | Try a `.txt`/`.xlsx` file and a file over 5 MB. Expected: client blocks with an English message; the server rejects oversized/invalid uploads with 422 rather than truncating. | PENDING |
| 12 | Empty / no data rows | Upload a CSV containing only the header. Expected: server rejects with a clear English validation message; no batch is created. | PENDING |

Notes for reporting issues: include the scenario number, what you saw, and any browser console/network error (HTTP status). Do not paste student PII or the UAT password.

## Evidence boundary

Agent-side verification (DOM interaction, network responses, no screenshots) is recorded in `PHASE-05A-REPORT.md` §3. It covers steps 3–9, including valid commit (406→409 at that time), mixed rejection, error CSV download and update-by-NIS. Steps 1–2 and 10–12 remain owner-review items even though equivalent backend tests exist. MariaDB integration, automatic 30-day retention cleanup, XLSX import and employee import are **NOT RUN / not in scope**.
