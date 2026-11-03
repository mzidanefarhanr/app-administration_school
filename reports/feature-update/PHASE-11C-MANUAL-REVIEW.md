# Panduan Review Manual — Subfase 11c (Tugas Operasional & Pengingat Internal Operator)

Subfase: 11c — Tugas Operasional dan Pengingat Internal Operator (DEC-44 IMPLEMENTED).  
Keputusan pemilik: **DEC-44 APPROVED** (D1–D10, 2026-11-03 WIB; pernyataan **"Saya setuju D1-D10"**).  
Laporan teknis lengkap: [`reports/feature-update/PHASE-11C-REPORT.md`](./PHASE-11C-REPORT.md); rencana kerja: [`reports/feature-update/PHASE-11C-PLAN.md`](./PHASE-11C-PLAN.md).  
Baseline induk: `main` pada `035987a`. Status teknis: **IMPLEMENTED & DIVERIFIKASI AGEN (2026-11-03)**.  
Status review pemilik: **APPROVED (#1–#18, 2026-11-03 WIB)** berdasarkan pernyataan: *"Pengujian nomor 1-18 sudah berhasil dan saya approved. Buatkan plan untuk lanjutan tahap/fase berikutnya."*  

---

## Lingkungan UAT Terisolasi (`.uat-phase10`)

- **URL Frontend (SPA)**: `http://127.0.0.1:14028`
- **URL Backend (API)**: `http://127.0.0.1:18028`
- **Halaman Uji Utama**: `http://127.0.0.1:14028/public/tasks` (Pusat Tugas Operasional & Pengingat Internal)
- **Database**: SQLite terisolasi `.uat-phase10/database.sqlite`
- **Cookie Session**: `school_phase10_session`
- **Akun Uji Tersedia**:
  - **`uat.admin`**: Admin Sistem — akses penuh melihat seluruh tugas operasional, membuat, mengedit, menyelesaikan, membatalkan, menghapus pengingat, dan melakukan dismissal tugas sistem.
  - **`uat.operator`**: Operator Sekolah — wewenang operasional penuh (`tasks.view` + `tasks.manage`).
  - **`uat.kepsek`**: Kepala Sekolah — akses eksekutif melihat seluruh daftar dan metrik tugas sekolah (`tasks.view`).
  - **`uat.guru`**: Guru Pengampu — akses personal melihat tugas pembelajaran dan perizinan pribadi (`tasks.view`), tanpa wewenang mutasi pengingat staf (`tasks.manage` ditolak 403).
  - Password tersimpan pada file lokal: `.uat-phase10/account-password.txt`.

### Panduan Menjalankan Server UAT

Bila ingin melakukan pengujian mandiri melalui peramban (*browser*), jalankan dua perintah berikut di dua jendela terminal terpisah:

```powershell
# 1. Terminal Backend API (dari folder laravel-api)
cd D:\xampp-htdocs\app-administration_school\laravel-api
php -S 127.0.0.1:18028 scripts/phase10-uat.php

# 2. Terminal Frontend SPA (dari folder vue-app)
cd D:\xampp-htdocs\app-administration_school\vue-app
$env:VITE_API_BASE_URL="http://127.0.0.1:18028"
npx vite --host 127.0.0.1 --port 14028
```

> [!IMPORTANT]
> Sesuai aturan **`DEC-27`**, kedua server UAT di atas harus dihentikan kembali (tekan `Ctrl+C`) setelah peninjauan selesai agar port 18028 dan 14028 kembali bebas.

---

## Checklist Pengujian Manual (#1–#18)

### Skenario 1: Akses Navigasi Menu "Tasks & Reminders"
- **Langkah**: Buka `http://127.0.0.1:14028` dan login sebagai **`uat.admin`** atau **`uat.operator`**. Periksa bilah menu navigasi samping (sidebar) pada kelompok menu **Home**.
- **Ekspektasi**: Terdapat menu baru **"Tasks & Reminders"** dengan ikon centang kotak (`pi pi-check-square`). Klik menu tersebut dan pastikan peramban membuka URL `/public/tasks` dengan judul *"Pusat Tugas Operasional & Pengingat Internal"*.

### Skenario 2: Integrasi Dashboard — Pintasan Cepat dari PendingTasksWidget
- **Langkah**: Buka halaman Beranda (`/`). Perhatikan widget *"Tugas & Tindakan Operasional"*. Di bagian bawah widget terdapat tombol baru *"Buka Pusat Tugas & Pengingat"*. Klik tombol tersebut.
- **Ekspektasi**: Peramban melakukan navigasi langsung menuju halaman `/public/tasks`.

### Skenario 3: 4 Kartu KPI Ringkasan Tugas Operasional
- **Langkah**: Pada bagian atas halaman `/public/tasks`, periksa 4 kartu metrik KPI:
  1. **Total Tugas Aktif**: menampilkan jumlah akumulasi tugas sistem dan pengingat manual yang belum selesai.
  2. **Tindakan Segera (High)**: menampilkan jumlah tugas berkategori prioritas tinggi/mendesak.
  3. **Perhatian / Jatuh Tempo (Warn)**: menampilkan jumlah tugas prioritas sedang atau mendekati batas waktu.
  4. **Selesai Hari Ini**: menampilkan jumlah tugas operasional yang telah diselesaikan pada hari kalender aktif.
- **Ekspektasi**: Angka-angka pada 4 kartu terisi secara akurat dan tidak ada placeholder demo/angka nol tak terdefinisi.

### Skenario 4: Tab "Semua Tugas" (All Tasks)
- **Langkah**: Pastikan tab aktif adalah **"Semua Tugas"**. Gulir daftar tugas.
- **Ekspektasi**: Daftar menampilkan gabungan tugas sistem (seperti antrean persuratan, izin, jurnal, audit kelengkapan profil, backup) dan pengingat manual staf dengan badge bidang/urgensi yang rapi.

### Skenario 5: Tab "Tugas Sistem" (System Tasks)
- **Langkah**: Klik tab kedua **"Tugas Sistem"**.
- **Ekspektasi**: Hanya tugas yang bersumber otomatis dari sistem (*Sistem*) yang ditampilkan. Setiap tugas menyertakan tombol aksi cepat seperti "Buka Modul" atau "Tandai Selesai".

### Skenario 6: Tab "Pengingat Manual" (Manual Reminders)
- **Langkah**: Klik tab ketiga **"Pengingat Manual"**.
- **Ekspektasi**: Hanya tugas pengingat internal staf (*Pengingat Staf*) yang ditampilkan dengan opsi pengelolaan (Selesai, Edit, Batalkan, Hapus).

### Skenario 7: Tab "Riwayat Selesai" (Completed History)
- **Langkah**: Klik tab keempat **"Riwayat Selesai"**.
- **Ekspektasi**: Menampilkan daftar tugas yang telah selesai ditindaklanjuti atau tugas sistem yang telah ditinjau (*dismiss*), lengkap dengan tanggal penyelesaian dan informasi nama staf yang menyelesaikan.

### Skenario 8: Filter Berdasarkan Domain / Kategori
- **Langkah**: Pada toolbar filter, buka dropdown **"Pilih Domain"**. Pilih domain **"Persuratan (Letters)"**, lalu ganti ke **"Pembelajaran (Teaching)"**.
- **Ekspektasi**: Daftar tugas langsung diperbarui dan hanya menyajikan tugas yang sesuai dengan bidang domain terpilih.

### Skenario 9: Filter Berdasarkan Tingkat Urgensi
- **Langkah**: Pada dropdown **"Pilih Urgensi"**, pilih **"Mendesak (High)"**.
- **Ekspektasi**: Hanya tugas dengan tag warna merah *"Mendesak"* yang muncul pada daftar.

### Skenario 10: Pencarian Kata Kunci Judul atau Uraian
- **Langkah**: Pada kolom input pencarian teks, ketikkan kata kunci (contoh: *"Dapodik"* atau *"Kurikulum"*), lalu tekan tombol **Enter**.
- **Ekspektasi**: Daftar tugas menyaring dan hanya menampilkan item yang mengandung kata kunci tersebut pada judul atau uraian.

### Skenario 11: Tombol Reset Filter
- **Langkah**: Ketika salah satu atau beberapa filter aktif, perhatikan munculnya tombol teks *"Reset"*. Klik tombol tersebut.
- **Ekspektasi**: Seluruh pilihan filter (domain, urgensi, teks pencarian) kembali bersih dan daftar tugas memuat seluruh data awal.

### Skenario 12: Pembuatan Pengingat Manual Baru (Create Reminder)
- **Langkah**: Klik tombol **"+ Buat Pengingat Baru"** di sudut kanan atas header. Pada modal dialog yang muncul, isi:
  - Judul: *"Koordinasi Berkas Lomba Sekolah Sehat"*
  - Kategori: *"Umum & Operasional"*
  - Prioritas: *"Tinggi / Mendesak (High)"*
  - Jatuh Tempo: Pilih tanggal 3 hari ke depan
  - Uraian: *"Menyiapkan berkas sertifikat dan dokumentasi lingkungan UKS."*
  Klik tombol **"Simpan Pengingat"**.
- **Ekspektasi**: Toast notifikasi hijau *"Pengingat baru berhasil ditambahkan"* muncul, modal dialog tertutup, kartu KPI Total Aktif dan Tindakan Segera bertambah, dan item tugas baru langsung muncul pada daftar.

### Skenario 13: Validasi Form Pembuatan Pengingat
- **Langkah**: Klik kembali tombol **"+ Buat Pengingat Baru"**, kosongkan kolom Judul, lalu klik tombol **"Simpan Pengingat"**.
- **Ekspektasi**: Dialog tidak tertutup, dan muncul pesan peringatan validasi: *"Judul tugas pengingat wajib diisi."*

### Skenario 14: Edit Catatan Pengingat Manual
- **Langkah**: Pada tab *"Pengingat Manual"*, cari pengingat yang baru dibuat pada Skenario 12. Klik tombol ikon pensil (*Edit Catatan*). Ubah judul menjadi *"Koordinasi Berkas Lomba Sekolah Sehat (Revisi)"* dan ubah prioritas menjadi *"Sedang / Perhatian (Warn)"*. Klik **"Simpan Pengingat"**.
- **Ekspektasi**: Notifikasi sukses muncul, dan data pada daftar tugas langsung terbarukan sesuai perubahan.

### Skenario 15: Tandai Selesai Pengingat Manual
- **Langkah**: Pada baris pengingat manual tersebut, klik tombol hijau bertuliskan **"Selesai"**.
- **Ekspektasi**: Muncul toast sukses *"Tugas Selesai"*. Tugas tersebut hilang dari tab aktif, kartu *"Selesai Hari Ini"* bertambah 1, dan saat membuka tab *"Riwayat Selesai"*, tugas tersebut tercatat dengan status *"Selesai"* beserta waktu saat ini.

### Skenario 16: Pembatalan dan Penghapusan Pengingat
- **Langkah**: Buat pengingat uji coba lain.
  1. Klik tombol ikon larangan (*Batalkan Tugas*). Pada dialog konfirmasi, klik **"Ya"**. Tugas berubah status menjadi dibatalkan.
  2. Klik tombol ikon tempat sampah (*Hapus Permanen*). Pada dialog konfirmasi merah, klik **"Ya"**.
- **Ekspektasi**: Notifikasi sukses muncul, dan baris pengingat tersebut terhapus permanen dari basis data.

### Skenario 17: Tindak Lanjut Tugas Sistem (Deep Link & Dismiss)
- **Langkah**: Pada tab *"Tugas Sistem"*:
  1. Pada salah satu tugas sistem (misalnya tugas disposisi surat atau izin), klik tombol **"Buka Modul"**. Pastikan router langsung membuka halaman kerja terkait.
  2. Kembali ke `/public/tasks`. Pada salah satu tugas sistem, klik tombol **"Tandai Selesai"**. Pada dialog konfirmasi, klik setuju.
- **Ekspektasi**: Tugas sistem tersebut di-dismiss dari daftar antrean aktif dan berpindah ke tab *"Riwayat Selesai"* dengan keterangan *"Tugas Sistem (Ditinjau)"*.

### Skenario 18: Pembatasan Wewenang Peran Guru & Kepatuhan DEC-27
- **Langkah**:
  1. Logout dan login kembali sebagai **`uat.guru`**. Buka halaman `/public/tasks`.
  2. Perhatikan tombol **"+ Buat Pengingat Baru"** tidak ditampilkan. Aksi mutasi (edit/hapus/dismiss) tidak dapat diakses oleh guru biasa.
  3. Setelah pengujian manual selesai, hentikan kedua terminal server UAT (tekan `Ctrl+C`).
- **Ekspektasi**: Otorisasi peran berjalan tegas dan aman (*fail-closed*). Kedua port `18028` dan `14028` kembali bebas listener sesuai aturan DEC-27.
