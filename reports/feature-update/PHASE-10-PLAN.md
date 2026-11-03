# Fase 10 (Subfase 10a) — Rencana Penugasan dan Jadwal Pembelajaran

- **Tanggal:** 2026-11-03 (WIB)
- **Cabang:** `main`
- **Baseline audit:** `f185ca6` (pencatatan penerimaan pemilik Subfase 09b `#1–#18` di atas implementasi `51002e6`); paket rencana: `a8264ad`
- **Status:** **PLAN APPROVED — DEC-39 APPROVED (2026-11-03 WIB, berdasarkan pernyataan pemilik `"Saya setuju D1-D11."`); implementasi Subfase 10a diotorisasi; `DEC-10` CLOSED melalui D3, D5, dan D8**
- **Keputusan:** **DEC-39 (APPROVED, 2026-11-03 WIB)** — Arsitektur Subfase 10a Penugasan Mengajar, Jadwal Mingguan + Deteksi Bentrok, Exception Kalender (`holiday`/`cancelled`/`rescheduled`), dan penyelesaian gerbang **`DEC-10`**
- **Cakupan langkah ini:** Persetujuan rencana D1–D11 dicatat terlebih dahulu; implementasi S1–S8 dieksekusi setelah commit persetujuan ini.

---

## 1. Posisi dan Prasyarat

| Aspek | Status fakta |
|---|---|
| Fase 00–09b | **COMPLETE & ACCEPTED** (terakhir Subfase 09b diterima pemilik `#1–#18` pada `f185ca6` di atas implementasi `51002e6`; server UAT `.uat-phase09` `18027`/`14027` sudah dihentikan sesuai `DEC-27`). |
| Fase 10 di roadmap | [`roadmap/phases/PHASE-10-TEACHING.md`](../../roadmap/phases/PHASE-10-TEACHING.md): **10a** Penugasan dan jadwal, **10b** Sesi dan jurnal, **10c** Laporan belum mengisi jurnal. |
| Kebutuhan fungsional | [`docs/REQUIREMENTS.md`](../../docs/REQUIREMENTS.md) **FR-12**: *"Penugasan mengajar, jadwal + deteksi bentrok + exception libur/reschedule, sesi dan jurnal pembelajaran, laporan belum mengisi jurnal."* |
| Gerbang keputusan | **`DEC-10`** (*"Jadwal dan jurnal: Jam/sesi, exception libur/cancel/reschedule, batas waktu pengisian"*, batas fase `10a`) saat ini berstatus **OPEN** di [`docs/DECISIONS.md`](../../docs/DECISIONS.md) dan diselesaikan melalui usulan **`DEC-39` (`D1–D11`)** pada dokumen ini. |
| Dependensi domain | Semua fondasi master akademik dan SDM sudah aktif: `school_years` (termasuk `locked_at` dari 06b), `semesters` (`I` Ganjil, `II` Genap), `academic_settings` (singleton periode aktif dari 06a), `school_levels`, `student_majors`, `school_rombels` (kelas per tahun ajaran dari 06b), `subjects` (50+ mata pelajaran dari `SubjectSeeder`), dan `employees` (`Pendidik` vs `Tenaga Kependidikan` dari 04b). |

---

## 2. Temuan Audit Fakta Kode (`f185ca6`)

Audit langsung pada `laravel-api/` dan `vue-app/` menunjukkan kondisi aktual berikut:

1. **Domain Penugasan, Jadwal, Sesi, dan Jurnal Masih Greenfield:**
   - Belum ada tabel, migration, model, service, FormRequest, controller, route `/api/teaching/*`, ataupun halaman Vue `/public/teaching/*` untuk penugasan mengajar, jadwal mingguan, exception kalender, sesi pembelajaran, maupun jurnal mengajar.
   - [`docs/DATABASE.md`](../../docs/DATABASE.md) mencatat: *"Fase 07–12 (Izin/Cuti, Surat, Arsip, Jadwal & Jurnal Mengajar, Pengumuman, Buku Tamu) belum memiliki tabel pada skema saat ini"* (catatan historis sebelum 07–09; khusus Jadwal & Jurnal Mengajar memang belum memiliki tabel).

2. **Ability RBAC Sudah Terdaftar di `AbilityCatalog`, namun Pemetaan Peran Perlu Penyelarasan:**
   - Di [`laravel-api/app/Support/AbilityCatalog.php`](../../laravel-api/app/Support/AbilityCatalog.php) (baris 72–73), dua ability sudah didefinisikan sejak Fase 03:
     - `teaching.view` — *"Lihat jadwal dan jurnal mengajar"* (`group: teaching`)
     - `teaching.manage` — *"Kelola jadwal dan jurnal mengajar"* (`group: teaching`)
   - Namun pada `AbilityCatalog::initialRoles()`:
     - `admin-sistem`: memiliki `teaching.view` dan `teaching.manage`.
     - `wakil-kesiswaan`: memiliki `teaching.view` dan `teaching.manage`.
     - `operator-sekolah`: **hanya** memiliki `teaching.view` (belum memiliki `teaching.manage`, padahal di `PHASE-10-TEACHING.md` penyusunan jadwal dan penugasan dilakukan oleh admin/operator akademik).
     - `kepala-sekolah`: memiliki `teaching.view` (read-only pengawasan).
     - `guru`: saat ini baru memiliki `leaves.request` (belum memiliki `teaching.view`, padahal guru perlu melihat penugasan & jadwal mengajarnya di 10a serta mengisi jurnal pada penugasannya sendiri di 10b).

3. **Relasi Pegawai (`Employee`) dan Mata Pelajaran (`Subject`):**
   - [`Employee.php`](../../laravel-api/app/Models/Employee.php) memiliki `scopeEligibleHomeroomTeacher()` yang memfilter pegawai aktif (`deleted_at IS NULL`, `exit_date IS NULL OR exit_date >= today (Asia/Jakarta)`) dengan tipe `Pendidik`. Scope yang sama ideal dijadikan dasar kelayakan guru pengampu (`scopeEligibleTeacher`).
   - Kolom `employees.subject_id` menyimpan **satu** mata pelajaran utama profil guru (wajib bagi `Pendidik` sejak Fase 04b). Dalam operasional sekolah dan spesifikasi 10a (*"Relasi banyak guru-matpel-rombel-periode; Guru dapat beberapa penugasan"*), seorang guru dapat mengajar di banyak rombel dan terkadang mengajar mata pelajaran serumpun selain `subject_id` utamanya.
   - [`Subject.php`](../../laravel-api/app/Models/Subject.php) (`subjects`: `id`, `name`, `description`, `deleted_at`) sudah memiliki `SubjectSeeder` (50+ mapel), tetapi [`SubjectController.php`](../../laravel-api/app/Http/Controllers/SubjectController.php) baru mengimplementasikan `index()` (`GET /api/subjects` di bawah `academics.view`), sedangkan `store/show/update/destroy` masih kosong. Endpoint opsi `/api/teaching/options` di 10a perlu menyuplai daftar mapel aktif, tahun ajaran, semester, rombel, dan guru layak tanpa memaksa user berpindah modul.

4. **Integritas Tahun Ajaran (`SchoolYear`), Semester (`Semester`), dan Rombel (`SchoolRombel`):**
   - [`SchoolYear.php`](../../laravel-api/app/Models/SchoolYear.php) memiliki `start_date`, `end_date`, dan `locked_at`. Sesuai pola 06b/06c, tahun ajaran yang sudah `locked` (`locked_at !== null`) wajib menolak pembuatan, pengubahan, dan penghapusan penugasan mengajar, slot jadwal, maupun exception kalender (`422 Unprocessable Entity`).
   - [`SchoolYearController::destroy()`](../../laravel-api/app/Http/Controllers/SchoolYearController.php) dan [`SchoolRombelController::destroy()`](../../laravel-api/app/Http/Controllers/SchoolRombelController.php) saat ini memeriksa keterkaitan `SchoolRombel` dan `StudentRombel`, tetapi nanti di 10a **wajib** juga menolak penghapusan (`422`) apabila tahun ajaran atau rombel sudah memiliki `teaching_assignments` atau `teaching_schedule_exceptions`.
   - [`Semester.php`](../../laravel-api/app/Models/Semester.php) adalah referensi global (`I` — Ganjil, `II` — Genap) tanpa tanggal mulai/selesai per semester, sedangkan [`AcademicSetting.php`](../../laravel-api/app/Models/AcademicSetting.php) menyimpan `active_school_year_id` dan `active_semester_id`. Karena itu, setiap penugasan mengajar wajib mengikat pasangan `(school_year_id, semester_id)`.

5. **Gerbang Keputusan `DEC-10` Masih `OPEN`:**
   - [`docs/DECISIONS.md`](../../docs/DECISIONS.md) mencantumkan `DEC-10`: *"Jadwal dan jurnal: Jam/sesi, exception libur/cancel/reschedule, batas waktu pengisian"* dengan batas fase `10a`. Seluruh parameter teknis `DEC-10` dirumuskan secara eksplisit pada keputusan **D3**, **D5**, dan **D8** di bawah.

---

## 3. Usulan Keputusan Pemilik (`D1–D11` / `DEC-39 PROPOSED`)

Setiap butir memiliki rekomendasi tegas agar pemilik cukup menyetujui seluruh paket atau menunjuk nomor `D#` yang ingin disesuaikan.

### D1 — Pembagian Subfase Fase 10 (`10a` vs `10b` & `10c`)
- **Konteks:** `PHASE-10-TEACHING.md` membagi Fase 10 menjadi tiga subfase berurutan: **10a** (Penugasan dan jadwal + deteksi bentrok + exception libur/cancel/reschedule), **10b** (Sesi dan pengisian jurnal pembelajaran), dan **10c** (Laporan belum mengisi jurnal).
- **Rekomendasi:** Eksekusi **Subfase 10a** terlebih dahulu secara utuh dan terverifikasi. Subfase **10b** dan **10c** akan direncanakan dan dieksekusi pada tahap berikutnya setelah 10a selesai dan diterima pemilik, namun prinsip **`DEC-10`** (termasuk aturan dasar sesi dan batas waktu pengisian jurnal) dikunci sekarang di **D3, D5, dan D8** agar skema tabel 10a 100% siap dipakai oleh 10b/10c tanpa bongkar ulang.
- **Alternatif:** Menggabungkan 10a + 10b sekaligus dalam satu iterasi besar.
- **Dampak:** Menjaga ukuran perubahan tetap fokus, mudah diuji di browser UAT, dan selaras dengan ritme subfase 06a/06b/06c serta 09a/09b.

### D2 — Skema Penugasan Mengajar (`teaching_assignments`) & Aturan Kelayakan Guru/Mapel
- **Konteks:** Sekolah perlu memetakan guru mana mengajar mata pelajaran apa di kelas (`school_rombel`) mana pada periode `(school_year_id, semester_id)`.
- **Rekomendasi:**
  1. Tabel **`teaching_assignments`** dengan kolom: `id`, `school_year_id` (FK `restrictOnDelete`), `semester_id` (FK `restrictOnDelete`), `school_rombel_id` (FK `restrictOnDelete`), `subject_id` (FK `restrictOnDelete`), `employee_id` (FK `restrictOnDelete`), `hours_per_week` (`unsignedTinyInteger` opsional, `1..24`), `status` (`active` | `inactive`, default `active`), `notes` (string opsional `<= 255`), `version` (`unsignedInteger` default `1`), `created_by`, `updated_by`, `timestamps`.
  2. **Validasi lintas entitas:**
     - `school_rombel.school_year_id` **wajib sama** dengan `school_year_id` penugasan (`422` jika berbeda).
     - Saat membuat penugasan baru atau mengganti guru pengampu, `employee_id` wajib memenuhi kriteria **Pendidik aktif** (`deleted_at IS NULL`, tipe pegawai `Pendidik`, dan `exit_date IS NULL OR exit_date >= today WIB`).
     - Guru **boleh** ditugaskan mengajar mata pelajaran aktif apa pun di tabel `subjects` (tidak dibatasi hanya pada `employees.subject_id` utamanya, karena di lapangan guru sering merangkap mapel serumpun/muatan lokal); di UI, `employees.subject_id` ditampilkan sebagai badge/saran default saat memilih guru.
  3. **Keunikan & Team-Teaching:** Kombinasi `(school_year_id, semester_id, school_rombel_id, subject_id, employee_id)` bersifat **unik** (`422` jika duplikat). Satu `(rombel, mapel, semester)` tetap memungkinkan lebih dari satu penugasan guru berbeda (mis. team-teaching atau pergantian guru tengah semester di mana penugasan lama diset `inactive` dan penugasan baru `active`).
- **Alternatif:** Membatasi 1 guru tunggal per `(rombel, mapel, semester)` atau membatasi mapel hanya sesuai `employees.subject_id`.
- **Dampak:** Fleksibel terhadap pergantian guru di tengah semester tanpa merusak riwayat jadwal/jurnal lama, sekaligus menjaga integritas tahun ajaran rombel.

### D3 — Struktur Slot Jadwal Mingguan (`teaching_schedules` — Penyelesaian `DEC-10` Bagian Jam/Sesi)
- **Konteks:** `DEC-10` menanyakan representasi jam/sesi pembelajaran mingguan dan ruang opsional.
- **Rekomendasi:**
  1. Tabel **`teaching_schedules`** berelasi ke `teaching_assignment_id` (FK `cascadeOnDelete` jika penugasan dihapus sebelum punya sesi/jurnal, atau `restrictOnDelete`), dengan kolom:
     - `day_of_week`: `unsignedTinyInteger` `1..6` (`1 = Monday` s.d. `6 = Saturday`; daftar hari aktif dikonfigurasi di `config/teaching.php`).
     - `period_start` dan `period_end`: `unsignedTinyInteger` (`1..12`, dengan `period_end >= period_start`), merepresentasikan "Jam Pelajaran ke-`N`" (mis. Jam ke-1 s.d. Jam ke-2) yang lazim dipakai sekolah di Indonesia.
     - `start_time` dan `end_time`: waktu eksplisit format `HH:MM` 24-jam (disimpan sebagai `char(5)` terstandar `07:00`..`17:30`, wajib `end_time > start_time`), dengan preset jam pelajaran standar di `config/teaching.php` (mis. Jam 1 = `07:00–07:45`, Jam 2 = `07:45–08:30`, dst.) yang otomatis mengisi `start_time`/`end_time` di UI namun tetap dapat disesuaikan per slot (mis. hari Jumat durasi 35 menit).
     - `room`: string opsional `<= 60` karakter (mis. `R-101`, `LAB-KOMP-1`), disimpan beserta `room_normalized` (`UPPER(TRIM(room))`, nullable) untuk deteksi bentrok ruang yang tahan perbedaan huruf besar/kecil.
     - `status`: `active` | `inactive` (default `active`; jadwal otomatis dianggap tidak aktif untuk bentrok bila `status = inactive` atau `teaching_assignment.status = inactive`).
     - `notes`: string opsional `<= 255`, `version` (`unsignedInteger` default `1`), `created_by`, `updated_by`, `timestamps`.
- **Alternatif:** Hanya memakai nomor jam (`period_start/end`) tanpa waktu `HH:MM`, atau hanya `HH:MM` tanpa nomor jam pelajaran.
- **Dampak:** Menggabungkan nomor jam pelajaran (`period_start–period_end`) dan rentang pukul (`start_time–end_time`) memberikan tampilan matriks jadwal sekolah yang familier sekaligus presisi matematis untuk deteksi bentrok lintas jenjang/kegiatan.

### D4 — Aturan Deteksi Bentrok (Collision Detection) & Concurrency (`409` / `422`)
- **Konteks:** FR-12 dan `PHASE-10-TEACHING.md` mewajibkan jadwal menolak bentrok guru, kelas/rombel, dan ruang (jika dipakai), serta menolak duplikasi akibat submit ganda.
- **Rekomendasi:**
  1. Dua slot jadwal aktif pada `(school_year_id, semester_id, day_of_week)` yang sama dinyatakan **beririsan waktu (overlap)** apabila:
     `new.start_time < existing.end_time AND new.end_time > existing.start_time` (atau irisan `period_start <= existing.period_end AND period_end >= existing.period_start`).
  2. Setiap pembuatan/pembaruan/pengaktifan `teaching_schedules` (di dalam transaksi database dengan `SchoolYear::whereKey($schoolYearId)->lockForUpdate()`) wajib menolak (`422 Unprocessable Entity` dengan pesan spesifik dan detail slot yang bentrok) apabila terdapat slot aktif lain yang beririsan waktu pada:
     - **Bentrok Guru (`employee_id` sama):** Guru yang sama tidak boleh mengajar di dua jadwal yang beririsan waktu pada hari & periode yang sama.
     - **Bentrok Kelas (`school_rombel_id` sama):** Rombel yang sama tidak boleh memiliki dua jadwal aktif yang beririsan waktu pada hari & periode yang sama.
     - **Bentrok Ruang (`room_normalized` sama, bila keduanya tidak null):** Ruang fisik/lab yang sama tidak boleh dipakai oleh dua jadwal aktif yang beririsan waktu pada hari & periode yang sama.
  3. **Pengaktifan kembali (`status -> active`):** Mengubah `teaching_assignment.status` atau `teaching_schedule.status` dari `inactive` kembali ke `active` wajib menjalankan ulang pemeriksaan bentrok terhadap seluruh jadwal aktifnya.
  4. **Optimistic Locking & Tahun Ajaran Terkunci:** Update penugasan, jadwal, dan exception wajib menyertakan `expected_version` (`409 Conflict` + tombol `Reload Latest` bila versi basi). Seluruh mutasi pada tahun ajaran yang `locked_at !== null` ditolak dengan `422`.
- **Alternatif:** Hanya memeriksa bentrok berdasarkan nomor jam pelajaran tanpa memeriksa irisan menit `start_time/end_time`.
- **Dampak:** Menjamin jadwal mingguan bebas konflik fisik maupun SDM, aman dari *race condition* submit ganda.

### D5 — Exception Kalender: Libur, Pembatalan, dan Reschedule (`teaching_schedule_exceptions` — Penyelesaian `DEC-10` Bagian Exception)
- **Konteks:** `DEC-10` dan `PHASE-10-TEACHING.md` mewajibkan: *"Exception libur/pembatalan/reschedule tercatat; libur/cancel tidak menghasilkan tugas jurnal palsu."*
- **Rekomendasi:**
  1. Tabel **`teaching_schedule_exceptions`** dengan kolom:
     - `id`, `school_year_id` (FK `restrictOnDelete`), `semester_id` (FK `restrictOnDelete`)
     - `exception_type`: `holiday` | `cancelled` | `rescheduled`
     - `exception_date`: `date` (`Y-m-d`), wajib berada dalam rentang `school_year.start_date .. school_year.end_date`.
     - `school_rombel_id`: nullable FK (`null` pada `holiday` berarti **libur satu sekolah** pada tanggal tersebut; terisi pada `holiday` berarti libur khusus kelas/rombel tersebut, mis. kegiatan studi lapangan kelas).
     - `teaching_schedule_id`: nullable FK ke `teaching_schedules` (wajib `null` untuk `holiday`; **wajib terisi** untuk `cancelled` dan `rescheduled`, dan hari `exception_date` wajib cocok dengan `teaching_schedule.day_of_week`).
     - Khusus `rescheduled` (wajib diisi bila `exception_type = rescheduled`, wajib `null` untuk tipe lain):
       - `rescheduled_date`: `date` (`Y-m-d`) dalam rentang tahun ajaran (boleh tanggal sama beda jam atau tanggal berbeda; tidak boleh jatuh pada tanggal yang berstatus `holiday` untuk sekolah/rombel tersebut).
       - `rescheduled_period_start`, `rescheduled_period_end`: `unsignedTinyInteger` (`1..12`).
       - `rescheduled_start_time`, `rescheduled_end_time`: `char(5)` (`HH:MM`, `end > start`).
       - `rescheduled_room` & `rescheduled_room_normalized`: string opsional `<= 60`.
       - `substitute_employee_id`: nullable FK ke `employees` (opsional bila jadwal pengganti diisi guru piket/pengganti `Pendidik` aktif; default `null` berarti tetap guru pengampu asli).
     - `reason`: string wajib `10..255` karakter.
     - `version` (`unsignedInteger` default `1`), `created_by`, `updated_by`, `timestamps`.
  2. **Deteksi Bentrok pada `rescheduled`:** Saat menyimpan exception `rescheduled`, sistem memeriksa pada `rescheduled_date` apakah jam pengganti berbenturan dengan:
     - Jadwal reguler aktif pada hari `DAYOFWEEK(rescheduled_date)` (yang **tidak** sedang `cancelled`/`rescheduled`/`holiday` pada tanggal itu), **atau**
     - Exception `rescheduled` lain yang menuju `rescheduled_date` yang sama,
     untuk guru efektif (`substitute_employee_id ?? assignment.employee_id`), rombel (`assignment.school_rombel_id`), dan ruang (`rescheduled_room_normalized`). Jika bentrok, tolak dengan `422`.
  3. **Keunikan Exception:**
     - Untuk `cancelled` / `rescheduled`: unik per `(teaching_schedule_id, exception_date)` (`422` bila slot jadwal pada tanggal tersebut sudah punya exception).
     - Untuk `holiday`: unik per `(school_year_id, semester_id, exception_date, school_rombel_id)`.
- **Alternatif:** Tidak memvalidasi bentrok pada jadwal hasil `rescheduled`.
- **Dampak:** Memberikan kalender operasional yang akurat untuk Subfase 10b (pembuatan sesi/jurnal pada slot reguler maupun tanggal reschedule) dan Subfase 10c (kalkulasi kewajiban jurnal yang otomatis mengecualikan `holiday` dan `cancelled` serta memperhitungkan `rescheduled`).

### D6 — Hak Akses RBAC & Penyelarasan Peran Default (`teaching.view`, `teaching.manage`)
- **Konteks:** Temuan audit #2 menunjukkan `operator-sekolah` baru memiliki `teaching.view` (belum `teaching.manage`), dan `guru` belum memiliki `teaching.view`.
- **Rekomendasi:**
  1. Gunakan ability yang sudah ada di `AbilityCatalog`:
     - **`teaching.view`**: melihat daftar penugasan, matriks jadwal mingguan (per kelas, per guru, per ruang), dan daftar exception kalender.
     - **`teaching.manage`**: membuat, mengubah, menonaktifkan/menghapus penugasan mengajar, slot jadwal mingguan, dan exception kalender.
  2. Perbarui `AbilityCatalog::initialRoles()` dan tambahkan migration data idempoten/additif (`2029_10_01_000004_sync_teaching_abilities_to_default_roles.php`) yang:
     - Menambahkan **`teaching.manage`** ke peran **`operator-sekolah`** (sehingga `admin-sistem`, `wakil-kesiswaan`, dan `operator-sekolah` dapat mengelola penugasan & jadwal).
     - Menambahkan **`teaching.view`** ke peran **`guru`** (sehingga akun guru dapat melihat penugasan & jadwal mengajar di 10a; pada 10b nanti guru dengan `teaching.view` akan diizinkan mengisi jurnal khusus untuk penugasannya sendiri, sedangkan koreksi lintas guru memerlukan `teaching.manage`).
     - Peran **`kepala-sekolah`** tetap memiliki **`teaching.view`** (read-only).
- **Alternatif:** Membuat ability baru terpisah untuk 10a.
- **Dampak:** Tetap mematuhi katalog ability kanonik Fase 03 tanpa ability liar, sekaligus memastikan Operator Sekolah dan Guru dapat menjalankan alur 10a sesuai matriks peran di `PHASE-10-TEACHING.md`.

### D7 — Guard Penghapusan Data Master & Siklus Hidup Penugasan/Jadwal
- **Konteks:** Penghapusan tahun ajaran, kelas (`school_rombel`), mata pelajaran, atau penugasan yang sudah dipakai dapat merusak integritas jadwal dan nantinya jurnal mengajar.
- **Rekomendasi:**
  1. Perbarui `SchoolYearController::destroy()` dan `SchoolRombelController::destroy()` agar menolak penghapusan (`422`) apabila sudah terdapat baris di `teaching_assignments` atau `teaching_schedule_exceptions` yang mereferensikan tahun ajaran/rombel tersebut.
  2. Pada Subfase 10a, `DELETE /api/teaching/assignments/{id}` diizinkan menghapus penugasan **hanya jika** belum memiliki jadwal (`teaching_schedules`) atau jika seluruh jadwalnya belum memiliki exception/sesi; bila sudah memiliki exception (atau sesi di 10b), tolak `DELETE` dengan `422` dan arahkan pengguna untuk mengubah `status` menjadi `inactive`.
  3. Demikian pula, `DELETE /api/teaching/schedules/{id}` menolak penghapusan (`422`) apabila jadwal tersebut sudah direferensikan oleh `teaching_schedule_exceptions` (arahkan ke `status = inactive` atau hapus exception terlebih dahulu).
- **Alternatif:** Soft delete pada seluruh tabel jadwal.
- **Dampak:** Konsisten dengan pola `status = active/inactive` + guard `422` yang mudah dipahami pengguna dan menjaga kebersihan constraint database.

### D8 — Kebijakan `DEC-10` untuk Sesi & Batas Waktu Pengisian Jurnal (Dikunci untuk 10b/10c)
- **Konteks:** Agar gerbang `DEC-10` tuntas pada Fase 10a tanpa menyisakan ambiguitas desain bagi 10b dan 10c, aturan hubungan jadwal → sesi → batas waktu jurnal perlu disepakati sekarang.
- **Rekomendasi:**
  1. **Definisi Sesi Pembelajaran Efektif (untuk 10b/10c):** Pada tanggal kalender `D` di dalam rentang tahun ajaran:
     - Slot jadwal reguler aktif yang jatuh pada `DAYOFWEEK(D)` menghasilkan **1 kewajiban sesi/jurnal**, **kecuali** tanggal `D` memiliki exception `holiday` (sekolah atau rombel terkait) atau slot tersebut memiliki exception `cancelled` / `rescheduled` pada tanggal `D`.
     - Setiap exception `rescheduled` yang memiliki `rescheduled_date = D` menghasilkan **1 kewajiban sesi/jurnal** pada tanggal `D` dengan waktu, ruang, dan guru efektif sesuai data reschedule.
  2. **Batas Waktu Pengisian Jurnal oleh Guru (untuk 10b):**
     - Guru pengampu hanya dapat mengisi/memperbarui jurnal pada tanggal sesi **`D <= today (Asia/Jakarta)`** (tidak boleh mengisi jurnal untuk tanggal di masa depan) dan paling lambat **`D + journal_grace_days`** (dikonfigurasi di `config/teaching.php`, default **7 hari kalender** termasuk hari H) selama tahun ajaran belum `locked`.
     - Lewat dari batas `journal_grace_days`, pengisian atau koreksi jurnal hanya dapat dilakukan oleh peran yang memiliki **`teaching.manage`** (dengan alasan koreksi wajib dicatat di audit log), dan tetap wajib ditolak (`422`) apabila tahun ajaran sudah `locked`.
- **Alternatif:** Tanpa batas hari pengisian bagi guru selama tahun ajaran belum dikunci, atau batas ketat hanya pada hari H (`0` hari).
- **Dampak:** Menutup seluruh pertanyaan terbuka di `DEC-10` secara proporsional; nilai `journal_grace_days = 7` disimpan di `config/teaching.php` sejak 10a sehingga siap langsung dipakai saat 10b dibangun.

### D9 — Kontrak API & Antarmuka Pengguna (UI) Subfase 10a
- **Konteks:** Pengguna membutuhkan antarmuka terpadu untuk mengelola penugasan guru, menyusun jadwal mingguan dengan umpan balik bentrok yang jelas, melihat matriks jadwal per kelas/guru, dan mencatat exception kalender.
- **Rekomendasi:**
  1. **Endpoint API (`/api/teaching/*`):**
     - `GET /api/teaching/options` (`teaching.view`): daftar `school_years`, `semesters`, `active_school_year_id`, `active_semester_id`, `rombels` (per tahun ajaran), `subjects`, `teachers` (`Pendidik` aktif + `subject_id` utama), `days_of_week` (`1..6`), dan `period_presets` (`1..12` beserta `start_time`/`end_time` default).
     - `GET /api/teaching/assignments` (`teaching.view`): daftar terpaginasi + filter `school_year_id`, `semester_id`, `school_rombel_id`, `subject_id`, `employee_id`, `status`, `search`, beserta jumlah slot jadwal & total jam.
     - `POST /api/teaching/assignments` (`teaching.manage`): tambah penugasan mengajar.
     - `PUT /api/teaching/assignments/{teachingAssignment}` (`teaching.manage`): perbarui penugasan (`expected_version`).
     - `DELETE /api/teaching/assignments/{teachingAssignment}` (`teaching.manage`): hapus penugasan yang belum terpakai.
     - `GET /api/teaching/schedules` (`teaching.view`): daftar slot jadwal + mode matriks mingguan (`view_mode = list | matrix`), filter `school_year_id`, `semester_id`, `school_rombel_id`, `employee_id`, `day_of_week`, `room`, `status`.
     - `POST /api/teaching/schedules` (`teaching.manage`): tambah slot jadwal mingguan (dengan deteksi bentrok guru/rombel/ruang).
     - `PUT /api/teaching/schedules/{teachingSchedule}` (`teaching.manage`): perbarui slot jadwal (`expected_version` + deteksi bentrok).
     - `DELETE /api/teaching/schedules/{teachingSchedule}` (`teaching.manage`): hapus slot jadwal yang belum memiliki exception.
     - `GET /api/teaching/exceptions` (`teaching.view`): daftar exception kalender terpaginasi + filter `school_year_id`, `semester_id`, `exception_type`, `school_rombel_id`, `employee_id`, `date_from`, `date_to`, `search`.
     - `POST /api/teaching/exceptions` (`teaching.manage`): tambah exception (`holiday` / `cancelled` / `rescheduled` + deteksi bentrok pada tanggal target reschedule).
     - `PUT /api/teaching/exceptions/{teachingScheduleException}` (`teaching.manage`): perbarui exception (`expected_version` + deteksi bentrok).
     - `DELETE /api/teaching/exceptions/{teachingScheduleException}` (`teaching.manage`): hapus exception.
  2. **Halaman Vue & Navigasi:**
     - Grup menu sidebar baru **Teaching** di `AppMenu.vue` dengan item **Assignments & Timetable** (`/teaching/schedules`, meta `ability: 'teaching.view'`).
     - Satu halaman komprehensif `vue-app/src/views/teaching/TeachingSchedulesView.vue` dengan **filter konteks periode (`School Year` & `Semester`)** di bagian atas (default otomatis ke periode aktif `AcademicSetting`, lengkap dengan badge **`Locked School Year`** bila `locked_at !== null`) dan **3 Tab**:
       1. **Weekly Timetable (Matriks & Daftar Jadwal):** Tampilan matriks Senin–Sabtu (atau daftar tabel) dengan filter per Kelas (`Rombel`) atau per Guru (`Teacher`), tombol **New Schedule Slot** (`teaching.manage`), preset jam pelajaran otomatis, dan alert terstruktur bila terjadi bentrok (`422` menampilkan detail jadwal yang bertabrakan: guru/kelas/ruang & jam).
       2. **Teaching Assignments (Penugasan Mengajar):** Tabel penugasan guru–mapel–kelas–periode, ringkasan jam/minggu vs jumlah slot terjadwal, dialog **New / Edit Assignment** (`teaching.manage`).
       3. **Schedule Exceptions (Libur, Batal, Reschedule):** Tabel & filter exception kalender (`Holiday`, `Cancelled`, `Rescheduled`), dialog **New / Edit Exception** (`teaching.manage`) yang menyesuaikan field secara dinamis berdasarkan tipe exception.
     - Seluruh label, tombol, badge, empty state, dan dialog dalam **Bahasa Inggris**, responsif pada lebar **390 px** tanpa *horizontal page overflow*, dan mendukung banner **`409 Conflict` + tombol `Reload Latest`**.
- **Alternatif:** Memecah menjadi tiga halaman rute terpisah di sidebar.
- **Dampak:** Memudahkan operator/wakil kurikulum/guru berpindah antara penugasan, matriks jadwal mingguan, dan exception kalender dalam satu konteks tahun ajaran & semester yang sama.

### D10 — Audit Log & Privasi (`Loggable`)
- **Konteks:** Setiap perubahan penugasan, jadwal, dan exception harus terekam di `application_logs` tanpa membocorkan PII pegawai atau teks bebas panjang.
- **Rekomendasi:**
  - Model `TeachingAssignment`, `TeachingSchedule`, dan `TeachingScheduleException` memakai trait `Loggable` dengan `loggableAttributes` berbasis allowlist:
    - `TeachingAssignment`: `['school_year_id', 'semester_id', 'school_rombel_id', 'subject_id', 'employee_id', 'hours_per_week', 'status', 'version']` (tanpa `notes`).
    - `TeachingSchedule`: `['teaching_assignment_id', 'day_of_week', 'period_start', 'period_end', 'start_time', 'end_time', 'room', 'status', 'version']` (tanpa `notes`).
    - `TeachingScheduleException`: `['school_year_id', 'semester_id', 'exception_type', 'exception_date', 'school_rombel_id', 'teaching_schedule_id', 'rescheduled_date', 'rescheduled_period_start', 'rescheduled_period_end', 'rescheduled_start_time', 'rescheduled_end_time', 'rescheduled_room', 'substitute_employee_id', 'version']` (tanpa `reason`).
- **Alternatif:** Mencatat `notes` dan `reason` ke dalam payload log.
- **Dampak:** Konsisten dengan kebijakan privasi audit log Fase 04–09b.

### D11 — Lingkungan UAT Terisolasi `.uat-phase10` & Dataset Fixture
- **Konteks:** Setiap fase memiliki launcher UAT mandiri yang terisolasi dari `.env` lokal dan dihentikan setelah penerimaan (`DEC-27`).
- **Rekomendasi:**
  - Buat skrip `scripts/phase10-uat.php` dengan direktori runtime `.uat-phase10/`, database SQLite `.uat-phase10/database.sqlite`, API `127.0.0.1:18028`, Vite SPA `127.0.0.1:14028`, dan cookie sesi `school_phase10_session`.
  - Subperintah `prepare-teaching` men-seed secara idempoten:
    - Minimal **100+ baris jadwal/penugasan sintetis** lintas beberapa kelas (`X-A`, `X-B`, `XI-IPA-1`, dll.) dan guru `Pendidik` pada tahun ajaran aktif serta 1 tahun ajaran terkunci (`locked`) untuk pengujian read-only/rejection `422`.
    - Sampel siap-uji untuk: (a) slot jadwal yang siap diuji bentrok guru, bentrok kelas, dan bentrok ruang (`LAB-KOMP-1`), serta (b) sampel exception `holiday`, `cancelled`, dan `rescheduled`.
    - Akun uji standar: `admin.phase10@example.test` (`admin-sistem`), `operator.phase10@example.test` (`operator-sekolah`), `vice.phase10@example.test` (`wakil-kesiswaan`), `principal.phase10@example.test` (`kepala-sekolah`), `teacher.phase10@example.test` (`guru`), dan `staff.phase10@example.test` (`staf-tata-usaha` tanpa akses `teaching.view`).
- **Alternatif:** Memakai ulang `.uat-phase09`.
- **Dampak:** Isolasi penuh untuk Fase 10 (10a, 10b, 10c) tanpa mengganggu artefak fase sebelumnya.

---

## 4. Rincian Teknis Subfase 10a

### 4.1. Rancangan Migration & Konfigurasi
1. `laravel-api/config/teaching.php`:
   - Daftar hari aktif (`1 => 'Monday'` s.d. `6 => 'Saturday'`).
   - Preset jam pelajaran `1..12` (beserta `start_time` dan `end_time` default).
   - `journal_grace_days => 7` (konstanta `DEC-10` untuk 10b/10c).
2. `2029_10_01_000001_create_teaching_assignments_table.php`:
   - Membuat tabel `teaching_assignments` dengan indeks unik `teaching_assignments_unique_period_rombel_subject_teacher` pada `(school_year_id, semester_id, school_rombel_id, subject_id, employee_id)` dan indeks pencarian pada `(school_year_id, semester_id, status)` serta `(employee_id, status)`.
3. `2029_10_01_000002_create_teaching_schedules_table.php`:
   - Membuat tabel `teaching_schedules` dengan indeks komposit `(day_of_week, status, start_time, end_time)` dan `(room_normalized, day_of_week, status)`.
4. `2029_10_01_000003_create_teaching_schedule_exceptions_table.php`:
   - Membuat tabel `teaching_schedule_exceptions` dengan indeks `(school_year_id, semester_id, exception_date, exception_type)` dan `(rescheduled_date, exception_type)`.
5. `2029_10_01_000004_sync_teaching_abilities_to_default_roles.php`:
   - Memastikan role `operator-sekolah` memiliki `teaching.manage` (selain `teaching.view`) dan role `guru` memiliki `teaching.view` secara idempoten.

### 4.2. Urutan Langkah Implementasi (Setelah `D1–D11` Disetujui)

| Langkah | Cakupan | Artefak Utama |
|---|---|---|
| **S0** | Pembekuan keputusan `DEC-39` & penutupan `DEC-10` | `docs/DECISIONS.md`, `reports/feature-update/PHASE-10-PLAN.md` |
| **S1** | Konfigurasi & Migration 10a + sinkronisasi ability role default | `config/teaching.php`, 4 migration `2029_10_01_*`, `AbilityCatalog.php` |
| **S2** | Model (`TeachingAssignment`, `TeachingSchedule`, `TeachingScheduleException`) + `Employee::scopeEligibleTeacher()` + guard `SchoolYearController` & `SchoolRombelController` | `app/Models/Teaching*.php`, `Employee.php`, `SchoolYearController.php`, `SchoolRombelController.php` |
| **S3** | `TeachingScheduleService` (validasi penugasan, kalkulasi bentrok guru/kelas/ruang pada jadwal reguler & reschedule, pemeriksaan tahun ajaran terkunci, optimistic locking `409`) | `app/Services/TeachingScheduleService.php` |
| **S4** | FormRequests, Controllers, dan Routes `/api/teaching/*` | `TeachingAssignmentController.php`, `TeachingScheduleController.php`, `TeachingScheduleExceptionController.php`, `TeachingOptionController.php`, `routes/api.php` |
| **S5** | Antarmuka Vue `TeachingSchedulesView.vue` (3 tab: Weekly Timetable, Teaching Assignments, Schedule Exceptions) + router & menu sidebar | `vue-app/src/views/teaching/TeachingSchedulesView.vue`, `vue-app/src/router/index.js`, `vue-app/src/layout/AppMenu.vue` |
| **S6** | Tes otomatis PHPUnit (`TeachingScheduleApiTest`) & Playwright smoke (`phase10-teaching-smoke.spec.js`) + regresi penuh | `laravel-api/tests/Feature/TeachingScheduleApiTest.php`, `vue-app/tests/e2e/phase10-teaching-smoke.spec.js` |
| **S7** | Launcher UAT `scripts/phase10-uat.php` (`.uat-phase10`, port `18028`/`14028`) & verifikasi browser langsung | `scripts/phase10-uat.php`, `.gitignore` |
| **S8** | Sinkronisasi dokumentasi, laporan `PHASE-10A-REPORT.md`, dan `PHASE-10A-MANUAL-REVIEW.md` | `docs/*`, `roadmap/*`, `reports/feature-update/*`, `PROJECT_CONTEXT.md`, `CHANGELOG.md` |

### 4.3. Rencana Matriks Pengujian Otomatis (`T1–T18`)

| ID | Skenario | Target Verifikasi |
|---|---|---|
| **T1** | RBAC akses `/api/teaching/*` | `admin-sistem`, `operator-sekolah`, `wakil-kesiswaan` dapat `view` & `manage`; `kepala-sekolah` dan `guru` hanya `view` (`403` saat tulis); `staf-tata-usaha` ditolak `403` pada semua endpoint. |
| **T2** | Buat & perbarui penugasan mengajar valid | Sukses `201`/`200`, versi bertambah, tercatat di `application_logs` tanpa `notes`. |
| **T3** | Validasi silang penugasan mengajar | Tolak (`422`) bila `school_rombel.school_year_id != school_year_id`, pegawai bukan `Pendidik` aktif (mis. `Tenaga Kependidikan` atau sudah `exit_date` lewat), atau kombinasi `(year, semester, rombel, subject, teacher)` duplikat. |
| **T4** | Buat slot jadwal mingguan & preset jam pelajaran | Sukses `201`, waktu dan ruang (`room_normalized`) tersimpan, muncul di matriks jadwal kelas dan guru. |
| **T5** | Deteksi bentrok **Guru** (`employee_id`) | Tolak (`422`) saat guru yang sama dijadwalkan pada hari & jam yang beririsan di kelas berbeda pada periode yang sama. |
| **T6** | Deteksi bentrok **Kelas/Rombel** (`school_rombel_id`) | Tolak (`422`) saat kelas yang sama dijadwalkan dua mata pelajaran/guru pada hari & jam yang beririsan. |
| **T7** | Deteksi bentrok **Ruang** (`room_normalized`) | Tolak (`422`) saat ruang yang sama (mis. `lab-komp-1` vs `LAB-KOMP-1`) dipakai dua kelas berbeda pada hari & jam yang beririsan; izinkan bila `room` kosong (`null`). |
| **T8** | Jadwal `inactive` tidak memicu bentrok & re-aktivasi memeriksa ulang bentrok | Menonaktifkan jadwal membebaskan slot; mengaktifkan kembali jadwal yang slotnya sudah terisi jadwal aktif lain ditolak `422`. |
| **T9** | Exception `holiday` (sekolah & per rombel) dan `cancelled` | Sukses `201`; tolak `422` bila `exception_date` di luar rentang tahun ajaran atau hari `cancelled` tidak cocok dengan `day_of_week` jadwal. |
| **T10** | Exception `rescheduled` & deteksi bentrok pada tanggal/jam pengganti | Sukses `201` bila slot pengganti kosong; tolak `422` bila jam pengganti pada `rescheduled_date` bentrok dengan jadwal reguler aktif atau reschedule lain (guru/kelas/ruang), atau jatuh pada tanggal `holiday`. |
| **T11** | Proteksi tahun ajaran terkunci (`locked_at !== null`) | Tolak (`422`) tambah/ubah/hapus penugasan, jadwal, dan exception pada tahun ajaran yang sudah `locked`. |
| **T12** | Optimistic locking (`409 Conflict`) | Kirim `expected_version` basi pada update penugasan, jadwal, dan exception → ditolak `409`. |
| **T13** | Guard penghapusan master & penugasan/jadwal | `DELETE` tahun ajaran atau rombel yang punya penugasan ditolak `422`; `DELETE` penugasan yang punya jadwal/exception ditolak `422`. |
| **T14** | Paginasi & skala ≥ 100 baris | Filter & paginasi `/api/teaching/assignments` dan `/api/teaching/schedules` stabil pada dataset ≥ 100 baris. |
| **T15–T18** | Smoke E2E Playwright (`.uat-phase10`) | Login operator/admin → buka `/public/teaching/schedules` → lihat matriks jadwal → uji tambah jadwal & verifikasi pesan bentrok → tambah exception kalender → verifikasi layout mobile `390 px` tanpa horizontal overflow & zero console/page error. |

---

## 5. Dampak File yang Direncanakan (Saat Eksekusi 10a Nanti)

- **Backend Baru:**
  - `laravel-api/config/teaching.php`
  - `laravel-api/database/migrations/2029_10_01_000001_create_teaching_assignments_table.php`
  - `laravel-api/database/migrations/2029_10_01_000002_create_teaching_schedules_table.php`
  - `laravel-api/database/migrations/2029_10_01_000003_create_teaching_schedule_exceptions_table.php`
  - `laravel-api/database/migrations/2029_10_01_000004_sync_teaching_abilities_to_default_roles.php`
  - `laravel-api/app/Models/TeachingAssignment.php`
  - `laravel-api/app/Models/TeachingSchedule.php`
  - `laravel-api/app/Models/TeachingScheduleException.php`
  - `laravel-api/app/Services/TeachingScheduleService.php`
  - `laravel-api/app/Http/Controllers/TeachingOptionController.php`
  - `laravel-api/app/Http/Controllers/TeachingAssignmentController.php`
  - `laravel-api/app/Http/Controllers/TeachingScheduleController.php`
  - `laravel-api/app/Http/Controllers/TeachingScheduleExceptionController.php`
  - `laravel-api/app/Http/Requests/TeachingAssignmentStoreRequest.php`, `TeachingAssignmentUpdateRequest.php`
  - `laravel-api/app/Http/Requests/TeachingScheduleStoreRequest.php`, `TeachingScheduleUpdateRequest.php`
  - `laravel-api/app/Http/Requests/TeachingScheduleExceptionStoreRequest.php`, `TeachingScheduleExceptionUpdateRequest.php`
  - `laravel-api/tests/Feature/TeachingScheduleApiTest.php`
- **Backend Diperbarui:**
  - `laravel-api/app/Support/AbilityCatalog.php`
  - `laravel-api/app/Models/Employee.php`, `SchoolYear.php`, `SchoolRombel.php`, `Subject.php`
  - `laravel-api/app/Http/Controllers/SchoolYearController.php`, `SchoolRombelController.php`
  - `laravel-api/routes/api.php`
- **Frontend & UAT Baru/Diperbarui:**
  - `vue-app/src/views/teaching/TeachingSchedulesView.vue`
  - `vue-app/src/router/index.js`, `vue-app/src/layout/AppMenu.vue`
  - `vue-app/tests/e2e/phase10-teaching-smoke.spec.js`
  - `scripts/phase10-uat.php`, `.gitignore`

---

## 6. Risiko dan Mitigasi

| Risiko | Mitigasi |
|---|---|
| Balapan (*race condition*) saat dua operator menambah jadwal guru/kelas/ruang yang sama secara bersamaan | Kunci baris `SchoolYear` (`lockForUpdate()`) di dalam transaksi database sebelum mengevaluasi query irisan waktu pada `TeachingScheduleService`. |
| Perbedaan penulisan nama ruang (`Lab Komp 1` vs `lab komp 1 `) membuat bentrok ruang lolos | Simpan dan indeks kolom `room_normalized = UPPER(TRIM(preg_replace('/\s+/', ' ', $room)))` serta gunakan kolom tersebut untuk pengecekan bentrok. |
| Reschedule jadwal dipindahkan ke tanggal yang ternyata merupakan hari libur sekolah (`holiday`) atau menabrak jadwal reguler guru/kelas pada hari target | `TeachingScheduleService` memvalidasi `rescheduled_date` terhadap tabel `teaching_schedule_exceptions` (`holiday`) serta jadwal reguler aktif pada `DAYOFWEEK(rescheduled_date)` (dikurangi slot yang sedang `cancelled`/`rescheduled` pada tanggal itu). |
| Tabel matriks jadwal mingguan melebar di layar sempit (`390 px`) | Gunakan kontainer scroll horizontal internal khusus di dalam kartu matriks atau mode ringkas per hari pada viewport mobile sehingga `documentElement.scrollWidth <= clientWidth` tetap terjaga. |

---

## 7. Kriteria Penerimaan Paket Rencana Ini

1. Seluruh temuan audit kode aktual pada baseline `f185ca6` terdokumentasi secara jujur.
2. Keputusan **D1–D11** (`DEC-39 PROPOSED`) mencakup penyelesaian penuh **`DEC-10`** (struktur jam/slot jadwal, exception libur/cancel/reschedule, dan kebijakan batas waktu pengisian jurnal untuk 10b/10c).
3. Tidak ada kode aplikasi, migration, atau tes yang dieksekusi sebelum pemilik menyetujui **D1–D11**.
