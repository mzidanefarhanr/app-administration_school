# Rencana Kerja Fitur — Subfase 13c (Rilis & Handoff Operasional / Release & Operational Handoff)

## 1. Ringkasan Eksekutif & Status Rencana

- **Subfase:** 13c — Rilis & Handoff Operasional (*Release v1.0.0 Publication, Deployment Instructions, Smoke Testing, Operational Handoff & Project Sign-Off*).
- **Milestone Induk:** Fase 13 — QA Akhir, Rilis dan Handoff Operasional (Subfase Penutup Milestone 13).
- **Usulan Keputusan Arsitektur:** **DEC-50 PROPOSED (D1–D8)**.
- **Baseline Kode:** `main` pada commit [`dcc607b`](https://github.com/mzidanefarhanr/app-administration_school/commit/dcc607b) setelah penerimaan resmi pemilik atas Subfase 13b (#1–#18).
- **Status Rencana:** **PLAN — AWAITING OWNER APPROVAL (DEC-50: D1–D8)**.
- **Status Implementasi:** **NOT STARTED** (tidak ada tindakan deployment atau mutasi produksi yang dijalankan sebelum pemilik menyetujui D1–D8).
- **Status Review Pemilik:** **MENUNGGU PERSETUJUAN RENCANA & PILIHAN TARGET INFRASTRUKTUR (D1–D8)**.

---

## 2. Penjelasan Konsep Rilis & Jawaban atas Pertanyaan Pemilik

### 2.1. Apa Makna "Rilis" pada Sistem Ini?
Secara rekayasa perangkat lunak standar dan arsitektur sistem sekolah, **"Rilis" (Release v1.0.0)** berarti sistem ini telah mencapai tingkat kematangan **Golden Master / Siap Digunakan Penuh (*Production-Ready*)**:
1. **Kelengkapan Fitur:** Seluruh 10 modul domain sekolah (Data Master, Akun & Hak Akses, Kesiswaan & Rombel, Kepegawaian PTK, Persuratan & Disposisi, Perizinan Pegawai, Kearsipan Dokumen, Administrasi Pembelajaran/Jadwal/Jurnal, Tugas Operasional, serta Antrean Background Jobs) telah selesai 100%, terintegrasi, dan bebas dari fitur bohongan (*zero mock/placeholders*).
2. **Kestabilan & Ketahanan:** Telah melalui QA integrasi menyeluruh (334 endpoint backend, 27 halaman rute antarmuka Vue SPA, audit wewenang 5 peran sekolah, audit nol kebocoran data pribadi PII, dan uji pemulihan bencana *restore drill* data dan berkas privat).
3. **Kesiapan Operasi:** Dilengkapi dengan SOP Runbook staf IT sekolah ([`docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md`](file:///D:/xampp-htdocs/app-administration_school/docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md)), rencana migrasi & rollback darurat ([`docs/RELEASE_MIGRATION_ROLLBACK_PLAN.md`](file:///D:/xampp-htdocs/app-administration_school/docs/RELEASE_MIGRATION_ROLLBACK_PLAN.md)), serta template konfigurasi pengerasan produksi ([`laravel-api/.env.production.example`](file:///D:/xampp-htdocs/app-administration_school/laravel-api/.env.production.example)).

### 2.2. Apakah Rilis Ini Otomatis Online ke Internet?
**TIDAK serta-merta otomatis online ke internet.** Keputusan lingkungan operasional (*deployment target*) sepenuhnya merupakan wewenang pemilik sesuai dengan kebutuhan dan ketersediaan infrastruktur sekolah ([DEC-02](file:///D:/xampp-htdocs/app-administration_school/docs/DECISIONS.md)). Terdapat dua pilihan utama:

1. **Pilihan 1: Rilis Server Lokal Sekolah (On-Premise / Local LAN Sekolah) — *Paling Umum & Hemat Biaya***
   - **Cara Kerja:** Aplikasi dipasang pada satu PC/Laptop Server di sekolah (menggunakan XAMPP atau Linux Server lokal sekolah).
   - **Akses Pengguna:** Seluruh guru, kepala sekolah, dan staf TU mengakses aplikasi melalui peramban web pada laptop/HP masing-masing yang terhubung ke jaringan Wi-Fi / LAN sekolah (misal membuka `http://192.168.1.100:8000` atau nama domain lokal `http://administrasi.sekolah.local`).
   - **Kelebihan:** 
     - **Biaya Rp 0:** Tidak perlu sewa domain atau server cloud/hosting bulanan.
     - **Keamanan Fisik:** Data sekolah 100% tersimpan di sekolah dan tidak bisa diakses peretas dari internet publik.
     - **Bebas Gangguan Internet:** Tetap bisa digunakan secara lancar walaupun koneksi internet sedang lambat atau mati.
   - **Keterbatasan:** Hanya dapat diakses saat berada di area jaringan sekolah (kecuali dipasang VPN sekolah).

2. **Pilihan 2: Rilis Server Online Publik (Cloud VPS / Web Hosting)**
   - **Cara Kerja:** Aplikasi dipasang pada server VPS Linux (seperti DigitalOcean, Linode, AWS, Biznet, Niagahoster, dll.) dengan nama domain resmi sekolah (contoh: `https://administrasi.sekolah.sch.id`).
   - **Akses Pengguna:** Dapat diakses dari mana saja (rumah, luar kota, perjalanan) melalui koneksi internet dengan pengamanan SSL/HTTPS.
   - **Kelebihan:** Sangat fleksibel bagi guru untuk mengisi jurnal atau pimpinan memeriksa persetujuan izin dari luar sekolah.
   - **Keterbatasan:** Membutuhkan biaya langganan server VPS/domain dan keahlian administrator IT untuk mengamankan server publik.

---

## 3. Usulan Keputusan Pemilik (DEC-50: D1–D8)

Berikut adalah 8 butir usulan keputusan teknis dan arsitektur untuk pelaksanaan Subfase 13c:

### D1 — Cakupan Subfase 13c & Batasan Non-Goals
- **Goals:**
  1. Menetapkan tag rilis Git resmi `v1.0.0` pada commit yang telah divalidasi penuh.
  2. Menyusun panduan deployment praktis (*Step-by-step Production Deployment Guide*) baik untuk skenario On-Premise LAN Sekolah maupun Cloud VPS Online.
  3. Menyusun paket serah terima operasional (*Operational Handoff Package*): kredensial inisialisasi, checklist smoke test pra-pakai, dan ringkasan arsitektur.
  4. Menyusun daftar *Known Issues, System Limitations, & Future Roadmap Recommendations* pasca-v1.0.
  5. Penutupan resmi Fase 13 dan tonggak akhir roadmap aplikasi.
- **Non-Goals:**
  - Tanpa modifikasi kode aplikasi atau skema basis data baru (*zero schema change*).
  - Agen tidak melakukan deployment liar ke server pihak ketiga tanpa otorisasi atau kredensial yang diserahkan pemilik secara sadar.
  - Apache dan MariaDB operasional port 3306 tetap terlindungi dan steril dari mutasi tak terencana.

### D2 — Penetapan Target Deployment Operasional Sekolah
- Pemilik menentukan target penerapan rilis:
  - **Opsi A (Rekomendasi Awal):** Rilis On-Premise LAN Sekolah (siap pakai di jaringan lokal sekolah).
  - **Opsi B:** Rilis Cloud VPS Online (jika sekolah telah memiliki server VPS dan domain aktif).
  - **Opsi C:** Rilis Dual-Mode (panduan dan bundel disiapkan untuk kedua lingkungan sehingga sekolah bebas beralih kapan saja).

### D3 — Penerbitan Tag Git Resmi `v1.0.0` & Bundel Rilis (*Release Bundle*)
- Menerbitkan Git Tag `v1.0.0` bertanda (*annotated release tag*) pada repositori resmi:
  `git tag -a v1.0.0 -m "Release v1.0.0: Sistem Administrasi Sekolah Siap Produksi"`
- Memastikan bundel aset frontend Vue SPA (`vue-app/dist`) telah terkompilasi bersih dan siap disajikan langsung oleh web server.

### D4 — Panduan Deployment & Inisialisasi Superadmin Lapangan
- Menyusun panduan operasional langkah-demi-langkah bagi teknisi sekolah untuk:
  1. Penyiapan berkas lingkungan `.env` dari `.env.production.example`.
  2. Pembuatan database produksi dan migrasi awal `php artisan migrate --force`.
  3. Pembuatan akun Superadmin perdana via CLI rahasia `php artisan app:bootstrap-admin`.
  4. Pengaturan backup harian otomatis pukul 02:00 WIB via Task Scheduler / Cron.

### D5 — Pre-Use Smoke Test Checklist Lapangan ($\le 10$ Detik)
- Menyediakan panduan uji cepat 5 langkah pasca-instalasi di server sekolah untuk memastikan:
  - Layanan web server dan API merespons dengan HTTP 200.
  - Halaman login antarmuka SPA tampil sempurna dan bebas dari galat konsol.
  - Akun superadmin dapat login dengan sukses.
  - Sesi dan token aman terbit dengan proteksi *cookie secure*.
  - Direktori penyimpanan privat siap menerima unggahan lampiran.

### D6 — Paket Handoff Operasional & Serah Terima Proyek
- Menyusun dokumen ringkasan serah terima ([`reports/feature-update/PHASE-13C-REPORT.md`](file:///D:/xampp-htdocs/app-administration_school/reports/feature-update/PHASE-13C-REPORT.md)) yang merangkum seluruh artefak proyek:
  - Rekapitulasi 13 Fase pengembangan (Fase 00 s.d. Fase 13).
  - Direktori berkas panduan penting (Runbook IT, Panduan Rollback, Kamus API, Arsitektur).
  - Informasi kontak dan tata kelola pemeliharaan sistem mandiri oleh sekolah.

### D7 — Daftar Known Issues & Saran Pengembangan Masa Depan
- Merangkum batasan wajar sistem v1.0 yang sengaja dipertahankan untuk stabilitas:
  - Ekspor/sinkronisasi Dapodik dilakukan melalui pertukaran berkas CSV lokal (tanpa integrasi API web langsung sesuai DEC-12).
  - Pengiriman notifikasi persuratan dan perizinan dilakukan via antarmuka web (tanpa modul SMS/WhatsApp gateway eksternal).
  - Penyusunan rekomendasi fitur opsional untuk pengembangan versi berikutnya (v1.1 / v2.0) jika sekolah menghendaki penambahan di masa depan.

### D8 — Protokol Review Akhir Pemilik (#1–#18) & Kepatuhan Port DEC-27
- Menyediakan 18 butir checklist review manual akhir pada [`reports/feature-update/PHASE-13C-MANUAL-REVIEW.md`](file:///D:/xampp-htdocs/app-administration_school/reports/feature-update/PHASE-13C-MANUAL-REVIEW.md).
- Menjamin port 18028 dan 14028 tetap bebas listener (`PORTS_18028_14028_FREE`).

---

## 4. Pertanyaan untuk Pemilik

Mohon konfirmasi pilihan dan persetujuan Anda:
1. **Target Rilis yang Diinginkan:**
   - Apakah Anda menginginkan **Opsi C (Dual-Mode: Siap Pakai di LAN Lokal Sekolah maupun di VPS Online)**? *(Rekomendasi)*
2. **Persetujuan Rencana DEC-50:**
   Silakan konfirmasi dengan menyatakan:
   > **"Saya setuju D1-D8"**
