# Laporan Subfase 06c — Mutasi, Pembatalan, Rekap, dan Koreksi Periode Terkunci

Tanggal: implementasi dan verifikasi agen 2026-11-01 WIB. Branch: `main`. Baseline induk: `da94195` (pencatatan DEC-31 APPROVED). Implementasi dan dokumentasi 06c dikomit dan di-push sebagai `9927d8e` (`main...origin/main` sinkron).
Keputusan pemilik: **DEC-31** (APPROVED 2026-11-01 WIB; D1–D10, pernyataan "Saya setuju D1-D10.").
Status teknis: **IMPLEMENTED — agent-verified (2026-11-01)**. Status review pemilik: **APPROVED 2026-11-01 WIB (#1–#16)** berdasarkan pernyataan **"Pengujian nomor 1-16 sudah berhasil dan saya approved."**; agen tidak mengulang skenario.
Rencana: `reports/feature-update/PHASE-06C-PLAN.md`. Checklist manual: `reports/feature-update/PHASE-06C-MANUAL-REVIEW.md`.

## 1. Outcome dan cakupan

Subfase 06c melengkapi siklus tahun ajaran dengan mutasi yang dapat ditelusuri dan koreksi periode terkunci:

- **Mutasi masuk** membuat membership baru pada tahun ajaran unlocked: status `Aktif`, entry `Mutasi Masuk` (id 2), tanggal masuk = tanggal efektif, NPSN sekolah asal, versi 1. Hanya untuk siswa non-archived tanpa membership aktif; re-entry diizinkan bila membership terakhir sudah ditutup dengan exit ≤ tanggal efektif. Biodata siswa tidak diubah.
- **Mutasi keluar** menutup **satu** membership yang dipilih (status `Mutasi Keluar` id 2, exit date, NPSN sekolah tujuan, versi+1). Biodata, entry, dan tahun lain tidak berubah; arsip administratif 03b tidak dipakai sebagai mutasi.
- **Log immutable** `student_mutations` menyimpan type, tahun, siswa, membership, kelas, tanggal efektif, NPSN, alasan, status `active`/`cancelled`, aktor, dan versi. Tidak ada endpoint update/delete.
- **Pembatalan kompensatif**: batal keluar memulihkan membership (status `Aktif`, exit/keluar dibersihkan, versi+1); batal masuk mengarsipkan membership (`archived_at`, alasan `Cancelled mutation-in`) tanpa menghapus. Log tetap ada dan berubah menjadi `cancelled`; state yang berubah menghasilkan `409` itemized tanpa mutasi.
- **Rekap per tahun**: `mutation_in` dan `mutation_out` dari log `active` dalam rentang tanggal tahun; `graduated` dari siswa unik berstatus `Lulus` dengan tanggal lulus dalam rentang; `retained` dari keputusan `retain` pada batch progresi committed terbaru per siswa. `cancelled` dikecualikan. Rekap semester ditunda karena `semesters` belum bertanggal (D5).
- **Koreksi periode locked**: ability sensitif baru `students.correct-locked` (default hanya Admin Sistem) + `students.manage`; hanya tahun locked; lima tindakan (`mutation_in`, `mutation_out`, `mutation_cancel`, `membership_exit`, `membership_reopen`); alasan 10–255 dan `approval_reference` opsional; tabel immutable `student_locked_corrections` menyimpan snapshot `before`/`after` (id/status/tanggal/versi).
- **Konkurensi**: setiap operasi tunggal memakai transaksi dengan urutan lock tahun → siswa → membership → kelas → mutasi, serta `expected_student_version`/`expected_membership_version`/`expected_mutation_version`; mismatch → `409` itemized dengan `current_versions`, nol mutasi. Tidak ada token pada 06c.
- **UI English**: section **Students → Mutations** dengan pemilih tahun sendiri, empat kartu rekap, tabel log 20/halaman, pencarian siswa/sekolah (maksimum 20 hasil), dialog In/Out/Cancel/Correct, dan dialog timeline siswa. Tombol tulis mengikuti ability dan status lock; server tetap menegakkan `403`/`422`/`409`.
- **Audit dan integritas**: allowlist `StudentMutation` tanpa alasan/NIK/nama/alamat; allowlist `StudentRombel` menambah `mutation_education_school_id` dan `latest_student_exit_school_id` (NPSN); kegagalan audit me-rollback transaksi; tahun ajaran yang memiliki mutasi/koreksi tidak dapat dihapus.

Tidak dikerjakan pada 06c: rekap semester, rekap sekolah (DEC-12), pembuatan siswa baru melalui mutasi, undo batch 06b, hapus log, validasi kapasitas, ekspor mutasi, integrasi Dapodik, merge/deploy, migrasi database operasional/MariaDB, dan perubahan dependency/service/`.env` (D10).

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File | Perubahan | Schema/izin |
|---|---|---|
| `database/migrations/2029_08_01_000001_create_student_mutations_table.php` | Tabel baru `student_mutations` + index siswa/tanggal, tahun/type/status, membership, status | FK tahun/siswa/kelas restrictOnDelete; membership nullable restrict; actor restrict/nullOnDelete; log immutable |
| `database/migrations/2029_08_01_000002_create_student_locked_corrections_table.php` | Tabel baru `student_locked_corrections` + snapshot `before`/`after`, approval, index tahun/siswa/mutasi/action | Koreksi immutable; FK restrictOnDelete |
| `database/migrations/2029_08_01_000003_add_students_correct_locked_ability.php` | Ability `students.correct-locked` (sensitif) ke peran `admin-sistem` | `down()` melepas dan menghapus |
| `app/Models/StudentMutation.php` | Model + `Loggable` allowlist tanpa alasan/PII; konstanta type/status; relasi siswa/kelas/membership/tahun/aktor | Tidak ada data pribadi pada audit |
| `app/Models/StudentLockedCorrection.php` | Model + lima konstanta `ACTION_*`; relasi mutasi/membership/tahun/aktor | Snapshot tanpa nama/NIK/alamat |
| `app/Services/Academic/StudentMutationService.php` | Opsi, pencarian sekolah, riwayat, rekap, timeline, mutation-in/out, cancel; konstanta ID status/entry, `MAX_SEARCH_RESULTS=20`, `PER_PAGE=20` | Baca/tulis sesuai route |
| `app/Services/Academic/StudentLockedCorrectionService.php` | Lima tindakan koreksi locked, validasi state, snapshot, guard versi | Hanya tahun locked; dua ability |
| `app/Http/Requests/RecordStudentMutationInRequest.php` | `school_rombel_id`, `effective_date`, `school_npsn`, `reason` 10–255, `expected_student_version` | `students.manage` |
| `app/Http/Requests/RecordStudentMutationOutRequest.php` | `student_rombel_id`, tanggal, NPSN, alasan, versi siswa + membership | `students.manage` |
| `app/Http/Requests/CancelStudentMutationRequest.php` | `reason`, versi siswa + membership + mutasi | `students.manage` |
| `app/Http/Requests/CorrectStudentLockedMutationRequest.php` | `action` (5 nilai), tahun, target, tanggal/NPSN/status, alasan, approval, tiga versi; aturan kondisional per aksi | `students.manage` + `students.correct-locked` |
| `app/Http/Controllers/StudentMutationController.php` | `options`, `schools`, `index`, `recap`, `timeline`, `mutationIn`, `mutationOut`, `cancel` | `students.view`/`students.manage` |
| `app/Http/Controllers/StudentLockedCorrectionController.php` | `index`, `store` | `students.view`; tulis dua ability |
| `routes/api.php` | Sepuluh route `/api/academic/mutations/*`, `/api/students/{id}/mutations*`, `/api/students/{id}/locked-corrections` | Guest `401`; tanpa ability `403` |
| `app/Http/Controllers/SchoolYearController.php` | Guard hapus tahun yang direferensikan mutasi/koreksi (`422`) | `master.manage` |
| `app/Http/Controllers/StudentController.php` | Item rombel direktori menambah `membership_id`/`membership_version` | Versi untuk dialog mutasi |
| `app/Models/StudentRombel.php` | Allowlist audit menambah NPSN mutasi masuk/keluar | Tanpa nama/NIK/alamat |
| `app/Support/AbilityCatalog.php` | Definisi `students.correct-locked` + pemetaan awal `admin-sistem` | Sensitif |
| `scripts/phase06-uat.php` | Perintah idempotent `prepare-mutations` (120 siswa `UAT-P06C`, 20 sekolah, tahun locked, 92 log fixture); `status` melaporkan mutasi | Hanya `.uat-phase06` SQLite |
| `tests/Feature/Academic/StudentMutationTest.php` | 19 tes / 369 assertions | Matriks 06c (lihat §3) |
| `vue-app/src/views/pages/public/StudentMutations.vue` | Section Mutations, rekap, log, dialog In/Out/Cancel/Correct, timeline, pencarian | `students.view`/`students.manage`/`students.correct-locked` |
| `vue-app/src/views/pages/public/StudentMutationTimelineDialog.vue` | Timeline gabungan mutasi + koreksi | Baca sesuai izin |
| `vue-app/src/views/pages/public/Student.vue` | Integrasi section Mutations | — |

Kontrak endpoint (detail di `docs/API-CONTRACTS.md`):

| Method | Endpoint | Izin | Catatan |
|---|---|---|---|
| GET | `/api/academic/mutations/options?school_year_id=` | `students.view` | Tahun (tanggal/lock/active), kelas, referensi ID entry/status |
| GET | `/api/academic/mutations/schools?search=` | `students.view` | Sekolah non-deleted, maksimum 20 |
| GET | `/api/academic/mutations/recap?school_year_id=` | `students.view` | `mutation_in`/`mutation_out`/`graduated`/`retained`, catatan semester |
| GET | `/api/academic/mutations?school_year_id=&type=&status=&search=&page=` | `students.view` | Riwayat 20/halaman, filter tipe/status/pencarian |
| GET | `/api/students/{id}/mutations` | `students.view` | Timeline mutasi + koreksi + versi terkini |
| POST | `/api/students/{id}/mutations/in` | `students.manage` | Membership baru; `422`/`409` tanpa mutasi |
| POST | `/api/students/{id}/mutations/out` | `students.manage` | Menutup satu membership |
| POST | `/api/students/{id}/mutations/{mutation}/cancel` | `students.manage` | Pembatalan kompensatif; `409` itemized |
| GET | `/api/students/{id}/locked-corrections` | `students.view` | Riwayat koreksi immutable |
| POST | `/api/students/{id}/locked-corrections` | `students.manage` + `students.correct-locked` | Lima aksi; hanya tahun locked; `422`/`403`/`409` |

## 3. Evidence

| ID | Metode / command | Fixture / peran | Expected | Actual | Status |
|---|---|---|---|---|---|
| E1 | `php artisan test tests/Feature/Academic/StudentMutationTest.php` | SQLite in-memory | Matriks 06c PASS | **19 passed / 369 assertions, 10,41 s** | PASS |
| E2 | `php artisan test` (suite penuh) | SQLite in-memory | Tidak ada regresi | **467 passed / 13 skipped / 4.492 assertions, 279,96 s**; skip adalah tes integrasi MariaDB tanpa password | PASS |
| E3 | `./vendor/bin/pint --test` pada 21 file PHP berubah/baru | — | Gaya bersih | **PASS 21 files** | PASS |
| E4 | `php artisan route:list --path=mutations` + `--path=locked-corrections` | — | Sepuluh route terdaftar | **8 + 2 route** | PASS |
| E5 | `npm run build` | Vite 6 | Build sukses | **PASS 30,56 s**; warning chunk >500 kB sudah ada sebelumnya | PASS |
| E6 | `npx eslint --quiet` pada 3 file frontend berubah/baru | ESLint | 0 error | **0 error** | PASS |
| E7 | `php scripts/phase06-uat.php status` | `.uat-phase06` SQLite terisolasi | Guard lolos; fixture 06c tersedia | Guard PASS; **263 siswa aktif (260 `UAT-P06`, termasuk 120 `UAT-P06C`; 10 P06C terarsip)**; 20 sekolah sintetis; **94 log mutasi**; tahun 2025/2026 locked; 2 batch progresi | PASS |
| E8 | Browser DOM + API (Chrome DevTools/browser-use MCP, SPA 14024 / API 18024) | `uat.operator` | In/out/cancel/re-entry/rekap/log | Mutation-in 0081, mutation-out 0111, cancel 0021, timeline re-entry 0031; rekap In 41/Out 20/Graduated 1/Retained 1; detail di §4 | PASS |
| E9 | Browser DOM + API | `uat.kepsek` (view-only) | Tidak dapat menulis | Tanpa New In/Out/Cancel/Correct; timeline/rekap/log terbaca; API tulis `403` | PASS |
| E10 | Browser DOM + API | `uat.admin` | Koreksi locked beralasan + audit | Tahun 2025/2026 badge Locked; tombol Correct tersedia; koreksi 0072 (sekolah 91000002→91000001, approval `UAT-06C-APPROVAL-001`); timeline menampilkan before/after + aktor | PASS |
| E11 | Emulasi 390 px + pemeriksaan label/console | `uat.operator`/`uat.admin` | Tanpa overflow; English; console bersih | `innerWidth=390`, `document.scrollWidth=390`; dialog 367 px; label English; ESC menutup dialog; 0 error console | PASS |
| E12 | Query read-only SQLite `.uat-phase06` | — | State sesuai hasil UAT | 0081 membership X-1 aktif entry 2/NPSN 91000005; 0111 membership XI-2 tertutup versi 2/NPSN 91000003; 0021 log cancelled + membership aktif versi 3; 0072 mutasi versi 2 + 1 koreksi | PASS |

**NOT RUN (tidak dikonversi menjadi PASS):**

- Migrasi/commit pada **MariaDB/InnoDB**, perilaku trigger MySQL, dan row-lock lintas proses; seluruh bukti memakai SQLite terisolasi.
- Race multi-proses/dua tab nyata untuk `409` mutasi/pembatalan/koreksi; konflik diuji lewat tes otomatis dan versi yang diubah di database UAT.
- Benchmark 94 log/120 siswa pada volume besar; pagination dan pencarian diuji dengan fixture sintetis.
- Screenshot/visual regression berbasis file gambar; bukti browser berupa snapshot DOM/API (tidak dikomit).
- Penerimaan manual pemilik: **APPROVED 2026-11-01 WIB (#1–#16)** berdasarkan pernyataan **"Pengujian nomor 1-16 sudah berhasil dan saya approved."**; agen tidak mengulang skenario dan item NOT RUN di atas tetap NOT RUN.
- Pemberhentian server UAT setelah penerimaan (DEC-27): **selesai 2026-11-01**; PID/command line diverifikasi (SPA 19280, API 9436), port 14024/18024 bebas, data `.uat-phase06` dipertahankan, Apache/MariaDB tidak disentuh.

## 4. UAT manual

Environment: `.uat-phase06` SQLite terisolasi, SPA `http://127.0.0.1:14024`, API `http://127.0.0.1:18024`, cookie `school_phase06_session`, launcher `laravel-api/scripts/phase06-uat.php`. Server API (PID 9436) dan SPA (PID 19280) dihentikan 2026-11-01 setelah penerimaan (DEC-27); data 06a/06b tidak di-reset dan perintah restart ada di `PHASE-06C-MANUAL-REVIEW.md`.

Akun review: `uat.operator` (mutasi/pembatalan), `uat.kepsek` (view-only), `uat.admin` (koreksi locked). Password hanya berada di runtime `.uat-phase06/account-password.txt` yang diabaikan; tidak dicetak di dokumen.

Fixture `prepare-mutations` (idempotent; tidak mereset 06a/06b):

- 0001–0020 mutasi masuk aktif 2026/2027; 0021–0030 sudah keluar; 0031–0040 keluar lalu masuk kembali (tiga log); 0041–0050 mutasi masuk dibatalkan; 0051–0060 mutasi keluar dibatalkan; 0061–0070 mutasi masuk tahun tujuan 2027/2028; 0071–0080 tahun locked 2025/2026 (koreksi); 0081–0100 unplaced; 0101–0110 terarsip; 0111–0120 aktif untuk mutasi keluar baru.
- 20 sekolah sintetis NPSN `91000001`–`91000020`; status referensi `Aktif`=1, `Mutasi Keluar`=2, `Lulus`=6 diverifikasi sebelum fixture siap.
- Keadaan setelah pengujian agen: `0021` dibatalkan (membership X-2 aktif), `0081` mutasi masuk (X-1, NPSN 91000005), `0111` mutasi keluar (XI-2, NPSN 91000003), `0072` dikoreksi ke NPSN 91000001 dengan approval `UAT-06C-APPROVAL-001`; 94 log (21 cancelled), 1 koreksi. Data tidak di-reset.

Hasil UAT yang dijalankan agen:

1. **Operator — rekap dan log**: section Mutations default ke 2026/2027; kartu In 41/Out 20/Graduated 1/Retained 1; log 94 baris (paginasi 20); pencarian `P06C-0030`/`0081`/`0111` mengembalikan baris yang benar.
2. **Operator — mutation-in** `P06C-0081`: dialog menampilkan konsekuensi membership baru/entry `Mutasi Masuk`; submit sukses; baris log In aktif 01 Nov 2026, X-1, NPSN 91000005; rekap In bertambah; siswa tampil placed di direktori.
3. **Operator — mutation-out** `P06C-0111`: dialog menjelaskan biodata tidak dihapus; submit sukses; membership XI-2 tertutup (status `Mutasi Keluar`, exit 01 Nov 2026, NPSN 91000003, versi 2); rekap Out 19→20; direktori menghitung siswa unplaced.
4. **Operator — pembatalan** `P06C-0021`: log `out` tetap ada sebagai `Cancelled`, membership X-2 kembali aktif, rekap Out berkurang, tombol Cancel tidak muncul untuk baris cancelled.
5. **Operator — timeline** `P06C-0031`: tiga peristiwa In → Out → In dengan sekolah asal/tujuan, aktor, dan versi; bukan duplikasi.
6. **Kepsek — view-only**: banner **View-only access**, tanpa tombol tulis; timeline/rekap/log terbaca; API `POST` mutasi/cancel/correction → `403`.
7. **Admin — tahun locked**: pilih 2025/2026; badge **Locked**, pesan informatif, New In/Out nonaktif; rekap In 1/Out 1/Graduated 1/Retained 0; koreksi `0072` sekolah tujuan 91000002→91000001 dengan alasan dan approval; toast sukses; timeline menampilkan **Locked Correction** before/after dan aktor `UAT Admin Sistem`.
8. **English/390 px/console**: label English; 390 px tanpa overflow halaman/dialog; keyboard/ESC berfungsi; 0 error console.

**Temuan dan perbaikan selama 06c:**

- **Versi membership untuk dialog**: direktori siswa hanya mengirim id/nama kelas; dialog mutasi membutuhkan `membership_id`/`membership_version`. `StudentController::index` kini menyertakan keduanya; `StudentDirectoryTest` disesuaikan dan lulus **39 tes/501 assertions**.
- **NPSN pada audit**: `StudentRombel` allowlist belum memuat kolom NPSN mutasi. Ditambahkan `mutation_education_school_id` dan `latest_student_exit_school_id`; alasan bebas dan PII tetap dikecualikan.
- **Aksesibilitas tombol aksi**: tombol icon-only (timeline/cancel/correct) tidak memiliki nama aksesibel; ditambahkan `aria-label`; ESLint/build dijalankan ulang.
- **Bukan bug produk**: dialog di tab browser latar tertahan pada transisi leave karena throttling `document.visibilityState`; pada tab terlihat ESC menutup dan dialog keluar dari DOM. Pesan "No results found" berada di `<span class="p-hidden-accessible">` (screen-reader-only), bukan elemen visual.
- **Bukan bug produk**: section Mutations memakai pemilih tahun sendiri (default periode aktif), sehingga dapat menampilkan 2026/2027 saat halaman Students memilih tahun lain; ini sesuai D8.

## 5. Risiko dan keputusan

- **Immutable by design**: mutasi/koreksi tidak memiliki update/delete; kesalahan dikoreksi lewat pembatalan kompensatif atau tindakan locked yang tercatat. Tidak ada undo batch 06b.
- **Single-active tetap berlaku**: mutation-in membuat membership baru hanya setelah memastikan tidak ada membership aktif; mutation-out menutup satu membership sebelum trigger menilai. Trigger MySQL/MariaDB tidak diuji pada 06c.
- **Versi wajib**: seluruh operasi memakai versi siswa/membership/mutasi; UI menampilkan panel konflik dengan tombol Reload. Race dua tab nyata tetap NOT RUN.
- **Lock tahun**: mutasi normal hanya unlocked, koreksi hanya locked; tahun aktif tidak dapat dikunci (06a) sehingga koreksi tidak berlaku pada periode aktif.
- **Rekap**: `retained` bergantung pada batch progresi committed; batch preview tidak dihitung. Rekap semester ditunda karena `semesters` tanpa tanggal.
- **Batas bukti**: seluruh UAT memakai SQLite; MariaDB, race multi-proses, dan dua tab nyata tetap NOT RUN. Migrasi belum dijalankan pada database operasional.
- **Data UAT**: demo mutation-in/out/cancel dan koreksi locked sengaja dipertahankan agar pemilik dapat memeriksa timeline/rekap; tidak ada reset data 06a/06b.
- **UAT lifecycle**: server 14024/18024 dihentikan 2026-11-01 setelah penerimaan pemilik sesuai DEC-27 (PID/command line diverifikasi, port bebas, data `.uat-phase06` dipertahankan, Apache/MariaDB tidak disentuh).

## 6. Sinkronisasi dokumen

Diperbarui: `docs/API-CONTRACTS.md` (sepuluh endpoint dan aturan 06c), `docs/DATABASE.md` (tiga migration dan dua tabel + ability), `docs/ACCESS-CONTROL.md` (matriks route/ability 06c termasuk `students.correct-locked`), `docs/TESTING.md` (bukti dan NOT RUN 06c), `docs/DECISIONS.md` (catatan implementasi DEC-31), `docs/REQUIREMENTS.md` (FR-07), `CHANGELOG.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-06-ACADEMIC-YEAR.md`, `reports/feature-update/PHASE-06C-PLAN.md`, `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `PROJECT_CONTEXT.md`, laporan ini, dan checklist manual.

Commit/push dilakukan agar pemilik dapat meninjau implementasi di GitHub; commit/push bukan penerimaan manual. Tidak ada merge/deploy/force-push, perubahan `.env` utama, dependency/lockfile, service, DB terlarang, atau migrasi database operasional/MariaDB.

## 7. Langkah berikutnya

1. Checklist `PHASE-06C-MANUAL-REVIEW.md` **#1–#16 telah dijalankan pemilik dan APPROVED 2026-11-01 WIB** berdasarkan pernyataan **"Pengujian nomor 1-16 sudah berhasil dan saya approved."**; agen tidak mengulang skenario.
2. Penerimaan dicatat pada commit dokumentasi tersendiri; server UAT dihentikan 2026-11-01 sesuai DEC-27 (PID/command line diverifikasi, port bebas, data `.uat-phase06` dipertahankan).
3. Merge/deploy, migrasi database operasional/MariaDB, rekap semester, dan rekap sekolah (DEC-12) tetap tidak diotorisasi.
4. Fase berikutnya memerlukan rencana dan keputusan pemilik terpisah; jangan mulai otomatis.
