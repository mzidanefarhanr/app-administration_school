# Laporan Fase 01a â€” Pengelolaan peran/kemampuan dan penegakan akses backend

- Tanggal WIB: 2026-10-15
- Mode: implementasi + verifikasi (backend Laravel + UI Vue) + isolasi lingkungan uji
- Scope instruksi pemilik: (A) selesaikan BLK-01 isolasi DB uji; (B) rapikan bukti Fase 00; (C) implementasi 01a (peran/kemampuan + enforcement + perbaikan FirstLogin/atribut akun/check-auth/register); (D) verifikasi; perbarui dokumentasi. Batas: boleh ubah kode/migration/tes/dokumentasi; migration hanya di DB uji terisolasi; jangan seeder identitas asli/migrate:fresh operasional/backup/restore/commit.
- Branch / baseline SHA: `main` @ `b699feccd65357a10f7430c165400906e6394d49` (HEAD, tidak berubah). Perubahan = working tree belum commit.
- Working tree sebelum: `M CHANGELOG.md` (edisi pemilik +7 baris, dipertahankan), `M HANDOFF.md`, `M INDEX.md`, `M ROADMAP.md`, `?? PHASE-00-RUNTIME-AUDIT.md`.
- Working tree sesudah: 29 file tracked termodifikasi + 19 file baru (lihat Â§7). Termasuk perubahan pemilik pada CHANGELOG.md yang **tidak** direset/ditimpa.
- Lingkungan: Windows, PHP 8.1.25, Composer 2.8.2, Node 24.18.0, npm 11.4.2, MySQL port 3306 terbuka. DB uji = SQLite in-memory (isolated).
- Status teknis: `IMPLEMENTED` (test backend 51 PASS; unit race test PASS; build frontend PASS). Status review: `APPROVED` (seluruh checklist manual Fase 01a disetujui pemilik 2026-10-15 WIB; lihat §21–§22).

## 1. Outcome dan cakupan

### A. Isolasi DB uji (BLK-01) â€” SELESAI
- `.env.testing` baru: SQLite `:memory:`, cache/session/queue array, tanpa kredensial operasional.
- `phpunit.xml`: `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` dengan `force="true"`.
- Fail-safe `tests/CreatesApplication.php`: membatalkan suite bila APP_ENVâ‰ testing atau koneksi bukan sqlite in-memory. **Diverifikasi** memblokir config mysql `backdata_administrations`.
- Tidak ada config/route cache (`bootstrap/cache/config.php` tidak ada).
- Fixture sintetis: referensi `type_users`/`status_users` + peran/kemampuan; **tidak** menjalankan `DatabaseSeeder` (identitas asli).
- SQLite kompatibel dengan seluruh 35 migration (terbukti: seluruh tes menjalankan RefreshDatabase di sqlite).

### B. Bukti Fase 00 â€” dirapikan
- PROJECT_CONTEXT + DATABASE disinkronkan ke `b02a87f` (kode) / HEAD `b699fec` + perubahan 01a.
- Port 3306 tidak lagi diklaim sebagai bukti koneksi Laravel/DB.
- DEC-03 dicatat APPROVED (peran/kemampuan). Perilaku autentikasi terkait (register ditutup, akun nonaktif diblokir) dicatat sebagai **implementasi menunggu keputusan pemilik**, bukan APPROVED. Keputusan autentikasi lain tetap OPEN.

### C. Fase 01a â€” IMPLEMENTED
- Peran/kemampuan fleksibel (tabel + katalog + UI), enforcement Gate terpusat, perbaikan IDOR/atribut akun/check-auth/register.

### Tidak dikerjakan (sesuai batas)
Tanda tangan elektronik, alur surat/izin, jadwal/asesmen guru, subfase 01b (konfigurasi API/error auth), 01c lanjutan (validator fixture/FK, reset password frontend).

## 2. File dan kontrak

| File/endpoint | Perubahan | Permission/schema/payload affected |
|---|---|---|
| `migrations/2029_07_20_000001_create_roles_table.php` | Baru: tabel `roles`, `role_user` | schema additive |
| `migrations/2029_07_20_000002_create_abilities_table.php` | Baru: tabel `abilities`, `ability_role` | schema additive |
| `migrations/2029_07_20_000003_seed_and_map_roles.php` | Baru: seed katalog + auto-map user legacy (by name) | data additive, idempotent |
| `app/Support/AbilityCatalog.php` | Baru: definisi 24 kemampuan + 4 peran awal | sumber katalog |
| `app/Providers/AccessServiceProvider.php` | Baru: `Gate::before` kemampuan bernama | enforcement terpusat, tanpa bypass admin |
| `app/Http/Middleware/EnsureUserIsActive.php` | Baru: tolak akun `status_active=false` | middleware alias `active` |
| `app/Models/Role.php`, `Ability.php` | Baru | relasi peran/kemampuan |
| `app/Models/User.php` | `roles()`, `hasAbility/hasAnyAbility/hasRole/canManageAccess/flushAbilityCache`, cast boolean | resolusi kemampuan |
| `app/Http/Controllers/RoleController.php` | Baru | `roles.view`, `roles.manage`, `abilities.manage` |
| `app/Http/Controllers/AbilityController.php` | Baru | `roles.view` |
| `app/Http/Controllers/AccessAssignmentController.php` | Baru | `access.assign`, anti-eskalasi, last-admin, audit |
| `app/Http/Controllers/UserController.php` | Tulis ulang autorisasi; self vs manage; proteksi admin terakhir; email:rfc | `users.view`, `users.manage` |
| `app/Http/Controllers/FirstLoginController.php` | Update hanya akun actor; hanya `first_login_at` | anti-IDOR |
| `app/Http/Requests/Auth/LoginRequest.php` | Tolak akun nonaktif saat login | autentikasi |
| `routes/api.php` | Grup `active`; `can:` pada master/siswa/pegawai; endpoint roles/abilities/access; check-auth minimal | kontrak API |
| `routes/auth.php` | Register publik dihapus | kontrak API |
| `app/Traits/Loggable.php` | Kecualikan password/token dari audit | audit tanpa rahasia |
| `vue-app/src/views/pages/master/Roles.vue` | Baru: UI matriks peranâ†’kemampuan | halaman `/master/roles` |
| `vue-app/src/router/index.js`, `layout/AppMenu.vue` | Route + menu Roles & Abilities | UI |
| `laravel-api/.env.uat` (untracked), `.env.uat.example` | Baru: konfigurasi UAT (DB `school_access_uat`) | lingkungan UAT, tanpa secret di file tracked |
| `vue-app/.env.uat` (untracked) | Baru: `VITE_API_BASE_URL=http://127.0.0.1:8001` | UAT frontend â†’ API lokal |
| `vue-app/src/apiLogin/index.js` | `baseURL` dari `VITE_API_BASE_URL` (fallback URL lama) | konfigurasi URL API minimal untuk UAT |
| `laravel-api/database/seeders/UatSeeder.php` | Baru: akun sintetis 4 peran + 1 nonaktif | fixture UAT (tanpa identitas nyata) |
| `.gitignore` (laravel-api & vue-app) | Ignore `.env.uat` | mencegah kredensial UAT terlacak |

## 3. Evidence / verification

| ID | Metode / command | Objek | Expected | Actual | Status |
|---|---|---|---|---|---|
| E-01 | `php artisan test` | seluruh suite | lulus di DB uji | 34 passed (79 assertions), 13â€“22s | PASS |
| E-02 | `php artisan test tests/Feature/Access` | tes akses | lulus | 21+2 passed | PASS |
| E-03 | probe guard (mysql config) | fail-safe isolasi | blokir | `Refused to run tests against an unsafe database...` | PASS |
| E-04 | `npm run build` | frontend | build sukses | built in ~52s, `âœ“ built` | PASS |
| E-05 | `npx eslint src/views/pages/master/Roles.vue` | lint non-fix | 0 error | 0 errors, 3 warnings (lihat Â§3b) | PASS (warning) |
| E-06 | `./vendor/bin/pint --test <27 file berubah>` | style PHP | bersih | 27 files PASS | PASS |
| E-07 | `php artisan route:list --path=api` | route | termuat | roles/abilities/access/firstlogin terdaftar | PASS |
| E-08 | Playwright + cURL UAT (DB `school_access_uat`) | UI + HTTP | skenario Â§4 | lihat Â§4 tabel verifikasi UAT | PASS |
| E-09 | Migrasi pada DB operasional | â€” | â€” | **tidak dijalankan** (dilarang) | NOT RUN |

### 3b. Ketiga warning ESLint `Roles.vue` dan penilaiannya

Dari `npx eslint src/views/pages/master/Roles.vue` â†’ `0 errors, 3 warnings` (semua `prettier/prettier`, auto-fixable):
1. `66:47 Delete ','` â€” trailing comma yang diharapkan Prettier dihapus; **kosmetik**, tidak memengaruhi perilaku.
2. `99:71 Replace <paragraph multi-baris> with <satu baris>` â€” Prettier menyarankan teks `<p>` satu baris; **kosmetik**.
3. `110:66 Replace <ekspresi multi-baris> with <satu baris>` â€” ekspresi interpolasi `{{ roles ? roles.length : 'Loading' }}` disarankan satu baris; **kosmetik**.

Penilaian: ketiganya **bukan error**, tidak mutasi logika, dan akan hilang dengan `--fix`. `npm run lint` baseline memakai `--fix` sehingga tidak dipakai sebagai pemeriksaan read-only (sesuai AGENTS). Status: diterima sebagai warning; tidak dianggap blocker.

Catatan isolasi: setiap tes berjalan pada `Connection: sqlite` `:memory:`; database operasional `backdata_administrations` tidak disentuh. Assertion decoy: penolakan menulis memastikan data tidak berubah (E di `AuthorizationTest` "decoy").

Jumlah test aktual: **34 passed / 79 assertions** (dari output `php artisan test`). Tidak ada test gagal.

## 4. Skenario yang diuji (nama tes + cakupan)

**Sumber: `tests/Feature/Access/AuthorizationTest.php` (21 tes) + `DatabaseIsolationTest.php` (2) + `tests/Feature/Auth/AuthenticationTest.php` (4) + `RegistrationTest.php` (1); total 34.**

| Area | Nama tes | Cakupan |
|---|---|---|
| Isolasi DB | `test database is isolated sqlite in-memory`; `operational mysql database is never the test connection` | Bukti suite berjalan di sqlite `:memory:`, bukan DB operasional |
| Guest | `guest cannot access roles endpoint`; `guest cannot access users endpoint`; `check-auth does not leak full user object to guest` | 401 + payload check-auth tidak bocor |
| Tanpa izin | `user without ability is denied master write and data unchanged (decoy)`; `user without users.view is denied listing users` | 403 + decoy tidak berubah |
| Dengan izin | `admin sistem can list roles and abilities`; `operator sekolah can view and manage master data` | 200/201 pada endpoint aktual |
| Konfigurasi awal DEC-03 | `initial ability configuration matches owner decision`; `kepala sekolah can approve letters ability while admin cannot`; `admin is not granted approve or sign by any bypass` | ekspor/approve/sign sesuai keputusan; admin tanpa bypass |
| Admin ubah izin | `admin can change role abilities` | PATCH roles 200, pemetaan berubah |
| Perubahan izin pada sesi aktif | `ability changes apply on the next request including active session` | Sesi sama: izin berubah pada request berikutnya |
| Anti-eskalasi / penetapan peran | `user cannot escalate own role via access assignment`; `admin can assign role to another user` | self â†’ 422; assign oleh admin â†’ 200 |
| Eskalasi atribut akun | `user cannot change own administrative attributes via profile update` | `type_user_id` sendiri tidak berubah |
| Admin pengelola akses terakhir | `last access manager cannot be deactivated`; `the only access manager cannot be deactivated` | Perlindungan deaktivasi/pencabutan + self role-change |
| IDOR FirstLogin | `firstlogin cannot update another user account`; `firstlogin can update own account only` | akun lain â†’ 403; akun sendiri â†’ 200 |
| Akun nonaktif | `inactive account is denied on subsequent request`; `active account can access` | `status_active=false` â†’ 403 |
| Login | `users can authenticate using the login screen`; `users can not authenticate with invalid password`; `inactive users can not authenticate`; `users can logout` | Login/lougot + akun nonaktif ditolak |
| Register | `public registration route is not available` | 404/405 |

### Pemetaan klasifikasi tes

- **Tes endpoint aktual (enforcement nyata)** â€” dijalankan pada route yang benar-benar ada: `/api/roles`, `/api/abilities`, `/api/users` (`users.view`/`users.manage`), `/api/type-users` (`master.manage`), `/api/firstlogin/{id}`, `/api/users/{user}/roles`, login/register.
- **Tes kemampuan katalog (bukan endpoint)** â€” `initial ability configuration matches owner decision`, `kepala sekolah can approve letters ability while admin cannot`, `admin is not granted approve or sign by any bypass`. Ini menguji **katalog/pemetaan** kemampuan. Modul **surat/izin/pembelajaran belum tersedia**, sehingga `letters.approve`, `letters.sign`, `leaves.approve` **TIDAK** diklaim lulus end-to-end â€” hanya pemetaan kemampuannya yang diverifikasi.

### Verifikasi browser/HTTP pada lingkungan UAT (2026-10-15, sintetis)

Dijalankan dengan server UAT lokal (`127.0.0.1:8001`, DB `school_access_uat`) dan SPA (`127.0.0.1:4001`, mode uat). Metode: Playwright (browser MCP untuk UI) + skrip cURL HTTP (sesi berjarak-jarak cookie) untuk propagasi izin & penolakan.

| Skenario | Metode | Hasil | Status |
|---|---|---|---|
| Admin buka `/master/roles`, matriks kemampuan per grup | Playwright | 4 peran tampil; dialog "Atur Kemampuan: Wakilâ€¦" menampilkan checkbox per grup | PASS (bukti: `reports/feature-update/evidence/uat-roles.png`) |
| Admin tambah `students.export` ke Wakil Kesiswaan | Playwright | `PATCH /api/roles/4` â†’ 200; daftar kemampuan waka bertambah | PASS |
| Perubahan izin pada sesi aktif (request berikutnya) | cURL 2 sesi | Sesi waka: sebelum = YES, setelah cabut = NO, setelah pulih = YES | PASS |
| Wakil Kesiswaan ditolak API pengelolaan akun (URL langsung) | cURL | `GET /api/users` 403; `/api/users/1` 403; `/api/roles` 403; `/api/abilities` 403; `/api/activities` 403; `POST /api/users` 403 | PASS |
| Akun nonaktif ditolak login | cURL + Playwright | login â†’ 422 | PASS |
| Sesi akun yang dinonaktifkan ditolak request berikutnya | cURL | operator dinonaktifkan â†’ `GET /api/type-users` 403, `check-auth.authenticated=false` | PASS |
| `/api/check-auth` payload minimum | Playwright + cURL | hanya `id,name,username,type_user_id,status_user_id,status_active,abilities[],roles[]` | PASS |
| Admin tanpa bypass approve/sign | cURL | admin 21 kemampuan; tanpa `letters.approve/leaves.approve/letters.sign`; punya `students.export` | PASS |
| Audit perubahan akses tanpa rahasia | SQL + cURL | `activities.table_name='users'` ada; query leak `password/remember_token` = **0 baris** | PASS |

## 4b. Ekspor siswa â€” keterbatasan (penting)

**Status akhir (penyelesaian 01a): ekspor siswa kini memakai endpoint backend terlindungi `GET /api/students/export`.** Lihat Â§11. Bagian di bawah adalah kondisi SEBELUM penyelesaian (dipertahankan sebagai riwayat).

Kondisi sebelumnya: tombol "Export" pada `vue-app/src/views/pages/public/Student.vue` memanggil `dt.exportCSV()` (PrimeVue DataTable) â€” ekspor **sisi klien** dari data yang sudah diterima di browser (baris 254â€“259, 866 pada Student.vue). Karena itu:
- Kemampuan `students.export` sebelumnya hanya **dipetakan di katalog** tanpa titik penegakan server.
- Jangan mengklaim penegakan ekspor server sebelum Â§11 dikerjakan.

## 4c. Pemetaan peran legacy berdasarkan nama `type_users`

Kondisi awal 01a (diperbaiki pada penyelesaian, lihat Â§11b):
- Awalnya `Admin`â†’`admin-sistem` dan `User`â†’`operator-sekolah`.
- **Setelah DEC-14**: hanya `Admin`â†’`admin-sistem`. `User` **tidak** dipetakan otomatis (peran ditetapkan manual admin). Nama tak dikenal/tanpa tipe â†’ tanpa peran (default deny).
- Idempotent: hanya menambah `role_user` yang belum ada; `down()` tidak menghapus penetapan manual (`assigned_by` null check).
- Tidak ada fallback ke hak admin.

## 4d. Register publik & pemblokiran akun nonaktif

**DISETUJUI pemilik (DEC-14, 2026-10-14 WIB)**: register publik ditutup; akun `status_active=false` ditolak login dan ditolak pada request berikutnya dari sesi aktif. `status_active` **tidak disamakan** dengan `status_user_id`. Keputusan autentikasi lain (reset password end-to-end, bootstrap pengguna pertama, CORS/Sanctum deployment, strategi cabut token vs sesi) tetap OPEN.

## 4e. `status_active` vs `status_user_id` â€” jangan disamakan

- `status_active` (boolean) **diimplementasikan** sebagai penegak aktif/nonaktif (login + sesi). Terbukti di UAT.
- `status_user_id` (FK ke `status_users`: Aktif/Tidak Aktif) **belum** dijadikan sumber penegakan. Tidak ada bukti yang menunjukkan `status_active` identik/sinonim dengan `status_user_id`; keduanya field terpisah. Pemetaan/penyelarasan keduanya = keputusan terbuka.

## 5. Lingkungan UAT terisolasi (bukti isolasi)

- **Database UAT**: MySQL `school_access_uat` (disposable) â€” terpisah dari operasional `backdata_administrations` dan dari DB tes in-memory.
- **Koneksi efektif diverifikasi sebelum migrasi**: `php artisan tinker --env=uat` â†’ `env=uat driver=mysql db=school_access_uat host=127.0.0.1`.
- **Tanpa config/route cache** (`bootstrap/cache/config.php` & `routes-v7.php` tidak ada).
- **Migrasi dijalankan hanya** pada DB UAT (`php artisan migrate --env=uat --force`) â€” termasuk 3 migration 01a. DB operasional tidak disentuh.
- **Fixture sintetis**: `UatSeeder` (bukan `DatabaseSeeder`). Akun: `uat.admin`, `uat.operator`, `uat.kepsek`, `uat.waka`, `uat.inactive` (5 akun). Kata sandi **tidak** ditulis di dokumen terlacak; diambil dari env `UAT_SEED_PASSWORD`. Peran di-backup oleh `AccessControlSeeder`.
- **Frontend UAT** hanya mengakses API lokal via `VITE_API_BASE_URL` (fallback URL lama dipertahankan).
- **Guard DB tes otomatis TIDAK dikendurkan** â€” tetap sqlite in-memory + fail-safe.
- **URL & operasional**:
  - API UAT: `http://127.0.0.1:8001` â€” jalankan: dari `laravel-api`: `php artisan serve --host=127.0.0.1 --port=8001 --env=uat`; hentikan: `Ctrl+C` (atau hentikan proses php pada port 8001).
  - Frontend UAT: `http://127.0.0.1:4001` â€” jalankan: dari `vue-app`: `npx vite --mode uat --host 127.0.0.1 --port 4001`; hentikan: `Ctrl+C`.
  - Kredensial uji: pemilik menyetel sendiri `UAT_SEED_PASSWORD` lalu menjalankan `php artisan db:seed --class=UatSeeder --env=uat --force` (lihat `.env.uat.example`). Kata sandi **tidak** dicatat di dokumen.
- Kedua server UAT **sudah dihentikan** setelah verifikasi. Server lain pada port 8000 (pra-ada, bukan milik sesi ini) dibiarkan utuh.

## 6. Status keluar subfase

- Scope 01a selesai dan terverifikasi otomatis (34 tes) + UAT browser/HTTP. Entrypoint UI `roles`/`abilities`/`access.assign` aktual. Perbaikan IDOR/atribut akun/audit tersedia.
- **BLK-02 sebagian**: endpoint aktual ditegakkan; modul surat/izin/pembelajaran hanya katalog (belum ada enforcement nyata, tidak diklaim end-to-end).
- **BLK-03 tetap**: `DatabaseSeeder` masih memuat identitas pribadi (tidak diperbaiki pada langkah ini).
- Browser: **PASS** untuk skenario yang dijalankan pada data/lingkungan sintetis; skenario modul surat/izin/pembelajaran NOT RUN (fitur belum ada).

## 7. Risiko, blocker dan keputusan

- **BLK-01 (isolasi DB uji) TERTUTUP** untuk SQLite in-memory.
- **BLK-02 (enforcement backend) sebagian tertutup**: endpoint aktual (master/siswa/pegawai/roles/abilities/accounts) telah ditegakkan. Modul yang belum dibangun (surat/izin/pembelajaran) hanya punya katalog kemampuan, **belum** enforcement nyata.
- **BLK-03 (seeder identitas asli) tetap**: `DatabaseSeeder` masih memuat identitas pribadi. Perbaikan seeder = scope 01c/lain, **belum** dilakukan. Fixture tes & UAT sintetis sudah dipakai.
- Risiko: (a) `Gate::before` menambah 1 query per user per request (di-cache per instance); (b) `status_user_id` belum ditegakkan (hanya `status_active`); (c) migration auto-map menyentuh `role_user` user legacy â€” reversible via `down()` tanpa menghapus users; (d) SQLite tidak menguji concurrency/unique MySQL secara penuh â†’ perlu uji MySQL terisolasi nanti; (e) ekspor siswa masih klien (lihat Â§4b).
- Keputusan terbuka/menunggu konfirmasi pemilik: status kebijakan register & pemblokiran nonaktif (Â§4d); makna `status_user_id` (Â§4e); reset password end-to-end; CORS/Sanctum deployment; strategi cabut token vs sesi.

## 8. Working tree (belum commit)

**Tracked modified**: CHANGELOG.md, PROJECT_CONTEXT.md, docs/{ACCESS-CONTROL,API-CONTRACTS,DATABASE,DECISIONS,REQUIREMENTS,TESTING}.md, roadmap/ROADMAP.md, reports/feature-update/{HANDOFF,INDEX}.md, laravel-api/{.gitignore,phpunit.xml,config/app.php,routes/api.php,routes/auth.php,database/factories/UserFactory.php,database/seeders/DatabaseSeeder.php,app/Http/Kernel.php,app/Http/Requests/Auth/LoginRequest.php,app/Models/User.php,app/Traits/Loggable.php,app/Http/Controllers/{UserController,FirstLoginController}.php,tests/{Pest.php,CreatesApplication.php},tests/Feature/Auth/{AuthenticationTest,RegistrationTest}.php}, vue-app/{.gitignore,src/router/index.js,src/layout/AppMenu.vue,src/apiLogin/index.js}.
**Untracked new**: laravel-api/{.env.testing,.env.uat.example,database/seeders/UatSeeder.php,app/Http/Controllers/{RoleController,AbilityController,AccessAssignmentController}.php,app/Http/Middleware/EnsureUserIsActive.php,app/Models/{Role,Ability}.php,app/Providers/AccessServiceProvider.php,app/Support/AbilityCatalog.php,database/migrations/2029_07_20_00000{1,2,3}_*.php,database/seeders/AccessControlSeeder.php,tests/Feature/Access/{AuthorizationTest,DatabaseIsolationTest}.php,tests/Support/CreatesAccessFixtures.php}, vue-app/src/views/pages/master/Roles.vue, reports/feature-update/{PHASE-00-RUNTIME-AUDIT.md,evidence/uat-roles.png}.
**Untracked lokal (di-ignore, tidak akan di-commit)**: laravel-api/.env.uat, vue-app/.env.uat.

Commit/push/merge/deploy: **NOT DONE** (tidak diotorisasi). Perubahan pemilik pada CHANGELOG.md dipertahankan.

## 9. Sinkronisasi dokumen
Diperbarui: REQUIREMENTS, ACCESS-CONTROL, DATABASE, API-CONTRACTS, TESTING, DECISIONS, PROJECT_CONTEXT, ROADMAP, CHANGELOG, INDEX, HANDOFF, dan laporan ini.

## 10. Langkah berikutnya (belum dijalankan)
- Konfirmasi pemilik: makna `status_user_id`.
- Subfase **01b** (API env/error auth/CORS/guard Vue) atau **01c** (validator fixture, FK/unique MySQL terisolasi, seed sintetis, reset password end-to-end).
- Perbaikan `DatabaseSeeder` (ganti identitas asli dengan sintetis) direkomendasikan sebelum data operasional.

---

# Penyelesaian Fase 01a (keputusan pemilik DEC-14, disetujui 2026-10-14 WIB)

## 11. Outcome penyelesaian

### 11a. Keputusan disetujui & disinkronkan
DEC-14 (APPROVED, **disetujui 2026-10-14 WIB**) mencatat: register publik ditutup; akun `status_active=false` ditolak login + request berikutnya; ekspor siswa via `students.export` (frontend + backend, ikut lingkup data); pemetaan legacy `Admin`â†’Admin Sistem, `User` **tidak** otomatis Operator, tak dikenal â†’ default deny; UI English. Keputusan OPEN: reset password end-to-end, bootstrap pengguna pertama, CORS/Sanctum deployment, strategi cabut token vs sesi.

### 11b. Ekspor siswa (backend endpoint + frontend)
- **Endpoint baru** `GET /api/students/export` (`app/Http/Controllers/StudentExportController.php`): auth:sanctum + `active` + `students.view` + `students.export`.
- Lingkup sama dengan daftar (tahun ajaran + filter `status/entry/rombel/search`); **seluruh hasil filter** (tanpa paginasi) â€” **dengan syarat** jumlah baris â‰¤ batas.
- **Batas baris (koreksi akhir)**: bila hasil filter melebihi batas (default 10.000; `config('students.export_max_rows')`, dapat diturunkan via env `STUDENTS_EXPORT_MAX_ROWS`), ekspor **DITOLAK `422`** dengan pesan English (jumlah baris, batas, ajakan mempersempit filter) â€” **tidak memotong hasil secara diam-diam**. Ekspor hanya berhasil bila hasil filter â‰¤ batas. (Kode lama memotong senyap di `MAX_ROWS`; telah diganti.)
- Kolom **eksplisit**: Name, Gender, Birthplace, Birthdate, NIK, NIS, NISN, School Rombel, Student Entry, Student Status. Tidak mengekspor seluruh model/relasi.
- Identitas sebagai teks: leading zero dipertahankan; koma/kutip/newline RFC 4180; formula injection dinetralkan (prefix apostrof).
- Frontend `Student.vue`: tombol Export mengunduh via endpoint (bukan `dt.exportCSV()`); tombol tampil hanya bila `students.export` (via `authStore.can(...)`); pesan `422` dari backend ditampilkan ke pengguna. Pencabutan izin berlaku pada request berikutnya (ditegakkan backend).
- Batasan yang dicatat: `students.export` mengendalikan **berkas unduhan**, bukan mencegah penyalinan data yang sudah boleh dilihat.

### 11c. Pemetaan legacy diperbaiki
Migration `2029_07_20_000003` diubah: hanya `Admin`â†’`admin-sistem`. `User` tidak lagi otomatis ke `operator-sekolah`; peran pengguna lama ditetapkan manual admin. `down()` hanya menghapus penautan auto-map `Admin` yang belum ditetapkan manual (`assigned_by` null). Tidak mengubah akun operasional dan tidak menghapus penetapan peran eksplisit. Karena migration ini belum pernah diterapkan ke operasional, koreksi dilakukan pada file yang sama (bukan menambah migration penghapus yang berisiko).

### 11d. UI English
- `Roles.vue`: judul, tabel, dialog, tombol, pesan error â†’ English; label kemampuan dipisah dari identifier (`ABILITY_LABELS`), grup diberi label (`GROUP_LABELS`), tag "sensitive".
- Nama tampilan peran (DB, `AbilityCatalog`): System Administrator, School Operator, Principal, Vice Principal for Student Affairs. Identifier/slug/fillable tidak berubah.
- `Accounts.vue`: ditambah **Assign Roles** dialog (English) memakai `access.assign`.
- `Student.vue`: tombol Export + notifikasi English.
- `authStore.js`: `abilities` dari `check-auth` + getter `can()`.
- Tidak memasang i18n/language switcher (di luar scope). Dokumentasi proyek tetap Bahasa Indonesia.

## 12. Evidence penyelesaian

| ID | Metode/command | Hasil | Status |
|---|---|---|---|
| P-01 | `php artisan test` | **49 passed (119 assertions)** (34 + 13 ekspor + 2 legacy) | PASS |
| P-02 | `php artisan test tests/Feature/Access/StudentExportTest.php` | 13 passed (36 assertions) | PASS |
| P-03 | `./vendor/bin/pint --test <file berubah>` | PASS (termasuk StudentExportController, config/students.php, StudentExportTest) | PASS |
| P-04 | `npm run build` | `âœ“ built` | PASS |
| P-05 | `npx eslint Roles.vue` | **0 problems** (3 warning lama dirapikan) | PASS |
| P-06 | `npx eslint authStore.js` | 0 errors (CRLF warning saja) | PASS |
| P-06b | `npx eslint Student.vue` | 22 error â€” **pre-existing** (baseline 23; perubahan ini mengurangi 1, bukan menambah) | PASS (baseline) |
| P-07 | HTTP UAT: operator export | `GET /api/students/export?school_year_id=1` â†’ 200 `text/csv`, kolom eksplisit, leading zero `0000000000000001`, formula `'=FORMULA TEST` dinetralkan, kutip `""quoted""` | PASS |
| P-08 | HTTP UAT: waka export | â†’ **403** (punya view, tanpa export) | PASS |
| P-09 | Playwright: unduhan CSV | Klik Export â†’ unduhan `students_1.csv` via endpoint 200; isi CSV benar | PASS |
| P-10 | Playwright: tombol per izin | Operator melihat tombol Export; Wakil Kesiswaan **tidak** | PASS |
| P-11 | Playwright: UI English | `/master/roles` menampilkan "Roles & Abilities", role English, dialog "Configure Abilitiesâ€¦", "Select all", "sensitive" (bukti: `evidence/uat-roles-en.png`) | PASS |
| P-12 | SQL UAT: audit tanpa rahasia | `activities` leak `password/remember_token` = **0 baris**; `role_user` audit berisi hanya action/user_id/role | PASS |
| P-13 | SQL UAT: leading zero + formula di CSV | Terbukti pada file `evidence/uat-students-export.csv` | PASS |
| P-14 | Migrasi pada DB operasional | **tidak dijalankan** | NOT RUN |
| P-15 | **HTTP UAT: batas baris** | `STUDENTS_EXPORT_MAX_ROWS=2`, 3 baris terfilter â†’ **422** `{"message":"The export matches 3 rows, which exceeds the limit of 2. Please narrow your filters ..."}` | PASS |
| P-16 | **Tes otomatis batas baris** | `export is rejected when the filtered result exceeds the row limit` (422 + pesan English) & `export succeeds when the filtered result is within the row limit` (200, semua baris ada) | PASS |

Isolasi: seluruh tes otomatis di SQLite `:memory:`; HTTP/Playwright di MySQL UAT `school_access_uat` (sintetis). DB operasional `backdata_administrations` tidak disentuh. UAT DB di-drop+recreate (disposable) sebelum seed.

## 13. Working tree penyelesaian
Lihat Â§14.

## 14. Working tree (belum commit) â€” daftar aktual `git status`

**Dimodifikasi (tracked, 35)**: CHANGELOG.md, PROJECT_CONTEXT.md, docs/{ACCESS-CONTROL,API-CONTRACTS,DATABASE,DECISIONS,REQUIREMENTS,TESTING}.md, roadmap/ROADMAP.md, reports/feature-update/{HANDOFF,INDEX}.md, laravel-api/{.gitignore,phpunit.xml,config/app.php,routes/{api,auth}.php,database/factories/UserFactory.php,database/seeders/{DatabaseSeeder,UatSeeder}.php,database/migrations/2029_07_20_000003_seed_and_map_roles.php,app/Http/{Kernel.php,Requests/Auth/LoginRequest.php,Controllers/{UserController,FirstLoginController}.php},app/Models/User.php,app/Traits/Loggable.php,app/Support/AbilityCatalog.php,tests/{Pest.php,CreatesApplication.php,Feature/Auth/{AuthenticationTest,RegistrationTest}.php}}, vue-app/{.gitignore,src/{apiLogin/index.js,router/index.js,layout/AppMenu.vue,stores/authStore.js,views/pages/master/Accounts.vue,views/pages/public/Student.vue}}.

**Baru (untracked, 26)**: laravel-api/{.env.testing,.env.uat.example,config/students.php,app/Http/Controllers/{RoleController,AbilityController,AccessAssignmentController,StudentExportController}.php,app/Http/Middleware/EnsureUserIsActive.php,app/Models/{Role,Ability}.php,app/Providers/AccessServiceProvider.php,app/Support/AbilityCatalog.php,database/migrations/2029_07_20_00000{1,2,3}_*.php,database/seeders/{AccessControlSeeder,UatSeeder}.php,tests/Support/CreatesAccessFixtures.php,tests/Feature/Access/{AuthorizationTest,DatabaseIsolationTest,StudentExportTest}.php}, vue-app/src/views/pages/master/Roles.vue, reports/feature-update/{PHASE-00-RUNTIME-AUDIT.md,PHASE-01A-ACCESS.md,evidence/{uat-roles-en.png,uat-students-export.csv}}.

Catatan koreksi: **`StudentExportTest.php` hanya UNTRACKED** (sebelumnya keliru dicantumkan di kedua kategori). `App\...\AbilityCatalog.php` adalah **untracked** (file baru). `config/students.php`, `RoleController`, `AbilityController`, `AccessAssignmentController`, `StudentExportController`, `EnsureUserIsActive`, `Role`, `Ability`, `AccessServiceProvider` juga **untracked**.

**Lokal (di-ignore, bukan deliverable)**: laravel-api/.env.uat, vue-app/.env.uat. Berkas bukti `evidence/*.png|csv` memuat **hanya data sintetis** dan tidak memuat kredensial/.env.

Commit/push/merge/deploy: **NOT DONE**. Perubahan pemilik pada CHANGELOG.md dipertahankan.

## 15. Batas pengujian penyelesaian
- Automated PASS: **49 tes** (34 akses/auth + 13 ekspor termasuk 2 batas baris + 2 legacy). Browser PASS: UI English, tombol per izin, unduhan CSV via endpoint terlindungi (data & lingkungan sintetis). HTTP PASS: ekspor (200/403/422 batas), check-auth, audit, legacy.
- **NOT RUN**: modul surat/izin/pembelajaran (belum ada); migrasi/backup pada DB operasional; reset password end-to-end; pengujian concurrency MySQL.
- Persetujuan manual pemilik: PENDING.

## 16. Blocker/keputusan tersisa
- **BLK-03 (seeder `DatabaseSeeder` memuat identitas asli) TETAP TERBUKA** (tidak diperbaiki pada langkah ini).
- `status_user_id` belum ditegakkan; keputusan terbuka lain (Â§11a).

## 17. Batas persetujuan (penting)
- Pemilik **menyetujui kebijakan** pada DEC-14 (register ditutup, akun nonaktif ditolak, ekspor via `students.export`, pemetaan legacy, UI English).
- Persetujuan kebijakan tersebut **BUKAN** penerimaan manual atas seluruh implementasi Fase 01a. Penerimaan/approval manual menyeluruh berstatus **PENDING** sampai pemilik menyelesaikan checklist akhir.
- Tetap **OPEN** sesuai scope masing-masing: reset password end-to-end, bootstrap pengguna pertama, deployment CORS/Sanctum, strategi pencabutan token.
- **BLK-03** tetap OPEN (tidak diperbaiki pada langkah koreksi ini).

## 18. Koreksi akhir (2026-10-15)
1. **Tanggal DEC-14** dikoreksi menjadi **2026-10-14 WIB** secara konsisten di DECISIONS.md, PROJECT_CONTEXT.md, ROADMAP.md, ACCESS-CONTROL.md, CHANGELOG.md, HANDOFF.md, dan laporan ini. Tanggal migration/identifier teknis **tidak** diubah.
2. **Batas ekspor 10.000 baris**: perilaku diubah dari potong senyap menjadi **tolak `422`** dengan pesan English; ditambah `config/students.php`, `maxRows()`, dan 2 tes batas (P-15/P-16). Dokumentasi "seluruh hasil filter" diperjelas: ekspor hanya berhasil bila hasil â‰¤ batas.
3. **Verifikasi**: `php artisan test` â†’ 49 passed; `pint --test` PASS; `npm run build` PASS; HTTP UAT batas 422 PASS (DB `school_access_uat`, sintetis). Tidak mengulang seluruh uji browser (hanya perubahan tanggal dokumentasi + kode ekspor yang diverifikasi).

## 19. Perbaikan Defect UAT Operator â€” Pemisahan Identitas dari Administrasi Akun (2026-10-15)

### 19a. Temuan & Rantai Penyebab (Bukti Console UAT)
Saat pemilik melakukan pengujian UAT dengan akun `uat.operator`, ditemukan serangkaian kegagalan:
1. `ListUsersOnlineWidget.vue` di dashboard memanggil `GET /api/users` tanpa memeriksa kemampuan `users.view`, menghasilkan response 403 Forbidden dan `Uncaught (in promise) AxiosError`.
2. `authStore.js` `getUser()` memanggil `GET /api/users` dengan tujuan mencari data akun sendiri dari seluruh daftar pengguna. Bagi operator tanpa `users.view`, endpoint menolak 403, menyebabkan `AllUsers` bernilai `null`.
3. `AppUsers.vue` membaca `.name` dan `.status_user` dari `null`, dan `AppMenu.vue` membaca `.type_user_id` dari `null`, menghasilkan `TypeError: Cannot read properties of null` di dalam render function Vue.
4. Fungsi logout di `AppUsers.vue` bergantung pada `saveUser.value.id` dari daftar pengguna tersebut; ketiadaan data membuat alur logout bermasalah.
5. `AppTopbar.vue` memunculkan warning unhandled directive karena directive PrimeVue `v-styleclass` belum diregistrasi di `main.js`.
6. `apiLogin/index.js` memiliki fallback diam-diam ke alamat IP hardcoded `http://26.117.240.38:4001`.

### 19b. Perbaikan yang Diterapkan
1. **Pemisahan Identitas Sesi**:
   - `authStore.js`: identitas sesi diambil dari kontrak ringan `GET /api/check-auth` (minimal payload). Tidak ada lagi pemanggilan `GET /api/users` untuk mencari profil sendiri.
   - Ditambahkan getter `displayName`, `roles`, dan method `can(ability)`.
   - Profil lengkap akun sendiri dipisahkan ke `fetchSelfProfile()` yang mengakses `GET /api/user` (hanya digunakan oleh `Profile.vue`).
2. **Perlindungan Widget Pengguna**:
   - `ListUsersOnlineWidget.vue`: diproteksi dengan `v-if="canViewUsers"` (`can('users.view')`). Bila tidak memiliki izin, widget tidak di-mount dan `fetchDataUsersOnline()` tidak memanggil `/api/users`.
   - Ditambahkan penanganan error eksplisit (`try/catch`) dan status loading/empty tanpa unhandled promise rejection.
3. **Penyempurnaan Render & Logout**:
   - `AppUsers.vue`: membaca nama dan peran langsung dari store identitas sesi (`userName`, `roles`).
   - Logout dilakukan langsung via sesi backend (`POST /logout`), tidak bergantung pada ID dari daftar pengguna administratif.
   - `authStore.logOut()` menjalankan `this.$reset()` untuk membersihkan seluruh state sesi lokal, mencegah kebocoran state antar-akun.
   - Pemisahan kode HTTP 401 (session expired) dari 403 (forbidden): 403 akibat ketiadaan izin fitur tidak lagi membatalkan sesi.
4. **Menu Berbasis Kemampuan Bernama**:
   - `AppMenu.vue`: beralih sepenuhnya ke kemampuan bernama (`requires`), menghapus pemeriksaan numerik inline `type_user_id` (`saveUser <= item.access`).
   - `router/index.js`: guard rute `/master` menggunakan kemampuan `users.view`, bukan `type_user_id == 2`.
5. **Registrasi Directive PrimeVue**:
   - `main.js`: mengimpor `StyleClass` dari `primevue/styleclass` dan mendaftarkan `app.directive('styleclass', StyleClass)`.
6. **Penataan Konfigurasi API UAT**:
   - `apiLogin/index.js`: menghapus seluruh fallback diam-diam ke IP lama. Jika `VITE_API_BASE_URL` tidak tersedia atau tidak valid, aplikasi memunculkan pesan error konfigurasi English yang jelas dan membatalkan request.
   - Interceptor axios membedakan error koneksi jaringan dari penolakan sesi/kredensial.
   - `Login_2.vue`: membedakan kegagalan koneksi jaringan ("Could not reach the API server...") dari penolakan kredensial (422 "Invalid username or password.").
   - `package.json`: menambahkan script `"dev:uat": "vite --mode uat --host 127.0.0.1 --port 4001"`.

### 19c. Hasil Verifikasi Browser & HTTP Bertahap
Semua verifikasi dijalankan pada lingkungan UAT sintetis (`127.0.0.1:8001`, DB `school_access_uat`, SPA `127.0.0.1:4001`):

| Peran | Alur yang Diuji | Hasil Aktual | Status |
|---|---|---|---|
| **Operator** (`uat.operator`) | Login â†’ dashboard â†’ refresh â†’ menu â†’ export â†’ logout | Dashboard: 0 console errors, request ke `/api/users` = **0**. Menu Admin & widget online users tidak tampil. Export: berhasil mengunduh `students_1.csv` (200). Logout: berhasil kembali ke `/auth/login`, sesi berakhir (`authenticated: false`). | **PASS** (bukti: `evidence/uat-operator-dashboard.png`, `evidence/uat-operator-profile-dropdown.png`) |
| **Admin** (`uat.admin`) | Login â†’ dashboard â†’ widget users â†’ export â†’ logout | Dashboard: `GET /api/users` berhasil (200), widget online users tampil dengan 5 akun. Menu Admin tampil lengkap. Export CSV berhasil (200). Logout berhasil. | **PASS** |
| **Principal** (`uat.kepsek`) | Login â†’ dashboard â†’ menu â†’ export â†’ logout | Dashboard: 0 console errors, tidak meminta `/api/users`. Tombol Export tampil di `/public/students`, unduh CSV berhasil (200). Logout berhasil. | **PASS** |
| **Wakil Kesiswaan** (`uat.waka`) | Login â†’ dashboard â†’ menu â†’ cek export â†’ direct call â†’ logout | Dashboard: 0 console errors, tidak meminta `/api/users`. Tombol Export **tidak tampil** di `/public/students`. Direct request `GET /api/students/export?school_year_id=1` â†’ **403**. Direct request `GET /api/users` â†’ **403**. Logout berhasil. | **PASS** |
| **Topbar Interaction** | Klik Dark/Light toggle, Menu toggle, Notification, Messages, Profile dropdown | Seluruh tombol merespons interaksi dengan **0 errors, 0 warnings** di console browser. Directive `v-styleclass` berjalan tanpa warning. | **PASS** |
| **Pergantian Akun** | Transisi antar-sesi Operator â†” Admin â†” Principal â†” Waka | State sesi dibersihkan sempurna setiap logout (`$reset()`). Tidak ada data atau kemampuan akun sebelumnya yang terbawa. | **PASS** |

## 20. Koreksi Defect Inisialisasi Identitas Pasca-Login (2026-10-15)

### 20a. Bukti Reproduksi & Investigasi Penyebab
1. **Reproduksi dari kondisi logout (UAT sintetis)**:
   - Login `uat.operator` memunculkan modal dialog `Authentication Error — Failed to validate user session.`
   - Console browser merekam: `Error: User data not found. at authenticated (Login_2.vue:144:20) at async submitLogin (Login_2.vue:229:13)`.
   - Namun session cookie Laravel (`POST /login` 204) sudah berhasil dibuat, sehingga saat halaman di-refresh, `router.beforeEnter: authNav` menjalankan `fetchIdentity()` dan langsung mengizinkan pengguna masuk ke dashboard.
2. **Penyebab terbukti (Rantai 3 Titik)**:
   - **Ekspektasi struktur lama vs baru**: `Login_2.vue` membaca properti `authStore().authUser`. Pada refactoring `5f18c06`, `authStore` beralih ke state `identity` dan getter `user` dari payload `GET /api/check-auth`. Properti `authUser` dihapus tanpa getter kompatibilitas, sehingga bernilai `undefined` dan memicu `throw new Error("User data not found.")`.
   - **Timing pemuatan identitas**: `POST /login` Laravel mengembalikan 204 No Content tanpa payload identitas. `Login_2.vue` perlu menunggu `await useAuthStore.fetchIdentity()` selesai dan mengecek `currentUser` sebelum menyatakan sukses dan navigasi.
   - **Potensi race condition request guest**: Jika ada request `check-auth` guest yang berjalan lambat di jaringan, respons unauthenticated yang tiba belakangan berpotensi menimpa state `identity` login jika tidak ada penanda urutan request. Selain itu, form login belum memiliki proteksi submit ganda (`isSubmitting`) dan variabel dialog di-assign string mentah yang mematikan reaktivitas.

### 20b. Perubahan yang Diterapkan
1. **`vue-app/src/stores/authStore.js`**:
   - Menambahkan getter kompatibilitas `authUser` dan `AllUserAssign` yang merujuk konsisten ke `identity`.
   - Menambahkan sequence tracker `_requestId` pada `fetchIdentity()`: bila request ID yang kembali bukan request ID aktif saat itu, respons usang dibuang sehingga tidak dapat menimpa sesi login aktif.
   - `fetchIdentity()`, `getUser()`, dan `checkLogin()` mengembalikan `this.identity` (atau `null`).
2. **`vue-app/src/views/pages/auth/Login_2.vue`**:
   - Menambahkan `isSubmitting` guard pada `submitLogin` dan atribut `:loading="isSubmitting" :disabled="isSubmitting"` pada tombol submit untuk mencegah submit ganda.
   - Menggunakan `ref` reaktif konsisten (`.value`) untuk teks judul dan pesan dialog modal.
   - Memanggil `await useAuthStore.fetchIdentity()` dan memvalidasi `currentUser` serta `hasLogin` sebelum transisi ke `firstLogin()` dan navigasi.
   - Menampilkan pesan error English yang spesifik: koneksi API server, kredensial tidak cocok (422), akun dinonaktifkan (422), dan kegagalan inisialisasi sesi.
3. **`laravel-api/app/Http/Requests/Auth/LoginRequest.php` & `EnsureUserIsActive.php`**:
   - Menyelaraskan pesan penolakan akun nonaktif ke English: `"Your account is inactive. Please contact the administrator."`.
4. **Pengujian deterministik**:
   - `vue-app/tests/auth-race-condition.test.mjs`: tes Node terisolasi untuk membuktikan getter konsisten dan respons guest lambat tidak menimpa sesi login aktif.
   - `laravel-api/tests/Feature/Auth/AuthenticationTest.php`: menambah tes kontrak `check-auth` pasca-login, penolakan pesan nonaktif English, dan unauthenticated check-auth.

### 20c. Hasil Pengujian Lengkap
- **Automated backend tests**: `php artisan test` → **51 passed (131 assertions)** di SQLite `:memory:`.
- **PHP style check**: `./vendor/bin/pint --test` → PASS pada seluruh berkas PHP yang diubah.
- **Frontend unit / race test**: `node --test vue-app/tests/auth-race-condition.test.mjs` → **3 passed (0 failed)**.
- **Frontend build**: `npm run build` → PASS (`✓ built in 42s`).
- **ESLint**: `npx eslint` → **0 errors** pada `authStore.js` dan `Login_2.vue`.
- **Playwright Browser UAT sintetis**:
  1. `uat.operator` + `password` → langsung masuk Dashboard tanpa refresh (0 console error, 0 `/api/users` calls, widget online users tersembunyi).
  2. Refresh di sesi `uat.operator` → Dashboard tetap utuh dan sesi valid.
  3. Logout `uat.operator` → kembali ke `/auth/login`, sesi berakhir.
  4. `uat.admin` + `password` → langsung masuk Dashboard tanpa refresh (widget online users tampil dengan 5 akun, menu Admin lengkap).
  5. `uat.kepsek` + `password` → langsung masuk Dashboard tanpa refresh (dropdown menampilkan "UAT Kepala Sekolah" & "kepala-sekolah").
  6. `uat.waka` + `password` → langsung masuk Dashboard tanpa refresh (dropdown menampilkan "UAT Wakil Kesiswaan" & "wakil-kesiswaan").
  7. `uat.operator` + `wrong-password` → ditolak 422, modal English "These credentials do not match our records."
  8. `uat.inactive` + `password` → ditolak 422, modal English "Your account is inactive. Please contact the administrator."
- **Penerimaan manual pemilik**: **APPROVED untuk seluruh checklist Fase 01a** (retest login §21 + pengujian akses/ekspor §22).

## 21. Persetujuan Manual Pemilik — Retest Login 01a Nomor 1–7 (15 Oktober 2026 WIB)

### 21a. Rincian Persetujuan Manual
Persetujuan manual diberikan langsung oleh pemilik aplikasi pada tanggal **15 Oktober 2026 WIB**, mengacu pada hasil implementasi dan pengujian commit `5f4308d` ("fix(phase-01a): correct post-login identity initialization") pada lingkungan UAT sintetis:

1. **Operator login langsung ke Dashboard**: Form login `uat.operator` langsung berhasil divalidasi dan diarahkan ke Dashboard (`/`) tanpa perlu refresh halaman atau memunculkan error di console. → **APPROVED**.
2. **Refresh mempertahankan sesi**: Pengguna me-refresh halaman Dashboard dan sesi tetap aktif secara konsisten. → **APPROVED**.
3. **Profil Operator**: Dropdown profil topbar menampilkan identitas nama ("UAT Operator Sekolah") dan peran ("operator-sekolah") dengan benar dari kontrak ringan `check-auth`. → **APPROVED**.
4. **Logout**: Tombol logout mengakhiri sesi backend dan mengembalikan pengguna ke halaman `/auth/login`. → **APPROVED**.
5. **Admin login**: Akun `uat.admin` dapat login langsung ke Dashboard dengan menu administrasi lengkap dan widget online users yang menampilkan data akun. → **APPROVED**.
6. **Akun nonaktif ditolak**: Login `uat.inactive` ditolak server dengan pesan error berbahasa English yang sesuai ("Your account is inactive. Please contact the administrator."). → **APPROVED**.
7. **Password salah ditolak**: Login dengan password salah ditolak server dengan pesan error berbahasa English yang sesuai ("These credentials do not match our records."). → **APPROVED**.

### 21b. Pembedaan Bukti Manual Pemilik vs Verifikasi Agen
- **Persetujuan Manual Pemilik**: 7 butir di atas adalah pengujian yang dilakukan dan divalidasi langsung oleh pemilik aplikasi di lingkungan UAT browser.
- **Verifikasi Otomatis Agen**: Seluruh hasil automated testing (51 tes PHPUnit/Pest di DB SQLite in-memory terisolasi, 3 tes deterministik Node di `vue-app/tests/auth-race-condition.test.mjs`, build Vite `npm run build`, linting ESLint 0 errors, dan penjelajahan Playwright) adalah bukti pelengkap dari agen kode dan **terpisah** dari persetujuan manual pemilik.

### 21c. Batasan Persetujuan Retest Login
Persetujuan retest login ini **secara tegas dibatasi hanya pada alur login dan inisialisasi sesi pasca-login (nomor 1–7)** dan **TIDAK** memperluas penerimaan ke seluruh modul Fase 01a. Pada saat pencatatan ini, checklist akses/ekspor (`#5–#11`, `#13`, `#14`) belum disetujui; statusnya kemudian **ditutup pada §22** (persetujuan tambahan 2026-10-15 WIB).
- **Status Blocker & Keputusan Lain**:
  - **BLK-03** (`DatabaseSeeder` masih memuat identitas asli) **tetap OPEN** (dilarang dijalankan untuk pengujian).
  - Keputusan autentikasi lanjutan (makna `status_user_id`, reset password end-to-end, bootstrap pengguna pertama, deployment CORS/Sanctum, strategi cabut token vs sesi) **tetap OPEN**.

## 22. Persetujuan Manual Pemilik — Pengujian Tambahan Akses & Ekspor 01a (15 Oktober 2026 WIB)

### 22a. Rincian Persetujuan Manual
Persetujuan manual tambahan diberikan langsung oleh pemilik aplikasi pada **15 Oktober 2026 WIB**, melengkapi persetujuan retest login (§21, mengacu `5f4308d`). Pengujian tambahan ini mengacu pada hasil yang sudah dicatat pada commit `bea4ccd`:
1. **Matriks Roles & Abilities English**: Halaman matriks Roles & Abilities tampil dalam bahasa English. → **APPROVED** (memetakan checklist panduan #5).
2. **Tambah & simpan `students.export`**: Admin berhasil menambahkan dan menyimpan `students.export` untuk Waka. → **APPROVED** (checklist #6).
3. **Izin tambahan berlaku pada sesi berjalan**: Izin tambahan berlaku pada sesi Waka yang tetap login; ekspor berhasil. → **APPROVED** (checklist #7).
4. **Pencabutan izin berlaku**: Pencabutan izin berlaku pada request berikutnya; ekspor ditolak `403`. → **APPROVED** (checklist #8, mencakup #10).
5. **Ekspor sesuai filter tahun ajaran**: Operator/Principal berhasil mengekspor sesuai filter tahun ajaran; nol awal identitas tetap utuh. → **APPROVED** (checklist #9).
6. **Batas ekspor**: Ekspor melebihi batas ditolak `422` dengan pesan English tanpa CSV terpotong. → **APPROVED** (checklist #11).
7. **Interaksi topbar**: Interaksi topbar berfungsi tanpa error/warning console. → **APPROVED** (checklist #13).
8. **Bahasa UI**: UI yang disentuh dalam scope 01a menggunakan English. → **APPROVED** (checklist #14).

### 22b. Pemetaan Nomor (Berbasis Isi, Bukan Menyamakan Nomor)
Penomoran berasal dari **dua checklist berbeda**: retest login pemilik (§21) dan pengujian tambahan akses/ekspor (§22). Pemetaan ke nomor panduan dilakukan **berdasarkan isi pemeriksaan**:

| Butir pengujian tambahan pemilik | Isi pemeriksaan | Nomor panduan |
|---|---|---|
| 1 | Matriks Roles & Abilities English | #5 |
| 2 | Admin menambah & menyimpan `students.export` untuk Waka | #6 |
| 3 | Izin tambahan berlaku di sesi Waka yang tetap login; ekspor berhasil | #7 |
| 4 | Pencabutan izin berlaku pada request berikutnya; ekspor ditolak 403 | #8 (+#10) |
| 5 | Operator/Principal ekspor sesuai filter tahun ajaran; nol awal utuh | #9 |
| 6 | Ekspor melebihi batas ditolak 422 English; tanpa CSV terpotong | #11 |
| 7 | Interaksi topbar tanpa error/warning console | #13 |
| 8 | UI dalam scope 01a memakai English | #14 |

### 22c. Pembedaan Bukti & Batasan
- **Bukti manual pemilik**: 8 butir di §22a adalah **pernyataan pengujian dan persetujuan manual pemilik**, bukan screenshot/log tambahan yang dibuat agen.
- **Verifikasi agen**: uji otomatis (51 Pest/PHPUnit, 3 unit Node race, build Vite, ESLint, Playwright) tetap bukti pelengkap yang **terpisah** dari persetujuan manual.
- **Batasan**: persetujuan ini **hanya** mencakup scope checklist Fase 01a dan **BUKAN** persetujuan deployment maupun modul yang belum tersedia (surat/izin/pembelajaran). Subfase lain pada Fase 01 (**01b**, **01c**) tetap **NOT STARTED**.
- **BLK-03** dan keputusan autentikasi lanjutan tetap **OPEN**.

### 22d. Status Penerimaan 01a
Seluruh nomor checklist panduan manual Fase 01a (**#1–#14**) kini **APPROVED** pemilik. Fase 01a **DITERIMA** dalam scope checklist; namun ini **tidak** menandai keseluruhan Fase 01 selesai (01b/01c belum dikerjakan).
