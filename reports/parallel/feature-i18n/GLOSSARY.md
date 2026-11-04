# GLOSSARY.md — Glosarium Istilah Terjemahan (Indonesia ↔ English)

**Proyek:** Sistem Administrasi Sekolah (Sekolario)
**Cabang:** `feature/i18n` (Worktree A)
**Tujuan:** Standarisasi istilah baku antarmuka untuk memastikan konsistensi terjemahan dari navigasi, master data, transaksi kesiswaan, kepegawaian, tata persuratan, perizinan, hingga kearsipan dan pembelajaran.

---

## 1. Prinsip Umum Penerjemahan

1. **Nama Diri & Merek:** "Sekolario" adalah nama diri dan **tidak diterjemahkan**.
2. **Akronim & Istilah Kedinasan Baku:** Tetap dipertahankan dalam singkatan resminya:
   - `NISN` (Nomor Induk Siswa Nasional)
   - `NIS` (Nomor Induk Siswa)
   - `NIK` (Nomor Induk Kependudukan)
   - `NIP` (Nomor Induk Pegawai)
   - `NUPTK` (Nomor Unik Pendidik dan Tenaga Kependidikan)
   - `NPSN` (Nomor Pokok Sekolah Nasional)
   - `PTK` (Pendidik dan Tenaga Kependidikan)
   - `Dapodik` (Data Pokok Pendidikan)
   - `WIB` (Waktu Indonesia Barat)
3. **Pola Penamaan Kunci i18n:** Menggunakan konvensi `modul.bagian.kunci` dalam format lowerCamelCase.

---

## 2. Navigasi & Menu Utama (`layout/AppMenu.vue` & Breadcrumbs)

| Bahasa Indonesia | English Translation | Keterangan / Konteks |
| :--- | :--- | :--- |
| **Beranda** | **Home** | Kategori menu navigasi |
| Dasbor | Dashboard | Halaman ringkasan statistik |
| Profil Sekolah | School Profile | Identitas & data umum sekolah |
| Pusat Tugas & Pengingat | Task & Reminder Center | Antrean tugas & pengingat operator |
| **Data Master** | **Master Data** | Kategori menu referensi |
| Jenjang Pendidikan | Education Levels | Tingkat sekolah (SD, SMP, SMA, SMK) |
| Sekolah | Schools | Entitas sekolah terdaftar |
| Status Fase | Phase Statuses | Status kurikulum / fase pembelajaran |
| Profesi | Professions | Referensi profesi orang tua/wali |
| Jurusan Siswa | Student Majors | Program keahlian / peminatan |
| **Akademik** | **Academic** | Kategori operasional kalender & kelas |
| Rombongan Belajar (Rombel) | Class Groups | Rombel / rombongan belajar per angkatan |
| Kenaikan & Kelulusan | Promotion & Graduation | Alur kenaikan tingkat dan kelulusan |
| Tahun Ajaran | Academic Years | Periode tahun pelajaran |
| **Kesiswaan & Kepegawaian** | **Students & Staff** | Kategori data induk pengguna sekolah |
| Data Siswa | Students | Buku induk & direktori peserta didik |
| Direktori Guru | Teacher Directory | Daftar pendidik / guru pengajar |
| Direktori Pegawai | Staff Directory | Daftar tenaga kependidikan / tata usaha |
| Kualitas Data | Data Quality | Validasi kelengkapan & anomali data |
| Laporan Sekolah | School Reports | Rekapitulasi & statistik kesiswaan/PTK |
| **Tata Persuratan** | **Letter Management** | Kategori administrasi persuratan |
| Surat Masuk | Incoming Letters | Registrasi & arsip surat masuk |
| Surat Keluar | Outgoing Letters | Pembuatan & penerbitan surat keluar |
| Disposisi Surat | Letter Dispositions | Alur arahan/disposisi surat pimpinan |
| Pengaturan Surat | Letter Settings | Konfigurasi penomoran & template kop |
| **Perizinan Pegawai** | **Staff Leaves & Permissions** | Kategori absensi dinas & perizinan |
| Pengajuan Izin & Cuti | Leave & Permission Requests | Form & daftar pengajuan izin |
| **Arsip Dokumen** | **Document Archives** | Kategori repositori dokumen sekolah |
| Repositori Arsip | Archive Repository | Manajemen berkas digital & SK |
| **Administrasi Pembelajaran** | **Teaching Administration** | Kategori tata kelola pembelajaran guru |
| Penugasan & Jadwal | Teaching Schedules & Assignments | Jadwal mengajar mingguan guru |
| Jurnal Mengajar | Teaching Journals | Pencatatan kegiatan belajar mengajar |
| Laporan Kepatuhan Jurnal | Journal Compliance Reports | Monitoring keterisian jurnal guru |
| **Akun Saya** | **My Account** | Kategori identitas pengguna aktif |
| Profil Pengguna | User Profile | Biodata akun & ubah kata sandi |
| **Pengaturan Akses** | **Access Management** | Kategori administrator sistem |
| Manajemen Pengguna | User Management | Pengelolaan akun pengguna |
| Tipe Pengguna | User Types | Kategori profil akun (PTK/Siswa/Umum) |
| Peran & Hak Akses | Roles & Permissions | Konfigurasi wewenang RBAC |

---

## 3. Istilah Domain Khusus Sekolah

| Istilah Indonesia | Istilah English Baku | Catatan Penggunaan |
| :--- | :--- | :--- |
| **Rombongan Belajar (Rombel)** | **Class Group** (jamak: *Class Groups*) | Menggambarkan rombongan belajar dalam satu kelas |
| **Wali Kelas** | **Homeroom Teacher** | Guru penanggung jawab rombel |
| **Kenaikan Kelas** | **Grade Promotion** | Alur naik ke tingkat kelas berikutnya |
| **Kelulusan** | **Graduation** | Pelepasan siswa tingkat akhir |
| **Mutasi Masuk** | **Transfer In** | Siswa pindahan dari sekolah lain |
| **Mutasi Keluar** | **Transfer Out** | Siswa pindah ke sekolah lain |
| **Tahun Ajaran Aktif** | **Active Academic Year** | Periode akademik yang sedang berjalan |
| **Semester Ganjil** | **Odd Semester** / **First Semester** | Semester ke-1 |
| **Semester Genap** | **Even Semester** / **Second Semester** | Semester ke-2 |
| **Terkunci** | **Locked** | Penguncian data arsip tahun ajaran lampau |
| **Buku Induk Siswa** | **Student Master Register** | Arsip identitas lengkap kesiswaan |
| **Pendidik (Guru)** | **Teacher** | PTK fungsional mengajar |
| **Tenaga Kependidikan** | **Non-teaching Staff** / **Staff** | Pegawai tata usaha, pustakawan, laboran |
| **Disposisi** | **Disposition** | Instruksi tindak lanjut pimpinan |
| **Tindak Lanjut** | **Follow-up** | Tindakan pelaksana atas disposisi |
| **Sifat Surat** | **Letter Urgency** | Tingkat urgensi: Biasa, Penting, Rahasia |
| **Kop Surat** | **Official Letterhead** | Bagian kepala surat dinas resmi |
| **Nomor Surat** | **Letter Number** | Format penomoran otomatis |
| **Cuti / Izin** | **Leave / Permission** | Ketidakhadiran kerja resmi |
| **Kepatuhan Jurnal** | **Journal Compliance** | Tingkat ketertiban pengisian KBM |

---

## 4. Status Dokumen & Status Transaksi

| Nilai Status (ID) | Nilai Status (EN) | Pemakaian & Severity PrimeVue |
| :--- | :--- | :--- |
| **Draft** | **Draft** | Dokumen belum diajukan (`secondary`) |
| **Review** | **Under Review** | Menunggu telaah verifikator (`warn`) |
| **Disetujui** | **Approved** | Surat/izin telah disetujui (`success`) |
| **Diterbitkan** | **Issued** | Surat keluar resmi bertanda tangan (`success`) |
| **Ditolak** | **Rejected** | Pengajuan tidak disetujui (`danger`) |
| **Dibatalkan** | **Cancelled** | Pembatalan transaksi (`secondary`) |
| **Aktif** | **Active** | Entitas akun / tahun ajaran aktif (`success`) |
| **Tidak Aktif / Nonaktif** | **Inactive** | Entitas tidak aktif (`secondary`) |
| **Menunggu** | **Pending** | Menunggu keputusan pimpinan (`warn`) |
| **Selesai** | **Completed** | Disposisi atau tugas selesai tuntas (`success`) |

---

## 5. Tombol Aksi & Komponen Umum (`common.json`)

| Indonesia | English | Pemakaian |
| :--- | :--- | :--- |
| **Simpan** | **Save** | Tombol submit form utama |
| **Batal** | **Cancel** | Batalkan dialog / edit |
| **Tutup** | **Close** | Menutup modal dialog |
| **Hapus** | **Delete** | Menghapus data terpilih |
| **Ubah / Edit** | **Edit** | Membuka form penyuntingan |
| **Tambah** | **Add** | Menambah entri baru |
| **Cari** | **Search** | Input pencarian data |
| **Muat Ulang / Segarkan** | **Refresh** | Reload data tabel dari server |
| **Ekspor** | **Export** | Mengunduh berkas rekap CSV/PDF |
| **Impor** | **Import** | Unggah data massal (bulk upload) |
| **Unduh** | **Download** | Mengunduh lampiran / berkas |
| **Unggah** | **Upload** | Mengunggah dokumen/foto |
| **Lihat Detail** | **View Details** | Menampilkan rincian entitas |
| **Konfirmasi** | **Confirm** | Dialog persetujuan tindakan kritis |
| **Ya** | **Yes** | Opsi konfirmasi setuju |
| **Tidak** | **No** | Opsi konfirmasi batal |
| **Kembali** | **Back** | Navigasi mundur |
| **Pilih** | **Select** | Memilih opsi dropdown/list |
| **Semua** | **All** | Filter tanpa batasan |
| **Filter** | **Filter** | Penyaringan data tabel |
| **Reset Filter** | **Reset Filters** | Mengembalikan filter ke kondisi awal |

---

## 6. Pesan Notifikasi Toast & Status Permintaan

| Tipe / Konteks | Bahasa Indonesia | English |
| :--- | :--- | :--- |
| **Sukses Simpan** | Berhasil Disimpan | Successfully Saved |
| **Sukses Ubah** | Perubahan Berhasil Disimpan | Changes Saved Successfully |
| **Sukses Hapus** | Data Berhasil Dihapus | Data Deleted Successfully |
| **Gagal Proses** | Gagal Memproses Data | Failed to Process Request |
| **Koneksi Terputus** | Gagal Menghubungi Server | Unable to Connect to Server |
| **Sesi Habis** | Sesi Anda Telah Berakhir | Your Session Has Expired |
| **Akses Ditolak** | Anda Tidak Memiliki Hak Akses | Access Denied |
| **Data Kosong** | Belum Ada Data Tersedia | No Data Available |
| **Memuat Data** | Memuat Data… | Loading Data… |

---

Glosarium ini menjadi acuan tunggal untuk seluruh implementasi subfase terjemahan i18n-2 hingga i18n-9.
