# Panduan Review Manual Subfase 02a — Integritas Audit Server

- Tanggal WIB: 2026-10-17
- Subfase: **02a — Audit Server dan Rollback Terbatas**
- Branch: `main`
- Status teknis: **IMPLEMENTED & AUTOMATED TESTS PASS (99/99)**
- Status checklist pemilik: **PENDING (Menunggu Pemeriksaan Manual Pemilik)**
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

---

## 2. Checklist Pemeriksaan Manual

Status: **PENDING** = menunggu verifikasi dan jawaban pemilik; **APPROVED** = pemilik telah menyetujui hasil uji manual.

| # | Skenario Uji | Tindakan / Endpoint | Hasil yang Diharapkan | Status Pemilik |
|---|---|---|---|---|
| 1 | Akses Tamu (Unauthenticated) | Buka URL API langsung: `GET http://127.0.0.1:8001/api/activities` (tanpa cookie/token sesi) | Mengembalikan **HTTP 401 Unauthorized** (`{"message":"Unauthenticated."}`). Akses ditolak. | **PENDING** |
| 2 | Otorisasi Akses Operator (Tanpa `audit.view`) | Login sebagai `uat.operator` di browser (`http://127.0.0.1:4001/auth/login`), lalu panggil `GET http://127.0.0.1:8001/api/activities` pada tab baru dengan sesi aktif | Mengembalikan **HTTP 403 Forbidden** (`{"message":"This action is unauthorized."}`). Operator tidak dapat melihat log audit. | **PENDING** |
| 3 | Pembacaan Audit oleh Administrator | Login sebagai `uat.admin` di browser, lalu panggil `GET http://127.0.0.1:8001/api/activities` pada tab baru | Mengembalikan **HTTP 200 OK** dengan format JSON paginasi (`data.data`), memuat informasi `id`, `table_name`, `record_id`, `information`, serta relasi pengguna `user` (`id`, `name`, `username`). | **PENDING** |
| 4 | Penolakan Pembuatan Audit Klien (Anti-Spoofing) | Kirim request POST ke endpoint aktivitas: `POST http://127.0.0.1:8001/api/activities` dengan payload JSON apa pun | Server menolak dengan **HTTP 405 Method Not Allowed** (`{"message":"Audit logs are immutable and cannot be created by clients."}`). Tidak ada log fiktif yang tercipta. | **PENDING** |
| 5 | Penolakan Modifikasi & Rollback Klien | Kirim request PUT/PATCH ke endpoint aktivitas: `PUT http://127.0.0.1:8001/api/activities/1` dengan payload rollback | Server menolak dengan **HTTP 405 Method Not Allowed** (`{"message":"Audit logs are immutable. Rollback has been disabled."}`). Rollback klien dinonaktifkan. | **PENDING** |
| 6 | Penolakan Penghapusan Audit Klien | Kirim request DELETE ke endpoint aktivitas: `DELETE http://127.0.0.1:8001/api/activities/1` | Server menolak dengan **HTTP 405 Method Not Allowed** (`{"message":"Audit logs are immutable and cannot be deleted."}`). Rekaman audit tidak dapat dihapus via API. | **PENDING** |
| 7 | Sanitasi Rahasia & Allowlist pada Mutasi User | Sebagai admin, ubah nama atau kata sandi akun pengguna via form Accounts (`/master/accounts`) atau API, lalu periksa rekaman pada tabel `activities` | Rekaman audit mencatat perubahan nama pada kolom `before` dan `after`. Kolom `password`, `password_new`, `remember_token`, atau hash bcrypt **100% absen** dari JSON audit. | **PENDING** |
| 8 | Audit Mutasi Pivot Hak Akses | Sebagai admin, tetapkan peran baru ke pengguna di `/master/accounts` atau ubah kemampuan peran di `/master/roles` | Tercipta rekaman audit dengan `table_name` bernilai `role_user` (untuk penetapan peran) atau `ability_role` (untuk perubahan matriks peran), memuat informasi peran dan kemampuan yang diubah tanpa rahasia. | **PENDING** |
| 9 | Batasan Perlindungan API Aplikasi | Konfirmasi pemahaman batasan | Memahami bahwa kontrol audit ditegakkan pada level aplikasi API Laravel dan Eloquent, bukan proteksi terhadap root database/server administrator. | **PENDING** |

---

## 3. Cara Memberikan Persetujuan Pemilik

Setelah Anda memeriksa butir-butir di atas pada lingkungan UAT lokal, kirim instruksi:
```text
Catat hasil pemeriksaan manual saya untuk subfase 02a pada kandidat <SHA>.
Hasil: checklist nomor #1–#9 APPROVED.
```
Hasil pemeriksaan manual Anda akan dicatat dalam commit dokumentasi tersendiri sesuai aturan repo.
