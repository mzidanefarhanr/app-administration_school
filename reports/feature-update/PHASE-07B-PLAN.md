# Rencana Fase 07 — Subfase 07b (Disposisi, Surat Keluar, dan Nomor)

- Tanggal: 2026-11-01 (WIB). Branch: `main`. Baseline dokumen: `c4bd338` (paket rencana 07b; audit baseline `aec356c`).
- Status dokumen: **PLAN APPROVED — DEC-33 APPROVED 2026-11-01 WIB** berdasarkan pernyataan pemilik **"Saya setuju D1-D11"**. Implementasi **07b diotorisasi**; bukti implementasi dicatat pada laporan 07b setelah verifikasi. D4–D5 menutup **DEC-06**.
- Cakupan: **07b saja** — disposisi surat masuk (penerima, tenggat, status, tindak lanjut), surat keluar dengan alur draft → review → approved → issued/cancelled, penomoran resmi yang unik dan atomik, serta approval/sign yang terpisah dari admin teknis.
- Di luar cakupan: **07c** (template PDF, kop, tanda tangan gambar, stempel, snapshot dokumen), OCR, e-sign provider, email/WhatsApp otomatis, arsip/versioning Fase 09, retensi DEC-13, merge/deploy, migrasi database operasional/MariaDB, perubahan dependency/lockfile/`.env`/service.
- Dokumen acuan: `roadmap/phases/PHASE-07-LETTERS.md` (07b), `roadmap/ROADMAP.md`, `docs/DECISIONS.md` (DEC-06, DEC-11, DEC-13, DEC-27, DEC-32, DEC-33), `docs/REQUIREMENTS.md` (FR-08, FR-09, aturan nomor/approval), `docs/DATABASE.md`, `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `reports/feature-update/PHASE-07A-REPORT.md`, `reports/feature-update/PHASE-07A-MANUAL-REVIEW.md`.
- Gate: 07a **IMPLEMENTED + agent-verified + OWNER APPROVED 2026-11-01 WIB (#1–#16)**. **DEC-06 CLOSED** melalui D4–D5/DEC-33; **DEC-11 tetap OPEN** dan hanya mengikat 07c; **DEC-13 tetap OPEN**.

---

## 1. Posisi dan prasyarat

| Prasyarat | Kondisi aktual 2026-11-01 | Status |
|---|---|---|
| Subfase 07a | Surat masuk, lampiran privat, edit/void berversi, history immutable, dan UI Incoming Letters selesai; owner approved #1–#16; server UAT 14025/18025 dihentikan (DEC-27); data `.uat-phase07` dipertahankan | Terpenuhi |
| Ability surat | `letters.view`, `letters.manage`, `letters.approve`, `letters.sign` sudah terdefinisi; admin/operator mendapat `view`/`manage`; kepala sekolah mendapat `view`/`approve`/`sign`; wakil kesiswaan hanya `view` | Terpenuhi; tidak perlu ability baru |
| Skema surat | Tabel `letters`, `letter_attachments`, `letter_events` ada; `direction` mendukung `outgoing`, tetapi belum ada kolom nomor resmi, penerima, workflow, approval, atau issue | Perlu migration additive |
| Disposisi | Belum ada tabel, model, service, endpoint, atau UI disposisi | Perlu dibuat |
| Penomoran | Belum ada counter/nomor resmi; `source_number` sengaja tidak unik dan bukan nomor internal | Perlu dibuat; gate DEC-06 |
| Approver/signatory | `letters.approve`/`letters.sign` sudah sensitif dan dipetakan ke Kepala Sekolah; belum dipakai di endpoint | Dapat dipakai 07b |
| Frontend | Grup menu **Letters** hanya berisi **Incoming Letters**; belum ada halaman Outgoing/Dispositions | Perlu ditambah |
| Data pendukung | `employees` memiliki relasi opsional ke `users`; `school_profiles` menyimpan `principal_id` dan NPSN sekolah; tidak ada kop/tanda tangan/stempel | Dipakai untuk penerima disposisi dan default pengirim; bukan untuk PDF |
| PDF | Tidak ada library PDF di `composer.json`/`composer.lock` | Gate 07c; bukan 07b |
| UAT | `.uat-phase07` sudah ada dan server berhenti; data 07a dipertahankan | Dipakai ulang dengan fixture additive |
| Keputusan | **DEC-06 OPEN** (format nomor, reset counter, pejabat penandatangan, kapan nomor diterbitkan); **DEC-11 OPEN** (template/PDF); **DEC-13 OPEN** (retensi) | D4–D5 mengajukan DEC-06; DEC-11/13 tetap OPEN |

Seluruh kondisi di atas diverifikasi dari source, migration, konfigurasi, dan dokumen aktual, bukan dari data operasional.

---

## 2. Temuan audit (fakta kode)

### 2.1 07a hanya mengimplementasikan surat masuk

- Route surat hanya grup `/api/letters/incoming` dengan sembilan endpoint: `laravel-api/routes/api.php:188-208`.
- `LetterService` selalu membatasi pencarian dengan `direction = incoming` pada `findIncoming()`/`lockIncoming()`: `laravel-api/app/Services/Letters/LetterService.php:324-351`; create selalu menulis `direction = incoming`: `:127-142`.
- Model `Letter` sudah memiliki konstanta `DIRECTION_OUTGOING`, tetapi tidak ada logika outgoing: `laravel-api/app/Models/Letter.php:21-31`.
- Tidak ada controller, request, service, model, route, atau UI untuk outgoing, disposisi, nomor, approval, atau sign. Pencarian `letter_dispositions|letter_number_counters|letter_approvals|workflow_status|outgoing` hanya menemukan konstanta `DIRECTION_OUTGOING` dan tes 07a.

**Implikasi:** 07b menambah domain baru di atas tabel `letters` dan event/attachment yang sudah ada; tidak ada consumer outgoing lama yang perlu dipertahankan.

### 2.2 Skema `letters` masih berorientasi surat masuk

- `letters` memiliki `received_date` dan `letter_date` **NOT NULL**, `sender` **NOT NULL**, `source_number` nullable/tidak unik, `status` default `active`, dan kolom void: `laravel-api/database/migrations/2029_08_02_000001_create_letters_table.php:16-38`.
- Tidak ada `recipient`, `official_number`, `workflow_status`, `approved_*`, `issued_*`, atau nomor resmi.
- `status` hanya `active`/`void`; `config/letters.php:19` juga hanya mendaftarkan dua status tersebut.
- `composer.json` tidak memuat `doctrine/dbal`: `laravel-api/composer.json:7-13`. Karena itu, mengubah nullability kolom lama lewat `->change()` tidak aman untuk SQLite (tes) dan tidak boleh menambah dependency tanpa keputusan terpisah.

**Implikasi desain:** jangan mengubah kolom lama secara destruktif. Tambahkan kolom nullable baru. Untuk outgoing:
- `sender` tetap wajib dan diisi nama sekolah (pengirim sebenarnya); UI dapat memakai default dari profil sekolah aktif.
- `received_date` dipakai sebagai **tanggal pencatatan** (recorded date) untuk outgoing, dengan label UI berbeda; validasi tetap `letter_date ≤ received_date ≤ hari ini`.
- `source_number` tidak dipakai untuk outgoing; nomor resmi memakai kolom baru `official_number`.
- `status` tetap `active`/`void`; pembatalan outgoing memakai `void` + `voided_*` (label UI “Cancelled”), sedangkan `workflow_status` menyimpan `draft`/`review`/`approved`/`issued`/`cancelled`.

### 2.3 Lampiran dan history dapat dipakai ulang

- `letter_attachments` menyimpan path privat, MIME, ukuran, checksum, uploader, dan soft delete: `laravel-api/database/migrations/2029_08_02_000002_create_letter_attachments_table.php`.
- `letter_events` immutable, append-only, dengan metadata JSON: `laravel-api/database/migrations/2029_08_02_000003_create_letter_events_table.php:15-25`; model menolak update/delete: `laravel-api/app/Models/LetterEvent.php:64-77`.
- `LetterAttachmentService` sudah menyediakan validasi file, penulisan privat, checksum, kompensasi, dan download berizin: `laravel-api/app/Services/Letters/LetterAttachmentService.php:24-189`.
- `LetterAttachment` allowlist audit tidak memuat nama asli/path/checksum: `laravel-api/app/Models/LetterAttachment.php:30-45`.

**Implikasi:** outgoing memakai tabel, service, dan endpoint lampiran yang sama polanya; tidak perlu tabel lampiran/event baru. Event list perlu diperluas untuk transisi 07b dan disposisi.

### 2.4 Ability approval/sign sudah siap, tetapi belum ditegakkan

- Definisi: `letters.view`, `letters.manage`, `letters.approve`, `letters.sign`; approve/sign sensitif: `laravel-api/app/Support/AbilityCatalog.php:53-56`.
- Admin Sistem/Operator Sekolah hanya mendapat `letters.view` + `letters.manage`; Kepala Sekolah mendapat `letters.view` + `letters.approve` + `letters.sign`: `AbilityCatalog.php:86-140`; komentar menegaskan admin tidak otomatis mendapat approve/sign: `:96-97`.
- Label UI English sudah ada: `vue-app/src/views/pages/master/Roles.vue:51-54`.
- Tidak ada pemakaian `letters.approve`/`letters.sign` pada route/controller saat ini.

**Implikasi:** 07b tidak perlu ability/role migration baru. Approval/sign memakai ability yang ada; server tetap menegakkan `can:` + `authorize`; UI hanya menyembunyikan tombol.

### 2.5 Data penerima disposisi dan penandatangan

- `employees` memiliki `user_id` opsional dan soft delete; relasi akun: `laravel-api/app/Models/Employee.php:130-133`; resolusi akun legacy: `:151-173`.
- `school_profiles` menyimpan `principal_id` (FK `users`), `education_school_npsn`, `official_number`, dan data sekolah per tahun: `laravel-api/database/migrations/2026_10_15_150625_create_school_profiles_table.php:18-36`; model/relasi: `laravel-api/app/Models/SchoolProfile.php:50-65`.
- Tidak ada kolom kop, tanda tangan gambar, stempel, atau format nomor di `school_profiles`.

**Implikasi:** penerima disposisi dapat memakai profil `employees` (termasuk yang tidak punya akun), dengan akun opsional untuk membaca. Identitas penandatangan tidak boleh di-hard-code ke `principal_id`; D5 mengusulkan berbasis ability `letters.sign` dan aktor tercatat. Data kop/tanda tangan/stempel tetap 07c/DEC-11.

### 2.6 Frontend masih hanya surat masuk

- Grup menu **Letters** hanya **Incoming Letters**: `vue-app/src/layout/AppMenu.vue:50-56`.
- Route `/public/letters/incoming` memakai `letters.view`: `vue-app/src/router/index.js:79-84`.
- `IncomingLetters.vue` sudah memiliki pola `canManage`, dialog, filter, lampiran, history, dan panel konflik: `vue-app/src/views/pages/public/IncomingLetters.vue:1-120`.
- Tidak ada halaman/route Outgoing atau Dispositions.

**Implikasi:** tambahkan dua halaman baru dan perluas detail Incoming dengan panel disposisi; pertahankan pola English, `409` Reload Latest, 390 px, dan aksesibilitas.

### 2.7 Penomoran resmi belum ada dan DEC-06 tetap OPEN

- Tidak ada tabel counter, kolom nomor resmi, atau service penomoran.
- `source_number` adalah nomor dari pengirim, nullable, tidak unik; bukan nomor keluar: migration `:22`, D2/DEC-32.
- `docs/DECISIONS.md:307` — **DEC-06 OPEN**: format nomor, reset counter, pejabat penandatangan, kapan nomor diterbitkan.
- `docs/REQUIREMENTS.md:41-42` — nomor surat keluar unik, dibuat server atomik; pembatalan tidak memakai ulang nomor yang telah diterbitkan; persetujuan tidak boleh menjadikan diri sendiri approver yang berwenang.

**Implikasi:** D4–D5 wajib diputuskan pemilik sebelum implementasi. Rencana ini tidak menganggap format tertentu final; rekomendasi default disediakan agar dapat disetujui atau diganti.

### 2.8 DEC-11 dan DEC-13 tetap menjadi gate lain

- `docs/DECISIONS.md:320` — DEC-11 OPEN: kop, tanda tangan, stempel, jenis surat pertama, format rekap; mengikat 07c.
- `docs/DECISIONS.md:322` — DEC-13 OPEN: retensi data pribadi termasuk lampiran.
- Tidak ada library PDF dan dependency tidak boleh diubah tanpa keputusan terpisah.

**Implikasi:** 07b hanya mencatat approval/sign sebagai aksi otorisasi; tidak menghasilkan PDF, gambar tanda tangan, atau kop. Lampiran yang di-soft-delete tetap disimpan sampai DEC-13.

### 2.9 Pola tes dan UAT 07a dapat diperluas

- Tes 07a memakai Pest + `RefreshDatabase`, `Storage::fake('local')`, waktu tetap Asia/Jakarta, dan empat peran: `laravel-api/tests/Feature/Letters/IncomingLetterTest.php:17-29`.
- Launcher UAT 07a: `laravel-api/scripts/phase07-uat.php`; perintah `init`, `prepare`, `prepare-letters`, `status`: `:20`, `:73`, `:99`, `:229`, `:250`; API/SPA `18025/14025`, cookie `school_phase07_session`.
- `.uat-phase07` sudah berisi data 07a (126 surat/64 lampiran/221 event) dan server sudah dihentikan.

**Implikasi:** 07b memakai ulang `.uat-phase07` dengan fixture **additive dan idempotent** (`prepare-outgoing`, `prepare-dispositions`), tanpa reset data 07a. Port tetap 14025/18025; server dihentikan setelah penerimaan (DEC-27).

---

## 3. Urutan pekerjaan yang diusulkan

| Langkah | Pekerjaan | Hasil yang dapat diverifikasi |
|---|---|---|
| S0 | Preflight: audit HEAD, pastikan `.uat-phase07` ada, verifikasi ability approve/sign di DB UAT, tidak ada data outgoing/disposisi, dan format nomor yang disetujui pemilik | Baseline/isolation jelas; tidak ada perubahan tabel lama |
| S1 | Migration additive: kolom outgoing pada `letters`, tabel `letter_dispositions`, `letter_approvals`, `letter_number_counters`; model, cast, relasi, allowlist audit | Tabel/kolom ada; 07a tetap berjalan; tidak ada perubahan kolom lama |
| S2 | `config/letters.php` + `LetterNumberService` (validasi format, counter atomik, issue) + `LetterEvent` event baru | Nomor unik/atomik; format tervalidasi; event tercatat |
| S3 | `OutgoingLetterService` + FormRequest + controller + route: draft/create/edit/submit/approve/reject/issue/cancel, lampiran outgoing | Transisi status, `expected_version`, `409`, approval/sign, lampiran |
| S4 | `LetterDispositionService` + FormRequest + controller + route: recipients, list, create multi-penerima, update status/tindak lanjut, cancel, guard void | Disposisi berversi; overdue; open-block void; `409` |
| S5 | Perluasan `LetterService` incoming: hitung/serialisasi disposisi, guard void, options | Detail/list incoming memuat disposisi tanpa merusak 07a |
| S6 | UI: panel disposisi di Incoming Letters, halaman Dispositions, halaman Outgoing Letters, router, menu Letters | Alur English, view-only, action visibility, 409 reload, 390 px |
| S7 | Tes backend T1–T26 + regresi suite + Pint + `route:list` | Matriks §6 PASS; tidak ada regresi |
| S8 | UAT `.uat-phase07`: launcher `prepare-outgoing`/`prepare-dispositions`, fixture, browser/API checklist, laporan | UAT siap review; bukti aktual tercatat |
| S9 | Sinkronisasi DATABASE/API-CONTRACTS/ACCESS-CONTROL/REQUIREMENTS/OPERATIONS/CHANGELOG/laporan/INDEX/HANDOFF/ROADMAP/fase; commit+push paket review | Paket review dapat dibaca di GitHub; server berjalan sampai penerimaan |

Setelah S9, berhenti untuk review pemilik. Tidak ada merge, deploy, atau lanjutan otomatis ke 07c.

---

## 4. Keputusan yang perlu pemilik (D1–D11)

Rekomendasi ditulis sebagai default. **D1–D11 APPROVED 2026-11-01 WIB** berdasarkan pernyataan pemilik **"Saya setuju D1-D11"** dan dicatat sebagai **DEC-33**; **DEC-06 CLOSED** melalui D4–D5. Implementasi 07b mengikuti keputusan ini tanpa menebak format/penandatangan di luar D4–D5.

| # | Pertanyaan | Rekomendasi | Alternatif / dampak |
|---|---|---|---|
| **D1** | Subfase mana yang diotorisasi oleh rencana ini? | **07b saja**: disposisi surat masuk, surat keluar, nomor, approval/sign. 07c (template/PDF/snapshot/kop/tanda tangan/stempel) tetap memerlukan rencana dan dependency terpisah. DEC-11/DEC-13 tetap OPEN. | Mengerjakan 07b+07c sekaligus (melanggar satu subfase per instruksi; 07c membutuhkan dependency PDF). |
| **D2** | Model data dan pemisahan incoming/outgoing? | Pakai ulang `letters` dengan `direction='outgoing'`. Migration additive: `recipient` (string 150, nullable), `official_number` (string 100, nullable, **unique**), `workflow_status` (string 20, nullable, index), `approved_at`/`approved_by`, `issued_at`/`issued_by`. Tabel baru: `letter_dispositions`, `letter_approvals`, `letter_number_counters`. **Jangan ubah nullability kolom lama** (tidak ada `doctrine/dbal`). Untuk outgoing: `sender` = nama sekolah, `received_date` = tanggal pencatatan (label UI “Recorded date”), `recipient` = tujuan, `source_number` tidak dipakai. `status` tetap `active`/`void`; pembatalan memakai `void` + `voided_*`, sedangkan `workflow_status` menyimpan status alur. | Tabel `outgoing_letters` terpisah (duplikasi lampiran/event dan menyimpang dari D2/DEC-32); mengubah kolom lama menjadi nullable (butuh dependency/raw SQL berisiko); memakai `sender` untuk penerima (semantik salah). |
| **D3** | Alur status surat keluar? | `draft → review → approved → issued`; `draft/review → cancelled` oleh `letters.manage`; `approved/issued → cancelled` oleh `letters.approve`; `review → draft` saat reject. Tidak ada lompat status, tidak ada reissue, tidak ada un-cancel. Edit metadata dan tambah/hapus lampiran **hanya saat `draft`**; submit membekukan; reject kembali ke `draft`. Setiap transisi memakai `expected_version` dan menaikkan versi; konflik `409` tanpa mutasi. Nomor yang sudah terbit tetap disimpan saat pembatalan dan tidak dipakai ulang. | Mengizinkan edit setelah approved (integritas approval rusak); status `cancelled` di kolom `status` tanpa `workflow_status` (mencampur incoming/outgoing); membolehkan lompat draft→issued (melanggar kriteria fase). |
| **D4** | Format nomor, reset counter, dan kapan nomor diterbitkan (DEC-06)? | Format dapat dikonfigurasi dengan token; rekomendasi **`{seq:03d}/{school_code}/{year}`**, `school_code` default NPSN sekolah aktif, contoh `001/20512345/2026`. Counter **global surat keluar per tahun kalender**, reset 1 Januari Asia/Jakarta; nomor diterbitkan **saat transisi `approved → issued`** (aksi sign), bukan saat approve/draft. Wajib ada `{seq}` dan `{year}`; maksimum 100 karakter; nomor tidak dapat diedit/diganti; counter tidak pernah mundur; pembatalan tidak memakai ulang nomor. Jika pemilik memiliki format resmi lain, tuliskan format exact saat menyetujui D4; implementasi memakai format itu. | Format per kategori (counter lebih rumit, risiko duplikasi lintas kategori); reset per tahun ajaran (perlu definisi periode akademik dan format tahun `2026/2027`); nomor saat approve (nomor terpakai untuk surat yang belum terbit); nomor manual (tidak atomik). |
| **D5** | Siapa yang boleh approve/sign dan bagaimana pemisahan tugas (DEC-06)? | `letters.approve` untuk approve/reject; `letters.sign` untuk issue/sign. **Pembuat surat tidak boleh approve/reject/issue suratnya sendiri** (`403`). Approver boleh juga sign jika memiliki kedua ability; approval dan signing dicatat terpisah. Admin/Operator tidak otomatis mendapat approve/sign; tanpa ability/role migration baru. Tidak ada delegasi, approval berlapis, atau tanda tangan digital tersertifikasi. Sign pada 07b adalah **aksi otorisasi tercatat**, bukan gambar/PDF tanda tangan (07c). Reject wajib alasan 10–255; approval note opsional ≤500. | Principal-only hard-coded dari `school_profiles.principal_id` (menghalangi delegasi dan akun pengganti); approver harus selalu berbeda dari signer (lebih ketat, tetapi menghalangi kepala sekolah menandatangani surat yang ia setujui); admin teknis boleh approve (melanggar pemisahan). |
| **D6** | Model disposisi? | Hanya untuk surat **masuk**. Satu disposisi per penerima; penerima = **profil `employees`** aktif (boleh tanpa akun), dipilih dari direktori; multi-penerima membuat beberapa baris dalam satu transaksi. Status `pending → in_progress → completed`; `pending/in_progress → cancelled`; tanpa reopen. `due_date` wajib; overdue dihitung dari tanggal (bukan disimpan); `follow_up_note` wajib 10–2000 saat completed; alasan cancel 10–255. Create/update/cancel oleh `letters.manage`; baca oleh `letters.view`; opsi penerima memerlukan `letters.manage` **dan** `employees.view`. Disposisi berversi (`expected_version`, `409`). Surat masuk dengan disposisi `pending`/`in_progress` tidak dapat di-void (`409`); disposisi completed/cancelled tetap menjadi history. | Penerima = akun `users` (staf tanpa akun tidak dapat didisposisi); satu baris dengan banyak penerima (status/tindak lanjut per orang tidak terlacak); disposisi pada surat keluar (tidak sesuai alur); auto-cancel disposisi saat void (menghilangkan keputusan manusia). |
| **D7** | Aturan edit dan lampiran? | Outgoing: create/edit/upload/hapus lampiran hanya saat `draft`; `review`/`approved`/`issued`/`cancelled` read-only; reject kembali ke `draft` dan membuka edit. Incoming: aturan 07a tidak berubah (active editable, void read-only). Lampiran memakai aturan 07a: PDF/JPG/JPEG/PNG, 10 MB/file, 5/surat, privat, UUID + sha256, soft delete, file disimpan sampai DEC-13. Download `letters.view`; upload/hapus `letters.manage`. Tidak ada PDF/preview/generation di 07b. | Mengizinkan lampiran berubah setelah submit (approval tidak lagi atas isi yang sama); mengizinkan edit setelah issued (surat final berubah); menyimpan versi lampiran (di luar scope; Fase 09). |
| **D8** | Hak akses dan route matrix? | Tanpa ability baru. Baca/download/opsi: `letters.view`. Draft/edit/submit/upload/hapus lampiran/cancel draft-review: `letters.manage`. Approve/reject: `letters.approve`. Issue/sign: `letters.sign`. Cancel approved/issued: `letters.approve` (route baseline `letters.view` + pemeriksaan ability dinamis di server). Opsi penerima disposisi: `letters.manage` + `employees.view`. Semua route memakai `auth:sanctum` + `active`; object-level check memastikan `direction` dan kepemilikan child; pelanggaran `403`/`404`, bukan hanya tombol tersembunyi. | Memberi approve/sign ke admin teknis (melanggar DEC-03); ability baru `letters.disposition` (tidak perlu); membiarkan UI sebagai satu-satunya penegak (melanggar aturan repo). |
| **D9** | Kontrak API dan UI? | Endpoint di §5.6: 6 endpoint disposisi + 13 endpoint outgoing; list/detail incoming diperluas dengan disposisi. Halaman: **Incoming Letters** (panel disposisi + jumlah open/overdue), **Dispositions** (worklist), **Outgoing Letters** (draft/approval/issue/cancel). English, 390 px, keyboard, `409` Reload Latest, tanpa tombol dummy. Detail memuat lampiran + event + approval; tanpa path/checksum. Respons `StatusResource` (`success`, `message`, `data`), create/update/transisi **`200`**, bukan `201`. | Satu halaman gabungan incoming/outgoing (ramai dan sulit diuji); disposisi hanya di detail (tidak ada worklist tindak lanjut); endpoint generik `/letters/{id}` tanpa pemisahan direction (rawan IDOR). |
| **D10** | Audit, history, dan privasi? | `letter_events` diperluas: `submitted`, `approved`, `rejected`, `issued`, `cancelled`, `disposition_created`, `disposition_updated`, `disposition_completed`, `disposition_cancelled`; tetap immutable. `letter_approvals` immutable (approve/reject, aktor, catatan, waktu) tanpa endpoint edit/hapus. Allowlist `Letter` menambah `official_number`, `workflow_status`, `approved_at/by`, `issued_at/by`; **tidak** menambah `recipient`, `sender`, `subject`, `notes`, atau alasan. Allowlist `LetterDisposition`: `letter_id`, `recipient_employee_id`, `due_date`, `status`, `version`, `completed_at/by`, `cancelled_at/by`; **tanpa** `note`, `follow_up_note`, `cancel_reason`. Event metadata minimal: id/status/nomor; tanpa teks catatan, nama penerima, atau isi file. | Menyimpan seluruh catatan disposisi di audit (risiko PII); menaruh nama penerima di event (PII); menjadikan `letter_approvals` dapat diedit (bukti approval hilang). |
| **D11** | UAT dan non-goals? | Pakai ulang **`.uat-phase07`** (SQLite; API `127.0.0.1:18025`; SPA `127.0.0.1:14025`; cookie `school_phase07_session`; storage `.uat-phase07/storage`; launcher `scripts/phase07-uat.php`). Tambahkan perintah idempotent `prepare-outgoing` dan `prepare-dispositions`: ≥40 surat keluar (draft/review/approved/issued/cancelled, nomor unik), ≥30 disposisi (pending/in_progress/completed/cancelled, overdue, multi-penerima), ≥10 pegawai sintetis, counter tahun berjalan; **tanpa reset** data 07a; tidak menyentuh `.uat-phase05/06` atau operasional. Server dihentikan setelah penerimaan (DEC-27); data dipertahankan. Non-goals: 07c/PDF/template/snapshot/kop/tanda tangan/stempel, OCR/e-sign/email/WhatsApp, delegasi/multi-level approval, retensi DEC-13, arsip/versioning Fase 09, ekspor, Dapodik, merge/deploy, dependency/service/`.env`, migrasi DB operasional/MariaDB. | Lingkungan baru `.uat-phase07b` (menduplikasi 126 surat 07a; tidak perlu); fixture <30 disposisi (kurang meyakinkan); reset `.uat-phase07` (kehilangan data 07a yang sudah diterima); server dibiarkan berjalan (melanggar DEC-27). |

**Ringkasan pertanyaan terbuka 07b:**

| Q | Jawaban yang diusulkan |
|---|---|
| Q1 — apakah nomor resmi boleh sama dengan nomor sumber? | Tidak relevan; `source_number` adalah nomor pengirim dan tidak unik. Nomor resmi memakai `official_number` unik yang dibuat server (D2/D4). |
| Q2 — apakah nomor dibuat saat draft? | Tidak; nomor dibuat saat `approved → issued` (sign). Draft/review/approved tidak memiliki nomor (D4). |
| Q3 — apakah nomor boleh dipakai ulang setelah pembatalan? | Tidak; nomor yang pernah terbit tetap tersimpan pada surat cancelled dan counter tidak mundur (D3/D4). |
| Q4 — apakah pembuat boleh approve suratnya sendiri? | Tidak; `403`. Approver boleh juga sign jika memegang `letters.sign` (D5). |
| Q5 — siapa penerima disposisi? | Profil `employees` aktif; akun login opsional; status diubah oleh `letters.manage` (D6). |
| Q6 — apakah surat masuk boleh di-void saat disposisi masih terbuka? | Tidak; `409` sampai disposisi completed/cancelled. Disposisi historis tetap disimpan (D6). |
| Q7 — apakah lampiran boleh diubah setelah submit? | Tidak; hanya saat `draft`. Reject membuka kembali edit (D7). |
| Q8 — apakah 07b menghasilkan PDF/tanda tangan? | Tidak; sign hanya aksi otorisasi tercatat. PDF/kop/tanda tangan/stempel adalah 07c/DEC-11 (D1/D5). |

---

## 5. Desain teknis

### 5.1 Migration additive

#### 5.1.1 Kolom baru pada `letters`

| Kolom | Tipe | Catatan |
|---|---|---|
| `recipient` | string(150), nullable, index | Tujuan surat keluar; wajib untuk `direction='outgoing'`; null untuk incoming |
| `official_number` | string(100), nullable, **unique** | Nomor resmi yang dibuat server; null sampai issued; tidak diedit manual |
| `workflow_status` | string(20), nullable, index | `draft`, `review`, `approved`, `issued`, `cancelled`; null untuk incoming |
| `approved_at` | timestamp nullable | Waktu approval terakhir |
| `approved_by` | FK `users` nullable, nullOnDelete | Aktor approval terakhir |
| `issued_at` | timestamp nullable | Waktu nomor diterbitkan/sign |
| `issued_by` | FK `users` nullable, nullOnDelete | Signatory/aktor issue |

- Tidak ada perubahan pada `received_date`, `letter_date`, `sender`, `status`, `voided_*`, atau `version`.
- Index tambahan: `(direction, workflow_status)`, `recipient`.
- Unique `official_number` memungkinkan banyak NULL pada SQLite/MariaDB; hanya nomor terbit yang unik.

#### 5.1.2 `letter_dispositions`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | — |
| `letter_id` | FK `letters`, restrictOnDelete | Hanya surat incoming |
| `recipient_employee_id` | FK `employees`, restrictOnDelete | Penerima dari direktori pegawai; soft delete pegawai tetap menyimpan history |
| `assigned_by` | FK `users`, restrictOnDelete | Aktor pembuat disposisi |
| `due_date` | date | Wajib; overdue dihitung, bukan disimpan |
| `status` | string(20), default `pending`, index | `pending`, `in_progress`, `completed`, `cancelled` |
| `note` | text nullable | Instruksi disposisi; tidak masuk audit |
| `follow_up_note` | text nullable | Hasil tindak lanjut; wajib saat completed; tidak masuk audit |
| `version` | unsigned big int, default 1 | `expected_version` |
| `completed_at` | timestamp nullable | — |
| `completed_by` | FK `users` nullable, nullOnDelete | — |
| `cancelled_at` | timestamp nullable | — |
| `cancelled_by` | FK `users` nullable, nullOnDelete | — |
| `cancel_reason` | string(255) nullable | Wajib saat cancelled; tidak masuk audit |
| `timestamps` | — | — |

- Index: `(letter_id, status)`, `(recipient_employee_id, status)`, `(due_date, status)`, `(status)`.
- Tidak ada soft delete; disposisi selesai/dibatalkan tetap history. Tidak ada hard delete.

#### 5.1.3 `letter_approvals`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | — |
| `letter_id` | FK `letters`, restrictOnDelete | — |
| `action` | string(20), index | `approved`, `rejected` |
| `actor_id` | FK `users`, restrictOnDelete | Approver |
| `notes` | text nullable | Catatan approval/alasan reject; tidak masuk `activities` |
| `created_at` | timestamp | Immutable; tanpa `updated_at` |

- Model menolak update/delete; tidak ada endpoint tulis langsung.
- Approval ledger ini melengkapi `letter_events`; keduanya mencatat aktor/waktu.

#### 5.1.4 `letter_number_counters`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | — |
| `scope` | string(50) | Default `outgoing`; dapat diperluas per kategori di masa depan |
| `period` | string(10) | Tahun kalender `2026` (default) atau periode lain sesuai D4 |
| `last_number` | unsigned big int, default 0 | Nomor terakhir yang terbit |
| `timestamps` | — | — |

- Unique `(scope, period)`; tanpa soft delete.
- Tidak ada endpoint langsung; counter hanya berubah di dalam transaksi issue.

### 5.2 Status dan matriks transisi outgoing

Nilai `workflow_status`: `draft`, `review`, `approved`, `issued`, `cancelled`. `status` tetap `active` sampai cancelled, lalu `void`; pembatalan mengisi `voided_at`, `voided_by`, `void_reason`.

| Dari | Aksi | Ke | Ability | Aturan |
|---|---|---|---|---|
| — | Create | `draft` | `letters.manage` | Wajib `sender`, `recipient`, `subject`, `category`, `letter_date`, `received_date`; tanpa nomor |
| `draft` | Edit/upload/hapus lampiran | `draft` | `letters.manage` | `expected_version`; hanya draft |
| `draft` | Submit | `review` | `letters.manage` | `expected_version`; membekukan edit/lampiran |
| `review` | Approve | `approved` | `letters.approve` | `expected_version`; aktor ≠ `created_by`; catatan opsional ≤500 |
| `review` | Reject | `draft` | `letters.approve` | `expected_version`; alasan 10–255; membuka edit/lampiran |
| `approved` | Issue/Sign | `issued` | `letters.sign` | `expected_version`; aktor ≠ `created_by`; nomor atomik; `issued_at/by` |
| `draft`/`review` | Cancel | `cancelled` | `letters.manage` | `expected_version`; alasan 10–255; tanpa nomor |
| `approved`/`issued` | Cancel | `cancelled` | `letters.approve` | `expected_version`; alasan 10–255; nomor (jika ada) dipertahankan |
| `issued` | Edit/upload/hapus | — | — | Ditolak `409`; hanya cancel |
| `cancelled` | Semua transisi | — | — | Ditolak `409`; read-only |

- Tidak ada transisi yang melompat; server memeriksa status saat ini di dalam transaksi dengan `lockForUpdate`.
- Semua transisi menaikkan `letters.version` dan menulis event; approve/reject juga menulis `letter_approvals`.
- `lifecycle_status` di API: incoming `active`/`void`; outgoing `workflow_status` (`draft`/`review`/`approved`/`issued`/`cancelled`).
- UI label cancellation: “Cancelled”; alasan tersimpan di `void_reason` tetapi API mengekspos `cancel_reason` untuk outgoing (alias baca, bukan kolom baru).

### 5.3 Alur penomoran

1. Preflight/config: format divalidasi (token whitelist, wajib `{seq}` dan `{year}`, maks. 100 karakter); `school_code` dari config atau NPSN sekolah aktif; jika tidak ada, issue `422` dengan pesan konfigurasi.
2. Issue dipanggil dalam `DB::transaction`:
   - Lock baris `letters` (`lockForUpdate`), validasi `workflow_status='approved'`, `expected_version`, dan aktor memiliki `letters.sign`, serta aktor ≠ `created_by`.
   - Tentukan `period` dari tanggal issue (Asia/Jakarta) sesuai D4.
   - `insertOrIgnore` baris counter `(scope='outgoing', period)`; `lockForUpdate`; `last_number + 1`.
   - Bentuk `official_number` dengan token; set `official_number`, `issued_at`, `issued_by`, `workflow_status='issued'`, `version+1`; simpan.
   - Insert event `issued` dengan metadata `{official_number, period, sequence}`; commit.
3. Unique index `official_number` adalah pertahanan terakhir; bila terjadi benturan, transaksi rollback (counter ikut rollback), respons `409` tanpa nomor baru.
4. Replay/parallel: request kedua dengan `expected_version` lama mendapat `409` berisi `{letter_version, workflow_status, official_number}`; tidak ada nomor kedua. Request dengan versi terbaru tetapi status sudah `issued` juga `409` (bukan issue ulang).
5. Pembatalan setelah issue tidak mengubah counter dan tidak menghapus `official_number`; nomor tidak dipakai ulang.
6. Tidak ada endpoint preview nomor yang mengonsumsi counter; opsi hanya menampilkan contoh format, bukan nomor berikutnya.

Contoh format rekomendasi: `{seq:03d}/{school_code}/{year}` → `001/20512345/2026`. Token yang didukung: `{seq}`, `{seq:03d}`, `{year}`, `{month}`, `{month_roman}`, `{school_code}`, `{category}`. Format final mengikuti keputusan pemilik (D4); jangan menebak format resmi sekolah.

### 5.4 Disposisi

- **Cakupan**: hanya `direction='incoming'`; surat void tidak dapat menerima disposisi baru.
- **Penerima**: `employees` aktif (tidak soft-deleted; `exit_date` null atau ≥ hari ini). Opsi penerima memerlukan `letters.manage` + `employees.view`; respons hanya id, nama, tipe pegawai (tanpa NIK/kontak).
- **Create multi-penerima**: daftar `recipients[]`; dalam satu transaksi lock surat, validasi semua penerima, tolak duplikasi aktif untuk `(letter_id, recipient_employee_id)`, buat satu baris per penerima + event per baris. Jika satu gagal, seluruh permintaan rollback.
- **Status**: `pending` → `in_progress` → `completed`; `pending`/`in_progress` → `cancelled`; completed/cancelled final (tanpa reopen).
- **Update**: `due_date` dan `note` boleh diubah saat pending/in_progress; `follow_up_note` wajib 10–2000 saat completed; `cancel_reason` wajib 10–255 saat cancelled; `expected_version` wajib; `409` tanpa mutasi.
- **Overdue**: dihitung `due_date < hari ini (Asia/Jakarta)` dan status pending/in_progress; tidak disimpan; UI menandai dan filter `overdue=1`.
- **Void surat masuk**: `LetterService::void` menolak `409` bila ada disposisi pending/in_progress; completed/cancelled tidak menghalangi. Pesan error memuat jumlah disposisi terbuka.
- **Read**: detail incoming memuat disposisi; halaman Dispositions memuat semua disposisi dengan filter status/overdue/penerima/pencarian surat; `letters.view`.
- **Tanpa notifikasi**: tidak ada email/WhatsApp; penerima tanpa akun tetap dapat didisposisi dan status dicatat operator.

### 5.5 Aturan edit dan lampiran

| Objek | Boleh edit/upload/hapus | Keterangan |
|---|---|---|
| Incoming active | Ya (`letters.manage`) | Aturan 07a tetap; void read-only |
| Incoming void | Tidak | `409`; lampiran tetap dapat diunduh |
| Outgoing draft | Ya (`letters.manage`) | Metadata dan lampiran |
| Outgoing review/approved/issued/cancelled | Tidak | `409`; reject (review→draft) membuka kembali |
| Lampiran semua status | Download `letters.view` | File privat, tanpa URL publik; soft delete; file disimpan sampai DEC-13 |

- Lampiran outgoing memakai `LetterAttachmentService` yang sama; path tetap `attachments/letters/{letter_id}/{uuid}.{ext}`; tidak ada duplikasi file per revisi.
- Tidak ada PDF generation/preview; 07c akan menangani render dan snapshot.

### 5.6 Endpoint (usulan)

**Disposisi (6 route baru):**

| Method | Endpoint | Ability | Perilaku |
|---|---|---|---|
| GET | `/api/letters/dispositions` | `letters.view` | Worklist semua disposisi; filter `status`, `overdue`, `recipient_employee_id`, `search`, `per_page`, `page`; urut `due_date` asc lalu `id` |
| GET | `/api/letters/dispositions/options` | `letters.view` | Status disposisi, opsi `per_page`, batas catatan |
| GET | `/api/letters/dispositions/recipients?search=&per_page=` | `letters.manage` + `employees.view` | Opsi penerima aktif; id/nama/tipe; tanpa PII |
| POST | `/api/letters/incoming/{letter}/dispositions` | `letters.manage` | `recipients[]`, `due_date`, `note` opsional; transaksi multi-baris |
| PUT/PATCH | `/api/letters/incoming/{letter}/dispositions/{disposition}` | `letters.manage` | `status`, `due_date`, `note`, `follow_up_note`, `cancel_reason`, `expected_version`; transisi tervalidasi |
| POST | `/api/letters/incoming/{letter}/dispositions/{disposition}/cancel` | `letters.manage` | `reason` 10–255 + `expected_version`; status cancelled; tanpa hapus |

- `{disposition}` harus milik `{letter}`; surat harus incoming; pelanggaran `404`.
- Detail incoming (`GET /api/letters/incoming/{letter}`) memuat `dispositions`; list incoming memuat `dispositions_open_count` dan `dispositions_overdue_count`.
- `POST` create disposisi mengembalikan detail surat yang diperbarui (termasuk disposisi baru) dengan envelope `200`.

**Surat keluar (13 route baru):**

| Method | Endpoint | Ability | Perilaku |
|---|---|---|---|
| GET | `/api/letters/outgoing/options` | `letters.view` | Kategori, status workflow, opsi `per_page`, batas lampiran, `default_sender` dari profil sekolah aktif, contoh format nomor (bukan nomor berikutnya) |
| GET | `/api/letters/outgoing?search=&workflow_status=&letter_from=&letter_to=&per_page=&page=` | `letters.view` | Daftar; default semua status; cari `official_number`/`recipient`/`sender`/`subject`; urut `letter_date` desc lalu `id` desc |
| GET | `/api/letters/outgoing/{letter}` | `letters.view` | Detail + lampiran + event + approvals + disposisi (null/kosong); tanpa path/checksum |
| POST | `/api/letters/outgoing` | `letters.manage` | Buat draft; field D2/D3; tanpa nomor |
| PUT/PATCH | `/api/letters/outgoing/{letter}` | `letters.manage` | Edit draft + `expected_version`; selain draft `409` |
| POST | `/api/letters/outgoing/{letter}/submit` | `letters.manage` | draft→review + `expected_version` |
| POST | `/api/letters/outgoing/{letter}/approve` | `letters.approve` | review→approved + `expected_version`; catatan ≤500; self `403` |
| POST | `/api/letters/outgoing/{letter}/reject` | `letters.approve` | review→draft + `expected_version`; alasan 10–255; self `403` |
| POST | `/api/letters/outgoing/{letter}/issue` | `letters.sign` | approved→issued; nomor atomik; `expected_version`; self `403` |
| POST | `/api/letters/outgoing/{letter}/cancel` | `letters.view` + dinamis (`letters.manage` untuk draft/review; `letters.approve` untuk approved/issued) | `reason` 10–255 + `expected_version`; cancelled; nomor dipertahankan bila ada |
| POST | `/api/letters/outgoing/{letter}/attachments` | `letters.manage` | Multipart `file`; hanya draft; aturan 07a |
| GET | `/api/letters/outgoing/{letter}/attachments/{attachment}/download` | `letters.view` | Stream privat; header keamanan 07a; lampiran surat lain `404` |
| DELETE | `/api/letters/outgoing/{letter}/attachments/{attachment}` | `letters.manage` | Soft delete + event; hanya draft; file disimpan |

- Semua route memakai `auth:sanctum`, `active`, `whereNumber` untuk id; controller memanggil `authorize` sebagai pertahanan berlapis.
- Respons `StatusResource`; create/transisi/upload/remove **`200`**.
- `409` memuat `message`, `errors` per field, dan `current_versions`:
  - Surat: `{letter_version, status, workflow_status, official_number}`.
  - Disposisi: `{disposition_version, status}`.
- `403` untuk ability/self-approval; `404` untuk objek lintas direction/letter; `422` validasi.
- Tidak ada endpoint delete surat, un-cancel, edit nomor, atau approve/sign massal.

### 5.7 Bentuk respons

Item outgoing memuat: `id`, `reference` (`#` + id 6 digit), `direction`, `recipient`, `sender`, `subject`, `category`, `received_date`, `letter_date`, `notes`, `status`, `workflow_status`, `lifecycle_status`, `official_number`, `approved_at`/`approved_by`, `issued_at`/`issued_by`, `attachments_count`, `version`, `created_by`, `created_at`/`updated_at`, serta `voided_at`/`void_reason`/`voided_by` untuk cancelled.

Detail menambahkan:
- `attachments` (id, nama asli, MIME, ukuran, waktu; tanpa path/checksum).
- `events` terbaru maks. 100 (event, actor, metadata, waktu).
- `approvals` (id, action, actor, notes, created_at).
- `dispositions` (untuk outgoing kosong/null).

Disposisi item: `id`, `letter` (`id`, `reference`, `subject`, `received_date`), `recipient` (`id`, `name`, `employee_type`), `due_date`, `status`, `overdue`, `note`, `follow_up_note`, `completed_at`/`completed_by`, `cancelled_at`/`cancelled_by`/`cancel_reason`, `version`, `created_at`/`updated_at`.

- `notes`, `follow_up_note`, dan `cancel_reason` hanya di detail/list berizin `letters.view`; tidak masuk audit/event.
- Tidak ada `path`, `checksum`, atau URL storage.

### 5.8 Matriks akses

| Operasi | Ability |
|---|---|
| Baca/list/detail/options/download incoming & outgoing & disposisi | `letters.view` |
| Create/edit/submit/cancel draft-review outgoing | `letters.manage` |
| Upload/hapus lampiran outgoing draft | `letters.manage` |
| Approve/reject outgoing | `letters.approve` |
| Issue/sign outgoing | `letters.sign` |
| Cancel approved/issued outgoing | `letters.approve` |
| Create/update/cancel disposisi | `letters.manage` |
| Opsi penerima disposisi | `letters.manage` + `employees.view` |
| Void incoming dengan disposisi terbuka | Ditolak `409` untuk semua |

- Tidak ada ability baru atau role migration. Verifikasi preflight bahwa baris ability/role ada; jika hilang, perbaiki data secara additive tanpa mengubah pemetaan lain.
- Admin/Operator default tidak dapat approve/sign; Kepala Sekolah default tidak dapat create/edit (tanpa `letters.manage`). Jika pemilik ingin kepala sekolah membuat draft, berikan `letters.manage` lewat UI peran (bukan perubahan kode).
- UI menyembunyikan tombol berdasarkan ability, tetapi server tetap `403`.

### 5.9 Audit dan history

- **`Letter` allowlist** (ditambah): `official_number`, `workflow_status`, `approved_at`, `approved_by`, `issued_at`, `issued_by`; tetap **tanpa** `recipient`, `sender`, `subject`, `notes`, `void_reason`.
- **`LetterDisposition` allowlist**: `letter_id`, `recipient_employee_id`, `due_date`, `status`, `version`, `completed_at`, `completed_by`, `cancelled_at`, `cancelled_by`; tanpa note/follow_up/cancel reason.
- **`LetterApproval`**: tidak memakai `Loggable`; immutable; notes hanya dapat dibaca lewat detail surat berizin.
- **`letter_events`**:
  - `submitted` `{from,draft→review}`;
  - `approved` `{approval_id}`;
  - `rejected` `{approval_id}`;
  - `issued` `{official_number, period, sequence}`;
  - `cancelled` `{from_status}`;
  - `disposition_created` `{disposition_id, recipient_employee_id, due_date}`;
  - `disposition_updated` `{disposition_id, changed_fields}`;
  - `disposition_completed` `{disposition_id}`;
  - `disposition_cancelled` `{disposition_id}`.
- Event lama 07a (`created`, `updated`, `voided`, `attachment_added`, `attachment_removed`) tetap; `EVENTS` diperluas.
- `letter_approvals` dan `letter_events` tidak memiliki endpoint edit/hapus; kegagalan insert membatalkan transaksi.
- Audit `activities` tetap dibuat server; tidak ada nama penerima, isi catatan, atau teks alasan.

### 5.10 UI (English)

**Incoming Letters (perluasan):**
1. Detail dialog menambahkan panel **Dispositions**: daftar penerima, due date, status, overdue, note/follow-up; tombol **Add Disposition** (`letters.manage`), **Update**, **Cancel**.
2. Tabel incoming menampilkan `Open dispositions` dan `Overdue` (count); filter tetap seperti 07a.
3. Create disposition dialog: multi-select penerima (search), due date, note; validasi inline; `409`/`422` jelas.
4. Update dialog: status (`Pending`, `In Progress`, `Completed`, `Cancelled`), due date, note/follow-up; completed wajib follow-up note; cancelled wajib reason.
5. View-only: panel read-only; tanpa tombol tulis.

**Dispositions (halaman baru `/public/letters/dispositions`, route `letterDispositions`):**
1. Header **Dispositions**, deskripsi worklist.
2. Filter: status (Open default = pending+in_progress, Pending, In Progress, Completed, Cancelled, All), overdue toggle, recipient search, letter search, page size.
3. Tabel: Letter (`#000123`), Subject, Recipient, Due date, Status, Overdue, Updated, Actions (**Open letter**, **Update**/`letters.manage`).
4. Empty state; 390 px; keyboard.

**Outgoing Letters (halaman baru `/public/letters/outgoing`, route `outgoingLetters`):**
1. Header **Outgoing Letters**, tombol **New Letter** (`letters.manage`).
2. Filter: search, workflow status (All default), letter date range, page size.
3. Tabel: Reference, Official No., Recipient, Subject, Letter date, Status, Attachments, Updated, Actions.
4. Create/Edit dialog (draft only): Sender (prefilled school), Recipient, Subject, Category, Letter date, Recorded date, Notes; validasi.
5. Detail dialog: metadata, official number (jika issued), approvals, attachments (download; upload/delete hanya draft), events, disposition section kosong.
6. Actions by state/ability: **Submit** (draft, manage), **Approve**/**Reject** (review, approve), **Issue** (approved, sign), **Cancel** (draft/review manage; approved/issued approve), **Edit** (draft, manage). Tombol self-approval/issue disembunyikan atau disabled dengan penjelasan; server `403`.
7. `409` panel **Reload Latest** menampilkan versi/status/nomor terbaru.
8. English, 390 px, keyboard, aria label, tanpa tombol dummy.

**Menu Letters:** **Incoming Letters**, **Outgoing Letters**, **Dispositions**; masing-masing `requires: 'letters.view'`; route guard `letters.view`.

### 5.11 Konfigurasi `config/letters.php`

Tambahan yang diusulkan (tanpa perubahan `.env` utama):

```php
'outgoing_workflow_statuses' => ['draft', 'review', 'approved', 'issued', 'cancelled'],
'outgoing_default_status' => 'draft',
'outgoing_search_max_length' => 100,
'disposition_statuses' => ['pending', 'in_progress', 'completed', 'cancelled'],
'disposition_open_statuses' => ['pending', 'in_progress'],
'disposition_note_max_length' => 2000,
'disposition_follow_up_min_length' => 10,
'disposition_cancel_reason_min_length' => 10,
'numbering' => [
    'scope' => 'outgoing',
    'period' => 'calendar_year',
    'format' => '{seq:03d}/{school_code}/{year}',
    'school_code' => env('LETTERS_NUMBER_SCHOOL_CODE'), // null → NPSN sekolah aktif
],
```

- Format final mengikuti D4; jangan meng-hard-code format di service.
- Validasi format: token whitelist, wajib `{seq}` dan `{year}`, maks. 100 karakter, hasil contoh tidak kosong.
- Tidak ada perubahan `.env`; nilai default cukup untuk UAT. `LETTERS_NUMBER_SCHOOL_CODE` opsional; jika null, pakai NPSN profil sekolah aktif.

### 5.12 Transaksi, lock, dan rollback

1. FormRequest memvalidasi field, `expected_version`, ability, dan aturan tanggal/status awal.
2. `DB::transaction`:
   - Lock baris `letters` target (`lockForUpdate`); validasi `direction`, `workflow_status`, `status`, `version`.
   - Terapkan perubahan, `version+1`, insert event/approval.
   - Issue: lock/upsert counter, increment, set nomor; unique index sebagai pertahanan.
   - Disposisi: lock surat lalu disposisi; validasi transisi; insert/update + event.
3. Upload lampiran outgoing: tulis file sebelum transaksi (pola 07a); jika transaksi gagal, hapus file best-effort; jika file gagal, tidak ada baris.
4. Semua kegagalan event/approval/counter membatalkan transaksi; tidak ada status/nomor/event tertinggal.
5. Tanpa token idempotensi terpisah: `expected_version` + status terminal mencegah replay nomor ganda. Request ulang setelah issue mendapat `409` dengan nomor yang sudah ada.
6. Konkurensi nyata multi-proses dan MariaDB **NOT RUN** sampai dijalankan; SQLite mengandalkan serialisasi writer + unique index.

### 5.13 Backup dan file

- Lampiran outgoing memakai path yang sama (`storage/app/attachments/letters/...`) dan mekanisme backup yang ada; tidak ada perubahan `BackupService`.
- Surat cancelled/void tetap menyimpan lampiran; file tidak dihapus sampai DEC-13.
- Restore drill dengan lampiran 07b tetap **NOT RUN** sampai dijalankan.

---

## 6. Matriks tes

Backend (Pest, SQLite in-memory; MariaDB tetap NOT RUN):

| # | Skenario | Ekspektasi |
|---|---|---|
| T1 | Guest/principal/operator/admin/wakil mengakses endpoint outgoing & disposisi | Guest `401`; principal approve/sign sesuai ability, create/edit `403`; operator/admin draft/edit, approve/sign `403`; wakil baca saja |
| T2 | Create outgoing draft valid | Baris `direction=outgoing`, `workflow_status=draft`, `status=active`, `version=1`, tanpa `official_number`; event `created`; audit `Created` |
| T3 | Validasi create outgoing: field wajib, kategori invalid, panjang, tanggal masa depan, `letter_date > received_date`, recipient kosong | `422`; nol baris |
| T4 | Edit draft valid | Field berubah, `version+1`, event `updated`, decoy tidak berubah |
| T5 | Edit/upload/hapus lampiran di review/approved/issued/cancelled | `409`; nol mutasi; file tidak berubah |
| T6 | Submit draft → review | `workflow_status=review`, `version+1`, event `submitted`; edit setelah submit `409` |
| T7 | Approve oleh `letters.approve` yang bukan pembuat | `approved`, `approved_at/by`, versi+1, event+approval ledger; surat lain tidak berubah |
| T8 | Approve oleh pembuat sendiri / tanpa ability | `403`; nol mutasi |
| T9 | Reject dengan alasan valid | Kembali `draft`, `version+1`, event+approval ledger; edit terbuka |
| T10 | Reject tanpa alasan/<10/versi basi | `422`/`409`; nol mutasi |
| T11 | Issue oleh `letters.sign` yang bukan pembuat | `issued`, `official_number`, `issued_at/by`, versi+1, event `issued`, counter+1 |
| T12 | Issue oleh pembuat sendiri/tanpa sign/status belum approved | `403`/`409`; tidak ada nomor baru |
| T13 | Dua issue paralel/replay dengan `expected_version` sama | Satu nomor; permintaan kedua `409` dengan `official_number` yang sama; counter hanya +1 |
| T14 | Nomor berurutan dan unik dalam periode | `001`, `002`, ...; unique index menolak duplikat; format sesuai config |
| T15 | Reset periode/tahun dan format | Counter periode baru mulai 1; format lama tidak dipakai ulang; format invalid ditolak |
| T16 | Cancel draft/review oleh manage | `cancelled`, `status=void`, alasan, versi+1, event `cancelled`; tanpa nomor |
| T17 | Cancel approved/issued oleh approve | `cancelled`; nomor tetap; counter tidak mundur; cancel oleh manage `403` |
| T18 | Edit/issue/cancel pada cancelled | `409`; read-only; nol mutasi |
| T19 | Create disposisi multi-penerima | Satu baris per penerima, status `pending`, due date, event per baris; versi 1 |
| T20 | Duplikasi penerima aktif / penerima invalid / surat void | `422`/`409`; nol baris; pesan field jelas |
| T21 | Update disposisi: pending→in_progress→completed | Transisi valid; completed wajib follow-up note; versi+1; event; overdue berubah |
| T22 | Transisi disposisi tidak sah / versi basi / completed cancel | `422`/`409`; nol mutasi |
| T23 | Void incoming dengan disposisi pending/in_progress | `409`; nol mutasi; disposisi tetap; setelah completed/cancelled void berhasil |
| T24 | List/filter disposisi dan outgoing | Filter status/overdue/penerima/tanggal/search, pagination/meta benar; wildcard `%`/`_` di-escape |
| T25 | Audit/history privacy | `activities`/event tidak memuat notes/subject/recipient name/follow-up/cancel reason; approval ledger dan event immutable; aktor/waktu benar |
| T26 | Rollback dan regresi | Kegagalan event/approval/counter/upload → rollback penuh; suite penuh PASS; 07a tidak regresi |

Frontend/UX: browser checks untuk outgoing draft/edit/submit/approve/reject/issue/cancel, disposisi create/update/cancel, view-only, self-approval block, `409` Reload Latest, 390 px, English, aria/keyboard, dan 0 console error. Jika browser tidak tersedia, tulis **NOT RUN** dan sediakan checklist manual; jangan mengklaim PASS tanpa bukti.

---

## 7. UAT

- Lingkungan: **`.uat-phase07`** (SQLite), API `127.0.0.1:18025`, SPA `127.0.0.1:14025`, cookie `school_phase07_session`, launcher `laravel-api/scripts/phase07-uat.php`, storage `.uat-phase07/storage`.
- Perintah baru (idempotent, tanpa reset):
  - `prepare-outgoing` — ≥40 surat keluar sintetis:
    - 10 draft, 8 review, 8 approved, 10 issued dengan nomor unik berurutan, 4 cancelled (2 sebelum issue, 2 setelah issue).
    - Nomor contoh mengikuti format D4; counter tahun berjalan disiapkan.
    - Lampiran PDF/JPG/PNG pada beberapa draft/review/issued.
  - `prepare-dispositions` — ≥30 disposisi pada surat masuk 07a:
    - 10 pending, 8 in_progress, 8 completed, 4 cancelled; sebagian overdue.
    - Satu surat dengan beberapa penerima; satu surat aktif dengan disposisi terbuka (untuk uji void `409`); satu surat void dengan disposisi completed (history read-only).
    - ≥10 pegawai sintetis `UAT-07B` (aktif, sebagian tertaut akun UAT); tidak menyentuh pegawai operasional.
  - `status` — menampilkan jumlah surat outgoing/disposisi/pegawai fixture.
- Akun: `uat.operator` dan `uat.admin` (`manage`), `uat.kepsek` (`view`/`approve`/`sign`), `uat.waka` (`view`). Password hanya di runtime privat `.uat-phase07/account-password.txt`; tidak dicetak di dokumen.
- Checklist manual (ditulis lengkap di `PHASE-07B-MANUAL-REVIEW.md` setelah implementasi):
  1. Isolasi: API/SPA hanya 18025/14025; DB/storage `.uat-phase07`; tidak menyentuh `.uat-phase05/06`/operasional.
  2. Login dan izin: operator/admin dapat draft/edit/submit/cancel draft; tidak dapat approve/sign; kepsek dapat approve/reject/issue; wakil baca saja; server `403` untuk pelanggaran.
  3. Buat draft outgoing; validasi field/tanggal; nomor belum ada.
  4. Edit draft + upload lampiran; submit; edit/upload setelah submit ditolak `409`.
  5. Approve oleh kepsek; reject dengan alasan; reject membuka edit; approve ulang.
  6. Issue: nomor muncul, format sesuai; issue oleh pembuat sendiri/operator ditolak.
  7. Replay/dua tab issue: satu nomor, permintaan kedua `409` dengan nomor yang sama.
  8. Cancel draft/review oleh operator; cancel approved/issued oleh kepsek; nomor tetap; cancel ulang `409`.
  9. Disposisi: create multi-penerima, update status, completed wajib follow-up, cancel wajib alasan; overdue tampil.
  10. Void surat masuk dengan disposisi terbuka `409`; setelah completed/cancelled void berhasil.
  11. Worklist Dispositions: filter status/overdue/penerima/pencarian; 390 px.
  12. History/approval: event dan approval ledger aktor/waktu; tidak ada catatan/nama penerima di audit.
  13. UI English, 390 px tanpa overflow, keyboard/aria, 0 console error.
  14. Setelah pemilik menyatakan penerimaan, hentikan server 14025/18025 dengan verifikasi PID/command line; pertahankan `.uat-phase07`; jangan sentuh Apache/MariaDB (DEC-27).
- Server dinyalakan untuk review dan **dihentikan setelah penerimaan**; perintah restart dicatat.

---

## 8. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Format nomor/pejabat penandatangan ditebak | D4–D5 wajib disetujui; format divalidasi; signatory berbasis ability + aktor tercatat; tidak hard-code `principal_id` |
| Nomor ganda saat dua issue paralel | Lock baris + counter row lock + unique `official_number`; replay `409`; uji T13; multi-proses/MariaDB NOT RUN |
| Status melompat (draft→issued) | Matriks transisi + validasi server di dalam transaksi; tes T6/T11/T12 |
| Approver/signatory tanpa mandat | Route `can:` + `authorize` + self-check; tes T7/T8/T11/T12 |
| Pembuat approve diri sendiri | `403` actor = `created_by`; UI menyembunyikan tombol; tes T8 |
| Lampiran berubah setelah approval | Hanya draft yang dapat edit/upload; submit membekukan; reject membuka; tes T5 |
| Disposisi ganda untuk penerima sama | Lock surat + cek duplikasi aktif; `422`; nol baris |
| Void surat meninggalkan disposisi terbuka | Void ditolak `409` sampai disposisi completed/cancelled; tes T23 |
| Penerima pegawai diarsipkan | Opsi hanya pegawai aktif; history tetap; update disposisi lama tetap boleh completed |
| PII di audit/event | Allowlist; event metadata id/status/nomor; tanpa note/nama penerima; tes T25 |
| `official_number` bentrok karena format salah | Validasi config + unique index + rollback transaksi; format wajib `{seq}`/`{year}` |
| SQLite vs MariaDB/InnoDB | Tes SQLite; MariaDB dan multi-proses NOT RUN; catat risiko |
| UAT fixture menggandakan data 07a | Perintah idempotent dengan marker; tanpa reset; verifikasi `status` |
| DEC-11/07c terbawa ke 07b | PDF/template/snapshot/kop/tanda tangan/stempel eksplisit non-goal; tidak ada dependency baru |
| Retensi lampiran | DEC-13 OPEN; file soft-deleted disimpan; catat pertumbuhan storage |
| Principal tidak bisa membuat draft | Default role principal hanya view/approve/sign; jika perlu, berikan `letters.manage` lewat UI peran (bukan kode) |

---

## 9. Non-goals dan batas otorisasi

- Implementasi 07b diotorisasi oleh **DEC-33 APPROVED 2026-11-01 WIB** ("Saya setuju D1-D11"); **DEC-06 CLOSED** melalui D4–D5. Otorisasi ini tidak mencakup 07c, merge/deploy, migrasi DB operasional/MariaDB, atau perubahan dependency/service/`.env`.
- Tidak ada 07c: template PDF, kop surat, tanda tangan gambar, stempel, snapshot biodata/penandatangan, render PDF, atau dependency PDF.
- Tidak ada OCR, e-sign provider, email/WhatsApp otomatis, notifikasi, delegasi approval, multi-level approval, atau akun otomatis untuk penerima disposisi.
- Tidak ada nomor manual, edit nomor setelah terbit, pemakaian ulang nomor, atau reset counter manual lewat UI.
- Tidak ada arsip terpusat/versioning Fase 09, retensi/pemusnahan DEC-13, antivirus, full-text search, ekspor, atau Dapodik.
- Tidak ada merge/deploy/force-push/rewrite, migrasi database operasional/MariaDB, perubahan `.env` utama, dependency/lockfile, service, atau akses DB terlarang.
- Tidak ada perubahan pada `.uat-phase05`/`.uat-phase06` atau data operasional; UAT hanya `.uat-phase07`.
- Commit+push paket review mengikuti aturan repo; push bukan penerimaan pemilik. Setelah 07b siap review, berhenti; 07c memerlukan instruksi/rencana terpisah.

---

## 10. Ringkasan keputusan untuk pemilik

| Kode | Ringkasan |
|---|---|
| D1 | Otomasi hanya 07b; 07c/PDF/template tetap terpisah; DEC-11/13 OPEN |
| D2 | Reuse `letters`; kolom additive `recipient`/`official_number`/`workflow_status`/approval/issue; tiga tabel baru; tanpa ubah kolom lama |
| D3 | draft→review→approved→issued; cancel; edit/lampiran hanya draft; reject kembali draft; tanpa lompat status |
| D4 | Format `{seq:03d}/{school_code}/{year}` (dapat diganti pemilik); counter global tahunan; nomor saat issue; unik/atomik/tidak dipakai ulang |
| D5 | Approve/reject `letters.approve`; issue/sign `letters.sign`; pembuat tidak boleh approve/sign sendiri; admin teknis tidak otomatis |
| D6 | Disposisi incoming untuk pegawai; multi-penerima; pending/in_progress/completed/cancelled; due date/overdue; follow-up; open-block void |
| D7 | Lampiran 07a dipakai ulang; outgoing hanya draft; beku saat submit; file disimpan sampai DEC-13 |
| D8 | Tanpa ability baru; route matrix; object-level; server `403`/`404`; UI hanya menyembunyikan |
| D9 | 6 endpoint disposisi + 13 endpoint outgoing; halaman Incoming/Dispositions/Outgoing; English/390/409 reload |
| D10 | Event/approval ledger immutable; audit allowlist tanpa catatan/nama penerima; metadata minimal |
| D11 | UAT `.uat-phase07` + fixture additive; server dihentikan setelah penerimaan; non-goals 07c/merge/deploy/dependency |

Pemilik menyetujui seluruhnya pada 2026-11-01 WIB (**"Saya setuju D1-D11"**); keputusan dicatat sebagai **DEC-33 APPROVED** dan **DEC-06 ditutup** melalui D4–D5. Implementasi 07b dikerjakan sesuai D1–D11; bukti verifikasi dicatat di `PHASE-07B-REPORT.md` setelah selesai.

---

## 11. Prompt setelah subfase ini disetujui

```text
Baca AGENTS.md, ROADMAP, HANDOFF, PHASE-07-LETTERS.md dan PHASE-07B-PLAN.md.
Kerjakan subfase 07b saja: disposisi surat masuk, surat keluar, penomoran
atomik, approval/sign, dan UI terkait sesuai PHASE-07B-PLAN.md (DEC-33).
Jangan menebak format nomor/pejabat penandatangan di luar keputusan pemilik.
Audit HEAD dan consumer/kontrak aktual sebelum edit; jangan mengubah kolom
lama secara destruktif. Gunakan fixture/DB/storage terisolasi; jalankan
verifikasi relevan dan catat hasil aktual. Jika browser tidak tersedia,
jelaskan dan berikan checklist manual. Sinkronkan kontrak affected,
CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP. Berhenti setelah hasil
subfase siap review; jangan lanjut 07c atau merge/deploy.
```

---

## 12. Syarat keluar 07b

- [x] D1–D11 disetujui dan dicatat sebagai **DEC-33 APPROVED 2026-11-01 WIB**; **DEC-06 ditutup** dengan format/reset/issue timing/signatory eksplisit (D4–D5).
- [ ] Scope 07b selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan di DATABASE/API-CONTRACTS/ACCESS-CONTROL/OPERATIONS/REQUIREMENTS.
- [ ] Tes relevan benar-benar dijalankan atau NOT RUN dengan alasan; hasil aktual dicatat (termasuk jumlah tes/assertions).
- [ ] UAT `.uat-phase07` dan checklist manual tersedia; persetujuan pemilik dicatat terpisah.
- [ ] SHA/working tree, known issues, dan langkah berikutnya dicatat di INDEX/HANDOFF/ROADMAP.
- [ ] Server UAT dihentikan setelah penerimaan (DEC-27); data dipertahankan.
- [ ] 07c, merge/deploy, migrasi DB operasional/MariaDB, dan perubahan dependency/service/`.env` tidak dijalankan.
