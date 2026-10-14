# Riwayat perubahan proyek

Catat perubahan selesai; jangan menyalin riwayat template `vue-app/CHANGELOG.md` menjadi riwayat aplikasi. Riwayat commit sebelum paket ini belum direkonstruksi. Tambah entri terbaru di atas entri lama; koreksi lewat addendum, jangan menghapus bukti sebelumnya.

## Format entri
```markdown
## [YYYY-MM-DD] Judul perubahan
- Type: Feature | Fix | Security | Schema Change | Docs | Chore
- Scope / fase:
- Files touched:
- What changed:
- Why:
- Validation: perintah + hasil aktual; NOT RUN bila tidak dijalankan
- Notes / risks:
- Evidence: laporan fase / SHA aktual bila sudah ada
```

## Entries

## [2026-10-14] Paket dokumentasi pengembangan OpenCode disiapkan
- Type: Docs
- Scope: aturan kerja, kebutuhan, arsitektur, schema baseline/usulan, roadmap 14 fase, prompt dan template evidence.
- Files touched: berkas Markdown dalam paket ini; belum diterapkan/commit/push ke repo oleh penyusun.
- What changed: dokumentasi disiapkan sebagai paket untuk dipasang dan ditinjau pemilik.
- Why: mendukung pengembangan fitur bertahap melalui OpenCode berdasarkan tujuan operator sekolah.
- Validation: source review pada baseline b02a87f; pemeriksaan struktur dan tautan paket. Runtime/test/build/browser NOT RUN.
- Notes / risks: target fitur dan role baru merupakan usulan; implementasi belum dimulai. Sumber App Gudang dipakai sebagai pola proses, bukan teknologi atau hasil QA.
- Evidence: reports/feature-update/PHASE-00-SOURCE-BASELINE.md.
