# Indeks laporan pengembangan

| Dokumen | Cakupan | Status |
|---|---|---|
| [Baseline source awal](PHASE-00-SOURCE-BASELINE.md) | Review source penyusunan paket (baseline b02a87f) | SOURCE REVIEW; runtime NOT RUN |
| [Fase 00 runtime audit](PHASE-00-RUNTIME-AUDIT.md) | Audit HEAD b699fec: source, route, Vue, schema, akses, seed, lingkungan | PARTIAL; test/build/browser NOT RUN |
| [Fase 01a authorization backend](PHASE-01A-ACCESS.md) | Peran/kemampuan fleksibel, enforcement Gate, IDOR FirstLogin, admin terakhir, isolasi DB uji, ekspor siswa (endpoint CSV terlindungi + batas baris), pemetaan legacy DEC-14, UI English | IMPLEMENTED + PENYELESAIAN; 49 test PASS; build PASS; browser/HTTP UAT PASS (sintetis); penerimaan manual PENDING; modul surat/izin/pembelajaran N/A |
| [Panduan review manual 01a](PHASE-01A-MANUAL-REVIEW.md) | Checklist UAT sintetis (roles, izin ekspor, 403, batas baris, akun nonaktif, UI English) + perintah server | PENDING (belum dijalankan pemilik) |
| [Bukti UAT roles (English)](evidence/uat-roles-en.png) | Tangkapan layar `/master/roles` English pada UAT sintetis | Artefak (data sintetis) |
| [Bukti UAT ekspor CSV](evidence/uat-students-export.csv) | CSV hasil unduhan endpoint terproteksi (sintetis) | Artefak (data sintetis) |
| [Handoff](HANDOFF.md) | Checkpoint dan langkah berikutnya | Fase 01a selesai (penyelesaian DEC-14 + koreksi akhir); siap review pemilik |
| [Template laporan](PHASE-REPORT-TEMPLATE.md) | Format evidence, bukan hasil uji | TEMPLATE |
| [Validasi paket](VALIDATION.md) | Struktur/tautan/ZIP | Validasi dokumentasi saja |

Tambah laporan tiap subfase dengan tanggal, branch/SHA, status teknis, status review dan link evidence. Jangan menganggap template sebagai laporan selesai. Contoh nama setelah dibuat: PHASE-01A-ACCESS.md; PHASE-02B-BACKUP-RESTORE.md. Jangan menautkan file belum ada sebagai hasil aktual.
