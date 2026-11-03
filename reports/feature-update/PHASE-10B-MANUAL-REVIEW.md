# Panduan Review Manual — Subfase 10b (Sesi dan Jurnal Pembelajaran)

Subfase: 10b — evaluasi deterministik sesi pembelajaran efektif harian/rentang (`GET /api/teaching/sessions`), pencatatan jurnal mengajar (`teaching_journals` — `UNIQUE (teaching_schedule_id, session_date)`), penanganan idempotent retry (`200` vs `201` vs `409`), otorisasi `own assignment` (`employees.user_id`) vs koreksi `teaching.manage` (`correction_reason`), penegakan jendela waktu `journal_grace_days = 7` hari kalender WIB (`DEC-10`), serta guard konsistensi dua arah terhadap jadwal & exception 10a.
Keputusan: **DEC-40 APPROVED** (D1–D9, 2026-11-03 WIB; **"Saya setuju D1-D9"**). Laporan teknis: `reports/feature-update/PHASE-10B-REPORT.md`; rencana: `reports/feature-update/PHASE-10B-PLAN.md`.
Baseline induk: `main` pada `1c2c48c` (pencatatan DEC-40 APPROVED di atas paket rencana `8ce345b` dan baseline 10a `a304df8`). Status teknis: **IMPLEMENTED — agent-verified (2026-11-03)**. Status review pemilik: **PENDING (#1–#18)**.

## Lingkungan UAT (terisolasi)

- SPA: `http://127.0.0.1:14028`
- API: `http://127.0.0.1:18028`
- Halaman uji utama: `http://127.0.0.1:14028/public/teaching/journals` (alias `/teaching/journals`)
- Halaman terkait (10a guard): `http://127.0.0.1:14028/public/teaching/schedules`
- Data: `.uat-phase10` SQLite (`.uat-phase10/database.sqlite`); cookie `school_phase10_session`; storage `.uat-phase10/storage`
- Launcher: `laravel-api/scripts/phase10-uat.php`
- Akun:
  - **`uat.guru`** (Guru; `teaching.view`, tertaut ke **`UAT-P10 Teacher 01`** — Matematika; hanya boleh mengisi/mengubah jurnal sesi miliknya dalam 7 hari terakhir)
  - **`uat.guru2`** (Guru kedua; `teaching.view`, tertaut ke **`UAT-P10 Teacher 02`** — Bahasa Indonesia; untuk menguji penolakan `403` lintas guru/kelas)
  - **`uat.operator`** (Operator Sekolah; `teaching.view` + **`teaching.manage`** — dapat mengisi/mengoreksi jurnal lintas guru dan lewat 7 hari dengan `correction_reason`)
  - **`uat.admin`** (Admin Sistem; `teaching.view` + **`teaching.manage`** + `audit.view`)
  - **`uat.waka`** (Wakil Kesiswaan; `teaching.view` + **`teaching.manage`**)
  - **`uat.kepsek`** (Kepala Sekolah; **`teaching.view` saja** tanpa tautan pegawai → read-only untuk seluruh sesi & jurnal, `403` untuk tulis)
  - **`uat.staff`** (Staf Non-Pengajar; **tanpa `teaching.view`** → ditolak `403`)
  - **`uat.inactive`** (Akun nonaktif → ditolak `403`)
  - Password seluruh akun UAT tersimpan hanya di file lokal `.uat-phase10/account-password.txt` (tidak dicetak di dokumen).

Perintah menyalakan server UAT untuk pengujian manual:

```powershell
# Lihat password akun UAT lokal (jangan commit/bagikan)
Get-Content D:\xampp-htdocs\app-administration_school\.uat-phase10\account-password.txt

# Terminal 1 — API (dari laravel-api)
cd D:\xampp-htdocs\app-administration_school\laravel-api
php -S 127.0.0.1:18028 scripts/phase10-uat.php

# Terminal 2 — SPA (dari vue-app; arahkan VITE_API_BASE_URL ke 18028)
cd D:\xampp-htdocs\app-administration_school\vue-app
$env:VITE_API_BASE_URL="http://127.0.0.1:18028"
npx vite --host 127.0.0.1 --port 14028
```

Perintah CLI pemeriksaan status `.uat-phase10`:

```powershell
cd D:\xampp-htdocs\app-administration_school\laravel-api
php scripts/phase10-uat.php status
```

Angka acuan `.uat-phase10` saat ini (setelah `prepare-journals` idempoten):

- **8 Akun Pengguna** (7 akun 10a + **`uat.guru2`** tertaut ke `UAT-P10 Teacher 02`).
- **2 Tahun Ajaran**:
  - `2026/2027` (`id=1`, **Active**, `2026-07-01` s.d. `2027-06-30`, semester aktif `Semester I`).
  - `2024/2025` (`id=2`, **Locked**, `locked_at = 2025-07-15 10:00:00`, memiliki 1 jadwal historis dan **1 jurnal historis** pada `2024-08-05`).
- **101 Penugasan Mengajar**, **121 Slot Jadwal Mingguan**, dan **6 Pengecualian Kalender** (utuh dari 10a).
- **66 Jurnal Pembelajaran (`teaching_journals`)**:
  - **64 jurnal reguler** sepanjang Oktober 2026 (`2026-10-01` s.d. `2026-10-27`) lintas kelas dan guru (termasuk jurnal milik `UAT-P10 Teacher 01` / `uat.guru`, jurnal milik `UAT-P10 Teacher 02` / `uat.guru2`, serta jurnal yang memiliki jejak koreksi operator `correction_reason`).
  - **1 jurnal sesi pengganti (`rescheduled`)** pada hari Sabtu **`2026-09-12`** (pindahan dari Kamis `2026-09-10`, diajar oleh guru pengganti `UAT-P10 Teacher 11` di `LAB-KOMP-1`).
  - **1 jurnal historis** pada tahun ajaran terkunci **`2024/2025`** (`session_date = 2024-08-05`).
  - Sesi pada akhir Oktober s.d. awal November 2026 (dalam jendela 7 hari terakhir) sengaja dibiarkan **belum terisi (`Pending`)** agar dapat langsung diuji pengisiannya dari UI.

## Checklist (#1–#18)

### 1. Akses menu & konteks default guru (`uat.guru`)
- Login sebagai **`uat.guru`**, klik menu sidebar **Teaching → Sessions & Journals** (`/public/teaching/journals`).
- Harapkan konteks otomatis memilih tahun ajaran aktif **2026/2027**, **Semester I**, tanggal hari ini (WIB), menampilkan kartu identitas guru tertaut (**`UAT-P10 Teacher 01`**) beserta informasi batas waktu **7-day grace window**, dan secara default memilih cakupan **My Sessions**.
- Status agen: **PASS (API/HTTP + kompilasi komponen)**; klik browser **NOT RUN**.

### 2. Evaluasi Daily Sessions Worklist pada hari kerja aktif
- Pada tab **Daily Sessions Worklist**, pilih tanggal hari Senin atau Selasa dalam 7 hari terakhir (mis. `2026-11-02` atau `2026-11-03`) dan ubah cakupan antara **My Sessions** dan **All Sessions**.
- Harapkan 4 kartu ringkasan (**Total Slots**, **Effective Sessions**, **Recorded Journals**, **Missing / Excluded**) memperbarui angka secara akurat, dan tabel menampilkan jam ke-, pukul, kelas, mata pelajaran, guru efektif, ruangan, status sesi, serta status jurnal.
- Status agen: **PASS (tes T2 + probe HTTP `.uat-phase10`)**.

### 3. Evaluasi tanggal libur sekolah (`2026-08-17`) & libur khusus kelas (`2026-09-07`)
- Ubah pemilih **Session Date** ke **`2026-08-17`** (Senin — Hari Kemerdekaan RI) dengan cakupan **All Sessions**:
  - Harapkan seluruh 20 slot jadwal hari Senin berstatus **Holiday**, `Effective Sessions = 0`, `Excluded = 20`, dan tidak ada tombol **Fill Journal** (`422` bila dikirim paksa ke API).
- Ubah **Session Date** ke **`2026-09-07`** (Senin — libur khusus kelas `UAT-P10 X-MIPA-1`):
  - Harapkan hanya slot milik `UAT-P10 X-MIPA-1` yang berstatus **Holiday**, sedangkan kelas lain tetap **Effective**.
- Status agen: **PASS (tes T2, T7 + probe HTTP `HOLIDAY_SESSIONS_EFFECTIVE=0 EXCLUDED=20`)**.

### 4. Evaluasi sesi `Cancelled` (`2026-09-08`) & `Rescheduled` (`2026-09-10` → `2026-09-12`)
- Pilih tanggal **`2026-09-08`** (Selasa) atau **`2026-09-09`** (Rabu) → harapkan slot yang dibatalkan tampil dengan badge **Cancelled** dan tidak dapat diisi jurnal.
- Pilih tanggal **`2026-09-10`** (Kamis) → harapkan slot yang dipindahkan tampil dengan badge **Moved (`rescheduled_out`)** beserta informasi tanggal tujuan `2026-09-12`.
- Pilih tanggal **`2026-09-12`** (Sabtu) → harapkan sesi pengganti tampil dengan badge **Rescheduled In**, memakai jam/ruangan pengganti (`Period 5–6`, `LAB-KOMP-1`), guru pengganti **`UAT-P10 Teacher 11`**, dan jurnalnya sudah tercatat (`Recorded`).
- Status agen: **PASS (tes T2, T6, T7)**.

### 5. Pengisian jurnal baru oleh guru pada sesinya sendiri (`Own Assignment` — `201`)
- Sebagai **`uat.guru`**, pilih tanggal dalam 7 hari terakhir (mis. `2026-11-02`), cari sesi Matematika milik `UAT-P10 Teacher 01` yang masih berstatus **Pending**, lalu klik **Fill Journal**.
- Isi **Topic / Material** (mis. `Persamaan Kuadrat dan Diskriminan`), pilih **Teaching Method** (`Lecture` / `Discussion`), isi **Activity Summary** dan **Class Notes**, lalu klik **Save Journal**.
- Harapkan jurnal berhasil disimpan (`201 Created`), status baris berubah menjadi **Recorded (`v1`)**, dan angka **Recorded Journals** bertambah 1.
- Status agen: **PASS (tes T3 + probe HTTP `GURU1_CREATE=201`)**.

### 6. Pembaruan jurnal oleh guru pada sesinya sendiri (`PUT` — `version` naik `1 -> 2`)
- Klik tombol **Edit** pada jurnal yang baru saja dibuat oleh `uat.guru`, perbarui **Topic** atau **Summary**, lalu simpan.
- Harapkan pembaruan berhasil (`200 OK`) dan versi jurnal naik menjadi **`v2`**.
- Status agen: **PASS (tes T3 + probe HTTP `UPDATE_V2=200(v2)`)**.

### 7. Ketahanan Idempotent Retry ("Retry Tidak Dobel" — `200 OK` vs `409 Conflict`)
- Bila request `POST /api/teaching/journals` untuk `(teaching_schedule_id, session_date)` yang sama dikirim ulang dengan payload fungsional yang sama persis (simulasi retry jaringan / double submit), server mengembalikan **`200 OK`** (`meta.idempotent_replay = true`) tanpa menggandakan baris dan tanpa menaikkan `version`.
- Bila dikirim ulang via `POST` dengan isi berbeda, server menolak dengan **`409 Conflict`** beserta snapshot `latest`.
- Status agen: **PASS (tes T4 + probe HTTP `REPLAY=200(true) POST_CONFLICT=409`)**.

### 8. Penolakan guru mengisi atau mengedit jurnal kelas/guru lain (`403 Forbidden`)
- Sebagai **`uat.guru`** (`UAT-P10 Teacher 01`), ubah filter ke **All Sessions** pada tanggal `2026-11-02`:
  - Perhatikan bahwa pada baris sesi milik **`UAT-P10 Teacher 02`** (atau guru lain), tombol **Fill Journal** / **Edit** tidak tersedia (hanya **View** bila sudah terisi).
  - Percobaan kirim `POST` atau `PUT` langsung ke API oleh `uat.guru` untuk sesi milik `UAT-P10 Teacher 02` (atau sebaliknya oleh **`uat.guru2`** terhadap jurnal `uat.guru`) ditolak tegas dengan **`403 Forbidden`**.
- Status agen: **PASS (tes T5 + probe HTTP `CROSS_TEACHER=403` & `GURU2_EDIT_GURU1=403`)**.

### 9. Penolakan tanggal masa depan (`session_date > today WIB` — `422`)
- Pilih tanggal di masa depan (mis. minggu depan di bulan November 2026).
- Harapkan sesi menampilkan keterangan bahwa jurnal belum dapat diisi untuk tanggal masa depan (`can_record = false`), dan pengiriman paksa ke API ditolak **`422`** baik bagi guru maupun operator.
- Status agen: **PASS (tes T7)**.

### 10. Batas waktu 7 hari kalender WIB (`journal_grace_days = 7` — `422` bagi guru)
- Sebagai **`uat.guru`**, pilih tanggal sesi yang sudah lewat lebih dari 7 hari (mis. **`2026-10-19`** atau **`2026-10-20`**).
- Harapkan sesi yang belum terisi menampilkan badge **Grace Expired**, tombol **Fill Journal** maupun **Edit** dinonaktifkan bagi guru, dan percobaan `POST`/`PUT` oleh guru ditolak **`422`** (*"The 7-day submission window for this session has expired..."*).
- Status agen: **PASS (tes T8)**.

### 11. Input susulan & koreksi lintas guru oleh Operator (`teaching.manage` + `correction_reason`)
- Logout lalu login sebagai **`uat.operator`** (`teaching.manage`):
  - Buka tanggal **`2026-10-19`** (lewat 7 hari) dan klik **Fill Journal** pada sesi yang belum terisi, **atau** klik **Edit** pada jurnal milik guru di tab **Recorded Journals History**.
  - Coba simpan tanpa mengisi **Correction Reason** → harapkan ditolak **`422`**.
  - Isi **Correction Reason** (`10–255` karakter, mis. `Late entry recorded by operator based on signed physical class logbook.`) lalu simpan → harapkan berhasil (`201`/`200`) dan menampilkan badge **Corrected**.
- Status agen: **PASS (tes T8, T9)**.

### 12. Konflik versi basi (`409 Conflict`) + tombol `Reload Latest`
- Uji pembaruan jurnal dengan `expected_version` basi.
- Harapkan API mengembalikan **`409 Conflict`** beserta objek `latest`, dan dialog menampilkan banner peringatan konflik dengan tombol **Reload Latest** yang memuat ulang data versi terbaru ke dalam form.
- Status agen: **PASS (tes T11 + probe HTTP `STALE_PUT=409`)**; dua tab browser nyata **NOT RUN**.

### 13. Perlindungan tahun ajaran terkunci (`2024/2025` — read-only `422`)
- Ubah pemilih **School Year** di bagian atas halaman ke **2024/2025 (Locked)** dan buka tab **Recorded Journals History** (atau pilih tanggal `2024-08-05` di **Daily Sessions Worklist**).
- Harapkan muncul banner peringatan bahwa tahun ajaran terkunci bersifat read-only, jurnal historis (`Pengenalan Aljabar Linear Dasar — Kelas X`) tetap dapat dilihat (**View**), seluruh tombol **Fill Journal** / **Edit** disembunyikan, dan percobaan tulis ke API pada tahun `2024/2025` ditolak **`422`** bahkan untuk `uat.operator`.
- Status agen: **PASS (tes T10)**.

### 14. Guard konsistensi terhadap penghapusan jadwal & pembuatan exception 10a (`422`)
- Sebagai **`uat.operator`**, buka halaman **Teaching → Assignments & Timetable** (`/public/teaching/schedules`):
  - Coba hapus (`DELETE`) slot jadwal yang sudah memiliki jurnal di bulan Oktober 2026 → harapkan ditolak **`422`** (*"Cannot delete a teaching schedule that already has recorded teaching journals..."*).
  - Coba buat **Schedule Exception** (`Cancelled`, `Holiday`, atau `Rescheduled`) pada tanggal Oktober 2026 yang sesinya sudah memiliki jurnal tercatat → harapkan ditolak **`422`**.
- Status agen: **PASS (tes T12)**.

### 15. Tab `Recorded Journals History` — filter, pencarian, dan paginasi (`66` jurnal)
- Kembali ke `/public/teaching/journals`, buka tab **Recorded Journals History**.
- Uji filter berdasarkan **Class**, **Subject**, **Teacher**, **Method** (mis. `Practicum`), **Source** (`Rescheduled`), rentang tanggal (`Date From` / `Date To`), serta pencarian kata kunci topik.
-Status agen: **PASS (tes T3 + probe HTTP `TOTAL=66`)**.

### 16. Pembatasan akses per peran (`uat.kepsek` read-only; `uat.staff` `403`; privasi audit)
- Login sebagai **`uat.kepsek`**: dapat melihat seluruh sesi dan detail 66 jurnal di kedua tab secara read-only, tanpa tombol tambah/edit (`POST`/`PUT` mengembalikan `403`).
- Login sebagai **`uat.staff`** (tanpa `teaching.view`): menu **Teaching** tidak tampil dan akses ke `/api/teaching/sessions` maupun `/api/teaching/journals` mengembalikan **`403`**.
- Login sebagai **`uat.admin`** (`audit.view`): periksa log audit `/api/activities` untuk `TeachingJournal` → pastikan hanya mencatat atribut struktural (`session_date`, `method`, `version`, dll.) dan **tidak membocorkan** teks bebas `topic`, `summary`, `notes`, maupun `correction_reason`.
- Status agen: **PASS (tes T1, T3 + probe HTTP `STAFF_SESSIONS=403`, `KEPSEK_GET=200 POST=403`)**.

### 17. Tampilan responsif (1440×900 & 390×844), keyboard/aria, dan console bersih
- Periksa halaman `/public/teaching/journals` (kedua tab dan dialog Fill/Edit/View Journal) pada lebar desktop 1440×900 dan mobile 390×844.
- Harapkan tabel dapat digulir secara rapi, dialog dapat ditutup dengan `Esc`, seluruh label berbahasa Inggris, dan 0 console error.
- Status agen: **NOT RUN** di browser otomatis (ESLint 0 error & `npm run build` PASS).

### 18. Penghentian server UAT setelah review selesai (`DEC-27`)
- Server UAT `.uat-phase10` sudah dihentikan oleh agen pada 2026-11-03 WIB setelah verifikasi teknis (`UAT_PORTS_18028_14028_STOPPED_CLEANLY`, `.uat-phase10` dipertahankan dengan `66` jurnal, Apache/MariaDB tidak disentuh).
- Bila Anda menyalakan server UAT untuk menjalankan checklist di atas, hentikan kembali setelah selesai dengan memeriksa PID listener pada port `18028` dan `14028`:

```powershell
Get-NetTCPConnection -LocalPort 18028,14028 -State Listen -ErrorAction SilentlyContinue |
  Select-Object LocalAddress,LocalPort,OwningProcess
# Verifikasi CommandLine proses terlebih dahulu, lalu hentikan PID terkait:
# taskkill /PID <PID_18028> /T /F
# taskkill /PID <PID_14028> /T /F
```

## Cara melaporkan hasil

Sampaikan nomor yang berhasil/gagal (mis. **"Pengujian nomor 1-18 sudah berhasil dan saya approved."** atau daftar temuan per nomor). Jangan mengubah data operasional atau me-reset `.uat-phase10`.
