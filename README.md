# Aplikasi Administrasi Sekolah

Sistem berbasis web untuk membantu operator mengelola data siswa, guru, pegawai, dan administrasi pembelajaran secara terpusat. Arah pengembangan mencakup persuratan, perizinan, pencatatan, arsip dokumen, backup, dan analisis data.

## Status proyek
Baseline source: `main` @ `b02a87f0d57adffec297ed465a8afcd8e8d7ec59`, diperiksa 14 Oktober 2026 (WIB). Source menunjukkan master data dan tampilan rekap siswa; CRUD siswa/pegawai/keanggotaan rombel belum lengkap. Surat, izin, backup operasional, dan jurnal pembelajaran belum ditemukan implementasi lengkapnya. Runtime, test, build, dan browser belum dijalankan dalam penyusunan paket ini.

## Stack dan direktori
| Bagian | Teknologi / lokasi |
|---|---|
| Backend | Laravel `^10.10`, PHP `^8.1`, Sanctum `^3.3`; `laravel-api/` |
| Frontend | Vue 3, Vite, Pinia, PrimeVue 4, Tailwind; `vue-app/` |
| Database konfigurasi contoh | MySQL; schema melalui migration Laravel |
| Dokumentasi | `docs/`, `roadmap/`, `reports/feature-update/` |

Versi di atas adalah constraint repo, bukan rekomendasi versi terbaru. Upgrade dependensi merupakan scope terpisah dengan penilaian kompatibilitas.

## Menjalankan pengembangan
Langkah di bawah belum diuji pada komputer pemilik. Gunakan database pengembangan kosong/terisolasi, jangan database sekolah aktif.

```bash
cd laravel-api
composer install
cp .env.example .env
# Windows PowerShell: Copy-Item .env.example .env
# Atur database development, APP_URL dan FRONTEND_URL secara lokal.
php artisan key:generate
# Setelah memastikan koneksi menunjuk database development:
php artisan migrate
php artisan serve --host=127.0.0.1 --port=4001
```

Terminal kedua:
```bash
cd vue-app
npm ci
npm run dev
```

Vite dikonfigurasi port 4000. Frontend baseline masih memakai URL API HTTP hardcode, sehingga langkah di atas belum menjamin login berhasil. Fase 01b memindahkannya ke environment dan menyelaraskan CORS, cookie/session, serta Sanctum stateful domains. Jangan mengakses alamat API hardcode baseline sebagai bagian setup.
`FRONTEND_URL` diperlukan konfigurasi CORS/reset password, tetapi belum tercantum dalam `.env.example`. Lengkapi contoh tanpa rahasia saat Fase 01b.

Seeder baseline memuat identitas yang menyerupai data pribadi. Jangan menjalankan `db:seed` tanpa review dan penggantian dengan data sintetis. Jangan menambahkan password bootstrap nyata ke repo.

## Verifikasi
Baca `docs/TESTING.md` sebelum menjalankan `php artisan test`; konfigurasi SQLite di `phpunit.xml` masih dikomentari. Konfirmasi DB pengujian terisolasi terlebih dahulu. Build frontend: `npm run build` di `vue-app`. Pemeriksaan Pint gunakan mode `--test`; lint frontend baseline memakai `--fix`, sehingga gunakan mode non-mutasi untuk audit.

## Dokumentasi utama
- [Aturan agen](AGENTS.md)
- [Konteks proyek](PROJECT_CONTEXT.md)
- [Kebutuhan](docs/REQUIREMENTS.md)
- [Database](docs/DATABASE.md)
- [Roadmap](roadmap/ROADMAP.md)
- [Prompt OpenCode](docs/OPENCODE-WORKFLOW.md)
- [Handoff](reports/feature-update/HANDOFF.md)

## Lisensi dan data
Lisensi template/dependensi tetap mengikuti berkas asli masing-masing. Lisensi aplikasi secara keseluruhan belum ditetapkan dalam paket ini. Jangan commit `.env`, kredensial, database dump, backup, lampiran siswa, atau bukti uji berisi data pribadi.
