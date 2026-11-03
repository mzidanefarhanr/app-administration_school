# Panduan Review Manual — Subfase 11b (Laporan Rekapitulasi Sekolah & Rekonsiliasi Dapodik)

Subfase: 11b — Laporan Rekapitulasi Sekolah, Statistik Multi-Tahun, dan Rekonsiliasi Data Dapodik (DEC-43 IMPLEMENTED, DEC-12 CLOSED).
Keputusan pemilik: **DEC-43 APPROVED** (D1–D10, 2026-11-03 WIB; pernyataan **"Saya setuju D1-D10"**). **DEC-12 CLOSED**.
Laporan teknis lengkap: [`reports/feature-update/PHASE-11B-REPORT.md`](./PHASE-11B-REPORT.md); rencana kerja: [`reports/feature-update/PHASE-11B-PLAN.md`](./PHASE-11B-PLAN.md).
Baseline induk: `main` pada `dc43954`. Status teknis: **IMPLEMENTED — agent-verified (2026-11-03)**. Status review pemilik: **AWAITING OWNER REVIEW (#1–#18, 2026-11-03 WIB)**.

---

## Lingkungan UAT Terisolasi (`.uat-phase10`)

- **URL Frontend (SPA)**: `http://127.0.0.1:14028`
- **URL Backend (API)**: `http://127.0.0.1:18028`
- **Halaman Uji Utama**: `http://127.0.0.1:14028/public/reports/school` (Laporan Rekapitulasi & Rekonsiliasi Dapodik)
- **Database**: SQLite terisolasi `.uat-phase10/database.sqlite`
- **Cookie Session**: `school_phase10_session`
- **Akun Uji Tersedia**:
  - **`uat.admin`**: Admin Sistem — akses penuh ke ketiga tab: Tren Multi-Tahun, Demografi & Kesiapan Profil, serta Rekonsiliasi Data Dapodik.
  - **`uat.operator`**: Operator Sekolah — akses penuh laporan dan rekonsiliasi Dapodik (`students.import`).
  - **`uat.kepsek`**: Kepala Sekolah — akses melihat tren multi-tahun dan demografi (`students.view`), tanpa izin upload rekonsiliasi Dapodik.
  - **`uat.waka`**: Wakil Kesiswaan — akses melihat tren multi-tahun dan demografi (`students.view`).
  - **`uat.guru`**: Guru Pengampu — tanpa hak akses laporan kesiswaan (mendapat pembatasan menu).
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

### Skenario 1: Akses Navigasi Menu "School Reports"
- **Langkah**: Buka `http://127.0.0.1:14028` dan login sebagai **`uat.admin`** atau **`uat.operator`**. Periksa menu navigasi di bilah samping (sidebar) pada kelompok menu **Public**.
- **Ekspektasi**: Terdapat menu baru **"School Reports"** dengan ikon grafik (`pi pi-chart-line`). Klik menu tersebut dan pastikan peramban membuka URL `/public/reports/school` dengan judul *"Laporan Rekapitulasi Sekolah & Rekonsiliasi Dapodik"*.

### Skenario 2: Tab Tren Multi-Tahun — Ringkasan 4 Kartu KPI Terkini
- **Langkah**: Pada tab aktif pertama **"Tren Multi-Tahun"**, amati 4 kartu KPI ringkasan tahun ajaran terkini di bagian atas.
- **Ekspektasi**:
  1. Kartu **Siswa Aktif Terkini**: menampilkan total siswa aktif beserta proporsi siswa Laki-laki dan Perempuan.
  2. Kartu **Rombongan Belajar**: menampilkan jumlah rombel aktif dan rata-rata rasio siswa per kelas.
  3. Kartu **Arus Mutasi Siswa**: menampilkan net mutasi (+/-) beserta rincian siswa Masuk dan Keluar.
  4. Kartu **Total PTK Aktif**: menampilkan gabungan Guru dan Tenaga Kependidikan aktif.

### Skenario 3: Tab Tren Multi-Tahun — Grafik Populasi Siswa & Gender
- **Langkah**: Periksa grafik sebelah kiri **"Tren Populasi Siswa & Gender"**.
- **Ekspektasi**: Tampil grafik batang komparasi siswa Laki-laki (biru) dan Perempuan (merah muda) serta garis tren Total Siswa (hijau) melintasi tahun ajaran yang tersedia. Arahkan kursor (*hover*) pada batang grafik untuk memeriksa *tooltip* rincian angka.

### Skenario 4: Tab Tren Multi-Tahun — Grafik Mutasi Siswa (Masuk vs Keluar)
- **Langkah**: Periksa grafik sebelah kanan **"Tren Mutasi Siswa (Masuk vs Keluar)"**.
- **Ekspektasi**: Tampil grafik batang komparasi mutasi masuk (cyan) dan mutasi keluar (oranye/amber) per tahun ajaran.

### Skenario 5: Tab Tren Multi-Tahun — Rekapitulasi Tabular per Tahun Ajaran
- **Langkah**: Gulir ke bagian bawah tab Tren Multi-Tahun pada tabel **"Rekapitulasi Tabular per Tahun Ajaran"**.
- **Ekspektasi**: Tampil tabel komprehensif berisi kolom: Tahun Ajaran (dengan badge status Terkunci bila berlaku), Siswa L, Siswa P, Total Siswa, Rombel, Mutasi Masuk (+), Mutasi Keluar (-), Guru, dan Tendik.

### Skenario 6: Tab Tren Multi-Tahun — Filter Rentang Waktu (3, 5, 10 Tahun)
- **Langkah**: Pada toolbar kontrol atas, ubah dropdown **Rentang Waktu** dari *"5 Tahun Terakhir"* menjadi *"3 Tahun Terakhir"* atau *"10 Tahun Terakhir"*.
- **Ekspektasi**: Grafik dan tabel rekapitulasi secara otomatis melakukan *reload* dan menyesuaikan jumlah tahun ajaran yang ditampilkan tanpa *refresh* peramban.

### Skenario 7: Tab Tren Multi-Tahun — Ekspor CSV Rekapitulasi (UTF-8 BOM)
- **Langkah**: Klik tombol **"Ekspor CSV Rekap"** di sudut kanan atas toolbar.
- **Ekspektasi**: Berkas CSV bernama `rekap_statistik_sekolah_YYYYMMDD.csv` terunduh. Buka di Microsoft Excel atau editor teks; huruf/karakter terbaca rapi karena telah disematkan UTF-8 BOM (`\xEF\xBB\xBF`) tanpa karakter aneh pada awal berkas.

### Skenario 8: Tab Demografi — Filter Tahun Ajaran & Indikator Kunci
- **Langkah**: Klik tab kedua **"Demografi & Kesiapan Profil"**.
- **Ekspektasi**: Tampil 4 kartu KPI demografi: Total Siswa Aktif, Siswa Laki-Laki (%), Siswa Perempuan (%), dan Kesiapan Profil Dapodik (%). Terdapat dropdown pemilih Tahun Ajaran yang memungkinkan pergantian tahun ajaran secara dinamis.

### Skenario 9: Tab Demografi — Doughnut Chart Gender & Agama
- **Langkah**: Periksa dua grafik donat (*doughnut chart*) di bagian tengah halaman.
- **Ekspektasi**:
  1. Grafik **Distribusi Gender Siswa**: menampilkan persentase siswa Putra vs Putri dengan palet warna kontras.
  2. Grafik **Distribusi Agama Siswa**: menampilkan sebaran pemeluk agama yang terdaftar di sekolah.

### Skenario 10: Tab Demografi — Audit Kesiapan Profil Dapodik Siswa
- **Langkah**: Amati kotak kartu **"Audit Kesiapan Data Sinkronisasi Dapodik"** pada panel kiri (Kesiapan Profil Siswa).
- **Ekspektasi**: Tampil status kesiapan profil kesiswaan dilengkapi *badge* persentase kelengkapan dan 5 bar kemajuan (*progress bar*):
  - NIK Valid (16 Digit)
  - NISN Valid (10 Digit)
  - Nomor Kartu Keluarga (KK)
  - Nama Ibu Kandung
  - Tempat & Tanggal Lahir

### Skenario 11: Tab Demografi — Audit Kesiapan Profil PTK
- **Langkah**: Amati panel kanan (Kesiapan Profil Pendidik & Tenaga Kependidikan).
- **Ekspektasi**: Tampil audit profil PTK untuk integrasi Dapodik:
  - NIK PTK Valid (16 Digit)
  - Pendidik dengan NUPTK
  - Pendidik Terpetakan Mata Pelajaran

### Skenario 12: Tab Demografi — Distribusi Kelompok Usia & Rekap Rombel
- **Langkah**: Amati dua tabel di bagian bawah tab Demografi:
  - Tabel Distribusi Kelompok Usia (<15, 15, 16, 17, 18, >18).
  - Tabel Rekapitulasi per Rombongan Belajar (Nama Rombel, Jenjang, L, P, Total Siswa).
- **Ekspektasi**: Data terdistribusi dengan benar, tabel rombel mendukung penomoran halaman (*pagination*).

### Skenario 13: Tab Rekonsiliasi Dapodik — Hak Akses Terbatas (Bukan Operator/Admin)
- **Langkah**: Buka sesi peramban baru / jendela incognito, lalu login sebagai **`uat.kepsek`** (Kepala Sekolah) atau **`uat.waka`** (Wakil Kesiswaan). Buka `/public/reports/school` dan klik tab **"Rekonsiliasi Data Dapodik"**.
- **Ekspektasi**: Tampil kotak informasi bertanda peringatan: *"Akses Terbatas: Hanya Operator & Administrator"*, menginformasikan bahwa proses komparasi dan impor data eksternal dibatasi pada pemegang wewenang `students.import` demi integritas data sekolah.

### Skenario 14: Tab Rekonsiliasi Dapodik — Unggah Berkas CSV Dapodik
- **Langkah**: Login kembali sebagai **`uat.admin`** atau **`uat.operator`**. Buka tab **"Rekonsiliasi Data Dapodik"**. Siapkan berkas teks berekstensi `.csv` (misal berkas contoh berikut):
  ```csv
  nisn,nik,nama,rombel,jenis_kelamin
  0012345678,3201010101010001,Ahmad Dahlan,X-A,L
  1234567890,3201010101010002,Budi Santoso Edit,X-B,L
  9999999999,3201010101019999,Siswa Luar Dapodik,X-C,P
  ```
  Pilih berkas tersebut lalu klik tombol **"Mulai Rekonsiliasi"**.
- **Ekspektasi**: Sistem membaca berkas, mendeteksi *delimiter*, melakukan pencocokan dua tahap (NISN lalu NIK), menampilkan notifikasi sukses *"Rekonsiliasi Selesai"*, dan langsung beralih ke tampilan hasil komparasi.

### Skenario 15: Tab Rekonsiliasi Dapodik — 6 Kartu KPI Ringkasan Hasil Komparasi
- **Langkah**: Amati baris kartu ringkasan hasil rekonsiliasi.
- **Ekspektasi**: Tampil 6 kartu metrik:
  1. **Data Dapodik**: total baris berkas eksternal.
  2. **Siswa Lokal**: total siswa aktif di database sekolah.
  3. **Cocok Identik**: siswa yang NISN/NIK cocok dan semua data identik (hijau).
  4. **Beda Data**: siswa yang cocok namun ada perbedaan nama/rombel (kuning/amber).
  5. **Hanya di Lokal**: siswa aktif lokal yang tidak tercatat di Dapodik (biru).
  6. **Hanya di Dapodik**: siswa tercatat di Dapodik namun belum ada di database lokal (ungu).

### Skenario 16: Tab Rekonsiliasi Dapodik — Filter Cepat Status Diskrepansi
- **Langkah**: Klik berturut-turut pada kartu atau tombol filter:
  - Klik **"Beda Data"**.
  - Klik **"Hanya di Lokal"**.
  - Klik **"Hanya di Dapodik"**.
  - Klik **"Cocok Identik"**.
  - Klik **"Semua Status"**.
- **Ekspektasi**: Tabel di bawahnya menyaring baris secara instan sesuai status yang dipilih tanpa memuat ulang halaman.

### Skenario 17: Tab Rekonsiliasi Dapodik — Komparasi Berdampingan & Penyorotan Beda Data
- **Langkah**: Amati kolom-kolom pada tabel komparasi rekonsiliasi:
  - Kolom **Nama Siswa**: menampilkan baris Lokal vs Dapodik.
  - Kolom **NISN & NIK**: menampilkan perbandingan nomor identitas.
  - Kolom **Rombongan Belajar**: menampilkan rombel sekolah lokal vs rombel pada berkas Dapodik.
  - Kolom **Rincian Diskrepansi**: rincian perbedaan atribut.
- **Ekspektasi**: Atribut yang berbeda (misalnya nama siswa atau rombel) disorot secara visual dengan latar belakang kuning/amber lembut sehingga operator dapat langsung melihat letak diskrepansi data.

### Skenario 18: Tab Rekonsiliasi Dapodik — Unduh Berkas Audit Rekonsiliasi CSV
- **Langkah**: Klik tombol **"Unduh Audit Rekonsiliasi (CSV)"** di toolbar atas hasil rekonsiliasi.
- **Ekspektasi**: Berkas bernama `audit_rekonsiliasi_dapodik_YYYYMMDD.csv` terunduh. Berkas tersebut memuat seluruh baris komparasi, status diskrepansi, dan catatan perbedaan atribut dengan format UTF-8 BOM yang dapat dibuka langsung di aplikasi spreadsheet.
