# Panduan Review Manual — Subfase 09b (Retensi, Akses Aman, dan Pengingat Arsip)

Subfase: 09b — preview inline aman, verifikasi SHA-256 saat baca, audit akses append-only (`archive_access_logs`), pengingat masa berlaku di dashboard (`ArchiveExpiryWidget.vue`), arsip/restore dokumen berversi (`expected_version`/`409`), dan laporan retensi read-only tanpa purge (`app:archives-retention --dry-run`).
Keputusan: **DEC-38 APPROVED** (D1–D11, 2026-11-03 WIB; **"Saya setuju D1-D11"**; D6 = **Opsi A** tanpa eksekusi purge; **DEC-13 PARTIALLY CLOSED untuk lingkup modul arsip dokumen**, sisanya tetap OPEN untuk Fase 13). Laporan teknis: `reports/feature-update/PHASE-09B-REPORT.md`; rencana: `reports/feature-update/PHASE-09B-PLAN.md`.
Baseline induk: `main` pada `b405fe7` (pencatatan DEC-38 APPROVED). Status teknis: **IMPLEMENTED — agent-verified (2026-11-03)**. Status review pemilik: **PENDING (#1–#18)**.

## Status penerimaan pemilik

- **PENDING (#1–#18)** — menunggu pemeriksaan pemilik. Commit/push paket review ini **bukan** penerimaan manual.
- Server UAT `.uat-phase09` (`127.0.0.1:18027` dan `127.0.0.1:14027`) **sudah dihentikan oleh agen pada 2026-11-03 WIB** setelah verifikasi selesai (DEC-27), dengan seluruh data `.uat-phase09` dipertahankan. Gunakan perintah di bawah untuk menyalakan server saat hendak menguji di browser, dan hentikan kembali setelah selesai (#18).
- Batas: review 09b tidak mengotorisasi eksekusi purge/pemusnahan data, penugasan `archives.purge` ke role, pengaktifan scheduler, merge/deploy/force-push, migrasi database operasional/MariaDB, perubahan dependency/lockfile, perubahan service/`.env`, atau 09c/Fase 10+.

## Lingkungan UAT (terisolasi)

- SPA: `http://127.0.0.1:14027`
- API: `http://127.0.0.1:18027`
- Data: `.uat-phase09` SQLite (`.uat-phase09/database.sqlite`); cookie `school_phase09_session`; storage `.uat-phase09/storage`
- Launcher: `laravel-api/scripts/phase09-uat.php`
- Akun:
  - **`uat.operator`** (Operator Sekolah; `archives.manage` + `archives.view` + domain siswa/pegawai/surat/izin, **tanpa** `audit.view`)
  - **`uat.admin`** (Admin Sistem; `archives.manage` + `archives.view` + semua domain + **`audit.view`**)
  - **`uat.kepsek`** (Kepala Sekolah; `archives.view` + semua domain, **tanpa** `archives.manage` dan **tanpa** `audit.view`)
  - **`uat.waka`** (Wakil Kesiswaan; `archives.view` + domain kesiswaan, view-only)
  - **`uat.guru`** (Guru; tanpa `archives.view`)
  - **`uat.archivist`** (peran UAT khusus `archives.view` **tanpa** ability domain; hanya melihat dokumen umum)
  - **`uat.inactive`** (akun nonaktif)
  - Password seluruh akun UAT tersimpan hanya di file lokal `.uat-phase09/account-password.txt` (tidak dicetak di dokumen).

Perintah menyalakan server UAT untuk pengujian manual:

```powershell
# Terminal 1 — API (dari laravel-api)
cd D:\xampp-htdocs\app-administration_school\laravel-api
C:\xampp\php82-operational\php.exe -S 127.0.0.1:18027 scripts/phase09-uat.php

# Terminal 2 — SPA (dari vue-app; mode uat mengarah ke API 18027)
cd D:\xampp-htdocs\app-administration_school\vue-app
npx vite --mode uat --host 127.0.0.1 --port 14027
```

Perintah CLI status & laporan retensi dry-run (read-only terhadap `.uat-phase09`):

```powershell
cd D:\xampp-htdocs\app-administration_school\laravel-api
C:\xampp\php82-operational\php.exe scripts/phase09-uat.php status
C:\xampp\php82-operational\php.exe scripts/phase09-uat.php retention-dry-run
```

Angka acuan `.uat-phase09` saat ini (setelah `prepare-09b` idempoten):

- **136 dokumen arsip** (`134 active`, `2 archived`):
  - 120 dokumen baseline 09a (`P09/ARCHIVE/0001`..`0120`) + 1 dokumen UI 09a (`#121`).
  - 15 dokumen additive 09b (`#122`..`#136`, nomor `P09B/ARCHIVE/001`..`015`):
    - `#122` (`P09B/ARCHIVE/001`): PDF umum `expiring_soon` (+1 hari).
    - `#123` (`P09B/ARCHIVE/002`): PNG umum `expiring_soon` (+15 hari) untuk uji preview gambar.
    - `#124` (`P09B/ARCHIVE/003`): PDF siswa `expiring_soon` (+30 hari, tepat batas).
    - `#125` (`P09B/ARCHIVE/004`): PDF pegawai `valid` (+31 hari, di luar batas 30 hari).
    - `#126` (`P09B/ARCHIVE/005`): PDF surat `expired` (hari ini - 1 hari).
    - `#127` (`P09B/ARCHIVE/006`): PDF izin `valid` (`valid_until` = hari ini).
    - `#128` (`P09B/ARCHIVE/007`): PDF umum `upcoming` (`valid_from` masa depan walau `valid_until` dekat; tidak masuk widget).
    - `#129` (`P09B/ARCHIVE/008`) & `#130` (`P09B/ARCHIVE/009`): dokumen `archived` (tidak muncul di daftar default `active` maupun widget dashboard; tampil saat filter `Status = Archived` atau `All`).
    - `#131` (`P09B/ARCHIVE/010`): dokumen dengan file fisik **sengaja tidak ada** di disk untuk probe `404` (tanpa menulis log akses).
    - `#132` (`P09B/ARCHIVE/011`): dokumen dengan isi file **sengaja tidak cocok SHA-256** untuk probe `500` (tanpa menulis log akses).
    - `#133` (`P09B/ARCHIVE/012`): dokumen aktif kandidat uji Archive/Restore di UI.
    - `#134` (`P09B/ARCHIVE/013`): Student Record bertanggal `2014-11-03` (>10 tahun, tetapi kategori `student_record` bersifat **Permanent** sehingga `0` eligible purge).
    - `#135` (`P09B/ARCHIVE/014`): School Document bertanggal `2015-11-03` (>10 tahun; **eligible** pada laporan retensi).
    - `#136` (`P09B/ARCHIVE/015`): Leave bertanggal `2023-11-03` (>2 tahun; **eligible** pada laporan retensi).
- **177 baris versi** dan **176 file fisik** di `.uat-phase09/storage/app/attachments/archives`.
- **23 baris `archive_access_logs`** (`0` baris pada `#131` dan `#132`).

## Checklist (#1–#18)

### 1. Preview inline PDF dan gambar dari detail dokumen
- Login sebagai `uat.operator`, buka `/public/archives`, buka detail dokumen PDF (mis. `#122` `P09B/ARCHIVE/001`) dan klik **Preview**; ulangi pada dokumen gambar PNG (`#123` `P09B/ARCHIVE/002`).
- Harapkan dialog preview menampilkan PDF di `<iframe>` dan gambar di `<img>` melalui stream berizin (`200`, `CSP: sandbox`, `X-Document-Filename`, tanpa URL publik).
- Status agen: **PASS (API/HTTP + kompilasi komponen)**; interaksi visual browser **NOT RUN**.

### 2. Preview versi lama pada dokumen multi-versi
- Buka dokumen yang memiliki ≥2 versi (mis. `#121` atau dokumen `P09/ARCHIVE/*` multi-versi), klik **Preview** pada versi 1 dan versi 2.
- Harapkan masing-masing versi dapat dipratinjau dan diunduh sesuai nomor versinya.
- Status agen: **PASS (tes/API)**.

### 3. Pencatatan audit akses (`download` & `preview`) tanpa mutasi metadata
- Lakukan `Preview` dan `Download` pada sebuah dokumen, lalu periksa kembali `version` dan `updated_at` dokumen serta tabel `activities`.
- Harapkan baris baru tercatat di `archive_access_logs` (`action=preview`/`download`), sedangkan `version` dokumen tidak naik dan `activities` tidak bertambah.
- Status agen: **PASS (tes/API)**.

### 4. Visibilitas Access History (`audit.view` + domain)
- Login sebagai `uat.admin` (memiliki `audit.view`): buka detail dokumen → panel **Access History** tampil, mendukung filter action (`All`/`Download`/`Preview`), tanpa menampilkan judul/nama file/path/checksum di payload log.
- Login sebagai `uat.operator` atau `uat.kepsek` (tanpa `audit.view`): panel **Access History** tidak tampil dan request langsung ke `GET /api/archives/{id}/access-logs` mengembalikan `403`.
- Status agen: **PASS (tes/API)**.

### 5. Isolasi domain pada preview, download, dan log akses
- Login sebagai `uat.archivist` (hanya `archives.view` tanpa ability domain): coba akses detail/preview/download/access-logs dokumen bertaut siswa/pegawai/surat/izin.
- Harapkan seluruhnya ditolak `404` dan tidak ada baris baru di `archive_access_logs`.
- Status agen: **PASS (tes/API)**.

### 6. Penanganan file hilang (`404`) dan ketidakcocokan SHA-256 (`500`) tanpa log palsu
- Uji download/preview pada `#131` (`P09B/ARCHIVE/010`, file fisik sengaja dihapus) → harapkan `404`.
- Uji download/preview pada `#132` (`P09B/ARCHIVE/011`, isi file sengaja diubah sehingga SHA-256 tidak cocok) → harapkan `500` tanpa byte file terkirim.
- Pastikan jumlah baris `archive_access_logs` untuk `#131` dan `#132` tetap `0`.
- Status agen: **PASS (tes T18 + probe `.uat-phase09`)**.

### 7. Arsip dokumen (`POST /api/archives/{id}/archive`) dengan alasan wajib
- Sebagai `uat.operator`, buka detail dokumen aktif (mis. `#133` `P09B/ARCHIVE/012`), klik **Archive**, coba kirim alasan <10 karakter (`422`), lalu isi alasan 10–255 karakter dan simpan.
- Harapkan status berubah menjadi `Archived`, `version` naik +1, `archived_at`/`archive_reason`/`archived_by` terisi, dan dokumen tidak lagi muncul pada filter default `Status = Active`.
- Status agen: **PASS (tes/API)**.

### 8. Pembatasan dokumen Archived (read-only tulis, tetap bisa baca/unduh/pratinjau)
- Buka detail dokumen berstatus `Archived` (`#129` `P09B/ARCHIVE/008` atau `#130` `P09B/ARCHIVE/009`).
- Harapkan tombol **Edit Metadata** dan **Add Version** nonaktif/tersembunyi (dan API mengembalikan `422` bila dipanggil paksa), sementara **Preview** dan **Download** tetap berfungsi (`200`), serta tidak ada endpoint/tombol delete.
- Status agen: **PASS (tes/API)**.

### 9. Restore dokumen (`POST /api/archives/{id}/restore`)
- Pada dokumen `Archived`, klik **Restore**, isi alasan 10–255 karakter, dan simpan.
- Harapkan status kembali `Active`, `archived_at`/`archive_reason`/`archived_by` kembali `null`, `version` naik +1, dan edit/tambah versi kembali diizinkan.
- Status agen: **PASS (tes/API)**.

### 10. Konflik versi basi (`409`) pada Archive/Restore + Reload Latest
- Kirim request `archive` atau `restore` dengan `expected_version` lama.
- Harapkan respons `409` dengan objek `latest {id, status, archived_at, version, current_version, updated_at}` tanpa mengubah data, dan tombol **Reload Latest** di UI memuat state terbaru.
- Status agen: **PASS (tes/API)**; simulasi dua tab browser nyata **NOT RUN**.

### 11. Filter daftar `Status = Active | Archived | All`
- Pada halaman `/public/archives`, uji filter **Status**:
  - `Active` (default): menampilkan 134 dokumen aktif.
  - `Archived`: menampilkan 2 dokumen (`P09B/ARCHIVE/008` dan `009`).
  - `All`: menampilkan seluruh 136 dokumen dengan badge status masing-masing.
- Status agen: **PASS (tes/API)**.

### 12. Widget pengingat masa berlaku di Dashboard (`ArchiveExpiryWidget.vue`)
- Login sebagai `uat.operator` dan buka Dashboard (`/`): periksa widget **Archive Validity Reminders** di baris teratas.
- Harapkan angka ringkasan **Expired** dan **Expiring Soon (≤30 days)** sesuai dokumen aktif yang diizinkan (dokumen `Archived` `#129`/`#130` dan dokumen `Upcoming` `#128` tidak ikut dihitung).
- Klik tombol filter/item pada widget → harapkan berpindah ke `/public/archives?validity=expired` atau `expiring_soon` dengan filter validitas otomatis terisi.
- Status agen: **PASS (tes/API + verifikasi kode komponen)**; klik visual di browser **NOT RUN**.

### 13. Batas visibilitas widget Dashboard per peran
- Login sebagai `uat.archivist`: widget hanya menghitung dokumen umum (tanpa dokumen siswa/pegawai/surat/izin).
- Login sebagai `uat.guru`: widget tidak dirender dan `GET /api/archives/tasks/expiring` mengembalikan `403`.
- Status agen: **PASS (tes/API)**.

### 14. Laporan retensi read-only di UI dan API (`GET /api/archives/retention/report`)
- Sebagai `uat.operator` atau `uat.admin`, klik **Retention Report** di halaman `/public/archives`.
- Harapkan tabel menampilkan kebijakan per kategori (`Student Record`: Permanent / `0` eligible; `Leave`: 2 tahun / `1` eligible `#136`; `School Document`: 10 tahun / `1` eligible `#135`; total `2` past retention), ringkasan log akses (`23` baris, `0` past 5-year cutoff), dan banner jelas bahwa **Purge is disabled** (`purge_enabled=false`).
- Sebagai `uat.kepsek` (view-only), tombol/endpoint laporan retensi mengembalikan `403`.
- Status agen: **PASS (tes/API)**.

### 15. CLI `app:archives-retention --dry-run` dan jaminan non-destruktif
- Jalankan `C:\xampp\php82-operational\php.exe scripts/phase09-uat.php retention-dry-run` (atau `php artisan app:archives-retention --dry-run`).
- Harapkan output tabel identik dengan laporan API, serta jumlah dokumen (`136`), versi (`177`), file (`176`), dan log akses (`23`) sebelum dan sesudah perintah **tidak berubah**. Tanpa `--dry-run`, command menolak berjalan.
- Status agen: **PASS (CLI diverifikasi pada `.uat-phase09` dan tes T16)**.

### 16. Ability `archives.purge` tidak ditugaskan ke role mana pun
- Periksa tabel `abilities` dan `ability_role`: `archives.purge` terdaftar sebagai `is_sensitive=true`, memiliki `0` baris di `ability_role`, dan `can.purge` bernilai `false` bahkan untuk `uat.admin`.
- Status agen: **PASS (tes T19 + verifikasi `.uat-phase09`)**.

### 17. Tampilan responsif (1440×900 & 390×844), keyboard/aria, dan console bersih
- Periksa halaman Dashboard dan `/public/archives` (termasuk dialog Preview, Archive/Restore, Retention Report, dan Access History) pada lebar desktop 1440×900 dan mobile 390×844.
- Harapkan tidak ada overflow horizontal yang merusak tata letak, dialog dapat ditutup dengan `Esc`, semua label berbahasa Inggris, dan 0 console error.
- Status agen: **NOT RUN** di browser otomatis (ESLint 0 error & `npm run build` PASS).

### 18. Penghentian server UAT setelah review selesai (DEC-27)
- Server UAT sudah dihentikan oleh agen pada 2026-11-03 WIB setelah verifikasi teknis (port `18027` dan `14027` bebas, `.uat-phase09` dipertahankan, Apache/MariaDB tidak disentuh).
- Bila Anda menyalakan server UAT untuk menjalankan checklist di atas, hentikan kembali setelah selesai dengan memeriksa PID listener pada port `18027` dan `14027`:

```powershell
Get-NetTCPConnection -LocalPort 18027,14027 -State Listen -ErrorAction SilentlyContinue |
  Select-Object LocalAddress,LocalPort,OwningProcess
# Verifikasi CommandLine proses terlebih dahulu, lalu hentikan PID terkait:
# taskkill /PID <PID_18027> /T /F
# taskkill /PID <PID_14027> /T /F
```

## Cara melaporkan hasil

Sampaikan nomor yang berhasil/gagal (mis. **"Pengujian nomor 1-18 sudah berhasil dan saya approved."** atau daftar temuan per nomor). Jangan mengubah data operasional atau me-reset `.uat-phase09`.
