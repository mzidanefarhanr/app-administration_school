# Rencana Fase 07 — Subfase 07c (Kop, Tanda Tangan, Stempel, Template PDF, dan Snapshot)

- Tanggal: 2026-11-02 (WIB). Branch: `main`. Baseline dokumen/audit: `ff581c6` (paket penerimaan 07b). Paket rencana: `42108dc`.
- Status dokumen: **PLAN APPROVED — DEC-34 APPROVED 2026-11-02 WIB** berdasarkan pernyataan pemilik **"Saya setuju D1-D11"**. Implementasi **07c diotorisasi**; bukti implementasi dicatat pada `PHASE-07C-REPORT.md` setelah verifikasi. **DEC-11 CLOSED untuk lingkup 07c** (D9); **DEC-13 tetap OPEN**.
- Cakupan: **07c saja** — pengaturan kop sekolah, logo, penandatangan, gambar tanda tangan, stempel, registry template kode, tiga jenis surat terpilih, render PDF server-side, snapshot immutable saat terbit, serta preview/download berizin.
- Di luar cakupan: **07d/Fase 08** (surat izin), template CRUD/editor, DOCX, PDF/A, e-sign tersertifikasi, QR/barcode, OCR, email/WhatsApp otomatis, arsip/versioning Fase 09, retensi DEC-13, rekap sekolah (DEC-12), merge/deploy, migrasi database operasional/MariaDB, perubahan service/`.env`, dan dependency selain yang diputuskan di D2.
- Dokumen acuan: `roadmap/phases/PHASE-07-LETTERS.md` (07c), `roadmap/ROADMAP.md`, `docs/DECISIONS.md` (DEC-07, DEC-11, DEC-12, DEC-13, DEC-27, DEC-32, DEC-33), `docs/REQUIREMENTS.md` (FR-08/FR-09, PDF dan snapshot), `docs/DATABASE.md`, `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `reports/feature-update/PHASE-07A-REPORT.md`, `reports/feature-update/PHASE-07B-REPORT.md`, `reports/feature-update/PHASE-07B-MANUAL-REVIEW.md`.
- Gate: 07a **OWNER APPROVED 2026-11-01 WIB (#1–#16)**; 07b **IMPLEMENTED + agent-verified + OWNER APPROVED 2026-11-02 WIB (#1–#16)**. **DEC-06 CLOSED**. **DEC-11 CLOSED untuk lingkup 07c** melalui D9/DEC-34 (2026-11-02 WIB); **DEC-13 tetap OPEN**.

---

## 1. Posisi dan prasyarat

| Prasyarat | Kondisi aktual 2026-11-02 | Status |
|---|---|---|
| Subfase 07a | Surat masuk, lampiran privat, edit/void berversi, history immutable, UI Incoming Letters; owner approved #1–#16; UAT 14025/18025 dihentikan (DEC-27); data `.uat-phase07` dipertahankan | Terpenuhi |
| Subfase 07b | Surat keluar `draft → review → approved → issued/cancelled`, nomor atomik `{seq:03d}/{school_code}/{year}`, approval/sign terpisah, disposisi, guard void; owner approved #1–#16; 28 route surat; 522 tes suite | Terpenuhi |
| Ability surat | `letters.view`, `letters.manage`, `letters.approve`, `letters.sign` sudah ada; admin/operator `view`/`manage`; kepala sekolah `view`/`approve`/`sign`; wakil kesiswaan `view`; admin/operator tidak otomatis sign | Terpenuhi; tidak perlu ability baru |
| Surat keluar | `letters` memiliki `recipient`, `official_number` unik, `workflow_status`, `approved_*`, `issued_*`; `issue()` sudah menjadi titik transisi `approved → issued` di dalam transaksi | Hook 07c tersedia |
| Kop/tanda tangan/stempel | **Tidak ada** kolom kop, logo, tanda tangan, stempel, atau format template di `school_profiles`, `letters`, atau tabel lain | Perlu tabel/kolom additive |
| Penandatangan | `school_profiles.principal_id` menunjuk akun; identitas yang tercetak belum dapat dikonfigurasi terpisah dari akun | Perlu `letter_settings` |
| PDF | **Tidak ada library PDF** di `composer.json`/`composer.lock`; tidak ada endpoint preview/download PDF | Gate D2 |
| Template | Belum ada view/template surat; `resources/views` belum memiliki template surat | Perlu registry kode + Blade |
| Data master | `students`, `student_rombels`, `student_mutations`, `employees`, `school_years`, `academic_settings` tersedia; `school_profiles` unik per tahun ajaran | Dapat dipakai auto-fill |
| UAT | `.uat-phase07` berisi 07a/07b (126 incoming, 46 outgoing, 32 disposisi, 3 siswa, 14 pegawai, 1 tahun, 1 rombel, 0 profil sekolah, 0 education level, 0 semester); server berhenti | Dipakai ulang dengan fixture additive |
| Keputusan | **DEC-11 CLOSED untuk lingkup 07c** melalui D9/DEC-34 (2026-11-02 WIB); **DEC-13 OPEN**; DEC-07 (izin) OPEN dan tidak mengikat 07c | D1–D11 disetujui; rekap tetap DEC-12 |

Seluruh kondisi di atas diverifikasi dari source, migration, konfigurasi, dan dokumen aktual, bukan dari data operasional.

---

## 2. Temuan audit (fakta kode)

### 2.1 Belum ada jalur PDF atau template

- `composer.json` hanya memuat `php`, `guzzlehttp/guzzle`, `laravel/framework`, `laravel/sanctum`, dan `laravel/tinker`: `laravel-api/composer.json:8-14`. Tidak ada `dompdf`, `mpdf`, `tcpdf`, atau library PDF lain.
- Tidak ada route/controller/service/view PDF: pencarian `letter_documents|letter_settings|dompdf|Dompdf|PDF` hanya menemukan validasi lampiran PDF di `StoreLetterAttachmentRequest.php:39` dan `LetterAttachmentService.php:44`.
- `resources/views` belum memiliki subdirektori template surat.
- `OutgoingLetters.vue` belum memiliki aksi preview/download PDF; capability surat hanya `canManage`, `canApproveLetter`, `canIssueLetter`, `canCancel`: `vue-app/src/views/pages/public/OutgoingLetters.vue:11-13`.

**Implikasi:** 07c menambah dependency, tabel, service, template, endpoint, dan UI baru. Tidak ada consumer PDF lama yang perlu dipertahankan.

### 2.2 Identitas sekolah dan penandatangan belum lengkap

- `school_profiles` menyimpan `education_school_npsn`, `principal_id`, `school_year_id` (unik), `status_principal_id`, `nds`, `nss`, `nis`, `nrks`, `tmt_principal`, `official_number`, `email`, `website`, dan data komite; **tidak ada** kop, logo, tanda tangan, stempel, atau template: `laravel-api/database/migrations/2026_10_15_150625_create_school_profiles_table.php:14-37`.
- `SchoolProfile` hanya memuat field tersebut dan relasi `educationSchool`, `principal`, `schoolYear`, `statusPrincipal`: `laravel-api/app/Models/SchoolProfile.php:24-39`.
- `SchoolProfileController::show($id)` mengambil profil berdasarkan `school_year_id`, bukan profil aktif: `laravel-api/app/Http/Controllers/SchoolProfileController.php:51-58`.
- `academic_settings` menyimpan `active_school_year_id`/`active_semester_id`; belum ada resolver profil sekolah aktif untuk surat: `laravel-api/app/Models/AcademicSetting.php:26-44`.

**Implikasi:** buat `letter_settings` 1:1 dengan `school_profiles`, resolusi melalui tahun ajaran aktif. Jangan menambah kolom ke `school_profiles`; jangan mengubah CRUD profil yang ada.

### 2.3 Titik issue adalah hook generation yang tepat

- `OutgoingLetterService::issue()` mengunci surat, memeriksa `workflow_status='approved'`, versi, dan larangan self-issue, lalu memanggil `LetterNumberService::next()` dan menyimpan nomor/`issued_at`/`issued_by`/status: `laravel-api/app/Services/Letters/OutgoingLetterService.php:312-354`.
- Nomor dibentuk dari counter atomik; `school_code` default NPSN profil pertama jika config kosong: `laravel-api/app/Services/Letters/LetterNumberService.php:54-...`, `:175-...`.
- `defaultSender()` memakai **profil pertama** (`orderBy('id')`), bukan profil tahun ajaran aktif: `OutgoingLetterService.php:604-614`. Ini gap yang perlu diperbaiki di 07c agar default pengirim konsisten dengan kop.
- `serializeDetail()` sudah memuat lampiran, event, approval, dan disposisi; dapat diperluas dengan ringkasan dokumen: `OutgoingLetterService.php:619-...`.

**Implikasi:** render PDF dan snapshot dilakukan di dalam transaksi `issue()` setelah nomor dibuat, agar nomor, status, dokumen, dan file konsisten. Kegagalan render harus me-rollback counter dan status.

### 2.4 Model surat dan event dapat diperluas additive

- `Letter` memiliki allowlist audit, fillable outgoing, dan konstanta workflow; belum ada `letter_type`, subject dokumen, atau relasi dokumen: `laravel-api/app/Models/Letter.php`.
- `letter_events` immutable/append-only; event `issued` saat ini memuat `official_number`, `period`, `sequence`: migration `2029_08_02_000003_create_letter_events_table.php:15-25`, model menolak update/delete `LetterEvent.php:64-77`.
- `letter_attachments` dan `LetterAttachmentService` sudah menangani file privat, MIME server, UUID, sha256, dan soft delete: `LetterAttachmentService.php:24-189`.
- `LetterNumberCounter` unik per `(scope, period)`: migration `2029_08_02_000007_create_letter_number_counters_table.php`.

**Implikasi:** 07c menambah kolom nullable pada `letters`, tabel `letter_documents`, dan service baru; tidak mengubah tabel/event/lampiran 07a/07b secara destruktif.

### 2.5 Data auto-fill tersedia, tetapi harus diminimalkan

- `students` memiliki nama, NIK, tempat/tanggal lahir, gender, NIS, NISN, alamat, orang tua; NIK/NIS/NISN unik nullable: migration `create_students_table.php:14-...`.
- `student_rombels` menentukan keanggotaan aktif: tidak deleted/archived, `student_status_id` null/1, `latest_student_exit_date` null; `StudentRombel::booted()` menegakkan satu keanggotaan aktif per siswa/tahun: `laravel-api/app/Models/StudentRombel.php`.
- `student_mutations` menyimpan `type` (`in`/`out`), `school_year_id`, `student_id`, `student_rombel_id`, `school_rombel_id`, `effective_date`, `school_npsn`, `reason`, `status` (`active`/`cancelled`), `created_by`; immutable: `laravel-api/app/Models/StudentMutation.php`, migration `2029_08_01_000001_create_student_mutations_table.php:14-38`.
- `employees` memiliki `nuptk`, nama, status/tipe/employment, `exit_date`;pegawai aktif = tidak soft-deleted dan `exit_date` null: `laravel-api/app/Models/Employee.php`, migration `create_employees_table.php:14-...`.

**Implikasi:** snapshot hanya memuat field yang tercetak di surat; **tidak** memuat NIK siswa/orang tua, alamat, atau kontak pribadi. Penerima/penandatangan dipilih server-side dan divalidasi ulang saat issue.

### 2.6 Ability dan UI belum memiliki halaman pengaturan surat

- Definisi ability: `letters.view`, `letters.manage`, `letters.approve`, `letters.sign`; approve/sign sensitif: `laravel-api/app/Support/AbilityCatalog.php:53-56`.
- Pemetaan: admin/operator `view`+`manage`; kepala sekolah `view`+`approve`+`sign`; wakil kesiswaan hanya `view`: `AbilityCatalog.php:86-140`.
- Grup menu **Letters** hanya Incoming/Outgoing/Dispositions: `vue-app/src/layout/AppMenu.vue:51-56`; route surat memakai `letters.view`: `vue-app/src/router/index.js:79-94`.
- Belum ada halaman **Letter Settings** atau kontrol unggah logo/tanda tangan/stempel.

**Implikasi:** tambah satu halaman pengaturan dan perluas Outgoing Letters; server tetap menegakkan ability. Tidak ada ability/role migration baru.

### 2.7 DEC-11 masih menjadi gate dan belum ada keputusan konten

- `docs/DECISIONS.md` — **DEC-11 OPEN**: kop, tanda tangan, stempel, jenis surat pertama, dan format rekap yang benar-benar dipakai; fase 07a/07c.
- `docs/REQUIREMENTS.md:30` — FR-09: draft, nomor unik, persetujuan, PDF, versi template, dan snapshot data.
- `docs/REQUIREMENTS.md:45` — PDF terbit mempertahankan snapshot identitas/penandatangan; perubahan master tidak mengubah surat lama.
- `docs/DECISIONS.md` — **DEC-13 OPEN**: retensi data pribadi termasuk lampiran; tidak boleh dianggap selesai oleh 07c.

**Implikasi:** D1–D4 dan D9 wajib diputuskan pemilik sebelum implementasi. Rencana ini menyediakan rekomendasi default, termasuk usulan isi surat, tetapi tidak menganggapnya final tanpa persetujuan.

### 2.8 Backup mencakup lokasi file baru

- `config/backup.php:19` memakai default `storage_path('app/attachments')`; `BackupService.php:54` membaca sumber yang sama.
- Lampiran surat sudah berada di `storage/app/attachments/letters`; file settings/dokumen 07c akan berada di bawah `storage/app/attachments/letter-settings` dan `letter-documents`, sehingga tercakup backup tanpa perubahan `BackupService`.
- Restore drill dengan file 07c tetap **NOT RUN** sampai dijalankan.

**Implikasi:** tidak perlu perubahan backup code; perlu tes bahwa file baru berada di disk privat dan path yang tercakup, serta catatan restore drill.

### 2.9 Pola tes dan UAT 07a/07b dapat diperluas

- Tes surat memakai Pest + `RefreshDatabase`, `Storage::fake('local')`, waktu tetap Asia/Jakarta, dan empat peran: `laravel-api/tests/Feature/Letters/OutgoingLetterTest.php:1-45`.
- `phpunit.xml` memakai SQLite in-memory terisolasi (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`); MariaDB tetap NOT RUN.
- Launcher UAT: `laravel-api/scripts/phase07-uat.php` (`init`, `prepare`, `prepare-letters`, `prepare-outgoing`, `prepare-dispositions`, `status`); API `127.0.0.1:18025`, SPA `127.0.0.1:14025`, cookie `school_phase07_session`.
- `.uat-phase07` saat ini: 126 incoming, 46 outgoing, 32 disposisi, 3 siswa, 14 pegawai (12 aktif), 1 tahun ajaran, 1 rombel, 0 profil sekolah, 0 education level, 0 semester, 0 mutasi, 0 surat bertipe.

**Implikasi:** 07c menambah perintah idempotent `prepare-documents` dan fixture additive; tidak me-reset data 07a/07b.

---

## 3. Urutan pekerjaan yang diusulkan

| Langkah | Pekerjaan | Hasil yang dapat diverifikasi |
|---|---|---|
| S0 | Preflight: audit HEAD `ff581c6`, pastikan `.uat-phase07` ada, verifikasi PHP extension, ability sign, dan tidak ada route/tabel PDF; catat persetujuan D2 (dependency) | Baseline/isolation jelas; dependency belum diubah sebelum D2 disetujui |
| S1 | Migration additive: `letter_settings`, `letter_documents`, kolom `letter_type`/`document_subject_type`/`document_subject_id`/`document_fields` pada `letters`; model, cast, relasi, allowlist audit | Tabel/kolom ada; 07a/07b tetap berjalan; tidak ada perubahan kolom lama |
| S2 | `LetterSettingsService` + `LetterSettingsAssetService` + resolver profil aktif + FormRequest/controller/route settings; readiness; perbaikan `defaultSender()` | Settings per tahun, aset privat, readiness, `expected_version`, `409` |
| S3 | Registry template di `config/letters.php` + `LetterTemplateRegistry` + snapshot builder + `LetterPdfRenderer` (Dompdf) | Template tervalidasi; snapshot minimal PII; PDF A4/DejaVu; watermark preview |
| S4 | Integrasi typed letters: create/edit/submit/issue + subject pickers; generate PDF/snapshot di `issue()`; rollback dan cleanup file | Tiga jenis surat; PDF/nomor/dokumen atomik; kegagalan tidak mengonsumsi nomor |
| S5 | `LetterDocumentService` + endpoint download/preview + serialisasi dokumen (tanpa path/hash/snapshot) | Download issued/cancelled; preview watermarked; legacy/general `404` |
| S6 | UI: halaman Letter Settings, form typed Outgoing, subject pickers, document card, filter/type, menu/route | English, 390 px, ability-aware, `409` Reload Latest, tanpa tombol dummy |
| S7 | Tes backend T1–T28 + regresi 07a/07b + Pint + `route:list` + build/lint frontend | Matriks §6 PASS; tidak ada regresi |
| S8 | UAT `.uat-phase07`: `prepare-documents`, fixture 120 siswa/20 mutasi/20 surat bertipe, browser/API checklist, laporan | UAT siap review; bukti aktual tercatat |
| S9 | Sinkronisasi DATABASE/API-CONTRACTS/ACCESS-CONTROL/OPERATIONS/REQUIREMENTS/CHANGELOG/laporan/INDEX/HANDOFF/ROADMAP/fase; commit+push paket review | Paket review dapat dibaca di GitHub; server berjalan sampai penerimaan |

Setelah S9, berhenti untuk review pemilik. Tidak ada merge, deploy, atau lanjutan otomatis ke Fase 08.

---

## 4. Keputusan yang perlu pemilik (D1–D11)

Rekomendasi ditulis sebagai default. **D1–D11 APPROVED 2026-11-02 WIB** berdasarkan pernyataan pemilik **"Saya setuju D1-D11"** dan dicatat sebagai **DEC-34 APPROVED**; **DEC-11 CLOSED untuk lingkup 07c**; **DEC-13 tetap OPEN**. Implementasi 07c mengikuti keputusan ini tanpa menebak di luar D1–D11.

| # | Pertanyaan | Rekomendasi | Alternatif / dampak |
|---|---|---|---|
| **D1** | Subfase dan jenis surat mana yang diotorisasi? | **07c saja** dengan **tiga jenis**: `student_active` (**Student Active Certificate** / Surat Keterangan Aktif Siswa), `student_mutation` (**Student Mutation Certificate** / Surat Keterangan Mutasi Siswa, terhubung mutasi keluar aktif), dan `employee_duty` (**Employee Duty Letter** / Surat Tugas, pegawai aktif). Surat umum 07b (`letter_type=null`) tetap ada tanpa PDF. **Surat izin (`student_leave`) ditunda ke Fase 08/DEC-07.** Jika pemilik memilih empat jenis sekaligus, 07c dipecah menjadi 07c-1/07c-2 dengan rencana terpisah. | Menambah surat izin sekarang (mencampur domain izin Fase 08, butuh approval/rentang tanggal sendiri); template CRUD (di luar scope); semua jenis surat dalam satu subfase (risiko besar, sulit diuji). |
| **D2** | Boleh menambah dependency PDF? | **Ya, hanya `dompdf/dompdf:^2.0`** (pure PHP, tanpa binary sistem) beserta transitive dependency yang diwajibkan, ditambahkan ke `laravel-api/composer.json` dan `composer.lock`, lalu `composer install`. Tidak ada upgrade package lain; diff lockfile diperiksa agar hanya berisi dompdf + dependency barunya. PHP 8.2 operasional sudah memiliki `dom`, `mbstring`, `gd`, `fileinfo`, `xml`, `zip`, `openssl`, `curl`. Versi 2.x yang ter-resolve dicatat di laporan. Jika 2.x tidak dapat diselesaikan pada PHP 8.2, **berhenti dan lapor**, jangan diam-diam memilih major lain. | Tanpa dependency, 07c tidak dapat memenuhi PDF tersimpan/immutable; browser print bukan bukti tersimpan; binary sistem (`wkhtmltopdf`) dilarang; layanan e-sign/PDF eksternal di luar scope. Jika D2 ditolak, 07c ditunda. |
| **D3** | Di mana kop, logo, penandatangan, tanda tangan, dan stempel disimpan? | Tabel baru **`letter_settings` 1:1 dengan `school_profiles`** (unik `school_profile_id`, `version` untuk optimistic locking), sehingga settings mengikuti tahun ajaran aktif. Field: `government_line`, `school_name`, `address_line`, `contact_line`, `place_of_issue`, logo (path/mime/size/sha256), `signatory_name`, `signatory_title`, `signatory_identifier_label`, `signatory_identifier`, signature (path/mime/size/sha256), stamp (path/mime/size/sha256), `updated_by`. Aset privat di `storage/app/attachments/letter-settings/{school_profile_id}/`, hanya PNG/JPG, maks. 2 MB dan 2000×2000, di-re-encode ke PNG (alpha dipertahankan). Logo dan stempel opsional; **tanda tangan wajib** untuk readiness. Header/logo diedit `letters.manage`; penandatangan/tanda tangan/stempel diedit `letters.sign`. Resolusi: `academic_settings.active_school_year_id` → `school_profiles.school_year_id` → `letter_settings`. Jika belum lengkap, submit/issue surat bertipe `422` dengan daftar yang kurang; surat umum 07b tidak terpengaruh. | Menambah kolom ke `school_profiles` (mencampur profil akademik dan aset surat; sulit versioning); settings per sekolah global (tidak mendukung pergantian tahun/kepala sekolah); menyimpan gambar di `public` (tidak aman); meng-hard-code `principal_id` sebagai penandatangan (menghalangi delegasi dan akun pengganti). |
| **D4** | Bagaimana template dikelola? | **Template kode**, bukan CRUD: Blade views di `resources/views/letters/pdf/` (`student-active.blade.php`, `student-mutation.blade.php`, `employee-duty.blade.php`, partial kop/tanda tangan), registry di `config/letters.php` dengan `code`, `version`, `view`, dan label. Versi dinaikkan manual saat layout/isi berubah; `letter_documents` menyimpan `template_code` + `template_version`. Tidak ada editor HTML, upload template, atau HTML dari pengguna. Font **DejaVu Sans** (bawaan Dompdf), A4 portrait, remote resource dimatikan, gambar disisipkan sebagai data URI, semua nilai di-escape. | Template CRUD/database (menambah editor, sanitasi HTML, dan versioning yang jauh lebih besar); DOCX (tidak sesuai output PDF); template per pengguna (tidak konsisten); memakai font eksternal (butuh file dan risiko rendering). |
| **D5** | Kapan PDF dibuat dan bagaimana snapshot? | PDF dibuat **saat transisi `approved → issued`**, di dalam transaksi `issue()` yang sudah ada, setelah nomor atomik dibuat. Urutan: lock surat → validasi status/versi/self → resolve settings + subjek → snapshot + validasi → nomor → render PDF → tulis file privat → simpan nomor/status/versi → insert `letter_documents` → event → commit. Kegagalan validasi/settings/subjek → `422`, letter tetap `approved`, **nomor tidak dikonsumsi**, tidak ada dokumen/file. Kegagalan renderer → `500` generik, rollback yang sama; file sementara dibersihkan best-effort. Tidak ada reissue/regenerate; koreksi dilakukan dengan cancel + surat baru. Submit surat bertipe juga memvalidasi readiness settings dan eligibility subjek (tanpa nomor) agar approver tidak menyetujui surat yang tidak dapat diterbitkan; issue tetap memvalidasi ulang. Snapshot diambil **saat terbit** sesuai kriteria fase; jika data master berubah sehingga subjek tidak lagi eligible, issue `422` dan surat tetap approved. | Render saat submit (nomor belum ada; dokumen bisa basi); render di luar transaksi (nomor/dokumen bisa tidak konsisten); menyimpan HTML (bukan dokumen final); mengizinkan reissue (melanggar kriteria dan integritas nomor). |
| **D6** | Model data dan isi tiap jenis surat? | Kolom additive `letters`: `letter_type` (nullable, index), `document_subject_type` (nullable), `document_subject_id` (nullable, polymorphic tanpa FK), `document_fields` (JSON nullable). `letter_type` dan subjek **immutable setelah create**; `document_fields` hanya dapat diedit saat `draft`. `student_active`: `purpose` wajib 5–500; subjek = siswa dengan keanggotaan aktif pada tahun ajaran aktif; snapshot nama/NIS/NISN/tempat-tanggal lahir/gender/kelas/tahun. `student_mutation`: subjek = `student_mutations` tipe `out` status `active`; snapshot kelas/tahun asal, sekolah/NPSN tujuan, tanggal efektif, alasan; tidak mensyaratkan keanggotaan aktif. `employee_duty`: `duty_description` wajib 10–2000, `start_date`, `end_date` (≥ start), `location` 5–200; subjek = pegawai aktif; snapshot nama/NUPTK/tipe/status. **Tidak** menyimpan NIK siswa/orang tua, alamat, atau kontak pribadi di snapshot/PDF. | Menyimpan seluruh profil siswa/pegawai (PII berlebih); memakai NIK sebagai identitas cetak (sensitiv); membolehkan ganti tipe/subjek setelah create (approval tidak bermakna); satu jenis surat generik (isi tidak sesuai kebutuhan). |
| **D7** | Hak akses dan route matrix? | Tanpa ability/role baru. `letters.view`: baca metadata/readiness settings, logo, preview, dan download PDF issued/cancelled. `letters.manage`: create/edit surat bertipe, header/logo settings, subject pickers (juga butuh `students.view`/`employees.view`). `letters.sign`: penandatangan/tanda tangan/stempel settings, aset mentah signature/stamp, dan issue. Admin/Operator tidak dapat sign atau melihat aset mentah tanda tangan; Kepala Sekolah tidak dapat mengedit header/surat kecuali role-nya diberi `letters.manage` lewat UI peran. Semua route `auth:sanctum` + `active`; object-level check; pelanggaran `403`/`404`, bukan hanya tombol tersembunyi. | Memberi `letters.manage` ke kepala sekolah (melanggar pemisahan); mengizinkan admin melihat tanda tangan mentah (PII/penyalahgunaan); ability baru `letters.settings` (tidak perlu); membiarkan UI sebagai penegak. |
| **D8** | Kapan PDF dapat diakses dan bagaimana preview? | Surat bertipe `issued`/`cancelled` dengan dokumen: **download PDF tersimpan**. Surat bertipe `draft`/`review`/`approved`: **preview watermarked** `PREVIEW — NOT ISSUED`, tanpa nomor resmi, tidak disimpan, tidak mengonsumsi counter; preview juga memvalidasi settings/subjek. Surat umum 07b dan surat issued 07b lama **tidak memiliki PDF** dan endpoint mengembalikan `404`; **tidak ada backfill**. Surat cancelled yang pernah issued tetap menyimpan PDF dan dapat diunduh; jika file hilang, `404` tanpa regenerasi. Tidak ada URL publik; download lewat stream berizin. | Preview tanpa watermark (dapat disalahgunakan sebagai dokumen final); membuat PDF untuk draft (counter/nomor terpakai); backfill surat 07b (isi tidak tersedia); menyajikan file dari `public/` (tidak aman). |
| **D9** | Konten dan aset resmi dari pemilik? | Pemilik menetapkan/menyetujui: (a) teks `government_line`, `school_name`, `address_line`, `contact_line`, `place_of_issue`; (b) identitas penandatangan (nama, jabatan, label/nilai identitas); (c) file logo, tanda tangan, dan stempel resmi (atau menyetujui aset sintetis hanya untuk UAT); (d) isi badan surat untuk tiga jenis (Lampiran A adalah usulan; jika disetujui, teks itu dipakai; jika tidak, implementasi menunggu teks final); (e) konfirmasi bahwa **rekap sekolah bukan bagian 07c** dan ditunda ke DEC-12, sehingga komponen rekap pada DEC-11 tidak menghalangi penutupan DEC-11 untuk lingkup 07c. Tanda tangan gambar **bukan** tanda tangan digital tersertifikasi. | Memakai teks/aset sintetis di produksi (tidak sah); menebak jabatan/identitas penandatangan; menganggap gambar tanda tangan sebagai e-sign tersertifikasi; memasukkan rekap ke 07c (menambah scope dan keputusan format). |
| **D10** | Kontrak API dan UI? | **9 route baru** (§5.6): 4 settings (GET settings, POST header, POST signatory, GET asset), 3 subject pickers, 2 dokumen (download, preview). Total **37 route surat**. Respons `StatusResource`, create/update/transisi `200`. UI: halaman **Letter Settings**, form **New/Edit Letter** dengan tipe dan subjek kondisional, document card di detail Outgoing, filter tipe, tombol Preview/Download sesuai status/ability. English, 390 px, keyboard/aria, `409` Reload Latest, tanpa tombol dummy. Preview dipertahankan sebagai rekomendasi; jika pemilik menolak preview, approver hanya dapat melihat PDF setelah issue. | Satu endpoint `document` yang melayani preview+download (semantik status rawan); tidak ada settings page (operator harus mengedit DB); hanya PDF setelah issue (approver tidak dapat memeriksa tata letak); UI Indonesia (melanggar kebijakan English). |
| **D11** | UAT dan non-goals? | Pakai ulang **`.uat-phase07`** (SQLite, API `127.0.0.1:18025`, SPA `127.0.0.1:14025`, cookie `school_phase07_session`, storage `.uat-phase07/storage`, launcher `scripts/phase07-uat.php`). Tambah perintah idempotent **`prepare-documents`**: profil sekolah + `letter_settings` + aset PNG sintetis, 120 siswa sintetis (100 aktif di rombel P07C, 20 mutasi keluar), 20 mutasi, ±20 surat bertipe (draft/review/approved/issued/cancelled; termasuk satu surat tugas multi-halaman dan satu cancelled-after-issue yang mempertahankan PDF), memperluas `status`. **Tanpa reset** data 07a/07b; tidak menyentuh `.uat-phase05/06` atau operasional. Server dihentikan setelah penerimaan (DEC-27); data dipertahankan. Non-goals: template CRUD/DOCX/PDF-A/e-sign/QR/OCR/email/WhatsApp, arsip/versioning Fase 09, retensi DEC-13, rekap DEC-12, surat izin Fase 08, ability/role baru, merge/deploy, migrasi DB operasional/MariaDB, dependency selain D2, service/`.env`. | Lingkungan UAT baru (menduplikasi 126 incoming/46 outgoing); fixture <100 siswa (kurang sesuai kebijakan data sintetis); reset `.uat-phase07` (kehilangan data yang sudah diterima); server dibiarkan berjalan (melanggar DEC-27). |

**Ringkasan pertanyaan terbuka 07c:**

| Q | Jawaban yang diusulkan |
|---|---|
| Q1 — jenis surat mana yang dikerjakan? | Tiga: Student Active Certificate, Student Mutation Certificate, Employee Duty Letter; surat izin ditunda ke Fase 08 (D1). |
| Q2 — library PDF apa? | `dompdf/dompdf:^2.0`, pure PHP; perubahan dependency/lockfile hanya untuk ini dan wajib disetujui (D2). |
| Q3 — di mana kop/tanda tangan/stempel? | `letter_settings` per profil sekolah aktif; aset privat; signature wajib, logo/stempel opsional (D3). |
| Q4 — kapan PDF dibuat? | Saat `approved → issued`, di dalam transaksi issue; nomor dan dokumen atomik; gagal → rollback, tanpa nomor (D5). |
| Q5 — apakah PDF boleh dibuat ulang? | Tidak; tidak ada reissue/regenerate. Koreksi via cancel + surat baru (D5). |
| Q6 — bagaimana surat 07b yang sudah issued? | Tidak memiliki PDF, tidak di-backfill; endpoint `404` (D8). |
| Q7 — siapa yang menandatangani? | Aktor dengan `letters.sign`; nama/jabatan tercetak dari settings; `issued_by` mencatat aktor; hak sign sebaiknya hanya kepala sekolah (D3/D7). |
| Q8 — apakah preview disimpan? | Tidak; preview watermarked, tanpa nomor, tanpa counter, tidak membuat `letter_documents` (D8). |
| Q9 — bagaimana jika data master berubah? | PDF terbit memakai snapshot; perubahan master tidak mengubah PDF lama. Jika subjek tidak lagi eligible saat issue, issue `422` dan surat tetap approved (D5/D6). |
| Q10 — apakah tanda tangan gambar = e-sign? | Bukan; hanya gambar tanda tangan pada PDF, bukan sertifikat digital (D9). |

---

## 5. Desain teknis

### 5.1 Migration additive

#### 5.1.1 `letter_settings`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | — |
| `school_profile_id` | FK `school_profiles`, **unique**, restrictOnDelete | Satu settings per profil/tahun |
| `version` | unsigned int, default 1 | Optimistic locking |
| `government_line` | string(150) nullable | Baris pemerintah daerah |
| `school_name` | string(150) nullable | Wajib untuk readiness |
| `address_line` | string(255) nullable | Wajib untuk readiness |
| `contact_line` | string(150) nullable | Telepon/email/website |
| `place_of_issue` | string(100) nullable | Kota penerbitan |
| `logo_path` | string(255) nullable | Relatif disk privat |
| `logo_mime` | string(50) nullable | `image/png` setelah re-encode |
| `logo_size_bytes` | unsigned int nullable | — |
| `logo_sha256` | char(64) nullable | — |
| `signatory_name` | string(150) nullable | Wajib untuk readiness |
| `signatory_title` | string(150) nullable | Wajib untuk readiness |
| `signatory_identifier_label` | string(30) nullable | Mis. `NIP` |
| `signatory_identifier` | string(50) nullable | Nilai identitas |
| `signature_path` | string(255) nullable | Wajib untuk readiness |
| `signature_mime` | string(50) nullable | — |
| `signature_size_bytes` | unsigned int nullable | — |
| `signature_sha256` | char(64) nullable | — |
| `stamp_path` | string(255) nullable | Opsional |
| `stamp_mime` | string(50) nullable | — |
| `stamp_size_bytes` | unsigned int nullable | — |
| `stamp_sha256` | char(64) nullable | — |
| `updated_by` | FK `users` nullable, nullOnDelete | Aktor terakhir |
| `timestamps` | — | — |

- Tidak ada soft delete; settings per tahun tetap disimpan ketika tahun berganti.
- `version` dinaikkan setiap update; update dengan `expected_version` basi `409`.

#### 5.1.2 `letter_documents`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | — |
| `letter_id` | FK `letters`, **unique**, restrictOnDelete | Satu dokumen final per surat |
| `template_code` | string(50) | Kode registry |
| `template_version` | unsigned int | Versi template saat render |
| `letter_version` | unsigned int | Versi surat setelah issue |
| `official_number` | string(100) | Duplikat immutable nomor |
| `snapshot` | json | Snapshot minimal PII |
| `pdf_path` | string(255) | Relatif disk privat |
| `pdf_sha256` | char(64) | Checksum |
| `pdf_size_bytes` | unsigned big int | — |
| `page_count` | unsigned int | Hasil render |
| `renderer` | string(50) | Mis. `dompdf 2.x` |
| `generated_at` | timestamp | Waktu render |
| `generated_by` | FK `users`, restrictOnDelete | Aktor issue |
| `timestamps` | — | — |

- Index `(template_code, template_version)`.
- Model menolak update/delete; tidak ada endpoint tulis langsung.
- `snapshot` tidak diekspos di API; hanya untuk audit internal/backup.

#### 5.1.3 Kolom baru pada `letters`

| Kolom | Tipe | Catatan |
|---|---|---|
| `letter_type` | string(30) nullable, index | `student_active`, `student_mutation`, `employee_duty`; null = surat umum 07b |
| `document_subject_type` | string(30) nullable | `student`, `student_mutation`, `employee` |
| `document_subject_id` | unsigned big int nullable | Polymorphic tanpa FK; divalidasi server |
| `document_fields` | json nullable | Field per jenis; hanya diedit saat draft |

- Index tambahan: `(direction, letter_type)`, `(document_subject_type, document_subject_id)`.
- Tidak ada perubahan pada kolom 07a/07b; tidak ada `->change()` pada kolom lama.
- `letter_type`/subjek immutable setelah create; field JSON divalidasi ketat per jenis.

### 5.2 Resolusi sekolah/penandatangan dan aset

1. `AcademicSetting::query()->first()` → `active_school_year_id` (singleton).
2. `SchoolProfile::with('educationSchool','principal')->where('school_year_id', $id)->first()`.
3. `LetterSettings::where('school_profile_id', $profile->id)->first()`.
4. Jika tahun aktif/profil/settings tidak ada → readiness `false`; submit/issue surat bertipe `422` dengan `errors.letter_settings` dan `missing[]`.
5. `defaultSender()` diperbaiki: gunakan nama `educationSchool` dari profil tahun aktif; fallback ke profil pertama hanya untuk kompatibilitas 07b.
6. Saran form (tidak otomatis tersimpan): `school_name` dari `educationSchool.name`, `address_line` dari `educationSchool.address`, `signatory_name` dari `principal.name`, `signatory_title` = "Kepala Sekolah", `contact_line` dari `school_profile.email`/`website`.
7. Aset:
   - Validasi upload: ekstensi/MIME cocok, `getimagesize`, maks. 2 MB, dimensi ≤2000×2000, hanya PNG/JPEG.
   - Re-encode ke PNG via GD (alpha dipertahankan), buang metadata; nama `{asset}-{uuid}.png`.
   - Path: `attachments/letter-settings/{school_profile_id}/{asset}-{uuid}.png` pada disk `letters.disk` (default `local`).
   - Ganti aset: tulis file baru, update row/audit dalam transaksi, hapus file lama best-effort setelah commit.
   - Aset tidak pernah disajikan dari `public/`; hanya endpoint berizin.

### 5.3 Registry template dan isi dokumen

`config/letters.php` (usulan):

```php
'letter_types' => [
    'student_active' => [
        'label' => 'Student Active Certificate',
        'template' => 'student_active',
        'subject_type' => 'student',
    ],
    'student_mutation' => [
        'label' => 'Student Mutation Certificate',
        'template' => 'student_mutation',
        'subject_type' => 'student_mutation',
    ],
    'employee_duty' => [
        'label' => 'Employee Duty Letter',
        'template' => 'employee_duty',
        'subject_type' => 'employee',
    ],
],
'templates' => [
    'student_active' => ['version' => 1, 'view' => 'letters.pdf.student-active'],
    'student_mutation' => ['version' => 1, 'view' => 'letters.pdf.student-mutation'],
    'employee_duty' => ['version' => 1, 'view' => 'letters.pdf.employee-duty'],
],
'document' => [
    'paper' => 'a4',
    'orientation' => 'portrait',
    'font' => 'DejaVu Sans',
    'assets_path' => 'attachments/letter-settings',
    'documents_path' => 'attachments/letter-documents',
    'max_image_kb' => 2048,
    'max_image_dimension' => 2000,
    'preview_watermark' => 'PREVIEW — NOT ISSUED',
],
```

- `LetterTemplateRegistry` memvalidasi config: `letter_type` dikenal, template ada, view ada, versi ≥1; config invalid → `500` saat preflight/test, bukan saat issue.
- Template memakai partial `letters.pdf.partials.letterhead` dan `signature`; semua variabel di-escape Blade.
- Isi minimum (usulan lengkap di **Lampiran A**):
  - **Student Active Certificate**: kop, judul, nomor resmi (kecuali preview), identitas siswa (nama, NIS, NISN, tempat/tanggal lahir, gender, kelas, tahun ajaran), pernyataan aktif, `purpose`, tempat/tanggal, penandatangan, tanda tangan/stempel.
  - **Student Mutation Certificate**: kop, judul, nomor, identitas siswa, pernyataan mutasi keluar, kelas/tahun asal, sekolah/NPSN tujuan, tanggal efektif, alasan, tempat/tanggal, penandatangan.
  - **Employee Duty Letter**: kop, judul, nomor, identitas pegawai (nama, NUPTK, tipe/status), `duty_description`, `start_date`–`end_date`, `location`, tempat/tanggal, penandatangan.
- Footer setiap halaman: nomor resmi/preview + "Page X of Y"; font DejaVu Sans; margin aman A4.
- Template version dinaikkan hanya saat layout/isi berubah; dokumen lama tetap memakai versi tersimpan.

### 5.4 Snapshot dan alur generate

Snapshot (JSON, minimal PII):

```json
{
  "template": {"code": "student_active", "version": 1},
  "school": {
    "name": "...", "npsn": "20512345",
    "government_line": "...", "address_line": "...", "contact_line": "...",
    "place_of_issue": "...", "logo_sha256": "..."
  },
  "signatory": {
    "name": "...", "title": "...",
    "identifier_label": "NIP", "identifier": "...",
    "signature_sha256": "...", "stamp_sha256": "..."
  },
  "letter": {
    "official_number": "...", "letter_date": "2026-11-10",
    "subject": "...", "recipient": "...", "category": "..."
  },
  "subject": {},
  "fields": {},
  "issued_at": "2026-11-10T09:00:00+07:00",
  "issued_by": {"id": 1, "name": "..."}
}
```

- `subject` hanya field yang tercetak (lihat D6); tanpa NIK/orang tua/alamat/kontak.
- Aset disimpan sebagai hash di snapshot, bukan path; render memakai data URI dari file privat.
- Alur `issue()`:
  1. `DB::transaction`: lock surat; validasi `approved`, `expected_version`, aktor ≠ creator.
  2. Jika `letter_type !== null`: resolve settings, validasi subjek, bangun snapshot, validasi field.
  3. `LetterNumberService::next()`; set nomor/`issued_at`/`issued_by`/`workflow_status=issued`; `version+1`.
  4. Render PDF (bytes) dari snapshot + nomor (atau watermark jika preview).
  5. Tulis file ke `attachments/letter-documents/{letter_id}/{uuid}.pdf` (disk privat); hitung sha256/size/page count.
  6. Simpan surat, insert `letter_documents`, event `issued` (metadata nomor/periode/sequence + template code/version/page count untuk typed), commit.
  7. Jika terjadi exception setelah file ditulis: hapus file best-effort; transaksi rollback; tidak ada nomor/dokumen/status tertinggal.
- File ditulis di dalam transaksi; crash setelah tulis sebelum commit dapat meninggalkan orphan file tanpa row. Ini dicatat sebagai risiko; tidak ada pembersihan otomatis di 07c.
- `letter_version` dokumen = versi surat setelah transisi (mis. versi 5 → 6).
- Tidak ada endpoint untuk regenerate; tidak ada reissue.

### 5.5 Preview dan download

| Status surat | Preview | Download PDF tersimpan |
|---|---|---|
| `draft` | Ya (watermarked; field wajib harus valid) | Tidak |
| `review` | Ya (watermarked) | Tidak |
| `approved` | Ya (watermarked) | Tidak |
| `issued` | Tidak (gunakan PDF final) | Ya |
| `cancelled` (pernah issued) | Tidak | Ya (file dipertahankan) |
| `cancelled` (belum issued) | Tidak | Tidak |
| Surat umum 07b / issued 07b lama | Tidak | Tidak (`404`) |

- Preview: render on-the-fly, `official_number=null`, watermark `PREVIEW — NOT ISSUED` pada setiap halaman, tidak menulis file/DB/counter.
- Preview memakai settings/subjek saat ini; jika tidak valid → `422`.
- Download: hanya file tersimpan; `Content-Type: application/pdf`, `Content-Disposition: attachment; filename="{sanitized official number}.pdf"`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, `Content-Security-Policy: sandbox`, `Content-Length`.
- Preview: `Content-Disposition: inline`, header keamanan sama.
- Nama file: ganti `/ \ : * ? " < > |` dengan `-`; fallback `letter-{id}.pdf`.
- Tidak ada path/hash/snapshot di respons/header.

### 5.6 Endpoint (usulan)

**Settings (4 route baru):**

| Method | Endpoint | Ability | Perilaku |
|---|---|---|---|
| GET | `/api/letters/settings` | `letters.view` | Tahun aktif, profil, settings metadata, readiness + `missing[]`, saran form, permission UI; tanpa path/aset mentah |
| POST | `/api/letters/settings/header` | `letters.manage` | Upsert header + logo (`multipart`); `expected_version`; `200`; `409` jika basi; `422` jika tahun/profil tidak ada |
| POST | `/api/letters/settings/signatory` | `letters.sign` | Upsert penandatangan + signature/stamp (`multipart`); `expected_version`; `200`; signature wajib pada simpan pertama |
| GET | `/api/letters/settings/assets/{asset}` | `logo`: `letters.view`; `signature`/`stamp`: `letters.sign` | Stream aset privat; `asset` whitelist; `404` jika tidak ada |

**Subject pickers (3 route baru):**

| Method | Endpoint | Ability | Perilaku |
|---|---|---|---|
| GET | `/api/letters/outgoing/subjects/students?search=&per_page=` | `letters.manage` + `students.view` | Siswa dengan keanggotaan aktif tahun aktif; id/nama/NIS/NISN/kelas; tanpa NIK/alamat |
| GET | `/api/letters/outgoing/subjects/student-mutations?search=&per_page=` | `letters.manage` + `students.view` | Mutasi `out` aktif; id/siswa/tanggal/sekolah tujuan; tanpa alasan panjang |
| GET | `/api/letters/outgoing/subjects/employees?search=&per_page=` | `letters.manage` + `employees.view` | Pegawai aktif; id/nama/NUPTK/tipe; tanpa NIK/alamat/kontak |

**Dokumen (2 route baru):**

| Method | Endpoint | Ability | Perilaku |
|---|---|---|---|
| GET | `/api/letters/outgoing/{letter}/document` | `letters.view` | PDF tersimpan untuk issued/cancelled; `404` untuk draft/review/approved/general/legacy/file hilang |
| GET | `/api/letters/outgoing/{letter}/document/preview` | `letters.view` | PDF preview watermarked untuk draft/review/approved typed; `404` untuk issued/cancelled/general; `422` jika settings/subjek tidak valid |

- **Total 9 route baru; 37 route surat.**
- Route `/letters/outgoing/subjects/*` didaftarkan **sebelum** `/letters/outgoing/{letter}` dan memakai `whereNumber('letter')`.
- Semua route `auth:sanctum` + `active`; controller `authorize`; object-level check memastikan surat outgoing dan tipe sesuai.
- Tidak ada endpoint untuk membaca `snapshot`, `pdf_path`, `pdf_sha256`, atau `document_fields` mentah di luar detail surat berizin.

### 5.7 Bentuk respons dan filter

Item outgoing menambahkan:

- `letter_type`, `letter_type_label`, `has_document`.
- `document`: untuk detail — `available`, `template_code`, `template_version`, `page_count`, `generated_at`, `size_bytes`; tanpa path/hash/snapshot.

`GET /api/letters/outgoing` menambahkan filter `letter_type` (whitelist) dan `has_document` (`0/1`); search tetap pada `official_number`/`recipient`/`sender`/`subject`. `options` menambahkan `letter_types`, `settings_ready`, `settings_missing`, dan `document` capability.

Settings response:

```json
{
  "active_school_year": {"id": 1, "name": "2026/2027"},
  "school_profile": {"id": 1, "npsn": "20512345", "school_name": "..."},
  "settings": {"version": 3, "school_name": "...", "signatory_name": "...", "logo_available": true, "signature_available": true, "stamp_available": false, "updated_at": "..."},
  "readiness": {"active_year": true, "profile": true, "header": true, "signatory": true, "signature": true, "template": true, "ready": true, "missing": []},
  "suggestions": {"school_name": "...", "address_line": "...", "signatory_name": "...", "signatory_title": "Kepala Sekolah"},
  "permissions": {"can_manage_header": true, "can_manage_signatory": false}
}
```

- Tidak ada `signature_path`, `pdf_path`, atau snapshot.
- `letter_type_label` English; isi dokumen mengikuti teks resmi D9.

### 5.8 Matriks akses

| Operasi | Ability |
|---|---|
| Baca settings/readiness, logo | `letters.view` |
| Edit header/logo settings | `letters.manage` |
| Edit penandatangan/signature/stamp | `letters.sign` |
| Lihat aset mentah signature/stamp | `letters.sign` |
| Create/edit surat bertipe, pilih subjek | `letters.manage` + `students.view`/`employees.view` untuk picker |
| Submit/approve/reject/issue/cancel | Aturan 07b (manage/approve/sign) |
| Preview PDF | `letters.view` |
| Download PDF issued/cancelled | `letters.view` |
| Surat umum/legacy | Tidak ada PDF; `404` |

- Tidak ada ability baru. Admin/Operator tidak otomatis mendapat `letters.sign`.
- UI menyembunyikan tombol; server tetap `403`/`404`.

### 5.9 UI (English)

**Letter Settings (halaman baru `/public/letters/settings`, route `letterSettings`, guard `letters.view`):**

1. Header **Letter Settings**, deskripsi singkat; kartu **Active Period** (tahun ajaran, profil sekolah, readiness checklist + missing).
2. **Letterhead** (form hanya `letters.manage`): government line, school name, address, contact, place of issue; logo upload/preview/remove; **Save Header**.
3. **Signatory** (form hanya `letters.sign`): name, title, identifier label/value; signature upload/preview; stamp upload/preview/remove; **Save Signatory**.
4. View-only: metadata terbaca, tanpa tombol tulis; pesan jika belum ada settings/tahun aktif.
5. Link ke halaman **School Profile** yang ada untuk melengkapi profil/tahun, tanpa CRUD baru.

**Outgoing Letters (perluasan):**

1. Dialog **New/Edit Letter** menambahkan **Letter type** (General, Student Active Certificate, Student Mutation Certificate, Employee Duty Letter) dan field kondisional:
   - `student_active`: **Student** picker + **Purpose**.
   - `student_mutation`: **Mutation record** picker (menampilkan siswa, tanggal, sekolah tujuan).
   - `employee_duty`: **Employee** picker + **Duty description**, **Start date**, **End date**, **Location**.
2. Setelah create, tipe/subjek read-only; `document_fields` editable hanya saat draft.
3. Detail menambahkan **Document** card: template, version, pages, generated date; **Preview** (draft/review/approved typed), **Download PDF** (issued/cancelled); pesan **No PDF available (legacy letter)** untuk issued 07b.
4. Daftar menambahkan kolom/filter **Type** dan indikator dokumen.
5. `409` panel **Reload Latest** tetap; preview/download memakai blob axios (bukan URL publik).
6. English, 390 px, keyboard/aria, 0 console error, tanpa tombol dummy.

**Menu Letters:** Incoming Letters, Outgoing Letters, Dispositions, **Letter Settings**; masing-masing `requires: 'letters.view'`.

### 5.10 Audit dan privasi

- **`Letter` allowlist** menambah `letter_type`; **tidak** menambah `document_fields`, `document_subject_id`, atau snapshot.
- **`LetterSettings` allowlist**: `school_profile_id`, `school_name`, `place_of_issue`, `signatory_title`, `version`, `updated_by`, `logo_sha256`, `signature_sha256`, `stamp_sha256`; **tanpa** government line/address/contact, `signatory_name`, `signatory_identifier`, atau path.
- **`LetterDocument`**: tidak memakai `Loggable`; model menolak update/delete; snapshot tidak diekspos; hanya dibaca internal.
- **`letter_events`**: event `issued` untuk typed menambahkan `template_code`, `template_version`, `page_count`; **tanpa** nama siswa, NISN, alasan mutasi, purpose, duty description, atau path file.
- Audit tetap dibuat server; tidak ada teks sensitif baru. File dan snapshot mengikuti DEC-13 (belum diputuskan).

### 5.11 Transaksi, lock, dan rollback

1. FormRequest memvalidasi field, tipe, subjek, `expected_version`, dan ability.
2. `DB::transaction` pada issue: lock surat, validasi status/versi/self, resolve settings/subjek, nomor, render, file, simpan, dokumen, event.
3. File ditulis sebelum commit; exception setelah tulis → hapus file best-effort; exception sebelum tulis → tidak ada file.
4. `letter_documents` unique `letter_id` mencegah dokumen ganda; unique `official_number` tetap pertahanan terakhir.
5. Replay setelah issue → `409` dengan konteks versi/nomor; tidak ada nomor/dokumen kedua.
6. SQLite mengandalkan serialisasi writer; MariaDB/InnoDB dan race multi-proses **NOT RUN** sampai dijalankan.
7. Settings update memakai `expected_version`; `409` tanpa mutasi.
8. Orphan file jika proses crash di antara tulis dan commit dicatat sebagai risiko; tidak ada pembersih otomatis di 07c.

### 5.12 Backup dan file

- Path baru: `storage/app/attachments/letter-settings/...` dan `storage/app/attachments/letter-documents/...` (disk privat).
- `config/backup.php:19` dan `BackupService.php:54` sudah mencakup `storage/app/attachments`; tidak ada perubahan backup code.
- File tidak dihapus saat surat cancelled/void; retensi mengikuti DEC-13.
- Restore drill dengan file 07c tetap **NOT RUN** sampai dijalankan.

---

## 6. Matriks tes

Backend (Pest, SQLite in-memory; MariaDB tetap NOT RUN):

| # | Skenario | Ekspektasi |
|---|---|---|
| T1 | Guest/operator/admin/kepsek/waka mengakses settings, subject pickers, preview, download | Guest `401`; operator/admin header/manage, sign/aset mentah `403`; kepsek sign/preview/download, edit header `403`; waka hanya view; object-level `404` |
| T2 | `GET settings` tanpa tahun aktif/profil/settings | `200` dengan readiness `false`, `missing[]`; tidak ada error 500; tidak ada data sensitif |
| T3 | Simpan header + logo valid | Row settings untuk profil aktif; `version+1`; file privat ada; audit dibuat; respons tanpa path |
| T4 | Logo invalid (SVG, PHP disguised, >2 MB, >2000 px, corrupt) | `422`; tidak ada row/file baru; settings lama tidak berubah |
| T5 | Simpan signatory + signature/stamp valid | File PNG ter-re-encode; signature/stamp privat; `version+1`; audit tanpa identifier; hanya `letters.sign` |
| T6 | Akses aset: logo oleh view, signature/stamp oleh non-sign | Logo `200`; signature/stamp `403`; guest `401`; asset whitelist |
| T7 | Settings per tahun/profil | Ganti tahun aktif → settings tahun baru; settings lama tetap; surat/dokumen lama tidak berubah |
| T8 | Update settings dengan `expected_version` basi | `409`; tidak ada mutasi; versi tetap |
| T9 | `defaultSender()` | Memakai sekolah dari profil tahun aktif; fallback profil pertama jika tahun aktif tidak ada |
| T10 | Create typed letter (3 jenis) valid | `letter_type`/subject/fields tersimpan; `workflow_status=draft`; tanpa nomor/dokumen |
| T11 | Create typed invalid: field kurang, subjek tidak aktif, mutasi bukan out/active, pegawai exited, tipe tidak dikenal | `422`; nol baris; pesan field jelas |
| T12 | Ubah tipe/subjek setelah create; edit fields di review/approved/issued | Tipe/subjek `422`/`409`; fields hanya draft; nol mutasi di status non-draft |
| T13 | Submit typed tanpa settings/subjek valid | `422`; surat tetap draft; tanpa nomor/dokumen; general letter 07b tetap dapat submit |
| T14 | Preview draft/review/approved typed | PDF bytes valid, watermark, tanpa nomor resmi; tidak ada `letter_documents`, file, atau counter |
| T15 | Preview settings/subjek invalid; preview issued/general/legacy | `422` untuk invalid; `404` untuk issued/general/legacy; tidak ada mutasi |
| T16 | Issue typed berhasil | `issued`; nomor; PDF file privat ada; `letter_documents` (snapshot, sha256, size, page count, renderer); event metadata; versi+1 |
| T17 | Issue gagal settings/subjek/render/file write | `422`/`500`; rollback; surat tetap approved; nomor tidak dikonsumsi; tidak ada dokumen/file |
| T18 | Issue gagal insert dokumen/event (forced failure) | Rollback penuh; file dibersihkan; counter tidak berubah; status tetap approved |
| T19 | Snapshot immutable setelah master/settings berubah | PDF dan snapshot tidak berubah; download tetap sama; surat baru memakai data baru |
| T20 | Subjek menjadi tidak eligible setelah approve | Issue `422`; surat tetap approved; tanpa nomor/dokumen |
| T21 | Surat tugas multi-halaman (2000 karakter) | PDF valid; `page_count ≥2`; file > ambang; tidak error layout |
| T22 | Font/gambar tertanam | PDF memuat font DejaVu dan objek gambar signature/stamp; tidak ada remote resource |
| T23 | Download issued/cancelled-after-issue | `200`, `application/pdf`, header keamanan, filename tersanitasi; file sama dengan sha256; cancelled tetap ada |
| T24 | Download draft/review/approved/general/legacy/file hilang | `404`; tidak ada regenerasi; tidak ada file baru |
| T25 | Response list/detail/options | `letter_type`, `has_document`, ringkasan `document`; tanpa path/hash/snapshot; filter `letter_type`/`has_document` benar |
| T26 | Audit/history privacy | Activities/events tanpa purpose/alasan/NISN/identifier/signatory name/path; `LetterDocument` immutable; settings allowlist benar |
| T27 | File privat/backup path | File settings/dokumen di `storage/app/attachments/...`; tidak ada URL publik; test `Storage::fake` tidak membocorkan path |
| T28 | Rollback, regresi, dan route | Kegagalan transaksi tidak meninggalkan nomor/dokumen/event; suite 07a/07b tetap PASS; Pint; `route:list` 37 route surat; build/lint frontend |

Frontend/UX: browser checks untuk settings (readiness/header/signatory/asset permission), typed create/edit (3 jenis, subject picker, validasi), preview watermark, issue/download, cancelled download, legacy/general 404, view-only, `409` Reload Latest, 390 px, English, keyboard/aria, 0 console error. Jika browser tidak tersedia, tulis **NOT RUN** dan sediakan checklist manual; jangan mengklaim PASS tanpa bukti.

---

## 7. UAT

- Lingkungan: **`.uat-phase07`** (SQLite), API `127.0.0.1:18025`, SPA `127.0.0.1:14025`, cookie `school_phase07_session`, launcher `laravel-api/scripts/phase07-uat.php`, storage `.uat-phase07/storage`.
- Perintah baru (idempotent, tanpa reset):
  - **`prepare-documents`**:
    - Pastikan education level + education school sintetis NPSN `20512345` + semester + `school_profiles` untuk tahun 1 dengan principal `uat.kepsek`; set `academic_settings` aktif ke tahun 1.
    - Buat `letter_settings` + aset PNG sintetis berlabel **UAT SYNTHETIC** (logo, signature, stamp) dengan GD; tidak memakai aset nyata.
    - Tambah **120 siswa sintetis**: 100 aktif di rombel `UAT P07C` tahun 1; 20 mutasi keluar dengan membership ditutup dan 20 `student_mutations` tipe `out` status `active` ke 3 sekolah tujuan sintetis.
    - Tambah **±20 surat bertipe**: 5 draft, 4 review, 4 approved, 4 issued, 3 cancelled (satu cancelled-after-issue dengan PDF); mencakup tiga jenis, satu surat tugas multi-halaman (2000 karakter), satu surat siswa dengan `purpose`, satu surat mutasi.
    - Nomor issued melanjutkan counter 07b (006); harapan aktual (mis. 007–011) dicatat di laporan UAT setelah dijalankan, bukan diklaim sekarang.
    - Marker idempotensi `UAT-P07C` pada catatan; rerun tidak menggandakan data.
  - **`status`**: menampilkan readiness settings, jumlah surat bertipe per tipe/status, `letter_documents`, file PDF, siswa/mutasi sintetis, dan data 07a/07b yang tetap utuh.
- Akun: `uat.operator`/`uat.admin` (`manage`), `uat.kepsek` (`view`/`approve`/`sign`), `uat.waka` (`view`). Password hanya di runtime privat `.uat-phase07/account-password.txt`; tidak dicetak di dokumen.
- Checklist manual (ditulis lengkap di `PHASE-07C-MANUAL-REVIEW.md` setelah implementasi):
  1. Isolasi: API/SPA hanya 18025/14025; DB/storage `.uat-phase07`; tidak menyentuh `.uat-phase05/06`/operasional.
  2. Login/izin: operator/admin dapat header/logo + surat bertipe; tidak dapat sign/aset tanda tangan; kepsek dapat sign/signatory; waka read-only; server `403`.
  3. Readiness: tahun/profil aktif, missing settings, typed issue tertolak `422`.
  4. Header: simpan teks/logo, reload, preview logo, versi naik.
  5. Signatory: simpan nama/jabatan/identifier, signature/stamp, preview; aset mentah hanya kepsek.
  6. Typed create: tiga jenis, subject picker, validasi, tipe/subjek immutable.
  7. Draft edit: fields editable; settings warning; submit ditolak jika settings kurang.
  8. Preview: watermark, tanpa nomor, tidak membuat dokumen/counter.
  9. Submit/approve typed letter oleh kepsek; reject membuka draft.
  10. Issue: PDF terbit, nomor, halaman, download; surat tugas multi-halaman.
  11. Snapshot: ubah siswa/settings setelah issue; PDF lama tetap; surat baru memakai data baru.
  12. Download: header aman, filename, PDF valid; legacy 07b/general `404`; cancelled-after-issue tetap dapat diunduh.
  13. Master berubah: mutasi dibatalkan/siswa tidak aktif → issue `422`; surat tetap approved; tanpa nomor.
  14. Daftar/filter tipe/indikator dokumen; detail tanpa path/hash/snapshot.
  15. Audit: activities/events tanpa purpose/alasan/NISN/identifier; dokumen immutable.
  16. 390 px, English, keyboard/aria, 0 console error.
  17. Isolasi data 07a/07b: 126 incoming/46 outgoing/32 disposisi tetap; tidak ada reset.
  18. Setelah pemilik menyatakan penerimaan, hentikan server 14025/18025 dengan verifikasi PID/command line; pertahankan `.uat-phase07`; jangan sentuh Apache/MariaDB (DEC-27).
- Server dinyalakan untuk review dan **dihentikan setelah penerimaan**; perintah restart dicatat.

---

## 8. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Dependency PDF tidak diotorisasi | D2 eksplisit; jangan menambah package sebelum persetujuan; jika ditolak, 07c ditunda |
| Lockfile berubah di luar dompdf | Tambah hanya `dompdf/dompdf:^2.0` + transitive; periksa diff lockfile; laporkan package yang berubah |
| Render gagal setelah nomor dibuat | Render/snapshot di dalam transaksi; rollback counter/status; hapus file best-effort; tes T17/T18 |
| Nomor terpakai untuk surat yang tidak terbit | Nomor dibuat setelah validasi settings/subjek; transaksi atomik; tes T17 |
| PDF berbeda dari isi yang disetujui | Submit memvalidasi readiness/subjek; approve melihat preview; issue memvalidasi ulang; snapshot saat issue dicatat; batas perubahan master didokumentasikan |
| PII berlebih di snapshot/PDF | Allowlist field per jenis; tanpa NIK/orang tua/alamat/kontak; tes T26 |
| Tanda tangan mentah disalahgunakan | Aset mentah hanya `letters.sign`; PDF terbit berizin; watermark preview; bukan e-sign tersertifikasi |
| Template injection/XSS | Blade escape, tanpa HTML pengguna, remote resource off, data URI; tes T22 |
| Font/encoding rusak | DejaVu Sans; tes teks Indonesia dan karakter khusus (`&`, `<`, `>`) |
| Multi-halaman/overflow | Tes surat tugas 2000 karakter; layout A4; footer nomor/halaman |
| Settings salah tahun/profil | Resolusi tahun aktif; unique `school_profile_id`; settings per tahun; tes T7/T9 |
| Settings basi saat update | `expected_version`; `409`; tes T8 |
| Surat 07b issued tidak punya PDF | Tidak ada backfill; `404` eksplisit; UI menjelaskan legacy; tes T24 |
| File hilang/rusak | Download `404`, tanpa regenerasi; backup mencakup path; restore drill NOT RUN |
| Orphan file saat crash | Tulis dalam transaksi + cleanup best-effort; catat risiko; tidak ada auto-prune |
| MariaDB/InnoDB/race | SQLite test; MariaDB dan multi-proses NOT RUN; catat |
| DEC-13 retensi | File/snapshot disimpan; tidak ada pemusnahan; DEC-13 tetap OPEN |
| Rekap DEC-12 terbawa | D9 meminta konfirmasi rekap di luar 07c; DEC-11 ditutup hanya untuk komponen 07c |
| Scope melebar ke Fase 08 | Surat izin eksplisit non-goal; D1 |
| UAT menggandakan data | Fixture idempotent marker `UAT-P07C`; tanpa reset; `status` verifikasi |

---

## 9. Non-goals dan batas otorisasi

- Paket rencana ini **telah disetujui** sebagai **DEC-34** (2026-11-02 WIB); implementasi 07c diotorisasi dan dikerjakan terpisah. Otorisasi 07c tidak mencakup 07d/Fase 08, merge/deploy/force-push/rewrite, migrasi database operasional/MariaDB, perubahan service/`.env`, atau dependency selain D2.
- Tidak ada template CRUD/editor, upload HTML, DOCX, PDF/A, e-sign tersertifikasi, QR/barcode, OCR, email/WhatsApp otomatis, notifikasi, bulk generation, print queue, atau integrasi eksternal.
- Tidak ada arsip/versioning Fase 09, retensi/pemusnahan DEC-13, rekap sekolah DEC-12, atau surat izin DEC-07.
- Tidak ada backfill PDF untuk surat 07b, nomor manual, edit nomor, reissue, atau regenerate.
- Tidak ada perubahan pada `.uat-phase05`/`.uat-phase06` atau data operasional; UAT hanya `.uat-phase07`.
- Commit+push paket rencana mengikuti aturan repo; push bukan penerimaan pemilik. Setelah 07c siap review, berhenti; Fase 08 memerlukan instruksi/rencana terpisah.

---

## 10. Ringkasan keputusan untuk pemilik

| Kode | Ringkasan |
|---|---|
| D1 | 07c saja; tiga jenis surat (aktif siswa, mutasi siswa, tugas pegawai); surat izin ditunda Fase 08 |
| D2 | Tambah `dompdf/dompdf:^2.0` + lockfile hanya untuk PDF; tanpa binary sistem; jika ditolak, 07c ditunda |
| D3 | `letter_settings` per profil/tahun; kop/logo/penandatangan/signature/stamp; aset privat; signature wajib |
| D4 | Template Blade kode + registry versi; tanpa CRUD/editor; DejaVu/A4; escape dan remote off |
| D5 | PDF + snapshot dibuat saat `approved → issued` dalam transaksi; gagal rollback; tanpa reissue |
| D6 | Kolom `letter_type`/subjek/fields; tiga jenis dengan validasi dan snapshot minimal PII |
| D7 | Tanpa ability baru; view/manage/sign sesuai matrix; aset mentah sign-only |
| D8 | Download hanya issued/cancelled; preview watermarked untuk draft/review/approved; legacy 07b `404` |
| D9 | Pemilik menetapkan teks/aset/penandatangan; Lampiran A adalah usulan; rekap di luar 07c |
| D10 | 9 route baru (37 total), halaman Letter Settings, typed Outgoing, document card, filter; English/390/409 |
| D11 | UAT `.uat-phase07` + `prepare-documents` (120 siswa/20 mutasi/20 surat bertipe); server stop setelah penerimaan |

D1–D11 **APPROVED 2026-11-02 WIB** ("Saya setuju D1-D11") dan dicatat sebagai **DEC-34 APPROVED**; implementasi 07c diotorisasi; **DEC-11 CLOSED untuk lingkup 07c**; **DEC-13 tetap OPEN**. Bukti implementasi dicatat di `PHASE-07C-REPORT.md` setelah verifikasi.

---

## 11. Prompt setelah subfase ini disetujui

```text
Baca AGENTS.md, ROADMAP, HANDOFF, PHASE-07-LETTERS.md dan PHASE-07C-PLAN.md.
Kerjakan subfase 07c saja: kop, logo, penandatangan, tanda tangan, stempel,
template PDF, snapshot immutable, preview/download berizin sesuai
PHASE-07C-PLAN.md (DEC-34/D1–D11).
Jangan menebak teks/aset/penandatangan di luar keputusan pemilik; gunakan
dependency dompdf yang disetujui D2 tanpa upgrade package lain.
Audit HEAD dan consumer/kontrak aktual sebelum edit; jangan mengubah kolom
lama secara destruktif. Gunakan fixture/DB/storage terisolasi; jalankan
verifikasi relevan dan catat hasil aktual. Jika browser tidak tersedia,
jelaskan dan berikan checklist manual. Sinkronkan kontrak affected,
CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP. Berhenti setelah hasil
subfase siap review; jangan lanjut Fase 08 atau merge/deploy.
```

---

## 12. Syarat keluar 07c

- [x] D1–D11 disetujui dan dicatat sebagai **DEC-34 APPROVED** (2026-11-02 WIB); **DEC-11 CLOSED untuk lingkup 07c**; DEC-13 tetap OPEN.
- [x] Dependency D2 disetujui dan hanya `dompdf/dompdf v2.0.8` + empat transitive (`masterminds/html5`, `phenx/php-font-lib`, `phenx/php-svg-lib`, `sabberworm/php-css-parser`) yang ditambahkan; diff lockfile diperiksa, tanpa penghapusan/upgrade lain.
- [x] `letter_settings`, `letter_documents`, dan kolom typed pada `letters` dimigrasikan additive; 07a/07b tetap berjalan (regresi Letters 86/1.744).
- [x] Tiga jenis surat, kop/logo/penandatangan/signature/stamp, PDF, snapshot, preview, dan download berfungsi (settings 10/167; documents 21/744).
- [x] Matriks T1–T28 dijalankan atau NOT RUN dengan alasan; hasil aktual (31 tes/911 assertions; NOT RUN dicatat di `PHASE-07C-REPORT.md`).
- [ ] UAT `.uat-phase07` + `prepare-documents` dan checklist manual tersedia; persetujuan pemilik dicatat terpisah. **Checklist tersedia; review pemilik PENDING (#1–#18).**
- [x] Kontrak DATABASE/API-CONTRACTS/ACCESS-CONTROL/OPERATIONS/REQUIREMENTS diperbarui.
- [x] SHA/working tree, known issues, dan langkah berikutnya dicatat di INDEX/HANDOFF/ROADMAP/laporan (`4713c70` → `2a78b7e`).
- [ ] Server UAT dihentikan setelah penerimaan (DEC-27); data dipertahankan. **Belum: server 14025/18025 masih berjalan sampai penerimaan.**
- [x] Tidak ada merge/deploy/force-push/rewrite, migrasi DB operasional/MariaDB, perubahan service/`.env`, atau dependency lain; Fase 08 tidak dimulai.

---

## Lampiran A — Usulan isi dokumen (dapat diganti pemilik)

> Usulan ini **belum final**. Jika pemilik menyetujui D9 dengan teks ini, teks dipakai; jika tidak, implementasi menunggu teks resmi. UAT memakai teks sintetis bertanda **UAT SYNTHETIC**, bukan teks produksi.

### A.1 Student Active Certificate

```text
[KOP: government_line / school_name / address_line / contact_line / logo]

SURAT KETERANGAN AKTIF SISWA
Nomor: {official_number}

Yang bertanda tangan di bawah ini, Kepala {school_name}, dengan ini menerangkan bahwa:

Nama                 : {student.name}
NIS                  : {student.nis}
NISN                 : {student.nisn}
Tempat/Tanggal Lahir : {student.birthplace}, {student.birthdate}
Jenis Kelamin        : {student.gender}
Kelas                : {student.class}
Tahun Ajaran         : {school_year.name}

adalah benar siswa aktif pada {school_name} tahun ajaran {school_year.name}.

Surat keterangan ini dibuat untuk keperluan {purpose}.

Demikian surat keterangan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.

{place_of_issue}, {letter_date}
Kepala {school_name}

[signature] [stamp]

{signatory.name}
{signatory.identifier_label} {signatory.identifier}
```

### A.2 Student Mutation Certificate

```text
[KOP]

SURAT KETERANGAN MUTASI SISWA
Nomor: {official_number}

Yang bertanda tangan di bawah ini, Kepala {school_name}, dengan ini menerangkan bahwa:

Nama                 : {student.name}
NIS                  : {student.nis}
NISN                 : {student.nisn}
Tempat/Tanggal Lahir : {student.birthplace}, {student.birthdate}
Jenis Kelamin        : {student.gender}
Kelas/Tahun Ajaran   : {student.class} / {school_year.name}

telah mutasi keluar dari {school_name} pada tanggal {mutation.effective_date} menuju {mutation.destination_school} (NPSN {mutation.destination_npsn}).

Alasan: {mutation.reason}

Demikian surat keterangan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.

{place_of_issue}, {letter_date}
Kepala {school_name}

[signature] [stamp]

{signatory.name}
{signatory.identifier_label} {signatory.identifier}
```

### A.3 Employee Duty Letter

```text
[KOP]

SURAT TUGAS
Nomor: {official_number}

Yang bertanda tangan di bawah ini, Kepala {school_name}, menugaskan kepada:

Nama         : {employee.name}
NUPTK        : {employee.nuptk}
Jenis/Status : {employee.type} / {employee.status}

untuk melaksanakan tugas: {duty_description}

Waktu        : {start_date} s.d. {end_date}
Tempat       : {location}

Demikian surat tugas ini dibuat untuk dilaksanakan dengan penuh tanggung jawab.

{place_of_issue}, {letter_date}
Kepala {school_name}

[signature] [stamp]

{signatory.name}
{signatory.identifier_label} {signatory.identifier}
```
