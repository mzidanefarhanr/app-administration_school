# Panduan Review Manual — Subfase 10c (Laporan Belum Mengisi Jurnal)

Subfase: 10c — Laporan Belum Mengisi Jurnal (Missing Teaching Journal Compliance Report), rekap kepatuhan guru skala sekolah, aturan penyebut eksak (denominator rules), segmentasi batas waktu grace window vs overdue, otorisasi RBAC berlapis, aksi pengisian jurnal terintegrasi, dan streaming ekspor file CSV.
Keputusan pemilik: **DEC-41 APPROVED** (D1–D9, 2026-11-03 WIB; pernyataan **"Saya setuju D1-D9"**).
Laporan teknis lengkap: [`reports/feature-update/PHASE-10C-REPORT.md`](./PHASE-10C-REPORT.md); rencana kerja: [`reports/feature-update/PHASE-10C-PLAN.md`](./PHASE-10C-PLAN.md).
Baseline induk: `main` pada `122e044` (pencatatan `DEC-41 APPROVED` di atas paket penerimaan 10b `35728bb`). Status teknis: **IMPLEMENTED — agent-verified (2026-11-03)**. Status review pemilik: **APPROVED (#1–#18, 2026-11-03 WIB)** berdasarkan pernyataan: *"Pengujian nomor 1-18 sudah berhasil dan saya approved."*.

---

## Lingkungan UAT Terisolasi (`.uat-phase10`)

- **URL Frontend (SPA)**: `http://127.0.0.1:14028`
- **URL Backend (API)**: `http://127.0.0.1:18028`
- **Halaman Uji Utama**: `http://127.0.0.1:14028/public/teaching/reports` (alias `/teaching/reports`)
- **Database**: SQLite terisolasi `.uat-phase10/database.sqlite` (data 10a & 10b utuh)
- **Cookie Session**: `school_phase10_session`
- **Akun Uji Tersedia**:
  - **`uat.admin`**: Admin Sistem (`teaching.manage`, `teaching.view`) — hak penuh seluruh sekolah & ekspor.
  - **`uat.operator`**: Operator Sekolah (`teaching.manage`, `teaching.view`) — hak kelola seluruh sekolah & koreksi.
  - **`uat.kepsek`**: Kepala Sekolah (`teaching.view`, pimpinan) — pemantauan seluruh sekolah (read-only) & ekspor.
  - **`uat.waka`**: Wakil Kesiswaan (`teaching.view`, pimpinan) — pemantauan seluruh sekolah & ekspor.
  - **`uat.guru`**: Guru Pengampu (`teaching.view`, tertaut ke **`UAT-P10 Teacher 01`**) — server memaksa `scope=mine` (hanya melihat data miliknya).
  - **`uat.guru2`**: Guru Pengampu kedua (`teaching.view`, tertaut ke **`UAT-P10 Teacher 02`**) — untuk verifikasi isolasi data antar guru.
  - **`uat.staff`**: Staf non-pengajar (tanpa `teaching.view`) — ditolak `403`.
  - **`uat.inactive`**: Akun nonaktif — ditolak `403`.
  - Password tersimpan pada file lokal: `.uat-phase10/account-password.txt`.

### Panduan Menjalankan Server UAT

Bila ingin melakukan pengujian mandiri melalui peramban (browser), jalankan dua perintah berikut di dua jendela terminal terpisah:

```powershell
# 1. Terminal Backend API (dari folder laravel-api)
cd D:\xampp-htdocs\app-administration_school\laravel-api
php -S 127.0.0.1:18028 scripts/phase10-uat.php

# 2. Terminal Frontend SPA (dari folder vue-app)
cd D:\xampp-htdocs\app-administration_school\vue-app
$env:VITE_API_BASE_URL="http://127.0.0.1:18028"
npx vite --host 127.0.0.1 --port 14028
```

> [!IMPORTANT]
> Sesuai aturan **`DEC-27`**, kedua server UAT di atas harus dihentikan kembali (tekan `Ctrl+C`) setelah peninjauan selesai agar port 18028 dan 14028 kembali bebas.

---

## Checklist Pengujian Manual (#1–#18)

### Skenario 1: Navigasi Menu & Tampilan Halaman Laporan
- **Langkah**: Login ke `http://127.0.0.1:14028` sebagai **`uat.admin`**. Buka menu sidebar: **Teaching → Journal Reports**.
- **Ekspektasi**: Halaman `/public/teaching/reports` terbuka mulus. Tampil judul *"Teaching Journal Compliance Reports"*, deskripsi analitikal, serta bilah filter context (Tahun Ajaran & Semester).

### Skenario 2: Konteks Default & Badge Peran Administrator
- **Langkah**: Amati bagian atas halaman setelah pemuatan selesai.
- **Ekspektasi**: Tahun Ajaran otomatis terpilih **2026/2027**, Semester **Semester I**. Pada Context Information Bar tampil badge biru: `Administrator / Operator Overview (School-Wide)` beserta tanggal `Today (WIB)` dan masa `Grace Window: 7 days`.

### Skenario 3: Tombol Pintasan Cepat Rentang Tanggal (Presets)
- **Langkah**: Pada baris filter, klik berturut-turut tombol pintasan: **7D**, **30D**, dan **This Month**.
- **Ekspektasi**: Nilai pada input `Date From` dan `Date To` terisi otomatis secara presisi sesuai rumus hari WIB, dan laporan dihitung ulang seketika.

### Skenario 4: Rekapitulasi Kartu KPI Skala Sekolah (Oktober 2026)
- **Langkah**: Tetapkan tanggal `Date From = 2026-10-01` dan `Date To = 2026-10-31`, lalu klik tombol **Apply Filters**.
- **Ekspektasi**: 4 kartu ringkasan KPI menampilkan data agregat sekolah:
  - **Expected Sessions**: `540`
  - **Recorded Journals**: `64`
  - **Missing Journals**: `476` (dengan rincian: Within Grace `100`, Overdue `376`)
  - **Overall Compliance**: `11.9%` (badge persentase kepatuhan)
  - **Excluded Slots**: `0`

### Skenario 5: Konsistensi Matematika Agregat Laporan
- **Langkah**: Periksa kebenaran matematis dari angka-angka pada kartu KPI.
- **Ekspektasi**:
  - $\text{Expected} = \text{Recorded} + \text{Missing} \rightarrow 540 = 64 + 476$ (eksak 100%).
  - $\text{Missing} = \text{Within Grace} + \text{Overdue} \rightarrow 476 = 100 + 376$ (eksak 100%).

### Skenario 6: Tab Rekap Kepatuhan Guru (Teacher Compliance Summary)
- **Langkah**: Pastikan aktif pada **Tab 1: Teacher Compliance Summary**.
- **Ekspektasi**: Tampil tabel 10 guru aktif mengajar (`UAT-P10 Teacher 01` s.d. `Teacher 10`). Kolom menampilkan Expected Sessions, Recorded, Missing (Grace), Missing (Overdue), persentase kepatuhan dengan progress bar visual, dan tombol aksi "View Sessions".

### Skenario 7: Pengecualian Guru Tanpa Jadwal Mengajar (Denominator Rule)
- **Langkah**: Periksa apakah ada guru yang tidak memiliki jadwal mengajar di periode tersebut masuk ke daftar penunggak jurnal.
- **Ekspektasi**: Guru tanpa jadwal mengajar tidak dimasukkan ke dalam daftar rekapitulasi kepatuhan guru (memenuhi kriteria penyebut eksak `DEC-41 D3`).

### Skenario 8: Interaksi Pintas "View Sessions" ke Tab Drilldown
- **Langkah**: Pada baris guru **`UAT-P10 Teacher 01`**, klik tombol **View Sessions**.
- **Ekspektasi**: Sistem otomatis berpindah ke **Tab 2: Missing Sessions Drilldown** dan bilah filter otomatis terpasang menyaring sesi milik `UAT-P10 Teacher 01`. Muncul banner filter aktif: *"Filtering by Teacher ID: ..."* dengan tombol *"Clear Teacher Filter"*.

### Skenario 9: Tampilan Rincian Sesi (Missing Sessions Drilldown)
- **Langkah**: Amati kolom-kolom pada tabel drilldown sesi.
- **Ekspektasi**: Setiap baris menampilkan Tanggal Sesi & Jam Ke, Kelas & Mata Pelajaran, Guru Pengampu (dengan badge khusus jika guru pengganti), Jenis Sesi (Reguler Timetable / Rescheduled Slot), dan Badge Status Jurnal.

### Skenario 10: Segmentasi Badge Status Sesi (Grace vs Overdue vs Recorded)
- **Langkah**: Amati perbedaan badge status jurnal pada sesi-sesi yang tampil.
- **Ekspektasi**:
  - Sesi yang sudah beres dicatat: Badge hijau **Recorded** beserta judul topik materi.
  - Sesi belum mengisi dalam $\le 7$ hari WIB: Badge kuning **Missing (Within Grace)** bertuliskan *"Can be recorded by teacher"*.
  - Sesi belum mengisi $> 7$ hari WIB: Badge merah **Missing (Overdue)** bertuliskan *"Requires admin/operator correction"*.

### Skenario 11: Paginasi Server-side Rincian Sesi
- **Langkah**: Ubah opsi dropdown per halaman di kanan bawah tabel drilldown dari 20 ke **10 / page**, lalu klik tombol panah kanan (Next Page).
- **Ekspektasi**: Tabel menampilkan tepat 10 baris per halaman, nomor halaman berpindah ke *Page 2 of N*, dan total sesi terhitung konsisten.

### Skenario 12: Penyaringan Berdasarkan Status Jurnal
- **Langkah**: Pada dropdown filter **Journal Status**, pilih **Missing Journals Only**, lalu klik **Apply Filters**.
- **Ekspektasi**: Seluruh baris yang berstatus *Recorded* disaring keluar; hanya sesi yang menunggak pengisian jurnal yang ditampilkan.

### Skenario 13: Aksi Langsung "Fill Journal" dari Halaman Laporan
- **Langkah**: Pada salah satu baris sesi yang berstatus Missing, klik tombol hijau **Fill Journal**.
- **Ekspektasi**: Muncul modal dialog *"Record Teaching Journal"* secara langsung di halaman laporan. Informasi tanggal, jam ke, kelas, mapel, dan guru telah terisi otomatis di header dialog.

### Skenario 14: Pengisian Jurnal & Pembaruan Laporan Real-Time
- **Langkah**: Masukkan Topik mengajar (misal: `"UAT 10c Pengujian Langsung dari Laporan"`), pilih Delivery Method, lalu klik **Save Journal**.
- **Ekspektasi**: Toast notifikasi hijau *"Teaching journal recorded successfully"* muncul. Modal tertutup, dan data laporan dihitung ulang otomatis secara real-time (jumlah Recorded bertambah 1 dan Missing berkurang 1).

### Skenario 15: Ekspor Laporan ke Format CSV
- **Langkah**: Klik tombol **Export CSV** berwarna hijau pada bilah filter toolbar.
- **Ekspektasi**: Peramban otomatis mengunduh berkas dengan nama pola `teaching-missing-journals-YYYYMMDD_HHMMSS.csv`. Buka file CSV: terdapat header UTF-8 BOM, judul kolom rapi (Session Date, Class, Subject, Assigned Teacher, Effective Teacher, Journal Status, Deadline Status), dan data sesuai dengan filter aktif.

### Skenario 16: Penegakan RBAC untuk Akun Guru (`uat.guru`)
- **Langkah**: Logout dari `uat.admin`, lalu login sebagai **`uat.guru`** (Guru Matematika). Buka halaman laporan: `/public/teaching/reports`.
- **Ekspektasi**:
  - Tampil badge hijau: `Teacher View: UAT-P10 Teacher 01`.
  - Dropdown filter guru tidak ditampilkan bagi guru biasa.
  - Rekap kepatuhan hanya menampilkan 1 baris (dirinya sendiri).
  - Rincian drilldown hanya menampilkan sesi di mana `UAT-P10 Teacher 01` bertindak sebagai guru efektif.
  - Server secara ketat memaksakan parameter `scope=mine`.

### Skenario 17: Penegakan Otorisasi Pimpinan Sekolah (`uat.kepsek`)
- **Langkah**: Logout lalu login sebagai **`uat.kepsek`** (Kepala Sekolah). Buka halaman laporan.
- **Ekspektasi**:
  - Kepala Sekolah dapat melihat rekapitulasi kepatuhan seluruh guru di sekolah (school-wide overview).
  - Dapat mengunduh file ekspor CSV seluruh sekolah.
  - Bersifat read-only: tombol pengisian jurnal langsung tidak aktif/tidak diizinkan bagi akun pimpinan non-pengajar.

### Skenario 18: Pembacaan Arsip Tahun Ajaran Terkunci (`2024/2025`)
- **Langkah**: Pada dropdown Academic Year, pilih tahun **2024/2025** (yang telah di-lock).
- **Ekspektasi**:
  - Tampil banner peringatan kuning: *"Locked Academic Year (2024/2025): This academic year is locked. Reports are read-only."*
  - Laporan analitikal historis tahun 2024/2025 tetap dapat dibaca dan dievaluasi.
  - Seluruh tombol aksi pengisian jurnal ("Fill Journal") dinonaktifkan (`can_record = false`).

---

---

## Hasil Review Manual Pemilik (APPROVED 2026-11-03 WIB)

Pemilik menyatakan:
> **"Pengujian nomor 1-18 sudah berhasil dan saya approved."** (2026-11-03 WIB)

Seluruh 18 skenario uji manual di atas dinyatakan **APPROVED**:
- **#1 s.d. #5**: Navigasi, konteks default, filter presets cepat, kartu KPI sekolah, konsistensi matematika denominator & status $ightarrow$ **APPROVED**
- **#6 s.d. #8**: Tab rekapitulasi kepatuhan per guru, aturan penyebut guru tanpa jadwal tidak dianggap menunggak, navigasi drilldown otomatis $ightarrow$ **APPROVED**
- **#9 s.d. #12**: Tab rincian sesi belum terisi, segmentasi badge grace window ($le 7$ hari) vs overdue ($> 7$ hari), paginasi server, filter status $ightarrow$ **APPROVED**
- **#13 s.d. #14**: Integrasi aksi langsung pengisian jurnal dari laporan dan kalkulasi ulang instan $ightarrow$ **APPROVED**
- **#15**: Ekspor CSV dengan UTF-8 BOM, judul kolom rapi, dan streaming aman $ightarrow$ **APPROVED**
- **#16 s.d. #17**: RBAC guru terisolasi ke `scope=mine` dan otorisasi pimpinan sekolah (read-only school-wide) $ightarrow$ **APPROVED**
- **#18**: Pembacaan arsip tahun ajaran terkunci 2024/2025 secara read-only + penghentian server UAT port 18028/14028 per DEC-27 $ightarrow$ **APPROVED**

Dengan persetujuan ini, **Subfase 10c resmi DITERIMA PEMILIK dan DITUTUP**, sehingga **seluruh rangkaian Fase 10 (10a, 10b, 10c) telah LENGKAP dan SELESAI**.
