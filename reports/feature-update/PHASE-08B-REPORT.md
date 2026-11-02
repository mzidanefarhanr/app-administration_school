# Laporan Subfase 08b — Keputusan dan Rekap Izin

Tanggal: implementasi dan verifikasi agen 2026-11-02 WIB. Branch: `main`. Baseline induk: `8dcfb1d` (pencatatan DEC-36 APPROVED); paket review dilaporkan setelah commit/push (tanpa klaim SHA masa depan). Status teknis: **IMPLEMENTED — agent-verified (2026-11-02)**. Status review pemilik: **PENDING** (checklist 18 item; agen memverifikasi #1–#17, item #18 menunggu penerimaan).
Keputusan pemilik: **DEC-36 APPROVED** (D1–D8, 2026-11-02 WIB; pernyataan **"Saya setuju D1-D8"**); **DEC-35 tetap**; **DEC-13 tetap OPEN**.
Otorisasi: implementasi **08b** sesuai `reports/feature-update/PHASE-08B-PLAN.md`. 08a tetap **OWNER APPROVED #1–#16**.

## 1. Outcome dan cakupan

Subfase 08b melengkapi domain izin 08a dengan keputusan dan rekap:

- **Approve/reject.** `POST /api/leaves/{leave}/approve` dan `/reject` hanya untuk izin `submitted`, oleh aktor dengan **`leaves.approve` + `leaves.view`**, bukan pembuat, dan bukan subjek pegawai yang tertaut ke akun aktor. Approve menulis `leave_decisions` `approved` dan reject menulis `rejected`, masing-masing dengan `decided_at/by` dan kenaikan versi. Approve memakai catatan opsional 10–500; reject wajib alasan 10–500. Approve mengecek ulang overlap tetapi tidak memvalidasi ulang config/eligibility (D3).
- **Cancel approved.** Memakai route 08a `POST /api/leaves/{leave}/cancel` dengan policy bercabang: `approved` hanya dapat dibatalkan oleh approver (bukan pembuat/subjek) dengan alasan wajib 10–255; `decided_*` dipertahankan dan riwayat mencatat `approved` lalu `cancelled`. `rejected`/`cancelled` final. Pembuat/`leaves.manage` tanpa ability approve tidak dapat membatalkan izin approved.
- **Rekap.** `GET /api/leaves/recap` (middleware `leaves.view`) menghitung izin yang tanggalnya beririsan dengan periode, inklusif WIB, dengan `days` dipotong ke irisan. `from`/`to` wajib, maksimum 366 hari, default semua status. Filter `status`, `type`, `subject_type`, `school_year_id`, `rombel_id`, `employee_type_id`; kelas/tahun memaksa subjek siswa dan tipe pegawai memaksa subjek pegawai. Agregat: `totals`, `by_status`, `by_type`, `by_class`, `by_employee`; kelas memakai `withTrashed()`; siswa tanpa kelas tampil `Unplaced`. Tanpa alasan/lampiran/NIK/teks bebas dan tanpa ekspor.
- **Policy dan UI.** `permissions` objek kini server-driven (`approve`/`reject` hanya untuk `submitted` + `decides()`, `cancel` untuk draft/submitted atau approved + `decides()`). UI menambahkan aksi baris/detail Approve/Reject, dialog alasan, Cancel Approved, konflik `409` + Reload Latest, dan tab **Recap** (`leaves.view`) dengan filter, kartu total, empat tabel agregat, empty/loading/error.
- **Tanpa schema/ability/dependency baru.** Tidak ada tabel, kolom, migration, ability, peran, atau dependency baru. `leave_decisions` yang sudah ada kini menulis ketiga aksi; rekap dihitung saat baca.

Tidak dikerjakan pada 08b (non-goals D1/D6/D7): route keputusan ke-17, delegasi per-permintaan, approval berjenjang, notifikasi email/WhatsApp, ekspor/PDF rekap, PDF surat izin, akun siswa/orang tua, absensi otomatis, saldo cuti, retensi DEC-13, perubahan service/`.env`/dependency/lockfile, migrasi database operasional/MariaDB, merge/deploy/force-push.

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File | Perubahan | Schema/izin |
|---|---|---|
| `app/Policies/LeaveRequestPolicy.php` | `decides()` (`leaves.approve` + `leaves.view`, bukan pembuat/subjek); `approve()`/`reject()`; `cancel()` bercabang per status | Tanpa ability baru |
| `app/Services/Leaves/LeaveRequestService.php` | `approve()`, `reject()`, perluasan `cancel()`, `recap()`, opsi rekap/decision, `permissions()` dinamis, cek overlap/versi/alasan | Transaksi + `lockForUpdate` |
| `app/Http/Controllers/LeaveController.php` | Handler `approve`, `reject`, `recap` | 3 route 08b |
| `app/Http/Requests/ApproveLeaveRequestRequest.php` | `expected_version` wajib; `reason` nullable 10–500; authorize `leaves.approve` | `422`/`403` |
| `app/Http/Requests/RejectLeaveRequestRequest.php` | `expected_version` wajib; `reason` wajib 10–500 | `422`/`403` |
| `app/Http/Requests/RecapLeaveRequestRequest.php` | `from`/`to` wajib `Y-m-d`, `to>=from`; filter whitelist; ≤366 hari; kombinasi filter tidak sah `422` | `422` |
| `app/Http/Requests/CancelLeaveRequestRequest.php` | Authorize menambahkan `leaves.approve` (cancel-approved) | Policy tetap menegakkan objek |
| `app/Models/LeaveRequest.php` | Konstanta `RECAP_MAX_RANGE_DAYS = 366` | Tanpa schema |
| `routes/api.php` | `GET /recap` sebelum `/{leave}`; `POST /{leave}/approve` dan `/reject`; `whereNumber` | 16 route total |
| `tests/Feature/Leaves/LeaveRequestTest.php` | 8 tes baru; total **30 tes / 473 assertions** | T17–T20/T26/T29b/T27b |
| `scripts/phase08-uat.php` | Command `prepare-decisions` idempoten + status diperluas | Hanya `.uat-phase08` |
| `vue-app/src/views/pages/public/Leaves.vue` | Aksi Approve/Reject, dialog keputusan, Cancel Approved, tab Recap, filter/agregat, `409` Reload Latest | Tombol mengikuti `can` server |

Kontrak endpoint 08b (detail respons di `docs/API-CONTRACTS.md`):

| # | Method | Endpoint | Izin | Catatan |
|---|---|---|---|---|
| 14 | POST | `/api/leaves/{leave}/approve` | `leaves.approve` + policy | `expected_version` wajib; `reason` opsional 10–500; `submitted → approved`; overlap dicek ulang |
| 15 | POST | `/api/leaves/{leave}/reject` | `leaves.approve` + policy | `expected_version` wajib; `reason` wajib 10–500; `submitted → rejected` |
| 16 | GET | `/api/leaves/recap` | `leaves.view` | `from`/`to` wajib; ≤366 hari; filter opsional; agregat tanpa PII |

- **Respons rekap**: `period {from,to,days}`, `filters`, `totals {requests,days,students,employees}`, `by_status`, `by_type`, `by_class`, `by_employee`. Tidak ada alasan/lampiran/path/hash/NIK/NISN.
- **Error**: `403` tanpa ability/self-decision; `404` objek tidak terlihat; `409` status/versi tidak sesuai dengan `latest` aman; `422` validasi/alasan/overlap/kombinasi filter.
- **Opsi additive**: `decision_reason` hanya untuk pemegang `leaves.approve`; `recap` (max 366 hari, school years, rombels, employee types) hanya untuk `leaves.view`; `can.approve`.
- **UI**: tab **Recap** hanya tampil untuk `leaves.view`; guru (`leaves.request` saja) hanya melihat My Leaves dan menerima `403` untuk daftar/rekap.

## 3. Bukti verifikasi

- **Tes backend**: `LeaveRequestTest` **30 tes / 473 assertions PASS** (22 tes 08a tetap lulus + 8 tes keputusan/rekap). Cakupan: approve/reject sah vs pembuat/subjek/peran tanpa ability (`403`), hanya `submitted` (`409`), versi basi/replay (`409`), batas alasan, cek ulang overlap, cancel-approved + `decided_*` dipertahankan, rejected/cancelled final, riwayat append-only, rekap inklusif/clipping/filter/366 hari/`from>to`, route order, IDOR `404`, dan GET tidak menulis.
- **Regresi Letters**: **86 passed / 1.744 assertions PASS**.
- **Kualitas kode**: Pint pada file PHP berubah **PASS**; `npx eslint` non-mutasi pada `Leaves.vue` **0 error** (warning Prettier lama); `npm run build` **PASS**; `php artisan route:list --path=leaves` **16 route**.
- **UAT `.uat-phase08`** (SQLite; API `127.0.0.1:18026`; SPA `127.0.0.1:14026`; cookie `school_phase08_session`; launcher `scripts/phase08-uat.php`): `prepare-decisions` idempoten — **124 izin** (34 draft, 41 submitted, 21 cancelled, 17 approved, 11 rejected; 101 siswa, 23 pegawai), **49 lampiran/49 file**, **55 keputusan** (approved 23, rejected 11, cancelled 21), 123 siswa/123 keanggotaan aktif, 21 pegawai (21 aktif; 2 tertaut), 6 akun. Target fixture 20 approved/10 rejected/5 approved→cancelled; keputusan tambahan berasal dari pengujian browser. Self leave `#123` dan subject-self decoy `#124` tersedia untuk uji self-decision.
- **Matriks peran/browser/API**:
  - `uat.operator`: approve/reject `#98` v2 → `403`; cancel `#122` v4 → `403`.
  - `uat.admin`: daftar `200`, approve `403`, cancel-approved `403`, rekap `200`; UI 20 baris hanya **View**.
  - `uat.waka`: approve/reject/cancel `403`; tidak ada aksi keputusan di UI.
  - `uat.guru`: hanya **My Leaves**; daftar `403`, rekap `403`.
  - `uat.kepsek`: approve `#98` v2 → `200` (Approved, versi 3, Decided by UAT Kepala Sekolah); dialog basi → `409` "Conflict: Only submitted leave requests can be approved." + **Reload Latest**; reload menampilkan Approved/versi 3.
- **Rekap default 01 Nov 2026 – 02 Nov 2026** (2 hari): `requests=4`, `days=5`, `students=2`, `employees=2`; `by_status` approved 1/1, rejected 1/2, cancelled 2/2; `by_class` X-A 1/1, X-B 1/1; `by_employee` Employee 01 1/1, Employee 09 1/2.
- **Batas inklusif/clipping**: izin `#121` (mulai 2026-11-02) dan `#40` (berakhir 2026-11-01) terhitung; total hari 5 sama dengan clipping. Izin `#109` (2026-10-10 s.d. 2026-11-08; 30 hari) dihitung 2 hari; izin `#40` (2 hari) dihitung 1 hari.
- **Filter**: rentang 2026-11-01 s.d. 2027-10-31 — semua 76 izin/211 hari/62 siswa/13 pegawai; status approved 2/4; jenis annual + subjek pegawai 4/7; subjek siswa 62/122; tahun ajaran 1 62/122; kelas 5 10/10; tipe pegawai 1 6/16. UI status Approved default → 1/1/0/1; UI kelas X-B → 1/1/1 siswa/cancelled dengan subjek otomatis Student.
- **Edge case terisolasi** (nilai dipulihkan): `#121` tanpa kelas → `{class:"Unplaced", rombel_id:null, requests:1, days:1}`; kelas X-A (`id=2`) soft-deleted → rekap tetap `UAT-P08 X-A`; `student_rombel_id` dipulihkan ke `4`, `deleted_at` kelas ke `NULL`.
- **UI/viewport**: 390×844 tanpa overflow halaman (`scrollWidth=clientWidth=390`), tabel scroll horizontal (1106 vs 250), dialog muat, Tab `leaveDecisionReason` → Close → Approve, Escape menutup; 3 console error hanya probe yang disengaja (`409` approve, `403` rekap, `403` daftar guru).

## 4. NOT RUN

- Suite penuh PHPUnit: **NOT RUN** karena `Tests\Feature\Backup\MariaDbIntegrationTest` membaca `school_access_uat` (batas akses pemilik); gate tes tersebut sebelum run penuh berikutnya.
- Race MariaDB/InnoDB/gap-lock dan keputusan paralel multi-proses nyata; seluruh runtime memakai SQLite.
- Dua tab browser nyata untuk `409`; konflik disimulasikan lewat API + dialog basi di UI.
- Restore drill yang memuat tabel izin; visual regression berbasis file gambar; benchmark volume besar; email/WhatsApp/notifikasi; ekspor/PDF rekap.
- Verifikasi browser seluruh skenario manual di luar daftar bukti di atas; checklist pemilik tetap disediakan.

## 5. Status UAT dan langkah berikutnya

- Server UAT **masih berjalan** (API `127.0.0.1:18026`, SPA `127.0.0.1:14026`) karena penerimaan pemilik belum ada. Sesuai DEC-27, server dihentikan **setelah** penerimaan dengan verifikasi PID/command line/port; `.uat-phase08` dipertahankan; Apache/MariaDB tidak disentuh.
- Review manual pemilik: checklist **18 item** di `PHASE-08B-MANUAL-REVIEW.md`; item #1–#17 telah diverifikasi agen, item #18 menunggu penerimaan.
- Setelah penerimaan, hentikan server UAT dan catat penerimaan pada commit dokumentasi terpisah. Merge/deploy/force-push, migrasi database operasional/MariaDB, perubahan dependency/service/`.env`, dan retensi DEC-13 tetap tidak diotorisasi.
