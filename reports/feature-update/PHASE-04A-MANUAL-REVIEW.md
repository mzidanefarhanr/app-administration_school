# Panduan Review Manual Subfase 04a — Fondasi Schema Profil Pegawai dan Hubungan Akun

- Tanggal WIB: 2026-10-26; re-verifikasi: 2026-10-29
- Subfase: **04a — Fondasi Schema Profil Pegawai dan Hubungan Akun** (Fase 04: Guru dan Pegawai)
- Branch: `main`
- Baseline keputusan: DEC-04 (`7cd6dc8`)
- Status implementasi: **IMPLEMENTED & RE-VERIFIED (AGEN, 2026-10-29)**
- Status penerimaan manual pemilik: **APPROVED OLEH PEMILIK (2026-10-29 WIB)** — sumber bukti: pernyataan pemilik; agen tidak menjalankan ulang pengujian.
- Laporan teknis: `reports/feature-update/PHASE-04A-EMPLOYEE-SCHEMA.md`

> **Pemberitahuan Cakupan Fondasi:** Subfase 04a berfokus strictly pada **fondasi schema database, model relasi, dan kebijakan hubungan akun**. Subfase ini **belum** menyediakan antarmuka pengguna (UI) atau form input di browser (menu Teachers dan Employees di UI masih berupa placeholder yang akan diaktifkan pada Subfase **04b**). Panduan review manual ini memandu peninjauan fondasi backend melalui CLI/Artisan tanpa menyentuh database operasional `backdata_administrations`.

---

## 1. Preparasi Peninjauan

Peninjauan dapat dilakukan pada database disposable `school_04a_integration` menggunakan CLI PHP 8.2 operasional:
```powershell
$php = "C:\xampp\php82-operational\php.exe"
$mysql = "C:\xampp\mysql\bin\mysql.exe"
```

---

## 2. Checklist Review Fondasi Subfase 04a

| # | Aspek yang Ditinjau | Perintah / Langkah Peninjauan | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| 1 | Struktur Schema Kolom Additive | Jalankan di MariaDB CLI:<br>`& $mysql -u root school_04a_integration -e "DESCRIBE employees;"` | Kolom `user_id` bertipe `bigint(20) unsigned` (`Null: YES`).<br>Kolom `user_id_resolved_at` bertipe `timestamp` (`Null: YES`).<br>Kolom `account_unlinked_at` bertipe `timestamp` (`Null: YES`).<br>Kolom `version` bertipe `int(10) unsigned` (`Default: 1`). | **APPROVED** |
| 2 | Pelonggaran Nullability `user_nik` & `subject_id` | Periksa kolom `user_nik` dan `subject_id` pada output DESCRIBE. | Kolom `user_nik` berstatus `Null: YES` (bukan NOT NULL).<br>Kolom `subject_id` berstatus `Null: YES` (bukan NOT NULL). | **APPROVED** |
| 3 | Unique Constraint pada `user_id` | Jalankan di MariaDB CLI:<br>`& $mysql -u root school_04a_integration -e "SHOW INDEXES FROM employees WHERE Column_name = 'user_id';"` | Indeks `employees_user_id_unique` ada dengan `Non_unique: 0`. | **APPROVED** |
| 4 | Foreign Key Constraints | Jalankan query ke information_schema:<br>`& $mysql -u root school_04a_integration -e "SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = 'employees' AND CONSTRAINT_NAME LIKE '%user_id%';"` | Constraint mengarah ke tabel `users` dengan `DELETE_RULE: SET NULL`. | **APPROVED** |
| 5 | Model Profil Independen (Tinker) | Buka Artisan Tinker pada SQLite test:<br>`& $php artisan tinker`<br>`$emp = \App\Models\Employee::create(['employee_status_id' => 1, 'employee_type_id' => 1, 'employment_id' => 1, 'religion_id' => 1, 'district_id' => 1, 'marital_status_id' => 1, 'gender_id' => 1, 'tmt_employee' => '2026-01-01']);`<br>`$emp->version;` | Objek Employee berhasil disimpan tanpa `user_id` dan tanpa `user_nik`. Nilai `version` otomatis bernilai `1`. | **APPROVED** |
| 6 | Profil Non-Guru Tanpa Mapel (Tinker) | Jalankan di Tinker:<br>`$emp->subject_id;`<br>`$emp->subject;` | Mengembalikan `null` tanpa memicu exception database. | **APPROVED** |
| 7 | Typo Fix `religion()` dan Alias `relogion()` | Jalankan di Tinker:<br>`$emp->religion;`<br>`$emp->relogion;` | Keduanya mengembalikan instance relasi agama yang sama secara identik. | **APPROVED** |
| 8 | Allowlist Privasi Audit Trail | Periksa properti model di Tinker:<br>`(new \App\Models\Employee())->getAuditableAttributes();` | Mengembalikan array berisi atribut operasional, tanpa NUPTK, nama ibu kandung, pasangan, nomor KK, NPWP, kontak, atau nilai ijazah. | **APPROVED** |
| 9 | Penegakan Otorisasi Policy | Uji kemampuan policy di Tinker:<br>`$user = \App\Models\User::first();`<br>`(new \App\Policies\EmployeePolicy())->forceDelete($user, $emp);` | Mengembalikan `false` (penghapusan permanen ditolak). | **APPROVED** |
| 10 | Layanan Backfill Idempotent | Jalankan uji otomatis backfill:<br>`& $php artisan test tests/Feature/Employee/EmployeeSchemaAndAccountRelationTest.php --filter="backfill"` | Seluruh skenario backfill (unik, tidak valid, soft-deleted, non-legacy, idempotent, bebas PII) PASS. | **APPROVED** |
| 11 | Penanda Baris Legacy | Periksa kolom dan isi penanda:<br>`& $mysql -u root school_04a_integration -e "DESCRIBE employees;"`<br>`& $mysql -u root school_04a_integration -e "SELECT COUNT(*) AS marked FROM employees WHERE legacy_profile_at IS NOT NULL;"` | Kolom `legacy_profile_at` ada (`Null: YES`) dan hanya baris pra-04a yang bertanda. Profil baru tidak memiliki penanda. | **APPROVED** |
| 12 | Rollback Fail-Closed | Pada database uji dengan baris `subject_id` NULL atau `user_id` terhubung, jalankan rollback migration 04a. | Migration menolak rollback **sebelum** DDL dengan `RuntimeException`; kolom dan data tetap utuh (tidak ada rollback parsial). | **APPROVED** |
| 13 | Command Backfill Tanpa PII | Jalankan:<br>`& $php artisan app:employee-account-backfill --dry-run` | Tabel hitungan agregat tampil; tidak ada NIK, nama, atau email pada keluaran; tidak ada data tertulis pada mode dry-run. | **APPROVED** |

---

## 3. Form Konfirmasi Persetujuan Pemilik

*(Diisi berdasarkan pernyataan pemilik pada sesi 2026-10-29)*

Status: **APPROVED**.

- [x] Seluruh checklist fondasi #1–#13 disetujui (APPROVED).
- Tanggal pelaksanaan WIB: 2026-10-29 (tanggal pernyataan pemilik; rincian jam tidak dicatat).
- Catatan / feedback pemilik: "Pengujian nomor 1-13 sudah berhasil dan saya approved."

**Provenansi bukti:** Sumber satu-satunya adalah pernyataan pemilik di atas. Pemilik menjalankan sendiri peninjauan checklist #1–#13; agen **tidak** menjalankan ulang perintah Tinker/MariaDB/tes untuk mencatat status ini, dan **tidak** menyimpulkan detail hasil per item di luar pernyataan tersebut. Persetujuan ini mencakup **fondasi 04a** (schema, model relasi, kebijakan hubungan akun, audit allowlist, policy, backfill) dan **bukan** penerimaan CRUD/UI 04b, bukan izin deploy ke produksi, bukan penerapan migration ke UAT, dan bukan penutupan risiko keamanan terbuka (dependency advisory, lifecycle Laravel/PHP, rotasi rahasia).
