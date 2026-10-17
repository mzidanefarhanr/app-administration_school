# Register keputusan

Status awal: keputusan belum dikonfirmasi kecuali tujuan umum dan penggunaan OpenCode. Agent boleh mengusulkan nilai, tetapi keputusan penting tidak dicatat APPROVED tanpa jawaban pemilik.

## Keputusan disetujui pemilik

### DEC-03 — Role dan kemampuan (APPROVED, 2026-10-15)
- Konteks: penetapan peran dan penegakan akses backend (Fase 01a).
- Pilihan pemilik:
  1. Peran awal: Admin Sistem; Operator Sekolah; Kepala Sekolah; Wakil Kepala Sekolah Bidang Kesiswaan (menggantikan Guru/Wali Kelas, lingkup awal: data kelas, penjadwalan, penilaian asesmen guru).
  2. Ekspor: Admin Sistem, Operator Sekolah, Kepala Sekolah boleh mengekspor sesuai lingkup akses datanya. Hanya Kepala Sekolah yang awalnya diberi kemampuan menyetujui surat, menyetujui izin, dan menandatangani dokumen. Wakil Kesiswaan tidak mendapat ekspor/persetujuan/penandatanganan secara default.
  3. Fleksibilitas: Admin Sistem dapat mengatur kemampuan tiap peran dan menetapkan peran pengguna. Konfigurasi di atas adalah nilai AWAL, bukan aturan yang dikunci di kode. Kemampuan bernama + enforcement backend terpusat; pisahkan view/create/update/delete/export/approve/sign; hindari cek inline `type_user_id`; admin tidak otomatis approve/sign; user tidak boleh eskalasi diri; admin pengelola akses terakhir dilindungi; perubahan izin berlaku pada request berikutnya; audit perubahan peran/izin tanpa rahasia.
- Dampak schema: tabel `roles`, `abilities`, `ability_role`, `role_user` (migration 2029_07_20_*). Field `type_users`/`status_user_id` legacy tidak diubah.
- Dampak API/UI: `GET/POST/PATCH/DELETE /api/roles`, `GET /api/abilities`, `POST/DELETE /api/users/{user}/roles[/{role}]`; middleware `active`; halaman `/master/roles`.
- Status: APPROVED. Fase affected: 01a.

### DEC-14 — Registrasi publik, akun nonaktif, ekspor siswa, pemetaan legacy (APPROVED, 2026-10-14 WIB)
- Konteks: penyelesaian Fase 01a; keputusan pemilik tertulis atas kebijakan autentikasi dan ekspor.
- Pilihan pemilik:
  1. **Registrasi publik ditutup**; akun dibuat oleh admin. (`POST /register` tetap dihapus.)
  2. **Akun `status_active=false`** ditolak login dan ditolak pada request berikutnya dari sesi aktif. `status_active` **tidak disamakan** dengan `status_user_id`.
  3. **Ekspor siswa dikendalikan kemampuan `students.export`** pada frontend dan backend, dan mengikuti lingkup data yang boleh diakses (tidak memberi akses tambahan melebihi akses baca).
  4. **Pemetaan legacy**: `Admin` → Admin Sistem. `User` **tidak** otomatis menjadi Operator Sekolah (peran ditetapkan manual admin). Tipe tak dikenal/tanpa tipe → tanpa peran (default deny). Akun operasional tidak diubah; perbaiki mekanisme pemetaan yang belum diterapkan ke operasional tanpa menghapus penetapan peran eksplisit admin.
  5. **Bahasa UI**: semua teks antarmuka yang ditambah/diubah pada pekerjaan ini memakai **English** (nama tampilan peran: System Administrator, School Operator, Principal, Vice Principal for Student Affairs). Identifier peran/kemampuan tetap. Fitur pergantian bahasa (i18n) **di luar scope**.
- Dampak API/UI: endpoint baru `GET /api/students/export` (CSV) dengan `students.view` + `students.export`; `Student.vue` mengunduh via endpoint (bukan `dt.exportCSV()`); tombol ekspor mengikuti izin aktual; teks UI halaman roles/matriks/penetapan peran/ekspor memakai English.
- Dampak schema/migration: migration baru memperbaiki auto-map legacy (hanya `Admin`→Admin Sistem; `User` tidak dipetakan otomatis) sebagai perubahan additive, tanpa menghapus `role_user` yang ditetapkan admin eksplisit.
- Status: APPROVED. Fase affected: 01a (penyelesaian).

### DEC-15 — Alur error autentikasi, pemulihan CSRF, dan reset password (APPROVED, 2026-10-16 WIB)
- Konteks: implementasi Fase 01b berdasarkan audit dan jawaban pemilik atas Q1–Q3.
- Pilihan pemilik:
  1. **Reset password (Q1)**: Form forgot password dan form password baru masuk scope 01b (dikerjakan setelah C-01–C-04 stabil). UAT lokal memakai `MAIL_MAILER=log` tanpa mengirim email nyata ke server/kredensial luar.
  2. **Sesi kedaluwarsa (Q2)**: Saat sesi aktif kedaluwarsa (401 pada request selain login/guest check), pengguna diarahkan ke `/auth/login` dengan pesan English yang tetap terlihat: "Your session has expired. Please sign in again." Tidak memakai modal konfirmasi.
  3. **Pemulihan CSRF 419 (Q3)**: Pada respons 419 yang dipastikan berasal dari penolakan CSRF, interceptor menyegarkan `csrf-cookie` sekali lalu mengulangi request asli maksimal sekali dengan payload utuh. Jika retry tetap gagal, tampilkan pesan pemulihan yang jelas tanpa loop.
- Dampak API/UI: interceptor `apiLogin` diperbaiki; routing dan form `/auth/forgot-password` serta `/auth/reset-password/:token` ditambahkan; `Logout.vue` dan `Profile.vue` diselaraskan ke English; `.env.uat` memakai `MAIL_MAILER=log`.
- Status: APPROVED. Fase affected: 01b.

### DEC-16 — Isolasi fixture, bootstrap admin, dan standar password (APPROVED, 2026-10-17 WIB)
- Konteks: perencanaan dan implementasi Fase 01c berdasarkan temuan audit fixture, penanganan BLK-03, dan jawaban pemilik atas Q-01c-1 s/d Q-01c-3.
- Pilihan pemilik:
  1. **Bootstrap admin pertama (Q-01c-1)**: Melalui CLI Artisan interaktif (`php artisan app:bootstrap-admin`), kata sandi diketik tersembunyi dengan konfirmasi. Ditolak bila pengguna aktif yang mampu mengelola akses (`canManageAccess()`) sudah ada; tidak mengubah akun lama.
  2. **Pembersihan data siswa riil seeder (Q-01c-2)**: Ganti data siswa riil dalam seeder dengan 10–20 siswa sintetis khusus lingkungan pengembangan/UAT terisolasi (`SyntheticDataSeeder`). `DatabaseSeeder` default hanya mengisi referensi murni (`ReferenceDataSeeder`) tanpa akun/password bawaan.
  3. **Standar password (Q-01c-3)**: Minimal 12 karakter tanpa kewajiban kombinasi huruf besar/kecil/angka. Diterapkan konsisten pada pembuatan akun, bootstrap, perubahan password, dan reset password. Akun lama tidak dipaksa mengubah kata sandi secara massal.
- Dampak API/UI: perintah Artisan `app:bootstrap-admin`; seeder dipisah ke `ReferenceDataSeeder` dan `SyntheticDataSeeder`; `StudentSeeder` lama dibersihkan dari data riil; validasi password minimal 12 karakter di backend dan form frontend; validasi keunikan nama pada `UserController` dihapus (nama boleh sama); validasi NIK string 16 digit angka (`digits:16`).
- Status: APPROVED. Fase affected: 01c.

### Keputusan yang masih OPEN (belum diputuskan)
- Makna `status_user_id` (jangan disamakan dengan `status_active`), deployment CORS/Sanctum produksi, strategi pencabutan token vs sesi.

## Register

| ID | Keputusan | Usulan awal / pertanyaan | Status | Fase paling lambat |
|---|---|---|---|---|
| DEC-01 | Cakupan sekolah | Satu sekolah untuk awal; multi-sekolah di luar scope | PROPOSED | 00 |
| DEC-02 | Lingkungan operasional | Local/LAN atau hosting; lokasi DB/storage; batas biaya | OPEN | 01b/02 |
| DEC-03 | Role dan kemampuan | Admin, Operator, Kepala sekolah, Guru/wali kelas; siapa boleh sign/approve/export | APPROVED (2026-10-15) | 01a |
| DEC-04 | Hubungan pegawai–akun | Profil boleh tanpa login; teacher assignment memakai profil pegawai | PROPOSED | 04a |
| DEC-05 | Identitas dan keanggotaan | NIK boleh kosong; satu rombel aktif per siswa/periode; NIS unik lingkup sekolah | PROPOSED | 03a/06a |
| DEC-06 | Format nomor dan persetujuan surat | Format, reset counter, pejabat penandatangan, kapan nomor diterbitkan | OPEN | 07b |
| DEC-07 | Jenis dan approval izin | Siswa/guru, approver, delegasi, perubahan/pembatalan keputusan | OPEN | 08a |
| DEC-08 | Impor data campuran | Preview wajib; default usulan validasi semua sebelum commit per batch kecil; update hanya eksplisit | PROPOSED | 05a |
| DEC-09 | Backup dan retensi | Lokasi, jadwal, RPO/RTO, enkripsi, lama simpan | OPEN | 02b |
| DEC-10 | Jadwal dan jurnal | Jam/sesi, exception libur/cancel/reschedule, batas waktu pengisian | OPEN | 10a |
| DEC-11 | Template dokumen | Kop, tanda tangan, stempel, jenis surat pertama dan format rekap yang benar-benar dipakai | OPEN | 07a/07c |
| DEC-12 | Laporan eksternal | Format ekspor sekolah; Dapodik via rekonsiliasi file dahulu, tanpa API otomatis | PROPOSED | 05b/11b |
| DEC-13 | Retensi data pribadi | Arsip siswa/alumni, audit, lampiran; siapa boleh memusnahkan | OPEN | 09b/13 |
| DEC-14 | Register/akun nonaktif/ekspor siswa/pemetaan legacy/Bahasa UI | Register ditutup; nonaktif ditolak; ekspor via `students.export`; legacy Admin→Admin Sistem (User manual); UI English | APPROVED (2026-10-14 WIB) | 01a |
| DEC-15 | Error auth, CSRF 419 retry, reset password scope 01b | Reset password in scope (mail log); session expired redirect login; 419 single retry | APPROVED (2026-10-16 WIB) | 01b |
| DEC-16 | Isolasi fixture, bootstrap admin, standar password 12 karakter | Bootstrap CLI interactive; synthetic seed dev only; password min 12 char | APPROVED (2026-10-17 WIB) | 01c |

## Format pencatatan keputusan
Tanggal WIB; ID; konteks; alternatif; pilihan pemilik dan bukti instruksi; dampak schema/API/UI; fase affected; status OPEN/PROPOSED/APPROVED/SUPERSEDED. Pertahankan riwayat bila keputusan berubah.
Jangan membebani pemilik dengan seluruh pertanyaan sekaligus. Fase 00 dapat audit tanpa jawaban; tanyakan hanya keputusan yang menghambat subfase berikutnya. Pekerjaan independen yang telah diotorisasi boleh terus berjalan.
