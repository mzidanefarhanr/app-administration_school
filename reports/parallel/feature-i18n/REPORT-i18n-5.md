# Laporan i18n-5 — Modul Kesiswaan (Worktree A)

- **Branch:** `feature/i18n`
- **Parent SHA:** `1ea488950fc15ba6cfab1cf4d8cb84c8a2b5e28a`
- **Dokumen Acuan:** [`docs/I18N.md`](file:///D:/xampp-htdocs/school-i18n/docs/I18N.md) dan [`reports/parallel/feature-i18n/GLOSSARY.md`](file:///D:/xampp-htdocs/school-i18n/reports/parallel/feature-i18n/GLOSSARY.md).

---

## 1. Yang Dikerjakan

1. **Namespace Terjemahan Baru:**
   - `vue-app/src/i18n/locales/{id,en}/students.json`: 442 kunci terjemahan mencakup:
     - `students.title`, `students.activePeriod`, `students.noActivePeriod`, `students.noActivePeriodNotice`, `students.schoolYears`, `students.selectSchoolYear`, `students.yearLocked`: header dan status akademik.
     - `students.statusCounts`: 4 kartu metrik siswa (*Active Students, Transfer Students, Drop Out Students, Graduation Students*).
     - `students.groups` & `students.subItems`: 3 kategori grup navigasi internal (*Demographics, Administration, Relationships*) dan 12 sub-item pil jangkar (*Students, Genders, Religions, Class Breakdown, Student Profile, F8355, KJP, PPDB, Dapodik Status, Mutations, Parents, Homeroom Teacher*).
     - `students.charts`: judul grafik, total statistik, tabel rekapitulasi tingkat kelas, label dinamis grafik lingkaran (*Male/Female* $\leftrightarrow$ *Laki-laki/Perempuan*), dan teks kondisi kosong (*No data found*).
     - `students.list`: tabel utama data siswa, filter status penempatan (*All, Placed, Unplaced*), filter rombel, pencarian, tombol aksi baris (*Mutation Timeline, View Detail, Edit Student, Assign Class, Transfer Class, Archive Student*), dan badge status penempatan.
     - `students.profile`: modal dialog cetak profil siswa (identitas A–G, detail biodata, nomor ijazah/SKHUN, kontak orang tua/wali, dan nomor registrasi).
     - `students.form`: form dialog tambah/edit siswa (data pribadi, dokumen identitas, alamat & kontak, orang tua/wali, serta afirmasi bantuan KJP/PIP/BPMS/PPDB Bersama).
     - `students.archive`: dialog konfirmasi pengarsipan administratif siswa dan tabel daftar siswa diarsipkan dengan aksi pemulihan (*restore*).
     - `students.placement`: dialog penempatan rombel (*assign*) dan kepindahan kelas (*transfer*) dengan proteksi tahun terkunci.
     - `students.conflict`: dialog deteksi konflik konkurensi (HTTP 409).
     - `students.import`: dialog wizard impor CSV (mode insert/update, validasi ukuran/format berkas, pratinjau data valid, tabel daftar kesalahan validasi baris, unduh templat, dan laporan error).
     - `students.mutations`: modul log mutasi siswa (mutasi masuk, mutasi keluar, pembatalan mutasi, koreksi mutasi terkunci, dan dialog riwayat kronologi mutasi).
     - `students.progression`: wizard 4 langkah kenaikan & kelulusan siswa (pemilihan tahun asal/tujuan, penentuan keputusan massal/individual, pratinjau atomik berbatas waktu, eksekusi keputusan, dan riwayat kelompok kenaikan kelas).

2. **Peningkatan Reaktivitas Komponen & Visualisasi:**
   - Menjadikan `groupItems`, `tabItems`, `placementOptions`, `steps`, `decisionOptions`, `typeOptions`, `statusOptions`, `correctionActions`, dan `targetStatusOptions` sebagai `computed property` yang bereaksi langsung terhadap perubahan bahasa.
   - Menambahkan pengamat reaktif `watch(locale, () => updateChartData())` pada `Student.vue` sehingga grafik distribusi gender dan tingkat rombel seketika memperbarui label bahasa Inggris/Indonesia tanpa memerlukan reload halaman.

3. **6 Berkas Komponen/Views Kesiswaan yang Dimodernisasi:**
   - `vue-app/src/views/pages/public/Student.vue`
   - `vue-app/src/views/pages/public/StudentProgression.vue`
   - `vue-app/src/views/pages/public/StudentMutations.vue`
   - `vue-app/src/views/pages/public/StudentMutationTimelineDialog.vue`
   - `vue-app/src/views/pages/public/StudentImportDialog.vue`
   - `vue-app/src/views/pages/public/studentImportParams.mjs`

---

## 2. Perintah dan Hasil Uji

| Pengujian / Perintah | Hasil |
| :--- | :--- |
| **Unit Test i18n (`test_i18n5.mjs`)** | **ALL PASS** (442 kunci terjemahan students; paritas kunci 100% ID & EN; seluruh 5 komponen mengimpor & menggunakan i18n secara benar). |
| **ESLint Non-Mutasi (`npx eslint ...`)** | **0 error, 0 warning** (kode bersih dan rapi sesuai konfigurasi Prettier). |
| **Frontend Production Build (`npm run build`)** | **SUKSES** (`✓ built in 22.25s`, zero bundling errors). |
| **Pemeriksaan Scope Bagian 9 `PARALLEL-WORK.md`** | **0 file out-of-scope** (tidak ada file milik Agen B atau dokumentasi bersama yang tersentuh). |

---

## 3. Checklist Pengujian Manual untuk Pemilik

1. Jalankan `npm run dev` di `vue-app` (pastikan port 18028 dan 14028 tetap bebas).
2. Akses menu **Kesiswaan -> Data Siswa** (`/public/students`):
   - Periksa 4 kartu status kesiswaan di bagian atas (Siswa Aktif, Siswa Mutasi, dsb.).
   - Periksa grup navigasi tab (*Demografi*, *Administrasi*, *Hubungan & Relasi*) dan sub-item pil.
   - Periksa tabel utama data siswa: kolom tabel, filter penempatan (*Semua*, *Sudah Ditempatkan*, *Belum Ditempatkan*), filter rombel, dan tombol aksi.
   - Buka tombol **Lihat Detail** dan **Ubah Siswa**: form dialog tampil dengan label bahasa aktif.
   - Buka tombol **Impor Siswa**: wizard impor dan pesan validasi tampil sesuai bahasa.
   - Buka sub-tab **Mutasi Siswa**: periksa tabel log mutasi, dialog catat mutasi masuk/keluar, dan kronologi mutasi siswa.
3. Akses menu **Akademik -> Kenaikan & Kelulusan** (`/public/progression`):
   - Periksa 4 langkah alur wizard (Tahun Pelajaran, Siswa & Keputusan, Pratinjau, Hasil).
   - Periksa tombol aksi massal (*Naik Kelas*, *Tinggal Kelas*, *Lulus*, *Atur Ulang*) dan tabel riwayat kelompok kenaikan kelas.
4. Ganti bahasa melalui floating configurator (**Indonesia** $\leftrightarrow$ **English**):
   - Seluruh teks header kolom tabel, tooltip aksi, dialog modal, pesan validasi, label opsi dropdown, dan legenda grafik seketika berganti bahasa tanpa reload halaman.

---

## 4. Berkas yang Diubah / Ditambahkan

* `vue-app/src/i18n/locales/id/students.json` (baru)
* `vue-app/src/i18n/locales/en/students.json` (baru)
* `vue-app/src/views/pages/public/studentImportParams.mjs`
* `vue-app/src/views/pages/public/StudentImportDialog.vue`
* `vue-app/src/views/pages/public/StudentMutationTimelineDialog.vue`
* `vue-app/src/views/pages/public/StudentProgression.vue`
* `vue-app/src/views/pages/public/StudentMutations.vue`
* `vue-app/src/views/pages/public/Student.vue`
* `reports/parallel/feature-i18n/REPORT-i18n-5.md` (baru)
