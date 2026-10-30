# Laporan Subfase 05b — Impor Pegawai dan Pilihan Kolom Ekspor

Tanggal: implementasi 2026-10-30 WIB; dokumentasi 2026-10-31 WIB; penerimaan pemilik 2026-10-31 WIB. Branch: `main`. HEAD sebelum paket 05b: `4f1fec0` (dokumentasi rencana 05b); implementasi dikomit sebagai **`ad74309`**, dokumentasi implementasi sebagai **`c5afdc4`**, dokumentasi penerimaan sebagai **`c19c7f0`**, semuanya di-push ke `origin/main` agar dapat direview di GitHub.
Keputusan pemilik: **DEC-26** (APPROVED 2026-10-30 WIB; D1–D7, pernyataan "Saya setuju D1-D7").
Status teknis: **IMPLEMENTED — agent-verified (2026-10-30/31)**. Status review pemilik: **APPROVED 2026-10-31 WIB — checklist #1–#14** per pernyataan pemilik.
Rencana: `reports/feature-update/PHASE-05B-PLAN.md`. Checklist manual: `reports/feature-update/PHASE-05B-MANUAL-REVIEW.md`.

## 1. Outcome dan cakupan

Subfase 05b menambahkan:

- **Impor pegawai CSV end-to-end** memakai mesin impor bersama: template English 22 kolom, preview tanpa mutasi, commit **all-or-nothing** dalam satu transaksi + `lockForUpdate`, laporan error privat `Row,Field,Message,NIK,NUPTK`.
- **Mode update berbasis NIK**: NIK wajib pada update dan opsional pada insert; NIK tidak dapat diubah; sel kosong = nilai tersimpan dipertahankan; validasi memakai gabungan nilai lama + sel terisi.
- **Aturan domain pegawai** yang sama dengan form (`App\Support\EmployeeRules`): NIK 16 digit string unik termasuk arsip; Subject wajib untuk `Pendidik` dan prohibited untuk `Tenaga Kependidikan`; perpindahan tipe ke Non-teaching mengosongkan Subject.
- **Resolusi referensi aman**: Regency + District berpasangan; District dicari dalam Regency; Subject/ District ambigu atau tidak cocok → error baris, bukan pilihan acak.
- **Ability `employees.import`** hanya untuk `admin-sistem` dan `operator-sekolah` (migration additive `2029_07_28_000001`); template cukup `employees.view`.
- **Pilihan kolom ekspor pegawai**: registry 10 kolom operasional (default, sama dengan 04b) + 15 kolom PII; PII hanya untuk `employees.manage`; endpoint `GET /api/employees/export/options`; permintaan PII tanpa izin → `403`; kolom tak dikenal → `422`.
- **UI English** di direktori Teachers/Employees: tombol Import CSV (sesuai ability, tersembunyi di mode arsip) dan dialog Export dengan pemilihan kolom.

Tidak dikerjakan pada 05b: rekap sekolah (05c, menunggu contoh format DEC-12), XLSX, job antrean, pembersihan retensi otomatis 30 hari (manual), impor akun pegawai, dan perubahan schema/tabel domain.

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File/endpoint | Perubahan | Permission/schema/payload |
|---|---|---|
| `app/Services/Imports/ImportService.php` | Mesin impor abstrak hasil ekstraksi 05a: parse CSV/BOM/delimiter, batas, hash, TTL, preview, validasi baris, commit atomik, error CSV, sanitasi formula | Kontrak siswa 05a tidak berubah; batch `domain` + `created_by` |
| `app/Services/EmployeeImportService.php` | Subclass domain pegawai: 22 kolom, resolusi referensi, Subject aktif/ambigu, Regency+District, aturan update parsial, commit insert/update | Menulis `employees` + audit; NIK kunci update |
| `app/Services/StudentImportService.php` | Menjadi subclass mesin bersama | Output/endpoint siswa tetap; regresi 18/97 PASS |
| `app/Support/EmployeeRules.php` | Satu sumber aturan form + impor | NIK/NUPTK/no_kk/NISN, Subject, tanggal, pesan English |
| `app/Http/Requests/Concerns/EmployeeProfileRules.php` | Delegasi ke `EmployeeRules` | Perilaku form 04b dipertahankan |
| `app/Http/Requests/StoreEmployeeImportRequest.php` | Validasi unggahan | CSV/TXT ≤ `imports.employees.max_size_kb`; mode insert/update; izin `employees.import` |
| `app/Http/Controllers/ImportController.php` | Aksi employee template/import/show/commit/errors; batch domain-aware | Template `employees.view`; sisanya `employees.import`; 404 lintas pengguna/domain |
| `app/Support/EmployeeExportColumns.php` | Registry kolom + resolver + select | 10 default, 15 sensitif, urutan registry, relasi |
| `app/Http/Controllers/EmployeeExportController.php` | `options()` + `columns` pada export | 403 PII tanpa manage; 422 kolom tak dikenal; 422 batas 10.000; BOM/nosniff/formula |
| `app/Support/AbilityCatalog.php` + migration `2029_07_28_000001` | Ability `employees.import` | `admin-sistem`, `operator-sekolah`; `down()` melepas |
| `config/imports.php` | Blok `employees` | 2.000 baris, 5.120 KB, TTL 120 menit, retensi 30 hari, preview 50, error 200 |
| `routes/api.php` | 5 route impor pegawai + `employees/export/options` | Urutan route sebelum resource `{employee}` |
| `tests/Feature/Import/EmployeeImportTest.php` | 28 tes / 189 assertions | Lihat Evidence |
| `tests/Feature/Employee/EmployeeExportColumnsTest.php` | 15 tes / 60 assertions | Lihat Evidence |
| `vue-app/src/views/pages/public/EmployeeImportDialog.vue` | Wizard Import English | Upload/preview/commit/error/template; 409 → invalid |
| `vue-app/src/views/pages/public/EmployeeExportDialog.vue` | Dialog pilihan kolom | Opsi server, Defaults/All/Clear, unduhan blob, error blob JSON |
| `vue-app/src/views/pages/public/employeeImportParams.mjs` | Validasi klien + FormData | `.csv`, kosong, >5 MB, mode fallback insert |
| `vue-app/src/views/pages/public/employeeDirectoryParams.mjs` | `buildEmployeeExportParams(filters, columns)` | Dedupe/trim key; `columns` hanya bila tidak kosong |
| `vue-app/src/views/pages/public/EmployeeDirectory.vue` | Integrasi dialog + tombol | Export selalu untuk `employees.export`; Import untuk `employees.import` dan bukan arsip |

Kontrak endpoint (detail di `docs/API-CONTRACTS.md`):

- `GET /api/imports/employees/template` (`employees.view`) → `employee-import-template.csv`.
- `POST /api/imports/employees` (`employees.import`) → `201` `{token, domain, mode, status, counts, preview, errors}`.
- `GET /api/imports/employees/{batch}`, `POST .../commit`, `GET .../errors` (`employees.import`, hanya pembuat batch).
- `GET /api/employees/export/options` (`employees.view` + `employees.export`) → default/columns/sensitive_allowed.
- `GET /api/employees/export?columns=...` (`employees.view` + `employees.export`) → CSV; PII butuh `employees.manage`.

## 3. Evidence

| ID | Metode / command | Fixture / peran | Expected | Actual | Status |
|---|---|---|---|---|---|
| E1 | `php artisan test tests/Feature/Import/EmployeeImportTest.php` | SQLite in-memory + `Storage::fake` | Semua skenario impor pegawai PASS | **28 passed / 189 assertions, 12,91 s** | PASS |
| E2 | `php artisan test tests/Feature/Employee/EmployeeExportColumnsTest.php` | SQLite in-memory | Registry/gating/limit PASS | **15 passed / 60 assertions, 6,96 s** | PASS |
| E3 | Gabungan E1+E2 | SQLite in-memory | 43 tes PASS | **43 passed / 249 assertions, 18,65 s** | PASS |
| E4 | Regresi suite penuh | SQLite in-memory, 13 skipped (MariaDB tanpa password) | Tidak ada regresi | **402 passed / 13 skipped / 3.587 assertions** | PASS |
| E5 | Regresi impor siswa 05a | SQLite in-memory | 05a tetap PASS | **18 passed / 97 assertions** | PASS |
| E6 | `./vendor/bin/pint --test` 15 file PHP berubah/baru | — | Gaya bersih | **PASS 15 files** | PASS |
| E7 | `node --test tests/employee-import-params.test.mjs tests/employee-directory-params.test.mjs tests/student-import-params.test.mjs` | Node 24.18.0 | Semua unit test helper PASS | **15 tests, 0 fail** | PASS |
| E8 | `npx eslint` pada file Vue/mjs baru | ESLint | 0 error | **0 error**; warning Prettier lama pada `EmployeeDirectory.vue` | PASS (scoped) |
| E9 | `npm run build` | Vite 6 | Build sukses | **PASS 28,46 s**; warning chunk >500 kB sudah ada sebelumnya | PASS |
| E10 | `php scripts/phase05-uat.php prepare` pada `.uat-phase05` | SQLite UAT terisolasi | Migration 05b diterapkan tanpa mengubah data | `2029_07_28_000001` **DONE**; students 409, employees 200 (tidak berubah) | PASS |
| E11 | Query SQLite UAT `abilities`/`ability_role` | — | Ability terpetakan benar | `employees.import` → `admin-sistem`, `operator-sekolah` | PASS |
| E12 | Validasi struktur sampel CSV (fgetcsv) | `.uat-phase05/samples/employees-import-*.csv` | 22 kolom konsisten | valid 3 baris, mixed 3 baris, update 1 baris; semua 22 kolom | PASS |
| E13 | `php artisan route:list --path=employees` | — | Route baru terdaftar dan tidak tertutup resource | 16 route termasuk export/options + 5 route impor | PASS |

**NOT RUN:** browser/visual UAT 05b — otomasi browser diblokir kebijakan sesi ini ("Browser automation requires explicit user confirmation"); tidak ada klik/upload/preview/commit lewat UI yang dijalankan agen. Verifikasi UI terbatas pada build, lint, dan unit test helper. Item lain NOT RUN: MariaDB/InnoDB, two-tab concurrency, retensi otomatis, rekap sekolah (05c), XLSX. Semua item NOT RUN tidak boleh dicatat PASS.

## 4. UAT manual

Sampel baru di `.uat-phase05/samples/` (ignored, tidak dikomit):

| File | Isi | Catatan |
|---|---|---|
| `employees-import-valid.csv` | 3 pegawai valid: 2 Pendidik (Subject Matematika/Bahasa Inggris) + 1 Tenaga Kependidikan tanpa Subject; NIK `0580000000000001` berawalan nol | Jalankan lebih dulu agar baris update memiliki match |
| `employees-import-mixed.csv` | 1 baris valid + 2 baris error (Employee Status `Honorer UAT`; NIK `12345`, Gender `Unknown Value`, District kosong) | Membuktikan all-or-nothing: tidak ada baris ditulis |
| `employees-import-update.csv` | NIK `0580000000000001`; hanya Employee Status/Address/Phone terisi | Preview `Update`; sel kosong tidak berubah |

Nilai referensi UAT yang dipakai (dibaca read-only dari SQLite UAT): Employee Type `Pendidik`/`Tenaga Kependidikan`; Status `Guru Tetap Yayasan`/`Pegawai Tetap Yayasan`; Employment 1–17 (sampel memakai `Guru Mata Pelajaran`, `Guru Bimbingan Konseling`, `Tenaga Administrasi Sekolah`); Gender `Laki-Laki`/`Perempuan`; Religion `Islam`; Marital Status `Belum Menikah`; Subject `Matematika`/`Bahasa Inggris`/`Ilmu Pengetahuan Alam (IPA)`; Regency `Synthetic Regency` + District `Synthetic District`.

Checklist 14 skenario tersedia di `PHASE-05B-MANUAL-REVIEW.md` (izin 403/404, template, insert valid + leading zero, campuran/ditolak, update parsial, NIK immutable, Subject Teacher/Staff, Regency+District, duplikat NIK/arsip, kolom ekspor default/PII, filter ekspor, batas berkas/baris, UI arsip, audit/privasi). Runtime saat review: SPA `127.0.0.1:14023`, API `127.0.0.1:18023`, akun `uat.operator` (manage/import) dan `uat.kepsek` (view+export, tanpa manage/import). Password hanya di runtime terabaikan; tidak dicetak.

**Penerimaan pemilik (2026-10-31 WIB):** pemilik menyatakan **"Pengujian nomor 1-14 sudah berhasil dan saya approved."** Checklist #1–#14 **APPROVED**. Pernyataan pemilik adalah satu-satunya bukti penerimaan; agen tidak mengulang skenario, dan item NOT RUN tetap NOT RUN. Setelah penerimaan, server UAT 04b/05 dihentikan (lihat §5 dan HANDOFF).

## 5. Risiko dan keputusan

- **PII ekspor**: pembatasan ditegakkan di server pada `export` dan `options`; UI bukan kontrol. Default tetap 10 kolom sehingga kompatibilitas 04b terjaga.
- **NIK unik termasuk arsip**: mengikuti unique index 04b; update baris arsip ditolak, bukan dipulihkan diam-diam.
- **All-or-nothing**: satu baris error memblokir seluruh commit; tidak ada partial success.
- **Berkas privat & formula**: source/errors di disk privat; sel dinetralkan; retensi 30 hari masih manual sehingga file dapat menumpuk bila operator tidak membersihkan.
- **Batas**: 2.000 baris/5 MB dan 10.000 baris ekspor ditolak `422`, tidak dipotong.
- **Refactor mesin impor**: kontrak siswa dipertahankan; regresi 18/97 mengunci perilaku. Perubahan perilaku siswa tidak diotorisasi pada 05b.
- **UAT**: migration 05b sudah diterapkan ke `.uat-phase05` (isolated) tetapi **belum** ke UAT 04b/MariaDB/produksi. Server UAT fase 04b (14022/18022) dan 05 (14023/18023) **dihentikan 2026-10-31** setelah fase lulus, sesuai kebijakan siklus UAT per fase (DEC-27); data/storage UAT dipertahankan dan perintah restart tercatat di checklist/HANDOFF.
- Risiko browser/visual belum diverifikasi agen; pemilik perlu menjalankan checklist pada browser nyata.

## 6. Sinkronisasi dokumen

Diperbarui: `CHANGELOG.md`, `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `docs/DATABASE.md`, `docs/REQUIREMENTS.md` (FR-06), `docs/TESTING.md`, `docs/DECISIONS.md` (DEC-26 dan DEC-27), `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-05-IMPORT-EXPORT.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, laporan ini dan checklist manual. `PROJECT_CONTEXT.md` mendapat addendum 05b dan catatan penerimaan.

Commit/push dilakukan agar pemilik dapat meninjau implementasi langsung di GitHub; commit/push bukan penerimaan manual. Penerimaan #1–#14 dicatat pada commit dokumentasi tersendiri. Tidak ada merge/deploy/force-push, perubahan `.env` utama, dependency/lockfile, service, DB terlarang, atau migrasi database operasional.

## 7. Langkah berikutnya

1. ~~Pemilik meninjau `PHASE-05B-MANUAL-REVIEW.md` pada `.uat-phase05`~~ — **SELESAI 2026-10-31 WIB**: #1–#14 APPROVED per pernyataan pemilik.
2. Penerimaan dicatat pada commit dokumentasi tersendiri; item NOT RUN tetap NOT RUN. Server UAT 04b/05 sudah dihentikan sesuai DEC-27.
3. Rekap sekolah (05c) menunggu contoh format (DEC-12 PROPOSED) dan instruksi terpisah; jangan mulai 05c tanpa instruksi pemilik.
