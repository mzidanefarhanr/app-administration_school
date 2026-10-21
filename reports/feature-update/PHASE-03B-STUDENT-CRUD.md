# Laporan Subfase 03b — CRUD Siswa, Optimistic Locking, Arsip Administratif, dan UI Form

- Tanggal WIB: 2026-10-21
- Subfase: **03b — CRUD dan Form Siswa**
- Branch: `main`
- Backend baseline: commit `e77f335` (sudah di-push, tidak di-amend)
- Deliverable UI baseline: commit `cbd25bd`
- Status keseluruhan Subfase 03b: **DITERIMA PEMILIK** — penerimaan manual #1–#13 APPROVED 2026-10-21 WIB mengacu baseline `cbd25bd`
- Dokumen review manual: `reports/feature-update/PHASE-03B-MANUAL-REVIEW.md` (checklist #1–#13, status pemilik APPROVED)

---

## 1. Ringkasan Perubahan

### A. Backend (commit `e77f335`, tidak diubah pada sesi ini)
- `POST /api/students`, `GET /api/students/{id}`, `PUT /api/students/{id}`, `DELETE /api/students/{id}`, `POST /api/students/{id}/restore`.
- Optimistic locking `version` pada update/arsip/restore; versi usang → HTTP 409.
- Arsip administratif: soft delete siswa + `archived_at`/`archive_reason` pada `student_rombels`; status Mutasi Keluar dan `latest_student_exit_date` **tidak** diubah.
- Restore tidak mengaktifkan kembali rombel lama (`archived_at` dipertahankan).
- Audit allowlist; `archive_reason`, data medis, kontak, dan penghasilan tidak masuk audit.

### B. Frontend (sesi ini)
File yang diubah:
- `vue-app/src/views/pages/public/Student.vue`
  - Tombol **New Student** dan **Archived** (hanya `students.manage`).
  - Kolom **Action**: View Detail (semua `students.view`), Edit & Archive (`students.manage`).
  - Dialog **Add New Student / Edit Student Details** dengan section English: Personal Details, Identification & Documents, Address & Contact, Parents & Guardian, Assistance & Welfare.
  - Dialog **Student Detail** (read-only, termasuk Record Version).
  - Dialog **Archive Student** dengan alasan administratif wajib + peringatan bahwa rombel dinonaktifkan administratif (bukan mutasi keluar).
  - Dialog **Archived Students** + tombol **Restore**.
  - Error HTTP 422 inline di bawah field yang bersangkutan.
  - HTTP 409: dialog **Data Conflict Detected**; input pengguna dipertahankan; tombol **Reload Latest** mengambil data terbaru dari server, **Keep Editing** menutup dialog tanpa menimpa input.
  - `isSubmitting` mencegah submit ganda dan menampilkan loading pada tombol.
  - Ekspor CSV existing dipertahankan. Pagination server & chart tetap scope 03c.
- `vue-app/src/main.js`
  - Registrasi directive `v-tooltip` (PrimeVue Tooltip) untuk tombol aksi ikon.

---

## 2. Bukti Konkurensi 03b (MariaDB disposable `school_restore_drill_uat`)

Ditambahkan di `laravel-api/tests/Feature/Student/MariaDbStudentIntegrationTest.php`:

1. **`version-guarded updates accept exactly one writer, bump version once, and keep audit consistent`**
   - Dua request update dengan expected version sama (`1`), meniru `StudentController::update` (`SELECT ... FOR UPDATE` + guard `version` + audit dalam transaksi).
   - Hasil: request pertama 200, request kedua 409 (`current_version=2`); `version` naik tepat sekali; nama pemenang bertahan; hanya satu baris audit `Updated` dan tidak memuat nama pihak yang kalah.
2. **`archived student never ends with an active rombel membership`**
   - Siswa dengan rombel aktif diarsipkan (soft delete + `archived_at`), lalu dicoba aktivasi rombel lain di tahun yang sama.
   - Hasil: aktivasi ditolak trigger (`Cannot create active rombel membership for an archived student.`); jumlah keanggotaan aktif akhir = **0**.

Tes konkurensi rombel 03a tetap ada dan bukan pengganti bukti di atas.

---

## 3. Hasil Pengujian Aktual

| Pemeriksaan | Perintah | Hasil aktual |
|---|---|---|
| Suite Student | `php artisan test tests/Feature/Student` | **55 passed (228 assertions)** — MariaDB 7, Age 5, Crud 24, Migration 8, RombelConstraint 6, RombelResolution 5 |
| Regresi backend penuh | `php artisan test` | **209 passed (946 assertions)** |
| Pint file PHP berubah | `./vendor/bin/pint --test tests/Feature/Student/MariaDbStudentIntegrationTest.php` | **PASS** |
| Unit test frontend | `node --test` (dari `vue-app`) | **15 passed (0 failed)** |
| Build frontend | `npm run build` | **PASS** (788 modules; warning chunk size > 500 kB sudah ada sebelumnya) |
| ESLint read-only (`Student.vue`) | `npx eslint src/views/pages/public/Student.vue` | **22 errors, 2609 warnings** (working tree CRLF). Perbandingan blok commit (LF, konfigurasi sama, lihat §5) |

Catatan: angka 53 (24+5+8+6+5+5) pada laporan sebelumnya sudah tidak berlaku setelah 2 integration test konkurensi 03b ditambahkan; total suite Student sekarang **55**.

---

## 4. Bukti Browser UAT (Playwright MCP, sesi agen)

Lingkungan: API `http://127.0.0.1:8001`, SPA `http://127.0.0.1:4001`, DB `school_access_uat` (disposable), akun `uat.operator` (manage) dan `uat.kepsek` (view-only).

| Skenario | Hasil aktual |
|---|---|
| Login `uat.operator` | PASS — masuk Dashboard tanpa error |
| Form create tampil (English) | PASS — section Personal Details / Identification & Documents / Address & Contact / Parents & Guardian / Assistance & Welfare |
| 422 inline create (NIK `123`) | PASS — pesan `NIK must be exactly 16 digits.` di bawah field NIK; dialog tetap terbuka dan input dipertahankan |
| Create valid (NIK 16 digit, NIS `UAT-BROWSER-001`) | PASS — toast `Student Created`; dialog Student Detail terbuka dengan Record Version `1` |
| Edit memuat data server | PASS — nama, NIK, NIS, tempat lahir terisi; version dari server |
| 422 inline edit (NISN seed `0001`) | PASS — pesan `NISN must be exactly 10 digits.` (data sintetis lama tidak memenuhi aturan ketat; bukan defect 03b) |
| Konflik 409 dua tab | PASS — tab B menyimpan lebih dulu (version naik); submit tab A menampilkan `Data Conflict Detected` dengan pesan English; input tab A dipertahankan |
| Reload Latest | PASS — dialog konflik tertutup, form berisi data terbaru server, simpan berikutnya sukses (`Student Updated`) |
| Arsip dengan alasan | PASS — toast `Student Archived`; record hilang dari daftar tahun ajaran (rombel dinonaktifkan administratif) |
| Daftar arsip | PASS — menampilkan nama, NIK, NIS, alasan `UAT browser archive test` |
| Restore | PASS — toast `Student Restored`; daftar arsip menjadi kosong; rombel lama tetap non-aktif (record tidak kembali ke daftar tahun ajaran, sesuai DEC-21) |
| Kontrol izin `uat.kepsek` | PASS — New Student, Archived, Edit, dan Archive tidak tampil; hanya View Detail; Export mengikuti kemampuan `students.export` aktual akun |
| View Detail (view-only) | PASS — dialog detail menampilkan identitas siswa |

Data UAT sintetis yang berubah selama UAT:
- Dibuat: `UAT Browser Invalid Nik` (NIK `9876543210123456`, NIS `UAT-BROWSER-001`).
- `Synthetic Student One` diubah menjadi `Synthetic Student One (B)` (NISN diperbaiki ke 10 digit), diarsipkan dengan alasan `UAT browser archive test`, lalu di-restore. Rombel lamanya tetap non-aktif sesuai DEC-21.
- Tidak ada akses ke database operasional `backdata_administrations`.

---

## 5. Keterbatasan, Klasifikasi Bukti, dan Catatan

- **Klasifikasi bukti**: verifikasi otomatis backend/frontend dan browser UAT di atas dijalankan **oleh agen** pada server/DB UAT lokal. Ini **terpisah** dari penerimaan manual pemilik.
- **Penerimaan manual pemilik**: **APPROVED** — checklist #1–#13 dinyatakan berhasil oleh pemilik pada 21 Oktober 2026 WIB mengacu baseline `cbd25bd`.
- **ESLint**: hasil terakhir `npx eslint src/views/pages/public/Student.vue src/main.js` pada working tree = **22 errors, 2609 warnings — bukan PASS**. Perbandingan blok commit ada di §6.
- **Deployment**: agen tidak menjalankan perintah deploy apa pun. Ketiadaan `.github/workflows` **tidak** dijadikan kesimpulan bahwa tidak ada auto-deploy.
- **Penerimaan ini bukan izin deployment, bukan izin akses data operasional, dan bukan otorisasi merge/force-push/scheduler.**
- UI tidak membuat/mengubah keanggotaan rombel; pembuatan siswa baru belum otomatis muncul di daftar per tahun ajaran sampai ada keanggotaan rombel. Penempatan rombel bukan scope 03b (diusulkan ditangani/deskripsikan pada 03c).
- Pagination server dan rekap chart tetap scope 03c.
- Tidak ada file tes Playwright di repo; UAT browser dijalankan melalui Playwright MCP pada server UAT yang sudah ada. Tidak menambah dependensi Playwright ke `package.json`.
- `console.txt` dan `vue-app/test-results/` tetap untracked (artefak lokal).

---

## 6. Perbandingan Lint Terarah (baseline vs perubahan, konfigurasi sama)

Dijalankan dari `vue-app` dengan konfigurasi ESLint repo yang sama, membandingkan **blob commit** (LF, agar setara) sehingga tidak terpengaruh konversi CRLF working tree.

| Berkas | `e77f335` (sebelum UI) | `cbd25bd` (setelah UI) | Selisih |
|---|---|---|---|
| `src/views/pages/public/Student.vue` | 22 errors, 172 warnings | 22 errors, 228 warnings | errors **0**, warnings **+56** |
| `src/main.js` | 0 errors, 66 warnings | 0 errors, 69 warnings | errors **0**, warnings **+3** |

Kesimpulan berbasis perbandingan ini (bukan asumsi):
- **Tidak ada error tambahan**; jumlah error tetap 22 pada `Student.vue` dan 0 pada `main.js`.
- Warning bertambah 59, seluruhnya gaya Prettier/CRLF pada baris-baris baru.
- Hasil tersebut bukan PASS. Perbaikan error terkait (mis. `no-unused-vars` lama) direncanakan sebagai pekerjaan tersendiri tanpa auto-format massal dan tanpa menonaktifkan aturan; belum dieksekusi.
