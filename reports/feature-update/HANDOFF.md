# Handoff pengembangan aplikasi sekolah

Checkpoint terbaru: 2026-10-15 (WIB). Branch `main`. Fase 00–01a dicatat pada commit `0f1a02765a4a14b82ec6d0a24aefa988b0016fdd` ("feat(phase-00-01a): record baseline audit and implement access control"), `parent b699feccd65357a10f7430c165400906e6394d49`. Commit `0f1a027` **sudah di-push ke `origin/main` oleh pemilik**. Kebijakan baru: commit + push per fase ke branch kerja aktif **diotorisasi** (merge/force-push/rewrite history/deploy manual tetap tidak). Perubahan pemilik pada `CHANGELOG.md` (entri "Pengembangan fitur iteratif via sesi Claude") **sengaja tidak di-commit** (masih unstaged) agar tidak mencampur edit pemilik dengan catatan fase.

## Status fase
- Fase 00 — Audit baseline: PARTIAL (source+env audit; browser NOT RUN).
- Fase 01a — Authorization backend: **IMPLEMENTED + PENYELESAIAN (DEC-14) + koreksi akhir** — 49 test PASS; build PASS; UI English; ekspor siswa via endpoint CSV terlindungi dengan batas baris (tolak, bukan potong); pemetaan legacy dikoreksi; UAT browser/HTTP PASS pada data sintetis. Penerimaan manual menyeluruh: PENDING.
- Fase 01b/01c dan fase lain: NOT STARTED.

## Hasil sesi ini (penyelesaian 01a + koreksi akhir)
- **DEC-14 APPROVED (2026-10-14 WIB)**: register publik ditutup; akun `status_active=false` ditolak login + request berikutnya; ekspor siswa via `students.export` (frontend+backend, ikut lingkup data); pemetaan legacy `Admin`→Admin Sistem, `User` **tidak** otomatis Operator (default deny); UI English. Persetujuan ini = **kebijakan**, bukan penerimaan manual seluruh implementasi.
- **Ekspor siswa**: endpoint `GET /api/students/export` (`students.view`+`students.export`), kolom eksplisit, filter sama dengan daftar (seluruh hasil filter). **Batas baris**: >10.000 → **422** English (minta persempit filter), **bukan potong senyap** (`config/students.php`, `STUDENTS_EXPORT_MAX_ROWS` dapat diturunkan). Leading zero & CSV escaping & neutralisasi formula. `Student.vue` unduh via endpoint; tombol mengikuti izin; pencabutan izin berlaku pada request berikutnya.
- **Legacy**: migration `2029_07_20_000003` dikoreksi (hanya `Admin`→`admin-sistem`; `down()` tidak menghapus penetapan manual).
- **UI English**: `Roles.vue` (label dipisah dari identifier), nama peran English di katalog, `Accounts.vue` + dialog Assign Roles, tombol/notifikasi ekspor `Student.vue`, `authStore` abilities + `can()`.
- **Perbaikan kecil**: `authStore.getUser` null-safe; 3 warning formatting `Roles.vue` dirapikan (0 problems).
- **Koreksi akhir**: tanggal DEC-14 → 2026-10-14 WIB konsisten; batas ekspor 10.000 → tolak 422 (dokumentasi + 2 tes); daftar file berubah dirapikan.
- Bukti: `php artisan test` → **49 passed (119 assertions)**; `npm run build` PASS; `pint --test` PASS; UAT HTTP+Playwright PASS (bukti `evidence/uat-roles-en.png`, `evidence/uat-students-export.csv`). Guard DB tes tidak dikendurkan.

## Keputusan disetujui
DEC-03 (peran/kemampuan) dan DEC-14 (register/nonaktif/ekspor/legacy/Bahasa UI; disetujui 2026-10-14 WIB). Persetujuan kebijakan ≠ penerimaan manual implementasi.

## Blockers / keputusan terbuka
- **BLK-03**: `DatabaseSeeder` masih memuat identitas pribadi (belum diperbaiki; direkomendasikan sebelum data operasional).
- Makna `status_user_id` belum ditegakkan. Reset password end-to-end, bootstrap pengguna pertama, CORS/Sanctum deployment, strategi cabut token vs sesi: OPEN.
- Enforcement modul surat/izin/pembelajaran hanya katalog (fitur belum ada).

## Tugas berikutnya
1. Review pemilik atas PHASE-01A-ACCESS.md §11–18 (penyelesaian + koreksi) dan bukti UAT.
2. Commit dokumentasi menyusul bila pemilik menerima/menyetujui (penerimaan manual masih PENDING); push/merge/deploy perlu instruksi terpisah.
3. Lanjut 01b atau 01c sesuai instruksi; ganti identitas asli `DatabaseSeeder` (scope terpisah).

## Aturan pembaruan handoff
Catat active subphase, branch/HEAD/dirty files, scope otorisasi, hasil, bukti, NOT RUN, keputusan, blocker dan next action. Ganti ringkas tiap sesi; jangan menulis SHA masa depan. Persetujuan manual terpisah dari hasil otomatis.
