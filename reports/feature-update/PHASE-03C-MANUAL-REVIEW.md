# Panduan Review Manual Subfase 03c — Daftar, Rekap, dan Ekspor Siswa (Server-side)

- Tanggal WIB: 2026-10-21
- Subfase: **03c — Daftar dan Rekap Siswa**
- Branch: `main`
- Baseline laporan: `0a15325`; keputusan `4fa20c6` (DEC-22)
- Status implementasi: **IMPLEMENTED & VERIFIED (AGEN)**
- Status keseluruhan 03c: **MANUAL ACCEPTANCE PENDING**
- Laporan teknis: `reports/feature-update/PHASE-03C-STUDENT-DIRECTORY.md`
- Rencana: `reports/feature-update/PHASE-03C-PLAN.md`

> Checklist menguji UI nyata `Student.vue`. Database UAT disposable `school_access_uat`; **dilarang** menyentuh `backdata_administrations`.

---

## 1. Preparasi

- API UAT `http://127.0.0.1:8001`; SPA UAT `http://127.0.0.1:4001`.
- Akun sintetis: `uat.operator` (view+manage+export), `uat.kepsek` (view+export, tanpa manage).
- Kata sandi UAT berlaku; bila lupa, reset hanya akun sintetis lewat Tinker (`--env=uat`), jangan tulis kata sandi ke dokumen terlacak.
- Preflight: `php artisan tinker --env=uat` → `DB::connection()->getDatabaseName();` **wajib** `school_access_uat`.

---

## Checklist berorientasi fitur (nomor manual)

| # | Skenario | Langkah | Harapan |
|---|---|---|---|
| 1 | Guest ditolak | Buka `/api/students?school_year_id=1` tanpa sesi (tab anonim) | `401` |
| 2 | View-only tidak bisa mutasi | Login `uat.kepsek`; coba POST `/api/students` (atau tombol) | Tombol New/Archived/Edit/Archive tersembunyi; POST `403` |
| 3 | Daftar memuat | `/public/students` sebagai `uat.operator` | Tabel + Total/Placed/Unplaced + paginasi tampil |
| 4 | Paginasi default | Tahun dengan >20 siswa | `Showing 1 to 20 of N`; pilihan 10/20/50/100 bekerja; `per_page=101`/`25` → `422` |
| 5 | Sort stabil | Klik header Name (asc→desc) lalu NIS | Urutan berubah; nilai sama diurut oleh id (tidak berkedip) |
| 6 | Pencarian | Cari nama / NIS / NISN | Hasil sesuai; NIK **tidak** ikut dicari |
| 7 | Filter All/Placed/Unplaced | Pilih tiap placement; saat `Unplaced` pilih Rombel | Angka Total/Placed/Unplaced konsisten; Rombel **disabled** pada `Unplaced`; `Unplaced` = tanpa keanggotaan aktif tahun terpilih |
| 8 | Rombel tidak boleh dikombinasikan | `placement=unplaced&rombel=<id tahun>` | `422` (placement) |
| 9 | Siswa baru tanpa rombel | Create siswa tanpa rombel → tutup dialog → refresh browser | Muncul pada All & Unplaced |
| 10 | Atribut minimum | Inspeksi respons `GET /api/students` (DevTools) | Hanya `id, version, name, nis, nisn, gender, rombel, placement`; tanpa NIK/birthdate/alamat/telepon/medis/penghasilan/archive_reason |
| 11 | Rekap server & lintas halaman | Catat Total/Placed/Unplaced; pindah halaman | Angka rekap **tidak berubah** karena pindah halaman |
| 12 | Detail/Edit tetap 03b | View, lalu Edit (uji NISN invalid → valid) | Dialog detail; 422 inline; simpan sukses → daftar refresh |
| 13 | Archive/restore tetap 03b | Arsip dengan alasan (dialog) lalu Restore dari dialog Archived | Arsip hilang dari daftar; restore mengembalikan; rombel lama tetap nonaktif |
| 14 | Halaman terakhir jadi kosong | Isi halaman terakhir dengan 1 siswa → arsipkan | Aplikasi mundur ke halaman sebelumnya, bukan menampilkan 0 |
| 15 | Ekspor mengikuti filter | Set filter (placement/pencarian/rombel) → Export | CSV = seluruh hasil filter (bukan hanya halaman aktif); `unplaced` kolom rombel kosong; leading zero utuh |
| 16 | Ekspor tanpa izin | `uat.kepsek` tanpa `students.export` (bila dicabut admin) | `403` |
| 17 | Batas ekspor | Turunkan `students.export_max_rows`; ekspor melebihi batas | `422` (bukan dipotong) |
| 18 | Error + Retry | Putuskan jaringan pada request daftar (DevTools throttle/offline) lalu pilih filter | Pesan error + tombol Retry; Retry memuat ulang |
| 19 | Empty state | Cari string tanpa hasil | "No students match the current filters." |
| 20 | Guard respons usang | Perlambat satu request daftar sementara ganti filter | Hasil filter terbaru tidak ditimpa respons lama (list + rekap + loading) |
| 21 | Daftar arsip terlindungi | `uat.kepsek` → `GET /api/students?only_archived=1` | `403` (hanya `students.manage`) |
| 22 | Supaya 03b tetap aman | `uat.operator` → update/archive/restore dari baris daftar | Sukses, memakai `version` dari daftar; konflik versi → `409` |
| 23 | Penempatan rombel | Cari aksi "tempatkan ke rombel" di UI | **Tidak ada** (di luar scope 03c) |

---

## 2. Hasil agen (hasil aktual)

Seluruh #1–#23 diuji/diverifikasi agen sesuai laporan teknis §4–§5: 229 test backend PASS, 15 unit frontend PASS, build PASS, Pint PASS, dan browser UAT PASS (#1–#20 kecuali #22 konflik 409 yang sudah ditutup test 03b/03c automated; #23 terkonfirmasi tidak dibangun).

## 3. Persetujuan pemilik

Status: **PENDING**. Isi setelah review:

- [ ] Seluruh nomor disetujui.
- Tanggal WIB:
- Catatan/pengecualian:
