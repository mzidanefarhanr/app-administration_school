# Fase 06 — Siklus tahun ajaran dan rombel

Status teknis: NOT STARTED. Status review: PENDING. Semua tes/browser/manual: NOT RUN.
Dependensi: 03,04; DEC-05. Baseline rencana: b02a87f; audit ulang HEAD saat pelaksanaan.

Rencana terbaru (2026-10-31): `reports/feature-update/PHASE-06-PLAN.md` (baseline `ffa71e3`) — **PLAN APPROVED 2026-10-31 WIB (DEC-29)**; implementasi **06a** diotorisasi, 06b/06c belum. Audit HEAD menemukan: `school_years` belum memiliki periode aktif/lock/tanggal; `semesters` global tanpa relasi tahun dan tanpa CRUD; CRUD Classes masih scaffold (`authorize(): false`, `class_teach_id` tidak fillable); wali kelas menunjuk `users` sehingga guru tanpa akun tidak dapat dipilih; route/menu Classes (`master.view`) tidak selaras dengan API (`students.*`); assign/transfer 03d belum memeriksa tahun locked/soft-deleted. Rencana mengusulkan urutan 06a → 06b → 06c dan UAT baru `.uat-phase06`.

## Tujuan dan scope
Siklus tahun ajaran dan rombel. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 06a — Periode dan rombel
Scope: Tahun/semester aktif eksplisit; rombel dan wali kelas sesuai akun/profil keputusan; lock periode; aturan satu keanggotaan per siswa/periode jika disetujui.

Kriteria penerimaan: Year kosong/locked tertangani; create/update membership tervalidasi; siswa tidak berada di dua rombel yang dilarang.

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
