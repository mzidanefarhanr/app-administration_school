# Panduan Review Manual Subfase 03a — Identitas Siswa dan Migrasi Keanggotaan Rombel

- Tanggal WIB: 2026-10-21
- Subfase: **03a — Identitas dan Membership Migration**
- Branch: `main`
- Status teknis: **IMPLEMENTED & FULLY VERIFIED (22 backend test PASS, Pint 100% clean, build PASS)**
- Status checklist pemilik: **PENDING (Menunggu Pemeriksaan Manual Pemilik)**
- Isolasi pengujian: Seluruh pengujian manual menggunakan server UAT lokal port 8001/4001 dan database disposable `school_access_uat` tanpa menyentuh data operasional.
- **Peringatan Keselamatan Manual**:
  - Dilarang menjalankan query destruktif atau pengosongan database pada `school_access_uat`.
  - Database operasional `backdata_administrations` dilarang disentuh.

---

## 1. Persiapan Lingkungan UAT Lokal

Pastikan server UAT lokal aktif pada port `8001` (API) dan `4001` (SPA) menggunakan database disposable `school_access_uat`:
- **API UAT**: `http://127.0.0.1:8001` (`php artisan serve --host=127.0.0.1 --port=8001 --env=uat`)
- **SPA UAT**: `http://127.0.0.1:4001` (`vite --mode uat --host 127.0.0.1 --port 4001`)

---

## 2. Checklist Pemeriksaan Manual Terisolasi

Status: **PENDING** = menunggu verifikasi dan persetujuan pemilik; **APPROVED** = pemilik telah menyetujui hasil uji manual.

| # | Skenario Uji | Tindakan / Perintah | Hasil yang Diharapkan | Status Pemilik |
|---|---|---|---|---|
| 1 | Status Migrasi Database UAT | Di folder `laravel-api`, jalankan: `php artisan migrate:status --env=uat` | Seluruh migrasi hingga `2029_07_23_000001_add_student_id_to_student_rombels_table` berstatus **Ran**. | **PENDING** |
| 2 | Struktur Kolom `student_rombels` | Buka MariaDB CLI: `C:\xampp\mysql\bin\mysql.exe -u root school_access_uat -e "DESCRIBE student_rombels;"` | Kolom `student_id` bertipe `bigint(20) unsigned` (Nullable: YES). Kolom `student_nik` memiliki Nullable: **YES**. | **PENDING** |
| 3 | Verifikasi Backfill UAT Bebas PII | Di folder `laravel-api`, jalankan: `php artisan --env=uat app:student-backfill --dry-run` | Menampilkan tabel metrik backfill: 3 record dievaluasi, 3 `Already Resolved`, 0 `Unresolved`. **Tidak ada nama siswa atau NIK yang tercetak**. | **PENDING** |
| 4 | Penolakan Rombel Ganda Aktif di Tahun yang Sama | Di folder `laravel-api`, jalankan tinker UAT: `php artisan tinker --env=uat` lalu jalankan perintah: `\App\Models\StudentRombel::create(['student_id' => 1, 'school_rombel' => 1, 'student_status_id' => 1]);` | Operasi langsung ditolak dengan **DomainException / SQLSTATE 45000**: `'Student already has an active rombel membership in this academic year.'` | **PENDING** |
| 5 | Preservasi Riwayat Mutasi Kelas di Tahun Sama | Di tinker UAT, daftarkan siswa 1 ke rombel yang sama tetapi dengan status non-aktif (misal status 2 'Mutasi Keluar'): `\App\Models\StudentRombel::create(['student_id' => 1, 'school_rombel' => 1, 'student_status_id' => 2, 'latest_student_exit_date' => '2026-09-01']);` | Operasi **berhasil disimpan**. Riwayat mutasi tersimpan tanpa memicu bentrok trigger. | **PENDING** |
| 6 | Keanggotaan Aktif pada Tahun Ajaran Berbeda | Di tinker UAT, daftarkan siswa 1 ke rombel di tahun ajaran berbeda (buat rombel uji jika perlu) | Operasi **berhasil disimpan**. Siswa dapat memiliki 1 rombel aktif di setiap tahun ajaran berbeda. | **PENDING** |
| 7 | Siswa Tanpa NIK Masuk Rombel | Di tinker UAT, buat siswa tanpa NIK: `$s = \App\Models\Student::create(['name' => 'Siswa Baru Belum Punya NIK', 'nik' => null]);` lalu masukkan ke rombel 1: `\App\Models\StudentRombel::create(['student_id' => $s->id, 'school_rombel' => 1, 'student_status_id' => 1]);` | Siswa tanpa NIK **berhasil disimpan ke dalam rombel** (`student_id` terisi, `student_nik` bernilai null). | **PENDING** |
| 8 | Perhitungan Umur Dinamis (Asia/Jakarta) | Di tinker UAT, periksa umur siswa: `$s = new \App\Models\Student(['name' => 'Test', 'birthdate' => '2010-10-21']); echo $s->age;` | Umur dihitung dinamis dari tanggal hari ini (`Asia/Jakarta`). Jika tanggal lahir null atau di masa depan, `$s->age` mengembalikan `null`. | **PENDING** |
| 9 | Preservasi Leading Zero Identitas String | Di tinker UAT, periksa NIK/NIS siswa: `$s = \App\Models\Student::create(['name' => 'Test Zero', 'nik' => '0012345678901234', 'nis' => '005']); echo $s->nik . ' | ' . $s->nis;` | Menampilkan tepat `0012345678901234 | 005`. Angka nol di depan **tidak hilang atau terpangkas**. | **PENDING** |
| 10 | Ekspor Siswa CSV Tetap Kompatibel | Login di browser sebagai `uat.admin` (`http://127.0.0.1:4001/public/students`). Klik tombol **Export**. Buka berkas CSV hasil unduhan. | Berkas CSV berhasil diunduh dan memuat seluruh data siswa dengan relasi rombel dan status yang sesuai, tanpa error. | **PENDING** |

---

## 3. Cara Memberikan Persetujuan Pemilik

Setelah Anda memeriksa butir-butir di atas pada lingkungan lokal, kirim instruksi:
```text
Catat hasil pemeriksaan manual saya untuk subfase 03a pada kandidat <SHA>.
Hasil: checklist nomor #1–#10 APPROVED.
```
Hasil pemeriksaan manual Anda akan dicatat dalam commit dokumentasi tersendiri sesuai aturan repo.
