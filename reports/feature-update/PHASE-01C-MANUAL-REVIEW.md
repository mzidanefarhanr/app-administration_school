# Panduan Review Manual Fase 01c (Lingkungan UAT Sintetis & Disposable Bootstrap)

Status penerimaan manual: **APPROVED** (seluruh checklist nomor 1–8 telah diuji manual dan dinyatakan **APPROVED** oleh pemilik pada 17 Oktober 2026 WIB mengacu baseline `844883e`).
Status subfase sebelumnya:
- **Fase 01a (Authorization Backend)**: **DITERIMA PEMILIK** (seluruh checklist #1–#14 APPROVED pada 15 Oktober 2026 WIB).
- **Fase 01b (Konfigurasi API & Error Auth)**: **DITERIMA PEMILIK** (seluruh checklist #1–#16 APPROVED pada 16–17 Oktober 2026 WIB).
- **Fase 01c (Isolasi Fixture & Bootstrap)**: **DITERIMA PEMILIK** (seluruh checklist #1–#8 APPROVED pada 17 Oktober 2026 WIB).
- **Fase 01 Secara Keseluruhan**: **DITUTUP LENGKAP** dalam cakupan roadmap (01a, 01b, dan 01c seluruhnya DITERIMA PEMILIK).
- **Fase 02 dan selanjutnya**: **NOT STARTED** (rencana Fase 02 disiapkan terpisah untuk ditinjau pemilik).

---

## 1. Aturan Keselamatan Database & Lingkungan UAT

1. **LARANGAN MUTLAK Database Operasional**:
   - DILARANG KERAS menjalankan perintah mutasi, migrasi, atau seeder apa pun terhadap database operasional sekolah (`backdata_administrations`).
   - Database operasional tidak boleh disentuh dalam bentuk apa pun.
2. **Perlindungan Database UAT Aktif (`school_access_uat`)**:
   - DILARANG menjalankan `migrate:fresh`, pengosongan data, atau penghapusan akun administrator pada database `school_access_uat` hanya untuk menguji skenario bootstrap.
   - Pengujian skenario bootstrap WAJIB menggunakan **database disposable khusus bootstrap** (`school_bootstrap_uat` atau file SQLite disposable), terpisah sepenuhnya dari `school_access_uat`.
3. **Eksplisit Environment & Pre-Flight Check**:
   - Setiap perintah Artisan mutasi WAJIB menyertakan flag lingkungan secara eksplisit (misal: `--env=uat` atau `--env=bootstrap`).
   - Sebelum menjalankan perintah migrasi atau seeder, WAJIB menjalankan verifikasi koneksi efektif dan nama database.
4. **Kerahasiaan Kata Sandi**:
   - Kata sandi pengujian (baik untuk bootstrap, pembuatan akun, ubah password, maupun reset) adalah pilihan bebas pemilik yang memenuhi syarat (minimal 12 karakter).
   - Nilai kata sandi DILARANG dicetak, dicatat, atau disimpan ke dalam laporan, terminal log, atau commit Git.

---

## 2. Verifikasi Pra-Syarat Server UAT Aplikasi Web
Untuk pengujian antarmuka web (skenario 5–8), pastikan server UAT berjalan normal:
```powershell
# Terminal 1 — API (dari direktori laravel-api)
php artisan serve --host=127.0.0.1 --port=8001 --env=uat

# Terminal 2 — SPA (dari direktori vue-app)
npm run dev:uat
```
URL SPA: `http://127.0.0.1:4001` | URL API: `http://127.0.0.1:8001` | Database: `school_access_uat`

---

## 3. Prosedur Konkret Pengujian Bootstrap Administrator (Skenario 3 & 4)

Pengujian bootstrap memerlukan database yang belum memiliki pengguna pengelola akses, tanpa mengganggu akun yang ada di `school_access_uat`.

### A. Penyiapan Database Disposable Khusus Bootstrap
1. Salin konfigurasi contoh bootstrap di direktori `laravel-api`:
   ```powershell
   Copy-Item .env.bootstrap.example .env.bootstrap
   ```
   *(Catatan: file `.env.bootstrap` sudah terdaftar dalam `.gitignore` sehingga aman dan tidak akan ter-commit).*
2. Buat database MySQL disposable kosong via MySQL client (misal phpMyAdmin, HeidiSQL, atau terminal MySQL):
   ```sql
   CREATE DATABASE IF NOT EXISTS school_bootstrap_uat;
   ```
   *(Alternatif jika tidak memakai MySQL: gunakan SQLite file disposable dengan mengedit `.env.bootstrap`: `DB_CONNECTION=sqlite` dan `DB_DATABASE=database/school_bootstrap_disposable.sqlite`).*
3. **Pre-Flight Check Koneksi Efektif**:
   Jalankan pemeriksaan koneksi dan pastikan database target adalah `school_bootstrap_uat` (dan BUKAN `backdata_administrations` / BUKAN `school_access_uat`):
   ```powershell
   php artisan tinker --env=bootstrap --execute="echo 'Target Connection: ' . config('database.default') . ' | Database: ' . config('database.connections.' . config('database.default') . '.database');"
   ```
   *Pastikan output mencetak: `Target Connection: mysql | Database: school_bootstrap_uat`.*
4. Jalankan migrasi dan seeder referensi murni pada database disposable:
   ```powershell
   php artisan migrate --env=bootstrap
   php artisan db:seed --class=ReferenceDataSeeder --env=bootstrap
   ```
5. Verifikasi bahwa database disposable tersebut bersih dari akun pengguna:
   ```powershell
   php artisan tinker --env=bootstrap --execute="echo 'User count: ' . \App\Models\User::count();"
   ```
   *Output harus: `User count: 0`.*

---

### B. Skenario 3: Bootstrap Pertama Berhasil (Empty Database)
1. Jalankan perintah bootstrap interaktif pada database disposable:
   ```powershell
   php artisan app:bootstrap-admin --env=bootstrap
   ```
2. Isi data sintetis yang diminta pada prompt terminal:
   - **Administrator name**: `Bootstrap Administrator`
   - **Administrator username**: `bootstrap.admin`
   - **Administrator email**: `bootstrap.admin@uat.local`
   - **16-digit NIK**: `9000000000000099` (tepat 16 digit angka)
   - **Password (min 12 characters)**: Ketikkan kata sandi pilihan pemilik (minimal 12 karakter, input tersembunyi tanpa echo di layar)
   - **Confirm password**: Ketikkan ulang kata sandi yang sama
3. Terminal akan menampilkan ringkasan lingkungan dan koneksi target tanpa kredensial:
   ```text
   Target Environment: bootstrap
   Target Database Connection: mysql (school_bootstrap_uat)
   Proceed with creating the administrator account? (yes/no) [yes]:
   ```
4. Ketik `yes` lalu tekan Enter.
5. **Hasil yang Diharapkan**:
   - Terminal menampilkan pesan sukses: `System administrator account 'bootstrap.admin' successfully created.`
   - Perintah keluar dengan exit code `0`.
6. Verifikasi via tinker bahwa akun dibuat aktif dan memiliki peran `admin-sistem`:
   ```powershell
   php artisan tinker --env=bootstrap --execute="\$u = \App\Models\User::where('username', 'bootstrap.admin')->first(); echo 'Username: ' . \$u->username . ' | Active: ' . (\$u->status_active ? 'Yes' : 'No') . ' | Can manage access: ' . (\$u->canManageAccess() ? 'Yes' : 'No');"
   ```
   *Output harus: `Username: bootstrap.admin | Active: Yes | Can manage access: Yes`.*

---

### C. Skenario 4: Pengulangan Bootstrap Ditolak (Active Manager Exists)
1. Pada database disposable yang sama (`school_bootstrap_uat`) yang kini sudah memiliki akun admin dari Skenario 3, jalankan kembali perintah bootstrap:
   ```powershell
   php artisan app:bootstrap-admin --env=bootstrap
   ```
2. **Hasil yang Diharapkan**:
   - Perintah langsung dibatalkan sebelum meminta input kata sandi dengan pesan error:
     `An active user with access management capabilities already exists. Bootstrap aborted.`
   - Perintah keluar dengan exit code `1`.
   - Tidak ada pengguna baru yang dibuat, dan akun `bootstrap.admin` yang sudah ada tidak mengalami perubahan data atau kata sandi.

---

### D. Pembersihan Database Disposable Bootstrap
Setelah Skenario 3 dan 4 selesai diuji:
1. Hapus database disposable `school_bootstrap_uat` via MySQL:
   ```sql
   DROP DATABASE IF EXISTS school_bootstrap_uat;
   ```
2. Hapus file `.env.bootstrap` jika tidak diperlukan lagi:
   ```powershell
   Remove-Item .env.bootstrap
   ```

---

## 4. Checklist Manual Pemilik Fase 01c

| # | Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| 1 | **DatabaseSeeder Bersih dari Akun Bawaan** | Periksa isi `DatabaseSeeder.php` atau jalankan `php artisan db:seed --class=DatabaseSeeder --env=bootstrap` pada database kosong | Seeder hanya memanggil `ReferenceDataSeeder`; tidak ada akun pengguna atau kata sandi default yang dibuat (`User::count() === 0`). | APPROVED (2026-10-17 WIB) |
| 2 | **Pembersihan Data Siswa Riil Seeder (BLK-03)** | Periksa berkas `StudentSeeder.php` dan `StudentRombelSeeder.php` di repositori | Berkas bersih dari ribuan baris data siswa dan NIK riil; `StudentSeeder` kini hanya memanggil `SyntheticDataSeeder` (khusus lingkungan non-produksi). | APPROVED (2026-10-17 WIB) |
| 3 | **Bootstrap Admin Interaktif via CLI (Berhasil)** | Di terminal `laravel-api`, jalankan `php artisan app:bootstrap-admin --env=bootstrap` pada database disposable kosong (ikuti Prosedur §3.B) | Menanyakan nama, username, email, NIK (16 digit), dan password diketik tersembunyi (secret prompt); menampilkan target database dan konfirmasi; berhasil membuat administrator aktif (exit code 0). | APPROVED (2026-10-17 WIB) |
| 4 | **Bootstrap Admin Ditolak Bila Pengelola Akses Ada** | Di terminal `laravel-api`, ulangi `php artisan app:bootstrap-admin --env=bootstrap` pada database yang sama (ikuti Prosedur §3.C) | Langsung dibatalkan dengan error: *"An active user with access management capabilities already exists. Bootstrap aborted."* (exit code 1). Tidak ada perubahan akun. | APPROVED (2026-10-17 WIB) |
| 5 | **Validasi Akun: Nama Boleh Sama (Homonim)** | Login sebagai `uat.admin` di `http://127.0.0.1:4001` → buka menu Accounts (`/master/accounts`) → buat akun baru dengan Name sama persis akun yang sudah ada (misal "UAT Operator Sekolah"), email & username berbeda | Akun baru berhasil disimpan tanpa penolakan keunikan nama. | APPROVED (2026-10-17 WIB) |
| 6 | **Validasi Akun: NIK Tepat 16 Digit** | Di menu Accounts (`/master/accounts`), coba masukkan NIK dengan 15 digit atau memuat huruf | Muncul pesan peringatan kuning *"NIK must be exactly 16 digits."* dan form menolak penyimpanan sebelum NIK tepat 16 digit. | APPROVED (2026-10-17 WIB) |
| 7 | **Standar Password Akun Baru: Min 12 Karakter** | Di dialog New Account (`/master/accounts`), ketik password kurang dari 12 karakter | Muncul pesan peringatan kuning *"Password must be at least 12 characters."* Form menolak submit jika password < 12 karakter. | APPROVED (2026-10-17 WIB) |
| 8 | **Standar Password Ubah & Reset: Min 12 Karakter** | Buka form Ubah Password di `/profile/user` atau form Reset Password di `/password-reset/:token` | Teks label memuat *"New Password (min 12 chars)"*; pengisian kata sandi kurang dari 12 karakter ditolak oleh validasi antarmuka klien dan backend. | APPROVED (2026-10-17 WIB) |

---

## 5. Catatan Batasan, Isolasi & Bukti Teknis
- **Pembedaan Bukti Otomatis vs Penerimaan Manual**:
  - Bukti otomatis agen: 82 backend test PASS (`php artisan test`), 15 frontend unit test PASS (`node --test`), Pint PASS (100% clean), ESLint PASS (0 errors dengan `--quiet`), frontend build PASS (`npm run build`).
  - Hasil pemeriksaan ESLint dengan `--quiet` membuktikan **0 errors** (peringatan formatting CRLF Prettier dilaporkan terpisah tanpa `--fix` otomatis massal).
  - Seluruh status pada tabel checklist manual pemilik tetap **PENDING** sampai pemilik mengujinya secara mandiri dan memberikan pernyataan persetujuan.
- **Status BLK-03**:
  - **CLOSED pada source code aktif (HEAD)**. Seluruh akun riil pada `DatabaseSeeder.php` dan ribuan baris data siswa riil pada `StudentSeeder.php` serta `StudentRombelSeeder.php` telah dibersihkan dari commit aktif.
  - Sesuai kebijakan repositori, riwayat commit Git historis masa lalu tidak diubah/rewrite.
- **Keputusan Terbuka**:
  - Makna `status_user_id` tetap **OPEN** (tidak disamakan dengan `status_active`).
  - Deployment CORS/Sanctum produksi dan strategi pencabutan token vs sesi tetap **OPEN**.
