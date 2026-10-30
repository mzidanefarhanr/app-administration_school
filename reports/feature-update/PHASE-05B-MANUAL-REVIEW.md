# Checklist Review Manual — Subfase 05b (Impor Pegawai & Pilihan Kolom Ekspor)

Tanggal: 2026-10-31 WIB. Baseline implementasi: `ad74309` (di atas `4f1fec0`). Status: **OWNER APPROVED 2026-10-31 WIB — seluruh #1–#14**. Checklist ini bukan bukti agen; agen **tidak** menjalankan browser untuk 05b (otomasi diblokir kebijakan sesi).

## Status penerimaan pemilik

- Pernyataan pemilik 2026-10-31 WIB: **"Pengujian nomor 1-14 sudah berhasil dan saya approved."**
- Cakupan: seluruh skenario #1–#14 pada lingkungan UAT terisolasi `.uat-phase05`. Pernyataan pemilik adalah satu-satunya bukti penerimaan; agen tidak mengulang skenario.
- Batas: penerimaan tidak mengotorisasi merge, deploy, migrasi data legacy/MariaDB/produksi, perubahan dependency/service, atau 05c. Item NOT RUN agen tetap NOT RUN.

## Lingkungan

- Server UAT 05 (SPA 14023/API 18023) sudah **dihentikan 2026-10-31** setelah penerimaan, sesuai kebijakan siklus UAT per fase (DEC-27). Data/storage `.uat-phase05` dipertahankan; Apache/MariaDB tidak disentuh.
- Untuk mengulang review: dari `laravel-api` jalankan `C:\xampp\php82-operational\php.exe -S 127.0.0.1:18023 -t public scripts/phase05-uat.php`; dari `vue-app` jalankan `npm run dev -- --host 127.0.0.1 --port 14023 --strictPort`.
- SPA: `http://127.0.0.1:14023` (Vite dev, source working tree)
- API: `http://127.0.0.1:18023` (PHP built-in)
- Database: `.uat-phase05/database.sqlite` (terisolasi; migration `2029_07_28_000001` sudah diterapkan)
- Akun:
  - `uat.operator` — `admin-sistem`/`operator-sekolah`: `employees.view`, `employees.manage`, `employees.export`, `employees.import`
  - `uat.kepsek` — `kepala-sekolah`: `employees.view`, `employees.export` (tanpa manage/import)
- Password UAT hanya dibaca dari runtime privat `.uat-phase05/account-password.txt`; jangan menyalinnya ke laporan/Git.
- Sampel: `.uat-phase05/samples/employees-import-valid.csv`, `employees-import-mixed.csv`, `employees-import-update.csv`.
- Data awal: 200 pegawai aktif (100 Teachers/100 Staff), 409 siswa aktif.

## Skenario

| # | Skenario | Langkah | Harapan |
|---|---|---|---|
| 1 | Izin tombol Import | Login `uat.kepsek` → Teachers | Tombol **Import CSV** tidak tampil; tombol Export tetap ada. Tidak ada error console. |
| 2 | Penolakan server impor | Sebagai `uat.kepsek`, panggil `POST /api/imports/employees` (DevTools/curl dengan sesi yang sama) | `403`; tidak ada batch/pegawai baru. |
| 3 | Template 22 kolom | Sebagai `uat.operator` → Employees → Import CSV → **Download Template** | File `employee-import-template.csv`; header English 22 kolom: Name, NIK, NUPTK, Employee Type, Employee Status, Employment, Subject, Gender, Religion, Marital Status, Regency, District, TMT, Exit Date, Place of Birth, Date of Birth, Address, RT, RW, Zip Code, Phone, Email. |
| 4 | Insert valid + leading zero | Import `employees-import-valid.csv`, mode Insert → Preview → Commit | Preview 3 baris `Insert`; commit sukses; toast 3 imported; NIK `0580000000000001` tetap berawalan nol (bukan 580…); 2 Pendidik punya Subject, 1 Tenaga Kependidikan tanpa Subject; total pegawai 200→203. |
| 5 | File campuran ditolak utuh | Import `employees-import-mixed.csv`, mode Insert | Status **Needs fixes**, 1 valid/2 invalid; tombol Commit tidak tampil; error: Employee Status `Honorer UAT` tidak dikenal, NIK `12345` bukan 16 digit, Gender `Unknown Value`, District wajib; **Download Error Report** menghasilkan CSV `Row,Field,Message,NIK,NUPTK`; jumlah pegawai tetap 203. |
| 6 | Update parsial via NIK | Setelah #4, import `employees-import-update.csv`, mode Update → Commit | Preview `Update` untuk NIK `0580000000000001`; hanya Employee Status (`Pegawai Tetap Yayasan`), Address (`Synthetic Updated Address 0580000000000001`), dan Phone (`081200009001`) berubah; nama/tipe/Subject/TMT tetap; total tetap 203; versi record naik. |
| 7 | NIK immutable / tanpa match | Ubah NIK pada file update menjadi `0580000000009999`, import mode Update | Baris error "tidak ditemukan" (bukan membuat pegawai baru); tidak ada mutasi. |
| 8 | Subject Teacher/Staff | Buat CSV: (a) Pendidik tanpa Subject; (b) Tenaga Kependidikan dengan Subject; (c) update pegawai NIK `0580000000000001` ke Tenaga Kependidikan (Subject kosong) | (a) error "Teachers must have a subject."; (b) error "Non-teaching employees must not have a subject."; (c) commit sukses dan Subject tersimpan menjadi kosong. Pulihkan data bila perlu dengan update berikutnya. |
| 9 | Regency+District berpasangan | CSV dengan Regency `Synthetic Regency` + District `Synthetic District` (valid), lalu District kosong, lalu pasangan tidak cocok | Baris valid diterima; District kosong/ pasangan tidak cocok → error baris; tidak ada penebakan. |
| 10 | Duplikat NIK termasuk arsip | Import NIK yang sudah ada (aktif), lalu NIK milik record arsip (bila ada) | Keduanya error duplikat; tidak menimpa/memulihkan record. |
| 11 | Export default 10 kolom | Sebagai `uat.operator`, Export CSV → biarkan **Defaults** → Download | CSV berisi tepat 10 kolom operasional 04b (Name … Archived At), tanpa NIK/PII; filter daftar aktif ikut terbawa. |
| 12 | Kolom PII hanya manager | Sebagai `uat.operator`, pilih beberapa kolom sensitif (NIK, Address, Phone) → Download; lalu sebagai `uat.kepsek` buka dialog Export | Operator: CSV memuat kolom sensitif; `uat.kepsek`: kelompok sensitif tidak tampil, dan permintaan `columns=nik` lewat API → `403` (bukan CSV tanpa kolom). |
| 13 | Batas berkas/baris & kolom | Unggah file >5 MB atau >2.000 baris; minta `columns=tidak_ada` | `422` dengan pesan English; tidak ada pemotongan diam-diam; kolom tak dikenal tidak diabaikan. |
| 14 | Mode arsip & audit | Aktifkan **Archived** di daftar pegawai | Tombol Import CSV tersembunyi; export tetap mengikuti izin; setelah commit, detail pegawai menunjukkan perubahan dan riwayat audit domain tanpa PII berlebih. |

## Catatan review

- Jalankan #4 sebelum #6 agar NIK target update ada.
- Setelah #5, tidak ada baris yang ditulis (all-or-nothing); total harus tetap 203.
- Bila ingin mengulang #4, gunakan NIK baru atau hapus/arsipkan record hasil uji melalui UI (jangan edit SQLite langsung).
- Item NOT RUN agen: browser/visual, MariaDB/InnoDB, two-tab concurrency, retensi otomatis, rekap sekolah, XLSX. Penerimaan pemilik 2026-10-31 tidak mengubah item ini menjadi PASS.
- Status akhir 2026-10-31 WIB: **#1–#14 APPROVED** berdasarkan pernyataan pemilik; tidak ada pengujian ulang oleh agen.
