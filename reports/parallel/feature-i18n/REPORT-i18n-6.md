# Laporan i18n-6 — Kepegawaian & Kualitas Data (Worktree A)

- **Branch:** `feature/i18n`
- **Parent SHA:** `ced53818c4b2e09260e4b55a641ceaa258934acd`
- **Dokumen Acuan:** [`docs/I18N.md`](file:///D:/xampp-htdocs/school-i18n/docs/I18N.md) dan [`reports/parallel/feature-i18n/GLOSSARY.md`](file:///D:/xampp-htdocs/school-i18n/reports/parallel/feature-i18n/GLOSSARY.md).

---

## 1. Yang Dikerjakan

1. **Namespace Terjemahan Baru:**
   - `vue-app/src/i18n/locales/{id,en}/dataQuality.json`: 123 kunci terjemahan mencakup:
     - Header, subtitle, ringkasan per tanggal (as-of timestamp dengan zona waktu WIB).
     - Filter domain data (*Students*, *Employees*, *Classes*, *Academics*), jenis masalah, status review (*Pending*, *Reviewed*, *Ignored*, *Resolved*), tahun ajaran, dan paginasi.
     - 4 metrik kualitas data (*Cleanliness Score*, *Unresolved Issues*, *Critical Issues*, *Duplicate Records*).
     - Kolom tabel anomali data, badge keparahan (*Critical*, *Warning*, *Info*), dan aksi penanganan.
     - Modal dialog tinjauan anomali (*Review Issue*): verifikasi entitas berbeda, penandaan selesai, penandaan diabaikan, alasan penolakan/pengabaian, serta catatan verifikasi.
   - `vue-app/src/i18n/locales/{id,en}/employees.json`: 227 kunci terjemahan mencakup:
     - Entitas dinamis guru dan tenaga kependidikan/pegawai (*Teacher* $\leftrightarrow$ *Guru*, *Employee* $\leftrightarrow$ *Pegawai*).
     - Direktori kepegawaian: tab direktori aktif dan arsip, filter status kepegawaian, filter jenis kelamin, pencarian terpadu, dan opsi paginasi.
     - Kolom tabel direktori, badge status aktif/cuti/berhenti, dan tombol aksi baris (*Detail*, *Edit*, *Tautkan Akun*, *Arsipkan*).
     - Modal form tambah & ubah pegawai: identitas diri, nomor identitas resmi (NIP, NUPTK, NIK), riwayat pendidikan berjenjang, status kepegawaian, penugasan mata pelajaran, dan riwayat kepangkatan.
     - Modal detail biodata pegawai ber-tab: tab biodata, tab riwayat pendidikan, tab kepegawaian, tab status akun pengguna, dan tab dokumen.
     - Modal dialog arsip & pemulihan administratif pegawai beserta alasan dan konfirmasi.
     - Modal wizard ekspor data pegawai: seleksi kolom terstruktur, pilihan format berkas, tombol pilih/batal semua kolom.
     - Modal wizard impor data pegawai: mode insert/update, unduh templat CSV, validasi ukuran/ekstensi berkas, pratinjau data valid, dan tabel pelaporan galat per baris.
     - Modal penautan akun pengguna (*Account Link Dialog*): pemilihan akun sistem, status peran, serta pemutusan tautan.

2. **Peningkatan Reaktivitas Komponen & Standar Desain:**
   - Seluruh opsi dropdown, tab navigasi, dan filter dibuat menggunakan `computed property` sehingga teks langsung berganti saat bahasa diubah di *Floating Configurator* tanpa reload halaman.
   - Penyesuaian nama entitas secara kontekstual (`t('employees.types.teacher')` vs `t('employees.types.employee')`) mengikuti parameter rute yang aktif.
   - Pesan galat spesifik dari backend pada anomali data dipertahankan secara utuh sesuai keputusan **D2** pada `docs/I18N.md`.

3. **11 Berkas Komponen/Views yang Dimodernisasi:**
   - `vue-app/src/views/pages/public/DataQuality.vue`
   - `vue-app/src/views/pages/public/DataQualityReviewDialog.vue`
   - `vue-app/src/views/pages/public/dataQualityParams.mjs`
   - `vue-app/src/views/pages/public/EmployeeDirectory.vue`
   - `vue-app/src/views/pages/public/EmployeeFormDialog.vue`
   - `vue-app/src/views/pages/public/EmployeeDetailDialog.vue`
   - `vue-app/src/views/pages/public/EmployeeArchiveDialog.vue`
   - `vue-app/src/views/pages/public/EmployeeExportDialog.vue`
   - `vue-app/src/views/pages/public/EmployeeImportDialog.vue`
   - `vue-app/src/views/pages/public/EmployeeAccountLinkDialog.vue`
   - `vue-app/src/views/pages/public/employeeImportParams.mjs`

---

## 2. Perintah dan Hasil Uji

| Pengujian / Perintah | Hasil |
| :--- | :--- |
| **Unit Test Paritas Kunci (`scratch/test_i18n6.mjs`)** | **ALL PASS** (123 kunci dataQuality, 227 kunci employees; paritas kunci 100% ID & EN). |
| **Unit Test Data Quality Params (`tests/data-quality-params.test.mjs`)** | **13/13 PASS** (semua parameter filter, paginasi, dan payload review terverifikasi). |
| **Unit Test Employee Params (`tests/employee-*.test.mjs`)** | **12/12 PASS** (validasi direktori, ekspor kolom, dan impor berkas terverifikasi). |
| **Prettier Formatting (`npx prettier --write ...`)** | **SUKSES** (seluruh 15 berkas rapi dan konsisten). |
| **ESLint Non-Mutasi (`npx eslint ...`)** | **0 error, 0 warning** (bebas dari *unused variables* atau peringatan sintaks). |
| **Frontend Production Build (`npm run build`)** | **SUKSES** (`✓ built in 25.32s`, zero bundling errors). |
| **Pemeriksaan Scope Bagian 9 `PARALLEL-WORK.md`** | **0 file out-of-scope** (tidak ada berkas milik Agen B atau dokumen bersama yang tersentuh). |

---

## 3. Checklist Pengujian Manual untuk Pemilik

1. Jalankan `npm run dev` di `vue-app` (pastikan port 18028 dan 14028 tetap bebas).
2. Akses menu **Kepegawaian -> Direktori Guru & Tenaga Kependidikan** (`/public/employees`):
   - Periksa tab *Direktori Aktif* dan *Arsip Pegawai*.
   - Periksa filter jenis kelamin, status kepegawaian, dan pencarian.
   - Buka tombol **Tambah Guru/Pegawai**: periksa setiap form group, tab riwayat pendidikan, dan dropdown opsi.
   - Buka tombol **Lihat Detail** pada baris pegawai: periksa modal detail biodata dan riwayat pendidikan.
   - Buka tombol **Tautkan Akun**: periksa opsi akun pengguna dan tombol simpan/putuskan tautan.
   - Buka tombol **Ekspor**: periksa checklist pemilihan kolom dan tombol unduh.
   - Buka tombol **Impor**: periksa wizard impor, unggah berkas, dan tabel preview.
3. Akses menu **Kualitas Data** (`/public/data-quality`):
   - Periksa 4 kartu metrik kualitas data di bagian atas.
   - Periksa tab domain data (*Siswa*, *Pegawai*, *Rombel*, *Akademik*) dan filter status anomali.
   - Buka tombol **Review** pada salah satu baris anomali: periksa dialog tinjauan anomali dan opsi alasan.
4. Ganti bahasa melalui *Floating Configurator* (**Indonesia** $\leftrightarrow$ **English**):
   - Seluruh teks tabel, header kolom, badge status, modal dialog, placeholder, dan tooltip aksi seketika berganti bahasa tanpa reload halaman.

---

## 4. Berkas yang Diubah / Ditambahkan

* `vue-app/src/i18n/locales/id/dataQuality.json` (baru)
* `vue-app/src/i18n/locales/en/dataQuality.json` (baru)
* `vue-app/src/i18n/locales/id/employees.json` (baru)
* `vue-app/src/i18n/locales/en/employees.json` (baru)
* `vue-app/src/views/pages/public/dataQualityParams.mjs`
* `vue-app/src/views/pages/public/DataQualityReviewDialog.vue`
* `vue-app/src/views/pages/public/DataQuality.vue`
* `vue-app/src/views/pages/public/employeeImportParams.mjs`
* `vue-app/src/views/pages/public/EmployeeAccountLinkDialog.vue`
* `vue-app/src/views/pages/public/EmployeeArchiveDialog.vue`
* `vue-app/src/views/pages/public/EmployeeDetailDialog.vue`
* `vue-app/src/views/pages/public/EmployeeExportDialog.vue`
* `vue-app/src/views/pages/public/EmployeeFormDialog.vue`
* `vue-app/src/views/pages/public/EmployeeImportDialog.vue`
* `vue-app/src/views/pages/public/EmployeeDirectory.vue`
* `reports/parallel/feature-i18n/REPORT-i18n-6.md` (baru)
