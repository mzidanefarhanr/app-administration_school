# Panduan Review Manual Subfase 02a — Integritas Audit Server

- Tanggal WIB: 2026-10-17
- Subfase: **02a — Audit Server dan Rollback Terbatas**
- Branch: `main`
- Status teknis: **IMPLEMENTED & AUTOMATED TESTS PASS (110/110)**
- Status checklist pemilik: **APPROVED (DITERIMA PEMILIK pada 19 Oktober 2026 WIB mengacu baseline cf94cae)**
- Batasan klaim: **Perlindungan integritas audit dan pencegahan modifikasi/rollback ditegakkan pada API aplikasi dan ORM Eloquent, bukan merupakan klaim kekebalan terhadap administrator sistem operasi atau administrator database langsung (root DB/OS admin).**

---

## 1. Persiapan Lingkungan UAT Lokal

Pastikan server UAT lokal aktif pada port `8001` (API) dan `4001` (SPA) menggunakan database disposable `school_access_uat`:
- **API UAT**: `http://127.0.0.1:8001` (`php artisan serve --host=127.0.0.1 --port=8001 --env=uat`)
- **SPA UAT**: `http://127.0.0.1:4001` (`npm run dev` / `vite --mode uat --host 127.0.0.1 --port 4001`)

Akun UAT sintetis yang tersedia (kata sandi sesuai sesi UAT Anda):
- `uat.admin` (System Administrator — memiliki `audit.view`)
- `uat.operator` (School Operator — **tidak** memiliki `audit.view` secara default)
- `uat.kepsek` (Principal — memiliki `audit.view`)
- `uat.waka` (Vice Principal for Student Affairs — **tidak** memiliki `audit.view`)

*Catatan pengujian API*: Cara paling andal menjalankan request POST/PUT/DELETE dengan sesi dan CSRF token yang valid adalah melalui **Developer Tools Console (F12)** pada tab browser SPA yang sedang login (memakai `fetch` yang otomatis menyertakan kredensial sesi Sanctum).

---

## 2. Checklist Pemeriksaan Manual

Status: **APPROVED** = pemilik telah memeriksa dan menyetujui hasil uji manual pada 19 Oktober 2026 WIB mengacu baseline `cf94cae`.

| # | Skenario Uji | Tindakan / Endpoint | Hasil yang Diharapkan | Status Pemilik |
|---|---|---|---|---|
| 1 | Akses Tamu (Unauthenticated) | Buka tab penyamaran (incognito) atau cURL: `curl.exe -s http://127.0.0.1:8001/api/activities` | Mengembalikan **HTTP 401 Unauthorized** (`{"message":"Unauthenticated."}`). Akses ditolak. | **APPROVED** (2026-10-19 WIB) |
| 2 | Otorisasi Akses Operator (Tanpa `audit.view`) | Login sebagai `uat.operator` di browser (`http://127.0.0.1:4001/auth/login`). Buka console browser (F12) lalu jalankan: `await fetch('http://127.0.0.1:8001/api/activities', { credentials: 'include', headers: { 'Accept': 'application/json' } }).then(r => r.status)` | Mengembalikan status **403 Forbidden** (`{"message":"This action is unauthorized."}`). Operator tidak dapat melihat log audit. | **APPROVED** (2026-10-19 WIB) |
| 3 | Pembacaan Audit oleh Administrator | Login sebagai `uat.admin` di browser. Buka console (F12) lalu jalankan: `const res = await fetch('http://127.0.0.1:8001/api/activities', { credentials: 'include', headers: { 'Accept': 'application/json' } }); const json = await res.json(); console.log(res.status, json);` | Mengembalikan status **200 OK**. Payload JSON memuat struktur paginasi `data.data`, field `id`, `table_name`, `record_id`, `information`, dan relasi `user` (`id`, `name`, `username`). Catat salah satu `id` audit nyata dari respons (misal `json.data.data[0].id`) untuk pengujian butir #5 dan #6. | **APPROVED** (2026-10-19 WIB) |
| 4 | Penolakan Pembuatan Audit Klien (Anti-Spoofing) | Di sesi `uat.admin`, jalankan di console (F12): `await fetch('http://127.0.0.1:8001/api/activities', { method: 'POST', credentials: 'include', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ table_name: 'users', record_id: 1, information: 'Created', new: { forged: true } }) }).then(r => r.status)` | Server menolak dengan **HTTP 405 Method Not Allowed** (`{"message":"Audit logs are immutable and cannot be created by clients."}`). Tidak ada log fiktif yang tercipta. | **APPROVED** (2026-10-19 WIB) |
| 5 | Penolakan Modifikasi & Rollback Klien | Di sesi `uat.admin`, gunakan ID audit nyata yang diperoleh dari butir #3: `const id = json.data.data[0]?.id || 1; await fetch(`http://127.0.0.1:8001/api/activities/${id}`, { method: 'PUT', credentials: 'include', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ information: 'Rollbacked_Updated' }) }).then(r => r.status)` | Server menolak dengan **HTTP 405 Method Not Allowed** (`{"message":"Audit logs are immutable. Rollback has been disabled."}`). Modifikasi/rollback ditolak. | **APPROVED** (2026-10-19 WIB) |
| 6 | Penolakan Penghapusan Audit Klien | Di sesi `uat.admin`, gunakan ID audit nyata: `const id = json.data.data[0]?.id || 1; await fetch(`http://127.0.0.1:8001/api/activities/${id}`, { method: 'DELETE', credentials: 'include', headers: { 'Accept': 'application/json' } }).then(r => r.status)` | Server menolak dengan **HTTP 405 Method Not Allowed** (`{"message":"Audit logs are immutable and cannot be deleted."}`). Rekaman audit tidak dapat dihapus via API. | **APPROVED** (2026-10-19 WIB) |
| 7 | Sanitasi Rahasia & Allowlist pada Mutasi User | Di menu Accounts (`/master/accounts`), edit akun `uat.operator`: ubah nama menjadi `UAT Operator Sekolah Updated` dan masukkan password baru valid (mis. `OperatorPass12345!`). Simpan. Lalu ambil log audit terbaru via console: `const res = await fetch('http://127.0.0.1:8001/api/activities', { credentials: 'include', headers: { 'Accept': 'application/json' } }); const latest = (await res.json()).data.data[0]; console.log(latest);` Periksa respons JSON mentah. | Kolom `before` dan `after` mencatat perubahan allowlist (`name`). Kolom `password`, `password_new`, `remember_token`, maupun string hash `$2y$` **absen total** dari respons JSON audit dan objek relasi pengguna `user`. Kembalikan nama ke `UAT Operator Sekolah` setelah verifikasi. | **APPROVED** (2026-10-19 WIB) |
| 8 | Audit Mutasi Pivot Hak Akses & Pemulihan | 1. Di menu `/master/accounts`, klik tombol Assign Roles pada akun `uat.waka`, tambahkan peran `School Operator`.<br>2. Periksa log audit: `const latest = (await (await fetch('http://127.0.0.1:8001/api/activities', { credentials: 'include', headers: { 'Accept': 'application/json' } })).json()).data.data[0]; console.log(latest);`<br>3. Cabut kembali peran `School Operator` dari `uat.waka` (restore state awal). | Pada langkah 2, tercipta rekaman audit dengan `table_name: 'role_user'`, `information: 'Created'`, dan payload `new` memuat peran `operator-sekolah`. Pada langkah 3, tercipta rekaman audit `information: 'Deleted'`. Peran `uat.waka` kembali seperti semula. | **APPROVED** (2026-10-19 WIB) |
| 9 | Batasan Perlindungan API Aplikasi | Konfirmasi pemahaman batasan | Memahami bahwa kontrol audit ditegakkan pada level aplikasi API Laravel dan ORM Eloquent, bukan proteksi terhadap root database/server administrator. | **APPROVED** (2026-10-19 WIB) |

---

## 3. Pemulihan State Pasca UAT (Post-Test Cleanup)

Bila pengujian butir #7 atau #8 mengubah data uji:
1. Pastikan nama akun `uat.operator` tetap `UAT Operator Sekolah`.
2. Pastikan akun `uat.waka` hanya memiliki satu peran: `wakil-kesiswaan` (cabut `operator-sekolah` bila masih ada).
3. Matriks kemampuan default peran pada `/master/roles` tetap pada baseline DEC-03/DEC-14.

---

## 4. Cara Memberikan Persetujuan Pemilik

Setelah Anda memeriksa butir-butir di atas pada lingkungan UAT lokal, kirim instruksi:
```text
Catat hasil pemeriksaan manual saya untuk subfase 02a pada kandidat <SHA>.
Hasil: checklist nomor #1–#9 APPROVED.
```
Hasil pemeriksaan manual Anda akan dicatat dalam commit dokumentasi tersendiri sesuai aturan repo.
