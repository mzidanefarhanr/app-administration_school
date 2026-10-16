# Panduan Review Manual Fase 01b (Lingkungan UAT Sintetis)

Status penerimaan manual: **PENDING** (menunggu review manual pemilik setelah implementasi).
Lingkungan: UAT lokal terisolasi (DB MySQL `school_access_uat`, SPA mode `uat`).
URL SPA: `http://127.0.0.1:4001` | URL API: `http://127.0.0.1:8001`
Email Log: `laravel-api/storage/logs/laravel.log` (driver `log`, tanpa pengiriman email nyata).

---

## 1. Prasyarat Server
Pastikan server UAT berjalan:
```powershell
# Terminal 1 — API (dari laravel-api)
php artisan serve --host=127.0.0.1 --port=8001 --env=uat

# Terminal 2 — SPA (dari vue-app)
npm run dev:uat
```

---

## 2. Checklist Manual Pemilik

| # | Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| 1 | **Tautan Forgot Password** | Buka `http://127.0.0.1:4001/auth/login` → klik link "Forgot Password?" | Halaman berpindah ke `/auth/forgot-password` dengan form English yang bersih. | PENDING |
| 2 | **Permintaan Link Reset (Anti-Enumerasi)** | Di `/auth/forgot-password`, masukkan `uat.operator@uat.local` → klik **Send Reset Link** | Muncul modal konfirmasi: "Check Your Email: If your email address is registered, you will receive a password reset link shortly." Pesan seragam tidak mengungkap apakah email terdaftar. | PENDING |
| 3 | **Pemeriksaan Email Log UAT** | Buka berkas `laravel-api/storage/logs/laravel.log` pada baris-baris terakhir | Ditemukan log email reset password berisi link tujuan `http://127.0.0.1:4001/password-reset/<token>?email=uat.operator%40uat.local`. Tidak ada pengiriman email nyata keluar jaringan. | PENDING |
| 4 | **Form Reset Password** | Salin URL bertoken dari log lalu buka di browser | Halaman `/password-reset/<token>` termuat; kolom email terisi `uat.operator@uat.local`; terdapat kolom New Password dan Confirm New Password. | PENDING |
| 5 | **Validasi Form Reset** | Coba masukkan password kurang dari 8 karakter atau konfirmasi yang tidak cocok | Muncul dialog validasi English yang sesuai. Form tidak mengirim request yang tidak valid. | PENDING |
| 6 | **Reset Password Berhasil** | Masukkan password baru (min 8 karakter) dan konfirmasi yang cocok → klik **Reset Password** | Muncul modal success: "Your password has been reset successfully. Redirecting to sign in...", lalu otomatis diarahkan kembali ke `/auth/login`. | PENDING |
| 7 | **Verifikasi Kredensial Baru & Lama** | Coba login `uat.operator` dengan password lama → lalu login dengan password baru | Password lama ditolak ("These credentials do not match our records."); password baru berhasil masuk langsung ke Dashboard. *(Catatan: kembalikan password ke `password` jika ingin konsisten dengan checklist lain).* | PENDING |
| 8 | **Token Bekas Pakai Ditolak** | Buka kembali URL bertoken yang sama pada browser dan coba submit reset lagi | Ditolak oleh server dengan status 422: "This password reset token is invalid or has expired." | PENDING |
| 9 | **Child Navigation Guard (Blokir Tanpa Izin)** | Login sebagai `uat.operator` → saat berada di Dashboard (`/`), coba navigasi ke `/master/roles` atau `/master/accounts` | Guard `router.beforeEach` memblokir transisi child dan mengarahkan ke `/auth/access` (Access Denied). | PENDING |
| 10 | **Child Navigation Guard (Izinkan Sesuai Izin)** | Di sesi `uat.operator`, navigasi ke `/master/typeusers` (Operator memiliki kemampuan `master.manage`) | Diizinkan masuk ke halaman Type Users tanpa dialihkan ke Access Denied. | PENDING |
| 11 | **Sesi Aktif Kedaluwarsa (401 Redirect)** | Login sebagai pengguna apa pun → hapus sesi di backend atau tunggu kedaluwarsa → lakukan interaksi yang memicu request API | Otomatis dialihkan ke `/auth/login` dengan modal dialog: "Your session has expired. Please sign in again." Tidak terjadi reload loop. | PENDING |
| 12 | **Penolakan Izin Mempertahankan Sesi (403)** | Lakukan aksi yang ditolak izinnya (mis. panggil API di luar hak akses) | Muncul pesan error/warning penolakan, namun pengguna **TIDAK** dikeluarkan dari aplikasi (sesi lokal tetap aktif). | PENDING |
| 13 | **Single Logout 204** | Klik tombol Logout dari dropdown profil topbar | Sesi backend berakhir via `POST /logout` (204 No Content tanpa membaca body); state lokal dibersihkan; kembali ke halaman login. | PENDING |
| 14 | **Bahasa UI English** | Periksa form Forgot Password, Reset Password, form Ubah Password di `/profile/user`, dan layar `/auth/logout` | Seluruh teks antarmuka yang disentuh menggunakan bahasa **English**. | PENDING |

---

## 3. Catatan Batasan & Keamanan
- UAT menggunakan driver `MAIL_MAILER=log`, sehingga tidak ada email nyata yang dikirim atau kredensial SMTP yang dibutuhkan.
- Token reset password, password baru, dan email log tidak boleh dicantumkan ke dalam commit Git atau laporan publik.
- **BLK-03** (`DatabaseSeeder` memuat identitas asli) tetap OPEN dan dialokasikan ke Fase 01c.
