# Laporan i18n-0 — Inventaris dan rencana (Worktree A)

- Branch: `feature/i18n` · Dasar `main` SHA `4a140178999b40c76e777422c1c1d177c7388c07`
- Cakupan: hanya inventaris dan rencana. **Tidak ada kode aplikasi yang diubah.**
- Dokumen rencana: `docs/I18N.md`

## Perintah dan hasil
| Perintah | Hasil |
|---|---|
| `npm ci` (`vue-app`) | selesai |
| `composer install` (`laravel-api`) | paket terpasang; skrip `package:discover` gagal sekali karena `bootstrap/cache` belum ada (tidak ikut git); setelah folder dibuat, `composer dump-autoload` sukses (7237 kelas) |
| Skrip inventaris (Python, read-only) atas `vue-app/src` | 115 berkas, ~2.129 string kandidat (heuristik) |

## NOT RUN
`npm run build`, eslint, `php artisan test`, uji browser: tidak relevan untuk fase ini (belum ada perubahan kode). `.env` belum disalin ke worktree A; tidak dibutuhkan untuk inventaris.

## File yang disentuh
- `docs/I18N.md` (baru)
- `reports/parallel/feature-i18n/REPORT-i18n-0.md` (berkas ini)
- `reports/parallel/feature-i18n/REQUESTS.md` (baru)

## Blocker
Tidak ada blocker teknis. Implementasi menunggu keputusan D1–D7 di `docs/I18N.md` bagian 8.

## Risiko
1. Pesan backend berbahasa Inggris (169 literal) bisa membuat UI tetap campur bahasa bila D2 = P2.
2. Perkiraan string heuristik; ada teks dalam template multibaris, `computed`, dan konstanta yang belum terhitung. Hitungan pasti dibuat saat implementasi dengan skrip pemindai.
3. Banyak string non-template (menu, breadcrumb, opsi) harus dibuat reaktif; kesalahan di sini membuat pergantian bahasa butuh reload.
4. Berkas besar (`Student.vue` 2.920 baris, `Leaves.vue`, `Archives.vue`, `OutgoingLetters.vue`) memperbesar risiko regresi; dikerjakan per subfase kecil.
5. `.gitattributes`/CRLF: peringatan LF→CRLF muncul di commit sebelumnya; pastikan diff berkas JSON locale tetap konsisten.

## Persetujuan manual pemilik
Belum ada (tidak diklaim).
