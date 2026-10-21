# Laporan Subfase 03a — Identitas Siswa dan Migrasi Keanggotaan Rombel

- Tanggal WIB: 2026-10-21
- Branch kerja: `main`
- Baseline: `832c0c7` (pencatatan keputusan DEC-20)
- Status teknis: **IMPLEMENTED & FULLY VERIFIED (22 backend test PASS, Pint 100% clean, build PASS)**
- Status review pemilik: **PENDING (Menunggu Pemeriksaan Manual Pemilik di `PHASE-03A-MANUAL-REVIEW.md`)**
- Prasyarat: Fase 01 dan Fase 02 telah **DITERIMA LENGKAP & DITUTUP**; keputusan pemilik **DEC-20** APPROVED pada 20 Oktober 2026 WIB.
- Database & storage uji:
  - Database pengujian otomatis: **SQLite in-memory `:memory:` terisolasi** dan MariaDB lokal disposable `school_restore_drill_uat`.
  - Database UAT lokal: `school_access_uat` (berhasil dimigrasikan dan di-backfill tanpa kehilangan data).
  - Database operasional: `backdata_administrations` (TERLINDUNGI & TIDAK PERNAH DISENTUH).

---

## 1. Ringkasan Eksekutif

Subfase 03a mengimplementasikan perbaikan struktural relasi keanggotaan rombel siswa, memutus ketergantungan kaku pada NIK sebagai primary foreign key, menormalisasi data identitas string, menghitung umur secara dinamis dari tanggal lahir, dan menegakkan aturan maksimal 1 rombel aktif per siswa per tahun ajaran pada level database engine:

1. **Migrasi Additive `student_id` pada `student_rombels`**:
   - Migration `2029_07_23_000001_add_student_id_to_student_rombels_table.php` menambahkan kolom `student_id` (`unsignedBigInteger`, nullable) dengan foreign key ke `students.id` (`onUpdate('cascade')`, `onDelete('restrict')`).
   - `onDelete('restrict')` mencegah penghapusan kaskade yang dapat melenyapkan riwayat akademik siswa.
   - Kolom `student_nik` diubah menjadi nullable agar siswa tanpa NIK tetap dapat didata dan dimasukkan ke dalam rombel.
   - Kolom legacy `student_nik` **tetap dipertahankan** untuk menjaga kompatibilitas ke belakang (*backward compatibility*).
2. **Backfill Aman & Idempotent (`StudentBackfillService`)**:
   - Service `StudentBackfillService` dan command CLI Artisan `app:student-backfill [--dry-run]` memadankan `student_nik` dengan `students.nik`.
   - **Aturan Pemadanan Ketat**:
     - Hanya mengisi baris yang `student_id`-nya masih `NULL`.
     - Hanya memadankan bila `student_nik` berupa string tepat 16 digit angka (`digits:16`) dan cocok pasti dengan **tepat satu siswa**.
     - Baris dengan NIK kosong, NIK tidak valid, NIK orphan (tidak ada di master), atau NIK ambigu (duplikat di master) dibiarkan **unresolved** (`student_id` tetap `NULL`) tanpa mutasi destruktif atau penggabungan siswa secara sembarangan.
     - Aman diulang (*idempotent*) tanpa menimpa relasi yang telah terisi.
     - Ringkasan pelaporan tidak mencetak PII (nama/NIK/data pribadi) ke konsol atau log sistem.
3. **Penegakan Aturan Maksimal Satu Rombel Aktif per Siswa per Tahun**:
   - Ditegakkan pada **Level Database Engine** menggunakan native `BEFORE INSERT` dan `BEFORE UPDATE` trigger pada MariaDB (`SQLSTATE 45000`) dan SQLite (`RAISE(ABORT)`).
   - Diperkuat di **Level Eloquent Model** (`StudentRombel::booted()`) dengan pesan domain exception yang ramah sebelum query menyentuh database.
   - **Preservasi Riwayat Perpindahan Kelas**:
     - Aturan hanya membatasi keanggotaan berstatus **Aktif** (`deleted_at IS NULL`, `student_status_id = 1` atau `null`, dan `latest_student_exit_date IS NULL`).
     - Siswa yang pindah kelas dalam tahun ajaran yang sama tetap memiliki riwayat di kelas lama dengan menandai status kelas lama sebagai non-aktif (misal status 2 'Mutasi Keluar' atau mencatat tanggal keluar `latest_student_exit_date`).
     - Siswa dapat memiliki keanggotaan rombel aktif pada tahun ajaran yang berbeda.
4. **Perhitungan Umur Dinamis (*Virtual Derived Age*)**:
   - Umur dihitung saat runtime dari atribut `birthdate` menggunakan zona waktu `Asia/Jakarta`.
   - Nilai umur tersimpan di database tidak lagi diperlakukan sebagai sumber kebenaran (*single source of truth*).
   - Penanganan null-safe: jika tanggal lahir kosong, tidak valid, atau berada di masa depan, accessor mengembalikan `null`.
5. **Penyelarasan Jalur Baca & Ekspor Existing**:
   - `StudentExportController` dan `StudentRombelController` diperbarui untuk memuat relasi utama `student` dengan fallback transisional ke `studentNik`.
   - Pencarian ekspor mencakup nama, NIK, NIS, dan NISN melalui kedua relasi tanpa regresi.

---

## 2. Audit Skema & Laporan Penghalang

### A. Penghalang Lingkup Sekolah untuk NIS (Audit Butir B.2)
- **Kondisi Skema Aktual**:
  - Tabel `students` tidak memiliki kolom `school_id`.
  - Berdasarkan register keputusan **DEC-01**, arsitektur aplikasi sekolah ini saat ini bersifat **single-tenant (satu institusi sekolah)**.
  - Pada baseline skema `2029_07_06_215144_create_students_table.php`, kolom `nis` didefinisikan sebagai `$table->string('nis')->unique()->nullable();`.
- **Kesimpulan & Pelaporan**:
  - Karena sistem adalah single-school, keunikan `nis` pada tabel `students` secara inheren sudah mewakili keunikan di lingkup sekolah tersebut.
  - Sesuai instruksi pemilik, **kami tidak mengarang kolom `school_id` tiruan** atau menetapkan nilai default sembarang.
  - Jika kelak di masa depan aplikasi dikembangkan menjadi multi-sekolah, constraint komposit `(school_id, nis)` baru dapat ditambahkan setelah entitas sekolah dan tenant isolation dibangun secara resmi.

### B. Definisi Keanggotaan Aktif & Riwayat Akademik (Audit Butir B.3)
- **Sumber Fakta Skema**:
  - Tabel `student_statuses`: ID 1 = 'Aktif', ID 2 = 'Mutasi Keluar', ID 3 = 'Drop Out', ID 4 = 'Mengundurkan Diri', ID 5 = 'Meninggal Dunia', ID 6 = 'Lulus'.
  - Tabel `student_rombels`: memiliki kolom `student_status_id`, `latest_student_exit_date`, `latest_student_graduation_date`, dan `deleted_at`.
- **Definisi Keanggotaan Aktif**:
  - Sebuah record `student_rombels` berstatus **Aktif** jika:
    1. `deleted_at IS NULL` (bukan record soft-delete).
    2. `student_status_id = 1` (atau `null` pada data warisan yang belum diisi statusnya).
    3. `latest_student_exit_date IS NULL` (belum tercatat tanggal keluar dari rombel).
- **Mekanisme Penyimpanan Riwayat Perpindahan Kelas**:
  - Saat siswa berpindah rombel dalam tahun ajaran yang sama:
    1. Record rombel lama diperbarui: `student_status_id` diubah menjadi non-aktif (misal 2 'Mutasi Keluar') dan/atau `latest_student_exit_date` dicatat.
    2. Record rombel baru disisipkan dengan `student_status_id = 1` ('Aktif').
    3. Trigger database mengizinkan operasi ini karena record rombel lama sudah tidak berstatus aktif, sehingga seluruh jejak perjalanan kelas siswa tetap utuh dan tersimpan di database.

---

## 3. Matriks Kompatibilitas Relasi

| Skenario Data | Kolom `student_id` | Kolom `student_nik` | Relasi `student` (Eloquent) | Relasi `studentNik` (Legacy) | Perilaku Ekspor / Tampilan |
|---|---|---|---|---|---|
| Siswa Lengkap (Normal) | Terisi ID Siswa | Terisi 16 digit NIK | **MATCH** (`belongsTo`) | **MATCH** (`belongsTo`) | Menggunakan `student` (primer). |
| Siswa Baru Tanpa NIK | Terisi ID Siswa | `NULL` | **MATCH** (`belongsTo`) | `NULL` | Menggunakan `student` (primer); siswa tetap tampil di rombel & ekspor. |
| Data Warisan (Unresolved NIK) | `NULL` | Terisi NIK lama | `NULL` | **MATCH** (jika NIK ada di master) | Fallback aman ke `studentNik` bila pasangan tunggal; jika orphan, kolom identitas kosong tanpa error. |
| Mutasi Kelas (Riwayat Lama) | Terisi ID Siswa | Terisi NIK Siswa | **MATCH** (`belongsTo`) | **MATCH** (`belongsTo`) | Riwayat tersimpan dengan status non-aktif; tidak memicu error trigger. |

---

## 4. Hasil Eksekusi Backfill pada Database UAT (`school_access_uat`)

Eksekusi migration dan command backfill pada database UAT lokal port 3306:
```text
Running student_rombels backfill...
+------------------------------------------------+-------+
| Metric / Category                              | Count |
+------------------------------------------------+-------+
| Total Rombel Rows Evaluated                    | 3     |
| Already Resolved (student_id set)              | 3     |
| Matched & Backfilled (or candidate in dry-run) | 0     |
| Unresolved: Empty / Null NIK                   | 0     |
| Unresolved: Invalid Format (!16 digits)        | 0     |
| Unresolved: Unmatched in Students Table        | 0     |
| Unresolved: Ambiguous (Multiple Students)      | 0     |
| Total Unresolved                               | 0     |
+------------------------------------------------+-------+
Backfill completed successfully with 0 unresolved rows.
```

- Data UAT `school_access_uat` berhasil diperbarui: seluruh 3 record siswa sintetis terhubung secara valid ke `student_id` (1, 2, 3).
- Data operasional `backdata_administrations` sama sekali tidak disentuh.

---

## 5. Hasil Pengujian Otomatis

### A. Pengujian Fitur Siswa Backend (`php artisan test tests/Feature/Student`)
- Total: **22 passed (82 assertions)**.
- Rincian Suite:
  1. `MariaDbStudentIntegrationTest` (3 tes):
     - `mariadb integration: migration sets up student_id, nullable student_nik, and active triggers` — **PASS**
     - `mariadb integration: database trigger blocks duplicate active rombel in same academic year` (SQLSTATE 45000) — **PASS**
     - `mariadb integration: backfill service executes cleanly on real MariaDB` — **PASS**
  2. `StudentAgeAndIdentityTest` (5 tes):
     - `age is dynamically calculated from birthdate using Asia/Jakarta timezone` — **PASS**
     - `age returns null for null, empty, invalid, or future birthdate` — **PASS**
     - `identity string fields strictly preserve leading zeros` — **PASS**
     - `student model fillable includes check_bpms along with other welfare aids` — **PASS**
     - `student audit allowlist excludes medical history and parent salaries` — **PASS**
  3. `StudentMigrationAndBackfillTest` (8 tes):
     - `backfill correctly resolves valid 16-digit NIK with leading zeros` — **PASS**
     - `backfill leaves null or empty NIK unresolved without touching row` — **PASS**
     - `backfill leaves invalid NIK format unresolved` — **PASS**
     - `backfill leaves unmatched orphan NIK unresolved` — **PASS**
     - `backfill leaves ambiguous duplicate NIK in students table unresolved without picking first` — **PASS**
     - `backfill is strictly idempotent and does not overwrite existing student_id` — **PASS**
     - `dry run mode reports counts without mutating rows` — **PASS**
     - `artisan command app:student-backfill executes cleanly without leaking PII` — **PASS**
  4. `StudentRombelConstraintTest` (6 tes):
     - `first active rombel membership in an academic year succeeds` — **PASS**
     - `second active rombel membership for same student in same academic year is rejected` — **PASS**
     - `active rombel in different academic year is permitted for the same student` — **PASS**
     - `multiple rombels in same year are permitted if previous membership is inactive preserving movement history` — **PASS**
     - `updating own active rombel row succeeds without triggering self-conflict` — **PASS**
     - `student without NIK can be enrolled in rombel via student_id` — **PASS**

### B. Pengujian Regresi Backend Lainnya
- `StudentExportTest`, `FactoryIntegrityTest`, `ServerAuditTest`: Seluruh **41 tes PASS (194 assertions)** tanpa regresi.

### C. Pemeriksaan Style Pint (`./vendor/bin/pint --test`)
- 14 berkas PHP terkait diperiksa: **14 files PASS (100% clean)**.

### D. Pengujian & Kompilasi Frontend
- Unit testing: `node --test tests/phase-01b-api-auth.test.mjs tests/auth-race-condition.test.mjs` → **15 passed, 0 failed**.
- Kompilasi build: `npm run build` → **PASS (0 errors, 788 modules transformed)**.

---

## 6. Berkas yang Berubah pada Subfase 03a

- Backend:
  - `laravel-api/database/migrations/2029_07_23_000001_add_student_id_to_student_rombels_table.php` (baru): migrasi additive penambahan `student_id`, pelonggaran nullability `student_nik`, backfill, dan trigger MariaDB/SQLite.
  - `laravel-api/app/Services/StudentBackfillService.php` (baru): service backfill pemadanan unik, pelaporan kategori unresolved tanpa PII, dan validasi precondition.
  - `laravel-api/app/Console/Commands/StudentBackfillCommand.php` (baru): artisan command `app:student-backfill [--dry-run]`.
  - `laravel-api/app/Models/Student.php`: penambahan dynamic age accessor berbasis `Asia/Jakarta`, penambahan `'check_bpms'` ke `$fillable`, dan relasi `studentRombels()`.
  - `laravel-api/app/Models/StudentRombel.php`: trait `Loggable`, `$auditableAttributes`, penambahan `'student_id'` dan missing fields ke `$fillable`, sinkronisasi dua arah di `booted()`, domain check 1 rombel aktif per tahun, dan relasi `student()`.
  - `laravel-api/app/Http/Controllers/StudentRombelController.php`: penambahan eager loading relasi `student.*` berdampingan dengan `studentNik.*`.
  - `laravel-api/app/Http/Controllers/StudentExportController.php`: adaptasi kolom dan query pencarian ke relasi ganda `student` & `studentNik`.
  - `laravel-api/database/seeders/SyntheticDataSeeder.php` & `UatSeeder.php`: pencatatan eksplisit `student_id` saat seeding keanggotaan rombel.
  - `laravel-api/tests/Support/CreatesAccessFixtures.php`: sinkronisasi `student_id` pada test fixture.
  - `laravel-api/tests/Feature/Student/*` (baru, 4 berkas tes): 22 tes komprehensif migrasi, backfill, identitas, umur, constraint, dan integrasi nyata MariaDB.
