# Rencana Fase 09 — Subfase 09b: Retensi, Akses Aman, dan Pengingat

- Tanggal: 2026-11-03 (WIB). Branch: `main`. Baseline audit: **`cad8f75`** (pencatatan penerimaan pemilik 09a #1–#18 dan verifikasi UAT berhenti). Paket rencana: commit dokumentasi setelah file ini (SHA dilaporkan setelah commit, bukan sebelumnya).
- Status dokumen: **PLAN APPROVED — DEC-38 APPROVED (2026-11-03 WIB)** berdasarkan pernyataan pemilik **"Saya setuju D1-D11"** (`b405fe7`); **IMPLEMENTED & AGENT-VERIFIED (2026-11-03 WIB)** pada paket review **`51002e6`** — lihat `reports/feature-update/PHASE-09B-REPORT.md`; **review manual pemilik APPROVED (#1–#18, 2026-11-03 WIB)** berdasarkan **"Pengujian nomor 1-18 sudah berhasil dan saya approved."** di `reports/feature-update/PHASE-09B-MANUAL-REVIEW.md`. Paket rencana: **`e89a682`**.
- Keputusan D6 disetujui: **Opsi A** (laporan retensi read-only + `app:archives-retention --dry-run`, tanpa eksekusi purge) dengan periode retensi usulan 6A sebagai nilai config; **DEC-13 dicatat PARTIALLY CLOSED untuk lingkup modul arsip dokumen** (periode retensi arsip, retensi log akses, otoritas pemusnahan `archives.purge` tanpa penugasan role). **Sisa DEC-13 tetap OPEN**: arsip siswa/alumni di luar modul arsip, lampiran surat/izin hasil soft delete, dan audit `activities` umum.
- Otorisasi saat ini: **implementasi 09b diotorisasi** (S0–S8). 09a sudah **IMPLEMENTED + agent-verified + OWNER APPROVED #1–#18** (paket review `dfd6f85`, penerimaan `cad8f75`); 07a/07b/07c dan 08a/08b tetap OWNER APPROVED; DEC-35/DEC-36/DEC-37 tidak berubah.
- Cakupan paket ini: rencana **09b — download/preview aman, audit akses, expiry task di dashboard, arsip/restore dokumen, dan retensi/pemusnahan menurut keputusan**. **Tidak ada kode, migration, route, model, service, request, controller, komponen, tes, fixture, atau UAT yang dibuat/diubah oleh paket rencana ini.** Seluruh verifikasi implementasi **NOT RUN**.
- Gate: **DEC-13 OPEN**. Sesuai kriteria penerimaan fase, **tidak ada auto-purge data sebelum kebijakan disetujui**; 09b mengusulkan kebijakan, bukan mengeksekusi pemusnahan. Merge/deploy/force-push, migrasi database operasional/MariaDB, perubahan dependency/lockfile, dan perubahan service/`.env` tidak diotorisasi.
- Dokumen acuan: `roadmap/phases/PHASE-09-ARCHIVES.md`, `docs/DECISIONS.md` (DEC-13, DEC-17, DEC-19, DEC-21, DEC-27, DEC-37), `docs/REQUIREMENTS.md` FR-11, `docs/DATABASE.md`, `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `docs/SECURITY-DATA.md`, `docs/OPERATIONS.md`, `reports/feature-update/PHASE-09-PLAN.md`, `PHASE-09A-REPORT.md`, `PHASE-09A-MANUAL-REVIEW.md`, serta source aktual 09a/07c/08a/02c untuk pola file privat, preview PDF, arsip/restore, dan scheduler.

---

## 1. Posisi dan prasyarat

| Prasyarat | Kondisi aktual 2026-11-03 | Status |
|---|---|---|
| Fase sebelumnya | **09a IMPLEMENTED + agent-verified 2026-11-02 + OWNER APPROVED #1–#18 (2026-11-03 WIB)**; 121 dokumen / 162 versi di `.uat-phase09`; server UAT dihentikan, data dipertahankan | Terpenuhi |
| Modul arsip | 2 tabel (`archive_documents`, `archive_file_versions`), ability `archives.view`/`archives.manage`, 9 route, halaman `/public/archives`, menu Archives | Ada, diperluas |
| Preview inline | **Belum ada untuk arsip.** Pola terbukti ada di 07c: `GET /api/letters/outgoing/{letter}/document/preview` → `pdfResponse()` (`OutgoingLetterController.php:179-238`), SPA fetch blob → `URL.createObjectURL` → `<iframe>` (`OutgoingLetters.vue:872-890`, `1498-1504`) | Dapat dipakai ulang |
| Audit | `activities` immutable; kolom `information` adalah **enum tetap** (`Created`/`Updated`/`Deleted`/`Rollbacked_*`) sehingga tidak dapat menampung event "diunduh/dipratinjau" tanpa mengubah enum existing; GET tidak diaudit saat ini | Perlu tabel append-only baru |
| Expiry/dashboard | `Dashboard.vue` hanya merender 7 widget template demo (30 baris); tidak ada endpoint tugas/pengingat; status validitas arsip sudah dihitung di 09a (`none`/`upcoming`/`valid`/`expiring_soon`/`expired`, ambang 30 hari) | Perlu endpoint + widget baru |
| Arsip/restore | `ArchiveDocumentPolicy::delete/restore/forceDelete` mengembalikan `false`; model tanpa soft delete; tabel tanpa `deleted_at`. Pola acuan: Student archive/restore (DEC-21) — alasan wajib, `version` + `expected_version`/`409`, transaksi + `lockForUpdate`; `StudentPolicy::restore` memakai `students.manage` (tanpa ability terpisah) | Perlu kolom additive + policy |
| Retensi/purge | **Tidak ada** kebijakan retensi arsip. Pola acuan: `app:backup-prune` + `BackupRetentionService::prune()` dengan `--dry-run`; scheduler opt-in `config('backup.schedule_enabled', false)` (`Console/Kernel.php:13-34`) | DEC-13 OPEN |
| Eksposur file privat | `public/storage` adalah symlink ke `storage/app/public`; arsip berada di `storage/app/attachments/archives` (di luar symlink) → tidak terjangkau URL publik; `config/backup.php:19` memindai `storage/app/attachments` sehingga file arsip tercakup backup | Terpenuhi, perlu tes regresi |
| Pola route/UI/UAT | Route statis sebelum `/{id}`, `whereNumber`, `can:<ability>`, policy object-level, `409` + `latest`, `404` objek tersembunyi; UI `/public/*` + `meta.requiresAbility` + label English; UAT `.uat-phase09` launcher `scripts/phase09-uat.php` (18027/14027, cookie `school_phase09_session`) | Dapat diikuti |
| Keputusan | DEC-13 **OPEN**; DEC-27 (stop server UAT) berlaku; DEC-37 menutup 09a | Perlu DEC-38 + keputusan D6 |

Semua kondisi di atas diverifikasi dari source, konfigurasi, migration, dan launcher aktual di `D:\xampp-htdocs\app-administration_school`. Tidak ada database operasional yang diakses saat menyusun rencana ini.

---

## 2. Temuan audit (fakta kode)

### 2.1 Preview aman sudah punya pola terbukti, tetapi header arsip lebih ketat

- 07c memakai satu helper untuk preview dan download: `Content-Type: application/pdf`, `X-Document-Filename`, `Access-Control-Expose-Headers`, `Content-Length`, `nosniff`, `Cache-Control: private, no-store`, `Content-Security-Policy: sandbox`, **tanpa `Content-Disposition`** — dengan komentar eksplisit bahwa `Content-Disposition` dihindari karena Chrome memblokir fetch berkredensial lintas origin (`OutgoingLetterController.php:224-238`).
- 09a memakai CSP lebih ketat: `sandbox; default-src 'none'` (`ArchiveFileService.php:106-110`).
- SPA 07c merender preview dengan blob URL di `<iframe>` dan sudah diverifikasi browser pada UAT 07c.

**Implikasi:** preview arsip mengikuti pola blob SPA yang sama. Karena 09a memakai `default-src 'none'` yang belum teruji untuk rendering inline, D2 mengusulkan: preview memakai CSP yang **terbukti** (`sandbox`) dan download mempertahankan `sandbox; default-src 'none'`; keduanya diverifikasi browser saat implementasi, dan bila `default-src 'none'` ternyata tidak memblokir viewer, header disamakan kembali. Tidak ada URL publik, tidak ada file sementara di disk publik.

### 2.2 Audit akses tidak bisa menumpang tabel `activities`

- `activities.information` adalah `enum('Created','Updated','Deleted','Rollbacked_Created','Rollbacked_Updated','Rollbacked_Deleted')` (`2026_10_15_125447_create_activities_table.php`). Menambah nilai berarti **mengubah enum yang sudah dijalankan di database pemilik** — bertentangan dengan aturan "jangan mengedit migration yang sudah pernah dijalankan".
- `Loggable` hanya merekam created/updated/deleted model; `Activity` immutable (`booted()` melempar exception pada updating/deleting); `ActivityController` menolak `store/update/destroy` dengan `405` dan membaca dengan `audit.view`.
- GET tidak menghasilkan audit sama sekali saat ini, sehingga unduhan/preview arsip **tidak terekam**.

**Implikasi:** 09b memerlukan tabel append-only baru `archive_access_logs` (bukan perubahan enum existing) + model immutable + endpoint baca berizin. `activities` tidak disentuh.

### 2.3 Tidak ada mekanisme tugas/pengingat

- `Dashboard.vue` hanya merender widget template (`StatsWidget`, `ChartDataWidget_1`, `ListUsersOnlineWidget`, `RecentSalesWidget`, `BestSellingWidget`, `RevenueStreamWidget`, `NotificationsWidget`); hanya `ListUsersOnlineWidget` yang memanggil API (`/api/users`). Tidak ada widget berbasis data arsip.
- Status validitas arsip sudah dihitung server-side di 09a (`ArchiveDocument::validityStatus()`), termasuk presedensi `upcoming` dan ambang `expiring_soon_days = 30` (`config/archives.php:41`).
- Tidak ada tabel notifikasi/tugas; tidak ada integrasi email/WhatsApp (dan tidak diminta).

**Implikasi:** "expiry task di dashboard" = **endpoint terhitung (tanpa tabel baru)** + satu widget dashboard yang menghormati filter visibilitas domain 09a. Pengingat bersifat pull (dilihat saat login), bukan push.

### 2.4 Arsip/restore: tabel 09a belum punya kolom lifecycle

- `archive_documents` memiliki `version` (optimistic lock) tetapi **tanpa** `deleted_at`, `archived_at`, `archive_reason`, atau `archived_by` (`2029_09_01_000001_create_archive_documents_table.php:16-39`); komentar migration menegaskan "Tidak ada soft delete pada 09a".
- `ArchiveDocumentPolicy` sudah menyiapkan `delete/restore/forceDelete` yang selalu `false`, sehingga penambahan perilaku arsip/restore tidak mengubah kontrak hard delete (tetap dilarang).
- Pola acuan DEC-21 (Student): arsip = soft delete + alasan wajib + `version`/`expected_version` + `409` + deaktivasi relasi; restore = validasi konflik identitas tanpa reaktivasi otomatis. `StudentPolicy::restore` memakai ability yang sama dengan manage (`students.manage`), bukan ability terpisah.

**Implikasi:** 09b memakai **status lifecycle eksplisit** (`active`/`archived`) dengan tiga kolom additive, bukan soft delete, karena versi file harus tetap dapat ditelusuri dan diunduh setelah dokumen diarsipkan (kriteria penerimaan 09a: "versi lama tetap dapat ditelusuri"). Restore memakai `archives.manage` + ability domain (konsisten dengan Student), tanpa ability baru.

### 2.5 Retensi: hanya ada preseden backup, tidak ada kebijakan arsip

- `app:backup-prune` → `BackupRetentionService::prune()` mendukung `--dry-run` (7 harian + 4 mingguan, DEC-17); scheduler hanya aktif bila `config('backup.schedule_enabled', false)` = true (DEC-19), timezone Asia/Jakarta, `withoutOverlapping(60)`.
- Tidak ada tabel/kolom/config yang menyimpan periode retensi arsip; DEC-13 masih OPEN ("Arsip siswa/alumni, audit, lampiran; siapa boleh memusnahkan").
- File lampiran surat/izin hasil soft delete juga **disimpan sampai DEC-13** (DEC-32 D5).

**Implikasi:** 09b tidak boleh mengarang periode retensi. D6 menyajikan usulan tabel retensi + tiga opsi implementasi, dan menegaskan **tidak ada purge yang dieksekusi** pada fase ini; eksekusi pemusnahan memerlukan instruksi terpisah setelah DEC-13 disetujui.

### 2.6 Eksposur file privat dapat dibuktikan dengan tes

- `laravel-api/public/storage` → symlink ke `storage/app/public`. Arsip ditulis ke disk `local` (`storage/app`) pada `attachments/archives/{document_id}/{uuid}.{ext}` sehingga berada **di luar** symlink publik.
- `config/backup.php:19` (`attachments_path`) sudah memindai `storage/app/attachments`, sehingga file arsip tercakup backup tanpa perubahan kode backup; restore drill arsip **NOT RUN** (dicatat di 09a).

**Implikasi:** kriteria "file private tidak terbuka via direct public URL" ditegaskan lewat tes regresi (symlink scope, route `whereNumber`, versi milik dokumen lain `404`, traversal ditolak) bukan hanya klaim dokumentasi.

---

## 3. Keputusan yang diminta dari pemilik (D1–D11)

> Format persetujuan mengikuti fase sebelumnya: pemilik dapat menjawab **"Saya setuju D1-D11"** (menerima seluruh rekomendasi), atau menyebut nomor keputusan yang diubah beserta pilihannya. Persetujuan dicatat sebagai **DEC-38**.

### D1 — Scope dan non-goals 09b

**Rekomendasi:** kerjakan **hanya 09b** dengan lima kelompok fitur: (1) preview inline aman, (2) audit akses download/preview, (3) expiry task di dashboard, (4) arsip/restore dokumen, (5) laporan retensi **read-only + dry-run** tanpa eksekusi pemusnahan.

**Non-goals (tidak dikerjakan, tidak di-commit):**
- Eksekusi purge/pemusnahan file atau baris arsip; penjadwalan purge; penghapusan massal.
- Perubahan enum/tabel `activities`; backfill audit historis unduhan (tidak ada datanya).
- Preview berbasis OCR/fulltext search isi dokumen; thumbnail/gambar buatan server; watermark raster.
- Notifikasi push (email/WhatsApp/web push); tabel notifikasi; langganan pengingat.
- Migrasi lampiran existing (`letter_attachments`, `leave_attachments`, `letter_documents`) ke modul arsip.
- Antivirus/malware scanning; enkripsi at-rest; cold storage/object storage; CDN/signed public URL.
- Dependency atau lockfile baru (composer/npm); perubahan service/`.env`; modernisasi framework.
- Merge/deploy/force-push/rewrite; migrasi database operasional/MariaDB; restore ke database aktif.
- Fase 10+ (pembelajaran), DEC-12 (laporan eksternal), DEC-10 (jadwal/jurnal).

**Konsekuensi bila disetujui:** semua temuan di luar daftar ini dicatat sebagai backlog, tidak dikerjakan diam-diam.

### D2 — Preview inline aman (PDF dan gambar)

**Rekomendasi:**
1. Route baru **`GET /api/archives/{archive}/versions/{version}/preview`**, izin **sama persis dengan download** (`ArchiveDocumentPolicy::download` = `archives.view` + ability domain), `whereNumber` pada kedua parameter, versi harus milik dokumen (`404` bila bukan), file hilang `404`.
2. Hanya tipe yang sudah diizinkan 09a yang dapat dipratinjau: PDF (`application/pdf`) dan gambar (`image/jpeg`, `image/png`). MIME diambil dari **kolom tersimpan** (hasil deteksi isi file saat upload), bukan dari ekstensi permintaan.
3. Header respons: `Content-Type` sesuai MIME tersimpan, `X-Document-Filename`, `Access-Control-Expose-Headers: X-Document-Filename`, `Content-Length`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, **tanpa `Content-Disposition`**, dan **`Content-Security-Policy: sandbox`** (pola 07c yang sudah terbukti merender preview di iframe). Endpoint **download** 09a tetap `sandbox; default-src 'none'`. Bila verifikasi browser menunjukkan `default-src 'none'` tidak memblokir viewer inline, preview disamakan ke nilai yang lebih ketat itu.
4. Tidak ada artefak baru: preview tidak menulis file, tidak membuat cache di disk, tidak membuat URL bertanda tangan, dan tidak menyalin bytes ke lokasi publik. Bytes dibaca dari disk privat dan dialirkan apa adanya.
5. **Tanpa watermark.** Alasan: file arsip adalah dokumen asli milik sekolah (bukan PDF yang dihasilkan aplikasi seperti 07c), dan memberi watermark pada PDF/gambar unggahan memerlukan rasterisasi = dependency baru (dilarang D1). Sebagai gantinya, dialog preview menampilkan label kontekstual dari metadata yang sudah ada: kategori, judul, nomor dokumen, nomor versi, pengunggah, dan tanggal unggah, plus teks tetap `Internal document — do not redistribute`.
6. SPA: tombol **Preview** pada setiap baris versi (hanya bila `can.download`), fetch berkredensial `responseType: 'blob'` → `URL.createObjectURL` → `<iframe>` untuk PDF dan `<img>` untuk gambar; `URL.revokeObjectURL` saat dialog ditutup; pesan error aman dari blob (`blobErrorMessage`); tanpa membuka tab baru ke URL API langsung.
7. Preview **tidak** menaikkan `version`, tidak mengubah `current_version`, dan tidak mengubah metadata.

**Alternatif yang ditolak:** (a) URL publik sementara/signed URL → melanggar kriteria file privat; (b) watermark via dompdf merge → butuh manipulasi PDF pihak ketiga; (c) `Content-Disposition: inline` → pola yang sudah terbukti bermasalah di Chrome lintas origin pada 07a/07b.

### D3 — Audit akses download/preview (`archive_access_logs`)

**Rekomendasi:**
1. Tabel **baru append-only `archive_access_logs`**:
   - `id`, `archive_document_id` (FK `archive_documents`, **restrictOnDelete**), `archive_file_version_id` (FK `archive_file_versions`, **nullOnDelete**), `user_id` (FK `users`, **nullOnDelete**), `action` (string 20, whitelist `download`/`preview`), `ip_address` (string 45, nullable), `user_agent` (string 255, nullable, terpotong), `created_at` saja (**tanpa `updated_at`**).
   - Index: `(archive_document_id, created_at)`, `(user_id, created_at)`, `(action, created_at)`.
   - Model immutable mengikuti pola `Activity`: `static::updating`/`static::deleting` melempar exception; tanpa `Loggable`; tanpa relasi balik yang memungkinkan mutasi.
2. **Privasi log:** tidak menyimpan judul, nomor dokumen, deskripsi, nama file, path, checksum, atau alasan arsip. Hanya ID + aktor + aksi + waktu + IP/UA. IP/UA disimpan untuk akuntabilitas akses; ini data teknis, bukan PII dokumen.
3. **Kapan ditulis:** hanya untuk akses yang **diotorisasi dan berhasil disiapkan** (setelah policy lolos dan file ditemukan). Baris ditulis **sebelum** bytes dialirkan. Akses ditolak (`401`/`403`/`404`) **tidak** dicatat di tabel ini (tetap terlihat dari log aplikasi existing), agar log akses = log konsumsi dokumen.
4. **Perilaku gagal (fail-closed):** bila penulisan log gagal, permintaan preview/download dibatalkan dengan `500` dan **bytes tidak dikirim**. Alasan: audit akses adalah syarat kepatuhan; lebih baik unduhan gagal daripada konsumsi dokumen tidak terekam. *Trade-off:* gangguan pada tabel log menghentikan unduhan. **Alternatif fail-open** (unduh tetap jalan, kegagalan log hanya dicatat ke log aplikasi) tersedia bila pemilik lebih memprioritaskan ketersediaan — sebutkan bila memilih ini.
5. GET yang menulis baris audit **bukan** mutasi data domain: tabel ini append-only, tidak memengaruhi state dokumen/versi, dan pengecualian ini didokumentasikan eksplisit di `docs/API-CONTRACTS.md`.
6. **Endpoint baca:** `GET /api/archives/{archive}/access-logs` dengan **`audit.view`** + `archives.view` + ability domain (ability yang sudah dipakai `ActivityController`; admin sistem dan kepala sekolah memilikinya, operator tidak). Respons: daftar terbaru dahulu (`created_at desc, id desc`), pagination `per_page` default 20 (opsi 10/20/50/100), item = `id`, `action`, `version_number` (nullable bila versi dihapus), `user {id, name}` (nullable → `System/Unknown`), `ip_address`, `created_at`, plus `meta`. Tanpa filter yang membocorkan isi dokumen.
7. **Ability baru: tidak ada.** Menggunakan `audit.view` yang sudah ada agar kebijakan "siapa boleh membaca audit" tetap satu pintu. Konsekuensi: operator sekolah tidak melihat riwayat akses (hanya admin sistem & kepala sekolah). **Alternatif:** ability baru `archives.access-audit` untuk admin+operator+kepsek — sebutkan bila memilih ini.
8. UI: tab **Access History** pada dialog detail, hanya dirender bila server mengirim `can.view_access_log = true`; empty state jelas; tanpa tombol dummy.
9. **Retensi log** mengikuti D6 (usul: 5 tahun, tanpa auto-purge).

### D4 — Expiry task di dashboard

**Rekomendasi:**
1. Endpoint terhitung **`GET /api/archives/tasks/expiring`** (route statis dideklarasikan sebelum `/{archive}`), ability `archives.view`, **tanpa tabel baru dan tanpa job**.
   - Respons: `{ threshold_days, generated_at, expiring_soon: { total, items }, expired: { total, items } }`.
   - `items` dibatasi `limit` (default 10, maksimum 50, `422` bila di luar 1–50), diurut `valid_until asc, id desc`, dan **sudah melewati filter visibilitas domain 09a** (dokumen umum + domain yang diizinkan aktor).
   - Bentuk item ringkas memakai serializer 09a yang sudah ada: `id`, `category` + label, `title`, `document_number`, `valid_until`, `validity_status`, `days_remaining` (negatif untuk expired), `subject` ringkas, `can {view, download, update}`. **Tanpa** path/checksum/deskripsi.
   - `total` adalah hitungan sebenarnya (bukan panjang `items`), sehingga widget dapat menampilkan "12 more".
   - Dokumen berstatus **archived dikecualikan** (lihat D5) karena bukan tugas aktif.
2. Widget dashboard baru **`ArchiveExpiryWidget.vue`** di `Dashboard.vue`, dirender hanya bila sesi memiliki `archives.view`; dua penghitung (Expiring Soon ≤30 hari, Expired) + daftar ringkas + badge hari; klik item/baris membuka `/public/archives?validity=expiring_soon` atau `?validity=expired`; empty state "No archive documents are expiring."; tanpa tombol dummy; label English; gagal memuat widget tidak merusak dashboard (pesan error lokal).
3. `Archives.vue` membaca query `validity` saat mount dan menerapkan filter yang sama seperti 09a (nilai di luar whitelist → diabaikan, bukan `422` di UI).
4. **Widget demo template existing tidak dihapus** pada 09b (di luar scope, dan penghapusannya adalah keputusan tampilan tersendiri). Widget arsip ditempatkan di baris teratas. **Alternatif:** sekalian hapus 7 widget demo — sebutkan bila memilih ini (menambah scope UI, tidak menambah risiko backend).
5. **Tanpa pengingat push.** Tidak ada email/WhatsApp/notifikasi browser; tidak ada tabel `reminders`; tidak ada scheduler untuk pengingat (D6 hanya membahas laporan retensi). Pengingat = dilihat saat membuka dashboard.

### D5 — Arsip dan restore dokumen

**Rekomendasi:**
1. Migration **additive** pada `archive_documents`: `archived_at` (timestamp, nullable), `archive_reason` (string 255, nullable), `archived_by` (FK `users`, nullable, `nullOnDelete`), plus index `archive_documents_archived_at_index`. **Tanpa soft delete**, **tanpa** perubahan pada `archive_file_versions` (versi tetap immutable dan tetap dapat diunduh).
2. State lifecycle: `active` (default, `archived_at IS NULL`) ↔ `archived` (`archived_at NOT NULL`). Status dihitung, bukan kolom enum baru.
3. **Archive** = `POST /api/archives/{archive}/archive`, ability `archives.manage` + ability domain, body `archive_reason` **wajib 10–255** dan `expected_version` **wajib**; transaksi + `lockForUpdate`; menaikkan `version`; konflik → `409` dengan `{ message, errors, latest { id, version, current_version, updated_at } }` (bentuk 09a yang sudah dipakai UI Reload Latest). Dokumen yang sudah archived → `422` (tidak boleh archive ganda).
4. **Restore** = `POST /api/archives/{archive}/restore`, ability `archives.manage` + ability domain, body `expected_version` wajib; pola transaksi/`409` sama; menghapus `archived_at`/`archive_reason`/`archived_by`; menaikkan `version`; dokumen yang tidak archived → `422`. **Restore tidak mengubah versi file, tidak mengaktifkan ulang relasi domain, dan tidak menyentuh subjek** (tautan subjek tetap immutable sejak 09a).
5. **Dokumen archived read-only:** `update` dan `addVersion` ditolak `422` dengan pesan aman; `view`, `versions`, `download`, `preview`, dan `access-logs` **tetap diizinkan** sesuai ability (kriteria 09a: versi lama tetap dapat ditelusuri).
6. **List/search:** default hanya `active`. Filter baru `status=active|archived|all` (default `active`); nilai di luar whitelist → `422`. Dokumen archived tetap melewati filter visibilitas domain. Badge `Archived` pada daftar dan detail.
7. **Policy:** `delete`/`forceDelete` **tetap `false`** (hard delete tidak pernah tersedia); `restore` tidak lagi selalu `false` tetapi mengikuti aturan restore di atas; metode policy baru `archive`/`unarchive` = `archives.manage` + domain (konsisten dengan `StudentPolicy` yang memakai ability manage untuk restore, tanpa ability terpisah).
8. **Audit:** `archived_at` ditambahkan ke allowlist `auditableAttributes` `ArchiveDocument` (timestamp, tanpa PII). **`archive_reason` tidak diaudit** (teks bebas, mengikuti preseden `void_reason`/alasan izin yang dikecualikan). Aksi archive/restore tetap terekam sebagai `Updated` pada `archive_documents` dengan aktor.
9. **Tidak ada unarchive massal, tidak ada arsip otomatis berdasarkan tanggal kedaluwarsa** (expired ≠ archived; keduanya orthogonal dan sama-sama tampil sebagai tugas/label).

### D6 — Retensi dan pemusnahan (gate **DEC-13**)

**Kondisi:** DEC-13 OPEN. Kriteria penerimaan fase menegaskan **"tidak auto-purge data sebelum kebijakan"**. 09b **tidak mengeksekusi pemusnahan apa pun**.

**6A. Usulan kebijakan retensi (untuk disetujui/diubah pemilik).** Periode dihitung dari tanggal acuan, zona WIB, dan hanya dipakai untuk **laporan**; tidak ada penghapusan otomatis.

| Kategori | Tanggal acuan | Usulan retensi minimum | Catatan |
|---|---|---|---|
| `student_record` | lulus/keluar siswa atau `document_date` bila tidak tertaut | **Permanen (tidak pernah auto-purge)** | jejak akademik/alumni; sejalan DEC-13 "arsip siswa/alumni" |
| `employee_record` | berakhirnya masa kerja atau `document_date` | **5 tahun** | kepegawaian |
| `letter` | `document_date` | **5 tahun** | surat masuk/keluar |
| `leave` | tanggal keputusan/`document_date` | **2 tahun** | izin siswa/pegawai |
| `school_document` | `document_date` | **10 tahun** | dokumen kelembagaan |
| `other` | `document_date` | **5 tahun** | default konservatif |
| `archive_access_logs` | `created_at` | **5 tahun** | audit akses |
| `activities` (audit existing) | `created_at` | **Tidak diubah pada 09b** | di luar modul arsip; DEC-13 lingkup audit umum |

**Otoritas pemusnahan (usulan):** ability baru **`archives.purge`** yang **tidak diberikan ke role mana pun** secara default (hanya dapat ditugaskan eksplisit lewat Roles & Abilities oleh pemegang `access.assign`), ditambah kewajiban dua langkah (laporan dry-run → konfirmasi token sekali pakai) bila eksekusi kelak diotorisasi. Kepala Sekolah dan Admin Sistem **tidak** otomatis memegangnya; pemisahan tugas: yang mengelola arsip (`archives.manage`) tidak boleh menjadi satu-satunya yang memusnahkan.

**6B. Tiga opsi implementasi 09b.**

| Opsi | Isi | Risiko | Rekomendasi |
|---|---|---|---|
| **A** | **Laporan retensi read-only + command dry-run.** `GET /api/archives/retention/report` (`archives.manage` atau `audit.view`, lihat catatan) + `php artisan app:archives-retention --dry-run` yang **hanya mencetak** kandidat (kategori, jumlah dokumen, jumlah versi, total byte, dokumen tertua) tanpa menulis/menghapus apa pun. Periode retensi disimpan di `config/archives.php` (nilai D6-6A). `archives.purge` didefinisikan di katalog tetapi tidak ditugaskan. **Tidak ada route purge, tidak ada scheduler, tidak ada ledger.** | Terendah; DEC-13 tetap OPEN sampai pemilik menetapkan kebijakan final | **Direkomendasikan** |
| **B** | A + **command purge berpagar**: `app:archives-purge --dry-run` (default) dan `--confirm --token=` dengan ledger append-only `archive_destruction_ledger` (siapa, kapan, kategori, jumlah, alasan, hash token), scheduler **tetap OFF**, dan **tidak pernah dijalankan** pada database/storage operasional di fase ini (hanya `.uat-phase09`). | Sedang; kode pemusnahan ada sebelum kebijakan final disahkan, risiko disalahgunakan bila `.env`/scheduler diubah di luar scope | Hanya bila pemilik ingin eksekusi siap pakai |
| **C** | Tunda seluruh item retensi ke Fase 13; 09b hanya D2–D5. | DEC-13 tetap OPEN lebih lama; laporan retensi tidak tersedia untuk keputusan | Bila pemilik ingin 09b minimal |

**Catatan izin laporan retensi:** laporan menampilkan agregat (tanpa judul/nama file/path) sehingga **`archives.manage`** diusulkan sebagai gate; bila pemilik ingin Kepala Sekolah dapat membacanya, tambahkan `audit.view` sebagai alternatif OR — sebutkan pilihan ini bila diinginkan.

**Status DEC-13 setelah 09b (usulan):** bila pemilik menyetujui 6A + opsi A, DEC-13 dicatat **PARTIALLY CLOSED — lingkup modul arsip dokumen** (periode retensi arsip, retensi log akses, dan otoritas pemusnahan `archives.purge`); **sisanya tetap OPEN** (arsip siswa/alumni di luar modul arsip, lampiran surat/izin hasil soft delete, dan audit `activities` umum) dan dieksekusi pada Fase 13 atau subfase 09c dengan rencana terpisah. Bila pemilik tidak menetapkan periode retensi, **DEC-13 tetap OPEN sepenuhnya** dan hanya opsi A yang diimplementasikan dengan nilai config sebagai **usulan default**, bukan kebijakan.

### D7 — Integritas file dan bukti "tidak terbuka via URL publik"

**Rekomendasi:**
1. **Verifikasi checksum saat baca:** sebelum mengalirkan bytes (download **dan** preview), hitung SHA-256 file dan bandingkan dengan `checksum_sha256` tersimpan. Bila tidak cocok → **`500`** dengan pesan aman (`The stored file failed its integrity check.`), bytes tidak dikirim, dan kegagalan dicatat ke log aplikasi (tanpa path/checksum di respons). Biaya: satu hash untuk file ≤10 MB — dapat diabaikan. **Alternatif:** lewati verifikasi demi kecepatan — sebutkan bila memilih ini.
2. **Tes eksposur publik (regresi, bukan klaim):**
   - menegaskan `public/storage` hanya memetakan `storage/app/public` dan **tidak ada** berkas arsip di dalamnya;
   - HTTP ke path yang menyerupai lokasi file (`/storage/attachments/archives/...`, `/attachments/archives/...`) → `404`;
   - parameter `{archive}`/`{version}` non-numerik dan traversal (`../`, `%2e%2e`, string) → `404` oleh `whereNumber`, bukan oleh query;
   - versi milik dokumen lain → `404` (bukan `403`), sehingga keberadaan objek tidak bocor;
   - pengguna tanpa ability domain → `404` untuk objek bertaut, `403` untuk filter subjek eksplisit (perilaku 09a dipertahankan);
   - tamu → `401`; pengguna nonaktif → `403`.
3. **Tidak ada** perubahan pada `.env`, `config/filesystems.php`, symlink `public/storage`, Apache, atau service apa pun. Bukti dikumpulkan read-only.

### D8 — Matriks izin lintas objek (ditegaskan ulang dengan tes)

**Rekomendasi:** seluruh route baru memakai policy terpusat yang sama dengan 09a; tidak ada cek `type_user_id` di controller/komponen.

| Peran (akun UAT) | view/list | preview | download | access-logs | tasks/expiring | archive/restore | retention report |
|---|---|---|---|---|---|---|---|
| Admin Sistem (`uat.admin`) | semua domain | ya | ya | **ya** (`audit.view`) | ya | ya | **ya** |
| Operator Sekolah (`uat.operator`) | semua domain | ya | ya | **tidak** (`403`) | ya | ya | **ya** |
| Kepala Sekolah (`uat.kepsek`) | semua domain | ya | ya | **ya** | ya | **tidak** (`403`) | tidak (kecuali OR `audit.view` disetujui) |
| Wakil Kesiswaan (`uat.waka`) | semua domain | ya | ya | tidak | ya | tidak (`403`) | tidak |
| Guru (`uat.guru`) | `403`, menu tidak tampil | `403` | `403` | `403` | `403` | `403` | `403` |
| Nonaktif (`uat.inactive`) | `403` | `403` | `403` | `403` | `403` | `403` | `403` |
| Archivist (`uat.archivist`, hanya `archives.view`) | **hanya dokumen umum** | umum saja | umum saja | tidak | hanya dokumen umum | tidak (`403`) | tidak |

Aturan objek: dokumen bertaut domain tanpa ability domain → **`404`** untuk show/preview/download/access-logs/archive/restore; filter `subject_type` eksplisit tanpa ability → **`403`**. Semua route berada di `auth:sanctum` + middleware `active` + `can:<ability>` seperti 09a.

### D9 — Kontrak API, UI, dan jumlah route

**Rekomendasi: 6 route baru → total 15 route `/api/archives*`.**

| # | Method | Endpoint | Ability | Catatan |
|---|---|---|---|---|
| 10 | GET | `/api/archives/tasks/expiring` | `archives.view` | statis, dideklarasikan sebelum `/{archive}`; `limit` 1–50 default 10 |
| 11 | GET | `/api/archives/retention/report` | `archives.manage` (atau OR `audit.view` bila D6 disetujui) | agregat per kategori; **read-only** |
| 12 | GET | `/api/archives/{archive}/access-logs` | `archives.view` + `audit.view` + domain | pagination; objek tersembunyi `404` |
| 13 | POST | `/api/archives/{archive}/archive` | `archives.manage` + domain | `archive_reason` 10–255, `expected_version`; `409` |
| 14 | POST | `/api/archives/{archive}/restore` | `archives.manage` + domain | `expected_version`; `409` |
| 15 | GET | `/api/archives/{archive}/versions/{version}/preview` | `archives.view` + domain (metode policy `download`) | bytes inline; header D2 |

**Perubahan pada respons existing (additive, tidak merusak consumer 09a):**
- `can` pada item/detail menambah `preview`, `archive`, `restore`, `view_access_log`.
- Detail menambah `archived_at`, `archive_reason` (hanya bila aktor boleh melihat; selalu boleh bagi pemegang `archives.view` karena bukan PII pihak ketiga), `archived_by {id, name}`, `status` (`active`/`archived`), `access_logs_count` (hanya bila `can.view_access_log`).
- Item ringkas menambah `status`.
- `GET /api/archives` menerima filter `status`; default `active`.
- `GET /api/archives/options` menambah `statuses` (`active`/`archived`), `retention` (periode per kategori bila D6 disetujui; hanya untuk pemegang gate laporan), `expiring_task_limit`.
- Bentuk `409`/`422`/`404`/`403`/`401` **tidak berubah** dari 09a.

**UI (Vue, English, tanpa dependency baru):**
- `Archives.vue`: tombol **Preview** per versi; dialog preview (iframe/img + metadata + label internal); filter **Status**; badge `Archived`; tombol **Archive**/**Restore** dengan dialog alasan (10–255) dan `expected_version`; penanganan `409` memakai **Reload Latest** yang sudah ada; tab **Access History** (gated).
- `ArchiveExpiryWidget.vue` (baru) + satu baris di `Dashboard.vue`.
- Router/menu: **tidak ada route atau menu baru** (archives sudah ada); widget dashboard mengikuti ability sesi.
- Aksesibilitas/viewport: 1440×900 **dan 390×844**, navigasi keyboard, `aria-label`, dialog full-width pada mobile, tanpa tombol dummy.

### D10 — Verifikasi, tes, dan UAT

**Rekomendasi:**
1. **Tes backend (SQLite in-memory per `phpunit.xml`)** — file baru `tests/Feature/Archives/ArchiveAccessRetentionTest.php` + perluasan `ArchiveDocumentTest.php`. Matriks minimum:

| ID | Skenario | Assert |
|---|---|---|
| T1 | Preview PDF/gambar oleh pemegang view | `200`, `Content-Type` sesuai MIME tersimpan, `X-Document-Filename`, tanpa `Content-Disposition`, bytes identik dengan file di disk |
| T2 | Preview oleh tamu / tanpa ability / tanpa ability domain / versi milik dokumen lain / file hilang | `401`, `403`, `404`, `404`, `404` |
| T3 | Preview menulis baris `download`→`preview` di `archive_access_logs`; GET tidak mengubah `version`/`current_version`/`updated_at` dokumen (decoy assertion) | log +1, metadata tidak berubah |
| T4 | Kegagalan penulisan log → `500` dan bytes tidak dikirim (fail-closed D3) | `500`, respons tanpa isi file |
| T5 | Access logs dibaca dengan `audit.view` → `200`; tanpa `audit.view` → `403`; tanpa ability domain → `404` | matriks izin |
| T6 | `archive_access_logs` immutable: update/delete model melempar; tidak ada route tulis | exception + `405`/`404` |
| T7 | Checksum tidak cocok → `500`, bytes tidak dikirim, pesan aman tanpa path/checksum | integritas |
| T8 | Archive: alasan wajib 10–255, `expected_version` wajib, `version` naik, `archived_at`/`archived_by` terisi, alasan **tidak** masuk audit | `200` + audit allowlist |
| T9 | Archive ganda / restore dokumen aktif → `422`; versi basi → `409` + `latest`; tanpa mutasi pada konflik | konkurensi |
| T10 | Dokumen archived: update/add version `422`; view/versions/download/preview tetap `200` | read-only |
| T11 | List default hanya `active`; `status=archived`/`all` sesuai; `status` tidak valid `422`; visibilitas domain tetap berlaku | filter |
| T12 | Restore menghapus `archived_*`, menaikkan `version`, tidak mengubah versi file/tautan subjek | restore aman |
| T13 | `tasks/expiring`: `total` benar, `items` ≤ limit, urut `valid_until asc`, `days_remaining` benar pada batas WIB (30 hari, 31 hari, hari ini, kemarin), presedensi `upcoming` dihormati, archived dikecualikan, visibilitas domain difilter | perhitungan |
| T14 | `tasks/expiring` `limit` 0/51 → `422`; tanpa `archives.view` → `403`; tamu → `401` | validasi |
| T15 | `retention/report`: agregat per kategori sesuai config, **read-only** (jumlah dokumen/versi/byte tidak berubah), tanpa judul/nama file/path, gate ability benar | laporan |
| T16 | `app:archives-retention --dry-run` mencetak kandidat dan **tidak** menghapus/menulis apa pun (assert jumlah baris & file sebelum/sesudah, termasuk decoy) | non-destruktif |
| T17 | Tidak ada route purge/destroy/force-delete arsip; `delete`/`forceDelete` policy tetap `false`; `DELETE /api/archives/{id}` → `405`/`404` | tidak auto-purge |
| T18 | Eksposur publik: `/storage/attachments/archives/...` dan varian traversal → `404`; `public/storage` tidak memuat berkas arsip | kriteria penerimaan |
| T19 | Ability/role: `archives.purge` ada di katalog tetapi **tidak** ditugaskan ke role mana pun; `activities`/enum tidak berubah | pemisahan tugas |
| T20 | Regresi 09a: 9 route existing tetap berperilaku sama (create/update/add version/download/list/options/subjects) dengan tambahan field `status`/`can` | tidak ada breaking change |

2. **Regresi:** suite `tests/Feature/Archives/*` + Letters + Leaves (pola 09a). **Suite penuh** boleh dijalankan dengan SQLite in-memory; tes integrasi MariaDB tetap skipped dan **tidak** boleh menyentuh `school_access_uat`, `school_compat_uat`, `backdata_administrations`, atau DB App Gudang.
3. **Kualitas:** `./vendor/bin/pint --test` pada file PHP berubah; `php artisan route:list --path=archives` = **15 route**; `npm run build`; `npx eslint` non-mutasi pada file Vue yang berubah (target 0 error; warning Prettier CRLF lama tidak diperbaiki agar scope tetap 09b).
4. **UAT:** runtime **`.uat-phase09`** yang sudah ada, **additive dan idempoten** — command baru `prepare-09b` pada `scripts/phase09-uat.php` yang **tidak me-reset** data 09a (121 dokumen/162 versi dipertahankan) dan menambah: dokumen `expiring_soon` pada batas 1/15/30 hari, dokumen `expired` (termasuk tepat kemarin), dokumen `upcoming`, dokumen archived (dengan alasan) + satu dokumen archived yang tetap dapat diunduh, baris `archive_access_logs` sintetis untuk dua aktor, satu dokumen dengan file dihapus dari disk (untuk `404`), dan satu file dengan byte dirusak pada salinan **terisolasi** (untuk uji integritas). API `127.0.0.1:18027`, SPA `127.0.0.1:14027`, cookie `school_phase09_session`, storage `.uat-phase09/storage`.
5. **Browser (Chrome DevTools MCP):** desktop 1440×900 **dan 390×844**; alur preview PDF & gambar, tab Access History (admin/kepsek ya, operator tidak), archive → filter Archived → restore, widget dashboard (operator/admin/kepsek tampil, guru tidak), deep link `?validity=expired`, keyboard/aria, console error (hanya probe `403`/`404`/`409`/`500` yang disengaja).
6. **NOT RUN (dicatat jujur):** race MariaDB/InnoDB dan multi-proses nyata (runtime UAT SQLite); dua tab browser nyata untuk `409`; restore drill yang memuat tabel/file arsip; benchmark volume besar; antivirus/malware scanning; **eksekusi purge/pemusnahan apa pun**; verifikasi browser di luar daftar bukti; scheduler cron nyata.
7. **Server UAT dihentikan setelah verifikasi/penerimaan** (DEC-27 + instruksi pemilik tentang lingkungan uji per fase): verifikasi PID/command line, pastikan port 18027/14027 bebas, pertahankan `.uat-phase09`, jangan sentuh Apache (80/443) dan MariaDB (3306).

### D11 — Dokumentasi, commit, dan batas otorisasi

**Rekomendasi:**
1. Sinkronkan: `docs/API-CONTRACTS.md` (6 route baru + perubahan respons + pengecualian audit GET), `docs/DATABASE.md` (`archive_access_logs`, kolom archive, ability `archives.purge`), `docs/ACCESS-CONTROL.md` (matriks D8, pemisahan tugas purge), `docs/SECURITY-DATA.md` (preview, checksum, eksposur publik, privasi log akses, retensi), `docs/OPERATIONS.md` (command `app:archives-retention`, scheduler tetap OFF, UAT, stop server), `docs/TESTING.md` (hasil aktual + NOT RUN), `docs/REQUIREMENTS.md` (FR-11 status 09b), `docs/DECISIONS.md` (**DEC-38**, status **DEC-13**), `CHANGELOG.md`, `PROJECT_CONTEXT.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-09-ARCHIVES.md`, `reports/feature-update/INDEX.md`, `HANDOFF.md`, serta `PHASE-09B-REPORT.md` + `PHASE-09B-MANUAL-REVIEW.md`.
2. **Commit + push paket review** ke `origin/main` setelah verifikasi (agar pemilik dapat membaca Markdown di GitHub), staging **selektif** (tanpa `git add .`/`-A`; tanpa `.env`, kredensial, DB, log, `console.txt`, `vue-app/test-results/`, `.uat-*`). Laporkan SHA aktual, branch, status sinkronisasi remote, hasil `git diff --cached --check`, dan file yang belum ter-commit beserta alasannya.
3. **Commit/push bukan penerimaan.** Setelah pemilik menyatakan approval checklist, penerimaan dicatat pada **commit dokumentasi terpisah**, lalu server UAT dihentikan.
4. **Tidak diotorisasi:** merge, deploy, force-push, rewrite history, migrasi database operasional/MariaDB, perubahan dependency/lockfile, perubahan service/`.env`/PHP/Apache, eksekusi retensi/purge, **09c/Fase 10+**.
5. Bila saat implementasi ditemukan kebutuhan yang mengubah perilaku yang belum diputuskan (mis. header preview harus berbeda, atau fail-open dibutuhkan), **berhenti dan laporkan**, jangan menebak.

---

## 4. Rencana pelaksanaan (S0–S8) setelah D1–D11 disetujui

| Langkah | Isi | Keluaran |
|---|---|---|
| **S0** | Audit ulang HEAD & source aktual (route 09a, `ArchiveFileService`, `Archives.vue`, `Dashboard.vue`, `AbilityCatalog`, `Console/Kernel`), pastikan baseline = commit persetujuan DEC-38 | catatan baseline |
| **S1** | Migration additive: kolom archive pada `archive_documents`; tabel `archive_access_logs`; ability `archives.purge` (tanpa penugasan role); perluasan `config/archives.php` (retention, statuses, task limit) | migration + config |
| **S2** | Model: `ArchiveAccessLog` immutable + relasi; perluasan `ArchiveDocument` (allowlist `archived_at`, relasi `archiver`, helper `isArchived()`); `ArchiveFileVersion` tidak berubah | model |
| **S3** | Policy: `archive`/`unarchive`; `restore` mengikuti D5; `delete`/`forceDelete` tetap `false`; `accessLogs` (`audit.view` + domain); `preview` = `download` | policy |
| **S4** | Service: `ArchiveAccessService` (pencatatan + query log, fail-closed), `ArchivePreviewService`/perluasan `ArchiveFileService` (preview + verifikasi checksum), `ArchiveLifecycleService` (archive/restore dengan transaksi + `lockForUpdate` + `409`), `ArchiveTaskService` (expiring), `ArchiveRetentionService` (laporan read-only) | services |
| **S5** | Request + Controller + Routes: `ArchiveAccessLogRequest`/`ArchiveLifecycleRequest` (alasan + `expected_version`), 6 route baru (statis sebelum `/{archive}`), serialisasi additive `status`/`can`/`archived_*` | API |
| **S6** | Console: `app:archives-retention` (`--dry-run`, read-only, tanpa scheduler); scheduler **tidak** diubah | command |
| **S7** | Frontend: preview dialog + Access History + Archive/Restore + filter Status di `Archives.vue`; `ArchiveExpiryWidget.vue` + `Dashboard.vue`; query `validity`; 390 px + keyboard/aria | UI |
| **S8** | Tes T1–T20 + regresi + Pint + route:list + build + ESLint; UAT `prepare-09b` + browser; dokumentasi + checklist manual; commit/push paket review; stop server setelah penerimaan | bukti + paket review |

---

## 5. Dampak bila disetujui

- **Database:** 1 migration additive kolom (`archive_documents`) + 1 tabel baru (`archive_access_logs`) + 1 migration ability idempoten (`archives.purge`, tanpa penugasan). **Tidak ada** perubahan tabel/enum existing, **tidak ada** soft delete, **tidak ada** penghapusan data.
- **Backend:** 1 controller diperluas (`ArchiveController`) atau 1 controller baru (`ArchiveRetentionController`) — dipilih saat S0 agar `ArchiveController` tidak membengkak; 4–5 service baru/diperluas; 2 FormRequest baru; 6 route baru (total 15); 1 console command baru; scheduler tidak berubah.
- **Frontend:** `Archives.vue` diperluas; `ArchiveExpiryWidget.vue` baru; `Dashboard.vue` 1 baris; router/menu tidak berubah.
- **Ability:** 1 ability baru didefinisikan (`archives.purge`) dan **tidak ditugaskan** ke role mana pun; `audit.view` dipakai ulang untuk log akses; tanpa role baru.
- **Dependency:** **tidak ada** (composer/npm tidak disentuh).
- **Storage/backup:** tidak ada path baru (file tetap di `attachments/archives`); `archive_access_logs` adalah tabel → otomatis tercakup dump backup; catatan manifest diverifikasi saat implementasi.
- **Dokumentasi:** 11 dokumen + 2 laporan subfase (daftar di D11).

---

## 6. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Preview inline membuka celah XSS/content-sniffing | MIME dari kolom tersimpan (hasil deteksi isi), `nosniff`, CSP sandbox, tanpa URL publik, bytes dialirkan dari disk privat; T1/T2/T18 |
| CSP `default-src 'none'` memblokir viewer PDF inline | Pakai pola 07c yang terbukti (`sandbox`) untuk preview; verifikasi browser 1440 & 390 px; fallback terdokumentasi di D2 |
| Log akses membocorkan isi dokumen | Hanya ID/aktor/aksi/waktu/IP/UA; tanpa judul/nama file/path/checksum; T3/T6 |
| GET yang menulis log dianggap mutasi | Append-only, bukan data domain, pengecualian didokumentasikan eksplisit di API-CONTRACTS; T3 menegaskan metadata tidak berubah |
| Fail-closed menghentikan unduhan saat tabel log bermasalah | Perilaku dipilih sadar (D3.4) dengan alternatif fail-open; kegagalan dicatat ke log aplikasi; T4 |
| Arsip/restore menghapus data atau versi | Tanpa soft/hard delete; versi immutable tetap ada; policy `delete`/`forceDelete` tetap `false`; T10/T12/T17 |
| Dokumen archived hilang dari penelusuran | Filter `status=archived`/`all`; badge Archived; download/preview tetap diizinkan; T10/T11 |
| Konflik update saat archive/restore | `expected_version` + `lockForUpdate` + `409` + `latest`; UI Reload Latest existing; T9 |
| Perhitungan expiry salah zona waktu/batas | Hitung dengan tanggal WIB, ambang config 30 hari, tes batas 1/15/30/31/hari-ini/kemarin + presedensi `upcoming`; T13 |
| Widget dashboard membocorkan dokumen lintas domain | Endpoint memakai filter visibilitas 09a; archivist hanya dokumen umum; T13 + bukti browser |
| Retensi berubah menjadi auto-purge | Tidak ada route/command purge yang menghapus (opsi A); `--dry-run` read-only dengan assert sebelum/sesudah; scheduler OFF; T15/T16/T17 |
| DEC-13 ditebak | Periode retensi disajikan sebagai **usulan** D6-6A; bila pemilik tidak menetapkan, config ditandai usulan dan DEC-13 tetap OPEN |
| Ability purge jatuh ke role yang salah | `archives.purge` tidak ditugaskan ke role mana pun; penugasan hanya lewat `access.assign`; T19 |
| Breaking change pada consumer 09a | Semua perubahan respons **additive**; default list tetap `active`; T20 regresi 9 route existing |
| Suite penuh menyentuh DB terlarang | SQLite in-memory per `phpunit.xml`; tes MariaDB tetap skipped; tidak menyentuh `school_access_uat`/`school_compat_uat`/`backdata_administrations`/DB App Gudang |
| Fixture UAT merusak data 09a yang sudah diterima | `prepare-09b` additive & idempoten, tanpa reset; `.uat-phase09` dipertahankan |
| Server UAT dibiarkan berjalan | Stop setelah verifikasi/penerimaan (DEC-27); verifikasi PID/port; data dipertahankan |

---

## 7. Kriteria penerimaan paket rencana

- [x] Pemilik menyetujui D1–D11 (**"Saya setuju D1-D11"**, 2026-11-03 WIB); pernyataan dicatat sebagai **DEC-38 APPROVED**.
- [x] Keputusan D6 menetapkan **Opsi A** (laporan retensi read-only + dry-run, tanpa purge) dan periode retensi 6A sebagai nilai config; **DEC-13 PARTIALLY CLOSED untuk lingkup modul arsip dokumen**, sisanya tetap OPEN.
- [x] Implementasi 09b diotorisasi **hanya** setelah persetujuan; 09c/Fase 10+ tetap memerlukan rencana terpisah.
- [x] Tidak ada kode/migration/route/model/service/tes/fixture/UAT pada paket rencana ini; seluruh verifikasi implementasi **NOT RUN** pada saat paket rencana di-push (`e89a682`).
- [x] Dokumen status (`INDEX`, `HANDOFF`, `ROADMAP`, phase file, `CHANGELOG`, `PROJECT_CONTEXT`, `REQUIREMENTS`, `DECISIONS`) menyatakan **09b PLAN — AWAITING OWNER APPROVAL (D1–D11; DEC-38 PROPOSED)** pada paket rencana, lalu diperbarui menjadi **DEC-38 APPROVED** pada commit dokumentasi persetujuan.
- [x] Setelah persetujuan, implementasi mengikuti S0–S8, diverifikasi agen (2026-11-03 WIB; laporan `PHASE-09B-REPORT.md` & panduan `PHASE-09B-MANUAL-REVIEW.md`), berhenti untuk review manual pemilik (#1–#18), commit+push paket review, dan penerimaan dicatat pada commit dokumentasi terpisah.

**Pernyataan otorisasi:** persetujuan **"Saya setuju D1-D11"** (2026-11-03 WIB) mengotorisasi **implementasi 09b** sesuai D1–D11 (S0–S8), termasuk commit+push paket review setelah verifikasi. Persetujuan ini **bukan** otorisasi: eksekusi purge/pemusnahan data apa pun, merge/deploy/force-push/rewrite, migrasi database operasional/MariaDB, perubahan dependency/lockfile, perubahan service/`.env`/PHP/Apache, penugasan `archives.purge` ke role, pengaktifan scheduler, atau pelaksanaan 09c/Fase 10+. Commit/push bukan penerimaan manual; penerimaan pemilik dicatat pada commit dokumentasi terpisah setelah review.
