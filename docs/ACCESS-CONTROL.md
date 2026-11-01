# Hak akses

## Correction addendum — 2026-10-29 (04b)

Current baseline `main` at `e281419` (corrections `dc5e031`, documentation `e281419`), pushed to `origin/main`. Authorization remains named backend Gate/Policy enforcement, not English labels or UI hiding. Teachers = Guru only (`Pendidik`), Employees = non-teachers (`Tenaga Kependidikan`), classified only by `employee_type_id`; canonical jobs including head-of-school positions are retained without job-title restrictions or role changes.

**Archived employee detail:** `GET /api/employees/{id}` uses `withTrashed()`; archived records require both **employees.view + employees.manage**, and a view-only actor receives **403**. Active operational detail continues to require view; sensitive identity/contact/family/education and expanded account identity require manage detail. Lists/exports contain name/NUPTK/gender and lists contain `account: {linked, user_id}` linkage metadata: earlier “no PII/no account data” wording was incorrect. CSV download still requires view + export; copying already-authorized operational data is not prevented by export permissions. Unresolved list names are null/blank. `Employment Start Date (TMT)` is the current CSV header.

Shared 17-domain/243-label JSON and frontend-only `display_name` enrichment preserve canonical references/IDs/user/proper names while displaying English. Passwords are stripped **before** cache enrichment; route-view key avoids stale Teachers/Employees rows/dialogs. Linking/unlinking does not create accounts or change roles/passwords; UAT preparation initializes only the initial five synthetic accounts, with no additional employee accounts.

New isolated SQLite UAT only: SPA/API `127.0.0.1:14022` / `127.0.0.1:18022`, root `.uat-phase04b/database.sqlite`, separate environment/storage and cookie `school_phase04b_session`, private ignored runtime/`UAT-ACCOUNTS.local.md`. Guarded launcher `scripts/phase04b-uat.php` (`init`/`prepare`/`status`) rejects URLs/caches/process overrides and runs normal migrations only; never root `artisan --env=uat`, old/main environment or MariaDB targets. No service/dependency changes or secret publication.

Operator browser DOM/API-readback checks cover teacher create/edit/stale conflict/archive/archived detail/restore/link/unlink, staff create, route types, English CSV and staff mobile fit/scroll. Separate HTTP sessions for `uat.waka`, `uat.kepsek`, `uat.operator` passed sensitive-detail, export, candidates/management and archived-detail restrictions; abilities remained unchanged after link/unlink. These are **not** owner acceptance or other-role browser UI coverage. Agent-side other-role browser UI automation, two-tab stale/link-conflict, print-preview/screenshot visual checks and 04b MariaDB DDL/InnoDB remain NOT RUN (available, excluded targets not accessed); owner acceptance does not convert them to PASS. **Owner #1–#18: APPROVED 2026-10-30 WIB per owner statement.** Full method/scope limits: `PHASE-04B-REPORT.md`.

## Baseline (historical pre-01a, not current enforcement)
API mayoritas hanya auth:sanctum. Beberapa controller akun membedakan tipe secara inline, tetapi bukan perlindungan menyeluruh. Policy tersedia sebagai scaffold; pembatasan menu/router Vue tidak mengamankan API. Akun aktif dan cakupan profil harus diperiksa server.

## Implementasi Fase 01a (aktual)
- Model akses: tabel `roles`, `abilities`, `ability_role`, `role_user`. Kemampuan bernama (mis. `students.export`) dipetakan ke peran; Admin Sistem mengaturnya lewat UI `/master/roles`.
- Penegakan terpusat: `App\Providers\AccessServiceProvider` mendaftarkan `Gate::before` yang mencocokkan kemampuan pengguna. Tidak ada bypass "admin boleh segalanya" — approve/sign hanya bila kemampuan benar-benar diberikan.
- Route dilindungi `can:<ability>`: master (`master.view`/`master.manage`), siswa (`students.view`/`students.manage`), pegawai (`employees.*`), `roles.*`, `abilities`, `access.assign`, `audit.view`. Middleware `active` menegakkan `status_active` pada setiap request.
- Peran awal (DEC-03, konfigurasi awal): Admin Sistem, Operator Sekolah, Kepala Sekolah, Wakil Kesiswaan. Hanya Kepala Sekolah yang awalnya punya `letters.approve`, `leaves.approve`, `letters.sign`. Admin Sistem TIDAK otomatis punya approve/sign.
- Perlindungan: FirstLogin hanya akun actor; atribut administratif akun tidak dapat diubah sendiri; eskalasi peran diri ditolak; admin pengelola akses terakhir dilindungi (deaktivasi/pencabutan); perubahan izin berlaku pada request berikutnya; audit perubahan peran tanpa rahasia.
- Register publik ditutup; `check-auth` hanya mengembalikan identitas minimal.
- Ekspor siswa: `GET /api/students/export` menegakkan `students.view` + `students.export`, lingkup sama dengan daftar (tahun ajaran + filter), kolom eksplisit, tanpa akses tambahan. Bila hasil filter melebihi batas (10.000 baris), ekspor ditolak `422` (bukan dipotong). Tombol di UI mengikuti izin aktual; pencabutan izin berlaku pada request berikutnya. Ekspor mengendalikan berkas, bukan kerahasiaan data yang sudah boleh dilihat.
- Keputusan: `docs/DECISIONS.md` DEC-14 (APPROVED 2026-10-14 WIB) — register ditutup, akun nonaktif ditolak, ekspor via `students.export`, pemetaan legacy `Admin`→Admin Sistem (User **tidak** otomatis Operator), UI English. `status_active` tidak disamakan dengan `status_user_id`.
- Pemetaan legacy: hanya `type_users.name='Admin'` yang dipetakan otomatis ke `admin-sistem`; `User`/tak dikenal/tanpa tipe → tanpa peran (default deny). Peran pengguna lama ditetapkan manual oleh admin; penetapan eksplisit tidak dihapus.
- Kemampuan modul yang belum dibangun (surat/izin/pembelajaran) tercatat sebagai **kemampuan direncanakan**; enforcement end-to-end belum diuji karena fiturnya belum ada. `students.export` kini punya titik penegakan nyata pada ekspor siswa.

## Implementasi Fase 04a (fondasi personalia)
- `App\Policies\EmployeePolicy` menegakkan kemampuan bernama: `viewAny`/`view` → `employees.view`; `create`/`update`/`delete`/`restore` → `employees.manage`; `forceDelete` selalu `false`.
- Route `/employee` (index/show → `employees.view`; lainnya → `employees.manage`) sudah memakai kemampuan ini sejak baseline `41f6e68`; 04a tidak mengubah route. Normalisasi `/employees` dan transisi consumer berada di 04b.
- Tidak ada perubahan penetapan peran/kemampuan pada 04a. Department/tipe pegawai tidak dipakai sebagai sumber otorisasi.

## Implementasi Fase 04b (CRUD personalia)
- **Enforcement backend (bukan sekadar menyembunyikan tombol)**: `/api/employees` index/show → `employees.view`; store/update/destroy/restore/link/unlink dan `account-candidates` → `employees.manage`; export → `employees.view` + `employees.export`. Daftar arsip (`only_archived=1`) juga mensyaratkan `employees.manage`; archived GET detail uses `withTrashed()` and requires **view + manage** (403 for view-only).
- **Pemisahan data sensitif**: daftar/ekspor memuat nama/NUPTK/gender; daftar memuat `account: {linked, user_id}`. Pemegang view tanpa manage menerima detail operasional aktif; NIK, NPWP, KK, ibu kandung, pasangan, kontak/alamat, pendidikan dan identitas akun diperluas hanya di manage detail. **Bukan klaim tanpa PII/data akun**; ekspor tidak menambah field sensitif tersebut.
- **Relasi akun terbatas**: 04b hanya menautkan akun `users` aktif yang belum tertaut profil lain; tidak membuat akun, tidak mereset password, dan tidak mengubah role/abilities. Lepas akun mengisi `account_unlinked_at` dan tidak mengaktifkan kembali fallback NIK legacy.
- **Audit**: perubahan domain dicatat server melalui allowlist `Loggable`; `name`, `nik`, dan `archive_reason` tidak termasuk atribut audit.
- **UI**: menu Teachers/Employees dan route `/public/teachers` + `/public/employees` memakai `requiresAbility: employees.view`; tombol New/Archived/Edit/Archive/Restore/Link/Export mengikuti kemampuan aktual. Pencabutan izin berlaku pada request berikutnya.
- **Tidak ada perubahan peran/kemampuan** pada 04b. `forceDelete` tetap ditolak.

## Implementasi Subfase 05a (impor siswa CSV)
- **Kemampuan baru `students.import`** (katalog `AbilityCatalog`, migration `2029_07_27_000002`) hanya diberikan ke peran **Admin Sistem** dan **Operator Sekolah** (DEC-25 D5). Tidak mengubah kemampuan lain, tidak membuat bypass "admin boleh segalanya".
- **Route matrix**: `GET /api/imports/students/template` → `students.view` (isi hanya header); `POST /api/imports/students`, `GET /api/imports/{batch}`, `POST /api/imports/{batch}/commit`, `GET /api/imports/{batch}/errors` → `students.import`. Guest `401`; tanpa kemampuan `403`.
- **Scope objek**: batch hanya dapat dibaca/di-commit/diunduh error-nya oleh `created_by` pembuat dan hanya `domain=students`; aktor berizin lain menerima **404** agar keberadaan batch tidak terungkap. Ini ditegakkan di server (bukan dengan menyembunyikan tombol).
- **Server-side validation**: berkas CSV/TXT UTF-8 ≤ 5 MB dan ≤ 2.000 baris; `mode` `insert`/`update`; validasi baris memakai `StudentRules` yang sama dengan form create/update. Commit `all-or-nothing` dalam transaksi + `lockForUpdate`; baris berubah/kedaluwarsa → `409` tanpa mutasi parsial. Audit domain tercatat server; berkas error tetap privat.
- **UI**: tombol Import di `Student.vue` hanya tampil bila `students.import`; dialog impor tidak menambah akses baru. Pencabutan izin berlaku pada request berikutnya.

## Implementasi Subfase 05b (impor pegawai & pilihan kolom ekspor)
- **Kemampuan baru `employees.import`** (katalog `AbilityCatalog`, migration `2029_07_28_000001`) hanya diberikan ke **Admin Sistem** dan **Operator Sekolah** (DEC-26). Tidak mengubah peran/kemampuan lain; template cukup `employees.view`.
- **Route matrix**: `GET /api/imports/employees/template` → `employees.view` (hanya header); `POST /api/imports/employees`, `GET /api/imports/employees/{batch}`, `POST .../commit`, `GET .../errors` → `employees.import`. Guest `401`; tanpa kemampuan `403`.
- **Scope objek**: batch impor pegawai hanya dapat dibaca/di-commit/diunduh error-nya oleh `created_by` pembuat dan `domain=employees`; aktor berizin lain atau domain lain menerima **404**.
- **Kolom ekspor sensitif**: `GET /api/employees/export/options` menyembunyikan metadata 15 kolom PII dari aktor tanpa `employees.manage`; `GET /api/employees/export` menolak **403** bila `columns` memuat kolom sensitif tanpa `employees.manage`. Default ekspor tetap 10 kolom operasional; ini pembatasan tambahan, bukan pengurangan izin `employees.export` yang sudah ada.
- **UI**: Teachers/Employees menampilkan Import CSV hanya bila `employees.import` (dan tidak di mode arsip); dialog ekspor hanya menampilkan kelompok sensitif bila opsi server mengizinkan. Pencabutan izin berlaku pada request berikutnya; tombol tersembunyi bukan pengganti enforcement server.

## Implementasi Subfase 05c (pemeriksaan kelengkapan dan review)
- **Tanpa ability baru**: baca memakai `students.view` (siswa) dan `employees.view` (pegawai); tulis review memakai `students.manage` dan `employees.manage` (DEC-28 D5). Tidak mengubah peran/kemampuan atau menambah bypass.
- **Route matrix**: `GET /api/data-quality/students/summary` + `GET /api/data-quality/students/issues` → `students.view`; `PUT /api/data-quality/students/reviews/{issueKey}` → `students.manage`; `GET /api/data-quality/employees/summary` + `GET /api/data-quality/employees/issues` → `employees.view`; `PUT /api/data-quality/employees/reviews/{issueKey}` → `employees.manage`. Guest `401`; tanpa kemampuan `403` (view-only tidak dapat menulis review).
- **Scope dan validasi review**: server memeriksa issue key, versi record subject/related, dan versi review; review siswa terikat `school_year_id`, review pegawai menolak `school_year_id`. Review tidak mengubah data domain maupun persentase, dan tidak melakukan merge/koreksi.
- **Privasi**: daftar/summary tidak memuat NIK/alamat/telepon/medis; audit review hanya metadata temuan. Review ignored tidak menyembunyikan issue baru karena issue key mengikat versi record.
- **UI**: menu dan route `/public/data-quality` memakai daftar ability `['students.view','employees.view']` (OR) melalui `routeAbility.mjs`/`canAny`; string ability tunggal pada route lain tetap berperilaku sama. Tombol review hanya tampil untuk pemegang manage; server tetap menegakkan `403`.

## Implementasi Subfase 06a (periode akademik, lock tahun, dan kelas)
- **Tanpa ability baru**: periode aktif dibaca dengan `students.view`; penetapan periode dan lock/unlock memakai `master.manage`; School Years tetap `master.view`/`master.manage`; Classes memakai `students.view`/`students.manage` (diselaraskan dengan API 06a); opsi wali kelas memakai `employees.view`. Tidak mengubah peran/kemampuan atau menambah bypass.
- **Route matrix**: `GET /api/academic/period` → `students.view`; `PUT /api/academic/period`, `POST /api/school-years/{id}/lock`, `POST /api/school-years/{id}/unlock` → `master.manage`; `GET/POST/PUT/DELETE /api/school-rombels` → `students.view`/`students.manage`; `GET /api/employees/teacher-options` → `employees.view`; assign/transfer → `students.manage`. Guest `401`; tanpa kemampuan `403` (principal view-only tidak dapat mengubah kelas/periode/lock).
- **Policy terpusat**: `SchoolRombelPolicy` (`students.view/manage`; restore/forceDelete `false`) dan `SchoolYearPolicy` (`master.view/manage`; `lock`/`unlock` membutuhkan `master.manage`). Penolakan objek tetap di server, bukan sekadar menyembunyikan tombol.
- **Batas objek**: wali kelas hanya profil `Pendidik` aktif; tahun locked menolak create/update/delete kelas dan assign/transfer di server; tahun aktif tidak dapat dikunci/dihapus; tahun locked tidak dapat dijadikan aktif/dihapus. Alasan lock/unlock wajib dan tercatat pada audit.
- **UI**: grup menu **Academic** dengan Classes (`students.view`) dan School Years (`master.view`); halaman Students menonaktifkan assign/transfer saat tahun terkunci. Tombol tersembunyi/nonaktif bukan pengganti enforcement server.

## Implementasi Subfase 06b (progresi akhir tahun)
- **Tanpa ability baru**: opsi, roster, riwayat, dan detail batch memakai `students.view`; preview dan commit memakai `students.manage`. Tidak mengubah peran/kemampuan atau menambah bypass.
- **Route matrix**: `GET /api/academic/progression/options`, `GET /roster`, `GET /batches`, `GET /batches/{id}` → `students.view`; `POST /preview`, `POST /commit` → `students.manage`; route UI `/public/progression` → `students.view`. Guest `401`; tanpa kemampuan `403` (principal view-only tidak dapat preview/commit).
- **Batas objek**: batch preview hanya dapat dilihat dan di-commit oleh pembuatnya (detail/list preview akun lain tidak terlihat/`403`); batch committed dapat dibaca semua pemegang `students.view`. Commit juga memeriksa token (hash, kedaluwarsa 30 menit) dan tahun ajaran (target unlocked untuk promote, sumber unlocked untuk graduate), bukan hanya tombol UI.
- **UI**: menu **Academic → Progression**; tombol/step preview/commit hanya tampil untuk `students.manage`; view-only melihat riwayat committed dan banner **View-only access**. Server tetap menegakkan `403`.

## Implementasi Subfase 06c (mutasi, pembatalan, rekap, dan koreksi periode locked)
- **Ability baru `students.correct-locked`** (sensitif, migration `2029_08_01_000003`) hanya diberikan ke peran **Admin Sistem** secara default; tidak mengubah pemetaan ability lain dan tidak membuat bypass. Koreksi memerlukan **dua** ability: `students.manage` **dan** `students.correct-locked`.
- **Route matrix**: `GET /api/academic/mutations/options|schools|recap` dan `GET /api/academic/mutations` → `students.view`; `GET /api/students/{id}/mutations` → `students.view`; `POST /api/students/{id}/mutations/in|out` dan `POST /api/students/{id}/mutations/{mutation}/cancel` → `students.manage`; `GET /api/students/{id}/locked-corrections` → `students.view`; `POST /api/students/{id}/locked-corrections` → `students.manage` + `students.correct-locked`. Guest `401`; tanpa ability `403`; view-only tidak dapat menulis.
- **Batas objek/waktu**: mutasi normal hanya pada tahun ajaran **unlocked**; koreksi hanya pada tahun **locked**. Server memeriksa siswa non-archived, kepemilikan membership, tahun/kelas yang sama, tanggal dalam rentang tahun dan tidak masa depan (Asia/Jakarta), NPSN sekolah non-deleted, serta versi siswa/membership/mutasi. Tahun ajaran tidak dapat dihapus selama memiliki mutasi, koreksi, kelas, atau batch.
- **Immutable log**: `student_mutations` dan `student_locked_corrections` tidak memiliki endpoint update/delete; pembatalan mengubah status log + memulihkan/mengarsipkan membership secara kompensatif dengan `409` itemized tanpa mutasi bila state berubah. Koreksi locked menulis snapshot before/after, aktor, alasan, dan approval reference.
- **UI**: section **Students → Mutations** (tahun aktif/locked, rekap empat angka, log berversi, pencarian siswa/sekolah, dialog In/Out/Cancel/Correct, timeline siswa). Tombol Cancel hanya untuk `students.manage` pada tahun unlocked; tombol Correct hanya untuk pemegang kedua ability pada tahun locked. Principal view-only melihat rekap/log/timeline tanpa tombol tulis; server tetap menegakkan `403`.

## Matriks target — usulan, bukan role yang sudah ada
| Kemampuan | Admin | Operator | Kepala sekolah | Guru/wali kelas |
|---|---|---|---|---|
| users.manage / roles.manage | Ya | Tidak | Tidak | Tidak |
| students.view / manage | Sesuai mandat | Ya | Ringkasan/detail sesuai kebutuhan | Hanya siswa yang ditugaskan |
| students.sensitive / export | Izin eksplisit | Izin eksplisit | Terbatas | Default tidak |
| employees.manage | Sesuai mandat | Ya | Ringkasan sesuai kebutuhan | Profil sendiri terbatas |
| letters.manage / disposition | Sesuai mandat | Ya | Sesuai mandat | Surat ditugaskan saja |
| letters.approve / issue | Tidak otomatis dari admin teknis | Jika didelegasikan | Ya | Tidak |
| leaves.request | Untuk diri sendiri | Input administrasi sesuai mandat | Diri sendiri | Diri sendiri |
| leaves.decide | Sesuai mandat | Jika didelegasikan | Ya | Sesuai scope wali kelas bila disepakati |
| teaching.journal.write | Tidak otomatis | Koreksi administrasi berizin | Review | Penugasan sendiri |
| audit.view / rollback | Eksplisit | Eksplisit terbatas | Review sesuai mandat | Tidak |
| backups.manage / restore | Eksplisit | Tidak default | Tidak default | Tidak |

Konfirmasi DEC-03 sebelum menjadikan matriks ini enforcement. Admin teknis bukan otomatis penandatangan surat. Siswa tidak perlu akun untuk masuk master data. Default deny untuk kemampuan tidak terdaftar.

## Implementasi
Ability bernama terpusat; policy memeriksa record scope termasuk tahun ajaran, kelas ditugaskan, kepemilikan dan data sensitif. Gunakan validasi FK untuk target yang benar-benar boleh dipilih pengguna. API response hanya atribut yang diperlukan; batasi export dan download juga.
Self profile/first login hanya mengubah akun actor; jangan menerima ID pengguna lain atau status aktif dari pengguna biasa. Jangan izinkan user menaikkan role/status diri sendiri. Lindungi admin terakhir dan akun actor dari penghapusan yang memutus operasi.
Pemetaan status_user_id/status_active dikonfirmasi dahulu; deactivation server mencabut sesi/token sesuai strategi yang dipilih. Evaluasi route register agar tidak menjadi pendaftaran terbuka tanpa kebijakan bootstrap.

## Uji minimum
Guest, akun nonaktif, setiap role target, objek sendiri, objek orang lain, siswa kelas lain, manipulasi ID/FK/payload, direct URL/API, export/download, perubahan role saat sesi hidup. Untuk setiap penolakan, assert data tidak berubah; UI hidden saja bukan PASS.
