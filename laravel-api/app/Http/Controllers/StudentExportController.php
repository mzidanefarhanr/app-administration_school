<?php

namespace App\Http\Controllers;

use App\Models\StudentRombel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fase 01a — ekspor siswa (CSV) yang terlindungi.
 *
 * Penegakan izin (server):
 *   - `students.view` : wajib (ekspor tidak lebih longgar dari akses baca).
 *   - `students.export` : wajib untuk mengunduh CSV.
 * Middleware route `active` menolak akun nonaktif. Guest ditolak oleh auth:sanctum.
 *
 * Lingkup data & filter: sama dengan daftar siswa (tahun ajaran + status/entry/rombel
 * + pencarian), tanpa menambah data yang tidak boleh dilihat. Kolom eksplisit
 * (tidak mengekspor seluruh model/relasi). Semua nilai identitas dikirim sebagai
 * teks; nilai diskalakan sebagai teks agar nol awal/panjang tidak hilang.
 *
 * Batas baris: bila hasil filter melebihi batas (`students.export_max_rows`,
 * default 10000), ekspor DITOLAK dengan pesan
 * (bukan memotong hasil secara diam-diam). Pengguna diminta mempersempit filter.
 *
 * Catatan: `students.export` TIDAK mencegah penyalinan data yang memang sudah boleh
 * dilihat pengguna; ini mengendalikan unduhan berkas, bukan menjamin kerahasiaan
 * data yang sudah dapat diakses.
 */
class StudentExportController extends Controller
{
    /**
     * Batas maksimum baris ekspor. Hasil filter yang melebihi batas ditolak.
     * Dapat diturunkan lewat config saat pengujian (`students.export_max_rows`).
     */
    private function maxRows(): int
    {
        return (int) config('students.export_max_rows', 10000);
    }

    /**
     * Kolom eksplisit pada CSV (urutan & header English).
     *
     * @var array<string, array{0: string, 1: callable}>
     */
    private array $columns = [];

    public function __construct()
    {
        $this->columns = [
            'name' => ['Name', fn (StudentRombel $r) => $r->resolveStudent()?->name],
            'gender' => ['Gender', fn (StudentRombel $r) => $r->resolveStudent()?->gender?->name],
            'birthplace' => ['Birthplace', fn (StudentRombel $r) => $r->resolveStudent()?->birthplace],
            'birthdate' => ['Birthdate', fn (StudentRombel $r) => $r->resolveStudent()?->birthdate],
            'nik' => ['NIK', fn (StudentRombel $r) => $r->resolveStudent()?->nik],
            'nis' => ['NIS', fn (StudentRombel $r) => $r->resolveStudent()?->nis],
            'nisn' => ['NISN', fn (StudentRombel $r) => $r->resolveStudent()?->nisn],
            'school_rombel' => ['School Rombel', fn (StudentRombel $r) => $r->schoolRombel?->name],
            'student_entry' => ['Student Entry', fn (StudentRombel $r) => $r->studentEntry?->name],
            'student_status' => ['Student Status', fn (StudentRombel $r) => $r->studentStatus?->name],
        ];
    }

    /**
     * Unduh CSV siswa. Filter sama dengan daftar: `school_year_id` (wajib),
     * opsional `status`, `entry`, `rombel`, `search`.
     *
     * Bila jumlah baris hasil filter melebihi batas (`students.export_max_rows`),
     * ekspor ditolak (422) tanpa memotong data — pengguna diminta mempersempit filter.
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        // Wajib punya akses baca siswa...
        $this->authorize('students.view');
        // ...dan kemampuan ekspor eksplisit.
        $this->authorize('students.export');

        $validated = $request->validate([
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'status' => ['nullable', 'string', 'max:100'],
            'entry' => ['nullable', 'string', 'max:100'],
            'rombel' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = $this->buildQuery($validated);

        // Tolak bila hasil filter melebihi batas — jangan potong diam-diam.
        $maxRows = $this->maxRows();
        $total = (clone $query)->toBase()->getCountForPagination();
        if ($total > $maxRows) {
            return response()->json([
                'message' => 'The export matches '.$total.' rows, which exceeds the limit of '
                    .$maxRows.'. Please narrow your filters (for example by school year, '
                    .'rombel, status, entry, or search) and try again.',
            ], 422);
        }

        $filename = 'students_'.($validated['school_year_id'] ?? 'all').'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 agar Excel membaca aksen dengan benar.
            fwrite($out, "\xEF\xBB\xBF");

            // Header.
            $headers = array_map(fn ($c) => $c[0], $this->columns);
            $this->writeCsvRow($out, $headers);

            // Data (sudah dipastikan tidak melebihi batas).
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    $row = [];
                    foreach ($this->columns as [$header, $resolver]) {
                        $row[] = $resolver($r);
                    }
                    $this->writeCsvRow($out, $row);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Query dasar: sama dengan daftar siswa (berdasar tahun ajaran + filter),
     * hanya memilih kolom yang dibutuhkan dan memuat relasi eksplisit.
     *
     * @param  array<string, mixed>  $filters
     */
    private function buildQuery(array $filters): Builder
    {
        $query = StudentRombel::query()
            ->with([
                'student.gender',
                'studentNik.gender',
                'schoolRombel',
                'studentEntry',
                'studentStatus',
            ])
            ->whereNull('student_rombels.archived_at')
            ->whereHas('schoolRombel', function (Builder $q) use ($filters) {
                $q->where('school_year_id', $filters['school_year_id']);
            });

        if (! empty($filters['status'])) {
            $query->whereHas('studentStatus', fn (Builder $q) => $q->where('name', $filters['status']));
        }
        if (! empty($filters['entry'])) {
            $query->whereHas('studentEntry', fn (Builder $q) => $q->where('name', $filters['entry']));
        }
        if (! empty($filters['rombel'])) {
            $query->whereHas('schoolRombel', fn (Builder $q) => $q->where('name', $filters['rombel']));
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $sub) use ($search) {
                $sub->whereHas('student', function (Builder $q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                })->orWhereHas('studentNik', function (Builder $q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            });
        }

        return $query->orderBy('id');
    }

    /**
     * Tulis satu baris CSV dengan penanganan khusus:
     *  - nilai diperlakukan sebagai teks (nol awal dipertahankan);
     *  - karakter koma/kutip/newline di-escape sesuai RFC 4180 (fputcsv);
     *  - formula injection (=, +, -, @, TAB, CR) dicegah dengan prefix apostrof.
     *
     * @param  resource  $out
     * @param  array<int, mixed>  $row
     */
    private function writeCsvRow($out, array $row): void
    {
        fputcsv($out, array_map([$this, 'sanitizeCell'], $row), ',', '"', '\\');
    }

    /**
     * Normalisasi nilai sel: null → '', cegah formula injection, semua → string.
     */
    private function sanitizeCell($value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        // Cegah CSV formula injection bila sel diawali karakter berbahaya.
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            $value = "'".$value;
        }

        return $value;
    }
}
