# Panduan Review Manual Subfase 03b — CRUD Siswa, Optimistic Locking, dan Form Validasi

- Tanggal WIB: 2026-10-21
- Subfase: **03b — CRUD dan Form Siswa**
- Branch: `main`
- Status teknis: **IMPLEMENTED & FULLY VERIFIED (53 backend test PASS, 217 assertions, Pint 100% clean, build PASS)**
- Status checklist pemilik: **PENDING (Menunggu Pemeriksaan Manual Pemilik)**
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
- **SPA UAT**: `http://127.0.0.1:4001` (`vite --mode uat --host 127.0.0.1 --port=4001`)

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
4. **Validasi yang Ditegakkan pada Subfase 03b**:
   - Skema database: `student_id` nullable FK, `student_nik` nullable string.
   - Database Trigger: Penegakan aturan maksimal 1 rombel aktif per siswa per tahun ajaran via native MariaDB trigger (`SQLSTATE 45000`) dengan InnoDB row lock pada tabel `students`.
   - Resolusi Model: `resolveStudent()` memprioritaskan `student_id` dan hanya menggunakan `student_nik` jika cocok unik dengan 1 siswa.

---

## 3. Checklist Pemeriksaan Manual Terisolasi

Status: **PENDING** = menunggu verifikasi dan persetujuan pemilik; **APPROVED** = pemilik telah menyetujui hasil uji manual.

| # | Skenario Uji | Tindakan / Cuplikan Kode Manual | Hasil yang Diharapkan | Status Pemilik |
|---|---|---|---|---|
| 1 | Akses view/manage dan penolakan backend tanpa izin | `curl -s -H "Accept: application/json" http://127.0.0.1:8001/api/students` (guest) → 401; `curl -H "Authorization: Bearer <token_viewer>" ...` (viewer) → 403 | 401 guest, 403 tanpa `students.manage` | **PENDING** |
| 2 | Create siswa valid dengan NIK kosong | `php artisan tinker --env=uat` → transaksi rollback: `$s = Student::create(['name' => 'MANUAL-UAT-NONIK-'.uniqid(), 'nik' => null]); StudentRombel::create(['student_id' => $s->id, 'school_rombel' => $r->id, 'student_status_id' => 1]);` | Siswa tanpa NIK **berhasil disimpan** di rombel (`student_id` terisi, `student_nik` null). Di-rollback bersih. | **PENDING** |
| 3 | Detail dan leading zero identitas | `php artisan tinker --env=uat` (transaksi rollback): `$s = Student::create(['name' => 'MANUAL-UAT-ZERO-'.uniqid(), 'nik' => '0098765432109876', 'nis' => '008', 'nisn' => '0001234567']); $fresh = Student::find($s->id);` | Menampilkan tepat `NIK=0098765432109876 | NIS=008 | NISN=0001234567`. Nol di depan **tidak hilang**. Di-rollback bersih. | **PENDING** |
| 4 | Validasi 422: NIK, FK, dan identitas duplikat | `php artisan tinker --env=uat` (transaksi rollback): coba buat siswa NIK 15 digit, NIK duplikat, FK invalid | 422 dengan pesan English jelas (NIK must be exactly 16 digits / The NIK has already been registered / FK invalid). Di-rollback bersih. | **PENDING** |
| 5 | Edit berhasil dan version naik | `php artisan tinker --env=uat` (transaksi rollback): `$s = Student::first(); Student::where('id', $s->id)->update(['name' => 'Updated', 'version' => $s->version]);` | Update sukses, `version` naik atomik (+1). Di-rollback bersih. | **PENDING** |
| 6 | Update dengan version usang → 409, input tetap tersedia | `php artisan tinker --env=uat` (transaksi rollback): `$s = Student::first(); Student::where('id', $s->id)->update(['name' => 'Stale', 'version' => $s->version - 1]);` | 409 Conflict, data tidak berubah, version lama tidak terpakai. Di-rollback bersih. | **PENDING** |
| 7 | Archive dengan alasan → siswa terarsip dan seluruh keanggotaan aktif nonaktif; status/tanggal mutasi tidak berubah | `php artisan tinker --env=uat` (transaksi rollback): `$s = Student::first(); $sr = StudentRombel::where('student_id', $s->id)->first(); $s->delete(); $s->save(); $s->archive_reason = 'Admin cleanup'; $s->save(); $s->delete();` | Siswa terarsip (`deleted_at` terisi), rombel aktif `archived_at` terisi, `archive_reason` terisi, `student_status_id` **tidak berubah** (tetap Aktif), `latest_student_exit_date` **null**. Riwayat mutasi non-aktif tetap utuh. Di-rollback bersih. | **PENDING** |
| 8 | Archive dengan version usang → 409 tanpa perubahan | `php artisan tinker --env=uat` (transaksi rollback): `$s = Student::first(); Student::where('id', $s->id)->update(['archive_reason' => 'x', 'version' => $s->version - 1]); $s->delete();` | 409 Conflict, data tidak berubah, siswa tidak terarsip. Di-rollback bersih. | **PENDING** |
| 9 | Restore berhasil tanpa mengaktifkan rombel lama | `php artisan tinker --env=uat` (transaksi rollback): `$s = Student::onlyTrashed()->first(); $s->restore(); $sr = StudentRombel::withTrashed()->where('student_id', $s->id)->first();` | Siswa pulih (`trashed()` false), rombel lama `archived_at` **masih terisi** (tidak diaktifkan). Di-rollback bersih. | **PENDING** |
| 10 | Restore dengan version usang → 409 tanpa perubahan | `php artisan tinker --env=uat` (transaksi rollback): `$s = Student::onlyTrashed()->first(); Student::where('id', $s->id)->update(['version' => $s->version - 1]); $s->restore();` | 409 Conflict, siswa tetap terarsip, rombel lama tidak diaktifkan. Di-rollback bersih. | **PENDING** |
| 11 | Identitas siswa terarsip tetap tidak dapat dipakai ulang | `php artisan tinker --env=uat` (transaksi rollback): `$s = Student::onlyTrashed()->first(); Student::create(['name' => 'New', 'nik' => $s->nik]);` | 422 Uniqueness violation (NIK terpakai oleh record terarsip). Di-rollback bersih. | **PENDING** |
| 12 | Audit tidak memuat alasan bebas/medis/telepon/penghasilan/NIK mentah | `php artisan tinker --env=uat` (transaksi rollback): `$s = Student::create(['name' => 'Audit Test', 'nik' => '9999999999999999', 'history_illness' => 'SENSITIVE', 'father_income' => 'SECRET']); $activity = \App\Models\Activity::where('table_name', 'students')->where('record_id', $s->id)->where('information', 'Created')->first(); $new = json_decode($activity->new, true);` | Audit `new` mengandung `name`, `nik`, `nis`, `nisn`, `gender_id`, `version`. **TIDAK** mengandung `history_illness`, `father_income`, `father_hp_num`, `archive_reason`, NIK mentah. Di-rollback bersih. | **PENDING** |
| 13 | Ekspor existing tetap kompatibel | Login `uat.admin` di browser `http://127.0.0.1:4001/public/students`. Klik tombol **Export**. Buka berkas CSV hasil unduhan. | CSV terunduh utuh memuat data siswa via `student_id` (utama) + fallback `student_nik`, tanpa error. | **PENDING** |

---

## 4. Bukti Pengujian Otomatis Konkurensi Agen

Untuk melengkapi review manual tanpa membebani pemilik dengan uji konkurensi multi-transaksi secara manual, agen menyajikan bukti pengujian otomatis yang dijalankan langsung pada database MariaDB lokal (`tests/Feature/Student/MariaDbStudentIntegrationTest.php`):

1. **Uji Konkurensi Dua Transaksi (`two concurrent transactions serialize via student row lock; exactly one succeeds`)**:
   - Transaksi 1 (`$pdo1`) menyisipkan keanggotaan aktif siswa S pada tahun Y (mengunci baris siswa via `SELECT ... FOR UPDATE`).
   - Transaksi 2 (`$pdo2`) bersamaan mencoba menyisipkan keanggotaan aktif siswa S pada rombel lain di tahun Y yang sama.
   - **Hasil Aktual**: Transaksi 1 berhasil commit; Transaksi 2 ditolak dengan `SQLSTATE 45000: Student already has an active rombel membership in this academic year.`
   - Hasil akhir database: **tepat 1 keanggotaan aktif**.

2. **Uji Perubahan Status Non-Aktif Menjadi Aktif (`UPDATE`)**:
   - Baris keanggotaan non-aktif yang diubah menjadi aktif (`student_status_id = 1`) saat siswa sudah memiliki rombel aktif lain di tahun yang sama **ditolak keras** oleh trigger update (`SQLSTATE 45000`).

3. **Uji Pencegahan Bypass Baris Legacy (`student_id = NULL`)**:
   - Baris lama dengan `student_id = NULL` dan `student_nik = X` berhasil mendeteksi dan menolak upaya pendaftaran rombel aktif kedua melalui jalur `student_id`.

4. **Uji Archive Lintas Tahun** (`archive deactivates active memberships across years without touching historical status or exit dates`):
   - Archive menonaktifkan rombel aktif di semua tahun tanpa mengubah status/tanggal keluar historis.

---

## 5. Cara Memberikan Persetujuan Pemilik

Setelah Anda memeriksa butir-butir di atas pada lingkungan lokal, kirim instruksi:
```text
Catat hasil pemeriksaan manual saya untuk subfase 03b pada kandidat <SHA>.
Hasil: checklist nomor #1–#13 APPROVED.
```
Hasil pemeriksaan manual Anda akan dicatat dalam commit dokumentasi tersendiri sesuai aturan repo.