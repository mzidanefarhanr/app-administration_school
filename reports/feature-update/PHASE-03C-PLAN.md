# Rencana Kerja Subfase 03c — Daftar, Pencarian, Server Pagination, dan Rekap Siswa

- Tanggal WIB: 2026-10-21
- Subfase: **03c — Daftar dan Rekap Siswa**
- Mode: **Audit source aktual + perencanaan (READ-ONLY)**. Tidak ada perubahan kode/skema yang dieksekusi pada langkah ini.
- Status rencana: **DISETUJUI PEMILIK (DEC-22, 2026-10-21 WIB); diimplementasikan pada 03c**.
- Prasyarat: Subfase 03b **DITERIMA PEMILIK** (checklist #1–#13 APPROVED 2026-10-21 WIB, baseline `cbd25bd`); backend `e77f335`.
- Dokumen acuan: `AGENTS.md`, `ROADMAP.md`, `roadmap/phases/PHASE-03-STUDENTS.md`, `docs/REQUIREMENTS.md`, `docs/DATABASE.md`, `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `docs/DECISIONS.md`.

---

## 1. Baseline & Kondisi Source Aktual

1. **`StudentController::index`** (`laravel-api/app/Http/Controllers/StudentController.php`)
   - Mengembalikan **seluruh** siswa dengan eager load relasi lengkap via `Student::with([...])->get()` (tanpa paginasi).
   - Mendukung `?only_archived=1` (`onlyTrashed`) dan `?with_archived=1` (`withTrashed`).
   - Tidak ada pencarian, sort, atau filter tahun/rombel/status/entry.
2. **`StudentRombelController::show`** (`.../StudentRombelController.php`)
   - Daftar per **tahun ajaran** (parameter `{id}` = `school_year_id`), `whereNull('archived_at')`, eager load relasi.
   - Di-konsumsi dashboard `Student.vue` lewat `GET /api/student-rombels/{schoolYearId}`.
   - **Keterbatasan**: siswa tanpa keanggotaan rombel pada tahun terpilih **tidak muncul**; pencarian siswa bergantung penuh pada keanggotaan tahun ajaran.
3. **`StudentExportController::export`**
   - Filter: `school_year_id` (wajib), `status`, `entry`, `rombel`, `search`.
   - Batas baris `students.export_max_rows` (default 10000) → **menolak 422 tanpa memotong** (sudah sesuai target).
   - Kolom eksplisit; identitas sebagai teks; sanitasi formula injection.
4. **Frontend `Student.vue`**
   - DataTable filter/pagination **client-side** di atas `school_years_pick` (hasil per-tahun).
   - Chart direkap dari `school_years_pick` (computed `rekapData`, `updateChartData`).
   - Belum ada loading/empty/error state terpadu untuk daftar besar, belum ada guard respons usang untuk filter.
5. **Kontrak 03b yang harus dipertahankan**: CRUD/archive/restore + `version` (409), arsip administratif (`archived_at`/`archive_reason`), restore tidak mengaktifkan rombel lama, unique NIK/NIS/NISN termasuk record terarsip.

---

## 2. Tujuan & Ruang Lingkup 03c

1. **Server-side query**: pagination + pencarian + sort + filter (tahun ajaran, rombel, status, entry, penempatan, arsip).
2. **Definisi domain yang tegas** (lihat §3) untuk menghindari double-count dan ambiguitas.
3. **Siswa baru tanpa rombel tetap dapat ditemukan kembali** setelah refresh (tidak bergantung pada keanggotaan tahun ajaran).
4. **Rekap/chart dinamis** dengan definisi hitungan eksplisit.
5. **Ekspor server mengikuti filter yang disepakati**, izin `students.export`, batas baris menolak 422 (tanpa pemotongan diam-diam).
6. **Respons daftar memuat atribut minimum** yang dibutuhkan UI (bukan seluruh model/relasi).
7. **UI English**, state loading/empty/error, dan guard respons usang (request lama tidak menimpa filter terbaru).
8. **Matriks tes backend + browser UAT sintetis**.

---

## 3. Definisi Domain (usulan, butuh keputusan pemilik)

| Istilah | Definisi usulan |
|---|---|
| **Siswa aktif (daftar)** | Siswa dengan **tepat satu** keanggotaan rombel **aktif** pada tahun ajaran terpilih: `student_rombels.deleted_at IS NULL AND archived_at IS NULL AND (student_status_id = 1 OR NULL) AND latest_student_exit_date IS NULL`. |
| **Siswa terarsip** | `students.deleted_at IS NOT NULL` (soft delete 03b). Tidak dihitung pada daftar/rekap tahun aktif. |
| **Belum ditempatkan (unplaced)** | `students.deleted_at IS NULL` **dan** tidak memiliki keanggotaan rombel aktif pada tahun terpilih. Tetap dapat ditemukan pada mode pencarian siswa global. |
| **Keanggotaan rombel aktif** | Baris `student_rombels` dengan `student_status_id = 1` (Aktif) atau null, `deleted_at IS NULL`, `archived_at IS NULL`, `latest_student_exit_date IS NULL`. |
| **Keanggotaan rombel non-aktif** | Mutasi keluar/lulus/arsip atau punya `latest_student_exit_date`; dipertahankan sebagai riwayat dan **tidak** dihitung sebagai aktif. |

**Prinsip anti double-count**: rekap menghitung **jumlah siswa unik** (`students.id`), bukan jumlah baris `student_rombels`. Bila satu siswa memiliki beberapa baris (mis. historis + aktif), hanya keanggotaan aktif pada tahun terpilih yang dihitung; baris historis dikecualikan.

---

## 4. Spesifikasi Teknis (usulan)

### A. Endpoint Daftar (server pagination)
Usulan menambah dukungan paginasi pada `GET /api/students` (atau endpoint baru `/api/students/list` bila kontrak `index` existing dipertahankan):

| Parameter | Tipe | Keterangan |
|---|---|---|
| `page`, `per_page` | int | Paginasi; default & maksimum ditetapkan pemilik (usulan default 25, max 100) |
| `search` | string | Nama/NIK/NIS/NISN (case-insensitive) |
| `sort` | string | Allowlist: `name`, `nis`, `nisn`, `nik`, `created_at` |
| `direction` | string | `asc`/`desc` |
| `school_year_id` | int | Filter tahun (opsional untuk mode unplaced/global) |
| `rombel` | int | Filter rombel |
| `status`, `entry` | string | Filter status/entry |
| `placement` | enum | `active` (default), `unplaced`, `all` |
| `archived` | bool | Sertakan/hanya terarsip (`only_archived`) |

Respons: `{ data: [...], meta: { current_page, per_page, total, last_page } }` dengan **atribut minimum** (mis. `id, name, nik, nis, nisn, gender, birthdate, version, rombel, status, entry, archived`).

### B. Mode "Cari semua siswa"
- Mode pencarian yang **tidak** mewajibkan `school_year_id`, agar siswa baru (belum ditempatkan) tetap ditemukan meski belum punya rombel.
- Menjaga kontrak 03b: `version` disertakan agar Edit/Archive/Restore tetap bekerja dari hasil pencarian.

### C. Rekap/Chart
- Rekap per level/rombel dihitung dari **siswa unik** dengan keanggotaan aktif pada tahun terpilih.
- Gender/religion distribution menghitung siswa unik.
- Angka total = jumlah siswa unik aktif; bila `placement=all` tampilkan pemisahan (aktif vs unplaced) agar tidak mencampur definisi.

### D. Ekspor
- Ikuti filter yang disepakati (tahun/rombel/status/entry/search/placement) dan `students.view` + `students.export`.
- Batas baris tetap menolak **422** tanpa pemotongan diam-diam (pertahankan perilaku existing).
- Putuskan apakah ekspor mendukung "semua tahun" atau tetap wajib `school_year_id`.

### E. Frontend
- Ganti pagination/filter client-side menjadi server-driven.
- State: `loading`, `empty`, `error`; tombol retry.
- Guard respons usang: gunakan `requestId` (pola `authStore`) agar respons lama tidak menimpa hasil filter terbaru.
- Tombol Export mengirim filter aktif; tombol lain (New/Edit/Archive/Restore) tetap sesuai `students.view`/`students.manage`.
- Label English; pertahankan dialog 03b.

---

## 5. Kompatibilitas dengan 03b
- `PUT/DELETE/POST restore` tetap memakai `version` dari baris hasil daftar; setelah mutasi, daftar di-refresh dengan filter aktif.
- Record terarsip tetap dapat di-restore dari dialog **Archived**.
- Tidak mengubah semantik arsip/restore atau trigger DB.

---

## 6. Matriks Pengujian (rencana)

**Backend (`tests/Feature/Student/`):**
1. Paginasi: jumlah per halaman, halaman terakhir, `per_page` di atas maksimum ditolak/di-clamp (sesuai keputusan).
2. Pencarian: nama/NIK/NIS/NISN; gambar parsial; tidak case-sensitive; identitas sebagai string (leading zero utuh).
3. Sort: allowlist; `direction` invalid ditolak.
4. Filter: tahun, rombel, status, entry, `placement` (`active`/`unplaced`/`all`), `archived`.
5. Definisi: siswa unplaced muncul pada `placement=unplaced`/`all`; siswa terarsip tidak muncul pada daftar aktif; siswa dengan riwayat mutasi tidak terhitung ganda.
6. Rekap: hitungan siswa unik; tidak double-count saat ada beberapa baris keanggotaan.
7. Ekspor: filter konsisten dengan daftar; batas baris → 422; kolom tidak bertambah tanpa izin.
8. Atribut minimum: respons tidak membocorkan PII berlebih (mis. tanpa `history_illness`, `*_income`, `*_hp_num`).
9. Kompatibilitas 03b: version disertakan dan update/archive/restore dari baris hasil daftar sukses.

**Frontend (browser UAT sintetis):**
- Pencarian menemukan siswa baru **tanpa rombel** setelah refresh; filter/sort/pagination; state loading/empty/error; respons usang tidak menimpa hasil terbaru; export mengikuti filter; izin view-only.

---

## 7. Rencana Lint Terarah (bukan PASS otomatis)
- Bandingkan **blob commit** (LF) baseline vs perubahan dengan konfigurasi ESLint repo yang sama (menghindari noise CRLF working tree).
- Baseline referensi 03b: `Student.vue` 22→22 errors (172→228 warnings), `main.js` 0→0 errors (66→69 warnings).
- Untuk setiap berkas yang disentuh 03c, jalankan lint terarah dan catat selisih error/warning.
- **Perbaikan error terkait** (mis. `no-unused-vars` lama `students`, `viewStudentProfile`; `vue/no-side-effects-in-computed-properties` pada `tabItems`) direncanakan pada baris yang memang disentuh 03c — **tanpa** auto-format massal dan **tanpa** menonaktifkan aturan.
- Hasil lint dilaporkan apa adanya (bukan klaim PASS).

---

## 8. Keputusan Pemilik yang Diperlukan (blocking sebelum implementasi)
1. **Definisi "siswa aktif" & rekap**: setuju memakai definisi pada §3 (dihitung dari keanggotaan aktif, jumlah siswa unik, hindari double-count)?
2. **Mode "unplaced"**: tampilkan siswa belum ditempatkan sebagai filter di halaman Students, atau halaman terpisah?
3. **Paginasi**: ukuran default & maksimum per halaman.
4. **Ekspor**: tetap wajib `school_year_id`, atau mendukung "semua tahun"? Ikut filter `placement`?
5. **Penempatan rombel via UI**: apakah 03c mencakup aksi menempatkan siswa ke rombel (membuat/mengaktifkan keanggotaan)? **Rekomendasi agen: pisahkan ke subfase tersendiri** karena menyentuh constraint aktif/trigger/audit. Mohon keputusan.
6. **Visibilitas arsip di daftar utama**: arsip hanya di dialog Archived (03b) atau ada toggle di daftar utama?
7. **Atribut minimum** daftar: setujui daftar field pada §4A tanpa data sensitif?

---

## 9. Di Luar Scope 03c
- Penempatan/perubahan keanggotaan rombel (kecuali keputusan #5 menyatakan sebaliknya).
- Impor siswa massal.
- Perubahan skema database (rencana ini tidak menambah kolom).
- Deploy, scheduler, dan akses data operasional.

---

## 10. Rencana Commit & Validasi
- Commit rencana ini sebagai commit dokumentasi tersendiri (tanpa perubahan kode).
- Implementasi 03c dikerjakan **hanya setelah** pemilik menyetujui rencana dan keputusan §8.
- Bukti implementasi wajib: hasil runner backend aktual, lint terarah, build, dan browser UAT sintetis — dilaporkan apa adanya (NOT RUN bila tidak dijalankan).