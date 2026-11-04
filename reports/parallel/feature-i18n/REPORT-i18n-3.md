# Laporan i18n-3 — Profil Pengguna & Seluruh Widget Dasbor (Worktree A)

- **Branch:** `feature/i18n`
- **Parent SHA:** `8831b5b31fbc3ee3165b63b27bcfb9cba35aa008`
- **Dokumen Acuan:** [`docs/I18N.md`](file:///D:/xampp-htdocs/school-i18n/docs/I18N.md) dan [`reports/parallel/feature-i18n/GLOSSARY.md`](file:///D:/xampp-htdocs/school-i18n/reports/parallel/feature-i18n/GLOSSARY.md).

---

## 1. Yang Dikerjakan

1. **Namespace Terjemahan Baru:**
   - `vue-app/src/i18n/locales/{id,en}/profile.json`: 37 kunci terjemahan mencakup statistik profil, form vertikal (demo), form ganti kata sandi, form lanjutan (demo), serta seluruh dialog validasi, konfirmasi, pesan sukses, gagal, dan waiting.
   - `vue-app/src/i18n/locales/{id,en}/dashboard.json`: 116 kunci terjemahan mencakup 8 widget dasbor operasional (metrik operasional, pengingat kedaluwarsa arsip, audit kualitas data master, pengguna online, antrean tugas operasional, grafik distribusi siswa per rombel, ringkasan sistem & pintasan cepat, dan tren kepatuhan jurnal mengajar).
   - `vue-app/src/i18n/locales/{id,en}/common.json`: Penambahan aksi `retry` ("Coba lagi" / "Retry").

2. **Halaman Profil Pengguna (`vue-app/src/views/pages/auth/Profile.vue`):**
   - Menghubungkan seluruh label form, pesan validasi kata sandi (semua bidang wajib, minimal 12 karakter, konfirmasi tidak cocok, kata sandi sama dengan yang lama), pesan toast pembatalan, dialog konfirmasi PrimeVue headless, dialog sukses, dan pesan dialog kesalahan ke i18n.
   - Menggunakan `t('common.action.save')` dan `t('common.action.cancel')` untuk tombol formulir dan modal.

3. **8 Widget Dasbor (`vue-app/src/components/dashboard/*`):**
   - `OperationalStatsWidget.vue`: Mengganti `toLocaleString('id-ID')` dengan `formatNumber` reaktif dari `@/utils/formatters`. Seluruh label kartu (Siswa Aktif, Pendidik & Tendik, Surat & Izin Tertunda, Jadwal Hari Ini / Kepatuhan Mengajar), badge tag, dan tooltip rekap operasional telah terhubung ke `dashboard.stats.*`.
   - `ArchiveExpiryWidget.vue`: Menginternasionalisasi seluruh pengingat masa berlaku dokumen arsip, perhitungan hari terlewat/tersisa (`daysLabel`), format tanggal (`formatDate` dinamis `en-GB` atau `id-ID`), filter kedaluwarsa/segera berakhir, dan teks batas dokumen tersembunyi.
   - `DataQualityOverviewWidget.vue`: Menginternasionalisasi header kualitas kelengkapan data siswa & PTK, label persentase, penghitung profil belum lengkap, dan tautan audit kendala.
   - `ListUsersOnlineWidget.vue`: Menghubungkan header widget, placeholder pencarian autocomplete, pesan pemuatan, pesan kesalahan dengan tombol coba lagi, dan pesan daftar kosong.
   - `PendingTasksWidget.vue`: Menghubungkan judul antrean tugas operasional, label tingkat urgensi (*Mendesak*, *Perhatian*, *Info*), state selesai/kosong (*Semua Beres!*), dan pintasan ke pusat tugas.
   - `StudentDistributionChartWidget.vue`: Menginternasionalisasi judul, teks izin (*students.view*), pesan pemuatan/kosong, dan label dataset grafik batang (*Laki-laki* / *Perempuan* $\leftrightarrow$ *Male* / *Female*) yang reaktif saat tema gelap maupun bahasa diubah (`watch([isDarkTheme, locale], ...)`).
   - `SystemOverviewWidget.vue`: Menginternasionalisasi informasi tahun ajaran aktif, status terkunci/aktif, semester berjalan, waktu server (WIB dipertahankan), status kesehatan backup database, serta 8 tombol pintasan operasional cepat.
   - `TeachingComplianceChartWidget.vue`: Menginternasionalisasi judul tren kepatuhan, teks izin (*teaching.view*), label sumbu garis/batang (*Kepatuhan (%)*, *Jurnal Terisi*) dengan pembaruan dinamis tanpa reload saat locale berubah.

---

## 2. Perintah dan Hasil Uji

| Pengujian / Perintah | Hasil |
| :--- | :--- |
| **Unit Test i18n (`test_i18n3.mjs`)** | **ALL PASS** (116 kunci dashboard, 37 kunci profile, 15 kunci common; paritas kunci 100%; seluruh 9 komponen mengimpor & menggunakan i18n). |
| **ESLint Non-Mutasi (`npx eslint ...`)** | **0 error, 0 warning** (kode bersih dan rapi sesuai konfigurasi Prettier). |
| **Frontend Production Build (`npm run build`)** | **SUKSES** (`✓ built in 32.67s`, zero bundling errors). |
| **Pemeriksaan Scope Bagian 9 `PARALLEL-WORK.md`** | **0 file out-of-scope** (tidak ada file milik Agen B atau dokumentasi bersama yang tersentuh). |

---

## 3. Checklist Pengujian Manual untuk Pemilik

1. Jalankan `npm run dev` di `vue-app` (pastikan port 18028 dan 14028 tetap bebas).
2. Buka menu **Dasbor** (`/` atau `/public/dashboard`):
   - Periksa 4 kartu metrik statistik teratas: angka diformat ribuan dengan pemisah titik (`.`) dalam bahasa Indonesia.
   - Periksa widget pengingat arsip, kualitas data master, antrean tugas, dan ringkasan sistem.
3. Buka floating configurator, ganti bahasa ke **English**:
   - Angka statistik langsung berganti menggunakan koma (`,`) sebagai pemisah ribuan.
   - Label kartu, tag status, teks widget, dan legenda grafik (*Male* / *Female*, *Compliance (%)*, *Journals Logged*) seketika berganti ke bahasa Inggris tanpa reload.
4. Buka halaman **Profil Pengguna** (`/pages/auth/profile`):
   - Periksa label pada form ubah kata sandi dan modal konfirmasi simpan: tampil dalam bahasa yang dipilih.
5. Ganti kembali ke **Indonesia**: Seluruh teks kembali ke bahasa Indonesia secara reaktif.

---

## 4. Berkas yang Diubah / Ditambahkan

* `vue-app/src/i18n/locales/id/profile.json` (baru)
* `vue-app/src/i18n/locales/en/profile.json` (baru)
* `vue-app/src/i18n/locales/id/dashboard.json` (baru)
* `vue-app/src/i18n/locales/en/dashboard.json` (baru)
* `vue-app/src/i18n/locales/id/common.json`
* `vue-app/src/i18n/locales/en/common.json`
* `vue-app/src/views/pages/auth/Profile.vue`
* `vue-app/src/components/dashboard/ArchiveExpiryWidget.vue`
* `vue-app/src/components/dashboard/DataQualityOverviewWidget.vue`
* `vue-app/src/components/dashboard/ListUsersOnlineWidget.vue`
* `vue-app/src/components/dashboard/OperationalStatsWidget.vue`
* `vue-app/src/components/dashboard/PendingTasksWidget.vue`
* `vue-app/src/components/dashboard/StudentDistributionChartWidget.vue`
* `vue-app/src/components/dashboard/SystemOverviewWidget.vue`
* `vue-app/src/components/dashboard/TeachingComplianceChartWidget.vue`
* `reports/parallel/feature-i18n/REPORT-i18n-3.md` (laporan ini)

---

## 5. Status Permintaan & Izin

* **REQUESTS.md:** Tidak ada permintaan baru kepada Agen B / Pemilik.
