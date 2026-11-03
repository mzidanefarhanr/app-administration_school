# Rencana Kerja Fitur — Subfase 11c (Tugas Operasional dan Pengingat Internal)

## 1. Ringkasan Eksekutif & Status Rencana

- **Subfase:** 11c — Tugas Operasional dan Pengingat Internal (*Operational Tasks & Reminders Center*).
- **Milestone Induk:** Fase 11 — Dashboard Operator dan Laporan.
- **Usulan Keputusan Arsitektur:** **DEC-44 APPROVED (D1–D10, 2026-11-03 WIB)**.
- **Baseline Kode:** `main` pada commit [`3cf80cf`](file:///D:/xampp-htdocs/app-administration_school) (pencatatan penerimaan pemilik Subfase 11b `#1–#18` dan penutupan Subfase 11b). Paket rencana: `38e5856`.
- **Status Rencana:** **PLAN APPROVED (DEC-44 APPROVED; D1–D10 DISETUJUI PEMILIK 2026-11-03 WIB)** berdasarkan pernyataan pemilik: *"Saya setuju D1-D10"*.
- **Status Implementasi:** **IN PROGRESS (Langkah S1–S8 diotorisasi)**.

---

## 2. Konteks & Latar Belakang Kebutuhan

Pada Subfase 11a dan 11b yang telah selesai lengkap dan disetujui pemilik:
1. Subfase 11a menggantikan seluruh widget demo template menjadi widget operasional nyata live di dashboard, termasuk widget ringkas `PendingTasksWidget.vue` yang menampilkan hingga 5 tugas mendesak per kategori.
2. Subfase 11b melengkapi kebutuhan analisis agregat sekolah melalui tren multi-tahun, demografi, audit kesiapan profil identitas Dapodik, serta rekonsiliasi berkas Dapodik lokal (menutup gerbang arsitektur DEC-12).

Namun, dalam operasional harian sekolah, operator sekolah, kepala sekolah, dan tenaga kependidikan membutuhkan **Pusat Tugas & Pengingat Operasional Terpadu (Operational Tasks & Reminders Center)** yang komprehensif, bukan sekadar cuplikan 5 item di beranda:
1. **Daftar Antrean Prioritas Lengkap Lintas Domain:** Operator membutuhkan antarmuka terpusat untuk meninjau seluruh item yang membutuhkan tindak lanjut operasional dari 6 domain utama:
   - **Persuratan (Letters):** Surat keluar yang masih menunggu persetujuan/tanda tangan pimpinan, surat masuk yang belum dibuatkan lembar disposisi, serta lembar disposisi terbuka yang mendekati atau telah melewati tenggat waktu (*overdue*).
   - **Perizinan (Leaves):** Permohonan izin siswa dan pegawai yang telah diajukan (*submitted*) dan menunggu keputusan persetujuan.
   - **Pembelajaran (Teaching):** Sesi kegiatan belajar mengajar yang belum diisi jurnalnya oleh guru pengampu (baik yang masih dalam masa tenggang toleransi $\le 7$ hari maupun yang telah lewat batas waktu).
   - **Arsip & Dokumen (Archives):** Dokumen penting sekolah yang telah kedaluwarsa atau mendekati tanggal akhir masa berlaku dalam 30 hari ke depan.
   - **Kualitas Data & Kesiswaan (Data Quality & Students):** Siswa aktif yang belum ditempatkan ke dalam rombongan belajar (*unplaced*), serta catatan profil identitas wajib (NIK/NISN) yang belum lengkap.
   - **Sistem & Keamanan (System & Backup):** Pemantauan status pencadangan sistem bila belum berjalan dalam rentang waktu yang diharapkan.
2. **Pengingat Khusus Operator (Custom Operator Reminders / Checklist):** Selain tugas otomatis yang dihasilkan sistem, staf administrasi sekolah sering kali memiliki tugas koordinasi manual internal (seperti *"Verifikasi berkas fisik pendaftaran murid mutasi"*, *"Kirimkan laporan cetak ke pengawas dinas"*, *"Koordinasi kelengkapan berkas bantuan siswa dengan wali kelas"*). Tugas internal manual ini membutuhkan pencatatan tanggal tenggat (*due date*), skala prioritas, serta pelacakan status (*pending, completed, cancelled*).
3. **Penyelesaian & Pengakuan Tugas (Task Completion & Dismissal):** Operator memerlukan kemampuan untuk menandai pengingat manual sebagai selesai, membatalkan tugas yang tidak relevan, atau menandai peringatan sistem sebagai "Sudah Ditinjau" (*acknowledged/dismissed*) agar tidak terus mendominasi antrean prioritas tinggi.
4. **Bebas Duplikasi & Tautan Langsung Objek (Dedup & Actionable Deep Links):** Antrean tugas tidak boleh menampilkan baris ganda saat halaman dimuat ulang (*refresh*), dan setiap tugas harus memiliki tautan aksi langsung yang membawa pengguna ke formulir atau halaman objek terkait dengan satu klik.

---

## 3. Usulan Keputusan Pemilik (DEC-44: D1–D10)

Berikut 10 butir usulan keputusan arsitektur untuk Subfase 11c:

### D1 — Cakupan Subfase 11c & Batasan Non-Goals
Kerjakan **Subfase 11c: Tugas dan Pengingat Internal Operator** saja. Fokus utama:
1. Mesin agregasi tugas sistem terpadu (*Unified Operational Task Engine*) yang merangkum item tertunda dari seluruh 6 modul operasional (Surat, Izin, Pembelajaran, Dokumen, Kualitas Data, Sistem).
2. Modul pengingat internal manual operator (*Custom Operator Reminders*) dengan dukungan tambah, ubah, selesaikan, batalkan, dan hapus.
3. Fitur penandaan status selesai untuk pengingat manual dan penandaan "Sudah Ditinjau" (*dismiss/acknowledge*) untuk pengingat sistem.
4. Halaman terpadu `/public/tasks` dengan 4 kartu metrik KPI dan 4 tab navigasi kerja.
5. Integrasi tautan balik dari widget dashboard `PendingTasksWidget.vue` ke pusat tugas `/public/tasks`.
- **Non-Goals**:
  - Tidak ada pengiriman notifikasi pesan eksternal via email, SMS, WhatsApp, atau integrasi push notification pihak ketiga (tetap mematuhi batasan roadmap).
  - Tidak ada background daemon cron atau queue worker eksternal baru (agregasi tugas sistem dievaluasi secara dinamis dan deterministik saat diakses melalui endpoint API).
  - Fase 12 (Performa, Observability & Konsistensi) belum dimulai dan baru akan direncanakan setelah Fase 11 selesai lengkap.

### D2 — Skema Database Additive `operator_reminders`
Menyediakan satu tabel database additive non-destruktif `operator_reminders` untuk menyimpan catatan pengingat internal manual dan status pengakuan/dismissal pengingat sistem:
- **Nama Tabel:** `operator_reminders`
- **Struktur Kolom:**
  - `id` (bigint unsigned auto-increment PK)
  - `user_id` (bigint unsigned nullable, FK ke `users.id` — pembuat atau penanggung jawab pengingat)
  - `title` (string 255 — judul tugas/pengingat)
  - `description` (text nullable — catatan tambahan/rincian tugas)
  - `category` (string 50: `general`, `letters`, `leaves`, `teaching`, `students`, `employees`, `archives`, `system`)
  - `priority` (string 20: `high`, `warn`, `info` — skala urgensi tugas)
  - `due_date` (date nullable — tanggal target penyelesaian tugas)
  - `status` (string 20: `pending`, `completed`, `cancelled` — default `pending`)
  - `system_source_key` (string 100 nullable, indexed — identifikasi deterministik tugas sistem yang di-dismiss/diakui)
  - `completed_at` (timestamp nullable — waktu penyelesaian tugas)
  - `completed_by` (bigint unsigned nullable, FK ke `users.id` — pengguna yang menyelesaikan tugas)
  - `created_at`, `updated_at` (timestamps Laravel)
- **Integritas:** Perubahan ini 100% additive, tidak memodifikasi atau menghapus tabel/kolom yang telah ada. Database MariaDB operasional port 3306 dan server Apache port 80/443 tidak disentuh.

### D3 — Mesin Agregasi Tugas Sistem Deterministik (Deterministic Unified Task Engine)
Layanan backend `OperationalTaskService.php` mengagregasikan item tugas dari seluruh modul dengan struktur data seragam dan key unik deterministik untuk menjamin deduplikasi:
1. **Surat (Letters):**
   - `letter-out-{id}`: Surat keluar status `review` menunggu persetujuan (urgensi `high`).
   - `letter-in-{id}`: Surat masuk status aktif yang belum memiliki disposisi (urgensi `warn`).
   - `disposition-{id}`: Disposisi terbuka yang telah atau hampir melewati tenggat waktu (urgensi `high`).
2. **Perizinan (Leaves):**
   - `leave-{id}`: Permohonan izin status `submitted` menunggu keputusan persetujuan (urgensi `high`).
3. **Pembelajaran (Teaching):**
   - `teaching-missing-{schedule_id}-{date}`: Sesi mengajar yang belum diisi jurnalnya (urgensi `warn` jika dalam grace period $\le 7$ hari; `high` jika telah overdue $> 7$ hari).
4. **Arsip & Dokumen (Archives):**
   - `archive-expiring-{id}`: Dokumen dengan masa berlaku berakhir dalam $\le 30$ hari ke depan (urgensi `warn`).
   - `archive-expired-{id}`: Dokumen yang masa berlakunya telah terlampaui (urgensi `high`).
5. **Kualitas Data & Kesiswaan (Data Quality & Students):**
   - `dq-student-unplaced-{id}`: Siswa aktif yang belum ditempatkan ke dalam rombel (urgensi `warn`).
   - `dq-issue-{domain}-{id}`: Isu kelengkapan data siswa atau pegawai penting yang belum ditinjau (urgensi `info`).
6. **Sistem & Cadangan (System & Backup):**
   - `system-backup-overdue`: Cadangan basis data belum diperbarui lebih dari 48 jam (urgensi `warn`).

Setiap entri tugas mengembalikan atribut standar:
`id`, `domain`, `category`, `title`, `description`, `date`, `due_date`, `urgency` (`high` | `warn` | `info`), `source_type` (`system` | `manual`), `status` (`pending` | `completed` | `cancelled`), dan `action_url` (rute tautan langsung).

### D4 — Logika Siklus Hidup & Penyelesaian Tugas
1. **Tugas Sistem Otomatis (*System Tasks*):**
   - Resolusi primer: tugas selesai secara alami (*native resolution*) ketika aksi di modul terkait telah dilakukan (misalnya: surat telah disetujui, izin telah diputuskan, jurnal mengajar telah dicatat, dokumen telah diperbarui).
   - Pengakuan/Abaikan (*Dismiss/Acknowledge*): operator dapat mengeklik tombol *"Tandai Sudah Ditinjau"* pada peringatan sistem. Sistem akan mencatat baris pada `operator_reminders` dengan `system_source_key` terisi dan status `completed`, sehingga peringatan tersebut langsung keluar dari antrean aktif dan berpindah ke tab riwayat selesai.
2. **Pengingat Manual Operator (*Custom Reminders*):**
   - Dikelola langsung melalui siklus hidup: `pending` $\rightarrow$ `completed` (dengan mencatat waktu dan aktor penyelesai) atau `cancelled` (bila dibatalkan).

### D5 — Hak Akses & Privasi Berbasis Peran (Role & Ability Guards)
Penyaringan antrean tugas menerapkan prinsip hak akses minimum (*least privilege*):
1. **Pimpinan Sekolah & Operator (`admin-sistem`, `operator-sekolah`, `kepala-sekolah`, `wakil-kesiswaan`):**
   - Memiliki visibilitas terhadap tugas operasional sekolah menyeluruh (*school-wide*).
   - Kepala Sekolah melihat tugas persetujuan surat dan permohonan izin.
   - Operator melihat tugas administratif surat masuk, kelengkapan data, dan arsip.
   - Admin Sistem dan Operator dapat membuat, mengedit, menyelesaikan, dan membatalkan pengingat internal manual.
2. **Guru Pengampu (`guru` / `teaching.view` tanpa wewenang pimpinan):**
   - Tampilan tugas dibatasi secara ketat hanya pada urusan personal (*personal tasks*): sesi mengajar miliknya yang belum diisi jurnalnya dalam masa tenggang toleransi (`missing_grace`), serta permohonan izin pribadi yang diajukannya.
   - Guru tidak dapat melihat persuratan dinas sekolah, arsip umum, status cadangan sistem, atau pengingat internal operator.

### D6 — Kontrak API Subfase 11c (`/api/tasks/*`)
Mendaftarkan 8 endpoint baru di bawah prefix `/api/tasks/*` pada `laravel-api/routes/api.php` dengan autentikasi `auth:sanctum`:
1. `GET /api/tasks`: Mengambil daftar tugas dan pengingat terfilter (parameter opsional: `tab=all|system|manual|completed`, `domain`, `urgency`, `search`, `page`, `per_page`).
2. `GET /api/tasks/summary`: Ringkasan metrik statistik jumlah tugas per domain dan skala urgensi untuk kartu KPI dan indikator badge.
3. `POST /api/tasks/reminders`: Membuat pengingat internal manual baru (validasi: `title` wajib, `category`, `priority`, `due_date`, `description` opsional).
4. `GET /api/tasks/reminders/{id}`: Mengambil rincian pengingat manual tertentu.
5. `PUT/PATCH /api/tasks/reminders/{id}`: Memperbarui data pengingat manual internal.
6. `POST /api/tasks/reminders/{id}/complete`: Menandai pengingat manual sebagai selesai.
7. `POST /api/tasks/reminders/{id}/cancel`: Membatalkan pengingat manual.
8. `POST /api/tasks/system/dismiss`: Menandai tugas sistem tertentu telah ditinjau/diakui (*dismissed*).

### D7 — Desain Antarmuka Pengguna (UI) `/public/tasks`
Menyediakan halaman mandiri SPA terpadu **"Pusat Tugas & Pengingat Operasional"** (*Operational Tasks & Reminders*) di rute `/public/tasks` yang didaftarkan pada menu navigasi `AppMenu.vue` (grup Home):
1. **Header & Aksi Cepat:** Judul halaman yang informatif, tombol *"Buat Pengingat Baru"*, tombol muat ulang (*Refresh*), serta kolom pencarian kata kunci interaktif.
2. **4 Kartu Metrik KPI di Baris Atas:**
   - **Tugas Mendesak (*High Urgency*):** jumlah item yang membutuhkan perhatian segera (badge merah).
   - **Peringatan (*Warning*):** jumlah item mendekati batas waktu (badge oranye/kuning).
   - **Tugas Rutin (*Routine/Info*):** tugas pemantauan berkala (badge biru/cyan).
   - **Selesai Hari Ini (*Completed Today*):** jumlah pengingat yang telah diselesaikan pada hari aktif (badge hijau).
3. **4 Tab Navigasi Kerja:**
   - **Tab 1: Semua Tugas Aktif (*All Active Tasks*):** tampilan gabungan tugas sistem dan pengingat manual yang belum selesai.
   - **Tab 2: Tugas Sistem Otomatis (*System Tasks*):** daftar tugas otomatis dari modul surat, izin, mengajar, dokumen, dan data quality.
   - **Tab 3: Pengingat Operator (*Custom Reminders*):** daftar tugas checklist internal manual buatan operator.
   - **Tab 4: Riwayat Selesai (*Completed & Dismissed*):** log tugas dan pengingat yang telah diselesaikan atau ditandai sudah ditinjau.
4. **Tindakan Baris Interaktif:** Tombol centang cepat selesai, tombol tautan langsung ke halaman terkait (*direct action link*), serta menu edit/batal/hapus untuk pengingat manual.
5. **Dialog Formulir Tambah/Edit:** Dialog modal PrimeVue yang responsif dengan input teks judul, dropdown kategori, pemilih tanggal tenggat (*Calendar/DatePicker*), pemilih prioritas (*SelectButton/Dropdown*), dan area teks deskripsi.

### D8 — Ketahanan Kinerja & Bebas Duplikasi saat Muat Ulang
1. **Bebas Duplikasi (*Deduplication Guarantee*):** Setiap tugas sistem memiliki formula ID deterministik. Saat pengguna memuat ulang halaman (*refresh browser*), daftar tugas tetap konsisten tanpa duplikasi baris.
2. **Optimasi Latensi Kueri:** Layanan agregasi menggunakan kueri terindeks dengan klausa pembatasan kuota (*indexed limit*) per domain, serta pemanfaatan `whereDoesntHave` dan kueri terarah sehingga waktu eksekusi agregasi backend tetap sangat cepat ($\le 150$ ms).
3. **Paginasi Server-Side:** Endpoint daftar tugas mendukung paginasi dinamis (`page` & `per_page`) untuk menangani volume tugas yang besar secara efisien.

### D9 — Integrasi Terpadu dengan Dashboard (Subfase 11a)
1. **Tautan Balik dari Widget Beranda:** Komponen `PendingTasksWidget.vue` pada dashboard (`/`) ditambahkan tombol navigasi di bagian bawah: *"Buka Seluruh Tugas & Pengingat"* yang mengarahkan langsung ke halaman `/public/tasks`.
2. **Konsistensi Data Real-Time:** Jika operator menyelesaikan atau menandai tugas di halaman `/public/tasks`, pembaruan tersebut langsung tercermin pada metrik dashboard saat kembali ke beranda.

### D10 — Lingkungan UAT Terisolasi & Kepatuhan DEC-27
1. **Pengujian Terisolasi:** Menggunakan kembali lingkungan UAT terisolasi `.uat-phase10` (port `18028` untuk API backend dan `14028` untuk frontend SPA) dengan database SQLite terisolasi `.uat-phase10/database.sqlite`.
2. **Data Uji Sintetis Idempoten:** Perintah `php scripts/phase10-uat.php prepare-tasks` disiapkan untuk menginjeksikan $\ge 15$ pengingat operator sintetis lintas kategori dan prioritas secara idempoten tanpa merusak data uji yang telah ada.
3. **Probe HTTP Otomatis:** Perintah `php scripts/phase10-uat.php probe-tasks` disediakan untuk memverifikasi 8 endpoint `/api/tasks/*` secara otomatis.
4. **Kepatuhan DEC-27:** Port `18028` dan `14028` harus dipastikan dihentikan kembali setelah sesi peninjauan selesai (`PORTS_18028_14028_FREE`). Apache port 80/443 dan MariaDB port 3306 tidak disentuh.

---

## 4. Matriks Hak Akses & Otorisasi

| Peran Pengguna | Melihat Tugas Sistem Sekolah | Melihat Tugas Personal | Mengelola Pengingat Manual (`tasks.manage`) | Menandai Selesai / Dismiss |
|---|:---:|:---:|:---:|:---:|
| **Admin Sistem** (`uat.admin`) | Ya (Penuh) | Ya | Ya (Penuh) | Ya |
| **Operator Sekolah** (`uat.operator`) | Ya (Penuh) | Ya | Ya (Penuh) | Ya |
| **Kepala Sekolah** (`uat.kepsek`) | Ya (Surat, Izin, Pembelajaran) | Ya | Hanya Baca | Ya |
| **Wakil Kesiswaan** (`uat.waka`) | Ya (Kesiswaan, Izin, Surat) | Ya | Hanya Baca | Ya |
| **Guru Pengampu** (`uat.guru`) | **Tidak** (Dibatasi) | Ya (Jurnal & Izin Sendiri) | **Tidak** | Hanya Izin/Jurnal Sendiri |

---

## 5. Rencana Tahapan Eksekusi (S1–S8)

Setelah pemilik menyetujui D1–D10, implementasi dikerjakan secara terstruktur melalui 8 langkah:

- **S1 — Additive Migration:** Membuat berkas migrasi penambahan tabel `operator_reminders` dengan indeks `system_source_key` dan foreign key relasional.
- **S2 — Eloquent Model & Logika Bisnis:**
  - Membuat model `OperatorReminder.php` dengan konstanta status dan prioritas.
  - Membangun layanan terpadu `OperationalTaskService.php` untuk agregasi lintas modul, deduplikasi, dan manajemen pengingat manual.
- **S3 — Controller & Routing Backend:**
  - Membuat `OperationalTaskController.php` yang melayani 8 endpoint API.
  - Mendaftarkan rute `/api/tasks/*` pada `laravel-api/routes/api.php` dengan middleware `auth:sanctum`.
- **S4 — Suite Feature Test Backend:**
  - Membuat `OperationalTaskApiTest.php` mencakup skenario pengujian `T1–T12` (agregasi lintas peran, pembuatan pengingat, validasi input, penyelesaian tugas, pembatalan, dismissal tugas sistem, dan pencegahan duplikasi).
- **S5 — Komponen Frontend SPA:**
  - Membangun view mandiri `vue-app/src/views/tasks/TasksView.vue` berisi kartu KPI, bilah filter, 4 tabulasi, tabel daftar tugas, dan modal dialog tambah/edit pengingat.
  - Mendaftarkan rute `/public/tasks` pada `vue-app/src/router/index.js`.
  - Mendaftarkan menu navigasi *"Tasks & Reminders"* pada `vue-app/src/layout/AppMenu.vue`.
  - Menghubungkan tombol deep-link pada widget beranda `vue-app/src/components/dashboard/PendingTasksWidget.vue`.
- **S6 — Verifikasi Kualitas Kode & Build:**
  - Menjalankan Laravel Pint (`vendor/bin/pint --test`).
  - Menjalankan ESLint pada komponen Vue baru (`npx eslint`).
  - Menjalankan production build Vite (`npm run build`).
  - Menjalankan feature test backend dan regresi penuh (`php artisan test`).
- **S7 — Perluasan UAT Terisolasi & HTTP Probe:**
  - Menambahkan sub-perintah `prepare-tasks` dan `probe-tasks` pada `laravel-api/scripts/phase10-uat.php`.
  - Menjalankan probe otomatis 5/5 PASS.
  - Memastikan server UAT dihentikan per DEC-27 (`PORTS_18028_14028_FREE`).
- **S8 — Laporan & Panduan Review Pemilik:**
  - Menyusun `PHASE-11C-REPORT.md` dan `PHASE-11C-MANUAL-REVIEW.md` (#1–#18).
  - Melakukan sinkronisasi dokumentasi repositori, commit, dan push ke `origin/main`.

---

## 6. Draft Checklist Review Manual Pemilik (#1–#18)

| No | Skenario Pengujian yang Direncanakan |
|---|---|
| **#1** | Navigasi menu *"Tasks & Reminders"* pada sidebar dan verifikasi pembukaan URL `/public/tasks`. |
| **#2** | Verifikasi 4 kartu KPI ringkasan tugas (Mendesak, Peringatan, Rutin, Selesai Hari Ini) pada baris atas. |
| **#3** | Verifikasi agregasi tugas Surat Keluar status `review` pada tab Tugas Sistem. |
| **#4** | Verifikasi agregasi tugas Surat Masuk belum didisposisi pada tab Tugas Sistem. |
| **#5** | Verifikasi agregasi tugas Permohonan Izin status `submitted` pada tab Tugas Sistem. |
| **#6** | Verifikasi agregasi tugas Jurnal Mengajar belum diisi (grace period vs overdue). |
| **#7** | Verifikasi agregasi tugas Dokumen Kedaluwarsa & Segera Kedaluwarsa. |
| **#8** | Verifikasi agregasi isu Kualitas Data (siswa unplaced & profil identitas belum lengkap). |
| **#9** | Pengujian tautan langsung aksi (*Action URL*): klik tugas langsung membuka halaman modul terkait. |
| **#10** | Pengujian tombol *"Tandai Sudah Ditinjau"* (*Dismiss*) pada tugas sistem otomatis. |
| **#11** | Pembuatan pengingat internal manual baru melalui modal dialog (*Create Custom Reminder*). |
| **#12** | Validasi formulir pengingat manual (penolakan jika judul kosong, pemilihan tanggal tenggat dan prioritas). |
| **#13** | Pengeditan data pengingat manual yang sudah dibuat (*Edit Reminder*). |
| **#14** | Penyelesaian pengingat manual via tombol centang cepat (*Complete Reminder*). |
| **#15** | Pembatalan pengingat manual (*Cancel Reminder*). |
| **#16** | Filter pencarian kata kunci dan penyaringan berdasarkan kategori domain dan skala urgensi. |
| **#17** | Tab Riwayat Selesai memuat seluruh tugas yang telah diselesaikan atau ditandai ditinjau. |
| **#18** | Pembatasan akses berbasis peran: login sebagai `uat.guru` memastikan tugas terbatas hanya pada sesi/izin pribadi tanpa kebocoran data operasional sekolah. |

---

## 7. Rekomendasi Tindakan Selanjutnya

Rencana di atas memerlukan peninjauan dan persetujuan dari pemilik proyek:
- Jika pemilik menyetujui usulan keputusan **D1–D10** di atas, silakan sampaikan konfirmasi:
  > **"Saya setuju D1-D10"**
- Setelah persetujuan diberikan, persetujuan akan dicatat secara resmi sebagai **DEC-44 APPROVED**, dan implementasi kode Subfase 11c (langkah S1–S8) akan segera dieksekusi.
