# Lingkungan kompatibilitas keamanan sekolah (preflight + runtime terisolasi)

Tanggal: 2026-10-22 (WIB). Branch `main`. Checkpoint awal `224529f`.
Status: **PREFLIGHT SELESAI; RUNTIME UJI TERISOLASI DISIAPKAN; FASE 03 TIDAK DITUTUP.**
Penerimaan manual 03c #1-#23 tetap **PENDING**. Approval 01-02 dan 03a-03b tidak berubah.

> **Temuan kritis (belum diperbaiki, sesuai batas tugas):** repository sekolah berada di
> `DocumentRoot` Apache bersama dan dapat diakses lewat parent directory. Saat preflight,
> `laravel-api/.env`, `.git/HEAD`, dan `laravel-api/storage/logs/laravel.log` mengembalikan
> HTTP 200 dari Apache port 80. Tidak ada perubahan Apache/XAMPP/firewall yang dilakukan.
> Lihat bagian 3 dan 8.

> **Addendum 2026-10-22:** containment Apache scoped untuk direktori sekolah sudah
> diterapkan pada sesi terpisah (baseline laporan `2e2e557`). Akses loopback HTTP/HTTPS
> ke root proyek, `.env`, `.git/HEAD`, log, dan `public/` kini 403. Ini tidak membuktikan
> tidak pernah ada akses sebelumnya; LAN/internet belum diuji. Detail, hash, backup, dan
> batas bukti: [`APACHE-SCHOOL-CONTAINMENT.md`](APACHE-SCHOOL-CONTAINMENT.md).
> Bagian 3 dan 8 di bawah tetap merupakan catatan historis preflight.

---

## 1. Batas dan hasil singkat

- Read-only terhadap kode aplikasi, database, `.env`, konfigurasi XAMPP/Apache/PHP, service, firewall, dan proses yang sudah berjalan.
- Tidak membuka database, tidak menjalankan bootstrap Laravel/`artisan`, tidak membuat endpoint `phpinfo()`, tidak mencetak isi `.env`/rahasia.
- `console.txt`, `vue-app/test-results/`, dan edit pemilik dipertahankan; tidak dibaca/diubah/di-commit.
- Runtime dan checkout uji **khusus sekolah** dibuat di luar web root bersama, memakai binary portable dan port loopback yang kosong.
- Isolasi **runtime uji** dapat dipenuhi. Isolasi **host bersama** TIDAK dapat diklaim selesai karena exposure Apache di atas.
- Tidak ada server API/SPA yang dijalankan pada sesi ini; scheduler/worker tetap OFF.

---

## 2. Checkpoint repository

| Item | Nilai |
|---|---|
| Branch | `main` |
| HEAD kode saat preflight | `224529f323ce433b21230758da990050454180af` |
| Remote | `origin` = `https://github.com/mzidanefarhanr/app-administration_school.git` |
| Upstream | `origin/main`, sinkron saat preflight |
| Working tree asli | bersih selain `?? console.txt` dan `?? vue-app/test-results/` (artefak lokal, tidak di-commit) |
| Workflow deployment | tidak ada `.github/workflows` di repo; push tidak memicu deployment otomatis yang terdeteksi |
| Perubahan sesi ini | hanya dokumen laporan/checkpoint; tidak ada perubahan kode, lock, schema, atau konfigurasi host |

---

## 3. Preflight Windows (bukti konfigurasi vs runtime)

### 3.1 PHP CLI dan Apache

| Komponen | Bukti |
|---|---|
| PHP CLI aktif | `C:\xampp\php\php.exe`, **PHP 8.1.25 (cli) ZTS x64**, build 2023-10-25 |
| php.ini CLI | `C:\xampp\php\php.ini` (dimuat); tanpa scan `.ini` tambahan |
| Apache | `C:\xampp\apache\bin\httpd.exe`, **Apache/2.4.58 (Win64)**, OpenSSL/3.1.3 |
| Modul PHP Apache | `httpd -M` menunjukkan `php_module (shared)`; `httpd-xampp.conf` memuat `php8apache2_4.dll`, `PHPINIDir C:/xampp/php` |
| Bukti PHP web berjalan | log Apache memuat startup `Apache/2.4.58 ... PHP/8.1.25 configured`; ini bukti runtime mod_php, bukan bukti endpoint aplikasi sekolah dieksekusi |
| PHP web aktual aplikasi | **NOT RUN** (tidak ada request ke endpoint Laravel sekolah; tidak bootstrap Laravel) |

Ekstensi CLI saat ini tersedia antara lain: `curl`, `fileinfo`, `mbstring`, `openssl`, `PDO`, `pdo_mysql`, `pdo_sqlite`, `zip`, `intl` tidak ada pada CLI 8.1.25. Nilai `php.ini` CLI: `memory_limit=512M`, `upload_max_filesize=40M`, `post_max_size=40M`, `max_execution_time=0`, `date.timezone=Europe/Berlin`.

### 3.2 Node/npm

| Komponen | Bukti |
|---|---|
| Node global | `C:\Program Files\nodejs\node.exe`, **v24.18.0** |
| npm global | **11.4.2** (`C:\Program Files\nodejs\npm.cmd`) |
| Node manager | tidak ditemukan nvm/fnm/nvs di path umum |
| Node uji terisolasi | portable **v24.22.1** + npm **11.19.1** (lihat bagian 5) |

### 3.3 Proses dan listener (read-only, tanpa command line mentah)

| Port | Bind | PID | Keterangan |
|---|---|---|---|
| 80, 443 | `0.0.0.0` dan `::` | 3364 | Apache bersama |
| 3306 | `::` | 1840 | MariaDB/MySQL XAMPP |
| 8000 | `127.0.0.1` | 2384 | API sekolah operasional (dibiarkan) |
| 8001 | `127.0.0.1` | 20444 | API UAT sekolah (dibiarkan) |
| 20128 | `0.0.0.0` | 20388 | proses Node lain; pemilik tidak diverifikasi; dibiarkan |
| 3307 | - | - | kosong (bukan dipakai) |
| 8081 | - | - | kosong |
| 4001 | - | - | kosong; HANDOFF menyebut SPA UAT 4001/PID 16624, tetapi listener tidak ada saat preflight |
| 5173 | - | - | kosong |
| 18021 | - | - | **kosong**, kandidat API uji |
| 14021 | - | - | **kosong**, kandidat SPA uji |

Tidak ada proses dihentikan. Port 18021/14021 tetap kosong sampai ada persetujuan menjalankan server.

### 3.4 Pemetaan web Apache

`httpd.conf`:

- `DocumentRoot "C:/xampp/htdocs"`
- `<Directory "C:/xampp/htdocs">`: `Options Indexes FollowSymLinks Includes ExecCGI`, `AllowOverride All`, `Require all granted`
- Tidak ada `<VirtualHost>` aktif; `httpd-vhosts.conf` hanya contoh terkomentar.
- `httpd-appgudang-access.conf` hanya membatasi `C:/xampp/htdocs/appgudang`; **tidak ada aturan khusus untuk `app-administration_school`**.
- `<Directory />` menolak, tetapi `C:/xampp/htdocs` dibuka kembali dengan `Require all granted`.
- `httpd-xampp.conf` hanya menolak `.ht*`, bukan `.env` atau `.git`.

Akibat yang terverifikasi melalui HTTP HEAD/GET (tanpa mencetak isi):

| URL | Hasil |
|---|---|
| `http://127.0.0.1/` | 200; listing parent `htdocs` aktif |
| `http://127.0.0.1/app-administration_school/` | 200; marker `Index of /app-administration_school` ditemukan (autoindex) |
| `.../laravel-api/.env` | **200** (file `.env` dapat diunduh) |
| `.../.git/HEAD` | **200** |
| `.../laravel-api/storage/logs/laravel.log` | **200** (log aplikasi dapat diunduh) |
| `.../laravel-api/.env.example` | 200 |

Tidak ada isi `.env`, `.git`, atau log yang dibaca/dicetak. Tidak ada endpoint Laravel yang dipanggil.

Apache listen di semua interface (`0.0.0.0:80`/`443`) dan firewall Windows mengizinkan rule `Apache HTTP Server` pada profil **Private** dan **Public**. Access log yang tersedia: 96 baris cocok pola `.env`/`.git`/`laravel.log`, 19 respons 200; semua 200 pada pola tersebut berasal dari loopback (termasuk probe audit ini), 4 hit dari `192.168.110.110` tidak berstatus 200. Ini **bukan** bukti tidak ada akses eksternal di luar cakupan log/rotasi.

### 3.5 Kesimpulan preflight

- Konfigurasi Apache memang memetakan seluruh repository sekolah, bukan hanya `laravel-api/public`.
- Directory listing, `.env`, metadata `.git`, dan log aplikasi dapat diakses lewat HTTP.
- Perbaikan path-specific **belum** diterapkan dan tidak diotorisasi pada sesi ini.
- Isolasi runtime uji tetap dapat dibuat dengan binary/checkout terpisah dan loopback; risiko host bersama tetap terbuka.

---

## 4. Verifikasi kandidat runtime

### 4.1 PHP 8.3.36

- Sumber resmi: `https://windows.php.net/downloads/releases/releases.json`.
- Artefak: `php-8.3.36-nts-Win32-vs16-x64.zip` (NTS, x64; sesuai OS 64-bit dan penggunaan CLI/built-in server, bukan mod_php).
- SHA-256 resmi: `3c9242622c7402b47c38d2ec2ed779cef3a290fd6f2bde6c35f9c482699e7900`.
- SHA-256 unduhan: **cocok**.
- Tanggal rilis pada metadata: 2026-10-21; build `Oct 20 2026`.
- Advisory pemilik:
  - `CVE-2026-109618` dan `CVE-2026-109619` ditemukan di NVD (status `Modified`, published 2026-10-21), deskripsi RCE melalui state/memory OOM.
  - Changelog resmi PHP 8.3.36 mencatat perbaikan `GHSA-w8hw-w9fx-j79w` dan `GHSA-6wmf-4rv5-p3rp` ("Avoid state corruption via bailout").
  - Kandidat 8.3.36 memuat perbaikan tersebut. Pemetaan rentang versi terdampak tidak diklaim lebih jauh; PHP 8.1.25 saat ini mendahului perbaikan dan sudah di luar jadwal dukungan keamanan 8.1.
- Kompatibilitas lock: `php: ^8.1` pada `composer.json`; `composer check-platform-reqs` untuk seluruh paket lock **success**.
- Ekstensi yang diaktifkan pada php.ini terisolasi: `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `pdo_sqlite`, `sqlite3`, `zip`, `intl`, `exif`; semua requirement ekstensi lock terpenuhi.

### 4.2 Node 24.22.1

- Sumber resmi: `https://nodejs.org/dist/index.json`; versi `v24.22.1`, LTS Krypton, npm 11.19.1.
- Artefak: `node-v24.22.1-win-x64.zip`.
- SHA-256 resmi `SHASUMS256.txt`: `d2046d28c2c3dcd211ac5145c0bf30e2047ef9dfb306693c8c6e2eccd8b95fa5`.
- SHA-256 unduhan: **cocok**.
- Audit runtime Node terpisah tidak dijalankan; audit dependensi frontend dilakukan dengan npm (bagian 6).

### 4.3 Compatibility lock

- `laravel-api/composer.lock` dan `vue-app/package-lock.json` **tidak diubah**. Hash SHA-256 checkout terisolasi sama dengan repo asli:
  - composer.lock: `64FFC92704B613D210693B5386D1532BCBEE68FF042956B0A889F443C980C5A1`
  - package-lock.json: `22830332C7C896CFD634D360CA6F93088CF378CFD5935D72C8FAA905C982240D`
- Tidak ada `composer update`, `npm update`, `--ignore-platform-reqs`, atau perubahan massal.

---

## 5. Runtime dan checkout terisolasi

Lokasi (di luar `C:\xampp\htdocs`, disposable, tidak di-commit):

```
C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env
├─ runtime\php-8.3.36\        PHP 8.3.36 NTS + php.ini terisolasi
├─ runtime\node-v24.22.1\     Node 24.22.1 + npm 11.19.1
├─ runtime\browser-profile\   profil browser terpisah (belum dipakai)
├─ runtime\logs\              log PHP/runtime
├─ composer-home\ composer-cache\
├─ npm-cache\
└─ staging\checkout\          clone repo @ 224529f, tanpa .env/artefak lokal
```

- Checkout: `git clone --no-hardlinks` dari repo lokal, HEAD `224529f323ce433b21230758da990050454180af`.
- Checkout tidak memiliki `laravel-api/.env`; tidak menyalin `.env` atau konfigurasi aplikasi lain.
- `storage/framework/{cache,sessions}`, `storage/logs` berada di dalam checkout terpisah.
- PHP `sys_temp_dir`, `upload_tmp_dir`, `session.save_path`, dan `error_log` diarahkan ke `runtime\php-8.3.36\tmp` dan `runtime\logs`.
- Composer home/cache dan npm cache dipisahkan; npm userconfig diarahkan ke file kosong.
- Browser profile terpisah disiapkan; **browser tidak dijalankan**.

### 5.1 Review script/plugin instalasi

- Composer `scripts` mencakup `artisan package:discover`; untuk sesi ini instalasi memakai `--no-scripts --no-plugins` agar tidak bootstrap Laravel.
- Plugin `pestphp/pest-plugin` disetujui di `composer.json`, tetapi **tidak diaktifkan** pada instalasi ini. Konsekuensinya `bootstrap/cache/packages.php` belum dibuat; itu prasyarat sebelum `artisan test`/serve.
- `npm ci` menjalankan instalasi biasa. npm 11.19.1 memberi peringatan `install-scripts` untuk `esbuild@0.25.5` dan tidak menjalankan postinstall karena kebijakan `allowScripts`; build tetap berhasil. `fsevents` hanya relevan macOS. Approval `npm install-scripts approve esbuild` belum dilakukan.

### 5.2 Port dan strict port

- `18021` dan `14021` diverifikasi kosong sebelum dan sesudah instalasi.
- Rencana menjalankan server memakai `--strictPort`/port eksplisit; tidak dijalankan pada sesi ini.
- Catatan cookie: cookie tidak terisolasi per port. Bila memakai `127.0.0.1` bersama UAT 8001, sesi/`XSRF-TOKEN` dapat saling menimpa. Untuk isolasi lebih kuat, gunakan label host loopback terpisah (mis. `localhost` atau `127.0.0.2`) dan `SESSION_COOKIE` unik; keputusan ini perlu review sebelum server dijalankan.

---

## 6. Pemeriksaan yang dijalankan (hasil aktual)

| Pemeriksaan | Hasil |
|---|---|
| PHP 8.3.36 `-v`, `--ini`, `-m` | PASS; ini terisolasi dimuat; ekstensi requirement tersedia |
| Composer install dari lock | PASS; 125 paket, tanpa perubahan lock, `--no-scripts --no-plugins` |
| `composer validate --strict` | PASS (`composer.json is valid`) |
| `composer check-platform-reqs` | PASS; seluruh requirement platform success |
| `composer audit --locked` | **48 advisory / 14 paket** (lihat risiko) |
| `npm ci` | PASS; 372 paket; warning `allowScripts` esbuild |
| `node --test` (Node 24.22.1) | **18 passed, 0 failed** |
| `npm run build` (Vite 6.3.5) | **PASS**, 789 modules, 27.85s; warning chunk >500 kB dan caniuse-lite |
| `npx eslint` non-mutasi (`Student.vue`, `main.js`) | **0 errors / 2574 warnings**; warning dominan `Delete ␍` (CRLF), bukan PASS bersih |
| `npm audit --omit=dev` | **20 vulnerabilities**: 1 critical, 16 high, 3 moderate |
| Backend `php artisan test` | **NOT RUN** (butuh DB/bootstrap; tidak diotorisasi) |
| Migrasi/seeder/DB | **NOT RUN** |
| API/SPA server, browser, smoke aplikasi | **NOT RUN** |

Catatan lint: `git ls-files --eol` menunjukkan working tree asli dan clone memakai `w/crlf`. Jumlah warning 2574 berasal dari line ending, bukan 217 seperti laporan 03c yang memakai blob LF. Tidak ada `--fix`/auto-format.

---

## 7. Konfigurasi target sintetis (review only; BELUM dijalankan)

Database, migrasi, seeder, dan smoke yang memerlukan DB **belum diotorisasi**. Nilai di bawah adalah target sintetis, bukan kredensial nyata. Jangan menyalin ke `.env` sebelum review.

### 7.1 Variabel target

```dotenv
APP_ENV=local
APP_DEBUG=false
APP_URL=http://localhost:18021
APP_KEY=<generate-dengan-perintah-php-di-bawah>

FRONTEND_URL=http://localhost:14021
SANCTUM_STATEFUL_DOMAINS=localhost:14021

SESSION_DRIVER=file
SESSION_COOKIE=school_env_session
SESSION_DOMAIN=localhost
SESSION_SECURE_COOKIE=false
CACHE_DRIVER=file
CACHE_PREFIX=school_env_
LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=school_env_synthetic
DB_USERNAME=school_env_app
DB_PASSWORD=<isi-setelah-review>
```

Catatan:
- `localhost` dipilih agar cookie terpisah dari UAT `127.0.0.1`; port tetap 18021/14021.
- `DB_HOST=127.0.0.1:3306` berarti memakai service MariaDB bersama. Ini **bukan** isolasi host penuh; opsi lebih kuat adalah instance MariaDB terpisah (port baru) yang belum diotorisasi.
- Database/user harus dibuat khusus, bukan database operasional/UAT/App Gudang.

### 7.2 Perintah yang diajukan (belum dijalankan)

```powershell
$env:SCHOOL_ENV='C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env'
$php="$env:SCHOOL_ENV\runtime\php-8.3.36\php.exe"
$ini="$env:SCHOOL_ENV\runtime\php-8.3.36\php.ini"
$checkout="$env:SCHOOL_ENV\staging\checkout\laravel-api"

# 1) Generate APP_KEY tanpa bootstrap Laravel
& $php -c $ini -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"

# 2) Setelah review: buat DB/user sintetis (jalankan di klien DB terpisah)
#    CREATE DATABASE school_env_synthetic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
#    CREATE USER 'school_env_app'@'127.0.0.1' IDENTIFIED BY '<password-review>';
#    GRANT ALL PRIVILEGES ON school_env_synthetic.* TO 'school_env_app'@'127.0.0.1';

# 3) Setelah review: siapkan .env dari nilai 7.1, lalu package discovery + migrasi seeder sintetis
Push-Location $checkout
& $php -c $ini artisan package:discover --ansi
& $php -c $ini artisan migrate --force
Pop-Location

# 4) Setelah review: API loopback (strict port, scheduler/worker OFF)
Push-Location $checkout
& $php -c $ini artisan serve --host=localhost --port=18021
Pop-Location

# 5) SPA loopback (terminal terpisah)
$env:PATH="$env:SCHOOL_ENV\runtime\node-v24.22.1\node-v24.22.1-win-x64;$env:PATH"
$env:VITE_API_BASE_URL='http://localhost:18021'
Push-Location "$env:SCHOOL_ENV\staging\checkout\vue-app"
npm run dev -- --host localhost --port 14021 --strictPort
Pop-Location
```

Perintah di atas adalah usulan, belum diverifikasi. `npm` harus berasal dari Node 24.22.1 pada PATH terisolasi; jangan mengandalkan npm global.

---

## 8. Perubahan path-specific yang diperlukan (TIDAK diterapkan)

Agar repository sekolah tidak lagi dapat diakses lewat parent directory, perubahan minimum yang perlu direview pemilik:

```apache
# Contoh kebijakan; JANGAN diterapkan tanpa instruksi terpisah.
<Directory "C:/xampp/htdocs/app-administration_school">
    Options -Indexes -Includes -ExecCGI
    AllowOverride None
    Require all denied
</Directory>

<Directory "C:/xampp/htdocs/app-administration_school/laravel-api/public">
    Options -Indexes -Includes -ExecCGI +FollowSymLinks
    AllowOverride All
    DirectoryIndex index.php
    Require local
</Directory>
```

Ini belum cukup untuk menghilangkan listing parent `htdocs`. `Options Indexes` pada `C:/xampp/htdocs` adalah host-wide dan memengaruhi aplikasi lain; perubahan itu memerlukan keputusan pemilik terpisah. Alternatif yang lebih aman: pindahkan checkout sekolah keluar dari `htdocs` dan layani hanya `public/` melalui vhost khusus. Semua opsi di atas **belum** dijalankan.

---

## 9. Risiko residual

1. **KRITIS - pre-existing:** `.env`, `.git/HEAD`, dan log Laravel sekolah dapat diunduh lewat Apache port 80; Apache listen semua interface dan firewall mengizinkan Public. Ini bisa membocorkan kredensial DB/APP_KEY dan data log. Belum diperbaiki.
2. **Tinggi:** dependency lock mengandung advisory: Composer 48 advisory/14 paket (termasuk `laravel/framework`, `guzzlehttp/*`, `symfony/*`, `league/commonmark`); npm 20 vulnerability (1 critical `form-data`, 16 high termasuk `axios`/`vue`). Perbaikan memerlukan update lock dan pengujian regresi; **tidak** dilakukan.
3. **Tinggi:** shared PHP web masih 8.1.25 dan mendahului perbaikan 8.3.36; status keterpaparan aplikasi terhadap CVE-2026-109618/109619 belum dinilai. Runtime 8.3.36 hanya untuk uji terisolasi.
4. **Sedang:** cookie pada `127.0.0.1` tidak terisolasi per port; gunakan host loopback terpisah/`SESSION_COOKIE` unik sebelum menjalankan SPA+API.
5. **Sedang:** `--no-scripts` membuat package discovery Laravel belum dijalankan; backend belum siap untuk `artisan test`/serve sampai langkah review dijalankan.
6. **Rendah:** npm 11.19.1 memblokir postinstall esbuild; build lulus, tetapi parity instalasi belum diverifikasi. Runtime/checkout berada di temp dan dapat dibersihkan.
7. **Rendah:** ESLint belum bersih (0 error, 2574 warning CRLF); bukti 03c lama tidak otomatis berlaku untuk checkout baru.

---

## 10. NOT RUN dan langkah berikutnya

**NOT RUN:** akses DB, migrasi, seeder, `artisan test`, `artisan serve`, Vite dev server, browser/UAT, smoke aplikasi, perubahan Apache/PHP/firewall/service, dan penerimaan manual 03c.

Langkah berikutnya (perlu instruksi terpisah):
1. Review temuan exposure Apache dan putuskan perubahan path-specific/vhost; jangan deploy sebelum ini dinilai.
2. Review konfigurasi target sintetis 7.1 dan keputusan DB terpisah vs DB khusus pada MariaDB bersama.
3. Setelah otorisasi DB: buat DB/user sintetis, jalankan package discovery + migrasi + seeder sintetis, lalu smoke di 18021/14021 dengan profil browser terpisah.
4. Review advisory dependency dan rencana upgrade terkontrol (update lock + regresi); jangan update massal.
5. 03c tetap menunggu checklist manual #1-#23; Fase 03 tetap PARTIAL.
