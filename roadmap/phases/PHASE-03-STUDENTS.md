# Fase 03 — Pendataan siswa lengkap dan aman

Status teknis: NOT STARTED. Status review: PENDING. Semua tes/browser/manual: NOT RUN.
Dependensi: 01; 02 sebelum data operasional; DEC-05. Baseline rencana: b02a87f; audit ulang HEAD saat pelaksanaan.

## Tujuan dan scope
Pendataan siswa lengkap dan aman. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 03a — Identitas dan membership migration
Scope: Rancang student_id additive untuk student_rombels; audit backfill NIK/orphan/duplikat tanpa expose data. Normalisasi null dan identitas string; schema/model mismatch bantuan/status diperbaiki sesuai source; umur dihitung.

Kriteria penerimaan: Siswa tanpa NIK tetap dapat dicatat dan diberi membership; NIK diperbaiki tidak memutus relasi; backfill count dan exception terbukti; unique constraint sesuai kebijakan.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-03-STUDENTS.md.
Kerjakan subfase 03a saja: Identitas dan membership migration sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 03b — CRUD dan form siswa
Scope: Implementasikan store/show/update/arsip/restore dengan FormRequest sah, validator server, resources minimal/sensitif berizin, transaksi audit, version atomik. UI detail/biodata/keluarga/alamat dan pesan error; tidak membuat akun login siswa otomatis.

Kriteria penerimaan: Tambah/ubah/arsip/restore nyata; nama sama diterima; identitas duplikat/invalid/FK tidak sah ditolak; stale update 409; record lain utuh; parent/guardian tersimpan benar.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-03-STUDENTS.md.
Kerjakan subfase 03b saja: CRUD dan form siswa sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 03c — Daftar dan rekap siswa
Scope: Pagination/search/filter server; pertahankan filter tahun/rombel/status; empty state tahun aktif tidak tersedia; agregasi chart sesuai filter; ekspor berizin diselaraskan fase05.

Kriteria penerimaan: Jumlah tabel/chart sesuai dataset/filter; data sensitif tidak bocor; tahun tanpa data tidak crash; light/dark dan mobile diuji atau manual NOT RUN jelas.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-03-STUDENTS.md.
Kerjakan subfase 03c saja: Daftar dan rekap siswa sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Belum membangun kenaikan kelas massal, portal siswa/orang tua, atau sinkronisasi pemerintah.

## Verifikasi minimum
CRUD lengkap, null identity, duplikat termasuk soft delete, version race, FK scope, pagination, chart dan audit dengan fixture sintetis.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
DATABASE; API-CONTRACTS; REQUIREMENTS; ACCESS-CONTROL; desain form; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Peralihan FK NIK additive; jangan drop kolom lama sebelum seluruh consumer dipindahkan. Tentukan restore vs duplicate sebelum implementasi.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
