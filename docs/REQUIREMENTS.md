# Kebutuhan aplikasi

Status: DRAFT untuk pengembangan; disusun 14 Oktober 2026. Tujuan pemilik sudah diketahui, detail kebijakan pada DECISIONS masih terbuka. Tidak semua target di dokumen ini diotorisasi untuk langsung dibangun.
Catatan Fase 01a (2026-10-15): FR-01 sebagian tercapai — kemampuan bernama + enforcement backend terpusat, FirstLogin terikat actor, akun nonaktif ditolak, admin pengelola akses terakhir dilindungi, register publik ditutup. Ekspor siswa kini punya endpoint backend terlindungi (`students.view` + `students.export`) dengan kolom eksplisit dan filter sama dengan daftar. Sisa FR-01 (cakupan record per modul, pemetaan role lengkap) menunggu modul terkait.
Catatan Subfase 03c (2026-10-21, DEC-22): FR-04 dan bagian server-pagination FR-14 untuk daftar siswa tercapai — daftar/rekap/ekspor siswa server-side dengan filter All/Placed/Unplaced, pencarian nama/NIS/NISN, sort allowlist, paginasi 10/20/50/100 (default 20), atribut daftar minimum tanpa PII sensitif, dan ekspor yang mengikuti filter (batas baris menolak 422). Aksi penempatan rombel via UI belum termasuk (di luar 03c). Penerimaan manual 03c masih menunggu pemilik.
Catatan Subfase 05a (2026-10-30, DEC-25): FR-06 bagian **impor siswa** tercapai — CSV UTF-8 ≤ 5 MB/2.000 baris, header English ketat 17 kolom, preview tanpa mutasi, validasi per baris dengan aturan form yang sama, mode insert/update (match NIS lalu NIK, bukan nama), commit all-or-nothing, laporan error privat 30 hari, izin `students.import` untuk Admin Sistem dan Operator Sekolah. **Owner APPROVED 2026-10-30 WIB (checklist #1–#12).**

Catatan Subfase 05b (2026-10-30, DEC-26): FR-06 bagian **impor pegawai** dan **pilihan kolom ekspor pegawai** tercapai — template English 22 kolom, match update via NIK (NIK wajib update/opsional insert, 16 digit string, unik termasuk arsip), update parsial dengan validasi gabungan, Subject wajib Teacher/prohibited Non-teaching, Regency+District berpasangan, laporan error privat `Row,Field,Message,NIK,NUPTK`, izin `employees.import` untuk Admin Sistem dan Operator Sekolah; ekspor default tetap 10 kolom operasional dan 15 kolom PII hanya untuk `employees.manage` (`403` tanpa izin). Status: **IMPLEMENTED, agent-verified 2026-10-30; owner APPROVED 2026-10-31 WIB (checklist #1–#14)**. FR-06 **belum lengkap**: rekap sekolah menunggu contoh format (DEC-12 PROPOSED), di luar 05a–05c.

Catatan Subfase 05c (2026-10-31, DEC-28): FR-06 bagian **pemeriksaan kelengkapan data** tercapai — denominator eksplisit (placed students / active employees), daftar `missing`/`invalid`/`duplicate`/`blocked`, kandidat duplikat tanpa auto-merge, status review `open`/`acknowledged`/`ignored` berversi dan teraudit, serta deep link ke form koreksi yang sudah ada. Status: **IMPLEMENTED, agent-verified 2026-10-31; owner APPROVED 2026-10-31 WIB (checklist #1–#14)**. Rekap sekolah tetap **OPEN** dan menunggu contoh format (DEC-12 PROPOSED), di luar scope 05c.

Catatan Subfase 07a (2026-11-01, DEC-32): FR-08 bagian **surat masuk** tercapai sebagian — agenda dengan referensi internal `#id`, tanggal terima/surat, nomor sumber opsional/tidak unik, pengirim, perihal, kategori config, catatan, lampiran privat PDF/JPG/PNG (10 MB/5 per surat), pencarian/filter, edit/void berversi, dan history immutable. Status: **IMPLEMENTED, agent-verified 2026-11-01; review pemilik PENDING**. FR-08 **belum lengkap**: disposisi, deadline, status tindak lanjut, penomoran resmi, approval/sign, dan template PDF berada di 07b/07c dan memerlukan rencana serta keputusan terpisah (DEC-06/DEC-11 tetap OPEN).

## Pengguna dan batas produk
Pengguna utama operator; admin mengatur akses; guru/wali kelas dan kepala sekolah merupakan peran target yang perlu dipetakan ke akun aktual. Default scope satu sekolah, beberapa tahun ajaran. Multi-sekolah, portal orang tua/siswa, pembayaran, LMS lengkap, aplikasi mobile native dan integrasi otomatis pemerintah di luar roadmap awal.

## Kebutuhan fungsional dan kriteria minimum
| ID | Modul | Kriteria penerimaan produk | Fase |
|---|---|---|---|
| FR-01 | Akun dan akses | Semua API menegakkan kemampuan + cakupan record; profil hanya milik sendiri; akun dinonaktifkan kehilangan akses; admin terakhir terlindungi | 01 |
| FR-02 | Audit | Log dibuat server, actor/waktu/objek/aksi jelas, tidak menyimpan rahasia; rollback terbatas dan menolak konflik | 02 |
| FR-03 | Backup/restore | Database + lampiran + manifest, hasil keberhasilan nyata, retensi, uji restore ke target terisolasi | 02 |
| FR-04 | Siswa | Tambah/detail/ubah/arsip/restore, validasi identitas, keluarga, alamat, kebutuhan administrasi, tanpa wajib akun login siswa | 03 |
| FR-05 | Guru/pegawai | Biodata dan status kerja, relasi akun opsional bila kebijakan disetujui; penugasan dipisah dari akun | 04 |
| FR-06 | Impor/ekspor | Preview, validasi tiap baris, keputusan insert/update, laporan gagal, izin ekspor, identitas tetap string | 05 |
| FR-07 | Tahun ajaran/rombel | Tahun aktif eksplisit dan lock per tahun (06a implemented dan diterima pemilik 2026-10-31); CRUD kelas + wali kelas dari profil pegawai (06a); kenaikan, tinggal kelas, lulus melalui preview tanpa mutasi + commit atomik ber-token dan riwayat batch (06b implemented dan diterima pemilik 2026-11-01, #1–#14); mutasi masuk/keluar bertanggal + pembatalan kompensatif + rekap per tahun + koreksi periode locked ter-audit (06c implemented, agent-verified, dan diterima pemilik 2026-11-01, #1–#16); periode lama utuh | 06 |
| FR-08 | Surat masuk | Agenda, nomor/tanggal sumber, pengirim, kategori, lampiran privat, disposisi, deadline, status tindak lanjut | 07 |
| FR-09 | Surat keluar | Draft, nomor unik sesuai format disepakati, persetujuan, PDF, versi template dan snapshot data | 07 |
| FR-10 | Izin | Siswa/guru, tanggal/rentang, alasan, lampiran, status diajukan/disetujui/ditolak/dibatalkan, rekap | 08 |
| FR-11 | Arsip | Metadata terpusat, kategori, kaitan domain, versi, kontrol download, tanggal kedaluwarsa bila relevan | 09 |
| FR-12 | Pembelajaran | Penugasan guru-matpel-rombel-periode, jadwal dengan deteksi bentrok, jurnal per sesi, laporan belum diisi | 10 |
| FR-13 | Analisis/tugas | Statistik nyata dengan definisi dan tanggal acuan; surat/izin/jurnal/data belum lengkap; drilldown sesuai izin | 11 |
| FR-14 | Operasi | Pagination server, kegagalan yang terlihat, pekerjaan berat terpantau, deployment dan pemulihan terdokumentasi | 12–13 |

## Aturan bisnis lintas modul
- Nama manusia tidak unik. NIK/NIS/NISN kosong tidak berubah menjadi 0 dan tidak memicu duplikat palsu. Aturan panjang/format ditentukan per field, tidak menebak dari min length saja.
- Perubahan NIK tidak boleh memutus FK; usulan beralih ke student_id/user_id memakai migration bertahap.
- Siswa boleh terdata sebelum NIK lengkap. Kebijakan satu rombel aktif per siswa/periode perlu keputusan tertulis.
- Tahun ajaran aktif berasal dari konfigurasi/data, bukan selalu perhitungan bulan kalender; tahun yang belum tersedia mendapat empty state.
- Catatan izin tidak otomatis mengubah absensi karena modul absensi terpisah belum disepakati.
- Nomor surat keluar unik, dibuat server secara atomik; pembatalan tercatat tanpa menggunakan ulang nomor yang telah diterbitkan kecuali kebijakan pemilik menyatakan lain.
- Persetujuan tidak boleh mengubah diri sendiri menjadi approver yang berwenang; aturan delegasi harus eksplisit.
- PDF yang telah diterbitkan mempertahankan snapshot identitas/penandatangan; perubahan master tidak mengubah surat lama.
- Arsip dan soft delete berbeda dari penghapusan permanen. Retensi/pemusnahan menunggu kebijakan pemilik.

## Kebutuhan nonfungsional
NFR-01: data pribadi hanya terlihat/terunduh sesuai kebutuhan peran; laporan dan evidence memakai fixture sintetis.
NFR-02: transaksi dan constraint menjaga identitas unik, nomor surat dan aksi bulk; double-submit tidak menggandakan efek.
NFR-03: empty/loading/validation/network error jelas; kesalahan tidak disamarkan menjadi sukses.
NFR-04: keyboard, label, focus dialog, mobile, light/dark konsisten dengan PrimeVue.
NFR-05: ukuran data uji dan target response disepakati sebelum klaim performa; hasil dicatat dengan metode.
NFR-06: backup selesai hanya setelah manifest/berkas diperiksa dan restore diuji; target RPO/RTO dicatat di DECISIONS.
NFR-07: perubahan fitur memiliki tes relevan dan checklist UAT; build sukses tidak membuktikan API/hak akses sukses.
NFR-08: koneksi produksi HTTPS; kredensial terpisah dari VITE_* yang bersifat publik.

## Nilai guna utama
Operator dapat memakai data sekali untuk rekap dan surat, melihat pekerjaan tertunda, serta menelusuri dan memulihkan perubahan. Ukur dengan skenario: waktu impor data, waktu membuat surat, jumlah data belum lengkap, dan keberhasilan restore; jangan mengklaim peningkatan sebelum mengukur.
