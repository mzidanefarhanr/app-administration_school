# Arsitektur aplikasi

## Aktual
Browser menjalankan Vue SPA; Axios instance mengirim request cookie/CSRF ke Laravel; Laravel memakai Sanctum stateful middleware, controller dan Eloquent untuk MySQL. Login/logout route web terpisah dari API resource. PrimeVue Aura menangani komponen dan tema. Auth store tersimpan sessionStorage; validitas sesi ditentukan server.

## Arah implementasi
Tetap dua subproyek. Resource sederhana boleh memakai controller + FormRequest + API Resource. Gunakan service domain untuk operasi lintas record: kenaikan kelas, impor, nomor surat, keputusan izin, backup/restore dan rollback. Controller tipis memanggil service setelah authorization; jangan membuat lapisan repository generik tanpa kebutuhan.
Policy menegakkan operasi dan cakupan objek; named abilities di satu konfigurasi/sumber pusat. Frontend menerima kemampuan untuk presentasi tombol, bukan memutuskan izin final. State server terbaru harus dipakai setelah perubahan sesi/role.

## Konsistensi dan konkurensi
Transaksi mencakup domain data dan audit wajib. Unique constraint merupakan perlindungan terakhir. Optimistic lock harus memasukkan versi pada kondisi update dan memeriksa affected rows; baca-cek-simpan saja tidak cukup. Gunakan row lock/counter atau pendekatan atomik untuk nomor surat. Impor/aksi bulk memakai idempotency key atau job token dengan hasil terpersisten.
Download, PDF dan backup melalui handler berizin. Metadata file di DB; bytes di storage privat. Simpan checksum dan ukuran; jalur tidak boleh berasal bebas dari request.

## Pekerjaan berat
Baseline queue sync. Jangan menjanjikan background processing sebelum worker dan retry tersedia. Untuk instalasi kecil, proses bounded synchronous boleh digunakan dengan batas ukuran; impor/PDF/backup besar memerlukan job state queued/running/completed/failed dan penanganan retry. Pilih implementasi lokal/hosting setelah DEC-02.

## Penyebaran tanggung jawab
| Lokasi target | Tanggung jawab |
|---|---|
| Controllers / Requests / Resources | HTTP, validasi, serialisasi sesuai hak akses |
| Policies / konfigurasi abilities | izin tindakan + scope data |
| Services / Jobs | operasi bisnis atomik dan pekerjaan berat |
| Models / migration | relasi, cast, constraint, index |
| Vue API modules / composables | komunikasi, state, UI reusable |
| Vue views/components | alur operator, tabel, form, dialog |

Lokasi target adalah usulan; jangan menciptakan semua folder kosong. Tambah sesuai subfase aktif. Route baru harus dicatat di API-CONTRACTS. Jangan ganti stack, menerjemahkan nama DB massal, atau menghapus seluruh template sebelum audit usage.
