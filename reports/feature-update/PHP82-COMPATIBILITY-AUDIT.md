# Audit Kompatibilitas PHP 8.2 Global, Triase Keamanan & Bukti Kesiapan UAT Terisolasi

Tanggal: 2026-10-26 (WIB). Branch: `main`.
Baseline implementasi kode: `e15f028`.
Baseline laporan awal: `27eca9227fabe31d323ae7a78e2c8d2123bd0b3b`.
Status subfase: Fase 01–02 DITUTUP LENGKAP; Subfase 03a–03b DITERIMA PEMILIK; Subfase 03c IMPLEMENTED & VERIFIED (AGEN), checklist manual #1–#23 **PENDING**. Fase 03 tetap **PARTIAL**.
Status tindakan sesi ini: **PERSIAPAN DAN PENGUJIAN KOMPATIBILITAS UAT SINTETIS BERHASIL DILAKSANAKAN; SERVER UAT TERISOLASI BERJALAN PADA PORT 18021/14021 UNTUK REVIEW PEMILIK.**

> **Pernyataan Batas Scope:** Pengujian ini membuktikan kompatibilitas teknis runtime PHP 8.2 global pada checkout uji terisolasi. Ini **bukan** penerimaan manual Subfase 03c dan **bukan** pernyataan bahwa seluruh risiko keamanan atau advisory telah selesai. Risiko keterpaparan historis, EOL framework, dan advisory dependency tetap **OPEN**.

---

## 1. Orientasi & Koreksi Dokumentasi

### 1.1 Koreksi Siklus Hidup Laravel 11
- Keterangan sebelumnya yang menyatakan Laravel 11 masih memiliki 5 bulan masa dukungan keamanan dikoreksi:
  - Rilis Laravel 11: Maret 2024.
  - Dukungan bugfix berakhir: September 2025.
  - **Dukungan keamanan berakhir: Maret 2026 (berstatus EOL / End-of-Life sejak Maret 2026)**.
- Kesimpulan: Laravel 11 **bukan target akhir yang masih didukung**. Laravel 11 hanya dapat berfungsi sebagai jembatan transisi teknis jika breaking change dari Laravel 10 ke 12 memerlukan tahapan bertingkat.
- **Target utama modernisasi yang kompatibel dengan PHP 8.2.35 adalah Laravel 12** (didukung bugfix hingga pertengahan 2026 dan keamanan hingga Februari 2027, mensyaratkan `php: ^8.2`).

### 1.2 Batasan Klaim Advisory dan Peran Loopback
- **Klaim Keterjangkauan (Reachability)**: Tidak ditemukannya pemanggilan fungsi/kelas secara langsung pada kode domain `laravel-api/app` (misal: tidak ada pemanggilan `GuzzleHttp\Client` atau parser Markdown `CommonMark`) membatasi eksposur langsung pada alur aplikasi sekolah. Namun hal ini **bukan bukti mutlak bahwa seluruh jalur internal framework atau transitif tidak dapat terpicu**.
- **Peran Loopback Binding**: Pengikatan ke `127.0.0.1` berfungsi sebagai pembatas akses jaringan (isolasi dari LAN dan internet eksternal). Loopback **bukan mitigasi universal** terhadap kerentanan parser data, kelemahan logika dependensi, prototype pollution client, atau eksekusi kode toolchain build lokal.
- **Status Risiko**: Advisory dependency (48 Composer, 20 npm), risiko eksposur file host sebelum penerapan containment Apache, dan kebutuhan rotasi rahasia produksi tetap berstatus **OPEN**. Tidak ada dependensi yang dimutasi/di-update pada sesi ini.

---

## 2. Checkout Uji dan Runtime Terverifikasi

### 2.1 Checkout Uji Terisolasi
- Lokasi checkout uji: `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\staging\checkout` (berada di luar `C:\xampp\htdocs` dan seluruh Alias Apache).
- Sinkronisasi: Checkout uji disinkronkan langsung ke branch `main`, mengusung baseline implementasi `e15f028` dan commit dokumentasi `27eca92`.
- Hash SHA checkout uji saat pengujian: `27eca9227fabe31d323ae7a78e2c8d2123bd0b3b`.
- Isolasi file: File `.env.uat`, storage, log, session, dan cache di checkout utama (`C:\xampp\htdocs\app-administration_school`) **tidak ditimpa atau diubah**.

### 2.2 Verifikasi Runtime
- **PHP**: `C:\xampp\php82-operational\php.exe` (**PHP 8.2.35 ZTS x64**, loaded ini: `C:\xampp\php82-operational\php.ini`).
- **Node**: Portable ter-patch `v24.22.1` di `C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\runtime\node-v24.22.1\node-v24.22.1-win-x64\node.exe` (npm 11.19.1).
- **Subprocess Node/npm**: Dipanggil dengan executable eksplisit dan environment PATH proses lokal yang mendahulukan binary portable, tanpa mengubah PATH global sistem. Terverifikasi mengeksekusi `v24.22.1` tanpa fallback ke Node global `v24.18.0`.

---

## 3. Pre-Flight Isolation Guard (Fail-Closed)

Untuk menjamin ketiadaan interaksi tidak disengaja dengan database lain atau konfigurasi kadaluarsa, dibangun script guard `laravel-api/scripts/verify-isolation.php` dengan kriteria fail-closed:

### 3.1 Profil Automated Testing (`testing`)
- `APP_ENV` harus eksak `testing`.
- `DB_CONNECTION` harus eksak `sqlite`.
- `DB_DATABASE` harus eksak `:memory:`.
- `bootstrap/cache/config.php` dan `routes-v7.php` tidak boleh ada.
- Verifikasi guard lolos: `[ISOLATION GUARD PASS] Environment verified safe for profile 'testing'`.

### 3.2 Profil UAT (`uat`)
- `APP_ENV` harus eksak `uat`.
- `DB_CONNECTION` harus eksak `mysql`.
- `DB_HOST` harus eksak `127.0.0.1` atau `localhost`.
- `DB_PORT` harus eksak `3306`.
- `DB_DATABASE` harus **eksak string** `school_compat_uat`. Target lain ditolak fail-closed (bukan sekadar `!= backdata_administrations`).
- `DB_USERNAME` tidak boleh `root`.
- `DATABASE_URL` dan `DB_URL` pada process environment ditolak jika ada. Variable OS `$env:DB_DATABASE` diperiksa agar tidak mengalahkan file `.env.uat`.
- `bootstrap/cache/config.php` dan `routes-v7.php` tidak boleh ada (jika ditemukan, proses ditolak; tidak dihapus diam-diam).
- `APP_URL` harus `http://127.0.0.1:18021`, `FRONTEND_URL` harus `http://127.0.0.1:14021`.
- `SESSION_COOKIE` harus `school_compat_session`.
- `MAIL_MAILER` harus `log` atau `array`.
- Verifikasi guard lolos: `[ISOLATION GUARD PASS] Environment verified safe for profile 'uat'`.

---

## 4. Database Sintetis Khusus (`school_compat_uat`)

1. **Provisioning Database & Dedicated User**:
   - Database baru `school_compat_uat` dibuat pada MariaDB lokal port 3306 (utf8mb4_unicode_ci).
   - Pengguna aplikasi khusus: `'school_compat_app'@'127.0.0.1'` dan `'school_compat_app'@'localhost'`.
   - Hak akses dibatasi strictly hanya pada `school_compat_uat.*` (`GRANT ALL ON school_compat_uat.*`).
   - Akun runtime aplikasi **bukan root**. Percobaan akses oleh `school_compat_app` ke `backdata_administrations` terbukti ditolak: `ERROR 1044 (42000): Access denied`.
   - Database operasional `backdata_administrations`, database UAT lama (`school_access_uat`, `school_restore_drill_uat`), serta database App Gudang sama sekali tidak diakses atau dimodifikasi.
   - MariaDB tidak direstart dan tidak ada grant global yang dibuat.
2. **Kredensial Privat**:
   - Password database dibuat menggunakan random bytes (32 hex characters) dan disimpan di file lokal privat `secret_db_pass.txt` di luar git, tanpa pernah dicetak ke argumen proses atau terminal output.
3. **Migrasi Normal**:
   - Dijalankan via `C:\xampp\php82-operational\php.exe artisan migrate --env=uat --force` pada checkout uji.
   - Hasil aktual: **42 migration files** selesai 100% tanpa error (dari `create_password_reset_tokens_table` hingga `add_archive_columns_to_students_and_student_rombels`).
   - Tidak ada `migrate:fresh`, dump, atau pemulihan data operasional.
4. **Seeding Sintetis**:
   - Dijalankan via `C:\xampp\php82-operational\php.exe artisan db:seed --class=UatSeeder --env=uat --force`.
   - Menghasilkan data referensi lookup (`ReferenceDataSeeder`), katalog peran/kemampuan (`AccessControlSeeder`), 4 akun sintetis (`uat.admin`, `uat.operator`, `uat.kepsek`, `uat.waka`), 1 akun nonaktif (`uat.inactive`), serta data siswa sintetis tahun ajaran 2026/2027 (termasuk nilai nol dan formula string `=FORMULA TEST`).
   - Password akun sintetis disuplai secara privat melalui environment variable sementara `UAT_SEED_PASSWORD`, disimpan di file privat lokal `secret_uat_password.txt` (tanpa dicetak ke laporan atau di-commit).

---

## 5. Konfigurasi Checkout Uji & Pemisahan Cookie

- **Backend API**: `http://127.0.0.1:18021` (port loopback strict).
- **Frontend SPA**: `http://127.0.0.1:14021` (port loopback strict).
- **Session Cookie**: `SESSION_COOKIE=school_compat_session` (memisahkan session cookie secara tegas dari port localhost lain).
- **CORS & Sanctum**:
  - `FRONTEND_URL=http://127.0.0.1:14021`
  - `SANCTUM_STATEFUL_DOMAINS=127.0.0.1:14021,localhost:14021,127.0.0.1,localhost`
  - Tidak ada wildcard `*` pada origin atau allowedHosts.
  - Reset password URL otomatis selaras ke `http://127.0.0.1:14021/password-reset/{token}?email={email}` via `AuthServiceProvider`.
- **App Key Baru**: `APP_KEY` baru dihasilkan khusus untuk checkout uji melalui `random_bytes(32)`. `APP_KEY` aplikasi utama tidak dibaca atau diubah.
- **Contoh Konfigurasi Tersanitasi**: Disediakan berkas contoh `laravel-api/.env.uat.example` dan `vue-app/.env.uat.example` di repositori.

---

## 6. Hasil Pengujian Aktual

### 6.1 Backend Automated Tests (SQLite In-Memory Murni)
- **Eksekusi**: `C:\xampp\php82-operational\php.exe artisan test <test-file-list>`
- **Pengecualian Eksplisit**:
  - `tests/Feature/Backup/MariaDbIntegrationTest.php` (dikecualikan agar tidak membuka socket mysqldump/MariaDB).
  - `tests/Feature/Student/MariaDbStudentIntegrationTest.php` (dikecualikan agar tidak membuka koneksi trigger MariaDB).
- **Hasil Aktual**:
  - Total berkas diuji: **27 test files**.
  - Jumlah tes: **221 passed (0 failed)**.
  - Jumlah asersi: **1038 assertions**.
  - Durasi: **178.05s** di atas PHP 8.2.35 ZTS.

### 6.2 Frontend Unit Tests
- **Eksekusi**: `& "...\runtime\node-v24.22.1\...\node.exe" --test`
- **Hasil Aktual**: **18 passed (0 failed)** dalam 961ms.
- Menguji auth race conditions, CSRF 419 single retry, route guards per-ability, single logout 204, password reset client validation, dan query parameter export placement.

### 6.3 Frontend Production Build
- **Eksekusi**: `& $node $npmCli run build`
- **Hasil Aktual**: **PASS** (Vite 6.3.5, 789 modules transformed, durasi 1m 14s). Output `dist/` ter-generate sempurna.

### 6.4 Frontend Targeted Lint
- **Eksekusi**: `& npx.cmd eslint src/views/pages/public/Student.vue src/main.js` (tanpa flag `--fix`)
- **Hasil Aktual**: **0 errors**, 2574 warnings (seluruh warning adalah LF vs CRLF `prettier/prettier Delete ␍`, identik dengan baseline).

---

## 7. Status Server UAT & Hasil Smoke Test Terarah

### 7.1 Proses Server yang Dijalankan (Aktif untuk Review Pemilik)

| Komponen | PID Induk | PID Child | Executable | Port & Bind | Checkout |
|---|---|---|---|---|---|
| **API Backend** | `3812` | `15960` | `C:\xampp\php82-operational\php.exe` | `127.0.0.1:18021` | `%TEMP%\...\school-sec-env\staging\checkout\laravel-api` |
| **SPA Frontend** | `17964` | `17964` | `...\node-v24.22.1\...\node.exe` | `127.0.0.1:14021` (strict) | `%TEMP%\...\school-sec-env\staging\checkout\vue-app` |

Waktu mulai: 2026-10-26 03:33 WIB. Port 18021 dan 14021 diverifikasi bebas sebelum start. Penghentian proses massal php/node tidak dilakukan.

---

### 7.2 Hasil Smoke Test Terarah (16 Skenario Lengkap)

Dijalankan secara terisolasi terhadap server aktif di atas:

| No | Skenario Pengujian | Endpoint / Aksi | Hasil Aktual | Status |
|---|---|---|---|---|
| 1 | CSRF Bootstrap | `GET /sanctum/csrf-cookie` | Status 204, Cookie `school_compat_session` & `XSRF-TOKEN` terbit | **PASS** |
| 2 | Login Operator | `POST /login` (user: `uat.operator`) | Status 204, sesi terotentikasi | **PASS** |
| 3 | Verifikasi Kemampuan | `GET /api/check-auth` | `authenticated: true`, memuat kemampuan `master.*`, `students.*`, dll. | **PASS** |
| 4 | Daftar Siswa All & Rekap | `GET /api/students?school_year_id=1&placement=all` | Status 200, total meta 3 siswa, rekap per gender & rombel terhitung akurat | **PASS** |
| 5 | Filter Placed & Unplaced | `GET /api/students?school_year_id=1&placement=placed` | Status 200, placed total 3, unplaced total 0 | **PASS** |
| 6 | Pencarian Nama Karakter Khusus | `GET /api/students?school_year_id=1&search=FORMULA` | Status 200, berhasil menemukan siswa `=FORMULA TEST, "quoted"` | **PASS** |
| 7 | Detail Siswa | `GET /api/students/3` | Status 200, mengembalikan payload detail dan version = 1 | **PASS** |
| 8 | Edit Siswa (Version Matching) | `PUT /api/students/3` (`version: 1`) | Status 200, nama terupdate, version naik menjadi 2 | **PASS** |
| 9 | Konflik Versi (Optimistic Lock) | `PUT /api/students/3` (`version: 1` usang) | Status **409 Conflict** (`The student record has been updated by another user`) | **PASS** |
| 10 | Arsip Administratif Siswa | `DELETE /api/students/3` (`version: 2`) | Status 200, siswa terarsip, version naik menjadi 3, rombel dinonaktifkan | **PASS** |
| 11 | Daftar Siswa Terarsip | `GET /api/students?school_year_id=1&only_archived=1` | Status 200, siswa terarsip tampil dengan version = 3 | **PASS** |
| 12 | Restore Siswa Terarsip | `POST /api/students/3/restore` (`version: 3`) | Status 200, siswa aktif kembali, version naik menjadi 4 | **PASS** |
| 13 | Ekspor CSV Terfilter | `GET /api/students/export?school_year_id=1&placement=all` | Status 200, `Content-Type: text/csv; charset=UTF-8`, formula ternetralisasi | **PASS** |
| 14 | Logout Operator | `POST /logout` | Status 204, sesi diakhiri bersih di server | **PASS** |
| 15 | Otorisasi Kepala Sekolah | Login `uat.kepsek`: <br>- View list: 200 OK (`students.view`) <br>- Create student: **403 Forbidden** (tanpa `students.manage`) <br>- View archived: **403 Forbidden** (tanpa `students.manage`) <br>- Export CSV: 200 OK (`students.export`) | Hak akses peran Kepala Sekolah ditegakkan presisi oleh Gate server | **PASS** |
| 16 | Otorisasi Wakil Kesiswaan | Login `uat.waka`: <br>- View list: 200 OK (`students.view`) <br>- Export CSV: **403 Forbidden** (`wakil-kesiswaan` tidak memiliki `students.export`) | Pembatasan hak ekspor Wakil Kesiswaan ditegakkan presisi | **PASS** |

*Catatan Fitur:* Penempatan rombel via UI **tidak diklaim tersedia** karena belum dibangun (kandidat 03d di luar scope).

---

## 8. Panduan Akses Lokal Pemilik & Prosedur Start/Stop

### 8.1 Cara Memperoleh Kredensial UAT Secara Lokal
Kredensial dan password akun sintetis UAT tidak ditulis pada laporan ini demi keamanan. Pemilik dapat mengakses kredensial dengan cara:
1. Membaca password dari file lokal privat di mesin:
   ```powershell
   Get-Content "C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\secret_uat_password.txt"
   ```
2. Atau menetapkan password baru secara lokal melalui Artisan Tinker UAT:
   ```powershell
   $php = "C:\xampp\php82-operational\php.exe"
   $checkout = "C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\staging\checkout\laravel-api"
   & $php $checkout\artisan tinker --env=uat
   # Di dalam Tinker:
   # \App\Models\User::where('username', 'uat.operator')->first()->update(['password' => bcrypt('password-pilihan-anda')]);
   ```
3. Akun UAT yang tersedia: `uat.operator` (Operator Sekolah), `uat.admin` (Admin Sistem), `uat.kepsek` (Kepala Sekolah), `uat.waka` (Wakil Kesiswaan).

### 8.2 Cara Mengakses UAT di Browser
Buka browser menggunakan profil terpisah (misal Chrome / Edge):
```powershell
# Jalankan browser dengan user-data-dir terpisah
Start-Process "chrome.exe" -ArgumentList "--user-data-dir=C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\runtime\browser-profile", "http://127.0.0.1:14021"
```

### 8.3 Prosedur Penghentian Server UAT Terverifikasi (Stop)
Untuk menghentikan server UAT tanpa memengaruhi proses lain:
```powershell
# 1. Hentikan child PHP server di port 18021
$apiConn = Get-NetTCPConnection -State Listen -LocalPort 18021 -ErrorAction SilentlyContinue
if ($apiConn) { Stop-Process -Id $apiConn.OwningProcess -Force }

# 2. Hentikan parent artisan serve (PID 3812)
$apiParent = Get-Process -Id 3812 -ErrorAction SilentlyContinue
if ($apiParent -and $apiParent.Path -like "*php*") { Stop-Process -Id 3812 -Force }

# 3. Hentikan SPA Vite server di port 14021 (PID 17964)
$spaConn = Get-NetTCPConnection -State Listen -LocalPort 14021 -ErrorAction SilentlyContinue
if ($spaConn) { Stop-Process -Id $spaConn.OwningProcess -Force }
```

---

## 9. Rencana Rollback & Risiko Tersisa (OPEN)

### 9.1 Prosedur Rollback
- Jika UAT ingin dibersihkan sepenuhnya:
  1. Hentikan proses pada port 18021 dan 14021 mengikuti prosedur 8.3.
  2. Hapus database sintetis `school_compat_uat`:
     `mysql -u root -e "DROP DATABASE IF EXISTS school_compat_uat; DROP USER IF EXISTS 'school_compat_app'@'127.0.0.1'; DROP USER IF EXISTS 'school_compat_app'@'localhost';"`
  3. Konfigurasi PHP global (`C:\xampp\php82-operational`) dan Apache containment **TETAP DIPERTAHANKAN** (tidak ada rollback ke PHP 8.1.25 lama dan tidak membuka containment Apache).

### 9.2 Risiko yang Tetap OPEN
1. **Advisory Dependency**: 48 Composer advisories & 20 npm vulnerabilities tersisa pada lockfile yang dipertahankan. Update terarah direncanakan terpisah.
2. **Eksposur Historis**: File `.env` dan log berpotensi terekspos sebelum containment Apache aktif pada sesi terdahulu. Rotasi kredensial produksi dan audit log historis tetap menjadi pekerjaan terpisah.
3. **Masa Dukungan Runtime & Framework**: Laravel 10 berstatus EOL (sejak Feb 2025); PHP 8.2 security support berakhir 31 Des 2026 (~2 bulan lagi). Modernisasi menuju Laravel 12 tetap direncanakan pada Jalur B.
4. **Penerimaan Manual 03c**: Checklist manual #1–#23 pada `PHASE-03C-MANUAL-REVIEW.md` tetap berstatus **PENDING review manual pemilik**. Subfase 03c dan Fase 03 tetap **PARTIAL**.
