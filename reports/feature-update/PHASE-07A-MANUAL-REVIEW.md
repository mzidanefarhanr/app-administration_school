# Panduan Review Manual — Subfase 07a (Surat Masuk dan Lampiran Dasar)

Subfase: 07a — agenda surat masuk, lampiran privat, pencarian/filter, edit/void berversi, dan history immutable.
Keputusan: **DEC-32 APPROVED** (D1–D10, 2026-11-01 WIB; "Saya setuju D1-D10."). Laporan teknis: `reports/feature-update/PHASE-07A-REPORT.md`.
Baseline induk: `main` pada `f46f736` (pencatatan DEC-32); paket implementasi `ae2b75f`, koreksi dokumentasi `b6f0af5`. Status: **IMPLEMENTED — agent-verified**. Status review pemilik: **APPROVED 2026-11-01 WIB (#1–#16)**.

## Status penerimaan pemilik

- Pemilik menyetujui rencana D1–D10 sebagai **DEC-32** dan mengotorisasi implementasi 07a; persetujuan rencana bukan penerimaan hasil.
- **Penerimaan hasil (2026-11-01 WIB):** pernyataan pemilik **"Pengujian nomor 1-16 sudah berhasil dan saya approved."** menetapkan seluruh checklist #1–#16 sebagai **APPROVED**. Agen tidak mengulang skenario; item NOT RUN tetap NOT RUN. Penerimaan dicatat pada commit dokumentasi tersendiri, bukan pada paket implementasi.
- Batas: penerimaan 07a tidak mengotorisasi merge, deploy, migrasi database operasional/MariaDB, perubahan dependency/service/`.env`, subfase 07b, atau 07c. DEC-06 dan DEC-11 tetap OPEN dan hanya mengikat 07b/07c.

## Lingkungan UAT (terisolasi)

- SPA: `http://127.0.0.1:14025`
- API: `http://127.0.0.1:18025`
- Data: `.uat-phase07` SQLite; cookie `school_phase07_session`
- Launcher: `laravel-api/scripts/phase07-uat.php`
- Akun: **`uat.operator`** dan **`uat.admin`** (`letters.manage`), **`uat.kepsek`** dan **`uat.waka`** (view-only `letters.view`). Password hanya ada di runtime privat `.uat-phase07/account-password.txt`; tidak dicetak di dokumen.
- Server UAT **dihentikan 2026-11-01 setelah penerimaan** (DEC-27): API PID 19564 (`php.exe -S 127.0.0.1:18025 -t public scripts/phase07-uat.php`, tree 19968) dan rantai npm/vite SPA (PID 19228 → 19196 → 20504 → 9060) diverifikasi command line sebelum `taskkill /T /F`. Port 14025/18025 terverifikasi bebas; Apache/MariaDB tidak disentuh; data `.uat-phase07` dipertahankan.
- Jangan menguji pada database operasional, `.uat-phase05`, `.uat-phase06`, atau MariaDB. Jangan me-reset `.uat-phase07`; fixture `prepare-letters` idempoten dan mempertahankan baris yang sudah ada.

Perintah restart bila server sudah berhenti:

```powershell
# API (dari laravel-api)
C:\xampp\php82-operational\php.exe -S 127.0.0.1:18025 -t public scripts/phase07-uat.php

# SPA (dari vue-app)
npm run dev -- --host 127.0.0.1 --port 14025 --strictPort
```

Perintah status (read-only):

```powershell
# dari laravel-api — hanya membaca .uat-phase07
C:\xampp\php82-operational\php.exe scripts/phase07-uat.php status
```

Angka acuan saat ini (hasil pengujian agen, data tidak di-reset):

- **126 surat** (115 active, 11 void); 125 surat fixture `UAT-P07` + 1 surat buatan pengujian UI `#000126` (void).
- **64 baris lampiran** (63 aktif + 1 soft-deleted dengan file tetap disimpan), **64 file** di storage privat; surat `#000001` memiliki 5 lampiran, `#000002`–`#000010` masing-masing 2, `#000011`–`#000050` masing-masing 1.
- **221 event** (126 created, 19 updated, 11 voided, 64 attachment added, 1 attachment removed).
- **17 nomor sumber kosong**; **10 surat memakai nomor sumber duplikat** `UAT-P07/DUP/2026/001` (nomor sumber tidak unik, sesuai D2).
- Event/versi surat `#000126` saat ini **version 3**: created → updated (Subject, Notes) → attachment added → attachment removed → voided.
- Surat void contoh: `#000012`, `#000024`, `#000037`, `#000049`, `#000062`, `#000074`, `#000087`, `#000099`, `#000112`, `#000124`, dan `#000126`.

## Checklist

### 1. Isolasi, login, dan daftar awal
- Login sebagai `uat.operator`, buka menu **LETTERS → Incoming Letters**.
- Harapkan daftar **115 active** surat (default filter Status **Active**), paginasi default 20/halaman, urutan terbaru (contoh baris pertama `#000125`, 09 May 2026).
- Harapkan kolom **Agenda, Received, Letter Date, Source No., Sender, Subject, Category, Files, Status, Actions**; agenda internal berformat `#000125`; tombol **New Letter** tersedia.
- Harapkan label dan pesan English; tidak ada error console/network (catatan bug library PrimeVue ada di bagian akhir).

### 2. Pencarian dan filter
- Cari `UAT-P07 Incoming Letter 001` (atau pengirim `Dinas Pendidikan Sintetis 01`): harapkan hasil terbatas dan paginasi menyesuaikan; kosongkan pencarian.
- Pilih Category **Invitation** (Status tetap Active): harapkan **16** surat; pilih Status **Void**: harapkan **11** surat; pilih Status **All**: harapkan total **126**.
- Isi Received from `2026-03-01` dan Received to `2026-03-31` dengan Status Active: harapkan **29** surat; isi rentang terbalik (from > to): harapkan pesan validasi/`422` dan daftar tidak berubah.
- Ubah **Rows** menjadi 50: harapkan paginasi mengikuti; **Clear** mengembalikan filter awal.

### 3. Detail surat dan lampiran
- Buka **View** surat `#000001`.
- Harapkan header **Letter #000001**, tag Active/Category, **Version 1**, pembuat, tanggal terima/surat, nomor sumber, pengirim, perihal, catatan (bila ada), **Attachments (5/5)**, dan **History** berisi 5 **Attachment added** + 1 **Created** (urut terbaru).
- Harapkan lampiran menampilkan nama file, ukuran, dan waktu unggah; tombol download dan (untuk pengelola) remove tersedia.
- Buka surat `#000126`: harapkan banner **Void** dengan aktor, waktu, dan alasan; tidak ada tombol Edit/Void/Upload/Remove; lampiran yang pernah dihapus tidak muncul.

### 4. Buat surat dan validasi
- Klik **New Letter**. Coba submit kosong: harapkan pesan wajib untuk Received date, Letter date, Sender, Subject, Category.
- Isi Received date **besok**: harapkan penolakan "may not be in the future". Isi Letter date **setelah** Received date: harapkan penolakan "may not be after the received date". Pilih kategori di luar daftar (lewat API): `422`.
- Buat surat sah: Received date hari ini, Letter date sama/hari sebelumnya, Source number **opsional** (boleh kosong), sender/perihal/kategori valid, catatan opsional. Harapkan toast sukses, agenda baru `#000127`, dan baris muncul di daftar Active.
- Buat **surat kedua dengan nomor sumber yang sama** (mis. `UAT-P07/DUP/2026/001`): harapkan tetap tersimpan (nomor sumber tidak unik) dan agenda internal tetap berbeda.

### 5. Edit berversi
- Dari detail surat baru, klik **Edit**; ubah Subject dan Notes lalu **Save Changes**.
- Harapkan toast sukses, **Version** naik (contoh 1 → 2), dan History menampilkan **Updated** dengan daftar field yang berubah; Received/Letter date, nomor sumber, pengirim, kategori juga dapat diubah dengan aturan tanggal yang sama.
- Buka surat void `#000012`: harapkan tidak ada tombol Edit.

### 6. Unggah lampiran dan batas file
- Pada surat baru, unggah `laravel-api/.uat-phase07/manual-files/valid.pdf`: harapkan sukses, daftar lampiran bertambah, event **Attachment added** muncul.
- Unggah `valid.png`: harapkan sukses. Unggah total hingga **5/5**, lalu coba file ke-6: harapkan penolakan "5 files per letter" tanpa menambah file.
- Unggah file berbahaya dari `manual-files/`: `shell.php`, `payload.exe`, `page.html`, `vector.svg`, `report.pdf.php`, dan `oversize.pdf` (11 MB): harapkan semua `422` dengan pesan English; tidak ada file tersimpan.
- Hapus satu lampiran: harapkan dialog konfirmasi, event **Attachment removed**, dan file tidak lagi dapat diunduh dari UI; baris lampiran soft-deleted dan file fisik tetap ada di storage privat (sesuai D7; pembersihan menunggu DEC-13).

### 7. Unduh lampiran privat
- Klik ikon download pada lampiran `#000001`: harapkan file terunduh (nama asli dipertahankan) dan dapat dibuka; untuk PDF, browser menampilkan/menyimpan file, bukan menjalankannya inline (header `attachment` + CSP sandbox).
- Pastikan file tidak dapat diakses lewat URL publik langsung (mis. `/storage/...`): harapkan `404`/tidak tersedia; satu-satunya jalan adalah endpoint download berizin.
- Login `uat.kepsek` (view-only) dan ulangi download: harapkan berhasil.

### 8. Void dan read-only
- Pada surat active, klik **Void**; coba alasan < 10 karakter: harapkan penolakan. Isi alasan ≥ 10 karakter lalu konfirmasi **Void Letter**.
- Harapkan status **Void**, banner alasan + aktor/waktu, tidak ada Edit/Void/Upload/Remove, tetapi download lampiran tetap tersedia; surat tetap tampil pada filter Status **Void** (bukan dihapus).
- Lewat API: `PUT`/`PATCH`, upload, atau remove lampiran pada surat void → `409` "This letter is void and can no longer be changed." dengan `current_versions`; tombol void kedua juga `409`.

### 9. Konflik versi `409` (opsional; agent-verified)
- Buka dialog Edit surat lain, lalu ubah `version` surat tersebut di luar UI (DevTools/DB UAT) sebelum submit, atau buka dua tab dan simpan dari tab pertama lebih dulu.
- Harapkan `409` itemized dengan `current_versions`, dialog **Conflict**, pesan tidak ada data yang diubah, dan tombol **Reload Latest** mengambil versi terbaru.
- Bila tidak ingin mengubah data, tandai **SKIP**; agen sudah memverifikasi lewat tes otomatis dan API.

### 10. Izin view-only (kepsek dan waka)
- Login `uat.kepsek`, buka Incoming Letters: harapkan daftar/detail/history/download dapat dibaca, tetapi **tanpa** New Letter, Edit, Void, Upload, atau Remove.
- Lewat DevTools/API: `POST /api/letters/incoming`, `PUT /api/letters/incoming/{id}`, `POST .../void`, `POST .../attachments`, `DELETE .../attachments/{id}` → `403`; `GET` daftar/detail/download → `200`.
- Ulangi sebagai `uat.waka`: hasil sama. Login kembali sebagai `uat.operator` untuk langkah berikut.

### 11. Audit, privasi, dan immutability
- Periksa tabel `activities` (DB UAT) setelah create/update/void/upload/remove.
- Harapkan entri audit memuat id/versi/status/kategori/tanggal dan referensi file internal, **tanpa** notes, sender, subject, alasan void, nama file asli, path, atau checksum (allowlist D9).
- Harapkan `letter_events` hanya bertambah (created/updated/voided/attachment added/removed) dan tidak ada endpoint update/delete untuk surat, lampiran, atau event; lampiran hanya soft delete.
- Harapkan payload API tidak memuat path storage privat atau checksum mentah pada daftar.

### 12. Backup dan storage privat (opsional; agent-verified)
- File berada di `.uat-phase07/storage/app/attachments/letters/{letter_id}/...` (di luar web root) dan tercakup pemindaian `config('backup.attachments_path')`.
- Manifest backup menyebut lampiran surat operasional dan menandai restore drill dengan lampiran surat sebagai **NOT RUN**; restore drill belum dijalankan.

### 13. Responsif 390 px
- Kecilkan viewport ke 390×844 pada daftar/detail/dialog: harapkan tidak ada overflow halaman; tabel bergulir horizontal di dalam wrapper; dialog muat (contoh lebar 367 px di dalam 390 px).

### 14. English dan console
- Harapkan seluruh label/tombol/pesan English dan format tanggal English.
- Catatan: pada perubahan orientasi/resize viewport, PrimeVue 4.3.1 dapat memunculkan exception internal `Select.alignOverlay` (8 entri di pengujian agen). Ini bug library yang sudah ada, tidak memblokir fungsi, dan tidak diperbaiki karena pembaruan dependency tidak diotorisasi; harapkan tidak ada error console dari kode aplikasi.

### 15. Regresi menu dan guard
- Harapkan menu dummy lama **Letters Form** sudah tidak ada; grup **LETTERS** berisi **Incoming Letters** dan hanya tampil untuk ability `letters.view`.
- Buka route langsung `/public/letters/incoming` tanpa izin (mis. akun lain/nonaktif): harapkan guard route/`403` sesuai pola aplikasi; halaman lain (Students, Teachers, Academic) tidak berubah.
- Harapkan `php artisan route:list --path=letters` menunjukkan **9 route** dan `DELETE /api/letters/incoming/{id}` tidak tersedia (`405`).

### 16. Batas penerimaan
- Penerimaan hanya mencakup checklist #1–#15 pada lingkungan `.uat-phase07`.
- Setelah pemilik menyatakan hasil berhasil, server UAT 14025/18025 dihentikan sesuai DEC-27 dan penerimaan dicatat pada commit dokumentasi tersendiri.
- Merge/deploy, migrasi database operasional/MariaDB, perubahan dependency/service/`.env`, 07b, dan 07c tetap tidak diotorisasi; 07c memerlukan keputusan dependency PDF tersendiri.

## Cara melaporkan hasil

Cukup sampaikan nomor yang berhasil/gagal dan catatan singkat untuk kegagalan (langkah, pesan, atau screenshot). Bila memilih **SKIP** pada langkah opsional, sebutkan nomornya. Jangan mengubah data operasional atau menjalankan ulang fixture dengan tujuan reset.

## Batas bukti agen (NOT RUN)

- **Unduhan artefak PDF lewat otomasi browser** tidak dapat diverifikasi pada profil Chrome otomatis (response PDF sebagai artefak tidak dapat ditangkap); wiring tombol dan URL sudah diverifikasi, serta header HTTP `200`, `application/pdf`, `Content-Disposition: attachment`, `nosniff`, CSP sandbox, dan `no-store` diverifikasi via curl/API. Mohon konfirmasi manual pada langkah 7.
- Restore drill backup yang memuat lampiran surat; MariaDB/InnoDB dan trigger; race multi-proses/dua tab nyata; screenshot-based visual regression.
