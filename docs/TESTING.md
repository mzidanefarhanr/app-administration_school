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
Dari vue-app: `npm run build`; `npx eslint <path-berubah>` non-fix bila ESLint tersedia. Baseline script lint --fix memutasi file. Frontend unit/E2E runner baru belum ada, pilih hanya jika kebutuhan fase membenarkan penambahan.
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
