# Panduan Review Manual Fase 01c (Lingkungan UAT Sintetis)

Status penerimaan manual: **PENDING** (menunggu review mandiri pemilik setelah implementasi).
Lingkungan: UAT lokal terisolasi (DB MySQL `school_access_uat`, SPA mode `uat`).
URL SPA: `http://127.0.0.1:4001` | URL API: `http://127.0.0.1:8001`

---

## 1. Prasyarat Server UAT
Pastikan kedua server pengujian lokal berjalan:
```powershell
# Terminal 1 — API (dari direktori laravel-api)
php artisan serve --host=127.0.0.1 --port=8001 --env=uat

# Terminal 2 — SPA (dari direktori vue-app)
npm run dev:uat
```
Akses aplikasi melalui browser di `http://127.0.0.1:4001`.

---

## 2. Checklist Manual Pemilik Fase 01c

| # | Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| 1 | **DatabaseSeeder Bersih dari Akun Bawaan** | Periksa isi `DatabaseSeeder.php` atau jalankan `php artisan db:seed --class=DatabaseSeeder` pada database kosong | Seeder hanya mengisi data referensi murni (`ReferenceDataSeeder`); tidak ada akun pengguna atau kata sandi bawaan yang dibuat. | PENDING |
| 2 | **Pembersihan Data Siswa Riil Seeder (BLK-03)** | Periksa berkas `StudentSeeder.php` dan `StudentRombelSeeder.php` di repositori | Berkas bersih dari ribuan baris data siswa dan NIK riil; hanya memanggil `SyntheticDataSeeder` untuk lingkungan non-produksi. | PENDING |
| 3 | **Bootstrap Admin Interaktif via CLI** | Di terminal `laravel-api`, jalankan `php artisan app:bootstrap-admin` pada sistem yang belum memiliki admin aktif | Perintah menanyakan nama, username, email, NIK (16 digit), serta kata sandi diketik secara tersembunyi (tanpa echo di layar); menampilkan ringkasan lingkungan dan konfirmasi. | PENDING |
| 4 | **Bootstrap Admin Ditolak Bila Pengelola Akses Ada** | Di terminal `laravel-api`, jalankan `php artisan app:bootstrap-admin` pada sistem yang sudah memiliki admin aktif | Perintah langsung dibatalkan dengan pesan: *"An active user with access management capabilities already exists. Bootstrap aborted."* | PENDING |
| 5 | **Validasi Akun: Nama Boleh Sama (Homonim)** | Login sebagai `uat.admin` → buka menu Accounts (`/master/accounts`) → buat akun baru dengan Name yang sama persis dengan akun yang sudah ada (email & username berbeda) | Akun baru berhasil disimpan tanpa penolakan keunikan nama. | PENDING |
| 6 | **Validasi Akun: NIK Tepat 16 Digit** | Di menu Accounts (`/master/accounts`), coba masukkan NIK dengan 15 digit atau memuat huruf | Muncul pesan peringatan kuning *"NIK must be exactly 16 digits."* dan form menolak penyimpanan sebelum NIK tepat 16 digit. | PENDING |
| 7 | **Standar Password Akun Baru: Min 12 Karakter** | Di dialog New Account (`/master/accounts`), ketik password kurang dari 12 karakter | Muncul pesan peringatan kuning *"Password must be at least 12 characters."* | PENDING |
| 8 | **Standar Password Ubah & Reset: Min 12 Karakter** | Buka form Ubah Password di `/profile/user` atau form Reset Password di `/password-reset/:token` | Teks label memuat *"New Password (min 12 chars)"*; pengisian kata sandi kurang dari 12 karakter ditolak oleh validasi antarmuka. | PENDING |

---

## 3. Catatan Batasan & Keamanan
- Pengujian hanya menggunakan database sintetis `school_access_uat` dan SQLite in-memory `:memory:`.
- **BLK-03** ditutup pada source code aktif (HEAD). Riwayat Git masa lalu tidak diubah untuk menjaga stabilitas repositori.
- Keputusan terbuka mengenai makna `status_user_id` tidak menghambat Fase 01c dan tetap berstatus OPEN.
