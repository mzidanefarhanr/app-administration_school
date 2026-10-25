# Audit Kompatibilitas PHP 8.2 Global dan Rencana Runtime Sekolah

Tanggal: 2026-10-26 (WIB). Branch `main`.
Baseline kode: `d5ab43677776a1f1fcec12520e34199b61934206` (pencatatan penghentian server UAT 8001).
Baseline implementasi akhir 03c: `e15f028`.
Status subfase: Fase 01–02 DITUTUP LENGKAP; Subfase 03a–03b DITERIMA PEMILIK; Subfase 03c IMPLEMENTED & VERIFIED (AGEN), checklist manual #1–#23 **PENDING**. Fase 03 tetap **PARTIAL**.
Status tindakan sesi ini: **AUDIT AWAL READ-ONLY SELESAI; RENCANA KOMPATIBILITAS DISUSUN; TIDAK ADA SERVER/DB/TEST YANG DIJALANKAN.**

---

## 1. Status Fakta dan Bukti Verifikasi (TERBUKTI / DUGAAN / NOT RUN)

| Komponen | Status | Bukti / Catatan |
|---|---|---|
| PHP CLI global terminal | **TERBUKTI** | `where.exe php` me-resolve `C:\xampp\php82-operational\php.exe`. Versi: **PHP 8.2.35 (cli) ZTS x64** (built Oct 20 2026 04:17:59). `php.ini` dimuat dari `C:\xampp\php82-operational\php.ini`, scanned ini: none. |
| Runtime rollback `C:\xampp\php` | **TERBUKTI** | Masih ada di disk, versi **PHP 8.1.25 (cli) ZTS x64**. Tidak aktif pada PATH terminal saat ini. |
| Runtime stageb `C:\xampp\php82-stageb` | **TERBUKTI** | Ada di direktori XAMPP; artefak transisi cutover, bukan runtime aktif terminal. |
| Launcher Composer | **TERBUKTI** | `C:\ProgramData\ComposerSetup\bin\composer.bat` mengeksekusi `php "%~dp0composer.phar" %*`. Menjalankan Composer 2.8.2 di atas `C:\xampp\php82-operational\php.exe` (PHP 8.2.35). |
| Apache web PHP SAPI | **TERBUKTI** | Apache berjalan (PID 16684, child 16108). `httpd-xampp.conf` memuat `php_module` dari `C:/xampp/php82-operational/php8apache2_4.dll`, `PHPINIDir C:/xampp/php82-operational`. Startup log Apache error.log mencatat: `AH00455: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.2.35 configured -- resuming normal operations`. |
| Containment Apache sekolah | **TERBUKTI** | `Include "conf/extra/httpd-school-containment.conf"` aktif di `httpd.conf:573`. Pemeriksaan loopback HTTP/HTTPS ke `app-administration_school/`, `.env`, `.git/HEAD`, log, dan `public/` seluruhnya mengembalikan **403 Forbidden**. |
| Listener port aplikasi | **TERBUKTI** | Port 80 (Apache PID 16684), Port 443 (Apache PID 16684), Port 3306 (MariaDB PID 1840). Port 8000 (operational), 8001 (UAT lama), 3307, 8081, 4001, 18021, 14021: seluruhnya **FREE** (0 listener). Tidak ada proses `php.exe` berjalan di host saat audit. |
| Node.js dan npm global | **TERBUKTI** | `node.exe` di `C:\Program Files\nodejs\node.exe` versi **v24.18.0**; `npm` versi **11.4.2**. Runtime portable sekolah di `%TEMP%\opencode\school-sec-env\runtime` memuat Node **v24.22.1** (npm 11.19.1). |
| Scheduled Task sekolah | **TERBUKTI** | Tidak ditemukan scheduled task Windows yang terkait dengan `school`, `laravel`, `artisan`, atau backup sekolah (`Get-ScheduledTask`). |
| Launcher / script khusus sekolah | **TERBUKTI** | Tidak ada file `.bat`, `.cmd`, `.ps1`, atau `.sh` di direktori proyek sekolah selain bawaan dependency. Konfigurasi editor `.vscode/settings.json` hanya berisi format-on-save Prettier/ESLint, tanpa path PHP hardcode. |
| Perilaku runtime Laravel sekolah di PHP 8.2.35 | **NOT RUN** | Aplikasi tidak di-bootstrap, tidak ada endpoint atau artisan yang dipanggil pada sesi ini. |
| Database / migrasi sekolah | **NOT RUN** | Database tidak dibuka, tidak ada query atau koneksi DB yang dijalankan. |

---

## 2. Audit Kompatibilitas Backend dari File

### 2.1 Manifest dan Lockfile (`composer.json` & `composer.lock`)
- Framework: `laravel/framework v10.48.29` (constraint root: `^8.1`).
- Autentikasi API: `laravel/sanctum v3.3.3` (constraint: `^8.0.2`).
- CLI tool: `laravel/tinker v2.10.1` (constraint: `^7.2.5|^8.0`).
- HTTP client: `guzzlehttp/guzzle 7.9.3` (constraint: `^7.2.5 || ^8.0`).
- Testing: `pestphp/pest v2.36.0` (constraint: `^8.1.0`), `pestphp/pest-plugin-laravel v2.4.0` (`^8.1.0`), `phpunit/phpunit 10.5.36` (`>=8.1`), `brianium/paratest v7.3.1` (`~8.1.0 || ~8.2.0 || ~8.3.0`).
- Dev tools: `laravel/breeze v1.29.1` (`^8.1.0`), `laravel/pint v1.20.0` (`^8.1.0`), `spatie/laravel-ignition 2.9.1` (`^8.1`).

### 2.2 Pengecekan Platform Requirement Composer
Dijalankan non-mutasi: `C:\xampp\php82-operational\php.exe composer.phar check-platform-reqs --no-plugins --working-dir=laravel-api`:
- `php 8.2.35`: **success**
- Seluruh 125 paket locked memenuhi constraint PHP 8.2 (tidak ada conflict atau batasan versi maksimal yang dilanggar).
- Ekstensi manifest: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `phar`, `reflection`, `session`, `simplexml`, `tokenizer`, `xml`, `xmlwriter`: seluruhnya **success** (8.2.35).

### 2.3 Kebutuhan Source di Luar Manifest
Diverifikasi langsung pada `C:\xampp\php82-operational\php.ini`:
- `pdo_mysql`: **YES** (tersedia untuk koneksi MariaDB/MySQL).
- `pdo_sqlite` & `sqlite3`: **YES** (tersedia; fail-safe `phpunit.xml` / `tests/CreatesApplication.php` mewajibkan sqlite in-memory untuk automated testing).
- `zip`: **YES**; `ZipArchive::EM_AES_256`: **YES** (tersedia; standar enkripsi AES-256 backup Fase 02b didukung penuh).
- `proc_open`: **YES**; `disable_functions`: kosong (tidak membatasi subprocess Symfony/Process).
- `memory_limit`: `512M` (mencukupi untuk build/test).

### 2.4 Script Composer dan Plugin
- Plugin: `pestphp/pest-plugin` dan `php-http/discovery` diizinkan di `composer.json`.
- Script: `post-autoload-dump` memanggil `@php artisan package:discover --ansi`.
- Risiko: jika `composer install` dijalankan tanpa opsi `--no-scripts`, Artisan akan langsung di-bootstrap. Oleh karena itu, instalasi harus selalu memakai `--no-scripts --no-plugins` sampai environment siap.

---

## 3. Audit Frontend Terpisah (`vue-app`)

### 3.1 Karakteristik Arsitektur
- **SPA Statis Murni**: Dibangun menggunakan Vite 6.3.5 dan Vue 3.4.34.
- Tidak ada SSR, Nuxt, Nitro, Express, atau server runtime Node di sisi produksi. Output build berupa file statis di `dist/` (`index.html`, bundle JS, CSS, aset).
- Server Node hanya dipakai saat development (`vite dev server`) atau unit test runner (`node --test`).

### 3.2 Kompatibilitas Engine Node
- `package.json` tidak memiliki batasan ketat `engines`.
- Vite 6.3.5 kompatibel penuh dengan Node 24.x (telah diverifikasi pada build test sebelumnya).
- Runtime Node global `v24.18.0` dan portable `v24.22.1` keduanya mampu menjalankan `node --test` dan `npm run build`.

### 3.3 Analisis Script Frontend
- `dev`: `vite --host`.
  - **Risiko**: `--host` tanpa parameter bind mengikat ke `0.0.0.0` (seluruh interface jaringan). Port default 4000 tidak menggunakan `--strictPort`, sehingga dapat bergeser ke 4001, 4002 jika terisi.
- `dev:uat`: `vite --mode uat --host 127.0.0.1 --port 4001`.
  - Mengikat ke loopback `127.0.0.1` pada port 4001.
  - Memuat file environment mode UAT (`.env.uat`).
  - Belum memiliki flag `--strictPort` (perlu ditambahkan agar gagal jika port terisi, bukan berpindah diam-diam).
- `build`: `vite build`. Menghasilkan bundel produksi ke `dist/`.
- `preview`: `vite preview`. Menjalankan server preview statis lokal.
- `lint`: `eslint --fix . --ext .vue,.js,.jsx,.cjs,.mjs --fix --ignore-path .gitignore`.
  - **PERINGATAN**: Mengandung `--fix` mutatif; dilarang digunakan untuk pemeriksaan read-only. Pemeriksaan lint harus memakai `npx eslint <target>` terarah tanpa `--fix`.

### 3.4 Validasi Environment di Source
- `vue-app/src/apiLogin/index.js:7` mewajibkan `VITE_API_BASE_URL` terdefinisi dan valid (`http:` atau `https:`). Bila tidak valid, melemparkan error konfigurasi dan tidak mengirim request HTTP fallback.

---

## 4. Evaluasi Advisory dan Risiko Tersisa

### 4.1 Patch Runtime PHP dan Node
1. **PHP CVE-2026-109618 & CVE-2026-109619**:
   - Status: Kerentanan RCE via OOM longjmp bailout state memory corruption.
   - Versi terdampak: `<8.2.35`, `8.3.0 - <8.3.36`, `8.4.0 - <8.4.27`.
   - Versi aktual: **PHP 8.2.35**. Telah diverifikasi bahwa PHP 8.2.35 memuat patch untuk `GHSA-w8hw-w9fx-j79w` dan `GHSA-6wmf-4rv5-p3rp` ("Avoid state corruption via bailout"). Runtime global operasional saat ini berada pada versi perbaikan.
2. **Node.js CVE-2026-111497**:
   - Status: Buffer overflow pada decoder Google Brotli versi 1.0.3 s.d. 1.2.0.
   - Node aktual: v24.18.0 dan v24.22.1 keduanya mengemas `brotli: 1.2.0`.
   - Dampak pada aplikasi sekolah: Rendah. Sekolah adalah SPA client-side. Node hanya digunakan sebagai CLI tool build/dev loopback tanpa dekompresi stream eksternal yang tidak dipercaya.

### 4.2 Lifecycle Dukungan Framework dan Runtime
1. **PHP 8.2**: Rilis 8 Des 2022. Dukungan aktif berakhir 31 Des 2024. Dukungan keamanan berakhir **31 Des 2026** (tersisa ~2 bulan).
2. **PHP 8.3**: Dukungan aktif hingga 31 Des 2025; keamanan hingga 31 Des 2027.
3. **Laravel 10**: Rilis Feb 2023. Dukungan bugfix berakhir Agustus 2024; dukungan keamanan berakhir **Februari 2025** (sudah berstatus EOL / End-of-Life).

### 4.3 Advisory Dependency Tersimpan (Bukti Audit 2026-10-22)
- **Composer**: 48 advisories pada 14 paket (termasuk `laravel/framework`, `guzzlehttp/*`, `symfony/*`, `league/commonmark`).
- **npm** (`--omit=dev`): 20 vulnerability (1 critical `form-data`, 16 high termasuk `axios`, `vue`).
- Status: Pre-existing pada lockfile yang dipertahankan. Bukan blocker kompatibilitas engine PHP 8.2, tetapi menjadi target modernisasi bertahap.

### 4.4 Paparan Sensitif dan Rahasia Host
- Containment Apache menolak akses ke root sekolah, `.env`, `.git/HEAD`, dan log (403 loopback).
- Risiko historis tetap terbuka: file-file tersebut pernah mengembalikan 200 sebelum containment diterapkan. Penanganan rotasi kredensial (DB, SMTP/token, session) dan pemeriksaan log historis tetap merupakan pekerjaan terpisah. Jangan merotasi `APP_KEY` tanpa analisis data terenkripsi.

---

## 5. Rencana Kompatibilitas Tiga Jalur

```
+-----------------------------------------------------------------------------------+
| JALUR A: MINIMUM DEV / UAT (Prioritas Dekat)                                      |
| - Gunakan PHP 8.2.35 global (C:\xampp\php82-operational)                         |
| - Tidak perlu PHP portable permanen (karena PHP 8.2 memenuhi platform reqs)       |
| - Node 24 global / portable kompatibel untuk dev & build                          |
| - Pertahankan containment Apache (403) & port terisolasi (18021 / 14021)          |
| - Lockfile tetap; tidak ada update massal                                         |
+-----------------------------------------------------------------------------------+
                                         |
+----------------------------------------v------------------------------------------+
| JALUR C: KELANJUTAN FITUR (Setelah Kompatibilitas Terbukti & Diizinkan)           |
| - Baseline implementasi 03c tetap e15f028                                         |
| - Checklist penerimaan manual 03c #1-#23 tetap PENDING review pemilik             |
| - Fase 03 tetap PARTIAL; Subfase 03d (UI penempatan rombel) belum diotorisasi     |
| - Selesaikan review manual 03c sebelum melangkah ke fitur baru                    |
+-----------------------------------------------------------------------------------+
                                         |
+----------------------------------------v------------------------------------------+
| JALUR B: MODERNISASI JANGKA PANJANG (Terkoordinasi)                               |
| - Rencana upgrade framework bertahap: Laravel 10 -> Laravel 11                    |
| - PHP 8.2 EOL Dec 2026 -> persiapan transisi ke PHP 8.3 / 8.4                     |
| - Koordinasi dengan pemilik dan Qoder untuk keseragaman lintas aplikasi           |
| - Targeted update dependensi Composer & npm berisiko tinggi                       |
+-----------------------------------------------------------------------------------+
```

### Jalur A: Minimum untuk Development dan UAT
1. **PHP Runtime**: Menggunakan PHP global yang telah diverifikasi: `C:\xampp\php82-operational\php.exe` (PHP 8.2.35). Runtime portable sekolah di temp tidak perlu dipertahankan permanen karena platform requirements lockfile terbukti 100% terpenuhi oleh PHP 8.2 global.
2. **Sanitasi Launcher**: Pastikan tidak ada launcher atau script lokal yang merujuk kembali ke `C:\xampp\php` lama.
3. **Node Runtime**: Menggunakan Node global v24.18.0 atau portable v24.22.1 untuk build frontend.
4. **Lockfile**: Dipertahankan tanpa modifikasi atau `--ignore-platform-reqs`.

### Jalur B: Modernisasi Jangka Panjang
1. **Upgrade Framework Terencana**:
   - Mengingat Laravel 10 berstatus EOL dan PHP 8.2 mendekati batas akhir dukungan keamanan (31 Des 2026), modernisasi ke Laravel 11 direncanakan sebagai fase tersendiri setelah Fase 03 selesai.
   - Langkah: verifikasi compatibility matrix Laravel 11, upgrade `laravel/framework` ke `^11.0`, upgrade `pest` ke v3, migrasi struktur konfigurasi Laravel 11 yang lebih ramping.
2. **Keseragaman Lintas Aplikasi**: Disajikan sebagai proposal koordinasi teknis bersama tim App Gudang/pemilik, bukan keputusan sepihak.

### Jalur C: Kelanjutan Fitur Sekolah
1. Mengembalikan fokus ke status deliverable sekolah:
   - Subfase 03a dan 03b tetap DITERIMA PEMILIK.
   - Subfase 03c (Daftar, Rekap & Ekspor Siswa) tetap `IMPLEMENTED & VERIFIED (AGEN)`.
   - Penerimaan manual pemilik #1–#23 pada `PHASE-03C-MANUAL-REVIEW.md` tetap **PENDING**.
   - Fase 03 tetap **PARTIAL**.
   - Subfase 03d (aksi penempatan rombel via UI) tetap **BELUM DIOTORISASI**.

---

## 6. Rencana Pengujian dan Pembukaan Kembali UAT (Rancangan — BELUM DIJALANKAN)

### 6.1 Isolasi Lingkungan UAT
1. **Checkout Uji**:
   - Dapat memanfaatkan checkout terisolasi di `%TEMP%\opencode\school-sec-env\staging\checkout` atau checkout kerja khusus di luar DocumentRoot bersama.
   - Storage framework (`cache`, `sessions`, `views`, `logs`) terpisah total dari aplikasi lain.
2. **Database Sintetis Khusus**:
   - Menggunakan database sintetis disposable baru: misal `school_compat_uat` pada port 3306 (atau instance terpisah bila diotorisasi).
   - Pengguna DB terpisah: `school_compat_app` dengan hak akses terbatas hanya pada `school_compat_uat`.
   - Dilarang keras menggunakan database produksi `backdata_administrations` atau database App Gudang.
3. **Fail-Safe Guard Pre-Bootstrap**:
   - Sebelum menjalankan `artisan serve` atau test suite, guard eksternal wajib memastikan:
     a. `bootstrap/cache/config.php` tidak ada (mencegah caching konfigurasi lama).
     b. `DB_DATABASE` pada environment aktif bukan `backdata_administrations`.
     c. `storage` mengarah ke folder disposable.

### 6.2 Port dan Konfigurasi Jaringan yang Diusulkan (BELUM DISETUJUI)
- **API Backend**: `127.0.0.1:18021` (listener loopback strict, status saat ini: FREE).
- **SPA Frontend**: `127.0.0.1:14021` (listener loopback strict, status saat ini: FREE).
- **Alasan tidak memakai 8000/8001**: Port 8000/8001 sebelumnya terkait dengan proses lama dan riwayat insiden console cleanup; port baru 18021/14021 menjamin isolasi total dari sisa socket/cache browser lama.
- **Konfigurasi State & Cookie**:
  - `SESSION_COOKIE=school_compat_session` (mencegah tabrakan cookie sesi dengan aplikasi lain di localhost).
  - `SANCTUM_STATEFUL_DOMAINS=127.0.0.1:14021,localhost:14021`.
  - `FRONTEND_URL=http://127.0.0.1:14021`.
- **Profil Browser**: Pengujian manual/UAT wajib menggunakan browser profile terpisah (`--user-data-dir`).

### 6.3 Urutan Eksekusi Pengujian (Ketika Diotorisasi)
1. **Langkah 1 (Backend Unit/Feature Tests)**:
   - Dijalankan via `C:\xampp\php82-operational\php.exe artisan test` pada SQLite in-memory (209+ test).
   - `tests/Feature/Student/MariaDbStudentIntegrationTest.php` (test konkurensi trigger MariaDB) hanya dijalankan setelah DB sintetis disposable siap.
2. **Langkah 2 (Frontend Unit Tests & Build)**:
   - `node --test` (18 passed unit tests) dan `npm run build` menggunakan Node 24.
3. **Langkah 3 (Smoke Test Manual 03c Terarah)**:
   - Jalankan API di 18021 dan SPA di 14021 secara terkontrol.
   - Verifikasi alur login akun sintetis (`uat.operator`, `uat.kepsek`).
   - Verifikasi 03c: paginasi server, filter All/Placed/Unplaced, pencarian, rekapitulasi, ekspor CSV, dan form arsip/restore.

### 6.4 Kriteria Terukur
- **Development tanpa server dapat dilanjutkan**: Jika laporan audit ini disetujui pemilik dan kesesuaian PHP 8.2 diterima.
- **UAT boleh dinyalakan**: Setelah database sintetis disiapkan, konfigurasi port 18021/14021 disetujui, dan pemilik memberikan otorisasi eksplisit untuk menjalankan server.
- **Risiko tetap OPEN**: EOL framework Laravel 10; PHP 8.2 security EOL akhir 2026; temuan 48 Composer & 20 npm advisories; eksposur historis sebelum containment.

---

## 7. Tindakan yang Memerlukan Otorisasi Berikutnya

1. Persetujuan penggunaan PHP global 8.2.35 untuk development/UAT sekolah (Jalur A).
2. Otorisasi pembuatan target database sintetis disposable `school_compat_uat` dan kredensialnya.
3. Otorisasi pembukaan server API UAT di `127.0.0.1:18021` dan SPA di `127.0.0.1:14021` dengan strict port.
4. Otorisasi eksekusi review checklist manual 03c #1–#23 oleh pemilik.
