# Panduan Review Manual Subfase 03d — Penempatan dan Perpindahan Rombel UI

- Tanggal WIB: 2026-10-26
- Subfase: **03d — Penempatan dan Perpindahan Rombel via UI**
- Branch: `main`
- Baseline implementasi: `01c953e` (DEC-23)
- Status implementasi: **IMPLEMENTED & VERIFIED (AGEN)**
- Status keseluruhan 03d: **MANUAL ACCEPTANCE PENDING**
- Laporan teknis: `reports/feature-update/PHASE-03D-STUDENT-PLACEMENT.md`
- Rencana teknis: `reports/feature-update/PHASE-03D-PLAN.md`

> Checklist ini menguji alur penempatan dan perpindahan rombel pada UI nyata `Student.vue` di lingkungan UAT loopback. Server aktif di port `18021` (API) dan `14021` (SPA) menggunakan database sintetis disposable `school_compat_uat`. **Dilarang keras menyentuh database operasional `backdata_administrations`.**

---

## 1. Preparasi Pengujian

1. **Alamat Server UAT Aktif**:
   - Backend API: `http://127.0.0.1:18021`
   - Frontend SPA: `http://127.0.0.1:14021`
2. **Cara Menjalankan Browser**:
   Buka browser (misal Chrome / Edge) menggunakan profil terpisah agar cookie tidak bercampur:
   ```powershell
   Start-Process "chrome.exe" -ArgumentList "--user-data-dir=C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\runtime\browser-profile", "http://127.0.0.1:14021"
   ```
3. **Akun Pengujian Sintetis**:
   - Operator Sekolah: `uat.operator` (memiliki kemampuan `students.manage` + `students.view` + `students.export`).
   - Kepala Sekolah: `uat.kepsek` (view-only: `students.view` + `students.export`, tanpa `students.manage`).
4. **Cara Memperoleh Password Akun UAT**:
   Baca password dari file lokal privat di mesin Anda:
   ```powershell
   Get-Content "C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\secret_uat_password.txt"
   ```
   *(Atau reset password akun UAT via Artisan Tinker: `php artisan tinker --env=uat`)*.

---

## 2. Checklist Pengujian Berorientasi Fitur

| # | Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| 1 | Hak Akses View-Only (UI) | Login sebagai `uat.kepsek`, buka menu `/public/students`. | Tombol **Assign Rombel** (`pi pi-user-plus`) dan **Transfer Rombel** (`pi pi-arrow-right-arrow-left`) **TIDAK TAMPIL** pada seluruh baris tabel. | **PENDING** |
| 2 | Hak Akses View-Only (API Assign) | Menggunakan tab anonim/API client, kirim `POST /api/students/{id}/assign-rombel` dengan cookie sesi `uat.kepsek`. | Server menolak dengan status **403 Forbidden**. | **PENDING** |
| 3 | Hak Akses View-Only (API Transfer) | Kirim `POST /api/students/{id}/transfer-rombel` dengan cookie sesi `uat.kepsek`. | Server menolak dengan status **403 Forbidden**. | **PENDING** |
| 4 | Tombol Assign pada Unplaced | Login sebagai `uat.operator`, buka menu `/public/students`. Pilih filter Placement = `Unplaced`. | Setiap siswa unplaced menampilkan tombol biru outline `pi pi-user-plus` dengan tooltip `"Assign to Rombel"`. | **PENDING** |
| 5 | Tombol Transfer pada Placed | Pilih filter Placement = `Placed`. | Setiap siswa placed menampilkan tombol oranye outline `pi pi-arrow-right-arrow-left` dengan tooltip `"Transfer Rombel"`. | **PENDING** |
| 6 | Dialog Penempatan (Assign) | Klik tombol Assign Rombel pada salah satu siswa unplaced. | Dialog terbuka dengan judul `"Assign to Rombel"`. Menampilkan Nama Siswa, NIS, Tahun Ajaran Aktif (read-only), Tanggal Efektif (label `"Today (Asia/Jakarta server time)"`), dan Dropdown Target Class Rombel. | **PENDING** |
| 7 | Validasi Rombel Tahun Ajaran | Buka dropdown Target Class Rombel pada dialog Assign. | Hanya menampilkan rombel yang terdaftar pada tahun ajaran yang sedang aktif dipilih. | **PENDING** |
| 8 | Eksekusi Penempatan Berhasil | Pilih salah satu rombel tujuan, lalu klik tombol `"Assign Rombel"`. | Tombol menampilkan spinner loading sejenak; toast hijau `"Placement Success"` muncul; dialog tertutup; baris siswa berubah menjadi `Placed` dengan nama rombel terisi; angka rekapitulasi `Placed` bertambah 1 dan `Unplaced` berkurang 1. | **PENDING** |
| 9 | Dialog Pemindahan (Transfer) | Klik tombol Transfer Rombel pada salah satu siswa placed. | Dialog terbuka dengan judul `"Transfer Class Rombel"`. Menampilkan Nama Siswa, Current Class (rombel saat ini), Tahun Ajaran Aktif, Tanggal Efektif (Today WIB), Dropdown Target Class Rombel, dan Textarea Transfer Reason. | **PENDING** |
| 10 | Pengecualian Rombel Saat Ini | Buka dropdown Target Class Rombel pada dialog Transfer. | Rombel saat ini **tidak muncul** dalam daftar pilihan target. | **PENDING** |
| 11 | Eksekusi Pemindahan Berhasil | Pilih rombel baru, isi Transfer Reason (misal: "Class balancing"), lalu klik tombol `"Transfer Student"`. | Toast hijau `"Transfer Success"` muncul; dialog tertutup; nama rombel pada baris siswa otomatis terupdate ke rombel baru; rekapitulasi per rombel otomatis diperbarui oleh server. | **PENDING** |
| 12 | Preservasi Riwayat & Status | Klik tombol View Detail (`pi pi-eye`) pada siswa yang baru dipindahkan. | Rombel baru aktif; riwayat rombel lama tersimpan dengan tanggal keluar hari ini; status siswa tetap Aktif (tidak berubah menjadi Mutasi Keluar). | **PENDING** |
| 13 | Konflik Konkurensi (409) | Buka siswa yang sama pada dua tab browser. Di tab 1 lakukan Transfer ke Rombel B. Di tab 2 (tanpa refresh) coba lakukan Transfer ke Rombel C. | Tab 2 ditolak dengan status **409 Conflict**; muncul dialog `"Data Conflict Detected"` yang memberitahukan data telah diubah operator lain; input pengguna dipertahankan dan tersedia tombol `"Reload Latest"`. | **PENDING** |
| 14 | Validasi Form Kosong (422) | Buka dialog Assign/Transfer, kosongkan pilihan rombel tujuan, lalu klik tombol submit. | Muncul pesan error validasi inline berwarna merah `"Target rombel is required."` di bawah dropdown; request tidak dikirim ke server. | **PENDING** |
| 15 | Preservasi Filter & Ekspor CSV | Lakukan Assign atau Transfer saat tabel sedang dalam kondisi pencarian atau filter tertentu. Klik tombol Export. | Filter pencarian dan halaman tabel tidak ter-reset pasca-mutasi. File CSV yang diunduh mencerminkan penempatan rombel yang baru. | **PENDING** |

---

## 3. Form Konfirmasi Persetujuan Pemilik

*(Diisi oleh pemilik setelah menjalankan peninjauan)*

Status: **PENDING**.

- [ ] Seluruh checklist #1–#15 disetujui (APPROVED).
- Tanggal pelaksanaan WIB:
- Catatan / feedback pemilik:
