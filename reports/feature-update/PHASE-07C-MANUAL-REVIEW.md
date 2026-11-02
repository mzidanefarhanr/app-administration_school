# Panduan Review Manual — Subfase 07c (Kop, Tanda Tangan, Stempel, Template PDF, dan Snapshot)

Subfase: 07c — letterhead/kop, logo, penandatangan, tanda tangan, stempel, tiga jenis surat, template PDF berversi, PDF tersimpan, dan snapshot immutable.
Keputusan: **DEC-34 APPROVED** (D1–D11, 2026-11-02 WIB; pernyataan **"Saya setuju D1-D11"**); **DEC-11 CLOSED untuk lingkup 07c**; DEC-13 tetap OPEN. Laporan teknis: `reports/feature-update/PHASE-07C-REPORT.md`.
Baseline induk: `main` pada `4713c70` (pencatatan DEC-34); paket implementasi 07c: `2a78b7e`. Status: **IMPLEMENTED — agent-verified**. Status review pemilik: **PENDING**.

## Status penerimaan pemilik

- Pemilik menyetujui rencana D1–D11 sebagai **DEC-34** dan mengotorisasi implementasi 07c; persetujuan rencana bukan penerimaan hasil.
- **Penerimaan hasil: PENDING.** Checklist ini berisi 18 skenario. Agen sudah menjalankan verifikasi otomatis T1–T28 dan sebagian pemeriksaan browser; skenario manual di bawah belum dinyatakan diterima sampai pemilik menyatakan hasilnya.
- Batas: penerimaan 07c tidak mengotorisasi merge, deploy, force-push/rewrite, migrasi database operasional/MariaDB, perubahan dependency selain D2, perubahan service/`.env`, retensi DEC-13, rekap DEC-12, surat izin Fase 08, atau Fase 08. UAT hanya pada `.uat-phase07`; jangan menyentuh `.uat-phase05`/`.uat-phase06`, database operasional, atau MariaDB.

## Lingkungan UAT (terisolasi)

- SPA: `http://127.0.0.1:14025`
- API: `http://127.0.0.1:18025`
- Data: `.uat-phase07` SQLite; cookie `school_phase07_session`; storage `.uat-phase07/storage`
- Launcher: `laravel-api/scripts/phase07-uat.php`
- Akun: **`uat.operator`** dan **`uat.admin`** (`letters.view`/`letters.manage`), **`uat.kepsek`** (`letters.view`/`letters.approve`/`letters.sign`), **`uat.waka`** (`letters.view`). Password hanya ada di runtime privat `.uat-phase07/account-password.txt`; tidak dicetak di dokumen.
- Server UAT saat ini **berjalan** (2026-11-02): API PID 8340 (`php.exe -S 127.0.0.1:18025 -t public scripts/phase07-uat.php`) dan SPA PID 22456 (Vite `--host 127.0.0.1 --port 14025 --strictPort`). Sesuai DEC-27 server **dihentikan setelah pemilik menyatakan penerimaan**; data `.uat-phase07` dipertahankan; Apache/MariaDB tidak disentuh.
- Jangan menguji pada database operasional, `.uat-phase05`, `.uat-phase06`, atau MariaDB. Jangan me-reset `.uat-phase07`; fixture 07c idempoten dan mempertahankan baris 07a/07b.

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

- **126 surat masuk** (115 active, 11 void), **66 surat keluar** (22 draft, 11 review, 14 approved, 11 issued, 8 cancelled), dan **32 disposisi** 07b tetap utuh.
- **20 surat bertipe 07c**: `student_active` 12, `student_mutation` 4, `employee_duty` 4; 6 draft, 3 review, 4 approved, 5 issued, 2 cancelled.
- **7 dokumen PDF** (12 halaman) untuk 5 issued + 2 cancelled-after-issue; nomor resmi `007/20512345/2026`–`013/20512345/2026`; counter 2026 = **13**; nomor berikutnya saat issue adalah **014/20512345/2026** (urutan bergantung surat yang di-issue lebih dulu).
- **Letter settings v2 siap** untuk profil #1 NPSN `20512345`, tahun aktif **2026/2027**: sekolah **SMA NEGERI 1 UAT SINTETIS**, alamat `Jl. Sintetis No. 1, Jakarta Utara UAT`, penandatangan **UAT Kepala Sekolah / Kepala Sekolah**, aset sintetis berlabel **UAT SYNTHETIC** (logo, signature, stamp).
- **120 siswa sintetis `UAT-P07C`**: 100 aktif di rombel `UAT P07C` + 20 mutasi keluar aktif; total 123 siswa di UAT.
- Lampiran/event 07a–07b: 68 baris lampiran (1 soft-deleted)/69 file, 443 event; 17 nomor sumber kosong dan 10 nomor sumber duplikat.

Daftar surat bertipe untuk memudahkan review:

- **Draft:** `#000173`–`#000176` (student active), `#000185` (student mutation), `#000189` (employee duty).
- **Review:** `#000177`, `#000178` (student active), `#000190` (employee duty).
- **Approved:** `#000179`, `#000180` (student active), `#000186` (student mutation), `#000191` (employee duty).
- **Issued:** `#000181` = `007/20512345/2026`, `#000182` = `008`, `#000183` = `009` (student active); `#000187` = `011` (student mutation); `#000192` = `013` (employee duty, **2 halaman**).
- **Cancelled-after-issue:** `#000184` = `010` (student active), `#000188` = `012` (student mutation); PDF tetap dapat diunduh.
- Surat umum/legacy 07b tidak memiliki PDF (contoh issued 07b: `#000170` = `005/20512345/2026`); tidak ada backfill.

## Checklist

### 1. Isolasi, login, dan menu Letters
- Login sebagai `uat.operator`, buka grup menu **LETTERS**. Harapkan empat item: **Incoming Letters**, **Outgoing Letters**, **Dispositions**, **Letter Settings**.
- Buka **Letter Settings**: harapkan halaman English dengan kartu **Active Period**, status **Ready**, tahun **2026/2027**, profil NPSN `20512345`, dan checklist readiness tanpa item missing.
- Pastikan hanya API `127.0.0.1:18025`/SPA `127.0.0.1:14025`/SQLite `.uat-phase07` yang dipakai; jangan menguji pada DB operasional atau `.uat-phase05/06`.

### 2. Login, izin, dan pemisahan ability
- Sebagai `uat.operator`/`uat.admin`: dapat mengisi **Letterhead** dan logo; tombol **Save Signatory**/signature/stamp tidak tersedia; membuka aset mentah signature/stamp lewat API → `403`.
- Sebagai `uat.kepsek`: dapat mengisi **Signatory**, signature, dan stamp; form **Letterhead** read-only; mencoba menyimpan header lewat API → `403`.
- Sebagai `uat.waka`: Letter Settings hanya dapat dibaca; semua tombol tulis tidak tampil dan API mutasi → `403`.
- Guest → `401`; akun nonaktif → `403`. Server tetap menegakkan izin meskipun tombol disembunyikan.

### 3. Readiness dan penolakan `422`
- Pada kondisi saat ini settings **Ready**; issue surat bertipe yang approved berhasil membuat PDF.
- Skenario settings tidak lengkap (mis. signature dihapus) bersifat **opsional/SKIP** karena mengubah fixture; perilaku submit/issue `422` tanpa nomor/dokumen dicakup tes otomatis T13/T17. Jangan menghapus aset/settings hanya untuk menutup skenario ini.
- General letter 07b (tanpa tipe) tetap dapat submit/issue seperti sebelumnya dan tidak memerlukan settings.

### 4. Simpan header dan logo
- Sebagai operator, ubah **School name** atau **Address line** (teks sintetis), simpan. Harapkan toast sukses, **Settings version** naik, nilai tersimpan setelah reload, dan audit mencatat perubahan tanpa path/hash.
- Unggah logo PNG valid (≤2 MB, ≤2000×2000); harapkan preview logo tampil, file privat tersimpan, dan versi naik. Coba unggah SVG atau file >2 MB → `422` dan settings lama tidak berubah.
- Sebagai kepsek/waka, tombol simpan header/logo tidak tersedia; API `403`.
- Catatan: setelah header diubah, PDF yang sudah terbit **tidak berubah** (snapshot); preview surat baru memakai header baru.

### 5. Simpan signatory, signature, dan stamp
- Sebagai kepsek, ubah **Signatory name/title** atau identifier, unggah signature/stamp PNG valid, simpan. Harapkan versi naik, preview aset tampil, dan hanya kepsek yang dapat membuka aset mentah signature/stamp.
- Sebagai operator/waka, form read-only; API `403`; logo tetap dapat dilihat sesuai `letters.view`.
- Signature wajib untuk readiness; menghapus signature membuat readiness **not ready** (SKIP bila tidak ingin mengubah fixture).

### 6. Buat surat bertipe (tiga jenis) dan subject picker
- Sebagai operator, klik **New Letter**. Pilih **Letter type**:
  - **Student Active Certificate** → **Student** picker (cari siswa `UAT-P07C Student 0xx`) + **Purpose** (5–500 karakter).
  - **Student Mutation Certificate** → **Mutation record** picker (siswa, tanggal, sekolah tujuan; tanpa alasan panjang).
  - **Employee Duty Letter** → **Employee** picker (`UAT P07B Employee 0x`) + **Duty description** (10–2000), **Start date**, **End date**, **Location** (5–200).
- Submit kosong/field kurang → validasi per field; subjek tidak aktif/tidak eligible → `422`; tipe tidak dikenal → `422`.
- Setelah create, tipe dan subjek **read-only**; mencoba mengubahnya lewat API → `422`/`409` tanpa mutasi. Field typed hanya dapat diedit saat **draft**.

### 7. Edit draft, peringatan settings, dan submit
- Pada draft baru, edit field typed/recipient/subject (bukan tipe/subjek) → versi naik. Unggah lampiran draft sesuai aturan 07a.
- Saat settings **Ready**, submit berhasil → **In Review**; reject kepsek mengembalikan ke **Draft** dan edit terbuka.
- Penolakan submit karena settings/subjek tidak valid bersifat **opsional/SKIP** (agent-verified T13); jangan merusak settings fixture.
- Surat umum 07b tetap dapat dibuat/diedit/submit tanpa field typed/PDF.

### 8. Preview PDF (draft/review/approved)
- Buka draft `#000173`, klik **Preview**. Harapkan dialog PDF menampilkan kop, isi surat, dan watermark **PREVIEW — NOT ISSUED**; **tanpa nomor resmi**, tanpa tanda tangan/stempel final, dan tidak menambah `letter_documents`, file PDF, atau counter.
- Ulangi untuk review `#000177` dan approved `#000179`; keduanya tetap preview watermarked.
- Preview untuk issued `#000181`, surat umum/legacy 07b, atau general letter → tombol tidak ada/`404`; settings/subjek invalid → `422`. Tidak ada regenerasi untuk surat issued.

### 9. Submit dan approve surat bertipe
- Sebagai operator, submit draft baru/`#000174` → **In Review**; sebagai kepsek approve dengan catatan → **Approved**, ledger mencatat aktor/catatan, dan versi naik.
- Sebagai kepsek reject surat review dengan alasan 10–255 → kembali **Draft**; tombol Edit muncul.
- Tombol Approve/Reject hanya untuk `letters.approve`; operator → `403`. Pembuat tidak dapat approve/reject/issue suratnya sendiri (agent-verified T8/T12; tidak ada akun UAT dengan kombinasi manage+approve).

### 10. Issue, nomor resmi, PDF, dan multi-halaman
- Sebagai kepsek, issue approved `#000179`. Harapkan status **Issued**, nomor berikutnya **`014/20512345/2026`** (bila belum ada issue lain), `issued_at/by` terisi, versi naik, dan event **Issued** dengan metadata template/page count tanpa PII.
- Buka **Document** card: template `student_active` v1, jumlah halaman, tanggal generate, ukuran; tombol **Download PDF** tersedia. Nomor tidak dapat diedit dan tidak muncul pada draft/review/approved.
- Unduh `#000192` (`013/20512345/2026`, employee duty): PDF **2 halaman**, halaman 1 kop + isi **SURAT TUGAS**, halaman 2 tanda tangan/stempel, **tanpa watermark**; footer nomor/halaman.
- Replay issue/issue kedua → `409` dengan nomor yang sama; tidak ada nomor/dokumen kedua.

### 11. Snapshot immutable
- Setelah issue `#000179` (atau `#000181`), unduh PDF dan catat tampilan/isi.
- Ubah sementara header/signatory settings atau nama siswa (opsional), lalu unduh ulang PDF lama: isi, kop, tanda tangan, dan nomor **tidak berubah**.
- Surat baru yang di-preview/di-issue setelah perubahan memakai settings/subjek terbaru. Mengubah master/settings tidak mengubah dokumen lama.
- Jika tidak ingin mengubah master, cukup bandingkan dua unduhan PDF lama sebelum/sesudah perubahan settings; perilaku ini juga dicakup tes T19.

### 12. Download, header aman, legacy, dan cancelled
- Unduh issued `#000181`: respons `application/pdf`, nama file `007-20512345-2026.pdf` (tersanitasi), tanpa path/hash, dan byte sama dengan yang tersimpan.
- Unduh cancelled-after-issue `#000184` (`010`) dan `#000188` (`012`): tetap tersedia; nomor dipertahankan; tidak ada un-cancel/regenerasi.
- Surat umum/legacy 07b issued `#000170` (005) dan general letter: **tidak ada PDF**, UI menampilkan pesan legacy, endpoint `404`; tidak ada backfill.
- Draft/review/approved: endpoint download `404`; hanya preview. File hilang/rusak → `404` tanpa regenerasi.
- Catatan teknis: respons PDF tidak memakai `Content-Disposition` (dibatasi browser untuk fetch lintas origin dengan kredensial); nama file dikirim lewat header `X-Document-Filename` dan dipakai UI saat menyimpan. Ini disengaja dan diuji; jangan menganggap header hilang sebagai defect.

### 13. Subjek menjadi tidak eligible setelah approve
- Skenario ini mengubah data: batalkan salah satu mutasi keluar `UAT-P07C` atau nonaktifkan salah satu siswa `UAT-P07C`, lalu issue `#000186` (student mutation approved) atau surat siswa approved.
- Harapkan issue **`422`**, surat tetap **Approved**, **tanpa nomor baru** dan **tanpa dokumen/file**; counter tidak mundur.
- Skenario ini **opsional/SKIP** bila tidak ingin mengubah fixture; perilaku server dicakup tes T20.

### 14. Daftar, filter tipe, dan indikator dokumen
- Buka **Outgoing Letters**: filter **Type** (General, Student Active, Student Mutation, Employee Duty) dan **Has document** (`Yes`/`No`).
- Harapkan 20 surat bertipe tampil dengan label tipe dan indikator dokumen; filter General menampilkan surat 07b; filter tipe menghitung subset dengan benar.
- Detail surat menampilkan ringkasan dokumen (template, versi, halaman, tanggal, ukuran) tanpa `pdf_path`, hash, snapshot, atau field typed mentah; detail general/legacy menampilkan pesan tanpa PDF.

### 15. Audit, history, dan privasi
- Periksa event surat issued dan activities: tidak memuat purpose, duty description, alasan mutasi, NISN/NIK, nama/identifier penandatangan, atau path file; hanya metadata minimal (template, versi, page count, nomor).
- `letter_documents` dan snapshot tidak dapat diedit/dihapus lewat UI/API; tidak ada endpoint untuk membaca snapshot/path/hash.
- Settings allowlist tidak memuat alamat/kontak/identifier lengkap; aset mentah hanya `letters.sign`. (Sebagian pemeriksaan ini agent-verified T26 dan dapat dilihat pada halaman audit yang ada.)

### 16. Responsif 390 px, English, keyboard, dan console
- Kecilkan viewport ke 390×844 pada **Letter Settings** dan **Outgoing Letters**. Harapkan tidak ada overflow halaman (`document.scrollWidth = 390`); tabel bergulir di wrapper; dialog/form muat.
- Seluruh label/tombol/pesan English; Tab/Shift+Tab, ESC menutup dialog, tombol ikon memiliki `aria-label`, fokus terlihat.
- Harapkan **0 error console aplikasi**. Respons `409`/`422` yang disengaja bukan error aplikasi. Bug library PrimeVue `Select.alignOverlay` dari 07a tidak diperbaiki karena dependency update tidak diotorisasi; laporkan bila muncul.

### 17. Isolasi data 07a/07b
- Pastikan 126 surat masuk, 66 surat keluar (termasuk 28 surat 07b), 32 disposisi, dan 14 pegawai 07b tetap ada; 20 surat bertipe adalah data additive `UAT-P07C`.
- Lampiran/event 07a–07b tetap dapat dibaca; tidak ada reset, penghapusan massal, atau perubahan data operasional.
- Perubahan status akibat review manual (mis. issue `#000179`, approve/reject surat baru) adalah data UAT yang wajar; jangan me-reset fixture untuk mengembalikan angka.

### 18. Penutupan setelah penerimaan
- Setelah pemilik menyatakan penerimaan, hentikan **hanya** server UAT 14025/18025 dengan verifikasi PID/command line, lalu pastikan port bebas; pertahankan `.uat-phase07` (database/storage/password runtime).
- Jangan menyentuh Apache/MariaDB, jangan merge/deploy/force-push, jangan migrasi database operasional/MariaDB, jangan mengubah `.env`/service, dan jangan memulai Fase 08.
- Penerimaan dicatat pada commit dokumentasi tersendiri; commit/push paket review bukan penerimaan manual.

## Cara melaporkan hasil

Cukup sampaikan nomor yang berhasil/gagal dan catatan singkat untuk kegagalan (langkah, pesan, atau screenshot). Bila memilih **SKIP** pada langkah opsional (#3 settings invalid, #5 menghapus signature, #7 submit tanpa settings, #11 mengubah master, #13 subjek tidak eligible), sebutkan nomornya. Jangan mengubah data operasional atau me-reset `.uat-phase07`.

## Batas bukti agen (NOT RUN)

- Konkurensi issue/nomor/dokumen pada **MariaDB/InnoDB** dan race multi-proses nyata; seluruh tes runtime memakai SQLite in-memory/`.uat-phase07` SQLite.
- Dua tab browser nyata untuk `409`; konflik disimulasikan lewat API + UI stale.
- Restore drill yang memuat aset settings dan PDF 07c; manifest backup belum diuji ulang.
- Screenshot-based visual regression berbasis file gambar; benchmark volume besar.
- E-sign tersertifikasi/QR/OCR/email/WhatsApp (non-goal D11).
- Backfill PDF untuk surat 07b/legacy (non-goal D1/D8).
- Pemeriksaan browser agen mencakup Letter Settings (readiness/permission split), daftar/detail bertipe, preview watermarked, unduhan PDF issued 2 halaman, 390 px, dan 0 console error. Skenario manual lain pada checklist ini disediakan untuk pemilik; jangan menganggapnya sudah dijalankan agen.
- **Temuan di luar scope:** unduhan lampiran 07a/07b juga gagal pada `fetch` lintas origin di Chrome karena `Content-Disposition` (diuji pada lampiran incoming #63). Perilaku 07c sudah memakai header alternatif; lampiran 07a/07b tidak diubah agar scope tidak melebar dan dicatat sebagai risiko terpisah.
