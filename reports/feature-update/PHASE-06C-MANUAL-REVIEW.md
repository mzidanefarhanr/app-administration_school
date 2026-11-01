# Panduan Review Manual — Subfase 06c (Mutasi, Pembatalan, Rekap, dan Koreksi Periode Terkunci)

Subfase: 06c — mutasi masuk/keluar bertanggal, pembatalan kompensatif, rekap per tahun, dan koreksi periode locked yang ter-audit.
Keputusan: **DEC-31 APPROVED** (D1–D10, 2026-11-01 WIB; "Saya setuju D1-D10."). Laporan teknis: `reports/feature-update/PHASE-06C-REPORT.md`.
Baseline induk: `main` pada `da94195` (pencatatan DEC-31); implementasi `9927d8e`. Status: **IMPLEMENTED — agent-verified; OWNER APPROVED 2026-11-01 WIB — checklist #1–#16**.

## Status penerimaan pemilik

- Pernyataan pemilik 2026-11-01 WIB: **"Pengujian nomor 1-16 sudah berhasil dan saya approved."**
- Cakupan: seluruh skenario #1–#16 pada lingkungan UAT terisolasi `.uat-phase06`. Pernyataan pemilik adalah satu-satunya bukti penerimaan; agen tidak mengulang skenario.
- Batas: penerimaan tidak mengotorisasi merge, deploy, migrasi data operasional/MariaDB, perubahan dependency/service, rekap semester, atau rekap sekolah (DEC-12).

## Lingkungan UAT (terisolasi)

- SPA: `http://127.0.0.1:14024`
- API: `http://127.0.0.1:18024`
- Data: `.uat-phase06` SQLite; cookie `school_phase06_session`
- Launcher: `laravel-api/scripts/phase06-uat.php`
- Akun: **`uat.operator`** (mutasi/pembatalan), **`uat.kepsek`** (view-only), **`uat.admin`** (koreksi periode locked). Password hanya ada di runtime privat `.uat-phase06/account-password.txt`; tidak dicetak di dokumen.
- Server UAT **dihentikan 2026-11-01 setelah penerimaan**: API PID 9436 (`php.exe -S 127.0.0.1:18024 -t public scripts/phase06-uat.php`) dan SPA PID 19280 (`node vite --host 127.0.0.1 --port 14024 --strictPort`) diverifikasi command line sebelum penghentian; port 14024/18024 bebas; Apache 80/443 (PID 1304) dan MariaDB 3306 (PID 18244) tidak disentuh; data `.uat-phase06` dipertahankan.
- Sesuai DEC-27, server dihentikan **setelah** pemilik menyatakan penerimaan; perintah restart di bawah hanya digunakan bila pemilik meminta sesi verifikasi tambahan.

Perintah restart bila server sudah berhenti:

```powershell
# API (dari laravel-api)
C:\xampp\php82-operational\php.exe -S 127.0.0.1:18024 -t public scripts/phase06-uat.php

# SPA (dari vue-app)
npm run dev -- --host 127.0.0.1 --port 14024 --strictPort
```

Perintah status (read-only, tidak mengubah data):

```powershell
# dari laravel-api
C:\xampp\php82-operational\php.exe scripts/phase06-uat.php status
```

Angka acuan saat ini: **263 siswa aktif** (260 `UAT-P06`, termasuk 120 `UAT-P06C`; 10 siswa P06C terarsip), 20 sekolah sintetis `UAT-P06C School 01..20`, **94 log mutasi** (21 cancelled), **1 koreksi locked**, 2 batch progresi 06b. Periode aktif **2026/2027 · Ganjil**; tahun 2026/2027 dan 2027/2028 unlocked; tahun fixture **2025/2026 locked** (bukan tahun aktif).

Keadaan setelah pengujian agen (tidak perlu direset; fixture `prepare-mutations` idempoten dan tidak mengubah baris yang sudah ada):

- `P06C-0021` — mutasi keluar fixture dibatalkan; membership X-2 kembali aktif (versi 3), log `out` tetap ada berstatus `cancelled`.
- `P06C-0081` — mutasi masuk demo tercipta: membership X-1 status `Aktif`, entry `Mutasi Masuk`, sekolah 91000005, tanggal efektif 2026-11-01, log `in` aktif.
- `P06C-0111` — mutasi keluar demo tercipta: membership XI-2 tertutup (status `Mutasi Keluar`, exit 2026-11-01, sekolah 91000003, versi 2), log `out` aktif.
- `P06C-0072` — koreksi locked demo oleh `uat.admin`: sekolah tujuan mutasi keluar dikoreksi 91000002 → 91000001 dengan alasan dan approval `UAT-06C-APPROVAL-001`; mutasi versi 2; tercatat di `student_locked_corrections` dan timeline.
- Rekap 2026/2027 saat ini: **In 41, Out 20, Graduated 1, Retained 1**; rekap 2025/2026: **In 1, Out 1, Graduated 1, Retained 0**.

Jangan menguji pada database operasional, `.uat-phase05`, atau MariaDB. Jangan menjalankan `prepare-mutations` berulang dengan tujuan reset; fixture tidak mereset data.

## Checklist

### 1. Isolasi, login, dan keadaan awal
- Login sebagai `uat.operator`, buka **Students** → pilih tahun **2026/2027** → bagian **Mutations**.
- Harapkan dropdown tahun bagian Mutations default ke periode aktif 2026/2027, kartu **Mutation In 41**, **Mutation Out 20**, **Graduated 1**, **Retained 1**, dan tabel log berisi 94 baris (paginasi 20/halaman).
- Harapkan tombol **New Mutation In** dan **New Mutation Out** tersedia; tidak ada banner **View-only access**.
- Harapkan label, tombol, dan pesan seluruhnya English; tidak ada error console/network.

### 2. Izin view-only (kepsek)
- Login sebagai `uat.kepsek`, buka bagian **Mutations** tahun 2026/2027.
- Harapkan banner **View-only access**, tanpa tombol New In/Out; kolom Action hanya **Student Timeline**; tidak ada Cancel/Correct.
- Buka timeline `P06C-0031`: harapkan riwayat In → Out → In dapat dibaca.
- Lewat DevTools/API: `POST /api/students/{id}/mutations/in|out`, `POST .../cancel`, dan `POST .../locked-corrections` → `403`; `GET /api/academic/mutations/recap` → `200`.
- Login kembali sebagai `uat.operator` untuk langkah berikut.

### 3. Mutasi masuk (siswa unplaced)
- Cari `P06C-0082` (unplaced), pilih kelas **X-1** tahun aktif, tanggal efektif (default hari ini), sekolah tujuan `UAT-P06C School 05` (NPSN `91000005`), alasan ≥10 karakter.
- Harapkan dialog menampilkan konsekuensi (membership baru, entry `Mutasi Masuk`, status `Aktif`) sebelum submit; tombol submit baru aktif setelah siswa/kelas/sekolah/alasan valid.
- Setelah submit: toast sukses; baris log `in` aktif muncul; rekap **Mutation In** bertambah 1; buka **Students**, cari `P06C-0082`: status placed di X-1.
- Coba siswa terarsip `P06C-0101` dan siswa yang sudah punya membership aktif (mis. `P06C-0001`): server menolak `422` dengan pesan jelas; tidak ada baris baru.

### 4. Guard re-entry
- Coba mutasi masuk untuk `P06C-0031` (sudah punya membership aktif setelah re-entry): harapkan penolakan `422` "already has an active class membership" (atau pesan setara) dan tidak ada mutasi baru.
- Buka **Student Timeline** `0031`: harapkan tiga peristiwa (in 2026-07-20, out 2027-01-10, in 2027-01-15) dengan sekolah asal/tujuan dan aktor; bukan duplikasi.

### 5. Mutasi keluar (menutup satu membership)
- Cari `P06C-0112` (aktif di XI-2), pilih **New Mutation Out**, pilih membership XI-2, tanggal efektif, sekolah tujuan `UAT-P06C School 03` (NPSN `91000003`), alasan ≥10 karakter.
- Harapkan dialog menyatakan biodata siswa **tidak dihapus** dan hanya satu membership yang ditutup; siswa dikeluarkan dari daftar aktif kelas.
- Setelah submit: membership berstatus `Mutasi Keluar` dengan exit date dan versi +1; log `out` aktif; rekap **Mutation Out** bertambah 1; siswa tetap ada di daftar Students (bukan archived) dan menjadi unplaced.
- Coba mutasi keluar untuk membership yang sudah tertutup atau milik siswa lain: `422`, tanpa mutasi.

### 6. Pembatalan kompensatif (out)
- Setelah langkah 5, buka baris `out` `P06C-0112` → **Cancel Mutation** dengan alasan ≥10 karakter.
- Harapkan log tetap ada berstatus `Cancelled` (tidak dihapus), membership XI-2 kembali `Aktif`, tanggal keluar dibersihkan, versi naik, dan rekap **Mutation Out** kembali berkurang 1.
- Harapkan baris yang sudah `Cancelled` tidak menampilkan tombol Cancel; pembatalan kedua ditolak `422`.

### 7. Pembatalan kompensatif (in) — opsional bila langkah 3 dijalankan
- Batalkan log `in` `P06C-0082`: harapkan membership diarsipkan (bukan dihapus) dengan alasan `Cancelled mutation-in`, log tetap `Cancelled`, rekap **Mutation In** berkurang 1, dan siswa kembali unplaced.
- Bila tidak ingin membatalkan, tandai **SKIP**; agen sudah memverifikasi lewat UI/tes otomatis pada fixture `0041–0050`.

### 8. Tahun locked dan gerbang koreksi
- Pilih tahun **2025/2026** pada dropdown Mutations: harapkan badge **Locked**, pesan informatif bahwa mutasi normal tidak tersedia, tombol **New Mutation In/Out** nonaktif; tabel log dan rekap tetap dapat dibaca (**In 1, Out 1, Graduated 1, Retained 0**).
- Sebagai `uat.operator`: tombol **Correct Locked Mutation** tidak muncul dan `POST /api/students/{id}/locked-corrections` → `403` (tidak memiliki `students.correct-locked`).
- Sebagai `uat.admin`: tombol pensil muncul pada baris yang dapat dikoreksi.
- Coba mutasi masuk/keluar normal pada tahun locked lewat UI/API: `422` "locked"; tidak ada mutasi.

### 9. Koreksi periode locked (admin)
- Sebagai `uat.admin`, buka timeline `P06C-0072` atau baris mutasi `out` di tahun 2025/2026.
- Koreksi sekolah tujuan (mis. ke `91000001`) dengan alasan ≥10 karakter dan approval reference opsional; harapkan konfirmasi konsekuensi, lalu toast sukses.
- Harapkan `student_mutations` versi naik, log tetap immutable, riwayat membership menampilkan sekolah tujuan baru, dan timeline menampilkan peristiwa **Locked Correction** berisi before/after, aktor, alasan, dan approval.
- Opsional: uji satu tindakan lain dari lima (`mutation_in`, `mutation_out`, `mutation_cancel`, `membership_exit`, `membership_reopen`) pada `P06C-0073`/`0074`; harapkan hasil konsisten dan tercatat.
- Coba koreksi pada tahun **unlocked**: `422` (hanya tahun locked); coba tanpa ability: `403`.

### 10. Konflik versi `409` (opsional; agent-verified)
- Buka dialog mutasi/koreksi, lalu ubah `version` data terkait di luar UI (DevTools/DB UAT) sebelum submit.
- Harapkan `409` dengan item `errors` + `current_versions`, dialog menyatakan tidak ada data yang diubah, dan tombol **Reload** mengambil data terbaru.
- Bila tidak memutasi versi, tandai **SKIP**; agen sudah memverifikasi lewat tes otomatis dan API.

### 11. Rekap per tahun
- Untuk 2026/2027: harapkan **In 41, Out 20, Graduated 1, Retained 1** sebelum langkah 3–7, berubah sesuai mutasi/pembatalan yang dilakukan.
- Harapkan mutasi `cancelled` tidak dihitung; graduated dihitung unik per siswa dalam rentang tanggal tahun; retained diambil dari keputusan `retain` pada batch progresi committed terbaru per siswa.
- Harapkan catatan bahwa rekap semester tidak tersedia karena semester belum memiliki tanggal (D5); jangan mengharapkan angka semester.

### 12. Audit, privasi, dan immutability
- Periksa halaman/tabel audit (`activities`) setelah mutasi, pembatalan, dan koreksi.
- Harapkan catatan audit memuat id/versi/tanggal/status dan referensi sekolah, **tanpa** NIK, nama, alamat, atau alasan bebas; token tidak relevan pada 06c.
- Harapkan `student_mutations` dan `student_locked_corrections` tidak memiliki endpoint delete/update; record lama tetap ada setelah pembatalan/koreksi.
- Harapkan tidak ada NIK/alamat pada payload timeline/log baru.

### 13. Guard hapus tahun ajaran
- Sebagai pemilik `master.manage`, coba hapus tahun **2025/2026** dan **2026/2027** di **School Years**.
- Harapkan `422` "still being referenced by N mutation log(s)" atau "N locked correction(s)" (selain guard kelas/batch yang sudah ada) dan tahun tetap ada.

### 14. Interaksi dengan arsip administratif
- Pastikan siswa terarsip (`P06C-0101`) tidak dapat dimutasi masuk/keluar (`422`).
- Pastikan mutasi keluar **tidak** mengarsipkan atau menghapus biodata siswa; siswa tetap muncul di daftar Students (filter unplaced) dan dapat dilihat detailnya.
- Arsip administratif 03b tetap terpisah dari mutasi; jangan gunakan arsip untuk mencatat keluar.

### 15. English, 390 px, dan console
- Periksa seluruh label/pesan 06c (section Mutations, dialog In/Out/Cancel/Correct, timeline, rekap, konflik): English.
- Emulasikan lebar 390 px: kartu rekap, tabel log (scroll horizontal), dan dialog tidak melebar keluar viewport; tombol aksi tetap terjangkau.
- Harapkan tidak ada error console/network selain `409`/`403`/`422` yang disengaja saat menguji konflik/akses.

### 16. Lifecycle server
- Server UAT 14024/18024 tersedia sampai pemilik menyatakan penerimaan (DEC-27).
- Setelah penerimaan, server dihentikan 2026-11-01 dengan verifikasi PID/command line (SPA 19280, API 9436), port 14024/18024 dibebaskan, data `.uat-phase06` dipertahankan, dan Apache (PID 1304, port 80/443)/MariaDB (PID 18244, port 3306) tidak disentuh.
- Jangan merge/deploy atau memigrasikan data ke database operasional.

## Cara melaporkan hasil

Untuk setiap nomor, tulis: **PASS/FAIL/SKIP**, langkah yang dilakukan, hasil aktual (angka/pesan/versi), dan screenshot bila ada. Sertakan pesan console/network bila muncul error. Seluruh skenario menggunakan `.uat-phase06`; jangan menguji mutasi pada data operasional.

## Penutupan setelah penerimaan

- Penerimaan dicatat pada commit dokumentasi tersendiri `41ec7b5` (`2026-11-01`), bukan pada commit implementasi `9927d8e`.
- Server UAT dihentikan 2026-11-01 setelah penerimaan; data dan password runtime dipertahankan di `.uat-phase06`; perintah restart tetap tercantum di atas.
- Apache/MariaDB/service/dependency tidak diubah; data tidak dimigrasikan ke DB operasional.
- Merge/deploy tetap memerlukan instruksi pemilik terpisah; rekap semester dan rekap sekolah (DEC-12) tetap di luar scope.
