# design.md — Sekolario

Panduan desain antarmuka Sekolario (Sistem Administrasi Sekolah). Disusun dari **logo resmi v1.0.0** (`sekolario-logo/PANDUAN-LOGO.md`) dan **kondisi aktual repo** `app-administration_school` (Vue 3 + PrimeVue 4 Aura + Tailwind 3).

Dokumen ini menggantikan klausa "token warna/font final belum ditetapkan pemilik" di `docs/DESIGN_SYSTEM.md`. Aturan pola layar, state wajib, dan aksesibilitas di dokumen itu tetap berlaku dan tidak diulang di sini.

---

## 1. Karakter merek

| Aspek | Keputusan |
|---|---|
| Kepribadian | Tenang, rapi, tepercaya. Alat kerja operator sekolah, bukan produk konsumen yang ramai. |
| Prinsip visual | Minimal. Satu warna aksen (hijau), sisanya netral. Warna dipakai untuk makna, bukan hiasan. |
| Bentuk | Sudut membulat, batang horizontal, tanpa bayangan berat. Mengikuti ikon: tiga batang bergeser yang membentuk huruf "S". |
| Bahasa | Bahasa Indonesia baku pada seluruh alur operator. |

Makna ikon: tiga batang = **data, dokumen, alur kerja**. Makna ini boleh dipakai ulang sebagai motif ilustrasi (misalnya empty state), tetapi jangan menambah elemen lain ke ikon.

---

## 2. Logo

### 2.1 Pilihan file

| Konteks | File |
|---|---|
| Header app, mode terang | `sekolario-horizontal.svg` |
| Header app, mode gelap | `sekolario-horizontal-on-dark.svg` |
| Sidebar tertutup / ikon saja | `sekolario-icon.svg` |
| Favicon | `sekolario-favicon.svg` (+ PNG 32, 180, 512) |
| Kop surat / dokumen cetak 1 warna | `sekolario-mono-dark.svg` |
| Watermark / latar gelap monokrom | `sekolario-mono-white.svg` |
| Varian tagline Inggris | `alternatif-tagline/*` (opsional) |

Salin ke `vue-app/public/logo/` agar jalur di panduan logo (`/logo/...`) berlaku.

### 2.2 Aturan pakai

- Clear space minimal setara tinggi ikon di semua sisi.
- Lebar minimum: horizontal 160 px, ikon 20 px. Untuk 16 px pakai favicon, bukan ikon polos.
- Hanya di atas latar polos (putih, gelap, atau hijau primer). Di atas foto wajib overlay.
- Dilarang: ganti warna di luar palet, regangkan, beri bayangan/bevel, rotasi, atau pakai versi terang di latar gelap (dan sebaliknya).
- Wordmark di SVG masih `<text>` yang memuat Google Fonts. Untuk cetak, ekspor, atau penggunaan offline, **ubah ke path** dahulu (Figma: Text → Outline; Inkscape: Object to Path).

---

## 3. Warna

### 3.1 Palet merek (dari logo)

| Peran | Nama | Hex |
|---|---|---|
| Primer | Emerald 600 | `#059669` |
| Primer terang | Emerald 500 | `#10b981` |
| Primer muda (aksen di latar gelap) | Emerald 400 | `#34d399` |
| Tint primer | Emerald 100 | `#d1fae5` |
| Gelap utama | Slate 950 | `#0f172a` |
| Gelap sekunder | Slate 800 | `#1e293b` |
| Teks muted | Slate 500 | `#64748b` |
| Teks sub | Slate 600 | `#475569` |
| Terang | Slate 50 | `#f8fafc` |
| Putih | — | `#ffffff` |

### 3.2 Pemetaan ke token PrimeVue

Repo sudah memakai `primary: 'emerald'` (`layout/composables/layout.js`) dan Aura, jadi hijau merek **sudah sejalan** dengan palet Aura emerald. Yang perlu diselaraskan hanya hal berikut.

| Token | Mode terang | Mode gelap | Catatan |
|---|---|---|---|
| `--p-primary-color` | emerald 600 `#059669` | emerald 400 `#34d399` | Sama dengan logika wordmark: "S" hijau gelap di terang, hijau muda di gelap. |
| `--p-primary-contrast-color` | putih | slate 950 | |
| Hover/active primer | emerald 700 | emerald 300 | |
| Surface (latar/kartu/border) | skala **slate** | skala **slate** | Logo memakai slate. Aura memakai palet surface bawaan sendiri (zinc menurut dokumentasi PrimeVue); karena `layoutConfig.surface` saat ini `null`, nilainya perlu **diperiksa di runtime** lalu diset ke slate bila berbeda. |
| `--surface-ground` | slate 50 `#f8fafc` | slate 950 `#0f172a` | Repo kini memakai `surface-100` / `surface-950`. |
| `--surface-card` | putih | slate 900 | |
| `--text-color` | slate 950 | slate 50 | |
| `--text-color-secondary` | slate 600 | slate 400 | |

Cara menerapkan, selaras `AGENTS.md` dan `DESIGN_SYSTEM.md` (tetap Aura + semantic tokens, tanpa redesign total): gunakan `definePreset(Aura, { semantic: { primary: {...emerald}, colorScheme: { light: { primary: { color: '{emerald.600}', ... }, surface: {...slate} }, dark: { primary: { color: '{emerald.400}', ... }, surface: {...slate} } } } })` di `main.js`. Ini satu titik perubahan dan tidak menyentuh komponen.

### 3.3 Warna semantik status

Status **selalu berlabel teks**, tidak hanya warna. Pakai severity PrimeVue yang sudah dominan di repo (`secondary`, `danger`, `warn`, `success`, `info`).

| Severity | Pakai untuk | Contoh di repo |
|---|---|---|
| `success` | selesai, disetujui, aktif, terbit | Approved, Issued, aktif |
| `warn` | menunggu tindakan, mendekati batas | Review, belum diisi (dalam tenggang) |
| `danger` | gagal, ditolak, melewati batas | Ditolak, belum diisi (lewat tenggang) |
| `info` | informasi netral, sedang berjalan | Draft terkirim, proses |
| `secondary` | tidak aktif, arsip, default | Draft, nonaktif |
| `primary` | hanya untuk penekanan merek, jarang | |

Catatan konsistensi: repo memuat `severity="error"` (±62 kali). Nilai itu valid untuk `Message`, tetapi tidak untuk `Tag` (yang memakai `danger`). Periksa tiap pemakaian dan rapikan yang berada pada `Tag` saat menyentuh file terkait. Hijau `success` dan hijau primer berdekatan; jangan menjadikan hijau satu-satunya pembeda antara "aksi utama" dan "status berhasil" (pakai ikon + teks).

### 3.4 Chart

Chart membaca token (`--p-emerald-500`, `--text-color`, `--surface-border`), sudah benar. Aturan:

1. Seri utama: emerald. Seri pembanding: slate 400 atau slate 500.
2. Seri ketiga dan seterusnya dari palet semantik di atas, bukan warna acak.
3. Gantikan `indigo-400` (seri kedua pada `TeachingComplianceChartWidget`) dengan slate agar tidak keluar palet.
4. Perbarui chart saat tema berganti. Grafik kosong tidak boleh diisi data demo.

---

## 4. Tipografi

| Elemen | Aturan |
|---|---|
| Font utama | **Plus Jakarta Sans** |
| Fallback | Inter → system-ui → sans-serif |
| Wordmark | 600, 26 px, letter-spacing −0.5 px |
| Tagline | 400, 8 px, letter-spacing +1.8 px, huruf kapital |
| Angka tabel | `font-variant-numeric: tabular-nums` |

Kondisi repo: `index.html` memuat `assets/css/inter.css` (Inter lokal). Rekomendasi: tambah Plus Jakarta Sans sebagai font utama **dengan berkas lokal** (`@font-face` di `assets/css`), mengikuti pola Inter yang sudah ada. Alasannya, aplikasi ini dipakai di jaringan sekolah yang belum tentu stabil ke Google Fonts, dan Inter tetap tersedia sebagai fallback.

Skala (rem, basis 16 px). Heading bawaan template terlalu besar untuk antarmuka administrasi yang padat (`h1` 2.5 rem, margin 1.5 rem). Untuk layar aplikasi:

| Peran | Ukuran | Weight |
|---|---|---|
| Judul halaman | 1.5 rem | 600 |
| Judul seksi / kartu | 1.125 rem | 600 |
| Isi, tabel, form | 0.875–1 rem | 400 |
| Label form | 0.875 rem | 500 |
| Keterangan, bantu | 0.8125 rem | 400, `--text-color-secondary` |

Heading halaman tidak lagi memakai `h1` 2.5 rem; sesuaikan di `_typography.scss` hanya jika disetujui, karena berdampak ke seluruh halaman.

---

## 5. Bentuk, jarak, elevasi

- **Radius:** kontainer/kartu 12 px (sama dengan kotak ikon di horizontal logo), kontrol (input, tombol) 8 px, status/tag pil penuh. Ikon polos memakai ujung batang bulat penuh.
- **Jarak:** kelipatan 4 px; antarseksi form 24 px; padding kartu 16–24 px.
- **Elevasi:** datar. Pemisahan dengan border `--surface-border`, bukan bayangan. Bayangan hanya untuk overlay (dialog, menu, toast).
- **Fokus:** cincin 2 px primer dengan offset 2 px (sudah ada di `styles.scss`, pertahankan).

---

## 6. Komponen dan layout

### 6.1 Shell aplikasi

- **Topbar:** ikon sidebar + logo. Ganti `AppLogoDefault.vue` (SVG bawaan Sakai/PrimeLand) dan teks "Administration Apps" dengan `sekolario-horizontal*.svg` yang berganti menurut tema, sesuai contoh `AppLayout.vue` di panduan logo. Tinggi logo 32 px (`h-8 w-auto`).
- **Sidebar tertutup:** `sekolario-icon.svg`, 32×32.
- **Judul tab:** `Sekolario` (sekarang `Administration Apps`), `lang="id"` (sekarang `en`).
- **Favicon:** ganti `favicon.ico` dengan set dari `sekolario-favicon.svg`.

### 6.2 Menu

Pertahankan grup dan label bahasa Indonesia yang sudah ada di `AppMenu.vue`. Aturan ikon: satu ikon PrimeIcons per item, ukuran seragam, tanpa kelas `rotated-icon` (hiasan tanpa makna). Beberapa ikon dipakai ganda untuk makna berbeda (`pi-calendar` untuk Tahun Ajaran dan Jurusan Siswa, `pi-book` untuk Rombel dan Jurnal); bedakan saat menyentuh menu. Item aktif: latar tint primer (emerald 100 di terang, emerald 400 dengan opasitas rendah di gelap), teks primer.

### 6.3 Tombol

| Jenis | Pakai |
|---|---|
| Primer (isi hijau) | Satu per layar/dialog, aksi utama (Simpan, Ajukan) |
| Sekunder (outlined) | Aksi pendukung |
| Teks | Aksi tersier, aksi baris tabel |
| `danger` | Aksi merusak; selalu dengan konfirmasi yang menyebut objek dan efek |

Label tindakan tepat: gunakan "Arsipkan" bila perilakunya soft delete, jangan "Hapus". Pada ponsel, target sentuh minimal 40 px (sudah ada).

### 6.4 Tabel, form, dialog

Ikuti pola di `docs/DESIGN_SYSTEM.md` (judul, tahun ajaran relevan, filter, aksi berizin, tabel, pagination server; form dengan grouping dan error per field; konfirmasi modal PrimeVue). Tambahan visual:

- Header tabel: latar `--surface-ground`, teks 600, tanpa huruf kapital semua.
- Baris hover: `--surface-hover`. Zebra tidak dipakai.
- Tanggal dan angka lewat `utils/formatters.js` (DD/MM/YYYY, WIB), jangan format ad hoc.
- Tabel lebar scroll di dalam kontainer; body tidak overflow horizontal.

### 6.5 Empty, loading, error

Gunakan motif tiga batang (§1) sebagai ilustrasi empty state: tiga batang abu-abu slate 200 yang bergeser, dengan satu kalimat penjelas dan satu aksi. Skeleton untuk loading tabel/kartu. Pesan error menyebut apa yang gagal dan langkah berikutnya.

### 6.6 Halaman login

Rute `/auth/login` perlu memakai logo horizontal Sekolario (atau ikon saja) di tengah kartu dan teks Indonesia ("Masuk ke Sekolario", "Email", "Kata sandi", "Lupa kata sandi?"). Draf `Login.vue` di repo masih berisi teks "Welcome to PrimeLand!" dan logo Sakai, serta `Login_2.vue` memakai `AppLogoDefault`. Pastikan komponen yang benar-benar dirutekan diperiksa dahulu, lalu bersihkan.

---

## 7. Mode gelap

- Selektor tetap `.app-dark` (`darkModeSelector`). Logo berganti ke `-on-dark`.
- Aksen primer naik ke emerald 400; jangan memakai emerald 600 di atas slate 900 untuk teks kecil (kontras kurang).
- Target kontras ≥ 4,5:1 untuk teks isi (DEC-47 D6). Verifikasi: teks muted slate 500 `#64748b` di atas putih sekitar 4,76:1 (lolos tipis); di atas slate 950 perlu naik ke slate 400.
- Tidak ada putih/hitam hardcode; gunakan token surface/text.

---

## 8. Dokumen dan cetak

Template PDF surat (`resources/views/letters/pdf/partials/styles.blade.php`) memakai DejaVu Sans, teks `#111111`, dan **bukan** merek Sekolario. Itu benar untuk surat dinas karena kop memakai identitas **sekolah** (logo sekolah dari `letter_settings`), bukan logo produk.

- Jangan menaruh logo Sekolario di kop surat atau di badan surat dinas.
- Logo Sekolario hanya pantas muncul kecil di footer ekspor non-surat (rekap, laporan), memakai `sekolario-mono-dark.svg`, bila pemilik menghendaki.
- Font tetap DejaVu Sans agar glyph aman di Dompdf. Jangan menyematkan Plus Jakarta Sans ke PDF tanpa menguji.

---

## 9. Aksesibilitas

Mewarisi `docs/DESIGN_SYSTEM.md`, ditambah:

- Kontras ≥ 4,5:1 (teks) dan ≥ 3:1 (komponen UI, ikon bermakna) pada kedua tema.
- Status tidak hanya warna (ikon atau teks).
- Fokus terlihat, urutan tab logis, fokus kembali ke pemicu setelah dialog ditutup.
- Logo di header: `alt="Sekolario"`; ikon dekoratif: `alt=""`.

---

## 10. Daftar penyelarasan yang tersisa di repo

Berdasarkan pembacaan source (bukan hasil jalan di browser; semuanya `NOT RUN`):

| # | Temuan | Berkas | Prioritas |
|---|---|---|---|
| 1 | Logo masih bawaan Sakai, teks "Administration Apps" | `layout/AppLogoDefault.vue`, `layout/AppTopbar.vue` | Tinggi |
| 2 | Judul tab dan favicon belum Sekolario; `lang="en"` | `index.html`, `public/favicon.ico` | Tinggi |
| 3 | Preset tema belum dikustom (primer dan surface bawaan Aura) | `src/main.js`, `layout/composables/layout.js` | Tinggi |
| 4 | Font Plus Jakarta Sans belum ada | `assets/css/`, `index.html` | Sedang |
| 5 | Halaman login masih teks Inggris/PrimeLand | `views/pages/auth/Login*.vue` | Sedang |
| 6 | `severity="error"` perlu diperiksa pada komponen `Tag` | banyak view | Sedang |
| 7 | Warna chart seri kedua `indigo-400` di luar palet | `components/dashboard/*` | Rendah |
| 8 | Skala heading template terlalu besar | `assets/layout/_typography.scss` | Rendah |
| 9 | Ikon ganda dan `rotated-icon` di menu | `layout/AppMenu.vue` | Rendah |

Sesuai `AGENTS.md`: kerjakan **satu subfase** per instruksi pemilik, perubahan skema/API tidak diperlukan, dan hasil perlu bukti visual desktop dan mobile (390 px), terang dan gelap, sebelum dinyatakan lulus.

---

## 11. Sumber

- Logo: `sekolario-logo/PANDUAN-LOGO.md` dan 8 berkas SVG (v1.0.0, Oktober 2026).
- Repo: `vue-app/src/main.js`, `layout/composables/layout.js`, `layout/AppMenu.vue`, `layout/AppTopbar.vue`, `assets/styles.scss`, `assets/layout/variables/_common.scss`, `utils/formatters.js`, `docs/DESIGN_SYSTEM.md`, `docs/DECISIONS.md` (DEC-47), `AGENTS.md`.
