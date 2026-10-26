# Rencana Subfase 04a — Schema Profil Pegawai dan Hubungan Akun

Tanggal: 2026-10-26 (WIB). Branch: `main`.
Status: **APPROVED BY OWNER (2026-10-26 WIB; KEPUTUSAN DEC-04 DISAHKAN)**.
Subfase: **04a — Schema Profil dan Hubungan Akun** (Fase 04: Guru dan Pegawai).

> **Pernyataan Batas Scope:** Subfase 04a difokuskan strictly pada **fondasi schema, model relasi, dan kebijakan hubungan akun**. CRUD lengkap, form input, antarmuka pengguna (UI), dan tes endpoint create 201/update 409 berada pada Subfase **04b**. Subfase 04a tidak mengaktifkan endpoint baru yang belum berimplementasi lengkap.

---

## 1. Konteks dan Tujuan Subfase 04a

Setelah Fase 01, 02, dan 03 (03a, 03b, 03c, serta 03d) selesai dan **DITERIMA LENGKAP & DITUTUP** oleh pemilik, roadmap berlanjut ke **Fase 04: Guru dan Pegawai**.

Subfase 04a berfokus pada fondasi schema dan pemodelan domain data personalia sekolah:
1. Memperbaiki relasi akun pengguna dengan profil pegawai agar akun login bersifat independen/opsional (DEC-04).
2. Memperbaiki nullability `subject_id` agar pegawai non-guru (tenaga kependidikan) dapat dicatat secara sah tanpa dipaksa memilih mata pelajaran.
3. Menambahkan kolom kontrol konkurensi atomik (`version`) pada tabel `employees` untuk perlindungan dari race condition.
4. Memperbaiki relasi model, allowlist privasi audit trail, serta menegakkan otorisasi berbasis kemampuan bernama (`employees.view`, `employees.manage`).

---

## 2. Hasil Audit Statis Source Baseline

Pemeriksaan terhadap berkas baseline menghasilkan temuan-temuan konkret sebagai berikut:

### 2.1 Database & Schema (`2026_10_15_161306_create_employees_table.php`)
- **Keterikatan Wajib ke Akun User via String NIK**:
  ```php
  $table->string('user_nik');
  $table->foreign('user_nik')->references('nik')->on('users');
  ```
  *Masalah*: Setiap baris pegawai wajib terhubung ke baris `users` melalui NIK string. Di sekolah nyata, banyak pegawai non-operasional (misal penjaga sekolah, kebersihan, tenaga honorer lepas) yang tidak memiliki dan tidak memerlukan akun login ke sistem. Keterikatan via NIK string juga rentan terhadap inkonsistensi jika format NIK berubah.
- **Kolom `subject_id` Wajib (NOT NULL)**:
  ```php
  $table->unsignedBigInteger('subject_id');
  $table->foreign('subject_id')->references('id')->on('subjects');
  ```
  *Masalah*: Seluruh pegawai dipaksa memiliki mata pelajaran. Staf Tata Usaha, Pustakawan, Laboran, dan Satpam yang bertipe `Tenaga Kependidikan` tidak mengampu mata pelajaran. Ini adalah defect arsitektur baseline yang menghambat pendataan pegawai non-guru.
- **Ketiadaan Kolom Versi (`version`)**:
  Tabel `employees` saat ini tidak memiliki kolom `version`, sehingga belum memiliki fondasi optimistic concurrency control (HTTP 409) seperti halnya tabel `students`.
- **Tabel Referensi Pegawai yang Sudah Ada**:
  - `employee_types`: ID 1 (`Tenaga Kependidikan`), ID 2 (`Pendidik` / Guru).
  - `employee_statuses`: PTT, PTY, GTT, GTY, PNS, PPPK.
  - `employments`: status kepegawaian yayasan/sekolah.

### 2.2 Model Eloquent (`laravel-api/app/Models/Employee.php`)
- **Typo Relasi Agama**:
  Line 111: `public function relogion(): BelongsTo` (salah ketik: `relogion` seharusnya `religion`).
- **Ketiadaan Allowlist Audit Trail (`auditableAttributes`)**:
  Model `Employee` memakai trait `Loggable`, tetapi tidak mendefinisikan array `$auditableAttributes`. Akibatnya, seluruh atribut yang dimutasi akan dicatat ke `activities`, termasuk PII yang sangat sensitif seperti:
  - `biological_mothers_name` (nama ibu kandung — rahasia finansial/perbankan).
  - `partners_name` (nama pasangan).
  - `no_kk` (nomor kartu keluarga).
  - `npwp` (nomor pokok wajib pajak).
  - Nilai/IPK ijazah (`passing_grade`).

### 2.3 Route & Controller Backend
- **Route API Singular & Salah Hak Akses**:
  Di `laravel-api/routes/api.php:188-191`:
  ```php
  Route::apiResource('/employee', EmployeeController::class)->only(['index', 'show'])->middleware('can:master.view');
  Route::apiResource('/employee', EmployeeController::class)->except(['index', 'show'])->middleware('can:master.manage');
  ```
  *Masalah*: Menggunakan endpoint singular `/employee` (bukan plural standar `/employees`) dan menggunakan permission `master.view`/`master.manage` alih-alih `employees.view`/`employees.manage` yang sudah disiapkan di `AbilityCatalog`.
- **Controller & Policy Masih Scaffold Kosong**:
  - `EmployeeController.php`: Method `store`, `show`, `update`, `destroy` adalah stub kosong tanpa implementasi.
  - `EmployeePolicy.php`: Seluruh method policy mengembalikan kosong (null/unauthorized).
  - `StoreEmployeeRequest.php` & `UpdateEmployeeRequest.php`: Method `authorize()` mengembalikan `false`.

### 2.4 Antarmuka Pengguna (Frontend Vue SPA)
- Di `vue-app/src/layout/AppMenu.vue:38-39`:
  ```javascript
  { label: 'Teachers', icon: 'pi pi-fw pi-slack', to: '#' },
  { label: 'Employees', icon: 'pi pi-fw pi-address-book', to: '#' },
  ```
  Menu navigasi masih mengarah ke placeholder dummy `#`. Belum ada router path atau halaman Vue untuk mengelola personalia guru dan pegawai.

---

## 3. Rincian Usulan Scope Subfase 04a

Subfase 04a dibatasi secara ketat pada fondasi **schema, model relasi, dan kebijakan hubungan akun**:

### 3.1 Pekerjaan yang Termasuk dalam Scope 04a (In-Scope)
1. **Persetujuan Keputusan Pemilik DEC-04**:
   - Menetapkan bahwa profil pegawai boleh ada tanpa akun login pengguna (`user_id` nullable).
   - Menetapkan bahwa pegawai non-guru tidak wajib mengampu mata pelajaran (`subject_id` nullable).
2. **Migration Additive Baru pada Database**:
   - Dibuat migration baru: `xxxx_xx_xx_xxxxxx_update_employees_schema_for_phase_04a.php`.
   - Menambahkan kolom `user_id` (`foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()`) di samping kolom legacy `user_nik` agar transisi aman tanpa merusak data lama.
   - Mengubah `user_nik` menjadi `nullable` (pegawai tanpa akun tidak wajib memiliki NIK yang terdaftar di tabel `users`).
   - Mengubah `subject_id` menjadi `nullable` (`$table->unsignedBigInteger('subject_id')->nullable()->change()`).
   - Menambahkan kolom `version` bertipe `unsignedInteger()->default(1)` untuk kontrol konkurensi atomik.
3. **Koreksi Model `Employee`**:
   - Memperbaiki typo method relasi: `relogion()` -> `religion()`, dengan mempertahankan alias kompatibilitas `relogion()` untuk keamanan consumer existing.
   - Menjadikan `user_id` relasi utama `user()`, dengan fallback aman ke `user_nik` hanya untuk data legacy yang belum termigrasi.
   - Menerapkan allowlist ketat `auditableAttributes` pada model `Employee` agar atribut pribadi sensitif (nama ibu kandung, pasangan, nomor KK, NPWP, IPK) tidak dicatat ke log audit.
4. **Persiapan Policy Otorisasi**:
   - Menyiapkan `EmployeePolicy` dengan penegakan kemampuan bernama `employees.view` dan `employees.manage`.
   - Endpoint CRUD/form aktif tidak diaktifkan pada 04a; normalisasi route `/employees` dan transisi consumer lengkap berada pada 04b.
5. **Backfill Pemadanan Akun (Service Idempotent)**:
   - Skrip/service backfill idempotent untuk mengisi `user_id` pada baris pegawai existing yang memiliki kesesuaian unik dengan `users.nik` tanpa menimpa `user_id` yang sudah ada.
   - Tidak mencetak NIK/nama/email atau PII lain ke log.
6. **Pengujian Otomatis Fondasi (SQLite & MariaDB Disposable)**:
   - Pengujian schema: profil tanpa akun, tenaga kependidikan tanpa subject, multiple user_id NULL, constraint 1 akun = 1 profil, hard delete akun melepas user_id (`nullOnDelete`), audit allowlist privacy, backfill, dan policy.

### 3.2 Pekerjaan yang Berada pada 04b (Out-of-Scope 04a)
- **CRUD Lengkap dan Form Input Pegawai**: Endpoint store/show/update/destroy berimplementasi penuh dan validasi FormRequest input bisnis (misal penegakan Guru wajib mata pelajaran).
- **Implementasi Halaman UI Vue SPA**: Halaman tabel daftar pegawai/guru, form biodata panjang, tab riwayat pendidikan, dan dialog mutasi.
- **Optimistic Concurrency Update Endpoints**: Tes dan penanganan HTTP 409 pada mutasi API.
- **Penugasan Mengajar Guru ke Rombel**: Detail jadwal mengajar per jam pelajaran dikerjakan pada **Fase 10 (Jadwal dan Jurnal)**.
- **Penugasan Wali Kelas Rombel**: Penetapan wali kelas (`class_teach_id`) pada rombel dikerjakan pada **Fase 06 (Tahun Ajaran dan Rombel)**.
- **Payroll dan Absensi Pegawai**: Modul penggajian dan absensi fingerprint/kehadiran pegawai tidak termasuk dalam cakupan aplikasi sekolah.

---

## 4. Rekomendasi Keputusan Pemilik (Proposal DEC-04)

Untuk mendukung eksekusi Subfase 04a, diajukan proposal keputusan bisnis berikut untuk ditinjau oleh pemilik:

| No | Poin Keputusan | Opsi yang Diusulkan | Alternatif yang Ditolak | Alasan Teknis / Operasional |
|---|---|---|---|---|
| 1 | **Hubungan Pegawai — Akun Login** | **Akun login bersifat OPSIONAL (`user_id` nullable)**. Profil pegawai adalah entitas personalia sekolah, bukan akun aplikasi. | Wajib memiliki akun `users` untuk setiap pegawai. | Banyak pegawai sekolah (penjaga, staf kebersihan, honorer) tidak perlu login ke aplikasi. Memaksa pembuatan akun membebani manajemen kredensial dan memperluas attack surface. |
| 2 | **Strategi Relasi Foreign Key** | **Gunakan `user_id` (bigint nullable)** sebagai relasi utama; pertahankan `user_nik` secara additive untuk kompatibilitas data lama. | Mempertahankan `user_nik` sebagai satu-satunya FK. | Mengikuti pola sukses Subfase 03a (`student_id` di samping `student_nik`). Integer ID stabil dan kebal terhadap koreksi nomor identitas string. |
| 3 | **Nullability Mata Pelajaran (`subject_id`)** | **Jadikan `subject_id` NULLABLE**. Hanya wajib diisi jika jenis pegawai adalah Guru/Pendidik; dikosongkan (NULL) untuk Tenaga Kependidikan. | Tetap NOT NULL dengan membuat mata pelajaran dummy (misal: "Non-Mengajar"). | Menghindari polusi data master mata pelajaran sekolah dengan entri tiruan. |
| 4 | **Kontrol Konkurensi Atomik** | **Wajibkan kolom `version` (default 1)** dan validasi `expected_version` pada setiap update profil pegawai (HTTP 409 Conflict). | Tanpa versioning (last-write-wins). | Melindungi data personalia dari overwrite simultan oleh dua operator sekolah (konsisten dengan arsitektur Siswa DEC-21). |
| 5 | **Privasi Audit Trail Personalia** | **Terapkan allowlist ketat atribut auditable**. Exclude nama ibu kandung, pasangan, nomor KK, NPWP, dan nilai ijazah dari tabel `activities`. | Rekam seluruh kolom tanpa filter. | Mencegah kebocoran data pribadi sensitif (PII) tingkat tinggi ke dalam log audit aplikasi. |
| 6 | **Standar Bahasa Kontrak API & UI** | **100% English** untuk endpoint (`/employees`), nama field, pesan validasi 422, dan error response. | Campuran bahasa Indonesia / English. | Konsisten dengan ketetapan DEC-14 dan seluruh endpoint Fase 01–03. |

---

## 5. Pemisahan Tegas Jalur Fitur vs Jalur Modernisasi

Pengembangan Fase 04a tetap berada pada **Jalur Fitur Domain Sekolah**:
- **Runtime Operasional**: Tetap menggunakan PHP global 8.2.35 ZTS dan Node portable 24.22.1 yang terbukti stabil.
- **Lockfile & Dependensi**: Lockfile `composer.lock` dan `package-lock.json` tidak disentuh.
- **Risiko Terbuka yang Dipisahkan**: EOL Laravel 10 (Februari 2025), EOL PHP 8.2 (Desember 2026), dan vulnerability dependency lama tetap dicatat sebagai risiko terbuka (OPEN) pada jalur terpisah, dan tidak dicampuradukkan dengan pekerjaan fitur personalia.

---

## 6. Rencana Pengujian Sintetis & Verifikasi

Sebelum implementasi dinyatakan selesai pada Subfase 04a, pengujian berikut wajib dipenuhi:
1. **SQLite In-Memory Feature Tests**:
   - `test_can_create_employee_without_user_account`: Menyimpan profil pegawai tanpa `user_id` (sukses 201).
   - `test_can_create_non_teacher_employee_without_subject`: Menyimpan staf tata usaha dengan `subject_id = null` (sukses 201).
   - `test_teacher_employee_requires_subject_or_validates_type`: Validasi aturan bisnis guru vs tenaga kependidikan.
   - `test_employee_audit_strictly_excludes_sensitive_pii`: Verifikasi field ibu kandung/KK/NPWP tidak tercatat di `activities.new` / `activities.after`.
   - `test_employee_concurrency_conflict_returns_409`: Percobaan update dengan `expected_version` usang ditolak HTTP 409 Conflict.
   - `test_employee_endpoints_enforce_named_abilities`: Hak akses 401/403 ditegakkan oleh `employees.view` dan `employees.manage`.
2. **MariaDB Integration Tests**:
   - Dijalankan pada database disposable terisolasi (misal `school_04a_integration`) dengan guard pre-bootstrap ketat.
   - Membuktikan bahwa foreign key `user_id` (`nullOnDelete`) dan nullability `subject_id` berjalan identik di MariaDB 10.4.

---

## 7. Status Eksekusi dan Keputusan DEC-04

Status dokumen: **APPROVED / DIOTORISASI EKSEKUSI OLEH PEMILIK (2026-10-26 WIB)**.
Keputusan DEC-04 telah disahkan penuh di `docs/DECISIONS.md`. Eksekusi Subfase 04a dijalankan sesuai batasan scope fondasi.
