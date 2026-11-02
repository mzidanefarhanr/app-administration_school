# Laporan Subfase 08a — Pengajuan Izin Siswa dan Pegawai

Tanggal: implementasi dan verifikasi agen 2026-11-02 WIB. Branch: `main`. Baseline induk: `7a45813` (pencatatan DEC-35 APPROVED). Status teknis: **IMPLEMENTED — agent-verified (2026-11-02)**. Status review pemilik: **PENDING (#1–#16)** melalui `PHASE-08A-MANUAL-REVIEW.md`; commit/push paket review bukan penerimaan manual.
Keputusan pemilik: **DEC-35 APPROVED** (D1–D12, 2026-11-02 WIB; pernyataan **"Saya setuju D1-D12"**); **DEC-07 CLOSED**; **DEC-13 tetap OPEN**; **DEC-11 CLOSED untuk lingkup 07c**.
Otorisasi: implementasi **08a**; **08b menunggu instruksi terpisah**. Rencana: `reports/feature-update/PHASE-08-PLAN.md`.

## 1. Outcome dan cakupan

Subfase 08a membangun domain izin dari nol untuk siswa dan pegawai:

- **Tiga tabel additive.** `leave_requests` menyimpan subjek siswa **atau** pegawai, snapshot tahun ajaran/kelas untuk siswa, jenis, rentang tanggal inklusif, alasan, status, versi, dan metadata pelaku; `leave_decisions` menyimpan keputusan append-only; `leave_attachments` menyimpan lampiran privat dengan soft delete. Tidak ada perubahan pada tabel domain lain, tidak ada soft delete/hard delete pengajuan.
- **Jenis izin dari konfigurasi kode.** `config/leaves.php` memuat jenis siswa (`sick`, `permission`, `other`) dan pegawai (`annual`, `sick`, `maternity`, `official_duty`, `other`). Batas jenis 30 hari (maternity/paternity 90), batas global rentang 90 hari, alasan 5–1000 karakter, alasan batal 10–255 karakter. Tidak ada CRUD master jenis izin.
- **Kemampuan dan peran.** Ability baru `leaves.request` (bukan sensitif) + peran awal baru `guru` yang hanya memiliki ability tersebut. `leaves.request` juga diberikan ke Admin Sistem, Operator Sekolah, Kepala Sekolah, dan Wakil Kesiswaan. `leaves.view`/`leaves.manage` tetap; `leaves.approve` tetap hanya Kepala Sekolah secara default dan **belum ditegakkan pada 08a** (keputusan 08b).
- **Subjek dan aktor.** Siswa tidak memiliki akun login; pengajuan siswa hanya melalui `leaves.manage` (default Admin/Operator). Pegawai self-service memakai `leaves.request` + tepat satu `employees.user_id = actor.id` (tanpa fallback NIK); tanpa profil tertaut → `403` dengan pesan agar admin menautkan akun. Pegawai atas nama memakai `leaves.manage`. Guru default tidak dapat membuat izin siswa atau izin pegawai lain.
- **Workflow 08a.** `draft → submitted`, `draft/submitted → cancelled`; `approved`/`rejected` disiapkan sebagai status final tetapi belum memiliki endpoint keputusan. Semua transisi memakai transaksi, `lockForUpdate`, dan `expected_version`; versi/status basi → `409` dengan ringkasan terbaru yang aman. Tidak ada reopen, edit setelah submit, atau delete.
- **Aturan tanggal dan overlap.** Tanggal penuh inklusif WIB, `start_date <= end_date`, batas `max_days` per jenis dan `max_range_days` global. Siswa harus berada di dalam rentang tahun ajaran aktif dan memiliki keanggotaan aktif pada tahun tersebut; pegawai tidak terikat tahun ajaran dan tanggal lampau diizinkan. Overlap dicek saat submit terhadap subjek yang sama berstatus `submitted`/`approved` (`existing.start <= new.end AND existing.end >= new.start`); draft dan status final cancelled/rejected tidak memblokir; tanggal berdampingan diperbolehkan.
- **Lampiran privat.** Opsional, maksimal 3 per pengajuan, 5 MB per file, PDF/JPG/JPEG/PNG. Validasi dipusatkan di `PrivateUploadValidator` (nama satu ekstensi, allowlist, MIME server, kecocokan ekstensi–MIME, `getimagesize`), file disimpan UUID di `attachments/leaves/{leave_id}/` pada disk privat, SHA-256 dicatat, nama asli disanitasi. Unduhan memakai `X-Document-Filename` tanpa `Content-Disposition`, dengan `nosniff`, CSP sandbox, dan `Cache-Control: private, no-store`.
- **Audit dan privasi.** Allowlist audit `LeaveRequest` tidak memuat alasan; `LeaveDecision` tidak memuat alasan; `LeaveAttachment` hanya mencatat leave_request_id/mime/ukuran/deleted_at. Nama siswa/pegawai, NIS/NISN/NUPTK, isi lampiran, path, checksum, dan alasan tidak masuk audit. `leave_decisions` dan `leave_requests` menolak update/delete pada level model.
- **API dan UI.** **13 route** `/api/leaves*` (options, index, mine, dua subject picker, detail, create, update, submit, cancel, upload/hapus/unduh lampiran). Halaman `/public/leaves` dengan tab **Requests** (`leaves.view`) dan **My Leaves** (`leaves.request`), form siswa/pegawai, filter server, dialog submit/cancel, lampiran, penanganan `409`/`422`, label English. Menu **Leaves** memakai `canAny(['leaves.view','leaves.request'])`; label peran `leaves.request` ditambahkan di Roles.
- **UAT terisolasi.** `.uat-phase08` SQLite/storage terpisah, launcher `scripts/phase08-uat.php` (`init`/`prepare`/`prepare-leaves`/`status`), API `127.0.0.1:18026`, SPA `127.0.0.1:14026`, cookie `school_phase08_session`. Fixture idempoten: 120 izin sintetis + 2 izin hasil pengujian browser; data sekolah/pegawai/siswa sintetis. Server dipertahankan sampai penerimaan pemilik, lalu dihentikan (DEC-27).

Tidak dikerjakan pada 08a (non-goals D11/D8): approve/reject/rekap/approval berjenjang, pembatalan izin approved, delegasi per-permintaan, wali kelas sebagai approver, izin setengah hari/per jam/berulang/lintas tahun, akun siswa/orang tua, absensi otomatis, saldo cuti, potongan gaji, notifikasi email/WhatsApp, PDF surat izin, impor/ekspor CSV izin, arsip/versioning Fase 09, retensi DEC-13, perubahan dependency/lockfile, perubahan service/`.env`, migrasi database operasional/MariaDB, merge/deploy/force-push, dan implementasi 08b.

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File | Perubahan | Schema/izin |
|---|---|---|
| `database/migrations/2029_08_04_000001_create_leave_requests_table.php` | Tabel `leave_requests` (subjek, snapshot tahun/kelas, jenis, tanggal, alasan, status, versi, pelaku) | Additive; FK restrict/nullOnDelete; index filter; tanpa soft delete |
| `database/migrations/2029_08_04_000002_create_leave_decisions_table.php` | Tabel `leave_decisions` (action/from/to, aktor, alasan, `created_at`) | Append-only; FK restrict |
| `database/migrations/2029_08_04_000003_create_leave_attachments_table.php` | Tabel `leave_attachments` (nama asli, path unik, MIME, ukuran, SHA-256, uploader, soft delete) | File privat; tanpa endpoint tulis langsung |
| `database/migrations/2029_08_04_000004_add_leaves_request_ability_and_guru_role.php` | Ability `leaves.request` + peran awal `guru`; idempoten, tidak mengubah peran/ability admin | D9 |
| `app/Models/LeaveRequest.php` | Status/aksi, relasi `withTrashed()`, audit allowlist tanpa alasan, tolak delete | Immutable lifecycle |
| `app/Models/LeaveDecision.php` | Ledger immutable (`UPDATED_AT=null`, tolak update/delete), audit tanpa alasan | Append-only |
| `app/Models/LeaveAttachment.php` | Soft delete, allowlist audit minimal, relasi pengajuan | File privat |
| `config/leaves.php` | Jenis siswa/pegawai, batas hari, validasi alasan, lampiran, pagination | Tanpa CRUD master |
| `app/Services/Files/PrivateUploadValidator.php` | Validasi upload privat bersama (huruf/ekstensi/MIME/gambar/ukuran) | Dipakai izin dan surat |
| `app/Services/Leaves/LeaveRequestService.php` | Options, list/mine, create/update, submit, cancel, kelayakan subjek, overlap, versi, serialisasi, policy checks | Server-side |
| `app/Services/Leaves/LeaveAttachmentService.php` | Simpan/unduh/hapus lampiran, header aman | `X-Document-Filename` |
| `app/Policies/LeaveRequestPolicy.php` | View/any, create, update, submit, cancel, attachment; delete/restore/forceDelete false | Ability + kepemilikan |
| `app/Http/Controllers/LeaveController.php` | 13 handler + filter whitelist + pagination | Route matrix 08a |
| `app/Http/Requests/StoreLeaveRequestRequest.php`, `UpdateLeaveRequestRequest.php`, `SubmitLeaveRequestRequest.php`, `CancelLeaveRequestRequest.php`, `StoreLeaveAttachmentRequest.php` | Validasi field, versi, lampiran | `422`/`409` |
| `app/Services/Letters/LetterAttachmentService.php` | Memakai `PrivateUploadValidator` (refactor perilaku setara) | Regresi surat |
| `app/Support/AbilityCatalog.php` | `leaves.request` + pemetaan peran + peran awal `guru` | D9 |
| `routes/api.php` | 13 route `/api/leaves*`; route statis sebelum `{leave}` + `whereNumber` | `auth:sanctum` + `active` |
| `tests/Feature/Leaves/LeaveRequestTest.php` | 22 tes / 209 assertions | Matriks izin/subjek/transisi/lampiran |
| `scripts/phase08-uat.php` | Launcher terisolasi + `prepare-leaves` + status | Hanya `.uat-phase08` |
| `vue-app/src/views/pages/public/Leaves.vue` | Halaman Requests/My Leaves, form, filter, lampiran, dialog | Tombol mengikuti ability |
| `vue-app/src/router/index.js`, `vue-app/src/layout/AppMenu.vue`, `vue-app/src/views/pages/master/Roles.vue` | Route `/public/leaves`, menu Leaves, label `leaves.request` | Guard OR |

Kontrak endpoint 08a (detail respons di `docs/API-CONTRACTS.md`):

| Method | Endpoint | Izin | Catatan |
|---|---|---|---|
| GET | `/api/leaves/options` | `viewAny` (`view`/`request`/`manage`) | Jenis, status, batas, pagination, lampiran, tahun aktif, `self_employee`, capability |
| GET | `/api/leaves` | `leaves.view` | Daftar seluruh pengajuan, filter/pagination |
| GET | `/api/leaves/mine` | `leaves.request` | Hanya pengajuan dengan subjek pegawai tertaut ke aktor |
| GET | `/api/leaves/subjects/students` | `leaves.manage` + `students.view` | Siswa aktif tahun aktif; tanpa NIK/alamat |
| GET | `/api/leaves/subjects/employees` | `leaves.manage` + `employees.view` | Pegawai aktif; tanpa NIK/alamat/kontak |
| GET | `/api/leaves/{leave}` | policy view | Visible bila view/manage, pembuat, atau subjek pegawai sendiri |
| POST | `/api/leaves` | policy create | Siswa hanya manage; pegawai self hanya `leaves.request` |
| PUT/PATCH | `/api/leaves/{leave}` | pembuat atau manage | Hanya draft; `expected_version` |
| POST | `/api/leaves/{leave}/submit` | pembuat atau manage | Validasi ulang kelayakan + overlap; `409`/`422` |
| POST | `/api/leaves/{leave}/cancel` | pembuat atau manage | Draft: alasan opsional; submitted: wajib 10–255 |
| POST | `/api/leaves/{leave}/attachments` | pembuat atau manage | Hanya draft/submitted; maksimal 3 |
| GET | `/api/leaves/{leave}/attachments/{attachment}/download` | policy view | Stream privat; tanpa `Content-Disposition` |
| DELETE | `/api/leaves/{leave}/attachments/{attachment}` | pembuat atau manage | Soft delete baris; file fisik dipertahankan |

- **Bentuk item ringkas**: subjek (`type`, `student`/`employee` minimal), jenis + label, tanggal + jumlah hari, status + label, versi, requester, `submitted_at`, `decided_at`, `cancelled_at`, `attachments_count`, dan `can {view, edit, submit, cancel, upload_attachment}`.
- **Bentuk detail** menambahkan alasan, pelaku submit/keputusan/pembatalan, daftar lampiran (nama asli, MIME, ukuran, waktu), riwayat keputusan, dan timestamp. Path, hash, dan snapshot internal tidak diekspos.
- **Filter list**: `search`, `status`, `subject_type`, `type`, `date_from`, `date_to`, `school_year_id`, `rombel_id`, `employee_type_id`, `sort` (`start_date|created_at|status|type|version`), `direction`, `per_page` (10/20/50/100), `page`. Nilai di luar whitelist → `422`.
- **Error**: guest `401`; akun nonaktif `403` (middleware `active`); tanpa ability `403`; objek tidak terlihat `404`; validasi/kelayakan/overlap `422`; versi/status basi `409` dengan ringkasan terbaru yang aman.
- **Respons API** tetap memakai envelope `{success, message, data}`; pagination menyertakan meta yang konsisten dengan modul lain.

## 3. Evidence

| ID | Metode / command | Fixture / peran | Expected | Actual | Status |
|---|---|---|---|---|---|
| E1 | `php artisan test tests/Feature/Leaves/LeaveRequestTest.php` | SQLite in-memory | Matriks izin/subjek/transisi/lampiran PASS | **22 passed / 209 assertions, 19,44 s** | PASS |
| E2 | `php artisan test tests/Feature/Letters` | SQLite in-memory | Refactor validator tidak meregresi surat | **86 passed / 1.744 assertions, 88,68 s** | PASS |
| E3 | `./vendor/bin/pint --test` pada 23 file berubah | — | Gaya kode | **23 file PASS** | PASS |
| E4 | `php artisan route:list --path=leaves` | — | 13 route | **13 route** (options, index, mine, 2 picker, detail, create, update, submit, cancel, 3 lampiran) | PASS |
| E5 | `npm run build` | — | Build produksi | **PASS 25,44 s** | PASS |
| E6 | `npx eslint src/views/pages/public/Leaves.vue src/layout/AppMenu.vue src/router/index.js src/views/pages/master/Roles.vue` | — | Tanpa error | **0 error**, 595 warning Prettier (CRLF) | PASS |
| E7 | `php scripts/phase08-uat.php status` | `.uat-phase08` SQLite | Guard isolasi + fixture | **Guard PASS; 122 izin (34 draft/73 submitted/15 cancelled; 101 siswa/21 pegawai), 49 lampiran/49 file, 15 keputusan, 123 siswa/123 keanggotaan aktif, 20 pegawai (1 tertaut), 6 akun** | PASS |
| E8 | Browser/API Chrome DevTools MCP | operator/guru/waka/kepsek | Alur utama + tampilan | **Operator end-to-end (draft → lampiran → submit → unduh berizin → cancel + riwayat); guru self-service (draft v1 → edit v2 → submit v3, hanya Cancel tersisa); waka/kepsek view-only 122 baris tanpa tombol tulis; viewport sempit 500 px tanpa overflow halaman; 0 console error aplikasi** | PASS (terbatas pada daftar ini) |
| E9 | Checklist manual `PHASE-08A-MANUAL-REVIEW.md` | 16 skenario | Dijalankan pemilik | **PENDING** | PENDING |

### Batas suite penuh

- `php artisan test` (suite penuh) **tidak dijalankan** pada 08a. Baseline suite memuat `Tests\Feature\Backup\MariaDbIntegrationTest` yang membaca `school_access_uat` (database yang dikecualikan pemilik) dan me-restore ke `school_restore_drill_uat` saat MariaDB lokal dapat dijangkau. Tes ini belum digating; menjalankan suite penuh akan melanggar batas akses. Regresi yang relevan (direktori Letters) dijalankan dan lulus E2.
- Tes 08a memakai SQLite in-memory; hasil tidak membuktikan perilaku konkurensi MariaDB/InnoDB.

## 4. UAT manual dan fixture

Lingkungan: `.uat-phase08` (SQLite), API `php -S 127.0.0.1:18026 scripts/phase08-uat.php`, SPA Vite `127.0.0.1:14026`, cookie `school_phase08_session`. Password hanya di `.uat-phase08/account-password.txt` dan tidak pernah masuk dokumen. Server dipertahankan sampai penerimaan pemilik; setelah itu dihentikan sesuai DEC-27 dan `.uat-phase08` dipertahankan.

Akun: `uat.admin` (Admin Sistem), `uat.operator` (Operator Sekolah), `uat.kepsek` (Kepala Sekolah), `uat.waka` (Wakil Kesiswaan), `uat.guru` (peran Guru, tertaut ke `UAT-P08 Employee 01`), `uat.inactive` (nonaktif).

Status fixture per 2026-11-02 (read-only):

- **122 izin** — 34 draft/73 submitted/15 cancelled/0 approved/0 rejected; 101 subjek siswa/21 subjek pegawai.
- **49 lampiran** / 49 file privat; **15 keputusan** (semua `cancelled`; approve/reject belum tersedia di 08a).
- **123 siswa** dengan 123 keanggotaan aktif tahun 2026/2027; **20 pegawai** (20 aktif; 1 tertaut ke `uat.guru`).
- **120 fixture sintetis** (100 siswa + 20 pegawai) + **2 izin hasil pengujian browser** (`#121` siswa oleh operator, `#122` pegawai oleh guru). `#121` adalah alur operator yang diakhiri cancel; `#122` adalah self-service guru yang diedit dan disubmit (versi 3).
- Contoh untuk review: draft siswa `#4`, draft pegawai guru `#101`, submitted siswa `#5`, submitted pegawai `#102`, submitted self-service guru `#122`, cancelled setelah submit `#10`/`#105`, cancelled dari draft `#20`, lampiran 2 file `#1`, lampiran 3 file `#2`, lampiran hasil browser `#121`.
- Guard `status` memastikan koneksi hanya SQLite `.uat-phase08`; tidak ada koneksi MariaDB, tidak menyentuh database operasional, `.uat-phase05/06/07`, Apache, atau service. `.uat-phase08` adalah runtime baru; fixture 08a hanya menulis tabel izin, data sintetis siswa/pegawai, dan ability/peran `guru`.

Bukti browser/API yang dijalankan agen:

- Operator membuat draft izin siswa (subjek snapshot tahun/kelas), mengunggah lampiran PDF, submit, mengunduh lampiran dengan header aman, lalu membatalkan izin submitted dengan alasan 10–255; riwayat keputusan `cancelled` muncul dan status menjadi cancelled.
- Guru membuka **My Leaves**, membuat self request untuk profil pegawai tertaut, mengedit draft (versi naik), submit (versi 3); setelah submit hanya aksi Cancel yang tersisa. Guru tidak melihat tombol New Request siswa dan tidak dapat membuka izin pegawai lain.
- Wakil Kesiswaan dan Kepala Sekolah melihat 122 baris dan detail read-only; tidak ada tombol create/edit/submit/cancel/upload; server tetap menolak mutasi tanpa ability yang sesuai.
- Halaman English, layout menyusun ulang pada viewport sempit (Chrome DevTools membatasi lebar 390 menjadi 500 px) tanpa overflow halaman, dan **0 console error aplikasi** (respons `409`/`422` yang disengaja tidak dihitung sebagai error).

Skenario manual lain (validasi tanggal/kelayakan, overlap, delete lampiran, audit, keyboard) diserahkan kepada pemilik melalui checklist #1–#16; agen tidak mengklaim PASS untuk skenario yang tidak dijalankan.

## 5. Risiko, keputusan, dan batas

- **08a belum memiliki keputusan.** `approved`/`rejected` belum dapat dicapai melalui API/UI; `leaves.approve` belum ditegakkan dan `permissions.approve/reject` selalu false. Ini disengaja; 08b menunggu instruksi terpisah.
- **Status final di 08a.** `cancelled` bersifat final; tidak ada un-cancel. Izin yang salah harus diajukan ulang.
- **Overlap di level aplikasi.** Dicek dalam transaksi dengan `lockForUpdate` pada subjek, tetapi race multi-proses nyata pada MariaDB/InnoDB **NOT RUN**. SQLite tidak membuktikan perilaku gap-lock/unique parsial.
- **Draft tidak memblokir overlap.** Dua draft dapat menempati tanggal yang sama; konflik baru terdeteksi saat submit. Ini sesuai rencana dan diuji.
- **Lampiran opsional.** Semua jenis izin mengizinkan tanpa lampiran pada v1; `attachment_required` tersedia di config untuk keputusan pemilik berikutnya tanpa migration.
- **Retensi.** File lampiran dipertahankan setelah soft delete; retensi/pemusnahan tetap **OPEN (DEC-13)**.
- **Refactor validator surat.** `LetterAttachmentService` sekarang memakai `PrivateUploadValidator`; pesan validasi dan perilaku dipertahankan, diregresi melalui 86 tes Letters.
- **Header unduhan.** Lampiran izin memakai `X-Document-Filename` tanpa `Content-Disposition` mengikuti 07c. Temuan lama unduhan lampiran 07a/07b masih memakai `Content-Disposition` dan gagal lintas origin di Chrome; tidak diubah pada 08a.
- **UAT `php -S` single-threaded.** Tidak merepresentasikan konkurensi produksi; dua tab nyata dan dua submit paralel tetap **NOT RUN**.
- **Suite penuh tidak dijalankan** karena batas akses `school_access_uat`; tes perlu digating sebelum run berikutnya.
- **Tidak ada perubahan dependency/lockfile, service, `.env`, database operasional/MariaDB, merge, deploy, atau force-push.**

## 6. Sinkronisasi dokumen

- `docs/DATABASE.md` — tiga tabel izin, migration 08a, dan status target izin.
- `docs/API-CONTRACTS.md` — 13 endpoint 08a, bentuk item, filter, error, audit, UI, non-goals.
- `docs/ACCESS-CONTROL.md` — route matrix 08a, ability/peran `guru`, batas subjek/objek, larangan self-decision (08b), UAT.
- `docs/TESTING.md` — bukti 08a, batas suite penuh, NOT RUN.
- `docs/REQUIREMENTS.md` — FR-10 diperbarui untuk 08a.
- `docs/DECISIONS.md` — DEC-35 status implementasi 08a; DEC-07 CLOSED.
- `docs/OPERATIONS.md` — lampiran izin privat, UAT `.uat-phase08`, siklus penghentian server.
- `PROJECT_CONTEXT.md`, `CHANGELOG.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-08-LEAVES.md` — status implementasi dan langkah berikutnya.
- `reports/feature-update/INDEX.md`, `HANDOFF.md`, `PHASE-08A-MANUAL-REVIEW.md` — checkpoint dan checklist review.

## 7. Langkah berikutnya

1. Pemilik menjalankan `PHASE-08A-MANUAL-REVIEW.md` #1–#16 pada server UAT 14026/18026.
2. Setelah pernyataan penerimaan, catat approval pada commit dokumentasi terpisah dan hentikan server UAT (DEC-27) dengan verifikasi PID/command line; pertahankan `.uat-phase08`.
3. 08b (keputusan dan rekap) hanya dimulai setelah instruksi pemilik terpisah.
4. Sebelum suite penuh berikutnya, gate `MariaDbIntegrationTest` agar tidak mengakses `school_access_uat` tanpa opt-in.
