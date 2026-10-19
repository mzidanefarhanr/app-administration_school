# Panduan Review Manual Subfase 02b — Backup Manual dan Restore Drill

- Tanggal WIB: 2026-10-19
- Subfase: **02b — Backup Manual dan Restore Drill**
- Branch: `main`
- Status teknis: **IMPLEMENTED & AUTOMATED TESTS PASS (125/125, MariaDB integration round-trip PASS)**
- Status checklist pemilik: **PENDING (Menunggu Pemeriksaan Manual Pemilik)**
- Database & storage drill: MySQL lokal port 3306 target `school_restore_drill_uat` (disposable).

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

---

## 2. Checklist Pemeriksaan Manual

Status: **PENDING** = menunggu verifikasi dan jawaban pemilik; **APPROVED** = pemilik telah menyetujui hasil uji manual.

| # | Skenario Uji | Tindakan / Perintah | Hasil yang Diharapkan | Status Pemilik |
|---|---|---|---|---|
| 1 | Akses Tamu (Unauthenticated) | Panggil cURL atau tab penyamaran: `curl.exe -s -H "Accept: application/json" http://127.0.0.1:8001/api/backups` | Mengembalikan **HTTP 401 Unauthorized** (`{"message":"Unauthenticated."}`). Akses ditolak. | **PENDING** |
| 2 | Otorisasi Akses Operator (Tanpa `backup.manage`) | Login sebagai `uat.operator` di browser (`http://127.0.0.1:4001/auth/login`). Buka console browser (F12) lalu jalankan: `await fetch('http://127.0.0.1:8001/api/backups', { credentials: 'include', headers: { 'Accept': 'application/json' } }).then(r => r.status)` | Mengembalikan status **403 Forbidden** (`{"message":"This action is unauthorized."}`). Operator tidak dapat melihat daftar backup. | **PENDING** |
| 3 | Pembacaan Daftar Backup oleh Admin | Login sebagai `uat.admin` di browser. Buka console (F12) lalu jalankan: `const res = await fetch('http://127.0.0.1:8001/api/backups', { credentials: 'include', headers: { 'Accept': 'application/json' } }); const json = await res.json(); console.log(res.status, json);` | Mengembalikan status **200 OK** dengan format JSON daftar berkas (`data`). Tidak ada kredensial atau passphrase yang tampak di respons. | **PENDING** |
| 4 | Eksekusi Pencadangan Manual via CLI | Buka terminal di folder `laravel-api`, jalankan: `php artisan app:backup-run --passphrase="UatSecurePassphrase2026!" --connection=mysql` | Proses berjalan sukses: tercetak nama arsip `backup-uat-<timestamp>.zip`, ukuran berkas, checksum SHA-256, dan berkas pendamping `.sha256`. Direktori temporer `.tmp-*` bersih. | **PENDING** |
| 5 | Pembuktian Enkripsi ZIP AES-256 | Buka file ZIP hasil backup di `laravel-api/storage/app/backups/` menggunakan aplikasi arsip (misal 7-Zip/WinRAR). Coba ekstrak file `manifest.json` tanpa password, lalu coba dengan password yang benar. | Ekstraksi tanpa password ditolak (meminta password enkripsi AES-256). Dengan password yang benar (`UatSecurePassphrase2026!`), berkas `manifest.json` dan `database.sql` dapat diekstrak secara utuh. | **PENDING** |
| 6 | Unduhan Berizin via API | Di sesi `uat.admin` pada console browser (F12), panggil endpoint download: `window.location.href = 'http://127.0.0.1:8001/api/backups/<nama_file_zip>/download'` | Berkas `.zip` terunduh langsung ke browser. Response header memuat `Content-Type: application/zip` dan `Cache-Control: no-store, private`. Berkas disajikan dari storage privat tanpa URL publik. | **PENDING** |
| 7 | Penolakan Path Traversal Unduhan | Di sesi `uat.admin`, coba unduh berkas dengan karakter traversal via console: `await fetch('http://127.0.0.1:8001/api/backups/..%2F..%2F.env/download', { credentials: 'include' }).then(r => r.status)` | Server menolak dengan status **400 Bad Request** (`Invalid backup filename` atau `Path traversal detected`). Berkas sistem di luar direktori backup tidak dapat diakses. | **PENDING** |
| 8 | Eksekusi Restore Drill ke Target Disposable | Jalankan perintah drill di terminal: `php artisan app:restore-drill <nama_file_zip> --passphrase="UatSecurePassphrase2026!" --target-connection=restore_drill --force` | Proses pemulihan sukses: tercetak `=== RESTORE DRILL VERIFICATION: PASS ===`. Seluruh tabel diverifikasi, foreign key utuh, akun admin terverifikasi (tanpa mencetak password/hash). Data berhasil masuk ke database target `school_restore_drill_uat`. | **PENDING** |
| 9 | Uji Proteksi Fail-Safe Target Operasional | Jalankan perintah drill dengan connection atau database palsu yang mengarah ke `backdata_administrations`: `php artisan app:restore-drill <nama_file_zip> --passphrase="UatSecurePassphrase2026!" --target-connection=fake_operational` (atau tes automated) | Perintah menolak keras dan menggugurkan proses seketika dengan pesan `SECURITY ERROR: Target database ... is the protected operational database! Restore drill is strictly forbidden.` | **PENDING** |
| 10 | Uji Penolakan Passphrase Salah | Jalankan perintah drill dengan passphrase salah: `php artisan app:restore-drill <nama_file_zip> --passphrase="WrongPassword123!" --target-connection=restore_drill --force` | Perintah gagal dengan pesan error dekripsi / manifest missing. Tidak ada data yang diimpor ke database target. | **PENDING** |

---

## 3. Cara Memberikan Persetujuan Pemilik

Setelah Anda memeriksa butir-butir di atas pada lingkungan lokal, kirim instruksi:
```text
Catat hasil pemeriksaan manual saya untuk subfase 02b pada kandidat <SHA>.
Hasil: checklist nomor #1–#10 APPROVED.
```
Hasil pemeriksaan manual Anda akan dicatat dalam commit dokumentasi tersendiri sesuai aturan repo.
