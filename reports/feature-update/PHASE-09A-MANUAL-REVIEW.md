# Panduan Review Manual — Subfase 09a (Metadata dan Versi Arsip Dokumen)

Subfase: 09a — metadata arsip, kategori, tautan domain, masa berlaku, versi file immutable, pencarian/filter, dan download berizin.
Keputusan: **DEC-37 APPROVED** (D1–D10, 2026-11-02 WIB; **"Saya setuju D1-D10"**); **DEC-35/DEC-36 tetap**; **DEC-13 OPEN**. Laporan teknis: `reports/feature-update/PHASE-09A-REPORT.md`; rencana: `reports/feature-update/PHASE-09-PLAN.md`.
Baseline induk: `main` pada `b26fb7f` (pencatatan DEC-37); SHA paket review dilaporkan setelah commit/push. Status: **IMPLEMENTED — agent-verified (2026-11-02)**. Status review pemilik: **PENDING (#1–#18)**.

## Status penerimaan pemilik

- Persetujuan rencana D1–D10 (**DEC-37**) mengotorisasi implementasi; persetujuan rencana bukan penerimaan hasil.
- Pemilik belum menyatakan penerimaan checklist 09a. Semua status di bawah adalah **bukti agen**, bukan approval.
- Item **#18** (penghentian server UAT) dijalankan agen setelah verifikasi UAT sesuai instruksi pemilik agar lingkungan uji yang sudah dilewati dihentikan; ini bukan bagian dari penerimaan manual.
- Batas: penerimaan 09a tidak mengotorisasi merge/deploy/force-push, migrasi database operasional/MariaDB, perubahan dependency/lockfile, perubahan service/`.env`, **09b**, atau retensi/pemusnahan **DEC-13**. Jangan menyentuh database operasional, `.uat-phase05/06/07/08`, Apache, atau service MariaDB.

## Lingkungan UAT (terisolasi)

- SPA: `http://127.0.0.1:14027`
- API: `http://127.0.0.1:18027`
- Data: `.uat-phase09` SQLite; cookie `school_phase09_session`; storage `.uat-phase09/storage`
- Launcher: `laravel-api/scripts/phase09-uat.php`
- Akun: **`uat.operator`** (Operator Sekolah; manage + view), **`uat.admin`** (Admin Sistem; manage + view), **`uat.kepsek`** (Kepala Sekolah; view semua domain), **`uat.waka`** (Wakil Kesiswaan; view-only), **`uat.guru`** (peran Guru; tanpa ability arsip), **`uat.inactive`** (nonaktif), **`uat.archivist`** (peran UAT khusus `archives.view` tanpa ability domain; hanya dokumen umum). Password hanya di runtime privat `.uat-phase09/account-password.txt`; tidak dicetak di dokumen.
- Server UAT **telah dihentikan 2026-11-02 WIB setelah verifikasi UAT**; port 18027/14027 dipastikan bebas; `.uat-phase09` dipertahankan. Penghentian bukan penerimaan manual.
- Jangan me-reset `.uat-phase09`; `prepare`/`prepare-archives` idempoten dan mempertahankan baris yang sudah ada. Perubahan data akibat review manual adalah data UAT yang wajar.

Perintah restart bila server sudah berhenti:

```powershell
# API (dari laravel-api)
C:\xampp\php82-operational\php.exe -S 127.0.0.1:18027 scripts/phase09-uat.php

# SPA (dari vue-app; mode uat memakai konfigurasi API 18027)
npx vite --mode uat --host 127.0.0.1 --port 14027
```

Perintah status (read-only, hanya membaca `.uat-phase09`):

```powershell
# dari laravel-api
C:\xampp\php82-operational\php.exe scripts/phase09-uat.php status
```

Angka acuan saat ini (hasil pengujian agen, data tidak di-reset):

- **121 dokumen arsip** — 40 Student Record, 20 Employee Record, 20 Letter, 20 Leave, 21 School Document (20 fixture + 1 dokumen UI `#121`).
- **162 versi file / 162 file privat**. Fixture awal: 120 dokumen / 160 versi; dokumen UI `#121` menambah 2 versi.
- **7 akun** (`uat.admin`, `uat.operator`, `uat.kepsek`, `uat.waka`, `uat.guru`, `uat.inactive`, `uat.archivist`); 123 siswa; 20 pegawai; 20 surat; 20 izin.
- Contoh data untuk review:
  - Dokumen UI `#121`: School Document berjudul mengandung "UI Created", 2 versi PDF (178 B dan 181 B), metadata `version=3` setelah edit judul.
  - Filter kategori **Student Record** → 40 dokumen; filter validitas **Expired** → 24 dokumen saat pengujian (halaman 2 memuat 4).
  - Pencarian "UI Created" → 1 dokumen.
  - `uat.archivist` hanya melihat 21 dokumen umum (20 fixture + `#121`); dokumen bertaut tidak muncul.
  - `uat.kepsek` melihat 121 dokumen dan keempat tipe subjek.

## Checklist

### 1. Operator melihat menu Archives dan daftar dokumen
- Login sebagai `uat.operator`, buka `/public/archives`.
- Harapkan menu **Archives** dan daftar 120 dokumen (sebelum dokumen UI) dengan 20 baris per halaman dan 6 halaman; filter/pagination berfungsi.
- Status agen: **PASS** (browser/API).

### 2. Create dokumen umum dengan PDF
- Klik **New Archive Document**, pilih kategori School Document/Other, isi judul dan tanggal, unggah PDF, simpan.
- Harapkan dokumen baru, versi 1 tampil, detail menampilkan metadata dan versi awal.
- Status agen: **PASS** (browser; dokumen `#121`).

### 3. Create dokumen siswa lewat picker
- Pilih **Subject Type = Student**, cari siswa lewat picker, unggah file, simpan.
- Harapkan subjek tampil pada daftar/detail dan file tersimpan.
- Status agen: **PASS dengan catatan** — API/tes/fixture mencakup tautan siswa (40 dokumen student) dan picker hanya memuat domain yang diizinkan; alur create UI khusus siswa tidak dijalankan terpisah. Bagian UI tersebut **NOT RUN**.

### 4. Create dokumen employee/letter/leave dan picker sesuai izin
- Sebagai operator/admin, buka picker untuk tiap tipe; sebagai `uat.archivist`, opsi tipe subjek harus kosong.
- Harapkan hanya tipe yang diizinkan tampil dan create berhasil untuk tipe yang sah.
- Status agen: **PASS dengan catatan** — fixture mencakup 20 employee, 20 letter, 20 leave; `uat.kepsek` melihat 4 tipe, `uat.archivist` melihat 0 tipe; alur create UI per domain tidak dijalankan terpisah. Bagian UI tersebut **NOT RUN**.

### 5. Tautan subjek arbitrer/tidak ada ditolak
- Panggil `POST /api/archives` dengan `subject_type` di luar whitelist, `subject_type` tanpa `subject_id`, `subject_id` tanpa tipe, atau ID yang tidak ada untuk tipe tersebut.
- Harapkan `422`; tidak ada dokumen baru.
- Status agen: **PASS** (tes/API).

### 6. Add version dan download current/versi lama
- Buka detail dokumen, klik **Add Version**, unggah file baru dan catatan opsional.
- Harapkan versi baru bertambah, versi lama tetap ada, download current dan versi lama berhasil.
- Status agen: **PASS** — UI menambah versi 2 pada `#121`; download current berhasil; download versi lama dan byte file dicakup tes/API.

### 7. Edit metadata, subjek read-only, versi basi
- Klik **Edit**, ubah judul/nomor/tanggal/deskripsi; pastikan field subjek tidak dapat diubah.
- Simulasikan versi basi (dua tab atau request manual): harapkan `409` + dialog **Reload Latest**; setelah reload data terbaru tampil.
- Status agen: **PASS dengan catatan** — edit UI menaikkan metadata ke versi 3; `409` dan `latest` dicakup tes/API serta UI menyediakan Reload Latest; dua tab browser nyata **NOT RUN**.

### 8. File salah tipe/ekstensi ganda/>10 MB ditolak
- Unggah file bukan PDF/JPG/JPEG/PNG, ekstensi ganda, atau >10 MB.
- Harapkan `422`; tidak ada dokumen/versi baru dan tidak ada orphan (laporan agen).
- Status agen: **PASS** (tes; kompensasi file saat penyimpanan gagal diuji).

### 9. Search dan filter
- Uji pencarian judul/nomor, filter kategori, subjek (tipe + id), validitas, dan rentang tanggal dokumen.
- Harapkan hasil dan total sesuai.
- Status agen: **PASS** — UI search "UI Created" → 1, kategori Student Record → 40, validitas Expired → 24; filter subjek/rentang tanggal dicakup tes/API.

### 10. Badge/status validitas
- Periksa dokumen pada batas tanggal: tanpa `valid_until` (`none`), `valid_from` masa depan (`upcoming`), berlaku (`valid`), ≤30 hari (`expiring_soon`), dan sudah lewat (`expired`).
- Harapkan badge/status sesuai; `upcoming` menang atas status lain.
- Status agen: **PASS** (tes batas WIB/presedensi + filter UI).

### 11. Kepala Sekolah melihat/mengunduh semua domain tanpa tombol manage
- Login `uat.kepsek`: daftar 121 dokumen, keempat tipe subjek terlihat, tanpa New/Edit/Add Version.
- Panggil create/update/add version langsung → `403`.
- Status agen: **PASS dengan catatan** — daftar semua domain dan `can.manage=false`/`403` terbukti; unduhan kepsek tidak dijalankan terpisah, tetapi policy download mengikuti `view` dan dicakup tes.

### 12. Wakil Kesiswaan view-only
- Login `uat.waka`: daftar/detail/download boleh; create/update/add version via API harus `403`.
- Status agen: **NOT RUN** — `uat.waka` tidak dijalankan terpisah pada sesi browser 09a; tes ability mencakup penolakan tulis bagi pemegang `archives.view` tanpa `archives.manage`, dan `uat.archivist` membuktikan perilaku view-only.

### 13. Guru tidak melihat menu Archives
- Login `uat.guru`: menu **Archives** tidak tampil; buka deep link `/public/archives` → Access Denied.
- Panggil `GET /api/archives` → `403`.
- Status agen: **PASS** (browser/API; ability hanya `leaves.request`).

### 14. Archivist hanya dokumen umum
- Login `uat.archivist`: hanya 21 dokumen umum; dokumen student/employee/letter/leave tidak muncul.
- Direct show/download dokumen bertaut → `404`; filter `subject_type=student` → `403`.
- Status agen: **PASS** (browser/API).

### 15. Path/checksum dan audit tidak bocor
- Periksa respons API: tidak boleh memuat `path` atau `checksum_sha256`.
- Periksa audit: tidak memuat judul, nomor dokumen, nama file, path, atau checksum.
- Status agen: **PASS** (tes).

### 16. Header download dan file hilang
- Unduh dokumen umum: harapkan `200`, `X-Document-Filename`, `Access-Control-Expose-Headers`, `nosniff`, CSP sandbox, `Cache-Control: private, no-store`, dan **tanpa** `Content-Disposition`; byte file sesuai.
- Hapus/rencanakan file hilang pada fixture terisolasi: harapkan `404`.
- Status agen: **PASS** (API/tes); header diperiksa pada unduhan browser.

### 17. Layout, keyboard, console, dan tombol
- Periksa layout desktop, tabel yang dapat digulir, dialog full-width, navigasi keyboard/aria, dan tidak ada tombol dummy.
- Harapkan 0 error console selain probe `409`/`422`/`403` yang disengaja.
- Status agen: **PASS sebagian / NOT RUN** — sesi browser 09a hanya desktop 1440×900; viewport 390 px, audit keyboard/aria, dan visual regression **NOT RUN**. Dua pesan console teramati: 1 peringatan aksesibilitas ("A form field element should have an id or name attribute") dan 1 resource `404`; tidak diklasifikasikan sebagai defect fungsional 09a.

### 18. Penghentian server UAT
- **DONE oleh agen (2026-11-02 WIB)** — API/SPA dihentikan setelah verifikasi UAT, port 18027/14027 dipastikan bebas, `.uat-phase09` dipertahankan; Apache/MariaDB tidak disentuh.
- Status: **DONE (bukan penerimaan manual; dijalankan sesuai instruksi pemilik agar lingkungan uji yang sudah dilewati dihentikan)**.

## Cara melaporkan hasil

Sampaikan nomor yang berhasil/gagal dan catatan singkat untuk kegagalan (langkah, pesan, atau screenshot), termasuk nomor yang **SKIP** atau **NOT RUN**. Jangan mengubah data operasional atau me-reset `.uat-phase09`.

## Batas bukti agen (NOT RUN)

- Viewport 390 px, audit keyboard/aria, dan visual regression berbasis gambar: hanya desktop 1440×900 yang diuji.
- Race MariaDB/InnoDB atau multi-proses nyata; seluruh runtime UAT memakai SQLite.
- Dua tab browser nyata untuk `409`.
- Restore drill yang memuat tabel/file arsip; benchmark volume besar; antivirus/malware scanning.
- Preview inline, audit akses download, expiry task, arsip/restore, retensi/purge (**09b/DEC-13**).
- Alur UI create dokumen bertaut per domain (#3/#4) dan peran `uat.waka` (#12) tidak dijalankan terpisah; lihat status masing-masing.
- Verifikasi browser seluruh skenario manual di luar bukti yang tercatat; checklist ini tidak otomatis PASS karena commit/push.
