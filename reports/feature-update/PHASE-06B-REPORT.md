# Laporan Subfase 06b — Naik Kelas, Tinggal Kelas, dan Lulus

Tanggal: implementasi dan verifikasi agen 2026-10-31 WIB. Branch: `main`. Baseline induk: `6dcd432` (pencatatan DEC-30 APPROVED). SHA paket implementasi+dokumentasi 06b dicatat pada checkpoint HANDOFF setelah push; laporan ini tidak menulis SHA yang belum ada.
Keputusan pemilik: **DEC-30** (APPROVED 2026-10-31 WIB; D1–D9, pernyataan "Saya setuju dieksekusi.").
Status teknis: **IMPLEMENTED — agent-verified (2026-10-31)**. Status review pemilik: **PENDING** (checklist manual belum diterima).
Rencana: `reports/feature-update/PHASE-06B-PLAN.md`. Checklist manual: `reports/feature-update/PHASE-06B-MANUAL-REVIEW.md`.

## 1. Outcome dan cakupan

Subfase 06b menambahkan proses akhir tahun ajaran yang lengkap dan dapat diaudit:

- **Empat keputusan**: `promote` (membuat keanggotaan baru di kelas tujuan), `retain` (tanpa mutasi; siswa Unplaced di tahun tujuan), `graduate` (keanggotaan sumber menjadi `Lulus` dengan tanggal keluar dan tanggal lulus), dan `skip` (tanpa mutasi). `students.version` hanya naik untuk siswa yang benar-benar berubah.
- **Preview tanpa mutasi**: endpoint preview memvalidasi satu keputusan per siswa (maksimum 500), memeriksa versi siswa/keanggotaan, tahun ajaran, lock, dan konflik target, lalu menyimpan batch `preview`. Tabel domain siswa/kelas tidak berubah saat preview.
- **Commit atomik ber-token**: token acak 256-bit hanya disimpan sebagai SHA-256, berlaku 30 menit, dan terikat pada akun pembuat. Commit memakai transaksi dengan urutan lock batch → tahun → siswa → keanggotaan → kelas tujuan; konflik menghasilkan `409` itemized tanpa mutasi; token yang sama setelah commit mengembalikan `200` replay tanpa membuat keanggotaan kedua.
- **Riwayat batch**: `student_progression_batches` menyimpan ringkasan dan hasil commit tanpa token/decisions mentah. Batch preview hanya terlihat oleh pembuatnya (list dan detail), sedangkan batch committed dapat dibaca pemegang `students.view`. Tahun ajaran yang direferensikan batch tidak dapat dihapus.
- **UI English**: wizard empat langkah **Academic → Progression** (Years → Students & Decisions → Preview → Result) dengan filter kelas, pencarian, keputusan massal/per siswa, ringkasan konflik, dialog commit, hasil, dan riwayat batch; kartu **Graduated** pada halaman Students kini menghitung status `Lulus` (id 6), bukan status id 4.
- **Tanpa ability baru**: baca opsi/roster/riwayat memakai `students.view`; preview dan commit memakai `students.manage`. Akun view-only (principal) melihat riwayat committed saja dan ditolak `403` oleh server untuk preview/commit/detail preview.

Tidak dikerjakan pada 06b: pembatalan/undo batch, mutasi masuk/keluar, koreksi periode terkunci, CRUD semester, validasi kapasitas, penurunan tingkat otomatis, rekap sekolah, ekspor, merge/deploy, migrasi database operasional/MariaDB, dan perubahan dependency/service/`.env` (D9).

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File | Perubahan | Schema/izin |
|---|---|---|
| `database/migrations/2029_07_31_000001_create_student_progression_batches_table.php` | Tabel baru `student_progression_batches` + index tahun/status/pembuat, unique `token_hash` | FK tahun `restrictOnDelete`, `created_by` restrict, `committed_by` nullOnDelete; JSON decisions/summary/result |
| `app/Models/StudentProgressionBatch.php` | Model + `Loggable` allowlist tanpa token/decisions/result; helper `isCommitted()`/`isExpired()`; `token_hash` hidden | Tidak ada data pribadi |
| `app/Services/Academic/StudentProgressionService.php` | Opsi, roster, preview, commit, riwayat/detail; `MAX_BATCH_SIZE=500`, `TOKEN_TTL_MINUTES=30`, `GRADUATED_STATUS_ID=6`; filter preview hanya pembuat | Baca/tulis sesuai route |
| `app/Http/Requests/PreviewStudentProgressionRequest.php` | Validasi tahun berbeda/non-deleted, 1–500 decisions, `student_id`/`membership_id` unik, `promote` wajib target, tanggal `Y-m-d`, keanggotaan harus milik tahun sumber | `students.manage` |
| `app/Http/Controllers/StudentProgressionController.php` | `options`, `roster`, `preview`, `commit`, `batches`, `showBatch` | `students.view`/`students.manage` |
| `routes/api.php` | Enam route `/api/academic/progression/*` | Guest `401`; tanpa ability `403` |
| `app/Http/Controllers/SchoolYearController.php` | Guard hapus tahun yang direferensikan batch (`422`) | `master.manage` |
| `app/Models/StudentRombel.php` | Allowlist audit menambah tanggal keluar/lulus + versi; **menghapus `student_nik`** | Tanpa nama/NIK/alamat pada audit baru |
| `scripts/phase06-uat.php` | Perintah idempotent `prepare-progression` (20 siswa `UAT-P06B`, status `Lulus` id 6, konflik target, kandidat lulus/retain); `status` melaporkan batch | Hanya `.uat-phase06` SQLite |
| `tests/Feature/Academic/StudentProgressionTest.php` | 11 tes / 150 assertions | T1–T16 (lihat §3) |
| `vue-app/src/views/pages/public/StudentProgression.vue` | Wizard 4 langkah, decisions persisten lintas filter, dialog 409 + Reload & Preview Again, riwayat/detail | `students.view`/`students.manage` |
| `vue-app/src/views/pages/public/Student.vue` | Kartu Graduated memakai `counts[6]` | Perbaikan tampilan |
| `vue-app/src/router/index.js`, `vue-app/src/layout/AppMenu.vue` | Route/menu Progression di grup Academic | `students.view` |

Kontrak endpoint (detail di `docs/API-CONTRACTS.md`):

| Method | Endpoint | Izin | Catatan |
|---|---|---|---|
| GET | `/api/academic/progression/options?source_school_year_id=&target_school_year_id=` | `students.view` | Tahun sumber/tujuan (tanggal, lock, active), kelas per tahun, `max_batch_size=500` |
| GET | `/api/academic/progression/roster?source_school_year_id=&target_school_year_id=&rombel_ids[]=&search=` | `students.view` | Siswa non-archived dengan tepat satu keanggotaan aktif; `eligible`/`ineligible_reason`; maksimum 500 baris + `truncated` |
| POST | `/api/academic/progression/preview` | `students.manage` | Tanpa mutasi; `200` + token/batch atau `422` item konflik; batch `preview` |
| POST | `/api/academic/progression/commit` | `students.manage` | Transaksi atomik; `409` itemized; replay `200` (`replayed: true`); token kedaluwarsa `422`; akun lain `403` |
| GET | `/api/academic/progression/batches` | `students.view` | Committed untuk semua pembaca; preview hanya milik pembuat; tanpa token/payload mentah |
| GET | `/api/academic/progression/batches/{id}` | `students.view` | Detail hasil committed; preview milik akun lain `403` |

## 3. Evidence

| ID | Metode / command | Fixture / peran | Expected | Actual | Status |
|---|---|---|---|---|---|
| E1 | `php artisan test --filter=StudentProgressionTest` | SQLite in-memory | Matriks T1–T16 PASS | **11 passed / 150 assertions, 6,64 s** | PASS |
| E2 | `php artisan test` (suite penuh) | SQLite in-memory + MariaDB disposable yang tersedia | Tidak ada regresi | **448 passed / 13 skipped / 4.121 assertions, 250,68 s**; skip adalah tes integrasi MariaDB tanpa password | PASS |
| E3 | `./vendor/bin/pint --test` pada 10 file PHP berubah/baru | — | Gaya bersih | **PASS 10 files** | PASS |
| E4 | `npm run build` | Vite 6 | Build sukses | **PASS 26,36 s**; warning chunk >500 kB sudah ada sebelumnya | PASS |
| E5 | `npx eslint` read-only pada 4 file frontend berubah | ESLint | 0 error | **0 error**; `StudentProgression.vue` 0 temuan; `AppMenu.vue`/`router/index.js`/`Student.vue` hanya warning Prettier/CRLF lama lingkungan | PASS (scoped) |
| E6 | `php artisan route:list --path=academic/progression` | — | Enam route terdaftar | options, roster, preview, commit, batches, batches/{id} | PASS |
| E7 | `php scripts/phase06-uat.php status` | `.uat-phase06` SQLite terisolasi | Guard lolos; fixture 06b tersedia | Guard PASS; **153 siswa (150 `UAT-P06`, termasuk 20 `UAT-P06B`)**; 12 kelas; 10 guru aktif + 1 separated; 4 staf; periode aktif 1/1; **2 batch** | PASS |
| E8 | Browser DOM + API (Chrome DevTools MCP, SPA 14024 / API 18024) | `uat.operator` | Preview/commit campuran, replay, 409, riwayat | Batch #1 committed (2 promote, 1 graduate, 1 retain); batch #2 preview sengaja ditinggalkan; detail di §4 | PASS |
| E9 | Browser DOM + API | `uat.kepsek` (view-only) | Tidak dapat menulis; preview orang lain tidak terlihat | API list hanya batch #1 committed; preview/commit/detail preview → **403**; banner **View-only access**; tabel riwayat hanya #1 | PASS |
| E10 | Query read-only `activities` `.uat-phase06` | — | Audit tanpa rahasia/PII baru | Batch create/commit tanpa token/decisions; membership create/update dengan status, tanggal keluar/lulus, versi; **0** baris memuat token/decisions | PASS |
| E11 | Tes regresi audit PII | SQLite in-memory | Audit `student_rombels` baru tanpa `student_nik` | Assertion pada tes commit: seluruh payload audit membership tidak memuat `student_nik` | PASS |

**NOT RUN (tidak dikonversi menjadi PASS):**

- Migrasi/commit pada **MariaDB/InnoDB** dan perilaku trigger MySQL saat graduate; seluruh bukti 06b memakai SQLite terisolasi.
- Race multi-proses/dua tab nyata untuk preview stale `409`; konflik diuji dengan versi yang diubah di database UAT dan lewat tes otomatis.
- Uji browser lock tahun **sumber** (tahun aktif 2026/2027 tidak dapat dikunci menurut 06a); aturan ini tertutup tes otomatis T12 dan lock target diuji di browser/API.
- Uji beban 500 keputusan nyata dalam satu transaksi; batas divalidasi oleh tes validasi, bukan benchmark.
- Screenshot/visual regression berbasis file gambar; bukti browser berupa snapshot DOM/API dan screenshot sesi (tidak dikomit).
- Penerimaan manual pemilik: **PENDING**; checklist `PHASE-06B-MANUAL-REVIEW.md` belum dijalankan pemilik.
- Pemberhentian server UAT setelah penerimaan (DEC-27) belum dilakukan; server tetap berjalan untuk review.

## 4. UAT manual

Environment: `.uat-phase06` SQLite terisolasi, SPA `http://127.0.0.1:14024`, API `http://127.0.0.1:18024`, cookie `school_phase06_session`, launcher `laravel-api/scripts/phase06-uat.php`. Server tetap berjalan menunggu review pemilik (DEC-27). Data 06a tidak di-reset.

Akun review: `uat.operator` (manage) dan `uat.kepsek` (view-only). Password hanya berada di runtime `.uat-phase06/account-password.txt` yang diabaikan; tidak dicetak di dokumen.

Fixture `prepare-progression` (idempotent; 20 siswa `UAT-P06B`):

- 1–4 `X-1 → XI-1`; 5–6 `X-1` dengan keanggotaan target yang sudah ada (konflik); 7–10 `X-2 → XI-1` (lintas jurusan); 11–14 `XI-1 → XII-1`; 15–17 `XII-1` kandidat lulus; 18–19 `XI-2 → XII-2`; 20 kandidat retain.
- Status referensi dipastikan lengkap dan `Lulus` harus id 6 sebelum fixture dianggap siap.
- Keadaan setelah pengujian agen: tahun 2027/2028 **unlocked**; batch **#1 committed** dan batch **#2 preview** (dibuat operator, versi sudah basi karena uji `409`). Data tidak di-reset.

Hasil UAT yang dijalankan agen:

1. **Commit campuran** batch #1 (`UAT-P06B-0001` promote, `-0003` promote, `-0015` graduate, `-0020` retain): dua keanggotaan target dibuat (id 126/127, status `Aktif`, tanggal masuk 2027-07-01, versi 1), siswa 134/140 versi 2; siswa 148 (graduate) memiliki keanggotaan 120 berstatus `Lulus`, exit/graduation 2027-06-30, `latest_information = "Progression batch #1"`, versi 2; siswa 153 (retain) tidak berubah dan tetap Unplaced di tahun tujuan.
2. **Replay idempoten**: token preview yang sama dikirim ulang → `200`, `replayed: true`, item sama; tidak ada keanggotaan/audit baru.
3. **409 itemized**: batch #2 dibuat untuk `UAT-P06B-0002`, versi siswa dinaikkan di luar aplikasi, commit → `409` dengan dialog **Data Conflict Detected**, daftar siswa terdampak, dan tombol **Reload & Preview Again**; nol mutasi.
4. **View-only**: `uat.kepsek` melihat banner **View-only access**; API `GET /batches` hanya mengembalikan batch #1; `POST preview`/`commit` dan `GET /batches/2` → `403`; UI riwayat hanya menampilkan #1.
5. **Audit**: baris #21 batch create (tanpa token/decisions), #22/#24 membership create, #23/#25/#27 versi siswa, #26 graduate (status 1→6, tanggal keluar/lulus, versi 1→2), #28 batch committed, #29 batch preview #2. Tidak ada token/decisions pada audit.
6. **Students**: kartu **Graduation Students** menampilkan 1 setelah graduate (status 6 dihitung benar).

**Temuan dan perbaikan selama UAT 06b:**

- **Commit 500 (blocker)**: fixture UAT hanya memiliki status `Aktif`, sedangkan graduate membutuhkan `Lulus` (FK `student_rombels.student_status_id`). Transaksi rollback penuh dan batch tetap preview. `prepare-progression` diperbaiki agar membuat keenam status baseline secara idempotent dan menolak bila `Lulus` bukan id 6.
- **Persistensi keputusan di UI**: keputusan yang sudah dipilih hilang saat filter kelas sumber berubah; diperbaiki dengan `Map` keputusan terpisah dari baris roster, prunning hanya untuk siswa yang tidak lagi ada di hasil server, dan ringkasan keputusan tersembunyi.
- **Preview terlihat oleh akun lain**: `GET /batches` menampilkan batch preview milik operator kepada principal, bertentangan dengan caption UI dan maksud D7/T14. `StudentProgressionService::batches()` kini memfilter `status=committed OR created_by=actor`; tes T14 diperluas dan diverifikasi ulang di browser.
- **PII pada audit membership**: `student_nik` masih ada pada allowlist audit `StudentRombel`. Dihapus sesuai D7/§5.5; tes commit menegaskan payload audit baru tidak memuat `student_nik`. Baris audit UAT #22/#24 dibuat sebelum perbaikan sehingga masih memuat NIK sintetis; audit append-only tidak diubah.

## 5. Risiko dan keputusan

- **Preview bukan janji**: commit memeriksa ulang seluruh versi/data di dalam transaksi; perubahan apa pun menghasilkan `409` itemized tanpa mutasi. Token hanya hash, 30 menit, terikat pembuat.
- **Single-active tetap berlaku**: promote membuat baris di tahun berbeda; graduate menutup baris sumber (status `Lulus` + exit date) sebelum trigger menilai. Trigger MySQL/MariaDB tidak diuji pada 06b.
- **Riwayat permanen**: batch tidak dihapus otomatis dan tahun yang direferensikan tidak dapat dihapus; koreksi/undo menunggu 06c.
- **Preview hanya pembuat**: list dan detail menegakkan aturan yang sama; batch committed dapat dibaca semua pemegang `students.view`.
- **Batas bukti**: seluruh UAT memakai SQLite; MariaDB, race multi-proses, dan dua tab nyata tetap NOT RUN. Migrasi belum dijalankan pada database operasional.
- **Data UAT**: batch #2 preview sengaja ditinggalkan bersama tahun target unlocked agar pemilik dapat memeriksa riwayat/konflik; tidak ada reset data 06a.
- **UAT lifecycle**: server 14024/18024 tetap berjalan sampai penerimaan pemilik, lalu dihentikan sesuai DEC-27; data `.uat-phase06` dipertahankan.

## 6. Sinkronisasi dokumen

Diperbarui: `docs/API-CONTRACTS.md` (endpoint dan aturan progresi), `docs/DATABASE.md` (tabel batch dan allowlist audit), `docs/ACCESS-CONTROL.md` (matriks route/objek 06b), `docs/TESTING.md` (bukti dan NOT RUN 06b), `docs/DECISIONS.md` (catatan implementasi DEC-30), `docs/REQUIREMENTS.md` (FR-07), `CHANGELOG.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `reports/feature-update/PHASE-06B-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `PROJECT_CONTEXT.md`, laporan ini, dan checklist manual.

Commit/push dilakukan agar pemilik dapat meninjau implementasi di GitHub; commit/push bukan penerimaan manual. Tidak ada merge/deploy/force-push, perubahan `.env` utama, dependency/lockfile, service, DB terlarang, atau migrasi database operasional/MariaDB.

## 7. Langkah berikutnya

1. Pemilik menjalankan checklist `PHASE-06B-MANUAL-REVIEW.md` pada `.uat-phase06` (server tetap berjalan).
2. Setelah pemilik menyatakan penerimaan, catat persetujuan pada commit dokumentasi tersendiri dan hentikan server UAT sesuai DEC-27.
3. 06c (mutasi/koreksi) memerlukan rencana dan keputusan pemilik terpisah; merge/deploy tetap tidak diotorisasi.
