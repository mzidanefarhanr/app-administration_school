# Fase 05 — Impor, ekspor dan kualitas data

Status teknis: NOT STARTED. Status review: PENDING. Semua tes/browser/manual: NOT RUN.
Dependensi: 03,04; DEC-08. Baseline rencana: b02a87f; audit ulang HEAD saat pelaksanaan.

## Tujuan dan scope
Impor, ekspor dan kualitas data. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 05a — Impor siswa
Scope: Template/version, mapping kolom, preview tanpa mutasi, validasi baris/FK/identity, mode insert/update eksplisit, hasil dan error download privat; batch token idempotent. Pilih library sesuai stack/dependency setelah review; jangan menyatakan Excel library sudah terpasang.

Kriteria penerimaan: Leading zero utuh; preview tidak mengubah DB; kasus campuran error mengikuti kebijakan; retry tidak menduplikasi; update tidak menimpa record luar scope.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-05-IMPORT-EXPORT.md.
Kerjakan subfase 05a saja: Impor siswa sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 05b — Ekspor dan impor pegawai
Scope: Ekspor seluruh hasil filter dengan pilihan kolom/izin, rekap sekolah sesuai format contoh disepakati; escape formula CSV berbahaya; impor pegawai memakai mesin dan rule domain sendiri. Dapodik hanya pencocokan file bila dipilih.

Kriteria penerimaan: Filter list/export konsisten, sensitive columns dibatasi, spreadsheet formula tidak aktif dari input tak tepercaya, error dilaporkan per baris.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-05-IMPORT-EXPORT.md.
Kerjakan subfase 05b saja: Ekspor dan impor pegawai sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 05c — Pemeriksaan kelengkapan
Scope: Aturan kelengkapan kontekstual, daftar duplikat/invalid/missing, link ke record berizin, status review operator; jangan auto-merge orang berdasar nama.

Kriteria penerimaan: Angka kelengkapan dengan denominator jelas; siswa NIK belum tersedia dibedakan incomplete vs blocked sesuai aturan; correction ter-audit.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-05-IMPORT-EXPORT.md.
Kerjakan subfase 05c saja: Pemeriksaan kelengkapan sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Tidak menggunakan API Dapodik tak diketahui atau menebak format resmi. Format F8355/KJP/PPDB membutuhkan contoh sekolah, bukan label menu saja.

## Verifikasi minimum
File salah format/ukuran, identitas numeric/leading zero, batch campuran, idempotency, izin, seluruh halaman export, CSV injection.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
REQUIREMENTS; API-CONTRACTS; DATABASE; TESTING; DECISIONS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Bulk dapat partial jika kebijakan memilih per-row; laporan tidak boleh menyebut seluruh batch sukses ketika sebagian gagal.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
