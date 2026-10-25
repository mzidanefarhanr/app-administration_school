# Rencana Pekerjaan Sekolah Berikutnya (Rekomendasi Pasca-Fase 03)

Tanggal: 2026-10-26 (WIB). Branch: `main`.
Baseline kode: `016b5c1` (Pencatatan penerimaan manual 03c dan penutupan Fase 03).
Status proyek sekolah:
- **Fase 00**: PARTIAL (source & environment audit selesai).
- **Fase 01**: **DITERIMA LENGKAP & DITUTUP** (01a, 01b, 01c).
- **Fase 02**: **DITERIMA LENGKAP & DITUTUP** (02a, 02b, 02c).
- **Fase 03**: **DITERIMA LENGKAP & DITUTUP** (03a, 03b, 03c).
- **Lingkungan UAT**: Aktif terisolasi pada `127.0.0.1:18021` (API) dan `127.0.0.1:14021` (SPA) di atas PHP global 8.2.35 dan Node portable 24.22.1.
- Status App Gudang: Terpisah total; status dan pola Blade App Gudang tidak berlaku untuk aplikasi sekolah.

> **Pernyataan Batas:** Dokumen ini merupakan rancangan analisis dan rekomendasi scope untuk ditinjau oleh pemilik. **Tidak ada pekerjaan implementasi yang dijalankan pada sesi ini.**

---

## 1. Analisis Posisi Kandidat Subfase 03d vs Roadmap Utama

### 1.1 Latar Belakang Kandidat 03d
Pada implementasi Subfase 03b dan 03c, siswa baru dapat dicatat tanpa rombel dan berstatus `Unplaced` pada daftar siswa tahun ajaran aktif (DEC-22). Di backend, trigger database dan relasi `student_rombels` (Subfase 03a) telah siap menerima keanggotaan rombel berbasis `student_id`. Namun, pada UI `Student.vue`, aksi interaktif untuk **menempatkan siswa unplaced ke dalam rombel** atau **memindahkan siswa antar-rombel** dicatat sebagai kandidat di luar scope 03c (DEC-22 #5).

### 1.2 Perbandingan Dua Opsi Jalur Berikutnya

```
                  +----------------------------------------------+
                  |           FASE 03 SELESAI & DITUTUP          |
                  +----------------------------------------------+
                                         |
                   PILIHAN JALUR IMPLEMENTASI BERIKUTNYA
                                         |
            +----------------------------+----------------------------+
            |                                                         |
            v                                                         v
    [OPSI 1: KANDIDAT 03d]                                    [OPSI 2: FASE 04a]
Aksi Penempatan Rombel UI                                 Schema Profil Pegawai & Akun
----------------------------------                        ----------------------------------
- Tutup alur operasional siswa                            - Masuk domain personalia sekolah
- Tempatkan siswa unplaced ke rombel                      - Hubungan pegawai-akun (DEC-04)
- Mutasi kelas individual siswa                           - Review subject_id (non-guru)
- Scope kecil, dependensi siap                            - Sesuai urutan numerik roadmap
- UI selesai sebelum pindah domain                        - Menyiapkan wali kelas utk Fase 06
```

#### Opsi 1 (Direkomendasikan): Eksekusi Subfase 03d Terlebih Dahulu
- **Argumen**:
  1. Melengkapi siklus hidup harian pendataan siswa (Create Siswa -> Unplaced -> Tempatkan ke Rombel -> Edit/Arsip). Tanpa 03d, siswa unplaced yang baru diinput di UAT/operasional hanya bisa ditempatkan via seeder atau API langsung.
  2. Scope terfokus dan kecil: hanya menambahkan endpoint/aksi penempatan rombel individual siswa (assign & move) dengan validasi trigger 03a (maksimal 1 rombel aktif per tahun ajaran), dialog penempatan di UI `Student.vue`, serta audit log `student_rombel`.
  3. Memastikan modul siswa 100% tuntas sebelum berganti domain data ke guru/pegawai.

#### Opsi 2: Langsung ke Fase 04 (Guru dan Pegawai)
- **Argumen**:
  1. Mengikuti urutan roadmap baseline M1 (Fase 01-06).
  2. Guru/pegawai dibutuhkan untuk data master wali kelas pada rombel (`class_teach_id` di `school_rombels`).
  3. Penempatan rombel siswa secara komprehensif (termasuk kenaikan kelas massal dan pembagian kelas tahun baru) dapat ditunda dan disatukan di **Fase 06 (Siklus Tahun Ajaran dan Rombel)**.

---

## 2. Rincian Scope, Dependensi, dan Keputusan Tiap Opsi

### 2.1 Bila Memilih Opsi 1: Subfase 03d (Aksi Penempatan Rombel UI)
- **Scope Teknis**:
  - Backend: `POST /api/students/{id}/assign-rombel` (menempatkan siswa unplaced ke rombel pada tahun ajaran aktif); `POST /api/students/{id}/move-rombel` (memindahkan siswa dari rombel lama ke rombel baru pada tahun ajaran aktif dengan menonaktifkan keanggotaan lama secara atomik).
  - Validasi: Penegakan aturan 1 rombel aktif per tahun ajaran via database trigger dan FormRequest; otorisasi `students.manage`.
  - Frontend: Tombol/dialog "Assign Rombel" pada baris siswa Unplaced di `Student.vue`; dialog pemindahan rombel pada siswa Placed; pembaruan rekap dan filter tabel pasca-mutasi.
- **Dependensi**: Telah terpenuhi (Fase 01, Fase 02, Subfase 03a, 03b, 03c).
- **Keputusan Pemilik yang Dibutuhkan**:
  - Konfirmasi format alasan perpindahan rombel (apakah wajib string alasan seperti arsip).
  - Konfirmasi hak akses penempatan rombel: apakah cukup `students.manage` atau membutuhkan izin khusus.

---

### 2.2 Bila Memilih Opsi 2: Fase 04 (Guru dan Pegawai)
- **Subfase 04a — Schema Profil dan Hubungan Akun**:
  - **Scope Teknis**:
    - Audit tabel `employees` dan `users`: relasi pegawai dengan akun pengguna (apakah pegawai wajib memiliki akun `users` atau opsional).
    - Review kolom `subject_id` di tabel `employees`: staf tata usaha, pustakawan, dan pegawai non-guru tidak mengajar mata pelajaran, sehingga `subject_id` harus diubah menjadi `nullable`.
    - Pembedaan guru vs tenaga kependidikan (pegawai) berdasarkan `employee_type_id` / `employment_id`.
    - Backfill data aman tanpa merusak relasi NIK yang ada.
  - **Dependensi**: Fase 01, Fase 02, dan persetujuan **DEC-04**.
  - **Keputusan Pemilik yang Dibutuhkan (DEC-04)**:
    1. Apakah setiap pegawai wajib memiliki akun login `users`, atau profil pegawai boleh ada tanpa akun sistem? (Rekomendasi: profil pegawai boleh tanpa akun login; akun hanya dibuat jika pegawai bertugas mengoperasikan sistem).
    2. Apakah kolom `subject_id` disetujui menjadi `nullable` untuk mengakomodasi pegawai non-guru? (Rekomendasi: ya, jadikan `nullable`).

- **Subfase 04b — CRUD Pegawai dan Menu Aktif**:
  - **Scope Teknis**:
    - Backend: FormRequest validasi pegawai (NIK 16 digit unik nullable/opsional, nama, jenis pegawai, status aktif, audit privacy).
    - Optimistic concurrency locking 409 pada data pegawai (seperti halnya siswa).
    - Frontend: Mengaktifkan menu `Teachers` dan `Employees` di sidebar yang saat ini masih berupa placeholder `#` mengarah ke halaman nyata dengan DataTable server-side.

---

## 3. Pemisahan Tegas: Jalur Fitur vs Jalur Modernisasi

Pekerjaan pengembangan fitur aplikasi dan modernisasi infrastruktur/dependency **wajib dipisahkan** agar tidak menimbulkan regresi ganda yang membingungkan evaluasi pengujian:

| Dimensi | Jalur Fitur (Domain Sekolah) | Jalur Modernisasi (Infrastruktur) |
|---|---|---|
| **Fokus Utama** | Penyelesaian modul domain: Rombel Siswa (03d) atau Pegawai (04a/04b). | Transisi framework dari Laravel 10 (EOL) menuju Laravel 12. |
| **Lingkungan Runtime** | Menggunakan PHP global 8.2.35 operasional dan Node 24.22.1 portable yang telah diverifikasi kompatibel 100%. | Mempersiapkan keseragaman versi framework jangka panjang sebelum PHP 8.2 EOL (31 Des 2026). |
| **Dependensi** | Lockfile Composer dan npm **dipertahankan**. Tidak ada package yang di-update. | Update terarah pada paket berrisiko (misal `axios`, `form-data`) dan dependensi Laravel 12. |
| **Eksekusi** | Dijalankan per subfase dengan bukti UAT fungsional. | Dijalankan dalam sesi khusus arsitektur dengan koordinasi bersama tim App Gudang dan pemilik. |

---

## 4. Kriteria Penerimaan dan Rencana Pengujian Sintetis

Untuk pekerjaan subfase berikutnya (baik 03d maupun 04a), berlaku kriteria pengujian berikut:
1. **Automated Unit & Feature Tests**:
   - Dijalankan pada SQLite in-memory murni melalui fail-closed guard `verify-isolation.php testing`.
   - Menghasilkan 0 failure dan 0 error.
2. **MariaDB Concurrency & Trigger Tests** (bila ada trigger baru):
   - Dijalankan terarah hanya terhadap database sintetis disposable `school_compat_uat`.
3. **Frontend Tests & Build**:
   - `node --test` unit tests lulus menggunakan Node portable 24.22.1.
   - `npm run build` lulus tanpa error kompilasi.
   - Lint non-mutasi tidak menambah error baru.
4. **Smoke Test UAT Terarah**:
   - Server berjalan pada port terisolasi `127.0.0.1:18021` (API) dan `127.0.0.1:14021` (SPA) dengan bind loopback dan strict port.
   - Pengujian peran (`uat.operator`, `uat.kepsek`, `uat.waka`) memverifikasi penegakan izin 200 vs 403.
   - Checklist manual disediakan untuk peninjauan pemilik.

---

## 5. Pertanyaan Keputusan untuk Pemilik

Sebelum memulai implementasi subfase berikutnya, pemilik diharapkan memberikan keputusan atas pertanyaan berikut:

1. **Arah Subfase Berikutnya**:
   - **Pilihan 1 (Rekomendasi)**: Buka Subfase **03d (Aksi Penempatan Rombel UI)** untuk melengkapi alur penempatan siswa unplaced dan mutasi rombel di `Student.vue` sebelum beralih modul.
   - **Pilihan 2**: Langsung buka Subfase **04a (Schema Profil Pegawai & Hubungan Akun)** sesuai urutan roadmap numerik, dan menunda aksi rombel siswa ke Fase 06.
2. **Keputusan DEC-04 (jika memilih Opsi 2)**:
   - Apakah profil pegawai/guru boleh dicatat tanpa akun login?
   - Apakah `subject_id` disetujui menjadi nullable untuk staf non-guru?
