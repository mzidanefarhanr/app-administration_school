# Hak akses

## Baseline
API mayoritas hanya auth:sanctum. Beberapa controller akun membedakan tipe secara inline, tetapi bukan perlindungan menyeluruh. Policy tersedia sebagai scaffold; pembatasan menu/router Vue tidak mengamankan API. Akun aktif dan cakupan profil harus diperiksa server.

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
