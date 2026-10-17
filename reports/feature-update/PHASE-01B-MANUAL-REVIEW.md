# Panduan Review Manual Fase 01b (Lingkungan UAT Sintetis)

Status penerimaan manual: **APPROVED** (seluruh checklist nomor 1–16 telah disetujui manual oleh pemilik: nomor 1–7 dan 9–16 APPROVED pada 16 Oktober 2026 WIB; nomor 8 retest berhasil dan APPROVED pada 17 Oktober 2026 WIB).
Lingkungan: UAT lokal terisolasi (DB MySQL `school_access_uat`, SPA mode `uat`).
URL SPA: `http://127.0.0.1:4001` | URL API: `http://127.0.0.1:8001`
Email Log: `laravel-api/storage/logs/laravel.log` (driver `log`, tanpa pengiriman email nyata).

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

## 2. Prosedur Khusus Skenario Recovery & Log Email

### A. Memperoleh Link Reset Password dari Log Lokal
1. Lakukan request reset password di `http://127.0.0.1:4001/auth/forgot-password`.
2. Buka PowerShell dan baca baris terakhir log backend:
   ```powershell
   Get-Content laravel-api/storage/logs/laravel.log -Tail 60
   ```
3. Temukan baris email reset yang memuat URL tujuan dengan format:
   `http://127.0.0.1:4001/password-reset/<token>?email=<alamat-email>`
4. Salin tautan langsung ke bilah alamat browser untuk membuka form reset.
5. **Aturan Keamanan:** Jangan pernah menempelkan token aktual atau baris log utuh ke dokumen Git, commit, atau laporan terbuka. Gunakan notasi tersanitasi `.../password-reset/<token>?email=...`.

### B. Kredensial Pengujian Setelah Reset
- Saat mengisi form reset password, gunakan kata sandi baru pilihan pemilik yang memenuhi syarat validasi (minimal 8 karakter).
- Jangan menuliskan nilai password baru tersebut ke dalam laporan review atau commit Git.
- Uji login menggunakan username akun dan kata sandi baru tersebut.

### C. Tampilan Pesan Session Expired Aktual di UI
- Sesuai implementasi pada `Login_2.vue` dan interceptor `apiLogin`, saat sesi aktif kedaluwarsa atau dibatalkan di backend (401), sistem melakukan:
  1. Pengalihan halaman ke `/auth/login`.
  2. Penampilan modal dialog PrimeVue berbingkai di tengah layar:
     - **Header Modal:** `Session Expired`
     - **Isi Pesan:** `Your session has expired. Please sign in again.`
     - **Aksi:** Tombol Close (silang) untuk menutup modal dialog.
  3. Toast notifikasi merah di sudut layar dengan judul `Session expired`.
  4. Form login tetap bersih dan siap menerima kredensial tanpa terjadi reload loop.

### D. Karakteristik Single Page Application (SPA) pada Form Reset Password
- Halaman `/password-reset/<token>` adalah rute frontend SPA klien (`ResetPassword.vue`). Membuka kembali URL bertoken pada peramban akan selalu memuat form antarmuka reset password (hal ini normal pada aplikasi SPA dan bukan bukti bahwa token masih berlaku).
- Keabsahan dan konsumsi token diverifikasi oleh backend Laravel saat formulir disubmit (`POST /reset-password`).
- Pada submit pertama yang valid, token langsung dikonsumsi (dihapus dari tabel broker token oleh backend) dan kata sandi baru disimpan.
- Jika tautan yang sama dibuka kembali lalu pengguna mengisi kata sandi baru lain dan menekan tombol **Reset Password**, server menolak submit tersebut dengan HTTP status 422: `This password reset token is invalid.` Modal dialog "Invalid Token" muncul di layar dan kata sandi tidak berubah.

---

## 3. Checklist Manual Pemilik

| # | Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| 1 | **Tautan Forgot Password** | Buka `http://127.0.0.1:4001/auth/login` → klik link "Forgot Password?" | Halaman berpindah ke `/auth/forgot-password` dengan form English yang bersih. | APPROVED (2026-10-16 WIB) |
| 2 | **Permintaan Link Reset (Anti-Enumerasi)** | Di `/auth/forgot-password`, masukkan `uat.operator@uat.local` → klik **Send Reset Link** | Muncul modal konfirmasi: "Check Your Email: If your email address is registered, you will receive a password reset link shortly." Pesan seragam tidak mengungkap apakah email terdaftar. | APPROVED (2026-10-16 WIB) |
| 3 | **Pemeriksaan Email Log UAT** | Jalankan `Get-Content laravel-api/storage/logs/laravel.log -Tail 60` | Ditemukan log email reset password berisi link tujuan `http://127.0.0.1:4001/password-reset/<token>?email=...`. Tidak ada email keluar jaringan. | APPROVED (2026-10-16 WIB) |
| 4 | **Form Reset Password** | Salin URL bertoken dari log lalu buka di browser | Halaman `/password-reset/<token>` termuat; kolom email terisi otomatis; terdapat kolom New Password dan Confirm New Password. | APPROVED (2026-10-16 WIB) |
| 5 | **Validasi Form Reset** | Masukkan password kurang dari 8 karakter atau konfirmasi tidak cocok | Muncul dialog validasi English yang sesuai. Form menolak submit sebelum valid. | APPROVED (2026-10-16 WIB) |
| 6 | **Reset Password Berhasil** | Masukkan password baru pilihan pemilik (min 8 char) dan konfirmasi cocok → klik **Reset Password** | Muncul modal success: "Your password has been reset successfully. Redirecting to sign in...", lalu otomatis diarahkan ke `/auth/login`. | APPROVED (2026-10-16 WIB) |
| 7 | **Verifikasi Kredensial Baru & Lama** | Coba login `uat.operator` dengan password lama → lalu login dengan password baru | Password lama ditolak ("These credentials do not match our records."); password baru berhasil masuk ke Dashboard. | APPROVED (2026-10-16 WIB) |
| 8 | **Token Bekas Pakai Ditolak** | Buka kembali URL bertoken yang sama pada browser, masukkan password baru berbeda yang valid (min 8 char + cocok), lalu klik **Reset Password** *(Catatan: form termuat di browser adalah sifat rute SPA klien; penolakan token bekas diverifikasi saat submit)* | Submit ditolak oleh server dengan HTTP status 422; modal dialog English muncul: "Invalid Token: This password reset token is invalid."; kata sandi tidak berubah dan kata sandi hasil reset pertama tetap berlaku. | APPROVED (2026-10-17 WIB) |
| 9 | **Sesi Aktif Kedaluwarsa (401 Redirect)** | Login ke Dashboard → hapus cookie sesi backend via DevTools/logout tab lain → klik navigasi yang memanggil API (mis. `/public/students`) | Otomatis dialihkan ke `/auth/login` dengan modal dialog: "Session Expired: Your session has expired. Please sign in again." Tanpa reload loop. | APPROVED (2026-10-16 WIB) |
| 10 | **Kegagalan Jaringan / Server Offline** | Saat berada di Dashboard, hentikan server API (Ctrl+C di terminal port 8001) → coba picu request API | Muncul notifikasi error merah "Connection error: Could not reach the API server...". Sesi lokal tetap dipertahankan (tidak langsung di-logout). | APPROVED (2026-10-16 WIB) |
| 11 | **Server Logout Gagal (Offline Logout)** | Saat server API port 8001 mati/offline, klik Logout dari profil topbar | State lokal dibersihkan; muncul dialog "Signed out: Signed out locally. Could not reach server to terminate session.", lalu dialihkan ke `/auth/login`. | APPROVED (2026-10-16 WIB) |
| 12 | **Child Navigation Guard (Blokir Tanpa Izin)** | Login sebagai `uat.operator` → saat di Dashboard (`/`), coba navigasi URL ke `/master/roles` atau `/master/accounts` | Guard `router.beforeEach` memblokir transisi child dan mengarahkan ke `/auth/access` (Access Denied). | APPROVED (2026-10-16 WIB) |
| 13 | **Child Navigation Guard (Izinkan Sesuai Izin)** | Di sesi `uat.operator`, buka menu User Type (`/master/typeusers`) | Diizinkan masuk ke halaman Type Users tanpa dialihkan ke Access Denied. | APPROVED (2026-10-16 WIB) |
| 14 | **Penolakan Izin Mempertahankan Sesi (403)** | Sebagai `uat.waka` (tanpa `students.export`), buka `/public/students` | Tombol Export tidak ditampilkan; jika memanggil API ekspor manual di console browser, respons 403 tidak menyebabkan logout. | APPROVED (2026-10-16 WIB) |
| 15 | **Single Logout 204 Bersih** | Saat backend online normal, klik tombol Logout dari dropdown profil | Sesi backend berakhir via `POST /logout` (204 No Content tanpa membaca body); state lokal dibersihkan; kembali ke halaman login. | APPROVED (2026-10-16 WIB) |
| 16 | **Konsistensi UI English** | Periksa layar login, forgot password, reset password, ubah password di `/profile/user`, dan layar logout | Seluruh antarmuka yang disentuh konsisten berbahasa **English**. | APPROVED (2026-10-16 WIB) |

---

## 4. Catatan Batasan & Keamanan
- Pengujian hanya menggunakan database sintetis `school_access_uat` dan SQLite in-memory `:memory:`.
- Driver mail disetel ke `MAIL_MAILER=log`. Tidak ada surat elektronik nyata yang dikirim ke internet.
- **BLK-03** (`DatabaseSeeder` memuat identitas asli) tetap **OPEN** dan tidak dijalankan pada sesi ini (dialokasikan ke Fase 01c).
