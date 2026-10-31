# Laporan Subfase 05c — Pemeriksaan Kelengkapan Siswa dan Pegawai

Tanggal: implementasi dan verifikasi agen 2026-10-31 WIB. Branch: `main`. Baseline: `037b1c6` (pencatatan DEC-28 APPROVED); commit implementasi 05c: **`c2fddfa`**.
Keputusan pemilik: **DEC-28** (APPROVED 2026-10-31 WIB; D1–D7, pernyataan "Saya setuju D1-D7.").
Status teknis: **IMPLEMENTED — agent-verified (2026-10-31)**. Status review pemilik: **APPROVED 2026-10-31 WIB (checklist #1–#14)** berdasarkan pernyataan pemilik **"Pengujian nomor 1-14 sudah berhasil dan saya approved."**; agen tidak mengulang skenario.
Rencana: `reports/feature-update/PHASE-05C-PLAN.md`. Checklist manual: `reports/feature-update/PHASE-05C-MANUAL-REVIEW.md`.

## 1. Outcome dan cakupan

Subfase 05c menambahkan:

- **Pemeriksaan kelengkapan siswa**: denominator **placed non-archived** pada tahun ajaran terpilih; field wajib NIK, NIS, NISN, Gender, Birthplace, Birthdate, Religion; siswa unplaced dengan NIK missing/invalid menjadi `blocked` **di luar persentase**; siswa archived dan unplaced lain tidak dievaluasi.
- **Pemeriksaan kelengkapan pegawai**: denominator **active non-archived** (`exit_date` null atau belum lewat tanggal acuan); field wajib NIK, Employee Type, Employee Status, Employment, Gender, Religion, Marital Status, District, Employment Start Date (TMT); `Pendidik` wajib NUPTK + Subject; `Tenaga Kependidikan` dengan Subject = `invalid`; separated/archived excluded.
- **Kandidat duplikat tanpa auto-merge**: siswa (nama+birthdate, integritas NIK/NIS/NISN), pegawai (NUPTK, nama+date_of_birth, integritas NIK). Selalu pasangan record; tidak pernah nama saja.
- **Status review operator**: tabel persisten `data_quality_reviews`, status `open`/`acknowledged`/`ignored` dengan alasan terbatas, versi review + `409`, terikat versi record dan tahun ajaran (siswa). Review **tidak** mengubah data domain maupun persentase.
- **Enam endpoint** `/api/data-quality/*` tanpa ability baru (reuse `students.view/manage` dan `employees.view/manage`), payload bebas NIK/alamat/telepon/medis.
- **UI Data Quality** English dengan tab Students/Employees, kartu denominator, tabel field, filter type/field/status, pencarian, paginasi, dialog review, dan **deep link** ke halaman Students/Teachers/Employees yang sudah ada (`?openStudent=`/`?openEmployee=`). Koreksi tetap melalui form lama yang teraudit.
- **Dukungan OR ability** pada guard router dan menu (`routeAbility.mjs` + `authStore.canAny`) tanpa mengubah perilaku ability tunggal.
- **Fixture UAT** 120 record `UAT-DQ` pada `.uat-phase05` melalui command guarded/idempotent `prepare-data-quality`.

Tidak dikerjakan pada 05c: rekap sekolah (DEC-12 PROPOSED), auto-merge, koreksi massal, edit inline, ekspor daftar issue, XLSX, ability baru, perubahan kontrak 05a/05b, dan Fase 06.

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File/endpoint | Perubahan | Izin/schema/payload |
|---|---|---|
| `app/Services/DataQuality/StudentCompletenessProfile.php` | Profil v1: field wajib, advisory, field duplikat | Tanpa schema; label English |
| `app/Services/DataQuality/EmployeeCompletenessProfile.php` | Profil v1: field wajib semua pegawai + tambahan Pendidik; label tipe English | `Pendidik`/`Tenaga Kependidikan` → Teacher/Non-teaching Employee |
| `app/Services/DataQuality/IssueKey.php` | SHA-256 deterministik domain/type/field/year/subject/versi/related | Tanpa nilai pribadi; perubahan record → key baru |
| `app/Services/DataQuality/StudentCompletenessInspector.php` | Denomintor placed, missing/invalid/blocked, duplikat pasangan, review attach, filter/sort/paginate, `hasIssue()` | `summary()`/`issues()`; default `per_page` 20 |
| `app/Services/DataQuality/EmployeeCompletenessInspector.php` | Denominator active, aturan Pendidik/Tenaga Kependidikan, duplikat, review attach, `hasIssue()` | Tanpa `school_year_id` |
| `app/Services/DataQuality/ReviewService.php` | Validasi issue aktif, versi record, versi review, upsert transaksi + `lockForUpdate` | `409`/`404`; audit via model |
| `app/Models/DataQualityReview.php` | Model review + `Loggable` allowlist audit | Tanpa nama/NIK/NUPTK/alamat di audit |
| `app/Http/Controllers/StudentDataQualityController.php` | summary/issues/review siswa | `students.view` / `students.manage` |
| `app/Http/Controllers/EmployeeDataQualityController.php` | summary/issues/review pegawai | `employees.view` / `employees.manage` |
| `app/Http/Requests/DataQuality*Request.php`, `Review*DataQualityIssueRequest.php`, `Concerns/DataQualityReviewRules.php` | Validasi filter, paginasi, payload review, alasan ignore | `422` English; `reason_code` wajib saat ignored; `verified_distinct` hanya duplicate |
| `config/data_quality.php` | `profile_version`, `per_page`, `default_per_page`, `review_reasons` | `[10,20,50,100]`; alasan `pending_document`, `source_unavailable`, `verified_distinct`, `not_applicable` |
| `database/migrations/2029_07_29_000001_create_data_quality_reviews_table.php` | Tabel baru `data_quality_reviews` (additive) | `issue_key` unique; index domain/status dan subject; FK `reviewed_by` nullOnDelete |
| `routes/api.php` | Enam route data quality | Di luar resource yang ada |
| `scripts/phase05-uat.php` | Command `prepare-data-quality` guarded/idempotent | Hanya `.uat-phase05`; 120 record `UAT-DQ`; tanpa reset |
| `tests/Feature/DataQuality/DataQualityApiTest.php` | 15 tes / 239 assertions | Lihat Evidence |
| `vue-app/src/views/pages/public/DataQuality.vue` | Halaman tab Students/Employees + review | English; tanpa edit inline |
| `vue-app/src/views/pages/public/DataQualityReviewDialog.vue` | Dialog acknowledge/ignore/reopen | Alasan wajib saat ignore; 409 → refresh |
| `vue-app/src/views/pages/public/dataQualityParams.mjs` | Parameter, payload review, label, deep link | Unit test Node |
| `vue-app/src/router/routeAbility.mjs`, `router/index.js`, `layout/AppMenu.vue`, `stores/authStore.js` | Dukungan ability array (OR) + route/menu Data Quality | `['students.view','employees.view']`; string tunggal tetap sama |
| `vue-app/src/views/pages/public/Student.vue`, `EmployeeDirectory.vue` | Deep link additive `?openStudent=`/`?openEmployee=` | Perilaku halaman lama dipertahankan |
| `vue-app/tests/data-quality-params.test.mjs`, `route-ability.test.mjs` | 13 + 4 tes Node | Lihat Evidence |

Kontrak endpoint (detail di `docs/API-CONTRACTS.md`):

| Method | Endpoint | Izin |
|---|---|---|
| GET | `/api/data-quality/students/summary?school_year_id=` | `students.view` |
| GET | `/api/data-quality/students/issues?school_year_id=&type=&field=&status=&search=&page=&per_page=` | `students.view` |
| PUT | `/api/data-quality/students/reviews/{issueKey}` | `students.manage` |
| GET | `/api/data-quality/employees/summary` | `employees.view` |
| GET | `/api/data-quality/employees/issues?type=&field=&status=&search=&page=&per_page=` | `employees.view` |
| PUT | `/api/data-quality/employees/reviews/{issueKey}` | `employees.manage` |

- **Issue**: `issue_key`, `type` (`missing`/`invalid`/`duplicate`/`blocked`), `field`, `field_label`, `message` (tanpa nilai field), `subject`, `related` (duplikat), `review` (`status`, `reason_code`, `version`, `reviewed_at`).
- **Review payload**: `status`, `reason_code`, `issue_type`, `field`, `subject_id`, `subject_version`, `related_subject_id`, `related_version`, `version`, serta `school_year_id` (wajib siswa, **prohibited** pegawai).
- **Summary**: `profile_version`, `as_of`, `school_year`, `denominator_basis`, `population`, `records`, `fields`, `duplicates`, `reviews` (`open`, `acknowledged`, `ignored`, `unresolved`).
- **Populasi siswa**: `active_students`, `placed`, `unplaced`, `not_evaluated`, `archived_excluded`; **pegawai**: `active`, `separated`, `archived_excluded`.
- **Status filter**: default `open`; `all` mencakup ignored. `per_page` hanya 10/20/50/100; nilai lain `422`.

## 3. Evidence

| ID | Metode / command | Fixture / peran | Expected | Actual | Status |
|---|---|---|---|---|---|
| E1 | `php artisan test tests/Feature/DataQuality` | SQLite in-memory + isolated testing config | Semua skenario data quality PASS | **15 passed / 239 assertions, 9,90 s** | PASS |
| E2 | `php artisan test` (suite penuh) | SQLite in-memory + MariaDB disposable yang sudah tersedia | Tidak ada regresi | **417 passed / 13 skipped / 3.826 assertions, 260,36 s** | PASS |
| E3 | `./vendor/bin/pint --test` 21 file PHP berubah/baru | — | Gaya bersih | **PASS 21 files** | PASS |
| E4 | `node --test tests/data-quality-params.test.mjs tests/route-ability.test.mjs` | Node 24.18.0 | Semua unit test PASS | **17 tests, 0 fail, 260,49 ms** | PASS |
| E5 | `npm run build` | Vite 6 | Build sukses | **PASS 38,18 s**; warning chunk >500 kB sudah ada sebelumnya | PASS |
| E6 | `npx eslint` pada 9 file frontend berubah | ESLint read-only | 0 error | **0 error**; file 05c baru tanpa temuan; file lama hanya warning Prettier/CRLF yang sudah ada (3.281 warning total) | PASS (scoped) |
| E7 | `php artisan route:list --path=data-quality` | — | Enam route terdaftar | 6 route: 3 students + 3 employees; method/izin sesuai kontrak | PASS |
| E8 | `php scripts/phase05-uat.php prepare-data-quality` (dua kali) | `.uat-phase05` SQLite terisolasi | Fixture 60+60; run kedua tidak menambah record | `Created this run: 0 students, 0 employees`; 60 siswa (50 placed), 60 pegawai (50 active, 10 separated) | PASS (idempotent) |
| E9 | `php scripts/phase05-uat.php status` | `.uat-phase05` | Isolasi dan jumlah benar | Guard pass; Students 469, Employees 260 (133 teachers/127 staff); DQ 60 (50 placed)/60 (50 active, 10 separated); ability `students.import` present; 8 import batches | PASS |
| E10 | Query read-only SQLite UAT | `.uat-phase05/database.sqlite` | Migration + review rows | `2029_07_29_000001_create_data_quality_reviews_table` applied; 3 review rows (hasil UAT, status dikembalikan Open) | PASS |
| E11 | Browser DOM — tab Students (Chrome DevTools MCP, `127.0.0.1:14023`) | `uat.operator` | Denominator, issue, duplikat, review tampil | Placed 153; Active 469; Unplaced 316; Not evaluated 306; Archived 0; Complete 30; Incomplete 123 (19,6%); Field findings 126 (118 missing/8 invalid); Blocked NIK 10; Duplicate 8 pasangan/16 record; Review 144 Open; 144 matching | PASS |
| E12 | Browser DOM — tab Employees | `uat.operator` | Denominator active dan aturan tipe | Active 250; Separated 10; Archived 0; Complete 30; Incomplete 220 (12,0%); Field findings 320 missing; NIK 213 missing; NUPTK 107 missing dari 133 teacher; Subject 250 complete; Duplicate 5 pasangan/10 record; Review 325 Open | PASS |
| E13 | Browser review lifecycle | `uat.operator` | Acknowledge → Ignore dengan alasan → Reopen; versi naik | Student 021: v1 Open → v2 Ignored (Pending document) → v3 Open; Teacher 021 ack → reopen; duplicate Teacher 034 Ignore (Verified as distinct) → reopen v3. Counter akhir dikembalikan: students 144 Open, employees 325 Open | PASS |
| E14 | Browser deep link | `uat.operator` | Membuka record yang benar di halaman lama | `/public/students?openStudent=430` → UAT-DQ Student 021 (detail, NIK "-"); related duplicate → `/public/teachers?openEmployee=242` (Teacher 035); `/public/employees?openEmployee=228` (Staff 021, Non-teaching Employee); label English | PASS |
| E15 | Browser responsive 390×844 (emulate) | `uat.operator` | Tanpa overflow halaman; kontrol terjangkau | Tidak ada overflow level halaman; tabel issue scroll horizontal (scrollWidth 1028 / clientWidth 261); dialog review 359 px di viewport 390 px; tombol aksi terjangkau | PASS (DOM measurement) |
| E16 | Browser console | — | Tidak ada error | **0 console message** | PASS |

**NOT RUN (tidak dikonversi menjadi PASS):**

- Skenario dua tab / review usang `409` lewat browser — `409` tertutup tes otomasi (versi review usang, versi record berubah, issue key tidak cocok), tetapi alur dua tab tidak dijalankan agen.
- MariaDB/InnoDB untuk query data quality, konkurensi review, dan rencana eksekusi pada data besar; suite penuh memakai SQLite untuk domain ini.
- Uji beban/performance scan ribuan record dan dataset eksternal.
- Screenshot/visual regression berbasis file gambar; bukti browser berupa snapshot DOM dan pembacaan UI, bukan artefak screenshot yang dikomit.
- Rekap sekolah (DEC-12 PROPOSED), XLSX, ekspor daftar issue, dan Fase 06.

## 4. UAT manual

Environment: `.uat-phase05` SQLite terisolasi, SPA `http://127.0.0.1:14023`, API `http://127.0.0.1:18023`, cookie `school_phase05_session`, launcher `laravel-api/scripts/phase05-uat.php`. Server SPA (PID 10336) dan API (PID 19196) **masih berjalan** untuk review pemilik; hentikan setelah penerimaan sesuai DEC-27.

Akun review: `uat.operator` (manage siswa/pegawai, dapat menulis review) dan `uat.kepsek` (view-only, tidak dapat menulis review). Password hanya berada di runtime `.uat-phase05` yang diabaikan; tidak dicetak di dokumen.

Fixture `UAT-DQ` (guarded + idempotent, data lama tidak di-reset):

- 60 siswa: 20 placed lengkap, 20 placed dengan gap terkontrol, 10 unplaced tanpa NIK (blocked), 10 siswa dalam 5 pasangan duplikat nama+tanggal lahir.
- 60 pegawai: 20 active lengkap, 20 active dengan gap, 10 dalam 5 pasangan duplikat NUPTK, 10 separated tanpa NIK.
- Verifikasi tally: student issues 35 (`blocked:nik` 10, `duplicate:name_birthdate` 5, `invalid:nik` 5, `missing:birthdate` 5, `missing:nik` 5, `missing:nisn` 5); employee issues 25 (`duplicate:nuptk` 5, `missing:nik` 13, `missing:nuptk` 7).
- Jumlah populasi setelah fixture: 469 siswa / 260 pegawai (133 teacher, 127 staff); DQ siswa 60 (50 placed), DQ pegawai 60 (50 active, 10 separated).

Checklist rinci ada di `PHASE-05C-MANUAL-REVIEW.md` (14 skenario). Ringkasnya: denominator dan blocked vs incomplete; filter/pencarian/paginasi; duplikat tanpa merge; deep link dan koreksi lewat form lama; acknowledge/ignore/reopen; akses view-only; ringkasan pegawai; tautan Teacher vs Employee; `409` versi usang; privasi respons; tampilan 390 px; regresi halaman lama; penghentian server setelah penerimaan.

## 5. Risiko dan keputusan

- **Definisi kelengkapan** mengikuti DEC-28 D2/D3 dan ditampilkan eksplisit di UI; persentase selalu disertai denominator. Perubahan profil di masa depan harus menaikkan `profile_version`.
- **`blocked` bukan error data**: siswa unplaced tanpa NIK valid berada di luar persentase dan dihitung terpisah; UI menjelaskan artinya.
- **Duplikat adalah kandidat**: tidak ada merge/auto-fix; record arsip dan pegawai separated tidak masuk kandidat.
- **Review bukan koreksi data**: status review tidak mengubah persentase dan tidak menyembunyikan issue baru; `IssueKey` mengikat versi record dan tahun ajaran sehingga koreksi membuka temuan baru.
- **Konsistensi data UAT**: fixture hanya menambah record bertanda `UAT-DQ`; run kedua membuat 0 record; tidak ada reset. Tally diverifikasi sebelum laporan.
- **Privasi**: pesan issue tidak menyalin nilai field; payload daftar tidak memuat NIK/alamat/telepon/medis; audit model hanya metadata temuan. Tes negatif mengunci perilaku ini.
- **Bug yang ditemukan dan diperbaiki saat verifikasi**: validasi review awalnya hanya memeriksa halaman pertama daftar issue (default 20), sehingga acknowledge issue di luar halaman pertama menghasilkan `409` palsu. Diperbaiki dengan `hasIssue()` pada kedua inspector (memeriksa seluruh hasil analisis) dan dua tes regresi; suite data quality tetap PASS.
- **Batas**: scan memakai `chunkById(500)`; dataset sekolah normal. Uji beban belum dijalankan dan dicatat NOT RUN.
- **UAT lifecycle**: server 14023/18023 **dihentikan 2026-10-31** setelah pemilik menerima checklist (DEC-27). Command line PID 10336 (Vite) dan 19196 (PHP) diverifikasi sebelum `taskkill /T /F`; port terverifikasi bebas; Apache 80/443 dan MariaDB 3306 tetap berjalan; data `.uat-phase05` dipertahankan.

## 6. Sinkronisasi dokumen

Diperbarui: `docs/API-CONTRACTS.md` (enam endpoint, parameter, bentuk issue/review/summary), `docs/ACCESS-CONTROL.md` (reuse view/manage; review write = manage; tanpa ability baru), `docs/DATABASE.md` (tabel `data_quality_reviews`), `docs/REQUIREMENTS.md` (FR-06: kelengkapan tersedia; rekap sekolah OPEN), `docs/TESTING.md` (bukti dan NOT RUN), `docs/DECISIONS.md` (catatan implementasi DEC-28), `CHANGELOG.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-05-IMPORT-EXPORT.md`, `reports/feature-update/PHASE-05-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `PROJECT_CONTEXT.md`, laporan ini, dan checklist manual.

Commit/push dilakukan agar pemilik dapat meninjau implementasi di GitHub; commit/push bukan penerimaan manual. Penerimaan checklist #1–#14 dicatat pada commit dokumentasi tersendiri berdasarkan pernyataan pemilik. Tidak ada merge/deploy/force-push, perubahan `.env` utama, dependency/lockfile, service, DB terlarang, atau migrasi database operasional/MariaDB.

## 7. Langkah berikutnya

1. ~~Pemilik meninjau `PHASE-05C-MANUAL-REVIEW.md` pada `.uat-phase05`.~~ **Selesai 2026-10-31 WIB: seluruh #1–#14 APPROVED** berdasarkan pernyataan pemilik.
2. ~~Setelah pernyataan penerimaan, checklist dicatat pada commit dokumentasi tersendiri; server UAT dihentikan.~~ **Selesai**: penerimaan dicatat pada commit dokumentasi tersendiri; server 14023/18023 dihentikan, port bebas, data `.uat-phase05` dipertahankan.
3. Rekap sekolah menunggu contoh format (DEC-12 PROPOSED) dan instruksi terpisah; jangan mulai rekap atau Fase 06 tanpa instruksi pemilik.
