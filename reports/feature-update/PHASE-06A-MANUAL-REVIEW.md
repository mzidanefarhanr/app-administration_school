# Panduan Review Manual — Subfase 06a (Periode Akademik dan Rombel)

Subfase: 06a — periode aktif eksplisit, lock tahun ajaran, CRUD kelas, wali kelas dari profil pegawai, dan penegakan lock pada penempatan siswa.
Keputusan: **DEC-29 APPROVED** (D1–D8, 2026-10-31 WIB). Laporan teknis: `reports/feature-update/PHASE-06A-REPORT.md`.
Baseline induk: `main` pada `25875c9`; implementasi+dokumentasi 06a: `d8d9874`. Status: **IMPLEMENTED — agent-verified; OWNER APPROVED 2026-10-31 WIB — checklist #1–#12**.

## Status penerimaan pemilik

- Pernyataan pemilik 2026-10-31 WIB: **"Pengujian nomor 1-12 sudah berhasil dan saya approved."**
- Cakupan: seluruh skenario #1–#12 pada lingkungan UAT terisolasi `.uat-phase06`. Pernyataan pemilik adalah satu-satunya bukti penerimaan; agen tidak mengulang skenario.
- Batas: penerimaan tidak mengotorisasi merge, deploy, migrasi data legacy/MariaDB/produksi, perubahan dependency/service, 06b, atau 06c. Merge/deploy tetap tidak diotorisasi.

## Lingkungan UAT (terisolasi)

- SPA: `http://127.0.0.1:14024`
- API: `http://127.0.0.1:18024`
- Data: `.uat-phase06` SQLite; cookie `school_phase06_session`
- Launcher: `laravel-api/scripts/phase06-uat.php`
- Akun: **`uat.operator`** (dapat mengelola kelas/periode) dan **`uat.kepsek`** (view-only). Password hanya ada di runtime privat `.uat-phase06/account-password.txt`; tidak dicetak di dokumen.

Status server: SPA (PID 19240) dan API (PID 2388) **dihentikan 2026-10-31** setelah penerimaan, sesuai DEC-27. Command line kedua proses diverifikasi sebelum `taskkill /T /F`; port 14024/18024 terverifikasi bebas; Apache 80/443 dan MariaDB 3306 tidak disentuh; data `.uat-phase06` dipertahankan.

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

Angka acuan saat ini: 133 siswa (130 `UAT-P06`); 12 kelas (6 per tahun); 10 guru aktif + 1 separated; 4 staf; periode aktif **2026/2027 · Ganjil**; tahun **2027/2028 locked** dengan alasan `UAT review lock 2026-10-31`; tahun 2026/2027 unlocked.

Catatan keadaan setelah pengujian agen:

- Kelas `X-3 UAT` yang dibuat saat uji CRUD sudah dihapus kembali; baris auditnya tetap ada.
- Tahun 2027/2028 sengaja ditinggalkan dalam keadaan locked agar checklist #2/#3 dapat langsung diperiksa. Bila Anda membuka lock untuk skenario lain, kunci kembali dengan alasan agar keadaan akhir konsisten.
- Jangan menguji pada database operasional, `.uat-phase05`, atau MariaDB; seluruh langkah memakai `.uat-phase06`.

## Checklist

### 1. Periode aktif eksplisit dan tunggal
- Login sebagai `uat.operator`, buka **Academic → School Years**.
- Periksa kartu **Active Period**: tahun `2026/2027`, semester `Ganjil`, tombol **Save Active Period**.
- Ubah sementara semester ke `Genap` lalu simpan, lalu kembalikan ke `Ganjil` dan simpan lagi (opsional).
- Harapkan: hanya satu tahun ditandai aktif; halaman Students dan Classes memakai tahun aktif dari server; tidak ada default dari kalender.
- Buka **Academic → Classes**: tag `Active period: 2026/2027 · Ganjil` tampil.

### 2. Lock tahun dan perlindungan tahun aktif
- Di **School Years**, coba kunci tahun aktif 2026/2027 (tombol lock tahun aktif nonaktif; API harus menolak).
- Kunci tahun **2027/2028** dengan alasan (mis. `Review lock manual`), lalu **Unlock** dengan alasan, lalu kunci kembali.
- Harapkan: tahun aktif tidak dapat dikunci; lock/unlock memerlukan alasan; status Tag `Locked` berubah; tidak ada error console.

### 3. Tahun locked menolak perubahan kelas dan penempatan
- Dengan tahun 2027/2028 locked, buka **Classes**, pilih filter tahun 2027/2028.
- Harapkan: tag `Locked`; tombol New Class nonaktif (tidak ada tahun unlocked lain yang dipilih); Edit/Delete nonaktif atau ditolak.
- Buka **Students**, pilih tahun 2027/2028: banner lock tampil; tombol Assign/Transfer nonaktif; dialog penempatan menjelaskan tahun locked.
- Bila memakai API/DevTools: create/update/delete kelas dan assign/transfer pada tahun locked menghasilkan `422` dengan pesan lock, bukan `200`.

### 4. Wali kelas dari profil guru aktif
- Unlock tahun 2027/2028 (atau gunakan tahun 2026/2027 yang unlocked).
- **New Class** dengan nama unik (mis. `UAT Review 01`), tingkat valid, jurusan opsional, dan wali kelas cari `Teacher`.
- Harapkan: hanya **guru aktif bertipe Pendidik** muncul (`UAT-P06 Teacher 01–10`); staf dan guru separated **tidak** muncul; kelas tersimpan dengan nama wali yang dipilih.
- Periksa tabel Classes: kolom Homeroom Teacher menampilkan nama guru.

### 5. Kelas tanpa jurusan dan tanpa wali
- **New Class** dengan nama unik lain (mis. `UAT Review 02`), tanpa jurusan dan tanpa wali.
- Harapkan: tersimpan (`201`/dialog tertutup), kolom jurusan dan wali kosong/tanda `—`; tidak ada error validasi wajib untuk field opsional.
- Kelas ini dapat dipakai untuk skenario hapus kelas kosong (#8).

### 6. Nama unik per tahun dan nama sama lintas tahun
- Di tahun yang sama, buat kelas dengan nama yang sudah ada (mis. `X-1` atau beda kapitalisasi `x-1`).
- Harapkan: ditolak `422` "The class name has already been taken for this school year."
- Buat nama yang sama di tahun berbeda (mis. `UAT Review 01` juga di tahun lain) setelah tahun tujuan unlocked.
- Harapkan: diterima karena unik per tahun, bukan global.

### 7. Edit kelas dan konflik versi (`409`)
- Edit kelas `UAT Review 01`: ubah nama/wali, simpan; harapkan versi naik.
- Uji konflik: buka edit kelas yang sama di dua tab; simpan di tab A; simpan perubahan basi di tab B.
- Harapkan: tab B menampilkan dialog **Data Conflict Detected** dengan tombol **Reload Latest**; memuat ulang menampilkan data terbaru (versi baru), bukan menimpa diam-diam.

### 8. Hapus kelas
- Hapus kelas kosong `UAT Review 02` (hasil #5) dengan konfirmasi.
- Harapkan: kelas terhapus (soft delete); data hilang dari daftar aktif.
- Coba hapus kelas yang sudah memiliki riwayat keanggotaan (mis. `X-1` pada tahun 2026/2027).
- Harapkan: ditolak `422` dengan pesan bahwa kelas masih memiliki riwayat; riwayat tidak rusak.

### 9. Students memakai tahun aktif server
- Buka **Students** sebagai `uat.operator`.
- Harapkan: default tahun `2026/2027` (periode aktif), tag `Active period: 2026/2027 · Ganjil`; total 133 / placed 73 / unplaced 60 (angka dapat berubah bila Anda melakukan penempatan).
- Pilih tahun 2027/2028 yang locked: tag `Locked` dan banner alasan lock; tombol Assign/Transfer nonaktif.

### 10. Akses view-only
- Login sebagai `uat.kepsek`; buka **Classes** dan **School Years**.
- Harapkan: dapat melihat data, tetapi tidak ada tombol New/Edit/Delete/lock/simpan; percobaan menulis lewat API ditolak `403` (server menegakkan, bukan hanya UI).
- Login kembali sebagai `uat.operator` untuk langkah lain.

### 11. Audit
- Buka halaman audit/Activities bila akun memiliki `audit.view`; bila tidak tersedia di UI, agen sudah memeriksa langsung tabel `activities` pada `.uat-phase06`.
- Harapkan: perubahan periode aktif, lock/unlock/relock (termasuk alasan dan aktor), serta create/update/delete kelas tercatat dengan before/after/new; tidak ada password/token/data pribadi.
- Contoh yang sudah diverifikasi agen: periode 2026/2027 · Ganjil; lock tahun 2027/2028 dengan alasan `UAT review lock 2026-10-31`; create/edit/delete `X-3 UAT` beserta versi.

### 12. English dan tampilan 390 px
- Periksa semua halaman 06a (School Years, Classes, Students, dialog lock/konflik/hapus): label dan pesan berbahasa English.
- Emulasikan lebar 390 px (DevTools device toolbar).
- Harapkan: tidak ada overflow level halaman; tab group Students membungkus; dialog Classes muat di viewport (sekitar 371 px); tombol utama terjangkau.
- Catat bila ada error console/network.

## Cara melaporkan hasil

Untuk setiap nomor, tulis: **PASS/FAIL**, langkah yang dilakukan, hasil aktual (angka/pesan), dan screenshot bila ada. Sertakan pesan console/network bila muncul error. Jangan mengetes mutasi pada data operasional; seluruh skenario menggunakan `.uat-phase06`.

## Penutupan setelah penerimaan

- Setelah checklist lulus dan pemilik menyatakan penerimaan, server UAT 14024/18024 dihentikan sesuai DEC-27; data `.uat-phase06` dipertahankan untuk jejak.
- Jangan mengubah Apache/MariaDB/service/dependency; jangan migrasikan data ke DB operasional.
- 06b/06c tidak dimulai tanpa rencana dan keputusan pemilik terpisah.
