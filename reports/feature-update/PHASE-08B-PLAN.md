# Rencana Subfase 08b — Keputusan Izin (Approve/Reject/Cancel Approved) dan Rekap

- Tanggal: 2026-11-02 (WIB). Branch: `main`. Baseline audit: `618b8f8` (pencatatan penerimaan 08a). Paket rencana: commit dokumentasi setelah file ini.
- Status dokumen: **PLAN APPROVED — DEC-36 APPROVED (2026-11-02 WIB)** berdasarkan pernyataan pemilik **"Saya setuju D1-D8"**; implementasi 08b diotorisasi.
- Otorisasi saat ini: **DEC-36 APPROVED** (D1–D8, 2026-11-02 WIB), **DEC-35 APPROVED** (D1–D12; **DEC-07 CLOSED**), 08a **IMPLEMENTED + agent-verified + OWNER APPROVED #1–#16** (2026-11-02 WIB). Implementasi 08b mengikuti D1–D8 pada dokumen ini.
- Cakupan paket ini: **rencana 08b** — keputusan approve/reject, pembatalan izin approved oleh approver, rekap izin, policy/otorisasi, UI, tes, UAT, dan dokumentasi. **Tidak ada kode, migration, route, model, service, request, controller, komponen, tes, atau fixture UAT yang dibuat/diubah oleh paket rencana ini.**
- Gate: **DEC-13 tetap OPEN**; **DEC-11 CLOSED untuk lingkup 07c**; **DEC-06 CLOSED**. Merge/deploy/force-push, migrasi database operasional/MariaDB, perubahan dependency/lockfile, perubahan service/`.env`, dan fase selain 08b **tidak diotorisasi**.
- Dokumen acuan: `reports/feature-update/PHASE-08-PLAN.md` (§§4.3–4.10, D5/D7, T17–T20/T26/T29, S7–S9), `roadmap/phases/PHASE-08-LEAVES.md`, `docs/DECISIONS.md` (DEC-35), `docs/API-CONTRACTS.md` (08a), `docs/ACCESS-CONTROL.md` (08a), `docs/DATABASE.md` (08a), `docs/REQUIREMENTS.md` FR-10, `reports/feature-update/PHASE-08A-REPORT.md`, `PHASE-08A-MANUAL-REVIEW.md`.
- Lingkungan UAT: `.uat-phase08` (SQLite/storage terisolasi; API `127.0.0.1:18026`, SPA `127.0.0.1:14026`, cookie `school_phase08_session`, launcher `laravel-api/scripts/phase08-uat.php`). Server 08a **sudah dihentikan** setelah penerimaan (DEC-27); data dipertahankan. 08b memakai ulang lingkungan ini secara additive.

---

## 1. Posisi dan prasyarat

| Prasyarat | Kondisi aktual 2026-11-02 | Status |
|---|---|---|
| Subfase 08a | Pengajuan siswa/pegawai, draft/submitted/cancelled, lampiran privat, 13 route, UI Requests/My Leaves; owner approved #1–#16; 22 tes/209 assertions; UAT dihentikan | Terpenuhi |
| Schema keputusan | `leave_requests` sudah memiliki `decided_at`/`decided_by`, `cancelled_at`/`cancelled_by`, `version`; `leave_decisions` sudah mendukung `approved`/`rejected`/`cancelled`; index `(leave_request_id, created_at)` | Terpenuhi; **tanpa migration baru** |
| Ability | `leaves.approve` ada dan sensitif; default hanya Kepala Sekolah; Admin/Operator/Wakil/Guru tidak memiliki approve | Terpenuhi; tidak perlu ability/peran baru |
| Konfigurasi | `config/leaves.php` sudah memuat `decision_reason` 10–500 dan `cancel_reason` 10–255; `decision_reason` belum dipakai | Terpenuhi |
| Workflow 08a | `draft → submitted`, `draft/submitted → cancelled`; `approved`/`rejected` sudah menjadi status final yang disiapkan tetapi belum dapat dicapai | Perlu transisi 08b |
| Policy | `LeaveRequestPolicy` memiliki view/create/update/submit/cancel/upload/attachment; **belum ada approve/reject**; `cancel` hanya untuk pembuat/`leaves.manage` | Perlu perluasan |
| Service | `cancel()` menolak approved dengan `409` "later phase"; belum ada `approve()`/`reject()`/`recap()` | Perlu implementasi |
| API | 13 route `/api/leaves*`; **belum ada** route keputusan/rekap; `GET /{leave}` terdaftar sebelum route statis baru harus diurutkan | Perlu 3 route |
| UI | Tab Requests/My Leaves; belum ada tombol approve/reject/cancel-approved atau tab Recap; `can.approve` dari options belum dipakai | Perlu perluasan |
| Tes | 22 tes 08a; belum ada tes keputusan/rekap | Perlu tes baru |
| UAT | `.uat-phase08` berisi 122 izin (34 draft/73 submitted/15 cancelled), 49 lampiran/49 file, 15 keputusan; **belum ada approved/rejected**; server berhenti | Perlu fixture additive `prepare-decisions` |
| Keputusan | DEC-35 APPROVED untuk seluruh Fase 08; 08b tetap memerlukan instruksi terpisah (D1 DEC-35); DEC-13 OPEN | Gate D1–D8 |

Seluruh kondisi di atas diverifikasi dari source, migration, konfigurasi, tes, dan launcher aktual, bukan dari data operasional.

---

## 2. Temuan audit (fakta kode)

### 2.1 Schema dan model sudah siap; tidak perlu migration

- `leave_requests` sudah memiliki `decided_at`, `decided_by`, `cancelled_at`, `cancelled_by`, dan `version`; status `approved`/`rejected` sudah termasuk konstanta dan allowlist audit: `laravel-api/app/Models/LeaveRequest.php:20-64`.
- `leave_decisions` sudah memiliki `action` (`approved`/`rejected`/`cancelled`), `from_status`/`to_status`, `actor_id`, `reason` nullable, dan hanya `created_at`; model menolak update/delete: `laravel-api/app/Models/LeaveDecision.php:14-34`.
- Migration 08a sudah membuat semua kolom/tabel yang dibutuhkan: `2029_08_04_000001_create_leave_requests_table.php`, `2029_08_04_000002_create_leave_decisions_table.php`.
- `config/leaves.php:53` sudah menetapkan `decision_reason => ['min' => 10, 'max' => 500]`.

**Implikasi:** 08b murni logika/service/API/UI. Tidak ada migration, backfill, perubahan schema, atau dependency baru. `leave_decisions` tetap append-only.

### 2.2 Policy dan service belum memiliki jalur keputusan

- `LeaveRequestPolicy` tidak memiliki `approve`/`reject`; `cancel()` hanya memanggil `modifies()` (pembuat atau `leaves.manage`): `laravel-api/app/Policies/LeaveRequestPolicy.php:50-53`, `:80-84`.
- `LeaveRequestService::cancel()` mengizinkan draft/submitted, lalu menolak approved dengan pesan "can only be cancelled by an approver in a later phase": `laravel-api/app/Services/Leaves/LeaveRequestService.php:244-295`.
- `permissions()` selalu mengembalikan `approve => false` dan `reject => false`; `cancel` hanya true untuk draft/submitted: `LeaveRequestService.php:1196-1213`.
- `options()` sudah mengembalikan `can.approve` berdasarkan ability, tetapi UI belum memakainya: `LeaveRequestService.php:39-100`.

**Implikasi:** tambah policy `approve`/`reject`/pembatalan approved, service `approve()`/`reject()`/`recap()`, dan perluas `cancel()`/`permissions()`. Semua tetap memakai transaksi, `lockForUpdate`, dan `expected_version` yang sudah ada (`:873-882`, `:884-902`).

### 2.3 Controller dan route belum memiliki keputusan/rekap

- `LeaveController` memiliki 13 handler 08a; tidak ada `approve`, `reject`, atau `recap`: `laravel-api/app/Http/Controllers/LeaveController.php:23-147`.
- Route izin berada di `laravel-api/routes/api.php:283-309`; `GET /{leave}` terdaftar di `:294`. Route statis `GET /recap` **wajib didaftarkan sebelum** `GET /{leave}` agar tidak tertangkap sebagai ID.
- FormRequest `CancelLeaveRequestRequest::authorize()` hanya mengizinkan `leaves.manage`/`leaves.request`; approver yang hanya memiliki `leaves.approve` + `leaves.view` akan ditolak sebelum policy. Perlu diperluas untuk pembatalan approved.

**Implikasi:** tambah `ApproveLeaveRequestRequest`, `RejectLeaveRequestRequest`, `RecapLeaveRequestRequest`, tiga handler controller, dan tiga route (total 16 route izin sesuai DEC-35). Tidak menambah route di luar 16.

### 2.4 UI belum memiliki aksi keputusan atau rekap

- Tab hanya **Requests** dan **My Leaves**: `vue-app/src/views/pages/public/Leaves.vue:966-971`.
- Aksi baris hanya View/Edit/Submit: `Leaves.vue:1077-1081`; tombol detail hanya Edit/Submit/Cancel: `Leaves.vue:1175-1179`.
- `cancelRequiresReason` hanya true untuk `submitted`; dialog cancel menyebut submitted/draft: `Leaves.vue:703-775`, `:1308-1319`.
- Riwayat keputusan sudah dirender (action/actor/reason/time), termasuk status approved/rejected message: `Leaves.vue:1182-1186`, `:1260-1277`.
- Belum ada tab Recap, filter rekap, kartu agregat, dialog approve/reject, atau tombol cancel-approved.

**Implikasi:** perluas halaman yang sama; tidak membuat halaman/menu baru. Tab Recap memakai ability `leaves.view`; aksi keputusan mengikuti `can.approve`/`can.reject` per objek dari server.

### 2.5 Tes dan UAT belum mencakup keputusan/rekap

- `tests/Feature/Leaves/LeaveRequestTest.php` memiliki 22 tes (T1–T16, T21–T25, T27, T29) dan helper `p08a*`; tidak ada approve/reject/recap: `:217-752`.
- UAT `prepare-leaves` hanya menghasilkan draft/submitted/cancelled; tidak ada approved/rejected: `laravel-api/scripts/phase08-uat.php:330-399`.
- `status` sudah mencetak approved/rejected (saat ini 0): `phase08-uat.php:403-427`; allowed commands masih `init, prepare, prepare-leaves, status`: `:429`.
- Fixture UAT sudah memiliki 120 siswa/3 rombel, 20 pegawai, 122 izin, 49 lampiran; tanggal mulai 2026-07-06 tersebar beberapa bulan, sehingga cocok untuk rekap periode.

**Implikasi:** tambah tes T17–T20/T26/T29b dan regresi 08a; tambah perintah idempotent `prepare-decisions` dan fixture approved/rejected/cancel-approved tanpa mereset data 08a.

### 2.6 Dokumen status masih menyatakan 08b belum dimulai

- `roadmap/phases/PHASE-08-LEAVES.md:3` — 08b NOT STARTED/PENDING.
- `docs/API-CONTRACTS.md` — 13 route 08a, "tidak ada route keputusan/rekap 08b".
- `docs/ACCESS-CONTROL.md:125` — larangan self-decision "08b"; `leaves.approve` belum ditegakkan.
- `docs/DATABASE.md:45-47` — approved/rejected belum dapat dicapai pada 08a.
- `docs/REQUIREMENTS.md` FR-10 — persetujuan/penolakan/rekap 08b menunggu instruksi.

**Implikasi:** paket rencana ini menyinkronkan status menjadi **08b PLAN — AWAITING OWNER APPROVAL**; kontrak API/DB/akses baru diperbarui saat implementasi, bukan sekarang.

---

## 3. Scope dan non-goals

### 3.1 Scope 08b

1. **Approve** izin `submitted → approved` oleh approver berwenang, dengan alasan opsional dan riwayat keputusan.
2. **Reject** izin `submitted → rejected` oleh approver berwenang, dengan alasan wajib.
3. **Cancel approved** `approved → cancelled` oleh approver berwenang, dengan alasan wajib; keputusan lama tetap di riwayat.
4. **Rekap izin** per periode dengan filter jenis/status/subjek/tahun ajaran/kelas/tipe pegawai dan agregat per status/jenis/kelas/pegawai.
5. **Server-side authorization** untuk semua aksi, termasuk larangan self-decision dan larangan pembuat/`leaves.manage` membatalkan izin approved.
6. **UI** tombol/aksi keputusan di Requests dan detail, dialog alasan, serta tab **Recap**.
7. **Tes** T17–T20/T26/T29b, regresi 08a, dan verifikasi build/lint/Pint.
8. **UAT** additive `.uat-phase08` dengan `prepare-decisions`, fixture approved/rejected/cancel-approved, dan checklist manual.

### 3.2 Non-goals (tidak dikerjakan pada 08b)

- Tidak ada tabel/kolom/migration/ability/peran baru; tidak ada perubahan `config/leaves.php` kecuali jika implementasi menemukan bug yang menghalangi (harus dilaporkan, bukan diam-diam).
- Tidak ada dependency/lockfile baru, perubahan service/`.env`, atau migrasi database operasional/MariaDB.
- Tidak ada notifikasi email/WhatsApp, PDF/CSV ekspor rekap, absensi otomatis, saldo cuti, potongan gaji, atau integrasi eksternal.
- Tidak ada delegasi per-permintaan, approval berjenjang, wali kelas sebagai approver, reopen, edit setelah submit, perubahan `approved → rejected`, atau pembatalan oleh pembuat/`leaves.manage` pada status approved.
- Tidak ada izin setengah hari/per jam/berulang/lintas tahun, akun siswa/orang tua, impor izin, atau arsip/versioning Fase 09.
- Tidak ada retensi/penghapusan permanen (DEC-13 tetap OPEN), merge/deploy/force-push, atau pekerjaan Fase 09+.

---

## 4. Desain yang diusulkan

### 4.1 Policy, approver, dan larangan self-decision

Tambahkan pada `LeaveRequestPolicy`:

- `approve(User $user, LeaveRequest $leave): bool`
- `reject(User $user, LeaveRequest $leave): bool`
- `cancelApproved(User $user, LeaveRequest $leave): bool` (dipanggil dari `cancel()` saat status approved)

Aturan `decides()`:

```text
user memiliki leaves.approve DAN leaves.view
DAN user bukan requested_by
DAN bukan subjek pegawai (subject_type=employee dan employees.user_id = user.id)
```

- `leaves.view` diperlukan agar approver dapat melihat objek dan menerima respons detail setelah aksi; default Kepala Sekolah memiliki `leaves.view` + `leaves.approve` + `leaves.request`.
- Admin teknis, Operator, Wakil Kesiswaan, dan Guru **tidak otomatis** memiliki approve; pemberian approve kepada peran lain adalah delegasi eksplisit melalui Roles & Abilities. Tidak ada bypass "admin boleh segalanya".
- `cancel()` policy:
  - draft/submitted → `modifies()` (pembuat atau `leaves.manage`) seperti 08a;
  - approved → `decides()`;
  - rejected/cancelled → `modifies() || decides()` agar service dapat mengembalikan `409` final (bukan `403`) kepada aktor yang berwenang.
- `permissions()`:
  - `approve`/`reject` = status submitted && `decides()`;
  - `cancel` = (draft/submitted && `modifies()`) || (approved && `decides()`);
  - `view` tetap true untuk objek yang terlihat;
  - `edit`/`submit`/`upload`/`delete_attachment` mengikuti 08a (hanya draft/submitted dan pembuat/manage).
- `CancelLeaveRequestRequest::authorize()` diperluas menjadi `canAny(['leaves.manage', 'leaves.request', 'leaves.approve'])`; policy tetap menolak objek/status yang tidak sah.
- `options.can.approve` diselaraskan menjadi `hasAbility('leaves.approve') && hasAbility('leaves.view')` agar UI tidak menampilkan aksi pada aktor yang tidak dapat melihat objek.

### 4.2 Workflow dan transisi

| Dari | Aksi | Aktor | Ke | Aturan |
|---|---|---|---|---|
| `submitted` | approve | `leaves.approve` + `leaves.view`, bukan pembuat/subjek | `approved` | Alasan opsional 10–500; cek ulang overlap; `decided_at/by`; versi naik; decision `approved` |
| `submitted` | reject | sama | `rejected` | Alasan wajib 10–500; tanpa cek overlap; `decided_at/by`; versi naik; decision `rejected` |
| `approved` | cancel | sama | `cancelled` | Alasan wajib 10–255; `cancelled_at/by`; `decided_at/by` dipertahankan; versi naik; decision `cancelled` from approved |
| `rejected` | — | — | final | Tidak ada reopen/cancel; koreksi via pengajuan baru |
| `cancelled` | — | — | final | — |
| draft | cancel | pembuat/`leaves.manage` | `cancelled` | Seperti 08a; alasan opsional 10–255 |
| submitted | cancel | pembuat/`leaves.manage` | `cancelled` | Seperti 08a; alasan wajib 10–255 |

Semua transisi:

- berjalan dalam `DB::transaction` dengan `lockForUpdate` pada baris izin;
- memakai `expected_version`; versi basi/status tidak valid → `409` dengan `latest` aman (id/status/version/type/tanggal);
- menulis `leave_decisions` append-only (tanpa update/delete);
- tidak mengubah lampiran, subjek, tipe, atau tanggal;
- tidak menulis alasan ke Activity/audit.

### 4.3 Approve

- Hanya status `submitted`; draft/approved/rejected/cancelled → `409`.
- `reason` opsional; jika diisi (setelah trim) harus 10–500 karakter; string kosong diperlakukan null.
- Cek ulang **overlap** terhadap subjek yang sama berstatus `submitted`/`approved`, memakai `assertNoOverlap()` yang sudah ada. Konflik → `422` `errors.overlap` berisi `#id (start to end)` tanpa data pribadi.
- **Tidak** memvalidasi ulang batas `max_days`/`max_range_days` terkini, tahun ajaran aktif, atau status master subjek. Alasan: izin sudah divalidasi saat submit; perubahan konfigurasi/master setelah submit tidak boleh membuat keputusan retroaktif gagal. Jika pemilik menghendaki validasi ketat saat approve, itu alternatif D3.
- Set `status=approved`, `decided_at` (WIB), `decided_by`, `version+1`; buat `LeaveDecision` action `approved`, from `submitted`, to `approved`, reason opsional.
- Kembalikan detail izin yang diperbarui (`show()`), termasuk `can` terbaru.

### 4.4 Reject

- Hanya status `submitted`; status lain → `409`.
- `reason` wajib; setelah trim harus 10–500 karakter; kosong/whitespace → `422`.
- Tidak ada cek overlap (penolakan membebaskan tanggal).
- Set `status=rejected`, `decided_at/by`, `version+1`; buat `LeaveDecision` action `rejected`, reason wajib.
- Kembalikan detail terbaru.

### 4.5 Cancel approved

- Memakai route 08a `POST /api/leaves/{leave}/cancel` (tidak menambah route ke-17).
- Hanya status `approved`; rejected/cancelled → `409`; draft/submitted mengikuti alur 08a.
- Aktor: `leaves.approve` + `leaves.view`, bukan pembuat/subjek. Pembuat/`leaves.manage` tanpa approve → `403`; approver yang merupakan pembuat/subjek → `403`.
- `reason` wajib 10–255 (config `cancel_reason`), setelah trim.
- Set `status=cancelled`, `cancelled_at/by`, `version+1`; **pertahankan** `decided_at/by`; buat `LeaveDecision` action `cancelled`, from `approved`, to `cancelled`, reason wajib.
- Riwayat menampilkan keputusan approved lama dan pembatalan baru; UI menampilkan "Previously approved by …" pada status cancelled bila `decided_at` ada.
- Tidak ada `approved → rejected`; tidak ada reopen.

### 4.6 Rekap izin

**Endpoint:** `GET /api/leaves/recap` (static route sebelum `GET /{leave}`), ability `leaves.view`.

**Periode:**

- `from` dan `to` wajib, format `Y-m-d`, `to >= from`, inklusif WIB.
- Maksimum rentang **366 hari** inklusif (cukup untuk satu tahun kabisat); melebihi → `422`.
- Izin dipilih jika rentang izin **beririsan** dengan periode: `start_date <= to AND end_date >= from`.
- `days` dihitung dari irisan:

  ```text
  days_in_period = max(0, min(leave.end_date, to) - max(leave.start_date, from) + 1)
  ```

  Contoh: izin 2026-10-28 s/d 2026-11-03, rekap 2026-11-01 s/d 2026-11-30 → 3 hari.
- Setiap izin dihitung **sekali** pada `totals.requests`; `totals.days` menjumlahkan irisan.
- Default status: semua (`draft`/`submitted`/`approved`/`rejected`/`cancelled`) kecuali filter status diberikan. Ini transparan untuk rekap; UI menampilkan rincian per status.

**Filter:**

- `status` (whitelist status; tanpa `all` di API),
- `type` (kode jenis; harus valid untuk `subject_type` bila diberikan),
- `subject_type` (`student`/`employee`),
- `school_year_id` (snapshot tahun ajaran siswa),
- `rombel_id` (kelas snapshot siswa; hanya student),
- `employee_type_id` (tipe pegawai; hanya employee).

Kombinasi `rombel_id` + `employee_type_id` tidak masuk akal (student vs employee); validasi → `422` agar client tidak mengirim filter mustahil. `rombel_id`/`school_year_id` secara implisit membatasi ke student; `employee_type_id` ke employee.

**Agregat (dihitung di PHP untuk portabilitas SQLite/MariaDB):**

- `totals`: `requests`, `days`, `students`, `employees`.
- `by_status`: semua status config, zero-filled, `requests`/`days`.
- `by_type`: kombinasi `subject_type` + `type` yang valid, zero-filled sesuai filter, `requests`/`days`, label dari config (fallback kode bila config berubah).
- `by_class`: hanya student; group per kelas snapshot (`student_rombels.school_rombel` → `school_rombels.name`, termasuk kelas soft-deleted via `withTrashed`); siswa tanpa kelas → `rombel_id: null`, label `Unplaced`; `requests`/`days`.
- `by_employee`: hanya employee; group per `employee_id`; nama/NUPTK/tipe pegawai (relasi `withTrashed`); `requests`/`days`; tanpa NIK/alamat/kontak.
- Urutan deterministik: status sesuai config, tipe sesuai config (student lalu employee), kelas/pegawai berdasarkan nama.

**Bentuk respons usulan:**

```json
{
  "success": true,
  "message": "Leave recap retrieved successfully.",
  "data": {
    "period": {"from": "2026-11-01", "to": "2026-11-30", "days": 30},
    "filters": {"status": null, "type": null, "subject_type": null, "school_year_id": null, "rombel_id": null, "employee_type_id": null},
    "totals": {"requests": 12, "days": 34, "students": 9, "employees": 3},
    "by_status": [{"status": "approved", "label": "Approved", "requests": 5, "days": 15}],
    "by_type": [{"subject_type": "student", "type": "sick", "label": "Sick Leave", "requests": 4, "days": 8}],
    "by_class": [{"rombel_id": 1, "class": "10-A", "requests": 5, "days": 10}],
    "by_employee": [{"employee_id": 2, "name": "Synthetic Teacher", "nuptk": "123", "employee_type": "Pendidik", "requests": 2, "days": 4}]
  }
}
```

Tidak ada `reason`, lampiran, path, hash, NIK, alamat, atau kontak di respons rekap. Tidak ada ekspor PDF/CSV di v1.

**Privasi/kinerja:**

- Query memakai eager loading (`student`, `studentRombel`, `schoolYear`, `employee.employeeType`) dan satu query tambahan untuk nama kelas `withTrashed`; tanpa N+1.
- Batas 366 hari membatasi volume; tidak ada pagination untuk agregat karena jumlah kelas/pegawai terbatas pada skala sekolah. Jika volume besar di kemudian hari, itu optimasi terpisah.
- Nama siswa/pegawai dan kelas adalah data operasional yang memang diperlukan; alasan izin tidak pernah masuk rekap.

### 4.7 Kontrak API (3 route baru, total 16)

| # | Method | Endpoint | Izin/Policy | Body/Query | Ringkas |
|---|---|---|---|---|---|
| 14 | POST | `/api/leaves/{leave}/approve` | middleware `can:leaves.approve` + policy approve (`leaves.approve`+`leaves.view`, bukan pembuat/subjek) | `expected_version` wajib; `reason` opsional 10–500 | `submitted → approved`; decision `approved`; `200`/`403`/`404`/`409`/`422` |
| 15 | POST | `/api/leaves/{leave}/reject` | sama | `expected_version` wajib; `reason` wajib 10–500 | `submitted → rejected`; decision `rejected`; `200`/`403`/`404`/`409`/`422` |
| 16 | GET | `/api/leaves/recap` | middleware `can:leaves.view` | `from`, `to` wajib; filter opsional | Agregat periode; `200`/`403`/`422` |

- Route `GET /recap` didaftarkan **sebelum** `GET /{leave}`; parameter `{leave}` memakai `whereNumber`.
- `POST /{leave}/approve` dan `/{leave}/reject` ditempatkan bersama mutasi lain; tidak bertabrakan dengan `GET /{leave}`.
- Tidak ada route ke-17. Perluasan `GET /api/leaves/options` bersifat additive:
  - `decision_reason` (min/max) bila aktor memiliki `leaves.approve`;
  - `recap` (opsi filter: school years, rombels, employee types, max range 366) bila aktor memiliki `leaves.view`;
  - `can.approve` diselaraskan dengan policy (approve + view).
- Respons mutasi: `200` dengan detail izin; validasi `422`; konflik versi/status `409` dengan `latest`; tidak berizin `403`; objek tidak terlihat `404`.
- `409` tidak menyertakan lampiran/path/alasan; hanya ringkasan aman.
- `permissions` pada detail/summary mengikuti §4.1.

### 4.8 UI

**Requests dan detail:**

- Tambah `Approve`/`Reject` pada aksi baris dan tombol detail hanya jika `data.can.approve`/`data.can.reject`.
- Dialog **Approve**: catatan opsional (textarea, 10–500 jika diisi), tombol konfirmasi; setelah sukses tutup dialog, refresh list/detail, toast.
- Dialog **Reject**: alasan wajib 10–500, tombol konfirmasi; validasi klien + server; `422` ditampilkan per field.
- Dialog **Cancel** diperluas: status `approved` juga memerlukan alasan 10–255; judul "Cancel Approved Leave"; pesan menjelaskan keputusan lama tetap di riwayat. Status `rejected`/`cancelled` tidak menampilkan tombol cancel.
- `409`: pakai dialog konflik 08a yang sudah ada, tampilkan status/versi terbaru, tombol **Reload Latest**.
- Pesan status approved/rejected sudah ada; tambahkan info "Previously approved by …" pada status cancelled bila `decided_at` tersedia.
- Tombol mengikuti `can` dari server; bukan pengganti enforcement.

**Tab Recap (`leaves.view`):**

- Tab ketiga **Recap**; tanpa tab ini untuk guru yang hanya `leaves.request`.
- Filter: `from`/`to` (default awal–akhir bulan berjalan WIB), status, jenis (dikelompokkan per subjek), subjek, tahun ajaran, kelas, tipe pegawai. Tombol Refresh/Reset.
- Ringkasan: kartu Total requests, Total days, Students, Employees; tabel **By status**, **By type**, **By class**, **By employee**.
- Empty state, loading, dan error yang terlihat; tidak ada tombol ekspor/PDF.
- Label English; layout 390 px; tabel dapat di-scroll horizontal; form/dialog full-width; keyboard/aria; tanpa tombol dummy.

**Role yang diharapkan:**

- Kepala Sekolah: Requests + Recap; approve/reject/cancel-approved pada izin yang bukan miliknya/subjeknya.
- Admin/Operator: Requests + Recap; tidak ada approve/reject/cancel-approved (kecuali didelegasikan eksplisit).
- Wakil Kesiswaan: Requests + Recap; view-only.
- Guru: hanya My Leaves; tanpa Requests/Recap/approve.

### 4.9 Audit, privasi, dan retensi

- `LeaveRequest` allowlist sudah memuat `status`, `version`, `decided_at/by`, `cancelled_at/by`; tidak perlu perubahan allowlist dan **tidak** menambahkan `reason`.
- `LeaveDecision` tetap append-only; `reason` hanya ada di tabel keputusan, tidak di Activity/audit.
- Activity dari perubahan status tetap tercatat otomatis oleh `Loggable`; alasan tidak boleh muncul.
- Rekap tidak mengekspos alasan, lampiran, path, hash, NIK, alamat, kontak, atau nama file.
- File/lampiran tidak berubah; retensi/penghapusan permanen tetap menunggu DEC-13.

---

## 5. Keputusan yang perlu pemilik (D1–D8)

Rekomendasi ditulis sebagai default. **Belum ada keputusan yang dianggap disetujui sampai pemilik menyatakan persetujuan eksplisit.**

| # | Pertanyaan | Rekomendasi | Alternatif / dampak |
|---|---|---|---|
| **D1** | Scope dan otorisasi 08b? | Implementasikan **08b saja**: approve/reject/cancel-approved + rekap + UI/tes/UAT. Tidak ada tabel/kolom/migration/ability/peran/dependency baru. UAT memakai ulang `.uat-phase08` secara additive. | Menambah notifikasi/ekspor/PDF (scope baru); membuat tabel delegate (tidak disetujui); mereset UAT (melanggar DEC-27). |
| **D2** | Siapa approver dan bagaimana self-decision? | `leaves.approve` + `leaves.view`, bukan pembuat, bukan subjek pegawai. Default hanya Kepala Sekolah; admin/operator/waka/guru tidak otomatis. Delegasi lewat Roles. | Admin otomatis approve (melanggar DEC-03); wali kelas approver (butuh scope baru); self-approval diizinkan (tidak aman). |
| **D3** | Semantik approve/reject dan validasi ulang? | Approve/reject hanya `submitted`; approve reason opsional, reject wajib 10–500; approve cek ulang overlap saja; tidak memvalidasi ulang config/master terkini; transisi atomik + `expected_version`. | Validasi ketat tanggal/eligibility saat approve (izin lama bisa gagal setelah config berubah); tanpa cek overlap (data ganda). |
| **D4** | Bagaimana cancel approved? | Pakai route `POST /cancel` yang ada; hanya approver (bukan pembuat/subjek); alasan wajib 10–255; `decided_*` dipertahankan; rejected/cancelled final; pembuat/manage tidak boleh cancel approved. | Route cancel terpisah (menambah route ke-17); pembuat/manage boleh cancel approved (melanggar pemisahan tugas); approved→rejected (keputusan tidak stabil). |
| **D5** | Semantik periode rekap? | Periode berdasarkan **tanggal izin** yang beririsan, inklusif WIB; `days` dipotong ke irisan periode; `from`/`to` wajib; maks 366 hari; default semua status. | Berdasarkan tanggal keputusan/submit (bukan periode izin); menghitung durasi penuh (periode parsial membengkak); tanpa batas rentang (query berat). |
| **D6** | Agregat dan privasi rekap? | `totals`, `by_status`, `by_type` (subject+type), `by_class`, `by_employee`; filter status/type/subject/tahun/kelas/tipe pegawai; tanpa alasan/lampiran/NIK; tanpa ekspor. | Hanya total (tidak memenuhi 08b); menyertakan alasan/lampiran (PII); ekspor CSV (scope tambahan). |
| **D7** | UI keputusan dan rekap? | Aksi Approve/Reject di tabel+detail, dialog alasan, cancel-approved; tab Recap; English; 390 px; `409` Reload Latest; tanpa tombol dummy. | Halaman terpisah (duplikasi); hanya detail tanpa aksi baris (lebih lambat); menyembunyikan tombol tanpa enforcement server (tidak aman). |
| **D8** | UAT, tes, dan dokumentasi? | `prepare-decisions` idempotent (approved/rejected/cancel-approved + satu self leave untuk uji self-decision); checklist 18 item; tes T17–T20/T26/T29b + regresi 08a; Pint/build/lint; server UAT dihentikan setelah penerimaan; dokumentasi kontrak diperbarui saat implementasi. | Fixture manual tanpa perintah (tidak reproducible); menjalankan suite penuh (menyentuh `school_access_uat`); membiarkan server berjalan (melanggar DEC-27). |

**Ringkasan pertanyaan terbuka:**

| Q | Jawaban yang diusulkan |
|---|---|
| Q1 — apakah approve/reject mengubah data master? | Tidak; hanya status/versi/decided_* dan `leave_decisions`. |
| Q2 — apakah alasan approve wajib? | Tidak; opsional 10–500. Reject wajib 10–500. Cancel approved wajib 10–255. |
| Q3 — apakah approve memvalidasi ulang overlap? | Ya; terhadap submitted/approved subjek yang sama. |
| Q4 — apakah approve memvalidasi ulang tahun ajaran/config? | Tidak; validasi sudah dilakukan saat submit. |
| Q5 — siapa boleh cancel approved? | Hanya `leaves.approve` + `leaves.view`, bukan pembuat/subjek. |
| Q6 — apakah rekap memakai tanggal izin atau tanggal keputusan? | Tanggal izin (overlap periode), dengan hari dipotong ke irisan. |
| Q7 — apakah rekap menyertakan draft/cancelled? | Ya, default semua status; filter status tersedia. |
| Q8 — apakah ada ekspor? | Tidak; hanya JSON dan tabel UI. |

---

## 6. Urutan pekerjaan yang diusulkan

| Langkah | Pekerjaan | Hasil yang dapat diverifikasi |
|---|---|---|
| S0 | Preflight: audit HEAD `618b8f8`, pastikan 08a bersih, `.uat-phase08` ada, tidak ada route/tes 08b, catat persetujuan D1–D8 | Baseline jelas; belum ada perubahan kode |
| S1 | Policy: `approve`/`reject`/`decides`/`cancel` bercabang; `permissions()`; `CancelLeaveRequestRequest` authorize | Matriks ability/self-decision teruji |
| S2 | Service: `approve()`, `reject()`, perluas `cancel()`; validasi alasan/versi/overlap; `LeaveDecision`; 409/422 | Transisi atomik + riwayat |
| S3 | Request/controller/route: `ApproveLeaveRequestRequest`, `RejectLeaveRequestRequest`, `RecapLeaveRequestRequest`; `approve`/`reject`/`recap`; route statis sebelum `/{leave}`; opsi additive | 16 route izin; route order benar |
| S4 | Service `recap()`: query overlap, filter, clip hari, agregat, eager loading, kelas withTrashed; respons aman | Rekap benar untuk batas/filter |
| S5 | UI keputusan: tombol baris/detail, dialog approve/reject, cancel-approved, `409` Reload Latest | Alur keputusan end-to-end |
| S6 | UI Recap: tab, filter, kartu, tabel, empty/loading/error, 390 px | Rekap dapat dipakai |
| S7 | Tes T17–T20/T26/T29b + regresi 08a + Pint/build/lint | Matriks PASS/NOT RUN |
| S8 | UAT `prepare-decisions`, browser/API checks, laporan + checklist + commit/push review package | UAT siap review; server berhenti hanya setelah penerimaan |

Setelah S8, berhenti untuk review pemilik. Penerimaan dicatat pada commit dokumentasi terpisah; server UAT dihentikan setelah penerimaan (DEC-27). Tidak ada merge/deploy/migrasi operasional.

---

## 7. Matriks tes yang direncanakan

Tes ditambahkan ke `tests/Feature/Leaves/LeaveRequestTest.php` (memakai fixture/helper 08a) atau file baru dengan fixture setara; tidak mengubah perilaku 08a.

| ID | Skenario | Expected |
|---|---|---|
| T17 | Approve/reject oleh Kepala Sekolah pada izin submitted student/employee; pembuat/subjek sendiri; admin/operator/waka/guru | `200` untuk approver sah; `403` untuk pembuat/subjek dan peran tanpa approve; `leave_decisions` bertambah hanya pada sukses; decoy tidak berubah |
| T18 | Double approve/reject, versi basi, dua keputusan paralel | Satu `200`; berikutnya `409` dengan `latest`; status final konsisten; tidak ada dua keputusan sukses |
| T19 | Approve tanpa alasan, approve dengan alasan 10–500, reject tanpa/terlalu pendek/terlalu panjang | `200`/`422`; alasan tersimpan hanya di `leave_decisions`, tidak di audit |
| T20 | Cancel approved oleh approver; cancel approved oleh pembuat/manage; cancel rejected/cancelled; alasan wajib/panjang | `200`/`403`/`409`/`422`; `decided_*` dipertahankan; riwayat approved + cancelled |
| T21 | Riwayat keputusan immutable untuk approved/rejected/cancelled | Update/delete ditolak; urutan/aktor/from-to benar |
| T26 | Rekap: periode inklusif, clipping, filter status/jenis/subject/tahun/kelas/tipe pegawai, batas 366 hari, `from>to` | Angka sesuai; tidak ada kebocoran lintas tahun; `422` untuk input tidak sah |
| T29b | Route order/IDOR: `/recap` tidak tertangkap `/{leave}`; objek tidak terlihat `404`; direct API tanpa ability `403` | Route statis benar; tidak ada data bocor |
| T27b | Rekap tidak mengubah data; approve/reject/cancel tidak menyentuh domain lain | Tidak ada writer absensi/saldo cuti; versi/status hanya target |
| T30 | Build/lint frontend + guard ability OR | Build sukses; guru hanya My Leaves; 390 px tanpa overflow |

Regresi wajib: 22 tes 08a (pengajuan, submit, cancel, lampiran, audit, route order), 86 tes Letters (07a/07b/07c), Pint pada file PHP berubah, `npm run build`, `npx eslint` non-mutasi. Suite penuh **NOT RUN** karena `Tests\Feature\Backup\MariaDbIntegrationTest` membaca `school_access_uat`; gate tes tersebut sebelum run penuh berikutnya. Race MariaDB/InnoDB tetap NOT RUN.

---

## 8. Rencana UAT

### 8.1 Lingkungan

- Gunakan ulang **`.uat-phase08`** (SQLite/storage terisolasi), API `127.0.0.1:18026`, SPA `127.0.0.1:14026`, cookie `school_phase08_session`, launcher `laravel-api/scripts/phase08-uat.php`.
- Jangan reset `.uat-phase08`; jangan menyentuh `.uat-phase05/06/07`, database operasional, Apache, atau service MariaDB.
- Server dinyalakan untuk review 08b, lalu **dihentikan setelah penerimaan** (DEC-27) dengan verifikasi PID/command line/port; data dipertahankan.

### 8.2 Perintah `prepare-decisions` (idempotent)

- Tambahkan command `prepare-decisions` ke launcher dan allowed commands.
- Menautkan **`uat.kepsek`** ke satu pegawai sintetis baru (`UAT-P08 Approver 01`) bila belum tertaut; membuat **satu self leave** submitted oleh `uat.kepsek` (subjek pegawai tersebut) dengan tanggal bebas overlap untuk menguji self-decision.
- Mengubah sebagian izin submitted hasil `prepare-leaves`:
  - target **20 approved** (sebagian dengan catatan, sebagian tanpa),
  - target **10 rejected** (alasan 10–500),
  - target **5 approved → cancelled** (alasan 10–255, mempertahankan `decided_*`).
- Aktor keputusan: `uat.kepsek` (approve + view + request). Skip izin yang pembuat/subjeknya kepsek (self leave), izin yang tidak eligible, atau konflik overlap; laporkan jumlah skip.
- Idempotent: hanya memproses izin yang masih pada status yang sesuai; target dihitung dari kondisi saat ini; tidak membuat duplikat; tidak mereset data.
- `status` menampilkan approved/rejected/cancelled, jumlah keputusan, pegawai tertaut, dan ringkasan rekap periode berjalan (opsional).
- Tidak mengubah lampiran, data siswa/pegawai lama, atau 15 keputusan cancelled 08a.

### 8.3 Checklist manual (18 item)

1. Login `uat.kepsek`: tab Requests + Recap terlihat; tombol Approve/Reject hanya pada izin submitted.
2. Approve izin siswa dengan catatan opsional; status berubah Approved; riwayat menampilkan aktor/waktu/catatan.
3. Approve izin pegawai tanpa catatan; status Approved; rekap menghitung.
4. Reject izin dengan alasan; alasan pendek ditolak; status Rejected; riwayat menampilkan alasan.
5. Cancel izin approved dengan alasan; status Cancelled; riwayat menampilkan approval lama + pembatalan; `decided_by` tetap.
6. Cancel rejected/cancelled tidak tersedia; direct API → `409`.
7. Self-approval: izin self milik `uat.kepsek` tidak menampilkan Approve/Reject; direct API → `403`.
8. Subject self: izin pegawai yang subjeknya kepsek (dibuat operator) tidak dapat di-approve kepsek; direct API → `403`.
9. `uat.admin`/`uat.operator`/`uat.waka`: tidak ada Approve/Reject/Cancel Approved; direct API → `403`.
10. `uat.guru`: hanya My Leaves; tidak ada Requests/Recap; direct API rekap → `403`.
11. Dua tab/versi basi: keputusan kedua → `409` + Reload Latest (bila dua tab dapat disimulasikan; jika tidak, catat NOT RUN).
12. Recap default bulan berjalan; `from`/`to` inklusif; angka total cocok dengan daftar/filter.
13. Recap filter status/jenis/subjek/tahun ajaran/kelas/tipe pegawai.
14. Recap clipping: izin yang melewati batas periode dihitung hanya hari di dalam periode.
15. Recap tidak menampilkan alasan/lampiran/NIK; siswa tanpa kelas tampil `Unplaced`; kelas terhapus tetap bernama.
16. Layout 390 px, keyboard, dialog, tabel scroll; tanpa overflow.
17. 0 console error selain `409`/`422` yang disengaja; tidak ada tombol dummy/placeholder.
18. Setelah penerimaan, server UAT dihentikan (DEC-27) dan `.uat-phase08` dipertahankan.

Browser checks dijalankan bila browser tersedia; jika tidak, checklist manual diberikan dan item browser dicatat **NOT RUN**.

---

## 9. Dokumentasi yang diperbarui saat implementasi

- `docs/API-CONTRACTS.md` — 3 route 08b, body/query, respons rekap, error, dan catatan route order; total 16 route izin.
- `docs/ACCESS-CONTROL.md` — enforcement `leaves.approve`, self-decision, cancel-approved, matriks peran, dan `permissions` objek.
- `docs/DATABASE.md` — `leave_decisions` kini menulis approved/rejected/cancelled; tidak ada schema baru; status note 08a diperbarui.
- `docs/REQUIREMENTS.md` — FR-10 status persetujuan/penolakan/rekap.
- `docs/TESTING.md` — perintah/hasil aktual, NOT RUN, batas MariaDB.
- `CHANGELOG.md`, `PROJECT_CONTEXT.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-08-LEAVES.md`, `reports/feature-update/INDEX.md`, `HANDOFF.md`.
- `reports/feature-update/PHASE-08B-REPORT.md` dan `PHASE-08B-MANUAL-REVIEW.md`.
- `docs/DECISIONS.md` — DEC-36 dari PROPOSED menjadi APPROVED setelah pernyataan pemilik; DEC-13 tetap OPEN.

---

## 10. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Approver juga pembuat/subjek | Policy `decides()` menolak; tes T17; UI menyembunyikan tombol; direct API `403` |
| Dua keputusan paralel | Transaksi + `lockForUpdate` + `expected_version`; tes T18; race MariaDB NOT RUN |
| Izin overlap disetujui ganda | Cek ulang overlap saat approve; `422`; data lama tidak diubah |
| Config/master berubah setelah submit | Approve tidak memvalidasi ulang config/eligibility; hanya overlap; alasan didokumentasikan di D3 |
| Pembuat/manage membatalkan approved | Policy cancel bercabang; `403`; tes T20 |
| Rekap membocorkan alasan/PII | Hanya agregat; tanpa reason/lampiran/NIK; tes T26; audit tanpa alasan |
| Hari rekap membengkak pada periode parsial | Clipping ke irisan periode; tes batas inklusif |
| Route `/recap` tertangkap `/{leave}` | Daftarkan sebelum `/{leave}`; tes T29b |
| Query rekap N+1/lambat | Eager loading + satu query kelas; batas 366 hari; tanpa pagination agregat |
| Fixture UAT tidak reproducible | `prepare-decisions` idempotent; target dihitung dari status; skip dilaporkan |
| Suite penuh menyentuh `school_access_uat` | Jalankan tes 08b/08a/Letters terpisah; gate `MariaDbIntegrationTest` sebelum suite penuh |
| Server UAT dibiarkan berjalan | Stop setelah penerimaan (DEC-27); verifikasi PID/port; data dipertahankan |

---

## 11. Kriteria penerimaan paket rencana

- [ ] Pemilik menyetujui D1–D8; pernyataan dicatat sebagai **DEC-36 APPROVED**.
- [ ] Implementasi 08b diotorisasi; 08a tetap APPROVED; DEC-35 tidak berubah; **DEC-13 tetap OPEN**.
- [ ] Tidak ada kode/migration/route/test/UAT pada paket rencana ini.
- [ ] Dokumen status (`INDEX`, `HANDOFF`, `ROADMAP`, phase file, `CHANGELOG`, `PROJECT_CONTEXT`, `PHASE-08-PLAN`) menyatakan **08b PLAN — AWAITING OWNER APPROVAL**.
- [ ] Setelah persetujuan, implementasi mengikuti S0–S8 dan berhenti untuk review; commit+push paket review; penerimaan dicatat pada commit dokumentasi terpisah.

**Pernyataan otorisasi:** paket ini hanya rencana. 08b **belum diimplementasikan** dan tidak boleh dimulai, di-commit sebagai kode, atau diuji sebagai fitur sampai pemilik menyetujui D1–D8. Merge/deploy/force-push, migrasi database operasional/MariaDB, perubahan dependency/lockfile, perubahan service/`.env`, dan retensi DEC-13 tetap tidak diotorisasi.
