# Rencana Fase 08 — Perizinan Siswa dan Guru (08a Pengajuan; 08b Keputusan dan Rekap)

- Tanggal: 2026-11-02 (WIB). Branch: `main`. Baseline dokumen/audit: `2db6475` (paket penerimaan 07c).
- Status dokumen: **PLAN — AWAITING OWNER APPROVAL (D1–D12)**. Diajukan sebagai **DEC-35 PROPOSED**. **DEC-07 tetap OPEN** sampai pemilik menyetujui keputusan di dokumen ini.
- Cakupan paket ini: **rencana saja** — belum ada implementasi izin, migration, route, model, controller, service, UI, tes, atau UAT. Paket ini hanya menambah dokumen.
- Otorisasi sebelumnya: 07a/07b/07c **OWNER APPROVED** (07c #1–#18, 2026-11-02 WIB). **DEC-11 CLOSED untuk lingkup 07c**; **DEC-13 tetap OPEN**.
- Di luar cakupan paket ini: implementasi Fase 08, merge/deploy, migrasi database operasional/MariaDB, perubahan dependency/lockfile, perubahan service/`.env`, dan perluasan peran di luar D9.

---

## 1. Posisi dan prasyarat

| Prasyarat | Kondisi aktual 2026-11-02 | Status |
|---|---|---|
| Subfase 07c | Surat bertipe, kop/tanda tangan/stempel, template PDF, snapshot, preview/download; owner approved #1–#18; UAT `.uat-phase07` dihentikan (DEC-27) | Terpenuhi |
| Ability izin | `leaves.view`, `leaves.manage`, `leaves.approve` sudah ada dan termap ke peran awal; belum ada enforcement karena fitur belum ada | Sebagian; perlu `leaves.request` (D9) |
| Domain siswa | `students`, `student_rombels`, `school_rombels`, `school_years`, `academic_settings` tersedia; satu keanggotaan aktif per siswa/tahun ditegakkan | Terpenuhi |
| Identitas pegawai—akun | `employees.user_id` unik; `resolveUser()` anti-ambigu; fallback NIK hanya untuk baris legacy bertanda | Terpenuhi; self-service memakai `user_id` saja |
| File privat | `LetterAttachmentService` memvalidasi MIME/ekstensi/ukuran dan menyajikan unduhan berizin; backup mencakup `storage/app/attachments` | Pola tersedia; perlu validasi bersama (D8) |
| Authorisasi | Ability bernama + Policy/Gate; route `auth:sanctum` + `active`; object-level check di server | Terpenuhi |
| UAT | `.uat-phase07` berhenti; `.uat-phase05/06` dipertahankan; launcher per fase sudah menjadi pola | Perlu `.uat-phase08` baru |
| Dependency | Tidak ada kebutuhan PDF/email/queue untuk izin; semua dependensi yang ada cukup | Tidak ada perubahan dependency |
| Keputusan | **DEC-07 OPEN** (jenis, approver, delegasi, perubahan/pembatalan keputusan); **DEC-13 OPEN** | Gate D1–D12 |

Seluruh kondisi di atas diverifikasi dari source, migration, konfigurasi, dan dokumen aktual, bukan dari data operasional.

---

## 2. Temuan audit (fakta kode)

### 2.1 Belum ada implementasi izin

- Ability izin hanya berupa katalog: `leaves.view`/`leaves.manage`/`leaves.approve` di `laravel-api/app/Support/AbilityCatalog.php:59-61`; `leaves.approve` bersifat sensitif.
- Tidak ada `config/leaves.php`, model `LeaveRequest`/`LeaveDecision`/`LeaveAttachment`, controller, request, service, migration, atau route izin. Pencarian `leave_request|leave_decision|leaves.request` hanya menemukan katalog ability dan label UI `vue-app/src/views/pages/master/Roles.vue:57`.
- Tidak ada tabel `leave_requests`/`leave_decisions`; keduanya masih berupa usulan di `docs/DATABASE.md:87`.
- Tidak ada halaman, menu, atau route frontend izin. Route surat berhenti di `laravel-api/routes/api.php:192-280`; router frontend hanya memuat surat di `vue-app/src/router/index.js:79-102` dan menu Letters di `vue-app/src/layout/AppMenu.vue:51-58`.
- FR-10 baru berupa baris kebutuhan: "Siswa/guru, tanggal/rentang, alasan, lampiran, status diajukan/disetujui/ditolak/dibatalkan, rekap" (`docs/REQUIREMENTS.md:33`). Aturan bisnis menegaskan izin tidak otomatis mengubah absensi (`docs/REQUIREMENTS.md:44`).

**Implikasi:** Fase 08 membangun domain baru dari nol. Tidak ada consumer lama yang perlu dipertahankan; tidak ada dependency baru.

### 2.2 Subjek siswa dan pegawai memiliki identitas berbeda

- `Student` tidak memiliki `user_id` atau relasi ke `User`; fillable hanya data profil/keluarga (`laravel-api/app/Models/Student.php:35-119`). Siswa tidak login. Karena itu pengajuan izin siswa harus dilakukan staf berwenang atas nama siswa.
- Keanggotaan aktif siswa ditentukan `student_rombels` (tidak deleted/archived, `student_status_id` null/1, `latest_student_exit_date` null) dan aturan satu keanggotaan aktif per siswa per tahun ditegakkan di `laravel-api/app/Models/StudentRombel.php:82-132`.
- `Employee` terhubung ke akun melalui `user_id` (relasi `user()` di `laravel-api/app/Models/Employee.php:130-136`); `resolveUser()` memprioritaskan `user_id` dan menolak fallback NIK bila akun telah dilepas/diresolusi (`Employee.php:151-177`). Profil pegawai boleh tanpa akun (DEC-04).
- Tahun ajaran aktif berasal dari singleton `academic_settings` (`laravel-api/app/Models/AcademicSetting.php:14-45`); `school_years` memiliki `start_date`/`end_date` (`laravel-api/app/Models/SchoolYear.php:23-36`).

**Implikasi:** subjek siswa disimpan dengan `student_id` + snapshot `school_year_id`/`student_rombel_id`; subjek pegawai dengan `employee_id`. Self-service pegawai memakai `employees.user_id` yang tepat, bukan NIK.

### 2.3 Peran awal belum memiliki peran guru

- Peran awal hanya `admin-sistem`, `operator-sekolah`, `kepala-sekolah`, `wakil-kesiswaan` (`AbilityCatalog.php:82-140`).
- Pemetaan izin saat ini: admin/operator `leaves.view` + `leaves.manage`; kepala sekolah `leaves.view` + `leaves.approve`; wakil kesiswaan hanya `leaves.view`. Admin tidak otomatis memperoleh `leaves.approve` (DEC-03).
- Tidak ada peran `guru`/Teacher. Agar "guru hanya permohonan sendiri" dapat ditegakkan dengan kemampuan bernama, diperlukan ability `leaves.request` dan peran guru yang hanya memilikinya (D9), atau admin membuat peran tersebut manual.
- Matriks target lama sudah mengusulkan `leaves.request`/`leaves.decide` (`docs/ACCESS-CONTROL.md:124-125`), tetapi belum menjadi enforcement.

**Implikasi:** D9 adalah keputusan bisnis/akses; tanpa itu guru harus diberi `leaves.manage`/`leaves.view` yang terlalu luas, atau endpoint self-service tidak memakai ability bernama (tidak direkomendasikan).

### 2.4 File privat dan header unduhan sudah memiliki preseden

- `LetterAttachmentService` memvalidasi upload (PDF/JPG/JPEG/PNG, MIME server, ekstensi cocok, gambar valid, batas ukuran), menulis nama UUID di disk privat, menghitung SHA-256, dan menyajikan unduhan streaming dengan header keamanan (`laravel-api/app/Services/Letters/LetterAttachmentService.php:24-131`, `:149-...`).
- 07c menetapkan respons dokumen memakai `X-Document-Filename` dan **tanpa `Content-Disposition`** agar tidak diblokir Chrome lintas origin; temuan ini tercatat di `docs/ACCESS-CONTROL.md:113`. Lampiran 07a/07b masih memakai `Content-Disposition` dan menjadi temuan di luar scope.
- Backup memindai `storage/app/attachments` (`laravel-api/config/backup.php:19`), sehingga lampiran izin di bawah path itu tercakup tanpa perubahan `BackupService`.

**Implikasi:** D8 memakai aturan validasi yang sama, path privat baru, dan header unduhan 07c. Validasi file diekstrak ke komponen bersama; penyimpanan/unduhan izin terpisah agar perilaku surat tidak berubah.

### 2.5 Pola UI dan UAT dapat diperluas

- Route halaman memakai `requiresAbility` tunggal/array OR (`vue-app/src/router/index.js:330-338`); menu memakai `can`/`canAny` (`vue-app/src/layout/AppMenu.vue:208-224`).
- Halaman surat menjadi contoh filter server, dialog create/edit, detail, aksi transisi, dan `409` Reload Latest (`OutgoingLetters.vue`, `IncomingLetters.vue`).
- Launcher UAT per fase: `scripts/phase06-uat.php` memakai 18024/14024 dan `scripts/phase07-uat.php` memakai 18025/14025, cookie per fase, SQLite/storage terisolasi, guard konfigurasi (`phase07-uat.php:29-40`). Fase 08 memakai urutan berikutnya: **18026/14026**.

**Implikasi:** UI izin dapat mengikuti pola yang sudah diterima; UAT baru `.uat-phase08` tanpa menyentuh lingkungan fase lain.

### 2.6 Gate dan temuan yang dibawa

- **DEC-07 OPEN**: jenis dan approval izin, approver, delegasi, perubahan/pembatalan keputusan (`docs/DECISIONS.md:344`).
- **DEC-13 OPEN**: retensi data pribadi/lampiran; Fase 08 tidak menutupnya.
- `Tests\Feature\Backup\MariaDbIntegrationTest` membaca `school_access_uat` saat suite penuh; harus digating sebelum suite penuh berikutnya.
- Unduhan lampiran 07a/07b masih gagal lintas origin di Chrome karena `Content-Disposition`; tidak diperbaiki di Fase 08 dan tidak boleh dianggap regresi baru.
- `.uat-phase07` tidak boleh direset; Fase 08 memakai lingkungan baru.

---

## 3. Scope dan non-goals

### 3.1 08a — Pengajuan izin

- Jenis izin siswa/pegawai dari konfigurasi kode (tanpa CRUD master).
- Subjek siswa (oleh staf berwenang) atau pegawai (self-service atau atas nama).
- Rentang tanggal, alasan, lampiran privat opsional.
- Validasi overlap dan eligibility di server.
- Status `draft`, `submitted`, `cancelled` (pembatalan sebelum keputusan).
- List/detail/milik sendiri, subject picker, lampiran, audit, UI.
- **Tidak** termasuk approve/reject/rekap; itu 08b.

### 3.2 08b — Keputusan dan rekap

- Approve/reject oleh pemegang `leaves.approve`, alasan keputusan, aktor/waktu.
- Pembatalan izin yang sudah approved oleh pemegang `leaves.approve`.
- Riwayat keputusan immutable dan transisi atomik/retry aman.
- Rekap per periode, jenis, status, kelas (siswa), dan pegawai.
- **Tidak** mengubah keputusan approved menjadi rejected; koreksi dilakukan dengan pembatalan + pengajuan baru.

### 3.3 Non-goals (eksplisit)

- Absensi otomatis, saldo cuti, potongan gaji, atau perhitungan kuota.
- Notifikasi email/WhatsApp/SMS, kalender, atau integrasi eksternal.
- Akun/login siswa atau orang tua; self-service siswa.
- Persetujuan per wali kelas, delegasi per-request, atau approval berjenjang.
- Izin setengah hari, per jam, berulang, atau lintas tahun ajaran.
- PDF/surat izin, impor/ekspor CSV izin, arsip/versioning Fase 09, retensi DEC-13.
- Perubahan dependency/lockfile, service, `.env`, database operasional/MariaDB, merge/deploy.

---

## 4. Desain yang diusulkan

### 4.1 Jenis izin (config, tanpa master CRUD)

`laravel-api/config/leaves.php` (usulan):

```php
'types' => [
    'student' => [
        'sick'       => ['label' => 'Sick Leave',       'max_days' => 30, 'attachment_required' => false],
        'permission' => ['label' => 'Permission',       'max_days' => 30, 'attachment_required' => false],
        'other'      => ['label' => 'Other',            'max_days' => 30, 'attachment_required' => false],
    ],
    'employee' => [
        'annual'        => ['label' => 'Annual Leave',        'max_days' => 30, 'attachment_required' => false],
        'sick'          => ['label' => 'Sick Leave',          'max_days' => 30, 'attachment_required' => false],
        'maternity'     => ['label' => 'Maternity/Paternity', 'max_days' => 90, 'attachment_required' => false],
        'official_duty' => ['label' => 'Official Duty',        'max_days' => 30, 'attachment_required' => false],
        'other'         => ['label' => 'Other',                'max_days' => 30, 'attachment_required' => false],
    ],
],
'max_range_days' => 90,
'attachment' => [
    'disk' => env('LEAVES_DISK', 'local'),
    'path' => 'attachments/leaves',
    'max_file_kb' => 5120,
    'max_files_per_request' => 3,
    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
    'allowed_mimetypes' => ['application/pdf', 'image/jpeg', 'image/png'],
],
'reason' => ['min' => 5, 'max' => 1000],
'decision_reason' => ['min' => 10, 'max' => 500],
'cancel_reason' => ['min' => 10, 'max' => 255],
```

- Kode jenis stabil; label English (DEC-14). Tidak ada endpoint CRUD jenis izin.
- Jenis divalidasi terhadap `subject_type`; kombinasi salah `422`.
- `max_days` per jenis (30; maternity/paternity 90) dan batas global `max_range_days` (90) divalidasi server; perubahan config tidak mengubah data lama.
- Semua lampiran opsional di v1; `attachment_required` disediakan agar keputusan pemilik dapat mengubah tanpa migration.

### 4.2 Model data (additive)

#### 4.2.1 `leave_requests`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | — |
| `subject_type` | string(20) | `student`/`employee` |
| `student_id` | FK `students` nullable, restrictOnDelete | Wajib jika `subject_type=student` |
| `employee_id` | FK `employees` nullable, restrictOnDelete | Wajib jika `subject_type=employee` |
| `school_year_id` | FK `school_years` nullable, restrictOnDelete | Snapshot tahun ajaran untuk siswa |
| `student_rombel_id` | FK `student_rombels` nullable, restrictOnDelete | Snapshot kelas saat pengajuan |
| `type` | string(30) | Kode dari config |
| `start_date` | date | Inklusif, WIB |
| `end_date` | date | Inklusif, WIB |
| `reason` | text | 5–1000 karakter |
| `status` | string(20), default `draft` | `draft`/`submitted`/`approved`/`rejected`/`cancelled` |
| `version` | unsigned int, default 1 | Optimistic locking |
| `requested_by` | FK `users`, restrictOnDelete | Pembuat (self atau staf) |
| `submitted_at`/`submitted_by` | timestamp/FK `users` nullable, nullOnDelete | Diisi saat submit |
| `decided_at`/`decided_by` | timestamp/FK `users` nullable, nullOnDelete | Keputusan terakhir |
| `cancelled_at`/`cancelled_by` | timestamp/FK `users` nullable, nullOnDelete | Pembatalan |
| `timestamps` | — | — |

- Index: `(status, start_date)`, `(subject_type, student_id, start_date)`, `(employee_id, start_date)`, `(school_year_id, student_rombel_id)`, `(type, start_date)`, `(requested_by)`.
- Tidak ada soft delete dan tidak ada endpoint delete; draft dibatalkan, bukan dihapus.
- Aplikasi menegakkan tepat satu subjek sesuai `subject_type`; tidak mempercayai ID dari request tanpa validasi.
- Nama subjek tidak diduplikasi; dibaca dari relasi `withTrashed()` agar riwayat tetap tampil.

#### 4.2.2 `leave_decisions` (immutable)

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | — |
| `leave_request_id` | FK `leave_requests`, restrictOnDelete | — |
| `action` | string(20) | `approved`/`rejected`/`cancelled` |
| `from_status` | string(20) | Status sebelum aksi |
| `to_status` | string(20) | Status sesudah aksi |
| `actor_id` | FK `users`, restrictOnDelete | Penyetuju/pembatal |
| `reason` | text nullable | Wajib untuk reject/cancel; opsional untuk approve |
| `created_at` | timestamp | Tanpa `updated_at` |

- Index `(leave_request_id, created_at)`.
- Model menolak update/delete; tidak ada endpoint tulis langsung.
- Riwayat lengkap keputusan; submit/edit dicatat di Activity dan kolom `submitted_*`.

#### 4.2.3 `leave_attachments`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | — |
| `leave_request_id` | FK `leave_requests`, restrictOnDelete | — |
| `original_name` | string(255) | Nama tersanitasi |
| `path` | string(255), unique | Relatif disk privat; tidak pernah diekspos |
| `mime_type` | string(100) | Hasil deteksi server |
| `size_bytes` | unsigned big int | — |
| `checksum_sha256` | char(64) | — |
| `uploaded_by` | FK `users`, nullOnDelete | — |
| `deleted_at` | timestamp nullable | Soft delete; file fisik dipertahankan (DEC-13) |
| `timestamps` | — | — |

- Index `(leave_request_id, deleted_at)`.
- Path: `attachments/leaves/{leave_request_id}/{uuid}.{ext}` pada disk `leaves.disk` (default `local`).
- Maksimal 3 file, 5 MB per file; PDF/JPG/JPEG/PNG; validasi isi dan ekstensi seperti surat.

### 4.3 Workflow dan transisi

| Dari | Aksi | Aktor | Ke | Catatan |
|---|---|---|---|---|
| — | create | `leaves.manage` (siswa/atas nama) atau `leaves.request` (self pegawai) | `draft` | Validasi subjek/type/tanggal; draft boleh overlap |
| `draft` | edit | pembuat atau `leaves.manage` | `draft` | Hanya field yang boleh berubah; versi naik |
| `draft` | submit | pembuat atau `leaves.manage` | `submitted` | Validasi lengkap + overlap; `submitted_*` diisi |
| `draft` | cancel | pembuat atau `leaves.manage` | `cancelled` | Alasan opsional; jika diisi 10–255; versi naik |
| `submitted` | approve | `leaves.approve`, bukan pembuat/subjek | `approved` | Re-check overlap; alasan opsional |
| `submitted` | reject | `leaves.approve`, bukan pembuat/subjek | `rejected` | Alasan wajib 10–500 |
| `submitted` | cancel | pembuat atau `leaves.manage` | `cancelled` | Alasan wajib 10–255 |
| `approved` | cancel | `leaves.approve`, bukan pembuat/subjek | `cancelled` | Alasan wajib 10–255; keputusan lama tetap di riwayat |
| `rejected` | — | — | final | Koreksi via pengajuan baru |
| `cancelled` | — | — | final | — |

- Semua transisi memakai transaksi, `lockForUpdate`, dan `expected_version`; versi basi/status tidak valid → `409` dengan payload terbaru yang aman.
- Dua keputusan paralel: yang pertama commit menang; yang kedua menerima `409` dan tidak mengubah data.
- Tidak ada reopen, edit setelah submit, atau perubahan approved → rejected.
- `leave_decisions` hanya mencatat approve/reject/cancel; submit/edit melalui Activity + kolom `submitted_*`/versi.

### 4.4 Aturan tanggal dan overlap

- Tanggal saja (full-day), inklusif, zona WIB; `start_date <= end_date`.
- Batas efektif adalah `max_days` jenis (30; maternity/paternity 90) dan tidak boleh melebihi `max_range_days` global (90); melebihi → `422`.
- Siswa: rentang harus berada di dalam `school_years.start_date`–`end_date` tahun ajaran aktif. Tahun aktif/tanggal tidak tersedia → `422` readiness.
- Pegawai: tidak terikat tahun ajaran; tanggal lampau diizinkan untuk sakit/izin susulan.
- Overlap dicek untuk subjek yang sama dengan status `submitted`/`approved`:
  `existing.start_date <= new.end_date AND existing.end_date >= new.start_date`.
- Draft tidak memblokir; `rejected`/`cancelled` tidak memblokir; tanggal berdampingan diperbolehkan.
- Overlap dicek saat submit dan dicek ulang saat approve. Konflik → `422` dengan `errors.overlap` berisi ID/tanggal konflik (tanpa data pribadi).
- Submit mengunci baris subjek (`students`/`employees`) di dalam transaksi untuk mengurangi race; perilaku race MariaDB/InnoDB tetap **NOT RUN** sampai diuji.

### 4.5 Aktor, self-service, dan approval

- **Siswa**: hanya `leaves.manage` (default Admin Sistem/Operator Sekolah) yang dapat membuat. Guru default tidak dapat membuat izin siswa.
- **Pegawai self**: `leaves.request` + `employees.user_id = actor.id` tepat satu; tidak memakai fallback NIK. Tanpa profil tertaut → `403` dengan pesan agar admin menautkan akun.
- **Pegawai atas nama**: `leaves.manage`; guru default tidak memiliki manage sehingga hanya bisa self. Jika admin memberikan `leaves.manage`, aktor dianggap berwenang secara eksplisit.
- **Approver**: `leaves.approve`; default hanya Kepala Sekolah. Admin teknis tidak otomatis; Wakil Kesiswaan default view-only. Pemberian approve ke peran lain dilakukan lewat Roles & Abilities (delegasi operasional).
- **Larangan self-decision**: aktor tidak boleh approve/reject/cancel-approved untuk request yang ia buat, atau yang subjek pegawainya adalah dirinya sendiri. `403`.
- **Delegasi**: tidak ada tabel delegate per-request di v1. Delegasi = pemberian ability/peran oleh Admin Sistem melalui UI yang ada. Jika pemilik menghendaki delegate eksplisit, itu keputusan/lingkup terpisah.
- **Homeroom approval**: tidak ada di v1; wali kelas tidak otomatis menjadi approver.

### 4.6 Lampiran privat

- Opsional; maksimal 3 per pengajuan; PDF/JPG/JPEG/PNG; maksimal 5 MB.
- Validasi: upload valid, nama satu ekstensi, ekstensi allowlist, MIME server allowlist, ekstensi cocok dengan MIME, `getimagesize` untuk gambar, ukuran.
- Nama file UUID; path tidak pernah diekspos; checksum SHA-256 disimpan; nama asli disanitasi.
- Upload/hapus hanya saat `draft`/`submitted` oleh pembuat atau `leaves.manage`; setelah keputusan read-only.
- Hapus lampiran = soft delete baris; file fisik dipertahankan sampai kebijakan retensi DEC-13.
- Unduhan memakai endpoint berizin: `X-Document-Filename`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: sandbox; default-src 'none'`, `Cache-Control: private, no-store`; **tanpa `Content-Disposition`** (pola 07c).
- Validasi file diekstrak dari `LetterAttachmentService` ke `App\Services\Files\PrivateUploadValidator`; `LetterAttachmentService` dan service izin memakai validator yang sama. Penyimpanan/streaming izin berada di `LeaveAttachmentService`. Regresi lampiran surat wajib lulus.
- Backup mencakup `storage/app/attachments`; restore drill dengan lampiran izin **NOT RUN** sampai dijalankan.

### 4.7 Ability dan peran

- Tambah ability **`leaves.request`** (non-sensitif): "Mengajukan izin untuk diri sendiri (pegawai tertaut)."
- Tambah peran awal **`guru`** (Teacher, non-protected) dengan hanya `leaves.request`; admin dapat menambah/mencabut ability peran ini.
- Tambah `leaves.request` ke peran awal `admin-sistem`, `operator-sekolah`, `kepala-sekolah`, `wakil-kesiswaan` (agar staf yang juga pegawai dapat self-request tanpa `leaves.manage`).
- `leaves.view` tetap berarti melihat seluruh izin; `leaves.manage` mengelola seluruh izin/siswa/atas nama; `leaves.approve` memutuskan; `leaves.request` hanya milik sendiri.
- Migration additive idempotent: ability + pivot peran + role `guru`; tidak mengubah ID/nama peran yang ada dan tidak menimpa konfigurasi ability yang sudah disesuaikan admin.
- `Roles.vue` menambah label English `leaves.request` ("Request own leave").
- DEC-03 menetapkan empat peran awal; penambahan `guru` adalah perluasan konfigurasi awal dan memerlukan persetujuan D9. Alternatif tanpa peran seed: admin membuat peran Guru manual; ability tetap ditambahkan.

### 4.8 Kontrak API (usulan 16 route)

Semua route berada di grup `auth:sanctum` + `active`. Route statis (`/options`, `/mine`, `/recap`, `/subjects/...`) didaftarkan sebelum `/{leave}`; parameter memakai `whereNumber('leave')`.

**08a**

| # | Method | Endpoint | Izin/Policy | Ringkas |
|---|---|---|---|---|
| 1 | GET | `/api/leaves/options` | `leaves.view`/`leaves.request`/`leaves.manage` (salah satu) | Jenis per subject, status, filter, capability aktor |
| 2 | GET | `/api/leaves` | `leaves.view` | Daftar semua izin + filter/paginasi |
| 3 | GET | `/api/leaves/mine` | `leaves.request` | Riwayat izin milik aktor |
| 4 | GET | `/api/leaves/{leave}` | Policy view: `leaves.view` atau pemilik/pembuat | Detail + lampiran + riwayat keputusan |
| 5 | POST | `/api/leaves` | Policy create: self `leaves.request`; siswa/atas nama `leaves.manage` | Buat draft |
| 6 | PUT/PATCH | `/api/leaves/{leave}` | Policy update: pembuat/`leaves.manage`, status draft | Edit draft |
| 7 | POST | `/api/leaves/{leave}/submit` | Policy submit: pembuat/`leaves.manage`, draft | Validasi + overlap + submit |
| 8 | POST | `/api/leaves/{leave}/cancel` | Policy cancel: pembuat/`leaves.manage` (draft/submitted); 08b: approve untuk approved | Batalkan dengan alasan |
| 9 | POST | `/api/leaves/{leave}/attachments` | Policy upload: pembuat/`leaves.manage`, draft/submitted | Upload lampiran |
| 10 | GET | `/api/leaves/{leave}/attachments/{attachment}/download` | Policy view | Unduh lampiran berizin |
| 11 | DELETE | `/api/leaves/{leave}/attachments/{attachment}` | Policy delete: pembuat/`leaves.manage`, draft/submitted | Soft delete lampiran |
| 12 | GET | `/api/leaves/subjects/students` | `leaves.manage` + `students.view` | Picker siswa aktif tahun aktif |
| 13 | GET | `/api/leaves/subjects/employees` | `leaves.manage` + `employees.view` | Picker pegawai aktif |

**08b**

| # | Method | Endpoint | Izin/Policy | Ringkas |
|---|---|---|---|---|
| 14 | POST | `/api/leaves/{leave}/approve` | `leaves.approve` + bukan pembuat/subjek | Setujui; alasan opsional |
| 15 | POST | `/api/leaves/{leave}/reject` | `leaves.approve` + bukan pembuat/subjek | Tolak; alasan wajib |
| 16 | GET | `/api/leaves/recap` | `leaves.view` | Rekap periode/kelas/pegawai/jenis/status |

- Respons mutasi memakai resource aman; create/update/transisi `200`; validasi `422`; konflik versi/status `409`; tidak berizin `403`; objek tidak terlihat `404` (bukan 403 agar keberadaan tidak bocor).
- `409` menyertakan `latest` yang aman (status/versi) untuk Reload Latest; tidak menyertakan lampiran/path.
- `LeaveRequestResource` memuat: id, subject_type, subjek ringkas (nama, NIS/NUPTK, kelas/tahun), type/label, tanggal, jumlah hari, alasan, status, versi, pembuat, waktu submit/keputusan, lampiran ringkas, riwayat keputusan, dan `can` (edit/submit/cancel/approve/reject/upload/delete). Tidak memuat NIK, alamat, kontak, path, hash, atau nama file asli di luar lampiran yang berhak.
- Filter list: status, type, subject_type, date_from/date_to, school_year_id, rombel_id, employee_type_id, search (nama/NIS/NUPTK), page/per_page (10/20/50/100). Sort whitelist.
- Rekap: `from`/`to` inklusif, type, status, subject_type, school_year_id, rombel_id, employee_type_id; agregat total/hari per status/jenis, per kelas, per pegawai; tanpa ekspor di v1.

### 4.9 UI (usulan)

- Route `/public/leaves` dengan `requiresAbility: ['leaves.view', 'leaves.request']`.
- Menu grup **Leaves** (requires OR): item **Leaves** menuju halaman yang sama; tab ditentukan ability.
- Halaman `Leaves.vue`:
  - Tab **Requests** (`leaves.view`): filter status/jenis/subject/tanggal/search; tabel; detail.
  - Tab **My Leaves** (`leaves.request`): hanya izin pegawai milik aktor; tombol New Self Request.
  - Tab **Recap** (`leaves.view`, 08b): filter periode/kelas/pegawai/jenis/status; ringkasan.
- Dialog **New/Edit**:
  - Jika `leaves.manage`: pilih Student/Employee, subject picker (student: nama/NIS/NISN/kelas aktif; employee: nama/NUPTK/tipe), jenis sesuai subject, tanggal, alasan, lampiran.
  - Jika hanya `leaves.request`: subjek terkunci ke pegawai tertaut; jenis employee; tanpa picker subjek.
  - Tombol Submit/Save Draft; validasi field server; `422` ditampilkan per field; `409` Reload Latest.
- Detail: status, riwayat keputusan, lampiran download, tombol Submit/Cancel (08a) dan Approve/Reject/Cancel Approved (08b) sesuai ability/status.
- Semua label English, layout 390 px, keyboard/aria, tanpa tombol dummy. Aksi yang belum tersedia di 08a tidak dirender; 08b menambahkannya.

### 4.10 Audit, privasi, dan retensi

- `LeaveRequest` allowlist Activity: `subject_type`, `student_id`, `employee_id`, `school_year_id`, `student_rombel_id`, `type`, `start_date`, `end_date`, `status`, `version`, `requested_by`, `submitted_at/by`, `decided_at/by`, `cancelled_at/by`. **Tidak** memasukkan `reason`.
- `LeaveDecision` immutable; Activity (bila dicatat) hanya action/from/to/actor, **tanpa** `reason`.
- `LeaveAttachment` allowlist: `leave_request_id`, `mime_type`, `size_bytes`, `deleted_at`; **tanpa** `original_name`, `path`, `checksum`.
- Alasan izin/keputusan hanya dapat dibaca oleh `leaves.view` dan pemilik/pembuat; tidak diekspos ke picker atau rekap publik.
- Tidak ada NIK/alamat/kontak di API izin; nama subjek dan kelas diperlukan untuk operasional.
- Tidak ada email/WhatsApp; tidak ada pengiriman data ke layanan luar.
- File dan baris lampiran dipertahankan setelah soft delete; penghapusan permanen menunggu DEC-13.

---

## 5. Keputusan yang perlu pemilik (D1–D12)

Rekomendasi ditulis sebagai default. **Belum ada yang disetujui.** Persetujuan pemilik akan dicatat sebagai **DEC-35 APPROVED** dan menutup **DEC-07**.

| # | Pertanyaan | Rekomendasi | Alternatif / dampak |
|---|---|---|---|
| **D1** | Subfase dan urutan yang diotorisasi? | Rencana mencakup **seluruh Fase 08** agar model/API stabil, tetapi implementasi dimulai **08a saja**. Setelah 08a selesai dan direview, 08b dimulai dengan instruksi terpisah. 08a = pengajuan; 08b = keputusan + rekap. | Implementasi 08a dan 08b sekaligus (risiko besar, sulit direview); hanya 08a tanpa rencana 08b (data model bisa berubah). |
| **D2** | Jenis izin apa yang dipakai? | Config kode: siswa `sick`, `permission`, `other`; pegawai `annual`, `sick`, `maternity`, `official_duty`, `other`. Label English, kode stabil, tanpa CRUD master. Lampiran opsional semua jenis; `max_days` per jenis. | Satu jenis generik (tidak sesuai kebutuhan); CRUD jenis (menambah master/otorisasi yang tidak diminta); menambah jenis lain (sebutkan saat approval). |
| **D3** | Bagaimana subjek dan eligibility ditentukan? | Siswa: `student_id` + snapshot `school_year_id`/`student_rombel_id`; harus aktif (tidak soft-deleted, punya keanggotaan aktif tahun aktif). Pegawai: `employee_id`; tidak soft-deleted; `exit_date` null atau ≥ `start_date`; semua tipe pegawai. Self-service memakai `employees.user_id` tepat, tanpa fallback NIK. | Memakai NIK sebagai identitas (rawan ambigu); mengizinkan siswa tanpa keanggotaan aktif; menyimpan snapshot PII lengkap. |
| **D4** | Siapa yang boleh mengajukan? | Siswa hanya `leaves.manage` (Admin/Operator) atas nama. Pegawai self dengan `leaves.request` + akun tertaut; atas nama pegawai lain dengan `leaves.manage`. Guru default hanya self. Pembuat draft dapat edit/submit/cancel; `leaves.manage` juga. | Guru boleh mengajukan siswa (perlu ability/scope wali kelas baru); siswa punya akun (di luar scope); semua staf boleh atas nama (terlalu luas). |
| **D5** | Siapa approver, bagaimana delegasi dan self-approval? | Approve/reject/cancel-approved hanya `leaves.approve`; default Kepala Sekolah. Admin teknis tidak otomatis; Wakil Kesiswaan default view-only. Larangan self-decision (pembuat/subjek sendiri) `403`. Delegasi lewat pemberian ability/peran di UI Roles, tanpa tabel delegate. | Wali kelas approver (perlu scope kelas + ability baru); delegasi per-request (tabel/endpoint baru); admin otomatis approve (melanggar DEC-03). |
| **D6** | Aturan tanggal dan overlap? | Full-day inklusif WIB; `start <= end`; maks 30 hari per jenis (maternity/paternity 90); siswa harus di dalam tahun ajaran aktif; pegawai bebas tahun ajaran dan boleh susulan. Overlap ditolak untuk subjek sama berstatus submitted/approved; draft/rejected/cancelled tidak memblokir; tanggal berdampingan boleh. Cek saat submit dan approve. | Mengizinkan overlap (data ganda); memblokir draft (tidak bisa menyusun rencana); menolak tanggal lampau (sakit susulan tidak tercatat). |
| **D7** | Status dan aturan pembatalan? | `draft → submitted → approved/rejected`, dengan `cancelled` dari draft/submitted oleh pembuat/manage dan dari approved oleh approver. Rejected/cancelled final; tidak ada reopen/edit setelah submit; koreksi = pengajuan baru. Transisi atomik + `expected_version`; paralel kalah `409`. | Menghapus draft (kehilangan jejak); reopen rejected (riwayat ambigu); approve → reject (keputusan tidak stabil). |
| **D8** | Lampiran dan privasi file? | Opsional, maks 3, 5 MB, PDF/JPG/JPEG/PNG, disk privat `attachments/leaves/{id}`, UUID + SHA-256, soft delete, unduhan berizin tanpa `Content-Disposition` (pola 07c). Upload/hapus hanya draft/submitted. Validasi file diekstrak bersama surat; regresi surat wajib. | Lampiran wajib (menghambat pengajuan); ukuran 10 MB (boros); URL publik (tidak aman); menyimpan alasan/lampiran di audit (PII). |
| **D9** | Ability/peran baru? | Tambah `leaves.request` (non-sensitif) + peran awal `guru` (Teacher) yang hanya memilikinya; tambahkan juga ke 4 peran awal. `leaves.view/manage/approve` tetap. Admin tidak otomatis approve. Migration additive/idempotent, tanpa mengubah peran lama. | Tanpa peran guru (admin membuat manual; guru tidak bisa self-service sampai dikonfigurasi); memberi guru `leaves.manage` (terlalu luas); endpoint self tanpa ability (melanggar aturan repo). |
| **D10** | Kontrak API/UI dan rekap? | 16 route (13 08a + 3 08b) seperti §4.8; object-level Policy; `409` Reload Latest; UI satu halaman dengan tab Requests/My Leaves/Recap; English, 390 px, keyboard; rekap agregat per periode/jenis/status/kelas/pegawai, tanpa ekspor. | Endpoint terpisah per tab (duplikasi); UI tanpa rekap (tidak memenuhi 08b); ekspor CSV (scope tambahan); notifikasi (non-goal). |
| **D11** | Audit dan privasi? | Allowlist Activity tanpa alasan/nama file/path/hash; keputusan immutable; alasan hanya untuk `leaves.view` + pemilik/pembuat; tanpa NIK/alamat/kontak di API; file dipertahankan sampai DEC-13. | Mencatat alasan ke audit (PII/medis); mengekspos path/hash; menghapus file saat soft delete (melanggar retensi). |
| **D12** | UAT dan non-goals? | Lingkungan baru **`.uat-phase08`** (SQLite; API `127.0.0.1:18026`, SPA `127.0.0.1:14026`, cookie `school_phase08_session`, launcher `scripts/phase08-uat.php` dengan `init`/`prepare`/`prepare-leaves`/`status`). Fixture ≥100 izin sintetis, 120 siswa, 20 pegawai, akun operator/kepsek/guru/waka, lampiran sintetis. Server dihentikan setelah penerimaan (DEC-27). Non-goals §3.3. | Memakai ulang `.uat-phase07` (mencampur domain); fixture <100 (tidak sesuai kebijakan data sintetis); server dibiarkan berjalan (melanggar DEC-27). |

**Ringkasan pertanyaan terbuka:**

| Q | Jawaban yang diusulkan |
|---|---|
| Q1 — apakah izin siswa dan guru dipisah? | Satu domain `leave_requests` dengan `subject_type`; aturan aktor/approver berbeda sesuai D3–D5. |
| Q2 — siapa mengajukan izin siswa? | Staf `leaves.manage`; siswa tidak punya akun (D3/D4). |
| Q3 — bagaimana guru mengajukan? | Self-service `leaves.request` dengan akun tertaut; guru default tidak bisa atas nama orang lain (D4/D9). |
| Q4 — siapa approver? | `leaves.approve`, default Kepala Sekolah; tanpa self-approval (D5). |
| Q5 — bagaimana delegasi? | Lewat Roles & Abilities; tanpa delegate per-request di v1 (D5). |
| Q6 — bagaimana overlap? | Tolak untuk subjek sama yang submitted/approved; draft boleh (D6). |
| Q7 — apakah izin yang sudah disetujui bisa dibatalkan? | Bisa oleh `leaves.approve` dengan alasan; keputusan lama tetap di riwayat (D7). |
| Q8 — apakah alasan/lampiran sensitif? | Ya; akses terbatas, tidak masuk audit, file privat (D8/D11). |
| Q9 — apakah absensi/saldo cuti berubah? | Tidak; non-goal eksplisit (D12/§3.3). |
| Q10 — apakah perlu dependency baru? | Tidak; tidak ada PDF/email/queue untuk izin. |

---

## 6. Urutan pekerjaan yang diusulkan

| Langkah | Pekerjaan | Hasil yang dapat diverifikasi |
|---|---|---|
| S0 | Preflight: audit HEAD, pastikan tidak ada tabel/route izin, konfirmasi D1–D12, siapkan DB/storage uji terisolasi | Baseline dan gate jelas; belum ada perubahan kode |
| S1 | Migration additive `leave_requests`, `leave_decisions`, `leave_attachments`; model, cast, relasi, allowlist audit; migration ability `leaves.request` + role `guru` | Tabel/ability ada; domain lama tidak berubah; migration idempotent |
| S2 | `LeaveRequestService` (create/update/submit/cancel), validasi subjek/type/tanggal/overlap, lock + versi; policy/ability; subject picker; FormRequest/controller/route 08a | 13 route 08a; create/edit/submit/cancel; self vs on-behalf; `409`/`422` |
| S3 | `LeaveAttachmentService` + ekstraksi validator bersama; upload/download/delete; header keamanan; regresi lampiran surat | Lampiran privat, izin akses, surat tidak regresi |
| S4 | UI `Leaves.vue` + dialog + tab + menu/router; state draft/submitted/cancelled; filter/detail | English, 390 px, ability-aware, tanpa tombol dummy |
| S5 | Tes 08a T1–T20 + regresi surat + Pint + `route:list` + build/lint | Matriks tes PASS/NOT RUN tercatat |
| S6 | UAT `.uat-phase08` 08a + dokumentasi 08a + commit/push paket review | UAT siap review; data sintetis ≥100; paket review dapat dibaca di GitHub |
| S7 | **Setelah instruksi pemilik:** 08b approve/reject/cancel-approved, `leave_decisions` append-only, rekap endpoint/UI | 3 route 08b; paralel/self/`409`; rekap benar |
| S8 | Tes 08b + regresi penuh yang relevan + build/lint | Matriks 08b PASS/NOT RUN |
| S9 | UAT 08b + dokumentasi + commit/push paket review | Paket review 08b dapat dibaca di GitHub |

Setelah S6 (08a) berhenti untuk review pemilik; 08b tidak dimulai otomatis. Setelah S9 (08b) selesai, berhenti lagi untuk review. Tidak ada merge/deploy/migrasi operasional.

---

## 7. Matriks tes yang direncanakan

| ID | Skenario | Expected |
|---|---|---|
| T1 | Guest/akun nonaktif pada semua route izin | `401`/`403`; tidak ada mutasi |
| T2 | Matriks ability tiap endpoint (view/request/manage/approve) | `403` untuk yang tidak berhak; data decoy tidak berubah |
| T3 | Operator membuat izin siswa | `200` draft; subjek/kelas/tahun tersimpan |
| T4 | Guru default membuat izin siswa atau pegawai lain | `403`; tidak ada baris baru |
| T5 | Guru self request dengan akun tertaut | `200` draft; subjek = pegawai sendiri |
| T6 | Self request tanpa tautan / tautan ambigu | `403`/`422`; tidak memakai NIK fallback |
| T7 | Siswa arsip/tanpa keanggotaan aktif/tanpa tahun aktif | `422`; tidak ada baris |
| T8 | Pegawai arsip/exit_date sebelum start | `422` |
| T9 | Type tidak cocok dengan subject_type | `422` |
| T10 | Tanggal start > end, melebihi max hari, di luar tahun ajaran siswa | `422`; batas WIB diuji dengan waktu tetap |
| T11 | Alasan terlalu pendek/panjang | `422` |
| T12 | Edit draft oleh pembuat/manage; edit submitted | `200`/`409`; versi naik hanya saat sukses |
| T13 | Submit tanpa field wajib; submit valid | `422`/`200`; `submitted_*` terisi |
| T14 | Overlap submitted/approved; tanggal berdampingan; draft/rejected/cancelled | `422` untuk overlap; `200` untuk kasus lain |
| T15 | Dua submit paralel untuk subjek sama | Satu sukses, satu `422`/`409`; tidak ada dua submitted overlap |
| T16 | Cancel draft/submitted oleh pembuat/manage; user lain | `200`/`403`; status dan versi benar |
| T17 | Approve/reject oleh approver; pembuat/subjek sendiri | `200`/`403`; `leave_decisions` bertambah |
| T18 | Dua approve paralel; versi basi | Satu `200`, satu `409`; status final konsisten |
| T19 | Reject/cancel tanpa alasan; approve dengan alasan | `422`/`200`; alasan tersimpan |
| T20 | Cancel approved oleh approver; cancel rejected | `200`/`409`; riwayat tetap |
| T21 | Riwayat keputusan immutable | Update/delete ditolak; urutan/aktor benar |
| T22 | Upload: ekstensi/MIME mismatch, gambar palsu, >5 MB, >3 file | `422`; tidak ada file/row |
| T23 | Download lampiran: pemilik, view, approver, user lain | `200` dengan header aman; `404` untuk yang tidak berhak |
| T24 | Upload/hapus setelah keputusan | `409`; file tetap |
| T25 | Audit allowlist | Tidak ada reason/nama file/path/hash; status/versi/aktor ada |
| T26 | Rekap: filter periode/status/jenis/kelas/pegawai; tanggal inklusif | Angka sesuai; tidak ada kebocoran lintas tahun |
| T27 | Tidak ada efek samping absensi/saldo cuti | Tabel/domain lain tidak berubah; tidak ada writer baru |
| T28 | Regresi surat 07a/07b/07c setelah ekstraksi validator | Semua tes surat lulus; header/unduhan tidak berubah |
| T29 | Route order/IDOR: `/mine`, `/options`, `/recap`, `/{leave}` | Route statis tidak tertangkap; objek tidak terlihat `404` |
| T30 | Build/lint frontend + route guard ability OR | Build sukses; guru hanya melihat My Leaves; 390 px tanpa overflow |

Tambahkan regresi yang benar-benar terdampak. Untuk tes mutasi, assert target berubah dan decoy tidak berubah. Tes rollback harus memicu kegagalan, bukan hanya jalur sukses.

---

## 8. Rencana UAT

- Lingkungan baru **`.uat-phase08`** (SQLite, storage/runtime terisolasi), API `127.0.0.1:18026`, SPA `127.0.0.1:14026`, cookie `school_phase08_session`, launcher `laravel-api/scripts/phase08-uat.php` dengan perintah `init`, `prepare`, `prepare-leaves`, `status`.
- Guard isolasi mengikuti pola `phase07-uat.php:29-40`: menolak override environment, cache config/route, dan memastikan `APP_ENV=uat`, SQLite di `.uat-phase08`, URL/port/cookie sesuai.
- Fixture sintetis:
  - 120 siswa (≥100 dengan keanggotaan aktif pada 3 rombel), 20 pegawai (12 Pendidik/8 Tenaga Kependidikan), 4 akun: operator, kepala sekolah, guru (tertaut ke pegawai Pendidik), wakil kesiswaan (view-only).
  - 1 tahun ajaran aktif dengan `start_date`/`end_date`, 3 rombel, 1 semester.
  - ≥100 `leave_requests` lintas status/jenis/subject, termasuk draft, submitted, approved, rejected, cancelled; ≥20 lampiran PDF/gambar sintetis; riwayat keputusan.
  - Perintah `prepare-leaves` idempotent; tidak me-reset data; tidak menyentuh `.uat-phase05/06/07` atau database operasional.
- Browser/API checklist (dijalankan bila browser tersedia; jika tidak, manual checklist):
  1. Operator membuat izin siswa draft + submit.
  2. Guru membuat self request; guru ditolak saat mencoba atas nama siswa/pegawai lain.
  3. Overlap ditolak; tanggal berdampingan diterima.
  4. Kepala sekolah approve/reject; alasan tampil di riwayat; self-approval ditolak.
  5. Dua tab/dua request paralel → `409` + Reload Latest (bila dapat disimulasikan).
  6. Lampiran: upload, download, permission user lain, hapus sebelum keputusan, read-only setelah keputusan.
  7. Rekap filter periode/kelas/pegawai/status; angka benar; tanggal inklusif.
  8. Guru hanya melihat My Leaves; operator/kepsek melihat Requests; waka view-only.
  9. 390 px, keyboard, 0 console error (selain `409`/`422` yang disengaja).
- Setelah penerimaan pemilik, server UAT dihentikan (DEC-27) dengan verifikasi PID/command line/port; data `.uat-phase08` dipertahankan.

---

## 9. Dokumen yang diperbarui saat implementasi

- `docs/REQUIREMENTS.md` — FR-10 status/realisasi.
- `docs/ACCESS-CONTROL.md` — ability `leaves.request`, peran `guru`, route matrix, object scope, larangan self-decision.
- `docs/DATABASE.md` — tiga tabel additive, index, retensi.
- `docs/API-CONTRACTS.md` — 16 endpoint, filter, error, resource.
- `docs/DECISIONS.md` — DEC-35 APPROVED (setelah persetujuan) dan DEC-07 CLOSED; DEC-13 tetap OPEN.
- `CHANGELOG.md`, `reports/feature-update/PHASE-08A-REPORT.md`, `PHASE-08A-MANUAL-REVIEW.md`, `INDEX.md`, `HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-08-LEAVES.md`.
- `docs/TESTING.md` — perintah/hasil aktual, NOT RUN.
- Paket rencana ini sendiri hanya memperbarui dokumen status (lihat commit terpisah).

---

## 10. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| DEC-07 belum diputuskan; aturan siswa/guru bisa berbeda | D1–D12 eksplisit; implementasi berhenti sampai persetujuan |
| Guru tidak punya akun tertaut | Admin harus menautkan lewat UI 04b; self-service `403` dengan pesan; tidak memakai NIK ambigu |
| Race overlap/keputusan paralel | Lock baris subjek + transaksi + `expected_version`; uji SQLite; MariaDB race NOT RUN |
| Alasan izin mengandung data medis | Akses `leaves.view`/pemilik; tidak masuk audit; UI tidak mengekspos ke rekap |
| Lampiran berisi PII | Disk privat, nama UUID, validasi MIME, unduhan berizin, tanpa path publik |
| `Content-Disposition` memblokir unduhan lintas origin | Ikuti pola 07c (`X-Document-Filename`, tanpa header tersebut) |
| Ekstraksi validator merusak lampiran surat | Tes regresi 07a/07b/07c wajib; adapter mempertahankan perilaku |
| Migration ability menimpa konfigurasi admin | Idempotent; hanya menambah pivot yang belum ada; tidak mengubah peran/ability eksisting |
| Suite penuh menyentuh `school_access_uat` | Gate `MariaDbIntegrationTest` sebelum suite penuh; suite izin dijalankan terpisah |
| Fixture UAT <100 atau mencampur fase | Wajib ≥100 baris sintetis; `.uat-phase08` baru; tanpa reset `.uat-phase07` |
| Tidak ada absensi/saldo cuti | Non-goal eksplisit; tes memastikan tidak ada writer baru |

---

## 11. Kriteria penerimaan paket rencana

- [ ] Pemilik menyetujui D1–D12 (atau memberikan perubahan); dicatat sebagai **DEC-35 APPROVED**.
- [ ] **DEC-07 CLOSED** setelah persetujuan; **DEC-13 tetap OPEN**.
- [ ] Tidak ada implementasi/migration/route/UI/UAT izin sebelum persetujuan.
- [ ] Baseline/HEAD, working tree, dan SHA paket rencana dicatat.
- [ ] Scope 08a/08b, API, data model, tes, UAT, non-goals, dan risiko dapat direview di GitHub.
- [ ] Paket rencana di-commit dan di-push ke `origin/main` sebagai dokumen saja.

---

## Lampiran A — Contoh payload (usulan, bukan kontrak final)

```json
{
  "subject_type": "student",
  "student_id": 42,
  "type": "sick",
  "start_date": "2026-11-03",
  "end_date": "2026-11-04",
  "reason": "Fever and doctor-advised rest.",
  "expected_version": null
}
```

```json
{
  "id": 17,
  "subject_type": "student",
  "subject": {"id": 42, "name": "Synthetic Student 042", "nis": "20260042", "class": "X IPA 1", "school_year": "2026/2027"},
  "type": "sick",
  "type_label": "Sick Leave",
  "start_date": "2026-11-03",
  "end_date": "2026-11-04",
  "days": 2,
  "reason": "Fever and doctor-advised rest.",
  "status": "submitted",
  "version": 2,
  "requested_by": {"id": 3, "name": "School Operator"},
  "submitted_at": "2026-11-02T10:15:00+07:00",
  "decided_at": null,
  "decided_by": null,
  "attachments": [],
  "decisions": [],
  "can": {"edit": false, "submit": false, "cancel": true, "approve": false, "reject": false, "upload": true, "delete_attachment": true}
}
```

Payload final mengikuti resource yang disetujui pada implementasi; contoh ini tidak boleh dianggap kontrak yang sudah berlaku.
