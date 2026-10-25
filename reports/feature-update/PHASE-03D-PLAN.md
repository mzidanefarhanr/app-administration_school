# Rencana Teknis Subfase 03d (Kandidat) — Penempatan dan Perpindahan Rombel via UI

Tanggal: 2026-10-26 (WIB). Branch: `main`.
Status: **RANCANGAN TEKNIS (BELUM DIOTORISASI UNTUK IMPLEMENTASI)**.
Baseline acuan: `80b4507fe9610c66861ccdaa9c035b64dfcc749a`.
Status fase induk: Fase 01, 02, dan 03 telah **DITERIMA LENGKAP & DITUTUP**. Subfase 03d adalah kandidat scope tambahan pelengkap operasional siswa (DEC-22 #5).
Dependensi: Subfase 03a (DB trigger & relasi `student_id`), Subfase 03b (optimistic locking 409 & arsip administratif), Subfase 03c (server-side list, recap, & filter All/Placed/Unplaced).

> **Pernyataan Batas:** Dokumen ini HANYA berisi audit source dan rencana teknis. **Tidak ada pekerjaan kode, migrasi database, atau eksekusi mutasi pada sesi ini.** Implementasi menunggu otorisasi terpisah dari pemilik.

---

## 1. Scope Pekerjaan

### 1.1 Scope Masuk (In-Scope)
1. **Penempatan Siswa Unplaced**:
   - Aksi UI dan endpoint backend untuk menempatkan siswa baru yang belum memiliki rombel (`Unplaced`) ke salah satu rombel pada tahun ajaran aktif yang sedang dipilih.
2. **Perpindahan Rombel Siswa Placed (Mutasi Kelas)**:
   - Aksi UI dan endpoint backend untuk memindahkan siswa yang sudah memiliki rombel (`Placed`) ke rombel lain dalam tahun ajaran yang sama secara atomik (menutup keanggotaan lama dan membuat keanggotaan baru dengan riwayat tersimpan).
3. **Koreksi Penempatan (Reassignment / Correction)**:
   - Mekanisme koreksi bila operator salah memilih rombel saat input awal tanpa mengotori riwayat mutasi siswa.
4. **Validasi & Penegakan Integritas**:
   - Penolakan penempatan untuk siswa yang berstatus terarsip (soft-deleted).
   - Penolakan rombel yang tidak terdaftar pada tahun ajaran aktif.
   - Penegakan aturan maksimal 1 rombel aktif per tahun ajaran melalui transaksi atomik, row lock InnoDB, dan database trigger existing.
   - Optimistic concurrency control (HTTP 409 Conflict) menggunakan versioning record.
5. **Antarmuka Pengguna (UI) di `Student.vue`**:
   - Tombol aksi kondisional pada kolom `Action` DataTable (Assign Rombel untuk Unplaced, Change Rombel untuk Placed).
   - Dialog interaktif `PlacementDialog` dengan dropdown rombel tahun ajaran aktif, proteksi double-submit, loading indicator, dan pesan error inline.
   - Pembaruan instan terhadap data tabel, status placement, dan widget rekap server pasca-aksi.
   - Seluruh teks dan pesan antarmuka dalam bahasa Inggris (English).

### 1.2 Scope Keluar (Out-of-Scope)
- Kenaikan kelas massal atau kelulusan siswa lintas tahun ajaran (merupakan scope **Fase 06 — Siklus Tahun Ajaran dan Rombel**).
- Pembuatan, pengubahan kapasitas, atau penghapusan data master rombel (scope **Fase 06**).
- Mutasi keluar sekolah (transfer-out ke sekolah lain) atau surat mutasi (scope **Fase 07 — Persuratan**).
- Perubahan arsitektur role/permission baseline atau penambahan peran baru.

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

## 3. Alur Operasional & Kebijakan Bisnis

### 3.1 Alur A: Penempatan Siswa Baru (Assign Rombel / Unplaced -> Placed)
1. Siswa dibuat melalui form create 03b (tanpa rombel) -> siswa muncul di daftar sebagai `Unplaced`.
2. Operator mengklik tombol **"Assign to Rombel"** (`pi pi-user-plus`) pada baris siswa Unplaced.
3. Dialog menampilkan: Nama Siswa, NIS/NISN, Tahun Ajaran Terpilih (read-only), Dropdown Pilihan Rombel (hanya rombel pada tahun ajaran tersebut), dan Tanggal Masuk (default: hari ini).
4. Operator memilih rombel dan menekan tombol **"Assign Rombel"**.
5. Server memvalidasi hak akses `students.manage`, mengunci baris siswa (`FOR UPDATE`), memverifikasi ketiadaan keanggotaan aktif lain, dan membuat baris baru di `student_rombels`.
6. UI menampilkan toast sukses, menutup dialog, dan me-refresh DataTable serta widget rekap server (angka `Placed` bertambah 1, `Unplaced` berkurang 1).

### 3.2 Alur B: Perpindahan Rombel (Transfer / Placed -> Placed Rombel Lain)
1. Siswa saat ini berada di rombel X-1 pada tahun ajaran 2026/2027.
2. Operator mengklik tombol **"Transfer Rombel"** (`pi pi-arrow-right-arrow-left`) pada baris siswa Placed.
3. Dialog menampilkan: Nama Siswa, Rombel Saat Ini (X-1), Dropdown Rombel Tujuan (misal X-2, mengecualikan rombel saat ini), Tanggal Efektif Pindah, dan Alasan Perpindahan.
4. Operator menekan tombol **"Transfer Student"**.
5. Server mengeksekusi transaksi atomik:
   a. Kunci baris siswa dan keanggotaan aktif saat ini (`lockForUpdate`).
   b. Verifikasi versi keanggotaan aktif (`optimistic concurrency check`).
   c. Tutup keanggotaan lama: isi `latest_student_exit_date = $transferDate`, simpan catatan alasan di `latest_information`, dan naikkan version.
   d. Buat keanggotaan baru: `school_rombel = $newRombelId`, `latest_student_entry_date = $transferDate`, `student_status_id = 1`, `version = 1`.
6. Trigger database mengizinkan karena keanggotaan lama sudah memiliki `latest_student_exit_date IS NOT NULL` sehingga tidak dihitung sebagai konflik aktif.
7. UI memperbarui tampilan rombel siswa menjadi X-2; rekapitulasi per rombel otomatis diperbarui oleh server.

### 3.3 Alur C: Pembedaan "Koreksi Salah Penempatan" vs "Perpindahan Kelas Nyata"
- **Masalah Kebijakan**: Jika operator salah memasukkan siswa ke rombel X-1 lima menit lalu karena typo, mencatatnya sebagai mutasi keluar dengan tanggal keluar akan merusak data historis siswa seolah-olah siswa pernah belajar di kelas tersebut.
- **Rancangan Kebijakan (Diajukan untuk Konfirmasi Pemilik)**:
  - **Pilihan 1 (Koreksi Langsung / Update Baris)**: Jika penempatan baru dilakukan pada tanggal yang sama dan belum ada data akademik (nilai/absensi), operator dengan izin `students.manage` dapat memilih opsi *"Correct Placement (Fix Entry Mistake)"*. Aksi ini meng-update kolom `school_rombel` pada record yang sama tanpa membuat riwayat keluar.
  - **Pilihan 2 (Mutasi Resmi / Move Rombel)**: Jika perpindahan kelas terjadi setelah proses belajar berjalan, sistem mewajibkan alur mutasi resmi: menutup record lama dengan tanggal keluar dan membuka record baru.
  - Rekomendasi: Sediakan flag `is_correction: boolean` pada payload transfer. Jika `is_correction = true`, sistem meng-update `school_rombel` baris aktif. Jika `false`, sistem membuat baris baru dan menutup baris lama.

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
    "entry_date": "2026-10-26",
    "student_entry_id": 1
  }
  ```
- **Validasi Server (422)**:
  - `school_year_id`: required, integer, exists:school_years,id.
  - `school_rombel_id`: required, integer, exists:school_rombels,id, belongs_to:school_year_id.
  - `entry_date`: nullable, date_format:Y-m-d.
  - `student_entry_id`: nullable, integer, exists:student_entries,id.
- **Respons Sukses (201 Created)**:
  ```json
  {
    "success": true,
    "message": "Student successfully assigned to rombel.",
    "data": {
      "id": 10,
      "student_id": 3,
      "school_rombel_id": 2,
      "school_rombel_name": "X-2",
      "version": 1
    }
  }
  ```
- **Respons Konflik (409 Conflict)**:
  - Siswa sudah memiliki rombel aktif pada tahun ajaran tersebut:
    `{"message": "Conflict: Student already has an active rombel membership in this school year."}`

---

#### 2. `POST /api/students/{id}/transfer-rombel`
- **Tujuan**: Memindahkan siswa placed ke rombel lain pada tahun ajaran yang sama.
- **Otorisasi**: Wajib `can:students.manage`.
- **Payload Request**:
  ```json
  {
    "school_year_id": 1,
    "current_rombel_id": 1,
    "target_rombel_id": 2,
    "transfer_date": "2026-10-26",
    "reason": "Class balancing / request from guardian",
    "is_correction": false,
    "expected_version": 1
  }
  ```
- **Validasi Server (422)**:
  - `school_year_id`: required, integer, exists:school_years,id.
  - `current_rombel_id`: required, integer, exists:school_rombels,id.
  - `target_rombel_id`: required, integer, exists:school_rombels,id, different:current_rombel_id, belongs_to:school_year_id.
  - `transfer_date`: required, date_format:Y-m-d.
  - `reason`: required_if:is_correction,false, string, max:255.
  - `expected_version`: required, integer.
- **Respons Sukses (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Student successfully transferred to new rombel.",
    "data": {
      "student_id": 3,
      "previous_rombel_id": 1,
      "new_rombel_id": 2,
      "new_version": 1
    }
  }
  ```
- **Respons Konflik (409 Conflict)**:
  - Versi keanggotaan telah berubah:
    `{"message": "Conflict: The student rombel membership has been modified by another operator. Please reload."}`

---

### 4.2 Integritas Transaksi & Koordinasi Lock Database
Implementasi backend wajib membungkus seluruh mutasi dalam transaksi database atomik:
```php
DB::transaction(function () use ($studentId, $targetRombelId, ...) {
    // 1. Kunci baris siswa untuk mencegah penempatan simultan
    $student = Student::where('id', $studentId)->lockForUpdate()->firstOrFail();

    // 2. Tolak jika siswa terarsip (DEC-21)
    if ($student->trashed()) {
        abort(422, 'Cannot assign rombel to an archived student.');
    }

    // 3. Kunci dan ambil keanggotaan aktif saat ini
    $activeMembership = StudentRombel::where('student_id', $student->id)
        ->whereHas('schoolRombel', fn($q) => $q->where('school_year_id', $schoolYearId))
        ->whereNull('deleted_at')
        ->whereNull('archived_at')
        ->whereNull('latest_student_exit_date')
        ->lockForUpdate()
        ->first();

    // 4. Tutup keanggotaan lama (jika transfer)
    if ($activeMembership) {
        $activeMembership->latest_student_exit_date = $transferDate;
        $activeMembership->version++;
        $activeMembership->save();
    }

    // 5. Buat keanggotaan baru
    StudentRombel::create([
        'student_id' => $student->id,
        'student_nik' => $student->nik, // fallback legacy sync
        'school_rombel' => $targetRombelId,
        'student_status_id' => 1,
        'latest_student_entry_date' => $entryDate,
        'version' => 1,
    ]);
});
```

---

## 5. Rancangan Antarmuka Pengguna (UI) di `Student.vue`

1. **Aksi Kolom Tabel**:
   - Jika `row.placement === 'Unplaced'`: Tampilkan tombol biru outline `pi pi-user-plus` dengan tooltip `"Assign Rombel"`.
   - Jika `row.placement === 'Placed'`: Tampilkan tombol oranye outline `pi pi-arrow-right-arrow-left` dengan tooltip `"Change Rombel"`.
   - Kedua tombol dilindungi directive `v-if="canManage"` (hanya Operator Sekolah & Admin; tersembunyi bagi Kepala Sekolah view-only).
2. **Dialog `placementDialog`**:
   - Judul dinamis: `"Assign Rombel"` (untuk unplaced) atau `"Transfer Class Rombel"` (untuk placed).
   - Form field:
     - Student Name & NIS (Read-only summary).
     - Target Rombel Dropdown: Opsi rombel diambil dari daftar rombel tahun ajaran aktif yang sudah dimuat oleh halaman.
     - Effective Date (Datepicker, default hari ini).
     - Checkbox: `"This is a correction of a mistaken entry"` (hanya saat transfer).
     - Transfer Reason (Textarea, wajib jika bukan koreksi).
3. **Double-Submit Guard & Feedback**:
   - Tombol submit memiliki state `:loading="isSubmittingPlacement"` dan `:disabled="isSubmittingPlacement"`.
   - Menangani error 409 Conflict dengan memunculkan `Data Conflict Detected` dialog dan me-reload data terbaru.
   - Pasca sukses: Menampilkan toast hijau `"Student successfully placed into X-1"`, menutup dialog, dan memanggil `loadStudents()` untuk menyinkronkan tabel serta widget rekap server.

---

## 6. Daftar Berkas yang Diperkirakan Berubah

| Berkas | Jenis Perubahan | Deskripsi |
|---|---|---|
| `laravel-api/routes/api.php` | Modifikasi | Mendaftarkan route `POST /api/students/{id}/assign-rombel` dan `POST /api/students/{id}/transfer-rombel`. |
| `laravel-api/app/Http/Controllers/StudentRombelController.php` | Modifikasi | Mengimplementasikan logika penempatan, pemindahan, validasi ketiadaan rombel aktif ganda, dan transaksi atomik. |
| `laravel-api/app/Http/Requests/AssignStudentRombelRequest.php` | Baru | FormRequest validasi input penempatan siswa unplaced. |
| `laravel-api/app/Http/Requests/TransferStudentRombelRequest.php` | Baru | FormRequest validasi input pemindahan rombel siswa placed. |
| `vue-app/src/views/pages/public/Student.vue` | Modifikasi | Menambahkan tombol aksi Assign/Transfer di baris DataTable, dialog `placementDialog`, state manajemen form, dan error handling 409. |
| `laravel-api/tests/Feature/Student/StudentRombelAssignmentTest.php` | Baru | Suite pengujian SQLite: FormRequest, hak akses 401/403, penolakan siswa terarsip, transaksi atomik, dan audit log. |
| `laravel-api/tests/Feature/Student/MariaDbRombelConcurrencyTest.php` | Baru | Pengujian integrasi MariaDB: dua request penempatan simultan dengan expected version sama, serialisasi row lock, archive vs assign race. |
| `reports/feature-update/PHASE-03D-STUDENT-PLACEMENT.md` | Baru | Laporan implementasi dan bukti pengujian Subfase 03d setelah diotorisasi. |
| `reports/feature-update/PHASE-03D-MANUAL-REVIEW.md` | Baru | Panduan checklist manual peninjauan pemilik untuk aksi penempatan/pemindahan rombel UI. |

---

## 7. Kriteria Penerimaan & Matriks Pengujian

| ID Pengujian | Skenario | Lingkungan | Target / Ekspektasi |
|---|---|---|---|
| **TEST-03D-01** | Guest ditolak saat assign/transfer | SQLite | HTTP 401 Unauthorized |
| **TEST-03D-02** | View-only (`uat.kepsek`) ditolak assign/transfer | SQLite | HTTP 403 Forbidden |
| **TEST-03D-03** | Operator (`uat.operator`) berhasil menempatkan siswa unplaced | SQLite | HTTP 201, baris `student_rombels` terbentuk, audit terbit |
| **TEST-03D-04** | Penempatan siswa terarsip ditolak | SQLite | HTTP 422 / DomainException |
| **TEST-03D-05** | Penempatan ke rombel tahun ajaran berbeda ditolak | SQLite | HTTP 422 Validation Error |
| **TEST-03D-06** | Penempatan siswa yang sudah punya rombel aktif ditolak | SQLite | HTTP 409 / Trigger Error |
| **TEST-03D-07** | Transfer rombel menutup baris lama dan membuat baris baru | SQLite | Baris lama `exit_date` terisi, baris baru aktif, riwayat utuh |
| **TEST-03D-08** | Transfer dengan stale `expected_version` ditolak | SQLite | HTTP 409 Conflict |
| **TEST-03D-09** | Concurrency: dua assign simultan pada siswa yang sama | MariaDB (`school_compat_uat`) | Tepat satu 201/200 sukses, satu 409/conflict, baris aktif tetap 1 |
| **TEST-03D-10** | Concurrency: archive siswa bersamaan dengan assign rombel | MariaDB (`school_compat_uat`) | Siswa terarsip tidak pernah berakhir dengan rombel aktif |
| **TEST-03D-11** | UI Browser: Unplaced student menampilkan tombol Assign Rombel | Playwright UAT | Tombol tampil, dialog terbuka dengan rombel tahun aktif |
| **TEST-03D-12** | UI Browser: Placed student menampilkan tombol Change Rombel | Playwright UAT | Tombol tampil, rombel saat ini terindikasi, transfer sukses |
| **TEST-03D-13** | UI Browser: Rekapitulasi server pasca-penempatan | Playwright UAT | Angka Placed +1, Unplaced -1, rekap gender akurat instan |

---

## 8. Keputusan Pemilik yang Dibutuhkan Sebelum Implementasi

Sebelum subfase ini diotorisasi untuk implementasi, pemilik diharapkan mengonfirmasi 3 hal:

1. **Konfirmasi Otorisasi Subfase 03d**:
   - Apakah menyetujui pembukaan Subfase 03d untuk menyelesaikan aksi UI penempatan rombel siswa sebelum memulai Fase 04 (Guru dan Pegawai)?
2. **Pembedaan Koreksi vs Mutasi**:
   - Apakah menyetujui rancangan di Bagian 3.3 (opsi checkbox koreksi salah input vs mutasi resmi dengan alasan)?
3. **Konfirmasi Otorisasi Hak Akses**:
   - Apakah penempatan dan pemindahan rombel cukup menggunakan izin `students.manage` (sehingga Operator Sekolah dan Wakil Kesiswaan dapat mengeksekusinya, sedangkan Kepala Sekolah view-only)?
