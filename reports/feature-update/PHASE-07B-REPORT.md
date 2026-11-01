# Laporan Subfase 07b — Disposisi, Surat Keluar, dan Nomor Resmi

Tanggal: implementasi dan verifikasi agen 2026-11-01 WIB; verifikasi ulang dan paket dokumentasi 2026-11-02 WIB. Branch: `main`. Baseline induk: `d326369` (pencatatan DEC-33 APPROVED). Paket implementasi 07b: `d736b37` (di-push). Paket dokumentasi dicatat pada checkpoint setelah commit dokumentasi.
Keputusan pemilik: **DEC-33 APPROVED** (D1–D11, 2026-11-01 WIB; pernyataan **"Saya setuju D1-D11"**); **DEC-06 CLOSED** melalui D4–D5.
Status teknis: **IMPLEMENTED — agent-verified (2026-11-01; verifikasi ulang 2026-11-02)**. Status review pemilik: **APPROVED 2026-11-02 WIB (#1–#16)** berdasarkan pernyataan **"Pengujian nomor 1-16 sudah berhasil dan saya approved."**; agen tidak mengulang skenario dan item NOT RUN tetap NOT RUN.
Rencana: `reports/feature-update/PHASE-07B-PLAN.md`. Checklist manual: `reports/feature-update/PHASE-07B-MANUAL-REVIEW.md`.

## 1. Outcome dan cakupan

Subfase 07b melengkapi modul persuratan Fase 07 dengan disposisi surat masuk, surat keluar, penomoran resmi, dan pemisahan approval/sign:

- **Model data additive.** Tabel `letters` (07a) dipakai ulang untuk `direction='outgoing'`. Migration additive menambah `recipient` (string 150, nullable, index), `official_number` (string 100, nullable, unik), `workflow_status` (string 20, nullable, index), `approved_at`/`approved_by`, `issued_at`/`issued_by`, serta index `(direction, workflow_status)`; kolom lama tidak diubah nullability-nya. Tabel baru: `letter_dispositions`, `letter_approvals` (immutable), `letter_number_counters` (unik per `(scope, period)`).
- **Alur surat keluar.** `draft → review → approved → issued`; `review → draft` saat reject; `draft`/`review → cancelled` oleh `letters.manage`; `approved`/`issued → cancelled` oleh `letters.approve`. Tidak ada lompat status, reissue, atau un-cancel. Edit metadata dan tambah/hapus lampiran hanya saat `draft`; submit membekukan; reject membuka kembali. Setiap transisi memakai `expected_version`, menaikkan versi, dan mengembalikan `409` tanpa mutasi bila versi/status tidak cocok.
- **Penomoran resmi (DEC-06).** Format dapat dikonfigurasi; default `{seq:03d}/{school_code}/{year}` dengan kode sekolah `20512345` pada UAT, contoh `001/20512345/2026`. Counter global surat keluar per tahun kalender Asia/Jakarta, dikunci di dalam transaksi issue; nomor diberikan saat `approved → issued` (sign), bukan saat draft/approve. Format wajib memuat `{seq}` dan `{year}`, maksimum 100 karakter. Nomor tidak dapat diedit/diganti, counter tidak mundur, dan pembatalan tidak memakai ulang nomor.
- **Approval/sign terpisah.** `letters.approve` untuk approve/reject dan `letters.sign` untuk issue/sign. Pembuat surat tidak dapat approve/reject/issue suratnya sendiri (`403`). Approver boleh juga sign bila memiliki kedua ability; approval dan signing dicatat terpisah pada `letter_approvals` dan `letter_events`. Admin/Operator tidak otomatis mendapat approve/sign; tidak ada ability/role migration baru.
- **Disposisi surat masuk.** Penerima adalah profil `employees` aktif (boleh tanpa akun), multi-penerima dalam satu transaksi, satu baris per penerima. Status `pending → in_progress → completed`; `pending`/`in_progress → cancelled`; tanpa reopen. `due_date` wajib, overdue dihitung dari tanggal (bukan disimpan), `follow_up_note` wajib 10–2000 saat completed, alasan cancel 10–255. Disposisi berversi + `409`; disposisi terbuka memblokir void surat masuk (`409 open_dispositions`); completed/cancelled tetap history.
- **Integrasi surat masuk.** Daftar dan detail incoming 07a tetap berjalan; list menampilkan jumlah disposisi terbuka/overdue, detail memuat panel disposisi, dan void diperiksa terhadap disposisi terbuka. Aturan edit/void/lampiran incoming 07a tidak berubah.
- **API dan UI.** Enam endpoint disposisi dan 13 endpoint outgoing baru (total 28 route surat bersama sembilan route incoming 07a). Halaman **Outgoing Letters**, **Dispositions**, dan panel disposisi pada **Incoming Letters**; menu Letters berisi tiga halaman; seluruh label English, responsif 390 px, keyboard, konflik `409` dengan **Reload Latest**, dan tombol mengikuti ability.
- **Audit, history, privasi.** `letter_events` diperluas dengan `submitted`, `approved`, `rejected`, `issued`, `cancelled`, `disposition_created`, `disposition_updated`, `disposition_completed`, `disposition_cancelled`; tetap immutable. `letter_approvals` immutable dan tanpa endpoint tulis langsung. Allowlist `Letter` menambah `official_number`/`workflow_status`/`approved_*`/`issued_*` tetapi tidak `recipient`/`sender`/`subject`/`notes`/alasan; allowlist disposisi tanpa `note`/`follow_up_note`/`cancel_reason`; event metadata minimal tanpa teks/nama penerima.
- **UAT terisolasi.** `.uat-phase07` dipakai ulang tanpa reset: 46 surat keluar (16 draft/8 review/10 approved/6 issued/6 cancelled), 32 disposisi (11 pending/7 in_progress/7 completed/7 cancelled; 2 overdue), 14 pegawai (12 aktif); 126 surat masuk 07a tetap utuh, dengan total 69 baris lampiran (1 soft-deleted)/69 file dan 389 event. Fixture `prepare-outgoing` idempoten; server API 18025/SPA 14025 berjalan sampai penerimaan pemilik.

Tidak dikerjakan pada 07b (non-goals D1/D11): 07c/PDF/template/snapshot/kop/tanda tangan/stempel, OCR/e-sign/email/WhatsApp, delegasi/multi-level approval, retensi DEC-13, arsip/versioning Fase 09, ekspor, Dapodik, merge/deploy/force-push, migrasi database operasional/MariaDB, dan perubahan dependency/service/`.env`.

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File | Perubahan | Schema/izin |
|---|---|---|
| `database/migrations/2029_08_02_000004_add_outgoing_fields_to_letters_table.php` | Kolom additive outgoing pada `letters` + index `(direction, workflow_status)` | Additive; unique `official_number`; FK approval/issue nullOnDelete |
| `database/migrations/2029_08_02_000005_create_letter_dispositions_table.php` | Tabel `letter_dispositions` (+ penerima pegawai, due date, status, note, follow-up, versi, completed/cancelled, alasan) | Additive; FK restrict/nullOnDelete; tanpa soft delete |
| `database/migrations/2029_08_02_000006_create_letter_approvals_table.php` | Tabel `letter_approvals` (action, actor, notes, `created_at`) | Immutable; tanpa `updated_at` |
| `database/migrations/2029_08_02_000007_create_letter_number_counters_table.php` | Tabel `letter_number_counters` (`scope`, `period`, `last_number`) | Unik `(scope, period)`; tanpa endpoint tulis |
| `app/Models/Letter.php` | Konstanta workflow, relasi approval/issued/cancelled, scope outgoing, allowlist audit | Audit minimal PII |
| `app/Models/LetterDisposition.php` | Model disposisi, status open/terminal, relasi surat/penerima/aktor, allowlist audit tanpa teks | Audit minimal PII |
| `app/Models/LetterApproval.php` | Model approval immutable, relasi surat/aktor | Tanpa update/delete |
| `app/Models/LetterNumberCounter.php` | Model counter per scope/periode | Hanya berubah di transaksi issue |
| `app/Models/LetterEvent.php` | 14 event domain termasuk transisi outgoing/disposisi | Append-only |
| `app/Services/Letters/OutgoingLetterService.php` | Options/list/detail/create/update/submit/approve/reject/issue/cancel/lampiran; transaksi + lock + versi + self-check | Baca `letters.view`; tulis sesuai ability |
| `app/Services/Letters/LetterNumberService.php` | Validasi format/token, counter atomik `insertOrIgnore` + `lockForUpdate`, pembentukan nomor, contoh format tanpa konsumsi | Tanpa endpoint preview nomor |
| `app/Services/Letters/LetterDispositionService.php` | Create multi-penerima, update status/tenggat/tindak lanjut, cancel, overdue, filter/paginasi, serialisasi | Create/update/cancel `letters.manage` |
| `app/Services/Letters/LetterService.php` | Incoming: hitung/serialisasi disposisi dan guard void `409 open_dispositions` | 07a tidak berubah |
| `app/Http/Requests/*Outgoing*`, `*Disposition*` (10 file) | Validasi field, tanggal, panjang, transisi, `expected_version` | `letters.manage`/`approve`/`sign` |
| `app/Http/Controllers/OutgoingLetterController.php` | 13 aksi outgoing + streamed download | Route matrix D8 |
| `app/Http/Controllers/LetterDispositionController.php` | Worklist/options/recipients + create/update/cancel | `letters.view`/`manage` + `employees.view` |
| `routes/api.php` | 19 route baru; total 28 route surat | `auth:sanctum` + `active` |
| `config/letters.php` | Workflow, approval note, cancel reason, disposisi, penomoran, paginasi | Tanpa `.env` utama |
| `scripts/phase07-uat.php` | `prepare-outgoing`, fixture disposisi/pegawai, status 07b | Hanya `.uat-phase07` |
| `tests/Feature/Letters/OutgoingLetterTest.php` | 21 tes / 277 assertions | Matriks T1–T26 |
| `tests/Feature/Letters/DispositionTest.php` | 14 tes / 254 assertions | Matriks T19–T26 |
| `tests/Feature/Letters/IncomingLetterTest.php` | Penyesuaian regresi 07a | 20 tes 07a tetap |
| `vue-app/src/views/pages/public/OutgoingLetters.vue` | Daftar/filter/CRUD draft/approval/issue/cancel/detail/lampiran/konflik | Tombol mengikuti ability |
| `vue-app/src/views/pages/public/LetterDispositions.vue` | Worklist, filter status/overdue/penerima, edit/start/complete/cancel, konflik | Baca `letters.view`; tulis `letters.manage` |
| `vue-app/src/views/pages/public/IncomingLetters.vue` | Kolom jumlah disposisi + panel Assign/read-only + void `409` | Aturan 07a tetap |
| `vue-app/src/router/index.js`, `vue-app/src/layout/AppMenu.vue` | Route/halaman Outgoing dan Dispositions + menu Letters | Guard `letters.view` |

Kontrak endpoint baru (detail di `docs/API-CONTRACTS.md`):

| Method | Endpoint | Izin | Catatan |
|---|---|---|---|
| GET | `/api/letters/dispositions` | `letters.view` | Worklist; filter `status`/`overdue`/`recipient_employee_id`/`search`/paginasi |
| GET | `/api/letters/dispositions/options` | `letters.view` | Status, paginasi, batas catatan |
| GET | `/api/letters/dispositions/recipients` | `letters.manage` + `employees.view` | Opsi pegawai aktif tanpa PII |
| POST | `/api/letters/incoming/{letter}/dispositions` | `letters.manage` | Multi-penerima; `422`/`409` |
| PUT/PATCH | `/api/letters/incoming/{letter}/dispositions/{disposition}` | `letters.manage` | Status/tenggat/catatan + `expected_version` |
| POST | `/api/letters/incoming/{letter}/dispositions/{disposition}/cancel` | `letters.manage` | Alasan 10–255 + versi |
| GET | `/api/letters/outgoing/options` | `letters.view` | Kategori, workflow, contoh format nomor, default sender |
| GET | `/api/letters/outgoing` | `letters.view` | Search/filter tanggal/status/paginasi |
| GET | `/api/letters/outgoing/{letter}` | `letters.view` | Detail + lampiran + event + approval |
| POST | `/api/letters/outgoing` | `letters.manage` | Draft; tanpa nomor |
| PUT/PATCH | `/api/letters/outgoing/{letter}` | `letters.manage` | Hanya draft + versi |
| POST | `/api/letters/outgoing/{letter}/submit` | `letters.manage` | draft → review |
| POST | `/api/letters/outgoing/{letter}/approve` | `letters.approve` | review → approved; self `403` |
| POST | `/api/letters/outgoing/{letter}/reject` | `letters.approve` | review → draft; alasan 10–255 |
| POST | `/api/letters/outgoing/{letter}/issue` | `letters.sign` | approved → issued; nomor atomik |
| POST | `/api/letters/outgoing/{letter}/cancel` | `letters.view` + cek dinamis | draft/review `manage`; approved/issued `approve` |
| POST | `/api/letters/outgoing/{letter}/attachments` | `letters.manage` | Hanya draft; aturan 07a |
| GET | `/api/letters/outgoing/{letter}/attachments/{attachment}/download` | `letters.view` | Stream privat; lintas surat `404` |
| DELETE | `/api/letters/outgoing/{letter}/attachments/{attachment}` | `letters.manage` | Soft delete; hanya draft |

Sembilan route incoming 07a tidak berubah; `GET /api/letters/incoming` dan detail diperluas dengan `dispositions_open_count`, `dispositions_overdue_count`, dan `dispositions`. `409` memuat `message`, `errors`, dan `current_versions` (`letter_version`, `status`, `workflow_status`, `official_number`); disposisi memakai `{disposition_version, status}`. Tidak ada route delete surat, un-cancel, edit nomor, atau approve/sign massal.

## 3. Evidence

| ID | Metode / command | Fixture / peran | Expected | Actual | Status |
|---|---|---|---|---|---|
| E1 | `php artisan test tests/Feature/Letters/OutgoingLetterTest.php` | SQLite in-memory | Matriks outgoing PASS | **21 passed / 277 assertions** | PASS |
| E2 | `php artisan test tests/Feature/Letters/DispositionTest.php` | SQLite in-memory | Matriks disposisi PASS | **14 passed / 254 assertions** | PASS |
| E3 | Kedua berkas dijalankan ulang | SQLite in-memory | Tidak ada flake | **35 passed / 531 assertions, 18,42 s** | PASS |
| E4 | `php artisan test` (suite penuh) | SQLite in-memory | Tidak ada regresi | **522 passed / 13 skipped / 5.320 assertions, 464,78 s**; skip adalah tes integrasi MariaDB tanpa password | PASS |
| E5 | `./vendor/bin/pint --test` pada 31 file PHP berubah/baru | — | Gaya bersih | **PASS 31 files** | PASS |
| E6 | `php artisan route:list --path=letters` | — | 28 route surat | **28 route** (9 incoming + 6 disposisi + 13 outgoing); tanpa delete surat | PASS |
| E7 | `npm run build` | Vite 6 | Build sukses | **PASS 41,94 s**; warning chunk >500 kB sudah ada sebelumnya | PASS |
| E8 | `npx eslint --quiet` pada lima file frontend berubah/baru | ESLint | 0 error | **0 error** | PASS |
| E9 | `php scripts/phase07-uat.php status` | `.uat-phase07` SQLite terisolasi | Guard lolos; fixture 07b siap | **`[ISOLATION GUARD PASS]`**; 46 outgoing (16/8/10/6/6), 6 nomor resmi, counter 2026=6; 32 disposisi (11/7/7/7), 2 overdue; 14 pegawai (12 aktif); 126 surat masuk 07a, 69 baris lampiran (1 soft-deleted)/69 file, 389 event | PASS |
| E10 | Browser DOM + API (Chrome DevTools, SPA 14025/API 18025) | `uat.operator` | Draft/edit/submit/cancel outgoing | Daftar 45 fixture; create/edit/submit `#000172` → **In Review v3**; cancel beralasan → **Cancelled/Void v4**; lampiran draft; filter/paginasi; `409` stale + **Reload Latest** | PASS |
| E11 | Browser DOM + API | `uat.kepsek` | Approve/reject/issue + ledger | Approve `#000167` + catatan → ledger; issue `006/20512345/2026`; reject `#000166` → draft; tombol tidak muncul untuk operator | PASS |
| E12 | Browser DOM + API | `uat.kepsek`/`uat.waka`/`uat.operator` | Disposisi read-only dan transisi | Kepsek/waka: 18 baris Open, 2 overdue, status completed/cancelled tersedia, filter penerima tersembunyi, tanpa tombol tulis; operator: Start/Edit/Complete/Cancel, validasi panjang, `409` + **Reload Latest**, filter penerima 3 baris | PASS |
| E13 | Browser DOM + API | `uat.operator` | Assign + void guard | Incoming `#000025` Assign dua pegawai → 5 terbuka; void → `409 open_dispositions`; surat tetap active | PASS |
| E14 | Browser DOM + API | `uat.waka`, `uat.operator` | View-only, 390 px, keyboard, English | Baca `200`, tulis `403`; `document.scrollWidth=390`; fokus/Escape/aria; label English; **0 console error aplikasi** selain `409` yang disengaja | PASS |
| E15 | Query read-only SQLite + storage | — | Audit/immutability | `activities` tanpa recipient/sender/subject/notes/alasan; `letter_approvals` immutable; event transisi lengkap; nomor `001`–`006` unik; 1 baris lampiran soft-deleted/file tetap ada; counter 2026=6 | PASS |

**NOT RUN (tidak dikonversi menjadi PASS):**

- Konkurensi counter pada **MariaDB/InnoDB** atau race multi-proses nyata; T13 diuji sebagai replay/versi basi pada SQLite, bukan dua proses paralel.
- Dua tab browser nyata untuk `409`; konflik disimulasikan lewat API + UI stale.
- PDF/print/template (gate 07c/DEC-11).
- Restore drill yang memuat tabel dan nomor 07b; manifest backup tidak diubah.
- Screenshot-based visual regression berbasis file gambar.
- Notifikasi email/WhatsApp dan benchmark volume besar.
- **Fixture gap:** `.uat-phase07` belum memiliki surat void dengan disposisi completed sebagai history; perilaku void setelah disposisi tertutup dan read-only dicakup tes otomatis T23, bukan skenario browser. Tidak ada surat nyata yang diubah untuk menutup gap ini.

## 4. UAT manual

Environment: `.uat-phase07` SQLite terisolasi, SPA `http://127.0.0.1:14025`, API `http://127.0.0.1:18025`, cookie `school_phase07_session`, launcher `laravel-api/scripts/phase07-uat.php`. **Penerimaan pemilik (2026-11-02 WIB):** checklist #1–#16 **APPROVED** berdasarkan pernyataan **"Pengujian nomor 1-16 sudah berhasil dan saya approved."**; server 14025/18025 dihentikan setelah penerimaan (DEC-27; PID/command line diverifikasi, port bebas, data `.uat-phase07` dipertahankan, Apache/MariaDB tidak disentuh). Data `.uat-phase07` tidak di-reset.

Akun review: `uat.operator`/`uat.admin` (`letters.manage`), `uat.kepsek` (`letters.view`/`approve`/`sign`), `uat.waka` (`letters.view`). Password hanya berada di runtime `.uat-phase07/account-password.txt` yang diabaikan; tidak dicetak di dokumen.

Fixture `prepare-outgoing` (idempoten, additive):

- 45 surat keluar fixture + 1 surat buatan UI = **46**; 16 draft, 8 review, 10 approved, 6 issued, 6 cancelled.
- Nomor resmi `001/20512345/2026`–`006/20512345/2026`; counter 2026=6; surat cancelled sebelum issue tanpa nomor dan surat cancelled setelah issue mempertahankan nomor.
- Lampiran pada lima surat outgoing (satu file per surat).
- 32 disposisi pada surat masuk 1–26; surat `#000025` memiliki 6 disposisi (5 terbuka) dan `#000026` 4 disposisi (3 terbuka); dua disposisi in_progress overdue (due 2026-10-27).
- 14 pegawai sintetis: 12 aktif `UAT P07B Employee 01–12` + 2 non-aktif `UAT P07B Exited 01–02`; tidak menyentuh pegawai operasional.

Hasil UAT yang dijalankan agen:

1. **Daftar outgoing** (`uat.operator`): 45 fixture, filter status (16/8/10/6/6), pencarian nomor/penerima/perihal, rentang tanggal surat, paginasi; kolom Reference/Recorded/Letter Date/Recipient/Subject/Official No./Workflow/Files/Actions.
2. **Draft**: create dengan sender default sekolah, recipient, subject, kategori, tanggal; validasi field/tanggal/kategori; nomor belum ada; edit menaikkan versi; lampiran draft dapat diunggah/dihapus.
3. **Submit/approve/reject**: submit → In Review v3; kepsek approve `#000167` + catatan → ledger mencatat aktor/catatan; kepsek reject `#000166` dengan alasan → draft dan edit terbuka.
4. **Issue**: kepsek issue `#000167` → `006/20512345/2026`; `#000134`=`001`, `#000143`=`002`, `#000152`=`003`, `#000161`=`004`, `#000170`=`005`; nomor tidak tampil pada draft/review/approved; issue ulang ditolak `409` dengan nomor yang sama.
5. **Cancel**: operator cancel draft/review; kepsek cancel approved/issued; nomor yang sudah terbit dipertahankan; surat cancelled read-only; cancel kedua `409`.
6. **Disposisi**: kepsek/waka melihat 18 Open (11 pending + 7 in progress), 2 overdue; filter status/overdue; completed/cancelled history read-only; operator Assign multi-penerima, Start, Complete dengan follow-up, Cancel dengan alasan, konflik `409` + Reload Latest.
7. **Incoming guard**: `#000025` menampilkan 5 terbuka; Assign dua pegawai menambah count; void ditolak `409 open_dispositions`.
8. **English/390 px/console**: label English; `document.scrollWidth=390`; tabel bergulir di wrapper; dialog muat; fokus/Escape; 0 error console aplikasi.

**Temuan dan batasan selama UAT:**

- Tidak ada defect produk yang memerlukan perubahan kode. Penyesuaian hanya pada skrip otomasi UAT (sesi login, penantian state, selector DOM PrimeVue), yang tidak dikomit.
- `409` yang muncul pada uji konflik adalah respons yang disengaja, bukan error aplikasi.
- Fixture gap surat void dengan disposisi completed dicatat pada §3 dan tidak diubah dengan mutasi data manual.
- Surat `#000172` adalah surat buatan UAT (bukan fixture) yang berakhir Cancelled/Void v4; data ini dipertahankan.

## 5. Risiko dan keputusan

- **Penomoran** (D4): counter dikunci di transaksi + unique `official_number`; SQLite diuji; MariaDB/InnoDB dan dua issue paralel nyata **NOT RUN**. Nomor tidak dipakai ulang setelah cancel.
- **Approval/sign** (D5): ability + self-check server; approver boleh sign; tidak ada delegasi/multi-level; admin teknis tidak otomatis. Reject wajib alasan 10–255.
- **Disposisi** (D6): penerima pegawai aktif; satu baris per penerima; completed/cancelled final; disposisi terbuka memblokir void. Tidak ada auto-cancel saat void; keputusan manusia tetap dicatat.
- **Lampiran** (D7): hanya draft outgoing; file privat, soft delete, file disimpan sampai DEC-13. Tidak ada PDF/preview.
- **Audit/history** (D10): allowlist minimal; event/approval immutable; tidak ada teks catatan atau nama penerima di `activities`.
- **Fixture gap**: surat void dengan disposisi completed tidak ada di `.uat-phase07`; perilaku produk dicakup tes otomatis, tetapi skenario browser untuk history tersebut belum dijalankan.
- **Batas bukti**: seluruh bukti runtime memakai SQLite; migrasi belum diterapkan ke database operasional/MariaDB. Restore drill 07b, visual regression, dan benchmark volume belum dijalankan.
- **UAT lifecycle**: server 14025/18025 tetap berjalan sampai penerimaan; setelah penerimaan dihentikan sesuai DEC-27 dengan verifikasi PID/command line, data `.uat-phase07` dipertahankan.
- **Gate**: DEC-11 (07c: PDF/kop/tanda tangan/stempel) dan DEC-13 (retensi) tetap OPEN. Merge/deploy, force-push, migrasi DB operasional/MariaDB, dan perubahan dependency/service/`.env` tidak diotorisasi.

## 6. Sinkronisasi dokumen

Diperbarui: `docs/DATABASE.md` (kolom/tabel 07b), `docs/API-CONTRACTS.md` (19 endpoint dan kontrak workflow/nomor/`409`), `docs/ACCESS-CONTROL.md` (matriks route/ability 07b dan self-check), `docs/OPERATIONS.md` (penomoran, approval/sign, disposisi, lifecycle UAT), `docs/REQUIREMENTS.md` (FR-08 selesai untuk Fase 07; FR-09 partial untuk 07c), `docs/TESTING.md` (bukti dan NOT RUN 07b), `docs/DECISIONS.md` (DEC-33 IMPLEMENTED, agent-verified; DEC-06 CLOSED), `CHANGELOG.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-07-LETTERS.md`, `reports/feature-update/PHASE-07-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `PROJECT_CONTEXT.md`, laporan ini, dan checklist manual.

Commit/push dilakukan agar pemilik dapat meninjau implementasi di GitHub; commit/push bukan penerimaan manual. Tidak ada merge/deploy/force-push, perubahan `.env` utama, dependency/lockfile, service, DB terlarang, atau migrasi database operasional/MariaDB.

## 7. Langkah berikutnya

1. **Review pemilik:** **APPROVED 2026-11-02 WIB (#1–#16)** berdasarkan "Pengujian nomor 1-16 sudah berhasil dan saya approved."; agen tidak mengulang skenario.
2. **Setelah penerimaan:** penerimaan dicatat pada commit dokumentasi tersendiri; server UAT 14025/18025 dihentikan 2026-11-02 sesuai DEC-27 dengan verifikasi PID/command line; data `.uat-phase07` dipertahankan.
3. **Berhenti sebelum 07c.** Rencana 07c disusun terpisah dan menunggu keputusan pemilik (DEC-11 dan dependency PDF); implementasi belum diotorisasi. Retensi lampiran menunggu DEC-13.
4. Merge/deploy, force-push, migrasi database operasional/MariaDB, dan perubahan dependency/service/`.env` tetap tidak diotorisasi.
