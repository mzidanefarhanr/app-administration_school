# Panduan Review Manual — Subfase 11a (Ganti Widget Demo Dashboard Operasional)

Subfase: 11a — Penggantian Seluruh Widget Demo Mock Dashboard dengan Metrik Operasional Nyata (4 KPI Cards, Pending Tasks Action List, Data Quality Overview, Dual Charts, System Overview & Shortcuts).
Keputusan pemilik: **DEC-42 APPROVED** (D1–D10, 2026-11-03 WIB; pernyataan **"Saya setuju D1-D10"**).
Laporan teknis lengkap: [`reports/feature-update/PHASE-11A-REPORT.md`](./PHASE-11A-REPORT.md); rencana kerja: [`reports/feature-update/PHASE-11A-PLAN.md`](./PHASE-11A-PLAN.md).
Baseline induk: `main` pada `d7cf62d`. Paket implementasi: `2b7e8a5`. Status teknis: **IMPLEMENTED — agent-verified (2026-11-03)**. Status review pemilik: **APPROVED (#1–#18, 2026-11-03 WIB)** berdasarkan pernyataan pemilik: *"Pengujian nomor 1-18 sudah berhasil dan saya approved."*

---

## Lingkungan UAT Terisolasi (`.uat-phase10`)

- **URL Frontend (SPA)**: `http://127.0.0.1:14028`
- **URL Backend (API)**: `http://127.0.0.1:18028`
- **Halaman Uji Utama**: `http://127.0.0.1:14028/` (Dashboard Utama)
- **Database**: SQLite terisolasi `.uat-phase10/database.sqlite` (data operasional lengkap dari fase sebelumnya)
- **Cookie Session**: `school_phase10_session`
- **Akun Uji Tersedia**:
  - **`uat.admin`**: Admin Sistem — metrik lengkap sekolah, status backup, seluruh domain tugas tertunda, grafik siswa & mengajar.
  - **`uat.operator`**: Operator Sekolah — metrik operasional sekolah, tugas koreksi operasional, audit kualitas data, grafik sekolah.
  - **`uat.kepsek`**: Kepala Sekolah — ikhtisar pimpinan sekolah, tugas review surat keluar & izin, grafik kepatuhan mengajar sekolah.
  - **`uat.waka`**: Wakil Kesiswaan — ikhtisar pimpinan sekolah, review izin & surat.
  - **`uat.guru`**: Guru Pengampu (tertaut ke **`UAT-P10 Teacher 01`**) — mode personal guru: sesi hari ini, sisa jurnal grace yang perlu diisi, grafik kepatuhan mengajar sendiri, data staf/surat disembunyikan.
  - **`uat.staff`**: Staf non-pengajar tanpa hak administratif khusus — hanya menampilkan domain sesuai izin.
  - Password tersimpan pada file lokal: `.uat-phase10/account-password.txt`.

### Panduan Menjalankan Server UAT

Bila ingin melakukan pengujian mandiri melalui peramban (*browser*), jalankan dua perintah berikut di dua jendela terminal terpisah:

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

### Skenario 1: Navigasi Dashboard sebagai Administrator
- **Langkah**: Buka `http://127.0.0.1:14028` dan login sebagai **`uat.admin`**. Buka halaman Beranda / Dashboard (`/`).
- **Ekspektasi**: Halaman dashboard termuat mulus. Seluruh widget demo e-commerce bawaan template (*Orders, Revenue, Customers, Recent Sales, Best Selling, linear chart, notifications*) **sudah tidak ada sama sekali**, digantikan oleh widget operasional sekolah yang nyata.

### Skenario 2: Verifikasi 4 Kartu KPI Operasional Administrator
- **Langkah**: Amati baris paling atas kartu KPI operasional (`OperationalStatsWidget`).
- **Ekspektasi**:
  1. Kartu 1: **Siswa Aktif** menampilkan total siswa aktif nyata dan jumlah rombel aktif pada tahun ajaran berjalan.
  2. Kartu 2: **Pendidik & Tendik** menampilkan jumlah total PTK beserta rincian jumlah Guru dan Tendik.
  3. Kartu 3: **Surat & Izin Tertunda** menampilkan total surat masuk belum disposisi, surat keluar review, dan permohonan izin pending.
  4. Kartu 4: **Kepatuhan Mengajar (30H)** menampilkan persentase kepatuhan jurnal sekolah dan rasio sesi terisi terhadap total sesi terjadwal.

### Skenario 3: Interaktivitas & Navigasi Kartu KPI
- **Langkah**: Klik berturut-turut pada masing-masing kartu KPI:
  - Klik kartu Siswa Aktif.
  - Kembali ke Dashboard, lalu klik kartu Pendidik & Tendik.
  - Kembali ke Dashboard, lalu klik kartu Surat & Izin.
  - Kembali ke Dashboard, lalu klik kartu Kepatuhan Mengajar.
- **Ekspektasi**: Pengguna langsung diarahkan secara akurat ke halaman modul terkait:
  - Kartu Siswa $\to$ `/public/students/directory`.
  - Kartu PTK $\to$ `/public/employees`.
  - Kartu Surat/Izin $\to$ `/public/letters/incoming` atau `/public/leaves`.
  - Kartu Mengajar $\to$ `/public/teaching/reports`.

### Skenario 4: Daftar Tugas Operasional Tertunda (`PendingTasksWidget`)
- **Langkah**: Amati widget **"Tugas & Tindakan Operasional"** pada kolom kiri.
- **Ekspektasi**: Tampil daftar tugas yang memerlukan tindakan nyata. Setiap butir tugas memiliki ikon domain yang jelas (surat masuk/keluar, perizinan, jurnal mengajar, dokumen arsip), kategori, judul, deskripsi tanggal, dan badge urgensi.

### Skenario 5: Badge Urgensi dan Aksi Langsung pada Tugas Tertunda
- **Langkah**: Periksa label urgensi pada butir tugas:
  - Tugas mendesak (mis. surat keluar menunggu review atau izin menunggu persetujuan) bertanda `Mendesak` (merah/danger).
  - Tugas perhatian (mis. surat masuk belum disposisi atau jurnal masa grace) bertanda `Perhatian` (kuning/warn).
  - Klik tombol panah / baris tugas tersebut.
- **Ekspektasi**: Browser langsung berpindah ke halaman form/modul di mana tugas tersebut dapat langsung diselesaikan.

### Skenario 6: State Kosong Tugas Operasional
- **Langkah**: Ketika semua urusan atau tugas telah diselesaikan (atau tidak ada data tertunda).
- **Ekspektasi**: Widget menampilkan ilustrasi tanda centang hijau *"Semua Beres!"* dengan keterangan informatif bahwa tidak ada urusan tertunda.

### Skenario 7: Widget Kualitas & Kelengkapan Data Master (`DataQualityOverviewWidget`)
- **Langkah**: Amati widget **"Kualitas Data Master"** di bawah daftar tugas.
- **Ekspektasi**: Tampil dua kartu progress:
  1. **Data Kelengkapan Siswa**: progress bar persentase kelengkapan data siswa, jumlah profil belum lengkap, dan hitungan kendala yang belum teratasi.
  2. **Data Kelengkapan PTK**: progress bar kelengkapan data pendidik dan tendik.

### Skenario 8: Tautan Langsung ke Audit Kualitas Data
- **Langkah**: Klik tombol *"Buka Audit"* di pojok kanan atas widget atau tautan *"Tinjau X kendala"*.
- **Ekspektasi**: Pengguna langsung diarahkan ke halaman `/public/data-quality` dengan tab audit yang bersesuaian terbuka secara otomatis.

### Skenario 9: Grafik Distribusi Siswa per Rombel (`StudentDistributionChartWidget`)
- **Langkah**: Amati grafik batang di baris tengah sebelah kiri.
- **Ekspektasi**: Menampilkan grafik distribusi siswa per rombel pada tahun ajaran aktif, dengan pemisahan batang atau tumpukan (*stacked*) antara siswa Laki-laki (biru) dan Perempuan (merah muda). Tooltip saat kursor diarahkan ke batang menampilkan rincian angka yang presisi.

### Skenario 10: Grafik Tren Kepatuhan Mengajar (`TeachingComplianceChartWidget`)
- **Langkah**: Amati grafik di baris tengah sebelah kanan.
- **Ekspektasi**: Menampilkan grafik tren kepatuhan jurnal mengajar selama 4 bulan terakhir (kombinasi garis tren persentase kepatuhan dan batang jumlah jurnal terisi). Sumbu vertikal kiri berskala 0–100%.

### Skenario 11: Widget Ikhtisar Sistem & Waktu WIB (`SystemOverviewWidget`)
- **Langkah**: Amati widget **"Sistem & Pintasan Operasional"** di kolom kanan.
- **Ekspektasi**: Menampilkan informasi status Tahun Ajaran aktif (beserta badge Aktif/Terkunci), Semester berjalan, serta waktu server terkini dalam zona waktu Asia/Jakarta (WIB).

### Skenario 12: Tombol Pintasan Cepat Operasional
- **Langkah**: Pada bagian *"Pintasan Fitur Cepat"*, klik beberapa tombol pintasan (mis. *Jurnal Mengajar*, *Laporan KBM*, *Surat Masuk*, *Perizinan & Cuti*, *Direktori Siswa*, *Arsip Dokumen*).
- **Ekspektasi**: Masing-masing tombol berfungsi dengan presisi dan membuka rute modul yang dimaksud secara instan.

### Skenario 13: Status Kesehatan Backup Database
- **Langkah**: Masih pada widget sistem saat login sebagai **`uat.admin`**.
- **Ekspektasi**: Tampil kotak status *Backup Database* yang menampilkan waktu pencadangan terakhir serta badge status kesehatan (*Sehat* berwarna hijau atau *Perlu Cek* berwarna kuning).

### Skenario 14: Preservasi Pengingat Arsip Kedaluwarsa & Pengguna Online
- **Langkah**: Periksa bagian paling atas dashboard dan bagian kolom kanan.
- **Ekspektasi**:
  - `ArchiveExpiryWidget`: tetap tampil di bagian paling atas untuk mengingatkan dokumen arsip yang akan/telah kedaluwarsa.
  - `ListUsersOnlineWidget`: tetap tampil di kolom kanan menampilkan daftar akun yang sedang aktif/daring.

### Skenario 15: Verifikasi Dashboard Operator Sekolah (`uat.operator`)
- **Langkah**: Logout dari `uat.admin`, lalu login sebagai **`uat.operator`**. Buka Dashboard (`/`).
- **Ekspektasi**:
  - Kartu KPI menampilkan data operasional sekolah secara menyeluruh (*school-wide*).
  - Widget tugas menampilkan tugas-tugas operasional operator (mis. surat masuk belum didisposisi, jurnal mengajar overdue).
  - Grafik distribusi siswa dan kepatuhan mengajar tetap tampil penuh.

### Skenario 16: Verifikasi Dashboard Pimpinan Sekolah (`uat.kepsek` / `uat.waka`)
- **Langkah**: Logout, lalu login sebagai **`uat.kepsek`** (Kepala Sekolah) atau **`uat.waka`** (Wakil Kesiswaan).
- **Ekspektasi**:
  - Kartu KPI mengajar menampilkan kepatuhan skala sekolah (*school-wide*).
  - Tugas tertunda berfokus pada persetujuan pimpinan (surat keluar menunggu approval, permohonan izin menunggu keputusan).
  - Kotak status backup database tidak ditampilkan (karena bukan pengelola backup).

### Skenario 17: Verifikasi Dashboard Personal Guru Pengampu (`uat.guru`)
- **Langkah**: Logout, lalu login sebagai **`uat.guru`**. Buka Dashboard (`/`).
- **Ekspektasi**:
  - Kartu KPI mengajar berubah secara otomatis menjadi mode personal: judulnya **"Jadwal Hari Ini"** menampilkan jumlah sesi mengajar guru tersebut pada hari ini, serta sisa jurnal yang perlu diisi dalam masa grace.
  - Kartu PTK dan data umum staf tidak menampilkan angka global sekolah (terbatas).
  - Widget tugas tertunda hanya menampilkan sesi mengajar miliknya sendiri yang belum diisi jurnalnya (*scope=mine*), dengan tombol langsung membuka jurnal mengajar miliknya.
  - Grafik distribusi siswa disembunyikan secara aman dengan pesan informatif izin akses tanpa menyebabkan *error* sistem.
  - Grafik kepatuhan mengajar miliknya tetap tampil.

### Skenario 18: Tampilan Responsif & Penyesuaian Tema Gelap (Dark Mode)
- **Langkah**:
  1. Ubah ukuran jendela peramban ke resolusi tablet dan layar ponsel (atau gunakan Developer Tools Device Mode).
  2. Beralih antara tema Terang (*Light*) dan Gelap (*Dark*) melalui tombol pengaturan tema pada bilah atas.
- **Ekspektasi**:
  - Tampilan grid responsif menata ulang kartu KPI dari 4 kolom menjadi 2 kolom (tablet) dan 1 kolom (ponsel) secara rapi tanpa elemen terpotong.
  - Pada tema Gelap (*Dark Mode*), seluruh kartu, teks, warna latar belakang, border, dan grafik (*chart colors, grid lines, legend labels*) beradaptasi secara elegan dan nyaman dibaca.

---

## Catatan Penerimaan Pemilik (2026-11-03 WIB)

Pernyataan pemilik:
> **"Pengujian nomor 1-18 sudah berhasil dan saya approved. Buatkan plan untuk lanjutan tahap/fase berikutnya."**

- **Hasil checklist manual #1–#18**: Seluruhnya **APPROVED** (2026-11-03 WIB).
- **Status Subfase 11a**: **SELESAI LENGKAP & DITUTUP**.
- **DEC-42**: **APPROVED & CLOSED**.
- **Server UAT 18028/14028**: Dipastikan dihentikan per DEC-27 (`PORTS_18028_14028_FREE`).
- **Tahap selanjutnya**: Perencanaan Subfase 11b (Laporan dan Rekonsiliasi).
