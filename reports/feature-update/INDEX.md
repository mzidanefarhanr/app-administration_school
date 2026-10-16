# Indeks laporan pengembangan

| Dokumen | Cakupan | Status |
|---|---|---|
| [Baseline source awal](PHASE-00-SOURCE-BASELINE.md) | Review source penyusunan paket (baseline b02a87f) | SOURCE REVIEW; runtime NOT RUN |
| [Fase 00 runtime audit](PHASE-00-RUNTIME-AUDIT.md) | Audit HEAD b699fec: source, route, Vue, schema, akses, seed, lingkungan | PARTIAL; test/build/browser NOT RUN |
| [Fase 01a authorization backend](PHASE-01A-ACCESS.md) | Peran/kemampuan fleksibel, enforcement Gate, IDOR FirstLogin, admin terakhir, isolasi DB uji, ekspor siswa (endpoint CSV terlindungi + batas baris), pemetaan legacy DEC-14, UI English, inisialisasi identitas pasca-login langsung | IMPLEMENTED + KOREKSI DEFECT; 51 test PASS; build PASS; unit race test PASS; browser/HTTP UAT PASS (sintetis); **penerimaan manual pemilik: APPROVED (scope checklist 01a)** 2026-10-15 WIB; modul surat/izin/pembelajaran N/A |
| [Panduan review manual 01a](PHASE-01A-MANUAL-REVIEW.md) | Checklist UAT sintetis (roles, izin ekspor, 403, batas baris, akun nonaktif, UI English, login langsung) + perintah server | APPROVED pemilik: seluruh nomor #1–#14 (2026-10-15 WIB) |
| [Fase 01b implementasi API & error auth](PHASE-01B-API-AUTH.md) | Implementasi interceptor instance apiLogin, CSRF 419 single retry, penanganan 401/403/422/429/5xx/timeout, guard router child-to-child per ability, single logout 204, alur forgot & reset password end-to-end (mail log), UI English | IMPLEMENTED & VERIFIED (55 test backend PASS; 10 unit test frontend PASS; build PASS; UAT browser PASS); review PENDING |
| [Panduan review manual 01b](PHASE-01B-MANUAL-REVIEW.md) | Checklist UAT sintetis 01b (forgot/reset password via mail log, 419 retry, child guard, session expired redirect, single logout, UI English) | PENDING (siap ditinjau pemilik) |
| [Fase 01b audit API & error auth](PHASE-01B-API-AUTH-AUDIT.md) | Audit awal kondisi baseline 01b, instance axios, router guard, alur reset password, dan rencana C-01..C-06 | AUDIT SELESAI (arsip audit perencanaan) |
| [Bukti UAT roles (English)](evidence/uat-roles-en.png) | Tangkapan layar `/master/roles` English pada UAT sintetis | Artefak (data sintetis) |
| [Bukti UAT operator dashboard](evidence/uat-operator-dashboard.png) | Tangkapan layar dashboard operator tanpa widget online users & tanpa 403 | Artefak (data sintetis) |
| [Bukti UAT operator profile](evidence/uat-operator-profile-dropdown.png) | Tangkapan layar dropdown profil operator dengan nama & peran sesi | Artefak (data sintetis) |
| [Bukti UAT ekspor CSV](evidence/uat-students-export.csv) | CSV hasil unduhan endpoint terproteksi (sintetis) | Artefak (data sintetis) |
| [Handoff](HANDOFF.md) | Checkpoint dan langkah berikutnya | Fase 01a DITERIMA pemilik; Fase 01b IMPLEMENTED & VERIFIED (menunggu review pemilik); subfase 01c NOT STARTED |
| [Template laporan](PHASE-REPORT-TEMPLATE.md) | Format evidence, bukan hasil uji | TEMPLATE |
| [Validasi paket](VALIDATION.md) | Struktur/tautan/ZIP | Validasi dokumentasi saja |

Tambah laporan tiap subfase dengan tanggal, branch/SHA, status teknis, status review dan link evidence. Jangan menganggap template sebagai laporan selesai. Contoh nama setelah dibuat: PHASE-01A-ACCESS.md; PHASE-02B-BACKUP-RESTORE.md. Jangan menautkan file belum ada sebagai hasil aktual.
