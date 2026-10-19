# Panduan Review Manual Subfase 02b — Backup Manual dan Restore Drill

- Tanggal WIB: 2026-10-19
- Subfase: **02b — Backup Manual dan Restore Drill**
- Branch: `main`
- Status teknis: **IMPLEMENTED & AUTOMATED TESTS PASS (132/132, MariaDB integration round-trip PASS)**
- Status checklist pemilik: **PENDING (Menunggu Pemeriksaan Manual Pemilik)**
- Database & storage drill: MySQL lokal port 3306 target `school_restore_drill_uat` (disposable, wajib dalam keadaan bersih/kosong sebelum drill).

---

## 1. Persiapan Lingkungan UAT Lokal

Pastikan server UAT lokal aktif pada port `8001` (API) dan `4001` (SPA) menggunakan database disposable `school_access_uat`:
- **API UAT**: `http://127.0.0.1:8001` (`php artisan serve --host=127.0.0.1 --port=8001 --env=uat`)
- **SPA UAT**: `http://127.0.0.1:4001` (`npm run dev` / `vite --mode uat --host 127.0.0.1 --port 4001`)

Akun UAT sintetis yang tersedia:
- `uat.admin` (System Administrator — memiliki kemampuan `backup.manage`)
- `uat.operator` (School Operator — **tidak** memiliki `backup.manage`)

Database target drill:
- Target koneksi: `restore_drill` (database `school_restore_drill_uat` pada MySQL lokal port 3306).
- **Prasyarat Target**: Database target drill harus bersih/kosong (tanpa tabel) sebelum drill dijalankan. Sistem menolak menimpa database target yang sudah berisi data demi mencegah data loss.

---

## 2. Checklist Pemeriksaan Manual

Status: **PENDING** = menunggu verifikasi dan jawaban pemilik; **APPROVED** = pemilik telah menyetujui hasil uji manual.

| # | Skenario Uji | Tindakan / Perintah | Hasil yang Diharapkan | Status Pemilik |
|---|---|---|---|---|
| 1 | Akses Tamu (Unauthenticated) | Panggil cURL atau tab penyamaran: `curl.exe -s -H "Accept: application/json" http://127.0.0.1:8001/api/backups` | Mengembalikan **HTTP 401 Unauthorized** (`{"message":"Unauthenticated."}`). Akses ditolak. | **PENDING** |
| 2 | Otorisasi Akses Operator (Tanpa `backup.manage`) | Login sebagai `uat.operator` di browser (`http://127.0.0.1:4001/auth/login`). Buka console browser (F12) lalu jalankan: `await fetch('http://127.0.0.1:8001/api/backups', { credentials: 'include', headers: { 'Accept': 'application/json' } }).then(r => r.status)` | Mengembalikan status **403 Forbidden** (`{"message":"This action is unauthorized."}`). Operator tidak dapat melihat daftar backup. | **PENDING** |
| 3 | Pembacaan Daftar Backup oleh Admin | Login sebagai `uat.admin` di browser. Buka console (F12) lalu jalankan: `const res = await fetch('http://127.0.0.1:8001/api/backups', { credentials: 'include', headers: { 'Accept': 'application/json' } }); const json = await res.json(); console.log(res.status, json);` | Mengembalikan status **200 OK** dengan format JSON daftar berkas (`data`). Tidak ada kredensial atau passphrase yang tampak di respons. | **PENDING** |
| 4 | Eksekusi Pencadangan Manual via CLI | Buka terminal di folder `laravel-api`, jalankan perintah dengan `--env=uat`: `php artisan --env=uat app:backup-run --connection=mysql`<br>Ketik passphrase secara interaktif pada secret prompt (min 12 karakter) beserta konfirmasi. | Informasi koneksi efektif ditampilkan tanpa kredensial (`Source Target: host=127.0.0.1, port=3306, database=school_access_uat`). Proses berjalan sukses: tercetak nama arsip `backup-uat-<timestamp>.zip`, ukuran berkas, checksum SHA-256, dan berkas pendamping `.sha256`. Direktori temporer `.tmp-*` bersih. | **PENDING** |
| 5 | Pembuktian Enkripsi ZIP AES-256 | Buka file ZIP hasil backup di `laravel-api/storage/app/backups/` menggunakan aplikasi arsip (misal 7-Zip/WinRAR). Coba ekstrak file `manifest.json` tanpa password, lalu coba dengan password yang Anda ketik saat backup. | Ekstraksi tanpa password ditolak (meminta password enkripsi AES-256). Dengan password yang benar, berkas `manifest.json` dan `database.sql` dapat diekstrak secara utuh. | **PENDING** |
| 6 | Unduhan Berizin via API | Di sesi `uat.admin` pada console browser (F12), panggil endpoint download: `window.location.href = 'http://127.0.0.1:8001/api/backups/<nama_file_zip>/download'` | Berkas `.zip` terunduh langsung ke browser. Response header memuat `Content-Type: application/zip` dan `Cache-Control: no-store, private`. Berkas disajikan dari storage privat tanpa URL publik. | **PENDING** |
| 7 | Penolakan Path Traversal Unduhan | Di sesi `uat.admin`, coba unduh berkas dengan karakter traversal via console: `await fetch('http://127.0.0.1:8001/api/backups/..%2F..%2F.env/download', { credentials: 'include' }).then(r => r.status)` | Server menolak dengan status **400 Bad Request** (`Invalid backup filename` atau `Path traversal detected`). Berkas sistem di luar direktori backup tidak dapat diakses. | **PENDING** |
| 8 | Eksekusi Restore Drill ke Target Disposable | Pastikan database `school_restore_drill_uat` kosong. Jalankan perintah drill dengan `--env=uat`: `php artisan --env=uat app:restore-drill <nama_file_zip> --target-connection=restore_drill`<br>Ketik passphrase secara interaktif pada secret prompt. | Informasi target efektif ditampilkan (`Effective Target: host=127.0.0.1, port=3306, database=school_restore_drill_uat`). Proses pemulihan sukses: tercetak `=== RESTORE DRILL VERIFICATION: PASS ===`. Seluruh tabel diverifikasi, foreign key utuh, akun admin terverifikasi (tanpa mencetak password/hash). Data berhasil masuk ke database target `school_restore_drill_uat`. | **PENDING** |
| 9 | Uji Penolakan Target Berisi Data | Jalankan kembali perintah drill yang sama ke database `school_restore_drill_uat` yang kini sudah berisi tabel dari langkah #8: `php artisan --env=uat app:restore-drill <nama_file_zip> --target-connection=restore_drill` | Perintah menolak keras dengan pesan error: `Target database 'school_restore_drill_uat' already contains data (... tables). Target must be a clean/empty disposable database to prevent data overwriting.` Data target semula tetap utuh dan tidak dihapus otomatis. | **PENDING** |
| 10 | Uji Penolakan Passphrase Salah | Kosongkan kembali tabel target atau buat target drill kosong baru, lalu jalankan drill dengan password yang sengaja disalahkan pada secret prompt. | Perintah gagal dengan pesan error dekripsi / manifest missing. Tidak ada data yang diimpor ke database target. | **PENDING** |

---

## 3. Cara Memberikan Persetujuan Pemilik

Setelah Anda memeriksa butir-butir di atas pada lingkungan lokal, kirim instruksi:
```text
Catat hasil pemeriksaan manual saya untuk subfase 02b pada kandidat <SHA>.
Hasil: checklist nomor #1–#10 APPROVED.
```
Hasil pemeriksaan manual Anda akan dicatat dalam commit dokumentasi tersendiri sesuai aturan repo.
