# Laporan Integrasi: feature/i18n + feature/branding

**Branch:** `integration/i18n-branding`  
**Worktree:** `../school-integration`  
**Tanggal:** 2026-11-04  
**Pelaksana:** Antigravity  
**Dasar:** `reports/parallel/integration/INTEGRATION-PLAN.md`  

---

## Konstanta Integrasi

- `MAIN_SHA`: `4a140178999b40c76e777422c1c1d177c7388c07` (titik percabangan worktree A dan B)
- `A_SHA`: `c8a88507e484e0c8f336a08eb0d9953cddad3683` (`feature/i18n`)
- `B_SHA`: `5e28ecf5991a172cb418c9585f4c1ef0fff2b213` (`feature/branding`)

---

## Gerbang G0 — 2026-11-04
SHA diuji      : 4a140178999b40c76e777422c1c1d177c7388c07 (origin/main)
Perintah+hasil :
- 5.1 Branch check: school-i18n bersih & sync c8a8850; school-branding bersih & sync 5e28ecf.
- 5.2 Rule 9 check: git diff main...HEAD scope isolation 0 pelanggaran di kedua worktree.
- 5.3 Acuan desain: DESIGN.md teridentifikasi tepat di root repository.
- 5.4 REQUESTS check: R1-R3 di A (non-urgent), REQ-01 & REQ-02 di B (non-blocking).
- 5.5 Titik kembali: Tag lokal pre-integration-main, ready-i18n, ready-branding dibuat. .github/workflows tidak ada (0 CI trigger).
Hasil          : PASS
Commit baru    : (tidak ada commit kode di G0)
File disentuh  : (hanya pembuatan tag lokal)
Blocker        : tidak ada
Risiko baru    : tidak ada
Permintaan ke pemilik : izin lanjut ke Fase 1 (telah disetujui pemilik)
Bukti          : tidak ada

---

## Gerbang G1 — 2026-11-04
SHA diuji      : dce7cdd99df9a7be846bb32014023dd4948aee75
Perintah+hasil :
- git worktree add ../school-integration -b integration/i18n-branding origin/main: sukses (HEAD at 4a14017).
- cd vue-app && npm ci: sukses (426 packages installed in 48s).
- cd laravel-api && composer install: sukses (130 packages installed, package discovery DONE).
- cp INTEGRATION-PLAN.md reports/parallel/integration/INTEGRATION-PLAN.md & git commit: sukses (dce7cdd).
- Runtime isolation port allocation (DEC-27): Vite SPA 14030, Laravel API 18030 (keduanya bebas, tidak bentrok).
Hasil          : PASS
Commit baru    : dce7cdd ("docs: rencana integrasi i18n dan branding")
File disentuh  : reports/parallel/integration/INTEGRATION-PLAN.md
Blocker        : tidak ada
Risiko baru    : tidak ada
Permintaan ke pemilik :
1. Menyiapkan salinan .env untuk laravel-api dan vue-app di ../school-integration bila diperlukan untuk uji runtime.
2. Izin lanjut ke Fase 2 (Merge feature/i18n). (Telah disetujui pemilik)
Bukti          : tidak ada

---

## Gerbang G2 — 2026-11-04
SHA diuji      : b26ff4dbb0ea97669d0d34aa9f8602b9347d4fa1
Perintah+hasil :
- git rev-parse feature/i18n: terverifikasi sama persis dengan $A_SHA (c8a88507e484e0c8f336a08eb0d9953cddad3683).
- git merge --no-ff feature/i18n -m "integrate: merge feature/i18n": sukses bersih tanpa konflik (115 files changed, +11946/-2981).
- package-lock.json regenerasi (7.2): checkout dari origin/main, npm install; verifikasi diff terhadap origin/main membuktikan hanya menambah vue-i18n (^11.4.18) dan dependensinya (@intlify/*). Tidak ada paket lain yang bergeser.
- npm run build di vue-app: sukses (built in 38.15s, 0 errors, 0 warnings).
- Unit tests: 9 test suites di vue-app/tests/ (labels, params, auth, ability) seluruhnya lulus (100% pass).
Hasil          : PASS
Commit baru    : b26ff4d ("integrate: merge feature/i18n")
File disentuh  : 115 files (seluruh berkas i18n dan laporan dari worktree A)
Blocker        : tidak ada
Risiko baru    : tidak ada
Permintaan ke pemilik : Izin lanjut ke Fase 3 (Merge feature/branding). (Telah disetujui pemilik)
Bukti          : tidak ada

---

## Gerbang G3 — 2026-11-04
SHA diuji      : c8d67417eef9f270a6c6ecce0bdf51a7051dfeb7
Perintah+hasil :
- git rev-parse feature/branding: terverifikasi sama persis dengan $B_SHA (5e28ecf5991a172cb418c9585f4c1ef0fff2b213).
- git merge --no-ff feature/branding -m "integrate: merge feature/branding": sukses otomatis (19 files changed, +604/-204).
- Resolusi & verifikasi 8.1:
  - vue-app/src/main.js: impor i18n tepat setelah router, app.use(i18n) tepat setelah Notifications, preset SekolarioPreset dan app.use(PrimeVue) dari B dipertahankan utuh.
  - vue-app/index.html: atribut lang="id" dari A, judul "Sekolario", favicon, dan Plus Jakarta Sans woff2 dari B utuh.
  - vue-app/src/layout/AppTopbar.vue: teks tombol (notifications, messages, profile) dari A, layout-topbar-logo dengan AppLogoDefault dari B (teks usang "Administration Apps" dibersihkan).
- Pemeriksaan grep 8.1: seluruh 7 baris wajib menghasilkan output yang tepat.
- Pemeriksaan konflik: 0 penanda konflik tersisa di seluruh vue-app.
- Pemeriksaan berkas hilang/liar 8.2: 0 berkas hilang dari union A & B; berkas tambahan di integrasi hanya rencana dan laporan.
- npm run build di vue-app: sukses (built in 26.07s, 0 errors, 0 warnings; font Plus Jakarta Sans woff2 ter-bundle).
- Unit tests: 9 test suites di vue-app/tests/ seluruhnya lulus (100% pass).
Hasil          : PASS
Commit baru    :
- 0064371 ("integrate: merge feature/branding")
- c8d6741 ("integrate: bersihkan label lama di topbar logo")
File disentuh  : 19 files dari feature/branding + AppTopbar.vue
Blocker        : tidak ada
Risiko baru    : tidak ada
Permintaan ke pemilik : Izin lanjut ke Fase 4 (Lima item yang ditunda, dimulai dari item 9.1).
Bukti          : tidak ada


---

## Gerbang G4 — 2026-11-04
SHA diuji      : 57b699c279cbe878ce6c8e317c805ebdf596cb92
Perintah+hasil :
- 9.1 Audit severity="error": 99 kemunculan diperiksa; 62 `<Message severity="error">` (valid, dipertahankan), 37 `toast.add({ severity: 'error' })` (valid, dipertahankan). 0 `<Tag>` atau `<Button>` dengan `severity="error"` (semua sudah memakai `danger`). Tidak ada kode yang melanggar.
- 9.2 Penyesuaian warna grafik (DESIGN.md 3.4):
  - `TeachingComplianceChartWidget.vue`: `--sk-chart-1` (#059669) dan `--sk-chart-2` (#0ea5e9).
  - `StudentDistributionChartWidget.vue`: `--sk-gender-l` (#0ea5e9) dan `--sk-gender-p` (#ec4899).
  - `DemographicsTab.vue`: gender memakai `--sk-gender-l` dan `--sk-gender-p`, `relPalette` memakai urutan seri 1-6 + netral (#059669, #0ea5e9, #f59e0b, #8b5cf6, #f43f5e, #14b8a6, #94a3b8).
  - `MultiYearTrendsTab.vue`: seri gender (#0ea5e9, #ec4899) dan seri mutasi/total (#059669, #0ea5e9, #f59e0b).
- 9.3 Diferensiasi ikon menu ganda (AppMenu.vue):
  - `studentMajors`: `pi-calendar` (ganda dengan tahun ajaran) diganti menjadi `pi-compass` (bidang minat/jurusan).
  - `educationLevels`: `pi-chart-bar` (ganda dengan laporan) diganti menjadi `pi-sort-amount-up` (jenjang bertingkat).
  - `phaseStatuses`: `pi-building` (ganda konsep dengan sekolah) diganti menjadi `pi-flag` (status fase).
  - `teachers`: `pi-slack` (ikon pihak ketiga yang tidak relevan) diganti menjadi `pi-id-card` (kartu guru).
  - `schoolRombels`: `pi-book` (ganda dengan jurnal mengajar) diganti menjadi `pi-table` (daftar kelas/rombel).
- 9.4 Halaman login, reset & forgot password (auth):
  - Kunci terjemahan `auth.json` (id dan en) dibuat dengan keselarasan 100% (59 kunci sejajar, 0 selisih).
  - Seluruh teks login, reset, dan forgot password memakai `$t` / `t` tanpa teks hardcode.
  - Komponen `FloatingConfigurator` disematkan pada `Login_2.vue`, `ForgotPassword.vue`, dan `ResetPassword.vue` sehingga pemilih bahasa dan mode tema dapat diakses sebelum autentikasi.
  - Pilihan bahasa reaktif tanpa reload, tersimpan di localStorage (`app.locale`), bertahan setelah refresh dan setelah masuk ke aplikasi.
- 9.5 Varian tagline logo menurut bahasa:
  - `AppLogoDefault.vue` reaktif terhadap tema dan locale (`useI18n`).
  - Bahasa `id`: `sekolario-horizontal.svg` / `sekolario-horizontal-on-dark.svg` (SISTEM ADMINISTRASI SEKOLAH).
  - Bahasa `en`: `alternatif-tagline/sekolario-horizontal-tagline-singkat.svg` / `alternatif-tagline/sekolario-horizontal-tagline-singkat-on-dark.svg` (SCHOOL MANAGEMENT SYSTEM).
  - Teks `alt="Sekolario"` tetap, tinggi logo topbar tetap 32px (`h-8 w-auto`).
- Verifikasi akhir G4:
  - `npm run build` di `vue-app`: SUKSES (built in 28.49s, 0 errors).
  - Unit tests: 67/67 tests lolos (100% pass).
Hasil          : PASS
Commit baru    :
- 989fe5c ("brand: sesuaikan warna chart ke palet sekolario (item 9.2)")
- b24db5b ("brand: bedakan ikon menu ganda (item 9.3)")
- 65e6164 ("i18n: dan brand: integrasi teks dan logo halaman login (item 9.4)")
- 57b699c ("brand: tagline logo reaktif terhadap bahasa (item 9.5)")
File disentuh  :
- `vue-app/src/components/dashboard/StudentDistributionChartWidget.vue`
- `vue-app/src/components/dashboard/TeachingComplianceChartWidget.vue`
- `vue-app/src/components/reports/DemographicsTab.vue`
- `vue-app/src/components/reports/MultiYearTrendsTab.vue`
- `vue-app/src/layout/AppMenu.vue`
- `vue-app/src/layout/AppLogoDefault.vue`
- `vue-app/src/views/pages/auth/Login_2.vue`
- `vue-app/src/views/pages/auth/ForgotPassword.vue`
- `vue-app/src/views/pages/auth/ResetPassword.vue`
- `vue-app/src/i18n/locales/id/auth.json`
- `vue-app/src/i18n/locales/en/auth.json`
Blocker        : tidak ada
Risiko baru    : tidak ada
Permintaan ke pemilik : Izin lanjut ke Fase 5 (Validasi otomatis).
Bukti          : tidak ada
