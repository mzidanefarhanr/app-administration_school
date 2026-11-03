# PARALLEL-WORK.md — Aturan kerja paralel: i18n dan branding

Berlaku selama dua pekerjaan berjalan bersamaan pada repo `app-administration_school`. Aturan ini **menambah** `AGENTS.md`, tidak menggantinya. Bila bertentangan, bagian 7 (dokumentasi) adalah satu-satunya pengecualian yang disengaja.

## 1. Peta pekerjaan

| | Worktree A | Worktree B |
|---|---|---|
| Fitur | Terjemahan Indonesia ↔ English | Logo dan desain Sekolario |
| Branch | `feature/i18n` | `feature/branding` |
| Agen | Antigravity | OpenCode |
| Folder | `../school-i18n` | `../school-branding` |
| Acuan | `docs/I18N.md` (dibuat agen A) | `design.md` |

Kedua branch dibuat dari **commit `main` yang sama**. Pemilik mencatat SHA-nya di prompt pembuka.

## 2. Persiapan (pemilik, sekali)

1. Commit file ini dan `design.md` ke `main` **sebelum** membuat worktree, supaya kedua agen membacanya dari repo.
2. Buat worktree:
   ```bash
   git worktree add ../school-i18n     -b feature/i18n     main
   git worktree add ../school-branding -b feature/branding main
   ```
3. Di tiap worktree: `npm ci` (di `vue-app`), `composer install` (di `laravel-api`), dan salin `.env` lokal secara manual (jangan di-commit).
4. Satu branch tidak bisa di-checkout di dua worktree sekaligus. Jangan berpindah branch di dalam worktree.

## 3. Aturan inti

1. Setiap agen **hanya bekerja di worktree dan branch miliknya**.
2. Tidak ada merge, rebase, cherry-pick, atau pull **antar kedua branch**. Penggabungan hanya di integration branch oleh pemilik (bagian 9).
3. Hanya sunting file di daftar **milik sendiri** (bagian 4). File milik pihak lain: jangan diubah, walau hanya satu baris.
4. File bersama diubah **hanya pada baris yang ditentukan** (bagian 4.2).
5. Staging selektif: `git add <path>`. Dilarang `git add .` dan `git add -A`.
6. Commit kecil dan sering, dengan prefiks `i18n:` atau `brand:`.
7. Push hanya ke branch sendiri. Dilarang force-push dan rewrite history. Sebelum push, periksa apakah ada workflow deployment otomatis (`.github/workflows`).
8. Tidak ada migration baru, perubahan skema, atau perubahan kontrak API di kedua branch. Butuh itu → ajukan ke pemilik (bagian 8).
9. Tidak menambah dependensi kecuali yang tertulis di bagian 4 (hanya A: `vue-i18n`).
10. Perubahan di luar scope dicatat, bukan dikerjakan.

## 4. Kepemilikan file

### 4.1 Area eksklusif

| Area | Pemilik | Pihak lain |
|---|---|---|
| `vue-app/src/i18n/**`, `docs/I18N.md` | A | dilarang |
| `vue-app/src/views/**`, `vue-app/src/components/**` | A | dilarang |
| `vue-app/src/layout/AppMenu.vue`, `AppMenuItem.vue`, `AppUsers.vue`, `AppFooter.vue`, `AppConfigurator.vue` | A | dilarang |
| `vue-app/src/router/**` (judul rute) | A | dilarang |
| `vue-app/src/utils/formatters.js`, `referenceLabels.mjs` | A | dilarang |
| `vue-app/package.json`, `package-lock.json` | A (hanya `vue-i18n`) | dilarang |
| `laravel-api/**` | A (hanya berkas bahasa/pesan, dan **hanya jika disetujui**) | dilarang |
| `vue-app/src/assets/**` (styles, layout scss, font) | B | dilarang |
| `vue-app/public/**` (logo, favicon) | B | dilarang |
| `vue-app/src/theme/**` (preset tema baru) | B | dilarang |
| `vue-app/src/layout/AppLogoDefault.vue` | B | dilarang |
| `vue-app/src/layout/composables/layout.js` | B | dilarang |
| `vue-app/tailwind.config.js`, `postcss.config.js` | B | dilarang |
| `design.md`, `docs/DESIGN_SYSTEM.md` | B | A hanya baca |

### 4.2 File bersama (aturan baris)

| File | A boleh | B boleh |
|---|---|---|
| `vue-app/src/main.js` | Tambah `import i18n from '@/i18n'` tepat setelah `import router from './router';`, dan `app.use(i18n);` tepat setelah `app.use(Notifications);` | Ganti `import Aura ...` dan isi blok `app.use(PrimeVue, {...})`. Jangan sentuh baris di atasnya |
| `vue-app/index.html` | Hanya atribut `lang` | Judul, `<link rel="icon">`, font. Jangan ubah `lang` |
| `vue-app/src/layout/AppTopbar.vue` | Teks tombol (Notification, Messages, Profile) | Hanya blok `layout-topbar-logo` (logo dan teks merek). Jangan sentuh teks lain |

Teks merek "Sekolario" adalah nama diri, **tidak diterjemahkan**. A membiarkan literal "Administration Apps" di topbar apa adanya; B yang menggantinya.

## 5. Kontrak antar-pekerjaan

- **B tidak menambah teks tampilan baru** (judul, label, pesan). Hanya aset, token, dan gaya. `alt="Sekolario"` boleh karena nama diri.
- **A tidak mengubah** warna, font, logo, kelas gaya, atau struktur tata letak. Hanya teks dan format locale.
- B menjadikan logo baru lewat **mengganti isi `AppLogoDefault.vue`** (nama file dipertahankan) agar semua pemakainya ikut berubah tanpa menyentuh view milik A.
- Preset tema B berada di berkas sendiri, mis. `src/theme/sekolario-preset.js`; `main.js` hanya mengimpornya.
- Bahasa bawaan `id`. Pilihan bahasa disimpan di sisi klien (mis. `localStorage`), **tanpa kolom database baru**.
- Kunci terjemahan memakai pola `modul.bagian.kunci` (mis. `letters.outgoing.title`). Tidak ada teks Indonesia hardcode baru di view.

### Ditunda ke tahap integrasi (jangan dikerjakan sekarang)

| Item | Alasan | Dikerjakan |
|---|---|---|
| Warna chart (`indigo-400` → slate) | berkas ada di `components/**` milik A | setelah merge |
| Perbaikan ikon menu ganda | `AppMenu.vue` milik A | setelah merge |
| Teks dan logo halaman login (`Login*.vue`) | berkas milik A; teks harus lewat kunci i18n | setelah merge |
| Pilih varian tagline logo menurut bahasa (`School Management System` untuk English) | butuh i18n aktif dan logo siap | setelah merge |
| `severity="error"` pada `Tag` | tersebar di view milik A | setelah merge |

## 6. Isolasi runtime

- Jalankan dev server di **port berbeda** per worktree; periksa DEC-27 lebih dulu agar tidak bentrok dengan port yang dicadangkan.
- Jangan menjalankan `migrate`, `migrate:fresh`, seeder, atau restore terhadap database yang dipakai pemilik. Uji hanya dengan database disposable yang terbukti terisolasi.
- Jangan membuka satu worktree dari proses yang sedang menjalankan worktree lain.

## 7. Dokumentasi dan laporan (pengecualian atas `AGENTS.md` "Penutupan sesi")

`CHANGELOG.md`, `INDEX`, `HANDOFF`, `ROADMAP`, `DECISIONS.md`, `PROJECT_CONTEXT.md` adalah titik bentrok terbesar. Selama kerja paralel:

- **Jangan menyunting** berkas bersama di atas.
- Tulis hasil kerja hanya di folder milik sendiri:
  - A: `reports/parallel/feature-i18n/`
  - B: `reports/parallel/feature-branding/`
- Isi minimal: perintah dan hasil uji, SHA diuji, `NOT RUN`, blocker, risiko, file yang disentuh, dan daftar `REQUESTS.md` (bagian 8).
- Pemilik menggabungkan ke `CHANGELOG`/`ROADMAP` pada tahap integrasi, dalam commit dokumentasi terpisah.
- Jangan mengklaim PASS tanpa bukti. Persetujuan manual pemilik dicatat terpisah dari hasil otomatis.

## 8. Bila harus menyentuh file milik pihak lain

Berhenti. Jangan sunting. Tulis di `reports/parallel/<branch>/REQUESTS.md`:

```
File       : <path>
Alasan     : <mengapa perlu>
Usulan     : <perubahan persis yang diminta>
Mendesak?  : ya/tidak (memblokir pekerjaan?)
```

Pemilik memutuskan: pihak pemilik file mengerjakannya, atau ditunda ke integrasi. Lanjutkan tugas lain sambil menunggu.

## 9. Pemeriksaan sebelum commit

Jalankan dari worktree sendiri. Jika ada keluaran, ada file di luar scope.

**Agen A (i18n):**
```bash
git diff --name-only main...HEAD | grep -E '^(vue-app/(src/(assets|theme)/|public/|tailwind\.config\.js|postcss\.config\.js|src/layout/(AppLogoDefault\.vue|composables/layout\.js))|design\.md|docs/DESIGN_SYSTEM\.md)'
```

**Agen B (branding):**
```bash
git diff --name-only main...HEAD | grep -E '^(vue-app/(src/(views|components|i18n|router|utils)/|src/layout/(AppMenu|AppMenuItem|AppUsers|AppFooter|AppConfigurator)\.vue|package(-lock)?\.json)|laravel-api/|CHANGELOG\.md|docs/I18N\.md)'
```

Tambahan untuk keduanya: `git diff --cached --check`, dan pastikan `.env`, kredensial, dan data pribadi tidak ter-stage.

## 10. Integrasi (pemilik)

1. Pastikan kedua branch sudah di-push dan semua `REQUESTS.md` terjawab.
2. Buat `integration/i18n-branding` dari `main` terbaru.
3. Merge **`feature/i18n` dahulu** (perubahan lebar, hampir semua view), lalu **`feature/branding`** (perubahan sempit, terpusat). Konflik yang diharapkan hanya di `main.js`, `index.html`, `AppTopbar.vue`.
4. `package-lock.json`: jangan digabung manual. Ambil versi dari `main`, lalu jalankan `npm install` agar `vue-i18n` masuk ulang.
5. Kerjakan item "ditunda" di bagian 5, termasuk teks login lewat kunci i18n.
6. Regression: `npm ci`, `npm run build`, `eslint` non-mutasi untuk file berubah, uji tampilan **desktop dan mobile 390 px, terang dan gelap, Indonesia dan English** (cek teks panjang tidak merusak tata letak).
7. Gabungkan laporan ke `CHANGELOG`/`ROADMAP`. Merge ke `main` dan deploy memerlukan instruksi pemilik terpisah.

## 11. Definisi selesai

- **A:** tidak ada teks Indonesia hardcode tersisa di area yang dicakup; semua kunci ada di `id` dan `en`; pindah bahasa tidak memerlukan reload; tanggal dan angka mengikuti locale; build lolos.
- **B:** token primer dan surface sesuai `design.md`; logo, favicon, dan font terpasang untuk terang dan gelap; kontras ≥ 4,5:1; tidak ada teks baru; build lolos.

---

## Lampiran: prompt pembuka

**Antigravity (A):**
> Kamu bekerja di worktree `../school-i18n`, branch `feature/i18n`, dari `main` SHA `<SHA>`. Baca `AGENTS.md`, `docs/DESIGN_SYSTEM.md`, dan `PARALLEL-WORK.md` terlebih dahulu. Tugas: fitur terjemahan Indonesia ↔ English untuk frontend (`vue-i18n`, bahasa bawaan `id`). Patuhi bagian 3–9 `PARALLEL-WORK.md`: hanya file milik A, aturan baris pada `main.js`/`index.html`/`AppTopbar.vue`, tanpa migration, tanpa menyunting CHANGELOG/ROADMAP, laporan di `reports/parallel/feature-i18n/`. Mulai dengan inventaris teks dan rencana kunci (`docs/I18N.md`) dan laporkan sebelum mengimplementasikan.

**OpenCode (B):**
> Kamu bekerja di worktree `../school-branding`, branch `feature/branding`, dari `main` SHA `<SHA>`. Baca `AGENTS.md`, `design.md`, dan `PARALLEL-WORK.md` terlebih dahulu. Tugas: terapkan logo dan desain Sekolario sesuai `design.md` bagian 2–7 dan item 1–4 pada bagian 10. Patuhi bagian 3–9 `PARALLEL-WORK.md`: hanya file milik B, jangan menyentuh `views/`, `components/`, `AppMenu.vue`, `package.json`, dan jangan menambah teks tampilan baru. Logo diterapkan lewat `AppLogoDefault.vue`. Laporan di `reports/parallel/feature-branding/`. Kerjakan satu subfase per instruksi.
