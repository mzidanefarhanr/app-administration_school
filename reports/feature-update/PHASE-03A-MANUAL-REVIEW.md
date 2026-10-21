# Panduan Review Manual Subfase 03a — Identitas Siswa dan Migrasi Keanggotaan Rombel

- Tanggal WIB: 2026-10-21
- Subfase: **03a — Identitas dan Membership Migration**
- Branch: `main`
- Baseline: `8feff53` ("fix(phase-03a): enhance rombel trigger concurrency with row lock and safe fallback resolution")
- Status teknis: **IMPLEMENTED & FULLY VERIFIED (29 backend test PASS, Pint 100% clean, build PASS)**
- Status checklist pemilik: **APPROVED (Diterima Penuh oleh Pemilik pada 21 Oktober 2026 WIB mengacu baseline `37f7d79`)**
- Isolasi pengujian: Seluruh pengujian manual menggunakan server UAT lokal port 8001/4001 dan database disposable `school_access_uat` tanpa menyentuh data operasional.
- **Peringatan Keselamatan Manual**:
  - Dilarang menjalankan query destruktif atau pengosongan database pada `school_access_uat`.
  - Database operasional `backdata_administrations` dilarang keras disentuh.
  - Seluruh skenario mutasi manual di bawah ini dirancang menggunakan blok **transaksi database (`DB::beginTransaction()` ... `DB::rollBack()`)** di dalam klausa `try ... finally`. Seluruh data uji akan otomatis dibatalkan (*rolled back*) bahkan saat terjadi exception, sehingga database UAT tetap bersih dan riwayat/audit existing tidak terhapus.
  - **Catatan Konkurensi**: Pengujian integritas konkurensi (dua transaksi bersamaan yang menserialisasi kunci baris siswa via row lock InnoDB) telah dibuktikan secara otomatis melalui integration test nyata MariaDB (`tests/Feature/Student/MariaDbStudentIntegrationTest.php`), sehingga pemilik tidak perlu melakukan uji konkurensi multi-terminal manual yang rumit.

---

## 1. Persiapan Lingkungan UAT Lokal & Preflight Guard

Pastikan server UAT lokal aktif pada port `8001` (API) dan `4001` (SPA):
- **API UAT**: `http://127.0.0.1:8001` (`php artisan serve --host=127.0.0.1 --port=8001 --env=uat`)
- **SPA UAT**: `http://127.0.0.1:4001` (`vite --mode uat --host 127.0.0.1 --port 4001`)

### Preflight Connection Check (Wajib Dijalankan Pertama Kali)
Sebelum menjalankan perintah mutasi pada Tinker, buka terminal `laravel-api` lalu masuk ke Tinker dengan environment eksplisit `uat`:
```bash
php artisan tinker --env=uat
```
Jalankan pemeriksaan koneksi aktif di dalam prompt Tinker:
```php
DB::connection()->getDatabaseName();
```
**Hasil Wajib**: Mengembalikan `'school_access_uat'`. Jika mengembalikan nama database lain (misal `backdata_administrations`), **segera ketik `exit` dan batalkan eksekusi!**

---

## 2. Klarifikasi Batasan Validasi Jalur Tulis vs Scope Subfase

Untuk menghindari kerancuan dalam verifikasi:
1. **Validasi NIK pada `StudentBackfillService`**: Hanya berlaku untuk memvalidasi kelayakan kandidat pemadanan NIK data warisan (*legacy backfill*) agar string non-16 digit atau ambigu tidak dipadankan secara keliru.
2. **Validasi NIK pada `app:bootstrap-admin`**: Perintah CLI tersebut dikhususkan untuk akun administrator sistem pertama, bukan jalur pembuatan data siswa.
3. **Validasi Jalur Tulis CRUD Siswa**: Validasi input form HTTP (`StoreStudentRequest` / `UpdateStudentRequest` dengan penolakan HTTP 422, nama boleh sama, format tanggal, dsb.) secara resmi berada pada lingkup **Subfase 03b**.
4. **Validasi yang Ditegakkan pada Subfase 03a**:
   - Skema database: `student_id` nullable FK, `student_nik` nullable string.
   - Database Trigger: Penegakan aturan maksimal 1 rombel aktif per siswa per tahun ajaran via native MariaDB trigger (`SQLSTATE 45000`) dengan InnoDB row lock pada tabel `students`.
   - Resolusi Model: `resolveStudent()` memprioritaskan `student_id` dan hanya menggunakan `student_nik` jika cocok unik dengan 1 siswa.

---

## 3. Checklist Pemeriksaan Manual Terisolasi

Status: **APPROVED** = seluruh hasil uji manual #1–#11 telah diverifikasi dan disetujui penuh oleh pemilik pada 21 Oktober 2026 WIB mengacu baseline `37f7d79`. Pembuktian konkurensi multi-transaksi diakui berdasarkan bukti automated MariaDB integration test agen.

### Pemetaan Perubahan Nomor Checklist:
- **#1 s/d #5**: Identik dengan draft sebelumnya (Status migrasi, DESCRIBE, Backfill PII, Rombel ganda, Mutasi kelas).
- **#6**: Skenario keanggotaan aktif pada tahun ajaran berbeda (ditambahkan sebagai skenario mandiri).
- **#7**: Siswa tanpa NIK masuk rombel (sebelumnya nomor #6 / #7).
- **#8**: Perhitungan umur dinamis (sebelumnya nomor #7 / #8).
- **#9**: Preservasi leading zero (sebelumnya nomor #8 / #9).
- **#10**: Resolusi fallback studentNik anti-ambigu (sebelumnya nomor #9).
- **#11**: Ekspor siswa CSV kompatibel (sebelumnya nomor #10).

---

| # | Skenario Uji | Tindakan / Cuplikan Kode Manual | Hasil yang Diharapkan | Status Pemilik |
|---|---|---|---|---|
| 1 | Status Migrasi Database UAT | Di folder `laravel-api`, jalankan CLI:<br>`php artisan migrate:status --env=uat` | Seluruh migrasi hingga `2029_07_23_000001_add_student_id_to_student_rombels_table` berstatus **Ran**. | **APPROVED** |
| 2 | Struktur Kolom `student_rombels` | Buka MariaDB CLI:<br>`C:\xampp\mysql\bin\mysql.exe -u root school_access_uat -e "DESCRIBE student_rombels;"` | Kolom `student_id` bertipe `bigint(20) unsigned` (Nullable: YES). Kolom `student_nik` bertipe `varchar(255)` (Nullable: **YES**). | **APPROVED** |
| 3 | Evaluasi Backfill UAT Bebas PII | Di folder `laravel-api`, jalankan CLI:<br>`php artisan --env=uat app:student-backfill --dry-run` | Menampilkan tabel metrik backfill: baris dievaluasi, status `Already Resolved`, dan `Total Unresolved: 0`. **Bebas dari pencetakan nama siswa atau NIK**. | **APPROVED** |
| 4 | Penolakan Rombel Ganda Aktif di Tahun Sama | Di `php artisan tinker --env=uat`, jalankan transaksi terisolasi:<br><pre>DB::beginTransaction();<br>try {<br>    $rombel = \App\Models\SchoolRombel::firstOrFail();<br>    $statusAktif = \App\Models\StudentStatus::where('name', 'Aktif')->firstOrFail();<br>    $s = \App\Models\Student::create(['name' => 'MANUAL-UAT-DUP-'.uniqid()]);<br>    \App\Models\StudentRombel::create(['student_id' => $s->id, 'school_rombel' => $rombel->id, 'student_status_id' => $statusAktif->id]);<br>    // Percobaan kedua di rombel/tahun yang sama:<br>    \App\Models\StudentRombel::create(['student_id' => $s->id, 'school_rombel' => $rombel->id, 'student_status_id' => $statusAktif->id]);<br>} catch (\Throwable $e) {<br>    echo "REJECTED: " . $e->getMessage() . "\n";<br>} finally {<br>    DB::rollBack();<br>}</pre> | Menampilkan pesan penolakan: `REJECTED: Student already has an active rombel membership in this academic year.` Transaksi di-rollback bersih. | **APPROVED** |
| 5 | Preservasi Riwayat Mutasi Kelas di Tahun Sama | Di `php artisan tinker --env=uat`, jalankan transaksi terisolasi:<br><pre>DB::beginTransaction();<br>try {<br>    $rombel = \App\Models\SchoolRombel::firstOrFail();<br>    $statusAktif = \App\Models\StudentStatus::where('name', 'Aktif')->firstOrFail();<br>    $statusMutasi = \App\Models\StudentStatus::firstOrCreate(['name' => 'Mutasi Keluar']);<br>    $s = \App\Models\Student::create(['name' => 'MANUAL-UAT-HIST-'.uniqid()]);<br>    // Riwayat lama non-aktif (Mutasi Keluar dengan exit date):<br>    $sr1 = \App\Models\StudentRombel::create(['student_id' => $s->id, 'school_rombel' => $rombel->id, 'student_status_id' => $statusMutasi->id, 'latest_student_exit_date' => '2026-09-01']);<br>    // Pendaftaran baru berstatus Aktif di tahun yang sama:<br>    $sr2 = \App\Models\StudentRombel::create(['student_id' => $s->id, 'school_rombel' => $rombel->id, 'student_status_id' => $statusAktif->id]);<br>    echo "SUCCESS: Riwayat Mutasi (ID {$sr1->id}) dan Rombel Aktif (ID {$sr2->id}) keduanya tersimpan.\n";<br>} finally {<br>    DB::rollBack();<br>}</pre> | Menampilkan `SUCCESS: Riwayat Mutasi ... keduanya tersimpan.` Trigger mengizinkan karena keanggotaan lama non-aktif. Transaksi di-rollback bersih. | **APPROVED** |
| 6 | Keanggotaan Aktif pada Tahun Ajaran Berbeda | Di `php artisan tinker --env=uat`, jalankan transaksi terisolasi:<br><pre>DB::beginTransaction();<br>try {<br>    $rombel1 = \App\Models\SchoolRombel::firstOrFail();<br>    $statusAktif = \App\Models\StudentStatus::where('name', 'Aktif')->firstOrFail();<br>    $year2 = \App\Models\SchoolYear::create(['name' => '2027/2028 UAT '.uniqid()]);<br>    $rombel2 = \App\Models\SchoolRombel::create(['name' => '11-A UAT', 'school_year_id' => $year2->id, 'school_level_id' => $rombel1->school_level_id]);<br>    $s = \App\Models\Student::create(['name' => 'MANUAL-UAT-YEARS-'.uniqid()]);<br>    $sr1 = \App\Models\StudentRombel::create(['student_id' => $s->id, 'school_rombel' => $rombel1->id, 'student_status_id' => $statusAktif->id]);<br>    $sr2 = \App\Models\StudentRombel::create(['student_id' => $s->id, 'school_rombel' => $rombel2->id, 'student_status_id' => $statusAktif->id]);<br>    echo "SUCCESS: Aktif di Tahun 1 (ID {$sr1->id}) dan Tahun 2 (ID {$sr2->id}) keduanya diizinkan.\n";<br>} finally {<br>    DB::rollBack();<br>}</pre> | Menampilkan `SUCCESS: Aktif di Tahun 1 ... dan Tahun 2 ... keduanya diizinkan.` Siswa boleh memiliki 1 rombel aktif di setiap tahun berbeda. Di-rollback bersih. | **APPROVED** |
| 7 | Siswa Tanpa NIK Masuk Rombel | Di `php artisan tinker --env=uat`, jalankan transaksi terisolasi:<br><pre>DB::beginTransaction();<br>try {<br>    $rombel = \App\Models\SchoolRombel::firstOrFail();<br>    $statusAktif = \App\Models\StudentStatus::where('name', 'Aktif')->firstOrFail();<br>    $s = \App\Models\Student::create(['name' => 'MANUAL-UAT-NONIK-'.uniqid(), 'nik' => null]);<br>    $sr = \App\Models\StudentRombel::create(['student_id' => $s->id, 'school_rombel' => $rombel->id, 'student_status_id' => $statusAktif->id]);<br>    echo "SUCCESS: Siswa ID {$s->id} (NIK null) tersimpan di rombel SR ID {$sr->id} dengan student_nik: " . var_export($sr->student_nik, true) . "\n";<br>} finally {<br>    DB::rollBack();<br>}</pre> | Menampilkan `SUCCESS: Siswa ID ... (NIK null) tersimpan di rombel ... student_nik: NULL`. Siswa tanpa NIK berhasil terdaftar via `student_id`. Di-rollback bersih. | **APPROVED** |
| 8 | Perhitungan Umur Dinamis (Asia/Jakarta) | Di `php artisan tinker --env=uat`, periksa kalkulasi umur:<br><pre>$s1 = new \App\Models\Student(['birthdate' => '2010-10-21']);<br>$s2 = new \App\Models\Student(['birthdate' => null]);<br>echo "Lahir 2010-10-21: " . $s1->age . " tahun | Lahir null: " . var_export($s2->age, true) . "\n";</pre> | Menampilkan usia terhitung dinamis per hari ini di zona waktu `Asia/Jakarta` (misal: 16 tahun) dan `NULL` untuk tanggal kosong/tidak valid. | **APPROVED** |
| 9 | Preservasi Leading Zero Identitas String | Di `php artisan tinker --env=uat`, jalankan transaksi terisolasi:<br><pre>DB::beginTransaction();<br>try {<br>    $s = \App\Models\Student::create(['name' => 'MANUAL-UAT-ZERO-'.uniqid(), 'nik' => '0098765432109876', 'nis' => '008', 'nisn' => '0001234567']);<br>    $fresh = \App\Models\Student::find($s->id);<br>    echo "PRESERVED: NIK={$fresh->nik} | NIS={$fresh->nis} | NISN={$fresh->nisn}\n";<br>} finally {<br>    DB::rollBack();<br>}</pre> | Menampilkan `PRESERVED: NIK=0098765432109876 | NIS=008 | NISN=0001234567`. Nol di depan tidak terpangkas. Di-rollback bersih. | **APPROVED** |
| 10 | Resolusi Aman Fallback `studentNik` | Di `php artisan tinker --env=uat`, jalankan transaksi terisolasi:<br><pre>DB::beginTransaction();<br>try {<br>    $marker = 'MANUAL-UAT-FB-'.uniqid();<br>    $s = \App\Models\Student::create(['name' => $marker, 'nik' => '9988776655443322']);<br>    // Baris legacy tanpa student_id:<br>    $sr = new \App\Models\StudentRombel(['student_id' => null, 'student_nik' => '9988776655443322']);<br>    echo "RESOLVED: " . $sr->resolveStudent()?->name . "\n";<br>    // Uji NIK orphan (tidak ada siswa):<br>    $srOrphan = new \App\Models\StudentRombel(['student_id' => null, 'student_nik' => '0000000000000000']);<br>    echo "ORPHAN: " . var_export($srOrphan->resolveStudent(), true) . "\n";<br>} finally {<br>    DB::rollBack();<br>}</pre> | Menampilkan `RESOLVED: <marker>` dan `ORPHAN: NULL`. Fallback hanya bekerja pada NIK tunggal yang cocok pasti, dan mengembalikan null bila tidak cocok. Di-rollback bersih. | **APPROVED** |
| 11 | Ekspor Siswa CSV Tetap Kompatibel | Login di browser sebagai `uat.admin` (`http://127.0.0.1:4001/public/students`). Klik tombol **Export**. Buka berkas CSV hasil unduhan. | Berkas CSV berhasil diunduh dan memuat seluruh data siswa dengan relasi rombel dan status yang sesuai, tanpa error. | **APPROVED** |

---

## 4. Bukti Pengujian Otomatis Konkurensi Agen

Untuk melengkapi review manual tanpa membebani pemilik dengan uji konkurensi multi-transaksi secara manual, agen menyajikan bukti pengujian otomatis yang dijalankan langsung pada database MariaDB lokal (`tests/Feature/Student/MariaDbStudentIntegrationTest.php`):

1. **Uji Konkurensi Dua Transaksi**:
   - Transaksi 1 (`$pdo1`) menyisipkan keanggotaan aktif siswa S pada tahun Y (mengunci baris siswa via `SELECT ... FOR UPDATE`).
   - Transaksi 2 (`$pdo2`) bersamaan mencoba menyisipkan keanggotaan aktif siswa S pada rombel lain di tahun Y yang sama.
   - **Hasil Aktual**: Transaksi 1 berhasil commit; Transaksi 2 ditolak dengan `SQLSTATE 45000: Student already has an active rombel membership in this academic year.`
   - Hasil akhir database: **tepat 1 keanggotaan aktif**.
2. **Uji Perubahan Status Non-Aktif Menjadi Aktif (`UPDATE`)**:
   - Baris keanggotaan non-aktif yang diubah menjadi aktif (`student_status_id = 1`) saat siswa sudah memiliki rombel aktif lain di tahun yang sama **ditolak keras** oleh trigger update.
3. **Uji Pencegahan Bypass Baris Legacy (`student_id = NULL`)**:
   - Baris lama dengan `student_id = NULL` dan `student_nik = X` berhasil mendeteksi dan menolak upaya pendaftaran rombel aktif kedua melalui jalur `student_id`.

---

## 5. Catatan Persetujuan Pemilik

Persetujuan pemilik diberikan pada 21 Oktober 2026 WIB mengacu baseline commit `37f7d79`:
- Seluruh butir checklist manual #1–#11 dinyatakan **APPROVED**.
- Pembuktian serialisasi konkurensi multi-transaksi diakui berdasarkan bukti automated `MariaDbStudentIntegrationTest` agen.
- Subfase 03a resmi berstatus **DITERIMA PEMILIK**.
