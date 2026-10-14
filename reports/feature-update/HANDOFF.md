# Handoff pengembangan aplikasi sekolah

Checkpoint awal: 14 Oktober 2026 (WIB). Baseline source repo sekolah b02a87f0d57adffec297ed465a8afcd8e8d7ec59.

## Hasil yang tersedia
Paket Markdown untuk dipasang pemilik. Source aplikasi belum dimodifikasi oleh penyusun; tidak ada commit/push/PR baru. Runtime/test/build/browser/DB hidup NOT RUN. Seluruh fase implementasi NOT STARTED; persetujuan pemilik PENDING.
Tujuan aplikasi dan penggunaan OpenCode diketahui; role baru, format nomor surat, hosting, aturan izin dan retensi masih keputusan terbuka.

## Tugas berikutnya
Pasang dokumen ke repo lokal dengan membandingkan file existing, jalankan prompt Fase00 di docs/OPENCODE-WORKFLOW. Audit HEAD/working tree aktual dan safe testing. Sesudah laporan, review scope 01a dan DEC-03. Jangan langsung membangun seluruh roadmap.

## Blocker potensial
DB test belum diisolasi; seed berpotensi data pribadi; API hardcode; permission kosong; data siswa/pegawai CRUD belum lengkap. Jangan menggunakan data sekolah nyata untuk membuktikan fitur.

## Aturan pembaruan handoff
Catat active subphase, branch/HEAD/dirty files, scope otorisasi, hasil yang sudah selesai, bukti, NOT RUN, keputusan, blocker dan next concrete action. Checkpoint ini harus diganti ringkas saat sesi berikutnya; riwayat detail tetap di laporan/CHANGELOG.
Persetujuan manual mencantumkan cakupan dan kandidat SHA; tidak menghapus NOT RUN alur lain. Jika HEAD berubah setelah uji, periksa relevansi bukti dahulu.
