# Laporan Subfase 07a — Surat Masuk dan Lampiran Dasar

Tanggal: implementasi dan verifikasi agen 2026-11-01 WIB. Branch: `main`. Baseline induk: `f46f736` (pencatatan DEC-32 APPROVED). Paket implementasi+dokumentasi 07a: `ae2b75f` (di-push); koreksi dokumentasi PID UAT: `b6f0af5` (di-push). **Penerimaan pemilik 2026-11-01 WIB (#1–#16)** dicatat pada commit dokumentasi tersendiri setelah paket review.
Keputusan pemilik: **DEC-32** (APPROVED 2026-11-01 WIB; D1–D10, pernyataan "Saya setuju D1-D10.").
Status teknis: **IMPLEMENTED — agent-verified (2026-11-01)**. Status review pemilik: **APPROVED 2026-11-01 WIB (#1–#16)** berdasarkan pernyataan "Pengujian nomor 1-16 sudah berhasil dan saya approved."; agen tidak mengulang skenario.
Rencana: `reports/feature-update/PHASE-07-PLAN.md`. Checklist manual: `reports/feature-update/PHASE-07A-MANUAL-REVIEW.md`.

## 1. Outcome dan cakupan

Subfase 07a menambahkan agenda surat masuk beserta lampiran privat sebagai fondasi modul persuratan:

- **Agenda surat masuk** dengan referensi internal `#` + id ter-pad (`#000126`), bukan nomor resmi. `source_number` opsional, string, di-index, dan **tidak unik**; nomor resmi/agenda berformat tetap ditunda ke 07b/DEC-06 (D2).
- **Field wajib dan aturan tanggal**: `received_date` (tidak melewati hari ini), `letter_date` (tidak melewati tanggal terima), `sender` ≤150, `subject` ≤200, `category` dari config; `source_number` ≤100 dan `notes` ≤2000 opsional; tidak ada FK tahun ajaran (D3).
- **Kategori tetap** di `config/letters.php` — `Government`, `Internal`, `Invitation`, `Notification`, `Request`, `Partnership`, `Other` — divalidasi `Rule::in`; tidak ada CRUD kategori pada 07a (D4).
- **Lampiran privat**: hanya PDF/JPG/JPEG/PNG, maksimum 10 MB per file dan 5 file per surat, MIME dideteksi server, nama file acak di `storage/app/attachments/letters/{letter_id}/{uuid}.{ext}`, `sha256` disimpan, tanpa URL publik. Unduhan hanya lewat endpoint berizin dengan `Content-Disposition: attachment`, `nosniff`, CSP sandbox, dan `no-store, private`. Hapus lampiran = soft delete baris; file tetap disimpan sampai keputusan retensi DEC-13 (D5).
- **Edit/void berversi**: tidak ada hard delete surat. Update dan void mewajibkan `expected_version`, menaikkan versi, serta menulis event domain. Void wajib alasan 10–255; surat void menjadi read-only (edit/upload/remove/void kedua → `409`), tidak ada unvoid, tetap tampil pada filter Void, dan lampirannya tetap dapat diunduh oleh `letters.view` (D6).
- **Hak akses**: `letters.view` untuk list/detail/options/download/history; `letters.manage` untuk create/update/void/upload/remove. `letters.approve`/`letters.sign` tidak dipakai; tidak ada ability/role migration baru (D7).
- **API dan UI**: sembilan endpoint `/api/letters/incoming` dengan pencarian server-side (`source_number`/`sender`/`subject`), filter kategori/status/rentang tanggal terima, paginasi 10/20/50/100 (default 20), urut `received_date` desc lalu `id` desc; halaman English `/public/letters/incoming` di grup menu **Letters → Incoming Letters**, item dummy **Letters Form** dihapus (D8).
- **Audit, history, privasi**: `Letter` dan `LetterAttachment` memakai `Loggable` allowlist tanpa notes, sender, subject, alasan void, nama file asli, path, atau checksum. `letter_events` immutable mencatat `created`, `updated`, `voided`, `attachment_added`, `attachment_removed`, aktor, waktu, dan metadata minimal; `LetterEvent` tidak memakai `Loggable`. Catatan manifest backup diperbarui agar tidak lagi menyatakan modul lampiran belum ada (D9).
- **UAT terisolasi** `.uat-phase07` (SQLite, API 18025, SPA 14025, cookie `school_phase07_session`, launcher `scripts/phase07-uat.php`) dengan 125 surat sintetis `UAT-P07`, 64 file lampiran, 11 surat void, nomor sumber duplikat dan kosong, serta skenario file berbahaya. `.uat-phase07/` ditambahkan ke `.gitignore` (D10).

Tidak dikerjakan pada 07a (non-goals D10): 07b/07c, disposisi, penomoran resmi, approval/sign, template/PDF, OCR/e-sign/email/WhatsApp, arsip/versioning Fase 09, retensi/pemusnahan (DEC-13), antivirus, full-text search, ekspor, Dapodik, merge/deploy, migrasi database operasional/MariaDB, dan perubahan dependency/service/`.env`.

## 2. File dan kontrak

Path Laravel relatif terhadap `laravel-api`.

| File | Perubahan | Schema/izin |
|---|---|---|
| `database/migrations/2029_08_02_000001_create_letters_table.php` | Tabel baru `letters` (+ `version`, `direction` default incoming, tanggal, nomor sumber nullable, field isi, status, void, `created_by`, index) | Additive; FK `created_by`/`voided_by` restrict/nullOnDelete; tanpa FK tahun ajaran |
| `database/migrations/2029_08_02_000002_create_letter_attachments_table.php` | Tabel baru `letter_attachments` (+ `letter_id`, `original_name`, path unik, MIME, ukuran, sha256, uploader, soft delete) | Additive; FK surat restrictOnDelete; index surat/deleted_at |
| `database/migrations/2029_08_02_000003_create_letter_events_table.php` | Tabel baru `letter_events` (+ `event`, aktor, metadata JSON, `created_at`, index) | Immutable; tanpa update/delete endpoint |
| `app/Models/Letter.php` | Model, konstanta direction/status, relasi creator/voider/attachments/events, `Loggable` allowlist tanpa isi surat | Audit minimal PII |
| `app/Models/LetterAttachment.php` | Model + soft delete, relasi surat/uploader, `Loggable` allowlist tanpa nama asli/path/checksum | Audit minimal PII |
| `app/Models/LetterEvent.php` | Model event immutable, relasi surat/aktor, tanpa `Loggable` | Metadata minimal |
| `app/Services/Letters/LetterService.php` | Options, list/search/filter/pagination, detail, create, update, void, add/remove lampiran, download lookup; transaksi + `lockForUpdate`, versi, event, konflik itemized | Baca/tulis sesuai route |
| `app/Services/Letters/LetterAttachmentService.php` | Validasi MIME/ukuran, penulisan disk privat dengan nama UUID, deteksi ekstensi dari MIME, sha256, soft delete, streamed download + header keamanan | Tanpa URL publik |
| `app/Http/Requests/StoreIncomingLetterRequest.php` | Validasi create (tanggal, sender, subject, kategori, opsional nomor/notes) | `letters.manage` |
| `app/Http/Requests/UpdateIncomingLetterRequest.php` | Validasi update + `expected_version` wajib | `letters.manage` |
| `app/Http/Requests/VoidIncomingLetterRequest.php` | `reason` 10–255 + `expected_version` | `letters.manage` |
| `app/Http/Requests/StoreLetterAttachmentRequest.php` | `file` maks 10240 KB + `mimes`/`mimetypes` PDF/JPG/JPEG/PNG | `letters.manage` |
| `app/Http/Controllers/IncomingLetterController.php` | Sembilan aksi endpoint + streamed download | `can:letters.view`/`letters.manage` |
| `routes/api.php` | Prefix `letters/incoming` dengan sembilan route; `whereNumber` untuk surat/lampiran | Guest `401`; tanpa ability `403` |
| `config/letters.php` | Disk, path, batas file/jumlah, ekstensi/MIME, kategori, status, batas pencarian, paginasi, limit event | Nilai operasional English |
| `app/Services/BackupService.php` | Manifest menyebut lampiran surat operasional dalam pemindaian; restore drill NOT RUN | Tidak mengubah mekanisme backup |
| `scripts/phase07-uat.php` | Launcher `init`/`prepare`/`prepare-letters`/`status` terisolasi + fixture idempotent + file manual berbahaya | Hanya `.uat-phase07` SQLite |
| `tests/Feature/Letters/IncomingLetterTest.php` | 20 tes / 294 assertions | Matriks 07a (lihat §3) |
| `vue-app/src/views/pages/public/IncomingLetters.vue` | Halaman daftar/filter/detail/create/edit/void, upload/remove/download lampiran, history, konflik Reload Latest | Tombol mengikuti ability; server tetap menegakkan izin |
| `vue-app/src/router/index.js` | Route `/public/letters/incoming` (name `incomingLetters`, breadcrumb `Incoming Letters`, ability `letters.view`) | Guard route |
| `vue-app/src/layout/AppMenu.vue` | Hapus `Letters Form` dummy; grup **Letters** → **Incoming Letters** | `letters.view` |
| `.gitignore` | Tambah `.uat-phase07/` | Runtime UAT tidak dikomit |

Kontrak endpoint (detail di `docs/API-CONTRACTS.md`):

| Method | Endpoint | Izin | Catatan |
|---|---|---|---|
| GET | `/api/letters/incoming/options` | `letters.view` | Kategori, status, paginasi, batas file/ekstensi |
| GET | `/api/letters/incoming` | `letters.view` | `search`, `category`, `status=active|void|all`, `received_from/to`, `per_page`, `page` |
| GET | `/api/letters/incoming/{letter}` | `letters.view` | Detail + lampiran + 100 event terbaru |
| POST | `/api/letters/incoming` | `letters.manage` | Create; `422` validasi |
| PUT/PATCH | `/api/letters/incoming/{letter}` | `letters.manage` | Update + `expected_version`; `409` itemized |
| POST | `/api/letters/incoming/{letter}/void` | `letters.manage` | Alasan 10–255 + versi; `409` |
| POST | `/api/letters/incoming/{letter}/attachments` | `letters.manage` | Multipart `file`; `422` tipe/ukuran/limit |
| GET | `/api/letters/incoming/{letter}/attachments/{attachment}/download` | `letters.view` | Streamed response privat + header keamanan; lintas surat `404` |
| DELETE | `/api/letters/incoming/{letter}/attachments/{attachment}` | `letters.manage` | Soft delete; `409` bila surat void |

Tidak ada route delete surat (`DELETE /api/letters/incoming/{id}` → `405`), tidak ada unvoid, dan tidak ada endpoint event/lampiran restore pada 07a.

## 3. Evidence

| ID | Metode / command | Fixture / peran | Expected | Actual | Status |
|---|---|---|---|---|---|
| E1 | `php artisan test tests/Feature/Letters/IncomingLetterTest.php` | SQLite in-memory | Matriks 07a PASS | **20 passed / 294 assertions, 15,13 s** | PASS |
| E2 | `php artisan test` (suite penuh) | SQLite in-memory | Tidak ada regresi | **487 passed / 13 skipped / 4.786 assertions, 374,84 s**; skip adalah tes integrasi MariaDB tanpa password | PASS |
| E3 | `./vendor/bin/pint --test` pada 18 file PHP berubah/baru | — | Gaya bersih | **PASS 18 files** | PASS |
| E4 | `php artisan route:list --path=letters` | — | Sembilan route terdaftar | **9 route**; tanpa route delete surat | PASS |
| E5 | `npm run build` | Vite 6 | Build sukses | **PASS 26,18 s**; warning chunk >500 kB sudah ada sebelumnya | PASS |
| E6 | `npx eslint --quiet` pada file frontend berubah/baru | ESLint | 0 error | **0 error** | PASS |
| E7 | `php scripts/phase07-uat.php status` | `.uat-phase07` SQLite terisolasi | Guard lolos; fixture siap | **`[ISOLATION GUARD PASS]`**; 126 surat (115 active/11 void), 64 baris lampiran (63 aktif + 1 soft-deleted), 64 file, 221 event, 17 nomor sumber kosong, 10 surat nomor duplikat, 5 user | PASS |
| E8 | `bash .uat-phase07/api-guard.sh` (curl + cookie UAT, output `.uat-phase07/guard-output.txt`) | `uat.kepsek`, `uat.waka`, `uat.operator` | Izin dan validasi server | Create/update kepsek & waka **403**; update/void stale **409** + `current_versions`; future `received_date`/kategori invalid/alasan pendek **422**; `shell.php`, `payload.exe`, `page.html`, `vector.svg`, `report.pdf.php`, `oversize.pdf` **422**; filter kategori/status/rentang valid; rentang terbalik **422**; lampiran lintas surat **404**; surat hilang **404**; delete surat **405** | PASS |
| E9 | Browser DOM (Chrome DevTools, SPA 14025/API 18025) | `uat.operator` | Daftar, filter, detail, history | 115 active, paginasi 20, 10 kolom, pencarian/filter kategori/status/rentang, detail `#000001` (5 lampiran, 6 event), detail void; checklist tahap 2 **13/13** | PASS |
| E10 | Browser DOM + API | `uat.operator` | Create/edit/upload/remove/void/konflik/390 px | Surat `#000126` dibuat, diedit (version 1→2), lampiran diunggah/dihapus, void dengan alasan (version 3, read-only); konflik `409` + **Reload Latest** memulihkan versi; `document.scrollWidth=390`; label English; 0 error console aplikasi | PASS |
| E11 | Browser DOM + API | `uat.kepsek` dan `uat.waka` | View-only | Tanpa New/Edit/Void/Upload/Remove; list/detail/history/download `200`; create/update/void/upload/remove **403**; checklist **10/10** per peran | PASS |
| E12 | `curl -i` download lampiran + `.uat-phase07/download-headers.txt` | `uat.operator` | Header privat | **HTTP 200**, `Content-Type: application/pdf`, `Content-Disposition: attachment; filename=UAT-P07-L001-A1.pdf`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: sandbox; default-src 'none'`, `Cache-Control: no-store, private`, body PDF valid | PASS |
| E13 | Query read-only SQLite `.uat-phase07` | — | Audit dan immutability | `activities` surat `#000126` hanya memuat direction/tanggal/nomor/kategori/status/versi/pembuat (tanpa sender/subject/notes); audit lampiran hanya `letter_id`/MIME/ukuran (tanpa nama asli/path/checksum); 221 event; 1 baris lampiran soft-deleted dengan file tetap ada | PASS |
| E14 | Pemeriksaan storage + manifest backup | — | Lampiran privat tercakup backup | File di `.uat-phase07/storage/app/attachments/letters/{id}/{uuid}.{ext}`; manifest menyebut lampiran surat operasional dan restore drill **NOT RUN** | PASS |

**NOT RUN (tidak dikonversi menjadi PASS):**

- **Unduhan artefak PDF melalui otomasi browser**: profil Chrome otomatis sesi ini tidak dapat menangkap response PDF sebagai artefak (response fetch/XHR sintetis `204`/`ERR_FAILED`, navigasi blank), sedangkan CSV/TXT/PNG berhasil. Wiring tombol dan URL sudah diverifikasi, serta header HTTP dan body PDF diverifikasi via curl/API; konfirmasi manual ada di checklist langkah 7.
- Restore drill backup yang memuat lampiran surat (manifest hanya diperbarui).
- Migrasi/commit pada **MariaDB/InnoDB** dan perilaku trigger; seluruh bukti memakai SQLite terisolasi.
- Race multi-proses/dua tab nyata untuk `409`; konflik diuji lewat tes otomatis dan versi yang diubah di API UAT.
- Screenshot-based visual regression (bukti browser berupa snapshot DOM/API dan screenshot manual di runtime yang tidak dikomit).
- Pembersihan file lampiran soft-deleted (menunggu keputusan retensi DEC-13) dan antivirus (non-goal).

## 4. UAT manual

Environment: `.uat-phase07` SQLite terisolasi, SPA `http://127.0.0.1:14025`, API `http://127.0.0.1:18025`, cookie `school_phase07_session`, launcher `laravel-api/scripts/phase07-uat.php`. Server berjalan selama review dan **dihentikan 2026-11-01 setelah penerimaan pemilik** (DEC-27); data `.uat-phase07` tidak di-reset dan perintah restart ada di `PHASE-07A-MANUAL-REVIEW.md`.

Akun review: `uat.operator`/`uat.admin` (`letters.manage`) dan `uat.kepsek`/`uat.waka` (view-only). Password hanya berada di runtime `.uat-phase07/account-password.txt` yang diabaikan; tidak dicetak di dokumen.

Fixture `prepare-letters` (idempotent):

- 125 surat `UAT-P07` bernomor agenda internal, tanggal tersebar Jan–Okt 2026, tujuh kategori config, nomor sumber kosong setiap kelipatan 7, nomor duplikat `UAT-P07/DUP/2026/001` setiap kelipatan 11 (kecuali kelipatan 7), dan 10 surat void.
- Lampiran: 5 file pada `#000001`, 2 file pada `#000002`–`#000010`, 1 file pada `#000011`–`#000050`; campuran PDF dan PNG sintetis.
- File manual berbahaya di `.uat-phase07/manual-files/` (`shell.php`, `payload.exe`, `page.html`, `vector.svg`, `report.pdf.php`, `oversize.pdf`, `valid.pdf`, `valid.png`).
- Keadaan setelah pengujian agen: 126 surat (115 active, 11 void); `#000126` buatan UI dalam keadaan void version 3 dengan lampiran yang pernah dihapus; total 64 baris lampiran/64 file; 221 event.

Hasil UAT yang dijalankan agen:

1. **Daftar/filter** (`uat.operator`): 115 active, 10 kolom, paginasi 20; pencarian nomor/pengirim/perihal; filter kategori Invitation, status Void, dan rentang tanggal terima; validasi rentang terbalik; Clear.
2. **Detail**: `#000001` menampilkan 5/5 lampiran, metadata file, dan history 6 event; `#000012` menampilkan status void, aktor, dan alasan.
3. **Create/edit**: surat `#000126` dibuat dengan nomor sumber opsional terisi; edit Subject/Notes menaikkan versi dan menambah event Updated; validasi tanggal/kategori/alasan diuji lewat API.
4. **Lampiran**: upload PDF+PNG, batas 5/5, remove dengan konfirmasi, file tetap di storage; unduhan wiring mengarah ke `/api/letters/incoming/126/attachments/64/download`; header privat diverifikasi curl.
5. **Void**: dialog konfirmasi + alasan wajib; surat menjadi read-only; tombol Edit/Void/Upload/Remove hilang; tetap tampil di filter Void; lampiran tetap dapat diunduh.
6. **Konflik**: update dengan versi basi menghasilkan `409` + panel Conflict tanpa data berubah; **Reload Latest** memuat versi terbaru dan mempertahankan isi asli.
7. **Peran view-only**: `uat.kepsek` dan `uat.waka` hanya dapat membaca/mengunduh; tombol tulis tidak tampil; API tulis `403`.
8. **English/390 px/console**: label English; 390 px tanpa overflow halaman (tabel bergulir horizontal di wrapper, dialog 367 px); ESC menutup dialog; 0 error console dari aplikasi.

**Temuan dan batasan selama UAT:**

- **Bukan defect produk**: exception internal PrimeVue 4.3.1 `Select.alignOverlay` (8 entri) muncul saat orientasi/resize viewport berubah; dapat direproduksi hanya dengan resize, tidak memblokir fungsi, dan tidak diperbaiki karena pembaruan dependency tidak diotorisasi.
- **Bukan defect produk**: tombol download tidak menampilkan artefak pada otomasi Chrome (batasan browser/profil), sementara endpoint dan header terverifikasi. Checklist manual meminta konfirmasi unduhan.
- **Bukan defect produk**: surat `#000001` tidak menampilkan kontrol upload karena sudah mencapai batas 5/5; tombol remove tetap ada untuk pengelola.
- Tidak ada perubahan kode produk yang diperlukan akibat UAT; penyesuaian hanya pada skrip otomasi UAT (sesi login dan penantian state), yang tidak dikomit.

## 5. Risiko dan keputusan

- **Nomor sumber tidak unik** (D2): duplikat sengaja diizinkan dan diuji; referensi agenda internal stabil. Penomoran resmi menunggu 07b/DEC-06.
- **Tanpa hard delete** (D6): surat tidak dapat dihapus; lampiran memakai soft delete dan file dipertahankan sampai DEC-13. Ruang storage dapat tumbuh selama keputusan retensi belum ada.
- **Batas upload** (D5): 10 MB/5 file; validasi ekstensi + MIME + `UploadedFile` menolak file berbahaya yang diuji, tetapi antivirus di luar scope. Lampiran hanya dapat diunduh lewat endpoint berizin.
- **Konkurensi** (D6): update/void memakai transaksi + `lockForUpdate` + `expected_version`; konflik mengembalikan `409` itemized. Race dua tab/proses nyata tetap NOT RUN.
- **Audit/history** (D9): `activities` memakai allowlist minimal; `letter_events` immutable. Event tidak memiliki endpoint tulis langsung dari klien.
- **Batas bukti**: seluruh UAT memakai SQLite; MariaDB/InnoDB, trigger, dan restore drill dengan lampiran surat belum dijalankan. Migrasi belum diterapkan pada database operasional.
- **Backup**: manifest kini menyebut lampiran surat dan menandai restore drill NOT RUN; tidak mengubah mekanisme backup.
- **UAT lifecycle**: server 14025/18025 dihentikan pada 2026-11-01 setelah penerimaan pemilik sesuai DEC-27. Proses diverifikasi melalui command line sebelum `taskkill /T /F`: API `php.exe -S 127.0.0.1:18025 -t public scripts/phase07-uat.php` (PID 19564, tree 19968) dan rantai npm/vite SPA (PID 19228 → 19196 → 20504 → 9060). Port 14025/18025 terverifikasi bebas; Apache (PID 1304, port 80/443) dan MariaDB (PID 18244, port 3306) tidak disentuh; data `.uat-phase07` dipertahankan.

## 6. Sinkronisasi dokumen

Diperbarui: `docs/API-CONTRACTS.md` (sembilan endpoint dan aturan 07a), `docs/DATABASE.md` (tiga tabel dan catatan implementasi), `docs/ACCESS-CONTROL.md` (matriks route/ability 07a), `docs/OPERATIONS.md` (lampiran privat dan backup), `docs/REQUIREMENTS.md` (FR-08 07a partial), `docs/TESTING.md` (bukti dan NOT RUN 07a), `docs/DECISIONS.md` (catatan implementasi DEC-32), `CHANGELOG.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-07-LETTERS.md`, `reports/feature-update/PHASE-07-PLAN.md` (status implementasi), `reports/feature-update/INDEX.md`, `reports/feature-update/HANDOFF.md`, `PROJECT_CONTEXT.md`, laporan ini, dan checklist manual.

Commit/push dilakukan agar pemilik dapat meninjau implementasi di GitHub; commit/push bukan penerimaan manual. Penerimaan pemilik 2026-11-01 WIB (#1–#16) dicatat pada commit dokumentasi tersendiri. Tidak ada merge/deploy/force-push, perubahan `.env` utama, dependency/lockfile, service, DB terlarang, atau migrasi database operasional/MariaDB.

## 7. Langkah berikutnya

1. **Penerimaan pemilik tercatat:** pernyataan **"Pengujian nomor 1-16 sudah berhasil dan saya approved."** (2026-11-01 WIB) menetapkan checklist 07a #1–#16 **APPROVED**; agen tidak mengulang skenario dan item NOT RUN tetap NOT RUN. Server 14025/18025 dihentikan sesuai DEC-27 dan data `.uat-phase07` dipertahankan.
2. 07b dan 07c belum dimulai; keduanya memerlukan rencana dan keputusan terpisah. DEC-06/DEC-11 tetap OPEN; 07c memerlukan keputusan dependency PDF.
3. Merge/deploy, migrasi database operasional/MariaDB, perubahan dependency/service/`.env` tetap tidak diotorisasi.
