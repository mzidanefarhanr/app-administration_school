# Panduan Review Manual Subfase 03b — CRUD Siswa, Optimistic Locking, dan Form Validasi

- Tanggal WIB: 2026-10-21
- Subfase: **03b — CRUD dan Form Siswa**
- Branch: `main`
- Backend baseline: commit `e77f335` (di-push)
- Status implementasi: **Backend + UI IMPLEMENTED; verifikasi otomatis & browser UAT agen PASS**
- Status keseluruhan 03b: **DITERIMA PEMILIK**
- Status checklist pemilik: **APPROVED** (#1–#13, 2026-10-21 WIB, baseline `cbd25bd`)
- Laporan teknis: `reports/feature-update/PHASE-03B-STUDENT-CRUD.md`

> **Penting:** Checklist ini menguji UI nyata di `Student.vue` (bukan hanya Tinker). Semua langkah memakai akun dan data sintetis UAT. Database operasional `backdata_administrations` **dilarang** disentuh.

---

## 1. Persiapan & Preflight Guard

Server UAT yang sudah berjalan (jangan hentikan proses lain):
- API UAT: `http://127.0.0.1:8001` (`php artisan serve --host=127.0.0.1 --port=8001 --env=uat`)
- SPA UAT: `http://127.0.0.1:4001` (`npm run dev:uat`)

Preflight database (wajib, sebelum Tinker):
```bash
# dari laravel-api
php artisan tinker --env=uat
```
```php
DB::connection()->getDatabaseName();
```
**Wajib** mengembalikan `school_access_uat`. Jika bukan, ketik `exit` dan batalkan.

Akun sintetis:
- `uat.operator` — memiliki `students.view`, `students.manage`, `students.export`.
- `uat.kepsek` — hanya `students.view`.
- Kata sandi disetel pemilik saat seeding (`UAT_SEED_PASSWORD`); gunakan kata sandi UAT yang berlaku.

**ID dinamis:** jangan menulis ID di panduan ini. Ambil ID/versi record sintetis lewat Tinker:
```php
$s = App\Models\Student::withTrashed()->where('nis', 'UAT-BROWSER-001')->first();
[$s?->id, $s?->version, $s?->trashed()];
```
Atau, untuk baris yang sedang diuji, gunakan ID yang tampil pada dialog detail (Record Version) dan endpoint `GET /api/students/{id}`.

**Penanda fixture sintetis:** gunakan awalan `UAT-BROWSER-` pada NIS dan nama `UAT Browser ...`.

---

## 2. Checklist Pemeriksaan Manual

Status: **APPROVED** = pemilik telah memverifikasi dan menyetujui hasil uji manual.

| # | Skenario | Langkah | Hasil yang Diharapkan | Status Pemilik |
|---|---|---|---|---|
| 1 | Akses halaman & izin baca | Login `uat.operator` → buka `/public/students` → pilih tahun ajaran `2026/2027` | Halaman tampil; tombol **New Student** dan **Archived** tampil untuk operator | **APPROVED** |
| 2 | Create + inline 422 | Klik **New Student** → isi Full Name `UAT Browser Create` → isi NIK `123` → klik **Save Student** | Dialog tetap terbuka; pesan `NIK must be exactly 16 digits.` tampil **di bawah field NIK**; input tidak hilang | **APPROVED** |
| 3 | Create valid + detail | Perbaiki NIK menjadi 16 digit unik (mis. `9876543210123456`) → isi NIS `UAT-BROWSER-001` → **Save Student** | Toast `Student Created`; dialog **Student Detail** terbuka; Record Version `1`; NIK tetap string 16 digit (leading zero tidak hilang bila dipakai) | **APPROVED** |
| 4 | Edit + kenaikan versi | Catat ID/versi via Tinker (lihat §1) → pada baris siswa yang dapat diedit klik ikon pensil → ubah nama → **Save Changes** → cek ulang Tinker | Toast `Student Updated`; `version` naik tepat `+1`; perubahan nama tersimpan | **APPROVED** |
| 5 | Konflik 409 + Reload Latest | Buka form Edit siswa yang sama di **dua tab** → simpan di Tab B → di Tab A ubah nama lalu **Save Changes** | Tab A menampilkan **Data Conflict Detected** (English); input Tab A tetap; tombol **Reload Latest** memuat data server terbaru; simpan berikutnya berhasil | **APPROVED** |
| 6 | Arsip wajib alasan | Pada baris siswa klik ikon arsip → kosongkan alasan → **Archive Student** → isi alasan `UAT browser archive test` → **Archive Student** | Tanpa alasan: pesan `An administrative archiving reason is required.`; dengan alasan: toast `Student Archived`, baris keluar dari daftar tahun ajaran | **APPROVED** |
| 7 | Semantik arsip administratif | Setelah #6, jalankan Tinker: ambil siswa via NIS penanda dan membership-nya | `students.deleted_at` terisi; `student_rombels.archived_at` terisi; `archive_reason` terisi; `student_status_id` **tidak** berubah; `latest_student_exit_date` **null** | **APPROVED** |
| 8 | Daftar siswa terarsip | Klik **Archived** | Dialog **Archived Students** menampilkan nama, NIK, NIS, dan alasan `UAT browser archive test` | **APPROVED** |
| 9 | Restore + rombel tetap non-aktif | Klik **Restore** pada baris arsip → cek Tinker | Toast `Student Restored`; daftar arsip kosong; siswa tidak trashed; `version` naik; `archived_at` membership **tetap terisi** (rombel lama tidak aktif) | **APPROVED** |
| 10 | Restore konflik identitas (422) | Opsional/Tinker: siapkan siswa terarsip dengan NIK yang sama dengan siswa aktif lain → panggil restore dengan versi benar | HTTP 422 dengan pesan English (`Cannot restore: ...`); siswa tetap terarsip | **APPROVED** |
| 11 | Kontrol izin view-only | Logout → login `uat.kepsek` → buka `/public/students` | **New Student**, **Archived**, tombol Edit, dan tombol Archive **tidak tampil**; hanya ikon View Detail; akses langsung ke mutasi tetap ditolak backend (403) | **APPROVED** |
| 12 | Audit tanpa data sensitif | Setelah update/arsip, cek Tinker `activities` untuk `table_name='students'` dan `record_id` siswa penanda | Audit memuat nama/NIK/NIS/versi; **tidak** memuat `history_illness`, `father_income`, `father_hp_num`, atau teks `archive_reason` | **APPROVED** |
| 13 | Ekspor existing tetap kompatibel | Login operator → `/public/students` → klik **Export** | CSV terunduh; data siswa tetap memuat identitas via `student_id` + fallback `student_nik`; tidak ada error | **APPROVED** |

---

## 3. Pemulihan Aman Setelah UAT

- Data uji memakai penanda `UAT-BROWSER-` dan nama `UAT Browser ...`.
- Untuk membersihkan record uji, **arsipkan** lewat UI (bukan hard delete / `forceDelete`). Record terarsip tetap menyimpan identitas unik sesuai DEC-21.
- Jangan menjalankan `migrate:fresh`, reset database, atau penghapusan massal pada `school_access_uat`.
- Bila perlu memeriksa nilai domain tanpa mengubah data, gunakan Tinker read-only (`first()`, `pluck()`, `toArray()`), bukan `update()`/`delete()`.
- Bila harus memulihkan data sintetis secara transaksional untuk pemeriksaan tambahan, gunakan `DB::beginTransaction()` … `DB::rollBack()` di `try ... finally`. Mutasi UI nyata tidak dapat di-rollback dengan cara ini.

---

## 4. Bukti Otomatis Agen (bukan pengganti persetujuan pemilik)

- Suite Student: **55 passed (228 assertions)**.
- Regresi backend penuh: **209 passed (946 assertions)**.
- Frontend unit: **15 passed**; build **PASS**.
- Konkurensi update dengan expected version sama: tepat satu menang, satu 409, versi naik sekali, audit konsisten (MariaDB disposable).
- Arsip vs aktivasi rombel: siswa terarsip berakhir dengan **0** keanggotaan aktif (MariaDB disposable).
- Browser UAT agen (Playwright MCP): create → detail → edit → 409 → reload → archive → daftar arsip → restore → kontrol izin. Rincian di `PHASE-03B-STUDENT-CRUD.md` §4.

---

## 5. Penerimaan Pemilik

Pemilik menyatakan pengujian manual **#1–#13 berhasil dan APPROVED** pada **21 Oktober 2026 WIB** mengacu baseline **`cbd25bd`**. Status Subfase 03b menjadi **DITERIMA PEMILIK**.

Batasan yang tetap berlaku:
- Penerimaan manual pemilik ini **terpisah** dari bukti otomatis/browser agen (§4).
- Penerimaan **bukan** izin deployment, **bukan** izin akses data operasional, dan bukan otorisasi merge/force-push/scheduler.
- Fase 03 tetap **PARTIAL**: Subfase 03c belum selesai.
- Penerimaan 03a (checklist #1–#11, baseline `37f7d79`) tetap dipertahankan.
