# Rencana Kerja Subfase 03b — CRUD Siswa, Optimistic Locking, dan Form Validasi

- Tanggal WIB: 2026-10-21
- Subfase: **03b — CRUD dan Form Siswa**
- Mode: **Perencanaan & Audit Source (Read-Only)**. Tidak ada implementasi kode atau perubahan skema yang dieksekusi pada langkah ini.
- Status rencana: **PLANNING / READY FOR OWNER REVIEW**.
- Prasyarat: Subfase 03a telah **DITERIMA LENGKAP OLEH PEMILIK** (Checklist #1–#11 APPROVED 2026-10-21 WIB mengacu baseline `37f7d79`); keputusan pemilik **DEC-20** APPROVED.
- Dokumen acuan: `AGENTS.md`, `ROADMAP.md`, `roadmap/phases/PHASE-03-STUDENTS.md`, `docs/REQUIREMENTS.md` (FR-04, NFR-01, NFR-02), `docs/DATABASE.md`, `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, dan `docs/DECISIONS.md`.

---

## 1. Baseline & Kondisi Aktual HEAD

### A. Kondisi Backend Saat Ini
1. **`StudentController.php` (Scaffold)**:
   - Method `store()` masih memuat kode dummy/scaffold (`User::create`, `Employee::create`).
   - Method `show()`, `update()`, dan `destroy()` belum diimplementasikan (kosong).
   - Belum ada method `restore()`.
2. **Form Requests & Policies**:
   - `StoreStudentRequest.php` dan `UpdateStudentRequest.php` saat ini mengembalikan `authorize(): bool { return false; }` dan `rules(): array { return []; }`.
   - `StudentPolicy.php` memiliki method kosong tanpa pengecekan kemampuan terpusat.
3. **Model `Student.php`**:
   - Telah menggunakan trait `Loggable` dan `SoftDeletes`.
   - Kolom `version` sudah tersedia di skema baseline (default 1).
   - Accessor umur dinamis `age` dan `calculated_age` dari `birthdate` telah aktif (Subfase 03a).
   - Atribut `'check_bpms'` telah diselaraskan ke `$fillable`.
4. **Kondisi Skema Database & Constraint Keunikan Aktual**:
   - Skema `students` (`2029_07_06_215144_create_students_table.php`):
     - `name`: `varchar(255)` NOT NULL (satu-satunya kolom wajib di level skema).
     - `nik`: `varchar(255)` NULL, dengan index `UNIQUE KEY students_nik_unique (nik)`.
     - `nis`: `varchar(255)` NULL, dengan index `UNIQUE KEY students_nis_unique (nis)`.
     - `nisn`: `varchar(255)` NULL, dengan index `UNIQUE KEY students_nisn_unique (nisn)`.
     - `version`: `bigint(20) unsigned` NOT NULL default 1.
     - `deleted_at`: `timestamp` NULL, berindeks.
   - **Analisis Semantik Keunikan Record Terarsip (Soft Delete)**:
     - Pada database MariaDB/InnoDB dan SQLite, index `UNIQUE KEY (nik)` berlaku tanpa klausa `WHERE deleted_at IS NULL` (unconditional table-wide unique index).
     - Nilai `NULL` diizinkan ganda (beberapa siswa tanpa NIK tidak saling berbentrok).
     - Namun untuk nilai non-null, MariaDB **melarang** duplikasi nilai NIK/NIS/NISN bahkan jika salah satu record memiliki `deleted_at IS NOT NULL`.
     - *Prinsip Integritas*: Sesuai instruksi pemilik, constraint aktual ini **TIDAK DILEMAHKAN SECARA DIAM-DIAM**. Unconditional unique index memastikan bahwa NIK/NIS yang pernah digunakan oleh siswa (baik aktif maupun terarsip) tidak dapat dipakai oleh siswa baru lainnya kecuali ada pemulihan resmi, menjaga integritas historis buku induk siswa.

---

## 2. Tujuan dan Ruang Lingkup Subfase 03b

1. **Implementasi Endpoints CRUD Siswa**:
   - `POST /api/students`: Menambah data siswa baru (`students.manage`).
   - `GET /api/students/{id}`: Menampilkan detail lengkap siswa (`students.view`).
   - `PUT /api/students/{id}`: Memperbarui data siswa dengan *optimistic concurrency control* (`students.manage`).
   - `DELETE /api/students/{id}`: Soft delete (arsip) siswa dan menonaktifkan keanggotaan rombel aktif dalam satu transaksi atomik (`students.manage`).
   - `POST /api/students/{id}/restore`: Memulihkan siswa terarsip dengan validasi konflik identitas tanpa otomatis mengaktifkan rombel lama (`students.manage`).
2. **Validasi Server Ketat & Normalisasi Data**:
   - FormRequest server tervalidasi lengkap (bukan hanya validasi sisi peramban).
   - Normalisasi: `trim()` spasi, konversi string kosong/whitespace menjadi `null`.
   - Homonim sah: nama siswa tidak divalidasi unik (DEC-16, DEC-20).
   - Validasi identitas string: NIK nullable 16 digit angka (`digits:16`), NIS unik lingkup sekolah (DEC-01 & DEC-20), NISN nullable 10 digit angka (`digits:10`).
   - Validasi foreign key ke seluruh tabel referensi yang valid.
   - Proteksi atribut input: `id`, `age` (karena virtual), dan timestamp diabaikan dari penulisan.
3. **Kontrol Konkurensi Optimistik (Anti-Stale Overwrite)**:
   - Validasi kolom `version` pada setiap update.
   - Eksekusi atomic query `UPDATE ... WHERE id = ? AND version = ?`. Jika versi berbeda, tolak seketika dengan **HTTP 409 Conflict**.
4. **Audit Server Terarah & Minimalisasi PII**:
   - Memanfaatkan trait `Loggable` Eloquent.
   - Allowlist ketat atribut operasional: mengecualikan data riwayat medis (`history_illness`), nomor telepon orang tua, dan rincian gaji orang tua/wali (`*_income`).
5. **Antarmuka Pengguna (UI English)**:
   - Dialog Form Tambah & Ubah Siswa di `vue-app` dengan teks Bahasa English (DEC-14).
   - Penanganan error HTTP 422 (pesan inline di bawah form input).
   - Penanganan error HTTP 409 Conflict (dialog peringatan bentrok data dengan tombol Reload).
   - Indikator loading dan penonaktifan tombol submit untuk mencegah submit ganda.

---

## 3. Spesifikasi Rinci Teknis Subfase 03b

### A. Kontrak API & Otorisasi Backend

| Method | Endpoint | Izin Kemampuan | Request Payload | Response Status | Keterangan |
|---|---|---|---|---|---|
| `POST` | `/api/students` | `students.manage` | JSON field siswa (tanpa id/age) | `201 Created` | Membuat record siswa baru. `version` otomatis diisi 1. |
| `GET` | `/api/students/{id}` | `students.view` | - | `200 OK` | Mengembalikan detail lengkap siswa beserta relasi keluarga dan rombel aktif. |
| `PUT` | `/api/students/{id}` | `students.manage` | JSON field siswa + `version` | `200 OK` / `409 Conflict` | Memperbarui data siswa jika `version` cocok. `version` dinaikkan atomik (+1). |
| `DELETE` | `/api/students/{id}` | `students.manage` | - | `200 OK` | Soft delete siswa dan menonaktifkan keanggotaan rombel aktif dalam 1 transaksi. |
| `POST` | `/api/students/{id}/restore` | `students.manage` | - | `200 OK` / `422 Unprocessable` | Memulihkan siswa terarsip. Memeriksa bentrok identitas. Rombel lama tetap non-aktif. |

### B. Aturan Validasi & Normalisasi Server (`StoreStudentRequest` & `UpdateStudentRequest`)

1. **Normalisasi Otomatis**:
   - Seluruh input string di-`trim()`.
   - String kosong (`""`) dinormalisasi menjadi `null`.
2. **Aturan Validasi Kolom**:
   - `name`: `['required', 'string', 'max:255']` (Nama boleh sama / homonim sah).
   - `nik`: `['nullable', 'string', 'digits:16', Rule::unique('students', 'nik')->ignore($studentId)]` (Nol di depan utuh).
   - `nis`: `['nullable', 'string', 'max:50', Rule::unique('students', 'nis')->ignore($studentId)]` (Unik lingkup sekolah).
   - `nisn`: `['nullable', 'string', 'digits:10', Rule::unique('students', 'nisn')->ignore($studentId)]`.
   - `birthplace`: `['nullable', 'string', 'max:255']`.
   - `birthdate`: `['nullable', 'date_format:Y-m-d', 'before:today']`.
   - `gender_id`: `['nullable', 'integer', 'exists:genders,id']`.
   - `religion_id`: `['nullable', 'integer', 'exists:religions,id']`.
   - `education_level_id`: `['nullable', 'integer', 'exists:education_levels,id']`.
   - `blood_type_id`: `['nullable', 'integer', 'exists:blood_types,id']`.
   - `profession_id`: `['nullable', 'integer', 'exists:professions,id']`.
   - `village_id`: `['nullable', 'integer', 'exists:villages,id']`.
   - `zip_code`: `['nullable', 'string', 'max:10']`.
   - `rt_num`: `['nullable', 'string', 'max:10']`.
   - `rw_num`: `['nullable', 'string', 'max:10']`.
   - `address`: `['nullable', 'string']`.
   - `kk`: `['nullable', 'string', 'digits:16']`.
   - `akta`: `['nullable', 'string', 'max:100']`.
   - `child_order_to`: `['nullable', 'integer', 'min:1']`.
   - `child_order_total`: `['nullable', 'integer', 'min:1']`.
   - `family_status_id`: `['nullable', 'integer', 'exists:family_statuses,id']`.
   - `hp_num`: `['nullable', 'string', 'max:30']`.
   - `history_illness`: `['nullable', 'string', 'max:255']`.
   - `body_height`: `['nullable', 'integer', 'min:30', 'max:250']`.
   - `body_weight`: `['nullable', 'integer', 'min:5', 'max:200']`.
   - `last_education_school_id`: `['nullable', 'string', 'exists:education_schools,npsn']`.
   - `certificate_year`: `['nullable', 'digits:4', 'integer', 'min:1980', 'max:'.(date('Y') + 1)]`.
   - `certificate_num`: `['nullable', 'string', 'max:100']`.
   - `latest_education_school_id`: `['nullable', 'string', 'exists:education_schools,npsn']`.
   - *Data Orang Tua & Wali*:
     - `father_name`, `mother_name`, `guardian_name`: `['nullable', 'string', 'max:255']`.
     - `father_nik`, `mother_nik`, `guardian_nik`: `['nullable', 'string', 'digits:16']`.
     - `father_education_level_id`, `mother_education_level_id`, `guardian_education_level_id`: `['nullable', 'integer', 'exists:education_levels,id']`.
     - `father_profession_id`, `mother_profession_id`, `guardian_profession_id`: `['nullable', 'integer', 'exists:professions,id']`.
     - `father_income`, `mother_income`, `guardian_income`: `['nullable', 'string', 'max:100']`.
     - `father_hp_num`, `mother_hp_num`, `guardian_hp_num`: `['nullable', 'string', 'max:30']`.
   - *Bantuan Sosial*:
     - `check_ppdbbersama`, `check_kjp`, `check_bpms`, `check_pip`: `['nullable', 'boolean']`.
   - *Kolom Optimistic Locking*:
     - `version`: `['required', 'integer']` (pada `UpdateStudentRequest`).
3. **Pembatasan Kolom Input (Input Whitelist)**:
   - Atribut terlarang: `id`, `age` (karena turunan runtime), `created_at`, `updated_at`, `deleted_at`. Jika dikirim oleh klien, otomatis dibuang dari payload mutasi.

### C. Mekanisme Optimistic Concurrency Control (HTTP 409 Conflict)

Pada `StudentController::update`:
```php
DB::beginTransaction();
try {
    $currentStudent = Student::findOrFail($id);

    // Verifikasi versi klien vs database
    if ((int) $request->input('version') !== (int) $currentStudent->version) {
        DB::rollBack();
        return response()->json([
            'message' => 'Conflict: The student record has been updated by another user. Please reload the latest data.',
            'current_version' => $currentStudent->version,
        ], 409);
    }

    $validated = $request->validated();
    $validated['version'] = $currentStudent->version + 1;

    // Update atomik dengan guard version
    $affected = Student::where('id', $id)
        ->where('version', $currentStudent->version)
        ->update($validated);

    if ($affected === 0) {
        DB::rollBack();
        return response()->json([
            'message' => 'Conflict: Concurrent update detected. Please reload.',
        ], 409);
    }

    DB::commit();
    return response()->json([
        'message' => 'Student updated successfully.',
        'data' => $currentStudent->fresh(),
    ], 200);
} catch (\Throwable $e) {
    DB::rollBack();
    throw $e;
}
```

### D. Mekanisme Soft Delete dan Restore Atomik

1. **Soft Delete (`DELETE /api/students/{id}`)**:
   - Dijalankan dalam transaksi database:
     ```php
     DB::transaction(function () use ($student) {
         // 1. Soft delete siswa
         $student->delete();

         // 2. Nonaktifkan keanggotaan rombel aktif siswa pada tahun berjalan (DEC-20)
         // Menandai tanggal keluar dan status non-aktif (misal status 2 'Mutasi Keluar')
         StudentRombel::where('student_id', $student->id)
             ->whereNull('deleted_at')
             ->where(fn($q) => $q->where('student_status_id', 1)->orWhereNull('student_status_id'))
             ->whereNull('latest_student_exit_date')
             ->update([
                 'student_status_id' => 2,
                 'latest_student_exit_date' => now()->toDateString(),
                 'latest_information' => 'Student archived / soft-deleted.',
             ]);
     });
     ```
   - Riwayat mutasi rombel lama **tidak dihapus** dari database, melainkan ditandai keluar/non-aktif, sehingga kronologi kelas siswa tetap terpelihara utuh.
2. **Restore Siswa (`POST /api/students/{id}/restore`)**:
   - Dijalankan dalam transaksi database:
     ```php
     DB::transaction(function () use ($id) {
         $student = Student::onlyTrashed()->findOrFail($id);

         // Periksa potensi konflik identitas terhadap record aktif lain
         // (Menolak restore jika NIK/NIS/NISN telah digunakan oleh siswa aktif lain)
         if ($student->nik && Student::where('nik', $student->nik)->whereNull('deleted_at')->exists()) {
             throw ValidationException::withMessages(['nik' => 'Cannot restore: NIK is already used by an active student.']);
         }
         if ($student->nis && Student::where('nis', $student->nis)->whereNull('deleted_at')->exists()) {
             throw ValidationException::withMessages(['nis' => 'Cannot restore: NIS is already used by an active student.']);
         }

         // Pulihkan siswa
         $student->restore();
         $student->version = $student->version + 1;
         $student->save();

         // Keanggotaan rombel lama TIDAK diaktifkan otomatis (DEC-20)
         // Siswa berstatus dipulihkan tanpa kelas aktif hingga operator menempatkannya kembali secara eksplisit.
     });
     ```

### E. Jejak Audit Server-Side & Minimalisasi Data Pribadi (NFR-01, DEC-20)

- Trait `Loggable` mencatat setiap aksi `Created`, `Updated`, `Deleted`, dan `Restored`.
- Allowlist atribut auditable pada `Student.php`:
  ```php
  protected array $auditableAttributes = [
      'name',
      'nik',
      'nis',
      'nisn',
      'birthdate',
      'gender_id',
      'religion_id',
      'education_level_id',
      'version',
  ];
  ```
- **Atribut Sensitif yang Mutlak Dikecualikan dari Log Audit**:
  - `history_illness` (riwayat penyakit/medis).
  - `father_income`, `mother_income`, `guardian_income` (data penghasilan).
  - `father_hp_num`, `mother_hp_num`, `guardian_hp_num` (nomor kontak pribadi).
  - `address`, `kk`, `akta`.

### F. Antarmuka Pengguna & Observability (`Student.vue`)

1. **Form Dialog Tambah/Ubah Siswa**:
   - Seluruh label, judul dialog, dan tombol dalam Bahasa **English** (DEC-14):
     - Dialog Title: `"Add New Student"` / `"Edit Student Details"`.
     - Tab / Section: `"Personal Details"`, `"Identification & Documents"`, `"Parents & Guardian"`, `"Assistance & Welfare"`.
     - Buttons: `"Save Student"`, `"Cancel"`, `"Archive Student"`, `"Restore"`.
2. **Penanganan Error Validasi (HTTP 422)**:
   - Menangkap pesan error spesifik per kolom dari response JSON Laravel:
     `formErrors.value = error.response.data.errors;`
   - Menampilkan teks error merah di bawah masing-masing input PrimeVue (`InputText`, `Dropdown`, `Calendar`).
3. **Penanganan Konflik Konkurensi (HTTP 409)**:
   - Saat menerima HTTP 409, tampilkan modal alert PrimeVue:
     `"Data Conflict: This student record was updated by another operator while you were editing. Please click Reload to view the latest data."`
   - Form tidak menimpa data server dan menyediakan tombol reload instan.
4. **Pencegahan Double-Submit & Indikator Loading**:
   - State `isSubmitting = ref(false)`.
   - Tombol Save disetel `:disabled="isSubmitting"` dengan icon spinner animasi `pi-spin pi-spinner`.

---

## 4. Matriks Pengujian Subfase 03b

### A. Pengujian Otomatis Backend (`tests/Feature/Student/`)
1. **`StudentCrudValidationTest.php`**:
   - Pembuatan siswa valid dengan NIK 16 digit, NIS unik, tanggal lahir lampau.
   - Pembuatan siswa tanpa NIK (NIK null) berhasil.
   - Pendaftaran homonim (nama persis sama) berhasil disimpan (DEC-16).
   - Penolakan NIK tidak 16 digit (misal: 15 digit atau huruf) dengan HTTP 422.
   - Penolakan duplikat NIK / NIS dengan HTTP 422.
   - Normalisasi spasi dan string kosong menjadi null.
   - Penolakan foreign key tidak valid (misal `gender_id: 99999`).
2. **`StudentConcurrencyTest.php`**:
   - Request update dengan versi lama ditolak HTTP 409 Conflict.
   - Request update dengan versi cocok berhasil dan menaikkan versi (`version + 1`).
   - Simulasi 2 request update bersamaan: request pertama berhasil, request kedua ditolak 409 tanpa menimpa data pertama.
3. **`StudentSoftDeleteRestoreTest.php`**:
   - Soft delete siswa menonaktifkan rombel aktif siswa di tahun berjalan.
   - Riwayat mutasi rombel lama tetap ada di database (tidak terhapus).
   - Restore siswa sukses memulihkan record siswa.
   - Rombel lama tidak otomatis aktif pasca-restore.
   - Restore ditolak jika NIK/NIS telah digunakan oleh record aktif lain.
4. **`StudentAuditPrivacyTest.php`**:
   - Mutasi update siswa mencatat log audit pada tabel `activities`.
   - Log audit TIDAK memuat data `history_illness` atau `father_income`.
5. **Regresi Pembacaan & Ekspor Existing**:
   - Seluruh tes pada `StudentExportTest` dan `MariaDbStudentIntegrationTest` tetap 100% PASS.

### B. Pengujian Manual UAT Sintetis (Checklist Preview)
- Form Tambah Siswa lengkap dan tanpa NIK.
- Edit Siswa dengan verifikasi kenaikan versi.
- Simulasi tab ganda untuk memicu dialog 409 Conflict.
- Soft delete siswa dan verifikasi status rombel nonaktif di database.
- Ekspor CSV siswa pasca penambahan siswa baru.

---

## 5. Pertanyaan Keputusan Pemilik yang Terkait (DEC-21)

Sebelum implementasi Subfase 03b dieksekusi, satu hal operasional perlu konfirmasi pemilik:

### Pertanyaan Operasional: Status Rombel Saat Siswa Di-Soft Delete
- **Konteks**: Saat seorang siswa di-soft delete (diarsipkan), keanggotaan rombel aktif siswa tersebut di tahun ajaran berjalan wajib dinonaktifkan dalam satu transaksi atomik.
- **Pilihan Usulan**:
  - **Opsi A (Direkomendasikan)**: Ubah `student_status_id` ke ID 2 ('Mutasi Keluar') dan isi `latest_student_exit_date = hari ini` dengan catatan `'Student archived'`. Rombel lama tetap tercatat dalam riwayat mutasi tanpa berstatus aktif.
  - **Opsi B**: Cukup isi `latest_student_exit_date = hari ini` tanpa mengubah `student_status_id`.
- *Rekomendasi*: **Opsi A** (status dan tanggal keluar selaras mencerminkan siswa tidak lagi aktif di kelas tersebut).
