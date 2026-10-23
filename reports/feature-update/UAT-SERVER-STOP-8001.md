# Penghentian server UAT sekolah 127.0.0.1:8001

Tanggal: 2026-10-23 (WIB). Branch `main`. HEAD kode: `e5c4b41`.
Status: **SERVER UAT 8001 DIHENTIKAN; PORT 8001 BERSIH; DATA/SESSION TIDAK DIHAPUS.**
Fase 03 tetap **PARTIAL**. Penerimaan manual 03c #1-#23 tetap **PENDING**. Approval 01-02 dan 03a-03b tidak berubah.

> **Catatan insiden di luar target:** setelah tree UAT 8001 dihentikan, tidak ada lagi
> proses `php.exe` di host dan listener `127.0.0.1:8000` juga hilang. PID 2384 (server
> 8000) tidak pernah diperintahkan untuk dihentikan dan tidak ada `Stop-Process` langsung
> ke PID tersebut. Penyebab pasti belum dapat dibuktikan; kemungkinan terkait console/job
> cleanup saat parent Artisan dihentikan. Server **tidak** dijalankan ulang. Lihat bagian 4.

---

## 1. Scope

Hanya penghentian server UAT sekolah lama di `127.0.0.1:8001` dan pencatatan hasil.
Tidak ada audit dependency, DB, migrasi, test aplikasi, cutover global, perubahan Apache/PHP/PATH/service/firewall, atau pekerjaan fase berikutnya.

---

## 2. Verifikasi identitas sebelum tindakan

| Bukti | Nilai |
|---|---|
| Listener | `127.0.0.1:8001`, state LISTEN |
| PID pemilik | `20444` |
| Executable | `C:\xampp\php\php.exe` |
| Command line child | `php.exe -S 127.0.0.1:8001 C:\xampp\htdocs\app-administration_school\laravel-api\vendor\laravel\framework\src\Illuminate\Foundation\Console/../resources/server.php` |
| Parent child | `cmd.exe` PID `17428` |
| Parent Artisan | `php.exe` PID `13828`, command line `php.exe artisan serve --host=127.0.0.1 --port=8001 --env=uat` |
| Parent Artisan | PID `16648` sudah tidak ada; Artisan berjalan sebagai orphan |
| Working directory | PID `13828` = `C:\xampp\htdocs\app-administration_school\laravel-api`; PID `17428` dan `20444` = `...\laravel-api\public` |
| Checkout | `C:\xampp\htdocs\app-administration_school\laravel-api` |
| Bukti UAT | `--env=uat` pada command line; `laravel-api\.env.uat` ada (isi tidak dibaca) |
| Terminal pemilik | Tidak tersedia; parent PID 16648 hilang dan child `cmd.exe` dijalankan dengan stdout/stderr dialihkan ke file temp oleh Laravel |
| Environment proses | Tidak dibaca/dicetak |

Working directory dibaca melalui API proses Windows read-only, bukan dari environment. Tidak ada rahasia yang dicetak.

Pemeriksaan pertama sebelum stop dibatalkan tanpa tindakan karena validasi command line parent terlalu ketat (path checkout tidak ada di command line Artisan, hanya di command line child). Setelah relasi parent/child, executable, port, `--env=uat`, dan checkout diverifikasi ulang, penghentian dijalankan.

---

## 3. Metode dan waktu penghentian

- Tidak ada terminal pemilik yang dapat dipakai untuk penghentian normal; metode yang dipakai adalah penghentian proses Windows pada PID yang sudah diverifikasi.
- Urutan: hentikan parent Artisan `13828` lebih dahulu, lalu child `cmd.exe` `17428`, lalu child `php -S` `20444`, untuk mengurangi risiko respawn.
- Waktu penghentian: **2026-10-23 15:07:31 +07:00**.
- Tidak ada `clear-cache`, logout/revoke session, penghapusan file, atau perubahan konfigurasi.

---

## 4. Hasil verifikasi

| Pemeriksaan | Hasil |
|---|---|
| Listener 8001 setelah stop | **0 listener** |
| PID 13828/17428/20444 | tidak ada |
| Respawn 8001 setelah 3 detik dan 10+ detik | tidak ada |
| Port 8001 diambil proses lain | tidak |
| Proses `php.exe` tersisa | **0** |
| Listener 8000 (server operational) | **hilang**; sebelum stop PID 2384 |
| Listener 80/443 Apache | tetap aktif, PID 9360/15464 |
| MariaDB 3306 | tetap aktif, PID 1840 |
| Request ke endpoint Laravel/DB | tidak dilakukan |
| Restart server | tidak dilakukan |

**Insiden di luar scope:** PID 2384 (listener 8000) bukan target dan tidak dihentikan secara langsung. Setelah penghentian tree 8001, seluruh proses `php.exe` (termasuk PID 2384 dan PID 13232) tidak ada. Penyebab pasti belum terbukti; kemungkinan besar proses-proses tersebut berbagi console/job dengan parent Artisan yang dihentikan. Ini adalah dampak yang harus ditinjau pemilik. Server 8000 **tidak** dijalankan ulang sesuai batas tugas.

---

## 5. Data dan artefak yang dipertahankan

- Source, `.env`, `.env.uat`, database, session, log, dan artefak UAT tidak diubah/dihapus.
- `.env` dan `.env.uat` masih ada; timestamp tidak berubah selama sesi ini (isi tidak dibaca).
- File output proses `sf_proc_02.out`/`sf_proc_02.err` di temp tidak dihapus.
- `console.txt` dan `vue-app/test-results/` tetap tidak di-commit.
- Konfigurasi Apache containment dari sesi sebelumnya tetap; hash `httpd.conf` `A24BE18C...`, App Gudang `A9944CAD...` tidak berubah.

---

## 6. Status dan langkah berikutnya

- Server UAT sekolah `127.0.0.1:8001` **dihentikan**.
- Session/data UAT **tersimpan**; tidak ada pembersihan atau pencabutan session.
- Kelanjutan runtime menunggu cutover PHP global selesai.
- Pemilik perlu memutuskan pemulihan server 8000 (di luar scope sesi ini); jangan menganggapnya berjalan.
- Audit dependency, DB/migrasi/smoke, cutover global, dan fitur berikutnya **NOT RUN**.
- 03c #1-#23 tetap PENDING; Fase 03 tetap PARTIAL.
