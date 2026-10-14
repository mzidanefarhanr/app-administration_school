# Konteks proyek

## Baseline dan maksud
Tanggal source review: 14 Oktober 2026 (WIB); audit Fase 00/01a: 15 Oktober 2026 (WIB). Repo: `mzidanefarhanr/app-administration_school`.
Baseline kode: `main` @ `b02a87f0d57adffec297ed465a8afcd8e8d7ec59`. HEAD saat audit: `b699feccd65357a10f7430c165400906e6394d49` (delta = paket docs, kode tidak berubah). Fase 01a menambah perubahan working tree belum commit (lihat laporan 01a).
Tujuan pemilik: mempermudah operator mengelola siswa, guru, pembelajaran, surat, izin, pencatatan, backup dan analisis. Runtime aplikasi (server hidup) NOT RUN; tes otomatis DB SQLite in-memory dijalankan di Fase 01a.

## Arsitektur aktual
| Area | Lokasi / perilaku source |
|---|---|
| API | `laravel-api/routes/api.php`, mayoritas resource dalam `auth:sanctum` |
| Autentikasi | `routes/auth.php`, `LoginRequest`, session controller; Sanctum SPA cookie/session |
| Model | `laravel-api/app/Models` dan relasi Eloquent; banyak soft delete |
| Audit | `app/Traits/Loggable.php`, `ActivityController`, tabel activities |
| Halaman Vue | `vue-app/src/views/pages/public`, `master`, `auth` |
| Routing Vue | `src/router/index.js`; pemeriksaan frontend tipe User terhadap `/master` |
| State | `src/stores/authStore.js`; persisted state sessionStorage, bukan sumber otorisasi |
| CRUD reusable | `src/utils/useResourceManager.js` |
| Tema | `src/main.js`: PrimeVue Aura, selector `.app-dark` |
| URL API | `src/apiLogin/index.js`: HTTP hardcode, perlu environment |
| Akses (Fase 01a) | `app/Support/AbilityCatalog.php`, `app/Providers/AccessServiceProvider.php`, tabel `roles`/`abilities`/`ability_role`/`role_user`; middleware `active`; Gate bernama `can:` |
| UI peran (Fase 01a) | `vue-app/src/views/pages/master/Roles.vue` (route `/master/roles`), penetapan peran di `master/Accounts.vue`; label English |
| Ekspor siswa (Fase 01a) | `GET /api/students/export` (`StudentExportController`); unduhan CSV di `public/Student.vue` |

Folder frontend `public` adalah nama pengelompokan source, bukan bukti data boleh diakses guest. Jangan membuka biodata siswa ke publik karena nama direktori tersebut.

## Domain dan keadaan
Siswa memiliki biodata dan keluarga. Keanggotaan rombel terpisah dari siswa; rombel terkait tahun ajaran, jenjang, jurusan dan wali kelas. Pegawai baseline terkait akun lewat NIK; mata pelajaran pegawai baseline satu FK, belum mendukung banyak penugasan.
Role seeder: Admin kemudian User. Status akun: Aktif kemudian Tidak Aktif. ID 1/2 mengikuti urutan seeder pada database baru, bukan jaminan ID setiap instalasi. Jangan menyalin role gudang 1–6.
Fase 01a menambahkan peran baru terpisah dari `type_users`: Admin Sistem, Operator Sekolah, Kepala Sekolah, Wakil Kepala Sekolah Bidang Kesiswaan (DEC-03). Pemetaan legacy memakai nama `type_users` (Admin→Admin Sistem, User→Operator Sekolah), bukan angka ID. Kemampuan bernama mis. `roles.manage`, `students.export`, `letters.approve`, `leaves.approve`, `letters.sign`.
`status_active` dan `first_login_at` adalah boolean baseline; Fase 01a menegakkan `status_active` (akun nonaktif ditolak login dan sesi aktifnya diakhiri). `status_user_id` (Aktif/Tidak Aktif) belum dijadikan sumber penegakan — keputusan terbuka. DEC-14 (2026-10-14 WIB) menyetujui register publik ditutup, akun nonaktif ditolak, ekspor siswa via `students.export`, dan pemetaan legacy `Admin`→Admin Sistem (`User` **tidak** otomatis Operator Sekolah).

## Source yang belum lengkap
StudentController: index berisi query; store hanya transaksi kosong; update/destroy kosong; FormRequest siswa authorize false. EmployeeController dan operasi tulis StudentRombel belum lengkap. Teachers/Employees/Letters Form pada menu masih `#`. Subject dan master lainnya bukan bukti jurnal pembelajaran tersedia.
Dashboard memuat widget penjualan contoh; halaman siswa sendiri memiliki rekap/chart/filter tahun ajaran dan ekspor CSV. Penanda Dapodik bukan integrasi sinkronisasi.

## Risiko awal
Hak akses API, rollback audit, log atribut sensitif, payload siswa berlebihan, query seluruh data, lingkungan test tidak terisolasi, seed identitas menyerupai data pribadi, endpoint HTTP hardcode. Daftar dengan sumber ada di laporan baseline.

## Akses (status setelah Fase 01a)
Penegakan backend terpusat via Gate bernama (tanpa bypass admin). Endpoint master/siswa/pegawai/roles/abilities dilindungi `can:`. FirstLogin terikat actor; atribut administratif akun dilindungi; `check-auth` diminimalkan; register publik ditutup. Default deny untuk kemampuan tak terdaftar. Kemampuan modul yang belum ada (surat/izin/pembelajaran) baru sebatas katalog/kemampuan, belum ada enforcement pada fitur yang belum dibangun.

## Sumber kebenaran dokumentasi
REQUIREMENTS menyimpan target; DATABASE memisahkan schema aktual/usulan; ROADMAP menyimpan scope/status; DECISIONS menyimpan keputusan; laporan menyimpan evidence. HANDOFF berisi checkpoint sesi terbaru. Semua harus diaudit ulang jika HEAD berubah.
