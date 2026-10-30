# Rencana Fase 05 — Impor, Ekspor dan Kualitas Data

- Tanggal: 2026-10-30 (WIB). Branch: `main`. Baseline kode: `b093db7` (04b diterima pemilik dan sudah di-push).
- Status dokumen: **DISETUJUI PEMILIK 2026-10-30 WIB sebagai DEC-25** (D1–D8). Subfase **05a IMPLEMENTED (agent-verified) dan owner APPROVED 2026-10-30 WIB (#1–#12)**; 05b/05c belum dimulai.
- Fase 05: subfase **05a (impor siswa) → 05b (ekspor & impor pegawai) → 05c (pemeriksaan kelengkapan)**, dikerjakan satu per satu atas instruksi pemilik.
- Dokumen acuan: `roadmap/phases/PHASE-05-IMPORT-EXPORT.md`, `docs/DECISIONS.md` (DEC-08, DEC-12, DEC-14, DEC-20, DEC-24), `docs/REQUIREMENTS.md` (FR-06), `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `docs/DATABASE.md`, `docs/TESTING.md`.
- Gate: **DEC-08 dan D1–D8 sudah APPROVED 2026-10-30 WIB sebagai DEC-25**; DEC-12 tetap PROPOSED untuk rekap 05b.

---

## 1. Posisi fase dan prasyarat

Fase 03 (siswa) dan Fase 04 (guru/pegawai) sudah diterima pemilik, sehingga dependensi teknis Fase 05 terpenuhi. Yang belum siap bukan kode dasarnya, melainkan **kebijakan impor** yang menentukan perilaku commit, kunci pencocokan, dan izin.

| Prasyarat | Kondisi saat ini | Dampak bila belum diputuskan |
|---|---|---|
| DEC-08 (kebijakan impor campuran) | PROPOSED | Mode commit, partial vs all-or-nothing, dan aturan update tidak boleh ditebak |
| DEC-12 (format laporan eksternal) | PROPOSED | Rekap sekolah 05b butuh contoh format; tanpa contoh jangan dikarang |
| Library spreadsheet | Tidak ada di `laravel-api/composer.json`; perubahan dependency belum diotorisasi | Impor XLSX tidak bisa dimulai; CSV dapat dikerjakan tanpa dependency baru |
| Izin impor | Belum ada `students.import`/`employees.import` di `app/Support/AbilityCatalog.php:36-47` | Harus diputuskan agar impor tidak disamakan dengan `manage` |
| Lingkungan UAT | UAT 04b (`.uat-phase04b`, 14022/18022) berstatus diterima | UAT 05 sebaiknya terpisah agar bukti 04b tidak berubah |

---

## 2. Temuan audit kode (fakta, bukan asumsi)

### 2.1 Ekspor sudah ada dan dapat dipakai ulang

| Temuan | Bukti | Implikasi untuk 05 |
|---|---|---|
| Ekspor siswa CSV terfilter + izin ganda + batas 422 | `laravel-api/routes/api.php:161`, `app/Http/Controllers/StudentExportController.php` | 05b tidak membangun ulang; cukup menambah pilihan kolom/rekap |
| Ekspor pegawai CSV terfilter + kolom eksplisit + netralisasi formula | `laravel-api/routes/api.php:190`, `app/Http/Controllers/EmployeeExportController.php` | Pola `sanitizeCell` dipakai ulang untuk laporan error impor |
| Batas baris lewat konfigurasi, bukan potong diam-diam | `config/students.php`, `config/employees.php` | Batas impor mengikuti pola yang sama (tolak, jangan potong) |

### 2.2 Belum ada fondasi impor

- Tidak ada route, controller, request, atau model impor di `laravel-api/app` dan `routes/api.php`.
- Tidak ada tabel batch/staging, tidak ada `app/Jobs`, antrean UAT memakai `sync`.
- Tidak ada library spreadsheet (PhpSpreadsheet/maatwebsite/league/csv) di `composer.json`.
- Belum ada izin `*.import`; peran Admin Sistem dan Operator Sekolah saat ini memegang `students.manage`/`employees.manage` (`AbilityCatalog.php:79-107`).

### 2.3 Aturan identitas yang harus dipatuhi impor

- `students.nik` nullable unique, `nis` nullable unique (`max:50`), `nisn` nullable unique (`digits:10`) — migration `2029_07_06_215144`, aturan di `app/Http/Requests/StoreStudentRequest.php`.
- `employees.nik` nullable unique `varchar(16)`, `name` nullable, `nuptk` nullable non-unik — migration `2029_07_26_000001`.
- Impor wajib memperlakukan NIK/NIS/NISN/NUPTK sebagai **string** agar nol di depan tidak hilang, dan tidak boleh menimpa record di luar himpunan yang cocok.

### 2.4 UAT 04b bersifat tetap

`laravel-api/scripts/phase04b-uat.php` mengunci runtime `.uat-phase04b`, port 18022/14022, cookie `school_phase04b_session`, dan menolak override proses. UAT 05 perlu launcher/pasangan port sendiri; jangan mengubah UAT yang sudah diterima.

---

## 3. Rekomendasi urutan pekerjaan

| Urutan | Subfase | Alasan |
|---|---|---|
| 1 | **05a — Impor siswa (CSV)** | Mesin impor, staging, preview, commit, laporan error, dan izin adalah fondasi yang dipakai 05b |
| 2 | **05b — Ekspor & impor pegawai** | Memakai mesin 05a dengan rule domain pegawai; rekap sekolah menunggu contoh format (DEC-12) |
| 3 | **05c — Pemeriksaan kelengkapan** | Membutuhkan data hasil impor dan definisi kelengkapan dari pemilik |

Fase 06 (tahun ajaran/rombel) sengaja tidak dimasukkan: roadmap menempatkan 05 lebih dulu, dan impor siswa menghasilkan data yang justru dibutuhkan pengujian 06.

---

## 4. Keputusan yang perlu pemilik (D1–D8)

Rekomendasi ditulis sebagai default; pemilik dapat memilih alternatif. Keputusan dicatat sebagai DEC baru (mis. DEC-25 untuk impor) sebelum implementasi.

| # | Pertanyaan | Rekomendasi | Alasan |
|---|---|---|---|
| D1 | Commit partial atau all-or-nothing? | **All-or-nothing per batch**: commit ditolak selama masih ada baris error; operator memperbaiki file lalu unggah ulang. Batch dibatasi 500 baris. | Sesuai usulan DEC-08 "validasi semua sebelum commit"; menghindari laporan "sebagian sukses" yang membingungkan |
| D2 | Kunci pencocokan mode update | Siswa: **NIS** utama, NIK alternatif; Pegawai: **NIK** saja. Tidak pernah mencocokkan berdasarkan nama. | NIS/NIK unik dan stabil; nama homonim dan dilarang auto-merge (batas 05c) |
| D3 | Format file | **CSV UTF-8** (pemisah `,` atau `;` terdeteksi), maks 5 MB / 2.000 baris; XLSX ditunda sampai dependency disetujui | Tidak menambah dependency; cukup untuk data sekolah |
| D4 | Template kolom | **Full English**: header template v1 English tetap, tanpa alias Indonesia, tanpa UI mapping bebas | Mengurangi permukaan bug dan menyamakan bahasa aplikasi |
| D5 | Izin impor | Ability baru `students.import`, `employees.import`; hanya Admin Sistem + Operator Sekolah | Least privilege; impor massal berbeda risiko dari CRUD satuan |
| D6 | Penyimpanan & retensi laporan error | Disk `local` privat `imports/{token}/errors.csv`; unduh lewat endpoint berizin; retensi 30 hari dengan perintah pembersihan manual | Tidak ada file PII di web root; tidak menambah scheduler |
| D7 | Lingkungan UAT 05 | Runtime baru `.uat-phase05` + launcher terpisah, port 14023/18023, data sintetis ≥300 siswa dan ≥100 pegawai | Bukti 04b tetap utuh; UAT 05 tidak menyentuh DB/servis pemilik |
| D8 | Cakupan 05b | Impor pegawai + pilihan kolom ekspor dikerjakan; **rekap sekolah menunggu contoh format** dari pemilik | DEC-12 masih PROPOSED; format tidak boleh dikarang |

Bila pemilik hanya menyetujui sebagian, 05a tetap dapat berjalan untuk D1–D7; 05b menunggu D8.

---

## 5. Desain teknis Subfase 05a — Impor siswa

### 5.1 Skema (migration additive)

`import_batches`:
- `id`, `token` (uuid, unique), `domain` (`students`), `mode` (`insert`/`update`), `status` (`parsed`, `committed`, `failed`),
- `file_name`, `file_hash` (sha256), `total_rows`, `valid_rows`, `invalid_rows`, `committed_rows`,
- `errors_path`, `created_by` (FK users, nullOnDelete), `committed_at`, timestamps,
- index `(created_by, created_at)`.

Baris tidak disimpan di tabel terpisah agar tidak menduplikasi PII; hasil per baris ada di `errors.csv` privat dan ringkasan di batch.

### 5.2 Endpoint

| Endpoint | Izin | Perilaku |
|---|---|---|
| `POST /api/imports/students` | `students.import` | Unggah + parse + validasi; **tidak mengubah data**; balikan token, ringkasan, 50 baris pratinjau, 50 error pertama |
| `GET /api/imports/{batch}` | `students.import` + pemilik batch/admin | Status dan ringkasan batch |
| `POST /api/imports/{batch}/commit` | `students.import` + pemilik batch | Idempotent; menolak bila ada error, batch kedaluwarsa, atau hash file berubah; transaksi per 100 baris |
| `GET /api/imports/{batch}/errors` | `students.import` | CSV privat, sel dinetralkan dari formula |
| `GET /api/imports/students/template` | `students.view` | CSV header v1 + satu baris contoh sintetis |

### 5.3 Aturan parsing & validasi

- BOM dihapus; encoding UTF-8 wajib; baris kosong diabaikan; header tidak cocok → 422 dengan daftar kolom yang tidak dikenal/hilang.
- Semua identitas dibaca sebagai string (tidak ada cast numerik) sehingga `00123` tetap utuh.
- Tanggal menerima `Y-m-d` dan `d/m/Y`, dinormalkan ke `Y-m-d`; tanggal lahir wajib sebelum hari ini.
- Nilai referensi (gender, agama, pendidikan, profesi, status keluarga) menerima nama kanonik **atau** label English lewat katalog `reference-labels.json`; nilai tak dikenal menjadi error baris, bukan dibuatkan master baru.
- Rule domain siswa diekstrak ke satu sumber (mis. `app/Support/StudentRules` atau concern) dan dipakai bersama `StoreStudentRequest` supaya impor dan form tidak menyimpang.
- Duplikat di dalam file (NIS/NIK/NISN sama) dilaporkan pada semua baris terkait; duplikat terhadap DB dilaporkan per baris.
- Mode `update`: hanya baris dengan kunci cocok yang diubah, `version` naik, konflik versi dilaporkan sebagai daftar; baris tanpa pasangan menjadi error (tidak insert diam-diam).
- Audit mengikuti allowlist `Loggable`; PII tidak masuk log.

### 5.4 Frontend

- Tombol **Import** pada halaman Students, hanya tampil bila `students.import` (backend tetap penegak utama).
- Wizard: Unggah → Pratinjau (jumlah valid/error + tabel error) → Konfirmasi commit → Hasil + unduh laporan error.
- Seluruh teks English, tanggal dan angka mengikuti format tampilan yang ada; dialog tetap usable pada 390px.

### 5.5 Tes (SQLite in-memory dengan guard `tests/CreatesApplication.php`)

1. Pratinjau tidak mengubah DB (hitung baris sebelum/sesudah).
2. Leading zero NIK/NIS/NISN/NUPTK bertahan.
3. File salah format/encoding/header → 422 tanpa mutasi.
4. Baris campuran valid+error → commit ditolak, tidak ada insert.
5. Duplikat dalam file dan terhadap DB terdeteksi.
6. Commit ulang dengan token sama tidak menduplikasi (idempotent).
7. Mode update hanya mengubah baris cocok; decoy di luar kunci tidak berubah.
8. Konflik versi dilaporkan, bukan ditimpa.
9. Izin: tanpa `students.import` → 403; `students.view` saja → 403; Operator/Admin → 200.
10. Batas ukuran/baris → 422 (bukan potong).
11. Formula injection dinetralkan pada CSV laporan error.
12. Batch kedaluwarsa / hash berubah → commit ditolak.
13. Tes rollback: kegagalan di tengah commit membatalkan seluruh batch.
14. Regresi ekspor siswa/pegawai tetap PASS.

### 5.6 UAT

Runtime `.uat-phase05` (D7), seed sintetis ≥300 siswa dan ≥100 pegawai, file contoh valid dan file campuran error. Checklist manual pemilik disiapkan setelah implementasi; status awal PENDING.

---

## 6. Garis besar Subfase 05b — Ekspor & impor pegawai

- Tambah pilihan kolom ekspor sesuai izin; kolom sensitif tetap dibatasi `employees.manage`.
- Rekap sekolah **hanya** setelah pemilik memberikan contoh format (DEC-12).
- Impor pegawai memakai mesin 05a dengan rule domain 04b: Teachers = `Pendidik` (Subject wajib), Employees = `Tenaga Kependidikan` (Subject ditolak), NIK unik, NUPTK non-unik.
- Pencocokan file Dapodik ditunda (butuh contoh file dan keputusan terpisah).

## 7. Garis besar Subfase 05c — Pemeriksaan kelengkapan

- Daftar duplikat/invalid/missing dengan tautan ke record berizin dan status review operator.
- Denominator kelengkapan harus eksplisit; siswa tanpa NIK dibedakan **incomplete** vs **blocked** sesuai DEC-20.
- Koreksi manual ter-audit; tidak ada penggabungan otomatis berdasarkan nama.

---

## 8. Verifikasi dan bukti

- Setiap subfase: `php artisan test` untuk berkas terkait setelah isolasi terbukti, `npx eslint` non-mutasi, `npm run build`, `pint --test` pada berkas berubah.
- Bukti browser hanya diklaim bila benar-benar dijalankan; jika tidak, tulis NOT RUN dan sediakan checklist manual.
- Angka tes dari subfase berbeda tidak diagregasikan; laporan mencatat perintah, hasil, SHA, dan working tree.
- Commit + push dilakukan setelah pemilik menyatakan cek manual lulus dan approved (otorisasi tetap 2026-10-30), dengan penerimaan pada commit dokumentasi terpisah.

---

## 9. Risiko utama

| Risiko | Mitigasi |
|---|---|
| Sebagian batch sukses dilaporkan sebagai sukses total | Commit all-or-nothing (D1) + ringkasan eksplisit |
| PII bocor lewat laporan error | Disk privat, akses berizin, retensi 30 hari, tanpa PII di log |
| Karakter identitas hilang (leading zero) | Semua identitas string, tanpa cast numerik |
| Formula injection dari file pengguna | Netralisasi sel pada laporan error dan ekspor turunan |
| Duplikasi akibat retry | Token + hash file + transisi status atomik |
| Perbedaan perilaku SQLite vs MariaDB | Uji unit SQLite; uji integrasi MariaDB terisolasi hanya bila diizinkan |
| Dependency baru (XLSX) | Ditunda; CSV dulu, dependency butuh persetujuan terpisah |

---

## 10. Yang dikerjakan setelah rencana ini disetujui

1. Catat keputusan D1–D8 sebagai DEC baru; perbarui `docs/DECISIONS.md`.
2. Implementasi 05a saja: migration additive, service parser/validator, endpoint, izin baru, UI wizard, tes, laporan.
3. Siapkan `.uat-phase05` + seeder sintetis; jangan menyentuh UAT 04b, DB legacy, atau MariaDB pemilik.
4. Serahkan laporan + checklist manual; berhenti sampai pemilik menyetujui sebelum lanjut 05b.

**Tidak termasuk dalam rencana ini:** deploy, merge, migrasi data operasional, penambahan dependency, perubahan servis, API Dapodik, dan penutupan risiko keamanan terbuka.
