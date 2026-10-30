# Strategi pengujian dan bukti

## Baseline
Tes yang ditemukan terutama auth Breeze dan ExampleTest. `laravel-api/phpunit.xml` semula mengomentari DB_CONNECTION sqlite dan DB_DATABASE :memory:. Jangan menyalin klaim App Gudang bahwa tes sudah aman memakai SQLite.

## Isolasi aktual (Fase 01a) — SUDAH diterapkan
- `laravel-api/.env.testing`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `CACHE_DRIVER=array`, `SESSION_DRIVER=array`. Tidak memuat kredensial operasional.
- `phpunit.xml`: `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` dengan `force="true"`.
- Fail-safe: `tests/CreatesApplication.php::guardAgainstUnsafeTestDatabase()` membatalkan suite bila APP_ENV ≠ testing atau koneksi bukan sqlite in-memory. Diverifikasi: guard memblokir konfigurasi mysql `backdata_administrations`.
- Tidak ada `bootstrap/cache/config.php`/route cache (tidak ada koneksi operasional dari cache).
- Fixture referensi (type_users/status_users) + peran/kemampuan dibuat sintetis via `tests/Pest.php` dan `tests/Support/CreatesAccessFixtures.php`. `DatabaseSeeder` (identitas asli) TIDAK dijalankan pada tes.
- SQLite in-memory kompatibel dengan seluruh 35 migration (enum → varchar+check, FK diaktifkan).

## Lingkungan UAT terisolasi (Fase 01a, 2026-10-15)
- Database UAT MySQL disposable `school_access_uat` (terpisah dari operasional `backdata_administrations` dan dari DB tes sqlite in-memory).
- Konfigurasi: `laravel-api/.env.uat` (lokal, di-ignore) dari contoh `laravel-api/.env.uat.example`. Koneksi efektif diverifikasi (`--env=uat` → mysql/school_access_uat) sebelum migrasi.
- Fixture sintetis `UatSeeder`: `uat.admin`, `uat.operator`, `uat.kepsek`, `uat.waka`, `uat.inactive`. Kata sandi dari env `UAT_SEED_PASSWORD` (tidak di dokumen). `DatabaseSeeder` identitas asli TIDAK dijalankan.
- Frontend UAT: `VITE_API_BASE_URL` (mis. `http://127.0.0.1:8001`) via `vue-app/.env.uat` (lokal, di-ignore). Tidak memakai URL hardcode.
- Cara jalan: API `php artisan serve --host=127.0.0.1 --port=8001 --env=uat`; SPA `npx vite --mode uat --host 127.0.0.1 --port 4001`.
- **Guard DB tes otomatis tidak dikendurkan**: `phpunit.xml` + fail-safe `tests/CreatesApplication.php` tetap memaksa sqlite in-memory.
- **Penyelesaian 01a**: `UatSeeder` juga membuat data siswa sintetis (termasuk nilai awal nol dan nama berformula) untuk UAT ekspor. Tes otomatis ekspor: `tests/Feature/Access/StudentExportTest.php` (guest/nonaktif/tanpa view/tanpa export/dua izin/leading zero/CSV escaping/neutralisasi formula/lingkup tahun ajaran/validasi input/pencabutan izin pada sesi aktif). Total suite 47 tes.

## Isolasi sebelum tes
Tetapkan `.env.testing`/konfigurasi test yang tidak berisi koneksi sekolah aktif. Pastikan config cache tidak mengarahkan test ke DB development/produksi. Gunakan database disposable dengan nama khusus; tambahkan guard yang menggagalkan tes jika nama/driver tidak sesuai. SQLite boleh untuk tes cepat bila migration kompatibel; FK, enum, unique dan concurrency yang kritis harus diuji MySQL terisolasi. Verifikasi database runtime tanpa mencetak password.
Storage uji memakai fake/temp disk; backup/restore mengarah ke target test, tidak ke direktori aktif. Seeder uji sintetis. Jangan menjalankan RefreshDatabase atau migrate:fresh sampai isolasi dipastikan.

## Subfase 05a — impor siswa (bukti 2026-10-30)
- `laravel-api/tests/Feature/Import/StudentImportTest.php` (baru): **18 tes / 97 assertion PASS** — template berizin, guest/tanpa kemampuan, unggah valid → preview tanpa mutasi, campuran error → batch `invalid` + CSV error privat, commit all-or-nothing, duplikat dalam berkas, mode update match NIS lalu NIK (bukan nama), batch pembuat lain → 404, batch kedaluwarsa/berubah → 409 tanpa mutasi, netralisasi formula CSV, dan batas baris. Memakai `Storage::fake('local')` dan `RefreshDatabase` pada SQLite in-memory terjaga.
- Suite regresi pada isolated testing config: **170 tes / 1.097 assertion / 13 skipped PASS**.
- `./vendor/bin/pint --test` pada file PHP berubah: **PASS** (16 file).
- Frontend: `vue-app/tests/student-import-params.test.mjs` (baru, `node --test`) **3 tes PASS**; `npm run build` **PASS 26,02 s**.
- UAT terisolasi `.uat-phase05` (SQLite, API `127.0.0.1:18023`, SPA `127.0.0.1:14023`, cookie `school_phase05_session`): `prepare`/`status` sukses; 300 siswa sintetis + 200 pegawai (100 guru/100 staf); kemampuan `students.import` hadir untuk dua peran. Browser DOM: impor valid 406→409 siswa, campuran 1 valid/2 error ditolak tanpa mutasi (total tetap 409), update via NIS, unduh template, **0 console error**.
- **NOT RUN**: verifikasi UAT via MariaDB/InnoDB, konkurensi dua tab, dan pemeriksaan visual screenshot. Bukti ini bukan penerimaan pemilik; checklist manual 05a **APPROVED 2026-10-30 WIB (#1–#12)** berdasarkan pernyataan pemilik, bukan pengujian ulang agen. Item NOT RUN tetap NOT RUN.

## Subfase 05b — impor pegawai & pilihan kolom ekspor (bukti 2026-10-30)
- `laravel-api/tests/Feature/Import/EmployeeImportTest.php` (baru): **28 tes** — guest/kemampuan, batch domain lain → 404, template 22 header, preview tanpa mutasi, leading zero/date mapping, header salah, field inti, Subject Teacher/Staff, duplikat NIK termasuk arsip, Subject ambigu/arsip, Regency+District ambigu/tidak cocok, update parsial/imutabilitas NIK/validasi gabungan/tipe Staff mengosongkan Subject, tanpa perubahan, rollback atomik (0 employee, 0 activity, batch tetap `ready`), idempotensi, TTL/hash, batas berkas/baris, header & formula CSV error, audit allowlist, BOM + delimiter `;`.
- `laravel-api/tests/Feature/Employee/EmployeeExportColumnsTest.php` (baru): **15 tes** — options gating, default 10 kolom, subset urut registry, `columns` kosong → default, kolom tak dikenal → 422, sensitif tanpa manage → 403 / dengan manage → 200, label & field personal, netralisasi formula, filter/arsip, batas 10.000 → 422, filter invalid.
- Gabungan kedua berkas: **43 tes / 249 assertions PASS** (`php artisan test tests/Feature/Import/EmployeeImportTest.php tests/Feature/Employee/EmployeeExportColumnsTest.php`, 18,65 s). Regresi penuh suite: **402 passed / 13 skipped / 3.587 assertions PASS**; regresi impor siswa 05a tetap **18/97 PASS**.
- `./vendor/bin/pint --test` pada 15 file PHP berubah/baru: **PASS 15 files**.
- Frontend: `vue-app/tests/employee-import-params.test.mjs` (baru) + tambahan tes kolom di `employee-directory-params.test.mjs` + `student-import-params.test.mjs` → `node --test` **15 tes PASS**; `npx eslint` file baru **0 error** (warning Prettier lama pada `EmployeeDirectory.vue`); `npm run build` **PASS 28,46 s** (warning chunk >500 kB sudah ada sebelumnya).
- UAT terisolasi `.uat-phase05`: sampel baru `employees-import-valid.csv`, `employees-import-mixed.csv`, `employees-import-update.csv` (22 kolom; nilai referensi UAT: Pendidik/Tenaga Kependidikan, Guru Tetap/Pegawai Tetap Yayasan, employment `Guru Mata Pelajaran`/`Guru Bimbingan Konseling`/`Tenaga Administrasi Sekolah`, Matematika/Bahasa Inggris, Synthetic Regency/District, NIK leading zero `0580000000000001`). `prepare` menerapkan migration 05b dan mempertahankan 409 siswa/200 pegawai. **Browser/UI UAT 05b NOT RUN**: otomasi browser diblokir kebijakan sesi ini; verifikasi UI mengandalkan build + lint + tes helper. Checklist manual disediakan.
- **NOT RUN**: browser/visual UAT 05b, MariaDB/InnoDB, two-tab concurrency, retensi otomatis (manual 30 hari), rekap sekolah (05c), XLSX. Item ini tidak boleh dicatat PASS sampai dijalankan.
- **Penerimaan pemilik (2026-10-31 WIB):** pernyataan pemilik **"Pengujian nomor 1-14 sudah berhasil dan saya approved."** menetapkan checklist 05b #1–#14 **APPROVED**; agen tidak mengulang skenario dan item NOT RUN tetap NOT RUN. Setelah penerimaan, server UAT 04b (14022/18022) dan 05 (14023/18023) dihentikan sesuai kebijakan siklus UAT per fase (DEC-27); data `.uat-phase04b`/`.uat-phase05` dipertahankan dan perintah restart tercatat di `PHASE-05B-MANUAL-REVIEW.md` dan HANDOFF. Port UAT terverifikasi bebas; Apache/MariaDB tidak disentuh.

## Matriks minimum per perubahan
| Area | Kasus wajib |
|---|---|
| Akses | guest, akun nonaktif, role diizinkan/ditolak, objek luar scope, export/download |
| CRUD | create/update/arsip/restore sah, identitas duplikat, null, FK invalid, nama sama |
| Konkurensi | stale version, dua submit, nomor surat paralel, retry job |
| Transaksi | kegagalan setelah penulisan pertama; rollback data dan audit; decoy tidak berubah |
| Impor | preview tidak mutasi, semua-valid, campuran error, file duplikat, leading zero, partial result sesuai kebijakan |
| Tahun ajaran | naik/tinggal/lulus/mutasi, riwayat lama utuh, periode dikunci |
| Surat/izin | transisi sah/tidak sah, keputusan berulang, penandatangan, snapshot PDF |
| Pembelajaran | bentrok jadwal, cancelled/rescheduled, sesi belum ada jurnal, jurnal kelas lain |
| Backup | manifest lengkap, checksum, missing file, disk penuh, restore database+file terisolasi |
| UI | empty/error/loading, keyboard, mobile, light/dark, session expired |

## Perintah
Dari laravel-api: `php artisan test --filter=<test-relevan>` setelah isolasi; Pint `./vendor/bin/pint --test <path-berubah>`.
Dari vue-app: `npm run build`; `npx eslint <path-berubah>` non-fix bila ESLint tersedia. Baseline script lint --fix memutasi file. Sejak 05a tersedia unit test Node murni via `node --test tests/*.test.mjs` (contoh `student-import-params.test.mjs`); belum ada runner E2E browser.
Jalankan tes relevan dahulu, lalu suite regresi pada milestone perubahan lintas domain. Bedakan tes yang baru ditambahkan, baseline gagal, perubahan gagal, dan yang tidak dijalankan.

## Status bukti
- NOT RUN: belum dieksekusi.
- PASS: skenario spesifik benar-benar dijalankan, assertion/hasil tersedia.
- FAIL: hasil aktual tidak sesuai.
- BLOCKED: prasyarat tidak tersedia, tulis penyebab.
- N/A: tidak berlaku dengan alasan.
Build PASS bukan fungsi PASS. Source review bukan browser PASS. Approval manual bukan otomatis seluruh role/alur PASS.
Bukti setiap item: tanggal, branch/SHA atau working tree, lingkungan, fixture, langkah/command, expected, actual, result, artefak tanpa data pribadi. Catat jumlah test hanya dari output aktual.

## Checklist manual bagi pemilik
Jika OpenCode tidak punya browser, jelaskan keterbatasan sebelum meminta pemeriksaan. Kirim URL/halaman, akun uji/peran, langkah, hasil yang diharapkan, dan cara melaporkan error console/network. Jangan meminta pemilik menguji mutasi pada data operasional.
