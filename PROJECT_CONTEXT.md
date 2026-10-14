# Konteks proyek

## Baseline dan maksud
Tanggal source review: 14 Oktober 2026 (WIB). Repo: `mzidanefarhanr/app-administration_school`; `main` @ `b02a87f0d57adffec297ed465a8afcd8e8d7ec59`. Tujuan pemilik: mempermudah operator mengelola siswa, guru, pembelajaran, surat, izin, pencatatan, backup dan analisis. Runtime NOT RUN.

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

Folder frontend `public` adalah nama pengelompokan source, bukan bukti data boleh diakses guest. Jangan membuka biodata siswa ke publik karena nama direktori tersebut.

## Domain dan keadaan
Siswa memiliki biodata dan keluarga. Keanggotaan rombel terpisah dari siswa; rombel terkait tahun ajaran, jenjang, jurusan dan wali kelas. Pegawai baseline terkait akun lewat NIK; mata pelajaran pegawai baseline satu FK, belum mendukung banyak penugasan.
Role seeder: Admin kemudian User. Status akun: Aktif kemudian Tidak Aktif. ID 1/2 mengikuti urutan seeder pada database baru, bukan jaminan ID setiap instalasi. Jangan menyalin role gudang 1–6.
`status_active` dan `first_login_at` adalah boolean baseline; makna bisnisnya perlu dikonfirmasi. Jangan menganggap first_login_at timestamp.

## Source yang belum lengkap
StudentController: index berisi query; store hanya transaksi kosong; update/destroy kosong; FormRequest siswa authorize false. EmployeeController dan operasi tulis StudentRombel belum lengkap. Teachers/Employees/Letters Form pada menu masih `#`. Subject dan master lainnya bukan bukti jurnal pembelajaran tersedia.
Dashboard memuat widget penjualan contoh; halaman siswa sendiri memiliki rekap/chart/filter tahun ajaran dan ekspor CSV. Penanda Dapodik bukan integrasi sinkronisasi.

## Risiko awal
Hak akses API, rollback audit, log atribut sensitif, payload siswa berlebihan, query seluruh data, lingkungan test tidak terisolasi, seed identitas menyerupai data pribadi, endpoint HTTP hardcode. Daftar dengan sumber ada di laporan baseline.

## Sumber kebenaran dokumentasi
REQUIREMENTS menyimpan target; DATABASE memisahkan schema aktual/usulan; ROADMAP menyimpan scope/status; DECISIONS menyimpan keputusan; laporan menyimpan evidence. HANDOFF berisi checkpoint sesi terbaru. Semua harus diaudit ulang jika HEAD berubah.
