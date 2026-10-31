# Panduan Review Manual — Subfase 06b (Naik Kelas, Tinggal Kelas, dan Lulus)

Subfase: 06b — preview tanpa mutasi, commit atomik ber-token, riwayat batch, dan wizard Academic → Progression.
Keputusan: **DEC-30 APPROVED** (D1–D9, 2026-10-31 WIB). Laporan teknis: `reports/feature-update/PHASE-06B-REPORT.md`.
Baseline induk: `main` pada `6dcd432` (pencatatan DEC-30); implementasi `c1dde67`. Status: **IMPLEMENTED — agent-verified; OWNER REVIEW PENDING**.

## Status penerimaan pemilik

- Belum ada pernyataan penerimaan untuk 06b. Checklist #1–#14 di bawah menunggu hasil review pemilik.
- Setelah pemilik menyatakan penerimaan, hasil dicatat pada commit dokumentasi tersendiri; commit/push implementasi bukan penerimaan.
- Penerimaan tidak mengotorisasi merge, deploy, migrasi data operasional/MariaDB, perubahan dependency/service, atau 06c.

## Lingkungan UAT (terisolasi)

- SPA: `http://127.0.0.1:14024`
- API: `http://127.0.0.1:18024`
- Data: `.uat-phase06` SQLite; cookie `school_phase06_session`
- Launcher: `laravel-api/scripts/phase06-uat.php`
- Akun: **`uat.operator`** (dapat preview/commit) dan **`uat.kepsek`** (view-only). Password hanya ada di runtime privat `.uat-phase06/account-password.txt`; tidak dicetak di dokumen.

Status server: SPA dan API **tetap berjalan** untuk review 06b. Setelah penerimaan, server dihentikan sesuai DEC-27; data `.uat-phase06` dipertahankan.

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

Angka acuan saat ini: **153 siswa** (150 `UAT-P06`, termasuk 20 `UAT-P06B`); 12 kelas; 10 guru aktif + 1 separated; 4 staf; periode aktif **2026/2027 · Ganjil**; tahun 2026/2027 dan 2027/2028 **unlocked**; **2 batch progresi**.

Keadaan setelah pengujian agen:

- Batch **#1 committed** oleh `uat.operator`: 2 promote, 1 graduate, 1 retain. Keanggotaan target baru id 126/127; siswa 148 berstatus `Lulus`; siswa 153 retain tetap Unplaced di tahun tujuan.
- Batch **#2 preview** oleh `uat.operator` (sisa uji `409`; versi sudah basi sehingga tokennya tidak dapat di-commit). Batch ini sengaja dipertahankan sebagai riwayat preview dan untuk memeriksa filter visibilitas.
- Tidak ada reset data 06a. Jangan menguji pada database operasional, `.uat-phase05`, atau MariaDB.

## Checklist

### 1. Isolasi, login, dan keadaan awal
- Login sebagai `uat.operator`, buka **Academic → Progression**.
- Periksa banner/lingkungan: halaman **Student Progression** dengan empat langkah; tidak ada banner **View-only access**.
- Di **Batch History** (setelah memilih tahun), harapkan batch **#1 committed** dan **#2 preview** terlihat karena keduanya milik operator.
- Harapkan tidak ada error console/network.

### 2. Langkah Years dan opsi
- Pilih **Source Academic Year 2026/2027** dan **Target Academic Year 2027/2028**; tombol **Continue to Students** aktif setelah keduanya dipilih.
- Harapkan tanggal tahun tampil (01 Jul 2026 – 30 Jun 2027 dan 01 Jul 2027 – 30 Jun 2028); tidak ada peringatan lock karena kedua tahun unlocked.
- Coba pilih tahun sumber = tujuan: tombol Continue nonaktif/server menolak `422`.

### 3. Roster dan eligibilitas
- Pilih kelas sumber **X-1**; periksa kolom Student, Source Class, Eligibility, Decision, Target Class.
- Harapkan siswa `UAT-P06B Progression Student 0001–0006` dan siswa `UAT-P06` lain di X-1 tampil; pencarian nama/NIS/NISN berfungsi.
- Harapkan siswa `0005` dan `0006` tidak eligible dengan alasan sudah memiliki keanggotaan di tahun tujuan (konflik tidak boleh tertimpa).
- Ganti filter kelas (mis. X-2, XI-1): keputusan yang sudah dipilih pada kelas sebelumnya **tetap tersimpan** dan ringkasan **Selected decisions** tidak kembali ke nol.

### 4. Keputusan per siswa dan massal
- Terapkan **Promote** massal dengan target class (mis. `XI-1`), lalu ubah satu siswa menjadi **Retain**, satu menjadi **Graduate**, dan satu menjadi **Skip**.
- Harapkan ringkasan Promote/Retain/Graduate menghitung keputusan di luar filter saat ini; siswa `skip` tidak dikirim sebagai keputusan.
- Harapkan **promote** tanpa target class ditandai invalid dan tidak dapat dilanjutkan.

### 5. Preview tanpa mutasi dan konflik
- Klik **Review Preview** lalu **Create Preview**.
- Harapkan ringkasan jumlah keputusan, token berlaku 30 menit, dan tombol commit tersedia; tabel siswa/keanggotaan di database **belum berubah**.
- Uji konflik: sertakan siswa `0005`/`0006` atau siswa yang sudah punya keanggotaan target → preview ditolak `422` dengan item konflik dan tombol kembali; tidak ada batch baru.
- Harapkan preview yang valid tercatat sebagai batch `preview` dan hanya terlihat oleh pembuatnya.

### 6. Commit campuran dan hasil
- Commit preview yang valid (mis. 2 promote, 1 graduate, 1 retain).
- Harapkan hasil per siswa: promote membuat keanggotaan baru di kelas tujuan (status `Aktif`, tanggal masuk 2027-07-01); graduate mengubah keanggotaan sumber menjadi `Lulus` dengan tanggal keluar/lulus; retain tidak membuat keanggotaan target; skip tidak berubah.
- Buka **Students** dengan tahun 2026/2027: kartu **Graduation Students** menghitung siswa `Lulus` (bukan status lain); cari siswa graduate dan periksa status/tanggal.
- Buka **Batch History**: batch baru berstatus **committed**, detail menampilkan item dan versi baru; tidak ada token pada tampilan/API.

### 7. Replay idempoten (agent-verified; opsional)
- Token preview hanya ditampilkan sekali di langkah Preview. Untuk menguji replay, gunakan DevTools: salin `data.token` dari respons `POST /api/academic/progression/preview`, commit sekali, lalu kirim `POST /api/academic/progression/commit` dengan token yang sama.
- Harapkan respons kedua `200` dengan `replayed: true`, item sama, dan **tidak** ada keanggotaan/audit baru. Jika tidak melakukan langkah ini, tandai **SKIP**; agen sudah memverifikasi lewat API.

### 8. Konflik `409` dan Reload & Preview Again (agent-verified; opsional)
- Skenario membutuhkan perubahan versi di luar UI (mis. DevTools/DB UAT): buat preview, ubah `students.version` atau data terkait, lalu commit.
- Harapkan dialog **Data Conflict Detected** memuat daftar siswa/konflik, menyatakan tidak ada data yang diubah, dan tombol **Reload & Preview Again** membuat preview baru dengan data terbaru.
- Bila tidak ingin memutasi versi, tandai **SKIP**; agen sudah memverifikasi lewat API dengan batch #2.

### 9. Lock tahun
- Tahun 2027/2028 saat ini unlocked. Kunci target (School Years) lalu coba preview/commit yang mengandung **promote**: harapkan `422`/peringatan bahwa target harus unlocked.
- Tahun sumber 2026/2027 adalah tahun aktif dan **tidak dapat dikunci** menurut 06a; aturan sumber-terkunci untuk **graduate** tertutup tes otomatis dan tidak dapat diuji di browser.
- Retain/skip tanpa promote/graduate tetap boleh dengan tahun yang lock sesuai D4.

### 10. View-only (kepsek)
- Login sebagai `uat.kepsek`; buka **Academic → Progression**.
- Harapkan banner **View-only access**; langkah Students/Preview tidak dapat membuat preview/commit.
- **Batch History** hanya menampilkan batch **#1 committed**; batch **#2 preview** milik operator **tidak** terlihat.
- Lewat API/DevTools: `POST /preview`, `POST /commit`, dan `GET /batches/2` → `403`; `GET /batches/1` → `200`.
- Login kembali sebagai `uat.operator` untuk langkah lain.

### 11. Riwayat, detail, dan guard hapus tahun
- Sebagai operator, buka detail batch #1: harapkan item hasil per siswa, kelas tujuan, versi baru, status, dan tanggal; tidak ada token/decisions mentah.
- Sebagai akun operator lain (bila tersedia) atau kepsek, detail preview #2 → `403`; list tidak memuat preview #2.
- Coba hapus tahun yang direferensikan batch (School Years, `master.manage`): harapkan `422` "This record is still being referenced by N progression batch(es)." dan tahun tetap ada.

### 12. Audit
- Periksa tabel `activities`/halaman audit bila tersedia.
- Harapkan batch create/commit tercatat tanpa token/decisions; setiap keanggotaan baru/perubahan graduate mencatat `student_id`, `school_rombel`, `student_status_id`, tanggal keluar/lulus, dan `version`; **tidak** ada nama/NIK/alamat pada audit baru.
- Catatan: baris audit lama #22/#24 dibuat sebelum allowlist NIK diperbaiki dan masih memuat NIK sintetis; audit append-only tidak diubah.

### 13. Kartu Graduated di Students
- Buka **Students** sebagai operator; pilih tahun 2026/2027.
- Harapkan kartu **Graduation Students** menghitung status `Lulus` (id 6), bukan `Mengundurkan Diri` (id 4); setelah commit agen angkanya 1.
- Pastikan siswa retain tetap tampil Unplaced di tahun tujuan dan siswa promote punya kelas target.

### 14. English, 390 px, console, dan lifecycle
- Periksa seluruh label/pesan 06b (Years, Students & Decisions, Preview, Result, riwayat, dialog commit/konflik): English.
- Emulasikan lebar 390 px: wizard, tabel (scroll horizontal), dan dialog tidak melebar keluar viewport.
- Harapkan tidak ada error console/network selain respons `409`/`403` yang disengaja saat menguji konflik/akses.
- Setelah checklist selesai dan pemilik menyatakan penerimaan, server UAT 14024/18024 dihentikan sesuai DEC-27; data `.uat-phase06` dipertahankan.

## Cara melaporkan hasil

Untuk setiap nomor, tulis: **PASS/FAIL/SKIP**, langkah yang dilakukan, hasil aktual (angka/pesan), dan screenshot bila ada. Sertakan pesan console/network bila muncul error. Jangan mengetes mutasi pada data operasional; seluruh skenario menggunakan `.uat-phase06`.

## Penutupan setelah penerimaan

- Penerimaan dicatat pada commit dokumentasi tersendiri, bukan pada commit implementasi.
- Server UAT dihentikan setelah penerimaan; data dan password runtime dipertahankan di `.uat-phase06`.
- Jangan mengubah Apache/MariaDB/service/dependency; jangan migrasikan data ke DB operasional.
- 06c tidak dimulai tanpa rencana dan keputusan pemilik terpisah.
