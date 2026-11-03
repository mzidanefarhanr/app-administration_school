# Panduan Review Manual — Subfase 10a (Penugasan dan Jadwal Pembelajaran)

Subfase: 10a — penugasan mengajar (`teaching_assignments`), jadwal mingguan dengan deteksi bentrok tiga dimensi (`teaching_schedules` — guru, kelas, ruangan), dan pengecualian kalender (`teaching_schedule_exceptions` — `holiday`, `cancelled`, `rescheduled`).
Keputusan: **DEC-39 APPROVED** (D1–D11, 2026-11-03 WIB; **"Saya setuju D1-D11."**; **DEC-10 CLOSED** melalui D3, D5, dan D8). Laporan teknis: `reports/feature-update/PHASE-10A-REPORT.md`; rencana: `reports/feature-update/PHASE-10-PLAN.md`.
Baseline induk: `main` pada `b515cb1` (pencatatan DEC-39 APPROVED di atas paket rencana `a8264ad` dan baseline 09b `f185ca6`). Status teknis: **IMPLEMENTED — agent-verified (2026-11-03)**. Status review pemilik: **PENDING (#1–#18)**.

## Status penerimaan pemilik

- **PENDING (#1–#18)** — menunggu pemeriksaan manual pemilik atas daftar uji `#1–#18` di bawah ini.
- Server UAT `.uat-phase10` (`127.0.0.1:18028` dan `127.0.0.1:14028`) **telah dihentikan oleh agen pada 2026-11-03 WIB** segera setelah verifikasi teknis selesai sesuai kebijakan `DEC-27`: port `18028` dan `14028` bebas listener, data `.uat-phase10` dipertahankan utuh, dan layanan Apache/MariaDB tidak disentuh.
- Batas: review 10a tidak mengotorisasi Subfase 10b/10c, merge/deploy/force-push, migrasi database operasional/MariaDB, perubahan dependency/lockfile, atau perubahan service/`.env`.

## Lingkungan UAT (terisolasi)

- SPA: `http://127.0.0.1:14028`
- API: `http://127.0.0.1:18028`
- Halaman uji utama: `http://127.0.0.1:14028/public/teaching/schedules` (alias `/teaching/schedules`)
- Data: `.uat-phase10` SQLite (`.uat-phase10/database.sqlite`); cookie `school_phase10_session`; storage `.uat-phase10/storage`
- Launcher: `laravel-api/scripts/phase10-uat.php`
- Akun:
  - **`uat.operator`** (Operator Sekolah; `teaching.view` + **`teaching.manage`**)
  - **`uat.admin`** (Admin Sistem; `teaching.view` + **`teaching.manage`** + `audit.view`)
  - **`uat.guru`** (Guru; **`teaching.view` saja** / read-only; ditautkan ke profil `UAT-P10 Teacher 01`)
  - **`uat.kepsek`** (Kepala Sekolah; **`teaching.view` saja** / read-only)
  - **`uat.waka`** (Wakil Kesiswaan; **`teaching.view` saja** / read-only)
  - **`uat.staff`** (Staf Non-Pengajar; **tanpa `teaching.view`** → ditolak `403`)
  - **`uat.inactive`** (Akun nonaktif → ditolak `403`)
  - Password seluruh akun UAT tersimpan hanya di file lokal `.uat-phase10/account-password.txt` (tidak dicetak di dokumen).

Perintah menyalakan server UAT untuk pengujian manual:

```powershell
# Lihat password akun UAT lokal (jangan commit/bagikan)
Get-Content D:\xampp-htdocs\app-administration_school\.uat-phase10\account-password.txt

# Terminal 1 — API (dari laravel-api)
cd D:\xampp-htdocs\app-administration_school\laravel-api
php -S 127.0.0.1:18028 scripts/phase10-uat.php

# Terminal 2 — SPA (dari vue-app; arahkan VITE_API_BASE_URL ke 18028)
cd D:\xampp-htdocs\app-administration_school\vue-app
$env:VITE_API_BASE_URL="http://127.0.0.1:18028"
npx vite --host 127.0.0.1 --port 14028
```

Perintah CLI pemeriksaan status `.uat-phase10`:

```powershell
cd D:\xampp-htdocs\app-administration_school\laravel-api
php scripts/phase10-uat.php status
```

Angka acuan `.uat-phase10` saat ini (setelah `prepare-teaching` idempoten):

- **2 Tahun Ajaran**:
  - `2026/2027` (`id=1`, **Active**, `2026-07-01` s.d. `2027-06-30`, semester aktif `Semester I`).
  - `2024/2025` (`id=2`, **Locked**, `locked_at = 2025-07-15 10:00:00`, dengan 1 kelas `UAT-P10 X-MIPA-1 (2024/2025)`, 1 penugasan, dan 1 slot jadwal historis).
- **10 Mata Pelajaran** (`Matematika`, `Bahasa Indonesia`, `Bahasa Inggris`, `Fisika`, `Kimia`, `Biologi`, `Sejarah Indonesia`, `Informatika`, `Pendidikan Pancasila`, `Ekonomi`).
- **12 Rombel/Kelas** (`10` kelas `UAT-P10 X-MIPA-1` s.d. `UAT-P10 XII-IPS-1` + `1` kelas bawaan seeder di `2026/2027`, serta `1` kelas di `2024/2025`).
- **18 Pegawai**:
  - **15 Guru `Pendidik` aktif** (`UAT-P10 Teacher 01` .. `UAT-P10 Teacher 15`) yang muncul pada dropdown guru.
  - **2 Staf `Tenaga Kependidikan` aktif** (`UAT-P10 Staff 16`, `UAT-P10 Staff 17`) dan **1 Guru keluar** (`UAT-P10 Exited Teacher 18`, `exit_date = 2025-12-31`) yang **dikecualikan** dari dropdown guru.
- **101 Penugasan Mengajar** (`100` di `2026/2027 Semester I` + `1` di `2024/2025`).
- **121 Slot Jadwal Mingguan** (`120` di `2026/2027 Semester I` lintas Senin..Sabtu termasuk ruang `LAB-FISIKA`, `LAB-KIMIA`, `LAB-BIOLOGI`, `LAB-KOMP-1`, `R-101`..`R-110` + `1` di `2024/2025`).
- **6 Pengecualian Kalender** di `2026/2027 Semester I`:
  - `2026-08-17`: `Holiday` seluruh sekolah (Hari Kemerdekaan RI).
  - `2026-09-07`: `Holiday` khusus kelas `UAT-P10 X-MIPA-1`.
  - `2026-09-08` (Selasa) & `2026-09-09` (Rabu): `Cancelled` slot jadwal.
  - `2026-09-10` (Kamis → pindah ke Sabtu `2026-09-12` Jam 5–6 di `LAB-KOMP-1` dengan guru pengganti `UAT-P10 Teacher 11`) & `2026-09-11` (Jumat → pindah ke Sabtu `2026-09-12` Jam 7–8 di `R-105`): `Rescheduled` slot jadwal.

## Checklist (#1–#18)

### 1. Akses menu & konteks periode aktif (`uat.operator`)
- Login sebagai `uat.operator`, klik menu sidebar **Teaching → Assignments & Timetable** (`/public/teaching/schedules`).
- Harapkan konteks otomatis memilih tahun ajaran aktif **2026/2027** dan **Semester I**, serta menampilkan ringkasan jumlah jadwal, penugasan, dan pengecualian kalender.
- Status agen: **PASS (API/HTTP + kompilasi komponen)**; klik browser **NOT RUN**.

### 2. Tampilan Weekly Timetable — mode Matrix (Mon–Sat) & List
- Pada tab **Weekly Timetable**, periksa tampilan **Matrix (Mon–Sat)** (kartu per hari Senin..Sabtu berisi jam pelajaran, pukul, mata pelajaran, kelas, guru, dan badge ruangan).
- Beralih ke tampilan **Paginated List**, lalu uji filter berdasarkan **Class**, **Teacher**, **Day**, dan **Room** (mis. `LAB-KOMP-1`).
- Status agen: **PASS (tes T4 + probe `/api/teaching/schedules?matrix=1`)**.

### 3. Kelayakan dropdown guru pada Teaching Assignments (`Pendidik` aktif saja)
- Buka tab **Teaching Assignments**, klik **New Assignment**, dan periksa opsi dropdown **Teacher**.
- Harapkan hanya menampilkan **15 guru `Pendidik` aktif** (`UAT-P10 Teacher 01`..`15`); staf non-pengajar (`UAT-P10 Staff 16`..`17`) dan guru yang sudah keluar (`UAT-P10 Exited Teacher 18`) tidak muncul (dan ditolak `422` bila dikirim paksa ke API).
- Status agen: **PASS (tes T3 + probe `/api/teaching/options`)**.

### 4. Pembuatan & pembaruan penugasan mengajar + keunikan Kelas+Mapel
- Sebagai `uat.operator`, buat penugasan baru pada kelas `UAT-01` (atau ubah `hours_per_week` / `teacher` pada penugasan yang ada).
- Coba buat penugasan duplikat dengan kombinasi `(School Year, Semester, Class, Subject)` yang sudah ada → harapkan ditolak `422` dengan pesan duplikasi yang jelas.
- Status agen: **PASS (tes T2–T3)**.

### 5. Penambahan slot jadwal dengan preset jam pelajaran otomatis (`1..12`)
- Pada tab **Weekly Timetable**, klik **New Schedule Slot**, pilih penugasan mengajar, pilih hari **Saturday**, lalu ubah **Start Period** dan **End Period** (mis. Period `9` s.d. `10`).
- Harapkan field **Start Time** (`13:45`) dan **End Time** (`15:15`) terisi otomatis sesuai preset namun tetap dapat disesuaikan manual (`HH:MM`), dan jadwal berhasil disimpan (`201`).
- Status agen: **PASS (tes T4)**.

### 6. Deteksi bentrok Guru (`Teacher collision` — `422`)
- Coba tambahkan slot jadwal di mana guru yang sama (`UAT-P10 Teacher 01`) dijadwalkan pada hari **Monday**, Period `1–2` (`07:00–08:30`) di kelas lain.
- Harapkan ditolak `422` dengan banner error yang menyebutkan nama guru, mata pelajaran, kelas, hari, dan jam yang bentrok.
- Status agen: **PASS (tes T5 + probe HTTP #7 `.uat-phase10`)**.

### 7. Deteksi bentrok Kelas (`Class collision` — `422`)
- Coba tambahkan slot jadwal untuk kelas `UAT-P10 X-MIPA-1` pada hari **Monday**, Period `1–2` (`07:00–08:30`) dengan mata pelajaran/guru lain.
- Harapkan ditolak `422` dengan pesan `Class collision` yang menyebutkan mata pelajaran dan guru yang sudah mengisi kelas tersebut.
- Status agen: **PASS (tes T6)**.

### 8. Deteksi bentrok Ruangan case-insensitive (`Room collision` — `422`)
- Coba tambahkan slot jadwal pada hari **Thursday**, Period `3–4` (`08:30–10:00`) untuk kelas lain dengan ruangan `lab-komp-1` (huruf kecil, sementara `UAT-P10 X-MIPA-1` sudah memakai `LAB-KOMP-1` pada jam tersebut).
- Harapkan ditolak `422` dengan pesan `Room collision` karena normalisasi `room_normalized`. Pastikan jadwal dengan ruangan dikosongkan (`null`) tidak memicu bentrok ruangan palsu.
- Status agen: **PASS (tes T7)**.

### 9. Deteksi bentrok saat reaktivasi slot (`inactive -> active`) atau ganti guru penugasan
- Nonaktifkan (`inactive`) sebuah slot jadwal, buat slot pengganti di jam yang sama, lalu coba aktifkan kembali (`active`) slot lama tersebut → harapkan ditolak `422`.
- Status agen: **PASS (tes T8)**.

### 10. Pengecualian kalender `Holiday` (sekolah & spesifik kelas)
- Buka tab **Schedule Exceptions**, periksa daftar 6 pengecualian bawaan (termasuk `Holiday` seluruh sekolah `2026-08-17` dan `Holiday` khusus kelas `UAT-P10 X-MIPA-1` `2026-09-07`).
- Coba tambah `Holiday` dengan tanggal di luar rentang tahun ajaran `2026/2027` (mis. `2025-01-01`) → harapkan ditolak `422`.
- Status agen: **PASS (tes T9)**.

### 11. Pengecualian kalender `Cancelled` & validasi kecocokan hari (`day_of_week`)
- Klik **New Exception**, pilih tipe **Cancelled**, pilih slot jadwal hari **Monday**, lalu isi `Exception Date` yang jatuh pada hari **Tuesday** (mis. `2026-09-15`).
- Harapkan ditolak `422` karena tanggal pengecualian harus jatuh pada hari Senin sesuai `day_of_week` slot jadwal. Pilih tanggal hari Senin yang valid → berhasil disimpan (`201`).
- Status agen: **PASS (tes T9)**.

### 12. Pengecualian kalender `Rescheduled` + blokir hari libur & deteksi bentrok target
- Pilih tipe **Rescheduled** pada sebuah slot jadwal:
  - Coba arahkan `Rescheduled Date` ke tanggal libur `2026-08-17` → harapkan ditolak `422` (`Target reschedule date falls on a recorded holiday`).
  - Coba arahkan `Rescheduled Date` dan jam pelajaran ke slot yang sudah terisi oleh guru/kelas/ruangan tersebut → harapkan ditolak `422`.
  - Arahkan ke tanggal dan jam kosong (dengan atau tanpa `Substitute Teacher`) → berhasil disimpan (`201`).
- Status agen: **PASS (tes T10)**.

### 13. Konflik versi basi (`409 Conflict`) + tombol `Reload Latest`
- Uji pengiriman update penugasan/jadwal/exception dengan `expected_version` basi.
- Harapkan API mengembalikan `409` beserta objek `latest`, dan dialog menampilkan peringatan konflik dengan tombol **Reload Latest** yang memuat ulang versi terbaru.
- Status agen: **PASS (tes T12 + probe HTTP #8 `.uat-phase10`)**; dua tab browser nyata **NOT RUN**.

### 14. Perlindungan tahun ajaran terkunci (`2024/2025` — read-only `422`)
- Ubah pemilih **School Year** di bagian atas halaman ke **2024/2025 (Locked)**.
- Harapkan muncul banner peringatan bahwa tahun ajaran terkunci bersifat read-only, seluruh tombol tambah/edit/hapus disembunyikan, data historis `2024/2025` tetap tampil, dan percobaan tulis langsung ke API untuk `school_year_id=2` ditolak `422`.
- Status agen: **PASS (tes T11 + probe HTTP #10 `.uat-phase10`)**.

### 15. Guard penghapusan berjenjang (`422`)
- Coba hapus (`DELETE`) penugasan mengajar yang sudah memiliki slot jadwal, atau hapus slot jadwal yang sudah memiliki exception kalender, atau hapus kelas `UAT-P10 X-MIPA-1` yang memiliki penugasan.
- Harapkan seluruhnya ditolak `422` dengan pesan yang meminta penghapusan child record terlebih dahulu atau menonaktifkan statusnya.
- Status agen: **PASS (tes T13 + probe HTTP #9 `.uat-phase10`)**.

### 16. Pembatasan akses per peran (`uat.guru`, `uat.kepsek`, `uat.waka` read-only; `uat.staff` `403`)
- Login sebagai **`uat.guru`** (atau `uat.kepsek` / `uat.waka`): halaman `/public/teaching/schedules` dapat dibuka dan menampilkan jadwal/penugasan/exception secara read-only tanpa tombol tambah/edit/hapus (`POST`/`PUT`/`DELETE` mengembalikan `403`).
- Login sebagai **`uat.staff`** (tanpa `teaching.view`): menu **Teaching** tidak tampil dan akses ke `/api/teaching/*` mengembalikan `403`.
- Status agen: **PASS (tes T1 + probe HTTP #2–#3 `.uat-phase10`)**.

### 17. Tampilan responsif (1440×900 & 390×844), keyboard/aria, dan console bersih
- Periksa halaman `/public/teaching/schedules` (ketiga tab dan dialog CRUD) pada lebar desktop 1440×900 dan mobile 390×844.
- Harapkan tabel/matriks dapat digulir secara rapi tanpa merusak tata letak, dialog dapat ditutup dengan `Esc`, seluruh label berbahasa Inggris, dan 0 console error.
- Status agen: **NOT RUN** di browser otomatis (ESLint 0 error & `npm run build` PASS).

### 18. Penghentian server UAT setelah review selesai (`DEC-27`)
- Server UAT `.uat-phase10` sudah dihentikan oleh agen pada 2026-11-03 WIB setelah verifikasi teknis (port `18028` dan `14028` bebas listener, `.uat-phase10` dipertahankan, Apache/MariaDB tidak disentuh).
- Bila Anda menyalakan server UAT untuk menjalankan checklist di atas, hentikan kembali setelah selesai dengan memeriksa PID listener pada port `18028` dan `14028`:

```powershell
Get-NetTCPConnection -LocalPort 18028,14028 -State Listen -ErrorAction SilentlyContinue |
  Select-Object LocalAddress,LocalPort,OwningProcess
# Verifikasi CommandLine proses terlebih dahulu, lalu hentikan PID terkait:
# taskkill /PID <PID_18028> /T /F
# taskkill /PID <PID_14028> /T /F
```

## Cara melaporkan hasil

Sampaikan nomor yang berhasil/gagal (mis. **"Pengujian nomor 1-18 sudah berhasil dan saya approved."** atau daftar temuan per nomor). Jangan mengubah data operasional atau me-reset `.uat-phase10`.
