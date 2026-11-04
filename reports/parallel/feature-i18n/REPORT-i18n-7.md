# Laporan i18n-7 — Persuratan, Perizinan, & Arsip (Worktree A)

- **Branch:** `feature/i18n`
- **Parent SHA:** `b5bdf72576620e4b70dc6df235568a45b83be97c`
- **Dokumen Acuan:** [`docs/I18N.md`](file:///D:/xampp-htdocs/school-i18n/docs/I18N.md) dan [`reports/parallel/feature-i18n/GLOSSARY.md`](file:///D:/xampp-htdocs/school-i18n/reports/parallel/feature-i18n/GLOSSARY.md).

---

## 1. Yang Dikerjakan

1. **Namespace Terjemahan Baru (Total 698 Kunci, 100% Paritas):**
   - `vue-app/src/i18n/locales/{id,en}/letters.json` (405 kunci):
     - **Surat Masuk (`incoming`):** agenda surat masuk, lampiran berkas privat, riwayat penyuntingan versi (*versioned history*), pembatalan administratif (*void letter*), dan dialog penugasan disposisi pimpinan.
     - **Surat Keluar (`outgoing`):** pembuatan draf, alur verifikasi & penelaahan (*approval workflow*), penomoran dinas atomik (*official numbering*), penerbitan & penandatanganan resmi (*issue & sign*), pratinjau PDF bertanda air (*watermark*), dan buku register persetujuan (*approval ledger*).
     - **Disposisi Surat (`dispositions`):** pemantauan antrean tindak lanjut disposisi, peringatan lewat tenggat (*overdue*), penyelesaian (*complete*), pembatalan disposisi (*cancel*), dan pencatatan hasil tindak lanjut (*outcome*).
     - **Pengaturan Surat (`settings`):** konfigurasi kop surat dinas, unggah logo sekolah, identitas penandatangan resmi, tanda tangan digital, stempel dinas, dan checklist kesiapan penerbitan (*readiness items*).
   - `vue-app/src/i18n/locales/{id,en}/leaves.json` (152 kunci):
     - **Pengajuan Izin & Cuti:** pengajuan staf/pegawai dan mandiri (*self-service*), lampiran berkas pendukung, dan verifikasi status.
     - **Tab Navigasi Reaktif:** tab *Daftar Pengajuan* (*Requests*), tab *Pengajuan Saya* (*My Leaves*), dan tab *Rekapitulasi* (*Recap*).
     - **Rekapitulasi Ketidakhadiran:** filter agregat per periode waktu, rekapitulasi berdasarkan status, jenis izin, rombel/kelas, dan jenis kepegawaian.
     - **Alur Persetujuan Bertingkat:** persetujuan (*approve*), penolakan (*reject*), pembatalan permohonan (*cancel draft*), dan pembatalan izin disetujui (*cancel approved leave*).
   - `vue-app/src/i18n/locales/{id,en}/archives.json` (141 kunci):
     - **Repositori Arsip Dokumen:** metadata dokumen arsip, nomor dokumen, tanggal dokumen, dan tautan subjek (siswa, pegawai, dsb.).
     - **Pelacakan Masa Berlaku (*Validity Tracking*):** status berlaku (*valid*), belum berlaku (*upcoming*), segera berakhir (*expiring soon*), kedaluwarsa (*expired*), dan tanpa masa berlaku (*no expiry*).
     - **Manajemen Versi Berkas (*File Versioning*):** pengunggahan versi baru tanpa mengubah riwayat berkas lama (*immutable version history*), pratinjau langsung, dan unduh berkas.
     - **Tata Kelola Arsip:** penguncian dokumen (*archive document*), pemulihan dokumen (*restore*), dan buku riwayat audit akses pengguna (*access history log*).

2. **Peningkatan Reaktivitas Komponen & Formatter:**
   - Seluruh opsi status, alur kerja, jenis dokumen, dan kategori dibuat sebagai `computed property` yang bereaksi instan saat bahasa diubah melalui *Floating Configurator* tanpa reload halaman.
   - Menggunakan format tanggal lokal (`toLocaleDateString` dengan `en-GB` atau `id-ID`) serta `formatDateTime` standar WIB.
   - Menjaga integritas pesan galat spesifik backend sesuai keputusan **D2** pada `docs/I18N.md`.

3. **6 Berkas Views yang Dimodernisasi:**
   - `vue-app/src/views/pages/public/LetterSettings.vue`
   - `vue-app/src/views/pages/public/LetterDispositions.vue`
   - `vue-app/src/views/pages/public/IncomingLetters.vue`
   - `vue-app/src/views/pages/public/OutgoingLetters.vue`
   - `vue-app/src/views/pages/public/Leaves.vue`
   - `vue-app/src/views/pages/public/Archives.vue`

---

## 2. Perintah dan Hasil Uji

| Pengujian / Perintah | Hasil |
| :--- | :--- |
| **Unit Test Paritas Kunci (`scratch/test_i18n7.mjs`)** | **ALL PASS** (405 kunci letters, 152 kunci leaves, 141 kunci archives; paritas 100% ID & EN). |
| **Prettier Formatting (`npx prettier --write ...`)** | **SUKSES** (seluruh berkas JSON dan 6 komponen Vue rapi dan konsisten). |
| **ESLint Non-Mutasi (`npx eslint ...`)** | **0 error, 0 warning** (bebas dari *unused variables*, peringatan sintaks, atau format CRLF). |
| **Frontend Production Build (`npm run build`)** | **SUKSES** (`✓ built in 45.94s`, zero bundling errors). |
| **Pemeriksaan Scope Bagian 9 `PARALLEL-WORK.md`** | **0 file out-of-scope** (tidak ada berkas milik Agen B atau dokumen bersama yang tersentuh). |

---

## 3. Checklist Pengujian Manual untuk Pemilik

1. Jalankan `npm run dev` di `vue-app` (pastikan port 18028 dan 14028 tetap bebas).
2. Akses menu **Tata Persuratan -> Surat Masuk** (`/public/letters/incoming`):
   - Periksa header, filter pencarian, filter kategori, filter tanggal, dan tombol **Surat Masuk Baru**.
   - Buka form dialog tambah/edit surat masuk dan periksa seluruh placeholder serta label input.
   - Buka modal detail surat masuk: periksa tab lampiran berkas, timeline riwayat, dan daftar disposisi.
   - Buka dialog **Beri Disposisi**: periksa multiselect pegawai aktif dan input instruksi.
   - Buka dialog **Batalkan Surat**: periksa pesan peringatan dan input alasan pembatalan.
3. Akses menu **Tata Persuratan -> Surat Keluar** (`/public/letters/outgoing`):
   - Periksa kolom tabel, status alur kerja (*Draf*, *Dalam Telaah*, *Disetujui*, *Diterbitkan*), badge PDF, dan tombol aksi.
   - Buka dialog tambah draf surat keluar: periksa pemilihan jenis surat (*Surat Umum*, *Surat Tugas*, *Surat Keterangan*).
   - Buka detail draf: periksa tombol alur kerja (*Ajukan*, *Setujui*, *Tolak*, *Terbitkan*), pratinjau PDF, dan buku register persetujuan.
4. Akses menu **Tata Persuratan -> Disposisi Surat** (`/public/letters/dispositions`):
   - Periksa filter status disposisi, filter penerima, checkbox *Hanya lewat tenggat*, dan tombol aksi baris.
   - Buka modal selesaikan disposisi dan modal batalkan disposisi.
5. Akses menu **Tata Persuratan -> Pengaturan Surat** (`/public/letters/settings`):
   - Periksa kartu *Tahun Ajaran Aktif*, kartu *Kop Surat*, dan kartu *Penandatangan Resmi*.
   - Periksa checklist item kesiapan penerbitan (*readiness items*) dan pesan peringatan jika belum lengkap.
6. Akses menu **Perizinan Pegawai -> Pengajuan Izin & Cuti** (`/public/leaves`):
   - Periksa tab *Daftar Pengajuan*, tab *Pengajuan Saya*, dan tab *Rekapitulasi*.
   - Pada tab *Rekapitulasi*, periksa kartu total hari/pengajuan dan tabel rekapitulasi (*Berdasarkan Status*, *Berdasarkan Jenis*, *Berdasarkan Kelas*, *Berdasarkan Pegawai*).
   - Buka dialog pengajuan baru dan form persetujuan/penolakan izin.
7. Akses menu **Arsip Dokumen -> Repositori Arsip** (`/public/archives`):
   - Periksa filter kategori, subjek terkait, filter masa berlaku, dan filter status arsip.
   - Periksa badge masa berlaku (*Berlaku*, *Segera Berakhir*, *Kedaluwarsa*, *Tanpa Masa Berlaku*).
   - Buka modal tambah dokumen arsip baru, modal tambah versi berkas, dan modal pratinjau berkas.
8. Ganti bahasa melalui *Floating Configurator* (**Indonesia** $\leftrightarrow$ **English**):
   - Seluruh teks header kolom tabel, tooltip aksi, dialog modal, placeholder, label filter, dan pesan status seketika berganti bahasa tanpa reload halaman.

---

## 4. Berkas yang Diubah / Ditambahkan

* `vue-app/src/i18n/locales/id/letters.json` (baru)
* `vue-app/src/i18n/locales/en/letters.json` (baru)
* `vue-app/src/i18n/locales/id/leaves.json` (baru)
* `vue-app/src/i18n/locales/en/leaves.json` (baru)
* `vue-app/src/i18n/locales/id/archives.json` (baru)
* `vue-app/src/i18n/locales/en/archives.json` (baru)
* `vue-app/src/views/pages/public/LetterSettings.vue`
* `vue-app/src/views/pages/public/LetterDispositions.vue`
* `vue-app/src/views/pages/public/IncomingLetters.vue`
* `vue-app/src/views/pages/public/OutgoingLetters.vue`
* `vue-app/src/views/pages/public/Leaves.vue`
* `vue-app/src/views/pages/public/Archives.vue`
* `reports/parallel/feature-i18n/REPORT-i18n-7.md` (baru)
