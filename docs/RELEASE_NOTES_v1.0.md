# Catatan Rilis Resmi — Versi 1.0.0
## Release Notes — Sistem Administrasi Sekolah (Golden Master v1.0.0)

**Tanggal Rilis:** 3 November 2026  
**Status Kematangan:** *Production-Ready / Stable Release*  
**Tag Rilis Git:** `v1.0.0`  
**Baseline Repositori:** [`dcc607b`](https://github.com/mzidanefarhanr/app-administration_school/commit/dcc607b)  

---

## 1. Ringkasan Eksekutif Produk

Versi 1.0.0 merupakan rilis stabil perdana (*Golden Master*) dari **Sistem Administrasi Sekolah**, sebuah platform tata kelola terintegrasi modern berbasis web yang dibangun untuk mendigitalkan seluruh aktivitas administrasi, persuratan, kepegawaian, kesiswaan, dan pembelajaran sekolah secara tertib, aman, dan dapat dipertanggungjawabkan (*accountable*).

Aplikasi dibangun menggunakan kombinasi teknologi modern:
- **Backend:** Laravel 11 (PHP 8.2+) dengan arsitektur RESTful API (334 endpoint terdaftar).
- **Frontend:** Vue 3, Vite, PrimeVue (Sakai template) menyajikan 27 halaman rute antarmuka SPA yang responsif (desktop, tablet, dan ponsel) serta mendukung tema Terang (*Light*) dan Gelap (*Dark*).
- **Database:** MariaDB 10.4+ / MySQL 8.0+ dengan integritas relasi data InnoDB ACID.

---

## 2. Cakupan 10 Modul Domain Sekolah (Fitur Siap Pakai)

Seluruh 10 modul utama di bawah ini telah selesai 100%, terintegrasi, dan bebas dari data/komponen tiruan (*zero mock/placeholders*):

1. **Modul Data Master Sekolah:**
   - Manajemen Profil Sekolah, Jenjang Pendidikan, Tahun Ajaran, dan Semester aktif.
   - Fitur Penguncian Tahun Ajaran (*Academic Year Locking*) untuk melindungi integritas arsip historis masa lalu dari modifikasi tidak disengaja.
2. **Modul Akun, Peran & Otorisasi Pengguna:**
   - Otorisasi berbasis peran formal (*Role-Based Access Control* / RBAC) yang memetakan wewenang secara ketat untuk 5 profil peran kanonikal: `admin-sistem`, `operator-sekolah`, `kepala-sekolah`, `wakil-kesiswaan`, dan `guru`.
   - Perintah inisialisasi administrator aman melalui terminal: `php artisan app:bootstrap-admin`.
3. **Modul Kesiswaan & Rombongan Belajar (Rombel):**
   - Buku Induk Siswa Digital lengkap (biodata, NISN, NIK, alamat, riwayat keluarga).
   - Pengelompokan siswa ke dalam rombel per tahun ajaran aktif.
   - Tata kelola alur kenaikan kelas (*grade promotion*), kelulusan, dan mutasi siswa keluar/masuk.
4. **Modul Kepegawaian (Pendidik & Tenaga Kependidikan / PTK):**
   - Direktori biodata pegawai, NIP/NUPTK, status kepegawaian (PNS, PPPK, Honorer), jabatan, dan riwayat penugasan.
5. **Modul Tata Persuratan & Disposisi Digital:**
   - Penatausahaan Surat Masuk dan Surat Keluar ber-Kop resmi dinas sekolah.
   - Penomoran surat otomatis yang atomik dan terkunci (`lockForUpdate`).
   - Alur persetujuan surat berjenjang (*Draft $\rightarrow$ Review $\rightarrow$ Approved $\rightarrow$ Issued*) dengan pemisahan wewenang ketat dan proteksi *no self-approval*.
   - Lembar disposisi digital pimpinan kepada staf/guru dengan tenggat waktu tindak lanjut.
   - Generator dokumen PDF surat resmi dinas ber-Kop menggunakan Dompdf.
6. **Modul Perizinan & Cuti Pegawai/Siswa:**
   - Pengajuan izin mandiri oleh guru/pegawai (*self-service*) dan pengajuan izin siswa oleh wali kelas/operator.
   - Persetujuan izin oleh Kepala Sekolah dengan pencatatan jejak keputusan *append-only*.
7. **Modul Kearsipan Dokumen Digital:**
   - Penyimpanan arsip dokumen sekolah dengan skema versi berkas *immutable* (tidak dapat diubah atau ditimpa).
   - Verifikasi integritas setiap berkas menggunakan tanda tangan digital hash SHA-256.
8. **Modul Administrasi Pembelajaran (Jadwal & Jurnal Mengajar):**
   - Penugasan mengajar guru per mata pelajaran dan rombel.
   - Penyusunan jadwal pelajaran mingguan bebas bentrok dengan validasi otomatis `422 Conflict Detection`.
   - Pengisian jurnal harian mengajar idempoten oleh guru pengampu mata pelajaran.
   - Rekapitulasi laporan kepatuhan jam mengajar guru secara *real-time*.
9. **Modul Pusat Tugas & Pengingat Internal Operator:**
   - Dasbor tugas operasional terpadu (`/public/tasks`) yang secara otomatis mengagregasi 6 jenis pekerjaan tertunda di sekolah (surat belum bernomor, izin pending persetujuan, jurnal belum terisi, dan status kesehatan backup).
10. **Modul Antrean Background Jobs & Observabilitas:**
    - Pekerja antrean latar belakang (`php artisan queue:work`) untuk tugas berat (ekspor CSV ribuan baris, pengolahan data massal).
    - Pelacakan status pekerjaan di tabel `job_execution_statuses`.

---

## 3. Keamanan, Integritas Data, dan Keandalan Sistem

- **Zero PII Leak:** Penyaringan otomatis pada seluruh berkas log Monolog menggunakan `PiiRedactionProcessor` untuk menyensor NIK (`[REDACTED_NIK]`), kata sandi, token otorisasi, dan nomor telepon pribadi.
- **Observabilitas Kesalahan Terpadu:** Setiap permintaan HTTP dibekali header penjejak unik `X-Correlation-ID` (UUID v4) yang ditampilkan pada toast notifikasi peramban dan dicatat di log server untuk mempermudah pelacakan galat.
- **Penyimpanan Berkas Privat:** Seluruh lampiran (surat, izin, arsip) disimpan di `storage/app/attachments/` di luar *DocumentRoot* publik; tidak ada tautan langsung peramban (*no direct public URL*).
- **Sistem Pencadangan Otomatis:** Scheduler harian pukul 02:00 WIB menghasilkan arsip ZIP berenkripsi AES-256 (`app:backup-run`) dan rotasi retensi bertingkat 7 harian + 4 mingguan pukul 02:30 WIB (`app:backup-prune`).
- **Simulasi Pemulihan Bencana (*Restore Drill*):** Teruji dan terbukti dapat memulihkan 100% basis data dan berkas privat ke target sekali pakai (*zero data loss*) dengan penolakan mutlak menyentuh basis data operasional.

---

## 4. Batasan Sistem yang Diketahui (*Known Issues & Design Constraints*)

Sesuai dengan keputusan arsitektur proyek yang disahkan pemilik:
1. **Sinkronisasi Dapodik (DEC-12):** Dilakukan melalui pertukaran berkas CSV lokal semi-otomatis, bukan melalui koneksi API langsung ke server Kemendikbud, guna mencegah ketergantungan pada perubahan endpoint eksternal yang tidak terkendali.
2. **Pemberitahuan Sistem (Notifikasi):** Seluruh notifikasi tugas operasional disajikan langsung di antarmuka web melalui Pusat Tugas (`/public/tasks`), tanpa integrasi gateway SMS/WhatsApp pihak ketiga (guna menjaga biaya operasional Rp 0 dan privasi nomor telepon staf).
3. **Penyimpanan Berkas Lampiran:** Ukuran berkas dibatasi maksimal 10 MB per berkas dengan format PDF, PNG, JPG, dan JPEG.

---

## 5. Saran & Rekomendasi Pengembangan Masa Depan (v1.1+)

Jika sekolah menghendaki penambahan kapabilitas di masa mendatang, berikut adalah modul yang direkomendasikan:
1. **Modul Portal Orang Tua & Siswa (v1.1):** Halaman khusus bagi orang tua untuk memantau presensi, izin, dan jadwal belajar anak dari rumah.
2. **Notifikasi WhatsApp Bot / Telegram Gateway (v1.2):** Integrasi pengingat tugas pimpinan dan surat keluar via bot perpesanan lokal.
3. **Modul Penilaian & Cetak Rapor Digital (v2.0):** Penginputan nilai formatif, sumatif, dan pencetakan buku rapor Kurikulum Merdeka.
