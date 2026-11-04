# REQUESTS.md — feature/i18n

## R1
File       : `vue-app/src/main.js` (blok `app.use(PrimeVue, {...})`, milik B)
Alasan     : PrimeVue belum memakai locale; teks bawaan komponen tetap Inggris.
Usulan     : **Tidak ada perubahan di blok B.** A memasang locale lewat `usePrimeVue().config.locale` dari `src/i18n/`. Hanya konfirmasi bahwa pendekatan ini diterima.
Mendesak?  : tidak

## R2
File       : `laravel-api/resources/reference-labels.json`
Alasan     : Mungkin ada nilai master tanpa pasangan English.
Usulan     : Belum ada permintaan. Akan diajukan daftar entri yang kurang setelah audit i18n-4/5/6, bila ada.
Mendesak?  : tidak

## R3
File       : `vue-app/index.html`
Alasan     : `lang="en"` tidak sesuai bahasa bawaan `id`.
Usulan     : Ubah atribut `lang` menjadi `id` (diizinkan bagian 4.2 untuk A). Bukan permintaan lintas pemilik; dicatat sebagai informasi.
Mendesak?  : tidak
