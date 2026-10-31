# Panduan Review Manual — Subfase 05c (Pemeriksaan Kelengkapan)

Subfase: 05c — pemeriksaan kelengkapan siswa dan pegawai, daftar missing/invalid/duplikat/blocked, status review operator.
Keputusan: **DEC-28 APPROVED** (D1–D7, 2026-10-31 WIB). Laporan teknis: `reports/feature-update/PHASE-05C-REPORT.md`.
Baseline implementasi: `main` pada `c2fddfa` (di atas `037b1c6`). Status: **OWNER APPROVED 2026-10-31 WIB — seluruh #1–#14**. Pernyataan pemilik adalah satu-satunya bukti penerimaan; agen tidak mengulang skenario dan item NOT RUN tetap NOT RUN.

## Status penerimaan pemilik

- Pernyataan pemilik 2026-10-31 WIB: **"Pengujian nomor 1-14 sudah berhasil dan saya approved."**
- Cakupan: seluruh skenario #1–#14 pada lingkungan UAT terisolasi `.uat-phase05`. Pernyataan pemilik adalah satu-satunya bukti penerimaan; agen tidak mengulang skenario.
- Batas: penerimaan tidak mengotorisasi merge, deploy, migrasi data legacy/MariaDB/produksi, perubahan dependency/service, rekap sekolah, atau Fase 06. Item NOT RUN agen tetap NOT RUN.

## Lingkungan UAT (terisolasi)

- SPA: `http://127.0.0.1:14023`
- API: `http://127.0.0.1:18023`
- Data: `.uat-phase05` SQLite; cookie `school_phase05_session`
- Launcher: `laravel-api/scripts/phase05-uat.php`
- Akun: **`uat.operator`** (manage siswa/pegawai, dapat menulis review) dan **`uat.kepsek`** (view-only; tidak dapat menulis review). Password hanya ada di runtime privat `.uat-phase05`; tidak dicetak di dokumen.

Status: server SPA (PID 10336) dan API (PID 19196) **dihentikan 2026-10-31** setelah penerimaan, sesuai DEC-27. Command line kedua proses diverifikasi sebelum `taskkill /T /F`; port 14023/18023 terverifikasi bebas; Apache 80/443 dan MariaDB 3306 tidak disentuh; data `.uat-phase05` dipertahankan.

Perintah restart bila server sudah berhenti:

```powershell
# API (dari laravel-api)
C:\xampp\php82-operational\php.exe -S 127.0.0.1:18023 -t public scripts/phase05-uat.php

# SPA (dari vue-app)
npm run dev -- --host 127.0.0.1 --port 14023 --strictPort
```

Perintah fixture (tidak perlu dijalankan ulang; idempotent, tidak mereset data):

```powershell
# dari laravel-api
C:\xampp\php82-operational\php.exe scripts/phase05-uat.php prepare-data-quality
C:\xampp\php82-operational\php.exe scripts/phase05-uat.php status
```

Angka acuan saat ini: 469 siswa / 260 pegawai (133 teacher, 127 staff); fixture `UAT-DQ` 60 siswa (50 placed) dan 60 pegawai (50 active, 10 separated). Tally fixture yang diharapkan: 35 issue siswa dan 25 issue pegawai (lihat `PHASE-05C-REPORT.md` §4).

## Checklist

### 1. Isolasi dan populasi UAT
- Jalankan `status` pada launcher (bila perlu) dan buka **Data Quality**.
- Harapkan: guard SQLite lolos, jumlah siswa/pegawai sesuai angka acuan, tab Students dan Employees tampil, menu **Data Quality** terlihat untuk akun dengan `students.view` atau `employees.view`.
- Catat bila ada error console/network.

### 2. Ringkasan siswa (denominator dan blocked)
- Tab **Students**, tahun ajaran `2026/2027`.
- Harapkan (angka saat ini): Placed Students (denominator) **153**; Active **469**; Unplaced **316**; Not evaluated **306**; Archived excluded **0**; Complete Records **30**; Incomplete **123**; Complete **19,6%**; Field Findings **126** (Missing 118 / Invalid 8); Blocked NIK **10 missing / 0 invalid**; Duplicate Candidates **8** (affected 16); Review Workflow **144 Open**.
- Pastikan ada penjelasan bahwa **blocked di luar denominator** dan **duplikat hanya kandidat, tanpa auto-merge**.

### 3. Filter, pencarian, dan paginasi
- Filter type: **Missing**, **Invalid**, **Blocked**; filter field: **NIK**; status tetap **Open**.
- Cari nama/NIS/NISN (contoh: siswa `UAT-DQ`), ubah rows per page 10/20/50/100.
- Harapkan: jumlah `matching` berubah sesuai filter, hasil konsisten lintas halaman, `Reset` mengembalikan filter, tidak ada error.

### 4. Kandidat duplikat (tanpa merge)
- Filter type **Duplicate Candidate** atau cari pasangan duplikat siswa.
- Harapkan: dua record ditampilkan (Record + Related), pesan “Possible duplicate…”, **tidak ada** tombol merge/auto-fix. Tautan related membuka record pasangannya.

### 5. Deep link dan koreksi lewat form lama
- Klik aksi **Open** pada issue siswa `UAT-DQ Student 021` (id 430) atau record bermasalah lain.
- Harapkan: halaman **Students** terbuka dengan dialog detail record yang benar; NIK tampil sesuai data.
- Tutup dialog, edit record melalui form yang ada (bukan edit inline di Data Quality), simpan.
- Kembali ke Data Quality: issue yang terkait berubah/hilang; bila record berubah versi, status review lama **tidak** menyembunyikan issue baru.

### 6. Acknowledge / Ignore / Reopen
- Pilih satu issue `open`; **Acknowledge** → status berubah, versi review naik.
- **Ignore** tanpa alasan → ditolak dengan pesan English; pilih alasan (mis. `Pending document`) → tersimpan.
- **Reopen** → status kembali `Open`, versi naik lagi.
- Harapkan: counter Open/Acknowledged/Ignored berubah konsisten; persentase kelengkapan **tidak berubah** karena review tidak mengubah data.
- Contoh yang sudah diverifikasi agen: Student 021 v1 Open → v2 Ignored (Pending document) → v3 Open; duplicate Teacher 034 Ignore (Verified as distinct) → reopen v3.

### 7. Akses view-only
- Login sebagai `uat.kepsek`.
- Harapkan: dapat membuka Data Quality dan melihat issue, tetapi tombol/dialog review tidak tersedia; percobaan menulis review ditolak `403` (server menegakkan, bukan hanya UI).
- Login kembali sebagai `uat.operator` untuk skenario berikutnya.

### 8. Ringkasan pegawai
- Tab **Employees**.
- Harapkan (angka saat ini): Active Employees (denominator) **250**; Separated **10**; Archived excluded **0**; Complete **30**; Incomplete **220**; Complete **12,0%**; Field Findings **320 Missing**; NIK **213 missing** dari 250; NUPTK **107 missing** dari 133 teacher; Subject **250 complete**; Duplicate Candidates **5** (affected 10); Review Workflow **325 Open**.
- Pastikan aturan tampil: **NUPTK diukur hanya untuk teacher**, **Subject wajib untuk teacher dan harus kosong untuk non-teaching**.

### 9. Deep link pegawai (Teacher vs Employee)
- Buka issue `UAT-DQ` milik teacher (Pendidik) → tautan membuka **Teachers** (`?openEmployee=…`).
- Buka issue milik staff (Tenaga Kependidikan) → tautan membuka **Employees** (`?openEmployee=…`).
- Contoh terverifikasi: Teacher 035 id 242 (Teachers), Staff 021 id 228 (Employees). Label tipe English: Teacher / Non-teaching Employee.

### 10. Konflik versi (409)
- Buka Data Quality di dua tab; di tab A lakukan Acknowledge, lalu di tab B coba review dengan data lama.
- Harapkan: tab B menerima `409` dengan pesan refresh; data tidak berubah sampai daftar dimuat ulang.
- Catatan: skenario dua tab belum dijalankan agen (NOT RUN); `409` sudah tertutup tes otomatis untuk review usang, versi record berubah, dan issue key tidak cocok.

### 11. Privasi respons
- Buka DevTools → Network pada daftar issue.
- Harapkan: payload tidak memuat NIK, alamat, telepon, atau data medis; pesan issue tidak menyalin nilai field; nomor identitas tidak muncul di `issue_key`.
- Buka audit aktivitas terkait review: hanya metadata temuan (tanpa nama/NIK/NUPTK/alamat).

### 12. Tampilan 390 px dan keyboard
- Emulasikan lebar 390 px (mis. DevTools device toolbar).
- Harapkan: tidak ada overflow level halaman; tabel issue dapat di-scroll horizontal; dialog review muat di viewport (sekitar 359 px) dan kontrol dapat difokus/dioperasikan dengan keyboard.

### 13. Regresi halaman lama
- Buka Students, Teachers, Employees seperti biasa (tanpa query deep link).
- Harapkan: perilaku create/edit/arsip/ekspor/impor 03b/04b/05a/05b tidak berubah; tombol dan filter tetap berfungsi; tidak ada error console.

### 14. Penutupan setelah penerimaan
- Setelah checklist lulus dan pemilik menyatakan penerimaan, hentikan server UAT 14023/18023 sesuai DEC-27; data `.uat-phase05` dipertahankan.
- Jangan mengubah Apache/MariaDB/service/dependency; jangan migrasikan data ke DB operasional.

## Cara melaporkan hasil

Untuk setiap nomor, tulis: **PASS/FAIL**, langkah yang dilakukan, hasil aktual (angka/pesan), dan screenshot bila ada. Sertakan pesan console/network bila muncul error. Jangan mengetes mutasi pada data operasional; seluruh skenario menggunakan `.uat-phase05`.
