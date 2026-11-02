# Keamanan dan data pribadi

## Penanganan data
Repo baseline memuat identitas yang menyerupai data pribadi pada seeder. Jangan menganggap semua data adalah dummy. Audit seed/factory/fixture/asset/history dan ganti contoh dengan sintetis pada working tree tanpa menyalin nilai identitas ke laporan. Pembersihan history remote, penggantian kredensial, atau perubahan visibilitas repo memerlukan scope eksplisit terpisah. Penggantian working tree tidak menghapus riwayat lama.
Jangan menyimpan NIK/KK/alamat/nomor kontak/dump nyata dalam screenshot, log debug atau zip dokumentasi. Gunakan fixture sintetis dan masking.

## Kontrol baseline yang diperbaiki
- Authorization operasi dan record, termasuk accounts, firstlogin, activities, export dan download.
- Validator server: password baru min 12 karakter, format NIK 16 digit, foreign key exists dan allowed scope; tidak bergantung pada validasi frontend.
- Audit whitelist/allowlist atribut: diterapkan pada model domain (`User`, `Role`, `Ability`, `Student`, master) via `Loggable.php`. Password, password hash, password_new, remember_token, dan token otentikasi 100% tersaring dan tidak tercatat dalam audit JSON.
- Endpoint log klien (`POST /api/activities`) dan manipulasi/rollback audit (`PUT/PATCH/DELETE /api/activities/{id}`) dinonaktifkan (HTTP 405); model `Activity` menerapkan *immutable append-only* pada model level (exception pada updating/deleting).
- Actor (`user_id`), target (`table_name`, `record_id`), aksi (`information`), dan timestamp (`created_at`) ditentukan sepenuhnya dari server session (`Auth::id()`).
- Reentrancy guard mencegah loop audit rekursif. Transaksi mutasi dan audit berjalan atomik (audit ikut ter-rollback bila transaksi domain gagal).
- Backup & Restore Keamanan (Fase 02b):
  - Kredensial database diisolasi menggunakan `--defaults-extra-file` temporer yang langsung dihapus seketika; tidak tampak pada process list OS atau log.
  - Arsip dienkripsi standar ZIP AES-256 via native PHP `ZipArchive`; passphrase min 12 karakter disimpan terpisah dari repositori/arsip.
  - Fail-safe keras restore drill: menolak database operasional `backdata_administrations`, database sumber aktif, dan database di luar allowlist disposable.
  - Anti-Zip-Slip guard: menolak entri arsip yang memuat `..` atau path traversal saat ekstraksi.
  - Audit SQL dump otomatis: menolak file dump yang memuat perintah berbahaya pengalihan koneksi database (`USE ...` atau `CREATE DATABASE ...`).
  - Unduhan backup dilindungi kemampuan bernama `backup.manage` dan validasi anti-path-traversal.
- **Batasan klaim perlindungan**: Perlindungan integritas audit dan pencegahan modifikasi/rollback ditegakkan pada lapisan API aplikasi dan ORM Eloquent, bukan merupakan klaim kekebalan terhadap administrator sistem operasi atau administrator database langsung (root DB/OS admin).
- Jangan mematikan console.error/warn global untuk membuat QA tampak bersih. Baseline main.js mematikannya di produksi; ganti dengan logging yang terukur tanpa data pribadi pada fase observability.

## Lampiran
Whitelist tipe MIME/ekstensi/ukuran; tolak executable dan path traversal; nama storage acak; simpan di disk privat; endpoint download menegakkan izin objek. Preview konten aktif seperti HTML/SVG harus dibatasi atau disajikan sebagai download aman. Jangan menaruh backup di public storage. Upload gagal/metadata gagal membersihkan orphan sesuai transaksi/kompensasi.

## Arsip dokumen privat (Fase 09a — DEC-37)
- **Penyimpanan**: file arsip disimpan di disk `local` pada `storage/app/attachments/archives/{archive_document_id}/{uuid}.{ext}` (config `archives.attachment.disk`/`path`), di luar web root; tidak ada URL publik, path/checksum tidak pernah diekspos ke API. Unduhan hanya lewat `GET /api/archives/{archive}/versions/{version}/download` setelah policy memeriksa ability `archives.*` dan ability domain subjek.
- **Header unduhan**: `X-Document-Filename` (nama asli tersanitasi), `Access-Control-Expose-Headers: X-Document-Filename`, `nosniff`, CSP `sandbox; default-src 'none'`, `Cache-Control: private, no-store`; **tanpa `Content-Disposition`** mengikuti pola 07c/08a.
- **Validasi upload**: PDF/JPG/JPEG/PNG maksimum 10 MB per file melalui `PrivateUploadValidator` bersama (allowlist ekstensi, MIME server, kecocokan ekstensi–MIME, `getimagesize`); nama file UUID dibuat server; nama asli disanitasi; SHA-256 dicatat internal untuk verifikasi, bukan untuk ditampilkan.
- **Versi immutable**: baris `archive_file_versions` tidak diubah/dihapus; versi lama tetap dapat diunduh sesuai izin. Versi baru menaikkan `current_version` dokumen dalam transaksi + `lockForUpdate` + `expected_version`/`409`; kegagalan penyimpanan/insert membuang file best-effort dan rollback.
- **Visibilitas dan izin objek**: dokumen umum (tanpa subjek) terlihat oleh `archives.view`; dokumen bertaut hanya bila aktor juga memiliki ability domain (`students.view`/`employees.view`/`letters.view`/`leaves.view`). Filter subjek eksplisit tanpa izin domain → `403`; akses/download objek tersembunyi → `404` (bukan membocorkan keberadaan). Subjek `student`/`employee` yang soft-deleted tetap boleh ditautkan dan diringkas; tautan subjek immutable setelah dibuat.
- **Audit**: allowlist server mencatat aksi dokumen/versi tanpa `title`, nomor dokumen, deskripsi, nama file asli, path storage, atau checksum; tidak ada audit khusus pembukaan/download pada 09a (audit akses download masuk 09b bila diminta).
- **Batas 09a**: tanpa preview inline, tanpa endpoint hapus/restore/soft delete, tanpa auto-purge/antivirus, tanpa OCR/fulltext, dan tanpa memindahkan lampiran lama secara destruktif. Retensi/pemusnahan file arsip tetap **DEC-13 OPEN**.
- **UAT terisolasi**: `.uat-phase09` memakai SQLite/storage terpisah; file uji sintetis dan tidak menyentuh `school_access_uat`, MariaDB operasional, atau storage lampiran operasional.

## Operasi
Environment rahasia tidak masuk git. Nilai VITE_* terlihat oleh browser, jadi tidak boleh berisi secret. HTTPS pada produksi; cookie Secure/SameSite dan Sanctum/CORS selaras deployment. Jangan membuka allowed origins wildcard sebagai solusi login.
Akun bootstrap diprovisi aman, tanpa password tetap di seeder. Rate limit login baseline dipertahankan; tambah pembatasan ekspor/impor/backup bila diperlukan.
Retensi arsip, audit, backup dan pemusnahan ditentukan pemilik; paket ini tidak mengklaim kepatuhan hukum tertentu. Dokumentasikan who/what/when/reason untuk aksi sensitif dan insiden yang benar-benar terjadi.
