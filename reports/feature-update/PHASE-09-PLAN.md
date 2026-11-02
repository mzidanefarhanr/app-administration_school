# Rencana Fase 09 — Subfase 09a: Metadata dan Versi Arsip Dokumen

- Tanggal: 2026-11-02 (WIB). Branch: `main`. Baseline audit: `aeca361` (pencatatan penerimaan 08b dan penghentian UAT). Paket rencana: commit dokumentasi setelah file ini.
- Status dokumen: **PLAN APPROVED — DEC-37 APPROVED (2026-11-02 WIB)** berdasarkan pernyataan pemilik **"Saya setuju D1-D10"**; implementasi 09a diotorisasi. Usulan keputusan: **DEC-37 APPROVED**.
- Otorisasi saat ini: **tidak ada implementasi Fase 09 yang diotorisasi**. 08a/08b sudah **OWNER APPROVED**; DEC-35/DEC-36 tidak berubah. Paket ini hanya rencana.
- Cakupan paket ini: rencana **09a — metadata, kategori, link domain, versi file/checksum, pencarian lintas domain berizin, dan download file berizin**. **Tidak ada kode, migration, route, model, service, request, controller, komponen, tes, fixture, atau UAT yang dibuat/diubah oleh paket rencana ini.**
- Gate: **DEC-13 (retensi data pribadi) tetap OPEN** dan menjadi prasyarat 09b; **09b tidak direncanakan rinci di sini** dan memerlukan rencana serta persetujuan terpisah. Merge/deploy/force-push, migrasi database operasional/MariaDB, perubahan dependency/lockfile, dan perubahan service/`.env` tidak diotorisasi.
- Dokumen acuan: `roadmap/phases/PHASE-09-ARCHIVES.md`, `docs/DECISIONS.md` (DEC-13, DEC-27), `docs/REQUIREMENTS.md` FR-11, `docs/DATABASE.md`, `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `docs/SECURITY-DATA.md`, `reports/feature-update/PHASE-08B-PLAN.md` (pola paket rencana), serta source 07a/07c/08a untuk pola file privat, ability, route, dan UAT.

---

## 1. Posisi dan prasyarat

| Prasyarat | Kondisi aktual 2026-11-02 | Status |
|---|---|---|
| Fase sebelumnya | 07a/07b/07c dan 08a/08b **IMPLEMENTED + agent-verified + OWNER APPROVED**; UAT fase yang lulus sudah dihentikan (DEC-27); `.uat-phase08` dipertahankan | Terpenuhi |
| Modul arsip | **Belum ada** model, migration, service, controller, route, ability `archives.*`, halaman, atau menu arsip; pencarian `archive` hanya menemukan konsep arsip siswa/pegawai yang tidak terkait | Greenfield |
| File privat | `PrivateUploadValidator` sudah memvalidasi PDF/JPG/JPEG/PNG, MIME dari isi file, satu ekstensi, gambar valid, dan ukuran; nama file dibuat server; pola path UUID + sha256 sudah dipakai 07a/07c/08a | Dapat dipakai ulang |
| Download | Pola 07c/08a: `StreamedResponse`, `X-Document-Filename` + `Access-Control-Expose-Headers`, tanpa `Content-Disposition`; pola ini menghindari masalah Chrome lintas origin | Dapat dipakai ulang |
| Ability | `AbilityCatalog` belum memiliki `archives.*`; role awal: admin/operator manage-level, kepala/waka view-level, guru hanya `leaves.request` | Perlu ability baru + migration idempotent |
| Schema | Belum ada tabel arsip; tabel lampiran existing hanya `letter_attachments`, `leave_attachments`, `letter_documents` | Perlu dua tabel additive |
| Pola route | Route statis sebelum `/{id}`, `whereNumber`, middleware `can:<ability>`, policy object-level, `409` + `latest`, `422`, `404` untuk objek tidak terlihat | Dapat diikuti |
| Pola UI | Halaman `/public/*`, router `meta.requiresAbility`, menu `requires`, label English, 390 px, `can` dari server, dialog `409` Reload Latest | Dapat diikuti |
| Pola UAT | Runtime `.uat-phaseNN`, SQLite/storage terisolasi, port API/SPA khusus, cookie unik, launcher `scripts/phaseNN-uat.php`, command `init`/`prepare-*`/`status` idempotent, server dihentikan setelah penerimaan | Dapat diikuti |
| Tes | `phpunit.xml` memakai SQLite in-memory terisolasi; `CreatesAccessFixtures` menyediakan role/ability dari katalog; helper 08a dapat dijadikan acuan | Dapat dipakai |
| Keputusan | DEC-13 **OPEN**; DEC-10/DEC-12 tidak menghambat 09a; tidak ada DEC untuk arsip | Perlu DEC-37 |

Seluruh kondisi di atas diverifikasi dari source, konfigurasi, tes, dan launcher aktual. Tidak ada data operasional yang diakses.

---

## 2. Temuan audit (fakta kode)

### 2.1 Belum ada modul arsip

- Tidak ada model/migration/service/controller/route `archive*` di `laravel-api`; tidak ada halaman/menu arsip di `vue-app`.
- `AbilityCatalog::definitions()` belum memuat `archives.view`/`archives.manage`: `laravel-api/app/Support/AbilityCatalog.php:22-67`.
- `routes/api.php` tidak memiliki grup `/api/archives`; route surat (37) dan izin (16) tetap menjadi kontrak yang berjalan.
- `roadmap/phases/PHASE-09-ARCHIVES.md:3` menyatakan fase **NOT STARTED**; 09a adalah subfase pertama.

**Implikasi:** 09a adalah greenfield dengan dua tabel baru, ability baru, dan halaman baru; tidak ada perubahan pada tabel/route/UI domain existing.

### 2.2 Pola file privat sudah matang tetapi belum generik

- `PrivateUploadValidator` memusatkan validasi MIME/ekstensi/ukuran dan sanitasi nama: `laravel-api/app/Services/Files/PrivateUploadValidator.php:18-96`.
- `LeaveAttachmentService` menyimpan file di disk privat, menghitung sha256, melakukan kompensasi `discard()`, dan mengunduh dengan header pola 07c: `laravel-api/app/Services/Leaves/LeaveAttachmentService.php:40-118`.
- `LetterAttachmentService` memakai pola serupa tetapi masih memakai `Content-Disposition`; ini bug lintas origin 07a/07b yang **di luar scope 09a** dan tidak boleh ditiru untuk arsip.
- `config/backup.php:19` memindai `storage/app/attachments`; path `attachments/archives` otomatis tercakup tanpa perubahan kode backup, tetapi manifest/narasi harus diverifikasi saat implementasi.

**Implikasi:** 09a memakai `PrivateUploadValidator` + service file baru dengan pola 07c/08a; tidak menambah dependency. Download arsip memakai `X-Document-Filename`, bukan `Content-Disposition`.

### 2.3 Ability/role dan migration idempotent

- `AbilityCatalog::initialRoles()` memetakan ability per role: admin/operator manage-level, kepala/waka view-level, guru minimal: `laravel-api/app/Support/AbilityCatalog.php:80-150`.
- Pola migration 08a menambahkan ability baru dan attach ke role yang belum memilikinya tanpa mengubah konfigurasi admin: `laravel-api/database/migrations/2029_08_04_000004_add_leaves_request_ability_and_guru_role.php:17-51`.
- Tes `CreatesAccessFixtures` membuat ability/role dari katalog dan hanya mengisi role yang belum punya ability: `laravel-api/tests/Support/CreatesAccessFixtures.php:31-95`.

**Implikasi:** tambah dua definisi ability + perluasan `initialRoles`, lalu satu migration additive/idempotent untuk database existing. Tidak ada role baru.

### 2.4 Konvensi route/UI/UAT

- Route izin memakai route statis sebelum `/{leave}`, `whereNumber`, dan middleware `can:`: `laravel-api/routes/api.php:283-309`.
- Router SPA memakai `meta.requiresAbility`; contoh `/public/leaves` memakai array OR `['leaves.view','leaves.request']`: `vue-app/src/router/index.js:103-108`.
- Menu memakai grup + `requires`; grup Letters/Leaves menjadi contoh: `vue-app/src/layout/AppMenu.vue:50-66`.
- Launcher 08 memakai isolation guard, `init`, `prepare`, `prepare-*`, `status`, dan command allowlist: `laravel-api/scripts/phase08-uat.php:1-140`.
- `.gitignore` root sudah memuat `.uat-phase04b/`, `.uat-phase05/`, `.uat-phase07/`, `.uat-phase08/`, tetapi belum `.uat-phase09/`; `vue-app/.env.uat` di-ignore oleh `vue-app/.gitignore:10`.

**Implikasi:** 09a menambah halaman `/public/archives` + grup menu Archives, launcher `scripts/phase09-uat.php`, dan entri `.uat-phase09/` saat implementasi. Konfigurasi frontend UAT memakai env var `VITE_API_BASE_URL` (jangan commit `.env.uat`).

### 2.5 Tes dan batas suite penuh

- `phpunit.xml:24-28` memakai SQLite in-memory dan disk `testing`; tes 08 memakai `RefreshDatabase`, `CreatesAccessFixtures`, `Storage::fake('local')`, dan `Carbon::setTestNow` WIB: `laravel-api/tests/Feature/Leaves/LeaveRequestTest.php:24-38`.
- Suite penuh tetap **NOT RUN** karena `Tests\Feature\Backup\MariaDbIntegrationTest` membaca `school_access_uat`; batas akses ini tidak berubah dan tidak boleh dilanggar.

**Implikasi:** tes 09a dijalankan terpisah dengan SQLite in-memory; suite penuh tetap NOT RUN sampai tes backup digate.

---

## 3. Scope dan non-goals

### 3.1 Scope 09a

1. **Metadata arsip**: kategori config, judul, nomor dokumen opsional/non-unik, tanggal dokumen, masa berlaku opsional, deskripsi opsional.
2. **Link domain**: subjek `student`, `employee`, `letter`, `leave`, atau tanpa subjek (dokumen umum); whitelist dan validasi keberadaan.
3. **File version/checksum**: file pertama wajib saat create; versi baru immutable; path privat UUID; sha256 tersimpan internal; kompensasi kegagalan agar tidak ada orphan.
4. **Pencarian lintas domain berizin**: daftar/filter/detail hanya menampilkan objek yang domain-nya boleh dilihat aktor; filter eksplisit tanpa izin ditolak.
5. **Download berizin**: current dan versi lama; streamed, privat, header aman; **bukan preview inline**.
6. **UI**: halaman `/public/archives`, menu, daftar/filter, create/edit, detail, riwayat versi, download, `409` Reload Latest.
7. **Tes dan UAT**: T1–T13, regresi Letters/Leaves, Pint/build/lint, `.uat-phase09` dengan fixture ≥120 dokumen dan ≥150 versi.
8. **Dokumentasi**: kontrak API/DB/akses/keamanan, laporan, checklist manual.

### 3.2 Ditunda ke 09b (tidak dikerjakan pada 09a)

- **Preview inline** (PDF/gambar di browser), audit akses download, expiry task di dashboard, pengingat, arsip/restore dokumen arsip, retensi/pemusnahan.
- **Retensi/pemusnahan** memerlukan **DEC-13**; 09b memerlukan rencana dan persetujuan terpisah. 09a tidak menghapus file/versi dan tidak menambahkan purge otomatis.
- Pencarian full-text/OCR, ekspor CSV/PDF, notifikasi email/WhatsApp, integrasi eksternal, dan migrasi lampiran existing ke arsip.

### 3.3 Non-goals 09a

- Tidak ada perubahan pada tabel/route/UI surat, izin, siswa, atau pegawai; tidak ada migrasi data lampiran existing.
- Tidak ada soft delete/archived_at pada 09a; tidak ada route delete/restore/archive.
- Tidak ada per-document ACL, delegasi, approval arsip, atau signing.
- Tidak ada dependency/lockfile baru, perubahan service/`.env`, atau migrasi database operasional/MariaDB.
- Tidak ada merge/deploy/force-push/rewrite history.

---

## 4. Desain yang diusulkan

### 4.1 Model data (dua tabel additive)

**`archive_documents`**

| Kolom | Tipe | Aturan |
|---|---|---|
| `id` | bigint PK | — |
| `category` | string(40), index | Wajib; kunci dari `config/archives.php` |
| `title` | string(200) | Wajib; 3–200 karakter setelah trim |
| `document_number` | string(100), nullable, index | Opsional; **tidak unik**; kosong → null |
| `document_date` | date, index | Wajib; format `Y-m-d` |
| `valid_from` | date, nullable | Opsional; harus berpasangan dengan `valid_until` |
| `valid_until` | date, nullable, index | Opsional; `>= valid_from` bila keduanya ada |
| `description` | text, nullable | Opsional; maks 1.000 karakter |
| `subject_type` | string(20), nullable | `student`/`employee`/`letter`/`leave` atau null |
| `subject_id` | unsignedBigInteger, nullable | Wajib bila `subject_type` ada; keduanya null atau keduanya terisi |
| `current_version` | unsignedSmallInteger, default 0 | Jumlah versi terakhir; 1 setelah create |
| `version` | unsignedInteger, default 1 | Optimistic lock metadata/versi |
| `created_by` | FK users nullable, nullOnDelete | Aktor pembuat |
| `updated_by` | FK users nullable, nullOnDelete | Aktor perubahan terakhir |
| `timestamps` | — | created_at/updated_at |

Index komposit: `(subject_type, subject_id)`, `(category, document_date)`, `(valid_until)`. Tidak ada unique pada judul/nomor.

**`archive_file_versions`**

| Kolom | Tipe | Aturan |
|---|---|---|
| `id` | bigint PK | — |
| `archive_document_id` | FK `archive_documents`, restrictOnDelete | Wajib |
| `version_number` | unsignedSmallInteger | Wajib; unik per dokumen |
| `original_name` | string(255) | Nama asli tersanitasi; ditampilkan ke viewer berizin |
| `path` | string(500) | Path privat; **tidak pernah diekspos** |
| `mime_type` | string(100) | Hasil deteksi server |
| `size_bytes` | unsignedBigInteger | Ukuran aktual |
| `checksum_sha256` | char(64) | SHA-256; **tidak diekspos** |
| `note` | string(500), nullable | Catatan versi opsional |
| `uploaded_by` | FK users nullable, nullOnDelete | Aktor pengunggah |
| `created_at` | timestamp | Append-only; tidak ada `updated_at` |

Constraint unik: `(archive_document_id, version_number)`. Model memblokir update/delete versi (pola `LeaveDecision`).

**Relasi model:** `ArchiveDocument` hasMany `versions()` (urut `version_number`), hasOne `latestVersion()` (`latestOfMany('version_number')`), belongsTo `creator`/`updater`. `ArchiveFileVersion` belongsTo `document`/`uploader`. Subjek tidak memakai relasi polimorfik; diselesaikan oleh service whitelist.

### 4.2 Metadata, kategori, dan masa berlaku

**Kategori config (`config/archives.php`), label English:**

| Kode | Label |
|---|---|
| `student_record` | Student Record |
| `employee_record` | Employee Record |
| `letter` | Letter |
| `leave` | Leave |
| `school_document` | School Document |
| `other` | Other |

- Kategori tetap di config; tanpa master CRUD. Validasi `Rule::in(array_keys(config('archives.categories')))`.
- `document_number` opsional dan tidak unik karena arsip menerima nomor eksternal yang dapat berulang.
- `document_date` wajib; tidak ada pembatasan masa depan yang arbitrer (dokumen dapat bertanggal mendatang); validitas diatur `valid_from`/`valid_until`.
- `valid_from`/`valid_until` harus berpasangan; `valid_until >= valid_from`.
- **Status validitas dihitung** (bukan kolom), memakai tanggal WIB:
  - `none`: tidak ada `valid_until`;
  - `upcoming`: `valid_from > hari ini`;
  - `expired`: `valid_until < hari ini`;
  - `expiring_soon`: `hari ini <= valid_until <= hari ini + 30 hari`;
  - `valid`: selain di atas.
- `expiring_soon_days = 30` di config. Tidak ada dashboard/pengingat pada 09a.
- `description` opsional 0–1.000; tidak masuk audit.

### 4.3 Link domain dan visibilitas lintas domain

**Whitelist subjek (`config/archives.php`):**

| `subject_type` | Model | Ability domain | Label picker |
|---|---|---|---|
| `student` | `Student` (withTrashed) | `students.view` | nama + NIS |
| `employee` | `Employee` (withTrashed) | `employees.view` | nama + NUPTK |
| `letter` | `Letter` | `letters.view` | `#agenda` + perihal/status |
| `leave` | `LeaveRequest` | `leaves.view` | `#id` + jenis/status |

**Aturan link:**

- `subject_type` dan `subject_id` harus **keduanya null** (dokumen umum) atau **keduanya terisi**.
- `subject_type` di luar whitelist → `422`.
- `subject_id` tidak ditemukan pada model yang sesuai → `422` (termasuk type/id yang tidak cocok).
- Subjek soft-deleted (siswa/pegawai) **boleh** ditautkan untuk arsip historis; picker menandai `archived: true`.
- Surat/izin dengan status void/cancelled **boleh** ditautkan; arsip bersifat historis.
- **Link subjek immutable setelah create.** Koreksi link dilakukan dengan membuat dokumen arsip baru; update metadata tidak menerima `subject_type`/`subject_id` ( jika dikirim → `422`).
- Tidak ada input nama tabel/model bebas; tidak ada arbitrary type/id.

**Visibilitas baca:**

- Dokumen umum: cukup `archives.view`.
- Dokumen bertaut: `archives.view` **dan** ability domain subjek.
- List/search **mengecualikan** dokumen yang domain-nya tidak boleh dilihat aktor.
- Filter eksplisit `subject_type`/`subject_id` yang domain-nya tidak boleh dilihat → `403`.
- Akses langsung ke dokumen yang domain-nya tidak terlihat → `404` (tidak membocorkan keberadaan).
- Create/update/add version untuk dokumen bertaut memerlukan `archives.manage` + ability domain; tanpa ability domain → `403`.
- Tidak ada per-document ACL atau delegasi pada 09a.

### 4.4 File, versi, dan kompensasi kegagalan

- Format: **PDF/JPG/JPEG/PNG**, maksimum **10 MB** per file, **satu file per versi**.
- Disk privat `local` (`storage/app`), path `attachments/archives/{document_id}/{uuid}.{ext}`; tidak ada URL publik.
- Nama file dibuat server (UUID); `original_name` disanitasi; MIME dideteksi server via `PrivateUploadValidator`.
- File pertama **wajib** saat create. Versi baru menambah baris `archive_file_versions` dan menaikkan `current_version`/`version`.
- Versi lama **tidak diubah/dihapus**; model memblokir update/delete; tidak ada route delete.
- `sha256` dihitung setelah file ditulis; disimpan internal; tidak pernah masuk respons/audit.
- **Kompensasi:** file ditulis di dalam `DB::transaction` setelah baris dokumen/versi siap; setiap `Throwable` → rollback + `discard(path)`. Validasi file dilakukan sebelum menulis. Tidak ada orphan untuk kegagalan yang tertangani.
- **Batas residual:** proses yang mati keras di antara tulis file dan commit DB dapat meninggalkan file tanpa baris. 09a tidak menambahkan sweeper background; rekonsiliasi orphan dapat menjadi bagian operasional/09b dan harus dicatat sebagai risiko.
- Download:
  - `StreamedResponse`, `Content-Type` tersimpan, `Content-Length`, `X-Document-Filename` tersanitasi, `Access-Control-Expose-Headers: X-Document-Filename`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: sandbox; default-src 'none'`, `Cache-Control: private, no-store`.
  - **Tanpa `Content-Disposition`** (pola 07c/08a).
  - File hilang → `404` JSON "Archive file not found." tanpa path.
  - Versi harus milik dokumen; versi dari dokumen lain → `404`.
- Retensi/penghapusan permanen **tidak ada** sampai DEC-13/09b.

### 4.5 Ability dan role

Tambahkan dua definisi di `AbilityCatalog::definitions()`:

- `archives.view` — grup `archives`, deskripsi "Melihat arsip dokumen.", non-sensitif.
- `archives.manage` — grup `archives`, deskripsi "Mengelola arsip dokumen.", non-sensitif.

Perluas `initialRoles()`:

| Role | `archives.view` | `archives.manage` |
|---|---|---|
| `admin-sistem` | ya | ya |
| `operator-sekolah` | ya | ya |
| `kepala-sekolah` | ya | tidak |
| `wakil-kesiswaan` | ya | tidak |
| `guru` | tidak | tidak |

- Tidak ada role baru; tidak ada ability approve/sign/export arsip.
- Migration baru `2029_09_01_000003_add_archive_abilities.php` memakai `firstOrCreate` + attach idempotent, pola 08a; `down()` hanya melepas/menghapus dua ability ini.
- Pemberian ke role lain adalah delegasi eksplisit melalui Roles & Abilities, bukan bypass di controller.
- Server tetap menegakkan ability + visibilitas domain; menyembunyikan tombol bukan perlindungan.

### 4.6 Kontrak API (9 route)

| # | Method | Endpoint | Ability/Policy | Body/Query | Ringkas |
|---|---|---|---|---|---|
| 1 | GET | `/api/archives/options` | `archives.view` | — | Kategori, tipe subjek yang terlihat, status validitas, batas file, `can` |
| 2 | GET | `/api/archives/subjects/{type}` | `archives.view` + ability domain | `q` opsional, `limit` | Picker subjek minimal; `type` whitelist |
| 3 | GET | `/api/archives` | `archives.view` | `q`, `category`, `subject_type`, `subject_id`, `validity`, `date_from`, `date_to`, `page`, `per_page` | Daftar server-side + visibilitas domain |
| 4 | POST | `/api/archives` | `archives.manage` + ability domain | `category`, `title`, `document_number?`, `document_date`, `valid_from?`, `valid_until?`, `description?`, `subject_type?`, `subject_id?`, `file` | Create + versi 1 |
| 5 | GET | `/api/archives/{archive}` | `archives.view` + ability domain | — | Detail + ringkasan subjek + `can` |
| 6 | PATCH/PUT | `/api/archives/{archive}` | `archives.manage` + ability domain | Metadata + `expected_version` | Update metadata; subjek immutable |
| 7 | GET | `/api/archives/{archive}/versions` | `archives.view` + ability domain | `page`, `per_page` | Riwayat versi (metadata) |
| 8 | POST | `/api/archives/{archive}/versions` | `archives.manage` + ability domain | `file`, `note?`, `expected_version` | Versi baru immutable |
| 9 | GET | `/api/archives/{archive}/versions/{version}/download` | `archives.view` + ability domain | — | Download streamed berizin |

**Route order dan parameter:**

- `options` dan `subjects/{type}` didaftarkan **sebelum** `GET /{archive}`.
- `{archive}` dan `{version}` memakai `whereNumber`; `{type}` memakai `whereIn` whitelist.
- `GET /{archive}` tidak boleh menangkap `options`, `subjects`, atau segmen statis lain.
- Middleware `auth:sanctum` + `active` + `can:`; policy object-level untuk `view`/`update`/`addVersion`/`download`.
- Tidak ada route delete/restore/archive/preview pada 09a.

**Query list:**

- `q` maks 100; mencari `title` dan `document_number` (bukan `description`).
- `category`, `subject_type`, `subject_id`, `validity` (whitelist status), `date_from`/`date_to` pada `document_date`; `date_from <= date_to`.
- `per_page` ∈ {10,20,50,100}, default 20; sort `document_date` desc lalu `id` desc.
- Filter `subject_id` tanpa `subject_type` → `422`; filter domain tidak berizin → `403`.
- `validity` dihitung di PHP (portabilitas SQLite/MariaDB), bukan kolom.

**Bentuk respons (ringkas):**

```json
{
  "success": true,
  "message": "Archive documents retrieved successfully.",
  "data": [
    {
      "id": 1,
      "category": "student_record",
      "category_label": "Student Record",
      "title": "Enrollment Archive 2026",
      "document_number": "ARC-001",
      "document_date": "2026-07-01",
      "valid_from": "2026-07-01",
      "valid_until": "2027-06-30",
      "validity_status": "valid",
      "subject": {
        "type": "student",
        "id": 10,
        "label": "Synthetic Student",
        "secondary": "NIS 0001",
        "archived": false
      },
      "current_version": 2,
      "versions_count": 2,
      "updated_at": "2026-11-02T10:00:00+07:00",
      "can": {"view": true, "update": true, "add_version": true, "download": true}
    }
  ],
  "meta": {"current_page": 1, "per_page": 20, "total": 120, "last_page": 6}
}
```

- Detail menambahkan `description` dan `versions_count`; riwayat versi diambil dari route #7.
- Respons **tidak** memuat `path`, `checksum_sha256`, atau isi file.
- `409` memuat `latest` aman: `id`, `version`, `current_version`, `updated_at`; tanpa path/checksum.
- `403` untuk ability kurang, filter domain tidak berizin, atau create/update link ke domain tidak berizin.
- `404` untuk dokumen/versi tidak terlihat, domain hidden, atau file hilang.
- `422` untuk validasi metadata, link arbitrary/tidak ditemukan, file tidak sah, atau `expected_version` hilang.

**Options (ringkas):**

- `categories` (value/label), `subject_types` hanya yang ability domain-nya dimiliki aktor, `validity_statuses`, `per_page_options`, `default_per_page`, `search_max_length`, `file` (max_kb/extensions), `expiring_soon_days`, `can.view`, `can.manage`.

**Picker subjek:**

- `q` opsional 0–100; limit 20 (config).
- Student: name/NIS; employee: name/NUPTK; letter: `#id`, subject, workflow status; leave: `#id`, type label, status, dates.
- Hanya field minimum; tanpa NIK/alamat/kontak/alasan surat-izin.
- Soft-deleted student/employee disertakan dengan `archived: true`.

### 4.7 UI

**Halaman `/public/archives`:**

- Router name `archives`, breadcrumb `Archives`, `meta.requiresAbility: 'archives.view'`.
- Menu grup baru **Archives** (requires `archives.view`) → item **Archive Documents** (ikon folder), setelah grup Leaves.
- Daftar: Title, Category, Subject, Number, Document Date, Validity, Version, Updated.
- Filter: Search, Category, Subject Type + Subject picker, Validity Status, Document Date From/To; Refresh/Reset.
- Aksi baris: View, Edit (jika `can.update`), Add Version (jika `can.add_version`), Download current (jika `can.download`).
- Tombol **New Archive Document** jika `can.manage`.

**Dialog create/edit:**

- Field: category, title, document number, document date, valid from/until, description.
- Subject: select None/Student/Employee/Letter/Leave; bila dipilih, remote picker dengan search; tipe yang tidak diizinkan tidak ditampilkan.
- Create: file wajib; edit: subjek read-only, file tidak diubah.
- Validasi klien mencerminkan aturan server; `422` ditampilkan per field.

**Dialog detail:**

- Metadata, badge validitas, kartu subjek, ringkasan file terkini.
- Tabel riwayat versi: version, original name, MIME, size, note, uploader, created at, Download per versi.
- Tanpa preview inline, tanpa delete/archive/restore.

**Dialog add version:**

- Info current version, file, note opsional; `expected_version` dari detail.
- Sukses → refresh detail/list; `409` → dialog konflik + **Reload Latest**.
- `404`/`403`/`422` ditampilkan jelas.

**Standar:** label English, 390 px, tabel scroll horizontal, dialog full-width, keyboard/aria, empty/loading/error state, tanpa tombol dummy/placeholder.

### 4.8 Audit, privasi, dan backup

- `ArchiveDocument` memakai `Loggable` dengan allowlist: `category`, `document_date`, `valid_from`, `valid_until`, `subject_type`, `subject_id`, `current_version`, `version`.
- **Tidak** masuk audit: `title`, `document_number`, `description`, `original_name`, `path`, `checksum_sha256`, `note`, `created_by`, `updated_by`.
- `ArchiveFileVersion` **tidak** memakai `Loggable`; baris immutable; perubahan `current_version` pada dokumen tercatat oleh Loggable.
- Download **tidak** diaudit pada 09a; audit akses download adalah 09b.
- Respons tidak mengekspos path/checksum; subject summary minimal tanpa NIK/alamat/kontak/alasan.
- Metadata title/number/description dapat diedit oleh manager tetapi tidak masuk audit (trade-off privasi vs ketertelusuran). Perubahan kategori/tanggal/validitas/subjek/versi tercatat.
- Path `attachments/archives` berada di bawah `storage/app/attachments` yang dipindai backup (`config/backup.php:19`); verifikasi manifest saat implementasi, tanpa perubahan kode backup yang diharapkan.
- Retensi/penghapusan permanen menunggu **DEC-13**; tidak ada purge pada 09a.

### 4.9 Transaksi dan konkurensi

- Create/update/add version berjalan dalam `DB::transaction`.
- Update metadata dan add version memakai `lockForUpdate` pada baris dokumen + `expected_version`.
- Versi baru memakai `current_version + 1`; unique constraint `(archive_document_id, version_number)` sebagai pengaman kedua.
- Versi basi/status tidak valid → `409` dengan `latest` aman; tanpa mutasi.
- Kegagalan file/DB → rollback + `discard`; tidak ada baris/versi setengah jadi.
- Race MariaDB/InnoDB dan multi-proses **NOT RUN** pada paket ini; akan dicatat sebagai NOT RUN saat implementasi jika tidak dapat diuji.

---

## 5. Keputusan yang perlu pemilik (D1–D10)

Rekomendasi ditulis sebagai default. **Belum ada keputusan yang dianggap disetujui sampai pemilik menyatakan persetujuan eksplisit.**

| # | Pertanyaan | Rekomendasi | Alternatif / dampak |
|---|---|---|---|
| **D1** | Scope dan otorisasi 09a? | Implementasikan **09a saja**: metadata, link domain, file version/checksum, pencarian lintas domain berizin, **download berizin**, UI/tes/UAT. 09b (preview, audit akses, expiry task, arsip/restore, retensi) memerlukan rencana terpisah; DEC-13 tetap OPEN. | Tanpa download di 09a (sesuai pembagian roadmap yang lebih ketat, tetapi versi tidak dapat diverifikasi end-to-end); seluruh Fase 09 sekaligus (melanggar satu-subfase); migrasi lampiran existing (risiko tinggi). |
| **D2** | Model data? | Dua tabel additive `archive_documents` + `archive_file_versions`; tanpa perubahan tabel existing; `current_version`/`version`; checksum internal; tanpa soft delete/archived_at di 09a. | Satu tabel dengan JSON versi (sulit diaudit); kolom arsip di tabel surat/izin (coupling); tabel polymorphic generik (tidak perlu). |
| **D3** | Link domain? | Whitelist `student`/`employee`/`letter`/`leave` atau null; both-or-none; cek keberadaan termasuk soft-deleted; link immutable setelah create; type/id arbitrary `422`; visibilitas lintas domain memakai ability domain. | Model class bebas (risiko keamanan); relink subjek (bypass visibilitas); wajib subjek (dokumen umum tidak bisa). |
| **D4** | Ability/role? | `archives.view` + `archives.manage`; default admin/operator manage, kepala/waka view, guru none; tanpa role/approval baru; delegasi lewat Roles. | Memakai `letters.manage` (domain salah); admin-only (operator tidak bisa bekerja); memberi guru view (tidak punya domain view). |
| **D5** | File/versi? | PDF/JPG/JPEG/PNG, 10 MB/file, satu file/versi, disk privat `local`, path `attachments/archives/{id}/{uuid}.{ext}`, UUID, sha256 internal, file pertama wajib, versi immutable, download berizin, kompensasi + rollback, tanpa delete/purge. | Multi-file per versi (scope baru); hapus versi (riwayat hilang); public disk (bocor); ekspos checksum/path (tidak perlu). |
| **D6** | Metadata/validitas? | Kategori config 6 nilai; title 3–200; number opsional/non-unik; document_date wajib; valid_from/valid_until berpasangan dan berurutan; status `none/upcoming/valid/expiring_soon/expired` (WIB, ambang 30 hari); tanpa pengingat. | Nomor wajib/unik (arsip eksternal sering duplikat); satu tanggal kedaluwarsa (kurang ekspresif); pengingat sekarang (09b). |
| **D7** | Otorisasi/visibilitas? | Baca `archives.view`; tulis `archives.manage`; dokumen bertaut juga butuh ability domain; list/search menyaring domain; filter eksplisit tanpa izin `403`; objek hidden `404`; umum cukup `archives.view`; tanpa ACL per dokumen. | Ability arsip saja untuk semua link (kebocoran lintas domain); `403` untuk hidden (membocorkan keberadaan); ACL per dokumen (scope baru). |
| **D8** | API/UI? | 9 route; static sebelum `/{archive}`; `whereNumber`; `expected_version`/`409`; halaman `/public/archives` + menu; dialog create/edit/detail/versi; English; 390 px; tanpa preview/delete/archive/restore/tombol dummy. | Halaman terpisah (duplikasi); input ID subjek manual (rawan salah); tanpa picker (UX buruk). |
| **D9** | Audit/privasi? | Allowlist metadata minimal; title/number/description/original_name/path/checksum/note **tidak** di audit; versi immutable; respons tanpa path/checksum; subject summary minimal; download belum diaudit (09b); retensi DEC-13. | Audit title/number (PII); ekspos checksum (tidak perlu); audit isi file (tidak mungkin); metadata revisions table (scope baru). |
| **D10** | Tes/UAT/dokumentasi? | T1–T13 + regresi Letters/Leaves; `.uat-phase09` 18027/14027, cookie `school_phase09_session`, launcher `scripts/phase09-uat.php`, `.uat-phase09/` di `.gitignore`; `prepare-archives` idempotent ≥120 dokumen/≥150 versi; server dihentikan setelah penerimaan (DEC-27); suite penuh NOT RUN karena `school_access_uat`; dokumentasi kontrak saat implementasi. | Reuse `.uat-phase08` (coupling); fixture manual (tidak reproducible); full suite (melanggar batas akses); membiarkan server berjalan (DEC-27). |

**Ringkasan pertanyaan terbuka:**

| Q | Jawaban yang diusulkan |
|---|---|
| Q1 — apakah 09a termasuk download? | Ya, download berizin current/versi lama; preview inline dan audit akses download adalah 09b. |
| Q2 — apakah link subjek dapat diubah? | Tidak; buat dokumen arsip baru untuk koreksi link. |
| Q3 — apakah lampiran existing dimigrasikan? | Tidak; modul surat/izin tetap seperti sekarang. |
| Q4 — apakah retensi/purge termasuk? | Tidak; DEC-13 OPEN dan menjadi gate 09b. |
| Q5 — apakah pengingat kedaluwarsa termasuk? | Tidak; hanya status validitas yang dihitung. |
| Q6 — siapa boleh mengelola? | Admin Sistem + Operator Sekolah; Kepala/Waka view-only; Guru tanpa akses. |
| Q7 — boleh dokumen tanpa subjek? | Ya, dokumen umum; cukup `archives.view`. |
| Q8 — apakah nomor dokumen unik? | Tidak; opsional dan non-unik. |
| Q9 — apakah checksum diekspos? | Tidak; hanya internal untuk integritas. |
| Q10 — apakah ada dependency baru? | Tidak. |

---

## 6. Urutan pekerjaan yang diusulkan

| Langkah | Pekerjaan | Hasil yang dapat diverifikasi |
|---|---|---|
| S0 | Preflight: audit HEAD `aeca361`, pastikan tidak ada kode/route/ability arsip, `.uat-phase08` utuh dan server berhenti, catat persetujuan D1–D10 | Baseline jelas; belum ada perubahan kode |
| S1 | Schema/model/config/ability: dua migration tabel, migration ability, `config/archives.php`, `ArchiveDocument`/`ArchiveFileVersion`, `ArchiveDocumentPolicy`, allowlist audit | Tabel/ability/model/policy siap; tidak mengubah tabel existing |
| S2 | Service: resolusi subjek + visibilitas, file service + kompensasi, document service create/update/version/list/detail/download, transaksi/409 | Aturan bisnis dan keamanan terpusat |
| S3 | API: FormRequest, controller, 9 route, options/picker, route order, respons aman | 9 route arsip; kontrak berjalan |
| S4 | UI: halaman Archives, router, menu, daftar/filter, dialog create/edit/detail/versi, download, 409 Reload Latest, 390 px | Alur arsip end-to-end |
| S5 | Tes T1–T13 + regresi Letters/Leaves + Pint/build/lint + hitung route | Matriks PASS/NOT RUN aktual |
| S6 | UAT: launcher `.uat-phase09`, `.gitignore`, `prepare-archives`, browser/API checks | UAT siap review; data sintetis reproducible |
| S7 | Dokumentasi: DATABASE/API/ACCESS/SECURITY/OPS/TESTING/REQUIREMENTS/DECISIONS/CHANGELOG + laporan + checklist + commit/push review package | Paket review di GitHub; berhenti untuk review pemilik |

Setelah S7, berhenti untuk review pemilik. Penerimaan dicatat pada commit dokumentasi terpisah; server UAT dihentikan setelah penerimaan (DEC-27). Tidak ada merge/deploy/migrasi operasional.

---

## 7. Matriks tes yang direncanakan

Tes baru di `tests/Feature/Archives/ArchiveDocumentTest.php` (dan file terkait bila perlu), memakai `RefreshDatabase`, `CreatesAccessFixtures`, `Storage::fake('local')`, dan `Carbon::setTestNow` WIB. Tidak mengubah perilaku modul existing.

| ID | Skenario | Expected |
|---|---|---|
| T1 | Create dokumen umum + file pertama; metadata/versi 1; checksum di DB | `201`/`200`; baris + versi 1; checksum 64 hex; respons tanpa path/checksum |
| T2 | Upload tidak sah dan kegagalan DB setelah file ditulis | `422`/`500`; tidak ada baris/versi; tidak ada file tersisa (kompensasi) |
| T3 | Link student/employee/letter/leave valid; type invalid; id tidak ada; type/id mismatch; subjek soft-deleted | `200` untuk valid; `422` untuk arbitrary/mismatch; soft-deleted diterima |
| T4 | Visibilitas lintas domain: user `archives.view` tanpa `students.view`; list/show/filter; dokumen umum | Dokumen student tidak muncul; show/download `404`; filter student `403`; umum terlihat |
| T5 | Create/update oleh manage+domain, manage tanpa domain, tanpa manage; update subjek; stale version | `200`/`403`/`403`/`422`/`409`; subjek tidak berubah |
| T6 | Tambah 2 versi; riwayat; versi lama; stale `expected_version`; update/delete model versi | Versi 2/3; versi lama utuh; `409` tanpa mutasi; update/delete ditolak |
| T7 | PDF/JPG/JPEG/PNG valid; ekstensi ganda; MIME mismatch; >10 MB; nama aneh; path | Valid diterima; tidak sah `422`; path `attachments/archives/{id}/{uuid}.{ext}`; nama tersanitasi |
| T8 | Validasi kategori/judul/nomor/tanggal/validitas/deskripsi/subjek both-or-none | `422` untuk yang salah; `200` untuk yang benar |
| T9 | Status validitas: none/upcoming/valid/expiring_soon/expired; batas hari ini dan +30; WIB | Status benar di batas; filter konsisten |
| T10 | List search/filter/pagination/sort; filter domain tidak berizin; parameter invalid | Angka/urutan benar; `403`/`422`; tidak ada data lintas domain |
| T11 | Audit allowlist; versi immutable; respons tanpa path/checksum; subject summary minimal | Field sensitif tidak masuk audit/respons; update/delete versi ditolak |
| T12 | Route order/IDOR: options/subjects tidak tertangkap `{archive}`; `whereNumber`; direct API tanpa ability; hidden `404`; version beda dokumen `404`; tidak ada route delete/restore/preview | Route statis benar; `403`/`404` tepat; hanya 9 route arsip |
| T13 | Download: header `X-Document-Filename`, expose header, tanpa `Content-Disposition`, nosniff/CSP/no-store; byte file; versi lama | Header/byte benar; file hilang `404`; tidak ada path di respons |

Regresi wajib: **Letters 86 tes/1.744 assertions** dan **Leaves 30 tes/473 assertions** (baseline 08b), Pint pada file PHP berubah, `npm run build`, `npx eslint` non-mutasi, dan hitung route `archives` = **9**. Suite penuh **NOT RUN** karena `Tests\Feature\Backup\MariaDbIntegrationTest` membaca `school_access_uat`; gate tes tersebut sebelum run penuh berikutnya. Race MariaDB/InnoDB dan multi-proses tetap NOT RUN.

---

## 8. Rencana UAT

### 8.1 Lingkungan

- Runtime baru **`.uat-phase09`** (SQLite/storage terisolasi), API `127.0.0.1:18027`, SPA `127.0.0.1:14027`, cookie `school_phase09_session`, launcher `laravel-api/scripts/phase09-uat.php`.
- Tambahkan `.uat-phase09/` ke `.gitignore` root saat implementasi. Jangan menyentuh `.uat-phase05/06/07/08`, database operasional, Apache, atau service MariaDB.
- Frontend memakai `VITE_API_BASE_URL=http://127.0.0.1:18027`; berkas `vue-app/.env.uat` di-ignore dan **tidak boleh di-commit**. Perintah yang disarankan:
  - API: `C:\xampp\php82-operational\php.exe -S 127.0.0.1:18027 scripts/phase09-uat.php`
  - SPA: `VITE_API_BASE_URL=http://127.0.0.1:18027 npx vite --mode uat --host 127.0.0.1 --port 14027`
- Server dihentikan setelah penerimaan (DEC-27) dengan verifikasi PID/command line/port; data `.uat-phase09` dipertahankan. Password hanya di `.uat-phase09/account-password.txt`, tidak pernah di dokumen/commit.

### 8.2 Perintah launcher

- `init`: membuat runtime terisolasi, SQLite, storage, cookie, password; menolak menimpa runtime existing.
- `prepare`: migrate + `UatSeeder` (akun dasar).
- `prepare-archives` (idempotent): membuat/menemukan subjek sintetis dan fixture arsip.
- `status`: read-only; mencetak jumlah dokumen/versi/subjek/akun dan kategori/status validitas.
- Allowed commands: `init`, `prepare`, `prepare-archives`, `status`.

### 8.3 Fixture `prepare-archives`

- Subjek: **120 siswa** (3 rombel, 1 tahun ajaran aktif), **20 pegawai** (2 tertaut akun), **20 surat masuk**, **20 izin** (status bervariasi).
- Arsip: **120 dokumen** (40 student, 20 employee, 20 letter, 20 leave, 20 umum), **160 versi** (40 dokumen memiliki versi tambahan), kategori tersebar.
- Validitas: 20 expired, 20 expiring_soon, 60 valid, 10 upcoming, 10 none.
- File: PDF/PNG/JPG sintetis kecil yang valid; nama asli bervariasi; path UUID.
- Akun: `uat.admin`, `uat.operator`, `uat.kepsek`, `uat.waka`, `uat.guru`, `uat.archivist` (role kustom **archives.view saja**, tanpa domain view), `uat.inactive`. Password dari runtime.
- Idempotent berdasarkan penanda `UAT-P09-*`; tidak membuat duplikat; tidak mereset data; tidak menyentuh `.uat-phase08`.

### 8.4 Checklist manual (18 item)

1. `uat.operator` melihat menu **Archives** dan daftar 120 dokumen; filter/pagination berfungsi.
2. Create dokumen umum dengan PDF; versi 1 tampil; detail menampilkan metadata.
3. Create dokumen siswa lewat picker; subjek tampil; file tersimpan.
4. Create dokumen employee/letter/leave; picker hanya menampilkan tipe yang diizinkan.
5. Link arbitrary/tidak ada via API → `422`; type/id mismatch → `422`.
6. Add version; versi lama tetap ada; download current dan versi lama berhasil.
7. Edit metadata; subjek read-only; versi basi → `409` + Reload Latest.
8. File salah tipe/ekstensi ganda/>10 MB ditolak; tidak ada dokumen baru; tidak ada orphan (laporan agen).
9. Search/filter kategori/subjek/validitas/rentang tanggal; hasil sesuai.
10. Badge validitas expired/expiring_soon/valid/upcoming/none benar pada batas tanggal.
11. `uat.kepsek` dapat melihat dan mengunduh dokumen semua domain; tanpa tombol manage.
12. `uat.waka` view-only; create/update/add version via API → `403`.
13. `uat.guru` tidak melihat menu Archives; API → `403`.
14. `uat.archivist` hanya melihat dokumen umum; dokumen student/employee/letter/leave tidak muncul; direct show/download → `404`; filter domain → `403`.
15. Respons API tidak memuat path/checksum; audit tidak memuat title/number/filename/path/checksum.
16. Download header benar (`X-Document-Filename`, tanpa `Content-Disposition`), file byte sesuai; file hilang `404`.
17. Layout 390 px, tabel scroll, dialog full-width, keyboard/aria; 0 console error selain probe `409`/`422`/`403` yang disengaja; tidak ada tombol dummy.
18. Setelah penerimaan, server UAT dihentikan (DEC-27), `.uat-phase09` dipertahankan; `.uat-phase08`/Apache/MariaDB tidak disentuh.

Browser checks dijalankan bila browser tersedia; jika tidak, checklist manual diberikan dan item browser dicatat **NOT RUN**.

---

## 9. Dokumentasi yang diperbarui saat implementasi

- `docs/DATABASE.md` — dua tabel, kolom, index, relasi, catatan immutable/checksum.
- `docs/API-CONTRACTS.md` — 9 route arsip, payload/query, respons, download header, error.
- `docs/ACCESS-CONTROL.md` — `archives.view`/`archives.manage`, matriks role, visibilitas domain, `403`/`404`.
- `docs/SECURITY-DATA.md` — disk privat, UUID, sha256 internal, header download, audit allowlist, tanpa URL publik.
- `docs/OPERATIONS.md` — path backup `attachments/archives`, UAT `.uat-phase09`, stop DEC-27.
- `docs/TESTING.md` — perintah/hasil aktual, NOT RUN, batas `school_access_uat`.
- `docs/REQUIREMENTS.md` — FR-11 status 09a.
- `docs/DECISIONS.md` — DEC-37 dari PROPOSED menjadi APPROVED setelah pernyataan pemilik.
- `CHANGELOG.md`, `PROJECT_CONTEXT.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-09-ARCHIVES.md`, `reports/feature-update/INDEX.md`, `HANDOFF.md`.
- `reports/feature-update/PHASE-09A-REPORT.md` dan `PHASE-09A-MANUAL-REVIEW.md`.

---

## 10. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Kebocoran lintas domain (arsip siswa terlihat oleh user tanpa `students.view`) | Resolusi visibilitas terpusat + filter query + tes T4; direct hidden `404`; filter `403` |
| Orphan file saat upload gagal | Validasi sebelum tulis; tulis dalam transaksi; `discard` pada setiap `Throwable`; tes T2; crash-window dicatat residual |
| Dua versi paralel / versi duplikat | `lockForUpdate` + `expected_version` + unique `(document, version_number)`; `409`; tes T6 |
| Path traversal/nama file berbahaya | `PrivateUploadValidator`, UUID server-side, satu ekstensi, MIME dari isi; tes T7 |
| File terekspos via URL publik | Disk privat di luar web root; tanpa route publik; download berizin; tes T12/T13 |
| Checksum/path bocor di API/audit | Allowlist audit; respons eksplisit tanpa path/checksum; tes T11 |
| Batas validitas salah karena zona waktu | Hitung dengan tanggal WIB; tes batas T9 |
| Metadata sensitif masuk audit | Allowlist tanpa title/number/description/filename; trade-off dicatat di D9 |
| Bug `Content-Disposition` 07a/07b terulang | Gunakan pola 07c/08a (`X-Document-Filename`); bug existing di luar scope dan dicatat sebagai backlog |
| Suite penuh menyentuh `school_access_uat` | Jalankan tes arsip/Letters/Leaves terpisah; gate `MariaDbIntegrationTest` sebelum suite penuh |
| Fixture UAT berat/tidak reproducible | Launcher idempotent + penanda `UAT-P09-*`; target dihitung dari kondisi; status command |
| Server UAT dibiarkan berjalan | Stop setelah penerimaan (DEC-27); verifikasi PID/port; data dipertahankan |
| Retensi/purge belum diputuskan | DEC-13 tetap OPEN; 09a tidak menghapus; 09b menunggu keputusan |
| Backup tidak mencakup file arsip | Path berada di `storage/app/attachments`; verifikasi manifest saat implementasi |

---

## 11. Kriteria penerimaan paket rencana

- [ ] Pemilik menyetujui D1–D10; pernyataan dicatat sebagai **DEC-37 APPROVED**.
- [ ] Implementasi 09a diotorisasi; 09b tetap memerlukan rencana dan persetujuan terpisah; **DEC-13 tetap OPEN**.
- [ ] Tidak ada kode/migration/route/test/UAT pada paket rencana ini.
- [ ] Dokumen status (`INDEX`, `HANDOFF`, `ROADMAP`, phase file, `CHANGELOG`, `PROJECT_CONTEXT`, `REQUIREMENTS`, `DECISIONS`) menyatakan **09a PLAN — AWAITING OWNER APPROVAL (D1–D10; DEC-37 PROPOSED)**.
- [ ] Setelah persetujuan, implementasi mengikuti S0–S7 dan berhenti untuk review; commit+push paket review; penerimaan dicatat pada commit dokumentasi terpisah.

**Pernyataan otorisasi:** paket ini hanya rencana. 09a **belum diimplementasikan** dan tidak boleh dimulai, di-commit sebagai kode, atau diuji sebagai fitur sampai pemilik menyetujui D1–D10. Merge/deploy/force-push, migrasi database operasional/MariaDB, perubahan dependency/lockfile, perubahan service/`.env`, dan retensi/pemusnahan DEC-13 tetap tidak diotorisasi.
