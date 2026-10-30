# Rencana Subfase 05c — Pemeriksaan Kelengkapan

- Tanggal: 2026-10-31 (WIB). Branch: `main`. Baseline: `8b091cf` (penerimaan 05b); `main...origin/main` sinkron.
- Status: **PLAN APPROVED — DEC-28 (D1–D7) disetujui pemilik 2026-10-31 WIB ("Saya setuju D1-D7").** Implementasi 05c diotorisasi; merge/deploy tidak. Dokumen ini tetap rencana, bukan laporan implementasi.
- Scope: pemeriksaan kelengkapan **siswa** dan **pegawai**, daftar missing/invalid/duplikat/blocked, serta status review operator. **Rekap sekolah tidak termasuk** (DEC-12 masih PROPOSED, menunggu contoh format; penomoran subfase ditentukan kemudian).
- Dokumen acuan: `reports/feature-update/PHASE-05-PLAN.md` §7, `roadmap/phases/PHASE-05-IMPORT-EXPORT.md`, `docs/DECISIONS.md` (DEC-12, DEC-20, DEC-21, DEC-22, DEC-24, DEC-25, DEC-26, DEC-27), `docs/REQUIREMENTS.md` (FR-06), `docs/API-CONTRACTS.md`, `docs/ACCESS-CONTROL.md`, `docs/DATABASE.md`, `docs/TESTING.md`.
- Gate: pemilik menjawab **D1–D7** dan menyatakan persetujuan; keputusan dicatat sebagai **DEC-28**. **Selesai 2026-10-31 WIB — DEC-28 APPROVED ("Saya setuju D1-D7").**

---

## 1. Konteks dan tujuan

Subfase 05a dan 05b sudah diterima pemilik. Mesin impor siswa/pegawai sekarang dapat memasukkan data dalam jumlah besar, dan ekspor pegawai sudah dapat memilih kolom. Belum ada halaman atau API yang menjawab pertanyaan dasar operator:

1. Berapa siswa/pegawai yang datanya **lengkap** dan berapa yang belum, dengan denominator yang eksplisit?
2. Field apa saja yang kosong atau tidak valid, dan record mana yang harus dibuka untuk diperbaiki?
3. Apakah ada kandidat duplikat yang perlu ditinjau manusia, tanpa penggabungan otomatis?
4. Siapa yang sudah meninjau suatu temuan dan dengan alasan apa?

Tujuan 05c:

1. Menyediakan **pemeriksaan kelengkapan kontekstual** untuk siswa dan pegawai. Konteks minimum: penempatan siswa pada tahun ajaran terpilih dan tipe pegawai (Pendidik vs Tenaga Kependidikan).
2. Menampilkan **denominator, pembilang, dan persentase** yang dapat direkonsiliasi; siswa tanpa NIK dibedakan **incomplete** vs **blocked**.
3. Menyajikan daftar temuan yang dapat difilter dengan tautan ke record berizin. **Tidak ada auto-merge** berdasarkan nama.
4. Menyimpan **status review operator** secara teraudit dan berversi. Status review tidak mengubah kebenaran data; koreksi tetap melalui form yang sudah ada dan sudah teraudit.
5. Tidak mengubah kontrak 05a/05b, tidak menambah dependency, dan tidak menyentuh data operasional.

Rekap sekolah (FR-06) tetap menjadi pekerjaan terpisah setelah DEC-12 memiliki contoh format; jangan mengarang format.

---

## 2. Temuan audit kode (fakta per `8b091cf`)

### 2.1 Populasi data dan semantik yang sudah ada

| Temuan | Bukti | Implikasi 05c |
|---|---|---|
| Siswa terarsip memakai soft delete; daftar/rekap/ekspor tidak pernah memuatnya | `laravel-api/app/Models/Student.php:13`, `app/Support/StudentDirectory.php:14`, `docs/DECISIONS.md` DEC-21/DEC-22 | Populasi 05c mengecualikan siswa terarsip; arsip dihitung terpisah sebagai excluded |
| "Placed" = keanggotaan rombel aktif pada tahun terpilih, termasuk fallback `student_nik` untuk baris legacy | `StudentDirectory::activeMembershipExists()` baris 30–57; `place()` baris 99–108 | 05c **wajib memakai definisi ini**, bukan query baru yang bisa menyimpang |
| "Unplaced" = tidak punya keanggotaan aktif tahun terpilih (bukan berarti belum pernah punya rombel) | DEC-22 angka 2; `StudentDirectory::place()` | Siswa unplaced tidak masuk denominator; NIK-nya dilaporkan **blocked** |
| Pegawai terarsip memakai soft delete; direktori/ekspor mengecualikannya | `app/Models/Employee.php:13`, `app/Support/EmployeeDirectory.php:10-13` | Populasi 05c mengecualikan pegawai terarsip |
| `exit_date` bermakna berhenti/pensiun dan tidak diubah oleh arsip | DEC-24 D7; `app/Models/Employee.php:42` | "Aktif" perlu definisi eksplisit; rekomendasi: `exit_date` null atau belum lewat tanggal acuan |
| Tidak ada tabel/endpoint/halaman pemeriksaan kelengkapan | Pencarian kode: tidak ada `data_quality`, `completeness`, atau ability terkait | Fitur benar-benar baru; perlu migration additive, service, route, UI |
| Tidak ada konsep review/duplikat yang dapat dipakai ulang | Tidak ada model/table review; tidak ada endpoint duplikat | Perlu tabel review baru; deteksi duplikat dihitung server, bukan disimpan sebagai data domain |

### 2.2 Field yang relevan dan aturan validasi yang sudah berlaku

| Temuan | Bukti | Implikasi |
|---|---|---|
| NIK siswa opsional; jika diisi harus 16 digit dan unik termasuk arsip | `app/Support/StudentRules.php`; DEC-20 angka 1; DEC-21 angka 2 | "NIK kosong" bukan pelanggaran form, tetapi menjadi **incomplete** bila siswa placed; **blocked** bila unplaced |
| NISN siswa opsional, 10 digit, unik | `StudentRules.php`; migration `2029_07_06_215144_create_students_table.php:40-41` | Dapat diperiksa format/panjangnya; missing dihitung untuk siswa placed |
| Banyak biodata siswa nullable: gender, tempat/tanggal lahir, agama, keluarga, alamat, desa, telepon | migration students baris 19–46 | Profil kelengkapan v1 harus eksplisit; jangan menganggap semua field nullable wajib |
| Pegawai: nama, tipe, status, employment, agama, district, marital status, gender, TMT wajib di form; NIK/NUPTK opsional | `app/Support/EmployeeRules.php`; `EmployeeController`/request | Gap legacy tetap mungkin; 05c memeriksa field wajib form + NIK/NUPTK kontekstual |
| Subject wajib untuk `Pendidik` dan harus kosong untuk `Tenaga Kependidikan` | `EmployeeRules.php`; DEC-24 D4 | Pemeriksaan kontekstual tipe pegawai; pelanggaran legacy masuk daftar invalid |
| NIK pegawai unik tanpa syarat (termasuk arsip); NUPTK tidak unik | migration `2029_07_26_000001_add_profile_fields_to_employees.php`; DEC-24 D2 | Duplikat NIK seharusnya nol; duplikat NUPTK bisa nyata dan perlu ditinjau |
| Audit memakai allowlist dan tidak boleh memuat PII | `Student.php:27-36`, `Employee.php:32-43`, `app/Traits/Loggable.php` | Model review baru wajib allowlist; pesan issue tidak boleh menyalin nilai NIK/alamat |
| API detail siswa mengembalikan model lengkap; detail pegawai menyembunyikan PII dari pemegang `employees.view` saja | `StudentController::show()`; `EmployeeController::show()`; DEC-24 D6 | Tautan "Open record" cukup ke halaman detail berizin; 05c tidak membuat endpoint data pribadi baru |

### 2.3 Akses, navigasi, dan tautan record

| Temuan | Bukti | Implikasi |
|---|---|---|
| Halaman Students memakai `students.view`; Teachers/Employees memakai `employees.view` | `vue-app/src/router/index.js:67-85` | Halaman Data Quality perlu mendukung **dua kemampuan** (OR) |
| Guard route hanya menerima satu `requiresAbility` | `router/index.js:294-299` | Perlu dukungan array secara additive; string tunggal tetap berperilaku sama |
| Menu memfilter dengan satu `requires` | `vue-app/src/layout/AppMenu.vue:33-41` | Perlu dukungan array pada `canSee`; item Data Quality tampil bila salah satu izin ada |
| Store hanya punya `can(ability)` | `vue-app/src/stores/authStore.js:44` | Tambah getter `canAny(abilities)`; tidak mengubah `can` |
| Halaman siswa/pegawai membuka detail lewat dialog, bukan route detail | `Student.vue:894-907`; `EmployeeDirectory.vue:190-207` | Perlu dukungan query `openStudent`/`openEmployee` agar tautan dari 05c membuka dialog yang tepat |
| Daftar siswa/pegawai sudah server-side, memakai `StudentDirectory`/`EmployeeDirectory` | `Student.vue:124-166`; `EmployeeDirectory.vue:96-134` | Pola paginasi, debounce, requestId, dan page fallback dapat ditiru; jangan menduplikasi logika penempatan |
| Ekspor siswa/pegawai sudah ada; tidak ada ekspor issue | `routes/api.php:187-189`, `:217-...` | 05c tidak menambah ekspor issue pada v1 |
| Tidak ada route data quality | `routes/api.php` | Route baru harus memakai middleware `can:` yang sudah ada |

### 2.4 UAT dan gate dokumen

| Temuan | Bukti | Implikasi |
|---|---|---|
| UAT 05 memakai `.uat-phase05` SQLite, launcher `laravel-api/scripts/phase05-uat.php`, port 14023/18023 | `scripts/phase05-uat.php`; `PHASE-05B-MANUAL-REVIEW.md` | 05c memakai kembali lingkungan yang sama; tidak membuat environment baru |
| Data UAT 05 saat ini 409 siswa dan 200 pegawai | `roadmap/phases/PHASE-05-IMPORT-EXPORT.md` status 05b | Fixture deterministik perlu ditambahkan tanpa reset; jumlah sebelum/sesudah dicatat |
| Server UAT 04b/05 sudah dihentikan setelah penerimaan | DEC-27; laporan 05b | 05c menyalakan kembali 14023/18023 saat UAT, lalu menghentikannya lagi setelah diterima |
| DEC-12 masih PROPOSED | `docs/DECISIONS.md` register | Rekap sekolah **tidak boleh** masuk 05c; hanya boleh direncanakan setelah contoh format |
| FR-06 masih menunggu format | `docs/REQUIREMENTS.md` | 05c hanya memenuhi bagian pemeriksaan kelengkapan; rekap tetap OPEN |

---

## 3. Definisi kelengkapan (kontrak inti)

### 3.1 Istilah

| Istilah | Definisi |
|---|---|
| **Population** | Record tidak terarsip yang relevan untuk domain. Populasi ditampilkan sebagai angka, bukan disembunyikan. |
| **Denominator** | Subpopulasi yang **wajib lengkap** dan menjadi pembagi persentase. Denominator selalu ditampilkan di UI/API. |
| **Complete** | Field terisi dan lolos aturan validitas; atau record yang seluruh field wajibnya complete. |
| **Missing** | Field wajib kosong/null/whitespace. |
| **Invalid** | Field terisi tetapi gagal aturan validitas (mis. NIK bukan 16 digit). |
| **Blocked** | Record di luar denominator karena prasyarat belum terpenuhi; temuan dihitung terpisah dan **tidak** masuk persentase. Untuk 05c: siswa unplaced dengan NIK missing/invalid. |
| **Duplicate candidate** | Pasangan record yang memiliki kemiripan kunci; hanya kandidat untuk ditinjau, **bukan** penggabungan. |
| **Excluded** | Arsip, atau pegawai yang sudah keluar; dihitung terpisah, tidak masuk denominator dan tidak membuat issue. |

**Aturan angka:**

- Persentase field = `complete / denominator × 100`, satu angka desimal. Denominator 0 → `null`, bukan 100%.
- Record complete = semua field wajib untuk konteksnya complete. Persentase record = `complete_records / denominator × 100`.
- `missing` dan `invalid` sama-sama mengurangi complete, tetapi dilaporkan terpisah agar operator tahu jenis tindakannya.
- Status review (acknowledged/ignored) **tidak** mengubah pembilang, denominator, atau persentase. Review hanya status alur kerja.
- Duplikat tidak mengurangi persentase kelengkapan; duplikat dilaporkan sebagai dimensi terpisah.
- Semua hitungan memakai `as_of` tanggal server `Asia/Jakarta`; tidak ada cache pada v1.

### 3.2 Siswa — populasi dan denominator

| Angka | Definisi |
|---|---|
| `active_students` | Siswa dengan `students.deleted_at IS NULL`. |
| `placed` | Siswa aktif yang punya keanggotaan rombel aktif pada `school_year_id` terpilih, memakai `StudentDirectory::activeMembershipExists()` (termasuk pencocokan legacy NIK). |
| `unplaced` | Siswa aktif yang tidak punya keanggotaan aktif pada tahun terpilih. |
| `archived_excluded` | Siswa `onlyTrashed()`; tidak pernah muncul di daftar issue. |
| `not_evaluated` | Siswa unplaced tanpa temuan `blocked`; field wajib lain tidak dievaluasi pada v1. |
| **Denominator** | **`placed`**. Hanya siswa placed yang diwajibkan lengkap pada tahun terpilih. |
| **Blocked** | Siswa `unplaced` dengan NIK missing/invalid. Ditampilkan sebagai issue `blocked`, di luar persentase; prasyaratnya adalah penempatan. |

Siswa unplaced yang field lain kosong **tidak** dievaluasi pada v1 (ditampilkan sebagai `not_evaluated` di ringkasan) — jangan membuat ratusan issue yang belum relevan sebelum penempatan.

### 3.3 Profil kelengkapan siswa v1 (rekomendasi D2)

Berlaku untuk siswa **placed**:

| Field | Label English | Wajib | Aturan invalid |
|---|---|---|---|
| `nik` | NIK | Ya | Jika terisi, harus tepat 16 digit angka |
| `nis` | NIS | Ya | Hanya missing; tidak ada aturan format baru |
| `nisn` | NISN | Ya | Jika terisi, harus tepat 10 digit angka |
| `gender_id` | Gender | Ya | FK referensi; missing dihitung |
| `birthplace` | Birthplace | Ya | Missing dihitung |
| `birthdate` | Birthdate | Ya | Tidak boleh tanggal masa depan (acuan `as_of`) |
| `religion_id` | Religion | Ya | FK referensi; missing dihitung |

**Advisory:** family status, blood type, address, village, postal code, phone. Field advisory tidak membuat issue dan tidak masuk persentase pada v1; nilainya tetap dapat dilihat di detail record. Owner dapat memilih untuk mempromosikan sebagian field ini menjadi wajib melalui D2.

Contoh angka (ilustrasi): 380 placed, 30 NIK missing, 2 NIK invalid → NIK complete 348, `348/380 = 91,6%`; siswa unplaced yang NIK-nya kosong muncul sebagai blocked, bukan menurunkan 91,6%.

### 3.4 Pegawai — populasi dan denominator

| Angka | Definisi |
|---|---|
| `active_employees` | Pegawai tidak terarsip dengan `exit_date` null **atau** `exit_date >= as_of`; masih aktif pada tanggal acuan. |
| `separated` | Pegawai tidak terarsip dengan `exit_date < as_of`; tidak diwajibkan lengkap. |
| `archived_excluded` | Pegawai `onlyTrashed()`; tidak pernah muncul di daftar issue. |
| **Denominator** | **`active_employees`**. |
| **Blocked** | Tidak ada kategori blocked terpisah untuk pegawai pada v1; pegawai separated/archived adalah `excluded`, bukan blocked. |

Tipe pegawai ditentukan dari `employee_type_id` dengan nama kanonik `Pendidik` (Teacher) dan `Tenaga Kependidikan` (Non-teaching Employee). Tipe lain (bila ada) memakai konteks non-teaching untuk aturan Subject, kecuali owner menentukan lain.

### 3.5 Profil kelengkapan pegawai v1 (rekomendasi D3)

Berlaku untuk pegawai **active**:

| Field | Label English | Konteks | Aturan invalid |
|---|---|---|---|
| `nik` | NIK | Semua | Jika terisi, harus tepat 16 digit angka |
| `employee_type_id` | Employee Type | Semua | Missing dihitung |
| `employee_status_id` | Employee Status | Semua | Missing dihitung |
| `employment_id` | Employment | Semua | Missing dihitung |
| `gender_id` | Gender | Semua | Missing dihitung |
| `religion_id` | Religion | Semua | Missing dihitung |
| `marital_status_id` | Marital Status | Semua | Missing dihitung |
| `district_id` | District | Semua | Missing dihitung |
| `tmt_employee` | Employment Start Date (TMT) | Semua | Missing dihitung |
| `nuptk` | NUPTK | **Pendidik** | Missing dihitung; tidak ada format baru |
| `subject_id` | Subject | **Pendidik** | Missing dihitung; untuk Tenaga Kependidikan, Subject terisi → invalid |

**Advisory:** place/date of birth, address, RT/RW, postal code, phone, email, NUPTK untuk Tenaga Kependidikan, riwayat pendidikan. Field advisory tidak membuat issue dan tidak masuk persentase pada v1.

**Integritas kontekstual:** `Tenaga Kependidikan` dengan `subject_id` terisi adalah **invalid** (aturan yang sama dengan form). `Pendidik` tanpa Subject adalah **missing** (bukan invalid), karena field wajibnya kosong.

### 3.6 Duplikat dan integritas identitas (rekomendasi D4)

Deteksi duplikat bersifat **kandidat**, bukan keputusan. Tidak ada auto-merge, auto-delete, atau perubahan record dari proses ini.

| Domain | Sumber kandidat | Catatan |
|---|---|---|
| Siswa | Nama ternormalisasi (`LOWER(TRIM(name))`) + `birthdate` yang sama, keduanya tidak kosong | Satu issue per pasangan; record dengan ID lebih kecil menjadi `subject`, ID lebih besar menjadi `related` |
| Siswa | NIK/NIS/NISN sama di antara record tidak terarsip | Pemeriksaan integritas; unique index seharusnya mencegah duplikat baru, tetapi data legacy tetap diperiksa |
| Pegawai | NUPTK tidak kosong dan sama di antara pegawai active | NUPTK memang tidak unik di skema; kandidat nyata |
| Pegawai | Nama ternormalisasi + `date_of_birth` sama, keduanya tidak kosong | Pasangan; tidak pernah memakai nama saja |
| Pegawai | NIK sama di antara pegawai tidak terarsip | Pemeriksaan integritas; unique index seharusnya mencegahnya |

Larangan eksplisit: **nama saja tidak cukup** untuk menyatakan duplikat. Nama boleh sama (DEC-16/DEC-20). Setiap kandidat harus ditautkan ke dua record berizin untuk ditinjau manusia.

### 3.7 Yang tidak dihitung

- Siswa/pegawai terarsip: excluded, tidak membuat issue.
- Pegawai separated: excluded dari denominator; bila NIK kosong, tidak membuat issue (bukan blocked).
- Field advisory: ditampilkan sebagai informasi, tidak menurunkan persentase.
- Duplikat: tidak menurunkan persentase; tidak pernah mengubah data.
- Rekap sekolah: bukan bagian 05c.

---

## 4. Usulan scope teknis

### 4.1 Backend — service dan aturan terpusat

Tambahkan direktori `laravel-api/app/Services/DataQuality/`:

| Komponen | Tanggung jawab |
|---|---|
| `StudentCompletenessInspector` | Membangun populasi siswa, denominator placed, ringkasan field, daftar issue, dan kandidat duplikat memakai `StudentDirectory` |
| `EmployeeCompletenessInspector` | Membangun populasi pegawai active/separated, ringkasan field per tipe, daftar issue, dan kandidat duplikat |
| `IssueKey` | Menghasilkan SHA-256 dari konteks issue (domain, tipe, field, school year siswa, subject, versi subject, related, versi related) tanpa nilai PII |
| `StudentCompletenessProfile` | Registry field wajib/advisory, label English, aturan validitas, dan konteks |
| `EmployeeCompletenessProfile` | Registry field wajib/advisory per tipe pegawai, label English, dan aturan validitas |
| `ReviewService` | Upsert status review dengan transaksi, version check, validasi alasan, dan audit |

Prinsip:

- **GET tidak menulis domain data.** Summary/issues hanya membaca.
- Iterasi memakai `chunkById`/query terbatas; tidak memuat seluruh tabel ke memori tanpa batas.
- Tidak menduplikasi logika penempatan; siswa memakai `StudentDirectory::activeMembershipExists()`/`place()`.
- Deteksi duplikat tidak memakai nilai mentah di response; hanya ID, nama/NIS/NUPTK yang memang sudah diizinkan di daftar.
- Semua pesan issue English dan tidak menyalin NIK, alamat, telepon, atau nilai sensitif lain.
- Skala v1 diasumsikan sekolah (ribuan record), bukan puluhan ribu; performa belum diuji dan dicatat NOT RUN sampai ada bukti.

### 4.2 Migration additive dan model review

Migration baru: `2029_07_29_000001_create_data_quality_reviews_table.php` (nomor mengikuti migration terakhir `2029_07_28_000001`).

```text
data_quality_reviews
- id
- issue_key          string(64), unique
- domain             string(20)   // students | employees
- issue_type         string(20)   // missing | invalid | duplicate | blocked
- field              string(50), nullable  // key field/rule, bukan nilai
- subject_type       string(20)   // student | employee
- school_year_id     unsignedBigInteger, nullable  // siswa; null untuk pegawai
- subject_id         unsignedBigInteger
- subject_version    unsignedBigInteger
- related_subject_id unsignedBigInteger, nullable
- related_version    unsignedBigInteger, nullable
- status             string(20), default open  // open | acknowledged | ignored
- reason_code        string(40), nullable
- version            unsignedBigInteger, default 1
- reviewed_by        foreignId users, nullable, nullOnDelete
- reviewed_at        timestamp, nullable
- timestamps
- index (domain, status)
- index (subject_type, subject_id)
```

Catatan:

- Tidak ada FK ke `students`/`employees` karena tabel review bersifat generik dan record domain memakai soft delete; validasi subject dilakukan di service.
- `issue_key` adalah hash; tidak ada NIK/nama di dalamnya.
- `subject_version`/`related_version` mengikat review ke **versi data saat ditinjau**. Jika record berubah, issue key berubah dan review lama tidak lagi berlaku (issue kembali open). Ini mencegah status ignored menyembunyikan data yang berubah.
- `school_year_id` mengikat review siswa ke tahun ajaran; review di tahun A tidak menyembunyikan issue yang sama di tahun B. Untuk pegawai nilainya `null`.
- `version` review untuk optimistic concurrency; update bersamaan → `409`.
- Model `App\Models\DataQualityReview` memakai trait `Loggable` dengan allowlist minimal: `issue_key`, `domain`, `issue_type`, `field`, `subject_type`, `school_year_id`, `subject_id`, `status`, `reason_code`, `version`; **tanpa** nama, NIK, NUPTK, alamat, atau nilai field.
- Migration hanya additive; `down()` menghapus tabel review baru. Tidak ada perubahan tabel `students`, `employees`, `import_batches`, atau ability.

### 4.3 API baru

Semua route berada di prefix `/api/data-quality`, di dalam grup `auth:sanctum`, dan memakai middleware kemampuan yang sudah ada. **Tidak ada ability baru.**

| Method | Endpoint | Izin | Perilaku |
|---|---|---|---|
| GET | `/api/data-quality/students/summary` | `students.view` | Ringkasan populasi, denominator, per-field, duplikat, status review untuk `school_year_id` |
| GET | `/api/data-quality/students/issues` | `students.view` | Daftar issue siswa berfilter dan berpaginasi |
| PUT | `/api/data-quality/students/reviews/{issueKey}` | `students.manage` | Upsert status review siswa |
| GET | `/api/data-quality/employees/summary` | `employees.view` | Ringkasan populasi active/separated, per-field per tipe, duplikat, status review |
| GET | `/api/data-quality/employees/issues` | `employees.view` | Daftar issue pegawai berfilter dan berpaginasi |
| PUT | `/api/data-quality/employees/reviews/{issueKey}` | `employees.manage` | Upsert status review pegawai |

**Parameter summary siswa:** `school_year_id` wajib, integer, `exists:school_years,id`. Tanpa parameter lain.

**Parameter issues siswa:** `school_year_id` wajib; `type` opsional `missing|invalid|duplicate|blocked`; `field` opsional sesuai allowlist profil; `status` opsional `open|acknowledged|ignored|all` (default `open`); `search` opsional maks 100 (nama/NIS/NISN); `page` integer ≥1; `per_page` `10|20|50|100` (default 20). Untuk `type=duplicate`, `field` memfilter rule duplikat (`name_birthdate`, `nik`, `nis`, `nisn`). `placement` tidak perlu karena denominator sudah placed; blocked ditampilkan sebagai tipe.

**Parameter issues pegawai:** sama tanpa `school_year_id`; `search` nama/NUPTK; `field` allowlist profil pegawai; tipe/status/per_page sama.

**Respons summary (bentuk):**

```json
{
  "success": true,
  "data": {
    "profile_version": 1,
    "as_of": "2026-10-31T10:00:00+07:00",
    "school_year": { "id": 1, "name": "2026/2027" },
    "denominator_basis": "placed_students",
    "population": {
      "active_students": 469,
      "placed": 400,
      "unplaced": 69,
      "not_evaluated": 48,
      "archived_excluded": 12
    },
    "records": {
      "complete": 350,
      "incomplete": 50,
      "complete_percent": 87.5
    },
    "fields": [
      {
        "field": "nik",
        "label": "NIK",
        "missing": 30,
        "invalid": 2,
        "complete": 368,
        "completeness_percent": 92.0,
        "blocked": { "missing": 20, "invalid": 1 }
      }
    ],
    "duplicates": { "candidate_pairs": 3, "affected_subjects": 6 },
    "reviews": { "open": 80, "acknowledged": 12, "ignored": 4, "unresolved": 92 }
  }
}
```

- `blocked` hanya ada pada `nik` siswa; field lain memakai `null`/tidak ditampilkan.
- Ringkasan pegawai memakai `denominator_basis: "active_employees"`, `population: { active, separated, archived_excluded }`, dan field `nuptk`/`subject` hanya dihitung untuk Pendidik.
- `reviews.unresolved = open + acknowledged`; ignored tidak dihitung unresolved, tetapi tetap ditampilkan.
- Pada siswa, seluruh angka (termasuk `reviews`) dihitung untuk `school_year_id` terpilih; review pegawai memakai `school_year_id = null`.
- Semua hitungan adalah angka, bukan persentase saja; jangan ada pembulatan yang menghilangkan denominator.

**Respons issues (bentuk):**

```json
{
  "success": true,
  "data": [
    {
      "issue_key": "…64 hex…",
      "type": "missing",
      "field": "nik",
      "field_label": "NIK",
      "message": "NIK is missing for a placed student.",
      "subject": {
        "type": "student",
        "id": 123,
        "version": 7,
        "name": "…",
        "nis": "…",
        "nisn": null,
        "placement": "placed",
        "gender": "Female",
        "rombel": { "id": 9, "name": "X IPA 1" }
      },
      "related": null,
      "review": { "status": "open", "reason_code": null, "version": 0, "reviewed_at": null }
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 82, "last_page": 5, "from": 1, "to": 20 }
}
```

- `subject` siswa tidak memuat NIK, birthdate, alamat, telepon, atau data medis; hanya atribut yang sudah diizinkan daftar 03c + `version` untuk review.
- `subject` pegawai memuat `id`, `version`, `name`, `nuptk`, `employee_type` (label English), `employee_status`, `employment`, `subject`; tidak memuat NIK/PII lain.
- `related` untuk duplikat berisi bentuk subjek yang sama; `null` untuk tipe lain.
- `message` tidak pernah memuat nilai asli field. Contoh: "NIK must be exactly 16 digits.", "NUPTK is missing for a teacher.", "Possible duplicate: same name and birthdate."
- `field` adalah kunci field/rule, bukan nilai: `nik`, `nis`, `nisn`, `gender_id`, `birthplace`, `birthdate`, `religion_id`, `nuptk`, `subject_id`, `name_birthdate`, `employee_type_id`, dan seterusnya.
- Urutan hasil deterministik: nama subject asc, lalu `field`, lalu `subject_id`; paginasi stabil.
- `meta` mengikuti pola 03c/04b.

**Review — body `PUT`:**

```json
{
  "status": "ignored",
  "reason_code": "pending_document",
  "issue_type": "missing",
  "field": "nik",
  "school_year_id": 1,
  "subject_id": 123,
  "subject_version": 7,
  "related_subject_id": null,
  "related_version": null,
  "version": 0
}
```

Aturan:

- `status`: `open|acknowledged|ignored`.
- `reason_code`: wajib bila `ignored`; allowlist `pending_document`, `source_unavailable`, `verified_distinct`, `not_applicable`. `verified_distinct` hanya sah untuk `issue_type=duplicate`. Selain itu `reason_code` harus null.
- `school_year_id`: wajib `exists:school_years,id` pada review siswa dan menjadi bagian konteks issue; review pegawai menolak field ini (`422`) dan menyimpan `null`.
- `version`: 0 bila belum ada review; wajib sama dengan versi review tersimpan. Versi usang → `409` + `current_version`.
- `subject_id`/`subject_version`/`related` harus menunjuk record tidak terarsip dengan versi saat ini; versi berubah → `409` "The issue is out of date. Refresh the list."
- `issue_key` dihitung ulang dari body dan harus sama dengan `{issueKey}` route; tidak sama → `409` (bukan membuat review untuk issue fiktif).
- Upsert dalam transaksi dengan `lockForUpdate`; `version` review naik 1; `reviewed_by`/`reviewed_at` diisi.
- Review `open` dapat mengembalikan status ke open (baris tetap ada untuk jejak audit); tombol "Reopen" hanya untuk manage.
- Response `200`: `{status, reason_code, version, reviewed_at, reviewed_by}`.
- `403` untuk pengguna view-only; `422` untuk status/alasan/field tidak sah; `404` bila subject tidak ditemukan; `409` untuk versi/issue usang.
- Review **tidak** mengubah data siswa/pegawai dan **tidak** mengubah persentase.

**Issue key:** `hash('sha256', implode('|', [domain, issueType, field, schoolYearId, subjectType, subjectId, subjectVersion, relatedId, relatedVersion]))` dengan string kosong untuk nilai null. `schoolYearId` kosong untuk pegawai. `domain` = `students`/`employees`; `subjectType` = `student`/`employee`. Nilai field, NIK, nama, dan PII lain tidak pernah masuk hash.

### 4.4 Frontend

**Halaman baru:** `vue-app/src/views/pages/public/DataQuality.vue`, route `/public/data-quality` dengan nama `dataquality`.

- Guard route mendukung **string maupun array**; item ini memakai `requiresAbility: ['students.view', 'employees.view']` dan lolos bila salah satu ada. Perilaku string tunggal tidak berubah.
- `authStore` menambah getter `canAny(abilities)`; `AppMenu.vue` `canSee` mendukung array. Item menu "Data Quality" di grup Public dengan ikon yang sesuai.
- Halaman memiliki dua tab: **Students** dan **Employees**.
  - Tab Students hanya tampil bila `students.view`; memuat `school_year_id` (mengikuti pola pemilihan tahun di `Student.vue`) dan menampilkan ringkasan + denominator "placed students".
  - Tab Employees hanya tampil bila `employees.view`; menampilkan denominator "active employees" dan pemisahan active/separated.
  - Bila pengguna hanya punya salah satu izin, halaman langsung membuka tab yang tersedia. Bila tidak punya keduanya, route guard menolak.
- Kartu ringkasan: denominator, complete/incomplete + persentase, missing/invalid, blocked (siswa), duplicate candidates, open/acknowledged/ignored.
- Tabel issue server-side: filter type/field/status, pencarian debounce 300 ms, paginasi 10/20/50/100 default 20, `requestId` guard, loading/empty/error + Retry, page fallback bila halaman terakhir kosong.
- Aksi baris:
  - **Open record** → `/public/students?openStudent={id}` atau `/public/teachers|employees?openEmployee={id}` (halaman ditentukan dari label `employee_type` pada subject).
  - **Acknowledge / Ignore / Reopen** hanya untuk pemegang `students.manage`/`employees.manage`; membuka `DataQualityReviewDialog.vue` untuk memilih status dan alasan (alasan wajib untuk Ignore). Dialog menangani `409` dengan memuat ulang daftar.
- Teks English; layout usable pada 390px; tanpa grafik baru dan tanpa ekspor issue.

**Perubahan halaman yang sudah ada (kecil dan additive):**

- `Student.vue`: baca query `openStudent` setelah daftar/tahun awal siap; panggil detail yang ada (`GET /api/students/{id}`) dan buka dialog detail. Jangan mengubah filter atau perilaku daftar.
- `EmployeeDirectory.vue`: baca query `openEmployee` setelah referensi dan daftar siap; panggil detail yang ada dan buka dialog detail. Halaman Teachers/Employees tetap memfilter tipe sesuai route.
- Tidak menambahkan tombol edit/merge di halaman Data Quality; koreksi melalui dialog form yang sudah ada dan sudah teraudit.
- Anggota menu/halaman Data Quality tidak memerlukan ability baru; backend tetap penentu izin.

**Helper dan unit test:**

- `vue-app/src/views/pages/public/dataQualityParams.mjs`: pembentuk parameter summary/issues dan label tipe/status; diuji dengan Node test.
- Unit test untuk `canAny`/array guard bila ada utilitas yang dapat diuji tanpa DOM.

### 4.5 Konfigurasi

`laravel-api/config/data_quality.php` (baru, tanpa environment variable rahasia):

```php
return [
    'profile_version' => 1,
    'per_page' => [10, 20, 50, 100],
    'default_per_page' => 20,
    'review_reasons' => [
        'pending_document',
        'source_unavailable',
        'verified_distinct',
        'not_applicable',
    ],
];
```

- Tidak mengubah `config/students.php`, `config/employees.php`, atau `config/imports.php`.
- Tidak menambah dependency, queue, scheduler, atau cache eksternal.
- Tidak membuat file publik; seluruh data issue berasal dari API berizin.

### 4.6 UAT

- Gunakan kembali **`.uat-phase05`** (SPA 14023, API 18023, cookie `school_phase05_session`, launcher `scripts/phase05-uat.php`). DEC-27 tetap berlaku: server dinyalakan saat UAT dan dihentikan setelah pemilik menerima.
- Perluas launcher dengan command guarded `prepare-data-quality` (nama final ditentukan saat implementasi) yang:
  - menolak berjalan bila environment bukan `.uat-phase05`;
  - idempotent dan tidak melakukan reset;
  - menambahkan **120 record sintetis** bertanda `UAT-DQ`:
    - 60 siswa: 20 placed lengkap (kontrol), 20 placed dengan gap terkontrol (NIK kosong, NIK invalid, NISN kosong, birthdate kosong), 10 unplaced tanpa NIK (blocked), 10 siswa dalam 5 pasangan kandidat duplikat nama+tanggal lahir;
    - 60 pegawai: 20 active lengkap (10 Pendidik, 10 Tenaga Kependidikan), 20 active dengan gap (Pendidik tanpa NUPTK, NIK kosong, Tenaga Kependidikan tanpa NIK), 10 pegawai dalam 5 pasangan duplikat NUPTK, 10 separated tanpa NIK (excluded);
  - memakai NIK/NIS/NUPTK sintetis dengan rentang khusus dan memeriksa tabrakan sebelum insert;
  - mencatat jumlah populasi sebelum/sesudah di output agregat tanpa PII.
- Data lama tidak dihapus/diubah; jumlah 409 siswa/200 pegawai akan bertambah dan dicatat di laporan.
- Skenario UAT manual mencakup: denominator students/employees, blocked vs incomplete, filter type/field/status, duplikat tanpa auto-merge, tautan ke record, koreksi lewat form lama, acknowledge/ignore/reopen, 409 versi, izin view-only, dan tampilan 390px.

### 4.7 Dokumentasi yang disinkronkan saat implementasi

`docs/API-CONTRACTS.md` (enam endpoint, parameter, bentuk issue/review, tanpa PII), `docs/ACCESS-CONTROL.md` (reuse `students.view/manage` dan `employees.view/manage`; tidak ada ability baru; review write = manage), `docs/DATABASE.md` (tabel `data_quality_reviews`, issue key, versi), `docs/REQUIREMENTS.md` (FR-06: completeness tersedia; rekap sekolah tetap OPEN), `docs/TESTING.md` (bukti + NOT RUN), `docs/DECISIONS.md` (DEC-28 setelah disetujui), `CHANGELOG.md`, `PHASE-05C-REPORT.md`, `PHASE-05C-MANUAL-REVIEW.md`, `INDEX.md`, `HANDOFF.md`, `roadmap/ROADMAP.md`, `roadmap/phases/PHASE-05-IMPORT-EXPORT.md`, `reports/feature-update/PHASE-05-PLAN.md`.

---

## 5. Keputusan pemilik yang dibutuhkan (usulan DEC-28)

| ID | Pertanyaan | Rekomendasi | Alternatif | Dampak |
|---|---|---|---|---|
| D1 | Cakupan 05c | **Kelengkapan siswa + pegawai; daftar missing/invalid/duplikat/blocked; review operator.** Rekap sekolah terpisah menunggu DEC-12; tanpa auto-merge dan tanpa bulk correction | (a) siswa saja; (b) tambahkan rekap sekolah sekarang | Opsi (b) tidak dapat diimplementasikan tanpa format DEC-12; opsi (a) menunda nilai untuk data pegawai |
| D2 | Profil dan denominator siswa | **Denominator = placed non-archived; wajib NIK, NIS, NISN, Gender, Birthplace, Birthdate, Religion; unplaced NIK = blocked; archived excluded; field kontak/keluarga advisory** | (a) identitas saja (NIK/NIS/NISN); (b) tambah alamat/telepon/keluarga sebagai wajib | Menentukan persentase dan jumlah issue; field lebih banyak = lebih banyak temuan dan pekerjaan koreksi |
| D3 | Profil dan denominator pegawai | **Denominator = active non-archived (`exit_date` null atau belum lewat); wajib NIK + tipe/status/employment/gender/agama/marital/district/TMT; Pendidik wajib NUPTK + Subject; Tenaga Kependidikan dengan Subject = invalid; separated/archived excluded** | (a) hanya NIK + NUPTK/Subject; (b) tambah kontak/alamat/riwayat pendidikan sebagai wajib | Menentukan cakupan koreksi; opsi (b) memperbesar pekerjaan operator |
| D4 | Deteksi duplikat | **Kandidat pasangan: siswa nama+birthdate dan integritas NIK/NIS/NISN; pegawai NUPTK dan nama+date_of_birth serta integritas NIK; tidak pernah nama saja; tidak ada auto-merge** | (a) tanpa duplikat pada v1; (b) deteksi nama saja | Nama saja menghasilkan false positive tinggi dan melanggar larangan auto-merge; tanpa duplikat mengurangi nilai 05c |
| D5 | Alur review | **Tabel review persisten; status open/acknowledged/ignored; alasan terbatas untuk ignored; tulis = `students.manage`/`employees.manage`; baca = view; versi + 409; persentase tetap dihitung dari data mentah** | (a) status hanya di memori/UI; (b) ability baru khusus review; (c) ignored mengeluarkan issue dari persentase | (a) hilang setelah refresh; (b) menambah izin yang harus dikelola; (c) membuat persentase tidak jujur |
| D6 | UX koreksi | **Tautan ke halaman detail/form yang ada (`?openStudent=`/`?openEmployee=`); koreksi lewat endpoint yang sudah teraudit; tidak ada edit inline/merge API** | (a) edit inline di halaman Data Quality; (b) endpoint koreksi massal baru | Opsi (a)/(b) memperbesar risiko validasi/audit dan menduplikasi form |
| D7 | UAT dan fixture | **Reuse `.uat-phase05` 14023/18023; tambah 120 record `UAT-DQ` idempotent dan guarded; data lama tidak di-reset; hentikan server setelah penerimaan (DEC-27)** | (a) environment/port baru; (b) tanpa fixture, mengandalkan data yang ada | (a) menambah biaya isolasi; (b) membuat skenario blocked/duplikat tidak deterministik |

Bila pemilik memilih alternatif, rencana diperbarui sebelum implementasi. Tidak ada keputusan yang ditebak.

---

## 6. Urutan eksekusi dan gate

| Langkah | Pekerjaan | Verifikasi | Gate |
|---|---|---|---|
| S1 | Catat D1–D7 sebagai **DEC-28** di `docs/DECISIONS.md` | Keputusan eksplisit pemilik | D1–D7 |
| S2 | Migration additive `data_quality_reviews`, model, allowlist audit | Migration SQLite; tidak mengubah tabel lama | — |
| S3 | Profil field + `StudentCompletenessInspector` + `EmployeeCompletenessInspector` + `IssueKey` | Tes unit denominator, missing/invalid/blocked/duplikat; tidak ada mutasi GET | D2–D4 |
| S4 | Controller, request validasi, route, `ReviewService` | Tes izin, 422/403/409, review version, audit tanpa PII | D5 |
| S5 | UI Data Quality, dialog review, OR guard/menu, deep link | Unit Node, build, ESLint, browser DOM bila tersedia | D6 |
| S6 | Fixture UAT 120 record + checklist manual | Guard `.uat-phase05`, jumlah sebelum/sesudah, alur UAT | D7 |
| S7 | Dokumentasi, laporan, commit/push paket review | Pint, diff check, working tree bersih dari artefak | — |

Setiap langkah berhenti pada gate yang belum dijawab. Implementasi tidak dilanjutkan ke rekap sekolah atau Fase 06.

---

## 7. Rencana pengujian

### 7.1 Backend (SQLite `:memory:`, tanpa data operasional)

1. **Izin:** guest `401`; pengguna tanpa `students.view` `403` pada summary/issues siswa; `students.view` `403` pada review siswa; `students.manage` `200`; pengguna dengan `employees.view` `403` pada review pegawai; `employees.manage` `200`; principal (view-only) dapat membaca kedua domain tetapi tidak dapat menulis review.
2. **Populasi siswa:** siswa archived tidak muncul di summary/issues; placed/unplaced dihitung sesuai `SchoolYear`; tahun ajaran berbeda memindahkan denominator; `school_year_id` hilang/tidak ada → `422`.
3. **Siswa incomplete vs blocked:** placed tanpa NIK → `missing` dan menurunkan persentase; unplaced tanpa NIK → `blocked`, di luar persentase; NIK invalid 15/17 digit/karakter → `invalid`; NISN invalid; birthdate masa depan; field lain kosong sesuai profil.
4. **Duplikat siswa:** dua record nama+birthdate sama → satu pasangan kandidat; nama sama tetapi birthdate berbeda → bukan duplikat; record archived tidak ikut; tidak ada perubahan data setelah GET.
5. **Populasi pegawai:** active vs separated berdasarkan `exit_date` dan `as_of`; archived excluded; `Pendidik` tanpa NUPTK/Subject → missing; `Tenaga Kependidikan` dengan Subject → invalid; NIK invalid; field advisory tidak membuat issue dan tidak menurunkan persentase.
6. **Duplikat pegawai:** NUPTK sama di antara active → kandidat; NUPTK sama dengan separated/archived → tidak masuk; nama+date_of_birth sama → kandidat; nama sama tanpa tanggal lahir → tidak.
7. **Review:** default open; acknowledge; ignore tanpa alasan `422`; alasan di luar allowlist `422`; `verified_distinct` hanya untuk duplicate; reopen; versi review usang `409` + `current_version`; versi record berubah `409`; issue key tidak cocok `409`; subject tidak ada `404`; review ignored pada tahun ajaran A tidak menyembunyikan issue siswa yang sama pada tahun ajaran B; review pegawai dengan `school_year_id` → `422`.
8. **Review audit:** aktivitas tercatat tanpa nama/NIK/NUPTK/alamat; nilai field tidak masuk log; review tidak mengubah tabel domain.
9. **Filter/paginasi:** type/field/status allowlist; `per_page` di luar daftar `422`; search nama/NIS/NUPTK; pagination stabil; `status=all` mencakup ignored; `status=open` default.
10. **Privasi respons:** payload siswa tanpa NIK/alamat/telepon/medis; payload pegawai tanpa NIK/NPWP/KK/alamat; pesan tidak menyalin nilai field; tidak ada nomor identitas di issue key.
11. **Regresi:** seluruh tes 03c/04b/05a/05b yang terdampak tetap PASS; route list tidak berubah selain enam route baru; tidak ada route lama yang hilang/berubah metode.
12. **Batas data:** dataset kosong → denominator 0 dan persentase null, bukan error; satu record dengan banyak issue → paginasi tetap benar.

### 7.2 Frontend

- Unit test `dataQualityParams.mjs` (parameter, label, default status/per_page).
- Unit test dukungan array pada helper kemampuan/guard bila dapat diuji tanpa DOM; string tunggal tetap berperilaku sama.
- `npm run build` PASS; ESLint read-only pada file berubah; tidak memakai `npm run lint` (baseline memakai `--fix`).
- Browser DOM (bila tersedia): tab Students/Employees, ringkasan denominator, filter blocked vs missing, duplikat tanpa merge, open record membuka dialog, acknowledge/ignore dengan alasan, 409 refresh, tombol review tidak tampil untuk principal, 0 console error.
- Bila otomasi browser tidak tersedia, catat **NOT RUN** dan sediakan checklist manual; screenshot/Playwright tidak boleh diklaim tanpa bukti.

### 7.3 Checklist manual pemilik (dibuat setelah implementasi)

1. Isolasi `.uat-phase05`, jumlah populasi sebelum/sesudah fixture, dan ability pengguna.
2. Students summary: denominator placed, complete/incomplete, missing/invalid, blocked NIK, archived excluded.
3. Filter type missing/invalid/blocked, field NIK, status open; pagination dan pencarian.
4. Duplikat kandidat menampilkan dua record; tidak ada tombol merge/auto-fix.
5. Open record membuka detail siswa yang benar; edit melalui form lama; setelah koreksi, issue berubah/hilang dan review lama tidak menyembunyikan versi baru.
6. Acknowledge dan Ignore (alasan wajib); Reopen; status tampil di daftar.
7. Principal view-only dapat melihat tetapi tidak dapat mengubah review.
8. Employees summary: active vs separated; Pendidik wajib NUPTK/Subject; Tenaga Kependidikan dengan Subject invalid.
9. Employee issue link membuka Teachers atau Employees sesuai tipe.
10. 409 saat review memakai versi lama; data tidak berubah tanpa refresh.
11. Respons tidak memuat NIK/alamat/telepon/medis pada daftar issue.
12. Tampilan 390px dan keyboard/focus dialog.
13. Tidak ada perubahan pada halaman Students/Teachers/Employees selain deep link.
14. Setelah pemilik menerima, server UAT 14023/18023 dihentikan dan data `.uat-phase05` dipertahankan.

---

## 8. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Definisi kelengkapan berbeda dari harapan pemilik | D1–D3 eksplisit; persentase selalu menampilkan denominator dan field wajib |
| "Blocked" disalahartikan sebagai error data | Definisi tertulis di UI/API; blocked hanya siswa unplaced NIK; di luar persentase |
| Penempatan siswa dihitung ulang dan menyimpang dari 03c | Wajib memakai `StudentDirectory::activeMembershipExists()`; tes perbandingan daftar/summary |
| Duplikat nama menimbulkan tuduhan palsu | Tidak pernah nama saja; kandidat pasangan; tidak ada auto-merge; alasan `verified_distinct` |
| Review ignored menyembunyikan data yang berubah | Issue key mengikat versi record; perubahan record → key baru → open kembali |
| PII bocor lewat pesan issue/audit | Pesan tanpa nilai; payload minimum; allowlist audit; tes negatif |
| Performa scan besar | Skala sekolah; `chunkById`; batas data didokumentasikan; uji beban dicatat NOT RUN |
| Review write oleh view-only | Middleware `can:students.manage`/`can:employees.manage`; tes 403 |
| Perubahan guard/menu meregresi route lain | Dukungan array additive; string tunggal diuji; route yang ada tidak diubah |
| Fixture UAT mengubah data pemilik | Command guarded + idempotent; jumlah sebelum/sesudah; tanpa reset; server dihentikan setelah penerimaan |
| Rekap sekolah menyelinap ke 05c | Out of scope eksplisit; DEC-12 tetap PROPOSED |
| Deep link membuka record yang salah | Link memakai ID dari subject/tipe; tes halaman students/teachers/employees |
| Migration review bentrok dengan data lama | Tabel baru; unique issue_key; tidak mengubah tabel domain; down additive |

---

## 9. Kriteria penerimaan dan syarat keluar

1. D1–D7 dijawab pemilik dan dicatat sebagai **DEC-28**.
2. Ringkasan siswa/pegawai menampilkan denominator, pembilang, persentase, dan angka excluded secara konsisten; siswa NIK missing pada placed = incomplete, pada unplaced = blocked.
3. Daftar issue dapat difilter per type/field/status, berpaginasi, dan setiap baris memiliki tautan ke record berizin; duplikat hanya kandidat, tanpa auto-merge.
4. Review status tersimpan, berversi, teraudit, dan hanya dapat diubah pemegang manage; status review tidak mengubah persentase data.
5. Koreksi tetap melalui endpoint/form yang ada dan teraudit; tidak ada endpoint koreksi massal/merge baru.
6. Enam endpoint baru, tabel review, dan UI berjalan; tes backend/frontend/build dijalankan dan dicatat; NOT RUN dinyatakan eksplisit.
7. UAT memakai `.uat-phase05` dengan fixture 120 record; data lama tidak di-reset; server dihentikan setelah penerimaan sesuai DEC-27.
8. Dokumentasi kontrak/akses/database/requirements/CHANGELOG/laporan/INDEX/HANDOFF/ROADMAP disinkronkan; commit/push paket review dilakukan; persetujuan pemilik dicatat pada commit dokumentasi terpisah.
9. Tidak ada merge/deploy/force-push, migration DB operasional/MariaDB/UAT 04b, dependency baru, atau perubahan `.env`.
10. Rekap sekolah tetap di luar 05c dan menunggu DEC-12.

---

## 10. Di luar scope 05c

- Rekap sekolah (FR-06) dan format ekspor eksternal; menunggu DEC-12.
- Auto-merge, auto-delete, atau koreksi massal berdasarkan nama/duplikat.
- Edit inline di halaman Data Quality.
- Ekspor daftar issue, XLSX, Dapodik API, atau sinkronisasi eksternal.
- Pembersihan PII otomatis, retensi scheduler, atau notifikasi eksternal.
- Ability baru khusus data quality (reuse view/manage).
- Perubahan perilaku impor/ekspor 05a/05b atau halaman daftar yang sudah diterima, selain deep link additive.
- Fase 06 atau pekerjaan lintas fase lainnya.

---

## 11. Batas otorisasi saat ini

- Dokumen ini **hanya rencana**; belum ada kode, migration, route, UI, ability, atau data yang diubah.
- Implementasi 05c menunggu jawaban D1–D7 dan instruksi pemilik. DEC-25 mengotorisasi arah fase, tetapi pilihan profil, duplikat, review, dan UAT belum boleh ditebak.
- Implementasi nanti hanya boleh menyentuh `.uat-phase05` untuk fixture dan migration additive; bukan DB operasional, MariaDB, `.uat-phase04b`, `.env`, service, atau dependency.
- Commit/push paket review dilakukan setelah verifikasi sesuai kebijakan pemilik (review di GitHub); push bukan penerimaan. Penerimaan manual dicatat terpisah.
- Rekap sekolah, Dapodik, XLSX, merge/deploy/force-push, dan Fase 06 tetap di luar scope sampai ada instruksi keputusan terpisah.

---

## 12. Prompt untuk memulai implementasi setelah rencana disetujui

```text
Baca AGENTS.md, ROADMAP, HANDOFF, PHASE-05-IMPORT-EXPORT.md,
PHASE-05-PLAN.md, dan PHASE-05C-PLAN.md.

Kerjakan subfase 05c saja: pemeriksaan kelengkapan siswa dan pegawai,
daftar missing/invalid/duplikat/blocked, dan status review operator.
Patuhi DEC-20, DEC-21, DEC-22, DEC-24, DEC-25, DEC-27, dan DEC-28 (D1–D7);
jangan menebak keputusan yang belum dijawab.

Batas:
- Jangan menyentuh DB operasional/MariaDB, UAT 04b, .env utama, service,
  dependency/lockfile, atau kontrak 05a/05b.
- Rekap sekolah menunggu DEC-12; jangan mengarang format.
- Jangan menambah ability baru; gunakan students.view/manage dan
  employees.view/manage.
- Jangan membuat auto-merge, edit inline, atau endpoint koreksi massal.
- Gunakan .uat-phase05 untuk fixture 120 record UAT-DQ yang guarded/idempotent;
  data lama tidak boleh di-reset.

Verifikasi minimal:
- Tes denominator, incomplete vs blocked, invalid, duplikat, izin, review
  422/403/409, audit tanpa PII, dan regresi 03c/04b/05a/05b.
- Pint, unit test frontend, build, ESLint read-only; browser DOM bila tersedia
  dan catat NOT RUN bila tidak.
- Pastikan tidak ada nomor identitas/alamat di pesan issue atau audit.

Setelah verifikasi, siapkan PHASE-05C-REPORT.md dan
PHASE-05C-MANUAL-REVIEW.md, sinkronkan dokumen kontrak/CHANGELOG/INDEX/HANDOFF/
ROADMAP, lalu commit dan push paket review secara selektif agar pemilik dapat
meninjau di GitHub. Push bukan penerimaan; penerimaan manual dicatat pada commit
dokumentasi terpisah. Berhenti setelah paket 05c siap review; jangan lanjut
rekap sekolah atau Fase 06.
```
