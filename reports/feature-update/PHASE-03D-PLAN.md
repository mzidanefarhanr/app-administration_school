# Rencana Teknis Subfase 03d (Kandidat) — Penempatan dan Perpindahan Rombel via UI

Tanggal: 2026-10-26 (WIB). Branch: `main`.
Status: **DIOTORISASI OLEH PEMILIK (DEC-23, 2026-10-26 WIB)**.
Baseline acuan: `e4f6d40a95a00c863ed8aeec79d274bc5714864f`.
Status fase induk: Fase 01, 02, dan 03 telah **DITERIMA LENGKAP & DITUTUP**. Subfase 03d adalah scope tambahan yang telah diotorisasi implementasinya oleh pemilik (DEC-23).
Dependensi: Subfase 03a (DB trigger & relasi `student_id`), Subfase 03b (optimistic locking 409 & arsip administratif), Subfase 03c (server-side list, recap, & filter All/Placed/Unplaced).

> **Pernyataan Batas:** Dokumen rencana ini diperbarui sesuai Keputusan Pemilik DEC-23 (2026-10-26 WIB). Penerimaan manual untuk Subfase 03d akan diverifikasi secara terpisah setelah implementasi selesai.

---

## 1. Scope Pekerjaan

### 1.1 Scope Masuk (In-Scope)
1. **Penempatan Siswa Unplaced (Assign Rombel)**:
   - Aksi UI dan endpoint backend untuk menempatkan siswa yang belum memiliki rombel (`Unplaced`) ke salah satu rombel pada tahun ajaran aktif yang sedang dipilih di halaman Students (`school_year_id`).
2. **Perpindahan Rombel Siswa Placed (Transfer Rombel)**:
   - Aksi UI dan endpoint backend untuk memindahkan siswa yang sudah memiliki rombel (`Placed`) ke rombel lain dalam tahun ajaran yang sama secara atomik (menutup keanggotaan lama dan membuat keanggotaan baru dengan riwayat tersimpan).
   - Transfer strictly antar-rombel dalam tahun ajaran yang sama; perpindahan lintas tahun ajaran ditunda.
3. **Preservasi Riwayat & Tanggal Efektif Server**:
   - Tanggal efektif adalah tanggal hari ini di timezone `Asia/Jakarta` yang ditentukan mutlak oleh server. Tanggal mundur atau terjadwal dari input pengguna tidak didukung.
   - Keanggotaan lama ditutup dengan mencatat `latest_student_exit_date = $today`. `student_status_id` lama **tidak diubah** menjadi Mutasi Keluar.
4. **Validasi & Penegakan Integritas**:
   - Penolakan penempatan untuk siswa yang berstatus terarsip (soft-deleted).
   - Penolakan rombel yang tidak terdaftar pada tahun ajaran aktif terpilih.
   - Penegakan aturan maksimal 1 rombel aktif per tahun ajaran melalui transaksi atomik, row lock InnoDB, dan database trigger existing.
   - Optimistic concurrency control (HTTP 409 Conflict) menggunakan versioning record (`expected_student_version` dan `expected_membership_version`).
5. **Antarmuka Pengguna (UI) di `Student.vue`**:
   - Tombol aksi kondisional pada kolom `Action` DataTable (Assign Rombel untuk Unplaced, Change Rombel untuk Placed).
   - Dialog interaktif `PlacementDialog` dengan dropdown rombel tahun ajaran aktif terpilih, proteksi double-submit, loading indicator, dan pesan error inline (100% English).
   - Pembaruan instan terhadap data tabel, status placement, dan widget rekap server pasca-aksi.

### 1.2 Scope Keluar (Out-of-Scope)
- Fitur koreksi salah input / `is_correction` (ditunda per DEC-23 #1; tidak ada checkbox koreksi di UI).
- Perpindahan rombel lintas tahun ajaran (ditunda ke **Fase 06**).
- Kenaikan kelas massal atau kelulusan siswa lintas tahun ajaran (scope **Fase 06**).
- Pembuatan atau penghapusan data master rombel (scope **Fase 06**).
- Mutasi keluar sekolah (transfer-out ke sekolah lain) (scope **Fase 07**).
- Perubahan arsitektur role/permission baseline.

---

## 2. Temuan Audit Source Aktual

### 2.1 Backend (`laravel-api`)
1. **Model `StudentRombel.php`**:
   - Menggunakan trait `Loggable` dan `SoftDeletes`.
   - Kolom `auditableAttributes` telah dibatasi: `['student_id', 'student_nik', 'school_rombel', 'student_status_id', 'student_entry_id', 'archived_at', 'version']`.
   - Event `saving` model memvalidasi keanggotaan aktif: `deleted_at IS NULL AND archived_at IS NULL AND (student_status_id = 1 OR NULL) AND latest_student_exit_date IS NULL`.
   - Event melempar `DomainException` jika siswa terarsip atau jika sudah ada keanggotaan aktif di tahun ajaran yang sama.
2. **Database Triggers (MariaDB & SQLite)**:
   - Trigger `trg_student_rombels_single_active_insert` dan `trg_student_rombels_single_active_update` (Subfase 03a & 03b) aktif di database.
   - Pada MariaDB, trigger melakukan InnoDB row lock (`SELECT id, deleted_at INTO ... FROM students WHERE id = ... FOR UPDATE`) untuk menserialisasi transaksi konkuren pada siswa yang sama.
   - Trigger memblokir (`SQLSTATE 45000`) pendaftaran rombel aktif ganda pada tahun ajaran yang sama atau pendaftaran rombel untuk siswa yang `students.deleted_at IS NOT NULL`.
3. **Controller `StudentRombelController.php`**:
   - Berstatus **scaffold kosong**: method `store()`, `update()`, dan `destroy()` belum memiliki implementasi (hanya stub).
   - Method `show($id)` saat ini mengambil seluruh data rombel pada tahun ajaran `$id`.
   - FormRequest `StoreStudentRombelRequest.php` dan `UpdateStudentRombelRequest.php` memiliki `authorize() => false` dan rules kosong.
4. **Daftar & Rekap `StudentDirectory.php`**:
   - Menggunakan `activeMembershipExists()` untuk menentukan status `Placed` vs `Unplaced`.
   - Status `Unplaced` terjadi jika siswa belum memiliki record di `student_rombels` yang memenuhi syarat aktif tahun ajaran tersebut.

### 2.2 Frontend (`vue-app/src/views/pages/public/Student.vue`)
- Kolom `Action` pada DataTable siswa (`line 1262-1268`) saat ini hanya memiliki tombol `View Detail`, `Edit Student`, dan `Archive Student`.
- Terdapat filter `Placement` (`All`, `Placed`, `Unplaced`). Jika `Unplaced` dipilih, dropdown Rombel otomatis dinonaktifkan.
- Belum ada dialog atau pemanggilan API untuk menempatkan siswa ke rombel atau memindahkan rombel.

---

## 3. Alur Operasional & Kebijakan Bisnis (DEC-23)

### 3.1 Alur A: Penempatan Siswa Baru (Assign Rombel / Unplaced -> Placed)
1. Siswa dibuat melalui form create 03b (tanpa rombel) -> siswa muncul di daftar sebagai `Unplaced`.
2. Operator mengklik tombol **"Assign to Rombel"** (`pi pi-user-plus`) pada baris siswa Unplaced.
3. Dialog menampilkan: Nama Siswa, NIS/NISN, Tahun Ajaran Terpilih (read-only), Tanggal Efektif (read-only: tanggal hari ini WIB), dan Dropdown Rombel Tujuan.
4. Operator memilih rombel dan menekan tombol **"Assign Rombel"**.
5. Server memvalidasi hak akses `students.manage`, mengunci baris siswa (`FOR UPDATE`), memverifikasi ketiadaan keanggotaan aktif lain, dan membuat baris baru di `student_rombels` dengan entry date hari ini. Versi siswa dinaikkan atomik.
6. UI menampilkan toast sukses, menutup dialog, dan me-refresh DataTable serta widget rekap server (angka `Placed` bertambah 1, `Unplaced` berkurang 1).

### 3.2 Alur B: Perpindahan Rombel (Transfer / Placed -> Placed Rombel Lain)
1. Siswa saat ini berada di rombel X-1 pada tahun ajaran 2026/2027.
2. Operator mengklik tombol **"Transfer Rombel"** (`pi pi-arrow-right-arrow-left`) pada baris siswa Placed.
3. Dialog menampilkan: Nama Siswa, Rombel Saat Ini (X-1), Tahun Ajaran Terpilih (read-only), Tanggal Efektif (read-only: tanggal hari ini WIB), Dropdown Rombel Tujuan (mengecualikan rombel saat ini), dan Alasan Perpindahan.
4. Operator menekan tombol **"Transfer Student"**.
5. Server mengeksekusi transaksi atomik:
   a. Kunci baris siswa dan keanggotaan aktif saat ini (`lockForUpdate`).
   b. Verifikasi versi siswa (`expected_student_version`) dan versi keanggotaan aktif (`expected_membership_version`).
   c. Tutup keanggotaan lama: isi `latest_student_exit_date = $todayWib`, simpan catatan alasan di `latest_information`, dan naikkan version keanggotaan lama. `student_status_id` dipertahankan (tidak diubah ke Mutasi Keluar).
   d. Buat keanggotaan baru: `school_rombel = $newRombelId`, `latest_student_entry_date = $todayWib`, `student_status_id = 1`, `version = 1`.
   e. Naikkan versi siswa (`students.version = students.version + 1`).
6. Trigger database mengizinkan karena keanggotaan lama sudah memiliki `latest_student_exit_date IS NOT NULL` sehingga tidak dihitung sebagai konflik aktif.
7. UI memperbarui tampilan rombel siswa menjadi X-2; rekapitulasi per rombel otomatis diperbarui oleh server.

---

## 4. Rancangan Kontrak API & Integritas Transaksi

### 4.1 Endpoint yang Diusulkan

#### 1. `POST /api/students/{id}/assign-rombel`
- **Tujuan**: Menempatkan siswa unplaced ke rombel tahun ajaran aktif.
- **Otorisasi**: Wajib `can:students.manage`.
- **Headers**: `Accept: application/json`, `X-XSRF-TOKEN: ...`
- **Payload Request**:
  ```json
  {
    "school_year_id": 1,
    "school_rombel_id": 2,
    "expected_student_version": 1
  }
  ```
- **Validasi Server (422)**:
  - `school_year_id`: required, integer, exists:school_years,id.
  - `school_rombel_id`: required, integer, exists:school_rombels,id.
  - `expected_student_version`: required, integer.
- **Respons Sukses (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Student successfully assigned to rombel.",
    "data": {
      "student_id": 3,
      "school_rombel_id": 2,
      "school_rombel_name": "X-2",
      "student_version": 2
    }
  }
  ```
- **Respons Konflik (409 Conflict)**:
  - Versi siswa usang: `{"message": "Conflict: The student record has been updated by another user. Please reload."}`
  - Siswa sudah memiliki rombel aktif pada tahun ajaran tersebut: `{"message": "Conflict: Student already has an active rombel membership in this school year."}`

---

#### 2. `POST /api/students/{id}/transfer-rombel`
- **Tujuan**: Memindahkan siswa placed ke rombel lain pada tahun ajaran yang sama.
- **Otorisasi**: Wajib `can:students.manage`.
- **Payload Request**:
  ```json
  {
    "school_year_id": 1,
    "target_rombel_id": 2,
    "reason": "Class balancing / request from guardian",
    "expected_student_version": 1,
    "expected_membership_version": 1
  }
  ```
- **Validasi Server (422)**:
  - `school_year_id`: required, integer, exists:school_years,id.
  - `target_rombel_id`: required, integer, exists:school_rombels,id, belongs_to:school_year_id.
  - `reason`: nullable, string, max:255.
  - `expected_student_version`: required, integer.
  - `expected_membership_version`: required, integer.
- **Respons Sukses (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Student successfully transferred to new rombel.",
    "data": {
      "student_id": 3,
      "previous_rombel_id": 1,
      "new_rombel_id": 2,
      "student_version": 2
    }
  }
  ```
- **Respons Konflik (409 Conflict)**:
  - Versi siswa atau keanggotaan usang:
    `{"message": "Conflict: The student or rombel membership has been modified by another process. Please reload."}`

---

## 5. Rancangan Antarmuka Pengguna (UI) di `Student.vue`

1. **Aksi Kolom Tabel**:
   - Jika `row.placement === 'Unplaced'`: Tampilkan tombol biru outline `pi pi-user-plus` dengan tooltip `"Assign Rombel"`.
   - Jika `row.placement === 'Placed'`: Tampilkan tombol oranye outline `pi pi-arrow-right-arrow-left` dengan tooltip `"Change Rombel"`.
   - Kedua tombol dilindungi directive `v-if="canManage"` (hanya Operator Sekolah & Admin; tersembunyi bagi Kepala Sekolah view-only).
2. **Dialog `placementDialog`**:
   - Judul dinamis: `"Assign to Rombel"` (untuk unplaced) atau `"Transfer Class Rombel"` (untuk placed).
   - Form field:
     - Student Name & NIS (Read-only summary).
     - School Year: Menampilkan nama tahun ajaran yang sedang aktif dipilih (read-only).
     - Effective Date: Menampilkan tanggal hari ini WIB (read-only label).
     - Target Rombel Dropdown: Opsi rombel diambil dari daftar rombel tahun ajaran aktif terpilih (mengecualikan rombel saat ini saat transfer).
     - Transfer Reason (Textarea, hanya saat transfer).
3. **Double-Submit Guard & Feedback**:
   - Tombol submit memiliki state `:loading="isSubmittingPlacement"` dan `:disabled="isSubmittingPlacement"`.
   - Menangani error 409 Conflict dengan memunculkan `Data Conflict Detected` dialog dan me-reload data terbaru.
   - Pasca sukses: Menampilkan toast hijau `"Student successfully placed into X-1"`, menutup dialog, dan memanggil `loadStudents()` untuk menyinkronkan tabel serta widget rekap server.

---

## 6. Daftar Berkas yang Diperkirakan Berubah

| Berkas | Jenis Perubahan | Deskripsi |
|---|---|---|
| `laravel-api/routes/api.php` | Modifikasi | Mendaftarkan route `POST /api/students/{id}/assign-rombel` dan `POST /api/students/{id}/transfer-rombel`. |
| `laravel-api/app/Http/Controllers/StudentRombelController.php` | Modifikasi | Mengimplementasikan method `assignRombel()` dan `transferRombel()`, validasi ketiadaan rombel aktif ganda, dan transaksi atomik. |
| `laravel-api/app/Http/Requests/AssignStudentRombelRequest.php` | Baru | FormRequest validasi input penempatan siswa unplaced. |
| `laravel-api/app/Http/Requests/TransferStudentRombelRequest.php` | Baru | FormRequest validasi input pemindahan rombel siswa placed. |
| `vue-app/src/views/pages/public/Student.vue` | Modifikasi | Menambahkan tombol aksi Assign/Transfer di baris DataTable, dialog `placementDialog`, state manajemen form, dan error handling 409. |
| `laravel-api/tests/Feature/Student/StudentRombelAssignmentTest.php` | Baru | Suite pengujian SQLite: FormRequest, hak akses 401/403, penolakan siswa terarsip, transaksi atomik, dan audit log. |
| `laravel-api/tests/Feature/Student/MariaDbRombelConcurrencyTest.php` | Baru | Pengujian integrasi MariaDB: dua request penempatan simultan dengan expected version sama, serialisasi row lock, archive vs assign race. |
| `reports/feature-update/PHASE-03D-STUDENT-PLACEMENT.md` | Baru | Laporan implementasi dan bukti pengujian Subfase 03d setelah selesai. |
| `reports/feature-update/PHASE-03D-MANUAL-REVIEW.md` | Baru | Panduan checklist manual peninjauan pemilik untuk aksi penempatan/pemindahan rombel UI. |

---

## 7. Kriteria Penerimaan & Matriks Pengujian

| ID Pengujian | Skenario | Lingkungan | Target / Ekspektasi |
|---|---|---|---|
| **TEST-03D-01** | Guest ditolak saat assign/transfer | SQLite | HTTP 401 Unauthorized |
| **TEST-03D-02** | View-only (`uat.kepsek`) ditolak assign/transfer | SQLite | HTTP 403 Forbidden |
| **TEST-03D-03** | Operator (`uat.operator`) berhasil menempatkan siswa unplaced | SQLite | HTTP 200/201, baris `student_rombels` terbentuk, versi siswa naik |
| **TEST-03D-04** | Penempatan siswa terarsip ditolak | SQLite | HTTP 422 / DomainException |
| **TEST-03D-05** | Penempatan ke rombel tahun ajaran berbeda ditolak | SQLite | HTTP 422 Validation Error |
| **TEST-03D-06** | Penempatan siswa yang sudah punya rombel aktif ditolak | SQLite | HTTP 409 / Trigger Error |
| **TEST-03D-07** | Transfer rombel menutup baris lama (`exit_date`) dan membuat baris baru | SQLite | Baris lama `exit_date` terisi, baris baru aktif, riwayat utuh |
| **TEST-03D-08** | Transfer dengan stale `expected_student_version` ditolak | SQLite | HTTP 409 Conflict |
| **TEST-03D-09** | Concurrency: dua assign simultan pada siswa yang sama | MariaDB disposable | Tepat satu sukses, satu 409/conflict, baris aktif tetap 1 |
| **TEST-03D-10** | Concurrency: archive siswa bersamaan dengan assign rombel | MariaDB disposable | Siswa terarsip tidak pernah berakhir dengan rombel aktif |
| **TEST-03D-11** | UI Browser: Unplaced student menampilkan tombol Assign Rombel | Playwright UAT | Tombol tampil, dialog terbuka dengan rombel tahun aktif |
| **TEST-03D-12** | UI Browser: Placed student menampilkan tombol Change Rombel | Playwright UAT | Tombol tampil, rombel saat ini terindikasi, transfer sukses |
| **TEST-03D-13** | UI Browser: Rekapitulasi server pasca-penempatan | Playwright UAT | Angka Placed +1, Unplaced -1, rekap gender akurat instan |

---

## 8. Keputusan Pemilik yang Ditetapkan (DEC-23)

Keputusan pemilik telah ditetapkan pada 2026-10-26 WIB via **DEC-23**:
1. Assign dan Transfer diimplementasikan; koreksi / `is_correction` ditunda (APPROVED).
2. Menggunakan tahun ajaran aktif terpilih di halaman Students (APPROVED).
3. Transfer dibatasi antar-rombel dalam tahun ajaran yang sama (APPROVED).
4. Tanggal efektif ditentukan server hari berjalan timezone Asia/Jakarta (APPROVED).
5. Preservasi riwayat keanggotaan lama via `exit_date`, tanpa mengubah status menjadi Mutasi Keluar (APPROVED).
6. Otorisasi ditegakkan seragam via `students.manage` (APPROVED).
7. Versioning atomik via `expected_student_version` dan `expected_membership_version` (APPROVED).
