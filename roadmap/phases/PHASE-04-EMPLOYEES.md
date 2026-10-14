# Fase 04 — Guru dan pegawai

Status teknis: NOT STARTED. Status review: PENDING. Semua tes/browser/manual: NOT RUN.
Dependensi: 01; 02; DEC-04. Baseline rencana: b02a87f; audit ulang HEAD saat pelaksanaan.

## Tujuan dan scope
Guru dan pegawai. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 04a — Schema profil dan hubungan akun
Scope: Tetapkan akun opsional atau wajib sesuai keputusan; backfill FK stabil tanpa mengubah NIK sembarangan; teacher vs pegawai berdasarkan tipe dan penugasan; review required subject_id yang tidak cocok semua pegawai. Koreksi relasi typo dengan consumer.

Kriteria penerimaan: Profil pegawai tanpa mata pelajaran tetap sah sesuai kebijakan; akun/login terpisah dari kepegawaian; backfill tidak kehilangan histori.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-04-EMPLOYEES.md.
Kerjakan subfase 04a saja: Schema profil dan hubungan akun sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 04b — CRUD dan menu aktif
Scope: Selesaikan controller/request/resource, biodata, riwayat pendidikan/pekerjaan, status, arsip/restore, izin field, version bila ditambahkan, audit. Menu Teachers/Employees aktif ke halaman nyata.

Kriteria penerimaan: Aksi tersimpan, jenis pegawai terfilter benar, profil sendiri terbatas, download/export sesuai izin, nama sama dan data opsional valid.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-04-EMPLOYEES.md.
Kerjakan subfase 04b saja: CRUD dan menu aktif sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Multi-penugasan mengajar detail dikerjakan Fase10; tidak menambahkan payroll/absensi pegawai.

## Verifikasi minimum
Role/own/other, invalid FK, status keluar, soft-delete relasi, audit dan data pribadi; tabel dan form panjang mobile.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
DATABASE; REQUIREMENTS; ACCESS-CONTROL; API-CONTRACTS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Employee baseline subject tunggal dan relasi akun by NIK; perubahan harus menjaga data existing.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
