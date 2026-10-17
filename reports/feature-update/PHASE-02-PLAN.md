# Rencana Kerja Fase 02 — Audit Terpercaya dan Backup yang Dapat Dipulihkan

- Tanggal WIB: 2026-10-17
- Mode: **perencanaan & audit source (read-only)**. Tidak ada implementasi kode, backup aktif, migrasi, atau manipulasi data yang dijalankan pada langkah ini.
- Status subfase: **PLANNING / READY FOR OWNER REVIEW**.
- Prasyarat: **Fase 01 telah DITERIMA PENUH dan DITUTUP** (01a, 01b, dan 01c seluruhnya APPROVED).
- Dokumen acuan: `AGENTS.md`, `ROADMAP.md`, `roadmap/phases/PHASE-02-BACKUP-AUDIT.md`, `PROJECT_CONTEXT.md`, dan register keputusan `DEC-02`, `DEC-09`.

---

## 1. Tujuan dan Ruang Lingkup

Fase 02 bertujuan menjamin integritas data sekolah dan kesiapan pemulihan bencana (disaster recovery) sebelum data operasional riil mulai dimasukkan pada fase-fase berikutnya. Fase 02 terdiri dari tiga subfase berurutan:

1. **Subfase 02a — Audit Server dan Rollback Terbatas**:
   - Menghilangkan celah pemalsuan log audit dari sisi klien (tampering).
   - Memastikan pencatatan audit server-side otomatis pada mutasi domain penting tanpa membocorkan data sensitif (password, hash, token).
   - Memperbaiki arsitektur rollback yang saat ini cacat atau membatasi rollback hanya pada domain yang aman dan dapat diverifikasi.
2. **Subfase 02b — Backup Manual dan Restore Drill**:
   - Membangun mekanisme backup mandiri (database dump + file privat) yang menghasilkan berkas manifest bervalidasi checksum SHA-256.
   - Melakukan uji pemulihan (restore drill) secara nyata ke database dan storage target disposable yang terisolasi.
   - Memastikan kredensial database tidak bocor pada process listing saat backup berjalan.
3. **Subfase 02c — Jadwal dan Monitoring Backup**:
   - Mengaktifkan scheduler backup otomatis dengan penanganan retry yang idempotent.
   - Menerapkan kebijakan rotasi dan retensi arsip backup.
   - Menyediakan runbook operasi untuk memantau status keberhasilan atau kegagalan backup.

---

## 2. Audit Kondisi Aktual HEAD (Baseline Pasca Fase 01)

### A. Kondisi Audit Log Saat Ini (`Activity` & `ActivityController`)
1. **Celah Pemalsuan Log oleh Klien (`POST /api/activities`)**:
   - `ActivityController::store` menerima payload langsung dari request HTTP (`before`, `after`, `new`, `delete`, `table_name`, `record_id`, `information`).
   - Klien yang terautentikasi dapat mengirimkan data audit fiktif atau menimpa rekaman riwayat aktivitas.
   - *Rencana*: Hapus atau blokir endpoint `POST /api/activities` untuk klien. Seluruh rekaman audit WAJIB dibuat secara otomatis di sisi server (server-side observer / model events via trait `Loggable`).
2. **Cacat Teknis pada Fitur Rollback (`ActivityController::update`)**:
   - Pada `ActivityController::update($id)` baris 110–115:
     ```php
     $modelName = 'App\\Models\\' . Str::studly(Str::singular($tableName));
     DB::table($modelName)->where('id', $recordId)->update($activity->before);
     ```
     `DB::table(...)` menerima nama kelas model (bukan nama tabel SQL), yang memicu exception SQL error pada database riil.
   - Tidak ada pemeriksaan hak akses spesifik (siapa yang boleh melakukan rollback).
   - Tidak ada whitelist entitas yang boleh di-rollback (berpotensi merusak relasi foreign key atau memulihkan data yang bertentangan dengan aturan bisnis).
   - Tidak ada pemeriksaan konkurensi/versi (stale rollback): jika data sudah berubah lagi setelah log dibuat, rollback tetap menimpa data terbaru tanpa peringatan konflik.
   - *Rencana*: Evaluasi apakah fitur rollback perlu dibatasi secara ketat pada model referensi tertentu dengan version check, ATAU dinonaktifkan sepenuhnya demi prinsip *immutable append-only audit trail*.
3. **Penyaringan Data Sensitif (`Loggable.php`)**:
   - Trait `Loggable` sudah memiliki `$auditExcludedAttributes` (menyaring password, remember_token, token autentikasi).
   - Perlu diaudit dan diperluas agar atribut sensitif seperti data pribadi yang tidak relevan dengan audit tidak terekam secara berlebihan.
4. **Relasi Cascade pada Tabel Activities**:
   - Foreign key `user_id` pada tabel `activities` saat ini memiliki aturan `cascadeOnDelete()`. Jika sebuah akun pengguna dihapus permanen, seluruh log aktivitas historis yang pernah dilakukannya akan ikut terhapus.
   - *Rencana*: Cegah penghapusan permanen akun yang memiliki riwayat audit, atau gunakan soft delete / `nullOnDelete()` untuk menjaga keutuhan jejak audit institusi.

### B. Kondisi Backup & Restore Saat Ini
1. **Belum Ada Mekanisme Backup**:
   - Belum ada perintah Artisan, service, atau paket backup yang terpasang di `laravel-api`.
   - Tidak ada mekanisme pembuktian restore (restore drill).
2. **Kesiapan Storage Privat**:
   - Fase 01c telah menyiapkan disk `testing` terisolasi pada `storage/framework/testing/disks/testing`.
   - Untuk backup operasional, diperlukan disk privat khusus (misal: `backups` di luar web root publik) yang tidak dapat diakses langsung via URL peramban.

---

## 3. Rincian Scope per Subfase

### A. Subfase 02a — Audit Server dan Rollback Terbatas
- **Penutupan Endpoint Klien**: Hapus atau nonaktifkan `POST /api/activities` dan `PUT/PATCH /api/activities/{id}` dari rute API publik.
- **Penegakan Server-Side Audit**: Mutasi data domain (Users, Roles, Students, Master Data) wajib dicatat secara otomatis via observer/trait Eloquent.
- **Pembersihan Log Terpercaya**:
  - Kolom `before` dan `after` hanya merekam perubahan atribut yang diizinkan (whitelist).
  - Rahasia autentikasi (`password`, `remember_token`, hash) disaring 100%.
- **Kebijakan Rollback**:
  - Jika rollback dipertahankan: batasi hanya untuk tabel referensi master sederhana, wajib verifikasi `version` record (mencegah stale rollback), dan catat audit rollback baru.
  - Jika rollback ditolak: kembalikan respons 403/404 yang konsisten dan hapus tombol rollback dari antarmuka pengguna (hindari tombol sukses palsu).

### B. Subfase 02b — Backup Manual dan Restore Drill
- **Command Backup Mandiri (`php artisan app:backup-run`)**:
  - Mengekspor database skema + data ke file `.sql` menggunakan driver yang aman.
  - Mengarsipkan berkas lampiran privat (dokumen/storage) ke dalam satu berkas terkompresi (ZIP/tar.gz).
  - Menyertakan berkas `manifest.json` yang memuat:
    - Metadata: nama aplikasi, versi, lingkungan, tanggal & waktu (UTC & WIB).
    - Checksum: SHA-256 berkas database dump dan berkas lampiran.
    - Info skema: ringkasan tabel dan versi migrasi terakhir.
- **Penyimpanan Terisolasi**:
  - File arsip disimpan pada direktori privat `storage/app/backups` (di luar public web root).
  - Akses unduhan melalui endpoint API berizin (`can:backup.manage`).
- **Command Restore Drill (`php artisan app:restore-drill`)**:
  - Menerima path berkas arsip backup dan target koneksi database.
  - **Fail-Safe Mutlak**: Menolak keras target database yang sama dengan database operasional (`backdata_administrations`) atau target yang tidak berstatus disposable.
  - Memvalidasi integritas checksum berkas terhadap `manifest.json`.
  - Memulihkan skema dan data ke database disposable, lalu menjalankan serangkaian asersi verifikasi keutuhan data.

### C. Subfase 02c — Jadwal dan Monitoring Backup
- **Penjadwalan Otomatis**:
  - Mengonfigurasi Laravel Task Scheduling di `app/Console/Kernel.php` untuk menjalankan backup harian/mingguan.
- **Rotasi & Kebijakan Retensi**:
  - Menghapus arsip backup yang sudah melebihi batas retensi tanpa menyentuh backup terbaru atau backup yang sedang ditandai sebagai arsip permanen.
- **Observability & Alerting**:
  - Mencatat keberhasilan atau kegagalan setiap proses backup ke dalam sistem logging.
  - Memberikan indikator status backup terakhir yang dapat dipantau oleh Administrator Sistem.

---

## 4. Kriteria Penerimaan Fase 02

1. **Integritas Audit Trail**:
   - Tidak ada endpoint API yang mengizinkan klien menulis atau memanipulasi catatan log audit secara bebas.
   - Mutasi data pada model yang diawasi menghasilkan log `Created`, `Updated`, `Deleted` secara akurat dengan data `before`/`after` yang benar.
   - Tidak ada kata sandi, token, atau rahasia apa pun yang bocor ke dalam database audit.
2. **Integritas Backup & Manifest**:
   - Perintah backup menghasilkan satu berkas arsip terpadu yang memuat dump database, lampiran privat, dan `manifest.json`.
   - Manifest memuat checksum SHA-256 yang valid; modifikasi sekecil apa pun pada berkas backup akan terdeteksi dan menggagalkan proses restore.
   - Proses dump database tidak mengekspos kredensial database pada process list sistem operasi.
3. **Keberhasilan Restore Drill Terisolasi**:
   - Restore drill berhasil memulihkan database dan file secara 100% pada database target uji yang telah disiapkan.
   - Upaya melakukan restore ke database operasional atau target yang tidak diizinkan digagalkan secara tegas oleh fail-safe pengaman.
4. **Isolasi Pengujian**:
   - Seluruh automated test berjalan pada SQLite in-memory atau database disposable khusus drill, tanpa menyentuh data operasional.

---

## 5. Keputusan yang Diperlukan dari Pemilik

Sebelum implementasi teknis Fase 02 dimulai, ada 3 keputusan arsitektural yang memerlukan arahan dan persetujuan pemilik:

1. **DEC-02 (Lingkungan Operasional & Lokasi Penyimpanan Backup)**:
   - *Pertanyaan*: Di mana arsip backup akan disimpan dalam operasional sebenarnya?
   - *Opsi A (Rekomendasi)*: Disimpan pada direktori lokal server yang terisolasi (`storage/app/backups`), dengan opsi operator mengunduh salinan berkala ke komputer lokal/harddisk eksternal.
   - *Opsi B*: Disimpan secara otomatis ke secondary cloud storage (mis. S3-compatible storage) jika server memiliki akses internet keluar.
2. **DEC-09 (Kebijakan Retensi & Frekuensi Backup)**:
   - *Pertanyaan*: Berapa lama arsip backup disimpan dan bagaimana rotasinya?
   - *Opsi A (Rekomendasi)*: Retensi bertingkat (7 backup harian terakhir + 4 backup mingguan terakhir). Backup lama otomatis dibersihkan.
   - *Opsi B*: Retensi berbasis kuota jumlah file (misal: simpan 10 arsip backup terakhir).
   - *Pertanyaan Tambahan*: Apakah file arsip backup wajib dienkripsi password (ZIP AES-256) saat dibuat?
3. **Keputusan Kebijakan Rollback pada Subfase 02a**:
   - *Pertanyaan*: Apakah fitur rollback aktivitas audit dipertahankan atau dinonaktifkan?
   - *Opsi A (Rekomendasi)*: **Nonaktifkan Rollback (Audit Trail Append-Only Murni)**. Rollback pada aplikasi relasional kompleks sangat rentan memicu inkonsistensi foreign key dan integritas data. Prinsip audit yang baik adalah rekaman tidak dapat diubah (immutable). Koreksi kesalahan data sebaiknya dilakukan melalui update normal yang tercatat sebagai audit baru.
   - *Opsi B*: **Rollback Terbatas Ketat**. Rollback hanya diizinkan untuk data referensi master tertentu yang tidak memiliki ketergantungan relasional, dengan validasi versioning ketat.

---

## 6. Strategi Pengujian Terisolasi (Tanpa Menyentuh Database Operasional)

1. **Uji Otomatis Unit & Feature (`php artisan test`)**:
   - Uji audit trail, sanitasi rahasia, dan penolakan log palsu dijalankan pada SQLite in-memory `:memory:`.
   - Uji pembuatan manifest dan verifikasi checksum SHA-256 diuji menggunakan berkas sintetis pada disk storage `testing`.
2. **Uji Restore Drill Manual/Automated**:
   - Menggunakan database MySQL disposable khusus (misal: `school_restore_drill_uat`), terpisah sepenuhnya dari `school_access_uat` dan BUKAN `backdata_administrations`.
   - Menguji kegagalan yang disengaja: manifest rusak, checksum tidak cocok, berkas dump terpotong.
3. **Pemeriksaan Keamanan (Pint & Lint)**:
   - `./vendor/bin/pint --test` pada seluruh berkas backend baru.
   - `npx eslint ...` pada komponen frontend audit/backup yang disentuh.

---

## 7. Batasan Pekerjaan
- Tidak ada modifikasi kode aplikasi, schema database, atau eksekusi backup yang dijalankan pada sesi ini.
- Pelaksanaan Fase 02 menunggu review dan persetujuan pemilik atas rencana kerja ini dan arahan atas keputusan di Bab 5.
