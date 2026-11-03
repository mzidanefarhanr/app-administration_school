# Rencana Kerja Fitur — Subfase 13b (Kesiapan Rilis / Release Readiness)

## 1. Ringkasan Eksekutif & Status Rencana

- **Subfase:** 13b — Kesiapan Rilis (*Release Readiness, Full Data & Private Storage Backup, Candidate Restore Drill, Production Environment Audit, Rollback Plan, Operational Runbook & Smoke Test Suite*).
- **Milestone Induk:** Fase 13 — QA Akhir, Rilis dan Handoff Operasional.
- **Keputusan Arsitektur:** **DEC-49 APPROVED (D1–D10) & IMPLEMENTED (2026-11-03 WIB)** via instruksi pemilik: *"Saya setuju D1-D10"*.
- **Baseline Kode:** `main` pada commit [`303b624`](https://github.com/mzidanefarhanr/app-administration_school/commit/303b624).
- **Status Rencana:** **APPROVED (2026-11-03 WIB)**.
- **Status Implementasi:** **IMPLEMENTED & DIVERIFIKASI AGEN (2026-11-03 WIB)**. Seluruh tahapan S1–S8 selesai. Laporan di `reports/feature-update/PHASE-13B-REPORT.md`.
- **Status Review Pemilik:** **APPROVED (#1–#18) & CLOSED (2026-11-03 WIB)** via instruksi pemilik: *"Pengujian nomor 1-18 sudah berhasil dan saya approved."*

---

## 2. Konteks & Latar Belakang Kebutuhan

Dengan diselesaikannya dan disetujuinya **Subfase 13a (QA Integrasi dan UAT)** oleh pemilik:
1. Seluruh 334 endpoint backend dan 27 rute antarmuka frontend telah diverifikasi bebas dari rute yatim piatu (*orphaned routes*).
2. Matriks hak akses 5 peran kanonikal sekolah (`admin-sistem`, `operator-sekolah`, `kepala-sekolah`, `wakil-kesiswaan`, `guru`) telah divalidasi dengan disiplin wewenang yang tegas (`403 Forbidden`).
3. Empat skenario alur kerja integrasi lintas modul telah divalidasi secara end-to-end.
4. Audit zero PII leak membuktikan ketiadaan kebocoran data pribadi (NIK, password, token, telepon) pada catatan log Monolog dan respons API publik.
5. Suite pengujian integrasi otomatis `EndToEndIntegrationTest.php` (6/6 pass) dan probe UAT `probe-integration` (5/5 pass) telah terintegrasi di lingkungan uji terisolasi.

Sebelum melangkah ke tahap penerbitan rilis kandidat operasional (*Deployment* — Subfase 13c), sistem harus melalui gerbang audit kesiapan rilis (**Subfase 13b — Kesiapan Rilis / Release Readiness**):
- **Kelengkapan Pencadangan (Full Backup):** Setelah modul berkembang pesat dari Fase 01 hingga Fase 12 (terutama penambahan berkas PDF surat dinas, lampiran perizinan, dan versi arsip dokumen immutable), pencadangan tidak boleh hanya mencakup database SQL, melainkan wajib mencakup seluruh direktori storage privat beserta validasi checksum SHA-256.
- **Simulasi Pemulihan Kandidat Rilis (Restore Drill):** Menjamin secara empiris bahwa berkas cadangan dapat dipulihkan 100% ke basis data dan media penyimpanan kosong tanpa kehilangan integritas relasional data (*zero data loss*).
- **Audit Lingkungan & Hardening Konfigurasi Produksi:** Memastikan kesiapan berkas lingkungan `.env.production`, penutupan mode debug (`APP_DEBUG=false`), penerapan HTTPS & cookie secure, serta pengetatan izin berkas (*file permissions*).
- **Prosedur Migrasi & Rencana Rollback Terperinci:** Menyusun prosedur migrasi bertahap dan mitigasi darurat pemulihan (*rollback plan*) jika terjadi kegagalan saat proses rilis berlangsung.
- **Runbook & SOP Operasional Sekolah:** Menyusun panduan operasional definitif bagi staf IT sekolah: mulai dari inisialisasi superadmin, pemeliharaan berkala, pencadangan harian otomatis pukul 02:00 WIB, hingga tata kelola pergantian tahun ajaran (*academic year rollover*).
- **Pre-Release Smoke Test Suite & Probe UAT:** Menyediakan alat uji kesehatan cepat (*health-check*) yang dapat memvalidasi kesiapan operasional server dalam hitungan detik.

---

## 3. Usulan Keputusan Pemilik (DEC-49: D1–D10)

Berikut adalah 10 butir usulan keputusan teknis dan arsitektur untuk Subfase 13b:

### D1 — Cakupan Subfase 13b & Batasan Non-Goals
Kerjakan **Subfase 13b: Kesiapan Rilis (Release Readiness)** saja.
- **Goals:**
  1. Verifikasi kelengkapan cadangan basis data SQL dan seluruh media penyimpanan berkas privat (`storage/app/private`).
  2. Eksekusi simulasi pemulihan penuh (*restore drill*) pada database uji terisolasi kandidat rilis.
  3. Audit kesiapan konfigurasi lingkungan produksi (`.env`, security headers, permissions direktori, HTTPS redirection).
  4. Penyusunan urutan migrasi dan prosedur rollback darurat (*migration & rollback plan*).
  5. Penyusunan Runbook & SOP Operasional Sekolah (panduan instalasi, pemeliharaan rutin, backup berkala, pemulihan bencana).
  6. Penyusunan checklist smoke test pra-rilis (*pre-release smoke checklist*) untuk validasi cepat pasca-instalasi.
  7. Penyediaan handler probe UAT terisolasi `probe-release-readiness` pada `laravel-api/scripts/phase10-uat.php`.
- **Non-Goals:**
  - Tidak melakukan rilis/deployment aktual ke lingkungan produksi operasional (rilis aktual adalah wewenang penuh pemilik pada Subfase 13c jika diotorisasi).
  - Tidak menambahkan fitur bisnis baru di luar modul yang telah diuji (*zero scope creep*).
  - Tidak mengubah skema tabel basis data baru (*zero schema change*).
  - Tidak menyentuh port operasional utama: Apache (port 80/443) dan MariaDB operasional (port 3306) tetap steril dan tidak disentuh.
  - Mematuhi aturan DEC-27: port 18028 dan 14028 dipastikan bebas listener saat tidak dalam sesi pengujian aktif (`PORTS_18028_14028_FREE`).

### D2 — Backup Komprehensif Database & Berkas Lampiran Privat
- Memverifikasi mekanisme pencadangan mencakup dua komponen vital:
  1. **Basis Data SQL:** Skema dan seluruh data dari 10 domain modul sekolah (pengguna, master, kesiswaan, kepegawaian, persuratan, perizinan, arsip, pembelajaran, laporan, dan tugas operasional).
  2. **Storage Privat:** Berkas lampiran nyata di `storage/app/private/` mencakup:
     - Berkas template dan PDF surat keluar ber-Kop (`attachments/letters/*`).
     - Berkas lampiran perizinan siswa/pegawai (`attachments/leaves/*`).
     - Berkas dokumen arsip versi immutable (`attachments/archives/*`).
- Setiap berkas cadangan dilengkapi metadata sha256 checksum untuk memastikan integritas berkas tidak rusak atau terkorupsi saat dipindahkan.

### D3 — Prosedur Restore Drill Kandidat Rilis
- Menjalankan pengujian pemulihan (*restore drill*) pada database dan storage terisolasi untuk membuktikan bahwa:
  1. Skema basis data dan seluruh baris data dapat dipulihkan secara utuh tanpa error sintaks SQL atau constraint violation.
  2. Keterkaitan relasi antar-entitas tetap valid: siswa terhubung ke rombel aktif, penugasan guru terhubung ke jadwal dan jurnal, surat terhubung ke disposisi, serta arsip terhubung ke versi berkasnya.
  3. Berkas fisik pada storage privat dapat diakses dan diverifikasi hash SHA-256-nya sesuai dengan metadata database yang dipulihkan.

### D4 — Audit Konfigurasi Lingkungan Produksi & Keamanan
- Menyusun panduan audit konfigurasi hardening produksi:
  - `APP_ENV=production` dan `APP_DEBUG=false` untuk mencegah kebocoran informasi teknis server.
  - `APP_KEY` wajib menggunakan kunci kriptografis 32-byte acak base64 yang aman.
  - Pengaturan `SESSION_SECURE_COOKIE=true` dan `SESSION_HTTP_ONLY=true` untuk proteksi session hijacking pada HTTPS.
  - Pembatasan izin direktori (*directory permissions*): direktori `storage/` dan `bootstrap/cache/` hanya dapat ditulis oleh akun web server (e.g. `www-data` atau user Apache/PHP terisolasi).
  - Penutupan akses langsung peramban terhadap berkas sensitif (`.env`, `.git`, `composer.json`, `package.json`).

### D5 — Rencana Migrasi & Prosedur Rollback (*Rollback Plan*)
- Menyusun urutan langkah migrasi produksi yang aman:
  1. Langkah pra-migrasi: aktivasi mode pemeliharaan (`php artisan down`), snapshot database penuh, dan backup arsip berkas storage.
  2. Langkah migrasi: eksekusi `php artisan migrate --force` dengan verifikasi output 0 error.
  3. Langkah pasca-migrasi: pembersihan dan caching konfigurasi (`php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`).
- Menyusun prosedur rollback terperinci jika terjadi kendala saat instalasi rilis:
  - Langkah pemulihan snapshot database pra-migrasi.
  - Pengembalian kode sumber (*code checkout*) ke commit rilis stabil sebelumnya.
  - Nonaktifkan mode pemeliharaan (`php artisan up`).

### D6 — Kesiapan Background Worker & Task Scheduling
- Menyusun spesifikasi kesiapan proses latar belakang:
  - Konfigurasi antrean background jobs (`php artisan queue:work --tries=3 --backoff=5,25,125 --timeout=60`) untuk memproses pekerjaan asinkron dan pelacakan status di `job_execution_statuses`.
  - Konfigurasi penjadwalan otomatis (*task scheduler*) melalui Cron Linux atau Windows Task Scheduler untuk menjalankan perintah `php artisan schedule:run` setiap menit.
  - Penjadwalan backup harian otomatis setiap pukul 02:00 WIB dan pemantauan pasif status kesehatan backup via endpoint `/api/backups/status`.

### D7 — SOP & Runbook Operasional Sekolah
- Menyusun dokumen Runbook Operasional Sekolah yang komprehensif bagi tim IT dan administrator sekolah:
  1. *Panduan Inisialisasi Server Baru:* Prasyarat PHP 8.2+, ekstensi wajib, konfigurasi web server, dan pembuatan akun superadmin perdana (`php artisan app:bootstrap-admin`).
  2. *Prosedur Operasi Harian & Mingguan:* Pengecekan dasbor pimpinan, pemantauan log anomali, dan penanganan tugas tertunda.
  3. *Prosedur Pencadangan & Pemulihan Rutin:* Tata cara pencadangan mandiri dan pengujian pemulihan berkala.
  4. *Prosedur Pergantian Tahun Ajaran (Academic Year Rollover):* Penguncian tahun ajaran lama (*locked*), pembuatan tahun ajaran baru, kenaikan kelas, dan penempatan rombel baru.

### D8 — Pre-Release Smoke Test Suite
- Menyusun rangkaian tes cepat pra-rilis (*pre-release smoke checklist*) yang dapat dieksekusi dalam $\le 10$ detik untuk memverifikasi kesehatan komponen kritis:
  - Konektivitas basis data dan integritas tabel utama.
  - Ketersediaan disk penyimpanan storage privat.
  - Respon API endpoint publik dan proteksi endpoint terproteksi (`401 Unauthorized`).
  - Kesiapan aset statis frontend (CSS/JS build dist).

### D9 — UAT Probe Terisolasi `probe-release-readiness`
- Menambahkan handler probe baru pada launcher UAT:
  `php scripts/phase10-uat.php probe-release-readiness`
- Handler ini menjalankan 5 probe verifikasi otomatis di lingkungan runtime terisolasi `.uat-phase10`:
  1. *Probe 1 (Storage & Asset Integrity):* Verifikasi struktur direktori storage privat dan validitas hash SHA-256 berkas lampiran.
  2. *Probe 2 (Database Readiness & Record Counts):* Verifikasi konsistensi data seluruh 10 modul pada basis data kandidat rilis.
  3. *Probe 3 (Production Hardening Guard):* Verifikasi konfigurasi keamanan produksi (`APP_DEBUG=false`, secure cookie, permission checks).
  4. *Probe 4 (Queue & Scheduler Setup):* Verifikasi kesiapan tabel antrean jobs dan scheduler command registration.
  5. *Probe 5 (Bootstrap Admin & Health Check):* Verifikasi perintah CLI `app:bootstrap-admin` dan responsivitas endpoint status sistem.

### D10 — Protokol Review Manual Pemilik (#1–#18) & Kepatuhan Port DEC-27
- Menyusun checklist tinjauan manual 18 butir (#1–#18) pada `reports/feature-update/PHASE-13B-MANUAL-REVIEW.md` yang mencakup:
  - Verifikasi berkas cadangan dan hasil restore drill.
  - Verifikasi panduan operasional (Runbook) dan prosedur rollback.
  - Verifikasi kesiapan konfigurasi produksi dan keamanan berkas.
  - Verifikasi eksekusi probe UAT kesiapan rilis.
- Kepatuhan mutlak DEC-27: port 18028 dan 14028 hanya digunakan saat verifikasi aktif dan wajib dipastikan bebas listener setelahnya (`PORTS_18028_14028_FREE`).

---

## 4. Tahapan Rencana Kerja Implementasi (S1–S8)

| Tahap | Deskripsi Pekerjaan | Artefak / File Target |
|:---:|---|---|
| **S1** | **Audit Kelengkapan Backup Database & Storage Privat** | Verifikasi integritas script backup dan cakupan seluruh file privat di `storage/app/private` (surat, izin, arsip). |
| **S2** | **Eksekusi Restore Drill Kandidat Rilis** | Simulasi pemulihan basis data dan berkas storage privat pada database terisolasi kandidat rilis. |
| **S3** | **Audit Konfigurasi Hardening Lingkungan Produksi** | Verifikasi kesiapan `.env.example`, keamanan session cookie, CSP headers, dan izin direktori storage. |
| **S4** | **Penyusunan Rencana Migrasi & Prosedur Rollback** | Penyusunan dokumen teknis urutan migrasi bertahap dan langkah mitigasi pemulihan darurat (*rollback plan*). |
| **S5** | **Kesiapan Background Queue & Task Scheduler** | Dokumentasi konfigurasi queue worker, retries backoff, dan scheduler Cron harian 02:00 WIB. |
| **S6** | **Penyusunan Runbook & SOP Operasional Sekolah** | Pembuatan panduan operasional lengkap bagi tim IT sekolah (instalasi, pemeliharaan, backup, tahun ajaran baru). |
| **S7** | **Penyediaan Probe UAT `probe-release-readiness`** | Penambahan handler probe kesiapan rilis di `laravel-api/scripts/phase10-uat.php` dan verifikasi 5/5 PASS. |
| **S8** | **Dokumentasi Laporan Kesiapan & Checklist Manual #1–#18** | Penyusunan `PHASE-13B-REPORT.md`, `PHASE-13B-MANUAL-REVIEW.md`, pembaruan tracking docs, commit, dan push. |

---

## 5. Pertanyaan Keputusan Pemilik

Mohon konfirmasi pemilik dengan menyatakan:
> **"Saya setuju D1-D10"**

atau berikan arahan spesifik jika ada butir yang ingin disesuaikan sebelum eksekusi dimulai.
