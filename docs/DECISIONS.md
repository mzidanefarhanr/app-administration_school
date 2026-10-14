# Register keputusan

Status awal: keputusan belum dikonfirmasi kecuali tujuan umum dan penggunaan OpenCode. Agent boleh mengusulkan nilai, tetapi keputusan penting tidak dicatat APPROVED tanpa jawaban pemilik.

| ID | Keputusan | Usulan awal / pertanyaan | Status | Fase paling lambat |
|---|---|---|---|---|
| DEC-01 | Cakupan sekolah | Satu sekolah untuk awal; multi-sekolah di luar scope | PROPOSED | 00 |
| DEC-02 | Lingkungan operasional | Local/LAN atau hosting; lokasi DB/storage; batas biaya | OPEN | 01b/02 |
| DEC-03 | Role dan kemampuan | Admin, Operator, Kepala sekolah, Guru/wali kelas; siapa boleh sign/approve/export | PROPOSED | 01a |
| DEC-04 | Hubungan pegawai–akun | Profil boleh tanpa login; teacher assignment memakai profil pegawai | PROPOSED | 04a |
| DEC-05 | Identitas dan keanggotaan | NIK boleh kosong; satu rombel aktif per siswa/periode; NIS unik lingkup sekolah | PROPOSED | 03a/06a |
| DEC-06 | Format nomor dan persetujuan surat | Format, reset counter, pejabat penandatangan, kapan nomor diterbitkan | OPEN | 07b |
| DEC-07 | Jenis dan approval izin | Siswa/guru, approver, delegasi, perubahan/pembatalan keputusan | OPEN | 08a |
| DEC-08 | Impor data campuran | Preview wajib; default usulan validasi semua sebelum commit per batch kecil; update hanya eksplisit | PROPOSED | 05a |
| DEC-09 | Backup dan retensi | Lokasi, jadwal, RPO/RTO, enkripsi, lama simpan | OPEN | 02b |
| DEC-10 | Jadwal dan jurnal | Jam/sesi, exception libur/cancel/reschedule, batas waktu pengisian | OPEN | 10a |
| DEC-11 | Template dokumen | Kop, tanda tangan, stempel, jenis surat pertama dan format rekap yang benar-benar dipakai | OPEN | 07a/07c |
| DEC-12 | Laporan eksternal | Format ekspor sekolah; Dapodik via rekonsiliasi file dahulu, tanpa API otomatis | PROPOSED | 05b/11b |
| DEC-13 | Retensi data pribadi | Arsip siswa/alumni, audit, lampiran; siapa boleh memusnahkan | OPEN | 09b/13 |

## Format pencatatan keputusan
Tanggal WIB; ID; konteks; alternatif; pilihan pemilik dan bukti instruksi; dampak schema/API/UI; fase affected; status OPEN/PROPOSED/APPROVED/SUPERSEDED. Pertahankan riwayat bila keputusan berubah.
Jangan membebani pemilik dengan seluruh pertanyaan sekaligus. Fase 00 dapat audit tanpa jawaban; tanyakan hanya keputusan yang menghambat subfase berikutnya. Pekerjaan independen yang telah diotorisasi boleh terus berjalan.
