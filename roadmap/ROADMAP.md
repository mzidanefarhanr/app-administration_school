# Roadmap update fitur aplikasi sekolah

Versi dokumen: 1.0, 14 Oktober 2026 (WIB). Baseline `main` @ `b02a87f0d57adffec297ed465a8afcd8e8d7ec59`.
Status: DRAFT terstruktur. Penyusunan dokumen selesai; seluruh implementasi fase belum dimulai. Tujuan utama berasal instruksi pemilik; detail target/role/kebijakan masih usulan sampai dipilih.

## Urutan dan prioritas
Fondasi keamanan + backup dahulu; penyelesaian pendataan; pengolahan massal dan tahun ajaran; surat/izin/arsip; pembelajaran; analisis; optimasi dan QA. Backup didahulukan pada Fase02 dan diuji ulang setelah seluruh modul lampiran berkembang. Data operasional tidak digunakan sebelum fondasi dan restore drill siap.

| Fase | Scope | Dependensi | Status teknis | Review |
|---|---|---|---|---|
| 00 | [Audit baseline dan kesiapan lingkungan](phases/PHASE-00-BASELINE.md) | Tidak ada | NOT STARTED | PENDING |
| 01 | [Hak akses, autentikasi dan lingkungan aman](phases/PHASE-01-ACCESS.md) | 00; DEC-03 untuk 01a | NOT STARTED | PENDING |
| 02 | [Audit terpercaya dan backup yang dapat dipulihkan](phases/PHASE-02-BACKUP-AUDIT.md) | 01; DEC-02 dan DEC-09 untuk backup | NOT STARTED | PENDING |
| 03 | [Pendataan siswa lengkap dan aman](phases/PHASE-03-STUDENTS.md) | 01; 02 sebelum data operasional; DEC-05 | NOT STARTED | PENDING |
| 04 | [Guru dan pegawai](phases/PHASE-04-EMPLOYEES.md) | 01; 02; DEC-04 | NOT STARTED | PENDING |
| 05 | [Impor, ekspor dan kualitas data](phases/PHASE-05-IMPORT-EXPORT.md) | 03,04; DEC-08 | NOT STARTED | PENDING |
| 06 | [Siklus tahun ajaran dan rombel](phases/PHASE-06-ACADEMIC-YEAR.md) | 03,04; DEC-05 | NOT STARTED | PENDING |
| 07 | [Persuratan dan generator dokumen](phases/PHASE-07-LETTERS.md) | 01,02; siswa/guru siap untuk auto-fill; DEC-06,11 | NOT STARTED | PENDING |
| 08 | [Perizinan siswa dan guru](phases/PHASE-08-LEAVES.md) | 01,03,04; file privat Fase07a atau service ekuivalen; DEC-07 | NOT STARTED | PENDING |
| 09 | [Arsip dokumen terpusat](phases/PHASE-09-ARCHIVES.md) | File privat Fase07; DEC-13 | NOT STARTED | PENDING |
| 10 | [Administrasi pembelajaran](phases/PHASE-10-TEACHING.md) | 04,06; DEC-10 | NOT STARTED | PENDING |
| 11 | [Dashboard operator dan laporan](phases/PHASE-11-ANALYTICS.md) | Modul sumber 03–10; boleh bertahap setelah sumber siap | NOT STARTED | PENDING |
| 12 | [Performa, observability dan konsistensi](phases/PHASE-12-PERFORMANCE.md) | Modul aktif; audit 00 | NOT STARTED | PENDING |
| 13 | [QA akhir, rilis dan handoff operasional](phases/PHASE-13-QA-RELEASE.md) | Fase yang dipilih selesai; keputusan kritis ditutup | NOT STARTED | PENDING |

Setiap fase memiliki subfase, prompt, kriteria dan bukti. Fase11 dashboard dapat bertahap berdasarkan modul sumber yang sudah siap; tidak harus menunggu semua modul untuk mengganti widget demo, tetapi jangan menampilkan angka palsu untuk modul belum tersedia.

## Milestone yang bisa dirilis terpisah
| Milestone | Isi | Prasyarat |
|---|---|---|
| M1 Pendataan | Fase01–06 | Hak akses, backup/restore, CRUD, impor, riwayat periode dan UAT scope |
| M2 Administrasi | M1 + Fase07–09 | Surat, izin, file privat dan UAT scope |
| M3 Pembelajaran | M2 + Fase10–11 | Jadwal, jurnal, missing report dan dashboard nyata |
| M4 Operasional | Scope terpilih + Fase12–13 | Ukuran data/performansi, restore drill kandidat rilis dan runbook |

Ini opsi scope, bukan izin deploy. Fase13 QA dapat diterapkan pada tiap milestone terpilih; fase lain tetap NOT STARTED/deferred bila belum dipilih.

## Aturan status
Teknis: NOT STARTED → IN PROGRESS → PARTIAL/BLOCKED/IMPLEMENTED → VERIFIED untuk cakupan bukti jelas. Review: PENDING → APPROVED/CHANGES REQUESTED. Evidence: SOURCE REVIEW/NOT RUN/PASS/FAIL/BLOCKED/N/A per skenario.
VERIFIED teknis tidak sama dengan APPROVED pemilik; APPROVED manual tidak mengubah skenario belum diuji menjadi PASS. Jangan satu status global menutupi role/backup/browser NOT RUN.

## Kendali scope
Satu subfase = scope reviewable. Branch mengikuti kondisi repo dan instruksi pemilik; contoh nama `feat/school-03b-student-crud`. Commit kecil per concern jika diotorisasi; prefix docs/fix/feat/test/chore sesuai perubahan. Tidak merge main otomatis. Tidak migrasi framework, multi-school, portal publik, pembayaran, AI/OCR atau API Dapodik otomatis tanpa scope tambahan.

## Penggunaan pertama
Baca [prompt OpenCode](../docs/OPENCODE-WORKFLOW.md), jalankan 00a–00b audit saja sesuai instruksi awal, lalu review keputusan untuk 01a. Update baseline source jika HEAD berubah. Tidak mengeksekusi fase lain hanya karena ada dalam roadmap.

## Status penyusunan paket
- [x] Source repo sekolah dibaca.
- [x] Pola dokumentasi App Gudang ditinjau dan diadaptasi.
- [x] Paket Markdown disiapkan; link/struktur diperiksa pada penyusunan.
- [ ] Paket dipasang/direview dalam repo pemilik.
- [ ] Fase00 runtime inventory oleh OpenCode.
- [ ] Implementasi dan UAT fase terpilih.
