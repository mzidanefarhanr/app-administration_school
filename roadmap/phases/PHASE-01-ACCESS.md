# Fase 01 — Hak akses, autentikasi dan lingkungan aman

Status teknis: NOT STARTED. Status review: PENDING. Semua tes/browser/manual: NOT RUN.
Dependensi: 00; DEC-03 untuk 01a. Baseline rencana: b02a87f; audit ulang HEAD saat pelaksanaan.

## Tujuan dan scope
Hak akses, autentikasi dan lingkungan aman. Kerjakan satu subfase per instruksi pemilik; jangan mengeksekusi seluruh fase secara otomatis. Tidak ada pekerjaan implementasi yang telah dilakukan dalam penyusunan dokumen ini.

## Subfase

### 01a — Authorization backend
Scope: Tetapkan matriks role/ability disetujui; Policy/Gate named, cakupan record; users manage; self profile dan first login terikat actor; lindungi admin terakhir; review register dan status akun aktif. Semua route legacy yang tetap aktif harus diaudit.

Kriteria penerimaan: Guest/role denied/record lain ditolak server tanpa mutasi; user tidak dapat membuat admin, menghapus akun orang lain, atau mengubah status aktif lewat firstlogin; status nonaktif berlaku pada sesi yang ada.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-01-ACCESS.md.
Kerjakan subfase 01a saja: Authorization backend sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 01b — Konfigurasi API dan error auth
Scope: Ganti API hardcode dengan environment; perbarui contoh env tanpa secret; selaraskan CORS/Sanctum/cookie. Perbaiki interceptor pada instance yang benar dan tangani error tanpa response. Audit beforeEnter parent Vue yang tidak selalu berjalan saat berpindah child; sesuaikan guard dan session-expired flow. Audit reset-password frontend end-to-end.

Kriteria penerimaan: Login/logout/expired/deny dan network failure tertangani; navigasi child tidak bergantung hanya menu; URL localhost/tujuan sesuai env; tidak ada secret di bundle.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-01-ACCESS.md.
Kerjakan subfase 01b saja: Konfigurasi API dan error auth sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

### 01c — Isolasi test dan fixture
Scope: Siapkan DB/storage test dengan fail-safe, seed sintetis, audit fixture/factory pribadi; validator password/FK/identity akun; nama tidak wajib unik. Jangan rewrite history remote.

Kriteria penerimaan: Tes authorization jalan pada DB disposable; error target database tidak aman menggagalkan test; fixture tidak memuat identitas nyata.

Prompt setelah subfase ini dipilih:
```text
Baca AGENTS.md, ROADMAP, HANDOFF dan PHASE-01-ACCESS.md.
Kerjakan subfase 01c saja: Isolasi test dan fixture sesuai scope dan kriteria dokumen.
Audit HEAD dan consumer/kontrak aktual sebelum edit; keputusan terkait belum
APPROVED jangan ditebak bila menentukan perilaku. Scope ini belum memberi izin
commit/push/merge/deploy atau mutasi data operasional.
Gunakan fixture/DB/storage terisolasi; jalankan verifikasi relevan dan catat hasil
aktual. Jika browser tidak tersedia, jelaskan dan berikan checklist manual.
Sinkronkan kontrak affected, CHANGELOG, laporan, INDEX, HANDOFF dan ROADMAP.
Berhenti setelah hasil subfase siap review; jangan lanjut subfase berikutnya.
```

## Batas pekerjaan
Belum membangun CRUD siswa, surat, atau desain ulang total. Pengubahan role dalam fase ini mengikuti matriks disetujui, bukan role gudang.

## Verifikasi minimum
API langsung: guest, user nonaktif, role sah/tidak sah, ID target berbeda, payload role/status, password invalid, CSRF/cookie dan login rate limit.

Tambahkan skenario regresi yang benar-benar terdampak. Catat expected/actual dan evidence; untuk aksi mutasi assert perubahan target dan tidak berubahnya decoy. Tes rollback perlu memicu kegagalan, bukan hanya jalur sukses. Browser di luar kemampuan OpenCode tetap NOT RUN sampai bukti pemilik tersedia.

## Dokumen yang diperbarui
ACCESS-CONTROL; API-CONTRACTS; SECURITY-DATA; TESTING; README; DECISIONS; CHANGELOG; laporan subfase; INDEX; HANDOFF; ROADMAP.

## Risiko dan keputusan
Kebijakan status_user_id vs status_active perlu ditentukan. Perubahan auth wajib uji regresi; jangan membuka CORS wildcard untuk memperbaiki login.

## Syarat keluar subfase
- [ ] Scope selesai atau partial/blocker dijelaskan.
- [ ] Kontrak request/response/schema/permission yang berubah didokumentasikan.
- [ ] Uji relevan benar-benar dijalankan atau NOT RUN dengan alasan.
- [ ] Checklist manual tersedia bila dibutuhkan; persetujuan dicatat hanya setelah jawaban pemilik.
- [ ] SHA/working tree, known issues dan langkah berikutnya dicatat.
- [ ] Scope berikutnya diusulkan, belum dijalankan tanpa instruksi.
