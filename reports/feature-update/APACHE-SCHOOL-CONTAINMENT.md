# Containment akses Apache untuk proyek sekolah

Tanggal: 2026-10-22 (WIB). Branch `main`. Baseline laporan: `2e2e557`.
Status: **CONTAINMENT DITERAPKAN; VERIFIKASI LOOPBACK PASS; LAN/INTERNET NOT TESTED.**
Fase 03 tetap **PARTIAL**. Penerimaan manual 03c #1-#23 tetap **PENDING**. Approval 01-02 dan 03a-03b tidak berubah.

> Ringkas: akses HTTP Apache ke seluruh direktori fisik
> `C:/xampp/htdocs/app-administration_school` sekarang ditolak 403, termasuk
> `.env`, `.git/HEAD`, log, `public/`, dan junction `public/storage`.
> Keberhasilan ini tidak membuktikan tidak pernah ada akses/eksposur sebelumnya.

---

## 1. Orientasi dan bukti awal

- `AGENTS.md`, `HANDOFF.md`, dan `ENVIRONMENT-SCHOOL-COMPAT.md` dibaca.
- HEAD kode saat mulai: `2e2e557495e1c2858a7754f9f47848bf2a98d608`; working tree hanya `console.txt` dan `vue-app/test-results/` (tidak di-commit).
- Apache aktif: `C:/xampp/apache/bin/httpd.exe`, Apache/2.4.58 Win64, MPM WinNT, mod_php PHP/8.1.25; PID awal 3364 (pid file `logs/httpd.pid`), child 14664.
- Konfigurasi utama: `C:/xampp/apache/conf/httpd.conf`; `DocumentRoot C:/xampp/htdocs`; parent `htdocs` memakai `Options Indexes`, `AllowOverride All`, `Require all granted`.
- Konfigurasi efektif: hanya satu vhost, `*:443 www.example.com` dari `httpd-ssl.conf:121`, dengan `DocumentRoot C:/xampp/htdocs`. Tidak ada vhost HTTP khusus sekolah.
- Tidak ditemukan Alias/AliasMatch/Location yang menunjuk ke proyek sekolah. Tidak ada aturan Directory khusus sekolah sebelum perubahan; satu-satunya aturan scoped adalah App Gudang.
- Path fisik sekolah bukan junction/symlink. Ditemukan satu junction di dalamnya:
  `laravel-api/public/storage` -> `C:/xampp/htdocs/app-administration_school/backdata-administration/storage/app/public` (masih di dalam pohon sekolah).
- Tidak ada isi `.env`, log, cookie, password, atau command line rahasia yang dicetak.

---

## 2. Perubahan yang diterapkan

Perubahan hanya membatasi direktori proyek sekolah; konfigurasi aplikasi lain dan PHP global tidak diubah.

1. File baru: `C:/xampp/apache/conf/extra/httpd-school-containment.conf`.
   - Berlaku untuk direktori fisik sekolah beserta seluruh turunannya.
   - Menolak seluruh akses (`Require all denied`).
   - Menonaktifkan directory listing dan opsi lain pada scope tersebut.
   - `AllowOverride None`, sehingga `.htaccess` di bawah path tidak dapat melemahkan aturan.
2. `C:/xampp/apache/conf/httpd.conf` ditambah satu `Include` ke file di atas, ditempatkan di bagian akhir setelah seluruh include lain agar tidak ada aturan sesudahnya yang membukanya kembali.
3. Tidak ada vhost baru, pemindahan proyek, penghapusan berkas, perubahan firewall, service registration, PATH, PHP global, atau resource App Gudang.

### 2.1 Hash konfigurasi

| File | Sebelum | Sesudah |
|---|---|---|
| `httpd.conf` | `D4E2DB1A9F2571F041846481A03A0ADACF3E17D5411E36197B0644AEC6A9EF12` | `A24BE18C89D7A58C02A8FC870379D6FC3A954F6B3E0B3B610AD0B3B3732F783E` |
| `httpd-school-containment.conf` | belum ada | `4C3D07A6121ECD81C55CFF5160AACD74A35AE7F6F82E535CFA30D7906FFF23A1` |
| `httpd-appgudang-access.conf` | `A9944CADEE10C90CFBDAFCA59907E19C67CFF14860163EA103D75382E8D2BBFA` | sama (tidak berubah) |
| `httpd-ssl.conf` | `91473E6B86E62D246D7F0758A1D671238FC1B820DD12BA5AB3D4EEBE233AD685` | sama (tidak berubah) |
| `httpd-xampp.conf` | `791876AB51F37637624CDB44EE08C9B7A00C908B6A9D0FC719496DE1E0E8524B` | sama (tidak berubah) |

Seluruh file `conf/extra/*.conf` lain juga dibandingkan dan **tidak berubah**. Konfigurasi mentah tidak dimasukkan ke Git.

### 2.2 Backup privat

- Lokasi: `C:\Users\LENOVO\AppData\Local\Temp\opencode\apache-containment-backup-20261022-231712`
- Isi: `httpd.conf.pre-containment`, `httpd.conf.after-containment`, salinan file containment baru, `baseline-hashes.txt`, `after-hashes.txt`.
- Lokasi berada di luar web root. ACL hanya `SYSTEM`, `Administrators`, dan user `LENOVO`.
- Backup tidak di-commit.

---

## 3. Pemeriksaan syntax dan penerapan

- `httpd.exe -t -f C:\xampp\apache\conf\httpd.conf` -> **Syntax OK**, exit `0`.
- `-t -D DUMP_INCLUDES` mengonfirmasi file containment dimuat dari `httpd.conf:573`.
- `httpd.exe -k restart` **gagal** dengan exit `2`: `No installed service named "Apache2.4"`. Apache di host ini berjalan sebagai console application, bukan Windows service; perintah tersebut tidak mengubah proses yang berjalan.
- Karena tidak ada service, penerapan dilakukan dengan mekanisme proses Windows pada PID yang sudah dibuktikan:
  - Parent `3364` (pemilik listener 80/443 dan isi `httpd.pid`) dihentikan; child `14664` yang tersisa dihentikan.
  - Apache dimulai kembali dengan executable dan ServerRoot yang sama: `httpd.exe -d C:/xampp/apache`, working directory `C:\xampp`, hidden window.
  - Proses baru: parent `9360`, child `15464`; listener 80/443 kembali aktif; `httpd.pid` berisi `9360`.
- Dampak: koneksi ke Apache sempat terputus sekitar 3-5 detik. Aplikasi lain yang memakai Apache berpotensi mengalami gangguan singkat. MySQL, API sekolah 8000/8001, dan resource App Gudang tidak dihentikan/diubah.
- Error log memuat startup normal `AH00455 ... configured -- resuming normal operations` untuk PID 9360; tidak ada error syntax.

---

## 4. Verifikasi status HTTP (tanpa DB, tanpa body sensitif)

Metode: `curl.exe -I` (HEAD) dengan `-o NUL`; hanya status yang dicetak. Tidak ada body, `.env`, log, atau cookie yang dibaca/dicetak. Pengujian hanya dari loopback host ini.

| Target | HTTP :80 | HTTPS :443 |
|---|---|---|
| `/app-administration_school/` | 403 | 403 |
| `/app-administration_school` (tanpa slash) | 403 | - |
| `/APP-ADMINISTRATION_SCHOOL/` | 403 | - |
| `/app-administration_school/laravel-api/.env` | 403 | 403 |
| `/app-administration_school/.git/HEAD` | 403 | - |
| `/app-administration_school/laravel-api/storage/logs/laravel.log` | 403 | - |
| `/app-administration_school/laravel-api/public/` | 403 | 403 |
| `/app-administration_school/laravel-api/public/index.php` | 403 | - |
| `/app-administration_school/laravel-api/public/storage/` (junction) | 403 | - |
| `/app-administration_school/backdata-administration/` | 403 | - |
| `/app-administration_school/README.md` | 403 | - |
| `https://www.example.com/.../.env` via `--resolve` ke 127.0.0.1 | - | 403 |
| `/app-administration_school%2f` | 404 (bukan 200/login) | - |

Verifikasi layanan lain:
- `http://127.0.0.1/` -> 302 (Apache tetap melayani; bukan endpoint aplikasi ber-DB).
- `http://127.0.0.1/appgudang/` -> 403 (aturan App Gudang tetap; konfigurasinya tidak berubah).
- Listener 80/443, 3306, 8000, 8001 tetap ada; proses API sekolah 8000/8001 tidak dihentikan.
- Tidak ada endpoint aplikasi lain yang dapat mengakses DB yang dibuka.

**Batas bukti:** status 403 di atas adalah bukti dari loopback dan vhost HTTPS lokal. **Bukan** bukti pengujian dari LAN atau internet. Apache tetap listen `0.0.0.0:80/443`; firewall tidak diubah.

---

## 5. Risiko rahasia dan batas containment

- Sebelum containment, `.env`, `.git/HEAD`, dan `laravel-api/storage/logs/laravel.log` sempat merespons 200. Containment sekarang menolak akses, tetapi **tidak membuktikan** tidak pernah ada akses sebelumnya.
- Pemeriksaan log historis/indikasi akses dan rotasi kredensial adalah pekerjaan terpisah; log tidak dihapus.
- Kandidat rahasia yang perlu ditinjau rotasinya (nilai tidak disalin):
  - kredensial database aplikasi;
  - `APP_KEY` (jangan diganti tanpa analisis data terenkripsi);
  - kredensial SMTP/mail dan token layanan eksternal;
  - secret session/CSRF bila ada;
  - isi log yang mungkin memuat PII/konfigurasi;
  - riwayat `.git` dan kredensial yang pernah ter-commit.
- Tidak ada rotasi otomatis. Beberapa kredensial mungkin dipakai bersama aplikasi lain.

---

## 6. Rollback (bila diperlukan)

1. Kembalikan `httpd.conf` dari `httpd.conf.pre-containment` di backup privat.
2. Hapus referensi `Include` ke file containment (atau biarkan file baru tanpa referensi).
3. Jalankan `httpd -t`; bila valid, hentikan dan mulai ulang Apache dengan mekanisme yang sama seperti bagian 3.
4. Rollback tidak dilakukan karena pemeriksaan syntax dan verifikasi status berhasil.

---

## 7. NOT RUN dan langkah berikutnya

**NOT RUN:** akses/query DB, migrasi, seeder, test backend, test/build frontend, smoke aplikasi, browser, pengujian LAN/internet, perubahan firewall/service/PATH/PHP, remediasi dependency, rotasi kredensial, dan pekerjaan fitur/fase.

Langkah berikutnya:
1. Review laporan ini dan status LAN/firewall; bila perlu, uji dari host lain secara terpisah.
2. Lakukan pemeriksaan log historis dan rencana rotasi kredensial sebagai pekerjaan terpisah.
3. Lanjutkan keputusan DB sintetis/smoke hanya setelah otorisasi baru.
4. 03c #1-#23 tetap PENDING; Fase 03 tetap PARTIAL.
