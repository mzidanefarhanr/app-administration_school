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
- **Batasan klaim perlindungan**: Perlindungan integritas audit dan pencegahan modifikasi/rollback ditegakkan pada lapisan API aplikasi dan ORM Eloquent, bukan merupakan klaim kekebalan terhadap administrator sistem operasi atau administrator database langsung (root DB/OS admin).
- Jangan mematikan console.error/warn global untuk membuat QA tampak bersih. Baseline main.js mematikannya di produksi; ganti dengan logging yang terukur tanpa data pribadi pada fase observability.

## Lampiran
Whitelist tipe MIME/ekstensi/ukuran; tolak executable dan path traversal; nama storage acak; simpan di disk privat; endpoint download menegakkan izin objek. Preview konten aktif seperti HTML/SVG harus dibatasi atau disajikan sebagai download aman. Jangan menaruh backup di public storage. Upload gagal/metadata gagal membersihkan orphan sesuai transaksi/kompensasi.

## Operasi
Environment rahasia tidak masuk git. Nilai VITE_* terlihat oleh browser, jadi tidak boleh berisi secret. HTTPS pada produksi; cookie Secure/SameSite dan Sanctum/CORS selaras deployment. Jangan membuka allowed origins wildcard sebagai solusi login.
Akun bootstrap diprovisi aman, tanpa password tetap di seeder. Rate limit login baseline dipertahankan; tambah pembatasan ekspor/impor/backup bila diperlukan.
Retensi arsip, audit, backup dan pemusnahan ditentukan pemilik; paket ini tidak mengklaim kepatuhan hukum tertentu. Dokumentasikan who/what/when/reason untuk aksi sensitif dan insiden yang benar-benar terjadi.
