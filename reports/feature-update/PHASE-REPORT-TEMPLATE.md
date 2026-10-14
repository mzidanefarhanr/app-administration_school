# Laporan subfase <ID> — <Judul>

TEMPLATE; semua placeholder harus diisi berdasarkan sesi nyata. Jangan menandai file ini sebagai laporan pelaksanaan.

- Tanggal WIB:
- Mode: source review / implementasi / koreksi / verifikasi
- Scope instruksi pemilik:
- Branch / baseline SHA / kandidat diuji:
- Working tree sebelum/sesudah:
- Lingkungan (OS/PHP/Node/DB/browser):
- Status teknis: NOT STARTED / IN PROGRESS / PARTIAL / BLOCKED / IMPLEMENTED / VERIFIED
- Status review: PENDING / CHANGES REQUESTED / APPROVED

## 1. Outcome dan cakupan
Apa berubah dan perilaku sebelum/sesudah. Subfase berikutnya yang tidak dijalankan.

## 2. File dan kontrak
| File/endpoint | Perubahan | Permission/schema/payload affected |
|---|---|---|
| <actual> | <actual> | <actual> |

## 3. Evidence
| ID | Metode / command / langkah | Fixture / role / objek | Expected | Actual | Status | Artefak/SHA |
|---|---|---|---|---|---|---|
| <id> | <actual> | <sintetis> | <expected> | <actual> | NOT RUN | <actual> |

Lampirkan command dan hasil/jumlah test aktual. Catat database/storage isolation dan decoy assertions. Source review, build dan browser merupakan bukti berbeda.

## 4. UAT manual
Checklist ringkas berurutan dengan URL, akun fixture/peran, langkah dan expected. Catat jawaban pemilik per nomor, tanggal, kandidat SHA dan hasil. Sebelum hasil ada tetap NOT RUN/PENDING. Jangan menaruh password atau data pribadi dalam laporan.

## 5. Risiko, blocker dan keputusan
Temuan baseline vs regresi; issue ID; rujuk DECISIONS; dampak dan next action. Transaksi rollback/idempotency yang belum diuji disebut eksplisit.

## 6. Sinkronisasi dokumen
CHANGELOG, ROADMAP, INDEX, HANDOFF dan kontrak domain updated sesuai fakta. Commit/push/merge/deploy status aktual; jangan menulis SHA belum tercipta.

## 7. Langkah berikutnya
Satu subfase konkret, prasyarat, scope yang perlu dipilih pemilik. Tidak menganggap laporan ini sebagai izin lanjut otomatis.
