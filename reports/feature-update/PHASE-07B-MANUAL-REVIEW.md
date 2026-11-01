# Panduan Review Manual — Subfase 07b (Disposisi, Surat Keluar, dan Nomor Resmi)

Subfase: 07b — disposisi surat masuk, surat keluar, nomor resmi, dan approval/sign.
Keputusan: **DEC-33 APPROVED** (D1–D11, 2026-11-01 WIB; pernyataan **"Saya setuju D1-D11"**); **DEC-06 CLOSED** melalui D4–D5. Laporan teknis: `reports/feature-update/PHASE-07B-REPORT.md`.
Baseline induk: `main` pada `d326369` (pencatatan DEC-33); paket implementasi 07b: `d736b37`. Status: **IMPLEMENTED — agent-verified**. Status review pemilik: **PENDING**.

## Status penerimaan pemilik

- Pemilik menyetujui rencana D1–D11 sebagai **DEC-33** dan mengotorisasi implementasi 07b; persetujuan rencana bukan penerimaan hasil.
- **Penerimaan hasil belum ada.** Seluruh checklist #1–#16 berstatus **PENDING** sampai pemilik menyatakan hasil review berhasil. Setelah itu, penerimaan dicatat pada commit dokumentasi tersendiri dan server UAT dihentikan sesuai DEC-27.
- Batas: penerimaan 07b tidak mengotorisasi merge, deploy, force-push, migrasi database operasional/MariaDB, perubahan dependency/service/`.env`, atau 07c. DEC-11 (PDF/kop/tanda tangan/stempel) dan DEC-13 (retensi) tetap OPEN.

## Lingkungan UAT (terisolasi)

- SPA: `http://127.0.0.1:14025`
- API: `http://127.0.0.1:18025`
- Data: `.uat-phase07` SQLite; cookie `school_phase07_session`; storage `.uat-phase07/storage`
- Launcher: `laravel-api/scripts/phase07-uat.php`
- Akun: **`uat.operator`** dan **`uat.admin`** (`letters.view`/`letters.manage`), **`uat.kepsek`** (`letters.view`/`letters.approve`/`letters.sign`), **`uat.waka`** (`letters.view`). Password hanya ada di runtime privat `.uat-phase07/account-password.txt`; tidak dicetak di dokumen.
- Server UAT **berjalan sampai penerimaan** (DEC-27): API PID 3132 (`php.exe -S 127.0.0.1:18025 -t public scripts/phase07-uat.php`) dan SPA PID 21876 (Vite `--host 127.0.0.1 --port 14025 --strictPort`). Command line diverifikasi 2026-11-02 WIB. Setelah penerimaan, keduanya dihentikan dengan verifikasi PID/command line; data `.uat-phase07` dipertahankan.
- Jangan menguji pada database operasional, `.uat-phase05`, `.uat-phase06`, atau MariaDB. Jangan me-reset `.uat-phase07`; fixture 07b idempoten dan mempertahankan baris 07a.

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

- **126 surat masuk** (115 active, 11 void) dan **46 surat keluar**: 16 draft, 8 review, 10 approved, 6 issued, 6 cancelled. 45 surat keluar adalah fixture; `#000172` dibuat saat UAT dan berakhir Cancelled/Void version 4.
- **Nomor resmi terbit:** `001/20512345/2026` (`#000134`), `002/20512345/2026` (`#000143`), `003/20512345/2026` (`#000152`), `004/20512345/2026` (`#000161`), `005/20512345/2026` (`#000170`), `006/20512345/2026` (`#000167`). Counter 2026 = **6**; nomor berikutnya saat issue adalah **007/20512345/2026**.
- **32 disposisi:** 11 pending, 7 in_progress, 7 completed, 7 cancelled; **2 overdue** (disposisi #6 dan #18, due 2026-10-27). Default halaman **Open** menampilkan **18** baris (11 pending + 7 in progress).
- Surat incoming dengan beberapa penerima: `#000025` memiliki **6 disposisi (5 terbuka)**; `#000026` memiliki **4 disposisi (3 terbuka)**.
- **14 pegawai** sintetis: **12 aktif** `UAT P07B Employee 01–12` + 2 non-aktif `UAT P07B Exited 01–02`. Dua pegawai non-aktif tidak muncul di opsi penerima.
- Lampiran outgoing contoh: `#000127`, `#000136`, `#000145`, `#000154`, `#000163` masing-masing satu file.
- Data 07a tetap utuh: 126 surat masuk; total 69 baris lampiran (1 soft-deleted)/69 file dan 389 event; 17 nomor sumber kosong dan 10 nomor sumber duplikat.

Daftar surat keluar untuk memudahkan review:

- **Review:** `#000130`, `#000131`, `#000139`, `#000140`, `#000148`, `#000149`, `#000157`, `#000158`.
- **Approved:** `#000132`, `#000133`, `#000141`, `#000142`, `#000150`, `#000151`, `#000159`, `#000160`, `#000168`, `#000169`.
- **Issued:** `#000134` (001), `#000143` (002), `#000152` (003), `#000161` (004), `#000170` (005), `#000167` (006).
- **Cancelled:** `#000135`, `#000144`, `#000153`, `#000162`, `#000171` (sebelum issue, tanpa nomor); `#000172` (setelah melalui review, tanpa nomor, version 4).
- **Draft:** `#000127`–`#000129`, `#000136`–`#000138`, `#000145`–`#000147`, `#000154`–`#000156`, `#000163`–`#000166`, dan `#000172` (sudah cancelled; bukan draft).

## Checklist

### 1. Isolasi, login, dan menu Letters
- Login sebagai `uat.operator`, buka grup menu **LETTERS**. Harapkan tiga item: **Incoming Letters**, **Outgoing Letters**, **Dispositions**.
- Buka **Outgoing Letters**: harapkan daftar default menampilkan **46** surat (semua workflow), paginasi default 20, urutan terbaru, kolom **Reference, Recorded, Letter Date, Recipient, Subject, Official No., Workflow, Files, Actions**.
- Harapkan label dan pesan English; tidak ada error console/network dari aplikasi. Jangan menguji pada DB operasional atau `.uat-phase05/06`.

### 2. Daftar, filter, dan paginasi surat keluar
- Pilih Workflow **Draft** → **16**; **In Review** → **8**; **Approved** → **10**; **Issued** → **6**; **Cancelled** → **6**; **All** → **46**.
- Cari nomor resmi `006/20512345/2026` → `#000167`; cari penerima `Dinas Pendidikan Sintetis 01` atau perihal `UAT-P07B Outgoing Letter 001` → hasil terbatas; kosongkan pencarian.
- Isi rentang Letter date yang valid (mis. 2026-01-01 s/d 2026-12-31) → daftar konsisten; rentang terbalik → validasi `422`/pesan dan daftar tidak berubah.
- Ubah **Rows** menjadi 50 → paginasi mengikuti; **Clear** mengembalikan filter awal.

### 3. Buat draft dan validasi
- Klik **New Letter**. Coba submit kosong: harapkan pesan wajib untuk Recipient, Subject, Category, Letter date, dan Recorded date.
- Isi Recorded date **besok**: harapkan penolakan "may not be in the future". Isi Letter date **setelah** Recorded date: harapkan penolakan. Pilih kategori di luar daftar lewat API → `422`.
- Buat draft sah dengan sender default sekolah (`Sekolah UAT Sintetis`), recipient, subject, kategori, tanggal valid, notes opsional. Harapkan toast sukses, referensi baru (mis. `#000173`), workflow **Draft**, status **Active**, **Version 1**, dan **Official No. kosong**.
- Buka detail draft: harapkan tidak ada tombol Approve/Reject/Issue; nomor belum terbentuk.

### 4. Edit draft, lampiran, dan submit
- Pada draft baru, klik **Edit**, ubah Subject/Notes, simpan. Harapkan **Version** naik dan history menampilkan **Updated**.
- Unggah `.uat-phase07/manual-files/valid.pdf`; harapkan sukses dan event **Attachment added**. Hapus lampiran → dialog konfirmasi, event **Attachment removed**, file tidak lagi dapat diunduh dari UI (baris soft-deleted; file fisik dipertahankan sampai DEC-13).
- Klik **Submit**. Harapkan workflow **In Review**, versi naik, event **Submitted for approval**, dan tombol **Edit**/upload/hapus lampiran hilang.
- Setelah submit, coba edit/upload/hapus lewat UI (tombol tidak ada) dan lewat API → `409` tanpa mutasi. Tombol **Approve**/**Reject** hanya muncul untuk akun dengan `letters.approve`.

### 5. Pemisahan ability dan self-approval
- Sebagai `uat.operator` (manage), buka surat berstatus Review: harapkan **tidak ada** tombol Approve/Reject/Issue; tombol Submit/Cancel sesuai status. Lewat API, approve/reject/issue → `403`.
- Sebagai `uat.kepsek` (approve/sign), buka Outgoing: harapkan **tidak ada** New Letter/Edit/Submit/Cancel draft; tombol Approve/Reject tersedia pada Review dan Issue pada Approved.
- Pembuat surat tidak boleh approve/reject/issue suratnya sendiri (`403`). Ini **agent-verified** lewat tes T8/T12; akun UAT tidak memiliki kombinasi manage+approve sekaligus, sehingga pemeriksaan manual skenario ini ditandai **SKIP/agent-verified** dan tidak perlu mengubah role UAT.

### 6. Approve, reject, dan approval ledger
- Sebagai `uat.kepsek`, buka **#000130** (Review v2), klik **Approve**, isi catatan opsional (≤500), konfirmasi. Harapkan workflow **Approved**, `approved_at/by` terisi, versi naik, event **Approved**, dan bagian **Approvals** menampilkan aktor, aksi, catatan, dan waktu.
- Buka **#000131** (Review v2), klik **Reject**, coba alasan < 10 karakter → validasi; isi alasan ≥10 → surat kembali **Draft**, versi naik, ledger mencatat **Rejected**; tombol Edit muncul kembali.
- Approve/reject surat yang sudah bukan Review → `409`; approve oleh akun tanpa ability → `403`.

### 7. Issue, nomor resmi, dan replay
- Sebagai `uat.kepsek`, buka **#000132** (Approved v3), klik **Issue**. Harapkan:
  - workflow **Issued**, nomor **`007/20512345/2026`** (counter sebelumnya 6), `issued_at/by` terisi, versi naik, event **Issued**.
  - Detail menampilkan Official number, Issued by, dan nomor tidak dapat diedit.
- Pastikan nomor tidak muncul pada draft/review/approved. Nomor yang sudah terbit tetap ada pada surat issued/cancelled dan tidak dipakai ulang.
- Replay/issue kedua: tombol Issue hilang setelah issued. Lewat API, ulangi issue dengan versi lama → **`409`** dengan `official_number` yang sama dan tidak ada nomor baru. Ini juga **agent-verified** (T13); dua proses paralel nyata **NOT RUN**.
- Format nomor mengikuti config `{seq:03d}/{school_code}/{year}`; kode sekolah UAT `20512345`. Jangan mengubah format lewat UI (tidak ada endpoint edit nomor).

### 8. Cancel, read-only, dan larangan un-cancel
- Sebagai `uat.operator`, buka draft **#000128**, klik **Cancel**, alasan <10 → validasi; alasan ≥10 → **Cancelled/Void**, versi naik, event **Cancelled**, tanpa nomor resmi. Surat menjadi read-only (tanpa Edit/Submit/upload).
- Sebagai `uat.kepsek`, buka approved **#000133** atau surat issued baru dari langkah 7, klik **Cancel** dengan alasan ≥10. Harapkan status **Cancelled**, nomor (bila ada) tetap tersimpan, counter tidak mundur, dan tidak ada tombol un-cancel.
- Sebagai `uat.operator`, coba cancel surat approved/issued → tombol tidak tersedia dan API `403`; cancel kedua pada surat cancelled → `409`.
- Pastikan surat cancelled tetap tampil pada filter **Cancelled** dan history/approval/lampiran tetap dapat dibaca.

### 9. Disposisi read-only, filter, dan overdue
- Login `uat.kepsek`, buka **Dispositions**. Harapkan default **Open** menampilkan **18** baris (11 Pending + 7 In Progress), dengan **2** baris bertanda **Overdue** (disposisi #6 dan #18, due 2026-10-27).
- Pilih **Completed** → **7**; **Cancelled** → **7**; **All** → **32**. Centang **Overdue only** → **2** baris. Filter penerima **tidak tampil** untuk kepsek/waka; kolom Letter/Recipient/Due Date/Status/Note/Outcome/Actions tetap terbaca.
- Sebagai kepsek/waka, panel detail incoming menampilkan disposisi tanpa tombol Edit/Start/Complete/Cancel; API tulis disposisi → `403`.
- Login kembali sebagai `uat.operator`; filter penerima muncul dan dapat mencari nama pegawai. Pilih penerima `UAT P07B Employee 01` → daftar terbatas pada disposisi penerima tersebut.

### 10. Transisi disposisi oleh operator
- Buka incoming **#000027** (active, belum punya disposisi), klik **Assign**, pilih dua penerima (mis. `UAT P07B Employee 02` dan `03`), isi due date ≥ hari ini, notes opsional. Harapkan dua baris disposisi `Pending`, version 1, dan event per penerima.
- Coba assign penerima yang sama lagi saat disposisi masih terbuka → `422`/`409` dengan pesan jelas; tidak ada baris ganda.
- Pada salah satu disposisi, klik **Start** → `In Progress`; lalu **Complete**, isi follow-up <10 → validasi, ≥10 → `Completed`, versi naik, event **Disposition completed**. `Cancel` memerlukan alasan 10–255 dan menghasilkan `Cancelled` (final, tanpa reopen).
- Konflik: ubah disposisi dari tab/API lain sehingga versi berubah, lalu simpan dari UI lama → **`409`** + **Reload Latest**; pastikan data tidak berubah sebelum reload.
- Catatan: transisi ini mengubah angka acuan Open/Completed/Cancelled pada langkah 9; itu wajar. Untuk kembali ke angka acuan, gunakan data UAT yang sudah ada, bukan reset.

### 11. Panel disposisi pada surat masuk
- Buka incoming **#000025**: harapkan panel **Dispositions (5 open, 0 overdue)** dengan **6** baris history; tabel incoming menampilkan jumlah open/overdue pada kolom **Dispositions**. (Dua disposisi overdue berada pada surat lain, disposisi #6 dan #18.)
- Buka **#000026**: harapkan **3 open** dari **4** disposisi.
- Sebagai operator, klik **Assign** pada `#000025`, pilih pegawai lain yang belum memiliki disposisi terbuka, due date valid → jumlah open bertambah; penerima duplikat ditolak.
- Sebagai kepsek/waka, panel hanya dapat dibaca; tombol Assign tidak tampil dan API `letters.manage` → `403`.

### 12. Void surat masuk yang masih memiliki disposisi terbuka
- Sebagai operator, buka **#000025** dan klik **Void** dengan alasan ≥10. Harapkan **`409 open_dispositions`** dengan pesan bahwa surat tidak dapat di-void selama disposisi terbuka; surat tetap Active dan tidak ada data berubah.
- Agent-verified (T23): setelah seluruh disposisi open di-complete/cancel, void berhasil dan disposisi completed/cancelled tetap menjadi history read-only. Uji manual lanjutan ini **opsional/SKIP** karena mengubah 5 baris fixture; jangan mengubah data dengan DB manual.
- Surat void tetap read-only dan disposisi tidak dapat ditambah.

### 13. Matriks izin dan route
- `uat.operator`/`uat.admin`: create/edit/submit/cancel draft, upload/hapus lampiran draft, assign/update/cancel disposisi; **tidak** approve/reject/issue/cancel approved-issued.
- `uat.kepsek`: baca semua, approve/reject, issue/sign, cancel approved/issued; **tidak** create/edit/submit/cancel draft atau mengubah disposisi.
- `uat.waka`: baca semua (incoming/outgoing/dispositions) dan download; semua mutasi `403`.
- Guest → `401`; akun nonaktif → `403`; objek lintas direction/letter (`disposition` bukan milik `letter`, lampiran surat lain) → `404`; pelanggaran ability → `403`. Server tetap menegakkan izin meskipun tombol disembunyikan.

### 14. Responsif 390 px
- Kecilkan viewport ke 390×844 pada Outgoing Letters, Dispositions, dan panel disposisi Incoming Letters.
- Harapkan tidak ada overflow halaman (`document.scrollWidth = 390`); tabel bergulir horizontal di dalam wrapper; dialog muat; tombol dan filter dapat digunakan.

### 15. Keyboard, aksesibilitas, English, dan console
- Harapkan seluruh label/tombol/pesan English dan format tanggal English.
- Uji Tab/Shift+Tab pada filter, dialog, dan aksi; ESC menutup dialog; tombol ikon memiliki `aria-label`; fokus terlihat.
- Harapkan **0 error console aplikasi**. Respons `409` yang disengaja pada uji konflik bukan error aplikasi. Catatan bug library PrimeVue `Select.alignOverlay` dari 07a tidak diperbaiki karena pembaruan dependency tidak diotorisasi; laporkan bila muncul.

### 16. Route, batas REST, dan batas penerimaan
- `php artisan route:list --path=letters` → **28 route** (9 incoming + 6 disposisi + 13 outgoing). Tidak ada `DELETE /api/letters/outgoing/{id}`, tidak ada endpoint un-cancel, tidak ada endpoint edit nomor, dan tidak ada approve/sign massal.
- Lampiran hanya dapat diunduh lewat endpoint berizin dengan header privat (attachment/nosniff/CSP sandbox/no-store); URL storage publik tidak tersedia.
- Penerimaan hanya mencakup checklist #1–#16 pada lingkungan `.uat-phase07`. Setelah pemilik menyatakan hasil berhasil, server 14025/18025 dihentikan sesuai DEC-27 dan penerimaan dicatat pada commit dokumentasi tersendiri.
- Merge/deploy/force-push, migrasi database operasional/MariaDB, perubahan dependency/service/`.env`, dan 07c tetap tidak diotorisasi; 07c memerlukan keputusan dependency PDF (DEC-11) dan retensi (DEC-13).

## Cara melaporkan hasil

Cukup sampaikan nomor yang berhasil/gagal dan catatan singkat untuk kegagalan (langkah, pesan, atau screenshot). Bila memilih **SKIP** pada langkah opsional (mis. #5 self-approval, #12 void setelah disposisi ditutup, replay dua proses), sebutkan nomornya. Jangan mengubah data operasional atau menjalankan ulang fixture dengan tujuan reset.

## Batas bukti agen (NOT RUN)

- Konkurensi counter pada MariaDB/InnoDB dan race multi-proses nyata; T13 hanya replay/versi basi pada SQLite.
- Dua tab browser nyata untuk `409`; konflik disimulasikan lewat API + UI stale.
- PDF/print/template (07c); restore drill dengan tabel/nomor 07b; screenshot-based visual regression; notifikasi email/WhatsApp; benchmark volume besar.
- Fixture surat void dengan disposisi completed tidak tersedia di `.uat-phase07`; perilaku void setelah disposisi tertutup dan read-only dicakup tes otomatis T23, bukan skenario browser.
- Self-approval/self-issue manual tidak dapat diuji dengan akun UAT karena tidak ada akun yang memegang `letters.manage` dan `letters.approve`/`letters.sign` sekaligus; perilaku server dicakup tes otomatis T8/T12.
