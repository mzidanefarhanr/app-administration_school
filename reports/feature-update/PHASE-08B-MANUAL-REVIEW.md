# Panduan Review Manual — Subfase 08b (Keputusan dan Rekap Izin)

Subfase: 08b — approve/reject izin submitted, pembatalan izin approved oleh approver, rekap periode, dan UI Recap.
Keputusan: **DEC-36 APPROVED** (D1–D8, 2026-11-02 WIB; **"Saya setuju D1-D8"**); **DEC-35 tetap**; **DEC-13 OPEN**. Laporan teknis: `reports/feature-update/PHASE-08B-REPORT.md`; rencana: `reports/feature-update/PHASE-08B-PLAN.md`.
Baseline induk: `main` pada `8dcfb1d` (pencatatan DEC-36); paket review **`23a38d7`**. Status: **IMPLEMENTED — agent-verified (2026-11-02)**. Status review pemilik: **APPROVED (#1–#17, 2026-11-02 WIB)**.

## Status penerimaan pemilik

- Persetujuan rencana D1–D8 (**DEC-36**) mengotorisasi implementasi; persetujuan rencana bukan penerimaan hasil.
- **Penerimaan 2026-11-02 WIB:** pemilik menyatakan **"Pengujian nomor 1-17 sudah berhasil dan saya approved."** Pernyataan ini dicatat sebagai penerimaan checklist 08b **#1–#17**; agen tidak mengulang skenario dan item NOT RUN tetap NOT RUN.
- Item **#18** bukan bagian dari pernyataan approval. Setelah penerimaan, agen menjalankan penghentian server UAT sesuai DEC-27: API PID 21084 dan SPA PID 17108 (beserta anak esbuild PID 14948) diverifikasi command line sebelum `taskkill /T /F`, port 14026/18026 dipastikan bebas, dan `.uat-phase08` dipertahankan; Apache PID 1304 (80/443) dan MariaDB PID 18244 (3306) tidak disentuh.
- Penerimaan ini dicatat pada commit dokumentasi terpisah setelah paket review `23a38d7`.
- Batas: penerimaan 08b tidak mengotorisasi merge/deploy/force-push, migrasi database operasional/MariaDB, perubahan dependency/lockfile, perubahan service/`.env`, atau retensi DEC-13. Jangan menyentuh database operasional, `.uat-phase05/06/07`, Apache, atau service MariaDB.

## Lingkungan UAT (terisolasi)

- SPA: `http://127.0.0.1:14026`
- API: `http://127.0.0.1:18026`
- Data: `.uat-phase08` SQLite; cookie `school_phase08_session`; storage `.uat-phase08/storage`
- Launcher: `laravel-api/scripts/phase08-uat.php`
- Akun: **`uat.operator`** (Operator Sekolah), **`uat.admin`** (Admin Sistem), **`uat.kepsek`** (Kepala Sekolah; satu-satunya peran dengan `leaves.approve`), **`uat.waka`** (Wakil Kesiswaan, view-only), **`uat.guru`** (peran Guru, tertaut ke `UAT-P08 Employee 01`), **`uat.inactive`** (nonaktif). Password hanya di runtime privat `.uat-phase08/account-password.txt`; tidak dicetak di dokumen.
- Server UAT **telah dihentikan 2026-11-02 WIB setelah penerimaan** (DEC-27): API PID 21084 (`C:\xampp\php82-operational\php.exe -S 127.0.0.1:18026 scripts/phase08-uat.php`), SPA PID 17108 (`node ...vite.js --mode uat --host 127.0.0.1 --port 14026`) dan anak esbuild PID 14948 diverifikasi command line sebelum `taskkill /T /F`; port 14026/18026 terverifikasi bebas; `.uat-phase08` dipertahankan.
- Jangan me-reset `.uat-phase08`; `prepare-decisions` idempoten dan mempertahankan baris yang sudah ada. Perubahan status akibat review manual adalah data UAT yang wajar.

Perintah restart bila server sudah berhenti:

```powershell
# API (dari laravel-api)
C:\xampp\php82-operational\php.exe -S 127.0.0.1:18026 scripts/phase08-uat.php

# SPA (dari vue-app; mode uat memakai konfigurasi API 18026)
npx vite --mode uat --host 127.0.0.1 --port 14026
```

Perintah status (read-only, hanya membaca `.uat-phase08`):

```powershell
# dari laravel-api
C:\xampp\php82-operational\php.exe scripts/phase08-uat.php status
```

Angka acuan saat ini (hasil pengujian agen, data tidak di-reset):

- **124 izin** — 34 draft, 41 submitted, 21 cancelled, 17 approved, 11 rejected; 101 subjek siswa, 23 subjek pegawai.
- **49 lampiran** / 49 file privat; **55 keputusan** (`approved` 23, `rejected` 11, `cancelled` 21). Target fixture `prepare-decisions`: 20 approved (sebagian bercatatan), 10 rejected, 5 approved→cancelled; selisih berasal dari pengujian browser.
- **123 siswa** dengan 123 keanggotaan aktif tahun ajaran **2026/2027**; **21 pegawai** (21 aktif; 2 tertaut ke akun); **6 akun**.
- Self leave `#123` (subjek/pembuat `uat.kepsek`) dan subject-self decoy `#124` (subjek pegawai yang tertaut ke `uat.kepsek`) tersedia untuk uji self-decision.
- Contoh data untuk review:
  - Approve via browser: `#98` (submitted v2 → approved v3 oleh `uat.kepsek`).
  - Approved→cancelled: 5 izin hasil `prepare-decisions` (alasan 10–255; `decided_*` dipertahankan).
  - Izin rejected: 10 izin hasil `prepare-decisions` (alasan 10–500).
  - Batas inklusif rekap: `#121` mulai 2026-11-02; `#40` berakhir 2026-11-01.
  - Clipping: `#109` (2026-10-10 s.d. 2026-11-08; 30 hari) dihitung 2 hari untuk periode 1–2 Nov; `#40` (2 hari) dihitung 1 hari.
  - Tanpa kelas: `#121` (`student_rombel_id=NULL`) tampil `Unplaced`; kelas X-A (`id=2`) pernah di-soft-delete dan tetap bernama di rekap (nilai dipulihkan setelah uji).

## Checklist

### 1. Login `uat.kepsek` dan tab Recap
- Login sebagai `uat.kepsek`, buka `/public/leaves`. Harapkan tab **Requests** dan **Recap** terlihat.
- Tombol **Approve**/**Reject** hanya muncul pada izin berstatus **Submitted**; draft/approved/rejected/cancelled tidak menampilkan aksi keputusan.
- Status agen: **PASS** (browser/API).

### 2. Approve izin siswa dengan catatan opsional
- Pilih izin siswa `submitted`, klik **Approve**, isi catatan opsional 10–500 atau kosongkan, konfirmasi.
- Harapkan status **Approved**, versi naik, dan riwayat menampilkan aktor/waktu/catatan keputusan.
- Catatan <10 atau >500 karakter → `422`; catatan kosong diterima.
- Status agen: **PASS** (browser approve `#98` menjadi Approved v3 oleh UAT Kepala Sekolah; validasi catatan dan riwayat dicakup tes/fixture).

### 3. Approve izin pegawai tanpa catatan
- Approve izin pegawai `submitted` tanpa catatan; harapkan **Approved** dan izin terhitung pada rekap periode.
- Status agen: **PASS** (fixture/API + rekap).

### 4. Reject dengan alasan
- Klik **Reject**, isi alasan 10–500, konfirmasi. Harapkan status **Rejected**, versi naik, riwayat menampilkan alasan/aktor/waktu.
- Alasan <10 atau >500, atau kosong → `422`; data tidak berubah.
- Status agen: **PASS** (tes + fixture; 10 izin rejected).

### 5. Cancel izin approved dengan alasan
- Sebagai `uat.kepsek`, buka izin **Approved**, klik **Cancel Approved**, isi alasan 10–255.
- Harapkan status **Cancelled**, `cancelled_*` terisi, riwayat menampilkan approval lama lalu pembatalan, dan `decided_by`/`decided_at` tetap.
- Alasan <10 atau >255 → `422`.
- Status agen: **PASS** (tes + fixture 5 approved→cancelled).

### 6. Rejected/cancelled bersifat final
- Pada izin **Rejected** atau **Cancelled**, aksi Cancel tidak tersedia.
- Panggil `POST /api/leaves/{id}/cancel` langsung → `409`; data tidak berubah.
- Status agen: **PASS** (tes/API).

### 7. Self-approval dilarang
- Izin self `#123` milik `uat.kepsek` tidak menampilkan **Approve**/**Reject**.
- Panggil `POST /api/leaves/123/approve` langsung sebagai `uat.kepsek` → `403`.
- Status agen: **PASS** (API/policy + UI `can`).

### 8. Subjek self dilarang
- Izin pegawai `#124` yang subjeknya tertaut ke `uat.kepsek` (dibuat operator) tidak dapat di-approve kepsek.
- Panggil `POST /api/leaves/124/approve` langsung → `403`.
- Status agen: **PASS** (API/policy).

### 9. Peran tanpa `leaves.approve`
- `uat.admin`, `uat.operator`, `uat.waka`: tidak ada **Approve**/**Reject**/**Cancel Approved** di UI.
- Panggil endpoint keputusan/cancel-approved langsung → `403`; rekap tetap `200` bila memegang `leaves.view`.
- Status agen: **PASS** (browser/API; operator `#98`/`#122`, admin, waka).

### 10. Guru hanya My Leaves
- `uat.guru` hanya melihat **My Leaves**; tidak ada **Requests**/**Recap** dan tidak ada aksi keputusan.
- `GET /api/leaves/recap` sebagai guru → `403`.
- Status agen: **PASS** (browser/API).

### 11. Dua tab/versi basi
- Bila dua tab dapat disimulasikan: keputusan kedua atas data basi → `409` dan dialog **Reload Latest**; setelah reload status terbaru tampil.
- Bila tidak dapat disimulasikan, catat **NOT RUN** untuk bagian dua tab nyata.
- Status agen: **PASS dengan catatan** — dialog basi disimulasikan lewat API + UI (`409 "Conflict: Only submitted leave requests can be approved."` + Reload Latest); dua tab browser nyata **NOT RUN**.

### 12. Default dan batas periode rekap
- Buka tab **Recap**: default bulan berjalan WIB (`from` = awal bulan, `to` = hari ini) dan total sesuai daftar/filter.
- `from`/`to` wajib; `to >= from`; rentang inklusif; maksimum 366 hari; `from > to` atau rentang berlebih → `422`.
- Status agen: **PASS** (API/UI; default 01–02 Nov 2026: 4 izin/5 hari/2 siswa/2 pegawai).

### 13. Filter rekap
- Uji filter **status**, **jenis**, **subjek**, **tahun ajaran**, **kelas**, **tipe pegawai**.
- Kelas/tahun ajaran memaksa subjek siswa; tipe pegawai memaksa subjek pegawai; kombinasi tidak sah → `422`.
- Angka total harus cocok dengan daftar/filter.
- Status agen: **PASS** (API/UI; matriks filter 2026-11-01 s.d. 2027-10-31 dan filter UI status/kelas).

### 14. Clipping hari
- Izin yang melewati batas periode dihitung hanya hari di dalam periode.
- Status agen: **PASS** (API; `#109` 30 hari → 2 hari, `#40` 2 hari → 1 hari).

### 15. Privasi dan label rekap
- Rekap tidak menampilkan alasan izin, lampiran, path/hash, NIK, atau teks bebas.
- Siswa tanpa kelas tampil **Unplaced**; kelas yang sudah soft-deleted tetap menampilkan namanya.
- Status agen: **PASS** (bentuk respons + edge case SQLite UAT terisolasi; nilai dipulihkan).

### 16. Layout, keyboard, dan dialog
- Viewport 390×844: tidak ada overflow halaman; tabel rekap bergulir di wrapper; dialog keputusan muat.
- Tab/Shift+Tab berfungsi, Escape menutup dialog, fokus terlihat.
- Status agen: **PASS** (Chrome DevTools emulation; `scrollWidth=clientWidth=390`, tabel 1106 vs 250).

### 17. Console dan tombol
- Harapkan **0 error console aplikasi** selain `409`/`422` yang disengaja; `403` yang disengaja untuk uji peran juga bukan defect.
- Tidak ada tombol dummy/placeholder; semua tombol memanggil endpoint nyata.
- Status agen: **PASS** (3 console error hanya probe yang disengaja: `409` approve, `403` recap, `403` daftar guru).

### 18. Penghentian server UAT setelah penerimaan
- **DONE setelah penerimaan (agen, 2026-11-02 WIB)** — API PID 21084 (`php -S 127.0.0.1:18026 scripts/phase08-uat.php`) dan SPA PID 17108 (`node ...vite.js --mode uat --host 127.0.0.1 --port 14026`) beserta anak esbuild PID 14948 diverifikasi command line sebelum dihentikan dengan `taskkill /T /F`; port 14026/18026 terverifikasi bebas; `.uat-phase08` dipertahankan; Apache PID 1304 (80/443) dan MariaDB PID 18244 (3306) tidak disentuh.
- Status: **DONE (bukan bagian dari pernyataan approval #1–#17; dijalankan agen sesuai DEC-27 setelah penerimaan)**.

## Cara melaporkan hasil

Sampaikan nomor yang berhasil/gagal dan catatan singkat untuk kegagalan (langkah, pesan, atau screenshot), termasuk nomor yang **SKIP** atau **NOT RUN**. Jangan mengubah data operasional atau me-reset `.uat-phase08`.

## Batas bukti agen (NOT RUN)

- Suite penuh PHPUnit: tidak dijalankan karena `Tests\Feature\Backup\MariaDbIntegrationTest` membaca `school_access_uat` (batas akses pemilik); regresi Letters dijalankan sebagai pengganti.
- Race MariaDB/InnoDB/gap-lock dan keputusan paralel multi-proses nyata; seluruh runtime memakai SQLite.
- Dua tab browser nyata untuk `409`; konflik disimulasikan lewat API + dialog basi.
- Restore drill yang memuat tabel izin; visual regression berbasis file gambar; benchmark volume besar; email/WhatsApp/notifikasi; ekspor/PDF rekap.
- Verifikasi browser seluruh skenario manual di luar bukti yang tercatat; checklist ini disediakan untuk pemilik dan tidak otomatis PASS karena commit/push.
