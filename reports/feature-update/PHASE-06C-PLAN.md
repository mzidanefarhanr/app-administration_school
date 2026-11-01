# Rencana Subfase 06c — Mutasi Masuk/Keluar, Pembatalan, Rekap, dan Koreksi Periode Terkunci

- Tanggal: 2026-11-01 (WIB). Branch: `main`. Baseline dokumen: `953e6f7` (checkpoint penerimaan 06b; implementasi 06b `c1dde67` di atas `main` @ `6dcd432`).
- Status dokumen: **PLAN APPROVED — pemilik menyatakan "Saya setuju D1-D10." pada 2026-11-01 WIB; dicatat sebagai DEC-31 APPROVED (D1–D10).** Implementasi 06c **selesai dan agent-verified 2026-11-01**; review manual pemilik **PENDING (#1–#16)** di `PHASE-06C-MANUAL-REVIEW.md`, bukti di `PHASE-06C-REPORT.md`.
- Cakupan: **06c saja** — mutasi masuk dan keluar bertanggal/alasan/sekolah, pembatalan berizin yang kompensatif, rekap mutasi/kelulusan/tinggal kelas per tahun ajaran, dan koreksi periode terkunci melalui tindakan khusus ter-audit.
- Di luar cakupan: rekap semester (skema `semesters` belum memiliki tanggal), pembuatan siswa baru melalui mutasi, undo batch progresi 06b, penghapusan log mutasi, validasi kapasitas kelas, ekspor, integrasi Dapodik, merge/deploy, migrasi database operasional/MariaDB, perubahan dependency/service/`.env`.
- Dokumen acuan: `reports/feature-update/PHASE-06-PLAN.md` §7 (outline 06c), `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `docs/DECISIONS.md` (DEC-20, DEC-21, DEC-23, DEC-27, DEC-29, DEC-30), `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `docs/DATABASE.md`, `docs/TESTING.md`.
- Gate: 06a **OWNER APPROVED 2026-10-31 WIB (#1–#12)** dan 06b **OWNER APPROVED 2026-11-01 WIB (#1–#14)**. **D1–D10 disetujui pemilik 2026-11-01 WIB ("Saya setuju D1-D10.") sebagai DEC-31 APPROVED; implementasi 06c diotorisasi.** Persetujuan ini bukan merge/deploy dan bukan izin migrasi database operasional.

---

## 1. Posisi dan prasyarat

| Prasyarat | Kondisi aktual 2026-11-01 | Status |
|---|---|---|
| 06a periode aktif, tanggal tahun, lock | Implemented, owner approved; `school_years.start_date`/`end_date` ada dan nullable; tahun aktif tidak dapat lock; tahun locked menolak assign/transfer | Terpenuhi |
| 06b progresi | Implemented, owner approved; batch permanen `student_progression_batches`; `retain` hanya tercatat di JSON batch | Terpenuhi |
| Status siswa | Hidup pada `student_rombels` (`student_status_id`, `student_entry_id`, tanggal masuk/keluar/lulus), bukan `students` | Terpenuhi |
| Entry `Mutasi Masuk` | Seed id **2**; `Siswa Baru` id 1 | Terpenuhi |
| Status `Mutasi Keluar`/`Lulus` | Seed id **2** dan **6**; `Aktif` id 1 | Terpenuhi |
| Sekolah asal/tujuan | `education_schools.npsn` unik, string, soft delete; `status_education` berupa string bebas | Terpenuhi (validasi status tidak ditebak) |
| Log mutasi | Belum ada tabel/model/endpoint/tes | Perlu dibuat |
| Koreksi periode terkunci | Belum ada ability/endpoint/tabel koreksi | Perlu dibuat |
| Rekap semester | `semesters` hanya `name`/`name_other` tanpa tanggal dan tanpa relasi tahun | Tidak memungkinkan tanpa perluasan skema; ditunda |
| UI mutasi | Placeholder `Student Transfers` kosong di `Student.vue` | Perlu diimplementasikan |
| UAT | `.uat-phase06` dipertahankan; server 14024/18024 dihentikan setelah penerimaan 06b (DEC-27) | Siap dinyalakan kembali |

Catatan: angka dan kondisi di atas diverifikasi dari source dan `.uat-phase06` (read-only), bukan data operasional.

---

## 2. Temuan audit (fakta kode)

### 2.1 Mutasi belum memiliki log domain

- `student_rombels` sudah memiliki kolom `student_status_id`, `student_entry_id`, `latest_student_entry_date`, `mutation_education_school_id`, `latest_student_exit_date`, `latest_student_exit_school_id`, dan `latest_student_graduation_date`: `laravel-api/database/migrations/2029_07_10_070707_create_student_rombels_table.php:21-31`.
- Tidak ada tabel, model, service, controller, route, request, atau tes dengan kata kunci mutation/mutasi di `laravel-api/app`, `laravel-api/routes`, dan `vue-app/src` (pencarian 2026-11-01). Satu-satunya jejak adalah kolom sekolah pada membership dan placeholder UI.
- **Implikasi 06c**: riwayat mutasi memerlukan tabel baru; kolom membership saja tidak cukup untuk asal/tujuan, alasan, pembatalan, dan koreksi.

### 2.2 Transfer bukan mutasi keluar

- `assignRombel` (`laravel-api/app/Http/Controllers/StudentRombelController.php:172`) dan `transferRombel` (`:308`) hanya menangani penempatan/perpindahan kelas **dalam tahun yang sama**.
- `transferRombel` menutup membership lama dengan `latest_student_exit_date = today` dan menulis `latest_information = 'Transfer: ...'`, tetapi mempertahankan status `Aktif` dan langsung membuat membership baru di kelas tujuan; ini bukan mutasi keluar dan tidak boleh dipakai sebagai dasar rekap mutasi.
- `StudentRombel` allowlist audit belum memuat `mutation_education_school_id` dan `latest_student_exit_school_id`: `laravel-api/app/Models/StudentRombel.php:26-36`.
- **Implikasi 06c**: mutasi keluar harus menutup **satu** membership terpilih dengan status `Mutasi Keluar`, tanpa membuat membership pengganti; transfer 03d tidak diubah perilakunya.

### 2.3 Arsip administratif bukan mutasi

- `StudentController::destroy` (`laravel-api/app/Http/Controllers/StudentController.php:323`) mengarsipkan siswa dan seluruh membership aktif (`archived_at`, `archive_reason`), lalu soft delete siswa.
- Restore tidak mengaktifkan kembali membership (DEC-21). Arsip tidak boleh dipakai untuk mencatat keluar karena menghilangkan siswa dari populasi aktif dan tidak memiliki sekolah tujuan/tanggal efektif.
- **Implikasi 06c**: mutasi keluar hanya mengubah status/tanggal membership; biodata dan siswa tetap ada. Pembatalan mutasi masuk mengarsipkan membership yang dibuat (bukan menghapus), agar jejak tetap terlihat.

### 2.4 Kelulusan 06b dan koreksinya

- `StudentProgressionService` menetapkan status `Lulus`, `latest_student_exit_date` = `latest_student_graduation_date`, dan `latest_information` referensi batch: `laravel-api/app/Services/Academic/StudentProgressionService.php:392-405`.
- Tidak ada endpoint untuk membatalkan atau mengoreksi kelulusan setelah tahun dikunci.
- **Implikasi 06c**: koreksi `membership_exit`/`membership_reopen` harus dapat memperbaiki kelulusan 06b pada periode terkunci dengan alasan dan audit, tanpa mengubah batch 06b.

### 2.5 Lock dan versi siap dipakai ulang

- Tahun locked diperiksa di dalam transaksi oleh 06a/06b; tahun aktif tidak dapat lock: `laravel-api/app/Models/SchoolYear.php:49-63`, `laravel-api/app/Http/Controllers/SchoolRombelController.php:71-82`.
- `students.version` dan `student_rombels.version` dinaikkan pada mutasi domain; `409` memakai `expected_*_version`.
- `StudentRombel::booted()` menegakkan maksimal satu membership aktif per siswa per tahun dan menolak membership aktif untuk siswa terarsip: `laravel-api/app/Models/StudentRombel.php:63-135`.
- **Implikasi 06c**: semua operasi memakai transaksi, urutan lock, dan versi yang sama; tidak ada mekanisme paralel.

### 2.6 Ability dan route

- Ability siswa: `students.view`, `students.manage`, `students.export`, `students.import`: `laravel-api/app/Support/AbilityCatalog.php:40-43`.
- Admin Sistem dan Operator Sekolah memiliki `students.view`/`students.manage`; Kepala Sekolah hanya `students.view`/`students.export`; Wakil Kepala memiliki `students.view`/`students.manage`: `laravel-api/app/Support/AbilityCatalog.php:81-138`.
- Ability baru sebelumnya ditambahkan lewat migration additive yang hanya mengaitkan `admin-sistem`/`operator-sekolah` (pola 05a/05b).
- Route assign/transfer memakai `students.manage`: `laravel-api/routes/api.php:244-247`. `apiResource('/student-rombels')` masih scaffold (`:261-264`).
- **Implikasi 06c**: baca rekap/riwayat memakai `students.view`; mutasi normal memakai `students.manage`; koreksi periode terkunci memerlukan ability sensitif baru yang diberikan hanya ke Admin Sistem secara default.

### 2.7 Skema yang membatasi rekap

- `semesters` hanya memiliki `name`, `name_other`, soft delete, dan timestamps: `laravel-api/database/migrations/2025_07_05_175126_create_semesters_table.php:14-20`. Tidak ada tanggal mulai/selesai atau relasi tahun ajaran.
- **Implikasi 06c**: rekap yang dapat dipertanggungjawabkan hanya **per tahun ajaran**. Rekap semester ditunda sampai skema semester diperluas dan diputuskan terpisah.

### 2.8 UI dan UAT

- Grup Administrasi pada `Student.vue` sudah memiliki sub-menu `Transfer` dengan id `section-mutation`: `vue-app/src/views/pages/public/Student.vue:658`.
- Section tersebut masih placeholder kosong berjudul `Student Transfers`: `vue-app/src/views/pages/public/Student.vue:2195-2208`.
- Aksi baris Assign/Transfer terkunci bila tahun terpilih locked: `vue-app/src/views/pages/public/Student.vue:1481-1490`; dialog penempatan English dan accessible: `:2864-2925`.
- `.uat-phase06` memiliki 153 siswa, 12 kelas, dan dua tahun ajaran; server dihentikan setelah penerimaan 06b (DEC-27) dan dapat dinyalakan kembali dengan perintah di `PHASE-06B-MANUAL-REVIEW.md`.
- **Implikasi 06c**: section placeholder dapat diimplementasikan tanpa menu baru; fixture UAT harus idempoten dan tidak me-reset data 06a/06b.

---

## 3. Urutan pekerjaan yang diusulkan

| Langkah | Pekerjaan | Hasil yang dapat diverifikasi |
|---|---|---|
| S1 | Migration additive `student_mutations` + model + allowlist audit | Tabel/model ada; tidak mengubah tabel domain 03/06a/06b |
| S2 | Migration additive `student_locked_corrections` + ability `students.correct-locked` | Tabel koreksi immutable; ability hanya terpasang ke `admin-sistem` |
| S3 | `StudentMutationService`: masuk, keluar, pembatalan | Transaksi atomik, versi, lock, audit; nol mutasi saat konflik |
| S4 | `StudentLockedCorrectionService`: lima tindakan koreksi | Hanya periode locked; snapshot before/after; alasan wajib |
| S5 | Rekap per tahun + query riwayat | Empat angka rekap deterministik; cancelled dikecualikan |
| S6 | Controller, request, route, endpoint baca | Route terdaftar; guest/view-only ditolak server |
| S7 | UI section Mutations + dialog + timeline siswa | Alur masuk/keluar/batal/koreksi English; view-only read |
| S8 | Tes backend + regresi 03d/06a/06b + build/ESLint | Matriks §6 PASS; tidak ada regresi |
| S9 | Fixture UAT `prepare-mutations` + checklist review + laporan | UAT siap; laporan dan checklist manual untuk pemilik |

Setelah S9, berhenti untuk review pemilik. Tidak ada merge, deploy, atau lanjutan otomatis.

---

## 4. Keputusan yang perlu pemilik (D1–D10)

Rekomendasi ditulis sebagai default. Setelah disetujui, keputusan dicatat sebagai **DEC-31** sebelum implementasi.

| # | Pertanyaan | Rekomendasi | Alternatif / dampak |
|---|---|---|---|
| **D1** | Apa arti dan syarat mutasi masuk? | **Mutasi masuk** hanya untuk siswa **yang sudah ada dan tidak terarsip**. Tahun dipilih harus ada, tidak soft-deleted, **tidak locked**, dan memiliki `start_date`/`end_date`. Kelas harus milik tahun tersebut dan tidak soft-deleted. Siswa **tidak boleh memiliki membership aktif** di tahun itu. Bila sudah ada membership tertutup di tahun itu (mis. keluar lalu masuk kembali), membership terakhir harus tertutup dengan tanggal keluar ≤ tanggal efektif. Sistem membuat **membership baru**: status `Aktif` (1), entry `Mutasi Masuk` (2), `latest_student_entry_date` = tanggal efektif, `mutation_education_school_id` = NPSN sekolah asal, `version=1`; `students.version` +1; log `in`. **Tidak** membuat siswa baru dan **tidak** mengubah biodata. | Menimpa membership lama (kehilangan riwayat); membuat siswa baru (duplikasi identitas); mengizinkan masuk saat masih aktif (melanggar single-active). |
| **D2** | Apa arti dan syarat mutasi keluar? | **Mutasi keluar** memakai `membership_id` eksplisit; membership harus aktif, milik siswa (`student_id` atau NIK legacy yang menunjuk **tepat satu** siswa), berada di tahun terpilih, tidak archived/deleted. Tahun harus **tidak locked** dan bertanggal. Tanggal efektif wajib, tidak di masa depan (Asia/Jakarta), dalam rentang tahun, dan **≥ tanggal masuk membership**. Hanya membership itu yang ditutup: status `Mutasi Keluar` (2), `latest_student_exit_date` = tanggal efektif, `latest_student_exit_school_id` = NPSN sekolah tujuan, `version` +1; field masuk dipertahankan; `latest_information` **tidak** ditimpa. Membership di tahun lain tidak disentuh. `students.version` +1; log `out`. **Tidak** menghapus biodata/membership. | Menutup semua membership lintas tahun (menghapus riwayat periode lain); memakai arsip administratif (siswa hilang dari populasi); menghapus membership (kehilangan jejak). |
| **D3** | Bagaimana log mutasi dan pembatalannya? | Tabel `student_mutations` **immutable** (tanpa soft delete, tanpa endpoint edit/hapus); status `active`/`cancelled`. Pembatalan memerlukan `students.manage`, alasan 10–255 karakter, dan `expected_student_version`/`expected_membership_version`/`expected_mutation_version`. **Batal keluar**: membership harus masih persis kondisi pasca-mutasi (status 2, exit date dan exit school sama dengan log, versi cocok), tidak archived, dan tidak ada membership aktif lain di tahun itu; sistem mengembalikan status 1, mengosongkan exit date/exit school/graduation, versi +1. **Batal masuk**: membership harus masih persis seperti saat dibuat (versi 1, kelas sama, aktif, entry `Mutasi Masuk`, tanggal masuk dan sekolah asal sama, belum ada mutasi keluar aktif untuk membership itu); sistem mengarsipkan membership (`archived_at`, alasan generik `Cancelled mutation-in`, versi +1), **bukan** menghapus. Log menjadi `cancelled` dengan aktor/waktu/alasan; `students.version` +1. Ketidaksesuaian → `409` itemized, **nol mutasi**. Mutasi yang pernah dikoreksi pada periode locked hanya dapat dibatalkan oleh pemegang ability koreksi. | Menghapus log (kehilangan audit); hard delete membership (riwayat hilang); membatalkan tanpa memeriksa state (dapat mengaktifkan ganda). |
| **D4** | Aturan tanggal dan sekolah? | Tahun wajib memiliki `start_date`/`end_date` untuk semua operasi mutasi/rekap; bila null → `422` dengan field yang jelas. Tanggal efektif wajib, **tidak boleh melewati hari ini** (Asia/Jakarta), dan berada di rentang tahun. Untuk keluar, tanggal ≥ tanggal masuk membership. Sekolah asal/tujuan wajib berupa NPSN yang **ada dan tidak soft-deleted**; `status_education` tidak difilter karena nilainya string bebas yang belum distandarkan. Pencarian sekolah memakai endpoint read-only; **tidak menerima nama sekolah bebas** sebagai kunci. | Menerima nama sekolah bebas (salah ketik/duplikat); menurunkan tanggal dari nama tahun (tebakan); mengizinkan tanggal masa depan (rekap tidak stabil). |
| **D5** | Bagaimana definisi rekap? | **Rekap per tahun ajaran saja.** `mutation_in` = jumlah log `in` berstatus `active` dengan tanggal efektif dalam rentang tahun; `mutation_out` = jumlah log `out` aktif dalam rentang; `graduated` = jumlah **siswa unik** dengan membership non-deleted berstatus `Lulus` (6) dan `latest_student_graduation_date` dalam rentang; `retained` = jumlah siswa unik pada **batch committed terbaru per siswa** untuk tahun sumber yang memiliki keputusan `retain`. Mutasi `cancelled` tidak dihitung. Pembaca `students.view`; respons tanpa PII. | Menghitung semua baris termasuk cancelled (angka salah); memakai `latest_information` (teks bebas); rekap semester (butuh skema baru). |
| **D6** | Bagaimana koreksi periode terkunci? | Ability baru **`students.correct-locked`** (sensitif), migration additive, awalnya hanya `admin-sistem`. Route koreksi memerlukan **`students.manage` DAN `students.correct-locked`**. Koreksi **hanya** untuk tahun locked; endpoint mutasi normal menolak tahun locked, endpoint koreksi menolak tahun unlocked. Lima tindakan: `mutation_in`/`mutation_out` (koreksi tanggal efektif dan/atau NPSN sekolah), `mutation_cancel`, `membership_exit` (menetapkan `Mutasi Keluar` atau `Lulus`; untuk `Lulus`, graduation date = tanggal efektif), `membership_reopen` (mengosongkan exit date/exit school/graduation dan hanya bila tidak ada membership aktif lain di tahun itu). Alasan 10–255 wajib; `approval_reference` opsional ≤100. Setiap tindakan menulis baris immutable `student_locked_corrections` berisi before/after (ID, status, tanggal, versi saja). Tidak ada edit/hapus inline. Koreksi kelulusan 06b memakai `membership_exit`/`membership_reopen`; batch 06b tidak diubah. | Tidak memakai ability khusus (operator biasa dapat mengubah periode tertutup); mengizinkan edit langsung tanpa log; membuka kembali tahun (mengubah status lock). |
| **D7** | Transaksi, lock, dan versi? | Semua operasi dalam `DB::transaction`, lock berurutan naik: `school_years` → `students` → `student_rombels` → `school_rombels` → `student_mutations`, lalu insert koreksi terakhir. Validasi ulang lock, tanggal, kelas, kepemilikan membership, dan state mutasi **di dalam** transaksi. `409` itemized dengan versi terkini; nol mutasi. Tidak memakai token: operasi tunggal; duplikasi dicegah oleh state + versi (percobaan kedua melihat membership sudah aktif/tertutup → `409`). Kegagalan audit membatalkan seluruh transaksi. | Token per operasi (kompleks tanpa manfaat untuk satu baris); tanpa versi (last-write-wins); lock tidak berurutan (risiko deadlock). |
| **D8** | Endpoint dan UI? | Endpoint baca `students.view`; tulis `students.manage`; koreksi `students.manage` + `students.correct-locked`. UI memakai section **Students → Administration → Mutations** yang sudah ada (ganti placeholder `Student Transfers`): kartu rekap tahun, filter tipe/status/pencarian, tabel riwayat, dialog masuk/keluar/batal, dialog koreksi (hanya pemegang ability), dan timeline per siswa. View-only dapat membaca; tombol aksi disembunyikan dan server tetap `403`. English, 390 px, keyboard accessible. | Menu baru terpisah (duplikasi navigasi); hanya dialog per siswa tanpa rekap (tidak memenuhi scope rekap). |
| **D9** | Audit dan integritas data? | `StudentMutation` memakai `Loggable` dengan allowlist: `student_id`, `school_year_id`, `type`, `student_rombel_id`, `school_rombel_id`, `effective_date`, `school_npsn`, `status`, `version`. Alasan bebas teks **tidak** diduplikasi ke `activities`; tersimpan di log domain. Tambahkan `mutation_education_school_id` dan `latest_student_exit_school_id` ke allowlist `StudentRombel`. `student_locked_corrections` bersifat self-auditing/immutable (tanpa `Loggable` ganda). Tidak ada NIK/nama/alamat di payload audit baru. Tahun ajaran yang memiliki mutasi tidak dapat dihapus (guard pola 06b). | Mengaudit payload penuh (PII); menyimpan alasan di dua tempat (duplikasi); mengizinkan hapus tahun (riwayat yatim). |
| **D10** | UAT dan non-goals? | Pakai ulang **`.uat-phase06`** (SQLite, API `127.0.0.1:18024`, SPA `127.0.0.1:14024`, cookie `school_phase06_session`) dan tambahkan perintah idempotent **`prepare-mutations`**: tidak me-reset data 06a/06b, menambahkan ≥120 siswa sintetis `UAT-P06C`, ≥60 log mutasi (aktif/cancelled, masuk/keluar, dua tahun), sekolah sintetis dengan NPSN valid, serta skenario tahun locked untuk koreksi. Tidak menyentuh `.uat-phase05` atau data operasional. Server dihentikan setelah penerimaan (DEC-27); data dipertahankan. Non-goals: rekap semester, pembuatan siswa via mutasi, undo batch 06b, hapus log, validasi kapasitas, ekspor, Dapodik, migrasi DB operasional, merge/deploy, perubahan dependency/service/`.env`. | Runtime baru `.uat-phase07` (data 06a/06b harus dibuat ulang); fixture <100 baris (melanggar kebijakan data sintetis); mengaktifkan kembali tahun aktif untuk koreksi (melanggar aturan lock). |

**Jawaban pertanyaan terbuka 06c (Q1–Q5):**

| Q | Jawaban yang diusulkan |
|---|---|
| Q1 — apakah mutasi masuk membuat membership baru? | Ya; satu membership baru per masuk, status `Aktif`, entry `Mutasi Masuk` (D1). |
| Q2 — apakah pembatalan memulihkan state otomatis? | Ya, kompensatif dan deterministik bila state masih cocok; bila tidak cocok → `409` tanpa mutasi (D3). |
| Q3 — siapa yang boleh mengoreksi periode terkunci? | Hanya pemegang `students.manage` + `students.correct-locked`; default hanya Admin Sistem (D6). |
| Q4 — granularitas rekap? | Per tahun ajaran; semester ditunda karena `semesters` tanpa tanggal (D5/§2.7). |
| Q5 — apakah mutasi menyentuh tahun lain? | Tidak; hanya membership pada tahun terpilih. Tahun lain tetap dan tidak dihitung ganda (D2/D5). |

---

## 5. Desain teknis

### 5.1 Skema `student_mutations` (migration additive)

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | — |
| `version` | unsigned bigint, default 1 | Naik saat koreksi/pembatalan |
| `type` | string(10) | `in` atau `out` |
| `school_year_id` | FK `school_years` | Tahun operasi; restrict delete |
| `student_id` | FK `students` | Siswa pemilik; restrict delete (siswa memakai soft delete) |
| `student_rombel_id` | FK `student_rombels` nullable | Membership yang dibuat (in) atau ditutup (out) |
| `school_rombel_id` | FK `school_rombels` | Snapshot kelas saat mutasi |
| `effective_date` | date | Tanggal masuk/keluar |
| `school_npsn` | string(20) | NPSN asal (in) atau tujuan (out); snapshot tervalidasi |
| `reason` | string(255) | Alasan operator; tidak masuk `activities` |
| `status` | string(10), default `active` | `active` atau `cancelled` |
| `cancelled_at` | timestamp nullable | — |
| `cancelled_by` | FK `users` nullable | — |
| `cancel_reason` | string(255) nullable | — |
| `created_by` | FK `users` | Aktor pembuat |
| `timestamps` | — | — |

- Index: `(student_id, effective_date)`, `(school_year_id, type, status)`, `(student_rombel_id)`, `(status)`.
- Tidak ada soft delete dan tidak ada unique index yang menghalangi re-entry; aturan “satu mutasi aktif per membership per tipe” ditegakkan di transaksi.
- FK `student_rombel_id` memakai `restrictOnDelete`; membership tidak pernah di-hard-delete oleh aplikasi.

### 5.2 Skema `student_locked_corrections` (migration additive)

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | — |
| `school_year_id` | FK `school_years` | Tahun locked yang dikoreksi |
| `student_id` | FK `students` | — |
| `action` | string(30) | `mutation_in`, `mutation_out`, `mutation_cancel`, `membership_exit`, `membership_reopen` |
| `target_type` | string(20) | `mutation` atau `membership` |
| `target_id` | unsigned bigint | ID target |
| `mutation_id` | FK `student_mutations` nullable | Diisi untuk aksi mutasi |
| `student_rombel_id` | FK `student_rombels` nullable | Diisi untuk aksi membership |
| `before` | json | Snapshot terbatas (ID, status, tanggal, versi) |
| `after` | json | Snapshot terbatas |
| `reason` | string(255) | Wajib 10–255 |
| `approval_reference` | string(100) nullable | Referensi persetujuan internal |
| `created_by` | FK `users` | — |
| `timestamps` | — | — |

- Index: `(school_year_id, student_id)`, `(mutation_id)`, `(student_rombel_id)`, `(action)`.
- Immutable: tidak ada endpoint update/delete; model menolak `updating`/`deleting` (kecuali tidak ada perubahan).
- `before`/`after` **tidak** memuat nama, NIK, alamat, atau alasan.

### 5.3 Ability `students.correct-locked`

- Tambah definisi di `laravel-api/app/Support/AbilityCatalog.php` (sensitif, `true`).
- Migration additive baru (mis. `2029_08_01_000001_add_students_correct_locked_ability.php`) mengaitkan ability **hanya** ke role `admin-sistem`; tidak mengubah role/ability lain.
- Route koreksi memakai middleware `can:students.manage` dan `can:students.correct-locked` (dua middleware), sehingga Operator/Wakil Kepala tetap tidak dapat mengoreksi locked period tanpa keputusan pemilik.
- Bila pemilik ingin Operator juga, cukup ubah migration sebelum dijalankan; jangan menebak.

### 5.4 Endpoint (usulan)

| Method | Endpoint | Ability | Perilaku |
|---|---|---|---|
| GET | `/api/academic/mutations/options?school_year_id=` | `students.view` | Tahun (tanggal, lock, active), kelas per tahun, status/entry yang valid |
| GET | `/api/academic/mutations/schools?search=` | `students.view` | Cari `education_schools` aktif (NPSN, nama, level); maksimum 20 |
| GET | `/api/academic/mutations?school_year_id=&type=&status=&search=&page=` | `students.view` | Riwayat mutasi per tahun; tanpa NIK |
| GET | `/api/academic/mutations/recap?school_year_id=` | `students.view` | Empat angka rekap + periode + status lock |
| GET | `/api/students/{id}/mutations` | `students.view` | Timeline mutasi + koreksi siswa; tanpa NIK |
| GET | `/api/students/{id}/locked-corrections` | `students.view` | Riwayat koreksi siswa (read-only) |
| POST | `/api/students/{id}/mutations/in` | `students.manage` | Buat membership + log `in` |
| POST | `/api/students/{id}/mutations/out` | `students.manage` | Tutup satu membership + log `out` |
| POST | `/api/students/{id}/mutations/{mutation}/cancel` | `students.manage` (+ `students.correct-locked` bila locked/correction-origin) | Pembatalan kompensatif |
| POST | `/api/students/{id}/locked-corrections` | `students.manage` + `students.correct-locked` | Lima tindakan koreksi periode locked |

Request masuk (ringkas):

```json
{
  "school_year_id": 1,
  "school_rombel_id": 7,
  "effective_date": "2026-08-01",
  "school_npsn": "12345678",
  "reason": "Transfer from another school",
  "expected_student_version": 3
}
```

Request keluar:

```json
{
  "student_rombel_id": 55,
  "effective_date": "2026-11-01",
  "school_npsn": "87654321",
  "reason": "Family relocation",
  "expected_student_version": 3,
  "expected_membership_version": 1
}
```

Request batal:

```json
{
  "reason": "Cancelled because the transfer was withdrawn",
  "expected_student_version": 4,
  "expected_membership_version": 2,
  "expected_mutation_version": 1
}
```

Request koreksi locked:

```json
{
  "action": "mutation_out",
  "school_year_id": 2,
  "mutation_id": 12,
  "effective_date": "2027-10-15",
  "school_npsn": "87654321",
  "reason": "Correcting a data-entry date after year close",
  "approval_reference": "PRINCIPAL-2027-014",
  "expected_student_version": 5,
  "expected_membership_version": 3,
  "expected_mutation_version": 1
}
```

- `{mutation}` harus milik `{id}`; selain itu `404`.
- Server tidak menerima nama/NIK sebagai kunci; identitas memakai `student_id`, `student_rombel_id`, dan `mutation_id`.
- Respons sukses menyertakan versi baru dan ringkasan state; respons `409` menyertakan `current_version`/state aktual tanpa PII.

### 5.5 Aturan operasi

| Operasi | Pemeriksaan | Hasil gagal |
|---|---|---|
| Masuk | Tahun ada/tidak deleted/tidak locked/bertanggal; siswa ada/tidak archived; kelas milik tahun/tidak deleted; tidak ada membership aktif di tahun; re-entry hanya bila membership terakhir tertutup dan exit ≤ tanggal efektif; NPSN valid; tanggal valid; versi siswa cocok | `422`/`409`; nol mutasi |
| Keluar | Tahun ada/tidak deleted/tidak locked/bertanggal; membership aktif/milik siswa/tahun benar/tidak archived; NPSN valid; tanggal ≥ entry dan dalam rentang; versi siswa+membership cocok | `422`/`409`; nol mutasi |
| Batal keluar | Log `active`; membership cocok dengan state pasca-mutasi; tidak ada membership aktif lain di tahun; versi ketiga objek cocok | `409` itemized; nol mutasi |
| Batal masuk | Log `active`; membership masih versi 1, kelas/entry/tanggal/sekolah sama, aktif, tidak archived; tidak ada log `out` aktif untuk membership itu; versi cocok | `409` itemized; nol mutasi |
| Koreksi | Tahun **locked**; ability lengkap; alasan 10–255; target milik siswa/tahun; state/versi cocok; `membership_exit` hanya status 2/6 (Lulus mengisi graduation date); `membership_reopen` mengosongkan exit/graduation dan tanpa membership aktif lain; tidak ada mutasi aktif untuk aksi langsung membership | `403`/`422`/`409`; nol mutasi |

### 5.6 Transaksi dan urutan lock

1. Validasi request di FormRequest (tipe, ukuran, format tanggal, `reason` 10–255).
2. `DB::transaction`:
   - Lock baris `school_years` terpilih (dan tahun terkait bila ada) berurutan naik; validasi lock/tanggal.
   - Lock baris `students` berurutan naik; validasi archived/versi.
   - Lock baris `student_rombels` target berurutan naik; validasi state/versi/tahun/ownership.
   - Lock baris `school_rombels` target berurutan naik; validasi tahun/tidak deleted.
   - Lock baris `student_mutations` target berurutan naik; validasi status/versi.
   - Terapkan perubahan (membership, versi siswa, log, koreksi) berurutan.
   - Insert `student_locked_corrections` terakhir bila aksi koreksi.
3. Commit DB. Kegagalan audit/exception → rollback penuh; tidak ada log/versi yang tertinggal.
4. Pembatalan dan koreksi memakai transaksi yang sama; tidak ada operasi parsial.

### 5.7 Audit

- `StudentMutation` memakai `Loggable`; allowlist tanpa `reason`/`cancel_reason` dan tanpa NIK.
- `StudentRombel` allowlist ditambah `mutation_education_school_id` dan `latest_student_exit_school_id`; `latest_information` tetap tidak diaudit.
- `student_locked_corrections` adalah log domain immutable; tidak memakai `Loggable` agar snapshot before/after tidak diduplikasi ke `activities`.
- Setiap perubahan siswa/membership tetap diaudit oleh model masing-masing; kegagalan insert audit membatalkan transaksi (diuji).
- Tidak ada NIK, nama, alamat, atau alasan bebas teks di payload audit baru.

### 5.8 UI (English)

Section yang sudah ada di **Students → Administration → Mutations** (mengganti placeholder `Student Transfers`):

1. **Header**: pemilih tahun ajaran (default periode aktif), badge lock, tombol **New Mutation In** (dari siswa terpilih) dan **New Mutation Out**.
2. **Recap cards**: Mutation In, Mutation Out, Graduated, Retained; angka dari endpoint rekap; catatan “year-level recap”.
3. **Filters**: tahun, tipe (`in`/`out`), status (`active`/`cancelled`), pencarian nama/NIS; pagination server.
4. **History table**: tanggal efektif, siswa (nama/NIS), kelas, tipe, sekolah (NPSN + nama), alasan, status, aktor, aksi **Cancel** (bila diizinkan dan state cocok) dan **Correct** (hanya bila tahun locked dan ability tersedia).
5. **Dialog masuk**: tahun, kelas, tanggal, sekolah asal (autocomplete NPSN), alasan; menampilkan peringatan bila siswa masih aktif di tahun itu.
6. **Dialog keluar**: membership/kelas aktif, tanggal, sekolah tujuan, alasan; peringatan bila tahun locked.
7. **Dialog batal**: ringkasan mutasi dan konsekuensi (restore membership vs archive membership), alasan wajib.
8. **Dialog koreksi**: hanya muncul untuk pemegang ability; action, field relevan, alasan, approval reference, konfirmasi; menjelaskan bahwa tindakan dicatat permanen.
9. **Timeline siswa**: dialog dari aksi baris **Mutations** di tabel Students; menampilkan masuk/keluar/batal/koreksi berurutan.

Ketentuan: seluruh label/pesan English; tombol hanya untuk ability yang sesuai; view-only melihat rekap/riwayat tanpa aksi; layout 390 px tanpa overflow; dialog dapat dioperasikan dengan keyboard; `409` menampilkan versi terbaru dan tombol **Reload**.

---

## 6. Matriks tes

Backend (Pest, SQLite in-memory; MariaDB tetap NOT RUN):

| # | Skenario | Ekspektasi |
|---|---|---|
| T1 | Guest / `uat.kepsek` membaca dan menulis endpoint mutasi/koreksi | `401`/`403`; tidak ada mutasi |
| T2 | Mutasi masuk sukses | Membership baru status 1, entry 2, entry date, NPSN asal, versi 1; siswa versi +1; log `in`; audit |
| T3 | Mutasi masuk ditolak: siswa archived, sudah aktif di tahun, kelas tahun lain/deleted, tahun locked/tanpa tanggal, tanggal masa depan/di luar rentang, NPSN invalid, versi basi | `422`/`409`; nol mutasi |
| T4 | Re-entry setelah keluar | Diizinkan bila exit ≤ tanggal efektif; ditolak bila membership terakhir masih aktif atau exit > tanggal |
| T5 | Mutasi keluar sukses | Hanya membership terpilih ditutup (status 2, exit date, exit NPSN, versi +1); field masuk tetap; `latest_information` tidak berubah; membership tahun lain (decoy) tidak berubah; siswa versi +1; audit |
| T6 | Mutasi keluar ditolak: membership tidak aktif/archived/salah siswa/tahun lain, tanggal < entry, tanggal masa depan/di luar rentang, NPSN invalid, legacy ambigu, versi basi | `422`/`409`; nol mutasi |
| T7 | Batal keluar sukses | Status 1, exit date/exit NPSN/graduation null, versi +1; log cancelled dengan aktor/waktu/alasan; siswa versi +1; audit |
| T8 | Batal keluar konflik: membership berubah, ada membership aktif lain, log sudah cancelled, versi basi | `409` itemized; nol mutasi |
| T9 | Batal masuk sukses | Membership diarsipkan (bukan dihapus), `archive_reason` generik, versi +1; log cancelled; siswa versi +1; audit |
| T10 | Batal masuk konflik: kelas/versi berubah, membership tertutup, ada log `out` aktif, versi basi | `409` itemized; nol mutasi |
| T11 | Tahun locked: masuk/keluar/batal normal; koreksi tanpa ability; koreksi dengan ability; koreksi tahun unlocked | Normal `422`/`409`; tanpa ability `403`; dengan ability sukses; unlocked ditolak `422` |
| T12 | Koreksi `mutation_in`/`mutation_out` | Tanggal/NPSN log dan tanggal membership terkait berubah konsisten; versi naik; snapshot before/after benar; alasan wajib |
| T13 | Koreksi `membership_exit`/`membership_reopen` | Hanya status 2/6; reopen ditolak bila ada membership aktif lain; tidak boleh saat ada mutasi aktif untuk membership itu; audit |
| T14 | Rekap | In/out hanya log aktif dalam rentang; cancelled dikecualikan; graduated distinct berdasarkan status 6 + tanggal; retained dari batch committed terbaru per siswa; tahun tanpa tanggal `422` |
| T15 | Kegagalan audit di tengah transaksi masuk/keluar/batal/koreksi | Rollback penuh; tidak ada log/versi tertinggal |
| T16 | Privasi audit | `activities` tidak memuat NIK/nama/alamat/alasan; NPSN boleh; `latest_information` tidak diaudit |
| T17 | Guard hapus tahun | Tahun dengan mutasi tidak dapat dihapus (soft delete) |
| T18 | Duplikasi/idempotensi | Percobaan masuk kedua `409` tanpa membership ganda; batal kedua `409`; tidak ada token |
| T19 | Regresi 03d/06a/06b + suite penuh | Tidak ada regresi |
| T20 | `route:list` | Sepuluh route baru terdaftar dengan middleware yang benar |

Frontend/UX: `npm run build`, `npx eslint --quiet` pada file berubah; browser DOM untuk rekap, filter, dialog masuk/keluar, batal, koreksi, view-only, 409, dan 390 px; tidak ada error console.

---

## 7. UAT

- Lingkungan: `.uat-phase06` (SQLite), API `127.0.0.1:18024`, SPA `127.0.0.1:14024`, cookie `school_phase06_session`, launcher `laravel-api/scripts/phase06-uat.php`.
- Perintah baru **`prepare-mutations`** (idempotent; tidak me-reset data 06a/06b):
  - ≥120 siswa sintetis `UAT-P06C` (sebagian aktif 2026/2027, sebagian tanpa membership, sebagian sudah keluar, sebagian archived).
  - ≥60 log mutasi sintetis: masuk/keluar, aktif/cancelled, tersebar di 2026/2027 dan 2027/2028.
  - ≥20 sekolah sintetis dengan NPSN 8 digit unik dan nama `UAT-P06C School ...`.
  - Satu tahun **locked** untuk skenario koreksi (tahun non-aktif; tahun aktif 2026/2027 tetap unlocked), dengan minimal satu mutasi dan satu kelulusan untuk dikoreksi.
  - Tidak menyentuh `.uat-phase05`, data operasional, atau membuat ulang data 06a/06b.
- Skenario manual ditulis di `PHASE-06C-MANUAL-REVIEW.md` setelah implementasi; cakupan: isolasi, izin, mutasi masuk/keluar, re-entry, batal, koreksi locked, rekap, audit, guard tahun, English/390 px, dan penghentian server setelah penerimaan.
- Server dinyalakan untuk review dan **dihentikan setelah penerimaan** (DEC-27); `.uat-phase06` dipertahankan; perintah restart dicatat.

---

## 8. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Data lama memiliki >1 membership aktif per siswa/tahun | Mutasi masuk/keluar menolak state ambigu; tidak memilih baris pertama; koreksi tersedia untuk periode locked |
| Legacy `student_id` null / NIK ambigu | Resolusi unik saja (DEC-20); ambigu ditolak `409`/`422`, tidak diatribusikan |
| Trigger MySQL menolak transisi status | Urutan update menjaga satu membership aktif; diuji di SQLite; MariaDB NOT RUN |
| Pembatalan mengaktifkan ganda | Cek membership aktif lain di tahun yang sama di dalam transaksi; `409` |
| Re-entry membuat dua membership tertutup/aktif | Aturan kronologi (exit ≤ tanggal efektif) dan single-active |
| Koreksi locked disalahgunakan | Ability sensitif hanya Admin Sistem; alasan + approval reference; log immutable |
| Rekap `retained` mahal dari JSON batch | Hanya batch committed tahun terpilih; keputusan diproses per batch; skala saat ini kecil; benchmark tidak dijalankan |
| Rekap semester diminta | Dinyatakan ditunda; `semesters` tanpa tanggal (D5) |
| Tahun dihapus menghilangkan riwayat | Guard hapus tahun bila ada mutasi/koreksi |
| Mutasi keluar disamakan dengan transfer 03d | UI dan laporan menjelaskan perbedaan; transfer tidak diubah |
| Audit membocorkan PII | Allowlist ketat; alasan di log domain; tes privasi T16 |

---

## 9. Non-goals dan batas otorisasi

- Tidak ada implementasi sebelum D1–D10 disetujui dan dicatat sebagai **DEC-31**.
- Tidak ada merge/deploy/force-push/rewrite, migrasi database operasional/MariaDB, perubahan `.env` utama, dependency/lockfile, service, atau akses DB terlarang.
- Tidak ada perubahan pada data `.uat-phase05` atau data operasional; UAT hanya `.uat-phase06`.
- Tidak ada rekap semester, pembuatan siswa baru via mutasi, undo batch 06b, penghapusan log, validasi kapasitas, ekspor, atau integrasi Dapodik.
- Rekap sekolah (DEC-12) tetap PROPOSED dan di luar scope.
- Setelah 06c diterima, pekerjaan berikutnya memerlukan instruksi/rencana terpisah.

---

## 10. Ringkasan keputusan untuk pemilik

| Kode | Ringkasan |
|---|---|
| D1 | Mutasi masuk = membership baru (status Aktif, entry Mutasi Masuk, tanggal+NPSN asal) untuk siswa existing non-archived |
| D2 | Mutasi keluar = tutup satu membership (status Mutasi Keluar, tanggal+NPSN tujuan), tanpa menghapus biodata/tahun lain |
| D3 | Log immutable; batal kompensatif dengan alasan + versi; masuk dibatalkan dengan arsip membership, bukan hapus |
| D4 | Tahun wajib bertanggal; tanggal efektif valid/tidak masa depan; sekolah wajib NPSN aktif |
| D5 | Rekap per tahun: mutation_in, mutation_out, graduated, retained; cancelled dikecualikan; semester ditunda |
| D6 | Koreksi locked hanya dengan `students.manage` + `students.correct-locked`; lima tindakan; log immutable |
| D7 | Transaksi + lock berurutan + expected versions + `409` itemized; tanpa token |
| D8 | Endpoint baca `students.view`, tulis `students.manage`; section Students → Mutations; English/390 px |
| D9 | Audit allowlist tanpa PII/alasan; NPSN membership ditambahkan; guard hapus tahun |
| D10 | Pakai ulang `.uat-phase06` + `prepare-mutations`; server dihentikan setelah penerimaan |

Bila pemilik menyetujui seluruhnya, implementasi 06c diotorisasi dan akan dikerjakan setelah keputusan dicatat sebagai **DEC-31**. Bila ada yang diubah, rencana dan matriks tes disesuaikan sebelum implementasi.

---

## 11. Prompt setelah subfase ini disetujui

```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-06-ACADEMIC-YEAR.md.
Kerjakan subfase 06c saja: mutasi masuk/keluar, pembatalan, rekap per tahun,
dan koreksi periode terkunci sesuai PHASE-06C-PLAN.md (DEC-31).
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan yang belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini tidak memberi izin
merge/deploy atau mutasi data operasional; commit+push paket review mengikuti
aturan repo. Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan
dan catat hasil aktual. Jika browser tidak tersedia, jelaskan dan berikan
checklist manual. Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX,
HANDOFF dan ROADMAP. Berhenti setelah hasil subfase siap review; jangan lanjut
subfase berikutnya.
```
