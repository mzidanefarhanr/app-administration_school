# Laporan Subfase 03c — Daftar, Rekap, dan Ekspor Siswa (Server-side)

- Tanggal WIB: 2026-10-21
- Subfase: **03c — Daftar dan Rekap Siswa**
- Branch: `main`
- Baseline laporan: `0a15325` (HEAD saat implementasi dimulai)
- Commit keputusan: `4fa20c6` — `docs(phase-03c): record owner decisions for list, placement, recap, and export`
- Status: **IMPLEMENTED & VERIFIED (AGEN) / MANUAL ACCEPTANCE PENDING**
- Status fase: 03a–03b tetap **DITERIMA PEMILIK**; Fase 03 tetap **PARTIAL** (belum ditutup)
- Keputusan: **DEC-22** (APPROVED, 2026-10-21 WIB) — `docs/DECISIONS.md`
- Rencana acuan: `reports/feature-update/PHASE-03C-PLAN.md`

> Penerimaan manual pemilik terpisah dari bukti otomatis/browser agen ini. Penerimaan bukan izin deployment/akses data operasional.

---

## 1. Ringkasan

Daftar siswa tidak lagi client-side di atas `GET /api/student-rombels/{year}`. Kini:

1. `GET /api/students` = daftar **server-side** (pagination/pencarian nama-NIS-NISN/sort allowlist/filter tahun-rombel-placement) dengan **rekap server** yang tidak berubah saat berpindah halaman.
2. Filter **All / Placed / Unplaced**; `Unplaced` = tidak punya keanggotaan aktif tahun terpilih (bukan belum pernah punya rombel). Rombel dinonaktifkan saat `Unplaced`.
3. Atribut daftar **minimum** (tanpa NIK/birthdate/alamat/telepon/medis/penghasilan/alasan arsip).
4. `GET /api/students/export` memakai definisi filter yang sama; `unplaced` mengisi kolom rombel kosong (tanpa mengarang), total = seluruh hasil filter (bukan halaman aktif).
5. `Student.vue`: DataTable lazy, pencarian debounce, guard respons usang (`requestId`), loading/empty/error + Retry, pemulihan halaman terakhir yang kosong, Export mengikuti filter.

## 2. Perubahan backend

| Berkas | Perubahan |
|---|---|
| `laravel-api/app/Support/StudentDirectory.php` (baru) | Definisi filter bersama: `activeMembershipExists()`, `base()`, `place()`, `membershipsFor()`. Identitas `student_id` primer + fallback legacy `student_nik` (aman 03a); anti duplikasi via EXISTS; keanggotaan aktif = belum dihapus, belum diarsipkan, status Aktif/null, tanpa tanggal keluar. |
| `laravel-api/app/Http/Controllers/StudentController.php` | `index()` baru: validasi 422 English, `school_year_id` wajib, `per_page` `10/20/50/100` (default 20), `sort` allowlist + pembeda `id`, `placement all/placed/unplaced`, `rombel` (validasi FK + tahun; tolak `unplaced`+`rombel` 422). Respons `data`/`meta`/`recap`. **`only_archived=1` kini butuh `students.manage`** (menutup kebocoran NIK/alasan arsip ke akun view-only); `with_archived` dihapus. |
| `laravel-api/app/Http/Controllers/StudentExportController.php` | Berbasis siswa (bukan baris membership); memakai `StudentDirectory`; `placement` opsional (default `placed` agar perilaku tahun ajaran tetap); `unplaced` ikut tanpa rombel; batas baris tetap 422; kolom/leading-zero/escape/formula tidak berubah; tanpa kolom sensitif baru. `status`/`entry` (parameter lama) tidak lagi dipakai. |

Query daftar memakai kolom eksplisit; `count()`/`EXISTS` (bukan `get()` penuh) untuk paginasi/rekap; `per_page` dilindungi allowlist sehingga seluruh dataset tidak pernah diambil untuk dipaginasi di browser.

## 3. Perubahan frontend

`vue-app/src/views/pages/public/Student.vue`:

- DataTable `:lazy`, `@page`/`@sort`, `:totalRecords`/`:first` dari `meta`; kolom minimal: No, Name, Gender, NIS, NISN, Rombel, Placement, Action.
- Kontrol: Search (debounce 300 ms), Placement (All/Placed/Unplaced), Rombel (disabled saat Unplaced), Export, New Student, Archived, Clear.
- Rekap server ditampilkan (Total/Placed/Unplaced + gender).
- State: `listLoading`, `listError` + tombol Retry; empty state English; guard respons usang via `listRequestId`.
- Pemulihan halaman terakhir kosong setelah archive (mundur satu halaman) — terbukti di browser.
- Mutasi (create/edit/archive/restore) me-refresh daftar + rekap sesuai filter aktif; siswa baru tanpa rombel tetap ditemukan pada All/Unplaced setelah refresh.
- Dialog Archived 03b & aksinya dipertahankan (kini server menuntut `students.manage`).
- Tab `tabItems` dijadikan computed murni (efek samping pilih-tab dipindah ke `updateChartData`) — memperbaiki `vue/no-side-effects-in-computed-properties`.
- Aksi **penempatan rombel tidak dibangun** (di luar 03c, DEC-22 #5).

### Ekspor selalu mengirim `placement`
UI membangun parameter ekspor lewat `vue-app/src/views/pages/public/studentExportParams.mjs` (`buildStudentExportParams`), yang **selalu** menyertakan `placement` dan memakai `all` bila nilai tidak tersedia — sehingga default backend `placed` tidak pernah dipakai diam-diam saat daftar berada pada All. Dikunci oleh `vue-app/tests/student-export-params.test.mjs` (3 test) dan diverifikasi browser (lihat §5).

## 4. Bukti pengujian (hasil aktual)

Backend (dari `laravel-api`, DB uji terisolasi; `phpunit.xml` memaksa SQLite in-memory):

- `php artisan test tests/Feature/Student tests/Feature/Access/StudentExportTest.php` → **88 passed / 394 assertions**.
- `php artisan test` (penuh) → **229 passed / 1076 assertions**.
- Perintah baseline sebelum 03c: `php artisan test` = 209 passed. Tambahan 20 test 03c (satu berkas: `tests/Feature/Student/StudentDirectoryTest.php`).
- `./vendor/bin/pint --test` (4 berkas PHP 03c) → **PASS**.

Cakupan test backend 03c (`StudentDirectoryTest.php`):
guest 401; tanpa `students.view` 403; dengan view 200; tahun wajib & invalid 422; `per_page`/`sort`/`direction`/`placement`/`rombel` invalid 422; `unplaced`+`rombel` 422; paginasi default 20 + pilihan 10/20/50/100 halaman terakhir; sorting stabil (id tiebreaker); pencarian nama/NIS/NISN; All/Placed/Unplaced termasuk siswa baru tanpa rombel; riwayat lintas tahun + nonaktif + legacy tanpa double-count; siswa hanya-riwayat-nonaktif = unplaced; siswa terarsip tidak muncul di daftar/rekap; atribut minimum + tidak ada field sensitif; rekap identik lintas halaman; ekspor mengikuti search/rombel/placement lintas halaman; `unplaced` tanpa rombel; ekspor tanpa `students.export` 403; melebihi batas 422; default `placed` + scoping tahun; `only_archived` wajib `students.manage` 403 untuk view-only; kompatibilitas `version` 03b (update/archive/restore dari baris daftar).

Frontend:

- `npm run build` → **PASS** (788 modules; Student chunk `Student-CCLJsLdh.js` 101.83 kB).
- `node --test` → **18 passed / 0 fail** (15 lama + 3 test parameter ekspor 03c).

Lint terarah (blob commit, LF, konfigurasi repo sama):

| Berkas | Baseline `0a15325` | Setelah implementasi | **Final (pemeriksaan akhir, baseline `472ce9b`)** |
|---|---|---|---|
| `vue-app/src/views/pages/public/Student.vue` | 22 errors / 228 warnings | 20 errors / 218 warnings | **0 errors / 217 warnings** |
| `vue-app/src/main.js` | 0 errors / 66 warnings | tidak disentuh | tidak disentuh |
| `studentExportParams.mjs` (baru) + `tests/student-export-params.test.mjs` (baru) | — | — | **0 errors / 0 warnings** |

Koreksi pemeriksaan akhir: **20 error `no-unused-vars` diperbaiki terarah** dengan menghapus import/ref/fungsi mati (`reactive`, `watch`, `useRoute`, `RouterLink`, `useLayout`, `useConfirm`+`confirm`, `useRouter`+`router`, `CustomerService`, `all_students`, `saveCompany`, `titleWarning`, `fieldWarning`, `titleWaiting`, `fieldWaiting`, `visibleStaticBackdropWarnings/Dangers/Waitings/Successs`, `saveValidContinue`, `validContinue`, `viewStudentProfile`). Tidak ada `eslint-disable`, tidak menonaktifkan aturan, tidak menjalankan auto-format massal. Perilaku CRUD/filter/rekap/ekspor tidak berubah (dibuktikan build + browser smoke). Warning tersisa (217) bersifat kosmetik/pre-existing (termasuk Prettier) — dicatat sebagai keterbatasan, bukan PASS bersih.


## 5. Browser UAT (Playwright, UAT sintetis `school_access_uat`)

Halaman `/public/students`, API `http://127.0.0.1:8001`, SPA `http://127.0.0.1:4001`.

| Skenario | Hasil |
|---|---|
| Daftar memuat | Total/Placed/Unplaced + tabel + paginasi tampil |
| Filter Unplaced | 3 siswa, Rombel **disabled**, kolom rombel `-` |
| Pencarian (NIS `UAT-BROWSER-001`) | 1 siswa, total 1 |
| Clear | kembali All, total 5 |
| Create tanpa rombel → refresh | `UAT 03C No Rombel` muncul pada All (total 6→31 setelah seed) |
| Paginasi | default 20, `Showing 1 to 20 of 31`; halaman 2; rows-per-page 10; Last Page; halaman terakhir kosong → pulih ke halaman sebelumnya (`11 to 20 of 20`) |
| Sort | header Name memicu `sort=name&direction=desc`; request 200 |
| Rekap lintas halaman | identik (31/12/19 lalu 30/12/18) |
| Stale guard | respons lambat `placement=unplaced` **tidak** menimpa hasil `placed` terbaru (list, rekap, loading tetap benar) |
| Error + Retry | route /api/students di-abort → pesan error + tombol Retry; Retry memuat ulang |
| Empty state | pencarian tanpa hasil → "No students match the current filters." |
| Export | unduhan `students_1.csv`; Unplaced 14 baris rombel kosong; Placed 12 baris semua ada rombel; leading zero (`0000000000000001`, `001`) terjaga |
| Export placement (pemeriksaan akhir) | All → request `...&placement=all`, CSV **27 baris = total daftar All 27**; Placed → `...&placement=placed`, CSV **12 = recap Placed 12** dan semua berisi rombel; Unplaced → recap 15, Rombel disabled |
| Detail/Edit | dialog detail tampil (Record Version); edit NISN tidak valid → 422 inline (perilaku 03b tetap); setelah NISN valid → tersimpan & daftar refresh |
| Archive/Restore | arsip dari dialog (wajib alasan); restore mengembalikan siswa (daftar 26→27); rombel lama tetap nonaktif |
| Izin operator (`uat.operator`) | New Student & Archived tampil; aksi baris View/Edit/Archive |
| Izin view-only (`uat.kepsek`) | New Student & Archived **tersembunyi**; aksi baris hanya View (1 tombol); POST `/api/students` 403; `only_archived=1` **403** (setelah perbaikan); daftar 200; Export tampil (benar: `kepala-sekolah` memang punya `students.export` per katalog awal DEC-03) |

Catatan: dua hasil awal yang tampak gagal adalah artefak skrip uji (teks alasan tertulis ke kotak Search, dan snapshot diambil sebelum refresh selesai); setelah diuji ulang dengan benar, arsip/refresh/pemulihan halaman berjalan sesuai harapan.

## 6. Keterbatasan & catatan

- **Kredensial akun UAT berubah.** Kata sandi `uat.operator`/`uat.kepsek` pada DB UAT disposable (`school_access_uat`) direset saat uji karena kata sandi sesi sebelumnya tidak tersedia. Kata sandi **tidak** ditulis pada dokumen ini. Cara pemilik menetapkan kata sandi lokal (tanpa mengubah lagi di luar kebutuhan): jalankan seeder UAT dengan env `UAT_SEED_PASSWORD` di `.env.uat`/lingkungan shell, lalu login; atau reset hanya akun sintetis lewat Tinker `--env=uat`. Hanya akun UAT sintetis; bukan akun operasional.
- **Data UAT ditambah.** Seed siswa sintetis bertanda `UAT03C Bulk 01–25` (10 placed, 15 unplaced; NIK `90000000000002xx`) pada tahun `2026/2027` untuk menguji paginasi; sebagian diarsipkan/restore selama uji. Tidak menyentuh DB operasional `backdata_administrations`.
- **Tidak ada deployment.** Tidak menjalankan deploy; ketiadaan `.github/workflows` tidak disimpulkan sebagai "tidak ada auto-deploy".
- **NOT RUN:** pengujian MariaDB khusus 03c (test 03c berjalan pada SQLite in-memory sesuai `phpunit.xml`; test konkurensi MariaDB 03b tidak dijalankan ulang), suite backend diulang (backend tidak berubah pada pemeriksaan akhir), dan pemeriksaan manual pemilik.
- `status`/`entry` pada ekspor tidak lagi difilter (kini atribut keanggotaan, bukan filter siswa) dan `with_archived` dihapus; tidak ada consumer Vue yang memakai keduanya saat perubahan ini.
- **Warning ESLint tersisa 217** (kosmetik/pre-existing). Bukan PASS bersih; tidak diperbaiki massal agar diff tetap terarah.

## 7. Berkas berubah (implementasi)

- `laravel-api/app/Support/StudentDirectory.php` (baru)
- `laravel-api/app/Http/Controllers/StudentController.php`
- `laravel-api/app/Http/Controllers/StudentExportController.php`
- `laravel-api/tests/Feature/Student/StudentDirectoryTest.php` (baru)
- `vue-app/src/views/pages/public/Student.vue`
- `vue-app/src/views/pages/public/studentExportParams.mjs` (baru; pemeriksaan akhir)
- `vue-app/tests/student-export-params.test.mjs` (baru; pemeriksaan akhir)

## 8. Langkah berikutnya

1. Review pemilik checklist `reports/feature-update/PHASE-03C-MANUAL-REVIEW.md`.
2. Setelah persetujuan manual: update status 03c → **DITERIMA PEMILIK** dan tutup Fase 03 bila seluruh subfase selesai.
3. Aksi penempatan rombel via UI belum dibangun (di luar 03c) — kandidat subfase berikutnya.
