# Panduan Review Manual Subfase 03a — Identitas Siswa dan Migrasi Keanggotaan Rombel

- Tanggal WIB: 2026-10-21
- Subfase: **03a — Identitas dan Membership Migration**
- Branch: `main`
- Status teknis: **IMPLEMENTED & FULLY VERIFIED (29 backend test PASS, Pint 100% clean, build PASS)**
- Status checklist pemilik: **PENDING (Menunggu Pemeriksaan Manual Pemilik)**
- Isolasi pengujian: Seluruh pengujian manual menggunakan server UAT lokal port 8001/4001 dan database disposable `school_access_uat` tanpa menyentuh data operasional.
- **Peringatan Keselamatan Manual**:
  - Dilarang menjalankan query destruktif atau pengosongan database pada `school_access_uat`.
  - Database operasional `backdata_administrations` dilarang keras disentuh.
  - Setiap fixture uji manual yang disisipkan wajib dibersihkan menggunakan perintah pemulihan yang disediakan di setiap butir checklist.
  - **Catatan Konkurensi**: Pengujian integritas konkurensi (dua transaksi bersamaan yang menserialisasi kunci baris siswa) telah dibuktikan secara otomatis melalui integration test nyata MariaDB (`tests/Feature/Student/MariaDbStudentIntegrationTest.php`), sehingga pemilik tidak perlu melakukan uji konkurensi manual multi-terminal yang rumit.

---

## 1. Persiapan Lingkungan UAT Lokal

Pastikan server UAT lokal aktif pada port `8001` (API) dan `4001` (SPA) menggunakan database disposable `school_access_uat`:
- **API UAT**: `http://127.0.0.1:8001` (`php artisan serve --host=127.0.0.1 --port=8001 --env=uat`)
- **SPA UAT**: `http://127.0.0.1:4001` (`vite --mode uat --host 127.0.0.1 --port 4001`)

---

## 2. Checklist Pemeriksaan Manual Terisolasi

Status: **PENDING** = menunggu verifikasi dan persetujuan pemilik; **APPROVED** = pemilik telah menyetujui hasil uji manual.

| # | Skenario Uji | Tindakan / Perintah (Tinker / CLI UAT) | Hasil yang Diharapkan | Status Pemilik |
|---|---|---|---|---|
| 1 | Status Migrasi Database UAT | Di folder `laravel-api`, jalankan: `php artisan migrate:status --env=uat` | Seluruh migrasi hingga `2029_07_23_000001_add_student_id_to_student_rombels_table` berstatus **Ran**. | **PENDING** |
| 2 | Struktur Kolom `student_rombels` | Buka MariaDB CLI: `C:\xampp\mysql\bin\mysql.exe -u root school_access_uat -e "DESCRIBE student_rombels;"` | Kolom `student_id` bertipe `bigint(20) unsigned` (Nullable: YES). Kolom `student_nik` memiliki Nullable: **YES**. | **PENDING** |
| 3 | Verifikasi Backfill Bebas PII | Di folder `laravel-api`, jalankan: `php artisan --env=uat app:student-backfill --dry-run` | Menampilkan tabel metrik: baris terdaftar berstatus `Already Resolved`, `Total Unresolved: 0`. **Tidak ada nama siswa atau NIK yang tercetak**. | **PENDING** |
| 4 | Penolakan Rombel Ganda Aktif di Tahun Sama | Di `php artisan tinker --env=uat`: <br>`$s = \App\Models\Student::first();`<br>`$sr = \App\Models\StudentRombel::where('student_id', $s->id)->first();`<br>`\App\Models\StudentRombel::create(['student_id' => $s->id, 'school_rombel' => $sr->school_rombel, 'student_status_id' => 1]);` | Ditolak dengan **DomainException / SQLSTATE 45000**: `'Student already has an active rombel membership in this academic year.'` Data database tidak bertambah. | **PENDING** |
| 5 | Preservasi Riwayat Mutasi Kelas di Tahun Sama | Di tinker UAT: <br>`$s = \App\Models\Student::first();`<br>`$sr = \App\Models\StudentRombel::where('student_id', $s->id)->first();`<br>`$history = \App\Models\StudentRombel::create(['student_id' => $s->id, 'school_rombel' => $sr->school_rombel, 'student_status_id' => 2, 'latest_student_exit_date' => '2026-09-01']);`<br>*Pemulihan fixture*: `$history->forceDelete();` | **Berhasil disimpan** karena berstatus non-aktif (status 2 / exit date). Riwayat tersimpan tanpa bentrok trigger. Pemulihan menghapus fixture uji. | **PENDING** |
| 6 | Siswa Tanpa NIK Masuk Rombel | Di tinker UAT: <br>`$rombel = \App\Models\SchoolRombel::first();`<br>`$newS = \App\Models\Student::create(['name' => 'MANUAL-UAT-NONIK-'.uniqid(), 'nik' => null]);`<br>`$newSr = \App\Models\StudentRombel::create(['student_id' => $newS->id, 'school_rombel' => $rombel->id, 'student_status_id' => 1]);`<br>*Pemulihan fixture*: `$newSr->forceDelete(); $newS->forceDelete();` | Siswa tanpa NIK **berhasil disimpan ke dalam rombel** (`student_id` terisi, `student_nik` null). Pemulihan menghapus fixture uji. | **PENDING** |
| 7 | Perhitungan Umur Dinamis (Asia/Jakarta) | Di tinker UAT: <br>`$testS = new \App\Models\Student(['birthdate' => '2010-10-21']);`<br>`echo $testS->age;` | Umur dihitung dinamis dari tanggal hari ini (`Asia/Jakarta`). Jika tanggal lahir di masa depan atau null, `$testS->age` mengembalikan `null`. | **PENDING** |
| 8 | Preservasi Leading Zero Identitas String | Di tinker UAT: <br>`$zeroS = \App\Models\Student::create(['name' => 'MANUAL-UAT-ZERO-'.uniqid(), 'nik' => '0098765432109876', 'nis' => '008']);`<br>`echo $zeroS->nik . ' | ' . $zeroS->nis;`<br>*Pemulihan fixture*: `$zeroS->forceDelete();` | Menampilkan tepat `0098765432109876 | 008`. Angka nol di depan **tidak hilang atau terpangkas**. Pemulihan menghapus fixture uji. | **PENDING** |
| 9 | Resolusi Aman Fallback studentNik | Di tinker UAT: <br>`$s = \App\Models\Student::first();`<br>`$dummySr = new \App\Models\StudentRombel(['student_id' => null, 'student_nik' => $s->nik]);`<br>`echo $dummySr->resolveStudent()?->name;` | Mengembalikan nama siswa yang cocok secara tepat. Jika NIK tidak ada di database, mengembalikan `null` tanpa error. | **PENDING** |
| 10 | Ekspor Siswa CSV Tetap Kompatibel | Login di browser sebagai `uat.admin` (`http://127.0.0.1:4001/public/students`). Klik tombol **Export**. Buka berkas CSV hasil unduhan. | Berkas CSV berhasil diunduh dan memuat seluruh data siswa dengan relasi rombel dan status yang sesuai, tanpa error. | **PENDING** |

---

## 3. Bukti Pengujian Otomatis Konkurensi & Integritas Agen

Untuk melengkapi review manual tanpa membebani pemilik dengan uji konkurensi multi-transaksi secara manual, agen menyajikan bukti pengujian otomatis yang dijalankan langsung pada database MariaDB lokal:

1. **Uji Konkurensi Dua Transaksi (`tests/Feature/Student/MariaDbStudentIntegrationTest.php`)**:
   - Transaksi 1 (`$pdo1`) menyisipkan keanggotaan aktif siswa S pada tahun Y (mengunci baris siswa via `SELECT ... FOR UPDATE`).
   - Transaksi 2 (`$pdo2`) bersamaan mencoba menyisipkan keanggotaan aktif siswa S pada rombel lain di tahun Y yang sama.
   - **Hasil Aktual**: Transaksi 1 berhasil commit; Transaksi 2 ditolak dengan `SQLSTATE 45000: Student already has an active rombel membership in this academic year.`
   - Hasil akhir database: **tepat 1 keanggotaan aktif**.
2. **Uji Perubahan Status Non-Aktif Menjadi Aktif (`UPDATE`)**:
   - Baris keanggotaan non-aktif yang diubah menjadi aktif (`student_status_id = 1`) saat siswa sudah memiliki rombel aktif lain di tahun yang sama **ditolak keras** oleh trigger update.
3. **Uji Pencegahan Bypass Baris Legacy (`student_id = NULL`)**:
   - Baris lama dengan `student_id = NULL` dan `student_nik = X` berhasil mendeteksi dan menolak upaya pendaftaran rombel aktif kedua melalui jalur `student_id`.

---

## 4. Cara Memberikan Persetujuan Pemilik

Setelah Anda memeriksa butir-butir di atas pada lingkungan lokal, kirim instruksi:
```text
Catat hasil pemeriksaan manual saya untuk subfase 03a pada kandidat <SHA>.
Hasil: checklist nomor #1–#10 APPROVED.
```
Hasil pemeriksaan manual Anda akan dicatat dalam commit dokumentasi tersendiri sesuai aturan repo.
