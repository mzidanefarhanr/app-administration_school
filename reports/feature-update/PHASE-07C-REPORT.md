# Laporan Subfase 07c — Kop, Tanda Tangan, Stempel, Template PDF, dan Snapshot

Tanggal: implementasi dan verifikasi agen 2026-11-02 WIB. Branch: `main`. Baseline induk: `4713c70` (pencatatan DEC-34 APPROVED). Paket implementasi 07c: `2a78b7e` (40 file; 7.306 insertions/33 deletions). Paket dokumentasi dicatat pada checkpoint setelah commit dokumentasi.
Keputusan pemilik: **DEC-34 APPROVED** (D1–D11, 2026-11-02 WIB; pernyataan **"Saya setuju D1-D11"**); **DEC-11 CLOSED untuk lingkup 07c**; **DEC-13 tetap OPEN**.
Status teknis: **IMPLEMENTED — agent-verified (2026-11-02)**. Status review pemilik: **APPROVED (#1–#18, 2026-11-02 WIB)** berdasarkan pernyataan **"Pengujian nomor 1-18 sudah berhasil dan saya approved."**; agen tidak mengulang skenario; detail di `reports/feature-update/PHASE-07C-MANUAL-REVIEW.md`.
Rencana: `reports/feature-update/PHASE-07C-PLAN.md`. Checklist manual: `reports/feature-update/PHASE-07C-MANUAL-REVIEW.md`.

## 1. Outcome dan cakupan

Subfase 07c melengkapi modul persuratan Fase 07 dengan kop sekolah, penandatangan, tanda tangan/stempel, template PDF berversi, dan snapshot dokumen immutable:

- **Settings sekolah.** Tabel `letter_settings` 1:1 dengan `school_profiles` (unik, `version`) menyimpan kop (baris pemerintah, nama sekolah, alamat, kontak, tempat terbit), logo, penandatangan (nama, jabatan, label/nilai identifier), signature, dan stempel. Resolusi memakai tahun ajaran aktif (`AcademicSetting` → `school_profiles` → settings); readiness melaporkan `active_year`, `profile`, `header`, `signatory`, `signature`, `template`, `ready`, dan `missing`. Tanpa tahun aktif/profil/kop/penandatangan/signature, submit/issue typed ditolak `422`.
- **Aset privat.** Logo, signature, dan stempel disimpan di `attachments/letter-settings/{school_profile_id}/`, hanya PNG/JPG, maksimum 2 MB dan 2000×2000, di-re-encode menjadi PNG; tidak ada URL publik. Header/logo memakai `letters.manage`; penandatangan/signature/stempel dan aset mentah memakai `letters.sign`.
- **Surat bertipe.** Kolom additive `letters.letter_type`, `document_subject_type`, `document_subject_id`, `document_fields`. Tiga jenis: `student_active` (purpose 5–500), `student_mutation` (mutasi keluar aktif), dan `employee_duty` (uraian 10–2000, tanggal mulai/selesai, lokasi 5–200). Tipe/subjek immutable setelah create; fields hanya editable saat draft; surat umum 07b (`letter_type=null`) tidak berubah.
- **Template dan PDF.** Template Blade kode di `resources/views/letters/pdf/` dengan partial kop/tanda tangan, registry `config/letters.php` (`code`, `version`, `view`, label). Renderer `dompdf/dompdf v2.0.8`: A4 portrait, DejaVu Sans, remote resource dimatikan, gambar data URI, semua nilai di-escape. Tidak ada editor template atau HTML dari pengguna.
- **Issue atomik dan snapshot.** Saat `approved → issued`, di dalam transaksi `issue()` setelah nomor resmi: validasi readiness/eligibility, snapshot minimal PII, render PDF, simpan file privat, `letter_documents`, dan event. Kegagalan validasi `422` atau renderer `500` → rollback, surat tetap `approved`, nomor tidak dikonsumsi, file dibersihkan best-effort. Tidak ada reissue/regenerate; koreksi dilakukan dengan cancel + surat baru.
- **Akses dokumen.** Draft/review/approved typed mendapat preview PDF watermarked `PREVIEW — NOT ISSUED` tanpa nomor/counter/penyimpanan; issued/cancelled mengunduh PDF tersimpan; surat umum/legacy 07b, tipe salah, atau file hilang `404`; settings/subjek tidak valid saat preview `422`. Respons PDF tanpa `Content-Disposition` dan memakai `X-Document-Filename` karena Chrome memblokir `fetch` lintas origin berkredensial untuk header tersebut.
- **API dan UI.** Sembilan route baru (4 settings, 3 subject picker, 2 dokumen; total **37 route surat**). Halaman **Letter Settings** dan perluasan **Outgoing Letters** (form bertipe, subject picker, document card, filter Type/Has document, preview/download). Menu **Letters → Incoming/Outgoing/Dispositions/Letter Settings**; English, 390 px, keyboard/aria, konflik versi `409` + Reload Latest, tombol mengikuti ability.
- **Audit dan privasi.** `LetterDocument` immutable (menolak update/delete), snapshot tanpa NIK siswa/orang tua, alamat, atau kontak pribadi; API hanya mengekspos ringkasan dokumen. Allowlist `Letter` menambah `letter_type` tetapi tidak `document_fields`/`document_subject_id`; `LetterSettings` tidak mencatat alamat/kontak/nama/identifier/path; event `issued` typed hanya menambah `template_code`/`template_version`/`page_count`.
- **UAT terisolasi.** `.uat-phase07` dipakai ulang tanpa reset dengan perintah idempoten `prepare-documents`: 20 surat bertipe, 7 dokumen/12 halaman/7 PDF, nomor resmi 007–013, settings v2 siap, sementara 126 surat masuk, 32 disposisi, 14 pegawai, 123 siswa, 20 mutasi, dan 443 event tetap utuh. Server API 18025/SPA 14025 dihentikan setelah penerimaan pemilik 2026-11-02 (DEC-27); data `.uat-phase07` dipertahankan.

Tidak dikerjakan pada 07c (non-goals D1/D11): template CRUD/editor, DOCX/PDF-A, e-sign tersertifikasi/QR/OCR, email/WhatsApp, notifikasi, bulk generation, arsip/versioning Fase 09, retensi DEC-13, rekap DEC-12, surat izin Fase 08, ability/role baru, backfill PDF surat 07b/legacy, merge/deploy/force-push, migrasi database operasional/MariaDB, dan perubahan dependency/service/`.env` selain D2.

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File | Perubahan | Schema/izin |
|---|---|---|
| `database/migrations/2029_08_03_000001_create_letter_settings_table.php` | Tabel `letter_settings` 1:1 `school_profiles` (kop, logo, penandatangan, signature, stempel, versi) | Additive; FK restrict/nullOnDelete; tanpa soft delete |
| `database/migrations/2029_08_03_000002_create_letter_documents_table.php` | Tabel `letter_documents` (template, snapshot JSON, file privat, halaman, ukuran, generator) | Immutable; unique per surat; FK restrict |
| `database/migrations/2029_08_03_000003_add_document_fields_to_letters_table.php` | Kolom typed additive `letter_type`, `document_subject_type`, `document_subject_id`, `document_fields` + index | Additive; kolom lama tidak diubah |
| `app/Models/LetterSettings.php` | Settings, relasi profil, allowlist audit tanpa PII/path | Versi + aset privat |
| `app/Models/LetterDocument.php` | Dokumen immutable, snapshot, relasi surat, penolakan update/delete | Tanpa `Loggable` |
| `app/Models/Letter.php` | Relasi dokumen, konstanta tipe, scope typed, allowlist `letter_type` | Audit minimal PII |
| `app/Services/Letters/LetterSettingsService.php` | Resolusi tahun aktif/profil, readiness, update header/signatory, versi + `409` | `letters.manage`/`letters.sign` |
| `app/Services/Letters/LetterSettingsAssetService.php` | Validasi/penyimpanan/re-encode PNG, path privat, stream aset | Whitelist aset |
| `app/Services/Letters/LetterTemplateRegistry.php` | Registry template kode + versi + label | Tanpa CRUD |
| `app/Services/Letters/LetterDompdfRenderer.php` | Render PDF A4, font DejaVu Sans, data URI, remote off | Tanpa resource eksternal |
| `app/Services/Letters/LetterDocumentService.php` | Snapshot minimal PII, simpan dokumen/PDF, preview watermarked, download | Immutable; tanpa regenerasi |
| `app/Services/Letters/LetterSubjectPickerService.php` | Picker siswa aktif, mutasi keluar aktif, pegawai aktif tanpa PII | `letters.manage` + ability domain |
| `app/Services/Letters/LetterSubjectService.php` | Validasi eligibility subjek per tipe dan snapshot subjek | `422` tanpa mutasi |
| `app/Services/Letters/LetterSettingsService.php` (integrasi) | Readiness/validasi dipakai submit dan issue | `422` sebelum nomor |
| `app/Services/Letters/OutgoingLetterService.php` | Create/update typed, submit readiness, issue + snapshot/PDF dalam transaksi | Rollback + nomor tidak dikonsumsi |
| `app/Http/Controllers/LetterSettingsController.php` | Show, header, signatory, asset | Route matrix 07c |
| `app/Http/Controllers/LetterSubjectController.php` | Tiga subject picker | Ability domain + `letters.manage` |
| `app/Http/Controllers/OutgoingLetterController.php` | Preview/download dokumen + filter typed | `letters.view` |
| `app/Http/Requests/UpdateLetterHeaderRequest.php`, `UpdateLetterSignatoryRequest.php` | Validasi kop/logo/penandatangan/signature/stempel + versi | `422`/`409` |
| `app/Http/Requests/StoreOutgoingLetterRequest.php`, `UpdateOutgoingLetterRequest.php` | Validasi typed fields/subjek | Tipe/subjek immutable |
| `routes/api.php` | 9 route baru; total 37 route surat | `auth:sanctum` + `active` + ability |
| `config/letters.php` | Tipe, template registry, settings, aset, PDF | Tanpa `.env` utama |
| `resources/views/letters/pdf/*` (6 file) | Template tiga surat + partial kop/tanda tangan/footer | Escaped; DejaVu Sans |
| `tests/Feature/Letters/LetterSettingsTest.php` | 10 tes / 167 assertions | Matriks settings |
| `tests/Feature/Letters/LetterDocumentTest.php` | 21 tes / 744 assertions | Matriks dokumen/issue |
| `tests/Feature/Letters/CreatesLetterFixtures.php` | Fixture settings/subjek/dokumen | Tes saja |
| `tests/Feature/Academic/StudentMutationTest.php` | Filter route 06c dipersempit agar tidak menghitung route subjek 07c | Regresi, tanpa perubahan produk |
| `scripts/phase07-uat.php` | `prepare-documents`, 120 siswa/20 mutasi/surat bertipe, status 07c | Hanya `.uat-phase07` |
| `composer.json`, `composer.lock` | `dompdf/dompdf v2.0.8` + 4 transitive; tanpa upgrade/penghapusan lain | D2 |
| `vue-app/src/views/pages/public/LetterSettings.vue` | Halaman settings (readiness, kop/logo, penandatangan/aset) | Tombol mengikuti ability |
| `vue-app/src/views/pages/public/OutgoingLetters.vue` | Form typed, picker, document card, filter, preview/download | `letters.view`/`manage`/`sign` |
| `vue-app/src/router/index.js`, `vue-app/src/layout/AppMenu.vue` | Route Letter Settings + menu Letters | Guard `letters.view` |

Kontrak endpoint baru (detail di `docs/API-CONTRACTS.md`):

| Method | Endpoint | Izin | Catatan |
|---|---|---|---|
| GET | `/api/letters/settings` | `letters.view` | Tahun aktif/profil, settings tersanitasi, readiness, suggestions, permissions |
| POST | `/api/letters/settings/header` | `letters.manage` | Kop + logo multipart + `expected_version`; `422`/`409` tanpa mutasi |
| POST | `/api/letters/settings/signatory` | `letters.sign` | Penandatangan + signature/stamp + `expected_version`; re-encode PNG |
| GET | `/api/letters/settings/assets/{asset}` | `logo`: `letters.view`; `signature`/`stamp`: `letters.sign` | Stream aset privat, whitelist |
| GET | `/api/letters/outgoing/subjects/students` | `letters.manage` + `students.view` | Siswa aktif tahun aktif; tanpa NIK/alamat |
| GET | `/api/letters/outgoing/subjects/student-mutations` | `letters.manage` + `students.view` | Mutasi keluar aktif; tanpa alasan panjang |
| GET | `/api/letters/outgoing/subjects/employees` | `letters.manage` + `employees.view` | Pegawai aktif; tanpa NIK/alamat/kontak |
| GET | `/api/letters/outgoing/{letter}/document` | `letters.view` | PDF tersimpan issued/cancelled; `404` lainnya; tanpa regenerasi |
| GET | `/api/letters/outgoing/{letter}/document/preview` | `letters.view` | Preview watermarked draft/review/approved; tanpa nomor/penyimpanan |

Route `subjects/*` didaftarkan sebelum `/outgoing/{letter}` dan memakai `whereNumber('letter')`. Sembilan route incoming 07a, enam route disposisi, dan 13 route outgoing 07b tidak berubah.

## 3. Evidence

| ID | Metode / command | Fixture / peran | Expected | Actual | Status |
|---|---|---|---|---|---|
| E1 | `php artisan test tests/Feature/Letters/LetterSettingsTest.php` | SQLite in-memory | Matriks settings PASS | **10 passed / 167 assertions, 11,81 s** | PASS |
| E2 | `php artisan test tests/Feature/Letters/LetterDocumentTest.php` | SQLite in-memory | Matriks dokumen/issue PASS | **21 passed / 744 assertions** | PASS |
| E3 | Kedua berkas dijalankan ulang | SQLite in-memory | Tidak ada flake | **31 passed / 911 assertions, 59,98 s** | PASS |
| E4 | Regresi direktori `tests/Feature/Letters` | SQLite in-memory | 07a/07b/07c tetap lulus | **86 passed / 1.744 assertions, 109,17 s** (Incoming 20, Disposition 14, Outgoing 21, LetterSettings 10, LetterDocument 21) | PASS |
| E5 | `php artisan test` (suite penuh) | SQLite in-memory | Tidak ada regresi | **552 passed / 1 failed / 13 skipped / 6.235 assertions, 541,94 s**; kegagalan tunggal adalah filter route 06c yang menghitung route 07c — diperbaiki dan tes terkait lulus ulang **1 passed / 23 assertions (4,32 s)**; suite penuh tidak diulang karena tes drill MariaDB melanggar batas akses | PASS setelah perbaikan tes (suite penuh tidak diulang) |
| E6 | `./vendor/bin/pint --test` pada file berubah | — | Gaya kode | **28 file PASS** | PASS |
| E7 | `php artisan route:list --path=letters` | — | 37 route | **37 route** (9 incoming + 6 disposisi + 13 outgoing + 9 07c) | PASS |
| E8 | `npm run build` | — | Build produksi | **PASS 32,44 s**; warning chunk >500 kB sudah ada | PASS |
| E9 | `npx eslint` pada `LetterSettings.vue`, `OutgoingLetters.vue`, router, menu | — | Tanpa error | **0 error**; 623 warning Prettier | PASS |
| E10 | Pemeriksaan `composer.lock` | — | Hanya D2 | **dompdf v2.0.8 + 4 transitive**, tanpa penghapusan/upgrade lain | PASS |
| E11 | `php scripts/phase07-uat.php prepare-documents` + status | `.uat-phase07` SQLite | Fixture idempoten, 07a/07b utuh | **20 surat bertipe, 7 dokumen/12 halaman/7 PDF, nomor 007–013, settings v2; 126 incoming/32 disposisi/443 event utuh** | PASS |
| E12 | Browser/API Chrome DevTools MCP | operator/kepsek/waka sintetis | Settings, preview, PDF, 390 px, console | **Readiness/pemisahan izin settings, preview watermarked, PDF issued 2 halaman `#000192` nomor `013`, 390 px tanpa overflow, 0 console error** | PASS (terbatas pada daftar ini) |
| E13 | Checklist manual `PHASE-07C-MANUAL-REVIEW.md` | 18 skenario | Dijalankan pemilik | **Approved #1–#18 (2026-11-02 WIB)** | APPROVED |

### Addendum hasil suite penuh

- Run suite penuh 2026-11-02: **552 passed / 1 failed / 13 skipped / 6.235 assertions (541,94 s)**. Tiga belas skip adalah tes integrasi MariaDB yang memerlukan `SCHOOL_04A_DB_PASSWORD`/file, bukan kegagalan.
- Kegagalan tunggal: `StudentMutationTest` → "mutation and correction routes are registered with the expected middleware". Penyebabnya filter tes memakai substring `mutations`, sehingga route baru 07c `/api/letters/outgoing/subjects/student-mutations` ikut terhitung (11, bukan 10). Filter dipersempit ke route `api/academic/mutations` dan `api/students/{id}/(mutations|locked-corrections)`; tes terkait lulus ulang **1 passed / 23 assertions (4,32 s)**. Tidak ada perubahan perilaku produk.
- Run tersebut juga menjalankan tes baseline `Tests\Feature\Backup\MariaDbIntegrationTest` yang membaca `school_access_uat` via `mysqldump` dan restore ke `school_restore_drill_uat` (lihat batas akses di §5). Karena batas tersebut, **suite penuh tidak dijalankan ulang**; seluruh 552 tes lain sudah lulus pada run yang sama dan satu-satunya kegagalan telah diperbaiki serta lulus ulang. Tidak ada klaim suite penuh PASS tanpa catatan ini.

## 4. UAT manual dan fixture

Lingkungan: `.uat-phase07` (SQLite), API `php -S 127.0.0.1:18025 -t public scripts/phase07-uat.php` (PID 8340), SPA Vite `127.0.0.1:14025` (PID 22456), cookie `school_phase07_session`. Password hanya di `.uat-phase07/account-password.txt` dan tidak pernah masuk dokumen. Perintah restart/status ada di `PHASE-07C-MANUAL-REVIEW.md`.

Status fixture per 2026-11-02:

- **Surat keluar 66** — 22 draft/11 review/14 approved/11 issued/8 cancelled.
- **Surat bertipe 20** — 12 `student_active`/4 `student_mutation`/4 `employee_duty`; workflow 6 draft/3 review/4 approved/5 issued/2 cancelled; ID 173–192.
- **Nomor resmi 007–013** — issued: 181=007, 182=008, 183=009, 187=011, 192=013 (2 halaman); cancelled-after-issue: 184=010, 188=012; counter tahun 2026 = 13.
- **Dokumen 7** — 12 halaman, 7 PDF, semua `letter_documents` immutable.
- **Settings v2 siap** — sekolah `SMA NEGERI 1 UAT SINTETIS`, baris pemerintah `PEMERINTAH PROVINSI UAT SINTETIS`, alamat `Jl. Sintetis No. 1, Jakarta Utara UAT`, tempat terbit `Jakarta`, penandatangan `UAT Kepala Sekolah / Kepala Sekolah`, label identifier NIP tanpa nilai; logo/signature/stempel sintetis.
- **Data lain utuh** — 126 surat masuk (115 aktif/11 void), 32 disposisi, 14 pegawai (12 aktif), 123 siswa/103 keanggotaan aktif/20 mutasi, 443 event, 68 baris lampiran/69 file.

Bukti browser/API yang dijalankan agen: readiness settings dan pemisahan izin (header/logo vs signatory/aset), preview watermarked, download PDF issued 2 halaman `#000192` bernomor `013`, tampilan 390 px tanpa overflow halaman, dan **0 console error**. Skenario manual lainnya (termasuk edit typed, submit/approve/issue dari UI, snapshot immutability, legacy `404`, ineligible subject, audit, keyboard) diserahkan kepada pemilik melalui checklist #1–#18; agen tidak mengklaim PASS untuk skenario yang tidak dijalankan.

## 5. Risiko, keputusan, dan batas

- **D2 dipatuhi.** Hanya `dompdf/dompdf v2.0.8` + empat transitive (`masterminds/html5 2.12.0`, `phenx/php-font-lib 0.5.6`, `phenx/php-svg-lib 0.5.4`, `sabberworm/php-css-parser v8.9.0`) yang ditambahkan; tidak ada upgrade/penghapusan package lain.
- **Render sinkron.** PDF dibuat di request issue; belum ada queue/worker. Volume besar dan batas waktu render belum diuji (NOT RUN).
- **Snapshot dan retensi.** Snapshot minimal PII membekukan surat issued; `letter_documents` tidak dapat diubah/dihapus. Retensi/pemusnahan file tetap **OPEN (DEC-13)**.
- **Tanpa regenerasi/backfill.** File PDF hilang setelah issue tidak diregenerasi; surat umum/legacy 07b tetap `404` tanpa PDF.
- **CORS PDF.** `Content-Disposition` dihilangkan dan nama file diekspos lewat `X-Document-Filename`; UI memakai atribut `download`. Unduhan lampiran 07a/07b yang masih memakai `Content-Disposition` tetap gagal lintas origin di Chrome — temuan di luar scope, tidak diperbaiki pada 07c.
- **Batas akses suite penuh.** `Tests\Feature\Backup\MariaDbIntegrationTest` (baseline lama, bukan perubahan 07c) membaca `school_access_uat` dan me-restore ke `school_restore_drill_uat` saat MariaDB root lokal dapat dijangkau; run suite penuh pertama menjalankannya. Data sumber tidak diubah; target adalah DB drill disposable milik tes. Sesuai instruksi pemilik, suite penuh tidak diulang; tes ini perlu digating (opt-in) sebelum suite penuh berikutnya.
- **UAT `php -S` single-threaded.** Tidak merepresentasikan konkurensi produksi; race/dua tab nyata tetap NOT RUN. `409` diuji lewat versi basi/replay, bukan dua proses paralel.
- **Server UAT dihentikan setelah penerimaan 2026-11-02** (DEC-27): PID 8340 (API) dan 22456/8808 (SPA) diverifikasi command line, port 14025/18025 terverifikasi bebas, `.uat-phase07` dipertahankan.

## 6. Sinkronisasi dokumen

- `docs/DATABASE.md` — kolom typed `letters`, `letter_settings`, `letter_documents`, migration 07c, dependency, batas isolasi.
- `docs/API-CONTRACTS.md` — 9 endpoint 07c, typed fields, transaksi issue, header PDF, audit, UI, non-goals, total 37 route.
- `docs/ACCESS-CONTROL.md` — route matrix 07c, pemisahan aset mentah, readiness gate, batas objek/state.
- `docs/OPERATIONS.md` — resolusi settings, aset privat/re-encode, permission/versioning, template, transaksi PDF/snapshot/rollback, header, backup, dependency, UAT, DEC-13.
- `docs/REQUIREMENTS.md` — FR-09 selesai untuk lingkup 07; review #1–#18 APPROVED; DEC-11 CLOSED untuk 07c; DEC-13 OPEN.
- `docs/DECISIONS.md` — DEC-34 IMPLEMENTED agent-verified dan review manual APPROVED #1–#18; DEC-11 CLOSED untuk 07c.
- `docs/TESTING.md` — bukti 07c, NOT RUN, temuan batas akses.
- `CHANGELOG.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-07-LETTERS.md`, `PHASE-07-PLAN.md`, `PHASE-07C-PLAN.md` §12, `INDEX.md`, `HANDOFF.md`, `PROJECT_CONTEXT.md` — status implementasi/review dan langkah berikutnya.

## 7. Langkah berikutnya

1. ~~Pemilik menjalankan `PHASE-07C-MANUAL-REVIEW.md` #1–#18 pada server UAT 14025/18025.~~ **Selesai — APPROVED #1–#18, 2026-11-02 WIB.**
2. ~~Catat approval pada commit dokumentasi terpisah dan hentikan server UAT.~~ **Selesai — approval dicatat di commit dokumentasi; server dihentikan sesuai DEC-27 dengan verifikasi PID/command line; `.uat-phase07` dipertahankan.**
3. Sebelum suite penuh berikutnya, gate `MariaDbIntegrationTest` agar tidak mengakses `school_access_uat` tanpa opt-in eksplisit.
4. Fase berikutnya (termasuk 07 lanjutan/08) memerlukan instruksi dan rencana terpisah. Jangan merge/deploy/force-push, migrasi DB operasional/MariaDB, mengubah service/`.env`, atau menambah dependency lain tanpa otorisasi.
