# Laporan Subfase 09a — Metadata dan Versi Arsip Dokumen

Tanggal: implementasi dan verifikasi agen 2026-11-02 WIB. Branch: `main`. Baseline induk: `b26fb7f` (pencatatan DEC-37 APPROVED); SHA paket review dilaporkan setelah commit/push (tanpa klaim SHA masa depan). Status teknis: **IMPLEMENTED — agent-verified (2026-11-02)**. Status review pemilik: **PENDING (#1–#18)**.
Keputusan pemilik: **DEC-37 APPROVED** (D1–D10, 2026-11-02 WIB; pernyataan **"Saya setuju D1-D10"**); **DEC-35/DEC-36 tidak berubah**; **DEC-13 tetap OPEN**.
Otorisasi: implementasi **09a** sesuai `reports/feature-update/PHASE-09-PLAN.md`. 08a/08b tetap **OWNER APPROVED** (08b #1–#17; item #18 dijalankan agen).

## 1. Outcome dan cakupan

Subfase 09a menyediakan arsip dokumen terpusat tahap pertama:

- **Metadata.** `archive_documents` menyimpan kategori (6 nilai config), judul, nomor dokumen opsional/non-unik, tanggal dokumen, `valid_from`/`valid_until` berpasangan, deskripsi, dan tautan subjek whitelist. Rekursi/versi metadata memakai `version`; `expected_version` mencegah update basi (`409`).
- **Tautan domain.** Subjek hanya `student`/`employee`/`letter`/`leave` atau null. Tipe/id arbitrer, tipe tidak dikenal, pasangan tidak lengkap, dan ID yang tidak ada untuk tipe tersebut ditolak `422`. Eksistensi memakai `withTrashed()` untuk siswa/pegawai, sehingga arsip administratif tetap dapat ditautkan. Tautan subjek immutable setelah create; request update memakai `prohibited`.
- **Masa berlaku.** Status validitas dihitung saat baca dengan WIB dan ambang 30 hari: `none`, `upcoming`, `valid`, `expiring_soon`, `expired`; `upcoming` menang atas status lain bila `valid_from` berada di masa depan. Filter validitas mencerminkan presedensi tersebut.
- **Versi file immutable.** File pertama wajib saat create; versi berikutnya append-only dengan nomor berurutan dan unique `(archive_document_id, version_number)`. Tidak ada endpoint update/delete versi. PDF/JPG/JPEG/PNG maksimum 10 MB, nama file UUID dari server, MIME dari isi, path privat `attachments/archives/{document_id}/{uuid}.{ext}` pada disk `local`, SHA-256 internal.
- **Download berizin.** Current dan versi lama diunduh lewat route berizin yang sama dengan visibilitas dokumen. Header mengikuti pola 07c/08a: `X-Document-Filename`, `Access-Control-Expose-Headers`, `nosniff`, CSP sandbox, `Cache-Control: private, no-store`, **tanpa `Content-Disposition`**. File hilang `404`.
- **Visibilitas dan izin.** `archives.view`/`archives.manage` ditambahkan ke katalog ability. Default peran: Admin Sistem/Operator Sekolah manage + view; Kepala Sekolah/Wakil Kesiswaan view; Guru tidak mendapat ability. Dokumen bertaut juga memerlukan ability domain (`students.view`/`employees.view`/`letters.view`/`leaves.view`). Daftar hanya menampilkan dokumen umum dan domain yang diizinkan; filter subjek tanpa ability `403`; objek/unduhan tersembunyi `404`.
- **Pencarian dan filter.** `q` mencocokkan judul/nomor dengan LIKE ter-escape; filter kategori, subjek (tipe + id), validitas, rentang tanggal dokumen, pagination 10/20/50/100 (default 20), urutan `document_date desc, id desc`.
- **UI.** Halaman `/public/archives` dengan daftar, filter, detail, versi, unggah versi, edit metadata, konflik `409` + Reload Latest, dan aksi download. Tombol tulis mengikuti `can.manage` server. Menu **Archives** dan route guard `archives.view`.
- **Audit.** Allowlist `ArchiveDocument`: `category`, `document_date`, `valid_from`, `valid_until`, `subject_type`, `subject_id`, `current_version`, `version`. Judul, nomor, deskripsi, nama file, path, dan checksum tidak masuk audit. `ArchiveFileVersion` append-only tanpa `updated_at`.

Tidak dikerjakan pada 09a (non-goals D1/D2/D10): **09b** — preview inline, audit akses download, expiry task/pengingat, arsip/restore, retensi/pemusnahan **DEC-13**; route delete/restore; OCR/fulltext; email/WhatsApp; perubahan dependency/lockfile; migrasi database operasional/MariaDB; merge/deploy/force-push.

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File | Perubahan | Schema/izin |
|---|---|---|
| `config/archives.php` | 6 kategori, 4 tipe subjek + ability domain, 5 status validitas, ambang 30 hari, batas judul/nomor/deskripsi/catatan, disk `local`, path `attachments/archives`, 10 MB, PDF/JPG/JPEG/PNG, pagination | Tanpa `.env` baru |
| `database/migrations/2029_09_01_000001_create_archive_documents_table.php` | Tabel metadata + index kategori/nomor/tanggal/`valid_until`/subjek/kategori+tanggal | Additive |
| `database/migrations/2029_09_01_000002_create_archive_file_versions_table.php` | Tabel versi + unique `(archive_document_id, version_number)`; FK dokumen restrictOnDelete, uploader nullOnDelete | Additive, append-only |
| `database/migrations/2029_09_01_000003_add_archive_abilities.php` | Ability `archives.view`/`archives.manage` + pemetaan peran awal; idempoten | Additive/idempoten |
| `app/Support/AbilityCatalog.php` | Definisi ability arsip dan penugasan peran awal | Admin/Operator manage; Kepala/Waka view |
| `app/Models/ArchiveDocument.php` | Cast tanggal, relasi creator/updater/versions/latestVersion, `validityStatus()`, allowlist audit, larangan delete | Tanpa soft delete |
| `app/Models/ArchiveFileVersion.php` | Relasi dokumen/uploader, larangan update/delete, tanpa `updated_at` | Immutable |
| `app/Policies/ArchiveDocumentPolicy.php` | `viewAny`, `view`/`download` (view + domain), `create`/`update`/`addVersion` (manage + domain), `delete`/`restore`/`forceDelete` false | Centralized Policy |
| `app/Services/Archives/ArchiveDocumentService.php` | Options, list/filter, show, create, update, addVersion, versions, download, serialisasi, konflik `409` | Transaksi + `lockForUpdate` |
| `app/Services/Archives/ArchiveFileService.php` | Validasi bersama `PrivateUploadValidator`, tulis file UUID, SHA-256, kompensasi, header download | Disk privat |
| `app/Services/Archives/ArchiveSubjectService.php` | Definisi/ability domain, constrain visibilitas, resolusi tautan, picker, ringkasan subjek | Whitelist + `withTrashed` |
| `app/Http/Controllers/ArchiveController.php` | 9 handler + validasi filter | 9 route |
| `app/Http/Requests/StoreArchiveDocumentRequest.php` | Metadata + subjek whitelist + file wajib | `422` |
| `app/Http/Requests/UpdateArchiveDocumentRequest.php` | Field `sometimes`; `expected_version`; subjek/file `prohibited` | `422`/`409` |
| `app/Http/Requests/StoreArchiveVersionRequest.php` | File wajib, catatan opsional 500, `expected_version` | `422`/`409` |
| `routes/api.php` | Grup `/api/archives` dengan `whereNumber` | 9 route |
| `tests/Feature/Archives/ArchiveDocumentTest.php` | 14 tes Pest / 374 assertions | T1–T13 |
| `scripts/phase09-uat.php` | Launcher `init`/`prepare`/`prepare-archives`/`status` | Hanya `.uat-phase09` |
| `vue-app/src/views/pages/public/Archives.vue` | Halaman daftar/filter/create/detail/versi/edit/download, `409` Reload Latest | Tombol mengikuti `can` server |
| `vue-app/src/router/index.js` | Route `archives` + `requiresAbility: 'archives.view'` | Guard UI |
| `vue-app/src/layout/AppMenu.vue` | Grup **Archives** | `archives.view` |
| `.gitignore` | `.uat-phase09/` | Runtime UAT tidak di-commit |

Kontrak endpoint 09a (detail respons di `docs/API-CONTRACTS.md`):

| # | Method | Endpoint | Izin | Catatan |
|---|---|---|---|---|
| 1 | GET | `/api/archives/options` | `archives.view` | Kategori, tipe subjek yang diizinkan, status validitas, batas, `can` |
| 2 | GET | `/api/archives/subjects/{type}` | `archives.view` + ability domain | Picker `q`/`limit` (≤100, default 20); tipe di luar whitelist tidak terdaftar |
| 3 | GET | `/api/archives` | `archives.view` | List + filter + pagination; hanya dokumen umum/domain yang diizinkan |
| 4 | POST | `/api/archives` | `archives.manage` | Multipart; file pertama wajib; subjek both-or-none; `200` |
| 5 | GET | `/api/archives/{archive}` | `archives.view` + domain | Detail + `latest_version`; tersembunyi `404` |
| 6 | PUT/PATCH | `/api/archives/{archive}` | `archives.manage` + domain | Metadata saja; `expected_version`; subjek/file `prohibited`; `409` |
| 7 | GET | `/api/archives/{archive}/versions` | `archives.view` + domain | Riwayat versi terbaru dahulu + meta |
| 8 | POST | `/api/archives/{archive}/versions` | `archives.manage` + domain | File + catatan opsional + `expected_version`; versi baru |
| 9 | GET | `/api/archives/{archive}/versions/{version}/download` | `archives.view` + domain | Stream file; versi harus milik dokumen; `404` |

- **Bentuk item ringkas**: `id`, `category` + label, `title`, `document_number`, `document_date`, `valid_from`, `valid_until`, `validity_status`, `subject` (ringkasan aman per domain, `archived` bila soft-deleted), `current_version`, `versions_count`, `version`, `updated_at`, `can {view, update, add_version, download}`. `meta {current_page, per_page, total, last_page}`.
- **Bentuk detail** menambahkan `description`, `created_at`, `created_by {id,name}`, `updated_by {id,name}`, `latest_version`. Versi: `id`, `version_number`, `original_name`, `mime_type`, `size_bytes`, `note`, `uploaded_by {id,name}`, `created_at`; **tanpa path/checksum**.
- **Error**: `401` guest; `403` tanpa ability/filter domain tanpa izin; `404` dokumen/versi tersembunyi atau file hilang; `409` versi basi dengan `{message, errors, latest {id, version, current_version, updated_at}}`; `422` validasi, tautan subjek, rentang tanggal, file, `expected_version` hilang.
- **Opsi**: tipe subjek difilter per ability aktor; `file.allowed_extensions` dan `max_file_kb`; `can.manage`; `expiring_soon_days`; `search_max_length`.
- **UI**: New/Edit/Add Version hanya untuk `can.manage`; detail tanpa tombol delete; konflik `409` memuat pesan aman + **Reload Latest** yang memuat ulang detail dan daftar; unduhan memakai `fetch` berkredensial lalu blob dari `X-Document-Filename`.

## 3. Bukti verifikasi

- **Tes arsip**: `ArchiveDocumentTest` **14 tes / 374 assertions PASS (11,81 s)**. Cakupan: create dokumen umum + versi immutable pertama; penolakan file salah tipe/ekstensi ganda/oversize dan kompensasi file saat penyimpanan versi gagal; tautan subjek whitelist/existence termasuk soft-deleted dan penolakan tipe-id mismatch/arbitrer; batas visibilitas viewer umum vs domain; penegakan `archives.manage` + ability domain pada tulis; append versi + optimistic locking + riwayat berurutan; validasi MIME dari isi + nama server; validasi metadata/tanggal; status validitas & filter dengan batas WIB dan presedensi `upcoming`; pencarian/filter/pagination/sort; audit minimal tanpa path/checksum; route auth/ability/`whereNumber`; download header aman + versi lama; picker hanya domain yang diizinkan.
- **Suite penuh backend**: **597 passed / 13 skipped / 7.083 assertions (438,24 s)**. Tiga belas skip adalah tes integrasi MariaDB yang memerlukan kredensial; tidak ada database operasional yang diakses. Suite memakai SQLite in-memory sesuai `phpunit.xml`.
- **Kualitas kode**: Pint pada file PHP berubah **PASS (18 file)**; `php artisan route:list --path=archives` **9 route**; `npm run build` **PASS**; `npx eslint` non-mutasi pada `Archives.vue`, router, dan menu **0 error / 613 warning** (warning Prettier CRLF lama, tidak diperbaiki agar scope tetap 09a).
- **UAT `.uat-phase09`** (SQLite; API `127.0.0.1:18027`; SPA `127.0.0.1:14027`; cookie `school_phase09_session`; launcher `scripts/phase09-uat.php`; storage `.uat-phase09/storage`): `prepare` menghasilkan **7 akun** (admin, operator, kepsek, waka, guru, inactive, archivist), 123 siswa, 20 pegawai, 20 surat, 20 izin. `prepare-archives` pertama membuat **120 dokumen / 160 versi / 160 file privat**; distribusi 40 student, 20 employee, 20 letter, 20 leave, 20 general. Run kedua idempoten: **0 created / 0 reused / 0 added**. Status akhir setelah bukti browser: **121 dokumen / 162 versi / 162 file privat**.
- **Fixture UI**: dokumen `#121` (School Document) dibuat dari UI dengan PDF, lalu ditambah versi 2 (PDF 178 B/181 B); metadata diedit hingga `version=3`. Total halaman daftar 20/page (6 halaman untuk 120 dokumen).
- **Matriks peran/browser/API**:
  - `uat.operator`/`uat.admin`: menu **Archives** tampil; daftar 120 dokumen; search "UI Created" → 1; filter kategori Student Record → 40; filter validitas Expired → 24 (halaman 2 memuat 4); create/versi/edit/detail berhasil.
  - `uat.archivist` (khusus `archives.view`, tanpa ability domain): hanya **21 dokumen umum** (20 fixture + 1 UI); tanpa tombol New/manage; opsi tipe subjek kosong; create/picker/filter subjek `403`; show/download dokumen bertaut `404`; show/download dokumen umum `200` dengan `X-Document-Filename`.
  - `uat.kepsek`: melihat **121 dokumen** dan keempat tipe subjek; `can.manage=false`; create/update/add version melalui API `403`.
  - `uat.guru`: menu **Archives** tidak tampil; API `403`; deep link diarahkan ke `/auth/access` (Access Denied); ability hanya `leaves.request`.
  - `uat.waka`: tidak dijalankan terpisah pada sesi browser 09a; perilaku view-only setara dicakup tes ability (`archives.view` tanpa `archives.manage`).
- **Download**: dokumen umum `200`, header `X-Document-Filename`, `Access-Control-Expose-Headers`, `nosniff`, CSP sandbox, `Cache-Control: private, no-store`, tanpa `Content-Disposition`; dokumen bertaut bagi archivist `404`; byte/versi lama dan file hilang dicakup tes.
- **Console browser**: 2 pesan — 1 peringatan aksesibilitas ("A form field element should have an id or name attribute") dan 1 resource `404`; tidak diklasifikasikan sebagai defect fungsional 09a dan tidak diperbaiki dalam scope ini.

## 4. NOT RUN

- Viewport 390 px, audit keyboard/aria, dan visual regression berbasis gambar: sesi browser 09a hanya desktop 1440×900. Item checklist #17 untuk 390 px/keyboard **NOT RUN**.
- Race MariaDB/InnoDB atau multi-proses nyata; seluruh runtime UAT memakai SQLite.
- Dua tab browser nyata untuk `409`; konflik versi diuji lewat tes/API dan jalur Reload Latest tersedia.
- Restore drill yang memuat tabel/file arsip; benchmark volume besar; antivirus/malware scanning.
- Preview inline, audit akses download, expiry task, arsip/restore, retensi/purge (**09b/DEC-13**).
- Verifikasi browser seluruh skenario manual di luar daftar bukti di atas; checklist pemilik tetap disediakan.
- Item checklist #3/#4 untuk alur create UI per domain siswa/pegawai/surat/izin: API/tes/fixture mencakup keempat domain, tetapi alur picker UI per domain tidak dijalankan terpisah.
- Item checklist #12 untuk `uat.waka`: tidak dijalankan terpisah; lihat status di `PHASE-09A-MANUAL-REVIEW.md`.

## 5. Status UAT dan langkah berikutnya

- Server UAT `.uat-phase09` **telah dihentikan 2026-11-02 WIB setelah verifikasi UAT** sesuai instruksi pemilik agar lingkungan uji yang sudah dilewati tidak terus berjalan: API dihentikan, SPA/Vite dihentikan setelah verifikasi PID, dan port 18027/14027 dipastikan bebas. `.uat-phase09` (SQLite/storage/password runtime) dipertahankan; Apache/MariaDB tidak disentuh. Penghentian ini **bukan** penerimaan manual pemilik.
- Perintah restart dan status tersedia di `PHASE-09A-MANUAL-REVIEW.md`. Jangan me-reset `.uat-phase09`.
- **Review manual pemilik PENDING (#1–#18)**. Commit/push paket review bukan penerimaan. Setelah pemilik menyatakan approval, penerimaan dicatat pada commit dokumentasi terpisah.
- **09b tidak diotorisasi**; **DEC-13 tetap OPEN**. Merge/deploy/force-push, migrasi database operasional/MariaDB, perubahan dependency/lockfile, dan perubahan service/`.env` tetap tidak diotorisasi.
