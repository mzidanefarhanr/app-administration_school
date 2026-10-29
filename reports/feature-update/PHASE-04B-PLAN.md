# Rencana Subfase 04b — CRUD Pegawai, Relasi Akun, dan Menu Aktif

- Tanggal: 2026-10-29 (WIB). Branch: `main`.
- Baseline kode: `c7379a5` (implementasi 04a). Penerimaan 04a: **APPROVED 2026-10-29 WIB** (checklist #1–#13 `PHASE-04A-MANUAL-REVIEW.md`).
- Subfase: **04b — CRUD dan menu aktif** (Fase 04: Guru dan Pegawai).
- Status: **DIIMLEMENTASIKAN DALAM SESI 2026-10-29** — rencana disetujui pemilik menjadi DEC-24 (D1–D8 APPROVED); laporan hasil aktual ada di `PHASE-04B-REPORT.md` (dibuat pada penutupan sesi).
- Dokumen acuan: `PHASE-04A-PLAN.md`, `PHASE-04A-EMPLOYEE-SCHEMA.md`, `docs/DECISIONS.md` (DEC-04, DEC-24), `docs/REQUIREMENTS.md` (FR-05), `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `docs/DATABASE.md`.

> **Catatan Otorisasi (2026-10-29 WIB):** Pemilik menyetujui seluruh rekomendasi D1–D8 dan menginstruksikan eksekusi ("Saya setuju rekomendasi kamu, lakukan eksekusi."). Persetujuan ini dicatat sebagai **DEC-24** dan mencakup implementasi 04b dalam batas rencana ini. Migration UAT, deploy, merge, force-push, dan perubahan dependency **tetap tidak diotorisasi**. Sub-item yang ditafsirkan secara teknis (keunikan `nik` unconditional, tanpa re-link otomatis, daftar kolom ekspor) dicatat eksplisit di DEC-24.

---

## 1. Konteks dan Tujuan Subfase 04b

Subfase 04a sudah menyelesaikan fondasi: profil pegawai independen, `user_id` nullable unique `nullOnDelete`, `user_nik`/`subject_id` nullable, `version` default 1, penanda legacy, rollback fail-closed, allowlist audit, policy, dan backfill akun. Namun seluruh lapisan operasional masih berupa scaffold:

- API CRUD belum berimplementasi.
- Belum ada halaman UI; menu Teachers/Employees masih placeholder.
- Belum ada kolom nama pada profil, sehingga profil tanpa akun tidak dapat ditampilkan atau dicari.

Tujuan 04b: menjadikan modul Guru dan Pegawai **benar-benar dapat dipakai operator** — daftar server-side, create/edit/detail, arsip/restore, relasi akun terkendali, ekspor terfilter, dan menu aktif — dengan tetap mematuhi DEC-04, standar privasi, dan pola konkurensi Fase 03.

---

## 2. Hasil Audit Statis (2026-10-29)

### 2.1 Schema `employees` — kesenjangan yang menentukan scope

Diperiksa dari `database/migrations/2026_10_15_161306_create_employees_table.php`, `2029_07_24_000001_update_employees_schema_for_phase_04a.php`, `2029_07_25_000001_add_legacy_profile_marker_to_employees.php`, dan `app/Models/Employee.php`.

| Temuan | Bukti | Dampak |
|---|---|---|
| **Tidak ada kolom nama pegawai** | Tabel `employees` tidak memiliki `name`; nama hanya ada di `users.name` | Profil independen (DEC-04 #1) tidak bisa ditampilkan/dicari tanpa akun. **Blocker** untuk list, form, dan ekspor |
| **Tidak ada NIK pribadi pegawai** | Satu-satunya NIK adalah `user_nik` (legacy FK ke `users.nik`) | Identitas profil tanpa akun hanya NUPTK (nullable, tidak unik). Perlu keputusan (Bagian 6, D2) |
| Tidak ada kolom alasan arsip | `employees` hanya punya `deleted_at` (softDeletes) | Arsip tanpa jejak alasan, berbeda dari siswa (DEC-21). Perlu keputusan (D7) |
| `tmt_employee` NOT NULL tanpa default | Migration baseline | Form wajib mengisi TMT; validasi server harus menolak kosong |
| Index minim | Hanya `deleted_at` | Query daftar/filter/sort akan full scan pada data besar; usul index additive |
| `subject_id` nullable, `version` default 1, `user_id` unique | Migration 04a | Fondasi siap dipakai; aturan bisnis guru vs non-guru ditegakkan di 04b (DEC-04 #4) |
| `user_id_resolved_at`, `account_unlinked_at`, `legacy_profile_at` | Migration 04a/04a-marker | Semantik link/unlink harus dihormati oleh endpoint akun di 04b |

Catatan fixture: `EmployeeFactory` memakai `EmployeeType` bernama `Tenaga Pendidik` dan `EmployeeStatus` bernama `Aktif`, sedangkan seeder membuat `Pendidik`/`Tenaga Kependidikan` dan PTT/PTY/GTT/GTY/PNS. Factory dapat membuat tipe/status liar bila seeder tidak berjalan — perlu diselaraskan di 04b.

### 2.2 Backend

- `app/Http/Controllers/EmployeeController.php:15` — hanya `index()` yang berisi, dan ia mengembalikan **seluruh baris** `Employee::latest()->get()` tanpa paginasi, tanpa filter, tanpa resource, dan **membocorkan seluruh kolom** (NPWP, KK, nama ibu kandung, pasangan, nilai ijazah) kepada setiap pemegang `employees.view`. `store`, `show`, `update`, `destroy` masih kosong.
- `app/Http/Requests/StoreEmployeeRequest.php:12` dan `UpdateEmployeeRequest.php` — `authorize()` mengembalikan `false`, `rules()` kosong. Endpoint saat ini tidak dapat dipakai.
- `routes/api.php:188-191` — `/employee` (singular) `apiResource` dengan `can:employees.view` (index/show) dan `can:employees.manage` (lainnya). Tidak ada route `restore`, `export`, atau link/unlink akun.
- `app/Policies/EmployeePolicy.php` — sudah benar (`employees.view`/`employees.manage`, `forceDelete` selalu `false`); tidak perlu diubah selain bila D6 menambah pemisahan sensitif.
- Tidak ada `EmployeeResource`/`EmployeeDirectory`; pola acuan tersedia di `App\Support\StudentDirectory` dan `StudentController` (paginasi server, 409, arsip, restore).
- Audit `Employee` memakai allowlist tanpa PII (`app/Models/Employee.php:32`); kolom baru 04b wajib dievaluasi ulang terhadap allowlist ini.

### 2.3 Frontend

- `vue-app/src/layout/AppMenu.vue:38-39` — menu `Teachers` dan `Employees` masih `to: '#'`.
- `vue-app/src/router/index.js` — tidak ada route pegawai; guard `meta.requiresAbility` sudah tersedia.
- Tidak ada view pegawai; pola acuan `Student.vue` (2.835 baris) mencakup DataTable lazy, filter server, guard respons usang `requestId`, 422 inline, 409 + Reload Latest, dialog arsip/restore, dan ekspor terfilter.
- `vue-app/src/stores/authStore.js` menyediakan `can(ability)`; `vue-app/test/*.test.mjs` dijalankan dengan `node --test`.

### 2.4 Kemampuan dan ekspor

- `app/Support/AbilityCatalog.php:45-47` mendefinisikan `employees.view`, `employees.manage`, dan `employees.export`; peran Admin/Operator/Kepala Sekolah sudah memetakan ketiganya.
- `employees.export` **belum memiliki route maupun consumer** — belum ada titik penegakan. Keputusan D8 menentukan apakah ekspor masuk 04b atau Fase 05.

### 2.5 Data uji dan UAT

- `database/seeders/EmployeeSeeder.php` kosong; `UatSeeder` tidak membuat pegawai. UAT `school_compat_uat` saat ini **tidak memiliki data pegawai**.
- Pengujian pegawai yang ada hanya `tests/Feature/Employee/EmployeeSchemaAndAccountRelationTest.php` (21 tes) dan `MariaDbEmployeeIntegrationTest.php` (6 tes) — keduanya level schema/model/service, bukan endpoint/UI.
- Untuk UAT browser diperlukan (a) otorisasi menjalankan migration 04b di UAT dan (b) fixture pegawai sintetis. Keduanya belum diotorisasi.

---

## 3. Ringkasan Gap dan Resolusi yang Diusulkan

| # | Gap | Tingkat | Resolusi usulan | Keputusan |
|---|---|---|---|---|
| N1 | Tidak ada nama profil | Blocker | Kolom `employees.name` additif + backfill aman dari akun | D1 |
| N2 | Tidak ada NIK pribadi | Tinggi | Kolom `employees.nik` nullable unik (usul) atau ditunda ke Fase 05 | D2 |
| N3 | `index()` membocorkan PII | **Kritis** | Ganti dengan `EmployeeDirectory` + payload minimal; detail penuh hanya sesuai izin | D6 |
| N4 | CRUD/request kosong | Blocker | Implementasi controller, request, resource, policy check | — |
| N5 | Tidak ada alasan arsip | Sedang | Kolom `archive_reason` + arsip/restore berversi | D7 |
| N6 | `employees.export` tidak bertitik penegakan | Sedang | Endpoint ekspor terfilter atau catat sebagai direncanakan | D8 |
| N7 | Tidak ada UI/menu | Blocker | Halaman Teachers/Employees + menu aktif | D3 |
| N8 | Route singular `/employee` | Rendah | Tambah `/employees`, pertahankan `/employee` sebagai alias deprecated selama transisi | — (default teknis) |

---

## 4. Usulan Scope Subfase 04b

### 4.1 Schema additive (migration baru `2029_07_26_000001`, tidak mengedit migration lama)

Hanya bila keputusan terkait disetujui:

1. `employees.name` (`string(100)`, nullable di schema, wajib di validasi API) + backfill dari `users.name` melalui `user_id` atau fallback legacy yang aman. Baris yang tidak dapat diresolusi dibiarkan kosong dan dilaporkan sebagai hitungan agregat (tanpa PII). Tidak ada penulisan NOT NULL paksa pada rollback; `down()` fail-closed bila masih ada baris bergantung pada kolom baru.
2. `employees.nik` (`string(16)`, nullable, unique lingkup aktif) bila D2 disetujui; nomor identitas disimpan string, validasi 16 digit.
3. `employees.archive_reason` (`string(255)`, nullable) bila D7 disetujui.
4. Index additive: `name`, `employee_type_id`, `employee_status_id`, `deleted_at` (bila belum memadai). Tanpa rename kolom massal (DEC-04 #7).
5. Command backfill nama yang idempotent, atomik, dan hanya mengeluarkan hitungan agregat (`app:employee-name-backfill [--dry-run]`).

### 4.2 Backend API

Default teknis mengikuti pola 03b/03c/03d (tidak perlu keputusan baru): whitelist field, normalisasi null/blank, `version` wajib pada mutasi, `409` + `current_version`, `422` inline, `404` aman, `forceDelete` ditolak, audit server.

| Method | Endpoint | Izin | Perilaku usulan |
|---|---|---|---|
| GET | `/api/employees` | `employees.view` | Paginasi server (`10/20/50/100`, default 20), `search` (name/NUPTK; tanpa NIK/NPWP/KK), filter `employee_type_id`/`employee_status_id`/`employment_id`/`subject_id`, sort allowlist (`name`,`tmt_employee`), `only_archived=1` wajib `employees.manage`, payload minimal |
| GET | `/api/employees/{id}` | `employees.view` | Detail + relasi; field sensitif hanya untuk `employees.manage` (D6) |
| POST | `/api/employees` | `employees.manage` | `201`, `version=1`; menolak `user_id`/`user_nik`/`version` dari klien; `subject_id` wajib untuk Pendidik dan NULL untuk Tenaga Kependidikan (D4); audit allowlist |
| PUT/PATCH | `/api/employees/{id}` | `employees.manage` | `200`; `409` versi usang; `422` bila terarsip; audit |
| DELETE | `/api/employees/{id}` | `employees.manage` | Arsip administratif: `version` + `archive_reason` wajib; `exit_date` **tidak** diubah; `409`/`422` |
| POST | `/api/employees/{id}/restore` | `employees.manage` | `200`; `409` versi usang; `422` bila bentrok keunikan (NIK bila ada) |
| POST | `/api/employees/{id}/link-account` | `employees.manage` | D5: hanya akun `users` aktif yang belum terhubung profil lain; tidak mengubah role/abilities; mencatat `user_id_resolved_at` |
| POST | `/api/employees/{id}/unlink-account` | `employees.manage` | D5: mengosongkan `user_id`, mengisi `account_unlinked_at`; fallback NIK legacy tidak aktif kembali |
| GET | `/api/employees/export` | `employees.view` + `employees.export` | D8: CSV mengikuti filter aktif, kolom eksplisit, batas baris `422` (bukan potong diam-diam) |

Penambahan pendukung: `app/Support/EmployeeDirectory.php` (query daftar bersama untuk index/export), `app/Http/Resources/EmployeeResource.php` (payload minimal + gating sensitif), `app/Http/Controllers/EmployeeExportController.php` (bila D8 disetujui). Route `/employees` ditambahkan; `/employee` dipertahankan sebagai alias sampai consumer diperbarui dan diuji.

### 4.3 Frontend

- Route baru `/public/teachers` dan `/public/employees` dengan `meta.requiresAbility: 'employees.view'`; menu `Teachers`/`Employees` diarahkan ke route nyata.
- Satu komponen direktori bersama dengan tipe tetap per route (D3), dipecah modular agar tidak mengulang monolith `Student.vue`: `EmployeeDirectory.vue`, `EmployeeFormDialog.vue`, `EmployeeDetailDrawer.vue`, `EmployeeArchiveDialog.vue`, dan (bila D5) `EmployeeAccountLinkDialog.vue`.
- Daftar: DataTable lazy, pencarian debounce 300 ms, filter jenis/status/kepegawaian/mapel, sort server, paginasi server, empty/loading/error + Retry, guard respons usang `requestId`, pemulihan halaman kosong.
- Form: bagian Identity, Employment (mapel kondisional), Contact/Address, Family, Education; 422 inline, 409 + Reload Latest, guard double-submit, konfirmasi perubahan belum disimpan; label English dan tanggal WIB.
- Tombol mengikuti izin: `employees.view` (lihat), `employees.manage` (create/edit/arsip/restore/link), `employees.export` (unduh).
- Daftar arsip hanya untuk `employees.manage` (pola 03b/03c).

### 4.4 Fixture dan data uji

- Perbaiki drift `EmployeeFactory` (tipe/status konsisten dengan seeder) dan tambahkan state `pendidik`/`tenaga-kependidikan`.
- Fixture UAT sintetis hanya dibuat pada database disposable; seeding UAT memerlukan otorisasi terpisah.

### 4.5 Dokumentasi dan penutupan

- Update `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `docs/DATABASE.md`, `CHANGELOG.md`, laporan subfase, `INDEX.md`, `HANDOFF.md`, `ROADMAP.md`, dan checklist manual pemilik.
- Commit/push selektif per fase setelah verifikasi; penerimaan manual dicatat pada commit dokumentasi terpisah.

---

## 5. Di Luar Scope 04b

- Penugasan mengajar multi-mapel/jadwal per jam dan jurnal — **Fase 10**.
- Penetapan wali kelas pada rombel (`class_teach_id`) — **Fase 06**.
- Payroll, absensi, fingerprint, dan portal pegawai.
- Pembuatan akun, reset password, dan perubahan role/abilities pegawai — modul Accounts/Fase 01 (04b hanya menautkan akun yang sudah ada, bila D5 disetujui).
- Impor massal pegawai dengan preview — **Fase 05**.
- Rename kolom massal, migrasi framework/dependency, migration/UAT deploy.

---

## 6. Keputusan Pemilik yang Dibutuhkan (usulan DEC-24)

| ID | Pertanyaan | Rekomendasi | Alternatif | Dampak bila disetujui |
|---|---|---|---|---|
| D1 | Bagaimana profil tanpa akun memiliki nama? | **Tambah `employees.name`** (additif) dan backfill dari `users.name` hanya untuk baris yang dapat diresolusi; nama wajib di API untuk profil baru/update | Nama hanya dari akun (bertentangan dengan DEC-04 #1) | Migration + command backfill; list/form/ekspor mungkin |
| D2 | Apakah NIK pribadi pegawai dicatat? | **Tambah `employees.nik` nullable** (16 digit string, unik di antara baris aktif) agar identitas profil tidak bergantung pada akun | Tunda ke Fase 05 (impor) | Migration; validasi; restore conflict; kolom ekspor opsional |
| D3 | Struktur menu Teachers vs Employees? | **Dua route, satu komponen direktori**; Teachers = Pendidik, Employees = Tenaga Kependidikan, tanpa filter tipe lintas halaman | Satu halaman dengan filter tipe | UX & routing; tidak mengubah API |
| D4 | Aturan mapel per jenis pegawai? | **`subject_id` wajib untuk Pendidik, harus NULL untuk Tenaga Kependidikan** (422 bila dilanggar) | Longgar (mapel opsional untuk semua) | Validasi server + form kondisional |
| D5 | Apakah 04b menyertakan taut/lepas akun? | **Ya, aksi terkendali**: pilih akun aktif yang belum tertaut, tanpa membuat akun, tanpa mengubah role; lepas akun mengisi `account_unlinked_at` dan tidak mengaktifkan kembali fallback NIK | Tunda ke modul Accounts | Endpoint + dialog; audit; menyelesaikan DEC-04 #3 secara operasional |
| D6 | Siapa boleh melihat PII sensitif (nama ibu kandung, pasangan, KK, NPWP, nilai ijazah)? | **Daftar tanpa PII untuk semua; detail PII hanya `employees.manage`**; pemegang `employees.view` melihat data operasional | Semua pemegang `employees.view` melihat penuh | Resource/gating + tes privasi; perubahan perilaku endpoint detail |
| D7 | Semantik arsip pegawai? | **Arsip administratif dengan `archive_reason` wajib dan restore berversi**; `exit_date` tetap bermakna berhenti/pensiun dan tidak diubah otomatis | Tanpa alasan arsip | Migration + endpoint + UI |
| D8 | Apakah ekspor pegawai masuk 04b? | **Ya**: `GET /api/employees/export`, izin `employees.view` + `employees.export`, filter sama dengan daftar, kolom eksplisit, batas 10.000 baris → 422 | Tunda ke Fase 05 | Route + controller + tombol UI |

**Default teknis tanpa keputusan baru** (mengikuti DEC-04/DEC-21/DEC-22): route plural + alias deprecated, paginasi 10/20/50/100 default 20, search nama/NUPTK, sort allowlist, `409` + `current_version`, `422` inline, arsip tidak menghapus riwayat, `forceDelete` tetap `false`, audit allowlist tanpa PII, UI English.

---

## 7. Urutan Eksekusi dan Gate

| Langkah | Pekerjaan | Verifikasi | Gate |
|---|---|---|---|
| S1 | Migration additive (`name`; `nik`/`archive_reason` sesuai D2/D7) + backfill nama + index; test schema SQLite & MariaDB disposable | DDL, backfill idempoten, rollback fail-closed, tanpa PII di output | D1 (+D2/D7) |
| S2 | `EmployeeDirectory` + `EmployeeResource` + `index`/`show` (paginasi, filter, sort, privasi) | Feature test list/detail + privasi + 401/403 | D6 |
| S3 | `store`/`update` + request rules + versioning | 201/200/409/422, audit allowlist, decoy tidak berubah | D1/D4 |
| S4 | Arsip/restore + alasan | 422 tanpa alasan, 409, restore conflict, `exit_date` utuh | D7 |
| S5 | Link/unlink akun | unique 1 akun=1 profil, tanpa perubahan role, timestamp, fallback NIK mati | D5 |
| S6 | Ekspor CSV terfilter (bila disetujui) | Izin ganda, filter, kolom, batas 422 | D8 |
| S7 | Frontend: route, menu, direktori, form, detail, dialog arsip/akun | Unit frontend, build, browser UAT sintetis | D3 |
| S8 | Dokumentasi, checklist manual, laporan aktual | Pint, diff-check, working tree bersih dari artefak | Semua |

Setiap langkah berhenti pada gate keputusan yang belum dijawab; tidak ada pekerjaan yang menebak keputusan bisnis.

---

## 8. Rencana Pengujian

### 8.1 SQLite feature tests (usulan `tests/Feature/Employee/EmployeeCrudTest.php`, `EmployeeDirectoryTest.php`)

1. Guest `401`; akun tanpa `employees.view` `403` pada index/show; tanpa `employees.manage` `403` pada mutasi/restore/link/ekspor.
2. Index: default 20/halaman, nilai `per_page` tidak sah `422`, page kosong mundur benar.
3. Index: pencarian nama/NUPTK, filter jenis/status/kepegawaian/mapel, sort allowlist + tie-break `id`.
4. Index: baris terarsip tidak muncul; `only_archived=1` hanya `employees.manage`; payload daftar tanpa PII sensitif.
5. Detail: relasi lengkap; PII sesuai D6; `404` aman untuk ID tak ada.
6. Create: Pendidik tanpa mapel `422`; Tenaga Kependidikan dengan mapel `422`/diabaikan sesuai D4; profil tanpa akun `201` `version=1`.
7. Create/update: `user_id`, `user_nik`, `version`, `id`, timestamp dari klien diabaikan; audit hanya allowlist.
8. Update: versi benar `200` versi+1; versi usang `409` + `current_version`; record terarsip `422`.
9. Arsip: tanpa alasan `422`; sukses soft delete + alasan; `exit_date` tidak berubah; versi naik.
10. Restore: `200`; versi usang `409`; bentrok NIK (bila D2) `422`.
11. Link/unlink: akun aktif saja; satu akun maksimal satu profil; role/abilities tidak berubah; unlink mengisi `account_unlinked_at`; NIK legacy tidak menaut ulang.
12. Ekspor (bila D8): izin ganda, filter aktif, seluruh hasil (bukan halaman), kolom eksplisit, leading zero, batas baris `422`.
13. Regresi 04a tetap lulus (21 tes) + policy/audit tidak melemah.

### 8.2 MariaDB disposable

Database baru khusus 04b (mis. `school_04b_integration`) dengan user DB-scoped; verifikasi `unique(user_id)`, `nullOnDelete`, kolom/index baru, arsip/restore, dan perilaku `422`/`409` pada driver MySQL. Tanpa menyentuh `backdata_administrations`, `school_compat_uat`, `school_04a_integration`, atau resource App Gudang.

### 8.3 Frontend

- `node --test` untuk helper filter/serializer (bila ditambahkan).
- `npm run build` lulus; ESLint read-only tanpa error baru.
- Uji manual browser bila server UAT tersedia: golden path (list → create → edit → 409 dua tab → arsip → restore) dan edge case (422 inline, izin view-only, halaman kosong, filter). Bila data UAT belum diotorisasi, browser UAT dinyatakan **NOT RUN** dan diganti checklist manual pemilik.

### 8.4 Checklist manual pemilik

Checklist baru `PHASE-04B-MANUAL-REVIEW.md` mengikuti pola 03b/03c: izin view-only, create/edit 422/409, arsip+alasan, restore, privasi PII, ekspor, link/unlink akun, dan menu aktif.

---

## 9. Risiko dan Mitigasi

| Risiko | Mitigasi |
|---|---|
| Backfill nama tidak lengkap untuk baris legacy ambigu | Laporkan hitungan agregat; jangan menebak; UI menampilkan status "name unresolved" dan operator melengkapi manual |
| PII bocor lewat daftar/ekspor | Resource minimal, kolom ekspor eksplisit, tes negatif PII, review D6 |
| Halaman form terlalu besar (pola `Student.vue` 2.835 baris) | Komponen modular, tab/accordion, satu sumber state form |
| Drift factory/seeder menciptakan tipe/status liar | Selaraskan factory dengan seeder; assert ID tipe (Pendidik vs Tenaga Kependidikan) di tes |
| Route alias `/employee` tertinggal | Pertahankan alias sampai consumer dimigrasikan, uji keduanya, catat deprecated |
| Migration 04b belum boleh ke UAT | Uji hanya pada disposable; UAT dinyatakan NOT RUN sampai diotorisasi |
| Konkurensi update/arsip | Row lock + `version` atomik seperti `StudentController` |
| Data UAT kosong | Fixture sintetis disposable; seeding UAT menunggu otorisasi |

---

## 10. Kriteria Penerimaan dan Syarat Keluar

1. Seluruh keputusan D1–D8 (bila relevan) dijawab pemilik dan dicatat di `docs/DECISIONS.md` sebagai DEC-24.
2. Scope 04b selesai: CRUD, arsip/restore, relasi akun (bila disetujui), ekspor (bila disetujui), menu aktif, privasi, dan audit.
3. Tes relevan benar-benar dijalankan (SQLite, MariaDB disposable, frontend) atau NOT RUN dengan alasan; Pint PASS.
4. Checklist manual 04b tersedia; persetujuan dicatat hanya setelah pernyataan pemilik, pada commit dokumentasi terpisah.
5. Laporan mencatat SHA, working tree, known issues, NOT RUN, dan risiko terbuka (advisory dependency, lifecycle Laravel/PHP).
6. Tidak ada merge/deploy/force-push, tidak ada migration UAT tanpa otorisasi, tidak ada perubahan dependency/lockfile.

---

## 11. Batas Otorisasi Saat Ini

- Subfase 04b **diotorisasi pemilik pada 2026-10-29 WIB** melalui persetujuan D1–D8 (DEC-24) dan instruksi eksekusi; implementasi dijalankan dalam batas rencana ini.
- Migration `2029_07_24_000001`, `2029_07_25_000001`, dan migration 04b **belum diterapkan ke UAT**; UAT migration, deploy, dan merge tidak diotorisasi.
- Tidak ada akun, role, data pegawai, atau database operasional yang diubah oleh penyusunan rencana ini.
- Risiko keamanan terbuka (48 Composer advisories, 20 npm vulnerabilities, EOL Laravel 10/PHP 8.2, rotasi rahasia produksi) tetap **OPEN** dan terpisah dari pekerjaan ini.
