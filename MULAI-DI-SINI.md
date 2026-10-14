# Panduan menggunakan paket dokumentasi

Tanggal: 14 Oktober 2026 (WIB). Paket ini menyiapkan pekerjaan OpenCode, bukan bukti implementasi fitur.

## Pemasangan
1. Pastikan repo lokal adalah `app-administration_school`, bukan App Gudang.
2. Periksa `git status` dan simpan perubahan lokal yang belum selesai. Jangan melakukan reset untuk memasang dokumen.
3. Ekstrak isi paket ke root repo sehingga `AGENTS.md`, `README.md`, `docs/`, `roadmap/`, dan `reports/` berada di root.
4. README root lama hanya memiliki judul pada baseline pemeriksaan. Bandingkan dahulu jika file lokal sudah berkembang; gabungkan bagian relevan, jangan menimpa dokumentasi baru.
5. Paket tidak mengganti `vue-app/CHANGELOG.md`, README subproyek, LICENSE template, source aplikasi, atau konfigurasi environment.
6. Buka OpenCode pada root repo. Gunakan prompt pertama di `docs/OPENCODE-WORKFLOW.md`.
7. Kerjakan Fase 00 dahulu; setelah laporan diperiksa, pilih satu subfase berikutnya. Jangan memberi instruksi “kerjakan seluruh roadmap” sekaligus.

## Isi paket
- Root: README, AGENTS, PROJECT_CONTEXT, CHANGELOG dan panduan ini.
- `docs/`: kebutuhan, database, arsitektur, akses, keamanan, desain, API, pengujian, operasi, keputusan, dan prompt OpenCode.
- `roadmap/ROADMAP.md`: urutan, dependensi, prioritas, dan status pekerjaan.
- `roadmap/phases/`: 14 spesifikasi fase, termasuk cakupan, uji, serta prompt per subfase.
- `reports/feature-update/`: indeks, baseline source, handoff awal, dan template laporan fase.

## Cara membaca status
`SOURCE REVIEW` berarti kode dibaca, bukan fungsi berhasil dijalankan. Semua fase implementasi mulai dengan `NOT STARTED`; tes dan browser `NOT RUN`; review pemilik `PENDING`.
Penulisan paket ini tidak memberi OpenCode izin otomatis melakukan implementasi, commit, push, merge, atau deploy. Instruksi pemilik berikutnya menentukan scope dan otorisasinya.

## Acuan dokumentasi
Pola diadaptasi dari App Gudang: AGENTS, PROJECT_CONTEXT, CHANGELOG, roadmap fase, baseline, INDEX, dan HANDOFF. Teknologi, role, domain, status QA, serta persetujuan App Gudang tidak disalin sebagai fakta aplikasi sekolah. Tidak ada skill baru; aturan pencatatan langsung berada di AGENTS sehingga tidak memerlukan pemasangan skill.
