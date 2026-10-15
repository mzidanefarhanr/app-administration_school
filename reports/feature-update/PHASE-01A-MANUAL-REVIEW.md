# Panduan review manual Fase 01a (lingkungan UAT sintetis)

Status penerimaan manual: **PENDING** (panduan ini belum dijalankan pemilik dan bukan bukti PASS).
Lingkungan: UAT lokal terisolasi (DB MySQL `school_access_uat`, SPA mode `uat`). **Jangan memakai database operasional `backdata_administrations`.** Jangan menjalankan `DatabaseSeeder` (identitas asli).

## 1. Prasyarat (sekali)
1. Database UAT terpisah sudah dibuat: `school_access_uat` (disposable).
2. Konfigurasi:
   - `laravel-api/.env.uat` (lokal, di-ignore) — salin dari `.env.uat.example`. `APP_ENV=uat`, `DB_DATABASE=school_access_uat`, `APP_URL=http://127.0.0.1:8001`.
   - `vue-app/.env.uat` (lokal, di-ignore) — `VITE_API_BASE_URL=http://127.0.0.1:8001`.
3. Migrasi hanya pada DB UAT (bukan operasional):
   ```powershell
   # dari laravel-api
   php artisan migrate --env=uat --force
   ```
4. Buat akun & data sintetis (kata sandi TIDAK ditulis di dokumen; pilih sendiri):
   ```powershell
   # dari laravel-api
   $env:UAT_SEED_PASSWORD='<pilih-kata-sandi-sintetis>'
   php artisan db:seed --class=UatSeeder --env=uat --force
   ```
   Akun sintetis: `uat.admin`, `uat.operator`, `uat.kepsek`, `uat.waka`, `uat.inactive` (nonaktif). Data: 3 siswa sintetis pada tahun ajaran `2026/2027`.

## 2. Menjalankan server
```powershell
# Terminal 1 — API (dari laravel-api)
php artisan serve --host=127.0.0.1 --port=8001 --env=uat

# Terminal 2 — SPA (dari vue-app, menggunakan script dev:uat)
npm run dev:uat
```
Catatan: `npm run dev:uat` mengeksekusi `vite --mode uat --host 127.0.0.1 --port 4001`. File `vue-app/.env.uat` harus memuat `VITE_API_BASE_URL=http://127.0.0.1:8001`. Tidak ada fallback ke IP lama; bila URL tidak diatur, aplikasi memunculkan pesan error konfigurasi yang jelas.

URL:
- SPA: `http://127.0.0.1:4001` (login: `http://127.0.0.1:4001/auth/login`)
- API: `http://127.0.0.1:8001` (mis. `http://127.0.0.1:8001/api/check-auth`)

## 3. Menghentikan server
- Tekan `Ctrl+C` di tiap terminal; atau hentikan proses pada port 8001/4001:
  ```powershell
  Get-NetTCPConnection -LocalPort 8001,4001 -State Listen | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force }
  ```

## 4. Checklist manual
Login memakai `username` + kata sandi sintetis yang Anda tetapkan.

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Login `uat.operator` → Dashboard (`/`) | Dashboard termuat bersih: **0 console error**, **tidak ada pemanggilan ke `/api/users`**. Widget online users tidak tampil. Menu "Admin" tidak tampil. |
| 2 | Di sesi `uat.operator`, klik Topbar profile dropdown | Menampilkan "UAT Operator Sekolah" dan "operator-sekolah" (berasal dari check-auth ringan). Tombol Profile dan Logout berfungsi normal. |
| 3 | Di sesi `uat.operator`, klik **Logout** | Berhasil logout kembali ke `/auth/login`, sesi berakhir (`/api/check-auth` mengembalikan `authenticated: false`). State sesi dibersihkan. |
| 4 | Login `uat.admin` → Dashboard (`/`) | Widget online users tampil normal (memanggil `/api/users` 200). Menu Admin tampil lengkap. |
| 5 | Login `uat.admin` → buka `/master/roles` → Edit **Vice Principal for Student Affairs** | Halaman berbahasa **English**; matriks kemampuan tampil per grup; label mis. "Export students (students.export)", tag "sensitive". |
| 6 | Centang **Export students (students.export)** pada Wakil Kesiswaan → **Save** | Tersimpan (200). |
| 7 | Di sesi **Wakil Kesiswaan** yang **sudah aktif** (buat sekali login `uat.waka` sebelum perubahan), muat ulang lalu cek izin | Izin ekspor **berlaku pada request berikutnya** (tombol Export muncul di `/public/students`). |
| 8 | Sebagai admin, **cabut** kembali `students.export` dari Wakil Kesiswaan → Save; cek sesi Wakil Kesiswaan lagi | Izin **hilang pada request berikutnya** (tombol Export tidak muncul / unduhan ditolak). |
| 9 | Login `uat.operator` (atau `uat.kepsek`) → `/public/students` → pilih tahun ajaran `2026/2027` → **Export** | Terunduh `students_1.csv` (via `GET /api/students/export`), berisi kolom Name…Student Status; NIK/NIS mempertahankan nol awal. |
| 10 | Login `uat.waka` (tanpa `students.export`) → `/public/students` | Tombol Export **tidak** tampil. Panggil langsung `GET http://127.0.0.1:8001/api/students/export?school_year_id=1` (dengan cookie sesi) → **403**. |
| 11 | Batas baris ekspor | Jalankan server API dengan `$env:STUDENTS_EXPORT_MAX_ROWS='2'` lalu export tahun ajaran berisi 3 siswa → **422** dengan pesan English menyebut jumlah baris/limit dan minta mempersempit filter; **tidak ada CSV terpotong**. |
| 12 | Akun nonaktif | Login `uat.inactive` → **ditolak** (pesan akun tidak aktif). Bila akun aktif dinonaktifkan admin saat sesi berjalan, request berikutnya ditolak. |
| 13 | Topbar Actions & Tema | Klik Dark/Light toggle, Menu toggle, Notifications, Messages di topbar → berjalan normal tanpa warning directive di console. |
| 14 | Bahasa UI | Halaman Roles & Abilities, dialog **Assign Roles** (di Accounts), tombol/notifikasi Export memakai **English**. |

Catatan batas: `students.export` mengendalikan **berkas unduhan**, bukan kerahasiaan data yang sudah boleh dilihat. Modul surat/izin/pembelajaran belum tersedia (kemampuan hanya katalog).

## 5. Cara melaporkan hasil
- Untuk tiap nomor: tulis **PASS/FAIL**, langkah, hasil aktual, dan lampiran (tangkapan layar) tanpa data pribadi.
- Untuk kegagalan, sertakan URL/endpoint, peran akun uji, status HTTP, dan pesan console/network.
- Kirim hasil ke sesi berikutnya untuk dicatat di laporan (`reports/feature-update/PHASE-01A-ACCESS.md`) sebagai **persetujuan/hasil manual**; bagian yang belum diperiksa tetap **NOT RUN/PENDING**.
