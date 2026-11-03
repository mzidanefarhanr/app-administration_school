# Laporan i18n-1 — Fondasi i18n (Worktree A)

- Branch: `feature/i18n` · Parent SHA: `4d974ad7c33b763595cf8dd6d5e3b643e288fd6f` (commit i18n-0). Dasar `main` `4a14017`.
- Keputusan pemilik: setuju D1–D7 (lihat `docs/I18N.md` bagian 10).

## Yang dikerjakan
- Dependensi baru satu-satunya: `vue-i18n@^11` (`package.json`, `package-lock.json`: +5 paket, +83 baris lock). Versi terpasang dibaca dari lock.
- `vue-app/src/i18n/index.js`: `createI18n` (Composition API, `legacy: false`), locale awal dari `localStorage` (`app.locale`) atau `id`, fallback `id`, pesan dimuat dari `locales/<locale>/<namespace>.json` via `import.meta.glob`; `setLocale()` menyimpan pilihan, memperbarui `<html lang>` dan locale PrimeVue tanpa reload; plugin `app.use(i18n)`.
- `vue-app/src/i18n/useLocale.js`: komposabel `locale` (dapat di-`v-model`), `locales`, `setLocale`.
- `vue-app/src/i18n/primevue.js`: locale komponen PrimeVue `id` dan `en` (teks, hari/bulan, aria).
- `vue-app/src/i18n/locales/{id,en}/common.json`: namespace awal `common` (bahasa, aksi umum, status).
- `vue-app/src/main.js`: hanya dua baris yang diizinkan (`import i18n from '@/i18n';` setelah import router; `app.use(i18n);` setelah `app.use(Notifications);`). Blok `app.use(PrimeVue, {...})` tidak disentuh.
- `vue-app/index.html`: hanya atribut `lang` (`en` → `id`).
- `vue-app/src/utils/formatters.js`: nama bulan panjang dan pemisah ribuan mengikuti locale aktif (`id`: perilaku lama; `en`: `17 August 2026`, `1,234,567.5`). Format pendek `DD/MM/YYYY`, sufiks `WIB`, `formatPercentage`, dan `emptyPlaceholder` tidak berubah.
- `vue-app/src/layout/AppConfigurator.vue`: bagian "Bahasa/Language" (pemilih Indonesia/English) di bawah "Menu Mode". Teks lain di berkas itu ("Primary", "Surface", "Presets", "Menu Mode") belum diterjemahkan; dijadwalkan i18n-2.

## Perintah dan hasil
| Perintah | Hasil |
|---|---|
| `npm install vue-i18n@^11` | OK, +5 paket |
| Skrip verifikasi sementara (`ssrLoadModule` Vite, di luar repo) | 24 assert **ALL PASS**: regresi format `id` identik, format `en`, `setLocale` tak valid ditolak, paritas kunci `id`/`en`, locale PrimeVue `id`/`en` (accept, aria, bulan) diterapkan saat install dan saat ganti bahasa |
| `npx eslint src/i18n src/main.js src/utils/formatters.js src/layout/AppConfigurator.vue` (non-mutasi) | **0 error**; 453 warning, seluruhnya `prettier: Delete ␍` (CRLF working copy Windows; sama dengan berkas lama) |
| `npm run build` (`vue-app`) | **sukses**, `✓ built in 29.09s`; peringatan CSS `Expected identifier but found "-"` sudah ada sebelumnya (terlihat juga pada build `main`) |
| Pemeriksaan scope bagian 9 (grep file milik B pada `git diff --name-only main...HEAD`) | tidak ada keluaran |
| `git diff --cached --check` | lihat commit di bawah |

## NOT RUN
- Uji browser/manual (desktop, mobile 390 px, terang/gelap, ID/EN, pindah bahasa tanpa reload, persistensi `localStorage` di browser sungguhan): **belum dijalankan**; checklist untuk pemilik di bawah. Skrip verifikasi berjalan di Node tanpa `window`, sehingga bagian `localStorage` hanya teruji sebagai jalur gagal-aman.
- Tidak ada unit test permanen di repo (belum ada kerangka uji frontend; tidak menambah dependensi sesuai aturan 3.9). Skrip verifikasi bersifat sementara.
- `php artisan test`: tidak relevan (backend tidak diubah).

## Checklist manual untuk pemilik
1. `npm run dev` (port bukan yang dicadangkan DEC-27), buka aplikasi: bahasa awal Indonesia, `<html lang="id">`.
2. Buka panel konfigurator (ikon palet), pilih **English**: `<html lang>` berubah menjadi `en`, label bagian bahasa berubah menjadi "Language", tanpa reload.
3. Muat ulang halaman: pilihan bahasa tetap (`localStorage` `app.locale`).
4. Buka komponen dengan teks bawaan PrimeVue (kalender, paginator, filter tabel): teks mengikuti bahasa (mis. "Hari ini"/"Today").
5. Tanggal panjang dan angka ribuan berubah sesuai bahasa pada halaman yang memakai `formatters.js`.
6. Mode terang/gelap dan lebar 390 px: pemilih bahasa tidak merusak panel konfigurator.

## Blocker
Tidak ada.

## Risiko
1. Hanya `formatters.js` yang locale-aware. Halaman yang memakai `toLocale*String`/`Intl` langsung (83 pemakaian di 19 berkas) belum ikut; diselesaikan per subfase.
2. Locale PrimeVue diterapkan lewat microtask setelah `app.use(...)`; bila urutan `main.js` diubah (mis. oleh integrasi), pastikan PrimeVue tetap terpasang sebelum microtask berjalan.
3. Konfigurator masih bercampur ID/EN sampai i18n-2.
4. `main.js`, `index.html`: area bersama; konflik merge yang diharapkan hanya pada baris `import`/`app.use` di `main.js` dan atribut `lang`.

## REQUESTS
Tidak ada permintaan baru. R1 (lihat `REQUESTS.md`) terjawab: locale PrimeVue dipasang tanpa menyentuh blok milik B.

## Persetujuan manual pemilik
Belum ada untuk implementasi i18n-1 (tidak diklaim).
