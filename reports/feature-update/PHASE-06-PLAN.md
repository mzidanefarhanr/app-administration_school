# Rencana Fase 06 — Siklus Tahun Ajaran dan Rombel

- Tanggal: 2026-10-31 (WIB). Branch: `main`. Baseline kode: `ffa71e3` (05c diterima pemilik dan sudah di-push).
- Status dokumen: **PLAN APPROVED — D1–D8 disetujui pemilik 2026-10-31 WIB ("Saya setuju D1-D8 dilakukan.") dan dicatat sebagai DEC-29 APPROVED.** Implementasi 06a **IMPLEMENTED & agent-verified 2026-10-31** (review manual pemilik PENDING); 06b/06c memerlukan rencana dan keputusan terpisah.
- Fase 06: **06a (periode dan rombel) → 06b (naik/tinggal/lulus) → 06c (mutasi dan koreksi)**, dikerjakan satu subfase per instruksi pemilik. Rencana ini merinci 06a dan memberi garis besar 06b/06c; keputusan 06b/06c diajukan terpisah sebelum subfase tersebut dimulai.
- Dokumen acuan: `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `docs/DECISIONS.md` (DEC-05, DEC-20, DEC-21, DEC-23, DEC-27, DEC-29), `docs/REQUIREMENTS.md` (FR-07), `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `docs/DATABASE.md`, `docs/TESTING.md`.
- Gate: D1–D8 telah disetujui dan dicatat sebagai **DEC-29 APPROVED (2026-10-31 WIB)**; implementasi 06a diotorisasi. Persetujuan ini bukan merge/deploy dan bukan izin migrasi database operasional.

---

## 1. Posisi fase dan prasyarat

Fase 03 (pendataan siswa), 04 (guru/pegawai), dan 05 (impor/ekspor/kualitas data) sudah diterima pemilik. Dependensi teknis Fase 06 (03, 04) terpenuhi; yang belum siap adalah **kebijakan periode akademik, wali kelas, dan batas lock** yang menentukan perilaku.

| Prasyarat | Kondisi saat ini | Dampak bila belum diputuskan |
|---|---|---|
| DEC-05 (identitas/keanggotaan) | APPROVED dan disahkan DEC-20 | Aturan satu keanggotaan aktif per siswa per tahun tidak boleh diulang atau dilemahkan |
| DEC-20/21/23 (arsip, versioning, assign/transfer) | APPROVED dan sudah diimplementasikan | 06a harus memperluas, bukan mengganti, alur 03d |
| DEC-27 (siklus UAT per fase) | APPROVED 2026-10-31 WIB | UAT 06 harus lingkungan terpisah; server dihentikan setelah penerimaan |
| Periode aktif dan lock | **Belum ada** di schema/API/UI | Tidak boleh ditebak; menentukan perilaku seluruh 06a |
| Sumber wali kelas | `class_teach_id` saat ini menunjuk `users` | Guru tanpa akun tidak bisa menjadi wali kelas; perlu keputusan D4 |
| Data kelas | CRUD backend masih scaffold | Halaman Classes saat ini tidak dapat menyimpan data secara benar |
| UAT 05 | `.uat-phase05` dipertahankan, server 14023/18023 sudah dihentikan | UAT 06 tidak boleh mengubah bukti 05; perlu runtime baru |

---

## 2. Temuan audit kode (fakta, bukan asumsi)

### 2.1 Tahun ajaran dan semester belum memiliki periode aktif eksplisit

| Temuan | Bukti | Implikasi |
|---|---|---|
| `school_years` hanya memiliki `id`, `name`, soft delete, dan timestamps | `laravel-api/database/migrations/2025_07_05_171436_create_school_years_table.php:14-20` | Tidak ada `start_date`, `end_date`, status aktif, atau lock |
| `semesters` bersifat global (`name`, `name_other`) tanpa relasi tahun ajaran | `laravel-api/database/migrations/2025_07_05_175126_create_semesters_table.php:14-21` | “Semester aktif” belum dapat diikat ke tahun secara eksplisit |
| `SemesterController` hanya mengimplementasikan `index`; store/update/destroy kosong | `laravel-api/app/Http/Controllers/SemesterController.php:15-69` | Belum ada CRUD semester; tidak ada UI semester di router/menu |
| `SchoolYearController::index` hanya mengembalikan `name` dan `id`; CRUD tidak memiliki konsep aktif/lock | `laravel-api/app/Http/Controllers/SchoolYearController.php:17-24`, `:37-135` | Halaman School Years belum bisa menetapkan periode aktif |
| `SchoolYearController::destroy` hanya memblokir bila `school_rombels_count > 0` | `laravel-api/app/Http/Controllers/SchoolYearController.php:140-161` | Tahun aktif/locked dan relasi soft-deleted belum dipertimbangkan |
| Halaman Students menentukan tahun default sendiri dari bulan berjalan | `vue-app/src/views/pages/public/Student.vue:89-101` | Default bukan “tahun aktif eksplisit” dari server; dapat berbeda dari konfigurasi sekolah |
| `/api/school-years` dibaca dengan `master.view` | `laravel-api/routes/api.php:133-160` | Halaman Students ikut bergantung pada `master.view`; perlu endpoint periode yang sesuai |

### 2.2 CRUD Classes (rombel) masih scaffold di backend

| Temuan | Bukti | Implikasi |
|---|---|---|
| `SchoolRombelController` hanya mengimplementasikan `index`; `store`, `show`, `update`, `destroy` kosong | `laravel-api/app/Http/Controllers/SchoolRombelController.php:15-70` | Frontend Classes mengirim create/update/delete tetapi tidak ada perilaku server yang benar |
| `StoreSchoolRombelRequest` dan `UpdateSchoolRombelRequest` masih `authorize(): false` dan tanpa rules | `laravel-api/app/Http/Requests/StoreSchoolRombelRequest.php:12-26`; `UpdateSchoolRombelRequest.php:12-26` | Permintaan tulis akan ditolak/ tidak tervalidasi |
| Model `SchoolRombel` tidak memasukkan `class_teach_id` ke `$fillable` | `laravel-api/app/Models/SchoolRombel.php:23-29` | Wali kelas yang dipilih di UI akan diabaikan saat mass-assignment |
| `classTeach()` menunjuk `User`, bukan profil pegawai | `laravel-api/app/Models/SchoolRombel.php:53-56` | Guru tanpa akun tidak dapat menjadi wali kelas; bertentangan dengan DEC-04 |
| Belum ada `Loggable`, policy, atau pemeriksaan `version` pada `SchoolRombel` | `laravel-api/app/Models/SchoolRombel.php:10-14` | Perubahan kelas tidak teraudit dan tidak terlindungi lost update |
| Schema belum memiliki unique name per tahun; `student_major_id` dan `class_teach_id` nullable | `laravel-api/database/migrations/2027_07_06_222354_create_school_rombels_table.php` | Duplikasi nama kelas dan kelas tanpa jurusan/guru perlu aturan eksplisit |
| Halaman Classes menggunakan generic `useResourceManager` dan mewajibkan empat FK | `vue-app/src/views/pages/public/SchoolRombels.vue` | Perlu halaman khusus untuk lock, version, opsi guru, dan penanganan konflik |

### 2.3 Sumber wali kelas dan akses belum selaras

- `class_teach_id` adalah FK ke `users`; Phase 04 menetapkan profil pegawai independen dan akun login opsional (DEC-04). Teachers = `Pendidik`, Employees = `Tenaga Kependidikan`.
- Halaman Classes memakai endpoint sekunder `users` untuk pilihan wali kelas (`vue-app/src/views/pages/public/SchoolRombels.vue`), sehingga wali kelas saat ini harus memiliki akun.
- Route API `/school-rombels` memakai `students.view`/`students.manage` (`laravel-api/routes/api.php:229-232`), sedangkan route SPA dan menu Classes memakai `master.view` (`vue-app/src/router/index.js:55-60`; `vue-app/src/layout/AppMenu.vue:21-30`). Pengguna dengan `students.manage` tetapi tanpa `master.view` tidak dapat membuka halaman; pengguna `master.view` tanpa `students.manage` dapat membuka halaman tetapi ditolak server saat menyimpan.
- Principal (`kepala-sekolah`) memiliki `master.view` + `students.view`, bukan `students.manage`; ini harus tetap konsisten setelah perubahan.

### 2.4 Fondasi penempatan 03d sudah kuat, tetapi belum mengenal lock

- Assign dan transfer sudah memakai transaksi, `lockForUpdate` pada siswa, expected student/membership version, single-active guard, dan audit rollback. Bukti: `laravel-api/app/Http/Controllers/StudentRombelController.php:170-399`; tes `StudentRombelAssignmentTest.php` (16 kasus), `StudentRombelConstraintTest.php`, `MariaDbRombelConcurrencyTest.php`.
- Request assign/transfer hanya memvalidasi tahun dan rombel dengan `exists`; belum menolak tahun/rombel yang soft-deleted atau tahun yang locked (`AssignStudentRombelRequest.php:25-50`; `TransferStudentRombelRequest.php:25-52`).
- Belum ada row lock pada `school_years`; lock tahun dapat berbalapan dengan assign/transfer.
- Belum ada aturan penghapusan rombel yang memiliki riwayat keanggotaan. Menghapus rombel dapat membuat riwayat lama kehilangan label kelas.

### 2.5 Naik/tinggal/lulus dan mutasi belum ada

- Belum ada endpoint, service, tabel batch, atau UI promosi/kelulusan/mutasi.
- `student_rombels` sudah memiliki `student_status_id`, `student_entry_id`, tanggal masuk/keluar, sekolah mutasi, tanggal lulus, dan informasi; status seed: `Aktif`, `Mutasi Keluar`, `Drop Out`, `Mengundurkan Diri`, `Meninggal Dunia`, `Lulus` (`laravel-api/database/seeders/StudentStatusSeeder.php`); entry seed: `Siswa Baru`, `Mutasi Masuk` (`StudentEntrySeeder.php`).
- `school_levels` hanya berisi nama I–XII tanpa urutan/level berikutnya (`SchoolLevelSeeder.php`; migration `2025_07_06_221826_create_school_levels_table.php`), dan `student_majors` juga hanya nama. Karena itu **kenaikan kelas otomatis berbasis level tidak dapat diandalkan**; target kelas harus eksplisit per kelas sumber.
- Belum ada tabel log mutasi; field mutasi saat ini hanya keadaan terakhir pada membership. Koreksi/pembatalan bertanggal membutuhkan rancangan tersendiri di 06c.

### 2.6 Tes dan dokumentasi aktual

- Tes yang ada mencakup assignment/transfer, constraint single-active, resolusi siswa, dan konkurensi MariaDB; belum ada tes CRUD School Year/School Rombel, lock periode, atau periode aktif.
- `docs/API-CONTRACTS.md:263-265` mencatat bahwa `GET /api/student-rombels/{id}` baseline memakai ID tahun ajaran, bukan ID membership; jangan mengubah makna `{id}` diam-diam.
- `docs/ACCESS-CONTROL.md` belum memiliki ability periode/akademik baru; `master.*` dan `students.*` adalah kemampuan yang berlaku saat ini.
- UAT 05 sudah dihentikan sesuai DEC-27; `.uat-phase05` dipertahankan. Fase 06 membutuhkan runtime terpisah agar bukti 05 tidak berubah.

---

## 3. Urutan pekerjaan yang direkomendasikan

| Urutan | Subfase | Alasan |
|---|---|---|
| 1 | **06a — Periode dan rombel** | Fondasi periode aktif, lock, CRUD kelas, wali kelas, dan penegakan lock pada assign/transfer. Menjadi prasyarat 06b/06c. |
| 2 | **06b — Naik kelas/tinggal kelas/lulus** | Membutuhkan periode aktif, target kelas yang valid, dan aturan lock dari 06a. |
| 3 | **06c — Mutasi dan koreksi** | Membutuhkan riwayat periode/kelas serta mekanisme koreksi terkunci dari 06a/06b. |

Jangan mengeksekusi 06b/06c otomatis setelah 06a. Setiap subfase berhenti untuk review pemilik.

---

## 4. Keputusan yang perlu pemilik (D1–D8)

Rekomendasi ditulis sebagai default. Pemilik dapat memilih alternatif. Setelah disetujui, keputusan dicatat sebagai DEC baru sebelum implementasi.

| # | Pertanyaan | Rekomendasi | Alternatif / dampak |
|---|---|---|---|
| **D1** | Di mana periode aktif disimpan, dan bagaimana nilai awalnya? | **Tabel singleton `academic_settings`** (`id=1`) menyimpan `active_school_year_id` dan `active_semester_id`; perubahan memakai transaksi + row lock; **tidak menebak tahun aktif saat migration** — kolom dibiarkan kosong dan UI menampilkan banner “set active period” bagi pemegang `master.manage`. Semester tetap data referensi global (Ganjil/Genap); tidak ditautkan per tahun pada 06a. | Flag `is_active` pada `school_years`/`semesters` (rawan dua baris aktif); menurunkan tahun dari tanggal berjalan (tidak eksplisit); menautkan semester ke tahun (migration data lebih besar). |
| **D2** | Apakah tahun ajaran memiliki rentang tanggal? | **Tambahkan `start_date` dan `end_date` nullable** pada `school_years`, diisi operator; tidak diisi otomatis dari nama. 06b membutuhkan `start_date` tahun tujuan untuk tanggal efektif; bila kosong, preview promosi ditolak dengan pesan validasi. | Memakai tanggal hari ini untuk semua promosi (rawan bila dikerjakan sebelum tahun baru); menurunkan tanggal dari string nama (tebakan). |
| **D3** | Apa cakupan lock dan siapa yang berwenang? | **Lock per tahun ajaran** (`locked_at`, `locked_by`, `lock_reason`). Hanya `master.manage` yang dapat lock/unlock, dengan alasan wajib dan audit. **Tahun aktif tidak boleh dikunci**; operator harus memilih tahun aktif lain yang belum dikunci terlebih dahulu. Tahun terkunci menolak create/update/delete kelas dan assign/transfer; koreksi terkunci disediakan terpisah di 06c. | Lock per semester (butuh periode per semester); mengizinkan tahun aktif dikunci (risiko operasi terkunci); lock tanpa alasan (audit lemah). |
| **D4** | Wali kelas bersumber dari akun `users` atau profil `employees`? | **Profil pegawai**: tambahkan `class_teach_employee_id` nullable ke `employees`; hanya pegawai aktif bertipe `Pendidik` yang dapat dipilih. `class_teach_id` lama dipertahankan untuk kompatibilitas dan di-backfill hanya bila relasi `employees.user_id` tunggal/jelas; baris ambigu dibiarkan kosong dan dilaporkan agregat. UI baru memakai employee. | Tetap `users` (guru tanpa akun tidak bisa menjadi wali kelas); mengganti `class_teach_id` sepenuhnya (breaking). |
| **D5** | Ability dan penempatan menu untuk periode/kelas? | **Tanpa ability baru**: Classes memakai `students.view`/`students.manage` (sesuai API saat ini); School Years/Semesters tetap `master.view`/`master.manage`; lock dan set active period memakai `master.manage`. Route/menu Classes diselaraskan ke `students.view`, ditempatkan pada grup menu **Academic** bersama School Years (item masing-masing memakai ability-nya). | Menambah `academic.view`/`academic.manage` (lebih konsisten jangka panjang, tetapi menambah katalog/role/migration); membiarkan mismatch route/menu (pengguna tertentu tidak bisa mengakses). |
| **D6** | Aturan nama, version, dan hapus kelas? | **Nama unik per tahun ajaran di antara baris non-deleted**, divalidasi dalam transaksi dengan lock baris tahun; **`expected_version` wajib** untuk update/delete dan `version` dinaikkan; **hapus ditolak bila sudah ada riwayat `student_rombels`** (termasuk nonaktif/soft-deleted); ubah tahun/level/jurusan ditolak bila sudah ada riwayat; nama dan wali kelas masih dapat diubah selama tahun belum lock. Tidak memasang unique index DB pada 06a sebelum data existing diaudit. | Unique index per tahun (bisa gagal bila data existing duplikat); mengizinkan hapus kelas berisi riwayat (merusak tampilan histori); tanpa version (lost update). |
| **D7** | Field wajib kelas? | **Wajib**: `name`, `school_year_id`, `school_level_id`. **Opsional**: `student_major_id` (sekolah tanpa jurusan), `class_teach_employee_id` (kelas boleh dibuat sebelum wali ditetapkan). Bila diisi, jurusan/wali harus valid dan aktif. | Mempertahankan empat field wajib seperti UI sekarang (menghambat sekolah dasar/non-vokasi dan setup awal). |
| **D8** | Lingkungan dan fixture UAT 06? | **Runtime baru `.uat-phase06`**: SQLite terisolasi, API `127.0.0.1:18024`, SPA `127.0.0.1:14024`, cookie `school_phase06_session`, launcher `scripts/phase06-uat.php` (`init`/`prepare`/`status`, fail-closed). Fixture sintetis **≥120 siswa** (dua tahun ajaran, sebagian placed/unplaced), **12 kelas**, **≥8 guru `Pendidik`** (sebagian tanpa akun), **4 staf**, **4 jurusan**; idempotent dan tidak me-reset `.uat-phase05`. Server dihentikan setelah penerimaan (DEC-27). | Memakai ulang `.uat-phase05` (mengubah bukti 05 yang sudah accepted); jumlah data lebih kecil (tidak memenuhi kebijakan minimal 100 baris). |

Catatan: bila pemilik hanya menyetujui sebagian, 06a hanya dapat dimulai untuk keputusan yang disetujui. D8 diperlukan sebelum UAT; D2 dapat ditunda hanya bila 06b belum dimulai, tetapi `start_date`/`end_date` sebaiknya ditambahkan pada migration 06a agar tidak ada migration tambahan di 06b.

---

## 5. Desain teknis Subfase 06a — Periode dan rombel

### 5.1 Skema (migration additive)

Semua perubahan additive; tidak ada drop kolom, rename, `migrate:fresh`, reset, atau backfill destruktif.

1. **`academic_settings`** (baru, singleton):
   - `id` (primary, diisi 1), `active_school_year_id` nullable FK `school_years.id` `nullOnDelete`, `active_semester_id` nullable FK `semesters.id` `nullOnDelete`, `updated_by` nullable FK `users.id` `nullOnDelete`, timestamps.
   - Migration membuat baris `id=1` dengan nilai null; tidak menebak tahun aktif.
2. **`school_years`** (tambah kolom):
   - `start_date` date nullable, `end_date` date nullable.
   - `locked_at` timestamp nullable, `locked_by` FK `users.id` nullable `nullOnDelete`, `lock_reason` string(255) nullable.
   - Index `(locked_at)` bila diperlukan untuk filter; tidak mengubah unique `name`.
3. **`school_rombels`** (tambah kolom):
   - `class_teach_employee_id` nullable FK `employees.id` `nullOnDelete` (bila D4 disetujui).
   - `class_teach_id` lama dipertahankan; tidak dihapus.
   - Index `(school_year_id, deleted_at)` untuk daftar/filter.
   - Tidak memasang unique index baru sebelum audit data existing.
4. **`student_rombels`**: tidak ada kolom baru di 06a; tahun ajaran tetap melalui `school_rombels.school_year_id`.

Data existing:
- `start_date`/`end_date` dibiarkan null; operator melengkapi. Tidak ada parsing nama.
- Backfill `class_teach_employee_id` hanya untuk `class_teach_id` yang dapat dipetakan tunggal ke `employees.user_id`; hasil agregat dilaporkan tanpa PII; baris ambigu tetap null.
- Bila data existing memiliki nama kelas duplikat per tahun, implementasi **tidak** menghapus/menggabungkan; validasi baru hanya mencegah duplikasi baru dan laporan audit mencatat jumlah konflik untuk keputusan pemilik.

### 5.2 Model, policy, dan audit

- `AcademicSetting` baru: `Loggable` dengan allowlist `active_school_year_id`, `active_semester_id`, `updated_by`; relasi `activeSchoolYear()`, `activeSemester()`, `updatedBy()`.
- `SchoolYear`: tambah `start_date`, `end_date`, `locked_at`, `locked_by`, `lock_reason` ke `$fillable`; tetap `Loggable`; relasi `lockedBy()`.
- `SchoolRombel`: tambah `class_teach_id` dan `class_teach_employee_id` ke `$fillable`; gunakan `Loggable` dengan allowlist `name`, `school_year_id`, `school_level_id`, `student_major_id`, `class_teach_id`, `class_teach_employee_id`, `version`, `deleted_at`; relasi `classTeachEmployee()`; pertahankan `classTeach()` lama.
- `SchoolRombelPolicy` baru: `viewAny`/`view` → `students.view`; `create`/`update`/`delete`/`restore` → `students.manage`; `forceDelete` selalu false.
- `SchoolYearPolicy` baru: `viewAny`/`view` → `master.view`; `create`/`update`/`delete`/`lock`/`unlock` → `master.manage`; `forceDelete` false.
- `SemesterPolicy` yang ada tetap tidak diubah pada 06a; semester hanya dibaca untuk pilihan aktif. Bila CRUD semester dibutuhkan, itu scope terpisah.
- Audit lock/unlock dan perubahan periode aktif tercatat melalui `Loggable`; tidak ada PII baru di log.

### 5.3 Endpoint dan izin

| Method | Endpoint | Izin | Perilaku |
|---|---|---|---|
| GET | `/api/academic/period` | `students.view` | Periode aktif (`active` atau null), daftar tahun (`id`, `name`, `start_date`, `end_date`, `locked_at`, `is_active`), daftar semester; **read-only** |
| PUT | `/api/academic/period` | `master.manage` | Set `active_school_year_id` + `active_semester_id` dalam transaksi + row lock; tahun harus ada, tidak soft-deleted, **tidak locked**; semester harus ada; audit |
| GET | `/api/school-years` | `master.view` | Tambah field tanggal/lock/`is_active`; dipakai halaman School Years |
| POST | `/api/school-years/{id}/lock` | `master.manage` | Body `reason` wajib (max 255); tolak bila tahun aktif atau sudah locked; set `locked_at/by/reason`; audit; idempoten aman |
| POST | `/api/school-years/{id}/unlock` | `master.manage` | Body `reason` wajib; tolak bila belum locked; kosongkan lock; audit |
| GET | `/api/school-rombels?school_year_id=` | `students.view` | Filter opsional; sertakan tahun/level/jurusan/wali employee, `version`, jumlah membership; tetap mengembalikan semua bila filter kosong |
| POST | `/api/school-rombels` | `students.manage` | Create; validasi D6/D7; tahun harus unlocked; version awal 1; audit |
| PATCH | `/api/school-rombels/{id}` | `students.manage` | Update dengan `expected_version`; 409 bila stale; tahun harus unlocked; ubah tahun/level/jurusan ditolak bila ada riwayat; version naik |
| DELETE | `/api/school-rombels/{id}` | `students.manage` | `expected_version` wajib; tahun unlocked; tolak bila ada riwayat membership; soft delete; audit |
| GET | `/api/employees/teacher-options?search=` | `employees.view` | Opsi ringan wali kelas: hanya pegawai aktif bertipe `Pendidik`, `exit_date` null/belum lewat, tidak arsip; return `id`, `name`; maks 50; tanpa PII lain |
| POST | `/api/students/{id}/assign-rombel` | `students.manage` | **Regresi 03d** + lock tahun + validasi rombel aktif/soft-deleted; perilaku sukses tetap |
| POST | `/api/students/{id}/transfer-rombel` | `students.manage` | **Regresi 03d** + lock tahun + validasi target rombel aktif; perilaku sukses tetap |

Catatan kontrak:
- `GET /api/student-rombels/{id}` tetap memakai ID tahun ajaran (baseline); jangan diubah diam-diam.
- `POST` dengan `_method=PATCH/DELETE` tetap diterima untuk kompatibilitas consumer lama, tetapi halaman Classes yang baru memakai verb yang benar.
- Error: 401 guest, 403 tanpa ability, 404 objek tidak ada/terlarang, 422 validasi, 409 version/resource conflict, 423 tidak dipakai (lock dilaporkan 422 dengan pesan jelas).
- Respons list tetap memuat `data` + `meta` bila paginasi ditambahkan; 06a tidak perlu paginasi kelas karena jumlah kelas kecil, tetapi filter tahun wajib didukung.

### 5.4 Aturan validasi dan bisnis

- `name`: wajib, trim, max 100; unik case-insensitive per `school_year_id` di antara baris non-deleted; nama boleh sama di tahun berbeda.
- `school_year_id`: wajib, ada, tidak soft-deleted, **tidak locked** untuk create/update/delete.
- `school_level_id`: wajib, ada, tidak soft-deleted.
- `student_major_id`: opsional; bila diisi harus ada dan tidak soft-deleted. Tidak ada aturan “wajib untuk level X” karena schema belum memiliki pemetaan level–jurusan.
- `class_teach_employee_id`: opsional; bila diisi harus pegawai aktif `Pendidik` (bukan `Tenaga Kependidikan`, tidak arsip, `exit_date` null/belum lewat).
- `class_teach_id` lama: hanya untuk kompatibilitas; UI baru tidak mengirim field ini. Bila dikirim, server memvalidasi user ada/aktif dan tidak mengubah role/ability.
- `expected_version`: wajib untuk update/delete; nilai harus sama dengan `version` saat ini; 409 menyertakan `current_version` dan data terbaru yang aman.
- Hapus kelas: tolak bila `student_rombels` (termasuk `withTrashed`) merujuk kelas; tolak bila tahun locked.
- Tahun:
  - `start_date`/`end_date` opsional; bila keduanya diisi, `start_date < end_date`.
  - Lock membutuhkan alasan; unlock membutuhkan alasan; tahun aktif tidak boleh lock.
  - Tahun yang masih memiliki kelas tidak boleh dihapus (termasuk kelas soft-deleted) dan tahun aktif/locked tidak boleh dihapus.
- Set periode aktif:
  - Tahun harus unlocked; semester harus ada; tidak boleh set tahun soft-deleted.
  - Bila `active_school_year_id` berubah, UI memuat ulang daftar siswa/kelas dan menampilkan banner.
- Assign/transfer:
  - Validasi tahun dan rombel harus tidak soft-deleted.
  - Lock tahun diperiksa **di dalam transaksi** setelah row lock, bukan hanya di FormRequest.
  - Perilaku single-active, version, dan preservasi riwayat 03d tidak berubah.
- Semua tanggal efektif memakai `Asia/Jakarta`; tidak ada input tanggal mundur untuk assign/transfer (tetap seperti DEC-23).

### 5.5 Konkurensi dan urutan lock

Urutan lock yang ditetapkan untuk mencegah deadlock:

1. `academic_settings` (hanya operasi set active period dan lock/unlock).
2. `school_years` (baris tahun yang terlibat).
3. `students` (bila operasi menyentuh siswa).
4. `student_rombels` (membership).
5. `school_rombels` (bila perlu update kelas).

- Assign/transfer: lock tahun → lock siswa → cek membership → tulis. Tidak perlu lock `academic_settings`.
- Create/update/delete kelas: lock tahun → lock kelas (update/delete) → validasi → tulis.
- Lock/unlock tahun: lock `academic_settings` → lock tahun → validasi status aktif → tulis.
- Set active period: lock `academic_settings` → lock tahun target → validasi unlocked → tulis.
- Semua operasi yang mengubah `version` memakai transaksi dan pengecekan versi di dalam transaksi; konflik mengembalikan 409.
- Tes rollback harus memicu kegagalan (mis. audit insert gagal) dan memastikan tidak ada perubahan parsial.

### 5.6 UI (English)

1. **School Years** (route tetap `/public/schoolyears`, `master.view`):
   - Kartu **Academic Period**: pilihan Active School Year + Active Semester + tombol Save; hanya aktif untuk `master.manage`; status “No active period” bila null.
   - Tabel: Name, Start Date, End Date, Active, Locked, Actions.
   - Aksi Set Active, Lock (dialog alasan), Unlock (dialog alasan); tombol Delete dinonaktifkan untuk tahun aktif/locked/berisi kelas.
   - Banner peringatan bila tahun terkunci; semua teks English.
2. **Classes** (route `/public/schoolrombels`, guard `students.view`):
   - Filter tahun (default active year), level, jurusan, wali kelas; kolom Name, Year, Level, Major, Homeroom Teacher, Members, Version, Actions.
   - Dialog create/edit: year (unlocked only), level, major optional, homeroom teacher autocomplete dari `teacher-options`; nama; validasi inline 422; 409 conflict + Reload Latest.
   - Tombol New/Edit/Delete hanya untuk `students.manage` dan tahun tidak locked; server tetap penegak utama.
   - Konfirmasi delete menyebut jumlah riwayat membership bila ada dan menjelaskan penolakan.
3. **Students** (regresi):
   - Ganti perhitungan `years_combine` dengan periode aktif dari `/api/academic/period`; bila belum ada active period, tampilkan banner setup dan tetap izinkan pemilihan tahun manual untuk melihat data.
   - Tampilkan nama tahun + semester aktif; badge Locked pada tahun terpilih.
   - Tombol Assign/Transfer dinonaktifkan saat tahun locked; server menolak dengan 422.
4. **Menu**:
   - Grup **Academic** berisi Classes (`students.view`) dan School Years (`master.view`), atau bila pemilik memilih alternatif D5, ikuti keputusan tersebut. Tidak ada menu Semesters baru pada 06a.

### 5.7 Matriks tes 06a (rencana; belum dijalankan)

Backend (SQLite in-memory terisolasi):
1. Guest 401; `students.view` hanya baca; `students.manage` dapat CRUD kelas; `master.view` tidak dapat CRUD kelas; `master.manage` dapat lock/set active.
2. Set active period: hanya satu pasangan aktif; tahun locked ditolak; tahun soft-deleted ditolak; semester tidak ada ditolak; perubahan diaudit; dua request bersamaan tidak menghasilkan dua active.
3. Lock/unlock: alasan wajib; tahun aktif tidak bisa lock; lock ganda/unlock ganda aman; audit mencatat alasan; tahun locked menolak create/update/delete kelas dan assign/transfer.
4. Create kelas: nama wajib; unik per tahun; nama sama di tahun berbeda diterima; level wajib; jurusan opsional; wali harus `Pendidik` aktif; wali `Tenaga Kependidikan`/arsip/exit ditolak; tahun soft-deleted ditolak.
5. Update kelas: `expected_version` wajib; stale 409; version naik; ubah tahun/level/jurusan dengan riwayat ditolak; nama/wali masih bisa diubah; audit.
6. Delete kelas: `expected_version` wajib; kelas tanpa riwayat soft delete; kelas dengan membership aktif/nonaktif/soft-deleted ditolak; tahun locked ditolak.
7. Assign/transfer regression: semua 16 kasus 03d tetap PASS; tambah tahun locked 422; rombel soft-deleted 422; tahun soft-deleted 422; single-active dan rollback audit tetap PASS.
8. `teacher-options`: hanya Pendidik aktif; search; limit; tanpa PII; 403 tanpa `employees.view`.
9. `academic/period`: siswa.view dapat membaca; master.view-only (bila ada) sesuai keputusan D5; tidak ada mutasi pada GET.
10. Tahun: update tanggal validasi; delete tahun aktif/locked/berisi kelas (termasuk kelas soft-deleted) ditolak; delete tahun tanpa kelas berhasil.
11. Audit: perubahan kelas/periode/lock tercatat dengan allowlist; tidak ada PII di log.
12. Rollback: kegagalan audit pada create/update/delete kelas dan set active period membatalkan seluruh transaksi.
13. Regresi: `StudentRombelConstraintTest`, `StudentRombelResolutionTest`, `StudentRombelAssignmentTest`, dan tes student directory tetap PASS.

Frontend:
- Unit test helper parameter/filter (bila ada).
- Build dan ESLint non-mutasi.
- Browser/UAT: checklist manual; bila browser tidak tersedia, tulis NOT RUN dan sertakan langkah manual.

### 5.8 UAT 06a (setelah implementasi, bukan sekarang)

- Runtime `.uat-phase06` sesuai D8; fixture idempotent; tidak menyentuh `.uat-phase05`, database operasional, atau MariaDB pemilik.
- Checklist manual minimal:
  1. Login operator; set active year/semester; hanya satu aktif.
  2. Tahun aktif tidak dapat dikunci; lock tahun lain dengan alasan.
  3. Tahun locked menolak New/Edit/Delete kelas dan Assign/Transfer (UI + API).
  4. Create kelas dengan wali `Pendidik`; opsi hanya guru aktif; staf tidak muncul.
  5. Create kelas tanpa jurusan/wali (opsional) berhasil.
  6. Nama kelas duplikat di tahun sama ditolak; nama sama di tahun berbeda diterima.
  7. Edit kelas; stale version 409 + Reload Latest.
  8. Delete kelas kosong berhasil; delete kelas dengan membership ditolak.
  9. Students page memakai active year dari server; banner dan badge lock tampil.
  10. View-only principal tidak dapat mengubah kelas/lock.
  11. Audit lock/kelas/periode tercatat.
  12. UI English dan 390 px tanpa overflow.
- Owner approval dicatat pada commit dokumentasi terpisah; server UAT dihentikan setelah penerimaan.

### 5.9 Kriteria penerimaan 06a (pemetaan)

- Tahun kosong/locked tertangani: set active period kosong menampilkan banner; tahun locked menolak operasi dengan pesan jelas.
- Create/update membership tervalidasi: assign/transfer tetap memakai validasi 03d + lock/soft-delete.
- Siswa tidak berada di dua rombel yang dilarang: trigger/model single-active tetap berlaku; tidak ada perubahan aturan.
- CRUD kelas benar-benar berfungsi (bukan scaffold) dan wali kelas tersimpan.
- Tidak ada regresi pada 03d dan data lama.

---

## 6. Garis besar Subfase 06b — Naik kelas, tinggal kelas, lulus

Belum diimplementasikan. Keputusan rinci akan diajukan dalam rencana 06b setelah 06a selesai dan disetujui.

- **Preview tanpa mutasi**: pilih tahun sumber, tahun tujuan, pemetaan kelas sumber → kelas tujuan, dan keputusan per siswa (`promote`, `retain`, `graduate`, `skip`). Preview menampilkan per siswa: status, kelas tujuan, alasan tidak eligible, konflik (sudah punya membership di tahun tujuan, siswa arsip, tidak ada membership aktif di sumber, kelas tujuan invalid/locked, versi berubah).
- **Commit atomik dan bounded**: token preview mengikat payload + versi siswa/membership; commit all-or-nothing, maksimum 500 siswa per batch; retry dengan token sama idempotent; konflik 409 menyertakan siswa yang berubah.
- **Aturan status/tanggal**:
  - `promote`: membuat membership baru di tahun tujuan dengan status `Aktif`, tanggal masuk = `start_date` tahun tujuan (D2), source membership tidak diubah (riwayat tahun lama utuh).
  - `retain`: **tidak membuat** membership di tahun tujuan; siswa menjadi Unplaced di tahun tujuan; source membership tidak diubah.
  - `graduate`: mengubah source membership menjadi `Lulus` + tanggal lulus; tanggal keluar = tanggal lulus; **tidak membuat** membership tahun tujuan.
  - `skip`: tidak ada mutasi.
- **Lock**: tahun tujuan wajib unlocked; tahun sumber boleh locked untuk `promote`/`retain` karena tidak mengubah source; `graduate` yang mengubah source memerlukan source unlocked atau alur koreksi 06c.
- **UI**: halaman/wizard Promotion (Academic) dengan pilihan tahun, pemetaan kelas, tabel keputusan, ringkasan konflik, konfirmasi commit, hasil per siswa; English.
- **Tes**: preview tidak mengubah DB; commit idempotent/retry; stale token 409; campuran promote/retain/graduate; decoy tidak berubah; rollback audit; concurrent commit; sumber locked vs target locked; batch bound.
- **Pertanyaan terbuka 06b (Q1–Q6)**:
  1. Pemetaan target: satu kelas tujuan per kelas sumber, dengan exception per siswa?
  2. Apakah siswa `retain` tetap ditampilkan sebagai Unplaced di tahun tujuan atau perlu penanda khusus?
  3. Tanggal efektif `promote` memakai `start_date` tahun tujuan; bagaimana bila `start_date` null?
  4. Apakah `graduate` mengisi `latest_student_exit_date` atau hanya `latest_student_graduation_date`?
  5. Apakah siswa yang sudah punya membership di tahun tujuan harus diblokir total atau boleh ditimpa dengan koreksi?
  6. Batas batch 500 dan masa berlaku token (mis. 30 menit) dapat diterima?

---

## 7. Garis besar Subfase 06c — Mutasi dan koreksi

Belum diimplementasikan. Rencana rinci akan diajukan setelah 06b.

- **Mutasi masuk/keluar bertanggal**: tanggal efektif, alasan, sekolah asal/tujuan (NPSN dari `education_schools`), entry type `Mutasi Masuk`/status `Mutasi Keluar`; tidak menghapus biodata.
- **Riwayat dan pembatalan**: tabel log mutasi terpisah (mis. `student_mutations`) dengan status aktif/dibatalkan, alasan, pelaku, dan relasi ke membership; pembatalan memerlukan izin + alasan + audit, tidak menghapus baris log.
- **Rekap per periode**: jumlah masuk/keluar/lulus/tinggal per tahun (dan semester bila D1 menghendaki periode per semester).
- **Koreksi periode terkunci**: endpoint khusus ter-audit, hanya untuk kasus yang disetujui, dengan alasan wajib; tidak ada edit inline atau hapus data.
- **UI**: dialog mutasi dari halaman Students, riwayat mutasi, rekap; English.
- **Tes**: tanggal/status konsisten; mutasi pada tahun locked ditolak kecuali alur koreksi; pembatalan tidak menghapus log; decoy tidak berubah; rollback.
- **Pertanyaan terbuka 06c (Q1–Q5)**:
  1. Apakah mutasi masuk membuat membership baru atau mengubah membership yang ada?
  2. Apakah pembatalan mutasi mengembalikan status membership sebelumnya secara otomatis?
  3. Siapa yang berwenang atas koreksi periode terkunci (`students.manage` + syarat tambahan)?
  4. Apakah rekap per semester diperlukan, atau cukup per tahun ajaran?
  5. Apakah mutasi keluar harus menutup semua membership aktif atau hanya membership tahun berjalan?

---

## 8. Batas pekerjaan (out of scope)

- Tidak ada nilai/rapor, jadwal pelajaran, penerimaan siswa online, Dapodik otomatis, atau integrasi eksternal.
- Tidak ada kenaikan kelas otomatis berbasis level; target kelas harus eksplisit.
- Tidak ada penggabungan/penghapusan siswa untuk “memperbaiki” data; koreksi melalui alur 06c.
- Tidak ada perubahan dependency, framework, `.env`, service, Apache/MariaDB, database operasional, atau database review yang dilarang.
- Tidak ada merge/deploy/force-push/rewrite history.
- Tidak ada perubahan pada `.uat-phase05`, `.uat-phase04b`, atau data UAT yang sudah accepted.

---

## 9. Risiko utama dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Dua periode aktif akibat race | Tabel singleton + transaksi + row lock; tes dua request bersamaan |
| Lock tahun berbalapan dengan assign/transfer | Lock tahun di dalam transaksi; urutan lock tetap; tes konkurensi |
| Data kelas existing duplikat/soft-deleted | Audit agregat sebelum unique; validasi baru; jangan hapus/merge otomatis |
| Wali kelas lama ambigu (`users` vs `employees`) | Backfill hanya relasi tunggal; sisanya null dan dilaporkan; UI baru memakai employee |
| Pengguna kehilangan akses karena route/menu berubah | Matriks role diuji (principal, operator, admin, view-only); server tetap penegak utama |
| Tahun aktif kosong setelah migration | Banner setup; tidak menebak; UAT mengisi active period; dokumentasi deployment |
| `start_date` null menghambat 06b | D2; validasi preview 06b; operator melengkapi sebelum promosi |
| Halaman Classes generic tidak mendukung version/lock | Halaman khusus; API proper verbs; 409 + Reload Latest |
| Lock membuat operasi darurat tidak mungkin | Unlock berizin + alasan + audit; koreksi terkunci 06c |
| Bukti UAT 05 berubah | Runtime `.uat-phase06` terpisah; tidak menyentuh `.uat-phase05` |
| Perbedaan SQLite vs MariaDB | Tes SQLite untuk logika; uji MariaDB disposable hanya bila tersedia; jangan klaim PASS tanpa bukti |

---

## 10. Verifikasi dan bukti

- Rencana ini: hanya dokumentasi. Tidak ada tes yang dijalankan; semua status implementasi **NOT STARTED**.
- Implementasi 06a nanti:
  - Backend: `php artisan test` untuk berkas terkait setelah isolasi terbukti; `./vendor/bin/pint --test <file>`; tidak memakai `migrate:fresh`/reset; tidak menyentuh DB operasional.
  - Frontend: `npm run build`; `npx eslint <file>` non-mutasi (bukan `npm run lint` yang memakai `--fix`).
  - Browser/UAT: hanya diklaim PASS bila benar-benar dijalankan; jika tidak, tulis **NOT RUN** dan sertakan checklist manual.
  - Hasil tes tidak diagregasikan antar subfase; laporan mencatat perintah, hasil, SHA, dan working tree.
- Commit/push: paket rencana ini di-push agar dapat dibaca di GitHub; push bukan penerimaan. Implementasi 06a hanya setelah D1–D8 disetujui dan dicatat sebagai DEC; penerimaan manual pemilik dicatat pada commit dokumentasi terpisah.
- Sebelum push: selective staging; `git diff --cached --check`; jangan memasukkan `.uat-*`, database/log/runtime, kredensial, `console.txt`, atau `vue-app/test-results/`.

---

## 11. Yang dikerjakan setelah rencana ini disetujui

1. Catat D1–D8 sebagai DEC baru di `docs/DECISIONS.md`; perbarui register dan status rencana.
2. Implementasi **06a saja**: migration additive, model/policy/audit, endpoint periode/lock/kelas, penyesuaian assign/transfer, UI School Years/Classes/Students, tes, laporan.
3. Siapkan `.uat-phase06` + fixture sintetis; jangan menyentuh UAT 05 atau database operasional.
4. Serahkan laporan + checklist manual 06a; berhenti sampai pemilik menyetujui.
5. Jangan memulai 06b/06c tanpa rencana dan keputusan terpisah.

**Tidak termasuk dalam persetujuan rencana ini:** merge, deploy, migrasi database operasional, perubahan dependency/service, dan pelaksanaan 06b/06c.
