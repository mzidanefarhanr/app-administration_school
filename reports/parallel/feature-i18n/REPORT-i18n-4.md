# Laporan i18n-4 — Data Master & Manajemen Akun (Worktree A)

- **Branch:** `feature/i18n`
- **Parent SHA:** `03535c2494ea4e9f7831d3cae866a2f7c0879685`
- **Dokumen Acuan:** [`docs/I18N.md`](file:///D:/xampp-htdocs/school-i18n/docs/I18N.md) dan [`reports/parallel/feature-i18n/GLOSSARY.md`](file:///D:/xampp-htdocs/school-i18n/reports/parallel/feature-i18n/GLOSSARY.md).

---

## 1. Yang Dikerjakan

1. **Namespace Terjemahan Baru:**
   - `vue-app/src/i18n/locales/{id,en}/master.json`: 268 kunci terjemahan mencakup:
     - `master.common`: label umum CRUD (*name, code, description, action, save, cancel, yes, no, export, search, loading, empty, deleteConfirmTitle, deleteConfirm, deleteSelectedConfirm, deleteSuccess, deleteError, exportSuccess, reloadLatest*).
     - `master.educationLevels`: Jenjang Pendidikan.
     - `master.educationSchools`: Sekolah.
     - `master.phaseStatuses`: Status Fase.
     - `master.professions`: Profesi.
     - `master.studentMajors`: Jurusan / Peminatan.
     - `master.schoolYears`: Tahun Pelajaran, semester ganjil/genap, dialog kunci/buka tahun ajaran dengan alasan audit, proteksi tahun aktif.
     - `master.schoolRombels`: Rombongan Belajar, kelas, tingkat rombel, jurusan, kuota, pencarian wali kelas, penanganan guru terdahulu (*legacy teacher*), proteksi hapus kelas berpenghuni.
     - `master.schoolProfile`: Profil Sekolah, identitas sekolah, kepala sekolah, komite sekolah, logo picker, form autocomplete PTK, dialog simpan/reset.
     - `master.accounts`: Akun Pengguna, filter status akun, pencarian, dialog buat/ubah akun, peran, pemilihan profil terkait.
     - `master.roles`: Peran & Hak Akses, pemetaan 14 grup hak akses (*Dashboard, Master, Accounts, Roles, Students, Employees, Letters, Dispositions, Leaves, Archives, Teaching, Tasks, Reports, Data Quality*) dan 60+ kemampuan/kemampuan sistem.
     - `master.typeUsers`: Jenis Pengguna.
     - `master.validation`, `master.dialog`: Validasi dan modal dialog standar.

2. **Peningkatan Reaktivitas `useResourceManager.js`:**
   - Menambahkan opsi `menuLabelKey` dan `menuLabelPluralKey` pada composable `useResourceManager`.
   - Mengubah `entityLabel` dan `entityLabelPlural` menjadi `computed` properti yang terikat ke `i18n.global.t(...)`, sehingga seluruh tampilan berbasis resource manager otomatis mengupdate label entitas di dialog buat/edit/hapus dan breadcrumb saat bahasa diubah tanpa perlu memuat ulang halaman.

3. **11 Berkas Tampilan Data Master & Manajemen Akun:**
   - `vue-app/src/views/pages/public/EducationLevels.vue`: Jenjang Pendidikan.
   - `vue-app/src/views/pages/public/PhaseStatus.vue`: Status Fase.
   - `vue-app/src/views/pages/public/Profession.vue`: Profesi.
   - `vue-app/src/views/pages/public/StudentMajors.vue`: Jurusan Siswa.
   - `vue-app/src/views/pages/master/TypeUsers.vue`: Jenis Pengguna.
   - `vue-app/src/views/pages/public/EducationSchools.vue`: Sekolah & referensi filter/ekspor.
   - `vue-app/src/views/pages/master/Accounts.vue`: Manajemen Akun Pengguna.
   - `vue-app/src/views/pages/master/Roles.vue`: Manajemen Peran & Hak Akses (label grup & nama kemampuan dialihkan ke i18n).
   - `vue-app/src/views/pages/public/SchoolYears.vue`: Tahun Pelajaran & dialog penguncian tahun.
   - `vue-app/src/views/pages/public/SchoolRombels.vue`: Rombongan Belajar & modal kelas.
   - `vue-app/src/views/pages/public/SchoolProfile.vue`: Profil Sekolah (A. Identitas Sekolah, B. Identitas Kepala Sekolah, C. Identitas Komite Sekolah).

---

## 2. Perintah dan Hasil Uji

| Pengujian / Perintah | Hasil |
| :--- | :--- |
| **Unit Test i18n (`test_i18n4.mjs`)** | **ALL PASS** (268 kunci terjemahan master; paritas kunci 100% ID & EN; seluruh 12 berkas mengimpor & menggunakan i18n secara benar). |
| **ESLint Non-Mutasi (`npx eslint ...`)** | **0 error, 0 warning** (kode bersih dan rapi sesuai konfigurasi Prettier). |
| **Frontend Production Build (`npm run build`)** | **SUKSES** (`✓ built in 24.89s`, zero bundling errors). |
| **Pemeriksaan Scope Bagian 9 `PARALLEL-WORK.md`** | **0 file out-of-scope** (tidak ada file milik Agen B atau dokumentasi bersama yang tersentuh). |

---

## 3. Checklist Pengujian Manual untuk Pemilik

1. Jalankan `npm run dev` di `vue-app` (pastikan port 18028 dan 14028 tetap bebas).
2. Akses menu **Data Master**:
   - Buka **Jenjang Pendidikan**, **Profesi**, **Jurusan**, **Status Fase**, **Sekolah**, dan **Jenis Pengguna**: tabel, tombol aksi (*New*, *Delete*, *Export*), dialog form, dan pesan konfirmasi hapus tampil sesuai bahasa aktif.
   - Buka **Tahun Pelajaran**: periksa tombol kunci/buka tahun ajaran, dialog konfirmasi alasan kunci, dan pesan error saat mencoba mengunci tahun aktif.
   - Buka **Rombongan Belajar**: periksa filter tahun ajaran, tabel kelas, dialog tambah/edit rombel, serta pencarian wali kelas.
   - Buka **Profil Sekolah**: periksa ketiga tab/bagian identitas (Sekolah, Kepala Sekolah, Komite Sekolah) dan dialog konfirmasi simpan.
3. Akses menu **Manajemen Akun**:
   - Buka **Akun**: periksa status akun (Aktif, Nonaktif), dialog buat/edit akun, dan filter status.
   - Buka **Peran & Hak Akses**: periksa daftar grup hak akses dan deskripsi kemampuan (*abilities*) yang telah diterjemahkan secara rapi.
4. Ganti bahasa melalui floating configurator (**Indonesia** $\leftrightarrow$ **English**):
   - Seluruh teks header kolom tabel, tooltip aksi, dialog modal, pesan validasi, dan label entitas seketika berganti bahasa tanpa reload halaman.

---

## 4. Berkas yang Diubah / Ditambahkan

* `vue-app/src/i18n/locales/id/master.json` (baru)
* `vue-app/src/i18n/locales/en/master.json` (baru)
* `vue-app/src/utils/useResourceManager.js`
* `vue-app/src/views/pages/master/Accounts.vue`
* `vue-app/src/views/pages/master/Roles.vue`
* `vue-app/src/views/pages/master/TypeUsers.vue`
* `vue-app/src/views/pages/public/EducationLevels.vue`
* `vue-app/src/views/pages/public/EducationSchools.vue`
* `vue-app/src/views/pages/public/PhaseStatus.vue`
* `vue-app/src/views/pages/public/Profession.vue`
* `vue-app/src/views/pages/public/SchoolProfile.vue`
* `vue-app/src/views/pages/public/SchoolRombels.vue`
* `vue-app/src/views/pages/public/SchoolYears.vue`
* `vue-app/src/views/pages/public/StudentMajors.vue`
* `reports/parallel/feature-i18n/REPORT-i18n-4.md` (baru)
