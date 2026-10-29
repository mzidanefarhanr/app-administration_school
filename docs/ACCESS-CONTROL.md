# Hak akses

## Baseline
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
