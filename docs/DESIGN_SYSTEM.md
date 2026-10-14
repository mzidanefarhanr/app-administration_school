# Panduan desain dan alur operator

## Baseline
PrimeVue Aura pada `vue-app/src/main.js`, darkModeSelector `.app-dark`; layout dan komponen Sakai. Ini bukan proyek redesign total. Token warna/font final belum ditetapkan pemilik; pertahankan tema existing dan semantic tokens PrimeVue. Jangan menyalin CSS Blade/Bootstrap, palet/sidebar App Gudang sebagai keputusan desain sekolah.

## Navigasi target
Kelompok: Ringkasan; Siswa; Guru & Pegawai; Tahun Ajaran & Rombel; Persuratan; Perizinan; Pembelajaran; Arsip; Laporan; Pengaturan. Aktifkan menu setelah fitur tersedia. Sembunyikan dokumentasi/UI demo dari navigasi operasional setelah audit penggunaan; jangan hapus aset template massal.
Gunakan Bahasa Indonesia pada alur operator. Bahasa/nama DB existing tidak perlu diubah. Jika bilingual diinginkan, tetapkan scope terpisah.

## Pola layar
Daftar: judul, tahun ajaran relevan, filter, aksi berizin, tabel, pagination server; filter tersimpan dalam query URL bila berguna. Detail: ringkasan identitas, tab domain, riwayat, dokumen; data sensitif tampil hanya bila diizinkan. Form: grouping, label, required marker, error per field, fokus ke error pertama dan submit disabled selama request.
Tindakan massal: jumlah pilihan, preview perubahan, hasil tiap record, alasan gagal, dan retry aman. Aksi berbahaya memiliki konfirmasi yang menyebut objek/efek. Jangan gunakan label “hapus” untuk “arsip” bila perilakunya soft delete.

## Tema dan aksesibilitas
Gunakan surface/text/border semantic tokens, bukan putih/hitam hardcode. Chart membaca token dan memperbarui saat tema berubah. Status berlabel teks, tidak warna saja. Keyboard operasional, focus kembali setelah dialog ditutup, modal tidak menutupi tombol di layar kecil. Tabel lebar boleh scroll dalam container; body tidak overflow horizontal.

## State wajib
Loading, kosong tanpa data, kosong hasil filter, validation error, session expired, permission denied, network/server failure dan keberhasilan nyata. Jangan menutup error dialog sebelum operator memahami kegagalan. Grafik kosong jangan mengganti data sekolah dengan data demo.

## Bukti visual
Uji desktop dan mobile, light/dark, form panjang, dropdown, tabel dan PDF terpisah. Screenshot fixture sintetis; pemeriksaan manual pemilik dicatat tanggal/SHA/checklist. Ukuran viewport serta browser dilaporkan aktual, bukan diasumsikan.
