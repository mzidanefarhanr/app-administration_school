# INTEGRATION-PLAN.md — Penggabungan dan validasi `feature/i18n` + `feature/branding`

Versi: 2 (menggantikan versi 1)
Dibuat: 2026-11-04
Pemilik dan pengawas: Muhamad Zidane Farhan Ramadhan
Pelaksana: Antigravity
Dasar: `PARALLEL-WORK.md` bagian 10 (integrasi) dan bagian 11 (definisi selesai). Dokumen ini merinci, tidak menggantikan.
Acuan desain: `DESIGN.md`, satu-satunya.
Branch integrasi: `integration/i18n-branding`, dibuat dari `main` terbaru
Worktree integrasi: `../school-integration`
Lokasi berkas ini di repo: `reports/parallel/integration/INTEGRATION-PLAN.md`

**Status: RENCANA.** Belum ada langkah yang dijalankan. Semua hasil uji berstatus `NOT RUN` sampai ada bukti (perintah, keluaran, SHA yang diuji).

---

## 1. Pembagian peran dan aturan eksekusi

### 1.1 Siapa mengerjakan apa

| Pekerjaan | Pelaksana |
|---|---|
| Pemeriksaan prasyarat, pembuatan tag lokal, worktree, `npm ci`, `composer install` | Antigravity |
| Salin `.env` lokal ke worktree integrasi | **Pemilik** (agen hanya memeriksa berkasnya ada, tidak membuka isinya) |
| Merge, penyelesaian konflik, regenerasi lock | Antigravity |
| Lima item yang ditunda (bagian 9) | Antigravity |
| Validasi otomatis (bagian 10) | Antigravity |
| Validasi di browser pada matriks tema, bahasa, layar (bagian 11) | Antigravity, dengan bukti |
| Menyiapkan commit dokumentasi | Antigravity |
| Meninjau bukti dan laporan tiap gerbang, memberi izin lanjut | **Pemilik** |
| Keputusan DEC-D1 (kontras tombol) | **Pemilik** |
| Persetujuan visual akhir (dicatat terpisah dari hasil otomatis) | **Pemilik** |
| Push tag, merge ke `main`, deploy, bersih-bersih | **Pemilik** |

Hasil validasi Antigravity bukan persetujuan pemilik. Agen yang sama menulis i18n (worktree A) dan menguji hasilnya, sehingga tinjauan pemilik atas bukti adalah pemeriksaan independen satu-satunya.

### 1.2 Aturan eksekusi untuk agen (berlaku di semua fase)

1. **Satu fase per instruksi.** Kerjakan fase yang diminta, berhenti di gerbangnya, tulis laporan gerbang (format di Lampiran B), lalu tunggu izin pemilik.
2. **Commit** kecil dan selektif (`git add <path>`; dilarang `git add .` dan `git add -A`), hanya di `integration/i18n-branding`. **Push** hanya ke branch itu dan hanya setelah pemilik menyetujui laporan gerbang yang bersangkutan. Dilarang force-push dan rewrite history.
3. **Tidak ada** migration, perubahan skema, perubahan kontrak API, dependensi baru (selain yang sudah masuk lewat merge), atau penyuntingan berkas bersama (`CHANGELOG`, `ROADMAP`, `INDEX`, `HANDOFF`, `DECISIONS`, `PROJECT_CONTEXT`) di luar Fase 7.
4. **`DESIGN.md` tidak disunting.** Jika kode bertentangan dengan `DESIGN.md`, `DESIGN.md` yang benar. Jika `DESIGN.md` sendiri bermasalah (mis. kontras tidak memenuhi kriteria), laporkan di laporan gerbang; jangan mengubah warna atau token untuk menyelesaikannya tanpa keputusan pemilik.
5. **Jangan mengklaim PASS tanpa bukti.** Langkah yang tidak bisa dijalankan (mis. tidak ada akses browser, viewport tidak bisa diatur) ditulis `NOT RUN` beserta alasannya. Jangan menebak hasil.
6. **Kredensial.** Akun uji diberikan pemilik lewat sesi. Jangan menulisnya ke berkas, laporan, atau commit.
7. **Data pribadi.** Aplikasi menampilkan data siswa (nama, NIK, NIS, NISN, alamat, email). Tangkapan layar **tidak boleh masuk repo** (repo bersifat publik). Simpan di luar repo, di `../evidence-integration/`, dan di laporan cukup tulis nama berkas. Jangan menyalin data siswa ke laporan.
8. **Database.** Tidak ada `migrate`, `migrate:fresh`, seeder, atau restore terhadap database milik pemilik. Uji di browser bersifat **baca saja**: jangan menekan Simpan, Ya (hapus), atau Impor. Dialog boleh dibuka dan dibatalkan; validasi diuji dengan mengetik. Uji simpan/ubah/hapus hanya jika pemilik menyatakan database disposable secara tertulis.
9. **Di luar scope = catat, bukan kerjakan.** Temuan baru masuk `reports/parallel/integration/REPORT.md`.

---

## 2. Keputusan yang sudah ditetapkan

| Topik | Keputusan | Dampak pada pekerjaan |
|---|---|---|
| Pemilih bahasa di halaman login | **Ada** (sudah dimiliki halaman login) | Item 9.4: verifikasi, bukan membangun baru. Jika tidak ditemukan di kode, laporkan sebagai blocker |
| Acuan desain | **`DESIGN.md` satu-satunya** | Semua pemeriksaan token, warna, tipografi merujuk `DESIGN.md`. `docs/DESIGN_SYSTEM.md` dan dokumen desain lain tidak menjadi acuan (lihat Fase 7) |
| Bahasa bawaan | `id`, pilihan disimpan di sisi klien, tanpa kolom database baru | Dari PARALLEL-WORK bagian 5 |
| Nama merek | "Sekolario" tidak diterjemahkan | Berlaku di semua berkas terjemahan |
| **DEC-D1: warna isi tombol primer tema terang** | **Masih terbuka (pemilik)** | Agen mengukur dan melaporkan (bagian 11.4), tidak memutuskan |

---

## 3. Ringkasan alur

| Fase | Isi | Pelaksana | Gerbang |
|---|---|---|---|
| 0 | Prasyarat dan pengamanan branch A dan B | Antigravity | G0 |
| 1 | Branch dan worktree integrasi, commit rencana ini | Antigravity (+ pemilik: `.env`) | G1 |
| 2 | Merge `feature/i18n` | Antigravity | G2 |
| 3 | Merge `feature/branding` | Antigravity | G3 |
| 4 | Lima item yang ditunda | Antigravity | G4 |
| 5 | Validasi otomatis | Antigravity | G5 |
| 6 | Validasi di browser | Antigravity, ditinjau pemilik | G6 |
| 7 | Dokumentasi (commit terpisah) | Antigravity, ditinjau pemilik | G7 |
| 8 | PR, merge ke `main`, bersih-bersih | Pemilik | instruksi terpisah |

Gerbang gagal berarti berhenti dan lapor, jangan lanjut.

Prefiks commit: `integrate:` untuk merge dan penyelesaian konflik, `i18n:` atau `brand:` untuk item yang ditunda sesuai domainnya, `docs:` untuk dokumentasi.

---

## 4. Konstanta (diisi agen pada Fase 0)

```bash
export MAIN_SHA=<SHA main saat worktree A dan B dibuat, dari prompt pembuka>
export A_SHA=$(git rev-parse feature/i18n)
export B_SHA=$(git rev-parse feature/branding)   # terakhir dilaporkan: 483c606
```

Catat ketiganya di `reports/parallel/integration/REPORT.md`. Seluruh hasil uji merujuk ke SHA hasil integrasi, bukan SHA branch asal.

---

## 5. Fase 0 — Prasyarat

Dijalankan dari folder repo (atau salah satu worktree). Semua pemeriksaan wajib bersih.

### 5.1 Kondisi branch

```bash
# di ../school-i18n
git status --short                      # harus kosong
git fetch origin
git rev-parse HEAD origin/feature/i18n  # kedua SHA harus sama

# di ../school-branding
git status --short
git fetch origin
git rev-parse HEAD origin/feature/branding
```

### 5.2 Cakupan file (PARALLEL-WORK bagian 9)

Keluaran harus kosong. Jika ada isi, ada file di luar scope: jangan digabung, laporkan ke pemilik. Opsi `-i` ditambahkan karena nama berkas desain bisa berbeda huruf besar-kecil (`DESIGN.md` atau `design.md`).

```bash
# di ../school-i18n  (A tidak boleh menyentuh area B)
git diff --name-only main...HEAD | grep -iE '^(vue-app/(src/(assets|theme)/|public/|tailwind\.config\.js|postcss\.config\.js|src/layout/(AppLogoDefault\.vue|composables/layout\.js))|design\.md|docs/DESIGN_SYSTEM\.md)'

# di ../school-branding  (B tidak boleh menyentuh area A)
git diff --name-only main...HEAD | grep -iE '^(vue-app/(src/(views|components|i18n|router|utils)/|src/layout/(AppMenu|AppMenuItem|AppUsers|AppFooter|AppConfigurator)\.vue|package(-lock)?\.json)|laravel-api/|CHANGELOG\.md|docs/I18N\.md)'
```

Di kedua worktree juga:

```bash
git diff main...HEAD --check
git ls-files | grep -E '(^|/)\.env($|\.)'      # harus kosong
```

### 5.3 Lokasi `DESIGN.md`

```bash
git ls-files | grep -iE '(^|/)design\.md$'
```

Catat path dan huruf besar-kecil persisnya di laporan. Jangan mengganti nama berkas.

### 5.4 Permintaan lintas pemilik

Baca `reports/parallel/feature-i18n/REQUESTS.md` dan `reports/parallel/feature-branding/REQUESTS.md`. Daftar setiap entri yang belum terjawab. Pemilik yang menjawab; entri yang ditunda ke integrasi masuk Fase 4.

Jika A menyentuh `laravel-api/**`, pastikan ada persetujuan tertulis pemilik. Jika tidak ada, berhenti.

### 5.5 Titik kembali

```bash
git tag -a pre-integration-main -m "main sebelum integrasi" main
git tag -a ready-i18n     -m "i18n siap integrasi"     $A_SHA
git tag -a ready-branding -m "branding siap integrasi" $B_SHA
```

Tag dibuat lokal. **Push tag dilakukan pemilik** setelah memeriksa `.github/workflows`. Agen menyiapkan datanya:

```bash
ls .github/workflows 2>/dev/null && grep -nE '^\s*(on|push|tags|branches):' .github/workflows/*.yml
```

**G0 lulus bila:** 5.1 bersih, 5.2 kosong untuk kedua branch, 5.3 tercatat, 5.4 tidak ada entri terbuka yang memblokir, tag lokal dibuat, ringkasan workflow dilaporkan.

---

## 6. Fase 1 — Branch dan worktree integrasi

Pemilik lebih dulu menyiapkan `.env` lokal untuk `../school-integration/laravel-api` dan `../school-integration/vue-app` (bila diperlukan), setelah worktree terbentuk. Agen memeriksa keberadaannya saja:

```bash
test -f laravel-api/.env && echo "laravel .env ada"
test -f vue-app/.env.uat  && echo "vue env uat ada"    # sesuaikan nama berkas dengan repo
```

Langkah agen:

```bash
git fetch origin
git worktree add ../school-integration -b integration/i18n-branding origin/main
cd ../school-integration

cd vue-app && npm ci && cd ..
cd laravel-api && composer install && cd ..

mkdir -p reports/parallel/integration
cp <lokasi>/INTEGRATION-PLAN.md reports/parallel/integration/INTEGRATION-PLAN.md
git add reports/parallel/integration/INTEGRATION-PLAN.md
git commit -m "docs: rencana integrasi i18n dan branding"
```

Isolasi runtime: dev server integrasi memakai port yang berbeda dari worktree A, worktree B, dan UAT (8001/4001). Periksa DEC-27 lebih dulu agar tidak bentrok dengan port yang dicadangkan, lalu catat port yang dipilih di laporan.

**G1 lulus bila:** worktree terbuat, `git status` bersih, `npm ci` dan `composer install` berhasil, rencana ini menjadi commit pertama, port tercatat.

---

## 7. Fase 2 — Merge `feature/i18n`

Urutan mengikuti PARALLEL-WORK bagian 10.3: perubahan lebar dulu, sempit kemudian.

```bash
git rev-parse feature/i18n          # harus sama dengan $A_SHA
git merge --no-ff feature/i18n -m "integrate: merge feature/i18n"
```

### 7.1 Hasil yang diharapkan

Merge pertama ke branch yang baru dari `main` seharusnya bersih. Jika ada konflik, berhenti dan lapor: berarti `main` bergerak sejak worktree dibuat. Keputusan menyinkronkan A ada di tangan pemilik.

### 7.2 `package-lock.json`

Jangan gabung manual.

```bash
cd vue-app
git checkout origin/main -- package-lock.json
npm install
git diff origin/main -- package.json          # hanya boleh menambah vue-i18n
git diff --stat origin/main -- package-lock.json
```

Periksa bahwa perubahan di lock hanya `vue-i18n` dan dependensinya (`@intlify/*`). Jika paket lain berubah versi, batalkan dan ulangi dengan `npm ci` sebelum `npm install`.

```bash
cd ..
git add vue-app/package-lock.json
git commit -m "integrate: regenerasi package-lock setelah vue-i18n"
```

### 7.3 Gerbang G2

```bash
cd vue-app && npm run build
```

Tambahan: jalankan dev server dan buka Dashboard, Students, Accounts. Pada konsol browser tidak boleh ada peringatan `[intlify] Not found key`.

**G2 lulus bila:** build lulus, lock hanya menambah vue-i18n, tidak ada peringatan kunci hilang pada tiga halaman itu.

---

## 8. Fase 3 — Merge `feature/branding`

```bash
git rev-parse feature/branding      # harus sama dengan $B_SHA
git merge --no-ff feature/branding -m "integrate: merge feature/branding"
```

### 8.1 Konflik yang diharapkan

Hanya tiga berkas. Aturan penyelesaian:

| Berkas | Hasil akhir wajib |
|---|---|
| `vue-app/src/main.js` | Dari A: `import i18n from '@/i18n'` tepat setelah `import router from './router';`, dan `app.use(i18n);` tepat setelah `app.use(Notifications);`. Dari B: pengganti `import Aura ...` (impor preset Sekolario) dan blok `app.use(PrimeVue, {...})`. Tidak ada impor atau pemanggilan yang hilang dari salah satu sisi |
| `vue-app/index.html` | Atribut `lang` dari A. Judul, `<link rel="icon">`, dan font dari B |
| `vue-app/src/layout/AppTopbar.vue` | Teks tombol (Notification, Messages, Profile) dari A. Blok `layout-topbar-logo` dari B |

Jika konflik muncul di berkas lain, **berhenti dan lapor**; jangan menyelesaikannya sendiri. Sebelum commit hasil resolusi, tulis ringkasan resolusi tiap berkas di `REPORT.md` (sisi mana yang dipertahankan dan alasannya).

Pemeriksaan setelah resolusi (semua harus menghasilkan baris):

```bash
grep -n "import i18n"          vue-app/src/main.js
grep -n "app.use(i18n)"        vue-app/src/main.js
grep -n "app.use(PrimeVue"     vue-app/src/main.js
grep -nE "Sekolario|preset"    vue-app/src/main.js
grep -n "<html lang"           vue-app/index.html
grep -nE "<title>|rel=\"icon\"" vue-app/index.html
grep -n "layout-topbar-logo"   vue-app/src/layout/AppTopbar.vue
```

Tidak boleh tersisa penanda konflik:

```bash
grep -rnE '^(<<<<<<<|=======|>>>>>>>)' vue-app/src vue-app/index.html && echo "MASIH ADA KONFLIK"
```

### 8.2 Tidak ada berkas hilang atau liar

```bash
git diff --name-only main...feature/i18n     >  /tmp/union.txt
git diff --name-only main...feature/branding >> /tmp/union.txt
sort -u /tmp/union.txt -o /tmp/union.txt
git diff --name-only origin/main...HEAD | sort > /tmp/integ.txt

echo "--- hilang dari integrasi (harus kosong) ---"
comm -23 /tmp/union.txt /tmp/integ.txt
echo "--- hanya ada di integrasi (harus hanya rencana, lock, dan hasil resolusi) ---"
comm -13 /tmp/union.txt /tmp/integ.txt
```

### 8.3 Gerbang G3

```bash
cd vue-app && npm run build
```

Uji cepat di dev server: halaman terbuka, tema terang dan gelap berganti, logo topbar tampil 32px, favicon dan judul "Sekolario" benar, tombol topbar tetap berteks.

**G3 lulus bila:** tidak ada penanda konflik, 8.1 dan 8.2 sesuai harapan, build lulus, uji cepat lulus.

---

## 9. Fase 4 — Item yang ditunda

Kelimanya berasal dari PARALLEL-WORK bagian 5 dan baru boleh dikerjakan sekarang karena menyentuh berkas milik A. Satu item per commit, urut dari yang paling mekanis.

| Urut | Item | Prefiks |
|---|---|---|
| 1 | `severity="error"` pada `Tag` dan `Button` | `brand:` |
| 2 | Warna chart indigo menjadi slate | `brand:` |
| 3 | Ikon menu ganda | `brand:` |
| 4 | Teks, logo, dan pemilih bahasa halaman login | `i18n:` dan `brand:` |
| 5 | Varian tagline logo menurut bahasa | `brand:` |

### 9.1 `severity="error"` pada `Tag` dan `Button`

`error` tidak valid untuk `Tag` dan `Button` (nilai valid: `danger`), tetapi **valid dan harus dipertahankan** untuk `Toast` dan `Message`. Jangan ganti semua kemunculan secara otomatis.

```bash
grep -rnE "severity=\"error\"|:severity=\"['\"]error|severity: 'error'|return 'error'" vue-app/src --include=*.vue --include=*.js
```

Klasifikasikan tiap hasil dan tulis tabel klasifikasinya di laporan:

| Konteks | Tindakan |
|---|---|
| `<Tag>`, `<Button>`, atau fungsi `getSeverity` yang hasilnya dipasang ke `Tag`/`Button` | Ubah ke `danger` |
| `toast.add({ severity: 'error' })`, `<Message severity="error">` | Biarkan |

Verifikasi nilai valid terhadap versi PrimeVue yang terpasang sebelum mengubah.
**Penerimaan:** tidak ada `Tag` atau `Button` dengan severity `error`; toast kesalahan tetap ada dan tidak berubah.

### 9.2 Warna chart (indigo → slate)

```bash
grep -rniE "indigo" vue-app/src
```

Ganti dengan token yang ditetapkan `DESIGN.md`. Untuk seri data, ikuti urutan palet grafik di `DESIGN.md`, bukan menulis ulang `rgba(...)` per halaman. Jika `DESIGN.md` tidak mendefinisikan palet grafik, laporkan sebagai permintaan keputusan dan hentikan item ini.
**Penerimaan:** grafik terbaca di tema terang dan gelap; kategori yang berpasangan (mis. laki-laki/perempuan) tetap punya label teks, tidak hanya warna.

### 9.3 Ikon menu ganda

Berkas: `vue-app/src/layout/AppMenu.vue`. Cari item bersaudara (dalam satu grup) yang memakai ikon yang sama, lalu beri ikon yang berbeda dan bermakna.
**Penerimaan:** tidak ada dua item sebaris dengan ikon identik; semua kelas `pi pi-*` yang dipakai ada di versi PrimeIcons yang terpasang. Tulis daftar sebelum dan sesudah di laporan.

### 9.4 Halaman login

Berkas: `vue-app/src/views/**/Login*.vue` (nama pasti sesuai repo).

- Semua teks lewat kunci terjemahan (pola `modul.bagian.kunci`, mis. `auth.login.title`), tidak ada teks hardcode.
- Logo memakai komponen logo yang sudah diganti B (`AppLogoDefault.vue`), bukan berkas gambar lama.
- **Pemilih bahasa pada halaman login sudah ada (keputusan pemilik): verifikasi, jangan membangun baru.** Periksa bahwa: (a) pemilih tampil sebelum autentikasi, (b) mengganti bahasa langsung mengganti semua teks login tanpa reload, (c) pilihan bertahan setelah refresh, (d) pilihan tetap berlaku setelah masuk ke aplikasi, (e) label aksesibilitas pemilih memakai kunci terjemahan, sedangkan nama bahasa (Indonesia, English) ditampilkan dalam bahasanya sendiri dan tidak berubah saat bahasa diganti.
- Jika pemilih tidak ditemukan di kode, laporkan sebagai blocker. Jangan membangun fitur baru di fase ini.

**Penerimaan:** login tampil benar di terang/gelap dan Indonesia/English; tidak ada peringatan kunci hilang; butir (a) sampai (e) terbukti.

### 9.5 Tagline logo menurut bahasa

Logo memiliki tagline yang tertanam di berkas SVG. Tagline harus mengikuti bahasa.

| Bahasa | Tagline | Berkas terang | Berkas gelap |
|---|---|---|---|
| `id` | SISTEM ADMINISTRASI SEKOLAH | `sekolario-horizontal.svg` | `sekolario-horizontal-on-dark.svg` |
| `en` | SCHOOL MANAGEMENT SYSTEM | `alternatif-tagline/sekolario-horizontal-tagline-singkat.svg` | `alternatif-tagline/sekolario-horizontal-tagline-singkat-on-dark.svg` |

Periksa dulu bahwa keempat berkas ada di `vue-app/public/` (hasil kerja B). Jika varian English belum ada, laporkan; menyalinnya dari paket logo adalah pekerjaan pemilik atau B, bukan keputusan agen.
Ubah `AppLogoDefault.vue` agar memilih berkas dari kombinasi tema dan locale, dengan `alt="Sekolario"` tetap. Logo di halaman login ikut aturan yang sama.
**Penerimaan:** mengganti bahasa mengganti tagline tanpa reload (termasuk di halaman login); tinggi logo topbar tetap 32px; "Sekolario" tidak muncul sebagai kunci terjemahan.

**G4 lulus bila:** kelima item selesai, tiap item punya commit sendiri, `npm run build` lulus setelah item terakhir.

---

## 10. Fase 5 — Validasi otomatis

Dari `../school-integration/vue-app`. Catat perintah, keluaran, dan SHA di laporan.

```bash
npm ci
npm run build
```

Lint hanya untuk berkas yang berubah, tanpa mengubah isi berkas (jangan memakai `--fix`; baca isi skrip `lint` di `package.json` karena ada yang memakai `--fix`):

```bash
git diff --name-only origin/main...HEAD -- 'src/**/*.vue' 'src/**/*.js' \
  | sed 's#^vue-app/##' | xargs npx eslint
```

Kesetaraan kunci terjemahan. Sesuaikan path dengan `docs/I18N.md`:

```bash
node -e "
const fs=require('fs');
const load=f=>JSON.parse(fs.readFileSync(f,'utf8'));
const flat=(o,p='')=>Object.entries(o).flatMap(([k,v])=>typeof v==='object'&&v!==null?flat(v,p+k+'.'):[p+k]);
const id=new Set(flat(load('src/i18n/locales/id.json')));
const en=new Set(flat(load('src/i18n/locales/en.json')));
console.log('hanya di id:',[...id].filter(k=>!en.has(k)));
console.log('hanya di en:',[...en].filter(k=>!id.has(k)));
"
```

Sisa teks Indonesia di view (heuristik; hasil perlu dibaca, sebagian bisa berupa nama data yang sah):

```bash
grep -rnE '>[^<>{}]*\b(Simpan|Hapus|Batal|Silakan|Silahkan|Berhasil|Gagal|Tidak ada|Menunggu|Memuat)\b' src/views src/components --include=*.vue
grep -rnE "(summary|detail|header|label|placeholder): *['\"][^'\"]*\b(Simpan|Hapus|Batal|Silakan|Silahkan|Berhasil|Gagal)\b" src --include=*.vue --include=*.js
grep -rn "Administration Apps" src index.html      # nama lama, tidak boleh tersisa
```

Backend: jalankan hanya jika ada perubahan di `laravel-api/**` dan hanya dengan database disposable yang terbukti terisolasi.

```bash
git diff --name-only origin/main...HEAD -- laravel-api/
# bila ada isi:
php artisan test
```

Keamanan commit:

```bash
git diff origin/main...HEAD --check
git ls-files | grep -E '(^|/)\.env($|\.)'                 # kosong
git ls-files | grep -iE '\.(png|jpe?g|webp)$' | grep -i 'evidence'   # kosong: bukti tidak boleh masuk repo
git diff origin/main...HEAD | grep -nEi '(password|secret|token)\s*[:=]\s*[^\s]{6,}'   # periksa manual
```

**G5 lulus bila:** build lulus, lint tanpa kesalahan, kunci `id` dan `en` sejajar, tidak ada teks Indonesia hardcode baru, tidak ada "Administration Apps", tidak ada `.env`, rahasia, atau tangkapan layar ter-commit.

---

## 11. Fase 6 — Validasi di browser (dikerjakan Antigravity)

### 11.1 Persiapan

- Backend untuk uji: server UAT milik pemilik yang sedang berjalan (hanya baca) atau database disposable. Tidak ada operasi tulis (aturan 1.2 butir 8).
- Dev server integrasi dijalankan di port yang dicatat pada Fase 1.
- Akun uji dari pemilik, diberikan di sesi (aturan 1.2 butir 6).
- Folder bukti di luar repo: `../evidence-integration/` (aturan 1.2 butir 7). Penamaan berkas: `<kombinasi>__<halaman>__<langkah>.png`.

### 11.2 Prosedur per kombinasi

1. Atur viewport: desktop (minimal 1366×768) atau mobile 390×844.
2. Atur tema (terang/gelap) lewat kontrol tema aplikasi dan bahasa (Indonesia/English) lewat pemilih bahasa. Catat jika kontrolnya tidak dapat dioperasikan.
3. Buka tiap halaman pada matriks, ambil tangkapan layar, periksa daftar di 11.3.
4. Baca konsol browser: catat setiap peringatan `[intlify]` dan setiap galat.
5. Tulis hasil sel tabel 11.5: `PASS`, `FAIL` (dengan catatan singkat), atau `NOT RUN` (dengan alasan).

### 11.3 Matriks wajib

Tiga halaman kritis diuji di **semua delapan kombinasi**: {desktop, mobile 390 px} × {terang, gelap} × {Indonesia, English}.

| Halaman | Yang diperiksa |
|---|---|
| Login | Logo dan tagline sesuai tema dan bahasa, semua teks, pemilih bahasa (butir 9.4 a sampai e) |
| Students | Semua sub-menu (Students, Genders, Religions, Class Breakdown, Profile Student), tabel, paginator, dialog profil, rekap |
| Accounts | Tabel, dialog tambah/ubah (dibuka lalu dibatalkan), semua pesan validasi, dialog hapus (dibuka lalu dibatalkan), pesan toast yang dapat dipicu tanpa menulis data |

Halaman lain diuji di dua kombinasi saja: **desktop·terang·Indonesia** dan **mobile·gelap·English**: Dashboard, Education Levels, Education Schools, Phase Status, Professions, School Rombels, School Years, Student Majors, School Profile (edit inline dibuka lalu dibatalkan), Profile.

### 11.4 Pemeriksaan khusus

| Area | Pemeriksaan |
|---|---|
| Teks panjang | Teks English biasanya lebih panjang. Periksa kolom label berlebar tetap (mis. `w-[140px]` pada School Profile), tombol toolbar, judul kolom tabel, dan menu samping tidak terpotong atau patah |
| Locale PrimeVue | `DatePicker` (TMT Principal): nama bulan dan hari mengikuti bahasa. Paginator `Showing {first} to {last}...`, placeholder filter, dan teks kosong/memuat DataTable mengikuti bahasa |
| Tanggal dan angka | Mengikuti locale (format tanggal, pemisah ribuan, format Rp) |
| Pindah bahasa | Berpindah tanpa reload; pilihan bertahan setelah refresh dan setelah login |
| Konsol | Tidak ada `[intlify] Not found key`, tidak ada galat |
| Logo dan merek | Logo sesuai tema dan bahasa; judul tab dan favicon "Sekolario"; teks "Sekolario" tidak diterjemahkan |
| Font | Plus Jakarta Sans termuat dari berkas lokal (tab Network: tidak ada permintaan ke `fonts.googleapis.com` untuk font aplikasi). Jika tab Network tidak dapat dibaca, tulis `NOT RUN` |
| Fokus keyboard | Cincin fokus terlihat di tema terang dan gelap (tekan Tab pada Login, tombol utama Students, dan dialog Accounts) |
| Kesesuaian dengan `DESIGN.md` | Token primer dan surface, radius, skala heading, dan menu aktif sesuai `DESIGN.md`. Catat selisih, jangan memperbaiki `DESIGN.md` |

### 11.5 Gerbang kontras (kriteria selesai B: ≥ 4,5:1)

Ambil warna aktual dari browser (`getComputedStyle`) untuk: label tombol primer, tautan, teks item menu aktif, teks `Tag`, teks bantu, placeholder. Hitung rasionya:

```bash
node -e "
const L=h=>{const c=[1,3,5].map(i=>parseInt(h.slice(i,i+2),16)/255).map(v=>v<=0.03928?v/12.92:((v+0.055)/1.055)**2.4);return 0.2126*c[0]+0.7152*c[1]+0.0722*c[2]};
const [x,y]=[L(process.argv[1]),L(process.argv[2])].sort((p,q)=>q-p);
console.log(((x+0.05)/(y+0.05)).toFixed(2));
" '#ffffff' '#059669'
```

Contoh: putih di atas `#059669` menghasilkan 3,77 (tidak memenuhi 4,5:1 untuk teks normal; memenuhi 3:1 untuk teks besar dan elemen grafis). Laporkan tabel: elemen, warna latar, warna teks, rasio, lulus/tidak.

Agen **tidak** mengubah preset untuk menaikkan kontras. Keputusan DEC-D1 ada pada pemilik:

- Opsi A: pertahankan emerald 600 dan catat penyimpangan dari kriteria secara sadar.
- Opsi B: pakai emerald 700 (`#047857`, 5,48 : 1 dengan putih) untuk isi tombol dan tautan teks; logo tetap 600.

### 11.6 Tabel hasil (disalin ke `REPORT.md`)

| Kombinasi | Login | Students | Accounts | Catatan |
|---|---|---|---|---|
| Desktop · Terang · ID | NOT RUN | NOT RUN | NOT RUN | |
| Desktop · Terang · EN | NOT RUN | NOT RUN | NOT RUN | |
| Desktop · Gelap · ID | NOT RUN | NOT RUN | NOT RUN | |
| Desktop · Gelap · EN | NOT RUN | NOT RUN | NOT RUN | |
| Mobile 390 · Terang · ID | NOT RUN | NOT RUN | NOT RUN | |
| Mobile 390 · Terang · EN | NOT RUN | NOT RUN | NOT RUN | |
| Mobile 390 · Gelap · ID | NOT RUN | NOT RUN | NOT RUN | |
| Mobile 390 · Gelap · EN | NOT RUN | NOT RUN | NOT RUN | |

**G6 lulus bila:** semua sel wajib terisi (`PASS`, atau `NOT RUN` dengan alasan yang diterima pemilik), 11.4 dan 11.5 terlaporkan, bukti tersimpan di `../evidence-integration/`, dan pemilik telah meninjau lalu menyetujui. Kegagalan yang tersisa dicatat sebagai risiko yang diterima pemilik secara tertulis.

---

## 12. Definisi selesai (PARALLEL-WORK bagian 11) dan cara memverifikasi

| Kriteria | Cara verifikasi | Fase |
|---|---|---|
| A: tidak ada teks Indonesia hardcode di area cakupan | Pemeriksaan sisa teks (bagian 10) dan pembacaan hasilnya | 5 |
| A: semua kunci ada di `id` dan `en` | Skrip kesetaraan kunci | 5 |
| A: pindah bahasa tanpa reload | 11.4, termasuk di halaman login | 6 |
| A: tanggal dan angka mengikuti locale | 11.4 | 6 |
| A dan B: build lulus | `npm run build` setelah tiap fase | 2 sampai 5 |
| B: token primer dan surface sesuai `DESIGN.md` | Bandingkan preset dan tampilan aktual dengan `DESIGN.md` (11.4) | 6 |
| B: logo, favicon, font untuk terang dan gelap | 11.4 | 6 |
| B: kontras ≥ 4,5:1 | 11.5 dan keputusan DEC-D1 | 6 |
| B: tidak ada teks tampilan baru | Tinjau diff `feature/branding` untuk teks baru | 3 |

---

## 13. Fase 7 dan 8

### Fase 7 — Dokumentasi (commit terpisah, disiapkan Antigravity, ditinjau pemilik)

Berkas bersama baru boleh disunting sekarang, dalam commit `docs:` yang terpisah dari kode:

- `CHANGELOG.md`: satu entri untuk integrasi i18n + branding, merujuk SHA yang diuji dan hasil validasi.
- `ROADMAP`, `INDEX`, `HANDOFF`, `PROJECT_CONTEXT.md`: perbarui status.
- `DECISIONS.md`: catat putusan DEC-D1 (setelah pemilik memutuskan), pemilih bahasa di login, dan `DESIGN.md` sebagai satu-satunya acuan desain. Beri nomor DEC berikutnya yang kosong (DEC-27 sudah dipakai).
- **Konsolidasi acuan desain:** daftar berkas desain selain `DESIGN.md` yang ada di repo (mis. `docs/DESIGN_SYSTEM.md`, dokumen desain lain) beserta apakah isinya bertentangan dengan `DESIGN.md`. Jangan menghapus, mengubah, atau menggabungkannya tanpa instruksi pemilik. Rujukan ke berkas lain sebagai "acuan" di dokumen yang ada dicatat untuk diperbarui.
- Laporan A dan B di `reports/parallel/feature-*/` dibiarkan apa adanya. Hasil integrasi ditulis di `reports/parallel/integration/REPORT.md`.
- Persetujuan visual pemilik dicatat terpisah dari hasil otomatis. Jangan menulis PASS tanpa bukti.

**G7 lulus bila:** dokumen konsisten dengan hasil uji dan SHA, dan pemilik telah meninjaunya.

### Fase 8 — PR, merge, bersih-bersih (pemilik)

Merge ke `main` dan deploy memerlukan instruksi pemilik terpisah. Langkahnya:

1. Pemilik membuka PR `integration/i18n-branding` → `main` dan meninjau seluruh diff satu kali, bukan per commit.
2. Setelah disetujui dan di-merge, beri tag versi (usul: `v0.6.0`).
3. Bersihkan hanya setelah merge terkonfirmasi:

```bash
git worktree list
git worktree remove ../school-i18n
git worktree remove ../school-branding
git worktree remove ../school-integration
git branch -d feature/i18n feature/branding integration/i18n-branding
git worktree prune
```

Tag `ready-i18n`, `ready-branding`, dan `pre-integration-main` dipertahankan sampai tag versi stabil. Folder `../evidence-integration/` dihapus setelah pemilik selesai meninjau, karena memuat data siswa.

---

## 14. Rollback

Sebelum merge ke `main`, branch integrasi bisa dibuang tanpa efek ke yang lain:

```bash
git worktree remove ../school-integration
git branch -D integration/i18n-branding
```

`feature/i18n` dan `feature/branding` tidak berubah, dan tag `ready-*` menunjuk SHA aslinya.

Setelah merge ke `main`, batalkan dengan commit baru, bukan menulis ulang history:

```bash
git revert -m 1 <SHA-merge-ke-main>
```

Force-push dan rewrite history dilarang (PARALLEL-WORK bagian 3).

---

## 15. Risiko

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Urutan `app.use` atau impor di `main.js` salah setelah konflik | Preset tema atau i18n tidak aktif | Pemeriksaan `grep` di 8.1 |
| `package-lock.json` bergeser versi paket lain | Build berbeda dari yang diuji | Ambil lock dari `main`, `npm install`, periksa diff hanya `vue-i18n` |
| Tagline tertanam di SVG tidak ikut bahasa | Logo berbahasa Indonesia di antarmuka English | Item 9.5 |
| Teks English lebih panjang merusak tata letak | Teks terpotong, tombol patah | Pemeriksaan 11.4 pada kolom berlebar tetap dan toolbar |
| Locale PrimeVue tidak ikut berganti | Kalender dan paginator tetap satu bahasa | Pemeriksaan 11.4 |
| Mengganti `error` menjadi `danger` secara membabi buta | Toast dan Message kehilangan severity valid | Klasifikasi di 9.1 |
| Kontras tombol primer tidak lolos | Tidak memenuhi definisi selesai B | Agen melaporkan, pemilik memutuskan DEC-D1 sebelum G6 |
| Tangkapan layar memuat data siswa masuk repo publik | Kebocoran data pribadi | Bukti hanya di `../evidence-integration/`, pemeriksaan di bagian 10 |
| Agen menyimpan atau mengubah data saat uji di browser | Data pemilik berubah | Uji baca saja (aturan 1.2 butir 8) |
| Agen menyetujui hasil kerjanya sendiri | Cacat lolos | Tinjauan pemilik atas bukti di G6 |
| Push memicu workflow deploy | Deploy tidak sengaja | Ringkasan workflow di 5.5; push tag oleh pemilik |
| Agen mengubah `DESIGN.md` agar cocok dengan kode | Acuan desain bergeser diam-diam | Aturan 1.2 butir 4 |

---

## Lampiran A — Prompt untuk Antigravity

Kirim satu prompt per fase. Ganti `<N>` dengan nomor fase.

**Prompt pembuka (tiap sesi):**

> Kamu pelaksana integrasi `feature/i18n` + `feature/branding` untuk repo `app-administration_school`. Kerja di worktree `../school-integration`, branch `integration/i18n-branding` (Fase 0 dan 1 dari folder repo). Baca terlebih dahulu: `AGENTS.md`, `PARALLEL-WORK.md`, `DESIGN.md` (satu-satunya acuan desain), dan `reports/parallel/integration/INTEGRATION-PLAN.md`. Patuhi bagian 1 rencana itu: satu fase per instruksi, commit selektif tanpa `git add .`, push hanya setelah izin pemilik, tanpa migration dan tanpa dependensi baru, tanpa menyunting `DESIGN.md` dan berkas bersama di luar Fase 7, tangkapan layar hanya di `../evidence-integration/` dan tidak pernah di repo, uji browser baca saja, kredensial tidak ditulis ke berkas. Tulis `NOT RUN` bila suatu langkah tidak bisa dijalankan; jangan mengklaim PASS tanpa bukti.
>
> Tugas sesi ini: **kerjakan Fase `<N>` saja**, berhenti di gerbang G`<N>`, dan tulis laporan gerbang sesuai Lampiran B. Jangan lanjut ke fase berikutnya tanpa instruksi.

**Untuk Fase 4**, tambahkan: "Kerjakan item 9.`<M>` saja (satu item per instruksi), satu commit."

---

## Lampiran B — Format laporan gerbang

Ditulis di `reports/parallel/integration/REPORT.md`, satu bagian per gerbang:

```
## Gerbang G<N> — <tanggal>
SHA diuji      : <SHA HEAD integrasi>
Perintah+hasil : <ringkas; keluaran penting saja, tanpa data siswa dan tanpa kredensial>
Hasil          : PASS | FAIL | NOT RUN (alasan)
Commit baru    : <SHA + subjek>
File disentuh  : <daftar>
Blocker        : <bila ada>
Risiko baru    : <bila ada>
Permintaan ke pemilik : <keputusan atau izin yang dibutuhkan>
Bukti          : <nama berkas di ../evidence-integration/, bila ada>
```
