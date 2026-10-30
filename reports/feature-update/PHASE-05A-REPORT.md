# Laporan Subfase 05a — Impor Siswa CSV

Tanggal WIB: 2026-10-30. Branch: `main`. HEAD sebelum paket 05a: `b093db7` (dokumentasi penerimaan 04b); implementasi 05a dikomit sebagai **`58828a6`** untuk review manual di GitHub; commit dokumentasi ini menyusul. Push bersama commit dokumentasi; review pemilik tetap PENDING.
Keputusan pemilik: **DEC-25** (APPROVED 2026-10-30 WIB; D1–D8).
Status teknis: **IMPLEMENTED — agent-verified (2026-10-30)**. Status review pemilik: **PENDING**.
Laporan rencana: `reports/feature-update/PHASE-05-PLAN.md`. Checklist manual: `reports/feature-update/PHASE-05A-MANUAL-REVIEW.md`.

## 1. Outcome dan cakupan

Subfase 05a menambahkan impor siswa CSV end-to-end tanpa dependency baru:

- Unggah CSV UTF-8 (maks 5 MB / 2.000 baris) → batch `ready`/`invalid` + preview tanpa mutasi data.
- Commit **all-or-nothing** dalam satu transaksi; baris error memblokir seluruh commit.
- Mode `insert` dan `update`; update mencocokkan **NIS lebih dulu, lalu NIK**, tidak pernah nama.
- Laporan error CSV privat (`storage/app/imports/{token}/errors.csv`) diunduh lewat endpoint berizin; sel formula dinetralkan.
- Izin baru `students.import` hanya untuk `admin-sistem` dan `operator-sekolah`; template cukup `students.view`.
- UI wizard English di halaman Students dengan tombol Import mengikuti kemampuan aktual.

Tidak dikerjakan pada 05a: impor pegawai, pilihan kolom ekspor, rekap sekolah, XLSX, job antrean, dan pembersihan otomatis retensi 30 hari (manual sesuai D6).

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File/endpoint | Perubahan | Permission/schema/payload |
|---|---|---|
| `app/Services/StudentImportService.php` | Parser/validator/preview/commit/error report; template v1 English; deteksi `,`/`;` dan BOM; normalisasi tanggal `Y-m-d`/`d/m/Y` | Menulis `import_batches`; menyimpan source/errors di disk privat; transaksi + `lockForUpdate` |
| `app/Http/Controllers/ImportController.php` | 5 aksi: template, upload, show, commit, errors | Scope batch: `domain=students` dan `created_by` = aktor; selain itu 404 |
| `app/Http/Requests/StoreStudentImportRequest.php` | Validasi unggahan | `file` wajib CSV/TXT ≤5 MB; `mode` ∈ insert/update; izin `students.import` |
| `app/Models/ImportBatch.php`, `app/Exceptions/ImportBatchException.php` | Model batch dan error 409 berpayload | Status invalid/ready/committed; route key `token` |
| `app/Support/StudentRules.php` | Aturan siswa tunggal untuk form + impor | Menggantikan duplikasi di Store/UpdateStudentRequest |
| `app/Support/AbilityCatalog.php` + migration `2029_07_27_000002_add_students_import_ability.php` | Kemampuan `students.import` | Diberikan ke `admin-sistem`, `operator-sekolah` |
| Migration `2029_07_27_000001_create_import_batches_table.php` | Tabel batch impor | `token` unik, `file_hash`, hitungan baris, `errors_path`, `committed_at`, index |
| `config/imports.php` | Batas dan retensi | 2.000 baris, 5.120 KB, TTL batch 120 menit, retention 30 hari, preview 50, error 200 |
| `routes/api.php` | 5 route impor | Template: `students.view`; sisanya `students.import` |
| `scripts/phase05-uat.php` | Launcher UAT terpisah | Runtime `.uat-phase05`, 18023/14023, cookie `school_phase05_session`; `init`/`prepare`/`status` |
| `tests/Feature/Import/StudentImportTest.php` | 18 tes / 97 assertions | Preview, header, leading zero, all-or-nothing, idempotensi, rollback, update, izin, batas, formula, TTL/hash |
| `vue-app/src/views/pages/public/StudentImportDialog.vue` | Wizard English | Unggah/preview/commit/error download; 409 menandai batch invalid |
| `vue-app/src/views/pages/public/studentImportParams.mjs` + `vue-app/tests/student-import-params.test.mjs` | Validasi klien + 3 unit test | `.csv`, kosong, >5 MB; mode fallback insert |
| `vue-app/src/views/pages/public/Student.vue` | Tombol Import + integrasi dialog + refresh daftar | Tombol hanya bila `students.import`; +18 baris |

Kontrak endpoint (detail di `docs/API-CONTRACTS.md`):

- `GET /api/imports/students/template` (`students.view`) → CSV lampiran.
- `POST /api/imports/students` (`students.import`) → `201` `{token, domain, mode, status, counts, preview, errors}`.
- `GET /api/imports/{batch}`, `POST /api/imports/{batch}/commit`, `GET /api/imports/{batch}/errors` (`students.import`, hanya pembuat batch).
- Commit idempoten untuk batch `committed`; `409` untuk batch invalid/kedaluwarsa/hash berubah/validasi ulang gagal.

## 3. Evidence

| ID | Metode / command | Fixture / peran | Expected | Actual | Status |
|---|---|---|---|---|---|
| E1 | `php artisan test tests/Feature/Import` | SQLite in-memory + `Storage::fake` | 18 tes PASS | 18 tes / 97 assertions PASS (bagian dari E2) | PASS |
| E2 | `php artisan test tests/Feature/Import tests/Feature/Student tests/Feature/Employee` | SQLite in-memory | Regresi impor/siswa/pegawai PASS | **170 passed / 1097 assertions, 13 skipped, 95,04 s**; skip = tes integrasi MariaDB tanpa password (bukan kegagalan) | PASS |
| E3 | `./vendor/bin/pint --test <16 file berubah>` | — | Gaya bersih | PASS 16 file | PASS |
| E4 | `node --test tests/student-import-params.test.mjs` | Node 24.18.0 | 3 tes PASS | 3 PASS, 0 fail | PASS |
| E5 | `npm run build` | Vite 6 | Build sukses | PASS 26,02 s; warning chunk >500 kB sudah ada sebelumnya | PASS |
| E6 | `php -l scripts/phase05-uat.php`; `php scripts/phase05-uat.php status` | `.uat-phase05` SQLite | Guard isolasi + data sintetis | `[ISOLATION GUARD PASS]`; Students 406; Employees 200 (Teachers 100/Staff 100); ability `students.import` present pada `admin-sistem`,`operator-sekolah`; 4 batch | PASS |
| E7 | Browser operator: unggah file valid (NIS 40001–40003) | `uat.operator` | Preview 3 valid tanpa mutasi; commit menambah 3 | Preview 3/0; `POST /api/imports/students` 201; commit 200; toast "3 student(s) imported."; Total 406→409; GET daftar 200 | PASS |
| E8 | Browser operator: unggah file campuran (NIS 41001–41003) | `uat.operator` | 1 valid/2 invalid; commit diblokir; laporan error dapat diunduh | Status "Needs fixes", 1 valid/2 invalid, tombol Commit absen; error rows NIK 16 digit & Gender tak dikenal; GET errors 200 `text/csv`; Total tetap 409 | PASS |
| E9 | Browser operator: mode update (NIS 10002) | `uat.operator` | Baris cocok via NIS, aksi Update, perubahan terbatas | Preview `Update` NIS 10002; commit 200; toast "1 student(s) imported."; Gender Phase05 Student 002 Female→Male; Total tetap 409 | PASS |
| E10 | Browser operator: Download Template | `uat.operator` | CSV 200 | GET `/api/imports/students/template` 200 `text/csv` | PASS |
| E11 | `mcp__browser-use__list_console_messages` (error/warn) | Sesi UAT | Tidak ada error baru | Tidak ada pesan | PASS |
| E12 | Isolasi DB/storage | — | Tidak menyentuh target terlarang | Hanya `.uat-phase05/database.sqlite` + storage runtime; MariaDB/legacy/main **tidak diakses** | PASS (scoped) |

Catatan metode: browser tool tidak menyediakan pointer click pada sesi ini (`NATIVE_BROWSER_VIEWPORT_UNAVAILABLE`, viewport 0x0); interaksi memakai DOM click + `DataTransfer` file input dan verifikasi snapshot/network. Tidak ada screenshot; ini bukti DOM/HTTP, bukan bukti visual.

Setelah verifikasi, contoh CSV di launcher dan `.uat-phase05/samples/` **dikunci ulang ke identitas yang belum terpakai** agar tetap dapat dijalankan pemilik: valid NIS `42001–42003`, campuran NIS `43001–43003`, update menargetkan NIS `40001` (hasil impor valid) dan belum dijalankan. Bukti E7–E9 memakai nilai sebelumnya (40001–40003/41001–41003/10002) yang sekarang sudah ada di DB UAT.

**NOT RUN:** tes integrasi MariaDB 05a (tidak diperlukan untuk fitur ini dan target MariaDB tetap dikecualikan); retensi/pembersihan otomatis 30 hari (D6 manual); impor XLSX; impor pegawai; rekap sekolah.

## 4. UAT manual

Checklist `PHASE-05A-MANUAL-REVIEW.md` disiapkan dengan status **PENDING** untuk 12 skenario. Runtime: SPA `http://127.0.0.1:14023`, API `http://127.0.0.1:18023`, akun `uat.operator` (dan peran view-only `uat.kepsek` untuk skenario izin). Password hanya di runtime terabaikan `.uat-phase05/account-password.txt`; tidak dicetak di laporan/Git.

## 5. Risiko, blocker dan keputusan

- **All-or-nothing** sesuai D1: commit ditolak selama ada baris error; tidak ada partial success.
- **Identitas string**: NIK/NIS/NISN leading zero dipertahankan; NIK 16 digit & NISN 10 digit divalidasi lewat `StudentRules` yang sama dengan form.
- **Update aman**: hanya baris yang cocok NIS/NIK; nama tidak dipakai; tanpa perubahan → error, record arsip tidak ditimpa; versi dinaikkan +1.
- **Keamanan file**: source/errors di disk privat di luar web root; endpoint memeriksa pemilik batch (404 untuk batch pengguna lain); formula dinetralkan.
- **Retensi**: belum ada scheduler; pembersihan 30 hari masih manual (D6) sehingga file privat dapat menumpuk bila operator tidak membersihkan.
- **Batas 2.000 baris/5 MB**: ditolak 422, tidak dipotong.
- Insiden sesi: launcher `prepare` pertama gagal karena `EmployeeSeeder::guardEnvironment()` hanya mengizinkan `.uat-phase04b`; guard diperluas eksplisit ke `.uat-phase04b`/`.uat-phase05` (tidak melonggarkan ke path lain). Contoh mixed awal memakai identitas yang sama dengan file valid sehingga setelah commit valid menghasilkan error duplikat; contoh diperbaiki agar mandiri (NIK/NIS/NISN `053...`/`3000x`) dan ditambah contoh update.

## 6. Sinkronisasi dokumen

Diperbarui: `CHANGELOG.md`, `docs/API-CONTRACTS.md`, `docs/DATABASE.md`, `docs/ACCESS-CONTROL.md`, `docs/REQUIREMENTS.md` (FR-06), `docs/TESTING.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-05-IMPORT-EXPORT.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, laporan ini dan checklist manual. `PROJECT_CONTEXT.md` mendapat addendum 05a.

Commit/push dilakukan agar pemilik dapat meninjau implementasi langsung di GitHub sebelum cek manual; **commit/push bukan penerimaan manual**. Tidak ada merge/deploy/force-push, perubahan `.env` utama, dependency, service, DB terlarang, atau migrasi database operasional.

## 7. Langkah berikutnya

1. Pemilik menguji 12 skenario pada UAT `.uat-phase05` dan menyatakan hasil + persetujuan.
2. Setelah persetujuan: commit+push paket 05a (implementasi; dokumentasi/penerimaan pada commit terpisah sesuai aturan repo), lalu perbarui status ROADMAP/INDEX/HANDOFF dengan SHA aktual.
3. Subfase 05b (impor pegawai + pilihan kolom ekspor) menunggu instruksi pemilik; rekap sekolah menunggu contoh format (DEC-12 masih PROPOSED).
