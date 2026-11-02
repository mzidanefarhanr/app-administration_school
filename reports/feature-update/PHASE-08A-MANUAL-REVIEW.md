# Panduan Review Manual — Subfase 08a (Pengajuan Izin Siswa dan Pegawai)

Subfase: 08a — pengajuan izin siswa dan pegawai, validasi kelayakan/tanggal/overlap, lampiran privat, pembatalan, audit, dan UI Leaves.
Keputusan: **DEC-35 APPROVED** (D1–D12, 2026-11-02 WIB; **"Saya setuju D1-D12"**); **DEC-07 CLOSED**; **DEC-13 OPEN**. Implementasi 08a diotorisasi; 08b menunggu instruksi terpisah. Laporan teknis: `reports/feature-update/PHASE-08A-REPORT.md`.
Baseline induk: `main` pada `7a45813` (pencatatan DEC-35); paket review `91b3eeb`. Status: **IMPLEMENTED — agent-verified**. Status review pemilik: **APPROVED (#1–#16, 2026-11-02 WIB)**.

## Status penerimaan pemilik

- Persetujuan rencana D1–D12 (**DEC-35**) mengotorisasi implementasi; persetujuan rencana bukan penerimaan hasil.
- **Penerimaan 2026-11-02 WIB:** pemilik menyatakan **"Pengujian nomor 1-16 sudah berhasil dan saya approved."** Pernyataan ini dicatat sebagai penerimaan checklist 08a #1–#16; agen tidak mengulang skenario dan item NOT RUN tetap NOT RUN.
- Setelah penerimaan, server UAT dihentikan (DEC-27; PID/command line diverifikasi, port bebas) dan data `.uat-phase08` dipertahankan.
- Batas: penerimaan 08a tidak mengotorisasi 08b, merge, deploy, force-push/rewrite, migrasi database operasional/MariaDB, perubahan dependency/lockfile, perubahan service/`.env`, atau retensi DEC-13. UAT hanya pada `.uat-phase08`; jangan menyentuh database operasional, `.uat-phase05`, `.uat-phase06`, `.uat-phase07`, Apache, atau service MariaDB.

## Lingkungan UAT (terisolasi)

- SPA: `http://127.0.0.1:14026`
- API: `http://127.0.0.1:18026`
- Data: `.uat-phase08` SQLite; cookie `school_phase08_session`; storage `.uat-phase08/storage`
- Launcher: `laravel-api/scripts/phase08-uat.php`
- Akun: **`uat.operator`** (Operator Sekolah), **`uat.admin`** (Admin Sistem), **`uat.kepsek`** (Kepala Sekolah, view-only di 08a), **`uat.waka`** (Wakil Kesiswaan, view-only), **`uat.guru`** (peran Guru, tertaut ke `UAT-P08 Employee 01`), **`uat.inactive`** (nonaktif). Password hanya ada di runtime privat `.uat-phase08/account-password.txt`; tidak dicetak di dokumen.
- Server UAT dihentikan 2026-11-02 setelah penerimaan pemilik: API PID 11864 dan SPA PID 19376 diverifikasi command line sebelum dihentikan, port 14026/18026 dipastikan bebas, `.uat-phase08` dipertahankan; Apache/MariaDB tidak disentuh.
- Jangan me-reset `.uat-phase08`; fixture `prepare-leaves` idempoten dan mempertahankan baris yang sudah ada.

Perintah restart bila server sudah berhenti:

```powershell
# API (dari laravel-api)
C:\xampp\php82-operational\php.exe -S 127.0.0.1:18026 -t public scripts/phase08-uat.php

# SPA (dari vue-app)
$env:VITE_API_BASE_URL = "http://127.0.0.1:18026"
npm run dev -- --host 127.0.0.1 --port 14026 --strictPort
```

Perintah status (read-only):

```powershell
# dari laravel-api — hanya membaca .uat-phase08
C:\xampp\php82-operational\php.exe scripts/phase08-uat.php status
```

Angka acuan saat ini (hasil pengujian agen, data tidak di-reset):

- **122 izin** — 34 draft, 73 submitted, 15 cancelled, 0 approved, 0 rejected; 101 subjek siswa, 21 subjek pegawai.
- **49 lampiran** / 49 file privat; **15 keputusan** (`cancelled`; approve/reject belum tersedia di 08a).
- **123 siswa** dengan 123 keanggotaan aktif tahun ajaran **2026/2027** (2026-07-01 s.d. 2027-06-30); **20 pegawai** (20 aktif; 1 tertaut ke `uat.guru`).
- **120 fixture sintetis** (100 siswa + 20 pegawai) + 2 izin hasil pengujian browser (`#121`, `#122`).
- Contoh data untuk review:
  - Draft siswa: `#4` (sick, 2 hari), `#11`–`#13`.
  - Draft pegawai (milik guru): `#101` (annual, 2 hari).
  - Submitted siswa: `#5` (permission, 3 hari).
  - Submitted pegawai: `#102` (sick, 3 hari); self-service guru: `#122` (annual, versi 3).
  - Cancelled setelah submit: `#10` (versi 3, alasan batal 10–255), `#105` (pegawai).
  - Cancelled dari draft: `#20` (versi 2, tanpa `submitted_at`).
  - Lampiran: `#1` 2 file, `#2` 3 file, `#121` 1 file.
- Lampiran/event modul surat serta data 05/06/07 tidak disentuh oleh fixture 08a. `.uat-phase08` adalah runtime baru; fixture hanya menulis tabel izin, data sintetis siswa/pegawai, dan ability/peran `guru`.

## Checklist

### 1. Isolasi, login, dan menu Leaves
- Login sebagai `uat.operator`, buka grup menu **LEAVES**. Harapkan satu item **Leaves** menuju `/public/leaves`.
- Halaman menampilkan tab **Requests** dan **My Leaves** (guru hanya melihat **My Leaves**), judul English, dan tabel 122 izin.
- Pastikan hanya API `127.0.0.1:18026`, SPA `127.0.0.1:14026`, dan SQLite `.uat-phase08` yang dipakai; jangan menguji pada DB operasional atau `.uat-phase05/06/07`.

### 2. Matriks izin dan tampilan tombol
- `uat.operator`/`uat.admin`: melihat **Requests** + **My Leaves**, tombol **New Request** (siswa/pegawai atas nama) tersedia.
- `uat.guru`: hanya **My Leaves**; tombol **New Self Request** tersedia; tidak ada tombol membuat izin siswa atau izin pegawai lain.
- `uat.waka`/`uat.kepsek`: melihat 122 baris dan detail read-only; tidak ada tombol create/edit/submit/cancel/upload/delete.
- `uat.inactive`: login/API ditolak; guest → `401`.
- Coba panggil mutasi langsung lewat API tanpa ability (mis. guru `POST /api/leaves` untuk siswa) → `403`; tombol tersembunyi bukan pengganti enforcement server.

### 3. Buat izin siswa (operator)
- Sebagai `uat.operator`, klik **New Request**, pilih **Subject type = Student**, cari siswa `UAT-P08 Student 001`–`120` melalui picker, pilih jenis **Sick/Permission/Other**, isi tanggal dan alasan 5–1000 karakter, simpan.
- Harapkan draft dibuat dengan snapshot tahun ajaran dan kelas, versi 1, status **Draft**, dan pembuat `uat.operator`.
- Coba submit tanpa siswa/tanggal/alasan atau dengan alasan <5 karakter → validasi per field, tidak ada baris baru.
- Pilih jenis milik pegawai (mis. **Annual**) untuk subjek siswa → `422`; pilih siswa tanpa keanggotaan aktif/tahun aktif tidak tersedia → `422`.

### 4. Self-service guru dan penautan akun
- Sebagai `uat.guru`, klik **New Self Request**; picker subjek tidak ditampilkan karena subjek ditentukan dari `employees.user_id`.
- Buat draft untuk tanggal valid, edit draft (versi naik), lalu submit. Harapkan status **Submitted** dan `submitted_by = uat.guru`.
- Pastikan guru tidak dapat membuat izin untuk pegawai lain/siswa lewat UI maupun API (`403`).
- Akun tanpa profil pegawai tertaut (bila ada) mendapat `403` dengan pesan agar admin menautkan akun; fallback NIK tidak dipakai.

### 5. Validasi tanggal, jenis, dan tahun ajaran
- Rentang dengan `start_date > end_date` → `422`.
- Rentang melebihi batas jenis (30 hari; maternity/paternity 90) atau batas global 90 hari → `422`.
- Izin siswa di luar rentang tahun ajaran aktif (2026-07-01 s.d. 2027-06-30) → `422`; pegawai tidak terikat tahun ajaran.
- Jenis tidak dikenal → `422`; jenis yang bukan milik `subject_type` → `422`.
- Alasan 1001 karakter → `422`; batas bawah/atas lain sesuai pesan server.

### 6. Edit draft dan optimistic locking
- Edit draft milik sendiri: field yang diizinkan berubah, versi naik, status tetap **Draft**.
- Buka dua tab/dua request dengan versi lama, simpan dari tab basi → `409` dan UI menampilkan konflik + opsi memuat data terbaru; data tidak berubah.
- Setelah submit, edit lewat UI/API → `409`/`422`; tidak ada edit setelah submit.

### 7. Submit dan larangan tumpang tindih
- Submit draft valid → **Submitted**, `submitted_at/by` terisi, versi naik.
- Buat draft kedua untuk siswa yang sama dengan tanggal beririsan dan submit → `422` pada `errors.overlap` berisi ID/rentang konflik; draft tetap draft dan tidak mengubah data.
- Tanggal berdampingan (mis. izin submitted 06–07, draft baru 08) → submit berhasil.
- Draft lain yang beririsan tidak memblokir sampai salah satunya disubmit; setelah submit, submit kedua → `422`.
- Submit ulang/replay versi basi → `409`.

### 8. Lampiran privat
- Pada draft/submitted, unggah PDF/JPG/JPEG/PNG ≤5 MB; harapkan nama UUID tersimpan, nama asli tampil, ukuran/ MIME benar, dan file hanya dapat diunduh lewat endpoint berizin.
- Unggah file >5 MB, ekstensi di luar allowlist, atau isi yang tidak cocok dengan ekstensi → `422`; file tidak tersimpan.
- Tambahkan lebih dari 3 lampiran → `422`; hapus lampiran → baris soft-deleted dan hilang dari detail, file fisik tetap dipertahankan.
- Unduh sebagai operator/guru pemilik/kepsek (view) → berhasil dengan header aman (`X-Document-Filename`, `nosniff`, `Cache-Control: private, no-store`, tanpa `Content-Disposition`).
- Setelah izin cancelled, unggah/hapus → `409` (read-only); unduhan tetap berizin.

### 9. Pembatalan dan riwayat keputusan
- Batalkan draft: alasan opsional; bila diisi 10–255; status **Cancelled**, versi naik.
- Batalkan izin **Submitted**: alasan wajib 10–255; status **Cancelled**, `cancelled_at/by` terisi, dan riwayat `leave_decisions` mencatat `action=cancelled`, `from_status=submitted`, `to_status=cancelled`, aktor, alasan.
- Alasan batal <10 atau >255 → `422`; pembatalan oleh non-pembuat tanpa `leaves.manage` → `403`.
- Izin cancelled tidak dapat diedit/disubmit/di-cancel ulang; tidak ada un-cancel.

### 10. Peran view-only dan batas server
- `uat.kepsek`/`uat.waka` membuka detail izin: hanya tombol **Close**; tidak ada approve/reject (08a), tidak ada edit/submit/cancel/upload.
- Panggil endpoint mutasi sebagai kepsek/waka → `403`; data tidak berubah.
- Detail izin milik orang lain yang tidak terlihat (mis. guru membuka izin siswa lain) → `404`, bukan bocor keberadaan/data.
- `leaves.approve` belum ditegakkan di 08a; tidak ada UI/endpoint approve/reject/rekap. Jangan menganggap ketiadaan tombol sebagai defect.

### 11. Daftar, filter, pencarian, dan pagination
- **Requests** menampilkan 122 baris dengan kolom subjek, jenis, tanggal, jumlah hari, status, versi, dan jumlah lampiran.
- Filter **Status** (Draft/Submitted/Cancelled), **Subject type** (Student/Employee), **Type**, rentang tanggal, dan pencarian nama/NIS/NISN/NUPTK bekerja di server; total berubah sesuai filter.
- Pagination 10/20/50/100 (default 20); `per_page` di luar daftar → `422`; halaman kosong memiliki empty state.
- **My Leaves** sebagai guru hanya menampilkan izin dengan subjek pegawai tertaut ke akunnya (`#101`, `#122`); sebagai operator menampilkan izin yang ia buat.

### 12. Detail, audit, dan privasi
- Detail menampilkan alasan, subjek (nama/NIS/NISN/kelas untuk siswa; nama/NUPTK/tipe untuk pegawai), riwayat keputusan, dan lampiran tanpa path/hash internal.
- Periksa activities/audit: perubahan status/versi/tanggal/aktor tercatat; **alasan izin, alasan batal, nama file, path, checksum, NIK/alamat/kontak tidak tercatat**.
- `leave_decisions` dan `leave_requests` tidak memiliki endpoint update/delete; tidak ada hard delete pengajuan.
- Pastikan tidak ada efek samping ke absensi, saldo cuti, atau gaji (modul tersebut tidak ada).

### 13. Subjek menjadi tidak eligible (opsional/SKIP)
- Skenario ini mengubah fixture: arsipkan/nonaktifkan salah satu siswa `UAT-P08`, atau isi `exit_date` pegawai sebelum tanggal mulai izin submitted, lalu submit/unduh.
- Harapkan submit `422` tanpa mutasi; izin tetap draft/submitted sesuai kondisi awal.
- **SKIP** bila tidak ingin mengubah fixture; perilaku ini dicakup tes otomatis E1 (kelayakan subjek dan revalidasi saat submit).

### 14. Responsif, English, keyboard, dan console
- Kecilkan viewport ke 390×844 pada halaman Leaves dan dialog create/edit. Chrome DevTools dapat membatasi lebar menjadi 500 px; pastikan `document.scrollWidth` tidak melebihi lebar viewport, tabel bergulir di wrapper, dan dialog/form muat.
- Seluruh label/tombol/pesan English; Tab/Shift+Tab, ESC menutup dialog, tombol ikon memiliki `aria-label`, fokus terlihat.
- Harapkan **0 error console aplikasi**. Respons `409`/`422` yang disengaja bukan error aplikasi.

### 15. Isolasi data dan siklus server UAT
- Pastikan data modul lain (surat 07a/07b/07c, siswa/pegawai 05/06) tidak berubah karena fixture 08a; hanya tabel izin dan ability/peran `guru` yang additive.
- Jangan me-reset `.uat-phase08`; perubahan status akibat review manual adalah data UAT yang wajar.
- Setelah penerimaan: hentikan server UAT 14026/18026 hanya setelah verifikasi PID/command line, verifikasi port bebas, pertahankan `.uat-phase08`; jangan menyentuh Apache/MariaDB, merge/deploy, atau migrasi database operasional.

### 16. Batas 08a
- Tidak ada approve/reject/rekap, pembatalan izin approved, delegasi per-permintaan, atau approval berjenjang di UI/API.
- Tidak ada notifikasi email/WhatsApp, PDF surat izin, impor/ekspor CSV izin, akun siswa, absensi otomatis, saldo cuti, atau potongan gaji.
- Tidak ada perubahan dependency/lockfile, service, `.env`, database operasional/MariaDB, atau merge/deploy.

## Cara melaporkan hasil

Cukup sampaikan nomor yang berhasil/gagal dan catatan singkat untuk kegagalan (langkah, pesan, atau screenshot). Bila memilih **SKIP** pada langkah opsional (#13), sebutkan nomornya. Jangan mengubah data operasional atau me-reset `.uat-phase08`.

## Batas bukti agen (NOT RUN)

- Konkurensi MariaDB/InnoDB, gap-lock overlap, dan race multi-proses nyata; seluruh tes runtime memakai SQLite in-memory/`.uat-phase08` SQLite.
- Dua tab browser nyata untuk `409`; konflik disimulasikan lewat API + UI stale.
- Suite penuh PHPUnit (tidak dijalankan karena `Tests\Feature\Backup\MariaDbIntegrationTest` membaca `school_access_uat`); regresi Letters dijalankan sebagai pengganti untuk refactor validator.
- Restore drill yang memuat tabel/lampiran izin; manifest backup belum diuji ulang.
- Screenshot-based visual regression berbasis file gambar; benchmark volume besar; uji beban submit paralel.
- Email/WhatsApp/notifikasi, PDF surat izin, impor/ekspor CSV izin, dan seluruh alur keputusan 08b (non-goal 08a).
- Pemeriksaan browser agen mencakup alur operator end-to-end, self-service guru, tampilan view-only waka/kepsek, viewport sempit, dan 0 console error aplikasi. Skenario manual lain pada checklist ini disediakan untuk pemilik; jangan menganggapnya sudah dijalankan agen.
