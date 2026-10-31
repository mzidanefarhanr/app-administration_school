# Laporan Subfase 06a — Periode Akademik dan Rombel

Tanggal: implementasi dan verifikasi agen 2026-10-31 WIB. Branch: `main`. Baseline induk: `25875c9` (pencatatan DEC-29 APPROVED); paket implementasi+dokumentasi 06a belum memiliki SHA saat laporan ini ditulis — SHA aktual dicatat pada checkpoint berikutnya setelah commit+push.
Keputusan pemilik: **DEC-29** (APPROVED 2026-10-31 WIB; D1–D8, pernyataan "Saya setuju D1-D8 dilakukan.").
Status teknis: **IMPLEMENTED — agent-verified (2026-10-31)**. Status review pemilik: **PENDING — menunggu review manual checklist #1–#12**.
Rencana: `reports/feature-update/PHASE-06-PLAN.md` (§5). Checklist manual: `reports/feature-update/PHASE-06A-MANUAL-REVIEW.md`.

## 1. Outcome dan cakupan

Subfase 06a mengubah fondasi akademik dari scaffold menjadi alur yang berfungsi:

- **Periode akademik aktif eksplisit**: tabel singleton `academic_settings` id=1 menyimpan tahun dan semester aktif; migration tidak menebak nilai awal. Hanya `master.manage` yang dapat menetapkan periode; tahun locked atau soft-deleted dan semester tidak valid ditolak `422`. Nilai aktif dibaca halaman Students dan Classes dari server, bukan dari kalender.
- **Rentang tanggal tahun ajaran**: `start_date`/`end_date` nullable, divalidasi format dan urutannya; tidak diturunkan dari nama tahun.
- **Lock per tahun ajaran**: `locked_at`, `locked_by`, `lock_reason`; alasan wajib (maks. 255) saat lock/unlock; hanya `master.manage`; tahun aktif tidak dapat dikunci; tahun locked tidak dapat dijadikan aktif atau dihapus; lock/unlock diaudit. Tahun locked menolak create/update/delete kelas dan assign/transfer dengan `422`.
- **CRUD Classes berfungsi** (sebelumnya `authorize(): false` dan tanpa rules): nama wajib, tahun dan tingkat wajib, jurusan dan wali kelas opsional; wali kelas berasal dari **profil pegawai aktif bertipe `Pendidik`** (`class_teach_employee_id`); `class_teach_id` legacy dipertahankan. Nama unik per tahun ajaran secara case-insensitive; `expected_version` wajib untuk update/delete; `409` menyertakan `current_version` dan data terbaru; hapus ditolak bila ada riwayat keanggotaan (termasuk soft-deleted); tahun/tingkat/jurusan tidak dapat diubah bila sudah ada riwayat.
- **Penempatan 03d diperketat**: assign/transfer mengunci baris tahun ajaran terlebih dahulu di dalam transaksi dan menolak tahun locked/soft-deleted serta kelas soft-deleted; aturan single-active, versi siswa/membership, dan audit 03d tidak diubah.
- **UI English**: halaman School Years (periode aktif, tanggal, lock/unlock, guard hapus), halaman Classes (filter tahun, tag active/locked, CRUD, pencarian wali kelas remote, dialog konflik), halaman Students (tahun aktif dari server, banner periode/lock, tombol assign/transfer dinonaktifkan saat locked), grup menu **Academic**, dan route Classes memakai `students.view`.
- **UAT terisolasi** `.uat-phase06` dengan launcher guarded/idempotent `scripts/phase06-uat.php` (`init`/`prepare`/`status`), 130 siswa sintetis, 12 kelas pada dua tahun, 10 guru aktif + 1 separated, 4 staf, dan 4 jurusan.

Tidak dikerjakan pada 06a: **06b** (naik/tinggal/lulus), **06c** (mutasi/koreksi), CRUD semester, unique index DB untuk nama kelas (D6), merge/deploy/force-push, migrasi database operasional/MariaDB, dan perubahan dependency/service/`.env`.

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File | Perubahan | Schema/izin |
|---|---|---|
| `database/migrations/2029_07_30_000001_create_academic_settings_table.php` | Tabel singleton `academic_settings` (id=1) | FK `active_school_year_id`/`active_semester_id`/`updated_by` nullable `nullOnDelete`; baris id=1 tanpa nilai aktif |
| `database/migrations/2029_07_30_000002_add_period_and_lock_to_school_years.php` | Kolom `start_date`, `end_date`, `locked_at`, `locked_by`, `lock_reason` + index `locked_at` | Additive nullable; `down()` aman untuk SQLite/MySQL |
| `database/migrations/2029_07_30_000003_add_class_teach_employee_to_school_rombels.php` | `class_teach_employee_id` → `employees` + index `(school_year_id, deleted_at)`; backfill legacy | Backfill hanya pemetaan `employees.user_id` tunggal yang aktif `Pendidik`; baris ambigu dibiarkan null; log hanya agregat tanpa PII |
| `app/Models/AcademicSetting.php` | Model singleton + `Loggable` allowlist (`active_school_year_id`, `active_semester_id`, `updated_by`) | Tanpa data pribadi |
| `app/Http/Controllers/AcademicPeriodController.php` | `show` (periode + daftar tahun/semester) dan `update` (transaksi + row lock) | `students.view` baca; `master.manage` tulis |
| `app/Http/Controllers/SchoolYearController.php` | Serialisasi tanggal/active/lock/count; validasi urutan tanggal; guard hapus aktif/locked/kelas termasuk arsip; `lock`/`unlock` beralasan + audit | `master.view`/`master.manage` |
| `app/Http/Controllers/SchoolRombelController.php` | List filter tahun + hitungan anggota; create/update/delete transaksional; lock tahun; nama unik case-insensitive; `expected_version`; imutabilitas struktural; `409`; resolusi wali `Pendidik` aktif | `students.view`/`students.manage` |
| `app/Http/Controllers/EmployeeController.php` | `teacherOptions`: hanya `Pendidik` aktif (exit_date null/≥ hari ini), `id`+`name`, limit 50, search maks. 100 | `employees.view` |
| `app/Http/Controllers/StudentRombelController.php` | Assign/transfer mengunci tahun lebih dulu dan menolak tahun locked/soft-deleted | `students.manage`; pesan lock khusus |
| `app/Http/Requests/StoreSchoolRombelRequest.php`, `UpdateSchoolRombelRequest.php` | Rules wajib/opsional, trim nama, null untuk field opsional kosong, `expected_version` | `students.manage` |
| `app/Http/Requests/AssignStudentRombelRequest.php`, `TransferStudentRombelRequest.php` | Tahun/kelas hanya non-deleted; tahun locked ditolak | `students.manage` |
| `app/Models/SchoolRombel.php` | `Loggable` allowlist, relasi `classTeachEmployee`, fillable, `version` | Audit tanpa data pribadi siswa/guru |
| `app/Models/SchoolYear.php`, `app/Models/Employee.php` | Cast tanggal/lock, fillable terkait lock; scope `eligibleHomeroomTeacher` | — |
| `app/Policies/SchoolRombelPolicy.php`, `SchoolYearPolicy.php` | Policy terpusat; restore/forceDelete ditolak | Kemampuan bernama, tanpa cek angka |
| `routes/api.php` | Route periode, lock/unlock, teacher-options | Lihat tabel endpoint |
| `scripts/phase06-uat.php` | Launcher UAT fail-closed + fixture idempotent | Hanya `.uat-phase06` SQLite |
| `tests/Feature/Academic/AcademicPeriodAndLockTest.php` | 10 tes | Guest/view-only, periode tunggal, lock/unlock, penolakan CRUD/assign/transfer, tanggal, hapus tahun |
| `tests/Feature/Academic/SchoolRombelCrudTest.php` | 10 tes | Guest/view-only, create opsional, validasi wali, duplikat case-insensitive, versi, imutabilitas, pindah tahun, hapus, list, teacher options |
| `vue-app/src/views/pages/public/SchoolYears.vue` | Penetapan periode aktif, create/edit tanggal, lock/unlock dengan alasan, tag, jumlah kelas, guard hapus | `master.view`/`master.manage` |
| `vue-app/src/views/pages/public/SchoolRombels.vue` | Halaman Classes khusus: filter tahun, CRUD, pencarian wali remote, banner lock, dialog konflik 409 + Reload Latest, konfirmasi hapus | `students.view`/`students.manage` |
| `vue-app/src/views/pages/public/Student.vue` | Tahun default dari periode server; tag/banner active+locked; assign/transfer dinonaktifkan saat locked; `flex-wrap` tab group agar 390 px tidak overflow | `students.view`/`students.manage` |
| `vue-app/src/layout/AppMenu.vue`, `vue-app/src/router/index.js` | Grup **Academic**; Classes `students.view`, School Years `master.view` | Tanpa ability baru |

Kontrak endpoint (detail di `docs/API-CONTRACTS.md`):

| Method | Endpoint | Izin | Catatan |
|---|---|---|---|
| GET | `/api/academic/period` | `students.view` | `data.active` (`school_year`, `semester` atau null), `data.school_years`, `data.semesters` |
| PUT | `/api/academic/period` | `master.manage` | `active_school_year_id`, `active_semester_id`; menolak tahun locked/soft-deleted dan semester tidak valid (`422`) |
| POST | `/api/school-years/{id}/lock` | `master.manage` | `reason` wajib (maks. 255); tahun aktif `422`; idempotent |
| POST | `/api/school-years/{id}/unlock` | `master.manage` | `reason` wajib; idempotent |
| GET | `/api/employees/teacher-options?search=` | `employees.view` | Hanya `Pendidik` aktif; payload `id`,`name` |
| GET | `/api/school-rombels?school_year_id=` | `students.view` | Item memuat `version`, `school_year` (dengan `is_locked`), `class_teach_employee`, `memberships_count`, `active_members_count` |
| POST/PUT/DELETE | `/api/school-rombels` | `students.manage` | `expected_version` wajib untuk update/delete; `409` + `current_version` |
| POST | `/api/students/{id}/assign-rombel`, `/transfer-rombel` | `students.manage` | Menolak tahun locked/soft-deleted dan kelas soft-deleted |
| POST/PUT/DELETE | `/api/school-years` | `master.manage` | Tanggal opsional berurutan; delete butuh `delete=true` dan ditolak bila aktif/locked/punya kelas (termasuk arsip) |

## 3. Evidence

| ID | Metode / command | Fixture / peran | Expected | Actual | Status |
|---|---|---|---|---|---|
| E1 | `php artisan test` (suite penuh) | SQLite in-memory + MariaDB disposable yang tersedia | Tidak ada regresi | **437 passed / 13 skipped / 3.971 assertions, 321,27 s**; skip adalah tes integrasi MariaDB tanpa password | PASS |
| E2 | `php artisan test tests/Feature/Academic` | SQLite in-memory | Semua skenario periode/lock/CRUD PASS | **20 passed / 145 assertions, 9,59 s** | PASS |
| E3 | `php artisan test tests/Feature/Student/StudentRombelAssignmentTest.php tests/Feature/Student/StudentRombelConstraintTest.php tests/Feature/Student/StudentRombelResolutionTest.php` | SQLite in-memory | Regresi penempatan 03d tetap PASS | **22/92 + 5/12 = 27 passed / 104 assertions** | PASS |
| E4 | `./vendor/bin/pint --test` pada 22 file PHP berubah/baru | — | Gaya bersih | **PASS 22 files** | PASS |
| E5 | `npm run build` | Vite 6 | Build sukses | **PASS 806 modules, 21,55 s**; warning chunk >500 kB sudah ada sebelumnya | PASS |
| E6 | `npx eslint --quiet` pada file frontend berubah | ESLint read-only | 0 error | **exit 0**; warning non-quiet hanya Prettier/CRLF lingkungan (`core.autocrlf=true`), bukan error kode | PASS (scoped) |
| E7 | `php artisan route:list` (filter academic/school-years/teacher-options) | — | Route terdaftar | Route periode, lock/unlock, teacher-options terdaftar dengan middleware sesuai | PASS |
| E8 | `php scripts/phase06-uat.php status` | `.uat-phase06` SQLite terisolasi | Guard SQLite lolos; jumlah benar | Guard PASS; **133 siswa (130 UAT-P06)**; **12 kelas** (6 per tahun); **10 guru aktif + 1 separated**; **4 staf**; **active period 1/1**; tahun 2026/2027 unlocked, 2027/2028 locked | PASS |
| E9 | Browser DOM + API (Chrome DevTools MCP, `127.0.0.1:14024`) | `uat.operator` | Periode aktif, lock, CRUD, validasi, konflik | Checklist #1–#9, #11, #12 sesuai; rincian di §4 | PASS |
| E10 | Browser DOM + API | `uat.kepsek` | View-only tidak dapat mengubah | Classes tanpa New/Edit/Delete/Action; School Years tanpa simpan/lock; API create/lock/update/delete/period → **403** | PASS |
| E11 | Query read-only audit `activities` | `.uat-phase06` | Perubahan tercatat tanpa rahasia | Periode, lock/unlock/relock (alasan + aktor), kelas create/update/delete (before/after/new) tercatat; tidak ada password/token | PASS |

**NOT RUN (tidak dikonversi menjadi PASS):**

- Migrasi dan backfill pada **MariaDB/InnoDB** serta pengujian dua koneksi untuk lock tahun; seluruh bukti 06a memakai SQLite terisolasi.
- Skenario balapan (race) lock tahun vs assign/transfer di MariaDB secara nyata; jalur kode memakai row lock + transaksi dan tertutup tes perilaku, tetapi konkurensi multi-proses tidak dijalankan.
- Uji browser dua tab untuk konflik `409` kelas oleh dua operator; konflik diuji lewat API + dialog dengan data basi, bukan dua tab nyata.
- Screenshot/visual regression berbasis file gambar; bukti browser berupa snapshot DOM/pengukuran layout, bukan artefak gambar yang dikomit.
- 06b (naik/tinggal/lulus), 06c (mutasi/koreksi), CRUD semester, dan unique index DB nama kelas — di luar scope 06a.
- Penerimaan manual pemilik; status review tetap PENDING sampai pernyataan pemilik.

## 4. UAT manual

Environment: `.uat-phase06` SQLite terisolasi, SPA `http://127.0.0.1:14024` (PID 19240), API `http://127.0.0.1:18024` (PID 2388), cookie `school_phase06_session`, launcher `laravel-api/scripts/phase06-uat.php`. Kedua server **masih berjalan** untuk review pemilik; hentikan setelah penerimaan sesuai DEC-27.

Akun review: `uat.operator` (manage) dan `uat.kepsek` (view-only). Password hanya berada di runtime `.uat-phase06/account-password.txt` yang diabaikan; tidak dicetak di dokumen. Fixture juga memakai `uat.waka` untuk profil guru tanpa akun terpisah.

Fixture saat ini (guarded + idempotent, data lama tidak di-reset):

- 130 siswa `UAT-P06` (sebagian placed di tahun sumber/target, sebagian unplaced) + 3 siswa lama = 133.
- 12 kelas: `X-1, X-2, XI-1, XI-2, XII-1, XII-2` pada 2026/2027 dan 2027/2028.
- 10 guru aktif `UAT-P06 Teacher 01–10` (sebagian tanpa akun login), 1 guru separated, 4 staf `UAT-P06 Staff 01–04`, 4 jurusan.
- Periode aktif: **2026/2027 · Ganjil**. Tahun 2027/2028 **locked** dengan alasan uji `UAT review lock 2026-10-31`; ini sengaja ditinggalkan untuk review checklist #2/#3. Kelas `X-3 UAT` yang dibuat saat uji duplikat/CRUD sudah dihapus kembali; baris auditnya tetap ada.

Hasil checklist #1–#12 (rincian langkah ada di `PHASE-06A-MANUAL-REVIEW.md`):

1. **Periode aktif** — 2026/2027 · Ganjil tersimpan; hanya satu tahun aktif (server menghitung `activeCount: 1`).
2. **Lock tahun** — API lock tahun aktif → `422` "The active school year cannot be locked. Set another active period first."; tahun 2027/2028 berhasil dikunci dengan alasan; unlock/relock berfungsi dan diaudit.
3. **Tahun locked** — UI Classes menampilkan tag Locked dan menonaktifkan Edit/Delete; API create/update/delete → `422`; assign/transfer → `422` dengan pesan lock khusus ("...placing students." / "...moving students.").
4. **Wali kelas Pendidik** — kelas `X-3 UAT` dibuat dengan `UAT-P06 Teacher 01`; `teacher-options` mengembalikan tepat 10 `Pendidik` aktif, tanpa staf dan tanpa guru separated.
5. **Tanpa jurusan/wali** — create berhasil `201`; `student_major_id` dan `class_teach_employee_id` null.
6. **Nama kelas** — duplikat dalam tahun yang sama (beda kapitalisasi) → `422`; nama sama di tahun berbeda → `201`; edit menaikkan versi v1→v2; versi basi v2 vs data eksternal v3 → `409` dan dialog **Reload Latest** memuat v3.
7. **Hapus kelas** — kelas kosong terhapus (soft delete) dengan `expected_version`; kelas berisi riwayat keanggotaan → `422`.
8. **Students memakai tahun server** — default 2026/2027 dari `/api/academic/period`; total 133 / placed 73 / unplaced 60; banner tahun locked dan tag Locked tampil; tombol assign/transfer nonaktif.
9. **View-only** — `uat.kepsek`: halaman Classes tanpa kontrol tulis, School Years tanpa simpan/lock; API create/lock/update/delete/period → `403`.
10. **Audit** — periode, lock/unlock/relock (alasan + aktor 2), dan create/update/delete kelas tercatat pada `activities`; tidak ada kredensial.
11. **English + 390 px** — tidak ditemukan label Indonesia; Classes (dialog 371 px), School Years, dan Students (setelah perbaikan `flex-wrap`, `docScrollWidth` 375 ≤ 390) tanpa overflow halaman.
12. **UI lain** — tidak ada error console yang tercatat; dialog konflik dan hapus dapat dioperasikan.

Catatan otomasi: PrimeVue AutoComplete di lingkungan ini hanya memicu pencarian remote pada input keyboard nyata; alat CDP menghapus spasi (`Teacher 01` → `Teacher01`). Pengujian memakai token tunggal `Teacher` lalu memilih opsi dari daftar. Ini artefak alat, bukan cacat aplikasi.

## 5. Risiko dan keputusan

- **Tahun aktif tidak boleh lock/delete dan tahun locked tidak boleh active/delete** — dicek di server dengan row lock pada `academic_settings` dan `school_years`; UI hanya membantu, bukan penegak utama.
- **Lock idempotent**: lock ulang tahun yang sudah locked atau unlock tahun yang belum locked mengembalikan `200` tanpa perubahan dan tanpa audit baru; alasan tidak menimpa data lama.
- **Jumlah kelas pada tahun ajaran** dihitung termasuk kelas soft-deleted (`withTrashed`) agar tahun tidak dapat dihapus saat masih memiliki riwayat kelas; kolom Classes di School Years dapat menampilkan angka lebih besar dari daftar kelas aktif. Ini disengaja sesuai keputusan D6/plan §5.8 dan dijelaskan di laporan, bukan bug.
- **Nama kelas belum memakai unique index DB** (D6): validasi case-insensitive dilakukan di dalam transaksi dengan lock baris tahun. Unique index menunggu audit data existing pada subfase berikutnya.
- **Wali kelas legacy**: `class_teach_id` dipertahankan; backfill hanya mengisi baris dengan tepat satu profil `Pendidik` aktif untuk user tersebut. Baris ambigu tetap null dan dilaporkan agregat; UI menampilkan nama guru legacy sebagai informasi.
- **Imutabilitas struktural**: tahun/tingkat/jurusan terkunci setelah ada riwayat keanggotaan (termasuk soft-deleted), sedangkan nama dan wali kelas masih dapat diubah selama tahun belum locked.
- **Perbaikan 390 px**: tab group pada Students tidak membungkus (pre-existing) sehingga halaman melebar; `flex-wrap` ditambahkan pada container dan diukur ulang (`docScrollWidth` 375). Tidak mengubah perilaku desktop.
- **Batas bukti**: seluruh UAT memakai SQLite; MariaDB, race multi-proses, dan dua tab nyata tetap NOT RUN. Migrasi belum dijalankan pada database operasional atau `.uat-phase05`.
- **UAT lifecycle**: server 14024/18024 dipertahankan sampai pemilik menyatakan penerimaan; setelah itu dihentikan sesuai DEC-27 dan data `.uat-phase06` dipertahankan.

## 6. Sinkronisasi dokumen

Diperbarui: `docs/DATABASE.md` (tabel/kolom 06a), `docs/API-CONTRACTS.md` (endpoint periode/lock/teacher-options dan aturan kelas), `docs/ACCESS-CONTROL.md` (matriks izin periode/kelas), `docs/TESTING.md` (bukti dan NOT RUN 06a), `docs/DECISIONS.md` (catatan implementasi DEC-29), `docs/REQUIREMENTS.md` (FR-07 status), `CHANGELOG.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `reports/feature-update/PHASE-06-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `PROJECT_CONTEXT.md`, laporan ini, dan checklist manual.

Commit/push dilakukan agar pemilik dapat meninjau implementasi di GitHub; commit/push bukan penerimaan manual. Penerimaan checklist #1–#12 akan dicatat pada commit dokumentasi tersendiri berdasarkan pernyataan pemilik. Tidak ada merge/deploy/force-push, perubahan `.env` utama, dependency/lockfile, service, DB terlarang, atau migrasi database operasional/MariaDB.

## 7. Langkah berikutnya

1. Pemilik meninjau `PHASE-06A-MANUAL-REVIEW.md` pada `.uat-phase06` (checklist #1–#12) dan menyatakan hasilnya.
2. Bila diterima, penerimaan dicatat pada commit dokumentasi tersendiri; server UAT 14024/18024 dihentikan dan data `.uat-phase06` dipertahankan (DEC-27).
3. 06b/06c menunggu rencana dan keputusan pemilik terpisah; jangan mulai otomatis. Merge/deploy tetap tidak diotorisasi.
