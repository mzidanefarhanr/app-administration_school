# Baseline source awal

Tanggal: 14 Oktober 2026 (WIB). Status: SOURCE REVIEW. Ini laporan penyusun dokumen, bukan hasil pelaksanaan Fase00 OpenCode.
Repo sekolah: main @ b02a87f0d57adffec297ed465a8afcd8e8d7ec59. Commit terakhir bertema sub-menu siswa Demographics/Administration/Relationships. HEAD remote diperiksa sama saat persiapan paket. Database hidup/browser tidak diperiksa.

## Inventaris dan bukti
| ID | Prioritas | Temuan source | Sumber | Fase |
|---|---|---|---|---|
| B-01 | Kritis sebelum penggunaan | Resource utama hanya auth:sanctum; authorization operasi/record belum menyeluruh | laravel-api/routes/api.php; UserController; scaffold Policies | 01 |
| B-02 | Tinggi | firstlogin dapat menerima ID/status dari request tanpa pembatasan kepemilikan pada handler | FirstLoginController.php | 01a |
| B-03 | Tinggi | CRUD siswa/membership/pegawai belum selesai; request siswa authorize false | StudentController; StudentRombelController; EmployeeController; Store/UpdateStudentRequest | 03–04 |
| B-04 | Tinggi | Raw getOriginal pada audit dapat mencakup hash password; Student/Membership belum memakai trait | Traits/Loggable.php; Models/User/Student/StudentRombel | 02/03 |
| B-05 | Tinggi | Rollback Updated menggunakan class model sebagai DB table; log manual berasal request | ActivityController::store/update | 02a |
| B-06 | Tinggi | Tidak ditemukan backup/restore operasional; scheduler kosong | Console/Kernel.php; composer.json; routes | 02 |
| B-07 | Tinggi | Test DB sqlite :memory: dikomentari; suite tidak terbukti terisolasi | phpunit.xml | 01c |
| B-08 | Tinggi | Seeder memuat identitas menyerupai pribadi | database/seeders/DatabaseSeeder.php | 00/01c |
| B-09 | Menengah | API HTTP hardcode; interceptor terpasang pada axios global, bukan instance apiLogin; response bisa tidak ada | vue-app/src/apiLogin/index.js | 01b |
| B-10 | Menengah | Dashboard widget Orders/Revenue dan chart data demo | Dashboard.vue; components/dashboard/StatsWidget.vue; ChartDataWidget_1.vue | 11 |
| B-11 | Menengah | Teachers/Employees/Letters Form ke # | layout/AppMenu.vue | 04/07 |
| B-12 | Menengah | Payload relasi siswa luas dan query get semua | StudentController; StudentRombelController | 03/12 |
| B-13 | Menengah | FK NIK nullable vs membership; model-schema mismatch beberapa atribut | migration students/student_rombels; models | 03a |
| B-14 | Menengah | User name unik; password create tidak divalidasi memadai; version check akun belum atomik | UserController.php | 01 |
| B-15 | Menengah | Console error/warn/log disenyapkan pada PROD | vue-app/src/main.js | 12b |
| B-16 | Menengah | authNav ada pada beforeEnter parent; audit perpindahan sibling/role stale diperlukan | router/index.js; stores/authStore.js | 01b |
| B-17 | Menengah | Register bawaan tidak menyediakan semua kolom users required; reset frontend route belum ditemukan | RegisteredUserController; users migration; Vue router | 01 |

Temuan di atas berasal source, tidak diberi runtime FAIL/PASS. Prioritas adalah usulan berbasis dampak, bukan bukti eksploitasi atau insiden.

## Kelebihan yang dipertahankan
Data siswa/keluarga/pendidikan/wilayah rinci; membership terpisah; tahun ajaran/rombel/wali kelas; rekap siswa dan chart/filter/CSV; beberapa master soft delete dan blocking referenced deletion; composable CRUD reusable; auth rate limit dan CSRF/session.

## Acuan App Gudang
Repo mzidanefarhanr/app-gudang, branch redesign/foundation @ 4260bcf87a4346a0a6e5b56ced8876f6ef7fec29. Dibaca: AGENTS, PROJECT_CONTEXT, README, CHANGELOG, roadmap/redesign-roadmap, reports/redesign/PHASE-00-BASELINE. Referensi proses: scope per fase, kontrak UI, realita source, evidence aktual, INDEX dan HANDOFF.
Beberapa teks dokumen acuan berbeda dari source/status terkini; karena itu pola diadaptasi, tidak disalin sebagai fakta. Paket tidak menyalin NIK/alamat/akun sekolah dari seed, source private Gudang, status PASS/approval, role 1–6 atau konfigurasi Blade.

## Verifikasi saat penyusunan
| Aktivitas | Status |
|---|---|
| Clone/read source sekolah dan HEAD | SOURCE REVIEW |
| Baca dokumen App Gudang melalui GitHub | SOURCE REVIEW |
| Membuat dan memeriksa struktur/link paket | Dilakukan penyusun; hasil detail pada VALIDATION.md |
| Install/run aplikasi sekolah | NOT RUN |
| php artisan test / Pint / frontend build | NOT RUN |
| Database hidup / migration / seed / restore | NOT RUN |
| Browser / manual pemilik | NOT RUN |
| Commit/push/merge/deploy repo | NOT RUN |

## Selanjutnya
OpenCode menjalankan Fase00 terhadap repo lokal/HEAD aktual, mencatat environment, inventory lengkap dan blocker. Laporan ini tidak menggantikan audit runtime atau menjadi klaim siap produksi.
