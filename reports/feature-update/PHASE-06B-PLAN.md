# Rencana Subfase 06b — Naik Kelas, Tinggal Kelas, dan Lulus

- Tanggal: 2026-10-31 (WIB). Branch: `main`. Baseline: `1799cf7` (penerimaan 06a #1–#12; implementasi 06a `d8d9874`).
- Status dokumen: **PLAN APPROVED — pemilik menyatakan "Saya setuju dieksekusi" pada 2026-10-31 WIB; dicatat sebagai DEC-30 APPROVED (D1–D9).** Implementasi 06b **IMPLEMENTED & agent-verified 2026-10-31**; review manual #1–#14 PENDING. Bukti dan checklist ada di `PHASE-06B-REPORT.md` dan `PHASE-06B-MANUAL-REVIEW.md`.
- Cakupan: **06b saja** — proses akhir tahun ajaran: `promote` (naik kelas), `retain` (tinggal kelas), `graduate` (lulus), `skip` (tidak diproses), dengan **preview tanpa mutasi** dan **commit atomik ber-token**.
- Di luar cakupan: 06c (mutasi masuk/keluar, pembatalan, koreksi periode terkunci), CRUD semester, rekap sekolah (DEC-12), merge/deploy, migrasi database operasional/MariaDB, perubahan dependency/service/`.env`.
- Dokumen acuan: `reports/feature-update/PHASE-06-PLAN.md` §6 (outline 06b, Q1–Q6), `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `docs/DECISIONS.md` (DEC-05, DEC-20, DEC-21, DEC-23, DEC-27, DEC-29), `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `docs/DATABASE.md`, `docs/TESTING.md`.
- Gate: 06a **OWNER APPROVED 2026-10-31 WIB (#1–#12)**. **D1–D9 disetujui pemilik 2026-10-31 WIB ("Saya setuju dieksekusi") sebagai DEC-30 APPROVED**; implementasi 06b selesai dan agent-verified. Persetujuan ini bukan merge/deploy dan bukan izin migrasi database operasional.

---

## 1. Posisi dan prasyarat

| Prasyarat | Kondisi aktual 2026-10-31 | Status |
|---|---|---|
| 06a periode aktif, lock, dan CRUD kelas | Implemented, agent-verified, owner approved; `d8d9874`; UAT 06a dihentikan, data `.uat-phase06` dipertahankan | Terpenuhi |
| `school_years.start_date`/`end_date` | Ada (06a), nullable, diisi operator; `.uat-phase06` berisi 2026-07-01…2027-06-30 dan 2027-07-01…2028-06-30 | Terpenuhi |
| Lock tahun | Ada (06a); tahun aktif tidak dapat lock; tahun locked menolak CRUD kelas dan assign/transfer | Terpenuhi; 06b perlu aturan lock sendiri (D4) |
| Tahun sumber dan tujuan | `.uat-phase06` memiliki 2026/2027 dan 2027/2028 | Terpenuhi untuk UAT |
| Kelas sumber dan tujuan | 12 kelas (6 per tahun) pada `.uat-phase06` | Terpenuhi |
| Siswa aktif di tahun sumber | 73 keanggotaan aktif pada 2026/2027 (133 siswa total, 0 archived) | Terpenuhi |
| Batas batch | Belum ada; diusulkan 500 (D5) | Perlu keputusan |
| Idempotensi/commit token | Belum ada | Perlu keputusan D5 |
| Ability baru | Tidak diperlukan; `students.view`/`students.manage` sudah tersedia | Terpenuhi |

Catatan: angka UAT di atas dibaca langsung dari `.uat-phase06/database.sqlite` (read-only) pada 2026-10-31; bukan data operasional.

---

## 2. Temuan audit (fakta kode)

### 2.1 Status siswa hidup pada keanggotaan rombel, bukan pada `students`

- Tabel `students` **tidak memiliki** kolom `student_status_id`, `student_entry_id`, tanggal masuk/keluar, atau tanggal lulus; kolom tersebut dikomentari pada migration: `laravel-api/database/migrations/2029_07_06_215144_create_students_table.php:58-66`.
- `student_rombels` memuat `student_status_id`, `student_entry_id`, `latest_student_entry_date`, `latest_student_exit_date`, `latest_student_graduation_date`, `latest_information`, dan `version`: `laravel-api/database/migrations/2029_07_10_070707_create_student_rombels_table.php:14-41`.
- Kolom arsip `archived_at`/`archive_reason` ditambahkan pada 03b: `laravel-api/database/migrations/2029_07_23_000002_add_archive_columns_to_students_and_student_rombels.php:22-52`.
- Status seed: `Aktif`, `Mutasi Keluar`, `Drop Out`, `Mengundurkan Diri`, `Meninggal Dunia`, `Lulus`: `laravel-api/database/seeders/StudentStatusSeeder.php:17-34`. Entry seed: `Siswa Baru`, `Mutasi Masuk`: `laravel-api/database/seeders/StudentEntrySeeder.php:17-22`.
- **Implikasi 06b**: `graduate` mengubah baris keanggotaan sumber (status `Lulus` + tanggal keluar/lulus), bukan tabel `students`; `promote` membuat baris keanggotaan baru di tahun tujuan.

### 2.2 Definisi “aktif” dan single-active sudah konsisten

- Keanggotaan aktif = `deleted_at` null, `archived_at` null, `student_status_id` = 1/null, dan `latest_student_exit_date` null: `laravel-api/app/Support/StudentDirectory.php:28-58`; `laravel-api/app/Http/Controllers/StudentController.php:96-119`.
- Aturan maksimal satu keanggotaan aktif per siswa per tahun ditegakkan di model (`StudentRombel::booted()`) `laravel-api/app/Models/StudentRombel.php:61-133` dan trigger MySQL 03b untuk insert/update.
- Assign/transfer 03d memakai transaksi, row lock tahun lebih dulu, versi siswa/membership, dan audit: `laravel-api/app/Http/Controllers/StudentRombelController.php:172-303` dan `:308-452`.
- **Implikasi 06b**: `promote` tidak melanggar single-active karena baris baru berada di tahun berbeda; `graduate` mengubah status + tanggal keluar sehingga baris tidak lagi dihitung aktif. `retain`/`skip` tidak menyentuh baris sumber.

### 2.3 Belum ada fondasi promosi/kelulusan

- Tidak ada tabel batch/token, service, endpoint, request, policy, halaman, menu, atau tes dengan kata kunci promote/graduate/progression di `laravel-api/app`, `laravel-api/routes`, dan `vue-app/src` (pencarian 2026-10-31; hasil hanya tanggal kelulusan pendidikan pegawai, bukan progresi siswa).
- `StudentRombelController` hanya memiliki assign/transfer; `update`/`destroy` masih kosong (`laravel-api/app/Http/Controllers/StudentRombelController.php:156-167`). Tidak ada API untuk menutup keanggotaan dengan status `Lulus`.
- `StudentRombel` belum mengaudit `latest_student_exit_date` dan `latest_student_graduation_date`: allowlist `laravel-api/app/Models/StudentRombel.php:26-34`.
- `school_levels` hanya `id`+`name` (unique) tanpa urutan/level berikutnya: `laravel-api/database/migrations/2025_07_06_221826_create_school_levels_table.php:14-16`; `student_majors` juga hanya nama. **Kenaikan tingkat tidak dapat diturunkan otomatis**; target kelas harus dipilih operator.
- **Implikasi 06b**: seluruh alur (preview, token, commit, UI, tes) adalah pekerjaan baru; tidak ada perilaku lama yang boleh diubah.

### 2.4 Versi, audit, dan lock yang dapat dipakai ulang

- `students.version` dinaikkan pada update/assign/transfer/arsip; `student_rombels.version` dinaikkan pada perubahan membership (03d).
- `Loggable` mencatat create/update/delete dengan allowlist per model: `laravel-api/app/Traits/Loggable.php:25-118`.
- Lock tahun 06a tersedia dan diperiksa di dalam transaksi (`SchoolYear::locked_at`): `laravel-api/app/Models/SchoolYear.php:49-63`; `laravel-api/app/Http/Controllers/SchoolRombelController.php:71-82`, `:155-196`, `:271-295`.
- **Implikasi 06b**: commit memakai pola yang sama (transaksi, row lock berurutan, versi, audit), bukan mekanisme baru yang paralel.

### 2.5 UAT 06a (terisolasi) siap menjadi basis 06b

- `.uat-phase06` berisi 133 siswa, 0 archived; 73 keanggotaan aktif di 2026/2027 (X-1 15, X-2 12, XI-1 12, XI-2 12, XII-1 11, XII-2 11); 30 keanggotaan aktif di 2027/2028.
- Tahun 2027/2028 saat ini **locked** (alasan uji 06a); tahun 2026/2027 unlocked; periode aktif 2026/2027 · Ganjil.
- Server UAT 06a sudah dihentikan setelah penerimaan (DEC-27); data dan password runtime dipertahankan.
- **Temuan tambahan (pre-existing)**: kartu statistik halaman Students menghitung `counts[4]` sebagai Graduated (`vue-app/src/views/pages/public/Student.vue:242`), padahal seed status `Lulus` adalah id **6** (`laravel-api/database/seeders/StudentStatusSeeder.php:32-34`); akibatnya angka “Graduated” saat ini menghitung `Mengundurkan Diri` dan tidak akan menghitung siswa yang lulus pada 06b. 06b akan memperbaiki pemetaan kartu menjadi `counts[6]` (perbaikan kebenaran tampilan, bukan perubahan bisnis) dan mengujinya.
- **Implikasi 06b**: lingkungan dapat dipakai ulang dengan fixture tambahan idempotent; tidak perlu runtime baru dan tidak boleh me-reset data 06a (D8).

---

## 3. Urutan pekerjaan yang diusulkan

| Langkah | Pekerjaan | Hasil yang dapat diverifikasi |
|---|---|---|
| S1 | Migration additive `student_progression_batches` + model + allowlist audit | Tabel/model ada; tidak ada perubahan tabel domain 03/06a |
| S2 | Service eligibility + preview (tanpa mutasi) + token | Preview 200 dengan ringkasan/konflik; DB siswa/kelas tidak berubah |
| S3 | Service commit atomik + idempotensi + audit | Commit all-or-nothing; retry token sama mengembalikan hasil yang sama |
| S4 | Controller, request, route, ability | Endpoint terdaftar; guest/view-only ditolak server |
| S5 | UI `StudentProgression.vue` + menu Academic + perbaikan kartu Graduated di Students (`counts[6]`) | Wizard English: tahun → kelas → keputusan → preview → commit → hasil/riwayat; kartu statistik menghitung `Lulus` dengan benar |
| S6 | Tes backend + regresi 03d/06a + build/ESLint | Matriks §6 PASS; tidak ada regresi |
| S7 | Fixture UAT `prepare-progression` + checklist review + laporan | UAT `.uat-phase06` siap; laporan dan checklist manual untuk pemilik |

Setelah S7, berhenti untuk review pemilik. Tidak ada 06c, merge, atau deploy otomatis.

---

## 4. Keputusan yang perlu pemilik (D1–D9)

Rekomendasi ditulis sebagai default. Setelah disetujui, keputusan dicatat sebagai **DEC-30** sebelum implementasi.

| # | Pertanyaan | Rekomendasi | Alternatif / dampak |
|---|---|---|---|
| **D1** | Apa arti tepat setiap keputusan? | **`promote`**: buat membership baru di kelas tujuan, status `Aktif`, tanggal masuk = `start_date` tahun tujuan, `student_entry_id` null (bukan siswa baru/mutasi), `version=1`; membership sumber **tidak diubah**. **`retain`**: tidak membuat membership tujuan; siswa tampil Unplaced di tahun tujuan; baris sumber tidak diubah; keputusan hanya tercatat di batch. **`graduate`**: ubah membership sumber menjadi status `Lulus`, `latest_student_exit_date` = `latest_student_graduation_date` = tanggal lulus, `version+1`, `latest_information` = referensi batch (tanpa PII); tidak membuat membership tujuan. **`skip`**: tidak ada mutasi. Setiap siswa yang berubah menaikkan `students.version` +1 kecuali retain/skip. | Menambah kolom penanda “retained” pada siswa/kelas tujuan (schema dan UI lebih besar); menyalin status kelulusan ke tabel `students` (melanggar desain status per keanggotaan). |
| **D2** | Bagaimana aturan tanggal? | **`start_date` tahun tujuan wajib** bila batch mengandung `promote`; bila null → preview ditolak `422` dengan pesan jelas. **Tanggal lulus wajib** bila ada `graduate`; UI mengisi default `end_date` tahun sumber, lalu `start_date` tahun tujuan bila `end_date` null, dan operator dapat mengubahnya. Tanggal lulus dipakai untuk exit dan graduation date. `retain`/`skip` tidak membutuhkan tanggal. | Memakai tanggal hari ini otomatis (tidak deterministik); menurunkan tanggal dari nama tahun (tebakan); mewajibkan `end_date` tahun sumber (menghambat data lama yang belum lengkap). |
| **D3** | Siswa mana yang masuk, dan bagaimana pemetaan kelas? | Roster = siswa **non-archived** dengan tepat **satu keanggotaan aktif** di tahun sumber. Kolom legacy tanpa `student_id` diikutkan hanya bila NIK menunjuk **tepat satu** siswa; baris ambigu/terpecah dilaporkan sebagai konflik, bukan ditebak (DEC-20). Pemetaan default per kelas sumber → satu kelas tujuan, tetapi **setiap siswa dapat memilih kelas tujuan sendiri** (override) selama kelas itu berada di tahun tujuan dan tidak terhapus. Sistem **tidak** menurunkan tingkat berikutnya dan **tidak** memblokir perbedaan jurusan/tingkat; ketidaksesuaian ditampilkan sebagai peringatan. Siswa dengan membership apa pun di tahun tujuan (aktif atau tertutup) → konflik, bukan ditimpa. | Otomatis +1 tingkat (tidak andal karena `school_levels` tanpa urutan); memblokir semua perbedaan jurusan (menghalangi reorganisasi kelas yang sah); menimpa membership tujuan yang sudah ada (kehilangan riwayat). |
| **D4** | Bagaimana aturan lock dan tahun? | **Tahun sumber ≠ tahun tujuan**, keduanya ada dan tidak soft-deleted. **Target harus unlocked hanya bila ada `promote`** (karena membuat membership di target). **Sumber harus unlocked hanya bila ada `graduate`** (karena mengubah membership sumber). `retain`/`skip` tidak memerlukan lock apa pun. Tahun aktif tidak harus diubah; proses boleh dijalankan selama tahun sumber masih aktif. Preview dan commit sama-sama memeriksa lock. | Mewajibkan target unlocked untuk semua jenis (menghambat lulus saat target sudah dikunci); mewajibkan sumber unlocked untuk promote (tidak diperlukan); mengunci periode aktif sebelum promosi (tidak ada dasar aturan). |
| **D5** | Bagaimana token, batas batch, konflik, dan idempotensi? | Simpan batch preview di tabel baru; token acak 256-bit diberikan sekali, **hanya hash** yang disimpan, berlaku **30 menit**, terikat pada pembuat batch (hanya akun itu yang dapat commit). Maksimum **500 keputusan** per batch. Preview mengembalikan item `ready`/`conflict` (tanpa mutasi). Commit memeriksa ulang semuanya di dalam transaksi; bila versi/data berubah → **409** dengan daftar siswa yang berubah dan **nol mutasi**. Commit ulang dengan token yang sama setelah sukses → **200** memutar hasil tersimpan (`replayed: true`), bukan membuat membership kedua. Token kedaluwarsa → `422` (preview baru diperlukan). | Tanpa tabel batch (sulit idempotensi/audit); token JWT tanpa penyimpanan (tidak bisa replay); batas 1.000+ (transaksi lebih panjang); token dapat dipakai akun lain (risiko penyalahgunaan). |
| **D6** | Ability, route, dan menu? | **Tanpa ability baru**: baca opsi/roster/riwayat memakai `students.view`; preview dan commit memakai `students.manage`. Menu **Academic → Progression** dengan `students.view`. Principal (`kepsek`) dapat melihat tetapi tidak dapat preview/commit; server menegakkan `403`. | Menambah `academic.manage` (menambah katalog role/ability); menaruh Progression di grup Public (tidak konsisten dengan Academic). |
| **D7** | Bagaimana audit, riwayat, dan penghapusan batch? | Model batch memakai `Loggable` dengan allowlist **tanpa token/hash** (`source_school_year_id`, `target_school_year_id`, `status`, `created_by`, `committed_by`, `committed_at`, ringkasan jumlah). Setiap perubahan membership tetap diaudit oleh `StudentRombel`; tambahkan `latest_student_exit_date` dan `latest_student_graduation_date` ke allowlist audit membership (tanpa `latest_information` bebas teks). Batch tidak dihapus otomatis; riwayat dapat dibaca dengan `students.view`. Tahun ajaran yang memiliki batch tidak dapat dihapus. | Menghapus batch setelah commit (kehilangan jejak); mengaudit payload penuh (berisiko memuat data pribadi); menyimpan token plaintext (rahasia). |
| **D8** | Lingkungan dan fixture UAT 06b? | **Pakai ulang `.uat-phase06`** (SQLite, API `127.0.0.1:18024`, SPA `127.0.0.1:14024`, cookie `school_phase06_session`) dan perluas launcher dengan perintah idempotent **`prepare-progression`**: tidak me-reset data 06a, menambahkan fixture sintetis `UAT-P06B` untuk skenario konflik (mis. siswa yang sudah punya membership di target), kandidat lulus, kandidat retain, dan penempatan aktif sumber; tidak menyentuh `.uat-phase05` atau data operasional. Server dijalankan untuk review dan **dihentikan setelah penerimaan** (DEC-27). | Runtime baru `.uat-phase07` (data 06a harus dibuat ulang); memakai data produksi (dilarang); fixture <100 baris (melanggar kebijakan data sintetis). |
| **D9** | Apa yang secara eksplisit tidak termasuk? | Tidak ada **pembatalan/undo** batch, mutasi masuk/keluar, koreksi periode terkunci, CRUD semester, validasi kapasitas kelas, penurunan tingkat otomatis, rekap sekolah, ekspor, merge/deploy/force-push, migrasi DB operasional/MariaDB, atau perubahan dependency/service/`.env`. Koreksi dan pembatalan menunggu **06c**. | Memasukkan undo sekarang (memperbesar scope dan konflik dengan 06c); menggabungkan 06c ke 06b (melanggar instruksi satu subfase). |

**Jawaban pertanyaan terbuka 06b (Q1–Q6 di `PHASE-06-PLAN.md` §6):**

| Q | Jawaban yang diusulkan |
|---|---|
| Q1 — pemetaan target | Satu kelas tujuan default per kelas sumber; override per siswa diizinkan; server memvalidasi kelas tujuan per siswa (D3). |
| Q2 — penanda retain | Tidak ada kolom baru; retain hanya tercatat di batch, siswa tampil Unplaced di tahun tujuan (D1/D7). |
| Q3 — `start_date` target null | Preview `promote` ditolak `422`; retain/skip tanpa promote tetap boleh (D2). |
| Q4 — tanggal lulus | Isi **kedua** `latest_student_exit_date` dan `latest_student_graduation_date` dengan tanggal lulus yang dipilih operator (D1/D2). |
| Q5 — membership target sudah ada | Blokir sebagai konflik; jangan timpa; koreksi menunggu 06c (D3/D9). |
| Q6 — batas batch/token | 500 keputusan per batch; token 30 menit; idempotent replay (D5). |

---

## 5. Desain teknis

### 5.1 Skema (migration additive)

Satu tabel baru `student_progression_batches`:

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | — |
| `token_hash` | char(64) unique | SHA-256 dari token acak; token plaintext tidak disimpan |
| `source_school_year_id` | FK `school_years` | Wajib; tahun sumber |
| `target_school_year_id` | FK `school_years` | Wajib; harus berbeda dari sumber |
| `status` | string(20) | `preview` atau `committed` |
| `decisions` | json | Daftar keputusan + versi yang diharapkan; snapshot preview |
| `summary` | json | Jumlah per keputusan/konflik; tanpa PII |
| `result` | json nullable | Hasil commit per siswa (id, keputusan, kelas, versi baru) |
| `created_by` | FK `users` | Pembuat preview; satu-satunya akun yang boleh commit |
| `committed_by` | FK `users` nullable | Aktor commit |
| `expires_at` | timestamp | `created_at + 30 menit` |
| `committed_at` | timestamp nullable | — |
| `timestamps` | — | — |

- Index: `(source_school_year_id, target_school_year_id)`, `(status)`, `(created_by)`, unique `token_hash`.
- Tidak ada soft delete; batch adalah riwayat. Tidak ada FK ke siswa (payload JSON) agar batch tetap dapat dibaca setelah siswa diarsipkan; hasil commit memuat `student_id`.
- `SchoolYearController::destroy` ditambah guard: tahun yang memiliki batch tidak dapat dihapus (pola guard 06a).
- Tidak ada perubahan pada `students`, `student_rombels`, `school_rombels`, atau `school_years` selain guard hapus. Tidak ada unique index baru.

### 5.2 Endpoint (usulan)

| Method | Endpoint | Ability | Perilaku |
|---|---|---|---|
| GET | `/api/academic/progression/options?source_school_year_id=&target_school_year_id=` | `students.view` | Tahun sumber/tujuan (tanggal, lock, active), kelas per tahun + jumlah anggota aktif, `max_batch_size=500` |
| GET | `/api/academic/progression/roster?source_school_year_id=&rombel_ids[]=&search=` | `students.view` | Siswa dengan tepat satu keanggotaan aktif di tahun sumber; item: id, nama, NIS/NISN, `student_version`, `membership_id`, `membership_version`, kelas sumber, ada/tidak membership target, alasan tidak eligible; maksimum 500 baris |
| POST | `/api/academic/progression/preview` | `students.manage` | Validasi global + per siswa tanpa mutasi; simpan batch `preview`; kembalikan `token`, `expires_at`, `batch_id`, ringkasan, item `ready`/`conflict` |
| POST | `/api/academic/progression/commit` | `students.manage` | `token`; transaksi all-or-nothing; `409` itemized bila versi/data berubah; `200` replay bila batch sudah committed |
| GET | `/api/academic/progression/batches?source_school_year_id=&target_school_year_id=` | `students.view` | Riwayat batch + ringkasan; tanpa token/payload mentah |
| GET | `/api/academic/progression/batches/{id}` | `students.view` | Detail hasil per siswa untuk batch committed; preview hanya untuk pembuatnya |

Request preview (ringkas):

```json
{
  "source_school_year_id": 1,
  "target_school_year_id": 2,
  "graduation_date": "2027-06-30",
  "decisions": [
    {"student_id": 10, "membership_id": 55, "expected_student_version": 3, "expected_membership_version": 1, "decision": "promote", "target_rombel_id": 7},
    {"student_id": 11, "membership_id": 56, "expected_student_version": 2, "expected_membership_version": 1, "decision": "retain"},
    {"student_id": 12, "membership_id": 57, "expected_student_version": 4, "expected_membership_version": 2, "decision": "graduate"},
    {"student_id": 13, "membership_id": 58, "expected_student_version": 1, "expected_membership_version": 1, "decision": "skip"}
  ]
}
```

- `decisions` 1–500 baris; `student_id` unik; `membership_id` harus milik tahun sumber; `target_rombel_id` wajib untuk `promote`, diabaikan untuk keputusan lain; `graduation_date` wajib bila ada `graduate`.
- Server tidak menerima nama/NIK sebagai kunci; identitas memakai `student_id` dan `membership_id` (resolusi legacy dilakukan server-side).
- Preview tidak mengubah siswa/membership; hanya menulis baris batch perencanaan (di luar domain siswa) dan audit pembuatannya.

### 5.3 Aturan eligibilitas (preview dan commit)

| Pemeriksaan | Hasil bila gagal |
|---|---|
| Tahun sumber/tujuan ada, tidak soft-deleted, berbeda | `422` global |
| `target.start_date` ada bila ada promote | `422` dengan field `target_school_year_id` |
| Tanggal lulus ada bila ada graduate | `422` dengan field `graduation_date` |
| Target unlocked bila ada promote | `422` pesan lock |
| Sumber unlocked bila ada graduate | `422` pesan lock |
| `decisions` 1–500 dan `student_id` unik | `422` |
| Siswa ada, tidak archived, versi siswa cocok | Item konflik / `409` saat commit |
| Tepat satu keanggotaan aktif sumber, versi cocok, bukan legacy ambigu | Item konflik / `409` saat commit |
| Tidak ada membership apa pun di tahun tujuan | Item konflik / `409` saat commit |
| Kelas tujuan milik tahun tujuan, tidak soft-deleted | Item konflik / `422` bila tidak ada kelas valid |
| `promote` tanpa kelas tujuan | `422` |
| Perbedaan tingkat/jurusan sumber vs tujuan | Peringatan di item, **tidak** memblokir (D3) |
| Batch token valid, belum kedaluwarsa, milik akun yang sama | `422`/`403` |
| Batch sudah committed | `200` replay hasil tersimpan |

### 5.4 Transaksi commit dan urutan lock

1. Muat batch berdasarkan `token_hash`; tolak bila tidak ada, kedaluwarsa, atau `created_by` bukan pengguna aktif.
2. `DB::transaction`:
   - Lock baris batch; bila `committed` → kembalikan hasil (replay).
   - Kumpulkan ID tahun; lock baris `school_years` berurutan naik; validasi lock sesuai D4.
   - Lock baris `students` berurutan naik; validasi archived/versi.
   - Lock baris `student_rombels` sumber berurutan naik; validasi active/versi/tahun.
   - Lock baris `school_rombels` tujuan berurutan naik; validasi tahun/tidak deleted.
   - Cek membership target tanpa lock terpisah (tahun target sudah lock; assign/transfer 03d juga mengunci tahun lebih dulu).
   - Terapkan keputusan berurutan naik berdasarkan `student_id`:
     - promote: `StudentRombel::create([...])`; `student.version += 1`.
     - graduate: update membership (status Lulus, exit/graduation date, versi+1); `student.version += 1`.
     - retain/skip: tidak ada mutasi; tidak menaikkan versi.
   - Update batch: `status=committed`, `committed_by`, `committed_at`, `result`.
3. Commit DB. Bila exception/audit gagal → seluruh transaksi rollback; batch tetap `preview` (dapat dicoba lagi setelah operator membuat preview baru bila data berubah).
4. Catatan: preview dan commit memakai aturan yang sama; preview hanya membaca dan tidak mengunci baris domain.

### 5.5 Audit

- Batch create/commit diaudit oleh `Loggable`; allowlist tidak memuat `token_hash`, `decisions` mentah, atau NIK.
- Setiap `StudentRombel` yang dibuat/diubah diaudit dengan `student_id`, `school_rombel`, `student_status_id`, tanggal keluar/lulus (setelah allowlist ditambah), dan `version`; tanpa nama/NIK/alamat.
- Kegagalan insert audit membatalkan transaksi commit (diuji).

### 5.6 UI (English)

Halaman baru **Academic → Progression** (`StudentProgression.vue`), empat langkah:

1. **Years**: pilih tahun sumber (default periode aktif) dan tahun tujuan; tampilkan tanggal/lock dan peringatan bila target/sumber terkunci.
2. **Classes & students**: pilih kelas sumber yang diproses (total ≤500); tabel siswa dengan kelas sumber, NIS/NISN, versi; keputusan default `promote` dan kelas tujuan default dari pemetaan kelas; ubah per siswa (`retain`/`graduate`/`skip`).
3. **Preview**: ringkasan jumlah per keputusan, peringatan jurusan/tingkat, konflik per siswa; tombol **Create Preview** menghasilkan token dan masa berlaku; tidak ada tombol yang mengubah data sebelum commit.
4. **Commit**: konfirmasi jumlah dan tahun; commit mengirim token; hasil per siswa dan riwayat batch; bila `409`, tampilkan siswa yang berubah dan tombol **Reload & Preview Again**.

Ketentuan UI: seluruh label/pesan English; tombol preview/commit hanya untuk `students.manage`; view-only melihat riwayat; layout 390 px tanpa overflow; dialog konfirmasi dapat dioperasikan dengan keyboard.

---

## 6. Matriks tes

Backend (Pest, SQLite in-memory; MariaDB tetap NOT RUN):

| # | Skenario | Ekspektasi |
|---|---|---|
| T1 | Guest / `uat.kepsek` membuka endpoint | `401`/`403`; tidak ada mutasi |
| T2 | Opsi/roster: filter kelas, search, archived, legacy unik, legacy ambigu | Hanya active non-archived; legacy unik diikutkan; ambigu dilaporkan |
| T3 | Preview sukses campuran promote/retain/graduate/skip | `200`, summary benar, DB siswa/membership **tidak berubah**, batch `preview` |
| T4 | Preview konflik: target membership ada, versi basi, kelas target invalid, tahun sama, tanggal kosong, >500 | `422`/item konflik; tidak ada mutasi |
| T5 | Commit promote | Membership target dibuat (status 1, entry date target start, versi 1), sumber tidak berubah, versi siswa +1, audit |
| T6 | Commit retain | Tidak ada membership target; siswa unplaced di tahun target; versi tidak berubah |
| T7 | Commit graduate | Status `Lulus`, exit=graduation date, versi membership+1, versi siswa+1, tanpa membership target, audit |
| T8 | Commit skip | Tidak ada mutasi/versi berubah |
| T9 | Token sama diulang setelah commit | `200` replay, tidak ada membership kedua |
| T10 | Versi berubah setelah preview | Commit `409` itemized, nol mutasi |
| T11 | Token kedaluwarsa / akun lain | `422`/`403`, nol mutasi |
| T12 | Lock: target locked + promote; source locked + graduate; retain-only | Ditolak untuk promote/graduate; retain-only sesuai D4 |
| T13 | Kegagalan audit di tengah commit | Rollback penuh; batch tetap `preview` |
| T14 | Riwayat batch list/detail | Hanya batch committed yang menampilkan hasil; tanpa token |
| T15 | Regresi 03d/06a + suite penuh | Tidak ada regresi |
| T16 | Kartu statistik Students setelah graduate | “Graduated” menghitung status `Lulus` (6), bukan status 4 |

Frontend/UX: `npm run build`, `npx eslint --quiet` file berubah; browser DOM untuk tahun/kelas/keputusan/preview/commit/409/390 px; tidak ada error console.

---

## 7. UAT

- Lingkungan: `.uat-phase06` (SQLite), API `127.0.0.1:18024`, SPA `127.0.0.1:14024`, cookie `school_phase06_session`, launcher `laravel-api/scripts/phase06-uat.php`.
- Perintah baru: `prepare-progression` (idempotent; tidak me-reset data), `status` diperluas dengan ringkasan batch; `init`/`prepare` lama tidak berubah perilakunya.
- Fixture tambahan minimal:
  - 20 siswa `UAT-P06B` dengan penempatan aktif di 2026/2027 (sebagian di kelas XII untuk kandidat lulus).
  - 5 siswa dengan membership di tahun 2027/2028 (konflik target) — tidak boleh tertimpa.
  - 5 kandidat retain dan 5 kandidat graduate (bisa memakai siswa yang sama dengan keputusan berbeda di kelas berbeda).
  - 1 kelas sumber dengan target kelas berbeda jurusan untuk memeriksa peringatan (tidak memblokir).
  - Tahun 2027/2028 dibuka untuk review melalui UI/seed fixture (bukan data 06a yang dihapus); tahun aktif tetap 2026/2027.
- Skenario manual akan ditulis di `PHASE-06B-MANUAL-REVIEW.md` setelah implementasi; cakupan: isolasi, opsi/roster, preview tanpa mutasi, commit campuran, replay token, 409, lock, view-only, audit, riwayat, English/390 px, dan penghentian server setelah penerimaan.
- Server dihentikan setelah penerimaan (DEC-27); `.uat-phase06` dipertahankan.

---

## 8. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Data lama memiliki >1 keanggotaan aktif per siswa/tahun | Preview menandai konflik; tidak memilih baris pertama diam-diam; koreksi 06c |
| Legacy `student_id` null / NIK ambigu | Resolusi unik saja (DEC-20); ambigu dilaporkan, tidak diatribusikan |
| Trigger MySQL menolak update graduate | Baris menjadi non-aktif (status Lulus + exit date) sebelum trigger menilai; diuji di SQLite; MariaDB NOT RUN |
| Transaksi 500 baris panjang | Batas 500, index batch, lock berurutan; uji beban tidak dijalankan |
| Token bocor/dipakai akun lain | Hash di DB, 30 menit, terikat pembuat |
| Batch diulang membuat membership ganda | Status committed + replay idempoten; single-active guard tetap berlaku |
| Retain tidak terlihat di siswa | Batch history adalah sumber keputusan; UI riwayat menampilkan retain; siswa tetap Unplaced |
| Tahun target berubah lock setelah preview | Commit memeriksa ulang lock dan menolak `422` |
| Penghapusan tahun menghapus riwayat batch | Guard hapus tahun bila batch ada |
| Kartu Graduated menampilkan angka salah setelah 06b | Perbaikan `counts[6]` + tes/browser pada halaman Students (temuan pre-existing §2.5) |
| Koreksi setelah commit tidak tersedia | Dinyatakan di UI dan laporan; menunggu 06c |

---

## 9. Non-goals dan batas otorisasi

- Tidak ada implementasi sebelum D1–D9 disetujui dan dicatat sebagai DEC baru.
- Tidak ada merge/deploy/force-push/rewrite, migrasi database operasional/MariaDB, perubahan `.env` utama, dependency/lockfile, service, atau akses DB terlarang.
- Tidak ada perubahan pada data `.uat-phase05` atau data operasional; UAT hanya `.uat-phase06`.
- Rekap sekolah tetap menunggu DEC-12 (PROPOSED).
- Setelah 06b diterima, 06c (mutasi/koreksi) memerlukan rencana dan keputusan terpisah.

---

## 10. Ringkasan keputusan untuk pemilik

| Kode | Ringkasan |
|---|---|
| D1 | Semantik promote/retain/graduate/skip dan mutasi yang tepat |
| D2 | Tanggal target/kelulusan dan validasinya |
| D3 | Cakupan roster, resolusi legacy, pemetaan/override kelas, tanpa auto-tingkat |
| D4 | Lock: target untuk promote, sumber untuk graduate, tahun berbeda |
| D5 | Token 30 menit, hash, terikat aktor, batch ≤500, 409 itemized, replay idempoten |
| D6 | Tanpa ability baru; `students.view`/`students.manage`; menu Academic → Progression |
| D7 | Audit batch tanpa token, audit tanggal keluar/lulus, riwayat permanen, guard hapus tahun |
| D8 | Pakai ulang `.uat-phase06` + `prepare-progression`; server dihentikan setelah penerimaan |
| D9 | Tanpa undo/mutasi/koreksi/semester/kapasitas/rekap; 06c terpisah |

Bila pemilik menyetujui seluruhnya, implementasi 06b diotorisasi dan akan dikerjakan setelah keputusan dicatat sebagai **DEC-30**. Bila ada yang diubah, rencana dan matriks tes disesuaikan sebelum implementasi.
