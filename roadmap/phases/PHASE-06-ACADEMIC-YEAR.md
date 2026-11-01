# Fase 06 — Siklus tahun ajaran dan rombel

Status teknis: **06a IMPLEMENTED & agent-verified (2026-10-31); 06b IMPLEMENTED & agent-verified (2026-10-31); 06c IMPLEMENTED & agent-verified (2026-11-01).** Status review: **06a OWNER APPROVED 2026-10-31 WIB (#1–#12); 06b OWNER APPROVED 2026-11-01 WIB (#1–#14); 06c OWNER APPROVED 2026-11-01 WIB (#1–#16).** Tes/browser 06a/06b/06c dijalankan agen; item MariaDB/konkurensi/dua-tab tetap NOT RUN. Implementasi 06a: `d8d9874`; implementasi 06b `c1dde67` di atas `main` @ `6dcd432`; implementasi 06c `9927d8e` di atas `main` @ `da94195`.
Dependensi: 03,04; DEC-05; DEC-30. Baseline rencana: b02a87f; implementasi 06a di atas `main` @ `25875c9` (DEC-29 APPROVED).

Rencana terbaru (2026-10-31): `reports/feature-update/PHASE-06-PLAN.md` (baseline `ffa71e3`) — **PLAN APPROVED 2026-10-31 WIB (DEC-29)**; implementasi **06a** selesai dan diterima. Rencana rinci 06b tersedia di `reports/feature-update/PHASE-06B-PLAN.md` (baseline `1799cf7`) — **PLAN APPROVED 2026-10-31 WIB (DEC-30, D1–D9)** berdasarkan pernyataan **"Saya setuju dieksekusi"**; implementasi 06b selesai, agent-verified, dan **checklist #1–#14 OWNER APPROVED 2026-11-01 WIB**. Audit HEAD menemukan: `school_years` belum memiliki periode aktif/lock/tanggal; `semesters` global tanpa relasi tahun dan tanpa CRUD; CRUD Classes masih scaffold (`authorize(): false`, `class_teach_id` tidak fillable); wali kelas menunjuk `users` sehingga guru tanpa akun tidak dapat dipilih; route/menu Classes (`master.view`) tidak selaras dengan API (`students.*`); assign/transfer 03d belum memeriksa tahun locked/soft-deleted. Rencana mengusulkan urutan 06a → 06b → 06c dan UAT baru `.uat-phase06`. Rencana rinci **06c** tersedia di `reports/feature-update/PHASE-06C-PLAN.md` (baseline `953e6f7`) dan **DISETUJUI PEMILIK 2026-11-01 WIB** ("Saya setuju D1-D10.") sebagai **DEC-31 APPROVED (D1–D10)**; implementasi 06c selesai dan agent-verified 2026-11-01, checklist #1–#16 OWNER APPROVED 2026-11-01 WIB.

## Tujuan dan scope
Siklus tahun ajaran dan rombel. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 06a — Periode dan rombel
Scope: Tahun/semester aktif eksplisit; rombel dan wali kelas sesuai akun/profil keputusan; lock periode; aturan satu keanggotaan per siswa/periode jika disetujui.

Kriteria penerimaan: Year kosong/locked tertangani; create/update membership tervalidasi; siswa tidak berada di dua rombel yang dilarang.

Status implementasi 2026-10-31: **IMPLEMENTED — agent-verified; OWNER APPROVED 2026-10-31 WIB (#1–#12).** Periode aktif singleton `academic_settings` (tanpa menebak nilai awal); `school_years` tanggal + lock beralasan/aktor/audit; tahun aktif tidak dapat lock/delete; tahun locked menolak CRUD kelas dan assign/transfer; CRUD kelas berfungsi dengan nama unik per tahun case-insensitive, `expected_version` + `409`, delete ditolak bila ada riwayat termasuk soft-deleted; wali kelas dari profil `Pendidik` aktif (`class_teach_employee_id`) dengan `class_teach_id` legacy; UI School Years/Classes/Students + menu Academic; UAT `.uat-phase06`. Bukti: 20 tes akademik/145 assertions, regresi 03d 27/104, suite 437 passed/13 skipped/3.971 assertions, Pint 22 file, build 21,55 s, browser/API checklist #1–#12. Detail di `reports/feature-update/PHASE-06A-REPORT.md` dan `PHASE-06A-MANUAL-REVIEW.md`. Server UAT 14024/18024 dihentikan setelah penerimaan (DEC-27). Merge/deploy dan migrasi database operasional tidak diotorisasi; 06b/06c belum dimulai.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-06-ACADEMIC-YEAR.md.
Kerjakan subfase 06a saja: Periode dan rombel sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini tidak memberi izin merge/deploy atau mutasi data operasional; commit+push paket review mengikuti aturan repo.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 06b — Naik kelas/tinggal kelas/lulus
Scope: Preview siswa sumber, target kelas, status dan exception; commit atomik/bounded dengan token; riwayat tahun lama tidak ditimpa; hasil tiap siswa.

Kriteria penerimaan: Preview tanpa mutasi; retry aman; tinggal/lulus tidak dipindah otomatis; data sumber dan decoy tetap benar.

Status per 2026-11-01: **IMPLEMENTED — agent-verified; OWNER APPROVED 2026-11-01 WIB (#1–#14)** berdasarkan **"Pengujian nomor 1-14 sudah berhasil dan saya approved."**; agen tidak mengulang skenario. Rencana rinci di `reports/feature-update/PHASE-06B-PLAN.md` (baseline `1799cf7`) disetujui sebagai **DEC-30 APPROVED** ("Saya setuju dieksekusi"). Implementasi: preview tanpa mutasi, commit atomik ber-token (256-bit hash, 30 menit, terikat pembuat, ≤500 keputusan, `409` itemized, replay idempoten), `promote`/`retain`/`graduate`/`skip`, tabel `student_progression_batches`, riwayat (preview hanya pembuat), guard hapus tahun, UI Academic → Progression, audit tanpa NIK, dan kartu Graduated `counts[6]`. Bukti: 11 tes progresi/150 assertions, suite 448 passed/13 skipped/4.121 assertions, Pint 10 file, build 26,36 s, browser/API UAT `.uat-phase06` (commit campuran, replay, 409, view-only 403, audit). Detail: `PHASE-06B-REPORT.md` dan `PHASE-06B-MANUAL-REVIEW.md`. Server UAT 14024/18024 dihentikan 2026-11-01 setelah penerimaan (DEC-27); data `.uat-phase06` dipertahankan.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-06-ACADEMIC-YEAR.md.
Kerjakan subfase 06b saja: Naik kelas/tinggal kelas/lulus sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini tidak memberi izin merge/deploy atau mutasi data operasional; commit+push paket review mengikuti aturan repo.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 06c — Mutasi dan koreksi
Scope: Masuk/keluar bertanggal/alasan/sekolah, pembatalan berizin, rekap per periode; koreksi locked period melalui tindakan khusus ter-audit.

Kriteria penerimaan: Histori siswa dapat ditelusuri, status/tanggal konsisten, tidak menghapus biodata untuk mencatat keluar.

Status per 2026-11-01: **IMPLEMENTED — agent-verified; OWNER APPROVED 2026-11-01 WIB (#1–#16)** di atas `main` @ `da94195` (implementasi `9927d8e`), dengan otorisasi **DEC-31 APPROVED** ("Saya setuju D1-D10."). Implementasi: log immutable `student_mutations`; mutasi masuk (membership baru, entry `Mutasi Masuk`, NPSN asal) dan keluar (menutup satu membership, biodata utuh); pembatalan kompensatif dengan `expected_*_version` dan `409` itemized; rekap per tahun (cancelled dikecualikan; semester ditunda); ability `students.correct-locked` + tabel immutable `student_locked_corrections` untuk lima tindakan koreksi tahun locked; sepuluh endpoint; UI Students → Mutations + timeline; guard hapus tahun; audit tanpa alasan/PII. Bukti: 19 tes mutasi/369 assertions, regresi direktori 39/501, suite 467 passed/13 skipped/4.492 assertions, Pint 21 file, build 30,56 s, ESLint 0, browser/API `.uat-phase06` (operator in/out/cancel/re-entry, kepsek view-only 403, admin koreksi locked 0072 + approval, 390 px). Detail: `reports/feature-update/PHASE-06C-REPORT.md` dan `PHASE-06C-MANUAL-REVIEW.md`. Server UAT 14024/18024 **dihentikan 2026-11-01 setelah penerimaan** (DEC-27; PID/command line diverifikasi, port bebas, data `.uat-phase06` dipertahankan, Apache/MariaDB tidak disentuh). Merge/deploy, migrasi database operasional/MariaDB, rekap semester, dan rekap sekolah (DEC-12) tidak diotorisasi.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-06-ACADEMIC-YEAR.md.
Kerjakan subfase 06c saja: Mutasi dan koreksi sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini tidak memberi izin merge/deploy atau mutasi data operasional; commit+push paket review mengikuti aturan repo.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Belum mengubah nilai/rapor atau membuat penerimaan siswa online.

## Verifikasi minimum
Naik/tinggal/lulus/mutasi, tahun locked, concurrent/retry, wali kelas invalid, histori dan soft-delete references.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
DATABASE; REQUIREMENTS; API-CONTRACTS; ACCESS-CONTROL; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Satu rombel per periode bukan constraint student_id-rombel_id saja. Definisikan tahun vs semester sebelum unique index.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
