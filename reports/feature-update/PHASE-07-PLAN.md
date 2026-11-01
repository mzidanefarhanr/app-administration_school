# Rencana Fase 07 — Persuratan: Subfase 07a (Surat Masuk dan Lampiran)

- Tanggal: 2026-11-01 (WIB). Branch: `main`. Baseline dokumen: `1101812` (HEAD; penerimaan 06c tercatat).
- Status dokumen: **PLAN APPROVED — DEC-32 APPROVED 2026-11-01 WIB** berdasarkan pernyataan pemilik **"Saya setuju D1-D10"**. Implementasi 07a **selesai, agent-verified, dan OWNER APPROVED (#1–#16, 2026-11-01 WIB)**. Rencana rinci **07b** tersedia di `PHASE-07B-PLAN.md` dan **APPROVED 2026-11-01 WIB sebagai DEC-33** ("Saya setuju D1-D11"); implementasi 07b diotorisasi dan **DEC-06 ditutup**. 07c tetap memerlukan rencana dan keputusan terpisah. Bukti: `PHASE-07A-REPORT.md`; checklist pemilik `PHASE-07A-MANUAL-REVIEW.md`.
- Cakupan: **07a saja** — agenda surat masuk (tanggal terima/tanggal surat, nomor sumber, pengirim, perihal, kategori, catatan), lampiran privat, pencarian/filter, edit/void berversi, dan history. Layanan file minimal dibuat di sini dan diperluas pada Fase 09.
- Di luar cakupan: **07b** (disposisi, surat keluar, penomoran, approval/sign), **07c** (template PDF, snapshot, kop/tanda tangan/stempel), OCR, e-sign provider, pengiriman email/WhatsApp otomatis, arsip terpusat/versioning Fase 09, retensi/pemusnahan data pribadi (DEC-13), merge/deploy, migrasi database operasional/MariaDB, perubahan dependency/lockfile/`.env`/service.
- Dokumen acuan: `roadmap/phases/PHASE-07-LETTERS.md`, `roadmap/ROADMAP.md`, `docs/DECISIONS.md` (DEC-06, DEC-11, DEC-13, DEC-27), `docs/DATABASE.md`, `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `docs/OPERATIONS.md`, `docs/TESTING.md`, `reports/feature-update/PHASE-06C-PLAN.md`.
- Gate: 06c **OWNER APPROVED 2026-11-01 WIB (#1–#16)**; **DEC-11 tetap OPEN** dan hanya menjadi gate 07c; **DEC-06 CLOSED** melalui DEC-33 (D4–D5) untuk 07b. Tidak ada PDF library terpasang; 07c memerlukan keputusan dependency terpisah. **D1–D10 APPROVED 2026-11-01 WIB sebagai DEC-32**; implementasi 07a selesai sesuai rencana ini.

---

## 1. Posisi dan prasyarat

| Prasyarat | Kondisi aktual 2026-11-01 | Status |
|---|---|---|
| Fase 06 | 06a, 06b, 06c implemented dan owner-accepted; UAT `.uat-phase06` dihentikan setelah penerimaan (DEC-27) | Terpenuhi |
| Ability surat | `letters.view`, `letters.manage`, `letters.approve`, `letters.sign` sudah terdefinisi; role awal sudah memetakan admin/operator (`view`/`manage`), kepala sekolah (`view`/`approve`/`sign`), wakil kesiswaan (`view`) | Terpenuhi |
| Skema surat | Tidak ada tabel, model, migration, route, controller, request, atau tes surat | Perlu dibuat |
| UI surat | Tidak ada halaman/route; hanya item menu dummy `Letters Form` dan label ability di Roles | Perlu dibuat |
| File privat | Pola storage privat + download berizin sudah ada pada modul impor; backup memindai `storage/app/attachments` | Dapat dipakai ulang dengan path baru |
| Backup lampiran | `config/backup.php` menunjuk `storage/app/attachments`; direktori belum ada; manifest menyatakan modul lampiran operasional belum ada | Perlu diselaraskan saat 07a |
| Data untuk 07c | Siswa dan pegawai sudah tersedia untuk auto-fill; letterhead/signatory/stempel belum ada di `school_profiles` | Gap 07c, bukan 07a |
| PDF | Tidak ada library PDF di `composer.json`/`composer.lock` | Gate dependency 07c |
| UAT | `.uat-phase06` dipertahankan; server 14024/18024 berhenti; belum ada `.uat-phase07` | Perlu lingkungan baru |
| Keputusan | DEC-06 **CLOSED** melalui DEC-33/D4–D5 (07b); DEC-11 (kop/tanda tangan/stempel/jenis surat pertama) **OPEN** | DEC-11 gate 07c |

Catatan: seluruh kondisi di atas diverifikasi dari source, migration, konfigurasi, dan dokumen aktual, bukan dari data operasional.

---

## 2. Temuan audit (fakta kode)

### 2.1 Belum ada implementasi surat

- Tidak ada route surat di `laravel-api/routes/api.php` (pencarian `letters` pada 2026-11-01 tidak menemukan route).
- Tidak ada model `Letter` di `laravel-api/app/Models/`.
- Tidak ada migration bernama `*letter*` di `laravel-api/database/migrations/`; migration terbaru adalah `2029_08_01_000003_add_students_correct_locked_ability.php`.
- Tidak ada route surat di `vue-app/src/router/index.js` dan tidak ada halaman surat di `vue-app/src/views/pages/public/`.
- Satu-satunya jejak UI adalah item dummy di `vue-app/src/layout/AppMenu.vue:48`:
  ```js
  { label: 'Letters Form', icon: 'pi pi-fw pi-book', to: '#' },
  ```
  Item ini harus diganti dengan route nyata; item dummy tidak boleh dipertahankan.
- Label ability surat sudah ada di UI pengelolaan peran: `vue-app/src/views/pages/master/Roles.vue:51-54` (`letters.view`, `letters.manage`, `letters.approve`, `letters.sign`).

**Implikasi 07a:** seluruh fondasi surat masuk (skema, model, service, endpoint, UI, tes) harus dibuat; tidak ada consumer lama yang perlu dipertahankan selain item menu dummy.

### 2.2 Ability dan pemetaan peran sudah siap

- Definisi ability: `laravel-api/app/Support/AbilityCatalog.php:53-56`. `letters.approve` dan `letters.sign` bersifat sensitif; admin teknis tidak otomatis mendapatkannya.
- Pemetaan role awal:
  - `admin-sistem` dan `operator-sekolah`: `letters.view`, `letters.manage` (`AbilityCatalog.php:92`, `:107`).
  - `kepala-sekolah`: `letters.view`, `letters.approve`, `letters.sign` (`:120`).
  - `wakil-kesiswaan`: `letters.view` (`:137`).
- Migration awal `2029_07_20_000003_seed_and_map_roles.php:35-67` menyemai katalog kemampuan dan memetakan role awal hanya bila role belum memiliki ability.

**Implikasi 07a:** tidak perlu ability baru atau migration peran. `letters.view` dipakai untuk baca dan download; `letters.manage` untuk tulis. `letters.approve`/`letters.sign` tidak dipakai pada 07a dan tetap menjadi fondasi 07b. Verifikasi keberadaan baris ability di DB target saat implementasi; bila baris lama tidak ada, tambahkan data migration additive yang hanya menyisipkan ability surat yang hilang tanpa mengubah pemetaan role.

### 2.3 Pola file privat dan download berizin sudah ada

- `laravel-api/config/filesystems.php:39-43`: disk `local` berakar di `storage_path('app')`, terpisah dari disk `public`.
- `laravel-api/phpunit.xml:28`: `FILESYSTEM_DISK=testing` dengan root `storage/framework/testing/disks/testing` (`config/filesystems.php:33-37`).
- `laravel-api/config/imports.php:13`: pemilihan disk privat memakai config (`IMPORTS_DISK`, default `local`).
- `laravel-api/app/Services/Imports/ImportService.php:147-150`: file privat disimpan dengan path yang dibuat server (`imports/{token}/source.csv`), bukan nama dari pengguna.
- `laravel-api/app/Http/Controllers/ImportController.php:159-176`: download memakai `response()->streamDownload(...)` dan memeriksa keberadaan file; `:179-187` membatasi akses ke pembuat batch dan mengembalikan `404` untuk objek yang tidak cocok.
- `laravel-api/tests/Feature/Import/StudentImportTest.php:35` memakai `Storage::fake('local')`; pola ini dapat dipakai untuk tes lampiran surat.

**Implikasi 07a:** lampiran surat harus memakai disk privat, nama file acak yang dibuat server, path tersimpan di DB, dan endpoint download berizin. Jangan memakai `Storage::url()` atau disk `public`.

### 2.4 Backup sudah menyiapkan direktori lampiran, tetapi manifest masih menyatakan belum ada modul lampiran

- `laravel-api/config/backup.php:16-22`: backup archive berada di `storage/app/backups`; sumber lampiran default `storage/app/attachments`; drill memakai direktori terpisah.
- `laravel-api/app/Services/BackupService.php:54` memindai `config('backup.attachments_path')` dan menyalin seluruh isi direktori ke arsip.
- `BackupService.php:122` masih menulis `operational_attachments_consistency => 'N/A (operational attachment modules not yet implemented; verified via synthetic fixtures)'`.
- Saat audit, `laravel-api/storage/app/` hanya berisi `backups/` dan `public/`; subdirektori `attachments/` belum ada.

**Implikasi 07a:** simpan lampiran surat di bawah `attachments/` pada disk `local` (mis. `attachments/letters/{letter_id}/{uuid}.{ext}`) agar otomatis tercakup backup yang sudah ada. Perbarui catatan manifest agar tidak lagi menyatakan modul lampiran belum ada, dan tambahkan pemeriksaan path/backup. Restore drill penuh dengan lampiran surat tetap **NOT RUN** sampai benar-benar dijalankan.

### 2.5 Data letterhead/signatory untuk 07c belum tersedia

- `school_profiles` menyimpan `education_school_npsn`, `principal_id` (FK ke `users`), `school_year_id`, status principal, NDS/NSS/NIS/NRKS, TMT principal, official number, email, website, dan komite: `laravel-api/database/migrations/2026_10_15_150625_create_school_profiles_table.php:15-36`.
- Tidak ada kolom logo, kop surat, alamat kop, tanda tangan gambar, stempel, atau format nomor.
- `education_schools` hanya memiliki nama, NPSN, level, status, alamat, dan district: `laravel-api/database/migrations/*_create_education_schools_table.php:17-24`.
- `principal_id` menunjuk ke `users`, bukan `employees`.

**Implikasi 07c:** DEC-11 tetap OPEN dan memerlukan keputusan pemilik tentang kop, tanda tangan, stempel, identitas penandatangan, dan jenis surat pertama. Jangan menebak format atau identitas penandatangan. 07a tidak membutuhkan data ini.

### 2.6 Data siswa/pegawai siap untuk auto-fill 07c, tetapi snapshot wajib

- `students` memiliki nama, NIK, tempat/tanggal lahir, gender, agama, alamat, NIS, NISN, dan data keluarga: `laravel-api/database/migrations/2029_07_06_215144_create_students_table.php:17-41`.
- `employees` memiliki `name` dan `nik` setelah 04b: `laravel-api/database/migrations/2029_07_26_000001_add_profile_fields_to_employees.php:16-17`; data profil lain tersedia pada model/migration 04a/04b.
- Status/kelas siswa hidup pada `student_rombels`, bukan `students` (pola 06a/06c).

**Implikasi 07c:** auto-fill dapat memakai siswa/pegawai, tetapi surat final harus menyimpan snapshot biodata/penandatangan/template saat terbit agar perubahan master tidak mengubah surat issued. Ini tidak diimplementasikan pada 07a.

### 2.7 Tidak ada dependency PDF

- `laravel-api/composer.json:7-13` hanya memuat PHP/Laravel dan dependency yang sudah ada; pencarian `dompdf`, `mpdf`, `snappy`, dan `fpdf` pada `composer.json`/`composer.lock` tidak menemukan library PDF.
- Aturan pemilik melarang update dependency/lockfile tanpa persetujuan terpisah.

**Implikasi 07c:** 07c tidak dapat menghasilkan PDF server-side tanpa keputusan dependency. Opsi yang perlu diputuskan saat 07c: menambah library PDF yang maintained (dengan persetujuan eksplisit), atau menunda 07c. Jangan menambah dependency pada 07a.

### 2.8 Pola tes dan UAT terisolasi

- Tes backend memakai Pest + `RefreshDatabase`; `phpunit.xml` memaksa SQLite in-memory (MariaDB **NOT RUN**), dan storage tes memakai disk `testing`/`Storage::fake('local')`.
- Launcher UAT fase 06 memisahkan runtime, environment, SQLite, storage, cookie, dan guard isolasi: `laravel-api/scripts/phase06-uat.php:1-60`.
- Pola port per fase: 04b `14022/18022`, 05 `14023/18023`, 06 `14024/18024`; 07 memakai `14025/18025`.
- `.gitignore:26-27` baru memuat `.uat-phase04b/` dan `.uat-phase05/`; `.uat-phase06/` masih terlihat sebagai untracked dan sengaja tidak di-stage. 07a harus menambahkan `.uat-phase07/` agar runtime baru tidak ikut ter-stage.

**Implikasi 07a:** buat launcher `scripts/phase07-uat.php` dengan pola isolasi yang sama; gunakan `Storage::fake('local')`/config disk terisolasi pada tes; jangan menyentuh `.uat-phase05`, `.uat-phase06`, atau data operasional.

### 2.9 Status keputusan

- `docs/DECISIONS.md:290` — **DEC-06 OPEN**, scope 07b: format nomor, reset counter, pejabat penandatangan, kapan nomor diterbitkan.
- `docs/DECISIONS.md:302` — **DEC-11 OPEN**, scope 07a/07c: kop, tanda tangan, stempel, jenis surat pertama, format rekap.
- `docs/DECISIONS.md:304` — **DEC-13 OPEN**, scope 09b/13: retensi data pribadi dan pemusnahan lampiran.

**Implikasi:** 07a tidak menanyakan DEC-06/DEC-11 dan tidak menebaknya. DEC-11 tetap menjadi gate 07c; DEC-06 menjadi gate 07b. DEC-13 berarti lampiran 07a belum memiliki kebijakan pemusnahan; file yang dihapus secara domain disimpan sampai keputusan retensi.

---

## 3. Urutan pekerjaan yang diusulkan

| Langkah | Pekerjaan | Hasil yang dapat diverifikasi |
|---|---|---|
| S0 | Preflight: audit HEAD, pastikan `.uat-phase07` belum ada, verifikasi ability surat, tidak ada tabel surat; tambah `.uat-phase07/` ke `.gitignore` | Baseline dan isolasi jelas; tidak ada perubahan tabel lama |
| S1 | Migration additive `letters`, `letter_attachments`, `letter_events`; model, cast, relasi, allowlist audit | Tabel/model ada; tabel domain lain tidak berubah |
| S2 | `config/letters.php` + `LetterAttachmentService` (store/delete/download path) | Path privat `attachments/letters/...`, nama acak, checksum, kompensasi file |
| S3 | `LetterService` create/update/void dengan transaksi + versi + event; FormRequest; controller CRUD/void; route `letters.view`/`letters.manage` | Create/update/void atomik; `409` tanpa mutasi; event/audit tercatat |
| S4 | Endpoint lampiran upload/download/delete; validasi MIME/ukuran/jumlah; header keamanan; object-level authorization | Lampiran privat hanya dapat diakses berizin; file berbahaya ditolak |
| S5 | List/detail/options: search, filter tanggal/kategori/status, pagination, sort, timeline event | Agenda dapat dicari/difilter; respons tanpa path storage |
| S6 | UI `IncomingLetters.vue` + dialog form/void/detail/attachments; router; menu grup Letters; hapus dummy `Letters Form` | Alur English, view-only, 409 reload, 390 px |
| S7 | Tes backend T1–T16 + regresi suite terkait + Pint + `route:list` | Matriks §6 PASS; tidak ada regresi |
| S8 | UAT `.uat-phase07`: launcher `init`/`prepare`/`prepare-letters`/`status`, fixture ≥120 surat + lampiran, browser/API checklist, laporan | UAT siap review; bukti aktual tercatat |
| S9 | Sinkronisasi DATABASE/API-CONTRACTS/REQUIREMENTS/ACCESS-CONTROL/OPERATIONS/CHANGELOG/laporan/INDEX/HANDOFF/ROADMAP/fase; commit+push paket review | Paket review dapat dibaca di GitHub; server tetap berjalan sampai penerimaan |

Setelah S9, berhenti untuk review pemilik. Tidak ada merge, deploy, atau lanjutan otomatis ke 07b/07c.

---

## 4. Keputusan yang perlu pemilik (D1–D10)

Rekomendasi ditulis sebagai default. Setelah disetujui, keputusan dicatat sebagai **DEC-32** sebelum implementasi.

| # | Pertanyaan | Rekomendasi | Alternatif / dampak |
|---|---|---|---|
| **D1** | Subfase mana yang diotorisasi oleh rencana ini? | **07a saja.** 07b/07c hanya digambarkan di §12 dan memerlukan rencana serta keputusan terpisah. DEC-06/DEC-11 tetap OPEN dan tidak ditebak. | Mengerjakan seluruh Fase 07 sekaligus (melanggar aturan satu subfase per instruksi; risiko besar). |
| **D2** | Model data dan referensi agenda? | Tiga tabel additive: `letters` (berbagi untuk incoming/outgoing, `direction`), `letter_attachments`, `letter_events`. Referensi internal agenda = `#` + `id` yang di-pad (mis. `#000123`); **bukan** nomor resmi. `source_number` disimpan sebagai string, **nullable**, di-index, dan **tidak unik**. Format nomor resmi/agenda berformat ditunda ke 07b/DEC-06. | Tabel `incoming_letters` terpisah (duplikasi saat 07b); nomor agenda berformat sekarang (mengambil keputusan penomoran 07b dan memerlukan counter konkurensi lebih awal). |
| **D3** | Field wajib dan aturan tanggal? | `sender` (≤150), `subject` (≤200), `category` (daftar config), `received_date`, `letter_date` wajib. `source_number` opsional (≤100). `notes` opsional (≤2000). `received_date` tidak boleh melewati hari ini (Asia/Jakarta); `letter_date` tidak boleh melewati `received_date`. Tidak ada FK tahun ajaran; surat tidak terikat tahun akademik. | `source_number` wajib (menghalangi surat tanpa nomor); tanggal masa depan diizinkan (agenda tidak stabil); FK tahun ajaran (tidak perlu dan menyulitkan surat lintas tahun). |
| **D4** | Bagaimana kategori surat? | Daftar tetap di `config/letters.php`, divalidasi `Rule::in`, disimpan string(50). Default usulan: `Government`, `Internal`, `Invitation`, `Notification`, `Request`, `Partnership`, `Other`. Nilai kategori adalah data; label UI tetap English. Pemilik dapat mengganti daftar sebelum implementasi. Tidak ada CRUD kategori pada 07a. | Tabel master `letter_categories` + halaman/ability baru (menambah scope dan menu); teks bebas (filter tidak konsisten). |
| **D5** | Penyimpanan dan keamanan lampiran? | `letter_attachments`; disk privat `local` (config `letters.disk`, default `local`); path `attachments/letters/{letter_id}/{uuid}.{ext}` (berada di `storage/app/attachments/...`, tercakup backup); hanya PDF/JPG/JPEG/PNG; maksimum 10 MB per file; maksimum 5 file per surat; MIME dideteksi server; nama file acak; `sha256`; nama asli hanya untuk tampilan; tanpa URL publik; download hanya lewat endpoint berizin dengan `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, dan CSP sandbox; hapus lampiran = soft delete baris, file tetap disimpan sampai keputusan retensi DEC-13; tidak ada overwrite. | Disk `public` (melanggar aturan file privat); DOCX/SVG/HTML (risiko makro/XSS); hard delete file (kehilangan bukti sebelum DEC-13); tanpa checksum (integritas sulit diverifikasi). |
| **D6** | Kebijakan edit, void, dan hapus? | Tidak ada hard delete surat. `letters.version`; update wajib `expected_version` dan menghasilkan `+1`; void wajib alasan 10–255 + `expected_version`, status `active` → `void`, `voided_at`/`voided_by` diisi. Daftar default hanya `active`; filter `status=void` menampilkan yang void. Surat void tidak dapat diedit/ditambah/dihapus lampirannya (`409`). Tidak ada unvoid pada 07a. Lampiran surat void tetap dapat diunduh oleh `letters.view` karena record dipertahankan. | Hard delete (kehilangan audit); unvoid sekarang (kompleks dan belum diputuskan); menyembunyikan surat void sepenuhnya (kehilangan jejak). |
| **D7** | Hak akses 07a? | `letters.view` untuk list/detail/options/download/history; `letters.manage` untuk create/update/void/upload/delete lampiran. `letters.approve`/`letters.sign` **tidak dipakai** pada 07a. Role awal yang ada dipakai apa adanya (admin/operator manage; kepala sekolah/wakil view). Tidak ada ability/role migration baru. Server menegakkan `can:` + `authorize`; UI hanya menyembunyikan tombol. | Memberi approve/sign sekarang (di luar scope); hanya admin yang boleh manage (operator tidak dapat bekerja); ability baru (tidak perlu). |
| **D8** | Kontrak API, daftar, dan UI? | Endpoint `/api/letters/incoming` (lihat §5.5). Pencarian server-side pada `source_number`/`sender`/`subject`; filter kategori, status, dan rentang `received_date`; pagination 10/20/50/100 default 20; sort `received_date` desc lalu `id` desc. Detail memuat lampiran + event (maks. 100 terbaru). Halaman `/public/letters/incoming` (nama route `incomingLetters`, breadcrumb `Incoming Letters`), grup menu **Letters** → **Incoming Letters**; item dummy `Letters Form` dihapus. English, 390 px, keyboard accessible; view-only read-only; `409` menampilkan versi terbaru dan tombol Reload. | Filter client-side (tidak skalabel); halaman terpisah per aksi (navigasi berlebihan); mempertahankan menu dummy (dilarang). |
| **D9** | Audit, history, dan privasi? | `Letter` dan `LetterAttachment` memakai `Loggable` dengan allowlist. `activities` **tidak** memuat `notes`, `sender`, `subject`, `void_reason`, `original_name`, `path`, atau `checksum`; yang dicatat adalah field operasional (tanggal, nomor sumber, kategori, status, versi, ukuran/MIME). `letter_events` immutable (tanpa update/delete) mencatat `created`, `updated`, `voided`, `attachment_added`, `attachment_removed`, aktor, waktu, dan metadata minimal (mis. daftar nama field yang berubah, `attachment_id`, MIME/ukuran); tanpa isi file dan tanpa PII. Event dapat dibaca `letters.view`. `LetterEvent` tidak memakai `Loggable` agar tidak duplikasi. Catatan manifest backup diperbarui agar tidak lagi menyatakan modul lampiran belum ada. | Hanya `activities` (tidak terlihat operator dan tidak memenuhi history UI); menyimpan diff penuh (risiko PII); menyimpan nama file di audit (PII). |
| **D10** | UAT dan non-goals? | Lingkungan baru **`.uat-phase07`** (SQLite; API `127.0.0.1:18025`; SPA `127.0.0.1:14025`; cookie `school_phase07_session`; storage `.uat-phase07/storage`; launcher `scripts/phase07-uat.php`). Perintah idempotent `prepare-letters`: ≥120 surat sintetis, kategori dari config, ≥40 lampiran PDF/JPG/PNG, ≥8 surat void, duplikat nomor sumber, ≥3 surat tanpa nomor sumber, tanggal tersebar, dan skenario file berbahaya. Tidak menyentuh `.uat-phase05`/`.uat-phase06` atau data operasional. Tambah `.uat-phase07/` ke `.gitignore`. Server dihentikan setelah penerimaan (DEC-27); data dipertahankan. Non-goals: 07b/07c, disposisi, penomoran, approval/sign, template/PDF, OCR/e-sign/email/WhatsApp, arsip/versioning Fase 09, retensi/pemusnahan (DEC-13), antivirus, full-text search, ekspor, Dapodik, merge/deploy, dependency/service/`.env`. | Memakai ulang `.uat-phase06` (mencampur domain dan data fase lama); fixture <100 baris (melanggar kebijakan data sintetis); menyalakan server tanpa batas waktu (melanggar DEC-27). |

**Ringkasan pertanyaan terbuka 07a:**

| Q | Jawaban yang diusulkan |
|---|---|
| Q1 — apakah nomor sumber unik? | Tidak. Nomor sumber adalah atribut surat, bukan kunci internal; duplikat diperbolehkan dan di-index untuk pencarian (D2). |
| Q2 — apakah ada nomor agenda berformat? | Tidak pada 07a; referensi internal memakai `#` + id. Format resmi ditunda ke 07b/DEC-06 (D2). |
| Q3 — apakah surat boleh dihapus? | Tidak; hanya void beralasan dan berversi. Lampiran di-soft-delete tetapi file disimpan sampai DEC-13 (D5/D6). |
| Q4 — siapa yang boleh mengunggah/menghapus lampiran? | `letters.manage`; `letters.view` hanya membaca/mengunduh (D7). |
| Q5 — apakah view-only dapat melihat surat void? | Ya, dengan status yang jelas dan filter khusus; record tidak disembunyikan (D6). |

---

## 5. Desain teknis

### 5.1 Skema `letters` (migration additive)

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | Referensi internal agenda ditampilkan `#` + id |
| `version` | unsigned bigint, default 1 | Naik saat update/void |
| `direction` | string(10), default `incoming` | `incoming` pada 07a; `outgoing` ditambahkan 07b |
| `received_date` | date | Tanggal terima; wajib |
| `letter_date` | date | Tanggal surat; wajib; ≤ `received_date` |
| `source_number` | string(100) nullable, index | Nomor dari pengirim; **tidak unik** |
| `sender` | string(150) | Pengirim/perihal organisasi; wajib |
| `subject` | string(200) | Perihal; wajib |
| `category` | string(50), index | Divalidasi terhadap config; wajib |
| `notes` | text nullable | Catatan internal; tidak masuk audit |
| `status` | string(10), default `active`, index | `active` atau `void` |
| `voided_at` | timestamp nullable | — |
| `voided_by` | FK `users` nullable | — |
| `void_reason` | string(255) nullable | Wajib saat void; tidak masuk audit |
| `created_by` | FK `users` | Aktor pembuat; restrict delete |
| `timestamps` | — | — |

- Index tambahan: `(direction, received_date)`, `(direction, status)`, `(category)`, `(source_number)`.
- Tidak ada soft delete pada `letters`; penghapusan domain tidak tersedia. `void` adalah state bisnis, bukan delete.
- Tidak ada unique index pada `source_number`; duplikat adalah kasus sah.
- 07b dapat menambah kolom outgoing (nomor, signatory, status alur) lewat migration additive tanpa mengubah tabel ini secara destruktif.

### 5.2 Skema `letter_attachments` (migration additive)

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | — |
| `letter_id` | FK `letters` | Restrict delete; surat tidak di-hard-delete |
| `original_name` | string(255) | Nama asli untuk tampilan; disanitasi; tidak masuk audit |
| `path` | string(255), unique | Path server-generated pada disk privat |
| `mime_type` | string(100) | Hasil deteksi server; harus allowlist |
| `size_bytes` | unsigned bigint | Ukuran aktual |
| `checksum_sha256` | string(64) | Integritas file |
| `uploaded_by` | FK `users` | Aktor upload |
| `deleted_at` | timestamp nullable | Soft delete; file tetap disimpan |
| `timestamps` | — | — |

- Index: `(letter_id)`, `(deleted_at)`.
- SoftDeletes dipakai agar file dan metadata tidak hilang sebelum keputusan retensi DEC-13.
- Path fisik: `storage/app/attachments/letters/{letter_id}/{uuid}.{ext}` pada disk `local`; backup memindai `storage/app/attachments`.

### 5.3 Skema `letter_events` (migration additive)

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | — |
| `letter_id` | FK `letters` | Restrict delete |
| `event` | string(30), index | `created`, `updated`, `voided`, `attachment_added`, `attachment_removed` |
| `actor_id` | FK `users` | Aktor; non-null |
| `metadata` | json nullable | Metadata minimal; tanpa PII/isi file |
| `created_at` | timestamp | — |

- Index: `(letter_id, created_at)`, `(event)`.
- Immutable: model menolak update/delete; tidak ada endpoint edit/hapus event.
- Metadata `updated`: `{ "changed_fields": ["subject", "category"] }`; `attachment_added`: `{ "attachment_id": 12, "mime_type": "application/pdf", "size_bytes": 12345 }`; `attachment_removed`: `{ "attachment_id": 12 }`.
- Event bukan pengganti audit keamanan; `activities` tetap mencatat perubahan model.

### 5.4 Konfigurasi `config/letters.php`

```php
return [
    'disk' => env('LETTERS_DISK', 'local'),
    'attachments_path' => 'attachments/letters',
    'max_file_kb' => (int) env('LETTERS_MAX_FILE_KB', 10240),
    'max_files_per_letter' => (int) env('LETTERS_MAX_FILES_PER_LETTER', 5),
    'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    'allowed_mimetypes' => ['application/pdf', 'image/jpeg', 'image/png'],
    'categories' => [
        'Government',
        'Internal',
        'Invitation',
        'Notification',
        'Request',
        'Partnership',
        'Other',
    ],
    'search_max_length' => 100,
    'per_page_options' => [10, 20, 50, 100],
    'default_per_page' => 20,
];
```

- Tidak ada perubahan `.env` utama; nilai default cukup untuk pengembangan dan UAT.
- Jika pemilik mengganti daftar kategori, ubah config sebelum implementasi; jangan menebak kategori di kode.

### 5.5 Endpoint (usulan)

| Method | Endpoint | Ability | Perilaku |
|---|---|---|---|
| GET | `/api/letters/incoming/options` | `letters.view` | Kategori, status, opsi `per_page` |
| GET | `/api/letters/incoming?search=&category=&status=&received_from=&received_to=&per_page=&page=` | `letters.view` | Daftar agenda; server-side filter/pagination |
| GET | `/api/letters/incoming/{letter}` | `letters.view` | Detail + lampiran + event (maks. 100 terbaru) |
| POST | `/api/letters/incoming` | `letters.manage` | Buat surat masuk |
| PUT/PATCH | `/api/letters/incoming/{letter}` | `letters.manage` | Update metadata; wajib `expected_version` |
| POST | `/api/letters/incoming/{letter}/void` | `letters.manage` | Void beralasan; wajib `expected_version` |
| POST | `/api/letters/incoming/{letter}/attachments` | `letters.manage` | Upload multipart (`file`) |
| GET | `/api/letters/incoming/{letter}/attachments/{attachment}/download` | `letters.view` | Stream unduhan; `Content-Disposition: attachment` |
| DELETE | `/api/letters/incoming/{letter}/attachments/{attachment}` | `letters.manage` | Soft delete lampiran; file disimpan |

- `{attachment}` harus milik `{letter}`; jika tidak, `404`.
- Route memakai middleware `auth:sanctum`, `active`, dan `can:letters.*`; controller juga memanggil `authorize` sebagai pertahanan berlapis (pola `StudentMutationController.php:22-24`).
- Respons memakai envelope `StatusResource` (`success`, `message`, `data`); daftar memuat `items`, `meta`, dan `filters` seperti pola 06c (`StudentMutationService.php:159-181`).
- Respons tidak memuat `path`, `checksum`, atau URL storage; frontend membangun URL download dari id.

Contoh create:

```json
{
  "received_date": "2026-11-01",
  "letter_date": "2026-10-30",
  "source_number": "005/123/DISDIK/2026",
  "sender": "Dinas Pendidikan Kabupaten",
  "subject": "Undangan rapat koordinasi",
  "category": "Invitation",
  "notes": "Diterima melalui front office."
}
```

Contoh update:

```json
{
  "received_date": "2026-11-01",
  "letter_date": "2026-10-30",
  "source_number": "005/123/DISDIK/2026",
  "sender": "Dinas Pendidikan Kabupaten",
  "subject": "Undangan rapat koordinasi (revisi)",
  "category": "Invitation",
  "notes": "Perihal diperbaiki.",
  "expected_version": 1
}
```

Contoh void:

```json
{
  "reason": "Duplicate entry; the original letter is registered as #000045.",
  "expected_version": 2
}
```

- `409` memuat versi/state terkini dan tidak mengubah data. `422` memuat error validasi per field.
- Upload lampiran memakai `multipart/form-data` dengan field `file`; ukuran/jumlah/MIME divalidasi server.

### 5.6 Aturan operasi

| Operasi | Pemeriksaan | Hasil gagal |
|---|---|---|
| Create | Field wajib; kategori ada di config; `received_date` ≤ hari ini (Asia/Jakarta); `letter_date` ≤ `received_date`; panjang field; `source_number` boleh duplikat | `422`; nol baris |
| Update | Surat ada; status `active`; `expected_version` cocok; validasi field sama; transaksi lock | `404`/`409`; nol mutasi |
| Void | Surat ada; status `active`; `expected_version` cocok; alasan 10–255; transaksi lock | `404`/`409`/`422`; nol mutasi |
| Upload | Surat `active`; file valid (MIME/ukuran); jumlah lampiran < 5; tulis file; transaksi lock + hitung ulang; insert attachment + event | `422`/`409`; file yang sudah ditulis dihapus |
| Delete attachment | Surat `active`; lampiran milik surat; belum deleted; lock; soft delete + event; file disimpan | `404`/`409`; nol mutasi |
| Download | `letters.view`; surat/lampiran ada; lampiran milik surat; belum deleted; file ada di disk | `401`/`403`/`404`; tidak ada kebocoran path |

- Update/void tidak mengizinkan perubahan pada surat `void`; upload/delete lampiran juga ditolak pada surat `void`.
- `source_number` disimpan setelah trim; tidak diubah menjadi uppercase dan tidak dianggap unik.
- Tanggal memakai format `Y-m-d`; validasi "hari ini" mengikuti timezone aplikasi (Asia/Jakarta) dan pola 06c.

### 5.7 Transaksi, lock, dan kompensasi file

1. Validasi request di FormRequest (tipe, panjang, format tanggal, kategori, `expected_version`, file).
2. `DB::transaction`:
   - Lock baris `letters` target (`lockForUpdate`); validasi status/versi.
   - Untuk update/void: terapkan perubahan, `version + 1`, insert `letter_events`.
   - Untuk upload: hitung lampiran non-deleted; jika sudah 5 → `422`/`409`; insert attachment + event.
   - Untuk delete attachment: lock attachment, soft delete, insert event.
3. Upload menulis file **sebelum** transaksi DB; jika transaksi gagal, file dihapus (best effort) dan exception diteruskan. Jika penulisan file gagal, tidak ada baris DB.
4. Tidak ada token idempotensi pada 07a. Update/void aman dari replay karena versi/status; create dapat menghasilkan duplikat jika request dikirim ulang, tetapi duplikat nomor sumber memang sah dan UI mencegah double-submit.
5. Kegagalan audit/event membatalkan transaksi; tidak ada versi/event yang tertinggal.

### 5.8 Layanan file minimal

`LetterAttachmentService` menyediakan:

- `store(Letter $letter, UploadedFile $file, User $actor): LetterAttachment`
  - Menentukan ekstensi dari MIME yang lolos validasi (bukan dari nama klien).
  - Membuat nama `uuid` + ekstensi; path `attachments/letters/{letter_id}/{uuid}.{ext}`.
  - Menulis ke disk `config('letters.disk')`; menghitung `sha256`.
  - Menyimpan metadata; kompensasi hapus file bila insert/event gagal.
- `delete(LetterAttachment $attachment, User $actor): void`
  - Soft delete baris; file tetap disimpan; event `attachment_removed`.
- `stream(LetterAttachment $attachment)` atau controller `streamDownload`
  - Memeriksa keberadaan file; nama unduhan dari `original_name` yang disanitasi; header keamanan.

Layanan ini tidak membangun versioning/arsip; Fase 09 dapat memperluas atau memetakan ke `document_files`.

### 5.9 Keamanan lampiran

- Allowlist ekstensi + MIME: `pdf`, `jpg`, `jpeg`, `png`; `application/pdf`, `image/jpeg`, `image/png`. Tolak SVG, HTML, arsip, executable, dan file dengan ekstensi ganda.
- Deteksi MIME dari isi file (fileinfo), bukan `Content-Type` klien; untuk gambar, validasi `getimagesize` agar file bukan polyglot.
- Nama file acak; path dibuat server; tidak menerima path/nama dari klien sebagai lokasi simpan.
- Download: `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: sandbox`, `Cache-Control: private, no-store`; tidak pernah `inline`.
- Tidak ada symlink `public/storage` untuk lampiran; tidak ada URL publik.
- Sanitasi nama unduhan: `basename`, buang CR/LF/quote, fallback `attachment-{id}.{ext}`.
- Tanpa antivirus/OCR; itu non-goal dan dicatat sebagai risiko.

### 5.10 Audit dan history

- `Letter` allowlist: `direction`, `received_date`, `letter_date`, `source_number`, `category`, `status`, `version`, `voided_at`, `voided_by`, `created_by`. Tidak ada `sender`, `subject`, `notes`, `void_reason`.
- `LetterAttachment` allowlist: `letter_id`, `mime_type`, `size_bytes`, `deleted_at`. Tidak ada `original_name`, `path`, `checksum_sha256`.
- `letter_events` immutable; event `updated` hanya menyimpan daftar field yang berubah; event lampiran hanya id/MIME/ukuran.
- `activities` tetap mencatat create/update/soft-delete model; event domain memberi history yang dapat dibaca pengguna `letters.view`.
- Tidak ada NIK/nama siswa pada 07a karena surat belum terhubung ke siswa; jika 07c menambahkan relasi, snapshot dan kebijakan PII diputuskan terpisah.

### 5.11 UI (English)

Halaman **Letters → Incoming Letters**:

1. **Header**: judul `Incoming Letters`, deskripsi agenda, tombol **New Letter** (hanya `letters.manage`).
2. **Filters**: search (source number/sender/subject), category, status (`Active` default, `Void`, `All`), received date range, page size (10/20/50/100).
3. **Table**: Agenda (`#000123`), Received, Letter Date, Source No., Sender, Subject, Category, Attachments (count), Status, Actions. Sort default terbaru.
4. **Detail dialog**: metadata lengkap, notes, daftar lampiran (nama, tipe, ukuran, tombol Download), tombol Upload/Delete untuk manage, dan timeline history (actor/time/event).
5. **Create/Edit dialog**: field sesuai D3; validasi inline; tombol Save; `409` menampilkan versi terbaru dan **Reload**.
6. **Void dialog**: ringkasan surat, alasan wajib, konfirmasi; setelah sukses status berubah dan daftar disegarkan.
7. **View-only**: tanpa tombol New/Edit/Void/Upload/Delete; server tetap `403` bila dipanggil langsung.
8. **Responsif/aksesibilitas**: 390 px tanpa overflow; label field; fokus keyboard; dialog dapat ditutup dengan Escape; tombol ikon memiliki `aria-label`.

Menu: hapus item dummy `Letters Form` dari grup Public; tambah grup **Letters** dengan item **Incoming Letters** (`pi pi-fw pi-envelope`, route `/public/letters/incoming`, requires `letters.view`). 07b akan menambah item Outgoing; jangan membuat tab/tombol dummy sekarang.

### 5.12 Integrasi backup

- Lampiran berada di `storage/app/attachments/letters/...`; `BackupService` memindai `config('backup.attachments_path')` dan menyalin seluruh isi, sehingga lampiran surat ikut tanpa perubahan mekanisme backup.
- Perbarui narasi manifest `BackupService.php:122` agar tidak lagi menyatakan modul lampiran belum ada.
- Tambahkan tes/assertion path: path lampiran harus berada di bawah `config('backup.attachments_path')`.
- Restore drill penuh dengan lampiran surat **NOT RUN** sampai dijalankan; catat di laporan.

---

## 6. Matriks tes

Backend (Pest, SQLite in-memory; MariaDB tetap NOT RUN):

| # | Skenario | Ekspektasi |
|---|---|---|
| T1 | Guest, kepala sekolah, wakil, operator, admin mengakses endpoint | Guest `401`; kepala/wakil boleh baca, tulis `403`; operator/admin CRUD sesuai ability |
| T2 | Create surat valid | Baris tersimpan; `direction=incoming`, `status=active`, `version=1`, `created_by`; event `created`; audit `Created` |
| T3 | Create dengan dua nomor sumber sama | Keduanya tersimpan; tidak ada unique violation; daftar menampilkan keduanya |
| T4 | Create tanpa nomor sumber | Tersimpan; `source_number=null`; pencarian tidak error |
| T5 | Validasi create: field wajib, kategori invalid, panjang berlebih, `received_date` masa depan, `letter_date` > `received_date` | `422`; nol baris; pesan field jelas |
| T6 | Update valid | Field berubah; `version+1`; event `updated` dengan `changed_fields`; audit `Updated`; surat decoy tidak berubah |
| T7 | Update dengan `expected_version` basi / surat void | `409` itemized; nol mutasi; versi/state terkini dikembalikan |
| T8 | Void valid | `status=void`, `voided_at`/`voided_by`/`void_reason`; `version+1`; event `voided`; audit |
| T9 | Void tanpa alasan / alasan <10 / sudah void / versi basi | `422`/`409`; nol mutasi |
| T10 | Update/upload/delete lampiran pada surat void | `409`; nol mutasi; file tidak berubah |
| T11 | Upload PDF/JPG/PNG valid | File tersimpan di `attachments/letters/{id}/`; nama acak; MIME/ukuran/checksum benar; baris + event `attachment_added`; tidak ada path di respons |
| T12 | Upload file berbahaya: `.php`, `.exe`, `.svg`, `.html`, ekstensi ganda, MIME spoof, >10 MB, file ke-6 | `422`; nol baris/event; file tidak tersimpan |
| T13 | Hapus lampiran | Soft delete; file tetap ada; event `attachment_removed`; download `404`; versi surat tidak berubah; audit `Deleted` |
| T14 | Download berizin | `letters.view` mendapat stream; header `Content-Disposition: attachment`, `nosniff`, CSP sandbox; guest `401`; lampiran surat lain `404`; file hilang `404` |
| T15 | Search/filter/pagination | Pencarian nomor/pengirim/perihal; filter kategori/status/rentang tanggal; `per_page` valid; total/meta benar; wildcard `%`/`_` tidak mengubah hasil |
| T16 | History/audit privacy | Event berurutan; `updated` hanya `changed_fields`; tidak ada `notes`/`sender`/`subject`/`original_name`/`path`/`checksum`/`void_reason` di `activities` atau event; aktor/waktu benar |
| T17 | Rollback dan kompensasi | Kegagalan event/DB setelah file ditulis → file dihapus, tidak ada baris/versi/event tertinggal; create/update/void rollback penuh |
| T18 | Isolasi dan route | Tidak ada file di disk `public`; path berada di bawah `backup.attachments_path`; `route:list` memuat sembilan route dengan middleware benar; tidak ada route dummy |
| T19 | Regresi suite penuh | Suite penuh PASS; tidak ada regresi modul siswa/pegawai/impor/akademik |
| T20 | Pint dan build/lint | Pint PASS pada file berubah; `npm run build` PASS; ESLint file berubah 0 error |

Frontend/UX: browser checks untuk daftar, create, edit, upload, download, delete, void, view-only, 409, search/filter, 390 px, English, dan tidak ada error console. Jika browser tidak tersedia, tulis **NOT RUN** dan sediakan checklist manual; jangan mengklaim PASS tanpa bukti.

---

## 7. UAT

- Lingkungan: **`.uat-phase07`** (SQLite), API `127.0.0.1:18025`, SPA `127.0.0.1:14025`, cookie `school_phase07_session`, launcher `laravel-api/scripts/phase07-uat.php`, storage `.uat-phase07/storage`.
- Perintah launcher:
  - `init` — membuat runtime/env/SQLite/storage/password; menolak menimpa runtime yang sudah ada.
  - `prepare` — migrasi normal + seeder akun/referensi; bukan `migrate:fresh`.
  - `prepare-letters` — fixture idempotent; tidak me-reset data lain.
  - `status` — ringkasan runtime dan jumlah data.
- Fixture `prepare-letters`:
  - ≥120 surat sintetis dengan tanggal tersebar (minimal 12 bulan), kategori dari config, sebagian `void`, duplikat `source_number`, ≥3 tanpa nomor sumber, dan catatan panjang.
  - ≥40 lampiran sintetis: PDF satu halaman dan multi-halaman, JPG/JPEG/PNG; ukuran bervariasi; beberapa surat dengan 5 lampiran.
  - File uji berbahaya disiapkan untuk skenario manual (tidak disimpan sebagai lampiran valid): `.php`, `.exe`, `.svg`, `.html`, ekstensi ganda, MIME spoof, file >10 MB.
  - Akun UAT: operator (manage), admin (manage), kepala sekolah (view/approve/sign), wakil kesiswaan (view). Jika seeder belum menyediakan keempatnya, tambahkan pada seeder UAT fase 07, bukan pada data operasional.
- Checklist manual (ditulis lengkap di `PHASE-07A-MANUAL-REVIEW.md` setelah implementasi):
  1. Isolasi: API/SPA hanya 18025/14025; DB/storage `.uat-phase07`; tidak menyentuh `.uat-phase05/06`/operasional.
  2. Login dan izin: operator/admin dapat create/edit/void/upload/delete; kepala/wakil hanya baca/download; server `403` untuk tulis.
  3. Create surat + dua surat dengan nomor sumber sama; buktikan tidak ada unique violation.
  4. Validasi tanggal (masa depan, surat setelah diterima) dan field wajib.
  5. Edit + naik versi; dua tab/versi basi menghasilkan `409` dan tombol Reload.
  6. Upload PDF/JPG/PNG; download; header attachment; file tidak dapat diakses lewat URL publik.
  7. Tolak file berbahaya dan file ke-6/>10 MB.
  8. Hapus lampiran: hilang dari daftar/download, file tetap ada di storage (soft delete).
  9. Void: alasan wajib; default list tidak menampilkan; filter Void menampilkan; edit/upload setelah void ditolak.
  10. Search/filter/pagination: nomor sumber, pengirim, perihal, kategori, status, rentang tanggal.
  11. History: create/update/void/attachment events dengan aktor/waktu; tidak ada PII/nama file di audit.
  12. UI English, 390 px tanpa overflow, keyboard/aria, 0 console error.
  13. Backup path: lampiran berada di `storage/app/attachments/letters`; jalankan backup bila aman dan periksa manifest (jika tidak, **NOT RUN**).
  14. Setelah pemilik menyatakan penerimaan, hentikan server 14025/18025 dengan verifikasi PID/command line; pertahankan `.uat-phase07`; jangan sentuh Apache/MariaDB.
- Server dinyalakan untuk review dan **dihentikan setelah penerimaan** (DEC-27); perintah restart dicatat.

---

## 8. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| File berbahaya/polyglot lolos validasi | Allowlist ekstensi + MIME server; `getimagesize` untuk gambar; nama acak; tolak ekstensi ganda; force download; CSP sandbox |
| XSS dari SVG/HTML | SVG/HTML tidak diizinkan; semua unduhan `attachment`; `nosniff` |
| PDF berisi JavaScript | Tidak dirender inline; `Content-Disposition: attachment`; CSP sandbox |
| Path traversal / nama file jahat | Path dibuat server; nama asli hanya untuk header yang disanitasi |
| File yatim saat DB gagal | Tulis file sebelum transaksi; hapus file pada kegagalan; tes T17 |
| Lampiran tidak terbackup | Path di bawah `storage/app/attachments/letters`; backup memindai direktori itu; update manifest; drill penuh NOT RUN |
| Retensi file soft-deleted | DEC-13 OPEN; file disimpan; catat risiko pertumbuhan storage dan jangan memusnahkan tanpa keputusan |
| Duplikat nomor sumber | Disengaja; tidak ada unique index; UI menampilkan nomor apa adanya |
| Dua operator mengedit bersamaan | `version` + `expected_version` + `409` itemized; nol mutasi |
| Dua upload bersamaan melewati batas 5 | Lock surat + hitung ulang dalam transaksi; file yang kalah dihapus; uji multi-proses NOT RUN |
| Perbedaan LIKE SQLite vs MariaDB | Tes SQLite; MariaDB NOT RUN; escape wildcard; catat risiko |
| Ability surat tidak ada di DB target | Verifikasi saat preflight; data migration additive hanya untuk ability yang hilang; jangan ubah role mapping |
| Menu dummy dihapus memecahkan tautan | Hanya referensi `AppMenu.vue:48`; tidak ada consumer lain |
| 07c membutuhkan dependency PDF | Tidak menambah dependency pada 07a; keputusan terpisah saat 07c |
| DEC-06/DEC-11 ditebak | Dilarang; 07a tidak membutuhkannya; 07b/07c menunggu keputusan |
| Backup manifest masih menyatakan modul lampiran belum ada | Perbarui narasi manifest dan dokumentasi OPERATIONS pada S9 |

---

## 9. Non-goals dan batas otorisasi

- Tidak ada implementasi 07a sebelum **D1–D10 disetujui** dan dicatat sebagai **DEC-32**.
- Tidak ada 07b/07c: disposisi, surat keluar, penomoran, approval/sign, template, PDF, snapshot, kop, tanda tangan, atau stempel.
- Tidak ada OCR, e-sign provider, email/WhatsApp otomatis, antivirus, full-text search, ekspor, atau integrasi Dapodik.
- Tidak ada arsip terpusat/versioning Fase 09 dan tidak ada pemusnahan/retensi DEC-13.
- Tidak ada merge/deploy/force-push/rewrite, migrasi database operasional/MariaDB, perubahan `.env` utama, dependency/lockfile, service, atau akses DB terlarang.
- Tidak ada perubahan pada `.uat-phase05`/`.uat-phase06` atau data operasional; UAT hanya `.uat-phase07`.
- Commit+push paket review mengikuti aturan repo; push bukan penerimaan pemilik. Setelah 07a siap review, berhenti; 07b/07c memerlukan instruksi/rencana terpisah.

---

## 10. Ringkasan keputusan untuk pemilik

| Kode | Ringkasan |
|---|---|
| D1 | Otomasi hanya 07a; 07b/07c menunggu rencana dan DEC-06/DEC-11 |
| D2 | Tabel `letters`/`letter_attachments`/`letter_events`; referensi agenda `#id`; nomor sumber tidak unik |
| D3 | Pengirim, perihal, kategori, tanggal terima, tanggal surat wajib; nomor sumber opsional; tanggal valid |
| D4 | Kategori dari config (default 7 nilai); tanpa master CRUD |
| D5 | Lampiran PDF/JPG/PNG, 10 MB/file, 5/surat, privat di `attachments/letters`, download berizin, soft delete + file disimpan |
| D6 | Tanpa hard delete; update/void berversi; void beralasan; surat void read-only; lampiran tetap dapat diunduh |
| D7 | `letters.view` baca/download; `letters.manage` tulis/upload/void; approve/sign tidak dipakai 07a |
| D8 | `/api/letters/incoming`; search/filter/pagination server; halaman Incoming Letters; English/390 px |
| D9 | Audit allowlist tanpa PII/nama file; `letter_events` immutable untuk history; manifest backup diselaraskan |
| D10 | `.uat-phase07` 14025/18025; ≥120 surat + ≥40 lampiran; server dihentikan setelah penerimaan |

Bila pemilik menyetujui seluruhnya, implementasi **07a** diotorisasi dan akan dikerjakan setelah keputusan dicatat sebagai **DEC-32**. Bila ada yang diubah, rencana dan matriks tes disesuaikan sebelum implementasi.

---

## 11. Prompt setelah subfase ini disetujui

```text
Baca AGENTS.md, ROADMAP, HANDOFF, PHASE-07-LETTERS.md dan PHASE-07-PLAN.md.
Kerjakan subfase 07a saja: agenda surat masuk, lampiran privat, pencarian/filter,
edit/void berversi, dan history sesuai PHASE-07-PLAN.md (DEC-32).
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan yang belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini tidak memberi izin
merge/deploy atau mutasi data operasional; commit+push paket review mengikuti
aturan repo. Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan
dan catat hasil aktual. Jika browser tidak tersedia, jelaskan dan berikan
checklist manual. Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX,
HANDOFF dan ROADMAP. Berhenti setelah hasil subfase siap review; jangan lanjut
07b/07c.
```

---

## 12. Gambaran 07b/07c (belum diotorisasi)

### 07b — Disposisi, surat keluar, dan nomor

- **Rencana rinci:** `reports/feature-update/PHASE-07B-PLAN.md` (baseline `aec356c`; **PLAN APPROVED — DEC-33 APPROVED 2026-11-01 WIB**, "Saya setuju D1-D11"; implementasi dikerjakan). Rencana ini menggantikan gambaran singkat di bawah.
- Gate: **DEC-06 CLOSED** melalui D4–D5/DEC-33 (format nomor `{seq:03d}/{school_code}/{year}`, counter global tahunan, nomor saat issue, approver/signatory terpisah). Bagian DEC-11 yang menyangkut kop/tanda tangan/stempel tetap menjadi gate 07c.
- Rencana: `letter_dispositions` (penerima/tenggat/status/tindak lanjut), surat keluar pada `letters` (`direction='outgoing'`) dengan status `draft → review → approved → issued/cancelled`, `letter_approvals`, dan `letter_number_counters` atomik per tahun; approver/signatory memakai `letters.approve`/`letters.sign`, terpisah dari admin teknis.
- Verifikasi minimum: dua penerbitan paralel tidak menghasilkan nomor ganda; transisi status tidak meloncat; pengguna tanpa mandat tidak dapat approve/issue; pembatalan dan audit utuh; replay-safe.
- Tidak ada implementasi 07b pada paket rencana; jangan menebak format nomor atau identitas penandatangan.

### 07c — Template PDF dan snapshot

- Gate: **DEC-11** (kop, tanda tangan, stempel, jenis surat pertama, format rekap) dan keputusan dependency PDF.
- Tidak ada library PDF terpasang dan aturan pemilik melarang perubahan dependency tanpa persetujuan. Opsi yang perlu diputuskan: menambah library PDF maintained (eksplisit disetujui) atau menunda 07c sampai keputusan tersedia.
- Rencana yang akan disusun: template versioned, auto-fill siswa/pegawai, snapshot penandatangan/biodata/template saat terbit, kop/tanda tangan/stempel, PDF halaman panjang, dan download berizin. Surat issued tidak boleh dibuat ulang dari master yang berubah.
- Tidak ada implementasi pada paket ini.

---

## 13. Syarat keluar 07a

- [x] D1–D10 disetujui dan dicatat sebagai DEC-32.
- [x] Scope 07a selesai atau partial/blocker dijelaskan.
- [x] Kontrak request/response/schema/permission yang berubah didokumentasikan di DATABASE/API-CONTRACTS/ACCESS-CONTROL/OPERATIONS.
- [x] Tes relevan benar-benar dijalankan atau NOT RUN dengan alasan; hasil aktual dicatat.
- [x] UAT `.uat-phase07` dan checklist manual tersedia; persetujuan pemilik **APPROVED 2026-11-01 WIB (#1–#16)** dicatat terpisah.
- [x] SHA/working tree, known issues, dan langkah berikutnya dicatat di INDEX/HANDOFF/ROADMAP.
- [x] Server UAT dihentikan setelah penerimaan (DEC-27); data dipertahankan.
- [x] 07b/07c belum dijalankan tanpa instruksi/rencana terpisah.

Implementasi 07a selesai dan diverifikasi agen 2026-11-01: 20 tes surat/294 assertions, suite 487 passed/13 skipped/4.786 assertions, Pint 18 file, build 26,18 s, ESLint 0, UAT `.uat-phase07` (126 surat/64 baris lampiran/221 event). Paket implementasi `ae2b75f`, koreksi dokumentasi `b6f0af5`. **Penerimaan pemilik 2026-11-01 WIB (#1–#16)**; server UAT 14025/18025 dihentikan setelah penerimaan. Bukti: `PHASE-07A-REPORT.md`; checklist pemilik `PHASE-07A-MANUAL-REVIEW.md`.
