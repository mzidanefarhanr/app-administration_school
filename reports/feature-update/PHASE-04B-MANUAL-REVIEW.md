# Panduan Review Manual Subfase 04b — CRUD Pegawai, Relasi Akun, Ekspor, dan Menu Aktif

- Tanggal WIB: 2026-10-29
- Subfase: **04b — CRUD dan menu aktif** (Fase 04: Guru dan Pegawai)
- Branch: `main`
- Baseline implementasi: working tree 2026-10-29 di atas `fff5249` (commit implementasi dicatat pada laporan penutupan)
- Status implementasi: **IMPLEMENTED & DIVERIFIKASI OTOMATIS (AGEN)**
- Status checklist pemilik: **PENDING** — belum dijalankan/disetujui pemilik
- Laporan teknis: `reports/feature-update/PHASE-04B-REPORT.md`
- Keputusan: **DEC-24** (D1–D8 APPROVED 2026-10-29 WIB)

> **Prasyarat Otorisasi (BELUM TERPENUHI):** Migration 04b `2029_07_26_000001` **belum** diterapkan ke database UAT maupun produksi, dan penerapannya **belum diotorisasi**. Checklist UI di bawah hanya dapat dijalankan setelah pemilik memilih salah satu jalur:
> (a) **Disposable** — buat database baru khusus review (mis. `school_04b_integration`), jalankan migration di sana, arahkan instance API review ke database tersebut; atau
> (b) **UAT** — berikan otorisasi eksplisit untuk menerapkan migration 04b pada `school_compat_uat` setelah backup.
> Jangan menjalankan checklist ini terhadap `backdata_administrations` atau database operasional lain.

---

## 1. Persiapan & Preflight Guard

Perangkat (sesuaikan port instance review yang aktif):
```powershell
$php = "C:\xampp\php82-operational\php.exe"
$mysql = "C:\xampp\mysql\bin\mysql.exe"
```

Preflight database (wajib):
```powershell
cd D:\xampp-htdocs\app-administration_school\laravel-api
& $php artisan tinker --env=uat
```
```php
DB::connection()->getDatabaseName();
DB::table('migrations')->where('migration', 'like', '%2029_07_26_000001%')->exists();
```
**Wajib** database bukan `backdata_administrations`, dan migration 04b bernilai `true`. Jika `false`, hentikan review dan terapkan migration hanya pada target yang diotorisasi.

Akun sintetis yang diperlukan:
- Akun dengan `employees.view` **tanpa** `employees.manage` — mis. `wakil-kesiswaan` atau kepala sekolah.
- Akun dengan `employees.view` + `employees.manage` + `employees.export` — mis. operator sekolah.
- Akun aktif yang **belum** tertaut profil pegawai (untuk uji taut akun).

**ID dinamis:** jangan menulis ID di panduan. Ambil ID/versi lewat Tinker atau dialog detail:
```php
$e = App\Models\Employee::withTrashed()->where('name', 'UAT Browser Teacher')->first();
[$e?->id, $e?->version, $e?->trashed(), $e?->exit_date, $e?->archive_reason];
```

**Penanda fixture sintetis:** nama `UAT Browser ...`, NUPTK berawalan `UAT-`, NIK unik 16 digit sintetis. Jangan memakai data pegawai riil.

---

## 2. Checklist Pemeriksaan Manual

Status: **PENDING** sampai pemilik menyatakan hasilnya.

| # | Skenario | Langkah | Hasil yang Diharapkan | Status Pemilik |
|---|---|---|---|---|
| 1 | Menu aktif & routing | Login akun ber-`employees.view` → buka menu **Teachers**, lalu **Employees** | URL `/public/teachers` dan `/public/employees` terbuka; menu tersorot aktif; judul halaman **Teachers**/**Employees** | **PENDING** |
| 2 | Daftar server-side | Pada halaman Teachers, ubah pencarian, filter status/kepegawaian/mapel, sort kolom Name/TMT, dan paginasi | Daftar hanya menampilkan tipe **Pendidik**; filter/sort/paginasi diterapkan server; tanpa error konsol | **PENDING** |
| 3 | Pemisahan tipe per halaman | Buka Employees | Daftar hanya berisi **Tenaga Kependidikan**; tidak ada tombol/filter lintas tipe | **PENDING** |
| 4 | Create + inline 422 | **New Teacher** → kosongkan Name dan TMT → **Save**; lalu isi NIK `123` | Dialog tetap terbuka; pesan English di bawah field terkait (`Employee name is required.`, `NIK must be exactly 16 digits.`); input tidak hilang | **PENDING** |
| 5 | Aturan mapel (D4) | Buat Pendidik tanpa Subject → simpan; lalu buat Tenaga Kependidikan dan pilih Subject → simpan | Pendidik tanpa mapel → `422 subject_id.required`; Tenaga Kependidikan dengan mapel → `422 subject_id.prohibited`; form menyembunyikan mapel untuk non-guru | **PENDING** |
| 6 | Create valid + detail | Isi data valid (NIK 16 digit unik, TMT `2026-01-01`) → **Save** | `201`; toast sukses; detail terbuka; **Record Version 1**; baris muncul di daftar | **PENDING** |
| 7 | Edit + kenaikan versi | Edit baris → ubah nama → **Save Changes** → cek Tinker | Toast sukses; `version` naik tepat `+1`; nama baru tersimpan | **PENDING** |
| 8 | Konflik 409 + Reload Latest | Buka form edit record sama di **dua tab** → simpan Tab B → ubah dan simpan Tab A | Tab A menampilkan konflik (English) dengan versi server terbaru; **Reload Latest** memuat data terbaru; simpan berikutnya berhasil | **PENDING** |
| 9 | Arsip wajib alasan | Klik aksi **Archive** → kosongkan alasan → **Archive**; lalu isi `UAT browser archive test` → **Archive** | Tanpa alasan → pesan wajib; dengan alasan → record hilang dari daftar aktif; cek Tinker: `deleted_at` & `archive_reason` terisi, `version` naik, **`exit_date` tidak berubah** | **PENDING** |
| 10 | Daftar arsip & restore | Klik **Archived** → **Restore** pada record uji | Daftar arsip hanya tampil untuk akun `manage`; restore sukses; `archive_reason` menjadi `null`; record kembali ke daftar aktif dengan versi naik | **PENDING** |
| 11 | Privasi daftar vs detail | Sebagai akun `employees.view` **tanpa** `manage`: buka daftar, lalu detail/API `GET /api/employees/{id}` | Daftar tanpa NIK/NPWP/KK/kontak/nilai ijazah; detail juga **tanpa** field sensitif dan tanpa `education` | **PENDING** |
| 12 | Privasi detail untuk manage | Sebagai akun `manage`: buka detail record | Field sensitif (NIK, NPWP, KK, ibu kandung, pasangan, kontak, `education`) tampil sesuai izin | **PENDING** |
| 13 | Kontrol izin view-only | Sebagai akun view-only: periksa tombol/menu | **New**, **Archived**, **Edit**, **Archive**, **Link/Unlink**, **Export** tidak tampil; akses paksa ke endpoint mutasi tetap `403` | **PENDING** |
| 14 | Ekspor terfilter | Sebagai akun `view`+`export`: terapkan filter (mis. status), klik **Export**; lalu sebagai akun tanpa `employees.export` | CSV terunduh mengikuti filter, 10 kolom (`Name` … `Archived At`), tanpa NIK/NPWP; akun tanpa izin ekspor mendapat `403` | **PENDING** |
| 15 | Taut akun | Pada detail pegawai tanpa akun → **Link Account** → cari akun aktif → pilih → **Link** | Kandidat hanya akun aktif yang belum tertaut; link sukses; `user_id` & `user_id_resolved_at` terisi; role akun **tidak** berubah; taut ulang/akun nonaktif → `422` | **PENDING** |
| 16 | Lepas akun | **Unlink Account** → konfirmasi | `user_id` kosong; `account_unlinked_at` terisi; profil tidak otomatis tertaut kembali lewat NIK legacy | **PENDING** |
| 17 | Konkurensi akun | Buka dialog link di dua tab → link di Tab B → link di Tab A | Tab A menerima konflik versi; setelah reload, aksi diulang mengikuti state terbaru | **PENDING** |
| 18 | Command backfill tanpa PII | Jalankan `& $php artisan app:employee-name-backfill --dry-run` | Tabel hitungan agregat tampil; **tidak** ada nama, NIK, atau email; tidak ada data tertulis pada dry-run | **PENDING** |

---

## 3. Pemeriksaan Database Pendukung (read-only)

```powershell
& $mysql -u root <database-review> -e "SHOW COLUMNS FROM employees WHERE Field IN ('name','nik','archive_reason');"
& $mysql -u root <database-review> -e "SHOW INDEX FROM employees WHERE Column_name IN ('name','nik','employee_type_id','employee_status_id');"
```
Diharapkan: `name` varchar(100) NULL, `nik` varchar(16) NULL, `archive_reason` varchar(255) NULL; index `employees_nik_unique` (`Non_unique = 0`), serta index `name`, `employee_type_id`, `employee_status_id`.

---

## 4. Pemulihan Aman Setelah Review

- Gunakan penanda `UAT Browser ...` dan NIK sintetis; jangan memakai data pegawai riil.
- Bersihkan record uji dengan **arsip** melalui UI, bukan `forceDelete`/hard delete.
- Jangan menjalankan `migrate:fresh`, reset database, atau penghapusan massal.
- Jangan me-restart/hentikan server milik pemilik tanpa instruksi.
- Untuk inspeksi, gunakan Tinker read-only (`first()`, `pluck()`, `toArray()`).

---

## 5. Form Konfirmasi Persetujuan Pemilik

*(Diisi hanya setelah pemilik menyatakan hasil review; agen tidak boleh mengisi lebih awal.)*

Status: **PENDING**.

- [ ] Seluruh checklist #1–#18 disetujui (APPROVED).
- Tanggal pelaksanaan WIB: —
- Catatan / feedback pemilik: —

**Provenansi bukti:** status akan dicatat hanya dari pernyataan pemilik. Persetujuan 04b tidak mencakup penerapan migration ke produksi, deploy, merge, force-push, perubahan dependency, atau penutupan risiko keamanan terbuka.
