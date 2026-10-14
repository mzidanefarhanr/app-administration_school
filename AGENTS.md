# Aturan kerja OpenCode dan agen kode

## Tujuan dan batas scope
Aplikasi membantu operator sekolah. Pertahankan Laravel API + Vue SPA; jangan migrasi framework atau menerapkan pola Blade App Gudang. Sumber fakta: kode aktual, migration, instruksi pemilik, dan bukti pengujian terbaru. Dokumen lama tidak mengalahkan source aktual untuk klaim implementasi.

Urutan instruksi: instruksi eksplisit pemilik untuk sesi berjalan; aturan repo; spesifikasi fase; panduan umum. Roadmap merupakan rencana, bukan otorisasi seluruh fase. Kerjakan satu subfase yang diminta, termasuk perbaikan regresi akibat perubahan itu. Temuan di luar scope dicatat, jangan diam-diam memperluas pekerjaan.
Tidak perlu meminta persetujuan ulang untuk edit rutin dalam scope yang sudah diotorisasi. Perubahan lintas file yang diperlukan fase tersebut termasuk scope. Klarifikasi hanya untuk keputusan bisnis yang benar-benar memengaruhi hasil atau tindakan berisiko yang belum diotorisasi. Jangan menghentikan pekerjaan hanya karena jumlah file berubah banyak.

## Mulai sesi
1. Baca PROJECT_CONTEXT, ROADMAP, HANDOFF dan spesifikasi subfase aktif.
2. Catat branch, HEAD, working tree dan lingkungan. Jika baseline berbeda, audit diff relevan dahulu.
3. Jangan menimpa perubahan pemilik. Buat branch lokal jika diperlukan dan belum ada branch kerja yang sesuai; jangan berpindah branch dengan mengabaikan perubahan lokal.
4. Bedakan kode lengkap, scaffold, data demo, rencana, dan hasil runtime.
5. Pastikan database serta filesystem uji terisolasi sebelum test/mutasi data.

## Kontrak kode
- Hak akses harus ditegakkan di backend untuk operasi dan objek; menyembunyikan tombol bukan perlindungan.
- Gunakan kemampuan bernama dan Policy/Gate terpusat. Jangan menyebar cek angka `type_user_id` di controller/component baru.
- Jangan mengubah ID role/status baseline tanpa pemetaan dan migration yang sesuai.
- Buat migration baru; jangan mengedit migration yang sudah pernah dijalankan pada database pemilik.
- Hindari `migrate:fresh`, reset database, penghapusan massal, dan restore ke database aktif tanpa instruksi eksplisit pemilik. Pengujian memakai database disposable yang telah dibuktikan terisolasi.
- Nomor identitas disimpan string; nilai kosong diperlakukan konsisten; nama boleh sama.
- Jangan memakai umur tersimpan sebagai kebenaran; hitung dari tanggal lahir sesuai tanggal acuan.
- Update bersama memakai transaksi dan kontrol konkurensi atomik. Pertahankan API lama sampai consumer Vue ikut disesuaikan dan diuji.
- GET tidak boleh mengubah domain data. Mutasi memerlukan validasi server, izin, dan audit yang sesuai.
- Audit dibuat server; kecualikan password/hash/token dan minimalkan atribut pribadi.
- File pribadi disimpan di luar public web root dan diunduh lewat endpoint berizin.
- Jangan membuat tombol dummy, mengaktifkan route placeholder, atau menambahkan integrasi eksternal yang belum diminta.

## Perintah dan bukti
Backend dari `laravel-api`: `composer install`, `php artisan route:list`, `php artisan test` hanya setelah DB uji terisolasi; `./vendor/bin/pint --test <file-php-berubah>`.
Frontend dari `vue-app`: `npm ci`, `npm run build`; lint non-mutasi misalnya `npx eslint src/path.vue` setelah dependensi terpasang. `npm run lint` baseline berisi `--fix`; jangan pakai sebagai pemeriksaan read-only.
Jangan menyatakan test memakai SQLite: baseline `phpunit.xml` mengomentari pengaturan tersebut.

OpenCode belum diasumsikan memiliki browser. Jika tidak tersedia, laporkan keterbatasan dan beri checklist manual kepada pemilik sebelum meminta pemeriksaan. Jangan mengklaim screenshot/Playwright/manual PASS tanpa bukti. Jika akses browser tersedia dan tindakan berada dalam scope, lakukan pengujian yang relevan lalu catat metode.

## Penutupan sesi
Perbarui CHANGELOG, laporan fase, INDEX, HANDOFF, dan status ROADMAP sesuai hasil aktual. Update DATABASE/API/ACCESS-CONTROL/PROJECT_CONTEXT bila kontraknya berubah. Jangan duplikasi keseluruhan dokumen dalam setiap laporan.
Catat perintah, hasil, jumlah test bila diketahui, SHA yang diuji, NOT RUN, blocker, risiko dan langkah berikutnya. Persetujuan manual terpisah dari hasil otomatis.
Commit/push/PR mengikuti instruksi pemilik; merge/deploy/restore aktif bukan konsekuensi otomatis dari fase selesai. Jika commit belum diotorisasi, biarkan hasil reviewable dan laporkan working tree.
Jangan menulis SHA masa depan untuk commit yang belum ada. Gunakan SHA parent dan daftar perubahan belum commit, lalu catat SHA aktual pada checkpoint berikutnya.
