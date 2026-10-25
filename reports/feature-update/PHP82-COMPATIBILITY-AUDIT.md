# Audit Kompatibilitas PHP 8.2 Global, Triase Keamanan & Rencana UAT Terisolasi

Tanggal: 2026-10-26 (WIB). Branch: `main`.
Baseline laporan: `b906be9c9828faffd4e0296dc17b0acc91e10adb`.
Baseline implementasi akhir 03c: `e15f028`.
Status subfase: Fase 01–02 DITUTUP LENGKAP; Subfase 03a–03b DITERIMA PEMILIK; Subfase 03c IMPLEMENTED & VERIFIED (AGEN), checklist manual #1–#23 **PENDING**. Fase 03 tetap **PARTIAL**.
Status tindakan: **READ-ONLY AUDIT SELESAI; TIDAK ADA SERVER, DB, MIGRATION, ATAU TEST YANG DIJALANKAN.**

---

## 1. Matriks Bukti Kompatibilitas yang Dikoreksi

### 1.1 Tabel Status Fakta (TERBUKTI / DUGAAN / NOT RUN)

| Komponen | Status | Rincian dan Batas Bukti |
|---|---|---|
| PHP CLI global terminal | **TERBUKTI** | `where.exe php` me-resolve `C:\xampp\php82-operational\php.exe`. Versi: **PHP 8.2.35 (cli) ZTS x64** (build 2026-10-20). Loaded configuration file: `C:\xampp\php82-operational\php.ini`, scanned ini: none. |
| Runtime rollback `C:\xampp\php` | **TERBUKTI** | Masih ada di disk, versi **PHP 8.1.25 (cli) ZTS x64**. Tidak aktif pada PATH terminal saat ini. |
| Runtime transisi `C:\xampp\php82-stageb` | **TERBUKTI** | Ada di direktori `C:\xampp`, artefak transisi cutover, bukan runtime aktif terminal. |
| Launcher Composer | **TERBUKTI** | `C:\ProgramData\ComposerSetup\bin\composer.bat` menjalankan `php "%~dp0composer.phar" %*`. Menjalankan Composer 2.8.2 di atas `C:\xampp\php82-operational\php.exe` (PHP 8.2.35). |
| Apache web PHP SAPI | **TERBUKTI** | Apache berjalan (PID 16684, child 16108). `httpd-xampp.conf` memuat `php_module` dari `C:/xampp/php82-operational/php8apache2_4.dll`, `PHPINIDir C:/xampp/php82-operational`. Startup log Apache `logs/error.log` mencatat: `AH00455: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.2.35 configured -- resuming normal operations`. |
| Containment Apache sekolah | **TERBUKTI** | `Include "conf/extra/httpd-school-containment.conf"` aktif di `httpd.conf:573`. Pemeriksaan loopback HTTP/HTTPS ke `app-administration_school/`, `.env`, `.git/HEAD`, log, dan `public/` seluruhnya mengembalikan **403 Forbidden**. |
| Listener port aplikasi | **TERBUKTI** | Port 80 (Apache PID 16684), Port 443 (Apache PID 16684), Port 3306 (MariaDB PID 1840). Port 8000, 8001, 18021, 14021: seluruhnya **FREE** (0 listener). Tidak ada proses `php.exe` berjalan di host saat audit. |
| Node.js global vs portable | **TERBUKTI** | Node global di `C:\Program Files\nodejs\node.exe` adalah **v24.18.0** (npm 11.4.2). Node portable sekolah di `%TEMP%\opencode\school-sec-env\runtime\node-v24.22.1\node-v24.22.1-win-x64\node.exe` adalah **v24.22.1** (npm 11.19.1). |
| Scheduled Task sekolah | **TERBUKTI** | Tidak ditemukan task Windows terkait `school`, `laravel`, `artisan`, atau backup sekolah (`Get-ScheduledTask`). |
| Launcher khusus sekolah | **TERBUKTI** | Tidak ada script launcher `.bat`/`.ps1` di repo sekolah. `.vscode/settings.json` hanya berisi format-on-save Prettier/ESLint, tanpa path runtime hardcode. |
| Perilaku runtime Laravel sekolah di PHP 8.2.35 | **NOT RUN** | Aplikasi tidak di-bootstrap, tidak ada endpoint atau command artisan yang dipanggil pada sesi ini. |
| Database / migrasi sekolah | **NOT RUN** | Database tidak dibuka, tidak ada koneksi DB atau migrasi yang dijalankan pada sesi ini. |

---

### 1.2 Pembedaan Tingkat Requirement dan Versi Terpasang (Backend)

| Level | Nilai / Constraint | Catatan Kritis |
|---|---|---|
| **Root requirement** (`composer.json`) | `php: ^8.1` | Mengizinkan PHP `>= 8.1.0` hingga `< 9.0.0`. PHP 8.2.35 sah secara semantik root. |
| **Package requirements** (`composer.lock`) | Bervariasi per paket | Mayoritas `^8.1`, `>=8.1`, `^8.0`, `^7.4 || ^8.0`. Paket dengan batasan atas eksplisit: `nette/schema` (`8.1 - 8.4`), `nette/utils` (`8.0 - 8.4`), `brianium/paratest` (`~8.1.0 || ~8.2.0 || ~8.3.0`). Tidak ada paket yang menolak PHP 8.2. |
| **Versi terpasang aktif** | PHP 8.2.35 (cli) ZTS x64 | Dijalankan dari `C:\xampp\php82-operational\php.exe`. |
| **Hasil platform check lockfile** | **100% SUCCESS** | Diverifikasi via `composer check-platform-reqs --lock --no-plugins --no-scripts` langsung terhadap lockfile: seluruh 22 platform requirements (PHP dan 20 ekstensi) berstatus `success`. |
| **Batas klaim verifikasi** | **Metadata Lock Only** | `check-platform-reqs --lock` **HANYA** memverifikasi kecocokan batasan versi/ekstensi dari lockfile; **BUKAN** bukti berfungsinya aplikasi secara runtime atau ketiadaan deprecation/bug logika. |

---

### 1.3 Pembedaan Frontend (Constraint vs Versi Terkunci vs Runtime)

| Komponen | Constraint `package.json` | Versi Terkunci `package-lock.json` | Status / Catatan |
|---|---|---|---|
| `vue` | `^3.4.34` | **3.5.13** | Semver `^3.4.34` mengizinkan resolusi ke Vue 3.5.x; lockfile mengunci versi konkret 3.5.13. |
| `@vue/server-renderer` | Tidak ada di root | **3.5.13** | Dependency transitif dari `vue` dan `@vitejs/plugin-vue` untuk kompilasi SFC; tidak dieksekusi sebagai server SSR. |
| `vite` | `^6.3.5` | **6.3.5** | Dev server & bundler SPA client. |
| `axios` | `^1.10.0` | **1.10.0** | HTTP client browser di `apiLogin/index.js`. |
| Bukti build/test historis | N/A | N/A | Hasil `node --test` (18 passed) dan `npm run build` (789 modules) pada laporan 2026-10-22 adalah bukti historis di checkout staging temp, **BUKAN** dieksekusi ulang pada sesi read-only ini. |

---

## 2. Keputusan dan Rancangan Runtime Frontend

### 2.1 Penetapan Node Portable Patched v24.22.1
- **Node global (`v24.18.0`) TIDAK digunakan** untuk rencana eksekusi build/dev/UAT sekolah berikutnya.
- **Node portable patched (`v24.22.1`)** yang sudah tersedia di `%TEMP%\opencode\school-sec-env\runtime\node-v24.22.1\node-v24.22.1-win-x64\node.exe` ditetapkan sebagai runtime resmi frontend sekolah.
- **Alasan teknis**:
  1. Node.js v24.22.1 (rilis keamanan 2026-10-21) secara resmi memuat cherry-pick patch keamanan Google Brotli `CVE-2026-111497` (commit `0ed4268a4401abcb44720963` pada deps/brotli). Meskipun `process.versions.brotli` di kedua binary tetap mencetak string `'1.2.0'`, binary 24.22.1 memuat mitigasi buffer overflow tersebut, sedangkan 24.18.0 rentan.
  2. Node.js v24.22.1 juga menutup 4 kerentanan High lainnya: `CVE-2026-106069` & `CVE-2026-106072` (TLS IP non-canonical bypass), `CVE-2026-105482` (DNS null byte validation), dan `CVE-2026-105483` (TLS IP normalization regression).
  3. Argumen "aman/rendah karena SPA tanpa SSR" **dikoreksi**: toolchain build Vite, parser CSS/JS, dan Vite dev server mengeksekusi kode lokal dan membuka socket jaringan; mengabaikan patch runtime hanya karena output akhirnya SPA adalah kesimpulan yang keliru.

### 2.2 Desain Launcher Frontend Eksplisit Tanpa Fallback
Untuk mencegah fallback diam-diam ke Node global `v24.18.0`:
1. Launcher wajib menetapkan path absolut executable portable:
   ```powershell
   $nodeExe = "C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\runtime\node-v24.22.1\node-v24.22.1-win-x64\node.exe"
   $npmCli  = "C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\runtime\node-v24.22.1\node-v24.22.1-win-x64\node_modules\npm\bin\npm-cli.js"
   $nodeDir = Split-Path -Parent $nodeExe
   ```
2. Modifikasi PATH proses lokal secara terisolasi (tanpa mengubah PATH sistem/user global):
   ```powershell
   $env:PATH = "$nodeDir;$env:PATH"
   ```
3. Verifikasi pra-eksekusi wajib (fail-closed):
   ```powershell
   $resolvedNode = & where.exe node | Select-Object -First 1
   $nodeVersion  = & $nodeExe -v
   if ($resolvedNode -ne $nodeExe -or $nodeVersion -ne 'v24.22.1') {
       throw "ABORT: Node runtime mismatch! Resolved: $resolvedNode, Expected: $nodeExe"
   }
   ```
4. Subprocess npm dan Vite dijalankan melalui `$nodeExe $npmCli <args>` sehingga child process mewarisi Node 24.22.1.

---

## 3. Triase Minimum Advisory Keamanan

Klasifikasi temuan pre-existing dari audit 2026-10-22 (48 Composer advisories, 20 npm vulnerabilities). Tidak ada yang diabaikan hanya karena status "pre-existing". Dilakukan analisis reachability dan evaluasi blocker UAT nyata.

### 3.1 Temuan Kritis (Critical)

#### 1. `form-data 4.0.3` (npm)
- **CVE / GHSA**: GHSA-fjxv-7rqg-78g4 (unsafe random function untuk boundary selection); GHSA-hmw2-7cc7-3qxx (CRLF injection via multipart field names/filenames).
- **Klasifikasi**: Transitive production dependency (ditarik oleh `axios: 1.10.0`).
- **Sumber Primer**: [GitHub Advisory GHSA-fjxv-7rqg-78g4](https://github.com/advisories/GHSA-fjxv-7rqg-78g4).
- **Prasyarat Eksploitasi**: Aplikasi menjalankan HTTP client di Node.js yang mengirim request multipart/form-data ke server eksternal menggunakan pustaka `form-data`.
- **Penggunaan pada Source Sekolah**: Sekolah adalah client SPA. Seluruh pengiriman data/file (mis. form siswa) di browser menggunakan objek native `window.FormData`, bukan pustaka Node `form-data`.
- **Status Reachability**: **UNREACHABLE di browser runtime**. Pustaka `form-data` adalah dead-code pada bundel browser client.
- **Fixed Version**: `form-data >= 4.0.6` (atau via update `axios`).
- **Kandidat Update Terarah**: Update `axios` ke versi yang melepaskan dependency `form-data` usang. Bukan blocker UAT loopback.

---

### 3.2 Kelompok High Prioritas (Auth, HTTP Client, Parser, Input)

#### 2. `axios 1.10.0` (npm)
- **CVE / GHSA**: GHSA-w9j2-pvgh-6h63 (Prototype pollution merge strategy), GHSA-xx6v-rp6x-q39c (XSRF Token leakage via boolean coercion), GHSA-4hjh-wcwx-xvwj (DoS data size check).
- **Klasifikasi**: Direct production dependency (`vue-app/package.json`).
- **Sumber Primer**: [GitHub Advisory GHSA-w9j2-pvgh-6h63](https://github.com/advisories/GHSA-w9j2-pvgh-6h63).
- **Prasyarat Eksploitasi**: Penyerang mampu memanipulasi `Object.prototype` melalui input JSON yang tidak tersanitasi, atau mengarahkan redirect cross-origin saat request membawa header autentikasi.
- **Penggunaan pada Source Sekolah**: `src/apiLogin/index.js` mengonfigurasi instance tunggal `apiLogin` dengan `withCredentials: true`, `withXSRFToken: true`, dan `baseURL` yang divalidasi ketat ke host loopback API.
- **Status Reachability**: **POTENTIALLY REACHABLE di browser runtime**, namun mitigasi lokal aktif: URL divalidasi ketat (tanpa redirect cross-origin) dan aplikasi tidak melakukan deep object merge pada input pengguna yang belum tersanitasi.
- **Fixed Version**: `axios >= 1.20.0` (atau rilis 1.x patch mutakhir).
- **Risiko & Keputusan**: Bukan blocker untuk UAT loopback terisolasi. Update terarah ke `axios` versi aman disarankan sebagai paket tersendiri setelah UAT 03c selesai.

#### 3. `vue 3.5.13` & `@vue/server-renderer 3.5.13` (npm)
- **CVE / GHSA**: GHSA-g2v6-rqmx-r4w6 (XSS via missing CR in attribute-name blacklist pada `@vue/server-renderer <=3.5.41`).
- **Klasifikasi**: Direct production (`vue`) & transitive (`@vue/server-renderer`).
- **Sumber Primer**: [GitHub Advisory GHSA-g2v6-rqmx-r4w6](https://github.com/advisories/GHSA-g2v6-rqmx-r4w6).
- **Prasyarat Eksploitasi**: Aplikasi menjalankan SSR (Server-Side Rendering) di Node.js dan me-render atribut HTML dinamis dari input pengguna tanpa sanitasi.
- **Penggunaan pada Source Sekolah**: Aplikasi sekolah 100% Client-Side Rendering (CSR). `@vue/server-renderer` hanya dipanggil oleh compiler `@vitejs/plugin-vue` pada fase build.
- **Status Reachability**: **UNREACHABLE di runtime**.
- **Fixed Version**: `vue >= 3.5.42`.
- **Risiko & Keputusan**: Bukan blocker UAT.

#### 4. `laravel/framework v10.48.29` (Composer)
- **CVE / GHSA**: GHSA-5vg9-5847-vvmq (CVE-2026-48019 - CRLF injection pada default email validation rule).
- **Klasifikasi**: Direct production dependency (`laravel-api/composer.json`).
- **Sumber Primer**: [GitHub Advisory GHSA-5vg9-5847-vvmq](https://github.com/advisories/GHSA-5vg9-5847-vvmq).
- **Prasyarat Eksploitasi**: Pengguna mengirim string email berisi karakter CRLF (`\r\n`) yang lolos validasi `email` bawaan dan kemudian digunakan sebagai header email SMTP.
- **Penggunaan pada Source Sekolah**: Email hanya digunakan pada alur forgot password dan user management. Pada environment development/UAT, mailer disetel ke `MAIL_MAILER=log` (tidak ada socket SMTP aktif). Register publik telah ditutup (DEC-14).
- **Status Reachability**: **THEORETICAL / MITIGATED di UAT** (karena mail driver log dan register publik tertutup).
- **Fixed Version**: Ditutup pada Laravel 12.60.0 / 13.10.0 (atau via custom validator regex).
- **Risiko & Keputusan**: Bukan blocker UAT loopback; menjadi driver kuat untuk rencana modernisasi framework.

#### 5. `symfony/http-foundation v6.4.23` (Composer)
- **CVE / GHSA**: CVE-2025-64500 (Incorrect parsing of PATH_INFO leads to limited authorization bypass); CVE-2026-108082 (ReDoS HeaderUtils::split via Cache-Control header).
- **Klasifikasi**: Transitive production dependency (via Laravel).
- **Sumber Primer**: [Symfony Advisory CVE-2025-64500](https://symfony.com/blog/cve-2025-64500-incorrect-parsing-of-path-info-can-lead-to-limited-authorization-bypass).
- **Prasyarat Eksploitasi**: Otorisasi mengandalkan prefix URL pada reverse proxy (mis. Apache path-based access) yang dapat dikecoh oleh ambiguasi PATH_INFO.
- **Penggunaan pada Source Sekolah**: Otorisasi sekolah ditegakkan di level controller/middleware menggunakan Laravel Gate bernama (`can:students.manage`, dll.) berdasarkan objek user terotentikasi, **bukan** berdasarkan URL matching di reverse proxy.
- **Status Reachability**: **UNREACHABLE untuk bypass otorisasi sekolah**.
- **Fixed Version**: `symfony/http-foundation >= 6.4.29` (CVE-2025-64500) dan `>= 6.4.46` (CVE-2026-108082).
- **Risiko & Keputusan**: Bukan blocker UAT loopback.

#### 6. `guzzlehttp/guzzle 7.9.3` & `guzzlehttp/psr7 2.7.1` (Composer)
- **CVE / GHSA**: GHSA-v5mv-p594-2x33 (CVE-2026-69246 - Noncanonical host check bypass), GHSA-h95v-h523-3mw8 (Referer header fragment disclosure), CRLF injection.
- **Klasifikasi**: Transitive production dependency.
- **Sumber Primer**: [GitHub Advisory GHSA-v5mv-p594-2x33](https://github.com/advisories/GHSA-v5mv-p594-2x33).
- **Prasyarat Eksploitasi**: Aplikasi melakukan request outbound HTTP ke host eksternal berdasarkan input pengguna.
- **Penggunaan pada Source Sekolah**: Verifikasi kode `laravel-api/app` menunjukkan **0 pemanggilan outbound HTTP** (tidak ada `Http::`, `GuzzleHttp\Client`). Aplikasi sekolah bersifat self-contained.
- **Status Reachability**: **UNREACHABLE di aplikasi sekolah**.
- **Fixed Version**: `guzzlehttp/guzzle >= 7.15.2`, `guzzlehttp/psr7 >= 2.12.3`.
- **Risiko & Keputusan**: Bukan blocker UAT.

#### 7. `league/commonmark 2.7.0` (Composer)
- **CVE / GHSA**: 14 advisories (ReDoS via crafted markdown tables, footnotes, attributes, XML nesting).
- **Klasifikasi**: Transitive production dependency (via Laravel).
- **Penggunaan pada Source Sekolah**: Verifikasi kode `laravel-api/app` menunjukkan **0 penggunaan parser Markdown**.
- **Status Reachability**: **UNREACHABLE di aplikasi sekolah**.
- **Fixed Version**: `league/commonmark >= 2.10.2`.
- **Risiko & Keputusan**: Bukan blocker UAT.

#### 8. Toolchain Dev/Build Frontend (`postcss`, `brace-expansion`, `defu`, `picomatch`, `nanoid`)
- **Klasifikasi**: Development & build-only (`devDependencies` / compiler toolchain).
- **Status Reachability**: Hanya aktif saat `vite build` atau `vite dev server` lokal.
- **Mitigasi**: Dev server UAT wajib loopback `127.0.0.1` strict port; tidak diekspos ke jaringan LAN/internet.

---

### 3.3 Ringkasan Blocker UAT Nyata vs Risiko Tersisa

| Kategori | Item | Dampak pada UAT Terisolasi | Tindakan / Rekomendasi |
|---|---|---|---|
| **Blocker UAT Nyata** | **NIHIL** | Tidak ada blocker teknis yang menghalangi boot aplikasi pada PHP 8.2 loopback. | UAT terisolasi dapat diotorisasi setelah database sintetis dan guard siap. |
| **Risiko Terbuka #1** | Eksposur Historis Host Apache | Kredensial lama berpotensi terekspos sebelum containment 403 aktif. | UAT wajib menggunakan database dan user sintetis baru; rotasi kredensial produksi dilakukan terpisah. |
| **Risiko Terbuka #2** | EOL Framework Laravel 10 | Laravel 10 tidak lagi menerima patch keamanan upstream sejak Feb 2025. | Jalankan rencana modernisasi ke Laravel 12 (Jalur B). |
| **Risiko Terbuka #3** | EOL PHP 8.2 (31 Des 2026) | Dukungan keamanan PHP 8.2 berakhir dalam ~2 bulan. | Koordinasi upgrade PHP global ke 8.3/8.4 bersama Qoder/App Gudang. |
| **Risiko Terbuka #4** | Advisory Client Axios (High) | Prototype pollution & XSRF token edge cases. | Update terarah `axios` disiapkan sebagai paket upgrade terpisah. |

---

## 4. Koreksi Rencana Modernisasi Jangka Panjang

```
+-----------------------------------------------------------------------------------------+
| EVOLUSI FRAMEWORK LARAVEL SEKOLAH                                                       |
|                                                                                         |
|  [Laravel 10.48] (Aktif, EOL Feb 2025)                                                  |
|        |                                                                                |
|        v (Langkah transisi bila breaking changes memerlukan tahapan)                    |
|  [Laravel 11.x]  (EOL Security Mar 2026, ~5 bulan tersisa)                              |
|        |                                                                                |
|        v (TARGET UTAMA MODERNISASI - Kompatibel PHP 8.2.35)                             |
|  [Laravel 12.x]  (Didukung aktif s.d. 2026, Security s.d. Feb 2027, PHP >= 8.2)        |
|        |                                                                                |
|        v (Target koordinasi lintas aplikasi bersama Qoder & Pemilik)                    |
|  [Laravel 13.x]  (Memerlukan PHP >= 8.3 / 8.4; menunggu cutover PHP berikutnya)        |
+-----------------------------------------------------------------------------------------+
```

1. **Laravel 11 Bukan Target Akhir**: Laravel 11 hanya berstatus jembatan transisi jika upgrade langsung ke Laravel 12 mengalami kendala breaking change, karena masa dukungan keamanan Laravel 11 berakhir Maret 2026.
2. **Laravel 12 adalah Target Utama Kompatibel PHP 8.2**:
   - Persyaratan PHP: `php: ^8.2` (cocok dengan runtime operasional aktif saat ini `C:\xampp\php82-operational\php.exe`).
   - Masa dukungan: Didukung bugfix hingga akhir 2026, security support hingga Februari 2027.
   - Kompatibilitas pengujian: Mendukung Pest v2 dan v3, PHPUnit 10 dan 11.
   - Struktur skeleton: Aplikasi yang di-upgrade dari Laravel 10 **TIDAK DIWAJIBKAN** merestrukturisasi skeleton ke format baru Laravel 11/12 (`app/Http/Kernel.php` dan `RouteServiceProvider.php` tetap didukung penuh secara backward compatible).
3. **Laravel 13 dan PHP 8.3/8.4**: Menjadi agenda koordinasi lintas aplikasi bersama tim App Gudang setelah infrastruktur server siap. Tidak dipaksakan secara sepihak di proyek sekolah.
4. **Batasan Mutlak**: Dilarang melakukan `composer update` massal, downgrade, atau menyalin dependensi/patch dari App Gudang.

---

## 5. Rencana Isolasi Keras Pra-Bootstrap (Fail-Closed Guard)

Sebelum Laravel di-bootstrap pada environment UAT, script guard eksternal pra-penerbangan (**Pre-Flight Isolation Guard**) wajib dieksekusi dengan kriteria **Fail-Closed**:

### 5.1 Spesifikasi Guard Pra-Penerbangan
1. **Target Environment & Database Allowlist (Exact Match)**:
   - Target `APP_ENV` harus eksak `uat`.
   - Target `DB_CONNECTION` harus eksak `mysql`.
   - Target `DB_HOST` harus eksak `127.0.0.1` atau `localhost`.
   - Target `DB_PORT` harus eksak `3306`.
   - Target `DB_DATABASE` harus eksak `school_compat_uat`.
   - **Prinsip Fail-Closed**: Setiap nama database yang bukan `school_compat_uat` (termasuk nilai kosong, database default, database demo, maupun database produksi `backdata_administrations`) **WAJIB DITOLAK** dan membatalkan proses seketika.
2. **Precedence Environment Proses OS**:
   - `Dotenv` tidak menimpa variable environment proses yang sudah ada secara default.
   - Guard wajib memeriksa `[System.Environment]::GetEnvironmentVariable('DB_DATABASE')`, `$env:DB_DATABASE`, dan memastikan tidak ada environment variable OS warisan yang mengalahkan isi `.env.uat`.
3. **Pemeriksaan Caching dan Filesystem**:
   - `bootstrap/cache/config.php` **TIDAK BOLEH ADA**. Jika ditemukan, guard **MENOLAK START** dan melaporkannya (dilarang menghapus diam-diam).
   - `bootstrap/cache/routes-v7.php` **TIDAK BOLEH ADA**.
   - Path `storage/framework/sessions`, `storage/framework/cache`, `storage/logs` wajib divalidasi bukan junction/symlink yang mengarah ke luar folder target.
4. **Pemisahan State Cookie & Port**:
   - `SESSION_COOKIE` wajib diberi nama unik: `school_compat_session`.
   - Browser pengujian wajib menggunakan direktori profil terpisah (`--user-data-dir=...`) untuk mengisolasi storage dan cookie browser dari port localhost lainnya.
   - Port usulan: API `127.0.0.1:18021`, SPA `127.0.0.1:14021` dengan parameter `--strictPort`. Status port saat ini: **USULAN, BELUM DIIZINKAN START**.

---

### 5.2 Inventaris Suite Pengujian Backend (Pemisahan SQLite vs MySQL)

Untuk menjamin pengujian otomatis tidak menyentuh database operasional MySQL/MariaDB:

| Kategori Pengujian | File Pengujian | Koneksi Target | Perintah Eksekusi Aman |
|---|---|---|---|
| **Suite Unit & Feature Murni** (209+ test, 946+ assertions) | Seluruh file di `tests/Feature/Access`, `tests/Feature/Auth`, `tests/Feature/Student/*` (kecuali MariaDb), `tests/Unit` | **SQLite in-memory** (`:memory:`) dipaksa via `phpunit.xml` & guard `CreatesApplication.php` | `php artisan test --exclude-path=tests/Feature/Backup/MariaDbIntegrationTest.php --exclude-path=tests/Feature/Student/MariaDbStudentIntegrationTest.php` |
| **Integrasi MariaDB Concurrency & Triggers** (7 test) | `tests/Feature/Student/MariaDbStudentIntegrationTest.php` | **MySQL/MariaDB disposable** (`school_restore_drill_uat`) | Hanya dijalankan secara terarah setelah DB disposable dibuktikan siap: `php artisan test tests/Feature/Student/MariaDbStudentIntegrationTest.php` |
| **Integrasi MariaDB Backup/Restore Round-Trip** (1 test) | `tests/Feature/Backup/MariaDbIntegrationTest.php` | **MySQL/MariaDB disposable** (`school_access_uat` & `school_restore_drill_uat`) | Hanya dijalankan secara terarah untuk drill backup: `php artisan test tests/Feature/Backup/MariaDbIntegrationTest.php` |

---

## 6. Paket Implementasi Minimum & Urutan Pengujian

### 6.1 Daftar Berkas dan Perubahan yang Direncanakan
1. `laravel-api/.env.uat` (disiapkan khusus untuk target `school_compat_uat`, port `18021`, cookie `school_compat_session`, `MAIL_MAILER=log`, tanpa rahasia operasional).
2. `vue-app/.env.uat` (disiapkan khusus untuk `VITE_API_BASE_URL=http://127.0.0.1:18021`).
3. Script launcher terisolasi frontend (menggunakan Node portable 24.22.1 eksplisit).
4. Script fail-closed pre-flight guard backend.

### 6.2 Urutan Pengujian Bertahap (Ketika Diotorisasi)
1. **Fase 1 (Pre-Flight Guard & SQLite Tests)**: Jalankan guard fail-closed; jalankan 209+ automated tests di SQLite in-memory pada PHP 8.2.35.
2. **Fase 2 (Frontend Build & Unit Tests)**: Jalankan `node --test` (18 unit tests) dan `npm run build` menggunakan Node 24.22.1 portable.
3. **Fase 3 (Database Sintetis Disposable)**: Buat database kosong `school_compat_uat`, jalankan `artisan migrate` dan `ReferenceDataSeeder` + `SyntheticDataSeeder`.
4. **Fase 4 (Boot Server Terisolasi)**: Jalankan API di `127.0.0.1:18021` dan SPA di `127.0.0.1:14021` (`--strictPort`).
5. **Fase 5 (Smoke Test & Review 03c)**: Smoke test login, peran, daftar siswa, pagination server, filter All/Placed/Unplaced, rekapitulasi, ekspor CSV, dan form arsip/restore (#1–#23).

### 6.3 Prosedur Rollback
- Jika terjadi kegagalan atau ketidaksesuaian saat pengujian:
  1. Hentikan proses server API (18021) dan SPA (14021) berdasarkan PID terverifikasi.
  2. Hapus database sintetis disposable `school_compat_uat`.
  3. Konfigurasi Apache global dan PHP global **TETAP DIPERTAHANKAN** (tidak ada rollback ke PHP 8.1.25 lama dan tidak membuka containment Apache).

---

## 7. Prompt Implementasi Berikutnya untuk Review Pemilik

Bila pemilik menyetujui laporan audit ini dan siap mengotorisasi persiapan lingkungan UAT, berikut adalah draft prompt implementasi yang konkret dan terbatas:

```text
Saya menyetujui laporan audit kompatibilitas PHP 8.2 dan triase keamanan (PHP82-COMPATIBILITY-AUDIT.md).
Saya mengotorisasi persiapan lingkungan UAT terisolasi khusus sekolah:
1. Siapkan database sintetis disposable school_compat_uat pada MariaDB lokal port 3306.
2. Siapkan file konfigurasi lokal laravel-api/.env.uat (port 18021, DB school_compat_uat, cookie school_compat_session) dan vue-app/.env.uat (port 14021) dengan guard fail-closed.
3. Jalankan suite pengujian otomatis backend pada SQLite in-memory dan suite unit frontend pada Node portable 24.22.1.
4. Jalankan migrasi dan seeder sintetis pada database school_compat_uat.
5. Jalankan server UAT terbatas: API 127.0.0.1:18021 dan SPA 127.0.0.1:14021 dengan strict port untuk keperluan peninjauan checklist manual 03c #1–#23.
Jangan mengubah konfigurasi Apache atau resource App Gudang. Jangan memulai fitur Fase 03d.
```
