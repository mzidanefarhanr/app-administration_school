# Rencana Subfase 05b — Impor Pegawai dan Pilihan Kolom Ekspor

- Tanggal: 2026-10-30 (WIB). Branch: `main`. Baseline: `4e95706` (commit penerimaan 05a); `main...origin/main` sinkron.
- Status: **RENCANA — MENUNGGU KEPUTUSAN PEMILIK (usulan DEC-26)**. Belum ada implementasi 05b.
- Scope: sesuai DEC-25 D8 — **impor pegawai CSV** dan **pilihan kolom ekspor pegawai**. Rekap sekolah tetap ditunda menunggu contoh format (DEC-12 PROPOSED) dan bukan bagian 05b.
- Dokumen acuan: `PHASE-05-PLAN.md` §6, `roadmap/phases/PHASE-05-IMPORT-EXPORT.md`, `docs/DECISIONS.md` (DEC-04, DEC-24, DEC-25), `docs/REQUIREMENTS.md` (FR-05/FR-06), `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `docs/DATABASE.md`, `docs/TESTING.md`.
- Gate: pemilik menjawab **D1–D7** di Bagian 6; keputusan dicatat sebagai **DEC-26** sebelum implementasi dimulai.

---

## 1. Konteks dan tujuan

Subfase 05a sudah diterima pemilik dan menyediakan mesin impor CSV yang terbukti: preview tanpa mutasi, commit all-or-nothing, laporan error privat, batas berkas, dan isolasi batch per pembuat. Mesin itu masih **khusus domain siswa**: kolom, referensi, pesan error, preview, dan laporan error di-hardcode untuk siswa, dan endpoint batch-nya hanya menerima `students.import`.

Subfase 04b sudah menyediakan CRUD pegawai, direktori Teachers/Employees, dan ekspor CSV pegawai dengan **10 kolom operasional tetap**. Ekspor tersebut sengaja tidak memuat NIK/PII dan belum memiliki pilihan kolom.

Tujuan 05b:

1. Operator dapat mengimpor pegawai dari CSV dengan validasi yang sama ketatnya seperti impor siswa, mencocokkan data lama melalui **NIK** (DEC-25 D2), dan tidak pernah membuat data parsial.
2. Pengguna berizin dapat memilih kolom ekspor pegawai; kolom PII hanya untuk pemegang `employees.manage` (PHASE-05-PLAN §6).
3. Perilaku 05a dan ekspor default 04b tidak berubah.

---

## 2. Temuan audit kode (fakta per `4e95706`)

### 2.1 Mesin impor 05a — dapat dipakai ulang, belum generik

| Temuan | Bukti | Implikasi 05b |
|---|---|---|
| Parser/validator/commit siswa bersifat satu kelas | `laravel-api/app/Services/StudentImportService.php` (kolom pada baris 37–55, referensi 60–67, commit 182–255, parse 260–349, validasi 355–441) | Logika aman (BOM, delimiter, batas baris, hash, TTL, transaksi) layak diekstrak ke base class |
| Domain, config, dan endpoint batch terkunci siswa | `ImportController.php:108-116` mensyaratkan `domain === 'students'`; `config/imports.php` hanya blok `students`; route batch memakai `can:students.import` (`routes/api.php:161-172`) | Perlu service, controller, route, dan config domain pegawai |
| Preview dan laporan error memakai identitas siswa | `previewRows()` mengembalikan `nis`/`nisn` (baris 156–180); `storeErrors()` menulis header `Row,Field,Message,NIS,NIK` (baris 644–667) | Domain pegawai memerlukan proyeksi `nik`/`nuptk` dan header laporan sendiri |
| Tabel batch sudah mendukung domain lain | `import_batches.domain` (`2029_07_27_000001_create_import_batches_table.php:20`) dan index `(domain,status)` | **Tidak perlu migration tabel baru**; cukup migration ability |
| Ability impor pegawai belum ada | `AbilityCatalog.php:45-48` hanya `employees.view/manage/export`; `2029_07_27_000002` hanya menambah `students.import` | Perlu ability `employees.import` + pemetaan peran |
| Pola batch sudah matang | Token UUID, hash sumber, TTL 120 menit, commit idempoten, 409 saat kedaluwarsa/berubah, file privat `imports/{token}` | Dipertahankan identik untuk pegawai |

### 2.2 Rule domain pegawai

| Temuan | Bukti | Implikasi |
|---|---|---|
| Field wajib create/update | `EmployeeProfileRules.php:30-98`: `name`, `employee_type_id`, `employee_status_id`, `employment_id`, `religion_id`, `district_id`, `marital_status_id`, `gender_id`, `tmt_employee` | Impor insert wajib memenuhi field yang sama |
| Subject kondisional | `EmployeeProfileRules.php:22-28`: wajib untuk `Pendidik`, `prohibited` untuk `Tenaga Kependidikan` | Impor harus menegakkan aturan yang sama, termasuk saat tipe berubah |
| NIK nullable tetapi unik tanpa syarat | `2029_07_26_000001_add_profile_fields_to_employees.php:17-18` (`unique('nik')`) | NIK arsip tetap menempati keunikan; impor harus melaporkan konflik dengan arsip |
| NIK/identitas disimpan string | Model dan aturan `digits:16`; tidak ada cast numerik | Parser tidak boleh meng-cast angka; leading zero dipertahankan |
| TMT wajib, tanggal lain opsional | `tmt_employee` required `date_format:Y-m-d`; `exit_date`, `date_of_birth` opsional | Normalisasi `Y-m-d` / `d/m/Y` seperti 05a |
| Audit memakai allowlist tanpa PII | `Employee.php:32-43` | Impor massal tetap diaudit server tanpa menulis NIK/NUPTK/alamat |
| Akun tidak boleh disentuh impor | `user_id`, `user_nik`, `legacy_profile_at`, `account_unlinked_at` bukan data CSV | Template tidak memuat kolom akun; impor tidak menautkan akun |

### 2.3 Ekspor pegawai saat ini

| Temuan | Bukti | Implikasi |
|---|---|---|
| 10 kolom operasional tetap | `EmployeeExportController.php:34-45`: Name, Employee Type, Employee Status, Employment, Subject, NUPTK, Gender, Employment Start Date (TMT), Exit Date, Archived At | Default 05b harus tetap 10 kolom ini |
| Izin ganda | `EmployeeExportController.php:50-51`: `employees.view` + `employees.export` | Pilihan kolom tidak boleh menurunkan izin |
| Filter sama dengan daftar | `EmployeeDirectory::base()`; validasi filter di controller baris 53–59 | Pilihan kolom hanya menambah parameter, filter tidak berubah |
| Batas baris 422, bukan potong | `config/employees.php` (`export_max_rows` 10.000) | Tidak berubah |
| CSV aman | BOM, `nosniff`, netralisasi formula baris 119–131 | Berlaku untuk semua kolom baru |
| Belum ada parameter `columns` | Tidak ada validasi/registry kolom | Perlu registry allowlist + endpoint opsi |

### 2.4 Referensi dan ambiguitas

| Temuan | Bukti | Implikasi |
|---|---|---|
| `regencies.name` unik | `2025_07_05_183514_create_regencies_table.php:18` | Regency dapat dicari berdasarkan nama |
| `districts.name` **tidak** unik; ada `regency_id` | `2025_07_05_184827_create_districts_table.php:15-18` | District harus dicari dalam Regency; ambigu = error baris |
| `subjects.name` tidak unik dan soft-delete | `2026_05_28_144637_create_subjects_table.php:18-20` | Hanya subject aktif; duplikat nama = error, bukan pilih acak |
| Label English tersedia | `resources/reference-labels.json` domain employee-types, employee-statuses, employments, subjects, genders, religions, marital-statuses | Nilai menerima nama kanonik atau label English, seperti 05a |

### 2.5 Frontend, fixture, dan dokumen

| Temuan | Bukti | Implikasi |
|---|---|---|
| Tombol ekspor ada, impor belum | `EmployeeDirectory.vue:361` (Export CSV), tidak ada Import | Tambah tombol Import dan dua dialog |
| Helper ekspor belum mendukung kolom | `employeeDirectoryParams.mjs` `buildEmployeeExportParams()` menghapus paginasi/sort | Tambah parameter `columns` tanpa mengubah filter |
| Pola wizard impor tersedia | `StudentImportDialog.vue` + `studentImportParams.mjs` | Buat dialog pegawai terpisah; **jangan refactor UI 05a yang sudah diterima** |
| UAT 05 memiliki 200 pegawai sintetis | `.uat-phase05` (100 Teacher + 100 Staff); launcher `scripts/phase05-uat.php` | Cukup untuk fixture; tambah sample CSV pegawai |
| Sample CSV baru hanya siswa | `.uat-phase05/samples/students-import-*.csv` (ignored) | Perlu sample pegawai baru, juga ignored |
| Dokumen 05b masih garis besar | `PHASE-05-PLAN.md` §6; `PHASE-05-IMPORT-EXPORT.md` subfase 05b | Rencana ini menggantikan garis besar dengan desain konkret |

---

## 3. Gap dan resolusi yang diusulkan

| # | Gap | Tingkat | Resolusi | Keputusan |
|---|---|---|---|---|
| N1 | Tidak ada `employees.import` | Blocker | Ability baru + migration additive + katalog | — |
| N2 | Service/controller/config impor hanya siswa | Blocker | Ekstrak base `ImportService`; tambah `EmployeeImportService` dan controller/route domain | — |
| N3 | Template/aturan pegawai belum ada | Blocker | `EmployeeRules` bersama form + impor; template 22 kolom | D6 |
| N4 | NIK arsip tetap unik dan tidak terlihat dari CSV | Tinggi | Deteksi konflik NIK termasuk arsip; error eksplisit; update ke arsip ditolak | D3 |
| N5 | District/subject ambigu | Tinggi | Regency+District pair; subject aktif unik atau error | D5 |
| N6 | Ekspor belum bisa pilih kolom | Tinggi | Registry allowlist + parameter `columns` + endpoint opsi | D1/D2 |
| N7 | PII belum pernah masuk ekspor | **Kritis privasi** | Default tanpa PII; kolom sensitif hanya `employees.manage` | D2 |
| N8 | UI impor/ekspor kolom belum ada | Blocker | Dua dialog + helper + unit test | — |

---

## 4. Usulan scope teknis 05b

### 4.1 Backend impor pegawai

**Base engine (refactor terkendali).** Ekstrak bagian umum `StudentImportService` ke `app/Services/Imports/ImportService.php`:

- `createBatch()`, `commit()`, `parse()`, `validateRows()`, `previewRows()`, `errorList()`, `storeErrors()`, `normalizeDate()`, `sanitizeCell()`, `sourceContents()`, `directory()`, `disk()`.
- Hook domain: `domain()`, `configPrefix()`, `columns()`, `references()`, `prepareRow()`, `previewFields()`, `errorIdentityFields()`.
- `StudentImportService extends ImportService` mempertahankan **API publik, template 17 kolom, dan header error CSV yang sama**. 18 tes 05a menjadi pagar regresi wajib.
- `EmployeeImportService extends ImportService` mengimplementasikan kolom/aturan domain pegawai.

**Aturan bersama form + impor.** Ekstrak `EmployeeProfileRules` ke `app/Support/EmployeeRules.php`:

- `rules(?int $employeeId = null, ?int $selectedTypeId = null): array` dan `messages(): array`.
- Trait `EmployeeProfileRules` mendelegasikan ke kelas ini; `StoreEmployeeRequest`/`UpdateEmployeeRequest` tidak berubah perilaku.
- Impor memvalidasi atribut **gabungan** (nilai lama + sel yang diisi) untuk mode update, sehingga form dan impor tidak menyimpang.

**Kontrak API baru.**

| Method | Endpoint | Izin | Perilaku |
|---|---|---|---|
| GET | `/api/imports/employees/template` | `employees.view` | Unduh `employee-import-template.csv` (header saja) |
| POST | `/api/imports/employees` | `employees.import` | Multipart `file` + `mode` (`insert`/`update`) → `201` ringkasan + preview/error terbatas; **tanpa mutasi** |
| GET | `/api/imports/employees/{batch}` | `employees.import` | Ringkasan batch milik pembuat (token route key) |
| POST | `/api/imports/employees/{batch}/commit` | `employees.import` | Commit all-or-nothing; `200` `committed`; `409` bila invalid/kedaluwarsa/hash berubah |
| GET | `/api/imports/employees/{batch}/errors` | `employees.import` | CSV error privat; `404` bila tidak ada laporan |

- Batch hanya dapat diakses pembuatnya; pengguna berizin lain menerima `404`. Batch domain pegawai tidak dapat diakses lewat route siswa, dan sebaliknya.
- Route employee dideklarasikan setelah route siswa; tidak ada perubahan middleware route 05a.
- `StoreEmployeeImportRequest`: `file` required `mimes:csv,txt`, maks `imports.employees.max_size_kb`; `mode` `in:insert,update`; pesan English.

**Migration dan katalog.**

- Migration additive `2029_07_28_000001_add_employees_import_ability.php`: `firstOrCreate` ability `employees.import` (grup `employees`, sensitif), attach ke `admin-sistem` dan `operator-sekolah`; `down()` detach/delete.
- `AbilityCatalog::definitions()` dan `initialRoles()` ditambah `employees.import` untuk dua peran tersebut. `kepala-sekolah` **tidak** mendapatkannya.
- Tidak ada perubahan tabel `employees`, `import_batches`, atau skema lain.

**Konfigurasi.**

```php
'employees' => [
    'template_version' => 1,
    'max_rows' => (int) env('IMPORTS_EMPLOYEES_MAX_ROWS', 2000),
    'max_size_kb' => (int) env('IMPORTS_EMPLOYEES_MAX_SIZE_KB', 5120),
    'batch_ttl_minutes' => (int) env('IMPORTS_EMPLOYEES_TTL_MINUTES', 120),
    'retention_days' => 30,
    'preview_rows' => 50,
    'error_rows' => 200,
],
```

- Disk tetap `imports.disk` (`local`), path `imports/{token}/source.csv` dan `imports/{token}/errors.csv`.
- Laporan error pegawai: header **`Row,Field,Message,NIK,NUPTK`**, formula dinetralkan, retensi manual 30 hari.
- Preview: `row`, `action`, `name`, `nik`, `nuptk`, `employee_type`.
- Ringkasan batch menambah `domain=employees`; field lain sama dengan 05a.

### 4.2 Template impor pegawai v1 (22 kolom, full English)

| # | Header | Field internal | Wajib | Catatan |
|---|---|---|---|---|
| 1 | Name | `name` | Insert wajib; update opsional | string max 100; kosong pada update = tidak berubah |
| 2 | NIK | `nik` | **Update wajib**; insert opsional | 16 digit, string, unik termasuk arsip; **tidak dapat diubah lewat update** |
| 3 | NUPTK | `nuptk` | Tidak | string max 50, tidak unik |
| 4 | Employee Type | `employee_type_id` | Insert wajib; update opsional | `Teacher` / `Non-teaching Employee` (atau kanonik); kosong pada update = nilai lama |
| 5 | Employee Status | `employee_status_id` | Insert wajib; update opsional | nama/label English |
| 6 | Employment | `employment_id` | Insert wajib; update opsional | nama/label English |
| 7 | Subject | `subject_id` | Teacher wajib; Staff harus kosong | update: kosong = nilai lama untuk Teacher, dan mengosongkan Subject saat tipe menjadi Staff |
| 8 | Gender | `gender_id` | Insert wajib; update opsional | nama/label English |
| 9 | Religion | `religion_id` | Insert wajib; update opsional | nama/label English |
| 10 | Marital Status | `marital_status_id` | Insert wajib; update opsional | nama/label English |
| 11 | Regency | `regency` | Insert wajib; update opsional | nama unik; dipakai bersama District |
| 12 | District | `district_id` | Insert wajib; update opsional | nama dalam Regency; ambigu = error |
| 13 | TMT | `tmt_employee` | Insert wajib; update opsional | `YYYY-MM-DD` atau `DD/MM/YYYY` |
| 14 | Exit Date | `exit_date` | Tidak | `YYYY-MM-DD` atau `DD/MM/YYYY` |
| 15 | Place of Birth | `place_of_birth` | Tidak | max 100 |
| 16 | Date of Birth | `date_of_birth` | Tidak | sebelum hari ini |
| 17 | Address | `address` | Tidak | max 1000 |
| 18 | RT | `rt` | Tidak | max 5 |
| 19 | RW | `rw` | Tidak | max 5 |
| 20 | Zip Code | `zip_code` | Tidak | max 10 |
| 21 | Phone | `wa_number` | Tidak | max 30 |
| 22 | Email | `email` | Tidak | email valid; bukan email akun |

- Header harus persis, urutan bebas, tanpa duplikat/kolom asing; BOM dan delimiter `,`/`;` ditangani seperti 05a.
- Kolom di luar daftar ini (NPWP, No. KK, nama ibu/pasangan, sertifikat, pendidikan, akun) **tidak diimpor** pada 05b; penambahan menunggu keputusan pemilik.
- Nilai referensi menerima nama kanonik atau label English; nilai tak dikenal menjadi error baris, **tidak membuat master baru**.

### 4.3 Validasi dan semantik update

**Insert**

- `EmployeeRules` dijalankan per baris; field wajib yang kosong menjadi error.
- NIK opsional (D3); bila diisi: 16 digit, unik terhadap seluruh baris `employees` termasuk arsip.
- Duplikat NIK di dalam file dilaporkan pada semua baris terkait.
- `subject` wajib untuk Teacher dan harus kosong untuk Non-teaching; pelanggaran → error baris.
- `version = 1`; `user_id`, `user_nik`, `legacy_profile_at`, `account_unlinked_at`, `archive_reason` tidak pernah diisi impor.

**Update**

- NIK **wajib** dan menjadi satu-satunya kunci pencocokan (DEC-25 D2); nama tidak pernah dipakai.
- Baris tanpa NIK, NIK tidak ditemukan, atau NIK milik pegawai terarsip → error; arsip harus di-restore manual.
- NIK dikecualikan dari atribut yang diubah; perubahan NIK hanya lewat form manual.
- Validasi memakai **nilai gabungan**: nilai lama + sel yang diisi. Sel kosong berarti **tidak berubah** (sama dengan 05a), bukan menghapus nilai.
- Baris yang hanya berisi NIK (tanpa nilai lain) → error "No values to update".
- Jika tipe berubah menjadi Non-teaching: `Subject` wajib kosong dan `subject_id` di-set `null` secara eksplisit. Jika tipe berubah menjadi Teacher: `Subject` wajib ada (nilai lama boleh dipakai bila masih valid).
- `version` naik 1 per baris; audit allowlist tetap tanpa PII.

**Commit**

- All-or-nothing dalam satu transaksi; `ImportBatch` dikunci; baris update dikunci dengan `lockForUpdate`.
- Revalidasi sumber sebelum transaksi; error baru → batch `invalid`, `409`, tidak ada tulisan.
- Idempoten bila batch sudah `committed`; TTL dan hash sama dengan 05a.
- Kegagalan unik saat commit (mis. balapan NIK) → `409`, transaksi rollback, tidak ada baris parsial.

### 4.4 Pilihan kolom ekspor pegawai

**Registry kolom** di `app/Support/EmployeeExportColumns.php` (satu sumber allowlist, label, resolver, dan sensitivitas). Urutan header selalu urutan registry, bukan urutan permintaan.

| Grup | Key | Header | Sensitif |
|---|---|---|---|
| Operasional (default) | `name` | Name | Tidak |
| Operasional | `employee_type` | Employee Type | Tidak |
| Operasional | `employee_status` | Employee Status | Tidak |
| Operasional | `employment` | Employment | Tidak |
| Operasional | `subject` | Subject | Tidak |
| Operasional | `nuptk` | NUPTK | Tidak |
| Operasional | `gender` | Gender | Tidak |
| Operasional | `tmt_employee` | Employment Start Date (TMT) | Tidak |
| Operasional | `exit_date` | Exit Date | Tidak |
| Operasional | `archived_at` | Archived At | Tidak |
| Sensitif (`employees.manage`) | `nik` | NIK | Ya |
| Sensitif | `npwp` | NPWP | Ya |
| Sensitif | `no_kk` | Family Card Number | Ya |
| Sensitif | `place_of_birth` | Place of Birth | Ya |
| Sensitif | `date_of_birth` | Date of Birth | Ya |
| Sensitif | `address` | Address | Ya |
| Sensitif | `rt` | RT | Ya |
| Sensitif | `rw` | RW | Ya |
| Sensitif | `zip_code` | Zip Code | Ya |
| Sensitif | `wa_number` | Phone | Ya |
| Sensitif | `email` | Email | Ya |
| Sensitif | `religion` | Religion | Ya |
| Sensitif | `regency` | Regency | Ya |
| Sensitif | `district` | District | Ya |
| Sensitif | `marital_status` | Marital Status | Ya |

**Kontrak API.**

- `GET /api/employees/export?columns=name,employment,tmt_employee` — `columns` opsional, comma-separated, allowlist registry.
- Tanpa `columns` atau string kosong → **default 10 kolom operasional** (perilaku 04b tidak berubah).
- Key tak dikenal → `422` dengan pesan English yang menyebut key; duplikat diabaikan.
- Kolom sensitif diminta tanpa `employees.manage` → **`403`**; pemegang `employees.export` tanpa manage hanya boleh operasional.
- Filter, batas 10.000 baris (`422`), BOM, `nosniff`, netralisasi formula, dan nama file tidak berubah.
- `GET /api/employees/export/options` (`employees.export`): metadata `default_columns` dan `groups` (operational, sensitive). Grup sensitive hanya dikembalikan bila pemanggil memegang `employees.manage`; UI memakai endpoint ini agar daftar kolom tidak duplikat di frontend.

**Frontend.**

- `EmployeeExportDialog.vue`: checkbox per kolom, grup Operational dan Sensitive (grup Sensitive hanya tampil bila `canManage`), default 10 operasional tercentang, tombol Select all/None per grup, minimal satu kolom, lalu unduh lewat endpoint yang sudah ada.
- `EmployeeDirectory.vue`: tombol Export CSV membuka dialog; tombol Import membuka wizard impor; keduanya tetap memerlukan izin backend.
- `employeeDirectoryParams.mjs`: `buildEmployeeExportParams(filters, columns)` menambahkan `columns` hanya bila ada pilihan; filter lama tidak berubah.

### 4.5 Frontend impor pegawai

- `EmployeeImportDialog.vue` mengikuti alur `StudentImportDialog.vue`: pilih mode → unduh template → unggah + preview → commit/errors.
- Mode: **Insert new employees** / **Update existing employees**.
- Preview menampilkan Name, NIK, NUPTK, Employee Type, Action; status `Ready to commit` / `Needs fixes`, ringkasan Total/Valid/Invalid.
- Error table menampilkan Row, Field, Message; tombol Download Error Report memakai endpoint privat.
- Setelah commit sukses: toast jumlah baris, daftar di-refresh, dialog tetap menampilkan hasil.
- `EmployeeDirectory.vue` menambahkan tombol Import hanya bila `employees.import`; halaman Teachers dan Employees memakai komponen yang sama, sehingga impor tersedia di keduanya. Tipe pegawai ditentukan oleh kolom file, bukan oleh halaman asal.
- `employeeImportParams.mjs`: cek `.csv` dan ukuran ≤5 MB di klien (server tetap penentu), pembentuk `FormData`.
- Seluruh teks English; dialog usable pada 390px; tidak ada perubahan pada dialog siswa 05a.

### 4.6 UAT

- Gunakan kembali **`.uat-phase05`** (SPA `127.0.0.1:14023`, API `127.0.0.1:18023`, cookie `school_phase05_session`, launcher `scripts/phase05-uat.php`) — D7.
- `prepare` hanya menjalankan migration additive ability dan seeder idempoten yang sudah ada; tidak reset data, tidak menyentuh UAT 04b, MariaDB, atau service.
- Fixture: 200 pegawai sintetis (100 Teacher/100 Staff) sudah ada; sample baru tidak menambah fixture permanen.
- Sample CSV baru di `.uat-phase05/samples/` (ignored):
  - `employees-import-valid.csv` — 3 pegawai (2 Teacher dengan Subject, 1 Non-teaching tanpa Subject), NIK sintetis 16 digit (salah satunya berawalan nol), NUPTK sintetis.
  - `employees-import-mixed.csv` — 3 baris; satu valid, satu NIK tidak 16 digit, satu Employee Type tidak dikenal.
  - `employees-import-update.csv` — satu baris dengan NIK dari sample valid + perubahan Employment/Phone (sel lain kosong).
- Satu sample valid hanya dijalankan sekali; upload ulang diharapkan gagal karena NIK duplikat (skenario duplikat, bukan defect). Untuk mengulang, ganti NIK/NUPTK ke nilai belum terpakai.
- Checklist manual pemilik dibuat terpisah setelah implementasi; status awal **PENDING**.

### 4.7 Dokumentasi yang disinkronkan saat implementasi

`docs/API-CONTRACTS.md` (endpoint impor pegawai, parameter `columns`, `/export/options`), `docs/ACCESS-CONTROL.md` (`employees.import`, gating kolom sensitif, scoping batch), `docs/DATABASE.md` (ability baru; `import_batches.domain`), `docs/REQUIREMENTS.md` (FR-05/FR-06), `docs/TESTING.md` (bukti + NOT RUN), `docs/DECISIONS.md` (DEC-26), `CHANGELOG.md`, `PHASE-05B-REPORT.md`, `PHASE-05B-MANUAL-REVIEW.md`, `INDEX.md`, `HANDOFF.md`, `ROADMAP.md`, `PHASE-05-IMPORT-EXPORT.md`, `PHASE-05-PLAN.md`.

---

## 5. Di luar scope 05b

- Rekap sekolah (DEC-12 masih PROPOSED; menunggu contoh format).
- Pencocokan file Dapodik, impor XLSX, atau library spreadsheet baru.
- Pembuatan/penautan akun pegawai lewat impor; perubahan role/ability pegawai.
- Impor PII di luar 22 kolom v1 (NPWP, KK, nama ibu/pasangan, sertifikat, pendidikan).
- Kolom ekspor siswa (kecuali D1 memilih perluasan).
- Perubahan perilaku impor siswa 05a (selain ekstraksi internal yang dijaga tes).
- Scheduler retensi otomatis (tetap manual 30 hari).
- Merge, deploy, migrasi DB operasional/UAT 04b/MariaDB, perubahan dependency/service.

---

## 6. Keputusan pemilik yang dibutuhkan (usulan DEC-26)

| ID | Pertanyaan | Rekomendasi | Alternatif | Dampak |
|---|---|---|---|---|
| D1 | Pilihan kolom ekspor berlaku untuk modul apa? | **Ekspor pegawai saja** (Teachers + Employees berbagi direktori/ekspor) | Pegawai + siswa pada 05b | Bila siswa ikut, perlu registry/UI/tes tambahan dan peninjauan ulang kebijakan PII ekspor siswa |
| D2 | Kolom sensitif apa yang boleh dipilih? | **15 kolom PII daftar Bagian 4.4** hanya untuk `employees.manage`; default tetap 10 operasional tanpa PII | (a) operasional saja; (b) seluruh field profil termasuk pendidikan/keluarga | Menentukan registry, UI, dan tes privasi; opsi (b) memperbesar risiko |
| D3 | Apakah NIK wajib di impor? | **Wajib pada mode update; opsional pada mode insert**; baris insert tanpa NIK diberi peringatan tidak dapat di-update lewat impor | Wajib di kedua mode; atau opsional di kedua mode | Menentukan template, validasi, dan kelengkapan data |
| D4 | Semantik update pegawai? | **Parsial seperti 05a**: NIK wajib, sel kosong = tidak berubah, validasi memakai nilai gabungan; NIK tidak dapat diubah; tipe Non-teaching mengosongkan Subject | Full-row: semua field wajib di setiap baris update | Parsial lebih ramah operator; full-row lebih sederhana tetapi lebih rawan salah isi |
| D5 | Identifikasi District? | **Kolom Regency + District** (nama; District dicari dalam Regency; ambigu = error) | District saja (ambigu sering); atau ID | Menentukan template dan validasi wilayah |
| D6 | Cakupan kolom template impor v1? | **22 kolom Bagian 4.2** (core + kontak/alamat); pendidikan/keluarga/sertifikat ditunda | Minimal core saja (13 kolom); atau seluruh field profil | Menentukan besar template dan cakupan data yang bisa dimigrasikan |
| D7 | Lingkungan UAT 05b? | **Gunakan kembali `.uat-phase05`** 14023/18023; tambah sample pegawai; jangan sentuh UAT 04b | Lingkungan baru `.uat-phase05b` dengan port baru | Reuse lebih sederhana; lingkungan baru menambah biaya dan risiko port |

Bila pemilik memilih alternatif, rencana ini diperbarui sebelum implementasi; tidak ada keputusan yang ditebak.

---

## 7. Urutan eksekusi dan gate

| Langkah | Pekerjaan | Verifikasi | Gate |
|---|---|---|---|
| S1 | Catat D1–D7 sebagai **DEC-26** di `docs/DECISIONS.md` | Keputusan eksplisit pemilik | D1–D7 |
| S2 | Ekstrak `ImportService` + `EmployeeRules`; pertahankan API 05a | 18 tes impor siswa PASS; template/error CSV siswa tidak berubah | — |
| S3 | Migration ability `employees.import`, katalog, config, route, request, controller | Tes ability/permission; route list; tidak ada route 05a berubah | — |
| S4 | `EmployeeImportService` + validasi/commit + laporan error | Tes insert/update/mixed/duplikat/arsip/subject/wilayah/rollback | D3–D6 |
| S5 | Registry kolom + `columns` + `/export/options` | Default 10 kolom tetap; PII gating; 422/403 | D1/D2 |
| S6 | UI impor + UI kolom ekspor + helper + unit test + build | Tes Node, build, ESLint, browser DOM | — |
| S7 | UAT sample + bukti agen + checklist pemilik | Guard PASS; alur impor/ekspor; 0 console error | D7 |
| S8 | Dokumentasi, laporan, commit/push paket review | Pint, diff check, working tree bersih dari artefak | — |

Setiap langkah berhenti pada gate yang belum dijawab. Implementasi tidak dilanjutkan ke 05c sebelum 05b selesai dan diterima.

---

## 8. Rencana pengujian

### 8.1 Backend impor pegawai (SQLite `:memory:`, `Storage::fake('local')`)

1. Guest `401`; `employees.view` saja `403`; `students.import` `403`; `employees.import` `201`.
2. Template `employees.view` `200`, nama file dan 22 header English; tanpa ability impor tetap `200`.
3. Preview valid tanpa mutasi (jumlah pegawai sebelum/sesudah sama); commit membuat baris `version=1`.
4. Leading zero NIK/NUPTK dipertahankan; tanggal `d/m/Y` dinormalkan.
5. File campuran valid+error → status invalid, commit `409`, tidak ada insert.
6. NIK bukan 16 digit, NIK duplikat dalam file, NIK sudah ada (termasuk arsip) → error baris jelas.
7. Teacher tanpa Subject / Non-teaching dengan Subject → error; Teacher valid → sukses.
8. Employee Type/Status/Employment/Gender/Religion/Marital Status tak dikenal → error baris, tanpa master baru.
9. Regency tak dikenal; District tidak ada di Regency; District ambigu; Subject ambigu → error baris.
10. Update via NIK: hanya sel terisi berubah, versi naik, NIK tetap; sel kosong tidak mengubah nilai.
11. Update tanpa NIK, NIK tidak cocok, NIK arsip, hanya NIK tanpa nilai lain → error; tidak ada mutasi.
12. Perubahan tipe Teacher → Non-teaching mengosongkan Subject; Non-teaching → Teacher tanpa Subject → error.
13. All-or-nothing: satu baris gagal saat commit → rollback total; batch `409`; decoy tidak berubah.
14. Commit idempoten; batch kedaluwarsa; hash sumber berubah → `409`.
15. Batch scoping: pengguna `employees.import` lain `404`; batch siswa tidak dapat diakses route pegawai dan sebaliknya.
16. Batas 2.001 baris / >5 MB → `422`; file kosong, header saja, header hilang/asing/duplikat → `422`; delimiter `;` dan BOM diterima.
17. Laporan error `Row,Field,Message,NIK,NUPTK`; formula dinetralkan; file privat.
18. Audit tidak memuat PII; `user_id`/akun tidak berubah; regresi 18 tes impor siswa PASS.

### 8.2 Ekspor kolom

1. Tanpa `columns` → tepat 10 kolom default 04b, header dan urutan sama.
2. Subset operasional → hanya kolom terpilih, urutan registry.
3. Kolom sensitif oleh `employees.manage` → `200` dan nilai benar (NIK/NPWP/KK/alamat/kontak/wilayah).
4. Kolom sensitif tanpa `employees.manage` → `403`; campuran operasional+sensitif tanpa manage → `403`.
5. Key tak dikenal → `422`; duplikat diabaikan; string kosong → default.
6. `/export/options`: non-manager hanya grup operational; manager mendapat sensitive.
7. Filter, batas 10.000 baris `422`, netralisasi formula, BOM/`nosniff`, nama file tetap.
8. Regresi tes ekspor 04b PASS.

### 8.3 Frontend

- Unit test `employeeImportParams` (mode, `.csv`, 5 MB, FormData).
- Unit test `buildEmployeeExportParams` (default, pilihan kolom, filter tetap).
- `npm run build` PASS; ESLint read-only pada berkas berubah.
- Browser DOM (bila tersedia): import valid/mixed/update/error download; dialog kolom default, subset, sensitif sebagai operator, tersembunyi sebagai principal; 0 console error. Bila pointer tidak tersedia, gunakan DOM/HTTP dan catat metodenya; screenshot tetap NOT RUN bila tidak diambil.

### 8.4 Checklist manual pemilik (dibuat setelah implementasi)

1. Isolasi `.uat-phase05` dan jumlah 200 pegawai (100/100); ability `employees.import` ada.
2. Operator melihat Import + Export; principal tidak melihat Import (tetapi tetap bisa ekspor).
3. Download template: 22 header English.
4. Preview valid tanpa mutasi; total tetap 200.
5. Commit valid: 203 pegawai; toast; preview retained.
6. Upload ulang valid: NIK duplikat, no commit, total 203.
7. Mixed: 1 valid/2 invalid, no commit; error NIK 16 digit dan unknown Employee Type; total tetap.
8. Download error report: header `Row,Field,Message,NIK,NUPTK`.
9. Update via NIK: Action Update; commit; Phone/Employment berubah; total tetap 203.
10. Aturan Subject Teacher/Non-teaching.
11. Header diubah (mis. `Employee Type` → `Jenis Pegawai`) ditolak English.
12. `.txt`/`.xlsx` dan >5 MB ditolak klien; server `422`.
13. File header saja ditolak `422`.
14. Ekspor default: 10 kolom, tanpa NIK.
15. Ekspor subset operasional: hanya kolom dipilih, urutan tetap.
16. Operator memilih kolom sensitif: CSV berisi NIK/Phone; principal tidak melihat grup sensitif dan HTTP langsung `403`.
17. Key kolom tak dikenal `422`; filter list/export konsisten.

---

## 9. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Refactor mesin 05a meregresi impor siswa | API publik dipertahankan; 18 tes siswa wajib PASS; template/error CSV siswa tidak berubah |
| PII bocor lewat ekspor | Default tanpa PII; allowlist registry; `employees.manage` wajib; tes negatif; tidak ada URL publik |
| Impor massal menimpa data tak diinginkan | All-or-nothing, revalidasi, lock baris, test decoy, version naik |
| NIK arsip tidak terlihat tetapi unik | Deteksi eksplisit; pesan "already registered (possibly archived)"; update ke arsip ditolak |
| District/subject ambigu | Regency+District; subject aktif; error baris; tidak memilih acak |
| Baris insert tanpa NIK tidak dapat di-update | Peringatan preview; keputusan D3; operator dapat melengkapi manual |
| Perubahan tipe pegawai meninggalkan Subject | Aturan eksplisit; test Teacher↔Non-teaching |
| Audit 2.000 baris membebani | Batas 2.000 baris; allowlist audit; commit transaksi tunggal |
| UI 05a yang diterima berubah | Dialog siswa tidak disentuh; dialog pegawai terpisah |
| UAT data pemilik berubah | Tidak reset; sample memakai NIK baru; sebelum/sesudah dicatat di checklist |
| MariaDB/concurrency belum diuji | Tetap NOT RUN; tidak membuat DB/grant baru; dicatat di laporan |
| Route batch tertukar domain | Route domain eksplisit; scoping domain + pembuat; tes silang |

---

## 10. Kriteria penerimaan dan syarat keluar

1. D1–D7 dijawab pemilik dan dicatat sebagai **DEC-26**.
2. Impor pegawai end-to-end berjalan: template, preview, commit all-or-nothing, update via NIK, laporan error privat, izin `employees.import`.
3. Pilihan kolom ekspor berjalan: default 10 kolom tidak berubah, subset operasional, kolom sensitif hanya `employees.manage`, endpoint opsi, UI.
4. 18 tes impor siswa 05a tetap PASS; tes ekspor 04b tetap PASS.
5. Tes baru, Pint, unit frontend, build, dan browser DOM (bila tersedia) dijalankan dan dicatat; NOT RUN dinyatakan eksplisit.
6. `PHASE-05B-REPORT.md` dan `PHASE-05B-MANUAL-REVIEW.md` tersedia; persetujuan pemilik dicatat pada commit dokumentasi terpisah.
7. Working tree bersih dari `.uat-phase05/`, sample CSV, DB, log, kredensial, `console.txt`, dan `vue-app/test-results/`.
8. Tidak ada merge/deploy/force-push, migration DB operasional/MariaDB/UAT 04b, atau dependency baru.

---

## 11. Batas otorisasi saat ini

- Dokumen ini **hanya rencana**; belum ada kode, migration, route, UI, atau data yang diubah.
- Implementasi 05b menunggu jawaban D1–D7 dan instruksi pemilik. DEC-25 sudah mengotorisasi arah fase, tetapi pilihan D1–D7 menentukan perilaku dan belum boleh ditebak.
- Implementasi nanti hanya boleh menyentuh `.uat-phase05` untuk migration ability dan sample; bukan DB operasional, MariaDB, `.uat-phase04b`, atau service.
- Commit/push paket review dilakukan setelah verifikasi sesuai kebijakan pemilik (review di GitHub); push bukan penerimaan. Penerimaan manual dicatat terpisah.
- Rekap sekolah, Dapodik, XLSX, dan 05c tetap di luar scope sampai ada keputusan/instruksi terpisah.

---

## 12. Prompt untuk memulai implementasi setelah rencana disetujui

```text
Baca AGENTS.md, ROADMAP, HANDOFF, PHASE-05-IMPORT-EXPORT.md,
PHASE-05-PLAN.md, dan PHASE-05B-PLAN.md.

Kerjakan subfase 05b saja: impor pegawai CSV + pilihan kolom ekspor pegawai.
Patuhi DEC-25 dan DEC-26 (D1–D7); jangan menebak keputusan yang belum dijawab.
Audit HEAD dan consumer/kontrak aktual sebelum edit.

Batas:
- Jangan menyentuh UAT 04b, DB operasional/MariaDB, service, .env utama,
  dependency/lockfile, atau route/perilaku impor siswa 05a.
- Gunakan .uat-phase05 untuk migration additive ability dan sample;
  data pemilik tidak boleh di-reset.
- Rekap sekolah, Dapodik, XLSX, akun pegawai, dan 05c di luar scope.

Verifikasi minimal:
- 18 tes impor siswa tetap PASS; tes impor pegawai dan ekspor kolom baru PASS.
- Pint, unit test frontend, build, dan browser DOM bila tersedia; catat NOT RUN.
- Periksa tidak ada PII di audit, laporan error privat, formula dinetralkan.

Setelah verifikasi, siapkan PHASE-05B-REPORT.md dan
PHASE-05B-MANUAL-REVIEW.md, sinkronkan dokumen kontrak/CHANGELOG/INDEX/HANDOFF/ROADMAP,
lalu commit dan push paket review secara selektif agar pemilik dapat meninjau di GitHub.
Push bukan penerimaan; penerimaan manual dicatat pada commit dokumentasi terpisah.
Berhenti setelah paket 05b siap review; jangan lanjut 05c.
```
