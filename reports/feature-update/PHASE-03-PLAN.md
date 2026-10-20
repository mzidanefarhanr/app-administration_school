# Rencana Kerja Fase 03 — Pendataan Siswa Lengkap dan Aman

- Tanggal WIB: 2026-10-20
- Mode: **Perencanaan & Audit Source (Read-Only)**. Tidak ada implementasi kode, migrasi database, atau perubahan data operasional pada tahap ini.
- Status rencana: **PLANNING / READY FOR OWNER REVIEW**.
- Prasyarat: **Fase 01 dan Fase 02 DITERIMA PENUH & DITUTUP LENGKAP** (01a–01c dan 02a–02c seluruhnya APPROVED).
- Dokumen acuan: `AGENTS.md`, `ROADMAP.md`, `roadmap/phases/PHASE-03-STUDENTS.md`, `docs/REQUIREMENTS.md` (FR-04, NFR-01, NFR-02), `docs/DATABASE.md`, `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, dan register keputusan `DEC-05`, `DEC-14`, `DEC-16`, `DEC-17`.

---

## 1. Baseline & Kondisi Aktual HEAD

### A. Kondisi Skema Database (`laravel-api/database/migrations`)
1. **Tabel `students` (`2029_07_06_215144_create_students_table.php`)**:
   - Kolom identitas: `id`, `version` (default 1), `name`, `nik` (unique nullable), `nis` (unique nullable), `nisn` (unique nullable), `birthplace`, `birthdate`, `age` (tersimpan statis, bertentangan dengan aturan AGENTS.md), `gender_id`, `religion_id`, `education_level_id`, `blood_type_id`, `profession_id`, `village_id`, `zip_code`, `rt_num`, `rw_num`, `address`, `kk`, `akta`.
   - Kolom keluarga & wali: `father_*` (name, nik, birthplace, birthdate, education_level_id, profession_id, income, hp_num), `mother_*` (serupa), `guardian_*` (serupa).
   - Kolom bantuan/afirmasi: `check_ppdbbersama`, `check_kjp`, `check_bpms`, `check_pip`.
   - Soft delete: `deleted_at` terindeks.
2. **Cacat Arsitektur Foreign Key pada `student_rombels` (`2029_07_10_070707_create_student_rombels_table.php`)**:
   - Kolom `student_nik` ditetapkan sebagai foreign key yang merujuk langsung ke `students.nik`:
     ```php
     $table->string('student_nik');
     $table->foreign('student_nik')->references('nik')->on('students');
     ```
   - **Masalah Kritis**:
     - `students.nik` bersifat nullable (siswa baru belum tentu memiliki NIK saat pendaftaran). Siswa tanpa NIK saat ini mustahil dimasukkan ke dalam rombel.
     - Jika NIK siswa diperbaiki karena salah ketik, relasi FK terancam putus atau memerlukan cascading update yang berisiko.
     - Perubahan NIK tidak boleh memutus relasi keanggotaan rombel historis.
   - **Solusi Rencana (Additive Migration)**:
     - Menambahkan kolom `student_id` (foreign key `unsignedBigInteger` nullable merujuk ke `students.id`) pada `student_rombels` melalui migration baru.
     - Melakukan backfill otomatis pada migration: mengisi `student_id` berdasarkan kecocokan `students.nik = student_rombels.student_nik`.
     - Mempertahankan kolom lama `student_nik` selama masa transisi (backward compatibility) agar tidak merusak data lama sebelum seluruh consumer dialihkan.

### B. Kondisi Backend Controller & Model
1. **`StudentController.php` (Scaffold)**:
   - Method `index()` memuat 18 eager loading relasi tanpa paginasi server (`Student::with(...)->get()`), berpotensi kehabisan memori (*out of memory*) saat dataset membesar.
   - Method `store()` hanya berisi kode contoh/dummy (referensi ke model `User` dan `Employee` yang tidak relevan).
   - Method `show()`, `update()`, dan `destroy()` kosong tanpa implementasi.
2. **`StudentRombelController.php` (Scaffold & Anomali Kontrak)**:
   - Method `show($id)` saat ini menyalahgunakan parameter `$id` sebagai `school_year_id` (`whereHas('schoolRombel', fn($q) => $q->where('school_year_id', $id))`), bukan mengambil single record membership berdasarkan primary key `id`.
   - Endpoint `GET /api/student-rombels/{id}` ini aktif dikonsumsi oleh `Student.vue` untuk memuat siswa per tahun ajaran.
3. **Form Requests & Policies**:
   - `StoreStudentRequest.php` dan `UpdateStudentRequest.php` memiliki `authorize(): bool { return false; }` dan `rules(): array { return []; }`.
   - `StudentPolicy.php` dan `StudentRombelPolicy.php` memiliki seluruh method otorisasi kosong.
4. **Model `Student.php`**:
   - Menggunakan trait `Loggable` dan `SoftDeletes`.
   - Kolom `age` tersimpan di database; belum memiliki accessor umur dinamis dari `birthdate`.
   - Kolom bantuan `check_bpms` ada di tabel namun perlu diselaraskan dengan `$fillable`.

### C. Kondisi Antarmuka Frontend (`vue-app/src/views/pages/public/Student.vue`)
- Terdiri dari 2.134 baris kode monolitik.
- Mengambil tahun ajaran aktif, lalu memanggil `GET /api/student-rombels/{school_year_id}`.
- Menghitung statistik lokal di memori peramban (Active, Mutation, Dropout, Graduation).
- Memiliki visualisasi chart (gender, umur, agama, rombel).
- Memiliki tombol ekspor CSV yang telah terintegrasi dengan endpoint berizin `GET /api/students/export` (Fase 01a).
- Dialog Tambah/Ubah/Hapus siswa masih berupa mockup lokal tanpa mutasi API nyata.
- Perlu diselaraskan ke Bahasa English sesuai DEC-14.

---

## 2. Tujuan dan Ruang Lingkup

Fase 03 bertujuan membangun sistem pendataan siswa yang lengkap, aman, tervalidasi di sisi server, terintegrasi dengan audit log server-side, mendukung *optimistic concurrency control* (anti-stale overwrite), dan menyelesaikan cacat struktural relasi foreign key keanggotaan rombel.

### Batasan Tetap Sesuai Roadmap & AGENTS.md:
1. **Tanpa Akun Login Siswa Otomatis**: Penambahan siswa tidak otomatis membuat akun login `User` (siswa tidak memiliki akses sistem).
2. **Tanpa Kenaikan Kelas Massal Otomatis**: Kenaikan kelas, kelulusan massal, dan mutasi rombel antar-tahun ajaran berada pada cakupan **Fase 06**.
3. **Tanpa Portal Orang Tua/Siswa & Tanpa Integrasi Eksternal**: Sinkronisasi otomatis Dapodik/EMIS dan portal publik berada di luar scope.
4. **UI Berbahasa English**: Seluruh teks form, tabel, dialog, dan pesan error memakai Bahasa English (DEC-14).
5. **Homonim Sah**: Nama siswa tidak wajib unik; validasi tidak boleh menolak nama yang sama (DEC-16).
6. **Umur Dihitung Dinamis**: Umur tidak diperlakukan sebagai sumber kebenaran tersimpan; dihitung dinamis dari tanggal lahir mengacu tanggal hari ini/referensi.
7. **Nomor Identitas String**: NIK, NIS, NISN, KK, nomor HP disimpan dan divalidasi sebagai tipe data string; nilai kosong diperlakukan konsisten tanpa berubah menjadi angka `0`.

---

## 3. Rincian Rencana Subfase

Fase 03 dibagi menjadi 3 subfase terstruktur:

```
[Subfase 03a] Migration Identitas & Membership
       │ (student_id additive, backfill NIK, virtual age, model fillable)
       ▼
[Subfase 03b] CRUD Siswa & Server Validation
       │ (Store/Show/Update/Arsip/Restore, FormRequest, Optimistic 409, Audit)
       ▼
[Subfase 03c] Daftar, Filter & Rekap Siswa
         (Server pagination, filter rombel/tahun, empty states, charts)
```

---

### A. Subfase 03a — Identitas dan Membership Migration

#### 1. Tujuan
Memperbaiki integritas skema database, memigrasikan relasi `student_rombels` ke `student_id` tanpa merusak data lama, menormalisasi data identitas string, dan mengaktifkan perhitungan umur dinamis.

#### 2. Pekerjaan Teknis
1. **Migration Baru (Additive)**:
   - Nama: `2029_07_20_000004_add_student_id_to_student_rombels_table.php`.
   - Menambahkan kolom `student_id` (`unsignedBigInteger`, nullable, foreign key ke `students.id` dengan `cascadeOnUpdate` dan `restrictOnDelete`).
   - Menambahkan index pada `student_id`.
   - **Backfill Aman**:
     ```sql
     UPDATE student_rombels sr
     INNER JOIN students s ON s.nik = sr.student_nik
     SET sr.student_id = s.id
     WHERE sr.student_id IS NULL;
     ```
   - Deteksi orphan: mencatat log peringatan jika terdapat record `student_rombels` yang `student_nik`-nya tidak ditemukan di tabel `students`.
   - Menambahkan composite unique index atau rule keanggotaan (sesuai keputusan DEC-05).
   - Kolom `student_nik` **TETAP DIPERTAHANKAN** sebagai fallback backward compatibility.
2. **Penyelarasan Model & Accessor**:
   - `Student.php`:
     - Menambahkan accessor dinamis:
       ```php
       public function getCalculatedAgeAttribute(): ?int
       {
           return $this->birthdate ? Carbon::parse($this->birthdate)->age : null;
       }
       ```
     - Menyelaraskan `$fillable` untuk `check_bpms`, `check_kjp`, `check_pip`, `check_ppdbbersama`.
     - Memperbarui relasi `studentRombels()`: `hasMany(StudentRombel::class, 'student_id', 'id')`.
   - `StudentRombel.php`:
     - Menambahkan `use Loggable;` untuk mencatat audit mutasi rombel.
     - Menambahkan `student_id` ke `$fillable`.
     - Menambahkan relasi `student()`: `belongsTo(Student::class, 'student_id', 'id')`.
     - Mempertahankan relasi legacy `studentNik()`: `belongsTo(Student::class, 'student_nik', 'nik')`.

#### 3. Kriteria Penerimaan Subfase 03a
- Siswa tanpa NIK dapat tersimpan dan dapat dikaitkan dengan `student_rombels` melalui `student_id`.
- Siswa yang NIK-nya diperbarui tidak memutus riwayat rombel.
- Backfill berjalan sukses pada database SQLite in-memory dan database UAT `school_access_uat`.
- Tes otomatis migration dan relasi model PASS 100%.

---

### B. Subfase 03b — CRUD dan Form Siswa

#### 1. Tujuan
Membangun operasi lengkap Tambah (Store), Detail (Show), Ubah (Update), Arsip (Soft Delete), dan Pemulihan (Restore) siswa dengan validasi server ketat, otorisasi kemampuan bernama, pencegahan konflik versi (*optimistic concurrency control*), dan pencatatan jejak audit otomatis.

#### 2. Spesifikasi Endpoint API & Kontrak
| Method | Endpoint | Kemampuan | Deskripsi & Validasi |
|---|---|---|---|
| `GET` | `/api/students/{id}` | `students.view` | Mengembalikan detail lengkap siswa beserta relasi keluarga dan rombel aktif. |
| `POST` | `/api/students` | `students.manage` | Membuat data siswa baru. `version` otomatis bernilai 1. |
| `PUT` | `/api/students/{id}` | `students.manage` | Memperbarui data siswa. Memeriksa `version`. Jika `version` berbeda, tolak **409 Conflict**. `version` dinaikkan secara atomik (`version + 1`). |
| `DELETE` | `/api/students/{id}` | `students.manage` | Soft delete record siswa. Mencatat audit `delete`. |
| `POST` | `/api/students/{id}/restore` | `students.manage` | Memulihkan record siswa yang terarsip. Memeriksa bentrok keunikan identitas. |

#### 3. Aturan Validasi Server (`StoreStudentRequest` & `UpdateStudentRequest`)
- `name`: `['required', 'string', 'max:255']` (Nama boleh sama / homonim sah).
- `nik`: `['nullable', 'string', 'digits:16', Rule::unique('students', 'nik')->ignore($studentId)->whereNull('deleted_at')]`.
- `nis`: `['nullable', 'string', 'max:50', Rule::unique('students', 'nis')->ignore($studentId)->whereNull('deleted_at')]`.
- `nisn`: `['nullable', 'string', 'digits:10', Rule::unique('students', 'nisn')->ignore($studentId)->whereNull('deleted_at')]`.
- `birthdate`: `['nullable', 'date', 'before:today']`.
- `gender_id`: `['nullable', 'integer', 'exists:genders,id']`.
- `religion_id`: `['nullable', 'integer', 'exists:religions,id']`.
- `education_level_id`: `['nullable', 'integer', 'exists:education_levels,id']`.
- `family_status_id`: `['nullable', 'integer', 'exists:family_statuses,id']`.
- `blood_type_id`: `['nullable', 'integer', 'exists:blood_types,id']`.
- `version`: `['required', 'integer']` (pada update).
- Foreign key lain (`village_id`, `last_education_school_id`, dll.): tervalidasi ada di database referensi.
- Bantuan sosial: `check_ppdbbersama`, `check_kjp`, `check_bpms`, `check_pip` divalidasi boolean.

#### 4. Audit Mutasi & Perlindungan Data Sensitif
- Trait `Loggable` mencatat setiap aksi insert, update, soft delete, dan restore.
- Allowlist atribut audit: `name`, `nis`, `nisn`, `nik`, `gender_id`, `religion_id`, `birthdate`, `version`.
- **Eksklusi Data Pribadi Sensitif**: `history_illness` (riwayat medis), nomor telepon orang tua, dan detail gaji orang tua (`*_income`) dikecualikan dari payload ringkasan audit guna mematuhi prinsip privasi data (NFR-01, DEC-17).

#### 5. Integrasi Antarmuka Pengguna (`Student.vue`)
- Dialog Add/Edit Student:
  - Form modular (Biodata Utama, Identitas & Dokumen, Data Orang Tua/Wali, Bantuan Pemerintah).
  - Validasi form interaktif di sisi klien sebelum submit.
  - Penanganan error HTTP 422 (tampilkan pesan field error di bawah input terkait).
  - Penanganan error HTTP 409 Conflict: modal peringatan English bahwa data telah diubah oleh operator lain, menyediakan opsi untuk memuat ulang data terbaru.
  - Dialog konfirmasi arsip (Delete) dan pemulihan (Restore) dengan pesan English.

#### 6. Kriteria Penerimaan Subfase 03b
- Tambah, ubah, arsip, dan restore siswa berjalan nyata dan terbukti pada pengujian otomatis dan manual.
- Siswa dengan nama identik (homonim) berhasil disimpan tanpa ditolak.
- Duplikat NIS / NISN / NIK pada siswa aktif ditolak dengan HTTP 422.
- Update data dengan `version` usang ditolak dengan HTTP 409 Conflict dan data target tidak berubah.
- Jejak audit server-side mencatat aktor, aksi, before/after tanpa memuat data medis rahasia.

---

### C. Subfase 03c — Daftar, Filter, dan Rekap Siswa

#### 1. Tujuan
Menyediakan daftar siswa dengan paginasi server efisien, pencarian multi-kolom cepat, filter fleksibel (tahun ajaran, rombel, status keaktifan, gender), penanganan *empty state* yang ramah pengguna, visualisasi rekap chart dinamis, dan penyelarasan dengan fitur ekspor CSV.

#### 2. Spesifikasi Endpoint API Daftar Berpaginasi
- **Path**: `GET /api/students`
- **Kemampuan**: `students.view`
- **Query Parameters**:
  - `page`: nomor halaman (default: 1).
  - `per_page`: jumlah record per halaman (default: 25, opsi: 10, 25, 50, 100, maks: 100).
  - `search`: string pencarian (mencocokkan nama, NIS, NISN, NIK).
  - `school_year_id`: filter tahun ajaran aktif/terpilih.
  - `school_rombel_id`: filter rombel spesifik.
  - `student_status_id`: filter status keaktifan (Aktif, Mutasi, Lulus, Dropout).
  - `gender_id`: filter jenis kelamin.
- **Payload Response**:
  - `data`: koleksi record siswa ringkas (kolom publik dan relasi penting).
  - `meta`: `current_page`, `last_page`, `per_page`, `total`, `from`, `to`.
  - `summary`: ringkasan metrik agregat (total siswa, laki-laki, perempuan, aktif, mutasi, lulus).
- **Pertahanan Kompatibilitas Legacy**:
  - Endpoint `GET /api/student-rombels/{school_year_id}` tetap dipertahankan selama masa transisi sampai seluruh dependensi Vue beralih ke `GET /api/students`.

#### 3. Antarmuka Pengguna & Observability (`Student.vue`)
- Paginasi Server di PrimeVue DataTable: lazy loading (`@page="onPage"`, `@sort="onSort"`, `@filter="onFilter"`).
- Filter Tahun Ajaran & Rombel:
  - Dropdown tahun ajaran memuat data dari `GET /api/school-years`.
  - Jika tahun ajaran yang dipilih belum memiliki data siswa, tampilkan **Empty State** yang informatif: "No students registered for this academic year." tanpa memicu runtime error pada konsol browser.
- Visualisasi Statistik (Rekap Chart):
  - Grafik distribusi gender (Doughnut Chart).
  - Grafik distribusi umur (Bar Chart, dihitung dinamis dari tanggal lahir).
  - Grafik distribusi agama dan rombel.
- Integrasi Ekspor CSV:
  - Tombol ekspor tetap memanfaatkan endpoint aman `GET /api/students/export?school_year_id=...` (Fase 01a) dengan batas 10.000 baris.
- Tampilan Responsif & Tema:
  - Pengujian kompatibilitas pada tampilan desktop dan mobile.
  - Keselarasan tema Light dan Dark PrimeVue (Aura theme).

#### 4. Kriteria Penerimaan Subfase 03c
- Query database dieksekusi dengan paginasi `paginate()`, tidak memuat seluruh data ke memori.
- Pencarian dan filter bekerja konsisten dan tepat sasaran.
- Tahun ajaran kosong menghasilkan empty state yang bersih dan bebas error konsol.
- Visualisasi rekap chart mencerminkan agregasi data aktual dari filter aktif.

---

## 4. Keputusan yang Diperlukan dari Pemilik (DEC-05 & Detail Fase 03)

Sebelum pekerjaan implementasi Subfase 03a dan 03b dimulai, diperlukan ketetapan pemilik atas pertanyaan-pertanyaan berikut:

### Pertanyaan 1: Kebijakan Keunikan Identitas Siswa (NIS vs NIK)
- **Konteks**: Di sekolah Indonesia, siswa baru (terutama tingkat awal) sering kali belum memiliki NIK lengkap atau KTP anak saat pendaftaran, namun sekolah menerbitkan NIS (Nomor Induk Siswa).
- **Pilihan Usulan**:
  - **Opsi A (Direkomendasikan)**: NIK bersifat opsional (`nullable`, format 16 digit angka jika diisi). NIS bersifat unik di lingkup sekolah. Siswa baru dapat didata menggunakan NIS sementara NIK dapat dilengkapi menyusul.
  - **Opsi B**: NIK wajib diisi saat pendaftaran pertama kali. Jika belum ada NIK, pendaftaran ditolak.
- *Rekomendasi*: **Opsi A**.

### Pertanyaan 2: Aturan Keanggotaan Rombel per Periode (Membership Rule)
- **Konteks**: Siswa ditempatkan di dalam rombel (rombongan belajar/kelas) per tahun ajaran.
- **Pilihan Usulan**:
  - **Opsi A (Direkomendasikan)**: Satu siswa hanya boleh memiliki **maksimal satu rombel aktif** dalam satu tahun ajaran yang sama. Jika siswa dipindahkan kelas di tengah tahun ajaran, record lama ditandai nonaktif/mutasi internal atau diupdate rombelnya, sehingga tidak terjadi keanggotaan ganda di tahun yang sama.
  - **Opsi B**: Satu siswa boleh terdaftar di lebih dari satu rombel dalam tahun ajaran yang sama tanpa pembatasan unik.
- *Rekomendasi*: **Opsi A**.

### Pertanyaan 3: Perilaku Soft Delete & Pemulihan (Arsip vs Restore)
- **Konteks**: Saat operator menghapus data siswa yang sudah lulus, mutasi, atau salah input.
- **Pilihan Usulan**:
  - **Opsi A (Direkomendasikan)**: Penghapusan menggunakan Soft Delete (arsip). Data keanggotaan rombel siswa di tahun berjalan otomatis dinonaktifkan. Saat di-restore, sistem memeriksa apakah NIS/NIK siswa tersebut sudah digunakan oleh siswa baru lainnya.
  - **Opsi B**: Hard delete permanen (menghapus fisik dari database).
- *Rekomendasi*: **Opsi A** (mempertahankan integritas audit dan sejarah).

### Pertanyaan 4: Eksklusi Data Pribadi Sensitif dari Log Audit
- **Konteks**: Tabel `students` memuat data riwayat penyakit (`history_illness`), nomor HP pribadi orang tua, dan besaran penghasilan.
- **Pilihan Usulan**:
  - **Opsi A (Direkomendasikan)**: Log audit server-side mencatat perubahan identitas dan akademik (`name`, `nis`, `nisn`, `nik`, `gender_id`, `birthdate`, `version`), namun mengecualikan riwayat medis dan penghasilan orang tua demi perlindungan data pribadi (NFR-01).
  - **Opsi B**: Seluruh kolom yang berubah dicatat mentah di log audit.
- *Rekomendasi*: **Opsi A**.

---

## 5. Strategi Pengujian Terisolasi

1. **Backend Testing (`php artisan test`)**:
   - Menjalankan test pada database SQLite in-memory `:memory:` terisolasi dan database disposable MariaDB lokal `school_access_uat`.
   - Menulis test suite baru di `tests/Feature/Student/`:
     - `StudentMigrationTest.php`: verifikasi penambahan kolom `student_id`, foreign key constraint, backfill data, dan relasi ganda (student_id & student_nik).
     - `StudentValidationTest.php`: pengujian validasi homonim (nama sama sah), penolakan NIK < 16 digit, format tanggal, dan FK lookup.
     - `StudentConcurrencyTest.php`: simulasi bentrok versi (optimistic locking), request dengan `version` lama menghasilkan 409 Conflict.
     - `StudentAuthorizationTest.php`: verifikasi 401 unauthenticated, 403 operator tanpa `students.manage` pada aksi store/update/delete, dan 200 OK untuk user berizin.
     - `StudentAuditTest.php`: verifikasi pembuatan log audit server-side otomatis pada mutasi siswa dan penyaringan data riwayat penyakit/gaji.
     - `StudentPaginationTest.php`: verifikasi query paginasi server, filter status, filter rombel, dan pencarian.
   - Code Style check: `./vendor/bin/pint --test` wajib 100% clean.
2. **Frontend Testing & Build**:
   - `npm run build`: verifikasi kompilasi Vue SPA bebas error sintaksis atau missing imports.
   - Unit tests: verifikasi penanganan error 409 dan 422 pada HTTP client.
3. **Database Operasional**:
   - Database `backdata_administrations` sama sekali **TIDAK DISENTUH** selama seluruh proses pengujian dan migrasi.

---

## 6. Dokumen yang Akan Disinkronkan Saat Pelaksanaan
- `docs/DATABASE.md`: pembaruan skema relasi `student_rombels.student_id`.
- `docs/API-CONTRACTS.md`: spesifikasi endpoint `POST/GET/PUT/DELETE /api/students` dan paginasi server.
- `docs/ACCESS-CONTROL.md`: penegakan izin `students.view` dan `students.manage`.
- `docs/DECISIONS.md`: pencatatan keputusan DEC-05 dan jawaban pemilik.
- `reports/feature-update/`: laporan teknis per subfase dan panduan review manual UAT.
- `CHANGELOG.md`, `INDEX.md`, `HANDOFF.md`, `ROADMAP.md`.
