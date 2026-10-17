# Rencana Kerja Fase 01c — Isolasi Test, Fixture Sintetis, dan Penanganan BLK-03

- Tanggal WIB: 2026-10-17
- Mode: **perencanaan & audit source (read-only)**. Tidak ada implementasi kode, migrasi, seeder, atau perubahan skema yang dijalankan pada langkah ini.
- Status subfase: **PLANNING / READY FOR REVIEW**.
- Dokumen acuan: `AGENTS.md`, `ROADMAP.md`, `roadmap/phases/PHASE-01-ACCESS.md`, `PROJECT_CONTEXT.md`, dan keputusan pemilik `DEC-03`, `DEC-14`, `DEC-15`.

---

## 1. Audit Kondisi Aktual (Yang Sudah Tersedia vs Yang Belum)

### A. Yang Sudah Tersedia (Tidak Perlu Diulang)
1. **Fail-Safe Isolasi Database Uji (BLK-01 / Sebagian)**:
   - `laravel-api/tests/CreatesApplication.php`: metode `guardAgainstUnsafeTestDatabase()` membatalkan seluruh suite test jika koneksi bukan SQLite in-memory (`driver !== 'sqlite' || database !== ':memory:'`) atau jika `APP_ENV !== 'testing'`.
   - `laravel-api/tests/Feature/Access/DatabaseIsolationTest.php`: memverifikasi secara otomatis bahwa database uji terisolasi pada `:memory:` dan database operasional MySQL tidak pernah menjadi target test.
   - `laravel-api/phpunit.xml`: menyetel variabel lingkungan uji (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_FOREIGN_KEYS=true`, `CACHE_DRIVER=array`, `MAIL_MAILER=array`, `SESSION_DRIVER=array`).
2. **Helper Fixture Sintetis untuk Unit/Feature Test**:
   - `laravel-api/tests/Support/CreatesAccessFixtures.php`: menyediakan `seedAccessReferenceData()` (katalog peran/kemampuan, referensi status/tipe), `makeUserWithRole()`, dan `seedStudentData(int $count = 3)` dengan identitas sintetis murni (NIK diawali nol: `0000000000000001`, dsb.).
3. **Seeder UAT Sintetis Terpisah**:
   - `laravel-api/database/seeders/UatSeeder.php`: seeder UAT untuk database disposable `school_access_uat` dengan 4 akun peran uji sintetis, akun nonaktif, dan 3 data siswa sintetis (termasuk kasus leading zero dan netralisasi formula CSV).

### B. Gap dan Kebutuhan Aktual Fase 01c
1. **Penanganan BLK-03 (`DatabaseSeeder.php` Memuat Identitas Nyata)**:
   - `laravel-api/database/seeders/DatabaseSeeder.php` saat ini masih memuat pemanggilan pembuatan akun pengguna dengan identitas orang riil (nama lengkap, NIK asli, email asli, username).
   - Memanggil `StudentSeeder.php` yang berukuran 62.784 baris memuat data riil siswa sekolah (nama, NIK 16 digit asli, tanggal lahir, tempat lahir).
   - Memanggil `StudentRombelSeeder.php` (11.416 baris) yang menautkan NIK asli siswa ke rombel.
   - **Kebutuhan**: Memisahkan seed referensi dari seed data akun/siswa, membersihkan data pribadi dari source code aktif tanpa melakukan rewrite riwayat Git.
2. **Isolasi Storage Pengujian**:
   - Belum ada fail-safe atau isolasi storage otomatis pada base test suite. Jika endpoint pengujian nantinya mengunggah berkas, berkas dapat tertulis ke direktori penyimpanan lokal aplikasi (`storage/app` atau `storage/app/public`).
   - **Kebutuhan**: Fail-safe disk pengujian (mis. `Storage::fake('local')` / `Storage::fake('public')` atau disk terisolasi di direktori temporary yang dibersihkan).
3. **Audit Factory Model**:
   - `laravel-api/database/factories/StudentFactory.php` dan `EmployeeFactory.php` saat ini masih berupa scaffold kosong (`return [];`).
   - `UserFactory.php` sudah memakai `fake()`, namun perlu dipastikan format NIK dan atribut default selaras dengan aturan aplikasi.
4. **Validasi Akun: Nama Tidak Wajib Unik (Roadmap 01c)**:
   - Pada `UserController.php:40`, kolom `name` saat ini divalidasi dengan `Rule::unique('users')->whereNull('deleted_at')`.
   - Di lingkungan sekolah, dua orang sangat mungkin memiliki nama yang sama persis (homonim). Sesuai aturan `AGENTS.md` ("nama boleh sama") dan roadmap 01c ("nama tidak wajib unik"), validasi unique pada nama adalah defect yang harus dihapus.
   - Kolom yang wajib unik hanyalah `email`, `username`, dan `nik`.
5. **Validasi NIK, Password, dan Foreign Key (FK)**:
   - Format NIK: string tepat 16 digit angka (`regex:/^[0-9]{16}$/` atau `digits:16`), leading zero dipertahankan.
   - Password: min 8 karakter dengan konfirmasi dan standar terpusat.
   - Foreign Key: relasi `type_user_id` dan `status_user_id` divalidasi dengan aturan `exists:...,id` yang konsisten.

---

## 2. Pemetaan Penanganan BLK-03 (Pembersihan Source vs Riwayat Git)

### A. Kebijakan Keamanan dan Riwayat Git
- **Aturan Repo**: **Dilarang melakukan rewrite history Git** (mis. `git rebase -i`, `git filter-repo`, force-push `--force`). Riwayat commit masa lalu tidak diubah agar stabilitas clone/remote pemilik tetap terjaga.
- **Pembersihan Source Aktif**: Perbaikan dilakukan pada commit terkini (HEAD) dengan mengubah atau mengganti berkas seeder di working tree sehingga source aktif bebas dari identitas pribadi (PII).

### B. Usulan Arsitektur Seeder Baru
Pemisahan seeder menjadi 3 kategori independen:

1. **`ReferenceDataSeeder` (Seeder Referensi Murni)**:
   - Berisi data kamus/referensi sistem yang statis dan bebas dari data individu:
     - Wilayah: Provinsi, Kabupaten/Kota, Kecamatan, Kelurahan (`ProvinceSeeder`, `RegencySeeder`, `DistrictSeeder`, `VillageSeeder`).
     - Referensi Sekolah: Agama, Golongan Darah, Status Nikah, Pekerjaan, Status Keluarga, Status Pegawai, Jenis Pegawai, Kepegawaian, Mata Pelajaran, Profil Sekolah umum (`ReligionSeeder`, `BloodTypeSeeder`, dll.).
     - Referensi Siswa & Kelas: Tahun Ajaran, Semester, Jenjang Pendidikan, Status Siswa, Jalur Masuk, Jurusan, Status Fase, Tingkat Sekolah, Rombel (`SchoolYearSeeder`, `EducationLevelSeeder`, `StudentMajorSeeder`, dsb.).
     - Otorisasi & Hak Akses: `StatusUserSeeder`, `TypeUserSeeder`, `AccessControlSeeder` (katalog kemampuan dan 4 peran awal).
   - Seeder ini aman dijalankan kapan saja di lingkungan produksi maupun pengujian karena tidak memuat data pribadi.

2. **`SyntheticDataSeeder` (Data Dummy untuk Lingkungan Dev/UAT)**:
   - Menggunakan Faker untuk menghasilkan data siswa dan pegawai tiruan murni (nama fiktif, NIK sintetis, tanggal lahir acak).
   - `StudentSeeder.php` lama (62.784 baris data riil) dinonaktifkan atau digantikan dengan seeder sintetis terukur (misal 10–50 baris data sintetis).
   - `StudentRombelSeeder.php` lama digantikan dengan penautan sintetis.

3. **`BootstrapAdminSeeder` / Artisan Command (Inisialisasi Akun Pertama)**:
   - Memisahkan pembuatan akun pertama dari `DatabaseSeeder`.
   - Tidak menanamkan kata sandi, NIK riil, atau email pribadi ke dalam kode repositori.

---

## 3. Usulan Mekanisme Bootstrap Akun Pertama

Agar instalasi baru (fresh database) dapat langsung digunakan tanpa memasukkan akun bawaan ke dalam Git, diusulkan 2 alternatif:

- **Opsi A (Artisan CLI Command — Direkomendasikan)**:
  - Disediakan perintah interaktif: `php artisan app:bootstrap-admin`.
  - Operator/admin menjalankan perintah tersebut di terminal; memasukkan nama, username, email, NIK (16 digit), dan password.
  - Perintah membuat akun `admin-sistem` dan memberikan peran Administrator Sistem secara otomatis.
  - Keunggulan: Kredensial tidak pernah tertulis di berkas `.env` atau commit repositori.
- **Opsi B (Environment-Driven Seeder)**:
  - Membuat `BootstrapAdminSeeder` yang hanya membuat akun jika variabel lingkungan tersedia (mis. `ADMIN_INITIAL_USERNAME`, `ADMIN_INITIAL_EMAIL`, `ADMIN_INITIAL_PASSWORD`).
  - Bila variabel tidak diatur, seeder tidak membuat akun apa pun atau menghasilkan kata sandi acak sekali pakai yang dicetak ke console stdout.

---

## 4. Pemetaan Validator Akun (Password, FK, Identitas)

### A. Penyelarasan Aturan Validasi `UserController` dan FormRequest
| Bidang Data | Aturan Validasi Saat Ini | Usulan Penyelarasan Fase 01c | Alasan / Acuan |
|---|---|---|---|
| `name` | `required, min:3, max:100, unique:users` | **`required, string, min:3, max:100`** *(unique DIHAPUS)* | Roadmap 01c & `AGENTS.md`: "nama tidak wajib unik / nama boleh sama". Mencegah penolakan terhadap pengguna dengan nama homonim. |
| `username` | `required, min:3, max:100, unique:users` | `required, alpha_dash, min:3, max:50, unique:users` | Username sebagai identifier login wajib unik dan format aman. |
| `email` | `required, email:rfc, max:60, unique:users` | `required, email:rfc,dns, max:100, unique:users` | Email unik, format valid. |
| `nik` | `required, min:16, unique:users` | **`required, string, digits:16, unique:users`** | NIK di Indonesia adalah 16 digit angka. Disimpan sebagai string, leading zero dipertahankan. |
| `password` | `required, string, min:8` | `required, string, Password::defaults()` | Menyelaraskan dengan standar password Laravel (min 8 karakter + konfirmasi pada registrasi/reset). |
| `type_user_id` | `required, exists:type_users,id` | `required, integer, exists:type_users,id` | Menjamin relasi FK valid; mencegah nilai yang merujuk record tidak ada. |
| `status_user_id` | `required, exists:status_users,id` | `required, integer, exists:status_users,id` | Menjamin relasi FK valid. |
| Soft-delete collision check | Memeriksa konflik nama, email, username | **Hanya memeriksa konflik email, username, NIK** | Pemulihan record soft-delete tidak boleh terblokir hanya karena nama yang sama. |

### B. Penyempurnaan Factory Model
- `UserFactory.php`: Memastikan NIK yang digenerate menggunakan format 16 digit (`fake()->numerify('################')`), nama tidak dipaksa unik, status aktif default true.
- `StudentFactory.php`: Mengisi definisi default dengan data sintetis (nama, NIK sintetis 16 digit, tempat/tanggal lahir, jenis kelamin acak dari referensi, NIS/NISN berformat angka).
- `EmployeeFactory.php`: Mengisi definisi default untuk kebutuhan pengujian modul pegawai di masa mendatang.

---

## 5. Kriteria Penerimaan Fase 01c

1. **Isolasi Database & Storage**:
   - Seluruh automated test berjalan pada SQLite in-memory disposable (`:memory:`).
   - Pengujian yang memanipulasi storage terisolasi menggunakan fake disk atau temporary folder, tidak menulis berkas ke storage publik/aplikasi.
   - Fail-safe database aktif: upaya menjalankan test terhadap target non-disposable langsung menggagalkan eksekusi.
2. **Penyelesaian BLK-03**:
   - `DatabaseSeeder.php` di source code aktif bersih dari identitas riil orang perorangan.
   - Data referensi master dapat di-seed secara terpisah (`ReferenceDataSeeder`) tanpa melibatkan data pribadi.
   - Tidak ada PII atau data siswa nyata yang tertinggal pada berkas seeder aktif.
3. **Validasi Akun**:
   - Pembuatan dan pembaruan akun mengizinkan nama yang sama (nama tidak wajib unik terverifikasi via tes).
   - Format NIK tervalidasi 16 digit angka string.
   - Validasi Foreign Key menolak ID referensi yang tidak terdaftar dengan status 422.
4. **Bootstrap Akun**:
   - Tersedia mekanisme pembuatan akun System Administrator pertama yang aman tanpa kredensial hardcoded di repositori.
5. **Integritas Test Suite**:
   - Seluruh test suite backend (`php artisan test`) dan frontend unit test tetap 100% PASS tanpa regresi pada fitur yang sudah diterima (01a & 01b).

---

## 6. Pertanyaan / Keputusan yang Diperlukan dari Pemilik

Sebelum implementasi kode Fase 01c dimulai, terdapat 3 keputusan teknis yang memerlukan konfirmasi pemilik:

1. **Keputusan Q-01c-1 (Mekanisme Bootstrap Akun Pertama)**:
   - *Opsi A (Rekomendasi)*: Perintah CLI Artisan interaktif (`php artisan app:bootstrap-admin` atau sejenisnya) yang meminta username, email, NIK, dan password saat instalasi.
   - *Opsi B*: Seeder yang membaca kredensial dari file `.env` lokal (mis. `ADMIN_INITIAL_PASSWORD=...`), jika kosong menghasilkan kata sandi acak yang dicetak sekali ke console.
2. **Keputusan Q-01c-2 (Penanganan Berkas `StudentSeeder.php` 62k Baris)**:
   - *Opsi A (Rekomendasi)*: Menghapus isi data riil dari `StudentSeeder.php` pada commit aktif dan menggantinya dengan seeder sintetis terukur (10–20 siswa fiktif) untuk kebutuhan uji coba lokal.
   - *Opsi B*: Mengosongkan `StudentSeeder.php` sepenuhnya (data siswa hanya dimasukkan melalui form aplikasi atau modul impor di fase mendatang).
3. **Keputusan Q-01c-3 (Aturan Kekuatan Kata Sandi Akun Baru)**:
   - *Opsi A*: Cukup minimal 8 karakter (standar saat ini).
   - *Opsi B*: Mewajibkan kombinasi huruf besar, huruf kecil, dan angka (`Password::min(8)->letters()->numbers()`).

---

## 7. Batasan & Langkah Selanjutnya
- Langkah ini **hanya dokumen perencanaan**. Tidak ada kode aplikasi, konfigurasi, migrasi, atau basis data yang diubah pada sesi ini.
- Pelaksanaan Fase 01c baru akan dimulai setelah pemilik meninjau dan menyetujui rencana di atas serta memberikan arahan atas pertanyaan Q-01c-1 sampai Q-01c-3.
