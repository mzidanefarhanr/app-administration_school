# Panduan Review Manual — Subfase 12b (Job, Observability, dan Error Resilience)

Subfase: 12b — Job, Observability, dan Error Resilience (DEC-46 IMPLEMENTED).  
Keputusan pemilik: **DEC-46 APPROVED** (D1–D10, 2026-11-03 WIB; pernyataan **"Saya setuju D1-D10"**).  
Laporan teknis lengkap: [`reports/feature-update/PHASE-12B-REPORT.md`](./PHASE-12B-REPORT.md); rencana kerja: [`reports/feature-update/PHASE-12B-PLAN.md`](./PHASE-12B-PLAN.md).  
Baseline induk: `main` pada commit `b2df2de`. Status teknis: **IMPLEMENTED & DIVERIFIKASI AGEN (2026-11-03)**.  
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

Atau jalankan automated resilience probe langsung tanpa membuka browser:
```bash
cd D:/xampp-htdocs/app-administration_school/laravel-api
php scripts/phase10-uat.php probe-resilience
```

---

## Akun Pengujian Tersedia

| No | Username | Password | Peran Pengujian |
|:--:|---|---|---|
| 1 | `uat.admin` | `UatAdmin#2026` | Administrator Sistem (Akses Penuh Seluruh Modul & Status Job) |
| 2 | `uat.kepsek` | `UatKepsek#2026` | Kepala Sekolah (Akses Manajerial & Disposisi Surat) |
| 3 | `uat.guru` | `UatGuru#2026` | Guru Pengajar (Jadwal & Jurnal Mengajar) |
| 4 | `uat.staff` | `UatStaff#2026` | Staf Tata Usaha / Operator Sekolah |

---

## Daftar Skenario Uji Manual (#1–#18)

| No | Skenario Uji | Prosedur Langkah Pengujian | Hasil yang Diharapkan |
|:--:|---|---|---|
| **1** | Start UAT Server & Isolasi Port | Jalankan `serve-backend` dan `serve-frontend` melalui `scripts/phase10-uat.php`. | Server backend aktif di `18028`, frontend aktif di `14028`. Port 3306 dan 80/443 tidak tersentuh. |
| **2** | UAT Automated Resilience Probe | Jalankan `php scripts/phase10-uat.php probe-resilience`. | Seluruh 5 probe ketahanan & observabilitas lolos dengan status **ALL 5 UAT PHASE 12B RESILIENCE PROBES PASSED**. |
| **3** | Correlation ID pada Respons Sukses | Buka browser DevTools (Network tab), lakukan login atau muat halaman Siswa. Periksa response headers. | Header `X-Correlation-ID` selalu ada berisi UUID v4 yang unik untuk setiap request. |
| **4** | Propagasi Correlation ID dari Klien | Kirim request ke endpoint API dengan menyertakan header custom `X-Correlation-ID: custom-cid-review-1234`. | Header `X-Correlation-ID` pada response mengembalikan nilai yang sama persis (`custom-cid-review-1234`). |
| **5** | Standarisasi Respons Error Validasi (422) | Buka form Tambah Siswa, klik simpan tanpa mengisi field wajib. Periksa response payload di Network tab. | Response berstatus 422 dengan format seragam: `{"success": false, "correlation_id": "...", "message": "...", "errors": {...}}`. |
| **6** | Standarisasi Respons Not Found (404) | Request URL API yang tidak ada, misalnya `GET /api/endpoint-tidak-ada`. | Response berstatus 404 dengan format seragam menyertakan `correlation_id` tanpa kebocoran path internal sistem. |
| **7** | Standarisasi Respons Unauthorized (401) | Request endpoint terproteksi tanpa menyertakan Authorization Bearer token. | Response berstatus 401 dengan format standar `success: false` dan `correlation_id` terdefinisi. |
| **8** | Standarisasi Respons Forbidden (403) | Login sebagai `uat.guru`, coba akses resource administratif yang dilarang. | Response berstatus 403 dengan format standar dan header `X-Correlation-ID`. |
| **9** | Observabilitas Toast Error 5xx pada Frontend | Simulasikan kegagalan server 500 atau periksa respons interceptor `vue-app/src/apiLogin/index.js`. | Toast notifikasi merah muncul dengan label yang ramah pengguna serta mencantumkan `(ID Referensi: <UUID>)`. |
| **10** | Eliminasi Console Error Suppression | Buka menu `/public/tasks`, periksa console browser saat API summary dimuat. | Tidak ada error unhandled mentah yang di-suppress diam-diam; seluruh status tersalurkan ke UI visual. |
| **11** | Redaksi PII Password di Monolog Logger | Lakukan autentikasi/login dengan password, periksa log Laravel pada `.uat-phase10` atau `laravel.log`. | Nilai password otomatis diredaksi menjadi `[REDACTED]` baik pada context maupun pesan log teks. |
| **12** | Redaksi PII NIK di Monolog Logger | Simulasikan operasi data siswa/pegawai yang memuat NIK 16 digit pada context atau body. | Nomor NIK 16-digit diredaksi otomatis menjadi `[REDACTED_NIK]`, menjamin privasi data pribadi. |
| **13** | Redaksi PII Token & Secret Keys di Log | Periksa catatan log saat request memuat Bearer token atau API key. | Token dan kunci rahasia otomatis diredaksi menjadi `[REDACTED]`. |
| **14** | Kesiapan Tabel Antrean Database | Periksa tabel database antrean melalui SQLite / MariaDB probe. | Tabel `jobs`, `failed_jobs`, `job_batches`, dan `job_execution_statuses` tersedia dengan skema valid. |
| **15** | Endpoint Pelacakan Progres Job | Request `GET /api/jobs/status/{jobId}` untuk job yang terdaftar. | Endpoint mengembalikan status `pending`/`processing`/`completed`, persentase progres, dan ringkasan payload/hasil. |
| **16** | Otorisasi Akses Status Job | Login sebagai `uat.guru`, akses endpoint status job milik `uat.staff`. | Server menolak dengan status **403 Forbidden**. Login sebagai `uat.admin` dapat memantau seluruh job. |
| **17** | Idempotency Replay Guard | Panggil `JobTrackingService::createOrGetJob` dengan `idempotency_key` yang sama sebanyak 2 kali. | Pemanggilan kedua mengembalikan job pertama tanpa membuat baris baru, mencegah eksekusi ganda. |
| **18** | Kepatuhan DEC-27 (Shutdown Bersih) | Hentikan UAT server dengan `Ctrl+C`, periksa port `18028` dan `14028`. | Port 18028 dan 14028 kembali bebas listener. Database operasional dan web server utama tetap aman. |
