# Panduan Review Manual — Subfase 13a (QA Integrasi dan UAT)

Subfase: 13a — QA Integrasi dan UAT (DEC-48 IMPLEMENTED).  
Keputusan pemilik: **DEC-48 APPROVED** (D1–D10, 2026-11-03 WIB; pernyataan **"Saya setuju D1-D10"**).  
Laporan teknis lengkap: [`reports/feature-update/PHASE-13A-REPORT.md`](./PHASE-13A-REPORT.md); rencana kerja: [`reports/feature-update/PHASE-13A-PLAN.md`](./PHASE-13A-PLAN.md).  
Baseline induk: `main` pada commit `6d79fdd`. Status teknis: **IMPLEMENTED & DIVERIFIKASI AGEN (2026-11-03)**.  
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

Atau jalankan automated integration probe langsung tanpa membuka browser:
```bash
cd D:/xampp-htdocs/app-administration_school/laravel-api
php scripts/phase10-uat.php probe-integration
```

---

## Akun Pengujian Tersedia (5 Peran Kanonikal)

| No | Username | Password | Peran Pengujian |
|:--:|---|---|---|
| 1 | `uat.admin` | `UatAdmin#2026` | Administrator Sistem (Akses Penuh Pengaturan Pengguna, Peran, & Backup) |
| 2 | `uat.operator` | `UatStaff#2026` | Operator Sekolah (Pengelolaan Kesiswaan, Persuratan, Arsip, & Jadwal) |
| 3 | `uat.kepsek` | `UatKepsek#2026` | Kepala Sekolah (Akses Manajerial, Persetujuan Surat/Izin, Dasbor Eksekutif) |
| 4 | `uat.waka` | `UatWaka#2026` | Wakil Kepala Sekolah / Kesiswaan (Penugasan & Jadwal Pelajaran) |
| 5 | `uat.guru` | `UatGuru#2026` | Guru Pengajar (Self-Service Jadwal, Jurnal Mengajar, & Pengajuan Izin) |

---

## Daftar Skenario Uji Manual (#1–#18)

| No | Skenario Uji | Prosedur Langkah Pengujian | Hasil yang Diharapkan |
|:--:|---|---|---|
| **1** | Start UAT Server & Isolasi Port | Jalankan `serve-backend` dan `serve-frontend` melalui `scripts/phase10-uat.php`. | Server backend aktif di `18028`, frontend aktif di `14028`. Port 3306 dan 80/443 tidak tersentuh. |
| **2** | UAT Automated Integration Probe | Jalankan `php scripts/phase10-uat.php probe-integration`. | Seluruh 5 probe integrasi lolos dengan status **ALL 5 UAT SUBFASE 13A INTEGRATION PROBES PASSED**. |
| **3** | Automated Integration Test Suite | Jalankan `php artisan test --filter=EndToEndIntegrationTest`. | Seluruh 6 test case integrasi lintas modul berhasil (**6 passed, 67 assertions**). |
| **4** | Akses Admin Sistem: Manajemen Akun & Backup | Login sebagai `uat.admin`. Buka menu `Pengaturan Akses -> Manajemen Pengguna` dan periksa status cadangan. | Administrator dapat melihat daftar akun pengguna, peran, audit aktivitas, dan status cadangan data. |
| **5** | Pembatasan Wewenang Admin Sistem | Sebagai `uat.admin`, coba akses persetujuan cuti pegawai atau penandatanganan surat keluar. | Akses ditolak (`403 Forbidden` / tombol aksi persetujuan tidak tersedia bagi peran admin). |
| **6** | Akses Operator Sekolah: Kesiswaan & Tata Usaha | Login sebagai `uat.operator`. Buka menu `Kesiswaan -> Data Siswa` dan `Tata Persuratan -> Surat Keluar`. | Operator dapat mendaftarkan siswa baru, menempatkan rombel, dan membuat draf surat keluar. |
| **7** | Pembatasan Wewenang Operator Sekolah | Sebagai `uat.operator`, coba navigasi langsung ke menu `Pengaturan Akses -> Manajemen Pengguna` atau endpoint backup. | Pengguna dicegah atau dialihkan ke halaman Akses Ditolak (`/auth/access`). |
| **8** | Akses Kepala Sekolah: Persetujuan & Monitoring | Login sebagai `uat.kepsek`. Buka Dasbor Pimpinan, periksa menu persetujuan izin dan surat. | Kepala Sekolah dapat menyetujui izin pegawai, menandatangani surat resmi, dan melihat ringkasan audit. |
| **9** | Pembatasan Wewenang Kepala Sekolah | Sebagai `uat.kepsek`, periksa menu teknis database atau pengelolaan akun admin. | Menu pengelolaan pengguna teknis dan backup tidak tersedia di sidebar. |
| **10** | Akses Waka Kurikulum: Pembelajaran | Login sebagai `uat.waka`. Buka menu `Administrasi Pembelajaran -> Penugasan & Jadwal`. | Waka dapat mengatur penugasan guru pengampu dan menyusun jadwal mingguan bebas bentrok. |
| **11** | Pembatasan Wewenang Waka Kurikulum | Sebagai `uat.waka`, coba buka log audit sistem atau menu backup. | Wewenang ditolak (`403 Forbidden`). |
| **12** | Akses Guru: Self-Service Pembelajaran | Login sebagai `uat.guru`. Buka menu `Administrasi Pembelajaran -> Jurnal Mengajar`. | Guru dapat melihat jadwal mengajar mandiri dan mengisi jurnal pembelajaran hari ini (dalam toleransi 7 hari WIB). |
| **13** | Pembatasan Wewenang Guru | Sebagai `uat.guru`, coba buka direktori siswa lengkap untuk edit data, atau buat surat keluar. | Guru tidak memiliki akses edit direktori siswa atau surat keluar sekolah (`403 Forbidden`). |
| **14** | Alur E2E Kesiswaan ke Arsip Dokumen | Pendaftaran siswa -> Penempatan Rombel -> Pembuatan draf Surat Keterangan Siswa Aktif -> Penerbitan & Pengarsipan PDF ke Repositori Arsip. | Dokumen tersimpan di modul Arsip dengan checksum SHA-256 dan versi tercatat. |
| **15** | Alur E2E Jadwal ke Jurnal & Rekap Kepatuhan | Penugasan guru -> Jadwal mingguan bebas bentrok -> Pengisian jurnal harian -> Buka Laporan Kepatuhan Jurnal Pimpinan. | Sesi terdeteksi telah terisi (0 sesi terlambat, persentase kepatuhan 100%). |
| **16** | Alur E2E Pengajuan Izin ke Approval Pimpinan | Guru mengajukan izin sakit -> Notifikasi tugas sistem muncul di Pusat Tugas Pimpinan -> Kepala Sekolah menyetujui izin. | Permohonan izin berstatus disetujui, guru tidak dapat menyetujui izinnya sendiri (*self-approval prevention*). |
| **17** | Tampilan Multi-Viewport & Tema Light/Dark | Buka aplikasi pada Desktop (1440px), Tablet (768px), dan Layar Ponsel (390px) serta uji tombol toggle Dark Mode. | Tata letak rapi, tabel dapat di-scroll horizontal tanpa merusak topbar, modal dialog proporsional, dan teks berkontras tinggi. |
| **18** | Kepatuhan DEC-27 (Shutdown Bersih) | Hentikan UAT server dengan `Ctrl+C`, periksa port `18028` dan `14028`. | Port 18028 dan 14028 kembali bebas listener. Database operasional dan web server utama tetap aman. |
