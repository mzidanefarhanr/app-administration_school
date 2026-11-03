# Panduan Review Manual — Subfase 12c (Konsistensi Antarmenu, Form/Dialog, dan Penghapusan Menu Demo)

Subfase: 12c — Konsistensi Antarmenu, Form/Dialog, dan Penghapusan Menu Demo (DEC-47 IMPLEMENTED).  
Keputusan pemilik: **DEC-47 APPROVED** (D1–D10, 2026-11-03 WIB; pernyataan **"Saya setuju D1-D10"**).  
Laporan teknis lengkap: [`reports/feature-update/PHASE-12C-REPORT.md`](./PHASE-12C-REPORT.md); rencana kerja: [`reports/feature-update/PHASE-12C-PLAN.md`](./PHASE-12C-PLAN.md).  
Baseline induk: `main` pada commit `7d9cd3e`. Status teknis: **IMPLEMENTED & DIVERIFIKASI AGEN (2026-11-03)**.  
Status review pemilik: **AWAITING OWNER REVIEW (#1–#18, 2026-11-03 WIB)**.  

---

## Lingkungan UAT Terisolasi (`.uat-phase10`)

- **URL Frontend (SPA)**: `http://127.0.0.1:14028`
- **URL Backend (API)**: `http://127.0.0.1:18028`
- **Database Operasional MySQL**: Port `3306` **tidak disentuh sama sekali**.
- **Kepatuhan DEC-27**: Port `18028` dan `14028` dipastikan bebas listener sebelum dan sesudah pengujian.

Untuk memulai server UAT terisolasi pengujian, jalankan perintah pada terminal terpisah:
```bash
# Terminal 1 - Backend UAT Terisolasi (port 18028)
cd D:/xampp-htdocs/app-administration_school/laravel-api
php scripts/phase10-uat.php serve-backend

# Terminal 2 - Frontend UAT Terisolasi (port 14028)
cd D:/xampp-htdocs/app-administration_school/laravel-api
php scripts/phase10-uat.php serve-frontend
```

Atau jalankan automated UI consistency probe langsung tanpa membuka browser:
```bash
cd D:/xampp-htdocs/app-administration_school/laravel-api
php scripts/phase10-uat.php probe-ui-consistency
```

---

## Akun Pengujian Tersedia

| No | Username | Password | Peran Pengujian |
|:--:|---|---|---|
| 1 | `uat.admin` | `UatAdmin#2026` | Administrator Sistem (Akses Penuh Seluruh Modul & Pengaturan Akses) |
| 2 | `uat.kepsek` | `UatKepsek#2026` | Kepala Sekolah (Akses Manajerial, Persuratan & Perizinan) |
| 3 | `uat.guru` | `UatGuru#2026` | Guru Pengajar (Penugasan, Jadwal, & Jurnal Mengajar) |
| 4 | `uat.staff` | `UatStaff#2026` | Staf Tata Usaha / Operator Sekolah |

---

## Daftar Skenario Uji Manual (#1–#18)

| No | Skenario Uji | Prosedur Langkah Pengujian | Hasil yang Diharapkan |
|:--:|---|---|---|
| **1** | Start UAT Server & Isolasi Port | Jalankan `serve-backend` dan `serve-frontend` melalui `scripts/phase10-uat.php`. | Server backend aktif di `18028`, frontend aktif di `14028`. Port 3306 dan 80/443 tidak tersentuh. |
| **2** | UAT Automated UI Consistency Probe | Jalankan `php scripts/phase10-uat.php probe-ui-consistency`. | Seluruh 5 probe konsistensi UI lolos dengan status **ALL 5 UAT PHASE 12C UI CONSISTENCY PROBES PASSED**. |
| **3** | Pembersihan Menu Demo Template Sakai | Buka antarmuka aplikasi di browser, amati seluruh sidebar kiri. | Tidak ada lagi grup menu demo `UI Components`, `Pages`, `Hierarchy`, maupun `Get Started`. Sidebar 100% khusus administrasi sekolah. |
| **4** | Navigasi Grup Menu: Beranda | Periksa item di bawah grup `Beranda`. | Memuat sub-item berbahasa Indonesia: `Dasbor`, `Profil Sekolah`, dan `Pusat Tugas & Pengingat`. |
| **5** | Navigasi Grup Menu: Data Master | Periksa item di bawah grup `Data Master`. | Memuat sub-item berbahasa Indonesia: `Jenjang Pendidikan`, `Sekolah`, `Status Fase`, `Profesi`, dan `Jurusan Siswa`. |
| **6** | Navigasi Grup Menu: Akademik | Periksa item di bawah grup `Akademik`. | Memuat sub-item berbahasa Indonesia: `Rombongan Belajar`, `Kenaikan & Kelulusan`, dan `Tahun Ajaran`. |
| **7** | Navigasi Grup Menu: Kesiswaan & Kepegawaian | Periksa item di bawah grup `Kesiswaan & Kepegawaian`. | Memuat sub-item: `Data Siswa`, `Direktori Guru`, `Direktori Pegawai`, `Kualitas Data`, dan `Laporan Sekolah`. |
| **8** | Navigasi Grup Menu: Tata Persuratan | Periksa item di bawah grup `Tata Persuratan`. | Memuat sub-item: `Surat Masuk`, `Surat Keluar`, `Disposisi Surat`, dan `Pengaturan Surat`. |
| **9** | Navigasi Grup Menu: Perizinan Pegawai | Periksa item di bawah grup `Perizinan Pegawai`. | Memuat sub-item: `Pengajuan Izin & Cuti`. |
| **10** | Navigasi Grup Menu: Arsip Dokumen | Periksa item di bawah grup `Arsip Dokumen`. | Memuat sub-item: `Repositori Arsip`. |
| **11** | Navigasi Grup Menu: Administrasi Pembelajaran | Periksa item di bawah grup `Administrasi Pembelajaran`. | Memuat sub-item: `Penugasan & Jadwal`, `Jurnal Mengajar`, dan `Laporan Kepatuhan Jurnal`. |
| **12** | Navigasi Grup Menu: Pengaturan Akses | Periksa item di bawah grup `Pengaturan Akses`. | Memuat sub-item: `Manajemen Pengguna`, `Tipe Pengguna`, dan `Peran & Hak Akses`. |
| **13** | Sinkronisasi Breadcrumbs Bahasa Indonesia | Buka halaman Data Siswa, Pengaturan Surat, atau Jurnal Mengajar. Amati judul breadcrumb di atas konten. | Breadcrumbs menampilkan teks bahasa Indonesia yang serasi dengan nama menu navigasi aktif. |
| **14** | Penanganan Rute Yatim Piatu (Orphaned Routes) | Akses URL demo lama melalui address bar, misalnya `http://127.0.0.1:14028/uikit/formlayout` atau `/pages/crud`. | Pengguna secara anggun diarahkan ke halaman 404 Not Found yang rapi tanpa blank screen. |
| **15** | Format Tanggal, Jam WIB, dan Angka Numerik | Buka tabel Data Siswa atau Jurnal Mengajar, amati kolom tanggal dan jam. | Tanggal diformat rapi `DD/MM/YYYY` atau `D MMMM YYYY`, jam `HH:mm WIB`, dan nilai kosong ditampilkan dengan tanda strip (`-`). |
| **16** | Keterbacaan Mode Gelap (Dark Mode) | Klik tombol toggle Dark Mode pada topbar atau configurator. | Seluruh tabel, kartu statistik dasbor, modal dialog, dan teks input mempertahankan kontras tinggi dan mudah dibaca. |
| **17** | Responsivitas Layar Ponsel (Viewport 390px) | Buka browser DevTools, aktifkan mode responsif (390×844 px). | Tabel memiliki scroll horizontal mulus tanpa merusak navbar; modal dialog mengisi 95vw layar; tombol aksi memiliki target sentuh nyaman. |
| **18** | Kepatuhan DEC-27 (Shutdown Bersih) | Hentikan UAT server dengan `Ctrl+C`, periksa port `18028` dan `14028`. | Port 18028 dan 14028 kembali bebas listener. Database operasional dan web server utama tetap aman. |
