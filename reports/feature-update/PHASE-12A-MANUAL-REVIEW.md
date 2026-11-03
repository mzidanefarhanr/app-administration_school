# Panduan Review Manual — Subfase 12a (Pagination, Query Performance, dan Pengendalian Beban)

Subfase: 12a — Pagination, Query Performance, dan Pengendalian Beban (DEC-45 IMPLEMENTED).  
Keputusan pemilik: **DEC-45 APPROVED** (D1–D10, 2026-11-03 WIB; pernyataan **"Saya setuju D1-D10"**).  
Laporan teknis lengkap: [`reports/feature-update/PHASE-12A-REPORT.md`](./PHASE-12A-REPORT.md); rencana kerja: [`reports/feature-update/PHASE-12A-PLAN.md`](./PHASE-12A-PLAN.md).  
Baseline induk: `main` pada `9a7eeb6`. Status teknis: **IMPLEMENTED & DIVERIFIKASI AGEN (2026-11-03)**.  
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

Atau jalankan automated performance probe langsung tanpa membuka browser:
```bash
cd D:/xampp-htdocs/app-administration_school/laravel-api
php scripts/phase10-uat.php probe-performance
```

---

## Akun Pengujian Tersedia

| No | Username | Password | Peran Pengujian |
|:--:|---|---|---|
| 1 | `uat.admin` | `UatAdmin#2026` | Administrator Sistem (Akses Penuh Seluruh Modul) |
| 2 | `uat.kepsek` | `UatKepsek#2026` | Kepala Sekolah (Akses Manajerial & Disposisi Surat) |
| 3 | `uat.guru` | `UatGuru#2026` | Guru Pengajar (Jadwal & Jurnal Mengajar) |
| 4 | `uat.staff` | `UatStaff#2026` | Staf Tata Usaha / Operator Sekolah |

---

## Daftar Skenario Uji Manual (#1–#18)

| No | Skenario Uji | Prosedur Langkah Pengujian | Hasil yang Diharapkan |
|:--:|---|---|---|
| **1** | Start UAT Server & Isolasi Port | Jalankan `serve-backend` dan `serve-frontend` melalui `scripts/phase10-uat.php`. | Server backend aktif di `18028`, frontend aktif di `14028`. Port 3306 dan 80/443 tidak tersentuh. |
| **2** | Kesiswaan: Batas Paginasi 100 Item | Buka menu Siswa, ubah dropdown ukuran halaman ke nilai maksimal (100). | Data tabel termuat dengan sukses (HTTP 200 OK) sebanyak hingga 100 baris. |
| **3** | Kesiswaan: Penolakan Keras `per_page=101` | Melalui Network DevTools, request `/api/students?per_page=101`. | Server secara ketat menolak request dengan status **422 Unprocessable Entity**. |
| **4** | Kesiswaan: Bebas Masalah N+1 Query | Periksa tab Network saat memuat daftar siswa. | Kueri relasi rombel aktif dan data siswa termuat dalam satu bundle tanpa request berulang per baris. |
| **5** | Kesiswaan: Debounce Input Pencarian | Buka daftar siswa, ketik nama di kotak pencarian secara cepat. | Request pencarian tertunda 300 ms dan baru dikirim setelah pengetikan berhenti. |
| **6** | Kepegawaian: Paginasi Server-side | Buka menu Direktori Pegawai, ubah ukuran halaman (10, 20, 50, 100). | Paginasi berpindah secara instan tanpa glitch atau lonjakan penggunaan memori. |
| **7** | Kepegawaian: Eager Loading Profil Pegawai | Periksa rincian baris pegawai pada tabel kepegawaian. | Status kepegawaian dan jenis pegawai langsung tersedia tanpa delay lazy-loading tambahan. |
| **8** | Jadwal Pelajaran: Paginasi Server-side | Buka menu Jadwal Pelajaran, navigasi dari halaman 1 ke halaman berikutnya. | Perpindahan halaman berlangsung cepat (<100 ms) dengan metadata paginasi yang valid. |
| **9** | Jurnal Mengajar: Filter Tanggal Terindeks | Buka Jurnal Mengajar, filter berdasarkan rentang tanggal. | Hasil filter muncul instan memanfaatkan indeks komposit `(session_date, teaching_schedule_id)`. |
| **10** | Persuratan Masuk: Paginasi & Indeks | Buka Surat Masuk, navigasi daftar surat. | Daftar surat masuk termuat efisien memanfaatkan indeks `(direction, status, workflow_status)`. |
| **11** | Persuratan Keluar: Workflow Eager Loading | Buka Surat Keluar, periksa status persetujuan surat. | Riwayat peninjauan dan status persetujuan termuat tanpa memicu kueri N+1. |
| **12** | Perizinan: Filter Subjek & Indeks Komposit | Buka menu Perizinan, filter izin berdasarkan tipe subjek (siswa/pegawai). | Kueri perizinan berjalan ringan memanfaatkan indeks `(subject_type, status, start_date)`. |
| **13** | Arsip Dokumen: Eager Loading Versi Berkas | Buka Repositori Arsip Dokumen. | Versi berkas aktif terkini (`latestVersion`) dan metadata kategori tampil utuh tanpa query terpisah. |
| **14** | Pusat Tugas: Paginasi Cepat Seluruh Tab | Buka menu `/public/tasks`, berpindah antar tab (Semua, Sistem, Manual, Riwayat). | Peralihan tab responsif (<50 ms) dengan pembatasan kuota kueri yang terukur. |
| **15** | Pusat Tugas: Debounce Pencarian 300 ms | Ketik kata kunci pada kotak pencarian tugas operasional. | Debounce 300 ms aktif dan mencegah pengiriman request berulang per ketukan tombol. |
| **16** | Dashboard: Kuota Kueri Pending Tasks | Login sebagai `uat.admin`, buka Dashboard. | Widget pending-tasks termuat cepat dengan kuota kueri SQL $\le 10$ (aktual 6 kueri). |
| **17** | Ekspor CSV: Memory-Safe Streaming | Buka menu Siswa / Mutasi, klik tombol Ekspor CSV. | Berkas CSV terunduh cepat dengan format UTF-8 BOM (`\xEF\xBB\xBF`) dan sanitasi formula aman. |
| **18** | Kepatuhan DEC-27 (Shutdown Bersih) | Tekan `Ctrl+C` pada kedua terminal UAT, jalankan `probe-performance` untuk verifikasi akhir. | Kedua port 18028 dan 14028 kembali bebas listener. Lingkungan operasional tetap utuh. |
