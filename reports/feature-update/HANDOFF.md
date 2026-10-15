# Handoff pengembangan aplikasi sekolah

Checkpoint terbaru: 2026-10-15 (WIB). Branch `main`. Fase 00–01a dicatat pada commit `0f1a02765a4a14b82ec6d0a24aefa988b0016fdd` ("feat(phase-00-01a): record baseline audit and implement access control"), `parent b699feccd65357a10f7430c165400906e6394d49`. Commit `0f1a027` **sudah di-push ke `origin/main` oleh pemilik**. Kebijakan baru: commit + push per fase ke branch kerja aktif **diotorisasi** (merge/force-push/rewrite history/deploy manual tetap tidak). Perubahan pemilik pada `CHANGELOG.md` (entri "Pengembangan fitur iteratif via sesi Claude") **sengaja tidak di-commit** (masih unstaged) agar tidak mencampur edit pemilik dengan catatan fase.

## Status fase
- Fase 00 — Audit baseline: PARTIAL (source+env audit; browser NOT RUN).
- Fase 01a — Authorization backend: **IMPLEMENTED + PENYELESAIAN (DEC-14) + koreksi akhir** — 49 test PASS; build PASS; UI English; ekspor siswa via endpoint CSV terlindungi dengan batas baris (tolak, bukan potong); pemetaan legacy dikoreksi; UAT browser/HTTP PASS pada data sintetis. Penerimaan manual menyeluruh: PENDING.
- Fase 01b/01c dan fase lain: NOT STARTED.

## Hasil sesi ini (penyelesaian 01a + koreksi akhir + fix defect operator)
- **DEC-14 APPROVED (2026-10-14 WIB)**: register publik ditutup; akun `status_active=false` ditolak login + request berikutnya; ekspor siswa via `students.export` (frontend+backend, ikut lingkup data); pemetaan legacy `Admin`→Admin Sistem, `User` **tidak** otomatis Operator (default deny); UI English. Persetujuan ini = **kebijakan**, bukan penerimaan manual seluruh implementasi.
- **Ekspor siswa**: endpoint `GET /api/students/export` (`students.view`+`students.export`), kolom eksplisit, filter sama dengan daftar (seluruh hasil filter). **Batas baris**: >10.000 → **422** English (minta persempit filter), **bukan potong senyap** (`config/students.php`, `STUDENTS_EXPORT_MAX_ROWS` dapat diturunkan). Leading zero & CSV escaping & neutralisasi formula. `Student.vue` unduh via endpoint; tombol mengikuti izin; pencabutan izin berlaku pada request berikutnya.
- **Legacy**: migration `2029_07_20_000003` dikoreksi (hanya `Admin`→`admin-sistem`; `down()` tidak menghapus penetapan manual).
- **UI English**: `Roles.vue` (label dipisah dari identifier), nama peran English di katalog, `Accounts.vue` + dialog Assign Roles, tombol/notifikasi ekspor `Student.vue`, `authStore` abilities + `can()`.
- **Perbaikan defect UAT Operator**:
  - Identitas pengguna login dipisahkan dari `/api/users` via `authStore.fetchIdentity()` menggunakan kontrak ringan `GET /api/check-auth`.
  - `ListUsersOnlineWidget.vue` diproteksi `v-if="canViewUsers"`, tidak memanggil `/api/users` jika tidak memiliki `users.view`.
  - `AppUsers.vue` & `AppMenu.vue` null-safe dan beralih ke gating kemampuan bernama (menghapus inline `type_user_id`).
  - Logout menggunakan endpoint backend session (`POST /logout`), tidak bergantung pada data daftar pengguna.
  - State antar-akun dibersihkan saat logout (`$reset()`).
  - `main.js` meregistrasikan directive PrimeVue `StyleClass` (`v-styleclass` tanpa warning).
  - `apiLogin/index.js` menghapus fallback IP lama, melempar error konfigurasi English jika `VITE_API_BASE_URL` tidak valid, dan membedakan network error dari 401.
  - `Login_2.vue` membedakan network error dari invalid credentials (422).
  - Script `"dev:uat"` ditambahkan ke `package.json`.
- Bukti verifikasi: `php artisan test` → **49 passed (119 assertions)**; `npm run build` PASS; `pint --test` PASS; UAT Playwright & HTTP PASS di 4 peran (Operator, Admin, Principal, Waka) dengan 0 console error dan 0 `/api/users` calls pada non-admin.

## Keputusan disetujui
DEC-03 (peran/kemampuan) dan DEC-14 (register/nonaktif/ekspor/legacy/Bahasa UI; disetujui 2026-10-14 WIB). Persetujuan kebijakan ≠ penerimaan manual implementasi.

## Blockers / keputusan terbuka
- **BLK-03**: `DatabaseSeeder` masih memuat identitas pribadi (belum diperbaiki; direkomendasikan sebelum data operasional).
- Makna `status_user_id` belum ditegakkan. Reset password end-to-end, bootstrap pengguna pertama, CORS/Sanctum deployment, strategi cabut token vs sesi: OPEN.
- Enforcement modul surat/izin/pembelajaran hanya katalog (fitur belum ada).

## Tugas berikutnya
1. Review pemilik atas laporan `PHASE-01A-ACCESS.md` dan panduan `PHASE-01A-MANUAL-REVIEW.md`.
2. Penerimaan manual pemilik (status masih PENDING).
3. Lanjut 01b atau 01c sesuai instruksi; ganti identitas asli `DatabaseSeeder` (scope terpisah).

## Aturan pembaruan handoff
Catat active subphase, branch/HEAD/dirty files, scope otorisasi, hasil, bukti, NOT RUN, keputusan, blocker dan next action. Ganti ringkas tiap sesi; jangan menulis SHA masa depan. Persetujuan manual terpisah dari hasil otomatis.
