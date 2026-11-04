# Desain Sekolario

Dokumen ini menjadi acuan visual untuk antarmuka Sekolario (Vue 3 + PrimeVue/Sakai). Semua nilai diturunkan dari logo: ikon hijau `#059669`, wordmark gelap `#0f172a`, versi gelap memakai `#34d399`, dan tipografi Plus Jakarta Sans.

Status: nilai preset di bagian 9 adalah referensi. Cocokkan dengan `SekolarioPreset` yang sudah ada di branch `feature/branding`, lalu perbarui dokumen ini bila ada selisih.

---

## 1. Prinsip

1. **Tenang dan padat data.** Aplikasi ini dipakai operator berjam-jam untuk tabel, formulir, dan rekap. Latar netral, satu warna merek, tanpa dekorasi.
2. **Warna menyampaikan makna.** Hijau merek menandai aksi utama dan item aktif. Status (berhasil, peringatan, bahaya, info) memakai warna semantik, bukan hijau merek.
3. **Satu aksen per layar.** Satu tombol utama per area. Aksi lain memakai gaya sekunder atau outline.
4. **Teks selalu menyertai warna.** Jangan membedakan data hanya lewat warna (contoh: laki-laki/perempuan di grafik wajib punya label).
5. **Konsisten antar halaman.** Pola yang sama (header halaman, toolbar, tabel, dialog) dipakai di semua menu, termasuk yang dibuat lewat `useResourceManager`.

---

## 2. Logo

| Konteks | File |
|---|---|
| Topbar/sidebar, tema terang | `sekolario-horizontal.svg` |
| Topbar/sidebar, tema gelap | `sekolario-horizontal-on-dark.svg` |
| Sidebar menyempit, avatar aplikasi | `sekolario-icon.svg` |
| Favicon | `sekolario-favicon.svg` (+ PNG 32/180/512) |
| Cetak satu warna, kop dokumen | `sekolario-mono-dark.svg` |
| Di atas latar gelap/foto dengan overlay | `sekolario-mono-white.svg` |

- Tinggi logo di topbar: **32px**.
- Ukuran minimum: horizontal lebar 160px, ikon 20px.
- Ruang bebas: minimal setinggi ikon di keempat sisi.
- Dilarang: mengubah warna di luar palet, meregangkan, memberi bayangan, memutar.

---

## 3. Palet warna

### 3.1 Merek (Emerald)

| Token | Hex | Penggunaan |
|---|---|---|
| `primary-50` | `#ecfdf5` | Latar tipis (baris terpilih, banner info merek) |
| `primary-100` | `#d1fae5` | Latar item menu aktif, badge lembut |
| `primary-200` | `#a7f3d0` | Garis fokus lembut, grafik seri pendukung |
| `primary-300` | `#6ee7b7` | Hover pada latar gelap |
| `primary-400` | `#34d399` | **Warna primer tema gelap**, wordmark "S" di latar gelap |
| `primary-500` | `#10b981` | Ikon dan grafik |
| `primary-600` | `#059669` | **Warna logo**, ikon aktif, garis dan elemen grafis |
| `primary-700` | `#047857` | Teks tautan, label tombol bertinta, fill tombol (lihat bagian 4) |
| `primary-800` | `#065f46` | Status tekan (active), teks di atas `primary-100` |
| `primary-900` | `#064e3b` | Teks di atas `primary-50` pada banner |
| `primary-950` | `#022c22` | Teks di atas `primary-400` |

### 3.2 Netral (Slate)

| Token | Hex | Penggunaan |
|---|---|---|
| `surface-0` | `#ffffff` | Kartu, dialog, tabel (terang) |
| `surface-50` | `#f8fafc` | Latar halaman (terang), zebra tabel |
| `surface-100` | `#f1f5f9` | Hover baris, latar input nonaktif |
| `surface-200` | `#e2e8f0` | Border kartu, pemisah, border input |
| `surface-300` | `#cbd5e1` | Border input hover, garis tabel tegas |
| `surface-400` | `#94a3b8` | Placeholder, ikon nonaktif |
| `surface-500` | `#64748b` | Teks sekunder, tagline |
| `surface-600` | `#475569` | Teks sekunder pekat, label |
| `surface-700` | `#334155` | Border pada tema gelap |
| `surface-800` | `#1e293b` | Kartu/dialog (gelap) |
| `surface-900` | `#0f172a` | Latar halaman (gelap), **warna wordmark** |
| `surface-950` | `#020617` | Latar terdalam (gelap), overlay |

Teks utama tema terang: `surface-900`. Teks utama tema gelap: `surface-50`.

### 3.3 Semantik

Hijau merek dipakai untuk "berhasil" karena selaras dengan identitas. Tiga status lain dibedakan jelas dari hijau.

| Status | Fill/ikon | Teks di atas latar putih | Latar lembut | Severity PrimeVue |
|---|---|---|---|---|
| Berhasil | `#059669` | `#047857` | `#d1fae5` | `success` |
| Info | `#0284c7` | `#0369a1` | `#e0f2fe` | `info` |
| Peringatan | `#d97706` | `#b45309` | `#fef3c7` | `warn` |
| Bahaya | `#dc2626` | `#b91c1c` | `#fee2e2` | `danger` |
| Netral | `#64748b` | `#475569` | `#f1f5f9` | `secondary` |

Gunakan hanya kata `danger` (bukan `error`) pada prop `severity` PrimeVue.

### 3.4 Data dan grafik

Urutan seri grafik (ambil dari atas, jangan lompat):

| Urutan | Nama | Hex |
|---|---|---|
| 1 | Emerald 600 | `#059669` |
| 2 | Sky 500 | `#0ea5e9` |
| 3 | Amber 500 | `#f59e0b` |
| 4 | Violet 500 | `#8b5cf6` |
| 5 | Rose 500 | `#f43f5e` |
| 6 | Teal 500 | `#14b8a6` |
| Pendukung | Slate 400 | `#94a3b8` |

Pasangan gender yang dipakai konsisten di grafik dan tabel rekap:

| Kategori | Hex | Catatan |
|---|---|---|
| Laki-laki (L) | `#0ea5e9` | Selalu tampilkan label "L" |
| Perempuan (P) | `#ec4899` | Selalu tampilkan label "P" |

Tag gender di tabel sebaiknya memakai `secondary`, bukan `success`/`info`, supaya hijau dan biru tidak terbaca sebagai status.

Pemetaan status siswa yang disarankan:

| Status | Severity |
|---|---|
| Aktif, Siswa Baru | `success` |
| Mutasi Masuk, Lulus | `info` |
| Mutasi Keluar, Drop Out | `danger` |
| Negeri | `success` |
| Swasta | `secondary` |

---

## 4. Kontras dan aksesibilitas

Nilai berikut dihitung dengan rumus WCAG 2.x.

| Pasangan | Rasio | Hasil |
|---|---|---|
| Putih di atas `#059669` (logo, fill primer) | 3,77 : 1 | Lolos untuk elemen grafis dan teks besar (≥3:1). **Tidak lolos** untuk teks normal (AA 4,5:1). |
| Putih di atas `#047857` | 5,48 : 1 | Lolos AA |
| `#34d399` di atas `#0f172a` | 9,28 : 1 | Lolos AAA |

Konsekuensi:

- Label tombol dan tautan berukuran normal tidak boleh memakai putih di atas `#059669`. Gunakan `#047857` sebagai warna isi tombol, atau terima penyimpangan ini secara sadar (lihat DEC-D1).
- Ikon, garis, dan ikon logo boleh memakai `#059669`.
- Pasangan lain (teks semantik, `surface-500`, dan seterusnya) perlu diperiksa dengan alat kontras sebelum dipakai untuk teks kecil. Aturan praktis: untuk teks di atas putih, pakai tingkat 700 atau lebih gelap.
- Fokus keyboard wajib terlihat: ring 2px `primary-600` (terang) atau `primary-400` (gelap) dengan offset 2px.
- Hormati `prefers-reduced-motion`; animasi hanya untuk respons terhadap aksi pengguna (buka dialog, simpan, hapus).

**DEC-D1 (keputusan terbuka):**
- Opsi A: fill tombol primer `#059669` (selaras logo, label putih 3,77:1).
- Opsi B: fill tombol primer `#047857` (label putih 5,48:1). Logo tetap `#059669`.
- Rekomendasi: B. Aplikasi ini dipakai lama oleh banyak operator; keterbacaan label lebih penting daripada kesamaan persis dengan logo.

---

## 5. Tipografi

**Font:** Plus Jakarta Sans, berkas woff2 lokal (subset latin) agar tidak bergantung pada Google Fonts.
**Fallback:** `'Plus Jakarta Sans', Inter, system-ui, -apple-system, 'Segoe UI', sans-serif`

| Peran | Ukuran | Bobot | Line-height |
|---|---|---|---|
| Judul halaman (h1) | 1.5rem (24px) | 600 | 1.3 |
| Judul bagian (h2) | 1.25rem (20px) | 600 | 1.35 |
| Judul kartu (h3) | 1.125rem (18px) | 600 | 1.4 |
| Teks isi | 0.875rem (14px) | 400 | 1.5 |
| Teks tabel | 0.875rem (14px) | 400 | 1.4 |
| Label formulir | 0.875rem (14px) | 500 | 1.4 |
| Teks bantu, keterangan | 0.8125rem (13px) | 400 | 1.4 |
| Angka ringkasan (statistik) | 1.5rem (24px) | 600 | 1.2 |

Aturan:

- Kapitalisasi kalimat biasa (huruf besar hanya di awal kalimat). Hindari huruf kapital semua untuk label.
- Panjang baris teks isi maksimal 80 karakter.
- Angka di tabel memakai `font-variant-numeric: tabular-nums` agar rata.

---

## 6. Bentuk dan ruang

| Token | Nilai | Penggunaan |
|---|---|---|
| Radius kecil | 6px | Input, tag |
| Radius sedang | 10px | Tombol, kartu, dialog |
| Radius ikon logo | 25% dari sisi | Mengikuti `rx` pada ikon |
| Satuan jarak | 4px | Kelipatan: 4, 8, 12, 16, 24, 32 |
| Jarak antar kartu | 24px (`gap-6`) | |
| Padding kartu | 24px | 16px di layar kecil |
| Border | 1px `surface-200` / `surface-700` | |
| Bayangan | Hanya untuk dialog, dropdown, toast | Kartu cukup border, tanpa bayangan |

---

## 7. Komponen

**Tombol**
- Primer: satu per area. Isi `primary`, label putih. Ikon + label untuk aksi utama (`Tambah`, `Simpan`).
- Sekunder: `severity="secondary"` atau `outlined`.
- Bahaya: `severity="danger"`, hanya untuk hapus. Aksi hapus selalu lewat dialog konfirmasi.
- Teks aksi berupa kata kerja spesifik: "Simpan perubahan", bukan "Submit". Nama aksi sama di tombol, toast, dan log: tombol "Hapus" menghasilkan toast "Dihapus".

**Input**
- Tinggi seragam, border `surface-300`, fokus ring `primary`.
- Pesan validasi di bawah input, berurutan: wajib diisi → terlalu pendek → sudah dipakai → tersedia. Merah untuk kesalahan, kuning untuk peringatan, biru untuk "sama dengan data saat ini", hijau untuk tersedia.

**Tag dan status**
- Pakai pemetaan di bagian 3.4. Teks tag memakai warna 700, latar lembut.

**Tabel (DataTable)**
- Header `surface-50`, teks 600. Zebra tidak dipakai; hover baris `surface-100`.
- Kolom aksi di paling kanan, tombol ikon berbentuk bulat.
- Keadaan kosong: kalimat yang memberi arah ("Belum ada siswa untuk tahun ajaran ini. Pilih tahun ajaran lain."), bukan sekadar "No data".
- Skeleton saat memuat, bukan spinner pada tabel.

**Dialog**
- Lebar 450px untuk formulir singkat, 700px untuk profil. Judul 18px/600.
- Tombol batal di kiri, aksi utama di kanan.
- Dialog proses ("Mohon tunggu") tanpa tombol tutup.

**Menu samping**
- Item aktif: latar `primary-100` (terang) atau `primary-950` dengan 30% opasitas (gelap), teks dan ikon `primary-700` (terang) atau `primary-400` (gelap).
- Judul grup menu memakai teks kecil `surface-500`, kapitalisasi kalimat.

**Edit inline (SchoolProfile)**
- Mode lihat: teks + tombol pensil outline bulat.
- Mode ubah: input + tombol centang (`success`) + tombol silang (`secondary`, outline). Enter menyimpan, Escape membatalkan.

**Toast**
- Berhasil 3 detik, peringatan dan kesalahan 5 detik. Kesalahan menjelaskan apa yang salah dan cara memperbaikinya, tanpa permintaan maaf.

---

## 8. Tema gelap

| Elemen | Terang | Gelap |
|---|---|---|
| Latar halaman | `surface-50` | `surface-950` |
| Kartu, dialog | `surface-0` | `surface-900` |
| Border | `surface-200` | `surface-700` |
| Teks utama | `surface-900` | `surface-50` |
| Teks sekunder | `surface-600` | `surface-400` |
| Warna primer | `primary-600` (lihat DEC-D1) | `primary-400` |
| Label di atas primer | putih | `primary-950` |
| Logo | `sekolario-horizontal.svg` | `sekolario-horizontal-on-dark.svg` |

---

## 9. Implementasi

### 9.1 Preset PrimeVue (referensi)

```js
// src/layout/... atau src/theme/SekolarioPreset.js
import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura'; // sesuaikan path dengan yang dipakai Sakai-vue di repo

export const SekolarioPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '{emerald.50}',  100: '{emerald.100}', 200: '{emerald.200}',
            300: '{emerald.300}', 400: '{emerald.400}', 500: '{emerald.500}',
            600: '{emerald.600}', 700: '{emerald.700}', 800: '{emerald.800}',
            900: '{emerald.900}', 950: '{emerald.950}'
        },
        colorScheme: {
            light: {
                primary: {
                    color: '{emerald.600}',        // ganti '{emerald.700}' jika DEC-D1 = B
                    contrastColor: '#ffffff',
                    hoverColor: '{emerald.700}',   // '{emerald.800}' jika DEC-D1 = B
                    activeColor: '{emerald.800}'   // '{emerald.900}' jika DEC-D1 = B
                },
                surface: {
                    0: '#ffffff', 50: '{slate.50}', 100: '{slate.100}', 200: '{slate.200}',
                    300: '{slate.300}', 400: '{slate.400}', 500: '{slate.500}', 600: '{slate.600}',
                    700: '{slate.700}', 800: '{slate.800}', 900: '{slate.900}', 950: '{slate.950}'
                }
            },
            dark: {
                primary: {
                    color: '{emerald.400}',
                    contrastColor: '{emerald.950}',
                    hoverColor: '{emerald.300}',
                    activeColor: '{emerald.200}'
                },
                surface: {
                    0: '#ffffff', 50: '{slate.50}', 100: '{slate.100}', 200: '{slate.200}',
                    300: '{slate.300}', 400: '{slate.400}', 500: '{slate.500}', 600: '{slate.600}',
                    700: '{slate.700}', 800: '{slate.800}', 900: '{slate.900}', 950: '{slate.950}'
                }
            }
        }
    }
});
```

### 9.2 Variabel CSS untuk elemen di luar PrimeVue

```css
:root {
    --sk-brand: #059669;
    --sk-brand-strong: #047857;
    --sk-brand-tint: #d1fae5;
    --sk-ink: #0f172a;
    --sk-muted: #475569;
    --sk-line: #e2e8f0;
    --sk-canvas: #f8fafc;

    --sk-success: #059669;
    --sk-info: #0284c7;
    --sk-warn: #d97706;
    --sk-danger: #dc2626;

    --sk-chart-1: #059669;
    --sk-chart-2: #0ea5e9;
    --sk-chart-3: #f59e0b;
    --sk-chart-4: #8b5cf6;
    --sk-chart-5: #f43f5e;
    --sk-chart-6: #14b8a6;
    --sk-gender-l: #0ea5e9;
    --sk-gender-p: #ec4899;
}

.app-dark {
    --sk-brand: #34d399;
    --sk-brand-strong: #6ee7b7;
    --sk-brand-tint: #022c22;
    --sk-ink: #f8fafc;
    --sk-muted: #94a3b8;
    --sk-line: #334155;
    --sk-canvas: #020617;
}
```

### 9.3 Grafik (Chart.js)

Ambil warna dari variabel di atas lewat `getComputedStyle(document.documentElement).getPropertyValue('--sk-chart-1')`, jangan menulis ulang `rgba(...)` di tiap halaman. Warna indigo bawaan template diganti dengan palet 3.4 dan netral slate.

---

## 10. Bahasa antarmuka

Antarmuka saat ini bercampur Inggris dan Indonesia (contoh: judul dialog proses berbahasa Indonesia, sisanya Inggris).

**DEC-D2 (keputusan terbuka):** tetapkan satu bahasa untuk teks antarmuka. Rekomendasi: bahasa Indonesia, karena penggunanya operator sekolah. Jika dipilih, ubah bertahap per halaman bersamaan dengan i18n, dan pakai istilah yang sama di tombol, toast, dan pesan kesalahan.

---

## 11. Daftar periksa sebelum menggabungkan perubahan UI

- [ ] Hanya satu tombol primer di tiap area
- [ ] Warna diambil dari token/variabel, tidak ditulis hex langsung di halaman
- [ ] `severity` memakai `danger`, bukan `error`
- [ ] Tag status mengikuti pemetaan 3.4
- [ ] Grafik punya label teks, tidak hanya warna
- [ ] Teks kecil memakai warna tingkat 700 atau lebih gelap
- [ ] Fokus keyboard terlihat di tema terang dan gelap
- [ ] Logo sesuai tema (terang/gelap)
- [ ] Keadaan kosong, memuat, dan galat sudah ditangani
- [ ] Teks antarmuka mengikuti keputusan DEC-D2
