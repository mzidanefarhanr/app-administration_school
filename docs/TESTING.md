# Strategi pengujian dan bukti

## Baseline
Tes yang ditemukan terutama auth Breeze dan ExampleTest. Belum dijalankan. `laravel-api/phpunit.xml` mengomentari DB_CONNECTION sqlite dan DB_DATABASE :memory:. Jangan menyalin klaim App Gudang bahwa tes sudah aman memakai SQLite.

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
