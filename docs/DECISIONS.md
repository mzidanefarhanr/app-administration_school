# Register keputusan

Status awal: keputusan belum dikonfirmasi kecuali tujuan umum dan penggunaan OpenCode. Agent boleh mengusulkan nilai, tetapi keputusan penting tidak dicatat APPROVED tanpa jawaban pemilik.

## Keputusan disetujui pemilik

### DEC-03 — Role dan kemampuan (APPROVED, 2026-10-15)
- Konteks: penetapan peran dan penegakan akses backend (Fase 01a).
- Pilihan pemilik:
  1. Peran awal: Admin Sistem; Operator Sekolah; Kepala Sekolah; Wakil Kepala Sekolah Bidang Kesiswaan (menggantikan Guru/Wali Kelas, lingkup awal: data kelas, penjadwalan, penilaian asesmen guru).
  2. Ekspor: Admin Sistem, Operator Sekolah, Kepala Sekolah boleh mengekspor sesuai lingkup akses datanya. Hanya Kepala Sekolah yang awalnya diberi kemampuan menyetujui surat, menyetujui izin, dan menandatangani dokumen. Wakil Kesiswaan tidak mendapat ekspor/persetujuan/penandatanganan secara default.
  3. Fleksibilitas: Admin Sistem dapat mengatur kemampuan tiap peran dan menetapkan peran pengguna. Konfigurasi di atas adalah nilai AWAL, bukan aturan yang dikunci di kode. Kemampuan bernama + enforcement backend terpusat; pisahkan view/create/update/delete/export/approve/sign; hindari cek inline `type_user_id`; admin tidak otomatis approve/sign; user tidak boleh eskalasi diri; admin pengelola akses terakhir dilindungi; perubahan izin berlaku pada request berikutnya; audit perubahan peran/izin tanpa rahasia.
- Dampak schema: tabel `roles`, `abilities`, `ability_role`, `role_user` (migration 2029_07_20_*). Field `type_users`/`status_user_id` legacy tidak diubah.
- Dampak API/UI: `GET/POST/PATCH/DELETE /api/roles`, `GET /api/abilities`, `POST/DELETE /api/users/{user}/roles[/{role}]`; middleware `active`; halaman `/master/roles`.
- Status: APPROVED. Fase affected: 01a.

### DEC-14 — Registrasi publik, akun nonaktif, ekspor siswa, pemetaan legacy (APPROVED, 2026-10-14 WIB)
- Konteks: penyelesaian Fase 01a; keputusan pemilik tertulis atas kebijakan autentikasi dan ekspor.
- Pilihan pemilik:
  1. **Registrasi publik ditutup**; akun dibuat oleh admin. (`POST /register` tetap dihapus.)
  2. **Akun `status_active=false`** ditolak login dan ditolak pada request berikutnya dari sesi aktif. `status_active` **tidak disamakan** dengan `status_user_id`.
  3. **Ekspor siswa dikendalikan kemampuan `students.export`** pada frontend dan backend, dan mengikuti lingkup data yang boleh diakses (tidak memberi akses tambahan melebihi akses baca).
  4. **Pemetaan legacy**: `Admin` → Admin Sistem. `User` **tidak** otomatis menjadi Operator Sekolah (peran ditetapkan manual admin). Tipe tak dikenal/tanpa tipe → tanpa peran (default deny). Akun operasional tidak diubah; perbaiki mekanisme pemetaan yang belum diterapkan ke operasional tanpa menghapus penetapan peran eksplisit admin.
  5. **Bahasa UI**: semua teks antarmuka yang ditambah/diubah pada pekerjaan ini memakai **English** (nama tampilan peran: System Administrator, School Operator, Principal, Vice Principal for Student Affairs). Identifier peran/kemampuan tetap. Fitur pergantian bahasa (i18n) **di luar scope**.
- Dampak API/UI: endpoint baru `GET /api/students/export` (CSV) dengan `students.view` + `students.export`; `Student.vue` mengunduh via endpoint (bukan `dt.exportCSV()`); tombol ekspor mengikuti izin aktual; teks UI halaman roles/matriks/penetapan peran/ekspor memakai English.
- Dampak schema/migration: migration baru memperbaiki auto-map legacy (hanya `Admin`→Admin Sistem; `User` tidak dipetakan otomatis) sebagai perubahan additive, tanpa menghapus `role_user` yang ditetapkan admin eksplisit.
- Status: APPROVED. Fase affected: 01a (penyelesaian).

### DEC-15 — Alur error autentikasi, pemulihan CSRF, dan reset password (APPROVED, 2026-10-16 WIB)
- Konteks: implementasi Fase 01b berdasarkan audit dan jawaban pemilik atas Q1–Q3.
- Pilihan pemilik:
  1. **Reset password (Q1)**: Form forgot password dan form password baru masuk scope 01b (dikerjakan setelah C-01–C-04 stabil). UAT lokal memakai `MAIL_MAILER=log` tanpa mengirim email nyata ke server/kredensial luar.
  2. **Sesi kedaluwarsa (Q2)**: Saat sesi aktif kedaluwarsa (401 pada request selain login/guest check), pengguna diarahkan ke `/auth/login` dengan pesan English yang tetap terlihat: "Your session has expired. Please sign in again." Tidak memakai modal konfirmasi.
  3. **Pemulihan CSRF 419 (Q3)**: Pada respons 419 yang dipastikan berasal dari penolakan CSRF, interceptor menyegarkan `csrf-cookie` sekali lalu mengulangi request asli maksimal sekali dengan payload utuh. Jika retry tetap gagal, tampilkan pesan pemulihan yang jelas tanpa loop.
- Dampak API/UI: interceptor `apiLogin` diperbaiki; routing dan form `/auth/forgot-password` serta `/auth/reset-password/:token` ditambahkan; `Logout.vue` dan `Profile.vue` diselaraskan ke English; `.env.uat` memakai `MAIL_MAILER=log`.
- Status: APPROVED. Fase affected: 01b.

### DEC-16 — Isolasi fixture, bootstrap admin, dan standar password (APPROVED, 2026-10-17 WIB)
- Konteks: perencanaan dan implementasi Fase 01c berdasarkan temuan audit fixture, penanganan BLK-03, dan jawaban pemilik atas Q-01c-1 s/d Q-01c-3.
- Pilihan pemilik:
  1. **Bootstrap admin pertama (Q-01c-1)**: Melalui CLI Artisan interaktif (`php artisan app:bootstrap-admin`), kata sandi diketik tersembunyi dengan konfirmasi. Ditolak bila pengguna aktif yang mampu mengelola akses (`canManageAccess()`) sudah ada; tidak mengubah akun lama.
  2. **Pembersihan data siswa riil seeder (Q-01c-2)**: Ganti data siswa riil dalam seeder dengan 10–20 siswa sintetis khusus lingkungan pengembangan/UAT terisolasi (`SyntheticDataSeeder`). `DatabaseSeeder` default hanya mengisi referensi murni (`ReferenceDataSeeder`) tanpa akun/password bawaan.
  3. **Standar password (Q-01c-3)**: Minimal 12 karakter tanpa kewajiban kombinasi huruf besar/kecil/angka. Diterapkan konsisten pada pembuatan akun, bootstrap, perubahan password, dan reset password. Akun lama tidak dipaksa mengubah kata sandi secara massal.
- Dampak API/UI: perintah Artisan `app:bootstrap-admin`; seeder dipisah ke `ReferenceDataSeeder` dan `SyntheticDataSeeder`; `StudentSeeder` lama dibersihkan dari data riil; validasi password minimal 12 karakter di backend dan form frontend; validasi keunikan nama pada `UserController` dihapus (nama boleh sama); validasi NIK string 16 digit angka (`digits:16`).
- Status: APPROVED. Fase affected: 01c.

### DEC-17 — Kebijakan audit trail, strategi backup, dan retensi arsip (APPROVED, 2026-10-17 WIB)
- Konteks: perencanaan operasional Fase 02 (audit & backup/restore) berdasarkan instruksi pemilik 17 Oktober 2026 WIB.
- Pilihan pemilik:
  1. **Audit trail & rollback**: Rollback audit dinonaktifkan sepenuhnya. Audit trail bersifat *immutable append-only* murni untuk mencegah inkonsistensi foreign key dan integritas relasional; perbaikan kesalahan data dilakukan via mutasi baru yang tercatat secara resmi. Endpoint klien `POST /api/activities` dan `PUT/PATCH /api/activities/{id}` dinonaktifkan/dihapus; seluruh catatan audit dibuat oleh server-side observer/events.
  2. **Lokasi backup (DEC-02)**: Backup privat lokal di server (`storage/app/backups`) dengan mekanisme salinan berkala ke perangkat terpisah oleh operator.
  3. **Retensi & frekuensi (DEC-09)**: Retensi bertingkat: 7 backup harian terakhir + 4 backup mingguan terakhir. Backup yang melebihi batas retensi dibersihkan secara otomatis.
  4. **Enkripsi arsip**: Enkripsi wajib diterapkan untuk arsip backup operasional (misal ZIP terenkripsi AES-256), dengan manajemen kunci/rahasia enkripsi disimpan terpisah dari repositori kode.
- Dampak API/UI: Subfase 02a mengunci audit logging ke server-side dan mematikan rute rollback klien; Subfase 02b membangun command backup ZIP terenkripsi + manifest SHA-256 dan restore drill; Subfase 02c mengaktifkan scheduler rotasi retensi.
- Status: APPROVED (Kebijakan). Fase affected: 02 (02a, 02b, 02c). Catatan: persetujuan kebijakan ini bukan penerimaan manual 02a atau otorisasi implementasi 02b–02c.

### DEC-18 — Operasional backup manual dan restore drill (APPROVED, 2026-10-19 WIB)
- Konteks: pelaksanaan implementasi Subfase 02b berdasarkan instruksi pemilik 19 Oktober 2026 WIB.
- Pilihan pemilik:
  1. **Target restore drill lokal**: Target database disposable untuk drill lokal ditetapkan pada `school_restore_drill_uat` (MySQL lokal port 3306). Database operasional (`backdata_administrations`) dan database aktif sumber dilarang keras dijadikan target pemulihan.
  2. **Kemampuan `backup.manage`**: Default kemampuan `backup.manage` (menjalankan backup, melihat daftar arsip, dan mengunduh berkas) hanya diberikan kepada peran Admin Sistem (`admin-sistem`). Pengaturan ini tetap fleksibel dan dapat dikonfigurasi melalui matriks kemampuan (`/master/roles`) tanpa mengubah kode.
  3. **Penyimpanan privat & enkripsi arsip**: Mempertahankan keputusan DEC-17: direktori privat `storage/app/backups`, enkripsi arsip wajib standar ZIP AES-256 dengan rahasia terpisah (tanpa masuk argumen proses/log/repo), serta prosedur salinan berkala ke perangkat eksternal.
- Dampak schema/API/UI: pendaftaran kemampuan `backup.manage` pada `AbilityCatalog`; command `app:backup-run` dan `app:restore-drill`; rute API `GET /api/backups` dan `GET /api/backups/{filename}/download`.
- Status: APPROVED. Fase affected: 02b.

### DEC-19 — Penjadwalan, retensi otomatis, dan monitoring backup (APPROVED, 2026-10-20 WIB)
- Konteks: pelaksanaan implementasi Subfase 02c berdasarkan instruksi pemilik 20 Oktober 2026 WIB.
- Pilihan pemilik:
  1. **Jadwal otomatis**: Backup harian dijadwalkan pukul **02:00 Asia/Jakarta (WIB)**; rotasi retensi dijadwalkan pukul **02:30 Asia/Jakarta (WIB)**. Default aktivasi penjadwalan otomatis disetel OFF (`BACKUP_SCHEDULE_ENABLED=false`) demi keamanan operasional dan pencegahan eksekusi tak disengaja.
  2. **Monitoring & Alerting awal**: Cukup melalui file terstruktur `monitoring.json`, endpoint API berizin `GET /api/backups/status` (`backup.manage`), dan log sistem Laravel; belum ada notifikasi eksternal (webhook/email). Pemantauan bersifat pasif dan memerlukan pemeriksaan oleh operator/admin.
  3. **Pemisahan Task OS**: Implementasi kode tidak mencakup pemasangan atau aktivasi task pada sistem operasi (Windows Task Scheduler / cron) dan tidak mengeksekusi backup terhadap database produksi.
- Dampak schema/API/UI: penambahan command `app:backup-prune`; mode `--non-interactive` pada `app:backup-run`; endpoint API `GET /api/backups/status`; pendaftaran jadwal di `app/Console/Kernel.php` dengan timezone `Asia/Jakarta`.
- Status: APPROVED. Fase affected: 02c.

### DEC-20 — Kebijakan identitas siswa, keanggotaan rombel, dan audit pendataan (APPROVED, 2026-10-20 WIB)
- Konteks: pelaksanaan implementasi Subfase 03a dan perencanaan Fase 03 berdasarkan instruksi pemilik 20 Oktober 2026 WIB (menuntaskan usulan DEC-05).
- Pilihan pemilik:
  1. **NIK opsional & format string**: NIK bersifat opsional (nullable). Jika diisi, wajib string tepat 16 digit angka (`digits:16`) dan unik (mengabaikan record terhapus soft delete). Karakter leading zero ('0') wajib dipertahankan; input kosong (string kosong/whitespace) dinormalisasi konsisten menjadi `null`.
  2. **NIS unik lingkup sekolah**: NIS bersifat unik dalam lingkup sekolah.
  3. **Keanggotaan rombel aktif per siswa per tahun ajaran**: Maksimal satu keanggotaan rombel aktif per siswa per tahun ajaran (`school_year_id`). Riwayat perpindahan kelas/mutasi tetap disimpan.
  4. **Perilaku soft delete & restore siswa**: Soft delete siswa menonaktifkan keanggotaan rombel aktif tanpa menghapus riwayat. Restore memeriksa potensi konflik keunikan identitas dan tidak otomatis mengaktifkan kembali rombel lama.
  5. **Audit pendataan terarah & proteksi privasi**: Log audit mencatat perubahan identitas dan data akademik seperlunya; mengecualikan data riwayat medis/penyakit (`history_illness`) serta penghasilan orang tua/wali; meminimalkan penyalinan NIK dan data pribadi pada log.
- Dampak schema/API/UI:
  - Additive migration menambahkan `student_id` nullable pada `student_rombels` dengan FK ke `students.id`.
  - Backfill aman berbasis kecocokan NIK tunggal; NIK kosong/ambigu/tidak cocok dibiarkan unresolved tanpa mutasi destruktif.
  - Perhitungan umur dinamis (*virtual derived age*) dari `birthdate` berbasis timezone `Asia/Jakarta`.
  - Model `Student` dan `StudentRombel` menyelaraskan relasi dan trait `Loggable` dengan allowlist audit yang meminimalkan data pribadi.
- Status: APPROVED. Fase affected: 03 (03a, 03b, 03c).

### DEC-21 — Pengarsipan administratif siswa, keunikan identitas, dan kontrol konkurensi (APPROVED, 2026-10-21 WIB)
- Konteks: pelaksanaan implementasi Subfase 03b berdasarkan instruksi pemilik 21 Oktober 2026 WIB.
- Pilihan pemilik:
  1. **Pengarsipan administratif terpisah dari Mutasi Keluar**: Soft delete siswa menonaktifkan seluruh keanggotaan rombel yang masih aktif tanpa mengubah status menjadi Mutasi Keluar (`student_status_id`) dan tanpa mengubah tanggal keluar sekolah (`latest_student_exit_date`). Waktu dan alasan pengarsipan disimpan terpisah (kolom `archived_at` dan `archive_reason` pada `student_rombels`, serta `archive_reason` pada `students`). Restore siswa tidak otomatis mengaktifkan kembali rombel lama (tetap nonaktif).
  2. **Keunikan NIK/NIS mencakup record terarsip**: Unconditional unique index pada tabel `students` dipertahankan sesuai skema aktual tanpa pelemahan; siswa aktif maupun terarsip tidak boleh memiliki NIK/NIS yang berbentrok.
  3. **Pemeriksaan versi (version check)**: Expected version divalidasi dan dinaikkan atomik pada operasi update, archive (soft delete), dan restore untuk mencegah race condition / lost update.
- Dampak schema/API/UI:
  - Additive migration menambahkan `archive_reason` pada `students`, serta `archived_at` dan `archive_reason` pada `student_rombels`.
  - Trigger single-active rombel MariaDB & SQLite diselaraskan (`archived_at IS NULL`), serta menolak aktivasi rombel baru untuk siswa terarsip.
  - Endpoint `PUT /api/students/{id}`, `DELETE /api/students/{id}`, dan `POST /api/students/{id}/restore` menegakkan expected version dan atomisitas transaksi.
  - UI `Student.vue` menyediakan dialog form CRUD, penanganan inline 422, dialog konflik 409 dengan tombol reload data terbaru, konfirmasi archive dengan alasan administratif, dan tombol restore.
- Status: APPROVED. Fase affected: 03b.

### DEC-22 — Daftar siswa server-side, penempatan, rekap, dan ekspor terfilter (APPROVED, 2026-10-21 WIB)
- Konteks: pelaksanaan Subfase 03c berdasarkan instruksi pemilik 21 Oktober 2026 WIB; melengkapi DEC-20/DEC-21 tanpa mengubah semantik arsip/restore.
- Pilihan pemilik:
  1. **Siswa tidak terarsip berbeda dari keanggotaan rombel aktif.** Rekap tahun ajaran menghitung **siswa unik tidak terarsip** yang memiliki keanggotaan aktif pada tahun tersebut; bukan seluruh siswa tidak terarsip.
  2. **Filter All / Placed / Unplaced di halaman Students.** `Unplaced` = tidak memiliki keanggotaan aktif pada tahun terpilih (bukan berarti belum pernah punya rombel). Bila `Unplaced` dipilih, filter rombel dikosongkan/dinonaktifkan.
  3. **Paginasi** default 20, pilihan 10/20/50/100, maksimum 100.
  4. **Ekspor** wajib `school_year_id`, mengikuti pencarian/rombel/placement, mencakup seluruh hasil filter tanpa terikat halaman. Batas baris tetap menolak **422**, tanpa memotong diam-diam.
  5. **Aksi penempatan rombel di luar 03c**; dicatat pada roadmap.
  6. **Arsip tetap melalui dialog Archived.** Daftar utama/rekap tidak mencampurkan siswa terarsip.
  7. **Atribut daftar** hanya `id`, `version`, nama, NIS, NISN, label gender, serta ID/nama rombel dan placement tahun terpilih. Tanpa NIK, birthdate, alamat, telepon, medis, penghasilan, atau alasan arsip. Detail melalui endpoint detail berizin.
- Dampak API/UI:
  - `GET /api/students` (tanpa `only_archived`) menjadi daftar server-side: `school_year_id` wajib, `search` (nama/NIS/NISN), `sort` (`id|name|nis|nisn`), `direction`, `placement` (`all|placed|unplaced`), `rombel`, `per_page` (10/20/50/100), `page`; respons memuat `data`, `meta`, dan `recap` (`total_matches`, `placed_count`, `unplaced_count`, `by_gender`, `by_rombel`). `only_archived=1` dipertahankan untuk dialog Archived.
  - `GET /api/students/export` memakai definisi filter yang sama; `placement` opsional (default `placed` agar perilaku tahun ajaran tetap), `unplaced` menghasilkan rombel kosong tanpa mengarang data.
  - `Student.vue` memakai pagination/pencarian/sort/filter server, rekap server, guard respons usang, serta state loading/empty/error.
- Status: APPROVED. Fase affected: 03c.

### DEC-23 — Penempatan dan perpindahan rombel siswa (APPROVED, 2026-10-26 WIB)
- Konteks: pelaksanaan implementasi Subfase tambahan 03d berdasarkan instruksi pemilik 26 Oktober 2026 WIB; melengkapi alur penempatan siswa di UI setelah Subfase 03c ditutup.
- Pilihan pemilik:
  1. **Assign dan Transfer saja**: Hanya mengimplementasikan Assign Rombel (untuk siswa Unplaced) dan Transfer Rombel (untuk siswa Placed). Fitur koreksi/`is_correction` ditunda; jangan menampilkan checkbox koreksi di UI.
  2. **Tahun ajaran terpilih**: Menggunakan tahun ajaran aktif yang sedang dipilih pada halaman Students (`school_year_id`). Server memvalidasi rombel tujuan wajib terdaftar pada tahun ajaran tersebut; tidak mengganti diam-diam dengan tahun lain.
  3. **Transfer dalam tahun ajaran yang sama**: Transfer rombel dibatasi hanya antar-rombel dalam tahun ajaran yang sama. Perpindahan lintas tahun ajaran ditunda ke Fase 06.
  4. **Tanggal efektif hari berjalan (Asia/Jakarta)**: Server menentukan tanggal efektif penempatan/perpindahan berdasarkan tanggal hari ini di timezone `Asia/Jakarta`. Tanggal mundur atau terjadwal dari input pengguna tidak didukung.
  5. **Preservasi riwayat keanggotaan**: Transfer rombel menutup keanggotaan lama dengan mencatat `latest_student_exit_date = $today` (sehingga trigger DB tidak lagi menganggapnya aktif) dan membuat keanggotaan baru. `student_status_id` keanggotaan lama **tidak diubah** menjadi Mutasi Keluar karena siswa hanya berpindah kelas, bukan keluar sekolah.
  6. **Otorisasi berbasis kemampuan**: Otorisasi ditegakkan secara seragam menggunakan kemampuan `students.manage`, bukan pengecekan nama atau ID peran.
  7. **Kontrol konkurensi atomik**: Setiap operasi Assign dan Transfer mewajibkan parameter `expected_student_version` dan menaikkan versi siswa (`students.version`) dalam satu transaksi atomik ber-row-lock InnoDB (`Student::lockForUpdate`). Pada Transfer, `expected_membership_version` juga divalidasi.
- Dampak schema/API/UI:
  - Penambahan endpoint `POST /api/students/{id}/assign-rombel` dan `POST /api/students/{id}/transfer-rombel`.
  - UI `Student.vue`: tombol Assign Rombel (Unplaced) dan Transfer Rombel (Placed) pada kolom Action; dialog `placementDialog` interaktif; double-submit guard; update instan tabel & rekap.
- Status: APPROVED. Fase affected: 03d.

### DEC-04 — Hubungan pegawai dan akun login (APPROVED, 2026-10-26 WIB)
- Konteks: Pelaksanaan fondasi schema personalia dan relasi akun Subfase 04a berdasarkan instruksi pemilik 26 Oktober 2026 WIB.
- Pilihan pemilik:
  1. **Profil pegawai independen; akun login opsional**: Profil pegawai adalah entitas personalia sekolah, bukan akun sistem. Pegawai boleh ada tanpa akun `users`. Akun hanya dibuat untuk pegawai yang mengoperasikan sistem.
  2. **`user_id` relasi utama; `user_nik` legacy dipertahankan**: Menambahkan `user_id` (nullable bigint unsigned constrained ke `users.id` dengan `nullOnDelete()`) sebagai relasi utama. `user_nik` dipertahankan dan dilonggarkan menjadi nullable untuk kompatibilitas data lama tanpa merusak integritas.
  3. **Keunikan akun per profil**: Satu akun `users` maksimal terhubung ke tepat satu profil pegawai (`unique('user_id')`, multiple NULL diizinkan). Akun yang terhubung tidak mengubah role/abilities.
  4. **Mata pelajaran (`subject_id`) nullable secara schema**: Wajib untuk Guru/Pendidik; NULL untuk Tenaga Kependidikan (TU, satpam, pustakawan, laboran). Penegakan validasi input endpoint lengkap dikerjakan pada 04b.
  5. **Fondasi versioning**: Menambahkan kolom `version` bertipe integer unsigned default 1 pada tabel `employees`. Kontrak update `expected_version` dan HTTP 409 Conflict diimplementasikan pada 04b.
  6. **Allowlist audit privacy**: Audit trail `activities` memakai allowlist minimum eksplisit; mengecualikan nama ibu kandung, pasangan, nomor KK, NPWP, nilai/IPK ijazah, dan relasi akun/hash/password.
  7. **Standar bahasa**: Kontrak baru dan UI memakai English (`/employees`, dll.); nama kolom existing dipertahankan (tidak diganti massal demi translasi).
- Dampak schema/API/UI:
  - Schema `employees`: additive `user_id` nullable unique, `user_nik` nullable, `subject_id` nullable, `version` unsigned integer default 1.
  - Model `Employee`: relasi `user()` via `user_id` (fallback aman legacy), koreksi method `religion()` dengan alias kompatibilitas `relogion()`, allowlist `$auditableAttributes`.
  - Policy: `EmployeePolicy` disiapkan untuk kemampuan bernama `employees.view` dan `employees.manage`.
  - Scope 04a dibatasi pada fondasi; CRUD/form/UI/endpoint mutasi berada pada 04b.
- Status: APPROVED. Fase affected: 04a, 04b.

### DEC-24 — CRUD pegawai, relasi akun, dan ekspor (APPROVED, 2026-10-29 WIB)
- Konteks: pemilik menyetujui rencana Subfase 04b (`reports/feature-update/PHASE-04B-PLAN.md`) dan seluruh rekomendasi keputusan D1–D8 dengan pernyataan "Saya setuju rekomendasi kamu, lakukan eksekusi." Persetujuan ini mencakup implementasi 04b dalam batas rencana; bukan otorisasi merge/deploy, migration UAT, atau perubahan dependency.
- Pilihan pemilik (pemetaan D1–D8):
  1. **D1 — Nama profil**: Menambah kolom additif `employees.name` (`string(100)`, nullable di schema, wajib di validasi API untuk create/update). Backfill dari `users.name` hanya untuk baris yang dapat diresolusi lewat `user_id`; baris tak teresolusi dibiarkan kosong dan dilaporkan sebagai hitungan agregat tanpa PII. Operator melengkapi manual; tidak ada tebakan nama.
  2. **D2 — NIK pegawai**: Menambah `employees.nik` (`string(16)`, nullable) agar identitas profil tidak bergantung pada akun. Nomor disimpan sebagai string, validasi 16 digit. Keunikan: **unconditional unique** konsisten DEC-21; baris terarsip tetap memegang NIK-nya sehingga restore dapat bentrok dan ditolak `422`.
  3. **D3 — Struktur menu**: Dua route, satu komponen direktori bersama. `/public/teachers` = Pendidik, `/public/employees` = Tenaga Kependidikan; tipe tetap per halaman, tanpa filter tipe lintas halaman.
  4. **D4 — Aturan mapel**: `subject_id` wajib untuk Pendidik dan harus NULL untuk Tenaga Kependidikan; pelanggaran ditolak `422`. Form menampilkan mapel secara kondisional.
  5. **D5 — Taut/lepas akun**: Termasuk 04b, terkendali. Hanya akun `users` aktif yang belum terhubung profil lain dapat ditautkan; tidak membuat akun dan tidak mengubah role/abilities. Lepas akun mengosongkan `user_id` dan mengisi `account_unlinked_at`; fallback `user_nik` tidak aktif kembali. Penautan ulang hanya melalui endpoint eksplisit yang sama, tidak otomatis.
  6. **D6 — Privasi PII**: Daftar tidak pernah memuat PII (NIK, nama ibu kandung, pasangan, KK, NPWP, nilai/IPK ijazah). Detail memuat data operasional untuk pemegang `employees.view`; PII hanya untuk `employees.manage`. NIK termasuk PII: tidak ada di daftar maupun ekspor.
  7. **D7 — Arsip**: Arsip administratif dengan `archive_reason` wajib dan restore berversi (`version` + `409` + `current_version`). `exit_date` tetap bermakna berhenti/pensiun dan tidak diubah otomatis oleh arsip. `forceDelete` tetap ditolak.
  8. **D8 — Ekspor pegawai**: `GET /api/employees/export` dengan izin `employees.view` + `employees.export`, mengikuti filter aktif, kolom eksplisit, seluruh hasil (bukan halaman aktif), batas 10.000 baris ditolak `422` (bukan dipotong). Kolom ekspor hanya data operasional; tanpa PII.
- Default teknis tanpa keputusan baru (mengikuti DEC-04/DEC-21/DEC-22): route plural `/employees` + alias deprecated `/employee`, paginasi 10/20/50/100 default 20, search nama/NUPTK (tanpa NIK/NPWP/KK), sort allowlist, `version` wajib pada mutasi, `422` inline, `404` aman, audit allowlist tanpa PII, UI English, waktu `Asia/Jakarta`.
- Dampak schema/API/UI:
  - Migration baru `2029_07_26_000001`: `name`, `nik`, `archive_reason`, index additive (`name`, `employee_type_id`, `employee_status_id`); tanpa mengedit migration lama; `down()` fail-closed.
  - Command `app:employee-name-backfill [--dry-run]` idempotent dan atomik; output hanya hitungan agregat.
  - `EmployeeDirectory`, `EmployeeResource`, `EmployeeController` (CRUD + arsip/restore + link/unlink), `EmployeeExportController`, `config/employees.php`; request rules aktif; route baru; policy `employees.view/manage` dan pemeriksaan `employees.export`.
  - UI: `EmployeeDirectory.vue` + dialog form/detail/arsip/akun; menu Teachers/Employees aktif; tombol mengikuti izin; 422 inline; 409 Reload Latest.
- Sub-item yang tetap perlu perhatian pemilik (tidak menghambat eksekusi, dicatat agar tidak menjadi keputusan diam-diam):
  - D2: frasa rencana "unik di antara baris aktif" ditafsirkan sebagai **unconditional unique** demi konsistensi DEC-21 dan kebutuhan deteksi bentrok restore. Bila pemilik menghendaki unique hanya untuk baris non-arsip, perubahan harus diminta sebelum migration diterapkan ke database operasional.
  - D5: tidak ada mekanisme penautan ulang otomatis; penautan ulang akun yang pernah dilepas hanya lewat aksi eksplisit `employees.manage`.
  - D8: daftar kolom ekspor final = Name, Employee Type, Employee Status, Employment, Subject, NUPTK, Gender, TMT, Exit Date, Archived At; NIK dan PII lain tidak diekspor.
- Status: APPROVED. Fase affected: 04b.

### Keputusan yang masih OPEN (belum diputuskan)
- Makna `status_user_id` (jangan disamakan dengan `status_active`), deployment CORS/Sanctum produksi, strategi pencabutan token vs sesi.

## Register

| ID | Keputusan | Usulan awal / pertanyaan | Status | Fase paling lambat |
|---|---|---|---|---|
| DEC-01 | Cakupan sekolah | Satu sekolah untuk awal; multi-sekolah di luar scope | PROPOSED | 00 |
| DEC-02 | Lingkungan operasional | Local/LAN atau hosting; lokasi DB/storage; batas biaya | APPROVED (2026-10-17) | 01b/02 |
| DEC-03 | Role dan kemampuan | Admin, Operator, Kepala sekolah, Guru/wali kelas; siapa boleh sign/approve/export | APPROVED (2026-10-15) | 01a |
| DEC-04 | Hubungan pegawai—akun | Profil boleh tanpa login; user_id relasi utama; user_nik legacy nullable; 1 akun = 1 profil; subject_id nullable; versioning; audit privacy | APPROVED (2026-10-26 WIB) | 04a |
| DEC-05 | Identitas dan keanggotaan | NIK boleh kosong; satu rombel aktif per siswa/periode; NIS unik lingkup sekolah | APPROVED (2026-10-20, disahkan DEC-20) | 03a/06a |
| DEC-06 | Format nomor dan persetujuan surat | Format, reset counter, pejabat penandatangan, kapan nomor diterbitkan | OPEN | 07b |
| DEC-07 | Jenis dan approval izin | Siswa/guru, approver, delegasi, perubahan/pembatalan keputusan | OPEN | 08a |
| DEC-08 | Impor data campuran | Preview wajib; default usulan validasi semua sebelum commit per batch kecil; update hanya eksplisit | PROPOSED | 05a |
| DEC-09 | Backup dan retensi | Lokasi, jadwal, RPO/RTO, enkripsi, lama simpan | APPROVED (2026-10-17) | 02b |
| DEC-10 | Jadwal dan jurnal | Jam/sesi, exception libur/cancel/reschedule, batas waktu pengisian | OPEN | 10a |
| DEC-11 | Template dokumen | Kop, tanda tangan, stempel, jenis surat pertama dan format rekap yang benar-benar dipakai | OPEN | 07a/07c |
| DEC-12 | Laporan eksternal | Format ekspor sekolah; Dapodik via rekonsiliasi file dahulu, tanpa API otomatis | PROPOSED | 05b/11b |
| DEC-13 | Retensi data pribadi | Arsip siswa/alumni, audit, lampiran; siapa boleh memusnahkan | OPEN | 09b/13 |
| DEC-14 | Register/akun nonaktif/ekspor siswa/pemetaan legacy/Bahasa UI | Register ditutup; nonaktif ditolak; ekspor via `students.export`; legacy Admin→Admin Sistem (User manual); UI English | APPROVED (2026-10-14 WIB) | 01a |
| DEC-15 | Error auth, CSRF 419 retry, reset password scope 01b | Reset password in scope (mail log); session expired redirect login; 419 single retry | APPROVED (2026-10-16 WIB) | 01b |
| DEC-16 | Isolasi fixture, bootstrap admin, standar password 12 karakter | Bootstrap CLI interactive; synthetic seed dev only; password min 12 char | APPROVED (2026-10-17 WIB) | 01c |
| DEC-17 | Kebijakan audit trail, strategi backup, dan retensi arsip | Rollback nonaktif; backup privat lokal + salinan terpisah; retensi 7+4; enkripsi wajib | APPROVED (2026-10-17) | 02a/02b |
| DEC-18 | Operasional backup manual dan restore drill | Target drill `school_restore_drill_uat`; `backup.manage` default admin-sistem | APPROVED (2026-10-19) | 02b |
| DEC-19 | Penjadwalan, retensi otomatis, dan monitoring backup | Jadwal 02:00 & 02:30 WIB; default schedule OFF; monitoring pasif API/JSON | APPROVED (2026-10-20) | 02c |
| DEC-20 | Identitas siswa, keanggotaan rombel, audit pendataan | NIK opsional 16 digit, NIS unik sekolah, 1 rombel aktif/tahun, soft delete nonaktifkan rombel, audit minimal PII | APPROVED (2026-10-20 WIB) | 03a |
| DEC-21 | Pengarsipan administratif, keunikan NIK/NIS, versioning | Arsip rombel via archived_at/reason terpisah dari mutasi, keunikan NIK/NIS unconditional, version check atomik | APPROVED (2026-10-21 WIB) | 03b |
| DEC-22 | Daftar siswa server-side, placement, rekap, ekspor | All/Placed/Unplaced; paginasi 10/20/50/100 (default 20); rekap siswa unik; ekspor ikut filter + 422 limit; atribut daftar minimum | APPROVED (2026-10-21 WIB) | 03c |
| DEC-23 | Penempatan dan perpindahan rombel siswa | Assign & Transfer rombel; tanpa koreksi/is_correction; tahun ajaran terpilih; tanggal Asia/Jakarta server; preservasi riwayat keluar; students.manage | APPROVED (2026-10-26 WIB) | 03d |
| DEC-24 | CRUD pegawai, relasi akun, ekspor | `employees.name`/`nik`/`archive_reason`; dua route Teachers/Employees; mapel kondisional; link/unlink terkendali; PII detail hanya manage; arsip beralasan + restore berversi; ekspor terfilter 422 limit | APPROVED (2026-10-29 WIB) | 04b |

## Format pencatatan keputusan
Tanggal WIB; ID; konteks; alternatif; pilihan pemilik dan bukti instruksi; dampak schema/API/UI; fase affected; status OPEN/PROPOSED/APPROVED/SUPERSEDED. Pertahankan riwayat bila keputusan berubah.
Jangan membebani pemilik dengan seluruh pertanyaan sekaligus. Fase 00 dapat audit tanpa jawaban; tanyakan hanya keputusan yang menghambat subfase berikutnya. Pekerjaan independen yang telah diotorisasi boleh terus berjalan.
