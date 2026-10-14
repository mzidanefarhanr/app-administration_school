# Cara kerja dan prompt OpenCode

## Alur sesi
Pasang paket pada root repo, buka OpenCode pada repo sekolah, bukan repo App Gudang. Baca aturan dan handoff; audit HEAD; kerjakan satu subfase; uji sesuai kemampuan lingkungan; laporkan bukti; sinkronkan dokumen. Instruksi awal ini menyiapkan audit, bukan menjalankan seluruh roadmap.

## Prompt pertama — Fase 00 saja
```text
Baca AGENTS.md, PROJECT_CONTEXT.md, docs/REQUIREMENTS.md, docs/DATABASE.md,
docs/TESTING.md, roadmap/ROADMAP.md, roadmap/phases/PHASE-00-BASELINE.md,
dan reports/feature-update/HANDOFF.md.
Kerjakan Fase 00 saja: audit source, route API, consumer Vue, schema migration,
akses, seed/factory dan kesiapan lingkungan. Cocokkan dengan HEAD aktual.
Read-only terhadap kode aplikasi dan data operasional; boleh menulis laporan.
Jangan menjalankan tes yang memakai DB sampai isolasi dibuktikan.
Jangan menjalankan seeder, migrate:fresh, backup/restore data aktif atau akses
alamat API hardcode. Catat runtime/build/test/browser yang benar-benar dijalankan.
Jika browser tidak tersedia, beri tahu saya sebelum meminta checklist manual.
Perbarui laporan baseline, INDEX, HANDOFF, ROADMAP dan CHANGELOG sesuai hasil.
Tampilkan blocker, keputusan yang dibutuhkan untuk 01a, serta scope berikutnya.
Jangan commit/push/merge/deploy. Berhenti setelah laporan Fase 00 untuk review.
```

## Prompt implementasi satu subfase
```text
Saya mengotorisasi implementasi Fase <ID-subfase> sesuai spesifikasinya.
Baca AGENTS, ROADMAP, HANDOFF dan dokumen domain terkait; audit HEAD dulu.
Kerjakan seluruh scope subfase beserta tes relevan dan koreksi regresinya.
Jangan mengimplementasikan fase berikutnya atau fitur di luar scope.
Gunakan DB/storage uji terisolasi dan fixture sintetis. Jika keputusan bisnis
belum ditetapkan dan menentukan schema/perilaku, jelaskan pilihan secara ringkas.
Perbarui dokumentasi kontrak, CHANGELOG, laporan fase, INDEX dan HANDOFF.
Laporan harus membedakan source review, tes, browser dan approval manual.
Jangan commit/push/merge/deploy pada sesi ini. Berhenti setelah hasil siap review.
```
Ganti placeholder dengan satu subfase konkret. Bila ingin commit/push, instruksikan terpisah atau tambahkan scope tersebut secara eksplisit; jangan menganggap izin review adalah izin deploy.

## Prompt mencatat hasil manual
```text
Catat hasil pemeriksaan manual saya untuk subfase <ID> pada kandidat <SHA/working tree>.
Hasil: <nomor checklist + actual + lampiran bila ada>.
Perbarui laporan, INDEX, HANDOFF, ROADMAP dan CHANGELOG dengan cakupan persis
hasil saya. Bagian yang belum saya periksa tetap NOT RUN/PENDING.
Jangan mengubah kode atau menjalankan fase berikutnya.
```

## Prompt melanjutkan sesi terputus
```text
Lanjutkan subfase aktif menurut HANDOFF. Periksa HEAD dan working tree;
jangan ulangi pekerjaan selesai tanpa alasan. Cocokkan evidence dengan kode.
Selesaikan sisa scope yang sebelumnya diotorisasi; laporkan blocker nyata.
Jangan mengambil status PASS dari sesi lain untuk SHA yang sudah berubah.
```

## Laporan akhir setiap sesi
Outcome; branch/SHA/dirty files; scope selesai; file/kontrak berubah; tes dan hasil; browser/manual dan NOT RUN; risiko; keputusan terbuka; langkah berikutnya. Jangan mengklaim aplikasi siap produksi dari build semata.
Gunakan template `reports/feature-update/PHASE-REPORT-TEMPLATE.md`. Satu laporan per subfase dapat dipecah jika besar; INDEX menghubungkan semuanya.
