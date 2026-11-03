# Panduan Review Manual Pemilik — Subfase 13c (Rilis & Handoff Operasional / Release & Operational Handoff)

Panduan ini berisi **18 butir checklist pengujian manual akhir (#1–#18)** yang disusun khusus untuk mempermudah pemilik memverifikasi secara langsung seluruh paket rilis resmi **v1.0.0**, panduan instalasi lapangan, catatan rilis, dan penyerahan operasional (*operational handoff*) sebelum menandatangani penyelesaian akhir proyek.

---

## Prasyarat & Lingkungan Verifikasi

- **Repositori:** `D:/xampp-htdocs/app-administration_school`
- **Baseline Kode:** `main` pada commit [`2a4abc7`](https://github.com/mzidanefarhanr/app-administration_school/commit/2a4abc7)
- **Tag Rilis:** `v1.0.0`
- **Kepatuhan DEC-27:** Port `18028` dan `14028` dipastikan bebas listener saat tidak dalam pengujian aktif (`PORTS_18028_14028_FREE`).
- **Apache (Port 80/443) & MariaDB (Port 3306):** Tetap steril dan tidak tersentuh.

---

## 18 Butir Checklist Review Manual Akhir (#1–#18)

| No | Kategori | Butir Pengujian | Langkah Verifikasi Pemilik | Kriteria Sukses (PASS) |
|:---:|---|---|---|---|
| **#1** | **Git Release Tag** | Keberadaan tag rilis Git resmi `v1.0.0` | Jalankan `git tag -l` di terminal repositori. | Tertera tag resmi bertanda **`v1.0.0`** pada riwayat commit stabil. |
| **#2** | **Frontend Assets** | Kesiapan bundel aset statis terkompilasi | Periksa direktori `vue-app/dist/` (terdapat `index.html`, `favicon.ico`, dan direktori `assets/`). | Bundel frontend SPA terkompilasi bersih, siap disajikan oleh web server tanpa butuh Node.js di server produksi. |
| **#3** | **LAN Deployment** | Panduan deployment jaringan lokal sekolah | Buka Bagian A pada [`docs/DEPLOYMENT_GUIDE.md`](file:///D:/xampp-htdocs/app-administration_school/docs/DEPLOYMENT_GUIDE.md). | Terdapat panduan instalasi XAMPP/Windows Server, IP statis lokal, VirtualHost Apache, dan Task Scheduler. |
| **#4** | **Cloud Deployment** | Panduan deployment server online (Cloud VPS) | Buka Bagian B pada [`docs/DEPLOYMENT_GUIDE.md`](file:///D:/xampp-htdocs/app-administration_school/docs/DEPLOYMENT_GUIDE.md). | Terdapat panduan Ubuntu 22.04, Nginx, SSL Let's Encrypt, Supervisor queue worker, dan Crontab Linux. |
| **#5** | **Common Setup** | Langkah inisialisasi bersama & konfigurasi `.env` | Buka Bagian C pada [`docs/DEPLOYMENT_GUIDE.md`](file:///D:/xampp-htdocs/app-administration_school/docs/DEPLOYMENT_GUIDE.md). | Langkah penyalinan `.env`, `key:generate`, `migrate --force`, dan seeder peran terstruktur rapi. |
| **#6** | **Bootstrap Admin** | Inisialisasi superadmin aman perdana | Periksa perintah `php artisan app:bootstrap-admin` pada panduan. | Perintah CLI bootstrap admin tersedia, tervalidasi di kontainer, dan meminta kata sandi via *secret prompt*. |
| **#7** | **Smoke Test** | Pre-use smoke test checklist lapangan ($\le 10$ detik) | Buka Bagian D pada [`docs/DEPLOYMENT_GUIDE.md`](file:///D:/xampp-htdocs/app-administration_school/docs/DEPLOYMENT_GUIDE.md). | Terdapat 5 uji cepat untuk memverifikasi DB, status backup, respons API, login web, dan pencadangan perdana. |
| **#8** | **Release Notes** | Kelengkapan Catatan Rilis Resmi v1.0.0 | Buka berkas [`docs/RELEASE_NOTES_v1.0.md`](file:///D:/xampp-htdocs/app-administration_school/docs/RELEASE_NOTES_v1.0.md). | Catatan rilis v1.0.0 memuat ringkasan eksekutif, cakupan 10 modul siap pakai, dan fitur keamanan. |
| **#9** | **Module Scope** | Ketersediaan 10 domain modul sekolah siap pakai | Periksa Bab 2 pada [`docs/RELEASE_NOTES_v1.0.md`](file:///D:/xampp-htdocs/app-administration_school/docs/RELEASE_NOTES_v1.0.md). | 10 modul (Master, Akun, Siswa, Guru, Surat, Izin, Arsip, Pembelajaran, Tugas, Antrean) siap dioperasikan. |
| **#10** | **System Limits** | Transparansi batasan sistem & known issues | Periksa Bab 4 pada [`docs/RELEASE_NOTES_v1.0.md`](file:///D:/xampp-htdocs/app-administration_school/docs/RELEASE_NOTES_v1.0.md). | Batasan wajar (rekonsiliasi Dapodik via CSV lokal semi-otomatis & notifikasi in-app) dicatat transparan. |
| **#11** | **Future Roadmap** | Rekomendasi pengembangan versi masa depan (v1.1+) | Periksa Bab 5 pada [`docs/RELEASE_NOTES_v1.0.md`](file:///D:/xampp-htdocs/app-administration_school/docs/RELEASE_NOTES_v1.0.md). | Tersedia saran modul pengembangan masa depan (Portal Ortu/Siswa v1.1, WA Bot v1.2, Rapor Digital v2.0). |
| **#12** | **Project Lifecycle** | Rekapitulasi paripurna seluruh 14 fase proyek | Buka Bab 4 pada [`reports/feature-update/PHASE-13C-REPORT.md`](file:///D:/xampp-htdocs/app-administration_school/reports/feature-update/PHASE-13C-REPORT.md). | Seluruh 14 fase (Fase 00 s.d. Fase 13) tercatat **SELESAI LENGKAP & DITUTUP (100% DONE)**. |
| **#13** | **Handoff Inventory** | Inventaris dokumen operasional serah terima | Buka Bab 5 pada [`reports/feature-update/PHASE-13C-REPORT.md`](file:///D:/xampp-htdocs/app-administration_school/reports/feature-update/PHASE-13C-REPORT.md). | Tersedia tautan lengkap ke 8 dokumen kunci (Runbook, Rollback SOP, Kamus API, Matriks Peran, dsb.). |
| **#14** | **Data Security** | Kebijakan proteksi privasi & Zero PII Leak | Periksa Bab 3 pada [`docs/RELEASE_NOTES_v1.0.md`](file:///D:/xampp-htdocs/app-administration_school/docs/RELEASE_NOTES_v1.0.md). | Sensor otomatis NIK (`[REDACTED_NIK]`) dan password aktif di Monolog; storage berkas bersifat privat. |
| **#15** | **Observability** | Penjejakan galat terpadu `X-Correlation-ID` | Periksa Bab 3 pada [`docs/RELEASE_NOTES_v1.0.md`](file:///D:/xampp-htdocs/app-administration_school/docs/RELEASE_NOTES_v1.0.md). | Header `X-Correlation-ID` terpropagasi ke antarmuka dan log server untuk memudahkan penanganan insiden. |
| **#16** | **Disaster Recovery** | Prosedur pencadangan dan pemulihan bencana | Buka [`docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md`](file:///D:/xampp-htdocs/app-administration_school/docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md) Bab 4. | Backup harian 02:00 WIB terenkripsi AES-256, rotasi 7 harian + 4 mingguan, dan restore drill teruji. |
| **#17** | **Quality Suite** | Validitas rangkaian pengujian otomatis | Jalankan `php scripts/phase10-uat.php probe-release-readiness` di `laravel-api`. | Seluruh 5 probe UAT kesiapan rilis berstatus **PASS (5/5 PASSED)** tanpa galat runtime. |
| **#18** | **Port Isolation** | Kepatuhan port DEC-27 & integritas Apache/MariaDB | Jalankan `netstat -ano \| findstr "18028 14028"` di terminal PowerShell. | Port 18028 dan 14028 terverifikasi **bebas listener** (`PORTS_18028_14028_FREE`), Apache 80/443 & MariaDB 3306 tidak terganggu. |

---

## Petunjuk Pengesahan Akhir Pemilik (*Final Project Sign-Off*)

Jika seluruh 18 butir pengujian manual akhir di atas telah Anda verifikasi dan disetujui, silakan sampaikan pengesahan akhir proyek dengan format:
> **"Pengujian nomor 1-18 sudah berhasil dan saya approved. Rilis v1.0.0 resmi diterima dan proyek selesai."**
