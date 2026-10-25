# Laporan Subfase 03d — Penempatan dan Perpindahan Rombel via UI

Tanggal WIB: 2026-10-26. Branch: `main`.
Baseline rencana: `e4f6d40a95a00c863ed8aeec79d274bc5714864f`.
Keputusan pemilik: **DEC-23** (APPROVED pada 2026-10-26 WIB, commit `01c953e`).
Status implementasi: **IMPLEMENTED & FULLY VERIFIED (AGEN)**.
Status penerimaan manual pemilik: **PENDING (Checklist manual #1–#15 menunggu review pemilik)**.
Status fase: Fase 01, 02, dan 03 tetap **DITERIMA LENGKAP & DITUTUP**; Subfase 03d adalah scope tambahan yang penerimaan manualnya terpisah.

> **Pernyataan Batas Scope:** Laporan ini membuktikan implementasi teknis dan verifikasi otomatis Subfase 03d. Bukti agen terpisah dari penerimaan manual pemilik. Penerimaan bukan izin deployment ke produksi dan bukan penyelesaian seluruh risiko keamanan terbuka.

---

## 1. Ringkasan Implementasi

Subfase 03d melengkapi alur operasional siswa di antarmuka `Student.vue` pasca-penutupan Fase 03, sesuai Keputusan Pemilik DEC-23:
1. **Penempatan Siswa Unplaced (`assign-rombel`)**:
   - Endpoint: `POST /api/students/{id}/assign-rombel`.
   - Menempatkan siswa berstatus `Unplaced` ke salah satu rombel pada tahun ajaran aktif yang sedang dipilih di halaman Students (`school_year_id`).
   - Server menentukan tanggal masuk (`latest_student_entry_date`) secara mutlak menggunakan tanggal hari berjalan pada timezone `Asia/Jakarta`.
   - Mengunci baris siswa (`lockForUpdate`), memvalidasi ketiadaan keanggotaan aktif lain pada tahun yang sama, membuat record keanggotaan baru (`version = 1`), dan menaikkan versi siswa (`students.version = students.version + 1`) dalam satu transaksi atomik.
2. **Perpindahan Rombel Siswa Placed (`transfer-rombel`)**:
   - Endpoint: `POST /api/students/{id}/transfer-rombel`.
   - Memindahkan siswa berstatus `Placed` ke rombel lain dalam tahun ajaran yang sama secara atomik.
   - Keanggotaan lama ditutup dengan mencatat `latest_student_exit_date = $todayWib` dan menaikkan versinya. `student_status_id` keanggotaan lama **tidak diubah** menjadi Mutasi Keluar (DEC-23 #5).
   - Catatan alasan transfer disimpan di domain pada field `latest_information` (maksimum 240 karakter) tanpa menyalin teks mentah ke payload audit.
   - Keanggotaan baru dibuka di rombel tujuan dengan `version = 1`, `student_status_id = 1`, dan `latest_student_entry_date = $todayWib`.
   - Versi siswa dinaikkan atomik (`students.version = students.version + 1`).
3. **Optimistic Concurrency Control (HTTP 409 Conflict)**:
   - Validasi `expected_student_version` pada Assign dan Transfer.
   - Validasi tambahan `expected_membership_version` pada Transfer untuk mencegah dua operator memindahkan keanggotaan yang sama secara simultan.
   - Setiap konflik versi mengembalikan HTTP 409 Conflict yang memicu dialog resolusi konflik di UI tanpa membuang input form.
4. **Otorisasi Terpusat**:
   - Ditegakkan seragam menggunakan kemampuan `can:students.manage` pada route middleware dan Policy/FormRequest, bukan hardcode nama atau ID peran.
   - Operator Sekolah dan Admin Sistem diizinkan; Kepala Sekolah (view-only) ditolak HTTP 403 Forbidden.
5. **Antarmuka Pengguna (100% English di `Student.vue`)**:
   - Tombol kondisional di kolom `Action`:
     - Siswa `Unplaced`: tombol biru outline `pi pi-user-plus` ("Assign Rombel").
     - Siswa `Placed`: tombol oranye outline `pi pi-arrow-right-arrow-left` ("Transfer Rombel").
     - Tersembunyi otomatis untuk akun view-only via `v-if="canManage"`.
   - Dialog interaktif `placementDialog`: menampilkan ringkasan siswa, tahun ajaran aktif (read-only), tanggal efektif server WIB (read-only label), dropdown rombel tujuan yang tervalidasi, textarea alasan (hanya saat transfer), double-submit guard (`:loading`, `:disabled`), dan refresh instan tabel & rekap server.
   - Checkbox koreksi / `is_correction` tidak ditampilkan (ditunda per DEC-23 #1).

---

## 2. Berkas yang Berubah / Dibuat

| Berkas | Status | Perubahan |
|---|---|---|
| `docs/DECISIONS.md` | Modifikasi | Mencatat Keputusan Pemilik **DEC-23** (Assign & Transfer saja, tanpa koreksi, tahun aktif, tanggal server Asia/Jakarta, preservasi riwayat keluar, students.manage, versioning atomik). |
| `laravel-api/routes/api.php` | Modifikasi | Mendaftarkan route `POST /api/students/{id}/assign-rombel` dan `POST /api/students/{id}/transfer-rombel` dilindungi `can:students.manage`. |
| `laravel-api/app/Http/Requests/AssignStudentRombelRequest.php` | Baru | FormRequest validasi input penempatan: `school_year_id`, `school_rombel_id` (belongs to year), `expected_student_version`, pesan error English 422. |
| `laravel-api/app/Http/Requests/TransferStudentRombelRequest.php` | Baru | FormRequest validasi input pemindahan: `school_year_id`, `target_rombel_id` (belongs to year & different), `expected_student_version`, `expected_membership_version`, `reason`, pesan error English 422. |
| `laravel-api/app/Policies/StudentRombelPolicy.php` | Modifikasi | Menegakkan method policy `viewAny`/`view` (`students.view`) dan `create`/`update`/`delete`/`restore` (`students.manage`). |
| `laravel-api/app/Http/Controllers/StudentRombelController.php` | Modifikasi | Mengimplementasikan method `assignRombel()` dan `transferRombel()`, row lock InnoDB `Student::lockForUpdate`, penanganan tanggal Asia/Jakarta, verifikasi konkurensi 409, dan preservasi status. |
| `vue-app/src/views/pages/public/Student.vue` | Modifikasi | Menambahkan tombol Assign/Transfer di kolom Action, dialog `placementDialog`, state manajemen form, double-submit guard, pemanggilan API 03d, error handling 409 & 422. |
| `laravel-api/tests/Feature/Student/StudentRombelAssignmentTest.php` | Baru | Suite pengujian otomatis SQLite in-memory: 16 skenario (auth 401/403, assign unplaced, validasi tahun/rombel, penolakan arsip 422, conflict 409, transfer atomik, audit privacy, rollback kegagalan audit assign & transfer). |
| `laravel-api/tests/Feature/Student/MariaDbRombelConcurrencyTest.php` | Baru | Pengujian integrasi MariaDB pada database disposable `school_03d_integration`: 7 skenario (dua assign konkuren, penolakan siswa terarsip non-race, dua transfer konkuren, archive vs assign [2 urutan lock], archive vs transfer [2 urutan lock]). |
| `laravel-api/tests/Support/concurrency_worker.php` | Baru | Harness worker proses independen untuk eksekusi endpoint nyata dan sinkronisasi deterministik lock InnoDB MariaDB. |
| `reports/feature-update/PHASE-03D-STUDENT-PLACEMENT.md` | Baru | Laporan implementasi teknis dan verifikasi Subfase 03d. |
| `reports/feature-update/PHASE-03D-MANUAL-REVIEW.md` | Baru | Panduan checklist manual peninjauan pemilik (#1–#15, status PENDING). |

---

## 3. Hasil Pengujian Otomatis (Hasil Aktual)

### 3.1 Backend SQLite Automated Tests
- Eksekusi: `C:\xampp\php82-operational\php.exe artisan test <28-test-files>`
- Pengecualian eksplisit: `MariaDbIntegrationTest.php`, `MariaDbStudentIntegrationTest.php`, `MariaDbRombelConcurrencyTest.php` (dikecualikan agar suite murni SQLite in-memory dan tidak menyentuh socket MariaDB).
- Hasil Aktual Keseluruhan: **237 passed (0 failed)** across 28 test files, total **1117 assertions** di atas PHP 8.2.35 ZTS x64.
- Cakupan khusus `StudentRombelAssignmentTest.php`: **16 passed (78 assertions)** mencakup seluruh validasi 422, otorisasi 401/403, conflict 409, transaksi atomik, dan **rollback kegagalan audit**:
  1. `audit insert failure during assign rolls back new membership and student version`: disimulasikan via `Activity::$testCreatingHook` pada event insert siswa yang berjalan setelah insert membership; membuktikan membership tidak tersimpan, versi siswa tetap 1, tidak ada audit parsial, dan record pembanding utuh.
  2. `audit insert failure during transfer rolls back source membership closure and target membership creation`: disimulasikan via `Activity::$testCreatingHook` saat update siswa setelah mutasi membership; membuktikan membership lama tetap aktif (exit_date kembali NULL, status tetap 1, versi tetap 1), membership target tidak tersimpan, versi siswa tetap 1, tidak ada audit parsial, dan record pembanding utuh.

### 3.2 MariaDB Concurrency Integration Tests
- Target database: `school_03d_integration` (database disposable khusus pada MariaDB port 3306; user `school_compat_app` dengan guard koneksi eksak).
- Harness: `tests/Support/concurrency_worker.php` menjalankan worker proses nyata (`proc_open`) yang memanggil request pipeline/controller aktual (`assignRombel`, `transferRombel`, `destroy`), membuktikan tumpang tindih transaksi / lock wait secara deterministik via `SHOW FULL PROCESSLIST` / `information_schema.innodb_trx` / `innodb_lock_waits` (bukan sekadar `sleep`).
- Hasil Aktual: `MariaDbRombelConcurrencyTest.php` -> **7 passed (43 assertions)** dalam 10.55s:
  1. **Dua Assign Konkuren Serial via Row Lock**: tepat satu transaksi commit 200, satu ditolak 409 Conflict. Versi siswa akhir 2; jumlah keanggotaan aktif tepat 1.
  2. **Penolakan Siswa Terarsip (Non-Race)**: siswa dengan kondisi pre-existing `deleted_at IS NOT NULL` ditolak oleh trigger DB `trg_student_rombels_single_active_insert` (SQLSTATE 45000); tidak ada keanggotaan aktif yang tercipta.
  3. **Dua Transfer Konkuren Simultan**: dua worker transfer pada siswa/membership/expected version yang sama bertumpang tindih deterministik; tepat satu berhasil 200 (keanggotaan lama ditutup, keanggotaan baru tercipta), transaksi kedua ditolak 409 Conflict karena versi telah naik. Total keanggotaan aktif tepat 1, riwayat lama terpelihara dengan status 1 (DEC-23 #5).
  4. **Archive vs Assign Race (Assign Menang Lock Pertama)**: Assign selesai 200 OK dan menaikkan versi siswa ke 2; Archive yang memegang expected version 1 unblock dan ditolak **409 Conflict**. Siswa `deleted_at` tetap NULL (tidak terarsip), rombel aktif 1.
  5. **Archive vs Assign Race (Archive Menang Lock Pertama)**: Archive selesai 200 OK (siswa di-soft-delete, rombel dinonaktifkan); Assign yang unblock mendeteksi `Student::trashed()` dan ditolak **422 Unprocessable Entity** ("Cannot assign rombel to an archived student"). Siswa terarsip, total rombel aktif 0.
  6. **Archive vs Transfer Race (Transfer Menang Lock Pertama)**: Transfer selesai 200 OK (rombel lama ditutup, rombel baru aktif, versi naik ke 2); Archive dengan expected version 1 unblock dan ditolak **409 Conflict**. Siswa `deleted_at` tetap NULL, total rombel aktif 1 (pada rombel tujuan).
  7. **Archive vs Transfer Race (Archive Menang Lock Pertama)**: Archive selesai 200 OK (siswa di-soft-delete, keanggotaan aktif ditandai `archived_at`); Transfer yang unblock mendeteksi `Student::trashed()` dan ditolak **422 Unprocessable Entity** ("Cannot transfer rombel for an archived student"). Siswa terarsip, total rombel aktif 0.

### 3.3 Frontend Unit Tests, Build & Lint
- Unit tests: `& $node --test` (Node portable v24.22.1) -> **18 passed (0 failed)** dalam 1090ms.
- Production build: `& $node $npmCli run build` -> **PASS** (Vite 6.3.5, 789 modules transformed dalam 1m 9s).
- Lint terarah: `& npx.cmd eslint src/views/pages/public/Student.vue src/main.js` -> **0 errors, 2750 warnings** (seluruh warning adalah CRLF `prettier/prettier Delete ␍`, tanpa error sintaks/variabel tidak terpakai).

---

## 4. Hasil Smoke Test UAT Terarah (Hasil Aktual)

Dijalankan secara terisolasi terhadap server aktif di port 18021/14021:
1. **CSRF & Autentikasi Operator**: Bootstrap CSRF 204 sukses (`school_compat_session`), login `uat.operator` sukses (204), check-auth mengonfirmasi kemampuan `students.manage`.
2. **Pembuatan Siswa Unplaced Baru**: Siswa baru `UAT 03D Fresh Student` dibuat (status 201, initial version = 1). Siswa terverifikasi muncul di filter `Unplaced` tahun ajaran aktif 2026/2027.
3. **Assign Rombel & Rekap**:
   - Percobaan Assign dengan `expected_student_version` usang (999) ditolak **409 Conflict**.
   - Assign valid ke Rombel 1 sukses (200 OK): `student_version` naik menjadi 2, `membership_version = 1`.
   - Verifikasi filter: Siswa berpindah dari `Unplaced` ke `Placed`. Rekapitulasi server otomatis memperbarui `placed_count` (+1) dan `unplaced_count` (-1).
   - Percobaan duplicate Assign ke siswa yang sudah placed ditolak **409 Conflict** (`Student already has an active rombel membership in this academic year`).
4. **Transfer Rombel & Preservasi Riwayat**:
   - Transfer ke rombel yang sama (Rombel 1 -> Rombel 1) ditolak **422 Validation Error**.
   - Transfer dengan `expected_student_version` usang (999) ditolak **409 Conflict**.
   - Transfer dengan `expected_membership_version` usang (999) ditolak **409 Conflict**.
   - Transfer valid ke Rombel 2 dengan alasan *"UAT Transfer Verification: balancing classes"* sukses (200 OK): `student_version` naik menjadi 3, keanggotaan baru memiliki `version = 1`.
   - Inspeksi riwayat detail siswa:
     - Keanggotaan lama (Rombel 1): `latest_student_exit_date = 2026-10-26`, `student_status_id = 1` (Preserved, bukan Mutasi Keluar), `latest_information` memuat alasan transfer.
     - Keanggotaan baru (Rombel 2): `latest_student_entry_date = 2026-10-26`, `latest_student_exit_date = ACTIVE`, `student_status_id = 1`.
   - Ekspor CSV: Terverifikasi file CSV hasil ekspor memuat rombel baru (Rombel 2).
5. **Otorisasi Kepala Sekolah (View-Only)**:
   - Login `uat.kepsek` (hanya `students.view` + `students.export`).
   - `POST /api/students/{id}/assign-rombel` ditolak **403 Forbidden**.
   - `POST /api/students/{id}/transfer-rombel` ditolak **403 Forbidden**.
   - Hak akses ditegakkan murni berdasarkan kemampuan `students.manage`.

---

## 5. Status Server UAT & Proses Aktif

Server UAT tetap dibiarkan berjalan aktif di loopback untuk keperluan peninjauan checklist manual oleh pemilik:
- **Backend API**: PID induk `3812`, PID child `15960` (`C:\xampp\php82-operational\php.exe -S 127.0.0.1:18021`).
- **Frontend SPA**: PID `17964` (`Node portable 24.22.1 ... vite --mode uat --host 127.0.0.1 --port 14021 --strictPort`).
- Apache port 80/443 (PID 16684) dan MariaDB port 3306 (PID 1840) tetap aktif.

---

## 6. Langkah Berikutnya

1. Pemilik meninjau checklist manual di `reports/feature-update/PHASE-03D-MANUAL-REVIEW.md` (#1–#15) menggunakan server aktif pada `http://127.0.0.1:14021`.
2. Setelah peninjauan selesai, pemilik memberikan persetujuan manual atas checklist tersebut.
3. Status Subfase 03d tetap **IMPLEMENTED & VERIFIED (AGEN) / MANUAL ACCEPTANCE PENDING** sampai approval pemilik dicatat.
