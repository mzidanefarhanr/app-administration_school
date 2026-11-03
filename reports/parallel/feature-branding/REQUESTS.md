# REQUESTS — Perubahan butuh keputusan pemilik atau pihak A

## REQ-01: Skala heading typography

**File:** `vue-app/src/assets/layout/_typography.scss`  
**Alasan:** design.md §4 minta heading aplikasi lebih kecil (judul halaman 1.5 rem, judul kartu 1.125 rem, weight 600). Baseline h1 2.5 rem terlalu besar untuk UI administrasi padat. Design.md §10.8 bilang "hanya jika disetujui, berdampak seluruh halaman".  
**Usulan:**
```scss
h1 { font-size: 1.5rem; font-weight: 600; }
h2 { font-size: 1.25rem; font-weight: 600; }
h3 { font-size: 1.125rem; font-weight: 600; }
```
**Mendesak?:** Tidak. Kosmetik, tidak memblokir item lain.  
**Status:** Menunggu approval pemilik.

---

## REQ-02: File font Plus Jakarta Sans woff2

**File:** `vue-app/src/assets/css/plus-jakarta-sans/`  
**Alasan:** Struktur @font-face sudah ada, tapi file woff2 belum tersedia. Font fallback ke Inter.  
**Usulan:** Sediakan `PlusJakartaSans-{Regular,Medium,SemiBold}.woff2` atau arahkan ke sumber download.  
**Mendesak?:** Sedang. Font merek belum aktual.  
**Status:** Menunggu file dari pemilik.
