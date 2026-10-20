# Panduan Review Manual Subfase 02c — Penjadwalan, Retensi, dan Monitoring Backup

- Tanggal WIB: 2026-10-20
- Subfase: **02c — Jadwal, Retensi, dan Monitoring Backup**
- Branch: `main`
- Status teknis: **IMPLEMENTED & AUTOMATED TESTS PASS (154/154)**
- Status checklist pemilik: **PENDING (Menunggu Pemeriksaan Manual Pemilik)**
- Isolasi pengujian: Seluruh pengujian manual menggunakan server UAT lokal port 8001/4001 dan fixture terisolasi tanpa menyentuh data operasional.
- **Peringatan Keselamatan Manual**:
  - Dilarang menjalankan rotasi prune destruktif pada arsip backup UAT pemilik.
  - Pengujian key kosong menggunakan sub-proses terisolasi tanpa merusak berkas `.env.uat`.
  - Pengujian status `OVERDUE` menggunakan metadata/fixture uji terisolasi tanpa mengubah jam sistem operasi komputer.
  - Scheduler/task OS (Windows Task Scheduler / cron) **tetap tidak diaktifkan**.

---

## 1. Persiapan Lingkungan UAT Lokal

Pastikan server UAT lokal aktif pada port `8001` (API) dan `4001` (SPA) menggunakan database disposable `school_access_uat`:
- **API UAT**: `http://127.0.0.1:8001` (`php artisan serve --host=127.0.0.1 --port=8001 --env=uat`)
- **SPA UAT**: `http://127.0.0.1:4001` (`npm run dev` / `vite --mode uat --host 127.0.0.1 --port 4001`)

Akun UAT sintetis yang tersedia:
- `uat.admin` (System Administrator — memiliki kemampuan `backup.manage`)
- `uat.operator` (School Operator — **tidak** memiliki `backup.manage`)

---

## 2. Checklist Pemeriksaan Manual Terisolasi

Status: **PENDING** = menunggu verifikasi dan jawaban pemilik; **APPROVED** = pemilik telah menyetujui hasil uji manual.

| # | Skenario Uji | Tindakan / Perintah | Hasil yang Diharapkan | Status Pemilik |
|---|---|---|---|---|
| 1 | Akses Tamu ke Endpoint Status | Buka cURL atau browser tanpa login: `curl.exe -s -H "Accept: application/json" http://127.0.0.1:8001/api/backups/status` | Mengembalikan **HTTP 401 Unauthorized** (`{"message":"Unauthenticated."}`). Akses ditolak. | **PENDING** |
| 2 | Otorisasi Akses Operator ke Status | Login sebagai `uat.operator` di browser (`http://127.0.0.1:4001/auth/login`). Buka console browser (F12) lalu jalankan: `await fetch('http://127.0.0.1:8001/api/backups/status', { credentials: 'include', headers: { 'Accept': 'application/json' } }).then(r => r.status)` | Mengembalikan status **403 Forbidden** (`{"message":"This action is unauthorized."}`). Operator tidak dapat melihat status monitoring backup. | **PENDING** |
| 3 | Pembacaan Status Monitoring oleh Admin | Login sebagai `uat.admin` di browser. Buka console (F12) lalu jalankan: `const res = await fetch('http://127.0.0.1:8001/api/backups/status', { credentials: 'include', headers: { 'Accept': 'application/json' } }); const json = await res.json(); console.log(res.status, json);` | Mengembalikan status **200 OK**. Payload JSON memuat informasi `status` (misal `HEALTHY` atau `NEVER_BACKED_UP`), `schedule_enabled: false`, `schedule_backup_time: "02:00"`, `hours_since_last_success`, `last_attempt`, dan `last_success`. Tidak ada password/secret yang tampak. | **PENDING** |
| 4 | Pembedaan `backup_created` vs `restore_verified` | Periksa payload `data` dari respons butir #3 | Kolom `backup_created` dan `restore_verified` tampil sebagai dua nilai boolean terpisah. `restore_verified` hanya bernilai true jika drill pemulihan nyata telah berhasil diverifikasi pada arsip yang bersangkutan. | **PENDING** |
| 5 | Mode Non-Interaktif dengan Passphrase Valid | Buka terminal di folder `laravel-api`. Pastikan `BACKUP_ENCRYPTION_KEY` terkonfigurasi di file env lokal, lalu jalankan: `php artisan --env=uat app:backup-run --non-interactive --connection=mysql` | Proses backup berjalan otomatis sampai selesai **tanpa meminta input secret prompt**. Tercetak `Backup successfully created!`, dan status monitoring tercatat `SUCCESS`. | **PENDING** |
| 6 | Mode Non-Interaktif saat Rahasia Kosong (Terisolasi) | Jalankan perintah pada sub-proses terisolasi dengan meniadakan env key tanpa mengubah `.env.uat`: di PowerShell: `powershell -Command "$env:BACKUP_ENCRYPTION_KEY=''; php artisan --env=uat app:backup-run --non-interactive"` | Proses langsung **gagal seketika tanpa menggantung** pada prompt dengan pesan error: `Non-interactive backup aborted: BACKUP_ENCRYPTION_KEY is not configured or shorter than 12 characters.` File `.env.uat` pemilik tetap utuh. | **PENDING** |
| 7 | Mutex Lock Overlap Prevention | Buka dua jendela terminal di folder `laravel-api`. Pada terminal 1, jalankan perintah backup. Sebelum terminal 1 selesai, jalankan perintah backup pada terminal 2: `php artisan --env=uat app:backup-run --non-interactive` | Terminal 2 mendeteksi lock dan melewatinya secara aman (*skipped*) dengan pesan: `Another backup or restore process is currently running. Skipping backup run.` Tidak terjadi crash atau file korup. | **PENDING** |
| 8 | Evaluasi Retensi Mode Dry-Run (Non-Destruktif) | Jalankan perintah rotasi murni mode simulasi pada arsip UAT: `php artisan --env=uat app:backup-prune --dry-run` | Menampilkan tabel arsip yang dipertahankan (*keep*) beserta alasan retensinya (Daily/Weekly/Most Recent), dan daftar kandidat yang akan dihapus. **Tidak ada berkas yang dihapus fisik** dari direktori arsip pemilik. | **PENDING** |
| 9 | Preservasi Backup Terakhir pada Evaluasi | Periksa tabel hasil evaluasi rotasi dari butir #8 | Cadangan valid paling akhir (*most recent successful backup*) selalu masuk ke dalam kelompok yang dipertahankan (*keep*) dan tidak pernah dimasukkan ke daftar kandidat penghapusan. | **PENDING** |
| 10 | Deteksi Keterlambatan (*Freshness Alert*) Terisolasi | Uji kalkulasi status `OVERDUE` melalui unit test terisolasi `BackupMonitoringTest` (menggunakan waktu virtual `Carbon::setTestNow` tanpa mengubah jam komputer sistem operasi): `php artisan test tests/Feature/Backup/BackupMonitoringTest.php` | Seluruh 7 tes monitoring lulus: terbukti status kesehatan secara dinamis berubah menjadi **`OVERDUE`** dan `is_overdue: true` saat waktu virtual melebihi 28 jam dari backup terakhir, tanpa menyentuh jam OS atau monitoring UAT aktif. | **PENDING** |

---

## 3. Cara Memberikan Persetujuan Pemilik

Setelah Anda memeriksa butir-butir di atas pada lingkungan lokal, kirim instruksi:
```text
Catat hasil pemeriksaan manual saya untuk subfase 02c pada kandidat <SHA>.
Hasil: checklist nomor #1–#10 APPROVED.
```
Hasil pemeriksaan manual Anda akan dicatat dalam commit dokumentasi tersendiri sesuai aturan repo.
