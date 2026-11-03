# Rencana Kerja Fitur — Subfase 12c (Konsistensi Antarmenu, Form/Dialog, dan Penghapusan Menu Demo)

## 1. Ringkasan Eksekutif & Status Rencana

- **Subfase:** 12c — Konsistensi Antarmenu, Form/Dialog, dan Penghapusan Menu Demo (*UI/UX Consistency, Sakai Demo Cleanup, Bahasa Indonesia Standardization, Theme Polish & A11y/Mobile Verification*).
- **Milestone Induk:** Fase 12 — Performa, Observability dan Konsistensi.
- **Usulan Keputusan Arsitektur:** **DEC-47 APPROVED (D1–D10)** pada 2026-11-03 WIB.
- **Baseline Kode:** `main` pada commit [`7d9cd3e`](https://github.com/mzidanefarhanr/app-administration_school/commit/7d9cd3e) setelah penyelesaian Subfase 12b (*Job, Observability & Error Resilience*).
- **Status Rencana:** **PLAN — APPROVED & CLOSED (DEC-47: D1–D10 disetujui pemilik 2026-11-03 WIB)**.
- **Status Implementasi:** **IMPLEMENTED & DIVERIFIKASI AGEN (2026-11-03)**.
- **Status Review Pemilik:** **APPROVED (#1–#18) & CLOSED (2026-11-03 WIB)** berdasarkan pernyataan: *"Pengujian nomor 1-18 sudah berhasil dan saya approved."* (Panduan review: [`PHASE-12C-MANUAL-REVIEW.md`](./PHASE-12C-MANUAL-REVIEW.md); Laporan lengkap: [`PHASE-12C-REPORT.md`](./PHASE-12C-REPORT.md)). Subfase 12c & FASE 12 RESMI SELESAI LENGKAP & DITUTUP.

---

## 2. Konteks & Latar Belakang Kebutuhan

Dengan diselesaikannya Subfase 12a (Query performance, indexing komposit, paginasi $O(1)$) dan Subfase 12b (Background queue resilience, correlation ID, sanitasi log PII, dan penanganan error standar), fondasi backend sistem kini telah memiliki performa tinggi dan observabilitas yang handal.

Tahap penutup Fase 12 adalah **Subfase 12c: Konsistensi Antarmenu dan Penghapusan Menu Demo**, yang berfokus pada penyempurnaan menyeluruh pengalaman pengguna (*User Experience / UX*):
1. **Pembersihan Menu Demo Template Sakai:** Sidebar aplikasi saat ini masih memuat grup menu bawaan template Sakai PrimeVue (`UI Components`, `Hierarchy`, `Pages: Crud, Landing, Empty`, `Get Started: Documentation, View Source`) yang bukan merupakan bagian fungsional administrasi sekolah. Hal ini membingungkan staf/operator sekolah dan menimbulkan kesan aplikasi belum tuntas.
2. **Standardisasi Bahasa Indonesia Baku:** Masih terdapat inkonsistensi percampuran bahasa Inggris dan Indonesia pada label menu navigasi, placeholder input, tombol aksi (`Save/Simpan`, `Cancel/Batal`, `Delete/Hapus`, `Export/Ekspor`), status tag, serta judul modal dialog di beberapa modul operasional.
3. **Konsistensi Dialog Konfirmasi Tindakan Destruktif:** Tindakan mutasi kritis (pembatalan mutasi siswa, penolakan pengajuan izin, pembatalan surat keluar, pengarsipan dokumen, pembatalan jadwal/tugas) harus menggunakan dialog konfirmasi modal PrimeVue yang seragam dengan tombol bahaya merah dan tombol batal abu-abu, bukan `window.confirm()` browser native yang tidak serasi.
4. **Keseragaman Format Visual Tanggal, Waktu & Angka:** Seluruh tabel dan kartu informasi harus menyajikan tanggal (DD/MM/YYYY atau D MMMM YYYY WIB), waktu (HH:MM WIB), dan persentase numerik secara rapi dan seragam.
5. **Harmonisasi Mode Terang & Gelap (Light / Dark Theme):** Memastikan seluruh tabel, formulir, modal dialog, kartu KPI, dan badge status memiliki kontras warna teks dan latar belakang yang nyaman dibaca (*high readability*) baik pada tema terang maupun tema gelap.
6. **Responsivitas Layar Ponsel (Viewport 390px):** Memastikan tata letak tabel memiliki scrolling horizontal yang rapi tanpa merusak topbar/sidebar, modal dialog dapat dioperasikan penuh pada layar sentuh ponsel, dan tidak ada tombol aksi yang terpotong.
7. **Aksesibilitas Dasar (Keyboard & Focus):** Memastikan formulir dan dialog modal dapat dinavigasi dengan tombol Tab, ditutup dengan tombol Escape, dan memiliki indikator fokus (*focus ring*) yang jelas.

---

## 3. Usulan Keputusan Pemilik (DEC-47: D1–D10)

Berikut 10 butir usulan keputusan arsitektur terinci untuk Subfase 12c:

### D1 — Cakupan Subfase 12c & Batasan Non-Goals
Kerjakan **Subfase 12c: Konsistensi Antarmenu, Form/Dialog, dan Penghapusan Menu Demo** saja.
- **Goals:**
  1. Audit dan eliminasi seluruh tautan dan rute menu demo template Sakai dari antarmuka aplikasi.
  2. Standardisasi bahasa Indonesia pada seluruh label menu navigasi sidebar, tombol aksi utama, judul modal, dan pesan status.
  3. Harmonisasi modal dialog dan konfirmasi tindakan destruktif berbasis komponen dialog PrimeVue seragam.
  4. Penyeragaman format tanggal, jam WIB, dan persentase numerik di seluruh tampilan tabel.
  5. Pengujian kontras dan kenyamanan visual tema *Light* dan *Dark*.
  6. Pengujian responsivitas mobile (viewport 390px) dan aksesibilitas keyboard dasar.
- **Non-Goals:**
  - Tidak melakukan migrasi skema database baru (zero schema change).
  - Tidak mengubah kontrak endpoint API backend yang telah berjalan.
  - Tidak melakukan perombakan alur bisnis (business flow) yang telah disetujui pada fase 01–11.
  - Tetap mematuhi aturan DEC-27 (server UAT dihentikan setelah pengujian, port 18028/14028 bebas).

### D2 — Penghapusan Menu Demo Template Sakai PrimeVue
- Menghapus grup menu demo berikut dari [`vue-app/src/layout/AppMenu.vue`](file:///D:/xampp-htdocs/app-administration_school/vue-app/src/layout/AppMenu.vue):
  - `UI Components` (Form Layout, Input, Button, Table, List, Tree, Panel, Overlay, Media, Menu, Message, File, Chart, Timeline, Misc).
  - `Pages` (Landing, Crud, Empty, Submenu items).
  - `Hierarchy` (Submenu 1, Submenu 2, dll.).
  - `Get Started` (Documentation, View Source).
- Membersihkan rute-rute demo yang tidak terpakai dari [`vue-app/src/router/index.js`](file:///D:/xampp-htdocs/app-administration_school/vue-app/src/router/index.js) agar bundle SPA menjadi lebih ringkas dan bebas tautan mati.
- Mempertahankan rute utilitas otentikasi inti: `/auth/login`, `/auth/error`, `/auth/access`, dan halaman profil `/profile/user`.

### D3 — Standardisasi Bahasa Indonesia pada Navigasi dan Label
- Menyelaraskan seluruh label menu pada sidebar utama (`AppMenu.vue`) menjadi bahasa Indonesia:
  - `Home` -> `Beranda` (`Dashboard` -> `Dasbor`, `School Profile` -> `Profil Sekolah`, `Tasks & Reminders` -> `Pusat Tugas & Pengingat`)
  - `Master Data` -> `Data Master` (`Education Levels` -> `Jenjang Pendidikan`, `Education Schools` -> `Sekolah`, `Phase Status` -> `Status Fase`, `Professions` -> `Profesi`, `Student Majors` -> `Jurusan Siswa`)
  - `Academic` -> `Akademik` (`Classes` -> `Rombongan Belajar`, `Progression` -> `Kenaikan & Kelulusan`, `School Years` -> `Tahun Ajaran`)
  - `Public` -> `Kesiswaan & Kepegawaian` (`Students` -> `Data Siswa`, `Teachers` -> `Direktori Guru`, `Employees` -> `Direktori Pegawai`, `Data Quality` -> `Kualitas Data`, `School Reports` -> `Laporan Sekolah`)
  - `Letters` -> `Tata Persuratan` (`Incoming Letters` -> `Surat Masuk`, `Outgoing Letters` -> `Surat Keluar`, `Dispositions` -> `Disposisi Surat`, `Letter Settings` -> `Pengaturan Surat`)
  - `Leaves` -> `Perizinan Pegawai` (`Leave Requests` -> `Pengajuan Izin & Cuti`)
  - `Archives` -> `Arsip Dokumen` (`Archive Documents` -> `Repositori Arsip`)
  - `Teaching` -> `Administrasi Pembelajaran` (`Assignments & Timetable` -> `Penugasan & Jadwal`, `Sessions & Journals` -> `Jurnal Mengajar`, `Journal Reports` -> `Laporan Kepatuhan Jurnal`)
  - `Personal` -> `Akun Saya` (`Profile` -> `Profil Pengguna`)
  - `Admin` -> `Pengaturan Akses` (`Accounts` -> `Manajemen Pengguna`, `User Type` -> `Tipe Pengguna`, `Roles & Abilities` -> `Peran & Hak Akses`)

### D4 — Standardisasi Tombol Aksi & Dialog Konfirmasi Destruktif
- Tombol aksi formulir di seluruh view distandarisasi secara konsisten:
  - `Simpan` (Primary - Biru / Emerald)
  - `Batal` / `Tutup` (Secondary - Outlined / Abu-abu)
  - `Hapus` / `Batalkan` (Danger - Merah)
  - `Ekspor CSV` (Help / Info - Hijau / Teal)
- Seluruh tindakan destruktif atau mutasi permanen wajib menggunakan dialog konfirmasi modal seragam (`ConfirmDialog` / Dialog PrimeVue) yang memuat judul peringatan jelas, pesan dampak tindakan, tombol konfirmasi bahaya, dan tombol batal non-destruktif.
- Mengeliminasi dialog bawaan peramban (`window.confirm()` dan `window.alert()`).

### D5 — Penyeragaman Format Tanggal, Jam WIB, dan Nilai Numerik
- Menstandarisasi helper formatting pada frontend (`vue-app/src/utils/formatters.js` atau helper terpusat):
  - Format tanggal standar: `DD/MM/YYYY` (misal `03/11/2026`) untuk kolom tabel padat, dan `D MMMM YYYY` (misal `3 November 2026`) untuk kartu detail/laporan resmi.
  - Format waktu standar: `HH:mm WIB` (misal `07:30 WIB`).
  - Format persentase: `0.0%` (misal `98.5%`).
  - Penanganan nilai kosong: menggunakan tanda strip (`-`) yang rapi, bukan `null`, `undefined`, atau sel kosong.

### D6 — Harmonisasi Kontras Tema Light dan Dark Mode
- Memeriksa seluruh halaman modul aktif pada mode terang (*light*) dan mode gelap (*dark*):
  - Menjamin kontras teks memenuhi standar keterbacaan (rasio kontras $\ge 4.5:1$).
  - Memastikan background tabel, kartu KPI, dialog modal, dropdown menu, dan form input tidak mengalami *text clipping* atau warna teks yang samar/sulit dibaca saat pengguna mengaktifkan dark mode.
  - Memastikan badge status (Aktif, Non-aktif, Menunggu, Ditolak, Selesai) memiliki warna kontras yang tegas di kedua tema.

### D7 — Responsivitas Mobile & Perangkat Layar Sentuh (390px)
- Seluruh tabel data utama (`DataTable`) pada tampilan mobile (lebar layar $\le 420$ px, standar iPhone 12/13/14 390px) wajib:
  - Memiliki pembungkus scrolling horizontal yang mulus tanpa menggeser topbar atau memotong layout induk.
  - Modal dialog formulir dan konfirmasi otomatis menyesuaikan lebar layar ($w \ge 90vw$) dengan tombol aksi yang mudah disentuh dengan jari (*touch target* $\ge 44 \times 44$ px).
  - Filter toolbar pada layar kecil bertransformasi menjadi layout vertikal bertumpuk (*stacked*) yang rapi tanpa tombol yang terpotong.

### D8 — Aksesibilitas Keyboard & Focus Management (A11y)
- Seluruh elemen interaktif (input formulir, tombol aksi, dropdown filter, tab bar) dapat diakses dan dioperasikan menggunakan navigasi keyboard (`Tab`, `Shift+Tab`, `Enter`, `Space`).
- Dialog modal menerapkan *focus trapping*: fokus keyboard tidak melompat ke latar belakang saat modal sedang aktif.
- Menekan tombol `Escape` pada dialog modal akan membatalkan atau menutup dialog dengan aman.
- Indikator fokus visual (*focus outline / ring*) terlihat jelas saat bernavigasi dengan keyboard.

### D9 — Pengujian Mutu Kode, Build, dan Integritas Rute
- **Zero Orphaned Routes:** Seluruh rute yang terdaftar pada Vue Router mengarah ke komponen yang valid dan terpakai.
- **Pembersihan File Demo:** File-file demo Sakai yang sudah tidak terpakai dibersihkan dari direktori views demo atau dinonaktifkan secara rapi.
- **Vite Build Clean:** Eksekusi `npm run build` berhasil 100% tanpa error dengan ukuran bundle optimal.
- **ESLint & Code Style:** Lolos pemeriksaan linter tanpa error.
- **Backend Test Suite:** Tidak ada efek samping terhadap backend; seluruh pengujian backend tetap lolos 100%.

### D10 — Prosedur UAT Terisolasi & Kepatuhan Port DEC-27
- Pengujian konsistensi antarmenu dijalankan pada lingkungan UAT terisolasi `.uat-phase10` (port 18028 untuk API dan 14028 untuk SPA).
- Skrip [`laravel-api/scripts/phase10-uat.php`](file:///D:/xampp-htdocs/app-administration_school/laravel-api/scripts/phase10-uat.php) ditambahkan probe otomatis:
  `php scripts/phase10-uat.php probe-ui-consistency`
  untuk memvalidasi ketiadaan menu demo pada konfigurasi menu, keberadaan rute aktif, dan konsistensi status.
- Disediakan checklist panduan review manual #1–#18 pada `PHASE-12C-MANUAL-REVIEW.md`.
- Kepatuhan DEC-27: server UAT wajib dihentikan setelah pengujian, port 18028 dan 14028 dipastikan bebas listener.

---

## 4. Tahapan Rencana Kerja Implementasi (S1–S8)

| Tahap | Deskripsi Pekerjaan | Artefak / File Target |
|:---:|---|---|
| **S1** | **Audit & Pembersihan Menu Demo Sakai** | Bersihkan grup menu demo dari `vue-app/src/layout/AppMenu.vue` dan hapus rute demo tidak terpakai dari `vue-app/src/router/index.js`. |
| **S2** | **Standardisasi Bahasa Indonesia Navigasi** | Perbarui seluruh label menu navigasi utama, sub-menu, dan header modul ke dalam bahasa Indonesia baku di `AppMenu.vue`. |
| **S3** | **Standardisasi Dialog Modal & Tombol Aksi** | Audit seluruh modal formulir & konfirmasi destruktif di modul Kesiswaan, Kepegawaian, Persuratan, Perizinan, Arsip, dan Tugas agar menggunakan styling PrimeVue seragam. |
| **S4** | **Harmonisasi Format Tanggal, Jam & Angka** | Pastikan format tanggal (`DD/MM/YYYY`), jam (`HH:mm WIB`), dan persentase numerik seragam di seluruh tabel dan kartu dasbor. |
| **S5** | **Polish Tema Light & Dark Mode** | Verifikasi dan perbaiki kontras warna teks, background tabel, modal dialog, dan kartu KPI pada mode terang dan mode gelap. |
| **S6** | **Optimasi Responsivitas Layar Ponsel (390px)** | Uji dan sesuaikan layout tabel horizontal scroll, touch target tombol, dan stack toolbar pada viewport mobile 390px. |
| **S7** | **Aksesibilitas Keyboard & Focus Management** | Uji navigasi keyboard (Tab, Enter, Escape) pada formulir, dialog modal, dan tab navigasi. |
| **S8** | **Verifikasi Build, Lint, Probe UAT & Laporan** | Jalankan Vite build, uji probe UAT `probe-ui-consistency`, siapkan `PHASE-12C-REPORT.md`, `PHASE-12C-MANUAL-REVIEW.md` (#1–#18), perbarui tracking docs, commit, dan push. |

---

## 5. Pertanyaan Keputusan Pemilik

Mohon konfirmasi pemilik dengan menyatakan:
> **"Saya setuju D1-D10"**

atau berikan arahan spesifik jika ada butir yang ingin disesuaikan sebelum eksekusi dimulai.
