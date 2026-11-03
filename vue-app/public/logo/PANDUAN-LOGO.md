# Panduan Logo Sekolario

Versi: 1.0.0  
Tanggal: Oktober 2026  
Desainer: Muhamad Zidane Farhan Ramadhan, S.Kom.

---

## Daftar File

```
sekolario-logo/
├── PANDUAN-LOGO.md                              ← dokumen ini
├── sekolario-icon.svg                           ← ikon saja, latar transparan
├── sekolario-favicon.svg                        ← ikon dalam kotak, untuk favicon
├── sekolario-horizontal.svg                     ← logo utama, latar terang/transparan
├── sekolario-horizontal-on-dark.svg             ← logo utama, latar gelap
├── sekolario-mono-dark.svg                      ← monokrom gelap (cetak hitam putih)
├── sekolario-mono-white.svg                     ← monokrom putih (di atas bg gelap)
└── alternatif-tagline/
    ├── sekolario-horizontal-tagline-singkat.svg          ← tagline "SCHOOL MANAGEMENT SYSTEM"
    └── sekolario-horizontal-tagline-singkat-on-dark.svg  ← idem, latar gelap
```

---

## Filosofi & Konsep

**Nama:** Sekolario  
**Tagline utama:** Sistem Administrasi Sekolah  
**Tagline alternatif:** School Management System

**Ikon mark:** Tiga batang horizontal yang bergeser ke kiri, membentuk ritme huruf "S". Sekaligus menyiratkan tiga hal inti produk: **data, dokumen, dan alur kerja** administrasi sekolah. Sederhana, scalable, dan mudah dikenali bahkan di ukuran terkecil (16×16px).

**Wordmark:** Huruf "S" pertama berwarna hijau primer sebagai pembeda visual. Sisa karakter berwarna netral gelap. Ini cara paling minimal untuk memberi karakter tanpa kebisingan visual.

---

## Warna

| Peran           | Nama             | Hex       |
|-----------------|------------------|-----------|
| Primer          | Emerald 600      | `#059669` |
| Primer terang   | Emerald 500      | `#10b981` |
| Primer muda     | Emerald 400      | `#34d399` |
| Tint primer     | Emerald 100      | `#d1fae5` |
| Gelap utama     | Slate 950        | `#0f172a` |
| Gelap sekunder  | Slate 800        | `#1e293b` |
| Teks muted      | Slate 500        | `#64748b` |
| Teks sub        | Slate 600        | `#475569` |
| Terang          | Slate 50         | `#f8fafc` |
| Putih           | —                | `#ffffff` |

---

## Tipografi

**Font utama:** [Plus Jakarta Sans](https://fonts.google.com/specimen/Plus+Jakarta+Sans)  
**Fallback:** Inter → system-ui → sans-serif

| Elemen    | Weight | Size  | Letter-spacing |
|-----------|--------|-------|----------------|
| Wordmark  | 600    | 26px  | −0.5px         |
| Tagline   | 400    | 8px   | +1.8px         |

> **Catatan penting:** File SVG saat ini menggunakan elemen `<text>` dengan referensi Google Fonts. Untuk penggunaan yang sepenuhnya mandiri (offline, cetak, ekspor), konversi wordmark ke path terlebih dahulu menggunakan **Figma** (Text → Outline) atau **Inkscape** (Path → Object to Path).

---

## Panduan Penggunaan

### ✅ Dibolehkan

- Menggunakan logo pada latar polos (putih, gelap, hijau primer)
- Mengubah ukuran proporsional
- Menggunakan versi monokrom untuk cetak 1 warna
- Menambah clear space minimal setara tinggi ikon di semua sisi

### ❌ Dilarang

- Mengubah warna di luar palet resmi
- Meregangkan atau mendistorsi proporsi
- Menambah efek bayangan, bevel, atau emboss
- Meletakkan logo di atas foto atau latar ramai tanpa overlay
- Merotasi atau memiringkan logo
- Menggunakan versi latar terang di atas latar gelap (dan sebaliknya)

---

## Panduan Penggunaan per Konteks

| Konteks                        | File yang dipakai                          |
|--------------------------------|--------------------------------------------|
| Header app (light mode)        | `sekolario-horizontal.svg`                 |
| Header app (dark mode)         | `sekolario-horizontal-on-dark.svg`         |
| Favicon browser (SVG)          | `sekolario-favicon.svg`                    |
| Favicon 32×32 PNG              | `favicon-32.png` *(lihat cara buat di bawah)* |
| Favicon 180×180 PNG (Apple)    | `favicon-180.png` *(lihat cara buat di bawah)* |
| Favicon 512×512 PNG (PWA)      | `favicon-512.png` *(lihat cara buat di bawah)* |
| Kop surat / dokumen resmi      | `sekolario-mono-dark.svg`                  |
| Stempel / watermark gelap      | `sekolario-mono-white.svg`                 |
| Sidebar app (ikon saja)        | `sekolario-icon.svg`                       |
| Kartu nama / merchandise       | `sekolario-horizontal.svg` atau mono       |

---

## Cara Membuat File PNG (favicon)

SVG dapat dikonversi ke PNG menggunakan salah satu cara berikut:

**Cara 1 — Figma (gratis, rekomendasi):**
1. Import `sekolario-favicon.svg` ke Figma
2. Pilih frame → Export → PNG
3. Set ukuran: 32, 180, 512px

**Cara 2 — Inkscape (offline, gratis):**
```bash
inkscape sekolario-favicon.svg --export-png=favicon-32.png  --export-width=32
inkscape sekolario-favicon.svg --export-png=favicon-180.png --export-width=180
inkscape sekolario-favicon.svg --export-png=favicon-512.png --export-width=512
```

**Cara 3 — Squoosh atau realfavicongenerator.net (browser):**
Upload `sekolario-favicon.svg`, download semua ukuran PNG sekaligus.

---

## Integrasi di Aplikasi Vue (Sekolario App)

### Favicon di `index.html`
```html
<link rel="icon"             type="image/svg+xml" href="/logo/sekolario-favicon.svg">
<link rel="icon"             type="image/png" sizes="32x32"  href="/logo/favicon-32.png">
<link rel="apple-touch-icon"                  sizes="180x180" href="/logo/favicon-180.png">
```

### Header sidebar
```vue
<!-- AppLayout.vue -->
<img
  :src="isDark ? '/logo/sekolario-horizontal-on-dark.svg' : '/logo/sekolario-horizontal.svg'"
  alt="Sekolario"
  class="h-8 w-auto"
/>
```

### Ikon saja (collapsed sidebar)
```vue
<img src="/logo/sekolario-icon.svg" alt="Sekolario" class="h-8 w-8" />
```

---

## Ukuran Minimum

| Format     | Ukuran minimum |
|------------|---------------|
| Horizontal | Lebar 160px   |
| Ikon saja  | 20×20px       |
| Favicon    | 16×16px (gunakan `sekolario-favicon.svg`, bukan `sekolario-icon.svg`) |

---

## Catatan Hak Cipta

Logo Sekolario adalah karya orisinal milik **Muhamad Zidane Farhan Ramadhan, S.Kom.**  
Tidak boleh digunakan untuk produk atau lembaga lain tanpa izin tertulis.
