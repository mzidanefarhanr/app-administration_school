# Panduan Review Manual Pemilik — Subfase 13b (Kesiapan Rilis / Release Readiness)

Panduan ini berisi **18 butir checklist pengujian manual (#1–#18)** yang disusun khusus untuk mempermudah pemilik memverifikasi secara langsung seluruh hasil kesiapan operasional rilis (**Subfase 13b**) sebelum masuk ke gerbang deployment produksi.

---

## Prasyarat & Lingkungan Verifikasi

- **Repositori:** `D:/xampp-htdocs/app-administration_school`
- **Baseline Kode:** `main` pada commit [`303b624`](https://github.com/mzidanefarhanr/app-administration_school/commit/303b624)
- **Kepatuhan DEC-27:** Port `18028` dan `14028` dipastikan bebas listener saat tidak dalam pengujian aktif (`PORTS_18028_14028_FREE`).
- **Apache (Port 80/443) & MariaDB (Port 3306):** Tetap steril dan tidak tersentuh.

---

## Perintah Verifikasi Cepat (Automated Probe)

Sebelum melakukan peninjauan dokumen dan checklist terperinci, pemilik dapat menjalankan probe verifikasi otomatis kesiapan rilis langsung dari terminal:
```bash
cd D:/xampp-htdocs/app-administration_school/laravel-api
php scripts/phase10-uat.php probe-release-readiness
```
*Ekspektasi Output:* Seluruh 5 probe dinyatakan **PASS** dengan ringkasan:
`=== ALL 5 UAT SUBFASE 13B RELEASE READINESS PROBES PASSED SUCCESSFULLY ===`

---

## 18 Butir Checklist Review Manual (#1–#18)

| No | Kategori | Butir Pengujian | Langkah Verifikasi Pemilik | Kriteria Sukses (PASS) |
|:---:|---|---|---|---|
| **#1** | **Database Readiness** | Konsistensi data 10 domain modul sekolah | Jalankan probe UAT atau periksa Probe 2 pada `phase10-uat.php`. | Seluruh 10 modul (Master, Akun, Siswa, Guru, Surat, Izin, Arsip, Pembelajaran, Tugas, Antrean) terdaftar lengkap tanpa error constraint SQL. |
| **#2** | **Private Storage** | Struktur direktori berkas lampiran privat | Periksa subdirektori pada `storage/app/attachments/` (letters, leaves, archives, letter-settings, letter-documents). | Seluruh subdirektori berkas privat tersedia dan berada di luar web root publik. |
| **#3** | **Data Integrity** | Validasi integritas hash SHA-256 berkas | Amati eksekusi Probe 1 pada `probe-release-readiness`. | Berkas lampiran diverifikasi kecocokan hash SHA-256 antara metadata dan berkas fisik (`MATCH`). |
| **#4** | **Production Hardening** | Ketersediaan template konfigurasi produksi | Buka berkas [`laravel-api/.env.production.example`](file:///D:/xampp-htdocs/app-administration_school/laravel-api/.env.production.example). | Berkas template konfigurasi produksi tersedia lengkap dengan panduan keamanan. |
| **#5** | **Environment Guard** | Mode debug nonaktif (`APP_DEBUG=false`) | Periksa baris `APP_DEBUG=false` dan `APP_ENV=production` pada template `.env.production.example`. | Mode debug dipastikan nonaktif guna mencegah kebocoran informasi teknis server. |
| **#6** | **Session Security** | Hardening keamanan cookie sesi HTTPS | Periksa pengaturan `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, dan `SESSION_SAME_SITE=lax`. | Proteksi sesi HTTPS terkonfigurasi untuk mencegah serangan *session hijacking* dan *XSS/CSRF*. |
| **#7** | **File Permissions** | Panduan izin direktori dan kepemilikan | Buka bab 2 pada [`docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md`](file:///D:/xampp-htdocs/app-administration_school/docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md). | Terdapat petunjuk eksplisit `chmod 600 .env` dan `chmod 775 storage bootstrap/cache` dengan kepemilikan `www-data`. |
| **#8** | **Migration Sequence** | Rencana 10 langkah migrasi produksi (*Golden Path*) | Buka Bab 3 pada [`docs/RELEASE_MIGRATION_ROLLBACK_PLAN.md`](file:///D:/xampp-htdocs/app-administration_school/docs/RELEASE_MIGRATION_ROLLBACK_PLAN.md). | Terdapat urutan 10 langkah migrasi aman mulai dari mode maintenance, snapshot backup, hingga verifikasi go-live. |
| **#9** | **Emergency Rollback** | Prosedur 9 langkah pemulihan darurat (*Rollback SOP*) | Buka Bab 4 pada [`docs/RELEASE_MIGRATION_ROLLBACK_PLAN.md`](file:///D:/xampp-htdocs/app-administration_school/docs/RELEASE_MIGRATION_ROLLBACK_PLAN.md). | Tersedia mitigasi pemulihan darurat cepat ($\le 15$ menit) untuk memulihkan database dan berkas dari snapshot pra-migrasi. |
| **#10** | **Queue Worker** | Kesiapan pekerja antrean latar belakang | Periksa Probe 4 pada `probe-release-readiness` dan tabel antrean database. | Tabel `jobs`, `job_batches`, dan `job_execution_statuses` terkonfirmasi aktif di skema database. |
| **#11** | **Task Scheduler** | Penjadwalan Cron harian 02:00 WIB | Periksa konfigurasi scheduler pada `config/backup.php` dan Bab 4 Runbook. | Perintah `app:backup-run` dijadwalkan otomatis pukul 02:00 WIB dan `app:backup-prune` pukul 02:30 WIB. |
| **#12** | **Server Prerequisites** | Prasyarat PHP 8.2+ dan konfigurasi web server | Buka Bab 2.1 & 2.2 pada [`docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md`](file:///D:/xampp-htdocs/app-administration_school/docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md). | Tertera spesifikasi ekstensi PHP wajib (AES-256 ZIP) dan aturan blokir akses berkas tersembunyi (`.env`, `.git`). |
| **#13** | **Bootstrap Admin** | Inisialisasi superadmin aman perdana | Periksa keberadaan perintah `php artisan app:bootstrap-admin` dan Probe 5. | Perintah CLI bootstrap admin tersedia, tervalidasi di kontainer, dan menerima kata sandi via *secret prompt*. |
| **#14** | **Daily/Weekly SOP** | Prosedur operasi harian & mingguan staf IT | Buka Bab 3 pada [`docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md`](file:///D:/xampp-htdocs/app-administration_school/docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md). | SOP harian (pusat tugas, log error correlation ID, status backup) dan mingguan (rekap jurnal, audit trail) terdokumentasi rapi. |
| **#15** | **Academic Rollover** | SOP pergantian tahun ajaran baru (*Rollover*) | Buka Bab 5 pada [`docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md`](file:///D:/xampp-htdocs/app-administration_school/docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md). | Terdapat panduan bertahap: penguncian tahun lama, pembuatan tahun baru, kenaikan kelas, dan penempatan rombel baru. |
| **#16** | **Troubleshooting** | Solusi mitigasi kendala operasional (FAQ) | Buka Bab 6 pada [`docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md`](file:///D:/xampp-htdocs/app-administration_school/docs/SCHOOL_IT_OPERATIONAL_RUNBOOK.md). | Panduan pemulihan queue macet, lock file `.backup.lock`, token CSRF 419 expired, dan disk space alert terdokumentasi. |
| **#17** | **UAT Probe Execution** | Kelulusan penuh UAT probe kesiapan rilis | Jalankan `php scripts/phase10-uat.php probe-release-readiness`. | Seluruh 5 probe UAT dinyatakan **PASS (5/5 PASSED)** tanpa galat runtime. |
| **#18** | **Port Isolation** | Kepatuhan port DEC-27 dan integritas Apache/MariaDB | Jalankan perintah `netstat -ano \| findstr "18028 14028"` di terminal PowerShell. | Port 18028 dan 14028 terverifikasi **bebas listener** (`PORTS_18028_14028_FREE`), Apache 80/443 & MariaDB 3306 tidak terganggu. |

---

## Petunjuk Pengesahan Pemilik

Jika seluruh butir pengujian di atas telah sesuai dengan harapan Anda, mohon sampaikan persetujuan Anda dengan format:
> **"Pengujian nomor 1-18 sudah berhasil dan saya approved. Buatkan plan untuk lanjutan tahap/fase berikutnya."**
